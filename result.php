<?php
// Include required files
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Start secure session and require login
startSecureSession();
requireLogin(true);

// Get result_id from GET or session
$result_id = 0;
$guestResultId = '';
if (isGuestMode()) {
    $guestResultId = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['guest'] ?? ($_SESSION['last_guest_result_id'] ?? ''))));
} elseif (isset($_GET['id'])) {
    $result_id = (int)$_GET['id'];
} elseif (isset($_GET['result_id'])) {
    $result_id = (int)$_GET['result_id'];
} elseif (isset($_SESSION['last_result_id'])) {
    $result_id = (int)$_SESSION['last_result_id'];
}

if (!$guestResultId && $result_id <= 0) {
    header('Location: index.php');
    exit;
}

if ($guestResultId) {
    $guestResult = $_SESSION['guest_test_results'][$guestResultId] ?? null;
    if (!$guestResult) {
        header('Location: test.php?setup=1&new=1');
        exit;
    }
    $row = $guestResult['row'];
    $answers = $guestResult['answers'];
} else {
    // Fetch test result for this user using PDO
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM test_results WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $result_id, 'user_id' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    header('Location: index.php');
    exit;
}

$correctAnswers = (int)($row['correct_answers'] ?? 0);
$total_questions = (int)($row['total_questions'] ?? 40);
$score_percent = (float)($row['score_percent'] ?? 0);
$time_spent = (int)($row['time_spent'] ?? 0);
$mode = $row['mode'] ?? 'exam';
$test_date = $row['test_date'] ?? '';
$wrongAnswers = max(0, $total_questions - $correctAnswers);
$avgTime = $total_questions > 0 ? round($time_spent / $total_questions) : 0;
$performanceLabel = $score_percent >= 90 ? 'Bardzo mocny wynik' : ($score_percent >= 70 ? 'Dobry wynik' : ($score_percent >= 50 ? 'Zaliczone' : 'Do poprawy'));

