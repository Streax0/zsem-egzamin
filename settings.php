<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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
$openExternalNewTab = ($_COOKIE['external_new_tab'] ?? '1') === '1';
$hideHelpCenter = ($_COOKIE['hide_help_center'] ?? '0') === '1';
$activeAppStatuses = getAppStatuses($pdo, true, 2);

$flashMsg = getSessionMessage();

// Fetch account and privacy settings
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $userSettings['username'] ?? ($_SESSION['username'] ?? '');
$email = $userSettings['email'] ?? '';
$classYear = $userSettings['class_year'] ?? '';
$classSuffix = $userSettings['class_suffix'] ?? '';
$settingsAvatarSrc = userAvatarSrc($userSettings['avatar_path'] ?? '');
$profilePublic = (bool)($userSettings['profile_public'] ?? 1);
$statsPublic = (bool)($userSettings['stats_public'] ?? 1);
$allowFriendRequests = (bool)($userSettings['allow_friend_requests'] ?? 1);
$searchable = (bool)($userSettings['searchable'] ?? 1);
$allowProfileComments = (bool)($userSettings['allow_profile_comments'] ?? 1);
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
$settingsHealth = [
    ['key' => 'profile', 'icon' => 'bi-person-check', 'label' => 'Profil', 'value' => ($username !== '' && $email !== '') ? 'OK' : 'Uzupełnij'],
    ['key' => 'security', 'icon' => 'bi-shield-lock', 'label' => 'Bezpieczeństwo', 'value' => $mfaEnabled ? 'MFA aktywne' : ($canUseMfa ? 'MFA opcjonalne' : 'Hasło')],
    ['key' => 'theme', 'icon' => 'bi-palette', 'label' => 'Motyw', 'value' => $currentTheme === 'dark' ? 'Ciemny' : 'Jasny'],
    ['key' => 'density', 'icon' => 'bi-sliders', 'label' => 'Interfejs', 'value' => $currentDensity === 'compact' ? 'Kompakt' : 'Wygodny'],
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia - System Testów</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <style>
        .settings-status-list {
            display: grid;
            gap: .85rem;
        }
        .settings-overview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
        }
        .settings-overview-card {
            display: flex;
            align-items: center;
            gap: .75rem;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 8px;
            padding: .85rem;
            background: linear-gradient(180deg, #fff, #f8fafc);
            min-height: 78px;
        }
        .settings-overview-card i {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: rgba(37, 99, 235, .10);
            color: #2563eb;
            font-size: 1.1rem;
            flex: 0 0 auto;
        }
        .settings-overview-card span {
            display: block;
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
        }
        .settings-overview-card strong {
            display: block;
            color: #0f172a;
            font-size: .95rem;
            line-height: 1.2;
        }
        .settings-status-card {
            border: 1px solid rgba(37, 99, 235, .14);
            border-radius: 8px;
            padding: 1rem;
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
        }
        .settings-status-card.status-danger {
            border-color: rgba(239, 68, 68, .22);
            background: linear-gradient(135deg, #fff7f7, #ffffff);
        }
        .settings-status-card.status-warning {
            border-color: rgba(245, 158, 11, .28);
            background: linear-gradient(135deg, #fffbeb, #ffffff);
        }
        .settings-status-card.status-success {
            border-color: rgba(16, 185, 129, .24);
            background: linear-gradient(135deg, #ecfdf5, #ffffff);
        }
        .settings-status-body {
            color: #1e293b;
            line-height: 1.55;
            margin-bottom: .75rem;
            overflow-wrap: anywhere;
        }
        .settings-status-meta {
            color: #64748b;
        }
        .settings-release-title {
            color: #64748b;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .settings-release-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: .5rem;
        }
        .settings-release-grid span {
            display: flex;
            align-items: center;
            gap: .45rem;
            border: 1px solid rgba(59, 130, 246, .18);
            border-radius: 8px;
            padding: .55rem .65rem;
            background: #f8fafc;
            color: #1e293b;
            font-size: .82rem;
            font-weight: 700;
        }
        .settings-release-grid span i {
            color: #2563eb;
            flex: 0 0 auto;
        }
        .settings-preference-stack {
            display: grid;
            gap: 1rem;
        }
        .settings-preference-box {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 8px;
            padding: .85rem;
            background: rgba(248, 250, 252, .72);
        }
        .settings-switch-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: .55rem;
        }
        .settings-switch-grid .form-check {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 8px;
            padding: .65rem .8rem .65rem 2.75rem;
            background: rgba(248, 250, 252, .72);
            margin: 0 !important;
        }
        body.dark-mode .settings-overview-card,
        body.dark-mode .settings-preference-box,
        body.dark-mode .settings-switch-grid .form-check {
            background: rgba(15, 23, 42, .82);
            border-color: rgba(148, 163, 184, .24);
            color: #e5e7eb;
        }
        body.dark-mode .settings-overview-card strong {
            color: #f8fafc;
        }
        body.dark-mode .settings-overview-card span {
            color: #94a3b8;
        }
        body.dark-mode .settings-overview-card i {
            background: rgba(96, 165, 250, .16);
            color: #bfdbfe;
        }
        body.dark-mode .settings-status-card {
            background: #111827;
            border-color: rgba(148, 163, 184, .24);
            box-shadow: none;
        }
        body.dark-mode .settings-release-grid span {
            background: rgba(15, 23, 42, .82);
            border-color: rgba(96, 165, 250, .22);
            color: #f8fafc;
        }
        body.dark-mode .settings-release-grid span i {
            color: #93c5fd;
        }
        body.dark-mode .settings-release-title {
            color: #94a3b8;
        }
        body.dark-mode .settings-status-body {
            color: #f8fafc;
        }
        @media (max-width: 991.98px) {
            .settings-overview-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 575.98px) {
            .settings-overview-grid {
                grid-template-columns: 1fr;
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
                        <!-- Profile Settings -->
                        <div class="col-lg-8">
                            <div class="dashboard-panel mb-4 animate-in">
                                <div class="panel-header mb-4">
                                    <h5 class="panel-title mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Dane podstawowe</h5>
                                </div>
                                <form action="actions/update_profile.php" method="POST" enctype="multipart/form-data">
                                    <?php echo csrfTokenField(); ?>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nazwa użytkownika</label>
                                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" minlength="3" maxlength="16" pattern="[A-Za-z0-9_.-]{3,16}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Adres E-mail</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Klasa</label>
                                            <select name="class_year" class="form-select">
                                                <option value="" <?php echo $classYear === null || $classYear === '' ? 'selected' : ''; ?>>Nie dotyczy</option>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo (string)$classYear === (string)$i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Oznaczenie klasy</label>
                                            <input type="text" name="class_suffix" class="form-control" maxlength="2" pattern="[A-Za-z]{0,2}" value="<?php echo htmlspecialchars($classSuffix); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Zdjęcie profilowe</label>
                                            <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                                            <div class="form-text">JPG/PNG/WebP, maks. 2 MB przed wysłaniem. Na serwerze avatar jest filtrowany, kompresowany do WebP i nie przekracza 25 KB.</div>
                                        </div>
                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-primary px-4">
                                                Zapisz zmiany
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <?php if ($settingsAvatarSrc): ?>
                                <form action="actions/update_profile.php" method="POST" class="mt-3" onsubmit="return appConfirmSubmit(this, 'Usunąć zdjęcie profilowe?')">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="return_to" value="settings.php">
                                    <input type="hidden" name="action" value="delete_avatar">
                                    <button type="submit" class="btn btn-outline-danger px-4">
                                        <i class="bi bi-trash3 me-1"></i>Usuń zdjęcie profilowe
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                            <div class="dashboard-panel animate-in" style="animation-delay: 0.1s;">
                                <div class="panel-header mb-4">
                                    <h5 class="panel-title mb-0"><i class="bi bi-shield-lock me-2 text-danger"></i>Zmiana hasła</h5>
                                </div>
                                <form action="actions/change_password.php" method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="return_to" value="settings.php">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Aktualne hasło</label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nowe hasło</label>
                                            <input type="password" name="new_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Powtórz nowe hasło</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-outline-danger px-4">
                                                Zmień hasło
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <form action="actions/logout_all_sessions.php" method="POST" class="mt-3">
                                    <?= csrfTokenField('logout_all') ?>
                                    <input type="hidden" name="include_current" value="1">
                                    <button type="submit" class="btn btn-outline-warning px-4">
                                        <i class="bi bi-box-arrow-right me-1"></i>Wyloguj wszystkie sesje
                                    </button>
                                </form>
                            </div>

                            <div class="dashboard-panel animate-in" style="animation-delay: 0.12s; margin-top: 1.5rem;">
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
                                    <a href="mfa.php" class="btn btn-outline-primary rounded-pill px-4">
                                        <i class="bi bi-shield-lock me-1"></i><?php echo $mfaEnabled ? 'Sprawdź kody' : 'Włącz 2FA'; ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="dashboard-panel animate-in" style="animation-delay: 0.15s; margin-top: 1.5rem;">
                                <div class="panel-header mb-4">
                                    <h5 class="panel-title mb-0"><i class="bi bi-eye-slash me-2 text-warning"></i>Ustawienia prywatności</h5>
                                </div>
                                <form action="actions/update_privacy.php" method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="profilePublic" name="profile_public" value="1" <?php echo $profilePublic ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="profilePublic">Profil publiczny (widoczny dla innych)</label>
                                            </div>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="statsPublic" name="stats_public" value="1" <?php echo $statsPublic ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="statsPublic">Statystyki publiczne</label>
                                            </div>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="allowProfileComments" name="allow_profile_comments" value="1" <?php echo $allowProfileComments ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allowProfileComments">Komentarze pod profilem</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="allowFriendRequests" name="allow_friend_requests" value="1" <?php echo $allowFriendRequests ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allowFriendRequests">Akceptuj zaproszenia do znajomych</label>
                                            </div>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="searchable" name="searchable" value="1" <?php echo $searchable ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="searchable">Profil widoczny w wyszukiwarce</label>
                                            </div>
                                            <?php if ($role === 'teacher'): ?>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="rankingVisible" name="ranking_visible" value="1" <?php echo $rankingVisible ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="rankingVisible">Biorę udział w rankingu XP</label>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <button type="submit" class="btn btn-primary px-4">
                                                Zapisz ustawienia prywatności
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Danger Zone -->
                            <div class="dashboard-panel border-danger border-opacity-25 mt-4 animate-in" style="animation-delay: 0.2s; background: rgba(239, 68, 68, 0.02);">
                                <div class="panel-header mb-4">
                                    <h5 class="panel-title mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Strefa niebezpieczna</h5>
                                </div>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h6 class="fw-bold mb-1">Zresetuj postępy</h6>
                                            <p class="text-muted small">Wszystkie Twoje wyniki, statystyki i XP zostaną usunięte. Konto pozostanie aktywne.</p>
                                        </div>
                                        <form action="actions/reset_progress.php" method="POST" onsubmit="return appConfirmSubmit(this, 'CZY NA PEWNO? Ta operacja jest nieodwracalna i usunie CAŁĄ Twoją historię nauki.')">
                                            <?php echo csrfTokenField(); ?>
                                            <button type="submit" class="btn btn-outline-warning btn-sm">Resetuj mój progres</button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h6 class="fw-bold mb-1">Usuń konto</h6>
                                            <p class="text-muted small">Trwale usuń swoje konto oraz wszystkie dane z serwera (Zgodnie z RODO). Tej operacji nie można cofnąć.</p>
                                        </div>
                                        <form action="actions/delete_account.php" method="POST" onsubmit="return appConfirmSubmit(this, 'UWAGA! Czy na pewno chcesz TRWALE USUNĄĆ swoje konto? Stracisz dostęp do wszystkich funkcji.')">
                                            <?php echo csrfTokenField(); ?>
                                            <button type="submit" class="btn btn-danger btn-sm">Usuń konto na zawsze</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- App Preferences -->
                        <div class="col-lg-4">
                            <div class="dashboard-panel mb-4 animate-in" style="animation-delay: 0.2s;">
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
                                    <label class="form-label d-block" for="densitySelect">Gęstość interfejsu</label>
                                    <select class="form-select" id="densitySelect" onchange="updateDensitySetting(this.value); applyUiPreferences();">
                                        <option value="comfortable" <?php echo $currentDensity === 'comfortable' ? 'selected' : ''; ?>>Wygodna</option>
                                        <option value="compact" <?php echo $currentDensity === 'compact' ? 'selected' : ''; ?>>Kompaktowa</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label d-block" for="accentColor">Kolor akcentu</label>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <input type="color" class="form-control form-control-color" id="accentColor" value="<?php echo htmlspecialchars($currentAccent); ?>" onchange="updateAccentSetting(this.value); applyUiPreferences();">
                                        <button type="button" class="accent-dot" style="--dot:#3b82f6" onclick="pickAccent('#3b82f6')" aria-label="Niebieski"></button>
                                        <button type="button" class="accent-dot" style="--dot:#10b981" onclick="pickAccent('#10b981')" aria-label="Zielony"></button>
                                        <button type="button" class="accent-dot" style="--dot:#8b5cf6" onclick="pickAccent('#8b5cf6')" aria-label="Fioletowy"></button>
                                        <button type="button" class="accent-dot" style="--dot:#f59e0b" onclick="pickAccent('#f59e0b')" aria-label="Pomarańczowy"></button>
                                    </div>
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
                                
                                <div class="form-check form-switch mb-3">
                                     <input class="form-check-input" type="checkbox" id="helpCenterSwitch" <?php echo $hideHelpCenter ? 'checked' : ''; ?> onchange="updateHelpCenterSetting(this.checked)">
                                     <label class="form-check-label" for="helpCenterSwitch">Ukryj Centrum Pomocy (pływający przycisk)</label>
                                </div>

                                </div>

                                <div class="settings-mini-grid mb-4">
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
                                        <span>Motyw <strong data-preference-status="theme">--</strong></span>
                                        <span>Układ <strong data-preference-status="dashboard">--</strong></span>
                                        <span>Start testu <strong data-preference-status="defaultMode">--</strong></span>
                                        <span>Linki <strong data-preference-status="external">--</strong></span>
                                        <span>Pomoc <strong data-preference-status="help">--</strong></span>
                                        <span>Alerty <strong data-preference-status="notify">--</strong></span>
                                        <span>Dźwięki <strong data-preference-status="sounds">--</strong></span>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetUiPrefs()">Resetuj wygląd</button>
                            </div>

                            <div class="dashboard-panel animate-in" style="animation-delay: 0.3s;">
                                <div class="panel-header mb-3">
                                    <h5 class="panel-title mb-0"><i class="bi bi-info-circle me-2 text-info"></i>Informacje</h5>
                                </div>
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Wersja aplikacji:</span>
                                        <span class="fw-bold">1.9 BETA</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">ID Użytkownika:</span>
                                        <span class="fw-bold">#<?php echo $userId; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Ostatnie logowanie:</span>
                                        <span class="fw-bold"><?php echo date('d.m.Y H:i'); ?></span>
                                    </div>
                                </div>
                                <div class="settings-release-title mt-3 mb-2">Changelog 1.9 Beta</div>
                                <div class="settings-release-grid" aria-label="Changelog wersji 1.9 Beta">
                                    <span><i class="bi bi-bell"></i> Płynniejsze menu powiadomień i profilu</span>
                                    <span><i class="bi bi-speedometer2"></i> Stabilniejsze odświeżanie topbara</span>
                                    <span><i class="bi bi-shield-check"></i> Dalsze poprawki sesji i formularzy</span>
                                </div>
                            </div>

                            <div class="dashboard-panel animate-in" id="app-status" style="animation-delay: 0.34s;">
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
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script>
    function preferenceCookiesAllowed() {
        const getCookie = (name) => document.cookie.split('; ').find(row => row.startsWith(name + '='))?.slice(name.length + 1) || '';
        try {
            const consent = getCookie('cookie_consent_v2');
            if (consent) {
                const parsed = JSON.parse(decodeURIComponent(consent));
                return !!(parsed.categories && parsed.categories.preferences);
            }
        } catch (error) {
            return false;
        }
        return getCookie('cookie_consent') === 'accepted';
    }
    const dashboardLabels = { balanced: 'Zbalansowany', learning: 'Nauka', compact: 'Kompakt' };
    const defaultModeLabels = { exam: 'Egzamin', practice: 'Ćwiczenia', single: 'Jedno pytanie' };
    function setPreferenceStatus(key, value) {
        const target = document.querySelector(`[data-preference-status="${key}"]`);
        if (target) target.textContent = value;
    }
    function syncSettingsMiniCards() {
        const notify = document.querySelector('[data-settings-mini="notify"] [data-settings-mini-value]');
        const layout = document.querySelector('[data-settings-mini="layout"] [data-settings-mini-value]');
        const theme = document.querySelector('[data-settings-mini="theme"] [data-settings-mini-value]');
        const notifyEnabled = localStorage.getItem('notify_new_tests') === '1';
        const soundsEnabled = localStorage.getItem('ui_sounds') === '1';
        const dashboard = document.getElementById('dashboardView')?.value || readPreference('dashboard_view', 'balanced');
        const defaultMode = document.getElementById('defaultTestMode')?.value || readPreference('default_test_mode', 'exam');
        const external = document.getElementById('externalTabSwitch')?.checked;
        const helpHidden = document.getElementById('helpCenterSwitch')?.checked;
        const themeValue = document.body.classList.contains('dark-mode') ? 'Ciemny' : 'Jasny';
        if (notify) notify.textContent = notifyEnabled ? 'Włączone' : 'Wyłączone';
        if (layout) layout.textContent = dashboardLabels[dashboard] || 'Zbalansowany';
        if (theme) theme.textContent = themeValue;
        setPreferenceStatus('theme', themeValue);
        setPreferenceStatus('dashboard', dashboardLabels[dashboard] || dashboard);
        setPreferenceStatus('defaultMode', defaultModeLabels[defaultMode] || defaultMode);
        setPreferenceStatus('external', external ? 'Nowa karta' : 'Ta sama karta');
        setPreferenceStatus('help', helpHidden ? 'Ukryta' : 'Widoczna');
        setPreferenceStatus('notify', notifyEnabled ? 'Włączone' : 'Wyłączone');
        setPreferenceStatus('sounds', soundsEnabled ? 'Włączone' : 'Wyłączone');
    }
    window.syncSettingsPreferencePanel = syncSettingsMiniCards;
    function syncSettingsOverviewCards() {
        const setOverview = (key, value) => {
            const target = document.querySelector(`[data-settings-overview="${key}"] [data-settings-overview-value]`);
            if (target) target.textContent = value;
        };
        const themeValue = document.getElementById('themeSelect')?.value === 'dark' ? 'Ciemny' : 'Jasny';
        const densityValue = document.getElementById('densitySelect')?.value === 'compact' ? 'Kompakt' : 'Wygodny';
        setOverview('theme', themeValue);
        setOverview('density', densityValue);
    }
    document.addEventListener('DOMContentLoaded', () => {
        syncSettingsMiniCards();
        syncSettingsOverviewCards();
        document.querySelectorAll('#dashboardView, #defaultTestMode, #themeSelect, #densitySelect, #notifySwitch, #soundsSwitch, #externalTabSwitch, #helpCenterSwitch, #motionSwitch').forEach((el) => {
            el.addEventListener('change', () => setTimeout(() => {
                applyUiPreferences();
                syncSettingsMiniCards();
                syncSettingsOverviewCards();
            }, 40));
        });
    });

    function setPreferenceCookie(name, value) {
        if (window.setUiPreference) {
            window.setUiPreference(name, value);
            return;
        }
        try { localStorage.setItem(name, value); } catch (error) {}
        if (!preferenceCookiesAllowed()) return;
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=15811200; SameSite=Lax${secure}`;
    }
    function readPreference(name, fallback) {
        if (window.getUiPreference) return window.getUiPreference(name, fallback);
        const cookie = document.cookie.split('; ').find(row => row.startsWith(name + '='))?.slice(name.length + 1);
        try {
            return cookie ? decodeURIComponent(cookie) : (localStorage.getItem(name) || fallback);
        } catch (error) {
            return cookie ? decodeURIComponent(cookie) : fallback;
        }
    }
    function applyUiPreferences() {
        if (window.applyStoredUiPreferences) {
            window.applyStoredUiPreferences();
        }
        syncSettingsMiniCards();
        syncSettingsOverviewCards();
    }
    function pickAccent(color) {
        const input = document.getElementById('accentColor');
        if (input) input.value = color;
        updateAccentSetting(color);
        applyUiPreferences();
    }
    function resetUiPrefs() {
        ['user_density','user_accent','reduce_motion','user_font_size','user_theme','dashboard_view','default_test_mode','external_new_tab','hide_help_center'].forEach(n => {
            const secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `${n}=; path=/; max-age=0; SameSite=Lax${secure}`;
            try { localStorage.removeItem(n); } catch (error) {}
        });
        localStorage.removeItem('notify_new_tests');
        localStorage.removeItem('ui_sounds');
        window.appNotice?.('Preferencje zresetowane.', 'secondary');
        location.reload();
    }
    function syncPreferenceControls() {
        const font = readPreference('user_font_size', '16');
        const fontIds = { '14': 'fontSmall', '16': 'fontMedium', '18': 'fontLarge' };
        const fontInput = document.getElementById(fontIds[font] || 'fontMedium');
        if (fontInput) fontInput.checked = true;

        const theme = readPreference('user_theme', 'light');
        const themeSelect = document.getElementById('themeSelect');
        if (themeSelect) themeSelect.value = ['light', 'dark'].includes(theme) ? theme : 'light';

        const density = readPreference('user_density', 'comfortable');
        const densitySelect = document.getElementById('densitySelect');
        if (densitySelect) densitySelect.value = density === 'compact' ? 'compact' : 'comfortable';

        const accent = readPreference('user_accent', '#3b82f6');
        const accentInput = document.getElementById('accentColor');
        if (accentInput && /^#[0-9a-fA-F]{6}$/.test(accent)) accentInput.value = accent;

        const dashboard = readPreference('dashboard_view', 'balanced');
        const dashboardSelect = document.getElementById('dashboardView');
        if (dashboardSelect && ['balanced', 'learning', 'compact'].includes(dashboard)) dashboardSelect.value = dashboard;

        const defaultMode = readPreference('default_test_mode', 'exam');
        const defaultModeSelect = document.getElementById('defaultTestMode');
        if (defaultModeSelect && ['exam', 'practice', 'single'].includes(defaultMode)) defaultModeSelect.value = defaultMode;

        const motion = document.getElementById('motionSwitch');
        if (motion) motion.checked = readPreference('reduce_motion', '0') === '1';
        const external = document.getElementById('externalTabSwitch');
        if (external) external.checked = readPreference('external_new_tab', '1') === '1';
        const help = document.getElementById('helpCenterSwitch');
        if (help) help.checked = readPreference('hide_help_center', '0') === '1';
    }
    document.addEventListener('DOMContentLoaded', () => {
        syncPreferenceControls();
        const notify = document.getElementById('notifySwitch');
        const sounds = document.getElementById('soundsSwitch');
        if (notify) notify.checked = localStorage.getItem('notify_new_tests') === '1';
        if (sounds) sounds.checked = localStorage.getItem('ui_sounds') === '1';
        applyUiPreferences();
        syncSettingsOverviewCards();
    });
    </script>
</body>
</html>
