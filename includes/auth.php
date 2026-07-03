<?php
/**
 * Authentication functions for the application
 *
 * This file contains all authentication-related functions including:
 * - Login/logout management
 * - User registration
 * - Session handling
 * - CSRF protection (uses session.php implementations)
 * - Rate limiting for login attempts
 */

// Require database configuration to get $pdo connection
require_once __DIR__ . '/../config/db.php';

// Require session management for CSRF tokens and session functions
require_once __DIR__ . '/../includes/session.php';

// Start session if not already started (handled by session.php)

/**
 * Verify Cloudflare Turnstile token
 * 
 * @param string $token Token from form
 * @return bool True if valid
 */
function verifyTurnstile($token) {
    if (empty($token)) return false;

    $secret = getenv('TURNSTILE_SECRET') ?: ($_ENV['TURNSTILE_SECRET'] ?? '');
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($remoteAddr, ['127.0.0.1', '::1'], true);

    if ($secret === '') {
        if ($isLocal && (defined('APP_ENV') ? APP_ENV : 'local') === 'local') {
            error_log('Turnstile: TURNSTILE_SECRET is not configured; bypassing only for local development.');
            return true;
        }
        error_log('Turnstile: TURNSTILE_SECRET is not configured.');
        return false;
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    
    $data = [
        'secret' => $secret,
        'response' => $token,
        'remoteip' => securityClientIp()
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5 // 5 seconds timeout
        ]
    ];
    
    $context  = stream_context_create($options);
    
    // Use @ to suppress warnings if no internet
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        if ($isLocal && (defined('APP_ENV') ? APP_ENV : 'local') === 'local') {
            error_log("Turnstile: Verification request failed; bypassing only for local development.");
            return true;
        }
        return false;
    }
    
    $response = json_decode($result);
    return isset($response->success) && $response->success;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function clearGuestSessionState(): void {
    unset(
        $_SESSION['guest_mode'],
        $_SESSION['guest_started_at'],
        $_SESSION['guest_test_results'],
        $_SESSION['last_guest_result_id'],
        $_SESSION['guest_exam_participants'],
        $_SESSION['current_test']
    );
}

function isGuestMode(): bool {
    return !isset($_SESSION['user_id']) && !empty($_SESSION['guest_mode']);
}

function startGuestSession(): void {
    if (function_exists('regenerateSessionId')) {
        regenerateSessionId(true);
    }
    clearGuestSessionState();
    unset(
        $_SESSION['user_id'],
        $_SESSION['session_version'],
        $_SESSION['mfa_enabled'],
        $_SESSION['mfa_verified']
    );
    $_SESSION['guest_mode'] = true;
    $_SESSION['guest_started_at'] = time();
    $_SESSION['username'] = 'Gość';
    $_SESSION['role'] = 'guest';
}

function guestExamParticipantId(int $sessionId): int {
    return (int)($_SESSION['guest_exam_participants'][$sessionId] ?? 0);
}

function rememberGuestExamParticipant(int $sessionId, int $participantId): void {
    if ($sessionId > 0 && $participantId > 0) {
        $_SESSION['guest_exam_participants'][$sessionId] = $participantId;
    }
}

function ensureActiveSessionTable(PDO $pdo): void {
    if (!appRuntimeSchemaUpdatesEnabled()) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS active_user_sessions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_hash CHAR(64) NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent_hash CHAR(64) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_session (user_id, session_hash),
        INDEX idx_user_last_seen (user_id, last_seen),
        INDEX idx_last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function currentSessionHash(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }
    $sessionId = session_id();
    return $sessionId !== '' ? hash('sha256', $sessionId) : '';
}

function authClientIpAddress(): string {
    $ip = securityClientIp();
    return $ip === '0.0.0.0' ? 'unknown' : $ip;
}

function enforceUserSessionLimit(PDO $pdo, int $userId, int $maxSessions = 2): void {
    if ($userId <= 0) {
        return;
    }

    $currentHash = currentSessionHash();
    if ($currentHash === '') {
        return;
    }

    $maxSessions = max(1, min(10, $maxSessions));
    $stmt = $pdo->prepare('
        SELECT session_hash
        FROM active_user_sessions
        WHERE user_id = ?
        ORDER BY (session_hash = ?) DESC, last_seen DESC, id DESC
    ');
    $stmt->execute([$userId, $currentHash]);
    $hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $remove = array_slice($hashes, $maxSessions);

    if (!$remove) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($remove), '?'));
    $delete = $pdo->prepare("DELETE FROM active_user_sessions WHERE user_id = ? AND session_hash IN ($placeholders)");
    $delete->execute(array_merge([$userId], $remove));
}

