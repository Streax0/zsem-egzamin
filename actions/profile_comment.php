<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Błąd bezpieczeństwa.');
    redirect('../user/profile.php');
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['comment_action'] ?? 'add';
$profileId = (int)($_POST['profile_user_id'] ?? $userId);

try {
    if ($action === 'delete') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'user';
        if (roleHasAdminAccess($role)) {
            $stmt = $pdo->prepare("DELETE FROM profile_comments WHERE id = ?");
            $stmt->execute([$commentId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM profile_comments WHERE id = ? AND (author_id = ? OR profile_user_id = ?)");
            $stmt->execute([$commentId, $userId, $userId]);
        }
        setSessionMessage('success', 'Komentarz został usunięty.');
        redirect('../user/profile.php?id=' . $profileId . '#profile-comments');
    }

    $text = trim($_POST['comment_text'] ?? '');
    if ($text === '' || mb_strlen($text) > 100) {
        setSessionMessage('error', 'Komentarz musi mieć od 1 do 100 znaków.');
        redirect('../user/profile.php?id=' . $profileId . '#profile-comments');
    }
    if (containsProfanity($text)) {
        setSessionMessage('error', 'Komentarz zawiera niedozwolone słowa.');
        redirect('../user/profile.php?id=' . $profileId . '#profile-comments');
    }

    try {
        $stmt = $pdo->prepare("SELECT allow_profile_comments FROM users WHERE id = ?");
        $stmt->execute([$profileId]);
        $allowComments = $stmt->fetchColumn();
        if ($allowComments !== false && (int)$allowComments !== 1 && $profileId !== $userId && !roleHasAdminAccess($_SESSION['role'] ?? 'user')) {
            setSessionMessage('error', 'Ten użytkownik wyłączył komentarze pod profilem.');
            redirect('../user/profile.php?id=' . $profileId . '#profile-comments');
        }
    } catch (PDOException $e) {
        // Older schema without this column keeps comments enabled.
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user_id = ?");
    $countStmt->execute([$profileId]);
    if ((int)$countStmt->fetchColumn() >= 20) {
        setSessionMessage('error', 'Ten profil ma już maksymalnie 20 komentarzy.');
        redirect('../user/profile.php?id=' . $profileId . '#profile-comments');
    }

    $cleanText = trim(strip_tags($text));
    $stmt = $pdo->prepare("INSERT INTO profile_comments (profile_user_id, author_id, comment_text) VALUES (?, ?, ?)");
    $stmt->execute([$profileId, $userId, mb_substr($cleanText, 0, 100)]);
    setSessionMessage('success', 'Komentarz został dodany.');
} catch (PDOException $e) {
    error_log('Profile comment failed: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się zapisać komentarza. Sprawdź, czy full_schema.sql został zaimportowany.');
}

redirect('../user/profile.php?id=' . $profileId . '#profile-comments');
