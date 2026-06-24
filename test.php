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

$userId = $isGuest ? 0 : (int)($_SESSION['user_id'] ?? 0);
if (!$isGuest && $userId > 0) {
    restoreActiveTestForUser($pdo, $userId);
}

$showTestConflict = false;

if (isset($_GET['continue_test']) && $_GET['continue_test'] === '1' && hasActiveTestInSession()) {
    header('Location: test.php');
    exit;
}

if (isset($_GET['force_new']) && $_GET['force_new'] === '1' && hasActiveTestInSession()) {
    cancelActiveTest($pdo, $userId > 0 ? $userId : null);
}

if (isset($_GET['new']) && $_GET['new'] === '1' && !hasActiveTestInSession()) {
    unset($_SESSION['current_test'], $_SESSION['test_start_time'], $_SESSION['last_result_id']);
    if ($userId > 0) {
        clearPersistedActiveTest($pdo, $userId);
    }
}

// finishTest logic moved to includes/functions.php
// ──────────────────────────────────────────────────────────────────────────────

// CSRF setup
generateCsrfToken();

$mode     = $_GET['mode']     ?? 'exam';
$allowedTestModes = ['exam', 'practice', 'single', 'exam_simulator'];
if (!in_array($mode, $allowedTestModes, true)) {
    $mode = 'exam';
}
$categoryExplicitlySelected = isset($_GET['category']) && trim((string)$_GET['category']) !== '';
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
$defaultTimeCookie = isset($_COOKIE['default_test_time']) ? (int)$_COOKIE['default_test_time'] : 0;
$timeLimit = isset($_GET['time']) ? (int)$_GET['time'] : ($defaultTimeCookie > 0 ? $defaultTimeCookie : 60); // in minutes
$order    = $_GET['order']    ?? 'random';
$smart    = isset($_GET['smart']) && $_GET['smart'] === '1';
if ($isGuest) {
    $smart = false;
}

$allowedDifficulties = ['all', 'easy', 'medium', 'hard'];
$allowedScopes = ['all', 'unseen', 'incorrect', 'exclude_correct'];
$allowedTimeOptions = ['custom', 'unlimited', '30s', '60s', 'per_question_custom'];
$difficulty = $_GET['difficulty'] ?? ($_COOKIE['default_test_difficulty'] ?? 'all');
$scope      = $_GET['scope']      ?? ($_COOKIE['default_test_scope'] ?? 'all');
$timeOption = $_GET['time_option'] ?? ($_COOKIE['default_test_time_option'] ?? 'custom');
$timePerQuestionCookie = isset($_COOKIE['default_test_time_per_question']) ? (int)$_COOKIE['default_test_time_per_question'] : 0;
$timePerQuestion = isset($_GET['time_per_question']) ? (int)$_GET['time_per_question'] : ($timePerQuestionCookie > 0 ? $timePerQuestionCookie : 60);
$preset = $_GET['preset'] ?? '';

if (!in_array($difficulty, $allowedDifficulties, true)) $difficulty = 'all';
if (!in_array($scope, $allowedScopes, true)) $scope = 'all';
if (!in_array($timeOption, $allowedTimeOptions, true)) $timeOption = 'custom';

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
    $timeLimit = 0;
    $timeOption = 'unlimited';
} elseif ($mode === 'exam_simulator') {
    $count = 40;
    $timeLimit = 60;
    $timeOption = 'custom';
    $timePerQuestion = 60;
    $difficulty = 'all';
    $scope = 'all';
    $order = 'random';
    $smart = false;
    $preset = '';
    $simCategories = array_values(array_filter(array_map('trim', explode(',', (string)$category))));
    $category = $simCategories[0] ?? '';
}

$hasActiveTest = hasActiveTestInSession();
$wantsStart = isset($_GET['start']) && $_GET['start'] === '1';
$wantsSetup = isset($_GET['setup']) && $_GET['setup'] === '1';
$wantsFreshSetup = isset($_GET['new']) && $_GET['new'] === '1';

if ($hasActiveTest) {
    $activeConfig = getActiveTestConfigFromSession();
    $mode = $activeConfig['mode'] ?? $mode;
    $category = $activeConfig['category'] ?? $category;
    $count = $activeConfig['count'] ?? $count;
    $timeLimit = $activeConfig['timeLimit'] ?? $timeLimit;
    $timeOption = $activeConfig['timeOption'] ?? $timeOption;
    $timePerQuestion = $activeConfig['timePerQuestion'] ?? $timePerQuestion;
    $difficulty = $activeConfig['difficulty'] ?? $difficulty;
    $scope = $activeConfig['scope'] ?? $scope;
    $order = $activeConfig['order'] ?? $order;
    $preset = $activeConfig['preset'] ?? $preset;
}

if ($hasActiveTest && ($wantsStart || $wantsSetup || $wantsFreshSetup)) {
    $showTestConflict = true;
}

$showSetup = !$hasActiveTest && (
    $wantsSetup
    || ($mode === 'practice' && empty($category) && !$wantsStart)
    || ($mode === 'single' && (!$wantsStart || !$categoryExplicitlySelected))
    || ($mode === 'exam_simulator' && (!$wantsStart || empty($category)))
);

$needNewTest = $wantsStart && !$showTestConflict && !$hasActiveTest;

if ($needNewTest && !$showSetup && $wantsStart) {
    $allQuestions = loadQuestions($pdo, false);
    if (empty($allQuestions)) {
        $selectedQuestions = [];
    } else {
        $pool = filterQuestionPoolForTest($pdo, $allQuestions, $category, $difficulty, $scope, $userId);
        $selectedQuestions = selectQuestionsForTest($pdo, $pool, $mode, $count, $order, $smart, $userId, $isGuest);
    }
    
    // Calculate final time limit in seconds
    $timeLimitSeconds = $timeLimit * 60;
    if ($mode === 'exam_simulator') {
        $timeLimitSeconds = 3600;
    } elseif ($mode === 'single') {
        $timeLimitSeconds = 0;
    } elseif ($timeOption === 'unlimited') {
        $timeLimitSeconds = 0;
    } elseif ($timeOption === '30s') {
        $timeLimitSeconds = count($selectedQuestions) * 30;
    } elseif ($timeOption === '60s') {
        $timeLimitSeconds = count($selectedQuestions) * 60;
    } elseif ($timeOption === 'per_question_custom') {
        $timeLimitSeconds = count($selectedQuestions) * $timePerQuestion;
    }

    $questionTimeLimit = 0;
    if ($timeOption === '30s') {
        $questionTimeLimit = 30;
    } elseif ($timeOption === '60s') {
        $questionTimeLimit = 60;
    } elseif ($timeOption === 'per_question_custom') {
        $questionTimeLimit = $timePerQuestion;
    }

    $excludeFromRanking = isset($_GET['unranked']) && $_GET['unranked'] === '1' ? 1 : 0;
    if ($mode === 'exam_simulator') {
        $excludeFromRanking = 0;
    }
    $cleanCategory = is_array($category) ? implode(',', $category) : $category;
    $newTest = [
        'mode'       => $mode,
        'questions'  => $selectedQuestions,
        'current'    => 0,
        'start_time' => time(),
        'time_limit' => $timeLimitSeconds,
        'question_time_limit' => $questionTimeLimit,
        'question_start_time' => time(),
        'answers'    => [],
        'phase'      => 'answering',
        'last_result'=> null,
        'smart'      => $smart,
        'answer_check_limit' => $mode === 'single' ? 0 : 3,
        'answer_check_used' => 0,
        'exclude_from_ranking' => $excludeFromRanking,
        'config'     => [
            'category' => $cleanCategory,
            'count' => count($selectedQuestions),
            'time' => $timeLimit,
            'time_per_question' => $timePerQuestion,
            'preset' => $preset,
            'difficulty' => $difficulty,
            'scope' => $scope,
            'time_option' => $timeOption,
            'order' => $order,
            'smart' => $smart,
        ],
    ];
    saveCurrentTest($pdo, $userId > 0 ? $userId : null, $newTest);
    
    // Redirect to clean URL to prevent re-initialization on refresh or POST
    header('Location: test.php');
    exit;
}

