<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

// Only admins allowed to manage question bank
if (!isAdmin($pdo, $_SESSION['user_id'])) {
    setSessionMessage('error', 'Brak uprawnień do zarządzania bazą pytań.');
    redirect('index.php');
}

$dataDir = __DIR__ . '/../data_question/';

// Helper to get all available JSON files in data_question/
function getQuestionJsonFiles(string $dataDir): array {
    $files = glob($dataDir . '*.json');
    sort($files);
    $result = [];
    foreach ($files as $f) {
        $name = basename($f);
        $cat = 'Inne';
        if (strpos($name, 'inf02') !== false) $cat = 'INF.02 / EE.08';
        elseif (strpos($name, 'inf03') !== false) $cat = 'INF.03 / EE.09';
        elseif (strpos($name, 'inf04') !== false) $cat = 'INF.04';
        elseif (strpos($name, 'inf07') !== false) $cat = 'INF.07';
        elseif (strpos($name, 'inf08') !== false) $cat = 'INF.08';
        
        $count = 0;
        if (file_exists($f)) {
            $json = file_get_contents($f);
            if (strncmp($json, "\xEF\xBB\xBF", 3) === 0) $json = substr($json, 3);
            $arr = json_decode($json, true);
            $count = is_array($arr) ? count($arr) : 0;
        }

        $result[$name] = [
            'path' => $f,
            'filename' => $name,
            'label' => strtoupper(basename($name, '.json')) . " ($cat)",
            'count' => $count
        ];
    }
    return $result;
}

// Helper to load all questions from JSON files or specific file
function loadQuestionsFromJson(string $dataDir, string $specificFile = ''): array {
    $files = getQuestionJsonFiles($dataDir);
    $allQuestions = [];

    foreach ($files as $name => $info) {
        if ($specificFile !== '' && $name !== $specificFile) continue;

        if (file_exists($info['path'])) {
            $rawJson = file_get_contents($info['path']);
            if (strncmp($rawJson, "\xEF\xBB\xBF", 3) === 0) $rawJson = substr($rawJson, 3);
            $items = json_decode($rawJson, true);
            if (is_array($items)) {
                foreach ($items as $idx => $q) {
                    $text = trim($q['question'] ?? ($q['question_text'] ?? ''));
                    if ($text === '') continue;

                    $opts = $q['options'] ?? [];
                    $allQuestions[] = [
                        'file' => $name,
                        'index' => $idx,
                        'category' => $q['category'] ?? (strpos($name, 'inf02') !== false ? 'INF.02' : (strpos($name, 'inf03') !== false ? 'INF.03' : 'Ogólne')),
                        'question_text' => $text,
                        'option_a' => $opts['A'] ?? ($q['option_a'] ?? ''),
                        'option_b' => $opts['B'] ?? ($q['option_b'] ?? ''),
                        'option_c' => $opts['C'] ?? ($q['option_c'] ?? ''),
                        'option_d' => $opts['D'] ?? ($q['option_d'] ?? ''),
                        'correct_answer' => strtoupper($q['correct'] ?? ($q['correct_answer'] ?? 'A')),
                        'explanation' => $q['explanation'] ?? ($q['explain'] ?? ''),
                        'image_url' => $q['image'] ?? ($q['image_url'] ?? '')
                    ];
                }
            }
        }
    }
    return $allQuestions;
}

// Helper to save questions array to JSON file
function saveJsonFile(string $filePath, array $questions): bool {
    $exportData = [];
    foreach ($questions as $q) {
        $exportData[] = [
            'question' => $q['question_text'],
            'options' => [
                'A' => $q['option_a'],
                'B' => $q['option_b'],
                'C' => $q['option_c'],
                'D' => $q['option_d']
            ],
            'correct' => $q['correct_answer'],
            'image' => !empty($q['image_url']) ? $q['image_url'] : null,
            'category' => $q['category'] ?? 'INF.02',
            'explanation' => !empty($q['explanation']) ? $q['explanation'] : null
        ];
    }
    $jsonString = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents($filePath, $jsonString) !== false;
}

$availableJsonFiles = getQuestionJsonFiles($dataDir);
$selectedFile = $_GET['file'] ?? '';

