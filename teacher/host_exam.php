<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    setSessionMessage('error', 'Brak uprawnień.');
    redirect('../index.php');
}

$userId = $_SESSION['user_id'];

// Generate unique access code
function generateAccessCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CSRF Protection
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        redirect('index.php');
    }
    
    if ($action === 'create_session') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        // Verify ownership
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$examId, $userId]);
        $exam = $stmt->fetch();
        
        if (!$exam) {
            setSessionMessage('error', 'Sprawdzian nie istnieje.');
            redirect('index.php');
        }
        
        // Pre-select questions for this session. Public pools exclude private/custom bank
        // entries, but explicitly selected custom exam questions must remain usable.
        $publicQuestions = loadQuestions($pdo, false);
        $publicQuestions = array_values(array_filter($publicQuestions, static fn($q) => !isInternalQuestionCategory($q['category'] ?? '')));
        $selectedIds = $exam['selected_questions'] ? json_decode($exam['selected_questions'], true) : null;
        $cats = $exam['categories'] ? json_decode($exam['categories'], true) : null;
        
        if ($selectedIds) {
            $allQuestions = loadQuestions($pdo, true);
            $questions = array_filter($allQuestions, fn($q) => in_array($q['id'], $selectedIds));
        } elseif ($cats) {
            $questions = array_filter($publicQuestions, fn($q) => in_array($q['category'], $cats));
            // Fallback: if category filter yields 0 questions, use all questions
            if (empty($questions)) {
                $questions = $publicQuestions;
            }
        } else {
            $questions = $publicQuestions;
        }
        
        $questions = array_values($questions);
        if (!empty($exam['shuffle_questions'])) {
            shuffle($questions);
        } else {
            usort($questions, static fn($a, $b) => ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0)));
        }
        
        $maxQuestions = (int)$exam['question_count'];
        if ($maxQuestions <= 0) $maxQuestions = 40;
        
        $questions = array_slice($questions, 0, $maxQuestions);

        try {
            $pdo->beginTransaction();

            // Generate unique code
            $code = generateAccessCode();
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $stmt = $pdo->prepare("INSERT INTO exam_sessions (exam_id, access_code, status, expires_at) VALUES (?, ?, 'lobby', ?)");
            $stmt->execute([$examId, $code, $expiresAt]);
            $sessionId = $pdo->lastInsertId();

            // Save questions to session
            $order = 1;
            $stmtQ = $pdo->prepare("INSERT INTO exam_session_questions (session_id, question_id, question_order) VALUES (?, ?, ?)");
            foreach ($questions as $q) {
                $stmtQ->execute([$sessionId, $q['id'], $order++]);
            }

            // Also update the session record with the actual count if it differs
            if (count($questions) > 0) {
                $pdo->prepare("UPDATE exams SET question_count = ? WHERE id = ?")
                    ->execute([count($questions), $examId]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Create exam session failed: ' . $e->getMessage());
            setSessionMessage('error', 'Nie udało się utworzyć sesji sprawdzianu.');
            redirect('index.php');
        }
        
        redirect('host_exam.php?session=' . $sessionId);
    }
    
    if ($action === 'start_exam') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $stmt = $pdo->prepare("
            UPDATE exam_sessions SET status = 'in_progress', started_at = NOW() 
            WHERE id = ? AND exam_id IN (SELECT id FROM exams WHERE teacher_id = ?)
        ");
        $stmt->execute([$sessionId, $userId]);
        
        // Update all lobby participants
        $pdo->prepare("UPDATE exam_participants SET status = 'taking_exam', started_at = NOW() WHERE session_id = ? AND status = 'in_lobby'")
            ->execute([$sessionId]);
        
        redirect('host_exam.php?session=' . $sessionId);
    }
    
    if ($action === 'pause_exam') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $pdo->prepare("UPDATE exam_sessions SET status = 'paused', paused_at = NOW() WHERE id = ? AND status = 'in_progress' AND exam_id IN (SELECT id FROM exams WHERE teacher_id = ?)")
            ->execute([$sessionId, $userId]);
        redirect('host_exam.php?session=' . $sessionId);
    }
    
    if ($action === 'resume_exam') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $pdo->prepare("UPDATE exam_sessions SET status = 'in_progress', paused_seconds = paused_seconds + IF(paused_at IS NULL, 0, TIMESTAMPDIFF(SECOND, paused_at, NOW())), paused_at = NULL WHERE id = ? AND status = 'paused' AND exam_id IN (SELECT id FROM exams WHERE teacher_id = ?)")
            ->execute([$sessionId, $userId]);
        redirect('host_exam.php?session=' . $sessionId);
    }
    
    if ($action === 'finish_exam') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $pdo->prepare("UPDATE exam_sessions SET status = 'finished', finished_at = NOW() WHERE id = ? AND exam_id IN (SELECT id FROM exams WHERE teacher_id = ?)")
            ->execute([$sessionId, $userId]);
        $pdo->prepare("UPDATE exam_participants SET status = 'finished', finished_at = NOW() WHERE session_id = ? AND status IN ('taking_exam','in_lobby')")
            ->execute([$sessionId]);
        redirect('exam_details.php?session=' . $sessionId);
    }
    
    if ($action === 'remove_participant') {
        $participantId = (int)($_POST['participant_id'] ?? 0);
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $pdo->prepare("UPDATE exam_participants SET status = 'removed' WHERE id = ? AND session_id = ?")
            ->execute([$participantId, $sessionId]);
        redirect('host_exam.php?session=' . $sessionId);
    }
}

