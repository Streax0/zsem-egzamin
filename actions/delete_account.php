<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        header('Location: ../settings.php');
        exit;
    }

    $userId = $_SESSION['user_id'];

    try {
        // Delete user (cascades to results, progress, friends, notifications etc. based on schema)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        // Destroy session
        $_SESSION = [];
        session_destroy();
        
        // Redirect to login with success message (though session is gone, we can use a GET parameter or just a fresh start)
        header('Location: ../login.php?account_deleted=1');
        exit;
    } catch (Exception $e) {
        error_log('Delete account failed: ' . $e->getMessage());
        setSessionMessage('error', 'Nie udało się usunąć konta. Spróbuj ponownie za chwilę.');
        header('Location: ../settings.php');
        exit;
    }
}

header('Location: ../settings.php');
exit;
