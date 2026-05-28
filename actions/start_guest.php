<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

function guestRedirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '', 'guest_start')) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Nieprawidłowe żądanie trybu gościa.'];
    guestRedirect('../landing.php');
}

startGuestSession();

$target = (string)($_POST['target'] ?? 'test');
if ($target === 'exam') {
    $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($_POST['access_code'] ?? '')));
    guestRedirect('../exam/join.php' . ($code !== '' ? '?code=' . urlencode($code) : ''));
}

guestRedirect('../test.php?setup=1&new=1');
