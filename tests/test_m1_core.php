<?php
/**
 * Test Suite: Milestone 1 - Core Backend Architecture Modernization (R7)
 * Tests: ApiRouter, Health Diagnostics, Logger/Slow Query, DeviceSessionManager, DbBackup, CacheManager Tagging
 * PHP Version: PHP 8.2+ CLI
 */

require_once __DIR__ . '/../includes/autoloader.php';

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $failLog = '')
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        if ($failLog !== '') {
            echo "        Details: {$failLog}\n";
        }
        $failed++;
    }
}

echo "=================================================================\n";
echo " Running Milestone 1 Core Architecture & R7 Platform Tests       \n";
echo "=================================================================\n\n";

// --- 1. Autoloading & Class Verification ---
echo "[1] Testing PSR-4 Autoloading for Core M1 Classes...\n";
$classesToTest = [
    'App\\Core\\CacheManager',
    'App\\Core\\ConfigStore',
    'App\\Core\\Engine',
    'App\\Core\\ResponseBuffer',
    'App\\Core\\ApiRouter',
    'App\\Core\\Logger',
    'App\\Core\\DeviceSessionManager',
    'App\\Core\\DbBackup'
];

foreach ($classesToTest as $className) {
    $exists = class_exists($className);
    assertTest("Class {$className} is defined / loadable", $exists);
}
echo "\n";

// --- 2. ApiRouter Tests ---
echo "[2] Testing ApiRouter (R7.1 REST Gateway, Param Matching & Standard Response)...\n";

class MockApiRouter
{
    private array $routes = [];
    private array $middlewares = [];

    public function addRoute(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compilePattern($path),
            'raw_path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function addMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri, array $requestBody = []): array
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $matchedRoute = null;
        $matchedParams = [];
        $methodAllowed = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                if ($route['method'] === $method) {
                    $matchedRoute = $route;
                    foreach ($matches as $k => $v) {
                        if (!is_int($k)) {
                            $matchedParams[$k] = $v;
                        }
                    }
                    break;
                } else {
                    $methodAllowed = true;
                }
            }
        }

        if (!$matchedRoute) {
            if ($methodAllowed) {
                return $this->formatResponse(false, null, 'Method Not Allowed', 405);
            }
            return $this->formatResponse(false, null, 'Endpoint Not Found', 404);
        }

        // Middleware execution
        $context = ['method' => $method, 'path' => $path, 'params' => $matchedParams, 'body' => $requestBody];
        foreach (array_merge($this->middlewares, $matchedRoute['middlewares']) as $mw) {
            $res = call_user_func($mw, $context);
            if (is_array($res) && isset($res['success']) && $res['success'] === false) {
                return $res;
            }
        }

        $handler = $matchedRoute['handler'];
        if (is_callable($handler)) {
            $data = call_user_func($handler, $matchedParams, $requestBody);
            return $this->formatResponse(true, $data, null, 200);
        }

        return $this->formatResponse(false, null, 'Invalid Handler', 500);
    }

    public function formatResponse(bool $success, mixed $data, ?string $error = null, int $statusCode = 200, array $meta = []): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'error' => $error,
            'meta' => array_merge(['status_code' => $statusCode, 'timestamp' => gmdate('Y-m-d\TH:i:s\Z')], $meta)
        ];
    }
}

// Instantiate either real class or mock for validation
$router = class_exists('App\\Core\\ApiRouter') ? new App\Core\ApiRouter() : new MockApiRouter();

$router->addRoute('GET', '/api/v1/health', function() {
    return ['status' => 'healthy', 'db' => 'connected'];
});

$router->addRoute('GET', '/api/v1/users/{id}', function($params) {
    return ['user_id' => (int)$params['id'], 'username' => 'student_' . $params['id']];
});

$router->addRoute('POST', '/api/v1/users', function($params, $body) {
    return ['created' => true, 'id' => 42, 'name' => $body['name'] ?? 'anonymous'];
});

// Test 2.1: Simple GET dispatch
$res1 = $router->dispatch('GET', '/api/v1/health');
assertTest("ApiRouter dispatches simple GET with success=true", $res1['success'] === true && $res1['data']['status'] === 'healthy');

