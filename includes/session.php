<?php
/**
 * Session Management System
 *
 * Provides secure session management with flash messages, CSRF protection,
 * and session security features.
 *
 * @package Session
 * @version 1.0.0
 */

if (!defined('APP_DEBUG_LOG')) {
    define('APP_DEBUG_LOG', dirname(__DIR__) . '/../logs/zsemtech-debug.log');
}

require_once dirname(__DIR__) . '/Security/bootstrap.php';

if (!function_exists('app_log')) {
    function app_log($message) {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . trim($message) . PHP_EOL;
        @file_put_contents(APP_DEBUG_LOG, $line, FILE_APPEND | LOCK_EX);
    }
}

function appCspNonce(): string {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }
    return $nonce;
}

function appVersionLocalAssetHref(string $href, string $assetFolder): string {
    $decodedHref = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = parse_url($decodedHref);
    if (!is_array($parts)) {
        return $href;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
        return $href;
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host !== '') {
        $requestAuthority = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $requestParts = $requestAuthority !== '' ? parse_url('http://' . $requestAuthority) : [];
        $requestHost = is_array($requestParts) ? strtolower((string)($requestParts['host'] ?? '')) : '';
        if ($requestHost === '' || $host !== $requestHost) {
            return $href;
        }
    }

    $path = (string)($parts['path'] ?? '');
    $assetPos = strpos($path, $assetFolder);
    if ($assetPos === false) {
        return $href;
    }

    $assetPath = substr($path, $assetPos);
    $absolute = dirname(__DIR__) . '/' . $assetPath;
    if (!is_file($absolute)) {
        return $href;
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str(str_replace('&amp;', '&', (string)$parts['query']), $query);
    }
    $query['v'] = (string)filemtime($absolute);
    $queryString = str_replace('&', '&amp;', http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    $baseHref = preg_replace('/[?#].*$/', '', $href) ?: $href;
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $baseHref . '?' . $queryString . $fragment;
}

function appVersionLocalStylesheetHref(string $href): string {
    return appVersionLocalAssetHref($href, 'assets/css/');
}

function appVersionLocalScriptHref(string $src): string {
    return appVersionLocalAssetHref($src, 'assets/js/');
}

function appVersionLocalStylesheetHrefs(string $buffer): string {
    return preg_replace_callback('/\bhref=(["\'])([^"\']*assets\/css\/[^"\']+\.css(?:\?[^"\']*)?(?:#[^"\']*)?)\1/i', static function ($matches): string {
        return 'href=' . $matches[1] . appVersionLocalStylesheetHref($matches[2]) . $matches[1];
    }, $buffer) ?? $buffer;
}

function appVersionLocalScriptSrcs(string $buffer): string {
    return preg_replace_callback('/\bsrc=(["\'])([^"\']*assets\/js\/[^"\']+\.js(?:\?[^"\']*)?(?:#[^"\']*)?)\1/i', static function ($matches): string {
        return 'src=' . $matches[1] . appVersionLocalScriptHref($matches[2]) . $matches[1];
    }, $buffer) ?? $buffer;
}

function appStartCspNonceBuffer(string $nonce): void {
    if (!empty($GLOBALS['app_csp_nonce_buffer_started'])) {
        return;
    }
    $GLOBALS['app_csp_nonce_buffer_started'] = true;
    ob_start(static function ($buffer) use ($nonce) {
        if (stripos($buffer, '<script') === false && stripos($buffer, '<style') === false) {
            return appVersionLocalScriptSrcs(appVersionLocalStylesheetHrefs($buffer));
        }
        $buffer = appVersionLocalStylesheetHrefs($buffer);
        $buffer = appVersionLocalScriptSrcs($buffer);
        $attr = ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"';
        $buffer = preg_replace('/<script(?![^>]*\bnonce\s*=)/i', '<script' . $attr, $buffer);
        return preg_replace('/<style(?![^>]*\bnonce\s*=)/i', '<style' . $attr, $buffer);
    });
}

function appSecurityPermissionsPolicy(): string {
    return "accelerometer=(), ambient-light-sensor=(), autoplay=(self), browsing-topics=(), camera=(self), display-capture=(), encrypted-media=(), fullscreen=(self), gamepad=(), geolocation=(), gyroscope=(), interest-cohort=(), magnetometer=(), microphone=(), midi=(), payment=(), publickey-credentials-get=(self), screen-wake-lock=(), usb=(), web-share=(self), xr-spatial-tracking=()";
}

function appContentSecurityPolicy(string $nonce): string {
    return "default-src 'none'; "
        . "script-src 'self' 'nonce-{$nonce}' blob: https://cdn.jsdelivr.net; "
        . "script-src-elem 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; "
        . "script-src-attr 'unsafe-inline'; "
        . "worker-src 'self' blob:; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "style-src-elem 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "style-src-attr 'unsafe-inline'; "
        . "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: https://praktycznyegzamin.pl https://www.praktycznyegzamin.pl https://api.qrserver.com; "
        . "connect-src 'self' https://cdn.jsdelivr.net; "
        . "media-src 'self'; manifest-src 'self'; "
        . "frame-src 'self' https://www.openstreetmap.org; "
        . "child-src 'self' https://www.openstreetmap.org; "
        . "object-src 'none'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'; upgrade-insecure-requests";
}

function appContentSecurityPolicyReportOnly(string $nonce): string {
    return "require-trusted-types-for 'script'; trusted-types default zsemtech-app; script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; object-src 'none'; base-uri 'none'";
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        app_log('Shutdown error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
    }
});

