<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(true, [], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

$ajaxUserId = (int)($_SESSION['user_id'] ?? 0);
if ($ajaxUserId > 0) {
    restoreActiveTestForUser($pdo, $ajaxUserId);
}

requireJsonCsrfToken();

$action = securityInputEnum($_POST['action'] ?? '', ['submit_answer', 'check_answer', 'previous_question', 'next_question', 'finish_early'], '');
$test = $_SESSION['current_test'] ?? null;
$isGuest = isGuestMode();

if (!$test && $action !== 'start_test') {
    echo securityJsonEncode(['success' => false, 'error' => 'No active test']);
    exit;
}

if ($action === '') {
    securityAudit('quiz_invalid_action', ['action' => $_POST['action'] ?? ''], 'warning');
    echo securityJsonEncode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$rateLimit = securityConsumeRateLimit('quiz-action:' . securityActorKey() . ':' . $action, $action === 'check_answer' ? 30 : 120, 60);
if (empty($rateLimit['allowed'])) {
    http_response_code(429);
    securityAudit('quiz_rate_limited', ['action' => $action, 'retry_after' => $rateLimit['retry_after'] ?? 0], 'warning');
    echo securityJsonEncode(array_merge([
        'success' => false,
        'error' => 'Zbyt wiele akcji naraz. Odczekaj chwilę i spróbuj ponownie.',
        'retry_after' => (int)($rateLimit['retry_after'] ?? 0),
    ], is_array($test) ? testProgressPayload($test) : []));
    exit;
}

function questionTimerPayload(array $test): array {
    $limit = getTestQuestionTimeLimit($test);
    if ($limit <= 0) {
        return [];
    }
    return [
        'question_time_limit' => $limit,
        'question_time_left' => getTestQuestionTimeRemaining($test, $limit),
    ];
}

function testProgressPayload(array $test): array {
    return array_merge([
        'current' => max(0, (int)($test['current'] ?? 0)),
        'total' => count($test['questions'] ?? []),
        'answered_count' => count($test['answers'] ?? []),
    ], testAnswerCheckPayload($test));
}

function emitCurrentQuestion($test) {
    $current = max(0, (int)($test['current'] ?? 0));
    $total = count($test['questions'] ?? []);
    if ($total < 1 || !isset($test['questions'][$current])) {
        echo securityJsonEncode(['success' => false, 'error' => 'No active question']);
        return;
    }
    echo securityJsonEncode(array_merge([
        'success' => true,
        'finished' => false,
        'next_question' => true,
        'current' => $current,
        'total' => $total,
        'question' => formatQuestionForAjax($test['questions'][$current])
    ], questionTimerPayload($test), testProgressPayload($test)));
}

switch ($action) {
    case 'submit_answer':
        $questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        $userAnswer = securityInputAnswerLetter($_POST['answer'] ?? '');
        $currentIdx = $test['current'];

        if (!isset($test['questions'][$currentIdx])) {
            echo securityJsonEncode(['success' => false, 'error' => 'No active question']);
            break;
        }

        if ((int)$test['questions'][$currentIdx]['id'] !== $questionId) {
            $previousIdx = $currentIdx - 1;
            $previousAnswer = $test['answers'][$previousIdx] ?? null;
            $previousQuestion = $test['questions'][$previousIdx] ?? null;
            if ($previousQuestion && (int)$previousQuestion['id'] === $questionId && $previousAnswer) {
                emitCurrentQuestion($test);
                break;
            }
            echo securityJsonEncode(['success' => false, 'error' => 'Invalid question']);
            break;
        }
        
        if (isset($test['questions'][$currentIdx]) && (int)$test['questions'][$currentIdx]['id'] === $questionId) {
            $q = $test['questions'][$currentIdx];
            $correctAnswer = strtoupper(trim((string)($q['correct_answer'] ?? ($q['correct'] ?? ''))));
            $isCorrect = ($userAnswer !== '' && $correctAnswer !== '' && $userAnswer === $correctAnswer);

            if (!empty($test['answers'][$currentIdx]['revealed_by_check'])) {
                $test['phase'] = 'reviewing';
                $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
                saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
                echo securityJsonEncode(array_merge([
                    'success' => true,
                    'phase' => 'review',
                    'result' => $test['last_result']
                ], testProgressPayload($test)));
                break;
            }
            if (($test['phase'] ?? 'answering') !== 'answering') {
                if (!empty($test['answers'][$currentIdx])) {
                    $test['phase'] = 'reviewing';
                    $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
                    saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
                    echo securityJsonEncode(array_merge([
                        'success' => true,
                        'phase' => 'review',
                        'result' => $test['last_result']
                    ], testProgressPayload($test)));
                } else {
                    echo securityJsonEncode(['success' => false, 'error' => 'Question already reviewed']);
                }
                break;
            }
            
            $test['answers'][$currentIdx] = [
                'question_id' => $questionId,
                'user_answer' => $userAnswer,
                'correct' => $isCorrect
            ];
            
            if (!$isGuest && isset($_SESSION['user_id'])) {
                $qId = ensureQuestionRecordExists($pdo, $q);
                updateQuestionProgress($pdo, $_SESSION['user_id'], $qId, $isCorrect);
                $test['progress_updated'][$currentIdx] = true;
            }
            
            if ($test['mode'] === 'exam') {
                $test['current']++;
                touchTestQuestionStart($test);
                saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
                
                if ($test['current'] >= count($test['questions'])) {
                    if ($isGuest) {
                        $resultId = finishGuestTest($test);
                        echo securityJsonEncode(['success' => true, 'finished' => true, 'redirect' => "result.php?guest=" . rawurlencode($resultId)]);
                    } else {
                        $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
                        echo securityJsonEncode(['success' => true, 'finished' => true, 'redirect' => "result.php?id=$resultId"]);
                    }
                } else {
                    $nextIdx = $test['current'];
                    $savedAnswer = $test['answers'][$nextIdx]['user_answer'] ?? '';
                    echo securityJsonEncode(array_merge([
                        'success' => true, 
                        'finished' => false, 
                        'next_question' => true,
                        'current' => $test['current'],
                        'total' => count($test['questions']),
                        'question' => formatQuestionForAjax($test['questions'][$test['current']]),
                        'saved_answer' => $savedAnswer
                    ], questionTimerPayload($test), testProgressPayload($test)));
                }
            } else {
                // Practice mode: show review
                $test['phase'] = 'reviewing';
                $isLast = ($currentIdx >= count($test['questions']) - 1);
                // For single-question mode, present review but allow loading another question
                if (isset($test['mode']) && $test['mode'] === 'single') {
                    $isLast = false;
                }
                $test['last_result'] = [
                    'is_correct' => $isCorrect,
                    'user_answer' => $userAnswer,
                    'user_answer_text' => $q['option_' . strtolower((string)$userAnswer)] ?? '',
                    'correct_answer' => $q['correct_answer'],
                    'correct_answer_text' => $q['option_' . strtolower((string)$q['correct_answer'])] ?? '',
                    'explanation' => buildQuestionExplanation($q, $userAnswer, $isCorrect),
                    'is_last' => $isLast
                ];

                // Persist single-question result to history for 'single' mode
                if (!$isGuest && isset($test['mode']) && $test['mode'] === 'single') {
                    $historyId = saveSingleQuestionResult($pdo, $_SESSION['user_id'], $q, $userAnswer, $isCorrect);
                    recordSingleQuestionResultId($test, $historyId);
                }

                saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
                echo securityJsonEncode(array_merge([
                    'success' => true,
                    'phase' => 'review',
                    'result' => $test['last_result']
                ], testProgressPayload($test)));
            }
        } else {
            echo securityJsonEncode(['success' => false, 'error' => 'Invalid question']);
        }
        break;

    case 'check_answer':
        $questionId = securityInputInt($_POST['question_id'] ?? 0, 0, PHP_INT_MAX, 0);
        $userAnswer = securityInputAnswerLetter($_POST['answer'] ?? '');
        $currentIdx = $test['current'] ?? 0;
        $q = $test['questions'][$currentIdx] ?? null;
        if ($q && (int)($q['id'] ?? 0) === $questionId && $pdo) {
            $qId = ensureQuestionRecordExists($pdo, $q);
        }
        $checkResult = applyTestAnswerCheck($test, $questionId, $userAnswer, $pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $isGuest);

        if (!empty($checkResult['success'])) {
            $test['progress_updated'][$currentIdx] = true;
            saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
            echo securityJsonEncode(array_merge([
                'success' => true,
                'phase' => 'review',
                'result' => $checkResult['result'],
                'already_checked' => !empty($checkResult['already_checked']),
            ], testProgressPayload($test)));
        } else {
            echo securityJsonEncode(array_merge([
                'success' => false,
                'error' => $checkResult['error'] ?? 'Nie można sprawdzić odpowiedzi.',
            ], testProgressPayload($test)));
        }
        break;

    case 'previous_question':
        if (testDisallowsPreviousQuestion($test)) {
            echo securityJsonEncode(['success' => false, 'error' => 'Cofanie pytań jest wyłączone w tym trybie']);
            break;
        }
        $test['current'] = max(0, min(count($test['questions']) - 1, (int)($test['current'] ?? 0) - 1));
        $test['phase'] = 'answering';
        $test['last_result'] = null;
        if (restoreCheckedQuestionReview($test)) {
            saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
            echo securityJsonEncode(array_merge([
                'success' => true,
                'phase' => 'review',
                'result' => $test['last_result'],
            ], testProgressPayload($test)));
            break;
        }
        touchTestQuestionStart($test);
        saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
        $savedAnswer = $test['answers'][$test['current']]['user_answer'] ?? '';
        echo securityJsonEncode(array_merge([
            'success' => true,
            'current' => $test['current'],
            'total' => count($test['questions']),
            'question' => formatQuestionForAjax($test['questions'][$test['current']]),
            'saved_answer' => $savedAnswer
        ], questionTimerPayload($test), testProgressPayload($test)));
        break;

    case 'next_question':
        if (!testCanAdvanceFromReview($test)) {
            echo securityJsonEncode(array_merge([
                'success' => false,
                'error' => 'Najpierw odpowiedz na bieżące pytanie.',
            ], testProgressPayload($test)));
            break;
        }
        if (($test['mode'] ?? '') === 'single' && !$isGuest) {
            $singleResultId = ensureSingleQuestionResultSaved($pdo, $test, $ajaxUserId);
            if ($singleResultId <= 0) {
                echo securityJsonEncode(array_merge([
                    'success' => false,
                    'error' => 'Nie udało się zapisać wyniku. Spróbuj ponownie.',
                ], testProgressPayload($test)));
                break;
            }
        }
        $test['current']++;
        $test['phase'] = 'answering';
        $test['last_result'] = null;
        touchTestQuestionStart($test);

        // If in single-question mode, instead of finishing the test, load a new random question
        if (isset($test['mode']) && $test['mode'] === 'single') {
            $nextSingle = prepareNextSingleQuestion($pdo, $test, $ajaxUserId > 0 ? $ajaxUserId : null, $isGuest);
            if (empty($nextSingle['success'])) {
                echo securityJsonEncode(['success' => false, 'error' => $nextSingle['error'] ?? 'No questions available in selected category']);
                exit;
            }
            $newQ = $nextSingle['question'];
            saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);

            echo securityJsonEncode(array_merge([
                'success' => true,
                'current' => 0,
                'total' => 1,
                'question' => formatQuestionForAjax($newQ)
            ], questionTimerPayload($test), testProgressPayload($test)));
        } else {
            if ($test['current'] >= count($test['questions'])) {
                if ($isGuest) {
                    $resultId = finishGuestTest($test);
                    echo securityJsonEncode(['success' => true, 'finished' => true, 'redirect' => "result.php?guest=" . rawurlencode($resultId)]);
                } else {
                    $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
                    echo securityJsonEncode(['success' => true, 'finished' => true, 'redirect' => "result.php?id=$resultId"]);
                }
            } else {
                if (restoreCheckedQuestionReview($test)) {
                    saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
                    echo securityJsonEncode(array_merge([
                        'success' => true,
                        'phase' => 'review',
                        'result' => $test['last_result'],
                    ], testProgressPayload($test)));
                    break;
                }
                saveCurrentTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null, $test);
                $savedAnswer = $test['answers'][$test['current']]['user_answer'] ?? '';
                echo securityJsonEncode(array_merge([
                    'success' => true,
                    'current' => $test['current'],
                    'total' => count($test['questions']),
                    'question' => formatQuestionForAjax($test['questions'][$test['current']]),
                    'saved_answer' => $savedAnswer
                ], questionTimerPayload($test), testProgressPayload($test)));
            }
        }
        break;

    case 'finish_early':
        if (($test['mode'] ?? '') === 'single' && !$isGuest) {
            if (testHasReviewedCurrentAnswer($test)) {
                $resultId = ensureSingleQuestionResultSaved($pdo, $test, $ajaxUserId);
                if ($resultId <= 0) {
                    echo securityJsonEncode(['success' => false, 'error' => 'Nie udało się zapisać wyniku. Spróbuj ponownie.']);
                    break;
                }
            } else {
                $resultId = 0;
            }
            cancelActiveTest($pdo, $ajaxUserId > 0 ? $ajaxUserId : null);
            $redirect = $resultId > 0 ? "result.php?id=$resultId" : 'test.php?mode=single&setup=1';
            echo securityJsonEncode(['success' => true, 'redirect' => $redirect]);
            break;
        }
        if ($isGuest) {
            $resultId = finishGuestTest($test);
            echo securityJsonEncode(['success' => true, 'redirect' => "result.php?guest=" . rawurlencode($resultId)]);
        } else {
            $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
            echo securityJsonEncode(['success' => true, 'redirect' => "result.php?id=$resultId"]);
        }
        break;

    default:
        echo securityJsonEncode(['success' => false, 'error' => 'Unknown action']);
}