// Export Handler (JSON / CSV)
if (isset($_GET['export']) && in_array($_GET['export'], ['json', 'csv'], true)) {
    $search = trim($_GET['q'] ?? '');
    $catFilter = trim($_GET['cat'] ?? '');
    $hasImgFilter = $_GET['has_image'] ?? '';
    $hasExpFilter = $_GET['has_explanation'] ?? '';

    $allJson = loadQuestionsFromJson($dataDir, $selectedFile);
    $filtered = [];

    foreach ($allJson as $q) {
        if ($search !== '') {
            $searchLower = mb_strtolower($search, 'UTF-8');
            $textMatch = mb_strpos(mb_strtolower($q['question_text'], 'UTF-8'), $searchLower) !== false
                      || mb_strpos(mb_strtolower($q['option_a'], 'UTF-8'), $searchLower) !== false
                      || mb_strpos(mb_strtolower($q['option_b'], 'UTF-8'), $searchLower) !== false
                      || mb_strpos(mb_strtolower($q['option_c'], 'UTF-8'), $searchLower) !== false
                      || mb_strpos(mb_strtolower($q['option_d'], 'UTF-8'), $searchLower) !== false
                      || mb_strpos(mb_strtolower($q['explanation'], 'UTF-8'), $searchLower) !== false;
            if (!$textMatch) continue;
        }
        if ($catFilter !== '' && $q['category'] !== $catFilter) continue;
        if ($hasImgFilter === '1' && empty($q['image_url'])) continue;
        if ($hasImgFilter === '0' && !empty($q['image_url'])) continue;
        if ($hasExpFilter === '1' && empty($q['explanation'])) continue;
        if ($hasExpFilter === '0' && !empty($q['explanation'])) continue;
        $filtered[] = $q;
    }

    $filename = 'pytania_json_' . ($selectedFile ? basename($selectedFile, '.json') : 'wszystkie') . '_' . date('Y-m-d_H-i-s');

    if ($_GET['export'] === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Plik JSON', 'Kategoria', 'Treść pytania', 'Opcja A', 'Opcja B', 'Opcja C', 'Opcja D', 'Poprawna', 'Wyjaśnienie', 'URL Obrazka']);
        foreach ($filtered as $qRow) {
            fputcsv($output, [
                $qRow['file'],
                $qRow['category'],
                $qRow['question_text'],
                $qRow['option_a'],
                $qRow['option_b'],
                $qRow['option_c'],
                $qRow['option_d'],
                $qRow['correct_answer'],
                $qRow['explanation'],
                $qRow['image_url']
            ]);
        }
        fclose($output);
        exit;
    }
}

