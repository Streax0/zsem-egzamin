<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

$returnTo = str_contains(securityReferrerRedirectTarget('../settings.php'), 'profile.php') ? '../profile.php' : '../settings.php';
if (!empty($_POST['return_to']) && in_array($_POST['return_to'], ['settings.php', 'profile.php'], true)) {
    $returnTo = securityLocalRedirectTarget('../' . $_POST['return_to'], '../settings.php', ['#^\.\./(?:settings|profile)\.php$#']);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securityRedirect($returnTo, '../settings.php');
}

// Validate CSRF token
if (!securityValidateRequestCsrf()) {
    setSessionMessage('error', 'Nieprawidłowy token CSRF. Spróbuj ponownie.');
    securityRedirect($returnTo, '../settings.php');
}

$userId = $_SESSION['user_id'];
$rateLimit = securityConsumeRateLimit('auth:migrate_md5:' . securityActorKey(), 8, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('migrate_md5_rate_limited', ['user_id' => (int)$userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    setSessionMessage('error', 'Zbyt wiele prób. Spróbuj za chwilę.');
    securityRedirect($returnTo, '../settings.php');
}

$currentPassword = $_POST['current_password'] ?? '';

if (empty($currentPassword)) {
    setSessionMessage('error', 'Podaj aktualne hasło.');
    securityRedirect($returnTo, '../settings.php');
}

try {
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !verifyPassword($currentPassword, $user['password_hash'])) {
        setSessionMessage('error', 'Aktualne hasło jest nieprawidłowe.');
        securityRedirect($returnTo, '../settings.php');
    }

    if (strlen($user['password_hash']) !== 32 || !ctype_xdigit($user['password_hash'])) {
        setSessionMessage('error', 'Twoje hasło nie wymaga migracji.');
        securityRedirect($returnTo, '../settings.php');
    }

    if (updateUserPassword($pdo, $userId, $currentPassword)) {
        $stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $_SESSION['session_version'] = (int)$stmt->fetchColumn();
        setSessionMessage('success', 'Hasło zostało zmigrowane pomyślnie.');
    } else {
        setSessionMessage('error', 'Nie udało się zmigrować hasła. Spróbuj ponownie.');
    }
} catch (PDOException $e) {
    error_log('Password migration error: ' . $e->getMessage());
    setSessionMessage('error', 'Wystąpił błąd bazy danych.');
}

securityRedirect($returnTo, '../settings.php');
