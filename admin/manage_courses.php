<?php
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/CourseService.php';

startSecureSession();
if (function_exists('_ensurePlatformCourses')) {
    _ensurePlatformCourses($pdo);
}
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
    $status = in_array((string)($_POST['status'] ?? ''), ['active', 'hidden', 'private'], true) ? (string)$_POST['status'] : 'hidden';
    $isExternal = isset($_POST['is_external']) ? 1 : 0;
    $externalUrl = trim((string)($_POST['external_url'] ?? ''));
    $sequential = $isExternal ? 0 : (isset($_POST['sequential_learning']) ? 1 : 0);
    $hasCertificate = $isExternal ? 0 : (isset($_POST['has_certificate']) ? 1 : 0);
    $category = courseText((string)($_POST['category'] ?? ''), 100, false);
    $difficulty = in_array((string)($_POST['difficulty'] ?? ''), ['beginner', 'intermediate', 'advanced'], true) ? (string)$_POST['difficulty'] : null;
    $estimatedHours = trim((string)($_POST['estimated_hours'] ?? ''));
    $estimatedHours = $estimatedHours !== '' ? max(0, min(9999, (int)$estimatedHours)) : null;
    $userFriends = getUserFriends($pdo, $adminId);
    $validFriendIds = array_map(fn($f) => (int)$f['id'], $userFriends);
    $rawSharedWith = is_array($_POST['shared_with'] ?? null) ? $_POST['shared_with'] : [];
    $sharedWith = array_filter(
        array_map('intval', $rawSharedWith),
        fn($id) => in_array($id, $validFriendIds, true)
    );

    if (in_array($action, ['add', 'edit'], true)) {
        if ($title === '') {
            setSessionMessage('error', 'Tytuł kursu jest wymagany.');
            redirect('manage_courses.php');
        }
        if ($isExternal && $externalUrl !== '' && !preg_match('#^https?://#i', $externalUrl)) {
            setSessionMessage('error', 'Prawidłowy link zewnętrzny musi rozpoczynać się od http:// lub https://');
            redirect('manage_courses.php');
        }
        if (($imageInput !== '' && $imageUrl === null) || ($startInput !== '' && $startDate === null) || ($endInput !== '' && $endDate === null) || !courseDateRangeIsValid($startDate, $endDate)) {
            setSessionMessage('error', 'Sprawdź adres okładki oraz zakres dat. Okładka musi pochodzić z assets/images lub zaufanej domeny platformy.');
            redirect('manage_courses.php');
        }
    }

    try {
        if ($action === 'add') {
            $statement = $pdo->prepare('INSERT INTO courses (title, description, image_url, category, difficulty, estimated_hours, status, sequential_learning, has_certificate, start_date, end_date, created_by, is_external, external_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$title, $description !== '' ? $description : null, $imageUrl, $category !== '' ? $category : null, $difficulty, $estimatedHours, $status, $sequential, $hasCertificate, $startDate, $endDate, $adminId, $isExternal, $externalUrl !== '' ? $externalUrl : null]);
            $newCourseId = (int)$pdo->lastInsertId();
            courseSetSharedUserIds($pdo, $newCourseId, $sharedWith);
            securityAudit('course_created', ['course_id' => $newCourseId, 'user_id' => $adminId]);
            setSessionMessage('success', $isExternal ? 'Kurs zewnętrzny utworzony.' : 'Kurs utworzony. Dodaj teraz pierwszy moduł i lekcję.');
            redirect($isExternal ? 'manage_courses.php' : 'course_builder.php?id=' . $newCourseId);
        }
        if ($action === 'edit') {
            $existingCourse = courseFetchById($pdo, $courseId);
            if (!$existingCourse) {
                setSessionMessage('error', 'Kurs nie istnieje.');
                redirect('manage_courses.php');
            }
            $createdBy = !empty($existingCourse['created_by']) ? (int)$existingCourse['created_by'] : $adminId;
            $statement = $pdo->prepare('UPDATE courses SET title = ?, description = ?, image_url = ?, category = ?, difficulty = ?, estimated_hours = ?, status = ?, sequential_learning = ?, has_certificate = ?, start_date = ?, end_date = ?, created_by = ?, is_external = ?, external_url = ? WHERE id = ?');
            $statement->execute([$title, $description !== '' ? $description : null, $imageUrl, $category !== '' ? $category : null, $difficulty, $estimatedHours, $status, $sequential, $hasCertificate, $startDate, $endDate, $createdBy, $isExternal, $externalUrl !== '' ? $externalUrl : null, $courseId]);
            courseSetSharedUserIds($pdo, $courseId, $sharedWith);
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

foreach ($courses as &$c) {
    $c['shared_user_ids'] = courseGetSharedUserIds($pdo, (int)$c['id']);
}
unset($c);

$userFriends = getUserFriends($pdo, $adminId);
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
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                    <div>
                        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-journal-bookmark-fill text-primary"></i> Kursy
                        </h1>
                        <p class="text-muted mb-0">Twórz programy, porządkuj moduły i publikuj dopiero gotowe materiały.</p>
                    </div>
                    <button class="btn btn-gradient-primary d-flex align-items-center gap-2 shadow" type="button" data-bs-toggle="modal" data-bs-target="#courseModal" data-course-modal="new">
                        <i class="bi bi-plus-lg fs-5"></i><span>Nowy kurs</span>
                    </button>
                </div>

                <?php if (!empty($flash['message'])): ?>
                    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                        <?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="dashboard-panel p-3 mb-4 rounded-4 border shadow-sm">
                    <form method="get" action="manage_courses.php" class="row g-2 align-items-center">
                        <div class="col-md">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input class="form-control border-start-0 ps-0" type="search" name="q" maxlength="100" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Szukaj po tytule lub opisie">
                            </div>
                        </div>
                        <div class="col-md-auto">
                            <button class="btn manage-courses-search-btn w-100" type="submit">Szukaj</button>
                        </div>
                        <?php if ($search !== ''): ?>
                            <div class="col-md-auto">
                                <a class="btn btn-outline-secondary w-100 rounded-3" href="manage_courses.php">Wyczyść</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="dashboard-panel p-0 overflow-hidden rounded-4 border shadow-sm">
                    <?php if (!$courses): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-50"></i>
                            Brak kursów do wyświetlenia.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-uppercase small fw-bold text-muted">
                                    <tr>
                                        <th class="ps-4 py-3">Kurs</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3">Struktura</th>
                                        <th class="py-3">Uczestnicy</th>
                                        <th class="text-end pe-4 py-3">Działania</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="fw-bold fs-6 text-body"><?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small text-muted d-flex align-items-center gap-1 mt-1">
                                                    <i class="bi bi-clock-history"></i> aktualizacja: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string)$course['updated_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($course['status'] === 'active'): ?>
                                                    <span class="badge badge-status badge-status-active"><i class="bi bi-globe me-1"></i>Opublikowany</span>
                                                <?php elseif ($course['status'] === 'private'): ?>
                                                    <span class="badge badge-status badge-status-private"><i class="bi bi-lock-fill me-1"></i>Tylko dla mnie</span>
                                                <?php else: ?>
                                                    <span class="badge badge-status badge-status-hidden"><i class="bi bi-eye-slash me-1"></i>Szkic</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($course['status'] === 'private' && !empty($course['shared_user_ids'])): ?>
                                                    <div class="mt-1">
                                                        <span class="badge badge-shared-tag"><i class="bi bi-people-fill me-1"></i>udostępniono (<?php echo count($course['shared_user_ids']); ?>)</span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ((int)$course['sequential_learning'] === 1): ?>
                                                    <div class="small text-muted mt-1"><i class="bi bi-diagram-2 me-1"></i>sekwencyjny</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><i class="bi bi-folder2-open me-1 text-primary"></i><?php echo (int)$course['module_count']; ?> modułów</div>
                                                <div class="small text-muted"><i class="bi bi-journal-text me-1 text-info"></i><?php echo (int)$course['item_count']; ?> lekcji</div>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-light border text-body fw-semibold px-2 py-1"><i class="bi bi-people me-1 text-success"></i><?php echo (int)$course['enrollment_count']; ?></span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group btn-group-sm">
                                                    <a class="btn btn-primary fw-semibold px-3" href="course_builder.php?id=<?php echo (int)$course['id']; ?>">
                                                        <i class="bi bi-journal-text me-1"></i>Edytuj program
                                                    </a>
                                                    <button class="btn btn-outline-secondary px-2" type="button" data-bs-toggle="modal" data-bs-target="#courseModal" data-course-modal="edit" data-course="<?php echo htmlspecialchars((string)json_encode($course, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ustawienia kursu">
                                                        <i class="bi bi-gear-fill"></i>
                                                    </button>
                                                </div>
                                                <form class="d-inline" method="post" action="manage_courses.php" onsubmit="return window.appConfirmSubmit(this, 'Usunąć kurs wraz z modułami, lekcjami i zapisanym postępem?');">
                                                    <?php echo csrfTokenField('manage_courses'); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$course['id']; ?>">
                                                    <button class="btn btn-link btn-sm text-danger text-decoration-none p-0 ms-2" type="submit"><i class="bi bi-trash3 me-1"></i>Usuń</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $search, 'page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8'); ?>">Poprzednia</a></li>
                            <li class="page-item disabled"><span class="page-link">Strona <?php echo $page; ?> z <?php echo $pages; ?></span></li>
                            <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $search, 'page' => min($pages, $page + 1)]), ENT_QUOTES, 'UTF-8'); ?>">Następna</a></li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content course-modal-content">
            <form method="post" action="manage_courses.php" id="courseSettingsForm">
                <?php echo csrfTokenField('manage_courses'); ?>
                <input type="hidden" name="action" id="courseFormAction" value="add">
                <input type="hidden" name="id" id="courseFormId" value="0">
                
                <div class="modal-header course-modal-header">
                    <h2 class="modal-title fs-5" id="courseModalTitle"><i class="bi bi-journal-plus text-primary"></i>Nowy kurs</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold" for="courseTitle"><i class="bi bi-type me-1 text-primary"></i>Tytuł kursu *</label>
                            <input class="form-control rounded-3" id="courseTitle" name="title" maxlength="160" required placeholder="np. Podstawy Sieci Komputerowych">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="courseStatus"><i class="bi bi-eye me-1 text-info"></i>Widoczność</label>
                            <select class="form-select rounded-3" id="courseStatus" name="status">
                                <option value="hidden">Szkic (niewidoczny)</option>
                                <option value="active">Opublikowany (publiczny)</option>
                                <option value="private">Tylko dla mnie (prywatny)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="courseDescription"><i class="bi bi-text-paragraph me-1 text-secondary"></i>Opis kursu</label>
                            <textarea class="form-control rounded-3" id="courseDescription" name="description" maxlength="5000" rows="3" placeholder="Wprowadź krótki opis, czego nauczą się uczestnicy..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="courseImage"><i class="bi bi-image me-1 text-success"></i>Ścieżka lub URL okładki</label>
                            <input class="form-control rounded-3" id="courseImage" name="image_url" maxlength="255" placeholder="assets/images/kurs.jpg">
                            <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Dozwolone: ścieżki lokalne z assets/images lub zaufane domeny platformy.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="courseCategory"><i class="bi bi-tag me-1 text-warning"></i>Kategoria</label>
                            <input class="form-control rounded-3" id="courseCategory" name="category" maxlength="100" placeholder="np. Sieci, Programowanie">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="courseDifficulty"><i class="bi bi-bar-chart me-1 text-danger"></i>Poziom trudności</label>
                            <select class="form-select rounded-3" id="courseDifficulty" name="difficulty">
                                <option value="">Nie określono</option>
                                <option value="beginner">Początkujący</option>
                                <option value="intermediate">Średniozaawansowany</option>
                                <option value="advanced">Zaawansowany</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="courseHours"><i class="bi bi-clock me-1 text-primary"></i>Czas nauki (h)</label>
                            <input class="form-control rounded-3" type="number" id="courseHours" name="estimated_hours" min="0" max="9999" placeholder="np. 10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="courseStart"><i class="bi bi-calendar-event me-1"></i>Data rozpoczęcia</label>
                            <input class="form-control rounded-3" type="date" id="courseStart" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="courseEnd"><i class="bi bi-calendar-check me-1"></i>Data zakończenia</label>
                            <input class="form-control rounded-3" type="date" id="courseEnd" name="end_date">
                        </div>
                        
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch p-2 rounded-3 bg-light-subtle border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="courseSequential" name="sequential_learning" value="1">
                                <label class="form-check-label fw-semibold" for="courseSequential">Wymagaj ukończenia poprzedniego etapu przed przejściem dalej</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch p-2 rounded-3 bg-light-subtle border" id="courseCertificateWrapper">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="courseCertificate" name="has_certificate" value="1" checked>
                                <label class="form-check-label fw-semibold" for="courseCertificate">
                                    Wystawiaj cyfrowy certyfikat ZSEM Tech po ukończeniu 100% kursu i zaliczeniu egzaminu / testu końcowego
                                    <span id="certDisabledNote" class="text-warning small ms-1 d-none">(Certyfikaty ZSEM Tech są obecnie wyłączone dla kursów zewnętrznych)</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch p-3 rounded-3 bg-warning bg-opacity-10 border border-warning">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="courseIsExternal" name="is_external" value="1">
                                <label class="form-check-label fw-bold text-dark" for="courseIsExternal">
                                    <i class="bi bi-box-arrow-up-right text-warning me-1"></i> Kurs Zewnętrzny (materiał zewnętrzny)
                                </label>
                                <div class="form-text text-muted small mt-1 ms-4">
                                    Kursy zewnętrzne odsyłają użytkownika do zewnętrznego serwisu. Uwaga: Kursy zewnętrzne nie posiadają certyfikatów ZSEM TECH (pracujemy nad tym!).
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="courseExternalUrlWrapper">
                            <label class="form-label fw-semibold" for="courseExternalUrl"><i class="bi bi-link-45deg me-1 text-warning"></i>Zewnętrzny link URL kursu *</label>
                            <input class="form-control rounded-3" type="url" id="courseExternalUrl" name="external_url" maxlength="500" placeholder="https://..." value="">
                            <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Wpisz adres URL do zewnętrznego materiału szkoleniowego.</div>
                        </div>

                        <div class="col-12 mt-3" id="courseShareWrapper">
                            <div class="course-share-card">
                                <label class="form-label fw-bold d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-people-fill text-primary fs-5"></i> Udostępnij wybranym znajomym
                                </label>
                                <p class="small text-muted mb-3">Wybierz osoby ze swojej listy znajomych, które uzyskają dostęp do kursu (głównie przy widoczności "Tylko dla mnie"):</p>
                                
                                <?php if (empty($userFriends)): ?>
                                    <div class="small text-muted fst-italic p-2 bg-body-tertiary rounded-3 border text-center">
                                        <i class="bi bi-person-x me-1"></i>Brak dodanych znajomych na koncie. Dodaj znajomych w panelu społecznościowym, aby móc udostępniać im kursy.
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-2 p-2 border rounded-3 bg-body-tertiary" style="max-height: 180px; overflow-y: auto;">
                                        <?php foreach ($userFriends as $friend): ?>
                                            <?php 
                                            $friendName = (string)$friend['username']; 
                                            $avatar = !empty($friend['avatar_path']) ? $friend['avatar_path'] : null;
                                            $firstLetter = strtoupper(mb_substr($friendName, 0, 1, 'UTF-8'));
                                            ?>
                                            <div class="friend-select-item" id="friend_item_<?php echo (int)$friend['id']; ?>">
                                                <input class="form-check-input course-friend-checkbox d-none" type="checkbox" name="shared_with[]" value="<?php echo (int)$friend['id']; ?>" id="friend_<?php echo (int)$friend['id']; ?>">
                                                <?php if ($avatar): ?>
                                                    <img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($friendName, ENT_QUOTES, 'UTF-8'); ?>" class="friend-avatar-img">
                                                <?php else: ?>
                                                    <div class="friend-avatar-badge"><?php echo htmlspecialchars($firstLetter, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <?php endif; ?>
                                                <span class="small fw-semibold text-body"><?php echo htmlspecialchars($friendName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <i class="bi bi-check-circle-fill ms-auto text-primary friend-check-icon opacity-0"></i>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light-subtle">
                    <button class="btn btn-outline-secondary rounded-3 px-4" type="button" data-bs-dismiss="modal">Anuluj</button>
                    <button class="btn btn-gradient-primary px-4 shadow" type="submit"><i class="bi bi-check2-circle me-1"></i>Zapisz kurs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
function syncFriendItemState(item) {
    const cb = item.querySelector('.course-friend-checkbox');
    const icon = item.querySelector('.friend-check-icon');
    if (cb && cb.checked) {
        item.classList.add('selected');
        if (icon) icon.classList.remove('opacity-0');
    } else {
        item.classList.remove('selected');
        if (icon) icon.classList.add('opacity-0');
    }
}

document.querySelectorAll('.friend-select-item').forEach(item => {
    item.addEventListener('click', function(e) {
        const cb = this.querySelector('.course-friend-checkbox');
        if (cb && e.target !== cb) {
            cb.checked = !cb.checked;
        }
        syncFriendItemState(this);
    });
});

function syncExternalCourseUI() {
    const isExt = document.getElementById('courseIsExternal')?.checked;
    const wrapper = document.getElementById('courseExternalUrlWrapper');
    const certCb = document.getElementById('courseCertificate');
    const seqCb = document.getElementById('courseSequential');
    const certNote = document.getElementById('certDisabledNote');

    if (wrapper) wrapper.classList.toggle('d-none', !isExt);
    if (certCb) {
        if (isExt) {
            certCb.checked = false;
            certCb.disabled = true;
        } else {
            certCb.disabled = false;
        }
    }
    if (seqCb) {
        if (isExt) {
            seqCb.checked = false;
            seqCb.disabled = true;
        } else {
            seqCb.disabled = false;
        }
    }
    if (certNote) certNote.classList.toggle('d-none', !isExt);
}

document.getElementById('courseIsExternal')?.addEventListener('change', syncExternalCourseUI);

document.getElementById('courseModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const mode = button && button.dataset.courseModal;
    const form = document.getElementById('courseSettingsForm');
    form.reset();
    
    document.querySelectorAll('.course-friend-checkbox').forEach(cb => cb.checked = false);
    document.querySelectorAll('.friend-select-item').forEach(item => syncFriendItemState(item));

    document.getElementById('courseFormAction').value = mode === 'edit' ? 'edit' : 'add';
    document.getElementById('courseFormId').value = '0';
    document.getElementById('courseModalTitle').innerHTML = mode === 'edit' ? '<i class="bi bi-gear-fill text-primary me-2"></i>Ustawienia kursu' : '<i class="bi bi-journal-plus text-primary me-2"></i>Nowy kurs';
    document.getElementById('courseCertificate').checked = true;
    document.getElementById('courseIsExternal').checked = false;
    document.getElementById('courseExternalUrl').value = '';
    syncExternalCourseUI();

    if (mode === 'edit' && button.dataset.course) {
        const course = JSON.parse(button.dataset.course);
        document.getElementById('courseFormId').value = course.id || 0;
        document.getElementById('courseTitle').value = course.title || '';
        document.getElementById('courseDescription').value = course.description || '';
        document.getElementById('courseImage').value = course.image_url || '';
        document.getElementById('courseCategory').value = course.category || '';
        document.getElementById('courseDifficulty').value = course.difficulty || '';
        document.getElementById('courseHours').value = course.estimated_hours || '';
        document.getElementById('courseStatus').value = course.status || 'hidden';
        document.getElementById('courseStart').value = course.start_date || '';
        document.getElementById('courseEnd').value = course.end_date || '';
        document.getElementById('courseSequential').checked = Number(course.sequential_learning) === 1;
        document.getElementById('courseCertificate').checked = Number(course.has_certificate ?? 1) === 1;
        document.getElementById('courseIsExternal').checked = Number(course.is_external ?? 0) === 1;
        document.getElementById('courseExternalUrl').value = course.external_url || '';
        syncExternalCourseUI();

        if (Array.isArray(course.shared_user_ids)) {
            course.shared_user_ids.forEach(friendId => {
                const cb = document.getElementById('friend_' + friendId);
                if (cb) {
                    cb.checked = true;
                    const item = document.getElementById('friend_item_' + friendId);
                    if (item) syncFriendItemState(item);
                }
            });
        }
    }
});
</script>
</body>
</html>
