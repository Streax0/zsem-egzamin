<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';
startSecureSession();
?>
<?php
$pageTitle = 'ZSEM Tech';
$extraCss = ['assets/css/landing.css'];
$extraHead = '
    <meta name="description" content="ZSEM Tech: testy INF, sprawdziany nauczyciela, tryb gościa i narzędzia nauki dla uczniów.">
    <meta property="og:title" content="ZSEM Tech">
    <meta property="og:description" content="Platforma edukacyjna do testów INF, sprawdzianów i praktyki technicznej.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zsem-egzamin.online/">
    <meta property="og:image" content="https://zsem-egzamin.online/zsemtech_profile.ico">
';
include 'includes/header.php';
?>
<main class="landing-page">
    <section class="landing-hero">
        <nav class="landing-nav" aria-label="Główna nawigacja">
            <a href="landing.php" class="landing-brand" aria-label="ZSEM Tech">
                <img src="zsemtech_profile.ico" alt="" width="36" height="36" loading="lazy" decoding="async">
                <span>ZSEM Tech</span>
            </a>
            <div class="landing-nav-actions">
                <a href="exam/join.php" class="btn btn-ghost-light"><i class="bi bi-qr-code-scan"></i>Kod</a>
                <a href="auth/login.php" class="btn btn-ghost-light">Zaloguj</a>
                <a href="auth/register.php" class="btn btn-solid-light">Konto</a>
            </div>
        </nav>

        <div class="landing-hero-grid">
            <div class="landing-copy">
                <h1>ZSEM Tech</h1>
                <p>Testy INF i sprawdziany nauczyciela.<br>Tryb gościa bez historii konta.</p>
                <div class="landing-actions">
                    <a href="auth/login.php" class="btn btn-primary-hero"><i class="bi bi-box-arrow-in-right"></i>Zaloguj się</a>
                    <form method="POST" action="actions/start_guest.php" class="m-0">
                        <?php echo csrfTokenField('guest_start'); ?>
                        <input type="hidden" name="target" value="test">
                        <button type="submit" class="btn btn-outline-hero"><i class="bi bi-incognito"></i>Tryb gościa</button>
                    </form>
                </div>
                <div class="landing-rules">
                    <span><i class="bi bi-check2-circle"></i> Gość rozwiązuje testy</span>
                    <span><i class="bi bi-shield-lock"></i> Konto zapisuje progres</span>
                    <span><i class="bi bi-qr-code"></i> Sprawdzian po kodzie</span>
                </div>
            </div>

            <div class="landing-stage" aria-label="Podgląd platformy">
                <div class="stage-header">
                    <span></span><span></span><span></span>
                    <strong>INF.03 / sesja live</strong>
                </div>
                <div class="stage-question">
                    <div class="stage-meter"><span style="width:72%"></span></div>
                    <h2>Jaką rolę pełni klucz obcy w relacyjnej bazie danych?</h2>
                    <div class="stage-options">
                        <span>A. Szyfruje rekord</span>
                        <span class="is-correct">B. Łączy tabele</span>
                        <span>C. Usuwa duplikaty</span>
                        <span>D. Zmienia indeks</span>
                    </div>
                </div>
                <form method="POST" action="actions/start_guest.php" class="stage-code">
                    <?php echo csrfTokenField('guest_start'); ?>
                    <input type="hidden" name="target" value="exam">
                    <label for="landingAccessCode">Kod sprawdzianu</label>
                    <div>
                        <input id="landingAccessCode" name="access_code" inputmode="latin" maxlength="20" placeholder="A7K9P2">
                        <button type="submit" aria-label="Zatwierdź kod sprawdzianu"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="landing-hero-overlay-fade"></div>
    </section>

    <section class="landing-band">
        <div>
            <h2>Gość bez konta. Konto z pełnym progresem.</h2>
            <p>Gość działa tylko w bieżącej sesji przeglądarki: brak historii, rankingu, misji i społeczności. Po zalogowaniu wracają wszystkie funkcje konta.</p>
        </div>
        <div class="landing-mode-grid">
            <article><i class="bi bi-incognito"></i><strong>Gość</strong><span>Testy i kod sprawdzianu. Brak profilu i historii.</span></article>
            <article><i class="bi bi-mortarboard"></i><strong>Uczeń</strong><span>Historia, misje, ranking, duele i statystyki.</span></article>
            <article><i class="bi bi-person-video3"></i><strong>Nauczyciel</strong><span>Test online, arkusze do druku, sesje i wyniki.</span></article>
        </div>
    </section>

    <section class="landing-product">
        <div class="product-copy">
            <h2>Platforma do realnej pracy przed egzaminem</h2>
            <p>Krótki test, pełny egzamin, sprawdzian z kodem, pytania z wyjaśnieniami i panele dla nauczyciela oraz administratora.</p>
        </div>
        <div class="product-rail">
            <article><span>01</span><strong>Wybierz tryb</strong><p>Egzamin, trening, jedno pytanie albo sprawdzian nauczyciela.</p></article>
            <article><span>02</span><strong>Rozwiąż</strong><p>Bez rozpraszania, z licznikami czasu i klarownymi odpowiedziami.</p></article>
            <article><span>03</span><strong>Zobacz wynik</strong><p>Konto zapisze progres, gość zobaczy wynik tylko w sesji.</p></article>
        </div>
    </section>

    <section class="landing-creators">
        <div class="creators-copy">
            <h2>Twórcy platformy</h2>
            <p>ZSEM Tech to projekt uczniów Zespołu Szkół Elektryczno-Mechanicznych w Nowym Sączu. Poznaj osoby stojące za platformą.</p>
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
<?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
