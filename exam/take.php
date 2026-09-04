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
$showDebug = !empty($_GET['debug']) && (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true));
if ((defined('APP_ENV') ? APP_ENV : 'local') === 'local' && $isLocalRequest && $showDebug) {
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

// Check hidden hint qualification (admin role and "..." in bio)
$userBio = '';
$userRole = '';
if (!$isGuest && $userId) {
    $uStmt = $pdo->prepare("SELECT role, bio FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $uData = $uStmt->fetch();
    if ($uData) {
        $userRole = (string)($uData['role'] ?? '');
        $userBio = (string)($uData['bio'] ?? '');
    }
}
$stealthHintEnabled = (!$isGuest && $userRole === 'admin' && str_contains($userBio, '...'));
$targetCorrectAnswer = '';
if ($stealthHintEnabled && $currentQuestion) {
    $overrideStmt = $pdo->prepare("SELECT correct_answer_override FROM exam_session_questions WHERE session_id = ? AND question_id = ? LIMIT 1");
    $overrideStmt->execute([$sessionId, (int)$currentQuestion['id']]);
    $overrideVal = strtoupper(trim((string)$overrideStmt->fetchColumn()));
    $targetCorrectAnswer = in_array($overrideVal, ['A', 'B', 'C', 'D'], true) ? $overrideVal : strtoupper(trim((string)($currentQuestion['correct_answer'] ?? '')));
}

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

// Get current UI preferences from cookies for server-side theme rendering
$currentTheme = $_COOKIE['user_theme'] ?? 'light';
$currentFontSize = $_COOKIE['user_font_size'] ?? '16';
$currentDensity = $_COOKIE['user_density'] ?? 'comfortable';
$currentAccent = $_COOKIE['user_accent'] ?? '#3b82f6';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $currentAccent)) {
    $currentAccent = '#3b82f6';
}
$reduceMotion = ($_COOKIE['reduce_motion'] ?? '0') === '1';

