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
$pageTitle = 'Współpraca - ZSEM Tech';
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
                                <i class="bi bi-people-fill"></i>
                                <span style="color: #ffffff; font-weight: 800;">Nauczyciele</span>
                            </div>
                            <h1 class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-briefcase-fill me-2"></i>Współpraca (Dla Nauczycieli ZSEM)</h1>
                            <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Jeśli jesteś nauczycielem przedmiotów zawodowych lub informatycznych w ZSEM i chciałbyś pomóc rozwijać platformę, zapraszamy do kontaktu!</p>
                        </div>
                        <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                            <i class="bi bi-people-fill text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                        </div>
                    </div>
                </section>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-journal-code me-2"></i>Materiały edukacyjne</h2>
                    <p>Można zgłaszać propozycje pytań, arkuszy, poradników, opisów kwalifikacji i przykładów zadań. Materiały są weryfikowane pod kątem poprawności, języka, bezpieczeństwa oraz zgodności z zakresem nauki.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-building me-2"></i>Partnerzy i scenariusze praktyczne</h2>
                    <p>Współpraca może obejmować warsztaty, konsultacje merytoryczne oraz zadania oparte o realistyczne sytuacje IT. Priorytetem jest wartość edukacyjna, nie promocja ani sprzedaż dostępu.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-shield-check me-2"></i>Bezpieczeństwo i jakość</h2>
                    <p>Zgłoszenia błędów technicznych, luk bezpieczeństwa, nieścisłości w materiałach oraz problemów z dostępnością są traktowane priorytetowo. Najlepsze zgłoszenie zawiera adres strony, kroki odtworzenia i oczekiwany rezultat.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-envelope me-2"></i>Kontakt</h2>
                    <p class="mb-0">Napisz na <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a> albo użyj formularza kontaktowego.</p>
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

