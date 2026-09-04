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

// Load session info
$stmt = $pdo->prepare("
    SELECT es.id, es.exam_id, es.access_code, es.status, es.started_at, es.paused_at, es.paused_seconds, es.finished_at, es.expires_at, es.created_at, e.title, e.show_results_to_student, e.show_predicted_grade, e.show_correct_answers,
           e.results_available_at,
           e.grade_thresholds, e.question_count, e.pass_threshold, u.username as teacher_name
    FROM exam_sessions es
    JOIN exams e ON es.exam_id = e.id
    JOIN users u ON e.teacher_id = u.id
    WHERE es.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) { redirect('../index.php'); }

// Get participant data (get the latest one for this session/user)
if ($isGuest) {
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND id = ? AND user_id IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId, guestExamParticipantId($sessionId)]);
} else {
    $stmt = $pdo->prepare("SELECT id, session_id, user_id, first_name, last_name, class, status, current_question, correct_answers, total_answered, score_percent, time_spent, violation_count, started_at, finished_at, joined_at, last_activity FROM exam_participants WHERE session_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId, $userId]);
}
$participant = $stmt->fetch();

if (!$participant) { redirect('../index.php'); }

$showResultsToStudent = (bool)$session['show_results_to_student'];
$resultsAvailableAt = $session['results_available_at'] ?? null;
if ($resultsAvailableAt && strtotime($resultsAvailableAt) > time()) {
    $showResultsToStudent = false;
}
$showCorrectAnswers = (bool)$session['show_correct_answers'];
$passedThreshold = (float)$participant['score_percent'] >= (int)($session['pass_threshold'] ?? 50);

// Mark as finished if not already
if ($participant['status'] !== 'finished') {
    $totalTimeSpent = time() - strtotime($participant['started_at'] ?? $session['started_at']);
    $pdo->prepare("UPDATE exam_participants SET status = 'finished', finished_at = NOW(), time_spent = ? WHERE id = ?")
        ->execute([$totalTimeSpent, $participant['id']]);
    $participant['status'] = 'finished';
}

// Calculate grade if enabled
$grade = null;
if ($session['show_predicted_grade'] && $session['grade_thresholds']) {
    $thresholds = json_decode($session['grade_thresholds'], true);
    $score = (float)$participant['score_percent'];
    if ($score >= ($thresholds['6'] ?? 95)) $grade = 6;
    elseif ($score >= ($thresholds['5'] ?? 85)) $grade = 5;
    elseif ($score >= ($thresholds['4'] ?? 70)) $grade = 4;
    elseif ($score >= ($thresholds['3'] ?? 50)) $grade = 3;
    elseif ($score >= ($thresholds['2'] ?? 30)) $grade = 2;
    else $grade = 1;
}

// Get answers if results shown
$answers = [];
if ($showResultsToStudent) {
    $stmt = $pdo->prepare("SELECT id, participant_id, session_id, question_id, question_order, user_answer, correct_answer, is_correct, time_spent, answered_at FROM exam_answers WHERE participant_id = ? AND session_id = ? ORDER BY question_order");
    $stmt->execute([$participant['id'], $sessionId]);
    $answers = $stmt->fetchAll();

    // Resolve only answered questions from DB, with JSON fallback when needed.
    if (!empty($answers)) {
        $qMap = [];
        foreach (getQuestionsByIds($pdo, array_column($answers, 'question_id')) as $q) {
            $qMap[(int)$q['id']] = $q;
        }

        foreach ($answers as &$a) {
            if (isset($qMap[$a['question_id']])) {
                $q = $qMap[$a['question_id']];
                $a['question_text'] = $q['question_text'];
                $a['option_a'] = $q['option_a'];
                $a['option_b'] = $q['option_b'];
                $a['option_c'] = $q['option_c'];
                $a['option_d'] = $q['option_d'];
                $a['explanation'] = $q['explanation'] ?? '';
            } else {
                $a['question_text'] = "[Nie znaleziono treści pytania]";
                $a['option_a'] = $a['option_b'] = $a['option_c'] = $a['option_d'] = "-";
                $a['explanation'] = "";
            }
        }
    }
}

// Ranking
$ranking = [];
if ($showResultsToStudent) {
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, class, score_percent, correct_answers, total_answered, time_spent, violation_count
        FROM exam_participants WHERE session_id = ? AND status = 'finished'
        ORDER BY score_percent DESC, time_spent ASC
    ");
    $stmt->execute([$sessionId]);
    $ranking = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wyniki sprawdzianu – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard-new.css">
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="../assets/js/devtools-guard.js"></script>
    <script src="../assets/js/theme-handler.js"></script>
</head>
<body>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            
                            <!-- Result Header -->
                            <div class="dashboard-panel text-center mb-4 animate-in">
                                <i class="bi bi-check-circle-fill display-1 text-success mb-3 d-block"></i>
                                <h2 class="fw-bold">Sprawdzian zakończony!</h2>
                                <p class="text-muted"><?= htmlspecialchars($session['title']) ?></p>

                                <?php if ($showResultsToStudent): ?>
                                <div class="row g-3 my-4 justify-content-center">
                                    <div class="col-auto">
                                        <div class="bg-primary bg-opacity-10 rounded-3 p-3 px-4 text-center">
                                            <div class="h2 fw-800 text-primary mb-0"><?= round($participant['score_percent']) ?>%</div>
                                            <div class="small text-muted">Wynik</div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="bg-success bg-opacity-10 rounded-3 p-3 px-4 text-center">
                                            <div class="h2 fw-800 text-success mb-0"><?= $participant['correct_answers'] ?>/<?= $participant['total_answered'] ?></div>
                                            <div class="small text-muted">Poprawnych</div>
                                        </div>
                                    </div>
                                    <?php if ($grade !== null): ?>
                                    <div class="col-auto">
                                        <div class="bg-warning bg-opacity-10 rounded-3 p-3 px-4 text-center">
                                            <div class="h2 fw-800 text-warning mb-0"><?= $grade ?></div>
                                            <div class="small text-muted">Ocena</div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-auto">
                                        <div class="bg-<?php echo $passedThreshold ? 'success' : 'danger'; ?> bg-opacity-10 rounded-3 p-3 px-4 text-center">
                                            <div class="h2 fw-800 text-<?php echo $passedThreshold ? 'success' : 'danger'; ?> mb-0"><?php echo $passedThreshold ? 'TAK' : 'NIE'; ?></div>
                                            <div class="small text-muted">Zaliczone</div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="bg-info bg-opacity-10 rounded-3 p-3 px-4 text-center">
                                            <div class="h2 fw-800 text-info mb-0"><?= $participant['time_spent'] ? formatTime($participant['time_spent']) : '—' ?></div>
                                            <div class="small text-muted">Czas</div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info border-0 mt-4 mb-0">
                                    <?php if ($resultsAvailableAt && strtotime($resultsAvailableAt) > time()): ?>
                                        Wyniki będą dostępne od <?= htmlspecialchars(date('d.m.Y H:i', strtotime($resultsAvailableAt))) ?>.
                                    <?php else: ?>
                                        Nauczyciel ukrył wyniki do czasu omówienia sprawdzianu.
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ranking -->
                            <?php if (!empty($ranking)): ?>
                            <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.1s">
                                <h5 class="fw-bold mb-3"><i class="bi bi-trophy me-2 text-warning"></i>Ranking uczestników</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr class="text-muted small"><th>#</th><th>Uczestnik</th><th>Klasa</th><th>Wynik</th><th>Czas</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($ranking as $i => $r): ?>
                                            <tr class="<?= $r['first_name'] === $participant['first_name'] && $r['last_name'] === $participant['last_name'] ? 'table-primary' : '' ?>">
                                                <td>
                                                    <?php if ($i === 0): ?><i class="bi bi-trophy-fill text-warning"></i>
                                                    <?php elseif ($i === 1): ?><i class="bi bi-trophy-fill text-secondary"></i>
                                                    <?php elseif ($i === 2): ?><i class="bi bi-trophy-fill" style="color:#cd7f32"></i>
                                                    <?php else: ?><?= $i + 1 ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                                <td class="text-muted"><?= htmlspecialchars($r['class']) ?></td>
                                                <td><span class="badge bg-<?= $r['score_percent'] >= 50 ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $r['score_percent'] >= 50 ? 'success' : 'danger' ?>"><?= round($r['score_percent']) ?>%</span></td>
                                                <td class="small text-muted"><?= $r['time_spent'] ? formatTime($r['time_spent']) : '—' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Answers review -->
                            <?php if (!empty($answers)): ?>
                            <div class="dashboard-panel mb-4 animate-in" style="animation-delay:0.2s">
                                <h5 class="fw-bold mb-3"><i class="bi bi-list-check me-2"></i>Twoje odpowiedzi</h5>
                                <?php foreach ($answers as $idx => $a): ?>
                                <div class="border rounded p-3 mb-3 <?= $a['is_correct'] ? 'border-success' : 'border-danger' ?> bg-opacity-10 bg-<?= $a['is_correct'] ? 'success' : 'danger' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong class="small">Pytanie <?= $idx + 1 ?></strong>
                                        <?php if ($a['is_correct']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check me-1"></i>Poprawna</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x me-1"></i>Błędna</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1 small"><?= htmlspecialchars(mb_substr($a['question_text'], 0, 200)) ?></p>
                                    <div class="small text-muted">
                                        Twoja: <strong><?= $a['user_answer'] ?: '—' ?></strong>
                                        <?php if (!$a['is_correct'] && $showCorrectAnswers): ?>
                                            | Poprawna: <strong class="text-success"><?= $a['correct_answer'] ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$a['is_correct'] && $showCorrectAnswers && !empty($a['explanation'])): ?>
                                        <div class="mt-2 p-2 bg-white bg-opacity-25 rounded small border-start border-4 border-info">
                                            <i class="bi bi-info-circle-fill me-1 text-info"></i><strong>Wyjaśnienie:</strong><br>
                                            <?= nl2br(htmlspecialchars($a['explanation'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <?php
                            $returnUrl = (!empty($_SESSION['user_id'])) ? '../index.php' : 'join.php';
                            $returnLabel = (!empty($_SESSION['user_id'])) ? 'Powrót do panelu' : 'Dołącz do innego testu';
                            ?>
                            <div class="text-center mb-4">
                                <a href="<?= $returnUrl ?>" class="btn btn-primary rounded-pill px-5">
                                    <i class="bi <?= !empty($_SESSION['user_id']) ? 'bi-house' : 'bi-qr-code-scan' ?> me-1"></i><?= $returnLabel ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