// NOTE: Auto-start moved to end of file after function definitions

/**
 * Starts a secure session with proper cookie parameters
 * 
 * Configures session cookie with security settings:
 * - HttpOnly flag to prevent JavaScript access
 * - Secure flag for HTTPS-only transmission
 * - SameSite attribute to prevent CSRF attacks
 * - Session lifetime management
 * 
 * @return void
 */
function startSecureSession() {
    // Security Headers
    if (!headers_sent()) {
        $cspNonce = appCspNonce();
        appStartCspNonceBuffer($cspNonce);
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 0");
        header("X-Frame-Options: SAMEORIGIN");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: " . appSecurityPermissionsPolicy());
        header("Cross-Origin-Opener-Policy: same-origin");
        header("Cross-Origin-Embedder-Policy: unsafe-none");
        header("Cross-Origin-Resource-Policy: same-origin");
        header("X-Permitted-Cross-Domain-Policies: none");
        header("X-DNS-Prefetch-Control: off");
        header("Origin-Agent-Cluster: ?1");
        header_remove("X-Powered-By");
        header("Content-Security-Policy: " . appContentSecurityPolicy($cspNonce));
        header("Content-Security-Policy-Report-Only: " . appContentSecurityPolicyReportOnly($cspNonce));
        securityApplyResponseHeaders($cspNonce);
    }

    // Session lifetime: 0 = until browser closes, 10800 = 3 hours
    $lifetime = 10800;

    // If a session is already active, avoid changing cookie parameters or ini settings
    // because PHP will emit warnings when those are modified after session start.
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (empty($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = time();
        }
        if (!isset($_SESSION['session_start'])) {
            $_SESSION['session_start'] = time();
        }
        return;
    }

    $isSecure = securityRequestIsSecure();

    if (!headers_sent() && $isSecure) {
        header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
    }

    // Set session cookie parameters BEFORE starting the session
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        ini_set('session.cookie_samesite', 'Lax');
    } else {
        // PHP < 7.3 does not support array cookie params or cookie_samesite ini
        session_set_cookie_params($lifetime, '/; SameSite=Lax', '', $isSecure, true);
    }

    // Configure session settings BEFORE session_start
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', $isSecure ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', $lifetime);

    // Start session if not already active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID to prevent fixation, but avoid doing it on every AJAX request
    // if a session is already established and has not changed roles.
    if (empty($_SESSION['initiated'])) {
        session_regenerate_id(false); // Changed true to false to avoid clearing data prematurely if session is shaky
        $_SESSION['initiated'] = time();
    }

    // Set session start time if not set
    if (!isset($_SESSION['session_start'])) {
        $_SESSION['session_start'] = time();
    }
}

/**
 * Destroys the current session completely
 * 
 * Clears all session data, destroys the session cookie,
 * and optionally redirects to login page.
 * 
 * @param bool $redirectToLogin Whether to redirect to login page after destruction
 * @param string $loginPage Path to login page (default: '/login.php')
 * @return void
 */
function destroySession($redirectToLogin = false, $loginPage = '/login.php') {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear session status
    session_write_close();
    
    // Redirect to login page if requested
    if ($redirectToLogin) {
        header('Location: ' . $loginPage);
        exit();
    }
}

/**
 * Regenerates session ID to prevent session fixation attacks
 * 
 * Should be called after successful login and periodically
 * during the session to enhance security.
 * 
 * @param bool $deleteOldSession Whether to delete old session data
 * @return bool True on success, false on failure
 */
