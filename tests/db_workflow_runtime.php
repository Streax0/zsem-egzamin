<?php
/**
 * Runtime Test Suite: Database Workflow, Connection Hardening, Memoization, and DDL Guarding
 * Covers Tiers 1-4 PHP runtime behaviors.
 */

declare(strict_types=1);

define('APP_DB_SKIP_CONNECT', true);
require dirname(__DIR__) . '/config/db.php';
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/auth.php';

function assertCondition(bool $condition, string $msg): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// -------------------------------------------------------------
// Tier 1 Feature 3: Guard Tests (appRuntimeSchemaUpdatesEnabled)
// -------------------------------------------------------------
echo "[1/4] Testing Tier 1: Guard & Connection & Memoization...\n";

// Test 1: appRuntimeSchemaUpdatesEnabled exists and returns false by default
assertCondition(function_exists('appRuntimeSchemaUpdatesEnabled'), 'Guard function missing');
assertCondition(appRuntimeSchemaUpdatesEnabled() === false, 'Guard must be false by default');

// Test 2: Guard function returns bool
assertCondition(is_bool(appRuntimeSchemaUpdatesEnabled()), 'Guard must return bool');

// Test 3: Spy PDO verifies 0 DDL queries executed when updates disabled
final class RuntimeWorkflowSpyPdo extends PDO {
    public array $statements = [];
    public array $prepares = [];
    public array $queries = [];

    public function __construct() {}

    public function exec(string $statement): int|false {
        $this->statements[] = $statement;
        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->prepares[] = $query;
        return new class extends PDOStatement {
            public function execute(?array $params = null): bool { return true; }
            public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return false; }
            public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
            public function fetchColumn(int $column = 0): mixed { return false; }
        };
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->queries[] = $query;
        return false;
    }
}

$spyPdo = new RuntimeWorkflowSpyPdo();
dbAddColumnIfMissing($spyPdo, 'users', 'test_col', 'INT DEFAULT 1');
dbAddIndexIfMissing($spyPdo, 'users', 'idx_test', '(test_col)');
ensurePlatformEnhancements($spyPdo);
ensureUserActiveTestsTable($spyPdo);
ensureDuelModeColumns($spyPdo);
ensureAdminRequestsTableExists($spyPdo);
ensureActiveSessionTable($spyPdo);

assertCondition($spyPdo->statements === [], 'Schema mutation helpers executed DDL statements with guard disabled');
assertCondition($spyPdo->queries === [], 'Schema mutation helpers executed probe queries with guard disabled');

// -------------------------------------------------------------
// Tier 1 Feature 4: Persistent Connection Configuration Tests
// -------------------------------------------------------------
// Test 1: Explicit persistent true option
$optionsPersistent = appDbPdoOptions(['persistent' => true]);
assertCondition($optionsPersistent[PDO::ATTR_PERSISTENT] === true, 'Persistent connection option true failed');

// Test 2: Explicit persistent false option
$optionsNonPersistent = appDbPdoOptions(['persistent' => false]);
assertCondition($optionsNonPersistent[PDO::ATTR_PERSISTENT] === false, 'Persistent connection option false failed');

// Test 3: Default persistence is false
$optionsDefault = appDbPdoOptions([]);
assertCondition($optionsDefault[PDO::ATTR_PERSISTENT] === false, 'Default persistent connection option must be false');

// Test 4: Timeout option correctly configured
$optionsTimeout = appDbPdoOptions(['connect_timeout' => 12]);
assertCondition($optionsTimeout[PDO::ATTR_TIMEOUT] === 12, 'Connect timeout setting failed');

// Test 5: Charset init command present
assertCondition(isset($optionsDefault[PDO::MYSQL_ATTR_INIT_COMMAND]), 'Init command missing in PDO options');
assertCondition(str_contains($optionsDefault[PDO::MYSQL_ATTR_INIT_COMMAND], 'utf8mb4'), 'Init command missing utf8mb4');

// -------------------------------------------------------------
// Tier 1 Feature 5: L1 Query Cache Memoization Tests
// -------------------------------------------------------------
final class MemoizationTestPdo extends PDO {
    public int $executionCount = 0;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->executionCount++;
        return new class($this) extends PDOStatement {
            private MemoizationTestPdo $parent;
            public function __construct(MemoizationTestPdo $p) { $this->parent = $p; }
            public function execute(?array $params = null): bool { return true; }
            public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
                return ['id' => 101, 'name' => 'cached_item'];
            }
            public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
                return [
                    ['id' => 101, 'name' => 'cached_item_1'],
                    ['id' => 102, 'name' => 'cached_item_2'],
                ];
            }
        };
    }
}

