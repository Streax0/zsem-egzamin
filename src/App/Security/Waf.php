<?php

namespace App\Security;

class Waf
{
    private string $logPath;
    private int $totalInspected = 0;
    private int $totalBlocked = 0;
    private array $attackTypeCounts = [
        'sqli' => 0,
        'xss' => 0,
        'path_traversal' => 0,
        'command_injection' => 0,
        'other' => 0,
    ];

    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?? __DIR__ . '/../../../data/logs/waf_log.json';
    }

    public function inspectRequest(string $level = 'medium'): bool
    {
        $normalizedLevel = strtolower(trim($level));
        if ($normalizedLevel === 'disabled') {
            return true;
        }

        $this->totalInspected++;

        $inputsToInspect = [
            'GET' => $_GET ?? [],
            'POST' => $_POST ?? [],
            'COOKIE' => $_COOKIE ?? [],
            'FILES' => $_FILES ?? [],
            'HEADERS' => $this->extractHeaders(),
        ];

        foreach ($inputsToInspect as $source => $data) {
            $found = $this->inspectData($data, $normalizedLevel, $source);
            if ($found !== null) {
                $this->totalBlocked++;
                $attackType = $found['attack_type'];
                if (isset($this->attackTypeCounts[$attackType])) {
                    $this->attackTypeCounts[$attackType]++;
                } else {
                    $this->attackTypeCounts['other']++;
                }

                $ip = function_exists('securityClientIp') ? securityClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
                if ($ip === '0.0.0.0') {
                    $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
                }

                $this->logBlockedAttack([
                    'timestamp' => time(),
                    'ip' => $ip,
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    'source' => $source,
                    'param' => $found['param'],
                    'attack_type' => $attackType,
                    'payload' => $found['payload'],
                    'level' => $normalizedLevel,
                ]);

                return false;
            }
        }

        return true;
    }

    private function extractHeaders(): array
    {
        $headers = [];
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $headers['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
            $headers['Referer'] = $_SERVER['HTTP_REFERER'];
        }
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    private function inspectData($data, string $level, string $source, string $prefix = ''): ?array
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $paramName = $prefix !== '' ? "{$prefix}[{$key}]" : (string)$key;
                $cleanKey = strtolower((string)$key);

                // Skip exempted text content parameters (e.g. code, question, answer, markdown)
                if (in_array($cleanKey, $this->exemptParams, true)) {
                    continue;
                }

                // Inspect key itself
                $keyMatch = $this->analyzeValue($key, $level);
                if ($keyMatch !== null) {
                    return ['param' => $paramName, 'attack_type' => $keyMatch['type'], 'payload' => (string)$key];
                }

                if (is_array($value)) {
                    $res = $this->inspectData($value, $level, $source, $paramName);
                    if ($res !== null) {
                        return $res;
                    }
                } else {
                    $valMatch = $this->analyzeValue($value, $level);
                    if ($valMatch !== null) {
                        return ['param' => $paramName, 'attack_type' => $valMatch['type'], 'payload' => (string)$value];
                    }
                }
            }
        } else {
            $match = $this->analyzeValue($data, $level);
            if ($match !== null) {
                return ['param' => $source, 'attack_type' => $match['type'], 'payload' => (string)$data];
            }
        }

        return null;
    }

    private function analyzeValue($value, string $level): ?array
    {
        if ($value === null || is_bool($value)) {
            return null;
        }

        $strict = ($level === 'strict');

        if ($this->detectSqlInjection($value, $strict)) {
            return ['type' => 'sqli'];
        }

        if ($this->detectXss($value, $strict)) {
            return ['type' => 'xss'];
        }

        if ($this->detectPathTraversal($value, $strict)) {
            return ['type' => 'path_traversal'];
        }

        if ($this->detectCommandInjection($value, $strict)) {
            return ['type' => 'command_injection'];
        }

        return null;
    }

    private array $exemptParams = [
        'code', 'sql_query', 'question_text', 'question', 'answer', 'content', 
        'description', 'solution', 'explanation', 'custom_css', 'custom_js', 
        'markdown', 'html_content', 'options', 'data'
    ];

    public function detectSqlInjection($value, bool $strict = false): bool
    {
        return $this->traverseCheck($value, function (string $str) use ($strict) {
            // Unescape/url decode once
            $decoded = rawurldecode($str);

            $patterns = [
                '/\bUNION\s+(?:ALL\s+)?SELECT\b/i',
                '/\bOR\s+[\'"]?1[\'"]?\s*=\s*[\'"]?1\b/i',
                '/\bAND\s+[\'"]?1[\'"]?\s*=\s*[\'"]?2\b/i',
                '/\bINFORMATION_SCHEMA\.(?:TABLES|COLUMNS)\b/i',
                '/--(?:[\s;]|$)/',
                '/\/\*!\d+.*?\*\//',
                '/\bSLEEP\s*\(\s*\d+\s*\)/i',
                '/\bBENCHMARK\s*\(\s*\d+/i',
                '/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[a-z0-9_]+/i',
                '/\bDELETE\s+FROM\s+[a-z0-9_]+\s+WHERE\b/i',
                '/\bINSERT\s+INTO\s+[a-z0-9_]+\s*\(/i',
            ];

            if ($strict) {
                $patterns[] = '/;\s*(?:DROP|DELETE|UPDATE|INSERT)\s+/i';
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $str) || preg_match($pattern, $decoded)) {
                    return true;
                }
            }
            return false;
        });
    }

    public function detectXss($value, bool $strict = false): bool
    {
        return $this->traverseCheck($value, function (string $str) use ($strict) {
            $decoded = rawurldecode($str);

            $patterns = [
                '/<\s*script\b[^>]*>/i',
                '/javascript\s*:\s*[^\s]/i',
                '/\bonerror\s*=\s*[\'"]?[^\'"]+/i',
                '/\bonload\s*=\s*[\'"]?[^\'"]+/i',
                '/\bonmouseover\s*=\s*[\'"]?[^\'"]+/i',
                '/<\s*iframe\b[^>]*>/i',
                '/document\s*\.\s*cookie/i',
            ];

            if ($strict) {
                $patterns[] = '/<\s*img\b[^>]*\bonerror\b/i';
                $patterns[] = '/<\s*svg\b[^>]*\bonload\b/i';
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $str) || preg_match($pattern, $decoded)) {
                    return true;
                }
            }
            return false;
        });
    }

    public function detectPathTraversal($value, bool $strict = false): bool
    {
        return $this->traverseCheck($value, function (string $str) use ($strict) {
            $decoded = rawurldecode($str);

            $patterns = [
                '/\.\.[\/\\\\]\.\.[\/\\\\]/',
                '/\/etc\/passwd\b/i',
                '/c:[\/\\\\]boot\.ini\b/i',
                '/%2e%2e%2f%2e%2e%2f/i',
            ];

            if ($strict) {
                $patterns[] = '/\.\.[\/\\\\]/';
                $patterns[] = '/\/etc\/shadow\b/i';
                $patterns[] = '/\/proc\/self\/environ/i';
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $str) || preg_match($pattern, $decoded)) {
                    return true;
                }
            }
            return false;
        });
    }

    public function detectCommandInjection($value, bool $strict = false): bool
    {
        return $this->traverseCheck($value, function (string $str) use ($strict) {
            $decoded = rawurldecode($str);

            $patterns = [
                '/(?:;\s*|\|\s*|&&\s*)(?:bash|sh|powershell|cmd|nc|netcat|wget|curl)\b/i',
                '/`[^`]*(?:whoami|id|cat|ls|pwd|netcat|powershell)[^`]*`/',
                '/\$\((?:whoami|id|cat|ls|pwd|netcat|powershell)\)/',
                '/\bnet\s+user\s+\/add\b/i',
            ];

            if ($strict) {
                $patterns[] = '/;\s*(?:ls|dir|cat|id|whoami)\b/i';
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $str) || preg_match($pattern, $decoded)) {
                    return true;
                }
            }
            return false;
        });
    }

    private function traverseCheck($value, callable $callback): bool
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_string($k) && $callback($k)) {
                    return true;
                }
                if ($this->traverseCheck($v, $callback)) {
                    return true;
                }
            }
            return false;
        }

        if (is_string($value) || is_numeric($value)) {
            return $callback((string)$value);
        }

        return false;
    }

    public function getLogs(): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $handle = @fopen($this->logPath, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }

            $raw = stream_get_contents($handle);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function clearLogs(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($this->logPath, 'c+b');
        if ($handle === false) {
            return;
        }

        try {
            if (flock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode([], JSON_PRETTY_PRINT));
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function logBlockedAttack(array $entry): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($this->logPath, 'c+b');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $logs = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $logs = $decoded;
                }
            }

            $logs[] = $entry;

            $json = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $json);
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function getStats(): array
    {
        return [
            'inspected' => $this->totalInspected,
            'total_inspected' => $this->totalInspected,
            'blocked' => $this->totalBlocked,
            'total_blocked' => $this->totalBlocked,
            'attack_types' => $this->attackTypeCounts,
        ];
    }
}
