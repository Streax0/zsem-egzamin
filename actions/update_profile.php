<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

$returnTarget = ($_POST['return_to'] ?? '') === 'profile.php' ? '../profile.php' : '../settings.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../settings.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Nieprawidłowy token CSRF.');
    header('Location: ' . $returnTarget);
    exit;
}

$userId = (int)$_SESSION['user_id'];
ensurePlatformEnhancements($pdo);
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$classParts = normalizeClassParts($_POST['class_year'] ?? null, $_POST['class_suffix'] ?? '');
$errors = [];
$avatarUploaded = false;
$avatarPath = null;

if (strlen($username) < 3 || strlen($username) > 50) {
    $errors[] = 'Nazwa użytkownika musi mieć od 3 do 50 znaków.';
}

if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
    $errors[] = 'Nazwa użytkownika może zawierać tylko litery, cyfry, kropki, podkreślenia i myślniki.';
}

if (containsProfanity($username) || containsProfanity($email) || containsProfanity($_POST['class_suffix'] ?? '')) {
    $errors[] = 'Dane profilu zawierają niedozwolone słowa.';
}

if (!validateAllowedEmail($email) || strlen($email) > 100) {
    $errors[] = 'Podaj poprawny adres e-mail z obsługiwanej domeny.';
}

if (!$classParts) {
    $errors[] = 'Klasa może być pusta / nie dotyczy albo z zakresu 1-5, a oznaczenie może mieć maksymalnie 2 znaki.';
}

if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Podana nazwa użytkownika lub e-mail jest już zajęty.';
        }
    } catch (PDOException $e) {
        error_log('Profile uniqueness check error: ' . $e->getMessage());
        $errors[] = 'Nie udało się sprawdzić danych profilu.';
    }
}

if (empty($errors) && isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['avatar'];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'Nie udało się przesłać zdjęcia profilowego.';
    } elseif (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $errors[] = 'Zdjęcie profilowe może mieć maksymalnie 2 MB.';
    } elseif (!function_exists('imagewebp')) {
        $errors[] = 'Serwer nie obsługuje konwersji zdjęć do WebP.';
    } else {
        try {
            $limitStmt = $pdo->prepare("SELECT avatar_changed_at FROM users WHERE id = ? LIMIT 1");
            $limitStmt->execute([$userId]);
            $lastAvatarChange = $limitStmt->fetchColumn();
            if ($lastAvatarChange && strtotime((string)$lastAvatarChange) > strtotime('-1 month')) {
                $errors[] = 'Zdjęcie profilowe można zmienić raz na miesiąc.';
            }
        } catch (PDOException $e) {
            error_log('Avatar change limit check failed: ' . $e->getMessage());
            $errors[] = 'Nie udało się sprawdzić limitu zmiany zdjęcia.';
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if (empty($errors) && ($tmp === '' || !is_uploaded_file($tmp))) {
            $errors[] = 'Nieprawidłowy plik zdjęcia profilowego.';
        }
        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        $loaders = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];
        if (empty($errors) && (!$info || !isset($loaders[$mime]) || !function_exists($loaders[$mime]))) {
            $errors[] = 'Zdjęcie musi być plikiem JPG, PNG albo WebP.';
        } elseif (empty($errors) && ((int)($info[0] ?? 0) * (int)($info[1] ?? 0) > 16000000)) {
            $errors[] = 'Zdjęcie profilowe ma zbyt dużą rozdzielczość.';
        } elseif (empty($errors)) {
            $source = @$loaders[$mime]($tmp);
            if (!$source) {
                $errors[] = 'Nie udało się odczytać zdjęcia profilowego.';
            } else {
                $width = imagesx($source);
                $height = imagesy($source);
                $safety = scanAvatarImageSafety($source, $width, $height);
                if (empty($safety['ok'])) {
                    $errors[] = $safety['message'] ?? 'Zdjęcie profilowe nie przeszło kontroli treści.';
                    imagedestroy($source);
                } else {
                $maxSide = 512;
                $scale = min(1, $maxSide / max(1, $width), $maxSide / max(1, $height));
                $targetW = max(1, (int)round($width * $scale));
                $targetH = max(1, (int)round($height * $scale));
                $target = imagecreatetruecolor($targetW, $targetH);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

                $uploadDir = dirname(__DIR__) . '/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = 'user_' . $userId . '_' . bin2hex(secureRandomBytes(6)) . '.webp';
                $dest = $uploadDir . '/' . $filename;
                if (!imagewebp($target, $dest, 82)) {
                    $errors[] = 'Nie udało się zapisać zdjęcia jako WebP.';
                } else {
                    $avatarPath = 'uploads/avatars/' . $filename;
                    $avatarUploaded = true;
                }
                imagedestroy($source);
                imagedestroy($target);
                }
            }
        }
    }
}

if (!empty($errors)) {
    setSessionMessage('error', implode(' ', $errors));
    header('Location: ' . $returnTarget);
    exit;
}

try {
    if ($avatarUploaded) {
        $oldStmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ? LIMIT 1");
        $oldStmt->execute([$userId]);
        $oldAvatar = (string)($oldStmt->fetchColumn() ?: '');
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, class = ?, class_year = ?, class_suffix = ?, avatar_path = ?, avatar_changed_at = NOW() WHERE id = ?");
        $stmt->execute([$username, $email, $classParts['label'], $classParts['year'], $classParts['suffix'], $avatarPath, $userId]);
        if ($oldAvatar !== '' && preg_match('#^uploads/avatars/user_\d+_[a-f0-9]{12}\.webp$#', $oldAvatar)) {
            $oldPath = dirname(__DIR__) . '/' . $oldAvatar;
            if (is_file($oldPath)) @unlink($oldPath);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, class = ?, class_year = ?, class_suffix = ? WHERE id = ?");
        $stmt->execute([$username, $email, $classParts['label'], $classParts['year'], $classParts['suffix'], $userId]);
    }
    $_SESSION['username'] = $username;
    setSessionMessage('success', $avatarUploaded ? 'Dane profilu i zdjęcie zostały zaktualizowane.' : 'Dane profilu zostały zaktualizowane.');
} catch (PDOException $e) {
    error_log('Profile update error: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się zapisać danych profilu.');
}

header('Location: ' . $returnTarget);
exit;
