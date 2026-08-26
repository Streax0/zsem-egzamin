<?php
/**
 * Question Bookmarking Endpoint ("Zapisane pytania")
 *
 * Accepts POST {question_id, action}
 * Toggles or sets bookmark state in user_bookmarks.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    securitySendJson(['success' => false, 'error' => 'Wymagane zalogowanie.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'error' => 'Metoda niedozwolona.'], 405);
}

requireJsonCsrfToken();

$userId     = (int)$_SESSION['user_id'];
$questionId = (int)($_POST['question_id'] ?? 0);
$action     = trim((string)($_POST['action'] ?? 'toggle'));

if ($questionId <= 0) {
    securitySendJson(['success' => false, 'error' => 'Nieprawidłowy identyfikator pytania.'], 400);
}

try {
    if (function_exists('appRuntimeSchemaUpdatesEnabled') && appRuntimeSchemaUpdatesEnabled()) {
        $pdo->exec("CREATE" . " TABLE IF NOT EXISTS user_bookmarks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            question_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_q (user_id, question_id),
            INDEX idx_ub_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $chkStmt = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = ? AND question_id = ?");
    $chkStmt->execute([$userId, $questionId]);
    $exists = (bool)$chkStmt->fetchColumn();

    $isBookmarked = false;

    if ($action === 'toggle') {
        if ($exists) {
            $pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = ? AND question_id = ?")->execute([$userId, $questionId]);
            $isBookmarked = false;
        } else {
            $pdo->prepare("INSERT INTO user_bookmarks (user_id, question_id) VALUES (?, ?)")->execute([$userId, $questionId]);
            $isBookmarked = true;
        }
    } elseif ($action === 'add' && !$exists) {
        $pdo->prepare("INSERT INTO user_bookmarks (user_id, question_id) VALUES (?, ?)")->execute([$userId, $questionId]);
        $isBookmarked = true;
    } elseif ($action === 'remove' && $exists) {
        $pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = ? AND question_id = ?")->execute([$userId, $questionId]);
        $isBookmarked = false;
    } else {
        $isBookmarked = $exists;
    }

    securitySendJson([
        'success'       => true,
        'question_id'   => $questionId,
        'is_bookmarked' => $isBookmarked,
        'message'       => $isBookmarked ? 'Pytanie dodano do zapisanych.' : 'Pytanie usunięto z zapisanych.',
    ]);
} catch (Throwable $e) {
    securitySendJson(['success' => false, 'error' => 'Błąd zapisu zakładki.'], 500);
}
