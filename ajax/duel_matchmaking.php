<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'message' => 'Wymagane zapytanie POST.'], 405);
}

$myId = (int)($_SESSION['user_id'] ?? 0);
if ($myId <= 0) {
    securitySendJson(['success' => false, 'message' => 'Musisz być zalogowany, aby szukać pojedynku.'], 401);
}
$currentUser = getUserById($myId);
if (!$currentUser) {
    securitySendJson(['success' => false, 'message' => 'Musisz być zalogowany, aby szukać pojedynku.'], 401);
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$csrfToken = (string)($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '')));
if (!validateCsrfToken($csrfToken) && !securityValidateRequestCsrf()) {
    securitySendJson(['success' => false, 'message' => 'Nieprawidłowy token CSRF.'], 403);
}

$action = (string)($data['action'] ?? 'search');
$category = trim((string)($data['category'] ?? 'INF.02'));
$mode = in_array($data['mode'] ?? '', ['classic', 'underdog', 'all_in'], true) ? $data['mode'] : 'classic';

if ($action !== 'cancel') {
    $recentDuelStmt = $pdo->prepare("
        SELECT d.id, d.challenger_id, d.opponent_id, u1.username AS challenger_name, u2.username AS opponent_name
        FROM duels d
        JOIN users u1 ON d.challenger_id = u1.id
        JOIN users u2 ON d.opponent_id = u2.id
        WHERE (d.challenger_id = ? OR d.opponent_id = ?)
          AND d.status = 'accepted'
          AND d.created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
          AND ((d.challenger_id = ? AND d.challenger_finished_at IS NULL) OR (d.opponent_id = ? AND d.opponent_finished_at IS NULL))
        ORDER BY d.id DESC LIMIT 1
    ");
    $recentDuelStmt->execute([$myId, $myId, $myId, $myId]);
    $recentDuel = $recentDuelStmt->fetch(PDO::FETCH_ASSOC);
    if ($recentDuel) {
        $isChallenger = ((int)$recentDuel['challenger_id'] === $myId);
        $oppId = $isChallenger ? (int)$recentDuel['opponent_id'] : (int)$recentDuel['challenger_id'];
        $oppName = $isChallenger ? (string)$recentDuel['opponent_name'] : (string)$recentDuel['challenger_name'];
        securitySendJson([
            'success' => true,
            'status' => 'matched',
            'duel_id' => (int)$recentDuel['id'],
            'opponent_id' => $oppId,
            'opponent_name' => $oppName,
            'redirect_url' => 'duels/take.php?id=' . (int)$recentDuel['id'],
        ], 200);
    }
}

$queueFile = __DIR__ . '/../data/duel_matchmaking_queue.json';
$queueDir = dirname($queueFile);
if (!is_dir($queueDir)) {
    @mkdir($queueDir, 0775, true);
}

$fp = @fopen($queueFile . '.lock', 'c+');
if ($fp) {
    @flock($fp, LOCK_EX);
}

$queue = [];
if (file_exists($queueFile)) {
    $content = @file_get_contents($queueFile);
    if ($content) {
        $parsed = json_decode($content, true);
        if (is_array($parsed)) {
            $queue = $parsed;
        }
    }
}

$now = time();
$activeQueue = [];
foreach ($queue as $entry) {
    if (($now - (int)($entry['timestamp'] ?? 0)) < 60 && (int)($entry['user_id'] ?? 0) !== $myId) {
        $activeQueue[] = $entry;
    }
}

if ($action === 'cancel') {
    @file_put_contents($queueFile, json_encode($activeQueue, JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($fp) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
    securitySendJson(['success' => true, 'status' => 'cancelled', 'message' => 'Wyszukiwanie anulowane.'], 200);
}

$matchedOpponent = null;
$remainingQueue = [];

foreach ($activeQueue as $candidate) {
    if ($matchedOpponent === null && ($candidate['category'] === $category || $category === 'all')) {
        $matchedOpponent = $candidate;
    } else {
        $remainingQueue[] = $candidate;
    }
}

if ($matchedOpponent !== null) {
    $oppId = (int)$matchedOpponent['user_id'];
    $oppName = (string)$matchedOpponent['username'];

    $qStmt = $pdo->prepare("SELECT id FROM questions WHERE category = :cat OR :catAll = 'all' ORDER BY RAND() LIMIT 10");
    $qStmt->execute(['cat' => $category, 'catAll' => $category]);
    $qIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($qIds)) {
        $qStmt = $pdo->query("SELECT id FROM questions ORDER BY RAND() LIMIT 10");
        $qIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $qIdsJson = json_encode(array_values(array_map('intval', $qIds)));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $insStmt = $pdo->prepare("
        INSERT INTO duels (challenger_id, opponent_id, category, question_count, question_ids, mode, status, expires_at, created_at)
        VALUES (:cid, :oid, :cat, 10, :qids, :mode, 'accepted', :exp, NOW())
    ");
    $insStmt->execute([
        'cid' => $oppId,
        'oid' => $myId,
        'cat' => $category,
        'qids' => $qIdsJson,
        'mode' => $mode,
        'exp' => $expiresAt,
    ]);
    $duelId = (int)$pdo->lastInsertId();

    @file_put_contents($queueFile, json_encode($remainingQueue, JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($fp) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }

    securitySendJson([
        'success' => true,
        'status' => 'matched',
        'duel_id' => $duelId,
        'opponent_id' => $oppId,
        'opponent_name' => $oppName,
        'redirect_url' => 'duels/take.php?id=' . $duelId,
    ], 200);
}

$remainingQueue[] = [
    'user_id' => $myId,
    'username' => $currentUser['username'] ?? 'Gracz',
    'category' => $category,
    'mode' => $mode,
    'timestamp' => $now,
];

@file_put_contents($queueFile, json_encode($remainingQueue, JSON_UNESCAPED_UNICODE), LOCK_EX);
if ($fp) {
    @flock($fp, LOCK_UN);
    @fclose($fp);
}

securitySendJson([
    'success' => true,
    'status' => 'waiting',
    'message' => 'Oczekiwanie na przeciwnika w kolejce matchmakingu...',
], 200);
