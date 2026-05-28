<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

startSecureSession();

$token = $_GET['token'] ?? '';
$message = '';
$type = 'danger';

if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            $message = "Twoje konto zostało pomyślnie zweryfikowane! Możesz się teraz zalogować.";
            $type = "success";
        } else {
            $message = "Nieprawidłowy lub wygasły token weryfikacyjny.";
        }
    } catch (PDOException $e) {
        $message = "Wystąpił błąd podczas weryfikacji. Spróbuj ponownie później.";
    }
} else {
    $message = "Brak tokena weryfikacyjnego.";
}

setSessionMessage($type === 'success' ? 'success' : 'error', $message);
header('Location: login.php');
exit;
