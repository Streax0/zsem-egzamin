<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();
ensureDuelModeColumns($pdo);

$myId = $_SESSION['user_id'];
$opponentId = (int)($_GET['opponent'] ?? 0);
$revengeParentId = (int)($_GET['revenge'] ?? 0);
$allInLimit = getAllInDailyLimit($pdo);
$allInUsed = getAllInUsage($pdo, (int)$myId);

function duelRevengeIsAvailable(PDO $pdo, int $parentId, int $userId, int $opponentId): bool {
    if ($parentId <= 0) return false;
    $stmt = $pdo->prepare("
        SELECT challenger_id, opponent_id, status, challenger_finished_at, opponent_finished_at
        FROM duels
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$parentId]);
    $duel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$duel || ($duel['status'] ?? '') !== 'finished') return false;
    $participants = [(int)$duel['challenger_id'], (int)$duel['opponent_id']];
    if (!in_array($userId, $participants, true) || !in_array($opponentId, $participants, true) || $userId === $opponentId) {
        return false;
    }
    $finishedTs = 0;
    foreach ([$duel['challenger_finished_at'] ?? null, $duel['opponent_finished_at'] ?? null] as $finishedAt) {
        if (!empty($finishedAt)) $finishedTs = max($finishedTs, strtotime((string)$finishedAt) ?: 0);
    }
    return $finishedTs > 0 && (time() - $finishedTs) <= 600;
}

if ($opponentId <= 0 || $opponentId === $myId) {
    setSessionMessage('error', 'Nieprawidłowy przeciwnik.');
    redirect('../social.php');
}

// Check if they are friends
$status = getFriendshipStatus($pdo, $myId, $opponentId);
if ($status !== 'friends') {
    setSessionMessage('error', 'Możesz wyzywać na pojedynek tylko swoich znajomych.');
    redirect('../social.php');
}

// Load opponent info
$stmt = $pdo->prepare("SELECT username, xp FROM users WHERE id = ?");
$stmt->execute([$opponentId]);
$opponent = $stmt->fetch();

if (!$opponent) {
    setSessionMessage('error', 'Przeciwnik nie istnieje.');
    redirect('../social.php');
}

if ($revengeParentId > 0 && !duelRevengeIsAvailable($pdo, $revengeParentId, (int)$myId, $opponentId)) {
    setSessionMessage('error', 'Rewanż jest dostępny tylko przez 10 minut po zakończeniu pojedynku przez obie osoby.');
    redirect('challenge.php?opponent=' . $opponentId);
}

// Load categories
$allQuestions = loadQuestions($pdo, false);
$categories = array_unique(array_column($allQuestions, 'category'));
sort($categories);

