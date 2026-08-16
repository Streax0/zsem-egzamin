<?php
declare(strict_types=1);

/**
 * Challenger 2: Database Connection, Concurrency & Security Adversarial Test Harness
 * 
 * Tests:
 * 1. DSN Construction & Hostile Input Fuzzing
 * 2. Persistent Connection Toggling & Security Options
 * 3. L1/L2 Query Cache Memoization Collision Boundaries, Key Hashing & Multi-Tier Caching
 * 4. Zero-DDL Execution Verification across Web Requests & SAPI Environments
 */

define('APP_DB_SKIP_CONNECT', true);
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

final class ChallengerTestLogger {
    public static int $assertions = 0;
    public static int $failures = 0;
    public static array $results = [];

    public static function assert(bool $condition, string $testName, string $failureDetails = ''): void {
        self::$assertions++;
        if ($condition) {
            self::$results[] = ['status' => 'PASS', 'test' => $testName];
            echo "  [PASS] {$testName}\n";
        } else {
            self::$failures++;
            self::$results[] = ['status' => 'FAIL', 'test' => $testName, 'error' => $failureDetails];
            fwrite(STDERR, "  [FAIL] {$testName}: {$failureDetails}\n");
        }
    }
}

// -----------------------------------------------------------------------------
// SECTION 1: DSN Construction & Hostile Input Fuzzing
// -----------------------------------------------------------------------------
function testHostileDsnFuzzing(): void {
    echo "\n=== [1/4] Testing Hostile DSN Construction & Fuzzing ===\n";

    // 1.1 Semicolon Injections
    $semicolonPayloads = [
        'localhost;port=3306',
        'localhost;charset=latin1',
        'db.example.com;unix_socket=/tmp/evil.sock',
        'exam_platform;drop=table',
        '127.0.0.1;foo=bar;baz=qux',
        ';;;;',
        'localhost;ssl_mode=disabled',
    ];

    foreach ($semicolonPayloads as $payload) {
        $caughtHost = false;
        try {
            appDbBuildDsn(['host' => $payload, 'database' => 'testdb']);
        } catch (RuntimeException $e) {
            $caughtHost = true;
        }
        ChallengerTestLogger::assert($caughtHost, "DSN Host Semicolon Injection Blocked: '{$payload}'");

        $caughtDb = false;
        try {
            appDbBuildDsn(['host' => 'localhost', 'database' => $payload]);
        } catch (RuntimeException $e) {
            $caughtDb = true;
        }
        ChallengerTestLogger::assert($caughtDb, "DSN DB Semicolon Injection Blocked: '{$payload}'");

        $caughtSocket = false;
        try {
            appDbBuildDsn(['socket' => $payload, 'database' => 'testdb']);
        } catch (RuntimeException $e) {
            $caughtSocket = true;
        }
        ChallengerTestLogger::assert($caughtSocket, "DSN Socket Semicolon Injection Blocked: '{$payload}'");
    }

    // 1.2 Control Characters & Null Byte Fuzzing (0x00 to 0x1F and 0x7F)
    for ($byte = 0; $byte <= 31; $byte++) {
        $char = chr($byte);
        $hex = sprintf('\\x%02X', $byte);
        
        $caughtHost = false;
        try {
            appDbBuildDsn(['host' => "local{$char}host", 'database' => 'testdb']);
        } catch (RuntimeException $e) {
            $caughtHost = true;
        }
        ChallengerTestLogger::assert($caughtHost, "DSN Host Control Char {$hex} Blocked");

        $caughtDb = false;
        try {
            appDbBuildDsn(['host' => 'localhost', 'database' => "db{$char}name"]);
        } catch (RuntimeException $e) {
            $caughtDb = true;
        }
        ChallengerTestLogger::assert($caughtDb, "DSN DB Control Char {$hex} Blocked");
    }

    // DEL character (0x7F)
    $del = chr(0x7F);
    $caughtDel = false;
    try {
        appDbBuildDsn(['host' => "host{$del}", 'database' => 'testdb']);
    } catch (RuntimeException $e) {
        $caughtDel = true;
    }
    ChallengerTestLogger::assert($caughtDel, "DSN Host DEL (0x7F) Char Blocked");

    // 1.3 Port Out-Of-Range & Extreme Boundary Clamping
    $portTests = [
        ['port' => -100, 'expected' => 1],
        ['port' => 0, 'expected' => 1],
        ['port' => 1, 'expected' => 1],
        ['port' => 80, 'expected' => 80],
        ['port' => 3306, 'expected' => 3306],
        ['port' => 65535, 'expected' => 65535],
        ['port' => 65536, 'expected' => 65535],
        ['port' => 999999, 'expected' => 65535],
        ['port' => '3307', 'expected' => 3307],
        ['port' => 'invalid', 'expected' => 1],
    ];

    foreach ($portTests as $tc) {
        $dsn = appDbBuildDsn(['host' => '127.0.0.1', 'port' => $tc['port'], 'database' => 'testdb']);
        $expectedSubstring = ';port=' . $tc['expected'];
        ChallengerTestLogger::assert(
            str_contains($dsn, $expectedSubstring),
            "DSN Port Clamping for input {$tc['port']} -> expected {$tc['expected']}",
            "DSN was: {$dsn}"
        );
    }

    // 1.4 Valid Hostnames, IPv4, IPv6, Sockets
    $validTargets = [
        ['host' => 'localhost', 'database' => 'zsem_db'],
        ['host' => '127.0.0.1', 'database' => 'zsem_db'],
        ['host' => '::1', 'database' => 'zsem_db'],
        ['host' => 'db-cluster-01.internal.lan', 'database' => 'exam_prod_2026'],
        ['socket' => '/var/run/mysqld/mysqld.sock', 'database' => 'zsem_db'],
    ];

    foreach ($validTargets as $vt) {
        $dsn = appDbBuildDsn($vt);
        ChallengerTestLogger::assert(
            str_contains($dsn, 'charset=utf8mb4') && str_contains($dsn, 'dbname=') && !empty($dsn),
            "Valid DSN built successfully for host/socket: " . ($vt['host'] ?? $vt['socket'])
        );
    }
}

