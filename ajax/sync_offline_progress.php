<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(true, [], ['success' => false, 'error' => 'Unauthorized'], ['success' => false, 'error' => 'Unauthorized']);

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo securityJsonEncode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

$batch = is_array($payload['batch'] ?? null) ? $payload['batch'] : [];
$sm2Reviews = is_array($payload['sm2_reviews'] ?? null) ? $payload['sm2_reviews'] : [];

if (empty($batch) && empty($sm2Reviews)) {
    echo securityJsonEncode(['success' => false, 'error' => 'Invalid or empty payload']);
    exit;
}

$rateLimit = securityConsumeRateLimit('sync-offline:' . securityActorKey(), 30, 60);
if (empty($rateLimit['allowed'])) {
    http_response_code(429);
    echo securityJsonEncode(['success' => false, 'error' => 'Rate limit exceeded']);
    exit;
}

$syncedCount = 0;
$sm2SyncedCount = 0;
$totalXpAwarded = 0;

try {
    // 1. Process test results batch
    foreach ($batch as $testItem) {
        if (!is_array($testItem)) continue;

        // Check if this item is actually an SM-2 review object embedded in batch
        if (($testItem['type'] ?? '') === 'sm2_review' || (isset($testItem['card_key']) && isset($testItem['rating']))) {
            $sm2Reviews[] = $testItem;
            continue;
        }

        $totalQ = max(1, min(100, (int)($testItem['total_questions'] ?? count($testItem['questions'] ?? []))));
        $correctCount = max(0, min($totalQ, (int)($testItem['correct_answers'] ?? 0)));
        $scorePct = round(($correctCount / $totalQ) * 100, 1);
        $timeSpent = max(1, min(7200, (int)($testItem['time_spent'] ?? 60)));
        $mode = in_array($testItem['mode'] ?? '', ['exam', 'practice', 'category'], true) ? $testItem['mode'] : 'practice';
        $startTime = !empty($testItem['client_saved_at']) ? date('Y-m-d H:i:s', strtotime($testItem['client_saved_at'])) : date('Y-m-d H:i:s');
        $excludeFromRanking = ($totalQ >= 40 && in_array($mode, ['exam', 'exam_simulator'], true)) ? 0 : 1;

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO test_results (user_id, total_questions, correct_answers, score_percent, time_spent, mode, start_time, exclude_from_ranking)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $totalQ, $correctCount, $scorePct, $timeSpent, $mode, $startTime, $excludeFromRanking]);
        $resultId = (int)$pdo->lastInsertId();

        // Process individual answers if provided
        if (!empty($testItem['answers']) && is_array($testItem['answers'])) {
            $stmtAns = $pdo->prepare("
                INSERT INTO test_answers (result_id, question_id, user_answer, correct_answer, is_correct)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($testItem['answers'] as $ans) {
                $rawQid = (int)($ans['question_id'] ?? 0);
                if ($rawQid <= 0) continue;
                $qId = ensureQuestionRecordExists($pdo, [
                    'id' => $rawQid,
                    'question_text' => (string)($ans['question_text'] ?? ($ans['question'] ?? '')),
                    'category' => (string)($ans['category'] ?? ($testItem['category'] ?? 'Ogólne')),
                    'correct_answer' => (string)($ans['correct_answer'] ?? '')
                ]);
                $userAns = strtoupper(substr(trim((string)($ans['user_answer'] ?? '')), 0, 1));
                $correctAns = strtoupper(substr(trim((string)($ans['correct_answer'] ?? '')), 0, 1));
                $isCorrect = ($userAns !== '' && $correctAns !== '' && $userAns === $correctAns) ? 1 : 0;
                $stmtAns->execute([$resultId, $qId, $userAns, $correctAns, $isCorrect]);

                // Update question mastery
                updateQuestionProgress($pdo, $userId, $qId, (bool)$isCorrect);
            }
        }

        // Award XP based on score
        $xpEarned = 5 + ($correctCount * 2);
        if ($scorePct >= 50.0) {
            $xpEarned += 10; // Passing bonus
        }
        awardXp($pdo, $userId, $xpEarned, 'offline_practice_sync', $resultId, 'Synchronizacja testu rozwiązanego offline');
        $totalXpAwarded += $xpEarned;

        $pdo->commit();
        $syncedCount++;
    }

    // 2. Process SM-2 flashcard reviews batch
    if (!empty($sm2Reviews)) {
        $sm2Select = $pdo->prepare("
            SELECT easiness_factor, interval_days, repetition_count
            FROM flashcard_sm2
            WHERE user_id = ? AND card_key = ?
        ");

        $sm2Upsert = $pdo->prepare("
            INSERT INTO flashcard_sm2
                (user_id, card_key, easiness_factor, interval_days, repetition_count, next_review_date, last_rating)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                easiness_factor  = VALUES(easiness_factor),
                interval_days    = VALUES(interval_days),
                repetition_count = VALUES(repetition_count),
                next_review_date = VALUES(next_review_date),
                last_rating      = VALUES(last_rating)
        ");

        foreach ($sm2Reviews as $review) {
            if (!is_array($review)) continue;
            $cardKey = trim((string)($review['card_key'] ?? ''));
            $rating = (int)($review['rating'] ?? -1);
            if ($cardKey === '' || $rating < 0 || $rating > 3) continue;

            $sm2Select->execute([$userId, $cardKey]);
            $current = $sm2Select->fetch(PDO::FETCH_ASSOC) ?: [];

            $ef          = (float)($current['easiness_factor'] ?? 2.5);
            $interval    = (int)($current['interval_days']     ?? 1);
            $repetitions = (int)($current['repetition_count'] ?? 0);

            // SuperMemo SM-2 algorithm
            if ($rating < 3) {
                $interval    = 1;
                $repetitions = 0;
            } else {
                if ($repetitions === 0) {
                    $interval = 1;
                } elseif ($repetitions === 1) {
                    $interval = 6;
                } else {
                    $interval = (int)round($interval * $ef);
                }
                $repetitions++;
            }

            $ef = $ef + (0.1 - (3 - $rating) * (0.08 + (3 - $rating) * 0.02));
            $ef = max(1.3, round($ef, 4));
            $nextDate = date('Y-m-d', strtotime("+{$interval} days"));

            $sm2Upsert->execute([$userId, $cardKey, $ef, $interval, $repetitions, $nextDate, $rating]);
            $sm2SyncedCount++;
        }

        if ($sm2SyncedCount > 0) {
            $sm2Xp = max(1, (int)ceil($sm2SyncedCount / 2));
            awardXp($pdo, $userId, $sm2Xp, 'offline_sm2_sync', null, "Synchronizacja powtórek fiszek offline ($sm2SyncedCount)");
            $totalXpAwarded += $sm2Xp;
        }
    }

    echo securityJsonEncode([
        'success' => true,
        'synced_count' => $syncedCount,
        'sm2_synced_count' => $sm2SyncedCount,
        'total_xp_awarded' => $totalXpAwarded
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Sync offline progress error: ' . $e->getMessage());
    echo securityJsonEncode(['success' => false, 'error' => 'Database sync error']);
}
