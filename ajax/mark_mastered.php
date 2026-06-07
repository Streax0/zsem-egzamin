<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

startSecureSession();
securityApplyJsonHeaders();
requireJsonLogin(false, [], ['success' => false, 'error' => 'Not authenticated'], ['success' => false, 'error' => 'Not authenticated']);

$userId = (int)$_SESSION['user_id'];

// Get question ID from POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo securityJsonEncode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// CSRF Protection
// Since this is called via AJAX from progress.php, we need to check the default csrf token
if (!securityValidateRequestCsrf()) {
    echo securityJsonEncode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$limit = securityConsumeRateLimit('mark-mastered:' . securityActorKey(), 40, 60);
if (empty($limit['allowed'])) {
    http_response_code(429);
    echo securityJsonEncode(['success' => false, 'error' => 'Zbyt wiele akcji naraz.', 'retry_after' => (int)($limit['retry_after'] ?? 0)]);
    exit;
}

$questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);

if ($questionId <= 0) {
    echo securityJsonEncode(['success' => false, 'error' => 'Invalid question ID']);
    exit;
}

try {
    // Check if progress record exists and is in progress
    $stmt = $pdo->prepare("SELECT id, is_mastered FROM user_question_progress WHERE user_id = :user_id AND question_id = :question_id");
    $stmt->execute(['user_id' => $userId, 'question_id' => $questionId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$progress) {
        echo securityJsonEncode(['success' => false, 'error' => 'Pytanie nie jest jeszcze śledzone. Najpierw rozwiąż je, aby móc je oznaczyć jako opanowane.']);
        exit;
    }
    if ((int)$progress['is_mastered'] === 1) {
        echo securityJsonEncode(['success' => false, 'error' => 'Pytanie jest już opanowane.']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE user_question_progress SET is_mastered = 1 WHERE user_id = :user_id AND question_id = :question_id");
    $updateStmt->execute(['user_id' => $userId, 'question_id' => $questionId]);
    
    echo securityJsonEncode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in mark_mastered.php: " . $e->getMessage());
    securityAudit('mark_mastered_failed', ['user_id' => $userId, 'question_id' => $questionId], 'error');
    echo securityJsonEncode(['success' => false, 'error' => 'Database error']);
}
