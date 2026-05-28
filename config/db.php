<?php
// Database configuration - reads environment/.env first, then safe local defaults.
function loadLocalEnvFile($path) {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        $value = trim($value, "\"'");
        if (getenv($key) === false && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

loadLocalEnvFile(__DIR__ . '/../.env');

function configValue($key, $default = '') {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    return $default;
}

define('APP_ENV', configValue('APP_ENV', 'local'));
$genericDbLooksPostgres = configValue('DB_PORT') === '5432'
    || strtolower(configValue('DB_USER')) === 'postgres'
    || strtolower(configValue('DB_DRIVER')) === 'pgsql';
define('DB_HOST', configValue('MYSQL_HOST', $genericDbLooksPostgres ? 'localhost' : configValue('DB_HOST', 'localhost')));
define('DB_NAME', configValue('MYSQL_DATABASE', $genericDbLooksPostgres ? 'rafifafi_egzamin' : configValue('DB_NAME', 'rafifafi_egzamin')));
define('DB_USER', configValue('MYSQL_USER', $genericDbLooksPostgres ? 'root' : configValue('DB_USER', 'root')));
define('DB_PASS', configValue('MYSQL_PASSWORD', configValue('MYSQL_PASS', $genericDbLooksPostgres ? '' : configValue('DB_PASS', configValue('DB_PASSWORD', '')))));

// PDO Connection
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed.');
    if (!defined('APP_DEBUG_LOG')) {
        define('APP_DEBUG_LOG', configValue('APP_DEBUG_LOG', dirname(__DIR__) . '/../logs/zsemtech-debug.log'));
    }
    $logDir = dirname(APP_DEBUG_LOG);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    @file_put_contents(APP_DEBUG_LOG, '[' . date('Y-m-d H:i:s') . '] DB connect failed.' . PHP_EOL, FILE_APPEND | LOCK_EX);
    die('Błąd połączenia z bazą danych. Spróbuj ponownie później.');
}