// POST Action Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token, 'manage_questions')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('manage_questions.php');
    }

    $action = $_POST['action'] ?? '';
    $targetFile = trim($_POST['target_file'] ?? 'inf02.json');
    if (!isset($availableJsonFiles[$targetFile])) {
        $targetFile = 'inf02.json';
    }
    $targetPath = $dataDir . $targetFile;

    $currentQuestions = [];
    if (file_exists($targetPath)) {
        $raw = file_get_contents($targetPath);
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) $raw = substr($raw, 3);
        $currentQuestions = json_decode($raw, true) ?? [];
    }

    $data = [
        'category' => trim($_POST['category'] ?? 'INF.02'),
        'question_text' => trim($_POST['question_text'] ?? ''),
        'option_a' => trim($_POST['option_a'] ?? ''),
        'option_b' => trim($_POST['option_b'] ?? ''),
        'option_c' => trim($_POST['option_c'] ?? ''),
        'option_d' => trim($_POST['option_d'] ?? ''),
        'correct_answer' => strtoupper(trim($_POST['correct_answer'] ?? 'A')),
        'explanation' => trim($_POST['explanation'] ?? ''),
        'image_url' => trim($_POST['image_url'] ?? '')
    ];

    switch ($action) {
        case 'add':
            if (empty($data['question_text'])) {
                setSessionMessage('error', 'Treść pytania nie może być pusta.');
            } else {
                $newEntry = [
                    'question' => $data['question_text'],
                    'options' => [
                        'A' => $data['option_a'],
                        'B' => $data['option_b'],
                        'C' => $data['option_c'],
                        'D' => $data['option_d']
                    ],
                    'correct' => $data['correct_answer'],
                    'image' => !empty($data['image_url']) ? $data['image_url'] : null,
                    'category' => $data['category'],
                    'explanation' => !empty($data['explanation']) ? $data['explanation'] : null
                ];
                array_unshift($currentQuestions, $newEntry);
                if (saveJsonFile($targetPath, array_map(function($q) {
                    return [
                        'question_text' => $q['question'] ?? ($q['question_text'] ?? ''),
                        'option_a' => $q['options']['A'] ?? ($q['option_a'] ?? ''),
                        'option_b' => $q['options']['B'] ?? ($q['option_b'] ?? ''),
                        'option_c' => $q['options']['C'] ?? ($q['option_c'] ?? ''),
                        'option_d' => $q['options']['D'] ?? ($q['option_d'] ?? ''),
                        'correct_answer' => $q['correct'] ?? ($q['correct_answer'] ?? 'A'),
                        'image_url' => $q['image'] ?? ($q['image_url'] ?? ''),
                        'category' => $q['category'] ?? 'INF.02',
                        'explanation' => $q['explanation'] ?? ''
                    ];
                }, $currentQuestions))) {
                    // Sync DB
                    addQuestion($pdo, $data);
                    setSessionMessage('success', "Dodano pytanie do pliku $targetFile i bazy danych.");
                } else {
                    setSessionMessage('error', "Nie udało się zapisać w pliku $targetFile.");
                }
            }
            break;

        case 'edit':
            $origFile = trim($_POST['orig_file'] ?? $targetFile);
            $origIdx = isset($_POST['orig_index']) ? (int)$_POST['orig_index'] : -1;
            $origPath = $dataDir . $origFile;

            if (file_exists($origPath) && $origIdx >= 0) {
                $rawOrig = file_get_contents($origPath);
                if (strncmp($rawOrig, "\xEF\xBB\xBF", 3) === 0) $rawOrig = substr($rawOrig, 3);
                $origList = json_decode($rawOrig, true) ?? [];

                if (isset($origList[$origIdx])) {
                    $origList[$origIdx] = [
                        'question' => $data['question_text'],
                        'options' => [
                            'A' => $data['option_a'],
                            'B' => $data['option_b'],
                            'C' => $data['option_c'],
                            'D' => $data['option_d']
                        ],
                        'correct' => $data['correct_answer'],
                        'image' => !empty($data['image_url']) ? $data['image_url'] : null,
                        'category' => $data['category'],
                        'explanation' => !empty($data['explanation']) ? $data['explanation'] : null
                    ];
                    
                    $normalizedList = array_map(function($q) {
                        return [
                            'question_text' => $q['question'] ?? ($q['question_text'] ?? ''),
                            'option_a' => $q['options']['A'] ?? ($q['option_a'] ?? ''),
                            'option_b' => $q['options']['B'] ?? ($q['option_b'] ?? ''),
                            'option_c' => $q['options']['C'] ?? ($q['option_c'] ?? ''),
                            'option_d' => $q['options']['D'] ?? ($q['option_d'] ?? ''),
                            'correct_answer' => $q['correct'] ?? ($q['correct_answer'] ?? 'A'),
                            'image_url' => $q['image'] ?? ($q['image_url'] ?? ''),
                            'category' => $q['category'] ?? 'INF.02',
                            'explanation' => $q['explanation'] ?? ''
                        ];
                    }, $origList);

                    if (saveJsonFile($origPath, $normalizedList)) {
                        setSessionMessage('success', "Zaktualizowano pytanie w pliku $origFile.");
                    } else {
                        setSessionMessage('error', "Błąd zapisu pliku $origFile.");
                    }
                }
            }
            break;

        case 'delete':
            $delFile = trim($_POST['orig_file'] ?? $targetFile);
            $delIdx = isset($_POST['orig_index']) ? (int)$_POST['orig_index'] : -1;
            $delPath = $dataDir . $delFile;

            if (file_exists($delPath) && $delIdx >= 0) {
                $rawDel = file_get_contents($delPath);
                if (strncmp($rawDel, "\xEF\xBB\xBF", 3) === 0) $rawDel = substr($rawDel, 3);
                $delList = json_decode($rawDel, true) ?? [];

                if (isset($delList[$delIdx])) {
                    array_splice($delList, $delIdx, 1);
                    $normalizedList = array_map(function($q) {
                        return [
                            'question_text' => $q['question'] ?? ($q['question_text'] ?? ''),
                            'option_a' => $q['options']['A'] ?? ($q['option_a'] ?? ''),
                            'option_b' => $q['options']['B'] ?? ($q['option_b'] ?? ''),
                            'option_c' => $q['options']['C'] ?? ($q['option_c'] ?? ''),
                            'option_d' => $q['options']['D'] ?? ($q['option_d'] ?? ''),
                            'correct_answer' => $q['correct'] ?? ($q['correct_answer'] ?? 'A'),
                            'image_url' => $q['image'] ?? ($q['image_url'] ?? ''),
                            'category' => $q['category'] ?? 'INF.02',
                            'explanation' => $q['explanation'] ?? ''
                        ];
                    }, $delList);

                    if (saveJsonFile($delPath, $normalizedList)) {
                        setSessionMessage('success', "Usunięto pytanie z pliku $delFile.");
                    } else {
                        setSessionMessage('error', "Błąd zapisu pliku $delFile.");
                    }
                }
            }
            break;

        case 'sync_to_db':
            $allQuestions = loadQuestionsFromJson($dataDir);
            $syncedCount = 0;
            $errorCount = 0;

            try {
                $pdo->beginTransaction();
                $pdo->exec("TRUNCATE TABLE questions");
                $stmt = $pdo->prepare("
                    INSERT INTO questions (category, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($allQuestions as $q) {
                    $res = $stmt->execute([
                        $q['category'],
                        $q['question_text'],
                        $q['option_a'],
                        $q['option_b'],
                        $q['option_c'],
                        $q['option_d'],
                        $q['correct_answer'],
                        !empty($q['explanation']) ? $q['explanation'] : null,
                        !empty($q['image_url']) ? $q['image_url'] : null
                    ]);
                    if ($res) $syncedCount++;
                    else $errorCount++;
                }
                $pdo->commit();
                setSessionMessage('success', "Pomyślnie zsynchronizowano $syncedCount pytań z plików JSON do bazy SQL.");
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setSessionMessage('error', "Błąd podczas synchronizacji z bazą: " . $ex->getMessage());
            }
            break;
    }

    $redirectUrl = 'manage_questions.php';
    $queryParams = [];
    if (!empty($_GET['file'])) $queryParams['file'] = $_GET['file'];
    if (!empty($_GET['q'])) $queryParams['q'] = $_GET['q'];
    if (!empty($_GET['cat'])) $queryParams['cat'] = $_GET['cat'];
    if (!empty($_GET['has_image'])) $queryParams['has_image'] = $_GET['has_image'];
    if (!empty($_GET['has_explanation'])) $queryParams['has_explanation'] = $_GET['has_explanation'];
    if (!empty($_GET['page'])) $queryParams['page'] = $_GET['page'];
    if (!empty($queryParams)) $redirectUrl .= '?' . http_build_query($queryParams);
    redirect($redirectUrl);
}

// Load and Filter JSON Questions
$allJsonQuestions = loadQuestionsFromJson($dataDir, $selectedFile);

$search = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['cat'] ?? '');
$hasImgFilter = $_GET['has_image'] ?? '';
$hasExpFilter = $_GET['has_explanation'] ?? '';

