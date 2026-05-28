<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '', 'logout_all')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    header('Location: ../settings.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo->prepare('UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?')->execute([$userId]);
$includeCurrent = ($_POST['include_current'] ?? '') === '1';
forgetAllUserSessions($pdo, $userId);
if ($includeCurrent) {
    destroySession(false);
    header('Location: ../login.php?logged_out_all=1');
    exit;
}
$stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
$stmt->execute([$userId]);
$_SESSION['session_version'] = (int)$stmt->fetchColumn();
registerCurrentUserSession($pdo, $userId);
setSessionMessage('success', 'Pozostałe sesje zostały unieważnione.');
header('Location: ../settings.php');
exit;
