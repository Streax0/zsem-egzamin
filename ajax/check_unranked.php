<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';

startSecureSession();
securityApplyJsonHeaders();

if (!isLoggedIn()) {
    echo securityJsonEncode(['used' => 0]);
    exit;
}
requireJsonLogin(false, [], ['used' => 0], ['used' => 0]);

$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS unranked_usage (
        user_id INT NOT NULL,
        used_date DATE NOT NULL,
        usage_count INT NOT NULL DEFAULT 0,
        PRIMARY KEY (user_id, used_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt = $pdo->prepare("SELECT usage_count FROM unranked_usage WHERE user_id = ? AND used_date = ?");
    $stmt->execute([$userId, $today]);
    $row = $stmt->fetch();
    echo securityJsonEncode(['used' => $row ? (int)$row['usage_count'] : 0]);
} catch (PDOException $e) {
    securityAudit('check_unranked_failed', ['user_id' => $userId], 'error');
    echo securityJsonEncode(['used' => 0]);
}
