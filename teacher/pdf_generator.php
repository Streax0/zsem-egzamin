<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'], true)) {
    setSessionMessage('error', 'Brak uprawnień do generatora sprawdzianów.');
    redirect('../index.php');
}

function worksheetCleanList($value): array {
    if (!is_array($value)) {
        $value = $value === null || $value === '' ? [] : [$value];
    }
    return array_values(array_filter(array_map(static fn($item) => trim((string)$item), $value), static fn($item) => $item !== ''));
}

function worksheetCorrectAnswer($value): string {
    $answer = strtoupper(substr(trim((string)$value), 0, 1));
    return in_array($answer, ['A', 'B', 'C', 'D'], true) ? $answer : '';
}

function parseWorksheetTxtQuestions(string $text, int &$errors = 0): array {
    $rows = [];
    $errors = 0;
    $lines = preg_split('/\R/u', $text) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = str_getcsv($line, ';');
        $parts = array_map(static fn($value) => trim((string)$value), $parts);
        $count = count($parts);

        if ($count >= 7) {
            $question = $parts[1] ?? '';
            if ($question === '') {
                $errors++;
                continue;
            }

            $rows[] = [
                'id' => 'txt_' . count($rows),
                'source' => 'txt',
                'category' => $parts[0] !== '' ? $parts[0] : 'TXT',
                'question_text' => $question,
                'option_a' => $parts[2] ?? '',
                'option_b' => $parts[3] ?? '',
                'option_c' => $parts[4] ?? '',
                'option_d' => $parts[5] ?? '',
                'correct_answer' => worksheetCorrectAnswer($parts[6] ?? ''),
                'image_url' => $parts[7] ?? '',
                'explanation' => $parts[8] ?? '',
            ];
            continue;
        }

        if ($count === 6) {
            $question = $parts[0] ?? '';
            if ($question === '') {
                $errors++;
                continue;
            }

            $rows[] = [
                'id' => 'txt_' . count($rows),
                'source' => 'txt',
                'category' => 'TXT',
                'question_text' => $question,
                'option_a' => $parts[1] ?? '',
                'option_b' => $parts[2] ?? '',
                'option_c' => $parts[3] ?? '',
                'option_d' => $parts[4] ?? '',
                'correct_answer' => worksheetCorrectAnswer($parts[5] ?? ''),
                'image_url' => '',
                'explanation' => '',
            ];
            continue;
        }

        if ($count >= 2) {
            if (($parts[1] ?? '') === '') {
                $errors++;
                continue;
            }
            $rows[] = [
                'id' => 'txt_' . count($rows),
                'source' => 'txt',
                'category' => $parts[0] !== '' ? $parts[0] : 'TXT',
                'question_text' => $parts[1] ?? '',
                'option_a' => '',
                'option_b' => '',
                'option_c' => '',
                'option_d' => '',
                'correct_answer' => '',
                'image_url' => '',
                'explanation' => $parts[2] ?? '',
                'open_question' => true,
            ];
            continue;
        }

        $errors++;
    }

    return $rows;
}

function worksheetQuestionIsOpen(array $question): bool {
    return !empty($question['open_question'])
        || trim((string)($question['option_a'] ?? '')) === ''
        || trim((string)($question['option_b'] ?? '')) === '';
}

function worksheetAnswerLabel(array $question): string {
    if (worksheetQuestionIsOpen($question)) {
        return 'opisowa';
    }
    $answer = worksheetCorrectAnswer($question['correct_answer'] ?? '');
    return $answer !== '' ? $answer : 'brak';
}

function worksheetGroupLabels(int $count): array {
    $count = max(1, min(10, $count));
    return array_slice(range('A', 'J'), 0, $count);
}

function worksheetBuildGroups(array $questions, int $questionCount, int $groupCount, string $groupStrategy): array {
    $labels = worksheetGroupLabels($groupCount);
    $questionCount = max(1, min(120, $questionCount));
    $groupStrategy = in_array($groupStrategy, ['same', 'rotate', 'unique'], true) ? $groupStrategy : 'unique';
    $groups = [];

    foreach ($labels as $groupIndex => $label) {
        if ($groupStrategy === 'unique') {
            $offset = $groupIndex * $questionCount;
            $slice = array_slice($questions, $offset, $questionCount);
            if (count($slice) < $questionCount && !empty($questions)) {
                $needed = $questionCount - count($slice);
                $slice = array_merge($slice, array_slice($questions, 0, $needed));
            }
        } elseif ($groupStrategy === 'rotate') {
            $pool = $questions;
            if (!empty($pool)) {
                $shift = $groupIndex % count($pool);
                $pool = array_merge(array_slice($pool, $shift), array_slice($pool, 0, $shift));
            }
            $slice = array_slice($pool, 0, $questionCount);
        } else {
            $slice = array_slice($questions, 0, $questionCount);
        }

        $groups[] = [
            'label' => $label,
            'questions' => $slice,
        ];
    }

    return $groups;
}

function worksheetPlainText($value, int $limit = 1200): string {
    $value = trim(strip_tags((string)$value));
    $value = preg_replace('/\s+/u', ' ', $value);
    return mb_substr($value, 0, $limit);
}

function worksheetManualQuestionsFromPost(array $rows): array {
    $questions = [];
    foreach ($rows as $row) {
        $text = worksheetPlainText($row['question_text'] ?? '', 1400);
        if ($text === '') {
            continue;
        }
        $type = ($row['type'] ?? 'closed') === 'open' ? 'open' : 'closed';
        $question = [
            'id' => 'manual_' . count($questions),
            'source' => 'manual',
            'category' => worksheetPlainText($row['category'] ?? 'Własne', 80) ?: 'Własne',
            'question_text' => $text,
            'option_a' => worksheetPlainText($row['option_a'] ?? '', 600),
            'option_b' => worksheetPlainText($row['option_b'] ?? '', 600),
            'option_c' => worksheetPlainText($row['option_c'] ?? '', 600),
            'option_d' => worksheetPlainText($row['option_d'] ?? '', 600),
            'correct_answer' => worksheetCorrectAnswer($row['correct_answer'] ?? 'A'),
            'image_url' => sanitizeQuestionImageUrl($row['image_url'] ?? ''),
            'explanation' => worksheetPlainText($row['explanation'] ?? '', 1600),
            'open_question' => $type === 'open',
        ];
        if ($type === 'closed' && count(array_filter([$question['option_a'], $question['option_b'], $question['option_c'], $question['option_d']])) < 4) {
            continue;
        }
        $questions[] = $question;
        if (count($questions) >= 120) {
            break;
        }
    }
    return $questions;
}

function worksheetPreviewQuestionsFromPayload(string $payload): array {
    $json = base64_decode($payload, true);
    if ($json === false || strlen($json) > 500000) {
        return [];
    }
    $rows = json_decode($json, true);
    return is_array($rows) ? array_slice($rows, 0, 120) : [];
}

