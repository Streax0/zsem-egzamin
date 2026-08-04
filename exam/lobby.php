<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin(true);

$isGuest = isGuestMode();
$userId = $isGuest ? null : (int)$_SESSION['user_id'];
$sessionId = (int)($_GET['session'] ?? 0);

// Get session and participant info
$stmt = $pdo->prepare("
    SELECT es.id, es.exam_id, es.access_code, es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, es.created_at, e.title, e.description, e.question_count, e.max_participants, e.total_time,
           e.anti_cheat_enabled, e.lobby_enabled, u.username as teacher_name
    FROM exam_sessions es
    JOIN exams e ON es.exam_id = e.id
    JOIN users u ON e.teacher_id = u.id
    WHERE es.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    setSessionMessage('error', 'Sesja nie istnieje.');
    redirect('../index.php');
}

// Get my participation
if ($isGuest) {
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND id = ? AND user_id IS NULL AND status != 'removed'");
    $stmt->execute([$sessionId, guestExamParticipantId($sessionId)]);
} else {
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND user_id = ? AND status != 'removed'");
    $stmt->execute([$sessionId, $userId]);
}
$participant = $stmt->fetch();

if (!$participant) {
    redirect('join.php');
}

// If exam started, redirect to take page
if ($session['status'] === 'in_progress' && $participant['status'] === 'taking_exam') {
    redirect('take.php?session=' . $sessionId);
}

// If exam finished
if ($session['status'] === 'finished') {
    redirect('finished.php?session=' . $sessionId);
}

// Get all participants
$stmt = $pdo->prepare("SELECT first_name, last_name, class, status FROM exam_participants WHERE session_id = ? AND status != 'removed' ORDER BY joined_at");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

// Get current UI preferences from cookies for server-side theme rendering
$currentTheme = $_COOKIE['user_theme'] ?? 'light';
$currentFontSize = $_COOKIE['user_font_size'] ?? '16';
$currentDensity = $_COOKIE['user_density'] ?? 'comfortable';
$currentAccent = $_COOKIE['user_accent'] ?? '#3b82f6';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $currentAccent)) {
    $currentAccent = '#3b82f6';
}
$reduceMotion = ($_COOKIE['reduce_motion'] ?? '0') === '1';
$dashboardView = $_COOKIE['dashboard_view'] ?? 'balanced';
$welcomeBannerStyle = $_COOKIE['welcome_banner_style'] ?? 'gradient';

