<?php
define('APP_DB_SKIP_CONNECT', true);
require dirname(__DIR__) . '/config/db.php';

function checkDbConnectionConfig(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$dsn = appDbBuildDsn([
    'host' => '127.0.0.1',
    'port' => 3307,
    'database' => 'exam_platform',
]);
checkDbConnectionConfig($dsn === 'mysql:host=127.0.0.1;port=3307;dbname=exam_platform;charset=utf8mb4', 'TCP DSN changed');

$socketDsn = appDbBuildDsn([
    'socket' => '/run/mysqld/mysqld.sock',
    'database' => 'exam_platform',
]);
checkDbConnectionConfig($socketDsn === 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=exam_platform;charset=utf8mb4', 'socket DSN changed');

$rejectedInjection = false;
try {
    appDbBuildDsn(['host' => 'localhost;port=1', 'database' => 'exam_platform']);
} catch (RuntimeException $error) {
    $rejectedInjection = true;
}
checkDbConnectionConfig($rejectedInjection, 'DSN option injection was accepted');

$options = appDbPdoOptions(['connect_timeout' => 999]);
checkDbConnectionConfig($options[PDO::ATTR_ERRMODE] === PDO::ERRMODE_EXCEPTION, 'PDO exceptions disabled');
checkDbConnectionConfig($options[PDO::ATTR_EMULATE_PREPARES] === false, 'emulated prepares enabled');
checkDbConnectionConfig($options[PDO::ATTR_PERSISTENT] === false, 'persistent connections enabled');
checkDbConnectionConfig($options[PDO::ATTR_TIMEOUT] === 30, 'connection timeout not bounded');
checkDbConnectionConfig($options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] === true, 'buffered queries disabled');
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    checkDbConnectionConfig($options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] === false, 'multiple statements enabled');
}

$tempEnv = tempnam(sys_get_temp_dir(), 'zsem-db-env-');
file_put_contents($tempEnv, "\xEF\xBB\xBFZSEM_DB_TEST_VALUE=loaded\ninvalid-key=ignored\nZSEM_DB_TEST_QUOTED=\"value # kept\"\n");
loadLocalEnvFile($tempEnv);
@unlink($tempEnv);
checkDbConnectionConfig(configValue('ZSEM_DB_TEST_VALUE') === 'loaded', '.env value was not loaded');
checkDbConnectionConfig(configValue('ZSEM_DB_TEST_QUOTED') === 'value # kept', 'quoted .env value changed');
checkDbConnectionConfig(getenv('invalid-key') === false, 'invalid .env key was loaded');

putenv('ZSEM_DB_TEST_VALUE');
putenv('ZSEM_DB_TEST_QUOTED');
unset($_ENV['ZSEM_DB_TEST_VALUE'], $_ENV['ZSEM_DB_TEST_QUOTED']);

echo "database connection config runtime OK\n";
