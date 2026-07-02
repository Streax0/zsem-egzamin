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
$customDir = __DIR__ . '/../data/custom_tests';
if (!is_dir($customDir)) mkdir($customDir, 0755, true);

$editFile = basename($_GET['file'] ?? '');
$isEdit = false;
$exam = [
    'title' => '',
    'description' => '',
    'time_limit' => 45,
    'pass_threshold' => 50,
    'difficulty' => 'mixed',
    'shuffle_questions' => true,
    'shuffle_answers' => false,
    'show_answers_after' => false,
    'tags' => [],
    'questions' => []
];

if ($editFile) {
    $path = $customDir . '/' . $editFile;
    if (file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
        if ($data && ($data['teacher_id'] ?? 0) == $userId) {
            $exam = $data;
            $isEdit = true;
        }
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('custom_exams.php');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $timeLimit = max(0, min(240, (int)($_POST['time_limit'] ?? 45)));
    $passThreshold = max(0, min(100, (int)($_POST['pass_threshold'] ?? 50)));
    $difficulty = in_array($_POST['difficulty'] ?? 'mixed', ['easy','medium','hard','mixed'], true) ? $_POST['difficulty'] : 'mixed';
    $shuffleQuestions = isset($_POST['shuffle_questions']);
    $shuffleAnswers = isset($_POST['shuffle_answers']);
    $showAnswersAfter = isset($_POST['show_answers_after']);
    $tags = array_values(array_filter(array_map(static function ($tag) {
        $tag = trim((string)$tag);
        return preg_match('/^[\p{L}\p{N} ._-]{1,32}$/u', $tag) ? $tag : '';
    }, explode(',', (string)($_POST['tags'] ?? '')))));
    $tags = array_slice(array_unique($tags), 0, 12);
    if (empty($title)) {
        setSessionMessage('error', 'Tytuł jest wymagany.');
        redirect($isEdit ? 'custom_exam_edit.php?file=' . urlencode($editFile) : 'custom_exam_edit.php');
    }
    if (containsProfanity($title) || containsProfanity($description) || containsProfanity(implode(' ', $tags))) {
        setSessionMessage('error', 'Tytuł, opis lub tagi zawierają niedozwolone słowa.');
        redirect($isEdit ? 'custom_exam_edit.php?file=' . urlencode($editFile) : 'custom_exam_edit.php');
    }

    $questions = [];
    foreach ($_POST['questions'] ?? [] as $q) {
        $text = mb_substr(trim((string)($q['text'] ?? '')), 0, 1000);
        if (empty($text)) continue;
        $answers = [
            'a' => mb_substr(trim((string)($q['a'] ?? '')), 0, 500),
            'b' => mb_substr(trim((string)($q['b'] ?? '')), 0, 500),
            'c' => mb_substr(trim((string)($q['c'] ?? '')), 0, 500),
            'd' => mb_substr(trim((string)($q['d'] ?? '')), 0, 500),
        ];
        $explanation = mb_substr(trim((string)($q['explanation'] ?? '')), 0, 1200);
        if (containsProfanity($text . ' ' . implode(' ', $answers) . ' ' . $explanation)) continue;
        if (count(array_filter($answers, static fn($answer) => $answer !== '')) < 4) continue;
        $correct = strtoupper(trim((string)($q['correct'] ?? 'A')));
        if (!in_array($correct, ['A','B','C','D'], true)) $correct = 'A';
        $questions[] = [
            'text' => $text,
            'a' => $answers['a'],
            'b' => $answers['b'],
            'c' => $answers['c'],
            'd' => $answers['d'],
            'correct' => $correct,
            'image' => sanitizeQuestionImageUrl($q['image'] ?? ''),
            'explanation' => $explanation
        ];
        if (count($questions) >= 120) break;
    }

    if (count($questions) === 0) {
        setSessionMessage('error', 'Dodaj przynajmniej jedno poprawne pytanie.');
        redirect($isEdit ? 'custom_exam_edit.php?file=' . urlencode($editFile) : 'custom_exam_edit.php');
    }

    $saveData = [
        'teacher_id' => $userId,
        'title' => $title,
        'description' => $description,
        'time_limit' => $timeLimit,
        'pass_threshold' => $passThreshold,
        'difficulty' => $difficulty,
        'shuffle_questions' => $shuffleQuestions,
        'shuffle_answers' => $shuffleAnswers,
        'show_answers_after' => $showAnswersAfter,
        'tags' => $tags,
        'created_at' => $isEdit ? ($exam['created_at'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'questions' => $questions
    ];

    if ($isEdit && $editFile) {
        $filename = $editFile;
    } else {
        $slug = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($title));
        $filename = $userId . '_' . $slug . '_' . time() . '.json';
    }

    file_put_contents($customDir . '/' . $filename, json_encode($saveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    setSessionMessage('success', $isEdit ? 'Sprawdzian zaktualizowany.' : 'Sprawdzian zapisany.');
    redirect('custom_exams.php');
}

$flashMsg = getSessionMessage();
?>
<?php
$pageTitle = '<?= $isEdit ? 'Edytuj' : 'Nowy' ?> sprawdzian – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        .q-card { border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem; margin-bottom: 1rem; background: #fafbfc; transition: all 0.2s; position: relative; }
        .q-card:hover { border-color: var(--primary-color); box-shadow: 0 4px 12px rgba(59,130,246,0.08); }
        .q-card .q-number { position: absolute; top: -10px; left: 16px; background: var(--primary-color); color: white; border-radius: 99px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
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
                            <h2 class="fw-bold mb-1">
                                <i class="bi bi-<?= $isEdit ? 'pencil' : 'plus-circle-fill' ?> me-2 text-primary"></i>
                                <?= $isEdit ? 'Edytuj sprawdzian' : 'Nowy sprawdzian' ?>
                            </h2>
                            <p class="text-muted">Dodaj tytuł i pytania do sprawdzianu. Zapisywane jako plik JSON.</p>
                        </div>
                        <a href="custom_exams.php" class="btn btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i>Powrót
                        </a>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4">
                            <?= htmlspecialchars($flashMsg['message']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="examForm">
                        <?= csrfTokenField() ?>

                        <div class="dashboard-panel mb-4 animate-in">
                            <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Informacje</h5>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Tytuł sprawdzianu</label>
                                    <input type="text" name="title" class="form-control form-control-lg" maxlength="120" placeholder="np. Test z rozdziału 3" value="<?= htmlspecialchars($exam['title']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Opis (opcjonalnie)</label>
                                    <input type="text" name="description" class="form-control form-control-lg" maxlength="500" placeholder="Krótki opis..." value="<?= htmlspecialchars($exam['description']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Czas (min, 0 = bez limitu)</label>
                                    <input type="number" name="time_limit" class="form-control" min="0" max="240" value="<?= (int)($exam['time_limit'] ?? 45) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Próg zaliczenia (%)</label>
                                    <input type="number" name="pass_threshold" class="form-control" min="0" max="100" value="<?= (int)($exam['pass_threshold'] ?? 50) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Poziom</label>
                                    <select name="difficulty" class="form-select">
                                        <?php foreach (['mixed' => 'Mieszany', 'easy' => 'Łatwy', 'medium' => 'Średni', 'hard' => 'Trudny'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= ($exam['difficulty'] ?? 'mixed') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Tagi</label>
                                    <input type="text" name="tags" class="form-control" maxlength="240" placeholder="np. PHP, SQL" value="<?= htmlspecialchars(implode(', ', $exam['tags'] ?? [])) ?>">
                                </div>
                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shuffle_questions" id="shuffleQuestions" <?= !empty($exam['shuffle_questions']) ? 'checked' : '' ?>><label class="form-check-label" for="shuffleQuestions">Mieszaj pytania</label></div>
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shuffle_answers" id="shuffleAnswers" <?= !empty($exam['shuffle_answers']) ? 'checked' : '' ?>><label class="form-check-label" for="shuffleAnswers">Mieszaj odpowiedzi</label></div>
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_answers_after" id="showAnswersAfter" <?= !empty($exam['show_answers_after']) ? 'checked' : '' ?>><label class="form-check-label" for="showAnswersAfter">Pokaż odpowiedzi po zakończeniu</label></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-panel mb-4 animate-in" style="animation-delay: 0.1s">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0"><i class="bi bi-question-circle me-2 text-primary"></i>Pytania <span class="badge bg-primary rounded-pill ms-2" id="qCountBadge">0</span></h5>
                            </div>
                            <div id="questionsContainer"></div>
                            <button type="button" class="btn btn-outline-primary rounded-pill px-4 mt-2" onclick="addQuestion()">
                                <i class="bi bi-plus-lg me-1"></i>Dodaj pytanie
                            </button>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <div class="text-muted small">Pytań: <strong id="qCountFooter">0</strong></div>
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow">
                                <i class="bi bi-check-lg me-2"></i><?= $isEdit ? 'Zapisz zmiany' : 'Zapisz sprawdzian' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    let qCount = 0;
    function addQuestion(data) {
        qCount++;
        const n = qCount;
        const c = document.getElementById('questionsContainer');
        const d = data || {};
        c.insertAdjacentHTML('beforeend', `
            <div class="q-card" id="q_${n}">
                <span class="q-number">${n}</span>
                <button type="button" class="btn btn-sm btn-link text-danger position-absolute top-0 end-0 m-2" onclick="removeQuestion(${n})"><i class="bi bi-x-circle-fill fs-5"></i></button>
                <div class="row g-2 mt-1">
                    <div class="col-12"><input type="text" name="questions[${n}][text]" class="form-control" placeholder="Treść pytania..." value="${esc(d.text)}"></div>
                    <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text fw-bold text-primary">A</span><input type="text" name="questions[${n}][a]" class="form-control" placeholder="Odpowiedź A" value="${esc(d.a)}"></div></div>
                    <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text fw-bold text-primary">B</span><input type="text" name="questions[${n}][b]" class="form-control" placeholder="Odpowiedź B" value="${esc(d.b)}"></div></div>
                    <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text fw-bold text-primary">C</span><input type="text" name="questions[${n}][c]" class="form-control" placeholder="Odpowiedź C" value="${esc(d.c)}"></div></div>
                    <div class="col-md-6"><div class="input-group input-group-sm"><span class="input-group-text fw-bold text-primary">D</span><input type="text" name="questions[${n}][d]" class="form-control" placeholder="Odpowiedź D" value="${esc(d.d)}"></div></div>
                    <div class="col-md-3"><select name="questions[${n}][correct]" class="form-select form-select-sm">
                        <option value="A" ${d.correct==='A'?'selected':''}>Poprawna: A</option><option value="B" ${d.correct==='B'?'selected':''}>Poprawna: B</option>
                        <option value="C" ${d.correct==='C'?'selected':''}>Poprawna: C</option><option value="D" ${d.correct==='D'?'selected':''}>Poprawna: D</option>
                    </select></div>
                    <div class="col-md-4"><input type="text" name="questions[${n}][image]" class="form-control form-control-sm" placeholder="URL obrazka (opcja)" value="${esc(d.image)}"></div>
                    <div class="col-md-5"><input type="text" name="questions[${n}][explanation]" class="form-control form-control-sm" placeholder="Wyjaśnienie (opcja)" value="${esc(d.explanation)}"></div>
                </div>
            </div>`);
        updateCount();
    }
    function removeQuestion(id) { document.getElementById('q_'+id).remove(); updateCount(); renumber(); }
    function updateCount() {
        const c = document.querySelectorAll('.q-card').length;
        document.getElementById('qCountBadge').textContent = c;
        document.getElementById('qCountFooter').textContent = c;
    }
    function renumber() { document.querySelectorAll('.q-card .q-number').forEach((el,i) => el.textContent = i+1); }
    function esc(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

    // Load existing questions
    <?php if (!empty($exam['questions'])): ?>
    (function(){
        const qs = <?= json_encode($exam['questions'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        qs.forEach(q => addQuestion(q));
    })();
    <?php else: ?>
    addQuestion();
    <?php endif; ?>
    </script>
</body>
</html>
