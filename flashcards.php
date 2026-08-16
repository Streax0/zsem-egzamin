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

// SM-2: Load spaced-repetition state for current user
$sm2State    = [];
$sm2DueCount = 0;
$sm2ReviewedToday = 0;
if (!$isGuest && isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $sm2Rows = [];
    try {
        $_s2Stmt = $pdo->prepare(
            "SELECT card_key, easiness_factor, interval_days, repetition_count, next_review_date, last_rating
             FROM flashcard_sm2 WHERE user_id = ?"
        );
        $_s2Stmt->execute([$userId]);
        $sm2Rows = $_s2Stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $sm2Rows = []; // Table may not exist yet
    }
    foreach ($sm2Rows as $row) {
        $sm2State[$row['card_key']] = $row;
        if ($row['next_review_date'] <= date('Y-m-d')) {
            $sm2DueCount++;
        }
    }
    // Count reviewed today (updated_at = today, last_rating >= 0)
    try {
        $_trStmt = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM flashcard_sm2
             WHERE user_id = ? AND DATE(updated_at) = CURDATE()"
        );
        $_trStmt->execute([$userId]);
        $todayRow = $_trStmt->fetch(PDO::FETCH_ASSOC);
        $sm2ReviewedToday = (int)($todayRow['cnt'] ?? 0);
    } catch (Throwable $e) {
        $sm2ReviewedToday = 0;
    }
    $sm2MasteredCount = count(array_filter($sm2State, fn($r) => (int)($r['interval_days'] ?? 0) >= 21));
} else {
    $sm2MasteredCount = 0;
}

