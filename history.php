<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$results = getUnifiedUserHistory($pdo, (int)$userId, 50);

$stats = getUserStats($pdo, $userId);
$flashMessage = getSessionMessage();
$flashTypeMap = [
    'success' => 'success',
    'error' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];
$modeBadgeMap = [
    'exam' => 'history-badge-exam',
    'practice' => 'history-badge-practice',
    'single' => 'history-badge-single',
    'exam_simulator' => 'history-badge-exam-simulator',
    'duel' => 'history-badge-duel',
    'sprawdzian' => 'history-badge-exam-session',
    'exam_session' => 'history-badge-exam-session',
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historia Testów - System Testów</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <style>
        .history-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .history-summary-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
        }
        .history-mode-badge {
            border: 0;
            border-radius: 999px;
            padding: .45rem .7rem;
            font-weight: 800;
        }
        .history-badge-exam { background: rgba(239,68,68,.12); color: #b91c1c; }
        .history-badge-practice { background: rgba(16,185,129,.12); color: #047857; }
        .history-badge-single { background: rgba(14,165,233,.14); color: #0369a1; }
        .history-badge-exam-simulator { background: rgba(99,102,241,.14); color: #4338ca; }
        .history-badge-duel { background: rgba(124,58,237,.13); color: #6d28d9; }
        .history-badge-exam-session { background: rgba(245,158,11,.16); color: #b45309; }
        .history-badge-default { background: rgba(100,116,139,.14); color: #475569; }
        body.dark-mode .history-summary-card { background: #1e293b; border-color: #334155; }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold">Historia Twoich testów</h2>
                    <a href="test.php?mode=exam&setup=1" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Nowy test
                    </a>
                </div>
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo $flashTypeMap[$flashMessage['type'] ?? 'info'] ?? 'info'; ?> border-0 rounded-3">
                        <?php echo htmlspecialchars($flashMessage['message'] ?? ''); ?>
                    </div>
                <?php endif; ?>
                <div class="history-summary-grid">
                    <div class="history-summary-card">
                        <div class="text-muted small fw-bold">Pełne testy</div>
                        <div class="h3 fw-bold mb-0"><?php echo number_format((int)($stats['tests_taken'] ?? 0)); ?></div>
                    </div>
                    <div class="history-summary-card">
                        <div class="text-muted small fw-bold">Średni wynik</div>
                        <div class="h3 fw-bold mb-0"><?php echo number_format((float)($stats['average_score'] ?? 0), 1); ?>%</div>
                    </div>
                    <div class="history-summary-card">
                        <div class="text-muted small fw-bold">Wpisy historii</div>
                        <div class="h3 fw-bold mb-0"><?php echo number_format(count($results)); ?></div>
                    </div>
                </div>

                <div class="dashboard-panel animate-in">
                    <div class="panel-header">
                        <h3 class="panel-title">Wszystkie podejścia</h3>
                    </div>
                    <div class="row g-3 mb-4 history-live-filters">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Szukaj po trybie, pojedynku lub dacie</label>
                            <input type="search" id="historySearch" class="form-control" placeholder="np. exam, pojedynek, 2026-01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Od</label>
                            <input type="date" id="historyDateFrom" class="form-control" min="2026-01-01" value="2026-01-01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Do</label>
                            <input type="date" id="historyDateTo" class="form-control" min="2026-01-01">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-light border w-100" id="historyClearFilters" title="Wyczyść filtry">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    
                    <?php if (empty($results)): ?>
                        <div class="empty-state">
                            <i class="bi bi-journal-x"></i>
                            <p>Nie masz jeszcze żadnych zapisanych wyników.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>DATA</th>
                                        <th>TRYB</th>
                                        <th>WYNIK</th>
                                        <th>POPRAWNE</th>
                                        <th>CZAS</th>
                                        <th>AKCJA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $idx => $test):
                                        $percentage = (float)($test['percentage'] ?? $test['score_percent'] ?? 0);
                                        $statusClass = $percentage >= 75 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                        $rowDate = date('Y-m-d', strtotime($test['completed_at'] ?? $test['date']));
                                        $rowLabel = $test['test_type'] ?? $test['label'];
                                        $kind = strtolower((string)($test['kind'] ?? 'test'));
                                        $modeKey = strtolower((string)($test['mode'] ?? $rowLabel));
                                        $badgeClass = $kind === 'duel' ? 'history-badge-duel' : ($modeBadgeMap[$modeKey] ?? $modeBadgeMap[$kind] ?? 'history-badge-default');
                                    ?>
                                        <tr class="history-row <?php echo $idx >= 30 ? 'd-none history-extra' : ''; ?>" data-search="<?php echo htmlspecialchars(mb_strtolower($rowLabel . ' ' . $rowDate, 'UTF-8')); ?>" data-date="<?php echo htmlspecialchars($rowDate); ?>">
                                            <td class="small">
                                                <div class="fw-bold text-main"><?php echo date('d.m.Y', strtotime($test['completed_at'] ?? $test['date'])); ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($test['completed_at'] ?? $test['date'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge history-mode-badge <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($rowLabel); ?>
                                                </span>
                                                <?php if (!empty($test['locked'])): ?><div class="small text-muted mt-1"><i class="bi bi-lock me-1"></i>Wyniki ukryte przez nauczyciela</div><?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 8px; width: 120px; background: rgba(0,0,0,0.05);">
                                                        <div class="progress-bar bg-<?php echo $statusClass; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <span class="fw-bold text-<?php echo $statusClass; ?>"><?php echo !empty($test['locked']) ? '—' : number_format($percentage) . '%'; ?></span>
                                                </div>
                                            </td>
                                            <td class="fw-medium">
                                                <?php if (!empty($test['locked'])): ?>
                                                    <span class="text-muted small">niedostępne</span>
                                                <?php elseif (($test['kind'] ?? 'test') === 'duel'): ?>
                                                    <span class="text-muted small">pojedynek / <?php echo (int)$test['total_questions']; ?> pytań</span>
                                                <?php else: ?>
                                                    <?php echo (int)$test['correct_count']; ?> <span class="text-muted small">/ <?php echo (int)$test['total_questions']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?php echo formatTime($test['time_spent']); ?></td>
                                            <td>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a href="<?php echo htmlspecialchars($test['url']); ?>" class="btn btn-sm btn-light border-0 px-3" style="border-radius: 8px;">
                                                        Szczegóły
                                                    </a>
                                                    <?php if (($test['kind'] ?? 'test') === 'test'): ?>
                                                        <form method="POST" action="actions/delete_test_result.php" onsubmit="return appConfirmSubmit(this, 'Usunąć ten wynik z historii?');">
                                                            <?php echo csrfTokenField('delete_test_result'); ?>
                                                            <input type="hidden" name="result_id" value="<?php echo (int)$test['id']; ?>">
                                                            <input type="hidden" name="return_to" value="../history.php">
                                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Usuń wynik" style="border-radius: 8px;"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    <?php elseif (($test['kind'] ?? 'test') === 'duel' && !empty($test['can_hide'])): ?>
                                                        <form method="POST" action="actions/delete_duel_history.php" onsubmit="return appConfirmSubmit(this, 'Usunąć ten pojedynek z Twojej historii?');">
                                                            <?php echo csrfTokenField('delete_duel_history'); ?>
                                                            <input type="hidden" name="duel_id" value="<?php echo (int)$test['id']; ?>">
                                                            <input type="hidden" name="return_to" value="../history.php">
                                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Usuń pojedynek" style="border-radius: 8px;"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($results) > 30): ?>
                            <div class="text-center pt-3">
                                <button type="button" class="btn btn-primary rounded-pill px-4" id="historyLoadMore">
                                    <i class="bi bi-plus-circle me-2"></i>Wczytaj więcej
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const rows = Array.from(document.querySelectorAll('.history-row'));
        const search = document.getElementById('historySearch');
        const from = document.getElementById('historyDateFrom');
        const to = document.getElementById('historyDateTo');
        const clear = document.getElementById('historyClearFilters');
        const loadMore = document.getElementById('historyLoadMore');
        let visibleLimit = 30;

        const applyFilters = () => {
            const term = (search?.value || '').trim().toLowerCase();
            const fromDate = from?.value || '2026-01-01';
            const toDate = to?.value || '';
            let visible = 0;
            rows.forEach(row => {
                const rowDate = row.dataset.date || '';
                const matches = (!term || (row.dataset.search || '').includes(term))
                    && (!fromDate || rowDate >= fromDate)
                    && (!toDate || rowDate <= toDate);
                visible += matches ? 1 : 0;
                row.classList.toggle('d-none', !matches || visible > visibleLimit);
            });
            if (loadMore) loadMore.classList.toggle('d-none', visible <= visibleLimit);
        };

        [search, from, to].forEach(input => input?.addEventListener('input', applyFilters));
        clear?.addEventListener('click', () => {
            if (search) search.value = '';
            if (from) from.value = '2026-01-01';
            if (to) to.value = '';
            visibleLimit = 30;
            applyFilters();
        });
        loadMore?.addEventListener('click', () => {
            visibleLimit += 30;
            applyFilters();
        });
        applyFilters();
    });
    </script>
</body>
</html>
