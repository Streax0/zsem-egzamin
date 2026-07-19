<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

startSecureSession();
if (function_exists('enforceFeaturePageBlockForCurrentRequest')) {
    enforceFeaturePageBlockForCurrentRequest($pdo);
}

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($courseId <= 0) {
    redirect('courses.php');
}

// Fetch course details
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
$stmt->execute([$courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    setSessionMessage('error', 'Kurs nie istnieje lub jest niedostępny.');
    redirect('courses.php');
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : 0;
$isEnrolled = false;

if ($isLoggedIn) {
    $checkStmt = $pdo->prepare("SELECT id FROM user_course_enrollments WHERE course_id = ? AND user_id = ?");
    $checkStmt->execute([$courseId, $userId]);
    $isEnrolled = (bool)$checkStmt->fetchColumn();
}

// Fetch enrollment count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_course_enrollments WHERE course_id = ?");
$countStmt->execute([$courseId]);
$enrolledCount = (int)$countStmt->fetchColumn();

// Fetch course structure for preview
$structStmt = $pdo->prepare("SELECT cm.id as module_id, cm.title as module_title, COUNT(ci.id) as item_count FROM course_modules cm LEFT JOIN course_items ci ON ci.module_id = cm.id WHERE cm.course_id = ? GROUP BY cm.id, cm.title ORDER BY cm.sort_order ASC");
$structStmt->execute([$courseId]);
$courseStructure = $structStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Enroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    if (!$isLoggedIn) {
        setSessionMessage('error', 'Musisz być zalogowany, aby zapisać się na kurs.');
        redirect('login.php');
    }
    
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token, 'enroll_course')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('course_view.php?id=' . $courseId);
    }
    
    if (!$isEnrolled) {
        $enrollStmt = $pdo->prepare("INSERT INTO user_course_enrollments (course_id, user_id) VALUES (?, ?)");
        if ($enrollStmt->execute([$courseId, $userId])) {
            setSessionMessage('success', 'Zostałeś pomyślnie zapisany na kurs!');
            $isEnrolled = true;
            redirect('course_view.php?id=' . $courseId);
        } else {
            setSessionMessage('error', 'Wystąpił błąd podczas zapisywania na kurs.');
        }
    }
}

$rawFlash = getSessionMessage();
$flashMessage = $rawFlash['message'] ?? '';
$flashType = $rawFlash['type'] ?? 'info';

$pageTitle = htmlspecialchars($course['title']) . ' - Kursy - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>

        <main role="main" class="content-body">
            <div class="container-fluid p-0">
                
                <div class="mb-4 animate-in">
                    <a href="courses.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left me-1"></i> Wróć do listy kursów</a>
                </div>

                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashType === 'error' ? 'danger' : 'success'); ?> alert-dismissible fade show mb-4 border-0 shadow-sm animate-in" role="alert">
                        <i class="bi <?php echo $flashType === 'error' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-check-circle-fill text-success'; ?> me-2"></i>
                        <?php echo htmlspecialchars($flashMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                    </div>
                <?php endif; ?>

                <div class="dashboard-panel p-0 rounded-3 overflow-hidden mb-4 border-0 animate-in" style="animation-delay: 0.05s;">
                    <?php if (!empty($course['image_url'])): ?>
                        <div style="height: 250px; background-image: url('<?php echo htmlspecialchars($course['image_url']); ?>'); background-size: cover; background-position: center;">
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted border-bottom" style="background-color: rgba(0,0,0,0.02); border-color: var(--border-color) !important;">
                            <i class="bi bi-journal-text text-primary" style="font-size: 4rem;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-4 p-md-5">
                        <h2 class="fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h2>
                        <div class="d-flex flex-wrap gap-3 mb-4 text-muted small border-bottom pb-3" style="border-color: var(--border-color) !important;">
                            <span><i class="bi bi-calendar-event me-1 text-primary"></i>
                                <?php 
                                    $sd = $course['start_date'] ? date('d.m.Y', strtotime($course['start_date'])) : '';
                                    $ed = $course['end_date'] ? date('d.m.Y', strtotime($course['end_date'])) : '';
                                    if ($sd && $ed) echo "$sd - $ed";
                                    elseif ($sd) echo "Od: $sd";
                                    elseif ($ed) echo "Do: $ed";
                                    else echo "Cały czas";
                                ?>
                            </span>
                            <span><i class="bi bi-people me-1 text-primary"></i><?php echo $enrolledCount; ?> zapisanych</span>
                            <span><i class="bi bi-journal-text me-1 text-primary"></i><?php echo count($courseStructure); ?> modułów</span>
                        </div>
                        
                        <div class="fs-5 mb-4 text-muted" style="line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($course['description'] ?? '')); ?>
                        </div>

                        <?php if (!empty($courseStructure)): ?>
                            <div class="mt-4 pt-4 border-top" style="border-color: var(--border-color) !important;">
                                <h5 class="fw-bold mb-3"><i class="bi bi-list-nested me-2 text-primary"></i>Zawartość kursu</h5>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($courseStructure as $i => $module): ?>
                                        <div class="list-group-item bg-transparent border-0 px-0 py-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-primary bg-opacity-10 text-primary" style="min-width: 28px;"><?php echo $i + 1; ?></span>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($module['module_title']); ?></span>
                                                <span class="text-muted small ms-auto"><?php echo (int)$module['item_count']; ?> lekcji</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isLoggedIn): ?>
                            <div class="alert alert-warning border-0 shadow-sm mt-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <i class="bi bi-info-circle-fill me-2"></i> Musisz być zalogowany, aby zapisać się na ten kurs i uzyskać dostęp do materiałów.
                                </div>
                                <a href="login.php" class="btn btn-warning btn-sm fw-bold">Zaloguj się</a>
                            </div>
                        <?php elseif (!$isEnrolled): ?>
                            <form method="POST" action="course_view.php?id=<?php echo $course['id']; ?>" class="mt-4">
                                <?php echo csrfTokenField('enroll_course'); ?>
                                <input type="hidden" name="action" value="enroll">
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 d-flex align-items-center justify-content-center gap-2 shadow-sm" style="font-weight: 600; border-radius: 50px;">
                                    <i class="bi bi-journal-plus fs-4"></i> Zapisz się na kurs
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEnrolled): ?>
                    <div class="dashboard-panel p-4 p-md-5 rounded-3 mb-4 animate-in text-center" style="animation-delay: 0.1s; background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(16, 185, 129, 0.08)); border: 1px solid var(--border-color);">
                        <h4 class="fw-bold text-primary mb-2"><i class="bi bi-mortarboard-fill me-2"></i>Jesteś zapisany na ten kurs</h4>
                        <p class="text-muted mb-4">Uzyskaj dostęp do pełnych materiałów, interaktywnych lekcji wideo, quizów oraz laboratoriów sieciowych/logicznych.</p>
                        <a href="course_learn.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success btn-lg px-5 py-3 fw-bold shadow-sm" style="border-radius: 50px;">
                            <i class="bi bi-play-circle-fill me-2 fs-5"></i> Otwórz panel lekcji (Cisco Style)
                        </a>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
</body>
</html>
