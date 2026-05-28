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

header('Location: ../settings.php');
exit;
