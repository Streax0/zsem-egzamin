<?php
define('APP_DB_SKIP_CONNECT', true);
require dirname(__DIR__) . '/config/db.php';

function checkDbConnectionConfig(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class DbSessionConfigPdoStub extends PDO {
    public array $executedSql = [];
    public int $queryCount = 0;

    public function __construct() {}

    public function exec(string $statement): int|false {
        $this->executedSql[] = $statement;
        return 0;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->queryCount++;
        return false;
    }
}

$dsn = appDbBuildDsn([
    'host' => '127.0.0.1',
    'port' => 3307,
    'database' => 'exam_platform',
]);
checkDbConnectionConfig($dsn === 'mysql:host=127.0.0.1;port=3307;dbname=exam_platform;charset=utf8mb4', 'TCP DSN changed');
checkDbConnectionConfig(APP_RUNTIME_SCHEMA_UPDATES === false, 'runtime schema updates enabled by default');
checkDbConnectionConfig(appRuntimeSchemaUpdatesEnabled() === false, 'runtime schema update policy bypassed default');

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

appDbValidateConnectionConfig(['host' => 'localhost', 'database' => 'exam_platform'], 'root', '', 'local');
$rejectedRemoteLocalRoot = false;
try {
    appDbValidateConnectionConfig(['host' => 'db.example.test', 'database' => 'exam_platform'], 'root', '', 'local');
} catch (RuntimeException $error) {
    $rejectedRemoteLocalRoot = true;
}
checkDbConnectionConfig($rejectedRemoteLocalRoot, 'local environment allowed weak credentials for a remote database');
checkDbConnectionConfig(appDbEndpointIsLocal(['host' => '127.0.0.1']), 'loopback database was not recognized');
checkDbConnectionConfig(appDbEndpointIsLocal(['socket' => '/run/mysqld/mysqld.sock']), 'socket database was not recognized');
checkDbConnectionConfig(!appDbEndpointIsLocal(['host' => 'db.example.test']), 'remote database was treated as local');
$rejectedProductionEmptyPassword = false;
try {
    appDbValidateConnectionConfig(['host' => 'db.internal', 'database' => 'exam_platform'], 'app_user', '', 'production');
} catch (RuntimeException $error) {
    $rejectedProductionEmptyPassword = true;
}
checkDbConnectionConfig($rejectedProductionEmptyPassword, 'empty production database password was accepted');

$rejectedUnknownEnvironment = false;
try {
    appDbValidateConnectionConfig(['host' => 'db.internal', 'database' => 'exam_platform'], 'app_user', '', 'prodution');
} catch (RuntimeException $error) {
    $rejectedUnknownEnvironment = true;
}
checkDbConnectionConfig($rejectedUnknownEnvironment, 'unknown environment weakened database password policy');

$rejectedProductionRoot = false;
try {
    appDbValidateConnectionConfig(['host' => 'db.internal', 'database' => 'exam_platform'], 'root', 'secret', 'staging');
} catch (RuntimeException $error) {
    $rejectedProductionRoot = true;
}
checkDbConnectionConfig($rejectedProductionRoot, 'root database account was accepted outside local development');

$rejectedInvalidUser = false;
try {
    appDbValidateConnectionConfig(['host' => 'localhost', 'database' => 'exam_platform'], "app\nuser", 'secret', 'local');
} catch (RuntimeException $error) {
    $rejectedInvalidUser = true;
}
checkDbConnectionConfig($rejectedInvalidUser, 'invalid database user was accepted');

$sessionPdo = new DbSessionConfigPdoStub();
appDbConfigureSession($sessionPdo, 'production');
checkDbConnectionConfig($sessionPdo->queryCount === 0, 'production session configuration used an extra read query');
checkDbConnectionConfig(count($sessionPdo->executedSql) === 1, 'production session configuration did not use one statement');
checkDbConnectionConfig(str_contains($sessionPdo->executedSql[0], 'STRICT_TRANS_TABLES'), 'strict SQL mode was not configured');
$localSessionPdo = new DbSessionConfigPdoStub();
appDbConfigureSession($localSessionPdo, 'local');
checkDbConnectionConfig($localSessionPdo->executedSql === [], 'local session configuration changed SQL mode');

$options = appDbPdoOptions(['connect_timeout' => 999]);
checkDbConnectionConfig($options[PDO::ATTR_ERRMODE] === PDO::ERRMODE_EXCEPTION, 'PDO exceptions disabled');
checkDbConnectionConfig($options[PDO::ATTR_EMULATE_PREPARES] === false, 'emulated prepares enabled');
checkDbConnectionConfig($options[PDO::ATTR_PERSISTENT] === false, 'persistent connections enabled');
checkDbConnectionConfig($options[PDO::ATTR_TIMEOUT] === 30, 'connection timeout not bounded');
checkDbConnectionConfig($options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] === true, 'buffered queries disabled');
checkDbConnectionConfig($options[PDO::MYSQL_ATTR_LOCAL_INFILE] === false, 'LOCAL INFILE enabled');
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    checkDbConnectionConfig($options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] === false, 'multiple statements enabled');
}

