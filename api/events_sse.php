<?php
declare(strict_types=1);

// Prevent output buffering
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (string)($_SESSION['role'] ?? '');

// Release PHP session lock immediately to prevent blocking other tabs
session_write_close();

if ($userId <= 0) {
    echo "event: error\ndata: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
    flush();
    exit;
}

$channel = (string)($_GET['channel'] ?? 'exam');
$sessionId = (int)($_GET['session_id'] ?? 0);
$duelId = (int)($_GET['duel_id'] ?? 0);

$maxExecutionTime = 50; // seconds before graceful reconnect
$startTime = time();
$lastHash = '';
$lastPing = time();

// Send initial connection event
echo "event: connected\ndata: " . json_encode(['status' => 'connected', 'channel' => $channel, 'time' => time()]) . "\n\n";
flush();

while (time() - $startTime < $maxExecutionTime) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }

    // Periodic Heartbeat
    if (time() - $lastPing >= 15) {
        echo ": ping\n\n";
        flush();
        $lastPing = time();
    }

    try {
        if ($channel === 'exam' && $sessionId > 0) {
            // Verify teacher / session access
            $stmt = $pdo->prepare("
                SELECT es.id, es.status, es.started_at, es.finished_at 
                FROM exam_sessions es
                JOIN exams e ON es.exam_id = e.id
                WHERE es.id = ? AND (e.teacher_id = ? OR ? IN ('admin', 'dyrektor'))
            ");
            $stmt->execute([$sessionId, $userId, $userRole]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                // Fetch live participants
                $stmtP = $pdo->prepare("
                    SELECT id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, violation_count, last_activity
                    FROM exam_participants
                    WHERE session_id = ?
                    ORDER BY joined_at ASC
                ");
                $stmtP->execute([$sessionId]);
                $participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);

                // Fetch latest violations
                $stmtV = $pdo->prepare("
                    SELECT ev.id, ev.participant_id, ev.violation_type, ev.question_id, ev.details, ev.created_at, p.first_name, p.last_name
                    FROM exam_violations ev
                    JOIN exam_participants p ON ev.participant_id = p.id
                    WHERE ev.session_id = ?
                    ORDER BY ev.created_at DESC LIMIT 5
                ");
                $stmtV->execute([$sessionId]);
                $violations = $stmtV->fetchAll(PDO::FETCH_ASSOC);

                $payload = [
                    'session_status' => $session['status'],
                    'participants' => $participants,
                    'violations' => $violations,
                    'server_time' => date('H:i:s')
                ];

                $currentHash = md5(json_encode($payload));
                if ($currentHash !== $lastHash) {
                    $lastHash = $currentHash;
                    echo "event: participant_update\ndata: " . json_encode($payload) . "\n\n";
                    flush();
                }
            }
        } elseif ($channel === 'duel' && $duelId > 0) {
            // Fetch live duel progress for opponent
            $stmt = $pdo->prepare("
                SELECT id, challenger_id, opponent_id, status, 
                       challenger_score_percent, opponent_score_percent,
                       challenger_finished_at, opponent_finished_at
                FROM duels 
                WHERE id = ? AND (challenger_id = ? OR opponent_id = ?)
            ");
            $stmt->execute([$duelId, $userId, $userId]);
            $duel = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($duel) {
                $isChallenger = ((int)$duel['challenger_id'] === $userId);
                $opponentId = $isChallenger ? (int)$duel['opponent_id'] : (int)$duel['challenger_id'];

                // Count opponent answered questions
                $stmtAns = $pdo->prepare("SELECT COUNT(*) FROM duel_answers WHERE duel_id = ? AND user_id = ?");
                $stmtAns->execute([$duelId, $opponentId]);
                $opponentAnswered = (int)$stmtAns->fetchColumn();

                $payload = [
                    'status' => $duel['status'],
                    'opponent_answered' => $opponentAnswered,
                    'opponent_finished' => !empty($isChallenger ? $duel['opponent_finished_at'] : $duel['challenger_finished_at']),
                    'my_finished' => !empty($isChallenger ? $duel['challenger_finished_at'] : $duel['opponent_finished_at']),
                    'server_time' => time()
                ];

                $currentHash = md5(json_encode($payload));
                if ($currentHash !== $lastHash) {
                    $lastHash = $currentHash;
                    echo "event: duel_update\ndata: " . json_encode($payload) . "\n\n";
                    flush();
                }
            }
        }
    } catch (Throwable $e) {
        error_log('SSE error: ' . $e->getMessage());
    }

    sleep(1);
}

// Graceful close signaling client to reconnect
echo "event: reconnect\ndata: {\"reconnect\": true}\n\n";
flush();
