<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');

requireJsonLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda.']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'notifications')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Błąd CSRF.']);
    exit;
}

$myId = (int)$_SESSION['user_id'];
$duelId = (int)($_POST['duel_id'] ?? 0);
$action = strtolower(trim((string)($_POST['action'] ?? '')));

if ($duelId <= 0 || !in_array($action, ['accept', 'decline'], true)) {
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowe żądanie.']);
    exit;
}

ensureDuelModeColumns($pdo);

try {
    if ($action === 'decline') {
        $stmt = $pdo->prepare("UPDATE duels SET status = 'declined' WHERE id = ? AND opponent_id = ? AND status = 'pending'");
        $stmt->execute([$duelId, $myId]);
        if ($stmt->rowCount() < 1) {
            echo json_encode(['success' => false, 'message' => 'Nie można odrzucić tego wyzwania.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Wyzwanie odrzucone.', 'redirect' => publicUrl('index.php')]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM duels WHERE id = ? AND opponent_id = ? AND status = 'pending' AND expires_at > NOW()");
    $stmt->execute([$duelId, $myId]);
    $duel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$duel) {
        echo json_encode(['success' => false, 'message' => 'Nieprawidłowe lub wygasłe wyzwanie.']);
        exit;
    }

    if (($duel['mode'] ?? 'classic') === 'all_in' && (int)($duel['stake_xp'] ?? 0) > 0) {
        if (!canUseAllInDuel($pdo, $myId)) {
            echo json_encode(['success' => false, 'message' => 'Wykorzystałeś dzienny limit All-In Duel.', 'redirect' => publicUrl('duels/lobby.php?id=' . $duelId)]);
            exit;
        }
        $xpStmt = $pdo->prepare('SELECT xp FROM users WHERE id = ?');
        $xpStmt->execute([$myId]);
        if ((int)$xpStmt->fetchColumn() < (int)$duel['stake_xp']) {
            echo json_encode(['success' => false, 'message' => 'Nie masz wystarczająco XP na tę stawkę.', 'redirect' => publicUrl('duels/lobby.php?id=' . $duelId)]);
            exit;
        }
        if (!consumeAllInDuelUse($pdo, $myId)) {
            echo json_encode(['success' => false, 'message' => 'Wykorzystałeś dzienny limit All-In Duel.', 'redirect' => publicUrl('duels/lobby.php?id=' . $duelId)]);
            exit;
        }
    }

    $pdo->prepare("UPDATE duels SET status = 'accepted' WHERE id = ?")->execute([$duelId]);
    addNotification(
        $pdo,
        (int)$duel['challenger_id'],
        'duel_accepted',
        'Użytkownik ' . ($_SESSION['username'] ?? 'Gracz') . ' zaakceptował Twoje wyzwanie!',
        '/duels/take.php?id=' . $duelId
    );

    echo json_encode([
        'success' => true,
        'message' => 'Wyzwanie zaakceptowane!',
        'redirect' => publicUrl('duels/take.php?id=' . $duelId),
    ]);
} catch (PDOException $e) {
    error_log('duel_respond error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Nie udało się przetworzyć wyzwania.']);
}
