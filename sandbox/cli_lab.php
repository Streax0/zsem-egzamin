<?php
/**
 * CLI Terminal Simulator — Linux & Windows (Phase 2 Pro)
 *
 * Interactive terminal emulator for IT exam preparation (INF.02, INF.03, INF.08).
 * Supports stateful VFS, pipes, inline nano editor, sub-shells (MySQL, Diskpart, Python, PowerShell, NSLOOKUP, SSH),
 * server services (Apache2, BIND9, Samba, DHCP, vsftpd, Postfix, NFS, IIS), man-pages, achievements and CKE scenarios.
 */
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin(true);

$userId = (int)($_SESSION['user_id'] ?? 0);
$userXp = 0;
$userRankInfo = ['name' => 'Początkujący', 'icon' => 'bi-shield', 'color' => '#64748b'];
$completedScenarioIds = [];

if ($userId > 0 && isset($pdo)) {
    try {
        $stmtUser = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userXp = (int)$stmtUser->fetchColumn();
        $userRankInfo = getRankInfoByXp($userXp);

        $pdo->exec("CREATE TABLE IF NOT EXISTS cli_lab_completions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scenario_id VARCHAR(64) NOT NULL,
            os VARCHAR(16) NOT NULL,
            xp_awarded INT NOT NULL,
            completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_scenario (user_id, scenario_id),
            INDEX idx_user_completions (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmtComp = $pdo->prepare("SELECT scenario_id FROM cli_lab_completions WHERE user_id = ?");
        $stmtComp->execute([$userId]);
        $completedScenarioIds = $stmtComp->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        error_log('CLI lab user data init failed: ' . $e->getMessage());
    }
}
$csrfCliLab = generateCsrfToken('cli_lab');

$pageTitle = 'CLI Lab — Symulator Terminala & Laboratorium CKE | ZSEM Tech';
$base_url  = '../';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Zaawansowany symulator terminala Linux i Windows do nauki poleceń sieciowych, administracji usługami (Apache, BIND, Samba, DHCP, IIS) na egzamin CKE INF.02/INF.03/INF.08">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/style.css', '..')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/dashboard-new.css', '..')) ?>">
    <script src="<?= htmlspecialchars(assetUrl('assets/js/theme-handler.js', '..')) ?>"></script>
    <style>
        :root {
            --term-bg: #0b0f19;
            --term-card-bg: #111827;
            --term-text: #38bdf8;
            --term-prompt: #818cf8;
            --term-error: #f87171;
            --term-warn: #fbbf24;
            --term-dim: #94a3b8;
            --term-white: #f8fafc;
            --term-success: #34d399;
            --term-border: rgba(255, 255, 255, 0.08);
            --term-cyan: #22d3ee;
            --term-magenta: #e879f9;
        }

        .cli-shell {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Terminal Window */
        .terminal-window {
            background: var(--term-bg);
            border-radius: 14px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .8), 0 0 0 1px var(--term-border);
            overflow: hidden;
            font-family: 'JetBrains Mono', 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
            font-size: .86rem;
            min-height: 540px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all .25s ease;
        }

        .terminal-window.is-fullscreen {
            position: fixed;
            inset: 10px;
            z-index: 1060;
            min-height: calc(100vh - 20px);
            border-radius: 12px;
        }

        .terminal-titlebar {
            background: rgba(255, 255, 255, 0.04);
            padding: .6rem 1rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            border-bottom: 1px solid var(--term-border);
            user-select: none;
            flex-wrap: wrap;
        }

        .term-dots {
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .term-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
            display: inline-block;
            cursor: pointer;
            transition: opacity .15s;
        }
        .term-dot:hover { opacity: .8; }

        .term-dot-red    { background: #ff5f57; }
        .term-dot-yellow { background: #ffbd2e; }
        .term-dot-green  { background: #28c840; }

        .term-title-text {
            color: #cbd5e1;
            font-size: .78rem;
            font-weight: 600;
            margin-left: .35rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .terminal-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
        }

        .os-toggle-group {
            display: flex;
            background: rgba(15, 23, 42, 0.6);
            padding: 2px;
            border-radius: 8px;
            border: 1px solid var(--term-border);
        }

        .os-btn {
            padding: .25rem .75rem;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--term-dim);
            font-size: .74rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .os-btn:hover { color: #fff; }

        .os-btn.active {
            background: #4f46e5;
            color: #fff;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.4);
        }

        .term-tool-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--term-border);
            color: var(--term-dim);
            font-size: .74rem;
            padding: .25rem .55rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all .2s;
        }

        .term-tool-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        /* Status Header Ribbon */
        .terminal-status-ribbon {
            background: rgba(15, 23, 42, 0.5);
            border-bottom: 1px solid var(--term-border);
            padding: .4rem 1rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-size: .72rem;
            color: #94a3b8;
            overflow-x: auto;
            white-space: nowrap;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .status-chip strong { color: #e2e8f0; }

        .status-chip .badge-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            box-shadow: 0 0 6px #10b981;
        }

        /* Output Area */
        .terminal-output {
            flex: 1;
            padding: 1.1rem 1.3rem;
            overflow-y: auto;
            color: var(--term-white);
            line-height: 1.65;
            min-height: 380px;
            max-height: 490px;
        }

        .terminal-window.is-fullscreen .terminal-output {
            max-height: calc(100vh - 200px);
            min-height: calc(100vh - 200px);
        }

        .terminal-output::-webkit-scrollbar { width: 6px; }
        .terminal-output::-webkit-scrollbar-track { background: transparent; }
        .terminal-output::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, .15); border-radius: 3px; }

        .term-line { white-space: pre-wrap; word-break: break-all; margin-bottom: 2px; }
        .term-line.prompt   { color: var(--term-prompt); font-weight: 700; }
        .term-line.error    { color: var(--term-error); font-weight: 600; }
        .term-line.warn     { color: var(--term-warn); }
        .term-line.dim      { color: var(--term-dim); }
        .term-line.success  { color: var(--term-success); font-weight: 600; }
        .term-line.white    { color: var(--term-white); }
        .term-line.info     { color: #38bdf8; }
        .term-dir           { color: #60a5fa; font-weight: 700; }
        .term-exec          { color: #34d399; font-weight: 700; }
        .term-link          { color: #22d3ee; }

        /* Input Row */
        .terminal-input-row {
            display: flex;
            align-items: center;
            padding: .65rem 1.25rem .85rem;
            border-top: 1px solid var(--term-border);
            gap: .5rem;
            background: rgba(15, 23, 42, 0.6);
        }

        .terminal-prompt-label {
            color: var(--term-prompt);
            white-space: nowrap;
            font-weight: 700;
            font-size: .86rem;
            flex-shrink: 0;
        }

        #termInput {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--term-white);
            font-family: inherit;
            font-size: .86rem;
            caret-color: #38bdf8;
        }

        /* Virtual Key Toolbar */
        .terminal-touch-bar {
            display: flex;
            gap: .35rem;
            padding: .4rem 1rem;
            background: rgba(11, 15, 25, 0.95);
            border-top: 1px solid var(--term-border);
            overflow-x: auto;
        }

        .touch-key {
            padding: .2rem .55rem;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--term-border);
            color: #cbd5e1;
            font-size: .72rem;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
            transition: all .15s;
        }

        .touch-key:active, .touch-key:hover {
            background: #4f46e5;
            color: #fff;
        }

        /* Nano Editor Inline Overlay */
        .nano-overlay {
            position: absolute;
            inset: 0;
            background: #0d1117;
            display: flex;
            flex-direction: column;
            z-index: 10;
            font-family: inherit;
        }

        .nano-header {
            background: #cbd5e1;
            color: #0f172a;
            padding: .35rem .9rem;
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: .78rem;
        }

        .nano-body {
            flex: 1;
            display: flex;
            padding: .6rem;
            background: #0b0f19;
            position: relative;
        }

        .nano-textarea {
            width: 100%;
            height: 100%;
            background: transparent;
            border: none;
            outline: none;
            color: #f8fafc;
            font-family: inherit;
            font-size: .85rem;
            resize: none;
            line-height: 1.55;
            white-space: pre;
            tab-size: 4;
        }

        .nano-status {
            padding: .35rem .9rem;
            background: #1e293b;
            color: #fbbf24;
            font-size: .75rem;
            font-weight: 600;
            min-height: 28px;
        }

        .nano-footer {
            background: #0f172a;
            border-top: 1px solid var(--term-border);
            padding: .45rem .9rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: .35rem;
            font-size: .72rem;
            color: #e2e8f0;
        }

        .nano-key-hint {
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .nano-key-hint kbd {
            background: #334155;
            color: #fff;
            padding: .15rem .35rem;
            border-radius: 4px;
            font-size: .68rem;
        }

        /* Scenario Panel & Right Column */
        .scenario-panel {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 14px;
            padding: 1.25rem;
            height: 100%;
        }

        .category-filter-chips {
            display: flex;
            gap: .35rem;
            overflow-x: auto;
            padding-bottom: .4rem;
            margin-bottom: .85rem;
        }

        .cat-chip {
            padding: .25rem .65rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            border: 1px solid var(--border-color, #e2e8f0);
            background: transparent;
            color: var(--text-muted, #64748b);
            cursor: pointer;
            white-space: nowrap;
            transition: all .2s;
        }

        .cat-chip.active {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }

        .scenario-card {
            padding: .85rem 1rem;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            transition: all .2s;
            font-size: .82rem;
            background: rgba(148, 163, 184, 0.04);
            border-color: rgba(148, 163, 184, 0.15);
        }

        .scenario-card:hover {
            border-color: rgba(99, 102, 241, .4);
            background: rgba(99, 102, 241, .06);
        }

        .scenario-card.active {
            border-color: #6366f1;
            background: rgba(99, 102, 241, .1);
        }

        .scenario-card.completed {
            border-color: #10b981;
            background: rgba(16, 185, 129, .08);
        }

        .scenario-badge {
            font-size: .65rem;
            font-weight: 800;
            padding: .15rem .45rem;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .scenario-stars {
            color: #f59e0b;
            font-size: .72rem;
        }

        /* Services Grid Widget */
        .service-pill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: .4rem;
        }

        .service-pill {
            padding: .35rem .6rem;
            border-radius: 8px;
            background: rgba(148, 163, 184, 0.06);
            border: 1px solid var(--border-color, #e2e8f0);
            font-size: .72rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .service-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .service-dot.active { background: #10b981; box-shadow: 0 0 5px #10b981; }
        .service-dot.inactive { background: #94a3b8; }

        /* Stats Strip */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: .4rem;
            background: rgba(148, 163, 184, 0.05);
            padding: .6rem;
            border-radius: 10px;
            text-align: center;
        }

        .stat-item-num { font-weight: 800; font-size: .95rem; color: #4f46e5; }
        .stat-item-lbl { font-size: .62rem; color: var(--text-muted, #64748b); text-transform: uppercase; }

        .cli-hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, .95) 0%, rgba(30, 41, 59, .85) 100%);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 16px;
            padding: 1.4rem 1.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .cli-hero-title { color: #f8fafc; font-weight: 800; font-size: 1.35rem; margin: 0; }
        .cli-hero-sub   { color: #94a3b8; font-size: .84rem; margin: .25rem 0 0; }

        /* Achievement Toast Container */
        #cliToastContainer {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .achievement-toast {
            background: #1e293b;
            color: #fff;
            border-left: 4px solid #f59e0b;
            padding: .75rem 1.1rem;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,.5);
            animation: slideInRight .3s ease;
            font-size: .82rem;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 cli-shell">

                <!-- Hero Header -->
                <div class="cli-hero shadow-sm">
                    <div>
                        <h1 class="cli-hero-title d-flex align-items-center flex-wrap gap-2">
                            <span><i class="bi bi-terminal-fill me-2 text-primary"></i>CLI Lab — Symulator Terminala & Laboratorium Serwerowe CKE</span>
                            <span class="badge bg-warning text-dark fw-bold px-2 py-1 fs-6 rounded-pill" title="Moduł w fazie testów i rozwoju">BETA</span>
                        </h1>
                        <p class="cli-hero-sub">Pełny stanowy terminal z VFS, potokami, edytorem nano, sub-shellami (MySQL, Diskpart, Python, PowerShell, NSLOOKUP, SSH) oraz usługami sieciowymi (Apache, BIND9, Samba, DHCP, vsftpd, Postfix, NFS, IIS).</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span class="badge bg-warning bg-opacity-20 text-warning fw-bold px-3 py-2 border border-warning border-opacity-30 rounded-pill" title="Twój bieżący stan XP i ranga">
                            <i class="bi bi-trophy-fill me-1"></i><span id="heroXpDisplay"><?= number_format($userXp) ?> XP</span> • <span id="heroRankDisplay"><?= htmlspecialchars($userRankInfo['name']) ?></span>
                        </span>
                        <span class="badge bg-success bg-opacity-20 text-success fw-bold px-3 py-2 border border-success border-opacity-30 rounded-pill">
                            <i class="bi bi-server me-1"></i>Usługi Serwerowe
                        </span>
                        <span class="badge bg-primary bg-opacity-20 text-primary fw-bold px-3 py-2 border border-primary border-opacity-30 rounded-pill">
                            <i class="bi bi-award-fill me-1"></i>20 Zadań CKE
                        </span>
                    </div>
                </div>

                <!-- Beta Notice Banner -->
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-4 rounded-3" style="background: rgba(245, 158, 11, 0.12); border-left: 4px solid #f59e0b !important;">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-info-circle-fill text-warning fs-4 flex-shrink-0"></i>
                        <div>
                            <div class="fw-bold text-dark dark:text-light mb-1">
                                Moduł CLI Lab jest obecnie w fazie testowej (BETA)
                            </div>
                            <div class="small text-muted">
                                Sandbox jest w trakcie aktywnego rozwoju. Baza poleceń, pakiety serwerowe oraz walidacja scenariuszy egzaminacyjnych mogą być na bieżąco aktualizowane.
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-warning bg-opacity-25 text-warning fw-bold px-3 py-2 rounded-pill d-none d-md-inline-block">
                        <i class="bi bi-shield-check me-1"></i>Wczesny dostęp
                    </span>
                </div>

                <div class="row g-4">

                    <!-- Terminal Column -->
                    <div class="col-lg-8">
                        <div class="terminal-window" id="terminalWindow">
                            
                            <!-- Title Bar -->
                            <div class="terminal-titlebar">
                                <div class="term-dots">
                                    <span class="term-dot term-dot-red" id="dotClose" title="Wyczyść terminal"></span>
                                    <span class="term-dot term-dot-yellow" id="dotMin" title="Zwiń / Minimalizuj"></span>
                                    <span class="term-dot term-dot-green" id="dotMax" title="Pełny ekran"></span>
                                </div>
                                <span class="term-title-text" id="termTitle">
                                    <i class="bi bi-terminal"></i> <span id="termTitleLabel">bash — student@zsem-lab: ~</span>
                                </span>
                                
                                <div class="terminal-actions">
                                    <div class="os-toggle-group">
                                        <button class="os-btn active" data-os="linux" id="osBtnLinux" title="Przełącz na Bash (Linux)">
                                            <i class="bi bi-terminal"></i> Linux
                                        </button>
                                        <button class="os-btn" data-os="windows" id="osBtnWin" title="Przełącz na CMD / PowerShell (Windows)">
                                            <i class="bi bi-windows"></i> Windows
                                        </button>
                                    </div>
                                    <button class="term-tool-btn" id="btnTermExport" title="Eksportuj log sesji do pliku .txt">
                                        <i class="bi bi-download"></i> Eksport
                                    </button>
                                    <button class="term-tool-btn" id="btnTermCopy" title="Kopiuj zawartość terminala">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <button class="term-tool-btn" id="btnTermClear" title="Wyczyść ekran (Ctrl+L)">
                                        <i class="bi bi-eraser"></i>
                                    </button>
                                    <button class="term-tool-btn" id="btnTermFullscreen" title="Pełny ekran">
                                        <i class="bi bi-fullscreen"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Live Status Ribbon -->
                            <div class="terminal-status-ribbon">
                                <div class="status-chip">
                                    <span class="badge-pulse"></span>
                                    <span>Status: <strong>Online</strong></span>
                                </div>
                                <div class="status-chip">
                                    <i class="bi bi-trophy-fill text-warning"></i>
                                    <span>XP: <strong id="statusXp"><?= number_format($userXp) ?></strong></span>
                                </div>
                                <div class="status-chip">
                                    <i class="bi bi-globe text-info"></i>
                                    <span>IP: <strong id="statusIp">192.168.1.100</strong></span>
                                </div>
                                <div class="status-chip">
                                    <i class="bi bi-router text-warning"></i>
                                    <span>Brama: <strong id="statusGw">192.168.1.1</strong></span>
                                </div>
                                <div class="status-chip">
                                    <i class="bi bi-person-fill text-primary"></i>
                                    <span>Użytkownik: <strong id="statusUser">student</strong></span>
                                </div>
                                <div class="status-chip">
                                    <i class="bi bi-folder-fill text-success"></i>
                                    <span>Katalog: <strong id="statusPwd">/home/student</strong></span>
                                </div>
                            </div>

                            <!-- Output Area -->
                            <div class="terminal-output" id="termOutput" role="log" aria-live="polite"></div>

                            <!-- Input Row -->
                            <div class="terminal-input-row" id="terminalInputRow">
                                <span class="terminal-prompt-label" id="termPromptLabel">student@zsem-lab:~$</span>
                                <input type="text" id="termInput" autocomplete="off" autocorrect="off"
                                    autocapitalize="off" spellcheck="false"
                                    aria-label="Wprowadź polecenie terminala" placeholder="Wpisz komendę (np. man, apt install apache2, nano, systemctl, mysql, diskpart, powershell)...">
                            </div>

                            <!-- Touch / Quick Virtual Keys Bar -->
                            <div class="terminal-touch-bar">
                                <button type="button" class="touch-key" data-key="Tab">Tab</button>
                                <button type="button" class="touch-key" data-key="CtrlC">Ctrl+C</button>
                                <button type="button" class="touch-key" data-key="CtrlL">Ctrl+L</button>
                                <button type="button" class="touch-key" data-key="Up">↑</button>
                                <button type="button" class="touch-key" data-key="Down">↓</button>
                                <button type="button" class="touch-key" data-insert="|">|</button>
                                <button type="button" class="touch-key" data-insert=">">&gt;</button>
                                <button type="button" class="touch-key" data-insert=">>">&gt;&gt;</button>
                                <button type="button" class="touch-key" data-insert="~">~</button>
                                <button type="button" class="touch-key" data-insert="/">/</button>
                                <button type="button" class="touch-key" data-insert=" -"> -</button>
                            </div>

                            <!-- Inline Nano Editor Overlay -->
                            <div class="nano-overlay" id="nanoOverlay" style="display:none;">
                                <div class="nano-header">
                                    <span>GNU nano 6.2</span>
                                    <span id="nanoFilename">Nowy plik</span>
                                    <span id="nanoModified">[Zapisany]</span>
                                </div>
                                <div class="nano-body">
                                    <textarea id="nanoTextarea" class="nano-textarea" spellcheck="false" placeholder="Wpisz tekst pliku..."></textarea>
                                </div>
                                <div class="nano-status" id="nanoStatusMsg">Wskazówka: Wciśnij Ctrl+O aby zapisać, Ctrl+X aby zamknąć, Ctrl+K aby wyciąć, Ctrl+U aby wkleić.</div>
                                <div class="nano-footer">
                                    <div class="nano-key-hint"><kbd>^O</kbd> Zapisz plik</div>
                                    <div class="nano-key-hint"><kbd>^X</kbd> Wyjdź</div>
                                    <div class="nano-key-hint"><kbd>^K</kbd> Wytnij linię</div>
                                    <div class="nano-key-hint"><kbd>^U</kbd> Wklej</div>
                                    <div class="nano-key-hint"><kbd>^C</kbd> Pozycja</div>
                                    <div class="nano-key-hint"><kbd>^W</kbd> Szukaj</div>
                                </div>
                            </div>

                        </div>

                        <!-- Scenario Progress Indicator -->
                        <div class="mt-3 card border-0 shadow-sm p-3" id="scenarioProgressWrap" style="display:none; background: var(--panel-bg, #fff);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold fs-6 text-primary" id="scenarioProgressLabel">Postęp zadania</span>
                                <span class="badge bg-primary bg-opacity-20 text-primary" id="scenarioStepLabel">Krok 1/4</span>
                            </div>
                            <div class="progress my-2" style="height:8px; border-radius:6px; background: rgba(148, 163, 184, 0.15);">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="scenarioProgressBar" style="width:0%; transition:width .4s"></div>
                            </div>
                            <div class="small text-muted" id="scenarioStepInstruction">Wykonaj polecenie zgodnie z instrukcją zadania.</div>
                        </div>

                        <!-- Server Services Live Monitor Widget -->
                        <div class="mt-3 card border-0 shadow-sm p-3" style="background: var(--panel-bg, #fff);">
                            <h3 class="fw-bold fs-6 mb-2 text-dark dark:text-light">
                                <i class="bi bi-hdd-stack me-1 text-primary"></i>Stan Usług Serwerowych (systemctl / sc)
                            </h3>
                            <div class="service-pill-grid" id="serviceMonitorGrid">
                                <!-- Injected by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Scenario & Guide Column -->
                    <div class="col-lg-4">
                        <div class="scenario-panel shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h2 class="fw-bold fs-5 mb-0">
                                    <i class="bi bi-list-check me-2 text-primary"></i>Zadania egzaminacyjne
                                </h2>
                                <button class="btn btn-outline-danger btn-sm rounded-pill py-1 px-2" id="scenarioResetVfsBtn" title="Przywraca domyślny system plików i IP">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset VFS
                                </button>
                            </div>
                            <p class="text-muted small mb-2">Wybierz zadanie z bazy CKE, aby przećwiczyć procedury diagnostyczne, konfigurację usług i uprawnień oraz zdobywać XP do rankingu!</p>

                            <!-- Session Stats Strip -->
                            <div class="stats-strip mb-3">
                                <div>
                                    <div class="stat-item-num text-warning" id="statUserXp"><?= number_format($userXp) ?></div>
                                    <div class="stat-item-lbl">Twój XP</div>
                                </div>
                                <div>
                                    <div class="stat-item-num" id="statCmdCount">0</div>
                                    <div class="stat-item-lbl">Komend</div>
                                </div>
                                <div>
                                    <div class="stat-item-num" id="statScenCount">0/20</div>
                                    <div class="stat-item-lbl">Zadań</div>
                                </div>
                                <div>
                                    <div class="stat-item-num" id="statPkgCount">0</div>
                                    <div class="stat-item-lbl">Pakietów</div>
                                </div>
                                <div>
                                    <div class="stat-item-num" id="statTimeCount">0m</div>
                                    <div class="stat-item-lbl">Czas</div>
                                </div>
                            </div>

                            <!-- Category Chips -->
                            <div class="category-filter-chips" id="scenarioCategoryChips">
                                <button type="button" class="cat-chip active" data-cat="all">Wszystkie (20)</button>
                                <button type="button" class="cat-chip" data-cat="inf02_srv">Serwery CKE</button>
                                <button type="button" class="cat-chip" data-cat="inf02_net">INF.02 Sieci</button>
                                <button type="button" class="cat-chip" data-cat="inf02_sys">INF.02 Systemy</button>
                                <button type="button" class="cat-chip" data-cat="inf03_db">INF.03 Bazy</button>
                                <button type="button" class="cat-chip" data-cat="inf08_sec">INF.08 Security</button>
                            </div>

                            <div class="d-flex flex-column gap-2" id="scenarioList" style="max-height: 380px; overflow-y: auto; padding-right: 4px;">
                                <!-- Injected by JS -->
                            </div>
                            
                            <hr class="my-3">
                            
                            <div id="activeScenarioDesc" class="p-2 rounded-3" style="font-size:.82rem; background: rgba(148, 163, 184, 0.08);">
                                <i class="bi bi-info-circle me-1 text-primary"></i>Wybierz scenariusz z listy powyżej, aby rozpocząć symulowany egzamin praktyczny.
                            </div>
                            
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-outline-secondary btn-sm rounded-pill flex-fill" id="scenarioClearBtn">
                                    <i class="bi bi-terminal me-1"></i>Wyczyść konsolę
                                </button>
                                <button class="btn btn-outline-primary btn-sm rounded-pill" id="scenarioSkipBtn">
                                    <i class="bi bi-skip-forward me-1"></i>Pomiń krok
                                </button>
                            </div>

                            <hr class="my-3">
                            <h3 class="fw-bold fs-6 mb-2 d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-keyboard me-1 text-primary"></i>Szybka ściągawka</span>
                                <span class="badge bg-secondary bg-opacity-20 text-muted" id="commandCountBadge">150+ komend & man</span>
                            </h3>
                            <div id="commandList" style="font-size:.72rem; color:var(--text-muted,#64748b); line-height:1.75; max-height:140px; overflow-y:auto;">
                                <!-- Injected by JS -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Toast Container for Milestones & Achievements -->
<div id="cliToastContainer"></div>

<script>
window.CLI_LAB_USER = {
    userId: <?= (int)$userId ?>,
    csrfToken: '<?= htmlspecialchars($csrfCliLab, ENT_QUOTES, 'UTF-8') ?>',
    xp: <?= (int)$userXp ?>,
    rankName: <?= json_encode($userRankInfo['name'] ?? 'Początkujący') ?>,
    rankIcon: <?= json_encode($userRankInfo['icon'] ?? 'bi-shield') ?>,
    completedScenarios: <?= json_encode($completedScenarioIds) ?>
};
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/theme-handler.js', '..')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/terminal_commands.js', '..')) ?>"></script>
</body>
</html>
