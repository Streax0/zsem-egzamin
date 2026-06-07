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
        // Check roles and target privacy settings.
        $stmt = $pdo->prepare("SELECT id, role, allow_friend_requests FROM users WHERE id IN (?, ?)");
        $stmt->execute([$myId, $friendId]);
        $usersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $senderRole = $_SESSION['role'] ?? 'user';
        $targetRole = 'user';
        $targetAllowsRequests = true;

        foreach ($usersData as $u) {
            if ($u['id'] === $myId) {
                $senderRole = $u['role'] ?? 'user';
            }
            if ($u['id'] === $friendId) {
                $targetRole = $u['role'] ?? 'user';
                $targetAllowsRequests = isset($u['allow_friend_requests']) ? $u['allow_friend_requests'] == 1 : true;
            }
        }

        if (!canSendMoreFriendRequests($pdo, (int)$myId)) {
            setSessionMessage('error', 'Masz już 4 oczekujące wysłane zaproszenia. Anuluj jedno albo poczekaj na akceptację.');
        } elseif (!canSendFriendRequest($senderRole, $targetRole, $targetAllowsRequests)) {
            setSessionMessage('error', 'Nie możesz wysłać zaproszenia do tego konta.');
        } else {
            if (sendFriendRequest($pdo, $myId, $friendId)) {
                setSessionMessage('success', 'Zaproszenie zostało wysłane.');
            } else {
                setSessionMessage('error', 'Nie udało się wysłać zaproszenia lub już istnieje relacja.');
            }
        }
    }
}

securityRedirect(securityReferrerRedirectTarget('../social.php'), '../social.php');
