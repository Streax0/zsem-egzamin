<?php
declare(strict_types=1);

require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/CourseService.php';

startSecureSession();
requireLogin();

$userId = (int)$_SESSION['user_id'];
$isAdmin = roleHasAdminAccess((string)($_SESSION['role'] ?? 'user'));
$courseId = (int)($_GET['course_id'] ?? 0);
$course = courseFetchById($pdo, $courseId);
if (!$course || !courseCanUserAccess($pdo, $course, $userId, $isAdmin)) {
    setSessionMessage('error', 'Ten kurs nie jest obecnie dostępny.');
    redirect('courses.php');
}
$statement = $pdo->prepare('SELECT status, progress_percent FROM user_course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
$statement->execute([$userId, $courseId]);
$enrollment = $statement->fetch(PDO::FETCH_ASSOC);
if (!$enrollment) {
    setSessionMessage('error', 'Zapisz się na kurs, aby otworzyć materiały.');
    redirect('course_view.php?id=' . $courseId);
}

$structure = courseFetchStructure($pdo, $courseId, true);
$items = courseItemsInOrder($structure);
if (!$items) {
    setSessionMessage('error', 'Ten kurs nie zawiera jeszcze lekcji.');
    redirect('course_view.php?id=' . $courseId);
}

$statement = $pdo->prepare('SELECT item_id, status, quiz_score, quiz_attempts FROM user_course_progress WHERE user_id = ? AND course_id = ?');
$statement->execute([$userId, $courseId]);
$progressMap = [];
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $progressMap[(int)$row['item_id']] = $row;
}

$sequential = (int)$course['sequential_learning'] === 1;
$locked = [];
$seenFirstIncomplete = false;
foreach ($items as $item) {
    $itemId = (int)$item['id'];
    $isComplete = ($progressMap[$itemId]['status'] ?? '') === 'completed';
    $locked[$itemId] = $sequential && $seenFirstIncomplete;
    if (!$isComplete) {
        $seenFirstIncomplete = true;
    }
}

$requestedItemId = (int)($_GET['item_id'] ?? 0);
$activeItem = null;
foreach ($items as $item) {
    if ((int)$item['id'] === $requestedItemId && empty($locked[(int)$item['id']])) {
        $activeItem = $item;
        break;
    }
}
if (!$activeItem) {
    foreach ($items as $item) {
        $itemId = (int)$item['id'];
        if (!$locked[$itemId] && (($progressMap[$itemId]['status'] ?? '') !== 'completed')) {
            $activeItem = $item;
            break;
        }
    }
}
if (!$activeItem) {
    $activeItem = $items[0];
}
$activeItemId = (int)$activeItem['id'];
$activeProgress = $progressMap[$activeItemId] ?? null;
$completed = count(array_filter($items, static fn(array $item): bool => (($progressMap[(int)$item['id']]['status'] ?? '') === 'completed')));
$progressPercent = (int)round(($completed / count($items)) * 100);

// Find Prev and Next lesson
$currentIndex = -1;
foreach ($items as $idx => $it) {
    if ((int)$it['id'] === $activeItemId) {
        $currentIndex = $idx;
        break;
    }
}
$prevItem = ($currentIndex > 0) ? $items[$currentIndex - 1] : null;
$nextItem = ($currentIndex >= 0 && $currentIndex < count($items) - 1) ? $items[$currentIndex + 1] : null;
if ($prevItem && !empty($locked[(int)$prevItem['id']])) { $prevItem = null; }
if ($nextItem && !empty($locked[(int)$nextItem['id']])) { $nextItem = null; }

$allExamsPassed = true;
foreach ($items as $it) {
    if (in_array($it['type'], ['quiz', 'exam'], true)) {
        if (($progressMap[(int)$it['id']]['status'] ?? '') !== 'completed') {
            $allExamsPassed = false;
            break;
        }
    }
}

$questions = [];
if (in_array((string)$activeItem['type'], ['quiz', 'exam'], true)) {
    $statement = $pdo->prepare('SELECT id, question_text, option_a, option_b, option_c, option_d FROM course_quiz_questions WHERE item_id = ? ORDER BY id ASC');
    $statement->execute([$activeItemId]);
    $questions = $statement->fetchAll(PDO::FETCH_ASSOC);
}

