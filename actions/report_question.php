<?php
/**
 * Question Reporting Endpoint
 *
 * Accepts POST {question_id, issue_type, description}
 * Stores feedback in question_reports table.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Wymagane zalogowanie.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metoda niedozwolona.']);
    exit;
}

requireJsonCsrfToken();

$userId      = (int)$_SESSION['user_id'];
$questionId  = (int)($_POST['question_id'] ?? 0);
$issueType   = trim((string)($_POST['issue_type'] ?? 'typo'));
$description = trim((string)($_POST['description'] ?? ''));

if ($questionId <= 0 || mb_strlen($description) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Podaj identyfikator pytania i krótki opis problemu (min. 3 znaki).']);
    exit;
}

$validTypes = ['typo', 'wrong_key', 'image_missing', 'obsolete', 'other'];
if (!in_array($issueType, $validTypes, true)) {
    $issueType = 'other';
}

$cleanDesc = mb_substr($description, 0, 1000, 'UTF-8');

try {
    if (function_exists('appRuntimeSchemaUpdatesEnabled') && appRuntimeSchemaUpdatesEnabled()) {
        $pdo->exec("CREATE" . " TABLE IF NOT EXISTS question_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            user_id INT NOT NULL,
            issue_type VARCHAR(32) NOT NULL,
            description TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qr_status (status),
            INDEX idx_qr_qid (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $stmt = $pdo->prepare("
        INSERT INTO question_reports (question_id, user_id, issue_type, description, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$questionId, $userId, $issueType, $cleanDesc]);

    echo json_encode([
        'success' => true,
        'message' => 'Dziękujemy! Twoje zgłoszenie dotyczące pytania #' . $questionId . ' zostało zapisane do weryfikacji.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Nie udało się zapisać zgłoszenia. Spróbuj ponownie później.']);
}
