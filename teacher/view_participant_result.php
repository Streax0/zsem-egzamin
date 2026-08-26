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

$participantId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

// Load participant and session info
$stmt = $pdo->prepare("
    SELECT ep.id, ep.session_id, ep.user_id, ep.first_name, ep.last_name, ep.class, ep.status, ep.current_question, ep.correct_answers, ep.total_answered, ep.score_percent, ep.time_spent, ep.violation_count, ep.started_at, ep.finished_at, ep.joined_at, ep.last_activity, es.status as session_status, es.access_code, e.title as exam_title, e.teacher_id
    FROM exam_participants ep
    JOIN exam_sessions es ON ep.session_id = es.id
    JOIN exams e ON es.exam_id = e.id
    WHERE ep.id = ?
");
$stmt->execute([$participantId]);
$participant = $stmt->fetch();

if (!$participant) {
    setSessionMessage('error', 'Uczestnik nie istnieje.');
    redirect('my_exams.php');
}

// Security: Check if teacher owns this exam
if (!roleHasAdminAccess($_SESSION['role'] ?? '') && $participant['teacher_id'] != $userId) {
    setSessionMessage('error', 'Brak uprawnień do podglądu tego wyniku.');
    redirect('my_exams.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'exam_answer_override')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('view_participant_result.php?id=' . $participantId);
    }
    $questionId = (int)($_POST['question_id'] ?? 0);
    $newCorrect = strtoupper(trim((string)($_POST['correct_answer'] ?? '')));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if (applyExamCorrectAnswerOverride($pdo, $participantId, $questionId, $newCorrect, $userId, $reason)) {
        try {
            $stmtAdmins = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'dyrektor')");
            foreach ($stmtAdmins->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
                addNotification($pdo, (int)$adminId, 'admin_request', 'Nowa prośba o weryfikację pytania po sprawdzianie.', 'admin_requests.php');
            }
        } catch (PDOException $e) {
            error_log('Question override admin notify failed: ' . $e->getMessage());
        }
        setSessionMessage('success', 'Poprawna odpowiedź zmieniona tylko dla tej sesji. Wysłano prośbę o weryfikację do administracji.');
    } else {
        setSessionMessage('error', 'Nie udało się zmienić odpowiedzi.');
    }
    redirect('view_participant_result.php?id=' . $participantId);
}

// Load answers
$stmt = $pdo->prepare("SELECT id, participant_id, session_id, question_id, question_order, user_answer, correct_answer, is_correct, time_spent, answered_at FROM exam_answers WHERE participant_id = ? ORDER BY question_order");
$stmt->execute([$participantId]);
$answers = $stmt->fetchAll();

