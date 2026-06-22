<?php
// Error reporting: only log, never display in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
if (isGuestMode()) {
    $flashMessage = getSessionMessage();
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tryb gościa - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0">
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type']); ?> border-0 shadow-sm mb-4"><?php echo htmlspecialchars($flashMessage['message']); ?></div>
                <?php endif; ?>
                <section class="dashboard-panel p-4 p-lg-5" style="border-radius:8px; background:linear-gradient(135deg,#0f172a,#155e75 58%,#166534); color:#fff;">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <h1 class="fw-900 mb-3">Tryb gościa</h1>
                            <p class="fs-5 opacity-75 mb-4">Możesz rozwiązywać testy bez konta. Wyniki zostają tylko w tej sesji przeglądarki i nie trafiają do historii, rankingu ani misji.</p>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="test.php?setup=1&new=1" class="btn btn-light btn-lg rounded-pill px-4 fw-bold"><i class="bi bi-play-fill me-1"></i>Rozpocznij test</a>
                                <a href="exam/join.php" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-bold"><i class="bi bi-qr-code-scan me-1"></i>Kod sprawdzianu</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="p-4 bg-white bg-opacity-10 border border-white border-opacity-25" style="border-radius:8px;">
                                <div class="d-flex align-items-center gap-3 mb-3"><i class="bi bi-incognito fs-2"></i><strong>Gość nie ma konta w bazie</strong></div>
                                <div class="small opacity-75">Dostępne: testy i dołączenie do sprawdzianu nauczyciela kodem. Zablokowane: społeczność, rankingi, misje, historia, lekcje, ustawienia i duele.</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
    exit;
}
if (!isLoggedIn()) {
    header('Location: landing.php');
    exit;
}
requireLogin();

$stats = getUserStats($pdo, $_SESSION['user_id']);
$dailyStats = getUserDailyStats($pdo, $_SESSION['user_id']);
$recentTests = getUnifiedUserHistory($pdo, (int)$_SESSION['user_id'], 5);
$flashMessage = getSessionMessage();
$currentUser = ['xp' => 0];
try {
    $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: $currentUser;
} catch (PDOException $e) {
    error_log('Index user fetch failed: ' . $e->getMessage());
}
$rankInfo = getRankInfoByXp((int)($currentUser['xp'] ?? 0));
$missionData = syncUserMissions($pdo, $_SESSION['user_id']);
$dailyMissions = $missionData['missions'] ?? [];
$completedMissions = 0;
foreach ($dailyMissions as $mission) {
    if (!empty($mission['is_completed'])) $completedMissions++;
}

