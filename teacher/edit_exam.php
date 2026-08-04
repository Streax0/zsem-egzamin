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
$examId = (int)($_GET['id'] ?? 0);

// Load exam
$stmt = $pdo->prepare("SELECT id, teacher_id, title, description, question_count, selected_questions, categories, difficulty_level, shuffle_questions, shuffle_answers, max_participants, time_per_question, total_time, exam_mode, auto_finish_on_time, allow_rejoin, anti_cheat_enabled, block_tab_switch, require_fullscreen, lobby_enabled, show_results_to_student, show_predicted_grade, show_correct_answers, randomize_per_student, lock_after_finish, pass_threshold, max_attempts, navigation_mode, allow_answer_changes, warning_limit, warning_action, late_join_cutoff_minutes, results_available_at, print_include_answer_key, available_from, available_until, grade_thresholds, created_at, updated_at FROM exams WHERE id = ? AND teacher_id = ?");
$stmt->execute([$examId, $userId]);
$exam = $stmt->fetch();

if (!$exam) {
    setSessionMessage('error', 'Sprawdzian nie istnieje lub nie masz do niego uprawnień.');
    redirect('index.php');
}

// Load categories & questions for selection
$allQuestions = loadQuestions($pdo, false);
$allQuestions = array_values(array_filter($allQuestions, static fn($q) => !isInternalQuestionCategory($q['category'] ?? '')));
$publicQuestionIds = array_map(static fn($q) => (int)($q['id'] ?? 0), $allQuestions);
$categories = array_unique(array_column($allQuestions, 'category'));
sort($categories);

