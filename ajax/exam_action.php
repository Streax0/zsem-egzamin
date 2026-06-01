<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

requireJsonLogin();

requireJsonCsrfToken();

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$sessionId = (int)($_POST['session_id'] ?? 0);

if (!$sessionId) {
    echo json_encode(['success' => false, 'error' => 'No session ID']);
    exit;
}

// Get participant
$stmt = $pdo->prepare("SELECT * FROM exam_participants WHERE session_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$sessionId, $userId]);
$participant = $stmt->fetch();

if (!$participant) {
    echo json_encode(['success' => false, 'error' => 'Participant not found']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT es.status, e.allow_answer_changes
    FROM exam_sessions es
    JOIN exams e ON e.id = es.exam_id
    WHERE es.id = ?
    LIMIT 1
");
$stmt->execute([$sessionId]);
$sessionInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$sessionStatus = $sessionInfo['status'] ?? null;

switch ($action) {
    case 'submit_answer':
        if ($sessionStatus !== 'in_progress' || $participant['status'] !== 'taking_exam') {
            echo json_encode(['success' => false, 'error' => 'Exam is not accepting answers']);
            exit;
        }

        $questionId = (int)($_POST['question_id'] ?? 0);
        $userAnswer = strtoupper(trim($_POST['answer'] ?? ''));
        $questionOrder = (int)($_POST['question_order'] ?? 0);
        $timeSpent = (int)($_POST['time_spent'] ?? 0);
        if (empty($sessionInfo['allow_answer_changes'])) {
            $stmt = $pdo->prepare("SELECT 1 FROM exam_answers WHERE participant_id = ? AND question_id = ? LIMIT 1");
            $stmt->execute([(int)$participant['id'], $questionId]);
            if ($stmt->fetchColumn()) {
                echo json_encode(['success' => false, 'error' => 'Answer changes are disabled']);
                exit;
            }
        }

        $stmt = $pdo->prepare("SELECT question_order, correct_answer_override FROM exam_session_questions WHERE session_id = ? AND question_id = ? AND question_order = ? LIMIT 1");
        $stmt->execute([$sessionId, $questionId, $questionOrder]);
        $sessionQuestion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sessionQuestion) {
            echo json_encode(['success' => false, 'error' => 'Question is not assigned to this session']);
            exit;
        }

        // Resolve only this question from DB, with JSON fallback when needed.
        $questionRows = getQuestionsByIds($pdo, [$questionId]);
        $question = $questionRows[0] ?? null;
        if (!$question) {
            echo json_encode(['success' => false, 'error' => 'Question not found']);
            exit;
        }

        $overrideAnswer = strtoupper((string)($sessionQuestion['correct_answer_override'] ?? ''));
        $correctAnswer = in_array($overrideAnswer, ['A','B','C','D'], true) ? $overrideAnswer : strtoupper($question['correct_answer'] ?? '');
        $isCorrect = ($userAnswer === $correctAnswer) ? 1 : 0;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO exam_answers (participant_id, session_id, question_id, question_order, user_answer, correct_answer, is_correct, time_spent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE user_answer = VALUES(user_answer), is_correct = VALUES(is_correct), time_spent = VALUES(time_spent)
            ");
            $stmt->execute([$participant['id'], $sessionId, $questionId, $questionOrder, $userAnswer, $correctAnswer, $isCorrect, $timeSpent]);

            $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(is_correct), 0) FROM exam_answers WHERE participant_id = ?");
            $stmt->execute([$participant['id']]);
            $stats = $stmt->fetch(PDO::FETCH_NUM);
            $answeredCount = (int)$stats[0];
            $correctCount = (int)$stats[1];

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_session_questions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $totalQuestions = (int)$stmt->fetchColumn();

            $percent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

            $pdo->prepare("UPDATE exam_participants SET current_question = ?, total_answered = ?, correct_answers = ?, score_percent = ?, last_activity = NOW() WHERE id = ?")
                ->execute([$answeredCount, $answeredCount, $correctCount, $percent, $participant['id']]);

            $finished = $answeredCount >= $totalQuestions;
            if ($finished) {
                $pdo->prepare("UPDATE exam_participants SET status = 'finished', finished_at = NOW() WHERE id = ?")
                    ->execute([$participant['id']]);
            }

            $pdo->commit();
            echo json_encode($finished
                ? ['success' => true, 'finished' => true, 'redirect' => "finished.php?session=$sessionId"]
                : ['success' => true, 'finished' => false, 'answered' => $answeredCount, 'total' => $totalQuestions]
            );
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Exam answer save failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Answer save failed']);
        }
        break;

    case 'report_violation':
        if (!in_array($sessionStatus, ['in_progress', 'paused'], true) || !in_array($participant['status'], ['taking_exam', 'in_lobby'], true)) {
            echo json_encode(['success' => false, 'error' => 'Exam is not active']);
            exit;
        }

        $allowedViolationTypes = ['tab_switch', 'window_blur', 'fullscreen_exit', 'copy_paste', 'other'];
        $type = $_POST['violation_type'] ?? 'other';
        if (!in_array($type, $allowedViolationTypes, true)) {
            $type = 'other';
        }
        $qId = (int)($_POST['question_id'] ?? 0);
        
        $stmt = $pdo->prepare("INSERT INTO exam_violations (participant_id, session_id, violation_type, question_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$participant['id'], $sessionId, $type, $qId]);
        
        $pdo->prepare("UPDATE exam_participants SET violation_count = violation_count + 1 WHERE id = ?")
            ->execute([$participant['id']]);
            
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
