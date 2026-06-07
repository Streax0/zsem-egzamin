<?php

function securityValidateCsrfToken(string $token, string $action = ''): bool {
    if (function_exists('validateCsrfToken')) {
        return validateCsrfToken($token, $action);
    }
    return false;
}

function securityValidateRequestCsrf(string $action = ''): bool {
    $json = [];
    if (empty($_POST['csrf_token']) && empty($_SERVER['HTTP_X_CSRF_TOKEN']) && function_exists('securityJsonBody')) {
        $json = securityJsonBody();
    }
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($json['csrf_token'] ?? ''));
    $ok = securityValidateCsrfToken($token, $action);
    if (!$ok) {
        securityAudit('csrf_failed', ['action' => $action, 'method' => $_SERVER['REQUEST_METHOD'] ?? ''], 'warning');
    }
    return $ok;
}