if (!$guestResultId) {
    // Fetch all answers for this result with question details
    $answers_stmt = $pdo->prepare("
        SELECT ta.question_id, ta.user_answer, ta.correct_answer, ta.is_correct,
               q.question_text, q.category AS question_category, q.explanation
        FROM test_answers ta
        LEFT JOIN questions q ON ta.question_id = q.id
        WHERE ta.result_id = :result_id
        ORDER BY ta.id
    ");
    $answers_stmt->execute(['result_id' => $result_id]);
    $answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fallback: Load questions for full text (handles both DB and JSON seamlessly)
$allQuestions = loadQuestions($pdo);
$questions_map = [];
foreach ($allQuestions as $q) {
    $questions_map[$q['id']] = $q;
}

$answerQualifications = [];
$categoryStats = [];
foreach ($answers as &$answer) {
    $questionId = (int)($answer['question_id'] ?? 0);
    $qualification = trim((string)($answer['question_category'] ?? ''));
    if ($qualification === '' && isset($questions_map[$questionId]['category'])) {
        $qualification = trim((string)$questions_map[$questionId]['category']);
    }
    if ($qualification === 'EE.08') $qualification = 'INF.02';
    if ($qualification === 'EE.09') $qualification = 'INF.03';
    $answer['qualification_label'] = $qualification;

    $user_answer = strtoupper(trim((string)($answer['user_answer'] ?? '')));
    $correct_answer = strtoupper(trim((string)($answer['correct_answer'] ?? '')));
    $is_correct = ((int)($answer['is_correct'] ?? 0) === 1) || ($user_answer !== '-' && $correct_answer !== '' && $user_answer === $correct_answer);

    if ($qualification !== '') {
        $answerQualifications[$qualification] = true;
        if (!isset($categoryStats[$qualification])) {
            $categoryStats[$qualification] = ['total' => 0, 'correct' => 0];
        }
        $categoryStats[$qualification]['total']++;
        if ($is_correct) {
            $categoryStats[$qualification]['correct']++;
        }
    }
}
unset($answer);
$showAnswerQualifications = true;

// Mode labels
$modeLabels = [
    'exam' => ['name' => 'Test', 'color' => 'primary', 'icon' => 'bi-journal-check'],
    'practice' => ['name' => 'Ćwiczenia', 'color' => 'success', 'icon' => 'bi-pencil'],
    'single' => ['name' => 'Pojedyncze', 'color' => 'info', 'icon' => 'bi-question-circle'],
    'exam_simulator' => ['name' => 'Egzamin', 'color' => 'dark', 'icon' => 'bi-pc-display-horizontal']
];
$modeInfo = $modeLabels[$mode] ?? ['name' => ucfirst($mode), 'color' => 'secondary', 'icon' => 'bi-file-text'];

// Determine pass/fail
$passed = $score_percent >= 50;

$resultUser = [
    'nickname' => 'Gość',
    'firstName' => '—',
    'lastName' => '—',
    'fullName' => 'Gość',
    'className' => '—',
];
if (!$guestResultId && isset($_SESSION['user_id'])) {
    try {
        $userStmt = $pdo->prepare('SELECT username, first_name, last_name, class FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([(int)$_SESSION['user_id']]);
        $resultUserRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($resultUserRow) {
            $resultUser['nickname'] = trim((string)($resultUserRow['username'] ?? '')) ?: '—';
            $resultUser['firstName'] = trim((string)($resultUserRow['first_name'] ?? '')) ?: '—';
            $resultUser['lastName'] = trim((string)($resultUserRow['last_name'] ?? '')) ?: '—';
            $resultUser['fullName'] = userDisplayName($resultUserRow);
            $classLabel = trim((string)($resultUserRow['class'] ?? ''));
            $resultUser['className'] = $classLabel !== '' ? strtoupper($classLabel) : '—';
        }
    } catch (Exception $e) {
        // Keep guest fallback labels.
    }
}

$resultSubtitle = $passed
    ? "Świetny wynik! Rozwiązałeś arkusz z sukcesem, zdobywając $correctAnswers na $total_questions punktów."
    : "Tym razem się nie udało, ale każdy błąd to lekcja. Zdobyłeś $correctAnswers na $total_questions punktów.";

$shareCardData = [
    'passed' => $passed,
    'performanceLabel' => $performanceLabel,
    'subtitle' => $resultSubtitle,
    'modeName' => $modeInfo['name'],
    'isHarvest' => ($mode === 'exam' && (int)$total_questions === 40 && (int)$time_spent <= 2400),
    'passLabel' => $passed ? 'Zaliczony' : 'Niezaliczony',
    'scorePercent' => (int)round($score_percent),
    'correctAnswers' => $correctAnswers,
    'totalQuestions' => $total_questions,
    'timeSpent' => formatTime($time_spent),
    'testDate' => !empty($test_date) ? date('d.m.Y H:i', strtotime($test_date)) : '-',
    'nickname' => $resultUser['nickname'],
    'firstName' => $resultUser['firstName'] ?? '—',
    'lastName' => $resultUser['lastName'] ?? '—',
    'fullName' => $resultUser['fullName'],
    'className' => $resultUser['className'],
    'brand' => 'ZSEM TECH',
    'brandSub' => 'Platforma egzaminacyjna',
    'brandUrl' => 'zsem-egzamin.online',
];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wynik testu - System Testów</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <link rel="stylesheet" href="assets/css/test.css">
    <style>
        /* ===== Result Hero ===== */
        .result-hero {
            background: <?php echo $passed ? 'linear-gradient(135deg, #0f9f75 0%, #047857 100%)' : 'linear-gradient(135deg, #f87171 0%, #dc2626 100%)'; ?>;
            color: white;
            border-radius: 18px;
            padding: 2.2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 34px rgba(15,23,42,0.10);
        }
        .result-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 66%);
            transform: rotate(30deg);
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255, 255, 255, 0.36);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .score-circle:hover { transform: scale(1.05); }
        .score-value { font-size: 3rem; font-weight: 800; line-height: 1; }
        .score-label { font-size: 0.875rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; font-weight: 600; }
        .stat-pill {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0.75rem 1.25rem;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            backdrop-filter: blur(8px);
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .stat-pill:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.25);
        }
        .stat-pill i { font-size: 1.35rem; opacity: 1; }

        /* ===== Action Buttons ===== */
        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .result-actions .btn {
            border-radius: 16px;
            padding: .75rem 1.75rem;
            font-weight: 600;
            font-size: .95rem;
            transition: all .3s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .result-actions .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
        }
        .result-actions .btn-outline-dark {
            background: var(--panel-bg, #fff);
            border-color: rgba(148,163,184,.3);
            color: var(--text-main, #1e293b);
        }
        .result-actions .btn-outline-dark:hover {
            border-color: var(--primary-color, #3b82f6);
            color: var(--primary-color-dark, #1d4ed8);
        }

        /* ===== Insight Cards ===== */
        .result-insights {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
        }
        .result-insight-card {
            border-radius: 18px;
            padding: 1.15rem 1.25rem;
            background: var(--panel-bg, #fff);
            border: 1px solid rgba(148, 163, 184, .18);
            box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .result-insight-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .1);
        }
        .result-insight-card .insight-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .result-insight-card .insight-icon.icon-success {
            background: rgba(16,185,129,.12);
            color: #10b981;
        }
        .result-insight-card .insight-icon.icon-danger {
            background: rgba(239,68,68,.12);
            color: #ef4444;
        }
        .result-insight-card .insight-icon.icon-info {
            background: rgba(59,130,246,.12);
            color: #3b82f6;
        }
        .result-insight-card .insight-icon.icon-warning {
            background: rgba(245,158,11,.12);
            color: #f59e0b;
        }
        .result-insight-card .insight-data {
            min-width: 0;
        }
        .result-insight-card .insight-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.2;
            color: var(--text-main, #1e293b);
        }
        .result-insight-card .insight-label {
            font-size: .78rem;
            color: var(--text-muted, #94a3b8);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ===== Filter Bar ===== */
        .answer-filter-bar {
            display: flex;
            gap: 0;
            background: rgba(148,163,184,.1);
            border-radius: 12px;
            padding: 3px;
        }
        .answer-filter-bar .btn {
            white-space: nowrap;
            border: none;
            border-radius: 10px;
            padding: .4rem .85rem;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-muted, #64748b);
            background: transparent;
            transition: all .2s ease;
        }
        .answer-filter-bar .btn:hover {
            color: var(--text-main, #1e293b);
        }
        .answer-filter-bar .btn.active {
            background: var(--panel-bg, #fff);
            color: var(--primary-color, #3b82f6);
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        /* ===== Accordion Answer Cards ===== */
        .answers-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .answer-card {
            border-bottom: 1px solid rgba(148,163,184,.12);
            border-left: 3px solid transparent;
            transition: background .2s, border-color .2s;
            cursor: pointer;
            opacity: 0;
            animation: cardFadeIn .4s ease forwards;
        }
        .answer-card:last-child { border-bottom: none; }
        .answer-card[data-answer-state="correct"] { border-left-color: #10b981; }
        .answer-card[data-answer-state="wrong"]   { border-left-color: #ef4444; }
        .answer-card:hover { background: rgba(59,130,246,.03); }
        .answer-card.open { background: rgba(59,130,246,.02); }

        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateX(-8px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .answer-card-header {
            display: flex;
            align-items: center;
            padding: .9rem 1.25rem;
            gap: .75rem;
            user-select: none;
        }
        .answer-card-num {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .82rem;
            flex-shrink: 0;
            color: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,.12);
        }
        .answer-card-num.correct { background: linear-gradient(135deg, #34d399, #059669); }
        .answer-card-num.wrong   { background: linear-gradient(135deg, #f87171, #dc2626); }
        .answer-card-text {
            flex: 1;
            min-width: 0;
            font-size: .9rem;
            line-height: 1.4;
            color: var(--text-main);
        }
        .answer-card-text .q-label {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .answer-card-badges {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-shrink: 0;
        }
        .answer-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            font-weight: 800;
            font-size: .82rem;
            line-height: 1;
        }
        .answer-badge.user-correct { background: rgba(16,185,129,.12); color: #10b981; }
        .answer-badge.user-wrong   { background: rgba(239,68,68,.12); color: #ef4444; }
        .answer-badge.correct-ref  { background: rgba(16,185,129,.12); color: #10b981; }

        .answer-card-chevron {
            flex-shrink: 0;
            color: var(--text-muted, #94a3b8);
            transition: transform .3s cubic-bezier(.4,0,.2,1);
            font-size: 1rem;
        }
        .answer-card.open .answer-card-chevron { transform: rotate(180deg); }

        .answer-card-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s cubic-bezier(.4,0,.2,1), padding .35s cubic-bezier(.4,0,.2,1);
            padding: 0 1.25rem;
        }
        .answer-card.open .answer-card-body {
            max-height: 760px;
            padding: .25rem 1.25rem 1.25rem;
        }

        .answer-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .55rem .85rem;
            border-radius: 12px;
            margin-bottom: .35rem;
            font-size: .85rem;
        }
        .answer-detail-row.your-answer { background: rgba(239,68,68,.06); }
        .answer-detail-row.your-answer.is-correct { background: rgba(16,185,129,.06); }
        .answer-detail-row.correct-answer { background: rgba(16,185,129,.06); }
        .answer-explanation {
            background: rgba(59,130,246,.08);
            border: 1px solid rgba(59,130,246,.14);
            border-radius: 12px;
            padding: .75rem .85rem;
            margin: .55rem 0;
            font-size: .86rem;
            line-height: 1.5;
            color: var(--text-main, #1e293b);
        }
        .answer-explanation-label {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-weight: 700;
            color: var(--primary-color-dark, #3b82f6);
            margin-bottom: .25rem;
        }
        .answer-distractors {
            border-top: 1px dashed rgba(59,130,246,.24);
            margin-top: .65rem;
            padding-top: .65rem;
            color: var(--text-muted, #64748b);
        }

        .answer-status-icon {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
        }
        .answer-status-icon.correct { background: rgba(16,185,129,.15); color: #10b981; }
        .answer-status-icon.wrong   { background: rgba(239,68,68,.15); color: #ef4444; }

        .answer-card .qual-badge {
            display: inline-block;
            font-size: .7rem;
            padding: .18rem .5rem;
            border-radius: 6px;
            background: rgba(59,130,246,.08);
            color: var(--primary-color-dark, #3b82f6);
            margin-top: .3rem;
            line-height: 1.2;
            font-weight: 500;
        }

        .answer-card-view-btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem 1rem;
            border-radius: 10px;
            border: 1px solid rgba(59,130,246,.2);
            background: rgba(59,130,246,.06);
            color: var(--primary-color-dark, #3b82f6);
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .25s ease;
            margin-top: .5rem;
        }
        .answer-card-view-btn:hover {
            background: rgba(59,130,246,.14);
            border-color: rgba(59,130,246,.35);
            transform: translateY(-1px);
        }

        /* ===== Animations ===== */
        .animate-in {
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes progress {
            0% { stroke-dasharray: 0 100; }
        }

        /* ===== Dark Mode ===== */
        body.dark-mode .category-breakdown-panel {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        body.dark-mode .result-insight-card {
            background: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .result-insight-card .insight-value { color: #f1f5f9; }
        body.dark-mode .result-actions .btn-outline-dark {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        body.dark-mode .answer-filter-bar { background: rgba(51,65,85,.4); }
        body.dark-mode .answer-filter-bar .btn { color: #94a3b8; }
        body.dark-mode .answer-filter-bar .btn:hover { color: #e2e8f0; }
        body.dark-mode .answer-filter-bar .btn.active {
            background: #1e293b;
            color: #60a5fa;
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
        }
        body.dark-mode .answer-card { border-bottom-color: rgba(51,65,85,.5); }
        body.dark-mode .answer-card:hover { background: rgba(59,130,246,.06); }
        body.dark-mode .answer-card.open { background: rgba(59,130,246,.04); }
        body.dark-mode .answer-card-view-btn {
            border-color: rgba(96,165,250,.25);
            background: rgba(96,165,250,.08);
            color: #60a5fa;
        }
        body.dark-mode .question-text { color: #e2e8f0; }

        /* ===== Tablet ===== */
        @media (max-width: 991.98px) {
            .result-hero { padding: 1.5rem; }
            .result-insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        /* ===== Mobile ===== */
        @media (max-width: 575.98px) {
            .score-circle { width: 130px; height: 130px; }
            .score-value { font-size: 2.3rem; }

            .result-actions {
                gap: .5rem;
            }
            .result-actions .btn {
                padding: .5rem 1rem;
                font-size: .8rem;
                border-radius: 12px;
            }
            .result-actions .btn i {
                margin-right: .35rem !important;
            }

            .result-insights {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .6rem;
            }
            .result-insight-card {
                padding: .85rem .9rem;
                border-radius: 14px;
                gap: .7rem;
            }
            .result-insight-card .insight-icon {
                width: 38px;
                height: 38px;
                border-radius: 11px;
                font-size: 1rem;
            }
            .result-insight-card .insight-value {
                font-size: 1.1rem;
            }
            .result-insight-card .insight-label {
                font-size: .7rem;
            }

            .detailed-answers-panel {
                padding: .5rem !important;
                border-radius: 18px !important;
            }
            .detailed-answers-panel .panel-header > .d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: .6rem;
            }
            .detailed-answers-panel .panel-header .d-flex.align-items-center.gap-2 {
                min-width: 0;
            }
            .detailed-answers-panel .panel-title {
                font-size: .9rem;
                line-height: 1.2;
            }

            .answer-filter-bar {
                width: 100%;
                border-radius: 10px;
            }
            .answer-filter-bar .btn {
                flex: 1;
                padding: .4rem .25rem;
                font-size: .75rem;
                border-radius: 8px;
            }

            .answer-card {
                border-left-width: 3px;
            }
            .answer-card-header {
                padding: .75rem .85rem;
                gap: .6rem;
            }
            .answer-card-num {
                width: 32px;
                height: 32px;
                font-size: .75rem;
                border-radius: 9px;
            }
            .answer-card-text {
                font-size: .82rem;
            }
            .answer-badge {
                width: 30px;
                height: 30px;
                font-size: .72rem;
                border-radius: 8px;
            }
            .answer-card.open .answer-card-body {
                padding: .25rem .85rem 1rem;
            }
            .answer-detail-row {
                padding: .45rem .65rem;
                font-size: .8rem;
                border-radius: 10px;
            }
            .answer-card-view-btn {
                width: 100%;
                justify-content: center;
                padding: .55rem;
                border-radius: 10px;
            }
        }

        /* ===== Share card preview modal ===== */
        .result-share-modal .modal-content {
            border: 0;
            border-radius: 24px;
            overflow: hidden;
            background: var(--panel-bg, #fff);
        }
        .result-share-modal .modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #fff;
            border: 0;
            padding: 1.25rem 1.5rem;
        }
        .result-share-modal .modal-title {
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        .result-share-modal .modal-body {
            padding: 1.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
        }
        body.dark-mode .result-share-modal .modal-body {
            background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
        }
        .result-share-preview-wrap {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
            background: #0f172a;
            line-height: 0;
        }
        .result-share-preview-wrap canvas,
        .result-share-preview-wrap img {
            width: 100%;
            height: auto;
            display: block;
        }
        .result-share-modal .modal-footer {
            border: 0;
            padding: 1rem 1.5rem 1.35rem;
            gap: 0.65rem;
        }
        .result-share-modal .btn-download-share {
            border-radius: 999px;
            font-weight: 700;
            padding: 0.65rem 1.35rem;
        }

        /* ===== Misc Legacy ===== */
        .question-text {
            color: var(--text-main, #1e293b);
            font-weight: 500;
            line-height: 1.5;
        }
        .badge-answer {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <!-- Result Hero -->
                    <div class="result-hero mb-4 animate-in <?= $passed ? 'result-hero-passed' : 'result-hero-failed' ?>">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <span class="badge" style="background-color: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                        <i class="bi <?php echo $modeInfo['icon']; ?> me-1"></i>
                                        Tryb: <?php echo htmlspecialchars($modeInfo['name']); ?>
                                    </span>
                                    <span class="badge" style="background-color: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                        <?php echo $passed ? '<i class="bi bi-check-circle-fill me-1"></i>Zaliczony' : '<i class="bi bi-x-circle-fill me-1"></i>Niezaliczony'; ?>
                                    </span>
                                </div>
                                <h1 class="display-4 fw-800 mb-3">
                                    <?php echo htmlspecialchars($performanceLabel); ?>
                                </h1>
                                <p class="lead opacity-90 mb-4" style="font-weight: 500;">
                                    <?php echo $passed 
                                        ? "Świetny wynik! Rozwiązałeś arkusz z sukcesem, zdobywając $correctAnswers na $total_questions punktów."
                                        : "Tym razem się nie udało, ale każdy błąd to lekcja. Zdobyłeś $correctAnswers na $total_questions punktów."; ?>
                                </p>
                                
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="stat-pill">
                                        <i class="bi bi-stopwatch"></i>
                                        <div>
                                            <div class="small opacity-80" style="font-size: 0.7rem; text-transform: uppercase;">Czas trwania</div>
                                            <div class="fw-bold"><?php echo formatTime($time_spent); ?></div>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <i class="bi bi-check2-all"></i>
                                        <div>
                                            <div class="small opacity-80" style="font-size: 0.7rem; text-transform: uppercase;">Poprawność</div>
                                            <div class="fw-bold"><?php echo $correctAnswers; ?> / <?php echo $total_questions; ?></div>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <i class="bi bi-calendar-event"></i>
                                        <div>
                                            <div class="small opacity-80" style="font-size: 0.7rem; text-transform: uppercase;">Data wykonania</div>
                                            <div class="fw-bold"><?php echo !empty($test_date) ? date('d.m.Y H:i', strtotime($test_date)) : '-'; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 d-flex justify-content-center mt-5 mt-lg-0">
                                <div class="score-circle" style="position: relative; width: 180px; height: 180px; background: transparent; border: none; box-shadow: none;">
                                    <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                        <path class="circle-bg"
                                            d="M18 2.0845
                                            a 15.9155 15.9155 0 0 1 0 31.831
                                            a 15.9155 15.9155 0 0 1 0 -31.831"
                                            style="fill: none; stroke: rgba(255, 255, 255, 0.2); stroke-width: 2.5;"
                                        />
                                        <path class="circle"
                                            stroke-dasharray="<?php echo round($score_percent); ?>, 100"
                                            d="M18 2.0845
                                            a 15.9155 15.9155 0 0 1 0 31.831
                                            a 15.9155 15.9155 0 0 1 0 -31.831"
                                            style="fill: none; stroke: #fff; stroke-width: 2.5; stroke-linecap: round; animation: progress 1.5s ease-out forwards;"
                                        />
                                    </svg>
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; width: 100%;">
                                        <span class="score-value" style="font-size: 2.5rem; font-weight: 800; display: block; line-height: 1;"><?php echo round($score_percent); ?>%</span>
                                        <span class="score-label" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; font-weight: 600;">Twój wynik</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="result-actions mb-4 animate-in" style="animation-delay: 0.1s;">
                        <a href="test.php?setup=1" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Nowy test
                        </a>
                        <a href="index.php" class="btn btn-outline-dark">
                            <i class="bi bi-grid-fill me-2"></i>Dashboard
                        </a>
                        <a href="user/progress.php" class="btn btn-outline-dark">
                            <i class="bi bi-clock-history me-2"></i>Historia
                        </a>
                        <button type="button" class="btn btn-outline-primary" id="saveResultImageBtn" data-bs-toggle="modal" data-bs-target="#resultShareModal">
                            <i class="bi bi-image me-2"></i>Udostępnij wynik
                        </button>
                    </div>

                    <div class="result-insights mb-4 animate-in" style="animation-delay: 0.15s;">
                        <div class="result-insight-card">
                            <div class="insight-icon icon-success"><i class="bi bi-check2-circle"></i></div>
                            <div class="insight-data">
                                <div class="insight-value text-success"><?php echo $correctAnswers; ?></div>
                                <div class="insight-label">Poprawne</div>
                            </div>
                        </div>
                        <div class="result-insight-card">
                            <div class="insight-icon icon-danger"><i class="bi bi-x-circle"></i></div>
                            <div class="insight-data">
                                <div class="insight-value text-danger"><?php echo $wrongAnswers; ?></div>
                                <div class="insight-label">Błędne</div>
                            </div>
                        </div>
                        <div class="result-insight-card">
                            <div class="insight-icon icon-info"><i class="bi bi-speedometer2"></i></div>
                            <div class="insight-data">
                                <div class="insight-value"><?php echo formatTime($avgTime); ?></div>
                                <div class="insight-label">Średnio / pytanie</div>
                            </div>
                        </div>
                        <div class="result-insight-card">
                            <div class="insight-icon icon-warning"><i class="bi bi-bullseye"></i></div>
                            <div class="insight-data">
                                <div class="insight-value"><?php echo round($score_percent, 1); ?>%</div>
                                <div class="insight-label">Skuteczność</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($categoryStats)): ?>
                    <!-- Category Breakdown -->
                    <div class="dashboard-panel category-breakdown-panel mb-4 animate-in" style="animation-delay: 0.18s;">
                        <div class="panel-inner-core">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-diagram-3 text-primary fs-4"></i>
                                <h5 class="panel-title mb-0">Wyniki według kwalifikacji</h5>
                            </div>
                            <div class="row g-3">
                                <?php foreach ($categoryStats as $cat => $stats): ?>
                                    <?php
                                    $catTotal = $stats['total'];
                                    $catCorrect = $stats['correct'];
                                    $catPercent = $catTotal > 0 ? round(($catCorrect / $catTotal) * 100) : 0;
                                    $barColor = $catPercent >= 70 ? 'bg-success' : ($catPercent >= 50 ? 'bg-warning' : 'bg-danger');
                                    ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded" style="background: rgba(148,163,184,.05); border: 1px solid rgba(148,163,184,.1);">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="fw-bold"><?php echo htmlspecialchars($cat); ?></span>
                                                <span class="fw-bold"><?php echo $catPercent; ?>% (<?php echo $catCorrect; ?>/<?php echo $catTotal; ?>)</span>
                                            </div>
                                            <div class="progress" style="height: 8px; border-radius: 4px; background-color: rgba(148,163,184,.2);">
                                                <div class="progress-bar <?php echo $barColor; ?>" role="progressbar" style="width: <?php echo $catPercent; ?>%" aria-valuenow="<?php echo $catPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Detailed Answers -->
                    <?php if (!empty($answers)): ?>
                    <div class="dashboard-panel detailed-answers-panel animate-in" style="animation-delay: 0.2s;">
                        <div class="panel-inner-core">
                            <div class="panel-header mb-0">
                                <div class="d-flex align-items-center justify-content-between w-100">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-list-stars text-primary fs-4"></i>
                                        <h5 class="panel-title mb-0">Szczegółowa analiza odpowiedzi</h5>
                                    </div>
                                    <div class="answer-filter-bar">
                                        <button type="button" class="btn btn-sm active" data-answer-filter="all">Wszystkie</button>
                                        <button type="button" class="btn btn-sm" data-answer-filter="correct">Poprawne</button>
                                        <button type="button" class="btn btn-sm" data-answer-filter="wrong">Błędne</button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="answers-list">
                                <?php foreach ($answers as $index => $answer): ?>
                                    <?php
                                    $user_answer = strtoupper(trim((string)($answer['user_answer'] ?? '')));
                                    $user_answer = $user_answer !== '' ? $user_answer : '-';
                                    $correct_answer = strtoupper(trim((string)($answer['correct_answer'] ?? '')));
                                    $is_correct = ((int)($answer['is_correct'] ?? 0) === 1) || ($user_answer !== '-' && $correct_answer !== '' && $user_answer === $correct_answer);
                                    
                                    $question_text = $answer['question_text'] ?? '';
                                    if (empty($question_text) && !empty($questions_map[$answer['question_id']])) {
                                        $question_text = $questions_map[$answer['question_id']]['question_text'] ?? '';
                                    }
                                    $question_source = $questions_map[(int)$answer['question_id']] ?? [];
                                    $correct_answer_text = '';
                                    if ($correct_answer !== '') {
                                        $correct_answer_text = trim((string)($question_source['option_' . strtolower($correct_answer)] ?? ''));
                                    }
                                    $user_answer_text = '';
                                    if ($user_answer !== '-' && $user_answer !== '') {
                                        $user_answer_text = trim((string)($question_source['option_' . strtolower($user_answer)] ?? ''));
                                    }
                                    $answer_explanation = trim((string)($answer['explanation'] ?? ($question_source['explanation'] ?? '')));
                                    if ($answer_explanation === '') {
                                        $question_for_explanation = $question_source;
                                        $question_for_explanation['question_text'] = $question_text;
                                        $question_for_explanation['correct_answer'] = $correct_answer;
                                        $question_for_explanation['option_' . strtolower($correct_answer)] = $correct_answer_text;
                                        if ($user_answer !== '-' && $user_answer !== '') {
                                            $question_for_explanation['option_' . strtolower($user_answer)] = $user_answer_text;
                                        }
                                        $answer_explanation = buildQuestionExplanation($question_for_explanation, $user_answer, $is_correct);
                                    }
                                    $answer_explanation_main = $answer_explanation;
                                    $answer_distractors = '';
                                    $why_marker = 'Dlaczego nie reszta?';
                                    $why_pos = mb_strpos($answer_explanation, $why_marker, 0, 'UTF-8');
                                    if ($why_pos !== false) {
                                        $answer_explanation_main = trim(mb_substr($answer_explanation, 0, $why_pos, 'UTF-8'));
                                        $answer_distractors = trim(mb_substr($answer_explanation, $why_pos, mb_strlen($answer_explanation, 'UTF-8'), 'UTF-8'));
                                    }
                                    ?>
                                    <div class="answer-card" data-answer-state="<?php echo $is_correct ? 'correct' : 'wrong'; ?>" data-question-id="<?php echo (int)$answer['question_id']; ?>" data-user-answer="<?php echo addslashes($user_answer); ?>" data-correct-answer="<?php echo addslashes($correct_answer); ?>" style="animation-delay: <?php echo min($index * 0.04, 1.2); ?>s">
                                        <div class="answer-card-header" data-answer-toggle role="button" tabindex="0" aria-expanded="false" onclick="toggleAnswerCard(this)">
                                            <div class="answer-card-num <?php echo $is_correct ? 'correct' : 'wrong'; ?>">
                                                <?php echo sprintf('%02d', $index + 1); ?>
                                            </div>
                                            <div class="answer-card-text">
                                                <div class="q-label"><?php echo htmlspecialchars($question_text); ?></div>
                                                <?php if ($showAnswerQualifications && !empty($answer['qualification_label'])): ?>
                                                    <span class="qual-badge">
                                                        <?php echo htmlspecialchars($answer['qualification_label']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="answer-card-badges">
                                                <span class="answer-badge <?php echo $is_correct ? 'user-correct' : 'user-wrong'; ?>"><?php echo htmlspecialchars($user_answer); ?></span>
                                            </div>
                                            <i class="bi bi-chevron-down answer-card-chevron"></i>
                                        </div>
                                        <div class="answer-card-body" data-answer-analysis>
                                            <div class="answer-detail-row your-answer <?php echo $is_correct ? 'is-correct' : ''; ?>">
                                                <span><i class="bi bi-person-fill me-2"></i>Twoja odpowiedź</span>
                                                <span class="fw-bold <?php echo $is_correct ? 'text-success' : 'text-danger'; ?>"><?php echo htmlspecialchars($user_answer); ?></span>
                                            </div>
                                            <div class="answer-detail-row correct-answer">
                                                <span><i class="bi bi-check-circle-fill me-2 text-success"></i>Poprawna odpowiedź</span>
                                                <span class="fw-bold text-success"><?php echo htmlspecialchars($correct_answer); ?></span>
                                            </div>
                                            <div class="answer-detail-row" style="background:transparent;">
                                                <span>
                                                    <span class="answer-status-icon <?php echo $is_correct ? 'correct' : 'wrong'; ?> me-2">
                                                        <i class="bi <?php echo $is_correct ? 'bi-check-lg' : 'bi-x-lg'; ?>"></i>
                                                    </span>
                                                    <?php echo $is_correct ? 'Poprawna' : 'Błędna'; ?>
                                                </span>
                                            </div>
                                            <div class="answer-explanation">
                                                <div class="answer-explanation-label">
                                                    <i class="bi bi-info-circle-fill"></i>
                                                    Wyjaśnienie
                                                </div>
                                                <div><?php echo nl2br(htmlspecialchars($answer_explanation_main)); ?></div>
                                                <?php if ($answer_distractors !== ''): ?>
                                                    <button type="button" class="answer-card-view-btn mt-2" data-distractors-toggle aria-expanded="false" onclick="event.stopPropagation(); toggleAnswerDistractors(this)">
                                                        <i class="bi bi-list-check"></i> Dlaczego nie reszta?
                                                    </button>
                                                    <div class="answer-distractors d-none" data-distractors-panel>
                                                        <?php echo nl2br(htmlspecialchars($answer_distractors)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="answer-card-view-btn" onclick="event.stopPropagation(); viewQuestion(<?php echo (int)$answer['question_id']; ?>, '<?php echo addslashes($user_answer); ?>', '<?php echo addslashes($correct_answer); ?>')">
                                                <i class="bi bi-eye"></i> Zobacz pytanie
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Share result preview modal -->
    <div class="modal fade result-share-modal" id="resultShareModal" tabindex="-1" aria-labelledby="resultShareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="resultShareModalLabel"><i class="bi bi-mortarboard-fill me-2"></i>Podgląd karty wyniku</h5>
                        <div class="small opacity-75">ZSEM TECH · sprawdź wygląd przed pobraniem</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                </div>
                <div class="modal-body">
                    <div class="result-share-preview-wrap" id="resultSharePreviewWrap">
                        <canvas id="resultSharePreviewCanvas" aria-label="Podgląd karty wyniku"></canvas>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between flex-wrap">
                    <span class="text-muted small align-self-center">PNG w wysokiej rozdzielczości · gotowe do udostępnienia</span>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Anuluj</button>
                        <button type="button" class="btn btn-primary btn-download-share" id="downloadResultShareBtn">
                            <i class="bi bi-download me-2"></i>Pobierz zdjęcie
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Detail Modal -->
    <div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; background-color: var(--panel-bg); color: var(--text-main);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalQuestionTitle">Podgląd pytania</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="modalQuestionText" class="h4 fw-medium mb-4" style="line-height: 1.5;"></div>
                    <div id="modalQuestionMeta" class="mb-4 d-none"></div>
                    <div id="modalImageContainer" class="mb-4 text-center d-none">
                        <img id="modalQuestionImage" src="" class="img-fluid rounded shadow-sm" alt="Ilustracja do pytania" loading="lazy" decoding="async">
                    </div>
                    <div id="modalAnswersContainer" class="d-flex flex-column gap-3">
                        <!-- Options will be injected here -->
                    </div>
                    <div id="modalQuestionExplanation" class="answer-explanation d-none mt-3"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        const questionsData = <?php echo json_encode($questions_map); ?>;
        const showAnswerQualifications = <?php echo $showAnswerQualifications ? 'true' : 'false'; ?>;
        const questionModal = new bootstrap.Modal(document.getElementById('questionModal'));

        function toggleAnswerCard(headerEl) {
            const card = headerEl.closest('.answer-card');
            const shouldOpen = !card.classList.contains('open');
            document.querySelectorAll('.answer-card.open').forEach(openCard => {
                if (openCard === card) return;
                openCard.classList.remove('open');
                openCard.querySelector('[data-answer-toggle]')?.setAttribute('aria-expanded', 'false');
                openCard.querySelectorAll('[data-distractors-panel]').forEach(panel => panel.classList.add('d-none'));
                openCard.querySelectorAll('[data-distractors-toggle]').forEach(button => button.setAttribute('aria-expanded', 'false'));
            });
            card.classList.toggle('open', shouldOpen);
            headerEl.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            if (!shouldOpen) {
                card.querySelectorAll('[data-distractors-panel]').forEach(panel => panel.classList.add('d-none'));
                card.querySelectorAll('[data-distractors-toggle]').forEach(button => button.setAttribute('aria-expanded', 'false'));
            }
        }

        function toggleAnswerDistractors(button) {
            const panel = button.closest('.answer-explanation')?.querySelector('[data-distractors-panel]');
            if (!panel) return;
            const willShow = panel.classList.contains('d-none');
            panel.classList.toggle('d-none', !willShow);
            button.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        }

        document.querySelectorAll('[data-answer-toggle]').forEach(toggle => {
            toggle.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                toggleAnswerCard(toggle);
            });
        });

        document.addEventListener('shown.bs.collapse', event => {
            const card = event.target.closest('.answer-card');
            if (!card) return;
            document.querySelectorAll('.answer-card.open').forEach(openCard => {
                if (openCard !== card) openCard.classList.remove('open');
            });
            card.classList.add('open');
        });

        document.querySelectorAll('[data-answer-filter]').forEach(button => {
            button.addEventListener('click', () => {
                const filter = button.dataset.answerFilter;
                document.querySelectorAll('[data-answer-filter]').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                document.querySelectorAll('.answer-card').forEach(card => {
                    card.hidden = filter !== 'all' && card.dataset.answerState !== filter;
                });
            });
        });

        function viewQuestion(id, userAns, correctAns) {
            const q = questionsData[id];
            if (!q) return;

            document.getElementById('modalQuestionText').innerText = q.question_text;
            const meta = document.getElementById('modalQuestionMeta');
            if (showAnswerQualifications && q.category) {
                meta.innerHTML = '<span class="badge bg-primary bg-opacity-10 text-primary">Kwalifikacja: ' + String(q.category).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char])) + '</span>';
                meta.classList.remove('d-none');
            } else {
                meta.innerHTML = '';
                meta.classList.add('d-none');
            }
            
            const imgContainer = document.getElementById('modalImageContainer');
            const img = document.getElementById('modalQuestionImage');
            if (q.image_url) {
                img.src = q.image_url;
                img.alt = 'Ilustracja do pytania: ' + (q.question_text || 'pytanie testowe').slice(0, 90);
                imgContainer.classList.remove('d-none');
            } else {
                img.removeAttribute('src');
                img.alt = 'Ilustracja do pytania';
                imgContainer.classList.add('d-none');
            }

            const container = document.getElementById('modalAnswersContainer');
            container.innerHTML = '';

            const options = {
                'A': q.option_a || q.a,
                'B': q.option_b || q.b,
                'C': q.option_c || q.c,
                'D': q.option_d || q.d
            };

            for (const [key, text] of Object.entries(options)) {
                if (!text || text.trim() === '') continue;

                const div = document.createElement('div');
                div.className = 'd-flex align-items-center p-3 rounded border-2 mb-2';
                
                let borderColor = 'var(--border-color)';
                let bgColor = 'var(--bg-color)';
                let icon = '';

                if (key === correctAns) {
                    borderColor = '#10b981';
                    bgColor = 'rgba(16, 185, 129, 0.2)';
                    icon = '<i class="bi bi-check-circle-fill text-success ms-auto"></i>';
                } else if (key === userAns) {
                    borderColor = '#ef4444';
                    bgColor = 'rgba(239, 68, 68, 0.2)';
                    icon = '<i class="bi bi-x-circle-fill text-danger ms-auto"></i>';
                }

                div.style.borderColor = borderColor;
                div.style.backgroundColor = bgColor;
                div.style.color = 'var(--text-main)';

                const keyEl = document.createElement('div');
                keyEl.className = 'fw-bold me-3 text-center';
                keyEl.style.cssText = 'width: 30px; height: 30px; line-height: 26px; border: 2px solid currentColor; border-radius: 50%; flex-shrink: 0;';
                keyEl.textContent = key;

                const textEl = document.createElement('div');
                textEl.className = 'flex-grow-1';
                textEl.textContent = text;

                div.appendChild(keyEl);
                div.appendChild(textEl);
                if (icon) {
                    const iconWrap = document.createElement('span');
                    iconWrap.innerHTML = icon;
                    div.appendChild(iconWrap.firstElementChild);
                }
                container.appendChild(div);
            }

            const explanationBox = document.getElementById('modalQuestionExplanation');
            const correctText = options[correctAns] || '';
            const userText = options[userAns] || '';
            let explanation = String(q.explanation || '').trim();
            if (!explanation) {
                const correctLabel = correctText ? `${correctAns}. ${correctText}` : correctAns;
                const userLabel = userText ? `${userAns}. ${userText}` : userAns;
                explanation = [
                    'Wyjaśnienie:',
                    `• Poprawna odpowiedź: ${correctLabel}.`,
                    userAns === correctAns
                        ? 'Wybrano poprawną odpowiedź.'
                        : `Wybrano: ${userLabel}.`
                ].filter(Boolean).join('\n');
            }
            explanationBox.innerHTML = '<div class="answer-explanation-label"><i class="bi bi-info-circle-fill"></i>Wyjaśnienie</div>';
            const explanationText = document.createElement('div');
            explanationText.textContent = explanation;
            explanationText.style.whiteSpace = 'pre-line';
            explanationBox.appendChild(explanationText);
            explanationBox.classList.remove('d-none');

            questionModal.show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js" integrity="sha384-HAH79XdRvHr6axVGh4xQWVCp14kcd32bNk4Xu0sHDHtFQ42n6BAM8ykvB47dGz6D" crossorigin="anonymous"></script>
    <script>
    window.resultShareCardData = <?php echo json_encode($shareCardData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // Confetti Effect for Passing Score
    <?php if ($passed): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const duration = 3 * 1000;
        const animationEnd = Date.now() + duration;
        const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 1000 };

        function randomInRange(min, max) {
            return Math.random() * (max - min) + min;
        }

        const interval = setInterval(function() {
            const timeLeft = animationEnd - Date.now();

            if (timeLeft <= 0) {
                return clearInterval(interval);
            }

            const particleCount = 50 * (timeLeft / duration);
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);
    });
    <?php endif; ?>
    </script>
    <script src="assets/js/result-share-card.js?v=<?php echo (int)@filemtime(__DIR__ . '/assets/js/result-share-card.js'); ?>"></script>
    <?php include 'includes/help_center.php'; ?>
</body>
</html>
