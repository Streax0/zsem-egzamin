/**
 * Theme and Font Size handler using cookies
 */

(function() {
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax" + secure;
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    function deleteCookie(name) {
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + secure;
    }

    function getPreference(name, fallback) {
        try {
            return getCookie(name) || window.localStorage.getItem(name) || fallback;
        } catch (error) {
            return getCookie(name) || fallback;
        }
    }

    function setPreference(name, value) {
        try { window.localStorage.setItem(name, value); } catch (error) {}
        if (optionalCookiesAllowed()) {
            setCookie(name, value, 183);
        } else {
            deleteCookie(name);
        }
    }

    function applyHelpCenterPreference() {
        const hidden = getPreference('hide_help_center', '0') === '1';
        document.querySelectorAll('.help-fab, #helpCenterOffcanvas').forEach((node) => {
            node.classList.toggle('d-none', hidden);
            if (hidden) {
                node.setAttribute('aria-hidden', 'true');
            } else {
                node.removeAttribute('aria-hidden');
            }
        });
    }

    function applyDefaultTestModePreference() {
        const mode = getPreference('default_test_mode', 'exam');
        const targets = {
            exam: { href: 'test.php?mode=exam&setup=1', label: 'Rozpocznij test' },
            practice: { href: 'test.php?mode=practice&setup=1', label: 'Ćwiczenia' },
            single: { href: 'test.php?mode=single&start=1&new=1', label: 'Jedno pytanie' }
        };
        const target = targets[mode] || targets.exam;
        document.querySelectorAll('[data-default-test-start]').forEach((quickStart) => {
            quickStart.href = target.href;
            const label = quickStart.querySelector('[data-default-test-label]');
            if (label) label.textContent = target.label;
        });
    }

    function optionalCookiesAllowed() {
        try {
            const consent = getCookie('cookie_consent_v2');
            if (consent) {
                const parsed = JSON.parse(decodeURIComponent(consent));
                return !!(parsed.categories && parsed.categories.preferences);
            }
        } catch (error) {
            return false;
        }
        return getCookie('cookie_consent') === 'accepted';
    }

    function applySettings() {
        if (getCookie('cookie_consent') === 'rejected') {
            deleteCookie('user_theme');
            deleteCookie('user_font_size');
        }
        const theme = getPreference('user_theme', 'light');
        const fontSize = getPreference('user_font_size', '16');
        const density = getPreference('user_density', 'comfortable');
        const accent = getPreference('user_accent', '#3b82f6');
        const reduceMotion = getPreference('reduce_motion', '0') === '1';

        // Apply theme
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }

        // Apply font size
        document.documentElement.style.fontSize = fontSize + 'px';
        document.documentElement.style.setProperty('--primary-color', accent);
        document.documentElement.style.setProperty('--kolor-glowy', accent);
        document.body.classList.toggle('ui-compact', density === 'compact');
        document.body.classList.toggle('reduce-motion', reduceMotion);
        applyHelpCenterPreference();
        applyDefaultTestModePreference();
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', applySettings);

    // Expose to window for settings.php
    window.updateThemeSetting = function(theme) {
        setPreference('user_theme', theme);
        applySettings();
    };

    window.updateFontSizeSetting = function(size) {
        setPreference('user_font_size', size);
        applySettings();
    };

    window.updateDensitySetting = function(density) {
        setPreference('user_density', density);
        applySettings();
    };

    window.updateAccentSetting = function(accent) {
        setPreference('user_accent', accent);
        applySettings();
    };

    window.updateReduceMotionSetting = function(enabled) {
        setPreference('reduce_motion', enabled ? '1' : '0');
        applySettings();
    };

    window.getUiPreference = getPreference;
    window.setUiPreference = function(name, value) {
        setPreference(name, value);
        applySettings();
    };

    window.addEventListener('cookie-consent-updated', function(event) {
        const categories = event.detail && event.detail.categories;
        if ((event.detail && event.detail.value === 'rejected') || (categories && !categories.preferences)) {
            deleteCookie('user_theme');
            deleteCookie('user_font_size');
            deleteCookie('user_density');
            deleteCookie('user_accent');
            deleteCookie('reduce_motion');
            deleteCookie('dashboard_view');
            deleteCookie('default_test_mode');
            deleteCookie('external_new_tab');
            deleteCookie('hide_help_center');
            applySettings();
        }
    });
})();
