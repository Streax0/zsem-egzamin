<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Ensure user is logged in
startSecureSession();
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get question ID from POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// CSRF Protection
$csrfToken = $_POST['csrf_token'] ?? '';
// Since this is called via AJAX from progress.php, we need to check the default csrf token
if (!validateCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$questionId = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;

if ($questionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid question ID']);
    exit;
}

try {
    // Check if progress record exists and is in progress
    $stmt = $pdo->prepare("SELECT id, is_mastered FROM user_question_progress WHERE user_id = :user_id AND question_id = :question_id");
    $stmt->execute(['user_id' => $userId, 'question_id' => $questionId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$progress) {
        echo json_encode(['success' => false, 'error' => 'Pytanie nie jest jeszcze śledzone. Najpierw rozwiąż je, aby móc je oznaczyć jako opanowane.']);
        exit;
    }
    if ((int)$progress['is_mastered'] === 1) {
        echo json_encode(['success' => false, 'error' => 'Pytanie jest już opanowane.']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE user_question_progress SET is_mastered = 1 WHERE user_id = :user_id AND question_id = :question_id");
    $updateStmt->execute(['user_id' => $userId, 'question_id' => $questionId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in mark_mastered.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
