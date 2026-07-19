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
$pageTitle = 'Regulamin Użytkowania – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        /* badge-rodo removed as it's replaced with hero-rank-pill */
    </style>
HTML;
include '../includes/header.php';
?>
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
                                <i class="bi bi-file-earmark-text"></i>
                                <span style="color: #ffffff; font-weight: 800;">Regulamin</span>
                            </div>
                            <h1 class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-journal-bookmark-fill me-2"></i>Regulamin Użytkowania</h1>
                            <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Poniższe zapisy stanowią oficjalny regulamin korzystania z platformy egzaminacyjnej <strong>ZSEM Tech</strong>.</p>
                        </div>
                        <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                            <i class="bi bi-journal-text text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                        </div>
                    </div>
                </section>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-info-circle me-2"></i>1. Postanowienia Ogólne</h2>
                    <p>1.1. Platforma ZSEM Tech (zsem-egzamin.online) jest narzędziem edukacyjnym służącym do weryfikacji wiedzy, przygotowania do egzaminów zawodowych oraz przeprowadzania sprawdzianów online.</p>
                    <p>1.2. Właścicielami i autorami platformy są Michał Michalik oraz Damian Podgórski. Platforma jest udostępniana ZSEM, szkolnej administracji i prowadzącym jako projekt szkolny oraz narzędzie edukacyjne.</p>
                    <p>1.3. Rejestracja i korzystanie z platformy oznaczają pełną akceptację niniejszego regulaminu.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-person-badge me-2"></i>2. Konta użytkowników</h2>
                    <p>Platforma udostępnia konta osobom korzystającym z materiałów edukacyjnych oraz osobom prowadzącym zajęcia. Zakres widocznych funkcji zależy od typu konta i celu korzystania z platformy.</p>
                    <p class="mt-3">2.1. Konto jest imienne i przypisane do jednej osoby. Udostępnianie danych logowania osobom trzecim jest surowo zabronione.</p>
                    <p>2.2. Użytkownik ponosi pełną odpowiedzialność za wszelkie działania wykonane za pośrednictwem jego konta.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-pencil-square me-2"></i>3. Zasady Rozwiązywania Egzaminów i Sprawdzianów</h2>
                    <p>Podczas oficjalnych sprawdzianów organizowanych przez nauczycieli obowiązują rygorystyczne zasady uczciwości:</p>
                    <ul>
                        <li>Zabronione jest korzystanie z niedozwolonych pomocy naukowych, skryptów lub notatek, o ile prowadzący nie postanowi inaczej.</li>
                        <li>Zabronione jest kontaktowanie się z innymi uczestnikami sprawdzianu w celu wymiany odpowiedzi.</li>
                        <li>Platforma może stosować ogólne zabezpieczenia uczciwości sprawdzianu i oznaczać nietypowe zdarzenia do weryfikacji.</li>
                        <li>W przypadku naruszenia zasad prowadzący może unieważnić wynik albo zakończyć sprawdzian zgodnie z zasadami zajęć.</li>
                    </ul>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-database me-2"></i>4. Baza Pytań i Własność Intelektualna</h2>
                    <p>4.1. Materiały udostępniane na platformie stanowią własność uprawnionych podmiotów albo zostały wykorzystane w celach edukacyjnych zgodnie z właściwymi zasadami.</p>
                    <p>4.2. Pełne prawa autorskie do autorskich elementów platformy ZSEM Tech należą do twórców: Damian Podgórski oraz Michał Michalik. Szkoła nie nabywa tych praw przez samo korzystanie z platformy.</p>
                    <p>4.3. ZSEM i upoważniona administracja szkolna mogą korzystać z platformy w zakresie potrzebnym do prowadzenia projektu szkolnego, obsługi zajęć i administracji edukacyjnej.</p>
                    <p>4.4. Kopiowanie, rozpowszechnianie lub komercyjne wykorzystywanie treści zawartych na platformie bez właściwej zgody jest zabronione.</p>
                    <p>4.5. Projekt ma charakter edukacyjny i niekomercyjny; platforma nie jest prowadzona w celu sprzedaży dostępu do materiałów ani generowania zysku.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-people me-2"></i>5. Społeczność, komentarze i pojedynki</h2>
                    <p>5.1. Użytkownik może korzystać z profilu, komentarzy, zaproszeń do znajomych i pojedynków wyłącznie zgodnie z celem edukacyjnym platformy.</p>
                    <p>5.2. Zabronione są treści obraźliwe, wulgarne, naruszające prywatność innych osób, podszywanie się pod innych użytkowników oraz próby manipulowania rankingiem.</p>
                    <p>5.3. Tryb All-In Duel może zmieniać liczbę XP użytkownika. Przed akceptacją wyzwania użytkownik widzi tryb, stawkę i podstawowe zasady.</p>
                    <p>5.4. Obsługa platformy może usunąć treść, ukryć komentarz, ograniczyć funkcję społecznościową albo zablokować konto, jeżeli narusza regulamin, bezpieczeństwo platformy lub prawa innych osób.</p>
                    <p>5.5. Użytkownik może zgłosić naruszenie przez <a href="pages/zglos-naruszenie.php">formularz zgłoszenia naruszenia</a> albo kontakt mailowy <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>. Zgłoszenie powinno zawierać link lub identyfikator treści, opis problemu i dane kontaktowe zgłaszającego.</p>
                    <p>5.6. Zgłoszenia naruszeń są rozpatrywane na podstawie opisu, wskazanej treści i danych kontaktowych, jeżeli zostały podane.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-shield-exclamation me-2"></i>6. Odpowiedzialność Administratora</h2>
                    <p>6.1. Administrator dokłada wszelkich starań, aby platforma działała bez zakłóceń, jednak nie gwarantuje jej 100% bezawaryjności.</p>
                    <p>6.2. Administrator nie ponosi odpowiedzialności za problemy techniczne wynikające z winy użytkownika (np. utrata połączenia z internetem podczas rozwiązywania testu, awaria sprzętu).</p>
                    <p>6.3. Administrator zastrzega sobie prawo do czasowego wyłączenia platformy w celu przeprowadzenia prac konserwacyjnych lub aktualizacji.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-x-circle me-2"></i>7. Blokada Konta (Bany)</h2>
                    <p>Złamanie postanowień regulaminu może skutkować:</p>
                    <ul>
                        <li>Tymczasowym zawieszeniem dostępu do platformy.</li>
                        <li>Anulowaniem wyników sprawdzianów.</li>
                        <li>Trwałą blokadą konta w przypadku rażących naruszeń, nadużyć technicznych, wulgarnego zachowania albo naruszenia praw innych osób.</li>
                    </ul>
                    <p>O ograniczeniach dostępu decyduje obsługa platformy albo prowadzący sprawdzian w zakresie prowadzonych zajęć.</p>
                </div>

                <div class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-journal-text me-2"></i>8. Postanowienia Końcowe</h2>
                    <p>8.1. Administrator zastrzega sobie prawo do wprowadzania zmian w niniejszym regulaminie. O istotnych zmianach użytkownicy zostaną poinformowani (np. poprzez komunikat po zalogowaniu).</p>
                    <p>8.2. W sprawach nieuregulowanych w niniejszym regulaminie zastosowanie mają powszechnie obowiązujące przepisy prawa polskiego oraz statut ZSEM.</p>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted">Ostatnia aktualizacja: <?= date('d.m.Y') ?> &nbsp;|&nbsp; ZSEM Tech &nbsp;|&nbsp; Damian Podgórski i Michał Michalik &nbsp;|&nbsp; <a href="pages/privacy.php">Polityka Prywatności</a></small>
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

