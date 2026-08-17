<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/autoloader.php';

// Database configuration: server environment wins over the optional local .env file.
function loadLocalEnvFile(string $path): void {
    if (!is_file($path) || is_link($path) || !is_readable($path)) return;

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
    if ($value !== false) return (string)$value;
    if (array_key_exists($key, $_ENV)) return (string)$_ENV[$key];
    return $default;
}

function appConfigBool(string $key, bool $default = false): bool {
    $value = configValue($key);
    if ($value === '') return $default;

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed ?? $default;
}

function appRuntimeSchemaUpdatesEnabled(): bool {
    return defined('APP_RUNTIME_SCHEMA_UPDATES')
        && APP_RUNTIME_SCHEMA_UPDATES === true
        && PHP_SAPI === 'cli';
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
    $isAbsolute = str_starts_with($path, '/')
        || str_starts_with($path, '\\')
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    $candidate = $isAbsolute ? $path : dirname(__DIR__) . DIRECTORY_SEPARATOR . $path;
    $resolved = realpath($candidate);
    if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
        throw new RuntimeException('Configured database TLS ' . $label . ' file is not readable.');
    }
    return $resolved;
}

function appDbPdoOptions(array $config): array {
    $isPersistent = isset($config['persistent'])
        ? (bool)$config['persistent']
        : appConfigBool('MYSQL_PERSISTENT', appConfigBool('DB_PERSISTENT', false));

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_PERSISTENT => $isPersistent, // Defaults to PDO::ATTR_PERSISTENT => false
        PDO::ATTR_TIMEOUT => max(1, min(30, (int)($config['connect_timeout'] ?? 5))),
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_LOCAL_INFILE => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ];

    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] = false;
    }

    $configuredSslCert = trim((string)($config['ssl_cert'] ?? ''));
    $configuredSslKey = trim((string)($config['ssl_key'] ?? ''));
    $sslCa = appDbReadableTlsFile((string)($config['ssl_ca'] ?? ''), 'CA');
    if ($sslCa === '' && ($configuredSslCert !== '' || $configuredSslKey !== '')) {
        throw new RuntimeException('Database TLS CA is required when a client certificate or key is configured.');
    }
    if ($sslCa !== '') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        if (!defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            throw new RuntimeException('PDO MySQL cannot verify the database server certificate.');
        }
        $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;

        $sslCert = appDbReadableTlsFile($configuredSslCert, 'certificate');
        $sslKey = appDbReadableTlsFile($configuredSslKey, 'key');
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

function appDbValidateConnectionConfig(array $config, string $user, string $password, string $appEnv): void {
    appDbBuildDsn($config);
    if ($user === '' || preg_match('/[\x00-\x1F\x7F]/', $user)) {
        throw new RuntimeException('Invalid database user configuration.');
    }

    $environment = strtolower(trim($appEnv));
    $isLocalEnvironment = in_array($environment, ['local', 'dev', 'development', 'test', 'testing'], true);
    $isLocalEndpoint = appDbEndpointIsLocal($config);
    if ((!$isLocalEnvironment || !$isLocalEndpoint) && $password === '') {
        throw new RuntimeException('Empty database password is not allowed outside local development.');
    }
    if ((!$isLocalEnvironment || !$isLocalEndpoint) && strtolower(trim($user)) === 'root') {
        throw new RuntimeException('The root database account is not allowed outside local development.');
    }
}

