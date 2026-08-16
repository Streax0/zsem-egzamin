<?php
declare(strict_types=1);

namespace App\Core;

class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    private const LEVEL_PRIORITY = [
        self::LEVEL_DEBUG => 100,
        self::LEVEL_INFO => 200,
        self::LEVEL_WARNING => 300,
        self::LEVEL_ERROR => 400,
        self::LEVEL_CRITICAL => 500,
    ];

    private static ?Logger $instance = null;

    private string $logDir;
    private string $minLevel;
    private int $maxFileSize;
    private int $maxFiles;
    private float $slowQueryThresholdMs;

    private array $logs = [];
    private array $slowQueries = [];

    public function __construct(
        ?string $logDir = null,
        string $minLevel = self::LEVEL_DEBUG,
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5,
        float $slowQueryThresholdMs = 100.0
    ) {
        $this->logDir = rtrim($logDir ?? __DIR__ . '/../../../data/logs', '/\\');
        $this->minLevel = strtoupper($minLevel);
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
        $this->ensureDirectoryExists($this->logDir);
    }

    public static function getInstance(?string $logDir = null): Logger
    {
        if (self::$instance === null) {
            $minLevel = (defined('APP_ENV') && in_array(APP_ENV, ['prod', 'production'], true))
                ? self::LEVEL_INFO
                : self::LEVEL_DEBUG;
            self::$instance = new self($logDir, $minLevel);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private function getRequestId(): string
    {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = function_exists('securityRequestId') ? securityRequestId() : ('req_' . bin2hex(random_bytes(6)));
        }
        return $requestId;
    }

    private function getClientIp(): string
    {
        if (function_exists('securityClientIp')) {
            return securityClientIp();
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function rotateIfNeeded(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $size = @filesize($filePath);
        if ($size === false || $size < $this->maxFileSize) {
            return;
        }

        // 1. Remove oldest archive if exists
        $oldest = "{$filePath}.{$this->maxFiles}";
        if (file_exists($oldest)) {
            @unlink($oldest);
        }

        // 2. Shift older archives up by 1
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $source = "{$filePath}.{$i}";
            $target = "{$filePath}." . ($i + 1);
            if (file_exists($source)) {
                @rename($source, $target);
            }
        }

        // 3. Rename current active log to .1
        @rename($filePath, "{$filePath}.1");
    }

    private function writeToFile(string $filePath, array $entry): void
    {
        $this->ensureDirectoryExists($this->logDir);
        $this->rotateIfNeeded($filePath);

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode([
                'timestamp' => date('c'),
                'level' => self::LEVEL_ERROR,
                'message' => 'Failed to JSON encode log entry: ' . json_last_error_msg(),
            ]);
        }
        $line = $json . PHP_EOL;

        $handle = @fopen($filePath, 'ab');
        if ($handle === false) {
            error_log("Logger failed to open log file: {$filePath}");
            return;
        }

        if (flock($handle, LOCK_EX)) {
            fwrite($handle, $line);
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        $this->rotateIfNeeded($filePath);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $levelUpper = strtoupper(trim($level));
        if (!isset(self::LEVEL_PRIORITY[$levelUpper])) {
            $levelUpper = self::LEVEL_INFO;
        }

        $minPriority = self::LEVEL_PRIORITY[$this->minLevel] ?? self::LEVEL_DEBUG;
        $currentPriority = self::LEVEL_PRIORITY[$levelUpper] ?? self::LEVEL_INFO;

        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $levelUpper,
            'message' => $message,
            'context' => $context,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'request_id' => $this->getRequestId(),
            'ip' => $this->getClientIp(),
        ];

        $this->logs[] = $entry;

        if ($currentPriority < $minPriority) {
            return;
        }

        $filePath = $this->logDir . '/app.log';
        $this->writeToFile($filePath, $entry);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    public function slowQuery(string $sql, array $params, float $durationMs, array $context = []): void
    {
        if ($durationMs < $this->slowQueryThresholdMs) {
            return;
        }

        $filePath = $this->logDir . '/slow_queries.log';
        $caller = $context['caller'] ?? $this->detectCaller();

        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => self::LEVEL_WARNING,
            'type' => 'slow_query',
            'duration_ms' => round($durationMs, 2),
            'threshold_ms' => $this->slowQueryThresholdMs,
            'sql' => $sql,
            'params' => $params,
            'caller' => $caller,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'cli' : 'unknown'),
            'ip' => $this->getClientIp(),
            'context' => $context,
        ];

        $this->slowQueries[] = $entry;
        $this->writeToFile($filePath, $entry);
    }

    private function detectCaller(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? 0;
            $func = $frame['function'] ?? '';
            if (str_contains($file, 'Logger.php') || str_contains($file, 'db.php')) {
                continue;
            }
            $relFile = str_replace('\\', '/', $file);
            $root = str_replace('\\', '/', dirname(__DIR__, 3));
            $relFile = str_replace($root . '/', '', $relFile);
            return "{$func} ({$relFile}:{$line})";
        }
        return 'unknown';
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getSlowQueries(): array
    {
        return $this->slowQueries;
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }

    // Static Proxy Facades
    public static function debugStatic(string $message, array $context = []): void { self::getInstance()->debug($message, $context); }
    public static function infoStatic(string $message, array $context = []): void { self::getInstance()->info($message, $context); }
    public static function warningStatic(string $message, array $context = []): void { self::getInstance()->warning($message, $context); }
    public static function errorStatic(string $message, array $context = []): void { self::getInstance()->error($message, $context); }
    public static function criticalStatic(string $message, array $context = []): void { self::getInstance()->critical($message, $context); }
    public static function slowQueryStatic(string $sql, array $params, float $durationMs, array $context = []): void { self::getInstance()->slowQuery($sql, $params, $durationMs, $context); }
    public static function logStatic(string $level, string $message, array $context = []): void { self::getInstance()->log($level, $message, $context); }

    public static function __callStatic(string $name, array $arguments)
    {
        $instance = self::getInstance();
        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }
        throw new \BadMethodCallException("Method Logger::{$name} does not exist.");
    }
}