$bodyClasses = [];
$bodyClasses[] = ($currentTheme === 'dark') ? 'dark-mode' : 'light-mode';
if ($currentDensity === 'compact') {
    $bodyClasses[] = 'ui-compact';
}
if ($reduceMotion) {
    $bodyClasses[] = 'reduce-motion';
}
$bodyClasses[] = 'dashboard-view-' . (in_array($dashboardView, ['balanced', 'learning', 'compact']) ? $dashboardView : 'balanced');
$bodyClasses[] = 'welcome-style-' . (in_array($welcomeBannerStyle, ['gradient', 'pure', 'aurora', 'glass']) ? $welcomeBannerStyle : 'gradient');
$bodyClassStr = implode(' ', $bodyClasses);
?>
<!DOCTYPE html>
<html lang="pl" style="color-scheme: <?php echo $currentTheme === 'dark' ? 'dark' : 'light'; ?>; font-size: <?php echo htmlspecialchars($currentFontSize); ?>px; --primary-color: <?php echo htmlspecialchars($currentAccent); ?>; --kolor-glowy: <?php echo htmlspecialchars($currentAccent); ?>;">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby – <?= htmlspecialchars($session['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('../assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('../assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('../assets/js/theme-handler.js')); ?>"></script>
    <style>
        .lobby-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 1.5rem;
            max-width: 1180px;
            margin: 0 auto;
            padding: 2rem 0;
        }
        .lobby-pulse { animation: lobbyPulse 2s ease-in-out infinite; }
        @keyframes lobbyPulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.05);opacity:0.8} }
        .participant-dot { width:10px; height:10px; border-radius:50%; background:#34d399; display:inline-block; }
        .lobby-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .85rem;
            margin-top: 2rem;
        }
        .lobby-stat-card {
            border-radius: 18px;
            padding: 1rem;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(10px);
            color: #f8fafc;
        }
        .lobby-side-card {
            border-radius: 24px;
            border: 1px solid rgba(148,163,184,.18);
            box-shadow: 0 18px 45px rgba(15,23,42,.15);
            background: rgba(15,23,42,.96);
            padding: 1.5rem;
        }
        .participant-chip {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .85rem;
            border: 1px solid rgba(148,163,184,.14);
            border-radius: 16px;
            background: rgba(255,255,255,.04);
            color: #f8fafc;
        }
        .participant-avatar {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(96,165,250,.12);
            color: #7dd3fc;
            font-weight: 800;
        }
        .waiting-ribbon {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .75rem 1.05rem;
            border-radius: 999px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.14);
            font-weight: 800;
            color: #f8fafc;
        }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .panel-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 0;
        }
        .lobby-shell .dashboard-panel h5,
        .lobby-shell .dashboard-panel .badge {
            color: #f8fafc;
        }
        @media (max-width: 991.98px) {
            .lobby-shell { grid-template-columns: 1fr; }
        }
        @media (max-width: 575.98px) {
            .lobby-stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($bodyClassStr); ?>">

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <div class="lobby-shell">
                        <section class="lobby-hero animate-in">
                            <div>
                                <div class="lobby-pulse mb-4">
                                    <div class="bg-white bg-opacity-25 rounded-4 d-inline-flex align-items-center justify-content-center" style="width:92px;height:92px;">
                                        <i class="bi bi-hourglass-split display-4 text-white"></i>
                                    </div>
                                </div>

                                <span class="waiting-ribbon mb-3"><i class="bi bi-broadcast-pin"></i>Lobby aktywne</span>
                                <h1 class="fw-bold mb-2"><?= htmlspecialchars($session['title']) ?></h1>
                                <p class="mb-1 opacity-75">Nauczyciel: <strong><?= htmlspecialchars($session['teacher_name']) ?></strong></p>

                                <?php if ($session['description']): ?>
                                    <div class="alert bg-white bg-opacity-10 border-0 text-white mt-4">
                                        <i class="bi bi-info-circle me-1"></i><?= nl2br(htmlspecialchars($session['description'])) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="lobby-stat-grid">
                                    <div class="lobby-stat-card">
                                        <div class="fw-bold h3 mb-0"><?= $session['question_count'] ?></div>
                                        <div class="small opacity-75">Pytań</div>
                                    </div>
                                    <div class="lobby-stat-card">
                                        <div class="fw-bold h3 mb-0" id="participantCount"><?= count($participants) ?></div>
                                        <div class="small opacity-75">Uczestników</div>
                                    </div>
                                    <div class="lobby-stat-card">
                                        <div class="fw-bold h3 mb-0"><?= $session['total_time'] ? $session['total_time'].'min' : '∞' ?></div>
                                        <div class="small opacity-75">Czas</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <?php if ($session['anti_cheat_enabled']): ?>
                                    <div class="alert bg-warning bg-opacity-25 border-0 text-white small">
                                        <i class="bi bi-shield-lock-fill me-1"></i> <strong>Uwaga:</strong> Ten sprawdzian posiada zabezpieczenia anty-oszustw. 
                                        Nie przełączaj kart i nie opuszczaj pełnego ekranu.
                                    </div>
                                <?php endif; ?>

                                <div class="waiting-ribbon">
                                    <i class="bi bi-clock"></i>
                                    <strong>Oczekiwanie na rozpoczęcie przez nauczyciela...</strong>
                                </div>
                            </div>
                        </section>

                        <aside class="dashboard-panel lobby-side-card animate-in">
                            <div class="panel-header mb-3">
                                <h5 class="panel-title mb-0"><i class="bi bi-people me-2 text-primary"></i>Uczestnicy</h5>
                                <span class="badge text-bg-primary rounded-pill" id="participantCountAlt"><?= count($participants) ?></span>
                            </div>
                            <div class="vstack gap-2" id="participantsGrid">
                                        <?php foreach ($participants as $p): ?>
                                        <div class="participant-chip">
                                            <span class="participant-avatar"><?= htmlspecialchars(strtoupper(substr($p['first_name'], 0, 1))) ?></span>
                                            <div class="min-w-0">
                                                <div class="fw-bold"><?= htmlspecialchars($p['first_name'] . ' ' . substr($p['last_name'], 0, 1) . '.') ?></div>
                                                <div class="small text-muted"><span class="participant-dot me-1"></span><?= htmlspecialchars($p['class']) ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                            </div>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    // Poll for exam start
     function checkStatus() {
         fetch('../ajax/exam_status.php?session=<?= $sessionId ?>')
             .then(r => r.json())
             .then(data => {
                 if (data.status === 'in_progress') {
                     window.location.href = 'take.php?session=<?= $sessionId ?>';
                 } else if (data.status === 'finished' || data.status === 'expired') {
                     window.location.href = '../index.php';
                 } else {
                     // Update participant count
                     if (data.participant_count !== undefined) {
                         const count1 = document.getElementById('participantCount');
                         const count2 = document.getElementById('participantCountAlt');
                         if (count1) count1.textContent = data.participant_count;
                         if (count2) count2.textContent = data.participant_count;
                     }
                     
                     // Update participant list
                     if (data.participants) {
                         const grid = document.getElementById('participantsGrid');
                         if (grid) {
                             let html = '';
                             data.participants.forEach(p => {
                                 const firstName = escapeHtml(p.first_name);
                                 const lastNameInit = p.last_name ? escapeHtml(p.last_name.substring(0, 1)) + '.' : '';
                                 const participantClass = escapeHtml(p.class);
                                 html += `
                                     <div class="participant-chip animate-in">
                                         <span class="participant-avatar">${firstName.substring(0, 1).toUpperCase()}</span>
                                         <div class="min-w-0">
                                             <div class="fw-bold">${firstName} ${lastNameInit}</div>
                                             <div class="small text-muted"><span class="participant-dot me-1"></span>${participantClass}</div>
                                         </div>
                                     </div>
                                 `;
                             });
                             grid.innerHTML = html;
                         }
                     }
                 }
             })
             .catch(() => {});
     }
     setInterval(checkStatus, 1000);
    </script>
    <?php include '../includes/help_center.php'; ?>
</body>
</html>
