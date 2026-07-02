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
                        </div>
                        
                        <div class="fs-5 mb-4 text-muted" style="line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($course['description'] ?? '')); ?>
                        </div>

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
