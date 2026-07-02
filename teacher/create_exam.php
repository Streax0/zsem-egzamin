<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    setSessionMessage('error', 'Brak uprawnień.');
    redirect('../index.php');
}

$userId = $_SESSION['user_id'];

// Load categories & questions for selection
$allQuestions = loadQuestions($pdo, false);
$allQuestions = array_values(array_filter($allQuestions, static fn($q) => !isInternalQuestionCategory($q['category'] ?? '')));
$categories = array_unique(array_column($allQuestions, 'category'));
sort($categories);
$categoryCounts = [];
foreach ($allQuestions as $question) {
    $cat = $question['category'] ?? 'Inne';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}

// Load teacher's custom exams from JSON files
$customDir = __DIR__ . '/../data/custom_tests';
$customExams = [];
if (is_dir($customDir)) {
    foreach (glob($customDir . '/*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($data['teacher_id'] ?? 0) == $userId) {
            $data['_filename'] = basename($file);
            $data['q_count'] = count($data['questions'] ?? []);
            $customExams[] = $data;
        }
    }
}

// Pre-select custom exam if passed via GET
$preselectedCustom = basename($_GET['custom_file'] ?? '');
$preselectedExam = null;
foreach ($customExams as $customExam) {
    if ($preselectedCustom !== '' && $customExam['_filename'] === $preselectedCustom) {
        $preselectedExam = $customExam;
        break;
    }
}
$preselectedExamData = is_array($preselectedExam) ? $preselectedExam : [];
$defaultTitle = $preselectedExamData['title'] ?? '';
$defaultDescription = $preselectedExamData['description'] ?? '';
$defaultQuestionCount = max(1, min(120, (int)($preselectedExamData['q_count'] ?? 40)));
$defaultTotalTime = (int)($preselectedExamData['time_limit'] ?? 0);
$defaultPassThreshold = max(0, min(100, (int)($preselectedExamData['pass_threshold'] ?? 50)));
$rawDefaultDifficulty = (string)($preselectedExamData['difficulty'] ?? 'mixed');
$defaultDifficulty = in_array($rawDefaultDifficulty, ['easy','medium','hard','mixed'], true) ? $rawDefaultDifficulty : 'mixed';
$defaultShuffleQuestions = !array_key_exists('shuffle_questions', $preselectedExamData) || !empty($preselectedExamData['shuffle_questions']);
$defaultShuffleAnswers = !empty($preselectedExamData['shuffle_answers']);
$defaultShowAnswers = !empty($preselectedExamData['show_answers_after']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('create_exam.php');
    }

    // Collect settings
    $title = trim($_POST['title'] ?? 'Sprawdzian');
    $description = trim($_POST['description'] ?? '');
    $questionCount = max(1, min(120, (int)($_POST['question_count'] ?? 40)));
    $maxParticipants = max(1, min(36, (int)($_POST['max_participants'] ?? 36)));
    $timePerQuestion = !empty($_POST['time_per_question']) ? (int)$_POST['time_per_question'] : null;
    $totalTime = !empty($_POST['total_time']) ? (int)$_POST['total_time'] : null;
    $shuffleQuestions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $shuffleAnswers = isset($_POST['shuffle_answers']) ? 1 : 0;
    $selectedCategories = $_POST['categories'] ?? [];
    $difficultyLevel = $_POST['difficulty_level'] ?? 'mixed';
    $examMode = isset($_POST['exam_mode']) ? 1 : 0;
    $autoFinish = isset($_POST['auto_finish']) ? 1 : 0;
    $allowRejoin = isset($_POST['allow_rejoin']) ? 1 : 0;
    $antiCheat = isset($_POST['anti_cheat']) ? 1 : 0;
    $aiCopyGuard = isset($_POST['ai_copy_guard']);
    $blockTabSwitch = isset($_POST['block_tab_switch']) ? 1 : 0;
    $requireFullscreen = isset($_POST['require_fullscreen']) ? 1 : 0;
    if (!$antiCheat) {
        $blockTabSwitch = 0;
        $requireFullscreen = 0;
    }
    $lobbyEnabled = isset($_POST['lobby_enabled']) ? 1 : 0;
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    $showGrade = isset($_POST['show_grade']) ? 1 : 0;
    $showCorrectAnswers = isset($_POST['show_correct_answers']) ? 1 : 0;
    if ($showCorrectAnswers || $showGrade) {
        $showResults = 1;
    }
    $randomizePerStudent = isset($_POST['randomize_per_student']) ? 1 : 0;
    $lockAfterFinish = isset($_POST['lock_after_finish']) ? 1 : 0;
    $passThreshold = max(0, min(100, (int)($_POST['pass_threshold'] ?? 50)));
    $maxAttempts = max(1, min(5, (int)($_POST['max_attempts'] ?? 1)));
    $navigationMode = in_array($_POST['navigation_mode'] ?? 'free', ['free','linear'], true) ? $_POST['navigation_mode'] : 'free';
    $allowAnswerChanges = isset($_POST['allow_answer_changes']) ? 1 : 0;
    $warningLimit = ($_POST['warning_limit'] ?? '') !== '' ? max(1, min(10, (int)$_POST['warning_limit'])) : null;
    $warningAction = in_array($_POST['warning_action'] ?? 'notify', ['notify','pause','finish'], true) ? $_POST['warning_action'] : 'notify';
    $lateJoinCutoff = ($_POST['late_join_cutoff_minutes'] ?? '') !== '' ? max(1, min(120, (int)$_POST['late_join_cutoff_minutes'])) : null;
    $resultsAvailableAt = trim($_POST['results_available_at'] ?? '') ?: null;
    $printIncludeAnswerKey = isset($_POST['print_include_answer_key']) ? 1 : 0;
    $availableFrom = trim($_POST['available_from'] ?? '') ?: null;
    $availableUntil = trim($_POST['available_until'] ?? '') ?: null;

    $validationErrors = [];
    if ($title === '') {
        $validationErrors[] = 'Podaj nazwę sprawdzianu.';
    }
    if (str_word_count(strip_tags($description), 0, 'ąćęłńóśźżĄĆĘŁŃÓŚŹŻ') > 375) {
        $validationErrors[] = 'Opis może mieć maksymalnie 375 słów.';
    }
    if ($availableFrom && $availableUntil && strtotime($availableUntil) <= strtotime($availableFrom)) {
        $validationErrors[] = 'Data końca dostępności musi być późniejsza niż data startu.';
    }
    
    // Grade thresholds
    $gradeThresholds = null;
    if ($showGrade) {
        $gradeValues = [
            '6' => (int)($_POST['grade_6'] ?? 95),
            '5' => (int)($_POST['grade_5'] ?? 85),
            '4' => (int)($_POST['grade_4'] ?? 70),
            '3' => (int)($_POST['grade_3'] ?? 50),
            '2' => (int)($_POST['grade_2'] ?? 30),
        ];
        if (!($gradeValues['6'] > $gradeValues['5'] && $gradeValues['5'] > $gradeValues['4'] && $gradeValues['4'] > $gradeValues['3'] && $gradeValues['3'] > $gradeValues['2'])) {
            $validationErrors[] = 'Progi ocen muszą maleć: 6 > 5 > 4 > 3 > 2.';
        }
        $gradeThresholds = json_encode($gradeValues);
    }

    // Selected specific questions from DB
    $selectedQuestionIds = $_POST['selected_questions'] ?? [];
    
    // Handle Custom Exam selection — load questions from JSON file
    $customExamFile = $_POST['custom_exam_file'] ?? '';
    if (!empty($customExamFile)) {
        $cePath = __DIR__ . '/../data/custom_tests/' . basename($customExamFile);
        if (file_exists($cePath)) {
            $ceData = json_decode(file_get_contents($cePath), true);
            if ($ceData && (roleHasAdminAccess($_SESSION['role'] ?? '') || (int)($ceData['teacher_id'] ?? 0) === (int)$userId) && !empty($ceData['questions'])) {
                $questionCount = min(120, count($ceData['questions']));
                $catName = 'Custom_' . preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['username']);
                foreach ($ceData['questions'] as $cq) {
                    // Check if question already exists in this teacher's custom category
                    $checkStmt = $pdo->prepare("SELECT id FROM questions WHERE question_text = ? AND category = ? LIMIT 1");
                    $checkStmt->execute([$cq['text'], $catName]);
                    $existingId = $checkStmt->fetchColumn();

                    if ($existingId) {
                        $selectedQuestionIds[] = (int)$existingId;
                    } else {
                        $data = [
                            'category' => $catName,
                            'question_text' => $cq['text'],
                            'option_a' => $cq['a'],
                            'option_b' => $cq['b'],
                            'option_c' => $cq['c'],
                            'option_d' => $cq['d'],
                            'correct_answer' => $cq['correct'],
                            'image_url' => $cq['image'] ?? '',
                            'explanation' => $cq['explanation'] ?? ''
                        ];
                        if (addQuestion($pdo, $data)) {
                            $selectedQuestionIds[] = $pdo->lastInsertId();
                        }
                    }
                }
                $questionCount = count($selectedQuestionIds);
            }
        }
    }

    $selectedQuestionsJson = !empty($selectedQuestionIds) ? json_encode(array_map('intval', $selectedQuestionIds)) : null;
    $categoriesJson = !empty($selectedCategories) ? json_encode($selectedCategories) : null;

    if ($validationErrors) {
        setSessionMessage('error', implode(' ', $validationErrors));
        redirect('create_exam.php');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO exams (teacher_id, title, description, question_count, selected_questions, categories,
                difficulty_level, shuffle_questions, shuffle_answers, max_participants, time_per_question,
                total_time, exam_mode, auto_finish_on_time, allow_rejoin, anti_cheat_enabled, block_tab_switch,
                require_fullscreen, lobby_enabled, show_results_to_student, show_predicted_grade,
                show_correct_answers, randomize_per_student, lock_after_finish, pass_threshold, max_attempts,
                navigation_mode, allow_answer_changes, warning_limit, warning_action, late_join_cutoff_minutes,
                results_available_at, print_include_answer_key, available_from, available_until, grade_thresholds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $title, $description, count($selectedQuestionIds) ?: $questionCount, $selectedQuestionsJson, $categoriesJson,
            $difficultyLevel, $shuffleQuestions, $shuffleAnswers, $maxParticipants, $timePerQuestion,
            $totalTime, $examMode, $autoFinish, $allowRejoin, $antiCheat, $blockTabSwitch,
            $requireFullscreen, $lobbyEnabled, $showResults, $showGrade,
            $showCorrectAnswers, $randomizePerStudent, $lockAfterFinish, $passThreshold, $maxAttempts,
            $navigationMode, $allowAnswerChanges, $warningLimit, $warningAction, $lateJoinCutoff,
            $resultsAvailableAt ? date('Y-m-d H:i:s', strtotime($resultsAvailableAt)) : null,
            $printIncludeAnswerKey,
            $availableFrom ? date('Y-m-d H:i:s', strtotime($availableFrom)) : null,
            $availableUntil ? date('Y-m-d H:i:s', strtotime($availableUntil)) : null,
            $gradeThresholds
        ]);
        
        $examId = $pdo->lastInsertId();
        if (!setExamAiCopyGuard($pdo, (int)$examId, $aiCopyGuard)) {
            setSessionMessage('error', 'Sprawdzian został utworzony, ale nie udało się zapisać ochrony AI. Wejdź w edycję i spróbuj ponownie.');
            redirect('edit_exam.php?id=' . (int)$examId);
        }
        setSessionMessage('success', "Sprawdzian \"$title\" został utworzony! Teraz możesz go zhostować.");
        redirect('host_exam.php?exam=' . $examId);
    } catch (PDOException $e) {
        error_log("Create exam error: " . $e->getMessage());
        setSessionMessage('error', 'Nie udało się utworzyć sprawdzianu. Spróbuj ponownie za chwilę.');
        redirect('create_exam.php');
    }
}