function appDbEndpointIsLocal(array $config): bool {
    if (trim((string)($config['socket'] ?? '')) !== '') return true;
    $host = strtolower(trim((string)($config['host'] ?? '')));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function appDbConfigureSession(PDO $pdo, string $appEnv): void {
    $environment = strtolower(trim($appEnv));
    if (in_array($environment, ['local', 'dev', 'development', 'test', 'testing'], true)) return;

    $pdo->exec(
        "SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), "
        . "'STRICT_TRANS_TABLES', 'ERROR_FOR_DIVISION_BY_ZERO')"
    );
}

function appDbPathIsInsidePublicRoot(string $path): bool {
    $publicRoot = realpath(dirname(__DIR__));
    $directory = realpath(dirname($path));
    if ($publicRoot === false || $directory === false) return false;

    $publicPrefix = rtrim(str_replace('\\', '/', $publicRoot), '/') . '/';
    $directoryPath = rtrim(str_replace('\\', '/', $directory), '/') . '/';
    return str_starts_with(strtolower($directoryPath), strtolower($publicPrefix));
}

function appDbWriteFailureLog(Throwable $error): void {
    if (!defined('APP_DEBUG_LOG')) {
        define('APP_DEBUG_LOG', configValue('APP_DEBUG_LOG', dirname(__DIR__) . '/../logs/zsemtech-debug.log'));
    }

    $logDir = dirname(APP_DEBUG_LOG);
    if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
    if (!is_dir($logDir) || is_link(APP_DEBUG_LOG) || appDbPathIsInsidePublicRoot(APP_DEBUG_LOG)) return;

    $errorCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$error->getCode()) ?: 'unknown';
    $line = '[' . date('Y-m-d H:i:s') . '] DB connect failed; code=' . $errorCode . PHP_EOL;
    $handle = @fopen(APP_DEBUG_LOG, 'ab');
    if ($handle === false) return;
    @chmod(APP_DEBUG_LOG, 0640);

    try {
        if (!@flock($handle, LOCK_EX)) return;
        $stats = @fstat($handle);
        if (is_array($stats) && (int)($stats['size'] ?? 0) < 1024 * 1024) {
            @fwrite($handle, $line);
            @fflush($handle);
        }
        @flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

define('APP_ENV', configValue('APP_ENV', 'local'));
define('APP_RUNTIME_SCHEMA_UPDATES', appConfigBool('APP_RUNTIME_SCHEMA_UPDATES', false));
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
    'persistent' => appConfigBool('MYSQL_PERSISTENT', appConfigBool('DB_PERSISTENT', false)),
    'connect_timeout' => appDbConfigInt('MYSQL_CONNECT_TIMEOUT', 5, 1, 30, 'DB_CONNECT_TIMEOUT'),
    'ssl_ca' => configValue('MYSQL_SSL_CA'),
    'ssl_cert' => configValue('MYSQL_SSL_CERT'),
    'ssl_key' => configValue('MYSQL_SSL_KEY'),
];

if (defined('APP_DB_SKIP_CONNECT') && APP_DB_SKIP_CONNECT === true) {
    if (!\App\Core\Engine::getInstance()->isBooted()) {
        \App\Core\Engine::getInstance()->boot();
    }
} else {
    try {
        appDbValidateConnectionConfig($appDbConfig, DB_USER, DB_PASS, APP_ENV);
        $pdo = new PDO(appDbBuildDsn($appDbConfig), DB_USER, DB_PASS, appDbPdoOptions($appDbConfig));
        appDbConfigureSession($pdo, APP_ENV);
        if (!\App\Core\Engine::getInstance()->isBooted()) {
            \App\Core\Engine::getInstance()->boot();
        }
    } catch (Throwable $error) {
        error_log('Database connection failed.');
        appDbWriteFailureLog($error);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code(503);
            header('Retry-After: 30');
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: no-referrer');
            header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        }
        die('Błąd połączenia z bazą danych. Spróbuj ponownie później.');
    }
}

if (!function_exists('dbQueryCached')) {
    function dbQueryCached(\PDO $pdo, string $sql, array $params = [], int $ttl = 300, bool $fetchOne = false, array $tags = []) {
        static $memoryCache = [];
        static $dbQueryCount = 0;
        static $dbQueryTimeMs = 0.0;

        $cacheKey = 'sql_' . md5($sql . '|' . json_encode($params)) . ($fetchOne ? '_one' : '_all');
        if (array_key_exists($cacheKey, $memoryCache)) {
            return $memoryCache[$cacheKey];
        }

        $dbQueryCount++;
        $startTime = microtime(true);

        $engine = \App\Core\Engine::getInstance();
        $cache = ($engine && $engine->isBooted()) ? $engine->getCache() : null;

        $isDbExecution = false;
        if ($cache) {
            $result = $cache->remember($cacheKey, $ttl, function() use ($pdo, $sql, $params, $fetchOne, &$isDbExecution) {
                $isDbExecution = true;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $fetchOne ? $stmt->fetch(\PDO::FETCH_ASSOC) : $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }, $tags);
        } else {
            $isDbExecution = true;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $fetchOne ? $stmt->fetch(\PDO::FETCH_ASSOC) : $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $elapsed = (microtime(true) - $startTime) * 1000;
        $dbQueryTimeMs += $elapsed;
        if ($engine && $engine->isBooted() && $engine->getResponseBuffer()) {
            $engine->getResponseBuffer()->addTiming('db', $dbQueryTimeMs, "DB ({$dbQueryCount} queries)");
        }

        // Slow query interception: threshold >= 100ms
        if ($isDbExecution && $elapsed >= 100.0) {
            if (class_exists('\\App\\Core\\Logger')) {
                \App\Core\Logger::getInstance()->slowQuery($sql, $params, round($elapsed, 2), [
                    'caller' => 'dbQueryCached',
                    'cached' => false,
                    'query_count' => $dbQueryCount,
                    'fetch_mode' => $fetchOne ? 'fetchOne' : 'fetchAll',
                    'tags' => $tags,
                ]);
            }
        }

        $memoryCache[$cacheKey] = $result;
        return $result;
    }
}

if (!function_exists('dbQuery')) {
    function dbQuery(string $sql, array $params = [], bool $fetchOne = false) {
        global $pdo;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $fetchOne ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}