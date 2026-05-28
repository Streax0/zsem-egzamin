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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .generator-shell { max-width: 1480px; margin: 0 auto; }
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
            max-width: 960px;
            margin: 0 auto;
            background:#fff;
            color:#111827;
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:2rem;
            box-shadow:0 14px 36px rgba(15,23,42,.07);
        }
        .worksheet-cover {
            border-bottom:3px solid #1d4ed8;
            padding-bottom:1rem;
            margin-bottom:1.5rem;
        }
        .worksheet-meta {
            display:grid;
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
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:1rem;
            margin-bottom:.85rem;
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
        .worksheet-question h2 { font-size:1rem; line-height:1.42; }
        .worksheet-options {
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:.5rem 1rem;
            margin-top:.75rem;
        }
        .worksheet-option {
            border:1px solid #d1d5db;
            border-radius:8px;
            padding:.55rem .7rem;
            min-height:38px;
        }
        .worksheet-open-space {
            height:92px;
            border:1px dashed #cbd5e1;
            border-radius:8px;
            margin-top:.75rem;
        }
        .answer-key-page {
            page-break-before:always;
            break-before:page;
            margin-top:2rem;
        }
        .answer-key { columns:4 140px; }
        .answer-key div { break-inside:avoid; padding:.25rem 0; }
        .worksheet-footer {
            margin-top:2rem;
            padding-top:1rem;
            border-top:1px solid #d1d5db;
            font-size:.8rem;
            color:#64748b;
        }
        .txt-format-box {
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:8px;
            padding:1rem;
        }
        .manual-q-item { border:1px solid rgba(148,163,184,.22); border-radius:8px; padding:1rem; background:#fff; }
        .manual-q-item + .manual-q-item { margin-top:.85rem; }
        .worksheet-brand-strip {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:1rem;
            color:#fff;
            background:linear-gradient(135deg,#1d4ed8,#0f172a);
            border-radius:8px;
            padding:.85rem 1rem;
            margin-bottom:1rem;
        }
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
        body.dark-mode .manual-q-item {
            background:#111827 !important;
            border-color:rgba(148,163,184,.24) !important;
            color:#e5e7eb;
        }
        body.dark-mode .worksheet-page {
            background:#fff !important;
            color:#111827 !important;
        }
        @media (max-width: 767.98px) {
            .generator-title-row { align-items:flex-start; }
            .source-method-grid { grid-template-columns:1fr; }
            .option-grid, .worksheet-options, .worksheet-meta { grid-template-columns:1fr; }
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
        .worksheet-cover { border-bottom:3px solid #1d4ed8; padding-bottom:12px; margin-bottom:18px; }
        .worksheet-brand-strip { display:flex; justify-content:space-between; align-items:center; gap:12px; color:#fff; background:linear-gradient(135deg,#1d4ed8,#0f172a); border-radius:8px; padding:10px 12px; margin-bottom:12px; }
        .worksheet-brand-mark { width:34px; height:34px; border-radius:7px; display:grid; place-items:center; background:rgba(255,255,255,.16); font-weight:900; }
        .worksheet-cover h1 { margin:0 0 4px; font-size:20pt; }
        .worksheet-cover p { margin:4px 0; }
        .text-muted, .small { color:#64748b; }
        .worksheet-meta { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:12px; font-size:10pt; }
        .worksheet-meta div { border:1px solid #dbe4f0; border-radius:6px; padding:7px 8px; min-height:32px; }
        .worksheet-question { break-inside:avoid; page-break-inside:avoid; border:1px solid #e5e7eb; border-radius:7px; padding:10px 12px; margin-bottom:10px; }
        .worksheet-group-label { display:inline-block; border-radius:999px; padding:4px 9px; background:#dbeafe; color:#1d4ed8; font-weight:800; margin:12px 0 8px; }
        .worksheet-question h2 { margin:4px 0 8px; font-size:11.5pt; line-height:1.38; }
        .worksheet-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px 12px; margin-top:8px; }
        .worksheet-option { border:1px solid #d1d5db; border-radius:6px; padding:7px 8px; min-height:28px; }
        .worksheet-open-space { height:92px; border:1px dashed #cbd5e1; border-radius:7px; margin-top:8px; }
        .answer-key-page { page-break-before:always; break-before:page; margin-top:24px; }
        .answer-key { columns:4 120px; }
        .answer-key div { break-inside:avoid; padding:3px 0; }
        .worksheet-footer { margin-top:24px; padding-top:10px; border-top:1px solid #d1d5db; font-size:9pt; color:#64748b; }
        img { max-width:100%; max-height:220px; height:auto; }
    </style>
</head>
<body>
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
                    <section class="dashboard-panel generator-panel mb-4">
                        <div class="config-section">
                            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Informacje</h5>
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <label class="form-label fw-semibold" for="title">Tytuł sprawdzianu</label>
                                    <input class="form-control" id="title" name="title" maxlength="120" placeholder="np. Test z rozdziału 3" value="<?php echo htmlspecialchars($title); ?>" required>
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label fw-semibold" for="description">Opis (opcjonalnie)</label>
                                    <input class="form-control" id="description" name="description" maxlength="220" placeholder="Krótki opis..." value="<?php echo htmlspecialchars($description); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold" for="questionCountInput">Liczba pytań</label>
                                    <input class="form-control" id="questionCountInput" name="question_count" type="number" min="1" max="120" value="<?php echo (int)$questionCount; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold" for="groupCountInput">Liczba grup</label>
                                    <input class="form-control" id="groupCountInput" name="group_count" type="number" min="1" max="10" value="<?php echo (int)$groupCount; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold" for="groupStrategy">Pytania w grupach</label>
                                    <select class="form-select" id="groupStrategy" name="group_strategy">
                                        <option value="unique" <?php echo $groupStrategy === 'unique' ? 'selected' : ''; ?>>Różne zestawy</option>
                                        <option value="rotate" <?php echo $groupStrategy === 'rotate' ? 'selected' : ''; ?>>Ten zestaw, inna kolejność</option>
                                        <option value="same" <?php echo $groupStrategy === 'same' ? 'selected' : ''; ?>>Ten sam zestaw</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold" for="difficultyLevel">Poziom</label>
                                    <select class="form-select" id="difficultyLevel" name="difficulty_level">
                                        <?php foreach ($difficultyLabels as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $difficultyLevel === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12 d-flex align-items-end">
                                    <div class="d-flex flex-wrap gap-3 pb-1">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffleQuestions" <?php echo $shuffleQuestions ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="shuffleQuestions">Mieszaj pytania</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="include_key" id="includeKey" <?php echo $includeKey ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="includeKey">Klucz odpowiedzi</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="show_explanations" id="showExplanations" <?php echo $showExplanations ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="showExplanations">Wyjaśnienia w kluczu</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="dashboard-panel generator-panel mb-4">
                        <div class="config-section">
                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                                <h5 class="fw-bold mb-0"><i class="bi bi-question-circle me-2"></i>Pytania</h5>
                                <span class="badge bg-primary rounded-pill"><?php echo count($allQuestions); ?> w puli</span>
                            </div>

                            <ul class="nav nav-tabs mb-3 border-0 gap-2" id="questionTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $generatorMode === 'db' ? 'active' : ''; ?> rounded-pill px-4 btn-outline-primary" id="db-tab" data-bs-toggle="tab" data-bs-target="#db-questions" type="button" role="tab">
                                        <i class="bi bi-database me-1"></i>Z puli pytań
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $generatorMode === 'txt' ? 'active' : ''; ?> rounded-pill px-4 btn-outline-primary" id="txt-tab" data-bs-toggle="tab" data-bs-target="#txt-questions" type="button" role="tab">
                                        <i class="bi bi-filetype-txt me-1"></i>Plik TXT
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $generatorMode === 'manual' ? 'active' : ''; ?> rounded-pill px-4 btn-outline-primary" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-questions" type="button" role="tab">
                                        <i class="bi bi-pencil-square me-1"></i>Ułóż własne pytania
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

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button class="btn btn-primary rounded-pill px-4" type="submit">
                                    <i class="bi bi-magic me-1"></i>Generuj arkusz
                                </button>
                            </div>
                        </div>
                    </section>
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

                    <article class="worksheet-page" id="worksheetPrintSource" data-print-title="<?php echo htmlspecialchars($title); ?>">
                        <header class="worksheet-cover">
                            <div class="worksheet-brand-strip">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="worksheet-brand-mark">ZT</div>
                                    <div>
                                        <div class="fw-bold">ZSEM Tech</div>
                                        <div class="small opacity-75">Sprawdzian do druku</div>
                                    </div>
                                </div>
                                <div class="small opacity-75">zsem-egzamin.online</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h1 class="h3 fw-bold mb-1"><?php echo htmlspecialchars($title); ?></h1>
                                    <?php if ($description !== ''): ?>
                                        <p class="mb-1"><?php echo htmlspecialchars($description); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted small mb-0">
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
                            <div class="worksheet-group-label"><i class="bi bi-collection"></i>Grupa <?php echo htmlspecialchars($group['label']); ?></div>
                            <?php foreach ($group['questions'] as $index => $question): ?>
                                <section class="worksheet-question">
                                    <div class="small text-muted mb-1">
                                        <?php echo htmlspecialchars((string)($question['category'] ?? 'Inne')); ?>
                                        <?php if (($question['source'] ?? '') === 'txt'): ?> · TXT<?php endif; ?>
                                    </div>
                                    <h2 class="fw-bold"><?php echo $index + 1; ?>. <?php echo htmlspecialchars($question['question_text'] ?? ''); ?></h2>
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
                            Wygenerowano dla: <?php echo htmlspecialchars($generatedFor); ?> · ZSEM Tech · <?php echo date('d.m.Y H:i'); ?>
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
    let manualCount = 0;
    const escAttr = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[char]));
    function addManualQuestion(data = {}) {
        if (!manualBox || manualCount >= 120) return;
        const n = manualCount++;
        const type = data.type === 'open' ? 'open' : 'closed';
        manualBox.insertAdjacentHTML('beforeend', `
            <div class="manual-q-item" data-manual-item>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <strong>Pytanie ${n + 1}</strong>
                    <button class="btn btn-sm btn-link text-danger" type="button" data-remove-manual><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Treść pytania</label>
                        <textarea class="form-control" rows="2" name="manual_questions[${n}][question_text]" maxlength="1400">${escAttr(data.question_text || '')}</textarea>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Kategoria</label>
                        <input class="form-control" name="manual_questions[${n}][category]" maxlength="80" value="${escAttr(data.category || 'Własne')}">
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
                        <textarea class="form-control" rows="2" name="manual_questions[${n}][explanation]" maxlength="1600">${escAttr(data.explanation || '')}</textarea>
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
});

function printWorksheet() {
    const mode = arguments[0] || 'print';
    const source = document.getElementById('worksheetPrintSource');
    if (!source) return;

    const css = document.getElementById('worksheetPrintCss')?.textContent || '';
    const title = (source.dataset.printTitle || document.title) + (mode === 'pdf' ? ' - PDF' : '');
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
<body>${source.outerHTML}</body>
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
