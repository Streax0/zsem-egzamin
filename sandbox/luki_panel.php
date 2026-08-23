<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$currentRole = (string)($stmt->fetchColumn() ?: ($_SESSION['role'] ?? ''));
$_SESSION['role'] = $currentRole;

if (!in_array($currentRole, ['admin', 'wujek_luki'], true)) {
    setSessionMessage('error', 'Panel Lukiego jest dostępny tylko dla administratora i kont ze statusem Wujek Luki.');
    redirect('../index.php');
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

function lukiPickOutcome(PDO $pdo, int $userId, int $currentXp, bool $riskMode = false): array {
    $roll = random_int(1, 10000);
    $outcome = [];

    if ($roll === 1 && $currentXp > 0) {
        $outcome = ['archetype' => 'void', 'label' => 'Zakonnica Nicości', 'xp' => -$currentXp, 'note' => 'Sekretny wyrok nicości: cały aktualny XP został pochłonięty.'];
    } elseif ($roll <= 2000) {
        $xp = [50, 100, 250][random_int(0, 2)];
        $outcome = ['archetype' => 'blessing', 'label' => 'Zakonnica Błogosławieństwa', 'xp' => $xp, 'note' => 'Spokojny powiew łaski. System dopisał bonus XP.'];
    } elseif ($roll <= 3400) {
        $xp = [300, 500, 750][random_int(0, 2)];
        $outcome = ['archetype' => 'abundance', 'label' => 'Zakonnica Obfitości', 'xp' => $xp, 'note' => 'Złoty dar obfitości! Potężny zastrzyk punktów XP.'];
    } elseif ($roll <= 4600) {
        $xp = [150, 250, 350][random_int(0, 2)];
        $outcome = ['archetype' => 'grace', 'label' => 'Zakonnica Łaski', 'xp' => $xp, 'note' => 'Święta łaska przyznała umiarkowany zastrzyk doświadczenia.'];
    } elseif ($roll <= 5800) {
        $xp = [0, 50, 100][random_int(0, 2)];
        $outcome = ['archetype' => 'ciaza', 'label' => 'Zakonnica Ciąży', 'xp' => $xp, 'note' => 'Opiekuńczy uścisk: bezpieczny spin z drobnym plusem lub stabilizacją.'];
    } elseif ($roll <= 7000) {
        $outcome = ['archetype' => 'silence', 'label' => 'Zakonnica Ciszy', 'xp' => 0, 'note' => 'System pozostał w idealnej harmonii. Brak zmiany punktów XP.'];
    } elseif ($roll <= 8200) {
        $xp = -[20, 50, 100][random_int(0, 2)];
        $outcome = ['archetype' => 'trial', 'label' => 'Zakonnica Próby', 'xp' => $xp, 'note' => 'Drobna próba charakteru. Spadek niewielkiej ilości XP.'];
    } elseif ($roll <= 9000) {
        $xp = -[150, 300, 500][random_int(0, 2)];
        $outcome = ['archetype' => 'judge', 'label' => 'Zakonnica Sędzi', 'xp' => $xp, 'note' => 'Surowy wyrok trybunału. Odczuwalna utrata punktów XP.'];
    } elseif ($roll <= 9600) {
        $xp = [-200, -100, 0, 100, 200, 400][random_int(0, 5)];
        $outcome = ['archetype' => 'fate', 'label' => 'Zakonnica Przeznaczenia', 'xp' => $xp, 'note' => 'Przeznaczenie zatańczyło: los przyniósł nieprzewidywalny obrót spraw.'];
    } elseif ($roll <= 9750) {
        $xp = [120, 180, 240, 320][random_int(0, 3)];
        $outcome = ['archetype' => 'forge', 'label' => 'Zakonnica Kuźni', 'xp' => $xp, 'note' => 'Święta Kuźnia wykuła solidny bonus punktowy.'];
    } elseif ($roll <= 9870) {
        $xp = [-220, -120, 120, 220][random_int(0, 3)];
        $outcome = ['archetype' => 'mirror', 'label' => 'Zakonnica Lustra', 'xp' => $xp, 'note' => 'Lustro odbiło los: symetryczny, zdecydowany werdykt.'];
    } elseif ($roll <= 9950) {
        $xp = [0, 90, 180][random_int(0, 2)];
        $outcome = ['archetype' => 'archive', 'label' => 'Zakonnica Archiwum', 'xp' => $xp, 'note' => 'Starożytne księgi archiwum zachowały stabilność i przyznały wiedzę.'];
    } else {
        $stmt = $pdo->prepare("SELECT xp_delta FROM luki_spins WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $previous = $stmt->fetchColumn();
        $twist = random_int(1, 4);
        if ($previous !== false && $twist === 1) {
            $xp = (int)$previous;
            $note = 'Wyrocznia skopiowała poprzedni wynik losowania.';
        } elseif ($previous !== false && $twist === 2) {
            $xp = max(-750, min(750, (int)$previous * -1));
            $note = 'Wyrocznia odwróciła Twój poprzedni wynik.';
        } elseif ($previous !== false && $twist === 3) {
            $xp = max(-750, min(750, (int)$previous * 2));
            $note = 'Wyrocznia podwoiła poprzedni wynik.';
        } else {
            $xp = [-250, -100, 0, 100, 250, 500][random_int(0, 5)];
            $note = 'Wyrocznia rzuciła całkowicie mistyczny werdykt.';
        }
        $outcome = ['archetype' => 'oracle', 'label' => 'Zakonnica Losu', 'xp' => $xp, 'note' => $note];
    }

    if ($riskMode) {
        if ($outcome['xp'] !== 0 && $outcome['archetype'] !== 'void') {
            $outcome['xp'] *= 2;
        }
        $outcome['note'] = '[SPIN RYZYKA x2] ' . $outcome['note'];
    }

    return $outcome;
}

function lukiSegments(): array {
    return [
        ['key' => 'blessing', 'name' => 'Błogosławieństwo', 'type' => 'blessing', 'rarity' => 'Pospolita', 'chance' => '20%', 'range' => '+50 do +250 XP', 'icon' => 'bi-plus-circle-fill', 'color' => '#22c55e', 'desc' => 'Dobra i łaskawa zakonnica obdarzająca pewnym, stabilnym zastrzykiem doświadczenia bez żadnego ryzyka.'],
        ['key' => 'abundance', 'name' => 'Obfitość', 'type' => 'blessing', 'rarity' => 'Rzadka', 'chance' => '14%', 'range' => '+300 do +750 XP', 'icon' => 'bi-gem', 'color' => '#f59e0b', 'desc' => 'Złoty dar Świątyni. Przynosi potężną dawkę punktów doświadczenia dla najbardziej zasłużonych.'],
        ['key' => 'grace', 'name' => 'Łaska', 'type' => 'blessing', 'rarity' => 'Pospolita', 'chance' => '12%', 'range' => '+150 do +350 XP', 'icon' => 'bi-heart-fill', 'color' => '#8b5cf6', 'desc' => 'Święta opieka przynosząca umiarkowane i pewne nagrody punktowe.'],
        ['key' => 'ciaza', 'name' => 'Ciąża', 'type' => 'blessing', 'rarity' => 'Pospolita', 'chance' => '12%', 'range' => '0 do +100 XP', 'icon' => 'bi-heart-pulse', 'color' => '#ec4899', 'desc' => 'Bezpieczny i opiekuńczy stan stabilizujący Twój profil oraz dający lekki bonus.'],
        ['key' => 'silence', 'name' => 'Cisza', 'type' => 'neutral', 'rarity' => 'Neutralna', 'chance' => '12%', 'range' => '0 XP', 'icon' => 'bi-volume-mute-fill', 'color' => '#94a3b8', 'desc' => 'Święte milczenie i równowaga kosmiczna. Brak zysku i brak strat.'],
        ['key' => 'trial', 'name' => 'Próba', 'type' => 'trial', 'rarity' => 'Próba', 'chance' => '12%', 'range' => '-20 do -100 XP', 'icon' => 'bi-dash-circle-fill', 'color' => '#ef4444', 'desc' => 'Drobna próba wiary i cierpliwości z lekkim ubytkiem punktów.'],
        ['key' => 'judge', 'name' => 'Sędzia', 'type' => 'trial', 'rarity' => 'Wyrok', 'chance' => '8%', 'range' => '-150 do -500 XP', 'icon' => 'bi-exclamation-triangle-fill', 'color' => '#a21caf', 'desc' => 'Surowy trybunał zakonny egzekwujący kary punktowe za nadmierne ryzyko.'],
        ['key' => 'fate', 'name' => 'Przeznaczenie', 'type' => 'mystic', 'rarity' => 'Mistyczna', 'chance' => '6%', 'range' => '-200 do +400 XP', 'icon' => 'bi-shuffle', 'color' => '#0ea5e9', 'desc' => 'Koło fortuny w kole fortuny. Wynik może być zarówno łaskawy, jak i srogi.'],
        ['key' => 'forge', 'name' => 'Kuźnia', 'type' => 'blessing', 'rarity' => 'Rzadka', 'chance' => '1.5%', 'range' => '+120 do +320 XP', 'icon' => 'bi-hammer', 'color' => '#f97316', 'desc' => 'Święte kowadło hartujące Twój profil z gwarantowanym zyskiem.'],
        ['key' => 'mirror', 'name' => 'Lustro', 'type' => 'mystic', 'rarity' => 'Mistyczna', 'chance' => '1.2%', 'range' => '-220 do +220 XP', 'icon' => 'bi-symmetry-horizontal', 'color' => '#14b8a6', 'desc' => 'Zwierciadło prawdy odbijające los z idealną symetrią.'],
        ['key' => 'archive', 'name' => 'Archiwum', 'type' => 'blessing', 'rarity' => 'Rzadka', 'chance' => '0.8%', 'range' => '0 do +180 XP', 'icon' => 'bi-archive-fill', 'color' => '#6366f1', 'desc' => 'Zapomniana wiedza minionych pokoleń przynosząca stabilny zysk.'],
        ['key' => 'oracle', 'name' => 'Los', 'type' => 'mystic', 'rarity' => 'Epicka', 'chance' => '0.5%', 'range' => 'Kopiowanie / Odwracanie', 'icon' => 'bi-bullseye', 'color' => '#06b6d4', 'desc' => 'Wyrocznia potrafiąca odwrócić lub podwoić Twój poprzedni wynik.'],
        ['key' => 'void', 'name' => 'Nicość', 'type' => 'mystic', 'rarity' => 'Legendarna', 'chance' => '0.01%', 'range' => 'Zerowanie XP', 'icon' => 'bi-moon-stars-fill', 'color' => '#020617', 'desc' => 'Czarna dziura Zakonnicomatu pochłaniająca cały zgromadzony dorobek punktowy. Szansa wystąpienia wynosi 1 na 10 000.']
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
    securityApplyJsonHeaders();
    echo securityJsonEncode($payload);
    exit;
}

function lukiSpinResponsePayload(array $outcome, int $spinId, int $resultIndex, int $spinsLeft, string $spinsDisplay, int $updatedXp, array $rankInfo, string $createdAt): array {
    return [
        'success' => true,
        'result' => [
            'id' => $spinId,
            'index' => $resultIndex,
            'archetype' => (string)$outcome['archetype'],
            'label' => (string)$outcome['label'],
            'xp' => (int)$outcome['xp'],
            'note' => (string)$outcome['note'],
            'created_at' => $createdAt,
        ],
        'state' => [
            'current_xp' => $updatedXp,
            'rank' => (string)($rankInfo['name'] ?? ''),
            'spins_left' => $spinsLeft,
            'spins_display' => $spinsDisplay,
            'last_spin_at' => date('d.m H:i', strtotime($createdAt)),
        ],
    ];
}

$segments = lukiSegments();
$segmentCount = count($segments);

// Generate exact 0deg-based conic-gradient stops
$wheelGradientStops = [];
foreach ($segments as $i => $segment) {
    $start = ($i * 360) / $segmentCount;
    $end = (($i + 1) * 360) / $segmentCount;
    $wheelGradientStops[] = sprintf('%s %.4fdeg %.4fdeg', htmlspecialchars($segment['color']), $start, $end);
}
$wheelGradient = implode(",\n                ", $wheelGradientStops);

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
            lukiJsonResponse(['success' => false, 'message' => 'Błąd bezpieczeństwa CSRF. Odśwież stronę.'], 403);
        }
        setSessionMessage('error', 'Błąd bezpieczeństwa CSRF.');
        redirect('luki_panel.php');
    }

    $isRiskMode = !empty($_POST['risk_mode']);

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
        
        $outcome = lukiPickOutcome($pdo, $userId, $lockedXp, $isRiskMode);
        
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

$stmt = $pdo->prepare("SELECT id, user_id, spin_date, archetype, label, xp_delta, note, created_at FROM luki_spins WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
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
$riskScore = min(100, max(10, (int)($weeklySpinCount * 14 + ($weeklyBalance < 0 ? min(50, abs($weeklyBalance) / 10) : 10))));

$luckBalance = array_sum(array_map(fn($row) => (int)$row['xp_delta'], $history));
$luckTrend = $luckBalance > 0 ? 'Szczęście sprzyja (+XP)' : ($luckBalance < 0 ? 'Próba cierpliwości (-XP)' : 'Idealna równowaga');

$resultIndex = $spinResult ? lukiOutcomeIndex($segments, (string)$spinResult['archetype']) : 0;

$pageTitle = 'Świątynia Zakonnicomat - Panel Wujka Lukiego';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/luki_panel.css'];
include '../includes/header.php';
?>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        
        <main role="main" class="content-body">
            <div class="container-fluid p-3 p-md-4 luki-shell">
                <?php if ($flashMsg): ?>
                    <div class="alert alert-<?php echo $flashMsg['type'] === 'error' ? 'danger' : 'success'; ?> border-0 shadow-sm rounded-4 mb-4">
                        <?php echo htmlspecialchars($flashMsg['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Hero Section -->
                <section class="luki-hero mb-4">
                    <div class="luki-hero-copy">
                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                            <span class="badge rounded-pill bg-warning text-dark fw-bold px-3 py-1">
                                <i class="bi bi-patch-check-fill me-1"></i>Zakonnicomat v3.5
                            </span>
                            <button type="button" class="luki-audio-toggle ms-auto" id="lukiAudioToggle" aria-label="Przełącz dźwięk Zakonnicomatu">
                                <i class="bi bi-volume-up-fill me-1" id="lukiAudioIcon"></i><span id="lukiAudioStatus">Dźwięk: Wł.</span>
                            </button>
                        </div>
                        <h1 class="fw-bold mb-2 display-6">
                            <i class="bi bi-stars text-warning me-2"></i>Świątynia Zakonnicomat
                        </h1>
                        <p class="lead opacity-75 mb-0 fs-6">
                            Elitarny moduł losujący dla kont ze statusem <strong>Wujek Luki</strong> i <strong>Administrator</strong>. Zakręć kołem 13 Świętych Zakonnic, testuj swoje szczęście i pomnażaj punkty XP.
                        </p>
                    </div>
                    <div class="luki-hero-art">
                        <img class="luki-sign" src="assets/images/luki-zakonnicomat-sign.svg" alt="Szyld maszyny losującej Wujka Lukiego" loading="lazy" decoding="async">
                        <img class="luki-mascot" src="assets/images/luki-zakonnica.svg" alt="" aria-hidden="true" loading="lazy" decoding="async">
                    </div>
                </section>

                <div class="luki-grid">
                    
                    <!-- Left: Spin Wheel Area -->
                    <section class="luki-spin-card p-4">
                        <div class="wheel-wrap">
                            <div class="wheel-stage" id="wheelStage">
                                <canvas id="lukiConfettiCanvas"></canvas>
                                
                                <!-- Mechanical Pointer at 12 o'clock (0deg) -->
                                <div class="wheel-pointer-assembly" id="wheelPointerAssembly">
                                    <div class="wheel-pointer-pin"></div>
                                    <div class="wheel-pointer-needle" id="wheelPointerNeedle"></div>
                                </div>

                                <!-- The Wheel (conic gradient starts at 0deg) -->
                                <div class="luki-wheel" id="lukiWheel" data-segments="<?php echo count($segments); ?>" style="background: conic-gradient(from 0deg, <?php echo $wheelGradient; ?>);">
                                    <div class="center-mark">
                                        <i class="bi bi-shield-shaded"></i>
                                    </div>
                                    <?php foreach ($segments as $i => $segment): ?>
                                        <?php 
                                        // Exact angle bisector of segment i
                                        $angle = ($i * (360 / $segmentCount)) + (180 / $segmentCount); 
                                        ?>
                                        <div class="wheel-segment-label" style="--angle: <?php echo sprintf('%.4f', $angle); ?>deg; --segment-color: <?php echo htmlspecialchars($segment['color']); ?>;">
                                            <i class="bi <?php echo htmlspecialchars($segment['icon']); ?>"></i>
                                            <span><?php echo htmlspecialchars($segment['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Spin Controls -->
                            <form method="POST" action="luki_panel.php" class="text-center spin-actions mt-2" data-luki-spin-form>
                                <?php echo csrfTokenField(); ?>
                                
                                <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-3 px-3 py-2 rounded-pill bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="riskModeSwitch" name="risk_mode" value="1" style="cursor:pointer;">
                                    <label class="form-check-label fw-bold small text-warning mb-0" for="riskModeSwitch" style="cursor:pointer;">
                                        <i class="bi bi-fire me-1"></i>Tryb Ryzyka: Podwójna Stawka (x2 XP)
                                    </label>
                                </div>

                                <div>
                                    <button class="btn btn-spin-main btn-lg rounded-pill px-5 py-3 fw-bold fs-5 shadow" data-luki-spin-button <?php echo $spinsLeft <= 0 ? 'disabled' : ''; ?>>
                                        <i class="bi bi-arrow-repeat me-2"></i>Spin Zakonnicomatem
                                    </button>
                                </div>
                                
                                <div class="small text-muted mt-2">
                                    Pozostało dziś spinów: <strong data-spins-left class="text-body fw-bold"><?php echo htmlspecialchars($spinsDisplay); ?></strong>
                                </div>
                                <div class="small mt-2 d-none" data-luki-spin-alert aria-live="polite"></div>
                            </form>

                            <!-- Result Card Mount (Mobile) -->
                            <div id="spinResultMobileMount" data-spin-result-mount class="mt-3 w-100 d-md-none">
                                <?php if ($spinResult): ?>
                                <div class="result-card pending-reveal <?php echo htmlspecialchars($spinResult['archetype']); ?>" data-index="<?php echo (int)$resultIndex; ?>" data-delta="<?php echo (int)$spinResult['xp']; ?>">
                                    <div class="small text-muted fw-bold text-uppercase">Wynik spinu</div>
                                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($spinResult['label']); ?></h4>
                                    <div class="display-6 fw-bold <?php echo (int)$spinResult['xp'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo (int)$spinResult['xp'] > 0 ? '+' : ''; ?><?php echo (int)$spinResult['xp']; ?> XP
                                    </div>
                                    <p class="mb-0 text-muted small"><?php echo htmlspecialchars($spinResult['note']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </section>

                    <!-- Right Column: Stats, Result, History -->
                    <div class="d-flex flex-column gap-3">
                        
                        <!-- Result Card Mount (Desktop) -->
                        <div id="spinResultDesktopMount" data-spin-result-mount class="d-none d-md-block">
                            <?php if ($spinResult): ?>
                            <div class="result-card pending-reveal <?php echo htmlspecialchars($spinResult['archetype']); ?>" data-index="<?php echo (int)$resultIndex; ?>" data-delta="<?php echo (int)$spinResult['xp']; ?>">
                                <div class="small text-muted fw-bold text-uppercase">Wynik spinu</div>
                                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($spinResult['label']); ?></h4>
                                <div class="display-6 fw-bold <?php echo (int)$spinResult['xp'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo (int)$spinResult['xp'] > 0 ? '+' : ''; ?><?php echo (int)$spinResult['xp']; ?> XP
                                </div>
                                <p class="mb-0 text-muted small"><?php echo htmlspecialchars($spinResult['note']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Status & Stats Card -->
                        <div class="luki-card p-3 p-md-4">
                            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-person-badge-fill text-primary"></i>Twój Profil Zakonnicomatu
                            </h2>
                            
                            <div class="status-row">
                                <span class="text-muted">Aktualny XP:</span>
                                <strong data-luki-xp class="text-warning"><?php echo number_format($currentXp); ?></strong>
                            </div>
                            <div class="status-row">
                                <span class="text-muted">Ranga:</span>
                                <strong data-luki-rank class="text-primary"><?php echo htmlspecialchars($rankInfo['name'] ?? 'Początkujący'); ?></strong>
                            </div>
                            <div class="status-row">
                                <span class="text-muted">Limit spinów:</span>
                                <strong><?php echo $isAdmin ? 'Bez limitu (Admin)' : '2 / dzień'; ?></strong>
                            </div>
                            <div class="status-row">
                                <span class="text-muted">Testy rozwiązane dziś:</span>
                                <strong><?php echo $activity['tests_today']; ?></strong>
                            </div>
                            <div class="status-row">
                                <span class="text-muted">Seria aktywności (Streak):</span>
                                <strong class="text-success"><i class="bi bi-fire me-1"></i><?php echo $activity['streak']; ?> dni</strong>
                            </div>
                            <div class="status-row">
                                <span class="text-muted">Ostatni spin:</span>
                                <strong data-luki-last-spin><?php echo htmlspecialchars($lastSpinAt); ?></strong>
                            </div>

                            <hr class="my-3">

                            <h3 class="h6 fw-bold mb-2 d-flex align-items-center gap-2">
                                <i class="bi bi-graph-up-arrow text-success"></i>Trend Szczęścia (7 dni)
                            </h3>
                            <div class="trend-grid mb-3">
                                <div class="trend-box">
                                    <div class="small text-muted">Spiny</div>
                                    <strong class="fs-6"><?php echo $weeklySpinCount; ?></strong>
                                </div>
                                <div class="trend-box">
                                    <div class="small text-muted">Bilans XP</div>
                                    <strong class="fs-6 <?php echo $weeklyBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $weeklyBalance > 0 ? '+' : ''; ?><?php echo $weeklyBalance; ?>
                                    </strong>
                                </div>
                                <div class="trend-box">
                                    <div class="small text-muted">Najlepszy</div>
                                    <strong class="fs-6 text-success">+<?php echo $weeklyBest; ?></strong>
                                </div>
                            </div>
                            <div class="small text-muted d-flex align-items-center gap-1 mb-2">
                                <i class="bi bi-activity text-info"></i> Stan: <span class="fw-semibold text-body"><?php echo htmlspecialchars($luckTrend); ?></span>
                            </div>
                            <div class="luki-risk-meter pt-2 border-top" data-luki-risk-meter>
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span><i class="bi bi-shield-exclamation text-warning me-1"></i>Tydzień losu (Wskaźnik Ryzyka):</span>
                                    <strong class="text-body"><?php echo $riskScore; ?>%</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $riskScore; ?>%;" aria-valuenow="<?php echo $riskScore; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>

                        <!-- History / Chronicle Card -->
                        <div class="luki-card p-3 p-md-4">
                            <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-clock-history text-primary"></i>Kronika Ostatnich Spinów
                            </h2>
                            <div data-luki-history class="d-flex flex-column" style="max-height: 280px; overflow-y: auto;">
                                <?php if (empty($history)): ?>
                                    <div class="text-muted small py-2" data-luki-history-empty>Brak zarejestrowanych spinów. Zakręć kołem po raz pierwszy!</div>
                                <?php else: ?>
                                    <?php foreach ($history as $spin): ?>
                                        <div class="chronicle-item" data-luki-history-entry="1">
                                            <div>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($spin['label']); ?></div>
                                                <div class="small text-muted" style="font-size:.74rem;"><?php echo htmlspecialchars($spin['note'] ?? ''); ?></div>
                                            </div>
                                            <div class="text-end flex-shrink-0">
                                                <div class="fw-bold small <?php echo (int)$spin['xp_delta'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo (int)$spin['xp_delta'] > 0 ? '+' : ''; ?><?php echo (int)$spin['xp_delta']; ?> XP
                                                </div>
                                                <div class="small text-muted" style="font-size:.7rem;"><?php echo date('d.m H:i', strtotime($spin['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Tarot Codex Section -->
                <section class="mt-4 pt-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h2 class="h4 fw-bold mb-1 d-flex align-items-center gap-2">
                                <i class="bi bi-book-half text-warning"></i>Kodeks 13 Świętych Zakonnic
                            </h2>
                            <p class="text-muted small mb-0">Kliknij w kartę, aby poznać pełną historię, szansę wylosowania i zakres punktów każdej postaci.</p>
                        </div>
                        <div class="d-flex gap-1 flex-wrap" id="codexFilters">
                            <button type="button" class="codex-filter-btn active" data-filter="all">Wszystkie (13)</button>
                            <button type="button" class="codex-filter-btn" data-filter="blessing">Błogosławieństwa</button>
                            <button type="button" class="codex-filter-btn" data-filter="trial">Próby i Wyroki</button>
                            <button type="button" class="codex-filter-btn" data-filter="mystic">Mistyczne</button>
                        </div>
                    </div>

                    <div class="nun-codex-grid" id="nunCodexGrid">
                        <?php foreach ($segments as $nun): ?>
                            <div class="nun-card" data-nun-type="<?php echo htmlspecialchars($nun['type']); ?>" style="--nun-color: <?php echo htmlspecialchars($nun['color']); ?>;" onclick="openNunCodexModal('<?php echo htmlspecialchars($nun['key']); ?>', '<?php echo htmlspecialchars($nun['name']); ?>', '<?php echo htmlspecialchars($nun['desc']); ?>', '<?php echo htmlspecialchars($nun['range']); ?>', '<?php echo htmlspecialchars($nun['chance']); ?>', '<?php echo htmlspecialchars($nun['rarity']); ?>', '<?php echo htmlspecialchars($nun['color']); ?>', '<?php echo htmlspecialchars($nun['icon']); ?>');">
                                <div class="nun-card-icon">
                                    <i class="bi <?php echo htmlspecialchars($nun['icon']); ?>"></i>
                                </div>
                                <div class="nun-card-type"><?php echo htmlspecialchars($nun['rarity']); ?> • <?php echo htmlspecialchars($nun['chance']); ?></div>
                                <div class="nun-card-title"><?php echo htmlspecialchars($nun['name']); ?></div>
                                <div class="nun-card-range text-body"><?php echo htmlspecialchars($nun['range']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<!-- Modal Tarot Card Codex Detail -->
<div class="modal fade" id="nunCodexModal" tabindex="-1" aria-labelledby="modalNunTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4 pb-0">
                <h3 class="modal-title h5 fw-bold d-flex align-items-center gap-2" id="modalNunTitle">
                    Nazwa Zakonnicy
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div id="modalNunIcon" class="rounded-4 d-grid place-items-center text-white flex-shrink-0" style="width: 60px; height: 60px; font-size: 1.8rem; background: #6366f1;">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div>
                        <div class="badge rounded-pill px-3 py-1 mb-1" id="modalNunBadge" style="background: rgba(99,102,241,0.2); color:#6366f1;">Rzadkość</div>
                        <div class="small fw-bold text-muted" id="modalNunChance">Szansa: 10%</div>
                    </div>
                </div>
                <div class="p-3 rounded-3 mb-3" style="background: rgba(148, 163, 184, 0.08);">
                    <div class="small text-muted fw-bold mb-1">Zakres Punktów XP:</div>
                    <div class="fw-bold fs-6 text-primary" id="modalNunRange">+100 do +300 XP</div>
                </div>
                <div>
                    <div class="small text-muted fw-bold mb-1">Inskrypcja Zakonna:</div>
                    <p class="small text-body mb-0" id="modalNunDesc">Opis zakonnicy...</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-body-tertiary">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

<!-- Web Audio API & Wheel Kinetic Physics Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const wheel = document.getElementById('lukiWheel');
    const needle = document.getElementById('wheelPointerNeedle');
    const form = document.querySelector('[data-luki-spin-form]');
    const button = document.querySelector('[data-luki-spin-button]');
    const alertBox = document.querySelector('[data-luki-spin-alert]');
    const resultMounts = Array.from(document.querySelectorAll('[data-spin-result-mount]'));
    const audioToggle = document.getElementById('lukiAudioToggle');
    const audioStatus = document.getElementById('lukiAudioStatus');
    const audioIcon = document.getElementById('lukiAudioIcon');
    const confettiCanvas = document.getElementById('lukiConfettiCanvas');

    let audioEnabled = localStorage.getItem('zsem_luki_audio') !== 'false';
    let audioCtx = null;
    let currentRotation = 0;

    const updateAudioToggleUI = () => {
        if (audioEnabled) {
            audioToggle?.classList.remove('is-muted');
            if (audioStatus) audioStatus.textContent = 'Dźwięk: Wł.';
            if (audioIcon) audioIcon.className = 'bi bi-volume-up-fill me-1';
        } else {
            audioToggle?.classList.add('is-muted');
            if (audioStatus) audioStatus.textContent = 'Dźwięk: Wyciszony';
            if (audioIcon) audioIcon.className = 'bi bi-volume-mute-fill me-1';
        }
    };
    updateAudioToggleUI();

    audioToggle?.addEventListener('click', () => {
        audioEnabled = !audioEnabled;
        localStorage.setItem('zsem_luki_audio', audioEnabled ? 'true' : 'false');
        updateAudioToggleUI();
    });

    const initAudio = () => {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) audioCtx = new AudioContextClass();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    };

    // Synthesize realistic clicking sound
    const playTickSound = () => {
        if (!audioEnabled) return;
        initAudio();
        if (!audioCtx) return;
        try {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(880, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(110, audioCtx.currentTime + 0.025);
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.025);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.025);
        } catch (e) {}
    };

    // Synthesize victory fanfare or trial tone
    const playOutcomeSound = (delta) => {
        if (!audioEnabled) return;
        initAudio();
        if (!audioCtx) return;
        try {
            if (delta > 0) {
                // Triumphant chord arpeggio (C5, E5, G5, C6)
                const notes = delta >= 300 ? [523.25, 659.25, 783.99, 1046.50] : [523.25, 659.25, 783.99];
                notes.forEach((freq, i) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    const startTime = audioCtx.currentTime + i * 0.1;
                    gain.gain.setValueAtTime(0.001, startTime);
                    gain.gain.linearRampToValueAtTime(0.25, startTime + 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.5);
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(startTime);
                    osc.stop(startTime + 0.55);
                });
            } else if (delta < 0) {
                // Low trial drone
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(130, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(55, audioCtx.currentTime + 0.6);
                gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.65);
            }
        } catch (e) {}
    };

    // ── Confetti Particle Physics ──
    const launchConfetti = () => {
        if (!confettiCanvas) return;
        const ctx = confettiCanvas.getContext('2d');
        const width = confettiCanvas.width = confettiCanvas.clientWidth || 600;
        const height = confettiCanvas.height = confettiCanvas.clientHeight || 600;
        const particles = [];
        const colors = ['#f59e0b', '#ec4899', '#22c55e', '#3b82f6', '#8b5cf6', '#eab308'];

        for (let i = 0; i < 90; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = Math.random() * 12 + 4;
            particles.push({
                x: width / 2,
                y: height / 2,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed - 3,
                size: Math.random() * 8 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                rotation: Math.random() * 360,
                rotSpeed: (Math.random() - 0.5) * 12,
                life: 1,
                decay: Math.random() * 0.015 + 0.01
            });
        }

        let animationFrame;
        const render = () => {
            ctx.clearRect(0, 0, width, height);
            let alive = 0;
            particles.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.25; // gravity
                p.vx *= 0.98; // friction
                p.rotation += p.rotSpeed;
                p.life -= p.decay;
                if (p.life > 0) {
                    alive++;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate((p.rotation * Math.PI) / 180);
                    ctx.fillStyle = p.color;
                    ctx.globalAlpha = Math.max(0, p.life);
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                    ctx.restore();
                }
            });
            if (alive > 0) {
                animationFrame = requestAnimationFrame(render);
            } else {
                ctx.clearRect(0, 0, width, height);
            }
        };
        render();
    };

    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    };

    const signedXp = (xp) => {
        const val = Number(xp || 0);
        return (val > 0 ? '+' : '') + val + ' XP';
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
            <h4 class="fw-bold mb-1">${escapeHtml(result?.label || '')}</h4>
            <div class="display-6 fw-bold ${toneClass}">${signedXp(delta)}</div>
            <p class="mb-0 text-muted small">${escapeHtml(result?.note || '')}</p>
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
        entry.innerHTML = `<div>
            <div class="fw-bold small">${escapeHtml(result.label)}</div>
            <div class="small text-muted" style="font-size:.74rem;">${escapeHtml(result.note)}</div>
        </div>
        <div class="text-end flex-shrink-0">
            <div class="fw-bold small ${delta >= 0 ? 'text-success' : 'text-danger'}">${signedXp(delta)}</div>
            <div class="small text-muted" style="font-size:.7rem;">teraz</div>
        </div>`;
        history.prepend(entry);
        history.querySelectorAll('[data-luki-history-entry]').forEach((item, idx) => {
            if (idx >= 10) item.remove();
        });
    };

    // ── Exact Wheel Rotation Physics ──
    const playSpinResult = (resultCard) => {
        if (!resultCard) return;
        if (!wheel) {
            resultCard.classList.add('is-visible');
            return;
        }

        const segmentCount = Number(wheel.dataset.segments || 13);
        const index = Number(resultCard.dataset.index || 0);
        const segmentAngle = 360 / segmentCount;
        
        // Exact angle bisector of target segment (starting from 0deg North)
        const segmentCenter = index * segmentAngle + segmentAngle / 2;
        
        // To align segmentCenter with 0deg pointer:
        const desired = (360 - (segmentCenter % 360)) % 360;
        const normalized = ((currentRotation % 360) + 360) % 360;
        const deltaAngle = (desired - normalized + 360) % 360;
        const extraRotations = 360 * (6 + Math.floor(Math.random() * 2));
        
        currentRotation += extraRotations + deltaAngle;

        wheel.classList.add('is-spinning');
        needle?.classList.add('is-ticking');

        // Dynamic tick frequency slowing down
        let tickInterval;
        let tickDelay = 70;
        const scheduleTick = () => {
            playTickSound();
            tickDelay = Math.min(380, tickDelay * 1.07);
            tickInterval = setTimeout(scheduleTick, tickDelay);
        };
        scheduleTick();

        requestAnimationFrame(() => {
            wheel.style.setProperty('--rot', currentRotation + 'deg');
        });

        window.setTimeout(() => {
            clearTimeout(tickInterval);
            resultCard.classList.add('is-visible');
            wheel.classList.remove('is-spinning');
            needle?.classList.remove('is-ticking');

            const delta = Number(resultCard.dataset.delta || 0);
            playOutcomeSound(delta);
            if (delta >= 150) {
                launchConfetti();
            }
        }, 4850);
    };

    const initialResult = getVisibleResultCard();
    if (initialResult) playSpinResult(initialResult);

    // Codex Filters
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

    // Form Submit Handler
    if (!form || !button || !window.fetch) return;
    const defaultButtonHtml = button.innerHTML;

    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (button.disabled || button.classList.contains('is-busy')) return;
        setAlert('');
        initAudio();
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

function openNunCodexModal(key, name, desc, range, chance, rarity, color, icon) {
    const titleEl = document.getElementById('modalNunTitle');
    const descEl = document.getElementById('modalNunDesc');
    const rangeEl = document.getElementById('modalNunRange');
    const chanceEl = document.getElementById('modalNunChance');
    const badgeEl = document.getElementById('modalNunBadge');
    const iconEl = document.getElementById('modalNunIcon');

    if (titleEl) titleEl.textContent = name;
    if (descEl) descEl.textContent = desc;
    if (rangeEl) rangeEl.textContent = range;
    if (chanceEl) chanceEl.textContent = 'Szansa wylosowania: ' + chance;
    if (badgeEl) {
        badgeEl.textContent = rarity;
        badgeEl.style.backgroundColor = color + '25';
        badgeEl.style.color = color;
    }
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