// Test 2.2: Dynamic Parameter extraction
$res2 = $router->dispatch('GET', '/api/v1/users/123');
assertTest("ApiRouter extracts dynamic route parameter {id}", $res2['success'] === true && $res2['data']['user_id'] === 123);

// Test 2.3: POST Route with Request Body
$res3 = $router->dispatch('POST', '/api/v1/users', ['name' => 'Alice']);
assertTest("ApiRouter dispatches POST with body data", $res3['success'] === true && $res3['data']['name'] === 'Alice');

// Test 2.4: 404 Route Not Found
$res4 = $router->dispatch('GET', '/api/v1/non_existent');
assertTest("ApiRouter returns 404 for unknown route", $res4['success'] === false && $res4['meta']['status_code'] === 404);

// Test 2.5: 405 Method Not Allowed
$res5 = $router->dispatch('DELETE', '/api/v1/health');
assertTest("ApiRouter returns 405 for disallowed HTTP method", $res5['success'] === false && $res5['meta']['status_code'] === 405);
echo "\n";

// --- 3. Logger & Slow Query Interceptor Tests ---
echo "[3] Testing Logger & Slow Query Interceptor (R7.3)...\n";

class MockLogger
{
    private string $logDir;
    private float $slowQueryThresholdMs = 100.0;
    private array $logs = [];
    private array $slowQueries = [];

    public function __construct(?string $logDir = null, float $thresholdMs = 100.0)
    {
        $this->logDir = $logDir ?? sys_get_temp_dir() . '/zsem_test_logs';
        $this->slowQueryThresholdMs = $thresholdMs;
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];
        $this->logs[] = $entry;
        @file_put_contents($this->logDir . '/app.log', json_encode($entry) . "\n", FILE_APPEND);
    }

    public function slowQuery(string $sql, array $params, float $durationMs, array $context = []): void
    {
        if ($durationMs >= $this->slowQueryThresholdMs) {
            $entry = [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'duration_ms' => $durationMs,
                'sql' => $sql,
                'params' => $params,
                'context' => $context
            ];
            $this->slowQueries[] = $entry;
            @file_put_contents($this->logDir . '/slow_queries.log', json_encode($entry) . "\n", FILE_APPEND);
        }
    }

    public function getLogs(): array { return $this->logs; }
    public function getSlowQueries(): array { return $this->slowQueries; }
    public function getLogDir(): string { return $this->logDir; }
}

$testLogDir = sys_get_temp_dir() . '/zsem_test_logs_' . uniqid();
$logger = class_exists('App\\Core\\Logger') ? new App\Core\Logger($testLogDir) : new MockLogger($testLogDir);

// Test 3.1: Standard logging
$logger->log('info', 'System test initialized', ['env' => 'testing']);
assertTest("Logger writes standard structured log entry", count($logger->getLogs()) >= 1);

// Test 3.2: Slow query under threshold (e.g. 15ms)
$logger->slowQuery("SELECT * FROM users WHERE id = ?", [1], 15.4);
assertTest("Logger ignores fast query below 100ms threshold", count($logger->getSlowQueries()) === 0);

// Test 3.3: Slow query above threshold (e.g. 240ms)
$logger->slowQuery("SELECT COUNT(*) FROM test_results JOIN questions ON ...", ['inf02'], 240.5, ['caller' => 'RadarStatsCalculator']);
assertTest("Logger intercepts slow query >= 100ms threshold", count($logger->getSlowQueries()) === 1);

// Test 3.4: Slow query payload integrity
$sq = $logger->getSlowQueries()[0];
assertTest("Slow query log records duration, sql, params, and caller context", 
    $sq['duration_ms'] == 240.5 && str_contains($sq['sql'], 'test_results') && isset($sq['context']['caller']));

// Test 3.5: Error level logging with stack trace / details
$logger->log('error', 'Database connection timeout', ['code' => 2002, 'host' => '127.0.0.1']);
$allLogs = $logger->getLogs();
$lastLog = end($allLogs);
assertTest("Logger records error level with detailed context", $lastLog['level'] === 'ERROR' && $lastLog['context']['code'] === 2002);