function registerCurrentUserSession(PDO $pdo, int $userId, int $maxSessions = 2): bool {
    if ($userId <= 0) {
        return false;
    }

    $sessionHash = currentSessionHash();
    if ($sessionHash === '') {
        return false;
    }

    try {
        ensureActiveSessionTable($pdo);
        $pdo->prepare('DELETE FROM active_user_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 DAY)')->execute();
        $ip = authClientIpAddress();
        $userAgentHash = hash('sha256', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
        $stmt = $pdo->prepare('
            INSERT INTO active_user_sessions (user_id, session_hash, ip_address, user_agent_hash, last_seen)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address), user_agent_hash = VALUES(user_agent_hash), last_seen = NOW()
        ');
        $stmt->execute([$userId, $sessionHash, $ip, $userAgentHash]);
        enforceUserSessionLimit($pdo, $userId, $maxSessions);
        return true;
    } catch (Throwable $e) {
        error_log('Failed to register active session: ' . $e->getMessage());
        return false;
    }
}

function validateCurrentUserSession(PDO $pdo, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }

    $sessionHash = currentSessionHash();
    if ($sessionHash === '') {
        return false;
    }

    try {
        ensureActiveSessionTable($pdo);
        $lastCleanup = (int)($_SESSION['active_session_cleanup_at'] ?? 0);
        if ($lastCleanup < time() - 3600) {
            $pdo->prepare('DELETE FROM active_user_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 DAY)')->execute();
            $_SESSION['active_session_cleanup_at'] = time();
        }

        $stmt = $pdo->prepare('SELECT UNIX_TIMESTAMP(last_seen) FROM active_user_sessions WHERE user_id = ? AND session_hash = ? LIMIT 1');
        $stmt->execute([$userId, $sessionHash]);
        $lastSeen = $stmt->fetchColumn();
        if ($lastSeen === false) {
            return false;
        }

        if ((int)$lastSeen < time() - 300) {
            $touch = $pdo->prepare('UPDATE active_user_sessions SET last_seen = NOW() WHERE user_id = ? AND session_hash = ? AND last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
            $touch->execute([$userId, $sessionHash]);
        }
        return true;
    } catch (Throwable $e) {
        error_log('Failed to validate active session: ' . $e->getMessage());
        return false;
    }
}

function forgetCurrentUserSession(PDO $pdo, int $userId): void {
    $sessionHash = currentSessionHash();
    if ($userId <= 0 || $sessionHash === '') {
        return;
    }

    try {
        ensureActiveSessionTable($pdo);
        $stmt = $pdo->prepare('DELETE FROM active_user_sessions WHERE user_id = ? AND session_hash = ?');
        $stmt->execute([$userId, $sessionHash]);
    } catch (Throwable $e) {
        error_log('Failed to forget active session: ' . $e->getMessage());
    }
}

function forgetAllUserSessions(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        return;
    }

    try {
        ensureActiveSessionTable($pdo);
        $stmt = $pdo->prepare('DELETE FROM active_user_sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
    } catch (Throwable $e) {
        error_log('Failed to forget all active sessions: ' . $e->getMessage());
    }
}

/**
 * Sync current session's role from the database, if the user is logged in.
 * This ensures UI links and permission guards use the latest role value.
 *
 * @return void
 */
function syncSessionUserRole() {
    if (!isLoggedIn()) {
        return;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        error_log('Session role sync has no database connection.');
        http_response_code(503);
        echo 'Usługa uwierzytelniania jest chwilowo niedostępna.';
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT role, session_version FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $prefix = file_exists('config/db.php') ? '' : '../';
            destroySession(true, $prefix . 'auth/login.php?session_expired=1');
        }
        if ($row && isset($row['role'])) {
            $previousRole = $_SESSION['role'] ?? 'user';
            $_SESSION['role'] = $row['role'];
            if ($previousRole !== $row['role'] && mfaRoleRequiresSetup($row['role'])) {
                $_SESSION['mfa_verified'] = false;
            }
            $prefix = file_exists('config/db.php') ? '' : '../';
            if (isset($row['session_version'])) {
                $dbVersion = (int)$row['session_version'];
                $sessionVersion = (int)($_SESSION['session_version'] ?? $dbVersion);
                if ($sessionVersion !== $dbVersion) {
                    destroySession(true, $prefix . 'auth/login.php');
                }
                $_SESSION['session_version'] = $dbVersion;
            }
            if (!validateCurrentUserSession($pdo, (int)$_SESSION['user_id'])) {
                destroySession(true, $prefix . 'auth/login.php?session_expired=1');
            }
        }
    } catch (Throwable $e) {
        error_log('Failed to sync session role: ' . $e->getMessage());
        http_response_code(503);
        echo 'Usługa uwierzytelniania jest chwilowo niedostępna.';
        exit;
    }
}

function requireJsonLogin(
    bool $allowGuest = false,
    array $roles = [],
    ?array $unauthorizedPayload = null,
    ?array $forbiddenPayload = null
): void {
    $unauthorizedPayload = $unauthorizedPayload ?? ['success' => false, 'error' => 'Unauthorized'];
    $forbiddenPayload = $forbiddenPayload ?? ['success' => false, 'error' => 'Forbidden'];

    if ($allowGuest && isGuestMode()) {
        return;
    }

    if (!isLoggedIn()) {
        http_response_code(401);
        echo function_exists('securityJsonEncode') ? securityJsonEncode($unauthorizedPayload) : json_encode($unauthorizedPayload);
        exit;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        http_response_code(503);
        $payload = ['success' => false, 'error' => 'Authentication service unavailable'];
        echo function_exists('securityJsonEncode') ? securityJsonEncode($payload) : json_encode($payload);
        exit;
    }
    try {
        $stmt = $pdo->prepare('SELECT role, session_version FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbVersion = (int)($row['session_version'] ?? 0);
        $sessionVersion = (int)($_SESSION['session_version'] ?? $dbVersion);

        if (!$row || ($dbVersion > 0 && $sessionVersion !== $dbVersion) || !validateCurrentUserSession($pdo, (int)$_SESSION['user_id'])) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            http_response_code(401);
            echo function_exists('securityJsonEncode') ? securityJsonEncode($unauthorizedPayload) : json_encode($unauthorizedPayload);
            exit;
        }

        $_SESSION['role'] = $row['role'] ?? ($_SESSION['role'] ?? 'user');
        if ($dbVersion > 0) {
            $_SESSION['session_version'] = $dbVersion;
        }
    } catch (Throwable $e) {
        error_log('JSON auth guard failed: ' . $e->getMessage());
        http_response_code(503);
        $payload = ['success' => false, 'error' => 'Authentication service unavailable'];
        echo function_exists('securityJsonEncode') ? securityJsonEncode($payload) : json_encode($payload);
        exit;
    }

    if (function_exists('mfaAccessRequired') && mfaAccessRequired()) {
        http_response_code(403);
        echo function_exists('securityJsonEncode') ? securityJsonEncode($forbiddenPayload) : json_encode($forbiddenPayload);
        exit;
    }

    if ($roles && !in_array($_SESSION['role'] ?? 'user', $roles, true)) {
        http_response_code(403);
        echo function_exists('securityJsonEncode') ? securityJsonEncode($forbiddenPayload) : json_encode($forbiddenPayload);
        exit;
    }
}

