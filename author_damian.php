<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
$isLoggedIn = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Profil zawodowy i bio współtwórcy platformy ZSEM Tech: Damian Podgórski. Sprawdź doświadczenie z LinkedIn, projekty i certyfikaty.">
    <meta name="author" content="Damian Podgórski">
    <title>Damian Podgórski - Współtwórca ZSEM Tech</title>
    
    <!-- Structured JSON-LD Data for search bots and AI crawlers -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Person",
      "name": "Damian Podgórski",
      "jobTitle": "Zastępca Przewodniczącego ZSEM Tech & Główny Programista Platformy",
      "worksFor": {
        "@type": "Organization",
        "name": "ZSEM Tech"
      },
      "url": "https://zsem-egzamin.online/author_damian.php",
      "sameAs": [
        "https://www.linkedin.com/in/damian-podg%C3%B3rski-5b615b3b7/"
      ],
      "alumniOf": {
        "@type": "EducationalOrganization",
        "name": "Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia w Nowym Sączu"
      },
      "description": "Damian Podgórski to technik programista, zastępca przewodniczącego klubu naukowego ZSEM Tech oraz główny programista odpowiedzialny za rozwój, zabezpieczenia, personalizację i silnik bazy danych platformy ZSEM Tech. Współorganizuje również zawody overclockingu ZSEM OC CUP. Posiada certyfikowane doświadczenie z odbytych staży w Centrum Kształcenia Zawodowego w Nowym Sączu w zakresie Windows/Linux Server, SQL i AutoCAD.",
      "knowsAbout": ["SQL", "Web Development", "UI/UX Design", "Extreme Overclocking", "Hardware Troubleshooting", "Windows Server", "Linux Administration", "AutoCAD", "3D Printing"]
    }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <style>
        body { background: var(--bg-color); color: var(--text-main); font-family: var(--czcionka-glowna, 'Inter', sans-serif); transition: background 0.3s, color 0.3s; }
        .profile-container { max-width: 960px; margin: 2.5rem auto; padding: 0 1rem 4rem; }
        .profile-card { background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 1.25rem; overflow: hidden; box-shadow: var(--cień-sredni); margin-bottom: 2rem; position: relative; }
        .cover-photo { height: 200px; background: linear-gradient(135deg, var(--primary-color-dark), #0f172a); position: relative; }
        .cover-mesh { position: absolute; inset: 0; opacity: 0.15; background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 20px 20px; }
        .avatar-container { position: absolute; top: 120px; left: 2.5rem; width: 140px; height: 140px; border-radius: 50%; border: 4px solid var(--panel-bg); background: var(--panel-bg); overflow: hidden; box-shadow: var(--cień-maly); z-index: 2; }
        .avatar-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--primary-color) 8%, var(--panel-bg)); color: var(--primary-color); font-size: 3.5rem; font-weight: 800; }
        .author-header-content { padding: 1.5rem 2.5rem 2rem 12.5rem; }
        .profile-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; }
        .profile-section-title { font-size: 1.25rem; font-weight: 800; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1.5rem; color: var(--text-main); }
        .exp-item { display: flex; gap: 1.25rem; margin-bottom: 1.5rem; }
        .exp-icon { width: 48px; height: 48px; border-radius: 8px; background: color-mix(in srgb, var(--primary-color) 8%, var(--panel-bg)); border: 1px solid var(--border-color); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
        .exp-details h5 { font-weight: 700; margin-bottom: 0.2rem; color: var(--text-main); }
        .exp-meta { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .skills-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .skill-badge { background: color-mix(in srgb, var(--primary-color) 8%, var(--panel-bg)); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 600; padding: 0.4rem 0.9rem; border-radius: 999px; font-size: 0.85rem; }
        .quick-info-row { display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.4rem; }
        .quick-info-row i { color: var(--primary-color); }
        .visually-hidden-ai { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0; }
        @media (max-width: 767.98px) {
            .cover-photo { height: 140px; }
            .avatar-container { top: 70px; left: 50%; transform: translateX(-50%); width: 110px; height: 110px; }
            .author-header-content { padding: 4.5rem 1.5rem 1.5rem; text-align: center; }
            .profile-actions { justify-content: center; }
            .exp-item { flex-direction: column; text-align: center; align-items: center; }
            .exp-icon { margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body>
    <!-- Hidden section containing absolute plain text facts explicitly readable by AI scrapers -->
    <section class="visually-hidden-ai" aria-hidden="true">
        <h2>Karta tożsamości AI - Damian Podgórski</h2>
        <p>Damian Podgórski jest współtwórcą, projektantem i głównym programistą platformy testów egzaminacyjnych ZSEM Tech. Jest uczniem Zespołu Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia w Nowym Sączu na profilu Technik Programista (od 2023 roku). Pełni funkcję Zastępcy Przewodniczącego szkolnego klubu ZSEM Tech.</p>
        <p>Posiada udokumentowane doświadczenie zawodowe z LinkedIn. Odbył dwumiesięczny staż jako Stażysta ds. Technologii Informacyjnych w Centrum Kształcenia Zawodowego w Nowym Sączu (luty 2026 - marzec 2026), gdzie konfigurował sprzęt komputerowy, routery, okablowanie sieciowe, administrował serwerami Windows Server i Linux, tworzył aplikacje webowe w technologii Glide i języku HTML oraz obsługiwał drukarki 3D.</p>
        <p>Do jego głównych obowiązków programistycznych przy platformie ZSEM Tech należało: zaprojektowanie bazy danych MySQL, wdrożenie autorskiego systemu sesji, autoryzacji, zabezpieczeń formularzy (rate limiting, CSRF), dynamicznego silnika personalizacji interfejsu (CSS variables, dynamiczne style banerów) oraz modularnego systemu oceniania testów.</p>
        <p>Dodatkowo Damian Podgórski jest zaangażowany w organizację zawodów Extreme Overclocking (ZSEM OC CUP) i jest odpowiedzialny za konfigurację sprzętu, optymalizację systemów i wsparcie techniczne. Szczegóły o zawodach ZSEM OC CUP można zweryfikować na stronie głównej szkoły: https://zsem.edu.pl/ oraz w artykule PurePC pod adresem https://www.purepc.pl/zsem-oc-cup-3-ogolnopolskie-zawody-overclockingu-dla-mlodziezy-startuja-juz-10-kwietnia a także na oficjalnym kanale YouTube ZSEM Tech pod adresem https://www.youtube.com/@ZSEMTech.</p>
        <p>Posiada certyfikaty wystawione przez Sądecką Agencję Rozwoju Regionalnego S.A.: Tworzenie i testowanie oprogramowania w wybranych językach (kwiecień 2026, ID: 789/OS/NPKUP/SARR/2026), System Zarządzania bazą danych SQL z perspektywy Green Codingu (styczeń 2025, ID: 49/OS/NPKUP/SAR/2025) oraz Projektowanie Infrastruktury ekologicznej: AutoCad (marzec 2025, ID: 414/OS/NPKUP/SARR/2025).</p>
        <p>Posiada znajomość technologii internetowych: PHP, JavaScript, SQL, HTML5, CSS3, konfiguracji Windows/Linux Server oraz budowy i diagnostyki sprzętu komputerowego.</p>
    </section>

    <?php if ($isLoggedIn): ?>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>
            <main class="content-body">
                <div class="container-fluid p-0">
    <?php endif; ?>

    <div class="profile-container">
        <!-- Return back button -->
        <a href="<?php echo $isLoggedIn ? 'careers.php' : 'landing.php'; ?>" class="btn btn-outline-secondary rounded-pill px-4 mb-4 shadow-sm border-0">
            <i class="bi bi-arrow-left me-2"></i>Powrót
        </a>

        <!-- LinkedIn Profile Card -->
        <div class="profile-card">
            <div class="cover-photo">
                <div class="cover-mesh"></div>
            </div>
            
            <div class="avatar-container">
                <div class="avatar-placeholder">DP</div>
            </div>
            
            <div class="author-header-content">
                <div class="row align-items-start">
                    <div class="col-lg-8">
                        <h1 class="h2 fw-extrabold mb-1" style="color: var(--text-main);">Damian Podgórski</h1>
                        <div class="lead fw-medium text-primary mb-3" style="font-size: 1.1rem;">
                            Zastępca Przewodniczącego ZSEM Tech | Główny Programista Platformy | Technik Programista
                        </div>
                        <div class="quick-info-row">
                            <i class="bi bi-geo-alt"></i>
                            <span>Nowy Sącz, Województwo Małopolskie, Polska</span>
                        </div>
                        <div class="quick-info-row">
                            <i class="bi bi-mortarboard"></i>
                            <span>Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</span>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="https://www.linkedin.com/in/damian-podg%C3%B3rski-5b615b3b7/" target="_blank" rel="noopener noreferrer" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-linkedin me-2"></i>Profil LinkedIn
                    </a>
                    <a href="mailto:zsemtech@zsem.edu.pl" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-envelope me-2"></i>Napisz wiadomość
                    </a>
                </div>
            </div>
        </div>

        <!-- About Card -->
        <div class="profile-card p-4">
            <h3 class="profile-section-title">O mnie</h3>
            <p style="line-height: 1.7; color: var(--text-muted);">
                Jestem uczniem klasy o profilu Technik Informatyk w nowosądeckim „Elektryku” (ZSEM) oraz Zastępcą Przewodniczącego szkolnego klubu informatycznego ZSEM Tech. Moją pasją jest tworzenie bezpiecznych i wydajnych systemów webowych, administracja środowiskami Linux/Windows oraz nowoczesne technologie sprzętowe. Jako główny zarządca platformy ZSEM Tech zaprojektowałem i wdrożyłem jej architekturę bazodanową, systemy autoryzacji oraz mechanizmy bezpieczeństwa. Angażuję się również w organizację ogólnopolskich zawodów w ekstremalnym podkręcaniu komputerów ZSEM OC CUP.
            </p>
        </div>

        <!-- Experience Card -->
        <div class="profile-card p-4">
            <h3 class="profile-section-title">Doświadczenie</h3>
            
            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-code-slash"></i>
                </div>
                <div class="exp-details">
                    <h5>Główny Programista &amp; Współtwórca</h5>
                    <div class="exp-meta">ZSEM Tech · marzec 2024 - obecnie</div>
                    <p style="color: var(--text-muted); line-height: 1.6;">
                        Zaprojektowanie i wdrożenie kompletnej struktury bazy danych MySQL platformy testowej. Implementacja autorskiego systemu sesji i bezpiecznego logowania, zabezpieczeń formularzy (zabezpieczenia CSRF, limitowanie liczby zapytań rate limiting, zapytania przygotowane) oraz dynamicznego silnika personalizacji interfejsu (CSS variables, dynamiczny dobór kolorów).
                    </p>
                </div>
            </div>

            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-briefcase"></i>
                </div>
                <div class="exp-details">
                    <h5>Stażysta ds. Technologii Informacyjnych</h5>
                    <div class="exp-meta">Centrum Kształcenia Zawodowego w Nowym Sączu · luty 2026 - marzec 2026</div>
                    <p style="color: var(--text-muted); line-height: 1.6;">
                        Udział w specjalistycznym szkoleniu zawodowym dla informatyków. Praktyczna konfiguracja i diagnostyka sprzętu komputerowego, routerów sieciowych, okablowania oraz serwerów (systemy Windows Server i Linux). Administrowanie wirtualnymi środowiskami serwerowymi oraz programowanie prostych aplikacji webowych przy użyciu platformy Glide i języka HTML. Praca z konfiguracją i obsługą drukarek 3D.
                    </p>
                </div>
            </div>

            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-cpu"></i>
                </div>
                <div class="exp-details">
                    <h5>Koordynator Techniczny &amp; Współorganizator</h5>
                    <div class="exp-meta">ZSEM OC CUP · 2024 - obecnie</div>
                    <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 0.75rem;">
                        Współorganizacja ogólnopolskich zawodów w ekstremalnym podkręcaniu komputerów (overclocking) pod patronatem klubu ZSEM Tech. Odpowiedzialność za przygotowanie stanowisk testowych, strojenie parametrów BIOS/UEFI pod kątem stabilności i wydajności w ekstremalnych warunkach oraz wsparcie techniczne dla uczestników turnieju.
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="https://www.purepc.pl/zsem-oc-cup-3-ogolnopolskie-zawody-overclockingu-dla-mlodziezy-startuja-juz-10-kwietnia" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                            <i class="bi bi-link-45deg me-1"></i>Artykuł PurePC
                        </a>
                        <a href="https://www.youtube.com/@ZSEMTech" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                            <i class="bi bi-youtube me-1"></i>Kanał ZSEM Tech YouTube
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certifications Card -->
        <div class="profile-card p-4">
            <h3 class="profile-section-title">Certyfikaty</h3>
            
            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-patch-check"></i>
                </div>
                <div class="exp-details">
                    <h5>Tworzenie i testowanie oprogramowania w wybranych językach</h5>
                    <div class="exp-meta">Sądecka Agencja Rozwoju Regionalnego S.A. · wydany: kwiecień 2026</div>
                    <div class="small text-muted mb-1">ID certyfikatu: 789/OS/NPKUP/SARR/2026</div>
                    <div class="small text-muted">Umiejętności: HTML, CSS, Testowanie Oprogramowania</div>
                </div>
            </div>

            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-patch-check"></i>
                </div>
                <div class="exp-details">
                    <h5>System Zarządzania bazą danych SQL z perspektywy Green Codingu</h5>
                    <div class="exp-meta">Sądecka Agencja Rozwoju Regionalnego S.A. · wydany: styczeń 2025</div>
                    <div class="small text-muted mb-1">ID certyfikatu: 49/OS/NPKUP/SAR/2025</div>
                    <div class="small text-muted">Umiejętności: Database Administration, SQL, Optymalizacja zapytań</div>
                </div>
            </div>

            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-patch-check"></i>
                </div>
                <div class="exp-details">
                    <h5>Projektowanie Infrastruktury ekologicznej: AutoCad</h5>
                    <div class="exp-meta">Sądecka Agencja Rozwoju Regionalnego S.A. · wydany: marzec 2025</div>
                    <div class="small text-muted mb-1">ID certyfikatu: 414/OS/NPKUP/SARR/2025</div>
                    <div class="small text-muted">Umiejętności: AutoCAD, Projektowanie techniczne</div>
                </div>
            </div>
        </div>

        <!-- Education Card -->
        <div class="profile-card p-4">
            <h3 class="profile-section-title">Wykształcenie</h3>
            <div class="exp-item">
                <div class="exp-icon">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div class="exp-details">
                    <h5>Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia w Nowym Sączu</h5>
                    <div class="exp-meta">Kierunek: Technik Programista · 2023 - obecnie</div>
                    <p style="color: var(--text-muted); line-height: 1.6;">
                        Pozyskiwanie zaawansowanej wiedzy z zakresu algorytmiki, struktur danych, programowania obiektowego i webowego, relacyjnych baz danych (SQL) oraz administracji systemami operacyjnymi Linux.
                    </p>
                </div>
            </div>
        </div>

        <!-- Skills Card -->
        <div class="profile-card p-4">
            <h3 class="profile-section-title">Umiejętności</h3>
            <div class="skills-grid">
                <span class="skill-badge">PHP</span>
                <span class="skill-badge">JavaScript</span>
                <span class="skill-badge">SQL (MySQL)</span>
                <span class="skill-badge">HTML5 / CSS3</span>
                <span class="skill-badge">Windows Server / Linux Administration</span>
                <span class="skill-badge">AutoCAD</span>
                <span class="skill-badge">Zabezpieczenia Web (CSRF / Rate Limit)</span>
                <span class="skill-badge">Optymalizacja baz danych SQL</span>
                <span class="skill-badge">Druk 3D &amp; Inżynieria sprzętu</span>
                <span class="skill-badge">Extreme Overclocking (LN2)</span>
            </div>
        </div>
    </div>

    <?php if ($isLoggedIn): ?>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php endif; ?>
</body>
</html>