$filteredQuestions = [];
$statTotal = count($allJsonQuestions);
$statWithImg = 0;
$statWithExp = 0;
$categoriesSet = [];

foreach ($allJsonQuestions as $q) {
    if (!empty($q['image_url'])) $statWithImg++;
    if (!empty($q['explanation'])) $statWithExp++;
    if (!empty($q['category'])) $categoriesSet[$q['category']] = true;

    if ($search !== '') {
        $searchLower = mb_strtolower($search, 'UTF-8');
        $textMatch = mb_strpos(mb_strtolower($q['question_text'], 'UTF-8'), $searchLower) !== false
                  || mb_strpos(mb_strtolower($q['option_a'], 'UTF-8'), $searchLower) !== false
                  || mb_strpos(mb_strtolower($q['option_b'], 'UTF-8'), $searchLower) !== false
                  || mb_strpos(mb_strtolower($q['option_c'], 'UTF-8'), $searchLower) !== false
                  || mb_strpos(mb_strtolower($q['option_d'], 'UTF-8'), $searchLower) !== false
                  || mb_strpos(mb_strtolower($q['explanation'], 'UTF-8'), $searchLower) !== false;
        if (!$textMatch) continue;
    }
    if ($catFilter !== '' && $q['category'] !== $catFilter) continue;
    if ($hasImgFilter === '1' && empty($q['image_url'])) continue;
    if ($hasImgFilter === '0' && !empty($q['image_url'])) continue;
    if ($hasExpFilter === '1' && empty($q['explanation'])) continue;
    if ($hasExpFilter === '0' && !empty($q['explanation'])) continue;

    $filteredQuestions[] = $q;
}

$categoriesList = array_keys($categoriesSet);
sort($categoriesList);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$totalQuestions = count($filteredQuestions);
$totalPages = max(1, (int)ceil($totalQuestions / $limit));
$offset = ($page - 1) * $limit;
$pageQuestions = array_slice($filteredQuestions, $offset, $limit);

$rawFlash = getSessionMessage();
$flashMessage = $rawFlash['message'] ?? '';
$flashType = $rawFlash['type'] ?? 'info';

