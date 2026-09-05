<?php
declare(strict_types=1);

require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/CourseService.php';

startSecureSession();

$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$isAdmin = isLoggedIn() && roleHasAdminAccess((string)($_SESSION['role'] ?? 'user'));
$courseId = (int)($_GET['id'] ?? 0);
$course = courseFetchById($pdo, $courseId);
if (!$course || !courseCanUserAccess($pdo, $course, $userId, $isAdmin)) {
    setSessionMessage('error', 'Ten kurs nie jest obecnie dostępny.');
    redirect('courses.php');
}
$enrollment = null;
if ($userId > 0) {
    $statement = $pdo->prepare('SELECT id, status, progress_percent FROM user_course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $statement->execute([$userId, $courseId]);
    $enrollment = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    if ($userId <= 0) {
        setSessionMessage('error', 'Zaloguj się, aby rozpocząć kurs.');
        redirect('auth/login.php?return=' . urlencode('course_view.php?id=' . $courseId));
    }
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''), 'course_enroll')) {
        setSessionMessage('error', 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.');
        redirect('course_view.php?id=' . $courseId);
    }
    $rate = securityConsumeRateLimit('course_enroll:' . $userId . ':' . securityClientIp(), 12, 300);
    if (empty($rate['allowed'])) {
        setSessionMessage('error', 'Zbyt wiele prób zapisu. Spróbuj ponownie za chwilę.');
        redirect('course_view.php?id=' . $courseId);
    }
    $statement = $pdo->prepare("INSERT INTO user_course_enrollments (user_id, course_id, status, progress_percent) VALUES (?, ?, 'active', 0) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
    $statement->execute([$userId, $courseId]);
    securityAudit('course_enrolled', ['course_id' => $courseId, 'user_id' => $userId]);
    setSessionMessage('success', $enrollment ? 'Nadal jesteś zapisany na ten kurs.' : 'Kurs został dodany do Twojej ścieżki nauki.');
    redirect('course_learn.php?course_id=' . $courseId);
}

$structure = courseFetchStructure($pdo, $courseId);
$items = courseItemsInOrder($structure);

$allExamsPassed = true;
if ($userId > 0 && $enrollment && !empty($items)) {
    foreach ($items as $it) {
        if (in_array($it['type'], ['quiz', 'exam'], true)) {
            $eStmt = $pdo->prepare("SELECT status FROM user_course_progress WHERE user_id = ? AND item_id = ? LIMIT 1");
            $eStmt->execute([$userId, (int)$it['id']]);
            if ($eStmt->fetchColumn() !== 'completed') {
                $allExamsPassed = false;
                break;
            }
        }
    }
}

$statement = $pdo->prepare('SELECT COUNT(*) FROM user_course_enrollments WHERE course_id = ?');
$statement->execute([$courseId]);
$enrollmentCount = (int)$statement->fetchColumn();
$flash = getSessionMessage();
$cover = courseDisplayImageUrl((string)($course['image_url'] ?? ''));
$pageTitle = (string)$course['title'] . ' — ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/courses.css'];
include 'includes/header.php';
?>

