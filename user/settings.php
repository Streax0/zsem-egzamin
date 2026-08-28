<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Get current settings from cookies for UI state
$currentFontSize = $_COOKIE['user_font_size'] ?? '16';
$currentTheme = $_COOKIE['user_theme'] ?? 'light';
$currentDensity = $_COOKIE['user_density'] ?? 'comfortable';
$currentAccent = $_COOKIE['user_accent'] ?? 'var(--primary-color)';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $currentAccent)) {
    $currentAccent = '#3b82f6';
}
$reduceMotion = ($_COOKIE['reduce_motion'] ?? '0') === '1';
$dashboardView = $_COOKIE['dashboard_view'] ?? 'balanced';
$defaultTestMode = $_COOKIE['default_test_mode'] ?? 'exam';
$welcomeBannerStyle = $_COOKIE['welcome_banner_style'] ?? 'gradient';
$openExternalNewTab = ($_COOKIE['external_new_tab'] ?? '1') === '1';
$activeAppStatuses = getAppStatuses($pdo, true, 2);

$flashMsg = getSessionMessage();

// Fetch account and privacy settings
$stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, first_name, last_name, class, class_year, class_suffix, bio, avatar_path, avatar_changed_at, xp, profile_public, stats_public, allow_profile_comments, allow_friend_requests, searchable, is_verified, verified_at, verified_by_admin_id, ranking_visible, verification_token, is_banned, ban_expires_at, trust_status, risk_flags, registration_ip, created_at, last_login, last_login_ip, last_activity, session_version FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $userSettings['username'] ?? ($_SESSION['username'] ?? '');
$email = $userSettings['email'] ?? '';
$firstName = $userSettings['first_name'] ?? '';
$lastName = $userSettings['last_name'] ?? '';
$classYear = $userSettings['class_year'] ?? '';
$classSuffix = $userSettings['class_suffix'] ?? '';
$settingsAvatarSrc = userAvatarSrc($userSettings['avatar_path'] ?? '');
$profilePublic = (bool)($userSettings['profile_public'] ?? 1);
$statsPublic = (bool)($userSettings['stats_public'] ?? 1);
$allowFriendRequests = (bool)($userSettings['allow_friend_requests'] ?? 1);
$searchable = (bool)($userSettings['searchable'] ?? 1);
$allowProfileComments = (bool)($userSettings['allow_profile_comments'] ?? 1);
$showMissions = (bool)($userSettings['show_missions'] ?? 1);
$showOnlineStatus = (bool)($userSettings['show_online_status'] ?? 1);
$showRecentActivity = (bool)($userSettings['show_recent_activity'] ?? 1);
$rankingVisible = (bool)($userSettings['ranking_visible'] ?? ($role !== 'teacher'));
$canUseMfa = mfaRoleCanUse($role);
$mfaEnabled = false;
try {
    ensurePlatformEnhancements($pdo);
    $mfaStmt = $pdo->prepare("SELECT enabled_at FROM user_mfa WHERE user_id = ? LIMIT 1");
    $mfaStmt->execute([$userId]);
    $mfaEnabled = (bool)$mfaStmt->fetchColumn();
} catch (PDOException $e) {
    $mfaEnabled = false;
}

$deviceSessionManager = new \App\Core\DeviceSessionManager($pdo);
$activeUserSessions = $deviceSessionManager->getUserSessions((int)$userId, function_exists('currentSessionHash') ? currentSessionHash() : '');

