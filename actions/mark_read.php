<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

startSecureSession();

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0;
if ($wantsJson) {
    requireJsonLogin(false, [], ['ok' => false, 'error' => 'unauthorized'], ['ok' => false, 'error' => 'unauthorized']);
} else {
    requireLogin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf('notifications')) {
    if ($wantsJson) {
        securitySendJson(['success' => false, 'ok' => false, 'error' => 'csrf'], 403);
    }
    setSessionMessage('error', 'Błąd bezpieczeństwa.');
    header('Location: ../user/notifications.php');
    exit;
}

$userId = $_SESSION['user_id'];
$notificationId = securityInputInt($_POST['notification_id'] ?? 0, 0, PHP_INT_MAX, 0);

if ($wantsJson) {
    securityThrottle(
        'notifications:mark_read:' . securityActorKey(),
        80,
        60,
        ['success' => false, 'ok' => false, 'error' => 'rate_limited']
    );
}

try {
    if ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type NOT IN ('mfa_optional_prompt', 'mfa_optional_declined')");
        $stmt->execute([$userId]);
    }
} catch (PDOException $e) {
    if ($wantsJson) {
        securityAudit('notification_mark_read_failed', ['notification_id' => $notificationId], 'error');
        securitySendJson(['success' => false, 'ok' => false, 'error' => 'db'], 500);
    }
}

if ($wantsJson) {
    securitySendJson(['success' => true, 'ok' => true, 'notification_id' => $notificationId]);
}

securityRedirect(securityReferrerRedirectTarget('../index.php'), '../index.php');