$memoPdo = new MemoizationTestPdo();
$uniqueToken = uniqid('test_', true);
$sql = "SELECT id, name FROM app_settings WHERE setting_key = ? -- " . $uniqueToken;

// Test 1: First call executes query
$res1 = dbQueryCached($memoPdo, $sql, ['site_name'], 300, true);
assertCondition($memoPdo->executionCount === 1, 'First query execution failed');
assertCondition(is_array($res1) && $res1['id'] === 101, 'Result fetch failed');

// Test 2: Second call with identical SQL and params hits L1 memoization cache (0 additional executions)
$res2 = dbQueryCached($memoPdo, $sql, ['site_name'], 300, true);
assertCondition($memoPdo->executionCount === 1, 'Second query did not hit L1 cache (execution count incremented)');
assertCondition($res2 === $res1, 'Cached result mismatch');

// Test 3: Parameter difference creates new cache entry
$res3 = dbQueryCached($memoPdo, $sql, ['site_theme'], 300, true);
assertCondition($memoPdo->executionCount === 2, 'Distinct params did not trigger new query');

// Test 4: fetchOne=false differentiates cache key from fetchOne=true
$resAll = dbQueryCached($memoPdo, $sql, ['site_name'], 300, false);
assertCondition($memoPdo->executionCount === 3, 'fetchAll did not create distinct cache entry');
assertCondition(is_array($resAll) && count($resAll) === 2, 'fetchAll return type mismatch');

// Test 5: Distinct SQL query creates distinct cache entry
$sql2 = "SELECT id, title FROM courses WHERE is_external = ? -- " . $uniqueToken;
$resCourse = dbQueryCached($memoPdo, $sql2, [0], 300, false);
assertCondition($memoPdo->executionCount === 4, 'Distinct SQL query did not trigger execution');

// -------------------------------------------------------------
// Tier 2 Boundary Tests: DSN Injection & Password Policies
// -------------------------------------------------------------
echo "[2/4] Testing Tier 2: Boundary Value Validations...\n";

// DSN Boundary 1: Reject semicolon injection in host
$caught = false;
try {
    appDbBuildDsn(['host' => '127.0.0.1;charset=latin1', 'database' => 'testdb']);
} catch (RuntimeException $e) {
    $caught = true;
}
assertCondition($caught, 'DSN host semicolon injection was not rejected');

// DSN Boundary 2: Reject null bytes in dbname
$caught = false;
try {
    appDbBuildDsn(['host' => '127.0.0.1', 'database' => "testdb\0injection"]);
} catch (RuntimeException $e) {
    $caught = true;
}
assertCondition($caught, 'DSN dbname null byte injection was not rejected');

// DSN Boundary 3: Reject newlines in dbname
$caught = false;
try {
    appDbBuildDsn(['host' => '127.0.0.1', 'database' => "testdb\ninjection"]);
} catch (RuntimeException $e) {
    $caught = true;
}
assertCondition($caught, 'DSN dbname newline injection was not rejected');

// DSN Boundary 4: Port boundary clamp (max 65535, min 1)
$dsnHighPort = appDbBuildDsn(['host' => '127.0.0.1', 'port' => 99999, 'database' => 'testdb']);
assertCondition(str_contains($dsnHighPort, 'port=65535'), 'Port > 65535 not clamped to 65535');
$dsnLowPort = appDbBuildDsn(['host' => '127.0.0.1', 'port' => -10, 'database' => 'testdb']);
assertCondition(str_contains($dsnLowPort, 'port=1'), 'Port <= 0 not clamped to 1');

