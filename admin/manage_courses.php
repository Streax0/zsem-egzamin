<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (function_exists('ensurePlatformEnhancements')) {
    ensurePlatformEnhancements($pdo);
}
// Only admins or dyrektor allowed
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) {
    setSessionMessage('error', 'Brak uprawnień do zarządzania kursami.');
    redirect('../index.php');
}

// Handle POST actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token, 'manage_courses')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('manage_courses.php');
    }

    $action = $_POST['action'] ?? '';
    $courseId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'image_url' => trim($_POST['image_url'] ?? ''),
        'status' => in_array($_POST['status'] ?? '', ['active', 'hidden']) ? $_POST['status'] : 'hidden',
        'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
        'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        'sequential_learning' => isset($_POST['sequential_learning']) ? 1 : 0,
    ];

    switch ($action) {
        case 'add':
            if (empty($data['title'])) {
                setSessionMessage('error', 'Tytuł kursu nie może być pusty.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO courses (title, description, content, image_url, status, start_date, end_date, sequential_learning) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$data['title'], $data['description'], $data['content'], $data['image_url'], $data['status'], $data['start_date'], $data['end_date'], $data['sequential_learning']])) {
                    $newId = $pdo->lastInsertId();
                    setSessionMessage('success', 'Kurs został pomyślnie dodany. Możesz teraz ułożyć jego strukturę.');
                    redirect('course_builder.php?id=' . $newId);
                } else {
                    setSessionMessage('error', 'Błąd podczas dodawania kursu.');
                }
            }
            break;

        case 'edit':
            if ($courseId <= 0) {
                setSessionMessage('error', 'Nieprawidłowe ID kursu.');
            } elseif (empty($data['title'])) {
                setSessionMessage('error', 'Tytuł kursu nie może być pusty.');
            } else {
                $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, content = ?, image_url = ?, status = ?, start_date = ?, end_date = ?, sequential_learning = ? WHERE id = ?");
                if ($stmt->execute([$data['title'], $data['description'], $data['content'], $data['image_url'], $data['status'], $data['start_date'], $data['end_date'], $data['sequential_learning'], $courseId])) {
                    setSessionMessage('success', 'Kurs został zaktualizowany.');
                } else {
                    setSessionMessage('error', 'Błąd podczas aktualizacji kursu.');
                }
            }
            break;

        case 'delete':
            if ($courseId <= 0) {
                setSessionMessage('error', 'Nieprawidłowe ID kursu.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                if ($stmt->execute([$courseId])) {
                    setSessionMessage('success', 'Kurs został usunięty.');
                } else {
                    setSessionMessage('error', 'Błąd podczas usuwania kursu.');
                }
            }
            break;
    }
    redirect('manage_courses.php' . (isset($_GET['q']) ? '?q='.urlencode($_GET['q']) : ''));
}

// Listing / search / pagination
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = [];

if ($search !== '') {
    $whereClauses[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM user_course_enrollments uce WHERE uce.course_id = c.id) AS enrolled_count, (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id) AS module_count, (SELECT COUNT(*) FROM course_items ci JOIN course_modules cm2 ON cm2.id = ci.module_id WHERE cm2.course_id = c.id) AS item_count FROM courses c $whereSql ORDER BY c.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM courses c $whereSql");
$countStmt->execute($params);
$totalCourses = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalCourses / $limit));

