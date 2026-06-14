<?php
if (!isset($base_url)) {
    $base_url = file_exists('config/db.php') ? '' : '../';
}
?>
<footer class="main-footer mt-auto py-5 border-top" role="contentinfo">
    <div class="container-fluid px-4">
        <!-- Modern rotating call-to-action -->
        <div class="footer-cta-card mb-5 animate-in" id="footerRotatingCta">
            <div class="row align-items-center g-4 p-4 p-md-5">
                <div class="col-lg-8">
                    <span class="badge px-3 py-2 rounded-pill mb-3 fw-bold footer-cta-badge" style="background: rgba(255,255,255,0.2); color: white;">Platforma Edukacyjna</span>
                    <h3 class="text-white fw-800 mb-2 footer-cta-title">Gotowy na kolejny sprawdzian?</h3>
                    <p class="text-white mb-0 fs-5 footer-cta-text">Rozwiąż test, sprawdź wynik i buduj portfolio umiejętności zawodowych.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?php echo $base_url; ?>exam/join.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold shadow-sm footer-cta-link">
                        <i class="bi bi-qr-code-scan me-2"></i><span>Dołącz teraz</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4 footer-main-grid">
            <!-- Brand & Mission -->
            <div class="col-lg-4">
                <div class="footer-brand-card h-100">
                <div class="footer-brand mb-4">
                    <div class="h4 fw-900 text-primary mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-mortarboard-fill"></i>
                        ZSEM <span class="text-main">Tech</span>
                    </div>
                    <div class="small fw-bold text-muted">Zespół Szkół Elektryczno-Mechanicznych</div>
                </div>
                <p class="text-muted mb-4 pe-lg-5">
                    Platforma Zespołu Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia do nauki, sprawdzianów i przygotowania do kwalifikacji zawodowych.
                    Projekt działa edukacyjnie i non-profit w ramach społeczności szkolnej.
                </p>
                <div class="social-links d-flex gap-2 flex-wrap">
                    <a href="https://www.facebook.com/people/ZSEM-Tech/61556411931896/" target="_blank" rel="noopener noreferrer" class="footer-social-btn" aria-label="Facebook ZSEM Tech"><i class="bi bi-facebook"></i></a>
                    <a href="https://x.com/zsem_tech" target="_blank" rel="noopener noreferrer" class="footer-social-btn" aria-label="X ZSEM Tech"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://www.instagram.com/zsem.tech" target="_blank" rel="noopener noreferrer" class="footer-social-btn" aria-label="Instagram ZSEM Tech"><i class="bi bi-instagram"></i></a>
                    <a href="https://www.tiktok.com/@zsem.tech" target="_blank" rel="noopener noreferrer" class="footer-social-btn" aria-label="TikTok ZSEM Tech"><i class="bi bi-tiktok"></i></a>
                    <a href="https://zsemtech.zsem.edu.pl" target="_blank" rel="noopener noreferrer" class="footer-social-btn" aria-label="Strona ZSEM Tech"><i class="bi bi-globe2"></i></a>
                    <a href="https://zsem.edu.pl/" target="_blank" rel="noopener noreferrer" class="footer-social-btn" aria-label="Strona szkoły ZSEM"><i class="bi bi-building"></i></a>
                </div>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div class="col-6 col-md-3 col-lg-2 ms-lg-auto">
                <div class="footer-link-card h-100">
                <h6 class="footer-heading">Platforma</h6>
                <ul class="list-unstyled footer-nav">
                    <li><a href="<?php echo $base_url; ?>index.php"><i class="bi bi-chevron-right small"></i> Dashboard</a></li>
                    <li><a href="<?php echo $base_url; ?>ranking.php"><i class="bi bi-chevron-right small"></i> Ranking</a></li>
                    <li><a href="<?php echo $base_url; ?>categories.php"><i class="bi bi-chevron-right small"></i> Kwalifikacje</a></li>
                    <li><a href="<?php echo $base_url; ?>practice.php"><i class="bi bi-chevron-right small"></i> Praktyka</a></li>
                    <li><a href="<?php echo $base_url; ?>careers.php"><i class="bi bi-chevron-right small"></i> Kariery</a></li>
                </ul>
                </div>
            </div>

            <!-- Community & Account -->
            <div class="col-6 col-md-3 col-lg-2">
                <div class="footer-link-card h-100">
                <h6 class="footer-heading">Konto</h6>
                <ul class="list-unstyled footer-nav">
                    <li><a href="<?php echo $base_url; ?>profile.php"><i class="bi bi-chevron-right small"></i> Twój profil</a></li>
                    <li><a href="<?php echo $base_url; ?>social.php"><i class="bi bi-chevron-right small"></i> Znajomi</a></li>
                    <li><a href="<?php echo $base_url; ?>goals.php"><i class="bi bi-chevron-right small"></i> Misje</a></li>
                    <li><a href="<?php echo $base_url; ?>settings.php"><i class="bi bi-chevron-right small"></i> Ustawienia</a></li>
                </ul>
                </div>
            </div>

            <!-- Contact & Help -->
            <div class="col-md-6 col-lg-3">
                <div class="footer-support-card h-100">
                <h6 class="footer-heading">Wsparcie</h6>
                <div class="footer-contact-item mb-3">
                    <div class="icon-box"><i class="bi bi-envelope"></i></div>
                    <div class="content">
                        <span class="label">Email</span>
                        <a href="mailto:zsemtech@zsem.edu.pl" class="value">zsemtech@zsem.edu.pl</a>
                    </div>
                </div>
                <div class="footer-contact-item mb-4">
                    <div class="icon-box"><i class="bi bi-geo-alt"></i></div>
                    <div class="content">
                        <span class="label">Lokalizacja</span>
                        <span class="value">Nowy Sącz, ul. Limanowskiego 4</span>
                    </div>
                </div>
                <a href="<?php echo $base_url; ?>contact.php" class="btn btn-primary rounded-pill w-100 fw-bold">
                    Centrum Pomocy
                </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom footer-bottom-card mt-4">
            <div class="row align-items-center">
                <div class="col-md-7 text-center text-md-start">
                    <p class="mb-0 text-muted small">
                        &copy; 2026 <strong>Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</strong>.
                        <br>Projekt platformy: <a href="https://www.linkedin.com/in/damian-podg%C3%B3rski-5b615b3b7/" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none fw-bold">Damian Podgórski</a> & <a href="https://www.linkedin.com/in/micha%C5%82-michalik-927a95311/" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none fw-bold">Michał Michalik</a>
                    </p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <ul class="list-inline mb-0 footer-legal-list small">
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>privacy.php" class="text-muted text-decoration-none hover-primary">Prywatność</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>polityka-cookies.php" class="text-muted text-decoration-none hover-primary">Cookies</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>careers.php" class="text-muted text-decoration-none hover-primary">Kariery</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>terms.php" class="text-muted text-decoration-none hover-primary">Regulamin</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>zglos-naruszenie.php" class="text-muted text-decoration-none hover-primary">Zgłoś naruszenie</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>cooperation.php" class="text-muted text-decoration-none hover-primary">Współpraca</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>dostepnosc.php" class="text-muted text-decoration-none hover-primary">Dostępność</a></li>
                        <li class="list-inline-item"><a href="<?php echo $base_url; ?>contact.php" class="text-muted text-decoration-none hover-primary">Kontakt</a></li>
                        <li class="list-inline-item"><button type="button" class="btn btn-link p-0 text-muted text-decoration-none hover-primary small align-baseline" data-cookie-settings>Ustawienia cookies</button></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php if (!empty($_SESSION['user_id'])): ?>
