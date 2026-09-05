<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();

requireJsonLogin(false, [], ['ok' => false, 'results' => []], ['ok' => false, 'results' => []]);

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$query = securityInputString($_GET['q'] ?? '', 64);

$sessionLimit = securityConsumeRateLimit('live-user-search:' . securityActorKey(), 80, 60);
if (empty($sessionLimit['allowed'])) {
    http_response_code(429);
    echo securityJsonEncode(['ok' => false, 'results' => [], 'retry_after' => (int)($sessionLimit['retry_after'] ?? 0)]);
    exit;
}

if (!consumeRateLimit($pdo, 'live_user_search', (string)$userId . '|' . clientIpAddress(), 60, 300)) {
    http_response_code(429);
    echo securityJsonEncode(['ok' => false, 'results' => []]);
    exit;
}

if (mb_strlen($query) < 1) {
    echo securityJsonEncode(['ok' => true, 'results' => []]);
    exit;
}

try {
    $escapedQuery = '%' . addcslashes($query, '%_\\') . '%';
    if (roleHasAdminAccess($role)) {
        $stmt = $pdo->prepare("
            SELECT id, username, role, is_verified, xp, allow_friend_requests, last_activity, avatar_path
            FROM users
            WHERE id != ? AND username LIKE ?
            ORDER BY last_activity DESC, xp DESC, username ASC
            LIMIT 6
        ");
        $stmt->execute([$userId, $escapedQuery]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, role, is_verified, xp, allow_friend_requests, last_activity, avatar_path
            FROM users
            WHERE id != ? AND searchable = 1 AND profile_public = 1 AND username LIKE ?
            ORDER BY last_activity DESC, xp DESC, username ASC
            LIMIT 6
        ");
        $stmt->execute([$userId, $escapedQuery]);
    }

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = getFriendshipStatus($pdo, $userId, (int)$row['id']);
        $rows[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'role' => $row['role'] ?? 'user',
            'verified' => ((int)($row['is_verified'] ?? 0) === 1) || in_array($row['role'] ?? 'user', privilegedStaffRoles(), true),
            'xp' => (int)($row['xp'] ?? 0),
            'status' => $status,
            'online' => isUserOnline($row['last_activity'] ?? null),
            'avatar' => userAvatarSrc($row['avatar_path'] ?? ''),
            'can_add' => canSendFriendRequest($role, $row['role'] ?? 'user', (int)($row['allow_friend_requests'] ?? 1) === 1)
        ];
    }

    $canSendMore = canSendMoreFriendRequests($pdo, (int)$userId);
    foreach ($rows as &$row) {
        $row['can_add'] = $canSendMore && !empty($row['can_add']);
        if (!$canSendMore) {
            $row['limit_message'] = 'Limit 4 wysłanych zaproszeń został osiągnięty.';
        }
    }
    unset($row);
    echo securityJsonEncode(['ok' => true, 'results' => $rows]);
} catch (PDOException $e) {
    error_log('Live user search failed: ' . $e->getMessage());
    http_response_code(500);
    securityAudit('live_user_search_failed', ['user_id' => $userId], 'error');
    echo securityJsonEncode(['ok' => false, 'results' => []]);
}
