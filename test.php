<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
if (!isLoggedIn() && !isGuestMode()) {
    startGuestSession();
}
requireLogin(true);
$isGuest = isGuestMode();

if (isset($_GET['new']) && $_GET['new'] === '1') {
    unset($_SESSION['current_test'], $_SESSION['test_start_time'], $_SESSION['last_result_id']);
}

// finishTest logic moved to includes/functions.php
// ──────────────────────────────────────────────────────────────────────────────

// CSRF setup
generateCsrfToken();

$mode     = $_GET['mode']     ?? 'exam';
$category = $_GET['category'] ?? '';
$defaultCategoryCookie = trim(urldecode($_COOKIE['default_test_categories'] ?? ''));
if (!isset($_GET['category']) && $defaultCategoryCookie !== '') {
    $category = $defaultCategoryCookie;
}
$count    = isset($_GET['count']) ? (int)$_GET['count'] : null;
$defaultCountCookie = isset($_COOKIE['default_test_count']) ? (int)$_COOKIE['default_test_count'] : 0;
if (!isset($_GET['count'])) {
    $count = $defaultCountCookie > 0 ? $defaultCountCookie : 40;
}
$timeLimit = isset($_GET['time']) ? (int)$_GET['time'] : 60; // in minutes
$order    = $_GET['order']    ?? 'random';
$smart    = isset($_GET['smart']) && $_GET['smart'] === '1';
if ($isGuest) {
    $smart = false;
}

$difficulty = $_GET['difficulty'] ?? 'all';
$scope      = $_GET['scope']      ?? 'all';
$timeOption = $_GET['time_option'] ?? 'custom';
$timePerQuestion = isset($_GET['time_per_question']) ? (int)$_GET['time_per_question'] : 60;
$preset = $_GET['preset'] ?? '';

$presets = [
    'harvest' => ['count' => 40, 'time' => 40, 'label' => 'Harvest'],
    'training' => ['count' => 20, 'time' => 20, 'label' => 'Trening'],
    'quick' => ['count' => 6, 'time' => 10, 'label' => 'Szybka powtórka'],
];
if (isset($presets[$preset])) {
    $count = $presets[$preset]['count'];
    $timeLimit = $presets[$preset]['time'];
    $timeOption = 'custom';
}

if ($count < 1) $count = 1;
if ($count > 100) $count = 100;
if ($timeLimit < 1) $timeLimit = 1;
if ($timeLimit > 120) $timeLimit = 120;
if ($timePerQuestion < 15) $timePerQuestion = 15;
if ($timePerQuestion > 600) $timePerQuestion = 600;
if ($mode === 'single') {
    $count = 1;
    $timeLimit = 2;
    $timeOption = 'custom';
}

// Initialize a new test only if none active or mode changed
$needNewTest = !isset($_SESSION['current_test'])
    || $_SESSION['current_test']['mode'] !== $mode
    || (isset($_GET['new']) && $_GET['new'] === '1')
    || (isset($_GET['start']) && $_GET['start'] === '1');

$showSetup = (isset($_GET['setup']) && $_GET['setup'] === '1') 
             || ($mode === 'practice' && empty($category) && !isset($_GET['start']));

if ($needNewTest && !$showSetup && isset($_GET['start'])) {
    $allQuestions = loadQuestions($pdo, false);
    if (empty($allQuestions)) {
        $selectedQuestions = [];
    } else {
        $pool = $allQuestions;
        
        // Filter questions by multiple categories if selected (comma-separated or array)
        if (!empty($category)) {
            $categoriesToFilter = is_array($category) ? $category : explode(',', $category);
            $categoriesToFilter = array_filter(array_map('trim', $categoriesToFilter));
            if (!empty($categoriesToFilter)) {
                $pool = array_values(array_filter($allQuestions, function($q) use ($categoriesToFilter) {
                    return in_array($q['category'] ?? '', $categoriesToFilter, true);
                }));
            }
        }

        // Filter by difficulty (Easy / Medium / Hard)
        if ($difficulty !== 'all') {
            $pool = array_values(array_filter($pool, function($q) use ($difficulty) {
                // Classify difficulty on-the-fly deterministically
                $qid = (int)($q['id'] ?? 0);
                $text = $q['question_text'] ?? '';
                $hashVal = hexdec(substr(md5($text . $qid), 0, 4));
                $qDiff = 'medium';
                if (strlen($text) < 100 && ($hashVal % 3 === 0)) {
                    $qDiff = 'easy';
                } elseif (strlen($text) > 180 || ($hashVal % 3 === 2)) {
                    $qDiff = 'hard';
                }
                return $qDiff === $difficulty;
            }));
        }

        // Filter by scope (unseen / incorrect / exclude_correct)
        if ($scope !== 'all' && isset($_SESSION['user_id'])) {
            $userProgress = [];
            try {
                $progressStmt = $pdo->prepare("SELECT question_id, times_seen, times_correct FROM user_question_progress WHERE user_id = ?");
                $progressStmt->execute([$_SESSION['user_id']]);
                while ($row = $progressStmt->fetch(PDO::FETCH_ASSOC)) {
                    $userProgress[(int)$row['question_id']] = $row;
                }
            } catch (Exception $e) {
                error_log("Failed to load user progress in test init: " . $e->getMessage());
            }

            $pool = array_values(array_filter($pool, function($q) use ($scope, $userProgress) {
                $qid = (int)$q['id'];
                if ($scope === 'unseen') {
                    return !isset($userProgress[$qid]) || (int)$userProgress[$qid]['times_seen'] === 0;
                } elseif ($scope === 'incorrect') {
                    return isset($userProgress[$qid]) && (int)$userProgress[$qid]['times_correct'] < (int)$userProgress[$qid]['times_seen'];
                } elseif ($scope === 'exclude_correct') {
                    return !isset($userProgress[$qid]) || (int)$userProgress[$qid]['times_correct'] === 0;
                }
                return true;
            }));
        }

        if (empty($pool)) {
            $selectedQuestions = [];
        } else {
            switch ($mode) {
                case 'single':
                    if ($smart && isset($_SESSION['user_id'])) {
                        $selectedQuestions = getWeightedRandomQuestions($pdo, $pool, 1, $_SESSION['user_id']);
                    } else {
                        $selectedQuestions = getRandomQuestions($pool, 1);
                    }
                    break;
                case 'exam':
                    $examCount = min($count, count($pool));
                    if ($smart && isset($_SESSION['user_id'])) {
                        $selectedQuestions = getWeightedRandomQuestions($pdo, $pool, $examCount, $_SESSION['user_id']);
                    } else {
                        $selectedQuestions = getRandomQuestions($pool, $examCount);
                    }
                    break;
                case 'practice':
                default:
                    $pracCount = min($count, count($pool));
                    if ($order === 'sequential') {
                        $selectedQuestions = array_slice($pool, 0, $pracCount);
                    } else {
                        if ($smart && isset($_SESSION['user_id'])) {
                            $selectedQuestions = getWeightedRandomQuestions($pdo, $pool, $pracCount, $_SESSION['user_id']);
                        } else {
                            $selectedQuestions = getRandomQuestions($pool, $pracCount);
                        }
                    }
                    break;
            }
        }
    }
    
    // Calculate final time limit in seconds
    $timeLimitSeconds = $timeLimit * 60;
    if ($mode === 'single') {
        $timeLimitSeconds = 120;
    } elseif ($timeOption === 'unlimited') {
        $timeLimitSeconds = 0;
    } elseif ($timeOption === '30s') {
        $timeLimitSeconds = count($selectedQuestions) * 30;
    } elseif ($timeOption === '60s') {
        $timeLimitSeconds = count($selectedQuestions) * 60;
    } elseif ($timeOption === 'per_question_custom') {
        $timeLimitSeconds = count($selectedQuestions) * $timePerQuestion;
    }

    $excludeFromRanking = isset($_GET['unranked']) && $_GET['unranked'] === '1' ? 1 : 0;
    $_SESSION['current_test'] = [
        'mode'       => $mode,
        'questions'  => $selectedQuestions,
        'current'    => 0,
        'start_time' => time(),
        'time_limit' => $timeLimitSeconds,
        'answers'    => [],
        'phase'      => 'answering',
        'last_result'=> null,
        'exclude_from_ranking' => $excludeFromRanking,
    ];
    
    // Redirect to clean URL to prevent re-initialization on refresh or POST
    $cleanCategory = is_array($category) ? implode(',', $category) : $category;
    header('Location: test.php?mode=' . urlencode($mode) 
        . ( !empty($cleanCategory) ? '&category=' . urlencode($cleanCategory) : '' )
        . '&count=' . count($selectedQuestions)
        . '&time=' . $timeLimit
        . '&time_per_question=' . $timePerQuestion
        . (!empty($preset) ? '&preset=' . urlencode($preset) : '')
        . '&difficulty=' . urlencode($difficulty)
        . '&scope=' . urlencode($scope)
        . '&time_option=' . urlencode($timeOption));
    exit;
}

