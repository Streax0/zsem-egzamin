<?php
declare(strict_types=1);

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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$rpName = 'Kappi';
$rpId = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($rpId === '127.0.0.1') $rpId = 'localhost';
$rpId = explode(':', $rpId)[0];

$WebAuthn = new WebAuthn($rpName, $rpId, ['android-key', 'android-safetynet', 'apple', 'fido-u2f', 'none', 'packed', 'tpm']);

if ($action === 'generate') {
    try {
        // Puste credentialIds = pozwalamy na użycie Discoverable Credentials (Resident Key)
        $getArgs = $WebAuthn->getGetArgs([], 30, true, true, true, true, true, 'preferred');
        
        $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge();
        
        echo securityJsonEncode(['status' => 'success', 'options' => $getArgs]);
    } catch (Throwable $e) {
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
    $authenticatorData = base64_decode($_POST['authenticatorData'] ?? '');
    $signature = base64_decode($_POST['signature'] ?? '');
    $userHandle = base64_decode($_POST['userHandle'] ?? '');
    $id = base64_decode($_POST['id'] ?? '');
    $challenge = $_SESSION['webauthn_challenge'] ?? '';

    if (!$challenge) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Sesja wygasła. Spróbuj ponownie.']);
        exit;
    }

    // Passkey authentication with Resident Key gives us the userHandle, which we mapped to user_id (string cast)
    $userId = (int)$userHandle;

    if (!$userId) {
        // Zabezpieczenie: szukamy klucza po credential_id
        $stmt = $pdo->prepare("SELECT user_id FROM user_passkeys WHERE credential_id = ?");
        $stmt->execute([base64_encode($id)]);
        $userId = (int)$stmt->fetchColumn();
    }

    if (!$userId) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Klucz Passkey nie jest powiązany z żadnym kontem.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT public_key, counter FROM user_passkeys WHERE user_id = ? AND credential_id = ?");
    $stmt->execute([$userId, base64_encode($id)]);
    $passkey = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$passkey) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Nieprawidłowy klucz Passkey.']);
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
        
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, first_name, last_name, class, class_year, class_suffix, bio, avatar_path, avatar_changed_at, xp, profile_public, stats_public, allow_profile_comments, allow_friend_requests, searchable, is_verified, verified_at, verified_by_admin_id, ranking_visible, verification_token, is_banned, ban_expires_at, trust_status, risk_flags, registration_ip, created_at, last_login, last_login_ip, last_activity, session_version FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Nie znaleziono użytkownika.");
        }

        if (!in_array($user['role'], ['admin', 'dyrektor', 'teacher'])) {
             throw new Exception("Twoje konto nie obsługuje logowania przez Passkey.");
        }

        // Check if user is banned
        if (isset($user['is_banned']) && (int)$user['is_banned'] === 1) {
            if (function_exists('clearExpiredBanForUser') && clearExpiredBanForUser($pdo, $user)) {
                $user['is_banned'] = 0;
            } elseif (function_exists('userBanIsActive') && userBanIsActive($pdo, (int)$user['id'])) {
                $until = !empty($user['ban_expires_at']) ? ' do ' . date('d.m.Y H:i', strtotime((string)$user['ban_expires_at'])) : '';
                echo securityJsonEncode(['status' => 'error', 'message' => 'Twoje konto zostało zablokowane' . $until . '. Skontaktuj się z administratorem.']);
                exit;
            }
        }

        // Initialize user session
        clearGuestSessionState();
        if (function_exists('regenerateSessionId')) {
            regenerateSessionId(true);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = (string)($user['role'] ?? 'user');
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
        
        // Passkey / WebAuthn is strong hardware multi-factor authentication; it satisfies MFA
        $_SESSION['mfa_enabled'] = function_exists('mfaUserHasEnabled') ? mfaUserHasEnabled($pdo, (int)$user['id']) : false;
        $_SESSION['mfa_verified'] = true;
        $_SESSION['2fa_verified'] = true;
        $_SESSION['auth_method'] = 'passkey';

        registerCurrentUserSession($pdo, (int)$user['id']);
        if (function_exists('updateLastLogin')) {
            updateLastLogin((int)$user['id']);
        }

        echo securityJsonEncode(['status' => 'success', 'message' => 'Zalogowano pomyślnie za pomocą Passkey.', 'redirect' => '../index.php']);
    } catch (WebAuthnException $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Błąd weryfikacji Passkey: ' . $e->getMessage()]);
    } catch (PDOException $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Błąd bazy danych podczas logowania.']);
    } catch (Throwable $e) {
        echo securityJsonEncode(['status' => 'error', 'message' => 'Wystąpił nieoczekiwany błąd: ' . $e->getMessage()]);
    }
    exit;
}

echo securityJsonEncode(['status' => 'error', 'message' => 'Unknown action']);
exit;
