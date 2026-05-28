<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        header('Location: ../social.php');
        exit;
    }
    
    $friendId = (int)($_POST['friend_id'] ?? 0);
    $myId = $_SESSION['user_id'];
    
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

        if (!canSendFriendRequest($senderRole, $targetRole, $targetAllowsRequests)) {
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

$returnUrl = '../social.php';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referrer !== '') {
    $parts = parse_url($referrer);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($parts && (!isset($parts['host']) || strcasecmp($parts['host'], $host) === 0)) {
        $path = $parts['path'] ?? '../social.php';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $returnUrl = $path . $query;
    }
}
header('Location: ' . $returnUrl);
exit;