function worksheetSavePreviewAsCustomExam(array $questions, int $teacherId, string $title, string $description, string $difficulty): bool {
    if ($teacherId <= 0 || empty($questions)) {
        return false;
    }

    $customQuestions = [];
    foreach ($questions as $question) {
        $text = worksheetPlainText($question['question_text'] ?? ($question['text'] ?? ''), 1400);
        if ($text === '') {
            continue;
        }
        $isOpen = worksheetQuestionIsOpen($question);
        $customQuestions[] = [
            'text' => $text,
            'a' => $isOpen ? 'Odpowiedź opisowa' : worksheetPlainText($question['option_a'] ?? ($question['a'] ?? ''), 600),
            'b' => $isOpen ? 'Do oceny nauczyciela' : worksheetPlainText($question['option_b'] ?? ($question['b'] ?? ''), 600),
            'c' => $isOpen ? 'Nie dotyczy' : worksheetPlainText($question['option_c'] ?? ($question['c'] ?? ''), 600),
            'd' => $isOpen ? 'Nie dotyczy' : worksheetPlainText($question['option_d'] ?? ($question['d'] ?? ''), 600),
            'correct' => worksheetCorrectAnswer($question['correct_answer'] ?? ($question['correct'] ?? 'A')) ?: 'A',
            'image' => sanitizeQuestionImageUrl($question['image_url'] ?? ($question['image'] ?? '')),
            'explanation' => worksheetPlainText($question['explanation'] ?? '', 1600),
            'open_question' => $isOpen,
        ];
    }
    if (!$customQuestions) {
        return false;
    }

    $customDir = __DIR__ . '/../data/custom_tests';
    if (!is_dir($customDir)) {
        mkdir($customDir, 0755, true);
    }
    $safeTitle = worksheetPlainText($title, 120) ?: 'Sprawdzian do druku';
    $slug = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($safeTitle, 'UTF-8'));
    $slug = trim($slug, '_') ?: 'sprawdzian';
    $filename = $teacherId . '_' . $slug . '_print_' . time() . '.json';
    $payload = [
        'teacher_id' => $teacherId,
        'title' => $safeTitle,
        'description' => worksheetPlainText($description, 500),
        'time_limit' => 45,
        'pass_threshold' => 50,
        'difficulty' => in_array($difficulty, ['easy','medium','hard','mixed'], true) ? $difficulty : 'mixed',
        'shuffle_questions' => true,
        'shuffle_answers' => false,
        'show_answers_after' => true,
        'tags' => ['do druku', 'PDF'],
        'print_only' => true,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'questions' => $customQuestions,
    ];
    return file_put_contents($customDir . '/' . $filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$allQuestions = loadQuestions($pdo, false);
$allQuestions = array_values(array_filter($allQuestions, static fn($question) => !isInternalQuestionCategory($question['category'] ?? '')));

$categories = array_values(array_unique(array_filter(array_column($allQuestions, 'category'), static fn($category) => trim((string)$category) !== '')));
sort($categories);

$categoryCounts = [];
$questionsById = [];
foreach ($allQuestions as $question) {
    $cat = $question['category'] ?? 'Inne';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    $questionsById[(string)($question['id'] ?? '')] = $question;
}

$selectedCategories = worksheetCleanList($_POST['categories'] ?? []);
$selectedCategories = array_values(array_intersect($selectedCategories, $categories));
$questionCount = max(1, min(120, (int)($_POST['question_count'] ?? 30)));
$groupCount = max(1, min(10, (int)($_POST['group_count'] ?? 1)));
$groupStrategy = (string)($_POST['group_strategy'] ?? 'unique');
if (!in_array($groupStrategy, ['unique', 'rotate', 'same'], true)) {
    $groupStrategy = 'unique';
}
$title = trim((string)($_POST['title'] ?? 'Nowy sprawdzian'));
$description = trim((string)($_POST['description'] ?? ''));
$difficultyLevel = (string)($_POST['difficulty_level'] ?? 'mixed');
if (!in_array($difficultyLevel, ['mixed', 'easy', 'medium', 'hard'], true)) {
    $difficultyLevel = 'mixed';
}
$worksheetAction = (string)($_POST['worksheet_action'] ?? 'preview');
$generatorMode = (string)($_POST['generator_mode'] ?? 'db');
if (!in_array($generatorMode, ['db', 'txt', 'manual'], true)) {
    $generatorMode = 'db';
}
$shuffleQuestions = $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['shuffle_questions']);
$includeKey = $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['include_key']);
$shuffleAnswers = $_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST["shuffle_answers"]);
$showPoints = $_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST["show_points"]);
$showDateSpace = $_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST["show_date_space"]);
$showGradeSpace = $_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST["show_grade_space"]);

$showExplanations = isset($_POST['show_explanations']);
$selected = [];
$worksheetGroups = [];
$submitted = $_SERVER['REQUEST_METHOD'] === 'POST';
$formNotice = null;
$generationSourceLabel = 'Baza pytań';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $worksheetAction === 'save_preview') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'teacher_pdf_generator_save')) {
        setSessionMessage('error', 'Nieprawidłowe zabezpieczenie zapisu.');
        redirect('pdf_generator.php');
    }
    $payloadQuestions = worksheetPreviewQuestionsFromPayload((string)($_POST['questions_payload'] ?? ''));
    if (worksheetSavePreviewAsCustomExam($payloadQuestions, $userId, $title, $description, $difficultyLevel)) {
        setSessionMessage('success', 'Podgląd zapisano w Moich sprawdzianach.');
        redirect('custom_exams.php');
    }
    setSessionMessage('error', 'Nie udało się zapisać podglądu sprawdzianu.');
    redirect('pdf_generator.php');
}

if ($submitted) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'teacher_pdf_generator')) {
        setSessionMessage('error', 'Nieprawidłowe zabezpieczenie formularza.');
        redirect('pdf_generator.php');
    }

    if ($generatorMode === 'manual') {
        $generationSourceLabel = 'Własne pytania';
        $manualRows = $_POST['manual_questions'] ?? [];
        $selected = worksheetManualQuestionsFromPost(is_array($manualRows) ? $manualRows : []);
        if (empty($selected)) {
            $formNotice = ['type' => 'warning', 'message' => 'Dodaj przynajmniej jedno poprawne pytanie własne.'];
        }
    } elseif ($generatorMode === 'txt') {
        $generationSourceLabel = 'Plik TXT';
        $txtErrors = 0;
        $file = $_FILES['txt_file'] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $formNotice = ['type' => 'warning', 'message' => 'Wybierz plik TXT/CSV z pytaniami.'];
        } else {
            $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($extension, ['txt', 'csv'], true) || (int)($file['size'] ?? 0) > 1024 * 1024) {
                $formNotice = ['type' => 'danger', 'message' => 'Nieprawidłowy plik. Dozwolone są TXT/CSV do 1 MB.'];
            } else {
                $content = @file_get_contents($file['tmp_name']);
                if ($content === false) {
                    $formNotice = ['type' => 'danger', 'message' => 'Nie można odczytać pliku TXT.'];
                } else {
                    $txtQuestions = parseWorksheetTxtQuestions($content, $txtErrors);
                    if ($shuffleQuestions) {
                        shuffle($txtQuestions);
                    }
                    $takeCount = $groupStrategy === 'unique' ? $questionCount * $groupCount : $questionCount;
                    $selected = array_slice($txtQuestions, 0, $takeCount);
                    if ($txtErrors > 0) {
                        $formNotice = [
                            'type' => 'warning',
                            'message' => 'Część linii pominięto: ' . $txtErrors . '. Popraw format i wygeneruj ponownie, jeśli czegoś brakuje.',
                        ];
                    }
                }
            }
        }
    } else {
        $selectedIds = array_values(array_unique(array_filter(array_map(
            static fn($id) => (string)(int)$id,
            worksheetCleanList($_POST['selected_questions'] ?? [])
        ), static fn($id) => $id !== '0')));

        if (!empty($selectedIds)) {
            foreach ($selectedIds as $id) {
                if (
                    isset($questionsById[$id])
                    && (empty($selectedCategories) || in_array((string)($questionsById[$id]['category'] ?? ''), $selectedCategories, true))
                ) {
                    $selected[] = $questionsById[$id];
                }
            }
            if ($shuffleQuestions) {
                shuffle($selected);
            }
        } else {
            $pool = array_values(array_filter($allQuestions, static function ($question) use ($selectedCategories) {
                return empty($selectedCategories) || in_array((string)($question['category'] ?? ''), $selectedCategories, true);
            }));
            if ($shuffleQuestions) {
                shuffle($pool);
            }
            $takeCount = $groupStrategy === 'unique' ? $questionCount * $groupCount : $questionCount;
            $selected = array_slice($pool, 0, $takeCount);
        }
    }
}

if (!empty($selected)) {
    $worksheetGroups = worksheetBuildGroups($selected, $questionCount, $groupCount, $groupStrategy);
}

$currentUserStmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE id = ? LIMIT 1");
$currentUserStmt->execute([$userId]);
$currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC) ?: ['username' => $_SESSION['username'] ?? 'nauczyciel'];
$generatedFor = userDisplayName($currentUser);
$difficultyLabels = ['mixed' => 'Mieszany', 'easy' => 'Łatwy', 'medium' => 'Średni', 'hard' => 'Trudny'];
$questionSelectorLimit = min(260, count($allQuestions));
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generator sprawdzianów - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css', '..')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css', '..')); ?>">
    <style>
        body.pdf-generator-page { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f8fafc; }
        .generator-shell { max-width: 1480px; margin: 0 auto; }

        /* New UI Styles */
        .generator-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .generator-card-header {
            background: #f8fafc;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .generator-card-header h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .generator-card-header .icon-wrapper {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(37, 99, 235, 0.1);
            color: #2563eb;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
        }
        .generator-card-body {
            padding: 1.5rem;
        }

        .form-floating-custom {
            position: relative;
        }
        .form-floating-custom label {
            position: absolute;
            top: -0.6rem;
            left: 0.75rem;
            background: #fff;
            padding: 0 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            z-index: 5;
        }
        .form-floating-custom .form-control,
        .form-floating-custom .form-select {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            border-radius: 8px;
            border-color: #cbd5e1;
            box-shadow: none;
            transition: all 0.2s;
        }
        .form-floating-custom .form-control:focus,
        .form-floating-custom .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .toggle-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.2s;
            height: 100%;
        }
        .toggle-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .toggle-card .form-check-label {
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }

        .generator-preset-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .generator-preset {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: linear-gradient(to bottom right, #ffffff, #f8fafc);
            padding: 1rem;
            text-align: left;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .generator-preset::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: #cbd5e1;
            transition: all 0.2s ease;
        }
        .generator-preset:hover {
            transform: translateY(-2px);
            border-color: #94a3b8;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }
        .generator-preset:hover::before {
            background: #2563eb;
        }
        .generator-preset strong { display: block; font-size: 1rem; color: #1e293b; margin-bottom: 0.25rem; }
        .generator-preset span { display: block; color: #64748b; font-size: 0.8rem; line-height: 1.4; }

        .nav-pills-custom .nav-link {
            border-radius: 8px;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            color: #475569;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .nav-pills-custom .nav-link:hover {
            background: #f1f5f9;
        }
        .nav-pills-custom .nav-link.active {
            background: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }

        /* Dark mode overrides for new UI */
        body.dark-mode .generator-card {
            background: #1e293b;
            border-color: rgba(148, 163, 184, 0.1);
        }
        body.dark-mode .generator-card-header {
            background: #0f172a;
            border-color: rgba(148, 163, 184, 0.1);
        }
        body.dark-mode .generator-card-header h5 {
            color: #f8fafc;
        }
        body.dark-mode .generator-card-header .icon-wrapper {
            background: rgba(96, 165, 250, 0.15);
            color: #60a5fa;
        }
        body.dark-mode .form-floating-custom label {
            background: #1e293b;
            color: #94a3b8;
        }
        body.dark-mode .form-floating-custom .form-control,
        body.dark-mode .form-floating-custom .form-select {
            background-color: #0f172a !important;
            border-color: #334155 !important;
        }
        body.dark-mode .toggle-card {
            border-color: #334155;
            background: #0f172a;
        }
        body.dark-mode .toggle-card:hover {
            background: #1e293b;
            border-color: #475569;
        }
        body.dark-mode .toggle-card .form-check-label {
            color: #cbd5e1;
        }
        body.dark-mode .generator-preset {
            background: #0f172a;
            border-color: #334155;
        }
        body.dark-mode .generator-preset::before {
            background: #475569;
        }
        body.dark-mode .generator-preset strong { color: #f8fafc; }
        body.dark-mode .generator-preset:hover::before { background: #60a5fa; }
        body.dark-mode .nav-pills-custom { background: rgba(255, 255, 255, 0.08) !important; }
        body.dark-mode .nav-pills-custom .nav-link { color: #94a3b8; }
        body.dark-mode .nav-pills-custom .nav-link:hover { background: #1e293b; }
        body.dark-mode .nav-pills-custom .nav-link.active {
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            border-color: rgba(37, 99, 235, 0.4);
        }

        .generator-title-row {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
            margin-bottom:1.5rem;
        }
        .generator-title-main {
            display:flex;
            align-items:center;
            gap:.85rem;
        }
        .generator-title-icon {
            width:34px;
            height:34px;
            border-radius:50%;
            display:grid;
            place-items:center;
            color:#fff;
            background:linear-gradient(135deg,#667eea,#2563eb);
            box-shadow:0 10px 22px rgba(37,99,235,.20);
            flex:0 0 auto;
        }
        .generator-panel {
            border:1px solid rgba(148,163,184,.20);
            box-shadow:0 16px 40px rgba(15,23,42,.06);
        }
        .source-method-grid {
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:.75rem;
            margin-bottom:1rem;
        }
        .source-method-card {
            border:1px solid rgba(148,163,184,.24);
            border-radius:8px;
            padding:.85rem;
            background:linear-gradient(180deg,#fff,#f8fafc);
            font-weight:800;
            cursor:pointer;
        }
        .source-method-card span { display:block; color:#64748b; font-size:.78rem; font-weight:600; margin-top:.25rem; }
        .nav-link.active + .source-method-card,
        .btn-check:checked + .source-method-card {
            border-color:#2563eb;
            box-shadow:0 0 0 4px rgba(37,99,235,.1);
        }
        .config-section { border-left:3px solid var(--bs-primary); padding-left:1rem; }
        .config-section h5 { color:var(--bs-primary); }
        .generator-preset-row {
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:.75rem;
        }
        .generator-preset {
            border:1px solid #dbe4f0;
            border-radius:8px;
            background:#fff;
            padding:.75rem .9rem;
            text-align:left;
            transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .generator-preset:hover {
            transform:translateY(-1px);
            border-color:#2563eb;
            box-shadow:0 10px 24px rgba(15,23,42,.08);
        }
        .generator-preset strong { display:block; font-size:.92rem; }
        .generator-preset span { display:block; color:#64748b; font-size:.78rem; margin-top:.2rem; }
        .generator-live-estimate {
            border:1px solid #dbe4f0;
            border-radius:8px;
            background:#f8fafc;
            padding:.65rem .8rem;
            font-size:.86rem;
            color:#475569;
        }
        @media (max-width: 768px) {
            .generator-preset-row { grid-template-columns:1fr; }
        }
        .category-tools, .question-toolbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:.75rem;
            flex-wrap:wrap;
        }
        .category-search { max-width:260px; }
        .category-selector-grid { max-height:220px; overflow:auto; padding:.15rem; }
        .category-btn-wrapper label { transition:all .2s ease; }
        .category-btn-wrapper label:hover { transform:translateY(-1px); }
        .btn-check:checked + .btn-outline-primary {
            background-color:var(--bs-primary);
            color:#fff;
            box-shadow:0 4px 12px rgba(59,130,246,.28);
            transform:translateY(-1px);
        }
        .nav-link.active.btn-outline-primary {
            background-color:var(--bs-primary) !important;
            color:#fff !important;
        }
        .nav-link.btn-outline-primary:not(.active) { color:#212529 !important; }
        .question-selector { max-height:420px; overflow-y:auto; background:#fff; }
        .question-item { transition:all .18s ease; }
        .question-item:hover { background-color:rgba(59,130,246,.05); }
        .question-item.is-hidden,
        .question-item.is-search-hidden,
        .question-item.is-category-hidden,
        .category-btn-wrapper.is-hidden { display:none !important; }
        .option-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.4rem; }
        .preview-actions {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:1rem;
            flex-wrap:wrap;
        }
        .worksheet-page {
            max-width: 880px;
            margin: 0 auto;
            background:#fff;
            color:#111827;
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:2.25rem;
            box-shadow:0 14px 36px rgba(15,23,42,.07);
        }
        .worksheet-cover {
            padding-bottom:1.25rem;
            margin-bottom:1.15rem;
            border-bottom:2px solid #111827;
        }
        .worksheet-title-row {
            align-items:flex-start;
            padding-top:.9rem;
        }
        .worksheet-title-row h1 {
            line-height:1.16;
        }
        .worksheet-title-meta {
            color:#475569;
            max-width:720px;
        }
        .worksheet-student-header {
            display:grid;
            grid-template-columns:minmax(0,1fr) auto;
            gap:1rem;
            color:#374151;
            font-size:.92rem;
            margin-bottom:1.15rem;
            padding:.85rem 1rem;
            border:1px solid #d1d5db;
            border-radius:8px;
            background:#f8fafc;
        }
        .worksheet-student-lines { display:grid; gap:.35rem; }
        .worksheet-group-chip {
            display:inline-grid;
            place-items:center;
            min-width:1.65rem;
            height:1.65rem;
            margin:0 .35rem;
            border-radius:4px;
            background:#111827;
            color:#fff;
            font-weight:900;
        }
        .worksheet-points-total { justify-self:end; font-weight:700; color:#6b7280; white-space:nowrap; }
        .worksheet-meta {
            display:none;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:.75rem;
            margin-top:1rem;
            font-size:.9rem;
        }
        .worksheet-meta div {
            border:1px solid #dbe4f0;
            border-radius:8px;
            padding:.55rem .7rem;
            min-height:44px;
        }
        .worksheet-question {
            break-inside:avoid;
            page-break-inside:avoid;
            border-bottom:1px solid #e5e7eb;
            border-radius:0;
            padding:.65rem 0 .95rem;
            margin-bottom:.15rem;
        }
        .worksheet-group-label {
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            border-radius:999px;
            padding:.35rem .75rem;
            background:#dbeafe;
            color:#1d4ed8;
            font-weight:800;
            margin:1rem 0 .75rem;
        }
        .worksheet-group-page + .worksheet-group-page {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px dashed #cbd5e1;
        }
        .worksheet-question h2 {
            display:grid;
            grid-template-columns:auto minmax(0,1fr) auto;
            align-items:start;
            gap:.45rem;
            font-size:1rem;
            line-height:1.42;
        }
        .worksheet-question-number {
            display:inline-grid;
            place-items:center;
            min-width:1.35rem;
            height:1.35rem;
            border-radius:3px;
            background:#111827;
            color:#fff;
            font-size:.82rem;
            font-weight:900;
            line-height:1;
            margin-top:.05rem;
        }
        .worksheet-question-points {
            color:#6b7280;
            font-size:.8rem;
            font-weight:700;
            white-space:nowrap;
        }
        .worksheet-options {
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:.5rem 1rem;
            margin-top:.75rem;
        }
        .worksheet-option {
            display:flex;
            align-items:flex-start;
            gap:.45rem;
            border:0;
            border-radius:0;
            padding:.25rem 0;
            min-height:24px;
            line-height:1.38;
        }
        .worksheet-option strong {
            display:inline-grid;
            place-items:center;
            width:1.35rem;
            height:1.35rem;
            border:1px solid #111827;
            border-radius:4px;
            font-size:.8rem;
            line-height:1;
            flex:0 0 auto;
        }
        .worksheet-open-space {
            height:128px;
            border:1px solid #d1d5db;
            border-radius:0;
            margin-top:.75rem;
            background-color:#fff;
            background-image:
                linear-gradient(#d8dce2 1px, transparent 1px),
                linear-gradient(90deg, #d8dce2 1px, transparent 1px);
            background-size:18px 18px;
        }
        .answer-key-page {
            page-break-before:always;
            break-before:page;
            margin-top:2rem;
        }
        .answer-key {
            columns:4 140px;
            border:1px solid #d1d5db;
            border-radius:8px;
            padding:.75rem 1rem;
            background:#f8fafc;
        }
        .answer-key div { break-inside:avoid; padding:.25rem 0; }
        .worksheet-footer { margin-top:2rem; padding-top:.7rem; border-top:1px solid #9ca3af; font-size:.8rem; color:#4b5563; display:flex; justify-content:space-between; gap:1rem; align-items:center; }
        .txt-format-box {
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:8px;
            padding:1rem;
        }
        .manual-q-item { border:1px solid rgba(148,163,184,.22); border-radius:8px; padding:1rem; background:#fff; }
        .manual-q-item + .manual-q-item { margin-top:.85rem; }
        .manual-q-textarea,
        .manual-q-explanation {
            resize: vertical;
            max-height: 220px;
            overflow: auto;
        }
        .manual-q-explanation {
            max-height: 180px;
        }
        .worksheet-brand-strip { display:none; }
        .worksheet-brand-mark {
            width:40px;
            height:40px;
            border-radius:8px;
            display:grid;
            place-items:center;
            background:rgba(255,255,255,.16);
            font-weight:900;
        }
        body.dark-mode .generator-panel,
        body.dark-mode .question-selector,
        body.dark-mode .txt-format-box,
        body.dark-mode .source-method-card,
        body.dark-mode .generator-preset,
        body.dark-mode .generator-live-estimate,
        body.dark-mode .manual-q-item,
        body.dark-mode .card.bg-light {
            background:#111827 !important;
            border-color:rgba(148,163,184,.24) !important;
            color:#e5e7eb;
        }
        body.dark-mode.pdf-generator-page {
            --bs-body-bg:#0f172a;
            --bs-body-color:#f8fafc;
            --bs-secondary-bg:#0f172a;
            --bs-tertiary-bg:#111827;
            --bs-border-color:rgba(148,163,184,.38);
            --bs-form-control-bg:#0f172a;
        }
        body.dark-mode .generator-title-row,
        body.dark-mode .generator-title-row .text-muted,
        body.dark-mode .generator-preset span,
        body.dark-mode .source-method-card span {
            color:#94a3b8 !important;
        }
        body.dark-mode .generator-preset strong,
        body.dark-mode .source-method-card,
        body.dark-mode .manual-q-item .fw-bold,
        body.dark-mode .txt-format-box .fw-bold {
            color:#f8fafc !important;
        }
        body.dark-mode .generator-preset:hover,
        body.dark-mode .source-method-card:hover {
            border-color:#60a5fa !important;
            box-shadow:0 10px 24px rgba(0,0,0,.24);
        }
        body.dark-mode .nav-link.btn-outline-primary:not(.active) {
            color:#dbeafe !important;
            border-color:rgba(96,165,250,.42) !important;
            background:rgba(15,23,42,.56) !important;
        }
        body.dark-mode.pdf-generator-page .form-control,
        body.dark-mode.pdf-generator-page .form-select,
        body.dark-mode.pdf-generator-page textarea {
            background:#0f172a !important;
            background-color:#0f172a !important;
            border-color:rgba(148,163,184,.38) !important;
            color:#f8fafc !important;
            box-shadow:none !important;
        }
        body.dark-mode.pdf-generator-page input.form-control,
        body.dark-mode.pdf-generator-page textarea.form-control {
            appearance:none;
            color-scheme:dark;
            background-image:none !important;
            -webkit-text-fill-color:#f8fafc !important;
            box-shadow:0 0 0 1000px #0f172a inset !important;
        }
        body.dark-mode.pdf-generator-page input[type="number"].form-control,
        body.dark-mode.pdf-generator-page input[type="text"].form-control {
            background-color:#0f172a !important;
            color:#f8fafc !important;
        }
        body.dark-mode.pdf-generator-page .form-control:focus,
        body.dark-mode.pdf-generator-page .form-select:focus,
        body.dark-mode.pdf-generator-page textarea:focus {
            border-color:#60a5fa !important;
            box-shadow:0 0 0 .2rem rgba(96,165,250,.18) !important;
        }
        body.dark-mode.pdf-generator-page .form-control::placeholder,
        body.dark-mode.pdf-generator-page textarea::placeholder {
            color:#94a3b8 !important;
        }
        body.dark-mode.pdf-generator-page .form-check-input:not(:checked) {
            background-color:#e5e7eb;
            border-color:#cbd5e1;
        }
        body.dark-mode .question-item {
            border-color:rgba(148,163,184,.22) !important;
        }
        body.dark-mode .question-item:hover {
            background:rgba(96,165,250,.10);
        }
        body.dark-mode .category-btn-wrapper label.btn-outline-primary {
            color:#dbeafe;
            border-color:rgba(96,165,250,.38);
        }
        body.dark-mode .worksheet-page {
            background:#fff !important;
            color:#111827 !important;
            border-color:#e5e7eb !important;
            box-shadow:0 16px 38px rgba(0,0,0,.34);
        }
        body.dark-mode .worksheet-page,
        body.dark-mode .worksheet-page h1,
        body.dark-mode .worksheet-page h2,
        body.dark-mode .worksheet-page h3,
        body.dark-mode .worksheet-page p,
        body.dark-mode .worksheet-page .fw-bold,
        body.dark-mode .worksheet-page .worksheet-option,
        body.dark-mode .worksheet-page .worksheet-meta div {
            color:#111827 !important;
        }
        body.dark-mode .worksheet-page .text-muted,
        body.dark-mode .worksheet-page .worksheet-footer {
            color:#64748b !important;
        }
        body.dark-mode .worksheet-page .worksheet-brand-strip,
        body.dark-mode .worksheet-page .worksheet-brand-strip *,
        body.dark-mode .worksheet-page .worksheet-brand-mark {
            color:#fff !important;
        }
        body.dark-mode .worksheet-page .worksheet-question,
        body.dark-mode .worksheet-page .worksheet-option,
        body.dark-mode .worksheet-page .worksheet-meta div {
            background:#fff !important;
            border-color:#d1d5db !important;
        }
        @media (max-width: 767.98px) {
            .generator-title-row,
            .worksheet-title-row {
                align-items:flex-start;
                flex-direction:column;
            }
            .source-method-grid { grid-template-columns:1fr; }
            .option-grid, .worksheet-options, .worksheet-meta, .worksheet-student-header { grid-template-columns:1fr; }
            .worksheet-points-total { justify-self:start; }
            .worksheet-page { padding:1rem; }
        }
        @media print {
            @page { size:A4; margin:12mm; }
            html, body { background:#fff !important; color:#111827 !important; }
            .sidebar, .topbar, .main-footer, .no-print { display:none !important; }
            .main-container { margin:0 !important; padding:0 !important; }
            .content-body { padding:0 !important; background:#fff !important; }
            .generator-shell { max-width:none !important; margin:0 !important; }
            .worksheet-page {
                max-width:none !important;
                width:100% !important;
                margin:0 !important;
                padding:0 !important;
                border:0 !important;
                border-radius:0 !important;
                box-shadow:none !important;
            }
            .worksheet-options { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
            .worksheet-group-page { page-break-before:always; break-before:page; }
            .worksheet-cover + .worksheet-group-page { page-break-before:auto; break-before:auto; }
            .answer-key { columns:4 140px !important; }
            a[href]::after { content:""; }
        }
    </style>
    <style id="worksheetPrintCss">
        @page { size:A4; margin:12mm; }
        body { margin:0; background:#fff; color:#111827; font-family:Inter, Arial, sans-serif; font-size:11pt; line-height:1.35; }
        .d-flex { display:flex; }
        .justify-content-between { justify-content:space-between; }
        .align-items-start { align-items:flex-start; }
        .gap-3 { gap:12px; }
        .text-end { text-align:right; }
        .fw-bold { font-weight:700; }
        .h3 { font-size:20pt; }
        .h4 { font-size:15pt; }
        .h6 { font-size:11.5pt; }
        .mb-0 { margin-bottom:0; }
        .mb-1 { margin-bottom:4px; }
        .mb-2 { margin-bottom:8px; }
        .mt-4 { margin-top:24px; }
        .worksheet-page { width:100%; margin:0; background:#fff; color:#111827; }
        .worksheet-cover { padding-bottom:12px; margin-bottom:14px; border-bottom:2px solid #111827; }
        .worksheet-brand-strip { display:none; }
        .worksheet-brand-mark { width:34px; height:34px; border-radius:7px; display:grid; place-items:center; background:rgba(255,255,255,.16); font-weight:900; }
        .worksheet-cover h1 { margin:0 0 4px; font-size:20pt; }
        .worksheet-cover p { margin:4px 0; }
        .text-muted, .small { color:#64748b; }
        .worksheet-title-row { align-items:flex-start; padding-top:8px; }
        .worksheet-title-meta { color:#475569; }
        .worksheet-student-header { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; color:#374151; font-size:10pt; margin-bottom:14px; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; background:#f8fafc; }
        .worksheet-student-lines { display:grid; gap:4px; }
        .worksheet-group-chip { display:inline-grid; place-items:center; min-width:20px; height:20px; margin:0 4px; border-radius:3px; background:#111827; color:#fff; font-weight:900; }
        .worksheet-points-total { justify-self:end; font-weight:700; color:#4b5563; white-space:nowrap; }
        .worksheet-meta { display:none; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:12px; font-size:10pt; }
        .worksheet-meta div { border:1px solid #dbe4f0; border-radius:6px; padding:7px 8px; min-height:32px; }
        .worksheet-question { break-inside:avoid; page-break-inside:avoid; border-bottom:1px solid #e5e7eb; border-radius:0; padding:6px 0 10px; margin-bottom:2px; }
        .worksheet-group-page { page-break-before:always; break-before:page; }
        .worksheet-cover + .worksheet-group-page { page-break-before:auto; break-before:auto; }
        .worksheet-group-label { display:inline-block; border-radius:999px; padding:4px 9px; background:#dbeafe; color:#1d4ed8; font-weight:800; margin:12px 0 8px; }
        .worksheet-question h2 { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:start; gap:6px; margin:4px 0 8px; font-size:11.5pt; line-height:1.38; }
        .worksheet-question-number { display:inline-grid; place-items:center; min-width:18px; height:18px; border-radius:3px; background:#111827; color:#fff; font-size:9pt; font-weight:900; line-height:1; margin-top:1px; }
        .worksheet-question-points { color:#64748b; font-size:9pt; font-weight:700; white-space:nowrap; }
        .worksheet-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px 12px; margin-top:8px; }
        .worksheet-option { display:flex; align-items:flex-start; gap:5px; border:0; border-radius:0; padding:2px 0; min-height:24px; line-height:1.35; }
        .worksheet-option strong { display:inline-grid; place-items:center; width:18px; height:18px; border:1px solid #111827; border-radius:3px; font-size:8.5pt; line-height:1; flex:0 0 auto; }
        .worksheet-open-space { height:112px; border:1px solid #d1d5db; border-radius:0; margin-top:8px; background-color:#fff; background-image:linear-gradient(#d8dce2 1px, transparent 1px), linear-gradient(90deg, #d8dce2 1px, transparent 1px); background-size:18px 18px; }
        .answer-key-page { page-break-before:always; break-before:page; margin-top:24px; }
        .answer-key { columns:4 120px; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; background:#f8fafc; }
        .answer-key div { break-inside:avoid; padding:3px 0; }
        .worksheet-footer { margin-top:24px; padding-top:10px; border-top:1px solid #9ca3af; font-size:9pt; color:#4b5563; display:flex; justify-content:space-between; gap:12px; align-items:center; }
        img { max-width:100%; max-height:220px; height:auto; }
    </style>
</head>
<body class="pdf-generator-page">
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main class="content-body">
            <div class="generator-shell">
                <div class="generator-title-row no-print">
                    <div>
                        <div class="generator-title-main">
                            <span class="generator-title-icon"><i class="bi bi-plus-lg"></i></span>
                            <h1 class="fw-bold mb-0">Generator sprawdzianów</h1>
                        </div>
                        <p class="text-muted mb-0 mt-2">Narzędzie do tworzenia sprawdzianów przeznaczonych do druku: z puli pytań, pliku TXT albo pytań ułożonych ręcznie.</p>
                    </div>
                    <a href="index.php" class="btn btn-link text-decoration-none text-muted fw-semibold">
                        <i class="bi bi-arrow-left me-1"></i>Powrót
                    </a>
                </div>

                <?php $flash = getSessionMessage(); if ($flash): ?>
                    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?> no-print"><?php echo htmlspecialchars($flash['message'] ?? ''); ?></div>
                <?php endif; ?>
                <?php if ($formNotice): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($formNotice['type']); ?> no-print"><?php echo htmlspecialchars($formNotice['message']); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="worksheetForm" class="no-print">
                    <?php echo csrfTokenField('teacher_pdf_generator'); ?>
                                        <div class="row g-4 mb-4">
                        <div class="col-xl-8">
                            <div class="generator-card h-100">
                                <div class="generator-card-header">
                                    <div class="icon-wrapper"><i class="bi bi-pencil-square"></i></div>
                                    <h5>Podstawowe informacje</h5>
                                </div>
                                <div class="generator-card-body">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <div class="form-floating-custom">
                                                <label for="title">Tytuł sprawdzianu</label>
                                                <input class="form-control form-control-lg" id="title" name="title" maxlength="120" placeholder="Wpisz tytuł..." value="<?php echo htmlspecialchars($title); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating-custom">
                                                <label for="difficultyLevel">Poziom trudności</label>
                                                <select class="form-select form-select-lg" id="difficultyLevel" name="difficulty_level">
                                                    <?php foreach ($difficultyLabels as $value => $label): ?>
                                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $difficultyLevel === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-floating-custom">
                                                <label for="description">Opis lub instrukcja (opcjonalnie)</label>
                                                <input class="form-control" id="description" name="description" maxlength="220" placeholder="Krótki opis widoczny pod tytułem..." value="<?php echo htmlspecialchars($description); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4 text-muted opacity-25">

                                    <h6 class="fw-bold mb-3 text-muted small text-uppercase tracking-wider">Konfiguracja arkuszy</h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="form-floating-custom">
                                                <label for="questionCountInput">Pytań / grupę</label>
                                                <input class="form-control text-center fw-bold" id="questionCountInput" name="question_count" type="number" min="1" max="120" value="<?php echo (int)$questionCount; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating-custom">
                                                <label for="groupCountInput">Liczba grup</label>
                                                <input class="form-control text-center fw-bold" id="groupCountInput" name="group_count" type="number" min="1" max="10" value="<?php echo (int)$groupCount; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating-custom">
                                                <label for="groupStrategy">Warianty grup</label>
                                                <select class="form-select" id="groupStrategy" name="group_strategy">
                                                    <option value="unique" <?php echo $groupStrategy === 'unique' ? 'selected' : ''; ?>>Różne pytania</option>
                                                    <option value="rotate" <?php echo $groupStrategy === 'rotate' ? 'selected' : ''; ?>>Inna kolejność</option>
                                                    <option value="same" <?php echo $groupStrategy === 'same' ? 'selected' : ''; ?>>Identyczne</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating-custom">
                                                <label for="fontSize">Wielkość czcionki</label>
                                                <select class="form-select" id="fontSize" name="font_size">
                                                    <option value="small" <?php echo $fontSize === 'small' ? 'selected' : ''; ?>>Mała</option>
                                                    <option value="normal" <?php echo $fontSize === 'normal' ? 'selected' : ''; ?>>Normalna</option>
                                                    <option value="large" <?php echo $fontSize === 'large' ? 'selected' : ''; ?>>Duża</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="generator-live-estimate mt-4" id="worksheetEstimate" aria-live="polite"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="generator-card h-100">
                                <div class="generator-card-header">
                                    <div class="icon-wrapper"><i class="bi bi-sliders"></i></div>
                                    <h5>Opcje i wygląd</h5>
                                </div>
                                <div class="generator-card-body d-flex flex-column gap-3">
                                    <div class="toggle-card">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-check-label mb-0" for="shuffleQuestions">
                                                <i class="bi bi-shuffle me-2 text-primary"></i>Tasuj pytania
                                            </label>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input fs-5 m-0" type="checkbox" name="shuffle_questions" id="shuffleQuestions" <?php echo $shuffleQuestions ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="text-muted small">Zmienia kolejność pytań w grupach.</div>
                                    </div>

                                    <div class="toggle-card">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-check-label mb-0" for="shuffleAnswers">
                                                <i class="bi bi-list-nested me-2 text-primary"></i>Tasuj odpowiedzi
                                            </label>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input fs-5 m-0" type="checkbox" name="shuffle_answers" id="shuffleAnswers" <?php echo $shuffleAnswers ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="text-muted small">Zmienia kolejność opcji A, B, C, D.</div>
                                    </div>

                                    <div class="toggle-card">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-check-label mb-0" for="showPoints">
                                                <i class="bi bi-123 me-2 text-success"></i>Pokaż punktację
                                            </label>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input fs-5 m-0" type="checkbox" name="show_points" id="showPoints" <?php echo $showPoints ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="text-muted small">Wyświetla ilość punktów przy pytaniach.</div>
                                    </div>

                                    <div class="toggle-card">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-check-label mb-0" for="includeKey">
                                                <i class="bi bi-key me-2 text-warning"></i>Klucz odpowiedzi
                                            </label>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input fs-5 m-0" type="checkbox" name="include_key" id="includeKey" <?php echo $includeKey ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="text-muted small">Dodaje stronę z rozwiązaniami dla nauczyciela.</div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <div class="form-check form-switch flex-fill border rounded p-2 ps-5 bg-light">
                                            <input class="form-check-input" type="checkbox" name="show_date_space" id="showDateSpace" <?php echo $showDateSpace ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="showDateSpace">Miejsce: Data</label>
                                        </div>
                                        <div class="form-check form-switch flex-fill border rounded p-2 ps-5 bg-light">
                                            <input class="form-check-input" type="checkbox" name="show_grade_space" id="showGradeSpace" <?php echo $showGradeSpace ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="showGradeSpace">Miejsce: Ocena</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="generator-preset-row" aria-label="Szybkie presety generatora">
                                <button type="button" class="generator-preset" data-generator-preset data-count="20" data-groups="1" data-strategy="same" data-title="Kartkówka CKE">
                                    <strong><i class="bi bi-lightning-charge me-2 text-warning"></i>Szybka Kartkówka</strong>
                                    <span>20 pytań, 1 grupa, bez mieszania wariantów.</span>
                                </button>
                                <button type="button" class="generator-preset" data-generator-preset data-count="40" data-groups="2" data-strategy="rotate" data-title="Sprawdzian CKE">
                                    <strong><i class="bi bi-shuffle me-2 text-primary"></i>Standardowy Sprawdzian</strong>
                                    <span>40 pytań, 2 grupy, rotacja kolejności.</span>
                                </button>
                                <button type="button" class="generator-preset" data-generator-preset data-count="60" data-groups="3" data-strategy="unique" data-title="Próbny egzamin zawodowy">
                                    <strong><i class="bi bi-mortarboard me-2 text-success"></i>Egzamin Próbny</strong>
                                    <span>60 pytań, 3 grupy, całkowicie różne zestawy.</span>
                                </button>
                            </div>
                        </div>
                    </div>

                                        <div class="generator-card mb-4">
                        <div class="generator-card-header justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-wrapper"><i class="bi bi-collection"></i></div>
                                <h5>Źródło pytań</h5>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-3 py-2"><?php echo count($allQuestions); ?> dostępnych pytań</span>
                        </div>
                        <div class="generator-card-body">

                            <ul class="nav nav-pills nav-pills-custom mb-4 gap-2 bg-light p-1 rounded-3 d-inline-flex" id="questionTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $generatorMode === 'db' ? 'active' : ''; ?>" id="db-tab" data-bs-toggle="tab" data-bs-target="#db-questions" type="button" role="tab">
                                        <i class="bi bi-database me-2"></i>Baza Pytań
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $generatorMode === 'txt' ? 'active' : ''; ?>" id="txt-tab" data-bs-toggle="tab" data-bs-target="#txt-questions" type="button" role="tab">
                                        <i class="bi bi-file-earmark-arrow-up me-2"></i>Import TXT
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $generatorMode === 'manual' ? 'active' : ''; ?>" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-questions" type="button" role="tab">
                                        <i class="bi bi-keyboard me-2"></i>Wprowadź Ręcznie
                                    </button>
                                </li>
                            </ul>

                            <input type="hidden" name="generator_mode" id="generatorMode" value="<?php echo htmlspecialchars($generatorMode); ?>">

                            <div class="tab-content" id="questionTabsContent">
                                <div class="tab-pane fade <?php echo $generatorMode === 'db' ? 'show active' : ''; ?>" id="db-questions" role="tabpanel">
                                    <div class="category-tools mb-3">
                                        <label class="form-label fw-semibold mb-0">Kategorie pytań</label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <input type="search" id="categorySearch" class="form-control form-control-sm category-search" placeholder="Szukaj kategorii...">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="selectAllCategories">Zaznacz widoczne</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="clearCategories">Wyczyść</button>
                                        </div>
                                    </div>
                                    <div class="category-selector-grid d-flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($categories as $cat): ?>
                                            <div class="category-btn-wrapper" data-category-name="<?php echo htmlspecialchars(mb_strtolower($cat, 'UTF-8')); ?>">
                                                <input type="checkbox" class="btn-check" name="categories[]" id="cat_<?php echo md5($cat); ?>" value="<?php echo htmlspecialchars($cat); ?>" autocomplete="off" <?php echo in_array($cat, $selectedCategories, true) ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-primary rounded-pill px-3 py-2 btn-sm fw-medium" for="cat_<?php echo md5($cat); ?>">
                                                    <i class="bi bi-tag-fill me-1 small"></i><?php echo htmlspecialchars($cat); ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-dark ms-1 fw-bold"><?php echo (int)($categoryCounts[$cat] ?? 0); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectSpecificToggle" <?php echo !empty($_POST['selected_questions'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold" for="selectSpecificToggle">Wybierz dokładne pytania</label>
                                    </div>
                                    <div id="questionSelector" class="question-selector border rounded p-3" style="<?php echo !empty($_POST['selected_questions'] ?? []) ? '' : 'display:none;'; ?>">
                                        <div class="question-toolbar mb-3">
                                            <input type="text" id="questionSearch" class="form-control" placeholder="Szukaj pytania..." style="max-width:360px">
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="selectVisibleQuestions">Zaznacz widoczne</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="clearQuestions">Wyczyść</button>
                                            </div>
                                        </div>
                                        <div id="questionList">
                                            <?php foreach (array_slice($allQuestions, 0, $questionSelectorLimit) as $question): ?>
                                                <?php
                                                $qid = (string)($question['id'] ?? '');
                                                $isChecked = in_array($qid, array_map('strval', $_POST['selected_questions'] ?? []), true);
                                                $questionText = (string)($question['question_text'] ?? '');
                                                ?>
                                                <div class="question-item form-check py-2 border-bottom"
                                                     data-question-category="<?php echo htmlspecialchars(mb_strtolower((string)($question['category'] ?? ''), 'UTF-8')); ?>"
                                                     data-question-text="<?php echo htmlspecialchars(mb_strtolower($questionText . ' ' . ($question['category'] ?? ''), 'UTF-8')); ?>">
                                                    <input class="form-check-input" type="checkbox" name="selected_questions[]" value="<?php echo htmlspecialchars($qid); ?>" id="q<?php echo htmlspecialchars($qid); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small" for="q<?php echo htmlspecialchars($qid); ?>">
                                                        <span class="badge bg-secondary bg-opacity-25 text-dark me-1">#<?php echo htmlspecialchars($qid); ?></span>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold me-1"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($question['category'] ?? ''); ?></span>
                                                        <?php echo htmlspecialchars(mb_substr($questionText, 0, 140)); ?><?php echo mb_strlen($questionText, 'UTF-8') > 140 ? '...' : ''; ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-muted small mt-2">
                                            Zaznaczono: <span id="selectedCount">0</span> pytań
                                            <?php if (count($allQuestions) > $questionSelectorLimit): ?>
                                                · pokazano pierwsze <?php echo (int)$questionSelectorLimit; ?> z <?php echo count($allQuestions); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade <?php echo $generatorMode === 'txt' ? 'show active' : ''; ?>" id="txt-questions" role="tabpanel">
                                    <div class="row g-3 align-items-start">
                                        <div class="col-lg-7">
                                            <label class="form-label fw-semibold" for="txtFile">Wybierz plik TXT/CSV</label>
                                            <input class="form-control" type="file" name="txt_file" id="txtFile" accept=".txt,.csv,text/plain,text/csv">
                                        </div>
                                        <div class="col-lg-5">
                                            <div class="txt-format-box small">
                                                <div class="fw-bold mb-1">Format z kategorią</div>
                                                <code>kategoria;pytanie;A;B;C;D;poprawna;obraz;wyjaśnienie</code>
                                                <div class="fw-bold mt-2 mb-1">Format prosty</div>
                                                <code>pytanie;A;B;C;D;poprawna</code>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade <?php echo $generatorMode === 'manual' ? 'show active' : ''; ?>" id="manual-questions" role="tabpanel">
                                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                                        <div>
                                            <div class="fw-bold">Własne pytania do wydruku</div>
                                            <div class="text-muted small">Dodaj treść, odpowiedzi, poprawną odpowiedź, URL obrazka i wyjaśnienie do klucza.</div>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill" type="button" id="addManualQuestion">
                                            <i class="bi bi-plus-lg me-1"></i>Dodaj pytanie
                                        </button>
                                    </div>
                                    <div id="manualQuestions"></div>
                                </div>
                            </div>

                            <hr class="my-4 text-muted opacity-25">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>Sprawdź opcje na górze przed wygenerowaniem.
                                </div>
                                <button class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm" type="submit">
                                    <i class="bi bi-magic me-2"></i>Generuj Arkusze
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if ($submitted && empty($selected)): ?>
                    <div class="alert alert-warning no-print">Brak pytań do wygenerowania. Zmień filtr, wybierz pytania albo dodaj plik TXT.</div>
                <?php endif; ?>

                <?php if (!empty($worksheetGroups)): ?>
                    <div class="preview-actions mb-3 no-print">
                        <div>
                            <div class="fw-bold">Podgląd arkusza</div>
                            <div class="text-muted small"><?php echo htmlspecialchars($generationSourceLabel); ?> · <?php echo (int)$groupCount; ?> grup · <?php echo (int)$questionCount; ?> pytań/grupa · <?php echo htmlspecialchars($difficultyLabels[$difficultyLevel]); ?></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap justify-content-end">
                            <button type="button" class="btn btn-danger rounded-pill px-4" onclick="printWorksheet('print')">
                                <i class="bi bi-printer me-1"></i>Drukuj
                            </button>
                            <button type="button" class="btn btn-outline-danger rounded-pill px-4" onclick="printWorksheet('pdf')">
                                <i class="bi bi-filetype-pdf me-1"></i>Zapisz PDF
                            </button>
                            <?php if ((int)$groupCount > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-outline-primary rounded-pill px-4 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-collection me-1"></i>PDF grupy
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php foreach ($worksheetGroups as $group): ?>
                                            <li><button class="dropdown-item" type="button" onclick="printWorksheet('pdf', '<?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?>')">Grupa <?php echo htmlspecialchars($group['label']); ?></button></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <form method="POST" class="m-0">
                                <?php echo csrfTokenField('teacher_pdf_generator_save'); ?>
                                <input type="hidden" name="worksheet_action" value="save_preview">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($title); ?>">
                                <input type="hidden" name="description" value="<?php echo htmlspecialchars($description); ?>">
                                <input type="hidden" name="difficulty_level" value="<?php echo htmlspecialchars($difficultyLevel); ?>">
                                <input type="hidden" name="questions_payload" value="<?php echo htmlspecialchars(base64_encode(json_encode($selected, JSON_UNESCAPED_UNICODE)), ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-primary rounded-pill px-4">
                                    <i class="bi bi-folder-plus me-1"></i>Zapisz w moje sprawdziany
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php
                        $worksheetHeaderGroup = count($worksheetGroups) === 1
                            ? (string)($worksheetGroups[0]['label'] ?? 'A')
                            : (string)($worksheetGroups[0]['label'] ?? 'A') . '-' . (string)($worksheetGroups[count($worksheetGroups) - 1]['label'] ?? 'A');
                        $worksheetTotalPoints = 0;
                        foreach (($worksheetGroups[0]['questions'] ?? []) as $worksheetPointQuestion) {
                            $worksheetTotalPoints += worksheetQuestionIsOpen($worksheetPointQuestion) ? 2 : 1;
                        }
                    ?>
                    <article class="worksheet-page" id="worksheetPrintSource" data-print-title="<?php echo htmlspecialchars($title); ?>">
                        <header class="worksheet-cover">
                            <div class="worksheet-student-header">
                                <div class="worksheet-student-lines">
                                    <div>Grupa <span class="worksheet-group-chip"><?php echo htmlspecialchars($worksheetHeaderGroup); ?></span> Klasa ....................................</div>
                                    <div>Imię i nazwisko ....................................................................................</div>
                                </div>
                                <div class="worksheet-points-total">Liczba punktów ........ / <?php echo (int)$worksheetTotalPoints; ?></div>
                            </div>
                            <div class="d-flex justify-content-between gap-3 worksheet-title-row">
                                <div>
                                    <h1 class="h3 fw-bold mb-1"><?php echo htmlspecialchars($title); ?></h1>
                                    <?php if ($description !== ''): ?>
                                        <p class="mb-1"><?php echo htmlspecialchars($description); ?></p>
                                    <?php endif; ?>
                                    <p class="worksheet-title-meta small mb-0">
                                        Źródło: <?php echo htmlspecialchars($generationSourceLabel); ?> ·
                                        Poziom: <?php echo htmlspecialchars($difficultyLabels[$difficultyLevel]); ?> ·
                                        Grup: <?php echo (int)$groupCount; ?> ·
                                        Pytań w grupie: <?php echo (int)$questionCount; ?> ·
                                        Data: <?php echo date('d.m.Y'); ?>
                                    </p>
                                </div>
                                <div class="text-end small text-muted">ZSEM Tech</div>
                            </div>
                            <div class="worksheet-meta">
                                <div>Imię i nazwisko:<br>................................</div>
                                <div>Klasa:<br>....................</div>
                                <div>Nr w dzienniku:<br>....................</div>
                                <div>Wynik:<br>....................</div>
                            </div>
                        </header>

                        <?php foreach ($worksheetGroups as $group): ?>
                            <section class="worksheet-group-page" data-worksheet-group="<?php echo htmlspecialchars($group['label']); ?>">
                            <div class="worksheet-group-label"><i class="bi bi-collection"></i>Grupa <?php echo htmlspecialchars($group['label']); ?></div>
                            <?php foreach ($group['questions'] as $index => $question): ?>
                                <section class="worksheet-question">
                                    <h2 class="fw-bold">
                                        <span class="worksheet-question-number"><?php echo $index + 1; ?></span>
                                        <span><?php echo htmlspecialchars($question['question_text'] ?? ''); ?></span>
                                        <span class="worksheet-question-points"><?php echo worksheetQuestionIsOpen($question) ? '2 p.' : '1 p.'; ?></span>
                                    </h2>
                                    <?php if (!empty($question['image_url'])): ?>
                                        <?php $imageSrc = questionImageSrc($question['image_url'], '../'); ?>
                                        <?php if ($imageSrc): ?>
                                            <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="Ilustracja do pytania <?php echo $index + 1; ?> w grupie <?php echo htmlspecialchars($group['label']); ?>" class="mb-2" style="max-width:100%;max-height:220px">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (worksheetQuestionIsOpen($question)): ?>
                                        <div class="worksheet-open-space"></div>
                                    <?php else: ?>
                                        <div class="worksheet-options">
                                            <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                                                <div class="worksheet-option">
                                                    <strong><?php echo $letter; ?>.</strong>
                                                    <?php echo htmlspecialchars($question['option_' . strtolower($letter)] ?? ''); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <?php if ($includeKey): ?>
                            <section class="answer-key-page">
                                <h2 class="h4 fw-bold">Klucz odpowiedzi</h2>
                                <p class="text-muted small">Ta sekcja zaczyna się od nowej strony.</p>
                                <div class="answer-key">
                                    <?php foreach ($worksheetGroups as $group): ?>
                                        <div class="fw-bold mt-2">Grupa <?php echo htmlspecialchars($group['label']); ?></div>
                                        <?php foreach ($group['questions'] as $index => $question): ?>
                                            <div><?php echo $index + 1; ?>. <strong><?php echo htmlspecialchars(worksheetAnswerLabel($question)); ?></strong></div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($showExplanations): ?>
                                    <div class="mt-4">
                                        <h3 class="h6 fw-bold">Wyjaśnienia</h3>
                                        <?php foreach ($worksheetGroups as $group): ?>
                                            <div class="fw-bold mt-2">Grupa <?php echo htmlspecialchars($group['label']); ?></div>
                                            <?php foreach ($group['questions'] as $index => $question): ?>
                                                <div class="mb-2"><strong><?php echo $index + 1; ?>.</strong> <?php echo htmlspecialchars(trim((string)($question['explanation'] ?? '')) ?: 'Brak wyjaśnienia w źródle pytania.'); ?></div>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>

                        <footer class="worksheet-footer">
                            <span>ZSEM Tech Generator</span>
                            <span>Grupa <?php echo htmlspecialchars($worksheetHeaderGroup); ?> | <?php echo date('d.m.Y'); ?></span>
                        </footer>
                    </article>
                <?php endif; ?>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/theme-handler.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const byId = id => document.getElementById(id);
    const generatorMode = byId('generatorMode');

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', event => {
            if (event.target.id === 'txt-tab') generatorMode.value = 'txt';
            if (event.target.id === 'db-tab') generatorMode.value = 'db';
            if (event.target.id === 'manual-tab') generatorMode.value = 'manual';
        });
    });

    function updateSelectedCount() {
        const count = document.querySelectorAll('[name="selected_questions[]"]:checked').length;
        const badge = byId('selectedCount');
        if (badge) badge.textContent = String(count);
    }

    function setQuestionSelectorEnabled(enabled) {
        const panel = byId('questionSelector');
        if (!panel) return;
        panel.style.display = enabled ? 'block' : 'none';
        panel.querySelectorAll('[name="selected_questions[]"]').forEach(input => {
            input.disabled = !enabled;
            if (!enabled) input.checked = false;
        });
        updateSelectedCount();
    }

    byId('selectSpecificToggle')?.addEventListener('change', function() {
        setQuestionSelectorEnabled(this.checked);
    });

    byId('categorySearch')?.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        document.querySelectorAll('.category-btn-wrapper').forEach(item => {
            item.classList.toggle('is-hidden', !item.dataset.categoryName.includes(term));
        });
    });

    byId('selectAllCategories')?.addEventListener('click', () => {
        document.querySelectorAll('.category-btn-wrapper:not(.is-hidden) input[type="checkbox"]').forEach(input => input.checked = true);
        syncQuestionCategoryFilter();
    });

    byId('clearCategories')?.addEventListener('click', () => {
        document.querySelectorAll('[name="categories[]"]').forEach(input => input.checked = false);
        syncQuestionCategoryFilter();
    });

    byId('questionSearch')?.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        document.querySelectorAll('.question-item').forEach(item => {
            item.classList.toggle('is-search-hidden', !item.dataset.questionText.includes(query));
        });
    });

    function syncQuestionCategoryFilter() {
        const selectedCategories = new Set(Array.from(document.querySelectorAll('[name="categories[]"]:checked')).map(input => input.value.trim().toLowerCase()));
        document.querySelectorAll('.question-item').forEach(item => {
            item.classList.toggle('is-category-hidden', selectedCategories.size > 0 && !selectedCategories.has((item.dataset.questionCategory || '').toLowerCase()));
        });
    }

    document.querySelectorAll('[name="categories[]"]').forEach(input => {
        input.addEventListener('change', syncQuestionCategoryFilter);
    });

    byId('selectVisibleQuestions')?.addEventListener('click', () => {
        document.querySelectorAll('.question-item:not(.is-search-hidden):not(.is-category-hidden) [name="selected_questions[]"]').forEach(input => input.checked = true);
        updateSelectedCount();
    });

    byId('clearQuestions')?.addEventListener('click', () => {
        document.querySelectorAll('[name="selected_questions[]"]').forEach(input => input.checked = false);
        updateSelectedCount();
    });

    document.querySelectorAll('[name="selected_questions[]"]').forEach(input => {
        input.addEventListener('change', updateSelectedCount);
    });
    setQuestionSelectorEnabled(byId('selectSpecificToggle')?.checked || false);
    syncQuestionCategoryFilter();
    updateSelectedCount();

    const manualBox = byId('manualQuestions');
    const manualInitial = <?php echo json_encode(is_array($_POST['manual_questions'] ?? null) ? $_POST['manual_questions'] : [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const manualQuestionLimit = 120;
    let manualCount = 0;
    const escAttr = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[char]));
    function addManualQuestion(data = {}) {
        if (!manualBox || manualBox.querySelectorAll('[data-manual-item]').length >= manualQuestionLimit) return;
        const n = manualCount++;
        const type = data.type === 'open' ? 'open' : 'closed';
        const selectedCategory = document.querySelector('[name="categories[]"]:checked')?.value || 'Własne';
        manualBox.insertAdjacentHTML('beforeend', `
            <div class="manual-q-item" data-manual-item>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <strong>Pytanie ${n + 1}</strong>
                    <button class="btn btn-sm btn-link text-danger" type="button" data-remove-manual><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Treść pytania</label>
                        <textarea class="form-control manual-q-textarea" rows="2" name="manual_questions[${n}][question_text]" maxlength="1400">${escAttr(data.question_text || '')}</textarea>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Kategoria</label>
                        <input class="form-control" name="manual_questions[${n}][category]" data-manual-category maxlength="80" value="${escAttr(data.category || selectedCategory)}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Typ</label>
                        <select class="form-select" name="manual_questions[${n}][type]" data-manual-type>
                            <option value="closed" ${type === 'closed' ? 'selected' : ''}>A-D</option>
                            <option value="open" ${type === 'open' ? 'selected' : ''}>Opisowe</option>
                        </select>
                    </div>
                    ${['A','B','C','D'].map(letter => `
                        <div class="col-md-6" data-closed-field>
                            <label class="form-label small fw-bold">Odpowiedź ${letter}</label>
                            <input class="form-control" name="manual_questions[${n}][option_${letter.toLowerCase()}]" maxlength="600" value="${escAttr(data['option_' + letter.toLowerCase()] || '')}">
                        </div>
                    `).join('')}
                    <div class="col-md-3" data-closed-field>
                        <label class="form-label small fw-bold">Poprawna</label>
                        <select class="form-select" name="manual_questions[${n}][correct_answer]">
                            ${['A','B','C','D'].map(letter => `<option value="${letter}" ${(data.correct_answer || 'A') === letter ? 'selected' : ''}>${letter}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label small fw-bold">URL obrazka</label>
                        <input class="form-control" name="manual_questions[${n}][image_url]" maxlength="500" value="${escAttr(data.image_url || '')}" placeholder="https://... albo assets/images/...">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Wyjaśnienie do klucza</label>
                        <textarea class="form-control manual-q-explanation" rows="2" name="manual_questions[${n}][explanation]" maxlength="1600">${escAttr(data.explanation || '')}</textarea>
                    </div>
                </div>
            </div>
        `);
        const item = manualBox.lastElementChild;
        const syncType = () => {
            const open = item.querySelector('[data-manual-type]')?.value === 'open';
            item.querySelectorAll('[data-closed-field]').forEach(el => el.style.display = open ? 'none' : '');
        };
        item.querySelector('[data-manual-type]')?.addEventListener('change', syncType);
        item.querySelector('[data-remove-manual]')?.addEventListener('click', () => item.remove());
        syncType();
    }
    if (Array.isArray(manualInitial) && manualInitial.length) {
        manualInitial.forEach(row => addManualQuestion(row || {}));
    }
    byId('addManualQuestion')?.addEventListener('click', () => addManualQuestion());
    byId('manual-tab')?.addEventListener('shown.bs.tab', () => {
        if (manualCount === 0) addManualQuestion();
    });
    if (generatorMode?.value === 'manual' && manualCount === 0) addManualQuestion();

    function syncWorksheetEstimate() {
        const questionCount = Math.max(1, Math.min(120, Number(byId('questionCountInput')?.value || 0)));
        const groupCount = Math.max(1, Math.min(10, Number(byId('groupCountInput')?.value || 0)));
        const strategy = byId('groupStrategy')?.value || 'unique';
        const estimate = byId('worksheetEstimate');
        if (!estimate) return;
        const pages = Math.max(1, Math.ceil((questionCount * groupCount) / 12));
        const modeLabel = strategy === 'same' ? 'ten sam zestaw' : (strategy === 'rotate' ? 'rotacja kolejności' : 'różne zestawy');
        estimate.innerHTML = `<i class="bi bi-speedometer2 me-1"></i>Podgląd: ${questionCount} pytań x ${groupCount} grup, ${modeLabel}. Szacowany druk: ok. ${pages} stron.`;
    }

    document.querySelectorAll('[data-generator-preset]').forEach(button => {
        button.addEventListener('click', () => {
            const titleInput = byId('title');
            if (titleInput && !titleInput.value.trim()) titleInput.value = button.dataset.title || '';
            if (byId('questionCountInput')) byId('questionCountInput').value = button.dataset.count || '40';
            if (byId('groupCountInput')) byId('groupCountInput').value = button.dataset.groups || '1';
            if (byId('groupStrategy')) byId('groupStrategy').value = button.dataset.strategy || 'unique';
            if (byId('shuffleQuestions')) byId('shuffleQuestions').checked = true;
            syncWorksheetEstimate();
        });
    });
    ['questionCountInput', 'groupCountInput', 'groupStrategy'].forEach(id => {
        byId(id)?.addEventListener('input', syncWorksheetEstimate);
        byId(id)?.addEventListener('change', syncWorksheetEstimate);
    });
    syncWorksheetEstimate();
});

function printWorksheet() {
    const mode = arguments[0] || 'print';
    const groupLabel = arguments[1] || '';
    const source = document.getElementById('worksheetPrintSource');
    if (!source) return;

    const css = document.getElementById('worksheetPrintCss')?.textContent || '';
    const title = (source.dataset.printTitle || document.title) + (groupLabel ? ` - grupa ${groupLabel}` : '') + (mode === 'pdf' ? ' - PDF' : '');
    const printable = source.cloneNode(true);
    if (groupLabel) {
        printable.querySelectorAll('[data-worksheet-group]').forEach(section => {
            if (section.dataset.worksheetGroup !== groupLabel) section.remove();
        });
        printable.querySelector('.answer-key-page')?.remove();
    }
    const win = window.open('', '_blank', 'width=960,height=720');
    if (!win) {
        window.print();
        return;
    }

    win.opener = null;
    win.document.open();
    win.document.write(`<!doctype html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>${title.replace(/[<>]/g, '')}</title>
<style>${css}</style>
</head>
<body>${printable.outerHTML}</body>
</html>`);
    win.document.close();
    win.focus();
    setTimeout(() => {
        win.print();
        setTimeout(() => win.close(), 250);
    }, 250);
}
</script>
</body>
</html>
