<?php
declare(strict_types=1);

require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/CourseService.php';

startSecureSession();
if (function_exists('_ensurePlatformCourses')) {
    _ensurePlatformCourses($pdo);
}

$search = courseText((string)($_GET['q'] ?? ''), 100, false);
$filterDifficulty = in_array((string)($_GET['difficulty'] ?? ''), ['beginner', 'intermediate', 'advanced'], true) ? (string)$_GET['difficulty'] : '';
$filterCategory = courseText((string)($_GET['category'] ?? ''), 100, false);
$page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
$limit = 12;
$currentUserId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

$params = [];
$where = "c.status = 'active' AND (c.start_date IS NULL OR c.start_date <= CURDATE()) AND (c.end_date IS NULL OR c.end_date >= CURDATE())";
if ($currentUserId > 0) {
    $where = "((c.status = 'active' AND (c.start_date IS NULL OR c.start_date <= CURDATE()) AND (c.end_date IS NULL OR c.end_date >= CURDATE())) OR (c.status = 'private' AND (c.created_by = ? OR c.id IN (SELECT course_id FROM course_shares WHERE shared_with_user_id = ?))))";
    $params[] = $currentUserId;
    $params[] = $currentUserId;
}
if ($search !== '') {
    $where .= ' AND (c.title LIKE ? OR c.description LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
}
if ($filterDifficulty !== '') {
    $where .= ' AND c.difficulty = ?';
    $params[] = $filterDifficulty;
}
if ($filterCategory !== '') {
    $where .= ' AND c.category LIKE ?';
    $params[] = '%' . $filterCategory . '%';
}

$countStatement = $pdo->prepare("SELECT COUNT(*) FROM courses c WHERE $where");
$countStatement->execute($params);
$total = (int)$countStatement->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

$statement = $pdo->prepare("SELECT c.id, c.title, c.description, c.image_url, c.category, c.difficulty, c.estimated_hours, c.status, c.created_by, c.start_date, c.end_date, c.sequential_learning, c.is_external, c.external_url, c.updated_at, (SELECT COUNT(*) FROM course_modules cm WHERE cm.course_id = c.id) AS module_count, (SELECT COUNT(*) FROM course_items ci JOIN course_modules cm ON cm.id = ci.module_id WHERE cm.course_id = c.id) AS item_count, (SELECT COUNT(*) FROM user_course_enrollments uce WHERE uce.course_id = c.id) AS enrollment_count FROM courses c WHERE $where ORDER BY c.updated_at DESC, c.id DESC LIMIT $limit OFFSET $offset");
$statement->execute($params);
$courses = $statement->fetchAll(PDO::FETCH_ASSOC);

$allCategoriesRows = dbQueryCached($pdo, "SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category ASC", [], 300);
$allCategories = array_column($allCategoriesRows, 'category');

$difficultyLabels = ['beginner' => 'Początkujący', 'intermediate' => 'Średniozaawansowany', 'advanced' => 'Zaawansowany'];
$difficultyColors = ['beginner' => 'success', 'intermediate' => 'warning', 'advanced' => 'danger'];

