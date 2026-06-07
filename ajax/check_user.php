<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

$username = securityInputString($_GET['username'] ?? $_POST['username'] ?? '', 32);
$email = securityInputString($_GET['email'] ?? $_POST['email'] ?? '', 120);

$response = ['exists' => false, 'checked' => false];

if (!consumeRateLimit($pdo, 'check_user', clientIpAddress(), 30, 300)) {
    http_response_code(429);
    echo securityJsonEncode(['exists' => false, 'limited' => true]);
    exit;
}

if (!empty($username)) {
    if (!preg_match('/^[A-Za-z0-9_.-]{3,16}$/', $username)) {
        echo securityJsonEncode(['exists' => false, 'invalid' => true]);
        exit;
    }
    $response['checked'] = true;
}

if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 100) {
        echo securityJsonEncode(['exists' => false, 'invalid' => true]);
        exit;
    }
    $response['checked'] = true;
}

echo securityJsonEncode($response);
