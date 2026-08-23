<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/autoloader.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

error_reporting(0);
securityApplyJsonHeaders();

startSecureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo securityJsonEncode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Tylko admin, dyrektor i teacher mogą korzystać z Passkeys (jak zażyczył sobie użytkownik)
if (!in_array($role, ['admin', 'dyrektor', 'teacher'])) {
    http_response_code(403);
    echo securityJsonEncode(['status' => 'error', 'message' => 'Forbidden for this role']);
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
    
    // Pobierz istniejące klucze użytkownika, aby zapobiec ponownej rejestracji tego samego klucza (excludeCredentials)
    $existingStmt = $pdo->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
    $existingStmt->execute([$userId]);
    $excludeCredentialIds = [];
    while ($row = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
        $decoded = base64_decode($row['credential_id']);
        if ($decoded !== false) {
            $excludeCredentialIds[] = $decoded;
        }
    }
    
    try {
        $createArgs = $WebAuthn->getCreateArgs((string)$userId, $username, $username, 30, 'required', 'preferred', null, $excludeCredentialIds);
        $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();
        
        echo securityJsonEncode(['status' => 'success', 'options' => $createArgs]);
    } catch (Exception $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Invalid method']);
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
        
        // Sprawdź, czy klucz już istnieje w bazie
        $checkStmt = $pdo->prepare("SELECT id FROM user_passkeys WHERE user_id = ? AND credential_id = ?");
        $checkStmt->execute([$userId, $credentialId]);
        if ($checkStmt->fetch()) {
            unset($_SESSION['webauthn_challenge']);
            echo securityJsonEncode(['status' => 'success', 'message' => 'Ten klucz Passkey jest już zarejestrowany na Twoim koncie.']);
            exit;
        }

        // Zapis do bazy
        $stmt = $pdo->prepare("INSERT INTO user_passkeys (user_id, credential_id, public_key, device_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $credentialId, $credentialPublicKey, $deviceName]);
        
        unset($_SESSION['webauthn_challenge']);
        
        echo securityJsonEncode(['status' => 'success', 'message' => 'Passkey został dodany pomyślnie.']);
    } catch (WebAuthnException $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Błąd weryfikacji WebAuthn: ' . $e->getMessage()]);
    } catch (PDOException $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Błąd bazy danych: Ten klucz Passkey może już istnieć.']);
    } catch (Throwable $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Wystąpił nieoczekiwany błąd: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Invalid method']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo securityJsonEncode(['status' => 'success']);
    } else {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Nie znaleziono klucza.']);
    }
    exit;
}

echo securityJsonEncode(['status' => 'error', 'message' => 'Unknown action']);
exit;