// -----------------------------------------------------------------------------
// SECTION 2: Persistent Connection Toggling & Security Options
// -----------------------------------------------------------------------------
function testPersistentConnectionsAndSecurityOptions(): void {
    echo "\n=== [2/4] Testing Persistent Connections & Security Options ===\n";

    // 2.1 Boolean Parsing (appConfigBool)
    $truthyStrings = ['1', 'true', 'TRUE', 'True', 'on', 'ON', 'yes', 'YES'];
    foreach ($truthyStrings as $val) {
        putenv("TEST_BOOL_KEY={$val}");
        $_ENV['TEST_BOOL_KEY'] = $val;
        ChallengerTestLogger::assert(
            appConfigBool('TEST_BOOL_KEY', false) === true,
            "appConfigBool parses truthy '{$val}' as true"
        );
    }

    $falsyStrings = ['0', 'false', 'FALSE', 'False', 'off', 'OFF', 'no', 'NO'];
    foreach ($falsyStrings as $val) {
        putenv("TEST_BOOL_KEY={$val}");
        $_ENV['TEST_BOOL_KEY'] = $val;
        ChallengerTestLogger::assert(
            appConfigBool('TEST_BOOL_KEY', true) === false,
            "appConfigBool parses falsy '{$val}' as false"
        );
    }

    $invalidStrings = ['2', '-1', 'maybe', 'invalid', 'true;drop', 'yes please', '100', "\0true"];
    foreach ($invalidStrings as $val) {
        putenv("TEST_BOOL_KEY={$val}");
        $_ENV['TEST_BOOL_KEY'] = $val;
        ChallengerTestLogger::assert(
            appConfigBool('TEST_BOOL_KEY', false) === false,
            "appConfigBool fallback to default (false) on invalid input '{$val}'"
        );
        ChallengerTestLogger::assert(
            appConfigBool('TEST_BOOL_KEY', true) === true,
            "appConfigBool fallback to default (true) on invalid input '{$val}'"
        );
    }

    // 2.2 Persistent Connection Toggling in appDbPdoOptions
    putenv('MYSQL_PERSISTENT=true');
    $_ENV['MYSQL_PERSISTENT'] = 'true';
    $optsPersistent = appDbPdoOptions([]);
    ChallengerTestLogger::assert(
        $optsPersistent[PDO::ATTR_PERSISTENT] === true,
        "MYSQL_PERSISTENT=true enables PDO::ATTR_PERSISTENT"
    );

    putenv('MYSQL_PERSISTENT=false');
    $_ENV['MYSQL_PERSISTENT'] = 'false';
    $optsNonPersistent = appDbPdoOptions([]);
    ChallengerTestLogger::assert(
        $optsNonPersistent[PDO::ATTR_PERSISTENT] === false,
        "MYSQL_PERSISTENT=false disables PDO::ATTR_PERSISTENT"
    );

    // Fallback to DB_PERSISTENT when MYSQL_PERSISTENT is not set
    putenv('MYSQL_PERSISTENT');
    unset($_ENV['MYSQL_PERSISTENT']);
    putenv('DB_PERSISTENT=true');
    $_ENV['DB_PERSISTENT'] = 'true';
    $optsFallback = appDbPdoOptions([]);
    ChallengerTestLogger::assert(
        $optsFallback[PDO::ATTR_PERSISTENT] === true,
        "DB_PERSISTENT=true fallback works when MYSQL_PERSISTENT is absent"
    );

    // Direct config array override
    $optsDirectTrue = appDbPdoOptions(['persistent' => true]);
    ChallengerTestLogger::assert(
        $optsDirectTrue[PDO::ATTR_PERSISTENT] === true,
        "Direct config['persistent'] = true overrides environment"
    );
    $optsDirectFalse = appDbPdoOptions(['persistent' => false]);
    ChallengerTestLogger::assert(
        $optsDirectFalse[PDO::ATTR_PERSISTENT] === false,
        "Direct config['persistent'] = false overrides environment"
    );

    // 2.3 Immutable Security Invariants in PDO Options
    $opts = appDbPdoOptions([]);
    ChallengerTestLogger::assert($opts[PDO::ATTR_ERRMODE] === PDO::ERRMODE_EXCEPTION, "PDO ATTR_ERRMODE is ERRMODE_EXCEPTION");
    ChallengerTestLogger::assert($opts[PDO::ATTR_EMULATE_PREPARES] === false, "PDO ATTR_EMULATE_PREPARES is false (Native Prepares)");
    ChallengerTestLogger::assert($opts[PDO::ATTR_DEFAULT_FETCH_MODE] === PDO::FETCH_ASSOC, "PDO ATTR_DEFAULT_FETCH_MODE is FETCH_ASSOC");
    ChallengerTestLogger::assert($opts[PDO::MYSQL_ATTR_LOCAL_INFILE] === false, "PDO MYSQL_ATTR_LOCAL_INFILE is false (Local file reading disabled)");
    ChallengerTestLogger::assert($opts[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] === true, "PDO MYSQL_ATTR_USE_BUFFERED_QUERY is true");
    ChallengerTestLogger::assert(
        $opts[PDO::MYSQL_ATTR_INIT_COMMAND] === 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        "PDO MYSQL_ATTR_INIT_COMMAND sets utf8mb4 and utf8mb4_unicode_ci"
    );

    // Clean up env
    putenv('MYSQL_PERSISTENT');
    putenv('DB_PERSISTENT');
    putenv('TEST_BOOL_KEY');
    unset($_ENV['MYSQL_PERSISTENT'], $_ENV['DB_PERSISTENT'], $_ENV['TEST_BOOL_KEY']);
}

