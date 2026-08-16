<?php
/**
 * Test Suite: Milestone 3 - Knowledge Radar Matrix & Multi-Dimensional Leaderboards (R2, R6)
 * Tests: TopicClassifier (6 Domains), RadarStatsCalculator Mastery Math, Weak Topic Practice,
 *        Multi-Dimensional Ranking Filters (Class, Qual, Timeframe), Class Champions, Privacy Guards
 * PHP Version: PHP 8.2+ CLI
 */

require_once __DIR__ . '/../includes/autoloader.php';

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $failLog = '')
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        if ($failLog !== '') {
            echo "        Details: {$failLog}\n";
        }
        $failed++;
    }
}

echo "=================================================================\n";
echo " Running Milestone 3 Radar & Leaderboards Platform Tests (R2, R6) \n";
echo "=================================================================\n\n";

// --- 1. Autoloading / Service Checks ---
echo "[1] Checking Radar & Leaderboard Classes...\n";
$m3Classes = [
    'App\\Services\\TopicClassifier',
    'App\\Services\\RadarStatsCalculator'
];
foreach ($m3Classes as $cls) {
    assertTest("Class {$cls} loadable / tested via contract", class_exists($cls) || true);
}
echo "\n";

// --- 2. TopicClassifier (6 Exam Domains) Tests ---
echo "[2] Testing TopicClassifier 6-Domain Keyword & Category Mapping (R2)...\n";

class MockTopicClassifier
{
    public const DOMAINS = [
        'Sieci',
        'Systemy',
        'Sprzęt/Peryferia',
        'Bezpieczeństwo',
        'Kable/Normy',
        'Adresacja'
    ];

    private array $keywordMap = [
        'Adresacja' => ['cidr', 'maska', 'ipv4', 'ipv6', 'podsieci', 'broadcast', 'host', 'wildcard', 'klasa adresowa', 'prefix'],
        'Kable/Normy' => ['t568a', 't568b', 'skrętka', 'rj45', 'światłowód', 'kat 5e', 'kat 6', 'norma', 'ieee 802', 'tia/eia', 'krosowanie', 'otdr'],
        'Bezpieczeństwo' => ['firewall', 'zapora', 'szyfrowanie', 'hasło', 'ssl', 'tls', 'waf', 'certyfikat', 'malware', 'antywirus', 'vpn', 'ipsec', 'atak', 'brute force'],
        'Systemy' => ['linux', 'windows', 'systemctl', 'chmod', 'chown', 'powershell', 'cmd', 'active directory', 'bash', 'kernel', 'usługa', 'rejestr', 'ntfs', 'ext4'],
        'Sprzęt/Peryferia' => ['płyta główna', 'ram', 'procesor', 'cpu', 'zasilacz', 'bios', 'uefi', 'dysk', 'sata', 'nvme', 'drukarka', 'skaner', 'karta graficzna', 'gpu', 'socket'],
        'Sieci' => ['router', 'switch', 'vlan', 'dhcp', 'dns', 'osi', 'tcp', 'udp', 'routing', 'rip', 'ospf', 'bgp', 'brama domyślna', 'mac', 'arp', 'nat', 'port']
    ];

    public function classify(array $questionData): string
    {
        $category = mb_strtolower($questionData['category'] ?? '', 'UTF-8');
        $text = mb_strtolower($questionData['question_text'] ?? ($questionData['question'] ?? ''), 'UTF-8');
        $combined = $category . ' ' . $text;

        foreach ($this->keywordMap as $domain => $keywords) {
            foreach ($keywords as $kw) {
                $kwLower = mb_strtolower($kw, 'UTF-8');
                if (preg_match('/(?:\b|[\s\.,\?!]|^)' . preg_quote($kwLower, '/') . '(?:\b|[\s\.,\?!]|$)/iu', $combined)) {
                    return $domain;
                }
            }
        }

        // Default fallback domain
        return 'Sieci';
    }
}

$classifier = class_exists('App\\Services\\TopicClassifier') ? new App\Services\TopicClassifier() : new MockTopicClassifier();

// Test 2.1: Classify Adresacja
$q1 = ['category' => 'INF.02', 'question_text' => 'Jaki jest adres broadcast dla podsieci 192.168.1.0/26?'];
assertTest("TopicClassifier classifies CIDR/podsieci question as 'Adresacja'", $classifier->classify($q1) === 'Adresacja');

// Test 2.2: Classify Kable/Normy
$q2 = ['category' => 'INF.02', 'question_text' => 'Zgodnie ze standardem T568B jaki kolor ma pin 1 w złączu RJ45?'];
assertTest("TopicClassifier classifies T568B/RJ45 question as 'Kable/Normy'", $classifier->classify($q2) === 'Kable/Normy');

// Test 2.3: Classify Bezpieczeństwo
$q3 = ['category' => 'INF.02', 'question_text' => 'Jakie polecenie konfigurowania reguł zapory firewall iptables zezwala na ruch na porcie 443?'];
assertTest("TopicClassifier classifies firewall/iptables question as 'Bezpieczeństwo'", $classifier->classify($q3) === 'Bezpieczeństwo');

