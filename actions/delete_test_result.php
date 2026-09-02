<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !securityValidateRequestCsrf('delete_test_result')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../user/history.php');
}

$resultId = securityInputInt($_POST['result_id'] ?? 0, 0, PHP_INT_MAX, 0);
$returnTo = securityLocalRedirectTarget(
    (string)($_POST['return_to'] ?? '../user/history.php'),
    '../user/history.php',
    ['#^(?:\.\./)?(?:history|profile)\.php(?:\?id=\d+)?$#', '#^(?:\.\./)?(?:user/)?(?:history|profile)\.php(?:\?id=\d+)?$#']
);
$rateLimit = securityConsumeRateLimit('history:delete_test_result:' . securityActorKey(), 25, 60);
if (empty($rateLimit['allowed'])) {
    securityAudit('test_result_delete_rate_limited', ['result_id' => $resultId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
    redirect($returnTo);
}

if (deleteUserTestResult($pdo, (int)$_SESSION['user_id'], $resultId)) {
    setSessionMessage('success', 'Wynik testu został usunięty z historii.');
} else {
    setSessionMessage('error', 'Nie udało się usunąć wyniku.');
}

redirect($returnTo);
