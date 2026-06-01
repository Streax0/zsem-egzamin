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
    <meta name="description" content="Polityka cookies ZSEM Tech: kategorie, cele, czas życia i ustawienia zgód.">
    <title>Polityka cookies – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <style>
        body { background: #f8fafc; color: #1e293b; font-family: 'Inter', sans-serif; }
        .legal-wrap { max-width: 980px; margin: 3rem auto; padding: 0 1rem 4rem; }
        .legal-hero { background: linear-gradient(135deg, #0f766e, #2563eb); color: white; border-radius: 1.25rem; padding: 2.5rem; margin-bottom: 2rem; }
        .legal-card { background: #fff; border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 24px rgba(0,0,0,.06); margin-bottom: 1.25rem; }
        .legal-card h2 { color: #0f766e; font-weight: 800; font-size: 1.15rem; margin-bottom: 1rem; }
        .legal-card p, .legal-card li, .legal-card td, .legal-card th { color: #334155; line-height: 1.7; }
        body.dark-mode .legal-card { background: #1e293b !important; color: #e5e7eb !important; border: 1px solid rgba(148,163,184,.24); box-shadow: none; }
        body.dark-mode .legal-card h2 { color: #5eead4 !important; }
        body.dark-mode .legal-card p, body.dark-mode .legal-card li, body.dark-mode .legal-card td, body.dark-mode .legal-card th { color: #cbd5e1 !important; }
    </style>
</head>
<body>
    <main class="legal-wrap" role="main">
        <a href="index.php" class="btn btn-light mb-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Powrót</a>

        <section class="legal-hero" aria-labelledby="cookies-title">
            <p class="fw-bold mb-2">ZSEM Tech</p>
            <h1 id="cookies-title" class="fw-800">Polityka cookies</h1>
            <p class="mb-0">Ostatnia aktualizacja: <?= date('d.m.Y') ?>. Zgody są wersjonowane: 2026-05-17.</p>
        </section>

        <section class="legal-card">
            <h2>1. Kategorie cookies</h2>
            <ul>
                <li><strong>Niezbędne</strong> – sesja, logowanie, bezpieczeństwo formularzy i zapamiętanie decyzji cookies.</li>
                <li><strong>Preferencyjne</strong> – opcjonalne ustawienia motywu, rozmiaru tekstu, gęstości i widoku.</li>
                <li><strong>Analityczne</strong> – obecnie brak aktywnych narzędzi analitycznych.</li>
                <li><strong>Marketingowe/social media</strong> – obecnie brak pikseli reklamowych i trackerów social media.</li>
            </ul>
        </section>

        <section class="legal-card">
            <h2>2. Tabela cookies</h2>
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

        <section class="legal-card">
            <h2>3. Zmiana zgody</h2>
            <p>Użytkownik może wrócić do ustawień w dowolnym momencie. Wycofanie zgody usuwa cookies preferencyjne z przeglądarki.</p>
            <button type="button" class="btn btn-primary rounded-pill px-4" data-cookie-settings>Otwórz ustawienia cookies</button>
        </section>
    </main>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
