<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();
ensurePlatformEnhancements($pdo);

// Prevent caching of this page to force latest JavaScript/CSS to load
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';
$canCreateLesson = in_array($role, ['teacher', 'admin', 'dyrektor'], true);
$qualifications = ['general' => 'Ogólne', 'INF.02' => 'INF.02', 'INF.03' => 'INF.03', 'INF.04' => 'INF.04', 'INF.07' => 'INF.07', 'INF.08' => 'INF.08'];
$types = ['lesson' => 'Lekcja', 'homework' => 'Zadanie domowe'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'lessons')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('lessons.php');
    }
    $action = $_POST['action'] ?? 'create';
    if ($action === 'archive' && $canCreateLesson) {
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE lessons SET status = 'archived' WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$lessonId, $userId]);
        setSessionMessage('success', 'Lekcja została zarchiwizowana.');
        redirect('lessons.php');
    }
    if ($action === 'toggle_pdf_download' && $canCreateLesson) {
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT teacher_id FROM lessons WHERE id = ? LIMIT 1");
        $stmt->execute([$lessonId]);
        $lessonTeacherId = (int)$stmt->fetchColumn();
        
        $isAdmin = in_array($role, ['admin', 'dyrektor'], true);
        if ($lessonTeacherId > 0 && ($lessonTeacherId === $userId || $isAdmin)) {
            $jsonPath = __DIR__ . '/pdf/lesson_' . $lessonId . '.json';
            $meta = readJsonMetadata($jsonPath);
            
            $currentAllowed = (int)($meta['pdf_download_allowed'] ?? 0);
            $newAllowed = $currentAllowed === 1 ? 0 : 1;
            $meta['pdf_download_allowed'] = $newAllowed;
            
            writeJsonMetadata($jsonPath, $meta);
            
            $lessonHasPdfColumns = dbColumnExists($pdo, 'lessons', 'pdf_path')
                && dbColumnExists($pdo, 'lessons', 'pdf_filename')
                && dbColumnExists($pdo, 'lessons', 'pdf_download_allowed');
            if ($lessonHasPdfColumns) {
                try {
                    $updateStmt = $pdo->prepare('UPDATE lessons SET pdf_download_allowed = ? WHERE id = ?');
                    $updateStmt->execute([$newAllowed, $lessonId]);
                } catch (Throwable $e) {
                    error_log('Failed to toggle PDF download in DB: ' . $e->getMessage());
                }
            }
            setSessionMessage('success', 'Ustawienia pobierania PDF zostały zaktualizowane.');
        } else {
            setSessionMessage('error', 'Brak uprawnień do edycji tej lekcji.');
        }
        redirect('lessons.php');
    }
    if ($action === 'create' && $canCreateLesson) {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $qualification = $_POST['qualification'] ?? 'general';
        $lessonType = $_POST['lesson_type'] ?? 'lesson';
        $dueRaw = trim((string)($_POST['due_at'] ?? ''));
        $pdfDownloadAllowed = isset($_POST['pdf_download_allowed']) ? 1 : 0;
        $pdfFile = $_FILES['pdf_file'] ?? null;
        $errors = [];
        if ($title === '' || mb_strlen($title, 'UTF-8') > 160) $errors[] = 'Tytuł jest wymagany i może mieć maksymalnie 160 znaków.';
        if ($body === '' || countWordsUtf8($body) > 1000) $errors[] = 'Treść lekcji jest wymagana i może mieć maksymalnie 1000 słów.';
        if (!array_key_exists($qualification, $qualifications)) $qualification = 'general';
        if (!array_key_exists($lessonType, $types)) $lessonType = 'lesson';
        if (containsProfanity($title) || containsProfanity($body)) $errors[] = 'Treść zawiera niedozwolone słowa.';
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE teacher_id = ? AND status = 'published'");
        $countStmt->execute([$userId]);
        if ((int)$countStmt->fetchColumn() >= 50) $errors[] = 'Limit aktywnych lekcji dla nauczyciela wynosi 50.';
        $dueAt = null;
        if ($dueRaw !== '') {
            $ts = strtotime($dueRaw);
            if ($ts === false) $errors[] = 'Nieprawidłowy termin zadania.';
            else $dueAt = date('Y-m-d H:i:s', $ts);
        }        if ($pdfFile && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($pdfFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[] = 'Błąd przesyłania pliku PDF.';
            } else {
                $tmpName = $pdfFile['tmp_name'];
                $originalName = trim((string)$pdfFile['name']);
                $extension = getUploadedFileExtension($originalName);
                $mimeType = '';
                if (is_uploaded_file($tmpName) && function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $tmpName);
                    finfo_close($finfo);
                }
                if ($extension !== 'pdf' || ($mimeType !== '' && $mimeType !== 'application/pdf')) {
                    $errors[] = 'Dozwolony jest tylko plik PDF.';
                } elseif ($pdfFile['size'] > 16 * 1024 * 1024) {
                    $errors[] = 'Plik PDF może mieć maksymalnie 16 MB.';
                } elseif (!is_uploaded_file($tmpName)) {
                    $errors[] = 'Nieprawidłowy plik PDF.';
                }
            }
        } else {
            $pdfFile = null;
        }
        $lessonHasPdfColumns = dbColumnExists($pdo, 'lessons', 'pdf_path')
            && dbColumnExists($pdo, 'lessons', 'pdf_filename')
            && dbColumnExists($pdo, 'lessons', 'pdf_download_allowed');

        if (empty($errors)) {
            // Always insert using standard columns to guarantee database compatibility
            $stmt = $pdo->prepare("INSERT INTO lessons (teacher_id, title, body, qualification, lesson_type, due_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $body, $qualification, $lessonType, $dueAt]);
            $lessonId = (int)$pdo->lastInsertId();

            if ($pdfFile) {
                $uploadDir = __DIR__ . '/pdf';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // File system paths
                $destination = $uploadDir . '/lesson_' . $lessonId . '.pdf';
                $metaDestination = $uploadDir . '/lesson_' . $lessonId . '.json';

                if (move_uploaded_file($pdfFile['tmp_name'], $destination)) {
                    // Write metadata to file system as primary/fallback source
                    $meta = [
                        'pdf_filename' => $originalName,
                        'pdf_download_allowed' => $pdfDownloadAllowed
                    ];
                    writeJsonMetadata($metaDestination, $meta);

                    // If columns exist, also attempt to update them in the database for consistency
                    if ($lessonHasPdfColumns) {
                        try {
                            $updateStmt = $pdo->prepare('UPDATE lessons SET pdf_path = ?, pdf_filename = ?, pdf_download_allowed = ? WHERE id = ?');
                            $updateStmt->execute(['pdf/lesson_' . $lessonId . '.pdf', $originalName, $pdfDownloadAllowed, $lessonId]);
                        } catch (Throwable $e) {
                            error_log('Failed to update PDF columns in DB: ' . $e->getMessage());
                        }
                    }
                } else {
                    setSessionMessage('error', 'Lekcja została opublikowana, ale nie udało się zapisać pliku PDF.');
                    redirect('lessons.php');
                }
            }

            setSessionMessage('success', 'Lekcja została opublikowana.');
        } else {
            setSessionMessage('error', implode(' ', $errors));
        }
        redirect('lessons.php');
    }
}

