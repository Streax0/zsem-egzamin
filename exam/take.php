<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin(true);

$isGuest = isGuestMode();
$userId = $isGuest ? null : (int)$_SESSION['user_id'];
$sessionId = securityInputInt($_GET['session'] ?? 0, 0, PHP_INT_MAX, 0);

// Load session
$stmt = $pdo->prepare("
    SELECT es.id, es.exam_id, es.access_code, es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, es.created_at, e.title, e.question_count, e.total_time, e.time_per_question,
           e.shuffle_questions, e.shuffle_answers, e.exam_mode, e.auto_finish_on_time,
           e.anti_cheat_enabled, e.block_tab_switch, e.require_fullscreen,
           e.show_results_to_student, e.show_predicted_grade, e.grade_thresholds,
           e.randomize_per_student
    FROM exam_sessions es
    JOIN exams e ON es.exam_id = e.id
    WHERE es.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session || !in_array($session['status'], ['in_progress', 'paused'])) {
    redirect('../index.php');
}

// Load participant
if ($isGuest) {
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND id = ? AND user_id IS NULL AND status IN ('taking_exam','in_lobby') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId, guestExamParticipantId($sessionId)]);
} else {
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND user_id = ? AND status IN ('taking_exam','in_lobby') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId, $userId]);
}
$participant = $stmt->fetch();

if (!$participant) {
    redirect('../index.php');
}

// Update status if still in lobby
if ($participant['status'] === 'in_lobby') {
    $pdo->prepare("UPDATE exam_participants SET status = 'taking_exam', started_at = NOW() WHERE id = ?")
        ->execute([$participant['id']]);
}

// Load questions for this session
$stmt = $pdo->prepare("SELECT question_id, question_order FROM exam_session_questions WHERE session_id = ? ORDER BY question_order");
$stmt->execute([$sessionId]);
$sessionQuestions = $stmt->fetchAll();
$totalInDb = count($sessionQuestions);

// Resolve only assigned question content (DB first, JSON fallback only when needed)
$questions = [];
$questionMap = [];
foreach (getQuestionsByIds($pdo, array_column($sessionQuestions, 'question_id')) as $q) {
    $questionMap[(int)$q['id']] = $q;
}

$resolvedCount = 0;
foreach ($sessionQuestions as $sq) {
    if (isset($questionMap[$sq['question_id']])) {
        $q = $questionMap[$sq['question_id']];
        $q['question_order'] = $sq['question_order'];
        $questions[] = $q;
        $resolvedCount++;
    } else {
        $questions[] = [
            'id' => $sq['question_id'],
            'question_text' => 'Błąd: Nie znaleziono treści pytania (ID: '.$sq['question_id'].')',
            'option_a' => '-', 'option_b' => '-', 'option_c' => '-', 'option_d' => '-',
            'correct_answer' => 'X',
            'question_order' => $sq['question_order']
        ];
    }
}

if (!empty($session['randomize_per_student'])) {
    usort($questions, static function($left, $right) use ($participant) {
        $leftHash = hash('sha256', $participant['id'] . ':' . ($left['id'] ?? 0));
        $rightHash = hash('sha256', $participant['id'] . ':' . ($right['id'] ?? 0));
        return strcmp($leftHash, $rightHash);
    });
}

$totalQuestions = $totalInDb; // Always use DB count as the target

// Load already answered questions
$stmt = $pdo->prepare("SELECT question_id FROM exam_answers WHERE participant_id = ? AND session_id = ?");
$stmt->execute([$participant['id'], $sessionId]);
$answeredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$answeredMap = array_fill_keys(array_map('intval', $answeredIds), true);
$answeredCount = count($answeredIds);
$currentIdx = $answeredCount; 

$debugInfo = '';
$isLocalRequest = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if ((defined('APP_ENV') ? APP_ENV : 'local') === 'local' && $isLocalRequest) {
    $debugInfo = "<div class='alert alert-info py-1 small mb-2'>DEBUG: W bazie sesji: $totalInDb | Znaleziono w JSON: $resolvedCount | Odpowiedziano: $answeredCount</div>";
}

