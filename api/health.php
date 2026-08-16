<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/autoloader.php';

function getSystemHealthReport(?PDO $pdo = null, bool $detailed = false): array
{
    $startTime = microtime(true);
    $status = 'healthy';
    $checks = [];

    // 1. Database Ping & Latency Check
    $dbPing = null;
    $dbLatencyMs = 0.0;
    $dbError = null;
    if ($pdo instanceof PDO) {
        $dbStart = microtime(true);
        try {
            $stmt = $pdo->query('SELECT 1');
            if ($stmt && $stmt->fetchColumn() == 1) {
                $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);
                $dbPing = 'ok';
            } else {
                $dbPing = 'unexpected_response';
                $status = 'degraded';
            }
        } catch (Throwable $e) {
            $dbPing = 'failed';
            $dbError = $e->getMessage();
            $status = 'unhealthy';
        }
    } else {
        $dbPing = 'no_connection';
        $status = 'unhealthy';
    }

    $checks['database'] = [
        'status' => $dbPing === 'ok' ? 'up' : 'down',
        'ping' => $dbPing,
        'latency_ms' => $dbLatencyMs,
        'driver' => $pdo ? $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : 'none',
    ];
    if ($dbError && $detailed) {
        $checks['database']['error'] = $dbError;
    }

    // 2. Memory Usage & Peak
    $memUsage = memory_get_usage(true);
    $memPeak = memory_get_peak_usage(true);
    $memLimit = ini_get('memory_limit');
    $memLimitBytes = parseIniSizeToBytes((string)$memLimit);
    $memPct = $memLimitBytes > 0 ? round(($memUsage / $memLimitBytes) * 100, 1) : 0.0;

    $checks['memory'] = [
        'current_bytes' => $memUsage,
        'current_mb' => round($memUsage / 1024 / 1024, 2),
        'peak_bytes' => $memPeak,
        'peak_mb' => round($memPeak / 1024 / 1024, 2),
        'limit' => $memLimit,
        'used_percent' => $memPct,
    ];

    // 3. Cache & OPcache Status
    $engine = class_exists('\\App\\Core\\Engine') ? \App\Core\Engine::getInstance() : null;
    $cacheManager = ($engine && $engine->isBooted()) ? $engine->getCache() : new \App\Core\CacheManager();
    $cacheStats = $cacheManager ? $cacheManager->getStats() : [];

    $opcacheStatus = false;
    $opcacheDetails = [];
    if (function_exists('opcache_get_status')) {
        $rawOpcache = @opcache_get_status(false);
        if (is_array($rawOpcache) && !empty($rawOpcache['opcache_enabled'])) {
            $opcacheStatus = true;
            $opcacheDetails = [
                'enabled' => true,
                'hit_rate' => round($rawOpcache['opcache_statistics']['opcache_hit_rate'] ?? 0, 1),
                'cached_scripts' => $rawOpcache['opcache_statistics']['num_cached_scripts'] ?? 0,
                'memory_used_mb' => round(($rawOpcache['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2),
                'memory_free_mb' => round(($rawOpcache['memory_usage']['free_memory'] ?? 0) / 1024 / 1024, 2),
                'jit_enabled' => !empty($rawOpcache['jit']['enabled']),
            ];
        }
    }

    $checks['cache'] = [
        'app_cache' => $cacheStats,
        'opcache' => $opcacheStatus ? $opcacheDetails : ['enabled' => false],
    ];

    // 4. Writable Storage Directories Status
    $root = dirname(__DIR__);
    $dirs = [
        'data' => $root . '/data',
        'cache' => $root . '/data/cache',
        'logs' => $root . '/data/logs',
        'config' => $root . '/data/config',
        'backups' => $root . '/data/backups',
        'abuse_reports' => $root . '/data/abuse_reports',
        'custom_tests' => $root . '/data/custom_tests',
        'pdfs' => $root . '/data/pdfs',
    ];

    $dirStatus = [];
    $allWritable = true;
    foreach ($dirs as $label => $path) {
        $exists = is_dir($path);
        if (!$exists) {
            @mkdir($path, 0755, true);
            $exists = is_dir($path);
        }
        $writable = $exists && is_writable($path);
        if (!$writable) {
            $allWritable = false;
        }
        $dirStatus[$label] = [
            'path' => $label,
            'exists' => $exists,
            'writable' => $writable,
        ];
    }
    if (!$allWritable && $status === 'healthy') {
        $status = 'degraded';
    }

    $checks['storage'] = [
        'all_writable' => $allWritable,
        'directories' => $dirStatus,
        'free_space_mb' => is_dir($root) ? round(@disk_free_space($root) / 1024 / 1024, 2) : null,
    ];

    $totalDurationMs = round((microtime(true) - $startTime) * 1000, 2);

    return [
        'status' => $status,
        'timestamp' => time(),
        'execution_time_ms' => $totalDurationMs,
        'checks' => $checks,
    ];
}

function parseIniSizeToBytes(string $size): int
{
    $size = trim($size);
    if ($size === '' || $size === '-1') {
        return -1;
    }
    $unit = strtolower(substr($size, -1));
    $value = (int)$size;
    return match ($unit) {
        'g' => $value * 1024 * 1024 * 1024,
        'm' => $value * 1024 * 1024,
        'k' => $value * 1024,
        default => $value,
    };
}

// Standalone execution if accessed directly
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'health.php' && PHP_SAPI !== 'cli') {
    startSecureSession();
    if (function_exists('securityApplyJsonHeaders')) {
        securityApplyJsonHeaders();
    } else {
        header('Content-Type: application/json; charset=utf-8');
    }
    $report = getSystemHealthReport($pdo ?? null, false);
    $httpCode = $report['status'] === 'unhealthy' ? 503 : 200;
    \App\Core\ApiRouter::sendResponse($report['status'] !== 'unhealthy', $report, null, $httpCode);
}