$test           = $_SESSION['current_test'] ?? null;
$questions      = $test['questions'] ?? [];
$totalQuestions = count($questions);

// ─── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$test) {
        header('Location: index.php');
        exit;
    }
    // CSRF Protection using standardized function
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Błąd bezpieczeństwa (CSRF).");
    }

    $action = $_POST['action'] ?? '';

    // ── FINISH EARLY ──────────────────────────────────────────────────────────
    if ($action === 'finish_early') {
        if (isGuestMode()) {
            $resultId = finishGuestTest($test);
            header('Location: result.php?guest=' . urlencode($resultId));
        } else {
            $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
            header('Location: result.php?id=' . $resultId);
        }
        exit;
    }

    // ── GO BACK TO PREVIOUS QUESTION ────────────────────────────────────────
    if ($action === 'previous_question') {
        $test['current'] = max(0, min($totalQuestions - 1, (int)($test['current'] ?? 0) - 1));
        $test['phase'] = 'answering';
        $test['last_result'] = null;
        $_SESSION['current_test'] = $test;
        header('Location: test.php?mode=' . urlencode($mode)
            . '&category=' . urlencode($category)
            . '&count=' . $count
            . '&time=' . $timeLimit);
        exit;
    }

    // ── ADVANCE TO NEXT QUESTION (from review phase) ──────────────────────────
    if ($action === 'next_question') {
        $test['current']++;
        $test['phase']       = 'answering';
        $test['last_result'] = null;
        
            if ($test['current'] >= $totalQuestions) {
                if ($mode === 'single') {
                    unset($_SESSION['current_test']);
                    header('Location: test.php?mode=single&start=1&new=1');
                    exit;
                }
                if (isGuestMode()) {
                    $resultId = finishGuestTest($test);
                    header('Location: result.php?guest=' . urlencode($resultId));
                } else {
                    $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
                    header('Location: result.php?id=' . $resultId);
                }
            exit;
        }
        
        $_SESSION['current_test'] = $test;
        // Redirect to avoid form re-submission
        header('Location: test.php?mode=' . urlencode($mode)
            . '&category=' . urlencode($category)
            . '&count=' . $count
            . '&time=' . $timeLimit);
        exit;
    }

    // ── SUBMIT ANSWER ─────────────────────────────────────────────────────────
    if ($action === 'submit_answer') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $userAnswer = strtoupper(trim($_POST['answer'] ?? ''));

        // Find current question
        $currentIdx = $test['current'];
        if (isset($questions[$currentIdx]) && (int)$questions[$currentIdx]['id'] === $questionId) {
            $q             = $questions[$currentIdx];
            $correctAnswer = strtoupper(trim((string)($q['correct_answer'] ?? '')));
            $isCorrect     = ($userAnswer === $correctAnswer);

            // Record answer
            $test['answers'][$currentIdx] = [
                'question_id' => $questionId,
                'user_answer' => $userAnswer,
                'correct'     => $isCorrect,
            ];

            // Update per-question progress stats
            if (!isGuestMode() && isset($_SESSION['user_id'])) {
                updateQuestionProgress($pdo, $_SESSION['user_id'], $questionId, $isCorrect);
            }

            if ($mode === 'exam') {
                // In exam mode, skip review and go to next question (or finish)
                $test['current']++;
                $test['phase'] = 'answering';
                
                if ($test['current'] >= $totalQuestions) {
                    if (isGuestMode()) {
                        $resultId = finishGuestTest($test);
                        header('Location: result.php?guest=' . urlencode($resultId));
                    } else {
                        $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
                        header('Location: result.php?id=' . $resultId);
                    }
                    exit;
                }
                
                $_SESSION['current_test'] = $test;
                header('Location: test.php?mode=exam');
                exit;
            } else {
                // In practice/single mode, show review info
                if ($mode === 'single' && !isGuestMode() && isset($_SESSION['user_id'])) {
                    $singleResultId = saveSingleQuestionResult($pdo, $_SESSION['user_id'], $q, $userAnswer, $isCorrect);
                    if ($singleResultId > 0) {
                        $_SESSION['last_result_id'] = $singleResultId;
                    }
                }
                $test['phase'] = 'reviewing';
                $test['last_result'] = [
                    'is_correct'     => $isCorrect,
                    'user_answer'    => $userAnswer,
                    'correct_answer' => $correctAnswer,
                    'is_last'        => ($currentIdx >= $totalQuestions - 1),
                ];
                $_SESSION['current_test'] = $test;
            }
        }
    }
}
// ──────────────────────────────────────────────────────────────────────────────