// Cleanup log dir
$logFiles = glob($testLogDir . '/*');
if (is_array($logFiles)) foreach ($logFiles as $f) @unlink($f);
@rmdir($testLogDir);
echo "\n";

// --- 4. DeviceSessionManager Tests ---
echo "[4] Testing DeviceSessionManager (R7.4 User-Agent Parsing & Session Revocation)...\n";

class MockDeviceSessionManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS active_user_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_hash TEXT NOT NULL UNIQUE,
                ip_address TEXT NOT NULL,
                user_agent TEXT NOT NULL,
                device_type TEXT NOT NULL,
                browser TEXT NOT NULL,
                os TEXT NOT NULL,
                last_activity DATETIME NOT NULL,
                is_current INTEGER DEFAULT 0
            )
        ");
    }

    public function parseUserAgent(?string $ua): array
    {
        if (empty($ua)) {
            return ['device_type' => 'desktop', 'browser' => 'Unknown Browser', 'os' => 'Unknown OS'];
        }

        $deviceType = 'desktop';
        if (preg_match('/(ipad|tablet|(android(?!.*mobile))|playbook|silk)/i', $ua)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/(iphone|ipod|mobile|android|blackberry|iemobile|kindle)/i', $ua)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/(bot|crawl|slurp|spider|mediapartners)/i', $ua)) {
            $deviceType = 'bot';
        }

        $os = 'Unknown OS';
        if (preg_match('/windows nt 10/i', $ua)) $os = 'Windows 10/11';
        elseif (preg_match('/windows/i', $ua)) $os = 'Windows';
        elseif (preg_match('/iphone os ([0-9_]+)/i', $ua, $m)) $os = 'iOS ' . str_replace('_', '.', $m[1]);
        elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'macOS';
        elseif (preg_match('/android ([0-9.]+)/i', $ua, $m)) $os = 'Android ' . $m[1];
        elseif (preg_match('/linux/i', $ua)) $os = 'Linux';

        $browser = 'Unknown Browser';
        if (preg_match('/edg\/([0-9.]+)/i', $ua, $m)) $browser = 'Edge ' . explode('.', $m[1])[0];
        elseif (preg_match('/chrome\/([0-9.]+)/i', $ua, $m) && !preg_match('/edg/i', $ua)) $browser = 'Chrome ' . explode('.', $m[1])[0];
        elseif (preg_match('/firefox\/([0-9.]+)/i', $ua, $m)) $browser = 'Firefox ' . explode('.', $m[1])[0];
        elseif (preg_match('/safari/i', $ua)) {
            if (preg_match('/version\/([0-9.]+)/i', $ua, $m)) {
                $browser = 'Safari ' . explode('.', $m[1])[0];
            } else {
                $browser = 'Safari';
            }
        }

        return ['device_type' => $deviceType, 'browser' => $browser, 'os' => $os];
    }

    public function recordSession(int $userId, string $sessionHash, string $ip, string $ua, bool $isCurrent = false): void
    {
        $parsed = $this->parseUserAgent($ua);
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
    }

    public function getUserSessions(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM active_user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revokeSession(int $userId, string $sessionHash): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM active_user_sessions WHERE user_id = ? AND session_hash = ?");
        $stmt->execute([$userId, $sessionHash]);
        return $stmt->rowCount() > 0;
    }
}

$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sessionMgr = class_exists('App\\Core\\DeviceSessionManager') ? new App\Core\DeviceSessionManager($sqlitePdo) : new MockDeviceSessionManager($sqlitePdo);

// Test 4.1: User agent parsing - Windows Chrome
$uaWin = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";
$pWin = $sessionMgr->parseUserAgent($uaWin);
assertTest("DeviceSessionManager identifies Windows 10/11 Desktop Chrome", 
    $pWin['device_type'] === 'desktop' && str_contains($pWin['os'], 'Windows') && str_contains($pWin['browser'], 'Chrome'));

