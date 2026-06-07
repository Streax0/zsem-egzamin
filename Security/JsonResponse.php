<?php

function securityJsonMeta(): array {
    return [
        'request_id' => securityRequestId(),
        'server_time' => time(),
    ];
}

function securityJsonEnvelope(array $payload): array {
    if (!array_key_exists('success', $payload) && array_key_exists('ok', $payload)) {
        $payload['success'] = (bool)$payload['ok'];
    }
    if (!array_key_exists('ok', $payload) && array_key_exists('success', $payload)) {
        $payload['ok'] = (bool)$payload['success'];
    }
    return array_merge($payload, securityJsonMeta());
}

function securityJsonEncode(array $payload): string {
    $json = json_encode(securityJsonEnvelope($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        return $json;
    }
    return '{"success":false,"ok":false,"error":"JSON encoding failed","request_id":"' . securityRequestId() . '"}';
}

function securitySendJson(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        securityApplyJsonHeaders();
    }
    echo securityJsonEncode($payload);
    exit;
}
