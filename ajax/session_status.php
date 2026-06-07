<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(false, [], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

$maxLifetime = 10800;
if (!isset($_SESSION['session_start'])) {
    $_SESSION['session_start'] = time();
}

$elapsed = time() - (int)$_SESSION['session_start'];
$remaining = max(0, $maxLifetime - $elapsed);

echo securityJsonEncode([
    'success' => true,
    'remaining_seconds' => $remaining,
    'expires_at' => date('c', time() + $remaining),
]);
