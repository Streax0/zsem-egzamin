<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();
$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$currentRole = (string)($stmt->fetchColumn() ?: ($_SESSION['role'] ?? ''));
$_SESSION['role'] = $currentRole;

if (!in_array($currentRole, ['admin', 'wujek_luki'], true)) {
    setSessionMessage('error', 'Panel Lukiego jest dostępny tylko dla administratora i kont ze statusem Wujek Luki.');
    redirect('index.php');
}

$isAdmin = $currentRole === 'admin';
$today = date('Y-m-d');
$spinResult = null;
$flashMsg = getSessionMessage();

try {
    if (appRuntimeSchemaUpdatesEnabled()) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS luki_spins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                spin_date DATE NOT NULL,
                archetype VARCHAR(40) NOT NULL,
                label VARCHAR(120) NOT NULL,
                xp_delta INT NOT NULL DEFAULT 0,
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, spin_date),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    error_log('Luki table create failed: ' . $e->getMessage());
}

function lukiTodayActivity(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_results WHERE user_id = ? AND DATE(test_date) = CURDATE()");
    $stmt->execute([$userId]);
    $testsToday = (int)$stmt->fetchColumn();

    $streak = 0;
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(test_date) AS activity_date
        FROM test_results
        WHERE user_id = ? AND test_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        ORDER BY activity_date DESC
    ");
    $stmt->execute([$userId]);
    $dates = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!empty($dates)) {
        $today = new DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $first = new DateTimeImmutable($dates[0]);
        if ($first->format('Y-m-d') === $today->format('Y-m-d') || $first->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            $expected = $first;
            foreach ($dates as $date) {
                if ($date !== $expected->format('Y-m-d')) {
                    break;
                }
                $streak++;
                $expected = $expected->modify('-1 day');
            }
        }
    }

    return ['tests_today' => $testsToday, 'streak' => $streak];
}

