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
                Szczegóły: <a href="<?php echo $base_url; ?>polityka-cookies.php">Polityka cookies</a>.
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
.cookie-consent {
    position: fixed;
    right: 1rem;
    bottom: max(1rem, env(safe-area-inset-bottom));
    left: 1rem;
    z-index: 1080;
    display: flex;
    justify-content: center;
    pointer-events: none;
}
.cookie-consent__content {
    width: min(860px, 100%);
    max-height: min(78vh, 620px);
    overflow: auto;
    background: #ffffff;
    color: #1f2937;
    border: 1px solid #d1d5db;
    border-radius: 16px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.2);
    padding: 1rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
    align-items: start;
    pointer-events: auto;
}
.cookie-consent__main p {
    margin: .25rem 0 0;
    font-size: .9rem;
}
.cookie-consent__actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.cookie-consent__details {
    margin-top: .9rem;
    padding-top: .75rem;
    border-top: 1px solid #d1d5db;
}
.cookie-consent__option {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: .6rem;
    align-items: start;
    margin-bottom: .55rem;
}
.cookie-consent__option small {
    display: block;
    color: #64748b;
}
@media (max-width: 767.98px) {
    .cookie-consent {
        bottom: .75rem;
        right: .75rem;
        left: .75rem;
    }
    .cookie-consent__content {
        grid-template-columns: 1fr;
    }
    .cookie-consent__actions .btn {
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