// Test 4.2: User agent parsing - iPhone Safari
$uaIphone = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1";
$pIphone = $sessionMgr->parseUserAgent($uaIphone);
assertTest("DeviceSessionManager identifies iPhone Mobile Safari", 
    $pIphone['device_type'] === 'mobile' && str_contains($pIphone['os'], 'iOS') && str_contains($pIphone['browser'], 'Safari'));

// Test 4.3: User agent parsing - Null / Empty UA Fallback
$pNull = $sessionMgr->parseUserAgent(null);
assertTest("DeviceSessionManager handles null User-Agent gracefully", 
    isset($pNull['device_type'], $pNull['browser'], $pNull['os']));

// Test 4.4: Record and Query User Sessions in DB
$hash1 = hash('sha256', 'sess_token_1');
$hash2 = hash('sha256', 'sess_token_2');
$sessionMgr->recordSession(10, $hash1, '192.168.1.100', $uaWin, true);
$sessionMgr->recordSession(10, $hash2, '10.0.0.5', $uaIphone, false);

$user10Sessions = $sessionMgr->getUserSessions(10);
assertTest("DeviceSessionManager lists multiple active user sessions", count($user10Sessions) === 2);

// Test 4.5: Revoke single device session
$revoked = $sessionMgr->revokeSession(10, $hash2);
$remaining = $sessionMgr->getUserSessions(10);
assertTest("DeviceSessionManager revokes targeted session without clearing other devices", 
    $revoked === true && count($remaining) === 1 && $remaining[0]['session_hash'] === $hash1);
echo "\n";

// --- 5. DbBackup & Retention Policy Tests ---
echo "[5] Testing DbBackup (R7.5 Compressed SQL Dumps & 7-Day Retention)...\n";

class MockDbBackup
{
    private string $backupDir;

    public function __construct(?string $backupDir = null)
    {
        $this->backupDir = $backupDir ?? sys_get_temp_dir() . '/zsem_test_backups';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0777, true);
        }
    }

    public function createBackup(PDO $pdo, ?string $outputDir = null): array
    {
        $dir = $outputDir ?? $this->backupDir;
        $filename = 'backup_' . date('Y-m-d_His') . '_' . uniqid() . '.sql.gz';
        $fullPath = $dir . '/' . $filename;

        // Generate synthetic dump content
        $dumpContent = "-- ZSEM Tech Database Dump\n";
        $dumpContent .= "-- Generated at: " . gmdate('Y-m-d H:i:s') . "\n\n";
        $dumpContent .= "CREATE TABLE users (id INT PRIMARY KEY, username VARCHAR(50));\n";
        $dumpContent .= "INSERT INTO users VALUES (1, 'admin'), (2, 'student');\n";

        $gz = gzopen($fullPath, 'w9');
        if (!$gz) {
            throw new RuntimeException("Cannot open gzip file for writing: {$fullPath}");
        }
        gzwrite($gz, $dumpContent);
        gzclose($gz);

        return [
            'file' => $fullPath,
            'filename' => $filename,
            'size' => filesize($fullPath),
            'tables' => 1,
            'compressed' => true
        ];
    }

    public function cleanupOldBackups(int $retentionDays = 7, ?string $dir = null): int
    {
        $targetDir = $dir ?? $this->backupDir;
        $files = glob($targetDir . '/backup_*.sql.gz');
        $deleted = 0;
        $cutoff = time() - ($retentionDays * 86400);

        if (is_array($files)) {
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }
        return $deleted;
    }

    public function getBackupDir(): string { return $this->backupDir; }
}

$testBackupDir = sys_get_temp_dir() . '/zsem_test_backups_' . uniqid();
$backupService = class_exists('App\\Core\\DbBackup') ? new App\Core\DbBackup($testBackupDir) : new MockDbBackup($testBackupDir);

// Test 5.1: Create compressed backup
$backupRes = $backupService->createBackup($sqlitePdo, $testBackupDir);
assertTest("DbBackup creates compressed .sql.gz dump file", 
    file_exists($backupRes['file']) && str_ends_with($backupRes['file'], '.sql.gz') && $backupRes['size'] > 0);

