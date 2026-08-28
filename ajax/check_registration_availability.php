<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

$type = securityInputEnum($_GET['type'] ?? '', ['username', 'email'], '');
$value = securityInputString($_GET['value'] ?? '', 100);
$rateLimit = securityConsumeRateLimit('registration-availability:' . securityActorKey() . ':' . $type, 80, 60);
if (empty($rateLimit['allowed'])) {
    http_response_code(429);
    echo securityJsonEncode([
        'ok' => false,
        'available' => false,
        'message' => 'Zbyt wiele sprawdzeń. Spróbuj za chwilę.',
        'suggestions' => [],
        'retry_after' => (int)($rateLimit['retry_after'] ?? 0),
    ]);
    exit;
}

$response = [
    'ok' => false,
    'available' => false,
    'message' => 'Nieprawidłowe dane.',
    'suggestions' => []
];

try {
    if ($type === 'username') {
        if ($value === '') {
            $response = ['ok' => true, 'available' => true, 'message' => 'Login zostanie wygenerowany losowo.', 'suggestions' => []];
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,16}$/', $value)) {
            $response['message'] = 'Login: 3-16 znaków, litery, cyfry, kropka, myślnik lub podkreślenie.';
        } elseif (containsProfanity($value)) {
            $response['message'] = 'Login zawiera niedozwolone słowa.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$value]);
            $taken = (bool)$stmt->fetchColumn();
            $response = [
                'ok' => true,
                'available' => !$taken,
                'message' => $taken ? 'Ta nazwa użytkownika jest już zajęta.' : 'Ta nazwa użytkownika jest dostępna.',
                'suggestions' => $taken ? registrationUsernameSuggestions($pdo, $value, 3) : []
            ];
        }
    } elseif ($type === 'email') {
        $email = mb_strtolower($value, 'UTF-8');
        if (!validateAllowedEmail($email) || mb_strlen($email, 'UTF-8') > 100) {
            $response['message'] = 'Podaj poprawny adres e-mail.';
        } elseif (isEmailBanned($email)) {
            $response['message'] = 'Ten adres e-mail jest zablokowany.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $taken = (bool)$stmt->fetchColumn();
            $response = [
                'ok' => true,
                'available' => !$taken,
                'message' => $taken ? 'Ten adres e-mail jest już używany.' : 'Ten adres e-mail jest dostępny.',
                'suggestions' => []
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Registration availability endpoint failed: ' . $e->getMessage());
    securityAudit('registration_availability_failed', ['type' => $type], 'error');
    $response = ['ok' => false, 'available' => false, 'message' => 'Nie udało się sprawdzić dostępności.', 'suggestions' => []];
}

echo securityJsonEncode($response);
