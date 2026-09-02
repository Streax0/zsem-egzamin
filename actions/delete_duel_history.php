<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf('delete_duel_history')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../user/history.php');
}

$duelId = securityInputInt($_POST['duel_id'] ?? 0, 0, PHP_INT_MAX, 0);
$returnTo = securityLocalRedirectTarget(
    (string)($_POST['return_to'] ?? '../user/history.php'),
    '../user/history.php',
    ['#^(?:\.\./)?(?:history|profile)\.php(?:\?id=\d+)?$#', '#^(?:\.\./)?(?:user/)?(?:history|profile)\.php(?:\?id=\d+)?$#']
);
$rateLimit = securityConsumeRateLimit('history:delete_duel:' . securityActorKey(), 25, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('duel_history_delete_rate_limited', ['duel_id' => $duelId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
    redirect($returnTo);
}

if (hideUserDuelFromHistory($pdo, (int)$_SESSION['user_id'], $duelId)) {
    setSessionMessage('success', 'Pojedynek został usunięty z Twojej historii.');
} else {
    setSessionMessage('error', 'Nie udało się usunąć pojedynku z historii.');
}

redirect($returnTo);