$query = trim((string)($_GET['q'] ?? ''));
$filterQual = $_GET['qualification'] ?? '';
$filterType = $_GET['type'] ?? '';
$where = ["l.status = 'published'"];
$params = [];
if ($query !== '') {
    $where[] = "(l.title LIKE ? OR l.body LIKE ? OR u.username LIKE ?)";
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}
if (array_key_exists($filterQual, $qualifications) && $filterQual !== '') {
    $where[] = "l.qualification = ?";
    $params[] = $filterQual;
}
if (array_key_exists($filterType, $types)) {
    $where[] = "l.lesson_type = ?";
    $params[] = $filterType;
}
$sql = "
    SELECT l.id, l.teacher_id, l.title, l.body, l.pdf_path, l.pdf_filename, l.pdf_download_allowed, l.qualification, l.lesson_type, l.status, l.due_at, l.created_at, l.updated_at, u.username, u.first_name, u.last_name
    FROM lessons l
    JOIN users u ON u.id = l.teacher_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load PDF fields from file system metadata as primary/fallback source
foreach ($lessons as $k => $lesson) {
    $dbPdfPath = isset($lesson['pdf_path']) ? $lesson['pdf_path'] : null;
    $dbPdfFilename = isset($lesson['pdf_filename']) ? $lesson['pdf_filename'] : null;
    $dbPdfDownloadAllowed = isset($lesson['pdf_download_allowed']) ? (int)$lesson['pdf_download_allowed'] : 0;

    $fsPdfPath = 'pdf/lesson_' . (int)$lesson['id'] . '.pdf';
    $fsJsonPath = 'pdf/lesson_' . (int)$lesson['id'] . '.json';

    if (file_exists(__DIR__ . '/' . $fsPdfPath)) {
        $lessons[$k]['pdf_path'] = $fsPdfPath;
        if (file_exists(__DIR__ . '/' . $fsJsonPath)) {
            $meta = readJsonMetadata(__DIR__ . '/' . $fsJsonPath);
            $lessons[$k]['pdf_filename'] = $meta['pdf_filename'] ?? 'dokument.pdf';
            $lessons[$k]['pdf_download_allowed'] = (int)($meta['pdf_download_allowed'] ?? 0);
        } else {
            $lessons[$k]['pdf_filename'] = $dbPdfFilename ?: 'dokument.pdf';
            $lessons[$k]['pdf_download_allowed'] = $dbPdfDownloadAllowed;
        }
    } else {
        $lessons[$k]['pdf_path'] = $dbPdfPath;
        $lessons[$k]['pdf_filename'] = $dbPdfFilename ?: 'dokument.pdf';
        $lessons[$k]['pdf_download_allowed'] = $dbPdfDownloadAllowed;
    }
}

$homeworkCount = count(array_filter($lessons, static fn($lesson) => ($lesson['lesson_type'] ?? '') === 'homework'));
$visibleQualifications = count(array_unique(array_filter(array_column($lessons, 'qualification'))));
$flash = getSessionMessage();
// Runtime detection exposed to DOM for safe debugging (no server logs)
$runtimeLessonHasPdfColumns = dbColumnExists($pdo, 'lessons', 'pdf_path')
    && dbColumnExists($pdo, 'lessons', 'pdf_filename')
    && dbColumnExists($pdo, 'lessons', 'pdf_download_allowed');
