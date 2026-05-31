<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '', 'delete_test_result')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../history.php');
}

$resultId = (int)($_POST['result_id'] ?? 0);
$returnTo = (string)($_POST['return_to'] ?? '../history.php');
$returnTo = preg_match('#^(?:\.\./)?(?:history|profile)\.php(?:\?id=\d+)?$#', $returnTo) ? $returnTo : '../history.php';

if (deleteUserTestResult($pdo, (int)$_SESSION['user_id'], $resultId)) {
    setSessionMessage('success', 'Wynik testu został usunięty z historii.');
} else {
    setSessionMessage('error', 'Nie udało się usunąć wyniku.');
}

redirect($returnTo);
