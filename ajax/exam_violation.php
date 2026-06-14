<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(true, [], ['error' => 'unauthorized'], ['error' => 'unauthorized']);

requireJsonCsrfToken();

// Get JSON input
$input = securityJsonBody();
$sessionId = securityInputInt($input['session_id'] ?? 0, 0, PHP_INT_MAX, 0);
$participantId = securityInputInt($input['participant_id'] ?? 0, 0, PHP_INT_MAX, 0);
$allowedViolationTypes = ['tab_switch', 'window_blur', 'fullscreen_exit', 'copy_paste', 'screenshot_attempt', 'other'];
$type = securityInputEnum($input['violation_type'] ?? 'other', $allowedViolationTypes, 'other');
$questionId = securityInputInt($input['question_id'] ?? 0, 0, PHP_INT_MAX, 0);

if (!$sessionId || !$participantId) {
    echo securityJsonEncode(['error' => 'invalid_request']);
    exit;
}

$limit = securityConsumeRateLimit('exam-violation:' . securityActorKey() . ':' . $sessionId, 40, 60);
if (empty($limit['allowed'])) {
    http_response_code(429);
    echo securityJsonEncode(['error' => 'rate_limited', 'retry_after' => (int)($limit['retry_after'] ?? 0)]);
    exit;
}

try {
    if (isGuestMode()) {
        $stmt = $pdo->prepare("SELECT id, session_id, first_name, last_name FROM exam_participants WHERE id = ? AND session_id = ? AND user_id IS NULL AND status != 'removed' LIMIT 1");
        $stmt->execute([$participantId, $sessionId]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $allowed = $participantId === guestExamParticipantId($sessionId) && $participant !== null;
    } else {
        $stmt = $pdo->prepare("SELECT id, session_id, first_name, last_name FROM exam_participants WHERE id = ? AND session_id = ? AND user_id = ? AND status != 'removed' LIMIT 1");
        $stmt->execute([$participantId, $sessionId, $_SESSION['user_id']]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $allowed = $participant !== null;
    }
    if (!$allowed) {
        http_response_code(403);
        echo securityJsonEncode(['error' => 'forbidden']);
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

    if (in_array($type, ['copy_paste', 'screenshot_attempt'], true)) {
        $stmt = $pdo->prepare("
            SELECT es.exam_id, e.teacher_id, e.title AS exam_title
            FROM exam_sessions es
            JOIN exams e ON e.id = es.exam_id
            WHERE es.id = ?
            LIMIT 1
        ");
        $stmt->execute([$sessionId]);
        $sessionInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (examAiCopyGuardEnabled($pdo, (int)($sessionInfo['exam_id'] ?? 0))) {
            notifyTeacherAboutExamAiGuard($pdo, $sessionInfo, $participant ?? [], $type);
        }
    }

    echo securityJsonEncode(['success' => true]);
} catch (PDOException $e) {
    error_log("Violation report error: " . $e->getMessage());
    securityAudit('exam_violation_failed', ['session_id' => $sessionId, 'participant_id' => $participantId], 'error');
    echo securityJsonEncode(['error' => 'db_error']);
}
