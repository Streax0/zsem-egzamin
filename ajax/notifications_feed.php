<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');

requireJsonLogin();

$userId = (int)$_SESSION['user_id'];
$baseUrl = trim((string)($_GET['base'] ?? ''));
if ($baseUrl !== '' && !str_ends_with($baseUrl, '/')) {
    $baseUrl .= '/';
}

$limit = max(1, min(10, (int)($_GET['limit'] ?? 5)));
$payload = buildNotificationsDropdownPayload($pdo, $userId, $baseUrl, $limit);

echo json_encode([
    'success' => true,
    'unread_count' => $payload['unread_count'],
    'has_unread' => $payload['has_unread'],
    'html' => $payload['html'],
], JSON_UNESCAPED_UNICODE);
