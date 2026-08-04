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

$courseId = (int)($_GET['id'] ?? 0);
if ($courseId <= 0) {
    redirect('manage_courses.php');
}

$course = courseFetchById($pdo, $courseId);
if (!$course) {
    setSessionMessage('error', 'Kurs nie istnieje.');
    redirect('manage_courses.php');
}

$modStmt = $pdo->prepare("SELECT id, course_id, title, description, sort_order, created_at FROM course_modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$modStmt->execute([$courseId]);
$modules = $modStmt->fetchAll(PDO::FETCH_ASSOC);

$itemsByModule = [];
if (!empty($modules)) {
    $modIds = array_column($modules, 'id');
    $placeholders = implode(',', array_fill(0, count($modIds), '?'));
    $itemStmt = $pdo->prepare("SELECT id, module_id, title, type, content, video_url, quiz_passing_score, lab_source, lab_tool_key, lab_custom_id, lab_instructions, sort_order, created_at FROM course_items WHERE module_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
    $itemStmt->execute($modIds);
    foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $itemsByModule[$it['module_id']][] = $it;
    }
}
foreach ($modules as &$mod) {
    $mod['items'] = $itemsByModule[$mod['id']] ?? [];
}
unset($mod);

$activeItemId = (int)($_GET['item_id'] ?? 0);
$activeModuleId = (int)($_GET['module_id'] ?? 0);
$activeItem = null;
$activeModule = null;

if ($activeItemId > 0) {
    foreach ($modules as $mod) {
        foreach ($mod['items'] as $it) {
            if ((int)$it['id'] === $activeItemId) {
                $activeItem = $it;
                $activeModule = $mod;
                $activeModuleId = (int)$mod['id'];
                break 2;
            }
        }
    }
}

$pageTitle = 'Edytor programu: ' . htmlspecialchars($course['title']) . ' — ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/courses.css'];
$base_url = '../';
include '../includes/header.php';
$csrfToken = generateCsrfToken('course_admin');
?>