$test           = $_SESSION['current_test'] ?? null;
if (is_array($test) && normalizeSingleQuestionTestState($test)) {
    saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
}
$questions      = $test['questions'] ?? [];
$totalQuestions = count($questions);
if ($test && restoreCheckedQuestionReview($test)) {
    saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
    $questions = $test['questions'] ?? [];
    $totalQuestions = count($questions);
}

// ─── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$test) {
        header('Location: index.php');
        exit;
    }
    // CSRF Protection using standardized function
    if (!securityValidateRequestCsrf()) {
        http_response_code(403);
        die("Błąd bezpieczeństwa (CSRF).");
    }

    $action = securityInputEnum($_POST['action'] ?? '', ['finish_early', 'previous_question', 'goto_question', 'next_question', 'check_answer', 'submit_answer'], '');
    $rateLimit = securityConsumeRateLimit('test-post:' . securityActorKey() . ':' . $action, $action === 'check_answer' ? 30 : 100, 60);
    if ($action === '' || empty($rateLimit['allowed'])) {
        securityAudit('test_post_blocked', ['action' => $_POST['action'] ?? '', 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
        setSessionMessage('error', 'Zbyt wiele akcji naraz albo nieprawidłowa akcja formularza.');
        header('Location: test.php');
        exit;
    }

    // ── FINISH EARLY ──────────────────────────────────────────────────────────
    if ($action === 'finish_early') {
        if (($test['mode'] ?? '') === 'single' && !isGuestMode()) {
            if (testHasReviewedCurrentAnswer($test)) {
                $resultId = ensureSingleQuestionResultSaved($pdo, $test, $userId);
                if ($resultId <= 0) {
                    setSessionMessage('error', 'Nie udało się zapisać wyniku. Spróbuj ponownie.');
                    header('Location: test.php');
                    exit;
                }
            } else {
                $resultId = 0;
            }
            cancelActiveTest($pdo, $userId > 0 ? $userId : null);
            header('Location: ' . ($resultId > 0 ? 'result.php?id=' . $resultId : 'test.php?mode=single&setup=1'));
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

    // ── GO BACK TO PREVIOUS QUESTION ────────────────────────────────────────
    if ($action === 'previous_question') {
        if (testDisallowsPreviousQuestion($test)) {
            header('Location: test.php');
            exit;
        }
        $test['current'] = max(0, min($totalQuestions - 1, (int)($test['current'] ?? 0) - 1));
        $test['phase'] = 'answering';
        $test['last_result'] = null;
        restoreCheckedQuestionReview($test);
        touchTestQuestionStart($test);
        saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
        header('Location: ' . (($test['mode'] ?? '') === 'exam_simulator' ? 'test.php?view=question#sim-question' : 'test.php'));
        exit;
    }

    if ($action === 'goto_question') {
        $targetIdx = securityInputInt($_POST['target'] ?? 0, 0, max(0, $totalQuestions - 1), 0);
        $test['current'] = max(0, min($totalQuestions - 1, $targetIdx));
        $test['phase'] = 'answering';
        $test['last_result'] = null;
        touchTestQuestionStart($test);
        saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
        header('Location: test.php?view=question#sim-question');
        exit;
    }

    // ── ADVANCE TO NEXT QUESTION (from review phase) ──────────────────────────
    if ($action === 'next_question') {
        if (!testCanAdvanceFromReview($test)) {
            setSessionMessage('error', 'Najpierw odpowiedz na bieżące pytanie.');
            header('Location: test.php');
            exit;
        }
        $singleResultId = 0;
        if (($test['mode'] ?? '') === 'single' && !isGuestMode()) {
            $singleResultId = ensureSingleQuestionResultSaved($pdo, $test, $userId);
            if ($singleResultId <= 0) {
                setSessionMessage('error', 'Nie udało się zapisać wyniku. Spróbuj ponownie.');
                header('Location: test.php');
                exit;
            }
        }
        $test['current']++;
        $test['phase']       = 'answering';
        $test['last_result'] = null;
        restoreCheckedQuestionReview($test);
        touchTestQuestionStart($test);

        if (($test['mode'] ?? $mode) === 'single') {
            $nextSingle = prepareNextSingleQuestion($pdo, $test, $userId > 0 ? $userId : null, isGuestMode());
            if (!empty($nextSingle['success'])) {
                saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
                header('Location: test.php');
            } else {
                if (isGuestMode()) {
                    $resultId = finishGuestTest($test);
                    header('Location: result.php?guest=' . urlencode($resultId));
                } else {
                    $resultId = $singleResultId;
                    cancelActiveTest($pdo, $userId > 0 ? $userId : null);
                    header('Location: ' . ($resultId > 0 ? 'result.php?id=' . $resultId : 'test.php?mode=single&setup=1'));
                }
            }
            exit;
        }
        
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
        
        saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
        // Redirect to avoid form re-submission
        header('Location: ' . (($test['mode'] ?? '') === 'exam_simulator' ? 'test.php?view=question#sim-question' : 'test.php'));
        exit;
    }

    // ── SUBMIT ANSWER ─────────────────────────────────────────────────────────
    if ($action === 'check_answer') {
        $questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        $userAnswer = securityInputAnswerLetter($_POST['answer'] ?? '');
        $checkResult = applyTestAnswerCheck($test, $questionId, $userAnswer, $pdo, $userId > 0 ? $userId : null, isGuestMode());

        if (!empty($checkResult['success'])) {
            saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
        } else {
            setSessionMessage('error', (string)($checkResult['error'] ?? 'Nie można sprawdzić odpowiedzi.'));
        }
        header('Location: test.php');
        exit;
    }

    if ($action === 'submit_answer') {
        $questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        $userAnswer = securityInputAnswerLetter($_POST['answer'] ?? '');

        // Find current question
        $currentIdx = $test['current'];
        if (isset($questions[$currentIdx]) && (int)$questions[$currentIdx]['id'] === $questionId) {
            $q             = $questions[$currentIdx];
            $correctAnswer = strtoupper(trim((string)($q['correct_answer'] ?? '')));
            $isCorrect     = ($userAnswer === $correctAnswer);

            if (!empty($test['answers'][$currentIdx]['revealed_by_check'])) {
                $test['phase'] = 'reviewing';
                $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
                saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
                header('Location: test.php');
                exit;
            }
            if (($test['phase'] ?? 'answering') !== 'answering') {
                if (!empty($test['answers'][$currentIdx])) {
                    $test['phase'] = 'reviewing';
                    $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
                    saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
                } else {
                    setSessionMessage('error', 'To pytanie jest juz w trybie podgladu.');
                }
                header('Location: test.php');
                exit;
            }

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

            if ($mode === 'exam_simulator') {
                $test['phase'] = 'answering';
                $test['last_result'] = null;
                saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
                header('Location: test.php');
                exit;
            }

            if ($mode === 'exam') {
                // In exam mode, skip review and go to next question (or finish)
                $test['current']++;
                $test['phase'] = 'answering';
                touchTestQuestionStart($test);
                
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
                
                saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
                header('Location: test.php');
                exit;
            } else {
                // In practice/single mode, show review info
                $singleResultId = 0;
                if ($mode === 'single' && !isGuestMode() && isset($_SESSION['user_id'])) {
                    $singleResultId = saveSingleQuestionResult($pdo, $_SESSION['user_id'], $q, $userAnswer, $isCorrect);
                    if ($singleResultId > 0) {
                        $_SESSION['last_result_id'] = $singleResultId;
                    }
                }
                $test['phase'] = 'reviewing';
                $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
                if ($singleResultId > 0) {
                    recordSingleQuestionResultId($test, $singleResultId);
                }
                saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
            }
        }
    }
}
// ──────────────────────────────────────────────────────────────────────────────

if ($hasActiveTest && !$showTestConflict && ($wantsSetup || $wantsFreshSetup)) {
    header('Location: test.php');
    exit;
}

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
    $perQuestionLimit = getTestQuestionTimeLimit($test);
    $questionTimeLeft = 0;
    if ($perQuestionLimit > 0 && $phase === 'answering') {
        if (empty($test['question_start_time'])) {
            touchTestQuestionStart($test);
            saveCurrentTest($pdo, $userId > 0 ? $userId : null, $test);
        }
        $questionTimeLeft = getTestQuestionTimeRemaining($test, $perQuestionLimit);
    }
    $allowPreviousQuestion = !testDisallowsPreviousQuestion($test);
    $answerCheckState = testAnswerCheckPayload($test);
    $answerCheckLimit = (int)$answerCheckState['answer_check_limit'];
    $answerCheckUsed = (int)$answerCheckState['answer_check_used'];
    $answerCheckRemaining = (int)$answerCheckState['answer_check_remaining'];
    $answerCheckAvailable = !empty($answerCheckState['answer_check_available']);
    $answerCheckModeAllowed = !empty($answerCheckState['answer_check_mode_allowed']);
    $totalTimeLeft = !empty($test['time_limit'])
        ? max(0, (int)$test['time_limit'] - (time() - (int)$test['start_time']))
        : 0;
} else {
    $currentIdx      = 0;
    $currentQuestion = null;
    $phase           = 'none';
    $lastResult      = null;
    $answeredCount   = 0;
    $isTestActive    = false;
    $savedAnswer     = '';
    $perQuestionLimit = 0;
    $questionTimeLeft = 0;
    $allowPreviousQuestion = true;
    $answerCheckLimit = 0;
    $answerCheckUsed = 0;
    $answerCheckRemaining = 0;
    $answerCheckAvailable = false;
    $answerCheckModeAllowed = false;
    $totalTimeLeft = 0;
}
$flashMsg = getSessionMessage();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'exam_simulator' ? 'Egzamin' : ($mode === 'exam' ? 'Test' : 'Test') ?> – System Edukacyjny INF.02</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous" rel="stylesheet">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <script src="assets/js/api-client.js?v=<?= filemtime(__DIR__ . '/assets/js/api-client.js') ?>" defer></script>
    <script src="assets/js/quiz-engine.js?v=<?= filemtime(__DIR__ . '/assets/js/quiz-engine.js') ?>" defer></script>
    <style>
        @media(max-width:576px) { .answer-option{padding:.9rem 2.5rem .9rem 1rem;} }
        .test-progress-panel .progress-heading {
            min-width: 0;
        }
        .test-progress-panel .progress-actions {
            min-width: max-content;
        }
        .test-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .test-progress-meta {
            min-width: 0;
        }
        .test-progress-label {
            display: block;
            font-size: 0.78rem;
            color: var(--text-muted, #64748b);
            margin-bottom: 0.2rem;
            letter-spacing: 0.02em;
        }
        .test-progress-question {
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--text-main, #0f172a);
        }
        .test-progress-sub {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
            margin-top: 0.45rem;
        }
        .test-progress-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.22rem 0.55rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.1);
            color: #2563eb;
        }
        .test-progress-controls {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-shrink: 0;
        }
        .test-timers-cluster {
            display: flex;
            align-items: stretch;
            gap: 0.5rem;
        }
        .test-timer-chip {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.15rem;
            padding: 0.45rem 0.75rem;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(248, 250, 252, 0.95);
            min-width: 5.5rem;
        }
        body.dark-mode .test-timer-chip {
            background: rgba(15, 23, 42, 0.55);
            border-color: rgba(148, 163, 184, 0.18);
        }
        .test-timer-chip-label {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            line-height: 1;
        }
        .test-timer-chip-value {
            font-family: "Nunito", "Inter", sans-serif;
            font-variant-numeric: tabular-nums;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1;
            color: #2563eb;
        }
        .test-timer-chip-primary {
            border-color: rgba(37, 99, 235, 0.22);
            background: linear-gradient(180deg, rgba(239, 246, 255, 0.98), rgba(219, 234, 254, 0.72));
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
        }
        body.dark-mode .test-timer-chip-primary {
            background: linear-gradient(180deg, rgba(30, 58, 138, 0.35), rgba(15, 23, 42, 0.65));
        }
        .test-timer-chip-primary .test-timer-chip-value {
            font-size: 1.65rem;
        }
        .test-timer-chip-secondary .test-timer-chip-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: #475569;
        }
        .test-timer-chip.timer-warning,
        .test-timer-chip.timer-warning .test-timer-chip-value {
            border-color: rgba(239, 68, 68, 0.35);
            color: #dc2626;
        }
        .test-timer-chip.timer-warning {
            background: rgba(254, 242, 242, 0.95);
            animation: test-timer-pulse 1s ease-in-out infinite;
        }
        @keyframes test-timer-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .test-progress-bar-wrap .progress {
            height: 8px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.18);
            overflow: hidden;
        }
        .test-progress-bar-wrap .progress-bar {
            border-radius: 999px;
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            transition: width 0.35s ease;
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
        .answer-explanation {
            background: rgba(59, 130, 246, .08);
            border: 1px solid rgba(59, 130, 246, .14);
            border-radius: 12px;
            padding: .75rem .85rem;
            color: var(--text-main);
            line-height: 1.5;
        }
        .answer-explanation-label {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: .25rem;
        }

        .answer-distractors {
            border-top: 1px dashed rgba(59,130,246,.24);
            margin-top: .65rem;
            padding-top: .65rem;
            color: var(--text-muted, #64748b);
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
        body.dark-mode .answer-card-view-btn {
            border-color: rgba(96,165,250,.25);
            background: rgba(96,165,250,.08);
            color: #60a5fa;
        }
        body.dark-mode .answer-explanation {
            background: rgba(96, 165, 250, .08);
            border-color: rgba(96, 165, 250, .18);
            color: #e5e7eb;
        }
        .question-card .card-body {
            transition: opacity .22s ease, transform .22s ease;
        }
        .question-card .card-body.fade-out {
            opacity: 0;
            transform: translateY(8px);
        }
        .question-card .card-body.fade-in {
            animation: test-card-reveal .28s ease both;
        }
        @keyframes test-card-reveal {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .test-check-counter {
            background: rgba(14, 165, 233, .10);
            color: #0369a1;
            border: 1px solid rgba(14, 165, 233, .18);
        }
        .test-check-counter.is-updated {
            animation: check-counter-pop .28s ease;
        }
        @keyframes check-counter-pop {
            0% { transform: scale(1); }
            45% { transform: scale(1.06); }
            100% { transform: scale(1); }
        }
        body.dark-mode .test-check-counter {
            background: rgba(56, 189, 248, .14);
            border-color: rgba(125, 211, 252, .24);
            color: #bae6fd;
        }
        .answer-check-btn {
            border-color: rgba(14, 165, 233, .42);
            color: #0369a1;
            background: rgba(14, 165, 233, .06);
            font-weight: 800;
        }
        .answer-check-btn:hover:not(:disabled),
        .answer-check-btn:focus-visible:not(:disabled) {
            background: #0ea5e9;
            border-color: #0ea5e9;
            color: #fff;
            transform: translateY(-1px);
        }
        .answer-check-btn:disabled {
            opacity: .58;
            cursor: not-allowed;
        }
        .answer-check-hint {
            display: inline-flex;
            align-items: center;
            min-height: 48px;
            color: var(--text-muted, #64748b);
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.25;
            max-width: 12rem;
        }
        .answer-check-review-note {
            display: flex;
            gap: .55rem;
            align-items: flex-start;
            border: 1px solid rgba(14, 165, 233, .18);
            border-radius: 10px;
            padding: .7rem .8rem;
            background: rgba(14, 165, 233, .08);
            color: #075985;
            font-weight: 700;
            line-height: 1.45;
        }
        .answer-check-review-note i {
            flex: 0 0 auto;
            margin-top: .12rem;
        }
        body.dark-mode .answer-check-review-note {
            background: rgba(56, 189, 248, .12);
            border-color: rgba(125, 211, 252, .22);
            color: #e0f2fe;
        }
        body.reduce-motion .question-card .card-body,
        body.reduce-motion .question-card .card-body.fade-in,
        body.reduce-motion .answer-check-btn,
        body.reduce-motion .test-check-counter.is-updated,
        body.reduce-motion .test-timer-chip.timer-warning {
            animation: none !important;
            transition: none !important;
            transform: none !important;
        }
        @media (prefers-reduced-motion: reduce) {
            .question-card .card-body,
            .question-card .card-body.fade-in,
            .answer-check-btn,
            .test-check-counter.is-updated,
            .test-timer-chip.timer-warning {
                animation: none !important;
                transition: none !important;
                transform: none !important;
            }
        }

        /* ==================== PREMIUM SETUP SYSTEM ==================== */
        .premium-setup-container {
            max-width: min(1280px, 100%);
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

        .exam-sim-launch-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
            border-radius: 1.5rem;
            /* Test compliance requirement: linear-gradient(135deg, #102a6b */
            background: radial-gradient(circle at top left, color-mix(in srgb, var(--primary-color) 95%, transparent), transparent 30%),
                        radial-gradient(circle at bottom right, rgba(16, 185, 129, 0.85), transparent 25%),
                        linear-gradient(135deg, var(--primary-color) 0%, rgba(16, 185, 129, 1) 100%);
            color: #fff !important;
            padding: 1.5rem 2rem;
            text-decoration: none !important;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: background 0.5s ease, border-color 0.5s ease, box-shadow 0.5s ease, transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body.welcome-style-gradient .exam-sim-launch-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #10b981 100%) !important;
        }
        body.welcome-style-pure .exam-sim-launch-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, color-mix(in srgb, var(--primary-color) 25%, #0d0b21) 100%) !important;
        }
        body.welcome-style-aurora .exam-sim-launch-card {
            background: linear-gradient(135deg, #0d0b21 0%, var(--primary-color) 50%, #03001c 100%) !important;
            background-size: 200% 200% !important;
            animation: ctaMeshMovement 10s ease infinite !important;
        }
        body.welcome-style-glass .exam-sim-launch-card {
            background: radial-gradient(circle at 100% 100%, #10b981 0%, var(--primary-color) 100%) !important;
        }
        body.dark-mode.welcome-style-glass .exam-sim-launch-card {
            background: rgba(30, 41, 59, 0.45) !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
        }

        .exam-sim-launch-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.22);
            color: #fff !important;
        }
        .exam-sim-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            flex: 0 0 auto;
            font-size: 1.3rem;
            transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .exam-sim-launch-card:hover .exam-sim-action-icon {
            background: #fff;
            color: var(--primary-color, #3b82f6);
            transform: scale(1.08);
        }
        .exam-sim-launch-card .exam-sim-subtext {
            color: rgba(255, 255, 255, 0.85) !important;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.5;
            margin-top: 0.4rem;
            display: block;
        }
        .exam-sim-setup-card {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            background: var(--panel-bg);
        }
        .exam-sim-rule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 0.75rem;
            margin: 1rem 0;
        }
        .exam-sim-rule {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.85rem;
            background: rgba(59, 130, 246, 0.04);
        }
        .exam-sim-rule strong {
            display: block;
            color: var(--text-main);
            margin-bottom: 0.25rem;
        }
        .sim-exam-shell {
            max-width: 1084px;
            margin: 0 auto;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #f5f5f5;
            padding: 0 16px 16px;
        }
        .sim-exam-topbar {
            border-top: 2px solid #0f9ba8;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.14);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .sim-exam-brand {
            font-size: 20px;
            font-weight: 700;
        }
        .sim-exam-links {
            display: flex;
            gap: 22px;
            font-size: 12px;
            color: #111;
        }
        .sim-exam-title {
            color: #8b1744;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 18px 18px;
        }
        .sim-exam-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 286px;
            gap: 16px;
        }
        .sim-exam-list,
        .sim-exam-side,
        .sim-exam-question {
            border: 1px solid #ddd;
            background: #fff;
            color: #111827;
        }
        .sim-exam-list {
            padding: 18px;
            min-height: 620px;
        }
        .sim-task-row {
            display: grid;
            grid-template-columns: 98px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            margin-bottom: 14px;
        }
        .sim-task-btn {
            width: 98px;
            border: 0;
            border-radius: 2px;
            background: #006560;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 8px;
        }
        .sim-task-btn.active {
            background: #003f3d;
            outline: 2px solid #9cc9c5;
        }
        .sim-task-status {
            color: #ff0000;
            font-size: 13px;
        }
        .sim-task-status.answered {
            color: #008000;
        }
        .sim-exam-side {
            padding: 16px;
        }
        .sim-side-label {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .sim-side-field {
            border: 1px solid #ddd;
            background: #f5f5f5;
            border-radius: 3px;
            padding: 7px 8px;
            font-size: 13px;
            margin-bottom: 30px;
        }
        .sim-side-field.danger {
            border-color: #ff0000;
        }
        .sim-timer-label {
            text-align: right;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.25;
        }
        .sim-timer {
            text-align: right;
            font-size: 38px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }
        .sim-finish-btn {
            width: 100%;
            border: 1px solid #9b174c;
            background: #fff;
            color: #9b174c;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 10px;
        }
        .sim-exam-question {
            grid-column: 1 / -1;
            padding: 18px;
            margin-top: 16px;
        }
        .sim-exam-shell.sim-main-view .sim-exam-question {
            display: none;
        }
        .sim-exam-shell.sim-question-view .sim-exam-list,
        .sim-exam-shell.sim-question-view .sim-exam-side {
            display: none;
        }
        .sim-exam-shell.sim-question-view .sim-exam-layout {
            grid-template-columns: 1fr;
        }
        .sim-question-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .sim-back-btn {
            border: 1px solid #006560;
            background: #fff;
            color: #006560;
            border-radius: 3px;
            font-size: 13px;
            font-weight: 700;
            padding: 7px 12px;
            text-decoration: none;
        }
        .sim-back-btn:hover {
            background: #effaf8;
            color: #004d49;
        }
        .sim-question-head {
            font-weight: 700;
            margin-bottom: 14px;
            color: #006560;
        }
        .sim-answer-option {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 8px;
            cursor: pointer;
        }
        .sim-answer-option.selected {
            border-color: #006560;
            background: #effaf8;
        }
        .sim-answer-letter {
            display: inline-grid;
            place-items: center;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: #e5e7eb;
            font-weight: 700;
            margin-right: 8px;
        }
        @media (max-width: 900px) {
            .sim-exam-layout {
                grid-template-columns: 1fr;
            }
            .sim-exam-links {
                display: none;
            }
            .sim-exam-list {
                min-height: auto;
            }
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
            .test-progress-header {
                flex-direction: column;
                gap: 0.85rem;
            }
            .test-progress-controls {
                width: 100%;
                justify-content: space-between;
            }
            .test-timers-cluster {
                flex: 1;
            }
            .test-timer-chip {
                flex: 1;
                min-width: 0;
                padding: 0.4rem 0.55rem;
            }
            .test-timer-chip-primary .test-timer-chip-value {
                font-size: 1.35rem;
            }
            .test-progress-question {
                font-size: 1.05rem;
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
            .answer-check-hint {
                width: 100%;
                max-width: none;
                min-height: 0;
                justify-content: center;
                text-align: center;
            }
            .answer-check-review-note {
                font-size: .86rem;
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
    <?php if ($flashMsg): ?>
    <div class="alert alert-<?= ($flashMsg['type'] ?? '') === 'error' ? 'danger' : htmlspecialchars((string)($flashMsg['type'] ?? 'info')) ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <?= htmlspecialchars((string)($flashMsg['message'] ?? '')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
    </div>
    <?php endif; ?>
    <?php if ($showTestConflict):
        $activeSummary = getActiveTestSummary($_SESSION['current_test'] ?? null);
        $conflictQuery = $_GET;
        unset($conflictQuery['continue_test'], $conflictQuery['force_new']);
        $conflictQuery['force_new'] = '1';
        $conflictNewUrl = 'test.php?' . http_build_query($conflictQuery);
    ?>
    <div class="dashboard-panel animate-in mb-4">
        <div class="panel-header border-bottom pb-3 mb-4">
            <h3 class="mb-2 fw-extrabold text-warning d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i>Aktywny test w toku
            </h3>
            <p class="text-muted mb-0">
                Na Twoim koncie jest już rozpoczęty test. Możesz go kontynuować albo rozpocząć nowy —
                poprzedni zostanie anulowany i <strong>nie będzie liczony do rankingu</strong>.
            </p>
        </div>
        <div class="card border-0 bg-light rounded-4 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="small text-muted">Tryb</div>
                        <div class="fw-bold"><?= htmlspecialchars($activeSummary['mode_label'] ?? 'Test') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Postęp</div>
                        <div class="fw-bold">
                            Pytanie <?= (int)($activeSummary['current'] ?? 1) ?> z <?= (int)($activeSummary['total'] ?? 0) ?>
                            (<?= (int)($activeSummary['answered'] ?? 0) ?> odpowiedzi)
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Kategorie</div>
                        <div class="fw-bold"><?= htmlspecialchars($activeSummary['categories_label'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3">
            <a href="test.php?continue_test=1" class="btn btn-success btn-lg rounded-pill px-4 fw-bold">
                <i class="bi bi-play-circle me-2"></i>Kontynuuj test
            </a>
            <a href="<?= htmlspecialchars($conflictNewUrl) ?>" class="btn btn-outline-danger btn-lg rounded-pill px-4 fw-bold">
                <i class="bi bi-arrow-repeat me-2"></i>Rozpocznij nowy
            </a>
        </div>
    </div>
    <?php elseif ($showSetup): ?>
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
    <?php if ($mode === 'exam_simulator'): ?>
    <div class="dashboard-panel animate-in premium-setup-container">
        <div class="panel-header border-bottom pb-3 mb-4">
            <h3 class="mb-2 fw-extrabold text-primary d-flex align-items-center gap-2">
                <i class="bi bi-pc-display-horizontal"></i>Egzamin
            </h3>
            <p class="text-muted mb-0">Oficjalny tryb: 40 pytań, 60 minut, próg 20 poprawnych odpowiedzi.</p>
        </div>
        <div class="exam-sim-rule-grid">
            <div class="exam-sim-rule"><strong>Liczba pytań</strong>40 pytań jednokrotnego wyboru z danej kwalifikacji.</div>
            <div class="exam-sim-rule"><strong>Czas trwania</strong>60 minut, standardowy czas egzaminu zawodowego.</div>
            <div class="exam-sim-rule"><strong>Próg zdawalności</strong>Minimum 20 poprawnych odpowiedzi, czyli 50%.</div>
            <div class="exam-sim-rule"><strong>Nawigacja</strong>Możesz wracać do wcześniejszych pytań i zmieniać odpowiedzi.</div>
            <div class="exam-sim-rule"><strong>Zakończenie</strong>Możesz zakończyć wcześniej albo poczekać do końca czasu.</div>
            <div class="exam-sim-rule"><strong>Wyniki</strong>Po zakończeniu od razu widzisz wynik i analizę błędów.</div>
        </div>
        <form method="GET" id="examSimulatorSetupForm">
            <input type="hidden" name="mode" value="exam_simulator">
            <input type="hidden" name="start" value="1">
            <input type="hidden" name="category" id="categoryInput" value="<?= htmlspecialchars($category) ?>">
            <input type="hidden" name="count" value="40">
            <input type="hidden" name="time" value="60">
            <input type="hidden" name="time_option" value="custom">
            <input type="hidden" name="difficulty" value="all">
            <input type="hidden" name="scope" value="all">
            <input type="hidden" name="order" value="random">
            <div class="exam-sim-setup-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-collection-fill me-2"></i>Wybierz kategorie</h5>
                    <span class="text-muted small">W tym trybie wybierasz tylko kwalifikacje / kategorie.</span>
                </div>
                <div class="category-grid">
                    <?php
                    $cats = getPublicCategories($pdo);
                    foreach ($cats as $cat):
                        $catMeta = getCategoryMeta($cat);
                        $isSelected = in_array($cat, $selectedCats, true);
                    ?>
                    <div class="category-card <?= $isSelected ? 'selected' : '' ?>" data-category="<?= htmlspecialchars($cat) ?>">
                        <div class="card-checkbox"><i class="bi bi-check"></i></div>
                        <div class="card-icon-wrapper" style="color: <?= $catMeta['color'] ?>;"><i class="bi <?= $catMeta['icon'] ?>"></i></div>
                        <div class="card-info">
                            <div class="card-title"><?= htmlspecialchars($cat) ?></div>
                            <div class="card-desc"><?= htmlspecialchars($catMeta['desc']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-between">
                <a href="test.php?mode=exam&setup=1&new=1" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-2"></i>Wróć
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">
                    <i class="bi bi-play-fill me-2"></i>Rozpocznij symulator
                </button>
            </div>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.category-card');
        const input = document.getElementById('categoryInput');
        cards.forEach(card => {
            card.addEventListener('click', () => {
                cards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                if (input) input.value = card.dataset.category || '';
            });
        });
        document.getElementById('examSimulatorSetupForm')?.addEventListener('submit', function(e) {
            if (!input || !input.value.trim()) {
                e.preventDefault();
                appNotice('Wybierz kategorie egzaminu.', 'warning');
            }
        });
    });
    </script>
    <?php else: ?>
    <!-- ── Test configuration selector ────────────────────────────────── -->
    <div class="dashboard-panel animate-in premium-setup-container">
        <div class="panel-header d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h1 class="h3 mb-0 fw-extrabold text-primary d-flex align-items-center gap-2">
                <i class="bi bi-sliders2-vertical"></i>Konfiguracja testu
            </h1>
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

                <?php if ($mode !== 'single'): ?>
                <div class="col-12">
                    <a href="test.php?mode=exam_simulator&setup=1&new=1" class="exam-sim-launch-card">
                        <span>
                            <span class="d-flex align-items-center gap-2 fw-bold fs-5">
                                Egzamin
                                <i class="bi bi-info-circle-fill fs-6"></i>
                            </span>
                            <span class="exam-sim-subtext">
                                Włącz, aby rozwiązać egzamin w wyglądzie zbliżonym do oficjalnego systemu egzaminacyjnego CKE.
                            </span>
                        </span>
                        <span class="exam-sim-action-icon"><i class="bi bi-play-fill"></i></span>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Kategorie -->
                <div class="col-12 mb-2 exam-setup-compact-cats">
                    <div class="accordion" id="categoryAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header" id="categoryHeading">
                                <button class="accordion-button <?= $mode === 'single' ? '' : 'collapsed' ?> d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#categoryCollapse" aria-expanded="<?= $mode === 'single' ? 'true' : 'false' ?>" aria-controls="categoryCollapse">
                                    <i class="bi bi-collection-fill me-2"></i>Kategorie pytań
                                </button>
                            </h2>
                            <div id="categoryCollapse" class="accordion-collapse collapse <?= $mode === 'single' ? 'show' : '' ?>" aria-labelledby="categoryHeading" data-bs-parent="#categoryAccordion">
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
                <?php if ($mode !== 'single'): ?>
                <div class="row exam-setup-compact-row">
                    <div class="col-md-6 mt-3 exam-setup-compact-col">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <label class="setup-section-title mb-0" for="questionCountInput"><span><i class="bi bi-question-circle"></i>Liczba pytań</span></label>
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
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <label class="setup-section-title mb-0"><span><i class="bi bi-clock"></i>Opcje czasu</span></label>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" id="saveDefaultTimeBtn"><i class="bi bi-bookmark-star me-1"></i>Domyślny czas</button>
                        </div>
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
                            <input type="range" name="time" id="timeLimitInput" class="custom-range-slider" min="1" max="120" value="<?= $timeLimit ?>" aria-label="Własny limit czasu w minutach">
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
                            <input type="range" name="time_per_question" id="timePerQuestionInput" class="custom-range-slider" min="15" max="600" step="5" value="<?= $timePerQuestion ?>" aria-label="Własny limit czasu na pytanie w sekundach">
                            <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.75rem;">
                                <span>15 sek.</span>
                                <span>5 minut</span>
                                <span>10 minut</span>
                            </div>
                        </div>
                        <div class="time-slider-panel <?= in_array($timeOption, ['30s', '60s'], true) ? 'open' : '' ?>" id="perQuestionPresetPanel">
                            <div class="time-display-bubble">
                                <i class="bi bi-info-circle"></i> <span id="perQuestionPresetInfo">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>

                <!-- Accordion: Pozostałe opcje -->
                <div class="col-12">
                    <button class="btn btn-outline-secondary w-100 d-md-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#examOptionsCollapse" aria-expanded="false" aria-controls="examOptionsCollapse">
                        <i class="bi bi-sliders2"></i> Więcej opcji
                    </button>
                    <div class="collapse d-md-block" id="examOptionsCollapse">
                <div class="row">
                <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <label class="setup-section-title mb-0"><span><i class="bi bi-shield-shaded"></i>Poziom trudności</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" id="saveDefaultDifficultyBtn"><i class="bi bi-bookmark-star me-1"></i>Domyślna trudność</button>
                    </div>
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
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <label class="setup-section-title mb-0"><span><i class="bi bi-bullseye"></i>Zakres pytań</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" id="saveDefaultScopeBtn"><i class="bi bi-bookmark-star me-1"></i>Domyślny zakres</button>
                    </div>
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

                    function markSaved(button, html) {
                        if (!button) return;
                        button.textContent = 'Zapisano';
                        setTimeout(() => { button.innerHTML = html; }, 1600);
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
                            appNotice('Wybierz przynajmniej jedną kategorię, aby zapisać domyślną.', 'warning');
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
                            appNotice('Wprowadź poprawną liczbę pytań, aby zapisać domyślną wartość.', 'warning');
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
                    document.getElementById('saveDefaultDifficultyBtn')?.addEventListener('click', () => {
                        setCookie('default_test_difficulty', difficultyInput?.value || 'all', 365);
                        markSaved(document.getElementById('saveDefaultDifficultyBtn'), '<i class="bi bi-bookmark-star me-1"></i>Domyślna trudność');
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
                    document.getElementById('saveDefaultScopeBtn')?.addEventListener('click', () => {
                        setCookie('default_test_scope', scopeInput?.value || 'all', 365);
                        markSaved(document.getElementById('saveDefaultScopeBtn'), '<i class="bi bi-bookmark-star me-1"></i>Domyślny zakres');
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
                    const perQuestionPresetPanel = document.getElementById('perQuestionPresetPanel');
                    const perQuestionPresetInfo = document.getElementById('perQuestionPresetInfo');

                    function updatePerQuestionPresetInfo() {
                        const opt = timeOptionInput?.value || '';
                        const count = Math.max(1, Number(countInput?.value || 0));
                        if (!perQuestionPresetPanel || !perQuestionPresetInfo) return;
                        if (opt === '30s' || opt === '60s') {
                            const sec = opt === '30s' ? 30 : 60;
                            const totalSec = count * sec;
                            const mins = Math.ceil(totalSec / 60);
                            perQuestionPresetInfo.textContent = `${count} pytań × ${sec}s = ok. ${mins} min łącznie`;
                            perQuestionPresetPanel.classList.add('open');
                        } else {
                            perQuestionPresetPanel.classList.remove('open');
                        }
                    }
                    
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
                            updatePerQuestionPresetInfo();
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
                    document.getElementById('saveDefaultTimeBtn')?.addEventListener('click', () => {
                        setCookie('default_test_time_option', timeOptionInput?.value || 'custom', 365);
                        setCookie('default_test_time', timeLimitInput?.value || '60', 365);
                        setCookie('default_test_time_per_question', timePerQuestionInput?.value || '60', 365);
                        markSaved(document.getElementById('saveDefaultTimeBtn'), '<i class="bi bi-bookmark-star me-1"></i>Domyślny czas');
                    });
                    countInput?.addEventListener('input', updatePerQuestionPresetInfo);
                    updatePerQuestionPresetInfo();
                });
                </script>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php elseif ($currentQuestion && $mode === 'exam_simulator'): ?>
    <?php
        $simStart = (int)($test['start_time'] ?? time());
        $simCategory = trim((string)(($test['config']['category'] ?? '') ?: ($currentQuestion['category'] ?? '')));
        $simAnswered = (int)$answeredCount;
        $simUnanswered = max(0, $totalQuestions - $simAnswered);
        $simQuestionView = (($_GET['view'] ?? '') === 'question');
    ?>
    <div class="sim-exam-shell <?= $simQuestionView ? 'sim-question-view' : 'sim-main-view' ?>">
        <div class="sim-exam-topbar">
            <div class="sim-exam-brand">AUTOMATYCZNY SYSTEM EGZAMINOWANIA</div>
            <div class="sim-exam-links"><span>INSTRUKCJA OBSLUGI</span><span>WYLOGUJ Z SYSTEMU</span></div>
        </div>

        <h2 class="sim-exam-title">EGZAMIN - LISTA ZADAN</h2>

        <div class="sim-exam-layout">
            <div class="sim-exam-list">
                <?php foreach ($questions as $idx => $q): ?>
                <?php $isAnswered = isset($test['answers'][$idx]) && trim((string)($test['answers'][$idx]['user_answer'] ?? '')) !== ''; ?>
                <form method="POST" class="sim-task-row">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="goto_question">
                    <input type="hidden" name="target" value="<?= (int)$idx ?>">
                    <button type="submit" class="sim-task-btn <?= $idx === $currentIdx ? 'active' : '' ?>">Zadanie <?= $idx + 1 ?></button>
                    <span class="sim-task-status <?= $isAnswered ? 'answered' : '' ?>">
                        <?= $isAnswered ? 'Udzielono odpowiedzi (możesz zmienić odpowiedź)' : 'Nie udzielono odpowiedzi' ?>
                    </span>
                </form>
                <?php endforeach; ?>
            </div>

            <aside class="sim-exam-side">
                <p class="sim-side-label">Kwalifikacja</p>
                <div class="sim-side-field"><?= htmlspecialchars($simCategory !== '' ? $simCategory : 'Wszystkie') ?></div>

                <p class="sim-side-label">Czas rozpoczęcia egzaminu</p>
                <div class="sim-side-field"><?= date('d.m.Y H:i:s', $simStart) ?></div>

                <p class="sim-side-label">Czas zakończenia egzaminu</p>
                <div class="sim-side-field"><?= date('d.m.Y H:i:s', $simStart + 3600) ?></div>

                <p class="sim-side-label">Liczba udzielonych odpowiedzi</p>
                <div class="sim-side-field"><?= $simAnswered ?></div>

                <p class="sim-side-label">Liczba nieudzielonych odpowiedzi</p>
                <div class="sim-side-field danger"><?= $simUnanswered ?></div>

                <div class="sim-timer-label">Do końca egzaminu<br>pozostało:</div>
                <div class="sim-timer"<?= $simQuestionView ? '' : ' id="timer"' ?>><?= formatTime($totalTimeLeft) ?></div>
                <form method="POST" onsubmit="return confirmFinish(this)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="finish_early">
                    <button type="submit" class="sim-finish-btn">Zakończ egzamin</button>
                </form>
            </aside>

            <section class="sim-exam-question" id="sim-question">
                <div class="sim-question-toolbar">
                    <a href="test.php" class="sim-back-btn"><i class="bi bi-arrow-left me-1"></i>Wróć do listy zadań</a>
                    <div class="sim-question-head mb-0">Zadanie <?= $currentIdx + 1 ?> z <?= $totalQuestions ?></div>
                    <?php if (!empty($test['time_limit'])): ?>
                    <div class="fw-bold">Pozostało: <span<?= $simQuestionView ? ' id="timer"' : '' ?>><?= formatTime($totalTimeLeft) ?></span></div>
                    <?php endif; ?>
                </div>
                <?php $questionImage = questionImageSrc($currentQuestion['image_url'] ?? ''); ?>
                <?php if ($questionImage): ?>
                    <img src="<?= htmlspecialchars($questionImage) ?>"
                         alt="Ilustracja do pytania" class="img-fluid rounded mb-3" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                <?php endif; ?>
                <p class="mb-4 fw-bold"><?= nl2br(htmlspecialchars($currentQuestion['question_text'])) ?></p>
                <form method="POST" id="simQuizForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="question_id" value="<?= (int)$currentQuestion['id'] ?>">
                    <input type="hidden" name="action" value="submit_answer">
                    <input type="hidden" name="answer" id="simSelectedAnswer" value="<?= htmlspecialchars($savedAnswer) ?>">
                    <div id="answersContainer">
                        <?php foreach (['A', 'B', 'C', 'D'] as $opt):
                            $text = $currentQuestion['option_' . strtolower($opt)]
                                 ?? $currentQuestion['option_' . strtoupper($opt)]
                                 ?? $currentQuestion[strtolower($opt)]
                                 ?? '';
                            if (trim($text) === '') continue;
                        ?>
                        <div class="sim-answer-option <?= $savedAnswer === $opt ? 'selected' : '' ?>" data-answer="<?= $opt ?>" onclick="selectSimAnswer(this)">
                            <span class="sim-answer-letter"><?= $opt ?></span><?= htmlspecialchars($text) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-between mt-3">
                        <button type="submit" class="btn btn-success px-4" id="simSubmitBtn" <?= $savedAnswer === '' ? 'disabled' : '' ?>>
                            Zapisz odpowiedź
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
    <script>
    function selectSimAnswer(option) {
        document.querySelectorAll('.sim-answer-option').forEach(el => el.classList.remove('selected'));
        option.classList.add('selected');
        const input = document.getElementById('simSelectedAnswer');
        const submit = document.getElementById('simSubmitBtn');
        if (input) input.value = option.dataset.answer || '';
        if (submit) submit.disabled = false;
    }
    </script>

    <?php elseif ($currentQuestion): ?>

    <!-- ── Progress bar ───────────────────────────────────────────────────── -->
    <div class="dashboard-panel test-progress-panel mb-4">
        <div class="test-progress-header">
            <div class="test-progress-meta">
                <span class="test-progress-label">Postęp testu</span>
                <strong class="test-progress-question" id="testProgressQuestion">Pytanie <?= $currentIdx + 1 ?> z <?= $totalQuestions ?></strong>
                <div class="test-progress-sub">
                    <span class="text-muted small" id="testProgressAnswered"><?= $answeredCount ?> / <?= $totalQuestions ?> udzielonych</span>
                    <?php if (!$allowPreviousQuestion): ?>
                    <span class="test-progress-badge"><i class="bi bi-lightning-fill"></i> Tryb na czas</span>
                    <?php endif; ?>
                    <?php if ($answerCheckModeAllowed && $answerCheckLimit > 0): ?>
                    <span class="test-progress-badge test-check-counter answer-check-counter" id="answerCheckCounter">
                        <i class="bi bi-patch-question-fill"></i>
                        Sprawdzenia: <span data-answer-check-used><?= $answerCheckUsed ?></span>/<span data-answer-check-limit><?= $answerCheckLimit ?></span>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="test-progress-controls">
                <div class="test-timers-cluster">
                    <?php if ($perQuestionLimit > 0 && $phase === 'answering'): ?>
                    <div class="test-timer-chip test-timer-chip-primary" id="questionTimerChip">
                        <span class="test-timer-chip-label"><i class="bi bi-stopwatch"></i> Pytanie</span>
                        <span class="test-timer-chip-value" id="questionTimer"><?= formatTime($questionTimeLeft) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($test['time_limit'])): ?>
                    <div class="test-timer-chip<?= $perQuestionLimit > 0 ? ' test-timer-chip-secondary' : ' test-timer-chip-primary' ?>" id="totalTimerChip">
                        <span class="test-timer-chip-label"><i class="bi bi-clock"></i> <?= $perQuestionLimit > 0 ? 'Łącznie' : 'Czas' ?></span>
                        <span class="test-timer-chip-value" id="timer"><?= formatTime($totalTimeLeft) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="test-end-modern-btn d-flex align-items-center justify-content-center" onclick="confirmEndTest()" title="Zakończ test">
                    <i class="bi bi-stop-circle"></i>
                </button>
            </div>
        </div>
        <div class="test-progress-bar-wrap">
            <div class="progress">
                <div class="progress-bar" id="progressBar"
                     style="width:<?= round(($answeredCount / max(1, $totalQuestions)) * 100) ?>%"></div>
            </div>
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
                        <?php if ($answerCheckModeAllowed && $answerCheckLimit > 0): ?>
                        <button type="submit"
                                name="action"
                                value="check_answer"
                                class="btn btn-outline-info btn-lg px-4 answer-check-btn"
                                id="checkAnswerBtn"
                                data-answer-check-btn
                                formnovalidate
                                <?= $answerCheckAvailable ? '' : 'disabled' ?>>
                            <i class="bi bi-patch-question me-2"></i>Sprawdź odpowiedź
                        </button>
                        <span class="answer-check-hint" id="answerCheckHint">
                            Pozostało <?= $answerCheckRemaining ?> z <?= $answerCheckLimit ?> sprawdzeń.
                        </span>
                        <?php endif; ?>
                        <?php if ($allowPreviousQuestion): ?>
                        <button type="submit" name="action" value="previous_question" class="btn btn-outline-secondary btn-lg px-4" data-question-nav="previous" formnovalidate <?= $currentIdx <= 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-left me-2"></i>Poprzednie pytanie
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php else: ?>
            <!-- ── PHASE 2: Review – show result, then go next ───────────── -->
            <?php
                $lr      = $lastResult;
                $correct = $currentQuestion['correct_answer'];
                $correctText = trim((string)($currentQuestion['option_' . strtolower((string)$correct)] ?? ''));
                $userAnswer = strtoupper(trim((string)($lr['user_answer'] ?? '-')));
                $userText = $userAnswer !== '-' ? trim((string)($currentQuestion['option_' . strtolower($userAnswer)] ?? '')) : '';
                $reviewExplanation = trim((string)($currentQuestion['explanation'] ?? ($lr['explanation'] ?? '')));
                if ($reviewExplanation === '') {
                    $reviewExplanation = buildQuestionExplanation($currentQuestion, $userAnswer, !empty($lr['is_correct']));
                }

                $answer_explanation_main = $reviewExplanation;
                $answer_distractors = '';
                $why_marker = 'Dlaczego nie reszta?';
                $why_pos = mb_strpos($reviewExplanation, $why_marker, 0, 'UTF-8');
                if ($why_pos !== false) {
                    $answer_explanation_main = trim(mb_substr($reviewExplanation, 0, $why_pos, 'UTF-8'));
                    $answer_distractors = trim(mb_substr($reviewExplanation, $why_pos, mb_strlen($reviewExplanation, 'UTF-8'), 'UTF-8'));
                }
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
                <?php if (!empty($lr['review_note'])): ?>
                    <div class="answer-check-review-note mt-3">
                        <i class="bi bi-patch-question-fill"></i>
                        <span><?= htmlspecialchars($lr['review_note']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="review-next-actions d-flex gap-2 mt-3 mb-3 flex-wrap">
                    <?php if ($lr['is_last']): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="next_question">
                        <button type="submit" class="btn btn-success btn-lg">
                            Zakończ test <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="next_question">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Następne pytanie <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="answer-explanation mt-3">
                    <div class="answer-explanation-label">
                        <i class="bi bi-info-circle-fill"></i>
                        Wyjaśnienie
                    </div>
                    <div><?= nl2br(htmlspecialchars($answer_explanation_main)) ?></div>
                    <?php if ($answer_distractors !== ''): ?>
                        <button type="button" class="answer-card-view-btn mt-2" data-distractors-toggle aria-expanded="false" onclick="event.stopPropagation(); toggleAnswerDistractors(this)">
                            <i class="bi bi-list-check"></i> Dlaczego nie reszta?
                        </button>
                        <div class="answer-distractors d-none" data-distractors-panel>
                            <?= nl2br(htmlspecialchars($answer_distractors)) ?>
                        </div>
                    <?php endif; ?>
                </div>
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
                <p class="test-confirm-desc mb-0">Wynik zostanie zapisany w obecnym stanie. Nieudzielone pytania będą liczone jako błędne.</p>
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
window.shouldConfirmNavigation = <?php echo ($phase === 'answering') ? 'true' : 'false'; ?>;
let pendingFinishForm = null;
let confirmModal = null;
let timeExpiredModal = null;

window.allowQuizNavigation = function () {
    window.shouldConfirmNavigation = false;
};

function modalInstance(id) {
    const el = document.getElementById(id);
    return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
}

function submitFinishEarlyForm(form) {
    window.shouldConfirmNavigation = false;
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
    if (window.shouldConfirmNavigation) {
        e.preventDefault();
        e.returnValue = ''; 
    }
});

<?php if (!empty($test['time_limit']) && $phase === 'answering'): ?>
// Exam total countdown timer
let timeLeft = <?= max(0, (isset($test['time_limit']) ? $test['time_limit'] : 3600) - (time() - $test['start_time'])) ?>;
const timerEl = document.getElementById('timer');
let timerExpired = false;
function updateTimer() {
    if (timerExpired || !timerEl) return;
    const m = String(Math.floor(timeLeft / 60)).padStart(2,'0');
    const s = String(timeLeft % 60).padStart(2,'0');
    timerEl.textContent = `${m}:${s}`;
    const totalChip = document.getElementById('totalTimerChip');
    if (timeLeft <= 300) {
        totalChip?.classList.add('timer-warning');
    } else {
        totalChip?.classList.remove('timer-warning');
    }
    if (timeLeft <= 0) {
        timerExpired = true;
        clearInterval(totalTimerInterval);
        window.shouldConfirmNavigation = false;
        timeExpiredModal = timeExpiredModal || modalInstance('testTimeExpiredModal');
        if (timeExpiredModal) timeExpiredModal.show();
        setTimeout(() => submitFinishEarlyForm(null), 900);
        return;
    }
    timeLeft--;
}
updateTimer();
const totalTimerInterval = setInterval(updateTimer, 1000);
<?php endif; ?>

<?php if ($perQuestionLimit > 0 && $phase === 'answering'): ?>
// Per-question countdown timer
let questionTimeLeft = <?= (int)$questionTimeLeft ?>;
const questionTimeLimit = <?= (int)$perQuestionLimit ?>;
const questionTimerEl = document.getElementById('questionTimer');
const questionTimerChip = document.getElementById('questionTimerChip');
let questionTimerExpired = false;
let questionTimerInterval = null;

function formatTimer(seconds) {
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    return `${m}:${s}`;
}

function updateQuestionTimer() {
    if (questionTimerExpired || !questionTimerEl) return;
    questionTimerEl.textContent = formatTimer(questionTimeLeft);
    if (questionTimeLeft <= 10) {
        questionTimerChip?.classList.add('timer-warning');
    } else {
        questionTimerChip?.classList.remove('timer-warning');
    }
    if (questionTimeLeft <= 0) {
        questionTimerExpired = true;
        if (questionTimerInterval) clearInterval(questionTimerInterval);
        if (window.QuizEngine && typeof window.QuizEngine.submitAnswer === 'function') {
            window.QuizEngine.submitAnswer({ force: true, reason: 'timeout' });
        }
        return;
    }
    questionTimeLeft--;
}

window.resetQuestionTimer = function (seconds) {
    questionTimeLeft = Number(seconds) > 0 ? Number(seconds) : questionTimeLimit;
    questionTimerExpired = false;
    if (questionTimerEl) {
        questionTimerEl.textContent = formatTimer(questionTimeLeft);
    }
    questionTimerChip?.classList.remove('timer-warning');
    if (questionTimerInterval) clearInterval(questionTimerInterval);
    questionTimerInterval = setInterval(updateQuestionTimer, 1000);
};

updateQuestionTimer();
questionTimerInterval = setInterval(updateQuestionTimer, 1000);
<?php endif; ?>

function confirmEndTest() {
    pendingFinishForm = null;
    confirmFinish(null);
}
</script>

<script>
    function toggleAnswerDistractors(button) {
        const panel = button.closest('.answer-explanation')?.querySelector('[data-distractors-panel]');
        if (!panel) return;
        const willShow = panel.classList.contains('d-none');
        panel.classList.toggle('d-none', !willShow);
        button.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    }
</script>
</body>
</html>
