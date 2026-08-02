<?php

namespace App\Security;

class Firewall
{
    private Waf $waf;
    private string $bannedIpsPath;
    private string $violationsPath;

    public function __construct(?Waf $waf = null, ?string $bannedIpsPath = null, ?string $violationsPath = null)
    {
        $this->waf = $waf ?? new Waf();
        $this->bannedIpsPath = $bannedIpsPath ?? __DIR__ . '/../../../data/config/banned_ips.json';
        $this->violationsPath = $violationsPath ?? __DIR__ . '/../../../data/logs/ip_violations.json';
    }

    public function protectRequest(string $wafLevel = 'medium', bool $enforceCsrf = true): bool
    {
        $ip = function_exists('securityClientIp') ? securityClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if ($ip === '0.0.0.0') {
            $ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        }

        // Localhost IP (127.0.0.1 / ::1) is never blocked
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        if ($this->isBanned($ip)) {
            return false;
        }

        // Authenticated admins accessing /admin/ are allowed
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/admin/')) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
            $role = (string)($_SESSION['role'] ?? '');
            if (in_array($role, ['admin', 'dyrektor'], true)) {
                return true;
            }
        }

        $inputData = array_merge($_GET ?? [], $_POST ?? []);
        if ($this->checkHoneypot($inputData)) {
            return false;
        }

        if ($enforceCsrf) {
            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                if (function_exists('securityCsrfVerify')) {
                    if (!securityCsrfVerify()) {
                        $this->recordViolation($ip);
                        return false;
                    }
                }
            }
        }

        if (!$this->waf->inspectRequest($wafLevel)) {
            $this->recordViolation($ip);
            return false;
        }

        return true;
    }

    public function banIp(string $ip, string $reason = 'Manual ban', int $duration = 86400): bool
    {
        if (in_array(trim($ip), ['127.0.0.1', '::1', 'localhost', '0.0.0.0'], true)) {
            return false;
        }

        $dir = dirname($this->bannedIpsPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($this->bannedIpsPath, 'c+b');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $list = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $list = $decoded;
                }
            }

            $now = time();
            $expiresAt = ($duration > 0) ? ($now + $duration) : 0;

            $updated = false;
            foreach ($list as &$entry) {
                if (isset($entry['ip']) && $entry['ip'] === $ip) {
                    $entry['reason'] = $reason;
                    $entry['banned_at'] = $now;
                    $entry['expires_at'] = $expiresAt;
                    $updated = true;
                    break;
                }
            }
            unset($entry);

            if (!$updated) {
                $list[] = [
                    'ip' => $ip,
                    'reason' => $reason,
                    'banned_at' => $now,
                    'expires_at' => $expiresAt,
                ];
            }

            $json = json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function unbanIp(string $ip): bool
    {
        if (!file_exists($this->bannedIpsPath)) {
            return true;
        }

        $handle = @fopen($this->bannedIpsPath, 'c+b');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $list = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $list = $decoded;
                }
            }

            $newList = array_filter($list, function ($entry) use ($ip) {
                return !isset($entry['ip']) || $entry['ip'] !== $ip;
            });

            $json = json_encode(array_values($newList), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function isBanned(string $ip): bool
    {
        if (in_array(trim($ip), ['127.0.0.1', '::1', 'localhost', '0.0.0.0'], true)) {
            return false;
        }

        $bannedList = $this->getBannedIps();
        foreach ($bannedList as $entry) {
            if (isset($entry['ip']) && $entry['ip'] === $ip) {
                return true;
            }
        }
        return false;
    }

    public function getBannedIps(): array
    {
        if (!file_exists($this->bannedIpsPath)) {
            return [];
        }

        $handle = @fopen($this->bannedIpsPath, 'rb');
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
                if (is_array($decoded)) {
                    $now = time();
                    return array_values(array_filter($decoded, function ($entry) use ($now) {
                        if (!isset($entry['ip'])) {
                            return false;
                        }
                        $expiresAt = (int)($entry['expires_at'] ?? 0);
                        return ($expiresAt === 0 || $expiresAt > $now);
                    }));
                }
            }
            return [];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function checkHoneypot(array $data): bool
    {
        $honeypotKeys = ['_hp_trap', 'website_hp', 'hp_email', 'honeypot', 'hp_field', '_honeypot'];
        foreach ($honeypotKeys as $key) {
            if (array_key_exists($key, $data)) {
                $value = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
                if ($value !== '' && $value !== null && $value !== false) {
                    // Reject honeypot spam bot submission without auto-banning IP
                    return true;
                }
            }
        }
        return false;
    }

    public function recordViolation(string $ip, int $threshold = 5, int $duration = 86400): int
    {
        $dir = dirname($this->violationsPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($this->violationsPath, 'c+b');
        if ($handle === false) {
            return 0;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return 0;
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $violations = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $violations = $decoded;
                }
            }

            $count = (int)($violations[$ip]['count'] ?? 0) + 1;
            $violations[$ip] = [
                'count' => $count,
                'last_violation' => time(),
            ];

            // Record violation log count without auto-banning IP
            $json = json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $json);
                fflush($handle);
            }

            return $count;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
