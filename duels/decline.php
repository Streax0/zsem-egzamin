<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

$myId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../index.php');
}

$duelId = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare("UPDATE duels SET status = 'declined' WHERE id = ? AND opponent_id = ? AND status = 'pending'");
    $stmt->execute([$duelId, $myId]);
    setSessionMessage('info', 'Wyzwanie zostało odrzucone.');
} catch (PDOException $e) {
    error_log('Duel decline error: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się odrzucić wyzwania.');
}
redirect('../index.php');
