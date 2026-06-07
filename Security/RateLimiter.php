<?php

function securityConsumeRateLimit(string $bucket, int $limit, int $windowSeconds): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return ['allowed' => true, 'remaining' => $limit, 'retry_after' => 0];
    }

    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $now = time();
    $key = hash('sha256', $bucket);

    if (!isset($_SESSION['security_rate_limits']) || !is_array($_SESSION['security_rate_limits'])) {
        $_SESSION['security_rate_limits'] = [];
    }

    $store =& $_SESSION['security_rate_limits'];
    foreach ($store as $storedKey => $entry) {
        if (!is_array($entry) || (int)($entry['reset_at'] ?? 0) < $now - 5) {
            unset($store[$storedKey]);
        }
    }

    $entry = is_array($store[$key] ?? null) ? $store[$key] : ['count' => 0, 'reset_at' => $now + $windowSeconds];
    if ((int)$entry['reset_at'] <= $now) {
        $entry = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $entry['count'] = (int)$entry['count'] + 1;
    $store[$key] = $entry;

    $remaining = max(0, $limit - (int)$entry['count']);
    return [
        'allowed' => (int)$entry['count'] <= $limit,
        'remaining' => $remaining,
        'retry_after' => max(0, (int)$entry['reset_at'] - $now),
    ];
}
