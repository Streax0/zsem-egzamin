<?php
declare(strict_types=1);

if (!isset($base_url)) {
    $base_url = file_exists('config/db.php') ? '' : '../';
}
?>
<div class="offcanvas offcanvas-end help-center-shell" tabindex="-1" id="helpCenterOffcanvas" aria-labelledby="helpCenterLabel">
    
    <!-- Ultra-Modern Glass Header -->
    <div class="offcanvas-header help-center-header">
        <div class="help-header-content">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="help-center-kicker">Wsparcie i Baza Wiedzy</span>
                <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-30 rounded-pill px-2 py-0.5 small d-inline-flex align-items-center gap-1">
                    <span class="help-status-dot"></span> Online 24/7
                </span>
            </div>
            <h2 class="offcanvas-title h5 fw-bold text-white mb-0 d-flex align-items-center gap-2" id="helpCenterLabel">
                <i class="bi bi-question-diamond-fill text-warning"></i> Centrum Pomocy ZSEM Tech
            </h2>
        </div>
        <button type="button" class="btn-close btn-close-white help-close-btn" data-bs-dismiss="offcanvas" aria-label="Zamknij"></button>
    </div>

    <div class="offcanvas-body p-0 help-body-scroll">
        
        <!-- Search and Quick Filter Box -->
        <div class="help-search-panel">
            <div class="input-group help-search-group shadow-sm">
                <span class="input-group-text bg-body border-end-0 text-muted ps-3">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" id="helpSearchInput" class="form-control bg-body border-start-0 ps-1" placeholder="Szukaj: terminal, subnetting, sprawdzian, passkey, XP..." aria-label="Szukaj w centrum pomocy">
                <button class="btn btn-outline-secondary border-start-0 pe-3 d-none" type="button" id="helpSearchClear" title="Wyczyść wyszukiwanie">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>

            <!-- Quick Keyword Tags -->
            <div class="help-tag-cloud mt-2 d-flex gap-1 flex-wrap">
                <button type="button" class="help-tag-chip" data-query="terminal">#cli-lab</button>
                <button type="button" class="help-tag-chip" data-query="podsieci">#subnetting</button>
                <button type="button" class="help-tag-chip" data-query="sprawdzian">#sprawdzian</button>
                <button type="button" class="help-tag-chip" data-query="passkey">#passkey</button>
                <button type="button" class="help-tag-chip" data-query="xp">#punkty-xp</button>
            </div>

            <!-- Quick Navigation Tiles (3 Themed Action Cards) -->
            <div class="help-quick-grid mt-3">
                <a href="#collapseExams" data-bs-toggle="collapse" class="help-quick-card">
                    <div class="quick-card-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <span class="quick-card-title">Instrukcja obsługi egzaminu</span>
                    <span class="quick-card-sub">Zasady & Przebieg</span>
                </a>
                <a href="<?php echo htmlspecialchars($base_url); ?>user/settings.php" class="help-quick-card">
                    <div class="quick-card-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <span class="quick-card-title">Zarządzanie kontem i profilem</span>
                    <span class="quick-card-sub">Konto & Ustawienia</span>
                </a>
                <a href="<?php echo htmlspecialchars($base_url); ?>pages/contact.php" class="help-quick-card">
                    <div class="quick-card-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-headset"></i>
                    </div>
                    <span class="quick-card-title">Zgłaszanie problemów technicznych</span>
                    <span class="quick-card-sub">Wsparcie & Pomoc</span>
                </a>
            </div>
        </div>

        <!-- Category Tabs / Filter Buttons -->
        <div class="help-category-bar px-3 pt-3 pb-2 d-flex gap-1 overflow-x-auto border-bottom">
            <button type="button" class="help-cat-btn active" data-cat="all">Wszystko</button>
            <button type="button" class="help-cat-btn" data-cat="cke">Egzaminy & Nauka</button>
            <button type="button" class="help-cat-btn" data-cat="sandbox">CLI & Sandbox</button>
            <button type="button" class="help-cat-btn" data-cat="security">Bezpieczeństwo & 2FA</button>
            <button type="button" class="help-cat-btn" data-cat="faq">FAQ</button>
        </div>

        <!-- Rich Knowledge Accordion -->
        <div class="accordion accordion-flush help-accordion p-2" id="helpAccordion">
            
            <!-- Item 1: Symulator CLI Lab -->
            <div class="accordion-item help-section" data-cat="sandbox" data-keywords="terminal cli bash linux cmd powershell nano diskpart mysql apache systemctl cke inf02 inf03 inf08">
                <h3 class="accordion-header" id="headingCliLab">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCliLab" aria-expanded="false" aria-controls="collapseCliLab">
                        <div class="d-flex align-items-center gap-2">
                            <span class="help-icon-wrap bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-terminal-fill"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-body">Symulator Terminala CLI & Zadania CKE</div>
                                <div class="small text-muted" style="font-size:.72rem;">Pełne środowisko Linux (Bash) & Windows (CMD/PowerShell)</div>
                            </div>
                        </div>
                    </button>
                </h3>
                <div id="collapseCliLab" class="accordion-collapse collapse" aria-labelledby="headingCliLab" data-bs-parent="#helpAccordion">
                    <div class="accordion-body help-content">
                        <div class="help-guide-block mb-2">
                            <strong class="d-block text-primary mb-1"><i class="bi bi-check2-circle me-1"></i>Jak działa CLI Lab?</strong>
                            <p class="small text-body-secondary mb-2">
                                CLI Lab to bezpieczny symulator konsoli obsługujący ponad <strong>200 komend</strong>, wirtualny system plików (VFS), edytor Nano, podpowłoki <code>diskpart</code>, <code>powershell</code> i <code>mysql</code>.
                            </p>
                        </div>
                        <ul class="small text-body-secondary ps-3 mb-2">
                            <li><strong>35+ Zadań CKE:</strong> Wybierz scenariusz egzaminacyjny z prawej listy, wykonuj kolejne kroki i zdobywaj punkty XP.</li>
                            <li><strong>Instrukcja Krok po Kroku:</strong> Zawiera sugerowaną składnię oraz wyjaśnienie teorii egzaminacyjnej. Możesz wklejać komendy jednym kliknięciem lub zwijać kartę do mini-paska.</li>
                            <li><strong>Skróty Klawiszowe:</strong> <kbd>Tab</kbd> (autouzupełnianie), <kbd>Ctrl+L</kbd> (czyszczenie), <kbd>↑</kbd>/<kbd>↓</kbd> (historia poleceń), <kbd>Ctrl+C</kbd> (przerwanie).</li>
                        </ul>
                        <a href="<?php echo htmlspecialchars($base_url); ?>sandbox/cli_lab.php" class="btn btn-outline-primary btn-sm rounded-pill mt-1">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Otwórz CLI Lab
                        </a>
                    </div>
                </div>
            </div>

            <!-- Item 2: Trening Podsieci Subnetting Challenge -->
            <div class="accordion-item help-section" data-cat="sandbox" data-keywords="subnetting podsieci ip maska broadcast hosty cidr kalkulator sieci inf02 cke">
                <h3 class="accordion-header" id="headingSubnetting">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSubnetting" aria-expanded="false" aria-controls="collapseSubnetting">
                        <div class="d-flex align-items-center gap-2">
                            <span class="help-icon-wrap bg-info bg-opacity-10 text-info">
                                <i class="bi bi-diagram-3-fill"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-body">Subnetting Challenge & Kalkulator IP</div>
                                <div class="small text-muted" style="font-size:.72rem;">Nauka i automatyczne obliczanie adresacji IPv4</div>
                            </div>
                        </div>
                    </button>
                </h3>
                <div id="collapseSubnetting" class="accordion-collapse collapse" aria-labelledby="headingSubnetting" data-bs-parent="#helpAccordion">
                    <div class="accordion-body help-content">
                        <p class="small text-body-secondary mb-2">
                            Moduł pozwala na błyskawiczne ćwiczenie obliczania adresu sieci, adresu rozgłoszeniowego (broadcast), pierwszego i ostatniego użytecznego hosta oraz maski dziesiętnej dla notacji CIDR (od <code>/8</code> do <code>/30</code>).
                        </p>
                        <div class="p-2 rounded bg-body-tertiary small mb-2 font-monospace">
                            Wzór: Liczba hostów = 2^(32 - prefiks) - 2
                        </div>
                        <a href="<?php echo htmlspecialchars($base_url); ?>sandbox/subnetting_challenge.php" class="btn btn-outline-info btn-sm rounded-pill">
                            <i class="bi bi-play-fill me-1"></i>Rozpocznij Wyzwanie Podsieci
                        </a>
                    </div>
                </div>
            </div>

            <!-- Item 3: Egzaminy CKE & Punkty XP -->
            <div class="accordion-item help-section" data-cat="cke" data-keywords="test egzamin cke inf02 inf03 inf08 xp punkty ranga ranking mistrzostwo sprawdzian">
                <h3 class="accordion-header" id="headingExams">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExams" aria-expanded="false" aria-controls="collapseExams">
                        <div class="d-flex align-items-center gap-2">
                            <span class="help-icon-wrap bg-success bg-opacity-10 text-success">
                                <i class="bi bi-mortarboard-fill"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-body">Egzaminy CKE, Misje i Rangi XP</div>
                                <div class="small text-muted" style="font-size:.72rem;">Kwalifikacje INF.02, INF.03, INF.08 i system awansów</div>
                            </div>
                        </div>
                    </button>
                </h3>
                <div id="collapseExams" class="accordion-collapse collapse" aria-labelledby="headingExams" data-bs-parent="#helpAccordion">
                    <div class="accordion-body help-content small text-body-secondary">
                        <p class="mb-2">
                            <strong>Baza pytań CKE:</strong> Zawiera oficjalne pytania z arkuszy teoretycznych i praktycznych. Możesz rozwiązywać testy w trybie standardowym, symulacji egzaminu (40 pytań, 60 minut) lub trenować wybrane działy.
                        </p>
                        <p class="mb-2">
                            <strong>Punkty XP i Rangi:</strong> Za każdy poprawnie rozwiązany test, misję dzienną oraz ukończone zadanie CLI Lab otrzymujesz punkty XP, które podnoszą Twoją rangę (od <em>Początkującego</em> przez <em>SysAdmina</em> po <em>Mistrza Architektury</em>).
                        </p>
                        <p class="mb-0">
                            <strong>Przebieg egzaminu:</strong> Przed rozpoczęciem upewnij się, że masz stabilne połączenie z siecią oraz wystarczającą ilość czasu na dokończenie arkusza.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Item 4: Bezpieczeństwo konta, Passkeys & 2FA -->
            <div class="accordion-item help-section" data-cat="security" data-keywords="bezpieczenstwo passkey webauthn 2fa mfa fido2 yubikey haslo sesja szyfrowanie">
                <h3 class="accordion-header" id="headingSecurity">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecurity" aria-expanded="false" aria-controls="collapseSecurity">
                        <div class="d-flex align-items-center gap-2">
                            <span class="help-icon-wrap bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-shield-lock-fill"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-body">Passkey (WebAuthn) & Uwierzytelnianie 2FA</div>
                                <div class="small text-muted" style="font-size:.72rem;">Biometria, klucze FIDO2 i kody aplikacji</div>
                            </div>
                        </div>
                    </button>
                </h3>
                <div id="collapseSecurity" class="accordion-collapse collapse" aria-labelledby="headingSecurity" data-bs-parent="#helpAccordion">
                    <div class="accordion-body help-content small text-body-secondary">
                        <p class="mb-2">
                            <strong>Klucze Passkey:</strong> Możesz powiązać swoje konto z czytnikiem linii papilarnych, Windows Hello lub sprzętowym kluczem YubiKey. Logowanie Passkey bezpośrednio spełnia wymogi bezpieczeństwa (bez konieczności wpisywania dodatkowych kodów).
                        </p>
                        <p class="mb-2">
                            <strong>2FA TOTP:</strong> Aktywuj kody jednorazowe w <a href="<?php echo htmlspecialchars($base_url); ?>user/settings.php">Ustawieniach</a> przy użyciu aplikacji Google Authenticator, Microsoft Authenticator lub 2FAS.
                        </p>
                        <p class="mb-0">
                            <strong>Wskazówka:</strong> Regularnie sprawdzaj listę aktywnych urządzeń w panelu ustawień profilu.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Item 6: FAQ i Częste Pytania -->
            <div class="accordion-item help-section" data-cat="faq" data-keywords="faq problem blad kod sprawdzianu haslo reset pomoc kontakt">
                <h3 class="accordion-header" id="headingFaqNew">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaqNew" aria-expanded="false" aria-controls="collapseFaqNew">
                        <div class="d-flex align-items-center gap-2">
                            <span class="help-icon-wrap bg-secondary bg-opacity-10 text-secondary">
                                <i class="bi bi-chat-square-dots-fill"></i>
                            </span>
                            <div>
                                <div class="fw-bold text-body">Najczęstsze Pytania (FAQ)</div>
                                <div class="small text-muted" style="font-size:.72rem;">Rozwiązania typowych problemów</div>
                            </div>
                        </div>
                    </button>
                </h3>
                <div id="collapseFaqNew" class="accordion-collapse collapse" aria-labelledby="headingFaqNew" data-bs-parent="#helpAccordion">
                    <div class="accordion-body help-content small text-body-secondary">
                        <div class="mb-3">
                            <strong class="d-block text-body">Nie działa kod sprawdzianu?</strong>
                            Upewnij się, że wpisujesz dokładnie 6 znaków (bez spacji) i że nauczyciel rozpoczął sesję sprawdzianu.
                        </div>
                        <div class="mb-3">
                            <strong class="d-block text-body">Jak zresetować hasło lub odzyskać dostęp?</strong>
                            Skontaktuj się z administratorem platformy lub nauczycielem prowadzącym, który może wygenerować bezpieczny link resetujący.
                        </div>
                        <div class="mb-0">
                            <strong class="d-block text-body">Gdzie zgłosić zauważony błąd w pytaniu?</strong>
                            Skorzystaj z przycisku „Zgłoś błąd” bezpośrednio pod pytaniem podczas rozwiązywania testu.
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- No Results Fallback View -->
        <div id="noResultsHelp" class="text-center p-4 d-none">
            <div class="p-3 d-inline-block rounded-circle bg-secondary bg-opacity-10 text-muted mb-2">
                <i class="bi bi-search fs-3"></i>
            </div>
            <h4 class="h6 fw-bold text-body">Brak wyników wyszukiwania</h4>
            <p class="small text-muted mb-3">Nie znaleziono pasujących tematów dla wpisanej frazy.</p>
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="document.getElementById('helpSearchClear')?.click();">
                Wyczyść filtr
            </button>
        </div>

        <!-- Contact Support Footer Card -->
        <div class="help-contact-card m-3 p-3 rounded-4 border">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary flex-shrink-0">
                    <i class="bi bi-headset fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="d-block small text-body">Potrzebujesz pomocy?</strong>
                    <span class="small text-muted d-block" style="font-size:.75rem;">Napisz do zespołu administratorów ZSEM Tech.</span>
                </div>
                <a href="<?php echo htmlspecialchars($base_url); ?>pages/contact.php" class="btn btn-primary btn-sm rounded-pill px-3 flex-shrink-0 fw-bold">
                    Kontakt
                </a>
            </div>
        </div>

    </div>
