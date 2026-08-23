<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Start secure session
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '', 'logout')) {
    if (isLoggedIn()) {
        setSessionMessage('error', 'Nieprawidłowe żądanie wylogowania.');
        header('Location: ../index.php');
    } else {
        header('Location: ../auth/login.php');
    }
    exit;
}

if (isLoggedIn() && isset($pdo)) {
    try {
        forgetCurrentUserSession($pdo, (int)$_SESSION['user_id']);
    } catch (Throwable $e) {
        error_log("Logout session cleanup error: " . $e->getMessage());
    }
}

// Clear remember me cookie
if (isset($_COOKIE['remember_me'])) {
    $secure = function_exists('securityRequestIsSecure') ? securityRequestIsSecure() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Destroy session completely
destroySession();

// Redirect to login page
header('Location: ../auth/login.php?logged_out=1');
exit;