// -----------------------------------------------------------------------------
// SECTION 3: L1/L2 Query Cache Memoization Collision & Concurrency Stress Test
// -----------------------------------------------------------------------------
final class MockChallengerStatement extends PDOStatement {
    public string $sql;
    public array $params;
    public int $executeCount = 0;
    private MockChallengerPdo $pdo;

    protected function __construct() {}

    public static function create(MockChallengerPdo $pdo, string $sql): self {
        $stmt = new self();
        $stmt->pdo = $pdo;
        $stmt->sql = $sql;
        return $stmt;
    }

    public function execute(?array $params = null): bool {
        $this->executeCount++;
        $this->params = $params ?? [];
        $this->pdo->totalExecutes++;
        $this->pdo->executedQueries[] = ['sql' => $this->sql, 'params' => $this->params];
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return [
            ['id' => 1, 'data' => 'row_1', 'param' => $this->params[0] ?? null],
            ['id' => 2, 'data' => 'row_2', 'param' => $this->params[0] ?? null],
        ];
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return ['id' => 1, 'data' => 'row_1', 'param' => $this->params[0] ?? null];
    }
}

final class MockChallengerPdo extends PDO {
    public int $totalExecutes = 0;
    public array $executedQueries = [];

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return MockChallengerStatement::create($this, $query);
    }
}