// Handle challenge submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Błąd CSRF.');
    } else {
        $category = $_POST['category'] ?? 'Ogólne';
        $duelMode = $_POST['duel_mode'] ?? 'classic';
        if (!in_array($duelMode, ['classic', 'underdog', 'all_in'], true)) $duelMode = 'classic';
        $preset = $_POST['duel_preset'] ?? 'classic';
        $presetMap = [
            'classic' => ['count' => 10, 'per' => null, 'total' => null, 'confirm' => 0],
            'sprint' => ['count' => 5, 'per' => 25, 'total' => 180, 'confirm' => 0],
            'exam' => ['count' => 20, 'per' => 60, 'total' => 1200, 'confirm' => 1],
            'precision' => ['count' => 10, 'per' => null, 'total' => 900, 'confirm' => 1],
        ];
        if (!isset($presetMap[$preset])) $preset = 'classic';
        $stakeXp = $duelMode === 'all_in' ? max(10, min(500, (int)($_POST['stake_xp'] ?? 50))) : 0;
        $underdogBonus = $duelMode === 'underdog' ? 1.15 : 1.00;
        $parentId = max(0, (int)($_POST['revenge_parent_id'] ?? 0));
        if ($parentId > 0 && !duelRevengeIsAvailable($pdo, $parentId, (int)$myId, $opponentId)) {
            setSessionMessage('error', 'Rewanż wygasł. Można go wysłać tylko przez 10 minut po zakończeniu pojedynku.');
            redirect('challenge.php?opponent=' . $opponentId);
        }
        $count = (int)($_POST['question_count'] ?? $presetMap[$preset]['count']);
        $count = max(5, min(20, $count));
        $postedPerQuestion = $_POST['time_per_question_seconds'] ?? '';
        $postedTotalTime = $_POST['total_time_seconds'] ?? '';
        $timePerQuestion = $postedPerQuestion !== '' ? max(10, min(300, (int)$postedPerQuestion)) : $presetMap[$preset]['per'];
        $totalTime = $postedTotalTime !== '' ? max(60, min(3600, (int)$postedTotalTime)) : $presetMap[$preset]['total'];
        $requireConfirmation = isset($_POST['require_answer_confirmation']) ? 1 : (int)$presetMap[$preset]['confirm'];
        $allowEarlyFinish = isset($_POST['allow_early_finish']) ? 1 : 0;
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        if ($duelMode === 'all_in') {
            if (!canUseAllInDuel($pdo, (int)$myId)) {
                setSessionMessage('error', 'Wykorzystałeś dzienny limit All-In Duel (' . $allInLimit . '/' . $allInLimit . ').');
                redirect('challenge.php?opponent=' . $opponentId);
            }
            $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
            $stmt->execute([$myId]);
            $myXp = (int)$stmt->fetchColumn();
            if ($myXp < $stakeXp) {
                setSessionMessage('error', 'Nie masz wystarczająco XP na tę stawkę.');
                redirect('challenge.php?opponent=' . $opponentId);
            }
        }

        $filtered = array_filter($allQuestions, fn($q) => ($q['category'] ?? '') === $category);
        if (empty($filtered)) $filtered = $allQuestions;
        $filtered = array_values($filtered);
        shuffle($filtered);
        $questionIds = array_map(fn($q) => (int)$q['id'], array_slice($filtered, 0, $count));

        try {
            if ($duelMode === 'all_in' && !consumeAllInDuelUse($pdo, (int)$myId)) {
                setSessionMessage('error', 'Wykorzystałeś dzienny limit All-In Duel.');
                redirect('challenge.php?opponent=' . $opponentId);
            }
            $stmt = $pdo->prepare("INSERT INTO duels (challenger_id, opponent_id, category, question_count, question_ids, mode, preset, stake_xp, underdog_bonus, time_per_question_seconds, total_time_seconds, require_answer_confirmation, allow_early_finish, revenge_parent_id, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$myId, $opponentId, $category, count($questionIds), json_encode($questionIds), $duelMode, $preset, $stakeXp, $underdogBonus, $timePerQuestion, $totalTime, $requireConfirmation, $allowEarlyFinish, $parentId ?: null, $expiresAt]);
            $duelId = $pdo->lastInsertId();

            // Notify opponent
            $modeLabel = ['classic' => 'klasyczny', 'underdog' => 'Underdog Mode', 'all_in' => 'All-In Duel'][$duelMode];
            addNotification($pdo, $opponentId, 'duel_challenge', "Użytkownik {$_SESSION['username']} wyzwał Cię na pojedynek ({$modeLabel})!", 'duels/lobby.php?id=' . $duelId);

            setSessionMessage('success', 'Wyzwanie zostało wysłane! Oczekuj na akceptację w lobby.');
            redirect('lobby.php?id=' . $duelId);
        } catch (PDOException $e) {
            error_log('Duel challenge create error: ' . $e->getMessage());
            setSessionMessage('error', 'Nie udało się wysłać wyzwania. Spróbuj ponownie za chwilę.');
        }
    }
}