// Fetch pending/active duels
$pendingDuels = [];
$activeDuels = [];
try {
    $duelStartedColumnsAvailable = dbColumnExists($pdo, 'duels', 'challenger_started_at')
        && dbColumnExists($pdo, 'duels', 'opponent_started_at');
    $activeDuelOrder = $duelStartedColumnsAvailable
        ? 'COALESCE(d.challenger_started_at, d.opponent_started_at, d.created_at)'
        : 'd.created_at';
    $stmt = $pdo->prepare("
        SELECT d.*, u.username as challenger_name, u.avatar_path as challenger_avatar
        FROM duels d 
        JOIN users u ON d.challenger_id = u.id 
        WHERE d.opponent_id = ? AND d.status = 'pending' AND d.expires_at > NOW()
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingDuels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT d.*,
               CASE WHEN d.challenger_id = ? THEN uo.username ELSE uc.username END AS opponent_name,
               CASE WHEN d.challenger_id = ? THEN uo.avatar_path ELSE uc.avatar_path END AS opponent_avatar,
               (SELECT COUNT(DISTINCT da.question_id) FROM duel_answers da WHERE da.duel_id = d.id AND da.user_id = ?) AS answered_count
        FROM duels d
        JOIN users uc ON d.challenger_id = uc.id
        JOIN users uo ON d.opponent_id = uo.id
        WHERE d.status = 'accepted'
          AND (d.challenger_id = ? OR d.opponent_id = ?)
          AND (CASE WHEN d.challenger_id = ? THEN d.challenger_finished_at ELSE d.opponent_finished_at END) IS NULL
        ORDER BY {$activeDuelOrder} DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $activeDuels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Index duel fetch failed: ' . $e->getMessage());
    $pendingDuels = [];
    $activeDuels = [];
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard ZSEM Tech: testy, lekcje, praktyka, ranking i postęp nauki.">
    <meta property="og:title" content="ZSEM Tech">
    <meta property="og:description" content="Platforma edukacyjna do testów INF, sprawdzianów, lekcji i praktyki technicznej.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zsem-egzamin.online/">
    <meta property="og:image" content="https://zsem-egzamin.online/zsemtech_profile.ico">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="ZSEM Tech">
    <meta name="twitter:description" content="Testy INF, lekcje, ranking, pojedynki i sandbox techniczny.">
    <meta name="twitter:image" content="https://zsem-egzamin.online/zsemtech_profile.ico">
    <title>Dashboard - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <!-- Flash Message -->
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type']); ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-info-circle-fill fs-4"></i>
                            <div><?php echo htmlspecialchars($flashMessage['message']); ?></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                    </div>
                <?php endif; ?>

                <!-- Welcome Card -->
                <div class="welcome-card dashboard-hero animate-in" style="overflow: hidden;">
                    <div class="dashboard-hero-inner">
                        <div class="hero-left" style="text-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);">
                            <div class="hero-rank-pill" style="border-color: rgba(255, 255, 255, 0.18); background: rgba(15, 23, 42, 0.35); color: #ffffff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                <i class="bi bi-stars" style="color: <?php echo $rankInfo['color'] ?? '#fff'; ?>;"></i>
                                <span style="color: #ffffff; font-weight: 800;"><?php echo htmlspecialchars(strtoupper($rankInfo['name'] ?? 'BRONZE')); ?></span>
                            </div>
                            <h1 class="h2" style="font-weight: 800; color: #ffffff;">Witaj, <?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?>!</h1>
                            <p class="mb-4 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Rozwiąż dzisiejsze testy egzaminacyjne, wykonaj aktywne misje i zdobywaj cenne punkty XP na drodze do kolejnego awansu.</p>
                            <div class="welcome-actions">
                                <a href="test.php?mode=exam&setup=1" class="btn-welcome btn-welcome-primary" style="box-shadow: 0 4px 15px rgba(15, 23, 42, 0.15);" data-default-test-start>
                                    <i class="bi bi-play-fill"></i>
                                    <span data-default-test-label>Rozpocznij test</span>
                                </a>
                                <a href="progress.php" class="btn-welcome btn-welcome-outline" style="background: rgba(15, 23, 42, 0.25); border: 1px solid rgba(255, 255, 255, 0.35); color: #ffffff; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);">
                                    <i class="bi bi-bar-chart"></i>
                                    Statystyki
                                </a>
                            </div>
                        </div>
                        <div class="hero-right">
                            <div class="xp-summary-card" style="backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border-color: rgba(255, 255, 255, 0.1); background: rgba(15, 23, 42, 0.45); box-shadow: 0 30px 70px rgba(15, 23, 42, 0.25); border-radius: 2rem;">
                                <div class="xp-summary-icon" style="background: rgba(255, 255, 255, 0.08); color: <?php echo $rankInfo['color'] ?? '#fff'; ?>; border: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 0 20px color-mix(in srgb, <?php echo $rankInfo['color'] ?? '#fff'; ?> 30%, transparent); display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="bi <?php echo htmlspecialchars($rankInfo['icon'] ?? 'bi-shield-fill'); ?>" style="font-size: 1.8rem; filter: drop-shadow(0 0 8px <?php echo $rankInfo['color'] ?? '#fff'; ?>);"></i>
                                </div>
                                <div class="xp-summary-body" style="color: #ffffff;">
                                    <div class="xp-summary-label" style="color: rgba(255, 255, 255, 0.7); font-weight: 700; letter-spacing: 0.15em; font-size: 0.8rem;">TWOJE XP</div>
                                    <div class="xp-summary-value" style="font-size: 3rem; font-weight: 900; color: #ffffff; letter-spacing: -0.02em;"><?php echo number_format((int)($currentUser['xp'] ?? 0)); ?></div>
                                </div>
                                <div class="xp-progress-line" style="height: 10px; background: rgba(255, 255, 255, 0.1); border-radius: 999px; overflow: hidden; margin-top: 0.5rem; border: 1px solid rgba(255, 255, 255, 0.05);">
                                    <div class="xp-progress-fill" style="width: <?php echo min(100, max(0, (float)($rankInfo['progress'] ?? 0))); ?>%; background: linear-gradient(90deg, #ffffff, <?php echo $rankInfo['color'] ?? '#ffffff'; ?>); box-shadow: 0 0 10px <?php echo $rankInfo['color'] ?? '#ffffff'; ?>; border-radius: inherit; height: 100%; transition: width 0.5s ease;"></div>
                                </div>
                                <div class="xp-summary-meta" style="margin-top: 0.2rem; display: flex; justify-content: space-between; gap: 1rem; color: rgba(255, 255, 255, 0.8); font-size: 0.88rem; font-weight: 600;">
                                    <span>Postęp: <?php echo min(100, max(0, (float)($rankInfo['progress'] ?? 0))); ?>%</span>
                                    <span><?php echo $rankInfo['next_name'] ? number_format($rankInfo['xp_to_next']) . ' XP do: ' . htmlspecialchars($rankInfo['next_name']) : 'Maksymalna ranga'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="stats-row">
                    <div class="stat-card-new">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div class="stat-number-new"><?php echo number_format($stats['tests_taken']); ?></div>
                        <div class="stat-label-new">Przeprowadzone testy</div>
                    </div>
                    <div class="stat-card-new">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-number-new"><?php echo number_format($stats['average_score'], 1); ?>%</div>
                        <div class="stat-label-new">Średnia wyników</div>
                    </div>
                    <div class="stat-card-new">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <div class="stat-number-new"><?php echo number_format($stats['progress_percentage'], 1); ?>%</div>
                        <div class="stat-label-new">Postęp</div>
                    </div>
                    <div class="stat-card-new">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="stat-number-new"><?php echo number_format($stats['mastered_questions']); ?></div>
                        <div class="stat-label-new">Opanowane pytania</div>
                    </div>
                    <div class="stat-card-new">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="stat-number-new" style="font-size: 1.25rem;"><?php echo formatTime($stats['total_time_seconds']); ?></div>
                        <div class="stat-label-new">Łączny czas nauki</div>
                    </div>
                    <div class="stat-card-new">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-calendar2-check"></i>
                        </div>
                        <div class="stat-number-new"><?php echo number_format((int)($dailyStats['streak_daily'] ?? 0)); ?></div>
                        <div class="stat-label-new">Dni serii</div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="dashboard-grid">
                    <!-- Recent Tests -->
                    <div class="dashboard-panel">
                        <div class="panel-header">
                            <h2 class="panel-title h3">Ostatnie testy</h2>
                            <a href="history.php" class="text-primary text-decoration-none small fw-bold">Zobacz wszystkie</a>
                        </div>

                        <?php
                        $recentTestLabels = [
                            'exam' => ['label' => 'Test', 'color' => 'danger'],
                            'practice' => ['label' => 'Ćwiczenia', 'color' => 'success'],
                            'single' => ['label' => 'Pojedyncze', 'color' => 'info'],
                            'exam_simulator' => ['label' => 'Egzamin', 'color' => 'primary'],
                            'duel' => ['label' => 'Pojedynek', 'color' => 'warning'],
                            'exam_session' => ['label' => 'Sprawdzian', 'color' => 'primary'],
                        ];
                        ?>
                        <?php if (empty($recentTests)): ?>
                            <div class="empty-state p-4 d-flex flex-column align-items-center justify-content-center" style="border: 1px dashed var(--border-color); border-radius: 1.5rem; background: color-mix(in srgb, var(--bg-color) 30%, var(--panel-bg)); min-height: 250px; text-align: center;">
                                <div class="empty-state-icon-wrap mb-3 d-inline-flex align-items-center justify-content-center" style="width: 70px; height: 70px; border-radius: 50%; background: color-mix(in srgb, var(--primary-color) 10%, var(--panel-bg)); border: 1px solid color-mix(in srgb, var(--primary-color) 20%, var(--panel-bg)); color: var(--primary-color); font-size: 2rem; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.03); margin: 0 auto;">
                                    <i class="bi bi-clipboard2-pulse" style="font-size: 2rem; color: var(--primary-color) !important; margin: 0;"></i>
                                </div>
                                <h4 class="fw-bold mb-2" style="font-size: 1.15rem; color: var(--text-main); margin-top: 0.5rem;">Brak zrealizowanych testów</h4>
                                <p class="text-muted small text-center mb-4" style="max-width: 340px; line-height: 1.6; color: var(--text-muted) !important; margin: 0 auto 1.5rem;">Rozpocznij naukę już teraz. Po ukończeniu pierwszego testu, w tym miejscu pojawi się szczegółowe podsumowanie Twoich ostatnich wyników.</p>
                                <a href="test.php?mode=exam&setup=1" class="btn btn-primary rounded-pill px-4 py-2" style="font-weight: 700; font-size: 0.88rem; box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);">
                                    Rozwiąż pierwszy test
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive recent-tests-table-wrap">
                                <table class="table table-hover align-middle recent-tests-table">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th>TYP TESTU</th>
                                            <th>WYNIK</th>
                                            <th>DATA</th>
                                            <th class="text-end">SZCZEGÓŁY</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTests as $test): ?>
                                            <tr>
                                                <td>
                                                     <?php
                                                     $typeKey = $test['kind'] === 'test' ? ($test['mode'] ?? $test['label'] ?? 'unknown') : ($test['kind'] ?? 'unknown');
                                                     $typeInfo = $recentTestLabels[$typeKey] ?? ['label' => ucfirst($typeKey), 'color' => 'secondary'];
                                                 ?>
                                                 <span class="badge bg-<?php echo $typeInfo['color']; ?> text-white test-type-badge">
                                                     <?php echo htmlspecialchars($typeKey === 'duel' ? ($test['label'] ?? $typeInfo['label']) : $typeInfo['label']); ?>
                                                 </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                                            <div class="progress-bar <?php echo ($test['score_percent'] ?? 0) >= 70 ? 'bg-success' : 'bg-primary'; ?>" style="width: <?php echo (float)($test['score_percent'] ?? 0); ?>%"></div>
                                                        </div>
                                                        <span class="fw-bold"><?php echo !empty($test['locked']) ? '—' : number_format((float)($test['score_percent'] ?? 0)); ?>%</span>
                                                    </div>
                                                </td>
                                                <td class="text-muted small"><?php echo date('d.m.Y', strtotime($test['date'] ?? 'now')); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo htmlspecialchars($test['url'] ?? 'history.php'); ?>">
                                                        <i class="bi bi-eye me-1"></i>Zobacz szczegóły
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-column gap-4">
                        <!-- Daily Missions -->
                        <div class="dashboard-panel">
                            <div class="panel-header mb-3">
                                <h2 class="panel-title h3">Dzisiejsze misje</h2>
                                <span class="badge text-bg-primary rounded-pill"><?php echo $completedMissions; ?>/<?php echo count($dailyMissions); ?></span>
                            </div>
                            <div class="vstack gap-2">
                                <?php foreach ($dailyMissions as $mission): ?>
                                    <?php
                                    $target = max(1, (int)$mission['target_value']);
                                    $current = min($target, (int)$mission['current_value']);
                                    $percent = round(($current / $target) * 100);
                                    $isDone = !empty($mission['is_completed']);
                                    ?>
                                    <div class="mission-mini-card <?php echo $isDone ? 'completed' : ''; ?>">
                                        <div class="mission-icon">
                                            <i class="bi <?php echo $isDone ? 'bi-check-circle-fill text-success' : 'bi-bullseye text-primary'; ?>"></i>
                                        </div>
                                        <div class="mission-content flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                                <div class="mission-desc small fw-bold"><?php echo htmlspecialchars(str_replace('{target}', (string)$target, $mission['mission_description'])); ?></div>
                                                <div class="mission-xp">+<?php echo (int)$mission['xp_reward']; ?> XP</div>
                                            </div>
                                            <div class="progress" style="height: 4px; border-radius: 2px;">
                                                <div class="progress-bar <?php echo $isDone ? 'bg-success' : 'bg-primary'; ?>" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1" style="font-size: 0.65rem;">
                                                <span class="text-muted"><?php echo $current; ?>/<?php echo $target; ?></span>
                                                <span class="fw-bold <?php echo $isDone ? 'text-success' : 'text-primary'; ?>"><?php echo $percent; ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Duels Panel -->
                        <div class="dashboard-panel animate-in" style="animation-delay: 0.1s; background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.02) 100%); border-left: 4px solid #ef4444;">
                            <div class="panel-header">
                                <h2 class="panel-title text-danger h3"><i class="bi bi-fire me-2"></i>Bitwy znajomych</h2>
                                <a href="social.php" class="text-danger text-decoration-none small fw-bold">Znajomi</a>
                            </div>
                            <div class="vstack gap-3 mt-2">
                                <?php if (empty($pendingDuels) && empty($activeDuels)): ?>
                                    <div class="empty-state py-3">
                                        <i class="bi bi-lightning-charge fs-2"></i>
                                        <p class="small">Brak oczekujących i aktywnych wyzwań.</p>
                                    </div>
                                <?php else: ?>
                                <?php foreach ($activeDuels as $duel): ?>
                                <?php $activeAvatar = userAvatarSrc($duel['opponent_avatar'] ?? ''); ?>
                                <div class="duel-lobby-card d-flex align-items-center justify-content-between p-2 rounded-3 shadow-sm border small">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-small bg-warning text-dark fw-bold" style="width:30px; height:30px; font-size:0.7rem;">
                                            <?php if ($activeAvatar): ?>
                                                <img class="user-avatar-img" src="<?= htmlspecialchars($activeAvatar) ?>" alt="">
                                            <?php else: ?>
                                                <?= strtoupper(substr($duel['opponent_name'], 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($duel['opponent_name']) ?></div>
                                            <div class="text-muted smaller" style="font-size:0.65rem;">
                                                W trakcie - <?= (int)$duel['answered_count'] ?>/<?= (int)$duel['question_count'] ?> - <?= htmlspecialchars($duel['category']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="duels/take.php?id=<?= (int)$duel['id'] ?>" class="btn btn-warning btn-sm p-1 px-2 fw-bold" style="font-size:0.65rem;">Kontynuuj</a>
                                </div>
                                <?php endforeach; ?>
                                <?php foreach ($pendingDuels as $duel): ?>
                                <?php $pendingAvatar = userAvatarSrc($duel['challenger_avatar'] ?? ''); ?>
                                <div class="duel-lobby-card d-flex align-items-center justify-content-between p-2 rounded-3 shadow-sm border small">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-small bg-danger text-white fw-bold" style="width:30px; height:30px; font-size:0.7rem;">
                                            <?php if ($pendingAvatar): ?>
                                                <img class="user-avatar-img" src="<?= htmlspecialchars($pendingAvatar) ?>" alt="">
                                            <?php else: ?>
                                                <?= strtoupper(substr($duel['challenger_name'], 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($duel['challenger_name']) ?></div>
                                            <div class="text-muted smaller" style="font-size:0.65rem;"><?= htmlspecialchars($duel['category']) ?></div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <form method="POST" action="duels/accept.php" class="m-0">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="id" value="<?= (int)$duel['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm p-1 px-2" style="font-size:0.65rem;">Graj</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Start -->
                        <div class="dashboard-panel">
                            <div class="panel-header">
                                <h2 class="panel-title h3">Szybki start</h2>
                            </div>
                            <div class="quick-start-buttons">
                                <a href="test.php?mode=exam&setup=1" class="btn-qs btn-qs-blue" data-default-test-start>
                                    <i class="bi bi-lightning-fill"></i>
                                    <span data-default-test-label>Rozpocznij test</span>
                                </a>
                                <a href="test.php?mode=single&setup=1&new=1" class="btn-qs" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff;">
                                    <i class="bi bi-patch-question"></i>
                                    Jedno pytanie
                                </a>
                                <a href="exam/join.php" class="btn-qs" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;">
                                    <i class="bi bi-qr-code-scan"></i>
                                    Sprawdzian
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
