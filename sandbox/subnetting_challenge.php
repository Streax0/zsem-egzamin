<?php
/**
 * Subnetting Speed Challenge Mini-Game
 *
 * Timed subnetting trainer with dynamic question generation,
 * streak multipliers, XP rewards, and high-score table.
 */
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin(true);

$pageTitle = 'Subnetting Challenge — Mapa sieci | ZSEM Tech';
$base_url  = '../';

// High scores
$topScores = [];
try {
    $stmt = $pdo->prepare(
        "SELECT u.username, s.score, s.difficulty, s.achieved_at
         FROM subnetting_scores s
         JOIN users u ON u.id = s.user_id
         ORDER BY s.score DESC, s.achieved_at ASC
         LIMIT 10"
    );
    $stmt->execute();
    $topScores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Table may not exist yet — silently skip
    $topScores = [];
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Ćwicz obliczenia podsieci IPv4 pod egzamin CKE INF.02. Adresy sieciowe, rozgłoszeniowe, hosty — tryb czasowy ze streak multiplier.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/style.css', '..')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/dashboard-new.css', '..')) ?>">
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="<?= htmlspecialchars(assetUrl('assets/js/devtools-guard.js', '..')) ?>"></script>
    <script src="<?= htmlspecialchars(assetUrl('assets/js/theme-handler.js', '..')) ?>"></script>
    <style>
        .challenge-shell { max-width: 900px; margin: 0 auto; }

        .challenge-hero {
            background: linear-gradient(135deg, rgba(99,102,241,.1) 0%, rgba(139,92,246,.06) 100%);
            border: 1px solid rgba(99,102,241,.2);
            border-radius: 20px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Timer Bar */
        .timer-bar-wrap {
            height: 8px;
            background: rgba(0,0,0,.07);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .timer-bar-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #10b981, #6366f1);
            transition: width .95s linear, background .5s;
        }

        .timer-bar-fill.danger { background: linear-gradient(90deg, #ef4444, #dc2626); }

        /* Question Card */
        .question-card {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.05);
            position: relative;
            overflow: hidden;
        }

        .question-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
        }

        .network-display {
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', 'Consolas', monospace;
            color: #6366f1;
            text-align: center;
            letter-spacing: -.02em;
            padding: .75rem;
            background: rgba(99,102,241,.07);
            border-radius: 14px;
            margin-bottom: 1.5rem;
            user-select: all;
        }

        /* Answer fields */
        .answer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 576px) {
            .answer-grid { grid-template-columns: 1fr; }
            .network-display { font-size: 1.3rem; }
        }

        .answer-field-wrap { position: relative; }

        .answer-label {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            margin-bottom: .3rem;
        }

        .answer-input {
            font-family: 'JetBrains Mono', monospace;
            font-size: .9rem;
            padding: .6rem 1rem;
            border-radius: 10px;
            border: 2px solid var(--border-color, #e2e8f0);
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
            background: var(--input-bg, #fff);
            color: var(--text-color, #1e293b);
        }

        .answer-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.15);
        }

        .answer-input.correct { border-color: #10b981; background: rgba(16,185,129,.05); }
        .answer-input.wrong   { border-color: #ef4444; background: rgba(239,68,68,.05); }

        /* Streak & Score */
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .stat-pill {
            display: flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .9rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: .82rem;
        }

        .stat-pill.timer  { background: rgba(99,102,241,.1);  color: #6366f1; }
        .stat-pill.streak { background: rgba(239,68,68,.1);   color: #ef4444; }
        .stat-pill.score  { background: rgba(16,185,129,.1);  color: #10b981; }
        .stat-pill.xp     { background: rgba(245,158,11,.1);  color: #b45309; }

        /* Difficulty Tabs */
        .diff-tabs {
            display: flex;
            gap: .4rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .diff-tab {
            padding: .35rem .85rem;
            border-radius: 8px;
            border: 1px solid var(--border-color, #e2e8f0);
            background: transparent;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
        }

        .diff-tab.active    { background: #6366f1; color: #fff; border-color: #6366f1; }
        .diff-tab[data-diff="easy"]   { --c: #10b981; }
        .diff-tab[data-diff="medium"] { --c: #6366f1; }
        .diff-tab[data-diff="hard"]   { --c: #f59e0b; }
        .diff-tab[data-diff="expert"] { --c: #ef4444; }

        /* Result overlay */
        .result-overlay {
            position: absolute;
            inset: 0;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            font-size: 1.1rem;
            font-weight: 700;
            z-index: 5;
            opacity: 0;
            transition: opacity .3s;
            pointer-events: none;
        }

        .result-overlay.show { opacity: 1; pointer-events: all; }
        .result-overlay.correct-bg { background: rgba(16,185,129,.92); color: #fff; }
        .result-overlay.wrong-bg   { background: rgba(239,68,68,.9);   color: #fff; }

        /* Leaderboard */
        .lb-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem 1rem;
            border-radius: 10px;
            font-size: .82rem;
        }

        .lb-row:nth-child(odd) { background: rgba(0,0,0,.025); }
        .lb-rank { font-weight: 800; font-size: .9rem; min-width: 28px; text-align: center; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 challenge-shell">

                <!-- Hero -->
                <div class="challenge-hero">
                    <div>
                        <h1 class="fw-black mb-1" style="font-size:1.4rem">
                            <i class="bi bi-router-fill me-2 text-primary"></i>Subnetting Speed Challenge
                        </h1>
                        <p class="text-muted mb-0" style="font-size:.85rem">Oblicz parametry podsieci IPv4 jak najszybciej. Zdobywaj XP za serię poprawnych odpowiedzi!</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary rounded-pill px-4 fw-bold" id="startBtn">
                            <i class="bi bi-play-fill me-1"></i>Rozpocznij
                        </button>
                        <button class="btn btn-outline-secondary rounded-pill px-3 fw-bold" id="stopBtn" style="display:none">
                            <i class="bi bi-stop-fill me-1"></i>Stop
                        </button>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Challenge Column -->
                    <div class="col-lg-8">

                        <!-- Difficulty -->
                        <div class="diff-tabs" role="group" aria-label="Poziom trudności">
                            <button class="diff-tab" data-diff="easy">Łatwy  <span class="badge bg-success ms-1">C</span></button>
                            <button class="diff-tab active" data-diff="medium">Średni <span class="badge bg-primary ms-1">B</span></button>
                            <button class="diff-tab" data-diff="hard">Trudny <span class="badge bg-warning text-dark ms-1">A</span></button>
                            <button class="diff-tab" data-diff="expert">Expert <span class="badge bg-danger ms-1">★</span></button>
                            <button class="diff-tab" data-diff="vlsm">VLSM CKE <span class="badge bg-info ms-1">INF.02</span></button>
                            <button class="diff-tab" data-diff="ipv6">IPv6 <span class="badge bg-secondary ms-1">::/64</span></button>
                        </div>

                        <!-- Stats row -->
                        <div class="stats-row">
                            <div class="stat-pill timer"><i class="bi bi-stopwatch-fill"></i><span id="timerDisplay">60s</span></div>
                            <div class="stat-pill streak"><i class="bi bi-fire"></i>Seria: <span id="streakDisplay">0</span> <span id="multiplierDisplay"></span></div>
                            <div class="stat-pill score"><i class="bi bi-trophy-fill"></i>Wynik: <span id="scoreDisplay">0</span></div>
                            <div class="stat-pill xp"><i class="bi bi-star-fill"></i>XP: +<span id="xpDisplay">0</span></div>
                        </div>

                        <!-- Timer bar -->
                        <div class="timer-bar-wrap">
                            <div class="timer-bar-fill" id="timerBar" style="width:100%"></div>
                        </div>

                        <!-- Question Card -->
                        <div class="question-card" id="questionCard">
                            <div class="result-overlay" id="resultOverlay">
                                <div id="resultIcon" style="font-size:3rem"></div>
                                <div id="resultMsg"></div>
                            </div>

                            <div id="startMessage" class="text-center py-4">
                                <i class="bi bi-router display-4 text-primary opacity-50 mb-3"></i>
                                <p class="fw-bold fs-5">Gotowy na wyzwanie?</p>
                                <p class="text-muted">Wybierz poziom trudności i kliknij <strong>Rozpocznij</strong>.</p>
                            </div>

                            <div id="questionContent" style="display:none">
                                <!-- Network display -->
                                <div class="network-display" id="networkDisplay" aria-live="polite">---.---.---/--</div>

                                <!-- Answer fields -->
                                <form id="answerForm" autocomplete="off">
                                    <div class="answer-grid mb-3">
                                        <div class="answer-field-wrap">
                                            <div class="answer-label">Adres sieci</div>
                                            <input class="answer-input" id="ans_network" placeholder="np. 192.168.1.0"
                                                type="text" pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}"
                                                aria-label="Adres sieci">
                                        </div>
                                        <div class="answer-field-wrap">
                                            <div class="answer-label">Adres rozgłoszeniowy</div>
                                            <input class="answer-input" id="ans_broadcast" placeholder="np. 192.168.1.255"
                                                type="text" aria-label="Adres rozgłoszeniowy">
                                        </div>
                                        <div class="answer-field-wrap">
                                            <div class="answer-label">Pierwszy host</div>
                                            <input class="answer-input" id="ans_first" placeholder="np. 192.168.1.1"
                                                type="text" aria-label="Pierwszy użyteczny host">
                                        </div>
                                        <div class="answer-field-wrap">
                                            <div class="answer-label">Ostatni host</div>
                                            <input class="answer-input" id="ans_last" placeholder="np. 192.168.1.254"
                                                type="text" aria-label="Ostatni użyteczny host">
                                        </div>
                                    </div>
                                    <div class="answer-field-wrap mb-3" style="max-width:220px">
                                        <div class="answer-label">Liczba hostów</div>
                                        <input class="answer-input" id="ans_hosts" placeholder="np. 254"
                                            type="number" min="0" aria-label="Liczba użytecznych hostów">
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold" id="submitBtn">
                                        <i class="bi bi-check2-circle me-1"></i>Sprawdź
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>

                    <!-- Leaderboard Column -->
                    <div class="col-lg-4">
                        <div style="background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:16px;padding:1.25rem">
                            <h2 class="fw-bold fs-5 mb-3">
                                <i class="bi bi-trophy-fill me-2 text-warning"></i>Hall of Fame
                            </h2>
                            <div id="leaderboardBody">
                                <?php if (empty($topScores)): ?>
                                    <div class="text-muted text-center py-3" style="font-size:.82rem">
                                        <i class="bi bi-award me-1"></i>Brak wyników — bądź pierwszy!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($topScores as $i => $row): ?>
                                        <div class="lb-row">
                                            <span class="lb-rank"><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '#'.($i+1))) ?></span>
                                            <span class="fw-bold flex-fill"><?= htmlspecialchars($row['username']) ?></span>
                                            <span class="badge bg-primary bg-opacity-15 text-primary"><?= (int)$row['score'] ?> XP</span>
                                            <span class="badge bg-secondary bg-opacity-10 text-muted"><?= htmlspecialchars($row['difficulty']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <hr class="my-3">
                            <h3 class="fw-bold fs-6 mb-2"><i class="bi bi-lightbulb me-1 text-warning"></i>Wskazówki</h3>
                            <ul style="font-size:.78rem;color:var(--text-muted,#64748b);padding-left:1.1rem;line-height:2">
                                <li>Maska /24 = 255.255.255.0</li>
                                <li>Hosty = 2^(32-CIDR) - 2</li>
                                <li>Network = IP AND Maska</li>
                                <li>Broadcast = Network OR NOT Maska</li>
                                <li>Seria x3 = potrójne XP!</li>
                            </ul>
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
<script>
(function () {
    'use strict';

    // ── State ──────────────────────────────────────────────────────────────────
    let difficulty   = 'medium';
    let timeLeft     = 60;
    let streak       = 0;
    let totalScore   = 0;
    let totalXp      = 0;
    let timerHandle  = null;
    let running      = false;
    let currentQ     = null;
    let resultTimer  = null;

    const DIFFICULTY_TIME = { easy: 90, medium: 60, hard: 45, expert: 30 };
    const DIFFICULTY_XP   = { easy: 5,  medium: 10, hard: 20, expert: 35 };

    // ── DOM ────────────────────────────────────────────────────────────────────
    const startBtn      = document.getElementById('startBtn');
    const stopBtn       = document.getElementById('stopBtn');
    const timerDisplay  = document.getElementById('timerDisplay');
    const timerBar      = document.getElementById('timerBar');
    const streakDisplay = document.getElementById('streakDisplay');
    const multiplierEl  = document.getElementById('multiplierDisplay');
    const scoreDisplay  = document.getElementById('scoreDisplay');
    const xpDisplay     = document.getElementById('xpDisplay');
    const networkDisplay = document.getElementById('networkDisplay');
    const startMsg      = document.getElementById('startMessage');
    const questionContent = document.getElementById('questionContent');
    const answerForm    = document.getElementById('answerForm');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultIcon    = document.getElementById('resultIcon');
    const resultMsg     = document.getElementById('resultMsg');

    // ── Difficulty tabs ────────────────────────────────────────────────────────
    document.querySelectorAll('.diff-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            if (running) return;
            document.querySelectorAll('.diff-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            difficulty = btn.dataset.diff;
        });
    });

    // ── Subnet Math ────────────────────────────────────────────────────────────
    function generateQuestion() {
        if (difficulty === 'ipv6') {
            const prefixes = ['2001:db8:acad:', 'fe80::', '2001:4860:4860::', '2001:0db8:85a3:'];
            const pfx = prefixes[Math.floor(Math.random() * prefixes.length)];
            const hextet = Math.floor(Math.random() * 65535).toString(16);
            const rawIp = `${pfx}${hextet}`;
            return {
                ip: rawIp,
                cidr: 64,
                networkIp: pfx.endsWith('::') ? pfx : pfx + '::',
                correct: {
                    network: pfx.endsWith('::') ? pfx : pfx + '::',
                    broadcast: 'N/A (Multicast/Anycast)',
                    first_host: `${pfx}${hextet}:1`,
                    last_host: `${pfx}ffff:ffff:ffff:ffff`,
                    host_count: '18446744073709551616',
                }
            };
        }

        const cidrRanges = {
            easy:   [24, 25, 26],
            medium: [20, 22, 24, 25, 26, 27, 28],
            hard:   [16, 18, 20, 22, 23, 24, 25, 26, 27, 28, 29],
            expert: [8, 10, 12, 14, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
            vlsm:   [25, 26, 27, 28, 29, 30],
        };
        const cidrs = cidrRanges[difficulty] || cidrRanges.medium;
        const cidr  = cidrs[Math.floor(Math.random() * cidrs.length)];

        // Generate random valid host IP
        const o1 = [10, 172, 192][Math.floor(Math.random()*3)];
        const o2 = o1 === 172 ? (16 + Math.floor(Math.random()*16)) : Math.floor(Math.random()*256);
        const o3 = Math.floor(Math.random() * 256);
        const o4 = Math.floor(Math.random() * 255) + 1;
        const ip  = `${o1}.${o2}.${o3}.${o4}`;

        // Calculate
        const ipNum    = ipToLong(ip);
        const maskNum  = cidr > 0 ? ((0xFFFFFFFF << (32-cidr)) >>> 0) : 0;
        const netNum   = (ipNum & maskNum) >>> 0;
        const bcastNum = (netNum | (~maskNum >>> 0)) >>> 0;

        let firstHost = netNum + 1;
        let lastHost  = bcastNum - 1;
        let hostCount = bcastNum - netNum - 1;

        if (cidr === 32) { firstHost = netNum; lastHost = netNum; hostCount = 1; }
        if (cidr === 31) { firstHost = netNum; lastHost = bcastNum; hostCount = 2; }

        return {
            ip, cidr,
            networkIp: longToIp(netNum),
            correct: {
                network:    longToIp(netNum),
                broadcast:  longToIp(bcastNum),
                first_host: longToIp(firstHost),
                last_host:  longToIp(lastHost),
                host_count: String(Math.max(0, hostCount)),
            }
        };
    }

    function ipToLong(ip) {
        return ip.split('.').reduce((acc, o) => (acc * 256 + parseInt(o, 10)) >>> 0, 0);
    }

    function longToIp(n) {
        return [(n >>> 24) & 0xFF, (n >>> 16) & 0xFF, (n >>> 8) & 0xFF, n & 0xFF].join('.');
    }

    // ── Game Control ───────────────────────────────────────────────────────────
    function startGame() {
        running    = true;
        streak     = 0;
        totalScore = 0;
        totalXp    = 0;
        timeLeft   = DIFFICULTY_TIME[difficulty] || 60;

        startBtn.style.display = 'none';
        stopBtn.style.display  = '';
        startMsg.style.display = 'none';
        questionContent.style.display = '';

        updateStats();
        nextQuestion();
        startTimer();
    }

    function stopGame(timeout = false) {
        running = false;
        clearInterval(timerHandle);
        timerHandle = null;

        startBtn.style.display = '';
        stopBtn.style.display  = 'none';

        if (timeout) {
            showResult(false, `⏱️ Czas minął! Wynik: ${totalScore} pkt / +${totalXp} XP`);
            setTimeout(() => {
                resultOverlay.classList.remove('show','correct-bg','wrong-bg');
                startMsg.style.display = '';
                questionContent.style.display = 'none';
            }, 3000);
        }
    }

    function startTimer() {
        clearInterval(timerHandle);
        updateTimerUI();
        timerHandle = setInterval(() => {
            timeLeft--;
            updateTimerUI();
            if (timeLeft <= 0) stopGame(true);
        }, 1000);
    }

    function updateTimerUI() {
        if (timerDisplay) timerDisplay.textContent = timeLeft + 's';
        const maxTime = DIFFICULTY_TIME[difficulty] || 60;
        const pct     = (timeLeft / maxTime) * 100;
        if (timerBar) {
            timerBar.style.width = pct + '%';
            timerBar.classList.toggle('danger', timeLeft <= 10);
        }
    }

    function nextQuestion() {
        currentQ = generateQuestion();
        if (networkDisplay) networkDisplay.textContent = `${currentQ.ip}/${currentQ.cidr}`;
        clearAnswerFields();
        resultOverlay.classList.remove('show','correct-bg','wrong-bg');
        document.getElementById('ans_network')?.focus();
    }

    function clearAnswerFields() {
        ['ans_network','ans_broadcast','ans_first','ans_last','ans_hosts'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.remove('correct','wrong'); }
        });
    }

    function updateStats() {
        const mult = getMultiplier();
        if (streakDisplay) streakDisplay.textContent = streak;
        if (multiplierEl)  multiplierEl.textContent  = mult > 1 ? `×${mult}` : '';
        if (scoreDisplay)  scoreDisplay.textContent  = totalScore;
        if (xpDisplay)     xpDisplay.textContent     = totalXp;
    }

    function getMultiplier() {
        if (streak >= 10) return 4;
        if (streak >= 5)  return 3;
        if (streak >= 3)  return 2;
        return 1;
    }

    // ── Form Submit ────────────────────────────────────────────────────────────
    answerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!running || !currentQ) return;

        const answers = {
            network:    document.getElementById('ans_network')?.value.trim()  || '',
            broadcast:  document.getElementById('ans_broadcast')?.value.trim() || '',
            first_host: document.getElementById('ans_first')?.value.trim()    || '',
            last_host:  document.getElementById('ans_last')?.value.trim()     || '',
            host_count: document.getElementById('ans_hosts')?.value.trim()    || '',
        };

        // Client-side validation (fast feedback)
        const correct = currentQ.correct;
        let allRight  = true;

        Object.entries(answers).forEach(([k, v]) => {
            const inputId = { network:'ans_network', broadcast:'ans_broadcast',
                              first_host:'ans_first', last_host:'ans_last', host_count:'ans_hosts' }[k];
            const input   = document.getElementById(inputId);
            const ok      = v.toLowerCase() === correct[k].toLowerCase();
            if (input) input.classList.add(ok ? 'correct' : 'wrong');
            if (!ok) allRight = false;
        });

        if (allRight) {
            streak++;
            const mult  = getMultiplier();
            const xp    = (DIFFICULTY_XP[difficulty] || 10) * mult;
            totalXp    += xp;
            totalScore += 100 * mult;

            showResult(true, `✅ Poprawnie! +${xp} XP ${mult > 1 ? `(×${mult} seria!)` : ''}`);

            // Server-side sync (async, non-blocking)
            const fd = new FormData();
            fd.append('network_ip', currentQ.networkIp);
            fd.append('cidr', currentQ.cidr);
            fd.append('difficulty', difficulty);
            fd.append('answer_network',    answers.network);
            fd.append('answer_broadcast',  answers.broadcast);
            fd.append('answer_first_host', answers.first_host);
            fd.append('answer_last_host',  answers.last_host);
            fd.append('answer_host_count', answers.host_count);
            fetch('../actions/subnetting_submit.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .catch(() => {});

            setTimeout(nextQuestion, 1500);
        } else {
            streak = 0;
            showResult(false, `❌ Błąd. Poprawne odpowiedzi poniżej.`);
            // Show correct answers in inputs
            Object.entries(correct).forEach(([k, v]) => {
                const inputId = { network:'ans_network', broadcast:'ans_broadcast',
                                  first_host:'ans_first', last_host:'ans_last', host_count:'ans_hosts' }[k];
                const input = document.getElementById(inputId);
                if (input && input.classList.contains('wrong')) {
                    input.placeholder = v;
                }
            });
            setTimeout(nextQuestion, 2500);
        }

        updateStats();
    });

    function showResult(ok, msg) {
        clearTimeout(resultTimer);
        resultOverlay.classList.remove('show','correct-bg','wrong-bg');
        resultOverlay.classList.add('show', ok ? 'correct-bg' : 'wrong-bg');
        if (resultIcon) resultIcon.textContent = ok ? '🎉' : '❌';
        if (resultMsg)  resultMsg.textContent  = msg;
        resultTimer = setTimeout(() => resultOverlay.classList.remove('show','correct-bg','wrong-bg'), 1400);
    }

    // ── Buttons ────────────────────────────────────────────────────────────────
    startBtn?.addEventListener('click', startGame);
    stopBtn?.addEventListener('click', () => stopGame(false));

}());
</script>
</body>
</html>
