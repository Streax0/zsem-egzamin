<?php

function securityRequireMethod(string $method, array $payload): void {
    $actual = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($actual !== strtoupper($method)) {
        securitySendJson($payload, 405);
    }
}

function securityThrottle(string $bucket, int $limit, int $windowSeconds, array $payload): void {
    $state = securityConsumeRateLimit($bucket, $limit, $windowSeconds);
    if (empty($state['allowed'])) {
        $payload['retry_after'] = (int)($state['retry_after'] ?? 0);
        securitySendJson($payload, 429);
    }
}
