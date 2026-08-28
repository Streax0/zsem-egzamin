<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';
startSecureSession();

$pageTitle = 'ZSEM Tech – Nowoczesna Platforma Edukacyjna i Testy Egzaminacyjne INF / EE';
$extraCss = ['assets/css/landing.css'];
$extraHead = '
    <meta name="description" content="ZSEM Tech: oficjalna baza ponad 5000 pytań egzaminacyjnych CKE dla kwalifikacji INF.02, INF.03, INF.04, EE.08 i EE.09. Sprawdziany nauczyciela, tryb gościa, pojedynki 1v1 i fiszki IT.">
    <meta property="og:title" content="ZSEM Tech – Egzaminy Zawodowe INF & EE | Platforma Edukacyjna">
    <meta property="og:description" content="Przygotuj się do egzaminu zawodowego z bazą pytań CKE, bierz udział w pojedynkach 1v1 i rozwiązuj sprawdziany szkolne online.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zsem-egzamin.online/">
    <meta property="og:image" content="https://zsem-egzamin.online/zsemtech_profile.ico">
';
include 'includes/header.php';
?>
<main class="landing-page" id="main-content">
    <!-- HERO SECTION -->
    <section class="landing-hero" aria-label="Sekcja powitalna">
        <div class="hero-glow-orb-1" aria-hidden="true"></div>
        <div class="hero-glow-orb-2" aria-hidden="true"></div>
        <div class="hero-glow-orb-3" aria-hidden="true"></div>

        <!-- Glassmorphic Navbar -->
        <header class="landing-nav-wrapper">
            <nav class="landing-nav" aria-label="Główna nawigacja">
                <a href="landing.php" class="landing-brand" aria-label="Strona główna ZSEM Tech">
                    <img src="zsemtech_profile.ico" alt="Logo ZSEM Tech" width="38" height="38" loading="lazy" decoding="async">
                    <span class="brand-text">ZSEM <span class="brand-accent">Tech</span></span>
                </a>

                <div class="landing-nav-links">
                    <a href="categories.php" class="nav-link-item"><i class="bi bi-folder2-open"></i> Kwalifikacje</a>
                    <a href="practice.php" class="nav-link-item"><i class="bi bi-bullseye"></i> Praktyka</a>
                    <a href="flashcards.php" class="nav-link-item"><i class="bi bi-card-text"></i> Fiszki</a>
                    <a href="dictionary.php" class="nav-link-item"><i class="bi bi-book"></i> Słownik IT</a>
                    <a href="exam/join.php" class="nav-link-item highlight"><i class="bi bi-qr-code-scan"></i> Kod sprawdzianu</a>
                </div>

                <div class="landing-nav-actions">
                    <a href="auth/login.php" class="btn btn-ghost-light"><i class="bi bi-box-arrow-in-right"></i> Zaloguj się</a>
                    <a href="auth/register.php" class="btn btn-solid-light"><i class="bi bi-person-plus-fill"></i> Załóż konto</a>
                </div>

                <!-- Mobile Menu Toggle Button -->
                <button type="button" class="landing-nav-toggle" id="landingNavToggle" aria-label="Otwórz menu nawigacji" aria-expanded="false" aria-controls="landingMobileMenu">
                    <span class="toggle-icon-bar"></span>
                    <span class="toggle-icon-bar"></span>
                    <span class="toggle-icon-bar"></span>
                </button>
            </nav>

            <!-- Mobile Navigation Drawer / Dropdown -->
            <div class="landing-mobile-menu" id="landingMobileMenu" aria-hidden="true">
                <div class="mobile-menu-links">
                    <a href="categories.php" class="mobile-nav-link"><i class="bi bi-folder2-open"></i> <span>Kwalifikacje CKE</span></a>
                    <a href="practice.php" class="mobile-nav-link"><i class="bi bi-bullseye"></i> <span>Inteligentna Praktyka</span></a>
                    <a href="test.php" class="mobile-nav-link"><i class="bi bi-ui-checks"></i> <span>Symulator Egzaminu</span></a>
                    <a href="flashcards.php" class="mobile-nav-link"><i class="bi bi-card-text"></i> <span>Fiszki IT</span></a>
                    <a href="dictionary.php" class="mobile-nav-link"><i class="bi bi-book"></i> <span>Słownik Pojęć</span></a>
                    <a href="courses.php" class="mobile-nav-link"><i class="bi bi-award"></i> <span>Kursy i Certyfikaty</span></a>
                    <a href="exam/join.php" class="mobile-nav-link highlight"><i class="bi bi-qr-code-scan"></i> <span>Dołącz do sprawdzianu</span></a>
                </div>
                <div class="mobile-menu-divider"></div>
                <div class="mobile-menu-actions">
                    <a href="auth/login.php" class="btn btn-ghost-light w-100 mb-2"><i class="bi bi-box-arrow-in-right"></i> Zaloguj się</a>
                    <a href="auth/register.php" class="btn btn-solid-light w-100 mb-2"><i class="bi bi-person-plus-fill"></i> Załóż darmowe konto</a>
                    <form method="POST" action="actions/start_guest.php" class="m-0 w-100">
                        <?php echo csrfTokenField('guest_start'); ?>
                        <input type="hidden" name="target" value="test">
                        <button type="submit" class="btn btn-outline-hero w-100">
                            <i class="bi bi-incognito"></i> Wypróbuj jako gość
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Hero Content Grid -->
        <div class="landing-hero-grid">
            <div class="landing-copy">
                <div class="hero-badge-tag">
                    <span class="badge-dot"></span>
                    <span>PROJEKT UCZNIOWSKI ZSEM • EGZAMINY ZAWODOWE CKE 2026</span>
                </div>
                
                <h1 class="hero-title">
                    Egzaminy INF i sprawdziany <span class="text-gradient">w jednym miejscu</span>.
                </h1>
                
                <p class="hero-desc">
                    Przygotuj się do egzaminu zawodowego, dołączaj do sprawdzianów za pomocą kodu i śledź swoje postępy. Wszystko w jednym, nowoczesnym narzędziu stworzonym z myślą o uczniach i nauczycielach.
                </p>
                
                <div class="landing-actions">
                    <a href="auth/register.php" class="btn btn-primary-hero">
                        <i class="bi bi-rocket-takeoff-fill"></i> Rozpocznij za darmo
                    </a>
                    
                    <form method="POST" action="actions/start_guest.php" class="guest-action-form">
                        <?php echo csrfTokenField('guest_start'); ?>
                        <input type="hidden" name="target" value="test">
                        <button type="submit" class="btn btn-outline-hero">
                            <i class="bi bi-incognito"></i> Wypróbuj jako gość
                        </button>
                    </form>
                </div>
                
                <div class="landing-trust-tags">
                    <span class="trust-item"><i class="bi bi-check-circle-fill text-cyan"></i> 100% Bezpłatny dostęp</span>
                    <span class="trust-item"><i class="bi bi-patch-check-fill text-emerald"></i> Oficjalna baza CKE</span>
                    <span class="trust-item"><i class="bi bi-phone-fill text-purple"></i> Działa offline (PWA)</span>
                    <span class="trust-item"><i class="bi bi-shield-lock-fill text-amber"></i> Sprawdziany z PIN</span>
                </div>
            </div>

            <!-- Interactive Stage Simulator Preview -->
            <div class="landing-stage" aria-label="Interaktywny symulator pytań egzaminacyjnych">
                <div class="stage-header">
                    <div class="mac-dots" aria-hidden="true">
                        <span class="dot-red"></span>
                        <span class="dot-yellow"></span>
                        <span class="dot-green"></span>
                    </div>
                    
                    <div class="stage-tabs" role="tablist" aria-label="Kwalifikacje pytań podglądu">
                        <button type="button" class="stage-tab-btn active" data-cat="inf03" role="tab" aria-selected="true">INF.03</button>
                        <button type="button" class="stage-tab-btn" data-cat="inf02" role="tab" aria-selected="false">INF.02</button>
                        <button type="button" class="stage-tab-btn" data-cat="inf04" role="tab" aria-selected="false">INF.04</button>
                        <button type="button" class="stage-tab-btn" data-cat="ee09" role="tab" aria-selected="false">EE.09</button>
                    </div>

                    <div class="stage-status-tag">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <span class="stage-category-label">INF.03 / Bazy danych</span>
                    </div>
                </div>

                <div class="stage-question">
                    <div class="stage-meter" aria-hidden="true"><span style="width: 75%"></span></div>
                    <div class="stage-q-number">Pytanie pokazowe • Wybierz poprawną odpowiedź:</div>
                    <h2 class="stage-q-title">Jaką rolę pełni klucz obcy (FOREIGN KEY) w relacyjnej bazie danych?</h2>
                    
                    <div class="stage-options" role="group" aria-label="Opcje odpowiedzi">
                        <button type="button" class="stage-option-btn" data-opt="0">
                            <span class="opt-letter">A</span>
                            <span class="opt-text">Szyfruje wybrane kolumny w tabeli</span>
                        </button>
                        <button type="button" class="stage-option-btn is-target-correct" data-opt="1">
                            <span class="opt-letter">B</span>
                            <span class="opt-text">Łączy tabele i wymusza integralność referencyjną</span>
                        </button>
                        <button type="button" class="stage-option-btn" data-opt="2">
                            <span class="opt-letter">C</span>
                            <span class="opt-text">Automatycznie usuwa zduplikowane rekordy</span>
                        </button>
                        <button type="button" class="stage-option-btn" data-opt="3">
                            <span class="opt-letter">D</span>
                            <span class="opt-text">Zmienia dynamicznie typ danych w zapytaniu</span>
                        </button>
                    </div>

                    <div class="stage-feedback" role="status" aria-live="polite"></div>
                </div>

                <!-- Instant Access Code Join Box -->
                <form method="POST" action="actions/start_guest.php" class="stage-code-form">
                    <?php echo csrfTokenField('guest_start'); ?>
                    <input type="hidden" name="target" value="exam">
                    <div class="stage-code-label">
                        <i class="bi bi-key-fill text-warning"></i>
                        <span>Masz kod od nauczyciela? <strong>Dołącz do sprawdzianu:</strong></span>
                    </div>
                    <div class="stage-code-input-group">
                        <input id="landingAccessCode" name="access_code" inputmode="latin" maxlength="12" placeholder="Wpisz PIN (np. ZS8K92)" autocomplete="off" spellcheck="false">
                        <button type="submit" class="btn-join-pin" aria-label="Wejdź do sprawdzianu">
                            <span>Dołącz</span>
                            <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- STATS COUNTER BANNER -->
    <section class="landing-stats-banner" aria-label="Statystyki platformy">
        <div class="stats-grid">
            <div class="stat-item reveal-on-scroll">
                <div class="stat-number">5 000+</div>
                <div class="stat-label">Pytań Egzaminacyjnych CKE</div>
                <div class="stat-sub">Z oficjalnymi arkuszami i kluczami</div>
            </div>
            <div class="stat-item reveal-on-scroll">
                <div class="stat-number">4+</div>
                <div class="stat-label">Kwalifikacje Zawodowe</div>
                <div class="stat-sub">INF.02, INF.03, INF.04, EE.08/09</div>
            </div>
            <div class="stat-item reveal-on-scroll">
                <div class="stat-number">100%</div>
                <div class="stat-label">Nowa Podstawa Programowa</div>
                <div class="stat-sub">Aktualizacja pytań na rok 2026</div>
            </div>
            <div class="stat-item reveal-on-scroll">
                <div class="stat-number">0 zł</div>
                <div class="stat-label">Bezpłatny Dostęp dla Wszystkich</div>
                <div class="stat-sub">Stworzone przez uczniów dla uczniów</div>
            </div>
        </div>
    </section>

    <!-- QUALIFICATIONS SHOWCASE SECTION -->
    <section class="landing-qualifications-section" id="kwalifikacje" aria-label="Kwalifikacje CKE">
        <div class="landing-container">
            <div class="section-header reveal-on-scroll">
                <div class="section-badge"><i class="bi bi-journal-code"></i> Kwalifikacje CKE</div>
                <h2 class="section-title">Wybierz swoją specjalizację techniczną</h2>
                <p class="section-desc">Pytania podzielone na logiczne kategorie i moduły. Trenuj całe arkusze lub pojedyncze zagadnienia, które sprawiają Ci trudność.</p>
            </div>

            <div class="qualifications-grid">
                <!-- INF.02 -->
                <article class="qual-card reveal-on-scroll">
                    <div class="qual-card-glow"></div>
                    <div class="qual-header">
                        <div class="qual-icon qual-icon-blue"><i class="bi bi-router"></i></div>
                        <span class="qual-tag">Sprzęt &amp; Sieci</span>
                    </div>
                    <h3 class="qual-title">INF.02</h3>
                    <div class="qual-full-name">Administracja systemami operacyjnymi i sieciami komputerowymi</div>
                    <p class="qual-desc">Montaż i konfiguracja komputerów, diagnostyka usterek, konfiguracja switchy/routerów Cisco, Windows Server, Linux, adresacja IPv4/IPv6 i bezpieczeństwo sieciowe.</p>
                    <div class="qual-skills">
                        <span>Linux</span>
                        <span>Windows Server</span>
                        <span>Routing &amp; VLAN</span>
                        <span>IPv4 / IPv6</span>
                    </div>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-database-check me-1"></i> Baza pytań CKE</span>
                        <a href="categories.php?code=INF.02" class="qual-link">Ćwicz kwalifikację <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>

                <!-- INF.03 -->
                <article class="qual-card reveal-on-scroll">
                    <div class="qual-card-glow"></div>
                    <div class="qual-header">
                        <div class="qual-icon qual-icon-indigo"><i class="bi bi-code-slash"></i></div>
                        <span class="qual-tag">Web &amp; Bazy</span>
                    </div>
                    <h3 class="qual-title">INF.03</h3>
                    <div class="qual-full-name">Tworzenie i administrowanie stronami i bazami danych</div>
                    <p class="qual-desc">Programowanie frontend i backend, relacyjne bazy danych SQL (MySQL/MariaDB), HTML5/CSS3, JavaScript, PHP, systemy CMS oraz bezpieczeństwo aplikacji webowych.</p>
                    <div class="qual-skills">
                        <span>JavaScript</span>
                        <span>PHP &amp; MySQL</span>
                        <span>HTML5 / CSS3</span>
                        <span>Projektowanie UI</span>
                    </div>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-database-check me-1"></i> Baza pytań CKE</span>
                        <a href="categories.php?code=INF.03" class="qual-link">Ćwicz kwalifikację <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>

                <!-- INF.04 -->
                <article class="qual-card reveal-on-scroll">
                    <div class="qual-card-glow"></div>
                    <div class="qual-header">
                        <div class="qual-icon qual-icon-purple"><i class="bi bi-cpu"></i></div>
                        <span class="qual-tag">Aplikacje Desktop &amp; Mobile</span>
                    </div>
                    <h3 class="qual-title">INF.04</h3>
                    <div class="qual-full-name">Projektowanie, programowanie i testowanie aplikacji</div>
                    <p class="qual-desc">Programowanie obiektowe w językach C++, Java, C# i Python, algorytmy i struktury danych, testy jednostkowe, wzorce projektowe i aplikacje mobilne.</p>
                    <div class="qual-skills">
                        <span>C++ / C#</span>
                        <span>Java &amp; Python</span>
                        <span>OOP &amp; Algorytmy</span>
                        <span>Unit Testy</span>
                    </div>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-database-check me-1"></i> Baza pytań CKE</span>
                        <a href="categories.php?code=INF.04" class="qual-link">Ćwicz kwalifikację <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>

                <!-- EE.08 / EE.09 -->
                <article class="qual-card reveal-on-scroll">
                    <div class="qual-card-glow"></div>
                    <div class="qual-header">
                        <div class="qual-icon qual-icon-emerald"><i class="bi bi-archive"></i></div>
                        <span class="qual-tag">Poprzednia Podstawa</span>
                    </div>
                    <h3 class="qual-title">EE.08 / EE.09</h3>
                    <div class="qual-full-name">Montaż i programowanie systemów komputerowych</div>
                    <p class="qual-desc">Kompletne archiwum pytań CKE z kwalifikacji EE.08 (montaż i eksploatacja) oraz EE.09 (programowanie aplikacji i bazy danych). Doskonałe źródło dodatkowych powtórek.</p>
                    <div class="qual-skills">
                        <span>Archiwum CKE</span>
                        <span>Teleinformatyka</span>
                        <span>Serwery</span>
                        <span>Podstawy baz</span>
                    </div>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-database-check me-1"></i> Baza pytań CKE</span>
                        <a href="categories.php?code=EE.08" class="qual-link">Ćwicz kwalifikację <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- BENTO GRID FEATURES SECTION -->
    <section class="landing-bento-section" id="funkcje" aria-label="Możliwości platformy">
        <div class="landing-container">
            <div class="section-header reveal-on-scroll">
                <div class="section-badge"><i class="bi bi-stars"></i> Funkcjonalności 2.0</div>
                <h2 class="section-title">Wszystko, czego potrzebujesz do nauki</h2>
                <p class="section-desc">Nowoczesny zestaw narzędzi edukacyjnych stworzony specjalnie dla uczniów technikum i nauczycieli informatyki.</p>
            </div>

            <div class="bento-grid">
                <!-- Bento 1: Pojedynki 1v1 (Large) -->
                <article class="bento-card bento-large bento-duels reveal-on-scroll">
                    <div class="bento-glow"></div>
                    <div class="bento-content">
                        <div class="bento-icon"><i class="bi bi-swords"></i></div>
                        <span class="bento-badge">Tryb Multiplayer</span>
                        <h3 class="bento-title">Pojedynki 1v1 na Żywo w Czasie Rzeczywistym</h3>
                        <p class="bento-desc">Wyzwij kolegę z ławki na pojedynek wiedzy informatycznej! Odpowiadajcie na te same pytania pod presją czasu, zdobywajcie punkty doświadczenia XP i awansujcie w szkolnym rankingu ZSEM.</p>
                        <div class="bento-tags">
                            <span><i class="bi bi-stopwatch"></i> Rundy na czas</span>
                            <span><i class="bi bi-trophy"></i> Rangi XP</span>
                            <span><i class="bi bi-people"></i> Zaproś znajomego</span>
                        </div>
                    </div>
                </article>

                <!-- Bento 2: Inteligentna Praktyka -->
                <article class="bento-card reveal-on-scroll">
                    <div class="bento-glow"></div>
                    <div class="bento-content">
                        <div class="bento-icon"><i class="bi bi-bullseye"></i></div>
                        <span class="bento-badge">Tryb Nauki</span>
                        <h3 class="bento-title">Inteligentny Symulator Praktyki</h3>
                        <p class="bento-desc">Pytania z natychmiastowym wyjaśnieniem dlaczego dana odpowiedź jest poprawna. System zapamiętuje Twoje błędy i powtarza je do pełnego opanowania.</p>
                    </div>
                </article>

                <!-- Bento 3: Fiszki & Słownik IT -->
                <article class="bento-card reveal-on-scroll">
                    <div class="bento-glow"></div>
                    <div class="bento-content">
                        <div class="bento-icon"><i class="bi bi-card-text"></i></div>
                        <span class="bento-badge">Powtórka</span>
                        <h3 class="bento-title">Interaktywne Fiszki i Słownik</h3>
                        <p class="bento-desc">Szybkie powtórki definicji, kodów odpowiedzi HTTP, portów sieciowych, poleceń Linuxa oraz komend SQL przed każdą lekcją lub sprawdzianem.</p>
                    </div>
                </article>

                <!-- Bento 4: Sprawdziany szkolne z kodem PIN -->
                <article class="bento-card reveal-on-scroll">
                    <div class="bento-glow"></div>
                    <div class="bento-content">
                        <div class="bento-icon"><i class="bi bi-shield-check"></i></div>
                        <span class="bento-badge">Dla Klasy</span>
                        <h3 class="bento-title">Sprawdziany Online z Anty-Cheat</h3>
                        <p class="bento-desc">Dołączaj do sprawdzianów organizowanych przez nauczyciela za pomocą 6-znakowego kodu PIN. Wbudowany moduł wykrywania utraty focusu i automatyczne podliczanie ocen.</p>
                    </div>
                </article>

                <!-- Bento 5: Generator PDF dla nauczycieli -->
                <article class="bento-card reveal-on-scroll">
                    <div class="bento-glow"></div>
                    <div class="bento-content">
                        <div class="bento-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                        <span class="bento-badge">Dla Nauczycieli</span>
                        <h3 class="bento-title">Generator Sprawdzianów PDF</h3>
                        <p class="bento-desc">Narzędzie umożliwiające wygenerowanie w kilka sekund gotowych do druku arkuszy sprawdzianu Grupa A i B wraz z kluczem odpowiedzi dla nauczyciela.</p>
                    </div>
                </article>

                <!-- Bento 6: Kursy i Certyfikaty (Large) -->
                <article class="bento-card bento-large bento-courses reveal-on-scroll">
                    <div class="bento-glow"></div>
                    <div class="bento-content">
                        <div class="bento-icon"><i class="bi bi-award-fill"></i></div>
                        <span class="bento-badge">Rozwój Kariery</span>
                        <h3 class="bento-title">Interaktywne Kursy z Cyfrowym Certyfikatem</h3>
                        <p class="bento-desc">Przechodź ustrukturyzowane lekcje przygotowane przez społeczność ZSEM. Po ukończeniu każdego kursu generuj imienny certyfikat z unikalnym numerem weryfikacyjnym do swojego CV lub LinkedIn.</p>
                        <div class="bento-tags">
                            <span><i class="bi bi-patch-check"></i> Weryfikacja online</span>
                            <span><i class="bi bi-file-earmark-code"></i> Praktyczne przykłady</span>
                            <span><i class="bi bi-share"></i> Eksport do PDF</span>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ROLES & MODES COMPARISON -->
    <section class="landing-band" id="tryby" aria-label="Porównanie trybów pracy">
        <div class="landing-container">
            <div class="band-intro reveal-on-scroll">
                <div class="section-badge"><i class="bi bi-people-fill"></i> Dostępność dla każdego</div>
                <h2 class="band-title">Graj jako gość lub załóż konto z pełnym progresem</h2>
                <p class="band-desc">ZSEM Tech został zaprojektowany tak, aby każdy mógł zacząć naukę od ręki bez żadnych barier.</p>
            </div>

            <div class="landing-mode-grid">
                <!-- Gość -->
                <article class="mode-card reveal-on-scroll">
                    <div class="mode-icon mode-icon-green"><i class="bi bi-incognito"></i></div>
                    <div class="mode-body">
                        <div class="mode-header">
                            <h3 class="mode-title">Tryb Gościa</h3>
                            <span class="mode-chip">Bez rejestracji</span>
                        </div>
                        <p class="mode-text">Błyskawiczny start w 3 sekundy. Idealny gdy chcesz szybko rozwiązać jeden test na szkolnym komputerze lub wejść na sprawdzian kodem PIN.</p>
                        <ul class="mode-features">
                            <li><i class="bi bi-check2 text-success"></i> Rozwiązywanie wszystkich testów CKE</li>
                            <li><i class="bi bi-check2 text-success"></i> Dołączanie do sprawdzianów PIN</li>
                            <li><i class="bi bi-x text-muted"></i> Brak zapisu historii po zamknięciu karty</li>
                        </ul>
                    </div>
                </article>

                <!-- Uczeń -->
                <article class="mode-card featured reveal-on-scroll">
                    <div class="featured-ribbon">Rekomendowane</div>
                    <div class="mode-icon mode-icon-indigo"><i class="bi bi-mortarboard-fill"></i></div>
                    <div class="mode-body">
                        <div class="mode-header">
                            <h3 class="mode-title">Konto Ucznia</h3>
                            <span class="mode-chip chip-primary">100% Darmowe</span>
                        </div>
                        <p class="mode-text">Pełna analityka Twoich postępów, statystyki opanowania pytań (% Mastery), historia wyników, odznaki, pojedynki 1v1 i certyfikaty.</p>
                        <ul class="mode-features">
                            <li><i class="bi bi-check2 text-success"></i> Zapis historii, błędów i statystyk</li>
                            <li><i class="bi bi-check2 text-success"></i> Grywalizacja, rangi XP i pojedynki 1v1</li>
                            <li><i class="bi bi-check2 text-success"></i> Misje codzienne i certyfikaty kursów</li>
                        </ul>
                    </div>
                </article>

                <!-- Nauczyciel -->
                <article class="mode-card reveal-on-scroll">
                    <div class="mode-icon mode-icon-purple"><i class="bi bi-person-video3"></i></div>
                    <div class="mode-body">
                        <div class="mode-header">
                            <h3 class="mode-title">Panel Nauczyciela</h3>
                            <span class="mode-chip chip-purple">Dla Szkół</span>
                        </div>
                        <p class="mode-text">Kompletne narzędzie do prowadzenia sprawdzianów i kartkówek w pracowni komputerowej z podglądem na żywo i automatyczną oceną.</p>
                        <ul class="mode-features">
                            <li><i class="bi bi-check2 text-success"></i> Tworzenie sesji sprawdzianu z PIN</li>
                            <li><i class="bi bi-check2 text-success"></i> Monitorowanie klasy w czasie rzeczywistym</li>
                            <li><i class="bi bi-check2 text-success"></i> Generator arkuszy PDF A/B do druku</li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- STEP BY STEP PROCESS -->
    <section class="landing-product" id="kroki" aria-label="Jak to działa">
        <div class="landing-container">
            <div class="product-layout">
                <div class="product-copy reveal-on-scroll">
                    <div class="section-badge"><i class="bi bi-signpost-2-fill"></i> Prosty schemat</div>
                    <h2>Jak zdać egzamin zawodowy krok po kroku?</h2>
                    <p>Skuteczna metoda oparta na systematycznych powtórkach, analityce błędów i praktycznych pytaniach CKE.</p>
                    
                    <div class="product-cta mt-4">
                        <a href="auth/register.php" class="btn btn-primary-hero"><i class="bi bi-play-fill"></i> Zacznij naukę teraz</a>
                    </div>
                </div>

                <div class="product-rail">
                    <article class="step-card reveal-on-scroll">
                        <div class="step-number">01</div>
                        <div class="step-body">
                            <h3 class="step-title">Wybierz kwalifikację lub wpisz kod PIN</h3>
                            <p class="step-desc">Wybierz INF.02, INF.03, INF.04 lub EE.08/09. Jeśli nauczyciel rozpoczął sprawdzian w klasie, wpisz kod na stronie głównej.</p>
                        </div>
                    </article>

                    <article class="step-card reveal-on-scroll">
                        <div class="step-number">02</div>
                        <div class="step-body">
                            <h3 class="step-title">Rozwiązuj pytania w symulatorze</h3>
                            <p class="step-desc">Pracuj w czytelnym interfejsie wzorowanym na oficjalnym egzaminie CKE lub trenuj w trybie praktyki z natychmiastowym feedbackiem.</p>
                        </div>
                    </article>

                    <article class="step-card reveal-on-scroll">
                        <div class="step-number">03</div>
                        <div class="step-body">
                            <h3 class="step-title">Poznaj szczegółowe wyjaśnienie</h3>
                            <p class="step-desc">Nie tylko dowiesz się, która odpowiedź jest prawidłowa, ale zrozumiesz <em>dlaczego</em>. Ucz się na błędach bez zgadywania.</p>
                        </div>
                    </article>

                    <article class="step-card reveal-on-scroll">
                        <div class="step-number">04</div>
                        <div class="step-body">
                            <h3 class="step-title">Śledź postępy i zdobywaj certyfikaty</h3>
                            <p class="step-desc">Konto automatycznie zapisuje historię, wylicza Twój wskaźnik zdawalności i wskazuje działy, które warto powtórzyć.</p>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ SECTION -->
    <section class="landing-faq-section" id="faq" aria-label="Często zadawane pytania">
        <div class="landing-container">
            <div class="section-header reveal-on-scroll">
                <div class="section-badge"><i class="bi bi-question-circle-fill"></i> Pomoc &amp; FAQ</div>
                <h2 class="section-title">Najczęściej zadawane pytania</h2>
                <p class="section-desc">Wszystko, co musisz wiedzieć o działaniu platformy ZSEM Tech.</p>
            </div>

            <div class="faq-accordion">
                <details class="faq-item reveal-on-scroll">
                    <summary class="faq-question">
                        <span>Czy korzystanie z platformy ZSEM Tech jest całkowicie bezpłatne?</span>
                        <i class="bi bi-chevron-down faq-chevron"></i>
                    </summary>
                    <div class="faq-answer">
                        <p><strong>Tak, w 100% bezpłatne.</strong> ZSEM Tech to projekt edukacyjny stworzony przez uczniów i nauczycieli Zespołu Szkół Elektryczno-Mechanicznych w Nowym Sączu. Nie pobieramy żadnych opłat, nie wyświetlamy irytujących reklam ani nie wymagamy subskrypcji.</p>
                    </div>
                </details>

                <details class="faq-item reveal-on-scroll">
                    <summary class="faq-question">
                        <span>Czy muszę zakładać konto, aby poćwiczyć pytania?</span>
                        <i class="bi bi-chevron-down faq-chevron"></i>
                    </summary>
                    <div class="faq-answer">
                        <p>Nie! Dzięki <strong>Trybowi Gościa</strong> możesz od razu uruchomić pełny test egzaminacyjny lub dołączyć do sprawdzianu szkolnego kodem PIN. Założenie konta jest jednak zalecane, jeśli chcesz zbierać punkty XP, zachować historię i śledzić statystyki opanowania pytań.</p>
                    </div>
                </details>

                <details class="faq-item reveal-on-scroll">
                    <summary class="faq-question">
                        <span>Jak dołączyć do sprawdzianu utworzonego przez nauczyciela?</span>
                        <i class="bi bi-chevron-down faq-chevron"></i>
                    </summary>
                    <div class="faq-answer">
                        <p>Wystarczy wpisać 6-znakowy kod PIN podany przez nauczyciela w polu na samej górze strony głównej lub przejść do podstrony <a href="exam/join.php">Kod sprawdzianu</a>. Zostaniesz automatycznie przekierowany do aktywnej sesji sprawdzianu.</p>
                    </div>
                </details>

                <details class="faq-item reveal-on-scroll">
                    <summary class="faq-question">
                        <span>Skąd pochodzą pytania w bazie i czy są aktualne?</span>
                        <i class="bi bi-chevron-down faq-chevron"></i>
                    </summary>
                    <div class="faq-answer">
                        <p>Baza zawiera ponad 5 000 autentycznych pytań z oficjalnych sesji egzaminacyjnych Centralnej Komisji Egzaminacyjnej (CKE) z lat ubiegłych oraz pytań przygotowanych zgodnie z nową podstawą programową 2026 dla technika informatyka i programisty.</p>
                    </div>
                </details>

                <details class="faq-item reveal-on-scroll">
                    <summary class="faq-question">
                        <span>Jak działają pojedynki 1v1 na żywo?</span>
                        <i class="bi bi-chevron-down faq-chevron"></i>
                    </summary>
                    <div class="faq-answer">
                        <p>Po zalogowaniu możesz rzucić wyzwanie dowolnemu użytkownikowi platformy lub znajomemu z klasy. Obaj gracze odpowiadają na ten sam zestaw 5-10 pytań z wybranej kwalifikacji. Liczy się poprawność i czas odpowiedzi. Zwycięzca zdobywa punkty rankingowe i awansuje w szkolnej tabeli wyników.</p>
                    </div>
                </details>

                <details class="faq-item reveal-on-scroll">
                    <summary class="faq-question">
                        <span>Czy certyfikat ukończenia kursu można dodać do CV lub LinkedIn?</span>
                        <i class="bi bi-chevron-down faq-chevron"></i>
                    </summary>
                    <div class="faq-answer">
                        <p>Tak! Każdy wygenerowany certyfikat posiada swój unikalny identyfikator kryptograficzny oraz publiczny adres URL na stronie <a href="verify_certificate.php">Weryfikacji certyfikatów</a>, dzięki czemu przyszły pracodawca lub nauczyciel może potwierdzić jego autentyczność.</p>
                    </div>
                </details>
            </div>
        </div>
    </section>

    <!-- CREATORS / AUTHORS SECTION -->
    <section class="landing-creators" id="tworcy" aria-label="Twórcy platformy">
        <div class="landing-container">
            <div class="creators-layout">
                <div class="creators-copy reveal-on-scroll">
                    <div class="section-badge"><i class="bi bi-code-square"></i> Zespół ZSEM Tech</div>
                    <h2>Twórcy platformy</h2>
                    <p>ZSEM Tech to autorski projekt edukacyjny rozwijany w Zespole Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia w Nowym Sączu. Poznaj autorów stojących za architekturą i rozwojem platformy.</p>
                    
                    <div class="school-affiliation-card mt-4">
                        <div class="school-icon"><i class="bi bi-building"></i></div>
                        <div class="school-info">
                            <strong>Zespół Szkół Elektryczno-Mechanicznych</strong>
                            <span>im. gen. Józefa Kustronia w Nowym Sączu</span>
                            <a href="https://zsem.edu.pl/" target="_blank" rel="noopener noreferrer" class="school-link">Odwiedź stronę szkoły <i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="creators-grid">
                    <!-- Damian Podgórski -->
                    <a href="pages/author_damian.php" class="creator-card reveal-on-scroll">
                        <div class="creator-avatar">DP</div>
                        <div class="creator-info">
                            <div class="creator-name-row">
                                <strong>Damian Podgórski</strong>
                                <span class="creator-badge">Lead Dev</span>
                            </div>
                            <span class="creator-title">Zastępca Przewodniczącego ZSEM Tech</span>
                            <span class="creator-role">Główny Programista &amp; Architekt Systemu</span>
                        </div>
                        <div class="creator-arrow"><i class="bi bi-arrow-right"></i></div>
                    </a>

                    <!-- Michał Michalik -->
                    <a href="pages/author_michal.php" class="creator-card reveal-on-scroll">
                        <div class="creator-avatar">MM</div>
                        <div class="creator-info">
                            <div class="creator-name-row">
                                <strong>Michał Michalik</strong>
                                <span class="creator-badge">Project Lead</span>
                            </div>
                            <span class="creator-title">Przewodniczący ZSEM Tech</span>
                            <span class="creator-role">Koordynator Projektu &amp; Współtwórca</span>
                        </div>
                        <div class="creator-arrow"><i class="bi bi-arrow-right"></i></div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- MODERN LANDING FOOTER -->
    <footer class="landing-footer" role="contentinfo" aria-label="Stopka strony">
        <div class="landing-container">
            <div class="footer-top-grid">
                <!-- Brand Info -->
                <div class="footer-brand-col">
                    <a href="landing.php" class="landing-brand mb-3">
                        <img src="zsemtech_profile.ico" alt="Logo ZSEM Tech" width="34" height="34" loading="lazy" decoding="async">
                        <span class="brand-text">ZSEM <span class="brand-accent">Tech</span></span>
                    </a>
                    <p class="footer-desc">
                        Nowoczesna platforma edukacyjna do przygotowania do egzaminów zawodowych CKE z kwalifikacji informatycznych i programistycznych. Projekt non-profit społeczności ZSEM w Nowym Sączu.
                    </p>
                    <div class="footer-social-links">
                        <a href="https://www.facebook.com/people/ZSEM-Tech/61556411931896/" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="Facebook ZSEM Tech"><i class="bi bi-facebook"></i></a>
                        <a href="https://x.com/zsem_tech" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="X ZSEM Tech"><i class="bi bi-twitter-x"></i></a>
                        <a href="https://www.instagram.com/zsem.tech" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="Instagram ZSEM Tech"><i class="bi bi-instagram"></i></a>
                        <a href="https://www.tiktok.com/@zsem.tech" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="TikTok ZSEM Tech"><i class="bi bi-tiktok"></i></a>
                        <a href="https://zsem.edu.pl/" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="Oficjalna strona ZSEM"><i class="bi bi-globe2"></i></a>
                    </div>
                </div>

                <!-- Navigation links: Platforma -->
                <div class="footer-links-col">
                    <h4 class="footer-heading">Platforma</h4>
                    <ul class="footer-nav-list">
                        <li><a href="categories.php"><i class="bi bi-chevron-right small"></i> Kwalifikacje CKE</a></li>
                        <li><a href="practice.php"><i class="bi bi-chevron-right small"></i> Inteligentna Praktyka</a></li>
                        <li><a href="test.php"><i class="bi bi-chevron-right small"></i> Symulator Egzaminu</a></li>
                        <li><a href="flashcards.php"><i class="bi bi-chevron-right small"></i> Fiszki IT</a></li>
                        <li><a href="dictionary.php"><i class="bi bi-chevron-right small"></i> Słownik Pojęć</a></li>
                        <li><a href="ranking.php"><i class="bi bi-chevron-right small"></i> Ranking Uczniów</a></li>
                        <li><a href="courses.php"><i class="bi bi-chevron-right small"></i> Kursy &amp; Certyfikaty</a></li>
                    </ul>
                </div>

                <!-- Navigation links: Dostęp & Sprawdziany -->
                <div class="footer-links-col">
                    <h4 class="footer-heading">Dostęp &amp; Narzędzia</h4>
                    <ul class="footer-nav-list">
                        <li><a href="exam/join.php"><i class="bi bi-chevron-right small"></i> Dołącz do sprawdzianu</a></li>
                        <li><a href="auth/login.php"><i class="bi bi-chevron-right small"></i> Zaloguj się</a></li>
                        <li><a href="auth/register.php"><i class="bi bi-chevron-right small"></i> Załóż konto ucznia</a></li>
                        <li><a href="verify_certificate.php"><i class="bi bi-chevron-right small"></i> Weryfikacja certyfikatu</a></li>
                        <li><a href="pages/careers.php"><i class="bi bi-chevron-right small"></i> Kariery w IT</a></li>
                        <li><a href="pages/author_damian.php"><i class="bi bi-chevron-right small"></i> Damian Podgórski</a></li>
                        <li><a href="pages/author_michal.php"><i class="bi bi-chevron-right small"></i> Michał Michalik</a></li>
                    </ul>
                </div>

                <!-- Navigation links: Bezpieczeństwo & Zgodność -->
                <div class="footer-links-col">
                    <h4 class="footer-heading">Informacje &amp; Prawo</h4>
                    <ul class="footer-nav-list">
                        <li><a href="pages/privacy.php"><i class="bi bi-chevron-right small"></i> Polityka Prywatności</a></li>
                        <li><a href="pages/polityka-cookies.php"><i class="bi bi-chevron-right small"></i> Polityka Cookies</a></li>
                        <li><a href="pages/terms.php"><i class="bi bi-chevron-right small"></i> Regulamin Serwisu</a></li>
                        <li><a href="pages/dostepnosc.php"><i class="bi bi-chevron-right small"></i> Deklaracja Dostępności</a></li>
                        <li><a href="pages/zglos-naruszenie.php"><i class="bi bi-chevron-right small"></i> Zgłoś Naruszenie</a></li>
                        <li><a href="pages/contact.php"><i class="bi bi-chevron-right small"></i> Kontakt z Administracją</a></li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom Bar -->
            <div class="footer-bottom-bar">
                <div class="footer-copy">
                    &copy; 2024–2026 <strong>ZSEM Tech</strong>. Wszystkie prawa zastrzeżone. Projekt edukacyjny ZSEM w Nowym Sączu.
                </div>
                <div class="footer-meta">
                    <span><i class="bi bi-shield-check text-success"></i> Bezpieczne połączenie SSL</span>
                    <span><i class="bi bi-code-slash text-primary"></i> Wersja 2026.1</span>
                </div>
            </div>
        </div>
    </footer>
</main>

<script src="assets/js/landing.js"></script>
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
