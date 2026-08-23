/**
 * DevTools Protection Guard - ZSEM Tech
 *
 * Blocks Developer Tools, inspect shortcuts, view-source, and right-click context menu for regular users.
 * Automatically bypassed for authenticated administrators (admin / dyrektor).
 */
(function() {
    'use strict';

    if (window.__zsemDevToolsGuardLoaded) {
        return;
    }
    window.__zsemDevToolsGuardLoaded = true;

    // Check if DevTools is explicitly allowed for this session (Admin)
    function isDevToolsAllowed() {
        if (window.__ZSEM_DEVTOOLS_ENABLED === true) {
            return true;
        }
        const meta = document.querySelector('meta[name="devtools-policy"]');
        if (meta && meta.getAttribute('content') === 'allow') {
            return true;
        }
        if (document.documentElement && document.documentElement.getAttribute('data-devtools-policy') === 'allow') {
            return true;
        }
        return false;
    }

    if (isDevToolsAllowed()) {
        return;
    }

    let devtoolsOpen = false;
    let overlayElement = null;

    // 1. Prevent Right-Click / Context Menu
    function handleContextMenu(e) {
        if (isDevToolsAllowed()) return;
        e.preventDefault();
        e.stopPropagation();
        return false;
    }

    window.addEventListener('contextmenu', handleContextMenu, true);
    document.addEventListener('contextmenu', handleContextMenu, true);

    // Prevent Middle / Right click auxiliary triggers
    window.addEventListener('auxclick', function(e) {
        if (isDevToolsAllowed()) return;
        if (e.button === 2) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);

    // 2. Prevent Keyboard Shortcuts for DevTools & Source viewing
    function handleKeydown(e) {
        if (isDevToolsAllowed()) return;

        const key = e.key ? e.key.toLowerCase() : '';
        const keyCode = e.keyCode || e.which;
        const ctrl = e.ctrlKey || e.metaKey; // Windows Ctrl or Mac Cmd
        const shift = e.shiftKey;
        const alt = e.altKey;

        // F12 key
        if (key === 'f12' || keyCode === 123) {
            return blockDevToolsEvent(e);
        }

        // Ctrl + Shift + I (Inspect)
        // Ctrl + Shift + J (Console)
        // Ctrl + Shift + C (Element selector)
        // Ctrl + Shift + K (Firefox Console)
        // Ctrl + Shift + E (Firefox Network)
        // Ctrl + Shift + S (Debugger / Screenshot)
        if (ctrl && shift && ['i', 'j', 'c', 'k', 'e', 's'].includes(key)) {
            return blockDevToolsEvent(e);
        }
        if (ctrl && shift && [73, 74, 67, 75, 69, 83].includes(keyCode)) {
            return blockDevToolsEvent(e);
        }

        // Cmd + Option + I / J / C / U (Mac DevTools)
        if (ctrl && alt && ['i', 'j', 'c', 'u'].includes(key)) {
            return blockDevToolsEvent(e);
        }

        // Ctrl + U (View Source)
        if (ctrl && !shift && !alt && (key === 'u' || keyCode === 85)) {
            return blockDevToolsEvent(e);
        }

        // Ctrl + S (Save page)
        if (ctrl && !shift && !alt && (key === 's' || keyCode === 83)) {
            return blockDevToolsEvent(e);
        }

        // Shift + F7 (Firefox Style Editor)
        if (shift && (key === 'f7' || keyCode === 118)) {
            return blockDevToolsEvent(e);
        }
    }

    function blockDevToolsEvent(e) {
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        return false;
    }

    window.addEventListener('keydown', handleKeydown, true);
    document.addEventListener('keydown', handleKeydown, true);

    // 3. Neutralize Console for regular users
    function neutralizeConsole() {
        try {
            if (!window.console) return;
            const noop = function() {};
            const methods = [
                'log', 'debug', 'info', 'warn', 'error', 'table', 'trace',
                'dir', 'dirxml', 'group', 'groupCollapsed', 'groupEnd',
                'time', 'timeEnd', 'timeLog', 'count', 'countReset',
                'assert', 'profile', 'profileEnd'
            ];
            methods.forEach(function(method) {
                if (typeof window.console[method] === 'function') {
                    window.console[method] = noop;
                }
            });
        } catch (err) {}
    }

    neutralizeConsole();

    // Periodically clear console
    setInterval(function() {
        if (!isDevToolsAllowed() && window.console && typeof window.console.clear === 'function') {
            try {
                window.console.clear();
            } catch (e) {}
        }
    }, 2500);

    // 4. Warning Overlay Management
    function ensureOverlay() {
        if (overlayElement && document.body && document.body.contains(overlayElement)) {
            return overlayElement;
        }

        overlayElement = document.getElementById('zsem-devtools-guard-overlay');
        if (overlayElement) {
            return overlayElement;
        }

        overlayElement = document.createElement('div');
        overlayElement.id = 'zsem-devtools-guard-overlay';
        overlayElement.setAttribute('role', 'alertdialog');
        overlayElement.setAttribute('aria-modal', 'true');
        overlayElement.setAttribute('aria-label', 'Narzędzia deweloperskie zablokowane');
        overlayElement.style.cssText = [
            'position: fixed !important',
            'top: 0 !important',
            'left: 0 !important',
            'width: 100vw !important',
            'height: 100vh !important',
            'background: rgba(15, 23, 42, 0.96) !important',
            'backdrop-filter: blur(16px) !important',
            '-webkit-backdrop-filter: blur(16px) !important',
            'z-index: 2147483647 !important',
            'display: none',
            'align-items: center !important',
            'justify-content: center !important',
            'padding: 1.5rem !important',
            'box-sizing: border-box !important',
            'color: #ffffff !important',
            'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important',
            'text-align: center !important'
        ].join(';');

        overlayElement.innerHTML = `
            <div style="max-width: 480px; width: 100%; background: rgba(30, 41, 59, 0.85); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 24px; padding: 2.5rem 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); backdrop-filter: blur(8px);">
                <div style="width: 72px; height: 72px; margin: 0 auto 1.5rem; border-radius: 20px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); display: flex; align-items: center; justify-content: center;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h3 style="font-size: 1.4rem; font-weight: 800; margin: 0 0 0.75rem; color: #ffffff; letter-spacing: -0.02em;">DevTools jest zablokowany</h3>
                <p style="font-size: 0.95rem; color: #94a3b8; line-height: 1.6; margin: 0 0 1.5rem;">
                    Narzędzia deweloperskie i podgląd kodu źródłowego zostały wyłączone dla użytkowników platformy ZSEM Tech.
                </p>
                <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: 14px; padding: 0.85rem 1rem; font-size: 0.85rem; color: #cbd5e1; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span>Zamknij narzędzia deweloperskie, aby odblokować ekran.</span>
                </div>
            </div>
        `;

        if (document.body) {
            document.body.appendChild(overlayElement);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                if (!document.getElementById('zsem-devtools-guard-overlay')) {
                    document.body.appendChild(overlayElement);
                }
            });
        }

        return overlayElement;
    }

    function showDevToolsWarning() {
        if (isDevToolsAllowed()) return;
        devtoolsOpen = true;
        const overlay = ensureOverlay();
        if (overlay) {
            overlay.style.display = 'flex';
        }
        // If on an exam page, report violation if possible
        if (window.ExamEngine && typeof window.ExamEngine.reportViolation === 'function') {
            try {
                const sessionId = new URLSearchParams(window.location.search).get('session');
                const participantId = window.__examParticipantId || 0;
                const questionId = document.querySelector('input[name="question_id"]')?.value || 0;
                if (sessionId) {
                    window.ExamEngine.reportViolation('devtools_open', sessionId, participantId, questionId);
                }
            } catch (err) {}
        }
    }

    function hideDevToolsWarning() {
        if (!devtoolsOpen) return;
        devtoolsOpen = false;
        const overlay = ensureOverlay();
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    // 5. Active DevTools Detection via Debugger timing & Dimensions
    function detectDevTools() {
        if (isDevToolsAllowed()) {
            hideDevToolsWarning();
            return;
        }

        let isDetected = false;

        // Check A: Dimensions check (for docked DevTools)
        const threshold = 160;
        const widthDiff = (window.outerWidth - window.innerWidth) > threshold;
        const heightDiff = (window.outerHeight - window.innerHeight) > threshold;
        if (widthDiff || heightDiff) {
            isDetected = true;
        }

        // Check B: Debugger timing check
        const t0 = performance.now();
        (function() {
            try {
                Function('debugger')();
            } catch (e) {}
        })();
        const t1 = performance.now();
        if ((t1 - t0) > 100) {
            isDetected = true;
        }

        if (isDetected) {
            showDevToolsWarning();
        } else {
            hideDevToolsWarning();
        }
    }

    // Run active detection check
    setInterval(detectDevTools, 1000);
    window.addEventListener('resize', detectDevTools, { passive: true });

    // Initial check after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', detectDevTools);
    } else {
        detectDevTools();
    }
})();
