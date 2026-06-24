<?php
if (!isset($base_url)) {
    $base_url = file_exists('config/db.php') ? '' : '../';
}
?>
<div class="offcanvas offcanvas-end help-center-shell" tabindex="-1" id="helpCenterOffcanvas" aria-labelledby="helpCenterLabel">
    <div class="offcanvas-header help-center-header">
        <div>
            <span class="help-center-kicker">Pomoc ZSEM Tech</span>
            <h5 class="offcanvas-title d-flex align-items-center mb-0" id="helpCenterLabel">
                <i class="bi bi-question-circle-fill me-2"></i> Centrum Pomocy
            </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Zamknij" tabindex="0"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="help-search-panel">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="helpSearchInput" class="form-control border-start-0 ps-0" placeholder="Szukaj: sprawdzian, misje, hasło..." aria-label="Szukaj w centrum pomocy" tabindex="0">
            </div>
            <div class="help-quick-grid mt-3">
                <a href="<?php echo $base_url; ?>test.php?mode=exam&setup=1" class="help-quick-card">
                    <i class="bi bi-play-circle"></i>
                    <span>Test</span>
                </a>
                <a href="<?php echo $base_url; ?>practice.php" class="help-quick-card">
                    <i class="bi bi-tools"></i>
                    <span>Praktyka</span>
                </a>
                <a href="<?php echo $base_url; ?>exam/join.php" class="help-quick-card">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>Sprawdzian</span>
                </a>
                <a href="<?php echo $base_url; ?>settings.php" class="help-quick-card">
                    <i class="bi bi-sliders"></i>
                    <span>Ustawienia</span>
                </a>
            </div>
        </div>

        <div class="accordion accordion-flush help-accordion" id="helpAccordion">
            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingStudent">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStudent" aria-expanded="false" aria-controls="collapseStudent" tabindex="0">
                        <i class="bi bi-mortarboard me-2 text-primary"></i> Uczeń: nauka, misje, rangi
                    </button>
                </h2>
                <div id="collapseStudent" class="accordion-collapse collapse" aria-labelledby="headingStudent" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        <strong>Testy:</strong> wybierz kategorię, tryb i liczbę pytań. Wynik zapisuje się po zakończeniu testu.
                        <br><br>
                        <strong>Misje:</strong> dzienne zadania dają XP po spełnieniu warunków. Misja testowa wymaga pełnego testu, chyba że opis mówi inaczej.
                        <br><br>
                        <strong>Rangi:</strong> zdobywasz je za XP. Progres widzisz na dashboardzie, profilu i rankingu.
                        <br><br>
                        <strong>Kategorie:</strong> kafelki prowadzą do opisów kwalifikacji, statystyk i startu nauki.
                        <br><br>
                        <strong>Praktyka:</strong> osobna zakładka opisuje przebieg egzaminu zawodowego praktycznego, punktację, sesje i protipy.
                    </div>
                </div>
            </div>

            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingPractice">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePractice" aria-expanded="false" aria-controls="collapsePractice" tabindex="0">
                        <i class="bi bi-tools me-2 text-danger"></i> Egzamin praktyczny
                    </button>
                </h2>
                <div id="collapsePractice" class="accordion-collapse collapse" aria-labelledby="headingPractice" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        <strong>Jak pracować z arkuszem?</strong><br>
                        Najpierw przeczytaj całość, wypisz wymagane pliki, nazwy kont, adresy, hasła testowe i kryteria. Dopiero potem konfiguruj lub koduj.
                        <br><br>
                        <strong>Co sprawdzać?</strong><br>
                        Działanie po odświeżeniu, poprawność danych, ścieżki plików, uprawnienia, walidację formularzy, usługi sieciowe i dokumentację.
                        <br><br>
                        <strong>Gdzie są materiały?</strong><br>
                        Wejdź w zakładkę Praktyka oraz Kategorie. Tam znajdziesz opis kwalifikacji, zakres i link do arkuszy CKE.
                    </div>
                </div>
            </div>

            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingSocial">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSocial" aria-expanded="false" aria-controls="collapseSocial" tabindex="0">
                        <i class="bi bi-people me-2 text-primary"></i> Społeczność i profil
                    </button>
                </h2>
                <div id="collapseSocial" class="accordion-collapse collapse" aria-labelledby="headingSocial" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        <strong>Profil:</strong> opis, linki, edukacja i certyfikaty pomagają pokazać postęp. Profil zawodowy pokazuje się publicznie dopiero po uzupełnieniu danych.
                        <br><br>
                        <strong>Znajomi:</strong> możesz wysyłać zaproszenia, akceptować je i startować pojedynki ze znajomymi.
                        <br><br>
                        <strong>Prywatność:</strong> w ustawieniach kontrolujesz widoczność profilu, statystyk, komentarzy i zaproszeń.
                    </div>
                </div>
            </div>

            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingDictionary">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDictionary" aria-expanded="false" aria-controls="collapseDictionary" tabindex="0">
                        <i class="bi bi-book me-2 text-success"></i> Słownik i nauka pojęć
                    </button>
                </h2>
                <div id="collapseDictionary" class="accordion-collapse collapse" aria-labelledby="headingDictionary" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        Słownik służy do szybkiego wyjaśniania pojęć z kwalifikacji. Używaj wyszukiwarki, filtrów kwalifikacji i alfabetu. Przy pojęciach są przykłady oraz linki do dodatkowych materiałów.
                    </div>
                </div>
            </div>

            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingSandbox">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSandbox" aria-expanded="false" aria-controls="collapseSandbox" tabindex="0">
                        <i class="bi bi-cpu me-2 text-primary"></i> Sandbox i narzędzia
                    </button>
                </h2>
                <div id="collapseSandbox" class="accordion-collapse collapse" aria-labelledby="headingSandbox" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        <strong>Sandbox:</strong> w lewym menu znajdziesz bramki logiczne, live HTML/CSS/JS, kalkulator zasilacza, konwerter systemów liczbowych i kalkulator podsieci.
                        <br><br>
                        <strong>Live kod:</strong> uruchamia tylko kod frontendowy w izolowanym iframe, więc nadaje się do ćwiczeń HTML, CSS i JavaScript.
                    </div>
                </div>
            </div>

            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingAccount">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccount" aria-expanded="false" aria-controls="collapseAccount" tabindex="0">
                        <i class="bi bi-person-gear me-2 text-warning"></i> Konto i prywatność
                    </button>
                </h2>
                <div id="collapseAccount" class="accordion-collapse collapse" aria-labelledby="headingAccount" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        W ustawieniach zmienisz e-mail, klasę, hasło, widoczność profilu, zaproszenia do znajomych oraz preferencje wyglądu.
                    </div>
                </div>
            </div>

            <div class="accordion-item help-section">
                <h2 class="accordion-header" id="headingFaq">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaq" aria-expanded="false" aria-controls="collapseFaq" tabindex="0">
                        <i class="bi bi-info-circle me-2 text-info"></i> FAQ
                    </button>
                </h2>
                <div id="collapseFaq" class="accordion-collapse collapse" aria-labelledby="headingFaq" data-bs-parent="#helpAccordion">
                    <div class="accordion-body small help-content">
                        <strong>Nie działa kod sprawdzianu?</strong><br>
                        Sprawdź wielkość liter, ważność sesji i czy sprawdzian nadal jest otwarty.
                        <br><br>
                        <strong>Dlaczego mam ostrzeżenie?</strong><br>
                        System wykrywa przełączanie kart, utratę fokusu, wyjście z pełnego ekranu oraz kopiowanie.
                        <br><br>
                        <strong>Jak usunąć powiadomienie?</strong><br>
                        Wejdź w Powiadomienia i użyj ikony kosza przy konkretnym wpisie albo przycisku „Usuń wszystkie”.
                        <br><br>
                        <strong>Jak działa rewanż w pojedynkach?</strong><br>
                        Po zakończonym pojedynku możesz wysłać nowe wyzwanie. Czasem system pokaże rewanż x2, jeśli tryb pojedynku to obsługuje.
                        <br><br>
                        <strong>Gdzie zgłosić błąd?</strong><br>
                        Użyj strony Kontakt. Podaj nazwę zakładki, opis kroków, oczekiwany efekt i zrzut ekranu, jeśli problem dotyczy wyglądu.
                    </div>
                </div>
            </div>
        </div>

        <div class="help-contact-card">
            <div>
                <strong>Nie znalazłeś odpowiedzi?</strong>
                <p class="mb-0 small text-muted">Napisz przez formularz kontaktowy albo opisz problem w zgłoszeniu.</p>
            </div>
            <a href="<?php echo $base_url; ?>contact.php" class="btn btn-primary btn-sm rounded-pill">Kontakt</a>
        </div>

        <div id="noResultsHelp" class="text-center p-4 d-none">
            <i class="bi bi-search text-muted" style="font-size: 2rem;"></i>
            <p class="mt-2 text-muted small">Brak wyników dla podanego hasła.</p>
        </div>
    </div>
