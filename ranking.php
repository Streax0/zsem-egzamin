<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$topUsers = getTopRankings($pdo, 200);
$userOfDay = getUserOfDay($pdo);
$rankDefinitions = getRankDefinitions($pdo);
$rankingEvents = getRankingEvents($pdo, 6);

// Get current user's rank and data
$stmt = $pdo->prepare("SELECT xp, (SELECT COUNT(*) FROM test_results WHERE user_id = ?) as tests_count FROM users WHERE id = ?");
$stmt->execute([$userId, $userId]);
$userData = $stmt->fetch();
$currentXp = $userData['xp'] ?? 0;
$currentTests = $userData['tests_count'] ?? 0;

$userRank = getUserRank($pdo, $userId);

// Find next user's XP
$stmt = $pdo->prepare("SELECT xp FROM users WHERE xp > ? AND (role = 'user' OR (role = 'teacher' AND COALESCE(ranking_visible, 0) = 1)) ORDER BY xp ASC LIMIT 1");
$stmt->execute([$currentXp]);
$nextXp = $stmt->fetchColumn();
$xpToNext = $nextXp ? ($nextXp - $currentXp) : 0;
$rankProgress = $nextXp ? round(($currentXp / $nextXp) * 100) : 100;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking Użytkowników - Platforma Testowa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .ranking-shell { max-width: 1320px; margin: 0 auto; }
        .ranking-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: center;
            padding: 1.5rem;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(59,130,246,.12), rgba(139,92,246,.08));
            border: 1px solid rgba(148,163,184,.22);
        }
        .ranking-stats-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }
        .ranking-stat {
            padding: .85rem 1rem;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(148,163,184,.18);
        }
        .rank-number {
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 10px; font-weight: 800;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .rank-1 { background: linear-gradient(135deg, #fbbf24, #d97706); color: #fff; }
        .rank-2 { background: linear-gradient(135deg, #94a3b8, #475569); color: #fff; }
        .rank-3 { background: linear-gradient(135deg, #d97706, #92400e); color: #fff; }
        .ranking-row { height: 72px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 12px; }
        .ranking-row:hover { background-color: rgba(59, 130, 246, 0.04) !important; transform: scale(1.005); }
        .ranking-row.current-user { background-color: rgba(59, 130, 246, 0.08) !important; border-left: 4px solid var(--primary-color); }
        .xp-badge { background: rgba(59, 130, 246, 0.1); color: var(--primary-color-dark); font-weight: 700; border-radius: 10px; }
        .ranking-stat {
            background: #ffffff !important;
            border: 1px solid rgba(148, 163, 184, 0.18) !important;
            color: #0f172a !important;
        }
        body.dark-mode .ranking-stat {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: rgba(148, 163, 184, 0.18) !important;
        }
        .ranking-stat .small.text-muted {
            color: #64748b !important;
        }
        .ranking-table { table-layout: fixed; width: 100%; }
        .ranking-sidebar {
            display: flex;
            flex-direction: column;
        }
        .user-rank-widget { order: 1; }
        #rank-threshold { order: 2; }
        .ranking-avatar {
            width: 40px;
            height: 40px;
            min-width: 40px;
            max-width: 40px;
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 40px;
        }
        .ranking-list-scroll {
            max-height: 760px;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid rgba(148, 163, 184, .14);
            border-radius: 18px;
        }
        .ranking-list-scroll .ranking-table {
            margin-bottom: 0;
        }
        .user-name-cell { min-width: 0; width: 30%; }
        .user-name-cell .username-text,
        .user-name-cell .rank-meta {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            max-width: 240px;
        }
        .user-name-cell .username-text {
            display: flex;
            align-items: center;
            gap: .25rem;
        }
        .user-name-cell .rank-meta { opacity: 0.8; }
        .rank-threshold-list {
            max-height: 760px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: .25rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 18px;
            background: rgba(248, 250, 252, 0.92);
            padding: 1rem;
        }
        .rank-threshold-list > div {
            padding: 0.65rem 0.75rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.96) !important;
            color: #111 !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            margin-bottom: 0.45rem;
        }
        .rank-threshold-list > div:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .rank-threshold-list .badge,
        .rank-threshold-list .badge.bg-light {
            background: rgba(241, 245, 249, 0.95) !important;
            color: #111 !important;
            border: 1px solid rgba(148, 163, 184, 0.22) !important;
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.5) !important;
        }
        .rank-threshold-list .fw-500 {
            color: #111 !important;
        }
        .rank-threshold-list .small {
            color: #263238 !important;
        }
        .rank-threshold-list .bi {
            opacity: 0.95;
        }
        body.dark-mode .rank-threshold-list,
        body.dark-mode .rank-threshold-list > div {
            background: rgba(255, 255, 255, 0.96) !important;
            color: #111 !important;
        }
        body.dark-mode .rank-threshold-list .badge,
        body.dark-mode .rank-threshold-list .badge.bg-light {
            background: rgba(241, 245, 249, 0.95) !important;
            color: #111 !important;
        }
        body.dark-mode .ranking-stat {
            background: rgba(248, 250, 252, 0.98) !important;
            color: #0f172a !important;
            border: 1px solid rgba(148, 163, 184, 0.18) !important;
        }
        body.dark-mode .ranking-stat .text-muted {
            color: #64748b !important;
        }
        .rank-threshold-list::-webkit-scrollbar {
            width: 10px;
        }
        .rank-threshold-list::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 10px;
        }
        .rect-rank {
            background: #ffffff;
            border-radius: 12px;
            padding: 12px;
            margin-top: 16px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 6px 18px rgba(11,22,40,0.06);
            width: 100%;
        }
        .xp-tips {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            color: #ffffff;
            border: none;
        }
        .xp-tips h6 {
            color: #ffffff !important;
        }
        .xp-tips ul li {
            color: rgba(255,255,255,0.95);
        }
        .xp-tips .bi {
            color: rgba(255,255,255,0.95);
            opacity: 1;
        }
        .xp-tips .list-unstyled li::marker { color: rgba(255,255,255,0.95); }
        .streak-badge {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            margin-left: .35rem;
            padding: .15rem .5rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            vertical-align: middle;
            border: 1px solid rgba(148, 163, 184, .2);
        }
        .streak-fire { background: rgba(239,68,68,.10); color: #dc2626; }
        .streak-cold { background: rgba(14,165,233,.12); color: #0284c7; }
        .streak-neutral { background: rgba(148,163,184,.12); color: #64748b; }
        .ranking-info-card {
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(59,130,246,.08), rgba(14,165,233,.05));
            border: 1px solid rgba(59,130,246,.14);
        }
        .ranking-info-card.is-inactive {
            opacity: .56;
            filter: grayscale(.25);
        }
        @media (min-width: 1200px) {
            .ranking-layout .dashboard-panel { height: auto; }
        }
        @media (max-width: 991.98px) {
            .ranking-hero { grid-template-columns: 1fr; }
            .ranking-stats-strip { grid-template-columns: 1fr; }
        }
        @media (max-width: 767.98px) {
            .ranking-table { min-width: 760px; }
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0 ranking-shell">
                    
                    <div class="ranking-hero mb-4 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1">Ranking użytkowników</h2>
                            <p class="text-muted mb-0">XP, aktywność i progres rang bez pustych przerw w układzie.</p>
                        </div>
                        <div class="ranking-stats-strip">
                            <div class="ranking-stat">
                                <div class="small text-muted">Twoje miejsce</div>
                                <strong class="h4 mb-0">#<?php echo $userRank; ?></strong>
                            </div>
                            <div class="ranking-stat">
                                <div class="small text-muted">Twoje XP</div>
                                <strong class="h4 mb-0"><?php echo number_format($currentXp); ?></strong>
                            </div>
                            <div class="ranking-stat">
                                <div class="small text-muted">Testy</div>
                                <strong class="h4 mb-0"><?php echo (int)$currentTests; ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 ranking-layout">
                        <div class="col-xl-9 col-lg-8">
                            <div class="dashboard-panel animate-in">
                                <div class="panel-header mb-4">
                                    <h5 class="panel-title mb-0">Pełna lista rankingowa</h5>
                                </div>
                                <div class="table-responsive ranking-list-scroll">
                                    <table class="table table-hover align-middle mb-0 ranking-table">
                                        <thead>
                                            <tr>
                                                <th class="ps-3" style="width: 100px;">Miejsce</th>
                                                <th>Użytkownik</th>
                                                <th style="width: 120px;">XP</th>
                                                <th style="width: 100px;">Testy</th>
                                                <th class="text-end pe-3" style="width: 120px;">Postęp</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topUsers as $index => $u): ?>
                                            <?php 
                                                $rank = $index + 1;
                                                $isMe = ($u['id'] == $userId);
                                                $rowRankInfo = getRankInfoByXp((int)$u['xp']);
                                                $rowStreak = getUserPerformanceStreak($pdo, (int)$u['id']);
                                                $rowProgress = max(((int)$u['xp'] > 0 ? 4 : 0), (int)($rowRankInfo['progress'] ?? 0));
                                                $rowAvatar = userAvatarSrc($u['avatar_path'] ?? '');
                                            ?>
                                            <tr class="ranking-row <?php echo $isMe ? 'current-user' : ''; ?> <?php echo $rank > 50 ? 'ranking-extra d-none' : ''; ?>" data-ranking-row>
                                                <td class="ps-3">
                                                    <div class="rank-number <?php echo ($rank <= 3) ? "rank-$rank" : 'bg-light text-muted'; ?>">
                                                        <?php echo $rank; ?>
                                                    </div>
                                                </td>
                                                <td class="user-name-cell">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <?php if ($rowAvatar): ?>
                                                            <img class="user-avatar-small ranking-avatar" src="<?php echo htmlspecialchars($rowAvatar); ?>" alt="">
                                                        <?php else: ?>
                                                            <div class="user-avatar-small ranking-avatar bg-primary bg-opacity-10 text-primary fw-bold">
                                                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div style="min-width: 0;">
                                                            <a class="fw-bold username-text text-decoration-none text-reset" href="profile.php?id=<?php echo (int)$u['id']; ?>" title="<?php echo htmlspecialchars($u['username']); ?>">
                                                                <?php echo htmlspecialchars($u['username']); ?><?php echo getUserBadgeHtml($u['role'] ?? 'user', (int)($u['is_verified'] ?? 0)); ?>
                                                                <span class="streak-badge <?php echo htmlspecialchars($rowStreak['class']); ?>" title="Seria wyników z pełnych testów"><?php echo htmlspecialchars($rowStreak['label']); ?></span>
                                                            </a>
                                                            <div class="small text-muted rank-meta">
                                                                <i class="bi <?php echo $rowRankInfo['icon']; ?> me-1"></i><?php echo htmlspecialchars($rowRankInfo['name']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                     <span class="badge xp-badge px-3 py-2">
                                                         <?php echo number_format($u['xp']); ?> XP
                                                     </span>
                                                 </td>
                                                <td><span class="text-muted fw-500"><?php echo $u['tests_count']; ?></span></td>
                                                <td class="text-end pe-3">
                                                    <div class="progress" style="height: 6px; width: 60px; margin-left: auto;">
                                                        <div class="progress-bar bg-primary" style="width: <?php echo $rowProgress; ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($topUsers) > 50): ?>
                                    <div class="pt-3 text-center">
                                        <button type="button" id="rankingLoadMore" class="btn btn-outline-primary rounded-pill px-4" data-visible="50">
                                            <i class="bi bi-chevron-down me-1"></i>Pokaż więcej
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="dashboard-panel mt-4 animate-in">
                                <div class="panel-header mb-3">
                                    <h5 class="panel-title mb-0"><i class="bi bi-calendar-event me-2 text-primary"></i>Wydarzenia</h5>
                                </div>
                                <?php if (empty($rankingEvents)): ?>
                                    <p class="text-muted mb-0">Brak aktywnych wydarzeń. System uruchomi kolejne automatycznie.</p>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($rankingEvents as $event): ?>
                                            <?php $isActive = ($event['status'] ?? '') === 'active'; ?>
                                            <div class="col-md-6">
                                                <div class="ranking-info-card <?php echo $isActive ? '' : 'is-inactive'; ?> p-3 h-100">
                                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                        <h6 class="fw-800 mb-0"><?php echo htmlspecialchars($event['name']); ?></h6>
                                                        <span class="badge <?php echo $isActive ? 'bg-success' : 'bg-secondary'; ?> rounded-pill">x<?php echo number_format((float)$event['multiplier'], 2); ?></span>
                                                    </div>
                                                    <p class="small text-muted mb-2"><?php echo htmlspecialchars($event['description']); ?></p>
                                                    <div class="small text-muted">
                                                        <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y', strtotime($event['starts_at'])); ?> - <?php echo date('d.m.Y', strtotime($event['ends_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-xl-3 col-lg-4 ranking-sidebar">
                            <div id="rank-threshold" class="dashboard-panel mb-4 animate-in">
                                <div class="panel-header mb-3">
                                    <h5 class="panel-title mb-0"><i class="bi bi-trophy me-2 text-primary"></i>Próg Rang</h5>
                                </div>
                                <div class="vstack gap-3 rank-threshold-list" style="background: rgba(248, 250, 252, 0.96);">
                                    <?php 
                                    $prevTier = '';
                                    foreach ($rankDefinitions as $rankDef): 
                                        $tierName = explode(' ', $rankDef['name'])[0];
                                        if ($tierName !== $prevTier && $prevTier !== ''):
                                            echo '<hr class="my-1 opacity-5">';
                                        endif;
                                        $prevTier = $tierName;
                                    ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small d-flex align-items-center">
                                                <i class="bi <?php echo htmlspecialchars($rankDef['icon']); ?> me-2" style="color: <?php echo htmlspecialchars($rankDef['color']); ?>; font-size: 1.1rem;"></i>
                                                <span class="fw-500"><?php echo htmlspecialchars($rankDef['name']); ?></span>
                                            </span>
                                            <span class="badge bg-light text-muted fw-bold"><?php echo number_format((int)$rankDef['min_xp']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <!-- User Stats Widget -->
                            <div class="dashboard-panel mb-4 animate-in user-rank-widget" style="border-top: 4px solid var(--primary-color);">
                                <div class="text-center py-2">
                                    <div class="display-5 fw-900 text-primary mb-0">#<?php echo $userRank; ?></div>
                                    <p class="text-muted fw-bold small text-uppercase letter-spacing-1">Twoja Pozycja</p>
                                    
                                    <div class="row g-2 mt-3">
                                        <div class="col-6">
                                            <div class="bg-light rounded-3 p-3">
                                                <div class="small text-muted mb-1">XP</div>
                                                <div class="h5 fw-bold mb-0"><?php echo number_format($currentXp); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="bg-light rounded-3 p-3">
                                                <div class="small text-muted mb-1">Testy</div>
                                                <div class="h5 fw-bold mb-0"><?php echo $currentTests; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($nextXp): ?>
                                    <div class="mt-4 text-start">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="small text-muted">Do następnej pozycji</span>
                                            <span class="small fw-bold text-primary"><?php echo number_format($xpToNext); ?> XP</span>
                                        </div>
                                        <div class="progress rounded-pill" style="height: 8px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: <?php echo $rankProgress; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($userOfDay): ?>
                            <?php $dayRank = getRankInfoByXp((int)$userOfDay['xp']); ?>
                            <div class="dashboard-panel mb-4 animate-in" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.05) 0%, rgba(245, 158, 11, 0.05) 100%); border: 1px solid rgba(245, 158, 11, 0.2);">
                                <div class="panel-header mb-3">
                                    <h5 class="panel-title mb-0 text-warning"><i class="bi bi-lightning-charge-fill me-2"></i>Użytkownik Dnia</h5>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar-small bg-warning text-white fw-bold shadow-sm" style="width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;"><?php echo strtoupper(substr($userOfDay['username'], 0, 1)); ?></div>
                                    <div>
                                        <div class="fw-800 text-dark"><?php echo htmlspecialchars($userOfDay['username']); ?></div>
                                        <div class="small text-muted"><i class="bi <?php echo $dayRank['icon']; ?> me-1"></i><?php echo htmlspecialchars($dayRank['name']); ?></div>
                                        <div class="mt-1"><span class="badge bg-warning bg-opacity-20 text-warning fw-bold">+<?php echo number_format((int)$userOfDay['today_xp']); ?> XP dzisiaj</span></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="ranking-info-card p-3 mt-4">
                                <h6 class="fw-800 mb-2"><i class="bi bi-fire text-danger me-2"></i>Seria przy nicku</h6>
                                <p class="small text-muted mb-2">🔥 oznacza serię pełnych testów z wynikiem min. 80%. ❄ oznacza cold streak po wynikach poniżej 50%.</p>
                                <p class="small text-muted mb-0">Do rankingu liczą się pełne testy rankingowe, więc krótkie treningi nie psują serii.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script>
        document.getElementById('rankingLoadMore')?.addEventListener('click', function () {
            const rows = Array.from(document.querySelectorAll('[data-ranking-row]'));
            const nextVisible = Math.min(rows.length, (Number(this.dataset.visible) || 20) + 10);
            rows.forEach((row, index) => row.classList.toggle('d-none', index >= nextVisible));
            this.dataset.visible = String(nextVisible);
            if (nextVisible >= rows.length) {
                this.remove();
            }
        });
    </script>
</body>
</html>
