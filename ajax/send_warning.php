<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();
requireJsonLogin(false, ['teacher', 'admin', 'dyrektor'], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireJsonCsrfToken();

    $participantId = securityInputInt($_POST['participant_id'] ?? 0, 0, PHP_INT_MAX, 0);
    $sessionId = securityInputInt($_POST['session_id'] ?? 0, 0, PHP_INT_MAX, 0);
    $message = securityInputString($_POST['message'] ?? '', 500);

    $limit = securityConsumeRateLimit('send-warning:' . securityActorKey() . ':' . $sessionId, 30, 60);
    if (empty($limit['allowed'])) {
        http_response_code(429);
        echo securityJsonEncode(['success' => false, 'message' => 'Zbyt wiele ostrzeżeń naraz.', 'retry_after' => (int)($limit['retry_after'] ?? 0)]);
        exit;
    }

    if (!$participantId || !$sessionId || empty($message)) {
        echo securityJsonEncode(['success' => false, 'message' => 'Missing data']);
        exit;
    }

    try {
        if (!roleHasAdminAccess($_SESSION['role'] ?? '')) {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM exam_participants p
                JOIN exam_sessions es ON es.id = p.session_id
                JOIN exams e ON e.id = es.exam_id
                WHERE p.id = ? AND p.session_id = ? AND e.teacher_id = ?
                LIMIT 1
            ");
            $stmt->execute([$participantId, $sessionId, $_SESSION['user_id']]);
            if (!$stmt->fetchColumn()) {
                http_response_code(403);
                echo securityJsonEncode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO exam_warnings (participant_id, session_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$participantId, $sessionId, $message]);
        echo securityJsonEncode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Send warning failed: ' . $e->getMessage());
        securityAudit('send_warning_failed', ['session_id' => $sessionId, 'participant_id' => $participantId], 'error');
        echo securityJsonEncode(['success' => false, 'message' => 'Nie udało się wysłać ostrzeżenia.']);
    }
} else {
    echo securityJsonEncode(['success' => false, 'message' => 'Method not allowed']);
}
