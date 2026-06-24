<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin(true);

$role = $_SESSION['role'] ?? 'guest';
$isGuest = isGuestMode();
$canRequestFlashcard = !$isGuest && in_array($role, teacherPanelRoleValues(), true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'flashcard_request') {
    if (!$canRequestFlashcard) {
        setSessionMessage('error', 'Tylko nauczyciele mogą wysyłać propozycje fiszek do administracji.');
        header('Location: flashcards.php');
        exit;
    }
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'flashcard_request')) {
        setSessionMessage('error', 'Sesja wygasła. Spróbuj ponownie.');
        header('Location: flashcards.php');
        exit;
    }
    $front = trim((string)($_POST['flashcard_front'] ?? ''));
    $back = trim((string)($_POST['flashcard_back'] ?? ''));
    $qualificationRequest = trim((string)($_POST['flashcard_qualification'] ?? ''));
    if ($front === '' || $back === '') {
        setSessionMessage('error', 'Uzupełnij przód i tył proponowanej fiszki.');
    } else {
        $subject = 'Propozycja fiszki: ' . mb_substr($front, 0, 120);
        $message = "Kwalifikacja: " . ($qualificationRequest !== '' ? $qualificationRequest : 'nie podano')
            . "\n\nPrzód fiszki:\n" . $front
            . "\n\nTył fiszki:\n" . $back;
        $requestId = createAdminRequest($pdo, (int)($_SESSION['user_id'] ?? 0), $subject, $message, 'flashcard_request');
        setSessionMessage($requestId > 0 ? 'success' : 'error', $requestId > 0 ? 'Propozycja fiszki została wysłana do administracji.' : 'Nie udało się wysłać propozycji. Sprawdź treść i spróbuj ponownie.');
    }
    header('Location: flashcards.php');
    exit;
}

$dictionaryFile = __DIR__ . '/data/dictionary.json';
$dictionaryData = [];
if (is_file($dictionaryFile)) {
    // Optimization: Cache parsed dictionary JSON using APCu to avoid synchronous file read and JSON decoding on every request.
    $cacheKey = 'dictionary_data_' . filemtime($dictionaryFile);
    if (function_exists('apcu_fetch')) {
        $dictionaryData = apcu_fetch($cacheKey, $success);
        if (!$success) {
            $dictionaryData = json_decode((string)file_get_contents($dictionaryFile), true) ?: [];
            apcu_store($cacheKey, $dictionaryData);
        }
    } else {
        $dictionaryData = json_decode((string)file_get_contents($dictionaryFile), true) ?: [];
    }
}

$cards = [];
function flashcardDifficulty(string $front, string $back, string $source): string {
    $weight = mb_strlen($front . ' ' . $back, 'UTF-8') + ($source === 'Baza pytań' ? 120 : 0);
    if ($weight > 520) return 'hard';
    if ($weight > 260) return 'medium';
    return 'easy';
}
foreach ($dictionaryData as $group) {
    foreach (($group['terms'] ?? []) as $term) {
        $front = (string)($term['term'] ?? '');
        if ($front === '') continue;
        $source = 'Słownik';
        $back = trim((string)($term['definition'] ?? '') . "\n\n" . (string)($term['example'] ?? ''));
        $cards[] = [
            'qualification' => (string)($group['qualification'] ?? ''),
            'front' => $front,
            'back' => $back,
            'source' => $source,
            'difficulty' => flashcardDifficulty($front, $back, $source),
            'wiki' => (string)($term['link'] ?? ''),
            'youtube' => 'https://www.youtube.com/results?search_query=' . rawurlencode($front . ' informatyka')
        ];
    }
}

foreach (loadQuestions($pdo) as $question) {
    $front = trim((string)($question['question_text'] ?? ''));
    $correct = strtoupper(trim((string)($question['correct_answer'] ?? '')));
    if ($front === '' || $correct === '') continue;
    if (trim((string)($question['image_url'] ?? '')) !== '') continue;
    if (mb_strlen($front, 'UTF-8') > 320) continue;
    $correctText = answerOptionText($question, $correct);
    if ($correctText === '') continue;
    $source = 'Baza pytań';
    $back = "Poprawna odpowiedź: {$correct}" . ($correctText !== '' ? " - {$correctText}" : '') . "\n\n" . buildQuestionExplanation($question);
    $cards[] = [
        'qualification' => (string)($question['category'] ?? 'Testy'),
        'front' => $front,
        'back' => $back,
        'source' => $source,
        'difficulty' => flashcardDifficulty($front, $back, $source),
        'wiki' => '',
        'youtube' => 'https://www.youtube.com/results?search_query=' . rawurlencode($front . ' egzamin zawodowy informatyka')
    ];
}