</div>

<!-- Floating Help Action Button (FAB) -->
<button class="help-fab help-center-fab" id="help-center-fab" type="button" data-bs-toggle="offcanvas" data-bs-target="#helpCenterOffcanvas" data-help-center-trigger aria-controls="helpCenterOffcanvas" aria-expanded="false" aria-label="Otwórz centrum pomocy" title="Centrum Pomocy & Baza Wiedzy">
    <i class="bi bi-question-lg"></i>
</button>

<style>
/* ── Help Center Scoped Styles ── */
.help-center-shell {
    width: 440px !important;
    max-width: 92vw !important;
    border-left: 1px solid var(--bs-border-color, rgba(0,0,0,0.1)) !important;
    background: var(--bs-body-bg, #ffffff) !important;
    box-shadow: -15px 0 45px rgba(0, 0, 0, 0.18) !important;
}

.help-center-header {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4338ca 100%) !important;
    padding: 1.35rem 1.25rem !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.help-center-kicker {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.75);
}

.help-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
    box-shadow: 0 0 6px #10b981;
}

.help-search-panel {
    background: rgba(148, 163, 184, 0.06);
    padding: 1.15rem;
    border-bottom: 1px solid var(--bs-border-color, rgba(0,0,0,0.08));
}

.help-search-group {
    border-radius: 12px;
    overflow: hidden;
}

