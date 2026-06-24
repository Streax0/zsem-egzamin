<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!securityValidateRequestCsrf()) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        securityRedirect('../settings.php', '../settings.php');
    }

    $userId = $_SESSION['user_id'];
    $rateLimit = securityConsumeRateLimit('settings:update_privacy:' . securityActorKey(), 30, 60);
    if (empty($rateLimit['allowed'])) {
        securityAudit('update_privacy_rate_limited', ['user_id' => (int)$userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        setSessionMessage('error', 'Zbyt wiele zmian naraz. Spróbuj za chwilę.');
        securityRedirect('../settings.php', '../settings.php');
    }
    
    // Checkboxes are only sent if they are checked
    $profilePublic = isset($_POST['profile_public']) ? 1 : 0;
    $statsPublic = isset($_POST['stats_public']) ? 1 : 0;
    $allowFriendRequests = isset($_POST['allow_friend_requests']) ? 1 : 0;
    $searchable = isset($_POST['searchable']) ? 1 : 0;
    $allowProfileComments = isset($_POST['allow_profile_comments']) ? 1 : 0;
    $showMissions = isset($_POST['show_missions']) ? 1 : 0;
    $showOnlineStatus = isset($_POST['show_online_status']) ? 1 : 0;
    $showRecentActivity = isset($_POST['show_recent_activity']) ? 1 : 0;
    $role = $_SESSION['role'] ?? 'user';

    try {
        ensurePlatformEnhancements($pdo);
        $roleStmt = $pdo->prepare("SELECT role, ranking_visible FROM users WHERE id = ? LIMIT 1");
        $roleStmt->execute([$userId]);
        $dbUser = $roleStmt->fetch(PDO::FETCH_ASSOC) ?: ['role' => $role, 'ranking_visible' => 0];
        $dbRole = $dbUser['role'] ?? $role;
        $rankingVisible = $dbRole === 'teacher' ? (isset($_POST['ranking_visible']) ? 1 : 0) : (int)($dbUser['ranking_visible'] ?? 0);

        $hasCommentColumn = false;
        $hasNewPrivacyColumns = false;
        try {
            $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'allow_profile_comments'");
            $hasCommentColumn = (bool)$columnStmt->fetch();

            $columnStmt2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'show_missions'");
            $hasNewPrivacyColumns = (bool)$columnStmt2->fetch();
        } catch (PDOException $e) {
            $hasCommentColumn = false;
            $hasNewPrivacyColumns = false;
        }

        if ($hasNewPrivacyColumns && $hasCommentColumn) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET profile_public = ?, stats_public = ?, allow_friend_requests = ?, searchable = ?, allow_profile_comments = ?,
                    show_missions = ?, show_online_status = ?, show_recent_activity = ?, ranking_visible = ?
                WHERE id = ?
            ");
            $stmt->execute([$profilePublic, $statsPublic, $allowFriendRequests, $searchable, $allowProfileComments, $showMissions, $showOnlineStatus, $showRecentActivity, $rankingVisible, $userId]);
        } elseif ($hasCommentColumn) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET profile_public = ?, stats_public = ?, allow_friend_requests = ?, searchable = ?, allow_profile_comments = ?, ranking_visible = ?
                WHERE id = ?
            ");
            $stmt->execute([$profilePublic, $statsPublic, $allowFriendRequests, $searchable, $allowProfileComments, $rankingVisible, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET profile_public = ?, stats_public = ?, allow_friend_requests = ?, searchable = ?, ranking_visible = ?
                WHERE id = ?
            ");
            $stmt->execute([$profilePublic, $statsPublic, $allowFriendRequests, $searchable, $rankingVisible, $userId]);
        }
        
        setSessionMessage('success', 'Ustawienia prywatności zostały pomyślnie zaktualizowane.');
    } catch (PDOException $e) {
        error_log("Update privacy error: " . $e->getMessage());
        setSessionMessage('error', 'Wystąpił błąd podczas aktualizacji ustawień prywatności.');
    }
}

securityRedirect('../settings.php', '../settings.php');