<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main class="content-body" id="main-content">
            <div class="container-fluid p-0">
                
                <?php if (!empty($flash['message'])): ?>
                    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show mb-4" role="alert">
                        <?php echo htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <a class="btn btn-sm btn-outline-secondary" href="courses.php">
                        <i class="bi bi-arrow-left me-1"></i> Wróć do kursów
                    </a>
                </div>

                <!-- Hero Section -->
                <?php $isExt = (int)($course['is_external'] ?? 0) === 1; ?>
                <section class="course-hero mb-4 p-4 p-lg-5">
                    <div class="course-hero-content">
                        <div class="row g-4 align-items-center">
                            <div class="col-lg-8">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php if ($isExt): ?>
                                        <span class="badge text-bg-warning text-dark px-3 py-2">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>Kurs Zewnętrzny
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-primary px-3 py-2">
                                            <i class="bi bi-layers me-1"></i>
                                            <?php echo (int)$course['sequential_learning'] === 1 ? 'Ścieżka sekwencyjna' : 'Elastyczna ścieżka'; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php
                                    $diffLabels = ['beginner' => 'Początkujący', 'intermediate' => 'Średniozaawansowany', 'advanced' => 'Zaawansowany'];
                                    $diffColors = ['beginner' => 'success', 'intermediate' => 'warning', 'advanced' => 'danger'];
                                    ?>
                                    <?php if (!empty($course['difficulty']) && isset($diffLabels[$course['difficulty']])): ?>
                                        <span class="badge text-bg-<?php echo $diffColors[$course['difficulty']]; ?> px-3 py-2">
                                            <?php echo $diffLabels[$course['difficulty']]; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($course['category'])): ?>
                                        <span class="badge text-bg-info px-3 py-2">
                                            <i class="bi bi-tag me-1"></i>
                                            <?php echo htmlspecialchars((string)$course['category'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex align-items-start gap-4">
                                    <?php if (!empty($cover)): ?>
                                        <img src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="rounded-3 border border-secondary shadow-sm d-none d-md-block" style="width: 130px; height: 130px; object-fit: cover; flex-shrink: 0;" loading="lazy" decoding="async">
                                    <?php endif; ?>
                                    <div>
                                        <h1 class="display-6 fw-bold text-white mb-3">
                                            <?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h1>

                                        <?php if (!empty($course['description'])): ?>
                                            <p class="fs-5 mb-4 opacity-90" style="color: #cbd5e1; line-height: 1.6;">
                                                <?php echo nl2br(htmlspecialchars((string)$course['description'], ENT_QUOTES, 'UTF-8')); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="course-meta-pills">
                                    <?php if ($isExt): ?>
                                        <div class="course-meta-pill">
                                            <i class="bi bi-globe text-warning"></i>
                                            <span>Materiały zewnętrzne</span>
                                        </div>
                                        <?php if (!empty($structure)): ?>
                                            <div class="course-meta-pill">
                                                <i class="bi bi-folder2-open text-primary"></i>
                                                <span><strong><?php echo count($structure); ?></strong> modułów</span>
                                            </div>
                                            <div class="course-meta-pill">
                                                <i class="bi bi-journal-text text-info"></i>
                                                <span><strong><?php echo count($items); ?></strong> tematów</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="course-meta-pill">
                                            <i class="bi bi-patch-minus text-secondary"></i>
                                            <span>Brak certyfikatu ZSEM TECH (pracujemy nad tym!)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="course-meta-pill">
                                            <i class="bi bi-folder2-open text-primary"></i>
                                            <span><strong><?php echo count($structure); ?></strong> modułów</span>
                                        </div>
                                        <div class="course-meta-pill">
                                            <i class="bi bi-journal-text text-info"></i>
                                            <span><strong><?php echo count($items); ?></strong> lekcji</span>
                                        </div>
                                        <div class="course-meta-pill">
                                            <i class="bi bi-people text-success"></i>
                                            <span><strong><?php echo $enrollmentCount; ?></strong> zapisanych uczniów</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($course['estimated_hours'])): ?>
                                        <div class="course-meta-pill">
                                            <i class="bi bi-clock text-warning"></i>
                                            <span><strong>~<?php echo (int)$course['estimated_hours']; ?>h</strong> czas trwania</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="course-action-card">
                                    <?php if ($isExt): ?>
                                        <div class="text-center p-2">
                                            <h3 class="h5 fw-bold text-white mb-2"><i class="bi bi-box-arrow-up-right text-warning me-2"></i>Dostęp zewnętrzny</h3>
                                            <p class="small mb-4" style="color: #cbd5e1;">Otwórz oficjalną stronę zewnętrznego dostawcy tego kursu.</p>
                                            <?php if (!empty($course['external_url'])): ?>
                                                <a href="<?php echo htmlspecialchars((string)$course['external_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-warning btn-lg w-100 fw-bold shadow py-3">
                                                    <i class="bi bi-box-arrow-up-right me-2"></i>Przejdź do kursu
                                                </a>
                                            <?php else: ?>
                                                <div class="alert alert-secondary mb-0 small text-muted">Brak bezpośredniego odnośnika URL.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($enrollment): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-2" style="color: #e2e8f0;">
                                                <span>Twój postęp w kursie</span>
                                                <strong class="text-success fs-6"><?php echo (int)$enrollment['progress_percent']; ?>%</strong>
                                            </div>
                                            <div class="progress" style="height: 0.65rem; background: rgba(255,255,255,0.15);">
                                                <div class="progress-bar" style="width: <?php echo (int)$enrollment['progress_percent']; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                                            </div>
                                        </div>

                                        <a class="btn btn-complete-lesson w-100 mb-2" href="course_learn.php?course_id=<?php echo $courseId; ?>">
                                            <i class="bi bi-play-fill me-1"></i>
                                            <?php echo (int)$enrollment['progress_percent'] > 0 ? 'Kontynuuj naukę' : 'Rozpocznij naukę'; ?>
                                        </a>

                                        <?php if ((int)$enrollment['progress_percent'] >= 100 && $allExamsPassed && (int)($course['has_certificate'] ?? 1) === 1): ?>
                                            <a class="btn btn-gold-cert w-100" href="course_certificate.php?course_id=<?php echo $courseId; ?>" target="_blank" rel="noopener noreferrer">
                                                <i class="bi bi-award-fill me-1"></i> Pobierz Certyfikat
                                            </a>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <form method="post" action="course_view.php?id=<?php echo $courseId; ?>">
                                            <?php echo csrfTokenField('course_enroll'); ?>
                                            <h3 class="h5 fw-bold text-white mb-2">Rozpocznij ten kurs</h3>
                                            <p class="small mb-4" style="color: #cbd5e1;">Uzyskaj natychmiastowy dostęp do wszystkich modułów i materiałów szkoleniowych.</p>
                                            
                                            <ul class="list-unstyled small d-flex flex-column gap-2 mb-4" style="color: #e2e8f0;">
                                                <li class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-success"></i> Pełny dostęp 24/7</li>
                                                <li class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-success"></i> Zadania i Quizy wiedzy</li>
                                                <?php if ((int)($course['has_certificate'] ?? 1) === 1): ?>
                                                    <li class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-success"></i> Cyfrowy Certyfikat po zaliczeniu</li>
                                                <?php endif; ?>
                                            </ul>

                                            <button class="btn btn-primary btn-lg w-100 shadow" type="submit">
                                                <i class="bi bi-plus-circle me-1"></i> Zapisz się na kurs
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($isExt): ?>
                    <div class="alert alert-warning border-warning d-flex align-items-start gap-3 p-3 p-md-4 mb-4 rounded-4 shadow-sm" style="background: rgba(245, 158, 11, 0.08);">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0 mt-1"></i>
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Informacja o kursie zewnętrznym</h5>
                            <p class="mb-2 text-secondary">Ten kurs jest materiałem dydaktycznym udostępnianym na zewnętrznej platformie. Opis i treść zostały podane przez twórcę kursu.</p>
                            <div class="small fw-semibold text-warning-emphasis bg-warning bg-opacity-10 p-2 px-3 rounded-3 d-inline-block border border-warning border-opacity-25">
                                <i class="bi bi-info-circle-fill me-1"></i><strong>Ważna informacja:</strong> Kursy zewnętrzne nie posiadają obecnie certyfikatów ZSEM TECH (pracujemy nad tym!).
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Course Program / Syllabus -->
                <section class="dashboard-panel p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h2 class="h4 fw-bold mb-0">
                            <i class="bi bi-journal-bookmark text-primary me-2"></i> Program kursu
                        </h2>
                        <span class="badge text-bg-light border text-muted px-3 py-2">
                            <?php echo count($structure); ?> modułów • <?php echo count($items); ?> lekcji
                        </span>
                    </div>

                    <?php if (!$structure): ?>
                        <div class="p-5 text-center text-muted border rounded-3 bg-light">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 text-primary opacity-50"></i>
                            Autor przygotowuje jeszcze materiały do tego kursu.
                        </div>
                    <?php else: ?>
                        <div class="accordion syllabus-accordion d-flex flex-column gap-3" id="syllabusAccordion">
                            <?php foreach ($structure as $number => $module): ?>
                                <div class="syllabus-card">
                                    <div class="syllabus-header d-flex align-items-center justify-content-between" data-bs-toggle="collapse" data-bs-target="#collapseSyllabus<?php echo $number; ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge text-bg-primary rounded-pill px-3 py-2">
                                                Moduł <?php echo sprintf('%02d', $number + 1); ?>
                                            </span>
                                            <div>
                                                <h3 class="h6 fw-bold mb-0">
                                                    <?php echo htmlspecialchars((string)$module['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                </h3>
                                                <?php if (!empty($module['description'])): ?>
                                                    <div class="small text-muted mt-1">
                                                        <?php echo htmlspecialchars((string)$module['description'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge text-bg-light border text-muted">
                                                <?php echo count($module['items']); ?> lekcji
                                            </span>
                                            <i class="bi bi-chevron-down text-muted"></i>
                                        </div>
                                    </div>

                                    <div id="collapseSyllabus<?php echo $number; ?>" class="collapse <?php echo $number === 0 ? 'show' : ''; ?>" data-bs-parent="#syllabusAccordion">
                                        <div class="border-top">
                                            <?php foreach ($module['items'] as $item): ?>
                                                <?php
                                                $icon = 'bi-file-earmark-text';
                                                $typeName = 'Lekcja';
                                                if (($item['type'] ?? '') === 'video') { $icon = 'bi-play-circle-fill text-danger'; $typeName = 'Wideo'; }
                                                if (($item['type'] ?? '') === 'quiz') { $icon = 'bi-patch-question-fill text-warning'; $typeName = 'Quiz'; }
                                                if (($item['type'] ?? '') === 'exam') { $icon = 'bi-award-fill text-primary'; $typeName = 'Egzamin'; }
                                                if (($item['type'] ?? '') === 'lab') { $icon = 'bi-terminal-fill text-success'; $typeName = 'Laboratorium'; }
                                                ?>
                                                <div class="syllabus-lesson-row">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <i class="bi <?php echo $icon; ?> fs-5"></i>
                                                        <span class="fw-semibold">
                                                            <?php echo htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </div>
                                                    <span class="badge text-bg-light border text-uppercase" style="font-size: 0.72rem;">
                                                        <?php echo $typeName; ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
