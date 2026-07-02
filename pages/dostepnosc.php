<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
$showSidebar = true;

// Get current UI preferences from cookies for server-side theme rendering
$currentTheme = $_COOKIE['user_theme'] ?? 'light';
$currentFontSize = $_COOKIE['user_font_size'] ?? '16';
$currentDensity = $_COOKIE['user_density'] ?? 'comfortable';
$currentAccent = $_COOKIE['user_accent'] ?? '#3b82f6';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $currentAccent)) {
    $currentAccent = '#3b82f6';
}
$reduceMotion = ($_COOKIE['reduce_motion'] ?? '0') === '1';
$dashboardView = $_COOKIE['dashboard_view'] ?? 'balanced';
$welcomeBannerStyle = $_COOKIE['welcome_banner_style'] ?? 'gradient';

$bodyClasses = [];
$bodyClasses[] = ($currentTheme === 'dark') ? 'dark-mode' : 'light-mode';
if ($currentDensity === 'compact') {
    $bodyClasses[] = 'ui-compact';
}
if ($reduceMotion) {
    $bodyClasses[] = 'reduce-motion';
}
$bodyClasses[] = 'dashboard-view-' . (in_array($dashboardView, ['balanced', 'learning', 'compact']) ? $dashboardView : 'balanced');
$bodyClasses[] = 'welcome-style-' . (in_array($welcomeBannerStyle, ['gradient', 'pure', 'aurora', 'glass']) ? $welcomeBannerStyle : 'gradient');
$bodyClassStr = implode(' ', $bodyClasses);
?>
<?php
$pageTitle = 'Dostępność - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
    </style>
HTML;
$bodyAttributes = 'class="<?php echo htmlspecialchars($bodyClassStr); ?';
include '../includes/header.php';
?>">
<div class="dashboard-layout">
    <?php if ($showSidebar) include '../includes/sidebar.php'; ?>
    <div class="main-container" style="<?php echo !$showSidebar ? 'margin-left: 0;' : ''; ?>">
        <?php if ($showSidebar) include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container py-4" style="max-width: 960px;">
                <a href="../index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2"></i>Powrót</a>

                <section class="welcome-card dashboard-hero mb-4 animate-in" style="overflow: hidden;">
                    <div class="dashboard-hero-inner">
                        <div class="hero-left" style="text-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);">
                            <div class="hero-rank-pill" style="border-color: rgba(255, 255, 255, 0.18); background: rgba(15, 23, 42, 0.35); color: #ffffff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                <i class="bi bi-universal-access-circle"></i>
                                <span style="color: #ffffff; font-weight: 800;">Dostępność</span>
                            </div>
                            <h1 class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-universal-access-circle me-2"></i>Deklaracja Dostępności</h1>
                            <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Staramy się, aby ZSEM Tech był dostępny dla wszystkich.</p>
                        </div>
                        <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                            <i class="bi bi-universal-access text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                        </div>
                    </div>
                </section>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-check2-circle me-2"></i>Standard</h2>
                    <p>Stosujemy semantyczny HTML, etykiety formularzy, widoczny fokus, tekst alternatywny obrazów i responsywny układ.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-keyboard me-2"></i>Obsługa</h2>
                    <p>Najważniejsze ekrany można obsługiwać klawiaturą. Przyciski i pola formularzy mają etykiety, a elementy interaktywne otrzymują widoczny fokus.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Znane ograniczenia</h2>
                    <p>Część starszych ekranów platformy nadal jest upraszczana pod kątem kontrastu i obsługi klawiaturą.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-envelope me-2"></i>Kontakt</h2>
                    <p class="mb-0">Uwagi dotyczące dostępności można zgłaszać przez formularz kontaktowy albo e-mail: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>.</p>
                </div>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/cookie_consent.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

