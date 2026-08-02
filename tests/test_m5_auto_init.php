<?php
/**
 * Test Suite for Milestone 5: R4 Auto-initialization & DB Helpers
 */

define('APP_DB_SKIP_CONNECT', true);

require_once __DIR__ . '/../config/db.php';

use App\Core\Engine;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $failLog = '')
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        if ($failLog !== '') {
            echo "        Details: {$failLog}\n";
        }
        $failed++;
    }
}

echo "==================================================\n";
echo " Running Milestone 5 Auto-Init & DB Helper Tests  \n";
echo "==================================================\n\n";

// --- 1. Engine Auto-Initialization & Autoloader Test ---
echo "[1] Testing Engine Singleton Auto-Initialization via config/db.php...\n";
assertTest("Engine singleton isBooted() returns true after loading config/db.php", Engine::getInstance()->isBooted() === true);
assertTest("Global helper function dbQueryCached() exists", function_exists('dbQueryCached'));
assertTest("PSR-4 Autoloader properly loaded Engine instance", Engine::getInstance() instanceof Engine);
assertTest("Engine getCache() returns valid CacheManager instance", Engine::getInstance()->getCache() !== null);
echo "\n";

// --- 2. dbQueryCached Helper Function Tests ---
echo "[2] Testing dbQueryCached() with PDO SQLite in-memory DB...\n";

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE test_users (id INTEGER PRIMARY KEY, username TEXT, role TEXT);");
    $pdo->exec("INSERT INTO test_users (id, username, role) VALUES (1, 'alice', 'admin'), (2, 'bob', 'user'), (3, 'charlie', 'user');");

    // Test fetchOne = false (fetchAll mode)
    $allUsers = dbQueryCached($pdo, "SELECT * FROM test_users ORDER BY id ASC", [], 300, false);
    assertTest("dbQueryCached(fetchOne=false) returns array of all rows", is_array($allUsers) && count($allUsers) === 3);
    assertTest("dbQueryCached first row content matches expected", is_array($allUsers) && isset($allUsers[0]['username']) && $allUsers[0]['username'] === 'alice');

    // Test fetchOne = true (fetch single row mode)
    $singleUser = dbQueryCached($pdo, "SELECT * FROM test_users WHERE id = ?", [2], 300, true);
    assertTest("dbQueryCached(fetchOne=true) returns associative array for single row", is_array($singleUser) && isset($singleUser['username']) && $singleUser['username'] === 'bob');

    // Test Cache Hit on repeated call
    // Directly modify database row without clearing cache
    $pdo->exec("UPDATE test_users SET username = 'robert' WHERE id = 2");

    // Query again with identical SQL and params - should hit cache and return 'bob'
    $cachedUser = dbQueryCached($pdo, "SELECT * FROM test_users WHERE id = ?", [2], 300, true);
    assertTest("dbQueryCached returns cached value ('bob') on repeated call despite DB update ('robert')", is_array($cachedUser) && $cachedUser['username'] === 'bob');

    // Query with different parameter - should execute query against DB and get updated value
    $directDbUser = $pdo->query("SELECT username FROM test_users WHERE id = 2")->fetchColumn();
    assertTest("Direct DB query confirms DB row was updated to 'robert'", $directDbUser === 'robert');

    // Verify cache isolation with different parameter values
    $paramUser1 = dbQueryCached($pdo, "SELECT * FROM test_users WHERE role = ?", ['admin'], 300, false);
    $paramUser2 = dbQueryCached($pdo, "SELECT * FROM test_users WHERE role = ?", ['user'], 300, false);
    assertTest("Different parameters yield different cache keys (admin count=1, user count=2)", count($paramUser1) === 1 && count($paramUser2) === 2);

} catch (Throwable $e) {
    assertTest("dbQueryCached test encountered exception: " . $e->getMessage(), false, $e->getTraceAsString());
}
echo "\n";

// --- Clean up Cache ---
Engine::getInstance()->getCache()->clear('all');

// Summary
echo "==================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED \n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}
