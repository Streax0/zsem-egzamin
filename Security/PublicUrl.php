<?php

function securityNormalizePublicBaseUrl(string $url): ?string {
    $url = trim(str_replace(["\r", "\n", "\0"], '', $url));
    if ($url === '' || str_contains($url, '\\')) {
        return null;
    }

    $parts = parse_url($url);
    if (
        !is_array($parts)
        || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])
    ) {
        return null;
    }

    $port = isset($parts['port']) ? (int)$parts['port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535)) {
        return null;
    }
    $path = rtrim((string)($parts['path'] ?? ''), '/');
    if (str_contains($path, '..')) {
        return null;
    }

    $origin = strtolower((string)$parts['scheme']) . '://' . (string)$parts['host'];
    if ($port !== null) {
        $origin .= ':' . $port;
    }
    return $origin . $path;
}

function securityPublicBaseUrl(string $fallback = 'https://zsem-egzamin.online'): string {
    $configured = getenv('APP_BASE_URL');
    if ($configured === false || trim((string)$configured) === '') {
        $configured = $_ENV['APP_BASE_URL'] ?? '';
    }
    if (trim((string)$configured) === '') {
        $configured = getenv('CLIENT_URL');
        if ($configured === false || trim((string)$configured) === '') {
            $configured = $_ENV['CLIENT_URL'] ?? '';
        }
    }

    $normalized = securityNormalizePublicBaseUrl((string)$configured);
    return $normalized ?? securityNormalizePublicBaseUrl($fallback) ?? 'https://zsem-egzamin.online';
}

function securityPasswordResetUrl(string $token): string {
    return securityPublicBaseUrl() . '/forgot_password.php?token=' . rawurlencode($token);
}