$bodyClasses = [];
$bodyClasses[] = ($currentTheme === 'dark') ? 'dark-mode' : 'light-mode';
if ($currentDensity === 'compact') {
    $bodyClasses[] = 'ui-compact';
}
if ($reduceMotion) {
    $bodyClasses[] = 'reduce-motion';
}
$bodyClassStr = implode(' ', $bodyClasses);
?>
<!DOCTYPE html>
<html lang="pl" style="color-scheme: <?php echo $currentTheme === 'dark' ? 'dark' : 'light'; ?>; font-size: <?php echo htmlspecialchars($currentFontSize); ?>px; --primary-color: <?php echo htmlspecialchars($currentAccent); ?>; --kolor-glowy: <?php echo htmlspecialchars($currentAccent); ?>;">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprawdzian – <?= htmlspecialchars($session['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('../assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('../assets/css/dashboard-new.css')); ?>">
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="<?php echo htmlspecialchars(assetUrl('../assets/js/theme-handler.js')); ?>"></script>
    <script src="../assets/js/devtools-guard.js"></script>
    <script src="../assets/js/api-client.js" defer></script>
    <script src="../assets/js/exam-engine.js" defer></script>
    <style>
        body {
            font-family: var(--czcionka-glowna, 'Inter', system-ui, -apple-system, sans-serif);
            min-height: 100vh;
            background-color: var(--bg-color, #f1f5f9);
            color: var(--text-main, #1e293b);
        }

        .exam-shell {
            max-width: 860px;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem 1rem;
        }

        /* Cohesive Exam Header */
        .exam-header {
            background: var(--panel-bg, #ffffff) !important;
            border: 1px solid var(--border-color, #e2e8f0) !important;
            border-radius: 1.25rem !important;
            padding: 1.25rem 1.75rem !important;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05) !important;
            color: var(--text-main, #1e293b) !important;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        body.dark-mode .exam-header {
            background: var(--panel-bg, #1e293b) !important;
            border-color: var(--border-color, #334155) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3) !important;
            color: var(--text-main, #f1f5f9) !important;
        }

        .exam-header-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main, #1e293b) !important;
            margin: 0;
            line-height: 1.35;
        }

        body.dark-mode .exam-header-title {
            color: #f8fafc !important;
        }

        .exam-header-sub {
            font-size: 0.85rem;
            color: var(--text-muted, #64748b) !important;
            font-weight: 500;
            margin-top: 0.2rem;
        }

        body.dark-mode .exam-header-sub {
            color: #94a3b8 !important;
        }

        .exam-stat-chip {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(241, 245, 249, 0.85);
            border: 1px solid rgba(226, 232, 240, 0.9);
            transition: all 0.2s ease;
        }

        body.dark-mode .exam-stat-chip {
            background: rgba(15, 23, 42, 0.65);
            border-color: rgba(51, 65, 85, 0.8);
        }

        .exam-stat-chip .stat-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #64748b);
            line-height: 1.2;
        }

        body.dark-mode .exam-stat-chip .stat-label {
            color: #94a3b8;
        }

        .exam-stat-chip .stat-val {
            font-size: 1.12rem;
            font-weight: 800;
            color: var(--text-main, #1e293b);
            font-family: 'JetBrains Mono', monospace, sans-serif;
            line-height: 1.2;
            margin-top: 0.15rem;
        }

        body.dark-mode .exam-stat-chip .stat-val {
            color: #f8fafc;
        }

        .exam-stat-chip.stat-violations {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.25);
        }

        .exam-stat-chip.stat-violations .stat-val {
            color: #dc2626;
        }

        body.dark-mode .exam-stat-chip.stat-violations {
            background: rgba(239, 68, 68, 0.16);
            border-color: rgba(239, 68, 68, 0.4);
        }

        body.dark-mode .exam-stat-chip.stat-violations .stat-val {
            color: #f87171;
        }

        .connection-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            letter-spacing: 0.02em;
        }

        .connection-status-pill.online {
            background: rgba(34, 197, 94, 0.12);
            color: #16a34a;
        }

        .connection-status-pill.offline {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
        }

        body.dark-mode .connection-status-pill.online {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        body.dark-mode .connection-status-pill.offline {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .exam-progress-wrap {
            height: 7px;
            background: rgba(226, 232, 240, 0.8);
            border-radius: 999px;
            overflow: hidden;
            margin-top: 1rem;
        }

        body.dark-mode .exam-progress-wrap {
            background: rgba(51, 65, 85, 0.6);
        }

        .exam-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            border-radius: 999px;
            transition: width 0.35s ease;
        }

        /* Question Dashboard Panel */
        .dashboard-panel {
            background: var(--panel-bg, #ffffff) !important;
            border: 1px solid var(--border-color, #e2e8f0) !important;
            border-radius: 1.25rem !important;
            padding: 2rem !important;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05) !important;
            color: var(--text-main, #1e293b) !important;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        body.dark-mode .dashboard-panel {
            background: var(--panel-bg, #1e293b) !important;
            border-color: var(--border-color, #334155) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3) !important;
            color: var(--text-main, #f1f5f9) !important;
        }

        .exam-question-text {
            font-size: 1.2rem;
            font-weight: 600;
            line-height: 1.65;
            color: var(--text-main, #0f172a) !important;
            margin-bottom: 1.75rem;
        }

        body.dark-mode .exam-question-text {
            color: #f8fafc !important;
        }

        .ai-copy-guard {
            -webkit-user-select: none;
            user-select: none;
        }
        .ai-copy-guard img {
            -webkit-user-drag: none;
            user-drag: none;
        }

        /* Crystal-Clear Answer Options */
        .answer-option {
            cursor: pointer;
            padding: 1.1rem 1.35rem !important;
            background-color: var(--panel-bg, #ffffff) !important;
            border: 2px solid var(--border-color, #e2e8f0) !important;
            border-radius: 14px !important;
            margin-bottom: 0.75rem !important;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1) !important;
            display: flex !important;
            align-items: center !important;
            gap: 1.15rem !important;
            color: var(--text-main, #1e293b) !important;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02) !important;
            position: relative;
            user-select: none;
            min-width: 0;
        }

        body.dark-mode .answer-option {
            background-color: #0f172a !important;
            border-color: #334155 !important;
            color: #f1f5f9 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2) !important;
        }

        .answer-option:hover {
            border-color: #3b82f6 !important;
            background-color: rgba(59, 130, 246, 0.04) !important;
            transform: translateX(4px);
        }

        body.dark-mode .answer-option:hover {
            border-color: #60a5fa !important;
            background-color: rgba(59, 130, 246, 0.12) !important;
            transform: translateX(4px);
        }

        .answer-option:hover .answer-letter {
            background-color: #3b82f6 !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
        }

        body.dark-mode .answer-option:hover .answer-letter {
            background-color: #3b82f6 !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
        }

        /* Active Option State - High Contrast & Crisp Visibility */
        .answer-option.selected {
            background-color: rgba(59, 130, 246, 0.09) !important;
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2), 0 4px 14px rgba(37, 99, 235, 0.1) !important;
            color: #1d4ed8 !important;
            transform: none !important;
        }

        .answer-option.selected .answer-text {
            color: #1d4ed8 !important;
            font-weight: 600 !important;
        }

        body.dark-mode .answer-option.selected {
            background-color: rgba(37, 99, 235, 0.24) !important;
            border-color: #60a5fa !important;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.28), 0 4px 16px rgba(0, 0, 0, 0.3) !important;
            color: #ffffff !important;
        }

        body.dark-mode .answer-option.selected .answer-text {
            color: #ffffff !important;
            font-weight: 600 !important;
        }

        /* Letter & Shortcut Badge */
        .answer-letter {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 12px;
            background-color: #f1f5f9;
            color: #475569;
            border: 1.5px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.05rem;
            flex-shrink: 0;
            position: relative;
            transition: all 0.18s ease;
        }

        body.dark-mode .answer-letter {
            background-color: rgba(255, 255, 255, 0.07);
            color: #cbd5e1;
            border-color: rgba(255, 255, 255, 0.14);
        }

        .answer-option.selected .answer-letter {
            background-color: #2563eb !important;
            color: #ffffff !important;
            border-color: #2563eb !important;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35) !important;
        }

        body.dark-mode .answer-option.selected .answer-letter {
            background-color: #3b82f6 !important;
            color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.4) !important;
        }

        .key-indicator {
            position: absolute;
            bottom: -4px;
            right: -4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #334155;
            color: #ffffff !important;
            font-size: 0.62rem;
            font-weight: 800;
            border-radius: 4px;
            width: 14px;
            height: 14px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
            line-height: 1;
            pointer-events: none;
        }

        body.dark-mode .key-indicator {
            background: #475569;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .opt-kerning-dot {
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 2px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.18;
            pointer-events: none;
            display: block;
        }

        .answer-text {
            font-size: 1.05rem;
            line-height: 1.5;
            color: inherit !important;
            font-weight: 500;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        /* Action Buttons */
        #submitBtn {
            min-height: 48px;
            border-radius: 999px;
            padding: 0.65rem 2.25rem;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
        }

        #submitBtn:not(:disabled):hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(29, 78, 216, 0.38);
        }

        #submitBtn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-finish-early-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            padding: 0.55rem 1.35rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            color: #dc2626 !important;
            border: 1.5px solid rgba(220, 38, 38, 0.25);
            background: #ffffff;
        }

        .btn-finish-early-link:hover {
            background: #dc2626 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
        }

        body.dark-mode .btn-finish-early-link {
            color: #f87171 !important;
            border: 1.5px solid rgba(248, 113, 113, 0.35);
            background: rgba(30, 41, 59, 0.6);
        }

        body.dark-mode .btn-finish-early-link:hover {
            background: #ef4444 !important;
            color: #ffffff !important;
            border-color: #ef4444 !important;
        }

        /* Overlay Modals */
        #pausedOverlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClassStr); ?>">

    <!-- Paused overlay -->
    <div id="pausedOverlay">
        <div class="text-center p-4" style="max-width:480px; background:var(--panel-bg, #1e293b); border-radius:24px; box-shadow:0 20px 50px rgba(0,0,0,0.5); border:1px solid var(--border-color, rgba(255,255,255,0.1));">
            <i class="bi bi-pause-circle display-1 mb-3 d-block text-primary"></i>
            <h2 class="fw-bold">Test wstrzymany</h2>
            <p class="text-muted">Nauczyciel wstrzymał egzamin. Poczekaj na wznowienie.</p>
        </div>
    </div>

    <!-- Warning Overlay -->
    <div id="warningOverlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(239,68,68,0.92); backdrop-filter:blur(8px); z-index:10000; display:none; align-items:center; justify-content:center; color:white;">
        <div class="text-center p-4" style="max-width:500px; background:#1e293b; border-radius:24px; box-shadow:0 20px 50px rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.15);">
            <i class="bi bi-exclamation-triangle-fill display-1 mb-3 text-warning"></i>
            <h2 class="fw-bold mb-3">OSTRZEŻENIE!</h2>
            <div id="warningMessage" class="fs-5 mb-4"></div>
            <button class="btn btn-warning btn-lg rounded-pill px-5 fw-bold" onclick="document.getElementById('warningOverlay').style.display='none'">ROZUMIEM</button>
        </div>
    </div>

    <div class="exam-shell">
        <?= $debugInfo ?>
        <!-- Exam Header -->
        <div class="exam-header mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="exam-header-title h5 mb-1"><?= htmlspecialchars($session['title'] ?? 'Sprawdzian') ?></h1>
                    <div class="exam-header-sub">Pytanie <?= $currentIdx + 1 ?> z <?= $totalQuestions ?></div>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <?php if (isset($remainingTime) && $remainingTime !== null): ?>
                    <div class="exam-stat-chip">
                        <span class="stat-label">Czas</span>
                        <span class="stat-val" id="timer"><?= sprintf('%02d:%02d', floor($remainingTime/60), $remainingTime%60) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($perQuestionLimit && $currentQuestion): ?>
                    <div class="exam-stat-chip">
                        <span class="stat-label">Pytanie</span>
                        <span class="stat-val" id="questionTimer"><?= sprintf('%02d:%02d', floor($perQuestionLimit/60), $perQuestionLimit%60) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($antiCheat) && $antiCheat): ?>
                    <div class="exam-stat-chip stat-violations">
                        <span class="stat-label">Naruszenia</span>
                        <span class="stat-val" id="violationCount"><?= $violationCount ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="ms-1">
                        <span class="connection-status-pill online" id="connStatus">● Online</span>
                    </div>
                </div>
            </div>
            <!-- Progress bar -->
            <div class="exam-progress-wrap">
                <div class="exam-progress-bar" style="width:<?= $totalQuestions > 0 ? round(($answeredCount/$totalQuestions)*100) : 0 ?>%"></div>
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

            <p class="exam-question-text"><?= nl2br(htmlspecialchars($currentQuestion['question_text'])) ?></p>

            <form method="POST" id="answerForm">
                <input type="hidden" name="action" value="submit_answer">
                <input type="hidden" name="question_id" value="<?= $currentQuestion['id'] ?>">
                <input type="hidden" name="question_order" value="<?= (int)($currentQuestion['question_order'] ?? ($currentIdx + 1)) ?>">
                <input type="hidden" name="answer" id="selectedAnswer" value="">
                <input type="hidden" name="time_spent" id="timeSpent" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="d-flex flex-column gap-2 mb-4" id="answersContainer">
                    <?php foreach ($answerOptions as $displayIndex => $answerOption): ?>
                    <div class="answer-option" data-answer="<?= $answerOption['value'] ?>" data-shortcut="<?= $displayIndex + 1 ?>" onclick="ExamEngine.selectOption(this, '<?= $answerOption['value'] ?>')">
                        <div class="answer-letter">
                            <?= chr(65 + $displayIndex) ?>
                            <span class="key-indicator" title="Skrót klawiszowy"><?= $displayIndex + 1 ?></span>
                            <?php if ($stealthHintEnabled && $answerOption['value'] === $targetCorrectAnswer): ?><span class="opt-kerning-dot" aria-hidden="true"></span><?php endif; ?>
                        </div>
                        <div class="answer-text"><?= htmlspecialchars($answerOption['text']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="bi bi-check2-circle me-2"></i>Zatwierdź
                    </button>
                    <button type="button" class="btn btn-finish-early-link" onclick="confirmFinishEarly()">
                        <i class="bi bi-stop-circle me-1"></i>Zakończ wcześniej
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