// Get session or exam to host
$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : 0;
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;

$session = null;
$exam = null;

if ($sessionId) {
    $stmt = $pdo->prepare("
        SELECT es.*, e.title, e.description, e.question_count, e.max_participants, e.anti_cheat_enabled,
               e.block_tab_switch, e.require_fullscreen, e.total_time, e.teacher_id
        FROM exam_sessions es 
        JOIN exams e ON es.exam_id = e.id 
        WHERE es.id = ? AND e.teacher_id = ?
    ");
    $stmt->execute([$sessionId, $userId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        setSessionMessage('error', 'Sesja nie istnieje.');
        redirect('index.php');
    }
    
    // Get participants
    $stmt = $pdo->prepare("SELECT * FROM exam_participants WHERE session_id = ? ORDER BY joined_at ASC");
    $stmt->execute([$sessionId]);
    $participants = $stmt->fetchAll();

    $printQuestions = [];
    try {
        $stmt = $pdo->prepare("
            SELECT q.*
            FROM exam_session_questions esq
            JOIN questions q ON q.id = esq.question_id
            WHERE esq.session_id = ?
            ORDER BY esq.question_order ASC, esq.id ASC
        ");
        $stmt->execute([$sessionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $printQuestions[] = normalizeQuestionRow($row);
        }
    } catch (PDOException $e) {
        $printQuestions = [];
    }
    
} elseif ($examId) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$examId, $userId]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        setSessionMessage('error', 'Sprawdzian nie istnieje.');
        redirect('index.php');
    }
}

$teacherName = $_SESSION['username'] ?? 'Nauczyciel';
$flashMsg = getSessionMessage();
$joinUrl = '';
$visibleParticipantCount = 0;
if ($session) {
    $visibleParticipantCount = count(array_filter($participants, static fn($p) => ($p['status'] ?? '') !== 'removed'));
    $joinUrl = securityPublicBaseUrl() . '/exam/join.php?code=' . rawurlencode((string)$session['access_code']);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostuj sprawdzian – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <style>
        .access-code { font-size: 3rem; letter-spacing: 0.5rem; font-family: 'Inter', sans-serif; font-weight: 800; color: var(--primary-color); text-shadow: 0 4px 10px rgba(59,130,246,0.2); }
        .participant-card { transition: all 0.3s; border-left: 4px solid transparent; background: var(--panel-bg); }
        .participant-card:hover { transform: translateX(5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .participant-card.taking_exam { border-left-color: #10b981; }
        .participant-card.finished { border-left-color: #64748b; opacity: 0.8; }
        .participant-card.removed { border-left-color: #ef4444; opacity: 0.6; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }
        .violation-badge { font-size: 0.75rem; border-radius: 50px; }
        .live-indicator { width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block; margin-right: 5px; }
        .feed-item { border-left: 2px solid var(--border-color); padding-left: 15px; position: relative; margin-bottom: 15px; }
        .feed-item::before { content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: var(--border-color); }
        .feed-item.danger::before { background: #ef4444; }
        .host-shell { max-width: 1480px; margin: 0 auto; }
        .host-top-card {
            border: 1px solid rgba(148,163,184,.20);
            background:
                radial-gradient(circle at 0% 0%, rgba(59,130,246,.12), transparent 32%),
                linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
        .host-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .48rem .78rem;
            font-weight: 900;
            letter-spacing: .01em;
        }
        .host-code-panel {
            border: 1px solid rgba(59,130,246,.16);
            background:
                linear-gradient(180deg, rgba(248,250,252,.98), #ffffff),
                radial-gradient(circle at 50% 0%, rgba(59,130,246,.14), transparent 45%);
        }
        .host-code-actions .btn {
            min-height: 42px;
            font-weight: 850;
        }
        .qr-box {
            border: 1px solid rgba(148,163,184,.22);
            background: #fff;
            border-radius: 18px;
        }
        .qr-box img { width: 210px; height: 210px; }
        .host-stat-card {
            min-height: 96px;
            border: 1px solid rgba(148,163,184,.18);
            background: #fff;
        }
        .participants-panel {
            position: sticky;
            top: 90px;
            border: 1px solid rgba(148,163,184,.20);
            overflow: hidden;
        }
        .participants-panel-head {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:.75rem;
            padding-bottom: .85rem;
            border-bottom: 1px solid rgba(148,163,184,.16);
        }
        .participants-list {
            max-height: min(68vh, 720px);
            overflow: auto;
            padding-right: .25rem;
        }
        .participant-card {
            border: 1px solid rgba(148,163,184,.18) !important;
            border-left-width: 4px !important;
            border-radius: 14px !important;
            box-shadow: 0 10px 24px rgba(15,23,42,.05);
        }
        .participant-card:hover { transform: translateY(-1px); }
        .participant-avatar {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            background: rgba(59,130,246,.10);
            color: #1d4ed8;
            font-weight: 900;
        }
        .participant-actions .btn,
        .participant-actions button {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .participant-empty {
            min-height: 220px;
            display: grid;
            place-items: center;
            text-align: center;
            color: #94a3b8;
        }
        .participant-refresh-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
            box-shadow: 0 0 0 6px rgba(34,197,94,.12);
        }
        body.dark-mode .host-top-card,
        body.dark-mode .host-code-panel,
        body.dark-mode .host-stat-card,
        body.dark-mode .qr-box {
            background: #111827 !important;
            border-color: rgba(148,163,184,.24) !important;
        }
        body.dark-mode .participant-card { background: #0f172a !important; }
        @media (max-width: 991.98px) {
            .participants-panel { position: static; }
            .access-code { font-size: 2.25rem; letter-spacing: .35rem; }
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0 host-shell">

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4">
                            <?= htmlspecialchars($flashMsg['message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($exam && !$session): ?>
                    <!-- PRE-HOST: Generate session -->
                    <div class="row justify-content-center">
                        <div class="col-lg-6">
                            <div class="dashboard-panel text-center animate-in">
                                <i class="bi bi-broadcast display-1 text-primary mb-3 d-block"></i>
                                <h3 class="fw-bold mb-2"><?= htmlspecialchars($exam['title']) ?></h3>
                                <p class="text-muted mb-4">Gotowy do hostowania sprawdzianu?<br>System wygeneruje unikatowy kod dostępu ważny przez 1 godzinę.</p>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="action" value="create_session">
                                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow">
                                        <i class="bi bi-play-fill me-2"></i>Hostuj test
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php elseif ($session): ?>
                    <!-- ACTIVE SESSION -->
                    <div class="row g-4 host-live-grid">
                        <!-- Left: Session info -->
                        <div class="col-lg-8">
                            <!-- Header -->
                            <div class="dashboard-panel host-top-card mb-4 animate-in">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h3 class="fw-bold mb-1"><?= htmlspecialchars($session['title']) ?></h3>
                                        <p class="text-muted mb-0">
                                            <?php
                                            echo match($session['status']) {
                                                'lobby' => '<span class="badge bg-warning text-dark fs-6"><i class="bi bi-hourglass-split me-1"></i>Lobby – oczekiwanie na uczniów</span>',
                                                'in_progress' => '<span class="badge bg-success fs-6 pulse"><i class="bi bi-broadcast me-1"></i>Egzamin w trakcie</span>',
                                                'paused' => '<span class="badge bg-info fs-6"><i class="bi bi-pause-fill me-1"></i>Wstrzymany</span>',
                                                'finished' => '<span class="badge bg-secondary fs-6"><i class="bi bi-check-circle me-1"></i>Zakończony</span>',
                                                default => '<span class="badge bg-light text-muted">Nieznany</span>',
                                            };
                                            ?>
                                        </p>
                                    </div>
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                                        <i class="bi bi-arrow-left me-1"></i>Panel
                                    </a>
                                </div>
                            </div>

                            <!-- Access Code -->
                            <div class="dashboard-panel host-code-panel printable-panel mb-4 text-center animate-in" style="animation-delay:0.1s;">
                                <div class="small text-muted mb-2 fw-bold" style="letter-spacing: 2px;">KOD DOSTĘPU</div>
                                <div class="access-code mb-2" id="displayCode"><?= htmlspecialchars($session['access_code']) ?></div>
                                <div class="small text-muted mt-2">
                                    <i class="bi bi-clock me-1"></i>Ważny do: <span class="fw-bold"><?= date('H:i', strtotime($session['expires_at'])) ?></span>
                                    <?php if (strtotime($session['expires_at']) < time()): ?>
                                        <span class="badge bg-danger ms-2">Wygasł</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap no-print host-code-actions">
                                    <button class="btn btn-primary btn-sm rounded-pill px-4" type="button" onclick="copyHostText(<?= json_encode((string)$session['access_code']) ?>, 'Kod skopiowany!')">
                                        <i class="bi bi-clipboard me-1"></i>Kopiuj kod
                                    </button>
                                    <button class="btn btn-dark btn-sm rounded-pill px-4" type="button" data-bs-toggle="collapse" data-bs-target="#qrSection">
                                        <i class="bi bi-qr-code me-1"></i>Kod QR
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-4" type="button" onclick="copyHostText(<?= json_encode($joinUrl) ?>, 'Link skopiowany!')">
                                        <i class="bi bi-link-45deg me-1"></i>Kopiuj link
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-4" type="button" onclick="window.print()">
                                        <i class="bi bi-printer me-1"></i>Drukuj arkusz
                                    </button>
                                </div>
                                <div class="collapse mt-4" id="qrSection">
                                    <div class="p-3 d-inline-block shadow-sm qr-box">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($joinUrl) ?>" alt="Kod QR do dołączenia do sprawdzianu" class="img-fluid" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                        <div class="mt-2 small text-dark fw-bold">Zeskanuj, aby dołączyć</div>
                                        <div class="small text-muted text-break" style="max-width:260px;"><?= htmlspecialchars($joinUrl) ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($printQuestions)): ?>
                                    <div class="print-exam-sheet text-start mt-5">
                                        <h4 class="fw-bold text-center mb-1"><?= htmlspecialchars($session['title']) ?></h4>
                                        <p class="text-center text-muted small mb-4">Kod: <?= htmlspecialchars($session['access_code']) ?> · Pytań: <?= count($printQuestions) ?></p>
                                        <?php foreach ($printQuestions as $idx => $question): ?>
                                            <div class="print-question mb-4">
                                                <div class="fw-bold mb-2"><?= ($idx + 1) ?>. <?= htmlspecialchars($question['question_text']) ?></div>
                                                <ol type="A" class="mb-0">
                                                    <li><?= htmlspecialchars($question['option_a']) ?></li>
                                                    <li><?= htmlspecialchars($question['option_b']) ?></li>
                                                    <li><?= htmlspecialchars($question['option_c']) ?></li>
                                                    <li><?= htmlspecialchars($question['option_d']) ?></li>
                                                </ol>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Live Stats -->
                            <div class="row g-3 mb-4 animate-in" style="animation-delay:0.15s">
                                <div class="col-md-4">
                                    <div class="dashboard-panel host-stat-card text-center p-3">
                                        <div class="text-muted small mb-1">Średni wynik</div>
                                        <div class="h3 fw-bold mb-0 text-success" id="avgScore">0%</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="dashboard-panel host-stat-card text-center p-3">
                                        <div class="text-muted small mb-1">Ukończyło</div>
                                        <div class="h3 fw-bold mb-0 text-primary" id="finishedCount">0</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="dashboard-panel host-stat-card text-center p-3">
                                        <div class="text-muted small mb-1">Naruszenia</div>
                                        <div class="h3 fw-bold mb-0 text-danger" id="totalViolations">0</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Controls -->
                            <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.2s">
                                <h5 class="fw-bold mb-3"><i class="bi bi-joystick me-2"></i>Kontrola egzaminu</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($session['status'] === 'lobby'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="action" value="start_exam">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-lg rounded-pill px-4" onclick="return appConfirmSubmit(this.form, 'Rozpocząć egzamin dla wszystkich uczestników?')">
                                                <i class="bi bi-play-fill me-2"></i>Rozpocznij test
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['status'] === 'in_progress'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="action" value="pause_exam">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="btn btn-warning rounded-pill px-4">
                                                <i class="bi bi-pause-fill me-1"></i>Wstrzymaj
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['status'] === 'paused'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="action" value="resume_exam">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="btn btn-success rounded-pill px-4">
                                                <i class="bi bi-play-fill me-1"></i>Wznów
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($session['status'], ['lobby', 'in_progress', 'paused'])): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="action" value="finish_exam">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="btn btn-danger rounded-pill px-4" onclick="return appConfirmSubmit(this.form, 'Zakończyć egzamin? Ta akcja jest nieodwracalna.')">
                                                <i class="bi bi-stop-fill me-1"></i>Zakończ test
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Participants -->
                        <div class="col-lg-4">
                            <div class="dashboard-panel participants-panel animate-in" style="animation-delay:0.15s">
                                <div class="participants-panel-head mb-3">
                                    <div>
                                        <h5 class="fw-bold mb-1"><i class="bi bi-people me-2"></i>Uczestnicy</h5>
                                        <div class="small text-muted"><span class="participant-refresh-dot me-2"></span>Lista odświeża się automatycznie</div>
                                    </div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3" id="participantsCountBadge">
                                        <?= $visibleParticipantCount ?> / <?= $session['max_participants'] ?>
                                    </span>
                                </div>
                                <div class="participants-list d-flex flex-column gap-2" id="participantsList" data-max-participants="<?= (int)$session['max_participants'] ?>"></div>
                                <div class="small text-muted mt-3" id="participantsUpdatedAt">Ładowanie listy...</div>
                            </div>
                        </div>
                    </div>

                    <script>
                    const SESSION_ID = <?= $sessionId ?>;
                    const CSRF_TOKEN = <?= json_encode(generateCsrfToken()) ?>;
                    const SESSION_IS_LIVE = <?= in_array($session['status'], ['lobby', 'in_progress', 'paused'], true) ? 'true' : 'false' ?>;
                    const INITIAL_PARTICIPANTS = <?= json_encode($participants, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                    async function refreshParticipantsOnly() {
                        if (!SESSION_IS_LIVE) return;
                        try {
                            const response = await fetch(`../ajax/get_session_status.php?session_id=${SESSION_ID}&scope=participants`, { cache: 'no-store' });
                            const data = await response.json();
                            if (data.success) {
                                renderParticipants(data.participants);
                                updateParticipantMeta(data.participants, data.server_time);
                            }
                        } catch (e) {
                            const updated = document.getElementById('participantsUpdatedAt');
                            if (updated) updated.textContent = 'Nie udało się odświeżyć listy. Spróbuję ponownie za chwilę.';
                        }
                    }

                    function renderParticipants(list) {
                        const container = document.getElementById('participantsList');
                        if (!container) return;

                        if (!Array.isArray(list) || list.length === 0) {
                            container.innerHTML = `
                                <div class="participant-empty">
                                    <div>
                                        <i class="bi bi-person-dash display-4 opacity-25 d-block mb-2"></i>
                                        <div class="fw-bold">Brak uczestników</div>
                                        <div class="small">Udostępnij kod: <strong><?= htmlspecialchars($session['access_code']) ?></strong></div>
                                    </div>
                                </div>
                            `;
                            return;
                        }

                        let html = '';
                        list.forEach(p => {
                            const id = parseInt(p.id, 10) || 0;
                            const current = Math.max(1, (parseInt(p.current_question, 10) || 0) + 1);
                            const correct = parseInt(p.correct_answers, 10) || 0;
                            const answered = parseInt(p.total_answered, 10) || 0;
                            const violations = parseInt(p.violation_count, 10) || 0;
                            const score = Number.parseFloat(p.score_percent || 0).toFixed(1).replace('.0', '');
                            const fullName = `${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}`.trim();
                            const initials = `${String(p.first_name || '?').charAt(0)}${String(p.last_name || '').charAt(0)}`.toUpperCase();
                            const statusLabel = {
                                'in_lobby': '<span class="badge bg-warning bg-opacity-10 text-warning">Lobby</span>',
                                'taking_exam': '<span class="badge bg-success bg-opacity-10 text-success">Pisze</span>',
                                'finished': '<span class="badge bg-secondary bg-opacity-10 text-secondary">Ukończył</span>',
                                'removed': '<span class="badge bg-danger bg-opacity-10 text-danger">Usunięty</span>'
                            };

                            html += `
                                <div class="participant-card ${escapeHtml(p.status)} d-flex justify-content-between align-items-center p-3 mb-2">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <div class="participant-avatar">${escapeHtml(initials)}</div>
                                        <div class="min-w-0">
                                            <div class="fw-bold small text-truncate">${fullName || 'Uczestnik'}</div>
                                            <div class="text-muted" style="font-size:0.75rem">
                                                ${escapeHtml(p.class)}
                                                ${p.status === 'taking_exam' ? ` · Pytanie ${current} · ${correct}/${answered} poprawnych` : ''}
                                                ${p.status === 'finished' ? ` · Wynik: ${score}%` : ''}
                                            </div>
                                            ${violations > 0 ? `
                                                <span class="badge bg-danger violation-badge mt-1">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>${violations} naruszeń
                                                </span>` : ''}
                                        </div>
                                    </div>
                                    <div class="participant-actions d-flex align-items-center gap-2">
                                        ${statusLabel[p.status] || ''}
                                        ${p.status !== 'removed' ? `<a href="view_participant_result.php?id=${id}" class="btn btn-sm btn-outline-primary rounded-circle" title="Szczegóły">
                                            <i class="bi bi-eye"></i>
                                        </a>` : ''}
                                        ${p.status !== 'removed' && p.status !== 'finished' ? `
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-circle" title="Wyślij ostrzeżenie"
                                                data-participant-id="${id}"
                                                data-participant-name="${fullName}"
                                                onclick="sendWarningFromButton(this)">
                                            <i class="bi bi-exclamation-triangle"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return appConfirmSubmit(this, 'Usunąć uczestnika z sesji?');">
                                            <input type="hidden" name="csrf_token" value="${escapeHtml(CSRF_TOKEN)}">
                                            <input type="hidden" name="action" value="remove_participant">
                                            <input type="hidden" name="session_id" value="${SESSION_ID}">
                                            <input type="hidden" name="participant_id" value="${id}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Usuń z sesji">
                                                <i class="bi bi-person-x"></i>
                                            </button>
                                        </form>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    }

                    function updateParticipantMeta(list, serverTime) {
                        const badge = document.getElementById('participantsCountBadge');
                        const updated = document.getElementById('participantsUpdatedAt');
                        const max = document.getElementById('participantsList')?.dataset.maxParticipants || '<?= (int)$session['max_participants'] ?>';
                        const visible = Array.isArray(list) ? list.filter(p => p.status !== 'removed').length : 0;
                        if (badge) badge.textContent = `${visible} / ${max}`;
                        if (updated) {
                            const stamp = serverTime && serverTime !== 'start' ? serverTime : new Date().toLocaleTimeString('pl-PL');
                            updated.textContent = `Ostatnie odświeżenie: ${stamp}`;
                        }
                    }

                    function sendWarningFromButton(button) {
                        sendWarning(button.dataset.participantId, button.dataset.participantName || 'uczestnika');
                    }

                    async function sendWarning(participantId, name) {
                        const msg = await appPrompt(`Wpisz treść ostrzeżenia dla ${name}:`, 'Proszę skupić się na teście.');
                        if (!msg) return;

                        const formData = new FormData();
                        formData.append('participant_id', participantId);
                        formData.append('session_id', SESSION_ID);
                        formData.append('message', msg);
                        formData.append('csrf_token', CSRF_TOKEN);

                        try {
                            const response = await fetch('../ajax/send_warning.php', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            if (data.success) {
                                appNotice('Ostrzeżenie zostało wysłane.', 'success');
                            } else {
                                appNotice('Błąd: ' + (data.message || 'Nie udało się wysłać ostrzeżenia.'), 'danger');
                            }
                        } catch (e) {
                            appNotice('Błąd połączenia.', 'danger');
                        }
                    }

                    function escapeHtml(text) {
                        if (!text) return '';
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }

                    async function copyHostText(text, message) {
                        try {
                            if (navigator.clipboard && window.isSecureContext) {
                                await navigator.clipboard.writeText(String(text));
                            } else {
                                const input = document.createElement('textarea');
                                input.value = String(text);
                                input.setAttribute('readonly', '');
                                input.style.position = 'fixed';
                                input.style.left = '-9999px';
                                document.body.appendChild(input);
                                input.select();
                                document.execCommand('copy');
                                input.remove();
                            }
                            appNotice(message || 'Skopiowano.', 'success');
                        } catch (e) {
                            appNotice('Nie udało się skopiować. Zaznacz tekst ręcznie.', 'warning');
                        }
                    }

                    renderParticipants(INITIAL_PARTICIPANTS);
                    updateParticipantMeta(INITIAL_PARTICIPANTS, 'start');
                    if (SESSION_IS_LIVE) {
                        setInterval(refreshParticipantsOnly, 3000);
                        refreshParticipantsOnly();
                    }
                    </script>

                    <?php endif; ?>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
