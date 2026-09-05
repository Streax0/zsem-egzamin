<?php
// Include necessary files
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Start secure session and require login
startSecureSession();
requireLogin();
ensurePlatformEnhancements($pdo);

// Get current user ID
$userId = $_SESSION['user_id'];

// Load all visible student questions (exclude admin/DB-only custom question bank entries)
$allQuestions = loadQuestions($pdo, false);
$totalQuestions = count($allQuestions);

// Get user progress data
$userProgress = [];
try {
    $progressStmt = $pdo->prepare("
        SELECT question_id, times_seen, times_correct, last_seen, is_mastered
        FROM user_question_progress
        WHERE user_id = ?
    ");
    $progressStmt->execute([$userId]);
    while ($row = $progressStmt->fetch(PDO::FETCH_ASSOC)) {
        $userProgress[$row['question_id']] = $row;
    }
} catch (PDOException $e) {
    error_log('Progress page progress query failed: ' . $e->getMessage());
    $userProgress = [];
}

// Calculate overall progress from mastered questions, not only seen rows.
$questionsWithProgress = count(array_filter($userProgress, static fn($row) => (int)($row['is_mastered'] ?? 0) === 1));
$overallPercentage = $totalQuestions > 0 ? ($questionsWithProgress / $totalQuestions) * 100 : 0;

// Detailed metrics calculation for Bento Stats
$masteredCount = $questionsWithProgress;
$inProgressCount = 0;
$notSeenCount = 0;
$totalAttempts = 0;
$totalCorrectAttempts = 0;

foreach ($allQuestions as $q) {
    $p = $userProgress[$q['id']] ?? null;
    $seen = (int)($p['times_seen'] ?? 0);
    $correct = (int)($p['times_correct'] ?? 0);
    $mastered = (int)($p['is_mastered'] ?? 0) === 1;

    if ($seen === 0) {
        $notSeenCount++;
    } elseif ($mastered) {
        // counted in masteredCount
    } else {
        $inProgressCount++;
    }
    $totalAttempts += $seen;
    $totalCorrectAttempts += $correct;
}

$globalAccuracy = $totalAttempts > 0 ? round(($totalCorrectAttempts / $totalAttempts) * 100, 1) : 0;
$masteredPct = $totalQuestions > 0 ? round(($masteredCount / $totalQuestions) * 100, 1) : 0;
$inProgressPct = $totalQuestions > 0 ? round(($inProgressCount / $totalQuestions) * 100, 1) : 0;

// Radial SVG circle calculations
$radius = 44;
$circumference = 2 * M_PI * $radius; // ~276.46
$strokeDashoffset = $circumference * (1 - min(100, max(0, $overallPercentage)) / 100);

// Milestone badge
if ($overallPercentage >= 90) {
    $milestoneLabel = 'Mistrz Egzaminu';
    $milestoneClass = 'bg-success text-white';
} elseif ($overallPercentage >= 70) {
    $milestoneLabel = 'Zaawansowany';
    $milestoneClass = 'bg-primary text-white';
} elseif ($overallPercentage >= 40) {
    $milestoneLabel = 'Praktyk CKE';
    $milestoneClass = 'bg-info text-dark';
} elseif ($overallPercentage >= 15) {
    $milestoneLabel = 'W drodze';
    $milestoneClass = 'bg-warning text-dark';
} else {
    $milestoneLabel = 'Początkujący';
    $milestoneClass = 'bg-secondary text-white';
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Get all categories from visible questions only
$allCategories = [];
foreach ($allQuestions as $question) {
    $catName = $question['category'] ?? 'Nieznana';
    if (!in_array($catName, $allCategories, true)) {
        $allCategories[] = $catName;
    }
}
sort($allCategories);

// Filter questions
$filteredQuestions = [];
foreach ($allQuestions as $question) {
    $qId = $question['id'];
    $progress = $userProgress[$qId] ?? null;

    // Apply status filter
    if ($filter === 'not_seen' && $progress !== null) continue;
    if ($filter === 'in_progress' && ($progress === null || $progress['is_mastered'])) continue;
    if ($filter === 'mastered' && ($progress === null || !$progress['is_mastered'])) continue;

    // Apply category filter (categories are strings, not IDs)
    if ($categoryFilter !== 'all' && (string)($question['category'] ?? '') !== $categoryFilter) continue;

    // Apply search filter
    if ($search !== '' && stripos($question['question_text'], $search) === false) continue;

    $filteredQuestions[] = $question;
}

// Calculate global category stats (always for all questions)
$globalCategoryStats = [];
foreach ($allQuestions as $question) {
    $catName = $question['category'] ?? 'Nieznana';
    if (!isset($globalCategoryStats[$catName])) {
        $globalCategoryStats[$catName] = ['total' => 0, 'seen' => 0, 'correct' => 0, 'mastered' => 0];
    }
    $globalCategoryStats[$catName]['total']++;

    if (isset($userProgress[$question['id']])) {
        $questionProgress = $userProgress[$question['id']];
        if ((int)$questionProgress['times_seen'] > 0) {
            $globalCategoryStats[$catName]['seen']++;
        }
        if ((int)$questionProgress['times_correct'] > 0) {
            $globalCategoryStats[$catName]['correct']++;
        }
        if ((int)$questionProgress['is_mastered'] === 1) {
            $globalCategoryStats[$catName]['mastered']++;
        }
    }
}

// Sort questions by weakest first (not mastered first)
usort($filteredQuestions, function($a, $b) use ($userProgress) {
    $progressA = $userProgress[$a['id']] ?? null;
    $progressB = $userProgress[$b['id']] ?? null;
    $masteredA = $progressA && $progressA['is_mastered'];
    $masteredB = $progressB && $progressB['is_mastered'];
    if ($masteredA == $masteredB) return 0;
    return $masteredA ? 1 : -1;
});

$pageTitle = 'Postęp w nauce';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
    .progress-bar { transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
    .status-badge { font-size: 0.75rem; padding: 0.4em 0.8em; border-radius: 8px; font-weight: 600; }
    .question-item { cursor: pointer; transition: background 0.2s; }
    .question-item:hover { background-color: rgba(59, 130, 246, 0.04) !important; }
    .dashboard-panel { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .category-badge { font-size: 0.65rem; padding: 0.2rem 0.5rem; border-radius: 6px; }
    .stat-highlight { font-size: 2rem; font-weight: 800; line-height: 1; }
    .question-details {
        background: var(--panel-bg, #fff) !important;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 16px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .08);
        color: var(--text-main, #0f172a);
    }
    .question-details h6 {
        font-weight: 800;
        line-height: 1.45;
        margin-bottom: 1rem;
        color: var(--text-main, #0f172a);
    }
    .question-details .form-check {
        margin: 0 0 .55rem;
        padding: .7rem .85rem .7rem 2.35rem;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 12px;
        background: color-mix(in srgb, var(--panel-bg, #fff) 92%, #f8fafc);
    }
    .question-details .form-check-label {
        color: var(--text-main, #0f172a);
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .question-details .alert-info {
        border: 1px solid rgba(37, 99, 235, .16);
        background: rgba(37, 99, 235, .08);
        color: var(--text-main, #0f172a);
    }
    body.dark-mode .question-details {
        background: #111827 !important;
        border-color: rgba(148, 163, 184, .22);
        box-shadow: 0 16px 34px rgba(0, 0, 0, .26);
    }
    body.dark-mode .question-details .form-check {
        background: rgba(15, 23, 42, .58);
        border-color: rgba(148, 163, 184, .22);
    }
</style>
HTML;
include '../includes/header.php';
?>    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">

                    <!-- Header Breadcrumb Strip -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill px-3 py-1 fw-bold">
                                    <i class="bi bi-person-check-fill me-1"></i> Panel Ucznia
                                </span>
                                <span class="badge rounded-pill <?php echo $milestoneClass; ?> px-3 py-1 fw-bold">
                                    <?php echo htmlspecialchars($milestoneLabel); ?>
                                </span>
                            </div>
                            <h2 class="h3 fw-bold mb-0 text-main"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Postęp w nauce</h2>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="../practice.php" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-play-circle-fill me-2"></i>Rozpocznij naukę
                            </a>
                        </div>
                    </div>

                    <!-- CSRF Token for AJAX & Flash Notice Box -->
                    <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                    <div id="progressNotice" class="alert d-none border-0 shadow-sm mb-4" role="status"></div>

                    <!-- Hero Progress Bento Panel -->
                    <div class="dashboard-panel progress-hero-panel mb-4 animate-in">
                        <div class="row align-items-center g-4">
                            <!-- SVG Radial Progress Meter -->
                            <div class="col-12 col-md-auto d-flex justify-content-center">
                                <div class="progress-radial-wrap">
                                    <svg class="progress-radial-svg" viewBox="0 0 100 100">
                                        <circle class="progress-radial-bg" cx="50" cy="50" r="<?php echo $radius; ?>" />
                                        <circle class="progress-radial-bar" cx="50" cy="50" r="<?php echo $radius; ?>"
                                                style="stroke-dasharray: <?php echo $circumference; ?>; stroke-dashoffset: <?php echo $strokeDashoffset; ?>;" />
                                    </svg>
                                    <div class="progress-radial-text">
                                        <span><?php echo round($overallPercentage, 1); ?>%</span>
                                        <span class="progress-radial-subtext">Opanowano</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bento Stats Grid -->
                            <div class="col-12 col-md">
                                <div class="progress-stats-bento">
                                    <div class="progress-stat-card">
                                        <div class="progress-stat-label"><i class="bi bi-check2-circle text-success"></i> Opanowane</div>
                                        <div class="progress-stat-value text-success"><?php echo $masteredCount; ?> <span class="fs-6 text-white text-opacity-50">/ <?php echo $totalQuestions; ?></span></div>
                                        <div class="progress-stat-sub"><?php echo $masteredPct; ?>% całości pytań</div>
                                    </div>

                                    <div class="progress-stat-card">
                                        <div class="progress-stat-label"><i class="bi bi-hourglass-split text-warning"></i> W trakcie</div>
                                        <div class="progress-stat-value text-warning"><?php echo $inProgressCount; ?></div>
                                        <div class="progress-stat-sub"><?php echo $inProgressPct; ?>% rozpoczętych</div>
                                    </div>

                                    <div class="progress-stat-card">
                                        <div class="progress-stat-label"><i class="bi bi-inbox text-info"></i> Nowe pytania</div>
                                        <div class="progress-stat-value text-white"><?php echo $notSeenCount; ?></div>
                                        <div class="progress-stat-sub">Czeka na rozwiązanie</div>
                                    </div>

                                    <div class="progress-stat-card">
                                        <div class="progress-stat-label"><i class="bi bi-bullseye text-primary"></i> Skuteczność</div>
                                        <div class="progress-stat-value text-info"><?php echo $globalAccuracy; ?>%</div>
                                        <div class="progress-stat-sub"><?php echo $totalCorrectAttempts; ?> / <?php echo $totalAttempts; ?> odpowiedzi</div>
                                    </div>
                                </div>

                                <!-- Multi-segment progress visual -->
                                <div class="progress-segmented-bar" title="Opanowane: <?php echo $masteredCount; ?>, W trakcie: <?php echo $inProgressCount; ?>">
                                    <div class="seg-mastered" style="width: <?php echo $masteredPct; ?>%;"></div>
                                    <div class="seg-inprogress" style="width: <?php echo $inProgressPct; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter & Search Toolbar -->
                    <div class="dashboard-panel mb-4">
                        <div class="panel-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="panel-title mb-0"><i class="bi bi-funnel-fill me-2 text-primary"></i>Filtrowanie i wyszukiwarka</h5>
                            <?php if ($filter !== 'all' || $categoryFilter !== 'all' || $search !== ''): ?>
                                <a href="progress.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                                    <i class="bi bi-x-circle me-1"></i>Wyczyść filtry
                                </a>
                            <?php endif; ?>
                        </div>
                        <form method="GET" class="row g-3" id="progressFilterForm">
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold text-muted">Status pytania</label>
                                <select name="filter" class="form-select live-filter-control rounded-3">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Wszystkie pytania</option>
                                    <option value="mastered" <?php echo $filter === 'mastered' ? 'selected' : ''; ?>>✅ Opanowane</option>
                                    <option value="in_progress" <?php echo $filter === 'in_progress' ? 'selected' : ''; ?>>⏳ W trakcie nauki</option>
                                    <option value="not_seen" <?php echo $filter === 'not_seen' ? 'selected' : ''; ?>>🆕 Nieobejrzane (Nowe)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small fw-semibold text-muted">Dział tematyczny</label>
                                <select name="category" class="form-select live-filter-control rounded-3">
                                    <option value="all">Wszystkie działy (<?php echo count($allCategories); ?>)</option>
                                    <?php foreach ($allCategories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-semibold text-muted">Szukaj w treści</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0 live-filter-control rounded-end-3" placeholder="Wpisz słowo kluczowe..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-12 col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold">
                                    <i class="bi bi-check2 me-1"></i>Zastosuj
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Summary: Strength & Growth Diagnostics -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-lg-6">
                            <div class="dashboard-panel h-100">
                                <div class="panel-header mb-3 d-flex justify-content-between align-items-center">
                                    <h5 class="panel-title mb-0"><i class="bi bi-trophy-fill me-2 text-warning"></i>Mocne strony (Najlepsze kategorie)</h5>
                                    <span class="badge bg-success bg-opacity-15 text-success rounded-pill px-2 py-1 small">Wysoka skuteczność</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php
                                    $catPercentages = [];
                                    foreach ($globalCategoryStats as $catName => $stats) {
                                        if ($stats['total'] > 0 && $stats['seen'] > 0) {
                                            $catPercentages[$catName] = ($stats['correct'] / $stats['total']) * 100;
                                        }
                                    }
                                    arsort($catPercentages);
                                    $topCategories = array_slice($catPercentages, 0, 3, true);
                                    ?>
                                    <?php if (empty($topCategories)): ?>
                                        <div class="p-4 text-center text-muted">
                                            <i class="bi bi-bar-chart display-6 opacity-50 mb-2"></i>
                                            <p class="mb-0 small">Brak rozwiązanych pytań do wyliczenia statystyk.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3">
                                            <?php foreach ($topCategories as $catName => $percent): ?>
                                                <?php 
                                                    $catStat = $globalCategoryStats[$catName] ?? ['correct' => 0, 'total' => 0];
                                                ?>
                                                <div class="progress-category-item">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="fw-bold text-main small"><?php echo htmlspecialchars($catName); ?></span>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <small class="text-muted"><?php echo (int)$catStat['correct']; ?>/<?php echo (int)$catStat['total']; ?> poprawnych</small>
                                                            <span class="badge bg-success bg-opacity-15 text-success fw-bold rounded-pill"><?php echo round($percent); ?>%</span>
                                                        </div>
                                                    </div>
                                                    <div class="progress" style="height: 6px; border-radius: 3px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="dashboard-panel h-100">
                                <div class="panel-header mb-3 d-flex justify-content-between align-items-center">
                                    <h5 class="panel-title mb-0"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Obszary do powtórki</h5>
                                    <span class="badge bg-danger bg-opacity-15 text-danger rounded-pill px-2 py-1 small">Wymagają uwagi</span>
                                </div>
                                <div class="card-body p-0">
                                    <?php
                                    asort($catPercentages);
                                    $weakCategories = array_slice($catPercentages, 0, 3, true);
                                    ?>
                                    <?php if (empty($weakCategories)): ?>
                                        <div class="p-4 text-center text-muted">
                                            <i class="bi bi-check-all display-6 text-success opacity-50 mb-2"></i>
                                            <p class="mb-0 small">Brak słabych obszarów lub brak danych.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3">
                                            <?php foreach ($weakCategories as $catName => $percent): ?>
                                                <?php 
                                                    $catStat = $globalCategoryStats[$catName] ?? ['correct' => 0, 'total' => 0];
                                                ?>
                                                <div class="progress-category-item">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="fw-bold text-main small"><?php echo htmlspecialchars($catName); ?></span>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <small class="text-muted"><?php echo (int)$catStat['correct']; ?>/<?php echo (int)$catStat['total']; ?> poprawnych</small>
                                                            <span class="badge bg-danger bg-opacity-15 text-danger fw-bold rounded-pill"><?php echo round($percent); ?>%</span>
                                                        </div>
                                                    </div>
                                                    <div class="progress" style="height: 6px; border-radius: 3px;">
                                                        <div class="progress-bar bg-danger" style="width: <?php echo $percent; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Questions Interactive List -->
                    <div class="dashboard-panel progress-table-card">
                        <div class="panel-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="panel-title mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Lista pytań egzaminacyjnych</h5>
                                <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill fw-bold"><?php echo count($filteredQuestions); ?> pytań</span>
                            </div>
                            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Kliknij w wiersz, aby zobaczyć opcje i wyjaśnienie</span>
                        </div>

                        <div class="card-body p-0">
                            <?php if (empty($filteredQuestions)): ?>
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-search display-4 opacity-50 mb-3"></i>
                                    <h5>Brak pytań spełniających kryteria</h5>
                                    <p class="small text-muted mb-3">Spróbuj zmienić filtry lub wyszukiwaną frazę.</p>
                                    <a href="progress.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">Zresetuj filtry</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 120px;" class="ps-3">Status</th>
                                                <th>Treść pytania i kategoria</th>
                                                <th style="width: 100px;" class="text-center">Próby</th>
                                                <th style="width: 160px;">Skuteczność</th>
                                                <th style="width: 140px;" class="pe-3">Ostatnio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $qIndex = 0;
                                            foreach ($filteredQuestions as $question):
                                                $qId = $question['id'];
                                                $progress = $userProgress[$qId] ?? null;
                                                $timesSeen = $progress ? (int)$progress['times_seen'] : 0;
                                                $timesCorrect = $progress ? (int)$progress['times_correct'] : 0;
                                                $accuracy = $timesSeen > 0 ? ($timesCorrect / $timesSeen) * 100 : 0;
                                                $lastSeen = $progress && $progress['last_seen'] ? date('d.m.Y H:i', strtotime($progress['last_seen'])) : 'Nigdy';
                                                $categoryName = $question['category'] ?? 'Nieznana';

                                                $rowClass = 'question-item question-row-interactive';
                                                if ($qIndex >= 15) {
                                                    $rowClass .= ' d-none';
                                                }

                                                // Determine status
                                                if ($progress && $progress['is_mastered']) {
                                                    $status = 'mastered';
                                                    $statusClass = 'success';
                                                    $statusText = 'Opanowane';
                                                    $statusIcon = 'bi-check-circle-fill';
                                                } elseif ($timesSeen > 0) {
                                                    $status = 'in_progress';
                                                    $statusClass = 'warning text-dark';
                                                    $statusText = 'W trakcie';
                                                    $statusIcon = 'bi-hourglass-split';
                                                } else {
                                                    $status = 'new';
                                                    $statusClass = 'secondary';
                                                    $statusText = 'Nowe';
                                                    $statusIcon = 'bi-circle';
                                                }
                                            ?>
                                                <tr class="<?php echo $rowClass; ?>" data-question-id="<?php echo $qId; ?>">
                                                    <td class="ps-3">
                                                        <span class="badge bg-<?php echo $statusClass; ?> status-badge d-inline-flex align-items-center gap-1">
                                                            <i class="bi <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column gap-1">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge bg-primary bg-opacity-10 text-primary category-badge fw-bold">
                                                                    <i class="bi bi-tag-fill me-1"></i><?php echo htmlspecialchars($categoryName); ?>
                                                                </span>
                                                            </div>
                                                            <span class="fw-medium text-main text-break">
                                                                <?php echo htmlspecialchars(mb_substr($question['question_text'], 0, 110)); ?><?php echo mb_strlen($question['question_text']) > 110 ? '...' : ''; ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="fw-bold text-main"><?php echo $timesSeen; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px;">
                                                                <div class="progress-bar bg-<?php echo $accuracy >= 70 ? 'success' : ($accuracy >= 40 ? 'warning' : 'danger'); ?>"
                                                                     style="width: <?php echo $accuracy; ?>%">
                                                                </div>
                                                            </div>
                                                            <small class="fw-bold text-main" style="min-width: 45px;"><?php echo round($accuracy); ?>%</small>
                                                        </div>
                                                        <small class="text-muted" style="font-size: 0.72rem;"><?php echo $timesCorrect; ?> z <?php echo $timesSeen; ?> poprawnych</small>
                                                    </td>
                                                    <td class="pe-3">
                                                        <small class="text-muted d-flex align-items-center gap-1">
                                                            <i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($lastSeen); ?>
                                                        </small>
                                                    </td>
                                                </tr>

                                                <!-- Expanded Question Details Drawer -->
                                                <tr class="question-details-row" id="details-<?php echo $qId; ?>" style="display: none;">
                                                    <td colspan="5" class="p-3 bg-body-tertiary">
                                                        <div class="question-details show p-4">
                                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                                                <h6 class="mb-0 fs-6"><?php echo htmlspecialchars($question['question_text']); ?></h6>
                                                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold">
                                                                    <?php echo htmlspecialchars($categoryName); ?>
                                                                </span>
                                                            </div>

                                                            <div class="mb-4">
                                                                <?php
                                                                $answers = [];
                                                                if (isset($question['option_a'])) {
                                                                    $answers = [
                                                                        'A' => $question['option_a'],
                                                                        'B' => $question['option_b'],
                                                                        'C' => $question['option_c'],
                                                                        'D' => $question['option_d']
                                                                    ];
                                                                } elseif (isset($question['answers']) && is_array($question['answers'])) {
                                                                    $answers = $question['answers'];
                                                                }
                                                                foreach ($answers as $key => $answer):
                                                                    $isCorrect = (string)$question['correct_answer'] === (string)$key;
                                                                ?>
                                                                    <div class="question-option-card <?php echo $isCorrect ? 'option-correct' : ''; ?>">
                                                                        <div class="question-option-letter"><?php echo htmlspecialchars($key); ?></div>
                                                                        <div class="flex-grow-1 <?php echo $isCorrect ? 'fw-bold text-success' : 'text-main'; ?>">
                                                                            <?php echo htmlspecialchars($answer); ?>
                                                                        </div>
                                                                        <?php if ($isCorrect): ?>
                                                                            <span class="badge bg-success bg-opacity-15 text-success rounded-pill px-2 py-1 small">
                                                                                <i class="bi bi-check-lg me-1"></i>Poprawna
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>

                                                            <?php
                                                                $questionExplanation = trim((string)($question['explanation'] ?? ''));
                                                                if ($questionExplanation === '') {
                                                                    $questionForExplanation = $question;
                                                                    $questionForExplanation['question_text'] = $question['question_text'] ?? '';
                                                                    $questionForExplanation['correct_answer'] = $question['correct_answer'] ?? '';
                                                                    $questionExplanation = buildQuestionExplanation($questionForExplanation);
                                                                }
                                                            ?>
                                                            <?php if ($questionExplanation !== ''): ?>
                                                                <div class="alert alert-info rounded-3 mb-3 p-3">
                                                                    <div class="fw-bold mb-1 d-flex align-items-center gap-2">
                                                                        <i class="bi bi-lightbulb-fill text-warning"></i> Wyjaśnienie merytoryczne:
                                                                    </div>
                                                                    <div class="small leading-relaxed">
                                                                        <?php echo nl2br(htmlspecialchars($questionExplanation)); ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <span class="badge bg-<?php echo $statusClass; ?> rounded-pill px-3 py-1"><?php echo $statusText; ?></span>
                                                                    <small class="text-muted">
                                                                        Łącznie podejść: <strong><?php echo $timesSeen; ?></strong> | Trafień: <strong><?php echo $timesCorrect; ?></strong>
                                                                    </small>
                                                                </div>
                                                                <div>
                                                                    <?php if ($progress && !$progress['is_mastered']): ?>
                                                                        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 mark-mastered" data-question-id="<?php echo $qId; ?>">
                                                                            <i class="bi bi-check2-circle me-1"></i> Oznacz jako opanowane
                                                                        </button>
                                                                    <?php elseif (!$progress): ?>
                                                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" disabled>
                                                                            <i class="bi bi-clock me-1"></i> Rozwiąż najpierw pytanie
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" disabled>
                                                                            <i class="bi bi-check-circle-fill me-1"></i> Opanowane
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php 
                                                $qIndex++;
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (count($filteredQuestions) > 15): ?>
                                    <div class="text-center py-4 border-top" id="loadMoreQuestionsWrap">
                                        <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm" id="loadMoreQuestionsBtn">
                                            <i class="bi bi-plus-circle me-2"></i>Załaduj kolejne pytania
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle question details
            const filterForm = document.getElementById('progressFilterForm');
            if (filterForm) {
                let liveTimer = null;
                filterForm.querySelectorAll('.live-filter-control').forEach(control => {
                    const eventName = control.tagName === 'SELECT' ? 'change' : 'input';
                    control.addEventListener(eventName, () => {
                        clearTimeout(liveTimer);
                        liveTimer = setTimeout(() => filterForm.submit(), 350);
                    });
                });
            }

            document.querySelectorAll('.question-item').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Don't toggle if clicking on a button
                    if (e.target.closest('button')) return;

                    const questionId = this.getAttribute('data-question-id');
                    const detailsRow = document.getElementById('details-' + questionId);

                    // Hide all other details
                    document.querySelectorAll('.question-details-row').forEach(r => {
                        if (r.id !== 'details-' + questionId) {
                            r.style.display = 'none';
                        }
                    });

                    // Toggle current
                    if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                        detailsRow.style.display = 'table-row';
                    } else {
                        detailsRow.style.display = 'none';
                    }
                });
            });

            // Pagination/Load More Questions
            let visibleCount = 15;
            const pageSize = 15;
            const questionRows = document.querySelectorAll('.question-item');
            const loadMoreBtn = document.getElementById('loadMoreQuestionsBtn');
            const loadMoreWrap = document.getElementById('loadMoreQuestionsWrap');

            function updateQuestionVisibility() {
                questionRows.forEach((row, index) => {
                    if (index < visibleCount) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                        // Hide details row if parent row is hidden
                        const qId = row.getAttribute('data-question-id');
                        const detailsRow = document.getElementById('details-' + qId);
                        if (detailsRow) detailsRow.style.display = 'none';
                    }
                });

                if (loadMoreWrap) {
                    if (questionRows.length <= visibleCount) {
                        loadMoreWrap.classList.add('d-none');
                    } else {
                        loadMoreWrap.classList.remove('d-none');
                    }
                }
            }

            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    visibleCount += pageSize;
                    updateQuestionVisibility();
                });
            }

            // Mark as mastered
            function showProgressNotice(message, type = 'info') {
                const box = document.getElementById('progressNotice');
                if (!box) return;
                box.textContent = message;
                box.className = 'alert alert-' + type + ' border-0 shadow-sm';
                window.setTimeout(() => box.classList.add('d-none'), 4200);
            }

            document.querySelectorAll('.mark-mastered').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const questionId = this.getAttribute('data-question-id');
                    const ajaxUrl = (window.location.pathname.includes('/user/') ? '../' : '') + 'ajax/mark_mastered.php';
                    fetch(ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'question_id=' + encodeURIComponent(questionId) + '&csrf_token=' + encodeURIComponent(csrfToken)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showProgressNotice('Oznaczono jako opanowane.', 'success');
                            window.setTimeout(() => location.reload(), 350);
                        } else {
                            showProgressNotice('Błąd: ' + (data.error || 'Nie udało się zapisać zmiany.'), 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Progress AJAX error:', error);
                        showProgressNotice('Wystąpił błąd sieciowy.', 'danger');
                    });
                });
            });
        });
    </script>
</body>
</html>

