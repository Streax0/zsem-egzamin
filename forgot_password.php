<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

ensurePlatformEnhancements($pdo);

$errors = [];
$notice = '';
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$resetUser = $token !== '' ? getPasswordResetUser($pdo, $token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'request';
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'forgot_password')) {
        $errors[] = 'Nieprawidłowy token CSRF.';
    } elseif ($action === 'request') {
        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Podaj poprawny adres e-mail.';
        } else {
            $resetToken = createPasswordResetToken($pdo, $email);
            if ($resetToken !== null) {
                sendPasswordResetEmail($email, $resetToken);
            }
            $notice = 'Jeśli konto istnieje, wysłaliśmy link resetowania hasła. Link jest ważny 30 minut.';
        }
        } elseif ($action === 'reset') {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (!$resetUser) {
            $errors[] = 'Link resetowania jest nieprawidłowy albo wygasł.';
        } elseif ($passwordPolicyErrors = validatePasswordPolicy($password)) {
            $errors = array_merge($errors, $passwordPolicyErrors);
        } elseif ($password !== $confirm) {
            $errors[] = 'Hasła nie są identyczne.';
        } elseif (resetPasswordWithToken($pdo, $token, $password)) {
            setSessionMessage('success', 'Hasło zostało zmienione. Zaloguj się ponownie.');
            header('Location: login.php');
            exit;
        } else {
            $errors[] = 'Nie udało się zmienić hasła. Wygeneruj nowy link.';
        }
    }
}

$csrf = generateCsrfToken('forgot_password');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset hasła - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script src="assets/js/register.js" defer></script>
</head>
<body class="auth-page">
    <div class="auth-shell">
        <section class="auth-info-panel" aria-label="Bezpieczny reset">
            <div>
                <div class="auth-brand"><i class="bi bi-shield-lock-fill"></i> ZSEM Tech</div>
                <h1>Bezpieczny reset hasła</h1>
                <p class="text-muted fs-5 mb-0">Ta funkcja jest nadal dopracowywana. Jeżeli wiadomość nie dotrze, skontaktuj się z opiekunem platformy.</p>
            </div>
            <div class="auth-feature-grid mt-4">
                <div class="auth-feature-card"><strong>30 minut</strong><br><span class="small text-muted">czas ważności linku</span></div>
                <div class="auth-feature-card"><strong>Pomoc</strong><br><span class="small text-muted">kontakt, gdy reset nie zadziała</span></div>
            </div>
        </section>

        <main class="auth-form-panel" role="main">
            <div class="text-center mb-4">
                <div class="brand-logo"><i class="bi bi-key-fill"></i> Reset hasła</div>
                <p class="text-muted small mb-0"><?= $resetUser ? 'Ustaw nowe hasło dla konta.' : 'Podaj e-mail konta.' ?></p>
            </div>
            <div class="alert alert-warning border-0 rounded-3 small">
                Trwają prace nad pełnym resetem hasła. W środowisku bez poczty link może nie zostać dostarczony.
            </div>

            <?php if ($notice): ?>
                <div class="alert alert-success border-0 rounded-3"><?= htmlspecialchars($notice) ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-custom">
                    <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <?php if ($token !== '' && $resetUser): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="regPassword">Nowe hasło</label>
                        <input type="password" class="form-control" id="regPassword" name="password" minlength="10" maxlength="128" required autocomplete="new-password">
                        <div class="strength-meter"><div id="strengthBar" class="strength-meter-bar"></div></div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="confirm_password">Powtórz hasło</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="10" maxlength="128" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">Zmień hasło</button>
                </form>
            <?php elseif ($token !== ''): ?>
                <div class="alert alert-warning border-0 rounded-3">Link resetowania jest nieprawidłowy albo wygasł.</div>
                <a href="forgot_password.php" class="btn btn-primary w-100">Wygeneruj nowy link</a>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="request">
                    <div class="mb-4">
                        <label for="email" class="form-label">Adres e-mail</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="100" required autocomplete="email" placeholder="jan@example.com">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">Wyślij link resetujący</button>
                </form>
            <?php endif; ?>

            <div class="text-center">
                <a href="login.php" class="small text-primary text-decoration-none fw-bold">Wróć do logowania</a>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
