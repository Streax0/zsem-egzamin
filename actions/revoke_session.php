<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autoloader.php';

use App\Core\DeviceSessionManager;

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateRequestCsrfToken('revoke_session')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie wylogowania.');
    securityRedirect('../user/settings.php', '../user/settings.php');
}

$userId = (int)$_SESSION['user_id'];
if (function_exists('securityConsumeRateLimit')) {
    $actorKey = function_exists('securityActorKey') ? securityActorKey() : (string)$userId;
    $rateLimit = securityConsumeRateLimit('auth:revoke_session:' . $actorKey, 15, 60);
    if (empty($rateLimit['allowed'])) {
        if (function_exists('securityAudit')) {
            securityAudit('revoke_session_rate_limited', ['user_id' => $userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        }
        setSessionMessage('error', 'Zbyt wiele akcji naraz. Spróbuj za chwilę.');
        securityRedirect('../user/settings.php', '../user/settings.php');
    }
}

$action = (string)($_POST['action'] ?? 'revoke_single');
$sessionManager = new DeviceSessionManager($pdo);
$currentHash = function_exists('currentSessionHash') ? currentSessionHash() : '';

if ($action === 'revoke_all_except') {
    $count = $sessionManager->revokeAllExcept($userId, $currentHash);
    setSessionMessage('success', "Wszystkie pozostałe urządzenia ({$count}) zostały pomyślnie wylogowane.");
} else {
    $sessionHash = (string)($_POST['session_hash'] ?? '');
    if ($sessionHash === '' || ($currentHash !== '' && hash_equals($sessionHash, $currentHash))) {
        setSessionMessage('error', 'Nie można wylogować bieżącej sesji w ten sposób. Użyj przycisku wylogowania.');
    } else {
        $success = $sessionManager->revokeSession($userId, $sessionHash);
        if ($success) {
            setSessionMessage('success', 'Wybrane urządzenie zostało pomyślnie wylogowane.');
        } else {
            setSessionMessage('error', 'Nie znaleziono wskazanej sesji lub została już wygaszona.');
        }
    }
}

securityRedirect('../user/settings.php', '../user/settings.php');