$labTool = '';
$labInstructions = '';
if (($activeItem['type'] ?? '') === 'lab') {
    $labTool = (string)($activeItem['lab_tool_key'] ?? '');
    $labInstructions = (string)($activeItem['lab_instructions'] ?? '');
    if (($activeItem['lab_source'] ?? '') === 'custom' && (int)($activeItem['lab_custom_id'] ?? 0) > 0) {
        $statement = $pdo->prepare('SELECT tool_key, instructions FROM course_custom_labs WHERE id = ? LIMIT 1');
        $statement->execute([(int)$activeItem['lab_custom_id']]);
        $customLab = $statement->fetch(PDO::FETCH_ASSOC);
        if ($customLab) {
            $labTool = (string)$customLab['tool_key'];
            $labInstructions = $labInstructions !== '' ? $labInstructions : (string)$customLab['instructions'];
        }
    }
    if (!in_array($labTool, COURSE_LAB_TOOLS, true)) {
        $labTool = '';
    }
}

$labBlocked = false;
if ($labTool !== '' && function_exists('getSandboxElementBlockMapForRole')) {
    $blocks = getSandboxElementBlockMapForRole($pdo, (string)($_SESSION['role'] ?? 'user'));
    $labBlocked = isset($blocks['tool.' . $labTool]);
}
$videoEmbed = ($activeItem['type'] ?? '') === 'video' ? courseYoutubeEmbedUrl((string)($activeItem['video_url'] ?? '')) : null;
$typeLabels = ['text' => 'Lekcja', 'video' => 'Wideo', 'quiz' => 'Quiz', 'exam' => 'Egzamin', 'lab' => 'Laboratorium'];
$typeIcons = ['text' => 'bi-file-earmark-text', 'video' => 'bi-play-circle-fill', 'quiz' => 'bi-patch-question-fill', 'exam' => 'bi-award-fill', 'lab' => 'bi-terminal-fill'];
$pageTitle = (string)$activeItem['title'] . ' — ' . (string)$course['title'];
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/courses.css'];
include 'includes/header.php';
?>