// Selected values
$currentCategories = $exam['categories'] ? json_decode($exam['categories'], true) : [];
$selectedSpecific = $exam['selected_questions'] ? json_decode($exam['selected_questions'], true) : [];
$privateSelectedIds = array_values(array_diff(array_map('intval', $selectedSpecific), $publicQuestionIds));
$gradeThresholds = $exam['grade_thresholds'] ? json_decode($exam['grade_thresholds'], true) : ['6'=>95, '5'=>85, '4'=>70, '3'=>50, '2'=>30];
$aiCopyGuard = examAiCopyGuardEnabled($pdo, $examId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('edit_exam.php?id=' . $examId);
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $questionCount = max(1, min(100, (int)($_POST['question_count'] ?? 40)));
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
    $lobbyEnabled = isset($_POST['lobby_enabled']) ? 1 : 0;
    $showResults = isset($_POST['show_results']) ? 1 : 0;
    $showGrade = isset($_POST['show_grade']) ? 1 : 0;
    $showCorrectAnswers = isset($_POST['show_correct_answers']) ? 1 : (int)($exam['show_correct_answers'] ?? 0);
    $randomizePerStudent = isset($_POST['randomize_per_student']) ? 1 : (int)($exam['randomize_per_student'] ?? 0);
    $lockAfterFinish = isset($_POST['lock_after_finish']) ? 1 : (int)($exam['lock_after_finish'] ?? 1);
    $passThreshold = max(1, min(100, (int)($_POST['pass_threshold'] ?? ($exam['pass_threshold'] ?? 50))));
    $maxAttempts = max(1, min(10, (int)($_POST['max_attempts'] ?? ($exam['max_attempts'] ?? 1))));
    $navigationMode = in_array($_POST['navigation_mode'] ?? ($exam['navigation_mode'] ?? 'free'), ['free','linear','locked'], true) ? ($_POST['navigation_mode'] ?? ($exam['navigation_mode'] ?? 'free')) : 'free';
    $allowAnswerChanges = isset($_POST['allow_answer_changes']) ? 1 : (int)($exam['allow_answer_changes'] ?? 1);
    $warningLimit = (isset($_POST['warning_limit']) && $_POST['warning_limit'] !== '') ? max(1, min(20, (int)$_POST['warning_limit'])) : ($exam['warning_limit'] ?? null);
    $warningAction = in_array($_POST['warning_action'] ?? ($exam['warning_action'] ?? 'notify'), ['notify','auto_finish'], true) ? ($_POST['warning_action'] ?? ($exam['warning_action'] ?? 'notify')) : 'notify';
    $lateJoinCutoff = (isset($_POST['late_join_cutoff_minutes']) && $_POST['late_join_cutoff_minutes'] !== '') ? max(1, min(120, (int)$_POST['late_join_cutoff_minutes'])) : ($exam['late_join_cutoff_minutes'] ?? null);
    $resultsAvailableAt = trim((string)($_POST['results_available_at'] ?? ''));
    $resultsAvailableAt = $resultsAvailableAt !== '' ? date('Y-m-d H:i:s', strtotime($resultsAvailableAt)) : ($exam['results_available_at'] ?? null);
    $printIncludeAnswerKey = isset($_POST['print_include_answer_key']) ? 1 : (int)($exam['print_include_answer_key'] ?? 0);
    
    $newGradeThresholds = null;
    if ($showGrade) {
        $newGradeThresholds = json_encode([
            '6' => (int)($_POST['grade_6'] ?? 95),
            '5' => (int)($_POST['grade_5'] ?? 85),
            '4' => (int)($_POST['grade_4'] ?? 70),
            '3' => (int)($_POST['grade_3'] ?? 50),
            '2' => (int)($_POST['grade_2'] ?? 30),
        ]);
    }

    $selectedQuestionIds = isset($_POST['use_selected_questions']) ? ($_POST['selected_questions'] ?? []) : [];
    $selectedQuestionsJson = !empty($selectedQuestionIds) ? json_encode(array_map('intval', $selectedQuestionIds)) : null;
    $categoriesJson = !empty($selectedCategories) ? json_encode($selectedCategories) : null;

    try {
        $stmt = $pdo->prepare("
            UPDATE exams SET 
                title = ?, description = ?, question_count = ?, selected_questions = ?, categories = ?,
                difficulty_level = ?, shuffle_questions = ?, shuffle_answers = ?, max_participants = ?, 
                time_per_question = ?, total_time = ?, exam_mode = ?, auto_finish_on_time = ?, 
                allow_rejoin = ?, anti_cheat_enabled = ?, block_tab_switch = ?,
                require_fullscreen = ?, lobby_enabled = ?, show_results_to_student = ?, 
                show_predicted_grade = ?, show_correct_answers = ?, randomize_per_student = ?,
                lock_after_finish = ?, pass_threshold = ?, max_attempts = ?, navigation_mode = ?,
                allow_answer_changes = ?, warning_limit = ?, warning_action = ?, late_join_cutoff_minutes = ?,
                results_available_at = ?, print_include_answer_key = ?, grade_thresholds = ?
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->execute([
            $title, $description, $questionCount, $selectedQuestionsJson, $categoriesJson,
            $difficultyLevel, $shuffleQuestions, $shuffleAnswers, $maxParticipants, 
            $timePerQuestion, $totalTime, $examMode, $autoFinish, 
            $allowRejoin, $antiCheat, $blockTabSwitch,
            $requireFullscreen, $lobbyEnabled, $showResults, 
            $showGrade, $showCorrectAnswers, $randomizePerStudent,
            $lockAfterFinish, $passThreshold, $maxAttempts, $navigationMode,
            $allowAnswerChanges, $warningLimit, $warningAction, $lateJoinCutoff,
            $resultsAvailableAt, $printIncludeAnswerKey, $newGradeThresholds, $examId, $userId
        ]);
        
        if (!setExamAiCopyGuard($pdo, $examId, $aiCopyGuard)) {
            setSessionMessage('error', 'Nie udało się zapisać ustawienia ochrony AI. Pozostałe zmiany mogły zostać zapisane.');
            redirect('edit_exam.php?id=' . $examId);
        }
        setSessionMessage('success', "Zmiany w sprawdzianie \"$title\" zostały zapisane.");
        redirect('index.php');
    } catch (PDOException $e) {
        error_log('Edit exam failed: ' . $e->getMessage());
        setSessionMessage('error', 'Nie udało się zapisać zmian. Spróbuj ponownie za chwilę.');
        redirect('edit_exam.php?id=' . $examId);
    }
}

$flashMsg = getSessionMessage();
?>
<?php
$pageTitle = 'Edytuj sprawdzian – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        .config-section { border-left: 3px solid var(--bs-primary); padding-left: 1rem; margin-bottom: 2rem; }
        .config-section h5 { color: var(--bs-primary); }
        .question-selector { max-height: 400px; overflow-y: auto; }
        .question-item { transition: all 0.2s; }
        .question-item:hover { background-color: rgba(59,130,246,0.05); }
        .transition-all { transition: all 0.2s ease-in-out; }
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

                    <div class="d-flex justify-content-between align-items-center mb-4 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2 text-primary"></i>Edytuj sprawdzian</h2>
                            <p class="text-muted">Aktualizujesz: <?= htmlspecialchars($exam['title']) ?></p>
                        </div>
                        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i>Powrót
                        </a>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4">
                            <?= htmlspecialchars($flashMsg['message']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?php echo csrfTokenField(); ?>
                        
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="dashboard-panel mb-4 animate-in">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Informacje podstawowe</h5>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">Nazwa sprawdzianu</label>
                                                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($exam['title']) ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">Opis / Instrukcja dla uczniów</label>
                                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($exam['description']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.1s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-question-circle me-2"></i>Pytania</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Liczba pytań</label>
                                                <input type="number" name="question_count" class="form-control" value="<?= $exam['question_count'] ?>" min="1" max="100">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold d-block mb-3">Kategorie pytań</label>
                                                <div class="category-selector-grid d-flex flex-wrap gap-2">
                                                    <?php foreach ($categories as $cat): ?>
                                                    <div class="category-btn-wrapper">
                                                        <input type="checkbox" class="btn-check" name="categories[]" id="cat_<?= md5($cat) ?>" value="<?= htmlspecialchars($cat) ?>" <?= in_array($cat, $currentCategories) ? 'checked' : '' ?> autocomplete="off">
                                                        <label class="btn btn-outline-primary rounded-pill px-3 py-2 btn-sm fw-medium transition-all" for="cat_<?= md5($cat) ?>">
                                                            <i class="bi bi-tag-fill me-1 small"></i><?= htmlspecialchars($cat) ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Poziom trudności</label>
                                                <select name="difficulty_level" class="form-select">
                                                    <option value="mixed" <?= $exam['difficulty_level'] == 'mixed' ? 'selected' : '' ?>>Mieszany</option>
                                                    <option value="easy" <?= $exam['difficulty_level'] == 'easy' ? 'selected' : '' ?>>Łatwy</option>
                                                    <option value="medium" <?= $exam['difficulty_level'] == 'medium' ? 'selected' : '' ?>>Średni</option>
                                                    <option value="hard" <?= $exam['difficulty_level'] == 'hard' ? 'selected' : '' ?>>Trudny</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="use_selected_questions" value="1" id="selectSpecificToggle" <?= !empty($selectedSpecific) ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-semibold" for="selectSpecificToggle">Wybierz dokładne pytania</label>
                                                </div>
                                                <div id="questionSelector" class="question-selector border rounded p-3" style="display: <?= !empty($selectedSpecific) ? 'block' : 'none' ?>;">
                                                    <?php foreach ($privateSelectedIds as $privateId): ?>
                                                        <input type="hidden" name="selected_questions[]" value="<?= (int)$privateId ?>" data-private-selected="1">
                                                    <?php endforeach; ?>
                                                    <?php if (!empty($privateSelectedIds)): ?>
                                                        <div class="alert alert-info py-2 small mb-3">
                                                            Ten sprawdzian zawiera <?= count($privateSelectedIds) ?> prywatnych pytań z własnego zestawu. Są zachowane, ale nie są widoczne w publicznym banku pytań.
                                                        </div>
                                                    <?php endif; ?>
                                                    <input type="text" id="questionSearch" class="form-control mb-3" placeholder="Szukaj pytania...">
                                                    <div id="questionList">
                                                        <?php foreach (array_slice($allQuestions, 0, 300) as $q): ?>
                                                        <div class="question-item form-check py-2 border-bottom">
                                                            <input class="form-check-input" type="checkbox" name="selected_questions[]" value="<?= $q['id'] ?>" id="q<?= $q['id'] ?>" <?= in_array($q['id'], $selectedSpecific) ? 'checked' : '' ?>>
                                                            <label class="form-check-label small" for="q<?= $q['id'] ?>">
                                                                <span class="badge bg-light text-dark me-1">#<?= $q['id'] ?></span>
                                                                <?= htmlspecialchars(mb_substr($q['question_text'], 0, 120)) ?>...
                                                            </label>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="text-muted small mt-2">Zaznaczono: <span id="selectedCount"><?= count($selectedSpecific) ?></span> pytań</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.12s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-danger"></i>Zabezpieczenia</h5>
                                        <div class="d-flex flex-column gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="anti_cheat" id="antiCheat" <?= !empty($exam['anti_cheat_enabled']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="antiCheat">Włącz zabezpieczenia anty-oszustw</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="block_tab_switch" id="blockTab" <?= !empty($exam['block_tab_switch']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="blockTab">Blokada zmiany zakładki</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="require_fullscreen" id="reqFs" <?= !empty($exam['require_fullscreen']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="reqFs">Wymagaj pełnego ekranu</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="ai_copy_guard" id="aiCopyGuard" <?= $aiCopyGuard ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="aiCopyGuard">Blokuj kopiowanie pytań do AI</label>
                                                <div class="form-text">Blokuje kopiowanie, podmienia schowek na komunikat dla AI i zgłasza próbę kopiowania lub wykryty klawisz PrintScreen. Zrzutów z telefonu lub systemu nie da się wykryć niezawodnie.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.15s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2"></i>Opcje testu</h5>
                                        <div class="d-flex flex-column gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffleQ" <?= $exam['shuffle_questions'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="shuffleQ">Mieszaj kolejność pytań</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="shuffle_answers" id="shuffleA" <?= $exam['shuffle_answers'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="shuffleA">Mieszaj odpowiedzi</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="exam_mode" id="examMode" <?= $exam['exam_mode'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="examMode">Tryb egzaminacyjny</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="auto_finish" id="autoFinish" <?= $exam['auto_finish_on_time'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="autoFinish">Automatyczne zakończenie po czasie</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="show_correct_answers" id="showCorrectAnswers" <?= !empty($exam['show_correct_answers']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="showCorrectAnswers">Pokaż poprawne odpowiedzi po sprawdzianie</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="allow_answer_changes" id="allowAnswerChanges" <?= !empty($exam['allow_answer_changes']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="allowAnswerChanges">Pozwól uczniowi zmieniać odpowiedzi</label>
                                            </div>
                                            <div>
                                                <label class="form-label small fw-bold" for="resultsAvailableAt">Wyniki dostępne od</label>
                                                <input class="form-control" type="datetime-local" name="results_available_at" id="resultsAvailableAt" value="<?= !empty($exam['results_available_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($exam['results_available_at']))) : '' ?>">
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold" for="passThreshold">Próg zaliczenia %</label>
                                                    <input class="form-control" type="number" name="pass_threshold" id="passThreshold" min="1" max="100" value="<?= (int)($exam['pass_threshold'] ?? 50) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold" for="maxAttempts">Podejścia</label>
                                                    <input class="form-control" type="number" name="max_attempts" id="maxAttempts" min="1" max="10" value="<?= (int)($exam['max_attempts'] ?? 1) ?>">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="form-label small fw-bold" for="navigationMode">Nawigacja</label>
                                                <select class="form-select" name="navigation_mode" id="navigationMode">
                                                    <?php foreach (['free' => 'Swobodna', 'linear' => 'Po kolei', 'locked' => 'Zablokowana po odpowiedzi'] as $modeKey => $modeLabel): ?>
                                                        <option value="<?= $modeKey ?>" <?= ($exam['navigation_mode'] ?? 'free') === $modeKey ? 'selected' : '' ?>><?= $modeLabel ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 rounded-pill shadow">
                                        <i class="bi bi-save me-2"></i>Zapisz zmiany
                                    </button>
                                    <a href="index.php" class="btn btn-light rounded-pill">Anuluj</a>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const specificToggle = document.getElementById('selectSpecificToggle');
        const selector = document.getElementById('questionSelector');
        const selectedCount = document.getElementById('selectedCount');

        function syncSpecificSelector() {
            if (!selector || !specificToggle) return;
            selector.style.display = specificToggle.checked ? 'block' : 'none';
        }

        function syncSelectedCount() {
            const checkedCount = document.querySelectorAll('input[type="checkbox"][name="selected_questions[]"]:checked').length;
            const privateCount = document.querySelectorAll('input[type="hidden"][name="selected_questions[]"][data-private-selected="1"]').length;
            if (selectedCount) selectedCount.textContent = checkedCount + privateCount;
        }

        specificToggle?.addEventListener('change', syncSpecificSelector);
        syncSpecificSelector();
        syncSelectedCount();

        document.getElementById('questionSearch')?.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.question-item').forEach(item => {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });

        document.querySelectorAll('[name="selected_questions[]"]').forEach(cb => {
            cb.addEventListener('change', syncSelectedCount);
        });
    });
    </script>
</body>
</html>
