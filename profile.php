<?php
// Include required files
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Start secure session and require login
startSecureSession();
requireLogin();

// Fetch user data
$myId = $_SESSION['user_id'];
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : $myId;
$isOwnProfile = ($viewId === $myId);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$viewId]);
$userData = $stmt->fetch();

if (!$userData) {
    die("Użytkownik nie istnieje.");
}

header('Cache-Control: no-cache, no-store, must-revalidate');

$username = $userData['username'] ?? 'Nieznany';
$displayName = userDisplayName($userData);
$displayHandle = userHandle($userData);
$role = $userData['role'] ?? 'user';
$roleBadge = getUserRoleBadge($role);
$bio = $userData['bio'] ?? 'Brak opisu profilu.';
$avatarId = $userData['avatar_id'] ?? 1;
$avatarPath = trim((string)($userData['avatar_path'] ?? ''));
$avatarSrc = userAvatarSrc($avatarPath) ?? '';
$userId = $viewId; // Used by stats functions below

$isProfilePublic = (bool)($userData['profile_public'] ?? 1);
$isStatsPublic = (bool)($userData['stats_public'] ?? 1);
$allowFriendRequests = (bool)($userData['allow_friend_requests'] ?? 1);
$allowProfileComments = (bool)($userData['allow_profile_comments'] ?? 1);

// Admins override privacy, owners override privacy
$myRole = $_SESSION['role'] ?? 'user';
$canViewProfile = $isProfilePublic || $isOwnProfile || roleHasAdminAccess($myRole);
$canViewStats = $isStatsPublic || $isOwnProfile || roleHasAdminAccess($myRole);


// Fetch Friends count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'");
$stmt->execute([$userId, $userId]);
$friendsCount = $stmt->fetchColumn();

// Fetch Pending requests (only for own profile)
$pendingRequests = 0;
if ($isOwnProfile) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE friend_id = ? AND status = 'pending'");
    $stmt->execute([$myId]);
    $pendingRequests = $stmt->fetchColumn();
}

// Sync missions only for the profile owner; viewing another profile must not mutate their mission state.
$missionData = $isOwnProfile ? syncUserMissions($pdo, $userId) : ['missions' => [], 'pool' => []];
$currentMissions = $missionData['missions'];
$missionPool = $missionData['pool'];

// Fetch all required data
$stats = getUserStats($pdo, $userId);
$testResults = getTestResults($pdo, $userId, 50);
$profileHistoryResults = getUnifiedUserHistory($pdo, $userId, 50);
$chartTestResults = getQualifiedTestResults($pdo, $userId, 100, 40);
$totalQuestions = count(loadQuestions($pdo, false));

// Format total time spent
$totalSeconds = $stats['total_time_spent'];
$hours = floor($totalSeconds / 3600);
$minutes = floor(($totalSeconds % 3600) / 60);
$seconds = $totalSeconds % 60;
if ($hours > 0) {
    $totalTimeFormatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
} else {
    $totalTimeFormatted = sprintf('%d minut', $minutes);
}

// Prepare data for chart - last 10 tests
$chartLabels = [];
$chartData = [];
$chartResults = array_slice(array_reverse($chartTestResults), 0, 10);
foreach ($chartResults as $result) {
    $chartLabels[] = date('d.m', strtotime($result['test_date'] ?? $result['completed_at'] ?? 'now'));
    $chartData[] = round($result['score_percent'], 2);
}

// Best and worst scores
$bestScore = null;
$worstScore = null;
if (!empty($testResults)) {
    $sortedByScore = $testResults;
    usort($sortedByScore, function($a, $b) {
        return $b['score_percent'] <=> $a['score_percent'];
    });
    $bestScore = $sortedByScore[0];
    $worstScore = end($sortedByScore);
}

// Mode info is stored in test_results.mode
// No need for complex mode counting from test_answers

// Get last 10 history entries for table, including duels and teacher tests.
$tableResults = array_slice($profileHistoryResults, 0, 10);

