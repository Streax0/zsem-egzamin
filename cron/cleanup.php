<?php
/**
 * Database Maintenance & Cleanup Script
 * Recommended to run via CRON every 24 hours.
 * 
 * Objectives:
 * 1. Remove detailed answer logs older than 30 days (RODO & Optimization)
 * 2. Remove expired and abandoned exam sessions
 * 3. Clean up old notifications
 */

require_once __DIR__ . '/../config/db.php';

$isCli = PHP_SAPI === 'cli';
$configuredSecret = configValue('CRON_SECRET', '');
if (!$isCli) {
    $providedSecret = $_GET['secret'] ?? ($_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if ($configuredSecret === '' || !hash_equals($configuredSecret, (string)$providedSecret)) {
        http_response_code(404);
        exit;
    }
}

echo "--- ZSEM Tech Database Cleanup Started: " . date('Y-m-d H:i:s') . " ---\n";

try {
    $pdo->beginTransaction();

    // 1. Cleanup Detailed Test Answers (> 30 days)
    // We keep the test_results (summary), but delete individual answer records.
    $stmt1 = $pdo->prepare("
        DELETE FROM test_answers 
        WHERE result_id IN (
            SELECT id FROM test_results WHERE test_date < NOW() - INTERVAL 30 DAY
        )
    ");
    $stmt1->execute();
    echo "Removed " . $stmt1->rowCount() . " detailed test answers.\n";

    // 2. Cleanup Detailed Exam Answers & Violations (> 30 days)
    // We keep exam_participants (summary), but delete detailed logs.
    $stmt2 = $pdo->prepare("
        DELETE FROM exam_answers 
        WHERE session_id IN (
            SELECT id FROM exam_sessions WHERE finished_at < NOW() - INTERVAL 30 DAY OR (status = 'expired' AND expires_at < NOW() - INTERVAL 30 DAY)
        )
    ");
    $stmt2->execute();
    echo "Removed " . $stmt2->rowCount() . " detailed exam answers.\n";

    $stmt3 = $pdo->prepare("
        DELETE FROM exam_violations 
        WHERE session_id IN (
            SELECT id FROM exam_sessions WHERE finished_at < NOW() - INTERVAL 30 DAY OR (status = 'expired' AND expires_at < NOW() - INTERVAL 30 DAY)
        )
    ");
    $stmt3->execute();
    echo "Removed " . $stmt3->rowCount() . " exam violations.\n";

    // 3. Cleanup Old Notifications (> 60 days)
    $stmt4 = $pdo->prepare("DELETE FROM notifications WHERE created_at < NOW() - INTERVAL 60 DAY");
    $stmt4->execute();
    echo "Removed " . $stmt4->rowCount() . " old notifications.\n";

    // 4. Cleanup Expired Unfinished Sessions (> 7 days)
    // Delete sessions that never started and are expired.
    $stmt5 = $pdo->prepare("DELETE FROM exam_sessions WHERE status = 'expired' AND created_at < NOW() - INTERVAL 7 DAY AND finished_at IS NULL");
    $stmt5->execute();
    echo "Removed " . $stmt5->rowCount() . " abandoned/expired exam sessions.\n";

    $pdo->commit();
    echo "--- Cleanup Completed Successfully ---\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "ERROR during cleanup: " . $e->getMessage() . "\n";
}
