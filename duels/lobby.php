<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
if (isset($_GET['ajax_status'])) {
    requireJsonLogin(false, [], ['status' => 'unauthorized', 'expires_at' => null], ['status' => 'unauthorized', 'expires_at' => null]);
} else {
    requireLogin();
}
ensureDuelModeColumns($pdo);

$myId = $_SESSION['user_id'];
$duelId = (int)($_GET['id'] ?? 0);

if ($duelId <= 0) {
    setSessionMessage('error', 'Nieprawidłowe żądanie.');
    redirect('../index.php');
}

$stmt = $pdo->prepare(
    "SELECT d.*, u1.username as challenger_name, u2.username as opponent_name
     FROM duels d
     JOIN users u1 ON d.challenger_id = u1.id
     JOIN users u2 ON d.opponent_id = u2.id
     WHERE d.id = ? AND (d.challenger_id = ? OR d.opponent_id = ? )"
);
$stmt->execute([$duelId, $myId, $myId]);
$duel = $stmt->fetch();

if (!$duel) {
    setSessionMessage('error', 'Pojedynek nie istnieje.');
    redirect('../index.php');
}

if (isset($_GET['ajax_status'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $duel['status'],
        'expires_at' => $duel['expires_at'],
    ]);
    exit;
}

$isChallenger = ($duel['challenger_id'] == $myId);
$isOpponent = ($duel['opponent_id'] == $myId);
$otherName = $isChallenger ? $duel['opponent_name'] : $duel['challenger_name'];
$modeLabel = ['classic' => 'Classic', 'underdog' => '🔥 Underdog Mode', 'all_in' => '⚡ All-In Duel'][$duel['mode'] ?? 'classic'] ?? 'Classic';
$expiresAt = new DateTime($duel['expires_at']);
$remainingSeconds = max(0, $expiresAt->getTimestamp() - (new DateTime())->getTimestamp());

if ($duel['status'] === 'accepted') {
    redirect('take.php?id=' . $duelId);
}

if ($duel['status'] === 'finished') {
    redirect('results.php?id=' . $duelId);
}

$canRespond = $isOpponent && $duel['status'] === 'pending';
$showPolling = $duel['status'] === 'pending';
$statusText = '';
$statusBadge = '';

