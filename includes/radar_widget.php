<?php
/**
 * Reusable Knowledge Radar Widget
 *
 * Include this file in any page to display an interactive SVG spider/radar chart
 * showing per-category learning mastery. Requires the user to be logged in.
 *
 * Usage:
 *   <?php include 'includes/radar_widget.php'; ?>
 */
if (!isset($_SESSION['user_id'])) return;
?>
<div class="radar-widget" id="radarWidget" aria-label="Mapa wiedzy - wykres radarowy">
    <div class="radar-widget-header">
        <div>
            <h3 class="radar-widget-title">
                <i class="bi bi-diagram-3-fill me-2 text-primary"></i>Mapa Wiedzy
            </h3>
            <p class="radar-widget-subtitle">Twoja dokładność według kategorii egzaminacyjnych CKE</p>
        </div>
        <div class="radar-widget-actions">
            <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" id="radarPracticeWeak" style="display:none">
                <i class="bi bi-lightning-charge me-1"></i>Ćwicz słabe strony
            </button>
        </div>
    </div>

    <div class="radar-widget-body">
        <div class="radar-chart-container" id="radarChartContainer">
            <svg id="radarSvg" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"
                 role="img" aria-label="Wykres radarowy wiedzy" style="width:100%;max-width:300px;height:auto">
                <title>Mapa wiedzy - wykres radarowy</title>
                <defs>
                    <radialGradient id="radarGrad" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="rgba(99,102,241,0.25)"/>
                        <stop offset="100%" stop-color="rgba(99,102,241,0.05)"/>
                    </radialGradient>
                </defs>
                <!-- Grid rings will be injected by JS -->
                <g id="radarGrid"></g>
                <!-- Data polygon -->
                <polygon id="radarPolygon" fill="url(#radarGrad)" stroke="#6366f1" stroke-width="2" opacity="0.9"/>
                <!-- Axis labels -->
                <g id="radarLabels"></g>
                <!-- Data points -->
                <g id="radarPoints"></g>
                <!-- Loading spinner -->
                <text x="150" y="155" text-anchor="middle" fill="#94a3b8" font-size="12" id="radarLoading">Ładowanie...</text>
            </svg>
        </div>

        <div class="radar-legend" id="radarLegend">
            <!-- Category legend injected by JS -->
        </div>
    </div>

    <div class="radar-weak-banner" id="radarWeakBanner" style="display:none">
        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
        <span id="radarWeakText"></span>
        <a href="#" id="radarWeakLink" class="btn btn-sm btn-warning rounded-pill ms-2 fw-bold">Zacznij ćwiczyć</a>
    </div>
</div>

