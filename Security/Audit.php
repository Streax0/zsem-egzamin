<?php

function securityAudit(string $event, array $details = [], string $severity = 'info'): void {
    $safeDetails = [];
    foreach ($details as $key => $value) {
        $key = (string)$key;
        if (preg_match('/token|password|secret|csrf/i', $key)) {
            $safeDetails[$key] = '[redacted]';
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $safeDetails[$key] = $value;
        }
    }

    $line = json_encode([
        'type' => 'security',
        'severity' => $severity,
        'event' => $event,
        'context' => securityRequestContext(),
        'details' => $safeDetails,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('app_log')) {
        app_log($line ?: ('security event: ' . $event));
    } else {
        error_log($line ?: ('security event: ' . $event));
    }
}