<!-- Player Top Navigation Bar -->
<header class="player-topbar d-flex align-items-center justify-content-between px-3 py-2 border-bottom sticky-top">
    <div class="d-flex align-items-center gap-3">
        <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" aria-label="Wróć do informacji o kursie">
            <i class="bi bi-arrow-left me-1"></i> Wróć do kursu
        </a>
        <div class="vr opacity-25 d-none d-md-block" style="height: 1.25rem;"></div>
        <div class="d-none d-md-block fw-bold text-truncate" style="max-width: 320px;">
            <?php echo htmlspecialchars((string)$course['title'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <div class="d-none d-lg-flex align-items-center gap-2">
            <span class="small text-muted">Postęp:</span>
            <strong class="small text-success"><?php echo $progressPercent; ?>%</strong>
            <div class="progress" style="width: 80px; height: 6px; background: rgba(148, 163, 184, 0.2);">
                <div class="progress-bar" style="width: <?php echo $progressPercent; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if ($prevItem): ?>
                <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo (int)$prevItem['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Poprzednia lekcja">
                    <i class="bi bi-chevron-left"></i> <span class="d-none d-sm-inline ms-1">Poprzednia</span>
                </a>
            <?php endif; ?>

            <?php if ($nextItem): ?>
                <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo (int)$nextItem['id']; ?>" class="btn btn-sm btn-primary" title="Następna lekcja">
                    <span class="d-none d-sm-inline me-1">Następna</span> <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="course-player" data-course-player data-course-id="<?php echo $courseId; ?>" data-active-item-id="<?php echo $activeItemId; ?>" data-csrf-token="<?php echo htmlspecialchars(generateCsrfToken('course_progress'), ENT_QUOTES, 'UTF-8'); ?>" id="main-content">
    
    <!-- Sidebar / Lesson Outline -->
    <aside class="course-player-sidebar" aria-label="Spis treści kursu">
        <div class="px-2 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small text-muted text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Program Kursu</span>
                <span class="badge text-bg-light border text-muted"><?php echo $completed; ?> / <?php echo count($items); ?></span>
            </div>
            <div class="progress" style="height: 0.4rem; background: rgba(148, 163, 184, 0.2);">
                <div class="progress-bar" data-course-progress role="progressbar" aria-valuenow="<?php echo $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $progressPercent; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
            </div>
        </div>

        <?php if ($progressPercent >= 100 && $allExamsPassed && (int)($course['has_certificate'] ?? 1) === 1): ?>
            <a class="btn btn-gold-cert btn-sm w-100 mb-3" href="course_certificate.php?course_id=<?php echo $courseId; ?>" target="_blank">
                <i class="bi bi-award-fill me-1"></i> Pobierz Certyfikat
            </a>
        <?php endif; ?>

        <nav class="d-flex flex-column gap-3">
            <?php foreach ($structure as $moduleNumber => $module): ?>
                <section class="course-player-module">
                    <h2 class="course-player-module-title">
                        Moduł <?php echo sprintf('%02d', $moduleNumber + 1); ?>: <?php echo htmlspecialchars((string)$module['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($module['items'] as $item): ?>
                            <?php
                            $itemId = (int)$item['id'];
                            $isCurrent = $itemId === $activeItemId;
                            $isCompleted = ($progressMap[$itemId]['status'] ?? '') === 'completed';
                            $isLocked = !empty($locked[$itemId]);
                            $classes = trim('course-player-link ' . ($isCurrent ? 'is-current ' : '') . ($isCompleted ? 'is-completed ' : '') . ($isLocked ? 'is-locked' : ''));
                            $icon = $isLocked ? 'bi-lock-fill' : ($isCompleted ? 'bi-check-circle-fill text-success' : ($typeIcons[$item['type']] ?? 'bi-file-earmark-text'));
                            ?>
                            <?php if ($isLocked): ?>
                                <span class="<?php echo $classes; ?>" aria-label="Lekcja zablokowana">
                                    <i class="bi <?php echo $icon; ?>" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            <?php else: ?>
                                <a class="<?php echo $classes; ?>" href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo $itemId; ?>">
                                    <i class="bi <?php echo $icon; ?>" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <section class="course-player-main">
        <div class="course-document-card">
            <article class="course-player-lesson">
                
                <!-- Lesson Header Badge & Title -->
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge text-bg-primary px-3 py-2">
                        <i class="bi <?php echo $typeIcons[$activeItem['type']] ?? 'bi-file-earmark-text'; ?> me-1"></i>
                        <?php echo htmlspecialchars($typeLabels[$activeItem['type']] ?? 'Lekcja', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <h1 class="display-6 fw-bold mb-4"><?php echo htmlspecialchars((string)$activeItem['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

                <!-- Content Renderer -->
                <div class="course-lesson-body mb-5">
                    <?php if ($activeItem['type'] === 'text'): ?>
                        <?php echo courseRenderLessonContent((string)($activeItem['content'] ?? '')); ?>
                    <?php elseif ($activeItem['type'] === 'video'): ?>
                        <?php if ($videoEmbed): ?>
                            <div class="course-video-wrap mb-4">
                                <iframe src="<?php echo htmlspecialchars($videoEmbed, ENT_QUOTES, 'UTF-8'); ?>" title="Film: <?php echo htmlspecialchars((string)$activeItem['title'], ENT_QUOTES, 'UTF-8'); ?>" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">Autor nie podał jeszcze poprawnego adresu filmu.</div>
                        <?php endif; ?>
                    <?php elseif (in_array($activeItem['type'], ['quiz', 'exam'], true)): ?>
                        <?php if (!$questions): ?>
                            <div class="alert alert-warning">Ten <?php echo $activeItem['type'] === 'exam' ? 'egzamin' : 'quiz'; ?> nie zawiera jeszcze pytań.</div>
                        <?php else: ?>
                            <form id="courseQuizForm" action="ajax/course_progress.php" method="post">
                                <?php echo csrfTokenField('course_progress'); ?>
                                <input type="hidden" name="item_id" value="<?php echo $activeItemId; ?>">
                                <?php foreach ($questions as $number => $question): ?>
                                    <fieldset class="course-quiz-question mb-4" data-question-id="<?php echo (int)$question['id']; ?>">
                                        <legend class="h6 fw-bold mb-3"><?php echo ($number + 1) . '. ' . htmlspecialchars((string)$question['question_text'], ENT_QUOTES, 'UTF-8'); ?></legend>
                                        <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $column): ?>
                                            <?php if (!empty($question[$column])): ?>
                                                <label class="course-quiz-option">
                                                    <input type="radio" name="answers[<?php echo (int)$question['id']; ?>]" value="<?php echo $letter; ?>">
                                                    <span><strong class="me-1"><?php echo $letter; ?>.</strong><?php echo htmlspecialchars((string)$question[$column], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php endforeach; ?>
                                <button class="btn btn-primary btn-lg shadow" type="submit">
                                    <i class="bi bi-send-check me-2"></i> Sprawdź odpowiedzi
                                </button>
                                <div id="courseQuizResult" hidden role="status" class="mt-3"></div>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($activeItem['type'] === 'lab'): ?>
                        <?php if ($labInstructions !== ''): ?>
                            <div class="course-callout course-callout-info mb-4">
                                <i class="bi bi-info-circle-fill fs-4" aria-hidden="true"></i>
                                <div>
                                    <h3>Instrukcja zadania</h3>
                                    <div><?php echo nl2br(htmlspecialchars($labInstructions, ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($labBlocked): ?>
                            <div class="alert alert-warning">To laboratorium nie jest dostępne dla Twojej roli.</div>
                        <?php elseif ($labTool !== ''): ?>
                            <iframe class="course-lab-frame" src="sandbox/index.php?tool=<?php echo rawurlencode($labTool); ?>&embed=1" title="Laboratorium: <?php echo htmlspecialchars((string)$activeItem['title'], ENT_QUOTES, 'UTF-8'); ?>" sandbox="allow-scripts allow-forms allow-same-origin"></iframe>
                        <?php else: ?>
                            <div class="alert alert-warning">Autor nie skonfigurował jeszcze narzędzia laboratorium.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Student Private Notes Drawer -->
                <div class="card border-0 bg-body-tertiary rounded-3 p-3 my-4 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold small text-muted text-uppercase"><i class="bi bi-journal-text me-1 text-primary"></i> Twoje prywatne notatki do tej lekcji</span>
                        <span class="small text-muted" id="noteSaveStatus">Wpisz tekst (zapis automatyczny)</span>
                    </div>
                    <textarea class="form-control bg-body border-0 shadow-inner small" id="studentCourseNoteArea" rows="3" placeholder="Zanotuj kluczowe komendy, adresy IP lub własne spostrzeżenia..."></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="badge bg-secondary-subtle text-secondary small"><i class="bi bi-keyboard me-1"></i> Skróty: [J] Poprzednia | [K] Następna</span>
                        <span class="small text-muted">Dostępne tylko dla Ciebie</span>
                    </div>
                </div>

                <!-- Bottom Completion Controls & Navigation -->
                <div class="player-footer-controls d-flex flex-wrap align-items-center justify-content-between gap-3 pt-4 border-top">
                    <div>
                        <?php if (!in_array($activeItem['type'], ['quiz', 'exam'], true) && !$labBlocked): ?>
                            <form id="completeLessonForm" action="ajax/course_progress.php" method="post" class="m-0">
                                <?php echo csrfTokenField('course_progress'); ?>
                                <input type="hidden" name="action" value="mark_completed">
                                <input type="hidden" name="item_id" value="<?php echo $activeItemId; ?>">

                                <?php if (($activeProgress['status'] ?? '') === 'completed'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge text-bg-success px-3 py-2 fs-6">
                                            <i class="bi bi-check-circle-fill me-1"></i> Ukończono tę lekcję
                                        </span>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            Zapisz ponownie
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-complete-lesson btn-lg shadow" type="submit">
                                        <i class="bi bi-check2-circle me-2"></i> Oznacz jako ukończoną
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <?php if ($prevItem): ?>
                            <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo (int)$prevItem['id']; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Poprzednia
                            </a>
                        <?php endif; ?>
                        <?php if ($nextItem): ?>
                            <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo (int)$nextItem['id']; ?>" class="btn btn-primary">
                                Następna <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </article>
        </div>
    </section>
</main>

<script src="<?php echo htmlspecialchars(assetUrl('assets/js/course-player.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
