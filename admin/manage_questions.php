<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

// Only admins allowed to manage global question bank
if (!isAdmin($pdo, $_SESSION['user_id'])) {
    setSessionMessage('error', 'Brak uprawnień do zarządzania bazą pytań.');
    redirect('index.php');
}

// Handle POST actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token, 'manage_questions')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('manage_questions.php');
    }

    $action = $_POST['action'] ?? '';
    $questionId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    $data = [
        'category' => trim($_POST['category'] ?? 'Ogólne'),
        'question_text' => trim($_POST['question_text'] ?? ''),
        'option_a' => trim($_POST['option_a'] ?? ''),
        'option_b' => trim($_POST['option_b'] ?? ''),
        'option_c' => trim($_POST['option_c'] ?? ''),
        'option_d' => trim($_POST['option_d'] ?? ''),
        'correct_answer' => strtoupper(trim($_POST['correct_answer'] ?? 'A')),
        'explanation' => trim($_POST['explanation'] ?? ''),
        'image_url' => trim($_POST['image_url'] ?? '')
    ];

    switch ($action) {
        case 'add':
            if (empty($data['question_text'])) {
                setSessionMessage('error', 'Treść pytania nie może być pusta.');
            } elseif (addQuestion($pdo, $data)) {
                setSessionMessage('success', 'Pytanie zostało dodane.');
            } else {
                setSessionMessage('error', 'Błąd podczas dodawania pytania.');
            }
            break;

        case 'edit':
            if ($questionId <= 0) {
                setSessionMessage('error', 'Nieprawidłowe ID pytania.');
            } elseif (updateQuestion($pdo, $questionId, $data)) {
                setSessionMessage('success', 'Pytanie zostało zaktualizowane.');
            } else {
                setSessionMessage('error', 'Błąd podczas aktualizacji pytania.');
            }
            break;

        case 'delete':
            if ($questionId <= 0) {
                setSessionMessage('error', 'Nieprawidłowe ID pytania.');
            } elseif (deleteQuestion($pdo, $questionId)) {
                setSessionMessage('success', 'Pytanie zostało usunięte.');
            } else {
                setSessionMessage('error', 'Błąd podczas usuwania pytania.');
            }
            break;
    }
    redirect('manage_questions.php' . (isset($_GET['q']) ? '?q='.urlencode($_GET['q']) : '') . (isset($_GET['cat']) ? '&cat='.urlencode($_GET['cat']) : ''));
}

// Listing / search / pagination
$search = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['cat'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($search !== '') {
    $whereClauses[] = "(question_text LIKE ? OR explanation LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($catFilter !== '') {
    $whereClauses[] = "category = ?";
    $params[] = $catFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$stmt = $pdo->prepare("SELECT * FROM questions $whereSql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM questions $whereSql");
$countStmt->execute($params);
$totalQuestions = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalQuestions / $limit));
$categories = getAllCategories($pdo);

$rawFlash = getSessionMessage();
$flashMessage = $rawFlash['message'] ?? '';
$flashType = $rawFlash['type'] ?? 'info';
?>
<?php
$pageTitle = 'Zarządzanie Baza Pytań - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        .question-text-truncate {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 767.98px) {
            .question-editor-modal .form-control,
            .question-editor-modal .form-select,
            .question-editor-modal textarea {
                font-size: 16px;
            }
            .question-text-truncate {
                max-width: none;
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
        }
    </style>
