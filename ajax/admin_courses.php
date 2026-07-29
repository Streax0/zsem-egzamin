<?php
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/CourseService.php';

startSecureSession();
securityApplyJsonHeaders();

function courseAdminFail(string $message, int $status = 422): never {
    securitySendJson(['success' => false, 'message' => $message], $status);
}

function courseAdminOrder(string $raw): array {
    try {
        $values = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        courseAdminFail('Nieprawidłowy format kolejności.');
    }
    if (!is_array($values) || count($values) > 250) {
        courseAdminFail('Nieprawidłowa kolejność elementów.');
    }
    $ids = [];
    foreach ($values as $value) {
        if (!(is_int($value) || (is_string($value) && ctype_digit($value))) || (int)$value <= 0) {
            courseAdminFail('Nieprawidłowy identyfikator w kolejności.');
        }
        $ids[] = (int)$value;
    }
    if (count($ids) !== count(array_unique($ids))) {
        courseAdminFail('Kolejność zawiera powtórzone elementy.');
    }
    return $ids;
}

function courseAdminAssertExactOrder(array $submitted, array $existing): void {
    sort($submitted, SORT_NUMERIC);
    sort($existing, SORT_NUMERIC);
    if ($submitted !== $existing) {
        courseAdminFail('Można sortować wyłącznie elementy z aktualnie wybranego modułu lub kursu.', 403);
    }
}

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string)($_REQUEST['action'] ?? '');

if ($requestMethod !== 'POST' && !($requestMethod === 'GET' && $action === 'get_questions')) {
    courseAdminFail('Ta operacja wymaga żądania POST lub jest to nieznana akcja GET.', 405);
}
if (!isLoggedIn()) {
    courseAdminFail('Musisz być zalogowany.', 401);
}
if (!roleHasAdminAccess((string)($_SESSION['role'] ?? 'user'))) {
    courseAdminFail('Brak uprawnień do zarządzania kursami.', 403);
}
if (function_exists('mfaAccessRequired') && mfaAccessRequired()) {
    courseAdminFail('Potwierdź uwierzytelnianie wieloskładnikowe przed zmianą kursu.', 403);
}
if ($requestMethod === 'POST' && !validateCsrfToken((string)($_POST['csrf_token'] ?? ''), 'course_admin')) {
    securityAudit('csrf_failed', ['action' => 'course_admin'], 'warning');
    courseAdminFail('Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.', 403);
}

$userId = (int)$_SESSION['user_id'];
securityThrottle('course_admin:' . $userId . ':' . securityClientIp(), 180, 60, ['success' => false, 'message' => 'Zbyt wiele zmian naraz. Spróbuj ponownie za chwilę.']);