function formatQuestionForAjax($q, ?PDO $pdo = null, int $userId = 0) {
    $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
    $userId = $userId > 0 ? $userId : (int)($_SESSION['user_id'] ?? 0);
    $isBookmarked = false;
    $qId = (int)($q['id'] ?? 0);
    if ($pdo && $userId > 0 && $qId > 0) {
        try {
            $bmStmt = $pdo->prepare("SELECT 1 FROM user_bookmarks WHERE user_id = ? AND question_id = ? LIMIT 1");
            $bmStmt->execute([$userId, $qId]);
            $isBookmarked = (bool)$bmStmt->fetchColumn();
        } catch (Throwable $e) {
            $isBookmarked = false;
        }
    }
    return [
        'id' => $q['id'],
        'question_text' => $q['question_text'],
        'explanation' => $q['explanation'] ?? '',
        'image_url' => questionImageSrc($q['image_url'] ?? '') ?? '',
        'category' => $q['category'] ?? 'Ogólne',
        'is_bookmarked' => $isBookmarked,
        'options' => [
            'A' => $q['option_a'] ?? $q['A'] ?? '',
            'B' => $q['option_b'] ?? $q['B'] ?? '',
            'C' => $q['option_c'] ?? $q['C'] ?? '',
            'D' => $q['option_d'] ?? $q['D'] ?? ''
        ]
    ];
}
