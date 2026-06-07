<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

function guestRedirect(string $url): void {
    securityRedirect($url, '../landing.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf('guest_start')) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Nieprawidłowe żądanie trybu gościa.'];
    guestRedirect('../landing.php');
}

$rateLimit = securityConsumeRateLimit('guest:start:' . securityActorKey(), 12, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('guest_start_rate_limited', ['retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Zbyt wiele prob uruchomienia trybu goscia. Sprobuj za chwile.'];
    guestRedirect('../landing.php');
}

startGuestSession();

$target = (string)($_POST['target'] ?? 'test');
if ($target === 'exam') {
    $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($_POST['access_code'] ?? '')));
    guestRedirect('../exam/join.php' . ($code !== '' ? '?code=' . urlencode($code) : ''));
}

guestRedirect('../test.php?setup=1&new=1');
