<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin(true);

$pdfFiles = [
    '2025-cze' => 'inf02_2025_cze.pdf',
    '2024-cze' => 'inf02_2024_cze.pdf',
    '2024-sty' => 'inf02_2024_sty.pdf',
    '2023-cze' => 'inf02_2023_cze.pdf',
    '2023-sty' => 'inf02_2023_sty.pdf',
    '2022-cze' => 'inf02_2022_cze.pdf',
    '2022-sty' => 'inf02_2022_sty.pdf',
    '2021-cze' => 'inf02_2021_cze.pdf',
    'cke' => 'inf02_cke_2026.pdf',
];

$sessionKey = isset($_GET['session']) ? (string)$_GET['session'] : '2025-cze';
if (!isset($pdfFiles[$sessionKey])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unknown exam session.';
    exit;
}

$baseDir = realpath(__DIR__ . '/data/pdfs');
$fileName = $pdfFiles[$sessionKey];
$filePath = $baseDir ? realpath($baseDir . DIRECTORY_SEPARATOR . $fileName) : false;
$basePrefix = $baseDir ? $baseDir . DIRECTORY_SEPARATOR : '';

if (
    $baseDir === false ||
    $filePath === false ||
    strncmp($filePath, $basePrefix, strlen($basePrefix)) !== 0 ||
    !is_file($filePath)
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF not found.';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . (string)filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
