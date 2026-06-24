<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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
<!DOCTYPE html>
<html lang="pl" style="color-scheme: <?php echo $currentTheme === 'dark' ? 'dark' : 'light'; ?>; font-size: <?php echo htmlspecialchars($currentFontSize); ?>px; --primary-color: <?php echo htmlspecialchars($currentAccent); ?>; --kolor-glowy: <?php echo htmlspecialchars($currentAccent); ?>;">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Polityka prywatności i cookies platformy ZSEM Tech zgodna z RODO.">
    <title>Polityka prywatności i cookies - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <style>
        .rights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; }
        .right-item { background: var(--border-color); border-radius: .5rem; padding: .85rem; font-weight: 600; }
        .table { --bs-table-bg: transparent; }
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClassStr); ?>">
<div class="dashboard-layout">
    <?php if ($showSidebar) include 'includes/sidebar.php'; ?>
    <div class="main-container" style="<?php echo !$showSidebar ? 'margin-left: 0;' : ''; ?>">
        <?php if ($showSidebar) include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container py-4" style="max-width: 960px;">
                <a href="index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Powrót</a>

                <section class="welcome-card dashboard-hero mb-4 animate-in" aria-labelledby="privacy-title" style="overflow: hidden;">
                    <div class="dashboard-hero-inner">
                        <div class="hero-left" style="text-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);">
                            <div class="hero-rank-pill" style="border-color: rgba(255, 255, 255, 0.18); background: rgba(15, 23, 42, 0.35); color: #ffffff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                                <i class="bi bi-shield-check"></i>
                                <span style="color: #ffffff; font-weight: 800;">RODO / GDPR</span>
                            </div>
                            <h1 id="privacy-title" class="h2" style="font-weight: 800; color: #ffffff;"><i class="bi bi-lock-fill me-2" aria-hidden="true"></i>Polityka prywatności i cookies</h1>
                            <p class="mb-0 text-white" style="font-size: 1.05rem; line-height: 1.6; font-weight: 500; opacity: 0.95;">Dokument opisuje zasady przetwarzania danych osobowych użytkowników platformy ZSEM Tech.</p>
                        </div>
                        <div class="hero-right d-none d-lg-flex align-items-center justify-content-end">
                            <i class="bi bi-shield-lock text-white" style="font-size: 5.5rem; opacity: 0.22; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.15));"></i>
                        </div>
                    </div>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-building me-2" aria-hidden="true"></i>1. Administrator danych</h2>
                    <p>Administratorem danych osobowych jest podmiot prowadzący platformę ZSEM Tech:</p>
                    <ul>
                        <li><strong>Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</strong></li>
                        <li>Adres: Nowy Sącz, ul. Limanowskiego 4, Polska</li>
                        <li>Platforma: <strong>ZSEM Tech</strong></li>
                        <li>E-mail kontaktowy: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a></li>
                        <li>Kontakt w sprawach danych: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a></li>
                    </ul>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-collection me-2" aria-hidden="true"></i>2. Zakres danych</h2>
                    <ul>
                        <li>dane konta: login, e-mail, imię, nazwisko, rola użytkownika, klasa, zahashowane hasło, ustawienia profilu,</li>
                        <li>dane edukacyjne: wyniki testów, odpowiedzi, czas rozwiązywania, postępy, rankingi, misje i historia aktywności,</li>
                        <li>dane sprawdzianów: kod sesji, dane uczestnika, status sprawdzianu i wynik,</li>
                        <li>dane społecznościowe: zaproszenia do znajomych, komentarze profilu, pojedynki, stawki XP i powiadomienia,</li>
                        <li>dane techniczne: identyfikator sesji, adres IP, informacje o przeglądarce i podstawowe zdarzenia potrzebne do działania konta,</li>
                        <li>dane dobrowolne: opis profilu, preferencje interfejsu, zapamiętane dane uczestnika sprawdzianu.</li>
                    </ul>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-bullseye me-2" aria-hidden="true"></i>3. Cele i podstawy prawne</h2>
                    <ul>
                        <li>prowadzenie konta, logowanie i udostępnienie funkcji platformy - art. 6 ust. 1 lit. b RODO,</li>
                        <li>rejestracja udziału w sprawdzianie, obsługa wyników i weryfikacja uczestników - art. 6 ust. 1 lit. b lub e RODO, zależnie od statusu administratora,</li>
                        <li>kontakt techniczny i odpowiedź na zapytania - art. 6 ust. 1 lit. f RODO albo art. 6 ust. 1 lit. a RODO, gdy użyta jest dobrowolna zgoda,</li>
                        <li>bezpieczeństwo kont, zapobieganie nadużyciom i ochrona uczciwości sprawdzianów - art. 6 ust. 1 lit. f RODO,</li>
                        <li>przechowywanie wymaganych informacji rozliczeniowych lub organizacyjnych - art. 6 ust. 1 lit. c RODO, jeśli taki obowiązek dotyczy administratora,</li>
                        <li>cookies preferencji interfejsu - zgoda użytkownika w rozumieniu ePrivacy i art. 6 ust. 1 lit. a RODO.</li>
                    </ul>
                    <p>Jeżeli podstawą jest zgoda, można ją wycofać w dowolnym momencie bez wpływu na zgodność wcześniejszego przetwarzania.</p>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i>4. Okres przechowywania</h2>
                    <ul>
                        <li>dane konta: przez czas aktywności konta, a potem do 30 dni na obsługę usunięcia, chyba że dłuższy okres wynika z obowiązków prawnych,</li>
                        <li>wyniki sprawdzianów i dane edukacyjne: przez czas potrzebny do realizacji celu edukacyjnego, zwykle rok szkolny plus 1 rok archiwalny,</li>
                        <li>zdarzenia techniczne: maksymalnie 90 dni, chyba że są potrzebne do wyjaśnienia zgłoszenia lub ochrony praw,</li>
                        <li>cookies zgody: 183 dni, cookies sesyjne: do zakończenia sesji lub wylogowania,</li>
                        <li>dane z zapytań kontaktowych: przez czas obsługi sprawy i maksymalnie 12 miesięcy po jej zamknięciu.</li>
                    </ul>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-share me-2" aria-hidden="true"></i>5. Odbiorcy danych</h2>
                    <p>Dane nie są sprzedawane. Dostęp mogą mieć wyłącznie upoważnione osoby obsługujące platformę lub zajęcia, dostawcy hostingu i usług technicznych oraz podmioty uprawnione na podstawie prawa. Jeżeli dostawca przetwarza dane w imieniu administratora, powinien działać na podstawie umowy powierzenia.</p>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-person-check me-2" aria-hidden="true"></i>6. Prawa użytkownika</h2>
                    <div class="rights-grid">
                        <div class="right-item">dostęp do danych</div>
                        <div class="right-item">sprostowanie danych</div>
                        <div class="right-item">usunięcie danych</div>
                        <div class="right-item">ograniczenie przetwarzania</div>
                        <div class="right-item">przenoszenie danych</div>
                        <div class="right-item">sprzeciw wobec przetwarzania</div>
                        <div class="right-item">wycofanie zgody</div>
                        <div class="right-item">skarga do organu nadzorczego</div>
                    </div>
                    <p class="mt-3">Żądania można wysyłać na <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>. Odpowiedź powinna zostać udzielona bez zbędnej zwłoki, co do zasady w terminie 30 dni.</p>
                </section>

                <section class="dashboard-panel mb-4" id="cookies">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-cookie me-2" aria-hidden="true"></i>7. Polityka cookies</h2>
                    <p>Cookies niezbędne mogą działać bez zgody, ponieważ utrzymują sesję, logowanie i zabezpieczenia. Cookies preferencji są zapisywane wyłącznie po akceptacji cookies w banerze albo po świadomym użyciu ustawień, jeśli użytkownik zaakceptował cookies opcjonalne.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr><th>Nazwa</th><th>Cel</th><th>Typ</th><th>Okres</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>PHPSESSID</td><td>utrzymanie sesji i logowania</td><td>niezbędny</td><td>sesja</td></tr>
                                <tr><td>remember_me</td><td>opcjonalne zapamiętanie logowania</td><td>funkcjonalny, ustawiany po wyborze użytkownika</td><td>zgodnie z ustawieniem aplikacji</td></tr>
                                <tr><td>cookie_consent</td><td>zapamiętanie decyzji z banera cookies</td><td>niezbędny do zgodności</td><td>183 dni</td></tr>
                                <tr><td>user_theme</td><td>preferencja jasnego/ciemnego motywu</td><td>opcjonalny</td><td>183 dni</td></tr>
                                <tr><td>user_font_size</td><td>preferencja rozmiaru tekstu</td><td>opcjonalny</td><td>183 dni</td></tr>
                                <tr><td>user_density, user_accent, reduce_motion</td><td>preferencje wyglądu i dostępności interfejsu</td><td>opcjonalny</td><td>183 dni</td></tr>
                                <tr><td>dashboard_view, default_test_mode, external_new_tab, hide_help_center</td><td>preferencje zachowania aplikacji</td><td>opcjonalny</td><td>183 dni</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Platforma nie ładuje obecnie cookies analitycznych ani marketingowych stron trzecich przed uzyskaniem zgody. Pełna tabela znajduje się w <a href="polityka-cookies.php">Polityce cookies</a>.</p>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-cpu me-2" aria-hidden="true"></i>8. AI i zautomatyzowane decyzje</h2>
                    <p>Platforma nie podejmuje wobec użytkowników decyzji wyłącznie w sposób zautomatyzowany wywołujących skutki prawne. Jeżeli narzędzia AI zostaną dodane, użytkownicy otrzymają aktualną informację o zakresie i celu ich użycia.</p>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-shield-exclamation me-2" aria-hidden="true"></i>9. Bezpieczeństwo</h2>
                    <ul>
                        <li>hasła nie są przechowywane w postaci jawnej,</li>
                        <li>formularze i sesje korzystają z zabezpieczeń przed nadużyciami,</li>
                        <li>dostęp do danych jest ograniczony do osób i funkcji, które go potrzebują,</li>
                        <li>stosowane są podstawowe zabezpieczenia po stronie aplikacji i serwera.</li>
                    </ul>
                </section>

                <section class="dashboard-panel mb-4">
                    <h2 class="h5 fw-bold text-primary mb-3"><i class="bi bi-telephone me-2" aria-hidden="true"></i>10. Kontakt i skargi</h2>
                    <p>Kontakt w sprawach prywatności: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>.</p>
                    <p>Użytkownik ma prawo wnieść skargę do Prezesa Urzędu Ochrony Danych Osobowych, ul. Stawki 2, 00-193 Warszawa.</p>
                </section>

                <p class="text-center text-muted small mt-4">Ostatnia aktualizacja: <?= date('d.m.Y') ?> | <a href="terms.php">Regulamin</a></p>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
