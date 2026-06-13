<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!securityValidateRequestCsrf()) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        securityRedirect('../social.php', '../social.php');
    }
    
    $friendId = securityInputInt($_POST['friend_id'] ?? 0, 0, PHP_INT_MAX, 0);
    $myId = $_SESSION['user_id'];
    $rateLimit = securityConsumeRateLimit('social:send_friend_request:' . securityActorKey(), 20, 60);
    if (empty($rateLimit['allowed'])) {
        securityAudit('send_friend_request_rate_limited', ['friend_id' => $friendId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
        securityRedirect(securityReferrerRedirectTarget('../social.php'), '../social.php');
    }
    
    if ($friendId > 0 && $friendId != $myId) {
        $failureReason = null;
        if (sendFriendRequest($pdo, $myId, $friendId, $failureReason)) {
            setSessionMessage('success', 'Zaproszenie zostało wysłane.');
        } elseif ($failureReason === 'friend_request_limit') {
            setSessionMessage('error', 'Masz już 4 oczekujące wysłane zaproszenia. Anuluj jedno albo poczekaj na akceptację.');
        } elseif ($failureReason === 'friend_request_privacy') {
            setSessionMessage('error', 'Nie możesz wysłać zaproszenia do tego konta.');
        } else {
            setSessionMessage('error', 'Nie udało się wysłać zaproszenia lub już istnieje relacja.');
        }
    }
}

securityRedirect(securityReferrerRedirectTarget('../social.php'), '../social.php');
