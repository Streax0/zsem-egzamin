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
$customDir = __DIR__ . '/../data/custom_tests';
if (!is_dir($customDir)) mkdir($customDir, 0755, true);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('custom_exams.php');
    }
    $file = basename($_POST['file'] ?? '');
    $path = $customDir . '/' . $file;
    if ($file && file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
        if (($data['teacher_id'] ?? 0) == $userId) {
            unlink($path);
            setSessionMessage('success', 'Sprawdzian usunięty.');
        }
    }
    redirect('custom_exams.php');
}

// Load all custom exams for this teacher
$customExams = [];
foreach (glob($customDir . '/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data && ($data['teacher_id'] ?? 0) == $userId) {
        $data['_filename'] = basename($file);
        $customExams[] = $data;
    }
}
usort($customExams, fn($a, $b) => strtotime($b['updated_at'] ?? $b['created_at'] ?? 'now') - strtotime($a['updated_at'] ?? $a['created_at'] ?? 'now'));

$difficultyLabels = ['easy' => 'Łatwy', 'medium' => 'Średni', 'hard' => 'Trudny', 'mixed' => 'Mieszany'];
$totalQuestions = array_sum(array_map(static fn($exam) => count($exam['questions'] ?? []), $customExams));
$hostableCount = count(array_filter($customExams, static fn($exam) => count($exam['questions'] ?? []) > 0));

$flashMsg = getSessionMessage();
?>
<?php
$pageTitle = 'Moje sprawdziany – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
include '../includes/header.php';
?>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid p-0">

                    <div class="d-flex justify-content-between align-items-center mb-4 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2 text-primary"></i>Moje sprawdziany</h2>
                            <p class="text-muted">Twórz własne sprawdziany online z własnymi pytaniami.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="custom_exam_edit.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                <i class="bi bi-plus-lg me-1"></i>Utwórz nowy
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i>Panel
                            </a>
                        </div>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= ($flashMsg['type'] === 'error') ? 'danger' : 'success'; ?> border-0 shadow-sm mb-4">
                            <?= htmlspecialchars($flashMsg['message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($customExams)): ?>
                        <div class="dashboard-panel text-center py-5 animate-in">
                            <i class="bi bi-journal-plus display-1 text-muted opacity-25 mb-3 d-block"></i>
                            <h5 class="fw-bold mb-2">Brak sprawdzianów</h5>
                            <p class="text-muted mb-4">Nie masz jeszcze żadnych własnych sprawdzianów.</p>
                            <a href="custom_exam_edit.php" class="btn btn-primary rounded-pill px-5">
                                <i class="bi bi-plus-lg me-1"></i>Utwórz sprawdzian
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="dashboard-panel h-100">
                                    <div class="text-muted small fw-bold text-uppercase">Sprawdziany</div>
                                    <div class="display-6 fw-900 mb-0"><?= count($customExams) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-panel h-100">
                                    <div class="text-muted small fw-bold text-uppercase">Pytania</div>
                                    <div class="display-6 fw-900 mb-0"><?= (int)$totalQuestions ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-panel h-100">
                                    <div class="text-muted small fw-bold text-uppercase">Gotowe do hostowania</div>
                                    <div class="display-6 fw-900 mb-0"><?= (int)$hostableCount ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-4">
                            <?php foreach ($customExams as $exam): ?>
                            <?php
                                $questionCount = count($exam['questions'] ?? []);
                                $timeLimit = (int)($exam['time_limit'] ?? 0);
                                $difficulty = $difficultyLabels[$exam['difficulty'] ?? 'mixed'] ?? 'Mieszany';
                                $tags = array_slice($exam['tags'] ?? [], 0, 4);
                            ?>
                            <div class="col-md-6 col-lg-4 animate-in">
                                <div class="dashboard-panel h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($exam['title']) ?></h5>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown" style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="custom_exam_edit.php?file=<?= urlencode($exam['_filename']) ?>"><i class="bi bi-pencil me-2 text-primary"></i>Edytuj</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" onsubmit="return appConfirmSubmit(this, 'Usunąć ten sprawdzian?')">
                                                        <?= csrfTokenField() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="file" value="<?= htmlspecialchars($exam['_filename']) ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Usuń</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php if (!empty($exam['description'])): ?>
                                        <p class="text-muted small mb-3"><?= htmlspecialchars(mb_substr($exam['description'], 0, 80)) ?></p>
                                    <?php endif; ?>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">
                                            <i class="bi bi-question-circle me-1"></i><?= $questionCount ?> pytań
                                        </span>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">
                                            <i class="bi bi-clock me-1"></i><?= $timeLimit > 0 ? $timeLimit . ' min' : 'bez limitu' ?>
                                        </span>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2">
                                            <i class="bi bi-bar-chart me-1"></i><?= htmlspecialchars($difficulty) ?>
                                        </span>
                                        <span class="text-muted small">
                                            <i class="bi bi-clock me-1"></i><?= date('d.m.Y', strtotime($exam['updated_at'] ?? $exam['created_at'] ?? 'now')) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($tags)): ?>
                                        <div class="d-flex flex-wrap gap-1 mb-3">
                                            <?php foreach ($tags as $tag): ?>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2">
                                        <a href="custom_exam_edit.php?file=<?= urlencode($exam['_filename']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 flex-grow-1">
                                            <i class="bi bi-pencil me-1"></i>Edytuj
                                        </a>
                                        <a href="create_exam.php?custom_file=<?= urlencode($exam['_filename']) ?>" class="btn btn-sm btn-success rounded-pill px-3 flex-grow-1">
                                            <i class="bi bi-play-fill me-1"></i>Hostuj
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
