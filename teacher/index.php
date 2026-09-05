<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

// Only teachers and admins allowed
if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    setSessionMessage('error', 'Brak uprawnień do panelu nauczyciela.');
    redirect('../index.php');
}

$userId = $_SESSION['user_id'];
$flashMsg = getSessionMessage();

// Get teacher's exam history
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.question_count, e.max_participants, e.created_at,
               es.id as session_id, es.access_code, es.status, es.started_at, es.finished_at,
               (SELECT COUNT(*) FROM exam_participants WHERE session_id = es.id AND status != 'removed') as participant_count
        FROM exams e
        LEFT JOIN exam_sessions es ON es.exam_id = e.id
        WHERE e.teacher_id = ?
        ORDER BY e.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $exams = [];
    error_log("Teacher panel error: " . $e->getMessage());
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Błąd bezpieczeństwa (CSRF).');
        redirect('index.php');
    }

    $action = $_POST['action'] ?? '';
    $examId = (int)($_POST['exam_id'] ?? 0);

    if ($action === 'delete_exam') {
        try {
            // Check ownership
            $stmt = $pdo->prepare("SELECT id FROM exams WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$examId, $userId]);
            if ($stmt->fetch()) {
                $pdo->beginTransaction();
                // Delete linked sessions first (cascade would be better, but let's be explicit)
                $stmtS = $pdo->prepare("SELECT id FROM exam_sessions WHERE exam_id = ?");
                $stmtS->execute([$examId]);
                $sessions = $stmtS->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($sessions)) {
                    $placeholders = str_repeat('?,', count($sessions) - 1) . '?';
                    $pdo->prepare("DELETE FROM exam_violations WHERE session_id IN ($placeholders)")->execute($sessions);
                    $pdo->prepare("DELETE FROM exam_answers WHERE session_id IN ($placeholders)")->execute($sessions);
                    $pdo->prepare("DELETE FROM exam_warnings WHERE session_id IN ($placeholders)")->execute($sessions);
                    $pdo->prepare("DELETE FROM exam_participants WHERE session_id IN ($placeholders)")->execute($sessions);
                    $pdo->prepare("DELETE FROM exam_session_questions WHERE session_id IN ($placeholders)")->execute($sessions);
                    $pdo->prepare("DELETE FROM exam_sessions WHERE exam_id = ?")->execute([$examId]);
                }
                
                $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
                $pdo->commit();
                setSessionMessage('success', 'Sprawdzian i wszystkie powiązane dane zostały usunięte.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Teacher exam delete failed: ' . $e->getMessage());
            setSessionMessage('error', 'Nie udało się usunąć sprawdzianu. Spróbuj ponownie za chwilę.');
        }
        redirect('index.php');
    }

    if ($action === 'delete_results') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        try {
            // Verify ownership of the exam linked to this session
            $stmt = $pdo->prepare("SELECT es.id FROM exam_sessions es JOIN exams e ON es.exam_id = e.id WHERE es.id = ? AND e.teacher_id = ?");
            $stmt->execute([$sessionId, $userId]);
            if ($stmt->fetch()) {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM exam_violations WHERE session_id = ?")->execute([$sessionId]);
                $pdo->prepare("DELETE FROM exam_answers WHERE session_id = ?")->execute([$sessionId]);
                $pdo->prepare("DELETE FROM exam_participants WHERE session_id = ?")->execute([$sessionId]);
                $pdo->prepare("DELETE FROM exam_warnings WHERE session_id = ?")->execute([$sessionId]);
                $pdo->commit();
                // We keep the session itself but it's now empty of results
                setSessionMessage('success', 'Historia wyników dla tej sesji została wyczyszczona.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Teacher result cleanup failed: ' . $e->getMessage());
            setSessionMessage('error', 'Nie udało się wyczyścić historii. Spróbuj ponownie za chwilę.');
        }
        redirect('index.php');
    }
}

