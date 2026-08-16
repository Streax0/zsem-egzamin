<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autoloader.php';

startSecureSession();
if (function_exists('securityApplyJsonHeaders')) {
    securityApplyJsonHeaders();
} else {
    header('Content-Type: application/json; charset=utf-8');
}

$router = new \App\Core\ApiRouter($pdo ?? null);

// --- Core API Endpoints ---
$router->get('/health', function () use ($pdo) {
    require_once __DIR__ . '/health.php';
    return getSystemHealthReport($pdo, false);
});

$router->get('/version', function () {
    return [
        'name' => 'ZSEM Tech Platform API',
        'version' => '2.0.0',
        'php_version' => PHP_VERSION,
        'environment' => defined('APP_ENV') ? APP_ENV : 'local',
        'server_time' => date('Y-m-d H:i:s'),
    ];
});

$router->get('/session/status', function () {
    $isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
    return [
        'authenticated' => $isLoggedIn,
        'guest_mode' => function_exists('isGuestMode') && isGuestMode(),
        'user' => $isLoggedIn ? [
            'id' => (int)($_SESSION['user_id'] ?? 0),
            'username' => (string)($_SESSION['username'] ?? ''),
            'role' => (string)($_SESSION['role'] ?? 'user'),
        ] : null,
        'csrf_token' => function_exists('generateCsrfToken') ? generateCsrfToken() : '',
    ];
});

$router->post('/session/keepalive', function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['session_start'] = time();
    }
    return [
        'refreshed' => true,
        'csrf_token' => function_exists('generateCsrfToken') ? generateCsrfToken('session_keepalive') : '',
    ];
}, ['csrf']);

// Dispatch request
$router->dispatch();
