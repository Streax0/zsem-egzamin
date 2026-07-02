<?php
if (!isset($base_url)) {
    $base_url = file_exists('config/db.php') ? '' : '../';
}
?>
<div class="cookie-consent" id="cookieConsent" hidden role="dialog" aria-modal="false" aria-labelledby="cookieConsentTitle" aria-describedby="cookieConsentDesc">
    <div class="cookie-consent__content">
        <div class="cookie-consent__main">
            <strong id="cookieConsentTitle">Ustawienia cookies</strong>
            <p id="cookieConsentDesc">
                Niezbędne cookies utrzymują logowanie i bezpieczeństwo. Opcjonalne cookies zapisują preferencje interfejsu.
                Nie uruchamiamy analityki ani marketingu przed zgodą.
                Szczegóły: <a href="<?php echo $base_url; ?>pages/polityka-cookies.php">Polityka cookies</a>.
            </p>

            <div class="cookie-consent__details" id="cookieConsentDetails" hidden>
                <fieldset>
                    <legend class="visually-hidden">Kategorie cookies</legend>
                    <label class="cookie-consent__option">
                        <input type="checkbox" checked disabled>
                        <span><strong>Niezbędne</strong><small>Sesja, CSRF, bezpieczeństwo. Zawsze aktywne.</small></span>
                    </label>
                    <label class="cookie-consent__option">
                        <input type="checkbox" id="cookieCategoryPreferences">
                        <span><strong>Preferencje</strong><small>Motyw, rozmiar tekstu, układ dashboardu.</small></span>
                    </label>
                    <label class="cookie-consent__option">
                        <input type="checkbox" id="cookieCategoryAnalytics">
                        <span><strong>Analityczne</strong><small>Obecnie brak aktywnych skryptów analitycznych.</small></span>
                    </label>
                    <label class="cookie-consent__option">
                        <input type="checkbox" id="cookieCategoryMarketing">
                        <span><strong>Marketingowe</strong><small>Obecnie brak aktywnych pikseli marketingowych.</small></span>
                    </label>
                </fieldset>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="cookieSaveCustom">Zapisz wybór</button>
            </div>
        </div>
        <div class="cookie-consent__actions" aria-label="Decyzja cookies">
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="cookieAcceptAll">Akceptuj wszystkie</button>
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="cookieRejectAll">Odrzuć wszystkie</button>
            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="cookieCustomize">Dostosuj</button>
        </div>
    </div>
</div>

