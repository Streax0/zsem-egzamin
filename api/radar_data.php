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

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    securitySendJson(['success' => false, 'error' => 'Unauthorized'], 401);
}

$userId = (int)$_SESSION['user_id'];

// Canonical display order
$displayCategories = ['Sieci', 'Systemy', 'Sprzęt', 'Bezpieczeństwo', 'Kable/Normy', 'Adresacja'];

// Build aggregated per-category data
$stats = [];
foreach ($displayCategories as $cat) {
    $stats[$cat] = ['total' => 0, 'correct' => 0];
}

/**
 * Maps technical CKE questions to the 6 radar domains based on content and category.
 */
function mapQuestionToRadarDomain(string $cat, string $text): string {
    $c = mb_strtolower($cat . ' ' . $text);
    if (preg_match('/(mask|cidr|podsiec|vlsm|ipv4|ipv6|\/24|\/28|brama domyślna|adres hosta)/iu', $c)) {
        return 'Adresacja';
    }
    if (preg_match('/(skrętk|światłow|kabel|złącz|rj-45|t568|ieee 802|kategori|ekranowan)/iu', $c)) {
        return 'Kable/Normy';
    }
    if (preg_match('/(szyfr|hasł|firewall|zapor|bezpiecz|certyfikat|tls|ssl|wpa|atak|malware|wirus)/iu', $c)) {
        return 'Bezpieczeństwo';
    }
    if (preg_match('/(linux|windows server|active directory|domena|gpo|usług|system|partycj|uprawnien|chmod|cron)/iu', $c)) {
        return 'Systemy';
    }
    if (preg_match('/(procesor|ram|dysk|płyt|zasilacz|karta graficzn|lutown|miernik|gniazd|socket|bios|uefi|chłodzen|drukark)/iu', $c)) {
        return 'Sprzęt';
    }
    return 'Sieci';
}

// Fetch user test answers joined with question details
try {
    $_ansStmt = $pdo->prepare("
        SELECT q.category, q.question_text, ta.is_correct
        FROM test_answers ta
        JOIN test_results tr ON tr.id = ta.result_id
        JOIN questions q ON q.id = ta.question_id
        WHERE tr.user_id = ?
        ORDER BY ta.id DESC
        LIMIT 500
    ");
    $_ansStmt->execute([$userId]);
    $rows = $_ansStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $domain = mapQuestionToRadarDomain((string)($r['category'] ?? ''), (string)($r['question_text'] ?? ''));
        if (isset($stats[$domain])) {
            $stats[$domain]['total']++;
            if ((int)($r['is_correct'] ?? 0) === 1) {
                $stats[$domain]['correct']++;
            }
        }
    }
} catch (Throwable $e) {
    // Fallback if table doesn't have answers yet
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

securitySendJson([
    'success'      => true,
    'categories'   => $displayCategories,
    'values'       => $values,
    'weak_areas'   => $weakAreas,
    'strong_areas' => $strongAreas,
    'total_tests'  => $totalTests,
    'generated_at' => date('Y-m-d H:i:s'),
]);