.help-search-group .form-control:focus {
    box-shadow: none;
}

.help-tag-chip {
    background: rgba(148, 163, 184, 0.12);
    border: 1px solid rgba(148, 163, 184, 0.2);
    color: var(--bs-body-color, #475569);
    border-radius: 999px;
    padding: 0.15rem 0.6rem;
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.help-tag-chip:hover {
    background: #4f46e5;
    color: #fff;
    border-color: #4f46e5;
}

.help-quick-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.55rem;
}

.help-quick-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0.65rem 0.4rem;
    background: var(--bs-body-bg, #fff);
    border: 1px solid var(--bs-border-color, rgba(0,0,0,0.08));
    border-radius: 12px;
    text-decoration: none;
    color: var(--bs-body-color, #1e293b);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.help-quick-card:hover {
    transform: translateY(-2px);
    border-color: #6366f1;
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.15);
    color: #4f46e5;
}

.quick-card-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 1.1rem;
    margin-bottom: 0.3rem;
}

.quick-card-title {
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.2;
}

.quick-card-sub {
    font-size: 0.64rem;
    color: #64748b;
    font-weight: 600;
}

.help-category-bar {
    background: var(--bs-body-bg, #fff);
    white-space: nowrap;
}

.help-cat-btn {
    background: transparent;
    border: 1px solid transparent;
    color: #64748b;
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}

.help-cat-btn:hover {
    color: #4f46e5;
    background: rgba(99, 102, 241, 0.08);
}

.help-cat-btn.active {
    background: #4f46e5;
    color: #fff;
}

.help-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.help-accordion .accordion-item {
    background: transparent;
    border: 1px solid var(--bs-border-color, rgba(0,0,0,0.06));
    border-radius: 12px !important;
    margin-bottom: 0.45rem;
    overflow: hidden;
}

.help-accordion .accordion-button {
    padding: 0.85rem 1rem;
    background: transparent;
    font-size: 0.88rem;
    box-shadow: none !important;
}

.help-accordion .accordion-button:not(.collapsed) {
    background: rgba(99, 102, 241, 0.06);
    color: #4f46e5;
}

.help-contact-card {
    background: rgba(148, 163, 184, 0.06);
    border-color: var(--bs-border-color, rgba(0,0,0,0.08)) !important;
}

/* ── Floating Action Button (FAB) ── */
.help-fab {
    position: fixed !important;
    right: 2rem !important;
    bottom: 2rem !important;
    width: 54px !important;
    height: 54px !important;
    border-radius: 50% !important;
    background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
    color: #ffffff !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.45) !important;
    display: grid !important;
    place-items: center !important;
    font-size: 1.4rem !important;
    z-index: 1060 !important;
    cursor: pointer !important;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, opacity 0.25s ease !important;
}

.help-fab:hover {
    transform: translateY(-3px) scale(1.05) !important;
    box-shadow: 0 14px 32px rgba(79, 70, 229, 0.6) !important;
}

#helpCenterOffcanvas.show ~ .help-fab,
#helpCenterOffcanvas.showing ~ .help-fab {
    opacity: 0 !important;
    pointer-events: none !important;
    transform: scale(0) !important;
}