$rawFlash = getSessionMessage();
$flashMessage = $rawFlash['message'] ?? '';
$flashType = $rawFlash['type'] ?? 'info';
?>
<?php
$pageTitle = 'Zarządzanie Kursami - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
include '../includes/header.php';
?>

    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3 animate-in">
                        <div>
                            <h2 class="fw-bold mb-1">Zarządzanie Kursami</h2>
                            <p class="text-muted mb-0">Dodawaj, edytuj i usuwaj kursy dla użytkowników.</p>
                        </div>
                        <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="openAddModal()">
                            <i class="bi bi-plus-lg"></i>
                            <span>Nowy Kurs</span>
                        </button>
                    </div>

                    <?php if ($flashMessage): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($flashType === 'error' ? 'danger' : 'success'); ?> alert-dismissible fade show mb-4 border-0 shadow-sm animate-in" role="alert">
                            <i class="bi <?php echo $flashType === 'error' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-check-circle-fill text-success'; ?> me-2"></i>
                            <?php echo htmlspecialchars($flashMessage); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="dashboard-panel mb-4 animate-in">
                        <form method="GET" action="manage_courses.php" class="row g-3 align-items-end">
                            <div class="col-12 col-md-8">
                                <label for="searchQuery" class="form-label text-muted small mb-1">Szukaj</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="searchQuery" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tytuł lub opis...">
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filtruj</button>
                            </div>
                            <?php if ($search !== ''): ?>
                                <div class="col-6 col-md-2">
                                    <a href="manage_courses.php" class="btn btn-outline-secondary w-100">Wyczyść</a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Courses List -->
                    <div class="dashboard-panel p-0 rounded-3 overflow-hidden animate-in" style="animation-delay: 0.05s;">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">ID</th>
                                        <th>Tytuł</th>
                                        <th>Status</th>
                                        <th>Okres od - do</th>
                                        <th>Zawartość</th>
                                        <th class="text-end pe-4">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courses)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">Nie znaleziono żadnych kursów.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td class="ps-4 text-muted">#<?php echo $course['id']; ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($course['title']); ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($course['status'] === 'active'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success">Aktywny</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Ukryty</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-muted small">
                                                    <?php 
                                                        $sd = $course['start_date'] ? date('d.m.Y', strtotime($course['start_date'])) : '-';
                                                        $ed = $course['end_date'] ? date('d.m.Y', strtotime($course['end_date'])) : '-';
                                                        echo $sd . ' - ' . $ed;
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <a href="course_builder.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold" style="border-radius: 5px;">
                                                            <i class="bi bi-journal-text me-1"></i> Moduły & Lekcje
                                                        </a>
                                                        <small class="text-muted"><?php echo (int)($course['module_count'] ?? 0); ?> moduł. / <?php echo (int)($course['item_count'] ?? 0); ?> lekcji / <?php echo (int)($course['enrolled_count'] ?? 0); ?> ucz.</small>
                                                    </div>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button class="btn btn-outline-primary btn-sm rounded-circle edit-btn" title="Edytuj" 
                                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($course)); ?>)"
                                                                style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form action="manage_courses.php" method="POST" class="d-inline-block" onsubmit="return confirm('Na pewno chcesz usunąć ten kurs?');">
                                                            <?php echo csrfTokenField('manage_courses'); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle" title="Usuń"
                                                                    style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer border-top bg-transparent p-3">
                                <nav aria-label="Nawigacja paginacji">
                                    <ul class="pagination pagination-sm justify-content-center mb-0">
                                        <?php 
                                            $baseParams = [];
                                            if ($search !== '') $baseParams['q'] = $search;
                                        ?>
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $page - 1])); ?>">Poprzednia</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $page + 1])); ?>">Następna</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="manage_courses.php" method="POST" id="courseForm">
                    <?php echo csrfTokenField('manage_courses'); ?>
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formCourseId" value="0">
                    
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="courseModalLabel">Nowy Kurs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label text-muted small fw-bold">Tytuł kursu *</label>
                                <input type="text" class="form-control" name="title" id="courseTitle" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Status</label>
                                <select class="form-select" name="status" id="courseStatus">
                                    <option value="active">Aktywny</option>
                                    <option value="hidden" selected>Ukryty</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold">Krótki opis</label>
                                <textarea class="form-control" name="description" id="courseDesc" rows="3"></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold">URL Okładki (Opcjonalnie)</label>
                                <input type="text" class="form-control" name="image_url" id="courseImage" placeholder="https://...">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Data rozpoczęcia (Opcjonalnie)</label>
                                <input type="date" class="form-control" name="start_date" id="courseStart">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Data zakończenia (Opcjonalnie)</label>
                                <input type="date" class="form-control" name="end_date" id="courseEnd">
                            </div>
                            <div class="col-12 mt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sequential_learning" id="courseSequential" value="1">
                                    <label class="form-check-label fw-bold text-muted small" for="courseSequential">Wymuś sekwencyjność nauki (Lekcja N+1 zablokowana do ukończenia N)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveCourse">Zapisz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('courseModalLabel').innerText = 'Nowy Kurs';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formCourseId').value = '0';
            
            document.getElementById('courseForm').reset();
            document.getElementById('courseStatus').value = 'hidden';
            document.getElementById('courseSequential').checked = false;
            document.getElementById('btnSaveCourse').innerText = 'Dodaj';
        }

        function openEditModal(course) {
            document.getElementById('courseModalLabel').innerText = 'Edycja Kursu';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formCourseId').value = course.id;
            
            document.getElementById('courseTitle').value = course.title || '';
            document.getElementById('courseStatus').value = course.status || 'hidden';
            document.getElementById('courseDesc').value = course.description || '';
            document.getElementById('courseImage').value = course.image_url || '';
            document.getElementById('courseStart').value = course.start_date || '';
            document.getElementById('courseEnd').value = course.end_date || '';
            document.getElementById('courseSequential').checked = (course.sequential_learning == 1);
            
            document.getElementById('btnSaveCourse').innerText = 'Zapisz zmiany';
            
            var modal = new bootstrap.Modal(document.getElementById('courseModal'));
            modal.show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php include '../includes/footer.php'; ?>
            </main>
        </div>
    </div>
</body>
</html>