$csrfToken = generateCsrfToken();

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!securityValidateRequestCsrf()) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('../index.php');
    }

    $action = securityInputEnum($_POST['action'] ?? '', ['submit_answer', 'finish_early'], '');
    $rateLimit = securityConsumeRateLimit('exam-take:' . securityActorKey() . ':' . $sessionId . ':' . $action, 100, 60);
    if ($action === '' || empty($rateLimit['allowed'])) {
        securityAudit('exam_take_post_blocked', ['session_id' => $sessionId, 'action' => $_POST['action'] ?? ''], 'warning');
        setSessionMessage('error', 'Zbyt wiele akcji naraz albo nieprawidłowa akcja formularza.');
        redirect('take.php?session=' . $sessionId);
    }
    
    if ($action === 'submit_answer') {
        if ($session['status'] !== 'in_progress' || $participant['status'] !== 'taking_exam') {
            setSessionMessage('info', 'Sprawdzian jest teraz wstrzymany. Odpowiedź nie została zapisana.');
            redirect('take.php?session=' . $sessionId);
        }

        $questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        $userAnswer = securityInputAnswerLetter($_POST['answer'] ?? '');
        $questionOrder = securityInputInt($_POST['question_order'] ?? 0, 0, 1000, 0);
        $timeSpent = securityInputInt($_POST['time_spent'] ?? 0, 0, 86400, 0);

        // Find question
        $q = null;
        foreach ($questions as $quest) {
            if ((int)$quest['id'] === $questionId) { $q = $quest; break; }
        }

        if ($q && !isset($answeredMap[$questionId])) {
            $overrideStmt = $pdo->prepare("SELECT correct_answer_override FROM exam_session_questions WHERE session_id = ? AND question_id = ? LIMIT 1");
            $overrideStmt->execute([$sessionId, $questionId]);
            $overrideAnswer = strtoupper((string)$overrideStmt->fetchColumn());
            $correctAnswer = in_array($overrideAnswer, ['A','B','C','D'], true) ? $overrideAnswer : strtoupper($q['correct_answer']);
            $isCorrect = ($userAnswer === $correctAnswer) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO exam_answers (participant_id, session_id, question_id, question_order, user_answer, correct_answer, is_correct, time_spent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$participant['id'], $sessionId, $questionId, $questionOrder, $userAnswer, $correctAnswer, $isCorrect, $timeSpent]);

            // Update participant progress
            $newAnswered = $answeredCount + 1;
            $newCorrect = $participant['correct_answers'] + $isCorrect;
            $newPercent = $totalQuestions > 0 ? round(($newCorrect / $totalQuestions) * 100, 2) : 0;

            $pdo->prepare("UPDATE exam_participants SET current_question = ?, total_answered = ?, correct_answers = ?, score_percent = ?, last_activity = NOW() WHERE id = ?")
                ->execute([$newAnswered, $newAnswered, $newCorrect, $newPercent, $participant['id']]);

            // Check if finished all questions
            if ($newAnswered >= $totalQuestions) {
                $totalTimeSpent = time() - strtotime($participant['started_at'] ?? $session['started_at']);
                $pdo->prepare("UPDATE exam_participants SET status = 'finished', finished_at = NOW(), time_spent = ? WHERE id = ?")
                    ->execute([$totalTimeSpent, $participant['id']]);
                
                session_write_close();
                redirect('finished.php?session=' . $sessionId);
            }

            // Redirect to next question
            session_write_close();
            redirect('take.php?session=' . $sessionId);
        }
    }

    if ($action === 'finish_early') {
        $totalTimeSpent = time() - strtotime($participant['started_at'] ?? $session['started_at']);
        $pdo->prepare("UPDATE exam_participants SET status = 'finished', finished_at = NOW(), time_spent = ? WHERE id = ?")
            ->execute([$totalTimeSpent, $participant['id']]);
        redirect('finished.php?session=' . $sessionId);
    }
}

// Check if exam was paused or finished by teacher
if ($session['status'] === 'paused') {
    // Show paused message
}

// Get current question
$currentQuestion = isset($questions[$currentIdx]) ? $questions[$currentIdx] : null;
$answerOptions = [];
if ($currentQuestion) {
    foreach (['A', 'B', 'C', 'D'] as $optionLetter) {
        $optionText = $currentQuestion['option_' . strtolower($optionLetter)] ?? '';
        if (trim($optionText) === '') continue;
        $answerOptions[] = ['value' => $optionLetter, 'text' => $optionText];
    }
    if (!empty($session['shuffle_answers'])) {
        $seed = (int)$participant['id'] + (int)$currentQuestion['id'];
        usort($answerOptions, static function($left, $right) use ($seed) {
            return strcmp(hash('sha256', $seed . ':' . $left['value']), hash('sha256', $seed . ':' . $right['value']));
        });
    }
}