$pageTitle = 'Kursy — ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/courses.css'];
include 'includes/header.php';
?>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main class="content-body" id="main-content">
            <div class="container-fluid p-0">
                <section class="course-hero p-4 p-md-5 mb-4">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-8">
                            <span class="badge text-bg-primary mb-3"><i class="bi bi-mortarboard-fill me-1"></i>Strefa nauki</span>
                            <h1 class="h2 fw-bold mb-2">Kursy z jasną ścieżką postępu</h1>
                            <p class="mb-0" style="color: #cbd5e1; font-size: 1.05rem; line-height: 1.6;">Materiały, quizy, laboratoria oraz sprawdzone kursy zewnętrzne. Twój postęp zapisuje się po każdym ukończonym etapie.</p>
                        </div>
                        <div class="col-lg-4">
                            <form method="get" action="courses.php" class="input-group input-group-lg">
                                <input class="form-control" type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" placeholder="Szukaj kursu" aria-label="Szukaj kursu">
                                <button class="btn btn-primary" type="submit" aria-label="Szukaj"><i class="bi bi-search"></i></button>
                            </form>
                        </div>
                    </div>
                </section>

                <?php if ($allCategories || $filterDifficulty !== ''): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if ($filterDifficulty !== '' || $filterCategory !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="courses.php<?php echo $search !== '' ? '?q=' . urlencode($search) : ''; ?>">Wyczyść filtry</a><?php endif; ?>
                    <?php foreach (['beginner' => 'Początkujący', 'intermediate' => 'Średniozaawansowany', 'advanced' => 'Zaawansowany'] as $dv => $dl): ?>
                        <a class="btn btn-sm <?php echo $filterDifficulty === $dv ? 'btn-primary' : 'btn-outline-primary'; ?>" href="?<?php echo htmlspecialchars(http_build_query(array_filter(['q' => $search, 'difficulty' => $filterDifficulty === $dv ? '' : $dv, 'category' => $filterCategory])), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $dl; ?></a>
                    <?php endforeach; ?>
                    <?php foreach ($allCategories as $cat): ?>
                        <a class="btn btn-sm <?php echo $filterCategory === $cat ? 'btn-info' : 'btn-outline-info'; ?>" href="?<?php echo htmlspecialchars(http_build_query(array_filter(['q' => $search, 'difficulty' => $filterDifficulty, 'category' => $filterCategory === $cat ? '' : $cat])), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
                    <div>
                        <h2 class="h4 fw-bold mb-1"><?php echo $search !== '' ? 'Wyniki wyszukiwania' : 'Dostępne kursy'; ?></h2>
                        <p class="text-muted mb-0"><?php echo $total === 1 ? 'Znaleziono 1 kurs.' : 'Znaleziono ' . $total . ' kursów.'; ?></p>
                    </div>
                    <?php if ($search !== ''): ?><a class="btn btn-outline-secondary" href="courses.php">Wyczyść wyszukiwanie</a><?php endif; ?>
                </div>

                <?php if (!$courses): ?>
                    <div class="dashboard-panel text-center p-5">
                        <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                        <h2 class="h5 fw-bold">Brak dostępnych kursów</h2>
                        <p class="text-muted mb-0">Spróbuj innego zapytania lub zajrzyj tutaj później.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($courses as $course): ?>
                            <?php 
                            $cover = courseDisplayImageUrl((string)($course['image_url'] ?? ''));
                            $isExt = (int)($course['is_external'] ?? 0) === 1;
                            ?>
                            <article class="col-12 col-md-6 col-xl-4">
                                <div class="course-card h-100 d-flex flex-column">
                                    <?php if ($cover): ?>
                                        <img class="course-cover" src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>" alt="Okładka kursu: <?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="course-cover-placeholder" aria-hidden="true">
                                            <i class="bi <?php echo $isExt ? 'bi-box-arrow-up-right text-warning' : 'bi-journal-bookmark-fill'; ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="course-card-body d-flex flex-column flex-grow-1">
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <?php if ($isExt): ?>
                                                <span class="badge text-bg-warning text-dark"><i class="bi bi-box-arrow-up-right me-1"></i>Kurs Zewnętrzny</span>
                                            <?php endif; ?>
                                            <?php if ($course['status'] === 'private'): ?>
                                                <?php if ($currentUserId > 0 && (int)($course['created_by'] ?? 0) === $currentUserId): ?>
                                                    <span class="badge text-bg-warning text-dark"><i class="bi bi-lock-fill me-1"></i>Tylko dla mnie</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-warning text-dark"><i class="bi bi-share-fill me-1"></i>Udostępniony dla Ciebie</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($course['difficulty']) && isset($difficultyLabels[$course['difficulty']])): ?>
                                                <span class="badge text-bg-<?php echo $difficultyColors[$course['difficulty']]; ?>"><?php echo $difficultyLabels[$course['difficulty']]; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($course['category'])): ?>
                                                <span class="badge text-bg-info text-dark"><?php echo htmlspecialchars((string)$course['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="h5 fw-bold mb-2"><?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="text-muted small flex-grow-1 mb-3"><?php echo htmlspecialchars(mb_strimwidth((string)($course['description'] ?? ''), 0, 140, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></p>
                                        <div class="course-card-meta mb-3">
                                            <?php if ($isExt): ?>
                                                <span><i class="bi bi-globe me-1 text-warning"></i>Źródło zewnętrzne</span>
                                                <span title="Kursy zewnętrzne nie posiadają certyfikatów ZSEM TECH (pracujemy nad tym!)"><i class="bi bi-patch-minus me-1 text-muted"></i>Brak certyfikatu*</span>
                                            <?php else: ?>
                                                <span><i class="bi bi-folder2-open me-1 text-primary"></i><?php echo (int)$course['module_count']; ?> modułów</span>
                                                <span><i class="bi bi-journal-text me-1 text-info"></i><?php echo (int)$course['item_count']; ?> lekcji</span>
                                                <span><i class="bi bi-people me-1 text-success"></i><?php echo (int)$course['enrollment_count']; ?> zapisanych</span>
                                            <?php endif; ?>
                                            <?php if (!empty($course['estimated_hours'])): ?><span><i class="bi bi-clock me-1 text-warning"></i>~<?php echo (int)$course['estimated_hours']; ?>h</span><?php endif; ?>
                                        </div>
                                        <a class="course-card-btn w-100" href="course_view.php?id=<?php echo (int)$course['id']; ?>">
                                            <?php echo $isExt ? 'Zobacz opis i link <i class="bi bi-box-arrow-up-right ms-1"></i>' : 'Zobacz kurs <i class="bi bi-arrow-right ms-2"></i>'; ?>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($pages > 1): ?>
                    <nav class="mt-4" aria-label="Strony kursów">
                        <ul class="pagination justify-content-center mb-0">
                            <?php for ($number = 1; $number <= $pages; $number++): ?>
                                <?php if ($number === 1 || $number === $pages || abs($number - $page) <= 2): ?>
                                    <li class="page-item <?php echo $number === $page ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $search, 'page' => $number]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $number; ?></a></li>
                                <?php elseif ($number === 2 || $number === $pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
