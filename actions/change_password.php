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

$returnTo = (str_contains($_SERVER['HTTP_REFERER'] ?? '', 'profile.php')) ? '../profile.php' : '../settings.php';
if (!empty($_POST['return_to']) && in_array($_POST['return_to'], ['settings.php', 'profile.php'], true)) {
    $returnTo = '../' . $_POST['return_to'];
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $returnTo);
    exit;
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Nieprawidłowy token CSRF. Spróbuj ponownie.');
    header('Location: ' . $returnTo);
    exit;
}

$userId = $_SESSION['user_id'];
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

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
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

header('Location: ' . $returnTo);
exit;
