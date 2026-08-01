<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';
startSecureSession();
?>
<?php
$pageTitle = 'ZSEM Tech - Platforma Edukacyjna i Testy INF';
$extraCss = ['assets/css/landing.css'];
$extraHead = '
    <meta name="description" content="ZSEM Tech: testy INF.02, INF.03, EE.08, EE.09, sprawdziany nauczyciela, tryb gościa i zaawansowane narzędzia do nauki dla uczniów.">
    <meta property="og:title" content="ZSEM Tech - Platforma Edukacyjna i Testy INF">
    <meta property="og:description" content="Platforma ZSEM Tech do przygotowania do egzaminów zawodowych INF/EE, sprawdzianów i praktyki technicznej.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zsem-egzamin.online/">
    <meta property="og:image" content="https://zsem-egzamin.online/zsemtech_profile.ico">
';
include 'includes/header.php';
?>
<main class="landing-page">
    <section class="landing-hero">
        <div class="hero-glow-orb-1" aria-hidden="true"></div>
        <div class="hero-glow-orb-2" aria-hidden="true"></div>

        <nav class="landing-nav" aria-label="Główna nawigacja">
            <a href="landing.php" class="landing-brand" aria-label="ZSEM Tech Home">
                <img src="zsemtech_profile.ico" alt="" width="38" height="38" loading="lazy" decoding="async">
                <span>ZSEM Tech</span>
            </a>
            <div class="landing-nav-actions">
                <a href="categories.php" class="btn btn-ghost-light"><i class="bi bi-folder2-open"></i>Kwalifikacje</a>
                <a href="exam/join.php" class="btn btn-ghost-light"><i class="bi bi-qr-code-scan"></i>Kod sprawdzianu</a>
                <a href="auth/login.php" class="btn btn-ghost-light"><i class="bi bi-box-arrow-in-right"></i>Zaloguj się</a>
                <a href="auth/register.php" class="btn btn-solid-light"><i class="bi bi-person-plus-fill"></i>Załóż konto</a>
            </div>
        </nav>

        <div class="landing-hero-grid">
            <div class="landing-copy">
                <div class="hero-badge-tag">
                    <span class="badge-dot"></span> PROJEKT UCZNIOWSKI ZSEM • TESTY INF & KWALIFIKACJE 2026
                </div>
                <h1>Egzaminy INF i sprawdziany w jednym miejscu.</h1>
                <p>Przygotuj się do egzaminu zawodowego z bazą ponad 5000 pytań CKE, rozwiązuj sprawdziany nauczyciela po kodzie i ćwicz w trybie gościa bez zakładań konta.</p>
                
                <div class="landing-actions">
                    <a href="auth/login.php" class="btn btn-primary-hero"><i class="bi bi-rocket-takeoff-fill"></i>Zaloguj się do platformy</a>
                    <form method="POST" action="actions/start_guest.php" class="m-0">
                        <?php echo csrfTokenField('guest_start'); ?>
                        <input type="hidden" name="target" value="test">
                        <button type="submit" class="btn btn-outline-hero"><i class="bi bi-incognito"></i>Wypróbuj w trybie gościa</button>
                    </form>
                </div>
                
                <div class="landing-rules">
                    <span><i class="bi bi-check2-circle"></i> Szybki tryb gościa</span>
                    <span><i class="bi bi-shield-check"></i> Statystyki na koncie</span>
                    <span><i class="bi bi-qr-code-scan"></i> Sprawdzian po kodzie</span>
                </div>
            </div>

            <div class="landing-stage" aria-label="Podgląd interaktywny platformy">
                <div class="stage-header">
                    <span></span><span></span><span></span>
                    <div class="stage-tabs" role="tablist">
                        <button type="button" class="stage-tab-btn active" data-cat="inf03">INF.03</button>
                        <button type="button" class="stage-tab-btn" data-cat="inf02">INF.02</button>
                        <button type="button" class="stage-tab-btn" data-cat="ee09">EE.09</button>
                    </div>
                    <strong>INF.03 / Sesja Live</strong>
                </div>

                <div class="stage-question">
                    <div class="stage-meter"><span style="width:72%"></span></div>
                    <h2>Jaką rolę pełni klucz obcy (FOREIGN KEY) w relacyjnej bazie danych?</h2>
                    
                    <div class="stage-options">
                        <button type="button" class="stage-option-btn"><span class="opt-letter">A.</span> Szyfruje pola w tabeli</button>
                        <button type="button" class="stage-option-btn is-target-correct"><span class="opt-letter">B.</span> Łączy tabele i wymusza integralność</button>
                        <button type="button" class="stage-option-btn"><span class="opt-letter">C.</span> Usuwa zduplikowane rekordy</button>
                        <button type="button" class="stage-option-btn"><span class="opt-letter">D.</span> Zmienia typ danych kolumny</button>
                    </div>
                </div>

                <form method="POST" action="actions/start_guest.php" class="stage-code">
                    <?php echo csrfTokenField('guest_start'); ?>
                    <input type="hidden" name="target" value="exam">
                    <label for="landingAccessCode"><i class="bi bi-key-fill text-warning me-1"></i>Dołącz do sprawdzianu kodem</label>
                    <div>
                        <input id="landingAccessCode" name="access_code" inputmode="latin" maxlength="20" placeholder="Wpisz kod (np. A7K9P2)">
                        <button type="submit" aria-label="Dołącz do sprawdzianu"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="landing-hero-overlay-fade" aria-hidden="true"></div>
    </section>

    <!-- Statystyki platformy -->
    <section class="landing-stats-banner">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">5 000+</div>
                <div class="stat-label">Pytań Egzaminacyjnych CKE</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">4</div>
                <div class="stat-label">Główne Kwalifikacje INF / EE</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">100%</div>
                <div class="stat-label">Zgodność z Nową Podstawą</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">0 zł</div>
                <div class="stat-label">Darmowy Dostęp dla Wszystkich</div>
            </div>
        </div>
    </section>

    <!-- Przegląd Kwalifikacji Zawodowych -->
    <section class="landing-qualifications-section">
        <div class="landing-qualifications">
            <div class="section-header">
                <div class="section-badge"><i class="bi bi-journal-bookmark-fill"></i> Kwalifikacje CKE</div>
                <h2 class="section-title">Wybierz swoją kwalifikację</h2>
                <p class="section-desc">Ćwicz pytania podzielone tematycznie według oficjalnych kwalifikacji technicznych.</p>
            </div>

            <div class="qualifications-grid">
                <article class="qual-card">
                    <div class="qual-header">
                        <div class="qual-icon"><i class="bi bi-laptop"></i></div>
                        <span class="qual-tag">Sprzęt &amp; Sieci</span>
                    </div>
                    <h3 class="qual-title">INF.02</h3>
                    <p class="qual-desc">Administracja systemami operacyjnymi, konfiguracja sieci komputerowych i urządzenia sieciowe.</p>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-question-circle me-1"></i> Baza CKE</span>
                        <a href="categories.php?code=INF.02" class="qual-link">Rozpocznij <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>

                <article class="qual-card">
                    <div class="qual-header">
                        <div class="qual-icon"><i class="bi bi-code-slash"></i></div>
                        <span class="qual-tag">Web &amp; Bazy</span>
                    </div>
                    <h3 class="qual-title">INF.03</h3>
                    <p class="qual-desc">Tworzenie aplikacji internetowych, bazy danych SQL, HTML/CSS, JavaScript oraz PHP.</p>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-question-circle me-1"></i> Baza CKE</span>
                        <a href="categories.php?code=INF.03" class="qual-link">Rozpocznij <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>

                <article class="qual-card">
                    <div class="qual-header">
                        <div class="qual-icon"><i class="bi bi-cpu"></i></div>
                        <span class="qual-tag">Systemy &amp; Montaż</span>
                    </div>
                    <h3 class="qual-title">EE.08 / INF.04</h3>
                    <p class="qual-desc">Montaż i eksploatacja komputerów osobistych oraz urządzeń peryferyjnych.</p>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-question-circle me-1"></i> Baza CKE</span>
                        <a href="categories.php?code=EE.08" class="qual-link">Rozpocznij <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>

                <article class="qual-card">
                    <div class="qual-header">
                        <div class="qual-icon"><i class="bi bi-phone"></i></div>
                        <span class="qual-tag">Aplikacje Mobilne</span>
                    </div>
                    <h3 class="qual-title">EE.09</h3>
                    <p class="qual-desc">Programowanie aplikacji webowych i mobilnych oraz zarządzanie strukturą baz danych.</p>
                    <div class="qual-footer">
                        <span class="qual-count"><i class="bi bi-question-circle me-1"></i> Baza CKE</span>
                        <a href="categories.php?code=EE.09" class="qual-link">Rozpocznij <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- Sekcja Bento Grid z Funkcjami -->
    <section class="landing-bento-section">
        <div class="section-header">
            <div class="section-badge"><i class="bi bi-stars"></i> Możliwości Platformy</div>
            <h2 class="section-title">Wszystko, czego potrzebujesz do zdania egzaminu</h2>
            <p class="section-desc">Zaawansowane narzędzia zaprojektowane specjalnie dla uczniów i nauczycieli szkół technicznych.</p>
        </div>

        <div class="bento-grid">
            <article class="bento-card bento-large">
                <div class="bento-icon"><i class="bi bi-swords"></i></div>
                <h3>Pojedynki 1v1 na Żywo</h3>
                <p>Rywalizuj z rówieśnikami w czasie rzeczywistym. Rozwiązuj pytania egzaminacyjne pod presją czasu, zdobywaj punkty XP i awansuj w rankingu szkolnym.</p>
            </article>

            <article class="bento-card">
                <div class="bento-icon"><i class="bi bi-card-text"></i></div>
                <h3>Interaktywne Fiszki</h3>
                <p>Szybka powtórka najważniejszych pojęć i definicji przed egzaminem zawodowym.</p>
            </article>

            <article class="bento-card">
                <div class="bento-icon"><i class="bi bi-printer"></i></div>
                <h3>Generator Arkuszy PDF</h3>
                <p>Nauczyciele mogą generować gotowe sprawdziany i klucze odpowiedzi do druku w klika sekund.</p>
            </article>

            <article class="bento-card bento-large">
                <div class="bento-icon"><i class="bi bi-trophy"></i></div>
                <h3>Szkolny Ranking &amp; Osiągnięcia</h3>
                <p>Zdobywaj rangi od Nowicjusza do Eksperta ZSEM. Śledź swoje postępy dziennie i tygodniowo na tle całej społeczności ZSEM Tech.</p>
            </article>
        </div>
    </section>

    <!-- Sekcja Tryby Pracy (Band) -->
    <section class="landing-band">
        <div>
            <h2>Gość bez konta.<br>Konto z pełnym progresem.</h2>
            <p>Tryb gościa pozwala natychmiast rozwiązywać testy i sprawdziany w bieżącej sesji. Po założeniu konta zyskujesz dostęp do historii wyników, rankingu i misji.</p>
        </div>
        <div class="landing-mode-grid">
            <article>
                <i class="bi bi-incognito"></i>
                <strong>Tryb Gościa</strong>
                <span>Rozwiązywanie testów i kod sprawdzianu. Szybki dostęp bez rejestracji.</span>
            </article>
            <article>
                <i class="bi bi-mortarboard"></i>
                <strong>Konto Ucznia</strong>
                <span>Historia, analiza błędów, misje codzienne, duele 1v1 i ranga XP.</span>
            </article>
            <article>
                <i class="bi bi-person-video3"></i>
                <strong>Panel Nauczyciela</strong>
                <span>Tworzenie sesji sprawdzianów online, drukowanie PDF i podgląd wyników.</span>
            </article>
        </div>
    </section>

    <!-- Krok po Kroku (Product Rail) -->
    <section class="landing-product">
        <div class="product-copy">
            <h2>Jak to działa?</h2>
            <p>Przygotowanie do egzaminu zawodowego w czterech prostych krokach.</p>
        </div>
        <div class="product-rail">
            <article>
                <span>01</span>
                <strong>Wybierz tryb lub kod</strong>
                <p>Wybierz test kwalifikacji CKE lub wpisz kod dostępu podany przez nauczyciela.</p>
            </article>
            <article>
                <span>02</span>
                <strong>Rozwiązuj pytania</strong>
                <p>Pracuj w nowoczesnym interfejsie z licznikami czasu i czytelnym układem pytań.</p>
            </article>
            <article>
                <span>03</span>
                <strong>Zobacz natychmiastowe wyjaśnienie</strong>
                <p>Otrzymaj wynik i szczegółowe wyjaśnienia do każdego pytania.</p>
            </article>
            <article>
                <span>04</span>
                <strong>Śledź swój progres</strong>
                <p>Konto zapamięta Twoje wyniki, statystyki i pomoże wyeliminować słabe punkty.</p>
            </article>
        </div>
    </section>

    <!-- Twórcy Platformy -->
    <section class="landing-creators">
        <div class="creators-copy">
            <h2>Twórcy platformy</h2>
            <p>ZSEM Tech to autorski projekt uczniów Zespołu Szkół Elektryczno-Mechanicznych w Nowym Sączu. Poznaj osoby stojące za platformą.</p>
        </div>
        <div class="creators-grid">
            <a href="pages/author_damian.php" class="creator-card">
                <div class="creator-avatar">DP</div>
                <div class="creator-info">
                    <strong>Damian Podgórski</strong>
                    <span>Zastępca Przewodniczącego ZSEM Tech</span>
                    <span class="creator-role">Główny Programista &amp; Współtwórca</span>
                </div>
                <i class="bi bi-arrow-right"></i>
            </a>
            <a href="pages/author_michal.php" class="creator-card">
                <div class="creator-avatar">MM</div>
                <div class="creator-info">
                    <strong>Michał Michalik</strong>
                    <span>Przewodniczący ZSEM Tech</span>
                    <span class="creator-role">Koordynator Projektu &amp; Współtwórca</span>
                </div>
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </section>
</main>

<script src="assets/js/landing.js"></script>
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
