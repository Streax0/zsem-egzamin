/**
 * Hint Drawer — Progressive 3-tier hint system
 *
 * Usage: Include this script on any page with quiz questions.
 * Requires: window.currentQuestionId to be set to the active question's DB id.
 *
 * Injects a floating hint drawer that slides up from the bottom.
 * Tiers: 💡 Wskazówka / ✂️ Eliminacja / 📖 Wyjaśnienie
 */
(function () {
    'use strict';

    const XP_COSTS = { 1: 2, 2: 5, 3: 10 };
    const usedTiers = new Set();
    let currentXp   = null; // set by server via window.userXp if available

    // ── DOM Build ──────────────────────────────────────────────────────────────
    function buildDrawer() {
        if (document.getElementById('hintDrawer')) return;

        const overlay = document.createElement('div');
        overlay.id = 'hintOverlay';
        overlay.style.cssText = `
            position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1049;
            display:none;opacity:0;transition:opacity .25s`;
        overlay.addEventListener('click', closeDrawer);

        const drawer = document.createElement('div');
        drawer.id = 'hintDrawer';
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');
        drawer.setAttribute('aria-label', 'Panel wskazówek');
        drawer.style.cssText = `
            position:fixed;bottom:0;left:0;right:0;z-index:1050;
            background:var(--card-bg,#fff);border-radius:20px 20px 0 0;
            padding:1.5rem;box-shadow:0 -8px 40px rgba(0,0,0,.15);
            transform:translateY(100%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);
            max-width:640px;margin:0 auto;`;
        drawer.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                <h3 style="font-size:1rem;font-weight:700;margin:0">
                    <i class="bi bi-lightbulb-fill me-2" style="color:#f59e0b"></i>Wskazówki
                </h3>
                <button id="hintClose" aria-label="Zamknij" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;line-height:1">&times;</button>
            </div>

            <div style="display:flex;gap:.6rem;margin-bottom:1.25rem;flex-wrap:wrap">
                ${[1,2,3].map(t => `
                <button class="hint-tier-btn" data-tier="${t}" id="hintBtn${t}"
                    style="flex:1;min-width:90px;padding:.55rem .7rem;border-radius:12px;
                           border:2px solid rgba(99,102,241,.25);background:rgba(99,102,241,.06);
                           cursor:pointer;font-size:.78rem;font-weight:700;color:#4f46e5;
                           transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:.2rem">
                    <span>${['💡','✂️','📖'][t-1]}</span>
                    <span>${['Wskazówka','Eliminacja','Wyjaśnienie'][t-1]}</span>
                    <span style="font-size:.7rem;font-weight:600;opacity:.75">−${XP_COSTS[t]} XP</span>
                </button>`).join('')}
            </div>

            <div id="hintContent" style="min-height:60px;padding:1rem;background:rgba(99,102,241,.05);
                border-radius:12px;font-size:.88rem;line-height:1.6;color:var(--text-color,#1e293b)">
                <span style="color:#94a3b8">Wybierz poziom wskazówki powyżej.</span>
            </div>

            <div id="hintXpBar" style="margin-top:.75rem;font-size:.75rem;color:#64748b;text-align:right;display:none">
                <i class="bi bi-star-fill me-1" style="color:#f59e0b"></i>
                Twoje XP po użyciu: <strong id="hintXpAfter"></strong>
            </div>`;

        document.body.appendChild(overlay);
        document.body.appendChild(drawer);

        drawer.querySelector('#hintClose').addEventListener('click', closeDrawer);
        drawer.addEventListener('click', e => e.stopPropagation());

        // Tier button clicks
        drawer.querySelectorAll('.hint-tier-btn').forEach(btn => {
            btn.addEventListener('click', () => requestHint(parseInt(btn.dataset.tier, 10)));
        });
    }

    // ── Open / Close ───────────────────────────────────────────────────────────
    function openDrawer() {
        buildDrawer();
        usedTiers.clear();
        document.getElementById('hintContent').innerHTML =
            '<span style="color:#94a3b8">Wybierz poziom wskazówki powyżej.</span>';
        document.getElementById('hintXpBar').style.display = 'none';
        updateBtnStates();

        const overlay = document.getElementById('hintOverlay');
        const drawer  = document.getElementById('hintDrawer');
        overlay.style.display = 'block';
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
            drawer.style.transform = 'translateY(0)';
        });
    }

    function closeDrawer() {
        const overlay = document.getElementById('hintOverlay');
        const drawer  = document.getElementById('hintDrawer');
        if (!overlay || !drawer) return;
        overlay.style.opacity = '0';
        drawer.style.transform = 'translateY(100%)';
        setTimeout(() => { overlay.style.display = 'none'; }, 300);
    }

    // ── Hint Request ───────────────────────────────────────────────────────────
    async function requestHint(tier) {
        const qId = window.currentQuestionId;
        if (!qId) {
            showHintContent('<span style="color:#ef4444">Brak aktywnego pytania.</span>', tier);
            return;
        }

        if (usedTiers.has(tier)) {
            // Already fetched — just re-display from cache
            const cached = document.getElementById('hintDrawer').__cache?.[tier];
            if (cached) { showHintContent(cached, tier); }
            return;
        }

        const btn = document.getElementById(`hintBtn${tier}`);
        if (btn) btn.style.opacity = '.5';
        showHintContent('<span style="color:#94a3b8"><i class="bi bi-hourglass-split me-1"></i>Ładowanie...</span>', tier);

        try {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content
                || window.csrfToken
                || '';
            const fd = new FormData();
            fd.append('question_id', qId);
            fd.append('tier', tier);
            if (csrfToken) {
                fd.append('csrf_token', csrfToken);
            }
            const res = await fetch('actions/get_hint.php', {
                method: 'POST',
                headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (!data.success) {
                showHintContent(`<span style="color:#ef4444">${escHtml(data.error || 'Błąd')}</span>`, tier);
                if (btn) btn.style.opacity = '1';
                return;
            }

            // Build hint HTML
            let html = '';
            if (tier === 2 && data.eliminated?.length) {
                html = `<p style="margin:0 0 .5rem"><strong>Eliminuję odpowiedzi:</strong></p>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                        ${data.eliminated.map(l => `<span style="padding:.25rem .75rem;background:#ef4444;color:#fff;
                            border-radius:8px;font-weight:700;text-decoration:line-through">${escHtml(l)}</span>`).join('')}
                    </div>`;
            } else {
                html = `<p style="margin:0;white-space:pre-line">${escHtml(data.hint || '')}</p>`;
            }

            // Cache
            const drawer = document.getElementById('hintDrawer');
            if (drawer) {
                if (!drawer.__cache) drawer.__cache = {};
                drawer.__cache[tier] = html;
            }

            showHintContent(html, tier);
            usedTiers.add(tier);
            updateBtnStates();

            // Show XP bar
            if (data.new_xp != null) {
                currentXp = data.new_xp;
                document.getElementById('hintXpAfter').textContent = data.new_xp + ' XP';
                document.getElementById('hintXpBar').style.display = 'block';
                // Update page XP if element exists
                const xpEl = document.querySelector('[data-user-xp]');
                if (xpEl) xpEl.textContent = data.new_xp;
            }

        } catch (err) {
            showHintContent('<span style="color:#ef4444">Błąd połączenia.</span>', tier);
            console.warn('Hint fetch error:', err);
        } finally {
            if (btn) btn.style.opacity = '1';
        }
    }

    function showHintContent(html, tier) {
        const content = document.getElementById('hintContent');
        if (!content) return;
        const tierLabels = { 1: '💡 Wskazówka', 2: '✂️ Eliminacja', 3: '📖 Wyjaśnienie' };
        content.innerHTML = `
            <div style="font-size:.7rem;font-weight:700;color:#6366f1;margin-bottom:.5rem;text-transform:uppercase;
                letter-spacing:.04em">${tierLabels[tier] || ''}</div>
            ${html}`;
    }

    function updateBtnStates() {
        [1, 2, 3].forEach(t => {
            const btn = document.getElementById(`hintBtn${t}`);
            if (!btn) return;
            if (usedTiers.has(t)) {
                btn.style.background     = 'rgba(99,102,241,.15)';
                btn.style.borderColor    = 'rgba(99,102,241,.5)';
                btn.style.opacity        = '.7';
                btn.title = 'Już użyto';
            }
        });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;');
    }

    // ── Trigger Button Injection ───────────────────────────────────────────────
    function injectHintTrigger() {
        if (document.getElementById('hintTriggerBtn')) return;

        // Look for a sensible anchor: question answer area or controls
        const anchor = document.querySelector(
            '.question-actions, .answer-controls, .quiz-controls, .test-controls, #questionControls, .question-footer'
        );

        const btn = document.createElement('button');
        btn.id          = 'hintTriggerBtn';
        btn.type        = 'button';
        btn.title       = 'Pokaż wskazówki';
        btn.setAttribute('aria-label', 'Otwórz panel wskazówek');
        btn.innerHTML   = '<i class="bi bi-lightbulb-fill me-1"></i>Wskazówki';
        btn.style.cssText = `
            padding:.45rem 1rem;border-radius:10px;border:2px solid rgba(245,158,11,.4);
            background:rgba(245,158,11,.08);color:#b45309;font-weight:700;font-size:.82rem;
            cursor:pointer;transition:all .2s`;
        btn.addEventListener('mouseenter', () => {
            btn.style.background = 'rgba(245,158,11,.18)';
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.background = 'rgba(245,158,11,.08)';
        });
        btn.addEventListener('click', openDrawer);

        if (anchor) {
            anchor.appendChild(btn);
        } else {
            // Floating button as fallback
            btn.style.cssText += ';position:fixed;bottom:5rem;right:1.25rem;z-index:900;box-shadow:0 4px 16px rgba(0,0,0,.12)';
            document.body.appendChild(btn);
        }
    }

    // ── Init ───────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        buildDrawer();
        injectHintTrigger();
    });

    // Public API
    window.HintDrawer = { open: openDrawer, close: closeDrawer };
}());