// Load session question list (to see what was assigned)
$stmt = $pdo->prepare("SELECT question_id, question_order, correct_answer_override FROM exam_session_questions WHERE session_id = ? ORDER BY question_order");
$stmt->execute([$participant['session_id']]);
$sessionQuestions = $stmt->fetchAll();
$sessionQuestionIds = array_map('intval', array_column($sessionQuestions, 'question_id'));
$allQuestionsMap = [];
foreach (getQuestionsByIds($pdo, $sessionQuestionIds) as $question) {
    $allQuestionsMap[(int)$question['id']] = $question;
}
$flashMsg = getSessionMessage();
$pageTitle = 'Szczegóły wyniku: ' . htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']);
$extraCss = ['assets/css/dashboard-new.css'];
include '../includes/header.php';
?>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold mb-0">Szczegóły uczestnika</h2>
                            <p class="text-muted"><?= htmlspecialchars($participant['exam_title']) ?> (Kod: <?= $participant['access_code'] ?>)</p>
                        </div>
                        <a href="host_exam.php?session=<?= $participant['session_id'] ?>" class="btn btn-outline-secondary rounded-pill">
                            <i class="bi bi-arrow-left me-1"></i>Powrót do sesji
                        </a>
                    </div>
                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= $flashMsg['type'] === 'success' ? 'success' : 'danger' ?> border-0 shadow-sm">
                            <?= htmlspecialchars($flashMsg['message']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="dashboard-panel h-100">
                                <h5 class="fw-bold mb-4 border-bottom pb-2">Informacje</h5>
                                <div class="mb-3">
                                    <label class="small text-muted d-block">Uczeń</label>
                                    <span class="fw-bold h5"><?= htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']) ?></span>
                                    <span class="badge bg-light text-dark border ms-2"><?= htmlspecialchars($participant['class']) ?></span>
                                </div>
                                <div class="mb-4">
                                    <label class="small text-muted d-block">Status</label>
                                    <span class="badge bg-<?= $participant['status'] === 'finished' ? 'success' : 'warning' ?> bg-opacity-10 text-<?= $participant['status'] === 'finished' ? 'success' : 'warning' ?> px-3">
                                        <?= strtoupper($participant['status']) ?>
                                    </span>
                                </div>

                                <div class="row g-2 mb-4">
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded">
                                            <div class="text-muted small">Wynik</div>
                                            <div class="h3 fw-bold text-primary mb-0"><?= round($participant['score_percent']) ?>%</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded">
                                            <div class="text-muted small">Poprawne</div>
                                            <div class="h3 fw-bold text-success mb-0"><?= $participant['correct_answers'] ?>/<?= $participant['total_answered'] ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded">
                                            <div class="text-muted small">Czas</div>
                                            <div class="h4 fw-bold mb-0"><?= $participant['time_spent'] ? formatTime($participant['time_spent']) : '--:--' ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded">
                                            <div class="text-muted small">Naruszenia</div>
                                            <div class="h4 fw-bold text-<?= $participant['violation_count'] > 0 ? 'danger' : 'muted' ?> mb-0"><?= $participant['violation_count'] ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($participant['status'] === 'taking_exam'): ?>
                                <div class="alert alert-info border-0 small">
                                    <i class="bi bi-arrow-repeat me-1"></i> Uczeń jest w trakcie rozwiązywania. Ten podgląd odświeża się automatycznie.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="dashboard-panel">
                                <h5 class="fw-bold mb-4 border-bottom pb-2">Analiza odpowiedzi</h5>
                                <div class="d-flex flex-column gap-3">
                                    <?php 
                                    $answeredMap = [];
                                    foreach ($answers as $a) $answeredMap[$a['question_id']] = $a;

                                    foreach ($sessionQuestions as $sq): 
                                        $qId = $sq['question_id'];
                                        $qData = $allQuestionsMap[$qId] ?? null;
                                        $userAnswer = $answeredMap[$qId] ?? null;
                                        $effectiveCorrect = strtoupper((string)($sq['correct_answer_override'] ?: ($qData['correct_answer'] ?? ($userAnswer['correct_answer'] ?? ''))));
                                    ?>
                                        <div class="border rounded p-3 <?= $userAnswer ? ($userAnswer['is_correct'] ? 'border-success' : 'border-danger') : 'border-light bg-light' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge bg-secondary">Pytanie <?= $sq['question_order'] ?></span>
                                                <?php if ($userAnswer): ?>
                                                    <?php if ($userAnswer['is_correct']): ?>
                                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Poprawne</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Błędne</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Brak odpowiedzi</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="fw-medium mb-3"><?= $qData ? htmlspecialchars($qData['question_text']) : '<span class="text-danger">Nie znaleziono treści pytania</span>' ?></p>
                                            
                                            <?php if ($qData): ?>
                                            <div class="row g-2 small mb-3">
                                                <?php foreach (['A','B','C','D'] as $opt): 
                                                    $optText = $qData['option_'.strtolower($opt)] ?? '';
                                                    if (!$optText) continue;
                                                    $isCorrect = ($effectiveCorrect === $opt);
                                                    $isUser = ($userAnswer && strtoupper($userAnswer['user_answer']) === $opt);
                                                    
                                                    $class = 'bg-white border';
                                                    if ($isCorrect) $class = 'bg-success bg-opacity-10 border-success text-success fw-bold';
                                                    if ($isUser && !$isCorrect) $class = 'bg-danger bg-opacity-10 border-danger text-danger fw-bold';
                                                ?>
                                                    <div class="col-md-6">
                                                        <div class="p-2 rounded <?= $class ?>">
                                                            <span class="me-2"><?= $opt ?>.</span>
                                                            <?= htmlspecialchars($optText) ?>
                                                            <?php if ($isUser): ?> <i class="bi bi-person-fill ms-1" title="Wybór ucznia"></i><?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($userAnswer): ?>
                                            <div class="d-flex justify-content-between align-items-center opacity-75 small border-top pt-2 mt-2">
                                                <div>Czas spędzony: <strong><?= $userAnswer['time_spent'] ?>s</strong></div>
                                                <div>Data: <strong><?= date('H:i:s', strtotime($userAnswer['answered_at'])) ?></strong></div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($qData): ?>
                                            <form method="POST" class="row g-2 align-items-end mt-3 border-top pt-3">
                                                <?= csrfTokenField('exam_answer_override') ?>
                                                <input type="hidden" name="question_id" value="<?= (int)$qId ?>">
                                                <div class="col-md-3">
                                                    <label class="form-label small fw-bold">Poprawna dla tej sesji</label>
                                                    <select name="correct_answer" class="form-select form-select-sm">
                                                        <?php foreach (['A','B','C','D'] as $letter): ?>
                                                            <option value="<?= $letter ?>" <?= $effectiveCorrect === $letter ? 'selected' : '' ?>><?= $letter ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-bold">Powód / notatka dla admina</label>
                                                    <input name="reason" class="form-control form-control-sm" maxlength="255" placeholder="np. błąd w kluczu odpowiedzi">
                                                </div>
                                                <div class="col-md-3">
                                                    <button class="btn btn-sm btn-outline-primary w-100" type="submit">
                                                        <i class="bi bi-check2-square me-1"></i>Zmień i zgłoś
                                                    </button>
                                                </div>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php if (in_array($participant['session_status'] ?? '', ['lobby', 'in_progress', 'paused'], true) && ($participant['status'] ?? '') !== 'finished'): ?>
    <script>
    (() => {
        const key = 'zsem.participant.scroll.<?= (int)$participantId ?>';
        const saved = sessionStorage.getItem(key);
        if (saved !== null) window.scrollTo(0, Number(saved) || 0);
        let editing = false;
        document.addEventListener('focusin', (event) => {
            if (event.target.closest('input, textarea, select')) editing = true;
        });
        document.addEventListener('focusout', () => {
            setTimeout(() => { editing = false; }, 1500);
        });
        setInterval(() => {
            if (editing || document.hidden) return;
            sessionStorage.setItem(key, String(window.scrollY || 0));
            window.location.reload();
        }, 5000);
    })();
    </script>
    <?php endif; ?>
    <?php include '../includes/help_center.php'; ?>
</body>
</html>
