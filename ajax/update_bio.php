<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(false, [], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!securityValidateRequestCsrf()) {
        echo securityJsonEncode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    $limit = securityConsumeRateLimit('update-bio:' . securityActorKey(), 20, 60);
    if (empty($limit['allowed'])) {
        http_response_code(429);
        echo securityJsonEncode(['success' => false, 'message' => 'Zbyt wiele zmian naraz.', 'retry_after' => (int)($limit['retry_after'] ?? 0)]);
        exit();
    }
    
    $bio = securityInputString($_POST['bio'] ?? '', 180);
    $userId = (int)$_SESSION['user_id'];
    
    // Simple validation
    $bio = trim($bio);
    if (mb_strlen($bio) > 160) {
        echo securityJsonEncode(['success' => false, 'message' => 'Opis jest za długi (max 160 znaków)']);
        exit();
    }
    if (containsProfanity($bio)) {
        echo securityJsonEncode(['success' => false, 'message' => 'Opis zawiera niedozwolone słowa.']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $stmt->execute([$bio, $userId]);
        echo securityJsonEncode(['success' => true]);
    } catch (PDOException $e) {
        securityAudit('update_bio_failed', ['user_id' => $userId], 'error');
        echo securityJsonEncode(['success' => false, 'message' => 'Błąd bazy danych']);
    }
} else {
    echo securityJsonEncode(['success' => false, 'message' => 'Invalid method']);
}