$pageTitle = 'Zarządzanie Bazą Pytań JSON - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
    /* KPI Cards Light Mode Default */
    .kpi-card-questions {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.15rem 1.35rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
    }
    .kpi-card-questions:hover {
        border-color: #6366f1;
        box-shadow: 0 8px 22px rgba(99, 102, 241, 0.15);
        transform: translateY(-2px);
    }
    .kpi-card-questions .kpi-num {
        color: #0f172a !important;
        font-weight: 900;
    }
    .kpi-card-questions .kpi-label {
        color: #64748b !important;
        font-weight: 700;
        font-size: 0.82rem;
    }

    /* KPI Cards Dark Mode */
    [data-bs-theme="dark"] .kpi-card-questions,
    body.dark-theme .kpi-card-questions {
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(30, 41, 59, 0.85) 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    }
    [data-bs-theme="dark"] .kpi-card-questions .kpi-num,
    body.dark-theme .kpi-card-questions .kpi-num {
        color: #ffffff !important;
    }
    [data-bs-theme="dark"] .kpi-card-questions .kpi-label,
    body.dark-theme .kpi-card-questions .kpi-label {
        color: #94a3b8 !important;
    }

    /* Badges High Contrast */
    .badge-cat-tag {
        background: rgba(99, 102, 241, 0.12) !important;
        color: #4338ca !important;
        border: 1px solid rgba(99, 102, 241, 0.3) !important;
        font-weight: 800 !important;
        font-size: 0.78rem !important;
        padding: 0.35rem 0.65rem !important;
        border-radius: 6px !important;
    }
    .badge-file-pill {
        font-family: monospace;
        font-size: 0.75rem !important;
        padding: 0.3rem 0.6rem !important;
        border-radius: 6px !important;
        background: rgba(148, 163, 184, 0.16) !important;
        color: #334155 !important;
        border: 1px solid rgba(148, 163, 184, 0.3) !important;
        font-weight: 700 !important;
    }
    .badge-correct-pill {
        background: rgba(16, 185, 129, 0.15) !important;
        color: #047857 !important;
        border: 1px solid rgba(16, 185, 129, 0.35) !important;
        font-weight: 800 !important;
        font-size: 0.8rem !important;
        padding: 0.35rem 0.7rem !important;
        border-radius: 6px !important;
    }
    .badge-img-pill {
        background: rgba(6, 182, 212, 0.12) !important;
        color: #0e7490 !important;
        border: 1px solid rgba(6, 182, 212, 0.3) !important;
        font-weight: 700 !important;
        font-size: 0.74rem !important;
    }
    .badge-exp-pill {
        background: rgba(16, 185, 129, 0.12) !important;
        color: #047857 !important;
        border: 1px solid rgba(16, 185, 129, 0.3) !important;
        font-weight: 700 !important;
        font-size: 0.74rem !important;
    }

    [data-bs-theme="dark"] .badge-cat-tag, body.dark-theme .badge-cat-tag { color: #a5b4fc !important; }
    [data-bs-theme="dark"] .badge-file-pill, body.dark-theme .badge-file-pill { color: #cbd5e1 !important; }
    [data-bs-theme="dark"] .badge-correct-pill, body.dark-theme .badge-correct-pill { color: #34d399 !important; }
    [data-bs-theme="dark"] .badge-img-pill, body.dark-theme .badge-img-pill { color: #22d3ee !important; }
    [data-bs-theme="dark"] .badge-exp-pill, body.dark-theme .badge-exp-pill { color: #34d399 !important; }

    .question-text-truncate {
        max-width: 400px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 600;
    }

    .questions-table-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.35rem;
        flex-wrap: nowrap;
    }
    .questions-table-actions .btn {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .question-editor-modal .modal-content,
    #previewQuestionModal .modal-content {
        background: #0f172a;
        color: #f8fafc;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 20px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.6);
    }
    .question-editor-modal .form-label,
    #previewQuestionModal h5 {
        color: #ffffff;
    }
    .question-editor-modal .form-control,
    .question-editor-modal .form-select,
    .question-editor-modal textarea {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #ffffff;
        border-radius: 10px;
    }
    .question-editor-modal .form-control:focus,
    .question-editor-modal .form-select:focus {
        background: rgba(15, 23, 42, 0.95);
        border-color: #6366f1;
        box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        color: #ffffff;
    }
    .question-editor-modal option {
        background: #0f172a;
        color: #ffffff;
    }
    .question-preview-box {
        background: rgba(15, 23, 42, 0.65);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 14px;
        padding: 1.25rem;
    }
    .option-pill {
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.04);
        border-radius: 10px;
        padding: 0.75rem 1rem;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #e2e8f0;
    }
    .option-pill.is-correct {
        border-color: rgba(16, 185, 129, 0.5);
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        font-weight: 700;
    }
    @media (max-width: 767.98px) {
        .question-text-truncate {
            max-width: none;
            white-space: normal;
            overflow: visible;
        }
        .questions-table-actions {
            justify-content: flex-start;
        }
    }
</style>
HTML;
include '../includes/header.php';
?>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>

        <main role="main" class="content-body">
            <div class="container-fluid p-0">
                
                <!-- Page Header -->
                <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-filetype-json text-primary me-2"></i>Zarządzanie Bazą Pytań JSON</h2>
                        <p class="text-muted mb-0">Zarządzaj pytaniami bezpośrednio w plikach JSON (<code>data_question/*.json</code>) i synchronizuj je z bazą danych.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <form method="POST" class="d-inline" onsubmit="return appConfirmSubmit(this, 'Czy chcesz zastąpić bazę danych SQL pytaniami z plików JSON?');">
                            <?php echo csrfTokenField('manage_questions'); ?>
                            <input type="hidden" name="action" value="sync_to_db">
                            <button type="submit" class="btn btn-outline-success rounded-pill px-3">
                                <i class="bi bi-arrow-repeat me-1"></i>Sync JSON &rarr; SQL DB
                            </button>
                        </form>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary rounded-pill px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-download me-1"></i>Eksportuj
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="?export=json<?= $selectedFile ? '&file='.urlencode($selectedFile) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $catFilter ? '&cat='.urlencode($catFilter) : '' ?>">
                                        <i class="bi bi-filetype-json text-warning"></i> Pobierz JSON
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="?export=csv<?= $selectedFile ? '&file='.urlencode($selectedFile) : '' ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $catFilter ? '&cat='.urlencode($catFilter) : '' ?>">
                                        <i class="bi bi-filetype-csv text-success"></i> Pobierz CSV
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                            <i class="bi bi-plus-lg me-1"></i>Dodaj pytanie
                        </button>
                    </div>
                </div>

                <?php if (!empty($flashMessage)): ?>
                    <div class="alert alert-<?php echo ($flashType === 'error') ? 'danger' : ($flashType === 'success' ? 'success' : 'info'); ?> border-0 shadow-sm animate-in mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i><?php echo htmlspecialchars($flashMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- KPI Stats Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="kpi-card-questions d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary fs-3">
                                <i class="bi bi-patch-question-fill"></i>
                            </div>
                            <div>
                                <div class="fs-4 kpi-num"><?= number_format($statTotal, 0, '', ' ') ?></div>
                                <div class="kpi-label">Pytania w JSON</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card-questions d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 bg-info bg-opacity-10 text-info fs-3">
                                <i class="bi bi-file-earmark-code"></i>
                            </div>
                            <div>
                                <div class="fs-4 kpi-num"><?= count($availableJsonFiles) ?></div>
                                <div class="kpi-label">Pliki JSON</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card-questions d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning fs-3">
                                <i class="bi bi-image"></i>
                            </div>
                            <div>
                                <div class="fs-4 kpi-num"><?= number_format($statWithImg, 0, '', ' ') ?></div>
                                <div class="kpi-label">Z rysunkami</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card-questions d-flex align-items-center gap-3">
                            <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success fs-3">
                                <i class="bi bi-card-text"></i>
                            </div>
                            <div>
                                <div class="fs-4 kpi-num"><?= number_format($statWithExp, 0, '', ' ') ?></div>
                                <div class="kpi-label">Z wyjaśnieniem</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- JSON Files Quick Badges Bar -->
                <div class="dashboard-panel mb-4 p-3 animate-in">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small fw-bold text-muted me-2"><i class="bi bi-folder-fill text-warning me-1"></i>Pliki JSON:</span>
                        <a href="manage_questions.php" class="btn btn-sm <?= $selectedFile === '' ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
                            Wszystkie (<?= $statTotal ?>)
                        </a>
                        <?php foreach ($availableJsonFiles as $fName => $fInfo): ?>
                            <a href="?file=<?= urlencode($fName) ?>" class="btn btn-sm <?= $selectedFile === $fName ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
                                <?= htmlspecialchars($fInfo['filename']) ?> <span class="badge bg-white bg-opacity-20 ms-1"><?= $fInfo['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Search & Filters Panel -->
                <div class="dashboard-panel mb-4 animate-in">
                    <form method="GET" class="row g-3 align-items-end">
                        <?php if ($selectedFile): ?>
                            <input type="hidden" name="file" value="<?= htmlspecialchars($selectedFile) ?>">
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">Szukaj w treści lub wyjaśnieniu</label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control border-start-0" placeholder="Wpisz szukaną frazę..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted">Kategoria CKE</label>
                            <select name="cat" class="form-select">
                                <option value="">Wszystkie kategorie</option>
                                <?php foreach ($categoriesList as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $catFilter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Obrazek</label>
                            <select name="has_image" class="form-select">
                                <option value="">Wszystkie</option>
                                <option value="1" <?= $hasImgFilter === '1' ? 'selected' : '' ?>>Z obrazkiem</option>
                                <option value="0" <?= $hasImgFilter === '0' ? 'selected' : '' ?>>Bez obrazka</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Wyjaśnienie</label>
                            <select name="has_explanation" class="form-select">
                                <option value="">Wszystkie</option>
                                <option value="1" <?= $hasExpFilter === '1' ? 'selected' : '' ?>>Z wyjaśnieniem</option>
                                <option value="0" <?= $hasExpFilter === '0' ? 'selected' : '' ?>>Bez wyjaśnienia</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex gap-2">
                            <button class="btn btn-primary w-100" type="submit" title="Filtruj"><i class="bi bi-funnel"></i></button>
                            <?php if ($search || $catFilter || $hasImgFilter !== '' || $hasExpFilter !== ''): ?>
                                <a href="manage_questions.php<?= $selectedFile ? '?file='.urlencode($selectedFile) : '' ?>" class="btn btn-outline-secondary" title="Resetuj filtry"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Questions Table -->
                <div class="dashboard-panel p-0 overflow-hidden animate-in questions-table-panel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 questions-table">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Treść pytania</th>
                                    <th>Kategoria</th>
                                    <th>Plik JSON</th>
                                    <th>Poprawna</th>
                                    <th class="text-end pe-4">Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pageQuestions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-search fs-2 d-block mb-2 text-muted opacity-50"></i>
                                            Brak pytań spełniających kryteria wyszukiwania.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($pageQuestions as $q): ?>
                                <tr>
                                    <td class="ps-4" data-label="Treść pytania">
                                        <div class="question-text-truncate fw-medium" title="<?php echo htmlspecialchars($q['question_text']); ?>">
                                            <?php echo htmlspecialchars($q['question_text']); ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <?php if (!empty($q['image_url'])): ?>
                                                <span class="badge badge-img-pill"><i class="bi bi-image me-1"></i>Obrazek</span>
                                            <?php endif; ?>
                                            <?php if (!empty($q['explanation'])): ?>
                                                <span class="badge badge-exp-pill"><i class="bi bi-card-text me-1"></i>Wyjaśnienie</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Kategoria">
                                        <span class="badge badge-cat-tag">
                                            <?php echo htmlspecialchars($q['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-file-pill"><?= htmlspecialchars($q['file']) ?></span>
                                    </td>
                                    <td data-label="Poprawna">
                                        <span class="badge badge-correct-pill">
                                            Odpowiedź <?php echo $q['correct_answer']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4" data-label="Akcje">
                                        <div class="d-flex justify-content-end gap-1 questions-table-actions">
                                            <button type="button" class="btn btn-outline-info btn-sm rounded-circle preview-btn"
                                                    data-question='<?php echo json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    title="Podgląd pytania"
                                                    style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm rounded-circle edit-btn" 
                                                    data-question='<?php echo json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    title="Edytuj w JSON"
                                                    style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle duplicate-btn"
                                                    data-question='<?php echo json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    title="Duplikuj pytanie"
                                                    style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;">
                                                <i class="bi bi-copy"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return appConfirmSubmit(this, 'Czy na pewno chcesz usunąć to pytanie z pliku JSON?');">
                                                <?php echo csrfTokenField('manage_questions'); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="orig_file" value="<?= htmlspecialchars($q['file']) ?>">
                                                <input type="hidden" name="orig_index" value="<?= $q['index'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle"
                                                        title="Usuń pytanie"
                                                        style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?file=<?= urlencode($selectedFile) ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($catFilter) ?>&has_image=<?= $hasImgFilter ?>&has_explanation=<?= $hasExpFilter ?>&page=<?= $page - 1 ?>">&laquo;</a>
                            </li>
                        <?php endif; ?>
                        <?php 
                        $startP = max(1, $page - 3);
                        $endP = min($totalPages, $page + 3);
                        for ($p = $startP; $p <= $endP; $p++): 
                        ?>
                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?file=<?= urlencode($selectedFile) ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($catFilter) ?>&has_image=<?= $hasImgFilter ?>&has_explanation=<?= $hasExpFilter ?>&page=<?= $p ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?file=<?= urlencode($selectedFile) ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($catFilter) ?>&has_image=<?= $hasImgFilter ?>&has_explanation=<?= $hasExpFilter ?>&page=<?= $page + 1 ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade question-editor-modal" id="addQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Dodaj nowe pytanie do pliku JSON</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php echo csrfTokenField('manage_questions'); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Docelowy plik JSON</label>
                        <select name="target_file" id="add_target_file" class="form-select" required>
                            <?php foreach ($availableJsonFiles as $fName => $fInfo): ?>
                                <option value="<?= htmlspecialchars($fName) ?>" <?= ($selectedFile === $fName || ($selectedFile === '' && $fName === 'inf02.json')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fInfo['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Kategoria CKE</label>
                        <select name="category" id="add_category" class="form-select" required>
                            <option value="INF.02">INF.02 (EE.08)</option>
                            <option value="INF.03">INF.03 (EE.09)</option>
                            <option value="INF.04">INF.04</option>
                            <option value="INF.07">INF.07</option>
                            <option value="INF.08">INF.08</option>
                            <option value="Ogólne">Ogólne</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Poprawna odpowiedź</label>
                        <select name="correct_answer" id="add_correct" class="form-select" required>
                            <option value="A">Odpowiedź A</option>
                            <option value="B">Odpowiedź B</option>
                            <option value="C">Odpowiedź C</option>
                            <option value="D">Odpowiedź D</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Treść pytania</label>
                        <textarea name="question_text" id="add_text" class="form-control" rows="3" placeholder="Wpisz treść pytania..." required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja A</label>
                        <input type="text" name="option_a" id="add_a" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja B</label>
                        <input type="text" name="option_b" id="add_b" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja C</label>
                        <input type="text" name="option_c" id="add_c" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja D</label>
                        <input type="text" name="option_d" id="add_d" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">URL obrazka (opcjonalnie)</label>
                        <input type="text" name="image_url" id="add_image" class="form-control" placeholder="https://www.praktycznyegzamin.pl/ee08/...">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Wyjaśnienie (opcjonalnie)</label>
                        <textarea name="explanation" id="add_explanation" class="form-control" rows="2" placeholder="Uzasadnienie poprawnej odpowiedzi..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-check-lg me-1"></i>Zapisz w pliku JSON</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade question-editor-modal" id="editQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edytuj pytanie w JSON</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php echo csrfTokenField('manage_questions'); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="orig_file" id="edit_orig_file">
                <input type="hidden" name="orig_index" id="edit_orig_index">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Kategoria</label>
                        <input type="text" name="category" id="edit_category" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Poprawna odpowiedź</label>
                        <select name="correct_answer" id="edit_correct" class="form-select" required>
                            <option value="A">Odpowiedź A</option>
                            <option value="B">Odpowiedź B</option>
                            <option value="C">Odpowiedź C</option>
                            <option value="D">Odpowiedź D</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Treść pytania</label>
                        <textarea name="question_text" id="edit_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja A</label>
                        <input type="text" name="option_a" id="edit_a" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja B</label>
                        <input type="text" name="option_b" id="edit_b" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja C</label>
                        <input type="text" name="option_c" id="edit_c" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Opcja D</label>
                        <input type="text" name="option_d" id="edit_d" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">URL obrazka (opcjonalnie)</label>
                        <input type="text" name="image_url" id="edit_image" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Wyjaśnienie (opcjonalnie)</label>
                        <textarea name="explanation" id="edit_explanation" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-save me-1"></i>Zapisz zmiany w JSON</button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Question Modal -->
<div class="modal fade" id="previewQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye-fill text-info me-2"></i>Podgląd pytania CKE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span id="prevCategory" class="badge bg-primary fs-6"></span>
                    <span id="prevFile" class="file-badge-pill"></span>
                </div>
                <div class="question-preview-box mb-4">
                    <h5 id="prevText" class="fw-bold mb-3"></h5>
                    <div id="prevImgWrap" class="mb-3 d-none text-center">
                        <img id="prevImg" src="" alt="Rysunek do pytania" class="img-fluid rounded border" style="max-height: 300px;">
                    </div>
                    <div class="options-list">
                        <div id="prevOptA" class="option-pill"><strong>A.</strong> <span></span></div>
                        <div id="prevOptB" class="option-pill"><strong>B.</strong> <span></span></div>
                        <div id="prevOptC" class="option-pill"><strong>C.</strong> <span></span></div>
                        <div id="prevOptD" class="option-pill"><strong>D.</strong> <span></span></div>
                    </div>
                </div>
                <div id="prevExpWrap" class="alert alert-info border-0 rounded-3 d-none mb-0">
                    <strong><i class="bi bi-lightbulb-fill me-1"></i>Wyjaśnienie:</strong>
                    <div id="prevExpText" class="mt-1"></div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Preview Event Handler
    document.querySelectorAll('.preview-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const q = JSON.parse(btn.getAttribute('data-question'));
            document.getElementById('prevCategory').textContent = q.category;
            document.getElementById('prevFile').textContent = q.file;
            document.getElementById('prevText').textContent = q.question_text;
            
            ['A', 'B', 'C', 'D'].forEach(letter => {
                const optEl = document.getElementById('prevOpt' + letter);
                optEl.querySelector('span').textContent = q['option_' + letter.toLowerCase()];
                optEl.classList.toggle('is-correct', q.correct_answer === letter);
            });

            const imgWrap = document.getElementById('prevImgWrap');
            const imgEl = document.getElementById('prevImg');
            if (q.image_url) {
                imgEl.src = (q.image_url.startsWith('http') || q.image_url.startsWith('/')) ? q.image_url : ('../' + q.image_url);
                imgWrap.classList.remove('d-none');
            } else {
                imgWrap.classList.add('d-none');
            }

            const expWrap = document.getElementById('prevExpWrap');
            const expText = document.getElementById('prevExpText');
            if (q.explanation) {
                expText.textContent = q.explanation;
                expWrap.classList.remove('d-none');
            } else {
                expWrap.classList.add('d-none');
            }

            const modal = new bootstrap.Modal(document.getElementById('previewQuestionModal'));
            modal.show();
        });
    });

    // Edit Event Handler
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const q = JSON.parse(btn.getAttribute('data-question'));
            document.getElementById('edit_orig_file').value = q.file;
            document.getElementById('edit_orig_index').value = q.index;
            document.getElementById('edit_category').value = q.category;
            document.getElementById('edit_text').value = q.question_text;
            document.getElementById('edit_a').value = q.option_a;
            document.getElementById('edit_b').value = q.option_b;
            document.getElementById('edit_c').value = q.option_c;
            document.getElementById('edit_d').value = q.option_d;
            document.getElementById('edit_correct').value = q.correct_answer;
            document.getElementById('edit_image').value = q.image_url || '';
            document.getElementById('edit_explanation').value = q.explanation || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
            modal.show();
        });
    });

    // Duplicate Event Handler
    document.querySelectorAll('.duplicate-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const q = JSON.parse(btn.getAttribute('data-question'));
            document.getElementById('add_target_file').value = q.file;
            document.getElementById('add_category').value = q.category;
            document.getElementById('add_text').value = q.question_text + ' (Kopia)';
            document.getElementById('add_a').value = q.option_a;
            document.getElementById('add_b').value = q.option_b;
            document.getElementById('add_c').value = q.option_c;
            document.getElementById('add_d').value = q.option_d;
            document.getElementById('add_correct').value = q.correct_answer;
            document.getElementById('add_image').value = q.image_url || '';
            document.getElementById('add_explanation').value = q.explanation || '';
            
            const modal = new bootstrap.Modal(document.getElementById('addQuestionModal'));
            modal.show();
        });
    });
});
</script>
</body>
</html>
