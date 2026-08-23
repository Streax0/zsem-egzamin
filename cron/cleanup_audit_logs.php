<?php
/**
 * Automated Security & Admin Audit Logs Retention Script
 *
 * Deletes security and admin audit logs older than 30 days.
 * Execution:
 *   CLI:  php cron/cleanup_audit_logs.php
 *   HTTP: GET /cron/cleanup_audit_logs.php?secret=<CRON_SECRET>
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

$isCli = PHP_SAPI === 'cli';
$configuredSecret = configValue('CRON_SECRET', '');

if (!$isCli) {
    $providedSecret = $_GET['secret'] ?? ($_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if ($configuredSecret === '' || !hash_equals($configuredSecret, (string)$providedSecret)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Not found or invalid secret.']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

$retentionDays = 30;
$totalDeleted = 0;
$results = [];

try {
    // 1. Purge admin_audit_log entries older than 30 days
    $stmt1 = $pdo->prepare("DELETE FROM admin_audit_log WHERE created_at < NOW() - INTERVAL ? DAY");
    $stmt1->execute([$retentionDays]);
    $adminCount = $stmt1->rowCount();
    $totalDeleted += $adminCount;
    $results['admin_audit_log'] = $adminCount;

    // 2. Purge security_audit_logs entries older than 30 days if table exists
    $secCount = 0;
    try {
        $stmt2 = $pdo->prepare("DELETE FROM security_audit_logs WHERE created_at < NOW() - INTERVAL ? DAY");
        $stmt2->execute([$retentionDays]);
        $secCount = $stmt2->rowCount();
        $totalDeleted += $secCount;
    } catch (Throwable $e) {}
    $results['security_audit_logs'] = $secCount;

    $msg = "Audit logs retention completed: purged {$totalDeleted} log entries older than {$retentionDays} days.";
    if ($isCli) {
        echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";
    } else {
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'details' => $results,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    $err = "Audit log cleanup error: " . $e->getMessage();
    error_log($err);
    if ($isCli) {
        fwrite(STDERR, "[ERROR] {$err}\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $err]);
    }
}