function regenerateSessionId($deleteOldSession = true) {
    // Only regenerate if session is active
    if (session_status() === PHP_SESSION_ACTIVE) {
        return session_regenerate_id($deleteOldSession);
    }
    return false;
}

/**
 * Sets a flash message in the session
 * 
 * Flash messages are temporary messages that persist only for
 * the next request, then are automatically cleared. Useful for
 * displaying success/error/info messages after form submissions.
 * 
 * @param string $type Message type: 'success', 'error', 'info', 'warning'
 * @param string $message The message content
 * @return void
 */
function setSessionMessage($type, $message) {
    // Validate message type
    $validTypes = ['success', 'error', 'info', 'warning'];
    if (!in_array($type, $validTypes)) {
        $type = 'info';
    }
    
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    
    // Store flash message
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Retrieves and clears the flash message from session
 * 
 * Returns the stored flash message if available, then removes
 * it from the session so it won't be displayed again.
 * 
 * @return array|null Array with 'type' and 'message' keys, or null if no message
 */
function getSessionMessage() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    
    // Check if flash message exists
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        
        // Remove the message so it's not shown again
        unset($_SESSION['flash_message']);
        
        return $message;
    }
    
    return null;
}

/**
 * Compatibility wrapper: get flash message (alias)
 * @return array|null
 */
function getFlashMessage() {
    return getSessionMessage();
}

/**
 * Compatibility wrapper: set flash message (alias)
 * @param string $type
 * @param string $message
 */
function setFlashMessage($type, $message) {
    setSessionMessage($type, $message);
}

/**
 * Updates the last login timestamp for a user in the database
 * 
 * Records when a user last logged in for security audit purposes.
 * Requires database connection to be established before calling.
 * 
 * @param int $userId The user ID to update
 * @param PDO|mysqli|null $dbConnection Optional database connection object
 * @return bool True on success, false on failure
 */
