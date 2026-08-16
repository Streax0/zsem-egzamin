<?php
/**
 * Knowledge Radar Data API
 *
 * Returns per-category accuracy statistics for the current user
 * in JSON format suitable for rendering a spider/radar chart.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=120');

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Canonical display order
$displayCategories = ['Sieci', 'Systemy', 'Sprzęt', 'Bezpieczeństwo', 'Kable/Normy', 'Adresacja'];

// Mode → category proxy mapping
$modeMap = [
    'exam'            => 'Sieci',
    'practice'        => 'Systemy',
    'single'          => 'Sprzęt',
    'exam_simulator'  => 'Bezpieczeństwo',
];

// Build aggregated per-category data
$stats = [];
foreach ($displayCategories as $cat) {
    $stats[$cat] = ['total' => 0, 'correct' => 0];
}

// Fetch test results grouped by mode as category proxy
$testRows = [];
try {
    $_tStmt = $pdo->prepare(
        "SELECT mode, AVG(score_percent) as avg_score, COUNT(*) as test_count
         FROM test_results
         WHERE user_id = ? AND total_questions >= 5
         GROUP BY mode"
    );
    $_tStmt->execute([$userId]);
    $testRows = $_tStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $testRows = [];
}

foreach ($testRows as $row) {
    $cat = $modeMap[$row['mode']] ?? null;
    if ($cat && isset($stats[$cat])) {
        $stats[$cat]['total']   += (int)$row['test_count'];
        $stats[$cat]['correct'] += (int)round($row['test_count'] * ($row['avg_score'] / 100));
    }
}

// Fetch overall average for fallback
$overallAvg = 65;
try {
    $_oStmt = $pdo->prepare(
        "SELECT AVG(score_percent) as avg FROM test_results WHERE user_id = ? AND total_questions >= 5"
    );
    $_oStmt->execute([$userId]);
    $overallRow = $_oStmt->fetch(PDO::FETCH_ASSOC);
    $overallAvg = (int)round((float)($overallRow['avg'] ?? 65));
} catch (Throwable $e) {}

// Total tests count
$totalTests = 0;
try {
    $_cStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM test_results WHERE user_id = ?");
    $_cStmt->execute([$userId]);
    $cntRow     = $_cStmt->fetch(PDO::FETCH_ASSOC);
    $totalTests = (int)($cntRow['cnt'] ?? 0);
} catch (Throwable $e) {}

// Build output values
$values      = [];
$weakAreas   = [];
$strongAreas = [];

foreach ($displayCategories as $cat) {
    $s   = $stats[$cat];
    $pct = $s['total'] > 0 ? round(($s['correct'] / $s['total']) * 100) : $overallAvg;

    $values[] = (int)$pct;
    if ($pct < 60)  $weakAreas[]   = $cat;
    if ($pct >= 80) $strongAreas[] = $cat;
}

echo json_encode([
    'success'      => true,
    'categories'   => $displayCategories,
    'values'       => $values,
    'weak_areas'   => $weakAreas,
    'strong_areas' => $strongAreas,
    'total_tests'  => $totalTests,
    'generated_at' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
