<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

startSecureSession();
requireLogin();

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '', 'notifications')) {
    if ($wantsJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
    setSessionMessage('error', 'Błąd bezpieczeństwa.');
    header('Location: ../notifications.php');
    exit;
}

$userId = $_SESSION['user_id'];
$notificationId = max(0, (int)($_POST['notification_id'] ?? 0));

try {
    if ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
} catch (PDOException $e) {
    if ($wantsJson) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'db']);
        exit;
    }
}

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

// Redirect back only inside this site.
$returnUrl = '../index.php';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referrer !== '') {
    $parts = parse_url($referrer);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($parts && (!isset($parts['host']) || strcasecmp($parts['host'], $host) === 0)) {
        $path = $parts['path'] ?? '../index.php';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $returnUrl = $path . $query;
    }
}
header('Location: ' . $returnUrl);
exit;
