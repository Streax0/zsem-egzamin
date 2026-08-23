<?php
/**
 * Test Suite for DevTools Protection Guard
 */

require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

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
echo " Running DevTools Protection Guard Tests         \n";
echo "==================================================\n\n";

// --- 1. Role-based DevTools Authorization Checks ---
echo "[1] Testing Role-based DevTools Policy...\n";

// Guest / Unauthenticated
$_SESSION = [];
assertTest("Anonymous/Guest: DevTools is disallowed", isDevToolsAllowed() === false);
$metaAnonymous = devtoolsPolicyMetaTag();
assertTest("Anonymous/Guest: Meta tag contains 'deny'", strpos($metaAnonymous, 'content="deny"') !== false);
assertTest("Anonymous/Guest: Meta tag does not enable admin flag", strpos($metaAnonymous, '__ZSEM_DEVTOOLS_ENABLED') === false);

// Regular user
$_SESSION = ['user_id' => 10, 'role' => 'user'];
assertTest("Regular user: DevTools is disallowed", isDevToolsAllowed() === false);
$metaUser = devtoolsPolicyMetaTag();
assertTest("Regular user: Meta tag contains 'deny'", strpos($metaUser, 'content="deny"') !== false);
assertTest("Regular user: Meta tag does not enable admin flag", strpos($metaUser, '__ZSEM_DEVTOOLS_ENABLED') === false);

// Teacher (not admin)
$_SESSION = ['user_id' => 20, 'role' => 'teacher'];
assertTest("Teacher: DevTools is disallowed", isDevToolsAllowed() === false);
$metaTeacher = devtoolsPolicyMetaTag();
assertTest("Teacher: Meta tag contains 'deny'", strpos($metaTeacher, 'content="deny"') !== false);

// Admin
$_SESSION = ['user_id' => 1, 'role' => 'admin'];
assertTest("Admin: DevTools is allowed", isDevToolsAllowed() === true);
$metaAdmin = devtoolsPolicyMetaTag();
assertTest("Admin: Meta tag contains 'allow'", strpos($metaAdmin, 'content="allow"') !== false);
assertTest("Admin: Meta tag enables __ZSEM_DEVTOOLS_ENABLED", strpos($metaAdmin, '__ZSEM_DEVTOOLS_ENABLED=true') !== false);

// Dyrektor
$_SESSION = ['user_id' => 2, 'role' => 'dyrektor'];
assertTest("Dyrektor: DevTools is allowed", isDevToolsAllowed() === true);
$metaDyrektor = devtoolsPolicyMetaTag();
assertTest("Dyrektor: Meta tag contains 'allow'", strpos($metaDyrektor, 'content="allow"') !== false);

echo "\n";

// --- 2. DevTools Guard JS file checks ---
echo "[2] Testing DevTools Guard Script Assets...\n";

$guardJsPath = __DIR__ . '/../assets/js/devtools-guard.js';
assertTest("devtools-guard.js exists", file_exists($guardJsPath));

$guardContent = file_get_contents($guardJsPath);
assertTest("devtools-guard.js has isDevToolsAllowed check", strpos($guardContent, 'isDevToolsAllowed') !== false);
assertTest("devtools-guard.js checks __ZSEM_DEVTOOLS_ENABLED", strpos($guardContent, '__ZSEM_DEVTOOLS_ENABLED') !== false);
assertTest("devtools-guard.js checks meta devtools-policy", strpos($guardContent, 'meta[name="devtools-policy"]') !== false);
assertTest("devtools-guard.js blocks contextmenu", strpos($guardContent, 'contextmenu') !== false);
assertTest("devtools-guard.js blocks F12 key", strpos($guardContent, 'f12') !== false || strpos($guardContent, '123') !== false);
assertTest("devtools-guard.js blocks Ctrl+Shift+I / inspect shortcuts", strpos($guardContent, "'i'") !== false || strpos($guardContent, '73') !== false);
assertTest("devtools-guard.js blocks Ctrl+U (View Source)", strpos($guardContent, "'u'") !== false || strpos($guardContent, '85') !== false);
assertTest("devtools-guard.js neutralizes console", strpos($guardContent, 'neutralizeConsole') !== false);
assertTest("devtools-guard.js has debugger timing check", strpos($guardContent, "Function('debugger')()") !== false);
assertTest("devtools-guard.js manages warning overlay", strpos($guardContent, 'zsem-devtools-guard-overlay') !== false);

echo "\n";

// --- 3. Header & Theme Handler integration ---
echo "[3] Testing Integration in Header & Theme Handler...\n";

$headerContent = file_get_contents(__DIR__ . '/../includes/header.php');
assertTest("header.php includes devtoolsPolicyMetaTag", strpos($headerContent, 'devtoolsPolicyMetaTag') !== false);
assertTest("header.php includes devtools-guard.js", strpos($headerContent, 'devtools-guard.js') !== false);

$themeHandlerContent = file_get_contents(__DIR__ . '/../assets/js/theme-handler.js');
assertTest("theme-handler.js dynamically loads devtools-guard.js fallback", strpos($themeHandlerContent, 'devtools-guard.js') !== false);

echo "\n==================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED \n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}