try {
    switch ($action) {
        case 'add_module': {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = courseText((string)($_POST['title'] ?? ''), 160, false);
            $description = courseText((string)($_POST['description'] ?? ''), 5000);
            if ($title === '' || !courseFetchById($pdo, $courseId)) {
                courseAdminFail('Wybierz istniejący kurs i podaj nazwę modułu.');
            }
            $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM course_modules WHERE course_id = ?');
            $next->execute([$courseId]);
            $statement = $pdo->prepare('INSERT INTO course_modules (course_id, title, description, sort_order) VALUES (?, ?, ?, ?)');
            $statement->execute([$courseId, $title, $description !== '' ? $description : null, (int)$next->fetchColumn()]);
            securityAudit('course_module_created', ['course_id' => $courseId, 'module_id' => (int)$pdo->lastInsertId(), 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Moduł został dodany.', 'module_id' => (int)$pdo->lastInsertId()]);
            break;
        }

        case 'edit_module': {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $module = courseFetchModule($pdo, $moduleId);
            $title = courseText((string)($_POST['title'] ?? ''), 160, false);
            $description = courseText((string)($_POST['description'] ?? ''), 5000);
            if (!$module || $title === '') {
                courseAdminFail('Podaj nazwę istniejącego modułu.');
            }
            $statement = $pdo->prepare('UPDATE course_modules SET title = ?, description = ? WHERE id = ?');
            $statement->execute([$title, $description !== '' ? $description : null, $moduleId]);
            securityAudit('course_module_updated', ['course_id' => (int)$module['course_id'], 'module_id' => $moduleId, 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Moduł został zapisany.']);
            break;
        }

        case 'delete_module': {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $module = courseFetchModule($pdo, $moduleId);
            if (!$module) {
                courseAdminFail('Moduł nie istnieje.', 404);
            }
            $statement = $pdo->prepare('DELETE FROM course_modules WHERE id = ?');
            $statement->execute([$moduleId]);
            securityAudit('course_module_deleted', ['course_id' => (int)$module['course_id'], 'module_id' => $moduleId, 'user_id' => $userId], 'warning');
            securitySendJson(['success' => true, 'message' => 'Moduł wraz z lekcjami został usunięty.']);
            break;
        }

        case 'reorder_modules': {
            $courseId = (int)($_POST['course_id'] ?? 0);
            if (!courseFetchById($pdo, $courseId)) {
                courseAdminFail('Kurs nie istnieje.', 404);
            }
            $order = courseAdminOrder((string)($_POST['order'] ?? ''));
            $statement = $pdo->prepare('SELECT id FROM course_modules WHERE course_id = ? ORDER BY id');
            $statement->execute([$courseId]);
            courseAdminAssertExactOrder($order, array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)));
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE course_modules SET sort_order = ? WHERE id = ? AND course_id = ?');
            foreach ($order as $position => $moduleId) {
                $update->execute([$position, $moduleId, $courseId]);
            }
            $pdo->commit();
            securityAudit('course_modules_reordered', ['course_id' => $courseId, 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Kolejność modułów została zapisana.']);
            break;
        }

        case 'add_item': {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $module = courseFetchModule($pdo, $moduleId);
            $title = courseText((string)($_POST['title'] ?? ''), 160, false);
            $type = (string)($_POST['type'] ?? '');
            if (!$module || $title === '' || !in_array($type, COURSE_ITEM_TYPES, true)) {
                courseAdminFail('Podaj tytuł i poprawny typ lekcji.');
            }
            $next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM course_items WHERE module_id = ?');
            $next->execute([$moduleId]);
            $content = $type === 'text' ? courseEncodeLessonDocument(courseDefaultLessonDocument()) : null;
            $statement = $pdo->prepare('INSERT INTO course_items (module_id, title, type, content, quiz_passing_score, lab_source, sort_order) VALUES (?, ?, ?, ?, 70, \'sandbox\', ?)');
            $statement->execute([$moduleId, $title, $type, $content, (int)$next->fetchColumn()]);
            $itemId = (int)$pdo->lastInsertId();
            securityAudit('course_item_created', ['course_id' => (int)$module['course_id'], 'module_id' => $moduleId, 'item_id' => $itemId, 'type' => $type, 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Lekcja została dodana.', 'item_id' => $itemId]);
            break;
        }

        case 'edit_item': {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $item = courseFetchItem($pdo, $itemId);
            $title = courseText((string)($_POST['title'] ?? ''), 160, false);
            if (!$item || $title === '') {
                courseAdminFail('Lekcja nie istnieje albo nie ma tytułu.');
            }
            $videoUrl = trim((string)($_POST['video_url'] ?? ''));
            if ($item['type'] === 'video' && $videoUrl !== '' && courseYoutubeEmbedUrl($videoUrl) === null) {
                courseAdminFail('Podaj prawidłowy adres filmu YouTube.');
            }
            if ($item['type'] !== 'video') {
                $videoUrl = '';
            }
            $passingScore = max(1, min(100, (int)($_POST['quiz_passing_score'] ?? 70)));
            $labTool = (string)($_POST['lab_tool_key'] ?? '');
            $labInstructions = courseText((string)($_POST['lab_instructions'] ?? ''), 10000);
            if ($item['type'] === 'lab' && !in_array($labTool, COURSE_LAB_TOOLS, true)) {
                courseAdminFail('Wybierz prawidłowe narzędzie laboratorium.');
            }
            if ($item['type'] !== 'lab') {
                $labTool = '';
                $labInstructions = '';
            }
            if ($item['type'] === 'text') {
                $rawDocument = (string)($_POST['content_document'] ?? '');
                $document = courseDecodeLessonDocument($rawDocument);
                if ($document === null) {
                    courseAdminFail('Treść lekcji ma nieprawidłowy format.');
                }
                $content = courseEncodeLessonDocument($document);
                $statement = $pdo->prepare('UPDATE course_items SET title = ?, content = ? WHERE id = ?');
                $statement->execute([$title, $content, $itemId]);
            } else {
                $statement = $pdo->prepare('UPDATE course_items SET title = ?, video_url = ?, quiz_passing_score = ?, lab_source = \'sandbox\', lab_tool_key = ?, lab_custom_id = NULL, lab_instructions = ? WHERE id = ?');
                $statement->execute([$title, $videoUrl !== '' ? $videoUrl : null, $passingScore, $labTool !== '' ? $labTool : null, $labInstructions !== '' ? $labInstructions : null, $itemId]);
            }
            securityAudit('course_item_updated', ['course_id' => (int)$item['course_id'], 'item_id' => $itemId, 'type' => (string)$item['type'], 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Lekcja została zapisana.']);
            break;
        }

        case 'delete_item': {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $item = courseFetchItem($pdo, $itemId);
            if (!$item) {
                courseAdminFail('Lekcja nie istnieje.', 404);
            }
            $statement = $pdo->prepare('DELETE FROM course_items WHERE id = ?');
            $statement->execute([$itemId]);
            securityAudit('course_item_deleted', ['course_id' => (int)$item['course_id'], 'item_id' => $itemId, 'user_id' => $userId], 'warning');
            securitySendJson(['success' => true, 'message' => 'Lekcja została usunięta.']);
            break;
        }

        case 'reorder_items': {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $module = courseFetchModule($pdo, $moduleId);
            if (!$module) {
                courseAdminFail('Moduł nie istnieje.', 404);
            }
            $order = courseAdminOrder((string)($_POST['order'] ?? ''));
            $statement = $pdo->prepare('SELECT id FROM course_items WHERE module_id = ? ORDER BY id');
            $statement->execute([$moduleId]);
            courseAdminAssertExactOrder($order, array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)));
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE course_items SET sort_order = ? WHERE id = ? AND module_id = ?');
            foreach ($order as $position => $itemId) {
                $update->execute([$position, $itemId, $moduleId]);
            }
            $pdo->commit();
            securityAudit('course_items_reordered', ['course_id' => (int)$module['course_id'], 'module_id' => $moduleId, 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Kolejność lekcji została zapisana.']);
            break;
        }

        case 'add_question':
        case 'edit_question': {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($action === 'edit_question' && $questionId > 0) {
                $statement = $pdo->prepare('SELECT q.item_id, cm.course_id, ci.type FROM course_quiz_questions q JOIN course_items ci ON ci.id = q.item_id JOIN course_modules cm ON cm.id = ci.module_id WHERE q.id = ? LIMIT 1');
                $statement->execute([$questionId]);
                $questionOwner = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$questionOwner) {
                    courseAdminFail('Pytanie nie istnieje.', 404);
                }
                $itemId = (int)$questionOwner['item_id'];
                $item = ['id' => $itemId, 'course_id' => (int)$questionOwner['course_id'], 'type' => (string)$questionOwner['type']];
            } else {
                $item = courseFetchItem($pdo, $itemId);
            }
            if (!$item || !in_array((string)$item['type'], ['quiz', 'exam'], true)) {
                courseAdminFail('Pytania można dodawać wyłącznie do quizów i egzaminów.');
            }
            $question = courseText((string)($_POST['question_text'] ?? ''), 5000);
            $options = [];
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $options[$letter] = courseText((string)($_POST['option_' . $letter] ?? ''), 255, false);
            }
            $correct = strtoupper((string)($_POST['correct_answer'] ?? ''));
            $explanation = courseText((string)($_POST['explanation'] ?? ''), 5000);
            if ($question === '' || $options['a'] === '' || $options['b'] === '' || !in_array($correct, ['A', 'B', 'C', 'D'], true) || $options[strtolower($correct)] === '') {
                courseAdminFail('Uzupełnij pytanie, opcje A i B oraz wskaż istniejącą poprawną odpowiedź.');
            }
            if ($action === 'add_question') {
                $statement = $pdo->prepare('INSERT INTO course_quiz_questions (item_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([$itemId, $question, $options['a'], $options['b'], $options['c'] !== '' ? $options['c'] : null, $options['d'] !== '' ? $options['d'] : null, $correct, $explanation !== '' ? $explanation : null]);
                $questionId = (int)$pdo->lastInsertId();
            } else {
                $statement = $pdo->prepare('UPDATE course_quiz_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, explanation = ? WHERE id = ? AND item_id = ?');
                $statement->execute([$question, $options['a'], $options['b'], $options['c'] !== '' ? $options['c'] : null, $options['d'] !== '' ? $options['d'] : null, $correct, $explanation !== '' ? $explanation : null, $questionId, $itemId]);
            }
            securityAudit('course_question_saved', ['course_id' => (int)$item['course_id'], 'item_id' => $itemId, 'question_id' => $questionId, 'user_id' => $userId]);
            securitySendJson(['success' => true, 'message' => 'Pytanie zostało zapisane.', 'question_id' => $questionId]);
            break;
        }

        case 'delete_question': {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $statement = $pdo->prepare('SELECT q.item_id, cm.course_id FROM course_quiz_questions q JOIN course_items ci ON ci.id = q.item_id JOIN course_modules cm ON cm.id = ci.module_id WHERE q.id = ? LIMIT 1');
            $statement->execute([$questionId]);
            $question = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$question) {
                courseAdminFail('Pytanie nie istnieje.', 404);
            }
            $statement = $pdo->prepare('DELETE FROM course_quiz_questions WHERE id = ?');
            $statement->execute([$questionId]);
            securityAudit('course_question_deleted', ['course_id' => (int)$question['course_id'], 'item_id' => (int)$question['item_id'], 'question_id' => $questionId, 'user_id' => $userId], 'warning');
            securitySendJson(['success' => true, 'message' => 'Pytanie zostało usunięte.']);
            break;
        }

        case 'get_questions': {
            $itemId = (int)($_GET['item_id'] ?? 0);
            $item = courseFetchItem($pdo, $itemId);
            if (!$item || !in_array((string)$item['type'], ['quiz', 'exam'], true)) {
                courseAdminFail('Lekcja nie istnieje lub nie jest quizem.', 404);
            }
            $statement = $pdo->prepare('SELECT id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation FROM course_quiz_questions WHERE item_id = ? ORDER BY id ASC');
            $statement->execute([$itemId]);
            $questions = $statement->fetchAll(PDO::FETCH_ASSOC);
            securitySendJson(['success' => true, 'questions' => $questions]);
            break;
        }

        default:
            courseAdminFail('Nieznana operacja.', 400);
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Course administration request failed: ' . $error->getMessage());
    courseAdminFail('Nie udało się zapisać zmiany. Spróbuj ponownie.', 500);
}
