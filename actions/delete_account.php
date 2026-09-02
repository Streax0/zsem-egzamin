<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!securityValidateRequestCsrf()) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        securityRedirect('../user/settings.php', '../user/settings.php');
    }

    $userId = $_SESSION['user_id'];
    $rateLimit = securityConsumeRateLimit('settings:delete_account:' . securityActorKey(), 2, 300);
    if (empty($rateLimit['allowed'])) {
        securityAudit('delete_account_rate_limited', ['user_id' => (int)$userId, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        setSessionMessage('error', 'Zbyt wiele prób usunięcia konta. Spróbuj za chwilę.');
        securityRedirect('../user/settings.php', '../user/settings.php');
    }

    try {
        // Delete user (cascades to results, progress, friends, notifications etc. based on schema)
        if (!deleteUser($pdo, (int)$userId)) {
            throw new RuntimeException('deleteUser returned false');
        }

        // Destroy session
        $_SESSION = [];
        session_destroy();
        
        // Redirect to login with success message (though session is gone, we can use a GET parameter or just a fresh start)
        securityRedirect('../auth/login.php?account_deleted=1', '../auth/login.php');
    } catch (Exception $e) {
        error_log('Delete account failed: ' . $e->getMessage());
        setSessionMessage('error', 'Nie udało się usunąć konta. Spróbuj ponownie za chwilę.');
        securityRedirect('../user/settings.php', '../user/settings.php');
    }
}

securityRedirect('../user/settings.php', '../user/settings.php');
