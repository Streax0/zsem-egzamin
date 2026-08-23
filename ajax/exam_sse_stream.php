<?php
declare(strict_types=1);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

$examSessionId = (int)($_GET['session_id'] ?? 0);
if ($examSessionId <= 0) {
    echo "event: error\ndata: " . json_encode(['message' => 'Nieprawidłowe ID sesji egzaminacyjnej.']) . "\n\n";
    @ob_flush();
    @flush();
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser || !in_array($currentUser['role'] ?? 'user', ['admin', 'dyrektor', 'teacher'], true)) {
    echo "event: error\ndata: " . json_encode(['message' => 'Brak uprawnień do monitoringu sesji.']) . "\n\n";
    @ob_flush();
    @flush();
    exit;
}

// Stream 5 ticks or terminate safely
for ($i = 0; $i < 5; $i++) {
    try {
        $stmt = $pdo->prepare("
            SELECT ep.id, ep.user_id, ep.status, ep.current_question_index, ep.score_percent, ep.violations_count, ep.last_ping,
                   u.username, u.first_name, u.last_name
            FROM exam_participants ep
            JOIN users u ON u.id = ep.user_id
            WHERE ep.session_id = :sid
            ORDER BY ep.id ASC
        ");
        $stmt->execute(['sid' => $examSessionId]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $payload = [
            'timestamp' => time(),
            'session_id' => $examSessionId,
            'participant_count' => count($participants),
            'participants' => array_map(function($p) {
                $isOnline = (time() - strtotime($p['last_ping'] ?? '2000-01-01')) <= 15;
                return [
                    'id' => (int)$p['id'],
                    'user_id' => (int)$p['user_id'],
                    'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: $p['username'],
                    'status' => $p['status'],
                    'progress_idx' => (int)$p['current_question_index'],
                    'score_percent' => (float)$p['score_percent'],
                    'violations' => (int)$p['violations_count'],
                    'is_online' => $isOnline,
                ];
            }, $participants),
        ];

        echo "event: update\ndata: " . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        @flush();
    } catch (Throwable $e) {
        echo "event: error\ndata: " . json_encode(['message' => 'Błąd odczytu bazy danych: ' . $e->getMessage()]) . "\n\n";
        @ob_flush();
        @flush();
        break;
    }

    if (connection_aborted()) {
        break;
    }
    sleep(2);
}
