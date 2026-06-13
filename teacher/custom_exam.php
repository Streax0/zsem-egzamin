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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('create_exam.php');
    }

    // Collect settings
    $title = trim($_POST['title'] ?? 'Sprawdzian');
    $description = trim($_POST['description'] ?? '');
    $questionCount = max(1, min(60, (int)($_POST['question_count'] ?? 40)));
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
    
    // Grade thresholds
    $gradeThresholds = null;
    if ($showGrade) {
        $gradeThresholds = json_encode([
            '6' => (int)($_POST['grade_6'] ?? 95),
            '5' => (int)($_POST['grade_5'] ?? 85),
            '4' => (int)($_POST['grade_4'] ?? 70),
            '3' => (int)($_POST['grade_3'] ?? 50),
            '2' => (int)($_POST['grade_2'] ?? 30),
        ]);
    }

    // Selected specific questions from DB
    $selectedQuestionIds = $_POST['selected_questions'] ?? [];
    
    // Handle Custom Questions
    $customQuestions = $_POST['custom_questions'] ?? [];
    foreach ($customQuestions as $cq) {
        if (!empty($cq['text']) && !empty($cq['a'])) {
            $data = [
                'category' => 'Własne_' . preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['username']),
                'question_text' => trim($cq['text']),
                'option_a' => trim($cq['a']),
                'option_b' => trim($cq['b']),
                'option_c' => trim($cq['c']),
                'option_d' => trim($cq['d']),
                'correct_answer' => strtoupper(trim($cq['correct'])),
                'image_url' => trim($cq['image'] ?? ''),
                'explanation' => trim($cq['explanation'] ?? '')
            ];
            
            if (addQuestion($pdo, $data)) {
                $selectedQuestionIds[] = $pdo->lastInsertId();
            }
        }
    }

    $selectedQuestionsJson = !empty($selectedQuestionIds) ? json_encode(array_map('intval', $selectedQuestionIds)) : null;
    $categoriesJson = !empty($selectedCategories) ? json_encode($selectedCategories) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO exams (teacher_id, title, description, question_count, selected_questions, categories,
                difficulty_level, shuffle_questions, shuffle_answers, max_participants, time_per_question,
                total_time, exam_mode, auto_finish_on_time, allow_rejoin, anti_cheat_enabled, block_tab_switch,
                require_fullscreen, lobby_enabled, show_results_to_student, show_predicted_grade, grade_thresholds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $title, $description, count($selectedQuestionIds) ?: $questionCount, $selectedQuestionsJson, $categoriesJson,
            $difficultyLevel, $shuffleQuestions, $shuffleAnswers, $maxParticipants, $timePerQuestion,
            $totalTime, $examMode, $autoFinish, $allowRejoin, $antiCheat, $blockTabSwitch,
            $requireFullscreen, $lobbyEnabled, $showResults, $showGrade, $gradeThresholds
        ]);
        
        $examId = $pdo->lastInsertId();
        setExamAiCopyGuard($pdo, (int)$examId, $aiCopyGuard);
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
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utwórz sprawdzian – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <style>
        .config-section { border-left: 3px solid var(--bs-primary); padding-left: 1rem; margin-bottom: 2rem; }
        .config-section h5 { color: var(--bs-primary); }
        .question-selector { max-height: 400px; overflow-y: auto; }
        .question-item { transition: all 0.2s; }
        .question-item:hover { background-color: rgba(59,130,246,0.05); }
        .transition-all { transition: all 0.2s ease-in-out; }
        .btn-check:checked + .btn-outline-primary {
            background-color: var(--bs-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }
        .category-btn-wrapper label:hover { transform: translateY(-2px); }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">

                    <div class="d-flex justify-content-between align-items-center mb-4 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Utwórz sprawdzian</h2>
                            <p class="text-muted">Skonfiguruj parametry nowego sprawdzianu.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="bi bi-file-earmark-arrow-up me-1"></i>Importuj z TXT
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i>Powrót
                            </a>
                        </div>
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
                                <div class="dashboard-panel mb-4 animate-in">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Informacje podstawowe</h5>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">Nazwa sprawdzianu</label>
                                                <input type="text" name="title" class="form-control" placeholder="np. Sprawdzian z INF.02 - Rozdział 3" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold">Opis / Instrukcja dla uczniów</label>
                                                <textarea name="description" class="form-control" rows="3" placeholder="Opcjonalny opis lub instrukcje..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                 <!-- Questions Config -->
                                 <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.1s">
                                     <div class="config-section">
                                         <h5 class="fw-bold mb-3"><i class="bi bi-question-circle me-2"></i>Pytania</h5>
                                         
                                         <ul class="nav nav-tabs mb-3 border-0 gap-2" id="questionTabs" role="tablist">
                                             <li class="nav-item" role="presentation">
                                                 <button class="nav-link active rounded-pill px-4 btn-outline-primary" id="db-tab" data-bs-toggle="tab" data-bs-target="#db-questions" type="button" role="tab">Wybierz z bazy</button>
                                             </li>
                                             <li class="nav-item" role="presentation">
                                                 <button class="nav-link rounded-pill px-4 btn-outline-primary" id="custom-tab" data-bs-toggle="tab" data-bs-target="#custom-questions" type="button" role="tab">Stwórz własne</button>
                                             </li>
                                         </ul>

                                         <div class="tab-content" id="questionTabsContent">
                                             <!-- Database Selection -->
                                             <div class="tab-pane fade show active" id="db-questions" role="tabpanel">
                                                 <div class="row g-3">
                                                     <div class="col-md-6">
                                                         <label class="form-label fw-semibold">Liczba pytań (losowo)</label>
                                                         <input type="number" name="question_count" class="form-control" value="40" min="1" max="60">
                                                     </div>
                                                     <div class="col-12">
                                                         <label class="form-label fw-semibold d-block mb-3">Kategorie pytań</label>
                                                         <div class="category-selector-grid d-flex flex-wrap gap-2">
                                                             <?php foreach ($categories as $cat): ?>
                                                             <div class="category-btn-wrapper">
                                                                 <input type="checkbox" class="btn-check" name="categories[]" id="cat_<?= md5($cat) ?>" value="<?= htmlspecialchars($cat) ?>" autocomplete="off">
                                                                 <label class="btn btn-outline-primary rounded-pill px-3 py-2 btn-sm fw-medium transition-all" for="cat_<?= md5($cat) ?>">
                                                                     <i class="bi bi-tag-fill me-1 small"></i><?= htmlspecialchars($cat) ?>
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
                                                             <input type="text" id="questionSearch" class="form-control mb-3" placeholder="Szukaj pytania...">
                                                             <div id="questionList">
                                                                 <?php foreach (array_slice($allQuestions, 0, 200) as $q): ?>
                                                                 <div class="question-item form-check py-2 border-bottom">
                                                                     <input class="form-check-input" type="checkbox" name="selected_questions[]" value="<?= $q['id'] ?>" id="q<?= $q['id'] ?>">
                                                                     <label class="form-check-label small" for="q<?= $q['id'] ?>">
                                                                         <span class="badge bg-light text-dark me-1">#<?= $q['id'] ?></span>
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

                                             <!-- Custom Questions -->
                                             <div class="tab-pane fade" id="custom-questions" role="tabpanel">
                                                 <div id="customQuestionsContainer">
                                                     <!-- Dynamic questions here -->
                                                 </div>
                                                 <button type="button" class="btn btn-outline-primary btn-sm rounded-pill mt-2" onclick="addCustomQuestion()">
                                                     <i class="bi bi-plus-lg me-1"></i>Dodaj kolejne pytanie
                                                 </button>
                                             </div>
                                         </div>
                                     </div>
                                 </div>

                                <!-- Time & Limits -->
                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.2s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-clock me-2"></i>Czas i limity</h5>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Limit uczestników</label>
                                                <input type="number" name="max_participants" class="form-control" value="36" min="1" max="36">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Czas na pytanie (sek.)</label>
                                                <input type="number" name="time_per_question" class="form-control" placeholder="Brak limitu" min="5" max="600">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Całkowity czas (min.)</label>
                                                <input type="number" name="total_time" class="form-control" placeholder="Brak limitu" min="1" max="180">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right column - toggles -->
                            <div class="col-lg-4">
                                <!-- Test Options -->
                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.15s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2"></i>Opcje testu</h5>
                                        <div class="d-flex flex-column gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffleQ" checked>
                                                <label class="form-check-label" for="shuffleQ">Mieszaj kolejność pytań</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="shuffle_answers" id="shuffleA">
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
                                        </div>
                                    </div>
                                </div>

                                <!-- Anti-cheat -->
                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.25s">
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
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Grading -->
                                <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.35s">
                                    <div class="config-section">
                                        <h5 class="fw-bold mb-3"><i class="bi bi-mortarboard me-2 text-success"></i>Ocenianie</h5>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="show_results" id="showResults">
                                            <label class="form-check-label" for="showResults">Pokaż podsumowanie uczniowi</label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="show_grade" id="showGrade">
                                            <label class="form-check-label" for="showGrade">Pokaż przewidywaną ocenę</label>
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
        // Toggle question selector
        document.getElementById('selectSpecificToggle').addEventListener('change', function() {
            document.getElementById('questionSelector').style.display = this.checked ? 'block' : 'none';
        });

        // Anti-cheat sub-options
        document.getElementById('antiCheat').addEventListener('change', function() {
            document.getElementById('antiCheatOptions').style.display = this.checked ? 'block' : 'none';
        });

        // Grade thresholds
        document.getElementById('showGrade').addEventListener('change', function() {
            document.getElementById('gradeThresholds').style.display = this.checked ? 'block' : 'none';
        });

        // Question search filter
        document.getElementById('questionSearch')?.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.question-item').forEach(item => {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });

        // Selected question counter
        document.querySelectorAll('[name="selected_questions[]"]').forEach(cb => {
            cb.addEventListener('change', () => {
                const count = document.querySelectorAll('[name="selected_questions[]"]:checked').length;
                const countBadge = document.getElementById('selectedCount');
                if (countBadge) countBadge.textContent = count;
            });
        });
    });

    let customQCount = 0;
    function addCustomQuestion() {
        customQCount++;
        const container = document.getElementById('customQuestionsContainer');
        const html = `
            <div class="custom-q-item bg-light p-3 rounded-4 mb-3 border position-relative animate-in" id="custom_q_${customQCount}">
                <button type="button" class="btn btn-sm btn-link text-danger position-absolute top-0 end-0 m-2" onclick="removeCustomQuestion(${customQCount})">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="small fw-bold text-muted mb-1">Treść pytania #${customQCount}</label>
                        <input type="text" name="custom_questions[${customQCount}][text]" class="form-control form-control-sm" placeholder="Wpisz pytanie..." required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="custom_questions[${customQCount}][a]" class="form-control form-control-sm" placeholder="Opcja A" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="custom_questions[${customQCount}][b]" class="form-control form-control-sm" placeholder="Opcja B" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="custom_questions[${customQCount}][c]" class="form-control form-control-sm" placeholder="Opcja C" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="custom_questions[${customQCount}][d]" class="form-control form-control-sm" placeholder="Opcja D" required>
                    </div>
                    <div class="col-md-3">
                        <select name="custom_questions[${customQCount}][correct]" class="form-select form-select-sm">
                            <option value="A">Poprawna: A</option>
                            <option value="B">Poprawna: B</option>
                            <option value="C">Poprawna: C</option>
                            <option value="D">Poprawna: D</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="custom_questions[${customQCount}][image]" class="form-control form-control-sm" placeholder="URL obrazka (opcjonalnie)">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="custom_questions[${customQCount}][explanation]" class="form-control form-control-sm" placeholder="Wyjaśnienie (opcjonalnie)">
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }

    function removeCustomQuestion(id) {
        document.getElementById(`custom_q_${id}`).remove();
    }

    // Add first custom question by default when switching to custom tab
    document.getElementById('custom-tab').addEventListener('shown.bs.tab', function (e) {
        if (customQCount === 0) addCustomQuestion();
    });
    </script>
</body>
</html>