$flashMsg = getSessionMessage();
?>
<?php
$pageTitle = 'Utwórz sprawdzian – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        .create-exam-hero {
            display: flex;
            align-items: stretch;
            justify-content: space-between;
            gap: 1rem;
            padding: clamp(1.25rem, 3vw, 2rem);
            border-radius: 28px;
            color: #fff;
            background:
                radial-gradient(circle at 92% 8%, rgba(255,255,255,.24), transparent 28%),
                linear-gradient(135deg, var(--primary-color-dark), #0f172a);
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 20px 50px rgba(37,99,235,.18);
        }
        .create-exam-hero .text-muted { color: rgba(255,255,255,.78) !important; }
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: .75rem;
        }
        .preset-card {
            border: 1px solid rgba(148,163,184,.28);
            background: #fff;
            border-radius: 16px;
            padding: .85rem;
            text-align: left;
            min-height: 94px;
            transition: .2s ease;
        }
        .preset-card:hover {
            transform: translateY(-2px);
            border-color: var(--bs-primary);
            box-shadow: 0 12px 28px rgba(15,23,42,.08);
        }
        .preset-card i {
            color: var(--bs-primary);
            font-size: 1.25rem;
        }
        .config-section { border-left: 3px solid var(--bs-primary); padding-left: 1rem; margin-bottom: 2rem; }
        .config-section h5 { color: var(--bs-primary); }
        .create-exam-card {
            border: 1px solid rgba(148,163,184,.18);
            box-shadow: 0 16px 40px rgba(15,23,42,.06);
        }
        .config-section .form-check.form-switch {
            padding: .85rem .85rem .85rem 3.1rem;
            border-radius: 16px;
            background: rgba(248,250,252,.86);
            border: 1px solid rgba(148,163,184,.16);
        }
        body.dark-mode .preset-card,
        body.dark-mode .config-section .form-check.form-switch {
            background: rgba(15,23,42,.82);
            color: #e5e7eb;
            border-color: rgba(148,163,184,.24);
        }
        .question-selector { max-height: 400px; overflow-y: auto; }
        .question-item { transition: all 0.2s; }
        .question-item:hover { background-color: rgba(59,130,246,0.05); }
        .question-item.is-hidden { display: none !important; }
        .transition-all { transition: all 0.2s ease-in-out; }
        .btn-check:checked + .btn-outline-primary {
            background-color: var(--bs-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }
        .category-btn-wrapper label:hover { transform: translateY(-2px); }
        .nav-link.active.btn-outline-primary {
            background-color: var(--bs-primary) !important;
            color: white !important;
        }
        .nav-link.btn-outline-primary:not(.active) {
            color: #212529 !important;
        }
        .exam-summary-card {
            position: sticky;
            top: 1rem;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            padding: .65rem 0;
            border-bottom: 1px solid rgba(148,163,184,.18);
            font-size: .9rem;
        }
        .summary-row:last-child { border-bottom: 0; }
        .quick-time-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .5rem;
        }
        .category-tools {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .category-search {
            max-width: 260px;
        }
        .question-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
        }
        @media (max-width: 991.98px) {
            .create-exam-hero { flex-direction: column; }
            .preset-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .exam-summary-card { position: static; }
        }
        @media (max-width: 575.98px) {
            .preset-grid,
            .quick-time-grid { grid-template-columns: 1fr; }
            .category-search { max-width: none; width: 100%; }
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

                    <div class="create-exam-hero mb-4 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Utwórz sprawdzian</h2>
                            <p class="text-muted mb-0">Skonfiguruj parametry, użyj presetu albo zapisz szkic w przeglądarce.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-3" id="saveDraftBtn">
                                <i class="bi bi-save me-1"></i>Szkic
                            </button>
                            <button type="button" class="btn btn-outline-light rounded-pill px-3" id="clearDraftBtn">
                                <i class="bi bi-eraser me-1"></i>Wyczyść
                            </button>
                            <button type="button" class="btn btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="bi bi-file-earmark-arrow-up me-1"></i>Importuj z TXT
                            </button>
                            <a href="index.php" class="btn btn-outline-light rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i>Powrót
                            </a>
                        </div>
                    </div>

                    <div class="preset-grid mb-4 animate-in" style="animation-delay:.05s">
                        <button type="button" class="preset-card" data-preset="exam">
                            <i class="bi bi-mortarboard d-block mb-2"></i>
                            <strong>Egzamin</strong>
                            <div class="small text-muted">40 pytań, 60 min, wyniki ukryte</div>
                        </button>
                        <button type="button" class="preset-card" data-preset="quiz">
                            <i class="bi bi-lightning-charge d-block mb-2"></i>
                            <strong>Kartkówka</strong>
                            <div class="small text-muted">10 pytań, 12 min, szybka kontrola</div>
                        </button>
                        <button type="button" class="preset-card" data-preset="practice">
                            <i class="bi bi-journal-check d-block mb-2"></i>
                            <strong>Trening</strong>
                            <div class="small text-muted">20 pytań, wyniki i odpowiedzi</div>
                        </button>
                        <button type="button" class="preset-card" data-preset="secure">
                            <i class="bi bi-shield-lock d-block mb-2"></i>
                            <strong>Bezpieczny</strong>
                            <div class="small text-muted">Anti-cheat, fullscreen, blokady</div>
                        </button>
                        <button type="button" class="preset-card" data-preset="repeat">
                            <i class="bi bi-arrow-repeat d-block mb-2"></i>
                            <strong>Powtórka</strong>
                            <div class="small text-muted">3 podejścia, odpowiedzi po teście</div>
                        </button>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4">
                            <?= htmlspecialchars($flashMsg['message']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="examForm">
                        <?php echo csrfTokenField(); ?>
                        
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <!-- Basic Info -->
                                <div class="dashboard-panel mb-4 animate-in create-exam-card">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Informacje podstawowe</h5>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">Nazwa sprawdzianu</label>
                                                <input type="text" name="title" class="form-control" maxlength="180" placeholder="np. Sprawdzian z INF.02 - Rozdział 3" value="<?= htmlspecialchars($defaultTitle) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="form-label fw-semibold mb-0">Opis / Instrukcja dla uczniów</label>
                                                    <small class="text-muted"><span id="descWordCount">0</span>/375 słów</small>
                                                </div>
                                                <textarea name="description" class="form-control" rows="3" maxlength="2200" placeholder="Opcjonalny opis lub instrukcje..." style="resize: none;" oninput="let text=this.value.trim(); let w=text?text.split(/\s+/):[]; if(w.length>375){w=w.slice(0,375);this.value=w.join(' ')+' ';} document.getElementById('descWordCount').innerText=w.length;"><?= htmlspecialchars($defaultDescription) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Questions Config -->
                                <div class="dashboard-panel mb-4 animate-in create-exam-card" style="animation-delay:0.1s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-question-circle me-2"></i>Źródło pytań</h5>
                                        
                                        <ul class="nav nav-tabs mb-3 border-0 gap-2" id="questionTabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link <?= $preselectedCustom ? '' : 'active' ?> rounded-pill px-4 btn-outline-primary" id="pool-tab" data-bs-toggle="tab" data-bs-target="#pool-questions" type="button" role="tab">
                                                    <i class="bi bi-database me-1"></i>Z puli pytań
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link <?= $preselectedCustom ? 'active' : '' ?> rounded-pill px-4 btn-outline-primary" id="custom-tab" data-bs-toggle="tab" data-bs-target="#custom-select" type="button" role="tab">
                                                    <i class="bi bi-pencil-square me-1"></i>Własny sprawdzian
                                                </button>
                                            </li>
                                        </ul>

                                        <div class="tab-content" id="questionTabsContent">
                                            <!-- Pool Questions -->
                                            <div class="tab-pane fade <?= $preselectedCustom ? '' : 'show active' ?>" id="pool-questions" role="tabpanel">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Liczba pytań (losowo)</label>
                                                        <input type="number" name="question_count" id="questionCountInput" class="form-control" value="<?= $defaultQuestionCount ?>" min="1" max="120">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-semibold">Poziom trudności</label>
                                                        <select name="difficulty_level" id="difficultyLevel" class="form-select">
                                                            <option value="mixed" <?= $defaultDifficulty === 'mixed' ? 'selected' : '' ?>>Mieszany</option>
                                                            <option value="easy" <?= $defaultDifficulty === 'easy' ? 'selected' : '' ?>>Łatwy</option>
                                                            <option value="medium" <?= $defaultDifficulty === 'medium' ? 'selected' : '' ?>>Średni</option>
                                                            <option value="hard" <?= $defaultDifficulty === 'hard' ? 'selected' : '' ?>>Trudny</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="category-tools mb-3">
                                                            <label class="form-label fw-semibold mb-0">Kategorie pytań</label>
                                                            <div class="d-flex gap-2 flex-wrap">
                                                                <input type="search" id="categorySearch" class="form-control form-control-sm category-search" placeholder="Szukaj kategorii...">
                                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="selectAllCategories">Zaznacz widoczne</button>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="clearCategories">Wyczyść</button>
                                                            </div>
                                                        </div>
                                                        <div class="category-selector-grid d-flex flex-wrap gap-2">
                                                            <?php foreach ($categories as $cat): ?>
                                                            <div class="category-btn-wrapper" data-category-name="<?= htmlspecialchars(mb_strtolower($cat, 'UTF-8')) ?>">
                                                                <input type="checkbox" class="btn-check" name="categories[]" id="cat_<?= md5($cat) ?>" value="<?= htmlspecialchars($cat) ?>" autocomplete="off">
                                                                <label class="btn btn-outline-primary rounded-pill px-3 py-2 btn-sm fw-medium transition-all" for="cat_<?= md5($cat) ?>">
                                                                    <i class="bi bi-tag-fill me-1 small"></i><?= htmlspecialchars($cat) ?>
                                                                    <span class="badge bg-primary bg-opacity-10 text-dark ms-1 fw-bold"><?= (int)($categoryCounts[$cat] ?? 0) ?></span>
                                                                </label>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-check form-switch mb-2">
                                                            <input class="form-check-input" type="checkbox" id="selectSpecificToggle">
                                                            <label class="form-check-label fw-semibold" for="selectSpecificToggle">Wybierz dokładne pytania</label>
                                                        </div>
                                                        <div id="questionSelector" class="question-selector border rounded p-3" style="display:none;">
                                                            <div class="question-toolbar mb-3">
                                                                <input type="text" id="questionSearch" class="form-control" placeholder="Szukaj pytania..." style="max-width:360px">
                                                                <div class="d-flex gap-2">
                                                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="selectVisibleQuestions">Zaznacz widoczne</button>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="clearQuestions">Wyczyść</button>
                                                                </div>
                                                            </div>
                                                            <div id="questionList">
                                                                <?php foreach (array_slice($allQuestions, 0, 200) as $q): ?>
                                                                <div class="question-item form-check py-2 border-bottom" data-question-text="<?= htmlspecialchars(mb_strtolower(($q['question_text'] ?? '') . ' ' . ($q['category'] ?? ''), 'UTF-8')) ?>">
                                                                    <input class="form-check-input" type="checkbox" name="selected_questions[]" value="<?= $q['id'] ?>" id="q<?= $q['id'] ?>">
                                                                    <label class="form-check-label small" for="q<?= $q['id'] ?>">
                                                                        <span class="badge bg-secondary bg-opacity-25 text-dark me-1">#<?= $q['id'] ?></span>
                                                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold me-1"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($q['category'] ?? '') ?></span>
                                                                        <?= htmlspecialchars(mb_substr($q['question_text'], 0, 120)) ?>...
                                                                    </label>
                                                                </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="text-muted small mt-2">Zaznaczono: <span id="selectedCount">0</span> pytań</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Custom Exam Selection -->
                                            <div class="tab-pane fade <?= $preselectedCustom ? 'show active' : '' ?>" id="custom-select" role="tabpanel">
                                                <?php if (empty($customExams)): ?>
                                                    <div class="text-center py-4">
                                                        <i class="bi bi-journal-plus display-4 text-muted opacity-25 d-block mb-2"></i>
                                                        <p class="text-muted mb-3">Nie masz jeszcze żadnych własnych sprawdzianów.</p>
                                                        <a href="custom_exams.php" class="btn btn-primary rounded-pill px-4">
                                                            <i class="bi bi-plus-lg me-1"></i>Utwórz swój pierwszy sprawdzian
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <label class="form-label fw-semibold mb-3">Wybierz sprawdzian do użycia:</label>
                                                    <div class="row g-3">
                                                        <?php foreach ($customExams as $ce): ?>
                                                        <div class="col-12">
                                                            <div class="form-check p-3 border rounded-4 <?= ($preselectedCustom == $ce['_filename']) ? 'border-primary bg-primary bg-opacity-10' : '' ?>">
                                                                <input class="form-check-input" type="radio" name="custom_exam_file" value="<?= htmlspecialchars($ce['_filename']) ?>" id="ce_<?= md5($ce['_filename']) ?>" <?= ($preselectedCustom == $ce['_filename']) ? 'checked' : '' ?>>
                                                                <label class="form-check-label d-flex justify-content-between align-items-center w-100" for="ce_<?= md5($ce['_filename']) ?>">
                                                                    <div>
                                                                        <div class="fw-bold"><?= htmlspecialchars($ce['title']) ?></div>
                                                                        <?php if (!empty($ce['description'])): ?>
                                                                        <div class="text-muted small"><?= htmlspecialchars(mb_substr($ce['description'], 0, 100)) ?></div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <span class="badge bg-primary rounded-pill"><?= $ce['q_count'] ?> pytań</span>
                                                                </label>
                                                                <div class="d-flex flex-wrap gap-2 mt-2 small text-muted">
                                                                    <span><i class="bi bi-clock me-1"></i><?= (int)($ce['time_limit'] ?? 0) > 0 ? (int)$ce['time_limit'] . ' min' : 'bez limitu' ?></span>
                                                                    <span><i class="bi bi-check2-circle me-1"></i>próg <?= (int)($ce['pass_threshold'] ?? 50) ?>%</span>
                                                                    <span><i class="bi bi-shuffle me-1"></i><?= !empty($ce['shuffle_questions']) ? 'mieszaj pytania' : 'kolejność stała' ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="mt-3">
                                                        <a href="custom_exams.php" class="btn btn-sm btn-outline-primary rounded-pill">
                                                            <i class="bi bi-plus-lg me-1"></i>Zarządzaj swoimi sprawdzianami
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Time & Limits -->
                                <div class="dashboard-panel mb-4 animate-in create-exam-card" style="animation-delay:0.2s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-clock me-2"></i>Czas i limity</h5>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Limit uczestników</label>
                                                <input type="number" name="max_participants" class="form-control" value="36" min="1" max="36">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Czas na pytanie (sek.)</label>
                                                <input type="number" name="time_per_question" id="timePerQuestionInput" class="form-control" placeholder="Brak limitu" min="5" max="600">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Całkowity czas (min.)</label>
                                                <input type="number" name="total_time" id="totalTimeInput" class="form-control" placeholder="Brak limitu" min="1" max="180" value="<?= $defaultTotalTime > 0 ? $defaultTotalTime : '' ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold d-block">Szybkie limity czasu</label>
                                                <div class="quick-time-grid">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-time="10">10 min</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-time="20">20 min</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-time="45">45 min</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-time="60">60 min</button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Dostępny od</label>
                                                <input type="datetime-local" name="available_from" id="availableFromInput" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Dostępny do</label>
                                                <input type="datetime-local" name="available_until" id="availableUntilInput" class="form-control">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold d-block">Szybkie okno dostępności</label>
                                                <div class="quick-time-grid">
                                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-window="45">Od teraz: 45 min</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-window="90">Od teraz: 90 min</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-window="1440">Dziś + 24h</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="clearWindowBtn">Bez okna</button>
                                                </div>
                                                <div class="form-text">Okno jest egzekwowane przy dołączaniu kodem.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right column - toggles -->
                            <div class="col-lg-4">
                                <div class="dashboard-panel mb-4 animate-in exam-summary-card create-exam-card" style="animation-delay:0.1s">
                                    <div class="config-section mb-0">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-data me-2"></i>Podgląd</h5>
                                        <div class="summary-row"><span>Pytania</span><strong id="summaryQuestions"><?= $defaultQuestionCount ?></strong></div>
                                        <div class="summary-row"><span>Czas</span><strong id="summaryTime">bez limitu</strong></div>
                                        <div class="summary-row"><span>Uczestnicy</span><strong id="summaryParticipants">36</strong></div>
                                        <div class="summary-row"><span>Tryb</span><strong id="summaryMode">egzamin</strong></div>
                                        <div class="summary-row"><span>Zabezpieczenia</span><strong id="summarySecurity">wyłączone</strong></div>
                                        <div class="alert alert-info small border-0 mt-3 mb-0">
                                            <i class="bi bi-info-circle me-1"></i>Podgląd aktualizuje się przed utworzeniem sprawdzianu.
                                        </div>
                                    </div>
                                </div>

                                <!-- Test Options -->
                                <div class="dashboard-panel mb-4 animate-in create-exam-card" style="animation-delay:0.15s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2"></i>Opcje testu</h5>
                                        <div class="d-flex flex-column gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffleQ" <?= $defaultShuffleQuestions ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="shuffleQ">Mieszaj kolejność pytań</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="shuffle_answers" id="shuffleA" <?= $defaultShuffleAnswers ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="shuffleA">Mieszaj odpowiedzi</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="exam_mode" id="examMode" checked>
                                                <label class="form-check-label" for="examMode">Tryb egzaminacyjny</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="auto_finish" id="autoFinish" checked>
                                                <label class="form-check-label" for="autoFinish">Automatyczne zakończenie po czasie</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="allow_rejoin" id="allowRejoin">
                                                <label class="form-check-label" for="allowRejoin">Ponowne wejście do testu</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="lobby_enabled" id="lobbyEnabled" checked>
                                                <label class="form-check-label" for="lobbyEnabled">Lobby przed rozpoczęciem</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="randomize_per_student" id="randomizePerStudent">
                                                <label class="form-check-label" for="randomizePerStudent">Inna kolejność pytań dla każdego ucznia</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="lock_after_finish" id="lockAfterFinish" checked>
                                                <label class="form-check-label" for="lockAfterFinish">Blokuj po zakończeniu podejścia</label>
                                            </div>
                                            <div>
                                                <label class="form-label small fw-semibold" for="maxAttempts">Maksymalna liczba podejść</label>
                                                <input type="number" name="max_attempts" id="maxAttempts" class="form-control form-control-sm" value="1" min="1" max="5">
                                                <div class="form-text">Działa razem z opcją ponownego wejścia i blokadą po zakończeniu.</div>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold" for="navigationMode">Nawigacja po pytaniach</label>
                                                    <select name="navigation_mode" id="navigationMode" class="form-select form-select-sm">
                                                        <option value="free">Dowolna</option>
                                                        <option value="linear">Po kolei</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-semibold" for="lateJoinCutoff">Blokuj dołączanie po (min)</label>
                                                    <input type="number" name="late_join_cutoff_minutes" id="lateJoinCutoff" class="form-control form-control-sm" min="1" max="120" placeholder="bez blokady">
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="allow_answer_changes" id="allowAnswerChanges" checked>
                                                        <label class="form-check-label" for="allowAnswerChanges">Pozwól zmieniać odpowiedzi</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="print_include_answer_key" id="printIncludeAnswerKey">
                                                        <label class="form-check-label" for="printIncludeAnswerKey">Klucz odpowiedzi w wydruku</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Anti-cheat -->
                                <div class="dashboard-panel mb-4 animate-in create-exam-card" style="animation-delay:0.25s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-danger"></i>Zabezpieczenia</h5>
                                        <div class="d-flex flex-column gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="anti_cheat" id="antiCheat">
                                                <label class="form-check-label" for="antiCheat">Włącz zabezpieczenia anty-oszustw</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="ai_copy_guard" id="aiCopyGuard">
                                                <label class="form-check-label" for="aiCopyGuard">Blokuj kopiowanie pytań do AI</label>
                                                <div class="form-text">Blokuje kopiowanie, podmienia schowek na komunikat dla AI i zgłasza nauczycielowi próbę kopiowania lub wykryty klawisz PrintScreen. Zrzutów wykonanych przez telefon lub system nie da się wykryć niezawodnie.</div>
                                            </div>
                                            <div id="antiCheatOptions" style="display:none;" class="ps-3 border-start">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="block_tab_switch" id="blockTab">
                                                    <label class="form-check-label small" for="blockTab">Blokada zmiany zakładki</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="require_fullscreen" id="reqFs">
                                                    <label class="form-check-label small" for="reqFs">Wymagaj pełnego ekranu</label>
                                                </div>
                                                <div class="row g-2 mt-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold" for="warningLimit">Limit ostrzeżeń</label>
                                                        <input type="number" name="warning_limit" id="warningLimit" class="form-control form-control-sm" min="1" max="10" placeholder="bez limitu">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold" for="warningAction">Po limicie</label>
                                                        <select name="warning_action" id="warningAction" class="form-select form-select-sm">
                                                            <option value="notify">Tylko powiadom</option>
                                                            <option value="pause">Wstrzymaj</option>
                                                            <option value="finish">Zakończ podejście</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Grading -->
                                <div class="dashboard-panel mb-4 animate-in create-exam-card" style="animation-delay:0.35s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-mortarboard me-2 text-success"></i>Ocenianie</h5>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="show_results" id="showResults">
                                            <label class="form-check-label" for="showResults">Pokaż podsumowanie uczniowi</label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="show_correct_answers" id="showCorrectAnswers" <?= $defaultShowAnswers ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="showCorrectAnswers">Pokaż poprawne odpowiedzi po teście</label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="show_grade" id="showGrade">
                                            <label class="form-check-label" for="showGrade">Pokaż przewidywaną ocenę</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold" for="passThreshold">Próg zaliczenia (%)</label>
                                            <input type="number" name="pass_threshold" id="passThreshold" class="form-control form-control-sm" value="<?= $defaultPassThreshold ?>" min="0" max="100">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold" for="resultsAvailableAt">Udostępnij wyniki od</label>
                                            <input type="datetime-local" name="results_available_at" id="resultsAvailableAt" class="form-control form-control-sm">
                                        </div>
                                        <div id="gradeThresholds" style="display:none;" class="bg-light rounded p-3">
                                            <p class="small fw-bold mb-2">Progi procentowe:</p>
                                            <div class="row g-2">
                                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">6</span><input type="number" name="grade_6" class="form-control" value="95" min="0" max="100"><span class="input-group-text">%</span></div></div>
                                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">5</span><input type="number" name="grade_5" class="form-control" value="85" min="0" max="100"><span class="input-group-text">%</span></div></div>
                                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">4</span><input type="number" name="grade_4" class="form-control" value="70" min="0" max="100"><span class="input-group-text">%</span></div></div>
                                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">3</span><input type="number" name="grade_3" class="form-control" value="50" min="0" max="100"><span class="input-group-text">%</span></div></div>
                                                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">2</span><input type="number" name="grade_2" class="form-control" value="30" min="0" max="100"><span class="input-group-text">%</span></div></div>
                                                <div class="col-6 d-flex align-items-center"><span class="small text-muted">1 = poniżej</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 rounded-pill shadow">
                                        <i class="bi bi-check-lg me-2"></i>Utwórz sprawdzian
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="import_txt.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Importuj pytania z pliku .txt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo csrfTokenField(); ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Wybierz plik .txt</label>
                            <input type="file" name="questions_file" class="form-control" accept=".txt" required>
                        </div>
                        <div class="bg-light p-3 rounded small">
                            <p class="fw-bold mb-1">Format pliku:</p>
                            <code>pytanie;odpA;odpB;odpC;odpD;poprawna</code>
                            <p class="mt-2 mb-0 text-muted">Przykład:<br>Ile bitów ma bajt?;4;8;16;32;B</p>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4">Importuj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('examForm');
        const draftKey = 'zsem_create_exam_draft_v2';
        const byName = name => form.querySelector(`[name="${name}"]`);
        const byId = id => document.getElementById(id);

        function setChecked(id, value) {
            const el = byId(id);
            if (el) el.checked = !!value;
        }

        function setValue(nameOrId, value) {
            const el = byName(nameOrId) || byId(nameOrId);
            if (el) el.value = value;
        }

        function togglePanel(checkboxId, panelId) {
            const checkbox = byId(checkboxId);
            const panel = byId(panelId);
            if (!checkbox || !panel) return;
            panel.style.display = checkbox.checked ? 'block' : 'none';
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('[name="selected_questions[]"]:checked').length;
            const countBadge = byId('selectedCount');
            if (countBadge) countBadge.textContent = count;
            return count;
        }

        function updateSummary() {
            const exactCount = updateSelectedCount();
            const questionCount = exactCount || parseInt(byName('question_count')?.value || '0', 10) || 0;
            const totalTime = byName('total_time')?.value;
            const perQuestion = byName('time_per_question')?.value;
            const participants = byName('max_participants')?.value || '36';
            const mode = byId('examMode')?.checked ? 'egzamin' : 'ćwiczenia';
            const security = byId('antiCheat')?.checked ? 'włączone' : 'wyłączone';

            byId('summaryQuestions').textContent = String(questionCount);
            byId('summaryTime').textContent = totalTime ? `${totalTime} min` : (perQuestion ? `${perQuestion}s / pyt.` : 'bez limitu');
            byId('summaryParticipants').textContent = participants;
            byId('summaryMode').textContent = mode;
            byId('summarySecurity').textContent = security;
        }

        // Toggle question selector
        byId('selectSpecificToggle')?.addEventListener('change', function() {
            byId('questionSelector').style.display = this.checked ? 'block' : 'none';
            updateSummary();
        });

        // Anti-cheat sub-options
        byId('antiCheat')?.addEventListener('change', function() {
            togglePanel('antiCheat', 'antiCheatOptions');
            updateSummary();
        });

        // Grade thresholds
        byId('showGrade')?.addEventListener('change', function() {
            togglePanel('showGrade', 'gradeThresholds');
        });

        byId('showCorrectAnswers')?.addEventListener('change', function() {
            if (this.checked) setChecked('showResults', true);
        });

        byId('showResults')?.addEventListener('change', function() {
            if (!this.checked) {
                setChecked('showCorrectAnswers', false);
                setChecked('showGrade', false);
                togglePanel('showGrade', 'gradeThresholds');
            }
        });

        document.querySelectorAll('[data-time]').forEach(button => {
            button.addEventListener('click', () => {
                setValue('total_time', button.dataset.time);
                setValue('time_per_question', '');
                updateSummary();
            });
        });

        function toDatetimeLocal(date) {
            const pad = value => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        }

        document.querySelectorAll('[data-window]').forEach(button => {
            button.addEventListener('click', () => {
                const now = new Date();
                const until = new Date(now.getTime() + (parseInt(button.dataset.window, 10) * 60 * 1000));
                setValue('availableFromInput', toDatetimeLocal(now));
                setValue('availableUntilInput', toDatetimeLocal(until));
            });
        });

        byId('clearWindowBtn')?.addEventListener('click', () => {
            setValue('availableFromInput', '');
            setValue('availableUntilInput', '');
        });

        document.querySelectorAll('[data-preset]').forEach(button => {
            button.addEventListener('click', () => {
                const preset = button.dataset.preset;
                if (preset === 'exam') {
                    setValue('question_count', 40);
                    setValue('total_time', 60);
                    setValue('time_per_question', '');
                    setValue('pass_threshold', 50);
                    setChecked('examMode', true);
                    setChecked('showResults', false);
                    setChecked('showCorrectAnswers', false);
                    setChecked('antiCheat', false);
                }
                if (preset === 'quiz') {
                    setValue('question_count', 10);
                    setValue('total_time', 12);
                    setValue('time_per_question', '');
                    setValue('pass_threshold', 50);
                    setChecked('examMode', true);
                    setChecked('showResults', true);
                }
                if (preset === 'practice') {
                    setValue('question_count', 20);
                    setValue('total_time', '');
                    setValue('time_per_question', 45);
                    setValue('pass_threshold', 0);
                    setValue('max_attempts', 3);
                    setChecked('examMode', false);
                    setChecked('showResults', true);
                    setChecked('showCorrectAnswers', true);
                    setChecked('allowRejoin', true);
                    setChecked('lockAfterFinish', false);
                }
                if (preset === 'secure') {
                    setValue('question_count', 40);
                    setValue('total_time', 45);
                    setValue('max_attempts', 1);
                    setChecked('examMode', true);
                    setChecked('antiCheat', true);
                    setChecked('blockTab', true);
                    setChecked('reqFs', true);
                    setChecked('lockAfterFinish', true);
                    setChecked('allowRejoin', false);
                }
                if (preset === 'repeat') {
                    setValue('question_count', 25);
                    setValue('total_time', 30);
                    setValue('time_per_question', '');
                    setValue('pass_threshold', 60);
                    setValue('max_attempts', 3);
                    setChecked('examMode', false);
                    setChecked('showResults', true);
                    setChecked('showCorrectAnswers', true);
                    setChecked('showGrade', true);
                    setChecked('allowRejoin', true);
                    setChecked('lockAfterFinish', false);
                    setChecked('randomizePerStudent', true);
                }
                togglePanel('antiCheat', 'antiCheatOptions');
                togglePanel('showGrade', 'gradeThresholds');
                updateSummary();
            });
        });

        byId('categorySearch')?.addEventListener('input', function() {
            const term = this.value.trim().toLowerCase();
            document.querySelectorAll('.category-btn-wrapper').forEach(item => {
                item.style.display = item.dataset.categoryName.includes(term) ? '' : 'none';
            });
        });

        byId('selectAllCategories')?.addEventListener('click', () => {
            document.querySelectorAll('.category-btn-wrapper').forEach(item => {
                if (item.style.display !== 'none') {
                    const input = item.querySelector('input[type="checkbox"]');
                    if (input) input.checked = true;
                }
            });
        });

        byId('clearCategories')?.addEventListener('click', () => {
            document.querySelectorAll('[name="categories[]"]').forEach(input => input.checked = false);
        });

        // Question search filter
        byId('questionSearch')?.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.question-item').forEach(item => {
                item.classList.toggle('is-hidden', !item.dataset.questionText.includes(q));
            });
        });

        byId('selectVisibleQuestions')?.addEventListener('click', () => {
            document.querySelectorAll('.question-item:not(.is-hidden) [name="selected_questions[]"]').forEach(input => input.checked = true);
            updateSummary();
        });

        byId('clearQuestions')?.addEventListener('click', () => {
            document.querySelectorAll('[name="selected_questions[]"]').forEach(input => input.checked = false);
            updateSummary();
        });

        // Selected question counter
        document.querySelectorAll('[name="selected_questions[]"]').forEach(cb => {
            cb.addEventListener('change', updateSummary);
        });

        ['question_count', 'max_participants', 'time_per_question', 'total_time'].forEach(name => {
            byName(name)?.addEventListener('input', updateSummary);
        });
        byId('examMode')?.addEventListener('change', updateSummary);

        function collectDraft() {
            const data = {};
            new FormData(form).forEach((value, key) => {
                if (key === 'csrf_token') return;
                if (!data[key]) data[key] = [];
                data[key].push(value);
            });
            data.__toggles = {
                selectSpecificToggle: byId('selectSpecificToggle')?.checked || false,
                antiCheat: byId('antiCheat')?.checked || false,
                showGrade: byId('showGrade')?.checked || false
            };
            return data;
        }

        function restoreDraft() {
            const raw = localStorage.getItem(draftKey);
            if (!raw) return;
            let data;
            try { data = JSON.parse(raw); } catch (e) { return; }

            form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(field => {
                field.checked = false;
            });
            Object.entries(data).forEach(([key, values]) => {
                if (key === '__toggles') return;
                const fields = form.elements[key] ? (form.elements[key].length ? Array.from(form.elements[key]) : [form.elements[key]]) : [];
                fields.forEach(field => {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = values.includes(field.value);
                    } else if (values[0] !== undefined) {
                        field.value = values[0];
                    }
                });
            });
            if (data.__toggles) {
                setChecked('selectSpecificToggle', data.__toggles.selectSpecificToggle);
                setChecked('antiCheat', data.__toggles.antiCheat);
                setChecked('showGrade', data.__toggles.showGrade);
            }
            togglePanel('antiCheat', 'antiCheatOptions');
            togglePanel('showGrade', 'gradeThresholds');
            if (byId('selectSpecificToggle')?.checked) byId('questionSelector').style.display = 'block';
            updateSummary();
        }

        byId('saveDraftBtn')?.addEventListener('click', () => {
            localStorage.setItem(draftKey, JSON.stringify(collectDraft()));
            appNotice('Szkic zapisany w tej przeglądarce.', 'success');
        });

        byId('clearDraftBtn')?.addEventListener('click', () => {
            localStorage.removeItem(draftKey);
            form.reset();
            togglePanel('antiCheat', 'antiCheatOptions');
            togglePanel('showGrade', 'gradeThresholds');
            byId('questionSelector').style.display = 'none';
            updateSummary();
        });

        form.addEventListener('submit', function(event) {
            const from = byId('availableFromInput')?.value;
            const until = byId('availableUntilInput')?.value;
            if (from && until && new Date(until) <= new Date(from)) {
                event.preventDefault();
                appNotice('Data końca dostępności musi być późniejsza niż data startu.', 'warning');
                return;
            }
            if (byId('showGrade')?.checked) {
                const g6 = parseInt(byName('grade_6').value, 10);
                const g5 = parseInt(byName('grade_5').value, 10);
                const g4 = parseInt(byName('grade_4').value, 10);
                const g3 = parseInt(byName('grade_3').value, 10);
                const g2 = parseInt(byName('grade_2').value, 10);
                if (!(g6 > g5 && g5 > g4 && g4 > g3 && g3 > g2)) {
                    event.preventDefault();
                    appNotice('Progi ocen muszą maleć: 6 > 5 > 4 > 3 > 2.', 'warning');
                    return;
                }
            }
            localStorage.removeItem(draftKey);
        });

        restoreDraft();
        updateSummary();
    });
    </script>
</body>
</html>
