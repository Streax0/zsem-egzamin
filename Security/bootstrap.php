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