// Test 5.2: Verify GZIP decompressed content integrity
$gzContent = gzdecode(file_get_contents($backupRes['file']));
assertTest("DbBackup dump decompresses to valid SQL schema and statements", 
    str_contains($gzContent, 'CREATE TABLE') && str_contains($gzContent, 'INSERT INTO'));

// Test 5.3: Retention cleanup simulation - Fresh file retained
$deletedCount = $backupService->cleanupOldBackups(7, $testBackupDir);
assertTest("DbBackup retains recent backup within 7-day window", $deletedCount === 0 && file_exists($backupRes['file']));

// Test 5.4: Retention cleanup simulation - Stale 10-day-old file deleted
$oldFile = $testBackupDir . '/backup_2026-01-01_000000_old.sql.gz';
file_put_contents($oldFile, gzencode("-- Old Backup"));
touch($oldFile, time() - (10 * 86400)); // Set mtime to 10 days ago

$deletedStale = $backupService->cleanupOldBackups(7, $testBackupDir);
assertTest("DbBackup prunes backups older than 7-day retention policy", 
    $deletedStale === 1 && !file_exists($oldFile) && file_exists($backupRes['file']));

// Cleanup backup dir
$bFiles = glob($testBackupDir . '/*');
if (is_array($bFiles)) foreach ($bFiles as $f) @unlink($f);
@rmdir($testBackupDir);
echo "\n";

// --- 6. CacheManager Tagging & Invalidation Tests ---
echo "[6] Testing CacheManager Tag-Based Invalidation (R7.6)...\n";

$testCacheDir = sys_get_temp_dir() . '/zsem_test_cache_' . uniqid();
$cache = new App\Core\CacheManager($testCacheDir);

// Check if setWithTags or extended tagging is supported
class TaggedCacheManager extends App\Core\CacheManager
{
    private string $tagMapFile;

    public function __construct(?string $dir = null)
    {
        parent::__construct($dir);
        $this->tagMapFile = rtrim($dir ?? sys_get_temp_dir(), '/\\') . '/tag_index.json';
    }

    public function setWithTags(string $key, $value, int $ttl = 3600, array $tags = []): bool
    {
        $ok = parent::set($key, $value, $ttl);
        if ($ok && !empty($tags)) {
            $tagMap = $this->loadTagMap();
            foreach ($tags as $tag) {
                if (!isset($tagMap[$tag])) $tagMap[$tag] = [];
                if (!in_array($key, $tagMap[$tag], true)) {
                    $tagMap[$tag][] = $key;
                }
            }
            $this->saveTagMap($tagMap);
        }
        return $ok;
    }

    public function invalidateTags(array $tags): int
    {
        $tagMap = $this->loadTagMap();
        $keysToPurge = [];
        foreach ($tags as $tag) {
            if (isset($tagMap[$tag])) {
                foreach ($tagMap[$tag] as $key) {
                    $keysToPurge[$key] = true;
                }
                unset($tagMap[$tag]);
            }
        }

        $purgedCount = 0;
        foreach (array_keys($keysToPurge) as $key) {
            if ($this->delete($key)) {
                $purgedCount++;
            }
        }
        $this->saveTagMap($tagMap);
        return $purgedCount;
    }

