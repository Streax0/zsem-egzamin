<?php
/**
 * Test Suite for Milestone 4: R3 Admin Dashboard & Sidebar Integration
 */

require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

use App\Core\Engine;
use App\Core\ConfigStore;
use App\Core\CacheManager;
use App\Security\Firewall;
use App\Security\Waf;

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
echo " Running Milestone 4 Admin Dashboard & Engine Tests\n";
echo "==================================================\n\n";

// --- Setup temporary test files ---
$testConfigPath = __DIR__ . '/../data/config/test_m4_engine_config.json';
$testCacheDir = __DIR__ . '/../data/cache/test_m4_cache';
$testWafLogPath = __DIR__ . '/../data/logs/test_m4_waf_log.json';
$testBannedIpsPath = __DIR__ . '/../data/config/test_m4_banned_ips.json';
$testViolationsPath = __DIR__ . '/../data/logs/test_m4_ip_violations.json';

foreach ([$testConfigPath, $testWafLogPath, $testBannedIpsPath, $testViolationsPath] as $p) {
    if (file_exists($p)) {
        @unlink($p);
    }
}
if (is_dir($testCacheDir)) {
    $files = glob($testCacheDir . '/*');
    if (is_array($files)) {
        foreach ($files as $f) {
            if (is_file($f)) @unlink($f);
        }
    }
}

// --- 1. Authorization Guard Tests ---
echo "[1] Testing Authorization Checks for admin/engine.php...\n";
assertTest("roleHasAdminAccess('user') returns false", !roleHasAdminAccess('user'));
assertTest("roleHasAdminAccess('teacher') returns false", !roleHasAdminAccess('teacher'));
assertTest("roleHasAdminAccess('guest') returns false", !roleHasAdminAccess('guest'));
assertTest("roleHasAdminAccess('') returns false", !roleHasAdminAccess(''));
assertTest("roleHasAdminAccess('admin') returns true", roleHasAdminAccess('admin'));
assertTest("roleHasAdminAccess('dyrektor') returns true", roleHasAdminAccess('dyrektor'));
echo "\n";

// --- 2. POST Actions Tests: ConfigStore, CacheManager, Firewall, WAF ---
echo "[2] Testing POST Actions (Config, Cache, Ban/Unban IP, WAF Logs)...\n";

// ConfigStore updates
$configStore = new ConfigStore($testConfigPath);
$configStore->set('maintenance_mode', true);
$configStore->set('minification_enabled', false);
$configStore->set('compression_enabled', true);
$configStore->set('waf_level', 'strict');
$configStore->set('csrf_enforced', true);

assertTest("ConfigStore maintenance_mode updated to true", $configStore->get('maintenance_mode') === true);
assertTest("ConfigStore minification_enabled updated to false", $configStore->get('minification_enabled') === false);
assertTest("ConfigStore compression_enabled updated to true", $configStore->get('compression_enabled') === true);
assertTest("ConfigStore waf_level updated to strict", $configStore->get('waf_level') === 'strict');
assertTest("ConfigStore csrf_enforced updated to true", $configStore->get('csrf_enforced') === true);

// Re-instantiate ConfigStore from disk to verify persistence
$configStoreReboot = new ConfigStore($testConfigPath);
assertTest("ConfigStore persisted maintenance_mode on reload", $configStoreReboot->get('maintenance_mode') === true);
assertTest("ConfigStore persisted waf_level on reload", $configStoreReboot->get('waf_level') === 'strict');

// CacheManager clearing
$cacheManager = new CacheManager($testCacheDir);
$cacheManager->set('test_key_1', 'hello_world', 3600);
assertTest("CacheManager set test key successfully", $cacheManager->get('test_key_1') === 'hello_world');

$clearResult = $cacheManager->clear('all');
assertTest("CacheManager clear('all') returned true", $clearResult);
assertTest("CacheManager key missing after clear", $cacheManager->get('test_key_1') === null);

// Firewall Ban/Unban IP
$waf = new Waf($testWafLogPath);
$firewall = new Firewall($waf, $testBannedIpsPath, $testViolationsPath);

$targetIp = '198.51.100.42';
assertTest("IP initially not banned", !$firewall->isBanned($targetIp));

$banResult = $firewall->banIp($targetIp, 'Testing M4 manual ban', 3600);
assertTest("Firewall banIp returned true", $banResult);
assertTest("IP is now banned", $firewall->isBanned($targetIp));

$bannedList = $firewall->getBannedIps();
$foundInList = false;
foreach ($bannedList as $entry) {
    if (isset($entry['ip']) && $entry['ip'] === $targetIp) {
        $foundInList = true;
        break;
    }
}
assertTest("Banned IP found in getBannedIps list", $foundInList);

$unbanResult = $firewall->unbanIp($targetIp);
assertTest("Firewall unbanIp returned true", $unbanResult);
assertTest("IP is no longer banned", !$firewall->isBanned($targetIp));

// WAF Log clearing
$_SERVER['REMOTE_ADDR'] = '198.51.100.99';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['malicious'] = "' OR 1=1 --";

$inspectResult = $waf->inspectRequest('medium');
assertTest("WAF detected and blocked malicious POST request", $inspectResult === false);

$wafLogs = $waf->getLogs();
assertTest("WAF recorded blocked attack entry", count($wafLogs) >= 1);

$waf->clearLogs();
$clearedWafLogs = $waf->getLogs();
assertTest("WAF logs empty after clearLogs()", count($clearedWafLogs) === 0);
echo "\n";

// --- 3. Sidebar Integration & Link Tests ---
echo "[3] Testing includes/sidebar.php Integration...\n";

$sidebarPath = __DIR__ . '/../includes/sidebar.php';
assertTest("includes/sidebar.php file exists", file_exists($sidebarPath));

$sidebarContent = file_get_contents($sidebarPath);
$hasEngineLink = strpos($sidebarContent, 'admin/engine.php') !== false;
assertTest("includes/sidebar.php contains admin/engine.php link", $hasEngineLink);

$hasCpuIcon = strpos($sidebarContent, 'bi-cpu') !== false;
assertTest("includes/sidebar.php contains bi-cpu icon", $hasCpuIcon);

$hasLabel = strpos($sidebarContent, 'Silnik i Security') !== false;
assertTest("includes/sidebar.php contains 'Silnik i Security' text", $hasLabel);

// Render sidebar for Admin role
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SERVER['PHP_SELF'] = '/admin/index.php';

ob_start();
include $sidebarPath;
$adminSidebarHtml = ob_get_clean();

assertTest("Admin sidebar rendering contains admin/engine.php", strpos($adminSidebarHtml, 'admin/engine.php') !== false);
assertTest("Admin sidebar rendering contains Silnik i Security", strpos($adminSidebarHtml, 'Silnik i Security') !== false);

// Render sidebar for User role
$_SESSION['role'] = 'user';
ob_start();
include $sidebarPath;
$userSidebarHtml = ob_get_clean();

assertTest("User sidebar rendering DOES NOT contain admin/engine.php", strpos($userSidebarHtml, 'admin/engine.php') === false);
assertTest("User sidebar rendering DOES NOT contain Silnik i Security", strpos($userSidebarHtml, 'Silnik i Security') === false);
echo "\n";

// Cleanup test files
foreach ([$testConfigPath, $testWafLogPath, $testBannedIpsPath, $testViolationsPath] as $p) {
    if (file_exists($p)) {
        @unlink($p);
    }
}

echo "==================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED \n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}