// Calculate remaining time
$remainingTime = null;
if ($session['total_time']) {
    $startedAt = strtotime($session['started_at']);
    $pausedSeconds = (int)($session['paused_seconds'] ?? 0);
    if ($session['status'] === 'paused' && !empty($session['paused_at'])) {
        $pausedSeconds += time() - strtotime($session['paused_at']);
    }
    $elapsed = max(0, time() - $startedAt - $pausedSeconds);
    $remainingTime = max(0, ($session['total_time'] * 60) - $elapsed);
}

$violationCount = $participant['violation_count'];
$antiCheat = $session['anti_cheat_enabled'];
$aiCopyGuard = examAiCopyGuardEnabled($pdo, (int)$session['exam_id']);
$blockTab = $session['block_tab_switch'];
$requireFs = $session['require_fullscreen'];
$perQuestionLimit = !empty($session['time_per_question']) ? max(5, (int)$session['time_per_question']) : null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprawdzian – <?= htmlspecialchars($session['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <script src="../assets/js/api-client.js" defer></script>
    <script src="../assets/js/exam-engine.js" defer></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Exam header styling (kept similar) */
        .exam-header { background: linear-gradient(135deg, #1e293b, #334155); color: white; padding: 1rem 1.5rem; border-radius: 16px; }

        /* Dashboard panel: slightly lighter dark surface for readability */
        .dashboard-panel {
            background: rgba(20, 30, 45, 0.96);
            color: #e6eef8;
            padding: 1.25rem;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.03);
        }
        .ai-copy-guard {
            -webkit-user-select: none;
            user-select: none;
        }
        .ai-copy-guard img {
            -webkit-user-drag: none;
            user-drag: none;
        }

        /* Answer option contrast improvements */
        .answer-option {
            cursor: pointer;
            padding: 1rem 1.5rem;
            border: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            transition: all 0.15s ease;
            color: #e6eef8;
        }

        .answer-option:hover {
            border-color: rgba(102,126,234,0.7);
            background: rgba(102,126,234,0.035);
            transform: none;
        }

        .answer-option.selected {
            border-color: rgba(59,130,246,0.95);
            background: rgba(59,130,246,0.12);
        }

        .answer-letter {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
            flex-shrink: 0;
            color: #cbd5e1;
        }

        .answer-option.selected .answer-letter {
            background: var(--primary-color);
            color: white;
        }

        .violation-counter { background: rgba(239,68,68,0.12); color: #ffb4b4; }
        .connection-status { font-size: 0.7rem; }
        #pausedOverlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.8); z-index:9999; display:none; align-items:center; justify-content:center; color:white; }
    </style>
</head>
<body>

    <!-- Paused overlay -->
    <div id="pausedOverlay">
        <div class="text-center">
            <i class="bi bi-pause-circle display-1 mb-3 d-block"></i>
            <h2>Test wstrzymany</h2>
            <p class="text-muted">Nauczyciel wstrzymał egzamin. Poczekaj na wznowienie.</p>
        </div>
    </div>

    <!-- Warning Overlay -->
    <div id="warningOverlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(239,68,68,0.9); z-index:10000; display:none; align-items:center; justify-content:center; color:white;">
        <div class="text-center p-4" style="max-width:500px; background:#1e293b; border-radius:24px; box-shadow:0 20px 50px rgba(0,0,0,0.5);">
            <i class="bi bi-exclamation-triangle-fill display-1 mb-3 text-warning"></i>
            <h2 class="fw-bold mb-3">OSTRZEŻENIE!</h2>
            <div id="warningMessage" class="fs-5 mb-4"></div>
            <button class="btn btn-warning btn-lg rounded-pill px-5 fw-bold" onclick="document.getElementById('warningOverlay').style.display='none'">ROZUMIEM</button>
        </div>
    </div>

    <div class="container-fluid p-3" style="max-width:900px; margin:auto;">
        <?= $debugInfo ?>
        <!-- Exam Header -->
        <div class="exam-header mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold"><?= htmlspecialchars($session['title'] ?? 'Sprawdzian') ?></h5>
                    <div class="small opacity-75 mt-1">Pytanie <?= $currentIdx + 1 ?> z <?= $totalQuestions ?></div>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <?php if (isset($remainingTime) && $remainingTime !== null): ?>
                    <div class="text-center">
                        <div class="small opacity-75">Czas</div>
                        <div class="fw-bold fs-5" id="timer"><?= sprintf('%02d:%02d', floor($remainingTime/60), $remainingTime%60) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($perQuestionLimit && $currentQuestion): ?>
                    <div class="text-center">
                        <div class="small opacity-75">Pytanie</div>
                        <div class="fw-bold fs-5" id="questionTimer"><?= sprintf('%02d:%02d', floor($perQuestionLimit/60), $perQuestionLimit%60) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($antiCheat) && $antiCheat): ?>
                    <div class="text-center">
                        <div class="small opacity-75">Naruszenia</div>
                        <div class="fw-bold" id="violationCount"><?= $violationCount ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="text-center">
                        <div class="connection-status text-success" id="connStatus">● Online</div>
                    </div>
                </div>
            </div>
            <!-- Progress bar -->
            <div class="progress mt-3" style="height:6px; background:rgba(255,255,255,0.2); border-radius:3px;">
                <div class="progress-bar bg-success" style="width:<?= $totalQuestions > 0 ? round(($answeredCount/$totalQuestions)*100) : 0 ?>%"></div>
            </div>
        </div>

        <?php if ($session['status'] === 'paused'): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-pause-circle display-4 d-block mb-2"></i>
                <h4>Test wstrzymany</h4>
                <p class="mb-0">Nauczyciel wstrzymał egzamin. Poczekaj na wznowienie.</p>
            </div>
            <script>setInterval(() => {
                fetch('../ajax/exam_status.php?session=<?=$sessionId?>&lite=1').then(r=>r.json()).then(d=>{
                    if(d.status==='in_progress') location.reload();
                    if(d.status==='finished') window.location='finished.php?session=<?=$sessionId?>';
                });
            }, 3000);</script>

        <?php elseif ($currentQuestion): ?>
        <!-- Question -->
        <div class="dashboard-panel mb-4<?= $aiCopyGuard ? ' ai-copy-guard' : '' ?>">
            <?php $examQuestionImage = questionImageSrc($currentQuestion['image_url'] ?? '', '../'); ?>
            <?php if ($examQuestionImage): ?>
                <img src="<?= htmlspecialchars($examQuestionImage) ?>" class="img-fluid rounded mb-3" alt="Ilustracja do pytania: <?= htmlspecialchars(mb_substr($currentQuestion['question_text'] ?? 'pytanie egzaminacyjne', 0, 90)) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
            <?php endif; ?>

            <p class="h5 fw-medium mb-4" style="line-height:1.6"><?= nl2br(htmlspecialchars($currentQuestion['question_text'])) ?></p>

            <form method="POST" id="answerForm">
                <input type="hidden" name="action" value="submit_answer">
                <input type="hidden" name="question_id" value="<?= $currentQuestion['id'] ?>">
                <input type="hidden" name="question_order" value="<?= (int)($currentQuestion['question_order'] ?? ($currentIdx + 1)) ?>">
                <input type="hidden" name="answer" id="selectedAnswer" value="">
                <input type="hidden" name="time_spent" id="timeSpent" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="d-flex flex-column gap-2 mb-4">
                    <?php foreach ($answerOptions as $displayIndex => $answerOption): ?>
                    <div class="answer-option d-flex align-items-center" data-answer="<?= $answerOption['value'] ?>" data-shortcut="<?= $displayIndex + 1 ?>" onclick="ExamEngine.selectOption(this, '<?= $answerOption['value'] ?>')">
                        <div class="answer-letter"><?= chr(65 + $displayIndex) ?></div>
                        <div><?= htmlspecialchars($answerOption['text']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill" id="submitBtn" disabled>
                        <i class="bi bi-check2-circle me-2"></i>Zatwierdź
                    </button>
                    <button type="button" class="btn btn-link text-danger text-decoration-none" onclick="confirmFinishEarly()">
                        <i class="bi bi-stop-fill me-1"></i>Zakończ wcześniej
                    </button>
                </div>
            </form>
        </div>

        <!-- Separate form for finish early to avoid nesting -->
        <form id="finishEarlyForm" method="POST" style="display:none;">
            <input type="hidden" name="action" value="finish_early">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        </form>

        <script>
        function confirmFinishEarly() {
            appConfirmSubmit(document.getElementById('finishEarlyForm'), 'Zakończyć sprawdzian? Nieudzielone odpowiedzi zostaną pominięte.');
        }
        </script>

        <?php else: ?>
        <!-- All questions answered -->
        <div class="dashboard-panel text-center py-5">
            <i class="bi bi-check-circle display-1 text-success mb-3 d-block"></i>
            <h3 class="fw-bold">Ukończyłeś sprawdzian!</h3>
            <p class="text-muted">Odpowiedziałeś na wszystkie pytania.</p>
            <form method="POST">
                <input type="hidden" name="action" value="finish_early">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-success btn-lg rounded-pill px-5">
                    <i class="bi bi-flag-fill me-2"></i>Zakończ sprawdzian
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/app-dialogs.js"></script>
    <script src="../assets/js/performance-metrics.js" defer></script>
    <script>
    let examPaused = <?= $session['status'] === 'paused' ? 'true' : 'false' ?>;
    <?php if ($remainingTime !== null): ?>
    let timeLeft = <?= $remainingTime ?>;
    const timerEl = document.getElementById('timer');
    setInterval(() => {
        if (examPaused) return;
        timeLeft--;
        if (timeLeft <= 0) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = '<input type="hidden" name="action" value="finish_early">' +
                '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">';
            document.body.appendChild(f);
            appNotice('Czas upłynął. Sprawdzian zostaje zakończony.', 'warning');
            setTimeout(() => f.submit(), 700);
            return;
        }
        const m = String(Math.floor(timeLeft/60)).padStart(2,'0');
        const s = String(timeLeft%60).padStart(2,'0');
        timerEl.textContent = `${m}:${s}`;
        if (timeLeft <= 60) timerEl.style.color = '#ef4444';
        else if (timeLeft <= 300) timerEl.style.color = '#f59e0b';
    }, 1000);
    <?php endif; ?>

    <?php if ($perQuestionLimit && $currentQuestion): ?>
    let questionTimeLeft = <?= (int)$perQuestionLimit ?>;
    const questionTimerEl = document.getElementById('questionTimer');
    const questionTimerInterval = setInterval(() => {
        if (examPaused) return;
        questionTimeLeft--;
        if (questionTimerEl) {
            const m = String(Math.floor(questionTimeLeft / 60)).padStart(2, '0');
            const s = String(questionTimeLeft % 60).padStart(2, '0');
            questionTimerEl.textContent = `${m}:${s}`;
            if (questionTimeLeft <= 10) questionTimerEl.style.color = '#ef4444';
            else if (questionTimeLeft <= 30) questionTimerEl.style.color = '#f59e0b';
        }
        if (questionTimeLeft <= 0) {
            clearInterval(questionTimerInterval);
            const answerForm = document.getElementById('answerForm');
            if (answerForm) {
                appNotice('Czas na pytanie minął. Pytanie zostaje zapisane jako brak odpowiedzi.', 'warning');
                setTimeout(() => HTMLFormElement.prototype.submit.call(answerForm), 700);
            }
        }
    }, 1000);
    <?php endif; ?>

    <?php if ($aiCopyGuard): ?>
    const reportAiGuardViolation = (type) => {
        ExamEngine.reportViolation(type, <?= $sessionId ?>, <?= $participant['id'] ?>, <?= $currentQuestion['id'] ?? 0 ?>);
    };
    function isLikelyScreenshotShortcut(event) {
        const key = event.key.toLowerCase();
        return event.key === 'PrintScreen'
            || (event.metaKey && event.shiftKey && key === 's')
            || ((event.ctrlKey || event.metaKey) && event.shiftKey && ['4', '5'].includes(key));
    }

    document.addEventListener('copy', (event) => {
        event.preventDefault();
        reportAiGuardViolation('copy_paste');
    });
    document.addEventListener('cut', (event) => {
        event.preventDefault();
        reportAiGuardViolation('copy_paste');
    });
    document.addEventListener('paste', (event) => {
        event.preventDefault();
        reportAiGuardViolation('copy_paste');
    });
    document.addEventListener('dragstart', (event) => {
        if (event.target.closest?.('.ai-copy-guard')) {
            event.preventDefault();
            reportAiGuardViolation('copy_paste');
        }
    });
    document.addEventListener('contextmenu', (event) => {
        if (event.target.closest?.('.ai-copy-guard')) {
            event.preventDefault();
        }
    });
    document.addEventListener('keydown', (event) => {
        const key = event.key.toLowerCase();
        const copyShortcut = (event.ctrlKey || event.metaKey) && ['c', 'x'].includes(key);
        const pasteShortcut = ((event.ctrlKey || event.metaKey) && key === 'v') || (event.shiftKey && key === 'insert');
        if (copyShortcut) {
            event.preventDefault();
            reportAiGuardViolation('copy_paste');
        } else if (pasteShortcut) {
            event.preventDefault();
            reportAiGuardViolation('copy_paste');
        } else if (isLikelyScreenshotShortcut(event)) {
            reportAiGuardViolation('screenshot_attempt');
        }
    });
    document.addEventListener('keyup', (event) => {
        if (event.key === 'PrintScreen') {
            reportAiGuardViolation('screenshot_attempt');
        }
    });
    <?php endif; ?>

    <?php if ($antiCheat): ?>
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            ExamEngine.reportViolation('tab_switch', <?= $sessionId ?>, <?= $participant['id'] ?>, <?= $currentQuestion['id'] ?? 0 ?>);
        }
    });
    <?php if (!$aiCopyGuard): ?>
    document.addEventListener('copy', () => {
        ExamEngine.reportViolation('copy_paste', <?= $sessionId ?>, <?= $participant['id'] ?>, <?= $currentQuestion['id'] ?? 0 ?>);
    });
    <?php endif; ?>
    document.addEventListener('paste', () => {
        ExamEngine.reportViolation('copy_paste', <?= $sessionId ?>, <?= $participant['id'] ?>, <?= $currentQuestion['id'] ?? 0 ?>);
    });
    window.addEventListener('blur', () => {
        // Blur often fires with visibilitychange. We only report it if the tab is actually still visible (e.g. alt-tab to another app)
        setTimeout(() => {
            if (!document.hidden) {
                ExamEngine.reportViolation('window_blur', <?= $sessionId ?>, <?= $participant['id'] ?>, <?= $currentQuestion['id'] ?? 0 ?>);
            }
        }, 200);
    });
    <?php if ($requireFs): ?>
    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) ExamEngine.reportViolation('fullscreen_exit', <?= $sessionId ?>, <?= $participant['id'] ?>, <?= $currentQuestion['id'] ?? 0 ?>);
    });
    <?php endif; ?>
    <?php endif; ?>

    setInterval(() => {
        fetch('../ajax/exam_status.php?session=<?= $sessionId ?>&lite=1')
            .then(r => r.json())
            .then(data => {
                const overlay = document.getElementById('pausedOverlay');
                if (data.status === 'paused') {
                    examPaused = true;
                    overlay.style.display = 'flex';
                }
                else if (data.status === 'finished') {
                    appNotice('Nauczyciel zakończył egzamin.', 'info');
                    setTimeout(() => { window.location = 'finished.php?session=<?= $sessionId ?>'; }, 700);
                } else {
                    if (examPaused && data.status === 'in_progress') location.reload();
                    examPaused = false;
                    overlay.style.display = 'none';
                }
                
                // Show warnings
                if (data.warnings && data.warnings.length > 0) {
                    const wOverlay = document.getElementById('warningOverlay');
                    const wMsg = document.getElementById('warningMessage');
                    wMsg.replaceChildren();
                    data.warnings.forEach((message, index) => {
                        if (index > 0) wMsg.appendChild(document.createElement('br'));
                        wMsg.appendChild(document.createTextNode(message));
                    });
                    wOverlay.style.display = 'flex';
                    // Play a sound if possible (optional)
                }

                const conn = document.getElementById('connStatus');
                conn.innerHTML = '● Online';
                conn.className = 'connection-status text-success';
            })
            .catch(() => {
                const conn = document.getElementById('connStatus');
                conn.innerHTML = '● Offline';
                conn.className = 'connection-status text-danger';
            });
    }, 1000);
    </script>
</body>
</html>
