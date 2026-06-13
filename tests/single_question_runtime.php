<?php
require dirname(__DIR__) . '/includes/functions.php';

function checkSingleQuestion($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$test = [
    'mode' => 'single',
    'questions' => [
        ['id' => 11, 'category' => 'INF.02'],
        ['id' => 22, 'category' => 'INF.03'],
    ],
    'current' => 1,
    'phase' => 'reviewing',
    'last_result' => ['revealed_by_check' => true],
    'answers' => [
        1 => [
            'question_id' => 22,
            'user_answer' => 'B',
            'revealed_by_check' => true,
            'answer_check_attempt_number' => 2,
        ],
    ],
    'time_limit' => 600,
    'question_time_limit' => 60,
    'question_start_time' => time(),
    'answer_check_limit' => 3,
    'answer_check_used' => 2,
    'config' => [
        'category' => 'INF.03',
        'count' => 20,
        'time' => 10,
        'time_option' => '60s',
        'time_per_question' => 60,
    ],
];

checkSingleQuestion(normalizeSingleQuestionTestState($test), 'stale single-question state was not normalized');
checkSingleQuestion(count($test['questions']) === 1 && (int)$test['questions'][0]['id'] === 22, 'single mode did not keep exactly the current question');
checkSingleQuestion($test['current'] === 0, 'single mode current index was not reset');
checkSingleQuestion($test['answers'] === [], 'answer-check result survived single-mode normalization');
checkSingleQuestion($test['phase'] === 'answering' && !isset($test['last_result']), 'answer-check review survived single-mode normalization');
checkSingleQuestion($test['time_limit'] === 0 && $test['question_time_limit'] === 0, 'single mode retained a timer');
checkSingleQuestion(!isset($test['question_start_time']), 'single mode retained question timer state');
checkSingleQuestion($test['answer_check_limit'] === 0 && $test['answer_check_used'] === 0, 'single mode retained answer checks');
checkSingleQuestion($test['config']['category'] === 'INF.03', 'single mode lost selected category');
checkSingleQuestion($test['config']['count'] === 1, 'single mode config did not force one question');
checkSingleQuestion($test['config']['time'] === 0 && $test['config']['time_option'] === 'unlimited', 'single mode config retained time');
checkSingleQuestion($test['config']['time_per_question'] === 0, 'single mode config retained per-question time');

$payload = testAnswerCheckPayload($test);
checkSingleQuestion($payload['answer_check_limit'] === 0, 'single mode answer-check payload exposed a limit');
checkSingleQuestion($payload['answer_check_available'] === false, 'single mode answer check remained available');
checkSingleQuestion($payload['answer_check_disabled_reason'] === 'mode', 'single mode answer-check reason invalid');
checkSingleQuestion(normalizeSingleQuestionTestState($test) === false, 'single mode normalization is not idempotent');

$staleReview = [
    'mode' => 'single',
    'questions' => [['id' => 31, 'category' => 'INF.02']],
    'current' => 0,
    'phase' => 'reviewing',
    'last_result' => ['is_correct' => true, 'history_id' => 99],
    'answers' => [0 => ['question_id' => 44, 'user_answer' => 'A']],
    'config' => ['category' => 'INF.02,INF.03'],
];
checkSingleQuestion(normalizeSingleQuestionTestState($staleReview), 'mismatched review state was not normalized');
checkSingleQuestion($staleReview['answers'] === [], 'answer from another question survived normalization');
checkSingleQuestion($staleReview['phase'] === 'answering' && !isset($staleReview['last_result']), 'stale review data survived normalization');

$completed = [
    'mode' => 'single',
    'questions' => [['id' => 51, 'category' => 'INF.02']],
    'current' => 0,
    'phase' => 'reviewing',
    'answers' => [0 => ['question_id' => 51, 'user_answer' => 'C', 'history_id' => 123]],
    'last_result' => ['question_id' => 51, 'history_id' => 123],
    'config' => ['category' => 'INF.02,INF.03'],
];
checkSingleQuestion(testCanAdvanceFromReview($completed), 'completed single question cannot advance from review');
checkSingleQuestion(singleQuestionCompletedResultId($completed) === 123, 'single result history id was not retained');
checkSingleQuestion(singleQuestionCategoryFilter($completed) === 'INF.02,INF.03', 'multi-category filter was narrowed to current question');

$completed['last_result'] = [];
unset($completed['answers'][0]['history_id']);
checkSingleQuestion(singleQuestionCompletedResultId($completed) === 0, 'missing single result id reused stale state');
$completed['last_result'] = testReviewResultFromAnswer($completed, 0);
recordSingleQuestionResultId($completed, 456);
checkSingleQuestion(singleQuestionCompletedResultId($completed) === 456, 'single result id was not recorded with the answer');
$completed['phase'] = 'answering';
checkSingleQuestion(!testCanAdvanceFromReview($completed), 'unreviewed question can advance');
checkSingleQuestion(singleQuestionCompletedResultId($completed) === 0, 'unreviewed question reused an old result id');

$completed['phase'] = 'reviewing';
$completed['answers'][0]['question_id'] = 999;
checkSingleQuestion(!testCanAdvanceFromReview($completed), 'mismatched answer can advance');

$categoryFallback = [
    'questions' => [['category' => 'INF.04']],
    'config' => ['category' => ''],
];
checkSingleQuestion(singleQuestionCategoryFilter($categoryFallback) === 'INF.04', 'category fallback ignored current question');

$wrongHistory = [
    'mode' => 'single',
    'phase' => 'reviewing',
    'current' => 0,
    'questions' => [['id' => 71, 'correct_answer' => 'A']],
    'answers' => [0 => ['question_id' => 71, 'user_answer' => 'A']],
    'last_result' => [
        'question_id' => 72,
        'user_answer' => 'A',
        'correct_answer' => 'A',
        'history_id' => 789,
    ],
];
normalizeSingleQuestionTestState($wrongHistory);
checkSingleQuestion(singleQuestionCompletedResultId($wrongHistory) === 0, 'history id from another question survived normalization');
checkSingleQuestion((int)($wrongHistory['last_result']['question_id'] ?? 0) === 71, 'review was not rebuilt for the current question');

$practice = [
    'mode' => 'practice',
    'phase' => 'reviewing',
    'current' => 0,
    'questions' => [['id' => 61]],
    'answers' => [0 => ['question_id' => 61, 'user_answer' => 'A']],
];
checkSingleQuestion(testCanAdvanceFromReview($practice), 'practice review cannot advance');

$exam = $practice;
$exam['mode'] = 'exam';
checkSingleQuestion(!testCanAdvanceFromReview($exam), 'exam accepted forged review advancement');

$simulator = $practice;
$simulator['mode'] = 'exam_simulator';
checkSingleQuestion(!testCanAdvanceFromReview($simulator), 'exam simulator accepted forged review advancement');

$guestSingle = $practice;
$guestSingle['mode'] = 'single';
checkSingleQuestion(testCanAdvanceFromReview($guestSingle), 'guest single review cannot advance without database history');
checkSingleQuestion(singleQuestionCompletedResultId($guestSingle) === 0, 'guest single unexpectedly requires a persisted history id');

$regular = ['mode' => 'practice', 'time_limit' => 600, 'answer_check_limit' => 3];
$regularBefore = $regular;
checkSingleQuestion(normalizeSingleQuestionTestState($regular) === false && $regular === $regularBefore, 'regular mode was modified');

echo "single question runtime OK\n";