<div id="sessionKeepaliveModal" class="session-keepalive-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="sessionKeepaliveTitle">
    <div class="session-keepalive-backdrop"></div>
    <div class="session-keepalive-panel">
        <h2 id="sessionKeepaliveTitle">Twoja sesja wygasa za chwilę</h2>
        <p id="sessionKeepaliveMessage">Twoja sesja wygaśnie za mniej niż 10 minut. Czy nadal jesteś aktywny?</p>
        <div class="session-keepalive-actions">
            <button id="sessionKeepaliveConfirm" type="button" class="btn btn-primary btn-lg">Tak, przedłuż</button>
            <button id="sessionKeepaliveClose" type="button" class="btn btn-outline-secondary btn-lg">Nie, dzięki</button>
        </div>
        <div id="sessionKeepaliveStatus" class="text-muted small mt-3"></div>
    </div>
</div>
<?php endif; ?>

<style>
.session-keepalive-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1200;
}
.session-keepalive-modal.visible {
    display: flex;
}
.session-keepalive-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(2px);
}
.session-keepalive-panel {
    position: relative;
    max-width: 520px;
    width: min(100%, 520px);
    background: #fff;
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.24);
    z-index: 1;
}
.session-keepalive-panel h2 {
    margin-bottom: 0.75rem;
    font-size: 1.35rem;
}
.session-keepalive-panel p {
    margin-bottom: 1.5rem;
    color: #475569;
}
.session-keepalive-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
</style>

