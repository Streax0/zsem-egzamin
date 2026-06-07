<?php

function securityFallbackRedirectTarget(string $fallback = 'index.php'): string {
    $fallback = trim(str_replace(["\r", "\n", "\0"], '', $fallback));
    if ($fallback === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $fallback) || str_starts_with($fallback, '//') || str_contains($fallback, '\\')) {
        return 'index.php';
    }

    $parts = parse_url($fallback);
    if (
        !is_array($parts)
        || isset($parts['scheme'])
        || isset($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || (string)($parts['path'] ?? '') === ''
    ) {
        return 'index.php';
    }

    return (string)$parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function securityLocalRedirectTarget(string $target, string $fallback = 'index.php', array $allowedPatterns = []): string {
    $target = trim(str_replace(["\r", "\n", "\0"], '', $target));
    $safeFallback = securityFallbackRedirectTarget($fallback);
    if ($target === '') {
        return $safeFallback;
    }

    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) || str_starts_with($target, '//') || str_contains($target, '\\')) {
        return $safeFallback;
    }

    $parts = parse_url($target);
    if (
        !is_array($parts)
        || isset($parts['scheme'])
        || isset($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        return $safeFallback;
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        return $safeFallback;
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $candidate = $path . $query;

    if ($allowedPatterns) {
        foreach ($allowedPatterns as $pattern) {
            if (@preg_match($pattern, $candidate) === 1) {
                return $candidate;
            }
        }
        return $safeFallback;
    }

    return $candidate;
}

function securityReferrerRedirectTarget(string $fallback = 'index.php'): string {
    $fallback = securityFallbackRedirectTarget($fallback);
    $referrer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referrer === '') {
        return $fallback;
    }

    $parts = parse_url($referrer);
    if (!is_array($parts)) {
        return $fallback;
    }

    if (isset($parts['host'])) {
        $requestHost = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?: '';
        $referrerHost = preg_replace('/:\d+$/', '', (string)$parts['host']) ?: '';
        if ($requestHost === '' || strcasecmp($referrerHost, $requestHost) !== 0) {
            return $fallback;
        }
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        return $fallback;
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return securityLocalRedirectTarget($path . $query, $fallback);
}

function securityRedirect(string $target, string $fallback = 'index.php'): void {
    $target = securityLocalRedirectTarget($target, $fallback);
    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }
    echo '<script>window.location.href="' . htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '";</script>';
    exit;
}
