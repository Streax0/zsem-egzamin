<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '', 'delete_duel_history')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../history.php');
}

$duelId = (int)($_POST['duel_id'] ?? 0);
$returnTo = (string)($_POST['return_to'] ?? '../history.php');
$returnTo = preg_match('#^(?:\.\./)?(?:history|profile)\.php(?:\?id=\d+)?$#', $returnTo) ? $returnTo : '../history.php';

if (hideUserDuelFromHistory($pdo, (int)$_SESSION['user_id'], $duelId)) {
    setSessionMessage('success', 'Pojedynek został usunięty z Twojej historii.');
} else {
    setSessionMessage('error', 'Nie udało się usunąć pojedynku z historii.');
}

redirect($returnTo);
