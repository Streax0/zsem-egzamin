<?php
require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../config/db.php';

$engine = App\Core\Engine::getInstance();
$waf = $engine->getWaf();

// Test 1: Benign code & search input (should PASS)
$_GET = [
    'search' => 'C++ && Python programming',
    'sql_query' => 'SELECT * FROM users WHERE id = 1',
    'code' => '<script>console.log("hello");</script>'
];
$passed1 = $waf->inspectRequest('medium');
echo "[TEST 1 - Benign educational input]: " . ($passed1 ? "PASSED (NO FALSE POSITIVE)" : "FAILED (FALSE POSITIVE TRIGGERED)") . "\n";

// Test 2: Real malicious attack in normal param (should BLOCK)
$_GET = [
    'username' => 'admin',
    'search' => 'test"; DROP TABLE users; --',
    'bio' => '<script>document.cookie</script>'
];
$passed2 = $waf->inspectRequest('medium');
echo "[TEST 2 - Malicious attack payload]: " . (!$passed2 ? "PASSED (BLOCKED PROPERLY)" : "FAILED (MISSED ATTACK)") . "\n";

echo "ALL FALSE POSITIVE TESTS COMPLETE!\n";