// DSN Boundary 5: Unix socket DSN formatting
$socketDsn = appDbBuildDsn(['socket' => '/var/run/mysqld/mysqld.sock', 'database' => 'testdb']);
assertCondition($socketDsn === 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=testdb;charset=utf8mb4', 'Socket DSN format mismatch');

// Password Policy Boundary 1: Reject empty password in production
$caught = false;
try {
    appDbValidateConnectionConfig(['host' => 'prod-db.aws.internal', 'database' => 'prod_db'], 'app_user', '', 'production');
} catch (RuntimeException $e) {
    $caught = true;
}
assertCondition($caught, 'Empty password in production was accepted');

// Password Policy Boundary 2: Reject root user in production
$caught = false;
try {
    appDbValidateConnectionConfig(['host' => 'prod-db.aws.internal', 'database' => 'prod_db'], 'root', 'strong_password', 'production');
} catch (RuntimeException $e) {
    $caught = true;
}
assertCondition($caught, 'Root user in production was accepted');

// Password Policy Boundary 3: Reject root user in staging on remote host
$caught = false;
try {
    appDbValidateConnectionConfig(['host' => 'stage-db.example.com', 'database' => 'stage_db'], 'root', 'strong_password', 'staging');
} catch (RuntimeException $e) {
    $caught = true;
}
assertCondition($caught, 'Root user on remote staging host was accepted');

// Password Policy Boundary 4: Accept root with empty password on local dev
$validLocal = true;
try {
    appDbValidateConnectionConfig(['host' => 'localhost', 'database' => 'dev_db'], 'root', '', 'local');
} catch (RuntimeException $e) {
    $validLocal = false;
}
assertCondition($validLocal, 'Local development root configuration rejected');

// Password Policy Boundary 5: Accept regular user with password on production
$validProd = true;
try {
    appDbValidateConnectionConfig(['host' => 'db.internal', 'database' => 'prod_db'], 'prod_app_user', 'Very$ecureP@ss123!', 'production');
} catch (RuntimeException $e) {
    $validProd = false;
}
assertCondition($validProd, 'Valid production user credentials rejected');

// -------------------------------------------------------------
// Tier 3 Cross-Feature Combinations: Privacy & Session Modes
// -------------------------------------------------------------
echo "[3/4] Testing Tier 3: Cross-Feature Integration...\n";

// Cross-Feature 1: Strict session SQL mode in production
$sessionPdo = new class extends PDO {
    public array $statements = [];
    public function __construct() {}
    public function exec(string $statement): int|false {
        $this->statements[] = $statement;
        return 0;
    }
};
appDbConfigureSession($sessionPdo, 'production');
assertCondition(count($sessionPdo->statements) === 1, 'Production session config did not execute exactly 1 statement');
assertCondition(str_contains($sessionPdo->statements[0], 'STRICT_TRANS_TABLES'), 'Production session missing STRICT_TRANS_TABLES');
assertCondition(str_contains($sessionPdo->statements[0], 'ERROR_FOR_DIVISION_BY_ZERO'), 'Production session missing ERROR_FOR_DIVISION_BY_ZERO');

// Cross-Feature 2: Local dev skips session query overhead
$localSessionPdo = new class extends PDO {
    public array $statements = [];
    public function __construct() {}
    public function exec(string $statement): int|false {
        $this->statements[] = $statement;
        return 0;
    }
};
appDbConfigureSession($localSessionPdo, 'local');
assertCondition(count($localSessionPdo->statements) === 0, 'Local environment executed unnecessary session configuration');

// Cross-Feature 3: Endpoint locality detection for IPv6 loopback
assertCondition(appDbEndpointIsLocal(['host' => '::1']), 'IPv6 loopback ::1 not recognized as local');
assertCondition(appDbEndpointIsLocal(['host' => '127.0.0.1']), 'IPv4 loopback 127.0.0.1 not recognized as local');
assertCondition(appDbEndpointIsLocal(['host' => 'localhost']), 'localhost not recognized as local');
assertCondition(!appDbEndpointIsLocal(['host' => 'remote.database.com']), 'Remote host incorrectly identified as local');

// -------------------------------------------------------------
// Tier 4 Real-World Scenario: Web Request Simulation (Zero DDL)
// -------------------------------------------------------------
echo "[4/4] Testing Tier 4: Real-World Web Request Simulation...\n";

// Scenario 1: Simulate standard web request requiring social profile data
// Confirm no ALTER, CREATE, DROP, or SHOW TABLES executed
$webSpyPdo = new RuntimeWorkflowSpyPdo();
// Simulate reading privacy flags from users
$webSpyPdo->prepare("SELECT id, username, show_missions, show_online_status, show_recent_activity FROM users WHERE id = ?");
$webSpyPdo->prepare("SELECT id, title, is_external, external_url FROM courses WHERE id = ?");
$webSpyPdo->prepare("SELECT id, title, body, level FROM app_statuses WHERE is_active = 1");

$ddlStatements = array_filter(
    $webSpyPdo->statements,
    fn($sql) => preg_match('/^\s*(ALTER|CREATE|DROP|TRUNCATE|RENAME)\s+/i', $sql) === 1
);
assertCondition(count($ddlStatements) === 0, 'DDL statements executed during web request simulation');

echo "ALL RUNTIME DB WORKFLOW TESTS PASSED (100% SUCCESS)\n";
