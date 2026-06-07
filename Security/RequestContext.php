<?php

function securityRequestId(): string {
    if (!empty($GLOBALS['security_request_id'])) {
        return (string)$GLOBALS['security_request_id'];
    }

    $incoming = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_CLIENT_REQUEST_ID'] ?? '');
    if (preg_match('/^[A-Za-z0-9._:-]{8,80}$/', $incoming)) {
        $GLOBALS['security_request_id'] = $incoming;
        return $incoming;
    }

    try {
        $id = bin2hex(random_bytes(8)) . '-' . dechex(time());
    } catch (Throwable $e) {
        $id = str_replace('.', '', uniqid('req', true));
    }
    $GLOBALS['security_request_id'] = $id;
    return $id;
}

function securityClientIp(): string {
    $candidates = [
        (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string)($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim(explode(',', $candidate)[0]);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function securityActorKey(): string {
    if (!empty($_SESSION['user_id'])) {
        return 'user:' . (int)$_SESSION['user_id'];
    }
    if (!empty($_SESSION['guest_id'])) {
        return 'guest:' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_SESSION['guest_id']);
    }
    return 'ip:' . hash('sha256', securityClientIp());
}

function securityRequestContext(): array {
    return [
        'request_id' => securityRequestId(),
        'actor' => securityActorKey(),
        'ip' => securityClientIp(),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        'path' => (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''),
        'user_agent_hash' => hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
        'time' => time(),
    ];
}
