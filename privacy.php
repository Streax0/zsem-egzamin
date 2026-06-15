<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

startSecureSession();
?>
<!DOCTYPE html>
<html lang="pl">
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
    <style>
        body { background: #f8fafc; color: #1e293b; font-family: 'Inter', sans-serif; }
        .legal-wrap { max-width: 920px; margin: 3rem auto; padding: 0 1rem 4rem; }
        .legal-hero { background: linear-gradient(135deg, #4413ce, #2088c4); color: white; border-radius: 1rem; padding: 2.5rem; margin-bottom: 2rem; }
        .legal-hero h1 { font-weight: 800; font-size: 2rem; margin-bottom: .5rem; }
        .legal-card { background: white; border-radius: .75rem; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
        .legal-card h2 { font-weight: 700; color: #174ea6; font-size: 1.15rem; margin-bottom: 1rem; display: flex; gap: .5rem; align-items: center; }
        .legal-card p, .legal-card li, .legal-card td, .legal-card th { line-height: 1.7; color: #334155; }
        .legal-card ul { padding-left: 1.25rem; }
        .badge-rodo { background: #dbeafe; color: #174ea6; font-size: .75rem; font-weight: 700; padding: .3rem .75rem; border-radius: 999px; display: inline-block; margin-bottom: 1rem; }
        .rights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; }
        .right-item { background: #f1f5f9; border-radius: .5rem; padding: .85rem; font-weight: 600; }
        .table { --bs-table-bg: transparent; }
        body.dark-mode .legal-card { background: #1e293b !important; color: #e5e7eb !important; border: 1px solid rgba(148,163,184,.24); box-shadow: none; }
        body.dark-mode .legal-card h2 { color: #93c5fd !important; }
        body.dark-mode .legal-card p, body.dark-mode .legal-card li, body.dark-mode .legal-card td, body.dark-mode .legal-card th { color: #cbd5e1 !important; }
        body.dark-mode .legal-wrap { color: #e5e7eb; }
    </style>
</head>
<body>
    <main class="legal-wrap" role="main">
        <a href="index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Powrót</a>

        <section class="legal-hero" aria-labelledby="privacy-title">
            <span class="badge-rodo"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>RODO / GDPR</span>
            <h1 id="privacy-title"><i class="bi bi-lock-fill me-2" aria-hidden="true"></i>Polityka prywatności i cookies</h1>
            <p>Dokument opisuje zasady przetwarzania danych osobowych użytkowników platformy ZSEM Tech.</p>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-building" aria-hidden="true"></i>1. Administrator danych</h2>
            <p>Administratorem danych osobowych jest podmiot prowadzący platformę ZSEM Tech:</p>
            <ul>
                <li><strong>Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</strong></li>
                <li>Adres: Nowy Sącz, ul. Limanowskiego 4, Polska</li>
                <li>Platforma: <strong>ZSEM Tech</strong></li>
                <li>E-mail kontaktowy: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a></li>
                <li>Kontakt w sprawach danych: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a></li>
            </ul>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-collection" aria-hidden="true"></i>2. Zakres danych</h2>
            <ul>
                <li>dane konta: login, e-mail, imię, nazwisko, rola użytkownika, klasa, zahashowane hasło, ustawienia profilu,</li>
                <li>dane edukacyjne: wyniki testów, odpowiedzi, czas rozwiązywania, postępy, rankingi, misje i historia aktywności,</li>
                <li>dane sprawdzianów: kod sesji, dane uczestnika, status sprawdzianu i wynik,</li>
                <li>dane społecznościowe: zaproszenia do znajomych, komentarze profilu, pojedynki, stawki XP i powiadomienia,</li>
                <li>dane techniczne: identyfikator sesji, adres IP, informacje o przeglądarce i podstawowe zdarzenia potrzebne do działania konta,</li>
                <li>dane dobrowolne: opis profilu, preferencje interfejsu, zapamiętane dane uczestnika sprawdzianu.</li>
            </ul>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-bullseye" aria-hidden="true"></i>3. Cele i podstawy prawne</h2>
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

        <section class="legal-card">
            <h2><i class="bi bi-calendar3" aria-hidden="true"></i>4. Okres przechowywania</h2>
            <ul>
                <li>dane konta: przez czas aktywności konta, a potem do 30 dni na obsługę usunięcia, chyba że dłuższy okres wynika z obowiązków prawnych,</li>
                <li>wyniki sprawdzianów i dane edukacyjne: przez czas potrzebny do realizacji celu edukacyjnego, zwykle rok szkolny plus 1 rok archiwalny,</li>
                <li>zdarzenia techniczne: maksymalnie 90 dni, chyba że są potrzebne do wyjaśnienia zgłoszenia lub ochrony praw,</li>
                <li>cookies zgody: 183 dni, cookies sesyjne: do zakończenia sesji lub wylogowania,</li>
                <li>dane z zapytań kontaktowych: przez czas obsługi sprawy i maksymalnie 12 miesięcy po jej zamknięciu.</li>
            </ul>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-share" aria-hidden="true"></i>5. Odbiorcy danych</h2>
            <p>Dane nie są sprzedawane. Dostęp mogą mieć wyłącznie upoważnione osoby obsługujące platformę lub zajęcia, dostawcy hostingu i usług technicznych oraz podmioty uprawnione na podstawie prawa. Jeżeli dostawca przetwarza dane w imieniu administratora, powinien działać na podstawie umowy powierzenia.</p>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-person-check" aria-hidden="true"></i>6. Prawa użytkownika</h2>
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

        <section class="legal-card" id="cookies">
            <h2><i class="bi bi-cookie" aria-hidden="true"></i>7. Polityka cookies</h2>
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

        <section class="legal-card">
            <h2><i class="bi bi-cpu" aria-hidden="true"></i>8. AI i zautomatyzowane decyzje</h2>
            <p>Platforma nie podejmuje wobec użytkowników decyzji wyłącznie w sposób zautomatyzowany wywołujących skutki prawne. Jeżeli narzędzia AI zostaną dodane, użytkownicy otrzymają aktualną informację o zakresie i celu ich użycia.</p>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-shield-exclamation" aria-hidden="true"></i>9. Bezpieczeństwo</h2>
            <ul>
                <li>hasła nie są przechowywane w postaci jawnej,</li>
                <li>formularze i sesje korzystają z zabezpieczeń przed nadużyciami,</li>
                <li>dostęp do danych jest ograniczony do osób i funkcji, które go potrzebują,</li>
                <li>stosowane są podstawowe zabezpieczenia po stronie aplikacji i serwera.</li>
            </ul>
        </section>

        <section class="legal-card">
            <h2><i class="bi bi-telephone" aria-hidden="true"></i>10. Kontakt i skargi</h2>
            <p>Kontakt w sprawach prywatności: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>.</p>
            <p>Użytkownik ma prawo wnieść skargę do Prezesa Urzędu Ochrony Danych Osobowych, ul. Stawki 2, 00-193 Warszawa.</p>
        </section>

        <p class="text-center text-muted small">Ostatnia aktualizacja: <?= date('d.m.Y') ?> | <a href="terms.php">Regulamin</a></p>
    </main>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
