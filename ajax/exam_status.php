<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(true, [], ['error' => 'unauthorized'], ['error' => 'unauthorized']);

$sessionId = securityInputInt($_GET['session'] ?? 0, 0, PHP_INT_MAX, 0);
$lite = ($_GET['lite'] ?? '') === '1';
if (!$sessionId) {
    echo securityJsonEncode(['error' => 'missing_session']);
    exit;
}

securityThrottle('exam-status:' . securityActorKey() . ':' . $sessionId, 120, 60, ['error' => 'rate_limited']);

try {
    $stmt = $pdo->prepare("
        SELECT es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, e.teacher_id
        FROM exam_sessions es
        JOIN exams e ON e.id = es.exam_id
        WHERE es.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        echo securityJsonEncode(['error' => 'not_found']);
        exit;
    }

    $isGuest = isGuestMode();
    $userId = $isGuest ? 0 : (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? 'user';
    $isTeacherOwner = roleHasAdminAccess($role) || ($role === 'teacher' && (int)$session['teacher_id'] === $userId);

    if ($isGuest) {
        $stmtParticipant = $pdo->prepare("
            SELECT id
            FROM exam_participants
            WHERE session_id = ? AND id = ? AND user_id IS NULL AND status != 'removed'
            LIMIT 1
        ");
        $stmtParticipant->execute([$sessionId, guestExamParticipantId($sessionId)]);
    } else {
        $stmtParticipant = $pdo->prepare("
            SELECT id
            FROM exam_participants
            WHERE session_id = ? AND user_id = ? AND status != 'removed'
            ORDER BY FIELD(status, 'taking_exam', 'in_lobby', 'finished', 'disconnected'), id DESC
            LIMIT 1
        ");
        $stmtParticipant->execute([$sessionId, $userId]);
    }
    $participantId = (int)$stmtParticipant->fetchColumn();

    if (!$isTeacherOwner && !$participantId) {
        http_response_code(403);
        echo securityJsonEncode(['error' => 'forbidden']);
        exit;
    }

    // Check expiry
    if (!empty($session['expires_at']) && strtotime($session['expires_at']) < time() && $session['status'] === 'lobby') {
        $pdo->prepare("UPDATE exam_sessions SET status = 'expired' WHERE id = ?")->execute([$sessionId]);
        $session['status'] = 'expired';
    }

    // Check for warnings for the current user
    $warnings = [];
    if ($participantId) {
        $stmtW = $pdo->prepare("
            SELECT id, message FROM exam_warnings 
            WHERE session_id = ? AND participant_id = ?
            AND is_read = 0
        ");
        $stmtW->execute([$sessionId, $participantId]);
        $warnings = $stmtW->fetchAll();
        
        if (!empty($warnings)) {
            $ids = array_column($warnings, 'id');
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $pdo->prepare("UPDATE exam_warnings SET is_read = 1 WHERE id IN ($placeholders)")->execute($ids);
        }
    }

    $response = [
        'status' => $session['status'],
        'started_at' => $session['started_at'],
        'paused_at' => $session['paused_at'],
        'paused_seconds' => (int)($session['paused_seconds'] ?? 0),
        'finished_at' => $session['finished_at'],
        'warnings' => array_column($warnings, 'message'),
    ];

    if (!$lite) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_participants WHERE session_id = ? AND status != 'removed'");
        $stmt->execute([$sessionId]);
        $response['participant_count'] = (int)$stmt->fetchColumn();

        $stmtP = $pdo->prepare("SELECT first_name, last_name, class, status FROM exam_participants WHERE session_id = ? AND status != 'removed' ORDER BY joined_at");
        $stmtP->execute([$sessionId]);
        $response['participants'] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    }

    echo securityJsonEncode($response);
} catch (PDOException $e) {
    securityAudit('exam_status_failed', ['session_id' => $sessionId], 'error');
    echo securityJsonEncode(['error' => 'db_error']);
}