$qualifications = array_values(array_unique(array_filter(array_map(static fn($card) => (string)($card['qualification'] ?? ''), $cards))));
sort($qualifications, SORT_NATURAL | SORT_FLAG_CASE);
$qualificationCounts = [];
foreach ($cards as $card) {
    $qual = (string)($card['qualification'] ?? '');
    if ($qual === '') continue;
    $qualificationCounts[$qual] = ($qualificationCounts[$qual] ?? 0) + 1;
}
arsort($qualificationCounts);
$flashMessage = getSessionMessage();
$flashAlertClass = 'info';
if ($flashMessage) {
    $flashType = (string)($flashMessage['type'] ?? 'info');
    $flashAlertClass = $flashType === 'error' ? 'danger' : (in_array($flashType, ['success', 'warning', 'info'], true) ? $flashType : 'info');
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiszki - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/flashcards.css')); ?>">
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 flashcard-shell">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-card-text text-primary me-2"></i>Fiszki</h2>
                        <p class="text-muted mb-0">Słownik pojęć i wybrane pytania testowe jako fiszki z powtórkami spaced repetition.</p>
                    </div>
                    <a class="btn btn-outline-primary rounded-pill px-4" href="dictionary.php"><i class="bi bi-book me-1"></i>Słownik</a>
                </div>
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashAlertClass); ?> border-0 rounded-3">
                        <?php echo htmlspecialchars($flashMessage['message'] ?? ''); ?>
                    </div>
                <?php endif; ?>
                <section class="flashcard-qualification-grid" aria-label="Kwalifikacje fiszek">
                    <?php foreach (array_slice($qualificationCounts, 0, 8, true) as $qual => $count): ?>
                        <button type="button" data-flashcard-qual-card="<?php echo htmlspecialchars($qual); ?>">
                            <?php echo htmlspecialchars($qual); ?>
                            <span><?php echo (int)$count; ?> fiszek</span>
                        </button>
                    <?php endforeach; ?>
                </section>

                <section class="flashcard-stage">
                    <div>
                        <div class="flashcard-tools mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold" for="flashcardSet">Zestaw</label>
                                    <select id="flashcardSet" class="form-select">
                                        <option value="all">Wszystkie</option>
                                        <option value="questions">Baza pytań</option>
                                        <option value="dictionary">Słownik</option>
                                        <option value="wrong">Tylko błędne z 3h</option>
                                        <option value="due">Do powtórki teraz</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold" for="flashcardQual">Kwalifikacja</label>
                                    <select id="flashcardQual" class="form-select">
                                        <option value="all">Wszystkie</option>
                                        <?php foreach ($qualifications as $qual): ?>
                                            <option value="<?php echo htmlspecialchars($qual); ?>"><?php echo htmlspecialchars($qual); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold" for="flashcardSearch">Szukaj</label>
                                    <input id="flashcardSearch" class="form-control" placeholder="Adres IP, DNS, BIOS...">
                                </div>
                            </div>
                            <div class="flashcard-study-builder mt-3" aria-label="Kreator nauki">
                                <button type="button" data-flashcard-study="all" class="active"><i class="bi bi-layers me-1"></i>Wszystkie tematy</button>
                                <button type="button" data-flashcard-study="mixed"><i class="bi bi-shuffle me-1"></i>Mieszane źródła</button>
                                <button type="button" data-flashcard-study="wrong"><i class="bi bi-arrow-repeat me-1"></i>Powtórka błędnych pojęć</button>
                            </div>
                            <div class="flashcard-difficulty-filter mt-3" aria-label="Poziom trudności">
                                <button type="button" data-flashcard-difficulty="all" class="active">Wszystkie</button>
                                <button type="button" data-flashcard-difficulty="easy">Łatwe</button>
                                <button type="button" data-flashcard-difficulty="medium">Średnie</button>
                                <button type="button" data-flashcard-difficulty="hard">Trudne</button>
                            </div>
                            <div class="flashcard-progress" data-flashcard-progress aria-live="polite"></div>
                        </div>
                        <div id="flashcardStudyShell" class="flashcard-study-shell">
                            <div class="flashcard-deck">
                                <div id="flashcardCard" class="flashcard-card-wrapper" tabindex="0" role="button" aria-live="polite">
                                    <div class="flashcard-card-inner">
                                        <div class="flashcard-card-front">
                                            <span class="flashcard-card-kicker">POJĘCIE</span>
                                            <strong class="flashcard-text" id="flashcardFrontText">Pojęcie</strong>
                                            <button type="button" class="btn-tts" id="flashcardTtsFront" title="Odsłuchaj pojęcie" aria-label="Odsłuchaj pojęcie">
                                                <i class="bi bi-volume-up-fill"></i>
                                            </button>
                                        </div>
                                        <div class="flashcard-card-back">
                                            <span class="flashcard-card-kicker">DEFINICJA</span>
                                            <div class="flashcard-text" id="flashcardBackText">Definicja</div>
                                            <button type="button" class="btn-tts" id="flashcardTtsBack" title="Odsłuchaj definicję" aria-label="Odsłuchaj definicję">
                                                <i class="bi bi-volume-up-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flashcard-controls-panel mb-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <button type="button" class="ctrl-btn" id="flashcardPrev" title="Poprzednia fiszka" aria-label="Poprzednia fiszka">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    
                                    <div class="d-flex flex-column align-items-center flex-grow-1 px-md-4 px-2" style="min-width: 140px;">
                                        <span class="flashcard-index-counter fw-bold mb-2" id="flashcardCounter">0 / 0</span>
                                        <div class="flashcard-progress-track">
                                            <div class="flashcard-progress-fill" id="flashcardProgressBar"></div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="button" class="ctrl-btn" id="flashcardPlay" title="Autoodtwarzanie" aria-label="Włącz autoodtwarzanie">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                        <button type="button" class="ctrl-btn" id="flashcardNext" title="Następna fiszka" aria-label="Następna fiszka">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                        <button type="button" class="ctrl-btn" id="flashcardFullscreen" title="Pełny ekran" aria-label="Tryb pełnoekranowy">
                                            <i class="bi bi-arrows-fullscreen"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flashcard-hint-row" aria-hidden="true">
                            <span><i class="bi bi-arrow-left"></i> przesuń w lewo = źle</span>
                            <span>dobrze = przesuń w prawo <i class="bi bi-arrow-right"></i></span>
                        </div>
                        <div class="flashcard-actions mt-3">
                            <button class="btn btn-outline-danger" data-rate="hard"><i class="bi bi-arrow-repeat me-1"></i>Trudne</button>
                            <button class="btn btn-outline-primary" data-rate="medium"><i class="bi bi-clock-history me-1"></i>Średnie</button>
                            <button class="btn btn-outline-success" data-rate="easy"><i class="bi bi-check2-circle me-1"></i>Łatwe</button>
                        </div>
                        <div class="flashcard-list-panel mt-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <h3 class="fw-bold mb-0 fs-5">Lista fiszek</h3>
                                <span id="flashcardListCount" class="small text-muted"></span>
                            </div>
                            <div id="flashcardList" class="flashcard-list"></div>
                            <button type="button" class="btn btn-outline-primary w-100 mt-3" data-flashcard-load-more>Załaduj więcej</button>
                        </div>
                    </div>
                    <div class="flashcard-side">
                        <h3 class="fw-bold mb-3 fs-5">Propozycja fiszki</h3>
                        <?php if ($canRequestFlashcard): ?>
                            <form method="POST" class="flashcard-request-form">
                                <?php echo csrfTokenField('flashcard_request'); ?>
                                <input type="hidden" name="action" value="flashcard_request">
                                <input name="flashcard_front" class="form-control" maxlength="140" placeholder="Przód fiszki" aria-label="Przód proponowanej fiszki" required>
                                <textarea name="flashcard_back" class="form-control" rows="5" maxlength="1200" placeholder="Tył fiszki" aria-label="Tył proponowanej fiszki" required></textarea>
                                <input name="flashcard_qualification" class="form-control" maxlength="40" placeholder="Kwalifikacja, np. INF.02" aria-label="Kwalifikacja proponowanej fiszki" >
                                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Wyślij do admina</button>
                            </form>
                        <?php else: ?>
                            <div class="flashcard-request-note">
                                Fiszki są teraz moderowane. Uczniowie korzystają z zatwierdzonych kart, a nauczyciele wysyłają propozycje do administracji.
                            </div>
                        <?php endif; ?>
                        <hr>
                        <div class="small text-muted" id="flashcardMeta"></div>
                        <div class="flashcard-shortcuts" aria-label="Skróty klawiaturowe">
                            <span>Spacja: obrót</span>
                            <span>←: trudne</span>
                            <span>→: łatwe</span>
                            <span>↓: średnie</span>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <a id="flashcardWiki" class="btn btn-sm btn-outline-primary flex-fill" target="_blank" rel="noopener" aria-label="Wikipedia"><i class="bi bi-wikipedia" aria-hidden="true"></i></a>
                            <a id="flashcardYoutube" class="btn btn-sm btn-outline-danger flex-fill" target="_blank" rel="noopener" aria-label="YouTube"><i class="bi bi-youtube" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </section>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
<script>
window.zsemFlashcards = {
    cards: <?php echo json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};
</script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/flashcards.js')); ?>"></script>
</body>
</html>
