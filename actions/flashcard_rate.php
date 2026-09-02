<?php
/**
 * SM-2 Flashcard Rating Action
 *
 * Accepts POST {card_key, rating} and updates the SuperMemo-2 state
 * for the current user. Returns JSON with next review date and interval.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

startSecureSession();

// Auth guard
if (!isset($_SESSION['user_id'])) {
    securitySendJson(['success' => false, 'error' => 'Nie zalogowany'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'error' => 'Metoda niedozwolona'], 405);
}

requireJsonCsrfToken();

$userId   = (int)$_SESSION['user_id'];
$cardKey  = trim((string)($_POST['card_key'] ?? ''));
$rating   = (int)($_POST['rating'] ?? -1);

if ($cardKey === '' || $rating < 0 || $rating > 3) {
    securitySendJson(['success' => false, 'error' => 'Nieprawidłowe parametry'], 400);
}

// Ensure tables exist (runtime guard)
if (function_exists('appRuntimeSchemaUpdatesEnabled') && appRuntimeSchemaUpdatesEnabled()) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS flashcard_sm2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        card_key VARCHAR(64) NOT NULL,
        easiness_factor FLOAT NOT NULL DEFAULT 2.5,
        interval_days INT NOT NULL DEFAULT 1,
        repetition_count INT NOT NULL DEFAULT 0,
        next_review_date DATE NOT NULL DEFAULT (CURDATE()),
        last_rating TINYINT DEFAULT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_card (user_id, card_key),
        INDEX idx_sm2_review (user_id, next_review_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Fetch current SM-2 state for this card
$_sm2Stmt = $pdo->prepare(
    "SELECT easiness_factor, interval_days, repetition_count
     FROM flashcard_sm2
     WHERE user_id = ? AND card_key = ?"
);
try {
    $_sm2Stmt->execute([$userId, $cardKey]);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), "doesn't exist") || $e->getCode() === '42S02') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS flashcard_sm2 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            card_key VARCHAR(64) NOT NULL,
            easiness_factor FLOAT NOT NULL DEFAULT 2.5,
            interval_days INT NOT NULL DEFAULT 1,
            repetition_count INT NOT NULL DEFAULT 0,
            next_review_date DATE NOT NULL DEFAULT (CURDATE()),
            last_rating TINYINT DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_card (user_id, card_key),
            INDEX idx_sm2_review (user_id, next_review_date),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $_sm2Stmt = $pdo->prepare(
            "SELECT easiness_factor, interval_days, repetition_count
             FROM flashcard_sm2
             WHERE user_id = ? AND card_key = ?"
        );
        $_sm2Stmt->execute([$userId, $cardKey]);
    } else {
        throw $e;
    }
}
$current = $_sm2Stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$ef         = (float)($current['easiness_factor'] ?? 2.5);
$interval   = (int)($current['interval_days']     ?? 1);
$repetitions = (int)($current['repetition_count'] ?? 0);

// --- SuperMemo SM-2 algorithm ---
if ($rating < 3) {
    // Incorrect or hard: reset
    $interval    = 1;
    $repetitions = 0;
} else {
    // Correct response
    if ($repetitions === 0) {
        $interval = 1;
    } elseif ($repetitions === 1) {
        $interval = 6;
    } else {
        $interval = (int)round($interval * $ef);
    }
    $repetitions++;
}

// Update easiness factor (never below 1.3)
$ef = $ef + (0.1 - (3 - $rating) * (0.08 + (3 - $rating) * 0.02));
$ef = max(1.3, round($ef, 4));

$nextDate = date('Y-m-d', strtotime("+{$interval} days"));

// Upsert SM-2 state
$stmt = $pdo->prepare("
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
$stmt->execute([$userId, $cardKey, $ef, $interval, $repetitions, $nextDate, $rating]);

securitySendJson([
    'success'     => true,
    'next_review' => $nextDate,
    'interval'    => $interval,
    'ef'          => $ef,
    'repetitions' => $repetitions,
]);
