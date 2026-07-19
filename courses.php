<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Możliwy dostęp dla gości lub zalogowanych
startSecureSession();
if (function_exists('enforceFeaturePageBlockForCurrentRequest')) {
    enforceFeaturePageBlockForCurrentRequest($pdo);
}

if (function_exists('ensurePlatformEnhancements')) {
    ensurePlatformEnhancements($pdo);
}

$search = trim($_GET['q'] ?? '');

$params = ['active'];
$whereClauses = ["c.status = ?"];

if ($search !== '') {
    $whereClauses[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM user_course_enrollments uce WHERE uce.course_id = c.id) AS enrolled_count FROM courses c $whereSql ORDER BY c.id DESC");
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kursy - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
    .course-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid var(--border-color);
        background-color: var(--panel-bg);
        overflow: hidden;
        border-radius: var(--radius-duzy);
    }
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--cień-sredni);
        border-color: var(--primary-color);
    }
    .course-card .card-title {
        color: var(--text-main);
        font-weight: 700;
    }
    .course-card .card-text {
        color: var(--text-muted);
    }
    .course-card .card-img-wrapper {
        position: relative;
        height: 160px;
        overflow: hidden;
    }
    .course-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }
    .course-card:hover img {
        transform: scale(1.05);
    }
    .search-container-custom {
        position: relative;
        width: 100%;
    }
    .search-container-custom i {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--kolor-tekst-jasny);
    }
    .search-container-custom input {
        padding-left: 3rem;
        border-radius: 100px;
        border: 1px solid var(--border-color);
        background-color: var(--panel-bg);
        color: var(--text-main);
        transition: all 0.2s;
    }
    .search-container-custom input:focus {
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        border-color: var(--primary-color);
        outline: none;
    }
</style>
HTML;
include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>

        <main role="main" class="content-body">
            <div class="container-fluid p-0">
                
                <!-- Header & Search -->
                <div class="row align-items-center mb-4 animate-in">
                    <div class="col-md-7">
                        <h2 class="fw-bold mb-1"><i class="bi bi-mortarboard text-primary me-2"></i>Dostępne Kursy</h2>
                        <p class="text-muted mb-0">Rozwijaj swoje umiejętności z naszymi materiałami.</p>
                    </div>
                    <div class="col-md-5 mt-3 mt-md-0">
                        <form method="GET" action="courses.php">
                            <div class="search-container-custom">
                                <i class="bi bi-search"></i>
                                <input type="text" name="q" class="form-control form-control-lg" placeholder="Wyszukaj kurs..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4">
                    <?php if (empty($courses)): ?>
                        <div class="col-12">
                            <div class="text-center text-muted py-5 dashboard-panel rounded-3">
                                <i class="bi bi-journal-x fs-1 mb-3 d-block opacity-50 text-primary"></i>
                                Brak dostępnych kursów w tej chwili.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="card h-100 course-card">
                                    <div class="card-img-wrapper">
                                        <?php if (!empty($course['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($course['image_url']); ?>" alt="Okładka kursu">
                                        <?php else: ?>
                                            <div class="w-100 h-100 bg-secondary d-flex align-items-center justify-content-center" style="opacity: 0.15;">
                                                <i class="bi bi-image fs-1 text-dark"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                        <p class="card-text small flex-grow-1">
                                            <?php echo htmlspecialchars(mb_strimwidth($course['description'] ?? '', 0, 100, '...')); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top" style="border-color: var(--border-color) !important;">
                                            <span class="small text-muted">
                                                <i class="bi bi-people me-1"></i>
                                                <?php echo (int)($course['enrolled_count'] ?? 0); ?> zapisanych
                                            </span>
                                            <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm stretched-link">
                                                Otwórz
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
</body>
</html>
