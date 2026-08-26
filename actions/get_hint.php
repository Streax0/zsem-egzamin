<?php
/**
 * Progressive Hint Assistant — AJAX Action
 *
 * Accepts POST {question_id, tier} and returns progressive hint content.
 * Tier 1: Conceptual clue   (-2 XP)
 * Tier 2: 50/50 elimination (-5 XP)
 * Tier 3: Full explanation  (-10 XP)
 *
 * Returns JSON {success, hint, eliminated, xp_cost, new_xp}
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    securitySendJson(['success' => false, 'error' => 'Nie zalogowany'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'error' => 'Metoda niedozwolona'], 405);
}

requireJsonCsrfToken();

$userId     = (int)$_SESSION['user_id'];
$questionId = (int)($_POST['question_id'] ?? 0);
$tier       = (int)($_POST['tier'] ?? 0);

if ($questionId <= 0 || $tier < 1 || $tier > 3) {
    securitySendJson(['success' => false, 'error' => 'Nieprawidłowe parametry'], 400);
}

// Block Tier 3 hints during active exam sessions to prevent exam answer leaks
if ($tier === 3) {
    $hasActiveExam = (function_exists('hasActiveTestInSession') && hasActiveTestInSession())
        || (isset($_SESSION['test_active']) && $_SESSION['test_active'] === true)
        || (isset($_SESSION['exam_id']) && (int)$_SESSION['exam_id'] > 0);
    if ($hasActiveExam) {
        securitySendJson([
            'success' => false,
            'error'   => 'Podpowiedzi 3. stopnia (pełna odpowiedź) są zablokowane podczas trwającego egzaminu.',
        ], 403);
    }
}

// XP costs per tier
$xpCosts = [1 => 2, 2 => 5, 3 => 10];
$xpCost  = $xpCosts[$tier];

// Fetch question
$_stmt = $pdo->prepare(
    "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
            q.correct_answer, q.explanation,
            qh.hint_tier1, qh.hint_tier2, qh.hint_tier3
     FROM questions q
     LEFT JOIN question_hints qh ON qh.question_id = q.id
     WHERE q.id = ?"
);
$_stmt->execute([$questionId]);
$question = $_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$question) {
    securitySendJson(['success' => false, 'error' => 'Pytanie nie znalezione'], 404);
}

// Atomically deduct XP
$deductStmt = $pdo->prepare("UPDATE users SET xp = xp - ? WHERE id = ? AND xp >= ?");
$deductStmt->execute([$xpCost, $userId, $xpCost]);

if ($deductStmt->rowCount() !== 1) {
    $_uStmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $_uStmt->execute([$userId]);
    $currentXp = (int)$_uStmt->fetchColumn();
    securitySendJson([
        'success' => false,
        'error'   => "Za mało XP. Potrzebujesz {$xpCost} XP, masz {$currentXp} XP.",
        'current_xp' => $currentXp,
    ]);
}

// Fetch updated balance
$_uStmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
$_uStmt->execute([$userId]);
$newXp = (int)$_uStmt->fetchColumn();
$_SESSION['xp'] = $newXp;

$response = ['success' => true, 'tier' => $tier, 'xp_cost' => $xpCost, 'new_xp' => $newXp];

if ($tier === 1) {
    // --- Tier 1: Conceptual clue ---
    if (!empty($question['hint_tier1'])) {
        $hint = $question['hint_tier1'];
    } else {
        // Auto-generate from question structure
        $questionWords = explode(' ', strip_tags($question['question_text']));
        $keyWords = array_filter($questionWords, fn($w) => mb_strlen($w) > 4);
        $keyWords = array_slice(array_values($keyWords), 0, 6);
        $hint = 'Wskazówka: zastanów się nad pojęciami związanymi z '
              . implode(', ', $keyWords) . '.';
    }
    $response['hint'] = $hint;

} elseif ($tier === 2) {
    // --- Tier 2: 50/50 elimination ---
    $correct = strtoupper(trim($question['correct_answer']));
    $allOptions = ['A', 'B', 'C', 'D'];
    $wrongOptions = array_values(array_filter($allOptions, fn($o) => $o !== $correct
        && !empty($question['option_' . strtolower($o)])));

    if (!empty($question['hint_tier2'])) {
        // Pre-defined elimination pair
        $elimStr = strtoupper($question['hint_tier2']);
        $eliminated = str_split($elimStr);
    } else {
        // Randomly pick 2 wrong answers to eliminate
        shuffle($wrongOptions);
        $eliminated = array_slice($wrongOptions, 0, 2);
    }

    $response['hint']       = 'Eliminuję dwie błędne odpowiedzi:';
    $response['eliminated'] = $eliminated;

} elseif ($tier === 3) {
    // --- Tier 3: Step-by-step explanation ---
    if (!empty($question['hint_tier3'])) {
        $explanation = $question['hint_tier3'];
    } elseif (!empty($question['explanation'])) {
        $explanation = $question['explanation'];
    } else {
        $correct     = strtoupper(trim($question['correct_answer']));
        $correctText = $question['option_' . strtolower($correct)] ?? '';
        $explanation = "Poprawna odpowiedź to {$correct}" . ($correctText ? ": {$correctText}" : '') . '.';
    }
    $response['hint'] = $explanation;
}

securitySendJson($response);
