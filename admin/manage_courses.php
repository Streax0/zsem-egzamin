<?php
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/CourseService.php';

startSecureSession();
requireLogin();
if (!roleHasAdminAccess((string)($_SESSION['role'] ?? 'user'))) {
    setSessionMessage('error', 'Brak uprawnień do zarządzania kursami.');
    redirect('../index.php');
}
if (function_exists('mfaAccessRequired') && mfaAccessRequired()) {
    setSessionMessage('error', 'Potwierdź uwierzytelnianie wieloskładnikowe przed zmianą kursów.');
    redirect('../index.php');
}

$adminId = (int)$_SESSION['user_id'];
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''), 'manage_courses')) {
        setSessionMessage('error', 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
        redirect('manage_courses.php');
    }
    $rate = securityConsumeRateLimit('course_catalog_admin:' . $adminId . ':' . securityClientIp(), 60, 60);
    if (empty($rate['allowed'])) {
        setSessionMessage('error', 'Zbyt wiele zmian naraz. Spróbuj ponownie za chwilę.');
        redirect('manage_courses.php');
    }
    $action = (string)($_POST['action'] ?? '');
    $courseId = (int)($_POST['id'] ?? 0);
    $title = courseText((string)($_POST['title'] ?? ''), 160, false);
    $description = courseText((string)($_POST['description'] ?? ''), 5000);
    $imageInput = trim((string)($_POST['image_url'] ?? ''));
    $imageUrl = courseNormalizeImageUrl($imageInput);
    $startInput = trim((string)($_POST['start_date'] ?? ''));
    $endInput = trim((string)($_POST['end_date'] ?? ''));
    $startDate = courseValidDate($startInput);
    $endDate = courseValidDate($endInput);
    $status = in_array((string)($_POST['status'] ?? ''), ['active', 'hidden'], true) ? (string)$_POST['status'] : 'hidden';
    $sequential = isset($_POST['sequential_learning']) ? 1 : 0;
    $hasCertificate = isset($_POST['has_certificate']) ? 1 : 0;
    $category = courseText((string)($_POST['category'] ?? ''), 100, false);
    $difficulty = in_array((string)($_POST['difficulty'] ?? ''), ['beginner', 'intermediate', 'advanced'], true) ? (string)$_POST['difficulty'] : null;
    $estimatedHours = trim((string)($_POST['estimated_hours'] ?? ''));
    $estimatedHours = $estimatedHours !== '' ? max(0, min(9999, (int)$estimatedHours)) : null;

    if (in_array($action, ['add', 'edit'], true)) {
        if ($title === '') {
            setSessionMessage('error', 'Tytuł kursu jest wymagany.');
            redirect('manage_courses.php');
        }
        if (($imageInput !== '' && $imageUrl === null) || ($startInput !== '' && $startDate === null) || ($endInput !== '' && $endDate === null) || !courseDateRangeIsValid($startDate, $endDate)) {
            setSessionMessage('error', 'Sprawdź adres okładki oraz zakres dat. Okładka musi pochodzić z assets/images lub zaufanej domeny platformy.');
            redirect('manage_courses.php');
        }
    }

    try {
        if ($action === 'add') {
            $statement = $pdo->prepare('INSERT INTO courses (title, description, image_url, category, difficulty, estimated_hours, status, sequential_learning, has_certificate, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$title, $description !== '' ? $description : null, $imageUrl, $category !== '' ? $category : null, $difficulty, $estimatedHours, $status, $sequential, $hasCertificate, $startDate, $endDate]);
            $newCourseId = (int)$pdo->lastInsertId();
            securityAudit('course_created', ['course_id' => $newCourseId, 'user_id' => $adminId]);
            setSessionMessage('success', 'Kurs utworzony. Dodaj teraz pierwszy moduł i lekcję.');
            redirect('course_builder.php?id=' . $newCourseId);
        }
        if ($action === 'edit') {
            if (!courseFetchById($pdo, $courseId)) {
                setSessionMessage('error', 'Kurs nie istnieje.');
                redirect('manage_courses.php');
            }
            $statement = $pdo->prepare('UPDATE courses SET title = ?, description = ?, image_url = ?, category = ?, difficulty = ?, estimated_hours = ?, status = ?, sequential_learning = ?, has_certificate = ?, start_date = ?, end_date = ? WHERE id = ?');
            $statement->execute([$title, $description !== '' ? $description : null, $imageUrl, $category !== '' ? $category : null, $difficulty, $estimatedHours, $status, $sequential, $hasCertificate, $startDate, $endDate, $courseId]);
            securityAudit('course_updated', ['course_id' => $courseId, 'user_id' => $adminId]);
            setSessionMessage('success', 'Ustawienia kursu zostały zapisane.');
            redirect('manage_courses.php');
        }
        if ($action === 'delete') {
            $course = courseFetchById($pdo, $courseId);
            if (!$course) {
                setSessionMessage('error', 'Kurs nie istnieje.');
            } else {
                $statement = $pdo->prepare('DELETE FROM courses WHERE id = ?');
                $statement->execute([$courseId]);
                securityAudit('course_deleted', ['course_id' => $courseId, 'user_id' => $adminId], 'warning');
                setSessionMessage('success', 'Kurs wraz z jego strukturą i postępami został usunięty.');
            }
            redirect('manage_courses.php');
        }
        setSessionMessage('error', 'Nieznana operacja.');
    } catch (Throwable $error) {
        error_log('Course catalog update failed: ' . $error->getMessage());
        setSessionMessage('error', 'Nie udało się zapisać kursu. Spróbuj ponownie.');
    }
    redirect('manage_courses.php');
}

$search = courseText((string)($_GET['q'] ?? ''), 100, false);
$page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
$limit = 20;
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE c.title LIKE ? OR c.description LIKE ?';
    $params = ['%' . $search . '%', '%' . $search . '%'];
}
$countStatement = $pdo->prepare("SELECT COUNT(*) FROM courses c $where");
$countStatement->execute($params);
$total = (int)$countStatement->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
$statement = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id) AS module_count, (SELECT COUNT(*) FROM course_items ci JOIN course_modules cm ON cm.id = ci.module_id WHERE cm.course_id = c.id) AS item_count, (SELECT COUNT(*) FROM user_course_enrollments uce WHERE uce.course_id = c.id) AS enrollment_count FROM courses c $where ORDER BY c.updated_at DESC, c.id DESC LIMIT $limit OFFSET $offset");
$statement->execute($params);
$courses = $statement->fetchAll(PDO::FETCH_ASSOC);
$flash = getSessionMessage();

