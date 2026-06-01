<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

startSecureSession();
requireJsonLogin(false, ['teacher', 'admin', 'dyrektor'], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireJsonCsrfToken();

    $participantId = (int)($_POST['participant_id'] ?? 0);
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $message = function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);

    if (!$participantId || !$sessionId || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Missing data']);
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
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO exam_warnings (participant_id, session_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$participantId, $sessionId, $message]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('Send warning failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Nie udało się wysłać ostrzeżenia.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
