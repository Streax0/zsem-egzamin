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
        const intervalMs = Math.max(1500, parseInt(list.dataset.pollInterval || '2000', 10) || 2000);
        let pollTimer = null;
        let inFlight = false;

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
                            window.alert(data.message || 'Nie udało się przetworzyć wyzwania.');
                            button.disabled = false;
                            return;
                        }
                        if (data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        refreshNotifications();
                    } catch (error) {
                        window.alert('Błąd połączenia. Spróbuj ponownie.');
                        button.disabled = false;
                    }
                });
            });
        }

        async function refreshNotifications() {
            if (!feedUrl || inFlight) return;
            inFlight = true;
            try {
                const url = new URL(resolveUrl(feedUrl));
                url.searchParams.set('base', baseUrl);
                url.searchParams.set('limit', '5');
                url.searchParams.set('_', String(Date.now()));

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data.success) return;
                list.innerHTML = data.html || '';
                updateBadge(Number(data.unread_count || 0));
                bindDuelActions();
            } catch (error) {
                // ignore background polling errors
            } finally {
                inFlight = false;
            }
        }

        function startPolling() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = window.setInterval(refreshNotifications, intervalMs);
        }

        bindDuelActions();
        refreshNotifications();
        startPolling();

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = null;
                return;
            }
            refreshNotifications();
            startPolling();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotificationsPoll);
    } else {
        initNotificationsPoll();
    }
})();
