<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        header('Location: ../social.php');
        exit();
    }

    $myId = $_SESSION['user_id'];
    $otherUserId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
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

header('Location: ../social.php');
exit();
