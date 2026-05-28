<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$username = trim((string)($_GET['username'] ?? $_POST['username'] ?? ''));
$email = trim((string)($_GET['email'] ?? $_POST['email'] ?? ''));

$response = ['exists' => false, 'checked' => false];

if (!consumeRateLimit($pdo, 'check_user', clientIpAddress(), 30, 300)) {
    http_response_code(429);
    echo json_encode(['exists' => false, 'limited' => true]);
    exit;
}

if (!empty($username)) {
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        echo json_encode(['exists' => false, 'invalid' => true]);
        exit;
    }
    $response['checked'] = true;
}

if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 100) {
        echo json_encode(['exists' => false, 'invalid' => true]);
        exit;
    }
    $response['checked'] = true;
}

echo json_encode($response);
