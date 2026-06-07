<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
if (!isLoggedIn()) {
    securityRedirect('../login.php', '../login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!securityValidateRequestCsrf()) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        securityRedirect('../settings.php', '../settings.php');
    }

    $userId = $_SESSION['user_id'];
    $rateLimit = securityConsumeRateLimit('settings:reset_progress:' . securityActorKey(), 3, 300);
    if (empty($rateLimit['allowed'])) {
        securityAudit('reset_progress_rate_limited', ['user_id' => (int)$userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        setSessionMessage('error', 'Zbyt wiele prób resetu. Spróbuj za chwilę.');
        securityRedirect('../settings.php', '../settings.php');
    }

    try {
        $pdo->beginTransaction();

        // 1. Delete test results (cascades to test_answers)
        $stmt = $pdo->prepare("DELETE FROM test_results WHERE user_id = ?");
        $stmt->execute([$userId]);

        // 2. Delete question progress
        $stmt = $pdo->prepare("DELETE FROM user_question_progress WHERE user_id = ?");
        $stmt->execute([$userId]);

        // 3. Reset XP
        $stmt = $pdo->prepare("UPDATE users SET xp = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        // 4. Delete user badges
        $stmt = $pdo->prepare("DELETE FROM user_badges WHERE user_id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();
        setSessionMessage('success', 'Twój progres został pomyślnie zresetowany. Możesz zacząć naukę od nowa!');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Reset progress failed: ' . $e->getMessage());
        setSessionMessage('error', 'Nie udało się zresetować progresu. Spróbuj ponownie za chwilę.');
    }
}

securityRedirect('../settings.php', '../settings.php');