switch ($duel['status']) {
    case 'pending':
        $statusText = $isChallenger ? 'Czekasz na akceptację przeciwnika.' : 'Masz nowe wyzwanie do zaakceptowania.';
        $statusBadge = 'Oczekujące';
        break;
    case 'declined':
        $statusText = 'Przeciwnik odrzucił wyzwanie.';
        $statusBadge = 'Odrzucone';
        break;
    case 'expired':
        $statusText = 'Wyzwanie wygasło.';
        $statusBadge = 'Wygasłe';
        break;
    default:
        $statusText = 'Status pojedynku: ' . htmlspecialchars($duel['status']);
        $statusBadge = htmlspecialchars($duel['status']);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby pojedynku – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <style>
        .lobby-hero { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: #fff; border-radius: 26px; padding: 2rem; position: relative; overflow: hidden; }
        .lobby-hero::after { content: ''; position: absolute; right: -60px; top: -60px; width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,.1); }
        .lobby-status-badge { font-size: .85rem; letter-spacing: .08em; }
        .duel-card { border-radius: 24px; border: 1px solid rgba(148,163,184,.16); }
        .duel-card p { margin-bottom: 0.5rem; }
        .status-ring { width: 96px; height: 96px; border-radius: 999px; display: grid; place-items: center; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); }
        .status-ring strong { display: block; font-size: 1rem; }
        .status-ring span { font-size: .8rem; color: rgba(255,255,255,.85); }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main class="content-body">
                <div class="container-fluid p-0">
                    <div class="lobby-hero mb-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-start">
                            <div>
                                <span class="badge bg-white bg-opacity-15 rounded-pill lobby-status-badge mb-3">Lobby</span>
                                <h1 class="fw-900 mb-2">Pojedynek: <?= htmlspecialchars($duel['challenger_name']) ?> vs <?= htmlspecialchars($duel['opponent_name']) ?></h1>
                                <p class="lead opacity-85"><?= htmlspecialchars($statusText) ?></p>
                            </div>
                            <div class="status-ring text-center">
                                <strong><?= htmlspecialchars($statusBadge) ?></strong>
                                <span><?= htmlspecialchars($duel['question_count']) ?> pytań</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-7">
                            <div class="dashboard-panel duel-card p-4">
                                <h3 class="fw-bold mb-3">Szczegóły wyzwania</h3>
                                <p><strong>Przeciwnik:</strong> <?= htmlspecialchars($otherName) ?></p>
                                <p><strong>Tryb:</strong> <?= htmlspecialchars($modeLabel) ?></p>
                                <?php if (($duel['mode'] ?? 'classic') === 'all_in'): ?>
                                    <p><strong>Stawka:</strong> <?= (int)$duel['stake_xp'] ?> XP od każdego gracza</p>
                                <?php elseif (($duel['mode'] ?? 'classic') === 'underdog'): ?>
                                    <p><strong>Bonus:</strong> słabszy gracz dostaje mnożnik wyniku <?= number_format((float)$duel['underdog_bonus'], 2) ?>x</p>
                                <?php endif; ?>
                                <p><strong>Kategoria:</strong> <?= htmlspecialchars($duel['category']) ?></p>
                                <p><strong>Liczba pytań:</strong> <?= (int)$duel['question_count'] ?></p>
                                <p><strong>Ważne do:</strong> <?= date('d.m.Y H:i', strtotime($duel['expires_at'])) ?></p>
                                <?php if ($duel['status'] === 'pending'): ?>
                                    <p class="text-muted">Żądanie akceptacji nie zostało jeszcze zakończone. Ta strona odświeża status co kilka sekund.</p>
                                <?php elseif ($duel['status'] === 'declined'): ?>
                                    <div class="alert alert-danger">Wyzwanie zostało odrzucone. Możesz wysłać nowe wyzwanie lub wrócić do znajomych.</div>
                                <?php elseif ($duel['status'] === 'expired'): ?>
                                    <div class="alert alert-warning">To wyzwanie już wygasło. Wyślij nowe albo odśwież znajomych.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="dashboard-panel duel-card p-4">
                                <h3 class="fw-bold mb-3">Co dalej?</h3>
                                <?php if ($canRespond): ?>
                                    <p class="mb-4">Masz nowe wyzwanie od <?= htmlspecialchars($duel['challenger_name']) ?>. Możesz rozpocząć pojedynek lub go odrzucić.</p>
                                    <div class="d-flex flex-column gap-3">
                                        <form method="POST" action="accept.php" class="m-0">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="id" value="<?= $duelId ?>">
                                            <button type="submit" class="btn btn-danger rounded-pill py-3 fw-bold">Akceptuj wyzwanie</button>
                                        </form>
                                        <form method="POST" action="decline.php" class="m-0">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="id" value="<?= $duelId ?>">
                                            <button type="submit" class="btn btn-outline-secondary rounded-pill py-3">Odrzuć wyzwanie</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <p class="mb-4">Oczekuj na ruch drugiej strony. Otwórz inną kartę, a po akceptacji pojedynek uruchomi się automatycznie.</p>
                                    <div class="alert alert-info mb-0"><i class="bi bi-clock-history me-2"></i> Status będzie odświeżany automatycznie.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php if ($showPolling): ?>
    <script>
        const duelId = <?= $duelId ?>;
        const pollInterval = 4000;

        async function refreshDuelStatus() {
            try {
                const response = await fetch(`lobby.php?id=${duelId}&ajax_status=1`, { cache: 'no-store' });
                const data = await response.json();
                if (data.status === 'accepted') {
                    window.location.href = 'take.php?id=' + duelId;
                    return;
                }
                if (data.status === 'declined' || data.status === 'expired') {
                    window.location.reload();
                    return;
                }
            } catch (error) {
                console.error('Błąd odświeżania statusu pojedynku:', error);
            }
        }

        setInterval(refreshDuelStatus, pollInterval);
    </script>
    <?php endif; ?>
</body>
</html>
