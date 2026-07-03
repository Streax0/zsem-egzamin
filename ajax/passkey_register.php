<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

error_reporting(0);
header('Content-Type: application/json');

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Tylko admin, dyrektor i teacher mogą korzystać z Passkeys (jak zażyczył sobie użytkownik)
if (!in_array($role, ['admin', 'dyrektor', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden for this role']);
    exit;
}

// Pobieranie danych z żądania (ponieważ to AJAX)
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$rpName = 'Kappi';
$rpId = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($rpId === '127.0.0.1') $rpId = 'localhost'; // WebAuthn prefers localhost or actual domain
// Usuwanie portu z HTTP_HOST
$rpId = explode(':', $rpId)[0];

$WebAuthn = new WebAuthn($rpName, $rpId, ['android-key', 'android-safetynet', 'apple', 'fido-u2f', 'none', 'packed', 'tpm']);

if ($action === 'generate') {
    $username = $_SESSION['username'];
    
    // Ustawiamy $requireResidentKey = true, aby umożliwić logowanie bez podawania loginu
    try {
        $createArgs = $WebAuthn->getCreateArgs((string)$userId, $username, $username, 20, true, false, null);
        $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();
        
        echo json_encode(['status' => 'success', 'options' => $createArgs]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
        exit;
    }

    $clientDataJSON = base64_decode($_POST['clientDataJSON'] ?? '');
    $attestationObject = base64_decode($_POST['attestationObject'] ?? '');
    $challenge = $_SESSION['webauthn_challenge'] ?? '';
    $deviceName = trim($_POST['deviceName'] ?? 'Nieznane urządzenie');

    try {
        $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, false, true, false);

        $credentialId = base64_encode($data->credentialId);
        $credentialPublicKey = $data->credentialPublicKey;
        
        // Zapis do bazy
        $stmt = $pdo->prepare("INSERT INTO user_passkeys (user_id, credential_id, public_key, device_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $credentialId, $credentialPublicKey, $deviceName]);
        
        unset($_SESSION['webauthn_challenge']);
        
        echo json_encode(['status' => 'success', 'message' => 'Passkey został dodany pomyślnie.']);
    } catch (WebAuthnException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd weryfikacji WebAuthn: ' . $e->getMessage()]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd bazy danych: Passkey może już istnieć.']);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Wystąpił nieoczekiwany błąd: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nie znaleziono klucza.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
exit;
