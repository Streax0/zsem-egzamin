/**
 * ZSEM Tech - User Settings Module
 * Handles profile avatar previews, UI preferences synchronization,
 * tab persistence, password validation, and WebAuthn Passkeys.
 */
(function (window, document) {
    'use strict';

    const safeStorage = (function () {
        const memoryStore = {};
        return {
            getItem: function (key, fallback) {
                try {
                    return localStorage.getItem(key) || fallback;
                } catch (e) {
                    return memoryStore.hasOwnProperty(key) ? memoryStore[key] : fallback;
                }
            },
            setItem: function (key, value) {
                try {
                    localStorage.setItem(key, value);
                } catch (e) {
                    memoryStore[key] = String(value);
                }
            },
            removeItem: function (key) {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    delete memoryStore[key];
                }
            }
        };
    })();

    function showNotice(msg, type) {
        if (window.appNotice) {
            window.appNotice(msg, type);
        } else {
            console.warn(msg);
        }
    }

    function preferenceCookiesAllowed() {
        const getCookie = (name) => {
            const row = document.cookie.split('; ').find(r => r.startsWith(name + '='));
            return row ? row.slice(name.length + 1) : '';
        };
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

    const dashboardLabels = { balanced: 'Zbalansowany', learning: 'Nauka', compact: 'Kompakt' };
    const defaultModeLabels = { exam: 'Egzamin', practice: 'Ćwiczenia', single: 'Jedno pytanie' };

    function setPreferenceStatus(key, value) {
        const target = document.querySelector(`[data-preference-status="${key}"]`);
        if (target) target.textContent = value;
    }

    function readPreference(name, fallback) {
        if (window.getUiPreference) return window.getUiPreference(name, fallback);
        const cookieRow = document.cookie.split('; ').find(row => row.startsWith(name + '='));
        const cookie = cookieRow ? cookieRow.slice(name.length + 1) : undefined;
        try {
            return cookie ? decodeURIComponent(cookie) : (safeStorage.getItem(name, fallback));
        } catch (error) {
            return cookie ? decodeURIComponent(cookie) : fallback;
        }
    }

    function syncSettingsMiniCards() {
        const notify = document.querySelector('[data-settings-mini="notify"] [data-settings-mini-value]');
        const layout = document.querySelector('[data-settings-mini="layout"] [data-settings-mini-value]');
        const theme = document.querySelector('[data-settings-mini="theme"] [data-settings-mini-value]');
        const notifyEnabled = safeStorage.getItem('notify_new_tests', '0') === '1';
        const soundsEnabled = safeStorage.getItem('ui_sounds', '0') === '1';
        const dbViewEl = document.getElementById('dashboardView');
        const dashboard = dbViewEl ? dbViewEl.value : readPreference('dashboard_view', 'balanced');
        const defModeEl = document.getElementById('defaultTestMode');
        const defaultMode = defModeEl ? defModeEl.value : readPreference('default_test_mode', 'exam');
        const extTabEl = document.getElementById('externalTabSwitch');
        const external = extTabEl ? extTabEl.checked : false;
        const themeValue = document.body.classList.contains('dark-mode') ? 'Ciemny' : 'Jasny';
        if (notify) notify.textContent = notifyEnabled ? 'Włączone' : 'Wyłączone';
        if (layout) layout.textContent = dashboardLabels[dashboard] || 'Zbalansowany';
        if (theme) theme.textContent = themeValue;
        setPreferenceStatus('theme', themeValue);
        setPreferenceStatus('dashboard', dashboardLabels[dashboard] || dashboard);
        setPreferenceStatus('defaultMode', defaultModeLabels[defaultMode] || defaultMode);
        setPreferenceStatus('external', external ? 'Nowa karta' : 'Ta sama karta');
        setPreferenceStatus('notify', notifyEnabled ? 'Włączone' : 'Wyłączone');
        setPreferenceStatus('sounds', soundsEnabled ? 'Włączone' : 'Wyłączone');
    }
    window.syncSettingsPreferencePanel = syncSettingsMiniCards;

    function syncSettingsOverviewCards() {
        const setOverview = (key, value) => {
            const target = document.querySelector(`[data-settings-overview="${key}"] [data-settings-overview-value]`);
            if (target) target.textContent = value;
        };
        const themeSelEl = document.getElementById('themeSelect');
        const themeValue = (themeSelEl ? themeSelEl.value : 'light') === 'dark' ? 'Ciemny' : 'Jasny';
        const densSelEl = document.getElementById('densitySelect');
        const densityValue = (densSelEl ? densSelEl.value : 'comfortable') === 'compact' ? 'Kompakt' : 'Wygodny';
        setOverview('theme', themeValue);
        setOverview('density', densityValue);
    }

    function applyUiPreferences() {
        if (window.applyStoredUiPreferences) {
            window.applyStoredUiPreferences();
        }
        syncSettingsMiniCards();
        syncSettingsOverviewCards();
    }

    function syncAccentUi(accentColor) {
        let foundPreset = false;
        document.querySelectorAll('.accent-dot').forEach(dot => {
            const dotColor = dot.getAttribute('data-color');
            if (dotColor === accentColor) {
                dot.classList.add('active');
                foundPreset = true;
            } else {
                dot.classList.remove('active');
            }
        });
        const customInput = document.getElementById('accentColor');
        if (customInput) {
            customInput.style.setProperty('--accent-custom-color', accentColor);
            if (!foundPreset) {
                customInput.classList.add('active');
            } else {
                customInput.classList.remove('active');
            }
        }
    }

    function syncWelcomeBannerStyleUi(activeStyle) {
        document.querySelectorAll('.welcome-banner-style-card').forEach(card => {
            if (card.getAttribute('data-style') === activeStyle) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });
    }

    window.previewAvatar = function (input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const previewWrapper = document.querySelector('.avatar-preview-wrapper');
                if (previewWrapper) {
                    previewWrapper.innerHTML = `<img src="${e.target.result}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy" decoding="async">`;
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    window.triggerDeleteAvatar = function (event) {
        if (event) event.preventDefault();
        const form = document.getElementById('deleteAvatarForm');
        if (window.appConfirmSubmit) {
            window.appConfirmSubmit(form, 'Usunąć zdjęcie profilowe?');
        } else if (form) {
            form.submit();
        }
    };

    window.selectWelcomeBannerStyle = function (style) {
        const select = document.getElementById('welcomeBannerStyleSelect');
        if (select) {
            select.value = style;
            if (window.updateWelcomeBannerStyleSetting) {
                window.updateWelcomeBannerStyleSetting(style);
            }
            applyUiPreferences();
        }
        syncWelcomeBannerStyleUi(style);
    };

    window.pickAccent = function (color) {
        const input = document.getElementById('accentColor');
        if (input) input.value = color;
        if (window.updateAccentSetting) {
            window.updateAccentSetting(color);
        }
        applyUiPreferences();
        syncAccentUi(color);
    };

    window.resetUiPrefs = function () {
        ['user_density','user_accent','reduce_motion','user_font_size','user_theme','dashboard_view','default_test_mode','external_new_tab','welcome_banner_style'].forEach(n => {
            const secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `${n}=; path=/; max-age=0; SameSite=Lax${secure}`;
            try { safeStorage.removeItem(n); } catch (error) {}
        });
        safeStorage.removeItem('notify_new_tests');
        safeStorage.removeItem('ui_sounds');
        showNotice('Preferencje zresetowane.', 'secondary');
        location.reload();
    };

    window.checkPasswordStrength = function (val) {
        const bar = document.getElementById('pwdStrengthBar');
        const fill = document.getElementById('pwdStrengthFill');
        const reqLen = document.getElementById('reqLen');
        const reqUpper = document.getElementById('reqUpper');
        const reqNum = document.getElementById('reqNum');

        if (!val) {
            if (bar) bar.style.display = 'none';
            return;
        }
        if (bar) bar.style.display = 'flex';

        let score = 0;
        const hasLen = val.length >= 8;
        const hasUpper = /[A-Z]/.test(val);
        const hasNum = /[0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val);

        if (hasLen) score += 40;
        if (hasUpper) score += 30;
        if (hasNum) score += 30;

        if (reqLen) {
            reqLen.className = hasLen ? 'text-success small' : 'text-muted small';
            reqLen.innerHTML = (hasLen ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-circle me-1"></i>') + 'Min. 8 znaków';
        }
        if (reqUpper) {
            reqUpper.className = hasUpper ? 'text-success small' : 'text-muted small';
            reqUpper.innerHTML = (hasUpper ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-circle me-1"></i>') + 'Wielka litera';
        }
        if (reqNum) {
            reqNum.className = hasNum ? 'text-success small' : 'text-muted small';
            reqNum.innerHTML = (hasNum ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-circle me-1"></i>') + 'Cyfra lub znak specjalny';
        }

        if (fill) {
            fill.style.width = score + '%';
            if (score < 40) {
                fill.className = 'progress-bar bg-danger';
            } else if (score < 80) {
                fill.className = 'progress-bar bg-warning';
            } else {
                fill.className = 'progress-bar bg-success';
            }
        }
        window.checkPasswordMatch();
    };

    window.checkPasswordMatch = function () {
        const p1 = document.getElementById('newPasswordInput')?.value || '';
        const p2 = document.getElementById('confirmPasswordInput')?.value || '';
        const reqMatch = document.getElementById('reqMatch');
        if (!reqMatch || !p2) return;
        const matches = p1 !== '' && p1 === p2;
        reqMatch.className = matches ? 'text-success small' : 'text-danger small';
        reqMatch.innerHTML = (matches ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-x-circle-fill me-1"></i>') + (matches ? 'Hasła są identyczne' : 'Hasła nie są identyczne');
    };

    // Passkey WebAuthn Handlers
    window.registerPasskey = async function () {
        if (!window.PublicKeyCredential) {
            showNotice('Twoja przeglądarka nie obsługuje kluczy Passkey.', 'danger');
            return;
        }

        try {
            const passkeyUrl = (window.location.pathname.includes('/user/') ? '../' : '') + 'ajax/passkey_register.php';
            const generateRes = await fetch(passkeyUrl + '?action=generate');
            const generateData = await generateRes.json();

            if (generateData.status !== 'success') {
                throw new Error(generateData.message || 'Błąd generowania żądania.');
            }

            const publicKey = generateData.options.publicKey;
            const parseBin = window.parseWebAuthnBinary || function (s) { return s; };
            const bufToBase64 = window.bufferToBase64 || function (b) { return b; };

            if (publicKey.challenge) publicKey.challenge = parseBin(publicKey.challenge);
            if (publicKey.user && publicKey.user.id) publicKey.user.id = parseBin(publicKey.user.id);
            if (publicKey.excludeCredentials) {
                for (let cred of publicKey.excludeCredentials) {
                    cred.id = parseBin(cred.id);
                }
            }

            const credential = await navigator.credentials.create({ publicKey: publicKey });

            const formData = new FormData();
            formData.append('action', 'verify');
            formData.append('clientDataJSON', bufToBase64(credential.response.clientDataJSON));
            formData.append('attestationObject', bufToBase64(credential.response.attestationObject));

            let deviceName = (window.appPrompt ? await appPrompt('Podaj krótką nazwę dla tego urządzenia:', 'Moje urządzenie') : 'Moje urządzenie');
            if (!deviceName) deviceName = 'Moje urządzenie';
            formData.append('deviceName', deviceName);

            const verifyRes = await fetch(passkeyUrl, {
                method: 'POST',
                body: formData
            });
            const verifyData = await verifyRes.json();

            if (verifyData.status === 'success') {
                showNotice('Klucz Passkey został pomyślnie dodany!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(verifyData.message || 'Błąd podczas weryfikacji.');
            }
        } catch (err) {
            console.error(err);
            if (err.name === 'InvalidStateError') {
                showNotice('Ten klucz Passkey (lub urządzenie) został już zarejestrowany dla tego konta (np. zsynchronizowany przez Google/Apple).', 'warning');
            } else if (err.name === 'NotAllowedError') {
                showNotice('Rejestracja klucza Passkey została anulowana.', 'info');
            } else if (err.name === 'SecurityError') {
                showNotice('Wymagane jest bezpieczne połączenie (HTTPS lub zarejestrowana domena).', 'danger');
            } else {
                showNotice('Proces rejestracji klucza nie powiódł się: ' + err.message, 'danger');
            }
        }
    };

    window.deletePasskey = async function (id) {
        try {
            const passkeyUrl = (window.location.pathname.includes('/user/') ? '../' : '') + 'ajax/passkey_register.php';
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            const res = await fetch(passkeyUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                showNotice('Klucz usunięty.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            showNotice('Wystąpił błąd podczas usuwania: ' + err.message, 'danger');
        }
    };

    function syncPreferenceControls() {
        const font = readPreference('user_font_size', '16');
        const fontIds = { '14': 'fontSmall', '16': 'fontMedium', '18': 'fontLarge' };
        const fontInput = document.getElementById(fontIds[font] || 'fontMedium');
        if (fontInput) fontInput.checked = true;

        const theme = readPreference('user_theme', 'light');
        const themeSelect = document.getElementById('themeSelect');
        if (themeSelect) themeSelect.value = ['light', 'dark'].includes(theme) ? theme : 'light';

        const density = readPreference('user_density', 'comfortable');
        const densitySelect = document.getElementById('densitySelect');
        if (densitySelect) densitySelect.value = density === 'compact' ? 'compact' : 'comfortable';

        const accent = readPreference('user_accent', '#3b82f6');
        const accentInput = document.getElementById('accentColor');
        if (accentInput && /^#[0-9a-fA-F]{6}$/.test(accent)) accentInput.value = accent;

        const dashboard = readPreference('dashboard_view', 'balanced');
        const dashboardSelect = document.getElementById('dashboardView');
        if (dashboardSelect && ['balanced', 'learning', 'compact'].includes(dashboard)) dashboardSelect.value = dashboard;

        const defaultMode = readPreference('default_test_mode', 'exam');
        const defaultModeSelect = document.getElementById('defaultTestMode');
        if (defaultModeSelect && ['exam', 'practice', 'single'].includes(defaultMode)) defaultModeSelect.value = defaultMode;

        const welcomeStyle = readPreference('welcome_banner_style', 'gradient');
        const welcomeStyleSelect = document.getElementById('welcomeBannerStyleSelect');
        if (welcomeStyleSelect && ['gradient', 'pure', 'aurora', 'glass'].includes(welcomeStyle)) welcomeStyleSelect.value = welcomeStyle;
        syncWelcomeBannerStyleUi(welcomeStyle);
        syncAccentUi(accent);

        const motion = document.getElementById('motionSwitch');
        if (motion) motion.checked = readPreference('reduce_motion', '0') === '1';
        const external = document.getElementById('externalTabSwitch');
        if (external) external.checked = readPreference('external_new_tab', '1') === '1';
    }

    document.addEventListener('DOMContentLoaded', () => {
        syncSettingsMiniCards();
        syncSettingsOverviewCards();
        syncPreferenceControls();

        const notify = document.getElementById('notifySwitch');
        const sounds = document.getElementById('soundsSwitch');
        if (notify) notify.checked = safeStorage.getItem('notify_new_tests', '0') === '1';
        if (sounds) sounds.checked = safeStorage.getItem('ui_sounds', '0') === '1';
        applyUiPreferences();

        document.querySelectorAll('#dashboardView, #defaultTestMode, #themeSelect, #densitySelect, #notifySwitch, #soundsSwitch, #externalTabSwitch, #motionSwitch, #welcomeBannerStyleSelect').forEach((el) => {
            el.addEventListener('change', () => setTimeout(() => {
                applyUiPreferences();
                syncSettingsMiniCards();
                syncSettingsOverviewCards();
                const accent = readPreference('user_accent', '#3b82f6');
                syncAccentUi(accent);
                const welcomeStyle = readPreference('welcome_banner_style', 'gradient');
                syncWelcomeBannerStyleUi(welcomeStyle);
            }, 40));
        });

        // Tab restore
        try {
            const activeTab = safeStorage.getItem('active_settings_tab', '') || window.location.hash;
            if (activeTab && activeTab !== '#') {
                const tabTrigger = document.querySelector(`#settings-tabs [data-bs-toggle="pill"][href="${activeTab}"]`) || document.querySelector(`#settings-tabs [data-bs-toggle="pill"][data-bs-target="${activeTab}"]`);
                if (tabTrigger) {
                    document.querySelectorAll('#settings-tabs [data-bs-toggle="pill"]').forEach(link => {
                        link.classList.remove('active');
                        link.setAttribute('aria-selected', 'false');
                    });
                    document.querySelectorAll('#settings-tab-content > .tab-pane').forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    tabTrigger.classList.add('active');
                    tabTrigger.setAttribute('aria-selected', 'true');
                    const targetId = tabTrigger.getAttribute('href') || tabTrigger.getAttribute('data-bs-target');
                    if (targetId) {
                        const targetPane = document.querySelector(targetId);
                        if (targetPane) targetPane.classList.add('show', 'active');
                    }
                }
            }
        } catch (err) {
            console.error('Failed to restore active tab:', err);
        }

        // Tab click handler
        document.querySelectorAll('[data-bs-toggle="pill"], [data-bs-toggle="tab"]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const isMainTab = this.closest('#settings-tabs');
                const target = this.getAttribute('href') || this.getAttribute('data-bs-target');

                if (isMainTab) {
                    document.querySelectorAll('#settings-tabs [data-bs-toggle="pill"]').forEach(link => {
                        link.classList.remove('active');
                        link.setAttribute('aria-selected', 'false');
                    });
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');

                    if (target) {
                        document.querySelectorAll('#settings-tab-content > .tab-pane').forEach(pane => {
                            pane.classList.remove('show', 'active');
                        });
                        try {
                            const pane = document.querySelector(target);
                            if (pane) {
                                pane.classList.add('show', 'active');
                                if (window.innerWidth < 768) {
                                    const y = pane.getBoundingClientRect().top + window.scrollY - 80;
                                    window.scrollTo(0, y);
                                }
                            }
                        } catch (selectorErr) {
                            console.error('Invalid selector for pane:', selectorErr);
                        }
                        safeStorage.setItem('active_settings_tab', target);
                        try {
                            history.replaceState(null, null, target);
                        } catch (historyErr) {
                            console.error('Failed to replace history state:', historyErr);
                        }
                    }
                } else {
                    const navContainer = this.closest('.nav') || this.parentElement;
                    if (navContainer) {
                        navContainer.querySelectorAll('[data-bs-toggle="pill"], [data-bs-toggle="tab"]').forEach(link => {
                            link.classList.remove('active');
                            link.setAttribute('aria-selected', 'false');
                        });
                    }
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');

                    if (target) {
                        const targetPane = document.querySelector(target);
                        if (targetPane) {
                            const parentContainer = targetPane.parentElement;
                            if (parentContainer) {
                                parentContainer.querySelectorAll(':scope > .tab-pane').forEach(pane => {
                                    pane.classList.remove('show', 'active');
                                });
                            }
                            targetPane.classList.add('show', 'active');
                        }
                    }
                }
            });
        });

        // AJAX settings forms submissions
        document.querySelectorAll('#settings-tab-content form').forEach(form => {
            if (form.id === 'deleteAvatarForm' || form.action.includes('revoke_session') || form.action.includes('reset_progress') || form.action.includes('delete_account')) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Zapisywanie...';
                }

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        if (form.action.includes('update_profile.php')) {
                            const usernameInput = form.querySelector('input[name="username"]');
                            if (usernameInput) {
                                const newUsername = usernameInput.value;
                                document.querySelectorAll('.user-profile-name').forEach(el => {
                                    if (el.childNodes[0]) el.childNodes[0].textContent = newUsername + ' ';
                                });
                            }
                            showNotice('Dane podstawowe zostały zaktualizowane.', 'success');
                            const avatarInput = form.querySelector('#avatarFileInput');
                            if (avatarInput && avatarInput.files && avatarInput.files.length > 0) {
                                setTimeout(() => location.reload(), 600);
                            }
                        } else if (form.action.includes('update_privacy.php')) {
                            showNotice('Ustawienia prywatności zostały zaktualizowane.', 'success');
                        } else if (form.action.includes('change_password.php')) {
                            showNotice('Hasło zostało pomyślnie zmienione.', 'success');
                            form.reset();
                        } else {
                            showNotice('Zapisano pomyślnie.', 'success');
                        }
                    } else {
                        showNotice('Wystąpił błąd podczas zapisu.', 'danger');
                    }
                } catch (err) {
                    showNotice('Błąd połączenia. Spróbuj ponownie.', 'danger');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        });
    });

})(window, document);