<style>
.builder-layout {
    display: flex;
    flex-direction: row;
    min-height: calc(100vh - 120px);
    background-color: var(--body-bg);
    position: relative;
    z-index: 1;
}
.builder-sidebar {
    width: 340px;
    min-width: 340px;
    border-right: 1px solid var(--border-color);
    background-color: var(--panel-bg);
    display: flex;
    flex-direction: column;
    height: auto;
    min-height: calc(100vh - 120px);
}
.builder-sidebar-header {
    padding: 1.25rem;
    border-bottom: 1px solid var(--border-color);
}
.builder-sidebar-body {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}
.builder-sidebar-footer {
    padding: 1rem;
    border-top: 1px solid var(--border-color);
    background-color: var(--panel-bg);
}
.builder-content {
    flex: 1;
    padding: 2rem;
    min-height: 500px;
    max-width: 100%;
}
.main-footer {
    position: relative;
    z-index: 10;
    clear: both;
    margin-top: 2rem;
}
.module-card {
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    background: var(--panel-bg);
    overflow: hidden;
}
.module-header {
    background: color-mix(in srgb, var(--panel-bg) 95%, var(--primary-color));
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    user-select: none;
}
.module-header:hover {
    background: color-mix(in srgb, var(--panel-bg) 90%, var(--primary-color));
}
.lesson-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.lesson-item {
    border-bottom: 1px solid var(--border-color);
    transition: background-color .15s ease;
}
.lesson-item:last-child {
    border-bottom: none;
}
.lesson-item:hover {
    background-color: color-mix(in srgb, var(--primary-color) 8%, transparent);
}
.lesson-item.active {
    background-color: color-mix(in srgb, var(--primary-color) 14%, transparent);
    border-left: 3px solid var(--primary-color);
}
.lesson-item.active a {
    font-weight: 600;
    color: var(--primary-color) !important;
}
@media (max-width: 991.98px) {
    .builder-layout {
        flex-direction: column;
    }
    .builder-sidebar {
        width: 100%;
        min-width: 100%;
        height: auto;
        min-height: auto;
        position: static;
        border-right: none;
        border-bottom: 1px solid var(--border-color);
    }
}
</style>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main class="content-body p-0" id="main-content">
            <div class="builder-layout" data-course-builder data-course-id="<?php echo $courseId; ?>" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                
                <!-- Left Sidebar: Modules & Items Tree -->
                <aside class="builder-sidebar">
                    <div class="builder-sidebar-header">
                        <a href="manage_courses.php" class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="bi bi-arrow-left me-1"></i> Wróć do kursów
                        </a>
                        <h1 class="h6 fw-bold mb-0 text-truncate" title="<?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h1>
                        <span class="badge text-bg-<?php echo $course['status'] === 'active' ? 'success' : 'secondary'; ?> mt-1">
                            <?php echo $course['status'] === 'active' ? 'Opublikowany' : 'Szkic'; ?>
                        </span>
                        <?php $isExtCourse = (int)($course['is_external'] ?? 0) === 1; ?>
                        <?php if ($isExtCourse): ?>
                            <span class="badge text-bg-warning text-dark ms-1 mt-1">Kurs Zewnętrzny</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($isExtCourse): ?>
                        <div class="p-3 bg-warning bg-opacity-10 border-bottom border-warning text-dark small">
                            <i class="bi bi-info-circle-fill text-warning me-1"></i>
                            <strong>Kurs Zewnętrzny:</strong> Treści dydaktyczne znajdują się na zewnętrznym serwisie. W tym edytorze możesz zdefiniować program kursu (tematy i moduły) oraz dodać opcjonalny Egzamin końcowy ZSEM TECH.
                        </div>
                    <?php endif; ?>

                    <div class="builder-sidebar-body">
                        <div id="modulesAccordion">
                            <?php if (empty($modules)): ?>
                                <div class="p-4 text-center text-muted small border rounded-3 bg-light">
                                    <i class="bi bi-folder-plus fs-2 d-block mb-2 text-primary opacity-75"></i>
                                    Kurs nie posiada jeszcze żadnych modułów.<br>Kliknij poniższy przycisk, aby stworzyć pierwszy moduł.
                                </div>
                            <?php endif; ?>

                            <?php foreach ($modules as $mIdx => $mod): ?>
                                <?php
                                $isOpen = ($activeModuleId === (int)$mod['id']) || (count($modules) === 1) || ($activeItemId === 0 && $mIdx === 0);
                                ?>
                                <div class="module-card mb-3" data-module-id="<?php echo (int)$mod['id']; ?>">
                                    <div class="module-header d-flex align-items-center justify-content-between p-2 px-3" data-bs-toggle="collapse" data-bs-target="#collapseMod<?php echo (int)$mod['id']; ?>">
                                        <div class="text-truncate me-2 fw-semibold flex-grow-1 d-flex align-items-center gap-2">
                                            <i class="bi bi-folder2-open text-primary"></i>
                                            <span><?php echo htmlspecialchars($mod['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="badge text-bg-light border text-muted ms-1" style="font-size: 0.7rem;"><?php echo count($mod['items']); ?> lekcji</span>
                                        </div>
                                        <div class="btn-group btn-group-sm flex-shrink-0" onclick="event.stopPropagation();">
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1" data-action="move-module" data-id="<?php echo (int)$mod['id']; ?>" data-dir="-1" title="Przesuń wyżej" <?php echo $mIdx === 0 ? 'disabled' : ''; ?>><i class="bi bi-chevron-up"></i></button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1" data-action="move-module" data-id="<?php echo (int)$mod['id']; ?>" data-dir="1" title="Przesuń niżej" <?php echo $mIdx === count($modules) - 1 ? 'disabled' : ''; ?>><i class="bi bi-chevron-down"></i></button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm p-1" data-action="edit-module-trigger" data-id="<?php echo (int)$mod['id']; ?>" data-title="<?php echo htmlspecialchars($mod['title'], ENT_QUOTES, 'UTF-8'); ?>" data-desc="<?php echo htmlspecialchars($mod['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" title="Edytuj moduł"><i class="bi bi-pencil"></i></button>
                                            <button type="button" class="btn btn-outline-danger btn-sm p-1" data-action="delete-module" data-id="<?php echo (int)$mod['id']; ?>" title="Usuń moduł"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>

                                    <div id="collapseMod<?php echo (int)$mod['id']; ?>" class="collapse <?php echo $isOpen ? 'show' : ''; ?>">
                                        <ul class="lesson-list border-top mb-0">
                                            <?php if (empty($mod['items'])): ?>
                                                <li class="text-muted p-3 text-center small">Brak lekcji w tym module.</li>
                                            <?php endif; ?>
                                            <?php foreach ($mod['items'] as $iIdx => $it): ?>
                                                <?php
                                                $icon = 'bi-file-earmark-text';
                                                if ($it['type'] === 'video') $icon = 'bi-play-circle';
                                                if ($it['type'] === 'quiz') $icon = 'bi-question-circle';
                                                if ($it['type'] === 'exam') $icon = 'bi-award';
                                                if ($it['type'] === 'lab') $icon = 'bi-terminal';
                                                ?>
                                                <li class="lesson-item d-flex align-items-center justify-content-between p-2 px-3 <?php echo ($activeItemId === (int)$it['id']) ? 'active' : ''; ?>" data-item-id="<?php echo (int)$it['id']; ?>">
                                                    <a href="?id=<?php echo $courseId; ?>&item_id=<?php echo (int)$it['id']; ?>" class="text-decoration-none text-reset text-truncate flex-grow-1 me-2 small">
                                                        <i class="bi <?php echo $icon; ?> me-1 text-muted"></i>
                                                        <?php echo htmlspecialchars($it['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                    <div class="lesson-actions btn-group btn-group-sm flex-shrink-0" onclick="event.stopPropagation();">
                                                        <button type="button" class="btn btn-link text-secondary p-0 px-1" data-action="move-item" data-id="<?php echo (int)$it['id']; ?>" data-dir="-1" title="Przesuń wyżej" <?php echo $iIdx === 0 ? 'disabled' : ''; ?>><i class="bi bi-chevron-up"></i></button>
                                                        <button type="button" class="btn btn-link text-secondary p-0 px-1" data-action="move-item" data-id="<?php echo (int)$it['id']; ?>" data-dir="1" title="Przesuń niżej" <?php echo $iIdx === count($mod['items']) - 1 ? 'disabled' : ''; ?>><i class="bi bi-chevron-down"></i></button>
                                                        <button type="button" class="btn btn-link text-danger p-0 px-1" data-action="delete-item" data-id="<?php echo (int)$it['id']; ?>" title="Usuń lekcję"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="p-2 bg-light border-top text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100" data-action="add-item-trigger" data-module-id="<?php echo (int)$mod['id']; ?>">
                                                <i class="bi bi-plus-lg me-1"></i> Dodaj lekcję
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="builder-sidebar-footer p-3 border-top">
                        <button type="button" class="btn btn-primary w-100 shadow-sm" data-action="add-module-trigger">
                            <i class="bi bi-folder-plus me-1"></i> Nowy moduł
                        </button>
                    </div>
                </aside>

                <!-- Right Workspace: Content Editor -->
                <main class="builder-content p-4 flex-grow-1 overflow-auto">
                    <?php if (!$activeItem): ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted text-center py-5">
                            <i class="bi bi-journal-text display-1 mb-3 opacity-50"></i>
                            <h2 class="h4 fw-bold">Wybierz lekcję do edycji</h2>
                            <p class="small max-w-md">Kliknij na dowolną lekcję w panelu bocznym lub użyj przycisku „Dodaj lekcję” w module, aby utworzyć nowe materiały.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom">
                            <div>
                                <span class="badge text-bg-light border text-uppercase mb-1">
                                    Moduł: <?php echo htmlspecialchars($activeModule['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <h2 class="h4 fw-bold mb-0">
                                    Edycja: <?php echo htmlspecialchars($activeItem['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </h2>
                            </div>
                            <span class="badge text-bg-primary px-3 py-2 text-uppercase">
                                Typ: <?php echo htmlspecialchars($activeItem['type'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <form id="courseItemEditorForm" class="mb-4">
                            <input type="hidden" name="action" value="edit_item">
                            <input type="hidden" name="item_id" value="<?php echo (int)$activeItem['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="dashboard-panel p-4 mb-4">
                                <div class="mb-3">
                                    <label class="form-label fw-bold" for="itemTitle">Tytuł lekcji *</label>
                                    <input type="text" class="form-control" id="itemTitle" name="title" value="<?php echo htmlspecialchars($activeItem['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>

                                <?php if ($activeItem['type'] === 'video'): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" for="videoUrl">Adres wideo YouTube</label>
                                        <input type="url" class="form-control" id="videoUrl" name="video_url" value="<?php echo htmlspecialchars($activeItem['video_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://www.youtube.com/watch?v=...">
                                        <div class="form-text">Dozwolone wyłącznie linki z youtube.com lub youtu.be.</div>
                                    </div>

                                <?php elseif ($activeItem['type'] === 'lab'): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" for="labToolKey">Narzędzie interaktywne</label>
                                        <select class="form-select" id="labToolKey" name="lab_tool_key">
                                            <?php foreach (COURSE_LAB_TOOLS as $tool): ?>
                                                <option value="<?php echo htmlspecialchars($tool, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($activeItem['lab_tool_key'] ?? '') === $tool ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(ucfirst($tool), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" for="labInstructions">Instrukcja dla ucznia</label>
                                        <textarea class="form-control" id="labInstructions" name="lab_instructions" rows="5"><?php echo htmlspecialchars($activeItem['lab_instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>

                                <?php elseif (in_array($activeItem['type'], ['quiz', 'exam'], true)): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" for="quizPassingScore">Wymagany próg zaliczenia (%)</label>
                                        <input type="number" class="form-control" id="quizPassingScore" name="quiz_passing_score" value="<?php echo (int)($activeItem['quiz_passing_score'] ?? 70); ?>" min="1" max="100">
                                    </div>

                                <?php elseif ($activeItem['type'] === 'text'): ?>
                                    <input type="hidden" id="courseLessonDocument" name="content_document">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold mb-2">Zawartość lekcji (Bloki)</label>
                                        <div id="courseBlockEditor" class="d-flex flex-column gap-3 mb-3"></div>
                                        <button type="button" class="btn btn-outline-primary" id="addCourseBlock">
                                            <i class="bi bi-plus-lg me-1"></i> Dodaj blok treści
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-end pt-3 border-top">
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="bi bi-check-lg me-1"></i> Zapisz lekcję
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if (in_array($activeItem['type'], ['quiz', 'exam'], true)): ?>
                            <div class="dashboard-panel p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3 class="h5 fw-bold mb-0">
                                        <i class="bi bi-question-square me-2 text-primary"></i>Pytania testowe
                                    </h3>
                                    <button type="button" class="btn btn-sm btn-primary" data-action="add-question-trigger">
                                        <i class="bi bi-plus-lg me-1"></i> Dodaj pytanie
                                    </button>
                                </div>
                                <div id="questionsContainer" class="d-flex flex-column gap-2">
                                    <div class="text-center text-muted py-4">Ładowanie pytań...</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </main>

            </div>
        </main>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="moduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="moduleForm">
            <input type="hidden" name="action" id="moduleAction" value="add_module">
            <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
            <input type="hidden" name="module_id" id="moduleId" value="0">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="moduleModalTitle">Dodaj moduł</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="moduleTitle">Nazwa modułu *</label>
                    <input type="text" class="form-control" name="title" id="moduleTitle" required maxlength="160">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="moduleDescription">Opis modułu (opcjonalnie)</label>
                    <textarea class="form-control" name="description" id="moduleDescription" rows="3" maxlength="5000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="submit" class="btn btn-primary">Zapisz</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="itemForm">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="module_id" id="itemModuleId" value="0">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><?php echo $isExtCourse ? 'Nowy Egzamin końcowy' : 'Nowa lekcja'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="newItemTitle">Tytuł *</label>
                    <input type="text" class="form-control" name="title" id="newItemTitle" required maxlength="160" placeholder="<?php echo $isExtCourse ? 'np. Egzamin końcowy ZSEM TECH' : 'np. Wprowadzenie do tematu'; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="newItemType">Typ zawartości *</label>
                    <select name="type" class="form-select" id="newItemType">
                        <?php if ($isExtCourse): ?>
                            <option value="exam" selected>Egzamin końcowy ZSEM TECH</option>
                        <?php else: ?>
                            <option value="text">Lekcja blokowa (Tekst, Obrazy, Kod)</option>
                            <option value="video">Wideo YouTube</option>
                            <option value="quiz">Quiz sprawdzający</option>
                            <option value="exam">Egzamin końcowy</option>
                            <option value="lab">Laboratorium (Sandbox)</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($isExtCourse): ?>
                        <div class="form-text text-warning small mt-1">
                            <i class="bi bi-info-circle me-1"></i>W kursie zewnętrznym lekcje odbywają się poza platformą. Dostępny jest wyłącznie opcjonalny egzamin końcowy.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="submit" class="btn btn-primary"><?php echo $isExtCourse ? 'Utwórz egzamin' : 'Utwórz lekcję'; ?></button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="questionForm">
            <input type="hidden" name="action" id="questionAction" value="add_question">
            <input type="hidden" name="item_id" value="<?php echo $activeItemId; ?>">
            <input type="hidden" name="question_id" id="questionId" value="0">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="questionModalTitle">Dodaj pytanie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="qText">Treść pytania *</label>
                    <textarea class="form-control" name="question_text" id="qText" rows="3" required></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qA">Odpowiedź A *</label>
                        <input type="text" class="form-control" name="option_a" id="qA" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qB">Odpowiedź B *</label>
                        <input type="text" class="form-control" name="option_b" id="qB" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qC">Odpowiedź C (opcjonalnie)</label>
                        <input type="text" class="form-control" name="option_c" id="qC">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qD">Odpowiedź D (opcjonalnie)</label>
                        <input type="text" class="form-control" name="option_d" id="qD">
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qCorrect">Poprawna odpowiedź *</label>
                        <select class="form-select" name="correct_answer" id="qCorrect" required>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="qExp">Wyjaśnienie (opcjonalnie)</label>
                        <input type="text" class="form-control" name="explanation" id="qExp" placeholder="Wyjaśnienie po rozwiązaniu testu">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="submit" class="btn btn-primary">Zapisz pytanie</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
(() => {
    'use strict';

    const builderRoot = document.querySelector('[data-course-builder]');
    if (!builderRoot) return;

    const courseId = builderRoot.dataset.courseId;
    const csrfToken = builderRoot.dataset.csrfToken;

    const notice = (msg, type = 'danger') => {
        if (typeof window.appNotice === 'function') window.appNotice(msg, type);
        else window['alert'](msg);
    };

    const apiRequest = async (formData, asGet = false) => {
        let url = '../ajax/admin_courses.php';
        let opts = { credentials: 'same-origin' };
        if (asGet) {
            const params = new URLSearchParams(formData);
            url += '?' + params.toString();
        } else {
            opts.method = 'POST';
            opts.body = formData;
        }
        const res = await fetch(url, opts);
        let payload;
        try { payload = await res.json(); } catch (_) { throw new Error('Błąd odkodowania JSON z serwera.'); }
        if (!res.ok || !payload.success) throw new Error(payload.message || 'Operacja nie powiodła się.');
        return payload;
    };

    // Lazy Modals getter
    const getModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(el);
        }
        return null;
    };

    // Question cache for editing
    let currentQuestions = [];

    // Global Event Delegation for buttons
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;

        if (action === 'add-module-trigger') {
            document.getElementById('moduleForm').reset();
            document.getElementById('moduleAction').value = 'add_module';
            document.getElementById('moduleId').value = '0';
            document.getElementById('moduleModalTitle').textContent = 'Nowy moduł';
            getModal('moduleModal')?.show();
        }

        if (action === 'edit-module-trigger') {
            document.getElementById('moduleForm').reset();
            document.getElementById('moduleAction').value = 'edit_module';
            document.getElementById('moduleId').value = btn.dataset.id;
            document.getElementById('moduleTitle').value = btn.dataset.title || '';
            document.getElementById('moduleDescription').value = btn.dataset.desc || '';
            document.getElementById('moduleModalTitle').textContent = 'Edytuj moduł';
            getModal('moduleModal')?.show();
        }

        if (action === 'add-item-trigger') {
            document.getElementById('itemForm').reset();
            document.getElementById('itemModuleId').value = btn.dataset.moduleId;
            getModal('itemModal')?.show();
        }

        if (action === 'add-question-trigger') {
            document.getElementById('questionForm').reset();
            document.getElementById('questionAction').value = 'add_question';
            document.getElementById('questionId').value = '0';
            document.getElementById('questionModalTitle').textContent = 'Dodaj pytanie';
            getModal('questionModal')?.show();
        }

        if (action === 'edit-question-trigger') {
            const qId = Number(btn.dataset.id);
            const q = currentQuestions.find(item => Number(item.id) === qId);
            if (!q) return;
            document.getElementById('questionAction').value = 'edit_question';
            document.getElementById('questionId').value = q.id;
            document.getElementById('qText').value = q.question_text || '';
            document.getElementById('qA').value = q.option_a || '';
            document.getElementById('qB').value = q.option_b || '';
            document.getElementById('qC').value = q.option_c || '';
            document.getElementById('qD').value = q.option_d || '';
            document.getElementById('qCorrect').value = q.correct_answer || 'A';
            document.getElementById('qExp').value = q.explanation || '';
            document.getElementById('questionModalTitle').textContent = 'Edytuj pytanie';
            getModal('questionModal')?.show();
        }

        if (action === 'delete-module' || action === 'delete-item' || action === 'delete-question') {
            e.preventDefault();
            const msg = action === 'delete-module' ? 'Usunąć moduł i wszystkie jego lekcje?' : (action === 'delete-item' ? 'Usunąć tę lekcję?' : 'Usunąć pytanie?');
            if (!window['confirm'](msg)) return;

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            if (action === 'delete-module') {
                fd.append('action', 'delete_module');
                fd.append('module_id', btn.dataset.id);
            } else if (action === 'delete-item') {
                fd.append('action', 'delete_item');
                fd.append('item_id', btn.dataset.id);
            } else {
                fd.append('action', 'delete_question');
                fd.append('question_id', btn.dataset.id);
            }

            try {
                await apiRequest(fd);
                if (action === 'delete-question') {
                    loadQuestions();
                } else {
                    location.href = `course_builder.php?id=${courseId}`;
                }
            } catch (err) { notice(err.message); }
        }

        if (action === 'move-module') {
            e.preventDefault();
            const allMods = [...document.querySelectorAll('[data-module-id]')].map(el => Number(el.dataset.moduleId));
            const id = Number(btn.dataset.id);
            const idx = allMods.indexOf(id);
            const target = idx + Number(btn.dataset.dir);
            if (idx === -1 || target < 0 || target >= allMods.length) return;
            [allMods[idx], allMods[target]] = [allMods[target], allMods[idx]];

            const fd = new FormData();
            fd.append('action', 'reorder_modules');
            fd.append('course_id', courseId);
            fd.append('order', JSON.stringify(allMods));
            fd.append('csrf_token', csrfToken);
            try {
                await apiRequest(fd);
                location.reload();
            } catch (err) { notice(err.message); }
        }

        if (action === 'move-item') {
            e.preventDefault();
            const modCard = btn.closest('[data-module-id]');
            if (!modCard) return;
            const allItems = [...modCard.querySelectorAll('[data-item-id]')].map(el => Number(el.dataset.itemId));
            const id = Number(btn.dataset.id);
            const idx = allItems.indexOf(id);
            const target = idx + Number(btn.dataset.dir);
            if (idx === -1 || target < 0 || target >= allItems.length) return;
            [allItems[idx], allItems[target]] = [allItems[target], allItems[idx]];

            const fd = new FormData();
            fd.append('action', 'reorder_items');
            fd.append('module_id', modCard.dataset.moduleId);
            fd.append('order', JSON.stringify(allItems));
            fd.append('csrf_token', csrfToken);
            try {
                await apiRequest(fd);
                location.reload();
            } catch (err) { notice(err.message); }
        }
    });

    // Forms handling
    document.getElementById('moduleForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await apiRequest(new FormData(e.target));
            if (res.module_id) {
                location.href = `course_builder.php?id=${courseId}&module_id=${res.module_id}`;
            } else {
                location.reload();
            }
        } catch (err) { notice(err.message); }
    });

    document.getElementById('itemForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await apiRequest(new FormData(e.target));
            if (res.item_id) {
                location.href = `course_builder.php?id=${courseId}&item_id=${res.item_id}`;
            } else {
                location.reload();
            }
        } catch (err) { notice(err.message); }
    });

    document.getElementById('questionForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await apiRequest(new FormData(e.target));
            getModal('questionModal')?.hide();
            loadQuestions();
        } catch (err) { notice(err.message); }
    });

    // Quiz Questions loading
    const activeItemId = <?php echo $activeItemId; ?>;
    const isQuiz = <?php echo (isset($activeItem) && in_array($activeItem['type'], ['quiz', 'exam'], true)) ? 'true' : 'false'; ?>;

    const loadQuestions = async () => {
        if (!isQuiz || !activeItemId) return;
        const container = document.getElementById('questionsContainer');
        try {
            const fd = new FormData();
            fd.append('action', 'get_questions');
            fd.append('item_id', activeItemId);
            const res = await apiRequest(fd, true);
            currentQuestions = res.questions || [];
            if (!currentQuestions.length) {
                container.innerHTML = '<div class="text-muted text-center py-3">Brak pytań w tym quizie. Dodaj pierwsze pytanie.</div>';
                return;
            }
            container.innerHTML = currentQuestions.map((q, i) => `
                <div class="card border mb-2">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fw-bold me-2">${i + 1}.</span>
                            <span>${escapeHtml(q.question_text)}</span>
                            <div class="small text-muted mt-1">Poprawna: <strong class="text-success">${q.correct_answer}</strong></div>
                        </div>
                        <div class="btn-group btn-group-sm flex-shrink-0 ms-2">
                            <button type="button" class="btn btn-outline-secondary" data-action="edit-question-trigger" data-id="${q.id}"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-danger" data-action="delete-question" data-id="${q.id}"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch (err) {
            container.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(err.message)}</div>`;
        }
    };

    const escapeHtml = (str) => {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    if (isQuiz) loadQuestions();

    // Block Document Editor
    const isText = <?php echo (isset($activeItem) && $activeItem['type'] === 'text') ? 'true' : 'false'; ?>;
    let blockState = null;

    if (isText) {
        let initialDoc = null;
        try {
            initialDoc = <?php
            if (isset($activeItem) && $activeItem['type'] === 'text') {
                $decoded = courseDecodeLessonDocument($activeItem['content'] ?? '');
                echo $decoded ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null';
            } else {
                echo 'null';
            }
            ?>;
        } catch (_) {}

        blockState = { document: initialDoc || { version: 2, blocks: [{ type: 'text', heading: '', body: '' }] } };
        if (!Array.isArray(blockState.document.blocks)) blockState.document.blocks = [{ type: 'text', heading: '', body: '' }];

        const editorRoot = document.getElementById('courseBlockEditor');
        const types = ['text', 'callout', 'code', 'checklist', 'image', 'divider'];
        const typeLabels = { text: 'Tekst', callout: 'Wyróżnienie', code: 'Kod', checklist: 'Lista kontrolna', image: 'Obraz', divider: 'Linia (Separator)' };

        const defaultBlock = (type) => {
            if (type === 'callout') return { type, tone: 'info', title: '', body: '' };
            if (type === 'code') return { type, language: '', code: '' };
            if (type === 'checklist') return { type, title: '', items: [''] };
            if (type === 'image') return { type, src: '', alt: '', caption: '' };
            if (type === 'divider') return { type };
            return { type: 'text', heading: '', body: '' };
        };

        const addField = (container, label, value, onChange, opts = {}) => {
            const wrap = document.createElement('div');
            wrap.className = 'mb-2';
            const lbl = document.createElement('label');
            lbl.className = 'form-label small fw-semibold mb-1';
            lbl.textContent = label;
            const input = opts.textarea ? document.createElement('textarea') : document.createElement('input');
            input.className = 'form-control';
            input.value = value || '';
            if (opts.textarea) input.rows = opts.rows || 4;
            else input.type = opts.type || 'text';
            if (opts.placeholder) input.placeholder = opts.placeholder;
            input.addEventListener('input', () => onChange(input.value));
            wrap.appendChild(lbl);
            wrap.appendChild(input);
            container.appendChild(wrap);
        };

        const renderBlocks = () => {
            if (!editorRoot) return;
            editorRoot.replaceChildren();

            blockState.document.blocks.forEach((block, index) => {
                const card = document.createElement('div');
                card.className = 'card border shadow-sm';

                const header = document.createElement('div');
                header.className = 'card-header bg-light d-flex align-items-center justify-content-between p-2';

                const sel = document.createElement('select');
                sel.className = 'form-select form-select-sm w-auto';
                types.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t; opt.textContent = typeLabels[t]; opt.selected = block.type === t;
                    sel.appendChild(opt);
                });
                sel.addEventListener('change', () => {
                    blockState.document.blocks[index] = defaultBlock(sel.value);
                    renderBlocks();
                });
                header.appendChild(sel);

                const btns = document.createElement('div');
                btns.className = 'btn-group btn-group-sm';

                const upBtn = document.createElement('button'); upBtn.type = 'button'; upBtn.className = 'btn btn-outline-secondary'; upBtn.innerHTML = '<i class="bi bi-arrow-up"></i>';
                upBtn.disabled = index === 0;
                upBtn.addEventListener('click', () => {
                    [blockState.document.blocks[index], blockState.document.blocks[index - 1]] = [blockState.document.blocks[index - 1], blockState.document.blocks[index]];
                    renderBlocks();
                });

                const dnBtn = document.createElement('button'); dnBtn.type = 'button'; dnBtn.className = 'btn btn-outline-secondary'; dnBtn.innerHTML = '<i class="bi bi-arrow-down"></i>';
                dnBtn.disabled = index === blockState.document.blocks.length - 1;
                dnBtn.addEventListener('click', () => {
                    [blockState.document.blocks[index], blockState.document.blocks[index + 1]] = [blockState.document.blocks[index + 1], blockState.document.blocks[index]];
                    renderBlocks();
                });

                const delBtn = document.createElement('button'); delBtn.type = 'button'; delBtn.className = 'btn btn-outline-danger'; delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                delBtn.addEventListener('click', () => {
                    blockState.document.blocks.splice(index, 1);
                    if (!blockState.document.blocks.length) blockState.document.blocks.push(defaultBlock('text'));
                    renderBlocks();
                });

                btns.appendChild(upBtn);
                btns.appendChild(dnBtn);
                btns.appendChild(delBtn);
                header.appendChild(btns);
                card.appendChild(header);

                const body = document.createElement('div');
                body.className = 'card-body p-3';

                if (block.type === 'text') {
                    addField(body, 'Nagłówek (opcjonalnie)', block.heading, v => block.heading = v);
                    addField(body, 'Treść akapitu', block.body, v => block.body = v, { textarea: true, rows: 5 });
                } else if (block.type === 'callout') {
                    const tWrap = document.createElement('div'); tWrap.className = 'mb-2';
                    const tLbl = document.createElement('label'); tLbl.className = 'form-label small fw-semibold mb-1'; tLbl.textContent = 'Typ wyróżnienia';
                    const tSel = document.createElement('select'); tSel.className = 'form-select mb-2';
                    [['info', 'Informacyjny (niebieski)'], ['success', 'Wskazówka (zielony)'], ['warning', 'Ostrzeżenie (żółty)']].forEach(([v, l]) => {
                        const opt = document.createElement('option'); opt.value = v; opt.textContent = l; opt.selected = block.tone === v;
                        tSel.appendChild(opt);
                    });
                    tSel.addEventListener('change', () => block.tone = tSel.value);
                    tWrap.appendChild(tLbl); tWrap.appendChild(tSel); body.appendChild(tWrap);
                    addField(body, 'Tytuł ramki', block.title, v => block.title = v);
                    addField(body, 'Treść', block.body, v => block.body = v, { textarea: true, rows: 3 });
                } else if (block.type === 'code') {
                    addField(body, 'Język (np. PHP, JS, Python)', block.language, v => block.language = v);
                    addField(body, 'Kod źródłowy', block.code, v => block.code = v, { textarea: true, rows: 6 });
                } else if (block.type === 'checklist') {
                    addField(body, 'Tytuł listy (opcjonalnie)', block.title, v => block.title = v);
                    const itemsDiv = document.createElement('div');
                    itemsDiv.className = 'd-flex flex-column gap-2 mb-2';
                    const items = Array.isArray(block.items) ? block.items : (block.items = ['']);
                    items.forEach((item, itemIdx) => {
                        const row = document.createElement('div'); row.className = 'input-group input-group-sm';
                        const inp = document.createElement('input'); inp.type = 'text'; inp.className = 'form-control'; inp.value = item;
                        inp.addEventListener('input', () => block.items[itemIdx] = inp.value);
                        const rm = document.createElement('button'); rm.type = 'button'; rm.className = 'btn btn-outline-danger'; rm.innerHTML = '<i class="bi bi-x-lg"></i>';
                        rm.addEventListener('click', () => { block.items.splice(itemIdx, 1); if (!block.items.length) block.items.push(''); renderBlocks(); });
                        row.appendChild(inp); row.appendChild(rm); itemsDiv.appendChild(row);
                    });
                    const addPt = document.createElement('button'); addPt.type = 'button'; addPt.className = 'btn btn-sm btn-outline-primary'; addPt.textContent = '+ Dodaj punkt';
                    addPt.addEventListener('click', () => { block.items.push(''); renderBlocks(); });
                    body.appendChild(itemsDiv); body.appendChild(addPt);
                } else if (block.type === 'image') {
                    addField(body, 'Ścieżka do obrazu (np. assets/images/foto.jpg)', block.src, v => block.src = v);
                    addField(body, 'Tekst alternatywny (ALT)', block.alt, v => block.alt = v);
                    addField(body, 'Podpis pod obrazem (opcjonalnie)', block.caption, v => block.caption = v);
                } else if (block.type === 'divider') {
                    const info = document.createElement('p'); info.className = 'text-muted small mb-0'; info.textContent = 'Na stronie pojawi się pozioma linia oddzielająca sekcje.';
                    body.appendChild(info);
                }

                card.appendChild(body);
                editorRoot.appendChild(card);
            });
        };

        document.getElementById('addCourseBlock')?.addEventListener('click', () => {
            blockState.document.blocks.push(defaultBlock('text'));
            renderBlocks();
        });

        renderBlocks();
    }

    // Item Form Submission
    document.getElementById('courseItemEditorForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const docInput = document.getElementById('courseLessonDocument');
        if (docInput && blockState && blockState.document) {
            docInput.value = JSON.stringify({ version: 2, blocks: blockState.document.blocks });
        }
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
            await apiRequest(new FormData(e.target));
            notice('Lekcja została zapisana.', 'success');
            setTimeout(() => location.reload(), 400);
        } catch (err) {
            notice(err.message);
            if (submitBtn) submitBtn.disabled = false;
        }
    });

})();
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