$pageTitle = 'Zarządzanie kursami — ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/courses.css'];
$base_url = '../';
include '../includes/header.php';
?>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main class="content-body" id="main-content">
            <div class="container-fluid p-0">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
                    <div><h1 class="h3 fw-bold mb-1">Kursy</h1><p class="text-muted mb-0">Twórz programy, porządkuj moduły i publikuj dopiero gotowe materiały.</p></div>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#courseModal" data-course-modal="new"><i class="bi bi-plus-lg me-1"></i>Nowy kurs</button>
                </div>
                <?php if (!empty($flash['message'])): ?><div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert"><?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <div class="dashboard-panel p-3 mb-4">
                    <form method="get" action="manage_courses.php" class="row g-2">
                        <div class="col-md"><input class="form-control" type="search" name="q" maxlength="100" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Szukaj po tytule lub opisie"></div>
                        <div class="col-md-auto"><button class="btn btn-outline-primary w-100" type="submit">Szukaj</button></div>
                        <?php if ($search !== ''): ?><div class="col-md-auto"><a class="btn btn-outline-secondary w-100" href="manage_courses.php">Wyczyść</a></div><?php endif; ?>
                    </form>
                </div>
                <div class="dashboard-panel p-0 overflow-hidden">
                    <?php if (!$courses): ?>
                        <div class="text-center p-5 text-muted">Brak kursów do wyświetlenia.</div>
                    <?php else: ?>
                        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th class="ps-4">Kurs</th><th>Status</th><th>Struktura</th><th>Uczestnicy</th><th class="text-end pe-4">Działania</th></tr></thead><tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr><td class="ps-4"><div class="fw-bold"><?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted">aktualizacja: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string)$course['updated_at'])), ENT_QUOTES, 'UTF-8'); ?></div></td>
                                <td><span class="badge text-bg-<?php echo $course['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo $course['status'] === 'active' ? 'Opublikowany' : 'Szkic'; ?></span><?php if ((int)$course['sequential_learning'] === 1): ?><div class="small text-muted mt-1">sekwencyjny</div><?php endif; ?></td>
                                <td><?php echo (int)$course['module_count']; ?> modułów<br><span class="small text-muted"><?php echo (int)$course['item_count']; ?> lekcji</span></td><td><?php echo (int)$course['enrollment_count']; ?></td>
                                <td class="text-end pe-4"><div class="btn-group btn-group-sm"><a class="btn btn-primary" href="course_builder.php?id=<?php echo (int)$course['id']; ?>">Edytuj program</a><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#courseModal" data-course-modal="edit" data-course="<?php echo htmlspecialchars((string)json_encode($course, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ustawienia kursu"><i class="bi bi-gear"></i></button></div>
                                <form class="d-inline" method="post" action="manage_courses.php" onsubmit="return confirm('Usunąć kurs wraz z modułami, lekcjami i zapisanym postępem?');"><?php echo csrfTokenField('manage_courses'); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$course['id']; ?>"><button class="btn btn-link btn-sm text-danger p-0 ms-2" type="submit">Usuń</button></form></td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
                <?php if ($pages > 1): ?><nav class="mt-4"><ul class="pagination justify-content-center"><li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $search, 'page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8'); ?>">Poprzednia</a></li><li class="page-item disabled"><span class="page-link">Strona <?php echo $page; ?> z <?php echo $pages; ?></span></li><li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $search, 'page' => min($pages, $page + 1)]), ENT_QUOTES, 'UTF-8'); ?>">Następna</a></li></ul></nav><?php endif; ?>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalTitle" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" action="manage_courses.php" id="courseSettingsForm">
    <?php echo csrfTokenField('manage_courses'); ?><input type="hidden" name="action" id="courseFormAction" value="add"><input type="hidden" name="id" id="courseFormId" value="0">
    <div class="modal-header"><h2 class="modal-title fs-5" id="courseModalTitle">Nowy kurs</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3"><div class="col-md-8"><label class="form-label" for="courseTitle">Tytuł *</label><input class="form-control" id="courseTitle" name="title" maxlength="160" required></div><div class="col-md-4"><label class="form-label" for="courseStatus">Widoczność</label><select class="form-select" id="courseStatus" name="status"><option value="hidden">Szkic</option><option value="active">Opublikowany</option></select></div>
        <div class="col-12"><label class="form-label" for="courseDescription">Opis</label><textarea class="form-control" id="courseDescription" name="description" maxlength="5000" rows="4"></textarea></div>
        <div class="col-12"><label class="form-label" for="courseImage">Okładka</label><input class="form-control" id="courseImage" name="image_url" maxlength="255" placeholder="assets/images/kurs.jpg"><div class="form-text">Dozwolone: plik z assets/images lub zaufana domena platformy.</div></div>
        <div class="col-md-4"><label class="form-label" for="courseCategory">Kategoria</label><input class="form-control" id="courseCategory" name="category" maxlength="100" placeholder="np. Sieci, Programowanie"></div>
        <div class="col-md-4"><label class="form-label" for="courseDifficulty">Poziom trudności</label><select class="form-select" id="courseDifficulty" name="difficulty"><option value="">Nie określono</option><option value="beginner">Początkujący</option><option value="intermediate">Średniozaawansowany</option><option value="advanced">Zaawansowany</option></select></div>
        <div class="col-md-4"><label class="form-label" for="courseHours">Szacowany czas (h)</label><input class="form-control" type="number" id="courseHours" name="estimated_hours" min="0" max="9999" placeholder="np. 10"></div>
        <div class="col-md-6"><label class="form-label" for="courseStart">Data rozpoczęcia</label><input class="form-control" type="date" id="courseStart" name="start_date"></div><div class="col-md-6"><label class="form-label" for="courseEnd">Data zakończenia</label><input class="form-control" type="date" id="courseEnd" name="end_date"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="courseSequential" name="sequential_learning" value="1"><label class="form-check-label" for="courseSequential">Wymagaj ukończenia poprzedniego etapu przed przejściem dalej</label></div></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="courseCertificate" name="has_certificate" value="1" checked><label class="form-check-label" for="courseCertificate">Wystawiaj cyfrowy certyfikat ZSEM Tech po ukończeniu 100% kursu i zaliczeniu egzaminu / testu końcowego</label></div></div>
    </div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Anuluj</button><button class="btn btn-primary" type="submit">Zapisz kurs</button></div>
