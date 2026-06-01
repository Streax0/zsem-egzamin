<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
startSecureSession();

requireJsonLogin(false, ['teacher', 'admin', 'dyrektor'], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT e.id, e.title, e.question_count, e.max_participants, e.created_at,
                es.id as session_id, es.access_code, es.status,
                (SELECT COUNT(*) FROM exam_participants WHERE session_id = es.id AND status != 'removed') as participant_count
         FROM exams e
         LEFT JOIN exam_sessions es ON es.exam_id = e.id
         WHERE e.teacher_id = ?
         ORDER BY e.created_at DESC
         LIMIT 20"
    );
    $stmt->execute([$userId]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalExams = count($exams);
    $activeExams = 0;
    $totalParticipants = 0;

    foreach ($exams as $exam) {
        if (in_array($exam['status'], ['lobby', 'in_progress', 'paused'], true)) {
            $activeExams++;
        }
        $totalParticipants += (int)($exam['participant_count'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'totalExams' => $totalExams,
        'activeExams' => $activeExams,
        'totalParticipants' => $totalParticipants,
        'exams' => $exams,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'db_error']);
}
