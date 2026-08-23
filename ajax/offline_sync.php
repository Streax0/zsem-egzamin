<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'message' => 'Wymagane zapytanie POST.'], 405);
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    securitySendJson(['success' => false, 'message' => 'Wymagane logowanie do synchronizacji.'], 401);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$csrfToken = (string)($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '')));
if (!validateCsrfToken($csrfToken) && !securityValidateRequestCsrf()) {
    securitySendJson(['success' => false, 'message' => 'Nieprawidłowy token CSRF.'], 403);
}

$items = is_array($data['sync_items'] ?? null) ? $data['sync_items'] : [];
$processed = 0;

foreach (array_slice($items, 0, 50) as $item) {
    if (!is_array($item)) continue;
    $type = (string)($item['type'] ?? '');
    
    if ($type === 'sm2_review') {
        $cardKey = trim((string)($item['card_key'] ?? ''));
        $rating = max(0, min(3, (int)($item['rating'] ?? 2)));
        if ($cardKey !== '') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO flashcard_sm2 (user_id, card_key, easiness_factor, interval_days, repetition_count, next_review_date, last_rating, updated_at)
                    VALUES (:uid, :ckey, 2.5, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), :rating, NOW())
                    ON DUPLICATE KEY UPDATE
                        last_rating = :rating2,
                        updated_at = NOW()
                ");
                $stmt->execute([
                    'uid' => (int)$currentUser['id'],
                    'ckey' => $cardKey,
                    'rating' => $rating,
                    'rating2' => $rating,
                ]);
                $processed++;
            } catch (Throwable $e) {}
        }
    }
}

securitySendJson([
    'success' => true,
    'synced_count' => $processed,
    'synced_at' => date('c'),
    'message' => 'Synchronizacja offline zakończona sukcesem (' . $processed . ' elementów).',
], 200);
