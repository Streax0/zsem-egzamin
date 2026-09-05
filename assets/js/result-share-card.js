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

    // Vector Icon Drawing Utilities (Resolution-independent, razor sharp at 2x)
    function drawMortarboard(ctx, x, y, size) {
        ctx.save();
        ctx.translate(x, y);
        const s = size / 48;
        ctx.scale(s, s);

        // Academic cap top rhomboid
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.moveTo(24, 8);
        ctx.lineTo(44, 17);
        ctx.lineTo(24, 26);
        ctx.lineTo(4, 17);
        ctx.closePath();
        ctx.fill();

        // Skullcap base
        ctx.fillStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.moveTo(11, 21);
        ctx.lineTo(11, 29);
        ctx.quadraticCurveTo(24, 38, 37, 29);
        ctx.lineTo(37, 21);
        ctx.quadraticCurveTo(24, 28, 11, 21);
        ctx.closePath();
        ctx.fill();

        // Tassel button and cord
        ctx.fillStyle = '#93c5fd';
        ctx.beginPath();
        ctx.arc(24, 17, 2.5, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = '#93c5fd';
        ctx.lineWidth = 1.8;
        ctx.beginPath();
        ctx.moveTo(24, 17);
        ctx.quadraticCurveTo(39, 19, 41, 29);
        ctx.stroke();

        // Tassel brush
        ctx.fillStyle = '#93c5fd';
        ctx.beginPath();
        ctx.arc(41, 31, 2.8, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function drawVectorIcon(ctx, iconType, cx, cy, color) {
        ctx.save();
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        if (iconType === 'mode') {
            // Stacked test exam sheet
            ctx.beginPath();
            roundRect(ctx, cx - 9, cy - 10, 18, 15, 3);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx - 5, cy - 4);
            ctx.lineTo(cx + 5, cy - 4);
            ctx.moveTo(cx - 5, cy);
            ctx.lineTo(cx + 2, cy);
            ctx.stroke();
            // Under sheet underline
            ctx.beginPath();
            ctx.moveTo(cx - 7, cy + 9);
            ctx.lineTo(cx + 9, cy + 9);
            ctx.stroke();
        } else if (iconType === 'check') {
            // Target badge with checkmark
            ctx.beginPath();
            ctx.arc(cx, cy, 11, 0, Math.PI * 2);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx - 5, cy);
            ctx.lineTo(cx - 1, cy + 4);
            ctx.lineTo(cx + 6, cy - 4);
            ctx.stroke();
        } else if (iconType === 'clock') {
            // Stopwatch / Clock
            ctx.beginPath();
            ctx.arc(cx, cy + 1, 10, 0, Math.PI * 2);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx, cy + 1);
            ctx.lineTo(cx, cy - 4);
            ctx.moveTo(cx, cy + 1);
            ctx.lineTo(cx + 4, cy + 1);
            ctx.stroke();
            // Top crown
            ctx.beginPath();
            ctx.moveTo(cx - 3, cy - 11);
            ctx.lineTo(cx + 3, cy - 11);
            ctx.stroke();
        } else if (iconType === 'calendar') {
            // Calendar
            ctx.beginPath();
            roundRect(ctx, cx - 10, cy - 8, 20, 18, 3);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx - 10, cy - 2);
            ctx.lineTo(cx + 10, cy - 2);
            ctx.stroke();
            // Spiral pins
            ctx.beginPath();
            ctx.moveTo(cx - 5, cy - 11);
            ctx.lineTo(cx - 5, cy - 7);
            ctx.moveTo(cx + 5, cy - 11);
            ctx.lineTo(cx + 5, cy - 7);
            ctx.stroke();
            // Calendar grid dots
            ctx.fillRect(cx - 6, cy + 2, 3, 3);
            ctx.fillRect(cx + 3, cy + 2, 3, 3);
            ctx.fillRect(cx - 6, cy + 6, 3, 3);
            ctx.fillRect(cx + 3, cy + 6, 3, 3);
        } else if (iconType === 'user') {
            // User avatar badge
            ctx.beginPath();
            ctx.arc(cx, cy - 4, 4.5, 0, Math.PI * 2);
            ctx.stroke();
            ctx.beginPath();
            ctx.arc(cx, cy + 9, 8.5, Math.PI * 1.15, Math.PI * 1.85);
            ctx.stroke();
        } else if (iconType === 'class') {
            // Academic graduation cap
            ctx.beginPath();
            ctx.moveTo(cx, cy - 6);
            ctx.lineTo(cx + 9, cy - 2);
            ctx.lineTo(cx, cy + 3);
            ctx.lineTo(cx - 9, cy - 2);
            ctx.closePath();
            ctx.stroke();
            ctx.beginPath();
            ctx.arc(cx, cy + 3, 5.5, 0.2, Math.PI - 0.2);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx + 6, cy);
            ctx.lineTo(cx + 8, cy + 7);
            ctx.stroke();
        }
        ctx.restore();
    }

    function drawInfoTile(ctx, x, y, w, h, label, value, accent, iconType) {
        // Tile container with premium frosted glass and gradient stroke
        roundRect(ctx, x, y, w, h, 20);
        const tileGrad = ctx.createLinearGradient(x, y, x + w, y + h);
        tileGrad.addColorStop(0, 'rgba(255, 255, 255, 0.055)');
        tileGrad.addColorStop(1, 'rgba(255, 255, 255, 0.018)');
        ctx.fillStyle = tileGrad;
        ctx.fill();

        // Specular top-left highlight border
        const strokeGrad = ctx.createLinearGradient(x, y, x + w, y + h);
        strokeGrad.addColorStop(0, 'rgba(255, 255, 255, 0.18)');
        strokeGrad.addColorStop(0.5, 'rgba(255, 255, 255, 0.07)');
        strokeGrad.addColorStop(1, 'rgba(255, 255, 255, 0.03)');
        ctx.strokeStyle = strokeGrad;
        ctx.lineWidth = 1.2;
        ctx.stroke();

        // Icon badge container (46x46, rounded 13px)
        const bx = x + 14;
        const by = y + 18;
        const bSize = 46;
        roundRect(ctx, bx, by, bSize, bSize, 13);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.04)';
        ctx.fill();
        ctx.strokeStyle = `${accent}55`;
        ctx.lineWidth = 1;
        ctx.stroke();

        // Ambient glow behind icon
        const iconGlow = ctx.createRadialGradient(bx + bSize / 2, by + bSize / 2, 0, bx + bSize / 2, by + bSize / 2, 22);
        iconGlow.addColorStop(0, `${accent}33`);
        iconGlow.addColorStop(1, 'transparent');
        ctx.fillStyle = iconGlow;
        ctx.fill();

        // Draw the vector icon
        drawVectorIcon(ctx, iconType, bx + bSize / 2, by + bSize / 2, accent);

        // Typography beside badge
        const textX = x + 72;
        ctx.fillStyle = 'rgba(148, 163, 184, 0.95)';
        ctx.font = '800 11px Inter, "Segoe UI", sans-serif';
        ctx.fillText(label.toUpperCase(), textX, y + 33);

        ctx.fillStyle = '#ffffff';
        fitFont(ctx, value, '800', 21, 'Inter, "Segoe UI", sans-serif', w - 84, 15);
        ctx.fillText(truncate(ctx, value, w - 84), textX, y + 59);
    }

    function drawScoreRing(ctx, cx, cy, radius, percent, accent) {
        // 1. Ambient localized glow cushion
        const ringSpotlight = ctx.createRadialGradient(cx, cy, radius * 0.4, cx, cy, radius * 1.8);
        ringSpotlight.addColorStop(0, `${accent}30`);
        ringSpotlight.addColorStop(0.6, `${accent}0a`);
        ringSpotlight.addColorStop(1, 'transparent');
        ctx.fillStyle = ringSpotlight;
        ctx.beginPath();
        ctx.arc(cx, cy, radius * 1.8, 0, Math.PI * 2);
        ctx.fill();

        // 2. Precision dial tick marks (36 ticks around circumference)
        ctx.save();
        ctx.translate(cx, cy);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.12)';
        ctx.lineWidth = 1.5;
        for (let i = 0; i < 36; i++) {
            const angle = (i * Math.PI * 2) / 36;
            const r1 = radius + 15;
            const r2 = (i % 3 === 0) ? radius + 21 : radius + 18;
            ctx.beginPath();
            ctx.moveTo(Math.cos(angle) * r1, Math.sin(angle) * r1);
            ctx.lineTo(Math.cos(angle) * r2, Math.sin(angle) * r2);
            ctx.stroke();
        }
        ctx.restore();

        // 3. Dark track ring (full circumference)
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
        ctx.lineWidth = 14;
        ctx.stroke();

        // 4. Progress Arc
        const start = -Math.PI / 2;
        const rawSweep = (Math.PI * 2 * (Math.max(0, Math.min(100, percent)) / 100));
        const sweep = Math.max(0.04, rawSweep);
        const end = start + sweep;

        if (percent > 0) {
            ctx.beginPath();
            ctx.arc(cx, cy, radius, start, end);
            const ringGrad = ctx.createLinearGradient(cx - radius, cy - radius, cx + radius, cy + radius);
            ringGrad.addColorStop(0, accent);
            ringGrad.addColorStop(0.5, '#ffffff');
            ringGrad.addColorStop(1, accent);
            ctx.strokeStyle = ringGrad;
            ctx.lineWidth = 14;
            ctx.lineCap = 'round';
            ctx.stroke();

            // 5. Glowing end indicator pip
            const endX = cx + Math.cos(end) * radius;
            const endY = cy + Math.sin(end) * radius;

            ctx.beginPath();
            ctx.arc(endX, endY, 10, 0, Math.PI * 2);
            ctx.fillStyle = `${accent}66`;
            ctx.fill();

            ctx.beginPath();
            ctx.arc(endX, endY, 4.5, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
        }

        // 6. Gauge Inner Center Glass Dial
        const dialR = radius - 24;
        roundRect(ctx, cx - dialR, cy - dialR, dialR * 2, dialR * 2, dialR);
        const dialGrad = ctx.createLinearGradient(cx, cy - dialR, cx, cy + dialR);
        dialGrad.addColorStop(0, 'rgba(30, 41, 59, 0.7)');
        dialGrad.addColorStop(1, 'rgba(10, 15, 29, 0.95)');
        ctx.fillStyle = dialGrad;
        ctx.fill();

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.14)';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // 7. Numeric percentage score
        ctx.textAlign = 'center';
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 66px Inter, "Segoe UI", sans-serif';
        ctx.fillText(`${percent}%`, cx, cy + 14);

        // 8. Frosted pill badge below score
        const pillW = 76;
        const pillH = 22;
        const pillX = cx - pillW / 2;
        const pillY = cy + 28;
        roundRect(ctx, pillX, pillY, pillW, pillH, 11);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.16)';
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.font = '800 10px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(226, 232, 240, 0.9)';
        ctx.fillText('WYNIK', cx, pillY + 15);
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
        const modeName = data.modeName || 'Egzamin';

        // Deep OLED Mesh Gradient
        const bg = ctx.createLinearGradient(0, 0, W, H);
        bg.addColorStop(0, '#070b14');
        bg.addColorStop(0.4, '#0d1527');
        bg.addColorStop(1, '#080c17');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        // Status-tinted atmospheric ambient orbs
        const orb1 = ctx.createRadialGradient(180, 140, 0, 180, 140, 380);
        if (isHarvest) {
            orb1.addColorStop(0, 'rgba(245, 158, 11, 0.22)');
            orb1.addColorStop(1, 'rgba(245, 158, 11, 0)');
        } else if (passed) {
            orb1.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
            orb1.addColorStop(0.7, 'rgba(6, 182, 212, 0.08)');
            orb1.addColorStop(1, 'rgba(16, 185, 129, 0)');
        } else {
            orb1.addColorStop(0, 'rgba(239, 68, 68, 0.20)');
            orb1.addColorStop(0.7, 'rgba(244, 63, 94, 0.08)');
            orb1.addColorStop(1, 'rgba(239, 68, 68, 0)');
        }
        ctx.fillStyle = orb1;
        ctx.fillRect(0, 0, W, H);

        // Secondary bottom indigo bloom
        const orb2 = ctx.createRadialGradient(280, H - 80, 0, 280, H - 80, 360);
        orb2.addColorStop(0, 'rgba(99, 102, 241, 0.12)');
        orb2.addColorStop(1, 'rgba(99, 102, 241, 0)');
        ctx.fillStyle = orb2;
        ctx.fillRect(0, 0, W, H);

        // Double bezel outer border
        roundRect(ctx, 24, 24, W - 48, H - 48, 28);
        const cardBorderGrad = ctx.createLinearGradient(24, 24, W - 24, H - 24);
        cardBorderGrad.addColorStop(0, 'rgba(255, 255, 255, 0.20)');
        cardBorderGrad.addColorStop(0.3, 'rgba(255, 255, 255, 0.06)');
        cardBorderGrad.addColorStop(0.7, 'rgba(255, 255, 255, 0.03)');
        cardBorderGrad.addColorStop(1, 'rgba(255, 255, 255, 0.12)');
        ctx.strokeStyle = cardBorderGrad;
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Inner subtle rim
        roundRect(ctx, 26, 26, W - 52, H - 52, 26);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
        ctx.lineWidth = 1;
        ctx.stroke();

        // Seamless Integrated Top Header Glass Strip
        const headerH = 96;
        roundRect(ctx, 24, 24, W - 48, headerH, 28);
        ctx.save();
        ctx.clip();
        const headerBg = ctx.createLinearGradient(24, 24, W - 24, 24 + headerH);
        headerBg.addColorStop(0, 'rgba(255, 255, 255, 0.045)');
        headerBg.addColorStop(1, 'rgba(255, 255, 255, 0.015)');
        ctx.fillStyle = headerBg;
        ctx.fillRect(24, 24, W - 48, headerH);
        ctx.restore();

        // Header bottom hairline separator
        const sepGrad = ctx.createLinearGradient(48, 24 + headerH, W - 48, 24 + headerH);
        sepGrad.addColorStop(0, 'rgba(255, 255, 255, 0.02)');
        sepGrad.addColorStop(0.2, 'rgba(255, 255, 255, 0.12)');
        sepGrad.addColorStop(0.8, 'rgba(255, 255, 255, 0.12)');
        sepGrad.addColorStop(1, 'rgba(255, 255, 255, 0.02)');
        ctx.strokeStyle = sepGrad;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(48, 24 + headerH);
        ctx.lineTo(W - 48, 24 + headerH);
        ctx.stroke();

        // Brand Icon Badge (48x48 rounded 14px)
        const bibX = 54;
        const bibY = 48;
        const bibS = 48;
        roundRect(ctx, bibX, bibY, bibS, bibS, 14);
        const bibGrad = ctx.createLinearGradient(bibX, bibY, bibX + bibS, bibY + bibS);
        bibGrad.addColorStop(0, 'rgba(59, 130, 246, 0.25)');
        bibGrad.addColorStop(1, 'rgba(37, 99, 235, 0.12)');
        ctx.fillStyle = bibGrad;
        ctx.fill();
        ctx.strokeStyle = 'rgba(96, 165, 250, 0.4)';
        ctx.lineWidth = 1.2;
        ctx.stroke();

        drawMortarboard(ctx, bibX + 5, bibY + 5, 38);

        // Brand Title & Subtitle
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 25px Inter, "Segoe UI", sans-serif';
        ctx.fillText(String(data.brand || 'ZSEM TECH'), 116, 70);
        ctx.font = '700 11px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(148, 163, 184, 0.88)';
        ctx.fillText('OFICJALNY CERTYFIKAT WYNIKU · PLATFORMA EGZAMINACYJNA', 116, 89);

        // Top-right pill tag
        const tagW = 180;
        const tagH = 32;
        const tagX = W - 56 - tagW;
        const tagY = 50;
        roundRect(ctx, tagX, tagY, tagW, tagH, 16);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.05)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.12)';
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.textAlign = 'center';
        ctx.fillStyle = '#93c5fd';
        ctx.font = '700 12px Inter, "Segoe UI", sans-serif';
        ctx.fillText(String(data.brandUrl || 'zsem-egzamin.online'), tagX + tagW / 2, tagY + 20);

        ctx.textAlign = 'right';
        ctx.fillStyle = 'rgba(148, 163, 184, 0.65)';
        ctx.font = '700 10px Inter, "Segoe UI", sans-serif';
        ctx.fillText(isHarvest ? 'KARTA HARVEST MASTER' : 'KARTA WERYFIKACJI WYNIKU', W - 56, tagY + 48);
        ctx.textAlign = 'left';

        // Status Pill Badge (Top-left of body)
        const passText = isHarvest ? 'HARVEST MASTER' : (passed ? 'ZALICZONY' : 'DO POPRAWY');
        ctx.font = '800 12px Inter, "Segoe UI", sans-serif';
        const badgeTextW = ctx.measureText(passText).width;
        const badgeW = badgeTextW + 48;
        const badgeH = 36;
        const badgeX = 56;
        const badgeY = 142;

        roundRect(ctx, badgeX, badgeY, badgeW, badgeH, 18);
        ctx.fillStyle = isHarvest ? 'rgba(245, 158, 11, 0.18)' : (passed ? 'rgba(16, 185, 129, 0.18)' : 'rgba(239, 68, 68, 0.18)');
        ctx.fill();
        ctx.strokeStyle = isHarvest ? 'rgba(251, 191, 36, 0.5)' : (passed ? 'rgba(52, 211, 153, 0.5)' : 'rgba(248, 113, 113, 0.5)');
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Mini status icon in badge
        const badgeIconX = badgeX + 18;
        const badgeIconY = badgeY + 18;
        if (isHarvest) {
            ctx.fillStyle = '#fcd34d';
            ctx.font = '700 14px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('★', badgeIconX, badgeIconY + 5);
        } else if (passed) {
            ctx.fillStyle = '#34d399';
            ctx.beginPath();
            ctx.arc(badgeIconX, badgeIconY, 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#064e3b';
            ctx.lineWidth = 1.8;
            ctx.beginPath();
            ctx.moveTo(badgeIconX - 3, badgeIconY);
            ctx.lineTo(badgeIconX - 1, badgeIconY + 2.5);
            ctx.lineTo(badgeIconX + 3, badgeIconY - 2.5);
            ctx.stroke();
        } else {
            ctx.fillStyle = '#f87171';
            ctx.beginPath();
            ctx.arc(badgeIconX, badgeIconY, 6, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#450a0a';
            ctx.lineWidth = 1.8;
            ctx.beginPath();
            ctx.moveTo(badgeIconX - 2.5, badgeIconY - 2.5);
            ctx.lineTo(badgeIconX + 2.5, badgeIconY + 2.5);
            ctx.moveTo(badgeIconX + 2.5, badgeIconY - 2.5);
            ctx.lineTo(badgeIconX - 2.5, badgeIconY + 2.5);
            ctx.stroke();
        }

        // Badge Text
        ctx.textAlign = 'left';
        ctx.fillStyle = isHarvest ? '#fde68a' : (passed ? '#a7f3d0' : '#fecaca');
        ctx.font = '800 12px Inter, "Segoe UI", sans-serif';
        ctx.fillText(passText, badgeX + 32, badgeY + 23);

        // Candidate Full Name
        const firstName = String(data.firstName || '—');
        const lastName = String(data.lastName || '—');
        const fullName = String(data.fullName || `${firstName} ${lastName}`.trim() || 'Uczestnik');
        ctx.fillStyle = '#ffffff';
        fitFont(ctx, fullName, '900', 44, 'Inter, "Segoe UI", sans-serif', 610, 26);
        ctx.fillText(truncate(ctx, fullName, 610), 56, 222);

        // Meta line: @nick · Klasa
        ctx.font = '700 16px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = '#38bdf8';
        const nickStr = `@${data.nickname || '—'}`;
        ctx.fillText(nickStr, 56, 256);
        const nickW = ctx.measureText(nickStr).width;

        ctx.fillStyle = 'rgba(148, 163, 184, 0.6)';
        ctx.fillText('  ·  ', 56 + nickW, 256);
        const dotW = ctx.measureText('  ·  ').width;

        ctx.fillStyle = 'rgba(226, 232, 240, 0.95)';
        const classStr = `Klasa ${data.className || '—'}`;
        ctx.fillText(truncate(ctx, classStr, 610 - nickW - dotW), 56 + nickW + dotW, 256);

        // Performance status title
        ctx.fillStyle = accent;
        ctx.font = '800 26px Inter, "Segoe UI", sans-serif';
        const perfText = isHarvest ? 'Harvest ukończony' : String(data.performanceLabel || (passed ? 'Wynik pozytywny' : 'Do poprawy'));
        ctx.fillText(truncate(ctx, perfText, 610), 56, 298);

        // Subtitle motivation text with automatic comma spacing sanitization
        const subtitleText = String(data.subtitle || '').replace(/,([^\s])/g, ', $1').trim();
        ctx.fillStyle = 'rgba(203, 213, 225, 0.92)';
        ctx.font = '500 16px Inter, "Segoe UI", sans-serif';
        wrapText(ctx, subtitleText, 56, 332, 600, 24, 2);

        // 6 Bento Stat Tiles
        const tileW = 250;
        const tileH = 82;
        const gap = 16;
        const startX = 56;
        const row1Y = 412;
        const row2Y = 508;

        drawInfoTile(ctx, startX, row1Y, tileW, tileH, 'Tryb', modeName, '#60a5fa', 'mode');
        drawInfoTile(ctx, startX + tileW + gap, row1Y, tileW, tileH, 'Poprawne', `${data.correctAnswers} / ${data.totalQuestions}`, '#34d399', 'check');
        drawInfoTile(ctx, startX + (tileW + gap) * 2, row1Y, tileW, tileH, 'Czas trwania', data.timeSpent, '#a78bfa', 'clock');
        drawInfoTile(ctx, startX, row2Y, tileW, tileH, 'Data wykonania', data.testDate, '#38bdf8', 'calendar');
        drawInfoTile(ctx, startX + tileW + gap, row2Y, tileW, tileH, 'Nick', `@${data.nickname || '—'}`, '#fbbf24', 'user');
        drawInfoTile(ctx, startX + (tileW + gap) * 2, row2Y, tileW, tileH, 'Klasa', data.className || '—', '#2dd4bf', 'class');

        // Score Gauge Dial
        drawScoreRing(ctx, W - 195, 278, 96, data.scorePercent || 0, accent);

        // Footer / Watermark
        ctx.textAlign = 'center';
        ctx.fillStyle = 'rgba(148, 163, 184, 0.7)';
        ctx.font = '700 11px Inter, "Segoe UI", sans-serif';
        ctx.fillText('WYGENEROWANO W ZSEM TECH · OFICJALNY DOKUMENT WYNIKU', W / 2, H - 44);
        ctx.font = '600 11px Inter, "Segoe UI", sans-serif';
        ctx.fillStyle = 'rgba(148, 163, 184, 0.45)';
        ctx.fillText('zsem.edu.pl · zsem-egzamin.online', W / 2, H - 26);
        ctx.textAlign = 'left';

        return canvas;
    }

    async function ensureFonts() {
        if (!document.fonts?.load) return;
        await Promise.all([
            document.fonts.load('500 16px Inter'),
            document.fonts.load('600 11px Inter'),
            document.fonts.load('700 10px Inter'),
            document.fonts.load('700 11px Inter'),
            document.fonts.load('700 12px Inter'),
            document.fonts.load('700 16px Inter'),
            document.fonts.load('800 10px Inter'),
            document.fonts.load('800 11px Inter'),
            document.fonts.load('800 12px Inter'),
            document.fonts.load('800 21px Inter'),
            document.fonts.load('800 26px Inter'),
            document.fonts.load('900 25px Inter'),
            document.fonts.load('900 44px Inter'),
            document.fonts.load('900 66px Inter'),
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
