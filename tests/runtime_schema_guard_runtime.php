<?php
declare(strict_types=1);

putenv('APP_RUNTIME_SCHEMA_UPDATES=false');
$_ENV['APP_RUNTIME_SCHEMA_UPDATES'] = 'false';
define('APP_DB_SKIP_CONNECT', true);
require dirname(__DIR__) . '/config/db.php';
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/auth.php';

final class SchemaGuardSpyPdo extends PDO {
    public array $calls = [];

    public function __construct() {}

    public function exec(string $statement): int|false {
        $this->calls[] = ['exec', $statement];
        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->calls[] = ['prepare', $query];
        return false;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->calls[] = ['query', $query];
        return false;
    }
}

function checkSchemaGuard(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

checkSchemaGuard(function_exists('appRuntimeSchemaUpdatesEnabled'), 'schema update policy helper is missing');
checkSchemaGuard(appRuntimeSchemaUpdatesEnabled() === false, 'runtime schema updates are enabled by default');

$pdo = new SchemaGuardSpyPdo();
dbAddColumnIfMissing($pdo, 'users', 'guard_test', 'INT DEFAULT NULL');
dbAddIndexIfMissing($pdo, 'users', 'idx_guard_test', '(guard_test)');
ensurePlatformEnhancements($pdo);
ensureUserActiveTestsTable($pdo);
ensureDuelModeColumns($pdo);
ensureAdminRequestsTableExists($pdo);
ensureActiveSessionTable($pdo);
createRegistrationAttemptsTable();
createLoginAttemptsTable();

checkSchemaGuard($pdo->calls === [], 'schema helpers contacted the database while runtime updates were disabled');

echo "runtime schema guard OK\n";
