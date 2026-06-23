/**
 * Global UI preference handler.
 */

(function() {
    if (window.__zsemThemeHandlerLoaded) {
        return;
    }
    window.__zsemThemeHandlerLoaded = true;

    const preferenceCookieNames = [
        'user_theme',
        'user_font_size',
        'user_density',
        'user_accent',
        'reduce_motion',
        'dashboard_view',
        'default_test_mode',
        'external_new_tab',
        'welcome_banner_style'
    ];

    function setCookie(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value || '') + expires + '; path=/; SameSite=Lax' + secure;
    }

    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) {
                try {
                    return decodeURIComponent(c.substring(nameEQ.length, c.length));
                } catch (error) {
                    return c.substring(nameEQ.length, c.length);
                }
            }
        }
        return null;
    }

    function deleteCookie(name) {
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax' + secure;
    }

    function optionalCookiesAllowed() {
        return true;
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

    function getLocalPreference(name, fallback = '0') {
        try {
            return window.localStorage.getItem(name) || fallback;
        } catch (error) {
            return fallback;
        }
    }

    function setLocalPreference(name, value) {
        try { window.localStorage.setItem(name, value); } catch (error) {}
    }

    function bodyReady() {
        return !!document.body;
    }

    function applyDefaultTestModePreference() {
        const mode = getPreference('default_test_mode', 'exam');
        const targets = {
            exam: { href: 'test.php?mode=exam&setup=1', label: 'Rozpocznij test' },
            practice: { href: 'test.php?mode=practice&setup=1', label: 'Ćwiczenia' },
            single: { href: 'test.php?mode=single&setup=1&new=1', label: 'Jedno pytanie' }
        };
        const target = targets[mode] || targets.exam;
        document.querySelectorAll('[data-default-test-start]').forEach((quickStart) => {
            quickStart.href = target.href;
            const label = quickStart.querySelector('[data-default-test-label]');
            if (label) label.textContent = target.label;
        });
    }

    function applyWelcomeBannerStylePreference() {
        if (!bodyReady()) return;
        const allowed = ['gradient', 'pure', 'aurora', 'glass'];
        const style = allowed.includes(getPreference('welcome_banner_style', 'gradient')) ? getPreference('welcome_banner_style', 'gradient') : 'gradient';
        document.body.classList.remove('welcome-style-gradient', 'welcome-style-pure', 'welcome-style-aurora', 'welcome-style-glass');
        document.body.classList.add('welcome-style-' + style);
    }

    function applyDashboardViewPreference() {
        if (!bodyReady()) return;
        const allowed = ['balanced', 'learning', 'compact'];
        const view = allowed.includes(getPreference('dashboard_view', 'balanced')) ? getPreference('dashboard_view', 'balanced') : 'balanced';
        document.body.classList.remove('dashboard-view-balanced', 'dashboard-view-learning', 'dashboard-view-compact');
        document.body.classList.add('dashboard-view-' + view);
    }

    function isExternalLink(anchor) {
        const href = anchor.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
            return false;
        }
        try {
            return new URL(anchor.href, window.location.href).origin !== window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function applyExternalLinkPreference() {
        const openInNewTab = getPreference('external_new_tab', '1') === '1';
        document.querySelectorAll('a[href]').forEach((anchor) => {
            if (!isExternalLink(anchor)) return;
            if (!anchor.dataset.externalPreferenceOriginalTarget) {
                anchor.dataset.externalPreferenceOriginalTarget = anchor.getAttribute('target') || '';
            }
            if (openInNewTab) {
                anchor.setAttribute('target', '_blank');
                const rel = new Set((anchor.getAttribute('rel') || '').split(/\s+/).filter(Boolean));
                rel.add('noopener');
                rel.add('noreferrer');
                anchor.setAttribute('rel', Array.from(rel).join(' '));
            } else {
                anchor.removeAttribute('target');
            }
        });
    }

    function applySettings() {
        if (!bodyReady()) return;
        if (getCookie('cookie_consent') === 'rejected') {
            preferenceCookieNames.forEach(deleteCookie);
        }
        const theme = getPreference('user_theme', 'light');
        const fontSize = getPreference('user_font_size', '16');
        const density = getPreference('user_density', 'comfortable');
        const accent = getPreference('user_accent', '#3b82f6');
        const reduceMotion = getPreference('reduce_motion', '0') === '1';

        document.body.classList.toggle('dark-mode', theme === 'dark');
        document.body.classList.toggle('light-mode', theme !== 'dark');
        document.documentElement.style.colorScheme = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.style.fontSize = (/^(14|16|18)$/.test(fontSize) ? fontSize : '16') + 'px';
        document.documentElement.style.setProperty('--primary-color', /^#[0-9a-fA-F]{6}$/.test(accent) ? accent : '#3b82f6');
        document.documentElement.style.setProperty('--kolor-glowy', /^#[0-9a-fA-F]{6}$/.test(accent) ? accent : '#3b82f6');
        document.body.classList.toggle('ui-compact', density === 'compact');
        document.body.classList.toggle('reduce-motion', reduceMotion);

        applyDefaultTestModePreference();
        applyDashboardViewPreference();
        applyWelcomeBannerStylePreference();
        applyExternalLinkPreference();
        window.syncSettingsPreferencePanel?.();
    }

    function playUiPreferenceChime(force = false) {
        if (!force && getLocalPreference('ui_sounds', '0') !== '1') return;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(740, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(520, ctx.currentTime + 0.13);
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.045, ctx.currentTime + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.16);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.17);
            window.setTimeout(() => ctx.close?.(), 240);
        } catch (error) {}
    }

    window.testPreferenceFeedback = function testPreferenceFeedback(message = 'Preferencje zapisane') {
        const soundsOn = getLocalPreference('ui_sounds', '0') === '1';
        window.appNotice?.(soundsOn ? message + ' Dźwięk aktywny.' : message + ' Dźwięki są wyłączone.', 'success');
        if (soundsOn) playUiPreferenceChime(true);
        window.syncSettingsPreferencePanel?.();
    };

    window.zsemNotifyUnreadCountChanged = function zsemNotifyUnreadCountChanged(currentCount, previousCount) {
        if (currentCount <= previousCount || getLocalPreference('notify_new_tests', '0') !== '1') return;
        const delta = currentCount - previousCount;
        window.appNotice?.(delta === 1 ? 'Masz nową aktywność.' : 'Masz nowe aktywności: ' + delta + '.', 'primary');
        playUiPreferenceChime();
    };

    document.addEventListener('DOMContentLoaded', () => {
        applySettings();
        const root = document.documentElement || document.body;
        if (root instanceof Node) {
            const observer = new MutationObserver(() => applyExternalLinkPreference());
            observer.observe(root, { childList: true, subtree: true });
        }
    });

    window.updateThemeSetting = function(theme) {
        setPreference('user_theme', theme === 'dark' ? 'dark' : 'light');
        applySettings();
        window.testPreferenceFeedback?.('Motyw zapisany.');
    };

    window.updateFontSizeSetting = function(size) {
        setPreference('user_font_size', /^(14|16|18)$/.test(size) ? size : '16');
        applySettings();
        window.testPreferenceFeedback?.('Rozmiar tekstu zapisany.');
    };

    window.updateDensitySetting = function(density) {
        setPreference('user_density', density === 'compact' ? 'compact' : 'comfortable');
        applySettings();
        window.testPreferenceFeedback?.('Gęstość interfejsu zapisana.');
    };

    window.updateAccentSetting = function(accent) {
        setPreference('user_accent', /^#[0-9a-fA-F]{6}$/.test(accent) ? accent : '#3b82f6');
        applySettings();
        window.testPreferenceFeedback?.('Kolor akcentu zapisany.');
    };

    window.updateReduceMotionSetting = function(enabled) {
        setPreference('reduce_motion', enabled ? '1' : '0');
        applySettings();
        window.testPreferenceFeedback?.('Preferencja animacji zapisana.');
    };

    window.updateDashboardViewSetting = function(view) {
        const allowed = ['balanced', 'learning', 'compact'];
        setPreference('dashboard_view', allowed.includes(view) ? view : 'balanced');
        applyDashboardViewPreference();
        applySettings();
        window.testPreferenceFeedback?.('Widok dashboardu zapisany.');
    };

    window.updateDefaultTestModeSetting = function(mode) {
        const allowed = ['exam', 'practice', 'single'];
        setPreference('default_test_mode', allowed.includes(mode) ? mode : 'exam');
        applyDefaultTestModePreference();
        applySettings();
        window.testPreferenceFeedback?.('Domyślny tryb testu zapisany.');
    };

    window.updateWelcomeBannerStyleSetting = function(style) {
        const allowed = ['gradient', 'pure', 'aurora', 'glass'];
        setPreference('welcome_banner_style', allowed.includes(style) ? style : 'gradient');
        applyWelcomeBannerStylePreference();
        applySettings();
        window.testPreferenceFeedback?.('Styl baneru powitalnego zapisany.');
    };

    window.updateNotifyActivitySetting = function(enabled) {
        setLocalPreference('notify_new_tests', enabled ? '1' : '0');
        window.syncSettingsPreferencePanel?.();
        window.appNotice?.(enabled ? 'Alerty o aktywnościach włączone.' : 'Alerty o aktywnościach wyłączone.', 'success');
        playUiPreferenceChime();
    };

    window.updateUiSoundsSetting = function(enabled) {
        setLocalPreference('ui_sounds', enabled ? '1' : '0');
        window.syncSettingsPreferencePanel?.();
        window.appNotice?.(enabled ? 'Efekty dźwiękowe włączone.' : 'Efekty dźwiękowe wyłączone.', 'success');
        if (enabled) playUiPreferenceChime(true);
    };

    window.updateExternalNewTabSetting = function(enabled) {
        setPreference('external_new_tab', enabled ? '1' : '0');
        applyExternalLinkPreference();
        applySettings();
        window.testPreferenceFeedback?.('Preferencja linków zapisana.');
    };

    window.getUiPreference = getPreference;
    window.setUiPreference = function(name, value) {
        setPreference(name, value);
        applySettings();
        window.testPreferenceFeedback?.('Preferencja zapisana.');
    };
    window.playUiPreferenceChime = playUiPreferenceChime;
    window.applyStoredUiPreferences = applySettings;
    window.applyUiPreferences = applySettings;



    window.addEventListener('cookie-consent-updated', function(event) {
        const categories = event.detail && event.detail.categories;
        if ((event.detail && event.detail.value === 'rejected') || (categories && !categories.preferences)) {
            preferenceCookieNames.forEach(deleteCookie);
            applySettings();
        }
    });
})();
