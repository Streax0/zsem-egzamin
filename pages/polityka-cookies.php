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
$pageTitle = 'Polityka cookies – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        /* badge-info-pill removed as it's replaced with hero-rank-pill */
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
                <a href="../index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Powrót</a>

                <section class="welcome-card dashboard-hero mb-4 animate-in" aria-labelledby="cookies-title" style="overflow: hidden;">
                    <div class="dashboard-hero-inner">
                        <div class="hero-left" style="text-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);">
                            <div class="hero-rank-pill" style="border-color: rgba(255, 255, 255, 0.18); background: rgba(15, 23, 42, 0.35); color: #ffffff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                <i class="bi bi-info-circle"></i>
                                <span style="color: #ffffff; font-weight: 800;">Przejrzystość</span>
                            </div>
                            <h1 id="cookies-title" class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-cookie me-2" aria-hidden="true"></i>Polityka Cookies</h1>
                            <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Zasady wykorzystywania plików cookies na platformie ZSEM Tech.</p>
                        </div>
                        <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                            <i class="bi bi-cookie text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                        </div>
                    </div>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3">1. Kategorie cookies</h2>
                    <ul>
                        <li><strong>Niezbędne</strong> – sesja, logowanie, bezpieczeństwo formularzy i zapamiętanie decyzji cookies.</li>
                        <li><strong>Preferencyjne</strong> – opcjonalne ustawienia motywu, rozmiaru tekstu, gęstości i widoku.</li>
                        <li><strong>Analityczne</strong> – obecnie brak aktywnych narzędzi analitycznych.</li>
                        <li><strong>Marketingowe/social media</strong> – obecnie brak pikseli reklamowych i trackerów social media.</li>
                    </ul>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3">2. Tabela cookies</h2>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Nazwa</th>
                                    <th>Dostawca</th>
                                    <th>Cel</th>
                                    <th>Czas życia</th>
                                    <th>Kategoria</th>
                                    <th>Third-party</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>PHPSESSID</td><td>ZSEM Tech</td><td>Utrzymanie sesji użytkownika</td><td>sesja / do 1h</td><td>niezbędne</td><td>nie</td></tr>
                                <tr><td>cookie_consent_v2</td><td>ZSEM Tech</td><td>Dowód zgody: timestamp, wersja, kategorie, źródło decyzji</td><td>183 dni</td><td>niezbędne</td><td>nie</td></tr>
                                <tr><td>cookie_consent</td><td>ZSEM Tech</td><td>Kompatybilność starego mechanizmu zgody</td><td>183 dni</td><td>niezbędne</td><td>nie</td></tr>
                                <tr><td>user_theme</td><td>ZSEM Tech</td><td>Jasny/ciemny motyw</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>user_font_size</td><td>ZSEM Tech</td><td>Rozmiar tekstu</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>user_density</td><td>ZSEM Tech</td><td>Gęstość interfejsu</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>user_accent</td><td>ZSEM Tech</td><td>Kolor akcentu interfejsu</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>reduce_motion</td><td>ZSEM Tech</td><td>Ograniczenie animacji</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>dashboard_view</td><td>ZSEM Tech</td><td>Preferowany układ dashboardu</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>default_test_mode</td><td>ZSEM Tech</td><td>Domyślny tryb startu testu</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>external_new_tab</td><td>ZSEM Tech</td><td>Preferencja otwierania linków zewnętrznych</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>hide_help_center</td><td>ZSEM Tech</td><td>Ukrycie pływającego centrum pomocy</td><td>183 dni</td><td>preferencyjne</td><td>nie</td></tr>
                                <tr><td>remember_me</td><td>ZSEM Tech</td><td>Opcjonalne zapamiętanie logowania, jeśli aktywne w aplikacji</td><td>zgodnie z ustawieniem aplikacji</td><td>funkcjonalne</td><td>nie</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3">3. Zmiana zgody</h2>
                    <p>Użytkownik może wrócić do ustawień w dowolnym momencie. Wycofanie zgody usuwa cookies preferencyjne z przeglądarki.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-cookie-settings>Otwórz ustawienia cookies</button>
                </section>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/cookie_consent.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

