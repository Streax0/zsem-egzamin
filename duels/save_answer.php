<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireJsonLogin(false, [], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireJsonCsrfToken();

    $duelId = (int)($_POST['duel_id'] ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);
    $answer = strtoupper(trim($_POST['answer'] ?? ''));
    $timeSpent = (int)($_POST['time_spent'] ?? 0);
    $userId = $_SESSION['user_id'];

    if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
        securitySendJson(['success' => false, 'message' => 'Invalid answer'], 400);
    }

    // Verify duel membership
    $stmt = $pdo->prepare("
        SELECT id, challenger_id, opponent_id, challenger_finished_at, opponent_finished_at, question_ids
        FROM duels
        WHERE id = ? AND status = 'accepted' AND (challenger_id = ? OR opponent_id = ?)
    ");
    $stmt->execute([$duelId, $userId, $userId]);
    $duel = $stmt->fetch();
    if (!$duel) {
        securitySendJson(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $isChallenger = (int)$duel['challenger_id'] === (int)$userId;
    $alreadyFinished = $isChallenger ? !empty($duel['challenger_finished_at']) : !empty($duel['opponent_finished_at']);
    if ($alreadyFinished) {
        securitySendJson(['success' => false, 'message' => 'Duel already finished'], 409);
    }

    $sessionQuestionIds = $_SESSION['duel_questions_' . $duelId] ?? [];
    if (!is_array($sessionQuestionIds) || empty($sessionQuestionIds)) {
        $sessionQuestionIds = !empty($duel['question_ids']) ? json_decode($duel['question_ids'], true) : [];
    }

    if (!in_array($questionId, array_map('intval', $sessionQuestionIds), true)) {
        securitySendJson(['success' => false, 'message' => 'Question is not assigned to this duel'], 400);
    }

    $question = getQuestionsByIds($pdo, [$questionId])[0] ?? null;
    $correct = strtoupper((string)($question['correct_answer'] ?? ''));
    if ($correct === '') {
        securitySendJson(['success' => false, 'message' => 'Question not found'], 404);
    }

    $isCorrect = ($answer === $correct) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO duel_answers (duel_id, user_id, question_id, user_answer, is_correct, time_spent)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id = id
        ");
        $stmt->execute([$duelId, $userId, $questionId, $answer, $isCorrect, max(0, min(86400, $timeSpent))]);

        $stmt = $pdo->prepare("SELECT user_answer FROM duel_answers WHERE duel_id = ? AND user_id = ? AND question_id = ?");
        $stmt->execute([$duelId, $userId, $questionId]);
        $existingAnswer = $stmt->fetchColumn();
        if ($existingAnswer === false || $existingAnswer !== $answer) {
            securitySendJson(['success' => false, 'message' => 'Answer already saved'], 409);
        }

        securitySendJson(['success' => true]);
    } catch (PDOException $e) {
        error_log('Duel answer save error: ' . $e->getMessage());
        securitySendJson(['success' => false, 'message' => 'Database error'], 500);
    }
} else {
    securitySendJson(['success' => false, 'message' => 'Method not allowed'], 405);
}
