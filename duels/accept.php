<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();
ensureDuelModeColumns($pdo);

$myId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../index.php');
}

$duelId = (int)($_POST['id'] ?? 0);

try {
    // Check if the duel belongs to this user and is pending
    $stmt = $pdo->prepare("SELECT * FROM duels WHERE id = ? AND opponent_id = ? AND status = 'pending' AND expires_at > NOW()");
    $stmt->execute([$duelId, $myId]);
    $duel = $stmt->fetch();

    if ($duel) {
        if (($duel['mode'] ?? 'classic') === 'all_in' && (int)($duel['stake_xp'] ?? 0) > 0) {
            if (!canUseAllInDuel($pdo, (int)$myId)) {
                setSessionMessage('error', 'Wykorzystałeś dzienny limit All-In Duel.');
                redirect('lobby.php?id=' . $duelId);
            }
            $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
            $stmt->execute([$myId]);
            if ((int)$stmt->fetchColumn() < (int)$duel['stake_xp']) {
                setSessionMessage('error', 'Nie masz wystarczająco XP, aby zaakceptować ten All-In Duel.');
                redirect('lobby.php?id=' . $duelId);
            }
            if (!consumeAllInDuelUse($pdo, (int)$myId)) {
                setSessionMessage('error', 'Wykorzystałeś dzienny limit All-In Duel.');
                redirect('lobby.php?id=' . $duelId);
            }
        }
        $stmt = $pdo->prepare("UPDATE duels SET status = 'accepted' WHERE id = ?");
        $stmt->execute([$duelId]);
        addNotification($pdo, $duel['challenger_id'], 'duel_accepted', "Użytkownik {$_SESSION['username']} zaakceptował Twoje wyzwanie!", '/duels/take.php?id=' . $duelId);
        setSessionMessage('success', 'Wyzwanie zaakceptowane! Rozpoczynasz pojedynek.');
        redirect('take.php?id=' . $duelId);
    } else {
        setSessionMessage('error', 'Nieprawidłowe lub wygasłe wyzwanie.');
        redirect('../index.php');
    }
} catch (PDOException $e) {
    error_log('Duel accept error: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się zaakceptować wyzwania. Spróbuj ponownie za chwilę.');
    redirect('../index.php');
}
