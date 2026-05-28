<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        header('Location: ../settings.php');
        exit;
    }

    $userId = $_SESSION['user_id'];
    
    // Checkboxes are only sent if they are checked
    $profilePublic = isset($_POST['profile_public']) ? 1 : 0;
    $statsPublic = isset($_POST['stats_public']) ? 1 : 0;
    $allowFriendRequests = isset($_POST['allow_friend_requests']) ? 1 : 0;
    $searchable = isset($_POST['searchable']) ? 1 : 0;
    $allowProfileComments = isset($_POST['allow_profile_comments']) ? 1 : 0;
    $role = $_SESSION['role'] ?? 'user';

    try {
        ensurePlatformEnhancements($pdo);
        $roleStmt = $pdo->prepare("SELECT role, ranking_visible FROM users WHERE id = ? LIMIT 1");
        $roleStmt->execute([$userId]);
        $dbUser = $roleStmt->fetch(PDO::FETCH_ASSOC) ?: ['role' => $role, 'ranking_visible' => 0];
        $dbRole = $dbUser['role'] ?? $role;
        $rankingVisible = $dbRole === 'teacher' ? (isset($_POST['ranking_visible']) ? 1 : 0) : (int)($dbUser['ranking_visible'] ?? 0);
        $hasCommentColumn = false;
        try {
            $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'allow_profile_comments'");
            $hasCommentColumn = (bool)$columnStmt->fetch();
        } catch (PDOException $e) {
            $hasCommentColumn = false;
        }

        if ($hasCommentColumn) {
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

header('Location: ../settings.php');
exit;
