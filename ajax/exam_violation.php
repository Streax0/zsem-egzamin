<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
startSecureSession();

requireJsonLogin(true, [], ['error' => 'unauthorized'], ['error' => 'unauthorized']);

requireJsonCsrfToken();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($input['session_id'] ?? 0);
$participantId = (int)($input['participant_id'] ?? 0);
$allowedViolationTypes = ['tab_switch', 'window_blur', 'fullscreen_exit', 'copy_paste', 'other'];
$type = $input['violation_type'] ?? 'other';
if (!in_array($type, $allowedViolationTypes, true)) {
    $type = 'other';
}
$questionId = (int)($input['question_id'] ?? 0);

if (!$sessionId || !$participantId) {
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

try {
    if (isGuestMode()) {
        $stmt = $pdo->prepare("SELECT id FROM exam_participants WHERE id = ? AND session_id = ? AND user_id IS NULL AND status != 'removed' LIMIT 1");
        $stmt->execute([$participantId, $sessionId]);
        $allowed = $participantId === guestExamParticipantId($sessionId) && (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT id FROM exam_participants WHERE id = ? AND session_id = ? AND user_id = ? AND status != 'removed' LIMIT 1");
        $stmt->execute([$participantId, $sessionId, $_SESSION['user_id']]);
        $allowed = (bool)$stmt->fetchColumn();
    }
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    // 1. Record the violation detail
    $stmt = $pdo->prepare("
        INSERT INTO exam_violations (participant_id, session_id, violation_type, question_id, timestamp)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$participantId, $sessionId, $type, $questionId]);

    // 2. Increment global violation count for participant
    $pdo->prepare("UPDATE exam_participants SET violation_count = violation_count + 1 WHERE id = ?")
        ->execute([$participantId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Violation report error: " . $e->getMessage());
    echo json_encode(['error' => 'db_error']);
}