</form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
document.getElementById('courseModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const mode = button && button.dataset.courseModal;
    const form = document.getElementById('courseSettingsForm');
    form.reset();
    document.getElementById('courseFormAction').value = mode === 'edit' ? 'edit' : 'add';
    document.getElementById('courseFormId').value = '0';
    document.getElementById('courseModalTitle').textContent = mode === 'edit' ? 'Ustawienia kursu' : 'Nowy kurs';
    document.getElementById('courseCertificate').checked = true;
    if (mode === 'edit' && button.dataset.course) {
        const course = JSON.parse(button.dataset.course);
        document.getElementById('courseFormId').value = course.id || 0;
        document.getElementById('courseTitle').value = course.title || '';
        document.getElementById('courseDescription').value = course.description || '';
        document.getElementById('courseImage').value = course.image_url || '';
        document.getElementById('courseCategory').value = course.category || '';
        document.getElementById('courseDifficulty').value = course.difficulty || '';
        document.getElementById('courseHours').value = course.estimated_hours || '';
        document.getElementById('courseStatus').value = course.status === 'active' ? 'active' : 'hidden';
        document.getElementById('courseStart').value = course.start_date || '';
        document.getElementById('courseEnd').value = course.end_date || '';
        document.getElementById('courseSequential').checked = Number(course.sequential_learning) === 1;
        document.getElementById('courseCertificate').checked = Number(course.has_certificate ?? 1) === 1;
    }
});
</script>
</body>
</html>
