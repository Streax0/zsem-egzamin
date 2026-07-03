<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf('logout_all')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    securityRedirect('../settings.php', '../settings.php');
}

$userId = (int)$_SESSION['user_id'];
$rateLimit = securityConsumeRateLimit('auth:logout_all:' . securityActorKey(), 10, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('logout_all_rate_limited', ['user_id' => $userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
    securityRedirect('../settings.php', '../settings.php');
}
$pdo->prepare('UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?')->execute([$userId]);
$includeCurrent = ($_POST['include_current'] ?? '') === '1';
forgetAllUserSessions($pdo, $userId);
if ($includeCurrent) {
    destroySession(false);
    securityRedirect('../auth/login.php?logged_out_all=1', '../auth/login.php');
}
$stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
$stmt->execute([$userId]);
$_SESSION['session_version'] = (int)$stmt->fetchColumn();
registerCurrentUserSession($pdo, $userId);
setSessionMessage('success', 'Pozostałe sesje zostały unieważnione.');
securityRedirect('../settings.php', '../settings.php');