// Stats
$totalExams = count($exams);
$activeExams = array_filter($exams, fn($e) => in_array($e['status'], ['lobby', 'in_progress', 'paused']));
$totalParticipants = array_sum(array_column($exams, 'participant_count'));
?>
<?php
$pageTitle = 'Panel Nauczyciela – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
include '../includes/header.php';
?>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">

                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-clipboard2-pulse-fill me-2 text-primary"></i>Panel Nauczyciela</h2>
                            <p class="text-muted">Twórz i zarządzaj sprawdzianami dla uczniów.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="create_exam.php" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm">
                                <i class="bi bi-plus-lg me-2"></i>Utwórz test online
                            </a>
                            <a href="help.php" class="btn btn-outline-primary btn-lg rounded-pill px-4">
                                <i class="bi bi-question-circle me-2"></i>Pomoc
                            </a>
                        </div>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4 animate-in">
                            <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($flashMsg['message']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Bento Grid Top Row -->
                    <div class="row g-4 mb-4">
                        <!-- Bento Hero Card (8 cols) -->
                        <div class="col-lg-8">
                            <div class="dashboard-panel p-4 h-100 position-relative overflow-hidden bento-hero-card teacher-hero-bento" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(124, 58, 237, 0.04) 100%); border: 1px solid rgba(99, 102, 241, 0.2);">
                                <div class="position-absolute top-0 end-0 p-4 opacity-10 fs-1 text-primary d-none d-md-block">
                                    <i class="bi bi-mortarboard-fill" style="font-size: 5.5rem;"></i>
                                </div>
                                <div class="position-relative z-1">
                                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 small fw-bold mb-3">
                                        <i class="bi bi-shield-check me-1"></i> Panel Edukatora ZSEM Tech
                                    </div>
                                    <h3 class="fw-bold mb-2">Centrum Egzaminów i Sprawdzianów</h3>
                                    <p class="text-muted mb-4" style="max-width: 580px; font-size: 0.95rem;">
                                        Kompleksowe narzędzia dydaktyczne: twórz bezpieczne sesje testowe online z kodem PIN, generuj gotowe do druku arkusze egzaminacyjne A/B z kluczem odpowiedzi oraz buduj autorską bazę pytań.
                                    </p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="create_exam.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold quick-action-tile">
                                            <i class="bi bi-plus-circle-fill me-1"></i> Utwórz test online
                                        </a>
                                        <a href="pdf_generator.php" class="btn btn-outline-danger rounded-pill px-4 fw-semibold quick-action-tile">
                                            <i class="bi bi-printer-fill me-1"></i> Drukuj arkusze (PDF)
                                        </a>
                                        <a href="custom_exams.php" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold quick-action-tile">
                                            <i class="bi bi-collection-fill me-1"></i> Baza pytań
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bento Stats Card (4 cols) -->
                        <div class="col-lg-4">
                            <div class="dashboard-panel p-4 h-100 d-flex flex-column justify-content-between">
                                <h6 class="fw-bold text-muted small text-uppercase tracking-wider mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-graph-up-arrow text-primary"></i> Twoje statystyki
                                </h6>
                                <div class="d-flex flex-column gap-3">
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-body-tertiary stat-chip">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 fs-4">
                                                <i class="bi bi-journal-check"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">Sprawdzianów łącznie</div>
                                                <div class="fw-bold fs-5"><?= $totalExams ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">Baza</span>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-body-tertiary stat-chip">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="p-2 bg-success bg-opacity-10 text-success rounded-3 fs-4">
                                                <i class="bi bi-broadcast"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">Aktywne sesje</div>
                                                <div class="fw-bold fs-5 text-success"><?= count($activeExams) ?></div>
                                            </div>
                                        </div>
                                        <?php if (count($activeExams) > 0): ?>
                                            <span class="badge bg-success text-white rounded-pill px-2 py-1 small animate-pulse">Live</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-muted rounded-pill">Brak</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-body-tertiary stat-chip">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="p-2 bg-info bg-opacity-10 text-info rounded-3 fs-4">
                                                <i class="bi bi-people-fill"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">Uczestników łącznie</div>
                                                <div class="fw-bold fs-5"><?= $totalParticipants ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill">Uczniowie</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($activeExams)): ?>
                        <!-- Active Live Sessions Banner -->
                        <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-success bg-opacity-10 border-start border-success border-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="spinner-grow text-success" role="status" style="width: 1.5rem; height: 1.5rem;">
                                        <span class="visually-hidden">Live</span>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-success mb-0">Trwają aktywne sesje sprawdzianów na żywo!</h6>
                                        <p class="text-muted small mb-0">Masz <?= count($activeExams) ?> aktywną(e) sesję(e). Kliknij, aby zarządzać uczestnikami w czasie rzeczywistym.</p>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ($activeExams as $aExam): ?>
                                        <a href="host_exam.php?session=<?= (int)$aExam['session_id'] ?>" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">
                                            <i class="bi bi-broadcast me-1"></i> Kod PIN: <?= htmlspecialchars($aExam['access_code'] ?? '') ?> (<?= htmlspecialchars($aExam['title'] ?? '') ?>)
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Bento Quick Tools Grid (4 columns) -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6 col-xl-3">
                            <a href="create_exam.php" class="dashboard-panel d-flex align-items-center gap-3 text-decoration-none hover-scale animate-in p-3 h-100 border">
                                <div class="icon-circle bg-primary bg-opacity-10 text-primary fs-3 p-3 rounded-3 flex-shrink-0">
                                    <i class="bi bi-plus-square-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-body mb-1">Kreator testu</h6>
                                    <p class="text-muted small mb-0">Egzamin online z kodem PIN.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <a href="pdf_generator.php" class="dashboard-panel d-flex align-items-center gap-3 text-decoration-none hover-scale animate-in p-3 h-100 border" style="animation-delay: 0.05s">
                                <div class="icon-circle bg-danger bg-opacity-10 text-danger fs-3 p-3 rounded-3 flex-shrink-0">
                                    <i class="bi bi-file-earmark-pdf-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-body mb-1">Generator PDF / Druk</h6>
                                    <p class="text-muted small mb-0">Arkusze A/B z kluczem ocen.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <a href="custom_exams.php" class="dashboard-panel d-flex align-items-center gap-3 text-decoration-none hover-scale animate-in p-3 h-100 border" style="animation-delay: 0.1s">
                                <div class="icon-circle bg-warning bg-opacity-10 text-warning fs-3 p-3 rounded-3 flex-shrink-0">
                                    <i class="bi bi-collection-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-body mb-1">Baza pytań własnych</h6>
                                    <p class="text-muted small mb-0">Autorskie zadania i testy.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <a href="txt_generator.php" class="dashboard-panel d-flex align-items-center gap-3 text-decoration-none hover-scale animate-in p-3 h-100 border" style="animation-delay: 0.15s">
                                <div class="icon-circle bg-info bg-opacity-10 text-info fs-3 p-3 rounded-3 flex-shrink-0">
                                    <i class="bi bi-file-earmark-arrow-up-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-body mb-1">Generator z TXT</h6>
                                    <p class="text-muted small mb-0">Masowy import z pliku.</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Exam History -->
                    <div class="dashboard-panel animate-in" style="animation-delay:0.3s">
                        <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                            <h5 class="panel-title mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Historia sprawdzianów</h5>
                            <?php if (!empty($exams)): ?>
                                <div class="input-group input-group-sm rounded-pill overflow-hidden border border-secondary border-opacity-25" style="max-width: 260px;">
                                    <span class="input-group-text bg-transparent border-0 pe-1"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="examFilterInput" class="form-control bg-transparent border-0 shadow-none ps-1" placeholder="Filtruj sprawdziany..." oninput="filterTeacherExams(this.value)">
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($exams)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x display-1 text-muted opacity-25 mb-3 d-block"></i>
                                <p class="text-muted mb-3">Nie masz jeszcze żadnych sprawdzianów.</p>
                                <a href="create_exam.php" class="btn btn-primary rounded-pill px-4">
                                    <i class="bi bi-plus-lg me-1"></i>Utwórz pierwszy sprawdzian
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="teacherExamsTable">
                                    <thead>
                                        <tr class="text-muted small text-nowrap">
                                            <th>NAZWA</th>
                                            <th>STATUS</th>
                                            <th>KOD</th>
                                            <th>UCZESTNICY</th>
                                            <th>PYTANIA</th>
                                            <th>DATA</th>
                                            <th>AKCJA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exams as $exam): ?>
                                        <tr class="exam-row">
                                            <td>
                                                <div class="fw-bold exam-title"><?= htmlspecialchars($exam['title']) ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadge = match($exam['status'] ?? 'none') {
                                                    'lobby' => '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25"><i class="bi bi-hourglass-split me-1"></i>Lobby</span>',
                                                    'in_progress' => '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25"><i class="bi bi-broadcast me-1 animate-pulse"></i>W trakcie</span>',
                                                    'paused' => '<span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-25"><i class="bi bi-pause-circle me-1"></i>Wstrzymany</span>',
                                                    'finished' => '<span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25">Zakończony</span>',
                                                    'expired' => '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25">Wygasły</span>',
                                                    default => '<span class="badge bg-secondary bg-opacity-10 text-muted">Niehostowany</span>',
                                                };
                                                echo $statusBadge;
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['access_code']): ?>
                                                    <code class="fw-bold text-primary px-2 py-1 bg-primary bg-opacity-10 rounded exam-code"><?= htmlspecialchars($exam['access_code']) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <span class="fw-bold"><?= (int)($exam['participant_count'] ?? 0) ?></span>
                                                <span class="text-muted">/ <?= $exam['max_participants'] ?></span>
                                            </td>
                                            <td class="text-nowrap"><?= $exam['question_count'] ?></td>
                                            <td class="small text-muted text-nowrap"><?= date('d.m.Y H:i', strtotime($exam['created_at'])) ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <?php if (in_array($exam['status'], ['lobby', 'in_progress', 'paused'])): ?>
                                                        <a href="host_exam.php?session=<?= $exam['session_id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                                            <i class="bi bi-broadcast me-1"></i>Zarządzaj
                                                        </a>
                                                    <?php elseif ($exam['status'] === 'finished'): ?>
                                                        <a href="exam_details.php?session=<?= $exam['session_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                            <i class="bi bi-bar-chart me-1"></i>Wyniki
                                                        </a>
                                                        <form method="POST" class="d-inline" onsubmit="return appConfirmSubmit(this, 'Czy na pewno chcesz usunąć wyniki tego sprawdzianu?')">
                                                            <?php echo csrfTokenField(); ?>
                                                            <input type="hidden" name="action" value="delete_results">
                                                            <input type="hidden" name="session_id" value="<?= $exam['session_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Usuń wyniki" style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;">
                                                                <i class="bi bi-trash3"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($exam['status'] === 'expired' || !$exam['session_id']): ?>
                                                        <a href="host_exam.php?exam=<?= $exam['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3">
                                                            <i class="bi bi-play-fill me-1"></i>Hostuj
                                                        </a>
                                                    <?php endif; ?>

                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown" style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;">
                                                            <i class="bi bi-three-dots-vertical"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                            <li><a class="dropdown-item" href="host_exam.php?exam=<?= $exam['id'] ?>"><i class="bi bi-broadcast me-2 text-info"></i>Nowa sesja (Hostuj)</a></li>
                                                            <?php if (!empty($exam['session_id'])): ?>
                                                                <li><a class="dropdown-item" href="exam_details.php?session=<?= $exam['session_id'] ?>"><i class="bi bi-bar-chart me-2 text-primary"></i>Arkusz ocen</a></li>
                                                            <?php endif; ?>
                                                            <li><a class="dropdown-item" href="edit_exam.php?id=<?= $exam['id'] ?>"><i class="bi bi-pencil me-2 text-secondary"></i>Edytuj</a></li>
                                                            <li><a class="dropdown-item" href="clone_exam.php?id=<?= $exam['id'] ?>"><i class="bi bi-copy me-2 text-secondary"></i>Duplikuj (Kopia)</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="copyShareLink('<?= $exam['id'] ?>'); return false;"><i class="bi bi-share me-2 text-success"></i>Udostępnij link</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" onsubmit="return appConfirmSubmit(this, 'Czy na pewno chcesz usunąć ten sprawdzian i wszystkie jego sesje?')">
                                                                    <?php echo csrfTokenField(); ?>
                                                                    <input type="hidden" name="action" value="delete_exam">
                                                                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Usuń sprawdzian</button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        function filterTeacherExams(query) {
            const term = (query || '').toLowerCase().trim();
            const rows = document.querySelectorAll('#teacherExamsTable tbody tr.exam-row');
            rows.forEach(row => {
                const title = row.querySelector('.exam-title')?.textContent?.toLowerCase() || '';
                const code = row.querySelector('.exam-code')?.textContent?.toLowerCase() || '';
                const match = !term || title.includes(term) || code.includes(term);
                row.style.display = match ? '' : 'none';
            });
        }

        function copyShareLink(id) {
            const baseUrl = window.location.origin + window.location.pathname.replace('index.php', '');
            const shareUrl = baseUrl + 'clone_exam.php?id=' + id;
            
            navigator.clipboard.writeText(shareUrl).then(() => {
                appNotice('Link do udostępniania sprawdzianu został skopiowany.', 'success');
            }).catch(err => {
                console.error('Could not copy text: ', err);
                appNotice('Nie udało się skopiować. Link: ' + shareUrl, 'warning');
            });
        }
    </script>
</body>
</html>
