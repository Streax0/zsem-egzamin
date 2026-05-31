<?php
ob_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';
$flashMsg = getSessionMessage();
if (!$flashMsg && isset($_GET['logged_out_all'])) {
    $flashMsg = ['type' => 'success', 'message' => 'Wylogowano wszystkie sesje konta.'];
}
if (!$flashMsg && isset($_GET['session_expired'])) {
    $flashMsg = ['type' => 'info', 'message' => 'Ta sesja została zakończona, ponieważ konto ma limit dwóch aktywnych urządzeń.'];
}
$clientIp = clientIpAddress();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Nieprawidłowy token CSRF.';
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username)) $errors[] = 'Nazwa użytkownika jest wymagana.';
    if (empty($password)) $errors[] = 'Hasło jest wymagane.';
    $captchaRequired = shouldRequireLoginCaptcha($clientIp, $username);
    if ($captchaRequired && !validateLoginCaptcha((string)($_POST['login_captcha_answer'] ?? ''))) {
        $errors[] = 'Przepisz poprawny wynik zabezpieczenia anty-bot.';
    }

    if (empty($errors)) {
        $result = login($username, $password, $remember);
        if ($result['success']) {
            regenerateSessionId();
            if (isset($result['user_id'])) {
                registerCurrentUserSession($pdo, (int)$result['user_id']);
                updateLastLogin($result['user_id']);
            }
            if (!empty($result['mfa_required'])) {
                header('Location: mfa.php');
                exit;
            }
            header('Location: index.php');
            exit;
        } else {
            $errors[] = $result['message'] ?? 'Nieprawidłowe dane logowania.';
        }
    }
}
$csrf_token = generateCsrfToken();
$captchaRequired = shouldRequireLoginCaptcha($clientIp, $username);
$captcha = $captchaRequired ? generateLoginCaptcha() : null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: var(--primary-color);
            --primary-dark: var(--primary-color-dark);
            --accent: #8b5cf6;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
            overflow-y: auto;
            position: relative;
            padding: 2rem 1rem;
        }
        .login-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-logo {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(to right, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .form-label { font-weight: 500; font-size: 0.9rem; color: #cbd5e1; } /* Lighter color */
        .text-muted { color: #94a3b8 !important; } /* Lighter muted text */
        .form-control {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(to right, #1333c0, #0b43ac);
            border: none;
            color: #fff !important;
            padding: 0.9rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .28);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
            color: #fff !important;
        }
        .alert-custom {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            border-radius: 12px;
            font-size: 0.9rem;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 42px;
            color: #64748b;
        }
        .turnstile-container {
            min-height: 65px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .turnstile-container.hidden {
            display: none;
            margin-bottom: 0;
        }
        @media (max-width: 576px), (max-height: 720px) {
            body { align-items: flex-start; }
            .login-card {
                padding: 1.5rem;
                border-radius: 16px;
            }
            .brand-logo { font-size: 2rem; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <section class="auth-info-panel" aria-label="ZSEM Tech">
            <div>
                <div class="auth-brand"><i class="bi bi-mortarboard-fill"></i> ZSEM Tech</div>
                <h1>Wejdź do panelu ZSEM Tech</h1>
                <p class="text-muted fs-5 mb-0">Testy, arkusze, wyniki i sprawdziany nauczyciela w jednym miejscu.</p>
            </div>
            <div class="row g-3 mt-4">
                <div class="col-6"><div class="p-3 rounded-4 bg-light border"><strong>Testy</strong><br><span class="small text-muted">INF.02 i arkusze</span></div></div>
                <div class="col-6"><div class="p-3 rounded-4 bg-light border"><strong>Wyniki</strong><br><span class="small text-muted">postęp i ranking</span></div></div>
            </div>
        </section>

    <main class="login-card auth-form-panel" role="main">
        <div class="text-center mb-5">
            <div class="brand-logo"><i class="bi bi-mortarboard-fill"></i> ZSEM Tech</div>
            <p class="text-muted">Witaj ponownie! Zaloguj się, aby kontynuować.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-custom mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($flashMsg): ?>
            <div class="alert alert-<?= ($flashMsg['type'] ?? '') === 'success' ? 'success' : 'info' ?> mb-4 border-0 rounded-3">
                <?= htmlspecialchars($flashMsg['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="mb-4">
                <label class="form-label" for="login_username">Użytkownik lub E-mail</label>
                <input type="text" name="username" id="login_username" class="form-control" placeholder="Twój login" value="<?= htmlspecialchars($username) ?>" required autofocus>
            </div>

            <div class="mb-4 position-relative">
                <label class="form-label" for="password">Hasło</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                <button type="button" class="password-toggle auth-password-toggle" data-password-toggle="password" aria-label="Pokaż lub ukryj hasło"><i class="bi bi-eye"></i></button>
            </div>

            <?php if ($captchaRequired && $captcha): ?>
            <div class="mb-4">
                <label class="form-label" for="loginCaptcha">Zabezpieczenie po nieudanych próbach: <?= htmlspecialchars($captcha['question']) ?> = ?</label>
                <input type="text" name="login_captcha_answer" id="loginCaptcha" class="form-control" inputmode="numeric" pattern="-?[0-9]+" autocomplete="off" required>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label small text-muted" for="remember">Zapamiętaj mnie</label>
                </div>
                <a href="forgot_password.php" class="small text-primary text-decoration-none">Zapomniałeś hasła?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-4">Zaloguj się</button>

            <div class="text-center">
                <p class="small text-muted">Nie masz konta? <a href="register.php" class="text-primary text-decoration-none fw-semibold">Załóż je teraz</a></p>
            </div>
        </form>
        <form action="actions/start_guest.php" method="POST" class="mt-3">
            <?php echo csrfTokenField('guest_start'); ?>
            <input type="hidden" name="target" value="test">
            <button type="submit" class="btn btn-outline-light guest-mode-btn w-100">
                <i class="bi bi-person-walking me-1"></i>Tryb gościa
            </button>
        </form>
    </main>
    </div>

    <script src="assets/js/auth.js"></script>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