function lukiPickOutcome(PDO $pdo, int $userId, int $currentXp): array {
    $roll = random_int(1, 10000);
    if ($roll === 1 && $currentXp > 0) {
        return ['archetype' => 'void', 'label' => 'Zakonnica Nicości', 'xp' => -$currentXp, 'note' => 'Sekretny werdykt: cały aktualny XP został wyzerowany. Szansa jest ekstremalnie mała.'];
    }
    if ($roll <= 2000) {
        $xp = [50, 100, 250][random_int(0, 2)];
        return ['archetype' => 'blessing', 'label' => 'Zakonnica Błogosławieństwa', 'xp' => $xp, 'note' => 'Bezpieczny spin. System dopisał spokojny bonus XP.'];
    }
    if ($roll <= 3400) {
        $xp = [300, 500, 750][random_int(0, 2)];
        return ['archetype' => 'abundance', 'label' => 'Zakonnica Obfitości', 'xp' => $xp, 'note' => 'Rzadki złoty wynik. Progres dostał mocny zastrzyk XP.'];
    }
    if ($roll <= 4600) {
        $xp = [150, 250, 350][random_int(0, 2)];
        return ['archetype' => 'grace', 'label' => 'Zakonnica Łaski', 'xp' => $xp, 'note' => 'Łaska systemu przyznała umiarkowany bonus XP.'];
    }
    if ($roll <= 5800) {
        $xp = [0, 50, 100][random_int(0, 2)];
        return ['archetype' => 'ciaza', 'label' => 'Zakonnica Ciąży', 'xp' => $xp, 'note' => 'Tryb ciąża sprawia, że system opiekuje się Twoim postępem — delikatny bonus lub stabilność.'];
    }
    if ($roll <= 7000) {
        return ['archetype' => 'silence', 'label' => 'Zakonnica Ciszy', 'xp' => 0, 'note' => 'System pozostał w równowadze. Brak zmiany XP.'];
    }
    if ($roll <= 8200) {
        $xp = -[20, 50, 100][random_int(0, 2)];
        return ['archetype' => 'trial', 'label' => 'Zakonnica Próby', 'xp' => $xp, 'note' => 'Lekka próba cierpliwości. Ryzyko istnieje.'];
    }
    if ($roll <= 9000) {
        $xp = -[150, 300, 500][random_int(0, 2)];
        return ['archetype' => 'judge', 'label' => 'Zakonnica Sędzi', 'xp' => $xp, 'note' => 'Rzadki wyrok systemu. Mocny spadek XP.'];
    }
    if ($roll <= 9600) {
        $xp = [-200, -100, 0, 100, 200, 400][random_int(0, 5)];
        return ['archetype' => 'fate', 'label' => 'Zakonnica Przeznaczenia', 'xp' => $xp, 'note' => 'Przeznaczenie wymieszało losy — wynik może być zarówno dobry, jak i trudny.'];
    }

    if ($roll <= 9750) {
        $xp = [120, 180, 240, 320][random_int(0, 3)];
        return ['archetype' => 'forge', 'label' => 'Zakonnica Kuźni', 'xp' => $xp, 'note' => 'Kuźnia wzmocniła progres bez ryzyka utraty serii.'];
    }
    if ($roll <= 9870) {
        $xp = [-220, -120, 120, 220][random_int(0, 3)];
        return ['archetype' => 'mirror', 'label' => 'Zakonnica Lustra', 'xp' => $xp, 'note' => 'Lustro odbiło los: wynik jest krótki, mocny i symetryczny.'];
    }
    if ($roll <= 9950) {
        $xp = [0, 90, 180][random_int(0, 2)];
        return ['archetype' => 'archive', 'label' => 'Zakonnica Archiwum', 'xp' => $xp, 'note' => 'Archiwum zachowało stabilność i dopisało ostrożny bonus.'];
    }

    $stmt = $pdo->prepare("SELECT xp_delta FROM luki_spins WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $previous = $stmt->fetchColumn();
    $twist = random_int(1, 4);
    if ($previous !== false && $twist === 1) {
        $xp = (int)$previous;
        $note = 'Oracle skopiowała poprzedni wynik.';
    } elseif ($previous !== false && $twist === 2) {
        $xp = max(-750, min(750, (int)$previous * -1));
        $note = 'Oracle odwróciła poprzedni wynik.';
    } elseif ($previous !== false && $twist === 3) {
        $xp = max(-750, min(750, (int)$previous * 2));
        $note = 'Oracle podwoiła poprzedni wynik.';
    } else {
        $xp = [-250, -100, 0, 100, 250, 500][random_int(0, 5)];
        $note = 'Oracle rzuciła całkowicie losowy werdykt.';
    }
    return ['archetype' => 'oracle', 'label' => 'Zakonnica Losu', 'xp' => $xp, 'note' => $note];
}

function lukiSegments(): array {
    return [
        ['key' => 'blessing', 'name' => 'Błogosławieństwo', 'icon' => 'bi-plus-circle-fill', 'color' => '#22c55e'],
        ['key' => 'abundance', 'name' => 'Obfitość', 'icon' => 'bi-gem', 'color' => '#f59e0b'],
        ['key' => 'grace', 'name' => 'Łaska', 'icon' => 'bi-heart-fill', 'color' => '#8b5cf6'],
        ['key' => 'ciaza', 'name' => 'Ciąża', 'icon' => 'bi-heart-pulse', 'color' => '#ec4899'],
        ['key' => 'silence', 'name' => 'Cisza', 'icon' => 'bi-volume-mute-fill', 'color' => '#94a3b8'],
        ['key' => 'trial', 'name' => 'Próba', 'icon' => 'bi-dash-circle-fill', 'color' => '#ef4444'],
        ['key' => 'judge', 'name' => 'Sędzia', 'icon' => 'bi-exclamation-triangle-fill', 'color' => '#a21caf'],
        ['key' => 'fate', 'name' => 'Przeznaczenie', 'icon' => 'bi-shuffle', 'color' => '#0ea5e9'],
        ['key' => 'forge', 'name' => 'Kuźnia', 'icon' => 'bi-hammer', 'color' => '#f97316'],
        ['key' => 'mirror', 'name' => 'Lustro', 'icon' => 'bi-symmetry-horizontal', 'color' => '#14b8a6'],
        ['key' => 'archive', 'name' => 'Archiwum', 'icon' => 'bi-archive-fill', 'color' => '#6366f1'],
        ['key' => 'oracle', 'name' => 'Los', 'icon' => 'bi-bullseye', 'color' => '#06b6d4'],
        ['key' => 'void', 'name' => 'Nicość', 'icon' => 'bi-moon-stars-fill', 'color' => '#020617'],
    ];
}

function lukiOutcomeIndex(array $segments, string $archetype): int {
    $index = array_search($archetype, array_column($segments, 'key'), true);
    return $index === false ? 0 : (int)$index;
}

function lukiWantsJson(): bool {
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function lukiJsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lukiSpinResponsePayload(array $outcome, int $spinId, int $resultIndex, int $spinsLeft, string $spinsDisplay, int $currentXp, array $rankInfo, string $createdAt): array {
    return [
        'success' => true,
        'result' => [
            'id' => $spinId,
            'archetype' => (string)$outcome['archetype'],
            'label' => (string)$outcome['label'],
            'xp' => (int)$outcome['xp'],
            'note' => (string)$outcome['note'],
            'index' => $resultIndex,
            'created_at' => $createdAt,
        ],
        'state' => [
            'current_xp' => $currentXp,
            'rank' => (string)($rankInfo['name'] ?? ''),
            'spins_left' => $spinsLeft,
            'spins_display' => $spinsDisplay,
            'last_spin_at' => date('d.m H:i', strtotime($createdAt)),
        ],
    ];
}

$segments = lukiSegments();
$activity = lukiTodayActivity($pdo, $userId);
$dailySpinLimit = $isAdmin ? null : 2;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM luki_spins WHERE user_id = ? AND spin_date = ?");
$stmt->execute([$userId, $today]);
$spinsToday = (int)$stmt->fetchColumn();
$spinsLeft = $isAdmin ? PHP_INT_MAX : max(0, $dailySpinLimit - $spinsToday);
$spinsDisplay = $isAdmin ? '∞' : ((string)$spinsLeft . '/' . (string)$dailySpinLimit);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wantsJson = lukiWantsJson();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        if ($wantsJson) {
            lukiJsonResponse(['success' => false, 'message' => 'Błąd bezpieczeństwa CSRF. Odśwież stronę i spróbuj ponownie.'], 403);
        }
        setSessionMessage('error', 'Błąd bezpieczeństwa CSRF.');
        redirect('luki_panel.php');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $lockedRole = (string)$stmt->fetchColumn();
        if (!in_array($lockedRole, ['admin', 'wujek_luki'], true)) {
            $pdo->rollBack();
            if ($wantsJson) {
                lukiJsonResponse(['success' => false, 'message' => 'Brak dostępu do Zakonnicomatu.'], 403);
            }
            setSessionMessage('error', 'Brak dostępu do Zakonnicomatu.');
            redirect('luki_panel.php');
        }

        $isAdminSpin = $lockedRole === 'admin';
        if (!$isAdminSpin) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM luki_spins WHERE user_id = ? AND spin_date = ?");
            $stmt->execute([$userId, $today]);
            if ((int)$stmt->fetchColumn() >= 2) {
                $pdo->rollBack();
                if ($wantsJson) {
                    lukiJsonResponse(['success' => false, 'message' => 'Limit 2 spinów na dziś został wykorzystany.'], 429);
                }
                setSessionMessage('error', 'Limit 2 spinów na dziś został wykorzystany.');
                redirect('luki_panel.php');
            }
        }

        $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $lockedXp = max(0, (int)$stmt->fetchColumn());
        $outcome = lukiPickOutcome($pdo, $userId, $lockedXp);
        $stmt = $pdo->prepare("INSERT INTO luki_spins (user_id, spin_date, archetype, label, xp_delta, note) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $today, $outcome['archetype'], $outcome['label'], $outcome['xp'], $outcome['note']]);
        $spinId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE users SET xp = GREATEST(0, xp + ?) WHERE id = ?")->execute([(int)$outcome['xp'], $userId]);
        $stmt = $pdo->prepare("INSERT INTO xp_events (user_id, source, source_id, amount, description) VALUES (?, 'luki_spin', ?, ?, ?)");
        $stmt->execute([$userId, $spinId, (int)$outcome['xp'], $outcome['label'] . ': ' . $outcome['note']]);
        $pdo->commit();
        if ($wantsJson) {
            $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $updatedXp = max(0, (int)$stmt->fetchColumn());
            $rankAfterSpin = getRankInfoByXp($updatedXp);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM luki_spins WHERE user_id = ? AND spin_date = ?");
            $stmt->execute([$userId, $today]);
            $spinsTodayAfter = (int)$stmt->fetchColumn();
            $spinsLeftAfter = $isAdminSpin ? PHP_INT_MAX : max(0, 2 - $spinsTodayAfter);
            $spinsDisplayAfter = $isAdminSpin ? '∞' : ((string)$spinsLeftAfter . '/2');
            lukiJsonResponse(lukiSpinResponsePayload(
                $outcome,
                $spinId,
                lukiOutcomeIndex($segments, (string)$outcome['archetype']),
                $spinsLeftAfter,
                $spinsDisplayAfter,
                $updatedXp,
                $rankAfterSpin,
                date('Y-m-d H:i:s')
            ));
        }
        $_SESSION['luki_last_spin'] = $outcome + ['id' => $spinId];
        redirect('luki_panel.php?spin=1');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Luki spin failed: ' . $e->getMessage());
        if (!empty($wantsJson)) {
            lukiJsonResponse(['success' => false, 'message' => 'Nie udało się wykonać spinu. Spróbuj ponownie.'], 500);
        }
        setSessionMessage('error', 'Nie udało się wykonać spinu.');
        redirect('luki_panel.php');
    }
}

