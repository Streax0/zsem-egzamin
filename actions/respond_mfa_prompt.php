<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf('mfa_prompt')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../index.php');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$notificationId = securityInputInt($_POST['notification_id'] ?? 0, 1, PHP_INT_MAX, 0);
$decision = securityInputEnum($_POST['decision'] ?? '', ['setup', 'decline'], '');

try {
    $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $roleStmt->execute([$userId]);
    $role = (string)($roleStmt->fetchColumn() ?: 'user');
} catch (PDOException $e) {
    $role = 'user';
}

if ($notificationId <= 0 || $decision === '' || !in_array($role, ['teacher', 'dyrektor'], true)) {
    setSessionMessage('error', 'Nieprawidłowa decyzja 2FA.');
    redirect('../index.php');
}

try {
    $stmt = $decision === 'decline'
        ? $pdo->prepare("UPDATE notifications SET type = 'mfa_optional_declined', is_read = 1, action_url = NULL WHERE id = ? AND user_id = ? AND type = 'mfa_optional_prompt'")
        : $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ? AND type = 'mfa_optional_prompt'");
    $stmt->execute([$notificationId, $userId]);
    if ($stmt->rowCount() < 1) {
        setSessionMessage('error', 'Prośba 2FA jest już obsłużona.');
        redirect('../index.php');
    }
} catch (PDOException $e) {
    error_log('Optional MFA prompt response failed: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się zapisać decyzji 2FA.');
    redirect('../index.php');
}

if ($decision === 'setup') {
    redirect('../mfa.php');
}

setSessionMessage('info', '2FA pozostaje wyłączone. Możesz włączyć je ręcznie w ustawieniach.');
securityRedirect(securityReferrerRedirectTarget('../index.php'), '../index.php');
