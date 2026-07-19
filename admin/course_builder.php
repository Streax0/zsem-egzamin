<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dyrektor', 'teacher'], true)) {
    setSessionMessage('error', 'Brak uprawnień do zarządzania kursami.');
    redirect('../index.php');
}

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($courseId <= 0) {
    redirect('manage_courses.php');
}

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    setSessionMessage('error', 'Kurs nie istnieje.');
    redirect('manage_courses.php');
}

$modStmt = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$modStmt->execute([$courseId]);
$modules = $modStmt->fetchAll(PDO::FETCH_ASSOC);

$itemsByModule = [];
if (!empty($modules)) {
    $modIds = array_column($modules, 'id');
    $placeholders = implode(',', array_fill(0, count($modIds), '?'));
    $itemStmt = $pdo->prepare("SELECT * FROM course_items WHERE module_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
    $itemStmt->execute($modIds);
    $allCourseItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allCourseItems as $it) {
        $itemsByModule[$it['module_id']][] = $it;
    }
}

foreach ($modules as &$mod) {
    $mod['items'] = $itemsByModule[$mod['id']] ?? [];
}
unset($mod);

$activeItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$activeItem = null;
$activeModule = null;

if ($activeItemId > 0) {
    foreach ($modules as $mod) {
        foreach ($mod['items'] as $it) {
            if ((int)$it['id'] === $activeItemId) {
                $activeItem = $it;
                $activeModule = $mod;
                break 2;
            }
        }
    }
}

$pageTitle = 'Kreator: ' . htmlspecialchars($course['title']) . ' - ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
include '../includes/header.php';
?>

<style>
    /* Premium Builder Layout */
    .builder-layout {
        display: flex;
        flex-direction: row;
        min-height: calc(100vh - 65px);
        background-color: var(--body-bg);
        font-family: 'Inter', sans-serif;
    }
    .builder-sidebar {
        width: 320px;
        border-right: 1px solid var(--border-color);
        background-color: var(--panel-bg);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.02);
        z-index: 10;
    }
    .builder-content {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        background-color: var(--body-bg);
    }
    .builder-sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--panel-bg);
    }
    .builder-sidebar-body {
        overflow-y: auto;
        flex-grow: 1;
        padding: 0.5rem 0;
    }
    
    /* Accordion & Modules styling */
    .module-item {
        border-bottom: 1px solid var(--border-color);
        background: transparent !important;
    }
    .module-header {
        width: 100%;
        padding: 0.65rem 1rem;
        background: none;
        border: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .accordion-button {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--text-main) !important;
        padding-right: 0.5rem;
    }
    .accordion-button:not(.collapsed) {
        background-color: transparent !important;
        color: var(--primary-color) !important;
        box-shadow: none !important;
    }
    .accordion-button::after {
        width: 1rem;
        height: 1rem;
        background-size: 1rem;
    }
    
    /* Lesson Links */
    .lesson-list {
        list-style: none;
        padding: 0 0.5rem;
        margin: 0;
    }
    .lesson-list li {
        margin: 4px 0;
    }
    .lesson-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 0.85rem 0.6rem 1.5rem;
        text-decoration: none;
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 500;
        border-radius: 10px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .lesson-link:hover {
        background-color: rgba(0, 0, 0, 0.03);
        color: var(--primary-color);
    }
    .lesson-link.active {
        background-color: var(--primary-color) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(var(--primary-color-rgb, 59, 130, 246), 0.25);
    }
    .lesson-link.active .lesson-icon,
    .lesson-link.active span,
    .lesson-link.active .btn {
        color: #ffffff !important;
    }
    
    .lesson-meta {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }
    .lesson-icon {
        font-size: 1.1rem;
        color: var(--kolor-tekst-jasny);
        transition: color 0.2s;
    }
    .action-icons {
        display: flex;
        gap: 0.3rem;
    }
    .action-icons .btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.82rem;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background-color: var(--panel-bg);
        color: var(--text-main);
        transition: all 0.15s;
    }
    .action-icons .btn:hover {
        background-color: var(--primary-color);
        color: white !important;
        border-color: var(--primary-color);
    }
    
    /* GrapesJS - Total Modern Integration */

    .gjs-one-bg { background-color: var(--panel-bg) !important; }
    .gjs-two-color { color: var(--text-main) !important; }
    .gjs-three-bg { background-color: var(--primary-color) !important; color: white !important; }
    .gjs-four-color, .gjs-four-color-h:hover { color: var(--primary-color) !important; }
    
    .gjs-pn-panel { 
        background-color: var(--panel-bg) !important; 
        border-color: var(--border-color) !important; 
        box-shadow: none !important; 
    }
    .gjs-pn-views-container { border-left: 1px solid var(--border-color) !important; }
    .gjs-pn-options { border-bottom: 1px solid var(--border-color) !important; }
    
    /* Modernizaja przycisków bloków (narzędzi) */
    .gjs-block {
        border-radius: 12px !important;
        border: 1px solid var(--border-color) !important;
        background: var(--panel-bg) !important;
        color: var(--text-main) !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02) !important;
        padding: 18px 12px !important;
        font-weight: 600 !important;
        font-family: 'Inter', sans-serif !important;
    }
    .gjs-block:hover {
        border-color: var(--primary-color) !important;
        color: var(--primary-color) !important;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05) !important;
        transform: translateY(-3px) !important;
    }
    .gjs-block-label { 
        font-weight: 600 !important; 
        margin-top: 8px !important; 
        font-size: 0.82rem !important; 
    }
    .gjs-block svg {
        width: 28px;
        height: 28px;
        color: var(--primary-color);
        transition: transform 0.2s;
    }
    .gjs-block:hover svg {
        transform: scale(1.1);
    }
    
    /* Zaznaczenia (Highlighters) w kolorze przewodnim */
    .gjs-highlighter {
        border: 2px solid var(--primary-color) !important;
    }
    .gjs-badge {
        background-color: var(--primary-color) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        border-radius: 6px !important;
        padding: 2px 6px !important;
    }
    .gjs-cv-canvas .gjs-highlight {
        outline: 2px solid var(--primary-color) !important;
    }
    .gjs-active {
        outline: 2px dashed var(--primary-color) !important;
    }
    
    /* Top Header Bar */
    .content-area {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
    }
    .editor-top-bar {
        background-color: var(--panel-bg);
        border-bottom: 1px solid var(--border-color);
        padding: 1rem 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }
    
    /* Scrollbars */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.2);
    }
