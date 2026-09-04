<?php
/**
 * Test Suite for Milestone 3: R2 Security System & WAF Implementation
 */

require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../Security/bootstrap.php';

use App\Security\Waf;
use App\Security\Firewall;
use App\Core\Engine;
use App\Core\ConfigStore;

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
echo " Running Milestone 3 Security System & WAF Tests  \n";
echo "==================================================\n\n";

// Setup temporary test file paths
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$testWafLogPath = __DIR__ . '/../data/logs/test_waf_log.json';
$testBannedIpsPath = __DIR__ . '/../data/config/test_banned_ips.json';
$testViolationsPath = __DIR__ . '/../data/logs/test_ip_violations.json';
$testEngineConfigPath = __DIR__ . '/../data/config/test_engine_m3_config.json';

// Cleanup existing test files
foreach ([$testWafLogPath, $testBannedIpsPath, $testViolationsPath, $testEngineConfigPath] as $path) {
    if (file_exists($path)) {
        @unlink($path);
    }
}

// --- 1. Autoloading Test ---
echo "[1] Testing PSR-4 Autoloading for Security classes...\n";
assertTest("Waf class loaded", class_exists(Waf::class));
assertTest("Firewall class loaded", class_exists(Firewall::class));
assertTest("securityWaf() helper function exists", function_exists('securityWaf'));
assertTest("securityFirewall() helper function exists", function_exists('securityFirewall'));
echo "\n";

// --- 2. WAF Attack Detection Methods Test ---
echo "[2] Testing WAF Detection Signatures...\n";
$waf = new Waf($testWafLogPath);

// SQLi
assertTest("SQLi: UNION SELECT detected", $waf->detectSqlInjection("1 UNION SELECT username, password FROM users"));
assertTest("SQLi: OR 1=1 detected", $waf->detectSqlInjection("' OR 1=1 --"));
assertTest("SQLi: INFORMATION_SCHEMA detected", $waf->detectSqlInjection("SELECT * FROM INFORMATION_SCHEMA.TABLES"));
assertTest("SQLi: -- comment detected", $waf->detectSqlInjection("admin' --"));
assertTest("SQLi: /* block comment detected", $waf->detectSqlInjection("admin' /* comment */"));
assertTest("SQLi: SLEEP() detected", $waf->detectSqlInjection("' AND SLEEP(5) --"));
assertTest("SQLi: BENCHMARK() detected", $waf->detectSqlInjection("BENCHMARK(1000000,MD5(1))"));
assertTest("SQLi: Clean input rejected", !$waf->detectSqlInjection("John Doe"));

// XSS
assertTest("XSS: <script> detected", $waf->detectXss("<script>alert(1)</script>"));
assertTest("XSS: javascript: detected", $waf->detectXss("javascript:alert(document.cookie)"));
assertTest("XSS: onerror= detected", $waf->detectXss("<img src=x alt=\"x\" onerror=alert(1)>"));
assertTest("XSS: onload= detected", $waf->detectXss("<body onload=alert(1)>"));
assertTest("XSS: <iframe detected", $waf->detectXss("<iframe src=\"https://attacker.com\"></iframe>"));
assertTest("XSS: document.cookie detected", $waf->detectXss("console.log(document.cookie)"));
assertTest("XSS: eval() detected", $waf->detectXss("eval('alert(1)')"));
assertTest("XSS: Clean input rejected", !$waf->detectXss("Hello, welcome to ZSEM!"));

// Path Traversal
assertTest("Path Traversal: ../ detected", $waf->detectPathTraversal("../../../../etc/passwd"));
assertTest("Path Traversal: ..\\ detected", $waf->detectPathTraversal("..\\..\\windows\\system32"));
assertTest("Path Traversal: /etc/passwd detected", $waf->detectPathTraversal("/etc/passwd"));
assertTest("Path Traversal: c:\\boot.ini detected", $waf->detectPathTraversal("c:\\boot.ini"));
assertTest("Path Traversal: %2e%2e detected", $waf->detectPathTraversal("%2e%2e/%2e%2e/etc/passwd"));
assertTest("Path Traversal: Clean input rejected", !$waf->detectPathTraversal("uploads/images/avatar.jpg"));

// Command Injection
assertTest("Command Injection: ; ls detected", $waf->detectCommandInjection("test.txt; ls -la"));
assertTest("Command Injection: | dir detected", $waf->detectCommandInjection("file.txt | dir"));
assertTest("Command Injection: && detected", $waf->detectCommandInjection("cat file.txt && id"));
assertTest("Command Injection: backticks detected", $waf->detectCommandInjection("echo `whoami`"));
assertTest("Command Injection: $( detected", $waf->detectCommandInjection("echo $(net user)"));
assertTest("Command Injection: net user detected", $waf->detectCommandInjection("net user admin /add"));
assertTest("Command Injection: Clean input rejected", !$waf->detectCommandInjection("document_v1.pdf"));
echo "\n";