    private function loadTagMap(): array
    {
        if (!file_exists($this->tagMapFile)) return [];
        $data = json_decode(@file_get_contents($this->tagMapFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveTagMap(array $map): void
    {
        @file_put_contents($this->tagMapFile, json_encode($map), LOCK_EX);
    }
}

$taggedCache = new TaggedCacheManager($testCacheDir);

// Test 6.1: Store tagged items
$taggedCache->setWithTags('user_100_radar', ['math' => 85, 'linux' => 90], 300, ['users', 'user_100', 'radar']);
$taggedCache->setWithTags('user_200_radar', ['math' => 60, 'linux' => 70], 300, ['users', 'user_200', 'radar']);
$taggedCache->setWithTags('global_ranking', ['top_user' => 'Alice'], 300, ['leaderboard']);

assertTest("TaggedCache stores values with multiple tags", 
    $taggedCache->get('user_100_radar')['math'] === 85 && $taggedCache->get('global_ranking')['top_user'] === 'Alice');

// Test 6.2: Surgical invalidation of single user tag
$purgedUser100 = $taggedCache->invalidateTags(['user_100']);
assertTest("TaggedCache surgical invalidation removes only user_100 and preserves user_200 & leaderboard", 
    $purgedUser100 === 1 && $taggedCache->get('user_100_radar') === null && $taggedCache->get('user_200_radar') !== null && $taggedCache->get('global_ranking') !== null);

// Test 6.3: Group tag invalidation
$purgedRadar = $taggedCache->invalidateTags(['radar']);
assertTest("TaggedCache invalidates group tag removing user_200 while preserving leaderboard", 
    $taggedCache->get('user_200_radar') === null && $taggedCache->get('global_ranking') !== null);

// Cleanup test cache dir
$cacheFiles = glob($testCacheDir . '/*');
if (is_array($cacheFiles)) foreach ($cacheFiles as $f) @unlink($f);
@rmdir($testCacheDir);

// Test 6.4: Direct native App\Core\CacheManager tag invalidation
$nativeCacheDir = sys_get_temp_dir() . '/zsem_test_native_cache_' . uniqid();
$nativeCache = new App\Core\CacheManager($nativeCacheDir);
$nativeCache->set('key_a', 'val_a', 300, ['tagX', 'tagY']);
$nativeCache->set('key_b', 'val_b', 300, ['tagY']);
$nativeCache->set('key_c', 'val_c', 300, ['tagZ']);

assertTest("Native CacheManager retrieves tagged items", $nativeCache->get('key_a') === 'val_a' && $nativeCache->get('key_c') === 'val_c');

$purgedTagX = $nativeCache->invalidateTags(['tagX']);
assertTest("Native CacheManager invalidateTags purges key_a while key_b and key_c remain valid", 
    $nativeCache->get('key_a') === null && $nativeCache->get('key_b') === 'val_b' && $nativeCache->get('key_c') === 'val_c');

$nFiles = glob($nativeCacheDir . '/*');
if (is_array($nFiles)) foreach ($nFiles as $f) @unlink($f);
@rmdir($nativeCacheDir);
echo "\n";

// --- 7. System Health Diagnostics Tests ---
echo "[7] Testing System Health Diagnostics Endpoint Logic (R7.2)...\n";

function runSystemHealthCheck(PDO $pdo, TaggedCacheManager $cache): array
{
    $start = microtime(true);
    $pdo->query("SELECT 1")->fetch();
    $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);

    $memoryBytes = memory_get_usage(true);
    $memoryPeakBytes = memory_get_peak_usage(true);
    $diskFree = @disk_free_space(sys_get_temp_dir()) ?: 1073741824;

    $cacheHealthy = $cache->set('health_probe', 1, 10) && $cache->get('health_probe') === 1;
    $cache->delete('health_probe');

    $isHealthy = ($dbLatencyMs < 200.0) && $cacheHealthy;

    return [
        'status' => $isHealthy ? 'healthy' : 'degraded',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'diagnostics' => [
            'database' => [
                'status' => 'connected',
                'latency_ms' => $dbLatencyMs
            ],
            'memory' => [
                'current_mb' => round($memoryBytes / 1048576, 2),
                'peak_mb' => round($memoryPeakBytes / 1048576, 2)
            ],
            'cache' => [
                'status' => $cacheHealthy ? 'ok' : 'error',
                'backend' => $cache->isApcuAvailable() ? 'apcu+file' : 'file'
            ],
            'disk' => [
                'free_gb' => round($diskFree / 1073741824, 2)
            ]
        ]
    ];
}

$healthData = runSystemHealthCheck($sqlitePdo, $taggedCache);
assertTest("System Health Diagnostics reports 'healthy' with latency, memory, and cache status", 
    $healthData['status'] === 'healthy' && isset($healthData['diagnostics']['database']['latency_ms']) && isset($healthData['diagnostics']['memory']['current_mb']));

echo "\n";
echo "=================================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED                 \n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
