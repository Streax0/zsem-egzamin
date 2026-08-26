<?php

require_once __DIR__ . '/../includes/autoloader.php';

require_once __DIR__ . '/RequestContext.php';
require_once __DIR__ . '/PublicUrl.php';
require_once __DIR__ . '/Input.php';
require_once __DIR__ . '/Headers.php';
require_once __DIR__ . '/JsonResponse.php';
require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/CsrfGuard.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Endpoint.php';
require_once __DIR__ . '/Redirect.php';

function securityWaf(?string $logPath = null): \App\Security\Waf {
    static $instance = null;
    if ($instance === null || $logPath !== null) {
        $instance = new \App\Security\Waf($logPath);
    }
    return $instance;
}

function securityFirewall(?\App\Security\Waf $waf = null, ?string $bannedIpsPath = null): \App\Security\Firewall {
    static $instance = null;
    if ($instance === null || $bannedIpsPath !== null) {
        $instance = new \App\Security\Firewall($waf ?? securityWaf(), $bannedIpsPath);
    }
    return $instance;
}

// Production Error & Exception Shield
(function() {
    $env = function_exists('configValue') ? configValue('APP_ENV', 'production') : (getenv('APP_ENV') ?: 'production');
    if ($env === 'production') {
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

        set_exception_handler(function (Throwable $e) {
            $errorId = substr(hash('sha256', (string)microtime(true) . $e->getMessage()), 0, 8);
            $logDir = __DIR__ . '/../data/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logEntry = sprintf(
                "[%s] [REF:%s] %s in %s:%d\nStack trace:\n%s\n",
                date('Y-m-d H:i:s'),
                $errorId,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            @file_put_contents($logDir . '/error.log', $logEntry, FILE_APPEND | LOCK_EX);

            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                http_response_code(500);
                if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'Wystąpił błąd serwera', 'ref' => $errorId]);
                } else {
                    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><title>Błąd serwera — ZSEM Tech</title><link rel="stylesheet" href="/assets/css/style.css"></head><body style="background:#0f172a;color:#fff;font-family:sans-serif;padding:3rem;text-align:center;"><h2>⚠️ Wystąpił nieoczekiwany błąd</h2><p class="text-muted">Kod referencyjny: <code>' . htmlspecialchars($errorId) . '</code></p><a href="/" style="color:#6366f1;">Wróć na stronę główną</a></body></html>';
                }
            }
        });
    }
})();


