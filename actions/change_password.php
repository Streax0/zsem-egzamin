<?php
/**
 * Change Password Handler
 * Processes password change requests from profile.php modal
 */
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
$rateLimit = securityConsumeRateLimit('auth:change_password:' . securityActorKey(), 8, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('change_password_rate_limited', ['user_id' => (int)$userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    setSessionMessage('error', 'Zbyt wiele prób zmiany hasła. Spróbuj za chwilę.');
    securityRedirect($returnTo, '../settings.php');
}
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate inputs
$errors = [];

if (empty($currentPassword)) {
    $errors[] = 'Podaj aktualne hasło.';
}

$errors = array_merge($errors, validatePasswordPolicy($newPassword));

if ($newPassword !== $confirmPassword) {
    $errors[] = 'Nowe hasła nie są identyczne.';
}

if ($newPassword === $currentPassword) {
    $errors[] = 'Nowe hasło musi się różnić od aktualnego.';
}

// Verify current password
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !verifyPassword($currentPassword, $user['password_hash'])) {
            $errors[] = 'Aktualne hasło jest nieprawidłowe.';
        }
    } catch (PDOException $e) {
        error_log('Password change error: ' . $e->getMessage());
        $errors[] = 'Wystąpił błąd bazy danych.';
    }
}

// Process change
if (empty($errors)) {
    if (updateUserPassword($pdo, $userId, $newPassword)) {
        $stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $_SESSION['session_version'] = (int)$stmt->fetchColumn();
        setSessionMessage('success', 'Hasło zostało zmienione pomyślnie.');
    } else {
        setSessionMessage('error', 'Nie udało się zmienić hasła. Spróbuj ponownie.');
    }
} else {
    setSessionMessage('error', implode(' ', $errors));
}

securityRedirect($returnTo, '../settings.php');