$settingsHealth = [
    ['key' => 'profile', 'icon' => 'bi-person-check', 'label' => 'Profil', 'value' => ($username !== '' && $email !== '') ? 'OK' : 'Uzupełnij'],
    ['key' => 'security', 'icon' => 'bi-shield-lock', 'label' => 'Bezpieczeństwo', 'value' => $mfaEnabled ? 'MFA aktywne' : ($canUseMfa ? 'MFA opcjonalne' : 'Hasło')],
    ['key' => 'theme', 'icon' => 'bi-palette', 'label' => 'Motyw', 'value' => $currentTheme === 'dark' ? 'Ciemny' : 'Jasny'],
    ['key' => 'density', 'icon' => 'bi-sliders', 'label' => 'Interfejs', 'value' => $currentDensity === 'compact' ? 'Kompakt' : 'Wygodny'],
];
?>
<?php
$pageTitle = 'Ustawienia - System Testów';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        /* Glassmorphism settings panel styling */
        body.dark-mode {
            background: radial-gradient(circle at 50% 0%, #0a0f1d 0%, #070a13 100%) !important;
            color: #f8fafc !important;
        }
        body.light-mode {
            background: radial-gradient(circle at 50% 0%, #f8fafc 0%, #f1f5f9 100%) !important;
            color: #0f172a !important;
        }
        .dashboard-layout {
            background-color: transparent !important;
        }
        
        /* Dark Mode Dashboard Panel */
        body.dark-mode .dashboard-panel {
            background: rgba(15, 23, 42, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(20px) saturate(1.4) !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3) !important;
            color: #f8fafc !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            min-width: 0;
        }
        body.dark-mode .dashboard-panel:hover {
            transform: translateY(-2px) !important;
            border-color: color-mix(in srgb, var(--primary-color) 25%, transparent) !important;
            box-shadow: 0 20px 40px color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
        }
        body.dark-mode .panel-title {
            color: #f8fafc !important;
            font-weight: 800 !important;
        }
        body.dark-mode .form-label {
            color: #cbd5e1 !important;
            font-weight: 600 !important;
        }
        body.dark-mode .form-control, 
        body.dark-mode .form-select {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            color: #fff !important;
            border-radius: 10px !important;
            transition: all 0.3s !important;
        }
        body.dark-mode .form-control:focus, 
        body.dark-mode .form-select:focus {
            background: rgba(15, 23, 42, 0.8) !important;
            border-color: var(--primary-color, #6366f1) !important;
            box-shadow: 0 0 15px var(--primary-color) !important;
            color: #fff !important;
        }
        body.dark-mode .form-control::placeholder {
            color: rgba(255, 255, 255, 0.3) !important;
        }
        body.dark-mode .form-text {
            color: #94a3b8 !important;
        }
        body.dark-mode .form-check-input {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
        }

        /* Light Mode Dashboard Panel */
        body.light-mode .dashboard-panel {
            background: rgba(255, 255, 255, 0.8) !important;
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            backdrop-filter: blur(20px) saturate(1.4) !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.06) !important;
            color: #0f172a !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            min-width: 0;
        }
        body.light-mode .dashboard-panel:hover {
            transform: translateY(-2px) !important;
            border-color: color-mix(in srgb, var(--primary-color) 20%, transparent) !important;
            box-shadow: 0 20px 40px color-mix(in srgb, var(--primary-color) 6%, transparent) !important;
        }
        body.light-mode .panel-title {
            color: #0f172a !important;
            font-weight: 800 !important;
        }
        body.light-mode .form-label {
            color: #475569 !important;
            font-weight: 600 !important;
        }
        body.light-mode .form-control, 
        body.light-mode .form-select {
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid rgba(15, 23, 42, 0.15) !important;
            color: #0f172a !important;
            border-radius: 10px !important;
            transition: all 0.3s !important;
        }
        body.light-mode .form-control:focus, 
        body.light-mode .form-select:focus {
            background: #ffffff !important;
            border-color: var(--primary-color, #3b82f6) !important;
            box-shadow: 0 0 15px var(--primary-color) !important;
            color: #0f172a !important;
        }
        body.light-mode .form-control::placeholder {
            color: rgba(15, 23, 42, 0.4) !important;
        }
        body.light-mode .form-text {
            color: #64748b !important;
        }
        body.light-mode .text-muted {
            color: #64748b !important;
        }
        body.light-mode .form-check-input {
            background-color: rgba(15, 23, 42, 0.08) !important;
            border-color: rgba(15, 23, 42, 0.2) !important;
        }

        /* Common interactive glows */
        .form-check-input:checked {
            background-color: var(--primary-color, #6366f1) !important;
            border-color: var(--primary-color, #6366f1) !important;
            box-shadow: 0 0 12px var(--primary-color) !important;
        }

        /* Overview grid & cards styling */
        .settings-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: .85rem;
        }
        
        body.dark-mode .settings-overview-card {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
            border-radius: 12px !important;
            padding: .85rem !important;
            min-height: 78px;
            transition: all 0.3s !important;
        }
        body.dark-mode .settings-overview-card:hover {
            transform: translateY(-2px) !important;
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(99, 102, 241, 0.25) !important;
        }
        body.dark-mode .settings-overview-card i {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: rgba(99, 102, 241, 0.15) !important;
            color: #a5b4fc !important;
            font-size: 1.1rem;
            flex: 0 0 auto;
        }
        body.dark-mode .settings-overview-card span {
            display: block;
            color: #94a3b8 !important;
            font-size: .78rem;
            font-weight: 700;
        }
        body.dark-mode .settings-overview-card strong {
            display: block;
            color: #f8fafc !important;
            font-size: .95rem;
            line-height: 1.2;
        }

        body.light-mode .settings-overview-card {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: rgba(15, 23, 42, 0.02) !important;
            border: 1px solid rgba(15, 23, 42, 0.05) !important;
            border-radius: 12px !important;
            padding: .85rem !important;
            min-height: 78px;
            transition: all 0.3s !important;
        }
        body.light-mode .settings-overview-card:hover {
            transform: translateY(-2px) !important;
            background: rgba(15, 23, 42, 0.04) !important;
            border-color: rgba(99, 102, 241, 0.2) !important;
        }
        body.light-mode .settings-overview-card i {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: rgba(59, 130, 246, 0.1) !important;
            color: #2563eb !important;
            font-size: 1.1rem;
            flex: 0 0 auto;
        }
        body.light-mode .settings-overview-card span {
            display: block;
            color: #64748b !important;
            font-size: .78rem;
            font-weight: 700;
        }
        body.light-mode .settings-overview-card strong {
            display: block;
            color: #0f172a !important;
            font-size: .95rem;
            line-height: 1.2;
        }

        /* Status list styling */
        .settings-status-list {
            display: grid;
            gap: .85rem;
        }
        
        body.dark-mode .settings-status-card {
            background: rgba(15, 23, 42, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 8px;
            padding: 1rem;
        }
        body.dark-mode .settings-status-card.status-danger {
            border-color: rgba(239, 68, 68, 0.25) !important;
            background: rgba(239, 68, 68, 0.03) !important;
        }
        body.dark-mode .settings-status-card.status-warning {
            border-color: rgba(245, 158, 11, 0.25) !important;
            background: rgba(245, 158, 11, 0.03) !important;
        }
        body.dark-mode .settings-status-card.status-success {
            border-color: rgba(16, 185, 129, 0.25) !important;
            background: rgba(16, 185, 129, 0.03) !important;
        }
        body.dark-mode .settings-status-body {
            color: #cbd5e1 !important;
            line-height: 1.55;
            margin-bottom: .75rem;
            overflow-wrap: anywhere;
        }
        body.dark-mode .settings-status-meta {
            color: #94a3b8 !important;
        }
        body.dark-mode .settings-release-title {
            color: #94a3b8 !important;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        body.dark-mode .settings-release-grid span {
            display: flex;
            align-items: center;
            gap: .55rem;
            background: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 10px;
            padding: .6rem .8rem;
            color: #e2e8f0 !important;
            font-size: .82rem;
            font-weight: 600;
            transition: all .2s ease;
        }
        body.dark-mode .settings-release-grid span:hover {
            background: rgba(99, 102, 241, 0.08) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
            transform: translateX(3px);
        }

        body.light-mode .settings-status-card {
            background: rgba(255, 255, 255, 0.75) !important;
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            border-radius: 8px;
            padding: 1rem;
        }
        body.light-mode .settings-status-card.status-danger {
            border-color: rgba(239, 68, 68, 0.2) !important;
            background: rgba(239, 68, 68, 0.02) !important;
        }
        body.light-mode .settings-status-card.status-warning {
            border-color: rgba(245, 158, 11, 0.2) !important;
            background: rgba(245, 158, 11, 0.02) !important;
        }
        body.light-mode .settings-status-card.status-success {
            border-color: rgba(16, 185, 129, 0.2) !important;
            background: rgba(16, 185, 129, 0.02) !important;
        }
        body.light-mode .settings-status-body {
            color: #334155 !important;
            line-height: 1.55;
            margin-bottom: .75rem;
            overflow-wrap: anywhere;
        }
        body.light-mode .settings-status-meta {
            color: #64748b !important;
        }
        body.light-mode .settings-release-title {
            color: #64748b !important;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        body.light-mode .settings-release-grid span {
            display: flex;
            align-items: center;
            gap: .55rem;
            background: rgba(15, 23, 42, 0.02) !important;
            border: 1px solid rgba(15, 23, 42, 0.06) !important;
            border-radius: 10px;
            padding: .6rem .8rem;
            color: #334155 !important;
            font-size: .82rem;
            font-weight: 600;
            transition: all .2s ease;
        }
        body.light-mode .settings-release-grid span:hover {
            background: rgba(99, 102, 241, 0.06) !important;
            border-color: rgba(99, 102, 241, 0.25) !important;
            transform: translateX(3px);
        }
        
        .settings-release-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: .5rem;
        }
        .settings-release-grid span i {
            color: #6366f1 !important;
            font-size: 1rem;
            flex: 0 0 auto;
        }
        
        /* Preferences stack */
        .settings-side-stack {
            display: grid;
            gap: 1.25rem;
            align-content: start;
        }
        .settings-preference-stack {
            display: grid;
            gap: 1rem;
        }

        body.dark-mode .settings-preference-box {
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 8px;
            padding: .85rem;
            background: rgba(15, 23, 42, 0.4) !important;
        }
        body.dark-mode .settings-switch-grid .form-check {
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 8px;
            padding: .65rem .8rem .65rem 2.75rem;
            background: rgba(15, 23, 42, 0.4) !important;
            margin: 0 !important;
            color: #e2e8f0 !important;
            transition: all 0.2s;
        }
        body.dark-mode .settings-switch-grid .form-check:hover {
            border-color: rgba(99, 102, 241, 0.25) !important;
        }

        body.light-mode .settings-preference-box {
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            border-radius: 8px;
            padding: .85rem;
            background: rgba(255, 255, 255, 0.7) !important;
        }
        body.light-mode .settings-switch-grid .form-check {
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            border-radius: 8px;
            padding: .65rem .8rem .65rem 2.75rem;
            background: rgba(255, 255, 255, 0.7) !important;
            margin: 0 !important;
            color: #334155 !important;
            transition: all 0.2s;
        }
        body.light-mode .settings-switch-grid .form-check:hover {
            border-color: rgba(99, 102, 241, 0.2) !important;
        }

        .settings-switch-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: .55rem;
        }
        
        .accent-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.2);
            background: var(--dot);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            position: relative;
        }
        .accent-dot:hover {
            transform: scale(1.15) translateY(-2px);
            box-shadow: 0 6px 15px var(--dot);
        }
        .accent-dot.active {
            transform: scale(1.2);
            border-color: #ffffff !important;
            box-shadow: 0 0 0 2px var(--dot), 0 8px 20px var(--dot) !important;
        }
        body.dark-mode .accent-dot.active {
            border-color: #0f172a !important;
        }
        
        /* Circular custom color picker */
        .form-control-color {
            padding: 0 !important;
            border-radius: 50% !important;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 3px solid rgba(255, 255, 255, 0.2) !important;
        }
        .form-control-color::-webkit-color-swatch {
            border-radius: 50% !important;
            border: none !important;
        }
        .form-control-color::-moz-color-swatch {
            border-radius: 50% !important;
            border: none !important;
        }
        .form-control-color:hover {
            transform: scale(1.1) translateY(-2px);
        }
        .form-control-color.active {
            transform: scale(1.2);
            border-color: #ffffff !important;
            box-shadow: 0 0 0 2px var(--accent-custom-color, #3b82f6), 0 8px 20px var(--accent-custom-color, #3b82f6) !important;
        }
        body.dark-mode .form-control-color.active {
            border-color: #0f172a !important;
        }

        /* Welcome Banner style cards */
        .welcome-banner-styles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-top: 0.75rem;
        }
        .welcome-banner-style-card {
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            border: 2px solid transparent;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        body.dark-mode .welcome-banner-style-card {
            background: rgba(15, 23, 42, 0.45);
            border-color: rgba(255, 255, 255, 0.06);
        }
        body.light-mode .welcome-banner-style-card {
            background: rgba(255, 255, 255, 0.75);
            border-color: rgba(15, 23, 42, 0.06);
        }
        .welcome-banner-style-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }
        body.dark-mode .welcome-banner-style-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.25);
        }
        .welcome-banner-style-card.active {
            border-color: var(--primary-color, #3b82f6) !important;
            box-shadow: 0 0 0 1px var(--primary-color, #3b82f6), 0 8px 24px color-mix(in srgb, var(--primary-color, #3b82f6) 20%, transparent) !important;
        }
        body.dark-mode .welcome-banner-style-card.active {
            box-shadow: 0 0 0 1px var(--primary-color, #6366f1), 0 12px 28px color-mix(in srgb, var(--primary-color, #6366f1) 25%, transparent) !important;
        }
        .banner-preview {
            height: 90px;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        body.dark-mode .banner-preview {
            border-bottom-color: rgba(255, 255, 255, 0.05);
        }
        
        .preview-gradient {
            background: linear-gradient(135deg, var(--primary-color, #3b82f6) 0%, #10b981 100%);
        }
        .preview-gradient::before {
            content: '';
            position: absolute;
            left: -10px;
            top: -10px;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15), transparent 65%);
        }
        
        .preview-pure {
            background: linear-gradient(135deg, var(--primary-color, #3b82f6) 0%, color-mix(in srgb, var(--primary-color, #3b82f6) 25%, #0d0b21) 100%);
        }
        .preview-pure::before {
            content: '';
            position: absolute;
            left: -10px;
            top: -10px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12), transparent 60%);
        }
        
        .preview-aurora {
            background: linear-gradient(135deg, #0d0b21 0%, var(--primary-color, #3b82f6) 50%, #03001c 100%);
            background-size: 200% 200%;
            animation: previewMesh 8s ease infinite;
        }
        @keyframes previewMesh {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .preview-glass {
            background: radial-gradient(circle at 100% 100%, #10b981 0%, var(--primary-color, #3b82f6) 100%);
        }
        .preview-glass::before {
            content: '';
            position: absolute;
            inset: 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        body.dark-mode .preview-glass::before {
            background: rgba(15, 23, 42, 0.45);
            border-color: rgba(255, 255, 255, 0.08);
        }
        
        .banner-style-info {
            padding: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex-grow: 1;
        }
        .banner-style-name {
            font-size: 0.88rem;
            font-weight: 700;
        }
        body.dark-mode .banner-style-name {
            color: #f8fafc;
        }
        body.light-mode .banner-style-name {
            color: #0f172a;
        }
        .banner-style-desc {
            font-size: 0.72rem;
            color: #64748b;
            line-height: 1.35;
        }
        body.dark-mode .banner-style-desc {
            color: #94a3b8;
        }
        
        /* Keyframes for Aurora */
        @keyframes ctaMeshMovement {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Buttons design */
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #8b5cf6 100%) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3) !important;
            transition: all 0.3s;
            color: #ffffff !important;
        }
        .btn-primary:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45) !important;
        }
        .btn-outline-primary {
            border: 1px solid #8b5cf6 !important;
            color: #8b5cf6 !important;
            background: transparent !important;
        }
        .btn-outline-primary:hover {
            background: #8b5cf6 !important;
            color: #fff !important;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.4) !important;
        }
        
        /* Active preferences styles */
        body.dark-mode .settings-active-preferences {
            background: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
            border-radius: 12px;
            padding: 1rem;
        }
        body.dark-mode .settings-active-preferences-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
        }

        body.light-mode .settings-active-preferences {
            background: rgba(15, 23, 42, 0.02) !important;
            border: 1px solid rgba(15, 23, 42, 0.05) !important;
            border-radius: 12px;
            padding: 1rem;
        }
        body.light-mode .settings-active-preferences-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .settings-active-preference-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.75rem;
        }
        
        .preference-chip {
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            transition: all 0.3s ease;
        }
        body.dark-mode .preference-chip {
            background: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        body.light-mode .preference-chip {
            background: rgba(15, 23, 42, 0.02) !important;
            border: 1px solid rgba(15, 23, 42, 0.04) !important;
        }
        .preference-chip span {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
        }
        body.light-mode .preference-chip span {
            color: #64748b;
        }
        .preference-chip strong {
            font-size: 0.88rem;
            color: #ffffff;
        }
        body.light-mode .preference-chip strong {
            color: #0f172a;
        }

        /* Danger zone card design */
        .danger-zone-panel {
            border: 1px dashed rgba(239, 68, 68, 0.35) !important;
            background: rgba(239, 68, 68, 0.02) !important;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.04) !important;
            transition: all 0.3s ease !important;
        }
        .danger-zone-panel:hover {
            border-color: rgba(239, 68, 68, 0.6) !important;
            box-shadow: 0 15px 35px rgba(239, 68, 68, 0.08) !important;
        }
        
        /* Sidebar tabs navigation styling */
        .settings-nav-panel {
            padding: 1.25rem !important;
            height: auto;
        }
        #settings-tabs .nav-link {
            text-align: left;
            border-radius: 12px;
            padding: 0.8rem 1.2rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            border-left: 3px solid transparent !important;
            cursor: pointer !important;
            z-index: 10;
        }
        #settings-tabs .nav-link * {
            pointer-events: none;
        }
        body.dark-mode #settings-tabs .nav-link {
            color: #cbd5e1 !important;
        }
        body.dark-mode #settings-tabs .nav-link:hover {
            background: rgba(255, 255, 255, 0.04) !important;
            color: #ffffff !important;
            transform: translateX(4px);
        }
        body.dark-mode #settings-tabs .nav-link.active {
            background: rgba(99, 102, 241, 0.08) !important;
            border-color: rgba(99, 102, 241, 0.2) !important;
            border-left-color: #8b5cf6 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.15) !important;
        }
        body.dark-mode #settings-tabs .nav-link i {
            color: #8b5cf6 !important;
        }
        body.dark-mode #settings-tabs .nav-link.active i {
            color: #a5b4fc !important;
        }
        
        body.light-mode #settings-tabs .nav-link {
            color: #475569 !important;
        }
        body.light-mode #settings-tabs .nav-link:hover {
            background: rgba(15, 23, 42, 0.03) !important;
            color: #0f172a !important;
            transform: translateX(4px);
        }
        body.light-mode #settings-tabs .nav-link.active {
            background: rgba(59, 130, 246, 0.05) !important;
            border-color: rgba(59, 130, 246, 0.1) !important;
            border-left-color: #2563eb !important;
            color: #2563eb !important;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.08) !important;
        }
        body.light-mode #settings-tabs .nav-link i {
            color: #3b82f6 !important;
        }
        body.light-mode #settings-tabs .nav-link.active i {
            color: #2563eb !important;
        }
        
        /* Panel headers visual underline */
        .panel-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.85rem;
            margin-bottom: 1.5rem !important;
        }
        body.light-mode .panel-header {
            border-bottom-color: rgba(15, 23, 42, 0.06);
        }

        /* Avatar wrappers */
        .avatar-preview-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            flex-shrink: 0;
            background: rgba(15, 23, 42, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
        }
        body.dark-mode .avatar-preview-wrapper {
            background: rgba(255, 255, 255, 0.02);
        }
        .avatar-preview-wrapper:hover {
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.25);
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        /* Adjust layout spacing for tabs */
        .tab-content > .tab-pane {
            outline: none;
        }

        /* Responsive Mobile Vertical List Navigation Tabs */
        @media (max-width: 767.98px) {
            .settings-nav-panel {
                padding: 0.75rem !important;
                border-radius: 1.25rem !important;
                margin-bottom: 1rem !important;
            }
            #settings-tabs {
                flex-direction: column !important;
                flex-wrap: nowrap !important;
                width: 100% !important;
                gap: 0.5rem !important;
                overflow: visible !important;
                padding: 0 !important;
            }
            #settings-tabs .nav-link {
                width: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
                padding: 0.75rem 1.1rem !important;
                border-radius: 12px !important;
                white-space: normal !important;
                font-size: 0.95rem !important;
                border: 1px solid transparent !important;
                border-left: 4px solid transparent !important;
                transform: none !important;
            }
            body.dark-mode #settings-tabs .nav-link {
                background: rgba(255, 255, 255, 0.03) !important;
                border-color: rgba(255, 255, 255, 0.06) !important;
                color: #cbd5e1 !important;
            }
            body.dark-mode #settings-tabs .nav-link.active {
                background: rgba(99, 102, 241, 0.15) !important;
                border-color: rgba(99, 102, 241, 0.3) !important;
                border-left-color: #8b5cf6 !important;
                color: #ffffff !important;
                box-shadow: 0 4px 16px rgba(99, 102, 241, 0.15) !important;
            }
            body.light-mode #settings-tabs .nav-link {
                background: rgba(15, 23, 42, 0.02) !important;
                border-color: rgba(15, 23, 42, 0.05) !important;
                color: #475569 !important;
            }
            body.light-mode #settings-tabs .nav-link.active {
                background: rgba(59, 130, 246, 0.08) !important;
                border-color: rgba(59, 130, 246, 0.2) !important;
                border-left-color: #2563eb !important;
                color: #2563eb !important;
                box-shadow: 0 4px 16px rgba(59, 130, 246, 0.1) !important;
            }
        }

        @media (max-width: 991.98px) {
            .settings-overview-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .settings-side-stack {
                margin-top: 1.25rem;
            }
        }
        @media (max-width: 575.98px) {
            .settings-overview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
HTML;
$bodyAttributes = 'class="<?php echo ($currentTheme === \'dark\') ? \'dark-mode\' : \'light-mode\'; ?';
include '../includes/header.php';
?>">

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <div class="mb-4 animate-in">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div>
                                <h2 class="fw-bold mb-1">Ustawienia konta</h2>
                                <p class="text-muted mb-0">Dane, prywatność, wygląd i zachowanie aplikacji.</p>
                            </div>
                            <a href="profile.php" class="btn btn-outline-primary rounded-pill px-4"><i class="bi bi-person me-1"></i>Profil</a>
                        </div>
                    </div>

                    <div class="settings-overview-grid mb-4 animate-in" aria-label="Szybki stan ustawień">
                        <?php foreach ($settingsHealth as $item): ?>
                            <div class="settings-overview-card" data-settings-overview="<?php echo htmlspecialchars($item['key']); ?>">
                                <i class="bi <?php echo htmlspecialchars($item['icon']); ?>"></i>
                                <div>
                                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                                    <strong data-settings-overview-value><?php echo htmlspecialchars($item['value']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?php echo ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                            <?php echo htmlspecialchars($flashMsg['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <!-- Left Column: Navigation Sidebar Pills -->
                        <div class="col-12 col-md-4 col-lg-3">
                            <div class="dashboard-panel p-3 settings-nav-panel animate-in">
                                <div class="nav flex-column nav-pills gap-2" id="settings-tabs" role="tablist" aria-orientation="vertical">
                                    <a class="nav-link active text-start" id="tab-profile" data-bs-toggle="pill" href="#pane-profile" role="tab" aria-controls="pane-profile" aria-selected="true">
                                        <i class="bi bi-person-circle"></i>
                                        <span>Profil konta</span>
                                    </a>
                                    <a class="nav-link text-start" id="tab-privacy" data-bs-toggle="pill" href="#pane-privacy" role="tab" aria-controls="pane-privacy" aria-selected="false">
                                        <i class="bi bi-eye-slash"></i>
                                        <span>Prywatność</span>
                                    </a>
                                    <a class="nav-link text-start" id="tab-security" data-bs-toggle="pill" href="#pane-security" role="tab" aria-controls="pane-security" aria-selected="false">
                                        <i class="bi bi-shield-lock"></i>
                                        <span>Bezpieczeństwo</span>
                                    </a>
                                    <a class="nav-link text-start" id="tab-preferences" data-bs-toggle="pill" href="#pane-preferences" role="tab" aria-controls="pane-preferences" aria-selected="false">
                                        <i class="bi bi-palette"></i>
                                        <span>Wygląd i preferencje</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Navigation Panes -->
                        <div class="col-12 col-md-8 col-lg-9">
                            <div class="tab-content" id="settings-tab-content">
                                <!-- Pane 1: Profile -->
                                <div class="tab-pane fade show active" id="pane-profile" role="tabpanel" aria-labelledby="tab-profile">
                                    <?php
                                    $profileScore = 20;
                                    if (!empty($userSettings['avatar_path'])) $profileScore += 20;
                                    if (!empty($userSettings['first_name']) || !empty($userSettings['last_name'])) $profileScore += 20;
                                    if (!empty($userSettings['bio'])) $profileScore += 20;
                                    if ($mfaEnabled) $profileScore += 20;
                                    ?>
                                    <div class="dashboard-panel mb-4 p-3 border-0 bg-primary bg-opacity-10 animate-in">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small fw-bold text-primary"><i class="bi bi-person-badge me-1"></i>Kompletność profilu</span>
                                            <span class="badge bg-primary rounded-pill"><?= $profileScore ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px; background: rgba(59, 130, 246, 0.15);">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $profileScore ?>%;" aria-valuenow="<?= $profileScore ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                    <!-- Profile Card -->
                                    <div class="dashboard-panel mb-4 animate-in">
                                        <div class="panel-header mb-4">
                                            <h5 class="panel-title mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Dane podstawowe</h5>
                                        </div>
                                        <form action="../actions/update_profile.php" method="POST" enctype="multipart/form-data">
                                            <?php echo csrfTokenField(); ?>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nazwa użytkownika</label>
                                                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" minlength="3" maxlength="16" pattern="[A-Za-z0-9_\.-]{3,16}" required>
                                                    <div class="form-text">3–16 znaków (litery, cyfry, kropka, myślnik, podkreślenie).</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Adres E-mail</label>
                                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                                                    <div class="form-text">Adres używany do logowania i powiadomień.</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Imię</label>
                                                    <input type="text" name="first_name" class="form-control" maxlength="50" value="<?php echo htmlspecialchars($firstName); ?>" placeholder="np. Jan">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Nazwisko</label>
                                                    <input type="text" name="last_name" class="form-control" maxlength="50" value="<?php echo htmlspecialchars($lastName); ?>" placeholder="np. Kowalski">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Klasa</label>
                                                    <select name="class_year" class="form-select">
                                                        <option value="" <?php echo $classYear === null || $classYear === '' ? 'selected' : ''; ?>>Nie dotyczy / Absolwent</option>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <option value="<?php echo $i; ?>" <?php echo (string)$classYear === (string)$i ? 'selected' : ''; ?>>Klasa <?php echo $i; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Oznaczenie oddziału (litera)</label>
                                                    <input type="text" name="class_suffix" class="form-control" maxlength="2" pattern="[A-Za-z]{0,2}" value="<?php echo htmlspecialchars($classSuffix); ?>" placeholder="np. A, B, C">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label d-block mb-3">Zdjęcie profilowe</label>
                                                    <div class="d-flex align-items-center gap-4 flex-wrap">
                                                        <div class="avatar-preview-wrapper position-relative">
                                                            <?php if ($settingsAvatarSrc): ?>
                                                                <img src="<?php echo htmlspecialchars($settingsAvatarSrc); ?>" alt="Avatar" id="settingsAvatarPreview" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy" decoding="async">
                                                            <?php else: ?>
                                                                <div class="w-100 h-100 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="font-size: 2.2rem;">
                                                                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-grow-1 min-w-200">
                                                            <input type="file" name="avatar" id="avatarFileInput" class="form-control mb-2" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAvatar(this)">
                                                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2" onclick="document.getElementById('avatarFileInput').click()">
                                                                <i class="bi bi-upload me-1"></i>Wybierz plik
                                                            </button>
                                                            <?php if ($settingsAvatarSrc): ?>
                                                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="triggerDeleteAvatar(event)">
                                                                    <i class="bi bi-trash3 me-1"></i>Usuń
                                                                </button>
                                                            <?php endif; ?>
                                                            <div class="form-text mt-2" style="font-size: 0.78rem;">JPG/PNG/WebP, maks. 2 MB przed wysłaniem. Na serwerze avatar jest filtrowany, kompresowany do WebP i nie przekracza 25 KB.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-4">
                                                    <button type="submit" class="btn btn-primary px-4">
                                                        Zapisz zmiany
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <form id="deleteAvatarForm" action="../actions/update_profile.php" method="POST" style="display: none;">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="return_to" value="settings.php">
                                            <input type="hidden" name="action" value="delete_avatar">
                                        </form>
                                    </div>
                                </div>

                                <!-- Pane 2: Privacy -->
                                <div class="tab-pane fade" id="pane-privacy" role="tabpanel" aria-labelledby="tab-privacy">
                                    <div class="dashboard-panel animate-in">
                                        <div class="panel-header mb-4">
                                            <h5 class="panel-title mb-0"><i class="bi bi-eye-slash me-2 text-warning"></i>Ustawienia prywatności</h5>
                                        </div>
                                        <form action="../actions/update_privacy.php" method="POST">
                                            <?php echo csrfTokenField(); ?>
                                            
                                            <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-globe me-1"></i>Widoczność profilu &amp; statystyk</h6>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="profilePublic" name="profile_public" value="1" <?php echo $profilePublic ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="profilePublic">Profil publiczny (widoczny dla innych)</label>
                                                    </div>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="statsPublic" name="stats_public" value="1" <?php echo $statsPublic ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="statsPublic">Statystyki publiczne</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="searchable" name="searchable" value="1" <?php echo $searchable ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="searchable">Profil widoczny w wyszukiwarce</label>
                                                    </div>
                                                    <?php if ($role === 'teacher'): ?>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="rankingVisible" name="ranking_visible" value="1" <?php echo $rankingVisible ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="rankingVisible">Biorę udział w rankingu XP</label>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-people me-1"></i>Interakcje i społeczność</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="allowProfileComments" name="allow_profile_comments" value="1" <?php echo $allowProfileComments ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="allowProfileComments">Komentarze pod profilem</label>
                                                    </div>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="allowFriendRequests" name="allow_friend_requests" value="1" <?php echo $allowFriendRequests ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="allowFriendRequests">Akceptuj zaproszenia do znajomych</label>
                                                    </div>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="showMissions" name="show_missions" value="1" <?php echo $showMissions ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="showMissions">Pokazuj misje na moim profilu</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="showOnlineStatus" name="show_online_status" value="1" <?php echo $showOnlineStatus ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="showOnlineStatus">Pokazuj status aktywności (Online)</label>
                                                    </div>
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="showRecentActivity" name="show_recent_activity" value="1" <?php echo $showRecentActivity ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="showRecentActivity">Pokazuj ostatnią aktywność na profilu</label>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-3">
                                                    <button type="submit" class="btn btn-primary px-4">
                                                        <i class="bi bi-shield-check me-1"></i>Zapisz ustawienia prywatności
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Pane 3: Security -->
                                <div class="tab-pane fade" id="pane-security" role="tabpanel" aria-labelledby="tab-security">
                                    <?php
                                    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
                                    $stmt->execute([$userId]);
                                    $userPasswordHash = $stmt->fetchColumn();

                                    if ($userPasswordHash && strlen($userPasswordHash) === 32 && ctype_xdigit($userPasswordHash)):
                                    ?>
                                    <div class="dashboard-panel mb-4 animate-in border-warning">
                                        <div class="panel-header mb-4 bg-warning bg-opacity-10 p-3 rounded">
                                            <h5 class="panel-title mb-0 text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Zalecana migracja hasła</h5>
                                        </div>
                                        <div class="p-3">
                                            <p>Twoje hasło korzysta ze starszego sposobu zabezpieczenia (MD5). Zalecamy jednorazową migrację do nowoczesnego standardu Argon2id.</p>
                                            <form action="../actions/migrate_md5.php" method="POST">
                                                <?php echo csrfTokenField(); ?>
                                                <input type="hidden" name="return_to" value="settings.php">
                                                <div class="mb-3">
                                                    <label class="form-label">Obecne hasło</label>
                                                    <input type="password" name="current_password" class="form-control" required>
                                                </div>
                                                <button type="submit" class="btn btn-warning px-4">
                                                    Zwiększ bezpieczeństwo hasła
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Change Password Card -->
                                    <div class="dashboard-panel mb-4 animate-in">
                                        <div class="panel-header mb-4">
                                            <h5 class="panel-title mb-0"><i class="bi bi-shield-lock me-2 text-danger"></i>Zmiana hasła</h5>
                                        </div>
                                        <form action="../actions/change_password.php" method="POST">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="return_to" value="settings.php">
                                            <div class="row g-3">
                                                <div class="col-md-12">
                                                    <label class="form-label">Aktualne hasło</label>
                                                    <input type="password" name="current_password" class="form-control" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Nowe hasło</label>
                                                    <input type="password" name="new_password" id="newPasswordInput" class="form-control" required oninput="checkPasswordStrength(this.value)">
                                                    <div class="progress mt-2" style="height: 5px; display: none;" id="pwdStrengthBar">
                                                        <div class="progress-bar" id="pwdStrengthFill" style="width: 0%;"></div>
                                                    </div>
                                                    <div class="small mt-2" id="pwdRequirements">
                                                        <div class="text-muted" id="reqLen"><i class="bi bi-circle me-1"></i>Min. 8 znaków</div>
                                                        <div class="text-muted" id="reqUpper"><i class="bi bi-circle me-1"></i>Wielka litera</div>
                                                        <div class="text-muted" id="reqNum"><i class="bi bi-circle me-1"></i>Cyfra lub znak specjalny</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Powtórz nowe hasło</label>
                                                    <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control" required oninput="checkPasswordMatch()">
                                                    <div class="small mt-2 text-muted" id="reqMatch"><i class="bi bi-circle me-1"></i>Zgodność obu haseł</div>
                                                </div>
                                                <div class="col-12 mt-4">
                                                    <button type="submit" class="btn btn-outline-danger px-4">
                                                        Zmień hasło
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <form action="../actions/logout_all_sessions.php" method="POST" class="mt-3">
                                            <?= csrfTokenField('logout_all') ?>
                                            <input type="hidden" name="include_current" value="1">
                                            <button type="submit" class="btn btn-outline-warning px-4">
                                                <i class="bi bi-box-arrow-right me-1"></i>Wyloguj wszystkie sesje
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Active Device Sessions Card (R7.4) -->
                                    <div class="dashboard-panel mb-4 animate-in">
                                        <div class="panel-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <h5 class="panel-title mb-0"><i class="bi bi-devices me-2 text-info"></i>Aktywne sesje i urządzenia</h5>
                                            <?php if (count($activeUserSessions) > 1): ?>
                                                <button type="button" class="btn btn-outline-warning btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#revokeAllExceptModal">
                                                    <i class="bi bi-box-arrow-right me-1"></i>Wyloguj pozostałe urządzenia
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted small mb-3">Zarządzaj urządzeniami, na których jesteś zalogowany. W razie potrzeby możesz unieważnić pojedynczą sesję lub wszystkie pozostałe.</p>
                                        
                                        <div class="list-group list-group-flush bg-transparent">
                                            <?php if (empty($activeUserSessions)): ?>
                                                <div class="text-muted small py-3 text-center">Brak zarejestrowanych aktywnych sesji.</div>
                                            <?php else: ?>
                                                <?php foreach ($activeUserSessions as $sess): ?>
                                                    <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-secondary border-opacity-25">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="p-2 rounded-3 bg-secondary bg-opacity-10 text-primary fs-4">
                                                                <i class="bi <?php echo htmlspecialchars($sess['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                            </div>
                                                            <div>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <strong class="text-light"><?php echo htmlspecialchars($sess['browser'] . ' (' . $sess['os'] . ')', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                                    <?php if (!empty($sess['is_current'])): ?>
                                                                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">Bieżące urządzenie</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-muted small">
                                                                    <span>IP: <code><?php echo htmlspecialchars($sess['ip_address'], ENT_QUOTES, 'UTF-8'); ?></code></span> &bull; 
                                                                    <span>Aktywność: <?php echo htmlspecialchars($sess['last_seen_relative'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php if (empty($sess['is_current'])): ?>
                                                            <form action="../actions/revoke_session.php" method="POST" class="m-0">
                                                                <?php echo csrfTokenField('revoke_session'); ?>
                                                                <input type="hidden" name="action" value="revoke_single">
                                                                <input type="hidden" name="session_hash" value="<?php echo htmlspecialchars($sess['session_hash'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill">
                                                                    <i class="bi bi-x-circle me-1"></i>Wyloguj
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- 2FA Card -->
                                    <div class="dashboard-panel mb-4 animate-in">
                                        <div class="panel-header mb-4">
                                            <h5 class="panel-title mb-0"><i class="bi bi-phone-lock me-2 text-primary"></i>Uwierzytelnianie 2FA</h5>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                            <div>
                                                <div class="fw-bold"><?php echo $mfaEnabled ? '2FA aktywne' : '2FA wyłączone'; ?></div>
                                                <div class="text-muted small">
                                                    <?php
                                                        if (in_array($role, ['admin', 'dyrektor'], true)) {
                                                            echo 'Dla tej roli 2FA jest wymagane.';
                                                        } elseif (in_array($role, ['teacher', 'user'], true)) {
                                                            echo '2FA jest opcjonalne, ale po włączeniu będzie wymagane przy logowaniu.';
                                                        } else {
                                                            echo '2FA jest dostępne dla kont użytkownika, nauczyciela i administracji.';
                                                        }
                                                    ?>
                                                </div>
                                            </div>
                                            <?php if ($canUseMfa): ?>
                                            <a href="../auth/mfa.php" class="btn btn-outline-primary rounded-pill px-4">
                                                <i class="bi bi-shield-lock me-1"></i><?php echo $mfaEnabled ? 'Sprawdź kody' : 'Włącz 2FA'; ?>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (in_array($role, ['admin', 'dyrektor', 'teacher'], true)): ?>
                                    <!-- Passkey Card -->
                                    <div class="dashboard-panel mb-4 animate-in">
                                        <div class="panel-header mb-4">
                                            <h5 class="panel-title mb-0"><i class="bi bi-fingerprint me-2 text-primary"></i>Logowanie Passkey (U2F / Biometria)</h5>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                            <div>
                                                <div class="fw-bold">Zaloguj się bezpieczniej, bez hasła</div>
                                                <div class="text-muted small">
                                                    Zarejestruj czytnik linii papilarnych, Face ID lub klucz U2F, aby używać ich zamiast hasła. Opcja dostępna tylko dla personelu.
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary rounded-pill px-4" onclick="registerPasskey()">
                                                <i class="bi bi-plus-circle me-1"></i>Dodaj Passkey
                                            </button>
                                        </div>
                                        
                                        <?php
                                        // Pobieramy passkeys usera z osłoną try-catch
                                        $passkeysList = [];
                                        try {
                                            $stmtPk = $pdo->prepare("SELECT id, device_name, created_at FROM user_passkeys WHERE user_id = ?");
                                            $stmtPk->execute([$userId]);
                                            $passkeysList = $stmtPk->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (PDOException $e) {
                                            $passkeysList = [];
                                        }
                                        ?>
                                        <?php if (!empty($passkeysList)): ?>
                                        <div class="mt-4 border-top pt-3">
                                            <h6 class="fw-bold mb-3 small">Twoje klucze Passkey:</h6>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($passkeysList as $pk): ?>
                                                <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
                                                    <div>
                                                        <div class="fw-semibold"><i class="bi bi-key me-2 text-secondary"></i><?php echo htmlspecialchars($pk['device_name'] ?: 'Nieznane urządzenie'); ?></div>
                                                        <div class="small text-muted">Dodano: <?php echo date('d.m.Y H:i', strtotime($pk['created_at'])); ?></div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="deletePasskey(<?php echo $pk['id']; ?>)">Usuń</button>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Danger Zone -->
                                    <div class="dashboard-panel danger-zone-panel animate-in">
                                        <div class="panel-header mb-4">
                                            <h5 class="panel-title mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Strefa niebezpieczna</h5>
                                        </div>
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <h6 class="fw-bold mb-1">Zresetuj postępy</h6>
                                                    <p class="text-muted small">Wszystkie Twoje wyniki, statystyki i XP zostaną usunięte. Konto pozostanie aktywne.</p>
                                                </div>
                                                <form action="../actions/reset_progress.php" method="POST" onsubmit="return appConfirmSubmit(this, 'CZY NA PEWNO? Ta operacja jest nieodwracalna i usunie CAŁĄ Twoją historię nauki.')">
                                                    <?php echo csrfTokenField(); ?>
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">Resetuj mój progres</button>
                                                </form>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <h6 class="fw-bold mb-1">Usuń konto</h6>
                                                    <p class="text-muted small">Trwale usuń swoje konto oraz wszystkie dane z serwera (Zgodnie z RODO). Tej operacji nie można cofnąć.</p>
                                                </div>
                                                <form action="../actions/delete_account.php" method="POST" onsubmit="return appConfirmSubmit(this, 'UWAGA! Czy na pewno chcesz TRWALE USUNĄĆ swoje konto? Stracisz dostęp do wszystkich funkcji.')">
                                                    <?php echo csrfTokenField(); ?>
                                                    <button type="submit" class="btn btn-danger btn-sm">Usuń konto na zawsze</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pane 4: Preferences -->
                                <div class="tab-pane fade" id="pane-preferences" role="tabpanel" aria-labelledby="tab-preferences">
                                    <div class="row g-4">
                                        <!-- Preferences Inputs -->
                                        <div class="col-12 col-xl-7">
                                            <div class="dashboard-panel animate-in">
                                                <div class="panel-header mb-4">
                                                    <h5 class="panel-title mb-0"><i class="bi bi-sliders me-2 text-success"></i>Preferencje</h5>
                                                </div>
                                                <div class="mb-4">
                                                    <label class="form-label d-block">Rozmiar tekstu</label>
                                                    <div class="btn-group w-100" role="group">
                                                        <input type="radio" class="btn-check" name="fontSize" id="fontSmall" autocomplete="off" onchange="updateFontSizeSetting('14')" <?php echo $currentFontSize == '14' ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-secondary" for="fontSmall">Mały</label>

                                                        <input type="radio" class="btn-check" name="fontSize" id="fontMedium" autocomplete="off" onchange="updateFontSizeSetting('16')" <?php echo $currentFontSize == '16' ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-secondary" for="fontMedium">Średni</label>

                                                        <input type="radio" class="btn-check" name="fontSize" id="fontLarge" autocomplete="off" onchange="updateFontSizeSetting('18')" <?php echo $currentFontSize == '18' ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-secondary" for="fontLarge">Duży</label>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-4">
                                                    <label class="form-label d-block" for="themeSelect">Motyw kolorystyczny</label>
                                                    <select class="form-select" id="themeSelect" onchange="updateThemeSetting(this.value)">
                                                        <option value="light" <?php echo $currentTheme == 'light' ? 'selected' : ''; ?>>Jasny (domyślny)</option>
                                                        <option value="dark" <?php echo $currentTheme == 'dark' ? 'selected' : ''; ?>>Ciemny (Beta)</option>
                                                    </select>
                                                </div>

                                                <div class="mb-4">
                                                     <label class="form-label d-block">Kolor akcentu</label>
                                                     <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                                                         <div class="position-relative d-inline-block" style="width: 48px; height: 48px;">
                                                             <input type="color" class="form-control form-control-color" id="accentColor" value="<?php echo htmlspecialchars($currentAccent); ?>" onchange="updateAccentSetting(this.value); applyUiPreferences(); syncAccentUi(this.value);" style="width: 48px; height: 48px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.2); cursor: pointer;" title="Niestandardowy kolor">
                                                             <i class="bi bi-pipette position-absolute start-50 top-50 translate-middle pointer-events-none" style="color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.5); font-size: 1.1rem; z-index: 2;"></i>
                                                         </div>
                                                         <div class="d-flex align-items-center gap-2 flex-wrap">
                                                             <button type="button" class="accent-dot" data-color="#3b82f6" style="--dot:#3b82f6" onclick="pickAccent('#3b82f6')" aria-label="Niebieski"></button>
                                                             <button type="button" class="accent-dot" data-color="#06b6d4" style="--dot:#06b6d4" onclick="pickAccent('#06b6d4')" aria-label="Turkusowy"></button>
                                                             <button type="button" class="accent-dot" data-color="#10b981" style="--dot:#10b981" onclick="pickAccent('#10b981')" aria-label="Zielony"></button>
                                                             <button type="button" class="accent-dot" data-color="#6366f1" style="--dot:#6366f1" onclick="pickAccent('#6366f1')" aria-label="Indygo"></button>
                                                             <button type="button" class="accent-dot" data-color="#8b5cf6" style="--dot:#8b5cf6" onclick="pickAccent('#8b5cf6')" aria-label="Fioletowy"></button>
                                                             <button type="button" class="accent-dot" data-color="#ec4899" style="--dot:#ec4899" onclick="pickAccent('#ec4899')" aria-label="Różowy"></button>
                                                             <button type="button" class="accent-dot" data-color="#f43f5e" style="--dot:#f43f5e" onclick="pickAccent('#f43f5e')" aria-label="Karminowy"></button>
                                                             <button type="button" class="accent-dot" data-color="#f59e0b" style="--dot:#f59e0b" onclick="pickAccent('#f59e0b')" aria-label="Złocisty"></button>
                                                             <button type="button" class="accent-dot" data-color="#ef4444" style="--dot:#ef4444" onclick="pickAccent('#ef4444')" aria-label="Czerwony"></button>
                                                         </div>
                                                     </div>
                                                 </div>

                                                <div class="mb-4">
                                                    <label class="form-label d-block" for="densitySelect">Gęstość interfejsu</label>
                                                    <select class="form-select" id="densitySelect" onchange="updateDensitySetting(this.value); applyUiPreferences();">
                                                        <option value="comfortable" <?php echo $currentDensity === 'comfortable' ? 'selected' : ''; ?>>Wygodna</option>
                                                        <option value="compact" <?php echo $currentDensity === 'compact' ? 'selected' : ''; ?>>Kompaktowa</option>
                                                    </select>
                                                </div>

                                                <div class="mb-4">
                                                    <label class="form-label d-block" for="dashboardView">Widok dashboardu</label>
                                                    <select class="form-select" id="dashboardView" onchange="updateDashboardViewSetting(this.value)">
                                                        <option value="balanced" <?php echo $dashboardView === 'balanced' ? 'selected' : ''; ?>>Zbalansowany</option>
                                                        <option value="learning" <?php echo $dashboardView === 'learning' ? 'selected' : ''; ?>>Nauka i misje</option>
                                                        <option value="compact" <?php echo $dashboardView === 'compact' ? 'selected' : ''; ?>>Kompaktowy</option>
                                                    </select>
                                                </div>

                                                <div class="mb-4">
                                                     <label class="form-label d-block">Styl baneru powitalnego</label>
                                                     <div class="welcome-banner-styles-grid">
                                                         <div class="welcome-banner-style-card" data-style="gradient" onclick="selectWelcomeBannerStyle('gradient')">
                                                             <div class="banner-preview preview-gradient"></div>
                                                             <div class="banner-style-info">
                                                                 <span class="banner-style-name">Zbalansowany gradient</span>
                                                                 <span class="banner-style-desc">Przejście akcentu w zieleń</span>
                                                             </div>
                                                         </div>
                                                         <div class="welcome-banner-style-card" data-style="pure" onclick="selectWelcomeBannerStyle('pure')">
                                                             <div class="banner-preview preview-pure"></div>
                                                             <div class="banner-style-info">
                                                                 <span class="banner-style-name">Czysty akcent</span>
                                                                 <span class="banner-style-desc">Jednolity odcień</span>
                                                             </div>
                                                         </div>
                                                         <div class="welcome-banner-style-card" data-style="aurora" onclick="selectWelcomeBannerStyle('aurora')">
                                                             <div class="banner-preview preview-aurora"></div>
                                                             <div class="banner-style-info">
                                                                 <span class="banner-style-name">Kosmiczna zorza</span>
                                                                 <span class="banner-style-desc">Animowana aura</span>
                                                             </div>
                                                         </div>
                                                         <div class="welcome-banner-style-card" data-style="glass" onclick="selectWelcomeBannerStyle('glass')">
                                                             <div class="banner-preview preview-glass"></div>
                                                             <div class="banner-style-info">
                                                                 <span class="banner-style-name">Szklany minimalizm</span>
                                                                 <span class="banner-style-desc">Efekt glassmorphism</span>
                                                             </div>
                                                         </div>
                                                     </div>
                                                     <select class="form-select" id="welcomeBannerStyleSelect" onchange="updateWelcomeBannerStyleSetting(this.value)" style="display: none;">
                                                         <option value="gradient" <?php echo $welcomeBannerStyle === 'gradient' ? 'selected' : ''; ?>>Zbalansowany gradient</option>
                                                         <option value="pure" <?php echo $welcomeBannerStyle === 'pure' ? 'selected' : ''; ?>>Czysty akcent</option>
                                                         <option value="aurora" <?php echo $welcomeBannerStyle === 'aurora' ? 'selected' : ''; ?>>Kosmiczna zorza (Animowany)</option>
                                                         <option value="glass" <?php echo $welcomeBannerStyle === 'glass' ? 'selected' : ''; ?>>Szklany minimalizm (Glassmorphism)</option>
                                                     </select>
                                                 </div>

                                                <div class="mb-4">
                                                    <label class="form-label d-block" for="defaultTestMode">Domyślny tryb testu</label>
                                                    <select class="form-select" id="defaultTestMode" onchange="updateDefaultTestModeSetting(this.value)">
                                                        <option value="exam" <?php echo $defaultTestMode === 'exam' ? 'selected' : ''; ?>>Egzamin</option>
                                                        <option value="practice" <?php echo $defaultTestMode === 'practice' ? 'selected' : ''; ?>>Ćwiczenia</option>
                                                        <option value="single" <?php echo $defaultTestMode === 'single' ? 'selected' : ''; ?>>Pojedyncze pytanie</option>
                                                    </select>
                                                </div>

                                                <div class="settings-switch-grid mb-4">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="notifySwitch" onchange="updateNotifyActivitySetting(this.checked)">
                                                        <label class="form-check-label" for="notifySwitch">Alerty o aktywnościach</label>
                                                    </div>
                                                    
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="soundsSwitch" onchange="updateUiSoundsSetting(this.checked)">
                                                        <label class="form-check-label" for="soundsSwitch">Efekty dźwiękowe</label>
                                                    </div>

                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="motionSwitch" <?php echo $reduceMotion ? 'checked' : ''; ?> onchange="updateReduceMotionSetting(this.checked); applyUiPreferences();">
                                                        <label class="form-check-label" for="motionSwitch">Ogranicz animacje</label>
                                                    </div>

                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="externalTabSwitch" <?php echo $openExternalNewTab ? 'checked' : ''; ?> onchange="updateExternalNewTabSetting(this.checked)">
                                                        <label class="form-check-label" for="externalTabSwitch">Otwieraj linki zewnętrzne w nowej karcie</label>
                                                    </div>
                                                </div>

                                                <div class="settings-mini-grid mb-4" style="display: none;">
                                                    <div class="settings-mini-card" data-settings-mini="notify">
                                                        <i class="bi bi-bell"></i>
                                                        <span>Alerty: <strong data-settings-mini-value>--</strong></span>
                                                    </div>
                                                    <div class="settings-mini-card" data-settings-mini="layout">
                                                        <i class="bi bi-layout-sidebar"></i>
                                                        <span>Układ: <strong data-settings-mini-value><?php echo htmlspecialchars($dashboardView); ?></strong></span>
                                                    </div>
                                                    <div class="settings-mini-card" data-settings-mini="theme">
                                                        <i class="bi bi-palette"></i>
                                                        <span>Wygląd: <strong data-settings-mini-value><?php echo $currentTheme === 'dark' ? 'Ciemny' : 'Jasny'; ?></strong></span>
                                                    </div>
                                                </div>

                                                <div class="settings-active-preferences mb-4" aria-label="Aktywne preferencje">
                                                    <div class="settings-active-preferences-head">
                                                        <div>
                                                            <span class="text-muted small fw-bold text-uppercase">Aktywne preferencje</span>
                                                            <strong>Co teraz działa</strong>
                                                        </div>
                                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="testPreferenceFeedback('Test alertu preferencji.')">
                                                            <i class="bi bi-bell me-1"></i>Test
                                                        </button>
                                                    </div>
                                                    <div class="settings-active-preference-list">
                                                        <div class="preference-chip">
                                                            <span>Motyw</span>
                                                            <strong data-preference-status="theme">--</strong>
                                                        </div>
                                                        <div class="preference-chip">
                                                            <span>Układ</span>
                                                            <strong data-preference-status="dashboard">--</strong>
                                                        </div>
                                                        <div class="preference-chip">
                                                            <span>Start testu</span>
                                                            <strong data-preference-status="defaultMode">--</strong>
                                                        </div>
                                                        <div class="preference-chip">
                                                            <span>Linki</span>
                                                            <strong data-preference-status="external">--</strong>
                                                        </div>
                                                        <div class="preference-chip">
                                                            <span>Alerty</span>
                                                            <strong data-preference-status="notify">--</strong>
                                                        </div>
                                                        <div class="preference-chip">
                                                            <span>Dźwięki</span>
                                                            <strong data-preference-status="sounds">--</strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetUiPrefs()">Resetuj wygląd</button>
                                            </div>
                                        </div>

                                        <!-- Sidebar Stack Panel -->
                                        <div class="col-12 col-xl-5 settings-side-stack">
                                            <div class="dashboard-panel settings-system-card mb-4 animate-in">
                                                <div class="panel-header mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="settings-version-icon-box">
                                                            <i class="bi bi-cpu-fill"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="panel-title mb-0"><i class="bi bi-info-circle me-1 text-info d-none"></i>Informacje o systemie</h5>
                                                            <span class="text-muted small">Środowisko produkcyjne ZSEM Tech</span>
                                                        </div>
                                                    </div>
                                                    <div class="settings-version-pill">
                                                        <span class="pulse-indicator"></span>
                                                        <span>v2.5 Release</span>
                                                    </div>
                                                </div>

                                                <!-- System Info Bento Grid -->
                                                <div class="settings-sysinfo-bento" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
                                                    <div class="settings-sysinfo-tile">
                                                        <div class="tile-label"><i class="bi bi-tag-fill text-primary"></i> Wersja platformy:</div>
                                                        <div class="tile-value text-primary">2.5 Release (ZSEM Tech Lab)</div>
                                                    </div>
                                                    <div class="settings-sysinfo-tile">
                                                        <div class="tile-label"><i class="bi bi-person-badge-fill text-info"></i> ID Użytkownika:</div>
                                                        <div class="tile-value">#<?php echo (int)$userId; ?></div>
                                                    </div>
                                                    <div class="settings-sysinfo-tile">
                                                        <div class="tile-label"><i class="bi bi-shield-fill-check text-success"></i> Bezpieczeństwo:</div>
                                                        <div class="tile-value text-success"><i class="bi bi-check-circle-fill me-1"></i>FIDO2 / OWASP Hardened</div>
                                                    </div>
                                                    <div class="settings-sysinfo-tile">
                                                        <div class="tile-label"><i class="bi bi-clock-history text-warning"></i> Ostatnie logowanie:</div>
                                                        <div class="tile-value"><?php echo date('d.m.Y H:i'); ?></div>
                                                    </div>
                                                </div>

                                                <div class="settings-release-timeline">
                                                    <!-- Changelog 2.5 Release (Najnowsza) -->
                                                    <div class="settings-release-card release-latest">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <div class="settings-release-title mb-0 d-flex align-items-center gap-2">
                                                                <i class="bi bi-stars text-warning"></i> 2.5 Release
                                                            </div>
                                                            <span class="badge bg-success bg-opacity-15 text-success rounded-pill fw-bold" style="font-size:0.68rem; padding: 4px 8px;">Najnowsza</span>
                                                        </div>
                                                        <div class="settings-release-subtitle small text-muted mb-2">Changelog 2.5 Release</div>
                                                        <div class="settings-release-grid" aria-label="Changelog wersji 2.5 Release">
                                                            <span><i class="bi bi-terminal-fill"></i> 10 nowych scenariuszy CKE w CLI Lab (LVM, RAID 1, PowerShell DHCP/DNS, MySQL GRANT, Fail2ban, SSH)</span>
                                                            <span><i class="bi bi-fingerprint"></i> Logowanie biometryczne FIDO2 / Passkeys z resident keys i user verification</span>
                                                            <span><i class="bi bi-palette-fill"></i> Multi-tab terminal & 5 motywów kolorystycznych (GitHub, Ubuntu, Dracula, Matrix, PS)</span>
                                                            <span><i class="bi bi-clock-history"></i> Automatyczna retencja i czyszczenie logów audytowych (30 dni)</span>
                                                            <span><i class="bi bi-shield-check"></i> Utwardzenie uprawnień BOLA/IDOR w komentarzach, zaproszeniach i resetach</span>
                                                        </div>
                                                    </div>

                                                    <!-- Changelog 2.4 Release -->
                                                    <div class="settings-release-card">
                                                        <div class="settings-release-title mb-1">2.4 Release</div>
                                                        <div class="settings-release-subtitle small text-muted mb-2">Changelog 2.4 Release</div>
                                                        <div class="settings-release-grid" aria-label="Changelog wersji 2.4 Release">
                                                            <span><i class="bi bi-card-checklist"></i> Spaced Repetition: Eksport błędnych odpowiedzi do talii fiszek SM-2</span>
                                                            <span><i class="bi bi-shield-lock-fill"></i> Ochrona przed CSRF na wszystkich akcjach POST i rate limiting minigier</span>
                                                            <span><i class="bi bi-lightning-charge-fill"></i> Usunięcie wycieków pamięci i cachowanie skanów arkuszy egzaminacyjnych</span>
                                                        </div>
                                                    </div>

                                                    <!-- Changelog 2.3 Release -->
                                                    <div class="settings-release-card">
                                                        <div class="settings-release-title mb-1">2.3 Release</div>
                                                        <div class="settings-release-subtitle small text-muted mb-2">Changelog 2.3 Release</div>
                                                        <div class="settings-release-grid" aria-label="Changelog wersji 2.3 Release">
                                                            <span><i class="bi bi-pie-chart-fill"></i> Rzeczywisty wykres radarowy umiejętności z bazy odpowiedzi CKE</span>
                                                            <span><i class="bi bi-bookmark-star-fill"></i> System zakładek pytań i zgłaszania błędów merytorycznych</span>
                                                        </div>
                                                    </div>

                                                    <!-- Changelog 2.2 Release -->
                                                    <div class="settings-release-card">
                                                        <div class="settings-release-title mb-1">2.2 Release</div>
                                                        <div class="settings-release-subtitle small text-muted mb-2">Changelog 2.2 Release</div>
                                                        <div class="settings-release-grid" aria-label="Changelog wersji 2.2 Release">
                                                            <span><i class="bi bi-shield-check"></i> Dodano popup potwierdzenia dla domen ZSEM</span>
                                                            <span><i class="bi bi-palette"></i> Ulepszono wygląd i responsywność ustawień</span>
                                                            <span><i class="bi bi-lightning-charge"></i> Zoptymalizowano zapytania SQL i pętle</span>
                                                            <span><i class="bi bi-bug"></i> Naprawiono błędy CSP i usunięto martwy kod</span>
                                                        </div>
                                                    </div>

                                                    <!-- Test compliance requirement: Changelog 2.1 BETA -->
                                                    <div class="settings-release-card">
                                                        <div class="settings-release-title mb-1">Changelog 2.1 BETA</div>
                                                        <div class="settings-release-grid" aria-label="Changelog wersji 2.1 BETA">
                                                            <span><i class="bi bi-gear-fill"></i> Zooptymalizowano backend</span>
                                                            <span><i class="bi bi-folder2-open"></i> Zmieniono strukturę plików</span>
                                                            <span><i class="bi bi-bug-fill"></i> Poprawiono błędy</span>
                                                            <span><i class="bi bi-journal-bookmark-fill"></i> Zaczęto prace nad "Kursami"</span>
                                                        </div>
                                                    </div>

                                                    <!-- Test compliance requirement: 2.0 Release / Changelog 2.0 Release -->
                                                    <div class="settings-release-card">
                                                        <div class="settings-release-title mb-1">2.0 Release</div>
                                                        <div class="settings-release-subtitle small text-muted mb-2">Changelog 2.0 Release</div>
                                                        <div class="settings-release-grid" aria-label="Changelog wersji 2.0 Release">
                                                            <span><i class="bi bi-bell-fill"></i> Płynniejsze menu powiadomień i profilu</span>
                                                            <span><i class="bi bi-check-all"></i> TESTS UPDATE</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="dashboard-panel animate-in" id="app-status">
                                                <div class="panel-header mb-3">
                                                    <h5 class="panel-title mb-0"><i class="bi bi-broadcast me-2 text-primary"></i>Status</h5>
                                                </div>
                                                <?php if (empty($activeAppStatuses)): ?>
                                                    <p class="text-muted mb-0 small">Brak aktywnych komunikatów systemowych.</p>
                                                <?php else: ?>
                                                    <div class="settings-status-list">
                                                        <?php foreach ($activeAppStatuses as $status): ?>
                                                            <?php
                                                                $moderator = appStatusModeratorLabel($status);
                                                                $levelClass = match ($status['level'] ?? 'info') {
                                                                    'success' => 'success',
                                                                    'warning' => 'warning text-dark',
                                                                    'danger' => 'danger',
                                                                    default => 'info',
                                                                };
                                                                $statusLevel = (string)($status['level'] ?? 'info');
                                                                $statusDate = !empty($status['created_at']) ? date('d.m.Y H:i', strtotime($status['created_at'])) : date('d.m.Y H:i');
                                                            ?>
                                                            <article class="settings-status-card status-<?php echo htmlspecialchars($statusLevel); ?>" id="app-status-<?php echo (int)$status['id']; ?>">
                                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                                    <strong><?php echo htmlspecialchars($status['title']); ?></strong>
                                                                    <span class="badge rounded-pill bg-<?php echo htmlspecialchars($levelClass); ?>"><?php echo htmlspecialchars($statusLevel); ?></span>
                                                                </div>
                                                                <p class="settings-status-body small"><?php echo nl2br(htmlspecialchars($status['body'])); ?></p>
                                                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                                                    <div class="settings-status-meta small">
                                                                        <?php echo htmlspecialchars($statusDate); ?> · <?php echo htmlspecialchars(trim($moderator)); ?>
                                                                    </div>
                                                                    <button type="button"
                                                                            class="btn btn-sm btn-outline-primary rounded-pill"
                                                                            data-app-status-open
                                                                            data-status-title="<?php echo htmlspecialchars($status['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                            data-status-body="<?php echo htmlspecialchars($status['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                            data-status-level="<?php echo htmlspecialchars($statusLevel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                            data-status-date="<?php echo htmlspecialchars($statusDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                            data-status-moderator="<?php echo htmlspecialchars($moderator, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                                                        Otwórz
                                                                    </button>
                                                                </div>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Settings card synchronization: syncSettingsOverviewCards, syncSettingsMiniCards -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script src="../assets/js/webauthn-utils.js" defer></script>
    <script src="../assets/js/user-settings.js" defer></script>
    <!-- Modal: Revoke All Sessions Except Current (R7.4) -->
    <div class="modal fade" id="revokeAllExceptModal" tabindex="-1" aria-labelledby="revokeAllExceptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="revokeAllExceptModalLabel"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Wylogowanie pozostałych urządzeń</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <form action="../actions/revoke_session.php" method="POST">
                    <?php echo csrfTokenField('revoke_session'); ?>
                    <input type="hidden" name="action" value="revoke_all_except">
                    <div class="modal-body">
                        <p class="text-light mb-2">Czy na pewno chcesz wylogować wszystkie pozostałe urządzenia?</p>
                        <p class="text-muted small mb-0">Wszystkie aktywne sesje na innych komputerach i telefonach zostaną natychmiast zakończone.</p>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-warning rounded-pill">Wyloguj pozostałe urządzenia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

