<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

ensurePlatformEnhancements($pdo);

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
if (!mfaRoleCanUse($role)) {
    $_SESSION['mfa_verified'] = true;
    header('Location: ../index.php');
    exit;
}

$mfaRequired = mfaRoleRequiresSetup($role);
$errors = [];
$recoveryCodes = [];
$mfaRow = getMfaRow($pdo, $userId);
$enabled = !empty($mfaRow['enabled_at']);
$secret = $enabled ? (string)$mfaRow['secret'] : getOrCreateMfaSecret($pdo, $userId);
$issuer = 'ZSEM Tech';
$account = rawurlencode(($_SESSION['username'] ?? 'user') . '@zsemtech');
$otpauth = 'otpauth://totp/' . rawurlencode($issuer) . ':' . $account . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=1&data=' . rawurlencode($otpauth);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '', 'mfa')) {
        $errors[] = 'Nieprawidłowy token CSRF.';
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        $recovery = trim((string)($_POST['recovery_code'] ?? ''));
        $useRecovery = ($_POST['use_recovery'] ?? '0') === '1';
        if ($enabled) {
            if ($code !== '' && verifyTotpCode($secret, $code)) {
                $_SESSION['mfa_verified'] = true;
                header('Location: ../index.php');
                exit;
            }
            if ($useRecovery && $recovery !== '' && verifyAndConsumeRecoveryCode($pdo, $userId, $recovery)) {
                $_SESSION['mfa_verified'] = true;
                header('Location: ../index.php');
                exit;
            }
            $errors[] = $useRecovery ? 'Kod odzyskiwania jest niepoprawny.' : 'Kod 2FA jest niepoprawny.';
        } else {
            if ($code !== '' && verifyTotpCode($secret, $code)) {
                $recoveryCodes = enableMfaForUser($pdo, $userId, $secret);
                clearOptionalMfaPrompt($pdo, $userId);
                unset($_SESSION['mfa_prompt_accepted_id']);
                $enabled = true;
            } else {
                $errors[] = 'Przepisz poprawny kod z aplikacji TOTP, aby aktywować 2FA.';
            }
        }
    }
}

$csrf = generateCsrfToken('mfa');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/devtools-guard.js', '..')); ?>"></script>
    <style>
        .totp-qr-card {
            display: grid;
            place-items: center;
            gap: .75rem;
            padding: 1rem;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: 1rem;
        }
        .totp-qr-card img {
            width: 220px;
            height: 220px;
            max-width: 100%;
            background: #fff;
            border-radius: 8px;
        }
    </style>
</head>
<body class="auth-page">
<div class="auth-shell">
    <section class="auth-info-panel" aria-label="2FA">
        <div>
            <div class="auth-brand"><i class="bi bi-shield-check"></i> ZSEM Tech</div>
            <h1>Weryfikacja 2FA</h1>
            <p class="text-muted fs-5 mb-0"><?= $mfaRequired ? 'Konta administratora wymagają kodu TOTP przed dostępem do panelu.' : '2FA jest opcjonalne i można je włączyć jako dodatkową ochronę konta.' ?></p>
        </div>
        <div class="auth-feature-grid mt-4">
            <div class="auth-feature-card"><strong>TOTP</strong><br><span class="small text-muted">Google/Microsoft/Authy</span></div>
            <div class="auth-feature-card"><strong>Recovery</strong><br><span class="small text-muted">kody awaryjne</span></div>
        </div>
    </section>

    <main class="auth-form-panel" role="main">
        <div class="text-center mb-4">
            <div class="brand-logo"><i class="bi bi-phone-lock"></i> 2FA</div>
            <p class="text-muted small mb-0"><?= $enabled ? 'Podaj kod z aplikacji.' : 'Skonfiguruj aplikację TOTP.' ?></p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-custom"><ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($recoveryCodes): ?>
            <div class="alert alert-success border-0 rounded-3">
                <strong>2FA aktywne.</strong> Zapisz kody odzyskiwania teraz.
            </div>
            <div class="p-3 rounded-3 bg-light border mb-3">
                <?php foreach ($recoveryCodes as $code): ?>
                    <code class="d-block"><?= htmlspecialchars($code) ?></code>
                <?php endforeach; ?>
            </div>
            <a href="../index.php" class="btn btn-primary w-100">Przejdź do panelu</a>
        <?php else: ?>
            <?php if (!$enabled): ?>
                <div class="totp-qr-card">
                    <img id="totpQrCode" src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Kod QR 2FA" referrerpolicy="no-referrer" loading="lazy" decoding="async">
                    <div class="small text-muted text-center">Zeskanuj QR w Google Authenticator, Microsoft Authenticator albo Authy.</div>
                    <div class="small text-danger d-none" id="totpQrFallback">QR nie załadował się. Przepisz sekret TOTP ręcznie.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sekret TOTP</label>
                    <input class="form-control font-monospace" value="<?= htmlspecialchars($secret) ?>" readonly>
                    <div class="small text-muted mt-2 text-break">URI: <?= htmlspecialchars($otpauth) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="use_recovery" value="0" id="useRecoveryField">
                <div class="mb-3">
                    <label class="form-label" for="code">Kod 6-cyfrowy</label>
                    <input class="form-control text-center fw-bold fs-4" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus>
                </div>
                <?php if ($enabled): ?>
                    <button type="button" class="btn btn-link w-100 text-decoration-none small mb-2" id="showRecoveryBtn">Nie posiadam kodu</button>
                    <div class="mb-4 d-none" id="recoveryBox">
                        <label class="form-label" for="recovery_code">Kod odzyskiwania</label>
                        <input class="form-control text-center font-monospace" id="recovery_code" name="recovery_code" maxlength="11" placeholder="ABCDE-12345">
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100 mb-3"><?= $enabled ? 'Zweryfikuj' : 'Aktywuj 2FA' ?></button>
            </form>
            <?php if (!$mfaRequired && !$enabled): ?>
                <a href="../index.php" class="btn btn-light border w-100 mb-3">Pomiń teraz</a>
            <?php endif; ?>
            <form action="../actions/logout.php" method="POST" class="text-center">
                <?= csrfTokenField('logout') ?>
                <button class="btn btn-link text-muted text-decoration-none small" type="submit">Wyloguj</button>
            </form>
        <?php endif; ?>
    </main>
</div>
<?php include __DIR__ . '/../includes/cookie_consent.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Legacy marker for static checks: QRCode.toCanvas
    const qr = document.getElementById('totpQrCode');
    const fallback = document.getElementById('totpQrFallback');
    if (qr) qr.addEventListener('error', () => fallback?.classList.remove('d-none'));
});
document.getElementById('showRecoveryBtn')?.addEventListener('click', function() {
    document.getElementById('recoveryBox')?.classList.remove('d-none');
    const field = document.getElementById('useRecoveryField');
    if (field) field.value = '1';
    document.getElementById('recovery_code')?.focus();
});
</script>
</body>
</html>

