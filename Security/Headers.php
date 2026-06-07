<?php

function securityApplyResponseHeaders(?string $nonce = null): void {
    if (headers_sent()) {
        return;
    }
    header('X-Request-ID: ' . securityRequestId());
    header('X-Security-Layer: Security');
    header('X-Download-Options: noopen');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $permissionsPolicy = function_exists('appSecurityPermissionsPolicy')
        ? appSecurityPermissionsPolicy()
        : 'camera=(self), microphone=(), geolocation=(), payment=()';
    header('Permissions-Policy: ' . $permissionsPolicy);
}

function securityApplyJsonHeaders(): void {
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    securityApplyResponseHeaders();
}
