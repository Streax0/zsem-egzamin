<?php
/**
 * CLI Terminal Simulator — Linux & Windows
 *
 * Interactive terminal emulator for IT exam preparation.
 * Supports networking and system commands in both OS environments.
 */
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin(true);

$pageTitle = 'CLI Lab — Terminal Simulator | ZSEM Tech';
$base_url  = '../';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Interaktywny symulator terminala Linux i Windows do nauki poleceń sieciowych na egzamin CKE INF.02/INF.03">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/style.css', '..')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/dashboard-new.css', '..')) ?>">
    <script src="<?= htmlspecialchars(assetUrl('assets/js/theme-handler.js', '..')) ?>"></script>
    <style>
        :root {
            --term-bg: #0d1117;
            --term-text: #39d353;
            --term-prompt: #58a6ff;
            --term-error: #ff7b72;
            --term-warn: #e3b341;
            --term-dim: #8b949e;
            --term-white: #f0f6fc;
        }

        .cli-shell {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* Terminal Window */
        .terminal-window {
            background: var(--term-bg);
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.06);
            overflow: hidden;
            font-family: 'JetBrains Mono', 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
            font-size: .85rem;
            min-height: 420px;
            display: flex;
            flex-direction: column;
        }

        .terminal-titlebar {
            background: rgba(255,255,255,.06);
            padding: .5rem 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
            user-select: none;
        }

        .term-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .term-dot-red    { background: #ff5f57; }
        .term-dot-yellow { background: #ffbd2e; }
        .term-dot-green  { background: #28c840; }

        .terminal-os-toggle {
            margin-left: auto;
            display: flex;
            gap: .3rem;
        }

        .os-btn {
            padding: .2rem .65rem;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,.15);
            background: transparent;
            color: var(--term-dim);
            font-size: .72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
        }

        .os-btn.active {
            background: rgba(88,166,255,.2);
            border-color: rgba(88,166,255,.5);
            color: var(--term-prompt);
        }

        .terminal-output {
            flex: 1;
            padding: 1rem 1.2rem;
            overflow-y: auto;
            color: var(--term-text);
            line-height: 1.65;
            min-height: 340px;
            max-height: 340px;
        }

        .terminal-output::-webkit-scrollbar { width: 6px; }
        .terminal-output::-webkit-scrollbar-track { background: transparent; }
        .terminal-output::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 3px; }

        .term-line { white-space: pre-wrap; word-break: break-all; }
        .term-line.prompt  { color: var(--term-prompt); }
        .term-line.error   { color: var(--term-error); }
        .term-line.warn    { color: var(--term-warn); }
        .term-line.dim     { color: var(--term-dim); }
        .term-line.success { color: #39d353; }
        .term-line.white   { color: var(--term-white); }

        .terminal-input-row {
            display: flex;
            align-items: center;
            padding: .5rem 1.2rem .75rem;
            border-top: 1px solid rgba(255,255,255,.06);
            gap: .5rem;
        }

        .terminal-prompt-label {
            color: var(--term-prompt);
            white-space: nowrap;
            font-weight: 700;
            font-size: .85rem;
            flex-shrink: 0;
        }

        #termInput {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--term-white);
            font-family: inherit;
            font-size: .85rem;
            caret-color: var(--term-green, #39d353);
        }

        .blink-cursor::after {
            content: '█';
            animation: blink 1s step-end infinite;
            color: var(--term-green, #39d353);
            opacity: 1;
        }

        @keyframes blink { 50% { opacity: 0; } }

        /* Scenario Panel */
        .scenario-panel {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 12px;
            padding: 1.25rem;
            height: 100%;
        }

        .scenario-card {
            padding: 1rem;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            transition: all .2s;
            font-size: .82rem;
        }

        .scenario-card:hover {
            border-color: rgba(99,102,241,.35);
            background: rgba(99,102,241,.04);
        }

        .scenario-card.active {
            border-color: #6366f1;
            background: rgba(99,102,241,.08);
        }

        .scenario-card.completed {
            border-color: #10b981;
            background: rgba(16,185,129,.06);
        }

        .scenario-badge {
            font-size: .65rem;
            font-weight: 700;
            padding: .15rem .4rem;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .cli-hero {
            background: linear-gradient(135deg, rgba(13,17,23,.9) 0%, rgba(33,38,45,.8) 100%);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .cli-hero-title { color: #f0f6fc; font-weight: 700; font-size: 1.3rem; margin: 0; }
        .cli-hero-sub   { color: #8b949e; font-size: .82rem; margin: .25rem 0 0; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 cli-shell">

                <!-- Hero -->
                <div class="cli-hero">
                    <div>
                        <h1 class="cli-hero-title">
                            <i class="bi bi-terminal-fill me-2" style="color:#39d353"></i>CLI Lab — Symulator Terminala
                        </h1>
                        <p class="cli-hero-sub">Ćwicz polecenia sieciowe Linux i Windows w bezpiecznym środowisku symulacyjnym.</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-success bg-opacity-15 text-success fw-bold px-3 py-2">
                            <i class="bi bi-shield-check me-1"></i>Środowisko sandbox
                        </span>
                    </div>
                </div>

                <div class="row g-4">

                    <!-- Terminal Column -->
                    <div class="col-lg-8">
                        <div class="terminal-window" id="terminalWindow">
                            <!-- Title bar -->
                            <div class="terminal-titlebar">
                                <span class="term-dot term-dot-red"></span>
                                <span class="term-dot term-dot-yellow"></span>
                                <span class="term-dot term-dot-green"></span>
                                <span style="color:#8b949e;font-size:.75rem;margin-left:.5rem" id="termTitle">bash — student@zsem-lab: ~</span>
                                <div class="terminal-os-toggle">
                                    <button class="os-btn active" data-os="linux" id="osBtnLinux">
                                        <i class="bi bi-terminal me-1"></i>Linux
                                    </button>
                                    <button class="os-btn" data-os="windows" id="osBtnWin">
                                        <i class="bi bi-windows me-1"></i>Windows
                                    </button>
                                </div>
                            </div>

                            <!-- Output area -->
                            <div class="terminal-output" id="termOutput"></div>

                            <!-- Input row -->
                            <div class="terminal-input-row">
                                <span class="terminal-prompt-label" id="termPromptLabel">student@zsem-lab:~$</span>
                                <input type="text" id="termInput" autocomplete="off" autocorrect="off"
                                    autocapitalize="off" spellcheck="false"
                                    aria-label="Wprowadź polecenie terminala">
                            </div>
                        </div>

                        <!-- Scenario progress bar -->
                        <div class="mt-3" id="scenarioProgressWrap" style="display:none">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="fw-bold" id="scenarioProgressLabel"></small>
                                <small class="text-muted" id="scenarioStepLabel"></small>
                            </div>
                            <div class="progress" style="height:6px;border-radius:4px">
                                <div class="progress-bar bg-success" id="scenarioProgressBar" style="width:0%;transition:width .4s"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Scenario Panel Column -->
                    <div class="col-lg-4">
                        <div class="scenario-panel">
                            <h2 class="fw-bold fs-5 mb-3">
                                <i class="bi bi-list-check me-2 text-primary"></i>Scenariusze egzaminacyjne
                            </h2>
                            <div class="d-flex flex-column gap-2" id="scenarioList">
                                <!-- Injected by JS -->
                            </div>
                            <hr class="my-3">
                            <div id="activeScenarioDesc" style="font-size:.82rem;color:var(--text-muted,#64748b)">
                                <i class="bi bi-info-circle me-1"></i>Wybierz scenariusz, aby zobaczyć zadanie.
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-outline-secondary btn-sm rounded-pill" id="scenarioClearBtn">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Resetuj terminal
                                </button>
                                <button class="btn btn-outline-primary btn-sm rounded-pill ms-2" id="scenarioSkipBtn" style="display:none">
                                    <i class="bi bi-skip-forward me-1"></i>Pomiń
                                </button>
                            </div>

                            <hr class="my-3">
                            <h3 class="fw-bold fs-6 mb-2"><i class="bi bi-keyboard me-1 text-primary"></i>Dostępne komendy</h3>
                            <div id="commandList" style="font-size:.72rem;color:var(--text-muted,#64748b);line-height:1.8"></div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/theme-handler.js', '..')) ?>"></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/terminal_commands.js', '..')) ?>"></script>
</body>
</html>
