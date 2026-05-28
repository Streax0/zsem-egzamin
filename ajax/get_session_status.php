<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sessionId = (int)($_GET['session_id'] ?? 0);
$scope = $_GET['scope'] ?? 'full';
if (!$sessionId) {
    echo json_encode(['success' => false, 'error' => 'Missing session ID']);
    exit;
}

try {
    // Check if session belongs to teacher
    $stmt = $pdo->prepare("
        SELECT es.* FROM exam_sessions es 
        JOIN exams e ON es.exam_id = e.id 
        WHERE es.id = ? AND e.teacher_id = ?
    ");
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmt->fetch();

    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }

    // Get participants
    $stmt = $pdo->prepare("SELECT * FROM exam_participants WHERE session_id = ? ORDER BY joined_at ASC");
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
            SELECT ev.*, p.first_name, p.last_name 
            FROM exam_violations ev 
            JOIN exam_participants p ON ev.participant_id = p.id 
            WHERE ev.session_id = ? 
            ORDER BY ev.created_at DESC LIMIT 5
        ");
        $stmt->execute([$sessionId]);
        $payload['violations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($payload);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