/* Dark Mode Tweaks */
body.dark-mode .help-center-shell {
    background: #0f172a !important;
}

body.dark-mode .help-search-panel,
body.dark-mode .help-contact-card {
    background: #1e293b !important;
}

body.dark-mode .help-quick-card {
    background: #1e293b !important;
    color: #f1f5f9 !important;
}

body.dark-mode .quick-card-sub {
    color: #94a3b8 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const panel = document.getElementById('helpCenterOffcanvas');
    const fab = document.querySelector('[data-help-center-trigger]');
    const searchInput = document.getElementById('helpSearchInput');
    const searchClear = document.getElementById('helpSearchClear');
    const tagChips = document.querySelectorAll('.help-tag-chip');
    const catButtons = document.querySelectorAll('.help-cat-btn');
    const helpSections = document.querySelectorAll('.help-section');
    const noResults = document.getElementById('noResultsHelp');

    if (panel && panel.parentElement !== document.body) document.body.appendChild(panel);
    if (fab && fab.parentElement !== document.body) document.body.appendChild(fab);
    panel?.classList.remove('d-none');
    fab?.classList.remove('d-none');
    fab?.removeAttribute('aria-hidden');

    // Avoid footer collision: lift FAB if overlapping with page footer
    const pageFooter = document.querySelector('footer, .main-footer, .landing-footer, .footer');
    if (pageFooter && fab) {
        const adjustFabPos = () => {
            const footerRect = pageFooter.getBoundingClientRect();
            const winHeight = window.innerHeight;
            if (footerRect.top < winHeight) {
                const overlap = winHeight - footerRect.top;
                fab.style.bottom = `${Math.min(overlap + 24, 220)}px`;
            } else {
                fab.style.bottom = '2rem';
            }
        };
        window.addEventListener('scroll', adjustFabPos, { passive: true });
        window.addEventListener('resize', adjustFabPos, { passive: true });
        adjustFabPos();
    }

    let fallbackBackdrop = null;
    const setFallbackOpen = (open) => {
        if (!panel || !fab) return;
        panel.classList.toggle('show', open);
        panel.style.visibility = open ? 'visible' : '';
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';

        fab.style.opacity = open ? '0' : '1';
        fab.style.pointerEvents = open ? 'none' : 'auto';
        fab.style.transform = open ? 'scale(0)' : 'scale(1)';

        if (open && !fallbackBackdrop) {
            fallbackBackdrop = document.createElement('div');
            fallbackBackdrop.className = 'offcanvas-backdrop fade show';
            fallbackBackdrop.addEventListener('click', () => setFallbackOpen(false));
            document.body.appendChild(fallbackBackdrop);
        } else if (!open && fallbackBackdrop) {
            fallbackBackdrop.remove();
            fallbackBackdrop = null;
        }
    };

    fab?.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        if (window.bootstrap?.Offcanvas && panel) {
            window.bootstrap.Offcanvas.getOrCreateInstance(panel).show();
            fab.setAttribute('aria-expanded', 'true');
            return;
        }
        setFallbackOpen(true);
    });

    panel?.addEventListener('show.bs.offcanvas', function() {
        if (fab) {
            fab.style.opacity = '0';
            fab.style.pointerEvents = 'none';
            fab.style.transform = 'scale(0)';
        }
    });

    panel?.addEventListener('hidden.bs.offcanvas', function() {
        if (fab) {
            fab.setAttribute('aria-expanded', 'false');
            fab.style.opacity = '1';
            fab.style.pointerEvents = 'auto';
            fab.style.transform = 'scale(1)';
        }
    });

    panel?.querySelector('[data-bs-dismiss="offcanvas"]')?.addEventListener('click', function(event) {
        if (window.bootstrap?.Offcanvas) return;
        event.preventDefault();
        setFallbackOpen(false);
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && panel?.classList.contains('show') && !window.bootstrap?.Offcanvas) {
            setFallbackOpen(false);
        }
    });

    if (fab) {
        fab.style.opacity = '1';
        fab.style.pointerEvents = 'auto';
        fab.style.transform = 'scale(1)';
    }

    let activeCategory = 'all';

    const filterHelpContent = () => {
        const query = (searchInput?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        if (searchClear) {
            searchClear.classList.toggle('d-none', query.length === 0);
        }

        helpSections.forEach(section => {
            const secCat = section.dataset.cat || 'all';
            const keywords = (section.dataset.keywords || '').toLowerCase();
            const textContent = section.innerText.toLowerCase();

            const matchesCategory = (activeCategory === 'all' || secCat === activeCategory);
            const matchesQuery = query === '' || keywords.includes(query) || textContent.includes(query);

            if (matchesCategory && matchesQuery) {
                section.style.display = '';
                visibleCount++;
                if (query.length > 0) {
                    const collapseEl = section.querySelector('.accordion-collapse');
                    if (collapseEl && window.bootstrap) {
                        bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                    }
                }
            } else {
                section.style.display = 'none';
            }
        });

        if (noResults) {
            noResults.classList.toggle('d-none', visibleCount > 0);
        }
    };

    searchInput?.addEventListener('input', filterHelpContent);

    searchClear?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        filterHelpContent();
        searchInput?.focus();
    });

    tagChips.forEach(chip => {
        chip.addEventListener('click', () => {
            const q = chip.dataset.query || '';
            if (searchInput) searchInput.value = q;
            activeCategory = 'all';
            catButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.cat === 'all'));
            filterHelpContent();
        });
    });

    catButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            catButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCategory = btn.dataset.cat || 'all';
            filterHelpContent();
        });
    });
});
</script>
