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
    $updateStmt = $pdo->prepare("
        INSERT INTO user_question_progress (user_id, question_id, is_mastered, last_seen) 
        VALUES (:user_id, :question_id, 1, NOW()) 
        ON DUPLICATE KEY UPDATE is_mastered = 1, last_seen = NOW()
    ");
    $updateStmt->execute(['user_id' => $userId, 'question_id' => $questionId]);
    
    echo securityJsonEncode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in mark_mastered.php: " . $e->getMessage());
    securityAudit('mark_mastered_failed', ['user_id' => $userId, 'question_id' => $questionId], 'error');
    echo securityJsonEncode(['success' => false, 'error' => 'Database error']);
}
