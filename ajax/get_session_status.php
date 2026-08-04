<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(false, ['teacher', 'admin', 'dyrektor'], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

$sessionId = securityInputInt($_GET['session_id'] ?? 0, 0, PHP_INT_MAX, 0);
$scope = securityInputEnum($_GET['scope'] ?? 'full', ['full', 'participants'], 'full');
if (!$sessionId) {
    echo securityJsonEncode(['success' => false, 'error' => 'Missing session ID']);
    exit;
}

securityThrottle('teacher-session-status:' . securityActorKey() . ':' . $sessionId . ':' . $scope, 120, 60, ['success' => false, 'error' => 'rate_limited']);

try {
    // Check if session belongs to teacher
    $stmt = $pdo->prepare("
        SELECT es.id, es.exam_id, es.access_code, es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, es.created_at FROM exam_sessions es 
        JOIN exams e ON es.exam_id = e.id 
        WHERE es.id = ? AND e.teacher_id = ?
    ");
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmt->fetch();

    if (!$session) {
        echo securityJsonEncode(['success' => false, 'error' => 'Session not found']);
        exit;
    }

    // Get participants
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? ORDER BY joined_at ASC");
    $stmt->execute([$sessionId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload = [
        'success' => true,
        'status' => $session['status'],
        'participants' => $participants,
        'server_time' => date('H:i:s')
    ];

    if ($scope !== 'participants') {
        // Get latest violations for legacy live status consumers.
        $stmt = $pdo->prepare("
            SELECT ev.id, ev.participant_id, ev.session_id, ev.violation_type, ev.question_id, ev.details, ev.created_at, p.first_name, p.last_name 
            FROM exam_violations ev 
            JOIN exam_participants p ON ev.participant_id = p.id 
            WHERE ev.session_id = ? 
            ORDER BY ev.created_at DESC LIMIT 5
        ");
        $stmt->execute([$sessionId]);
        $payload['violations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo securityJsonEncode($payload);

} catch (PDOException $e) {
    securityAudit('get_session_status_failed', ['session_id' => $sessionId], 'error');
    echo securityJsonEncode(['success' => false, 'error' => 'Database error']);
}