// Add card_key (md5 of front) to each card for SM-2 tracking
foreach ($cards as &$card) {
    $card['card_key'] = md5($card['front']);
    $sm2 = $sm2State[$card['card_key']] ?? null;
    $card['sm2_due']      = $sm2 ? ($sm2['next_review_date'] <= date('Y-m-d') ? 1 : 0) : 0;
    $card['sm2_interval'] = $sm2 ? (int)$sm2['interval_days'] : 0;
    $card['sm2_reps']     = $sm2 ? (int)$sm2['repetition_count'] : 0;
}
unset($card);
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
                
                <!-- Page Header Banner -->
                <div class="flashcard-hero-banner mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-primary bg-opacity-20 text-primary fw-bold px-3 py-1 rounded-pill">
                                    <i class="bi bi-lightning-charge-fill me-1"></i>Spaced Repetition (SM-2)
                                </span>
                            </div>
                            <h2 class="fw-black mb-1"><i class="bi bi-card-text text-primary me-2"></i>Fiszki Interaktywne</h2>
                            <p class="text-muted mb-0">Ucz się pojęć technicznych i pytań CKE wykorzystując inteligentny algorytm powtórek.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a class="btn btn-outline-primary rounded-pill px-4 shadow-sm fw-bold" href="dictionary.php">
                                <i class="bi bi-book me-1"></i>Słownik Pojęć
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashAlertClass); ?> border-0 rounded-3 shadow-sm mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i><?php echo htmlspecialchars($flashMessage['message'] ?? ''); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Overview Row -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="flashcard-stat-card">
                            <div class="stat-icon bg-primary"><i class="bi bi-layers-fill"></i></div>
                            <div>
                                <div class="stat-value"><?= number_format(count($cards), 0, '', ' ') ?></div>
                                <div class="stat-label">Wszystkie fiszki</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="flashcard-stat-card">
                            <div class="stat-icon bg-success"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <div class="stat-value" id="statMasteredCount"><?= $sm2MasteredCount ?></div>
                                <div class="stat-label">Opanowane (SM-2)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="flashcard-stat-card">
                            <div class="stat-icon bg-warning"><i class="bi bi-clock-history"></i></div>
                            <div>
                                <div class="stat-value" id="statDueCount"><?= $sm2DueCount ?></div>
                                <div class="stat-label">Do powtórki dzisiaj</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="flashcard-stat-card">
                            <div class="stat-icon bg-info"><i class="bi bi-folder-fill"></i></div>
                            <div>
                                <div class="stat-value"><?= count($qualifications) ?></div>
                                <div class="stat-label">Kwalifikacji CKE</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Qualifications Chips Bar -->
                <section class="flashcard-qualification-grid flashcard-qualification-chips mb-4" aria-label="Kwalifikacje fiszek">
                    <div class="chips-scroll-container">
                        <button type="button" class="chip-btn active" data-flashcard-qual-card="all">
                            <i class="bi bi-grid-fill me-1"></i> Wszystkie (<?= count($cards) ?>)
                        </button>
                        <?php foreach ($qualificationCounts as $qual => $count): ?>
                            <button type="button" class="chip-btn" data-flashcard-qual-card="<?php echo htmlspecialchars($qual); ?>">
                                <span><?php echo htmlspecialchars($qual); ?></span>
                                <span class="chip-count"><?php echo (int)$count; ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="flashcard-stage">
                    <div>
                        <!-- Flashcards Tools & Filters Panel -->
                        <div class="flashcard-tools mb-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" for="flashcardSet">Zestaw fiszek</label>
                                    <select id="flashcardSet" class="form-select">
                                        <option value="all">Wszystkie fiszki</option>
                                        <option value="questions">Baza pytań testowych</option>
                                        <option value="dictionary">Słownik pojęć</option>
                                        <option value="wrong">Błędne (z ostatnich 3h)</option>
                                        <option value="due">Do powtórki (Spaced Repetition)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" for="flashcardQual">Kwalifikacja</label>
                                    <select id="flashcardQual" class="form-select">
                                        <option value="all">Wszystkie kwalifikacje</option>
                                        <?php foreach ($qualifications as $qual): ?>
                                            <option value="<?php echo htmlspecialchars($qual); ?>"><?php echo htmlspecialchars($qual); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted" for="flashcardSearch">Szukaj pojęć</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input id="flashcardSearch" class="form-control border-start-0" placeholder="np. IP, DNS, RAM, BIOS...">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-3 border-top border-secondary border-opacity-10">
                                <div class="flashcard-study-builder" aria-label="Kreator nauki">
                                    <button type="button" data-flashcard-study="all" class="active"><i class="bi bi-layers me-1"></i>Wszystkie</button>
                                    <button type="button" data-flashcard-study="mixed"><i class="bi bi-shuffle me-1"></i>Losowe</button>
                                    <button type="button" data-flashcard-study="wrong"><i class="bi bi-arrow-repeat me-1"></i>Powtórka błędnych pojęć</button>
                                </div>

                                <div class="flashcard-difficulty-filter" aria-label="Poziom trudności">
                                    <button type="button" data-flashcard-difficulty="all" class="active">Wszystkie</button>
                                    <button type="button" data-flashcard-difficulty="easy">Łatwe</button>
                                    <button type="button" data-flashcard-difficulty="medium">Średnie</button>
                                    <button type="button" data-flashcard-difficulty="hard">Trudne</button>
                                </div>
                            </div>
                            <div class="flashcard-progress mt-3" data-flashcard-progress aria-live="polite"></div>
                        </div>

                        <!-- 3D Flashcard Deck Stage -->
                        <div id="flashcardStudyShell" class="flashcard-study-shell">
                            <div class="flashcard-deck">
                                <div id="flashcardCard" class="flashcard-card-wrapper" tabindex="0" role="button" aria-live="polite">
                                    <div class="flashcard-card-inner">
                                        <!-- Front Side -->
                                        <div class="flashcard-card-front">
                                            <div class="flashcard-top-bar">
                                                <span class="flashcard-card-kicker"><i class="bi bi-question-circle me-1"></i>POJĘCIE</span>
                                                <span class="badge bg-primary bg-opacity-15 text-primary fw-bold" id="flashcardQualFront">INF.02</span>
                                            </div>
                                            <div class="flashcard-text" id="flashcardFrontText">Pojęcie</div>
                                            <div class="flashcard-hint-text">
                                                <i class="bi bi-hand-index-thumb me-1"></i>Kliknij lub naciśnij <kbd class="kbd-badge">Spacja</kbd>, aby obrócić
                                            </div>
                                            <button type="button" class="btn-tts" id="flashcardTtsFront" title="Odsłuchaj wymowę" aria-label="Odsłuchaj pojęcie">
                                                <i class="bi bi-volume-up-fill"></i>
                                            </button>
                                        </div>
                                        <!-- Back Side -->
                                        <div class="flashcard-card-back">
                                            <div class="flashcard-top-bar">
                                                <span class="flashcard-card-kicker"><i class="bi bi-lightbulb me-1"></i>DEFINICJA & OPIS</span>
                                                <span class="badge bg-success bg-opacity-15 text-success fw-bold" id="flashcardQualBack">INF.02</span>
                                            </div>
                                            <div class="flashcard-text" id="flashcardBackText">Definicja</div>
                                            <button type="button" class="btn-tts" id="flashcardTtsBack" title="Odsłuchaj definicję" aria-label="Odsłuchaj definicję">
                                                <i class="bi bi-volume-up-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Interactive Controls Bar -->
                            <div class="flashcard-controls-panel mb-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <button type="button" class="ctrl-btn" id="flashcardPrev" title="Poprzednia fiszka" aria-label="Poprzednia fiszka">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    
                                    <div class="d-flex flex-column align-items-center flex-grow-1 px-md-4 px-2" style="min-width: 160px;">
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

                        <!-- Touch & Keyboard Rating Hint Row -->
                        <div class="flashcard-hint-row" aria-hidden="true">
                            <span><i class="bi bi-arrow-left"></i> Przesuń w lewo / <kbd class="kbd-badge">1</kbd> = Trudne</span>
                            <span>Średnie = <kbd class="kbd-badge">2</kbd></span>
                            <span>Łatwe = Przesuń w prawo / <kbd class="kbd-badge">3</kbd> <i class="bi bi-arrow-right"></i></span>
                        </div>

                        <!-- SM-2 Rating Actions Bar (4-level) -->
                        <div class="flashcard-actions mt-3" id="sm2RatingBar">
                            <button class="btn btn-outline-danger btn-rating-sm2" data-sm2-rating="0" title="Nie pamiętam — powtórz jutro">
                                <i class="bi bi-arrow-repeat me-1"></i>Znowu <span class="badge bg-danger bg-opacity-20 text-danger ms-1">1</span>
                            </button>
                            <button class="btn btn-outline-warning btn-rating-sm2" data-sm2-rating="1" title="Pamiętam z trudnością">
                                <i class="bi bi-exclamation-circle me-1"></i>Trudne <span class="badge bg-warning bg-opacity-20 text-warning ms-1">2</span>
                            </button>
                            <button class="btn btn-outline-primary btn-rating-sm2" data-sm2-rating="2" title="Pamiętam poprawnie">
                                <i class="bi bi-check-circle me-1"></i>Dobre <span class="badge bg-primary bg-opacity-20 text-primary ms-1">3</span>
                            </button>
                            <button class="btn btn-outline-success btn-rating-sm2" data-sm2-rating="3" title="Pamiętam bez wysiłku">
                                <i class="bi bi-lightning-charge-fill me-1"></i>Łatwe <span class="badge bg-success bg-opacity-20 text-success ms-1">4</span>
                            </button>
                        </div>

                        <!-- Searchable Flashcard List Section -->
                        <div class="flashcard-list-panel mt-4">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <h3 class="fw-bold mb-0 fs-5"><i class="bi bi-list-stars me-2 text-primary"></i>Lista fiszek w zestawie</h3>
                                <span id="flashcardListCount" class="badge bg-secondary bg-opacity-10 text-secondary"></span>
                            </div>
                            <div id="flashcardList" class="flashcard-list"></div>
                            <button type="button" class="btn btn-outline-primary w-100 mt-3 rounded-pill fw-bold" data-flashcard-load-more>Załaduj więcej fiszek</button>
                        </div>
                    </div>

                    <!-- Sidebar Panel -->
                    <div class="flashcard-side">
                        <h3 class="fw-bold mb-3 fs-5"><i class="bi bi-lightbulb me-2 text-warning"></i>Propozycja fiszki</h3>
                        <?php if ($canRequestFlashcard): ?>
                            <form method="POST" class="flashcard-request-form">
                                <?php echo csrfTokenField('flashcard_request'); ?>
                                <input type="hidden" name="action" value="flashcard_request">
                                <div class="mb-2">
                                    <input name="flashcard_front" class="form-control" maxlength="140" placeholder="Przód fiszki (pojęcie)" aria-label="Przód proponowanej fiszki" required>
                                </div>
                                <div class="mb-2">
                                    <textarea name="flashcard_back" class="form-control" rows="4" maxlength="1200" placeholder="Tył fiszki (definicja / opis)" aria-label="Tył proponowanej fiszki" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <input name="flashcard_qualification" class="form-control" maxlength="40" placeholder="Kwalifikacja, np. INF.02" aria-label="Kwalifikacja proponowanej fiszki">
                                </div>
                                <button type="submit" class="btn btn-primary w-100 rounded-pill shadow-sm fw-bold">
                                    <i class="bi bi-send me-1"></i>Wyślij do moderatora
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="flashcard-request-note">
                                <i class="bi bi-shield-check text-primary me-1"></i>
                                Fiszki są moderowane przez nauczycieli i administrację ZSEM. Uczniowie korzystają ze sprawdzonych zestawów CKE.
                            </div>
                        <?php endif; ?>

                        <hr class="my-3">
                        <div class="small text-muted mb-3" id="flashcardMeta"></div>
                        
                        <h4 class="fw-bold fs-6 mb-2"><i class="bi bi-keyboard me-2 text-primary"></i>Skróty klawiszowe</h4>
                        <div class="flashcard-shortcuts mb-3" aria-label="Skróty klawiaturowe">
                            <span><kbd class="kbd-badge">Spacja</kbd> obrót</span>
                            <span><kbd class="kbd-badge">1</kbd> trudne</span>
                            <span><kbd class="kbd-badge">2</kbd> średnie</span>
                            <span><kbd class="kbd-badge">3</kbd> łatwe</span>
                            <span><kbd class="kbd-badge">F</kbd> pełny ekran</span>
                        </div>

                        <div class="d-flex gap-2">
                            <a id="flashcardWiki" class="btn btn-sm btn-outline-primary flex-fill rounded-pill" target="_blank" rel="noopener" aria-label="Wikipedia">
                                <i class="bi bi-wikipedia me-1"></i>Wikipedia
                            </a>
                            <a id="flashcardYoutube" class="btn btn-sm btn-outline-danger flex-fill rounded-pill" target="_blank" rel="noopener" aria-label="YouTube">
                                <i class="bi bi-youtube me-1"></i>YouTube
                            </a>
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
    cards: <?php echo json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    sm2DueCount: <?= (int)$sm2DueCount ?>,
    isGuest: <?= $isGuest ? 'true' : 'false' ?>
};
</script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/flashcards.js')); ?>"></script>
<script>
// SM-2 spaced repetition AJAX integration
(function () {
    'use strict';
    const ratingBar = document.getElementById('sm2RatingBar');
    if (!ratingBar || window.zsemFlashcards.isGuest) return;

    // Get current card_key from the deck state exposed by flashcards.js
    function getCurrentCardKey() {
        const idx = window._flashcardDeckIndex;
        if (idx == null) return null;
        const cards = window.zsemFlashcards.cards;
        return cards[idx] ? cards[idx].card_key : null;
    }

    ratingBar.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-sm2-rating]');
        if (!btn) return;
        const rating  = parseInt(btn.dataset.sm2Rating, 10);
        const cardKey = getCurrentCardKey();
        if (!cardKey) return;

        // Visual feedback
        btn.disabled = true;
        btn.classList.add('active');

        try {
            const fd = new FormData();
            fd.append('card_key', cardKey);
            fd.append('rating', rating);
            const res  = await fetch('actions/flashcard_rate.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                // Update card sm2 metadata in memory
                const idx = window._flashcardDeckIndex;
                if (idx != null && window.zsemFlashcards.cards[idx]) {
                    window.zsemFlashcards.cards[idx].sm2_interval = data.interval;
                    window.zsemFlashcards.cards[idx].sm2_reps     = data.repetitions;
                    window.zsemFlashcards.cards[idx].sm2_due      = 0;
                }
                // Show toast
                const msg = `⏱ Następna powtórka za ${data.interval} ${data.interval === 1 ? 'dzień' : 'dni'}`;
                showSm2Toast(msg, rating >= 2 ? 'success' : 'warning');
            }
        } catch (err) {
            console.warn('SM-2 sync error:', err);
        } finally {
            setTimeout(() => {
                btn.disabled = false;
                btn.classList.remove('active');
            }, 800);
        }
    });

    function showSm2Toast(message, type) {
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
            background:${type==='success'?'#16a34a':'#d97706'};color:#fff;
            padding:.6rem 1.2rem;border-radius:12px;font-size:.85rem;font-weight:600;
            box-shadow:0 4px 16px rgba(0,0,0,.18);animation:fadeInUp .3s ease`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
}());
</script>
</body>
</html>
