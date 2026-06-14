<?php
declare(strict_types=1);

// Database configuration: server environment wins over the optional local .env file.
function loadLocalEnvFile(string $path): void {
    if (!is_readable($path)) return;

    $size = @filesize($path);
    if (is_int($size) && $size > 1024 * 1024) {
        error_log('Local environment file is unexpectedly large; skipped.');
        return;
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) return;

    try {
        $firstLine = true;
        while (($line = fgets($handle)) !== false) {
            if ($firstLine) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                $firstLine = false;
            }

            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_starts_with($line, 'export ')) $line = trim(substr($line, 7));
            if (!str_contains($line, '=')) continue;

            [$key, $rawValue] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) continue;
            if (getenv($key) !== false || array_key_exists($key, $_ENV)) continue;

            $value = $rawValue;
            $length = strlen($value);
            if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"') || ($value[0] === "'" && $value[$length - 1] === "'"))) {
                $value = substr($value, 1, -1);
            } elseif (preg_match('/\s+#/', $value, $commentMatch, PREG_OFFSET_CAPTURE)) {
                $value = rtrim(substr($value, 0, $commentMatch[0][1]));
            }

            if (str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) continue;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    } finally {
        fclose($handle);
    }
}

loadLocalEnvFile(__DIR__ . '/../.env');

function configValue(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') return (string)$value;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return (string)$_ENV[$key];
    return $default;
}

function appDbConfigInt(string $key, int $default, int $min, int $max, ?string $fallbackKey = null): int {
    $raw = configValue($key, $fallbackKey !== null ? configValue($fallbackKey) : '');
    if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT) === false) return $default;
    return max($min, min($max, (int)$raw));
}

function appDbDsnValue(string $value, string $label): string {
    if ($value === '' || preg_match('/[;\x00-\x1F\x7F]/', $value)) {
        throw new RuntimeException('Invalid database ' . $label . ' configuration.');
    }
    return $value;
}

function appDbBuildDsn(array $config): string {
    $database = appDbDsnValue((string)($config['database'] ?? ''), 'name');
    $socket = trim((string)($config['socket'] ?? ''));
    if ($socket !== '') {
        $dsn = 'mysql:unix_socket=' . appDbDsnValue($socket, 'socket');
    } else {
        $host = appDbDsnValue((string)($config['host'] ?? ''), 'host');
        $port = max(1, min(65535, (int)($config['port'] ?? 3306)));
        $dsn = 'mysql:host=' . $host . ';port=' . $port;
    }
    return $dsn . ';dbname=' . $database . ';charset=utf8mb4';
}

function appDbReadableTlsFile(string $path, string $label): string {
    $path = trim($path);
    if ($path === '') return '';
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Configured database TLS ' . $label . ' file is not readable.');
    }
    return $path;
}

function appDbPdoOptions(array $config): array {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => max(1, min(30, (int)($config['connect_timeout'] ?? 5))),
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ];

    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] = false;
    }

    $sslCa = appDbReadableTlsFile((string)($config['ssl_ca'] ?? ''), 'CA');
    if ($sslCa !== '') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
        }

        $sslCert = appDbReadableTlsFile((string)($config['ssl_cert'] ?? ''), 'certificate');
        $sslKey = appDbReadableTlsFile((string)($config['ssl_key'] ?? ''), 'key');
        if (($sslCert === '') !== ($sslKey === '')) {
            throw new RuntimeException('Database TLS certificate and key must be configured together.');
        }
        if ($sslCert !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CERT] = $sslCert;
            $options[PDO::MYSQL_ATTR_SSL_KEY] = $sslKey;
        }
    }

    return $options;
}

function appDbWriteFailureLog(Throwable $error): void {
    if (!defined('APP_DEBUG_LOG')) {
        define('APP_DEBUG_LOG', configValue('APP_DEBUG_LOG', dirname(__DIR__) . '/../logs/zsemtech-debug.log'));
    }

    $logDir = dirname(APP_DEBUG_LOG);
    if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
    $errorCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$error->getCode()) ?: 'unknown';
    @file_put_contents(
        APP_DEBUG_LOG,
        '[' . date('Y-m-d H:i:s') . '] DB connect failed; code=' . $errorCode . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

define('APP_ENV', configValue('APP_ENV', 'local'));
$genericDbLooksPostgres = configValue('DB_PORT') === '5432'
    || strtolower(configValue('DB_USER')) === 'postgres'
    || strtolower(configValue('DB_DRIVER')) === 'pgsql';

define('DB_HOST', configValue('MYSQL_HOST', $genericDbLooksPostgres ? 'localhost' : configValue('DB_HOST', 'localhost')));
define('DB_PORT', appDbConfigInt('MYSQL_PORT', 3306, 1, 65535, $genericDbLooksPostgres ? null : 'DB_PORT'));
define('DB_NAME', configValue('MYSQL_DATABASE', $genericDbLooksPostgres ? 'rafifafi_egzamin' : configValue('DB_NAME', 'rafifafi_egzamin')));
define('DB_USER', configValue('MYSQL_USER', $genericDbLooksPostgres ? 'root' : configValue('DB_USER', 'root')));
define('DB_PASS', configValue('MYSQL_PASSWORD', configValue('MYSQL_PASS', $genericDbLooksPostgres ? '' : configValue('DB_PASS', configValue('DB_PASSWORD', '')))));

$appDbConfig = [
    'host' => DB_HOST,
    'port' => DB_PORT,
    'socket' => configValue('MYSQL_SOCKET'),
    'database' => DB_NAME,
    'connect_timeout' => appDbConfigInt('MYSQL_CONNECT_TIMEOUT', 5, 1, 30, 'DB_CONNECT_TIMEOUT'),
    'ssl_ca' => configValue('MYSQL_SSL_CA'),
    'ssl_cert' => configValue('MYSQL_SSL_CERT'),
    'ssl_key' => configValue('MYSQL_SSL_KEY'),
];

if (!defined('APP_DB_SKIP_CONNECT') || APP_DB_SKIP_CONNECT !== true) {
    try {
        $pdo = new PDO(appDbBuildDsn($appDbConfig), DB_USER, DB_PASS, appDbPdoOptions($appDbConfig));
    } catch (Throwable $error) {
        error_log('Database connection failed.');
        appDbWriteFailureLog($error);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(503);
            header('Retry-After: 30');
            header('Cache-Control: no-store');
        }
        die('Błąd połączenia z bazą danych. Spróbuj ponownie później.');
    }
}
