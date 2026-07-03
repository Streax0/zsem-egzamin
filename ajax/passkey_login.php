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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$rpName = 'Kappi';
$rpId = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($rpId === '127.0.0.1') $rpId = 'localhost';
$rpId = explode(':', $rpId)[0];

$WebAuthn = new WebAuthn($rpName, $rpId, ['android-key', 'android-safetynet', 'apple', 'fido-u2f', 'none', 'packed', 'tpm']);

if ($action === 'generate') {
    try {
        // Puste credentialIds = pozwalamy na użycie Discoverable Credentials (Resident Key)
        $getArgs = $WebAuthn->getGetArgs([], 20, true, true, true, true, true, false);
        
        $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();
        
        echo json_encode(['status' => 'success', 'options' => $getArgs]);
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
    $authenticatorData = base64_decode($_POST['authenticatorData'] ?? '');
    $signature = base64_decode($_POST['signature'] ?? '');
    $userHandle = base64_decode($_POST['userHandle'] ?? '');
    $id = base64_decode($_POST['id'] ?? '');
    $challenge = $_SESSION['webauthn_challenge'] ?? '';

    if (!$challenge) {
        echo json_encode(['status' => 'error', 'message' => 'Sesja wygasła. Spróbuj ponownie.']);
        exit;
    }

    // Passkey authentication with Resident Key gives us the userHandle, which we mapped to user_id (string cast)
    $userId = (int)$userHandle;

    if (!$userId) {
        // Zabezpieczenie: szukamy klucza po credential_id
        $stmt = $pdo->prepare("SELECT user_id FROM user_passkeys WHERE credential_id = ?");
        $stmt->execute([base64_encode($id)]);
        $userId = $stmt->fetchColumn();
    }

    if (!$userId) {
        echo json_encode(['status' => 'error', 'message' => 'Klucz nie jest powiązany z żadnym kontem.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT public_key, counter FROM user_passkeys WHERE user_id = ? AND credential_id = ?");
    $stmt->execute([$userId, base64_encode($id)]);
    $passkey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$passkey) {
        echo json_encode(['status' => 'error', 'message' => 'Nieprawidłowy klucz.']);
        exit;
    }

    try {
        $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $passkey['public_key'], $challenge, null, false);
        
        $newCounter = $WebAuthn->getSignatureCounter();
        if ($newCounter !== 0) {
            $stmt = $pdo->prepare("UPDATE user_passkeys SET counter = ?, last_used_at = NOW() WHERE user_id = ? AND credential_id = ?");
            $stmt->execute([$newCounter, $userId, base64_encode($id)]);
        }

        unset($_SESSION['webauthn_challenge']);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Nie znaleziono użytkownika.");
        }

        if (!in_array($user['role'], ['admin', 'dyrektor', 'teacher'])) {
             throw new Exception("Twoje konto nie obsluguje passkey, jesli uwazasz ze to blad, skontaktuj sie z administratorem");
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
        $_SESSION['2fa_verified'] = true; 

        regenerateSessionId();
        registerCurrentUserSession($pdo, (int)$user['id']);
        if (function_exists('updateLastLogin')) {
            updateLastLogin($user['id']);
        }

        echo json_encode(['status' => 'success', 'message' => 'Zalogowano pomyślnie.', 'redirect' => '../index.php']);
    } catch (WebAuthnException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd weryfikacji logowania: ' . $e->getMessage()]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd bazy danych podczas logowania.']);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Wystąpił nieoczekiwany błąd: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
exit;
