<?php

function securityInputString($value, int $maxLength = 255): string {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function securityInputEnum($value, array $allowed, string $default = ''): string {
    $value = securityInputString($value, 80);
    return in_array($value, $allowed, true) ? $value : $default;
}

function securityInputInt($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, int $default = 0): int {
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $default;
    }
    return max($min, min($max, (int)$value));
}

function securityInputAnswerLetter($value): string {
    $value = strtoupper(securityInputString($value, 1));
    return in_array($value, ['A', 'B', 'C', 'D'], true) ? $value : '';
}

function securityJsonBody(): array {
    static $decoded = null;
    if ($decoded !== null) {
        return $decoded;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        $decoded = [];
        return $decoded;
    }

    $data = json_decode($raw, true);
    $decoded = is_array($data) ? $data : [];
    return $decoded;
}