</div>

<button class="help-fab" type="button" data-bs-toggle="offcanvas" data-bs-target="#helpCenterOffcanvas" data-help-center-trigger aria-controls="helpCenterOffcanvas" aria-expanded="false" aria-label="Otwórz centrum pomocy" title="Centrum Pomocy">
    <i class="bi bi-question-lg"></i>
</button>

<style>
.help-fab {
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
.help-fab i { line-height: 1; }
#helpCenterOffcanvas.show ~ .help-fab,
#helpCenterOffcanvas.showing ~ .help-fab {
    opacity: 0 !important;
    pointer-events: none !important;
    transform: scale(0) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const panel = document.getElementById('helpCenterOffcanvas');
    const fab = document.querySelector('[data-help-center-trigger]');

    // Avoid clipping by transformed or overflow-hidden layout containers.
    if (panel && panel.parentElement !== document.body) document.body.appendChild(panel);
    if (fab && fab.parentElement !== document.body) document.body.appendChild(fab);
    panel?.classList.remove('d-none');
    fab?.classList.remove('d-none');
    fab?.removeAttribute('aria-hidden');

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

    const searchInput = document.getElementById('helpSearchInput');
    const helpSections = document.querySelectorAll('.help-section');
    const noResults = document.getElementById('noResultsHelp');

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            let visibleCount = 0;

            helpSections.forEach(function(section) {
                const title = section.querySelector('.accordion-button').innerText.toLowerCase();
                const content = section.querySelector('.help-content').innerText.toLowerCase();
                const visible = title.includes(term) || content.includes(term);
                section.style.display = visible ? 'block' : 'none';
                if (visible) visibleCount++;
                if (visible && term !== '') {
                    const collapse = section.querySelector('.accordion-collapse');
                    if (collapse && window.bootstrap) {
                        new bootstrap.Collapse(collapse, { toggle: false }).show();
                    }
                }
            });

            noResults.classList.toggle('d-none', visibleCount > 0 || term === '');
        });
    }
});
</script>
