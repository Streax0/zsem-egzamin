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
$hasAdminAccess = roleHasAdminAccess($_SESSION['role'] ?? '');

// Load session + exam
$stmt = $pdo->prepare("
    SELECT es.id, es.exam_id, es.access_code, es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, es.created_at, e.id AS exam_tbl_id, e.teacher_id, e.title, e.description, e.question_count, e.selected_questions, e.categories, e.difficulty_level, e.shuffle_questions, e.shuffle_answers, e.max_participants, e.time_per_question, e.total_time, e.exam_mode, e.auto_finish_on_time, e.allow_rejoin, e.anti_cheat_enabled, e.block_tab_switch, e.require_fullscreen, e.lobby_enabled, e.show_results_to_student, e.show_predicted_grade, e.show_correct_answers, e.randomize_per_student, e.lock_after_finish, e.pass_threshold, e.max_attempts, e.navigation_mode, e.allow_answer_changes, e.warning_limit, e.warning_action, e.late_join_cutoff_minutes, e.results_available_at, e.print_include_answer_key, e.available_from, e.available_until, e.grade_thresholds, e.created_at AS exam_created_at, e.updated_at,
           u.username as teacher_name
    FROM exam_sessions es
    JOIN exams e ON es.exam_id = e.id
    JOIN users u ON e.teacher_id = u.id
    WHERE es.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session || !($hasAdminAccess || (int)$session['teacher_id'] === (int)$userId)) {
    redirect('index.php');
}

// Participants
$stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? ORDER BY score_percent DESC, time_spent ASC");
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
    SELECT ev.id, ev.participant_id, ev.session_id, ev.violation_type, ev.question_id, ev.details, ev.created_at, ep.first_name, ep.last_name, esq.question_order
    FROM exam_violations ev
    JOIN exam_participants ep ON ev.participant_id = ep.id
    LEFT JOIN exam_session_questions esq ON esq.session_id = ev.session_id AND esq.question_id = ev.question_id
    WHERE ev.session_id = ?
    ORDER BY ev.created_at DESC
    LIMIT 50
");
$stmt->execute([$sessionId]);
$violations = $stmt->fetchAll();

// Grade thresholds
$gradeThresholds = $session['grade_thresholds'] ? json_decode($session['grade_thresholds'], true) : null;

