<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if (!isLoggedIn()) {
    echo securityJsonEncode(['success' => false, 'message' => 'Niezalogowany użytkownik.']);
    exit;
}

$role = $_SESSION['role'] ?? 'user';
if (!in_array($role, ['admin', 'dyrektor', 'teacher'], true)) {
    echo securityJsonEncode(['success' => false, 'message' => 'Brak uprawnień.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !in_array($action, ['get_custom_labs', 'get_module_items', 'get_modules', 'get_questions'], true)) {
    if (!validateCsrfToken($token, 'manage_courses')) {
        echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowy token CSRF.']);
        exit;
    }
}

$userId = (int)$_SESSION['user_id'];

try {
    switch ($action) {
        case 'add_module':
            $courseId = (int)($_POST['course_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            if ($courseId <= 0 || $title === '') {
                echo securityJsonEncode(['success' => false, 'message' => 'Tytuł jest wymagany.']);
                exit;
            }

            // Get next sort order
            $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM course_modules WHERE course_id = ?");
            $orderStmt->execute([$courseId]);
            $sortOrder = (int)$orderStmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO course_modules (course_id, title, description, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$courseId, $title, $desc, $sortOrder]);

            echo securityJsonEncode(['success' => true, 'message' => 'Moduł został dodany.', 'module_id' => $pdo->lastInsertId()]);
            break;

        case 'edit_module':
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            if ($moduleId <= 0 || $title === '') {
                echo securityJsonEncode(['success' => false, 'message' => 'Tytuł jest wymagany.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE course_modules SET title = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $desc, $moduleId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Moduł zaktualizowany.']);
            break;

        case 'delete_module':
            $moduleId = (int)($_POST['module_id'] ?? 0);
            if ($moduleId <= 0) {
                echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowy moduł.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM course_modules WHERE id = ?");
            $stmt->execute([$moduleId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Moduł usunięty.']);
            break;

        case 'reorder_modules':
            $modules = $_POST['modules'] ?? []; // Array of module IDs in new order
            if (!is_array($modules)) {
                echo securityJsonEncode(['success' => false, 'message' => 'Błędne dane.']);
                exit;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE course_modules SET sort_order = ? WHERE id = ?");
            foreach ($modules as $index => $id) {
                $stmt->execute([$index, (int)$id]);
            }
            $pdo->commit();

            echo securityJsonEncode(['success' => true, 'message' => 'Kolejność modułów zaktualizowana.']);
            break;

        case 'add_item':
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $type = $_POST['type'] ?? '';
            $content = $_POST['content'] ?? '';
            $videoUrl = trim((string)($_POST['video_url'] ?? ''));
            $quizPassingScore = max(0, min(100, (int)($_POST['quiz_passing_score'] ?? 70)));
            $labSource = $_POST['lab_source'] ?? 'sandbox';
            $labToolKey = $_POST['lab_tool_key'] ?? '';
            $labCustomId = !empty($_POST['lab_custom_id']) ? (int)$_POST['lab_custom_id'] : null;
            $labInstructions = $_POST['lab_instructions'] ?? '';

            if ($moduleId <= 0 || $title === '' || !in_array($type, ['text', 'video', 'quiz', 'lab', 'exam'], true)) {
                echo securityJsonEncode(['success' => false, 'message' => 'Tytuł i poprawny typ są wymagane.']);
                exit;
            }

            $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM course_items WHERE module_id = ?");
            $orderStmt->execute([$moduleId]);
            $sortOrder = (int)$orderStmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO course_items (module_id, title, type, content, video_url, quiz_passing_score, lab_source, lab_tool_key, lab_custom_id, lab_instructions, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$moduleId, $title, $type, $content, $videoUrl, $quizPassingScore, $labSource, $labToolKey, $labCustomId, $labInstructions, $sortOrder]);

            echo securityJsonEncode(['success' => true, 'message' => 'Element dodany.', 'item_id' => $pdo->lastInsertId()]);
            break;

        case 'edit_item':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $content = $_POST['content'] ?? '';
            $videoUrl = trim((string)($_POST['video_url'] ?? ''));
            $quizPassingScore = max(0, min(100, (int)($_POST['quiz_passing_score'] ?? 70)));
            $labSource = $_POST['lab_source'] ?? 'sandbox';
            $labToolKey = $_POST['lab_tool_key'] ?? '';
            $labCustomId = !empty($_POST['lab_custom_id']) ? (int)$_POST['lab_custom_id'] : null;
            $labInstructions = $_POST['lab_instructions'] ?? '';

            if ($itemId <= 0 || $title === '') {
                echo securityJsonEncode(['success' => false, 'message' => 'Tytuł jest wymagany.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE course_items SET title = ?, content = ?, video_url = ?, quiz_passing_score = ?, lab_source = ?, lab_tool_key = ?, lab_custom_id = ?, lab_instructions = ? WHERE id = ?");
            $stmt->execute([$title, $content, $videoUrl, $quizPassingScore, $labSource, $labToolKey, $labCustomId, $labInstructions, $itemId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Lekcja zaktualizowana.']);
            break;

        case 'delete_item':
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId <= 0) {
                echo securityJsonEncode(['success' => false, 'message' => 'Błędne ID.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM course_items WHERE id = ?");
            $stmt->execute([$itemId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Lekcja usunięta.']);
            break;

        case 'reorder_items':
            $items = $_POST['items'] ?? [];
            if (!is_array($items)) {
                echo securityJsonEncode(['success' => false, 'message' => 'Błędne dane.']);
                exit;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE course_items SET sort_order = ? WHERE id = ?");
            foreach ($items as $index => $id) {
                $stmt->execute([$index, (int)$id]);
            }
            $pdo->commit();

            echo securityJsonEncode(['success' => true, 'message' => 'Kolejność lekcji zaktualizowana.']);
            break;

        case 'add_question':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $qText = trim((string)($_POST['question_text'] ?? ''));
            $optA = trim((string)($_POST['option_a'] ?? ''));
            $optB = trim((string)($_POST['option_b'] ?? ''));
            $optC = trim((string)($_POST['option_c'] ?? ''));
            $optD = trim((string)($_POST['option_d'] ?? ''));
            $correct = $_POST['correct_answer'] ?? 'A';
            $explanation = trim((string)($_POST['explanation'] ?? ''));

            if ($itemId <= 0 || $qText === '' || $optA === '' || $optB === '') {
                echo securityJsonEncode(['success' => false, 'message' => 'Treść pytania oraz opcje A i B są wymagane.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO course_quiz_questions (item_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $qText, $optA, $optB, $optC !== '' ? $optC : null, $optD !== '' ? $optD : null, $correct, $explanation !== '' ? $explanation : null]);

            echo securityJsonEncode(['success' => true, 'message' => 'Pytanie dodane.', 'question_id' => $pdo->lastInsertId()]);
            break;

        case 'edit_question':
            $qId = (int)($_POST['question_id'] ?? 0);
            $qText = trim((string)($_POST['question_text'] ?? ''));
            $optA = trim((string)($_POST['option_a'] ?? ''));
            $optB = trim((string)($_POST['option_b'] ?? ''));
            $optC = trim((string)($_POST['option_c'] ?? ''));
            $optD = trim((string)($_POST['option_d'] ?? ''));
            $correct = $_POST['correct_answer'] ?? 'A';
            $explanation = trim((string)($_POST['explanation'] ?? ''));

            if ($qId <= 0 || $qText === '' || $optA === '' || $optB === '') {
                echo securityJsonEncode(['success' => false, 'message' => 'Treść pytania oraz opcje A i B są wymagane.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE course_quiz_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, explanation = ? WHERE id = ?");
            $stmt->execute([$qText, $optA, $optB, $optC !== '' ? $optC : null, $optD !== '' ? $optD : null, $correct, $explanation !== '' ? $explanation : null, $qId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Pytanie zaktualizowane.']);
            break;

        case 'delete_question':
            $qId = (int)($_POST['question_id'] ?? 0);
            if ($qId <= 0) {
                echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowe ID.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM course_quiz_questions WHERE id = ?");
            $stmt->execute([$qId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Pytanie usunięte.']);
            break;

        case 'save_custom_lab':
            $title = trim((string)($_POST['title'] ?? ''));
            $toolKey = $_POST['tool_key'] ?? '';
            $instructions = trim((string)($_POST['instructions'] ?? ''));
            $isPrivate = isset($_POST['is_private']) ? 1 : 0;

            if ($title === '' || $toolKey === '' || $instructions === '') {
                echo securityJsonEncode(['success' => false, 'message' => 'Tytuł, narzędzie oraz instrukcja są wymagane.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO course_custom_labs (teacher_id, title, tool_key, instructions, is_private) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $toolKey, $instructions, $isPrivate]);

            echo securityJsonEncode(['success' => true, 'message' => 'Laboratorium zostało zapisane w repozytorium.', 'lab_id' => $pdo->lastInsertId()]);
            break;

        case 'get_custom_labs':
            // Fetch both public/shared custom labs and the ones private to this teacher
            $stmt = $pdo->prepare("SELECT id, title, tool_key, is_private FROM course_custom_labs WHERE teacher_id = ? OR is_private = 0 ORDER BY title ASC");
            $stmt->execute([$userId]);
            $labs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo securityJsonEncode(['success' => true, 'labs' => $labs]);
            exit;

        case 'delete_custom_lab':
            $labId = (int)($_POST['lab_id'] ?? 0);
            if ($labId <= 0) {
                echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowe ID labu.']);
                exit;
            }

            // Verify owner
            $checkStmt = $pdo->prepare("SELECT teacher_id FROM course_custom_labs WHERE id = ?");
            $checkStmt->execute([$labId]);
            $ownerId = (int)$checkStmt->fetchColumn();

            if ($ownerId !== $userId && $role !== 'admin') {
                echo securityJsonEncode(['success' => false, 'message' => 'Brak uprawnień do usunięcia tego szablonu.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM course_custom_labs WHERE id = ?");
            $stmt->execute([$labId]);

            echo securityJsonEncode(['success' => true, 'message' => 'Szablon labu został usunięty.']);
            break;

        case 'get_questions':
            $itemId = (int)($_GET['item_id'] ?? 0);
            if ($itemId <= 0) {
                echo securityJsonEncode(['success' => false, 'message' => 'Nieprawidłowe ID lekcji.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM course_quiz_questions WHERE item_id = ? ORDER BY id ASC");
            $stmt->execute([$itemId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo securityJsonEncode(['success' => true, 'questions' => $questions]);
            exit;

        default:
            echo securityJsonEncode(['success' => false, 'message' => 'Nieznana akcja.']);
            break;
    }
} catch (Exception $e) {
    error_log("AJAX admin courses failed: " . $e->getMessage());
    echo securityJsonEncode(['success' => false, 'message' => 'Wystąpił błąd serwera. Spróbuj ponownie.']);
}