HTML;
include '../includes/header.php';
?>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h2 class="fw-bold mb-1">Zarządzanie Bazą Pytań</h2>
                            <p class="text-muted">Dodawaj, edytuj i usuwaj pytania z centralnej bazy.</p>
                        </div>
                        <button class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                            <i class="bi bi-plus-lg me-2"></i>Dodaj pytanie
                        </button>
                    </div>

                    <?php if (!empty($flashMessage)): ?>
                        <div class="alert alert-<?php echo ($flashType === 'error') ? 'danger' : ($flashType === 'success' ? 'success' : 'info'); ?> border-0 shadow-sm animate-in">
                            <i class="bi bi-info-circle-fill me-2"></i><?php echo htmlspecialchars($flashMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-panel mb-4 animate-in">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="q" class="form-control border-start-0" placeholder="Szukaj w treści..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="cat" class="form-select">
                                    <option value="">Wszystkie kategorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $catFilter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-grid">
                                <button class="btn btn-primary" type="submit">Filtruj</button>
                            </div>
                        </form>
                    </div>

                    <div class="dashboard-panel p-0 overflow-hidden animate-in questions-table-panel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 questions-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Treść pytania</th>
                                        <th>Kategoria</th>
                                        <th>Poprawna</th>
                                        <th class="text-end pe-4">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($questions)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">Brak pytań spełniających kryteria.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($questions as $q): ?>
                                    <tr>
                                        <td class="ps-4" data-label="Treść pytania">
                                            <div class="question-text-truncate fw-medium" title="<?php echo htmlspecialchars($q['question_text']); ?>">
                                                <?php echo htmlspecialchars($q['question_text']); ?>
                                            </div>
                                            <?php if (!empty($q['image_url'])): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info small mt-1"><i class="bi bi-image me-1"></i>Ma obrazek</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Kategoria">
                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                <?php echo htmlspecialchars($q['category']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Poprawna">
                                            <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                                                <?php echo $q['correct_answer']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4" data-label="Akcje">
                                            <div class="d-flex justify-content-end gap-2 questions-table-actions">
                                                <button type="button" class="btn btn-outline-primary btn-sm rounded-circle edit-btn" 
                                                        data-question='<?php echo json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                        aria-label="Edytuj pytanie"
                                                        style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" onsubmit="return appConfirmSubmit(this, 'Czy na pewno chcesz usunąć to pytanie?');">
                                                    <?php echo csrfTokenField('manage_questions'); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle"
                                                            aria-label="Usuń pytanie"
                                                            style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?q=<?php echo urlencode($search); ?>&cat=<?php echo urlencode($catFilter); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Add Question Modal -->
    <div class="modal fade question-editor-modal" id="addQuestionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Dodaj nowe pytanie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrfTokenField('manage_questions'); ?>
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kategoria</label>
                            <input type="text" name="category" class="form-control" list="categoryList" placeholder="np. INF.02" required>
                            <datalist id="categoryList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Poprawna odpowiedź</label>
                            <select name="correct_answer" class="form-select" required>
                                <option value="A">Odpowiedź A</option>
                                <option value="B">Odpowiedź B</option>
                                <option value="C">Odpowiedź C</option>
                                <option value="D">Odpowiedź D</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Treść pytania</label>
                            <textarea name="question_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja A</label>
                            <input type="text" name="option_a" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja B</label>
                            <input type="text" name="option_b" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja C</label>
                            <input type="text" name="option_c" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja D</label>
                            <input type="text" name="option_d" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">URL obrazka (opcjonalnie)</label>
                            <input type="text" name="image_url" class="form-control" placeholder="assets/images/...">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Wyjaśnienie (opcjonalnie)</label>
                            <textarea name="explanation" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Zapisz pytanie</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div class="modal fade question-editor-modal" id="editQuestionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edytuj pytanie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrfTokenField('manage_questions'); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kategoria</label>
                            <input type="text" name="category" id="edit_category" class="form-control" list="categoryList" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Poprawna odpowiedź</label>
                            <select name="correct_answer" id="edit_correct" class="form-select" required>
                                <option value="A">Odpowiedź A</option>
                                <option value="B">Odpowiedź B</option>
                                <option value="C">Odpowiedź C</option>
                                <option value="D">Odpowiedź D</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Treść pytania</label>
                            <textarea name="question_text" id="edit_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja A</label>
                            <input type="text" name="option_a" id="edit_a" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja B</label>
                            <input type="text" name="option_b" id="edit_b" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja C</label>
                            <input type="text" name="option_c" id="edit_c" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Opcja D</label>
                            <input type="text" name="option_d" id="edit_d" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">URL obrazka (opcjonalnie)</label>
                            <input type="text" name="image_url" id="edit_image" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Wyjaśnienie (opcjonalnie)</label>
                            <textarea name="explanation" id="edit_explanation" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Zapisz zmiany</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const q = JSON.parse(btn.getAttribute('data-question'));
                document.getElementById('edit_id').value = q.id;
                document.getElementById('edit_category').value = q.category;
                document.getElementById('edit_text').value = q.question_text;
                document.getElementById('edit_a').value = q.option_a;
                document.getElementById('edit_b').value = q.option_b;
                document.getElementById('edit_c').value = q.option_c;
                document.getElementById('edit_d').value = q.option_d;
                document.getElementById('edit_correct').value = q.correct_answer;
                document.getElementById('edit_image').value = q.image_url || '';
                document.getElementById('edit_explanation').value = q.explanation || '';
                
                const modal = new bootstrap.Modal(document.getElementById('editQuestionModal'));
                modal.show();
            });
        });
    </script>
</body>
</html>

