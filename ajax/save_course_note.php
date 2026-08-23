<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'message' => 'Wymagane zapytanie POST.'], 405);
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    securitySendJson(['success' => false, 'message' => 'Wymagane logowanie.'], 401);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$itemId = (int)($data['item_id'] ?? 0);
$courseId = (int)($data['course_id'] ?? 0);
$noteContent = trim((string)($data['note'] ?? ''));

if ($itemId <= 0) {
    securitySendJson(['success' => false, 'message' => 'Nieprawidłowe ID lekcji.'], 422);
}

$notesDir = __DIR__ . '/../data/user_notes/' . (int)$currentUser['id'];
if (!is_dir($notesDir)) {
    @mkdir($notesDir, 0775, true);
}

$noteFile = $notesDir . '/item_' . $itemId . '.json';

if (isset($data['action']) && $data['action'] === 'load') {
    $savedNote = '';
    if (file_exists($noteFile)) {
        $fileContent = @file_get_contents($noteFile);
        if ($fileContent) {
            $parsed = json_decode($fileContent, true);
            $savedNote = (string)($parsed['note'] ?? '');
        }
    }
    securitySendJson(['success' => true, 'note' => $savedNote], 200);
}

// Sanitize note length (up to 10,000 characters)
$cleanNote = mb_substr($noteContent, 0, 10000, 'UTF-8');
$payload = [
    'user_id' => (int)$currentUser['id'],
    'course_id' => $courseId,
    'item_id' => $itemId,
    'note' => $cleanNote,
    'updated_at' => date('Y-m-d H:i:s'),
];

$written = @file_put_contents($noteFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

securitySendJson([
    'success' => $written !== false,
    'message' => $written !== false ? 'Notatka zapisana pomyślnie.' : 'Błąd zapisu notatki.',
    'updated_at' => $payload['updated_at'],
], 200);
