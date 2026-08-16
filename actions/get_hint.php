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

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nie zalogowany']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda niedozwolona']);
    exit;
}

$userId     = (int)$_SESSION['user_id'];
$questionId = (int)($_POST['question_id'] ?? 0);
$tier       = (int)($_POST['tier'] ?? 0);

if ($questionId <= 0 || $tier < 1 || $tier > 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe parametry']);
    exit;
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
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Pytanie nie znalezione']);
    exit;
}

// Check user has enough XP
$_uStmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
$_uStmt->execute([$userId]);
$userRow = $_uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$currentXp = (int)($userRow['xp'] ?? 0);

if ($currentXp < $xpCost) {
    echo json_encode([
        'success' => false,
        'error'   => "Za mało XP. Potrzebujesz {$xpCost} XP, masz {$currentXp} XP.",
        'current_xp' => $currentXp,
    ]);
    exit;
}

$response = ['success' => true, 'tier' => $tier, 'xp_cost' => $xpCost];

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

// Deduct XP
$newXp = max(0, $currentXp - $xpCost);
$pdo->prepare("UPDATE users SET xp = ? WHERE id = ?")->execute([$newXp, $userId]);
$_SESSION['xp'] = $newXp;

$response['new_xp'] = $newXp;

echo json_encode($response, JSON_UNESCAPED_UNICODE);
