<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
startSecureSession();

requireJsonLogin(false, [], ['success' => false, 'message' => 'Unauthorized'], ['success' => false, 'message' => 'Unauthorized']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $bio = $_POST['bio'] ?? '';
    $userId = $_SESSION['user_id'];
    
    // Simple validation
    $bio = trim($bio);
    if (mb_strlen($bio) > 160) {
        echo json_encode(['success' => false, 'message' => 'Opis jest za długi (max 160 znaków)']);
        exit();
    }
    if (containsProfanity($bio)) {
        echo json_encode(['success' => false, 'message' => 'Opis zawiera niedozwolone słowa.']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
        $stmt->execute([$bio, $userId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Błąd bazy danych']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
}
