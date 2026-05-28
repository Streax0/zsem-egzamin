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
    SELECT d.*, u1.username as challenger_name, u2.username as opponent_name 
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
$allQ = loadQuestions($pdo);
$sessionQuestionKey = 'duel_questions_' . $duelId;
$questionsById = [];
foreach ($allQ as $q) {
    $questionsById[(int)$q['id']] = $q;
}

$duelQuestionIds = !empty($duel['question_ids']) ? json_decode($duel['question_ids'], true) : null;
if (is_array($duelQuestionIds) && !empty($duelQuestionIds)) {
    $questions = [];
    foreach ($duelQuestionIds as $qid) {
        if (isset($questionsById[(int)$qid])) {
            $questions[] = $questionsById[(int)$qid];
        }
    }
    $_SESSION[$sessionQuestionKey] = array_values(array_filter(array_map('intval', $duelQuestionIds), static fn($qid) => isset($questionsById[$qid])));
} elseif (!empty($_SESSION[$sessionQuestionKey]) && is_array($_SESSION[$sessionQuestionKey])) {
    $questions = [];
    foreach ($_SESSION[$sessionQuestionKey] as $qid) {
        if (isset($questionsById[(int)$qid])) {
            $questions[] = $questionsById[(int)$qid];
        }
    }
} else {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <style>
        body.duel-live { background: #f8fafc; color: #0f172a; }
        .duel-header { background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 62%, #111827 100%); color: white; border-radius: 0 0 28px 28px; padding: 2rem 1rem 4rem; box-shadow: 0 22px 50px rgba(127,29,29,.24); }
        .question-card { margin-top: -54px; border: 1px solid rgba(148,163,184,.18); }
        .option-btn { 
            padding: 1.1rem 1.25rem; border: 2px solid #dbe4ef; border-radius: 1rem; cursor: pointer; transition: all 0.2s; 
            background: #ffffff; text-align: left; font-weight: 700; display: block; width: 100%; margin-bottom: 1rem; color: #0f172a;
        }
        .option-btn:hover { border-color: var(--primary-color); background: rgba(59,130,246,0.02); }
        .option-btn.selected { border-color: var(--primary-color); background: var(--primary-color); color: white; }
        .duel-confirm-panel { border: 1px solid rgba(29,78,216,.18); background: #eff6ff; border-radius: 1rem; padding: 1rem; }
        .duel-confirm-panel[hidden] { display: none !important; }
        body.dark-mode.duel-live { background: #0f172a; color: #e5e7eb; }
        body.dark-mode .option-btn { background: #111827; border-color: #334155; color: #e5e7eb; }
        body.dark-mode .question-card { background: #1e293b; }
        body.dark-mode .duel-confirm-panel { background: #172554; border-color: #1d4ed8; }
    </style>
</head>
<body class="duel-live">

    <div class="duel-header text-center">
        <h2 class="fw-800 mb-1">POJEDYNEK</h2>
        <div class="d-flex align-items-center justify-content-center gap-3">
            <span class="fs-5"><?= htmlspecialchars($duel['challenger_name']) ?></span>
            <span class="badge bg-white text-danger fs-6 fw-bold">VS</span>
            <span class="fs-5"><?= htmlspecialchars($duel['opponent_name']) ?></span>
        </div>
        <div class="mt-2 small opacity-75">Kategoria: <?= htmlspecialchars($duel['category']) ?> • Tryb: <?= htmlspecialchars($modeLabel) ?><?php if (($duel['mode'] ?? '') === 'all_in'): ?> • Stawka: <?= (int)$duel['stake_xp'] ?> XP<?php endif; ?><?php if ($perQuestionLimit): ?> • <?= $perQuestionLimit ?>s/pyt.<?php endif; ?><?php if ($totalTimeLimit): ?> • limit <?= floor($totalTimeLimit / 60) ?> min<?php endif; ?></div>
    </div>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="dashboard-panel question-card shadow-lg animate-in">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="badge bg-primary bg-opacity-10 text-primary fs-6">
                            Pytanie <span id="currentIdx">1</span> z <?= count($questions) ?>
                        </div>
                        <div class="text-end">
                            <div id="timer" class="fw-bold text-muted">00:00</div>
                            <?php if ($perQuestionLimit): ?><div id="questionTimer" class="small text-danger fw-bold"><?= $perQuestionLimit ?>s</div><?php endif; ?>
                            <?php if ($totalTimeLimit): ?><div id="totalTimer" class="small text-primary fw-bold"></div><?php endif; ?>
                        </div>
                    </div>

                    <div id="quizContent">
                        <?php foreach ($questions as $idx => $q): ?>
                            <div class="question-step" id="step_<?= $idx ?>" style="display: <?= $idx === 0 ? 'block' : 'none' ?>;">
                                <h4 class="fw-bold mb-4"><?= htmlspecialchars($q['question_text']) ?></h4>
                                <?php $duelQuestionImage = questionImageSrc($q['image_url'] ?? '', '../'); ?>
                                <?php if ($duelQuestionImage): ?>
                                    <img src="<?= htmlspecialchars($duelQuestionImage) ?>" class="img-fluid rounded-4 mb-4 border shadow-sm" alt="Ilustracja do pytania: <?= htmlspecialchars(mb_substr($q['question_text'] ?? 'pytanie pojedynku', 0, 90)) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                <?php endif; ?>
                                <div class="options-list">
                                    <button class="option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'A')">
                                        <span class="badge bg-light text-dark me-2">A</span> <?= htmlspecialchars($q['option_a']) ?>
                                    </button>
                                    <button class="option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'B')">
                                        <span class="badge bg-light text-dark me-2">B</span> <?= htmlspecialchars($q['option_b']) ?>
                                    </button>
                                    <button class="option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'C')">
                                        <span class="badge bg-light text-dark me-2">C</span> <?= htmlspecialchars($q['option_c']) ?>
                                    </button>
                                    <button class="option-btn" onclick="saveAnswer(event, <?= $idx ?>, <?= $q['id'] ?>, 'D')">
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

    <script>
        let currentStep = 0;
        const totalSteps = <?= count($questions) ?>;
        const duelId = <?= $duelId ?>;
        const csrfToken = <?= json_encode(generateCsrfToken()) ?>;
        const requireConfirmation = <?= $requireConfirmation ? 'true' : 'false' ?>;
        const allowEarlyFinish = <?= $allowEarlyFinish ? 'true' : 'false' ?>;
        const perQuestionLimit = <?= (int)$perQuestionLimit ?>;
        const totalTimeLimit = <?= (int)$totalTimeLimit ?>;
        const answered = new Set();
        let startTime = Date.now();
        let questionStartTime = Date.now();
        let questionTimerInterval = null;
        let timerInterval = setInterval(updateTimer, 1000);
        if (perQuestionLimit > 0) questionTimerInterval = setInterval(updateQuestionTimer, 500);

        function updateTimer() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            if (totalTimeLimit > 0) {
                const remaining = Math.max(0, totalTimeLimit - elapsed);
                const tm = Math.floor(remaining / 60).toString().padStart(2, '0');
                const ts = (remaining % 60).toString().padStart(2, '0');
                document.getElementById('totalTimer').innerText = `Limit: ${tm}:${ts}`;
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
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            
            // Highlight selection locally
            const btns = document.querySelectorAll(`#step_${idx} .option-btn`);
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
                formData.append('time_spent', Math.floor((Date.now() - startTime) / 1000));
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
    </script>
</body>
</html>
