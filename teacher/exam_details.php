<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    redirect('../index.php');
}

$userId = $_SESSION['user_id'];
$sessionId = (int)($_GET['session'] ?? 0);

// Load session + exam
$stmt = $pdo->prepare("
    SELECT es.*, e.*,
           u.username as teacher_name
    FROM exam_sessions es
    JOIN exams e ON es.exam_id = e.id
    JOIN users u ON e.teacher_id = u.id
    WHERE es.id = ? AND e.teacher_id = ?
");
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch();

if (!$session) { redirect('index.php'); }

// Participants
$stmt = $pdo->prepare("SELECT * FROM exam_participants WHERE session_id = ? ORDER BY score_percent DESC, time_spent ASC");
$stmt->execute([$sessionId]);
$participants = $stmt->fetchAll();

// Stats
$finished = array_filter($participants, fn($p) => $p['status'] === 'finished');
$scores = array_column($finished, 'score_percent');
$avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
$medianScore = 0;
if (!empty($scores)) {
    sort($scores);
    $mid = floor(count($scores) / 2);
    $medianScore = count($scores) % 2 ? $scores[$mid] : round(($scores[$mid-1] + $scores[$mid]) / 2, 1);
}
$maxScore = !empty($scores) ? max($scores) : 0;
$minScore = !empty($scores) ? min($scores) : 0;
$passingRate = count($finished) > 0 ? round(count(array_filter($scores, fn($s) => $s >= 50)) / count($finished) * 100) : 0;
$totalViolations = array_sum(array_column($participants, 'violation_count'));
$avgTime = !empty($finished) ? round(array_sum(array_column($finished, 'time_spent')) / count($finished)) : 0;

// Question stats
$stmt = $pdo->prepare("
    SELECT ea.question_id, q.question_text,
           COUNT(*) as total_answers,
           SUM(ea.is_correct) as correct_count,
           ROUND(SUM(ea.is_correct)/COUNT(*)*100, 1) as accuracy,
           ROUND(AVG(ea.time_spent)) as avg_time
    FROM exam_answers ea
    JOIN questions q ON ea.question_id = q.id
    WHERE ea.session_id = ?
    GROUP BY ea.question_id
    ORDER BY accuracy ASC
");
$stmt->execute([$sessionId]);
$questionStats = $stmt->fetchAll();

// Get violations details
$stmt = $pdo->prepare("
    SELECT ev.*, ep.first_name, ep.last_name
    FROM exam_violations ev
    JOIN exam_participants ep ON ev.participant_id = ep.id
    WHERE ev.session_id = ?
    ORDER BY ev.created_at DESC
    LIMIT 50
");
$stmt->execute([$sessionId]);
$violations = $stmt->fetchAll();

// Grade thresholds
$gradeThresholds = $session['grade_thresholds'] ? json_decode($session['grade_thresholds'], true) : null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szczegóły sprawdzianu – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
</head>
<body>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid p-0">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-1"><?= htmlspecialchars($session['title']) ?></h2>
                            <p class="text-muted mb-0">
                                <?= $session['started_at'] ? date('d.m.Y H:i', strtotime($session['started_at'])) : '' ?>
                                – <?= $session['finished_at'] ? date('H:i', strtotime($session['finished_at'])) : 'W trakcie' ?>
                                · <?= count($finished) ?> uczestników
                            </p>
                        </div>
                        <a href="index.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left me-1"></i>Powrót</a>
                    </div>

                    <!-- Stats cards -->
                    <div class="row g-3 mb-4">
                        <?php
                        $cards = [
                            ['Średni wynik', $avgScore.'%', 'bi-bar-chart-fill', 'primary'],
                            ['Mediana', $medianScore.'%', 'bi-distribute-vertical', 'info'],
                            ['Najwyższy', $maxScore.'%', 'bi-arrow-up-circle', 'success'],
                            ['Najniższy', $minScore.'%', 'bi-arrow-down-circle', 'danger'],
                            ['Zdawalność', $passingRate.'%', 'bi-check-circle', 'warning'],
                            ['Śr. czas', formatTime($avgTime), 'bi-clock', 'secondary'],
                            ['Naruszenia', $totalViolations, 'bi-shield-exclamation', 'danger'],
                            ['Status', ucfirst($session['status']), 'bi-broadcast', 'primary'],
                        ];
                        foreach ($cards as $c): ?>
                        <div class="col-md-3 col-6">
                            <div class="dashboard-panel text-center p-3">
                                <i class="bi <?= $c[2] ?> text-<?= $c[3] ?> fs-4 d-block mb-1"></i>
                                <div class="h4 fw-800 mb-0"><?= $c[1] ?></div>
                                <div class="text-muted small"><?= $c[0] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Participants -->
                    <div class="dashboard-panel mb-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-people me-2"></i>Uczestnicy (<?= count($participants) ?>)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr class="text-muted small">
                                    <th>#</th><th>Imię i Nazwisko</th><th>Klasa</th><th>Wynik</th><th>Poprawne</th>
                                    <th>Czas</th><th>Naruszenia</th><th>Status</th>
                                    <?php if ($gradeThresholds): ?><th>Ocena</th><?php endif; ?>
                                </tr></thead>
                                <tbody>
                                    <?php foreach ($participants as $i => $p):
                                        $pGrade = null;
                                        if ($gradeThresholds) {
                                            $s = (float)$p['score_percent'];
                                            if ($s >= ($gradeThresholds['6']??95)) $pGrade=6;
                                            elseif ($s >= ($gradeThresholds['5']??85)) $pGrade=5;
                                            elseif ($s >= ($gradeThresholds['4']??70)) $pGrade=4;
                                            elseif ($s >= ($gradeThresholds['3']??50)) $pGrade=3;
                                            elseif ($s >= ($gradeThresholds['2']??30)) $pGrade=2;
                                            else $pGrade=1;
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $i+1 ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
                                        <td><?= htmlspecialchars($p['class']) ?></td>
                                        <td><span class="badge bg-<?= $p['score_percent']>=50?'success':'danger' ?>"><?= round($p['score_percent']) ?>%</span></td>
                                        <td><?= $p['correct_answers'] ?>/<?= $p['total_answered'] ?></td>
                                        <td class="small"><?= $p['time_spent'] ? formatTime($p['time_spent']) : '—' ?></td>
                                        <td>
                                            <?php if ($p['violation_count'] > 0): ?>
                                                <span class="badge bg-danger"><?= $p['violation_count'] ?></span>
                                            <?php else: ?>
                                                <span class="text-success"><i class="bi bi-check-circle"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?= $p['status']==='finished'?'secondary':'warning' ?> bg-opacity-10 text-<?= $p['status']==='finished'?'secondary':'warning' ?>"><?= ucfirst($p['status']) ?></span></td>
                                        <?php if ($gradeThresholds): ?><td class="fw-bold"><?= $pGrade ?></td><?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Question difficulty -->
                    <?php if (!empty($questionStats)): ?>
                    <div class="dashboard-panel mb-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-graph-up me-2"></i>Statystyki pytań</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr class="text-muted small"><th>Pytanie</th><th>Poprawność</th><th>Śr. czas</th></tr></thead>
                                <tbody>
                                    <?php foreach ($questionStats as $qs): ?>
                                    <tr>
                                        <td class="small"><?= htmlspecialchars(mb_substr($qs['question_text'], 0, 80)) ?>...</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height:8px;width:100px;">
                                                    <div class="progress-bar bg-<?= $qs['accuracy']>=70?'success':($qs['accuracy']>=40?'warning':'danger') ?>" style="width:<?= $qs['accuracy'] ?>%"></div>
                                                </div>
                                                <span class="small fw-bold"><?= $qs['accuracy'] ?>%</span>
                                            </div>
                                        </td>
                                        <td class="small text-muted"><?= $qs['avg_time'] ?>s</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Violations log -->
                    <?php if (!empty($violations)): ?>
                    <div class="dashboard-panel mb-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Naruszenia zasad (<?= count($violations) ?>)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr class="text-muted small"><th>Czas</th><th>Uczestnik</th><th>Typ</th><th>Pytanie</th></tr></thead>
                                <tbody>
                                    <?php foreach ($violations as $v): ?>
                                    <tr>
                                        <td class="small"><?= date('H:i:s', strtotime($v['created_at'])) ?></td>
                                        <td class="fw-bold small"><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></td>
                                        <td><span class="badge bg-danger bg-opacity-10 text-danger"><?= $v['violation_type'] ?></span></td>
                                        <td class="small">#<?= $v['question_order'] ?: '—' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
