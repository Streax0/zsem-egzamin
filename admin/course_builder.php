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
    .builder-layout {
        display: flex;
        flex-direction: row-reverse; /* Sidebar on the right */
        min-height: calc(100vh - 65px);
        background-color: var(--body-bg);
    }
    .builder-sidebar {
        width: 320px;
        border-right: 1px solid var(--border-color);
        background-color: var(--panel-bg);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }
    .builder-content {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .builder-sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .builder-sidebar-body {
        overflow-y: auto;
        flex-grow: 1;
    }
    .module-item {
        border-bottom: 1px solid var(--border-color);
    }
    .module-header {
        width: 100%;
        padding: 0.75rem 1rem;
        background: none;
        border: none;
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        color: var(--text-main);
    }
    .module-header:hover {
        background-color: rgba(0,0,0,0.02);
    }
    .lesson-list {
        list-style: none;
        padding: 0;
        margin: 0;
        background-color: rgba(0,0,0,0.01);
    }
    .lesson-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 1rem 0.5rem 2rem;
        text-decoration: none;
        color: var(--text-main);
        font-size: 0.88rem;
        border-left: 3px solid transparent;
        transition: all 0.2s;
    }
    .lesson-link:hover {
        background-color: rgba(0,0,0,0.03);
    }
    .lesson-link.active {
        background-color: rgba(59, 130, 246, 0.05);
        color: var(--primary-color);
        border-left-color: var(--primary-color);
        font-weight: 600;
    }
    
    /* GrapesJS Modern UI Overrides */
    .gjs-one-bg { background-color: #1e1e2d !important; } /* Main panel bg */
    .gjs-two-color { color: #a1a5b7 !important; } /* Text color */
    .gjs-three-bg { background-color: #e33e5c !important; color: white !important; } /* Active elements */
    .gjs-four-color, .gjs-four-color-h:hover { color: #e33e5c !important; } /* Icons hover */
    .gjs-pn-panel { border: none !important; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .gjs-block {
        border-radius: 8px !important;
        border: 1px solid rgba(255,255,255,0.05) !important;
        background: #2b2b40 !important;
        transition: all 0.2s ease !important;
        box-shadow: none !important;
        padding: 15px 10px !important;
    }
    .gjs-block:hover {
        border-color: #e33e5c !important;
        background: #32324b !important;
        transform: translateY(-2px);
    }
    .gjs-block-label { font-weight: 600 !important; font-family: 'Inter', sans-serif !important; margin-top: 5px !important; font-size: 0.8rem !important; }
    .gjs-cv-canvas { background-color: #f4f6f9 !important; } /* Canvas wrapper bg */
    .gjs-category-title { background-color: #151521 !important; border-bottom: 1px solid rgba(255,255,255,0.05) !important; font-weight: 600 !important; font-family: 'Inter', sans-serif !important; }
    .gjs-title { font-family: 'Inter', sans-serif !important; }

    .lesson-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .lesson-icon {
        font-size: 1.05rem;
        color: var(--kolor-tekst-jasny);
    }
    .lesson-link.active .lesson-icon {
        color: var(--primary-color);
    }
    .action-icons {
        display: flex;
        gap: 0.25rem;
    }
    .action-icons .btn {
        padding: 0.15rem 0.35rem;
        font-size: 0.8rem;
    }
    .content-area {
        max-width: 1000px;
        width: 100%;
        margin: 0 auto;
        padding: 2.5rem 1.5rem;
    }
    
    /* TinyMCE customization */
    .tox-tinymce {
        border-radius: 12px !important;
        border: 1px solid var(--border-color) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
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
            <div class="d-flex align-items-center justify-content-center h-100 flex-column text-muted">
                <i class="bi bi-layout-text-window-reverse" style="font-size: 4rem; opacity: 0.5;"></i>
                <h4 class="mt-3">Wybierz lekcję do edycji</h4>
                <p>Kliknij na lekcję w panelu po lewej lub stwórz nową.</p>
            </div>
        <?php else: ?>
            <div class="content-area animate-in d-flex flex-column h-100">
                
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary mb-1 d-block" style="width: fit-content;">Moduł: <?php echo htmlspecialchars($activeModule['title']); ?></span>
                            <h5 class="fw-bold mb-0 text-truncate" style="max-width: 300px;">Edycja: <?php echo htmlspecialchars($activeItem['title']); ?></h5>
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
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i> Ze względu na brak zaawansowanego buildera pytań, ustaw tutaj po prostu próg zaliczenia. Wygeneruj pytania <?php echo $activeItem['type'] === 'exam' ? 'egzaminacyjne' : 'quizowe'; ?> z bazy.
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-muted small">Wymagany próg zaliczenia (%)</label>
                                        <input type="number" class="form-control" name="quiz_passing_score" value="<?php echo htmlspecialchars($activeItem['quiz_passing_score']); ?>" min="1" max="100">
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

<!-- GrapesJS Dependencies -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.21.2/dist/css/grapes.min.css">
<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.21.2/dist/grapes.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.js"></script>
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
            pluginsOpts: {
                'gjs-blocks-basic': {
                    flexGrid: true,
                    stylePrefix: 'gjs-',
                    addBasicStyle: true
                }
            },
            storageManager: false,
            canvas: {
                styles: [
                    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
                    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'
                ]
            }
        });

        editor.on('load', () => {
            // Set canvas background to white for document feel
            const wrapper = editor.getWrapper();
            if (wrapper) {
                wrapper.setStyle({ 
                    'background-color': '#ffffff', 
                    'min-height': '100vh', 
                    'padding': '40px',
                    'font-family': "'Inter', sans-serif",
                    'color': '#333'
                });
            }

            // Custom Blocks for Course
            const bm = editor.BlockManager;
            
            bm.add('alert-info', {
                label: '<i class="bi bi-info-square fs-3 mb-1 d-block text-info"></i> Informacja',
                category: 'Komponenty Kursu',
                content: '<div class="alert alert-info border-start border-4 border-info shadow-sm p-4 mb-4"><h5 class="fw-bold mb-2"><i class="bi bi-info-circle me-2"></i>Ważna Informacja</h5><p class="mb-0">Wpisz treść informacji tutaj...</p></div>',
            });

            bm.add('alert-warning', {
                label: '<i class="bi bi-exclamation-triangle fs-3 mb-1 d-block text-warning"></i> Ostrzeżenie',
                category: 'Komponenty Kursu',
                content: '<div class="alert alert-warning border-start border-4 border-warning shadow-sm p-4 mb-4"><h5 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Uwaga</h5><p class="mb-0">Wpisz tekst ostrzeżenia...</p></div>',
            });

            bm.add('code-snippet', {
                label: '<i class="bi bi-code-slash fs-3 mb-1 d-block text-secondary"></i> Kod (Snippet)',
                category: 'Komponenty Kursu',
                content: '<div class="bg-dark text-light p-3 rounded mb-4 font-monospace shadow-sm"><pre class="m-0" style="color: #00ff00;"><code>// Twój kod tutaj...</code></pre></div>',
            });

            bm.add('course-card', {
                label: '<i class="bi bi-card-text fs-3 mb-1 d-block text-primary"></i> Karta',
                category: 'Komponenty Kursu',
                content: '<div class="card shadow-sm border-0 mb-4"><div class="card-body"><h5 class="card-title fw-bold">Tytuł karty</h5><p class="card-text">Treść karty...</p></div></div>'
            });

            bm.add('fancy-title', {
                label: '<i class="bi bi-type-h2 fs-3 mb-1 d-block text-success"></i> Ozdobny Tytuł',
                category: 'Komponenty Kursu',
                content: '<h2 class="fw-bold mb-4" style="color: #e33e5c; border-bottom: 2px solid #eee; padding-bottom: 10px;">Tytuł sekcji</h2>'
            });

            // Open block manager automatically
            const pn = editor.Panels;
            const openBlocksBtn = pn.getButton('views', 'open-blocks');
            if (openBlocksBtn) openBlocksBtn.set('active', 1);
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
</script>
</body>
</html>
