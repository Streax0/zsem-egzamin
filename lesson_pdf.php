<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$downloadRequested = isset($_GET['download']) && $_GET['download'] === '1';

if ($lessonId <= 0) {
    http_response_code(400);
    exit('Nieprawidłowe ID lekcji.');
}

// Check lesson exists and is published
$stmt = $pdo->prepare('SELECT id, status, teacher_id FROM lessons WHERE id = ? LIMIT 1');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson || $lesson['status'] !== 'published') {
    http_response_code(404);
    exit('Lekcja nie istnieje lub nie jest opublikowana.');
}

// --- Resolve file path ---
$pdfDir = __DIR__ . DIRECTORY_SEPARATOR . 'pdf';
$pdfFile = $pdfDir . DIRECTORY_SEPARATOR . 'lesson_' . $lessonId . '.pdf';
$jsonFile = $pdfDir . DIRECTORY_SEPARATOR . 'lesson_' . $lessonId . '.json';

$downloadAllowed = false;
$filename = 'dokument.pdf';

// If the exact file doesn't exist, attempt to find an uploaded file with a random suffix:
// e.g. lesson_123_abcd.pdf — uploaded filenames may include a random suffix.
$globPattern = $pdfDir . DIRECTORY_SEPARATOR . 'lesson_' . $lessonId . '_*.pdf';
$globMatches = glob($globPattern);
if ((!file_exists($pdfFile) || !is_file($pdfFile)) && !empty($globMatches)) {
    // Pick the first matching file as the canonical PDF for this lesson
    $pdfFile = $globMatches[0];
    // Prefer a JSON meta file with the same base name if present
    $jsonCandidate = preg_replace('/\.pdf$/i', '.json', $pdfFile);
    if (file_exists($jsonCandidate) && is_file($jsonCandidate)) {
        $jsonFile = $jsonCandidate;
    } else {
        // fallback to legacy JSON name
        $jsonFile = $pdfDir . DIRECTORY_SEPARATOR . 'lesson_' . $lessonId . '.json';
    }
}

if (file_exists($pdfFile) && is_file($pdfFile)) {
    // Standard new-style upload found
    if (file_exists($jsonFile)) {
        $meta = json_decode(file_get_contents($jsonFile), true);
        if (is_array($meta)) {
            $downloadAllowed = (int)($meta['pdf_download_allowed'] ?? 0) === 1;
            if (!empty($meta['pdf_filename'])) {
                $filename = basename($meta['pdf_filename']);
            }
        }
    }
} else {
    // Fallback: check database for old-style uploads
    $hasPdfPath = dbColumnExists($pdo, 'lessons', 'pdf_path');

    if ($hasPdfPath) {
        $stmt2 = $pdo->prepare('SELECT pdf_path, pdf_filename, pdf_download_allowed FROM lessons WHERE id = ? LIMIT 1');
        $stmt2->execute([$lessonId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['pdf_path'])) {
            $candidate = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['pdf_path']);
            if (file_exists($candidate) && is_file($candidate)) {
                $pdfFile = $candidate;
                $downloadAllowed = (int)($row['pdf_download_allowed'] ?? 0) === 1;
                $filename = !empty($row['pdf_filename']) ? basename($row['pdf_filename']) : 'dokument.pdf';
            } else {
                http_response_code(404);
                exit('Plik PDF nie został znaleziony.');
            }
        } else {
            http_response_code(404);
            exit('Brak załączonego pliku PDF.');
        }
    } else {
        http_response_code(404);
        exit('Brak załączonego pliku PDF.');
    }
}

// Never serve a path outside the dedicated PDF directory, including symlinks
// or legacy database values containing traversal segments.
$pdfRoot = realpath($pdfDir);
$resolvedPdfFile = realpath($pdfFile);
$pdfPrefix = $pdfRoot !== false ? rtrim($pdfRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if (
    $pdfRoot === false
    || $resolvedPdfFile === false
    || strncmp($resolvedPdfFile, $pdfPrefix, strlen($pdfPrefix)) !== 0
    || !is_file($resolvedPdfFile)
) {
    http_response_code(404);
    exit('Plik PDF nie zostaÅ‚ znaleziony.');
}
$pdfFile = $resolvedPdfFile;

// Block download if not allowed
if ($downloadRequested && !$downloadAllowed) {
    http_response_code(403);
    exit('Pobieranie tego pliku PDF jest wyłączone przez autora lekcji.');
}

// Serve the file
$disposition = ($downloadAllowed && $downloadRequested) ? 'attachment' : 'inline';
$safeFilename = preg_replace('/[^\w\-. ]/u', '_', $filename);

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
header('Content-Length: ' . filesize($pdfFile));
header('Accept-Ranges: none');
header('Cache-Control: private, no-store, no-cache');
header('Pragma: no-cache');
readfile($pdfFile);
exit;
