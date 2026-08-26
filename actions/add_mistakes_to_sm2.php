<?php
/**
 * Add Test Mistakes to SM-2 Spaced Repetition Flashcards
 *
 * Accepts POST {result_id}
 * Queries all wrong questions from test_answers for this result and inserts them into flashcard_sm2.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    securitySendJson(['success' => false, 'error' => 'Wymagane zalogowanie.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'error' => 'Metoda niedozwolona.'], 405);
}

requireJsonCsrfToken();

$userId   = (int)$_SESSION['user_id'];
$resultId = (int)($_POST['result_id'] ?? 0);

if ($resultId <= 0) {
    securitySendJson(['success' => false, 'error' => 'Nieprawidłowe ID wyniku testu.'], 400);
}

try {
    // Verify ownership of test result
    $chkStmt = $pdo->prepare("SELECT id FROM test_results WHERE id = ? AND user_id = ?");
    $chkStmt->execute([$resultId, $userId]);
    if (!$chkStmt->fetch()) {
        securitySendJson(['success' => false, 'error' => 'Brak uprawnień do tego wyniku testu.'], 403);
    }

    // Fetch all wrong question IDs from this test result
    $ansStmt = $pdo->prepare("
        SELECT ta.question_id
        FROM test_answers ta
        WHERE ta.result_id = ? AND (ta.is_correct = 0 OR ta.user_answer = '-' OR ta.user_answer != ta.correct_answer)
    ");
    $ansStmt->execute([$resultId]);
    $wrongQids = $ansStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($wrongQids)) {
        securitySendJson([
            'success' => true,
            'added_count' => 0,
            'message' => 'Gratulacje! W tym teście nie ma błędnych odpowiedzi.',
        ]);
    }

    // Insert or reset SM-2 card state for each wrong question
    $added = 0;
    $insStmt = $pdo->prepare("
        INSERT INTO flashcard_sm2 (user_id, card_key, easiness_factor, interval_days, repetition_count, next_review_date, last_rating, updated_at)
        VALUES (?, ?, 2.5, 1, 0, CURDATE(), NULL, NOW())
        ON DUPLICATE KEY UPDATE
            interval_days = 1,
            repetition_count = 0,
            next_review_date = CURDATE(),
            updated_at = NOW()
    ");

    foreach ($wrongQids as $qid) {
        $qid = (int)$qid;
        if ($qid <= 0) continue;
        $cardKey = 'q_' . $qid;
        $insStmt->execute([$userId, $cardKey]);
        $added++;
    }

    securitySendJson([
        'success'     => true,
        'added_count' => $added,
        'message'     => "Dodano {$added} błędnych pytań do powtórek fiszkowych (SM-2). Możesz je powtórzyć w module Fiszek!",
    ]);
} catch (Throwable $e) {
    securitySendJson(['success' => false, 'error' => 'Nie udało się dodać pytań do bazy fiszek.'], 500);
}