<style>
.footer-links a {
    transition: all 0.2s ease;
    display: inline-block;
}
.footer-links a:hover {
    color: var(--primary-color, var(--primary-color)) !important;
    transform: translateX(5px);
}
.footer-social-icon {
    width: 36px;
    height: 36px;
    background-color: var(--bg-color, #f1f5f9);
    color: var(--text-muted, #64748b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
}
.footer-social-icon:hover {
    background-color: var(--primary-color, var(--primary-color));
    color: white;
    transform: translateY(-3px);
}
.main-footer {
    background: linear-gradient(180deg, rgba(248,250,252,0.96), rgba(241,245,249,0.96));
    border-color: rgba(148, 163, 184, 0.25) !important;
}
body.dark-mode .main-footer {
    background: linear-gradient(180deg, rgba(15,23,42,0.98), rgba(30,41,59,0.98));
}
.footer-brand .h4 {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
}
.main-footer .btn {
    white-space: normal;
}
.footer-brand-card,
.footer-link-card,
.footer-support-card,
.footer-bottom-card {
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(148, 163, 184, 0.16);
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.04);
}
.footer-brand-card,
.footer-link-card,
.footer-support-card {
    padding: 1.35rem;
}
.footer-bottom-card {
    padding: 1.25rem;
}
.footer-legal-list {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: .65rem 1rem;
}
.footer-legal-list .list-inline-item {
    margin: 0 !important;
}
body.dark-mode .footer-brand-card,
body.dark-mode .footer-link-card,
body.dark-mode .footer-support-card,
body.dark-mode .footer-bottom-card {
    background: rgba(15, 23, 42, 0.72);
    border-color: rgba(148, 163, 184, 0.18);
}
.footer-bottom-links {
    padding-right: 60px;
}
@media (max-width: 767.98px) {
    .main-footer {
        padding-top: 2rem !important;
        padding-bottom: 2rem !important;
    }
    .footer-main-col {
        text-align: left;
    }
    .footer-social-icon {
        width: 42px;
        height: 42px;
    }
    .footer-bottom-links {
        padding-right: 0;
        margin-top: 1rem;
        text-align: left;
    }
    .footer-legal-list {
        justify-content: center;
    }
    .footer-bottom-links .list-inline-item {
        margin: 0 .75rem .5rem 0 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cta = document.getElementById('footerRotatingCta');
    if (!cta) return;
    const base = <?php echo json_encode($base_url); ?>;
    const items = [
        { badge: 'Sprawdzian', title: 'Gotowy na kod od nauczyciela?', text: 'Dołącz do lobby, sprawdź status i rozpocznij bez ręcznego odświeżania.', href: base + 'exam/join.php', icon: 'bi-qr-code-scan', label: 'Dołącz teraz' },
        { badge: 'Nauka', title: 'Zrób krótki trening pytań', text: 'Wybierz kwalifikację, przećwicz materiał i obserwuj postęp.', href: base + 'categories.php', icon: 'bi-folder2-open', label: 'Kwalifikacje' },
        { badge: 'Zespół', title: 'Pomóż rozwijać ZSEM Tech', text: 'Szukamy osób od kodu, designu, testów i aktualizacji treści.', href: base + 'careers.php', icon: 'bi-code-slash', label: 'Kariery' }
    ];
    let index = Math.floor(Math.random() * items.length);
    const setCta = () => {
        const item = items[index % items.length];
        cta.querySelector('.footer-cta-badge').textContent = item.badge;
        cta.querySelector('.footer-cta-title').textContent = item.title;
        cta.querySelector('.footer-cta-text').textContent = item.text;
        const link = cta.querySelector('.footer-cta-link');
        link.href = item.href;
        link.querySelector('i').className = 'bi ' + item.icon + ' me-2';
        link.querySelector('span').textContent = item.label;
        index++;
    };
    setCta();
    const reduceMotion = document.body?.classList.contains('reduce-motion')
        || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion || items.length < 2) return;

    let ctaTimer = null;
    const stopCtaTimer = () => {
        if (!ctaTimer) return;
        clearInterval(ctaTimer);
        ctaTimer = null;
    };
    const startCtaTimer = () => {
        if (ctaTimer || document.hidden) return;
        ctaTimer = setInterval(setCta, 7000);
    };
    startCtaTimer();
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCtaTimer();
        } else {
            startCtaTimer();
        }
    });
});
</script>