// --- 3. WAF Inspection Levels & Logging ---
echo "[3] Testing WAF Inspection Levels & Log Recording...\n";
$_GET = [];
$_POST = [];
$_COOKIE = [];
$_FILES = [];
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnitTestAgent/1.0';

// Level: disabled
$_GET['payload'] = "1 UNION SELECT * FROM users";
assertTest("WAF disabled level allows attack payload", $waf->inspectRequest('disabled') === true);
$_GET = [];

// Level: medium
$_GET['clean_param'] = 'hello_world';
assertTest("WAF medium level allows clean request", $waf->inspectRequest('medium') === true);

$_GET['sqli_param'] = "SELECT * FROM INFORMATION_SCHEMA.TABLES";
assertTest("WAF medium level blocks SQLi payload", $waf->inspectRequest('medium') === false);
$_GET = [];

// Check WAF Log File recording
$logs = $waf->getLogs();
assertTest("WAF getLogs returns recorded attack entry", count($logs) === 1 && $logs[0]['attack_type'] === 'sqli');

$stats = $waf->getStats();
assertTest("WAF getStats returns valid inspection and block count", $stats['total_blocked'] >= 1 && $stats['attack_types']['sqli'] >= 1);

$waf->clearLogs();
assertTest("WAF clearLogs empties log array", count($waf->getLogs()) === 0);

// Level: strict
$_POST['strict_param'] = "SELECT * FROM users";
assertTest("WAF strict level blocks strict pattern", $waf->inspectRequest('strict') === false);
$_POST = [];
echo "\n";

// --- 4. Firewall Banning, Unbanning & Honeypot ---
echo "[4] Testing Firewall IP Banning, Unbanning & Honeypot Traps...\n";
$firewall = new Firewall($waf, $testBannedIpsPath, $testViolationsPath);
$testIp = '198.51.100.42';

assertTest("Firewall isBanned returns false for clean IP", !$firewall->isBanned($testIp));

$firewall->banIp($testIp, 'Testing ban mechanism', 3600);
assertTest("Firewall banIp successfully bans IP", $firewall->isBanned($testIp));

$bannedList = $firewall->getBannedIps();
assertTest("Firewall getBannedIps contains banned IP record", count($bannedList) === 1 && $bannedList[0]['ip'] === $testIp);

$firewall->unbanIp($testIp);
assertTest("Firewall unbanIp successfully removes ban", !$firewall->isBanned($testIp));

// Honeypot test
$cleanPost = ['username' => 'testuser', 'password' => 'secret123'];
assertTest("Firewall checkHoneypot passes clean form submission", !$firewall->checkHoneypot($cleanPost));

$trapPost = ['username' => 'botuser', '_hp_trap' => 'spam_content'];
assertTest("Firewall checkHoneypot catches honeypot trap", $firewall->checkHoneypot($trapPost));
assertTest("Firewall automatically bans IP on honeypot trigger", $firewall->isBanned('127.0.0.1'));
$firewall->unbanIp('127.0.0.1');

// Repeated violations auto ban
$violatorIp = '203.0.113.99';
for ($i = 0; $i < 4; $i++) {
    $firewall->recordViolation($violatorIp);
}
assertTest("Firewall violator IP not banned before 5 violations", !$firewall->isBanned($violatorIp));

$firewall->recordViolation($violatorIp);
assertTest("Firewall automatically bans IP on 5th violation", $firewall->isBanned($violatorIp));

// Test that protectRequest enforces IP ban even when wafLevel is 'disabled'
$_SERVER['REMOTE_ADDR'] = $violatorIp;
assertTest("Firewall protectRequest blocks banned IP even when wafLevel is disabled", $firewall->protectRequest('disabled') === false);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$firewall->unbanIp($violatorIp);
echo "\n";

// --- 5. Engine Boot Integration ---
echo "[5] Testing Engine Boot Integration with WAF & Firewall...\n";
Engine::resetInstance();
$configStore = new ConfigStore($testEngineConfigPath);
$configStore->set('waf_level', 'medium');
$configStore->set('csrf_enforced', false);

$engine = Engine::getInstance();
$engine->boot($testEngineConfigPath);

assertTest("Engine boot initializes Waf instance", $engine->getWaf() instanceof Waf);
assertTest("Engine boot initializes Firewall instance", $engine->getFirewall() instanceof Firewall);

// Flush buffer output cleanly
$engine->getResponseBuffer()->setMinification(false);
$engine->getResponseBuffer()->setCompression(false);
$bufferedContent = $engine->getResponseBuffer()->getClean();

// Cleanup test files
foreach ([$testWafLogPath, $testBannedIpsPath, $testViolationsPath, $testEngineConfigPath] as $path) {
    if (file_exists($path)) {
        @unlink($path);
    }
}
echo "\n";

// Summary
echo "==================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED \n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}
