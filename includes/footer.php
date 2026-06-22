<?php
if (!isset($base_url)) {
    $base_url = file_exists('config/db.php') ? '' : '../';
}
?>
<footer class="main-footer mt-auto py-5 border-top" role="contentinfo">
    <div class="container-fluid px-4">
        <!-- Modern rotating call-to-action -->
        <div class="footer-cta-card mb-4 animate-in" id="footerRotatingCta">
            <div class="footer-cta-glow-1"></div>
            <div class="footer-cta-glow-2"></div>
            <div class="row align-items-center g-4 p-4 p-md-5 position-relative" style="z-index: 1;">
                <div class="col-lg-8">
                    <span class="badge px-3 py-2 rounded-pill mb-3 fw-bold footer-cta-badge">Platforma Edukacyjna</span>
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

        <!-- Unified Footer Main Card -->
        <div class="footer-main-card p-4 p-md-5 animate-in">
            <div class="row g-4 footer-main-grid">
                <!-- Brand & Mission -->
                <div class="col-lg-4">
                    <div class="footer-brand-section">
                        <div class="footer-brand mb-3">
                            <div class="h4 fw-900 text-primary mb-1 d-flex align-items-center gap-2">
                                <i class="bi bi-mortarboard-fill"></i>
                                ZSEM <span class="text-main">Tech</span>
                            </div>
                            <div class="small fw-bold text-muted">Zespół Szkół Elektryczno-Mechanicznych</div>
                        </div>
                        <p class="text-muted mb-4 pe-lg-4">
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
                    <div class="footer-nav-section">
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
                    <div class="footer-nav-section">
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
                    <div class="footer-support-section">
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

            <hr class="footer-divider my-4">

            <!-- Copyright & Legal -->
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-5 text-center text-md-start">
                        <p class="mb-0 text-muted small">
                            &copy; 2026 <strong>Zespół Szkół Elektryczno-Mechanicznych im. gen. Józefa Kustronia</strong>.
                            <br>Projekt platformy: <a href="<?php echo $base_url; ?>author_damian.php" class="text-primary text-decoration-none fw-bold">Damian Podgórski</a> & <a href="<?php echo $base_url; ?>author_michal.php" class="text-primary text-decoration-none fw-bold">Michał Michalik</a>
                        </p>
                    </div>
                    <div class="col-md-7 mt-3 mt-md-0 footer-bottom-links">
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
.main-footer {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-top: 1px solid rgba(148, 163, 184, 0.25) !important;
    padding-bottom: 7rem !important; /* Spacing to prevent help-fab overlap on bottom links */
}
body.dark-mode .main-footer {
    background: linear-gradient(180deg, #0f172a 0%, #090d16 100%);
    border-color: rgba(255, 255, 255, 0.06) !important;
}
.footer-cta-card {
    position: relative;
    background: linear-gradient(135deg, #0d0b21 0%, #1e1b4b 35%, #3b0764 70%, #03001c 100%) !important;
    background-size: 200% 200% !important;
    animation: ctaMeshMovement 12s ease infinite;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.35);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes ctaMeshMovement {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.footer-cta-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 1.5px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.04) 50%, rgba(255, 255, 255, 0.12) 100%);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    z-index: 2;
    transition: all 0.4s ease;
}
.footer-cta-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 30px 60px -10px rgba(0, 0, 0, 0.45), 0 0 30px 2px rgba(99, 102, 241, 0.25);
}
.footer-cta-card:hover::before {
    background: linear-gradient(135deg, #6366f1 0%, #06b6d4 50%, #d946ef 100%);
}
.footer-cta-glow-1 {
    position: absolute;
    top: -20%;
    left: -10%;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.5) 0%, rgba(99, 102, 241, 0) 70%) !important;
    filter: blur(40px);
    pointer-events: none;
    z-index: 0;
    animation: ctaFloat1 12s ease-in-out infinite alternate;
}
.footer-cta-glow-2 {
    position: absolute;
    bottom: -30%;
    right: 5%;
    width: 380px;
    height: 380px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.4) 0%, rgba(6, 182, 212, 0) 70%) !important;
    filter: blur(50px);
    pointer-events: none;
    z-index: 0;
    animation: ctaFloat2 15s ease-in-out infinite alternate;
}
@keyframes ctaFloat1 {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(30px, 20px) scale(1.15); }
}
@keyframes ctaFloat2 {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(-25px, -30px) scale(1.1); }
}
.footer-cta-badge {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #e2e8f0 !important;
    font-size: 0.78rem;
    font-weight: 700 !important;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 0.45rem 1rem !important;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
}
.footer-cta-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    background-color: #38bdf8;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 8px #38bdf8;
    animation: ctaBadgePulse 2s infinite;
}
@keyframes ctaBadgePulse {
    0% {
        transform: scale(0.9);
        box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.6);
    }
    70% {
        transform: scale(1.1);
        box-shadow: 0 0 0 6px rgba(56, 189, 248, 0);
    }
    100% {
        transform: scale(0.9);
        box-shadow: 0 0 0 0 rgba(56, 189, 248, 0);
    }
}
.footer-cta-title {
    font-weight: 850;
    font-size: 2.2rem;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, #ffffff 30%, #cbd5e1 100%) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    background-clip: text !important;
}
.footer-cta-text {
    color: #cbd5e1 !important;
    font-weight: 500;
    font-size: 1.05rem;
    opacity: 0.95;
}
.footer-cta-link {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
    font-size: 1rem !important;
    padding: 0.8rem 2.2rem !important;
    border-radius: 999px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
}
.footer-cta-link:hover {
    transform: translateY(-3px) scale(1.03) !important;
    box-shadow: 0 15px 30px rgba(99, 102, 241, 0.35) !important;
    background: #ffffff !important;
    color: #4f46e5 !important;
    border-color: #a5b4fc !important;
}
@media (max-width: 991.98px) {
    .footer-cta-card .row {
        text-align: center;
    }
    .footer-cta-card .text-lg-end {
        text-align: center !important;
    }
    .footer-cta-badge {
        margin-left: auto;
        margin-right: auto;
    }
}
.footer-main-card {
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.65);
    border: 1px solid rgba(148, 163, 184, 0.12);
    box-shadow: 0 15px 35px -5px rgba(15, 23, 42, 0.04);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: all 0.3s ease;
}
.footer-main-card:hover {
    box-shadow: 0 20px 45px -5px rgba(15, 23, 42, 0.08);
    border-color: rgba(79, 70, 229, 0.15);
}
body.dark-mode .footer-main-card {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(255, 255, 255, 0.06);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
}
body.dark-mode .footer-main-card:hover {
    border-color: rgba(255, 255, 255, 0.1);
}
.footer-brand .h4 {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
}
.footer-brand .text-primary {
    color: #4f46e5 !important;
}
body.dark-mode .footer-brand .text-primary {
    color: #818cf8 !important;
}
.main-footer .btn {
    white-space: normal;
}
.footer-heading {
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-size: 0.85rem;
    margin-bottom: 1.5rem;
    color: var(--text-main);
}
.footer-nav li {
    margin-bottom: 0.75rem;
}
.footer-nav a {
    text-decoration: none;
    color: #64748b;
    font-size: 0.92rem;
    font-weight: 600;
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.footer-nav a i {
    font-size: 0.75rem;
    transition: transform 0.25s ease;
    color: #a5b4fc;
}
.footer-nav a:hover {
    color: #4f46e5 !important;
    transform: translateX(4px);
}
.footer-nav a:hover i {
    transform: translateX(2px);
    color: #4f46e5;
}
body.dark-mode .footer-nav a {
    color: #94a3b8;
}
body.dark-mode .footer-nav a:hover {
    color: #818cf8 !important;
}
.footer-social-btn {
    width: 42px;
    height: 42px;
    background: rgba(241, 245, 249, 0.8);
    color: #475569;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(148, 163, 184, 0.15);
    font-size: 1.1rem;
}
.footer-social-btn:hover {
    transform: translateY(-5px) scale(1.05);
    color: #fff !important;
}
.footer-social-btn[aria-label*="Facebook"]:hover {
    background: #1877f2 !important;
    border-color: #1877f2 !important;
    box-shadow: 0 8px 20px rgba(24, 119, 242, 0.4);
}
.footer-social-btn[aria-label*="X"]:hover {
    background: #000000 !important;
    border-color: #000000 !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
}
.footer-social-btn[aria-label*="Instagram"]:hover {
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%) !important;
    border-color: #dc2743 !important;
    box-shadow: 0 8px 20px rgba(220, 39, 67, 0.4);
}
.footer-social-btn[aria-label*="TikTok"]:hover {
    background: #010101 !important;
    border-color: #010101 !important;
    box-shadow: 0 8px 20px rgba(1, 1, 1, 0.4);
}
.footer-social-btn[aria-label*="Szkola"]:hover,
.footer-social-btn[aria-label*="szkoły"]:hover {
    background: #e11d48 !important;
    border-color: #e11d48 !important;
    box-shadow: 0 8px 20px rgba(225, 29, 72, 0.4);
}
.footer-social-btn[aria-label*="Strona ZSEM Tech"]:hover {
    background: #2563eb !important;
    border-color: #2563eb !important;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
}
body.dark-mode .footer-social-btn {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
    color: #94a3b8;
}
.footer-contact-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}
.footer-contact-item .icon-box {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(6, 182, 212, 0.08) 100%);
    color: #4f46e5;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    border: 1px solid rgba(79, 70, 229, 0.12);
    transition: all 0.3s ease;
}
.footer-contact-item:hover .icon-box {
    background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
    color: #fff;
    border-color: transparent;
    transform: scale(1.05) rotate(-5deg);
    box-shadow: 0 5px 15px rgba(79, 70, 229, 0.2);
}
body.dark-mode .footer-contact-item .icon-box {
    background: linear-gradient(135deg, rgba(129, 140, 248, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    color: #818cf8;
    border-color: rgba(129, 140, 248, 0.15);
}
body.dark-mode .footer-contact-item:hover .icon-box {
    background: linear-gradient(135deg, #818cf8 0%, #06b6d4 100%);
    color: #fff;
}
.footer-contact-item .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
}
body.dark-mode .footer-contact-item .label {
    color: #94a3b8;
}
.footer-contact-item .value {
    font-size: 0.92rem;
    color: #0f172a;
    text-decoration: none;
    font-weight: 600;
}
body.dark-mode .footer-contact-item .value {
    color: #e2e8f0;
}
.footer-support-section .btn-primary {
    background: linear-gradient(135deg, #4f46e5 0%, #8b5cf6 100%);
    border: none;
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
    transition: all 0.3s ease;
}
.footer-support-section .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(79, 70, 229, 0.35);
    background: linear-gradient(135deg, #4338ca 0%, #7c3aed 100%);
}
.footer-divider {
    border: 0;
    height: 1px;
    background: rgba(148, 163, 184, 0.15);
    margin: 2rem 0;
}
body.dark-mode .footer-divider {
    background: rgba(255, 255, 255, 0.06);
}
.footer-legal-list {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: .5rem 1.25rem;
}
.footer-legal-list .list-inline-item {
    margin: 0 !important;
}
.footer-legal-list a, 
.footer-legal-list button {
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.footer-legal-list a:hover,
.footer-legal-list button:hover {
    color: #4f46e5 !important;
    transform: translateY(-1px);
}
body.dark-mode .footer-legal-list a:hover,
body.dark-mode .footer-legal-list button:hover {
    color: #818cf8 !important;
}
.footer-bottom-links {
    padding-right: 60px;
}
.help-fab {
    background: linear-gradient(135deg, #4f46e5 0%, #8b5cf6 100%) !important;
    border: none !important;
    color: white !important;
    box-shadow: 0 8px 24px rgba(79, 70, 229, 0.4) !important;
    position: fixed !important;
    right: 2rem !important;
    bottom: 2rem !important;
    width: 58px !important;
    height: 58px !important;
    min-width: 58px !important;
    min-height: 58px !important;
    max-width: 58px !important;
    max-height: 58px !important;
    padding: 0 !important;
    line-height: 1 !important;
    display: inline-grid !important;
    place-items: center !important;
    border-radius: 999px !important;
    z-index: 1060 !important;
    transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.3s ease !important;
}
.help-fab:hover {
    transform: scale(1.1) rotate(5deg) !important;
    box-shadow: 0 12px 30px rgba(79, 70, 229, 0.55) !important;
}
body.dark-mode .help-fab {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
}
@media (max-width: 767.98px) {
    .main-footer {
        padding-top: 2rem !important;
        padding-bottom: 7rem !important;
    }
    .footer-main-col {
        text-align: left;
    }
    .footer-bottom-links {
        padding-right: 0;
        margin-top: 1rem;
        text-align: left;
    }
    .footer-legal-list {
        justify-content: center;
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
