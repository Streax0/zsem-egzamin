<?php
/**
 * Ranking Data API
 *
 * Returns filtered leaderboard data in JSON format.
 * Supports filters: class (1P-5P), qualification (INF.02/INF.03), timeframe (week/month/season/all)
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=60');

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$class         = trim((string)($_GET['class']         ?? ''));
$qualification = trim((string)($_GET['qualification'] ?? ''));
$timeframe     = trim((string)($_GET['timeframe']     ?? 'all'));

// Validate inputs
$validTimeframes = ['week', 'month', 'season', 'all'];
if (!in_array($timeframe, $validTimeframes, true)) {
    $timeframe = 'all';
}

// Validate class format: 1P, 2P, ..., 5P (or empty for all)
if ($class !== '' && !preg_match('/^[1-5][A-Z]{1,2}$/i', $class)) {
    $class = '';
}

// Validate qualification
$validQuals = ['INF.02', 'INF.03', ''];
if (!in_array($qualification, $validQuals, true)) {
    $qualification = '';
}

// Build date filter
$dateWhere = '';
switch ($timeframe) {
    case 'week':
        $dateWhere = "AND tr.test_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateWhere = "AND tr.test_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'season':
        $dateWhere = "AND tr.test_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    default:
        $dateWhere = '';
}

// Build user filter
$userWhere = "WHERE u.role IN ('user','wujek_luki') AND u.is_private = 0";
$params    = [];

if ($class !== '') {
    $userWhere .= " AND UPPER(u.class) = UPPER(?)";
    $params[] = $class;
}

// Qualification filter needs to join with test_results to filter by question category
// We'll use a subquery approach
$qualJoin = '';
if ($qualification !== '') {
    $qualJoin = "AND u.id IN (
        SELECT DISTINCT tr2.user_id FROM test_results tr2
        JOIN questions q ON q.category LIKE ?
        WHERE tr2.user_id = u.id
    )";
    $params[] = '%' . $qualification . '%';
}

// Main query: rank by XP for overall, or by test performance for time-filtered
if ($timeframe === 'all') {
    $sql = "
        SELECT u.id, u.username, u.xp, u.class, u.role,
               COUNT(DISTINCT tr.id) as test_count,
               ROUND(AVG(tr.score_percent), 1) as avg_score
        FROM users u
        LEFT JOIN test_results tr ON tr.user_id = u.id AND tr.exclude_from_ranking = 0
        {$userWhere}
        {$qualJoin}
        GROUP BY u.id
        ORDER BY u.xp DESC, test_count DESC
        LIMIT 50";
} else {
    $sql = "
        SELECT u.id, u.username, u.xp, u.class, u.role,
               COUNT(DISTINCT tr.id) as test_count,
               ROUND(AVG(tr.score_percent), 1) as avg_score,
               SUM(tr.correct_answers) as period_score
        FROM users u
        JOIN test_results tr ON tr.user_id = u.id AND tr.exclude_from_ranking = 0 {$dateWhere}
        {$userWhere}
        {$qualJoin}
        GROUP BY u.id
        HAVING test_count >= 1
        ORDER BY period_score DESC, avg_score DESC
        LIMIT 50";
}

$rows = [];
try {
    $_rStmt = $pdo->prepare($sql);
    $_rStmt->execute($params);
    $rows = $_rStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query failed']);
    exit;
}

// Find class champions (top user per class)
$classChampions = [];
foreach ($rows as $row) {
    $cls = strtoupper(trim($row['class'] ?? ''));
    if ($cls && !isset($classChampions[$cls])) {
        $classChampions[$cls] = $row['id'];
    }
}

// Build output
$leaderboard = [];
foreach ($rows as $idx => $row) {
    $isChampion = in_array($row['id'], $classChampions, true);
    $leaderboard[] = [
        'rank'        => $idx + 1,
        'username'    => $row['username'],
        'xp'          => (int)$row['xp'],
        'class'       => $row['class'] ?? '',
        'test_count'  => (int)$row['test_count'],
        'avg_score'   => (float)($row['avg_score'] ?? 0),
        'is_champion' => $isChampion,
        'badge'       => $isChampion ? 'Mistrz Klasy' : null,
    ];
}

echo json_encode([
    'success'     => true,
    'leaderboard' => $leaderboard,
    'filters'     => compact('class', 'qualification', 'timeframe'),
    'total'       => count($leaderboard),
], JSON_UNESCAPED_UNICODE);
