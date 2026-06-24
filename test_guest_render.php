<?php
define('APP_DB_SKIP_CONNECT', true);

// Setup the environment so that isGuestMode() returns true
$_SESSION['guest_mode'] = true;

// Mock headers_sent so the session functions don't complain or fail in CLI
if (!function_exists('xdebug_get_headers')) {
    // just for safe execution in cli
}

// capture output
ob_start();
try {
    require 'index.php';
} catch (Exception $e) {
    // Ignore exceptions after output
}
$output = ob_get_clean();

if (strpos($output, 'text-white') !== false) {
    echo "SUCCESS: text-white class found in output.\n";
} else {
    echo "FAILED: text-white class not found in output.\n";
}
