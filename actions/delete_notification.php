<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf()) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../notifications.php');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$notificationId = securityInputInt($_POST['notification_id'] ?? 0, 0, PHP_INT_MAX, 0);
$deleteAll = ($_POST['delete_all'] ?? '') === '1';
$rateLimit = securityConsumeRateLimit('notifications:delete:' . securityActorKey(), $deleteAll ? 5 : 40, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('notification_delete_rate_limited', ['delete_all' => $deleteAll, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
    redirect('../notifications.php');
}

try {
    if ($deleteAll) {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = ?');
        $stmt->execute([$userId]);
        setSessionMessage('info', 'Wszystkie powiadomienia zostały usunięte.');
    } elseif ($notificationId > 0) {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
        setSessionMessage('info', 'Powiadomienie zostało usunięte.');
    }
} catch (PDOException $e) {
    error_log('Delete notification error: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się usunąć powiadomienia.');
}

redirect('../notifications.php');
