<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
if (isset($_GET['poll'])) {
    requireJsonLogin(false, [], ['status' => 'unauthorized', 'finished' => false], ['status' => 'unauthorized', 'finished' => false]);
} else {
    requireLogin();
}
ensureDuelModeColumns($pdo);

$myId = $_SESSION['user_id'];
$duelId = (int)($_GET['id'] ?? 0);

// Load duel and participants
$stmt = $pdo->prepare("
    SELECT d.*, 
           u1.username as challenger_name, u1.xp as challenger_xp,
           u2.username as opponent_name, u2.xp as opponent_xp
    FROM duels d 
    JOIN users u1 ON d.challenger_id = u1.id 
    JOIN users u2 ON d.opponent_id = u2.id 
    WHERE d.id = ? AND (d.challenger_id = ? OR d.opponent_id = ?)
");
$stmt->execute([$duelId, $myId, $myId]);
$duel = $stmt->fetch();

if (!$duel) {
    setSessionMessage('error', 'Pojedynek nie istnieje.');
    redirect('../index.php');
}

if (isset($_GET['poll'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $duel['status'] ?? '',
        'finished' => ($duel['status'] ?? '') === 'finished',
        'challenger_finished' => !empty($duel['challenger_finished_at']),
        'opponent_finished' => !empty($duel['opponent_finished_at']),
    ]);
    exit;
}

$isChallenger = ($duel['challenger_id'] == $myId);
$meFinished = $isChallenger ? $duel['challenger_finished_at'] : $duel['opponent_finished_at'];
$opponentFinished = $isChallenger ? $duel['opponent_finished_at'] : $duel['challenger_finished_at'];
$modeLabel = ['classic' => 'Classic', 'underdog' => '🔥 Underdog Mode', 'all_in' => '⚡ All-In Duel'][$duel['mode'] ?? 'classic'] ?? 'Classic';

// Determine winner
$winner = null;
if ($duel['status'] === 'finished') {
    if (!empty($duel['winner_id']) && (int)$duel['winner_id'] === (int)$duel['challenger_id']) {
        $winner = 'challenger';
    } elseif (!empty($duel['winner_id']) && (int)$duel['winner_id'] === (int)$duel['opponent_id']) {
        $winner = 'opponent';
    } else {
        $winner = 'draw';
    }
}
$finishedTs = 0;
foreach ([$duel['challenger_finished_at'] ?? null, $duel['opponent_finished_at'] ?? null] as $finishedAt) {
    if (!empty($finishedAt)) $finishedTs = max($finishedTs, strtotime((string)$finishedAt) ?: 0);
}
$revengeAvailable = $duel['status'] === 'finished' && $finishedTs > 0 && (time() - $finishedTs) <= 600;
$showRevengePrompt = $revengeAvailable && $winner !== 'draw';

// Load my answers
$questionsById = [];
foreach (loadQuestions($pdo) as $question) {
    $questionsById[(int)($question['id'] ?? 0)] = $question;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM duel_answers
    WHERE duel_id = ? AND user_id = ?
    ORDER BY id ASC
");
$stmt->execute([$duelId, $myId]);
$myAnswers = $stmt->fetchAll();
foreach ($myAnswers as &$answerRow) {
    $question = $questionsById[(int)$answerRow['question_id']] ?? null;
    $answerRow['question_text'] = $question['question_text'] ?? 'Pytanie niedostępne';
    $answerRow['correct_answer'] = $question['correct_answer'] ?? '';
    $answerRow['options'] = [
        'A' => $question['option_a'] ?? '',
        'B' => $question['option_b'] ?? '',
        'C' => $question['option_c'] ?? '',
        'D' => $question['option_d'] ?? '',
    ];
    $answerRow['explanation'] = $question['explanation'] ?? '';
}
unset($answerRow);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyniki Pojedynku – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css', '..')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css', '..')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/duels.css', '..')); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid py-4">
                    
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            
                            <!-- Result Header -->
                            <div class="dashboard-panel duel-results-panel mb-4 text-center animate-in p-4">
                                <?php if ($duel['status'] !== 'finished'): ?>
                                    <div class="py-5">
                                        <i class="bi bi-hourglass-split display-3 text-primary d-block mb-3"></i>
                                        <h3 class="fw-800 mb-2">Wyniki są jeszcze ukryte</h3>
                                        <p class="text-muted mb-4">Pokażemy wynik, odpowiedzi i rewanż dopiero wtedy, gdy obie osoby zakończą pojedynek.</p>
                                        <a href="../social.php" class="btn btn-primary rounded-pill px-5 shadow-sm">Powrót do społeczności</a>
                                    </div>
                            </div>
                                <?php else: ?>

                                <div class="row g-4 align-items-stretch py-2">
                                    <div class="col-md-5">
                                        <div class="duel-player-card <?= ($winner === 'challenger') ? 'is-winner' : (($winner === 'opponent') ? 'is-loser' : 'is-draw') ?>">
                                            <div class="avatar-vs">
                                                <?= strtoupper(substr($duel['challenger_name'], 0, 1)) ?>
                                            </div>
                                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($duel['challenger_name']) ?></h4>
                                            <div class="display-5 fw-800 mb-2"><?= round($duel['challenger_score_percent']) ?>%</div>
                                            <?php if ($winner === 'challenger'): ?><span class="badge bg-white text-success rounded-pill px-3">WYGRANA</span><?php elseif ($winner === 'opponent'): ?><span class="badge bg-white text-danger rounded-pill px-3 opacity-75">PRZEGRANA</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-center justify-content-center">
                                        <div class="duel-vs-label">VS</div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="duel-player-card <?= ($winner === 'opponent') ? 'is-winner' : (($winner === 'challenger') ? 'is-loser' : 'is-draw') ?>">
                                            <div class="avatar-vs">
                                                <?= strtoupper(substr($duel['opponent_name'], 0, 1)) ?>
                                            </div>
                                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($duel['opponent_name']) ?></h4>
                                            <div class="display-5 fw-800 mb-2"><?= round($duel['opponent_score_percent']) ?>%</div>
                                            <?php if ($winner === 'opponent'): ?><span class="badge bg-white text-success rounded-pill px-3">WYGRANA</span><?php elseif ($winner === 'challenger'): ?><span class="badge bg-white text-danger rounded-pill px-3 opacity-75">PRZEGRANA</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                    <span class="duel-mode-pill">Tryb: <?= htmlspecialchars($modeLabel) ?></span>
                                    <?php if (($duel['mode'] ?? '') === 'all_in'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2">Stawka: <?= (int)$duel['stake_xp'] ?> XP</span>
                                    <?php elseif (($duel['mode'] ?? '') === 'underdog'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2">Bonus underdog: <?= number_format((float)$duel['underdog_bonus'], 2) ?>x</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($showRevengePrompt): ?>
                                <?php $otherId = $isChallenger ? (int)$duel['opponent_id'] : (int)$duel['challenger_id']; ?>
                                <div class="revenge-card p-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div>
                                        <h5 class="fw-800 mb-1"><i class="bi bi-lightning-charge-fill text-danger me-2"></i>Revenge Match?</h5>
                                        <p class="text-muted mb-0">Losowy rewanż aktywny. Możesz wysłać nowe wyzwanie z podbitą stawką.</p>
                                    </div>
                                    <a href="challenge.php?opponent=<?= $otherId ?>&revenge=<?= (int)$duelId ?>" class="btn btn-danger rounded-pill px-4 fw-bold">Rewanż x2</a>
                                </div>
                            <?php endif; ?>

                            <!-- Detailed Review -->
                            <div class="dashboard-panel animate-in" style="animation-delay: 0.1s;">
                                <h5 class="fw-bold mb-4"><i class="bi bi-list-check me-2 text-primary"></i>Twoje odpowiedzi</h5>
                                <div class="row g-3">
                                    <?php foreach ($myAnswers as $idx => $a): ?>
                                    <div class="col-12">
                                        <div class="p-3 border rounded-4 <?= $a['is_correct'] ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger' ?>">
                                            <div class="d-flex align-items-center justify-content-between gap-3">
                                            <div class="flex-grow-1">
                                                <div class="small fw-bold text-muted mb-1">Pytanie <?= $idx + 1 ?></div>
                                                <div class="fw-bold"><?= htmlspecialchars($a['question_text']) ?></div>
                                                <div class="small mt-1">
                                                    Twoja: <span class="<?= $a['is_correct'] ? 'text-success' : 'text-danger' ?> fw-bold"><?= $a['user_answer'] ?></span>
                                                    <?php if (!$a['is_correct']): ?>
                                                        | Poprawna: <span class="text-success fw-bold"><?= $a['correct_answer'] ?></span>
                                                    <?php endif; ?>
                                                    <span class="text-muted ms-3"><i class="bi bi-clock me-1"></i><?= $a['time_spent'] ?>s</span>
                                                </div>
                                            </div>
                                            <div class="ms-3 fs-2">
                                                <?php if ($a['is_correct']): ?>
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle-fill text-danger"></i>
                                                <?php endif; ?>
                                            </div>
                                            </div>
                                            <button class="btn btn-sm btn-light border mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#duelAnswer<?= (int)$a['id'] ?>">
                                                Pokaż wszystkie odpowiedzi
                                            </button>
                                            <div class="collapse mt-3" id="duelAnswer<?= (int)$a['id'] ?>">
                                                <div class="vstack gap-2">
                                                    <?php foreach ($a['options'] as $letter => $option): ?>
                                                        <?php
                                                        $classes = 'border rounded-3 p-2 bg-white';
                                                        if ($letter === $a['correct_answer']) $classes .= ' border-success';
                                                        if ($letter === $a['user_answer'] && !$a['is_correct']) $classes .= ' border-danger';
                                                        ?>
                                                        <div class="<?= $classes ?>">
                                                            <strong><?= htmlspecialchars($letter) ?>.</strong>
                                                            <?= htmlspecialchars($option) ?>
                                                            <?php if ($letter === $a['correct_answer']): ?><span class="badge bg-success ms-2">poprawna</span><?php endif; ?>
                                                            <?php if ($letter === $a['user_answer']): ?><span class="badge bg-primary ms-2">Twoja</span><?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (!empty($a['explanation'])): ?>
                                                        <div class="alert alert-info mb-0"><?= nl2br(htmlspecialchars($a['explanation'])) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <?php $otherId = $isChallenger ? (int)$duel['opponent_id'] : (int)$duel['challenger_id']; ?>
                                <?php if ($revengeAvailable): ?>
                                    <a href="challenge.php?opponent=<?= $otherId ?>&revenge=<?= (int)$duelId ?>" class="btn btn-outline-danger rounded-pill px-5 me-2">Rewanż</a>
                                <?php endif; ?>
                                <a href="../social.php" class="btn btn-primary rounded-pill px-5 shadow-sm">Powrót do społeczności</a>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php if ($duel['status'] !== 'finished'): ?>
    <script>
    (() => {
        const duelId = <?= (int)$duelId ?>;
        let tries = 0;
        const timer = setInterval(async () => {
            tries++;
            try {
                const res = await fetch(`results.php?id=${duelId}&poll=1`, { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.finished) {
                    clearInterval(timer);
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error polling duel results:', error);
            }
            if (tries > 300) clearInterval(timer);
        }, 3000);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
