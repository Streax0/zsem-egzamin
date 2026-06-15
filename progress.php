<?php
// Include necessary files
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

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
    if ($search && stripos($question['question_text'], $search) === false) continue;

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
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postęp w nauce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <style>
        .progress-bar { transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .status-badge { font-size: 0.75rem; padding: 0.4em 0.8em; border-radius: 8px; font-weight: 600; }
        .question-item { cursor: pointer; transition: background 0.2s; }
        .question-item:hover { background-color: rgba(59, 130, 246, 0.03) !important; }
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
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3"><i class="bi bi-graph-up"></i> Postęp w nauce</h2>

                <!-- Overall Progress -->
                <div class="dashboard-panel mb-4 animate-in overflow-hidden" style="background: linear-gradient(135deg, #1e293b, #334155); color: white;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold mb-1" style="color: rgba(255,255,255,0.6) !important;">Twój aktualny postęp</div>
                            <div class="stat-highlight"><?php echo round($overallPercentage, 1); ?>%</div>
                        </div>
                        <div class="text-end">
                            <div class="h5 mb-0 text-white"><?php echo $questionsWithProgress; ?> / <?php echo $totalQuestions; ?></div>
                            <div class="small opacity-75">opanowanych pytań</div>
                        </div>
                    </div>
                    <div class="progress" style="height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px;">
                        <div class="progress-bar bg-info" style="width: <?php echo $overallPercentage; ?>%"></div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="dashboard-panel mb-4">
                    <div class="panel-header mb-3">
                        <h5 class="panel-title mb-0">Filtrowanie</h5>
                    </div>
                    <form method="GET" class="row g-3" id="progressFilterForm">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="filter" class="form-select live-filter-control">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Wszystkie</option>
                                <option value="not_seen" <?php echo $filter === 'not_seen' ? 'selected' : ''; ?>>Nieobejrzane</option>
                                <option value="in_progress" <?php echo $filter === 'in_progress' ? 'selected' : ''; ?>>W trakcie</option>
                                <option value="mastered" <?php echo $filter === 'mastered' ? 'selected' : ''; ?>>Opanowane</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kategoria</label>
                            <select name="category" class="form-select live-filter-control">
                                <option value="all">Wszystkie kategorie</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Szukaj</label>
                            <input type="text" name="search" class="form-control live-filter-control" placeholder="Szukaj w treści pytań..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>Zastosuj
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Stats Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="dashboard-panel h-100">
                            <div class="panel-header mb-3">
                                <h5 class="panel-title mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Najlepsze kategorie</h5>
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
                                    <p class="text-muted p-3">Brak danych</p>
                                <?php else: ?>
                                    <div class="p-3">
                                        <?php foreach ($topCategories as $catName => $percent): ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1 small">
                                                    <span class="fw-bold"><?php echo htmlspecialchars($catName); ?></span>
                                                    <span class="text-success fw-bold"><?php echo round($percent); ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dashboard-panel h-100">
                            <div class="panel-header mb-3">
                                <h5 class="panel-title mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Kategorie do poprawy</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                asort($catPercentages);
                                $weakCategories = array_slice($catPercentages, 0, 3, true);
                                ?>
                                <?php if (empty($weakCategories)): ?>
                                    <p class="text-muted p-3">Brak danych</p>
                                <?php else: ?>
                                    <div class="p-3">
                                        <?php foreach ($weakCategories as $catName => $percent): ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1 small">
                                                    <span class="fw-bold"><?php echo htmlspecialchars($catName); ?></span>
                                                    <span class="text-danger fw-bold"><?php echo round($percent); ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
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

                <!-- CSRF Token for AJAX -->
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <div id="progressNotice" class="alert d-none border-0 shadow-sm" role="status"></div>

                <!-- Questions List -->
                <div class="dashboard-panel">
                    <div class="panel-header mb-3">
                        <h5 class="panel-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>Lista pytań
                            <span class="badge bg-secondary ms-2"><?php echo count($filteredQuestions); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($filteredQuestions)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-2">Brak pytań spełniających kryteria</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;">Status</th>
                                            <th>Pytanie</th>
                                            <th style="width: 100px;">Próby</th>
                                            <th style="width: 120px;">Skuteczność</th>
                                            <th style="width: 150px;">Ostatnie</th>
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

                                            $rowClass = 'question-item';
                                            if ($qIndex >= 15) {
                                                $rowClass .= ' d-none';
                                            }

                                            // Determine status
                                            if ($progress && $progress['is_mastered']) {
                                                $status = 'opanowane';
                                                $statusClass = 'success';
                                                $statusText = 'Opanowane';
                                            } elseif ($timesSeen > 0) {
                                                $status = 'in_progress';
                                                $statusClass = 'warning';
                                                $statusText = 'W trakcie';
                                            } else {
                                                $status = 'new';
                                                $statusClass = 'secondary';
                                                $statusText = 'Nowe';
                                            }
                                        ?>
                                            <tr class="<?php echo $rowClass; ?>" data-question-id="<?php echo $qId; ?>">
                                                <td>
                                                    <span class="badge bg-<?php echo $statusClass; ?> status-badge">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                     <div class="d-flex align-items-center gap-2">
                                                         <span class="badge bg-primary bg-opacity-10 text-primary category-badge fw-bold">
                                                             <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($categoryName); ?>
                                                         </span>
                                                         <span class="fw-medium text-main"><?php echo htmlspecialchars(mb_substr($question['question_text'], 0, 90)); ?><?php echo mb_strlen($question['question_text']) > 90 ? '...' : ''; ?></span>
                                                     </div>
                                                 </td>
                                                <td><?php echo $timesSeen; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                            <div class="progress-bar bg-<?php echo $accuracy >= 70 ? 'success' : ($accuracy >= 40 ? 'warning' : 'danger'); ?>"
                                                                 style="width: <?php echo $accuracy; ?>%">
                                                                <?php echo round($accuracy); ?>%
                                                            </div>
                                                        </div>
                                                        <small><?php echo $timesCorrect; ?>/<?php echo $timesSeen; ?></small>
                                                    </div>
                                                </td>
                                                <td><small><?php echo $lastSeen; ?></small></td>
                                            </tr>
                                            <tr class="question-details-row" id="details-<?php echo $qId; ?>" style="display: none;">
                                                <td colspan="5">
                                                    <div class="question-details show p-3 bg-light">
                                                        <h6><?php echo htmlspecialchars($question['question_text']); ?></h6>
                                                        <div class="mb-3">
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
                                                            ?>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="radio" disabled
                                                                           name="answer_<?php echo $qId; ?>"
                                                                           id="ans_<?php echo $qId . '_' . $key; ?>"
                                                                           <?php echo $question['correct_answer'] == $key ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label <?php echo $question['correct_answer'] == $key ? 'text-success fw-bold' : ''; ?>" for="ans_<?php echo $qId . '_' . $key; ?>">
                                                                        <strong><?php echo $key; ?>.</strong> <?php echo htmlspecialchars($answer); ?>
                                                                    </label>
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
                                                            <div class="alert alert-info mb-3">
                                                                <strong>Wyjaśnienie:</strong><br>
                                                                <?php echo nl2br(htmlspecialchars($questionExplanation)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span class="badge bg-<?php echo $statusClass; ?> me-2"><?php echo $statusText; ?></span>
                                                                <small class="text-muted">
                                                                    Próby: <?php echo $timesSeen; ?> | Poprawne: <?php echo $timesCorrect; ?>
                                                                </small>
                                                            </div>
                                                            <?php if ($progress && !$progress['is_mastered']): ?>
                                                                <button class="btn btn-success btn-sm mark-mastered" data-question-id="<?php echo $qId; ?>">
                                                                    <i class="bi bi-check-circle"></i> Oznacz jako opanowane
                                                                </button>
                                                            <?php elseif (!$progress): ?>
                                                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                                                    <i class="bi bi-clock"></i> Rozwiąż najpierw pytanie
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                                                    <i class="bi bi-check-circle-fill"></i> Opanowane
                                                                </button>
                                                            <?php endif; ?>
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
                                <div class="text-center py-4" id="loadMoreQuestionsWrap">
                                    <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" id="loadMoreQuestionsBtn">
                                        <i class="bi bi-plus-circle me-2"></i>Załaduj więcej pytań
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script src="assets/js/theme-handler.js"></script>
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
                    const csrfToken = document.getElementById('csrf_token').value;

                    fetch('ajax/mark_mastered.php', {
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