?>
<!doctype html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5, user-scalable=yes">
    <title>Lekcje - ZSEM Tech</title>
    <!-- PDF viewer v5: ostrzejsze renderowanie canvas na urządzeniach mobilnych -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <style>
        :root {
            --lesson-primary: #2563eb;
            --lesson-primary-soft: rgba(37, 99, 235, .10);
            --lesson-panel: #ffffff;
            --lesson-muted: #64748b;
            --lesson-border: rgba(148, 163, 184, .22);
            --lesson-shadow: 0 18px 55px rgba(15, 23, 42, .10);
            --pdf-bg: #eef2f7;
            --pdf-panel: #ffffff;
            --pdf-border: rgba(148, 163, 184, .24);
        }

        body.dark-mode {
            --lesson-panel: #1e293b;
            --lesson-muted: #94a3b8;
            --lesson-border: rgba(148, 163, 184, .28);
            --lesson-shadow: 0 18px 55px rgba(0, 0, 0, .28);
            --pdf-bg: #0f172a;
            --pdf-panel: #1e293b;
            --pdf-border: rgba(148, 163, 184, .28);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 0;
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            min-width: 0;
            overflow-x: hidden;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        body.modal-open {
            padding-right: 0 !important;
            overflow: hidden;
            touch-action: none;
        }

        .lessons-shell {
            max-width: 1320px;
            margin: 0 auto;
            width: 100%;
        }

        .lesson-row {
            border: 1px solid var(--lesson-border);
            border-radius: 18px;
            background: var(--lesson-panel);
            padding: 1rem;
            min-height: 168px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .04);
            overflow: hidden;
        }

        .lesson-body {
            color: var(--lesson-muted);
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            display: -webkit-box;
            -webkit-line-clamp: 8;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.68;
        }

        .lesson-row.is-expanded .lesson-body {
            display: block;
            -webkit-line-clamp: unset;
            overflow: visible;
        }

        .lesson-filters {
            position: sticky;
            top: 76px;
            z-index: 3;
            background: var(--panel-bg, #fff);
            border-radius: 18px;
        }

        textarea.lesson-body-input {
            min-height: 180px;
            max-height: 420px;
            resize: vertical;
        }

        .lesson-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .lesson-summary-card {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 18px;
            padding: 1rem;
            background: var(--panel-bg, #fff);
            box-shadow: 0 12px 34px rgba(15, 23, 42, .045);
        }

        .fw-900 {
            font-weight: 900;
        }

        @media (max-width: 768px) {
            body {
                touch-action: manipulation;
            }

            .content-body {
                overflow-x: hidden;
            }

            .lesson-row {
                border-radius: 16px;
                padding: .9rem;
            }

            .lesson-summary-grid {
                grid-template-columns: 1fr;
                gap: .75rem;
            }

            .lesson-filters {
                position: static;
            }
        }

        /* =========================
           PDF VIEWER — RESPONSIVE DISPLAY V4
           Cel: większe realne pole PDF na telefonach,
           kompaktowy pasek górny i brak ucinania strony.
           ========================= */
        #pdfViewerModal {
            --pdf-header-height: 58px;
            --pdf-footer-height: 0px;
            --pdf-toolbar-height: 58px;
            --pdf-stage-bg-1: #e7edf5;
            --pdf-stage-bg-2: #d8e2ee;
            --pdf-stage-bg-3: #cbd7e6;
        }

        #pdfViewerModal,
        #pdfViewerModal .modal-dialog,
        #pdfViewerModal .modal-content {
            width: 100vw;
            max-width: 100vw;
            height: 100vh;
            max-height: 100vh;
            margin: 0;
            overflow: hidden;
        }

        @supports (height: 100dvh) {
            #pdfViewerModal,
            #pdfViewerModal .modal-dialog,
            #pdfViewerModal .modal-content {
                height: 100dvh;
                max-height: 100dvh;
            }
        }

        #pdfViewerModal .modal-dialog {
            transform: none !important;
        }

        #pdfViewerModal .modal-content {
            position: relative;
            border: 0;
            border-radius: 0;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at 12% 0%, rgba(37, 99, 235, .10), transparent 28rem),
                linear-gradient(180deg, #f8fafc 0%, #edf2f7 100%);
        }

        #pdfViewerModal .modal-header {
            flex: 0 0 auto;
            min-height: var(--pdf-header-height);
            padding: .62rem clamp(.75rem, 1.5vw, 1.2rem);
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(148, 163, 184, .20) !important;
            box-shadow: 0 8px 26px rgba(15, 23, 42, .06);
            z-index: 20;
        }

        #pdfViewerModal .modal-title {
            min-width: 0;
            font-size: clamp(.95rem, 1.5vw, 1.08rem);
            display: flex;
            align-items: center;
            gap: .35rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #pdfViewerModal .btn-close {
            flex: 0 0 auto;
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 999px;
            background-color: #f1f5f9;
            opacity: 1;
            transition: transform .18s ease, background-color .18s ease;
        }

        #pdfViewerModal .btn-close:hover {
            transform: rotate(90deg);
            background-color: #e2e8f0;
        }

        #pdfViewerModal .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: .55rem;
            padding: .65rem clamp(.65rem, 1.25vw, 1rem) clamp(.65rem, 1.25vw, 1rem);
            overflow: hidden;
            background: transparent;
        }

        /* Stopka nie zabiera już wysokości podglądu. Jest pływająca jak w prawdziwym viewerze. */
        #pdfViewerModal .modal-footer {
            position: absolute;
            right: clamp(.85rem, 1.6vw, 1.4rem);
            bottom: calc(.85rem + env(safe-area-inset-bottom));
            z-index: 30;
            min-height: 0;
            padding: 0;
            margin: 0;
            background: transparent;
            border: 0 !important;
            box-shadow: none;
            gap: .55rem;
            pointer-events: none;
        }

        #pdfViewerModal .modal-footer .btn,
        #pdfViewerModal .modal-footer a.btn {
            pointer-events: auto;
            min-height: 44px;
            border-radius: 999px;
            font-weight: 900;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .22);
        }

        .pdf-controls {
            flex: 0 0 auto;
            width: 100%;
            max-width: min(100%, 1580px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: .7rem;
            padding: .48rem;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 18px;
            background: rgba(255, 255, 255, .90);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
            overflow: hidden;
        }

        .pdf-controls .btn-group[role="group"] {
            min-width: 0;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: .42rem;
            padding: .22rem;
            border-radius: 14px;
            background: #f1f5f9;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .pdf-controls .btn-group[role="group"]::-webkit-scrollbar {
            display: none;
        }

        .pdf-controls .btn {
            flex: 0 0 auto;
            min-width: 44px;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .5rem .72rem;
            border-radius: 12px !important;
            border: 1px solid rgba(148, 163, 184, .28);
            background: #ffffff;
            color: #334155;
            font-size: .9rem;
            font-weight: 900;
            line-height: 1;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .045);
            transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease, color .16s ease, border-color .16s ease;
            touch-action: manipulation;
        }

        .pdf-controls .btn:hover {
            transform: translateY(-1px);
            background: #eaf2ff;
            color: #1d4ed8;
            border-color: rgba(37, 99, 235, .35);
            box-shadow: 0 8px 18px rgba(37, 99, 235, .10);
        }

        .pdf-controls .btn:active {
            transform: translateY(0);
        }

        .pdf-controls .btn.is-active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            box-shadow: 0 10px 22px rgba(37, 99, 235, .24);
        }

        #pdfViewerStatus {
            min-width: max-content;
            margin: 0;
            padding-right: .2rem;
            color: #64748b;
            font-weight: 900;
            font-size: .86rem;
            white-space: nowrap;
        }

        .pdf-viewer-pages {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
            max-width: none;
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1.65rem;
            padding: clamp(1rem, 2vw, 1.8rem) clamp(.75rem, 2.5vw, 2rem) calc(5.4rem + env(safe-area-inset-bottom));
            border: 1px solid rgba(100, 116, 139, .20);
            border-radius: 22px;
            background:
                radial-gradient(circle at 50% -10%, rgba(255, 255, 255, .50), transparent 38rem),
                linear-gradient(180deg, var(--pdf-stage-bg-1) 0%, var(--pdf-stage-bg-2) 54%, var(--pdf-stage-bg-3) 100%);
            overflow: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            user-select: none;
            -webkit-user-select: none;
            scroll-behavior: smooth;
            scrollbar-gutter: stable;
        }

        .pdf-viewer-pages .pdf-loading,
        .pdf-viewer-pages .pdf-error {
            width: min(100%, 560px);
            margin: auto;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 20px;
            padding: 2rem 1rem;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 20px 56px rgba(15, 23, 42, .13);
            text-align: center;
        }

        .pdf-viewer-pages .page-wrapper {
            width: max-content;
            max-width: none;
            min-width: 0;
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0;
            margin: 0 auto;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            overflow: visible;
        }

        .pdf-viewer-pages .page-number {
            width: auto;
            margin: 0 0 .55rem;
            padding: .35rem .78rem;
            color: #e2e8f0;
            background: rgba(15, 23, 42, .68);
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 900;
            text-align: center;
            letter-spacing: .01em;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .16);
        }

        .pdf-viewer-pages canvas {
            display: block;
            width: auto;
            max-width: none;
            height: auto !important;
            margin: 0;
            border-radius: 3px;
            background: #ffffff;
            image-rendering: auto;
            transform: translateZ(0);
            backface-visibility: hidden;
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, .12),
                0 22px 70px rgba(15, 23, 42, .28);
            pointer-events: auto;
            -webkit-touch-callout: none;
            user-select: none;
        }

        .pdf-viewer-pages.pdf-fit-page .page-wrapper {
            margin-left: auto;
            margin-right: auto;
        }

        .pdf-viewer-pages.pdf-compact {
            gap: .9rem;
            padding-top: .8rem;
        }

        .pdf-viewer-pages.pdf-compact .page-number {
            display: none;
        }

        .pdf-viewer-pages.pdf-compact canvas {
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, .10),
                0 12px 38px rgba(15, 23, 42, .22);
        }

        @media (min-width: 1200px) {
            #pdfViewerModal .modal-body {
                padding-left: 1.15rem;
                padding-right: 1.15rem;
            }

            .pdf-viewer-pages {
                border-radius: 24px;
            }
        }

        @media (max-width: 768px) {
            #pdfViewerModal {
                --pdf-header-height: 52px;
            }

            #pdfViewerModal .modal-header {
                padding: .55rem .65rem;
            }

            #pdfViewerModal .modal-title {
                font-size: .94rem;
            }

            #pdfViewerModal .btn-close {
                width: 2rem;
                height: 2rem;
            }

            #pdfViewerModal .modal-body {
                gap: .45rem;
                padding: .45rem;
            }

            #pdfViewerModal .modal-footer {
                left: .55rem;
                right: .55rem;
                bottom: calc(.55rem + env(safe-area-inset-bottom));
                justify-content: flex-end;
            }

            #pdfViewerModal .modal-footer .btn,
            #pdfViewerModal .modal-footer a.btn {
                min-height: 44px;
                padding-left: 1rem;
                padding-right: 1rem;
                border-radius: 999px;
                font-weight: 900;
            }

            .pdf-controls {
                grid-template-columns: 1fr;
                gap: .38rem;
                padding: .38rem;
                border-radius: 16px;
            }

            .pdf-controls .btn-group[role="group"] {
                gap: .28rem;
                padding: .18rem;
                border-radius: 13px;
            }

            .pdf-controls .btn {
                min-width: 40px;
                min-height: 38px;
                padding: .42rem .56rem;
                font-size: .8rem;
                border-radius: 11px !important;
            }

            #pdfViewerStatus {
                min-width: 0;
                width: 100%;
                padding: 0 .15rem;
                font-size: .76rem;
                white-space: normal;
                text-align: left;
            }

            .pdf-viewer-pages {
                gap: 1rem;
                padding: .8rem .45rem calc(5rem + env(safe-area-inset-bottom));
                border-radius: 16px;
                scrollbar-gutter: auto;
            }

            .pdf-viewer-pages .page-number {
                margin-bottom: .38rem;
                padding: .28rem .62rem;
                font-size: .70rem;
            }

            .pdf-viewer-pages canvas {
                border-radius: 2px;
                box-shadow:
                    0 0 0 1px rgba(15, 23, 42, .10),
                    0 12px 40px rgba(15, 23, 42, .26);
            }
        }

        @media (max-width: 390px) {
            #pdfViewerModal .modal-title span.pdf-title-text {
                display: none;
            }

            .pdf-controls .btn {
                min-width: 37px;
                min-height: 36px;
                padding: .38rem .48rem;
                font-size: .75rem;
            }

            .pdf-viewer-pages {
                padding-left: .35rem;
                padding-right: .35rem;
            }
        }

        @media (max-height: 560px) and (orientation: landscape) {
            #pdfViewerModal {
                --pdf-header-height: 44px;
            }

            #pdfViewerModal .modal-header {
                padding: .38rem .62rem;
            }

            #pdfViewerModal .modal-body {
                padding: .35rem;
                gap: .35rem;
            }

            #pdfViewerModal .modal-footer {
                right: .55rem;
                bottom: calc(.55rem + env(safe-area-inset-bottom));
            }

            .pdf-controls {
                grid-template-columns: minmax(0, 1fr) auto;
                padding: .32rem;
            }

            .pdf-controls .btn {
                min-width: 36px;
                min-height: 32px;
                padding: .32rem .45rem;
                font-size: .72rem;
            }

            #pdfViewerStatus {
                font-size: .70rem;
                white-space: nowrap;
            }

            .pdf-viewer-pages {
                gap: .75rem;
                padding: .5rem .45rem calc(4.2rem + env(safe-area-inset-bottom));
                border-radius: 14px;
            }

            .pdf-viewer-pages .page-number {
                display: none;
            }
        }


        /* =========================
           V4 MOBILE COMPACT OVERRIDES
           Mniej miejsca na nagłówek i toolbar = większe pole PDF.
           ========================= */
        @media (max-width: 768px) {
            #pdfViewerModal {
                --pdf-header-height: 46px;
                --pdf-toolbar-height: 46px;
            }

            #pdfViewerModal .modal-header {
                min-height: var(--pdf-header-height);
                padding: .34rem .52rem;
                box-shadow: 0 4px 14px rgba(15, 23, 42, .055);
            }

            #pdfViewerModal .modal-title {
                font-size: .88rem;
                gap: .28rem;
                max-width: calc(100vw - 4rem);
            }

            #pdfViewerModal .modal-title i {
                font-size: .92rem;
            }

            #pdfViewerModal .btn-close {
                width: 1.86rem;
                height: 1.86rem;
                padding: .48rem;
                background-size: .74rem;
            }

            #pdfViewerModal .modal-body {
                gap: .30rem;
                padding: .30rem .34rem .34rem;
            }

            .pdf-controls {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: .38rem;
                padding: .28rem;
                border-radius: 13px;
                box-shadow: 0 6px 18px rgba(15, 23, 42, .055);
            }

            .pdf-controls .btn-group[role="group"] {
                width: 100%;
                min-width: 0;
                gap: .22rem;
                padding: .12rem;
                border-radius: 11px;
            }

            .pdf-controls .btn {
                min-width: 34px;
                min-height: 34px;
                padding: .32rem .42rem;
                font-size: .72rem;
                border-radius: 10px !important;
                box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
            }

            #pdfZoomReset {
                min-width: 56px;
                padding-left: .48rem;
                padding-right: .48rem;
            }

            #pdfViewerStatus {
                width: auto;
                min-width: max-content;
                max-width: 8.4rem;
                padding: 0 .18rem 0 0;
                font-size: .69rem;
                line-height: 1.05;
                white-space: normal;
                text-align: right;
            }

            .pdf-viewer-pages {
                gap: .74rem;
                padding: .46rem .32rem calc(4.35rem + env(safe-area-inset-bottom));
                border-radius: 14px;
            }

            .pdf-viewer-pages .page-number {
                margin-bottom: .26rem;
                padding: .24rem .56rem;
                font-size: .66rem;
                box-shadow: 0 6px 14px rgba(15, 23, 42, .12);
            }

            #pdfViewerModal .modal-footer {
                left: auto;
                right: .48rem;
                bottom: calc(.48rem + env(safe-area-inset-bottom));
            }

            #pdfViewerModal .modal-footer .btn,
            #pdfViewerModal .modal-footer a.btn {
                min-height: 40px;
                padding: .48rem .95rem;
                font-size: .86rem;
            }
        }

        @media (max-width: 430px) {
            #pdfViewerModal {
                --pdf-header-height: 42px;
            }

            #pdfViewerModal .modal-header {
                padding: .28rem .42rem;
            }

            #pdfViewerModal .modal-title {
                font-size: .82rem;
            }

            #pdfViewerModal .modal-title span.pdf-title-text {
                display: inline;
            }

            #pdfViewerModal .btn-close {
                width: 1.72rem;
                height: 1.72rem;
                background-size: .68rem;
            }

            .pdf-controls {
                grid-template-columns: minmax(0, 1fr);
                gap: .20rem;
                padding: .22rem;
                border-radius: 12px;
            }

            .pdf-controls .btn-group[role="group"] {
                gap: .18rem;
                padding: .08rem;
            }

            .pdf-controls .btn {
                min-width: 31px;
                min-height: 31px;
                padding: .28rem .34rem;
                font-size: .67rem;
                border-radius: 8px !important;
            }

            #pdfZoomReset {
                min-width: 50px;
            }

            #pdfViewerStatus {
                max-width: none;
                width: 100%;
                padding: 0 .12rem .02rem;
                text-align: left;
                font-size: .65rem;
                line-height: 1;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .pdf-viewer-pages {
                padding: .34rem .24rem calc(4.05rem + env(safe-area-inset-bottom));
                border-radius: 12px;
            }

            .pdf-viewer-pages .page-number {
                margin-bottom: .20rem;
                padding: .20rem .48rem;
                font-size: .62rem;
            }
        }

        @media (max-height: 680px) and (max-width: 768px) {
            #pdfViewerModal .modal-header {
                min-height: 40px;
                padding-top: .24rem;
                padding-bottom: .24rem;
            }

            #pdfViewerModal .modal-title {
                font-size: .80rem;
            }

            #pdfViewerModal .btn-close {
                width: 1.62rem;
                height: 1.62rem;
            }

            #pdfViewerModal .modal-body {
                padding: .22rem .26rem .28rem;
                gap: .22rem;
            }

            .pdf-controls {
                padding: .18rem;
            }

            .pdf-controls .btn {
                min-width: 30px;
                min-height: 30px;
                padding: .24rem .32rem;
                font-size: .64rem;
            }

            #pdfZoomReset {
                min-width: 48px;
            }

            #pdfViewerStatus {
                font-size: .62rem;
            }

            .pdf-viewer-pages {
                padding-top: .26rem;
                padding-left: .20rem;
                padding-right: .20rem;
            }
        }


        /* =========================
           V7 MOBILE HD NO-CUT MODE
           Domyślny tryb mobilny nie robi już sztucznego powiększenia strony ponad ekran.
           Dzięki temu PDF nie jest ucięty po prawej stronie. Ostrość podbijamy bitmapą HD,
           a nie powiększaniem CSS-canvasu poza viewport.
           ========================= */
        @media (max-width: 768px) {
            .pdf-viewer-pages.pdf-fit-width {
                align-items: center;
                overflow-x: hidden;
                overflow-y: auto;
                touch-action: pan-y pinch-zoom;
                scroll-padding: .35rem;
            }

            .pdf-viewer-pages.pdf-fit-width .page-wrapper {
                width: 100%;
                max-width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            .pdf-viewer-pages.pdf-fit-width .page-number {
                align-self: center;
                margin-left: 0;
            }

            .pdf-viewer-pages.pdf-fit-width canvas {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                image-rendering: auto;
            }

            .pdf-viewer-pages.pdf-fit-page,
            .pdf-viewer-pages.pdf-fit-height {
                align-items: center;
                overflow-x: hidden;
            }

            .pdf-viewer-pages.pdf-fit-page .page-wrapper,
            .pdf-viewer-pages.pdf-fit-height .page-wrapper {
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 768px) {
            .pdf-viewer-pages:not(.pdf-fit-width):not(.pdf-fit-page):not(.pdf-fit-height) {
                overflow-x: auto;
                touch-action: pan-x pan-y pinch-zoom;
            }
        }

    </style>
</head>
<body data-lesson-pdf-columns="<?php echo $runtimeLessonHasPdfColumns ? '1' : '0'; ?>">
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main class="content-body">
            <div class="container-fluid p-0 lessons-shell">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-easel2 text-primary me-2"></i>Lekcje</h2>
                        <p class="text-muted mb-0">Materiały i zadania domowe publikowane przez prowadzących.</p>
                    </div>
                </div>
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'error' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($flash['message'] ?? ''); ?></div>
                <?php endif; ?>

                <section class="lesson-summary-grid mb-4">
                    <div class="lesson-summary-card">
                        <div class="text-muted small fw-bold">Widoczne materiały</div>
                        <div class="h3 fw-900 mb-0"><?php echo number_format(count($lessons)); ?></div>
                    </div>
                    <div class="lesson-summary-card">
                        <div class="text-muted small fw-bold">Zadania domowe</div>
                        <div class="h3 fw-900 mb-0"><?php echo number_format($homeworkCount); ?></div>
                    </div>
                    <div class="lesson-summary-card">
                        <div class="text-muted small fw-bold">Kwalifikacje w filtrze</div>
                        <div class="h3 fw-900 mb-0"><?php echo number_format($visibleQualifications); ?></div>
                    </div>
                </section>

                <?php if ($canCreateLesson): ?>
                <div class="mb-3 text-end">
                    <button class="btn btn-primary rounded-pill px-4" type="button" data-lesson-create-toggle aria-expanded="false" aria-controls="lessonCreatePanel">
                        <i class="bi bi-plus-lg me-1"></i>Dodaj lekcję
                    </button>
                </div>
                <section class="dashboard-panel mb-4 d-none" id="lessonCreatePanel">
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <?php echo csrfTokenField('lessons'); ?>
                        <input type="hidden" name="action" value="create">
                        <div class="col-lg-5">
                            <label class="form-label fw-bold">Tytuł</label>
                            <input name="title" class="form-control" maxlength="160" required>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label fw-bold">Typ</label>
                            <select name="lesson_type" class="form-select">
                                <?php foreach ($types as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label fw-bold">Kwalifikacja</label>
                            <select name="qualification" class="form-select">
                                <?php foreach ($qualifications as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-bold">Termin zadania</label>
                            <input name="due_at" type="datetime-local" class="form-control">
                        </div>                        <div class="col-lg-6">
                            <label class="form-label fw-bold">Załącznik PDF</label>
                            <input type="file" name="pdf_file" accept="application/pdf" class="form-control">
                            <div class="form-text">Opcjonalnie dodaj plik PDF z materiałem lekcji.</div>
                        </div>
                        <div class="col-lg-6 d-flex align-items-center">
                            <div class="form-check mt-3 mt-lg-0">
                                <input class="form-check-input" type="checkbox" name="pdf_download_allowed" id="pdfDownloadAllowed">
                                <label class="form-check-label" for="pdfDownloadAllowed">Pozwól uczniom pobrać PDF</label>
                            </div>
                        </div>                        <div class="col-12">
                            <label class="form-label fw-bold">Treść</label>
                            <textarea name="body" class="form-control lesson-body-input" maxlength="9000" required></textarea>
                            <div class="form-text">Maksymalnie 1000 słów. Limit aktywnych lekcji na prowadzącego: 50.</div>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-send me-1"></i>Opublikuj</button>
                        </div>
                    </form>
                </section>
                <?php endif; ?>

                <section class="dashboard-panel lesson-filters mb-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-lg-6">
                            <label class="form-label fw-bold">Szukaj</label>
                            <input type="search" name="q" class="form-control" value="<?php echo htmlspecialchars($query); ?>" placeholder="Tytuł, treść albo autor">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-bold">Kwalifikacja</label>
                            <select name="qualification" class="form-select">
                                <option value="">Wszystkie</option>
                                <?php foreach ($qualifications as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $filterQual === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label fw-bold">Typ</label>
                            <select name="type" class="form-select">
                                <option value="">Wszystkie</option>
                                <?php foreach ($types as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo $filterType === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-1 d-grid"><button class="btn btn-primary" type="submit" title="Szukaj"><i class="bi bi-search"></i></button></div>
                        <?php if ($query !== '' || $filterQual !== '' || $filterType !== ''): ?>
                            <div class="col-12"><a class="btn btn-sm btn-light border rounded-pill" href="lessons.php"><i class="bi bi-x-lg me-1"></i>Wyczyść filtry</a></div>
                        <?php endif; ?>
                    </form>
                </section>

                <div class="vstack gap-3" id="lessonsList">
                    <?php foreach ($lessons as $idx => $lesson): ?>
                        <?php $teacher = ['username' => $lesson['username'], 'first_name' => $lesson['first_name'], 'last_name' => $lesson['last_name']]; ?>
                        <article class="lesson-row <?php echo $idx >= 50 ? 'd-none lesson-extra' : ''; ?>" data-lesson-row>
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                                <div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border rounded-pill"><?php echo htmlspecialchars($qualifications[$lesson['qualification']] ?? $lesson['qualification']); ?></span>
                                    <span class="badge <?php echo $lesson['lesson_type'] === 'homework' ? 'bg-warning text-dark' : 'bg-success'; ?> rounded-pill"><?php echo htmlspecialchars($types[$lesson['lesson_type']] ?? 'Lekcja'); ?></span>
                                    <h3 class="h5 fw-bold mt-2 mb-1"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                    <div class="small text-muted">Autor: <?php echo htmlspecialchars(userDisplayName($teacher)); ?> | <?php echo date('d.m.Y H:i', strtotime($lesson['created_at'])); ?></div>
                                </div>
                                <?php if (!empty($lesson['due_at'])): ?>
                                    <span class="badge bg-light text-dark border rounded-pill">Termin: <?php echo date('d.m.Y H:i', strtotime($lesson['due_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="lesson-body"><?php echo htmlspecialchars($lesson['body']); ?></div>                            <?php if (!empty($lesson['pdf_path'])): ?>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill" type="button" onclick="openLessonPdf(<?php echo (int)$lesson['id']; ?>, <?php echo (int)$lesson['pdf_download_allowed']; ?>)">
                                        <i class="bi bi-file-earmark-pdf me-1"></i>Otwórz PDF
                                    </button>
                                    <?php if ($canCreateLesson && ((int)$lesson['teacher_id'] === $userId || in_array($role, ['admin', 'dyrektor'], true))): ?>
                                        <form method="POST" class="d-inline">
                                            <?php echo csrfTokenField('lessons'); ?>
                                            <input type="hidden" name="action" value="toggle_pdf_download">
                                            <input type="hidden" name="lesson_id" value="<?php echo (int)$lesson['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo (int)$lesson['pdf_download_allowed'] === 1 ? 'btn-outline-warning' : 'btn-outline-success'; ?> rounded-pill">
                                                <i class="bi <?php echo (int)$lesson['pdf_download_allowed'] === 1 ? 'bi-lock' : 'bi-unlock'; ?> me-1"></i>
                                                <?php echo (int)$lesson['pdf_download_allowed'] === 1 ? 'Zablokuj pobieranie' : 'Zezwól na pobieranie'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int)$lesson['pdf_download_allowed'] === 1): ?>
                                        <a href="lesson_pdf.php?lesson_id=<?php echo (int)$lesson['id']; ?>&download=1" class="btn btn-sm btn-outline-success rounded-pill">
                                            <i class="bi bi-download me-1"></i>Pobierz PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill d-flex align-items-center">Pobieranie wyłączone</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>                            <button class="btn btn-sm btn-light border rounded-pill mt-3" type="button" data-expand-lesson>
                                <i class="bi bi-arrows-expand me-1"></i>Pokaż całość
                            </button>
                            <?php if ($canCreateLesson && (int)$lesson['teacher_id'] === $userId): ?>
                                <form method="POST" class="mt-3" onsubmit="return confirmArchiveLesson(this);">
                                    <?php echo csrfTokenField('lessons'); ?>
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="lesson_id" value="<?php echo (int)$lesson['id']; ?>">
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit"><i class="bi bi-archive me-1"></i>Archiwizuj</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($lessons)): ?>
                        <div class="dashboard-panel text-center text-muted py-5">Brak lekcji dla wybranych filtrów.</div>
                    <?php endif; ?>
                </div>
                <?php if (count($lessons) > 50): ?>
                    <div class="text-center mt-4">
                        <button class="btn btn-outline-primary rounded-pill px-4" id="loadMoreLessons" type="button" data-visible="50">
                            <i class="bi bi-chevron-down me-1"></i>Załaduj więcej
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<div class="modal fade" id="pdfViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf text-danger me-2"></i><span class="pdf-title-text">Podgląd PDF</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <div class="pdf-controls">
                    <div class="btn-group" role="group" aria-label="Zoom PDF">
                        <button type="button" class="btn" id="pdfFitPage" title="Dopasuj całą stronę">□</button>
                        <button type="button" class="btn" id="pdfFitWidth" title="Dopasuj do szerokości">↔</button>
                        <button type="button" class="btn" id="pdfFitHeight" title="Dopasuj do wysokości">↕</button>
                        <button type="button" class="btn" id="pdfZoomOut" title="Zmniejsz">−</button>
                        <button type="button" class="btn" id="pdfZoomReset" title="Resetuj zoom">100%</button>
                        <button type="button" class="btn" id="pdfZoomIn" title="Zwiększ">+</button>
                        <button type="button" class="btn" id="pdfToggleCompact" title="Widok kompaktowy">≡</button>
                        <button type="button" class="btn" id="pdfFullScreen" title="Pełny ekran">⛶</button>
                    </div>
                    <span id="pdfViewerStatus" class="text-muted small"></span>
                </div>
                <div id="pdfViewerPages" class="pdf-viewer-pages" oncontextmenu="return false;"></div>
            </div>
            <div class="modal-footer border-0">
                <a href="#" class="btn btn-outline-success d-none" id="pdfViewerDownload" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>Pobierz PDF</a>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="lessonArchiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive text-secondary me-2"></i>Archiwizować lekcję?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body pt-0 text-muted">Lekcja zniknie z listy widocznej dla uczniów. Nie usuwa to wpisu z bazy.</div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Wróć</button>
                <button type="button" class="btn btn-secondary rounded-pill px-4" id="lessonArchiveSubmit">Archiwizuj</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js" integrity="sha384-/1qUCSGwTur9vjf/z9lmu/eCUYbpOTgSjmpbMQZ1/CtX2v/WcAIKqRv+U1DUCG6e" crossorigin="anonymous"></script>
<script src="assets/js/theme-handler.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdfjs/pdf.worker.min.js';

let pendingLessonArchiveForm = null;

function confirmArchiveLesson(form) {
    pendingLessonArchiveForm = form;
    const modalEl = document.getElementById('lessonArchiveModal');

    if (modalEl && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        return false;
    }

    form.submit();
    return false;
}

document.getElementById('lessonArchiveSubmit')?.addEventListener('click', function () {
    if (pendingLessonArchiveForm) {
        pendingLessonArchiveForm.submit();
    }
});

document.getElementById('loadMoreLessons')?.addEventListener('click', function () {
    const rows = Array.from(document.querySelectorAll('[data-lesson-row]'));
    const next = Math.min(rows.length, (Number(this.dataset.visible) || 50) + 25);

    rows.forEach((row, index) => row.classList.toggle('d-none', index >= next));
    this.dataset.visible = String(next);

    if (next >= rows.length) {
        this.remove();
    }
});

document.querySelector('[data-lesson-create-toggle]')?.addEventListener('click', function () {
    const panel = document.getElementById('lessonCreatePanel');
    if (!panel) return;
    const expanded = panel.classList.toggle('d-none') === false;
    this.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    this.innerHTML = expanded
        ? '<i class="bi bi-dash-lg me-1"></i>Ukryj formularz'
        : '<i class="bi bi-plus-lg me-1"></i>Dodaj lekcję';
});

document.querySelectorAll('[data-expand-lesson]').forEach((button) => {
    button.addEventListener('click', () => {
        const row = button.closest('.lesson-row');
        const expanded = row?.classList.toggle('is-expanded');

        button.innerHTML = expanded
            ? '<i class="bi bi-arrows-collapse me-1"></i>Zwiń'
            : '<i class="bi bi-arrows-expand me-1"></i>Pokaż całość';
    });
});

window.lessonPdfViewer = window.lessonPdfViewer || {
    pdf: null,
    pages: [],
    scale: 1,
    lessonId: null,
    allowDownload: false,
    fitMode: 'width',
    renderToken: 0,
    renderTimer: null,
    isRendering: false,
    lastRenderedScale: 0
};

const pdfViewerModalEl = document.getElementById('pdfViewerModal');
const pdfPagesEl = document.getElementById('pdfViewerPages');
const pdfStatusEl = document.getElementById('pdfViewerStatus');
const pdfDownloadEl = document.getElementById('pdfViewerDownload');

function setPdfStatus(text) {
    if (pdfStatusEl) {
        pdfStatusEl.textContent = text || '';
    }
}

function getPdfPlural(count) {
    if (count === 1) {
        return 'strona';
    }

    if (count > 1 && count < 5) {
        return 'strony';
    }

    return 'stron';
}

function updatePdfControlsState() {
    const fitPage = document.getElementById('pdfFitPage');
    const fitWidth = document.getElementById('pdfFitWidth');
    const fitHeight = document.getElementById('pdfFitHeight');
    const pages = document.getElementById('pdfViewerPages');

    fitPage?.classList.toggle('is-active', lessonPdfViewer.fitMode === 'page');
    fitWidth?.classList.toggle('is-active', lessonPdfViewer.fitMode === 'width');
    fitHeight?.classList.toggle('is-active', lessonPdfViewer.fitMode === 'height');
    pages?.classList.toggle('pdf-fit-page', lessonPdfViewer.fitMode === 'page');
    pages?.classList.toggle('pdf-fit-width', lessonPdfViewer.fitMode === 'width');
}

function showPdfLoading(message = 'Ładowanie PDF...') {
    if (!pdfPagesEl) {
        return;
    }

    pdfPagesEl.innerHTML = `
        <div class="pdf-loading">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Ładowanie...</span>
            </div>
            <div class="fw-bold">${message}</div>
            <div class="small text-muted mt-1">Za chwilę dokument otworzy się w ostrym trybie mobilnym. Na telefonie możesz przesuwać stronę poziomo.</div>
        </div>
    `;
}

function showPdfError(message) {
    if (!pdfPagesEl) {
        return;
    }

    pdfPagesEl.innerHTML = `
        <div class="pdf-error">
            <i class="bi bi-exclamation-triangle text-danger fs-2 d-block mb-2"></i>
            <div class="fw-bold text-danger">Nie udało się wyświetlić PDF</div>
            <div class="small text-muted mt-2">${String(message || 'Wystąpił nieznany błąd.').replace(/[<>&"]/g, (char) => ({
                '<': '&lt;',
                '>': '&gt;',
                '&': '&amp;',
                '"': '&quot;'
            }[char]))}</div>
        </div>
    `;
}

function waitForNextFrame() {
    return new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
}


function formatPdfViewerStatus(pageCount) {
    const zoom = `${(window.lessonPdfViewer.scale * 100).toFixed(0)}%`;
    const smallScreen = window.innerWidth <= 430;
    if (smallScreen) {
        return `${pageCount} str. • ${zoom}`;
    }
    return `${pageCount} strona${pageCount !== 1 ? 'y' : ''} • Zoom: ${zoom}`;
}

async function openLessonPdf(lessonId, allowDownload) {
    lessonPdfViewer.renderToken++;
    lessonPdfViewer.pdf = null;
    lessonPdfViewer.pages = [];
    lessonPdfViewer.lessonId = Number(lessonId);
    lessonPdfViewer.allowDownload = Boolean(Number(allowDownload));
    lessonPdfViewer.scale = 1;
    lessonPdfViewer.fitMode = getInitialPdfFitMode();

    if (pdfPagesEl) {
        pdfPagesEl.classList.remove('pdf-compact');
        showPdfLoading();
    }

    setPdfStatus('Ładowanie PDF...');

    if (pdfDownloadEl) {
        pdfDownloadEl.classList.toggle('d-none', !lessonPdfViewer.allowDownload);
        pdfDownloadEl.href = lessonPdfViewer.allowDownload
            ? `lesson_pdf.php?lesson_id=${encodeURIComponent(lessonId)}&download=1`
            : '#';
    }

    if (pdfViewerModalEl && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(pdfViewerModalEl, {
            backdrop: true,
            keyboard: true,
            focus: true
        }).show();
    }

    try {
        await waitForNextFrame();
        await loadLessonPdf(lessonId);
        await waitForNextFrame();
        setPdfFit(getInitialPdfFitMode());
    } catch (error) {
        showPdfError(error.message);
        setPdfStatus('');
    }
}

async function loadLessonPdf(lessonId) {
    const response = await fetch(`lesson_pdf.php?lesson_id=${encodeURIComponent(lessonId)}`, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            'Accept': 'application/pdf,application/octet-stream;q=0.9,*/*;q=0.8'
        }
    });

    if (!response.ok) {
        throw new Error('Nie udało się pobrać pliku PDF. Status HTTP: ' + response.status + '.');
    }

    const contentType = response.headers.get('content-type') || '';

    if (contentType.includes('text/html') || response.url.includes('login.php')) {
        throw new Error('Serwer zwrócił HTML zamiast PDF. Najczęściej oznacza to wygaśniętą sesję albo brak dostępu do pliku.');
    }

    const arrayBuffer = await response.arrayBuffer();

    if (!arrayBuffer || arrayBuffer.byteLength < 4) {
        throw new Error('Plik PDF jest pusty albo uszkodzony.');
    }

    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

    lessonPdfViewer.pdf = pdf;
    lessonPdfViewer.pages = [];

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        lessonPdfViewer.pages.push(page);
    }

    if (!pdfPagesEl) {
        return;
    }

    pdfPagesEl.innerHTML = '';
    setPdfStatus(`${pdf.numPages} ${getPdfPlural(pdf.numPages)} • Dopasowywanie...`);
}

function getInitialPdfFitMode() {
    // Domyślnie pokazujemy PDF jak normalny czytnik: duży dokument na szerokość.
    // Przycisk □ dalej pozwala wymusić widok całej strony, gdy komuś jest potrzebny.
    return 'width';
}

function clampNumber(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function computeFitScale(mode) {
    const pages = lessonPdfViewer.pages;

    if (!pages || pages.length === 0) {
        return lessonPdfViewer.scale || 1;
    }

    const pagesContainer = document.getElementById('pdfViewerPages');

    if (!pagesContainer) {
        return lessonPdfViewer.scale || 1;
    }

    const pageAt1 = pages[0].getViewport({ scale: 1 });
    const containerStyles = window.getComputedStyle(pagesContainer);
    const paddingX = parseFloat(containerStyles.paddingLeft || '0') + parseFloat(containerStyles.paddingRight || '0');
    const paddingY = parseFloat(containerStyles.paddingTop || '0') + parseFloat(containerStyles.paddingBottom || '0');
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    const isSmallPhone = window.matchMedia('(max-width: 390px)').matches;
    const isLandscapeCompact = window.matchMedia('(max-height: 560px) and (orientation: landscape)').matches;
    const isWideDesktop = window.matchMedia('(min-width: 1800px)').matches;

    const sideComfort = isSmallPhone ? 2 : (isMobile ? 6 : 24);
    const verticalComfort = isLandscapeCompact ? 6 : (isMobile ? 20 : 34);

    const availableWidth = Math.max(180, pagesContainer.clientWidth - paddingX - sideComfort);
    const availableHeight = Math.max(160, pagesContainer.clientHeight - paddingY - verticalComfort);

    const widthScale = availableWidth / pageAt1.width;
    const heightScale = availableHeight / pageAt1.height;

    if (mode === 'height') {
        return clampNumber(heightScale, 0.20, isMobile ? 3.5 : 4);
    }

    if (mode === 'page') {
        return clampNumber(Math.min(widthScale, heightScale), 0.20, isMobile ? 3.5 : 4);
    }

    if (mode === 'width') {
        if (isMobile) {
            // V7: bez ucinania po bokach.
            // Dopasowujemy CSS canvas dokładnie do widocznej szerokości kontenera,
            // a jakość poprawiamy wyższą rozdzielczością bitmapy w computePdfRenderOutputScale().
            const mobileWidthScale = widthScale;
            const mobileMaxScale = isLandscapeCompact ? 1.05 : 1.12;

            return clampNumber(mobileWidthScale, 0.35, mobileMaxScale);
        }

        let maxComfortableWidthScale = 1.16;

        if (isWideDesktop) {
            maxComfortableWidthScale = 1.28;
        }

        if (isLandscapeCompact) {
            maxComfortableWidthScale = 1.05;
        }

        return clampNumber(Math.min(widthScale, maxComfortableWidthScale), 0.20, 4);
    }

    return lessonPdfViewer.scale || 1;
}

function setPdfFit(mode) {
    const nextMode = ['page', 'width', 'height'].includes(mode) ? mode : 'page';
    lessonPdfViewer.fitMode = nextMode;
    updatePdfControlsState();

    const newScale = computeFitScale(nextMode);
    setPdfZoom(newScale, true);
}

function setPdfZoom(newScale, keepFitMode = false) {
    if (!Number.isFinite(newScale)) {
        return;
    }

    if (!keepFitMode) {
        lessonPdfViewer.fitMode = 'none';
        updatePdfControlsState();
    }

    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    lessonPdfViewer.scale = clampNumber(newScale, 0.22, isMobile ? 6 : 4);
    schedulePdfRender(40);
}

function schedulePdfRender(delay = 0) {
    clearTimeout(lessonPdfViewer.renderTimer);

    lessonPdfViewer.renderTimer = setTimeout(() => {
        renderPdfPages();
    }, delay);
}

function computePdfRenderOutputScale(viewport) {
    const dpr = Math.max(1, window.devicePixelRatio || 1);
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    const isTinyPhone = window.matchMedia('(max-width: 390px)').matches;

    // Renderujemy canvas w większej bitmapie niż jego rozmiar CSS.
    // Na mobile to robi największą różnicę, bo przeglądarka wyświetla dużo małych liter i screenów.
    let qualityBoost = 1;

    if (isMobile) {
        if (lessonPdfViewer.fitMode === 'width') {
            // Po powrocie do trybu bez ucinania CSS-canvas jest mniejszy,
            // więc renderujemy agresywniejszą bitmapę HD. To poprawia ostrość bez robienia poziomego cięcia.
            qualityBoost = 1.75;
        } else if (lessonPdfViewer.scale < 0.9) {
            qualityBoost = 1.55;
        } else {
            qualityBoost = 1.35;
        }
    }

    let outputScale = dpr * qualityBoost;

    if (isMobile && outputScale < 3.2) {
        outputScale = 3.2;
    }

    outputScale = Math.min(outputScale, isMobile ? 5.25 : 2.5);

    const maxCanvasPixels = isMobile ? (isTinyPhone ? 18_000_000 : 24_000_000) : 12_000_000;
    const estimatedPixels = viewport.width * viewport.height * outputScale * outputScale;

    if (estimatedPixels > maxCanvasPixels) {
        outputScale *= Math.sqrt(maxCanvasPixels / estimatedPixels);
    }

    return Math.max(1, outputScale);
}

async function renderPdfPages() {
    const container = document.getElementById('pdfViewerPages');
    const pdf = lessonPdfViewer.pdf;

    if (!container || !pdf || !lessonPdfViewer.pages.length) {
        return;
    }

    const renderToken = ++lessonPdfViewer.renderToken;
    lessonPdfViewer.isRendering = true;
    lessonPdfViewer.lastRenderedScale = lessonPdfViewer.scale;

    container.innerHTML = '';

    const pageCount = lessonPdfViewer.pages.length;
    setPdfStatus(`${pageCount} ${getPdfPlural(pageCount)} • Renderowanie...`);

    for (let index = 0; index < pageCount; index++) {
        if (renderToken !== lessonPdfViewer.renderToken) {
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 0));

        const page = lessonPdfViewer.pages[index];
        const viewport = page.getViewport({ scale: lessonPdfViewer.scale });
        const outputScale = computePdfRenderOutputScale(viewport);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { alpha: false, desynchronized: true });

        canvas.width = Math.ceil(viewport.width * outputScale);
        canvas.height = Math.ceil(viewport.height * outputScale);
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;
        canvas.dataset.renderScale = outputScale.toFixed(2);
        canvas.setAttribute('aria-label', `Strona ${index + 1} z ${pageCount}`);

        if (context) {
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
        }

        const pageWrapper = document.createElement('div');
        pageWrapper.className = 'page-wrapper';

        const title = document.createElement('div');
        title.className = 'page-number';
        title.textContent = `Strona ${index + 1} z ${pageCount}`;

        pageWrapper.append(title, canvas);
        container.append(pageWrapper);

        if (!context) {
            throw new Error('Przeglądarka nie udostępniła kontekstu renderowania PDF.');
        }

        const renderContext = {
            canvasContext: context,
            viewport,
            transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
        };

        try {
            await page.render(renderContext).promise;
        } catch (error) {
            if (renderToken === lessonPdfViewer.renderToken) {
                throw error;
            }

            return;
        }

        setPdfStatus(`${pageCount} ${getPdfPlural(pageCount)} • Renderowanie HD: ${index + 1}/${pageCount} • Zoom: ${(lessonPdfViewer.scale * 100).toFixed(0)}%`);
    }

    if (renderToken !== lessonPdfViewer.renderToken) {
        return;
    }

    lessonPdfViewer.isRendering = false;

    if (container && window.matchMedia('(max-width: 768px)').matches && lessonPdfViewer.fitMode === 'width') {
        container.scrollLeft = 0;
    }

    const zoomText = `${pageCount} ${getPdfPlural(pageCount)} • Zoom: ${(lessonPdfViewer.scale * 100).toFixed(0)}%`;
    setPdfStatus(zoomText);
}

document.getElementById('pdfFitPage')?.addEventListener('click', () => setPdfFit('page'));
document.getElementById('pdfFitWidth')?.addEventListener('click', () => setPdfFit('width'));
document.getElementById('pdfFitHeight')?.addEventListener('click', () => setPdfFit('height'));

document.getElementById('pdfZoomOut')?.addEventListener('click', () => {
    setPdfZoom(lessonPdfViewer.scale - 0.15);
});

document.getElementById('pdfZoomIn')?.addEventListener('click', () => {
    setPdfZoom(lessonPdfViewer.scale + 0.15);
});

document.getElementById('pdfZoomReset')?.addEventListener('click', () => {
    setPdfZoom(1);
});

document.getElementById('pdfToggleCompact')?.addEventListener('click', () => {
    const pages = document.getElementById('pdfViewerPages');

    if (!pages) {
        return;
    }

    pages.classList.toggle('pdf-compact');

    if (lessonPdfViewer.fitMode !== 'none') {
        window.setTimeout(() => setPdfFit(lessonPdfViewer.fitMode), 60);
    }
});

document.getElementById('pdfFullScreen')?.addEventListener('click', async () => {
    const target = document.getElementById('pdfViewerModal')?.querySelector('.modal-content');

    if (!target) {
        return;
    }

    try {
        if (!document.fullscreenElement) {
            await target.requestFullscreen?.();
        } else {
            await document.exitFullscreen?.();
        }
    } catch (error) {
        console.warn('Fullscreen request failed:', error);
    }
});

let pdfResizeTimer = null;

function handlePdfViewportChange() {
    clearTimeout(pdfResizeTimer);

    pdfResizeTimer = setTimeout(() => {
        if (!lessonPdfViewer.pdf || lessonPdfViewer.fitMode === 'none') {
            return;
        }

        setPdfFit(lessonPdfViewer.fitMode);
    }, 180);
}

window.addEventListener('resize', handlePdfViewportChange, { passive: true });
window.addEventListener('orientationchange', () => window.setTimeout(handlePdfViewportChange, 260), { passive: true });

pdfViewerModalEl?.addEventListener('shown.bs.modal', () => {
    if (lessonPdfViewer.pdf && lessonPdfViewer.fitMode !== 'none') {
        window.setTimeout(() => setPdfFit(lessonPdfViewer.fitMode), 80);
    }
});

pdfViewerModalEl?.addEventListener('hidden.bs.modal', () => {
    lessonPdfViewer.renderToken++;
    lessonPdfViewer.pdf = null;
    lessonPdfViewer.pages = [];
    lessonPdfViewer.scale = 1;
    lessonPdfViewer.fitMode = getInitialPdfFitMode();
    lessonPdfViewer.isRendering = false;
    clearTimeout(lessonPdfViewer.renderTimer);
    clearTimeout(pdfResizeTimer);

    if (pdfPagesEl) {
        pdfPagesEl.innerHTML = '';
        pdfPagesEl.classList.remove('pdf-compact');
    }

    setPdfStatus('');
    updatePdfControlsState();

    if (document.fullscreenElement) {
        document.exitFullscreen?.().catch(() => {});
    }
});

document.addEventListener('keydown', (event) => {
    const modalIsOpen = pdfViewerModalEl?.classList.contains('show');

    if (!modalIsOpen || !lessonPdfViewer.pdf) {
        return;
    }

    if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        setPdfZoom(lessonPdfViewer.scale + 0.15);
    }

    if (event.key === '-') {
        event.preventDefault();
        setPdfZoom(lessonPdfViewer.scale - 0.15);
    }

    if (event.key === '0') {
        event.preventDefault();
        setPdfZoom(1);
    }

    if (event.key.toLowerCase() === 'w') {
        event.preventDefault();
        setPdfFit('width');
    }

    if (event.key.toLowerCase() === 'h') {
        event.preventDefault();
        setPdfFit('height');
    }
});

updatePdfControlsState();
</script>
</body>
</html>