$flashMsg = getSessionMessage();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyzwanie na pojedynek – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <style>
        .duel-mode-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .duel-mode-card { border: 1px solid rgba(148,163,184,.25); border-radius: 18px; padding: 1rem; cursor: pointer; background: #fff; height: 100%; }
        .btn-check:checked + .duel-mode-card { border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220,38,38,.10); background: rgba(254,242,242,.95); }
        .duel-mode-card strong { display: block; }
        .duel-mode-card span { font-size: .78rem; color: #64748b; }
        @media (max-width: 767.98px) { .duel-mode-grid { grid-template-columns: 1fr; } }
        .duel-preset-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:.75rem; }
        .duel-preset-card { border:1px solid rgba(148,163,184,.25); border-radius:16px; padding:.85rem; cursor:pointer; background:#fff; min-height:98px; }
        .btn-check:checked + .duel-preset-card { border-color:var(--primary-color); box-shadow:0 0 0 4px rgba(59,130,246,.10); }
        @media (max-width: 991.98px) { .duel-preset-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 575.98px) { .duel-preset-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid py-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-6">
                            <div class="dashboard-panel text-center animate-in">
                                <div class="user-avatar-large mx-auto mb-3" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); width: 80px; height: 80px; font-size: 2rem;">
                                    VS
                                </div>
                                <h2 class="fw-bold mb-1">Pojedynek z <?= htmlspecialchars($opponent['username']) ?></h2>
                                <p class="text-muted">Skonfiguruj parametry starcia</p>
                                <?php if ($revengeParentId > 0): ?>
                                    <div class="alert alert-warning border-0 rounded-4 text-start">
                                        <i class="bi bi-lightning-charge-fill me-2"></i>Revenge Match: rewanż po poprzednim pojedynku. Możesz podbić stawkę i szybciej wrócić do rywalizacji.
                                    </div>
                                <?php endif; ?>

<?php if ($flashMsg): ?>
                                     <div class="alert alert-<?php echo ($flashMsg['type'] ?? 'error') === 'error' ? 'danger' : (($flashMsg['type'] === 'success') ? 'success' : 'info'); ?> border-0 mb-4 rounded-4">
                                         <?= htmlspecialchars($flashMsg['message']) ?>
                                     </div>
                                 <?php endif; ?>

                                <form method="POST" class="text-start mt-4">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="revenge_parent_id" value="<?= (int)$revengeParentId ?>">
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Kategoria pytań</label>
                                        <select name="category" class="form-select form-select-lg rounded-4 border-0 shadow-sm bg-light">
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Tryb pojedynku</label>
                                        <div class="duel-mode-grid">
                                            <div>
                                                <input type="radio" class="btn-check" name="duel_mode" id="mode_classic" value="classic" <?= $revengeParentId > 0 ? '' : 'checked' ?>>
                                                <label class="duel-mode-card" for="mode_classic">
                                                    <strong>⚔ Classic</strong>
                                                    <span>Równe zasady, wynik + czas decydują o wygranej.</span>
                                                </label>
                                            </div>
                                            <div>
                                                <input type="radio" class="btn-check" name="duel_mode" id="mode_underdog" value="underdog">
                                                <label class="duel-mode-card" for="mode_underdog">
                                                    <strong>🔥 Underdog</strong>
                                                    <span>Słabszy gracz dostaje mnożnik wyniku 1.15x.</span>
                                                </label>
                                            </div>
                                            <div>
                                                <input type="radio" class="btn-check" name="duel_mode" id="mode_all_in" value="all_in" <?= $revengeParentId > 0 ? 'checked' : '' ?>>
                                                <label class="duel-mode-card" for="mode_all_in">
                                                    <strong>⚡ All-In</strong>
                                                    <span>Obaj gracze stawiają XP. Limit: <?= (int)$allInUsed ?>/<?= (int)$allInLimit ?> dzisiaj.</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4" id="stakeWrap">
                                        <label class="form-label fw-bold small text-uppercase text-muted" for="stakeXp">Stawka XP (All-In)</label>
                                        <input type="number" name="stake_xp" id="stakeXp" class="form-control form-control-lg rounded-4 border-0 shadow-sm bg-light" min="10" max="500" step="10" value="<?= $revengeParentId > 0 ? 100 : 50 ?>">
                                        <div class="form-text">Stawka jest aktywna tylko w trybie All-In. Maksymalnie 500 XP.</div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Preset czasu</label>
                                        <div class="duel-preset-grid">
                                            <?php
                                            $presets = [
                                                'classic' => ['Classic', '10 pytań, bez limitu'],
                                                'sprint' => ['Sprint', '5 pytań, 25s/pyt., 3 min'],
                                                'exam' => ['Egzamin', '20 pytań, 60s/pyt., 20 min'],
                                                'precision' => ['Precyzja', '10 pytań, 15 min, potwierdzanie'],
                                            ];
                                            foreach ($presets as $key => [$title, $desc]):
                                            ?>
                                                <div>
                                                    <input type="radio" class="btn-check" name="duel_preset" id="preset_<?= $key ?>" value="<?= $key ?>" <?= $key === 'classic' ? 'checked' : '' ?>>
                                                    <label class="duel-preset-card d-block" for="preset_<?= $key ?>"><strong><?= htmlspecialchars($title) ?></strong><span class="d-block small text-muted"><?= htmlspecialchars($desc) ?></span></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-uppercase text-muted" for="timePerQuestion">Limit na pytanie (s)</label>
                                            <input type="number" name="time_per_question_seconds" id="timePerQuestion" class="form-control rounded-4 border-0 shadow-sm bg-light" min="10" max="300" placeholder="bez limitu">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-uppercase text-muted" for="totalTime">Limit całości (s)</label>
                                            <input type="number" name="total_time_seconds" id="totalTime" class="form-control rounded-4 border-0 shadow-sm bg-light" min="60" max="3600" placeholder="bez limitu">
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="require_answer_confirmation" id="confirmAnswers">
                                                <label class="form-check-label" for="confirmAnswers">Potwierdzaj odpowiedzi</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="allow_early_finish" id="earlyFinish" checked>
                                                <label class="form-check-label" for="earlyFinish">Pozwól zakończyć wcześniej</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Liczba pytań</label>
                                        <div class="d-flex gap-2">
                                            <?php foreach ([5, 10, 15, 20] as $c): ?>
                                                <input type="radio" class="btn-check" name="question_count" id="count_<?= $c ?>" value="<?= $c ?>" <?= $c === 10 ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-primary flex-grow-1 rounded-pill" for="count_<?= $c ?>"><?= $c ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 pt-3">
                                        <button type="submit" class="btn btn-danger btn-lg rounded-pill fw-bold py-3 shadow-sm">
                                            <i class="bi bi-fire me-2"></i>WYŚLIJ WYZWANIE
                                        </button>
                                        <a href="../social.php" class="btn btn-light rounded-pill">Zrezygnuj</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const stakeWrap = document.getElementById('stakeWrap');
            const syncStake = () => {
                const selected = document.querySelector('[name="duel_mode"]:checked')?.value;
                stakeWrap.style.display = selected === 'all_in' ? '' : 'none';
            };
            document.querySelectorAll('[name="duel_mode"]').forEach(input => input.addEventListener('change', syncStake));
            syncStake();
        });
    </script>
</body>
</html>
