<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$topUsers = getTopRankings($pdo, 200);
$rankingStreaks = getUsersPerformanceStreaks($pdo, array_column($topUsers, 'id'));
$userOfDay = getUserOfDay($pdo);
$rankDefinitions = getRankDefinitions($pdo);
$rankingEvents = getRankingEvents($pdo, 6);

// Get current user's rank and data
$stmt = $pdo->prepare("SELECT xp, role, (SELECT COUNT(*) FROM test_results WHERE user_id = ?) as tests_count FROM users WHERE id = ?");
$stmt->execute([$userId, $userId]);
$userData = $stmt->fetch();
$currentXp = $userData['xp'] ?? 0;
$currentTests = $userData['tests_count'] ?? 0;
$userRankingApplies = roleParticipatesInRanking($userData['role'] ?? 'user');

$userRank = getUserRank($pdo, $userId);

// Find next user's XP
$stmt = $pdo->prepare("SELECT xp FROM users WHERE xp > ? AND role IN ('user','wujek_luki') ORDER BY xp ASC LIMIT 1");
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
    <title>Ranking Użytkowników - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <style>
        .ranking-shell { max-width: 1320px; margin: 0 auto; }
        
        /* Hero Banner */
        .ranking-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1.5rem;
            align-items: center;
            padding: 1.75rem 2rem;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(168, 85, 247, 0.08) 100%);
            border: 1px solid rgba(99, 102, 241, 0.2);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }
        .ranking-stats-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .ranking-stat {
            padding: 0.95rem 1.2rem;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            text-align: center;
            transition: transform 0.2s ease;
        }
        .ranking-stat:hover {
            transform: translateY(-2px);
        }
        .ranking-stat .stat-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #64748b;
        }
        .ranking-stat .stat-num {
            font-size: 1.35rem;
            font-weight: 900;
            color: #0f172a;
        }

        /* Podium Showcase */
        .podium-container {
            margin-bottom: 2rem;
        }
        .podium-card {
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 1.25rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            box-shadow: 0 8px 25px rgba(15, 23, 42, 0.04);
        }
        .podium-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.15);
        }
        .podium-card-1 {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.08) 0%, rgba(245, 158, 11, 0.04) 100%);
            border: 2px solid rgba(245, 158, 11, 0.4);
            transform: scale(1.03);
        }
        .podium-card-2 {
            border: 1px solid rgba(148, 163, 184, 0.3);
        }
        .podium-card-3 {
            border: 1px solid rgba(217, 119, 6, 0.25);
        }
        .podium-avatar {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .podium-avatar.gold-avatar {
            width: 76px;
            height: 76px;
            border-color: #f59e0b;
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }
        .podium-crown-badge {
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            padding: 0.3rem 0.8rem;
            border-radius: 99px;
            display: inline-block;
            margin-bottom: 0.75rem;
        }
        .podium-crown-badge.gold {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: #422006;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        .podium-crown-badge.silver {
            background: linear-gradient(135deg, #e2e8f0, #94a3b8);
            color: #0f172a;
        }
        .podium-crown-badge.bronze {
            background: linear-gradient(135deg, #fed7aa, #d97706);
            color: #7c2d12;
        }

        /* Rank Numbers & Table */
        .rank-number {
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px; font-weight: 800;
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
            font-size: 0.95rem;
        }
        .rank-1 { background: linear-gradient(135deg, #fbbf24, #d97706); color: #422006; }
        .rank-2 { background: linear-gradient(135deg, #e2e8f0, #64748b); color: #0f172a; }
        .rank-3 { background: linear-gradient(135deg, #d97706, #92400e); color: #ffffff; }
        
        .ranking-row { height: 72px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 12px; }
        .ranking-row:hover { background-color: rgba(99, 102, 241, 0.05) !important; transform: scale(1.003); }
        .ranking-row.current-user { background-color: rgba(99, 102, 241, 0.1) !important; border-left: 4px solid #6366f1; }
        
        .xp-badge {
            background: rgba(99, 102, 241, 0.12);
            color: #4f46e5;
            font-weight: 800;
            border-radius: 8px;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }
        
        .ranking-avatar {
            width: 42px;
            height: 42px;
            min-width: 42px;
            aspect-ratio: 1 / 1;
            border-radius: 12px;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 42px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
        }
        .ranking-list-scroll {
            max-height: 860px;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 20px;
        }
        .user-name-cell { min-width: 0; width: 35%; }
        .user-name-cell .username-text,
        .user-name-cell .rank-meta {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            max-width: 260px;
        }
        .streak-badge {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            margin-left: .35rem;
            padding: .15rem .5rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            border: 1px solid rgba(148, 163, 184, .2);
        }
        .streak-fire { background: rgba(239,68,68,.12); color: #dc2626; border-color: rgba(239,68,68,.3); }
        .streak-cold { background: rgba(14,165,233,.14); color: #0284c7; border-color: rgba(14,165,233,.3); }
        .streak-neutral { background: rgba(148,163,184,.14); color: #64748b; }

        .rank-threshold-list {
            max-height: 340px;
            overflow-y: auto;
            border-radius: 16px;
            padding: 0.5rem;
        }
        .rank-threshold-item {
            padding: 0.65rem 0.85rem;
            border-radius: 12px;
            margin-bottom: 0.4rem;
            background: rgba(148, 163, 184, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .rank-threshold-item:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .user-of-day-card {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 20px;
        }

        /* Dark Theme Support */
        [data-bs-theme="dark"] .ranking-stat,
        body.dark-theme .ranking-stat {
            background: rgba(15, 23, 42, 0.75) !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }
        [data-bs-theme="dark"] .ranking-stat .stat-num,
        body.dark-theme .ranking-stat .stat-num {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .ranking-stat .stat-title,
        body.dark-theme .ranking-stat .stat-title {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .podium-card,
        body.dark-theme .podium-card {
            background: rgba(15, 23, 42, 0.75);
            border-color: rgba(255, 255, 255, 0.12);
        }
        [data-bs-theme="dark"] .podium-card-1,
        body.dark-theme .podium-card-1 {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(15, 23, 42, 0.85) 100%);
            border-color: rgba(245, 158, 11, 0.5);
        }
        [data-bs-theme="dark"] .xp-badge,
        body.dark-theme .xp-badge {
            color: #a5b4fc;
        }
        [data-bs-theme="dark"] .rank-threshold-item,
        body.dark-theme .rank-threshold-item {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }

        @media (max-width: 991.98px) {
            .ranking-hero { grid-template-columns: 1fr; }
            .ranking-stats-strip { grid-template-columns: 1fr; }
        }
        @media (max-width: 767.98px) {
            .ranking-table { min-width: 740px; }
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
                    
                    <!-- Hero Banner -->
                    <div class="ranking-hero mb-4 animate-in">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-primary bg-opacity-20 text-primary fw-bold px-3 py-1 rounded-pill">
                                    <i class="bi bi-trophy-fill me-1"></i>Liga ZSEM Tech
                                </span>
                            </div>
                            <h2 class="fw-black mb-1"><i class="bi bi-award-fill text-primary me-2"></i>Ranking Użytkowników</h2>
                            <p class="text-muted mb-0">Zdobywaj punkty XP z testów CKE, buduj serie wyników i awansuj w klasyfikacji szkół.</p>
                        </div>
                        <div class="ranking-stats-strip">
                            <div class="ranking-stat">
                                <div class="stat-title">Twoje miejsce</div>
                                <div class="stat-num"><?php echo $userRankingApplies ? '#' . (int)$userRank : 'Nie dotyczy'; ?></div>
                            </div>
                            <div class="ranking-stat">
                                <div class="stat-title">Twoje XP</div>
                                <div class="stat-num"><?php echo number_format($currentXp); ?></div>
                            </div>
                            <div class="ranking-stat">
                                <div class="stat-title">Testy</div>
                                <div class="stat-num"><?php echo (int)$currentTests; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Dynamic Ranking Filters (R6) ── -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4" id="rankingFiltersCard">
                        <div class="card-body py-3 px-4">
                            <div class="row g-2 align-items-end">
                                <div class="col-6 col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1" for="filterClass">Klasa</label>
                                    <select id="filterClass" class="form-select form-select-sm rounded-3">
                                        <option value="">Wszystkie klasy</option>
                                        <?php foreach (['1P','2P','3P','4P','5P','1T','2T','3T','4T','5T'] as $cls): ?>
                                        <option value="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($cls) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1" for="filterQual">Kwalifikacja</label>
                                    <select id="filterQual" class="form-select form-select-sm rounded-3">
                                        <option value="">Wszystkie</option>
                                        <option value="INF.02">INF.02</option>
                                        <option value="INF.03">INF.03</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1" for="filterTime">Okres</label>
                                    <select id="filterTime" class="form-select form-select-sm rounded-3">
                                        <option value="all">Wszystkie czasy</option>
                                        <option value="week">Ten tydzień</option>
                                        <option value="month">Ten miesiąc</option>
                                        <option value="season">Sezon (90 dni)</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3 d-flex gap-2">
                                    <button class="btn btn-primary btn-sm rounded-3 px-4 fw-bold flex-fill" id="applyFilters">
                                        <i class="bi bi-funnel-fill me-1"></i>Filtruj
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm rounded-3" id="resetFilters" title="Resetuj filtry">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic filtered leaderboard (injected by JS when filters active) -->
                    <div id="filteredRankingWrap" style="display:none" class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <h3 class="fw-bold fs-5 mb-0"><i class="bi bi-list-ol me-2 text-primary"></i>Wyniki filtrowane</h3>
                            <span class="badge bg-primary bg-opacity-15 text-primary" id="filteredCount"></span>
                            <span class="ms-auto text-muted small" id="filteredLabel"></span>
                        </div>
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 align-middle" id="filteredTable">
                                        <thead>
                                            <tr class="table-light">
                                                <th class="ps-4" style="width:60px">#</th>
                                                <th>Użytkownik</th>
                                                <th>Klasa</th>
                                                <th>XP</th>
                                                <th>Testy</th>
                                                <th>Śr. wynik</th>
                                                <th>Odznaka</th>
                                            </tr>
                                        </thead>
                                        <tbody id="filteredTableBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /R6 Filters -->

                    <!-- Top 3 Podium Showcase -->
                    <?php if (count($topUsers) >= 3): ?>
                    <div class="row g-3 mb-4 podium-container align-items-end">
                        <!-- 2nd Place (Silver) -->
                        <div class="col-4 col-md-4 order-1">
                            <?php $u2 = $topUsers[1]; $r2 = getRankInfoByXp((int)$u2['xp']); $av2 = userAvatarSrc($u2['avatar_path'] ?? ''); ?>
                            <div class="podium-card podium-card-2 text-center p-3">
                                <div class="podium-crown-badge silver"><i class="bi bi-award-fill me-1"></i>2. MIEJSCE</div>
                                <div class="d-flex justify-content-center mb-2">
                                    <?php if ($av2): ?>
                                        <img src="<?php echo htmlspecialchars($av2); ?>" class="podium-avatar rounded-circle" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="podium-avatar rounded-circle bg-secondary bg-opacity-20 text-secondary fw-bold d-flex align-items-center justify-content-center fs-4">
                                            <?php echo strtoupper(substr($u2['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-bold text-truncate mb-1" title="<?php echo htmlspecialchars($u2['username']); ?>"><?php echo htmlspecialchars($u2['username']); ?></div>
                                <div class="badge xp-badge px-3 py-1 mb-2"><?php echo number_format($u2['xp']); ?> XP</div>
                                <div class="small text-muted"><i class="bi <?php echo $r2['icon']; ?> me-1"></i><?php echo htmlspecialchars($r2['name']); ?></div>
                            </div>
                        </div>

                        <!-- 1st Place (Gold) -->
                        <div class="col-4 col-md-4 order-2">
                            <?php $u1 = $topUsers[0]; $r1 = getRankInfoByXp((int)$u1['xp']); $av1 = userAvatarSrc($u1['avatar_path'] ?? ''); ?>
                            <div class="podium-card podium-card-1 text-center p-3 p-md-4 shadow-lg">
                                <div class="podium-crown-badge gold"><i class="bi bi-crown-fill me-1"></i>1. MIEJSCE</div>
                                <div class="d-flex justify-content-center mb-2">
                                    <?php if ($av1): ?>
                                        <img src="<?php echo htmlspecialchars($av1); ?>" class="podium-avatar gold-avatar rounded-circle" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="podium-avatar gold-avatar rounded-circle bg-warning bg-opacity-25 text-warning fw-black d-flex align-items-center justify-content-center fs-3">
                                            <?php echo strtoupper(substr($u1['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-black text-truncate fs-5 mb-1" title="<?php echo htmlspecialchars($u1['username']); ?>"><?php echo htmlspecialchars($u1['username']); ?></div>
                                <div class="badge bg-warning bg-opacity-25 text-warning fw-black fs-6 px-3 py-1 mb-2"><?php echo number_format($u1['xp']); ?> XP</div>
                                <div class="small fw-bold text-warning"><i class="bi <?php echo $r1['icon']; ?> me-1"></i><?php echo htmlspecialchars($r1['name']); ?></div>
                            </div>
                        </div>

                        <!-- 3rd Place (Bronze) -->
                        <div class="col-4 col-md-4 order-3">
                            <?php $u3 = $topUsers[2]; $r3 = getRankInfoByXp((int)$u3['xp']); $av3 = userAvatarSrc($u3['avatar_path'] ?? ''); ?>
                            <div class="podium-card podium-card-3 text-center p-3">
                                <div class="podium-crown-badge bronze"><i class="bi bi-award-fill me-1"></i>3. MIEJSCE</div>
                                <div class="d-flex justify-content-center mb-2">
                                    <?php if ($av3): ?>
                                        <img src="<?php echo htmlspecialchars($av3); ?>" class="podium-avatar rounded-circle" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="podium-avatar rounded-circle bg-danger bg-opacity-20 text-danger fw-bold d-flex align-items-center justify-content-center fs-4">
                                            <?php echo strtoupper(substr($u3['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-bold text-truncate mb-1" title="<?php echo htmlspecialchars($u3['username']); ?>"><?php echo htmlspecialchars($u3['username']); ?></div>
                                <div class="badge bg-danger bg-opacity-20 text-danger px-3 py-1 mb-2"><?php echo number_format($u3['xp']); ?> XP</div>
                                <div class="small text-muted"><i class="bi <?php echo $r3['icon']; ?> me-1"></i><?php echo htmlspecialchars($r3['name']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row g-4 ranking-layout">
                        <!-- Left Main Column (Ranking Table + Events) -->
                        <div class="col-xl-9 col-lg-8">
                            <div class="dashboard-panel animate-in ranking-list-panel">
                                <div class="panel-header mb-4 d-flex justify-content-between align-items-center">
                                    <h5 class="panel-title mb-0"><i class="bi bi-list-ol me-2 text-primary"></i>Pełna lista rankingowa</h5>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">TOP <?php echo count($topUsers); ?></span>
                                </div>
                                <div class="table-responsive ranking-list-scroll">
                                    <table class="table table-hover align-middle mb-0 ranking-table">
                                        <thead>
                                            <tr>
                                                <th class="ps-3" style="width: 90px;">Miejsce</th>
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
                                                $rowStreak = $rankingStreaks[(int)$u['id']] ?? classifyUserPerformanceStreakScores([]);
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
                                                            <img class="user-avatar-small ranking-avatar" src="<?php echo htmlspecialchars($rowAvatar); ?>" alt="" loading="lazy" decoding="async">
                                                        <?php else: ?>
                                                            <div class="user-avatar-small ranking-avatar bg-primary bg-opacity-10 text-primary fw-bold">
                                                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div style="min-width: 0;">
                                                            <a class="fw-bold username-text text-decoration-none text-reset" href="user/profile.php?id=<?php echo (int)$u['id']; ?>" title="<?php echo htmlspecialchars($u['username']); ?>">
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
                                        <button type="button" id="rankingLoadMore" class="btn btn-outline-primary rounded-pill px-4 shadow-sm" data-visible="50">
                                            <i class="bi bi-chevron-down me-1"></i>Pokaż więcej
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ranking Events -->
                            <div class="dashboard-panel mt-4 animate-in">
                                <div class="panel-header mb-3">
                                    <h5 class="panel-title mb-0"><i class="bi bi-calendar-event me-2 text-primary"></i>Wydarzenia i Mnożniki XP</h5>
                                </div>
                                <?php if (empty($rankingEvents)): ?>
                                    <p class="text-muted mb-0">Brak aktywnych wydarzeń. Kolejne wydarzenia zostaną uruchomione automatycznie.</p>
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

                        <!-- Right Sidebar Column (User Stats Widget + Rank Thresholds) -->
                        <div class="col-xl-3 col-lg-4 ranking-sidebar">
                            
                            <!-- User Stats Widget -->
                            <div class="dashboard-panel mb-4 animate-in user-rank-widget" style="border-top: 4px solid var(--primary-color);">
                                <div class="text-center py-2">
                                    <div class="display-5 fw-900 text-primary mb-0"><?php echo $userRankingApplies ? '#' . (int)$userRank : 'Nie dotyczy'; ?></div>
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
                                    
                                    <?php if ($userRankingApplies && $nextXp): ?>
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

                            <!-- Rank Thresholds Panel -->
                            <div id="rank-threshold" class="dashboard-panel mb-4 animate-in">
                                <div class="panel-header mb-3">
                                    <h5 class="panel-title mb-0"><i class="bi bi-trophy me-2 text-primary"></i>Próg Rang</h5>
                                </div>
                                <div class="rank-threshold-list">
                                    <?php foreach ($rankDefinitions as $rankDef): ?>
                                        <div class="rank-threshold-item">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi <?php echo htmlspecialchars($rankDef['icon']); ?> fs-5" style="color: <?php echo htmlspecialchars($rankDef['color']); ?>;"></i>
                                                <span class="fw-bold small"><?php echo htmlspecialchars($rankDef['name']); ?></span>
                                            </div>
                                            <span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?php echo number_format((int)$rankDef['min_xp']); ?> XP</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($userOfDay): ?>
                            <?php $dayRank = getRankInfoByXp((int)$userOfDay['xp']); ?>
                            <div class="dashboard-panel user-of-day-card mb-4 animate-in">
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

        // ── R6: Dynamic Ranking Filters ───────────────────────────────────────
        (function () {
            const applyBtn  = document.getElementById('applyFilters');
            const resetBtn  = document.getElementById('resetFilters');
            const wrap      = document.getElementById('filteredRankingWrap');
            const tbody     = document.getElementById('filteredTableBody');
            const countEl   = document.getElementById('filteredCount');
            const labelEl   = document.getElementById('filteredLabel');

            async function fetchRanking() {
                const cls   = document.getElementById('filterClass')?.value  || '';
                const qual  = document.getElementById('filterQual')?.value   || '';
                const time  = document.getElementById('filterTime')?.value   || 'all';

                const params = new URLSearchParams({ class: cls, qualification: qual, timeframe: time });
                if (applyBtn) { applyBtn.disabled = true; applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Ładowanie...'; }

                try {
                    const res  = await fetch('api/ranking_data.php?' + params, { credentials: 'same-origin' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.error);

                    renderTable(data.leaderboard);

                    const labels = [];
                    if (cls)  labels.push(`Klasa: ${cls}`);
                    if (qual) labels.push(`Kwalifikacja: ${qual}`);
                    if (time !== 'all') labels.push({ week:'Tydzień', month:'Miesiąc', season:'Sezon' }[time] || time);

                    if (countEl) countEl.textContent = data.total + ' wyników';
                    if (labelEl) labelEl.textContent  = labels.length ? labels.join(' · ') : 'Wszystkie';
                    if (wrap)   wrap.style.display = '';

                } catch (err) {
                    console.warn('Ranking fetch error:', err);
                } finally {
                    if (applyBtn) { applyBtn.disabled = false; applyBtn.innerHTML = '<i class="bi bi-funnel-fill me-1"></i>Filtruj'; }
                }
            }

            function renderTable(rows) {
                if (!tbody) return;
                tbody.innerHTML = '';
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Brak wyników dla wybranych filtrów.</td></tr>';
                    return;
                }
                rows.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="ps-4 fw-bold">${r.rank <= 3 ? ['🥇','🥈','🥉'][r.rank-1] : '#'+r.rank}</td>
                        <td class="fw-bold">${escHtml(r.username)}</td>
                        <td>${escHtml(r.class || '—')}</td>
                        <td class="fw-bold text-primary">${Number(r.xp).toLocaleString('pl-PL')}</td>
                        <td>${r.test_count}</td>
                        <td>${r.avg_score > 0 ? r.avg_score + '%' : '—'}</td>
                        <td>${r.is_champion ? '<span class="badge bg-warning text-dark fw-bold"><i class="bi bi-crown-fill me-1"></i>Mistrz Klasy</span>' : ''}</td>`;
                    tbody.appendChild(tr);
                });
            }

            function escHtml(str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            applyBtn?.addEventListener('click', fetchRanking);
            resetBtn?.addEventListener('click', () => {
                document.getElementById('filterClass').value = '';
                document.getElementById('filterQual').value  = '';
                document.getElementById('filterTime').value  = 'all';
                if (wrap) wrap.style.display = 'none';
            });
        }());
    </script>
</body>
</html>