/**
 * Require user to be logged in, redirect to login page if not
 * 
 * Redirects to login.php with return URL if user is not logged in
 */
if (!function_exists('requireLogin')) {
    function requireLogin(bool $allowGuest = false) {
        if ($allowGuest && isGuestMode()) {
            return;
        }

        if (!isLoggedIn()) {
            $return_url = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            $script = $_SERVER['PHP_SELF'] ?? '';
            $prefix = file_exists('config/db.php') ? '' : '../';

            http_response_code(401);
            $login_url = $prefix . 'auth/login.php?return=' . $return_url;
            $home_url = $prefix . 'index.php';
            echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>Wymagane logowanie - ZSEM Tech</title>';
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">';
            echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">';
            echo '<link href="' . $prefix . 'assets/css/fonts.css" rel="stylesheet">';
            echo '<link rel="stylesheet" href="' . $prefix . 'assets/css/auth.css">';
            echo '<style>';
            echo 'body.auth-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }';
            echo '.auth-shell { min-height: unset; height: auto; max-width: 900px; width: 100%; }';
            echo '.auth-form-panel { padding: 3rem 2rem; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }';
            echo '.blocked-icon { font-size: 4.5rem; color: var(--color-primary, #4f46e5); margin-bottom: 1.5rem; line-height: 1; }';
            echo '.blocked-badge { display: inline-block; padding: 0.4em 0.8em; font-size: 0.85em; font-weight: 700; color: #fff; background-color: #ef4444; border-radius: 50rem; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.05em; }';
            echo '.btn-action { font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 0.75rem; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 100%; margin-bottom: 0.75rem; }';
            echo '.auth-info-panel h1 { font-size: 2.2rem; margin-bottom: 1rem; }';
            echo '</style>';
            echo '</head><body class="auth-page">';
            echo '<div class="auth-shell">';
            echo '<section class="auth-info-panel" aria-label="ZSEM Tech">';
            echo '<div>';
            echo '<div class="auth-brand"><i class="bi bi-mortarboard-fill"></i> ZSEM Tech</div>';
            echo '<h1>Zaloguj się, aby kontynuować</h1>';
            echo '<p class="text-muted fs-5 mb-0">Ta strona jest dostępna tylko dla zalogowanych użytkowników. Dołącz do nas, aby uzyskać pełny dostęp.</p>';
            echo '</div>';
            echo '<div class="auth-feature-grid mt-4">';
            echo '<div class="auth-feature-card"><strong>Testy</strong><br><span class="small text-muted">INF.02 i arkusze</span></div>';
            echo '<div class="auth-feature-card"><strong>Wyniki</strong><br><span class="small text-muted">postęp i ranking</span></div>';
            echo '</div>';
            echo '</section>';
            echo '<main class="login-card auth-form-panel" role="main">';
            echo '<div class="blocked-icon"><i class="bi bi-shield-lock-fill"></i></div>';
            echo '<span class="blocked-badge">Dostęp ograniczony</span>';
            echo '<h1 class="h3 fw-bold mb-3 text-white">Wymagane logowanie</h1>';
            echo '<p class="text-muted mb-4">Wymagane jest uwierzytelnienie, aby wyświetlić tę stronę.</p>';
            echo '<div class="w-100" style="max-width: 320px;">';
            echo '<a class="btn btn-primary btn-action" href="' . htmlspecialchars($login_url, ENT_QUOTES) . '"><i class="bi bi-box-arrow-in-right me-2"></i>Zaloguj się</a>';
            echo '<a class="btn btn-outline-light btn-action" href="' . htmlspecialchars($home_url, ENT_QUOTES) . '"><i class="bi bi-house me-2"></i>Strona główna</a>';
            echo '</div>';
            echo '</main>';
            echo '</div>';
            echo '</body></html>';
            exit();
        }

        syncSessionUserRole();
        if (function_exists('mfaAccessRequired') && mfaAccessRequired()) {
            $script = $_SERVER['PHP_SELF'] ?? '';
            $prefix = (strpos($script, '/teacher/') !== false || strpos($script, '/exam/') !== false || strpos($script, '/duels/') !== false || strpos($script, '/actions/') !== false || strpos($script, '/ajax/') !== false) ? '../' : '';
            header('Location: ' . $prefix . 'auth/mfa.php');
            exit();
        }

        global $pdo;
        if ($pdo instanceof PDO && function_exists('enforceFeaturePageBlockForCurrentRequest')) {
            enforceFeaturePageBlockForCurrentRequest($pdo);
        }
    }
}

