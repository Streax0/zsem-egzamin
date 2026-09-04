<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(true, [], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

requireJsonCsrfToken();

$action = securityInputEnum($_POST['action'] ?? '', ['submit_answer', 'report_violation'], '');
$userId = isGuestMode() ? 0 : (int)$_SESSION['user_id'];
$sessionId = securityInputInt($_POST['session_id'] ?? 0, 0, PHP_INT_MAX, 0);

if (!$sessionId) {
    echo securityJsonEncode(['success' => false, 'error' => 'No session ID']);
    exit;
}

if ($action === '') {
    securityAudit('exam_invalid_action', ['action' => $_POST['action'] ?? ''], 'warning');
    echo securityJsonEncode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$rateLimit = securityConsumeRateLimit('exam-action:' . securityActorKey() . ':' . $sessionId . ':' . $action, $action === 'report_violation' ? 30 : 120, 60);
if (empty($rateLimit['allowed'])) {
    http_response_code(429);
    securityAudit('exam_rate_limited', ['session_id' => $sessionId, 'action' => $action], 'warning');
    echo securityJsonEncode([
        'success' => false,
        'error' => 'Zbyt wiele akcji naraz. Odczekaj chwilę i spróbuj ponownie.',
        'retry_after' => (int)($rateLimit['retry_after'] ?? 0),
    ]);
    exit;
}

// Get participant
$stmt = isGuestMode()
    ? $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND id = ? AND user_id IS NULL ORDER BY id DESC LIMIT 1")
    : $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute(isGuestMode() ? [$sessionId, guestExamParticipantId($sessionId)] : [$sessionId, $userId]);
$participant = $stmt->fetch();

if (!$participant) {
    echo securityJsonEncode(['success' => false, 'error' => 'Participant not found']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT es.status, es.exam_id, e.allow_answer_changes, e.teacher_id, e.title AS exam_title
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
            echo securityJsonEncode(['success' => false, 'error' => 'Exam is not accepting answers']);
            exit;
        }

        $questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        $userAnswer = securityInputAnswerLetter($_POST['answer'] ?? '');
        $questionOrder = securityInputInt($_POST['question_order'] ?? 0, 0, 1000, 0);
        $timeSpent = securityInputInt($_POST['time_spent'] ?? 0, 0, 86400, 0);
        if (empty($sessionInfo['allow_answer_changes'])) {
            $stmt = $pdo->prepare("SELECT 1 FROM exam_answers WHERE participant_id = ? AND question_id = ? LIMIT 1");
            $stmt->execute([(int)$participant['id'], $questionId]);
            if ($stmt->fetchColumn()) {
                echo securityJsonEncode(['success' => false, 'error' => 'Answer changes are disabled']);
                exit;
            }
        }

        $stmt = $pdo->prepare("SELECT question_order, correct_answer_override FROM exam_session_questions WHERE session_id = ? AND question_id = ? AND question_order = ? LIMIT 1");
        $stmt->execute([$sessionId, $questionId, $questionOrder]);
        $sessionQuestion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sessionQuestion) {
            echo securityJsonEncode(['success' => false, 'error' => 'Question is not assigned to this session']);
            exit;
        }

        // Resolve only this question from DB, with JSON fallback when needed.
        $questionRows = getQuestionsByIds($pdo, [$questionId]);
        $question = $questionRows[0] ?? null;
        if (!$question) {
            echo securityJsonEncode(['success' => false, 'error' => 'Question not found']);
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
                $startedAt = $participant['started_at'] ?? $participant['joined_at'] ?? null;
                $totalTimeSpent = $startedAt ? max(1, time() - strtotime($startedAt)) : 0;
                $pdo->prepare("UPDATE exam_participants SET status = 'finished', finished_at = NOW(), time_spent = ? WHERE id = ?")
                    ->execute([$totalTimeSpent, $participant['id']]);
            }

            $pdo->commit();
            echo securityJsonEncode($finished
                ? ['success' => true, 'finished' => true, 'redirect' => "finished.php?session=$sessionId"]
                : ['success' => true, 'finished' => false, 'answered' => $answeredCount, 'total' => $totalQuestions]
            );
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Exam answer save failed: ' . $e->getMessage());
            securityAudit('exam_answer_save_failed', ['session_id' => $sessionId, 'participant_id' => $participant['id'] ?? 0], 'error');
            echo securityJsonEncode(['success' => false, 'error' => 'Answer save failed']);
        }
        break;

    case 'report_violation':
        if (!in_array($sessionStatus, ['in_progress', 'paused'], true) || !in_array($participant['status'], ['taking_exam', 'in_lobby'], true)) {
            echo securityJsonEncode(['success' => false, 'error' => 'Exam is not active']);
            exit;
        }

        $allowedViolationTypes = ['tab_switch', 'window_blur', 'fullscreen_exit', 'copy_paste', 'screenshot_attempt', 'other'];
        $type = securityInputEnum($_POST['violation_type'] ?? 'other', $allowedViolationTypes, 'other');
        $qId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        
        $stmt = $pdo->prepare("INSERT INTO exam_violations (participant_id, session_id, violation_type, question_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$participant['id'], $sessionId, $type, $qId]);
        
        $pdo->prepare("UPDATE exam_participants SET violation_count = violation_count + 1 WHERE id = ?")
            ->execute([$participant['id']]);

        if (in_array($type, ['copy_paste', 'screenshot_attempt'], true)
            && examAiCopyGuardEnabled($pdo, (int)($sessionInfo['exam_id'] ?? 0))) {
            notifyTeacherAboutExamAiGuard($pdo, $sessionInfo, $participant, $type);
        }
            
        echo securityJsonEncode(['success' => true]);
        break;

    default:
        echo securityJsonEncode(['success' => false, 'error' => 'Unknown action']);
}
