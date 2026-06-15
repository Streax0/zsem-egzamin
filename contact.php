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
    <title>Kontakt - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .contact-hero-panel {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border-radius: 1.5rem;
            color: #fff;
            box-shadow: 0 15px 35px rgba(13, 110, 253, 0.2);
            overflow: hidden;
            position: relative;
        }
        .contact-hero-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
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
</head>
<body>

    <div class="dashboard-layout">
        <?php if (isset($_SESSION['user_id'])) include 'includes/sidebar.php'; ?>

        <div class="main-container" style="<?php echo !isset($_SESSION['user_id']) ? 'margin-left: 0;' : ''; ?>">
            <?php if (isset($_SESSION['user_id'])) include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container py-4">
                    
                    <div class="mb-4 animate-in">
                        <h1 class="fw-bold mb-1 h2"><i class="bi bi-headset text-primary me-2" aria-hidden="true"></i>Kontakt</h1>
                        <p class="text-muted">Skontaktuj się z nami w razie pytań lub problemów technicznych.</p>
                    </div>

                    <div class="row g-4 align-items-stretch">
                        <!-- Contact Info Column -->
                        <div class="col-lg-5 col-xl-4">
                            <div class="contact-hero-panel p-4 p-xl-5 h-100 d-flex flex-column animate-in">
                                <h3 class="fw-bold mb-3">ZSEM Tech</h3>
                                <p class="mb-5 fs-6">
                                    Masz pytania dotyczące platformy? Chcesz zgłosić błąd lub zaproponować nową funkcjonalność? Jesteśmy do Twojej dyspozycji.
                                </p>
                                
                                <div class="d-flex flex-column gap-4 mt-auto">
                                    <div class="d-flex align-items-center gap-3 contact-info-row">
                                        <div class="contact-icon-box">
                                            <i class="bi bi-envelope-at-fill"></i>
                                        </div>
                                        <div>
                                            <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">E-mail do nas</div>
                                            <a href="mailto:zsemtech@zsem.edu.pl" class="text-white text-decoration-none fw-bold fs-5">zsemtech@zsem.edu.pl</a>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 contact-info-row">
                                        <div class="contact-icon-box">
                                            <i class="bi bi-building-fill" aria-hidden="true"></i>
                                        </div>
                                        <div>
                                            <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Dane identyfikacyjne</div>
                                            <div class="text-white fw-bold">Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</div>
                                            <div class="text-white small">Platforma edukacyjna ZSEM Tech</div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 contact-info-row">
                                        <div class="contact-icon-box">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </div>
                                        <div>
                                            <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Lokalizacja</div>
                                            <div class="text-white fw-bold fs-5">Nowy Sącz, Polska</div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 contact-info-row">
                                        <div class="contact-icon-box">
                                            <i class="bi bi-clock-fill"></i>
                                        </div>
                                        <div>
                                            <div class="small text-white text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Godziny pracy</div>
                                            <div class="text-white fw-bold fs-5">Pon - Pt: 7:00 - 17:00</div>
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
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>
