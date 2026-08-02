<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: ../index.php');
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
                header("Location: mfa.php");
                exit;
            }
            header('Location: ../index.php');
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
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-glow-orb-1" aria-hidden="true"></div>
    <div class="auth-glow-orb-2" aria-hidden="true"></div>

    <div class="auth-shell">
        <section class="auth-info-panel" aria-label="ZSEM Tech Info">
            <div>
                <a href="../landing.php" class="auth-brand">
                    <img src="../zsemtech_profile.ico" alt="" width="36" height="36" loading="lazy" decoding="async">
                    <span>ZSEM Tech</span>
                </a>
                <div class="mt-4">
                    <h1>Wejdź do panelu ZSEM Tech</h1>
                    <p class="text-muted fs-5 mb-0">Oficjalny portal przygotowania do kwalifikacji zawodowych INF i EE. Rozwiązuj testy, sprawdziany i śledź swój progres.</p>
                </div>
            </div>
            <div class="auth-feature-grid mt-4">
                <div class="auth-feature-card">
                    <strong><i class="bi bi-journal-check"></i> Testy CKE</strong>
                    <span class="small text-muted">+5000 pytań INF.02/03</span>
                </div>
                <div class="auth-feature-card">
                    <strong><i class="bi bi-graph-up-arrow"></i> Wyniki &amp; XP</strong>
                    <span class="small text-muted">Statystyki i rankingi</span>
                </div>
                <div class="auth-feature-card">
                    <strong><i class="bi bi-shield-lock-fill"></i> Szyfrowanie</strong>
                    <span class="small text-muted">Bezpieczne konto RODO</span>
                </div>
                <div class="auth-feature-card">
                    <strong><i class="bi bi-fingerprint"></i> Passkey</strong>
                    <span class="small text-muted">Logowanie biometryczne</span>
                </div>
            </div>
        </section>

        <main class="login-card auth-form-panel" role="main">
            <div class="text-center mb-4">
                <div class="brand-logo"><i class="bi bi-mortarboard-fill"></i> ZSEM Tech</div>
                <p class="text-muted small">Witaj ponownie! Zaloguj się, aby kontynuować naukę.</p>
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

            <form method="POST" data-kappicrypt="true" data-kappicrypt-badge="false">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="mb-4 auth-input-group has-icon">
                    <label class="form-label" for="login_username">Login lub e-mail</label>
                    <div class="position-relative">
                        <i class="bi bi-person-fill auth-input-icon"></i>
                        <input type="text" name="username" id="login_username" class="form-control" placeholder="login albo adres e-mail" value="<?= htmlspecialchars($username) ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4 position-relative password-field auth-input-group has-icon">
                    <label class="form-label" for="password">Hasło</label>
                    <div class="position-relative">
                        <i class="bi bi-lock-fill auth-input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <button type="button" class="password-toggle auth-password-toggle" data-password-toggle="password" aria-label="Pokaż lub ukryj hasło"><i class="bi bi-eye"></i></button>
                    </div>
                </div>

                <?php if ($captchaRequired && $captcha): ?>
                <div class="mb-4 auth-input-group has-icon">
                    <label class="form-label" for="loginCaptcha">Zabezpieczenie po nieudanych próbach: <?= htmlspecialchars($captcha['question']) ?> = ?</label>
                    <div class="position-relative">
                        <i class="bi bi-shield-check auth-input-icon"></i>
                        <input type="text" name="login_captcha_answer" id="loginCaptcha" class="form-control" inputmode="numeric" pattern="-?[0-9]+" autocomplete="off" required>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label small text-muted" for="remember">Zapamiętaj mnie</label>
                    </div>
                    <a href="forgot_password.php" class="small text-primary text-decoration-none">Zapomniałeś hasła?</a>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-box-arrow-in-right me-1"></i>Zaloguj się</button>
                <button type="button" class="btn btn-outline-secondary w-100 mb-4 guest-mode-btn" onclick="loginPasskey()">
                    <i class="bi bi-fingerprint me-1"></i>Zaloguj przez Passkey
                </button>

                <div class="text-center">
                    <p class="small text-muted">Nie masz konta? <a href="register.php" class="text-primary text-decoration-none fw-semibold">Załóż je teraz</a></p>
                </div>
            </form>
            <form action="../actions/start_guest.php" method="POST" class="mt-3">
                <?php echo csrfTokenField('guest_start'); ?>
                <input type="hidden" name="target" value="test">
                <button type="submit" class="btn btn-outline-light guest-mode-btn w-100">
                    <i class="bi bi-incognito me-1"></i>Tryb gościa
                </button>
            </form>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/api-client.js"></script>
    <script src="../assets/js/app-dialogs.js"></script>
    <script src="../assets/js/kappicrypt.js?v=2"></script>
    <script src="../assets/js/auth.js"></script>
    <script>
    // Helper functions for WebAuthn
    function base64urlToBuffer(baseurl64) {
        return parseWebAuthnBinary(baseurl64);
    }

    function parseWebAuthnBinary(str) {
        if (typeof str !== 'string') return str;
        let b64 = str;
        if (str.startsWith('=?BINARY?B?') && str.endsWith('?=')) {
            b64 = str.substring(11, str.length - 2);
        }
        b64 = b64.replace(/\-/g, '+').replace(/_/g, '/');
        const padding = '=='.slice(0, (4 - b64.length % 4) % 4);
        b64 += padding;
        const raw = window.atob(b64);
        const buffer = new ArrayBuffer(raw.length);
        const view = new Uint8Array(buffer);
        for(let i=0; i<raw.length; i++) {
            view[i] = raw.charCodeAt(i);
        }
        return buffer;
    }

    function bufferToBase64(buffer) {
        const byteView = new Uint8Array(buffer);
        let str = '';
        for (const charCode of byteView) {
            str += String.fromCharCode(charCode);
        }
        return window.btoa(str);
    }

    async function loginPasskey() {
        if (!window.PublicKeyCredential) {
            window.appNotice('Twoja przeglądarka nie obsługuje kluczy Passkey.', 'danger');
            return;
        }

        try {
            // 1. Pobierz wyzwanie od serwera
            const formData = new FormData();
            formData.append('action', 'generate');

            const generateRes = await fetch('../ajax/passkey_login.php', {
                method: 'POST',
                body: formData
            });
            const generateData = await generateRes.json();

            if (generateData.status !== 'success') {
                throw new Error(generateData.message || 'Błąd generowania żądania.');
            }

            const publicKey = generateData.options.publicKey || generateData.options;

            // Konwersja base64 na Buffer dla pola challenge i allowCredentials
            if (publicKey.challenge) publicKey.challenge = parseWebAuthnBinary(publicKey.challenge);
            if (publicKey.allowCredentials) {
                for (let cred of publicKey.allowCredentials) {
                    cred.id = parseWebAuthnBinary(cred.id);
                }
            }

            // 2. Pobierz asercję (odcisk palca / PIN / Yubikey)
            const assertion = await navigator.credentials.get({ publicKey: publicKey });

            // 3. Wyślij do weryfikacji
            const verifyFormData = new FormData();
            verifyFormData.append('action', 'verify');
            verifyFormData.append('id', bufferToBase64(assertion.rawId));
            verifyFormData.append('clientDataJSON', bufferToBase64(assertion.response.clientDataJSON));
            verifyFormData.append('authenticatorData', bufferToBase64(assertion.response.authenticatorData));
            verifyFormData.append('signature', bufferToBase64(assertion.response.signature));
            if (assertion.response.userHandle) {
                verifyFormData.append('userHandle', bufferToBase64(assertion.response.userHandle));
            }

            const verifyRes = await fetch('../ajax/passkey_login.php', {
                method: 'POST',
                body: verifyFormData
            });
            const verifyData = await verifyRes.json();

            if (verifyData.status === 'success') {
                window.location.href = verifyData.redirect || '../index.php';
            } else {
                throw new Error(verifyData.message || 'Błąd podczas weryfikacji.');
            }
        } catch (err) {
            console.error(err);
            if (err.name === 'NotAllowedError') {
                window.appNotice('PassKey nie istnieje', 'danger');
            } else {
                window.appNotice(err.message, 'danger');
            }
        }
    }
    </script>
    <?php include __DIR__ . '/../includes/cookie_consent.php'; ?>
</body>
</html>