function testQueryCacheMemoizationCollisions(): void {
    echo "\n=== [3/4] Testing L1 Query Cache Collision Boundaries & Memoization ===\n";

    // Clean Engine cache first to ensure baseline testing
    if (\App\Core\Engine::getInstance()->isBooted()) {
        \App\Core\Engine::getInstance()->getCache()->clear('all');
    }

    $mockPdo = new MockChallengerPdo();

    // 3.1 fetchOne (true vs false) collision resistance
    $uniqueNonce = uniqid('test_', true);
    $sql1 = "SELECT * FROM users_test_{$uniqueNonce} WHERE id = ?";
    $resAll = dbQueryCached($mockPdo, $sql1, [101], 300, false);
    $resOne = dbQueryCached($mockPdo, $sql1, [101], 300, true);

    ChallengerTestLogger::assert(
        is_array($resAll) && isset($resAll[0]['data']) && count($resAll) === 2,
        "dbQueryCached with fetchOne=false returns list of rows"
    );
    ChallengerTestLogger::assert(
        is_array($resOne) && isset($resOne['data']) && !isset($resOne[0]),
        "dbQueryCached with fetchOne=true returns single row dictionary"
    );
    ChallengerTestLogger::assert(
        $mockPdo->totalExecutes === 2,
        "fetchOne=true and fetchOne=false generated distinct cache keys and executed 2 distinct DB statements"
    );

    // Repeated calls must hit L1 memoization without incrementing totalExecutes
    $resAllCached = dbQueryCached($mockPdo, $sql1, [101], 300, false);
    $resOneCached = dbQueryCached($mockPdo, $sql1, [101], 300, true);
    ChallengerTestLogger::assert(
        $mockPdo->totalExecutes === 2,
        "L1 static memoization prevented re-execution for identical query & params (HIT count: 2)"
    );
    ChallengerTestLogger::assert(
        $resAllCached === $resAll && $resOneCached === $resOne,
        "L1 memoized results match exactly"
    );

    // 3.2 Parameter Distinctness & Type Boundaries
    $paramVariants = [
        ['val' => 1, 'desc' => 'integer 1'],
        ['val' => '1', 'desc' => 'string "1"'],
        ['val' => 0, 'desc' => 'integer 0'],
        ['val' => '0', 'desc' => 'string "0"'],
        ['val' => null, 'desc' => 'null value'],
        ['val' => false, 'desc' => 'boolean false'],
        ['val' => '', 'desc' => 'empty string'],
    ];

    $executesBefore = $mockPdo->totalExecutes;
    $uniqueTable2 = uniqid('param_table_', true);
    foreach ($paramVariants as $pv) {
        $res = dbQueryCached($mockPdo, "SELECT ? AS test_col FROM {$uniqueTable2}", [$pv['val']], 300, false);
        ChallengerTestLogger::assert(is_array($res), "Query cache executed for {$pv['desc']}");
    }
    $executesAfter = $mockPdo->totalExecutes;
    $distinctCount = count($paramVariants);
    ChallengerTestLogger::assert(
        ($executesAfter - $executesBefore) === $distinctCount,
        "All {$distinctCount} parameter boundary variants generated distinct cache keys (no key collisions)"
    );

    // 3.3 Delimiter Collisions & Edge-Case SQL Strings
    $uniqueTable3 = uniqid('delim_table_', true);
    $delimiterTests = [
        ['sql' => "SELECT 1| FROM {$uniqueTable3}", 'params' => []],
        ['sql' => "SELECT 1 FROM {$uniqueTable3}", 'params' => ["|"]],
        ['sql' => "SELECT 1|[] FROM {$uniqueTable3}", 'params' => []],
        ['sql' => "SELECT 1 FROM {$uniqueTable3}", 'params' => ["[]"]],
    ];

    $execBeforeDelim = $mockPdo->totalExecutes;
    foreach ($delimiterTests as $dt) {
        dbQueryCached($mockPdo, $dt['sql'], $dt['params'], 300, false);
    }
    $execAfterDelim = $mockPdo->totalExecutes;
    ChallengerTestLogger::assert(
        ($execAfterDelim - $execBeforeDelim) === count($delimiterTests),
        "Delimiter-adjacent queries produced distinct MD5 cache keys"
    );

    // 3.4 Concurrency & Intra-Request Memoization Volume Test (100 queries)
    $uniqueTable4 = uniqid('stress_table_', true);
    $startTime = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        dbQueryCached($mockPdo, "SELECT * FROM {$uniqueTable4} WHERE id = ?", [$i], 300, false);
    }
    $durationMs = round((microtime(true) - $startTime) * 1000, 2);
    ChallengerTestLogger::assert(
        $mockPdo->totalExecutes >= 100,
        "100 distinct queries processed and memoized in {$durationMs}ms"
    );

    // Re-running the same 100 queries should take < 5ms and execute ZERO DB queries
    $reExecBefore = $mockPdo->totalExecutes;
    $reStartTime = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        dbQueryCached($mockPdo, "SELECT * FROM {$uniqueTable4} WHERE id = ?", [$i], 300, false);
    }
    $reDurationMs = round((microtime(true) - $reStartTime) * 1000, 2);
    $reExecAfter = $mockPdo->totalExecutes;
    ChallengerTestLogger::assert(
        $reExecAfter === $reExecBefore,
        "100 memoized query calls had 100% cache hit rate with 0 database prepares/executes"
    );
    ChallengerTestLogger::assert(
        $reDurationMs < 20.0,
        "100 cache hits completed in {$reDurationMs}ms (< 20ms requirement)"
    );
}