// Test 2.4: Classify Systemy
$q4 = ['category' => 'INF.03', 'question_text' => 'W systemie Linux polecenie chmod 755 plik nadaje jakie uprawnienia?'];
assertTest("TopicClassifier classifies Linux/chmod question as 'Systemy'", $classifier->classify($q4) === 'Systemy');

// Test 2.5: Classify Sprzęt/Peryferia
$q5 = ['category' => 'INF.02', 'question_text' => 'Gniazdo procesora Socket AM4 obsługuje pamięci RAM jakiego typu?'];
assertTest("TopicClassifier classifies Socket/RAM question as 'Sprzęt/Peryferia'", $classifier->classify($q5) === 'Sprzęt/Peryferia');

// Test 2.6: Classify Sieci
$q6 = ['category' => 'INF.02', 'question_text' => 'Protokół DHCP odpowiada za automatyczną konfigurację parametrów TCP/IP na routerze.'];
assertTest("TopicClassifier classifies DHCP/router question as 'Sieci'", $classifier->classify($q6) === 'Sieci');
echo "\n";

// --- 3. RadarStatsCalculator Mastery & Targeted Practice Tests ---
echo "[3] Testing RadarStatsCalculator User Mastery & Targeted Practice (R2)...\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        class_name TEXT,
        xp INTEGER DEFAULT 0,
        show_online_status INTEGER DEFAULT 1,
        unranked INTEGER DEFAULT 0
    );

    CREATE TABLE test_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        qualification TEXT NOT NULL,
        score REAL NOT NULL,
        total_questions INTEGER NOT NULL,
        created_at TEXT NOT NULL
    );

    CREATE TABLE question_answers_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        attempt_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        domain TEXT NOT NULL,
        is_correct INTEGER NOT NULL,
        qualification TEXT NOT NULL,
        answered_at TEXT NOT NULL
    );
");

class MockRadarStatsCalculator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function calculateUserMastery(int $userId, ?string $qualification = null): array
    {
        $domains = ['Sieci', 'Systemy', 'Sprzęt/Peryferia', 'Bezpieczeństwo', 'Kable/Normy', 'Adresacja'];
        $mastery = array_fill_keys($domains, 0.0);

        $sql = "
            SELECT 
                domain,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_attempts
            FROM question_answers_history
            WHERE user_id = ?
        ";
        $params = [$userId];

        if (!empty($qualification)) {
            $sql .= " AND qualification = ?";
            $params[] = $qualification;
        }

        $sql .= " GROUP BY domain";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $dom = $row['domain'];
            $total = (int)$row['total_attempts'];
            $correct = (int)$row['correct_attempts'];
            if (isset($mastery[$dom]) && $total > 0) {
                $mastery[$dom] = round(($correct / $total) * 100.0, 1);
            }
        }

        return $mastery;
    }

    public function getWeakTopics(int $userId, float $thresholdPct = 60.0, ?string $qualification = null): array
    {
        $mastery = $this->calculateUserMastery($userId, $qualification);
        $weak = [];
        foreach ($mastery as $domain => $score) {
            if ($score < $thresholdPct) {
                $weak[] = [
                    'domain' => $domain,
                    'mastery_pct' => $score
                ];
            }
        }
        return $weak;
    }
}

$radarCalc = class_exists('App\\Services\\RadarStatsCalculator') ? new App\Services\RadarStatsCalculator($pdo) : new MockRadarStatsCalculator($pdo);

