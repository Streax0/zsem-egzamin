<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
startSecureSession();

// Read engine configuration
$configPath = __DIR__ . '/data/config/engine_config.json';
$maintenanceMode = false;
$maintenanceMessage = 'Trwają planowane prace serwisowe. Zapraszamy wkrótce.';
$maintenanceUntil = '';

if (file_exists($configPath)) {
    $raw = @file_get_contents($configPath);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $maintenanceMode = (bool)($data['maintenance_mode'] ?? false);
            $maintenanceMessage = (string)($data['maintenance_message'] ?? $maintenanceMessage);
            $maintenanceUntil = (string)($data['maintenance_until'] ?? '');
        }
    }
}

// If maintenance is not active, redirect to home page
if (!$maintenanceMode) {
    header('Location: index.php');
    exit;
}

// Send HTTP 503 Service Unavailable
if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 300');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

$isAdmin = !empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true);
$hasTargetTime = !empty($maintenanceUntil) && strtotime($maintenanceUntil) > time();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>Prace Konserwacyjne — ZSEM Tech</title>
    <link rel="icon" href="zsemtech_profile.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <style>
        :root {
            --m-bg-start: #090d16;
            --m-bg-end: #131a2a;
            --m-card-bg: rgba(22, 30, 49, 0.75);
            --m-border: rgba(255, 255, 255, 0.1);
            --m-accent: #6366f1;
            --m-accent-glow: rgba(99, 102, 241, 0.35);
        }
        * { box-sizing: border-box; }
        body {
            background: radial-gradient(circle at 50% 20%, #1e1b4b 0%, var(--m-bg-start) 45%, var(--m-bg-end) 100%);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }
        .maintenance-card {
            background: var(--m-card-bg);
            border: 1px solid var(--m-border);
            border-radius: 28px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 40px var(--m-accent-glow);
            padding: 3rem 2.5rem;
            max-width: 580px;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 2;
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon-circle {
            width: 96px;
            height: 96px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(124, 58, 237, 0.2));
            color: #818cf8;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 1.75rem;
            border: 1px solid rgba(129, 140, 248, 0.3);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.25);
            animation: pulseGlow 3s infinite alternate;
        }
        @keyframes pulseGlow {
            0% { transform: scale(1); box-shadow: 0 0 20px rgba(99, 102, 241, 0.2); }
            100% { transform: scale(1.04); box-shadow: 0 0 35px rgba(99, 102, 241, 0.4); }
        }
        .countdown-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin: 2rem 0;
        }
        .countdown-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 0.85rem 0.4rem;
        }
        .countdown-val {
            font-size: 1.75rem;
            font-weight: 800;
            font-family: 'SF Mono', Monaco, Consolas, monospace;
            color: #ffffff;
            line-height: 1;
        }
        .countdown-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-top: 0.35rem;
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            box-shadow: 0 0 8px #ef4444;
            animation: blink 1.5s infinite ease-in-out;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>

    <div class="maintenance-card">
        <div class="status-badge">
            <span class="status-dot"></span> Prace Serwisowe (HTTP 503)
        </div>

        <div class="d-block">
            <div class="icon-circle">
                <i class="bi bi-tools"></i>
            </div>
        </div>

        <h1 class="h3 fw-bold mb-2">Trwają Prace Konserwacyjne</h1>
        <p class="text-secondary mb-3" style="font-size: 0.95rem; line-height: 1.6;">
            <?php echo nl2br(htmlspecialchars($maintenanceMessage, ENT_QUOTES, 'UTF-8')); ?>
        </p>

        <?php if ($hasTargetTime): ?>
            <div class="countdown-grid" id="maintenanceCountdown" data-legacy-id="countdownGrid" data-target="<?php echo htmlspecialchars($maintenanceUntil, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="countdown-item">
                    <div class="countdown-val" id="cdDays">00</div>
                    <div class="countdown-label">Dni</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-val" id="cdHours">00</div>
                    <div class="countdown-label">Godz</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-val" id="cdMinutes">00</div>
                    <div class="countdown-label">Min</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-val" id="cdSeconds">00</div>
                    <div class="countdown-label">Sek</div>
                </div>
            </div>
            <div class="small text-muted mb-4" id="cdEstimateNote">
                Przewidywany powrót: <strong><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($maintenanceUntil)), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        <?php else: ?>
            <div class="p-3 bg-body-tertiary bg-opacity-25 rounded-3 border border-white border-opacity-10 small text-muted mb-4">
                <i class="bi bi-info-circle me-1"></i> System wznowi działanie niezwłocznie po wdrożeniu poprawek. Strona odświeża się automatycznie co 60 sekund.
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <button type="button" class="btn btn-outline-light rounded-pill px-4 btn-sm" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i> Sprawdź ponownie
            </button>
            <?php if ($isAdmin): ?>
                <a href="admin/engine.php" class="btn btn-primary rounded-pill px-4 btn-sm fw-semibold">
                    <i class="bi bi-speedometer2 me-1"></i> Panel Administratora
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-secondary rounded-pill px-3 btn-sm text-white-50">
                    <i class="bi bi-lock me-1"></i> Logowanie personelu
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        const grid = document.getElementById('maintenanceCountdown') || document.getElementById('countdownGrid');
        if (grid) {
            const targetStr = grid.dataset.target;
            const targetDate = new Date(targetStr).getTime();

            function maintenanceCountdown() {
                const now = new Date().getTime();
                const diff = targetDate - now;

                if (diff <= 0) {
                    document.getElementById('cdDays').textContent = '00';
                    document.getElementById('cdHours').textContent = '00';
                    document.getElementById('cdMinutes').textContent = '00';
                    document.getElementById('cdSeconds').textContent = '00';
                    setTimeout(() => { window.location.reload(); }, 3000);
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                const pad = n => n.toString().padStart(2, '0');
                document.getElementById('cdDays').textContent = pad(days);
                document.getElementById('cdHours').textContent = pad(hours);
                document.getElementById('cdMinutes').textContent = pad(minutes);
                document.getElementById('cdSeconds').textContent = pad(seconds);
            }

            maintenanceCountdown();
            setInterval(maintenanceCountdown, 1000);
        }

        // Periodic check every 30 seconds
        setInterval(() => {
            fetch('index.php', { method: 'HEAD', cache: 'no-store' })
                .then(res => {
                    if (res.status === 200) {
                        window.location.href = 'index.php';
                    }
                })
                .catch(() => {});
        }, 30000);
    })();
    </script>
</body>
</html>