/**
 * Attempt to log in user with username and password
 * 
 * @param string $username Username or email
 * @param string $password Plain text password
 * @param bool $remember Remember me option (not implemented in session, but available for cookie implementation)
 * @return array|false User data on success, false on failure
 */
function login($username, $password, $remember = false) {
    global $pdo;

    $ip = authClientIpAddress();
    $username = trim((string)$username);
    if (function_exists('ensurePlatformEnhancements')) {
        ensurePlatformEnhancements($pdo);
    }

    if (isIpBanned($ip)) {
        return ['success' => false, 'message' => 'Dostęp z Twojego adresu IP został zablokowany.'];
    }

    // Check rate limiting before attempting login
    if (checkRateLimit($ip, $username)) {
        return ['success' => false, 'message' => 'Po 4 nieudanych próbach poczekaj 30 sekund i spróbuj ponownie.'];
    }

    // Find user by username or email
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1');
    $stmt->execute(['username' => $username, 'email' => $username]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password_hash'])) {
        // Check if user is banned
        if (isset($user['is_banned']) && $user['is_banned'] == 1) {
            if (clearExpiredBanForUser($pdo, $user)) {
                $user['is_banned'] = 0;
                $user['ban_expires_at'] = null;
            } elseif (userBanIsActive($pdo, (int)$user['id'])) {
                $until = !empty($user['ban_expires_at']) ? ' do ' . date('d.m.Y H:i', strtotime((string)$user['ban_expires_at'])) : '';
                return ['success' => false, 'message' => 'Twoje konto zostało zablokowane' . $until . '. Skontaktuj się z administratorem.'];
            }
        }

        // Check if user is verified (if the column exists)
        /*
        if (isset($user['is_verified']) && $user['is_verified'] == 0) {
            return ['success' => false, 'message' => 'Twoje konto nie zostało jeszcze zweryfikowane. Sprawdź e-mail.';
        }
        */

        upgradePasswordHashIfNeeded($pdo, $user, $password);

        // Clear any previous failed login attempts on successful login
        clearLoginAttempts($ip);

        // Update login metadata
        updateLastLogin($user['id'], $ip);

        // Store user data in session
        clearGuestSessionState();
        if (function_exists('regenerateSessionId')) {
            regenerateSessionId(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
        $_SESSION['mfa_enabled'] = mfaUserHasEnabled($pdo, (int)$user['id']);
        $_SESSION['mfa_verified'] = !(mfaRoleRequiresSetup($user['role'] ?? 'user') || !empty($_SESSION['mfa_enabled']));

        return [
            'success' => true,
            'user_id' => $user['id'],
            'user' => $user,
            'mfa_required' => mfaRoleRequiresSetup($user['role'] ?? 'user') || !empty($_SESSION['mfa_enabled'])
        ];
    } else {
        // Record failed login attempt
        recordLoginAttempt($ip, false);
        return ['success' => false, 'message' => 'Nieprawidłowe dane logowania.'];
    }
}

/**
 * Register new user account
 * 
 * @param string $username Unique username
 * @param string $email User email address
 * @param string $password Plain text password
 * @param string|null $firstName Optional first name
 * @param string|null $lastName Optional last name
 * @return int|false User ID on success, false on failure
 */
function register($username, $email, $password, $firstName = null, $lastName = null, $classYear = null, $classSuffix = null, bool $enforceUsernameLength = true) {
    global $pdo;

    $username = trim((string)$username);
    $email = trim(mb_strtolower((string)$email, 'UTF-8'));
    $firstName = trim((string)$firstName);
    $lastName = trim((string)$lastName);
    $usernamePattern = $enforceUsernameLength ? '/^[A-Za-z0-9_.-]{3,16}$/' : '/^[A-Za-z0-9_.-]{3,50}$/';
    if (!preg_match($usernamePattern, $username) || mb_strlen($email, 'UTF-8') > 100 || mb_strlen($firstName, 'UTF-8') > 50 || mb_strlen($lastName, 'UTF-8') > 50) {
        return false;
    }
    if (function_exists('containsProfanity') && (containsProfanity($username) || containsProfanity($email) || containsProfanity($firstName) || containsProfanity($lastName))) {
        return false;
    }
    if (function_exists('validatePasswordPolicy') && validatePasswordPolicy($password) !== []) {
        return false;
    }
    
    $ip = authClientIpAddress();
    if (!empty($ip) && isIpBanned($ip)) {
        return false;
    }
    if (checkRegistrationRateLimit($ip, $email)) {
        return false;
    }
    if (isEmailBanned($email)) {
        return false;
    }

    try {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Default role is 'user'
        $role = 'user';
        
        // Verification token
        $verification_token = bin2hex(secureRandomBytes(32));
        
        $classParts = normalizeClassParts($classYear, $classSuffix);
        if (!$classParts) return false;

        if (function_exists('ensurePlatformEnhancements')) {
            ensurePlatformEnhancements($pdo);
        }
        if (registrationIpAccountLimitReached($pdo, $ip, 2)) {
            recordRegistrationAttempt($ip, $email, false);
            return false;
        }

        $hasVerifiedAt = function_exists('dbColumnExists') && dbColumnExists($pdo, 'users', 'verified_at');
        $hasRegistrationIp = function_exists('dbColumnExists') && dbColumnExists($pdo, 'users', 'registration_ip');
        $columns = 'username, email, password_hash, role, first_name, last_name, class, class_year, class_suffix, verification_token, is_verified, xp, created_at';
        $values = ':username, :email, :password_hash, :role, :first_name, :last_name, :class_label, :class_year, :class_suffix, :token, 0, 4100, NOW()';
        if ($hasVerifiedAt) {
            $columns .= ', verified_at, verified_by_admin_id';
            $values .= ', NULL, NULL';
        }
        if ($hasRegistrationIp) {
            $columns .= ', registration_ip';
            $values .= ', :registration_ip';
        }

        $stmt = $pdo->prepare("INSERT INTO users ($columns) VALUES ($values)");
        $params = [
            'username' => $username,
            'email' => $email,
            'password_hash' => $password_hash,
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'class_label' => $classParts['label'],
            'class_year' => $classParts['year'],
            'class_suffix' => $classParts['suffix'],
            'token' => $verification_token
        ];
        if ($hasRegistrationIp) {
            $params['registration_ip'] = $ip ?: null;
        }
        $stmt->execute($params);
        
        $userId = $pdo->lastInsertId();
        recordRegistrationAttempt($ip, $email, true);
        
        /*
        if ($userId) {
            sendVerificationEmail($email, $verification_token);
        }
        */
        
        return $userId;
    } catch (PDOException $e) {
        recordRegistrationAttempt($ip, $email, false);
        // Handle duplicate entry or other database errors
        if ($e->getCode() == 23000) {
            // Duplicate username or email
            return false;
        }
        // Other database error
        error_log('Registration error: ' . $e->getMessage());
        return false;
    }
}

function registrationIpAccountLimitReached(PDO $pdo, string $ip, int $limit = 2): bool {
    if ($ip === '' || $ip === 'unknown' || $limit < 1) {
        return false;
    }
    try {
        if (function_exists('ensurePlatformEnhancements')) {
            ensurePlatformEnhancements($pdo);
        }
        if (!function_exists('dbColumnExists') || !dbColumnExists($pdo, 'users', 'registration_ip')) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE registration_ip = ?');
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn() >= $limit;
    } catch (PDOException $e) {
        error_log('Registration IP limit failed: ' . $e->getMessage());
        return false;
    }
}

function checkRegistrationRateLimit(string $ip, string $email = ''): bool {
    global $pdo;

    $identity = hash('sha256', mb_strtolower(trim($email), 'UTF-8'));
    $ipLimit = securityConsumeRateLimit('auth:register:ip:' . hash('sha256', $ip), 5, 3600);
    $identityLimit = securityConsumeRateLimit('auth:register:identity:' . $identity, 5, 3600);
    if (empty($ipLimit['allowed']) || empty($identityLimit['allowed'])) {
        return true;
    }

    try {
        createRegistrationAttemptsTable();
        $cutoff = date('Y-m-d H:i:s', time() - 3600);
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM registration_attempts
            WHERE attempt_time > ?
              AND (ip_address = ? OR (email_hash IS NOT NULL AND email_hash = ?))
        ");
        $stmt->execute([$cutoff, $ip, $email !== '' ? hash('sha256', mb_strtolower(trim($email), 'UTF-8')) : null]);
        return (int)$stmt->fetchColumn() >= 5;
    } catch (PDOException $e) {
        return false;
    }
}

function recordRegistrationAttempt(string $ip, string $email, bool $success): void {
    global $pdo;

    try {
        createRegistrationAttemptsTable();
        $stmt = $pdo->prepare('INSERT INTO registration_attempts (ip_address, email_hash, success, attempt_time) VALUES (?, ?, ?, NOW())');
        $stmt->execute([
            $ip ?: null,
            $email !== '' ? hash('sha256', mb_strtolower(trim($email), 'UTF-8')) : null,
            $success ? 1 : 0
        ]);
    } catch (PDOException $e) {
        error_log('Registration attempt log failed: ' . $e->getMessage());
    }
}

function createRegistrationAttemptsTable(): void {
    global $pdo;
    if (!appRuntimeSchemaUpdatesEnabled()) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) DEFAULT NULL,
        email_hash CHAR(64) DEFAULT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempt_time DATETIME NOT NULL,
        INDEX idx_ip_time (ip_address, attempt_time),
        INDEX idx_email_time (email_hash, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Send verification email to user
 * 
 * @param string $email User email
 * @param string $token Verification token
 * @return bool True if "sent"
 */
function sendVerificationEmail($email, $token) {
    $verifyUrl = "https://zsem-egzamin.online/verify_email.php?token=$token";
    $subject = "Zweryfikuj swoje konto - ZSEM Tech";
    $message = "Witaj! Dziękujemy za rejestrację. Kliknij w poniższy link, aby aktywować swoje konto:\n\n$verifyUrl\n\nLink jest ważny przez 24 godziny.";
    
    // Attempt PHP mail()
    $headers = "From: no-reply@zsem-egzamin.online\r\n";
    @mail($email, $subject, $message, $headers);
    
    return true;
}

/**
 * Get user data by ID
 * 
 * @param int $id User ID
 * @return array|false User data or false if not found
 */
function getUserById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

/**
 * Verify plain text password against stored hash
 * 
 * @param string $password Plain text password
 * @param string $hash Stored password hash
 * @return bool True if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function upgradePasswordHashIfNeeded(PDO $pdo, array &$user, string $password): void {
    $currentHash = (string)($user['password_hash'] ?? '');
    if ($currentHash === '' || !password_needs_rehash($currentHash, PASSWORD_DEFAULT)) {
        return;
    }

    $replacementHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($replacementHash) || $replacementHash === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id AND password_hash = :current_hash');
        $stmt->execute([
            'password_hash' => $replacementHash,
            'user_id' => (int)$user['id'],
            'current_hash' => $currentHash,
        ]);
        if ($stmt->rowCount() === 1) {
            $user['password_hash'] = $replacementHash;
        }
    } catch (PDOException $e) {
        error_log('Password hash upgrade failed for user ID ' . (int)$user['id'] . '.');
    }
}


// =============================================================================
// Rate Limiting Functions
// =============================================================================

/**
 * Check if IP address has exceeded login attempt limit
 * 
 * After 4 failed attempts, require a 30 second pause.
 * 
 * @param string $ip IP address to check
 * @return bool True if rate limit exceeded, false otherwise
 */
function checkRateLimit($ip, string $username = '') {
    global $pdo;

    $identity = hash('sha256', mb_strtolower(trim($username), 'UTF-8'));
    $ipLimit = securityConsumeRateLimit('auth:login:ip:' . hash('sha256', $ip), 40, 600);
    $identityLimit = securityConsumeRateLimit('auth:login:identity:' . $identity, 20, 600);
    if (empty($ipLimit['allowed']) || empty($identityLimit['allowed'])) {
        return true;
    }

    try {
        createLoginAttemptsTable();
        return getFailedLoginAttemptCount($ip, $username, 30) >= 4
            || getFailedLoginAttemptCount($ip, $username, 600) >= 20;
    } catch (PDOException $e) {
        error_log('Login rate limit failed: ' . $e->getMessage());
        return false;
    }
}

function getFailedLoginAttemptCount(string $ip, string $username = '', int $window = 600): int {
    global $pdo;

    try {
        createLoginAttemptsTable();
        $username = mb_substr(mb_strtolower(trim($username), 'UTF-8'), 0, 50);
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM login_attempts
            WHERE success = 0
              AND attempt_time > ?
              AND (ip_address = ? OR (? <> '' AND username = ?))
        ");
        $stmt->execute([date('Y-m-d H:i:s', time() - $window), $ip, $username, $username]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function shouldRequireLoginCaptcha(string $ip, string $username = ''): bool {
    return getFailedLoginAttemptCount($ip, $username) >= 3;
}

function generateLoginCaptcha(): array {
    $a = random_int(3, 14);
    $b = random_int(2, 12);
    $op = random_int(0, 1) === 1 ? '+' : '-';
    if ($op === '-' && $b > $a) {
        [$a, $b] = [$b, $a];
    }
    $answer = $op === '+' ? $a + $b : $a - $b;
    $_SESSION['login_captcha'] = [
        'question' => "{$a} {$op} {$b}",
        'answer_hash' => password_hash((string)$answer, PASSWORD_DEFAULT),
        'issued_at' => time(),
    ];
    return $_SESSION['login_captcha'];
}

function validateLoginCaptcha(string $answer): bool {
    $captcha = $_SESSION['login_captcha'] ?? null;
    if (!$captcha || time() - (int)($captcha['issued_at'] ?? 0) > 600) {
        return false;
    }
    $answer = preg_replace('/\s+/', '', $answer);
    $ok = password_verify($answer, (string)($captcha['answer_hash'] ?? ''));
    if ($ok) {
        unset($_SESSION['login_captcha']);
    }
    return $ok;
}

/**
 * Record a login attempt in the database
 * 
 * @param string $ip IP address
 * @param bool $success Whether login was successful
 */
function recordLoginAttempt($ip, $success) {
    global $pdo;
    
    try {
        $username = mb_substr(mb_strtolower(trim((string)($_POST['username'] ?? '')), 'UTF-8'), 0, 50);
        $hasUsername = function_exists('dbColumnExists') && dbColumnExists($pdo, 'login_attempts', 'username');
        $stmt = $hasUsername
            ? $pdo->prepare('INSERT INTO login_attempts (ip_address, username, success, attempt_time) VALUES (:ip, :username, :success, NOW())')
            : $pdo->prepare('INSERT INTO login_attempts (ip_address, success, attempt_time) VALUES (:ip, :success, NOW())');
        $params = ['ip' => $ip, 'success' => $success ? 1 : 0];
        if ($hasUsername) $params['username'] = $username ?: null;
        $stmt->execute($params);
    } catch (PDOException $e) {
        // If table doesn't exist, try to create it
        if ($e->getCode() == '42S02' || $e->getCode() == 'HY000') {
            createLoginAttemptsTable();
            // Retry insert
            $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, success, attempt_time) VALUES (:ip, :success, NOW())');
            $stmt->execute([
                'ip' => $ip,
                'success' => $success ? 1 : 0
            ]);
        }
    }
}

/**
 * Clear login attempts for an IP address (call on successful login)
 * 
 * @param string $ip IP address
 */
function clearLoginAttempts($ip) {
    global $pdo;
    
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip');
    $stmt->execute(['ip' => $ip]);
}

/**
 * Create login_attempts table if it doesn't exist
 */
function createLoginAttemptsTable() {
    global $pdo;
    if (!appRuntimeSchemaUpdatesEnabled()) return;
    
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(50) DEFAULT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempt_time DATETIME NOT NULL,
        INDEX idx_ip (ip_address),
        INDEX idx_username_time (username, attempt_time),
        INDEX idx_time (attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    if (function_exists('dbAddColumnIfMissing')) {
        dbAddColumnIfMissing($pdo, 'login_attempts', 'username', 'VARCHAR(50) DEFAULT NULL AFTER ip_address');
    }
    if (function_exists('dbAddIndexIfMissing')) {
        dbAddIndexIfMissing($pdo, 'login_attempts', 'idx_username_time', '(username, attempt_time)');
    }
}

/**
 * Check if username or email already exists in database
 * 
 * @param string $username Username to check
 * @param string $email Email to check
 * @return bool True if user exists, false otherwise
 */
function userExists($username, $email) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
        $stmt->execute(['username' => $username, 'email' => $email]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('Error checking user existence: ' . $e->getMessage());
        return false;
    }
}

function isBanActiveSql(): string {
    return '(expires_at IS NULL OR expires_at > NOW())';
}

function ensureBanExpiryColumn(PDO $pdo, string $table, string $afterColumn = 'banned_by'): bool {
    try {
        if (function_exists('dbAddColumnIfMissing')) {
            dbAddColumnIfMissing($pdo, $table, 'expires_at', 'DATETIME DEFAULT NULL AFTER ' . $afterColumn);
        }
        return !function_exists('dbColumnExists') || dbColumnExists($pdo, $table, 'expires_at');
    } catch (PDOException $e) {
        return false;
    }
}

function clearExpiredBanForUser(PDO $pdo, array $user): bool {
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0 || !function_exists('dbColumnExists') || !dbColumnExists($pdo, 'users', 'ban_expires_at')) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET is_banned = 0, ban_expires_at = NULL WHERE id = ? AND is_banned = 1 AND ban_expires_at IS NOT NULL AND ban_expires_at <= NOW()');
        $stmt->execute([$userId]);
        if ($stmt->rowCount() < 1) {
            return false;
        }

        if (!empty($user['email'])) {
            $pdo->prepare('DELETE FROM banned_emails WHERE email = ? AND expires_at IS NOT NULL AND expires_at <= NOW()')->execute([$user['email']]);
        }
        if (!empty($user['last_login_ip'])) {
            $pdo->prepare('DELETE FROM banned_ips WHERE ip_address = ? AND expires_at IS NOT NULL AND expires_at <= NOW()')->execute([$user['last_login_ip']]);
        }
        return true;
    } catch (PDOException $e) {
        error_log('Expired ban cleanup failed: ' . $e->getMessage());
        return false;
    }
}

function userBanIsActive(PDO $pdo, int $userId): bool {
    try {
        if (function_exists('dbColumnExists') && dbColumnExists($pdo, 'users', 'ban_expires_at')) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND is_banned = 1 AND (ban_expires_at IS NULL OR ban_expires_at > NOW()) LIMIT 1');
            $stmt->execute([$userId]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND is_banned = 1 LIMIT 1');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return true;
    }
}

function isEmailBanned($email) {
    global $pdo;

    try {
        $hasExpiry = ensureBanExpiryColumn($pdo, 'banned_emails');
        $sql = 'SELECT 1 FROM banned_emails WHERE email = ?';
        if ($hasExpiry) {
            $sql .= ' AND ' . isBanActiveSql();
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function isIpBanned($ip) {
    global $pdo;

    try {
        $hasExpiry = ensureBanExpiryColumn($pdo, 'banned_ips');
        $sql = 'SELECT 1 FROM banned_ips WHERE ip_address = ?';
        if ($hasExpiry) {
            $sql .= ' AND ' . isBanActiveSql();
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Update last login timestamp and IP address for a user
 * 
 * @param int $userId User ID
 * @param string|null $ip IP address
 * @return bool True on success
 */
function updateLastLogin($userId, $ip = null) {
    global $pdo;
    
    try {
        if ($ip !== null) {
            $stmt = $pdo->prepare('UPDATE users SET last_login = NOW(), last_login_ip = :ip WHERE id = :id');
            return $stmt->execute(['id' => $userId, 'ip' => $ip]);
        }

        $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        return $stmt->execute(['id' => $userId]);
    } catch (PDOException $e) {
        error_log('Error updating last login: ' . $e->getMessage());
        return false;
    }
}

function mfaRoleRequiresSetup(string $role): bool {
    return $role === 'admin';
}

function mfaRoleCanUse(string $role): bool {
    // Legacy staff set: ['admin', 'dyrektor', 'teacher']; normal users can opt in too.
    return in_array($role, ['admin', 'dyrektor', 'teacher', 'user'], true);
}

function mfaUserHasEnabled(PDO $pdo, int $userId): bool {
    if ($userId <= 0) return false;
    try {
        if (function_exists('ensurePlatformEnhancements')) ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('SELECT enabled_at FROM user_mfa WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('MFA enabled lookup failed: ' . $e->getMessage());
        return false;
    }
}

function mfaAccessRequired(): bool {
    if (empty($_SESSION['user_id'])) return false;
    $role = $_SESSION['role'] ?? 'user';
    $requiresByRole = mfaRoleRequiresSetup($role);
    $requiresByUser = false;
    if (!$requiresByRole && mfaRoleCanUse($role)) {
        if (!array_key_exists('mfa_enabled', $_SESSION)) {
            global $pdo;
            $_SESSION['mfa_enabled'] = ($pdo instanceof PDO) ? mfaUserHasEnabled($pdo, (int)$_SESSION['user_id']) : false;
        }
        $requiresByUser = !empty($_SESSION['mfa_enabled']);
    }
    if (!$requiresByRole && !$requiresByUser) return false;
    if (!empty($_SESSION['mfa_verified'])) return false;
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    return !in_array($current, ['mfa.php', 'logout.php', 'login.php'], true);
}

function base32Encode(string $bytes): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $out = '';
    for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function base32Decode(string $base32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $base32));
    $bits = '';
    foreach (str_split($base32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $out .= chr(bindec($byte));
    }
    return $out;
}

function getOrCreateMfaSecret(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare('SELECT secret FROM user_mfa WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $secret = $stmt->fetchColumn();
    if ($secret) return (string)$secret;
    $secret = base32Encode(secureRandomBytes(20));
    $stmt = $pdo->prepare('INSERT INTO user_mfa (user_id, secret) VALUES (?, ?)');
    $stmt->execute([$userId, $secret]);
    return $secret;
}

function getMfaRow(PDO $pdo, int $userId): ?array {
    try {
        if (function_exists('ensurePlatformEnhancements')) ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('SELECT * FROM user_mfa WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('MFA lookup failed: ' . $e->getMessage());
        return null;
    }
}

function totpCode(string $secret, ?int $timeSlice = null): string {
    $timeSlice = $timeSlice ?? (int)floor(time() / 30);
    $key = base32Decode($secret);
    $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $binaryTime, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code): bool {
    $code = preg_replace('/\D+/', '', $code);
    if (strlen($code) !== 6) return false;
    $slice = (int)floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totpCode($secret, $slice + $i), $code)) return true;
    }
    return false;
}

function generateRecoveryCodes(int $count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(secureRandomBytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return $codes;
}

function hashRecoveryCodes(array $codes): string {
    return json_encode(array_map(static fn($code) => password_hash(strtoupper(trim($code)), PASSWORD_DEFAULT), $codes));
}

function verifyAndConsumeRecoveryCode(PDO $pdo, int $userId, string $code): bool {
    $row = getMfaRow($pdo, $userId);
    $hashes = json_decode($row['recovery_codes_hash'] ?? '[]', true);
    if (!is_array($hashes)) return false;
    $code = strtoupper(trim($code));
    foreach ($hashes as $idx => $hash) {
        if (password_verify($code, $hash)) {
            unset($hashes[$idx]);
            $stmt = $pdo->prepare('UPDATE user_mfa SET recovery_codes_hash = ? WHERE user_id = ?');
            $stmt->execute([json_encode(array_values($hashes)), $userId]);
            return true;
        }
    }
    return false;
}

function enableMfaForUser(PDO $pdo, int $userId, string $secret): array {
    $codes = generateRecoveryCodes();
    $stmt = $pdo->prepare('
        INSERT INTO user_mfa (user_id, secret, enabled_at, recovery_codes_hash)
        VALUES (?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE secret = VALUES(secret), enabled_at = NOW(), recovery_codes_hash = VALUES(recovery_codes_hash)
    ');
    $stmt->execute([$userId, $secret, hashRecoveryCodes($codes)]);
    $_SESSION['mfa_verified'] = true;
    $_SESSION['mfa_enabled'] = true;
    return $codes;
}

function resetMfaForUser(PDO $pdo, int $userId): bool {
    if ($userId <= 0) return false;
    try {
        if (function_exists('ensurePlatformEnhancements')) ensurePlatformEnhancements($pdo);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM user_mfa WHERE user_id = ?');
        $stmt->execute([$userId]);
        $pdo->prepare('UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?')
            ->execute([$userId]);
        if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['mfa_enabled'] = false;
            $_SESSION['mfa_verified'] = !mfaRoleRequiresSetup($_SESSION['role'] ?? 'user');
        }
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('MFA reset failed: ' . $e->getMessage());
        return false;
    }
}

function createPasswordResetToken(PDO $pdo, string $email): ?string {
    $email = trim(mb_strtolower($email, 'UTF-8'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    if (function_exists('ensurePlatformEnhancements')) ensurePlatformEnhancements($pdo);
    if (function_exists('consumeRateLimit') && !consumeRateLimit($pdo, 'password_reset', clientIpAddress() . '|' . $email, 5, 3600)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return null;

    $token = bin2hex(secureRandomBytes(32));
    $hash = hash('sha256', $token);
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int)$user['id']]);
    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, ip_address, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
    $stmt->execute([(int)$user['id'], $hash, clientIpAddress()]);
    return $token;
}

function sendPasswordResetEmail(string $email, string $token): bool {
    $url = securityPasswordResetUrl($token);
    $subject = 'Reset hasła - ZSEM Tech';
    $message = "Aby zresetować hasło, otwórz link ważny 30 minut:\n\n{$url}\n\nJeśli to nie Ty, zignoruj wiadomość.";
    $sent = @mail($email, $subject, $message, "From: no-reply@zsemtech.local\r\n");
    if (!$sent) {
        error_log('Password reset email delivery failed.');
    }
    return $sent;
}

function getPasswordResetUser(PDO $pdo, string $token): ?array {
    $hash = hash('sha256', trim($token));
    $stmt = $pdo->prepare('
        SELECT pr.id AS reset_id, pr.user_id, u.email, u.username
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
        LIMIT 1
    ');
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function resetPasswordWithToken(PDO $pdo, string $token, string $password): bool {
    $row = getPasswordResetUser($pdo, $token);
    if (!$row || (function_exists('validatePasswordPolicy') && validatePasswordPolicy($password) !== [])) return false;
    $pdo->beginTransaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ?, session_version = COALESCE(session_version, 1) + 1 WHERE id = ?')
            ->execute([$hash, (int)$row['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([(int)$row['reset_id']]);
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Password reset failed: ' . $e->getMessage());
        return false;
    }
}

// ─── Restore persisted active test (one test per account) ─────────────────────
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/functions.php';
    restoreActiveTestForUser($pdo, (int)$_SESSION['user_id']);
}
// ──────────────────────────────────────────────────────────────────────────────
