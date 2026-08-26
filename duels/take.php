<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();
ensureDuelModeColumns($pdo);

$myId = $_SESSION['user_id'];
$duelId = (int)($_GET['id'] ?? 0);

// Load duel
$stmt = $pdo->prepare("
    SELECT d.id, d.challenger_id, d.opponent_id, d.category, d.question_count, d.question_ids, d.mode, d.preset, d.stake_xp, d.underdog_bonus, d.time_per_question_seconds, d.total_time_seconds, d.require_answer_confirmation, d.allow_early_finish, d.status, d.challenger_score_percent, d.opponent_score_percent, d.challenger_time_spent, d.opponent_time_spent, d.challenger_finished_at, d.opponent_finished_at, d.challenger_started_at, d.opponent_started_at, d.challenger_hidden_at, d.opponent_hidden_at, d.winner_id, d.revenge_parent_id, d.expires_at, d.created_at, u1.username as challenger_name, u2.username as opponent_name 
    FROM duels d 
    JOIN users u1 ON d.challenger_id = u1.id 
    JOIN users u2 ON d.opponent_id = u2.id 
    WHERE d.id = ? AND (d.challenger_id = ? OR d.opponent_id = ?)
");
$stmt->execute([$duelId, $myId, $myId]);
$duel = $stmt->fetch();

if (!$duel) {
    setSessionMessage('error', 'Pojedynek nie istnieje.');
    redirect('../index.php');
}
$modeLabel = ['classic' => 'Classic', 'underdog' => '🔥 Underdog Mode', 'all_in' => '⚡ All-In Duel'][$duel['mode'] ?? 'classic'] ?? 'Classic';
if ($duel['status'] === 'pending') {
    setSessionMessage('info', 'Pojedynek nadal oczekuje na akceptację.');
    redirect('lobby.php?id=' . $duelId);
}

if ($duel['status'] === 'declined') {
    setSessionMessage('error', 'Pojedynek został odrzucony.');
    redirect('../index.php');
}

if ($duel['status'] === 'expired') {
    setSessionMessage('error', 'Pojedynek wygasł.');
    redirect('../index.php');
}

if ($duel['status'] !== 'accepted') {
    setSessionMessage('error', 'Nie można rozpocząć tego pojedynku.');
    redirect('../index.php');
}
// Check if already finished by this user
$isChallenger = ($duel['challenger_id'] == $myId);
if (($isChallenger && $duel['challenger_finished_at']) || (!$isChallenger && $duel['opponent_finished_at'])) {
    redirect('results.php?id=' . $duelId);
}

// Prepare stable questions for this user's duel attempt.
$sessionQuestionKey = 'duel_questions_' . $duelId;
$duelQuestionIds = !empty($duel['question_ids']) ? json_decode($duel['question_ids'], true) : null;
if (is_array($duelQuestionIds) && !empty($duelQuestionIds)) {
    $questions = getQuestionsByIds($pdo, $duelQuestionIds);
    $_SESSION[$sessionQuestionKey] = array_column($questions, 'id');
} elseif (!empty($_SESSION[$sessionQuestionKey]) && is_array($_SESSION[$sessionQuestionKey])) {
    $questions = getQuestionsByIds($pdo, $_SESSION[$sessionQuestionKey]);
} else {
    $allQ = loadQuestions($pdo);
    $filtered = array_filter($allQ, fn($q) => $q['category'] === $duel['category']);
    if (empty($filtered)) $filtered = $allQ; // fallback
    shuffle($filtered);
    $questions = array_slice($filtered, 0, $duel['question_count']);
    $_SESSION[$sessionQuestionKey] = array_column($questions, 'id');
}

if (empty($questions)) {
    setSessionMessage('error', 'Brak pytań dla tego pojedynku.');
    redirect('../index.php');
}

$perQuestionLimit = !empty($duel['time_per_question_seconds']) ? (int)$duel['time_per_question_seconds'] : 0;
$totalTimeLimit = !empty($duel['total_time_seconds']) ? (int)$duel['total_time_seconds'] : 0;
$requireConfirmation = !empty($duel['require_answer_confirmation']);
$allowEarlyFinish = !empty($duel['allow_early_finish']);

$duelStartedAt = ensureDuelParticipantStarted($pdo, $duelId, $isChallenger);
$serverNow = time();
$elapsedSeconds = max(0, $serverNow - $duelStartedAt);

$answeredQuestionIds = [];
try {
    $answeredStmt = $pdo->prepare("SELECT question_id FROM duel_answers WHERE duel_id = ? AND user_id = ?");
    $answeredStmt->execute([$duelId, $myId]);
    $answeredQuestionIds = array_map('intval', $answeredStmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    error_log('Duel answered ids load failed: ' . $e->getMessage());
}
$answeredCount = count($answeredQuestionIds);
if ($answeredCount >= count($questions)) {
    redirect('results.php?id=' . $duelId);
}
$initialStep = min($answeredCount, max(0, count($questions) - 1));
$initialProgressPct = count($questions) > 0 ? round((($initialStep + 1) / count($questions)) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pojedynek: <?= htmlspecialchars($duel['challenger_name']) ?> vs <?= htmlspecialchars($duel['opponent_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <link rel="stylesheet" href="../assets/css/duels.css">
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="../assets/js/devtools-guard.js"></script>
    <script src="../assets/js/theme-handler.js"></script>
</head>
<body class="duel-live duel-page-shell">
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body duel-live-main">
                <div class="duel-arena-header text-center">
                    <div class="duel-arena-badge mb-2"><i class="bi bi-lightning-charge-fill me-1"></i> Pojedynek na żywo</div>
                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                        <span class="duel-player-name"><?= htmlspecialchars($duel['challenger_name']) ?></span>
                        <span class="duel-vs-pill">VS</span>
                        <span class="duel-player-name"><?= htmlspecialchars($duel['opponent_name']) ?></span>
                    </div>
                    <div class="duel-arena-meta mt-2">
                        <?= htmlspecialchars($duel['category']) ?> • <?= htmlspecialchars($modeLabel) ?>
                        <?php if (($duel['mode'] ?? '') === 'all_in'): ?> • Stawka <?= (int)$duel['stake_xp'] ?> XP<?php endif; ?>
                    </div>
                </div>

                <div class="container-fluid py-3 px-3 px-lg-4">
                    <div class="row justify-content-center">
                        <div class="col-xl-9 col-lg-10">
                            <div class="dashboard-panel duel-question-card shadow-lg animate-in p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-start mb-3 gap-3 flex-wrap">
                                    <div>
                                        <div class="duel-step-label text-uppercase">Postęp</div>
                                        <div class="badge bg-primary bg-opacity-10 text-primary fs-6 px-3 py-2 mt-1">
                                            Pytanie <span id="currentIdx"><?= $initialStep + 1 ?></span> z <?= count($questions) ?>
                                        </div>
                                        <div id="opponentLiveProgress" class="small text-muted mt-1">
                                            <i class="bi bi-person-badge me-1"></i>Rywal: <span id="oppAnsweredCount" class="fw-bold">0</span>/<?= count($questions) ?>
                                            <span id="oppStatusPill" class="badge bg-secondary bg-opacity-10 text-secondary ms-1">W grze</span>
                                        </div>
                                    </div>
                                    <div class="duel-timer-stack text-end">
                                        <div class="duel-timer-label">Czas gry</div>
                                        <div id="timer" class="duel-timer-value">00:00</div>
                                        <?php if ($perQuestionLimit): ?><div id="questionTimer" class="duel-question-timer"><?= $perQuestionLimit ?>s na pytanie</div><?php endif; ?>
                                        <?php if ($totalTimeLimit): ?><div id="totalTimer" class="duel-total-timer"></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="duel-progress-track mb-4" aria-hidden="true">
                                    <span id="duelProgressBar" style="width:<?= (int)$initialProgressPct ?>%"></span>
                                </div>

                                <div id="quizContent">
                        <?php foreach ($questions as $idx => $q): ?>
                            <div class="question-step" id="step_<?= $idx ?>" style="display: <?= $idx === $initialStep ? 'block' : 'none' ?>;">
                                <h4 class="duel-question-text mb-4"><?= htmlspecialchars($q['question_text']) ?></h4>
                                <?php $duelQuestionImage = questionImageSrc($q['image_url'] ?? '', '../'); ?>
                                <?php if ($duelQuestionImage): ?>
                                    <img src="<?= htmlspecialchars($duelQuestionImage) ?>" class="img-fluid rounded-4 mb-4 border shadow-sm" alt="Ilustracja do pytania: <?= htmlspecialchars(mb_substr($q['question_text'] ?? 'pytanie pojedynku', 0, 90)) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                <?php endif; ?>
                                <div class="options-list">
                                    <button type="button" class="duel-option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'A')">
                                        <span class="badge bg-light text-dark me-2">A</span> <?= htmlspecialchars($q['option_a']) ?>
                                    </button>
                                    <button type="button" class="duel-option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'B')">
                                        <span class="badge bg-light text-dark me-2">B</span> <?= htmlspecialchars($q['option_b']) ?>
                                    </button>
                                    <button type="button" class="duel-option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'C')">
                                        <span class="badge bg-light text-dark me-2">C</span> <?= htmlspecialchars($q['option_c']) ?>
                                    </button>
                                    <button type="button" class="duel-option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'D')">
                                        <span class="badge bg-light text-dark me-2">D</span> <?= htmlspecialchars($q['option_d']) ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="duelConfirmPanel" class="duel-confirm-panel mt-3" hidden>
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="fw-bold" id="duelConfirmTitle">Potwierdź akcję</div>
                                <div class="small text-muted" id="duelConfirmText"></div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light border rounded-pill px-3" id="duelConfirmCancel">Anuluj</button>
                                <button type="button" class="btn btn-primary rounded-pill px-3" id="duelConfirmAccept">Potwierdź</button>
                            </div>
                        </div>
                    </div>

                    <div id="saving" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary mb-3"></div>
                        <p class="text-muted">Zapisywanie wyniku...</p>
                    </div>
                    <?php if ($allowEarlyFinish): ?>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-outline-danger rounded-pill" onclick="finishDuel(true)">
                                <i class="bi bi-flag-fill me-1"></i>Zakończ wcześniej
                            </button>
                        </div>
                    <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        let currentStep = <?= (int)$initialStep ?>;
        const totalSteps = <?= count($questions) ?>;
        const duelId = <?= $duelId ?>;
        const csrfToken = <?= json_encode(generateCsrfToken()) ?>;
        const requireConfirmation = <?= $requireConfirmation ? 'true' : 'false' ?>;
        const allowEarlyFinish = <?= $allowEarlyFinish ? 'true' : 'false' ?>;
        const perQuestionLimit = <?= (int)$perQuestionLimit ?>;
        const totalTimeLimit = <?= (int)$totalTimeLimit ?>;
        const serverElapsedSeconds = <?= (int)$elapsedSeconds ?>;
        const serverNowMs = <?= (int)$serverNow * 1000 ?>;
        const answered = new Set(<?= json_encode($answeredQuestionIds) ?>);
        const clockOffsetMs = Date.now() - serverNowMs;
        let startTime = Date.now() - clockOffsetMs - (serverElapsedSeconds * 1000);
        let questionStartTime = Date.now();
        let questionTimerInterval = null;
        let timerInterval = setInterval(updateTimer, 1000);
        if (perQuestionLimit > 0) questionTimerInterval = setInterval(updateQuestionTimer, 500);
        updateTimer();
        if (perQuestionLimit > 0) updateQuestionTimer();

        function getElapsedSeconds() {
            return Math.max(0, Math.floor((Date.now() - clockOffsetMs - startTime) / 1000));
        }

        function updateTimer() {
            const elapsed = getElapsedSeconds();
            if (totalTimeLimit > 0) {
                const totalNode = document.getElementById('totalTimer');
                const remaining = Math.max(0, totalTimeLimit - elapsed);
                const tm = Math.floor(remaining / 60).toString().padStart(2, '0');
                const ts = (remaining % 60).toString().padStart(2, '0');
                if (totalNode) totalNode.innerText = `Limit całego testu: ${tm}:${ts}`;
                if (remaining <= 0) finishDuel(true, true);
            }
            const m = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const s = (elapsed % 60).toString().padStart(2, '0');
            document.getElementById('timer').innerText = `${m}:${s}`;
        }

        function updateQuestionTimer() {
            if (perQuestionLimit <= 0) return;
            const elapsed = Math.floor((Date.now() - questionStartTime) / 1000);
            const remaining = Math.max(0, perQuestionLimit - elapsed);
            const node = document.getElementById('questionTimer');
            if (node) node.innerText = `${remaining}s`;
            if (remaining <= 0) nextQuestionOrFinish();
        }

        function nextQuestionOrFinish() {
            if (currentStep < totalSteps - 1) {
                document.getElementById(`step_${currentStep}`).style.display = 'none';
                currentStep++;
                questionStartTime = Date.now();
                document.getElementById(`step_${currentStep}`).style.display = 'block';
                document.getElementById('currentIdx').innerText = currentStep + 1;
                const progressBar = document.getElementById('duelProgressBar');
                if (progressBar) {
                    progressBar.style.width = `${Math.round(((currentStep + 1) / totalSteps) * 100)}%`;
                }
            } else {
                finishDuel(true, true);
            }
        }

        const confirmPanel = document.getElementById('duelConfirmPanel');
        const confirmTitle = document.getElementById('duelConfirmTitle');
        const confirmText = document.getElementById('duelConfirmText');
        const confirmAccept = document.getElementById('duelConfirmAccept');
        const confirmCancel = document.getElementById('duelConfirmCancel');
        let pendingConfirmAction = null;

        function showDuelConfirm(title, text, buttonText, action) {
            pendingConfirmAction = action;
            confirmTitle.textContent = title;
            confirmText.textContent = text;
            confirmAccept.textContent = buttonText || 'Potwierdź';
            confirmPanel.hidden = false;
            confirmAccept.focus();
        }

        function hideDuelConfirm() {
            pendingConfirmAction = null;
            confirmPanel.hidden = true;
        }

        confirmCancel.addEventListener('click', hideDuelConfirm);
        confirmAccept.addEventListener('click', () => {
            const action = pendingConfirmAction;
            hideDuelConfirm();
            if (action) action();
        });

        function saveAnswer(event, idx, qId, ans) {
            const button = event.currentTarget;
            if (requireConfirmation) {
                showDuelConfirm('Potwierdź odpowiedź', `Wybrano odpowiedź ${ans}. Po potwierdzeniu nie będzie można jej zmienić.`, 'Zapisz odpowiedź', () => submitAnswer(button, idx, qId, ans));
                return;
            }
            submitAnswer(button, idx, qId, ans);
        }

        async function submitAnswer(button, idx, qId, ans) {
            const timeTaken = getElapsedSeconds();
            
            // Highlight selection locally
            const btns = document.querySelectorAll(`#step_${idx} .duel-option-btn`);
            btns.forEach(b => b.classList.remove('selected'));
            button.classList.add('selected');

            // Send to server
            try {
                const formData = new FormData();
                formData.append('duel_id', duelId);
                formData.append('question_id', qId);
                formData.append('answer', ans);
                formData.append('time_spent', timeTaken);
                formData.append('csrf_token', csrfToken);

                const answerResponse = await fetch('save_answer.php', { method: 'POST', body: formData });
                const answerData = await answerResponse.json();
                if (!answerData.success) {
                    showDuelConfirm('Błąd', answerData.message || 'Nie udało się zapisać odpowiedzi.', 'OK', () => {});
                    return;
                }
                answered.add(qId);

                nextQuestionOrFinish();
            } catch (e) {
                showDuelConfirm('Błąd połączenia', 'Spróbuj ponownie za chwilę.', 'OK', () => {});
            }
        }

        async function finishDuel(early = false, forced = false) {
            if (early && !allowEarlyFinish && answered.size < totalSteps) {
                showDuelConfirm('Nie można zakończyć', 'Ten pojedynek wymaga odpowiedzi na wszystkie pytania.', 'OK', () => {});
                return;
            }
            if (early && allowEarlyFinish && !forced && answered.size < totalSteps) {
                showDuelConfirm('Zakończyć pojedynek?', 'Nieudzielone odpowiedzi zostaną policzone jako błędne.', 'Zakończ pojedynek', () => finishDuel(true, true));
                return;
            }
            clearInterval(timerInterval);
            if (questionTimerInterval) clearInterval(questionTimerInterval);
            document.getElementById('quizContent').style.display = 'none';
            document.getElementById('saving').style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('id', duelId);
                formData.append('time_spent', getElapsedSeconds());
                formData.append('early_finish', early ? '1' : '0');
                formData.append('csrf_token', csrfToken);

                const response = await fetch('finish.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    window.location = 'results.php?id=' + duelId;
                } else {
                    showDuelConfirm('Błąd', data.message || 'Nie udało się zakończyć pojedynku.', 'OK', () => {});
                }
            } catch (e) {
                showDuelConfirm('Błąd połączenia', 'Spróbuj ponownie za chwilę.', 'OK', () => {});
            }
        }

        // Live Opponent Progress via SSE
        if (window.EventSource && duelId > 0) {
            try {
                const duelEventSource = new EventSource(`../api/events_sse.php?channel=duel&duel_id=${duelId}`);
                duelEventSource.addEventListener('duel_update', (e) => {
                    try {
                        const payload = JSON.parse(e.data);
                        if (payload) {
                            const countEl = document.getElementById('oppAnsweredCount');
                            const pillEl = document.getElementById('oppStatusPill');
                            if (countEl && typeof payload.opponent_answered !== 'undefined') {
                                countEl.textContent = payload.opponent_answered;
                            }
                            if (pillEl && payload.opponent_finished) {
                                pillEl.className = 'badge bg-success bg-opacity-10 text-success ms-1';
                                pillEl.textContent = 'Ukończył!';
                            }
                        }
                    } catch (err) {
                        console.warn('[Duel SSE] Parse error:', err);
                    }
                });
            } catch (err) {
                console.warn('[Duel SSE] Initialization failed:', err);
            }
        }
    </script>
</body>
</html>
