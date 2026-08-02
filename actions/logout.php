<?php

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

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

// Optionally clear remember me cookie if implemented
if (isset($_COOKIE['remember_me'])) {
    $secure = securityRequestIsSecure();
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (isLoggedIn()) {
    forgetCurrentUserSession($pdo, (int)$_SESSION['user_id']);
}

// Destroy session completely
destroySession();

// Redirect to login page
header('Location: ../auth/login.php');
exit();