if (isset($_GET['spin'], $_SESSION['luki_last_spin'])) {
    $spinResult = $_SESSION['luki_last_spin'];
    unset($_SESSION['luki_last_spin']);
}

$stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentXp = (int)$stmt->fetchColumn();
$rankInfo = getRankInfoByXp($currentXp);

$stmt = $pdo->prepare("SELECT * FROM luki_spins WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS spin_count,
        COALESCE(SUM(xp_delta), 0) AS xp_balance,
        COALESCE(MAX(xp_delta), 0) AS best_spin,
        COALESCE(MIN(xp_delta), 0) AS worst_spin,
        MAX(created_at) AS last_spin_at
    FROM luki_spins
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$userId]);
$weekly = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$weeklySpinCount = (int)($weekly['spin_count'] ?? 0);
$weeklyBalance = (int)($weekly['xp_balance'] ?? 0);
$weeklyBest = (int)($weekly['best_spin'] ?? 0);
$weeklyWorst = (int)($weekly['worst_spin'] ?? 0);
$lastSpinAt = !empty($weekly['last_spin_at']) ? date('d.m H:i', strtotime((string)$weekly['last_spin_at'])) : 'brak';

$positive = $neutral = $negative = 0;
foreach ($history as $entry) {
    if ((int)$entry['xp_delta'] > 0) $positive++;
    elseif ((int)$entry['xp_delta'] < 0) $negative++;
    else $neutral++;
}
$totalHistory = max(1, count($history));
$luckBalance = array_sum(array_map(fn($row) => (int)$row['xp_delta'], $history));
$luckTrend = $luckBalance > 0 ? 'Szczęście rośnie' : ($luckBalance < 0 ? 'System testuje cierpliwość' : 'Równowaga systemu');

$riskScore = min(100, max(0, 40 + ($negative * 12) - ($positive * 6) + ($weeklyWorst < -300 ? 18 : 0)));
$riskLabel = $riskScore >= 70 ? 'Wysokie ryzyko' : ($riskScore >= 45 ? 'Ryzyko umiarkowane' : 'Stabilny profil');

$segments = [
    ['key' => 'blessing', 'name' => 'Błogosławieństwo', 'icon' => 'bi-plus-circle-fill', 'color' => '#22c55e'],
    ['key' => 'abundance', 'name' => 'Obfitość', 'icon' => 'bi-gem', 'color' => '#f59e0b'],
    ['key' => 'grace', 'name' => 'Łaska', 'icon' => 'bi-heart-fill', 'color' => '#8b5cf6'],
    ['key' => 'ciaza', 'name' => 'Ciąża', 'icon' => 'bi-heart-pulse', 'color' => '#ec4899'],
    ['key' => 'silence', 'name' => 'Cisza', 'icon' => 'bi-volume-mute-fill', 'color' => '#94a3b8'],
    ['key' => 'trial', 'name' => 'Próba', 'icon' => 'bi-dash-circle-fill', 'color' => '#ef4444'],
    ['key' => 'judge', 'name' => 'Sędzia', 'icon' => 'bi-exclamation-triangle-fill', 'color' => '#a21caf'],
    ['key' => 'fate', 'name' => 'Przeznaczenie', 'icon' => 'bi-shuffle', 'color' => '#0ea5e9'],
    ['key' => 'forge', 'name' => 'Kuźnia', 'icon' => 'bi-hammer', 'color' => '#f97316'],
    ['key' => 'mirror', 'name' => 'Lustro', 'icon' => 'bi-symmetry-horizontal', 'color' => '#14b8a6'],
    ['key' => 'archive', 'name' => 'Archiwum', 'icon' => 'bi-archive-fill', 'color' => '#6366f1'],
    ['key' => 'oracle', 'name' => 'Los', 'icon' => 'bi-bullseye', 'color' => '#06b6d4'],
    ['key' => 'void', 'name' => 'Nicość', 'icon' => 'bi-moon-stars-fill', 'color' => '#020617'],
];