$profileSections = [
    'education' => [], 'certificates' => [], 'courses' => [], 'volunteering' => [],
    'languages' => [], 'organizations' => [], 'social_links' => [], 'comments' => []
];
try {
    $queries = [
        'education' => "SELECT * FROM user_education WHERE user_id = ? ORDER BY start_year DESC",
        'certificates' => "SELECT * FROM user_certificates WHERE user_id = ? ORDER BY obtained_date DESC, id DESC",
        'courses' => "SELECT * FROM user_courses WHERE user_id = ? ORDER BY completed_date DESC, id DESC",
        'volunteering' => "SELECT * FROM user_volunteering WHERE user_id = ? ORDER BY start_date DESC, id DESC",
        'languages' => "SELECT * FROM user_languages WHERE user_id = ? ORDER BY language_name",
        'organizations' => "SELECT * FROM user_organizations WHERE user_id = ? ORDER BY start_date DESC, id DESC",
        'social_links' => "SELECT * FROM user_social_links WHERE user_id = ? ORDER BY platform",
    ];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $profileSections[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt = $pdo->prepare("
        SELECT pc.*, u.username, u.role, u.is_verified, u.avatar_path
        FROM profile_comments pc
        JOIN users u ON u.id = pc.author_id
        WHERE pc.profile_user_id = ?
        ORDER BY pc.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $profileSections['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Profile sections unavailable: ' . $e->getMessage());
}

$rankInfo = getRankInfoByXp((int)($userData['xp'] ?? 0));
$hasProfessionalData = false;
foreach (['education','certificates','courses','volunteering','languages','organizations','social_links'] as $sectionKey) {
    if (!empty($profileSections[$sectionKey])) {
        $hasProfessionalData = true;
        break;
    }
}
$languagePresets = ['Angielski', 'Niemiecki', 'Hiszpański', 'Francuski', 'Włoski', 'Polski', 'Ukraiński', 'Rosyjski', 'Czeski', 'Słowacki'];
$socialPlatforms = [
    'github' => ['GitHub', 'bi-github'],
    'linkedin' => ['LinkedIn', 'bi-linkedin'],
    'instagram' => ['Instagram', 'bi-instagram'],
    'youtube' => ['YouTube', 'bi-youtube'],
    'facebook' => ['Facebook', 'bi-facebook'],
    'x' => ['X', 'bi-twitter-x'],
    'tiktok' => ['TikTok', 'bi-tiktok'],
    'gitlab' => ['GitLab', 'bi-gitlab'],
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil użytkownika - Platforma Testowa</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .stats-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }
        
        body.dark-mode .stats-card {
            background-color: #1e293b;
            border-color: #334155;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .highlight-card {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            height: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .highlight-card.worst {
            background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
        }
        .highlight-card::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }
        .highlight-card h5 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            opacity: 0.9;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .highlight-card .score-val {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .highlight-card .score-meta {
            font-size: 0.875rem;
            opacity: 0.85;
            font-weight: 500;
        }
        .highlight-card .badge-mode {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.7rem;
            padding: 0.4rem 0.75rem;
            border-radius: 50px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .profile-hero-card {
            border-radius: 28px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .08);
            border: 1px solid rgba(148, 163, 184, .18);
        }
        .profile-header-bg {
            min-height: 150px;
            background:
                radial-gradient(circle at 12% 10%, rgba(255,255,255,.28), transparent 28%),
                linear-gradient(135deg, var(--primary-color-dark), #7c3aed);
        }
        .profile-header-inner {
            margin-top: -46px;
        }
        .profile-header-content {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: end;
            gap: 1.5rem;
            background: rgba(255,255,255,.96);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, .08);
        }
        .profile-left-stack {
            display: grid;
            gap: .75rem;
            width: min(260px, 100%);
        }
        .user-avatar-large {
            width: 128px;
            height: 128px;
            border-radius: 28px !important;
            align-self: center;
            margin-top: -10px;
            position: relative;
            z-index: 2;
        }
        .profile-header-meta {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .profile-role-badge {
            padding: .65rem 1rem;
            border-radius: 999px;
            font-size: .78rem;
            letter-spacing: .02em;
            text-transform: uppercase;
            font-weight: 800;
        }
        .profile-mini-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            margin-top: 1rem;
        }
        .profile-mini-stat {
            padding: .75rem;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, .16);
            color: #1f2937;
        }
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(59, 130, 246, 0.14);
            color: var(--primary-color-dark);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 999px;
            padding: 0.5rem 0.9rem;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .profile-rank-highlight {
            margin-top: 0;
            padding: .75rem;
            border-radius: 20px;
            max-width: 100%;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--rank-color) 20%, transparent), rgba(255,255,255,.88)),
                #ffffff;
            border: 1px solid color-mix(in srgb, var(--rank-color) 35%, rgba(148,163,184,.25));
            box-shadow: 0 16px 35px color-mix(in srgb, var(--rank-color) 18%, transparent);
        }
        .profile-rank-top {
            display: flex;
            align-items: center;
            gap: .9rem;
        }
        .profile-rank-icon {
            width: 42px;
            height: 42px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--rank-color);
            color: #fff;
            font-size: 1.25rem;
            box-shadow: 0 12px 26px color-mix(in srgb, var(--rank-color) 38%, transparent);
        }
        .profile-rank-label {
            display: block;
            color: #64748b;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .profile-rank-name {
            display: block;
            color: #0f172a;
            font-size: .95rem;
            font-weight: 900;
        }
        .profile-rank-xp {
            margin-left: auto;
            padding: .45rem .75rem;
            border-radius: 999px;
            background: rgba(255,255,255,.75);
            color: #0f172a;
            border: 1px solid rgba(148,163,184,.18);
            font-size: .78rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .profile-account-summary {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }
        .profile-account-tile {
            padding: .85rem;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, .16);
        }
        .profile-rank-progress {
            height: 10px;
            border-radius: 999px;
            background: rgba(15,23,42,.08);
            overflow: hidden;
        }
        .profile-rank-progress-bar {
            height: 100%;
            width: var(--rank-progress);
            border-radius: inherit;
            background: linear-gradient(90deg, var(--rank-color), #facc15);
        }
        .profile-hero-card {
            border-radius: 28px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .08);
            border: 1px solid rgba(148, 163, 184, .18);
            overflow: hidden;
        }
        .profile-header-bg {
            min-height: 150px;
            background:
                radial-gradient(circle at 12% 10%, rgba(255,255,255,.28), transparent 28%),
                linear-gradient(135deg, var(--primary-color-dark), #7c3aed);
        }
        .profile-header-content {
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: end;
            gap: 1.5rem;
            background: rgba(255,255,255,.98);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, .08);
        }
        .profile-header-content .ms-auto {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            gap: 0.75rem;
        }
        .profile-header-content .btn {
            min-width: 170px;
        }
        body.dark-mode .profile-header-content,
        body.dark-mode .profile-mini-stat,
        body.dark-mode .profile-account-tile {
            background: rgba(15, 23, 42, .94);
            border-color: rgba(148, 163, 184, .22);
            color: #e2e8f0;
        }
        body.dark-mode .profile-badge {
            background: rgba(96, 165, 250, 0.16);
            color: #bfdbfe;
            border-color: rgba(96, 165, 250, 0.3);
        }
        body.dark-mode .profile-rank-highlight {
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--rank-color) 18%, transparent), rgba(15,23,42,.88)),
                #0f172a;
            border-color: color-mix(in srgb, var(--rank-color) 35%, rgba(148,163,184,.25));
        }
        body.dark-mode .profile-rank-name,
        body.dark-mode .profile-rank-xp {
            color: #e5e7eb;
        }
        body.dark-mode .profile-rank-xp {
            background: rgba(15,23,42,.72);
        }
        body.dark-mode .profile-rank-label {
            color: #94a3b8;
        }
        .comment-card {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 12px;
            padding: .75rem;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .comment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 50px rgba(15, 23, 42, .08);
        }
        .profile-comments-panel {
            margin-top: clamp(2rem, 5vw, 4rem);
            border: 1px solid rgba(59, 130, 246, .12);
        }
        .comment-form-card {
            border-radius: 12px;
            padding: .85rem;
            background: linear-gradient(135deg, rgba(59,130,246,.06), rgba(14,165,233,.04));
            border: 1px solid rgba(59,130,246,.14);
        }
        .comment-meta {
            border-radius: 999px;
            padding: .25rem .6rem;
            background: rgba(15,23,42,.04);
        }
        .comment-card-grid {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) auto;
            gap: .7rem;
            align-items: start;
        }
        .comment-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1, #2563eb);
            color: #fff;
            font-weight: 900;
            box-shadow: 0 12px 24px rgba(99,102,241,.22);
        }
        .comment-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .comment-author-link {
            color: #0f172a;
            text-decoration: none;
            font-weight: 900;
        }
        .comment-author-link:hover {
            color: var(--primary-color);
        }
        .comment-bubble {
            margin-top: .35rem;
            padding: .65rem .75rem;
            border-radius: 10px;
            background: linear-gradient(135deg, #f8fafc, #eef2ff);
            color: #1f2937;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.55;
        }
        .professional-empty-section:not(.editing) {
            display: none;
        }
        .comment-delete-form {
            align-self: center;
        }
        .comment-counter {
            font-weight: 700;
        }
        .professional-section.is-empty:not(.editing) {
            display: none;
        }
        .professional-section {
            border-radius: 18px;
            padding: 1rem;
            border: 1px solid rgba(148, 163, 184, .16);
            background: rgba(248, 250, 252, .72);
        }
        .professional-profile-panel .accordion-item {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: .85rem;
            background: #ffffff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
        }
        .professional-profile-panel .accordion-button {
            font-weight: 800;
            color: #0f172a;
            background: linear-gradient(135deg, rgba(248,250,252,.96), rgba(239,246,255,.86));
        }
        .professional-item {
            border-radius: 16px !important;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, .18) !important;
        }
        body.dark-mode .comment-card {
            background: #1e293b;
            border-color: rgba(148, 163, 184, .22);
        }
        body.dark-mode .comment-author-link {
            color: #f8fafc;
        }
        body.dark-mode .comment-bubble {
            background: rgba(15, 23, 42, .72);
            color: #e5e7eb;
            border: 1px solid rgba(148, 163, 184, .18);
        }
        body.dark-mode .comment-form-card,
        body.dark-mode .comment-meta,
        body.dark-mode .professional-profile-panel .accordion-item,
        body.dark-mode .professional-profile-panel .accordion-button,
        body.dark-mode .professional-item {
            background: rgba(15, 23, 42, .82);
            color: #e5e7eb;
            border-color: rgba(148, 163, 184, .24) !important;
        }
        body.dark-mode .professional-section {
            background: rgba(15, 23, 42, .42);
            border-color: rgba(148, 163, 184, .22);
        }
        .view-details-btn {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .view-details-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(99, 102, 241, 0.18);
        }
        @media (max-width: 767.98px) {
            .profile-header-bg { min-height: 115px; }
            .profile-header-content {
                grid-template-columns: 1fr;
                align-items: start;
                text-align: left;
            }
            .user-avatar-large {
                width: 96px;
                height: 96px;
                font-size: 2rem !important;
                margin-top: 0;
            }
            .profile-mini-stats {
                grid-template-columns: 1fr;
            }
            .profile-left-stack {
                width: 100%;
            }
            .profile-account-summary {
                grid-template-columns: 1fr;
            }
            .profile-rank-top {
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .profile-rank-xp {
                margin-left: 0;
            }
            .comment-card-grid {
                grid-template-columns: 42px minmax(0, 1fr);
            }
            .comment-delete-form {
                grid-column: 2;
                justify-self: start;
            }
            .profile-header-content .ms-auto {
                margin-left: 0 !important;
                width: 100%;
            }
            .profile-header-content .btn {
                width: 100%;
                margin: .25rem 0 0 0 !important;
            }
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
        <?php
        $flashMsg = getSessionMessage();
        if ($flashMsg): ?>
            <div class="alert alert-<?php echo ($flashMsg['type'] === 'error') ? 'danger' : ($flashMsg['type'] === 'success' ? 'success' : 'info'); ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo ($flashMsg['type'] === 'success') ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
                <?php echo htmlspecialchars($flashMsg['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <!-- Profile Hero -->
        <div class="dashboard-panel mb-4 animate-in overflow-hidden p-0 profile-hero-card">
            <div class="profile-header-bg"></div>
            <div class="profile-header-inner px-4 pb-4">
                <div class="profile-header-content">
                    <div class="profile-left-stack">
                        <?php if ($avatarSrc): ?>
                            <img class="user-avatar-large shadow-lg border border-4 border-white" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Zdjęcie profilowe <?php echo htmlspecialchars($displayName); ?>" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="user-avatar-large shadow-lg border border-4 border-white" style="background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; font-weight: 800;">
                                <?php echo strtoupper(substr($displayName, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canViewStats): ?>
                        <div class="profile-rank-highlight" style="--rank-color: <?php echo htmlspecialchars($rankInfo['color']); ?>; --rank-progress: <?php echo (int)$rankInfo['progress']; ?>%;">
                            <div class="profile-rank-top mb-2">
                                <div class="profile-rank-icon" aria-hidden="true">
                                    <i class="bi <?php echo htmlspecialchars($rankInfo['icon']); ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="profile-rank-label">Aktualna ranga</span>
                                    <strong class="profile-rank-name"><?php echo htmlspecialchars($rankInfo['name']); ?></strong>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                                <span><?php echo number_format((int)($userData['xp'] ?? 0)); ?> XP</span>
                                <span><?php echo (int)$rankInfo['progress']; ?>%</span>
                            </div>
                            <div class="profile-rank-progress" aria-label="Postęp rangi <?php echo (int)$rankInfo['progress']; ?>%">
                                <div class="profile-rank-progress-bar"></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="profile-rank-highlight" style="--rank-color: #64748b; --rank-progress: 0%;">
                            <div class="profile-rank-top">
                                <div class="profile-rank-icon"><i class="bi bi-lock"></i></div>
                                <div><span class="profile-rank-label">Statystyki</span><strong class="profile-rank-name">Prywatne</strong></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="pb-2">
                        <h2 class="fw-bold mb-0 d-flex align-items-center gap-2 flex-wrap">
                            <?php echo htmlspecialchars($displayName); ?><?php echo getUserBadgeHtml($role, (int)($userData['is_verified'] ?? 0)); ?>
                        </h2>
                        <?php if ($displayHandle !== '' && $displayHandle !== '@' . $displayName): ?>
                            <div class="text-muted small fw-semibold mt-1"><?php echo htmlspecialchars($displayHandle); ?></div>
                        <?php endif; ?>
                        <div class="profile-header-meta mt-2">
                            <span class="profile-role-badge <?php echo $roleBadge['class']; ?>">
                                <?php echo htmlspecialchars($roleBadge['label']); ?>
                            </span>
                            <span class="text-muted small d-flex align-items-center gap-1"><i class="bi bi-people"></i><?php echo $friendsCount; ?> znajomych</span>
                        </div>
                        <div class="profile-mini-stats">
                            <div class="profile-mini-stat">
                                <div class="small text-muted">XP</div>
                                <strong><?php echo $canViewStats ? number_format((int)($userData['xp'] ?? 0)) : '—'; ?></strong>
                            </div>
                            <div class="profile-mini-stat">
                                <div class="small text-muted">Testy</div>
                                <strong><?php echo $canViewStats ? number_format((int)($stats['total_tests'] ?? 0)) : '—'; ?></strong>
                            </div>
                            <div class="profile-mini-stat">
                                <div class="small text-muted">Średnia</div>
                                <strong><?php echo $canViewStats ? number_format((float)($stats['average_score'] ?? 0), 1) . '%' : '—'; ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="ms-auto pb-2">
                        <?php if ($isOwnProfile): ?>
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="editBio()">
                                <i class="bi bi-pencil me-1"></i>Edytuj profil
                            </button>
                        <?php else: ?>
                            <?php $status = getFriendshipStatus($pdo, $myId, $viewId); ?>
                            
                            <?php 
                                $myRole = $_SESSION['role'] ?? 'user';
                                $canAddFriends = canSendFriendRequest($myRole, $role, $allowFriendRequests);
                            ?>
                            
                            <?php if ($status === 'none' && $canAddFriends): ?>
                                <form action="actions/send_friend_request.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="friend_id" value="<?php echo $viewId; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">
                                        <i class="bi bi-person-plus me-1"></i>Dodaj znajomego
                                    </button>
                                </form>
                            <?php elseif ($status === 'none' && !$canAddFriends): ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" disabled title="Nie można zaprosić tego użytkownika">
                                    <i class="bi bi-person-x me-1"></i>Niedostępne
                                </button>
                            <?php elseif ($status === 'sent'): ?>
                                <button class="btn btn-light btn-sm rounded-pill px-3 disabled">
                                    <i class="bi bi-clock me-1"></i>Zaproszenie wysłane
                                </button>
                            <?php elseif ($status === 'pending'): ?>
                                <form action="social.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="friend_id" value="<?php echo $viewId; ?>">
                                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">
                                        <i class="bi bi-check2 me-1"></i>Akceptuj zaproszenie
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="dropdown d-inline">
                                    <button class="btn btn-success btn-sm rounded-pill px-3 dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="bi bi-check2 me-1"></i>Znajomi
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <form action="social.php" method="POST" onsubmit="return appConfirmSubmit(this, 'Czy na pewno chcesz usunąć tego znajomego?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="friend_id" value="<?php echo $viewId; ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-person-x me-2"></i>Usuń ze znajomych
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                             <a href="duels/challenge.php?opponent=<?php echo $viewId; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3 ms-2">
                                 <i class="bi bi-fire me-1"></i>Pojedynek
                             </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$canViewProfile): ?>
                    <div class="alert alert-warning animate-in text-center p-5 shadow-sm border-0 mt-4" style="background-color: var(--bg-color); border-radius: 15px;">
                        <i class="bi bi-lock-fill text-muted mb-3" style="font-size: 3rem;"></i>
                        <h4 class="fw-bold">Profil prywatny</h4>
                        <p class="text-muted mb-0">Ten użytkownik ukrył swój profil przed innymi.</p>
                    </div>
                <?php else: ?>

                <div class="mt-4">
                    <h6 class="fw-bold small text-uppercase mb-2" style="color: var(--text-muted);">O mnie</h6>
                    <div id="bioDisplay">
                        <p class="mb-0" id="bioText" style="color: var(--text-main);"><?php echo htmlspecialchars($bio); ?></p>
                    </div>
                    <?php if ($isOwnProfile): ?>
                    <div id="bioEdit" style="display: none;">
                        <textarea class="form-control mb-1" id="bioInput" rows="3" maxlength="160" style="resize: none;" onkeyup="updateCharCount()"><?php echo htmlspecialchars($bio); ?></textarea>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><span id="charCount"><?php echo mb_strlen($bio); ?></span>/160 znaków</small>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary btn-sm px-3" onclick="saveBio()">Zapisz opis</button>
                                <button class="btn btn-light btn-sm px-3" onclick="cancelBio()">Anuluj</button>
                            </div>
                        </div>
                        <form action="actions/update_profile.php" method="POST" enctype="multipart/form-data" class="border rounded-3 p-3 mt-3 profile-edit-tools" style="display:none;">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="return_to" value="profile.php">
                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>">
                            <input type="hidden" name="class_year" value="<?php echo htmlspecialchars((string)($userData['class_year'] ?? '')); ?>">
                            <input type="hidden" name="class_suffix" value="<?php echo htmlspecialchars((string)($userData['class_suffix'] ?? '')); ?>">
                            <label class="form-label fw-semibold" for="profileAvatarInput">Zdjęcie profilowe</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <input type="file" name="avatar" id="profileAvatarInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" type="submit"><i class="bi bi-image me-1"></i>Zmień zdjęcie</button>
                            </div>
                            <div class="form-text">JPG, PNG, GIF albo WebP. Zapis automatycznie do WebP, maks. 2 MB.</div>
                        </form>
                        <?php if ($avatarSrc): ?>
                        <form action="actions/update_profile.php" method="POST" class="mt-2 profile-edit-tools" style="display:none;" onsubmit="return appConfirmSubmit(this, 'Usunąć zdjęcie profilowe?')">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="return_to" value="profile.php">
                            <input type="hidden" name="action" value="delete_avatar">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" type="submit"><i class="bi bi-trash3 me-1"></i>Usuń zdjęcie</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($hasProfessionalData || $isOwnProfile): ?>
        <div class="dashboard-panel mb-4 professional-profile-panel" <?php echo (!$hasProfessionalData && $isOwnProfile) ? 'style="display:none;"' : ''; ?>>
            <div class="panel-header mb-3 d-flex justify-content-between align-items-center">
                <h4 class="panel-title mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Profil zawodowy</h4>
                <?php if ($isOwnProfile): ?><span class="badge bg-primary bg-opacity-10 text-primary profile-edit-tools" style="display:none;">Edycja aktywna</span><?php endif; ?>
            </div>

            <div class="accordion" id="professionalAccordion">
                <?php if (!empty($profileSections['education']) || $isOwnProfile): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingEducation">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEducation" aria-expanded="false" aria-controls="collapseEducation">
                            <i class="bi bi-mortarboard me-2"></i>Wykształcenie
                        </button>
                    </h2>
                    <div id="collapseEducation" class="accordion-collapse collapse show" aria-labelledby="headingEducation" data-bs-parent="#professionalAccordion">
                        <div class="accordion-body">
                            <?php foreach ($profileSections['education'] as $item): ?>
                                <div class="professional-item border rounded p-3 mb-2">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary"><i class="bi bi-mortarboard-fill"></i></span>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($item['school_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($item['level']); ?><?php echo $item['field'] ? ' • ' . htmlspecialchars($item['field']) : ''; ?> • <?php echo (int)$item['start_year']; ?>-<?php echo $item['end_year'] ? (int)$item['end_year'] : 'obecnie'; ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($isOwnProfile): ?>
                            <form action="actions/profile_section.php" method="POST" class="row g-2 mt-2 profile-edit-tools" style="display:none;">
                                <?php echo csrfTokenField(); ?><input type="hidden" name="type" value="education">
                                <div class="col-md-4"><select name="level" class="form-select form-select-sm"><option>podstawowe</option><option>średnie</option><option>wyższe</option></select></div>
                                <div class="col-md-8"><input name="school_name" class="form-control form-control-sm" placeholder="Szkoła / uczelnia" required></div>
                                <div class="col-md-6"><input name="field" class="form-control form-control-sm" placeholder="Kierunek"></div>
                                <div class="col-md-3"><input name="start_year" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" class="form-control form-control-sm" placeholder="Od" required></div>
                                <div class="col-md-3"><input name="end_year" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" class="form-control form-control-sm" placeholder="Do"></div>
                                <div class="col-12"><button class="btn btn-sm btn-primary">Dodaj</button></div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($profileSections['social_links']) || !empty($profileSections['languages']) || $isOwnProfile): ?>
                <?php $socialSectionEmpty = empty($profileSections['social_links']) && empty($profileSections['languages']); ?>
                <div class="accordion-item <?php echo $socialSectionEmpty ? 'professional-empty-section' : ''; ?>" <?php echo ($socialSectionEmpty && $isOwnProfile) ? 'style="display:none;"' : ''; ?>>
                    <h2 class="accordion-header" id="headingSocial">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSocial" aria-expanded="false" aria-controls="collapseSocial">
                            <i class="bi bi-share-fill me-2"></i>Linki społecznościowe i języki
                        </button>
                    </h2>
                    <div id="collapseSocial" class="accordion-collapse collapse" aria-labelledby="headingSocial" data-bs-parent="#professionalAccordion">
                        <div class="accordion-body">
                            <div class="mb-3">
                                <h6 class="fw-bold">Linki społecznościowe</h6>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach ($profileSections['social_links'] as $link): ?>
                                        <?php
                                            $platformMeta = $socialPlatforms[$link['platform']] ?? [ucfirst((string)$link['platform']), 'bi-link-45deg'];
                                            [$platformLabel, $icon] = $platformMeta;
                                        ?>
                                        <a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer"><i class="bi <?php echo htmlspecialchars($icon); ?> me-1"></i><?php echo htmlspecialchars($platformLabel); ?></a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($isOwnProfile): ?>
                                <form action="actions/profile_section.php" method="POST" class="row g-2 profile-edit-tools" style="display:none;">
                                    <?php echo csrfTokenField(); ?><input type="hidden" name="type" value="social">
                                    <div class="col-md-4">
                                        <select name="platform" class="form-select form-select-sm" required>
                                            <?php foreach ($socialPlatforms as $value => [$label, $iconName]): ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-8"><input name="url" type="url" class="form-control form-control-sm" placeholder="https://..." required></div>
                                    <div class="col-12"><button class="btn btn-sm btn-primary">Zapisz link</button></div>
                                </form>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h6 class="fw-bold">Języki</h6>
                                <div class="mb-3">
                                    <?php foreach ($profileSections['languages'] as $item): ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars($item['language_name']); ?> • <?php echo htmlspecialchars($item['level']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($isOwnProfile): ?>
                                <form action="actions/profile_section.php" method="POST" class="row g-2 mt-2 profile-edit-tools" style="display:none;">
                                    <?php echo csrfTokenField(); ?><input type="hidden" name="type" value="language">
                                    <div class="col-md-6">
                                        <select name="language_name" class="form-select form-select-sm" required>
                                            <?php foreach ($languagePresets as $languageName): ?>
                                                <option value="<?php echo htmlspecialchars($languageName); ?>"><?php echo htmlspecialchars($languageName); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4"><select name="level" class="form-select form-select-sm"><option>podstawowy</option><option>średni</option><option>zaawansowany</option><option>biegły</option></select></div>
                                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">+</button></div>
                                    <div class="col-12"><small class="text-muted">Maksymalnie 7 języków w profilu.</small></div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="accordion mt-3" id="professionalAccordionExtra">
                <?php
                $simpleSections = [
                    'certificates' => ['Certyfikaty', 'bi-patch-check'],
                    'courses' => ['Kursy', 'bi-journal-bookmark'],
                    'volunteering' => ['Wolontariat', 'bi-heart'],
                    'organizations' => ['Organizacje', 'bi-building'],
                ];
                $otherSectionsEmpty = true;
                foreach (array_keys($simpleSections) as $sectionKey) {
                    if (!empty($profileSections[$sectionKey])) {
                        $otherSectionsEmpty = false;
                        break;
                    }
                }
                ?>
                <div class="accordion-item <?php echo $otherSectionsEmpty ? 'professional-empty-section' : ''; ?>" <?php echo ($otherSectionsEmpty && $isOwnProfile) ? 'style="display:none;"' : ''; ?>>
                    <h2 class="accordion-header" id="headingOtherSections">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOtherSections" aria-expanded="false" aria-controls="collapseOtherSections">
                            <i class="bi bi-briefcase-fill me-2"></i>Inne sekcje zawodowe
                        </button>
                    </h2>
                    <div id="collapseOtherSections" class="accordion-collapse collapse" aria-labelledby="headingOtherSections" data-bs-parent="#professionalAccordionExtra">
                        <div class="accordion-body">
                            <div class="row g-4">
                                <?php foreach ($simpleSections as $key => [$label, $icon]): ?>
                                    <?php if (empty($profileSections[$key]) && !$isOwnProfile) continue; ?>
                                    <div class="col-md-6 col-xl-3 professional-section <?php echo empty($profileSections[$key]) ? 'is-empty' : ''; ?>">
                                        <h6 class="fw-bold"><i class="bi <?php echo $icon; ?> me-1"></i><?php echo $label; ?></h6>
                                        <?php foreach ($profileSections[$key] as $item): ?>
                                            <div class="professional-item small border rounded p-2 mb-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['name'] ?? $item['organization'] ?? ''); ?></div>
                                                <div class="text-muted"><?php echo htmlspecialchars($item['organization'] ?? $item['provider'] ?? $item['role_name'] ?? ''); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($isOwnProfile): ?>
                                        <form action="actions/profile_section.php" method="POST" class="vstack gap-1 profile-edit-tools" style="display:none;">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="type" value="<?php echo $key === 'certificates' ? 'certificate' : ($key === 'courses' ? 'course' : ($key === 'volunteering' ? 'volunteering' : 'organization')); ?>">
                                            <?php if ($key === 'certificates'): ?>
                                                <input name="name" class="form-control form-control-sm" placeholder="Nazwa certyfikatu" required>
                                                <input name="organization" class="form-control form-control-sm" placeholder="Organizacja" required>
                                                <input name="obtained_date" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control form-control-sm" placeholder="RRRRMMDD">
                                            <?php elseif ($key === 'courses'): ?>
                                                <input name="name" class="form-control form-control-sm" placeholder="Nazwa kursu" required>
                                                <input name="provider" class="form-control form-control-sm" placeholder="Platforma / organizacja" required>
                                                <input name="completed_date" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control form-control-sm" placeholder="RRRRMMDD">
                                            <?php elseif ($key === 'volunteering'): ?>
                                                <input name="organization" class="form-control form-control-sm" placeholder="Organizacja" required>
                                                <input name="role_name" class="form-control form-control-sm" placeholder="Rola" required>
                                                <input name="start_date" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control form-control-sm" placeholder="Od RRRRMMDD">
                                                <input name="end_date" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control form-control-sm" placeholder="Do RRRRMMDD">
                                            <?php else: ?>
                                                <input name="name" class="form-control form-control-sm" placeholder="Organizacja" required>
                                                <input name="role_name" class="form-control form-control-sm" placeholder="Funkcja">
                                                <input name="start_date" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control form-control-sm" placeholder="Od RRRRMMDD">
                                                <input name="end_date" type="text" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" class="form-control form-control-sm" placeholder="Do RRRRMMDD">
                                            <?php endif; ?>
                                            <textarea name="description" class="form-control form-control-sm" rows="2" maxlength="500" style="resize:none;" placeholder="Opis"></textarea>
                                            <button class="btn btn-sm btn-outline-primary">Dodaj</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
            
            <?php if (!$canViewStats): ?>
                <div class="dashboard-panel text-center py-5 mb-4">
                    <i class="bi bi-lock-fill display-1 text-muted mb-3 d-block opacity-50"></i>
                    <h4 class="fw-bold text-muted mb-2">Prywatne statystyki</h4>
                    <p class="text-muted">Statystyki tego użytkownika są ukryte.</p>
                </div>
            <?php else: ?>
                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center p-3">
                                <div class="stats-icon text-primary mb-2">
                                    <i class="bi bi-file-text"></i>
                                </div>
                                <h4 class="fw-bold mb-0"><?php echo $stats['total_tests']; ?></h4>
                                <p class="text-muted small mb-0">Testy</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center p-3">
                                <div class="stats-icon text-success mb-2">
                                    <i class="bi bi-trophy"></i>
                                </div>
                                <h4 class="fw-bold mb-0"><?php echo $stats['average_score']; ?>%</h4>
                                <p class="text-muted small mb-0">Wynik</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center p-3">
                                <div class="stats-icon text-warning mb-2">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <h4 class="fw-bold mb-0"><?php echo floor($stats['total_time_spent'] / 60); ?>m</h4>
                                <p class="text-muted small mb-0">Czas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center p-3">
                                <div class="stats-icon text-info mb-2">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h4 class="fw-bold mb-0"><?php echo $stats['mastered_questions']; ?></h4>
                                <p class="text-muted small mb-0">Opanowane</p>
                            </div>
                        </div>
                    </div>
                </div>

        <!-- Chart Section -->
        <?php if (!empty($chartData)): ?>
        <div class="dashboard-panel mb-4">
            <div class="panel-header mb-3">
                <h4 class="panel-title mb-0"><i class="bi bi-line-chart me-2 text-primary"></i>Postępy w czasie</h4>
            </div>
            <canvas id="progressChart" height="100"></canvas>
        </div>
        <?php endif; ?>

        <!-- Best/Worst Score Highlights -->
        <div class="row g-3 mb-4">
            <?php if ($bestScore): ?>
            <div class="col-md-6">
                <div class="highlight-card">
                    <h5><i class="bi bi-trophy-fill"></i> Najlepszy wynik</h5>
                    <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <div class="score-val"><?php echo round($bestScore['score_percent']); ?>%</div>
                            <div class="score-meta mb-2">
                                <?php echo $bestScore['correct_answers'] ?? $bestScore['correct_count'] ?? 0; ?>/<?php echo $bestScore['total_questions']; ?> poprawnych
                            </div>
                            <div class="text-white opacity-75 smaller" style="font-size: 0.75rem;">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo date('d.m.Y H:i', strtotime($bestScore['test_date'] ?? $bestScore['completed_at'] ?? 'now')); ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="badge-mode">
                                <?php echo $bestScore['mode'] ?? $bestScore['test_mode'] ?? 'exam'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($worstScore): ?>
            <div class="col-md-6">
                <div class="highlight-card worst">
                    <h5><i class="bi bi-exclamation-triangle-fill"></i> Najgorszy wynik</h5>
                    <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <div class="score-val"><?php echo round($worstScore['score_percent']); ?>%</div>
                            <div class="score-meta mb-2">
                                <?php echo $worstScore['correct_answers'] ?? $worstScore['correct_count'] ?? 0; ?>/<?php echo $worstScore['total_questions']; ?> poprawnych
                            </div>
                            <div class="text-white opacity-75 smaller" style="font-size: 0.75rem;">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?php echo date('d.m.Y H:i', strtotime($worstScore['test_date'] ?? $worstScore['completed_at'] ?? 'now')); ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="badge-mode">
                                <?php echo $worstScore['mode'] ?? $worstScore['test_mode'] ?? 'exam'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Test Results Table -->
        <div class="dashboard-panel mb-4 overflow-hidden border-0 shadow-sm">
            <div class="panel-header mb-0 d-flex justify-content-between align-items-center p-4" style="background: rgba(255,255,255,0.5); border-bottom: 1px solid var(--border-color);">
                <h4 class="panel-title mb-0 fw-bold"><i class="bi bi-list-stars me-2 text-primary"></i>Historia testów</h4>
                <span class="profile-badge">Ostatnie 10 wyników</span>
            </div>
            <?php if (!empty($tableResults)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-muted smaller text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <th class="ps-4">Data</th>
                            <th>Tryb</th>
                            <th>Czas</th>
                            <th>Pytania</th>
                            <th class="text-center">Odpowiedzi</th>
                            <th class="text-center">Wynik %</th>
                            <th class="text-end pe-4">Akcje</th>
                        </tr>
                    </thead>
                    <tbody style="border-top: none;">
                        <?php foreach ($tableResults as $result): ?>
                        <?php
                            $resultKind = $result['kind'] ?? 'test';
                            $resultLabel = $resultKind === 'test'
                                ? ($result['label'] ?? $result['mode'] ?? $result['test_mode'] ?? 'exam')
                                : ($result['label'] ?? $resultKind);
                            $modeLabels = [
                                'exam' => ['name' => 'Egzaminacyjny', 'color' => 'indigo'],
                                'practice' => ['name' => 'Ćwiczenia', 'color' => 'emerald'],
                                'single' => ['name' => 'Pojedyncze', 'color' => 'sky'],
                                'duel' => ['name' => $resultLabel, 'color' => 'rose'],
                                'exam_session' => ['name' => $resultLabel, 'color' => 'amber']
                            ];
                            $resultMode = $resultKind === 'test' ? $resultLabel : $resultKind;
                            $modeInfo = $modeLabels[$resultMode] ?? ['name' => ucfirst($resultMode), 'color' => 'slate'];
                            
                            // Map generic Bootstrap colors to our custom palette
                            $colorMap = [
                                'primary' => '#6366f1',
                                'success' => '#10b981',
                                'info' => '#0ea5e9',
                                'indigo' => '#6366f1',
                                'emerald' => '#10b981',
                                'sky' => '#0ea5e9',
                                'rose' => '#e11d48',
                                'amber' => '#d97706',
                                'slate' => '#64748b'
                            ];
                            $actualColor = $colorMap[$modeInfo['color']] ?? '#64748b';

                            $timeSpent = (int)($result['time_spent'] ?? 0);
                            $timeDisplay = $timeSpent >= 3600 
                                ? sprintf('%02d:%02d:%02d', floor($timeSpent/3600), floor(($timeSpent%3600)/60), $timeSpent%60)
                                : sprintf('%02d:%02d', floor($timeSpent/60), $timeSpent%60);
                        ?>
                        <tr style="transition: all 0.2s;">
                            <td class="ps-4">
                                <?php $dateStr = $result['test_date'] ?? $result['completed_at'] ?? $result['date'] ?? 'now'; ?>
                                <div class="fw-bold text-dark"><?php echo date('d.m.Y', strtotime($dateStr)); ?></div>
                                <div class="text-muted smaller"><?php echo date('H:i', strtotime($dateStr)); ?></div>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background: <?php echo $actualColor; ?>20; color: <?php echo $actualColor; ?>; border: 1px solid <?php echo $actualColor; ?>30;">
                                    <?php echo htmlspecialchars($modeInfo['name']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 text-muted">
                                    <i class="bi bi-clock-history opacity-75"></i>
                                    <span class="fw-medium"><?php echo $timeDisplay; ?></span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-dark opacity-75"><?php echo $result['total_questions']; ?></span>
                            </td>
                            <?php $correctCount = $result['correct_answers'] ?? $result['correct_count'] ?? null; ?>
                            <td class="text-center">
                                <?php if ($correctCount === null): ?>
                                    <span class="text-muted small"><?php echo $resultKind === 'duel' ? 'pojedynek' : 'niedostępne'; ?></span>
                                <?php else: ?>
                                <div class="d-inline-flex align-items-center gap-1">
                                    <span class="text-success fw-bold"><?php echo $correctCount; ?></span>
                                    <span class="text-muted opacity-50">/</span>
                                    <span class="text-danger fw-medium"><?php echo $result['total_questions'] - $correctCount; ?></span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                    $percent = (float)($result['score_percent'] ?? 0);
                                    $scoreColor = $percent >= 80 ? '#10b981' : ($percent >= 50 ? '#f59e0b' : '#ef4444');
                                ?>
                                <div class="fw-black fs-5" style="color: <?php echo $scoreColor; ?>; letter-spacing: 0;">
                                    <?php echo round($percent, 1); ?>%
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($resultKind === 'test'): ?>
                                    <button class="btn btn-sm view-details-btn d-inline-flex align-items-center gap-2 px-3 py-2"
                                            style="border-radius: 12px; border: 1px solid #6366f1; background: #6366f110; color: #6366f1; transition: all 0.2s;"
                                            data-test-id="<?php echo (int)$result['id']; ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#detailsModal">
                                        <i class="bi bi-eye-fill"></i>
                                        <span class="fw-bold">Szczegóły</span>
                                    </button>
                                    <?php if ($isOwnProfile): ?>
                                        <form method="POST" action="actions/delete_test_result.php" class="d-inline-block ms-1" onsubmit="return confirm('Usunąć ten wynik z historii?');">
                                            <?php echo csrfTokenField('delete_test_result'); ?>
                                            <input type="hidden" name="result_id" value="<?php echo (int)$result['id']; ?>">
                                            <input type="hidden" name="return_to" value="../profile.php?id=<?php echo (int)$userId; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Usuń wynik" style="border-radius: 12px;"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a class="btn btn-sm view-details-btn d-inline-flex align-items-center gap-2 px-3 py-2"
                                       style="border-radius: 12px; border: 1px solid #6366f1; background: #6366f110; color: #6366f1; transition: all 0.2s;"
                                       href="<?php echo htmlspecialchars($result['url'] ?? 'history.php'); ?>">
                                        <i class="bi bi-eye-fill"></i>
                                        <span class="fw-bold">Szczegóły</span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-inbox-fill text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">Brak historii testów. Rozpocznij pierwszy test!</p>
                <a href="test.php?setup=1&new=1" class="btn btn-primary">
                    <i class="bi bi-pencil-square"></i> Rozpocznij test
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php endif; // End of privacy check ?>

            </div> <!-- /col-lg-8 -->

            <div class="col-lg-4">
                <!-- Search Users -->
                <div class="dashboard-panel mb-4 animate-in" style="animation-delay: 0.1s;">
                    <div class="panel-header mb-3">
                        <h5 class="panel-title mb-0"><i class="bi bi-person-search me-2 text-primary"></i>Szukaj użytkowników</h5>
                    </div>
                    <form action="search_users.php" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="query" class="form-control" placeholder="Nazwa użytkownika...">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Friends Section -->
                <div class="dashboard-panel mb-4 animate-in" style="animation-delay: 0.2s;">
                    <div class="panel-header mb-3 d-flex justify-content-between align-items-center">
                        <h5 class="panel-title mb-0"><i class="bi bi-people me-2 text-success"></i>Znajomi</h5>
                        <?php if ($pendingRequests > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?php echo $pendingRequests; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="friends-list">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT u.id, u.username, u.last_activity, f.status 
                            FROM users u
                            JOIN friends f ON (u.id = f.user_id OR u.id = f.friend_id)
                            WHERE (f.user_id = ? OR f.friend_id = ?) 
                            AND u.id != ?
                            AND f.status = 'accepted'
                            LIMIT 5
                        ");
                        $stmt->execute([$userId, $userId, $userId]);
                        $friends = $stmt->fetchAll();
                        
                        if ($friends):
                            foreach ($friends as $friend): 
                                $isOnline = isUserOnline($friend['last_activity']);
                            ?>
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="user-avatar-small text-primary fw-bold" style="width: 35px; height: 35px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background-color: var(--bg-color);">
                                    <?php echo strtoupper(substr($friend['username'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold small">
                                        <a href="profile.php?id=<?php echo $friend['id']; ?>" class="text-decoration-none" style="color: var(--text-main);">
                                            <?php echo htmlspecialchars($friend['username']); ?>
                                        </a>
                                    </div>
                                    <div class="<?php echo $isOnline ? 'text-success' : 'text-muted'; ?> small" style="font-size: 0.7rem;">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                        <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                                    </div>
                                </div>
                                <a href="social.php" class="btn btn-link btn-sm text-muted p-0"><i class="bi bi-chat-dots"></i></a>
                            </div>
                            <?php endforeach;
                        else: ?>
                            <p class="text-muted small text-center py-3">Nie masz jeszcze znajomych.</p>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    <a href="social.php" class="btn btn-light btn-sm w-100 text-primary fw-bold">
                        Zobacz wszystkich znajomych
                    </a>
                </div>

                <!-- Recent Activity or Missions Preview -->
                <div class="dashboard-panel animate-in" style="animation-delay: 0.3s;">
                    <div class="panel-header mb-3">
                        <h5 class="panel-title mb-0"><i class="bi bi-lightning-charge me-2 text-warning"></i>Dzisiejsze cele</h5>
                    </div>
                    
                    <?php foreach ($currentMissions as $m): ?>
                    <?php 
                        $key = $m['mission_type'];
                        $config = $missionPool[$key];
                        $percent = min(100, round(($m['current_value'] / $m['target_value']) * 100));
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span><?php echo htmlspecialchars(str_replace('{target}', (string)$m['target_value'], $config['title'] ?? $m['mission_description'])); ?></span>
                            <span class="fw-bold"><?php echo $percent; ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-<?php echo $config['color']; ?>" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                        <div class="small text-muted mt-1"><?php echo round($m['current_value'], 1); ?>/<?php echo $m['target_value']; ?></div>
                    </div>
                    <?php endforeach; ?>
                    
                    <a href="goals.php" class="btn btn-outline-warning btn-sm w-100">Wszystkie misje</a>
                </div>
            </div> <!-- /col-lg-4 -->
        </div> <!-- /row -->

        <?php if ($allowProfileComments || $isOwnProfile || roleHasAdminAccess($myRole)): ?>
        <div class="dashboard-panel mb-4 profile-comments-panel">
            <div class="panel-header mb-3">
                <h4 class="panel-title mb-0"><i class="bi bi-chat-left-text me-2 text-primary"></i>Komentarze</h4>
            </div>
            <?php if (!$allowProfileComments && ($isOwnProfile || roleHasAdminAccess($myRole))): ?>
                <div class="alert alert-warning border-0 small">Komentarze pod tym profilem są wyłączone.</div>
            <?php endif; ?>
            <?php if ($allowProfileComments): ?>
            <form action="actions/profile_comment.php" method="POST" class="mb-4 comment-form-card">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="profile_user_id" value="<?php echo $userId; ?>">
                <label class="form-label fw-bold" for="profileCommentText">Dodaj komentarz</label>
                <textarea name="comment_text" id="profileCommentText" class="form-control mb-2" maxlength="100" rows="3" style="resize:none;" placeholder="Napisz krótki komentarz..." required aria-describedby="commentCharHelp"></textarea>
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <small class="text-muted" id="commentCharHelp"><span class="comment-counter" id="commentCharCount">0</span>/100 znaków • maks. 20 komentarzy na profil</small>
                    <button class="btn btn-sm btn-primary rounded-pill px-3">Dodaj komentarz</button>
                </div>
            </form>
            <?php endif; ?>
            <?php foreach ($profileSections['comments'] as $comment): ?>
                <article class="comment-card mb-3">
                    <div class="comment-card-grid">
                        <div class="comment-avatar" aria-hidden="true">
                            <?php $commentAvatar = userAvatarSrc($comment['avatar_path'] ?? ''); ?>
                            <?php if ($commentAvatar): ?>
                                <img src="<?php echo htmlspecialchars($commentAvatar); ?>" alt="" class="comment-avatar-img" loading="lazy" decoding="async">
                            <?php else: ?>
                                <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <a href="profile.php?id=<?php echo (int)$comment['author_id']; ?>" class="comment-author-link"><?php echo htmlspecialchars($comment['username']); ?><?php echo getUserBadgeHtml($comment['role'] ?? 'user', (int)($comment['is_verified'] ?? 0)); ?></a>
                                <span class="small text-muted comment-meta"><i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-bubble">
                                <?php echo htmlspecialchars($comment['comment_text']); ?>
                            </div>
                        </div>
                        <?php if ((int)$comment['author_id'] === $myId || roleHasAdminAccess($myRole)): ?>
                        <form action="actions/profile_comment.php" method="POST" class="comment-delete-form">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="comment_action" value="delete">
                            <input type="hidden" name="profile_user_id" value="<?php echo $userId; ?>">
                            <input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                <i class="bi bi-trash"></i> Usuń
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; // End $canViewProfile ?>
    </div>

    <!-- Password Change Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key-fill"></i> Zmiana hasła</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actions/change_password.php" method="POST">
                    <div class="modal-body">
                        <?php echo csrfTokenField(); ?>
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                        <input type="hidden" name="return_to" value="profile.php">
                        
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Aktualne hasło</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Nowe hasło</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" 
                                   pattern=".{6,}" title="Hasło musi mieć co najmniej 6 znaków" required>
                            <div class="form-text">Minimum 6 znaków, mała i wielka litera, cyfra oraz znak specjalny.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Potwierdź nowe hasło</label>
                            <input type="password" class="form-control" id="confirmPassword" 
                                   name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Zapisz hasło
                        </button>
                    </div>
                </form>
            </div>
        </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    
    <!-- Chart.js -->
    <?php if (!empty($chartData)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js" integrity="sha384-zYPBGXwO4633CABX/5Spf6emCKUJCfoOkhOMYyxMsatqQZPnDblmmOewfjsIVWCM" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('progressChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Wynik (%)',
                        data: <?php echo json_encode($chartData); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.1,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgb(75, 192, 192)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Wynik (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>

    <!-- Test Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard-data"></i> Szczegóły testu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content loaded dynamically via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Ładowanie...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const profileCsrfToken = <?php echo json_encode(generateCsrfToken(), JSON_UNESCAPED_SLASHES); ?>;

        function updateCharCount() {
            const input = document.getElementById('bioInput');
            const count = document.getElementById('charCount');
            count.innerText = input.value.length;
        }

        function editBio() {
            document.getElementById('bioDisplay').style.display = 'none';
            document.getElementById('bioEdit').style.display = 'block';
            document.querySelectorAll('.professional-profile-panel').forEach(el => el.style.display = '');
            document.querySelectorAll('.professional-empty-section').forEach(el => {
                el.style.display = '';
                el.classList.add('editing');
            });
            document.querySelectorAll('.professional-section').forEach(el => el.classList.add('editing'));
            document.querySelectorAll('.profile-edit-tools').forEach(el => el.style.display = '');
            document.getElementById('bioInput').focus();
        }
        
        function cancelBio() {
            document.getElementById('bioDisplay').style.display = 'block';
            document.getElementById('bioEdit').style.display = 'none';
            document.querySelectorAll('.profile-edit-tools').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.professional-section').forEach(el => el.classList.remove('editing'));
            document.querySelectorAll('.professional-empty-section').forEach(el => {
                el.classList.remove('editing');
                el.style.display = 'none';
            });
            <?php if (!$hasProfessionalData && $isOwnProfile): ?>
            document.querySelectorAll('.professional-profile-panel').forEach(el => el.style.display = 'none');
            <?php endif; ?>
        }
        
        function saveBio() {
            const bio = document.getElementById('bioInput').value;
            fetch('ajax/update_bio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'bio=' + encodeURIComponent(bio) + '&csrf_token=' + encodeURIComponent(profileCsrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('bioText').innerText = bio;
                    cancelBio();
                } else {
                    appNotice('Błąd: ' + (data.message || 'Nie udało się zapisać opisu.'), 'danger');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const detailButtons = document.querySelectorAll('.view-details-btn');
            const modalBody = document.getElementById('modalBody');
            const commentInput = document.getElementById('profileCommentText');
            const commentCount = document.getElementById('commentCharCount');
            if (commentInput && commentCount) {
                const syncCommentCount = () => {
                    commentCount.textContent = commentInput.value.length;
                };
                commentInput.addEventListener('input', syncCommentCount);
                syncCommentCount();
            }
            
            detailButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const testId = this.getAttribute('data-test-id');
                    modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div>';
                    
                    fetch('ajax/get_test_details.php?id=' + testId)
                        .then(response => response.text())
                        .then(html => {
                            modalBody.innerHTML = html;
                        })
                        .catch(error => {
                            modalBody.innerHTML = '<div class="alert alert-danger">Nie udało się załadować szczegółów testu.</div>';
                            console.error('Error loading test details:', error);
                        });
                });
            });
        });
    </script>
</body>
</html>
