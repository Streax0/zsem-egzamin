<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

$userId = $_SESSION['user_id'] ?? null;
$userRole = (string)($_SESSION['role'] ?? 'user');

// Fetch user profile and ranking info for stats bar
$userXp = 0;
$userRankInfo = ['name' => 'Początkujący', 'icon' => 'bi-shield'];
$completedScenarioIds = [];

if ($userId && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT xp, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $userXp = (int)($userRow['xp'] ?? 0);
            $userRankInfo = getRankInfoByXp($userXp);
        }

        // Fetch completed CLI lab scenarios
        $stmtScen = $pdo->prepare("SELECT scenario_id FROM cli_lab_completions WHERE user_id = ?");
        $stmtScen->execute([$userId]);
        $completedScenarioIds = $stmtScen->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        error_log("CLI Lab User Fetch Error: " . $e->getMessage());
    }
}

$csrfCliLab = generateCsrfToken('cli_lab_reward');
$pageTitle = 'Zaawansowany Symulator Terminala CLI & Zadania CKE';
$extraCss = ['assets/css/dashboard-new.css'];
$base_url = '../';
include '../includes/header.php';
?>
<style>
    :root {
        --term-bg: #0d1117;
        --term-titlebar: #161b22;
        --term-border: #30363d;
        --term-green: #3fb950;
        --term-cyan: #58a6ff;
        --term-yellow: #d29922;
        --term-red: #f85149;
        --term-white: #f0f6fc;
        --term-dim: #8b949e;
        --term-accent: #6366f1;
        --term-font: 'Fira Code', 'Cascadia Code', Consolas, Menlo, Monaco, monospace;
    }

    /* ── Color Themes ── */
    .terminal-window.theme-ubuntu {
        --term-bg: #300a24;
        --term-titlebar: #2c001e;
        --term-border: #5e2750;
        --term-green: #4af626;
        --term-cyan: #e95420;
    }
    .terminal-window.theme-powershell {
        --term-bg: #012456;
        --term-titlebar: #0c1021;
        --term-border: #1e3a8a;
        --term-green: #38bdf8;
        --term-cyan: #facc15;
    }
    .terminal-window.theme-dracula {
        --term-bg: #282a36;
        --term-titlebar: #21222c;
        --term-border: #44475a;
        --term-green: #50fa7b;
        --term-cyan: #8be9fd;
    }
    .terminal-window.theme-matrix {
        --term-bg: #030a04;
        --term-titlebar: #061708;
        --term-border: #14532d;
        --term-green: #22c55e;
        --term-cyan: #4ade80;
    }

    /* ── Terminal Tabs ── */
    .terminal-tabs {
        display: flex;
        background: rgba(0, 0, 0, 0.35);
        border-bottom: 1px solid var(--term-border);
        padding: 0 0.5rem;
        gap: 2px;
        overflow-x: auto;
    }
    .term-tab {
        padding: 0.35rem 0.75rem;
        font-size: 0.72rem;
        color: var(--term-dim);
        border-bottom: 2px solid transparent;
        cursor: pointer;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        user-select: none;
    }
    .term-tab:hover {
        color: var(--term-white);
        background: rgba(255, 255, 255, 0.04);
    }
    .term-tab.active {
        color: var(--term-white);
        border-bottom-color: var(--term-green);
        background: rgba(255, 255, 255, 0.06);
        font-weight: 600;
    }

    /* ── Modern Terminal Container ── */
    .terminal-window {
        background: var(--term-bg);
        border: 1px solid var(--term-border);
        border-radius: 16px;
        box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-family: var(--term-font);
        position: relative;
        transition: all .25s ease;
    }

    .terminal-window.fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 9999;
        border-radius: 0;
        box-shadow: none;
    }

    /* Title Bar */
    .terminal-titlebar {
        background: var(--term-titlebar);
        border-bottom: 1px solid var(--term-border);
        padding: .65rem 1rem;
        display: flex;
        align-items: center;
        user-select: none;
        gap: .75rem;
    }

    .term-dots {
        display: flex;
        gap: 7px;
        align-items: center;
    }

    .term-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        cursor: pointer;
        transition: transform .15s ease, filter .15s;
    }

    .term-dot:hover { transform: scale(1.15); filter: brightness(1.2); }
    .term-dot-red { background: #ff5f56; }
    .term-dot-yellow { background: #ffbd2e; }
    .term-dot-green { background: #27c93f; }

    .term-title-text {
        color: var(--term-dim);
        font-size: .82rem;
        font-weight: 500;
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

    .terminal-window.fullscreen .terminal-output {
        max-height: calc(100vh - 180px);
    }

    .term-line {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: .84rem;
    }

    .term-prompt {
        color: var(--term-cyan);
        font-weight: 700;
    }

    .term-cmd {
        color: #fff;
        font-weight: 600;
    }

    .term-success { color: var(--term-green); }
    .term-error { color: var(--term-red); }
    .term-warn { color: var(--term-yellow); }
    .term-dim { color: var(--term-dim); }
    .term-cyan { color: var(--term-cyan); }
    .term-magenta { color: #d946ef; }

    /* Input Row */
    .terminal-input-row {
        background: rgba(22, 27, 34, 0.95);
        border-top: 1px solid var(--term-border);
        padding: .65rem 1rem;
        display: flex;
        align-items: center;
        gap: .65rem;
    }

    .terminal-prompt-label {
        color: var(--term-green);
        font-weight: 700;
        font-size: .84rem;
        white-space: nowrap;
    }

    #termInput {
        flex: 1;
        background: transparent;
        border: none;
        color: #fff;
        font-family: var(--term-font);
        font-size: .85rem;
        outline: none;
        caret-color: var(--term-green);
    }

    /* Touch / Virtual Quick Keys Bar */
    .terminal-touch-bar {
        background: rgba(13, 17, 23, 0.9);
        border-top: 1px solid var(--term-border);
        padding: .35rem .75rem;
        display: flex;
        gap: .4rem;
        overflow-x: auto;
        white-space: nowrap;
    }

    .touch-key {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--term-border);
        color: #cbd5e1;
        padding: .2rem .55rem;
        border-radius: 5px;
        font-size: .72rem;
        font-family: var(--term-font);
        cursor: pointer;
        transition: all .15s;
    }

    .touch-key:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    /* ── Nano Text Editor Overlay ── */
    .nano-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #0d1117;
        z-index: 100;
        display: flex;
        flex-direction: column;
        font-family: var(--term-font);
    }

    .nano-header {
        background: #ffffff;
        color: #000;
        font-weight: 700;
        padding: .25rem .75rem;
        font-size: .8rem;
        display: flex;
        justify-content: space-between;
    }

    .nano-body {
        flex: 1;
        padding: .5rem .75rem;
    }

    .nano-textarea {
        width: 100%;
        height: 100%;
        background: transparent;
        border: none;
        color: #f0f6fc;
        font-family: var(--term-font);
        font-size: .85rem;
        resize: none;
        outline: none;
        line-height: 1.5;
    }

    .nano-status {
        background: rgba(255, 255, 255, 0.05);
        color: #94a3b8;
        font-size: .75rem;
        padding: .2rem .75rem;
        border-top: 1px solid var(--term-border);
    }

    .nano-footer {
        background: #161b22;
        border-top: 1px solid var(--term-border);
        padding: .4rem .75rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: .35rem;
        font-size: .72rem;
        color: #cbd5e1;
    }

    .nano-key-hint kbd {
        background: #ffffff;
        color: #000;
        font-weight: 700;
        padding: 1px 4px;
        border-radius: 3px;
        margin-right: 4px;
    }

    /* ── Scenario Step Card & Mini Bar ── */
    .scenario-full-card {
        background: var(--bs-body-bg, #fff);
        transition: all .25s ease;
    }

    .scenario-mini-bar {
        background: var(--bs-body-bg, #fff);
        border-radius: 12px;
        transition: all .2s ease;
    }

    .scenario-mini-bar:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08) !important;
    }

    /* Scenario List Panel */
    .scenario-panel {
        background: var(--bs-body-bg, #fff);
        border: 1px solid var(--bs-border-color, rgba(0,0,0,0.08));
        border-radius: 16px;
        padding: 1.25rem;
    }

    .scenario-card-item {
        background: rgba(148, 163, 184, 0.06);
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 12px;
        padding: .75rem .95rem;
        cursor: pointer;
        transition: all .2s;
    }

    .scenario-card-item:hover {
        background: rgba(99, 102, 241, 0.08);
        border-color: rgba(99, 102, 241, 0.35);
        transform: translateY(-2px);
    }

    .scenario-card-item.active {
        background: rgba(99, 102, 241, 0.12);
        border-color: #6366f1;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }

    .scenario-card-item.completed {
        border-color: rgba(16, 185, 129, 0.4);
        background: rgba(16, 185, 129, 0.05);
    }

    .stats-strip {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: .5rem;
        background: rgba(148, 163, 184, 0.08);
        padding: .6rem;
        border-radius: 10px;
        text-align: center;
    }

    .stat-item-num {
        font-weight: 800;
        font-size: .95rem;
    }

    .stat-item-lbl {
        font-size: .65rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
    }

    .category-filter-chips {
        display: flex;
        gap: .35rem;
        overflow-x: auto;
        padding-bottom: .4rem;
        margin-bottom: .75rem;
        white-space: nowrap;
    }

    .cat-chip {
        padding: .2rem .65rem;
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: transparent;
        font-size: .72rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .15s;
        color: #64748b;
    }

    .cat-chip.active {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
    }

    .service-pill-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: .4rem;
        margin-top: .5rem;
    }

    .service-pill {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(148, 163, 184, 0.08);
        border: 1px solid rgba(148, 163, 184, 0.15);
        padding: .35rem .6rem;
        border-radius: 8px;
        font-size: .72rem;
        font-family: var(--term-font);
    }

    .service-pill .srv-name { font-weight: 600; }
    .service-pill.active { border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.08); }
    .service-pill.active .srv-status { color: #10b981; font-weight: 700; }
    .service-pill.inactive .srv-status { color: #ef4444; }

    /* Toast Notifications */
    #cliToastContainer {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .cli-toast {
        background: #1e1b4b;
        color: #fff;
        border-left: 4px solid #6366f1;
        padding: .85rem 1.25rem;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        font-size: .85rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        animation: slideInToast .3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideInToast {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>

        <main class="content-body" id="main-content">
            <div class="container-fluid p-3 p-md-4">

                <!-- Header Banner -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-terminal-fill text-primary"></i> Zaawansowany Symulator Terminala CLI & Zadania CKE
                        </h1>
                        <p class="text-muted mb-0">Pełne środowisko Linux (Bash/GNU) oraz Windows (CMD/PowerShell 7) z wirtualnym systemem plików VFS, edytorem Nano i 35+ zadaniami praktycznymi CKE.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 fw-bold px-3 py-2 rounded-pill">
                            <i class="bi bi-patch-check-fill me-1"></i>Wersja Beta 2.0
                        </span>
                        <a href="../ranking.php" class="btn btn-outline-warning btn-sm rounded-pill px-3">
                            <i class="bi bi-trophy me-1"></i> Ranking XP
                        </a>
                    </div>
                </div>

                <!-- Beta Notice Info Banner -->
                <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-warning bg-opacity-20 text-warning flex-shrink-0">
                        <i class="bi bi-info-circle-fill fs-4"></i>
                    </div>
                    <div class="small">
                        <strong class="d-block mb-1">Informacja o module CLI Lab (Wersja Beta):</strong>
                        <span class="text-body-secondary">Moduł symulatora terminala i weryfikacji zadań egzaminacyjnych jest obecnie w fazie <strong>Beta</strong>. Zadania są automatycznie sprawdzane w czasie rzeczywistym, a za ich ukończenie otrzymujesz punkty XP do profilu i rankingu.</span>
                    </div>
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
                                    <div class="dropdown d-inline-block">
                                        <button class="term-tool-btn dropdown-toggle" type="button" id="btnTermTheme" data-bs-toggle="dropdown" aria-expanded="false" title="Zmień motyw kolorystyczny">
                                            <i class="bi bi-palette"></i> Motyw
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="btnTermTheme" style="font-size:0.8rem;">
                                            <li><a class="dropdown-item active term-theme-opt" href="#" data-theme="default"><i class="bi bi-circle-fill text-primary me-2"></i>GitHub Dark (Domyślny)</a></li>
                                            <li><a class="dropdown-item term-theme-opt" href="#" data-theme="ubuntu"><i class="bi bi-circle-fill text-danger me-2"></i>Ubuntu Purple</a></li>
                                            <li><a class="dropdown-item term-theme-opt" href="#" data-theme="powershell"><i class="bi bi-circle-fill text-info me-2"></i>PowerShell Blue</a></li>
                                            <li><a class="dropdown-item term-theme-opt" href="#" data-theme="dracula"><i class="bi bi-circle-fill text-warning me-2"></i>Dracula Pro</a></li>
                                            <li><a class="dropdown-item term-theme-opt" href="#" data-theme="matrix"><i class="bi bi-circle-fill text-success me-2"></i>Matrix Green</a></li>
                                        </ul>
                                    </div>
                                    <button class="term-tool-btn" id="btnTermSearch" title="Szukaj w historii komend (Ctrl+R)">
                                        <i class="bi bi-search"></i>
                                    </button>
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

                            <!-- Terminal Tabs -->
                            <div class="terminal-tabs" id="terminalTabs">
                                <div class="term-tab active" data-tab="tab1" id="termTab1">
                                    <i class="bi bi-terminal"></i> <span>student@zsem-lab</span>
                                </div>
                                <div class="term-tab" data-tab="tab2" id="termTab2">
                                    <i class="bi bi-shield-lock text-danger"></i> <span>root@zsem-lab</span>
                                </div>
                                <div class="term-tab" data-tab="tab3" id="termTab3">
                                    <i class="bi bi-file-earmark-code text-warning"></i> <span>nano (edytor)</span>
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

                        <!-- ═══════════════════════════════════════════════════════════ -->
                        <!-- SCENARIO STEP CARD & PROGRESS (RICH STEP-BY-STEP WITH HIDE) -->
                        <!-- ═══════════════════════════════════════════════════════════ -->
                        <div class="scenario-guide-wrapper mt-3" id="scenarioGuideWrapper" style="display:none;">
                            
                            <!-- Collapsed Mini Bar (Shown when collapsed) -->
                            <div class="scenario-mini-bar card border-0 shadow-sm p-2 px-3 align-items-center justify-content-between flex-row" id="scenarioMiniBar" style="display:none; cursor:pointer;" onclick="if(window.zsemTerminal) window.zsemTerminal.toggleScenarioGuide(true);" title="Kliknij, aby rozwinąć pełną instrukcję zadania">
                                <div class="d-flex align-items-center gap-2 text-truncate">
                                    <span class="badge bg-primary bg-opacity-20 text-primary fw-bold" id="miniStepBadge">Krok 1/4</span>
                                    <span class="fw-semibold text-truncate small text-body" id="miniTitleLabel">Tytuł zadania</span>
                                    <span class="text-muted small d-none d-md-inline" id="miniInstructionSnippet">— Wpisz polecenie...</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2">
                                        <i class="bi bi-chevron-down me-1"></i>Pokaż instrukcję
                                    </button>
                                </div>
                            </div>

                            <!-- Full Step-by-Step Scenario Card -->
                            <div class="scenario-full-card card border-0 shadow-sm rounded-4 p-3 p-md-4" id="scenarioProgressWrap">
                                <!-- Top Bar: Title, Badges & Controls -->
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill" id="scenarioCatBadge">INF.02 Sieci</span>
                                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30 rounded-pill px-2 py-1" id="scenarioXpBadge">
                                            <i class="bi bi-trophy-fill me-1"></i>+35 XP
                                        </span>
                                        <span class="text-warning small" id="scenarioStars">★★★</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-1" id="btnToggleScenarioHint" onclick="if(window.zsemTerminal) window.zsemTerminal.toggleScenarioHint();" title="Pokaż / Ukryj podpowiedź merytoryczną">
                                            <i class="bi bi-lightbulb text-warning me-1"></i><span class="d-none d-sm-inline">Podpowiedź</span>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-1" id="btnPasteScenarioCmd" onclick="if(window.zsemTerminal) window.zsemTerminal.pasteCurrentStepCmd();" title="Wklej przykładowe polecenie do terminala">
                                            <i class="bi bi-clipboard-plus me-1"></i><span class="d-none d-sm-inline">Wklej komendę</span>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm rounded-pill px-2 py-1" id="btnScenarioSkipStep" onclick="if(window.zsemTerminal) window.zsemTerminal.skipScenarioStep();" title="Pomiń ten krok">
                                            <i class="bi bi-skip-forward-fill"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-1" id="btnToggleScenarioGuide" onclick="if(window.zsemTerminal) window.zsemTerminal.toggleScenarioGuide(false);" title="Zwiń / Ukryj instrukcję">
                                            <i class="bi bi-eye-slash me-1"></i><span class="d-none d-sm-inline">Zwiń</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h3 class="h6 fw-bold mb-0 text-primary d-flex align-items-center gap-2" id="scenarioProgressLabel">
                                        Tytuł zadania
                                    </h3>
                                    <span class="badge bg-primary bg-opacity-20 text-primary fw-bold" id="scenarioStepLabel">Krok 1/4</span>
                                </div>

                                <!-- Progress Bar -->
                                <div class="progress my-2" style="height:8px; border-radius:6px; background: rgba(148, 163, 184, 0.15);">
                                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" id="scenarioProgressBar" style="width:0%; transition:width .4s"></div>
                                </div>

                                <!-- Step Instruction Box -->
                                <div class="step-instruction-box p-3 rounded-3 mt-2" style="background: rgba(148, 163, 184, 0.08); border-left: 4px solid var(--bs-primary, #3b82f6);">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-arrow-right-circle-fill text-primary fs-5 mt-0.5"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-body mb-1" id="scenarioStepInstruction">
                                                Instrukcja kroku
                                            </div>
                                            <div class="small text-body-secondary mb-2" id="scenarioStepCkeDesc">
                                                Kontekst egzaminacyjny: co robimy w tym kroku i jakie umiejętności weryfikuje CKE.
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="small fw-semibold text-muted">Sugerowane polecenie:</span>
                                                <code class="px-2 py-1 rounded bg-body-secondary font-monospace text-primary fw-bold cursor-pointer" id="scenarioSuggestedCmd" onclick="if(window.zsemTerminal) window.zsemTerminal.pasteCurrentStepCmd();" title="Kliknij, aby wkleić do terminala">
                                                    polecenie
                                                </code>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Collapsible Hint Details -->
                                <div class="step-hint-box p-3 rounded-3 mt-2 alert alert-warning border-0 mb-0" id="scenarioHintBox" style="display:none;">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-lightbulb-fill text-warning fs-5"></i>
                                        <div>
                                            <strong class="d-block mb-1 text-dark">Wyjaśnienie i teoria egzaminacyjna:</strong>
                                            <div class="small text-body-secondary" id="scenarioHintText">
                                                Szczegółowe wyjaśnienie flag i parametrów polecenia.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Server Services Live Monitor Widget -->
                        <div class="mt-3 card border-0 shadow-sm p-3" style="background: var(--bs-body-bg, #fff);">
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
                                    <div class="stat-item-num" id="statScenCount">0/35</div>
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
                                <button type="button" class="cat-chip active" data-cat="all">Wszystkie (36)</button>
                                <button type="button" class="cat-chip" data-cat="inf02_srv">Serwery CKE</button>
                                <button type="button" class="cat-chip" data-cat="inf02_net">INF.02 Sieci</button>
                                <button type="button" class="cat-chip" data-cat="inf02_sys">INF.02 Systemy</button>
                                <button type="button" class="cat-chip" data-cat="inf03_db">INF.03 Bazy</button>
                                <button type="button" class="cat-chip" data-cat="inf08_sec">INF.08 Security</button>
                                <button type="button" class="cat-chip" data-cat="windows">Windows / PS</button>
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
                                <span class="badge bg-secondary bg-opacity-20 text-muted" id="commandCountBadge">200+ komend & man</span>
                            </h3>
                            <div id="commandList" style="font-size:.72rem; color:var(--text-muted,#64748b); line-height:1.75; max-height:140px; overflow-y:auto;">
                                <!-- Injected by JS -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
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
        <script src="<?= htmlspecialchars(assetUrl('assets/js/terminal_commands.js', '..')) ?>"></script>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Toast Container for Milestones & Achievements -->
<div id="cliToastContainer"></div>

</body>
</html>
