<?php
require dirname(__DIR__) . '/Security/RequestContext.php';
require dirname(__DIR__) . '/Security/PublicUrl.php';
require dirname(__DIR__) . '/Security/RateLimiter.php';
require dirname(__DIR__) . '/includes/functions.php';
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
putenv('APP_TRUST_PROXY_HEADERS'); unset($_ENV['APP_TRUST_PROXY_HEADERS']);
putenv('APP_TRUSTED_PROXY_IPS'); unset($_ENV['APP_TRUSTED_PROXY_IPS']);
$_SERVER = ['REMOTE_ADDR' => '198.51.100.10', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https', 'SERVER_PORT' => '80'];
check(securityClientIp() === '198.51.100.10', 'proxy IP trusted by default');
check(securityRequestIsSecure() === false, 'proxy HTTPS trusted by default');
putenv('APP_TRUST_PROXY_HEADERS=true');
check(securityClientIp() === '198.51.100.10', 'proxy trusted without allowlist');
check(securityRequestIsSecure() === false, 'proxy HTTPS trusted without allowlist');
putenv('APP_TRUSTED_PROXY_IPS=198.51.100.0/24');
check(securityClientIp() === '203.0.113.9', 'allowlisted proxy IP ignored');
check(securityRequestIsSecure() === true, 'allowlisted proxy HTTPS ignored');
putenv('APP_TRUSTED_PROXY_IPS=192.0.2.1');
check(securityClientIp() === '198.51.100.10', 'non-allowlisted proxy trusted');
check(securityRequestIsSecure() === false, 'non-allowlisted proxy HTTPS trusted');
putenv('APP_BASE_URL=https://example.test/app');
check(securityPasswordResetUrl('a+b/c') === 'https://example.test/app/forgot_password.php?token=a%2Bb%2Fc', 'configured reset URL invalid');
putenv('APP_BASE_URL'); unset($_ENV['APP_BASE_URL']);
putenv('CLIENT_URL=http://localhost:5173');
check(securityPublicBaseUrl() === 'http://localhost:5173', 'CLIENT_URL fallback ignored');
putenv("APP_BASE_URL=https://evil.test\r\nBcc:x@y.test");
putenv('CLIENT_URL'); unset($_ENV['CLIENT_URL']);
check(securityPublicBaseUrl() === 'https://zsem-egzamin.online', 'header injection URL accepted');
check(securityNormalizePublicBaseUrl('javascript://evil.test') === null, 'unsafe scheme accepted');
check(normalizeNotificationActionUrl('javascript:alert%281%29') === null, 'javascript notification URL accepted');
check(normalizeNotificationActionUrl('//evil.test/path') === null, 'protocol-relative notification URL accepted');
check(normalizeNotificationActionUrl('/duels/lobby.php?id=42') === 'duels/lobby.php?id=42', 'valid local notification URL rejected');
putenv('APP_BASE_URL=https://example.test/app');
check(normalizeNotificationActionUrl('https://example.test/app/duels/lobby.php?id=42') === 'duels/lobby.php?id=42', 'configured-origin notification URL rejected');
check(notificationActionHref('https://example.test/app/duels/lobby.php?id=42', '../') === '../duels/lobby.php?id=42', 'nested notification href duplicated app base path');
check(notificationActionHref('/app/duels/lobby.php?id=42', '') === 'duels/lobby.php?id=42', 'root-relative notification href duplicated app base path');
check(notificationActionHref('/app/duels/lobby.php?id=42', '../') === '../duels/lobby.php?id=42', 'nested root-relative notification href duplicated app base path');
check(notificationActionHref('/duels/lobby.php?id=42', '') === 'duels/lobby.php?id=42', 'root notification href invalid');
check(normalizeNotificationActionUrl('https://example.test/outside') === null, 'notification URL escaped configured app path');
check(normalizeNotificationActionUrl('https://evil.test/app/duels/lobby.php') === null, 'cross-origin notification URL accepted');
check(normalizeNotificationActionUrl('/app/%2e%2e/admin.php') === null, 'encoded notification path traversal accepted');
check(normalizeNotificationActionUrl('/app/%252e%252e/admin.php') === null, 'double-encoded notification path traversal accepted');
putenv('APP_BASE_URL'); unset($_ENV['APP_BASE_URL']);
$rateLimitDir = sys_get_temp_dir() . '/zsemtech-rate-test-' . bin2hex(random_bytes(4));
putenv('APP_RATE_LIMIT_DIR=' . $rateLimitDir);
$bucket = 'runtime-test:' . bin2hex(random_bytes(6));
check(securityConsumeRateLimit($bucket, 2, 60)['allowed'] === true, 'first rate-limit request blocked');
check(securityConsumeRateLimit($bucket, 2, 60)['allowed'] === true, 'second rate-limit request blocked');
check(securityConsumeRateLimit($bucket, 2, 60)['allowed'] === false, 'shared rate limit not enforced');
$stalePath = $rateLimitDir . '/' . str_repeat('a', 64) . '.json';
file_put_contents($stalePath, '{}');
touch($stalePath, time() - 259200);
securityPruneRateLimitFiles($rateLimitDir, time() + 3601);
check(!is_file($stalePath), 'expired rate-limit file not pruned');
foreach (glob($rateLimitDir . '/*.json') ?: [] as $path) {
    unlink($path);
}
if (is_file($rateLimitDir . '/.cleanup')) {
    unlink($rateLimitDir . '/.cleanup');
}
if (is_dir($rateLimitDir)) {
    rmdir($rateLimitDir);
}
echo "security helper runtime OK\n";