<style>
.radar-widget {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0,0,0,.04);
}
.radar-widget-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.radar-widget-title { font-size: 1.05rem; font-weight: 700; margin-bottom: .15rem; }
.radar-widget-subtitle { font-size: .78rem; color: var(--text-muted, #64748b); margin: 0; }
.radar-widget-body {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}
.radar-chart-container { flex: 0 0 auto; }
.radar-legend {
    flex: 1;
    min-width: 140px;
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.radar-legend-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .8rem;
}
.radar-legend-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.radar-legend-label { font-weight: 600; flex: 1; }
.radar-legend-pct {
    font-weight: 700;
    font-size: .85rem;
    min-width: 36px;
    text-align: right;
}
.radar-weak-banner {
    margin-top: 1rem;
    padding: .75rem 1rem;
    background: rgba(234,179,8,.08);
    border-radius: 12px;
    border: 1px solid rgba(234,179,8,.25);
    font-size: .82rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
}
</style>

<script>
(function () {
    'use strict';

    const CATEGORIES  = ['Sieci','Systemy','Sprzęt','Bezpieczeństwo','Kable/Normy','Adresacja'];
    const N           = CATEGORIES.length;
    const CX = 150, CY = 150, R = 110;
    const COLORS      = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];

    function polarToXY(angle, radius) {
        const rad = (angle - 90) * Math.PI / 180;
        return { x: CX + radius * Math.cos(rad), y: CY + radius * Math.sin(rad) };
    }

    function buildGrid() {
        const g = document.getElementById('radarGrid');
        if (!g) return;
        // Rings
        [20,40,60,80,100].forEach(pct => {
            const r = R * pct / 100;
            const pts = CATEGORIES.map((_, i) => {
                const p = polarToXY(i * (360/N), r);
                return `${p.x},${p.y}`;
            }).join(' ');
            const poly = document.createElementNS('http://www.w3.org/2000/svg','polygon');
            poly.setAttribute('points', pts);
            poly.setAttribute('fill', 'none');
            poly.setAttribute('stroke', 'rgba(148,163,184,.25)');
            poly.setAttribute('stroke-width', '1');
            g.appendChild(poly);
        });
        // Axes
        CATEGORIES.forEach((_, i) => {
            const p = polarToXY(i * (360/N), R);
            const line = document.createElementNS('http://www.w3.org/2000/svg','line');
            line.setAttribute('x1', CX); line.setAttribute('y1', CY);
            line.setAttribute('x2', p.x); line.setAttribute('y2', p.y);
            line.setAttribute('stroke', 'rgba(148,163,184,.3)');
            line.setAttribute('stroke-width', '1');
            g.appendChild(line);
        });
    }

    function renderData(values, weakAreas) {
        const poly   = document.getElementById('radarPolygon');
        const labels = document.getElementById('radarLabels');
        const points = document.getElementById('radarPoints');
        const legend = document.getElementById('radarLegend');
        const loading = document.getElementById('radarLoading');

        if (loading) loading.style.display = 'none';

        const pts = values.map((v, i) => {
            const r = R * Math.min(v, 100) / 100;
            return polarToXY(i * (360/N), r);
        });

        if (poly) poly.setAttribute('points', pts.map(p => `${p.x},${p.y}`).join(' '));

        // Labels
        if (labels) {
            labels.innerHTML = '';
            CATEGORIES.forEach((cat, i) => {
                const lp  = polarToXY(i * (360/N), R + 22);
                const txt = document.createElementNS('http://www.w3.org/2000/svg','text');
                txt.setAttribute('x', lp.x);
                txt.setAttribute('y', lp.y);
                txt.setAttribute('text-anchor', 'middle');
                txt.setAttribute('dominant-baseline', 'middle');
                txt.setAttribute('font-size', '9.5');
                txt.setAttribute('font-weight', '600');
                txt.setAttribute('fill', weakAreas.includes(cat) ? '#ef4444' : 'var(--text-color,#1e293b)');
                txt.textContent = cat.length > 10 ? cat.substring(0,9)+'…' : cat;
                labels.appendChild(txt);
            });
        }

        // Data points
        if (points) {
            points.innerHTML = '';
            pts.forEach((p, i) => {
                const circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
                circle.setAttribute('cx', p.x);
                circle.setAttribute('cy', p.y);
                circle.setAttribute('r', '5');
                circle.setAttribute('fill', COLORS[i % COLORS.length]);
                circle.setAttribute('stroke', '#fff');
                circle.setAttribute('stroke-width', '2');

                const title = document.createElementNS('http://www.w3.org/2000/svg','title');
                title.textContent = `${CATEGORIES[i]}: ${values[i]}%`;
                circle.appendChild(title);
                points.appendChild(circle);
            });
        }

        // Legend
        if (legend) {
            legend.innerHTML = '';
            CATEGORIES.forEach((cat, i) => {
                const pct  = values[i];
                const item = document.createElement('div');
                item.className = 'radar-legend-item';
                const colorClass = pct >= 80 ? '#16a34a' : pct >= 60 ? COLORS[i] : '#ef4444';
                item.innerHTML = `
                    <span class="radar-legend-dot" style="background:${colorClass}"></span>
                    <span class="radar-legend-label">${cat}</span>
                    <span class="radar-legend-pct" style="color:${colorClass}">${pct}%</span>`;
                legend.appendChild(item);
            });
        }

        // Weak banner
        const banner  = document.getElementById('radarWeakBanner');
        const weakBtn = document.getElementById('radarPracticeWeak');
        const weakTxt = document.getElementById('radarWeakText');
        const weakLnk = document.getElementById('radarWeakLink');

        if (weakAreas.length > 0) {
            const params = encodeURIComponent(weakAreas.join(','));
            const href   = `test.php?mode=practice&weak=1&categories=${params}`;
            if (banner)  { banner.style.display  = 'flex'; }
            if (weakBtn) { weakBtn.style.display  = ''; weakBtn.onclick = () => location.href = href; }
            if (weakTxt) weakTxt.textContent = `Słabe obszary: ${weakAreas.join(', ')} — poniżej 60% dokładności.`;
            if (weakLnk) weakLnk.href = href;
        }
    }

    async function loadRadarData() {
        try {
            const base = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : '';
            const res  = await fetch(base + 'api/radar_data.php', { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'API error');
            buildGrid();
            renderData(data.values, data.weak_areas || []);
        } catch (err) {
            const loading = document.getElementById('radarLoading');
            if (loading) loading.textContent = 'Błąd ładowania danych';
            console.warn('Radar load error:', err);
        }
    }

    document.addEventListener('DOMContentLoaded', loadRadarData);
}());
</script>