<style>
.cookie-consent[hidden] {
    display: none !important;
}
.cookie-consent {
    position: fixed;
    right: 1.5rem;
    bottom: max(1.5rem, env(safe-area-inset-bottom));
    left: 1.5rem;
    z-index: 1080;
    display: flex;
    justify-content: center;
    pointer-events: none;
}
.cookie-consent__content {
    width: min(860px, 100%);
    max-height: min(85vh, 700px);
    overflow-y: auto;
    background: var(--kolor-tlo-wtoczne, #ffffff);
    color: var(--kolor-tekst, #1e293b);
    border: 1px solid var(--kolor-ramka, #e2e8f0);
    border-radius: var(--radius-xxl, 1.25rem);
    box-shadow: var(--cień-duzy, 0 10px 25px rgba(0, 0, 0, 0.12));
    padding: 1.5rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1.5rem;
    align-items: start;
    pointer-events: auto;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: var(--przejscie, all 0.3s ease);
}
body.dark-mode .cookie-consent__content {
    background: rgba(15, 23, 42, 0.95);
    border-color: rgba(255,255,255,0.05);
    color: #f8fafc;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.cookie-consent__main strong {
    font-size: 1.15rem;
    color: var(--kolor-glowy, #667eea);
    display: block;
    margin-bottom: 0.5rem;
}
body.dark-mode .cookie-consent__main strong {
    color: #818cf8;
}
.cookie-consent__main p {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.5;
    color: var(--kolor-tekst-muted, #475569);
}
body.dark-mode .cookie-consent__main p {
    color: #94a3b8;
}
.cookie-consent__main p a {
    color: var(--kolor-glowy, #667eea);
    text-decoration: none;
    font-weight: 500;
}
.cookie-consent__main p a:hover {
    text-decoration: underline;
}
.cookie-consent__actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 200px;
}
.cookie-consent__actions .btn {
    width: 100%;
    border-radius: var(--radius-standardowy, 0.5rem);
    font-weight: 500;
    padding: 0.6rem 1rem;
    transition: all 0.2s ease;
}
.cookie-consent__actions #cookieAcceptAll {
    background: var(--gradient-podstawowy, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    color: white;
    border: none;
    box-shadow: var(--cień-przycisk, 0 4px 12px rgba(102, 126, 234, 0.3));
}
.cookie-consent__actions #cookieAcceptAll:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}
.cookie-consent__actions #cookieRejectAll,
.cookie-consent__actions #cookieCustomize {
    background: transparent;
    color: var(--kolor-tekst-muted, #475569);
    border: 1px solid var(--kolor-ramka, #e2e8f0);
}
body.dark-mode .cookie-consent__actions #cookieRejectAll,
body.dark-mode .cookie-consent__actions #cookieCustomize {
    color: #cbd5e1;
    border-color: rgba(255,255,255,0.1);
}
.cookie-consent__actions #cookieRejectAll:hover,
.cookie-consent__actions #cookieCustomize:hover {
    background: var(--kolor-ramka, #f1f5f9);
    color: var(--kolor-tekst, #1e293b);
}
body.dark-mode .cookie-consent__actions #cookieRejectAll:hover,
body.dark-mode .cookie-consent__actions #cookieCustomize:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}
.cookie-consent__details {
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--kolor-ramka, #e2e8f0);
}
body.dark-mode .cookie-consent__details {
    border-top-color: rgba(255,255,255,0.1);
}
.cookie-consent__option {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.75rem;
    align-items: start;
    margin-bottom: 0.8rem;
    padding: 0.75rem;
    border-radius: var(--radius-standardowy, 0.5rem);
    background: var(--kolor-tlo, #f8fafc);
    border: 1px solid transparent;
    transition: border-color 0.2s ease;
}
body.dark-mode .cookie-consent__option {
    background: rgba(15, 23, 42, 0.5);
}
.cookie-consent__option:hover {
    border-color: var(--kolor-glowy, #667eea);
}
.cookie-consent__option strong {
    font-size: 0.95rem;
    color: var(--kolor-tekst, #1e293b);
    display: block;
    margin-bottom: 0.1rem;
}
body.dark-mode .cookie-consent__option strong {
    color: #f8fafc;
}
.cookie-consent__option small {
    display: block;
    color: var(--kolor-tekst-muted, #64748b);
    font-size: 0.85rem;
}
body.dark-mode .cookie-consent__option small {
    color: #94a3b8;
}
.cookie-consent__option input[type="checkbox"] {
    margin-top: 0.2rem;
    width: 1.1rem;
    height: 1.1rem;
    accent-color: var(--kolor-glowy, #667eea);
    cursor: pointer;
}
#cookieSaveCustom {
    margin-top: 1rem;
    width: auto;
    background: var(--kolor-glowy, #667eea);
    color: white;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: var(--radius-standardowy, 0.5rem);
    transition: all 0.2s ease;
}
#cookieSaveCustom:hover {
    background: var(--kolor-glowy-dark, #5a67d8);
    transform: translateY(-2px);
}
@media (max-width: 767.98px) {
    .cookie-consent {
        bottom: 0;
        right: 0;
        left: 0;
    }
    .cookie-consent__content {
        grid-template-columns: 1fr;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
        padding: 1.25rem;
    }
    .cookie-consent__actions {
        flex-direction: row;
        min-width: 100%;
    }
    .cookie-consent__actions .btn {
        flex: 1 1 calc(50% - 0.5rem);
    }
    .cookie-consent__actions #cookieCustomize {
        flex: 1 1 100%;
    }
}
</style>

<script>
(function () {
    const banner = document.getElementById('cookieConsent');
    if (!banner) return;

    const version = '2026-05-17';
    const maxAge = 60 * 60 * 24 * 183;
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    const details = document.getElementById('cookieConsentDetails');
    const preferences = document.getElementById('cookieCategoryPreferences');
    const analytics = document.getElementById('cookieCategoryAnalytics');
    const marketing = document.getElementById('cookieCategoryMarketing');

    const getLocal = (name) => {
        try {
            return window.localStorage.getItem(name) || '';
        } catch (error) {
            return '';
        }
    };
    const setLocal = (name, value) => {
        try {
            window.localStorage.setItem(name, value);
        } catch (error) {
            // ignore storage errors in privacy-locked browsers
        }
    };
    const deleteLocal = (name) => {
        try {
            window.localStorage.removeItem(name);
        } catch (error) {
            // ignore storage errors in privacy-locked browsers
        }
    };
    const getCookie = (name) => {
        const prefix = name + '=';
        const cookieValue = document.cookie.split('; ').find(row => row.startsWith(prefix))?.slice(prefix.length);
        return cookieValue || getLocal(name) || '';
    };
    const setCookie = (name, value) => {
        document.cookie = name + '=' + encodeURIComponent(value) + '; Max-Age=' + maxAge + '; Path=/; SameSite=Lax' + secure;
        setLocal(name, value);
    };
    const deleteOptionalPreferenceCookies = () => {
        ['user_theme', 'user_font_size', 'user_density', 'user_accent', 'reduce_motion', 'dashboard_view', 'default_test_mode', 'external_new_tab', 'hide_help_center'].forEach((name) => {
            document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax' + secure;
            deleteLocal(name);
        });
    };
    const parseConsent = () => {
        try {
            const raw = getCookie('cookie_consent_v2');
            return raw ? JSON.parse(decodeURIComponent(raw)) : null;
        } catch (error) {
            return null;
        }
    };
    const saveConsent = (categories, source) => {
        const value = {
            version,
            timestamp: new Date().toISOString(),
            source,
            categories: {
                necessary: true,
                preferences: true, // Treated as necessary for layout/styling
                analytics: !!categories.analytics,
                marketing: !!categories.marketing
            }
        };
        setCookie('cookie_consent_v2', JSON.stringify(value));
        setCookie('cookie_consent', 'accepted');
        banner.hidden = true;
        window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail: value }));
    };
    const showSettings = () => {
        const current = parseConsent();
        if (current?.categories) {
            preferences.checked = !!current.categories.preferences;
            analytics.checked = !!current.categories.analytics;
            marketing.checked = !!current.categories.marketing;
        }
        details.hidden = false;
        banner.hidden = false;
        preferences.focus();
    };

    document.getElementById('cookieRejectAll')?.addEventListener('click', () => saveConsent({ preferences: false, analytics: false, marketing: false }, 'reject_all'));
    document.getElementById('cookieAcceptAll')?.addEventListener('click', () => saveConsent({ preferences: true, analytics: true, marketing: true }, 'accept_all'));
    document.getElementById('cookieCustomize')?.addEventListener('click', showSettings);
    document.getElementById('cookieSaveCustom')?.addEventListener('click', () => saveConsent({
        preferences: preferences.checked,
        analytics: analytics.checked,
        marketing: marketing.checked
    }, 'custom'));

    window.addEventListener('open-cookie-settings', showSettings);
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-cookie-settings]');
        if (!trigger) return;
        event.preventDefault();
        showSettings();
    });

    const current = parseConsent();
    if (!current || current.version !== version || !current.categories) {
        banner.hidden = false;
    }
})();
</script>
