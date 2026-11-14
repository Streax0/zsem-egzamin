/**
 * ZSEM Tech - Core Utility Functions (Modernized ES2022)
 */
(function (window) {
    'use strict';

    /**
     * Safely escapes HTML special characters (including quotes) to prevent XSS.
     * @param {*} value
     * @returns {string}
     */
    function escapeHtml(value) {
        if (value == null) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Creates a debounced function that delays invoking fn until after delay milliseconds.
     * @param {Function} fn
     * @param {number} delay
     * @returns {Function}
     */
    function debounce(fn, delay = 250) {
        let timer = null;
        const debounced = function (...args) {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => {
                timer = null;
                fn.apply(this, args);
            }, delay);
        };
        debounced.cancel = () => {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        };
        return debounced;
    }

    /**
     * Creates a throttled function that only invokes fn at most once per every limit milliseconds.
     * @param {Function} fn
     * @param {number} limit
     * @returns {Function}
     */
    function throttle(fn, limit = 250) {
        let inThrottle = false;
        let lastArgs = null;
        let lastContext = null;
        return function (...args) {
            if (!inThrottle) {
                fn.apply(this, args);
                inThrottle = true;
                setTimeout(() => {
                    inThrottle = false;
                    if (lastArgs) {
                        fn.apply(lastContext, lastArgs);
                        lastArgs = null;
                        lastContext = null;
                    }
                }, limit);
            } else {
                lastArgs = args;
                lastContext = this;
            }
        };
    }

    /**
     * Safely parses JSON without throwing an uncaught exception.
     * @param {string} value
     * @param {*} fallback
     * @returns {*}
     */
    function safeJson(value, fallback = null) {
        if (!value || typeof value !== 'string') return fallback;
        try {
            return JSON.parse(value);
        } catch (_) {
            return fallback;
        }
    }

    /**
     * Copies text to user clipboard using modern Clipboard API with fallback.
     * @param {string} text
     * @returns {Promise<boolean>}
     */
    async function copyToClipboard(text) {
        if (!text) return false;
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(String(text));
                return true;
            }
        } catch (_) {}

        // Fallback for older contexts
        try {
            const ta = document.createElement('textarea');
            ta.value = String(text);
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            ta.style.pointerEvents = 'none';
            document.body.appendChild(ta);
            ta.select();
            const success = document.execCommand('copy');
            ta.remove();
            return success;
        } catch (_) {
            return false;
        }
    }

    /**
     * Formats bytes into human-readable string (B, KB, MB, GB).
     * @param {number} bytes
     * @param {number} decimals
     * @returns {string}
     */
    function formatBytes(bytes, decimals = 1) {
        if (!+bytes) return '0 B';
        const k = 1024;
        const dm = Math.max(0, decimals);
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    }

    /**
     * Concise querySelector helper.
     * @param {string} selector
     * @param {ParentNode} scope
     * @returns {Element|null}
     */
    const qs = (selector, scope = document) => scope?.querySelector(selector) ?? null;

    /**
     * Concise querySelectorAll helper returning native Array.
     * @param {string} selector
     * @param {ParentNode} scope
     * @returns {Element[]}
     */
    const qsa = (selector, scope = document) => Array.from(scope?.querySelectorAll(selector) ?? []);

    /**
     * Event delegation utility.
     * @param {Element|Document} target
     * @param {string} eventType
     * @param {string} selector
     * @param {Function} handler
     */
    function delegate(target, eventType, selector, handler) {
        if (!target) return;
        target.addEventListener(eventType, (e) => {
            const match = e.target?.closest?.(selector);
            if (match && target.contains(match)) {
                handler.call(match, e, match);
            }
        });
    }

    const AppUtils = Object.freeze({
        escapeHtml,
        debounce,
        throttle,
        safeJson,
        copyToClipboard,
        formatBytes,
        qs,
        qsa,
        delegate
    });

    window.AppUtils = AppUtils;
    window.escapeHtml = escapeHtml;
})(window);