// Calculate knowledge gaps & competency heatmap
$knowledgeGaps = calculateExamKnowledgeGaps($pdo, $sessionId);
?>
<?php
$pageTitle = 'Szczegóły sprawdzianu – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
include '../includes/header.php';
?>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid p-0">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <div>
                            <h2 class="fw-bold mb-1"><?= htmlspecialchars($session['title']) ?></h2>
                            <p class="text-muted mb-0">
                                <?= $session['started_at'] ? date('d.m.Y H:i', strtotime($session['started_at'])) : '' ?>
                                – <?= $session['finished_at'] ? date('H:i', strtotime($session['finished_at'])) : 'W trakcie' ?>
                                · <?= count($finished) ?> uczestników
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="host_exam.php?exam=<?= (int)$session['exam_id'] ?>" class="btn btn-outline-primary rounded-pill">
                                <i class="bi bi-broadcast me-1"></i>Hostuj ponownie
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left me-1"></i>Powrót</a>
                        </div>
                    </div>

                    <!-- Stats cards -->
                    <div class="row g-3 mb-4">
                        <?php
                        $statusLabels = [
                            'waiting' => 'Oczekiwanie',
                            'lobby' => 'Poczekalnia',
                            'in_progress' => 'W trakcie',
                            'paused' => 'Wstrzymany',
                            'finished' => 'Zakończony',
                            'expired' => 'Wygasły',
                        ];
                        $displaySessionStatus = $statusLabels[$session['status']] ?? ucfirst($session['status']);
                        $cards = [
                            ['Średni wynik', $avgScore.'%', 'bi-bar-chart-fill', 'primary'],
                            ['Mediana', $medianScore.'%', 'bi-distribute-vertical', 'info'],
                            ['Najwyższy', $maxScore.'%', 'bi-arrow-up-circle', 'success'],
                            ['Najniższy', $minScore.'%', 'bi-arrow-down-circle', 'danger'],
                            ['Zdawalność', $passingRate.'%', 'bi-check-circle', 'warning'],
                            ['Śr. czas', formatTime($avgTime), 'bi-clock', 'secondary'],
                            ['Naruszenia', $totalViolations, 'bi-shield-exclamation', 'danger'],
                            ['Status', $displaySessionStatus, 'bi-broadcast', 'primary'],
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

                    <!-- Knowledge Gaps & Competency Heatmap -->
                    <?php if (!empty($knowledgeGaps['categories'])): ?>
                    <div class="dashboard-panel mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold mb-1"><i class="bi bi-layers-half text-primary me-2"></i>Mapa Kompetencji i Luk Wiedzy Klasy</h5>
                                <div class="small text-muted">Zestawienie opanowania poszczególnych działów na podstawie udzielonych odpowiedzi</div>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                Przeanalizowano <?= (int)$knowledgeGaps['total_evaluated'] ?> odpowiedzi
                            </span>
                        </div>

                        <?php if (!empty($knowledgeGaps['critical_alarms'])): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-3 p-3 rounded-3">
                                <i class="bi bi-exclamation-triangle-fill fs-4 me-3 flex-shrink-0"></i>
                                <div>
                                    <div class="fw-bold">Wykryto krytyczne luki wiedzy w klasie!</div>
                                    <ul class="mb-0 ps-3 small mt-1">
                                        <?php foreach ($knowledgeGaps['critical_alarms'] as $alarm): ?>
                                            <li><?= htmlspecialchars($alarm['message']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-3">
                            <?php foreach ($knowledgeGaps['categories'] as $cat): 
                                $badgeClass = $cat['status'] === 'good' ? 'bg-success' : ($cat['status'] === 'warning' ? 'bg-warning text-dark' : 'bg-danger');
                                $barClass = $cat['status'] === 'good' ? 'bg-success' : ($cat['status'] === 'warning' ? 'bg-warning' : 'bg-danger');
                            ?>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-body-tertiary">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold small text-truncate pe-2" title="<?= htmlspecialchars($cat['category']) ?>">
                                            <?= htmlspecialchars($cat['category']) ?>
                                        </span>
                                        <span class="badge <?= $badgeClass ?>"><?= $cat['accuracy'] ?>%</span>
                                    </div>
                                    <div class="progress mb-2" style="height: 10px;">
                                        <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= $cat['accuracy'] ?>%" aria-valuenow="<?= $cat['accuracy'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Poprawne: <strong><?= $cat['correct'] ?></strong> / <?= $cat['total'] ?></span>
                                        <span>Śr. czas: <strong><?= $cat['avg_time'] ?>s</strong></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($knowledgeGaps['weakest_questions'])): ?>
                            <div class="mt-3 pt-3 border-top">
                                <h6 class="fw-bold mb-2 small text-uppercase text-muted"><i class="bi bi-flag-fill text-danger me-1"></i>Pytania sprawiające klasie największą trudność (Top 5):</h6>
                                <div class="list-group list-group-flush rounded-3">
                                    <?php foreach ($knowledgeGaps['weakest_questions'] as $wq): ?>
                                    <div class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center gap-3">
                                        <div class="small">
                                            <span class="badge bg-secondary me-2"><?= htmlspecialchars($wq['category']) ?></span>
                                            <?= htmlspecialchars(mb_substr($wq['question_text'], 0, 110)) ?>...
                                        </div>
                                        <span class="badge bg-danger flex-shrink-0"><?= $wq['accuracy_pct'] ?>% poprawności</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

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
                                        <td><?= (int)$p['correct_answers'] ?>/<?= (int)$session['question_count'] ?></td>
                                        <td class="small"><?= $p['time_spent'] ? formatTime($p['time_spent']) : '—' ?></td>
                                        <td>
                                            <?php if ($p['violation_count'] > 0): ?>
                                                <span class="badge bg-danger"><?= $p['violation_count'] ?></span>
                                            <?php else: ?>
                                                <span class="text-success"><i class="bi bi-check-circle"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?= $p['status']==='finished'?'secondary':'warning' ?> bg-opacity-10 text-<?= $p['status']==='finished'?'secondary':'warning' ?>"><?= $statusLabels[$p['status']] ?? ucfirst($p['status']) ?></span></td>
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

