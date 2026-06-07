/**
 * app api client - shared frontend/backend communication layer
 */
(function () {
    if (window.AppApi) return;

    const DEFAULT_TIMEOUT = 15000;
    const TIMEOUT_MESSAGE = 'Serwer odpowiada zbyt długo. Spróbuj ponownie.';

    function requestId() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return 'req-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    async function parseJson(response) {
        const text = await response.text();
        if (!text) return {};
        try {
            return JSON.parse(text);
        } catch (error) {
            const wrapped = new Error('Nieprawidłowa odpowiedź serwera.');
            wrapped.cause = error;
            wrapped.status = response.status;
            throw wrapped;
        }
    }

    function normalizeResponseShape(data, response) {
        if (!data || typeof data !== 'object') {
            data = {};
        }
        const hasSuccess = Object.prototype.hasOwnProperty.call(data, 'success');
        const hasOk = Object.prototype.hasOwnProperty.call(data, 'ok');
        if (!hasSuccess && hasOk) {
            data.success = Boolean(data.ok);
        } else if (!hasSuccess) {
            data.success = Boolean(response.ok);
        }
        if (!hasOk) {
            data.ok = Boolean(data.success);
        }
        if (!data.error && data.message && !data.success) {
            data.error = data.message;
        }
        if (!data.message && data.error) {
            data.message = data.error;
        }
        return data;
    }

    async function postForm(url, formData, options = {}) {
        const id = requestId();
        const timeout = Number(options.timeout || DEFAULT_TIMEOUT);
        const controller = window.AbortController ? new AbortController() : null;
        const timer = controller ? window.setTimeout(() => controller.abort(), timeout) : null;

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller?.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Client-Request-ID': id
                }
            });
            const data = normalizeResponseShape(await parseJson(response), response);
            data.client_request_id = id;
            data.http_status = response.status;
            if (!response.ok && !data.error) {
                data.error = response.status === 429
                    ? 'Zbyt wiele akcji naraz. Spróbuj za chwilę.'
                    : 'Serwer zwrócił błąd.';
            }
            return data;
        } catch (error) {
            if (error.name === 'AbortError') {
                return {
                    success: false,
                    ok: false,
                    error: TIMEOUT_MESSAGE,
                    message: TIMEOUT_MESSAGE,
                    client_request_id: id,
                    timed_out: true
                };
            }
            throw error;
        } finally {
            if (timer) window.clearTimeout(timer);
        }
    }

    async function getJson(url, options = {}) {
        const id = requestId();
        const timeout = Number(options.timeout || DEFAULT_TIMEOUT);
        const controller = window.AbortController ? new AbortController() : null;
        const timer = controller ? window.setTimeout(() => controller.abort(), timeout) : null;

        try {
            const response = await fetch(url, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin',
                signal: controller?.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Client-Request-ID': id
                }
            });
            const data = normalizeResponseShape(await parseJson(response), response);
            data.client_request_id = id;
            data.http_status = response.status;
            if (!response.ok && !data.error) {
                data.error = 'Serwer zwrócił błąd.';
            }
            return data;
        } catch (error) {
            if (error.name === 'AbortError') {
                return {
                    success: false,
                    ok: false,
                    error: TIMEOUT_MESSAGE,
                    message: TIMEOUT_MESSAGE,
                    client_request_id: id,
                    timed_out: true
                };
            }
            throw error;
        } finally {
            if (timer) window.clearTimeout(timer);
        }
    }

    function urlEncoded(data) {
        const body = new URLSearchParams();
        Object.entries(data || {}).forEach(([key, value]) => body.append(key, value == null ? '' : String(value)));
        return body;
    }

    window.AppApi = Object.freeze({
        getJson,
        postForm,
        urlEncoded
    });
})();
