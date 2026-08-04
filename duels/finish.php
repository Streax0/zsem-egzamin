<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSecureSession();
requireJsonLogin(false, [], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);
ensureDuelModeColumns($pdo);

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireJsonCsrfToken();

$duelId = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.challenger_id, d.opponent_id, d.category, d.question_count, d.question_ids, d.mode, d.preset, d.stake_xp, d.underdog_bonus, d.time_per_question_seconds, d.total_time_seconds, d.require_answer_confirmation, d.allow_early_finish, d.status, d.challenger_score_percent, d.opponent_score_percent, d.challenger_time_spent, d.opponent_time_spent, d.challenger_finished_at, d.opponent_finished_at, d.challenger_started_at, d.opponent_started_at, d.challenger_hidden_at, d.opponent_hidden_at, d.winner_id, d.revenge_parent_id, d.expires_at, d.created_at, uc.xp as challenger_xp, uo.xp as opponent_xp
        FROM duels d
        JOIN users uc ON uc.id = d.challenger_id
        JOIN users uo ON uo.id = d.opponent_id
        WHERE d.id = ? AND d.status = 'accepted' AND (d.challenger_id = ? OR d.opponent_id = ?)
    ");
    $stmt->execute([$duelId, $userId, $userId]);
    $duel = $stmt->fetch();

    if (!$duel) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Duel not found or not accepted']);
        exit;
    }

    $isChallenger = (int)$duel['challenger_id'] === (int)$userId;
    $alreadyFinished = $isChallenger ? !empty($duel['challenger_finished_at']) : !empty($duel['opponent_finished_at']);
    if ($alreadyFinished) {
        echo json_encode(['success' => true]);
        exit;
    }

    $requiredAnswers = max(1, (int)($duel['question_count'] ?? 1));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT question_id) as total, COALESCE(SUM(is_correct), 0) as correct FROM duel_answers WHERE duel_id = ? AND user_id = ?");
    $stmt->execute([$duelId, $userId]);
    $stats = $stmt->fetch();

    $earlyFinish = ($_POST['early_finish'] ?? '') === '1';
    $allowsEarlyFinish = !empty($duel['allow_early_finish']);
    if ((int)$stats['total'] < $requiredAnswers && !($earlyFinish && $allowsEarlyFinish)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Odpowiedz na wszystkie pytania przed zakończeniem pojedynku.']);
        exit;
    }

    $scorePercent = ((int)$stats['correct'] / $requiredAnswers) * 100;
    if (($duel['mode'] ?? 'classic') === 'underdog') {
        $challengerXp = (int)$duel['challenger_xp'];
        $opponentXp = (int)$duel['opponent_xp'];
        $currentIsUnderdog = ($challengerXp < $opponentXp && $isChallenger)
            || ($opponentXp < $challengerXp && !$isChallenger);
        if ($currentIsUnderdog) {
            $scorePercent = min(100, $scorePercent * (float)($duel['underdog_bonus'] ?? 1.15));
        }
    }

    $timeSpent = max(0, min(86400, (int)($_POST['time_spent'] ?? 0)));
    $pdo->beginTransaction();

    if ($isChallenger) {
        $stmt = $pdo->prepare("
            UPDATE duels
            SET challenger_score_percent = ?, challenger_time_spent = ?, challenger_finished_at = NOW()
            WHERE id = ? AND status = 'accepted' AND challenger_finished_at IS NULL
        ");
    } else {
        $stmt = $pdo->prepare("
            UPDATE duels
            SET opponent_score_percent = ?, opponent_time_spent = ?, opponent_finished_at = NOW()
            WHERE id = ? AND status = 'accepted' AND opponent_finished_at IS NULL
        ");
    }
    $stmt->execute([$scorePercent, $timeSpent, $duelId]);

    $stmt = $pdo->prepare("SELECT id, challenger_id, opponent_id, category, question_count, question_ids, mode, preset, stake_xp, underdog_bonus, time_per_question_seconds, total_time_seconds, require_answer_confirmation, allow_early_finish, status, challenger_score_percent, opponent_score_percent, challenger_time_spent, opponent_time_spent, challenger_finished_at, opponent_finished_at, challenger_started_at, opponent_started_at, challenger_hidden_at, opponent_hidden_at, winner_id, revenge_parent_id, expires_at, created_at FROM duels WHERE id = ?");
    $stmt->execute([$duelId]);
    $updated = $stmt->fetch();

    if ($updated && $updated['challenger_finished_at'] && $updated['opponent_finished_at']) {
        $winnerId = null;
        if ((float)$updated['challenger_score_percent'] > (float)$updated['opponent_score_percent']) {
            $winnerId = (int)$updated['challenger_id'];
        } elseif ((float)$updated['opponent_score_percent'] > (float)$updated['challenger_score_percent']) {
            $winnerId = (int)$updated['opponent_id'];
        } elseif ((int)$updated['challenger_time_spent'] < (int)$updated['opponent_time_spent']) {
            $winnerId = (int)$updated['challenger_id'];
        } elseif ((int)$updated['opponent_time_spent'] < (int)$updated['challenger_time_spent']) {
            $winnerId = (int)$updated['opponent_id'];
        }

        $stmt = $pdo->prepare("UPDATE duels SET status = 'finished', winner_id = ? WHERE id = ? AND status = 'accepted'");
        $stmt->execute([$winnerId, $duelId]);

        if ($stmt->rowCount() === 1) {
            if (($updated['mode'] ?? 'classic') === 'all_in' && $winnerId && (int)($updated['stake_xp'] ?? 0) > 0) {
                $stake = min(500, max(10, (int)$updated['stake_xp']));
                $loserId = ((int)$winnerId === (int)$updated['challenger_id']) ? (int)$updated['opponent_id'] : (int)$updated['challenger_id'];
                awardXp($pdo, $winnerId, $stake, 'duel', $duelId, 'Wygrana All-In Duel');
                awardXp($pdo, $loserId, -$stake, 'duel', $duelId, 'Przegrana All-In Duel');
                addNotification($pdo, $winnerId, 'duel_finished', "Wygrałeś All-In Duel i zgarniasz +$stake XP.", '/duels/results.php?id=' . $duelId);
                addNotification($pdo, $loserId, 'duel_finished', "Przegrałeś All-In Duel i tracisz $stake XP.", '/duels/results.php?id=' . $duelId);
            }

            $opponentId = $isChallenger ? (int)$duel['opponent_id'] : (int)$duel['challenger_id'];
            addNotification($pdo, $opponentId, 'duel_finished', 'Pojedynek został zakończony. Sprawdź wyniki.', '/duels/results.php?id=' . $duelId);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Duel finish error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
