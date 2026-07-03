<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($courseId <= 0) {
    setSessionMessage('error', 'Nieprawidłowy identyfikator kursu.');
    redirect('courses.php');
}

// Fetch course details
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course || $course['status'] !== 'active') {
    setSessionMessage('error', 'Kurs nie istnieje lub jest nieaktywny.');
    redirect('courses.php');
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// Verify enrollment (guests can view courses page but to open course_learn they must be enrolled)
$enrollStmt = $pdo->prepare("SELECT * FROM user_course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
$enrollStmt->execute([$userId, $courseId]);
$enrollment = $enrollStmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    setSessionMessage('error', 'Zapisz się na kurs, aby rozpocząć naukę.');
    redirect('course_view.php?id=' . $courseId);
}

// Fetch course structure
$modStmt = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$modStmt->execute([$courseId]);
$modules = $modStmt->fetchAll(PDO::FETCH_ASSOC);

$allItems = [];
foreach ($modules as $k => $mod) {
    $itemStmt = $pdo->prepare("SELECT id, title, type, video_url, quiz_passing_score, lab_source, lab_tool_key, lab_custom_id, lab_instructions FROM course_items WHERE module_id = ? ORDER BY sort_order ASC, id ASC");
    $itemStmt->execute([$mod['id']]);
    $modules[$k]['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($modules[$k]['items'] as $item) {
        $allItems[] = $item;
    }
}

// If course is empty, redirect
if (empty($allItems)) {
    setSessionMessage('error', 'Ten kurs nie zawiera jeszcze żadnych materiałów.');
    redirect('course_view.php?id=' . $courseId);
}

// Fetch user progress
$progStmt = $pdo->prepare("SELECT item_id, status, quiz_score, quiz_attempts FROM user_course_progress WHERE user_id = ? AND course_id = ?");
$progStmt->execute([$userId, $courseId]);
$progressRows = $progStmt->fetchAll(PDO::FETCH_ASSOC);

$progressMap = [];
$completedCount = 0;
foreach ($progressRows as $row) {
    $progressMap[(int)$row['item_id']] = $row;
    if ($row['status'] === 'completed') {
        $completedCount++;
    }
}

$totalItems = count($allItems);
$progressPercent = $totalItems > 0 ? (int)round(($completedCount / $totalItems) * 100) : 0;

// Determine active item
$activeItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$activeItem = null;

// Build locking map if sequential learning is enabled
$sequential = ((int)$course['sequential_learning'] === 1);
$lockedMap = [];
$firstUncompletedFound = false;

foreach ($allItems as $index => $item) {
    $itemId = (int)$item['id'];
    
    // Sequential logic: if sequential is enabled, lock everything after the first uncompleted item
    if ($sequential) {
        if ($firstUncompletedFound) {
            $lockedMap[$itemId] = true;
        } else {
            $lockedMap[$itemId] = false;
            $status = $progressMap[$itemId]['status'] ?? 'started';
            if ($status !== 'completed') {
                $firstUncompletedFound = true;
            }
        }
    } else {
        $lockedMap[$itemId] = false;
    }

    if ($itemId === $activeItemId && !$lockedMap[$itemId]) {
        $activeItem = $item;
    }
}

// If no active item specified or it is locked, set to first unlocked uncompleted item (or first item if all complete)
if (!$activeItem) {
    foreach ($allItems as $item) {
        $itemId = (int)$item['id'];
        if (!$lockedMap[$itemId]) {
            $status = $progressMap[$itemId]['status'] ?? 'started';
            if ($status !== 'completed') {
                $activeItem = $item;
                $activeItemId = $itemId;
                break;
            }
        }
    }
    // If still not set, fall back to first item
    if (!$activeItem) {
        $activeItem = $allItems[0];
        $activeItemId = (int)$activeItem['id'];
    }
}

// Fetch active item details (specifically content since we skipped it in the main list query for memory optimization)
$detailStmt = $pdo->prepare("SELECT content FROM course_items WHERE id = ?");
$detailStmt->execute([$activeItemId]);
$activeItemContent = $detailStmt->fetchColumn();

// If it's a quiz, fetch questions
$quizQuestions = [];
if ($activeItem['type'] === 'quiz') {
    $qStmt = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM course_quiz_questions WHERE item_id = ? ORDER BY id ASC");
    $qStmt->execute([$activeItemId]);
    $quizQuestions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
}

// If it's a lab, check if tool is blocked and fetch custom lab if needed
$labToolKey = '';
$labInstructions = '';
$labBlocked = false;
$labBlockedInfo = null;

if ($activeItem['type'] === 'lab') {
    if ($activeItem['lab_source'] === 'custom' && $activeItem['lab_custom_id'] > 0) {
        $cLabStmt = $pdo->prepare("SELECT * FROM course_custom_labs WHERE id = ?");
        $cLabStmt->execute([$activeItem['lab_custom_id']]);
        $customLab = $cLabStmt->fetch(PDO::FETCH_ASSOC);
        if ($customLab) {
            $labToolKey = $customLab['tool_key'];
            $labInstructions = $activeItem['lab_instructions'] ?: $customLab['instructions'];
        }
    } else {
        $labToolKey = $activeItem['lab_tool_key'];
        $labInstructions = $activeItem['lab_instructions'];
    }

    // Check if tool is blocked for user's role
    if (function_exists('getSandboxElementBlockMapForRole')) {
        $blocks = getSandboxElementBlockMapForRole($pdo, $userRole);
        $toolBlockKey = 'tool.' . $labToolKey;
        if (isset($blocks[$toolBlockKey])) {
            $labBlocked = true;
            $labBlockedInfo = $blocks[$toolBlockKey];
        }
    }
}

// Helper to convert YouTube URL to embed
function getYoutubeEmbedUrl($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
    if (preg_match($pattern, $url, $matches)) {
        return "https://www.youtube.com/embed/" . $matches[1] . "?enablejsapi=1&rel=0";
    }
    return null;
}

$pageTitle = htmlspecialchars($activeItem['title']) . ' - ' . htmlspecialchars($course['title']);
$extraCss = ['assets/css/dashboard-new.css'];
include 'includes/header.php';
?>

<style>
    /* Standalone Cisco NetAcad Player Layout */
    .netacad-player-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        width: 100vw;
        background-color: var(--body-bg);
        overflow: hidden;
    }
    .netacad-topbar {
        height: 60px;
        background-color: var(--panel-bg);
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
    }
    .netacad-body-layout {
        display: flex;
        flex-direction: row;
        height: calc(100vh - 60px);
        width: 100%;
        overflow: hidden;
    }
    .netacad-sidebar {
        width: 320px;
        background-color: var(--panel-bg);
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .netacad-sidebar.collapsed {
        margin-left: -320px;
    }
    .netacad-sidebar-content {
        overflow-y: auto;
        flex-grow: 1;
    }
    .netacad-content-wrapper {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
        background-color: var(--body-bg);
    }
    .netacad-content-header {
        height: 50px;
        background-color: var(--panel-bg);
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
    }
    .netacad-canvas-area {
        flex-grow: 1;
        overflow-y: auto;
        position: relative;
        padding: 40px;
        display: flex;
        justify-content: center;
    }
    .netacad-canvas-content {
        width: 100%;
        max-width: 1000px;
        background-color: var(--panel-bg);
        min-height: 800px;
        position: relative; /* Kluczowe dla pozycji absolutnych wygenerowanych z buildera */
        box-shadow: 0 5px 25px rgba(0,0,0,0.03);
        border-radius: 12px;
        padding: 40px;
        box-sizing: border-box;
        border: 1px solid var(--border-color);
    }
    
    /* Navigator arrows on the left and right */
    .netacad-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 45px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--panel-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.04);
        cursor: pointer;
        z-index: 1000;
        transition: all 0.2s;
        color: var(--text-main);
        text-decoration: none;
    }
    .netacad-nav-btn:hover {
        background-color: var(--primary-color);
        color: white !important;
        border-color: var(--primary-color);
    }
    .netacad-nav-btn.prev-btn {
        left: 20px;
    }
    .netacad-nav-btn.next-btn {
        right: 20px;
    }
    
    /* Cisco Outline Connector Lines */
    .netacad-outline-list {
        list-style: none;
        padding-left: 2.5rem;
        position: relative;
        margin: 0;
    }
    .netacad-outline-list::before {
        content: '';
        position: absolute;
        left: 1.45rem;
        top: 0;
        bottom: 0;
        width: 1px;
        border-left: 1px dashed var(--border-color);
        z-index: 1;
    }
    .netacad-outline-item {
        position: relative;
        margin-bottom: 2px;
    }
    .netacad-outline-node {
        position: absolute;
        left: -1.35rem;
        top: 0.65rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--panel-bg);
        border: 2px solid var(--border-color);
        z-index: 2;
        transition: all 0.2s;
    }
    .lesson-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 1rem 0.6rem 1.5rem;
        text-decoration: none;
        color: var(--text-main);
        font-size: 0.85rem;
        border-radius: 8px;
        margin: 2px 8px;
        transition: all 0.2s;
    }
    .lesson-link:hover {
        background-color: rgba(0,0,0,0.02);
    }
    .lesson-link.active {
        background-color: rgba(16, 185, 129, 0.08) !important;
        color: #10b981 !important;
        font-weight: 600;
    }
    .lesson-link.active .netacad-outline-node {
        background-color: #10b981 !important;
        border-color: #10b981 !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }
    .lesson-link.completed .netacad-outline-node {
        background-color: #10b981 !important;
        border-color: #10b981 !important;
    }
    .lesson-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    /* Header strip styling */
    .netacad-header-strip {
        height: 60px;
        background: linear-gradient(90deg, #1f2937 0%, #111827 100%);
        border-radius: 8px 8px 0 0;
        margin: -40px -40px 30px -40px;
        display: flex;
        align-items: center;
        padding-left: 40px;
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .module-item {
        border-bottom: 1px solid var(--border-color);
    }
    .module-btn {
        width: 100%;
        padding: 1rem 1.25rem;
        background: none;
        border: none;
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        color: var(--text-main);
    }
    .module-btn:hover {
        background-color: rgba(0,0,0,0.02);
    }
    .quiz-question-card {
        border: 1px solid var(--border-color);
        background-color: var(--panel-bg);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .option-btn {
        display: block;
        width: 100%;
        padding: 0.85rem 1.25rem;
        margin-bottom: 0.5rem;
        text-align: left;
        background-color: var(--panel-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-main);
        transition: all 0.2s;
    }
    .option-btn:hover {
        background-color: rgba(59, 130, 246, 0.05);
        border-color: var(--primary-color);
    }
    .option-btn.selected {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .option-btn.correct {
        background-color: rgba(16, 185, 129, 0.15) !important;
        border-color: var(--kolor-sukces) !important;
        color: var(--kolor-sukces) !important;
        font-weight: bold;
    }
    .option-btn.incorrect {
        background-color: rgba(239, 68, 68, 0.15) !important;
        border-color: #ef4444 !important;
        color: #ef4444 !important;
    }
    .lab-iframe {
        width: 100%;
        height: 650px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background-color: white;
    }
</style>

<div class="netacad-player-container">
    <!-- Top Bar z logo i tytułem kursu (jak na screenie) -->
    <div class="netacad-topbar">
        <div class="d-flex align-items-center gap-3">
            <div class="fw-bold" style="color: var(--text-main); font-size: 1.1rem; letter-spacing: -0.5px;">
                Cisco <span style="font-weight: 400;">Academy</span>
            </div>
            <div class="text-muted">|</div>
            <div class="fw-semibold text-truncate" style="max-width: 400px; color: var(--text-main); font-size: 0.95rem;">
                <?php echo htmlspecialchars($course['title']); ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="course_view.php?id=<?php echo $courseId; ?>" class="btn btn-link text-decoration-none p-0" style="color: var(--text-main);" title="Zamknij kurs i wróć">
                <i class="bi bi-x-lg fs-5"></i>
            </a>
        </div>
    </div>

    <div class="netacad-body-layout">
        <!-- Lewy Panel (Outline / Spis treści) -->
        <div class="netacad-sidebar" id="netacadSidebar">
            <div class="d-flex border-bottom" style="height: 50px;">
                <button class="bg-transparent border-0 w-50 fw-bold py-2 text-primary border-bottom border-3 border-primary" style="font-size: 0.85rem;">Spis treści</button>
                <button class="bg-transparent border-0 w-50 fw-semibold py-2 text-muted" style="font-size: 0.85rem;">Zasoby</button>
            </div>
            <div class="p-3 border-bottom">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" placeholder="Szukaj lekcji..." onkeyup="filterSidebar(this)" style="font-size: 0.85rem;">
                </div>
            </div>
            
            <div class="netacad-sidebar-content">
                <?php foreach ($modules as $modIndex => $mod): ?>
                    <?php
                    $totalModItems = count($mod['items']);
                    $completedModItems = 0;
                    foreach ($mod['items'] as $item) {
                        if (isset($progressMap[(int)$item['id']]) && $progressMap[(int)$item['id']]['status'] === 'completed') {
                            $completedModItems++;
                        }
                    }
                    $allModCompleted = ($totalModItems > 0 && $completedModItems === $totalModItems);
                    ?>
                    <div class="module-item">
                        <button class="module-btn" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMod-<?php echo $mod['id']; ?>">
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($allModCompleted): ?>
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 0.95rem;"></i>
                                <?php else: ?>
                                    <i class="bi bi-circle text-muted" style="font-size: 0.95rem;"></i>
                                <?php endif; ?>
                                <span class="fw-bold text-truncate" style="max-width: 180px; font-size: 0.88rem;"><?php echo htmlspecialchars($mod['title']); ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="small text-muted" style="font-size: 0.75rem;"><?php echo $completedModItems; ?>/<?php echo $totalModItems; ?></span>
                                <i class="bi bi-chevron-down small"></i>
                            </div>
                        </button>
                        <div class="collapse show" id="collapseMod-<?php echo $mod['id']; ?>">
                            <ul class="netacad-outline-list">
                                <?php foreach ($mod['items'] as $item): ?>
                                    <?php 
                                    $itemId = (int)$item['id'];
                                    $isCompleted = isset($progressMap[$itemId]) && $progressMap[$itemId]['status'] === 'completed';
                                    $isLocked = $lockedMap[$itemId];
                                    
                                    $classes = [];
                                    if ($itemId === $activeItemId) $classes[] = 'active';
                                    if ($isCompleted) $classes[] = 'completed';
                                    if ($isLocked) $classes[] = 'disabled locked';
                                    ?>
                                    <li class="netacad-outline-item">
                                        <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo $itemId; ?>" class="lesson-link <?php echo implode(' ', $classes); ?>">
                                            <div class="netacad-outline-node"></div>
                                            <span class="text-truncate" style="max-width: 185px;"><?php echo htmlspecialchars($item['title']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Prawy panel (Obszar nauki) -->
        <div class="netacad-content-wrapper">
            <div class="netacad-content-header">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-link p-0 me-2" style="color: var(--text-main);" onclick="toggleSidebarOutline()" title="Ukryj/Pokaż spis treści">
                        <i class="bi bi-layout-sidebar-inset fs-5"></i>
                    </button>
                    <span class="fw-semibold text-muted" style="font-size: 0.88rem;"><?php echo htmlspecialchars($activeItem['title']); ?></span>
                </div>
                <div class="d-flex align-items-center gap-3 text-muted">
                    <i class="bi bi-moon fs-5" style="cursor:pointer;" onclick="toggleThemeMode()" title="Tryb ciemny/jasny"></i>
                    <i class="bi bi-search fs-5" style="cursor:pointer;" title="Szukaj"></i>
                    <span style="cursor:pointer; font-weight:700; font-size:0.85rem;">PL</span>
                    <i class="bi bi-person-badge fs-5" style="cursor:pointer;" title="Ułatwienia dostępu"></i>
                    <i class="bi bi-fullscreen fs-5" style="cursor:pointer;" onclick="toggleFullscreenMode()" title="Pełny ekran"></i>
                </div>
            </div>

            <div class="netacad-canvas-area">
                <!-- Strzałki poprzecznego przewijania lekcji -->
                <?php 
                $prevItem = null;
                $nextItem = null;
                foreach ($allItems as $index => $item) {
                    if ((int)$item['id'] === $activeItemId) {
                        if ($index > 0) $prevItem = $allItems[$index - 1];
                        if ($index + 1 < count($allItems)) $nextItem = $allItems[$index + 1];
                        break;
                    }
                }
                ?>
                
                <?php if ($prevItem && !$lockedMap[(int)$prevItem['id']]): ?>
                    <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo $prevItem['id']; ?>" class="netacad-nav-btn prev-btn" title="Poprzednia lekcja">
                        <i class="bi bi-chevron-left fs-4"></i>
                    </a>
                <?php endif; ?>

                <?php if ($nextItem && !$lockedMap[(int)$nextItem['id']]): ?>
                    <a href="course_learn.php?course_id=<?php echo $courseId; ?>&item_id=<?php echo $nextItem['id']; ?>" class="netacad-nav-btn next-btn" title="Kolejna lekcja">
                        <i class="bi bi-chevron-right fs-4"></i>
                    </a>
                <?php endif; ?>

                <div class="netacad-canvas-content">
                    <!-- Nagłówek lekcji / Pasek dekoracyjny w stylu Cisco -->
                    <div class="netacad-header-strip">
                        <?php echo htmlspecialchars($activeItem['title']); ?>
                    </div>

                    <!-- 1. TEXT/BLOCK CONTENT (Renderowanie kodu wygenerowanego z buildera) -->
                    <?php if ($activeItem['type'] === 'text'): ?>
                        <div style="position: relative; width: 100%; min-height: 800px;">
                            <?php 
                            $decodedBlocks = json_decode($activeItemContent, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBlocks)) {
                                if (isset($decodedBlocks['html'])) {
                                    if (!empty($decodedBlocks['css'])) {
                                        echo '<style>' . $decodedBlocks['css'] . '</style>';
                                    }
                                    echo $decodedBlocks['html'];
                                } else {
                                    foreach ($decodedBlocks as $b) {
                                        if ($b['type'] === 'text') {
                                            echo '<div class="mb-4">' . $b['content'] . '</div>';
                                        } elseif ($b['type'] === 'image') {
                                            echo '<div class="mb-4 text-center"><img src="' . htmlspecialchars($b['url']) . '" class="img-fluid rounded border" alt="Ilustracja do lekcji" style="max-height:600px;"></div>';
                                        } elseif ($b['type'] === 'video') {
                                            $emb = getYoutubeEmbedUrl($b['url']);
                                            if ($emb) {
                                                echo '<div class="ratio ratio-16x9 mb-4 overflow-hidden rounded-3 border"><iframe src="' . $emb . '" allowfullscreen></iframe></div>';
                                            }
                                        }
                                    }
                                }
                            } else {
                                echo $activeItemContent;
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- 2. VIDEO CONTENT -->
                    <?php if ($activeItem['type'] === 'video'): ?>
                        <div class="ratio ratio-16x9 mb-4 overflow-hidden rounded-3 border">
                            <?php 
                            $embedUrl = getYoutubeEmbedUrl($activeItem['video_url']);
                            if ($embedUrl): 
                            ?>
                                <iframe id="ytVideoPlayer" src="<?php echo $embedUrl; ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            <?php else: ?>
                                <div class="bg-dark text-white d-flex align-items-center justify-content-center flex-column">
                                    <i class="bi bi-exclamation-triangle fs-1 text-warning mb-2"></i>
                                    Niepoprawny link do filmu YouTube.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 3. QUIZ / EXAM CONTENT -->
                    <?php if ($activeItem['type'] === 'quiz' || $activeItem['type'] === 'exam'): ?>
                        <div id="quizContainer">
                            <?php if (empty($quizQuestions)): ?>
                                <div class="alert alert-warning">Brak pytań w tym quizie. Skontaktuj się z administratorem.</div>
                            <?php else: ?>
                                <div class="alert alert-info border-0 mb-4 d-flex align-items-center">
                                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                                    Zaznacz poprawne odpowiedzi. Wymagany próg zaliczeniowy: <strong><?php echo $activeItem['quiz_passing_score']; ?>%</strong>. 
                                    <?php if ($activeItem['type'] === 'exam'): ?>
                                        To jest egzamin końcowy. Powodzenia!
                                    <?php endif; ?>
                                </div>

                                <form id="quizForm" onsubmit="submitQuiz(event)">
                                    <?php foreach ($quizQuestions as $qIndex => $q): ?>
                                        <div class="quiz-question-card mb-4" data-question-id="<?php echo $q['id']; ?>">
                                            <h6 class="fw-bold mb-3"><?php echo ($qIndex + 1) . '. ' . htmlspecialchars($q['question_text']); ?></h6>
                                            
                                            <div class="options-group">
                                                <button type="button" class="option-btn" data-value="A" onclick="selectQuizOption(this, <?php echo $q['id']; ?>)">
                                                    <strong>A.</strong> <?php echo htmlspecialchars($q['option_a']); ?>
                                                </button>
                                                <button type="button" class="option-btn" data-value="B" onclick="selectQuizOption(this, <?php echo $q['id']; ?>)">
                                                    <strong>B.</strong> <?php echo htmlspecialchars($q['option_b']); ?>
                                                </button>
                                                <?php if (!empty($q['option_c'])): ?>
                                                    <button type="button" class="option-btn" data-value="C" onclick="selectQuizOption(this, <?php echo $q['id']; ?>)">
                                                        <strong>C.</strong> <?php echo htmlspecialchars($q['option_c']); ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($q['option_d'])): ?>
                                                    <button type="button" class="option-btn" data-value="D" onclick="selectQuizOption(this, <?php echo $q['id']; ?>)">
                                                        <strong>D.</strong> <?php echo htmlspecialchars($q['option_d']); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="explanation-box mt-3 text-muted small border-start ps-3" style="display: none;"></div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div id="quizResultSummary" class="fw-bold"></div>
                                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4" id="btnSubmitQuiz">Sprawdź odpowiedzi</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 4. LAB CONTENT -->
                    <?php if ($activeItem['type'] === 'lab'): ?>
                        <div class="flex-grow-1 d-flex flex-column">
                            <?php if ($labInstructions): ?>
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light border-0 fw-bold d-flex align-items-center justify-content-between" style="cursor: pointer;" onclick="toggleLabInstructions()">
                                        <span><i class="bi bi-file-earmark-text me-1 text-primary"></i> Instrukcja laboratoryjna (Kliknij, aby zwinąć/rozwinąć)</span>
                                        <i class="bi bi-chevron-up" id="labInstructionsIcon"></i>
                                    </div>
                                    <div class="card-body" id="labInstructionsBody" style="line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($labInstructions)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($labBlocked): ?>
                                <div class="bg-dark text-white rounded-3 p-5 text-center my-auto d-flex flex-column align-items-center border border-danger">
                                    <i class="bi bi-shield-lock-fill text-danger fs-1 mb-3"></i>
                                    <h4 class="fw-bold text-danger">Laboratorium wyłączone przez administratora</h4>
                                    <p class="text-white-50 max-w-md mt-2 mb-4">
                                        To narzędzie symulacyjne (<?php echo htmlspecialchars($labToolKey); ?>) zostało zablokowane dla Twojej roli.
                                    </p>
                                    <small class="text-muted">Powód: <?php echo htmlspecialchars($labBlockedInfo['body'] ?? 'Prace konserwacyjne'); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="position-relative flex-grow-1">
                                    <iframe class="lab-iframe" src="sandbox/index.php?tool=<?php echo urlencode($labToolKey); ?>&embed=1" title="Sandbox Tool"></iframe>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Przycisk ukończenia lekcji na dole samej karty lekcji -->
                    <?php 
                    $status = $progressMap[$activeItemId]['status'] ?? 'started';
                    $isCompleted = ($status === 'completed');
                    $showCompleteBtn = ($activeItem['type'] !== 'quiz' && $activeItem['type'] !== 'exam'); 
                    ?>
                    <?php if ($showCompleteBtn): ?>
                        <div class="text-center mt-5 pt-4 border-top">
                            <button type="button" class="btn <?php echo $isCompleted ? 'btn-outline-success' : 'btn-success'; ?> btn-lg fw-bold px-5 py-3 rounded-pill shadow-sm" onclick="markActiveItemComplete()">
                                <?php if ($isCompleted): ?>
                                    <i class="bi bi-check-circle-fill me-2"></i> Lekcja Ukończona
                                <?php else: ?>
                                    Oznacz jako ukończone <i class="bi bi-arrow-right ms-2"></i>
                                <?php endif; ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php if ($isCompleted): ?>
                            <div class="text-center mt-5 pt-4 border-top">
                                <button type="button" class="btn btn-primary btn-lg fw-bold px-5 py-3 rounded-pill shadow-sm" onclick="navigateToNextLesson()">
                                    Kolejna lekcja <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const csrfToken = '<?php echo generateCsrfToken("course_progress"); ?>';
    const courseId = <?php echo $courseId; ?>;
    const activeItemId = <?php echo $activeItemId; ?>;
    const activeItemType = '<?php echo $activeItem['type']; ?>';
    
    // Quiz temporary selection map
    const quizAnswers = {};

    function selectQuizOption(btn, questionId) {
        // Remove selected class from siblings
        const parent = btn.parentElement;
        parent.querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
        
        btn.classList.add('selected');
        quizAnswers[questionId] = btn.getAttribute('data-value');
    }

    function submitQuiz(e) {
        e.preventDefault();

        // Check if all questions answered
        const questionCards = document.querySelectorAll('.quiz-question-card');
        if (Object.keys(quizAnswers).length < questionCards.length) {
            alert('Proszę odpowiedzieć na wszystkie pytania przed sprawdzeniem.');
            return;
        }

        const btnSubmit = document.getElementById('btnSubmitQuiz');
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Sprawdzanie...';

        const formData = new FormData();
        formData.append('action', 'submit_quiz');
        formData.append('item_id', activeItemId);
        formData.append('csrf_token', csrfToken);
        
        // Append answers
        for (const [qId, ans] of Object.entries(quizAnswers)) {
            formData.append(`answers[${qId}]`, ans);
        }

        fetch('ajax/course_progress.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Sprawdzaj ponownie';

            if (data.success) {
                // Render score feedback
                const summary = document.getElementById('quizResultSummary');
                if (data.passed) {
                    summary.innerHTML = `<span class="text-success fs-5"><i class="bi bi-patch-check-fill me-1"></i> Zaliczono! Wynik: ${data.score_percent}% (${data.correct_count}/${data.total_count})</span>`;
                    
                    // Show next button / success check
                    showAlert('success', 'Gratulacje! Zaliczono quiz. Możesz przejść do kolejnej lekcji.');
                } else {
                    summary.innerHTML = `<span class="text-danger fs-5"><i class="bi bi-x-octagon-fill me-1"></i> Niezaliczono. Wynik: ${data.score_percent}% (Wymagane: ${data.passing_score}%)</span>`;
                    showAlert('error', 'Niestety, próg zaliczeniowy nie został osiągnięty. Spróbuj ponownie.');
                }

                // Highlight correct/incorrect answers and show explanations
                for (const [qId, detail] of Object.entries(data.details)) {
                    const card = document.querySelector(`.quiz-question-card[data-question-id="${qId}"]`);
                    if (!card) continue;

                    // Disable option buttons click
                    card.querySelectorAll('.option-btn').forEach(btn => {
                        btn.onclick = null;
                        const val = btn.getAttribute('data-value');
                        if (val === detail.correct_answer) {
                            btn.classList.add('correct');
                        } else if (val === detail.user_answer && !detail.is_correct) {
                            btn.classList.add('incorrect');
                        }
                    });

                    // Render explanation if exists
                    const expBox = card.querySelector('.explanation-box');
                    if (detail.explanation) {
                        expBox.innerHTML = `<strong>Wskazówka:</strong> ${escapeHtml(detail.explanation)}`;
                        expBox.style.display = 'block';
                    }
                }

                // Update progress sidebar
                updateSidebarProgress(data.progress_percent);
                
                // If passed, reload after a brief moment or show a navigation button to go to next lesson
                if (data.passed) {
                    setTimeout(() => {
                        location.reload(); // reloads to unlock sequential paths and update headers
                    }, 2500);
                }
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(() => {
            btnSubmit.disabled = false;
            btnSubmit.innerText = 'Sprawdź odpowiedzi';
            showAlert('error', 'Wystąpił błąd podczas wysyłania odpowiedzi.');
        });
    }

    function markActiveItemComplete() {
        const formData = new FormData();
        formData.append('action', 'mark_completed');
        formData.append('item_id', activeItemId);
        formData.append('csrf_token', csrfToken);

        fetch('ajax/course_progress.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateSidebarProgress(data.progress_percent);
                // Mark active node as completed in list
                const activeLink = document.querySelector('.lesson-link.active');
                if (activeLink) {
                    activeLink.classList.add('completed');
                    const statusIcon = activeLink.querySelector('.lesson-status-icon');
                    if (statusIcon) statusIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                }
                
                // Navigate to next lesson
                navigateToNextLesson();
            } else {
                showAlert('error', data.message);
            }
        });
    }

    function navigateToNextLesson() {
        // Find current link, then find next list link that is not disabled
        const links = Array.from(document.querySelectorAll('.lesson-link'));
        const currentIndex = links.findIndex(l => l.classList.contains('active'));
        if (currentIndex !== -1 && currentIndex + 1 < links.length) {
            const nextLink = links[currentIndex + 1];
            if (!nextLink.classList.contains('disabled')) {
                window.location.href = nextLink.href;
            } else {
                // If locked, we need to reload so server refreshes the sequential lock states
                window.location.reload();
            }
        } else {
            // Course finished!
            showAlert('success', 'Gratulacje! Ukończyłeś wszystkie materiały w tym kursie.');
            setTimeout(() => {
                window.location.href = 'course_view.php?id=' + courseId;
            }, 3000);
        }
    }

    function updateSidebarProgress(percent) {
        document.getElementById('sidebarProgressText').innerText = percent + '%';
        const bar = document.getElementById('sidebarProgressBar');
        bar.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', percent);
    }

    function toggleLabInstructions() {
        const body = document.getElementById('labInstructionsBody');
        const icon = document.getElementById('labInstructionsIcon');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            icon.className = 'bi bi-chevron-up';
        } else {
            body.style.display = 'none';
            icon.className = 'bi bi-chevron-down';
        }
    }

    function escapeHtml(string) {
        return String(string).replace(/[&<>"']/g, function (s) {
            return {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': '&quot;',
                "'": '&#39;'
            }[s];
        });
    }

    // Nowe funkcje nawigacyjne dla odtwarzacza NetAcad
    function toggleSidebarOutline() {
        const sidebar = document.getElementById('netacadSidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
        }
    }

    function toggleThemeMode() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-bs-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    }

    function toggleFullscreenMode() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.error(`Błąd wejścia w tryb pełnoekranowy: ${err.message}`);
            });
        } else {
            document.exitFullscreen();
        }
    }

    function filterSidebar(input) {
        const query = input.value.toLowerCase();
        document.querySelectorAll('.netacad-outline-item').forEach(item => {
            const text = item.querySelector('span').innerText.toLowerCase();
            if (text.includes(query)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>
