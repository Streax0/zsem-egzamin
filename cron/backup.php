<?php
/**
 * Database Backup Cron Script
 * Recommended to run every 24 hours.
 * 
 * Supports:
 * - CLI execution: php cron/backup.php
 * - HTTP execution: GET /cron/backup.php?secret=YOUR_CRON_SECRET
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/autoloader.php';

use App\Core\DbBackup;

$isCli = PHP_SAPI === 'cli';
$configuredSecret = function_exists('configValue') ? configValue('CRON_SECRET', '') : (defined('CRON_SECRET') ? CRON_SECRET : '');

if (!$isCli) {
    $providedSecret = $_GET['secret'] ?? ($_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if ($configuredSecret === '' || !hash_equals((string)$configuredSecret, (string)$providedSecret)) {
        http_response_code(404);
        exit;
    }
}

echo "--- ZSEM Tech Database Backup Job Started: " . date('Y-m-d H:i:s') . " ---\n";

try {
    $backupService = new DbBackup($pdo ?? null);
    $result = $backupService->createBackup();

    echo "Backup created successfully!\n";
    echo "  File: " . $result['filename'] . "\n";
    echo "  Size: " . $result['size_formatted'] . "\n";
    echo "  Tables: " . $result['tables_count'] . "\n";
    echo "  Rows: " . $result['rows_count'] . "\n";
    echo "  Duration: " . $result['duration_seconds'] . "s\n";

    // Prune backups older than 7 days
    $pruned = $backupService->cleanupOldBackups(7);
    echo "Retention Cleanup: Removed " . $pruned . " backup(s) older than 7 days.\n";
    echo "--- Backup Job Completed Successfully ---\n";

} catch (Throwable $e) {
    echo "ERROR during backup: " . $e->getMessage() . "\n";
    if ($isCli) {
        exit(1);
    }
}
