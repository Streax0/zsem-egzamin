(function () {
    function initNotificationsPoll() {
        const root = document.getElementById('notificationsDropdownRoot');
        const list = document.getElementById('notificationList');
        if (!root || !list) {
            return;
        }

        const feedUrl = root.dataset.feedUrl || '';
        const respondUrl = root.dataset.respondUrl || '';
        const baseUrl = root.dataset.baseUrl || '';
        const csrfToken = root.dataset.csrf || '';
        const badge = document.getElementById('notificationBadge');
        const markReadForm = document.getElementById('notificationMarkReadForm');
        const baseIntervalMs = Math.max(8000, parseInt(list.dataset.pollInterval || '12000', 10) || 12000);
        let pollTimer = null;
        let inFlight = false;
        let failureCount = 0;
        let previousUnreadCount = badge && !badge.classList.contains('d-none')
            ? parseInt((badge.textContent || '0').replace(/\D+/g, ''), 10) || 0
            : 0;

        function resolveUrl(raw) {
            if (!raw) return '';
            if (/^https?:\/\//i.test(raw)) return raw;
            try {
                return new URL(raw, window.location.href).href;
            } catch (error) {
                return raw;
            }
        }

        function updateBadge(count) {
            if (!badge) return;
            if (typeof window.zsemNotifyUnreadCountChanged === 'function') {
                window.zsemNotifyUnreadCountChanged(Number(count || 0), previousUnreadCount);
            }
            previousUnreadCount = Number(count || 0);
            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : String(count);
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
            if (markReadForm) {
                markReadForm.classList.toggle('d-none', count <= 0);
            }
        }

        function bindDuelActions() {
            list.querySelectorAll('[data-duel-action]').forEach((button) => {
                if (button.dataset.bound === '1') return;
                button.dataset.bound = '1';
                button.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const duelId = button.getAttribute('data-duel-id');
                    const action = button.getAttribute('data-duel-action');
                    if (!duelId || !action) return;

                    button.disabled = true;
                    try {
                        const body = new URLSearchParams();
                        body.set('csrf_token', csrfToken);
                        body.set('duel_id', duelId);
                        body.set('action', action);

                        const response = await fetch(resolveUrl(respondUrl), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: body.toString(),
                        });
                        const data = await response.json();
                        if (!data.success) {
                            window.appNotice?.(data.message || 'Nie udało się przetworzyć wyzwania.', 'danger');
                            button.disabled = false;
                            return;
                        }
                        if (data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        refreshNotifications();
                    } catch (error) {
                        window.appNotice?.('Błąd połączenia. Spróbuj ponownie.', 'danger');
                        button.disabled = false;
                    }
                });
            });
        }

        function bindAppStatusActions(scope) {
            const rootScope = scope || document;
            rootScope.querySelectorAll('[data-app-status-open]').forEach((button) => {
                if (button.dataset.boundStatus === '1') return;
                button.dataset.boundStatus = '1';
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const modalEl = document.getElementById('appStatusModal');
                    if (!modalEl || !window.bootstrap?.Modal) return;
                    if (modalEl.parentElement !== document.body) {
                        document.body.appendChild(modalEl);
                    }

                    const titleEl = document.getElementById('appStatusModalLabel');
                    const bodyEl = document.getElementById('appStatusModalBody');
                    const metaEl = document.getElementById('appStatusModalMeta');
                    const levelEl = document.getElementById('appStatusModalLevel');
                    const level = button.dataset.statusLevel || 'info';

                    if (titleEl) titleEl.textContent = button.dataset.statusTitle || 'Status';
                    if (bodyEl) bodyEl.textContent = button.dataset.statusBody || '';
                    if (metaEl) {
                        const date = button.dataset.statusDate || '';
                        const moderator = button.dataset.statusModerator || '';
                        metaEl.textContent = [date, moderator].filter(Boolean).join(' · ');
                    }
                    if (levelEl) {
                        levelEl.textContent = level;
                        levelEl.className = 'badge rounded-pill mb-2 text-bg-' + (['success', 'warning', 'danger', 'info'].includes(level) ? level : 'info');
                    }

                    window.bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, focus: true }).show();
                });
            });
        }

        function pollDelay() {
            return baseIntervalMs * Math.min(6, 1 + failureCount);
        }

        async function refreshNotifications(options = {}) {
            const refreshOnOpen = options.refreshOnOpen === true;
            if (!feedUrl || inFlight || (document.hidden && !refreshOnOpen)) return;
            inFlight = true;
            try {
                const url = new URL(resolveUrl(feedUrl));
                url.searchParams.set('base', baseUrl);
                url.searchParams.set('limit', refreshOnOpen ? '10' : '3');
                url.searchParams.set('_', String(Date.now()));

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    failureCount++;
                    return;
                }
                const data = await response.json();
                if (!data.success) {
                    failureCount++;
                    return;
                }
                failureCount = 0;
                list.innerHTML = data.html || '';
                updateBadge(Number(data.unread_count || 0));
                bindDuelActions();
                bindAppStatusActions(list);
            } catch (error) {
                failureCount++;
            } finally {
                inFlight = false;
            }
        }

        function startPolling() {
            if (pollTimer) clearTimeout(pollTimer);
            if (document.hidden) {
                pollTimer = null;
                return;
            }
            pollTimer = window.setTimeout(async () => {
                await refreshNotifications();
                startPolling();
            }, pollDelay());
        }

        bindDuelActions();
        bindAppStatusActions(document);
        refreshNotifications({ refreshOnOpen: false });
        startPolling();

        root.addEventListener('show.bs.dropdown', () => {
            refreshNotifications({ refreshOnOpen: true });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (pollTimer) clearTimeout(pollTimer);
                pollTimer = null;
                return;
            }
            refreshNotifications({ refreshOnOpen: false });
            startPolling();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotificationsPoll);
    } else {
        initNotificationsPoll();
    }
})();