if ($test) {
    if (!empty($questions) && (int)($test['current'] ?? 0) >= $totalQuestions) {
        if (isGuestMode()) {
            $resultId = finishGuestTest($test);
            header('Location: result.php?guest=' . urlencode($resultId));
        } else {
            $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
            header('Location: result.php?id=' . $resultId);
        }
        exit;
    }
    $currentIdx      = $test['current'];
    $currentQuestion = $questions[$currentIdx] ?? null;
    $phase           = $test['phase'] ?? 'answering';
    $lastResult      = $test['last_result'] ?? null;
    $answeredCount   = count($test['answers'] ?? []);
    $isTestActive    = ($phase === 'answering' && !$showSetup);
    $savedAnswer     = strtoupper(trim((string)($test['answers'][$currentIdx]['user_answer'] ?? '')));
} else {
    $currentIdx      = 0;
    $currentQuestion = null;
    $phase           = 'none';
    $lastResult      = null;
    $answeredCount   = 0;
    $isTestActive    = false;
    $savedAnswer     = '';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'exam' ? 'Egzamin' : 'Test' ?> – System Edukacyjny INF.02</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <script src="assets/js/quiz-engine.js?v=<?= filemtime(__DIR__ . '/assets/js/quiz-engine.js') ?>" defer></script>
    <style>
        @media(max-width:576px) { .answer-option{padding:.9rem 2.5rem .9rem 1rem;} }
        .test-progress-panel .progress-heading {
            min-width: 0;
        }
        .test-progress-panel .progress-actions {
            min-width: max-content;
        }
        .test-progress-panel .timer-display {
            font-family: "Nunito", "Inter", sans-serif;
            font-variant-numeric: tabular-nums;
            min-width: 4.8rem;
            text-align: right;
            white-space: nowrap;
            line-height: 1;
        }
        .question-card .question-card-header h5 {
            font-family: "Nunito", "Inter", sans-serif;
            font-weight: 800;
        }
        .question-card .question-text-main {
            font-family: "Nunito", "Inter", sans-serif;
            font-size: 1.28rem;
            line-height: 1.55 !important;
            letter-spacing: 0;
        }
        .quiz-action-bar .btn {
            min-height: 48px;
        }
        
        .form-label {
            color: var(--text-main);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .form-text {
            color: var(--text-muted);
        }
        .question-card,
        .question-card .card-body {
            background: var(--panel-bg) !important;
            color: var(--text-main) !important;
        }
        .question-card .question-text-main,
        .question-card .answer-text,
        .question-card .answer-letter {
            color: var(--text-main) !important;
        }
        .question-card .answer-option {
            background: var(--panel-bg) !important;
            border-color: var(--border-color) !important;
        }
        .question-card .answer-option:hover,
        .question-card .answer-option.selected {
            background: rgba(59, 130, 246, .10) !important;
            border-color: var(--primary-color) !important;
        }
        body.dark-mode .question-card,
        body.dark-mode .question-card .card-body,
        body.dark-mode .question-card .answer-option {
            background: #1e293b !important;
            color: #e5e7eb !important;
        }
        body.dark-mode .question-card .question-text-main,
        body.dark-mode .question-card .answer-text,
        body.dark-mode .question-card .answer-letter {
            color: #f8fafc !important;
        }
        .question-card .answer-option.correct {
            background: rgba(34, 197, 94, .16) !important;
            border-color: #22c55e !important;
            box-shadow: inset 4px 0 0 #22c55e, 0 10px 24px rgba(34, 197, 94, .10) !important;
        }
        .question-card .answer-option.incorrect {
            background: rgba(239, 68, 68, .14) !important;
            border-color: #ef4444 !important;
            box-shadow: inset 4px 0 0 #ef4444, 0 10px 24px rgba(239, 68, 68, .10) !important;
        }
        .question-card .answer-option.correct .answer-letter {
            background: #22c55e !important;
            border-color: #22c55e !important;
            color: #fff !important;
        }
        .question-card .answer-option.incorrect .answer-letter {
            background: #ef4444 !important;
            border-color: #ef4444 !important;
            color: #fff !important;
        }

        /* ==================== PREMIUM SETUP SYSTEM ==================== */
        .premium-setup-container {
            max-width: 1050px;
            margin: 0 auto;
        }
        .setup-section-title {
            font-size: 0.95rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        .setup-section-title span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .setup-section-title i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        body.dark-mode .setup-section-title i {
            color: #60a5fa;
        }
        
        /* Custom styled category accordion */
        #categoryAccordion {
            border: 1px solid var(--border-color);
            border-radius: 16px !important;
            overflow: hidden;
            background: var(--panel-bg);
            margin-bottom: 0.5rem;
        }
        #categoryAccordion .accordion-item {
            background: var(--panel-bg) !important;
            border: none !important;
        }
        #categoryAccordion .accordion-button {
            background: var(--panel-bg) !important;
            color: var(--text-main) !important;
            box-shadow: none !important;
            border: none !important;
            padding: 1.15rem 1.5rem !important;
            font-size: 1.1rem;
            font-weight: 700;
            transition: background-color 0.2s ease;
        }
        #categoryAccordion .accordion-button:not(.collapsed) {
            background: rgba(59, 130, 246, 0.04) !important;
            color: var(--primary-color) !important;
            border-bottom: 1px solid var(--border-color) !important;
        }
        body.dark-mode #categoryAccordion .accordion-button:not(.collapsed) {
            background: rgba(96, 165, 250, 0.04) !important;
            color: #60a5fa !important;
        }
        
        /* Grid for categories */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .category-card {
            background: var(--panel-bg);
            border: 2px solid var(--border-color);
            border-radius: 18px;
            padding: 1rem 1.25rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.85rem;
            position: relative;
            user-select: none;
            min-height: 94px;
        }
        .category-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.08);
        }
        .category-card.selected {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.04);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.12);
        }
        
        body.dark-mode .category-card {
            background: #1e293b;
            border-color: #334155;
        }
        body.dark-mode .category-card:hover {
            border-color: #60a5fa;
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.06);
        }
        body.dark-mode .category-card.selected {
            border-color: #60a5fa;
            background: rgba(96, 165, 250, 0.04);
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.1);
        }

        .category-card .card-checkbox {
            width: 22px;
            height: 22px;
            border: 2px solid var(--border-color);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            margin-top: 0;
            flex-shrink: 0;
            background: transparent;
        }
        .category-card.selected .card-checkbox {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }
        body.dark-mode .category-card.selected .card-checkbox {
            background: #60a5fa;
            border-color: #60a5fa;
            color: #0f172a;
        }
        .category-card .card-checkbox i {
            font-size: 0.85rem;
            display: none;
            font-weight: bold;
        }
        .category-card.selected .card-checkbox i {
            display: block;
        }
        .category-card .card-info {
            flex-grow: 1;
            min-width: 0;
        }
        .category-card .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text-main);
            line-height: 1.3;
        }
        .category-card .card-desc {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.35;
        }
        
        .category-card .card-icon-wrapper {
            font-size: 1.4rem;
            color: var(--primary-color);
            flex-shrink: 0;
            margin-top: 0;
        }
        body.dark-mode .category-card .card-icon-wrapper {
            color: #60a5fa;
        }

        /* Modern segmented buttons */
        .segmented-control {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            background: rgba(148, 163, 184, 0.08);
            border-radius: 16px;
            padding: 0.35rem;
            gap: 0.25rem;
            border: 1px solid var(--border-color);
            width: 100%;
        }
        body.dark-mode .segmented-control {
            background: rgba(15, 23, 42, 0.3);
            border-color: #334155;
        }
        .segmented-btn {
            border: none;
            background: transparent;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            min-height: 48px;
            white-space: normal;
            line-height: 1.2;
            text-align: center;
        }
        .segmented-btn:hover {
            color: var(--text-main);
            background: rgba(148, 163, 184, 0.06);
        }
        .segmented-btn.active {
            background: var(--panel-bg);
            color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(148, 163, 184, 0.08);
        }
        body.dark-mode .segmented-btn.active {
            background: #334155;
            color: #60a5fa;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.04);
        }

        /* Custom grid cards for scopes */
        .scope-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .scope-card {
            background: var(--panel-bg);
            border: 2px solid var(--border-color);
            border-radius: 18px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            user-select: none;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        body.dark-mode .scope-card {
            background: #1e293b;
            border-color: #334155;
        }
        .scope-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.08);
        }
        body.dark-mode .scope-card:hover {
            border-color: #60a5fa;
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.06);
        }
        .scope-card.selected {
            border-color: var(--primary-color);
            background: rgba(59, 130, 246, 0.04);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.12);
        }
        body.dark-mode .scope-card.selected {
            border-color: #60a5fa;
            background: rgba(96, 165, 250, 0.04);
            box-shadow: 0 10px 25px rgba(96, 165, 250, 0.1);
        }
        .scope-card .scope-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(59, 130, 246, 0.08);
            color: var(--primary-color);
            display: grid;
            place-items: center;
            margin-bottom: 0.85rem;
            font-size: 1.6rem;
            transition: all 0.25s ease;
        }
        body.dark-mode .scope-card .scope-icon-wrapper {
            background: rgba(96, 165, 250, 0.08);
            color: #60a5fa;
        }
        .scope-card.selected .scope-icon-wrapper {
            background: var(--primary-color);
            color: #fff;
        }
        body.dark-mode .scope-card.selected .scope-icon-wrapper {
            background: #60a5fa;
            color: #0f172a;
        }
        .scope-card .scope-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: var(--text-main);
        }
        .scope-card .scope-desc {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.35;
        }

        /* Question count badges */
        .badge-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0;
            align-items: center;
        }
        .count-badge-btn {
            border: 2px solid var(--border-color);
            background: var(--panel-bg);
            border-radius: 12px;
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
            white-space: nowrap !important;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        body.dark-mode .count-badge-btn {
            background: #1e293b;
            border-color: #334155;
        }
        .count-badge-btn:hover {
            border-color: var(--primary-color);
            color: var(--text-main);
        }
        body.dark-mode .count-badge-btn:hover {
            border-color: #60a5fa;
        }
        .count-badge-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        body.dark-mode .count-badge-btn.active {
            background: #60a5fa;
            border-color: #60a5fa;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.2);
        }
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: .85rem;
            margin-bottom: 1.4rem;
        }
        .preset-btn {
            border: 2px solid var(--border-color);
            background: var(--panel-bg);
            color: var(--text-main);
            border-radius: 16px;
            padding: 1rem;
            text-align: left;
            transition: .2s ease;
            min-height: 92px;
        }
        .preset-btn:hover,
        .preset-btn.active {
            transform: translateY(-2px);
            border-color: var(--primary-color);
            box-shadow: 0 12px 24px rgba(59, 130, 246, .10);
        }
        .preset-btn.harvest,
        .preset-btn.harvest.active {
            border-color: rgba(220, 38, 38, .55);
            background: linear-gradient(135deg, rgba(220, 38, 38, .14), rgba(248, 113, 113, .06));
        }
        .preset-title {
            display: flex;
            align-items: center;
            gap: .45rem;
            font-weight: 900;
            margin-bottom: .25rem;
        }
        .preset-desc {
            color: var(--text-muted);
            font-size: .8rem;
            line-height: 1.35;
            margin: 0;
        }

        /* Custom sliding panel for timer */
        .time-slider-panel {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(148, 163, 184, 0.05);
            border-radius: 16px;
            padding: 0 1.25rem;
            border: 0 solid var(--border-color);
        }
        body.dark-mode .time-slider-panel {
            background: rgba(15, 23, 42, 0.2);
        }
        .time-slider-panel.open {
            max-height: 180px;
            padding: 1.25rem;
            border-width: 1px;
            margin-top: 1rem;
        }
        .custom-range-slider {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: var(--border-color);
            outline: none;
            margin-top: 1rem;
        }
        .time-display-bubble {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 0.15rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        body.dark-mode .time-display-bubble {
            color: #60a5fa;
        }

        /* Toggle switches as sleek cards */
        .switch-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            transition: all 0.25s ease;
        }
        body.dark-mode .switch-card {
            background: #1e293b;
            border-color: #334155;
        }
        .switch-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.04);
        }
        body.dark-mode .switch-card:hover {
            border-color: #60a5fa;
        }
        .switch-card-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .switch-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(59, 130, 246, 0.08);
            color: var(--primary-color);
            display: grid;
            place-items: center;
            font-size: 1.35rem;
        }
        body.dark-mode .switch-card-icon {
            background: rgba(96, 165, 250, 0.08);
            color: #60a5fa;
        }
        .switch-card-label {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
            color: var(--text-main);
        }
        .switch-card-desc {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        /* Responsive UI refinements for Test Configuration */
        @media (max-width: 768px) {
            .category-grid {
                grid-template-columns: 1fr;
            }
            .scope-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }
        @media (max-width: 576px) {
            .test-progress-panel {
                padding: 0.85rem !important;
            }
            .test-progress-panel .progress-top {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: flex-start !important;
                gap: 0.75rem;
                margin-bottom: 0.75rem !important;
            }
            .test-progress-panel .progress-heading {
                display: contents;
            }
            .test-progress-panel .progress-heading .small {
                font-size: 0.72rem;
                grid-column: 1;
                grid-row: 1;
            }
            .test-progress-panel .progress-heading strong {
                font-size: 1rem;
                line-height: 1.15;
                grid-column: 1 / -1;
                grid-row: 2;
                white-space: nowrap;
            }
            .test-progress-panel .progress-actions {
                gap: 0.5rem !important;
                flex-wrap: nowrap !important;
                grid-column: 2;
                grid-row: 1;
            }
            .test-progress-panel .timer-display {
                font-size: 1.05rem !important;
                min-width: 4.35rem;
            }
            .test-progress-panel .btn {
                width: 2.4rem;
                height: 2.4rem;
                padding: 0 !important;
                display: inline-grid;
                place-items: center;
                border-radius: 999px !important;
            }
            .test-progress-panel .btn span {
                display: none;
            }
            .question-card {
                padding: 1rem !important;
            }
            .question-card .question-card-header {
                margin-bottom: 1rem !important;
                gap: 0.75rem;
            }
            .question-card .question-card-header h5 {
                font-size: 1rem;
            }
            .question-card .badge {
                font-size: 0.68rem;
                padding: 0.35rem 0.6rem !important;
            }
            .question-card .question-text-main {
                font-size: 1.06rem;
                line-height: 1.45 !important;
                margin-bottom: 1rem !important;
            }
            .quiz-action-bar,
            .quiz-primary-actions {
                width: 100%;
            }
            .quiz-action-bar {
                align-items: stretch !important;
            }
            .quiz-primary-actions {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 0.6rem !important;
            }
            .quiz-primary-actions .btn {
                width: 100%;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
                font-size: 0.9rem;
            }
            .quiz-action-bar > a {
                width: 100%;
            }
            .segmented-control {
                grid-template-columns: 1fr;
                border-radius: 12px;
            }
            .segmented-btn {
                padding: 0.6rem 0.8rem;
                font-size: 0.8rem;
                border-radius: 8px;
            }
            .switch-card {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            .switch-card .form-switch {
                align-self: flex-end;
            }
            .scope-grid {
                grid-template-columns: 1fr;
            }
            .premium-setup-container h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid quiz-container p-0">
    <?php if ($showSetup): ?>
    <?php
    // Helper to get beautiful metadata for categories
    if (!function_exists('getCategoryMeta')) {
        function getCategoryMeta($catName) {
            $meta = [
                'default' => [
                    'icon' => 'bi-folder-fill',
                    'desc' => 'Pytania z wybranej kategorii podstawowej.',
                    'color' => '#3b82f6'
                ],
                'Systemy operacyjne' => [
                    'icon' => 'bi-window-sidebar',
                    'desc' => 'Instalacja, konfiguracja i administrowanie systemami Windows i Linux.',
                    'color' => '#06b6d4'
                ],
                'Urządzenia techniki komputerowej' => [
                    'icon' => 'bi-cpu-fill',
                    'desc' => 'Budowa, montaż, diagnostyka i serwisowanie sprzętu komputerowego.',
                    'color' => '#f59e0b'
                ],
                'Sieci komputerowe' => [
                    'icon' => 'bi-globe-americas',
                    'desc' => 'Projektowanie sieci LAN/WAN, protokoły sieciowe i konfiguracja urządzeń.',
                    'color' => '#10b981'
                ],
                'BHP' => [
                    'icon' => 'bi-shield-fill-check',
                    'desc' => 'Zasady bezpieczeństwa, higieny pracy i ergonomii w informatyce.',
                    'color' => '#ef4444'
                ]
            ];
            
            foreach ($meta as $key => $value) {
                if (mb_strtolower($catName) === mb_strtolower($key)) {
                    return $value;
                }
            }
            return $meta['default'];
        }
    }
    
    // Parse selected categories
    $selectedCats = array_filter(array_map('trim', explode(',', $category)));
    ?>
    <!-- ── Test configuration selector ────────────────────────────────── -->
    <div class="dashboard-panel animate-in premium-setup-container">
        <div class="panel-header d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h3 class="mb-0 fw-extrabold text-primary d-flex align-items-center gap-2">
                <i class="bi bi-sliders2-vertical"></i>Konfiguracja testu
            </h3>
            <?php if (isset($_SESSION['current_test'])): ?>
                <a href="test.php" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                    <i class="bi bi-play-circle me-1"></i>Kontynuuj aktywny test
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <form method="GET" class="row g-4 exam-setup-compact" id="premiumSetupForm">
                <!-- Start Button on Top -->
                <div class="col-12 mb-2 d-flex flex-column gap-1 text-center">
                    <button type="submit" class="btn btn-primary btn-sm fw-bold py-2 px-3 rounded-pill shadow-sm w-100 exam-setup-compact-btn">
                        <i class="bi bi-play-fill me-1"></i>
                        <?= ($mode === 'single') ? 'Wyświetl Pytanie' : 'Rozpocznij test' ?>
                    </button>
                </div>
                <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                <input type="hidden" name="start" value="1">
                <input type="hidden" name="category" id="categoryInput" value="<?= htmlspecialchars($category) ?>">
                <input type="hidden" name="difficulty" id="difficultyInput" value="<?= htmlspecialchars($difficulty) ?>">
                <input type="hidden" name="scope" id="scopeInput" value="<?= htmlspecialchars($scope) ?>">
                <input type="hidden" name="time_option" id="timeOptionInput" value="<?= htmlspecialchars($timeOption) ?>">
                <input type="hidden" name="preset" id="presetInput" value="<?= htmlspecialchars($preset) ?>">
                <input type="hidden" name="order" id="orderInput" value="<?= htmlspecialchars($order) ?>">
                
                <!-- Kategorie -->
                <div class="col-12 mb-2 exam-setup-compact-cats">
                    <div class="accordion" id="categoryAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header" id="categoryHeading">
                                <button class="accordion-button collapsed d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#categoryCollapse" aria-expanded="false" aria-controls="categoryCollapse">
                                    <i class="bi bi-collection-fill me-2"></i>Kategorie pytań
                                </button>
                            </h2>
                            <div id="categoryCollapse" class="accordion-collapse collapse" aria-labelledby="categoryHeading" data-bs-parent="#categoryAccordion">
                                <div class="accordion-body px-3 pt-3 pb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 border-bottom pb-2">
                                        <span class="text-muted small">Wybierz kategorie do wylosowania pytań:</span>
                                        <div class="d-flex gap-2 flex-wrap align-items-center">
                                            <button type="button" class="btn btn-sm btn-link text-success text-decoration-none fw-bold p-0 exam-cat-select-all" id="selectAllCats"><i class="bi bi-check2-all me-1"></i>Zaznacz wszystkie</button>
                                            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none fw-bold p-0 exam-cat-deselect-all" id="deselectAllCats"><i class="bi bi-x-lg me-1"></i>Odznacz wszystkie</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1" id="saveDefaultCategoryBtn"><i class="bi bi-bookmark-star me-1"></i>Zapisz jako domyślną</button>
                                        </div>
                                    </div>
                                    <div class="category-grid">
                                        <?php 
                                        $cats = getPublicCategories($pdo); 
                                        foreach ($cats as $cat): 
                                            $catMeta = getCategoryMeta($cat);
                                            $isSelected = in_array($cat, $selectedCats);
                                        ?>
                                            <div class="category-card <?= $isSelected ? 'selected' : '' ?>" data-category="<?= htmlspecialchars($cat) ?>">
                                                <div class="card-checkbox">
                                                    <i class="bi bi-check"></i>
                                                </div>
                                                <div class="card-icon-wrapper" style="color: <?= $catMeta['color'] ?>;">
                                                    <i class="bi <?= $catMeta['icon'] ?>"></i>
                                                </div>
                                                <div class="card-info">
                                                    <div class="card-title"><?= htmlspecialchars($cat) ?></div>
                                                    <div class="card-desc"><?= htmlspecialchars($catMeta['desc']) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liczba pytań i czas -->
                <div class="row exam-setup-compact-row">
                    <div class="col-md-6 mt-3 exam-setup-compact-col">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <label class="setup-section-title mb-0"><span><i class="bi bi-question-circle"></i>Liczba pytań</span></label>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" id="saveDefaultCountBtn"><i class="bi bi-bookmark-star me-1"></i>Domyślna liczba</button>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <input type="number" name="count" id="questionCountInput" class="form-control form-control-lg fw-bold text-center" 
                                   value="<?= $count ?>" min="1" max="100" style="width: 100px; border-radius: 12px; height: 48px;">
                            <div class="badge-group flex-grow-1">
                                <button type="button" class="count-badge-btn <?= $count === 5 ? 'active' : '' ?>" data-value="5">5</button>
                                <button type="button" class="count-badge-btn <?= $count === 10 ? 'active' : '' ?>" data-value="10">10</button>
                                <button type="button" class="count-badge-btn <?= $count === 20 ? 'active' : '' ?>" data-value="20">20</button>
                                <button type="button" class="count-badge-btn <?= $count === 40 ? 'active' : '' ?>" data-value="40">40</button>
                                <button type="button" class="count-badge-btn <?= $count === 80 ? 'active' : '' ?>" data-value="80">80</button>
                                <button type="button" class="count-badge-btn <?= $count === 100 ? 'active' : '' ?>" data-value="100">100</button>
                            </div>
                        </div>
                        <div class="form-text small mt-1">Maksymalnie 100 pytań na jedną próbę</div>
                    </div>
                    <div class="col-md-6 mt-3 exam-setup-compact-col">
                        <label class="setup-section-title"><span><i class="bi bi-clock"></i>Opcje czasu</span></label>
                        <div class="segmented-control">
                            <button type="button" class="segmented-btn time-option-btn <?= $timeOption === 'unlimited' ? 'active' : '' ?>" data-value="unlimited">
                                <i class="bi bi-infinity"></i>Bez limitu
                            </button>
                            <button type="button" class="segmented-btn time-option-btn <?= $timeOption === '30s' ? 'active' : '' ?>" data-value="30s">
                                <i class="bi bi-lightning"></i>30s / pyt.
                            </button>
                            <button type="button" class="segmented-btn time-option-btn <?= $timeOption === '60s' ? 'active' : '' ?>" data-value="60s">
                                <i class="bi bi-stopwatch"></i>60s / pyt.
                            </button>
                            <button type="button" class="segmented-btn time-option-btn <?= $timeOption === 'per_question_custom' ? 'active' : '' ?>" data-value="per_question_custom">
                                <i class="bi bi-hourglass"></i>Własny / pyt.
                            </button>
                            <button type="button" class="segmented-btn time-option-btn <?= $timeOption === 'custom' ? 'active' : '' ?>" data-value="custom">
                                <i class="bi bi-sliders"></i>Własny
                            </button>
                        </div>
                        <!-- Custom Time Sliding Panel -->
                        <div class="time-slider-panel <?= $timeOption === 'custom' ? 'open' : '' ?>">
                            <div class="time-display-bubble">
                                <i class="bi bi-hourglass-split"></i> <span id="timeLimitValue"><?= $timeLimit ?></span> min.
                            </div>
                            <input type="range" name="time" id="timeLimitInput" class="custom-range-slider" min="1" max="120" value="<?= $timeLimit ?>">
                            <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.75rem;">
                                <span>1 minuta</span>
                                <span>60 minut</span>
                                <span>120 minut</span>
                            </div>
                        </div>
                        <div class="time-slider-panel <?= $timeOption === 'per_question_custom' ? 'open' : '' ?>" id="timePerQuestionPanel">
                            <div class="time-display-bubble">
                                <i class="bi bi-stopwatch"></i> <span id="timePerQuestionValue"><?= $timePerQuestion ?></span> sek. / pyt.
                            </div>
                            <input type="range" name="time_per_question" id="timePerQuestionInput" class="custom-range-slider" min="15" max="600" step="5" value="<?= $timePerQuestion ?>">
                            <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.75rem;">
                                <span>15 sek.</span>
                                <span>5 minut</span>
                                <span>10 minut</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accordion: Pozostałe opcje -->
                <div class="col-12">
                    <button class="btn btn-outline-secondary w-100 d-md-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#examOptionsCollapse" aria-expanded="false" aria-controls="examOptionsCollapse">
                        <i class="bi bi-sliders2"></i> Więcej opcji
                    </button>
                    <div class="collapse d-md-block" id="examOptionsCollapse">
                <div class="row">
                <div class="col-md-6">
                    <label class="setup-section-title"><span><i class="bi bi-shield-shaded"></i>Poziom trudności</span></label>
                    <div class="segmented-control">
                        <button type="button" class="segmented-btn difficulty-btn <?= $difficulty === 'all' ? 'active' : '' ?>" data-value="all">
                            <i class="bi bi-border-all"></i>Wszystkie
                        </button>
                        <button type="button" class="segmented-btn difficulty-btn text-success <?= $difficulty === 'easy' ? 'active' : '' ?>" data-value="easy">
                            <i class="bi bi-emoji-smile"></i>Łatwy
                        </button>
                        <button type="button" class="segmented-btn difficulty-btn text-warning <?= $difficulty === 'medium' ? 'active' : '' ?>" data-value="medium">
                            <i class="bi bi-emoji-neutral"></i>Średni
                        </button>
                        <button type="button" class="segmented-btn difficulty-btn text-danger <?= $difficulty === 'hard' ? 'active' : '' ?>" data-value="hard">
                            <i class="bi bi-emoji-frown"></i>Trudny
                        </button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="setup-section-title"><span><i class="bi bi-sort-down-alt"></i>Kolejność pytań</span></label>
                    <div class="segmented-control">
                        <button type="button" class="segmented-btn order-btn <?= $order === 'random' ? 'active' : '' ?>" data-value="random">
                            <i class="bi bi-shuffle"></i>Losowa
                        </button>
                        <button type="button" class="segmented-btn order-btn <?= $order === 'sequential' ? 'active' : '' ?>" data-value="sequential">
                            <i class="bi bi-sort-numeric-down"></i>Po kolei
                        </button>
                    </div>
                </div>

                <!-- Scope of Questions -->
                <div class="col-12 mt-4">
                    <label class="setup-section-title"><span><i class="bi bi-bullseye"></i>Zakres pytań</span></label>
                    <div class="scope-grid">
                        <div class="scope-card <?= $scope === 'all' ? 'selected' : '' ?>" data-value="all">
                            <div class="scope-icon-wrapper"><i class="bi bi-collection"></i></div>
                            <div class="scope-title">Wszystkie pytania</div>
                            <div class="scope-desc">Dostęp do pełnej puli pytań z wybranych kategorii.</div>
                        </div>
                        <div class="scope-card <?= $scope === 'unseen' ? 'selected' : '' ?>" data-value="unseen">
                            <div class="scope-icon-wrapper"><i class="bi bi-eye-slash"></i></div>
                            <div class="scope-title">Tylko nowe</div>
                            <div class="scope-desc">Pokazuje tylko pytania, na które jeszcze nie odpowiadałeś.</div>
                        </div>
                        <div class="scope-card <?= $scope === 'incorrect' ? 'selected' : '' ?>" data-value="incorrect">
                            <div class="scope-icon-wrapper"><i class="bi bi-x-circle"></i></div>
                            <div class="scope-title">Błędne odpowiedzi</div>
                            <div class="scope-desc">Skup się na pytaniach, na które odpowiedziałeś błędnie.</div>
                        </div>
                        <div class="scope-card <?= $scope === 'exclude_correct' ? 'selected' : '' ?>" data-value="exclude_correct">
                            <div class="scope-icon-wrapper"><i class="bi bi-shield-slash"></i></div>
                            <div class="scope-title">Wyklucz poprawne</div>
                            <div class="scope-desc">Omija pytania, na które udzieliłeś już poprawnej odpowiedzi.</div>
                        </div>
                    </div>
                </div>

                <?php if ($mode !== 'single'): ?>
                <div class="col-12 mt-4">
                    <label class="setup-section-title"><span><i class="bi bi-stars"></i>Gotowe tryby</span></label>
                    <div class="preset-grid">
                        <button type="button" class="preset-btn harvest preset-mode-btn <?= $preset === 'harvest' ? 'active' : '' ?>" data-preset="harvest" data-count="40" data-time="40">
                            <div class="preset-title"><i class="bi bi-fire text-danger"></i>Harvest</div>
                            <p class="preset-desc">40 pytań, 40 minut. Pełny czerwony sprint rankingowy.</p>
                        </button>
                        <button type="button" class="preset-btn preset-mode-btn <?= $preset === 'training' ? 'active' : '' ?>" data-preset="training" data-count="20" data-time="20">
                            <div class="preset-title"><i class="bi bi-lightning-charge text-primary"></i>Trening</div>
                            <p class="preset-desc">20 pytań, 20 minut. Standardowa sesja ćwiczeniowa.</p>
                        </button>
                        <button type="button" class="preset-btn preset-mode-btn <?= $preset === 'quick' ? 'active' : '' ?>" data-preset="quick" data-count="6" data-time="10">
                            <div class="preset-title"><i class="bi bi-stopwatch text-success"></i>Szybka powtórka</div>
                            <p class="preset-desc">6 pytań, 10 minut. Krótkie utrwalenie przed lekcją.</p>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Switches (Smart Repetition & Unranked Mode) -->
                <div class="col-12 mt-4">
                    <div class="row g-3">
                        <?php if ($isGuest): ?>
                        <div class="col-12">
                            <div class="alert alert-info border-0 mb-0">
                                <i class="bi bi-incognito me-2"></i>Tryb gościa: bez historii, rankingu, misji i inteligentnego losowania.
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-md-6">
                            <label class="switch-card" for="smartSwitch" style="cursor: pointer; margin-bottom: 0;">
                                <div class="switch-card-info">
                                    <div class="switch-card-icon"><i class="bi bi-brain"></i></div>
                                    <div>
                                        <div class="switch-card-label">Inteligentne losowanie</div>
                                        <div class="switch-card-desc">Częstsze pokazywanie pytań, w których popełniasz błędy (algorytm Spaced Repetition).</div>
                                    </div>
                                </div>
                                <div class="form-check form-switch p-0 m-0">
                                    <input class="form-check-input m-0" type="checkbox" id="smartSwitch" name="smart" value="1" <?= $smart ? 'checked' : '' ?> style="width: 2.8em; height: 1.4em; cursor: pointer;">
                                </div>
                            </label>
                        </div>
                        
                        <?php if ($mode !== 'single'): ?>
                        <div class="col-md-6">
                            <label class="switch-card" for="unrankedSwitch" style="cursor: pointer; margin-bottom: 0;">
                                <div class="switch-card-info">
                                    <div class="switch-card-icon"><i class="bi bi-eye-slash-fill"></i></div>
                                    <div>
                                        <div class="switch-card-label">Nie wliczaj do rankingu</div>
                                        <div class="switch-card-desc font-weight-normal">Rozwiąż test treningowo bez wpływu na punkty rankingu głównego.</div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1">
                                    <div class="form-check form-switch p-0 m-0">
                                        <input class="form-check-input m-0" type="checkbox" id="unrankedSwitch" name="unranked" value="1" style="width: 2.8em; height: 1.4em; cursor: pointer;">
                                    </div>
                                    <span class="badge bg-primary bg-opacity-10 text-muted px-2 py-1 rounded-pill" id="unrankedInfo" style="color: var(--text-muted) !important; font-weight: 600; font-size: 0.68rem; max-width: 150px; white-space: normal; text-align: right;">Limit...</span>
                                </div>
                            </label>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Link powrotu na dół -->
                <div class="col-12 mt-3 d-flex flex-column gap-2 text-center">
                    <a href="index.php" class="btn btn-link text-muted mt-2 text-decoration-none"><i class="bi bi-arrow-left-short me-1"></i>Wróć do panelu głównego</a>
                </div>
                </div> <!-- /collapse/row -->
            </div> <!-- /accordion -->

                <?php if (!$isGuest && $mode !== 'single'): ?>
                <script>
                // Check unranked usage for today
                fetch('ajax/check_unranked.php')
                    .then(r => r.json())
                    .then(data => {
                        const info = document.getElementById('unrankedInfo');
                        const sw = document.getElementById('unrankedSwitch');
                        if (!info) return;
                        const remaining = 2 - (data.used || 0);
                        if (remaining <= 0) {
                            info.textContent = 'Wykorzystano limit (2/2 dzisiaj)';
                            if (sw) sw.disabled = true;
                        } else {
                            info.textContent = `Pozostało ${remaining} z 2 użyć`;
                        }
                        const countInput = document.getElementById('questionCountInput');
                        const syncRankingInfo = () => {
                            if (!countInput || !sw) return;
                            const count = Number(countInput.value || 0);
                            if (count < 40) {
                                info.textContent = 'Mniej niż 40 pytań - automatycznie trening';
                                sw.checked = true;
                                sw.disabled = true;
                            } else if (remaining <= 0) {
                                info.textContent = 'Wykorzystano limit (2/2 dzisiaj)';
                                sw.checked = false;
                                sw.disabled = true;
                            } else {
                                info.textContent = `Pozostało ${remaining} z 2 użyć`;
                                sw.disabled = false;
                            }
                        };
                        countInput?.addEventListener('input', syncRankingInfo);
                        syncRankingInfo();
                    })
                    .catch(() => {
                        const info = document.getElementById('unrankedInfo');
                        if (info) info.textContent = 'Dostępne 2 razy dziennie';
                    });
                </script>
                <?php endif; ?>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    function setCookie(name, value, days) {
                        let expires = '';
                        if (typeof days === 'number') {
                            const date = new Date();
                            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
                            expires = '; expires=' + date.toUTCString();
                        }
                        document.cookie = `${name}=${encodeURIComponent(value)}${expires}; path=/; SameSite=Lax`;
                    }

                    const categoryCards = document.querySelectorAll('.category-card');
                    const categoryInput = document.getElementById('categoryInput');
                    
                    function updateCategoryInput() {
                        const selected = [];
                        categoryCards.forEach(card => {
                            if (card.classList.contains('selected')) {
                                selected.push(card.dataset.category);
                            }
                        });
                        categoryInput.value = selected.join(',');
                    }
                    
                    categoryCards.forEach(card => {
                        card.addEventListener('click', () => {
                            card.classList.toggle('selected');
                            updateCategoryInput();
                        });
                    });
                    
                    document.getElementById('selectAllCats')?.addEventListener('click', () => {
                        categoryCards.forEach(card => card.classList.add('selected'));
                        updateCategoryInput();
                    });
                    
                    document.getElementById('deselectAllCats')?.addEventListener('click', () => {
                        categoryCards.forEach(card => card.classList.remove('selected'));
                        updateCategoryInput();
                    });

                    document.getElementById('saveDefaultCategoryBtn')?.addEventListener('click', () => {
                        const selected = [];
                        categoryCards.forEach(card => {
                            if (card.classList.contains('selected')) {
                                selected.push(card.dataset.category);
                            }
                        });
                        if (!selected.length) {
                            alert('Wybierz przynajmniej jedną kategorię, aby zapisać domyślną.');
                            return;
                        }
                        setCookie('default_test_categories', selected.join(','), 365);
                        const btn = document.getElementById('saveDefaultCategoryBtn');
                        if (btn) {
                            btn.textContent = 'Zapisano';
                            setTimeout(() => { btn.innerHTML = '<i class="bi bi-bookmark-star me-1"></i>Zapisz jako domyślną'; }, 1600);
                        }
                    });

                    document.getElementById('saveDefaultCountBtn')?.addEventListener('click', () => {
                        if (!countInput) return;
                        const countValue = Number(countInput.value || 0);
                        if (countValue < 1) {
                            alert('Wprowadź poprawną liczbę pytań, aby zapisać domyślną wartość.');
                            return;
                        }
                        setCookie('default_test_count', countValue, 365);
                        const btn = document.getElementById('saveDefaultCountBtn');
                        if (btn) {
                            btn.textContent = 'Zapisano';
                            setTimeout(() => { btn.innerHTML = '<i class="bi bi-bookmark-star me-1"></i>Domyślna liczba'; }, 1600);
                        }
                    });

                    // Difficulty Segmented Control
                    const difficultyBtns = document.querySelectorAll('.difficulty-btn');
                    const difficultyInput = document.getElementById('difficultyInput');
                    
                    difficultyBtns.forEach(btn => {
                        btn.addEventListener('click', () => {
                            difficultyBtns.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            difficultyInput.value = btn.dataset.value;
                        });
                    });

                    // Order Segmented Control
                    const orderBtns = document.querySelectorAll('.order-btn');
                    const orderInput = document.getElementById('orderInput');
                    
                    orderBtns.forEach(btn => {
                        btn.addEventListener('click', () => {
                            orderBtns.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            orderInput.value = btn.dataset.value;
                        });
                    });

                    // Scope Cards
                    const scopeCards = document.querySelectorAll('.scope-card');
                    const scopeInput = document.getElementById('scopeInput');
                    
                    scopeCards.forEach(card => {
                        card.addEventListener('click', () => {
                            scopeCards.forEach(c => c.classList.remove('selected'));
                            card.classList.add('selected');
                            scopeInput.value = card.dataset.value;
                        });
                    });

                    // Count Badges
                    const countBadges = document.querySelectorAll('.count-badge-btn');
                    const countInput = document.getElementById('questionCountInput');
                    
                    countBadges.forEach(badge => {
                        badge.addEventListener('click', () => {
                            countBadges.forEach(b => b.classList.remove('active'));
                            badge.classList.add('active');
                            if (countInput) {
                                countInput.value = badge.dataset.value;
                                // Dispatch an event on input to update checks
                                countInput.dispatchEvent(new Event('input'));
                            }
                        });
                    });
                    
                    countInput?.addEventListener('input', () => {
                        const val = Number(countInput.value || 0);
                        countBadges.forEach(b => {
                            if (Number(b.dataset.value) === val) {
                                b.classList.add('active');
                            } else {
                                b.classList.remove('active');
                            }
                        });
                    });
                    countInput?.dispatchEvent(new Event('input'));

                    const presetBtns = document.querySelectorAll('.preset-mode-btn');
                    const presetInput = document.getElementById('presetInput');
                    const timeLimitInput = document.getElementById('timeLimitInput');
                    const timeLimitValue = document.getElementById('timeLimitValue');

                    presetBtns.forEach(btn => {
                        btn.addEventListener('click', () => {
                            presetBtns.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            if (presetInput) presetInput.value = btn.dataset.preset || '';
                            if (countInput) {
                                countInput.value = btn.dataset.count || countInput.value;
                                countInput.dispatchEvent(new Event('input'));
                            }
                            if (timeLimitInput) {
                                timeLimitInput.value = btn.dataset.time || timeLimitInput.value;
                                timeLimitInput.dispatchEvent(new Event('input'));
                            }
                            const customBtn = document.querySelector('.time-option-btn[data-value="custom"]');
                            customBtn?.click();
                        });
                    });

                    // Time Options Segmented Control
                    const timeOptionBtns = document.querySelectorAll('.time-option-btn');
                    const timeOptionInput = document.getElementById('timeOptionInput');
                    const timeSliderPanel = document.querySelector('.time-slider-panel:not(#timePerQuestionPanel)');
                    const timePerQuestionPanel = document.getElementById('timePerQuestionPanel');
                    const timePerQuestionInput = document.getElementById('timePerQuestionInput');
                    const timePerQuestionValue = document.getElementById('timePerQuestionValue');
                    
                    timeOptionBtns.forEach(btn => {
                        btn.addEventListener('click', () => {
                            timeOptionBtns.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            if (timeOptionInput) timeOptionInput.value = btn.dataset.value;
                            
                            if (btn.dataset.value === 'custom') {
                                timeSliderPanel?.classList.add('open');
                                timePerQuestionPanel?.classList.remove('open');
                            } else if (btn.dataset.value === 'per_question_custom') {
                                timeSliderPanel?.classList.remove('open');
                                timePerQuestionPanel?.classList.add('open');
                            } else {
                                timeSliderPanel?.classList.remove('open');
                                timePerQuestionPanel?.classList.remove('open');
                            }
                        });
                    });
                    
                    timeLimitInput?.addEventListener('input', () => {
                        if (timeLimitValue) {
                            timeLimitValue.textContent = timeLimitInput.value;
                        }
                    });
                    timePerQuestionInput?.addEventListener('input', () => {
                        if (timePerQuestionValue) {
                            timePerQuestionValue.textContent = timePerQuestionInput.value;
                        }
                    });
                });
                </script>
            </form>
        </div>
    </div>
    <?php elseif ($currentQuestion): ?>

    <!-- ── Progress bar ───────────────────────────────────────────────────── -->
    <div class="dashboard-panel test-progress-panel mb-4">
        <div class="progress-top d-flex justify-content-between align-items-center mb-3">
            <div class="progress-heading">
                <span class="text-muted small d-block mb-1">Postęp testu</span>
                <strong class="h5 mb-0">Pytanie <?= $currentIdx + 1 ?> z <?= $totalQuestions ?></strong>
            </div>
            <div class="progress-actions test-progress-actions-modern d-flex align-items-center gap-2 flex-nowrap justify-content-end">
                <?php if (!empty($test['time_limit'])): ?>
                <div class="test-timer-modern" id="timer">
                    <?= formatTime(max(0, (isset($test['time_limit']) ? $test['time_limit'] : 3600) - (time() - $test['start_time']))) ?>
                </div>
                <?php endif; ?>
                <button type="button" class="test-end-modern-btn d-flex align-items-center justify-content-center" onclick="confirmEndTest()" title="Zakończ test">
                    <i class="bi bi-stop-circle"></i>
                </button>
            </div>
        </div>
        <div class="progress" style="height: 10px; border-radius: 5px;">
            <div class="progress-bar" id="progressBar"
                 style="width:<?= round(($answeredCount / $totalQuestions) * 100) ?>%"></div>
        </div>
    </div>

    <!-- ── Question card ──────────────────────────────────────────────────── -->
    <div class="dashboard-panel question-card">
        <div class="panel-header d-flex justify-content-between align-items-center mb-4 question-card-header">
            <div class="d-flex align-items-center gap-3">
                <h5 class="mb-0 fw-bold">Pytanie</h5>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2"><?= htmlspecialchars($currentQuestion['category'] ?? 'Ogólne') ?></span>
        </div>
        <div class="card-body p-0">

            <?php $questionImage = questionImageSrc($currentQuestion['image_url'] ?? ''); ?>
            <?php if ($questionImage): ?>
                <img src="<?= htmlspecialchars($questionImage) ?>"
                     alt="Ilustracja do pytania: <?= htmlspecialchars(mb_substr($currentQuestion['question_text'] ?? 'pytanie testowe', 0, 90)) ?>" class="img-fluid rounded mb-3 shadow-sm" loading="lazy" decoding="async" referrerpolicy="no-referrer">
            <?php endif; ?>

            <p class="h4 mb-4 fw-medium question-text-main" style="line-height: 1.5;"><?= nl2br(htmlspecialchars($currentQuestion['question_text'])) ?></p>

            <?php if ($phase === 'answering'): ?>
            <!-- ── PHASE 1: Answer form ──────────────────────────────────── -->
            <form method="POST" id="quizForm" onsubmit="return window.QuizEngine ? window.QuizEngine.handleFormSubmit(event) : true">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="question_id" value="<?= (int)$currentQuestion['id'] ?>">
                <input type="hidden" name="action"      value="submit_answer">
                <input type="hidden" name="answer"      id="selectedAnswer" value="<?= htmlspecialchars($savedAnswer) ?>">

                <div id="answersContainer" class="d-flex flex-column gap-2">
                    <?php 
                    $optionsFound = 0;
                    foreach (['A', 'B', 'C', 'D'] as $opt):
                        // Check multiple potential keys for robustness
                        $text = $currentQuestion['option_' . strtolower($opt)] 
                             ?? $currentQuestion['option_' . strtoupper($opt)] 
                             ?? $currentQuestion[strtolower($opt)] 
                             ?? '';
                             
                        if (trim($text) === '') continue;
                        $optionsFound++;
                    ?>
                    <div class="answer-option quiz-option <?= $savedAnswer === $opt ? 'selected' : '' ?>" data-answer="<?= $opt ?>" onclick="QuizEngine.selectOption(this)">
                        <div class="answer-letter"><?= $opt ?></div>
                        <div class="answer-text"><?= htmlspecialchars($text) ?></div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($optionsFound === 0): ?>
                        <div class="alert alert-warning">Błąd: Nie znaleziono opcji odpowiedzi dla tego pytania.</div>
                    <?php endif; ?>
                </div>

                <div class="quiz-action-bar d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4">
                    <div class="quiz-primary-actions d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn" <?= $savedAnswer === '' ? 'disabled' : '' ?>>
                            <i class="bi bi-check2-circle me-2"></i>Zatwierdź odpowiedź
                        </button>
                        <button type="submit" name="action" value="previous_question" class="btn btn-outline-secondary btn-lg px-4" data-question-nav="previous" formnovalidate <?= $currentIdx <= 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-left me-2"></i>Poprzednie pytanie
                        </button>
                    </div>
                    <a href="test.php?setup=1" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i>Zmień ustawienia
                    </a>
                </div>
            </form>

            <?php else: ?>
            <!-- ── PHASE 2: Review – show result, then go next ───────────── -->
            <?php
                $lr      = $lastResult;
                $correct = $currentQuestion['correct_answer'];
            ?>
                <div id="answersContainer" class="d-flex flex-column gap-2">
                    <?php foreach (['A', 'B', 'C', 'D'] as $opt):
                        $text = $currentQuestion['option_' . strtolower($opt)] 
                             ?? $currentQuestion['option_' . strtoupper($opt)] 
                             ?? $currentQuestion[strtolower($opt)] 
                             ?? '';
                        if (trim($text) === '') continue;
                        
                        $cls = '';
                        if ($opt === $correct && $opt === $lr['user_answer']) {
                            $cls = 'correct';
                        } elseif ($opt === $lr['user_answer']) {
                            $cls = 'incorrect';
                        } elseif ($opt === $correct) {
                            $cls = 'correct opacity-75';
                        } else {
                            $cls = 'disabled';
                        }
                    ?>
                    <div class="answer-option quiz-option <?= $cls ?>">
                        <div class="answer-letter"><?= $opt ?></div>
                        <div class="answer-text"><?= htmlspecialchars($text) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <!-- Result feedback -->
            <div class="review-box mt-3">
                <?php if ($lr['is_correct']): ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        <strong class="text-success fs-5">Poprawna odpowiedź!</strong>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                        <strong class="text-danger fs-5">Błędna odpowiedź.</strong>
                    </div>
                    <p class="mb-0 text-muted">
                        Poprawna odpowiedź: <strong class="text-success"><?= htmlspecialchars($lr['correct_answer']) ?></strong>
                        – <?= htmlspecialchars($currentQuestion['option_' . strtolower($lr['correct_answer'])] ?? '') ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Next / Finish buttons -->
            <div class="d-flex gap-2 mt-4 flex-wrap">
                <?php if ($lr['is_last']): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action"     value="next_question">
                    <button type="submit" class="btn btn-success btn-lg">
                        Następne pytanie <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action"     value="next_question">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Następne pytanie <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($mode === 'exam' && $answeredCount > 0): ?>
                <form method="POST" onsubmit="return confirmFinish(this)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action"     value="finish_early">
                    <button type="submit" class="btn btn-outline-warning btn-lg">
                        <i class="bi bi-flag-fill me-2"></i>Zakończ i zobacz wyniki
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; // phase ?>

        </div>
    </div>

    <!-- Keyboard hints (only in answering phase) -->
    <?php if ($phase === 'answering'): ?>
    <div class="keyboard-hints">
        <span class="key-hint"><kbd>1</kbd>–<kbd>4</kbd> Wybierz odpowiedź</span>
        <span class="key-hint"><kbd>Enter</kbd> Zatwierdź</span>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Brak pytań do wyświetlenia. Sprawdź czy baza danych zawiera pytania.
    </div>
    <?php endif; ?>
                </div><!-- /container -->
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

<div class="modal fade test-confirm-modal" id="testConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title test-confirm-title fw-bold mb-1"><i class="bi bi-flag-fill text-warning me-2"></i>Zakończyć test?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body pt-0 px-4 pb-4">
                <p class="test-confirm-desc mb-3">Wynik zostanie zapisany w obecnym stanie.</p>
                <div class="test-confirm-counter alert alert-warning mb-0 rounded-3 border-0">
                    <span class="test-confirm-answers"><strong><?= $answeredCount ?> / <?= $totalQuestions ?></strong></span>
                    <span class="test-confirm-label ms-2">Nieudzielone pytania będą liczone jako błędne.</span>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-3 justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Wróć</button>
                <button type="button" class="btn btn-danger test-confirm-btn rounded-pill px-4 shadow-sm" id="testConfirmSubmit">
                    <i class="bi bi-check2-circle me-2"></i>Zakończ i zapisz
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="testTimeExpiredModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-4">
                <div class="display-6 text-danger mb-2"><i class="bi bi-hourglass-bottom"></i></div>
                <h5 class="fw-bold mb-2">Czas upłynął</h5>
                <p class="text-muted mb-0">Test zostanie zapisany w obecnym stanie.</p>
            </div>
        </div>
    </div>
</div>

<!-- Test modals and timer -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
let shouldConfirmNavigation = false;
let pendingFinishForm = null;
let confirmModal = null;
let timeExpiredModal = null;

window.allowQuizNavigation = function () {
    shouldConfirmNavigation = false;
};

function modalInstance(id) {
    const el = document.getElementById(id);
    return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
}

function submitFinishEarlyForm(form) {
    shouldConfirmNavigation = false;
    if (form) {
        form.submit();
        return;
    }
    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">' +
                  '<input type="hidden" name="action" value="finish_early">';
    document.body.appendChild(f);
    f.submit();
}

function confirmFinish(form) {
    pendingFinishForm = form || null;
    confirmModal = confirmModal || modalInstance('testConfirmModal');
    if (confirmModal) {
        confirmModal.show();
        return false;
    }
    submitFinishEarlyForm(pendingFinishForm);
    return false;
}

document.getElementById('testConfirmSubmit')?.addEventListener('click', function () {
    if (confirmModal) confirmModal.hide();
    submitFinishEarlyForm(pendingFinishForm);
});

window.addEventListener('beforeunload', function (e) {
    if (shouldConfirmNavigation) {
        e.preventDefault();
        e.returnValue = ''; 
    }
});

<?php if (!empty($test['time_limit']) && $phase === 'answering'): ?>
// Exam countdown timer
let timeLeft = <?= max(0, (isset($test['time_limit']) ? $test['time_limit'] : 3600) - (time() - $test['start_time'])) ?>;
const timerEl = document.getElementById('timer');
let timerExpired = false;
function updateTimer() {
    if (timerExpired) return;
    const m = String(Math.floor(timeLeft / 60)).padStart(2,'0');
    const s = String(timeLeft % 60).padStart(2,'0');
    timerEl.textContent = `${m}:${s}`;
    if (timeLeft <= 300) timerEl.classList.add('timer-warning');
    if (timeLeft <= 0) {
        timerExpired = true;
        clearInterval(t);
        shouldConfirmNavigation = false;
        timeExpiredModal = timeExpiredModal || modalInstance('testTimeExpiredModal');
        if (timeExpiredModal) timeExpiredModal.show();
        setTimeout(() => submitFinishEarlyForm(null), 900);
        return;
    }
    timeLeft--;
}
updateTimer();
const t = setInterval(updateTimer, 1000);
<?php endif; ?>

function confirmEndTest() {
    pendingFinishForm = null;
    confirmFinish(null);
}
</script>
</body>
</html>