$rejectedIncompleteTls = false;
try {
    appDbPdoOptions(['ssl_cert' => __FILE__]);
} catch (RuntimeException $error) {
    $rejectedIncompleteTls = true;
}
checkDbConnectionConfig($rejectedIncompleteTls, 'TLS client certificate without CA was accepted');

$tlsOptions = appDbPdoOptions(['ssl_ca' => 'tests/db_connection_config_runtime.php']);
checkDbConnectionConfig(
    $tlsOptions[PDO::MYSQL_ATTR_SSL_CA] === realpath(__FILE__),
    'relative TLS CA path was not resolved against the application root'
);
checkDbConnectionConfig(
    $tlsOptions[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] === true,
    'TLS server certificate verification disabled'
);

$tempLog = tempnam(sys_get_temp_dir(), 'zsem-db-log-');
define('APP_DEBUG_LOG', $tempLog);
appDbWriteFailureLog(new RuntimeException('secret detail', 1045));
$failureLog = file_get_contents($tempLog);
checkDbConnectionConfig(str_contains($failureLog, 'code=1045'), 'database error code was not logged');
checkDbConnectionConfig(!str_contains($failureLog, 'secret detail'), 'database error detail leaked to log');
file_put_contents($tempLog, str_repeat('x', 1024 * 1024));
clearstatcache(true, $tempLog);
$cappedSize = filesize($tempLog);
appDbWriteFailureLog(new RuntimeException('another secret', 2002));
clearstatcache(true, $tempLog);
checkDbConnectionConfig(filesize($tempLog) === $cappedSize, 'database failure log exceeded size cap');
@unlink($tempLog);
checkDbConnectionConfig(appDbPathIsInsidePublicRoot(dirname(__DIR__) . '/config/test.log'), 'public log path was not detected');
checkDbConnectionConfig(!appDbPathIsInsidePublicRoot(sys_get_temp_dir() . '/zsemtech-test.log'), 'external log path was rejected');

$tempEnv = tempnam(sys_get_temp_dir(), 'zsem-db-env-');
putenv('ZSEM_DB_TEST_PRECEDENCE=server');
$_ENV['ZSEM_DB_TEST_PRECEDENCE'] = 'server';
file_put_contents($tempEnv, "\xEF\xBB\xBFZSEM_DB_TEST_VALUE=loaded\ninvalid-key=ignored\nZSEM_DB_TEST_QUOTED=\"value # kept\"\nZSEM_DB_TEST_PRECEDENCE=file\n");
loadLocalEnvFile($tempEnv);
@unlink($tempEnv);
checkDbConnectionConfig(configValue('ZSEM_DB_TEST_VALUE') === 'loaded', '.env value was not loaded');
checkDbConnectionConfig(configValue('ZSEM_DB_TEST_QUOTED') === 'value # kept', 'quoted .env value changed');
checkDbConnectionConfig(configValue('ZSEM_DB_TEST_PRECEDENCE') === 'server', '.env overrode server environment');
checkDbConnectionConfig(getenv('invalid-key') === false, 'invalid .env key was loaded');

putenv('ZSEM_DB_TEST_EMPTY=');
checkDbConnectionConfig(configValue('ZSEM_DB_TEST_EMPTY', 'fallback') === '', 'explicit empty environment value used fallback');

putenv('ZSEM_DB_TEST_VALUE');
putenv('ZSEM_DB_TEST_QUOTED');
putenv('ZSEM_DB_TEST_PRECEDENCE');
putenv('ZSEM_DB_TEST_EMPTY');
unset($_ENV['ZSEM_DB_TEST_VALUE'], $_ENV['ZSEM_DB_TEST_QUOTED'], $_ENV['ZSEM_DB_TEST_PRECEDENCE']);

echo "database connection config runtime OK\n";