function setUserLastLogin($userId, $dbConnection = null) {
    // Validate user ID
    if (!is_numeric($userId) || $userId <= 0) {
        return false;
    }
    
    // Get current timestamp
    $lastLogin = date('Y-m-d H:i:s');
    
    try {
        // Try to use provided database connection
        if ($dbConnection !== null) {
            if ($dbConnection instanceof PDO) {
                // PDO connection
                $stmt = $dbConnection->prepare(
                    "UPDATE users SET last_login = :last_login WHERE id = :user_id"
                );
                $stmt->bindParam(':last_login', $lastLogin);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                return $stmt->execute();
            } elseif ($dbConnection instanceof mysqli) {
                // MySQLi connection
                $stmt = $dbConnection->prepare(
                    "UPDATE users SET last_login = ? WHERE id = ?"
                );
                $stmt->bind_param('si', $lastLogin, $userId);
                return $stmt->execute();
            }
        }
        
        // Fallback: Try global database connection if available
        if (isset($GLOBALS['db']) && ($GLOBALS['db'] instanceof PDO || $GLOBALS['db'] instanceof mysqli)) {
            return setUserLastLogin($userId, $GLOBALS['db']);
        }
        
        // If no database connection available, store in session as fallback
        $_SESSION['user_last_login'] = $lastLogin;
        
        // Log the event for audit purposes
        error_log("User ID {$userId} login recorded at {$lastLogin}");
        
        return true;
        
    } catch (Exception $e) {
        // Log database error
        error_log("Failed to update last login for user {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Generates a CSRF token for form protection
 * 
 * Creates a unique token per session to prevent Cross-Site Request Forgery
 * attacks. Should be included in all forms as a hidden field.
 * 
 * @param string $action Optional action identifier for multiple tokens
 * @return string The generated CSRF token
 */
function secureRandomBytes($length = 32) {
    if ($length <= 0) {
        return '';
    }

    return random_bytes($length);
}

function getCsrfTokenMaxAge($action = '') {
    return $action === 'session_keepalive' ? 10800 : 3600;
}

function generateCsrfToken($action = '') {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    
    // Generate token key
    $tokenKey = $action ? 'csrf_token_' . $action : 'csrf_token';
    $maxAge = getCsrfTokenMaxAge($action);
    
    // Generate new token if it doesn't exist or is expired
    if (empty($_SESSION[$tokenKey]) || 
        (isset($_SESSION[$tokenKey . '_time']) && 
         time() - $_SESSION[$tokenKey . '_time'] > $maxAge)) {
        
        // Generate cryptographically secure random token
        $_SESSION[$tokenKey] = bin2hex(secureRandomBytes(32));
        $_SESSION[$tokenKey . '_time'] = time();
    }
    
    return $_SESSION[$tokenKey];
}

/**
 * Validates a CSRF token from form submission
 * 
 * Compares submitted token against stored session token to
 * ensure the request originated from the legitimate user.
 * 
 * @param string $token The token to validate
 * @param string $action Optional action identifier
 * @return bool True if token is valid, false otherwise
 */
function validateCsrfToken($token, $action = '') {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    
    // Get token key
    $tokenKey = $action ? 'csrf_token_' . $action : 'csrf_token';
    $maxAge = getCsrfTokenMaxAge($action);
    
    // Check if token exists in session
    if (empty($_SESSION[$tokenKey])) {
        return false;
    }
    
    // Check if token is expired
    if (isset($_SESSION[$tokenKey . '_time']) && 
        time() - $_SESSION[$tokenKey . '_time'] > $maxAge) {
        unset($_SESSION[$tokenKey]);
        unset($_SESSION[$tokenKey . '_time']);
        return false;
    }
    
    // Use hash_equals for timing attack resistant comparison
    if (hash_equals($_SESSION[$tokenKey], $token)) {
        return true;
    }
    
    // Log failure for debugging
    if (function_exists('app_log')) {
        app_log("CSRF Validation Failed: Key={$tokenKey}, Expected=" . substr($_SESSION[$tokenKey], 0, 8) . "..., Got=" . substr($token, 0, 8) . "...");
    }
    
    return false;
}

/**
 * Generates CSRF token hidden input field for HTML forms
 * 
 * Convenience function to output the CSRF token as a hidden
 * form field for easy inclusion in forms.
 * 
 * @param string $action Optional action identifier
 * @return string HTML input field with CSRF token
 */
function csrfTokenField($action = '') {
    $token = generateCsrfToken($action);
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validates a CSRF token submitted either as a POST field or X-CSRF-Token header.
 *
 * @param string $action Optional action identifier
 * @return bool
 */
function validateRequestCsrfToken($action = '') {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return validateCsrfToken($token, $action);
}

/**
 * Stops a JSON endpoint when the request is missing a valid CSRF token.
 *
 * @param string $action Optional action identifier
 * @return void
 */
function requireJsonCsrfToken($action = '') {
    if (!securityValidateRequestCsrf($action)) {
        http_response_code(403);
        echo securityJsonEncode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

/**
 * Checks if user is logged in based on session data
 * 
 * @return bool True if user appears to be logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

// Note: `requireLogin()` is implemented in includes/auth.php to handle
// redirects with the correct application path and return URL.

/**
 * Returns session age in seconds
 * 
 * @return int Age of current session in seconds
 */
function getSessionAge() {
    if (isset($_SESSION['session_start'])) {
        return time() - $_SESSION['session_start'];
    }
    return 0;
}

/**
 * Checks if session has expired
 * 
 * @param int $maxLifetime Maximum session lifetime in seconds (default: 3 hours)
 * @return bool True if session has expired
 */
function isSessionExpired($maxLifetime = 10800) {
    return getSessionAge() > $maxLifetime;
}

// Compatibility aliases for different casing conventions
if (!function_exists('validateCSRF')) {
    function validateCSRF($token, $action = '') {
        return validateCsrfToken($token, $action);
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken($action = '') {
        return generateCsrfToken($action);
    }
}

// Auto-start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession();
}

// Initialize CSRF token for general form usage
generateCsrfToken();

/**
 * Example usage:
 * 
 * // Start secure session (auto-called if session not started)
 * startSecureSession();
 * 
 * // Set flash message
 * setSessionMessage('success', 'Login successful!');
 * 
 * // Get and display flash message
 * $message = getSessionMessage();
 * if ($message) {
 *     echo '<div class="alert alert-' . $message['type'] . '">' . 
 *          htmlspecialchars($message['message']) . '</div>';
 * }
 * 
 * // Include CSRF token in form
 * echo csrfTokenField();
 * 
 * // Validate token on form submission
 * if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *     if (!validateCsrfToken($_POST['csrf_token'])) {
 *         die('Invalid CSRF token');
 *     }
 *     // Process form...
 * }
 * 
 * // Regenerate session ID after login
 * regenerateSessionId();
 * 
 * // Update user last login
 * setUserLastLogin($userId, $db);
 * 
 * // Destroy session on logout
 * destroySession(true);
 */
