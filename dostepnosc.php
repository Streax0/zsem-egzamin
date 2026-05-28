<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
startSecureSession();
?>
<!doctype html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dostępność - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background:#f8fafc; color:#1e293b; }
        .legal-wrap { max-width: 920px; margin: 3rem auto; padding: 0 1rem 4rem; }
        .legal-hero { background: linear-gradient(135deg, #1e40af, #2563eb); color:#fff; border-radius: 1.5rem; padding: clamp(2rem, 5vw, 3rem); margin-bottom: 1.5rem; box-shadow: 0 18px 45px rgba(37,99,235,.18); }
        .legal-hero p { color: rgba(255,255,255,.86); }
        .legal-card { background:#fff; border:1px solid rgba(148,163,184,.18); border-radius:1.25rem; padding:2rem; box-shadow:0 8px 28px rgba(15,23,42,.06); margin-bottom:1rem; }
        .legal-card h2 { color:#1e40af; font-weight:800; font-size:1.05rem; }
        .legal-card p, .legal-card li { color:#475569; line-height:1.75; }
    </style>
</head>
<body>
<main class="legal-wrap" id="main-content">
    <a href="index.php" class="btn btn-light border rounded-pill mb-4"><i class="bi bi-arrow-left me-1"></i>Powrót</a>
    <section class="legal-hero mb-4">
        <h1 class="fw-900">Deklaracja dostępności</h1>
        <p class="mb-0">ZSEM Tech rozwijamy tak, aby kluczowe funkcje były dostępne klawiaturą, czytnikami ekranu i na urządzeniach mobilnych.</p>
    </section>
    <div class="legal-card">
        <h2><i class="bi bi-check2-circle me-2"></i>Standard</h2>
        <p>Stosujemy semantyczny HTML, etykiety formularzy, widoczny fokus, tekst alternatywny obrazów i responsywny układ.</p>
    </div>
    <div class="legal-card">
        <h2><i class="bi bi-keyboard me-2"></i>Obsługa</h2>
        <p>Najważniejsze ekrany można obsługiwać klawiaturą. Przyciski i pola formularzy mają etykiety, a elementy interaktywne otrzymują widoczny fokus.</p>
    </div>
    <div class="legal-card">
        <h2><i class="bi bi-exclamation-triangle me-2"></i>Znane ograniczenia</h2>
        <p>Część starszych ekranów platformy nadal jest upraszczana pod kątem kontrastu i obsługi klawiaturą.</p>
    </div>
    <div class="legal-card">
        <h2><i class="bi bi-envelope me-2"></i>Kontakt</h2>
        <p class="mb-0">Uwagi dotyczące dostępności można zgłaszać przez formularz kontaktowy albo e-mail: <a href="mailto:zsemtech@zsem.edu.pl">zsemtech@zsem.edu.pl</a>.</p>
    </div>
</main>
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
