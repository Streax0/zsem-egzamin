<?php
/**
 * Subnetting Speed Challenge — Validate answers server-side
 *
 * POST {network_ip, cidr, answers: {network, broadcast, first_host, last_host, host_count}}
 * Returns {correct, correct_answers, xp_earned, streak}
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

requireJsonCsrfToken();

$userId = (int)$_SESSION['user_id'];
if (function_exists('securityConsumeRateLimit') && !securityConsumeRateLimit('subnetting:submit:' . $userId, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Zbyt wiele prób. Odczekaj chwilę.']);
    exit;
}
$networkIp  = trim((string)($_POST['network_ip'] ?? ''));
$cidr       = (int)($_POST['cidr'] ?? 0);
$difficulty = in_array($_POST['difficulty'] ?? '', ['easy','medium','hard','expert'])
    ? $_POST['difficulty'] : 'medium';

// Answers from user
$answers = [
    'network'    => trim((string)($_POST['answer_network']    ?? '')),
    'broadcast'  => trim((string)($_POST['answer_broadcast']  ?? '')),
    'first_host' => trim((string)($_POST['answer_first_host'] ?? '')),
    'last_host'  => trim((string)($_POST['answer_last_host']  ?? '')),
    'host_count' => trim((string)($_POST['answer_host_count'] ?? '')),
];

if (!filter_var($networkIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $cidr < 1 || $cidr > 32) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe parametry sieci']);
    exit;
}

// Calculate correct answers
$ipLong   = ip2long($networkIp);
$mask     = $cidr > 0 ? ~((1 << (32 - $cidr)) - 1) : 0;
$maskLong = $mask & 0xFFFFFFFF;

$networkLong   = $ipLong & $maskLong;
$broadcastLong = $networkLong | (~$maskLong & 0xFFFFFFFF);
$firstHostLong = $networkLong + 1;
$lastHostLong  = $broadcastLong - 1;
$hostCount     = max(0, $broadcastLong - $networkLong - 1);

// Special cases for /31 and /32
if ($cidr === 32) {
    $hostCount     = 1;
    $firstHostLong = $networkLong;
    $lastHostLong  = $networkLong;
} elseif ($cidr === 31) {
    $hostCount     = 2;
    $firstHostLong = $networkLong;
    $lastHostLong  = $broadcastLong;
}

$correctAnswers = [
    'network'    => long2ip($networkLong),
    'broadcast'  => long2ip($broadcastLong),
    'first_host' => long2ip($firstHostLong),
    'last_host'  => long2ip($lastHostLong),
    'host_count' => (string)$hostCount,
    'subnet_mask' => long2ip($maskLong),
];

// Evaluate user answers
$results  = [];
$allRight = true;
foreach ($correctAnswers as $field => $correct) {
    if ($field === 'subnet_mask') continue;
    $userAns = $answers[$field] ?? '';
    $ok = strtolower(trim($userAns)) === strtolower($correct);
    $results[$field] = $ok;
    if (!$ok) $allRight = false;
}

// XP per difficulty
$xpMap = ['easy' => 5, 'medium' => 10, 'hard' => 20, 'expert' => 35];
$xpEarned = 0;

if ($allRight) {
    $rawXp = $xpMap[$difficulty] ?? 10;
    
    // Check daily XP cap (max 250 XP/day for subnetting game)
    $dailyCap = 250;
    $todayXp = 0;
    try {
        $_capStmt = $pdo->prepare("SELECT COALESCE(SUM(score), 0) FROM subnetting_scores WHERE user_id = ? AND achieved_at >= CURDATE()");
        $_capStmt->execute([$userId]);
        $todayXp = (int)$_capStmt->fetchColumn();
    } catch (Throwable $e) {}

    $xpEarned = max(0, min($rawXp, $dailyCap - $todayXp));

    // Update user XP if under cap
    if ($xpEarned > 0) {
        $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpEarned, $userId]);
    }

    // Record score
    try {
        $pdo->prepare("INSERT INTO subnetting_scores (user_id, score, difficulty) VALUES (?,?,?)")
            ->execute([$userId, $xpEarned, $difficulty]);
    } catch (Throwable $e) {}
}

// Fetch top 10 scores for the leaderboard
$topScores = [];
try {
    $_lbStmt = $pdo->prepare(
        "SELECT u.username, s.score, s.difficulty, s.achieved_at
         FROM subnetting_scores s
         JOIN users u ON u.id = s.user_id
         ORDER BY s.score DESC, s.achieved_at ASC
         LIMIT 10"
    );
    $_lbStmt->execute();
    $topScores = $_lbStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $topScores = [];
}

echo json_encode([
    'success'         => true,
    'correct'         => $allRight,
    'field_results'   => $results,
    'correct_answers' => $correctAnswers,
    'xp_earned'       => $xpEarned,
    'top_scores'      => $topScores,
], JSON_UNESCAPED_UNICODE);
