<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

startSecureSession();

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
$pageTitle = 'Kontakt - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        .contact-icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            transition: transform 0.3s ease, background 0.3s ease;
        }
        .contact-info-row:hover .contact-icon-box {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.25);
        }
        .map-card {
            border-radius: 1.5rem;
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .map-wrapper {
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
            min-height: 400px;
            border: 1px solid var(--border-color);
        }
    </style>
HTML;
$bodyAttributes = 'class="<?php echo htmlspecialchars($bodyClassStr); ?';
include '../includes/header.php';
?>">

    <div class="dashboard-layout">
        <?php if (isset($_SESSION['user_id'])) include '../includes/sidebar.php'; ?>

        <div class="main-container" style="<?php echo !isset($_SESSION['user_id']) ? 'margin-left: 0;' : ''; ?>">
            <?php if (isset($_SESSION['user_id'])) include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container py-4">
                    
                    <section class="welcome-card dashboard-hero mb-4 animate-in" style="overflow: hidden;">
                        <div class="dashboard-hero-inner">
                            <div class="hero-left" style="text-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);">
                                <div class="hero-rank-pill" style="border-color: rgba(255, 255, 255, 0.18); background: rgba(15, 23, 42, 0.35); color: #ffffff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                    <i class="bi bi-headset"></i>
                                    <span style="color: #ffffff; font-weight: 800;">Pomoc</span>
                                </div>
                                <h1 class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-headset me-2" aria-hidden="true"></i>Kontakt i wsparcie</h1>
                                <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Skontaktuj się z nami w razie pytań lub problemów technicznych.</p>
                            </div>
                            <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                                <i class="bi bi-envelope-at text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                            </div>
                        </div>
                    </section>

                    <div class="row g-4 align-items-stretch">
                        <!-- Contact Info Column -->
                        <div class="col-lg-5 col-xl-4">
                            <div class="welcome-card dashboard-hero h-100 p-0 animate-in" style="align-items: stretch; flex-direction: column;">
                                <div class="dashboard-hero-inner d-flex flex-column h-100 p-4 p-xl-5" style="width: 100%; z-index: 1;">
                                    <h3 class="fw-bold mb-3 text-white">ZSEM Tech</h3>
                                    <p class="mb-5 fs-6 text-white" style="opacity: 0.9;">
                                        Masz pytania dotyczące platformy? Chcesz zgłosić błąd lub zaproponować nową funkcjonalność? Jesteśmy do Twojej dyspozycji.
                                    </p>
                                    
                                    <div class="d-flex flex-column gap-4 mt-auto">
                                        <div class="d-flex align-items-center gap-3 contact-info-row">
                                            <div class="contact-icon-box">
                                                <i class="bi bi-envelope-at-fill text-white"></i>
                                            </div>
                                            <div>
                                                <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">E-mail do nas</div>
                                                <a href="mailto:zsemtech@zsem.edu.pl" class="text-white text-decoration-none fw-bold fs-5">zsemtech@zsem.edu.pl</a>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-3 contact-info-row">
                                            <div class="contact-icon-box">
                                                <i class="bi bi-building-fill text-white" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Dane identyfikacyjne</div>
                                                <div class="text-white fw-bold">Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</div>
                                                <div class="text-white small" style="opacity: 0.8;">Platforma edukacyjna ZSEM Tech</div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-3 contact-info-row">
                                            <div class="contact-icon-box">
                                                <i class="bi bi-geo-alt-fill text-white"></i>
                                            </div>
                                            <div>
                                                <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Lokalizacja</div>
                                                <div class="text-white fw-bold fs-5">Nowy Sącz, Polska</div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-3 contact-info-row">
                                            <div class="contact-icon-box">
                                                <i class="bi bi-clock-fill text-white"></i>
                                            </div>
                                            <div>
                                                <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Godziny pracy</div>
                                                <div class="text-white fw-bold fs-5">Pon - Pt: 7:00 - 17:00</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Column -->
                        <div class="col-lg-7 col-xl-8">
                            <div class="map-card h-100 d-flex flex-column animate-in" style="animation-delay: 0.1s;">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="fw-bold mb-0">Znajdź nas na mapie</h4>
                                    <a href="https://www.openstreetmap.org/?#map=19/49.609886/20.703389" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        Wyświetl większą mapę <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </div>
                                <div class="flex-grow-1 map-wrapper shadow-sm">
                                    <iframe 
                                        title="Mapa lokalizacji ZSEM Tech w Nowym Sączu"
                                        width="100%" 
                                        height="100%" 
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"
                                        src="https://www.openstreetmap.org/export/embed.html?bbox=20.701619088649753%2C49.60911094154455%2C20.70515960454941%2C49.610661273736355&amp;layer=mapnik" 
                                        style="border: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                                    </iframe>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>

