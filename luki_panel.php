<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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

$activity = lukiTodayActivity($pdo, $userId);
$dailySpinLimit = $isAdmin ? null : 2;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM luki_spins WHERE user_id = ? AND spin_date = ?");
$stmt->execute([$userId, $today]);
$spinsToday = (int)$stmt->fetchColumn();
$spinsLeft = $isAdmin ? PHP_INT_MAX : max(0, $dailySpinLimit - $spinsToday);
$spinsDisplay = $isAdmin ? '∞' : ((string)$spinsLeft . '/' . (string)$dailySpinLimit);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
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
            setSessionMessage('error', 'Brak dostępu do Zakonnicomatu.');
            redirect('luki_panel.php');
        }

        $isAdminSpin = $lockedRole === 'admin';
        if (!$isAdminSpin) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM luki_spins WHERE user_id = ? AND spin_date = ?");
            $stmt->execute([$userId, $today]);
            if ((int)$stmt->fetchColumn() >= 2) {
                $pdo->rollBack();
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
        $_SESSION['luki_last_spin'] = $outcome + ['id' => $spinId];
        redirect('luki_panel.php?spin=1');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Luki spin failed: ' . $e->getMessage());
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
$resultIndex = $spinResult ? array_search($spinResult['archetype'], array_column($segments, 'key'), true) : 0;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zakonnicomat - Panel Lukiego</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .luki-shell { max-width: 1360px; margin: 0 auto; }
        .content-body {
            background:
                radial-gradient(circle at 20% 10%, rgba(245, 158, 11, .10), transparent 34%),
                radial-gradient(circle at 90% 18%, rgba(99, 102, 241, .12), transparent 34%),
                #eef3f8;
        }
        .luki-hero {
            position: relative;
            overflow: hidden;
            min-height: 250px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 430px);
            gap: clamp(1rem, 3vw, 2.5rem);
            align-items: center;
            border-radius: 34px;
            padding: clamp(1.35rem, 3vw, 2.75rem);
            color: #fff;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, .96), rgba(49, 17, 96, .96) 55%, rgba(15, 23, 42, .98)),
                radial-gradient(circle at 16% 12%, rgba(255,255,255,.28), transparent 30%);
            box-shadow: 0 26px 70px rgba(17, 24, 39, .22);
        }
        .luki-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(90deg, #000, transparent 78%);
            opacity: .35;
        }
        .luki-hero-copy, .luki-hero-art { position: relative; z-index: 1; }
        .luki-hero h1 { color: #fff; text-shadow: 0 10px 28px rgba(0,0,0,.28); }
        .luki-hero-art {
            display: flex;
            gap: clamp(.75rem, 2vw, 1.25rem);
            align-items: center;
            justify-content: flex-end;
            min-height: 200px;
        }
        .luki-sign {
            display: block;
            width: min(68%, 390px);
            max-width: 100%;
            filter: drop-shadow(0 18px 28px rgba(0,0,0,.38));
        }
        .luki-mascot {
            position: static;
            flex: 0 0 auto;
            width: min(30%, 150px);
            min-width: 96px;
            opacity: .98;
            filter: drop-shadow(0 20px 28px rgba(0,0,0,.28));
        }
        .luki-grid { display: grid; grid-template-columns: minmax(0, 1fr) 390px; gap: 1.5rem; align-items: start; }
        .wheel-wrap { display:flex; flex-direction:column; align-items:center; gap:1.15rem; }
        .wheel-stage {
            position: relative;
            width: min(720px, 92vw);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            margin-top: .15rem;
            background:
                radial-gradient(circle at 50% 50%, rgba(255,255,255,.24), transparent 28%),
                radial-gradient(circle at 50% 50%, rgba(15,23,42,.08), rgba(15,23,42,.14) 70%),
                rgba(15,23,42,.02);
            border-radius: 50%;
            padding: 1.8rem;
            box-shadow: 0 32px 90px rgba(15,23,42,.22);
            border: 1px solid rgba(255,255,255,.10);
            overflow: hidden;
        }
        .wheel-stage::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 50%, rgba(255,255,255,.12), transparent 30%);
            pointer-events: none;
        }
        .wheel-stage::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12), 0 0 80px rgba(34,197,94,.08), 0 0 120px rgba(59,130,246,.06);
            pointer-events: none;
            mix-blend-mode: screen;
        }
        .luki-wheel {
            --rot: 0deg;
            width: 100%;
            aspect-ratio: 1;
            border-radius: 50%;
            position: relative;
            background: conic-gradient(
                #22c55e 0 36deg,
                #f59e0b 36deg 72deg,
                #8b5cf6 72deg 108deg,
                #ec4899 108deg 144deg,
                #94a3b8 144deg 180deg,
                #ef4444 180deg 216deg,
                #a21caf 216deg 252deg,
                #0ea5e9 252deg 288deg,
                #06b6d4 288deg 324deg,
                #020617 324deg 360deg
            );
            transform: rotate(var(--rot));
            box-shadow:
                inset 0 0 0 12px rgba(255,255,255,.12),
                inset 0 0 0 32px rgba(15, 23, 42, .08),
                0 28px 90px rgba(15,23,42,.24);
            transition: transform 4.2s cubic-bezier(.12,.76,.16,1);
            overflow: hidden;
        }
        .luki-wheel::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background:
                radial-gradient(circle at 50% 50%, rgba(255,255,255,.16), transparent 34%),
                radial-gradient(circle at 26% 20%, rgba(255,255,255,.08), transparent 10%);
            pointer-events: none;
        }
        .luki-wheel::before {
            content: "";
            position: absolute;
            inset: 28%;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 50%, rgba(15,23,42,.98), rgba(15,23,42,.85) 58%);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.06), 0 16px 36px rgba(0,0,0,.2);
            z-index: 2;
        }
        .luki-wheel .center-mark {
            position: absolute;
            inset: 40%;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(15,23,42,.05);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.06), 0 0 0 16px rgba(0,0,0,.14);
            z-index: 3;
        }
        .luki-wheel .center-mark span { display: none; }
        .wheel-segment-label {
            position: absolute;
            left: 50%;
            top: 50%;
            z-index: 4;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-width: 108px;
            max-width: 128px;
            padding: .45rem .55rem;
            border-radius: 999px;
            color: #fff;
            background: linear-gradient(135deg, rgba(255,255,255,.24), rgba(255,255,255,.08)), var(--segment-color);
            border: 1px solid rgba(255,255,255,.26);
            font-size: .75rem;
            font-weight: 800;
            text-shadow: 0 1px 8px rgba(0,0,0,.22);
            transform: rotate(var(--angle)) translateX(76%) rotate(calc(-1 * var(--angle))) translate(-50%, -50%);
            pointer-events: none;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12), 0 10px 24px rgba(0,0,0,.18);
        }
        .wheel-segment-label i { color: rgba(255,255,255,.95); }
        .wheel-pointer {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 24px solid transparent;
            border-right: 24px solid transparent;
            border-bottom: 42px solid #ffffff;
            filter: drop-shadow(0 14px 22px rgba(0,0,0,.22));
            z-index: 5;
        }
        .wheel-pointer::after {
            content: "";
            position: absolute;
            top: -32px;
            left: -12px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 16px 28px rgba(0,0,0,.18);
        }
        .wheel-label {
            position: absolute;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            max-width: 160px;
            padding: .48rem .68rem;
            border-radius: 999px;
            color: #0f172a;
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(148,163,184,.28);
            font-weight: 850;
            font-size: .78rem;
            box-shadow: 0 12px 28px rgba(15,23,42,.14);
            backdrop-filter: blur(8px);
            white-space: nowrap;
            left: 50%;
            top: 50%;
            transform: rotate(var(--angle)) translateX(clamp(192px, 30vw, 305px)) rotate(calc(-1 * var(--angle))) translate(-50%, -50%);
        }
        .wheel-label i { color: var(--label-color); }
        .zakonnica-guard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: .65rem;
            margin: 1.3rem auto 0;
            max-width: 100%;
            place-items: center;
            position: relative;
            z-index: 2;
        }
        .zakonnica-guard {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem .75rem;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(255,255,255,.92), rgba(248,250,252,.84));
            border: 1px solid rgba(148,163,184,.22);
            color: #0f172a;
            font-size: .76rem;
            font-weight: 700;
            box-shadow: 0 16px 40px rgba(15,23,42,.10);
        }
        .zakonnica-guard i {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #0f172a;
            background: rgba(255,255,255,.95);
            box-shadow: inset 0 0 0 1px rgba(15,23,42,.06);
        }
        .zakonnica-guard small {
            display: inline-block;
            font-size: .65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #475569;
        }
        .result-card.pending-reveal {
            opacity: 0;
            transform: translateY(10px) scale(.98);
            pointer-events: none;
            transition: opacity .35s ease, transform .35s ease;
        }
        .result-card.pending-reveal.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .wheel-pointer {
            position: absolute;
            top: 8%;
            left: 50%;
            transform: translateX(-50%);
            width: 0; height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 38px solid #fff;
            filter: drop-shadow(0 8px 10px rgba(0,0,0,.24));
            z-index: 3;
        }
        .luki-spin-card, .luki-card {
            border-radius: 24px;
            background: #fff;
            border: 1px solid rgba(148,163,184,.22);
            box-shadow: 0 16px 45px rgba(15,23,42,.08);
        }
        .luki-spin-card {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(255,255,255,.95), rgba(255,255,255,.98)),
                radial-gradient(circle at 50% 28%, rgba(245,158,11,.16), transparent 34%);
        }
        .luki-spin-card::before {
            content: "";
            position: absolute;
            inset: 1rem;
            border-radius: 22px;
            border: 1px dashed rgba(148,163,184,.36);
            pointer-events: none;
        }
        .spin-actions { position: relative; z-index: 4; }
        .result-card {
            border-radius: 22px;
            padding: 1rem;
            border: 1px solid rgba(148,163,184,.22);
            background: linear-gradient(135deg, rgba(59,130,246,.08), rgba(139,92,246,.08));
        }
        .result-card.blessing { background: linear-gradient(135deg, rgba(34,197,94,.18), rgba(22,163,74,.08)); }
        .result-card.abundance { background: linear-gradient(135deg, rgba(245,158,11,.25), rgba(250,204,21,.1)); }
        .result-card.grace { background: linear-gradient(135deg, rgba(168,85,247,.18), rgba(167,139,250,.1)); }
        .result-card.ciaza { background: linear-gradient(135deg, rgba(236,72,153,.18), rgba(249,115,22,.14)); }
        .result-card.silence { background: linear-gradient(135deg, rgba(148,163,184,.22), rgba(100,116,139,.08)); }
        .result-card.trial { background: linear-gradient(135deg, rgba(239,68,68,.18), rgba(185,28,28,.08)); }
        .result-card.judge { animation: lukiShake .45s ease 2; background: linear-gradient(135deg, rgba(162,28,175,.24), rgba(239,68,68,.14)); }
        .result-card.fate { background: linear-gradient(135deg, rgba(14,165,233,.22), rgba(59,130,246,.14)); }
        .result-card.oracle { background: linear-gradient(135deg, rgba(6,182,212,.22), rgba(139,92,246,.18), rgba(245,158,11,.16)); }
        .result-card.void { animation: lukiShake .22s ease 5; background: linear-gradient(135deg, rgba(2,6,23,.92), rgba(88,28,135,.42)); color: #f8fafc; }
        .luki-motif-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            width: 100%;
            position: relative;
            z-index: 2;
        }
        .luki-motif {
            min-height: 96px;
            border-radius: 18px;
            padding: .9rem;
            background: linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.9));
            border: 1px solid rgba(148,163,184,.2);
            box-shadow: 0 12px 28px rgba(15,23,42,.06);
        }
        .luki-motif i { font-size: 1.35rem; color: var(--label-color); }
        @keyframes lukiShake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
        .chronicle-item { display:flex; justify-content:space-between; gap:1rem; padding:.85rem 0; border-bottom:1px solid rgba(148,163,184,.18); }
        .chronicle-item:last-child { border-bottom:0; }
        .trend-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:.75rem; }
        .trend-box { padding:.75rem; border-radius:16px; background:#f8fafc; text-align:center; }
        .luki-week-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:.75rem; }
        .luki-week-box { border-radius:16px; background:#f8fafc; padding:.85rem; }
        .luki-risk-meter { height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
        .luki-risk-meter span { display:block; height:100%; width: var(--risk); background: linear-gradient(90deg, #22c55e, #f59e0b, #ef4444); }
        .luki-legend {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .6rem;
            margin-top: 1rem;
            position: relative;
            z-index: 2;
        }
        .legend-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            min-height: 38px;
            padding: .45rem .55rem;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid rgba(148,163,184,.18);
            font-size: .76rem;
            font-weight: 800;
            color: #334155;
            text-align: center;
        }
        .legend-pill i { color: var(--label-color); }
        .status-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .85rem 0;
            border-bottom: 1px solid rgba(148,163,184,.2);
        }
        .status-row:last-child { border-bottom: 0; }
        body.dark-mode .luki-spin-card, body.dark-mode .luki-card { background:#1e293b; color:#e5e7eb; border-color:rgba(148,163,184,.24); }
        body.dark-mode .trend-box, body.dark-mode .luki-week-box { background:#0f172a; }
        body.dark-mode .legend-pill, body.dark-mode .wheel-label, body.dark-mode .luki-motif { background:#0f172a; color:#e5e7eb; border-color:rgba(148,163,184,.25); }
        @media (max-width: 1199.98px) { .luki-legend { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 991.98px) {
            .luki-grid, .luki-hero { grid-template-columns: 1fr; }
            .luki-hero-art { justify-content: center; }
            .luki-sign { width: min(70%, 360px); }
            .luki-mascot { width: min(24%, 120px); min-width: 84px; }
        }
        @media (max-width: 767.98px) {
            .luki-hero { border-radius: 22px; }
            .wheel-label { display: none; }
            .trend-grid, .luki-legend, .luki-motif-grid { grid-template-columns:1fr; }
            .zakonnica-guard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
            .zakonnica-guard { font-size: .68rem; padding: .4rem .55rem; }
            .wheel-segment-label { font-size: .55rem; max-width: 92px; padding: .25rem .34rem; }
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 luki-shell">
                <?php if ($flashMsg): ?>
                    <div class="alert alert-<?php echo $flashMsg['type'] === 'error' ? 'danger' : 'success'; ?> border-0 shadow-sm"><?php echo htmlspecialchars($flashMsg['message']); ?></div>
                <?php endif; ?>

                <section class="luki-hero mb-4">
                    <div class="luki-hero-copy">
                        <span class="badge rounded-pill bg-white bg-opacity-25 mb-3">Uncle Luki's Zakonnicomat</span>
                        <h1 class="fw-900 mb-2">Daily Spin System</h1>
                        <p class="lead opacity-75 mb-0">Endgame prestige dla kont ze statusem Wujek Luki. Jeden spin może błogosławić, uciszyć albo wystawić XP na próbę.</p>
                    </div>
                    <div class="luki-hero-art">
                        <img class="luki-sign" src="assets/images/luki-zakonnicomat-sign.svg" alt="Szyld maszyny losującej Wujka Lukiego" loading="lazy" decoding="async">
                        <img class="luki-mascot" src="assets/images/luki-zakonnica.svg" alt="" aria-hidden="true" loading="lazy" decoding="async">
                    </div>
                </section>

                <div class="luki-grid">
                    <section class="luki-spin-card p-4">
                        <div class="wheel-wrap">
                            <div class="wheel-stage">
                                <div class="wheel-pointer"></div>
                                <div class="luki-wheel" id="lukiWheel" data-segments="<?php echo count($segments); ?>">
                                    <div class="center-mark"></div>
                                    <?php foreach ($segments as $i => $segment): ?>
                                        <?php $angle = ($i * (360 / count($segments))) + (180 / count($segments)); ?>
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

                            <form method="POST" class="text-center spin-actions">
                                <?php echo csrfTokenField(); ?>
                                <button class="btn btn-primary btn-lg rounded-pill px-5 fw-bold" <?php echo $spinsLeft <= 0 ? 'disabled' : ''; ?>>
                                    <i class="bi bi-arrow-repeat me-2"></i>Spin Zakonnicomatem
                                </button>
                                <div class="small text-muted mt-2">Pozostało dziś: <strong><?php echo htmlspecialchars($spinsDisplay); ?></strong></div>
                            </form>

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
                            <div class="status-row"><span>XP</span><strong><?php echo number_format($currentXp); ?></strong></div>
                            <div class="status-row"><span>Ranga</span><strong><?php echo htmlspecialchars($rankInfo['name']); ?></strong></div>
                            <div class="status-row"><span>Limit spinów</span><strong><?php echo $isAdmin ? 'Bez limitu' : '2 dziennie'; ?></strong></div>
                            <div class="status-row"><span>Testy dziś</span><strong><?php echo (int)$activity['tests_today']; ?></strong></div>
                            <div class="status-row"><span>Streak aktywności</span><strong><?php echo (int)$activity['streak']; ?> dni</strong></div>
                            <div class="status-row"><span>Ostatni spin</span><strong><?php echo htmlspecialchars($lastSpinAt); ?></strong></div>
                        </div>

                        <?php if ($spinResult): ?>
                        <div class="result-card pending-reveal <?php echo htmlspecialchars($spinResult['archetype']); ?>" id="spinResult" data-index="<?php echo (int)$resultIndex; ?>" data-delta="<?php echo (int)$spinResult['xp']; ?>">
                            <div class="small text-muted fw-bold text-uppercase">Wynik spinu</div>
                            <h4 class="fw-900 mb-1"><?php echo htmlspecialchars($spinResult['label']); ?></h4>
                            <div class="display-6 fw-900 <?php echo (int)$spinResult['xp'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo (int)$spinResult['xp'] > 0 ? '+' : ''; ?><?php echo (int)$spinResult['xp']; ?> XP
                            </div>
                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($spinResult['note']); ?></p>
                        </div>
                        <?php endif; ?>

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

                <section class="luki-card p-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <h4 class="fw-bold mb-0"><i class="bi bi-scroll me-2 text-primary"></i>Chronicle of Spins</h4>
                        <span class="badge text-bg-light rounded-pill">Ostatnie 10 wyników</span>
                    </div>
                    <?php if (empty($history)): ?>
                        <div class="text-center text-muted py-4">Brak spinów. Chronicle czeka na pierwszy werdykt.</div>
                    <?php else: ?>
                        <?php foreach ($history as $entry): ?>
                            <div class="chronicle-item">
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
                </section>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const result = document.getElementById('spinResult');
    const wheel = document.getElementById('lukiWheel');
    if (!result || !wheel) return;

    const segmentCount = Number(wheel.dataset.segments || 6);
    const index = Number(result.dataset.index || 0);
    const segmentAngle = 360 / segmentCount;
    const segmentCenter = index * segmentAngle + segmentAngle / 2;
    const rotations = 360 * (5 + Math.floor(Math.random() * 3));
    const target = rotations + (360 - segmentCenter);
    requestAnimationFrame(() => {
        wheel.style.setProperty('--rot', target + 'deg');
    });
    setTimeout(() => {
        result.classList.add('is-visible');
    }, 4350);

    const delta = Number(result.dataset.delta || 0);
    setTimeout(() => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = delta > 0 ? 880 : (delta < 0 ? 120 : 320);
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
            osc.start();
            osc.stop(ctx.currentTime + 0.5);
        } catch (e) {}
    }, 4300);
});
</script>
</body>
</html>
