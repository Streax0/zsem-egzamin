(function () {
    const W = 1200;
    const H = 720;
    const SCALE = 2;

    let lastShareCanvas = null;

    function roundRect(ctx, x, y, width, height, radius) {
        const r = Math.min(radius, width / 2, height / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + width, y, x + width, y + height, r);
        ctx.arcTo(x + width, y + height, x, y + height, r);
        ctx.arcTo(x, y + height, x, y, r);
        ctx.arcTo(x, y, x + width, y, r);
        ctx.closePath();
    }

    function truncate(ctx, text, maxWidth) {
        let value = String(text || '—');
        if (ctx.measureText(value).width <= maxWidth) return value;
        while (value.length > 1 && ctx.measureText(value + '…').width > maxWidth) {
            value = value.slice(0, -1);
        }
        return value + '…';
    }

    function wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
        const words = String(text || '').split(/\s+/);
        let line = '';
        const lines = [];
        for (const word of words) {
            const test = line ? `${line} ${word}` : word;
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = test;
            }
        }
        if (line) lines.push(line);
        const clipped = maxLines ? lines.slice(0, maxLines) : lines;
        if (maxLines && lines.length > maxLines) {
            clipped[maxLines - 1] = clipped[maxLines - 1].replace(/\s+\S*$/, '') + '…';
        }
        clipped.forEach((ln, idx) => ctx.fillText(ln, x, y + idx * lineHeight));
        return clipped.length;
    }

    function fitFont(ctx, text, weight, size, family, maxWidth, minSize) {
        let current = size;
        do {
            ctx.font = `${weight} ${current}px ${family}`;
            if (ctx.measureText(String(text || '')).width <= maxWidth) break;
            current -= 2;
        } while (current >= minSize);
        return current;
    }

    function drawMortarboard(ctx, x, y, size) {
        ctx.save();
        ctx.translate(x, y);
        const s = size / 48;
        ctx.scale(s, s);
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.moveTo(24, 8);
        ctx.lineTo(4, 18);
        ctx.lineTo(24, 28);
        ctx.lineTo(44, 18);
        ctx.closePath();
        ctx.fill();
        ctx.fillRect(22, 28, 4, 12);
        ctx.beginPath();
        ctx.arc(24, 40, 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function drawInfoTile(ctx, x, y, w, h, label, value, accent) {
        roundRect(ctx, x, y, w, h, 18);
        ctx.fillStyle = 'rgba(255,255,255,0.06)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.12)';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        roundRect(ctx, x + 14, y + 14, 4, h - 28, 2);
        ctx.fillStyle = accent || '#60a5fa';
        ctx.fill();

        ctx.fillStyle = 'rgba(148,163,184,0.95)';
        ctx.font = '700 11px Inter, "Segoe UI", sans-serif';
        ctx.fillText(label.toUpperCase(), x + 28, y + 32);

        ctx.fillStyle = '#f8fafc';
        fitFont(ctx, value, '800', 23, 'Inter, "Segoe UI", sans-serif', w - 48, 15);
        ctx.fillText(truncate(ctx, value, w - 48), x + 28, y + 62);
    }

    function drawScoreRing(ctx, cx, cy, radius, percent, accent) {
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255,255,255,0.1)';
        ctx.lineWidth = 14;
        ctx.stroke();

        const start = -Math.PI / 2;
        const end = start + (Math.PI * 2 * (percent / 100));
        ctx.beginPath();
        ctx.arc(cx, cy, radius, start, end);
        const ringGrad = ctx.createLinearGradient(cx - radius, cy - radius, cx + radius, cy + radius);
        ringGrad.addColorStop(0, accent);
        ringGrad.addColorStop(1, '#ffffff');
        ctx.strokeStyle = ringGrad;
        ctx.lineWidth = 14;
        ctx.lineCap = 'round';
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(cx, cy, radius - 28, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(15,23,42,0.55)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.14)';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.textAlign = 'center';
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 78px Inter, "Segoe UI", sans-serif';
        ctx.fillText(`${percent}%`, cx, cy + 18);
        ctx.font = '800 13px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,0.72)';
        ctx.fillText('WYNIK', cx, cy + 48);
        ctx.textAlign = 'left';
    }

    function drawShareCard(data) {
        const canvas = document.createElement('canvas');
        canvas.width = W * SCALE;
        canvas.height = H * SCALE;
        const ctx = canvas.getContext('2d');
        ctx.scale(SCALE, SCALE);

        const passed = !!data.passed;
        const isHarvest = !!data.isHarvest;
        const accent = isHarvest ? '#fb923c' : (passed ? '#34d399' : '#f87171');
        const accentDeep = isHarvest ? '#ea580c' : (passed ? '#059669' : '#dc2626');
        const modeName = data.modeName || 'Egzamin';

        const bg = ctx.createLinearGradient(0, 0, W, H);
        bg.addColorStop(0, '#0b1220');
        bg.addColorStop(0.45, '#111827');
        bg.addColorStop(1, '#1e1b4b');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        const orb1 = ctx.createRadialGradient(180, 120, 0, 180, 120, 320);
        orb1.addColorStop(0, passed ? 'rgba(16,185,129,0.28)' : 'rgba(239,68,68,0.24)');
        orb1.addColorStop(1, 'rgba(16,185,129,0)');
        ctx.fillStyle = orb1;
        ctx.fillRect(0, 0, W, H);

        const orb2 = ctx.createRadialGradient(W - 120, H, 0, W - 120, H, 420);
        orb2.addColorStop(0, 'rgba(37,99,235,0.35)');
        orb2.addColorStop(1, 'rgba(37,99,235,0)');
        ctx.fillStyle = orb2;
        ctx.fillRect(0, 0, W, H);

        roundRect(ctx, 28, 28, W - 56, H - 56, 28);
        ctx.strokeStyle = 'rgba(255,255,255,0.1)';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        const headerH = 92;
        const headerGrad = ctx.createLinearGradient(28, 28, W - 28, 28 + headerH);
        headerGrad.addColorStop(0, isHarvest ? '#b91c1c' : '#1d4ed8');
        headerGrad.addColorStop(1, isHarvest ? '#7c2d12' : '#312e81');
        roundRect(ctx, 28, 28, W - 56, headerH, 28);
        ctx.save();
        ctx.clip();
        ctx.fillStyle = headerGrad;
        ctx.fillRect(28, 28, W - 56, headerH);
        ctx.restore();

        drawMortarboard(ctx, 62, 52, 42);
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 34px Inter, "Segoe UI", sans-serif';
        ctx.fillText(String(data.brand || 'ZSEM TECH'), 112, 68);
        ctx.font = '600 14px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,0.82)';
        ctx.fillText(String(data.brandSub || 'Platforma egzaminacyjna'), 112, 92);

        ctx.textAlign = 'right';
        ctx.fillStyle = 'rgba(255,255,255,0.72)';
        ctx.font = '700 13px Inter, "Segoe UI", sans-serif';
        ctx.fillText(String(data.brandUrl || 'zsem-egzamin.online'), W - 56, 68);
        ctx.font = '600 12px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,0.55)';
        ctx.fillText(isHarvest ? 'Karta wyniku Harvest' : 'Karta wyniku testu', W - 56, 88);
        ctx.textAlign = 'left';

        ctx.fillStyle = accent;
        const passText = isHarvest ? 'HARVEST' : String(data.passLabel || '');
        ctx.font = '800 13px Inter, "Segoe UI", sans-serif';
        const passW = Math.max(140, Math.min(220, ctx.measureText(passText).width + 46));
        roundRect(ctx, 56, 136, passW, 34, 17);
        ctx.fill();
        ctx.fillStyle = '#0f172a';
        ctx.textAlign = 'center';
        ctx.fillText(passText, 56 + passW / 2, 158);
        ctx.textAlign = 'left';

        const firstName = String(data.firstName || '—');
        const lastName = String(data.lastName || '—');
        const fullName = String(data.fullName || `${firstName} ${lastName}`.trim());
        ctx.fillStyle = '#ffffff';
        fitFont(ctx, fullName, '900', 46, 'Inter, "Segoe UI", sans-serif', 600, 30);
        ctx.fillText(truncate(ctx, fullName, 600), 56, 228);

        ctx.fillStyle = 'rgba(226,232,240,0.88)';
        ctx.font = '700 18px Inter, "Segoe UI", sans-serif';
        const metaLine = `@${data.nickname || '—'}  ·  Klasa ${data.className || '—'}`;
        ctx.fillText(truncate(ctx, metaLine, 620), 56, 262);

        ctx.fillStyle = accent;
        ctx.font = '800 28px Inter, "Segoe UI", sans-serif';
        ctx.fillText(truncate(ctx, isHarvest ? 'Harvest ukończony' : String(data.performanceLabel || 'Wynik testu'), 610), 56, 308);

        ctx.fillStyle = 'rgba(203,213,225,0.88)';
        ctx.font = '500 17px Inter, "Segoe UI", sans-serif';
        wrapText(ctx, data.subtitle || '', 56, 342, 620, 24, 2);

        const tileW = 250;
        const tileH = 84;
        const gap = 14;
        const startX = 56;
        const row1Y = 418;
        const row2Y = row1Y + tileH + gap;
        drawInfoTile(ctx, startX, row1Y, tileW, tileH, 'Tryb', modeName, '#60a5fa');
        drawInfoTile(ctx, startX + tileW + gap, row1Y, tileW, tileH, 'Poprawne', `${data.correctAnswers} / ${data.totalQuestions}`, '#34d399');
        drawInfoTile(ctx, startX + (tileW + gap) * 2, row1Y, tileW, tileH, 'Czas trwania', data.timeSpent, '#a78bfa');
        drawInfoTile(ctx, startX, row2Y, tileW, tileH, 'Data wykonania', data.testDate, '#38bdf8');
        drawInfoTile(ctx, startX + tileW + gap, row2Y, tileW, tileH, 'Nick', `@${data.nickname || '—'}`, '#fbbf24');
        drawInfoTile(ctx, startX + (tileW + gap) * 2, row2Y, tileW, tileH, 'Klasa', data.className || '—', '#22d3ee');

        drawScoreRing(ctx, W - 175, 290, 104, data.scorePercent || 0, accent);

        ctx.fillStyle = 'rgba(148,163,184,0.75)';
        ctx.font = '700 12px Inter, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Wygenerowano w ZSEM TECH · Platforma egzaminacyjna ZSEM', W / 2, H - 42);
        ctx.font = '600 11px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(148,163,184,0.55)';
        ctx.fillText('zsem.edu.pl · zsem-egzamin.online', W / 2, H - 24);
        ctx.textAlign = 'left';

        return canvas;
    }

    async function ensureFonts() {
        if (!document.fonts?.load) return;
        await Promise.all([
            document.fonts.load('500 17px Inter'),
            document.fonts.load('600 12px Inter'),
            document.fonts.load('700 13px Inter'),
            document.fonts.load('800 24px Inter'),
            document.fonts.load('900 46px Inter'),
            document.fonts.load('900 78px Inter'),
        ]).catch(() => {});
    }

    async function buildShareCanvas(data) {
        await ensureFonts();
        lastShareCanvas = drawShareCard(data);
        return lastShareCanvas;
    }

    function paintPreview(canvas) {
        const preview = document.getElementById('resultSharePreviewCanvas');
        if (!preview || !canvas) return;
        preview.width = canvas.width;
        preview.height = canvas.height;
        const pctx = preview.getContext('2d');
        pctx.clearRect(0, 0, preview.width, preview.height);
        pctx.drawImage(canvas, 0, 0);
    }

    async function downloadShareImage(data) {
        const canvas = lastShareCanvas || await buildShareCanvas(data);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png', 1));
        if (!blob) throw new Error('Nie udało się wygenerować obrazu.');
        const nick = String(data.nickname || 'wynik').replace(/[^\w.-]+/g, '_');
        const datePart = String(data.testDate || '').replace(/[^\d]/g, '').slice(0, 8) || 'karta';
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `zsemtech-wynik-${nick}-${datePart}.png`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function initResultShareCard() {
        const data = window.resultShareCardData;
        const modalEl = document.getElementById('resultShareModal');
        const downloadBtn = document.getElementById('downloadResultShareBtn');
        if (!data || !modalEl) return;

        modalEl.addEventListener('show.bs.modal', async function () {
            if (downloadBtn) {
                downloadBtn.disabled = true;
                downloadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generowanie…';
            }
            try {
                const canvas = await buildShareCanvas(data);
                paintPreview(canvas);
            } catch (err) {
                console.error(err);
            } finally {
                if (downloadBtn) {
                    downloadBtn.disabled = false;
                    downloadBtn.innerHTML = '<i class="bi bi-download me-2"></i>Pobierz zdjęcie';
                }
            }
        });

        if (downloadBtn) {
            downloadBtn.addEventListener('click', async function () {
                const original = downloadBtn.innerHTML;
                downloadBtn.disabled = true;
                downloadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Pobieranie…';
                try {
                    await downloadShareImage(data);
                } catch (err) {
                    console.error(err);
                    window.appNotice?.('Nie udało się pobrać zdjęcia.', 'danger');
                } finally {
                    downloadBtn.disabled = false;
                    downloadBtn.innerHTML = original;
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initResultShareCard);
    } else {
        initResultShareCard();
    }
})();
