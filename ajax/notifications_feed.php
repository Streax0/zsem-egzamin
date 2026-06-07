<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(false, [], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

$userId = (int)$_SESSION['user_id'];
$baseUrl = securityInputString($_GET['base'] ?? '', 120);
if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $baseUrl) || str_contains($baseUrl, "\0")) {
    $baseUrl = '';
}
if ($baseUrl !== '' && !str_ends_with($baseUrl, '/')) {
    $baseUrl .= '/';
}

$limit = securityInputInt($_GET['limit'] ?? 5, 1, 10, 5);
$payload = buildNotificationsDropdownPayload($pdo, $userId, $baseUrl, $limit);

echo securityJsonEncode([
    'success' => true,
    'unread_count' => $payload['unread_count'],
    'has_unread' => $payload['has_unread'],
    'html' => $payload['html'],
]);