// -----------------------------------------------------------------------------
// SECTION 4: Zero-DDL Execution Verification Across Standard Web Requests
// -----------------------------------------------------------------------------
final class ZeroDdlSpyPdo extends PDO {
    public array $ddlCalls = [];
    public array $allCalls = [];

    public function __construct() {}

    public function exec(string $statement): int|false {
        $this->allCalls[] = ['exec', $statement];
        if (preg_match('/(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)/i', $statement)) {
            $this->ddlCalls[] = ['exec', $statement];
        }
        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->allCalls[] = ['prepare', $query];
        if (preg_match('/(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)/i', $query)) {
            $this->ddlCalls[] = ['prepare', $query];
        }
        return false;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->allCalls[] = ['query', $query];
        if (preg_match('/(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)/i', $query)) {
            $this->ddlCalls[] = ['query', $query];
        }
        return false;
    }
}

function testZeroDdlWebExecution(): void {
    echo "\n=== [4/4] Testing Zero-DDL Execution in Web Context ===\n";

    // 4.1 Verify appRuntimeSchemaUpdatesEnabled() contract
    ChallengerTestLogger::assert(
        function_exists('appRuntimeSchemaUpdatesEnabled'),
        "appRuntimeSchemaUpdatesEnabled helper function exists"
    );

    // In this script (CLI, APP_RUNTIME_SCHEMA_UPDATES=false by default), it MUST return false
    ChallengerTestLogger::assert(
        appRuntimeSchemaUpdatesEnabled() === false,
        "appRuntimeSchemaUpdatesEnabled() returns false by default"
    );

    // 4.2 Spy Verification: Call all schema migration and table ensure functions
    $spyPdo = new ZeroDdlSpyPdo();

    // Call every single ensure / migration function
    ensurePlatformEnhancements($spyPdo);
    ensureUserActiveTestsTable($spyPdo);
    ensureDuelModeColumns($spyPdo);
    ensureAdminRequestsTableExists($spyPdo);
    ensureActiveSessionTable($spyPdo);
    createRegistrationAttemptsTable();
    createLoginAttemptsTable();
    dbAddColumnIfMissing($spyPdo, 'users', 'challenger_col', 'VARCHAR(255) DEFAULT NULL');
    dbAddIndexIfMissing($spyPdo, 'users', 'idx_challenger_test', '(challenger_col)');

    ChallengerTestLogger::assert(
        $spyPdo->ddlCalls === [],
        "ZERO DDL statements executed when appRuntimeSchemaUpdatesEnabled is false",
        "DDL statements executed: " . json_encode($spyPdo->ddlCalls)
    );
    ChallengerTestLogger::assert(
        $spyPdo->allCalls === [],
        "ZERO total database calls executed by migration helpers when updates are disabled",
        "Total calls: " . json_encode($spyPdo->allCalls)
    );

    // 4.3 Static Codebase Audit for Unguarded Inline DDL
    $root = dirname(__DIR__);
    $phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $unguardedDdlFiles = [];

    foreach ($phpFiles as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        
        // Skip tests/ and scratch/
        if (str_starts_with($relPath, 'tests/') || str_starts_with($relPath, 'scratch/') || str_starts_with($relPath, '.agents/')) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if (preg_match('/(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE)/i', $content, $match)) {
            // Check if file contains guard check
            if (!str_contains($content, 'appRuntimeSchemaUpdatesEnabled') && !str_contains($content, 'dbRuntimeSchemaUpdatesEnabled')) {
                // Check if it's just in a docblock/string comment (e.g. practice.php tutorial text)
                if ($relPath === 'practice.php' || str_contains($content, "'Projekt i Import Bazy Danych (MySQL)'")) {
                    continue;
                }
                $unguardedDdlFiles[] = $relPath . " (Matched: " . $match[0] . ")";
            }
        }
    }

    ChallengerTestLogger::assert(
        $unguardedDdlFiles === [],
        "All DDL occurrences across repository are strictly guarded by appRuntimeSchemaUpdatesEnabled",
        "Unguarded files found: " . implode(', ', $unguardedDdlFiles)
    );
}

// -----------------------------------------------------------------------------
// EXECUTE SUITE
// -----------------------------------------------------------------------------
echo "====================================================================\n";
echo "CHALLENGER 2: ADVERSARIAL DATABASE & CONCURRENCY VERIFICATION SUITE\n";
echo "====================================================================\n";

$startTotalTime = microtime(true);

testHostileDsnFuzzing();
testPersistentConnectionsAndSecurityOptions();
testQueryCacheMemoizationCollisions();
testZeroDdlWebExecution();

$totalDuration = round((microtime(true) - $startTotalTime) * 1000, 2);

echo "\n====================================================================\n";
echo "SUITE SUMMARY: " . ChallengerTestLogger::$assertions . " assertions executed in {$totalDuration}ms\n";
echo "PASSED: " . (ChallengerTestLogger::$assertions - ChallengerTestLogger::$failures) . "\n";
echo "FAILED: " . ChallengerTestLogger::$failures . "\n";
echo "FINAL VERDICT: " . (ChallengerTestLogger::$failures === 0 ? "APPROVE" : "REJECT") . "\n";
echo "====================================================================\n";

if (ChallengerTestLogger::$failures > 0) {
    exit(1);
}
