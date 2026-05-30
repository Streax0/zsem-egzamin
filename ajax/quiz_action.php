<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
header('Content-Type: application/json');

if (!isLoggedIn() && !isGuestMode()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

requireJsonCsrfToken();

$action = $_POST['action'] ?? '';
$test = $_SESSION['current_test'] ?? null;
$isGuest = isGuestMode();

if (!$test && $action !== 'start_test') {
    echo json_encode(['success' => false, 'error' => 'No active test']);
    exit;
}

function emitCurrentQuestion($test) {
    $current = max(0, (int)($test['current'] ?? 0));
    $total = count($test['questions'] ?? []);
    if ($total < 1 || !isset($test['questions'][$current])) {
        echo json_encode(['success' => false, 'error' => 'No active question']);
        return;
    }
    echo json_encode([
        'success' => true,
        'finished' => false,
        'next_question' => true,
        'current' => $current,
        'total' => $total,
        'question' => formatQuestionForAjax($test['questions'][$current])
    ]);
}

switch ($action) {
    case 'submit_answer':
        $questionId = (int)($_POST['question_id'] ?? 0);
        $userAnswer = strtoupper(trim($_POST['answer'] ?? ''));
        $currentIdx = $test['current'];

        if (!isset($test['questions'][$currentIdx])) {
            echo json_encode(['success' => false, 'error' => 'No active question']);
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
            echo json_encode(['success' => false, 'error' => 'Invalid question']);
            break;
        }
        
        if (isset($test['questions'][$currentIdx]) && (int)$test['questions'][$currentIdx]['id'] === $questionId) {
            $q = $test['questions'][$currentIdx];
            $isCorrect = ($userAnswer === $q['correct_answer']);
            
            $test['answers'][$currentIdx] = [
                'question_id' => $questionId,
                'user_answer' => $userAnswer,
                'correct' => $isCorrect
            ];
            
            if (!$isGuest && isset($_SESSION['user_id'])) {
                updateQuestionProgress($pdo, $_SESSION['user_id'], $questionId, $isCorrect);
            }
            
            if ($test['mode'] === 'exam') {
                $test['current']++;
                $_SESSION['current_test'] = $test;
                
                if ($test['current'] >= count($test['questions'])) {
                    if ($isGuest) {
                        $resultId = finishGuestTest($test);
                        echo json_encode(['success' => true, 'finished' => true, 'redirect' => "result.php?guest=" . rawurlencode($resultId)]);
                    } else {
                        $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
                        echo json_encode(['success' => true, 'finished' => true, 'redirect' => "result.php?id=$resultId"]);
                    }
                } else {
                    $nextIdx = $test['current'];
                    $savedAnswer = $test['answers'][$nextIdx]['user_answer'] ?? '';
                    echo json_encode([
                        'success' => true, 
                        'finished' => false, 
                        'next_question' => true,
                        'current' => $test['current'],
                        'total' => count($test['questions']),
                        'question' => formatQuestionForAjax($test['questions'][$test['current']]),
                        'saved_answer' => $savedAnswer
                    ]);
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
                    'correct_answer' => $q['correct_answer'],
                    'is_last' => $isLast
                ];

                // Persist single-question result to history for 'single' mode
                if (!$isGuest && isset($test['mode']) && $test['mode'] === 'single') {
                    $historyId = saveSingleQuestionResult($pdo, $_SESSION['user_id'], $q, $userAnswer, $isCorrect);
                    $test['last_result']['history_id'] = $historyId;
                }

                $_SESSION['current_test'] = $test;
                echo json_encode([
                    'success' => true,
                    'phase' => 'review',
                    'result' => $test['last_result']
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid question']);
        }
        break;

    case 'previous_question':
        $test['current'] = max(0, min(count($test['questions']) - 1, (int)($test['current'] ?? 0) - 1));
        $test['phase'] = 'answering';
        $test['last_result'] = null;
        $_SESSION['current_test'] = $test;
        $savedAnswer = $test['answers'][$test['current']]['user_answer'] ?? '';
        echo json_encode([
            'success' => true,
            'current' => $test['current'],
            'total' => count($test['questions']),
            'question' => formatQuestionForAjax($test['questions'][$test['current']]),
            'saved_answer' => $savedAnswer
        ]);
        break;

    case 'next_question':
        $test['current']++;
        $test['phase'] = 'answering';
        $test['last_result'] = null;

        // If in single-question mode, instead of finishing the test, load a new random question
        if (isset($test['mode']) && $test['mode'] === 'single') {
            // Determine category preference from previous question (if any)
            $prevCategory = $test['questions'][0]['category'] ?? '';
            $allQuestions = loadQuestions($pdo, false);
            $pool = $allQuestions;
            if (!empty($prevCategory)) {
                $pool = array_values(array_filter($allQuestions, function($qq) use ($prevCategory) { return ($qq['category'] ?? '') === $prevCategory; }));
            }
            if (empty($pool)) {
                echo json_encode(['success' => false, 'error' => 'No questions available in selected category']);
                exit;
            }
            if (!$isGuest && !empty($test['smart']) && isset($_SESSION['user_id'])) {
                $newQset = getWeightedRandomQuestions($pdo, $pool, 1, $_SESSION['user_id']);
            } else {
                $newQset = getRandomQuestions($pool, 1);
            }
            if (empty($newQset)) {
                echo json_encode(['success' => false, 'error' => 'No questions available in selected category']);
                exit;
            }
            $newQ = $newQset[0];

            $test['questions'] = [$newQ];
            $test['current'] = 0;
            $test['phase'] = 'answering';
            $test['last_result'] = null;
            $_SESSION['current_test'] = $test;

            echo json_encode([
                'success' => true,
                'current' => 0,
                'total' => 1,
                'question' => formatQuestionForAjax($newQ)
            ]);
        } else {
            if ($test['current'] >= count($test['questions'])) {
                if ($isGuest) {
                    $resultId = finishGuestTest($test);
                    echo json_encode(['success' => true, 'finished' => true, 'redirect' => "result.php?guest=" . rawurlencode($resultId)]);
                } else {
                    $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
                    echo json_encode(['success' => true, 'finished' => true, 'redirect' => "result.php?id=$resultId"]);
                }
            } else {
                $_SESSION['current_test'] = $test;
                $savedAnswer = $test['answers'][$test['current']]['user_answer'] ?? '';
                echo json_encode([
                    'success' => true,
                    'current' => $test['current'],
                    'total' => count($test['questions']),
                    'question' => formatQuestionForAjax($test['questions'][$test['current']]),
                    'saved_answer' => $savedAnswer
                ]);
            }
        }
        break;

    case 'finish_early':
        if ($isGuest) {
            $resultId = finishGuestTest($test);
            echo json_encode(['success' => true, 'redirect' => "result.php?guest=" . rawurlencode($resultId)]);
        } else {
            $resultId = finishTest($pdo, $_SESSION['user_id'], $test);
            echo json_encode(['success' => true, 'redirect' => "result.php?id=$resultId"]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

function formatQuestionForAjax($q) {
    return [
        'id' => $q['id'],
        'question_text' => $q['question_text'],
        'image_url' => questionImageSrc($q['image_url'] ?? '') ?? '',
        'category' => $q['category'] ?? 'Ogólne',
        'options' => [
            'A' => $q['option_a'] ?? $q['A'] ?? '',
            'B' => $q['option_b'] ?? $q['B'] ?? '',
            'C' => $q['option_c'] ?? $q['C'] ?? '',
            'D' => $q['option_d'] ?? $q['D'] ?? ''
        ]
    ];
}