<?php if (empty($appApiClientLoaded)): $appApiClientLoaded = true; ?>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/api-client.js', rtrim($base_url, '/'))); ?>"></script>
<?php endif; ?>

<?php if (!empty($_SESSION['user_id'])): ?>
<script>
(function() {
    const base = <?php echo json_encode($base_url); ?>;
    const keepaliveToken = <?php echo json_encode(generateCsrfToken('session_keepalive')); ?>;
    const warningThreshold = 600; // 10 minutes
    const pollIntervalMs = 60000; // 1 minute

    function getElement(id) {
        return document.getElementById(id);
    }

    const modal = getElement('sessionKeepaliveModal');
    const message = getElement('sessionKeepaliveMessage');
    const statusLabel = getElement('sessionKeepaliveStatus');
    const confirmButton = getElement('sessionKeepaliveConfirm');
    const closeButton = getElement('sessionKeepaliveClose');

    if (!modal || !message || !confirmButton || !closeButton) {
        return;
    }

    let modalVisible = false;
    let lastRemaining = null;

    function setModalVisible(visible) {
        modalVisible = visible;
        modal.classList.toggle('visible', visible);
        modal.setAttribute('aria-hidden', visible ? 'false' : 'true');
        if (!visible) {
            statusLabel.textContent = '';
        }
    }

    function showModal(remainingSeconds) {
        if (modalVisible) {
            return;
        }
        lastRemaining = remainingSeconds;
        const minutes = Math.max(1, Math.ceil(remainingSeconds / 60));
        message.textContent = `Twoja sesja wygasa za około ${minutes} minut. Jesteś nadal aktywny? Kliknij „Tak, przedłuż”.`;
        statusLabel.textContent = '';
        setModalVisible(true);
    }

    function hideModal() {
        setModalVisible(false);
    }

    async function fetchSessionStatus() {
        try {
            const data = window.AppApi?.getJson
                ? await window.AppApi.getJson(base + 'ajax/session_status.php')
                : await fetch(base + 'ajax/session_status.php', {
                    method: 'GET',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                }).then(response => response.json());
            if (!data.success) {
                return;
            }
            const remaining = Number(data.remaining_seconds || 0);
            if (remaining > 0 && remaining <= warningThreshold) {
                showModal(remaining);
            }
            if (remaining <= 0) {
                statusLabel.textContent = 'Twoja sesja wygasła. Odśwież stronę, aby się ponownie zalogować.';
            }
        } catch (error) {
            console.error('Session status error:', error);
        }
    }

    async function extendSession() {
        confirmButton.disabled = true;
        statusLabel.textContent = 'Przedłużam sesję...';
        try {
            const formData = window.AppApi?.urlEncoded
                ? window.AppApi.urlEncoded({ csrf_token: keepaliveToken })
                : new URLSearchParams({ csrf_token: keepaliveToken });
            const data = window.AppApi?.postForm
                ? await window.AppApi.postForm(base + 'ajax/extend_session.php', formData)
                : await fetch(base + 'ajax/extend_session.php', {
                    method: 'POST',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': keepaliveToken,
                        'Accept': 'application/json'
                    },
                    body: formData.toString()
                }).then(response => response.json());
            if (data.success) {
                statusLabel.textContent = 'Sesja została przedłużona o kolejne 3 godziny.';
                setTimeout(hideModal, 1200);
                return;
            }
            statusLabel.textContent = data.error ? `Błąd: ${data.error}` : 'Nie udało się przedłużyć sesji.';
        } catch (error) {
            statusLabel.textContent = 'Błąd połączenia. Spróbuj ponownie.';
            console.error('Session extend error:', error);
        } finally {
            confirmButton.disabled = false;
        }
    }

    closeButton.addEventListener('click', hideModal);
    confirmButton.addEventListener('click', extendSession);
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modalVisible) {
            hideModal();
        }
    });

    fetchSessionStatus();
    setInterval(fetchSessionStatus, pollIntervalMs);
})();
</script>
<?php endif; ?>

<script src="<?php echo htmlspecialchars(assetUrl('assets/js/app-dialogs.js', rtrim($base_url, '/'))); ?>" defer></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/performance-metrics.js', rtrim($base_url, '/'))); ?>" defer></script>
<?php include __DIR__ . '/cookie_consent.php'; ?>
<?php include __DIR__ . '/help_center.php'; ?>