</style>

<div class="builder-layout">
    <!-- Lewy panel (Drzewo Kursu) -->
    <div class="builder-sidebar">
        <div class="builder-sidebar-header">
            <a href="manage_courses.php" class="btn btn-sm btn-outline-secondary mb-3 d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Powrót do listy
            </a>
            <h5 class="fw-bold mb-1" style="font-size: 1.1rem;"><?php echo htmlspecialchars($course['title']); ?></h5>
            <p class="text-muted small mb-0">Edytor wizualny</p>
        </div>
        
        <div class="builder-sidebar-body">
            <div class="accordion accordion-flush" id="courseAccordion">
                <?php foreach ($modules as $index => $mod): ?>
                    <div class="module-item accordion-item">
                        <div class="module-header">
                            <button class="accordion-button collapsed px-0 py-2 bg-transparent shadow-none w-100" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMod<?php echo $mod['id']; ?>">
                                <?php echo htmlspecialchars($mod['title']); ?>
                            </button>
                            <div class="action-icons ms-2">
                                <button class="btn btn-light text-primary border" onclick="openEditModuleModal(<?php echo htmlspecialchars(json_encode($mod)); ?>)" title="Edytuj Moduł"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-light text-danger border" onclick="deleteModule(<?php echo $mod['id']; ?>)" title="Usuń Moduł"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <div id="collapseMod<?php echo $mod['id']; ?>" class="accordion-collapse collapse <?php echo ($activeModule && $activeModule['id'] == $mod['id']) ? 'show' : ''; ?>" data-bs-parent="#courseAccordion">
                            <div class="accordion-body p-0">
                                <ul class="lesson-list">
                                    <?php foreach ($mod['items'] as $item): ?>
                                        <?php 
                                        $itemId = (int)$item['id'];
                                        $classes = [];
                                        if ($itemId === $activeItemId) $classes[] = 'active';
                                        
                                        $icon = 'bi-file-text-fill';
                                        if ($item['type'] === 'quiz') $icon = 'bi-question-square-fill';
                                        if ($item['type'] === 'exam') $icon = 'bi-award-fill text-warning';
                                        if ($item['type'] === 'lab') $icon = 'bi-cpu-fill';
                                        ?>
                                        <li>
                                            <a href="course_builder.php?id=<?php echo $courseId; ?>&item_id=<?php echo $itemId; ?>" class="lesson-link <?php echo implode(' ', $classes); ?>">
                                                <div class="lesson-meta">
                                                    <i class="bi <?php echo $icon; ?> lesson-icon"></i>
                                                    <span style="font-size:0.85rem; overflow:hidden; text-overflow:ellipsis; max-width: 140px; white-space:nowrap; display:inline-block; vertical-align:middle;"><?php echo htmlspecialchars($item['title']); ?></span>
                                                </div>
                                                <div class="action-icons" onclick="event.preventDefault(); event.stopPropagation();">
                                                    <button class="btn btn-light text-danger border" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Usuń Lekcję"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="p-2 bg-light text-center border-top">
                                    <button class="btn btn-sm btn-outline-primary w-100" onclick="openAddItemModal(<?php echo $mod['id']; ?>)"><i class="bi bi-plus"></i> Dodaj lekcję</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="p-3 border-top">
            <button class="btn btn-primary w-100" onclick="openAddModuleModal()"><i class="bi bi-plus-lg"></i> Nowy Moduł</button>
        </div>
    </div>

    <!-- Prawy panel (Obszar edycji) -->
    <div class="builder-content">
        <?php if (!$activeItem): ?>
            <div class="d-flex align-items-center justify-content-center h-100 flex-column text-muted" style="background-color: var(--body-bg);">
                <i class="bi bi-layout-sidebar" style="font-size: 4rem; opacity: 0.3;"></i>
                <h4 class="mt-4 fw-bold" style="color: var(--text-main);">Wybierz lekcję z menu</h4>
                <p style="color: var(--kolor-tekst-jasny);">Kliknij na lekcję w panelu po lewej lub stwórz nową.</p>
            </div>
        <?php else: ?>
            <div class="content-area animate-in d-flex flex-column h-100">
                
                <div class="editor-top-bar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary mb-1 d-block" style="width: fit-content;">Moduł: <?php echo htmlspecialchars($activeModule['title']); ?></span>
                            <h5 class="fw-bold mb-0 text-truncate" style="max-width: 300px; color: var(--text-main);">Edycja: <?php echo htmlspecialchars($activeItem['title']); ?></h5>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-primary d-flex align-items-center gap-2 px-4 shadow-sm" onclick="saveActiveItemContent()">
                            <i class="bi bi-check-lg"></i> Zapisz Zmiany
                        </button>
                    </div>
                </div>

                <div class="flex-grow-1 position-relative" style="overflow: hidden;">
                    <form id="activeItemForm" class="h-100 d-flex flex-column">
                        <input type="hidden" name="action" value="edit_item">
                        <input type="hidden" name="item_id" value="<?php echo $activeItem['id']; ?>">
                        <input type="hidden" name="module_id" value="<?php echo $activeItem['module_id']; ?>">
                        <input type="hidden" name="type" value="<?php echo $activeItem['type']; ?>">

                        <?php if ($activeItem['type'] === 'text'): ?>
                            <!-- GRAPESJS FULL SCREEN CONTAINER -->
                            <div class="d-none">
                                <!-- Hidden title input so it saves correctly -->
                                <input type="text" name="title" value="<?php echo htmlspecialchars($activeItem['title']); ?>">
                            </div>
                            <div id="gjs" style="flex-grow: 1; height:100%; border:none;"></div>
                            <input type="hidden" name="content" id="finalContentInput">

                        <?php else: ?>
                            <div class="dashboard-panel p-4 m-4">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold text-muted small">Tytuł lekcji</label>
                                        <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($activeItem['title']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-muted small">Typ zawartości</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($activeItem['type']); ?>" disabled>
                                    </div>
                                </div>

                                <?php if ($activeItem['type'] === 'video'): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted small">URL wideo YouTube</label>
                                        <input type="url" class="form-control" name="video_url" value="<?php echo htmlspecialchars($activeItem['video_url']); ?>" placeholder="https://www.youtube.com/watch?v=...">
                                    </div>

                                <?php elseif ($activeItem['type'] === 'quiz' || $activeItem['type'] === 'exam'): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted small">Wymagany próg zaliczenia (%)</label>
                                        <input type="number" class="form-control" name="quiz_passing_score" value="<?php echo htmlspecialchars($activeItem['quiz_passing_score']); ?>" min="1" max="100">
                                    </div>
                                    <div class="card mt-4 border-0 shadow-sm">
                                        <div class="card-header bg-transparent border-bottom-0 d-flex justify-content-between align-items-center pt-3 pb-0">
                                            <h6 class="fw-bold mb-0"><i class="bi bi-question-square me-2 text-primary"></i>Pytania <?php echo $activeItem['type'] === 'exam' ? 'egzaminacyjne' : 'quizowe'; ?></h6>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="showQuestionModal()"><i class="bi bi-plus-lg me-1"></i> Dodaj pytanie</button>
                                        </div>
                                        <div class="card-body">
                                            <div id="questionsList" class="list-group list-group-flush">
                                                <div class="text-center text-muted py-3">Ładowanie pytań...</div>
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($activeItem['type'] === 'lab'): ?>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label text-muted small fw-bold">Źródło laba</label>
                                            <select class="form-select" name="lab_source" id="labSource">
                                                <option value="sandbox" <?php echo $activeItem['lab_source'] === 'sandbox' ? 'selected' : ''; ?>>Wbudowany Sandbox</option>
                                                <option value="custom" <?php echo $activeItem['lab_source'] === 'custom' ? 'selected' : ''; ?>>Prywatny Szablon</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8" id="sandboxToolKeyGroup">
                                            <label class="form-label text-muted small fw-bold">Narzędzie Sandboxa</label>
                                            <select class="form-select" name="lab_tool_key">
                                                <option value="logic" <?php echo $activeItem['lab_tool_key'] === 'logic' ? 'selected' : ''; ?>>Bramki logiczne</option>
                                                <option value="psu" <?php echo $activeItem['lab_tool_key'] === 'psu' ? 'selected' : ''; ?>>Kalkulator PSU</option>
                                                <option value="subnet" <?php echo $activeItem['lab_tool_key'] === 'subnet' ? 'selected' : ''; ?>>Podsieci IP</option>
                                                <option value="router" <?php echo $activeItem['lab_tool_key'] === 'router' ? 'selected' : ''; ?>>Laboratorium sieci (Router)</option>
                                                <option value="numbers" <?php echo $activeItem['lab_tool_key'] === 'numbers' ? 'selected' : ''; ?>>Systemy liczbowe</option>
                                                <option value="ohm" <?php echo $activeItem['lab_tool_key'] === 'ohm' ? 'selected' : ''; ?>>Prawo Ohma</option>
                                                <option value="live" <?php echo $activeItem['lab_tool_key'] === 'live' ? 'selected' : ''; ?>>Live HTML/CSS/JS</option>
                                                <option value="crypto" <?php echo $activeItem['lab_tool_key'] === 'crypto' ? 'selected' : ''; ?>>Krypto i Hasła</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8" id="customLabIdGroup" style="display: none;">
                                            <label class="form-label text-muted small fw-bold">Wybierz zapisany szablon</label>
                                            <input type="number" class="form-control" name="lab_custom_id" value="<?php echo $activeItem['lab_custom_id']; ?>" placeholder="ID Szablonu">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted small">Instrukcje dla ucznia</label>
                                        <textarea class="form-control" name="lab_instructions" rows="4"><?php echo htmlspecialchars($activeItem['lab_instructions']); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modals for structure creation -->
<!-- Module Modal -->
<div class="modal fade" id="moduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="moduleForm" onsubmit="saveModule(event)">
                <input type="hidden" name="action" id="moduleAction" value="add_module">
                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                <input type="hidden" name="module_id" id="formModuleId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="moduleModalLabel">Nowy Moduł</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nazwa modułu *</label>
                        <input type="text" class="form-control" name="title" id="moduleTitle" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveModule">Dodaj</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addItemForm" onsubmit="createNewItem(event)">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="module_id" id="newItemModuleId" value="0">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Nowa Lekcja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tytuł lekcji *</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Typ lekcji *</label>
                        <select class="form-select" name="type" required>
                            <option value="text">Lekcja Blokowa (Teksty, Obrazki, Wideo)</option>
                            <option value="video">Wideo (Pojedynczy Film)</option>
                            <option value="quiz">Quiz (Zwykły test)</option>
                            <option value="exam">Egzamin końcowy</option>
                            <option value="lab">Laboratorium (Sandbox)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">Dodaj Lekcję</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Question Modal -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="questionForm" onsubmit="saveQuestion(event)">
                <input type="hidden" name="action" id="questionAction" value="add_question">
                <input type="hidden" name="item_id" value="<?php echo isset($activeItem) ? $activeItem['id'] : 0; ?>">
                <input type="hidden" name="question_id" id="questionId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="questionModalLabel">Nowe Pytanie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Treść pytania *</label>
                        <textarea class="form-control" name="question_text" id="questionText" rows="3" required></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Opcja A *</label>
                            <input type="text" class="form-control" name="option_a" id="optionA" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opcja B *</label>
                            <input type="text" class="form-control" name="option_b" id="optionB" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opcja C</label>
                            <input type="text" class="form-control" name="option_c" id="optionC">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opcja D</label>
                            <input type="text" class="form-control" name="option_d" id="optionD">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Poprawna odpowiedź *</label>
                            <select class="form-select" name="correct_answer" id="correctAnswer" required>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Wyjaśnienie (opcjonalnie)</label>
                        <textarea class="form-control" name="explanation" id="explanation" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- GrapesJS Dependencies -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.2/dist/css/grapes.min.css" integrity="sha384-Rb3hPTAPYUwHzmCPbONJD8eq8Q68caCAY1GOhqbK8gjcW2IRcfrC9tnqZ2Yap69u" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.2/dist/grapes.min.js" integrity="sha384-gA9v1l0ZiLk8aDBHA97GEKpownBGOhcnIUjq2zA6zUFHtWQQr7GNwedHgwCc1lxt" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.js" integrity="sha384-j8iTYN3rOdgCfrmjtMgvExwZ7D5NsWYjtK8mQQSeUX0lquvFxBmVx0En06y9oPHt" crossorigin="anonymous"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
    const csrfToken = '<?php echo generateCsrfToken('manage_courses'); ?>';
    
    let editor = null;
    if (document.getElementById('gjs')) {
        editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            width: 'auto',
            plugins: ['gjs-blocks-basic'], 
            dragMode: 'absolute',
            storageManager: false,
            canvas: {
                styles: [
                    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
                    '../assets/css/fonts.css'
                ]
            }
        });

        editor.on('load', () => {
            // Czysty reset wrapper-a - układ karty jest teraz całkowicie wewnątrz iframe'a
            const wrapper = editor.getWrapper();
            if (wrapper) {
                wrapper.set('droppable', true); // Wymuszenie akceptowania zrzutów
                wrapper.setStyle({ 
                    'position': 'relative',
                    'background-color': '#ffffff',
                    'max-width': '1000px',
                    'margin': '40px auto',
                    'min-height': 'calc(100vh - 80px)', 
                    'padding': '40px',
                    'border-radius': '12px',
                    'box-shadow': '0 5px 25px rgba(0,0,0,0.05)',
                    'font-family': "'Inter', sans-serif",
                    'color': '#111827',
                    'box-sizing': 'border-box'
                });
            }

            // Stylizacja body dokumentu iframe (tło szare poza kartą wrappera)
            const doc = editor.Canvas.getDocument();
            if (doc) {
                const style = doc.createElement('style');
                style.innerHTML = `
                    html, body { 
                        height: 100%; 
                        background-color: #f3f4f6 !important; 
                        margin: 0;
                        padding: 0;
                    }
                    * { box-sizing: border-box; }
                `;
                doc.head.appendChild(style);
            }



            const pn = editor.Panels;
            const openBlocksBtn = pn.getButton('views', 'open-blocks');
            if (openBlocksBtn) {
                openBlocksBtn.set('active', 1);
            }
            editor.runCommand('show-blocks');

            // --- INICJALIZACJA TRYBU "DRAW TO CREATE" (MS PAINT STYLE) ---
            let activeDrawTool = null;
            let drawStartX = 0;
            let drawStartY = 0;
            let isDrawing = false;
            let ghostBox = null;

            // Przechwytywanie kliknięć w GrapesJS Block Manager (z użyciem delegacji zdarzeń, bo bloki są generowane dynamicznie)
            document.addEventListener('click', (e) => {
                const blockEl = e.target.closest('.gjs-block');
                if (blockEl) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Oznaczanie wizualne w panelu
                    document.querySelectorAll('.gjs-block').forEach(b => b.style.outline = 'none');
                    blockEl.style.outline = '3px solid var(--bs-primary)';
                    blockEl.style.borderRadius = '8px';

                    // Szukanie modelu klocka w BlockManager
                    const labelText = blockEl.innerText.trim();
                    const allBlocks = editor.BlockManager.getAll().models;
                    const foundBlock = allBlocks.find(b => {
                        const bLabel = b.get('label').replace(/<[^>]*>?/gm, '').trim();
                        return bLabel === labelText;
                    });

                    if (foundBlock) {
                        activeDrawTool = foundBlock;
                        editor.Canvas.getBody().style.cursor = 'crosshair';
                    }
                }
            }, true);

            // Wyłączanie natywnego Drag&Drop na zewnątrz (aby uniknąć pomyłki)
            document.addEventListener('dragstart', (e) => {
                if (e.target.closest('.gjs-block')) {
                    e.preventDefault();
                }
            });

            // Zdarzenia wewnątrz płótna edytora
            const iframeDoc = editor.Canvas.getDocument();
            const iframeBody = iframeDoc.body;
            
            iframeDoc.addEventListener('mousedown', (e) => {
                if (!activeDrawTool) return;
                
                isDrawing = true;
                drawStartX = e.pageX;
                drawStartY = e.pageY;

                ghostBox = iframeDoc.createElement('div');
                ghostBox.style.position = 'absolute';
                ghostBox.style.left = drawStartX + 'px';
                ghostBox.style.top = drawStartY + 'px';
                ghostBox.style.width = '0px';
                ghostBox.style.height = '0px';
                ghostBox.style.border = '2px dashed #0d6efd';
                ghostBox.style.backgroundColor = 'rgba(13, 110, 253, 0.1)';
                ghostBox.style.pointerEvents = 'none';
                ghostBox.style.zIndex = '999999';
                iframeBody.appendChild(ghostBox);
                
                e.preventDefault();
                e.stopPropagation();
            }, true);

            iframeDoc.addEventListener('mousemove', (e) => {
                if (!isDrawing || !ghostBox) return;
                
                const currentX = e.pageX;
                const currentY = e.pageY;
                
                const width = Math.abs(currentX - drawStartX);
                const height = Math.abs(currentY - drawStartY);
                const left = Math.min(currentX, drawStartX);
                const top = Math.min(currentY, drawStartY);
                
                ghostBox.style.width = width + 'px';
                ghostBox.style.height = height + 'px';
                ghostBox.style.left = left + 'px';
                ghostBox.style.top = top + 'px';
            }, true);

            iframeDoc.addEventListener('mouseup', (e) => {
                if (!isDrawing) return;
                isDrawing = false;
                
                if (ghostBox) {
                    const finalWidth = parseInt(ghostBox.style.width);
                    const finalHeight = parseInt(ghostBox.style.height);
                    const finalLeft = parseInt(ghostBox.style.left);
                    const finalTop = parseInt(ghostBox.style.top);
                    
                    ghostBox.remove();
                    ghostBox = null;
                    
                    const w = finalWidth > 20 ? finalWidth : 200;
                    const h = finalHeight > 20 ? finalHeight : 50;

                    const content = activeDrawTool.get('content');
                    // Dodanie komponentu bezpośrednio do wrappera
                    const addedComponent = editor.getWrapper().append(content);
                    const comp = Array.isArray(addedComponent) ? addedComponent[0] : addedComponent;
                    
                    if (comp) {
                        comp.addStyle({
                            position: 'absolute',
                            left: finalLeft + 'px',
                            top: finalTop + 'px',
                            width: w + 'px',
                            height: h + 'px'
                        });
                        
                        editor.select(comp);
                    }
                    
                    // Wyczyszczenie wybranego narzędzia (powrót kursora)
                    activeDrawTool = null;
                    document.querySelectorAll('.gjs-block').forEach(b => b.style.outline = 'none');
                    editor.Canvas.getBody().style.cursor = 'default';
                }
            }, true);
        });

        const bm = editor.BlockManager;

        // 1. Nagłówek (Heading)
        bm.add('heading', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M5 4v3h5.5v12h3V7H19V4z"/></svg><div class="gjs-block-label">Nagłówek</div>',
            content: '<h2 style="font-family: \'Inter\', sans-serif; color: var(--text-main); font-size: 2rem; font-weight: bold; margin: 0 0 16px 0;">Twój Nagłówek</h2>'
        });

        // 2. Tekst (Paragraph)
        bm.add('text', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M14 17H4v2h10v-2zm6-8H4v2h16V9zM4 15h16v-2H4v2zM4 5v2h16V5H4z"/></svg><div class="gjs-block-label">Tekst</div>',
            content: '<p style="font-family: \'Inter\', sans-serif; color: var(--text-main); line-height: 1.6; margin: 0 0 16px 0;">Wpisz swój tekst tutaj. Możesz go dowolnie edytować i formatować.</p>'
        });

        // 3. Obraz (Image)
        bm.add('image', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c0-1.1-.9-2-2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 5H5l3.5-4.5z"/></svg><div class="gjs-block-label">Obraz</div>',
            content: '<img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=600" alt="Przykładowy obraz" style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 16px;" />'
        });

        // 4. Wideo (Video)
        bm.add('video', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4zM14 16H6V8h8v8z"/></svg><div class="gjs-block-label">Wideo</div>',
            content: '<video src="https://www.w3schools.com/html/mov_bbb.mp4" style="max-width: 100%; border-radius: 8px; background-color: #000; margin-bottom: 16px;" controls></video>'
        });

        // 5. Karta (Card)
        bm.add('card', {
            category: 'Zaawansowane',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg><div class="gjs-block-label">Karta</div>',
            content: '<div style="padding: 24px; background-color: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 16px;"><h4 style="margin-top: 0; color: var(--text-main); font-weight: 700; margin-bottom: 12px;">Tytuł Karty</h4><p style="margin-bottom: 0; color: var(--kolor-tekst-jasny); font-size: 0.95rem; line-height: 1.5;">To jest przykładowa treść wewnątrz karty. Możesz w niej umieszczać tekst, obrazy lub inne elementy.</p></div>'
        });

        // 6. Cytat (Quote)
        bm.add('quote', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg><div class="gjs-block-label">Cytat</div>',
            content: '<blockquote style="border-left: 4px solid var(--primary-color); padding-left: 16px; margin: 0 0 16px 0; font-style: italic; color: var(--text-main); font-size: 1.1rem; line-height: 1.6;">"Wpisz tutaj ważny cytat lub myśl przewodnią..."</blockquote>'
        });

        // 7. Przycisk (Button)
        bm.add('button', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11H6v-2h12v2zm0-4H6V8h12v2z"/></svg><div class="gjs-block-label">Przycisk</div>',
            content: '<a href="#" style="display: inline-block; padding: 10px 24px; background-color: var(--primary-color); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center; font-size: 0.95rem; transition: background-color 0.2s; margin-bottom: 16px;">Kliknij tutaj</a>'
        });

        // 8. Lista (List)
        bm.add('list', {
            category: 'Podstawowe',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg><div class="gjs-block-label">Lista</div>',
            content: '<ul style="color: var(--text-main); line-height: 1.6; margin: 0 0 16px 0; padding-left: 20px;"><li style="margin-bottom: 4px;">Pierwszy element listy</li><li style="margin-bottom: 4px;">Drugi element listy</li><li style="margin-bottom: 0;">Trzeci element listy</li></ul>'
        });

        // 9. Kod (Code Box)
        bm.add('code', {
            category: 'Zaawansowane',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg><div class="gjs-block-label">Kod</div>',
            content: '<pre style="background-color: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; font-family: monospace; overflow: auto; margin: 0 0 16px 0; font-size: 0.85rem; line-height: 1.5; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"><code>// Wpisz tutaj swój kod...\nfunction main() {\n    console.log("ZSEM Tech Course");\n}</code></pre>'
        });

        // 10. Dwie Kolumny (2 Columns)
        bm.add('columns-2', {
            category: 'Zaawansowane',
            label: '<svg viewBox="0 0 24 24" fill="currentColor" style="width:24px;height:24px;"><path d="M4 4h7v16H4V4zm9 0h7v16h-7V4z"/></svg><div class="gjs-block-label">2 Kolumny</div>',
            content: '<div style="display: flex; gap: 24px; margin-bottom: 16px; flex-wrap: wrap;"><div style="flex: 1; min-width: 250px; padding: 16px; background: rgba(0,0,0,0.02); border-radius: 8px; border: 1px dashed var(--border-color);"><p style="margin:0; color: var(--text-main); font-family: \'Inter\', sans-serif;">Kolumna 1</p></div><div style="flex: 1; min-width: 250px; padding: 16px; background: rgba(0,0,0,0.02); border-radius: 8px; border: 1px dashed var(--border-color);"><p style="margin:0; color: var(--text-main); font-family: \'Inter\', sans-serif;">Kolumna 2</p></div></div>'
        });

        // Load existing content
        const rawContent = <?php echo isset($activeItem['content']) ? json_encode($activeItem['content']) : '""'; ?>;
        if (rawContent) {
            try {
                const parsed = JSON.parse(rawContent);
                if (parsed.html) {
                    editor.setComponents(parsed.html);
                    if (parsed.css) editor.setStyle(parsed.css);
                } else {
                    editor.setComponents(rawContent);
                }
            } catch (e) {
                editor.setComponents(rawContent);
            }
        }
    }

    function saveActiveItemContent() {
        const form = document.getElementById('activeItemForm');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        
        if (editor) {
            const html = editor.getHtml();
            const css = editor.getCss();
            const data = JSON.stringify({ html: html, css: css });
            document.getElementById('finalContentInput').value = data;
            formData.set('content', data);
        }

        fetch('../ajax/admin_courses.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Zmiany zostały zapisane.');
                location.reload();
            } else {
                alert(data.message);
            }
        });
    }

    function openAddModuleModal() {
        document.getElementById('moduleModalLabel').innerText = 'Nowy Moduł';
        document.getElementById('moduleAction').value = 'add_module';
        document.getElementById('formModuleId').value = '0';
        document.getElementById('moduleForm').reset();
        document.getElementById('btnSaveModule').innerText = 'Dodaj';
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    function openEditModuleModal(mod) {
        document.getElementById('moduleModalLabel').innerText = 'Edytuj Moduł';
        document.getElementById('moduleAction').value = 'edit_module';
        document.getElementById('formModuleId').value = mod.id;
        document.getElementById('moduleTitle').value = mod.title;
        document.getElementById('btnSaveModule').innerText = 'Zapisz';
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    function saveModule(e) {
        e.preventDefault();
        const formData = new FormData(document.getElementById('moduleForm'));
        formData.append('csrf_token', csrfToken);
        
        fetch('../ajax/admin_courses.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        });
    }

    function deleteModule(id) {
        if (!confirm('Na pewno usunąć ten moduł? Wszystkie jego lekcje też zostaną usunięte!')) return;
        const fd = new FormData();
        fd.append('action', 'delete_module');
        fd.append('module_id', id);
        fd.append('csrf_token', csrfToken);
        
        fetch('../ajax/admin_courses.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.message);
        });
    }

    function openAddItemModal(moduleId) {
        document.getElementById('addItemForm').reset();
        document.getElementById('newItemModuleId').value = moduleId;
        new bootstrap.Modal(document.getElementById('addItemModal')).show();
    }

    function createNewItem(e) {
        e.preventDefault();
        const formData = new FormData(document.getElementById('addItemForm'));
        formData.append('csrf_token', csrfToken);
        formData.append('content', '');
        
        fetch('../ajax/admin_courses.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.href = `course_builder.php?id=<?php echo $courseId; ?>&item_id=${data.item_id}`;
            } else {
                alert(data.message);
            }
        });
    }

    function deleteItem(id) {
        if (!confirm('Na pewno usunąć tę lekcję?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_item');
        fd.append('item_id', id);
        fd.append('csrf_token', csrfToken);
        
        fetch('../ajax/admin_courses.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.href = `course_builder.php?id=<?php echo $courseId; ?>`;
            else alert(data.message);
        });
    }

    const labSourceSelect = document.getElementById('labSource');
    if (labSourceSelect) {
        labSourceSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                document.getElementById('sandboxToolKeyGroup').style.display = 'none';
                document.getElementById('customLabIdGroup').style.display = 'block';
            } else {
                document.getElementById('sandboxToolKeyGroup').style.display = 'block';
                document.getElementById('customLabIdGroup').style.display = 'none';
            }
        });
        labSourceSelect.dispatchEvent(new Event('change'));
    }
    }
    
    function loadQuizQuestions() {
        const itemId = <?php echo isset($activeItem['id']) ? $activeItem['id'] : 0; ?>;
        if (!itemId) return;
        
        fetch(`../ajax/admin_courses.php?action=get_questions&item_id=${itemId}`)
        .then(res => res.json())
        .then(data => {
            const list = document.getElementById('questionsList');
            if (!list) return;
            if (!data.success) {
                list.innerHTML = `<div class="text-danger p-3">${data.message}</div>`;
                return;
            }
            if (data.questions.length === 0) {
                list.innerHTML = `<div class="text-muted text-center py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Brak pytań. Dodaj pierwsze pytanie.</div>`;
                return;
            }
            
            let html = '';
            data.questions.forEach((q, idx) => {
                html += `
                <div class="list-group-item px-0 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1">${idx + 1}. ${escapeHtml(q.question_text)}</h6>
                            <div class="small text-muted mb-2">
                                <div>A: ${escapeHtml(q.option_a)} ${q.correct_answer==='A' ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}</div>
                                <div>B: ${escapeHtml(q.option_b)} ${q.correct_answer==='B' ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}</div>
                                ${q.option_c ? `<div>C: ${escapeHtml(q.option_c)} ${q.correct_answer==='C' ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}</div>` : ''}
                                ${q.option_d ? `<div>D: ${escapeHtml(q.option_d)} ${q.correct_answer==='D' ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}</div>` : ''}
                            </div>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='editQuestion(${JSON.stringify(q).replace(/'/g, "&#39;")})'><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteQuestion(${q.id})"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            });
            list.innerHTML = html;
        });
    }

    function escapeHtml(unsafe) {
        return (unsafe || '').toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    function showQuestionModal() {
        document.getElementById('questionForm').reset();
        document.getElementById('questionAction').value = 'add_question';
        document.getElementById('questionId').value = '0';
        document.getElementById('questionModalLabel').textContent = 'Nowe Pytanie';
        new bootstrap.Modal(document.getElementById('questionModal')).show();
    }
    
    function editQuestion(q) {
        document.getElementById('questionAction').value = 'edit_question';
        document.getElementById('questionId').value = q.id;
        document.getElementById('questionText').value = q.question_text;
        document.getElementById('optionA').value = q.option_a;
        document.getElementById('optionB').value = q.option_b;
        document.getElementById('optionC').value = q.option_c || '';
        document.getElementById('optionD').value = q.option_d || '';
        document.getElementById('correctAnswer').value = q.correct_answer;
        document.getElementById('explanation').value = q.explanation || '';
        document.getElementById('questionModalLabel').textContent = 'Edytuj Pytanie';
        new bootstrap.Modal(document.getElementById('questionModal')).show();
    }
    
    function saveQuestion(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('csrf_token', csrfToken);
        
        fetch('../ajax/admin_courses.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('questionModal')).hide();
                loadQuizQuestions();
            } else {
                alert(data.message);
            }
        });
    }
    
    function deleteQuestion(id) {
        if (!confirm('Na pewno usunąć to pytanie?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_question');
        fd.append('question_id', id);
        fd.append('csrf_token', csrfToken);
        
        fetch('../ajax/admin_courses.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) loadQuizQuestions();
            else alert(data.message);
        });
    }

    <?php if (isset($activeItem) && in_array($activeItem['type'], ['quiz', 'exam'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        loadQuizQuestions();
    });
    <?php endif; ?>
</script>
</body>
</html>
