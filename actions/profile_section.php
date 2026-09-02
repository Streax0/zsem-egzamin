<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setSessionMessage('error', 'Błąd bezpieczeństwa.');
    redirect('../user/profile.php');
}

$userId = (int)$_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$action = $_POST['section_action'] ?? 'add';
$id = (int)($_POST['id'] ?? 0);

ensurePlatformEnhancements($pdo);

$tables = [
    'education' => 'user_education',
    'certificate' => 'user_certificates',
    'course' => 'user_courses',
    'volunteering' => 'user_volunteering',
    'language' => 'user_languages',
    'organization' => 'user_organizations',
    'social' => 'user_social_links',
];

if (!isset($tables[$type])) {
    setSessionMessage('error', 'Nieznana sekcja profilu.');
    redirect('../user/profile.php');
}

function profileText($key, $max = 160) {
    return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max);
}

function profileDateOrNull($key) {
    $value = trim((string)($_POST[$key] ?? ''));
    if ($value === '') return null;
    if (preg_match('/^\d{8}$/', $value)) {
        $year = (int)substr($value, 0, 4);
        $month = (int)substr($value, 4, 2);
        $day = (int)substr($value, 6, 2);
        if (!checkdate($month, $day, $year)) return false;
        if ($year < 2000 || $year > 2040) return false;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $ts = strtotime($value);
    if (!$ts) return false;
    $year = (int)date('Y', $ts);
    if ($year < 2000 || $year > 2040) return false;
    return date('Y-m-d', $ts);
}

try {
    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM {$tables[$type]} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        setSessionMessage('success', 'Element profilu został usunięty.');
        redirect('../user/profile.php');
    }

    $textFields = $_POST;
    foreach ($textFields as $value) {
        if (containsProfanity($value)) {
            setSessionMessage('error', 'Treść zawiera niedozwolone słowa.');
            redirect('../user/profile.php');
        }
    }

    if ($type === 'education') {
        $level = $_POST['level'] ?? '';
        $school = profileText('school_name', 160);
        $field = profileText('field', 160);
        $startRaw = trim((string)($_POST['start_year'] ?? ''));
        $endRaw = trim((string)($_POST['end_year'] ?? ''));
        $start = preg_match('/^\d{4}$/', $startRaw) ? (int)$startRaw : 0;
        $end = $endRaw !== '' && preg_match('/^\d{4}$/', $endRaw) ? (int)$endRaw : null;
        if ($endRaw !== '' && $end === null) {
            setSessionMessage('error', 'Nieprawidłowy rok zakończenia.');
            redirect('../user/profile.php');
        }
        if (!in_array($level, ['podstawowe','średnie','wyższe'], true) || $school === '' || $start < 2000 || $start > 2040 || ($end !== null && ($end < 2000 || $end > 2040 || $end < $start))) {
            setSessionMessage('error', 'Nieprawidłowe dane wykształcenia.');
            redirect('../user/profile.php');
        }

        if (in_array($level, ['podstawowe', 'średnie'], true)) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_education WHERE user_id = ? AND level = ?");
            $countStmt->execute([$userId, $level]);
            if ($countStmt->fetchColumn() > 0) {
                setSessionMessage('error', 'Możesz dodać tylko jedno wpisy wykształcenia dla poziomu podstawowego lub średniego.');
                redirect('../user/profile.php');
            }
        }

        $stmt = $pdo->prepare("INSERT INTO user_education (user_id, level, school_name, field, start_year, end_year) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $level, $school, $field ?: null, $start, $end]);
    } elseif ($type === 'certificate') {
        $date = profileDateOrNull('obtained_date');
        if ($date === false || profileText('name') === '' || profileText('organization') === '') {
            setSessionMessage('error', 'Nieprawidłowe dane certyfikatu.');
            redirect('../user/profile.php');
        }
        $stmt = $pdo->prepare("INSERT INTO user_certificates (user_id, name, organization, obtained_date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, profileText('name'), profileText('organization'), $date, profileText('description', 500)]);
    } elseif ($type === 'course') {
        $date = profileDateOrNull('completed_date');
        if ($date === false || profileText('name') === '' || profileText('provider') === '') {
            setSessionMessage('error', 'Nieprawidłowe dane kursu.');
            redirect('../user/profile.php');
        }
        $stmt = $pdo->prepare("INSERT INTO user_courses (user_id, name, provider, completed_date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, profileText('name'), profileText('provider'), $date, profileText('description', 500)]);
    } elseif ($type === 'volunteering') {
        $startDate = profileDateOrNull('start_date');
        $endDate = profileDateOrNull('end_date');
        if ($startDate === false || $endDate === false || ($startDate && $endDate && $endDate < $startDate) || profileText('organization') === '' || profileText('role_name') === '') {
            setSessionMessage('error', 'Nieprawidłowe dane wolontariatu.');
            redirect('../user/profile.php');
        }
        $stmt = $pdo->prepare("INSERT INTO user_volunteering (user_id, organization, role_name, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, profileText('organization'), profileText('role_name'), $startDate, $endDate, profileText('description', 500)]);
    } elseif ($type === 'language') {
        $level = $_POST['level'] ?? '';
        $language = profileText('language_name', 80);
        $allowedLanguages = ['Angielski', 'Niemiecki', 'Hiszpański', 'Francuski', 'Włoski', 'Polski', 'Ukraiński', 'Rosyjski', 'Czeski', 'Słowacki'];
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_languages WHERE user_id = ?");
        $countStmt->execute([$userId]);
        if ((int)$countStmt->fetchColumn() >= 7) {
            setSessionMessage('error', 'Możesz dodać maksymalnie 7 języków.');
            redirect('../user/profile.php');
        }
        if (!in_array($language, $allowedLanguages, true) || !in_array($level, ['podstawowy','średni','zaawansowany','biegły'], true)) {
            setSessionMessage('error', 'Nieprawidłowy język lub poziom.');
            redirect('../user/profile.php');
        }
        $stmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_name, level) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $language, $level]);
    } elseif ($type === 'organization') {
        $startDate = profileDateOrNull('start_date');
        $endDate = profileDateOrNull('end_date');
        if ($startDate === false || $endDate === false || ($startDate && $endDate && $endDate < $startDate) || profileText('name') === '') {
            setSessionMessage('error', 'Nieprawidłowe dane organizacji.');
            redirect('../user/profile.php');
        }
        $stmt = $pdo->prepare("INSERT INTO user_organizations (user_id, name, role_name, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, profileText('name'), profileText('role_name'), $startDate, $endDate, profileText('description', 500)]);
    } elseif ($type === 'social') {
        $platform = $_POST['platform'] ?? '';
        $url = trim($_POST['url'] ?? '');
        $hosts = [
            'github' => ['github.com'],
            'linkedin' => ['linkedin.com'],
            'instagram' => ['instagram.com'],
            'youtube' => ['youtube.com', 'youtu.be'],
            'facebook' => ['facebook.com', 'fb.com'],
            'x' => ['x.com', 'twitter.com'],
            'tiktok' => ['tiktok.com'],
            'gitlab' => ['gitlab.com'],
        ];
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $host = preg_replace('/^www\./', '', $host);
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
        $validHost = false;
        foreach ($hosts[$platform] ?? [] as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                $validHost = true;
                break;
            }
        }
        if (!isset($hosts[$platform]) || $scheme !== 'https' || mb_strlen($url, 'UTF-8') > 255 || !filter_var($url, FILTER_VALIDATE_URL) || !$validHost) {
            setSessionMessage('error', 'Nieprawidłowy link społecznościowy.');
            redirect('../user/profile.php');
        }
        $stmt = $pdo->prepare("INSERT INTO user_social_links (user_id, platform, url) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE url = VALUES(url)");
        $stmt->execute([$userId, $platform, $url]);
    }

    setSessionMessage('success', 'Profil został zaktualizowany.');
} catch (PDOException $e) {
    error_log('Profile section save failed: ' . $e->getMessage());
    setSessionMessage('error', 'Nie udało się zapisać sekcji profilu. Sprawdź, czy full_schema.sql został zaimportowany.');
}

redirect('../user/profile.php');
