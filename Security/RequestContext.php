<?php

function securityTrustProxyHeaders(): bool {
    $value = getenv('APP_TRUST_PROXY_HEADERS');
    if ($value === false || $value === '') {
        $value = $_ENV['APP_TRUST_PROXY_HEADERS'] ?? '';
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function securityIpMatchesTrustedRange(string $ip, string $range): bool {
    $ipBinary = @inet_pton(trim($ip));
    $range = trim($range);
    if ($ipBinary === false || $range === '') {
        return false;
    }

    if (!str_contains($range, '/')) {
        $rangeBinary = @inet_pton($range);
        return $rangeBinary !== false && hash_equals($rangeBinary, $ipBinary);
    }

    [$network, $prefixRaw] = array_map('trim', explode('/', $range, 2));
    if ($prefixRaw === '' || !ctype_digit($prefixRaw)) {
        return false;
    }
    $networkBinary = @inet_pton($network);
    if ($networkBinary === false || strlen($networkBinary) !== strlen($ipBinary)) {
        return false;
    }

    $prefix = (int)$prefixRaw;
    $maxBits = strlen($ipBinary) * 8;
    if ($prefix < 0 || $prefix > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($prefix, 8);
    if ($fullBytes > 0 && !hash_equals(substr($networkBinary, 0, $fullBytes), substr($ipBinary, 0, $fullBytes))) {
        return false;
    }
    $remainingBits = $prefix % 8;
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($networkBinary[$fullBytes]) & $mask) === (ord($ipBinary[$fullBytes]) & $mask);
}

function securityRequestComesFromTrustedProxy(): bool {
    if (!securityTrustProxyHeaders()) {
        return false;
    }

    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        return false;
    }

    $configured = getenv('APP_TRUSTED_PROXY_IPS');
    if ($configured === false || trim((string)$configured) === '') {
        $configured = $_ENV['APP_TRUSTED_PROXY_IPS'] ?? '';
    }
    $ranges = preg_split('/[\s,;]+/', trim((string)$configured), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($ranges as $range) {
        if (securityIpMatchesTrustedRange($remote, $range)) {
            return true;
        }
    }
    return false;
}

function securityRequestIsSecure(): bool {
    if (!empty($_SERVER['HTTPS']) && in_array(strtolower((string)$_SERVER['HTTPS']), ['on', '1'], true)) {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (!securityRequestComesFromTrustedProxy()) {
        return false;
    }

    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($forwardedProto === 'https') {
        return true;
    }
    if (strtolower(trim((string)($_SERVER['HTTP_X_SCHEME'] ?? ''))) === 'https') {
        return true;
    }
    return strtolower(trim((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''))) === 'on';
}

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
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $fallback = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    if (!securityRequestComesFromTrustedProxy()) {
        return $fallback;
    }

    $candidates = [
        (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string)($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim(explode(',', $candidate)[0]);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return $fallback;
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