$segmentCount = count($segments);
$wheelGradientStops = [];
foreach ($segments as $i => $segment) {
    $start = 90 + ($i * 360) / $segmentCount;
    $end = 90 + (($i + 1) * 360) / $segmentCount;
    $wheelGradientStops[] = sprintf('%s %.4fdeg %.4fdeg', htmlspecialchars($segment['color']), $start, $end);
}
$wheelGradient = implode(",\n                ", $wheelGradientStops);

$resultIndex = $spinResult ? lukiOutcomeIndex($segments, (string)$spinResult['archetype']) : 0;
?>
<?php
$pageTitle = 'Zakonnicomat - Panel Lukiego';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/luki_panel.css'];
include '../includes/header.php';
?>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 luki-shell">
                <?php if ($flashMsg): ?>
                    <div class="alert alert-<?php echo $flashMsg['type'] === 'error' ? 'danger' : 'success'; ?> border-0 shadow-sm"><?php echo htmlspecialchars($flashMsg['message']); ?></div>
                <?php endif; ?>

                <section class="luki-hero mb-4">
                    <div class="luki-hero-copy">
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <span class="badge rounded-pill bg-white bg-opacity-25">Uncle Luki's Zakonnicomat v3.0</span>
                            <button type="button" class="luki-audio-toggle ms-auto" id="lukiAudioToggle" aria-label="Przełącz dźwięk Zakonnicomatu">
                                <i class="bi bi-volume-up-fill me-1"></i><span id="lukiAudioStatus">Dźwięk: Wł.</span>
                            </button>
                        </div>
                        <h1 class="fw-900 mb-2"><i class="bi bi-stars text-warning me-2"></i>Świątynia Zakonnicomat</h1>
                        <p class="lead opacity-75 mb-0">Endgame prestige dla kont ze statusem Wujek Luki. Losuj łaskę 13 Świętych Zakonnic, zdobywaj bonusowe XP i buduj swoją serię szczęścia.</p>
                    </div>
                    <div class="luki-hero-art">
                        <img class="luki-sign" src="assets/images/luki-zakonnicomat-sign.svg" alt="Szyld maszyny losującej Wujka Lukiego" loading="lazy" decoding="async">
                        <img class="luki-mascot" src="assets/images/luki-zakonnica.svg" alt="" aria-hidden="true" loading="lazy" decoding="async">
                    </div>
                </section>

                <div class="luki-grid">
                    <section class="luki-spin-card p-4">
                        <div class="wheel-wrap">
                            <div class="wheel-stage" id="wheelStage">
                                <canvas id="lukiConfettiCanvas"></canvas>
                                <div class="wheel-pointer"></div>
                                <div class="luki-wheel" id="lukiWheel" data-segments="<?php echo count($segments); ?>" style="background: conic-gradient(<?php echo $wheelGradient; ?>);">
                                    <div class="center-mark"></div>
                                    <?php foreach ($segments as $i => $segment): ?>
                                        <?php $angle = 90 + ($i * (360 / count($segments))) + (180 / count($segments)); ?>
                                        <div class="wheel-segment-label" style="--angle: <?php echo $angle; ?>deg; --segment-color: <?php echo htmlspecialchars($segment['color']); ?>;">
                                            <i class="bi <?php echo htmlspecialchars($segment['icon']); ?>"></i>
                                            <span><?php echo htmlspecialchars($segment['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                                <div class="zakonnica-guard-grid" aria-hidden="true">
                                    <span class="zakonnica-guard"><i class="bi bi-person-circle"></i><small>Zakonnica Mocy</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-heart-fill"></i><small>Zakonnica Łaski</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-shield-lock"></i><small>Zakonnica Straży</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-moon-stars-fill"></i><small>Zakonnica Nicości</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-stars"></i><small>Zakonnica Gwiazd</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-award-fill"></i><small>Zakonnica Przeznaczenia</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-hammer"></i><small>Zakonnica Kuźni</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-symmetry-horizontal"></i><small>Zakonnica Lustra</small></span>
                                    <span class="zakonnica-guard"><i class="bi bi-archive-fill"></i><small>Zakonnica Archiwum</small></span>
                                </div>

                            <form method="POST" action="luki_panel.php" class="text-center spin-actions" data-luki-spin-form>
                                <?php echo csrfTokenField(); ?>
                                <button class="btn btn-primary btn-lg rounded-pill px-5 fw-bold" data-luki-spin-button <?php echo $spinsLeft <= 0 ? 'disabled' : ''; ?>>
                                    <i class="bi bi-arrow-repeat me-2"></i>Spin Zakonnicomatem
                                </button>
                                <div class="small text-muted mt-2">Pozostało dziś: <strong data-spins-left><?php echo htmlspecialchars($spinsDisplay); ?></strong></div>
                                <div class="small mt-2 d-none" data-luki-spin-alert aria-live="polite"></div>
                            </form>

                            <div id="spinResultMobileMount" data-spin-result-mount class="mt-4 d-md-none">
                                <?php if ($spinResult): ?>
                                <div class="result-card pending-reveal <?php echo htmlspecialchars($spinResult['archetype']); ?>" data-index="<?php echo (int)$resultIndex; ?>" data-delta="<?php echo (int)$spinResult['xp']; ?>">
                                    <div class="small text-muted fw-bold text-uppercase">Wynik spinu</div>
                                    <h4 class="fw-900 mb-1"><?php echo htmlspecialchars($spinResult['label']); ?></h4>
                                    <div class="display-6 fw-900 <?php echo (int)$spinResult['xp'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo (int)$spinResult['xp'] > 0 ? '+' : ''; ?><?php echo (int)$spinResult['xp']; ?> XP
                                    </div>
                                    <p class="mb-0 text-muted"><?php echo htmlspecialchars($spinResult['note']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="luki-legend w-100">
                                <?php foreach ($segments as $segment): ?>
                                    <div class="legend-pill" style="--label-color: <?php echo htmlspecialchars($segment['color']); ?>;">
                                        <i class="bi <?php echo htmlspecialchars($segment['icon']); ?>"></i>
                                        <span><?php echo htmlspecialchars($segment['name']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="luki-motif-grid">
                                <div class="luki-motif" style="--label-color:#22c55e;">
                                    <i class="bi bi-flower1"></i>
                                    <div class="fw-bold mt-2">Kaplica bonusu</div>
                                    <div class="small text-muted">Pozytywne zakonnice budują serię szczęścia.</div>
                                </div>
                                <div class="luki-motif" style="--label-color:#a21caf;">
                                    <i class="bi bi-stars"></i>
                                    <div class="fw-bold mt-2">Krypta ryzyka</div>
                                    <div class="small text-muted">Sędzia, Próba i Nicość pilnują, żeby spin miał wagę.</div>
                                </div>
                                <div class="luki-motif" style="--label-color:#06b6d4;">
                                    <i class="bi bi-incognito"></i>
                                    <div class="fw-bold mt-2">Oracle</div>
                                    <div class="small text-muted">Może skopiować, odwrócić albo podwoić poprzedni wynik.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <aside class="vstack gap-4">
                        <div class="luki-card p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Status Lukiego</h5>
                            <div class="status-row"><span>XP</span><strong data-luki-xp><?php echo number_format($currentXp); ?></strong></div>
                            <div class="status-row"><span>Ranga</span><strong data-luki-rank><?php echo htmlspecialchars($rankInfo['name']); ?></strong></div>
                            <div class="status-row"><span>Limit spinów</span><strong><?php echo $isAdmin ? 'Bez limitu' : '2 dziennie'; ?></strong></div>
                            <div class="status-row"><span>Testy dziś</span><strong><?php echo (int)$activity['tests_today']; ?></strong></div>
                            <div class="status-row"><span>Streak aktywności</span><strong><?php echo (int)$activity['streak']; ?> dni</strong></div>
                            <div class="status-row"><span>Ostatni spin</span><strong data-luki-last-spin><?php echo htmlspecialchars($lastSpinAt); ?></strong></div>
                        </div>
                        <div id="spinResultDesktopMount" data-spin-result-mount class="d-none d-md-block">
                            <?php if ($spinResult): ?>
                            <div class="result-card pending-reveal <?php echo htmlspecialchars($spinResult['archetype']); ?>" data-index="<?php echo (int)$resultIndex; ?>" data-delta="<?php echo (int)$spinResult['xp']; ?>">
                                <div class="small text-muted fw-bold text-uppercase">Wynik spinu</div>
                                <h4 class="fw-900 mb-1"><?php echo htmlspecialchars($spinResult['label']); ?></h4>
                                <div class="display-6 fw-900 <?php echo (int)$spinResult['xp'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo (int)$spinResult['xp'] > 0 ? '+' : ''; ?><?php echo (int)$spinResult['xp']; ?> XP
                                </div>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($spinResult['note']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="luki-card p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-activity me-2 text-warning"></i>Luck Trend</h5>
                            <div class="trend-grid mb-3">
                                <div class="trend-box"><strong><?php echo round(($positive / $totalHistory) * 100); ?>%</strong><div class="small text-muted">+XP</div></div>
                                <div class="trend-box"><strong><?php echo round(($neutral / $totalHistory) * 100); ?>%</strong><div class="small text-muted">0</div></div>
                                <div class="trend-box"><strong><?php echo round(($negative / $totalHistory) * 100); ?>%</strong><div class="small text-muted">-XP</div></div>
                            </div>
                            <div class="fw-bold"><?php echo htmlspecialchars($luckTrend); ?></div>
                            <div class="small text-muted">Bilans ostatnich spinów: <?php echo $luckBalance > 0 ? '+' : ''; ?><?php echo (int)$luckBalance; ?> XP</div>
                        </div>

                        <div class="luki-card p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow me-2 text-info"></i>Tydzień losu</h5>
                            <div class="luki-week-grid mb-3">
                                <div class="luki-week-box"><strong><?php echo (int)$weeklySpinCount; ?></strong><div class="small text-muted">spinów 7 dni</div></div>
                                <div class="luki-week-box"><strong><?php echo $weeklyBalance > 0 ? '+' : ''; ?><?php echo (int)$weeklyBalance; ?> XP</strong><div class="small text-muted">bilans tygodnia</div></div>
                                <div class="luki-week-box"><strong><?php echo $weeklyBest > 0 ? '+' : ''; ?><?php echo (int)$weeklyBest; ?></strong><div class="small text-muted">najlepszy spin</div></div>
                                <div class="luki-week-box"><strong><?php echo (int)$weeklyWorst; ?></strong><div class="small text-muted">najgorszy spin</div></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold"><?php echo htmlspecialchars($riskLabel); ?></span>
                                <span class="small text-muted"><?php echo (int)$riskScore; ?>/100</span>
                            </div>
                            <div class="luki-risk-meter" style="--risk: <?php echo (int)$riskScore; ?>%;"><span></span></div>
                        </div>

                        <div class="luki-card p-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-success"></i>Ochrona losowania</h5>
                            <div class="small text-muted">
                                Wynik jest liczony po stronie serwera, zapis idzie w transakcji, a limit Wujka Lukiego jest sprawdzany po blokadzie konta. Podwójny klik nie powinien nabić dodatkowego spinu.
                            </div>
                        </div>
                    </aside>
                </div>

                <!-- Kodeks 13 Świętych Zakonnic -->
                <section class="luki-card p-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <h4 class="fw-900 mb-1"><i class="bi bi-journal-bookmark-fill me-2 text-warning"></i>Klasztor Zakonnic – Kodeks 13 Archetypów</h4>
                            <p class="text-muted small mb-0">Kliknij na wybraną zakondę, aby poznać jej opis, zakres XP i wady.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap" id="codexFilters">
                            <button type="button" class="codex-filter-btn active" data-filter="all">Wszystkie (13)</button>
                            <button type="button" class="codex-filter-btn" data-filter="bonus">Bonusy (+XP)</button>
                            <button type="button" class="codex-filter-btn" data-filter="neutral">Neutralne (0 XP)</button>
                            <button type="button" class="codex-filter-btn" data-filter="trial">Próby (-XP)</button>
                            <button type="button" class="codex-filter-btn" data-filter="unique">Unikalne</button>
                        </div>
                    </div>

                    <div class="nun-codex-grid" id="nunCodexGrid">
                        <?php
                        $nunCodexDetails = [
                            'blessing' => ['type' => 'bonus', 'range' => '+50 do +250 XP', 'desc' => 'Bezpieczny spin. Spokojny bonus dopisywany do progresu bez ryzyka.', 'tag' => 'Bezpieczna'],
                            'abundance' => ['type' => 'bonus', 'range' => '+300 do +750 XP', 'desc' => 'Rzadki złoty wynik. Progres dostaje bardzo mocny zastrzyk energii.', 'tag' => 'Rzadka Gold'],
                            'grace' => ['type' => 'bonus', 'range' => '+150 do +350 XP', 'desc' => 'Łaska systemu przyznaje umiarkowany i pewny bonus XP.', 'tag' => 'Łaskawa'],
                            'ciaza' => ['type' => 'unique', 'range' => '0 do +100 XP', 'desc' => 'Tryb ciąży sprawia, że system opiekuje się Twoim postępem i chroni punkty.', 'tag' => 'Opiekuńcza'],
                            'silence' => ['type' => 'neutral', 'range' => '0 XP', 'desc' => 'System pozostaje w doskonałej równowadze. Brak zmiany XP.', 'tag' => 'Harmonia'],
                            'trial' => ['type' => 'trial', 'range' => '-20 do -100 XP', 'desc' => 'Lekka próba cierpliwości. Drobna korekta na drodze do celu.', 'tag' => 'Korekta'],
                            'judge' => ['type' => 'trial', 'range' => '-150 do -500 XP', 'desc' => 'Wyrok systemu. Mocny spadek XP testujący determinację.', 'tag' => 'Surowy Wyrok'],
                            'fate' => ['type' => 'unique', 'range' => '-200 do +400 XP', 'desc' => 'Przeznaczenie miesza losy — wynik może przynieść zarówno straty jak i zyski.', 'tag' => 'Losowa'],
                            'forge' => ['type' => 'bonus', 'range' => '+120 do +320 XP', 'desc' => 'Kuźnia wzmacnia progres bez jakiegokolwiek ryzyka utraty serii.', 'tag' => 'Zakonnica Kuźni'],
                            'mirror' => ['type' => 'unique', 'range' => '-220 do +220 XP', 'desc' => 'Lustro odbija los: wynik jest krótki, mocny i symetryczny.', 'tag' => 'Zakonnica Lustra'],
                            'archive' => ['type' => 'neutral', 'range' => '0 do +180 XP', 'desc' => 'Archiwum zachowuje stabilność bazy i dopisuje ostrożny bonus.', 'tag' => 'Zakonnica Archiwum'],
                            'oracle' => ['type' => 'unique', 'range' => 'Varium / Duplikacja', 'desc' => 'Oracle może skopiować, odwrócić lub podwoić Twój poprzedni spin!', 'tag' => 'Zakonnica Losu'],
                            'void' => ['type' => 'trial', 'range' => '-100% XP (Wyzerowanie)', 'desc' => 'Sekretny wyrok nicości. Szansa jest rzędu 0.01%, ale czyści cały XP.', 'tag' => 'Nicość Ekstremalna'],
                        ];
                        foreach ($segments as $seg):
                            $meta = $nunCodexDetails[$seg['key']] ?? ['type' => 'neutral', 'range' => 'Varium', 'desc' => '', 'tag' => 'Standard'];
                        ?>
                            <div class="nun-card" data-nun-key="<?php echo htmlspecialchars($seg['key']); ?>" data-nun-type="<?php echo htmlspecialchars($meta['type']); ?>" style="--nun-color: <?php echo htmlspecialchars($seg['color']); ?>;" onclick="openNunCodexModal('<?php echo htmlspecialchars($seg['key']); ?>', '<?php echo htmlspecialchars(addslashes($seg['name'])); ?>', '<?php echo htmlspecialchars(addslashes($meta['desc'])); ?>', '<?php echo htmlspecialchars($meta['range']); ?>', '<?php echo htmlspecialchars($seg['color']); ?>', '<?php echo htmlspecialchars($seg['icon']); ?>')">
                                <div class="nun-card-icon">
                                    <i class="bi <?php echo htmlspecialchars($seg['icon']); ?>"></i>
                                </div>
                                <div class="nun-card-type"><?php echo htmlspecialchars($meta['tag']); ?></div>
                                <div class="nun-card-title"><?php echo htmlspecialchars($seg['name']); ?></div>
                                <div class="nun-card-range" style="color: <?php echo htmlspecialchars($seg['color']); ?>;"><?php echo htmlspecialchars($meta['range']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="luki-card p-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <h4 class="fw-bold mb-0"><i class="bi bi-scroll me-2 text-primary"></i>Chronicle of Spins</h4>
                        <span class="badge text-bg-light rounded-pill">Ostatnie 10 wyników</span>
                    </div>
                    <div data-luki-history>
                        <?php if (empty($history)): ?>
                            <div class="text-center text-muted py-4" data-luki-history-empty>Brak spinów. Chronicle czeka na pierwszy werdykt.</div>
                        <?php else: ?>
                            <?php foreach ($history as $entry): ?>
                            <div class="chronicle-item" data-luki-history-entry>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($entry['label']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($entry['note'] ?? ''); ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-900 <?php echo (int)$entry['xp_delta'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo (int)$entry['xp_delta'] > 0 ? '+' : ''; ?><?php echo (int)$entry['xp_delta']; ?> XP</div>
                                    <div class="small text-muted"><?php echo date('d.m H:i', strtotime($entry['created_at'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Modal Kodeksu Zakonnicy -->
<div class="modal fade" id="nunCodexModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4" style="background: var(--bs-body-bg, #0f172a);">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div id="modalNunIcon" class="p-3 rounded-4 text-white display-6"></div>
                    <div>
                        <h4 class="fw-900 mb-0 text-body" id="modalNunTitle"></h4>
                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary small" id="modalNunRange"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body py-4">
                <p class="lead fs-6 mb-0 text-muted" id="modalNunDesc"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const wheel = document.getElementById('lukiWheel');
    const pointer = document.querySelector('.wheel-pointer');
    const form = document.querySelector('[data-luki-spin-form]');
    const button = document.querySelector('[data-luki-spin-button]');
    const alertBox = document.querySelector('[data-luki-spin-alert]');
    const resultMounts = Array.from(document.querySelectorAll('[data-spin-result-mount]'));
    let currentRotation = 0;

    // Audio Engine via Web Audio API
    const audioState = {
        muted: localStorage.getItem('luki_audio_muted') === 'true',
        ctx: null
    };
    const audioToggleBtn = document.getElementById('lukiAudioToggle');
    const audioStatusSpan = document.getElementById('lukiAudioStatus');

    const updateAudioUI = () => {
        if (!audioToggleBtn || !audioStatusSpan) return;
        if (audioState.muted) {
            audioToggleBtn.classList.add('is-muted');
            audioStatusSpan.textContent = 'Dźwięk: Wył.';
            audioToggleBtn.querySelector('i').className = 'bi bi-volume-mute-fill me-1';
        } else {
            audioToggleBtn.classList.remove('is-muted');
            audioStatusSpan.textContent = 'Dźwięk: Wł.';
            audioToggleBtn.querySelector('i').className = 'bi bi-volume-up-fill me-1';
        }
    };
    updateAudioUI();

    if (audioToggleBtn) {
        audioToggleBtn.addEventListener('click', () => {
            audioState.muted = !audioState.muted;
            localStorage.setItem('luki_audio_muted', audioState.muted ? 'true' : 'false');
            updateAudioUI();
        });
    }

    const getAudioCtx = () => {
        if (!audioState.ctx) {
            audioState.ctx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioState.ctx.state === 'suspended') {
            audioState.ctx.resume();
        }
        return audioState.ctx;
    };

    const playTickSound = () => {
        if (audioState.muted) return;
        try {
            const ctx = getAudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(600 + Math.random() * 80, ctx.currentTime);
            gain.gain.setValueAtTime(0.06, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.03);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.04);
        } catch (e) {}
    };

    // Canvas Confetti
    const canvas = document.getElementById('lukiConfettiCanvas');
    let particles = [];
    let canvasAnimId = null;

    const triggerConfetti = (delta) => {
        if (!canvas) return;
        const rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        const ctx = canvas.getContext('2d');
        const colors = delta > 0 ? ['#22c55e', '#f59e0b', '#8b5cf6', '#3b82f6', '#ffffff'] : ['#ef4444', '#a21caf', '#64748b'];
        const count = delta > 0 ? 60 : 30;
        particles = [];
        for (let i = 0; i < count; i++) {
            particles.push({
                x: canvas.width / 2,
                y: canvas.height / 2,
                vx: (Math.random() - 0.5) * 12,
                vy: (Math.random() - 0.7) * 12,
                size: Math.random() * 7 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                alpha: 1,
                decay: Math.random() * 0.02 + 0.015
            });
        }
        if (canvasAnimId) cancelAnimationFrame(canvasAnimId);
        const animate = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let alive = false;
            particles.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.25;
                p.alpha -= p.decay;
                if (p.alpha > 0) {
                    alive = true;
                    ctx.save();
                    ctx.globalAlpha = Math.max(0, p.alpha);
                    ctx.fillStyle = p.color;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                }
            });
            if (alive) {
                canvasAnimId = requestAnimationFrame(animate);
            }
        };
        animate();
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char]);
    const signedXp = (value) => {
        const amount = Number(value || 0);
        return `${amount > 0 ? '+' : ''}${amount} XP`;
    };
    const toneForDelta = (delta) => {
        if (!audioState.muted) {
            try {
                const ctx = getAudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = delta > 0 ? 880 : (delta < 0 ? 140 : 340);
                gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
                osc.start();
                osc.stop(ctx.currentTime + 0.5);
            } catch (e) {
                console.error('Audio generation error:', e);
            }
        }
        triggerConfetti(delta);
    };
    const setAlert = (message, type = 'danger') => {
        if (!alertBox) return;
        alertBox.textContent = message || '';
        alertBox.className = `small mt-2 ${message ? `text-${type}` : 'd-none'}`;
    };
    const renderSpinResultCard = (result) => {
        const delta = Number(result?.xp || 0);
        const toneClass = delta >= 0 ? 'text-success' : 'text-danger';
        return `<div class="result-card pending-reveal ${escapeHtml(result?.archetype || '')}" data-index="${Number(result?.index || 0)}" data-delta="${delta}">
            <div class="small text-muted fw-bold text-uppercase">Wynik spinu</div>
            <h4 class="fw-900 mb-1">${escapeHtml(result?.label || '')}</h4>
            <div class="display-6 fw-900 ${toneClass}">${signedXp(delta)}</div>
            <p class="mb-0 text-muted">${escapeHtml(result?.note || '')}</p>
        </div>`;
    };
    const updateState = (state) => {
        const xp = document.querySelector('[data-luki-xp]');
        const rank = document.querySelector('[data-luki-rank]');
        const spinsLeft = document.querySelector('[data-spins-left]');
        const lastSpin = document.querySelector('[data-luki-last-spin]');
        if (xp) xp.textContent = Number(state?.current_xp || 0).toLocaleString('pl-PL');
        if (rank) rank.textContent = state?.rank || '';
        if (spinsLeft) spinsLeft.textContent = state?.spins_display || '';
        if (lastSpin) lastSpin.textContent = state?.last_spin_at || '';
        if (button) button.disabled = Number(state?.spins_left || 0) <= 0;
    };

    const getVisibleResultCard = () => {
        return resultMounts.map(mount => mount.querySelector('.result-card')).find(card => card?.offsetParent !== null) || null;
    };
    const prependHistory = (result) => {
        const history = document.querySelector('[data-luki-history]');
        if (!history || !result) return;
        history.querySelector('[data-luki-history-empty]')?.remove();
        const entry = document.createElement('div');
        const delta = Number(result.xp || 0);
        entry.className = 'chronicle-item is-new';
        entry.setAttribute('data-luki-history-entry', '1');
        entry.innerHTML = `<div><div class="fw-bold">${escapeHtml(result.label)}</div><div class="small text-muted">${escapeHtml(result.note)}</div></div>
            <div class="text-end"><div class="fw-900 ${delta >= 0 ? 'text-success' : 'text-danger'}">${signedXp(delta)}</div><div class="small text-muted">teraz</div></div>`;
        history.prepend(entry);
        history.querySelectorAll('[data-luki-history-entry]').forEach((item, index) => {
            if (index >= 10) item.remove();
        });
    };
    const playSpinResult = (resultCard) => {
        if (!resultCard) return;
        if (!wheel) {
            resultCard.classList.add('is-visible');
            return;
        }
        const segmentCount = Number(wheel.dataset.segments || 6);
        const index = Number(resultCard.dataset.index || 0);
        const segmentAngle = 360 / segmentCount;
        const segmentCenter = index * segmentAngle + segmentAngle / 2;
        const desired = (360 - segmentCenter) % 360;
        const normalized = ((currentRotation % 360) + 360) % 360;
        const rotations = 360 * (5 + Math.floor(Math.random() * 3));
        currentRotation += rotations + ((desired - normalized + 360) % 360);
        wheel.classList.add('is-spinning');
        pointer?.classList.add('is-ticking');

        let tickInterval = setInterval(() => {
            playTickSound();
        }, 120);

        requestAnimationFrame(() => {
            wheel.style.setProperty('--rot', currentRotation + 'deg');
        });
        window.setTimeout(() => {
            clearInterval(tickInterval);
            resultCard.classList.add('is-visible');
            wheel.classList.remove('is-spinning');
            pointer?.classList.remove('is-ticking');
            toneForDelta(Number(resultCard.dataset.delta || 0));
        }, 4850);
    };

    const initialResult = getVisibleResultCard();
    if (initialResult) playSpinResult(initialResult);

    // Nun Codex Filters
    const codexFilterBtns = document.querySelectorAll('#codexFilters button');
    const nunCards = document.querySelectorAll('.nun-card');
    codexFilterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            codexFilterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.dataset.filter;
            nunCards.forEach(card => {
                if (filter === 'all' || card.dataset.nunType === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    if (!form || !button || !window.fetch) return;
    const defaultButtonHtml = button.innerHTML;
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (button.disabled || button.classList.contains('is-busy')) return;
        setAlert('');
        button.disabled = true;
        button.classList.add('is-busy');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Losowanie...';
        try {
            const response = await fetch(form.action || 'luki_panel.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Nie udało się wykonać spinu.');
            }
            if (resultMounts.length) {
                const html = renderSpinResultCard(data.result);
                resultMounts.forEach(mount => { mount.innerHTML = html; });
                playSpinResult(getVisibleResultCard());
            }
            updateState(data.state || {});
            prependHistory(data.result);
        } catch (error) {
            setAlert(error.message || 'Nie udało się wykonać spinu.');
            button.disabled = false;
        } finally {
            button.classList.remove('is-busy');
            button.innerHTML = defaultButtonHtml;
        }
    });
});

function openNunCodexModal(key, title, desc, range, color, icon) {
    const titleEl = document.getElementById('modalNunTitle');
    const descEl = document.getElementById('modalNunDesc');
    const rangeEl = document.getElementById('modalNunRange');
    const iconEl = document.getElementById('modalNunIcon');
    if (titleEl) titleEl.textContent = title;
    if (descEl) descEl.textContent = desc;
    if (rangeEl) rangeEl.textContent = 'Zakres: ' + range;
    if (iconEl) {
        iconEl.innerHTML = `<i class="bi ${icon}"></i>`;
        iconEl.style.backgroundColor = color;
    }
    const modalEl = document.getElementById('nunCodexModal');
    if (modalEl && window.bootstrap) {
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }
}
</script>
</body>
</html>



