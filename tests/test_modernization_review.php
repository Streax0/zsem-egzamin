<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

echo "=== Running Modernization & Function Robustness Verification ===\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $msg): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $msg\n";
        $passCount++;
    } else {
        echo " [FAIL] $msg\n";
        $failCount++;
    }
}

// 1. Test getAllCategories hash-set
$cats = getAllCategories(null);
assertTest(is_array($cats) && count($cats) > 0, "getAllCategories returns non-empty sorted array");
assertTest(in_array('INF.02', $cats, true) || in_array('INF.03', $cats, true), "getAllCategories contains INF.02 or INF.03");
$sortedCats = $cats;
sort($sortedCats);
assertTest($cats === $sortedCats, "getAllCategories is strictly sorted");

// 2. Test getNotificationPresentationMeta PHP 8.0 match expression
$metaRank = getNotificationPresentationMeta(['type' => 'rank_up']);
assertTest($metaRank['icon'] === 'bi-graph-up-arrow' && $metaRank['tone'] === 'success', "match rank_up returns correct tuple");

$metaMissions = getNotificationPresentationMeta(['type' => 'daily_missions_refresh']);
assertTest($metaMissions['icon'] === 'bi-arrow-repeat' && $metaMissions['tone'] === 'warning', "match daily_missions_refresh returns warning repeat");

$metaDuel = getNotificationPresentationMeta(['type' => 'duel_accepted']);
assertTest($metaDuel['icon'] === 'bi-lightning-charge' && $metaDuel['tone'] === 'danger', "match duel_accepted returns danger charge");

$metaDuelInvite = getNotificationPresentationMeta(['type' => 'duel_invite']);
assertTest($metaDuelInvite['icon'] === 'bi-lightning-charge' && $metaDuelInvite['tone'] === 'danger', "match duel_invite returns danger charge");

$metaDuelResult = getNotificationPresentationMeta(['type' => 'duel_result']);
assertTest($metaDuelResult['icon'] === 'bi-award' && $metaDuelResult['tone'] === 'info', "match duel_result returns info award");

$metaDefault = getNotificationPresentationMeta(['type' => 'unknown_type_xyz']);
assertTest($metaDefault['icon'] === 'bi-info-circle' && $metaDefault['tone'] === 'primary', "match unknown returns default System");

// 3. Test ensureQuestionRecordExists memoization
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE questions (
    id INTEGER PRIMARY KEY,
    category TEXT,
    question_text TEXT,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_answer TEXT,
    explanation TEXT,
    image_url TEXT
)");
$pdo->exec("INSERT INTO questions (id, category, question_text, option_a, option_b, option_c, option_d, correct_answer) 
VALUES (4242, 'INF.02', 'Sample Question 4242', 'A', 'B', 'C', 'D', 'A')");

$qObj = ['id' => 4242, 'question_text' => 'Sample Question 4242', 'category' => 'INF.02'];
$id1 = ensureQuestionRecordExists($pdo, $qObj);
assertTest($id1 === 4242, "ensureQuestionRecordExists finds existing question 4242");

// Drop table to verify that memoization in $verified prevents any subsequent SQL execution!
$pdo->exec("DROP TABLE questions");
$id2 = ensureQuestionRecordExists($pdo, $qObj);
assertTest($id2 === 4242, "ensureQuestionRecordExists returns memoized id without touching dropped table");

// 4. Test loadQuestions and getTotalQuestionsCount cache isolation between null PDO and active PDO
$pdo2 = new PDO('sqlite::memory:');
$pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo2->exec("CREATE TABLE questions (
    id INTEGER PRIMARY KEY,
    category TEXT,
    question_text TEXT,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_answer TEXT,
    explanation TEXT,
    image_url TEXT
)");
$pdo2->exec("INSERT INTO questions (id, category, question_text, option_a, option_b, option_c, option_d, correct_answer) 
VALUES (99001, 'INF.02', 'Extra Database Question Alpha', 'A', 'B', 'C', 'D', 'A')");

// First call: null PDO (e.g. from background task or script without DB)
$countNoPdo = getTotalQuestionsCount(null, true);
$questionsNoPdo = loadQuestions(null, true);
assertTest($countNoPdo === count($questionsNoPdo), "getTotalQuestionsCount(null) matches loadQuestions(null)");

// Second call: with active PDO containing DB question
$countWithPdo = getTotalQuestionsCount($pdo2, true);
$questionsWithPdo = loadQuestions($pdo2, true);
assertTest($countWithPdo === count($questionsWithPdo), "getTotalQuestionsCount(pdo) matches loadQuestions(pdo)");
assertTest($countWithPdo === $countNoPdo + 1, "Cache key correctly isolates JSON-only from DB+JSON questions ($countWithPdo === " . ($countNoPdo + 1) . ")");

// Third call: JSON-only with PDO
$countJsonOnly = getTotalQuestionsCount($pdo2, false);
assertTest($countJsonOnly === $countNoPdo, "getTotalQuestionsCount(pdo, false) correctly matches JSON-only count ($countJsonOnly === $countNoPdo)");

// 5. Test invalidateQuestionsCache
invalidateQuestionsCache();
assertTest(function_exists('invalidateQuestionsCache'), "invalidateQuestionsCache function exists and executes cleanly");

echo "\nSummary: $passCount PASSED, $failCount FAILED\n";
if ($failCount > 0) {
    exit(1);
}