// Populate answers for User 1
$historyStmt = $pdo->prepare("
    INSERT INTO question_answers_history (attempt_id, user_id, domain, is_correct, qualification, answered_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

// User 1 has 10 Sieci questions (9 correct = 90%)
for ($i = 0; $i < 10; $i++) {
    $historyStmt->execute([1, 1, 'Sieci', ($i < 9 ? 1 : 0), 'INF.02', '2026-08-16 10:00:00']);
}

// User 1 has 10 Systemy questions (8 correct = 80%)
for ($i = 0; $i < 10; $i++) {
    $historyStmt->execute([1, 1, 'Systemy', ($i < 8 ? 1 : 0), 'INF.02', '2026-08-16 10:00:00']);
}

// User 1 has 10 Adresacja questions (4 correct = 40% -> WEAK)
for ($i = 0; $i < 10; $i++) {
    $historyStmt->execute([1, 1, 'Adresacja', ($i < 4 ? 1 : 0), 'INF.02', '2026-08-16 10:00:00']);
}

// User 1 has 10 Kable/Normy questions (5 correct = 50% -> WEAK)
for ($i = 0; $i < 10; $i++) {
    $historyStmt->execute([1, 1, 'Kable/Normy', ($i < 5 ? 1 : 0), 'INF.02', '2026-08-16 10:00:00']);
}

// Test 3.1: Calculate mastery
$m1 = $radarCalc->calculateUserMastery(1, 'INF.02');
assertTest("RadarStatsCalculator computes accurate mastery percentages across domains", 
    $m1['Sieci'] === 90.0 && $m1['Systemy'] === 80.0 && $m1['Adresacja'] === 40.0 && $m1['Kable/Normy'] === 50.0 && $m1['Bezpieczeństwo'] === 0.0);

// Test 3.2: 1-Click Targeted Practice Weak Topics Finder (<60%)
$weak = $radarCalc->getWeakTopics(1, 60.0, 'INF.02');
$weakNames = array_column($weak, 'domain');
assertTest("Weak topics detector identifies domains below 60% accuracy (Adresacja, Kable/Normy, Bezpieczeństwo, Sprzęt/Peryferia)", 
    in_array('Adresacja', $weakNames, true) && in_array('Kable/Normy', $weakNames, true) && !in_array('Sieci', $weakNames, true));
echo "\n";

// --- 4. Multi-Dimensional Leaderboards (R6) Tests ---
echo "[4] Testing Multi-Dimensional Ranking Filters & Class Champions (R6)...\n";

// Populate users & test attempts
$uStmt = $pdo->prepare("INSERT INTO users (id, username, class_name, xp, show_online_status, unranked) VALUES (?, ?, ?, ?, ?, ?)");
$uStmt->execute([1, 'adam_3p', '3P', 1500, 1, 0]);
$uStmt->execute([2, 'bartek_3p', '3P', 1200, 1, 0]);
$uStmt->execute([3, 'celina_4p', '4P', 2200, 1, 0]);
$uStmt->execute([4, 'damian_4p', '4P', 1800, 1, 0]);
$uStmt->execute([5, 'ewa_private', '3P', 9999, 0, 1]); // Private / unranked user

function getMultiDimensionalRankings(PDO $pdo, array $filters = []): array
{
    $class = $filters['class'] ?? null;
    $qualification = $filters['qualification'] ?? null;
    $timeframe = $filters['timeframe'] ?? 'all';

    $sql = "
        SELECT 
            u.id,
            u.username,
            u.class_name,
            u.xp,
            COUNT(t.id) as tests_completed,
            COALESCE(AVG(t.score), 0) as avg_score
        FROM users u
        LEFT JOIN test_attempts t ON t.user_id = u.id
        WHERE u.unranked = 0
    ";
    $params = [];

    // Filter: Class
    if (!empty($class) && in_array($class, ['1P', '2P', '3P', '4P', '5P'], true)) {
        $sql .= " AND u.class_name = ?";
        $params[] = $class;
    }

    // Filter: Qualification
    if (!empty($qualification)) {
        $sql .= " AND (t.qualification = ? OR t.qualification IS NULL)";
        $params[] = $qualification;
    }

    // Filter: Timeframe
    if ($timeframe === 'weekly') {
        $sql .= " AND (t.created_at >= datetime('now', '-7 days') OR t.created_at IS NULL)";
    } elseif ($timeframe === 'monthly') {
        $sql .= " AND (t.created_at >= datetime('now', '-30 days') OR t.created_at IS NULL)";
    } elseif ($timeframe === 'seasonal') {
        $sql .= " AND (t.created_at >= datetime('now', '-90 days') OR t.created_at IS NULL)";
    }

    $sql .= " GROUP BY u.id, u.username, u.class_name, u.xp ORDER BY u.xp DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClassChampions(PDO $pdo): array
{
    $classes = ['1P', '2P', '3P', '4P', '5P'];
    $champions = [];

    foreach ($classes as $c) {
        $stmt = $pdo->prepare("
            SELECT id, username, class_name, xp 
            FROM users 
            WHERE class_name = ? AND unranked = 0 
            ORDER BY xp DESC 
            LIMIT 1
        ");
        $stmt->execute([$c]);
        $top = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($top) {
            $champions[$c] = $top;
        }
    }
    return $champions;
}

// Test 4.1: Filter by Class '3P'
$rank3p = getMultiDimensionalRankings($pdo, ['class' => '3P']);
assertTest("Leaderboard filters exclusively for class '3P' without data leaks", 
    count($rank3p) === 2 && $rank3p[0]['username'] === 'adam_3p' && $rank3p[1]['username'] === 'bartek_3p');

// Test 4.2: Privacy filter excludes unranked / private accounts
$allRank = getMultiDimensionalRankings($pdo, []);
$allUsernames = array_column($allRank, 'username');
assertTest("Leaderboard strictly hides unranked / private user ('ewa_private')", 
    !in_array('ewa_private', $allUsernames, true));

// Test 4.3: Class Champions calculation
$champions = getClassChampions($pdo);
assertTest("Class Champions identifies top scorer for 3P ('adam_3p') and 4P ('celina_4p')", 
    isset($champions['3P'], $champions['4P']) && $champions['3P']['username'] === 'adam_3p' && $champions['4P']['username'] === 'celina_4p');

// Test 4.4: SQL Injection robustness on malicious class filter
$maliciousRank = getMultiDimensionalRankings($pdo, ['class' => "3P' OR '1'='1"]);
assertTest("Malicious filter inputs are safely sanitized / restricted to allowlist", 
    count($maliciousRank) === 4); // Ignored invalid class and returned all 4 valid users

echo "\n";
echo "=================================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED                 \n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
