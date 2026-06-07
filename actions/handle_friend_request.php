<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!securityValidateRequestCsrf()) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        securityRedirect('../social.php', '../social.php');
    }

    $myId = $_SESSION['user_id'];
    $otherUserId = securityInputInt($_POST['user_id'] ?? 0, 0, PHP_INT_MAX, 0);
    $action = securityInputEnum($_POST['action'] ?? '', ['accept', 'reject'], '');
    $rateLimit = securityConsumeRateLimit('social:handle_friend_request:' . securityActorKey(), 40, 60);
    if (empty($rateLimit['allowed'])) {
        securityAudit('handle_friend_request_rate_limited', ['other_user_id' => $otherUserId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
        securityRedirect('../social.php', '../social.php');
    }
    
    if ($action === 'accept') {
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$otherUserId, $myId]);
        setSessionMessage('success', 'Zaproszenie zostało zaakceptowane!');
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$otherUserId, $myId]);
        setSessionMessage('info', 'Zaproszenie zostało odrzucone.');
    }
}

securityRedirect('../social.php', '../social.php');
