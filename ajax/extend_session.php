<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

requireJsonLogin(false, [], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

if (!validateRequestCsrfToken('session_keepalive')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$maxLifetime = 10800;
if (!isset($_SESSION['session_start'])) {
    $_SESSION['session_start'] = time();
}

if (time() - (int)$_SESSION['session_start'] > $maxLifetime) {
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

$_SESSION['session_start'] = time();

$params = session_get_cookie_params();
setcookie(
    session_name(),
    session_id(),
    time() + $maxLifetime,
    $params['path'] ?: '/',
    $params['domain'] ?: '',
    $params['secure'] ?? false,
    $params['httponly'] ?? true
);

echo json_encode([
    'success' => true,
    'remaining_seconds' => $maxLifetime,
    'extended_until' => date('c', time() + $maxLifetime),
]);
