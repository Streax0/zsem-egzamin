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
    <meta name="description" content="Regulamin korzystania z platformy ZSEM Tech – prawa, obowiązki użytkowników i zasady działania.">
    <title>Regulamin Użytkowania – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
   <style>
        body { background: #f8fafc; color: #1e293b; font-family: 'Inter', sans-serif; }
        .legal-wrap { max-width: 860px; margin: 3rem auto; padding: 0 1rem 4rem; }
        .legal-hero { background: linear-gradient(135deg, #1e40af, var(--primary-color)); color: white; border-radius: 1.5rem; padding: 3rem; margin-bottom: 2rem; }
        .legal-hero h1 { font-weight: 800; font-size: 2rem; margin-bottom: 0.5rem; }
        .legal-hero p { opacity: 0.85; margin: 0; }
        .legal-card { background: white; border-radius: 1.25rem; padding: 2.5rem; box-shadow: 0 4px 24px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
        .legal-card h2 { font-weight: 700; color: #1e40af; font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .legal-card h2 i { font-size: 1.2rem; }
        .legal-card p, .legal-card li { line-height: 1.75; color: #475569; }
        .legal-card ul { padding-left: 1.25rem; }
        .legal-card ul li { margin-bottom: 0.4rem; }
        .badge-rodo { background: #dbeafe; color: #1e40af; font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.75rem; border-radius: 999px; display: inline-block; margin-bottom: 1.5rem; }
        .terms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .terms-item { background: #f1f5f9; border-radius: 0.75rem; padding: 1rem; text-align: center; }
        .terms-item i { font-size: 1.5rem; color: var(--primary-color); display: block; margin-bottom: 0.5rem; }
        .terms-item span { font-size: 0.85rem; font-weight: 600; color: #334155; }
        body.dark-mode .legal-card { background: #1e293b !important; color: #e5e7eb !important; border: 1px solid rgba(148,163,184,.24); box-shadow: none; }
        body.dark-mode .legal-card h2 { color: #93c5fd !important; }
        body.dark-mode .legal-card p, body.dark-mode .legal-card li { color: #cbd5e1 !important; }
    </style>
</head>
<body>
    <main class="legal-wrap" role="main">
        <a href="index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2"></i>Powrót</a>

        <div class="legal-hero">
            <span class="badge-rodo"><i class="bi bi-file-earmark-text me-1"></i>Regulamin</span>
            <h1><i class="bi bi-journal-bookmark-fill me-2"></i>Regulamin Użytkowania</h1>
            <p>Poniższe zapisy stanowią oficjalny regulamin korzystania z platformy egzaminacyjnej <strong>ZSEM Tech</strong>.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-info-circle"></i>1. Postanowienia Ogólne</h2>
            <p>1.1. Platforma ZSEM Tech (zsem-egzamin.online) jest narzędziem edukacyjnym służącym do weryfikacji wiedzy, przygotowania do egzaminów zawodowych oraz przeprowadzania sprawdzianów online.</p>
            <p>1.2. Właścicielami i autorami platformy są Michał Michalik oraz Damian Podgórski. Platforma jest udostępniana ZSEM, szkolnej administracji i prowadzącym jako projekt szkolny oraz narzędzie edukacyjne.</p>
            <p>1.3. Rejestracja i korzystanie z platformy oznaczają pełną akceptację niniejszego regulaminu.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-person-badge"></i>2. Konta użytkowników</h2>
            <p>Platforma udostępnia konta osobom korzystającym z materiałów edukacyjnych oraz osobom prowadzącym zajęcia. Zakres widocznych funkcji zależy od typu konta i celu korzystania z platformy.</p>
            <p class="mt-3">2.1. Konto jest imienne i przypisane do jednej osoby. Udostępnianie danych logowania osobom trzecim jest surowo zabronione.</p>
            <p>2.2. Użytkownik ponosi pełną odpowiedzialność za wszelkie działania wykonane za pośrednictwem jego konta.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-pencil-square"></i>3. Zasady Rozwiązywania Egzaminów i Sprawdzianów</h2>
            <p>Podczas oficjalnych sprawdzianów organizowanych przez nauczycieli obowiązują rygorystyczne zasady uczciwości:</p>
            <ul>
                <li>Zabronione jest korzystanie z niedozwolonych pomocy naukowych, skryptów lub notatek, o ile prowadzący nie postanowi inaczej.</li>
                <li>Zabronione jest kontaktowanie się z innymi uczestnikami sprawdzianu w celu wymiany odpowiedzi.</li>
                <li>Platforma może stosować ogólne zabezpieczenia uczciwości sprawdzianu i oznaczać nietypowe zdarzenia do weryfikacji.</li>
                <li>W przypadku naruszenia zasad prowadzący może unieważnić wynik albo zakończyć sprawdzian zgodnie z zasadami zajęć.</li>
            </ul>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-database"></i>4. Baza Pytań i Własność Intelektualna</h2>
            <p>4.1. Materiały udostępniane na platformie stanowią własność uprawnionych podmiotów albo zostały wykorzystane w celach edukacyjnych zgodnie z właściwymi zasadami.</p>
            <p>4.2. Pełne prawa autorskie do autorskich elementów platformy ZSEM Tech należą do twórców: Damian Podgórski oraz Michał Michalik. Szkoła nie nabywa tych praw przez samo korzystanie z platformy.</p>
            <p>4.3. ZSEM i upoważniona administracja szkolna mogą korzystać z platformy w zakresie potrzebnym do prowadzenia projektu szkolnego, obsługi zajęć i administracji edukacyjnej.</p>
            <p>4.4. Kopiowanie, rozpowszechnianie lub komercyjne wykorzystywanie treści zawartych na platformie bez właściwej zgody jest zabronione.</p>
            <p>4.5. Projekt ma charakter edukacyjny i niekomercyjny; platforma nie jest prowadzona w celu sprzedaży dostępu do materiałów ani generowania zysku.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-people"></i>5. Społeczność, komentarze i pojedynki</h2>
            <p>5.1. Użytkownik może korzystać z profilu, komentarzy, zaproszeń do znajomych i pojedynków wyłącznie zgodnie z celem edukacyjnym platformy.</p>
            <p>5.2. Zabronione są treści obraźliwe, wulgarne, naruszające prywatność innych osób, podszywanie się pod innych użytkowników oraz próby manipulowania rankingiem.</p>
            <p>5.3. Tryb All-In Duel może zmieniać liczbę XP użytkownika. Przed akceptacją wyzwania użytkownik widzi tryb, stawkę i podstawowe zasady.</p>
            <p>5.4. Obsługa platformy może usunąć treść, ukryć komentarz, ograniczyć funkcję społecznościową albo zablokować konto, jeżeli narusza regulamin, bezpieczeństwo platformy lub prawa innych osób.</p>
            <p>5.5. Użytkownik może zgłosić naruszenie przez <a href="zglos-naruszenie.php">formularz zgłoszenia naruszenia</a> albo kontakt mailowy <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>. Zgłoszenie powinno zawierać link lub identyfikator treści, opis problemu i dane kontaktowe zgłaszającego.</p>
            <p>5.6. Zgłoszenia naruszeń są rozpatrywane na podstawie opisu, wskazanej treści i danych kontaktowych, jeżeli zostały podane.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-shield-exclamation"></i>6. Odpowiedzialność Administratora</h2>
            <p>6.1. Administrator dokłada wszelkich starań, aby platforma działała bez zakłóceń, jednak nie gwarantuje jej 100% bezawaryjności.</p>
            <p>6.2. Administrator nie ponosi odpowiedzialności za problemy techniczne wynikające z winy użytkownika (np. utrata połączenia z internetem podczas rozwiązywania testu, awaria sprzętu).</p>
            <p>6.3. Administrator zastrzega sobie prawo do czasowego wyłączenia platformy w celu przeprowadzenia prac konserwacyjnych lub aktualizacji.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-x-circle"></i>7. Blokada Konta (Bany)</h2>
            <p>Złamanie postanowień regulaminu może skutkować:</p>
            <ul>
                <li>Tymczasowym zawieszeniem dostępu do platformy.</li>
                <li>Anulowaniem wyników sprawdzianów.</li>
                <li>Trwałą blokadą konta w przypadku rażących naruszeń, nadużyć technicznych, wulgarnego zachowania albo naruszenia praw innych osób.</li>
            </ul>
            <p>O ograniczeniach dostępu decyduje obsługa platformy albo prowadzący sprawdzian w zakresie prowadzonych zajęć.</p>
        </div>

        <div class="legal-card">
            <h2><i class="bi bi-journal-text"></i>8. Postanowienia Końcowe</h2>
            <p>8.1. Administrator zastrzega sobie prawo do wprowadzania zmian w niniejszym regulaminie. O istotnych zmianach użytkownicy zostaną poinformowani (np. poprzez komunikat po zalogowaniu).</p>
            <p>8.2. W sprawach nieuregulowanych w niniejszym regulaminie zastosowanie mają powszechnie obowiązujące przepisy prawa polskiego oraz statut ZSEM.</p>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">Ostatnia aktualizacja: <?= date('d.m.Y') ?> &nbsp;|&nbsp; ZSEM Tech &nbsp;|&nbsp; Damian Podgórski i Michał Michalik &nbsp;|&nbsp; <a href="privacy.php">Polityka Prywatności</a></small>
        </div>
    </main>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
