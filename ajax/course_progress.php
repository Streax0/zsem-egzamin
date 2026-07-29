<?php
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/CourseService.php';

startSecureSession();
securityApplyJsonHeaders();

function courseProgressFail(string $message, int $status = 422): never {
    securitySendJson(['success' => false, 'message' => $message], $status);
    exit;
}

function courseProgressEnrollmentExists(PDO $pdo, int $userId, int $courseId): bool {
    $statement = $pdo->prepare('SELECT 1 FROM user_course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $statement->execute([$userId, $courseId]);
    return (bool)$statement->fetchColumn();
}

function courseProgressIsUnlocked(PDO $pdo, int $userId, array $item): bool {
    if ((int)($item['sequential_learning'] ?? 0) !== 1) {
        return true;
    }
    $statement = $pdo->prepare('SELECT ci.id FROM course_items ci JOIN course_modules cm ON cm.id = ci.module_id WHERE cm.course_id = ? ORDER BY cm.sort_order ASC, cm.id ASC, ci.sort_order ASC, ci.id ASC');
    $statement->execute([(int)$item['course_id']]);
    $itemIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    $position = array_search((int)$item['id'], $itemIds, true);
    if ($position === false || $position === 0) {
        return true;
    }
    $previous = array_slice($itemIds, 0, $position);
    $placeholders = implode(',', array_fill(0, count($previous), '?'));
    $statement = $pdo->prepare("SELECT COUNT(*) FROM user_course_progress WHERE user_id = ? AND status = 'completed' AND item_id IN ($placeholders)");
    $statement->execute(array_merge([$userId], $previous));
    return (int)$statement->fetchColumn() === count($previous);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    courseProgressFail('Ta operacja wymaga żądania POST.', 405);
}
if (!isLoggedIn()) {
    courseProgressFail('Musisz być zalogowany, aby zapisywać postęp.', 401);
}
if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''), 'course_progress')) {
    securityAudit('csrf_failed', ['action' => 'course_progress'], 'warning');
    courseProgressFail('Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.', 403);
}

$userId = (int)$_SESSION['user_id'];
$action = (string)($_POST['action'] ?? '');
securityThrottle('course_progress:' . $userId . ':' . securityClientIp(), 90, 60, ['success' => false, 'message' => 'Zbyt wiele zapisów postępu. Spróbuj ponownie za chwilę.']);

try {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $item = courseFetchItem($pdo, $itemId);
    if (!$item || !courseIsPubliclyAvailable($item)) {
        courseProgressFail('Lekcja nie jest obecnie dostępna.', 404);
    }
    $courseId = (int)$item['course_id'];
    if (!courseProgressEnrollmentExists($pdo, $userId, $courseId)) {
        courseProgressFail('Nie jesteś zapisany na ten kurs.', 403);
    }
    if (!courseProgressIsUnlocked($pdo, $userId, $item)) {
        courseProgressFail('Najpierw ukończ poprzednie lekcje.', 403);
    }

    if ($action === 'mark_completed') {
        if (in_array((string)$item['type'], ['quiz', 'exam'], true)) {
            courseProgressFail('Quiz i egzamin można ukończyć wyłącznie przez zaliczenie.');
        }
        $pdo->beginTransaction();
        $statement = $pdo->prepare("INSERT INTO user_course_progress (user_id, course_id, item_id, status, completed_at) VALUES (?, ?, ?, 'completed', NOW()) ON DUPLICATE KEY UPDATE status = 'completed', completed_at = COALESCE(completed_at, NOW())");
        $statement->execute([$userId, $courseId, $itemId]);
        $progress = courseRecalculateEnrollmentProgress($pdo, $userId, $courseId);
        if ($progress >= 100) {
            courseIssueCertificate($pdo, $userId, $courseId);
        }
        $pdo->commit();
        securitySendJson(['success' => true, 'message' => 'Lekcja została oznaczona jako ukończona.', 'progress_percent' => $progress]);
    }

    if ($action !== 'submit_quiz' || !in_array((string)$item['type'], ['quiz', 'exam'], true)) {
        courseProgressFail('Nieznana operacja.', 400);
    }
    securityThrottle('course_quiz:' . $userId . ':' . $itemId, 12, 300, ['success' => false, 'message' => 'Zbyt wiele prób quizu. Odczekaj chwilę przed kolejną próbą.']);
    $answers = $_POST['answers'] ?? [];
    if (!is_array($answers) || count($answers) > 100) {
        courseProgressFail('Nieprawidłowe odpowiedzi quizu.');
    }
    $statement = $pdo->prepare('SELECT id, correct_answer FROM course_quiz_questions WHERE item_id = ? ORDER BY id ASC');
    $statement->execute([$itemId]);
    $questions = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (!$questions) {
        courseProgressFail('Ten quiz nie zawiera jeszcze pytań.');
    }
    $correctCount = 0;
    foreach ($questions as $question) {
        $questionId = (int)$question['id'];
        $answer = strtoupper(trim((string)($answers[$questionId] ?? '')));
        if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
            courseProgressFail('Odpowiedz na wszystkie pytania quizu.');
        }
        if (hash_equals((string)$question['correct_answer'], $answer)) {
            $correctCount++;
        }
    }
    $totalCount = count($questions);
    $score = (int)round(($correctCount / $totalCount) * 100);
    $passed = $score >= (int)$item['quiz_passing_score'];

    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT status, quiz_score, quiz_attempts FROM user_course_progress WHERE user_id = ? AND item_id = ? FOR UPDATE');
    $statement->execute([$userId, $itemId]);
    $existing = $statement->fetch(PDO::FETCH_ASSOC);
    $attempts = min(2147483647, (int)($existing['quiz_attempts'] ?? 0) + 1);
    $bestScore = max((int)($existing['quiz_score'] ?? 0), $score);
    $completed = $passed || (($existing['status'] ?? '') === 'completed');
    $status = $completed ? 'completed' : 'started';
    $statement = $pdo->prepare("INSERT INTO user_course_progress (user_id, course_id, item_id, status, quiz_score, quiz_attempts, completed_at) VALUES (?, ?, ?, ?, ?, ?, CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END) ON DUPLICATE KEY UPDATE status = VALUES(status), quiz_score = GREATEST(COALESCE(quiz_score, 0), VALUES(quiz_score)), quiz_attempts = VALUES(quiz_attempts), completed_at = CASE WHEN VALUES(status) = 'completed' THEN COALESCE(completed_at, NOW()) ELSE completed_at END");
    $statement->execute([$userId, $courseId, $itemId, $status, $bestScore, $attempts, $status]);
    $progress = courseRecalculateEnrollmentProgress($pdo, $userId, $courseId);
    if ($progress >= 100) {
        courseIssueCertificate($pdo, $userId, $courseId);
    }
    $pdo->commit();
    securitySendJson(['success' => true, 'passed' => $passed, 'score_percent' => $score, 'passing_score' => (int)$item['quiz_passing_score'], 'correct_count' => $correctCount, 'total_count' => $totalCount, 'attempts' => $attempts, 'progress_percent' => $progress]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Course progress request failed: ' . $error->getMessage());
    courseProgressFail('Nie udało się zapisać postępu. Spróbuj ponownie.', 500);
}
