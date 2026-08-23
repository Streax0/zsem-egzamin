<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class DeviceSessionManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        if (function_exists('appRuntimeSchemaUpdatesEnabled') && !appRuntimeSchemaUpdatesEnabled()) {
            return;
        }
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS active_user_sessions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        session_hash TEXT NOT NULL UNIQUE,
                        ip_address TEXT,
                        user_agent_hash TEXT,
                        user_agent TEXT,
                        device_type TEXT,
                        browser TEXT,
                        os TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                        is_current INTEGER DEFAULT 0
                    )
                ");
            } catch (Throwable $e) {
                // Table might already exist
            }
        }
    }

    public static function parseUserAgent(?string $ua): array
    {
        return [
            'device_type' => self::parseDeviceType($ua),
            'browser'     => self::parseBrowser($ua),
            'os'          => self::parseOS($ua),
        ];
    }

    public static function parseDeviceType(?string $ua): string
    {
        if (empty($ua)) {
            return 'desktop';
        }
        if (preg_match('/(ipad|tablet|(android(?!.*mobile))|playbook|silk)/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/(iphone|ipod|mobile|android|blackberry|iemobile|kindle|fennec|opera m(?:obi|ini))/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/(bot|crawl|slurp|spider|mediapartners)/i', $ua)) {
            return 'bot';
        }
        return 'desktop';
    }

    public static function parseOS(?string $ua): string
    {
        if (empty($ua)) {
            return 'Unknown OS';
        }
        if (preg_match('/windows nt 10|windows nt 11/i', $ua)) {
            return 'Windows 10/11';
        }
        if (preg_match('/windows/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/(iphone|ipad|ipod|cpu os ([0-9_]+)|iphone os ([0-9_]+))/i', $ua, $m)) {
            $ver = !empty($m[2]) ? str_replace('_', '.', $m[2]) : (!empty($m[3]) ? str_replace('_', '.', $m[3]) : '');
            return $ver !== '' ? 'iOS ' . $ver : 'iOS';
        }
        if (preg_match('/android(?: ([0-9.]+))?/i', $ua, $m)) {
            return !empty($m[1]) ? 'Android ' . $m[1] : 'Android';
        }
        if (preg_match('/macintosh|mac os x/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/linux|x11|ubuntu|fedora|debian/i', $ua)) {
            return 'Linux';
        }
        return 'Other';
    }

    public static function parseBrowser(?string $ua): string
    {
        if (empty($ua)) {
            return 'Unknown Browser';
        }
        if (preg_match('/edg(?:e|a|ios)?\/([0-9.]+)/i', $ua, $m)) {
            $major = explode('.', $m[1])[0];
            return "Edge {$major}";
        }
        if (preg_match('/opr\/([0-9.]+)|opera/i', $ua, $m)) {
            $major = isset($m[1]) ? explode('.', $m[1])[0] : '';
            return $major !== '' ? "Opera {$major}" : 'Opera';
        }
        if (preg_match('/(chrome|crios)\/([0-9.]+)/i', $ua, $m) && !preg_match('/edg/i', $ua)) {
            $major = explode('.', $m[2])[0];
            return "Chrome {$major}";
        }
        if (preg_match('/(firefox|fxios)\/([0-9.]+)/i', $ua, $m)) {
            $major = explode('.', $m[2])[0];
            return "Firefox {$major}";
        }
        if (preg_match('/version\/([0-9.]+).*safari/i', $ua, $m)) {
            $major = explode('.', $m[1])[0];
            return "Safari {$major}";
        }
        if (preg_match('/safari/i', $ua) && !preg_match('/chrome|chromium|android/i', $ua)) {
            return 'Safari';
        }
        return 'Other';
    }

    public static function formatRelativeTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return 'Nieznana';
        }
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$ts) {
            return 'Nieznana';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'Teraz';
        }
        if ($diff < 3600) {
            $mins = (int)floor($diff / 60);
            return $mins . ' min temu';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' godz. temu';
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ($days === 1 ? ' dzień temu' : ' dni temu');
        }
        return date('d.m.Y H:i', $ts);
    }

    public static function getDeviceIcon(string $deviceType): string
    {
        return match (strtolower($deviceType)) {
            'mobile' => 'bi-phone',
            'tablet' => 'bi-tablet',
            'desktop' => 'bi-laptop',
            default => 'bi-display',
        };
    }

    public function recordSession(int $userId, string $sessionHash, string $ip, string $ua, bool $isCurrent = false): void
    {
        $parsed = self::parseUserAgent($ua);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("
                    INSERT OR REPLACE INTO active_user_sessions 
                    (user_id, session_hash, ip_address, user_agent, device_type, browser, os, last_activity, is_current)
                    VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), ?)
                ");
                $stmt->execute([
                    $userId,
                    $sessionHash,
                    $ip,
                    $ua,
                    $parsed['device_type'],
                    $parsed['browser'],
                    $parsed['os'],
                    $isCurrent ? 1 : 0
                ]);
            } else {
                $uaHash = hash('sha256', $ua);
                $stmt = $this->pdo->prepare("
                    INSERT INTO active_user_sessions (user_id, session_hash, ip_address, user_agent_hash, user_agent, device_type, browser, os, last_seen)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        ip_address = VALUES(ip_address),
                        user_agent_hash = VALUES(user_agent_hash),
                        user_agent = VALUES(user_agent),
                        device_type = VALUES(device_type),
                        browser = VALUES(browser),
                        os = VALUES(os),
                        last_seen = NOW()
                ");
                $stmt->execute([
                    $userId,
                    $sessionHash,
                    $ip,
                    $uaHash,
                    $ua,
                    $parsed['device_type'],
                    $parsed['browser'],
                    $parsed['os']
                ]);
            }
        } catch (Throwable $e) {
            // Fallback for minimal table schemas
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO active_user_sessions (user_id, session_hash, ip_address, user_agent)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $sessionHash, $ip, $ua]);
            } catch (Throwable $ex) {
                error_log('DeviceSessionManager::recordSession error: ' . $ex->getMessage());
            }
        }
    }

    public function registerSession(int $userId, ?string $sessionHash = null, ?string $ip = null, ?string $ua = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sessionHash = $sessionHash ?? (function_exists('currentSessionHash') ? currentSessionHash() : '');
        if ($sessionHash === '') {
            return false;
        }

        $ip = $ip ?? (function_exists('authClientIpAddress') ? authClientIpAddress() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        $ua = $ua ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        $this->recordSession($userId, $sessionHash, $ip, $ua, true);
        return true;
    }

    public function getUserSessions(int $userId, ?string $currentSessionHash = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            try {
                $stmt = $this->pdo->prepare("SELECT id, user_id, session_hash, ip_address, user_agent, device_type, browser, os, created_at, last_seen, last_activity, is_current FROM active_user_sessions WHERE user_id = ?");
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $ex1) {
                try {
                    $stmt = $this->pdo->prepare("SELECT id, user_id, session_hash, ip_address, user_agent FROM active_user_sessions WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $ex2) {
                    $stmt = $this->pdo->prepare("SELECT id, user_id, session_hash, ip_address, created_at, last_seen FROM active_user_sessions WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            $result = [];
            foreach ($rows as $row) {
                $ua = (string)($row['user_agent'] ?? '');
                $parsed = self::parseUserAgent($ua);

                $deviceType = !empty($row['device_type']) ? (string)$row['device_type'] : $parsed['device_type'];
                $browser = !empty($row['browser']) ? (string)$row['browser'] : $parsed['browser'];
                $os = !empty($row['os']) ? (string)$row['os'] : $parsed['os'];

                $sessionHash = (string)($row['session_hash'] ?? '');
                $isCurrent = false;
                if ($currentSessionHash !== null && $sessionHash !== '') {
                    $isCurrent = hash_equals($sessionHash, $currentSessionHash);
                } elseif (isset($row['is_current'])) {
                    $isCurrent = (bool)$row['is_current'];
                }

                $activity = (string)($row['last_seen'] ?? ($row['last_activity'] ?? ($row['created_at'] ?? '')));

                $result[] = [
                    'id'                 => (int)($row['id'] ?? 0),
                    'user_id'            => (int)($row['user_id'] ?? $userId),
                    'session_hash'       => $sessionHash,
                    'ip_address'         => (string)($row['ip_address'] ?? 'Nieznany'),
                    'user_agent'         => $ua,
                    'device_type'        => $deviceType,
                    'browser'            => $browser,
                    'os'                 => $os,
                    'icon'               => self::getDeviceIcon($deviceType),
                    'created_at'         => (string)($row['created_at'] ?? $activity),
                    'last_seen'          => $activity,
                    'last_activity'      => $activity,
                    'last_seen_relative' => self::formatRelativeTime($activity),
                    'is_current'         => $isCurrent,
                ];
            }

            // Sort: current first, then activity desc
            usort($result, function ($a, $b) {
                if ($a['is_current'] !== $b['is_current']) {
                    return $a['is_current'] ? -1 : 1;
                }
                return strcmp((string)$b['last_seen'], (string)$a['last_seen']);
            });

            return $result;
        } catch (Throwable $e) {
            error_log('DeviceSessionManager::getUserSessions error: ' . $e->getMessage());
            return [];
        }
    }

    public function revokeSession(int $userId, string $sessionHash): bool
    {
        if ($userId <= 0 || empty($sessionHash)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM active_user_sessions WHERE user_id = ? AND session_hash = ?");
            $stmt->execute([$userId, $sessionHash]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('DeviceSessionManager::revokeSession error: ' . $e->getMessage());
            return false;
        }
    }

    public function revokeAllExcept(int $userId, string $currentSessionHash): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            // 1. Delete all other session rows from active_user_sessions
            $stmt = $this->pdo->prepare("DELETE FROM active_user_sessions WHERE user_id = ? AND session_hash != ?");
            $stmt->execute([$userId, $currentSessionHash]);
            $deleted = $stmt->rowCount();

            // 2. Bump user session_version to invalidate PHP session stores on all other devices
            try {
                $this->pdo->prepare("UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?")->execute([$userId]);
                $stmtVer = $this->pdo->prepare("SELECT session_version FROM users WHERE id = ?");
                $stmtVer->execute([$userId]);
                $newVer = (int)$stmtVer->fetchColumn();
                if (isset($_SESSION)) {
                    $_SESSION['session_version'] = $newVer;
                }
            } catch (Throwable $ex) {
                // Ignore if users table not present in sqlite test
            }

            return $deleted;
        } catch (Throwable $e) {
            error_log('DeviceSessionManager::revokeAllExcept error: ' . $e->getMessage());
            return 0;
        }
    }
}
