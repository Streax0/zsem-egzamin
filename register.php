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
$username = $email = $first_name = $last_name = $class_suffix = '';
$class_year = '';
$apply_teacher = false;
$teacher_motivation = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) $errors[] = 'Nieprawidłowy token CSRF.';

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $class_year = $_POST['class_year'] ?? '';
    $class_suffix = trim($_POST['class_suffix'] ?? '');
    $terms = $_POST['terms'] ?? '';
    $privacy_consent = $_POST['privacy_consent'] ?? '';
    $apply_teacher = ($_POST['apply_teacher'] ?? '') === 'on';
    $teacher_motivation = trim((string)($_POST['teacher_motivation'] ?? ''));
    $classParts = normalizeClassParts($class_year, $class_suffix);

    if ($username === '') {
        $errors[] = 'Nazwa użytkownika jest wymagana.';
    }

    if ($username !== '') {
        if (mb_strlen($username, 'UTF-8') < 3 || mb_strlen($username, 'UTF-8') > 16 || !preg_match('/^[A-Za-z0-9_.-]{3,16}$/', $username)) {
            $errors[] = 'Login musi mieć 3-16 znaków i może zawierać litery, cyfry, kropkę, myślnik oraz podkreślenie.';
        }
    }
    if (mb_strlen($email, 'UTF-8') > 100) $errors[] = 'Adres e-mail jest za długi.';
    if (mb_strlen($first_name, 'UTF-8') > 50 || mb_strlen($last_name, 'UTF-8') > 50) $errors[] = 'Imię i nazwisko mogą mieć maksymalnie 50 znaków.';
    if (containsProfanity($username) || containsProfanity($email) || containsProfanity($first_name) || containsProfanity($last_name) || containsProfanity($class_suffix)) {
        $errors[] = 'Dane rejestracyjne zawierają niedozwolone słowa.';
    }
    if (!validateAllowedEmail($email)) $errors[] = 'Niepoprawny adres e-mail lub niedozwolona domena pocztowa.';
    $passwordErrors = validatePasswordPolicy($password);
    foreach ($passwordErrors as $passwordError) $errors[] = $passwordError;
    if ($password !== $confirm_password) $errors[] = 'Hasła nie są identyczne.';
    if (empty($first_name) || empty($last_name)) $errors[] = 'Imię i nazwisko są wymagane.';
    if (!$classParts) $errors[] = 'Klasa może być pusta / nie dotyczy albo z zakresu 1-5 z oznaczeniem do 2 liter.';
    if ($terms !== 'on') $errors[] = 'Musisz zaakceptować regulamin.';
    if ($privacy_consent !== 'on') $errors[] = 'Zgoda RODO jest wymagana do rejestracji.';
    if ($apply_teacher) {
        $errors = array_merge($errors, validateTeacherMotivationLimits($teacher_motivation));
        if ($teacher_motivation !== '' && containsProfanity($teacher_motivation)) {
            $errors[] = 'Uzasadnienie aplikacji zawiera niedozwolone słowa.';
        }
    }

    if (empty($errors)) {
        try {
            $availabilityStmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
            $availabilityStmt->execute([$username, mb_strtolower($email, 'UTF-8')]);
            $existingAccount = $availabilityStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingAccount) {
                if (strcasecmp((string)$existingAccount['username'], $username) === 0) {
                    $suggestions = registrationUsernameSuggestions($pdo, $username, 3);
                    $suffix = $suggestions ? ' Propozycje: ' . implode(', ', $suggestions) . '.' : '';
                    $errors[] = 'Ta nazwa użytkownika jest już zajęta. Wybierz inną.' . $suffix;
                }
                if (strcasecmp((string)$existingAccount['email'], $email) === 0) {
                    $errors[] = 'Ten adres e-mail jest już używany. Zaloguj się albo użyj innego adresu.';
                }
            }
        } catch (PDOException $e) {
            error_log('Registration availability check failed: ' . $e->getMessage());
            $errors[] = 'Nie udało się sprawdzić dostępności loginu i e-maila. Spróbuj ponownie.';
        }
    }

    if (empty($errors)) {
        if (isIpBanned(authClientIpAddress())) {
            $errors[] = 'Rejestracja z Twojego adresu IP została zablokowana.';
        }
        if (isEmailBanned($email)) {
            $errors[] = 'Ten adres e-mail jest zablokowany.';
        }

        if (empty($errors)) {
            $newUserId = register($username, $email, $password, $first_name, $last_name, $classParts['year'], $classParts['suffix'], true);
            if ($newUserId) {
                if ($apply_teacher) {
                    createTeacherApplicationRequest($pdo, (int)$newUserId, $teacher_motivation);
                }
                clearGuestSessionState();
                if (function_exists('regenerateSessionId')) {
                    regenerateSessionId(true);
                }
                $_SESSION['user_id'] = (int)$newUserId;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                $_SESSION['session_version'] = 1;
                $_SESSION['mfa_enabled'] = false;
                $_SESSION['mfa_verified'] = true;
                registerCurrentUserSession($pdo, (int)$newUserId);
                updateLastLogin((int)$newUserId, authClientIpAddress());
                setSessionMessage('success', $apply_teacher ? 'Konto zostało utworzone. Aplikacja na nauczyciela trafiła do administracji. Jesteś już zalogowany jako zwykłe konto.' : 'Konto zostało utworzone. Jesteś już zalogowany.');
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Nie można założyć konta: login lub e-mail może być zajęty, hasło może nie spełniać zasad albo z tego adresu IP utworzono już limit kont.';
            }
        }
    }
}
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/auth.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/api-client.js')); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/register.js')); ?>" defer></script>
</head>
<body class="auth-page">
    <div class="auth-shell auth-shell-register">
        <section class="auth-info-panel" aria-label="ZSEM Tech">
            <div>
                <div class="auth-brand"><i class="bi bi-mortarboard-fill"></i> ZSEM Tech</div>
                <h1>Dołącz do panelu nauki</h1>
                <p class="text-muted fs-5 mb-0">Załóż konto ucznia, rozwiązuj testy, zbieraj XP i dołączaj do sprawdzianów nauczyciela.</p>
            </div>
            <div class="auth-feature-grid mt-4">
                <div class="auth-feature-card"><strong>4100 XP</strong><br><span class="small text-muted">startowy poziom konta</span></div>
                <div class="auth-feature-card"><strong>INF.02+</strong><br><span class="small text-muted">kwalifikacje i arkusze</span></div>
                <div class="auth-feature-card"><strong>Rankingi</strong><br><span class="small text-muted">postęp po testach</span></div>
                <div class="auth-feature-card"><strong>Sprawdziany</strong><br><span class="small text-muted">sesje nauczyciela</span></div>
            </div>
        </section>

    <main class="auth-form-panel register-form-panel" role="main">
        <div class="text-center mb-4">
            <div class="brand-logo"><i class="bi bi-person-plus-fill"></i> Rejestracja</div>
            <p class="text-muted small">Stwórz darmowe konto i zacznij naukę.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-custom mb-4">
                <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="row g-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="first_name">Imię</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" placeholder="Jan" value="<?= htmlspecialchars($first_name) ?>" maxlength="50" autocomplete="given-name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="last_name">Nazwisko</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" placeholder="Kowalski" value="<?= htmlspecialchars($last_name) ?>" maxlength="50" autocomplete="family-name" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="regUsername">Nazwa użytkownika</label>
                <input type="text" name="username" id="regUsername" class="form-control" placeholder="np. test53" value="<?= htmlspecialchars($username) ?>" minlength="3" maxlength="16" pattern="[A-Za-z0-9_.-]{3,16}" autocomplete="username" aria-describedby="generatedUsernamePreview usernameFeedback" required>
                <div id="generatedUsernamePreview" class="form-text" hidden></div>
                <div id="usernameFeedback" class="small mt-1"></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Adres e-mail</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="jan.kowalski@gmail.com" value="<?= htmlspecialchars($email) ?>" maxlength="100" autocomplete="email" required>
                <div id="emailFeedback" class="small mt-1"></div>
            </div>

            <div class="row g-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="classYear">Klasa</label>
                    <select name="class_year" id="classYear" class="form-control">
                        <option value="" <?= $class_year === '' ? 'selected' : '' ?>>Nie dotyczy</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= (string)$class_year === (string)$i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="classSuffix">Oznaczenie klasy</label>
                    <input type="text" name="class_suffix" id="classSuffix" class="form-control" maxlength="2" pattern="[A-Za-z]{0,2}" placeholder="np. TI" value="<?= htmlspecialchars($class_suffix) ?>">
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6 mb-3 position-relative password-field">
                    <label class="form-label" for="regPassword">Hasło</label>
                    <input type="password" name="password" id="regPassword" class="form-control" placeholder="••••••" minlength="6" maxlength="128" autocomplete="new-password" required aria-describedby="passwordPolicy">
                    <button type="button" class="auth-password-toggle" data-password-toggle="regPassword" aria-label="Pokaż hasło" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    <div class="strength-meter" role="meter" aria-label="Siła hasła" aria-valuemin="0" aria-valuemax="5"><div id="strengthBar" class="strength-meter-bar"></div></div>
                    <div id="passwordPolicy" class="password-policy-single small mt-2" aria-live="polite">
                        <i class="bi bi-shield-lock"></i>
                        <span id="passwordPolicyMessage">Wpisz hasło, aby sprawdzić wymagania.</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3 position-relative password-field">
                    <label class="form-label" for="confirm_password">Powtórz hasło</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••" minlength="6" maxlength="128" autocomplete="new-password" required>
                    <button type="button" class="auth-password-toggle" data-password-toggle="confirm_password" aria-label="Pokaż hasło" aria-pressed="false"><i class="bi bi-eye"></i></button>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check mb-3">
                    <input type="checkbox" name="apply_teacher" class="form-check-input" id="applyTeacher" <?php echo $apply_teacher ? 'checked' : ''; ?>>
                    <label class="form-check-label small text-muted" for="applyTeacher">Chcę zaaplikować na stanowisko nauczyciela</label>
                </div>
                <div class="mb-3 <?php echo $apply_teacher ? '' : 'd-none'; ?>" id="teacherMotivationWrap">
                    <label class="form-label" for="teacherMotivation">Uzasadnienie aplikacji</label>
                    <textarea name="teacher_motivation" id="teacherMotivation" class="form-control" maxlength="2200" rows="4" style="resize:vertical; min-height:110px; max-height:180px; overflow:auto;" placeholder="Napisz krótko, dlaczego konto ma otrzymać uprawnienia nauczyciela."><?php echo htmlspecialchars($teacher_motivation); ?></textarea>
                    <div class="small text-muted mt-1" id="teacherMotivationHelp">Maksymalnie 100 słów, każde słowo do 20 znaków. Powód jest opcjonalny.</div>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="terms" class="form-check-input" id="terms" required>
                    <label class="form-check-label small text-muted" for="terms">Akceptuję <a href="terms.php" target="_blank" class="text-primary">Regulamin</a> i <a href="privacy.php" target="_blank" class="text-primary">Politykę prywatności</a></label>
                </div>
                <div class="form-check mt-2">
                    <input type="checkbox" name="privacy_consent" class="form-check-input" id="privacyConsent" required>
                    <label class="form-check-label small text-muted" for="privacyConsent">Wyrażam zgodę na przetwarzanie moich danych osobowych zgodnie z <a href="privacy.php" target="_blank" class="text-primary">Polityką Prywatności</a> w celu rejestracji.</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">Zarejestruj się</button>

            <div class="text-center">
                <p class="small text-muted">Masz już konto? <a href="login.php" class="text-primary text-decoration-none fw-semibold">Zaloguj się</a></p>
            </div>
        </form>
    </main>
    </div>
    <?php include __DIR__ . '/includes/cookie_consent.php'; ?>
    <script>
    document.getElementById('applyTeacher')?.addEventListener('change', function() {
        document.getElementById('teacherMotivationWrap')?.classList.toggle('d-none', !this.checked);
    });
    </script>
</body>
</html>
