<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
securityApplyJsonHeaders();
if (!isLoggedIn()) {
    http_response_code(401);
    echo securityJsonEncode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$myRole = (string)($_SESSION['role'] ?? 'user');
$offset = max(0, (int)($_GET['offset'] ?? 6));
$limit = min(18, max(1, (int)($_GET['limit'] ?? 6)));
$query = trim((string)($_GET['query'] ?? ''));

consumeRateLimit($pdo, 'load_more_users', (string)$userId . '|' . clientIpAddress(), 120, 300);

if (!empty($query)) {
    if (roleHasAdminAccess($myRole)) {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE username LIKE ? AND id != ? ORDER BY last_activity DESC, xp DESC, username ASC LIMIT ? OFFSET ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE username LIKE ? AND id != ? AND searchable = 1 AND profile_public = 1 ORDER BY last_activity DESC, xp DESC, username ASC LIMIT ? OFFSET ?");
    }
    $stmt->bindValue(1, '%' . $query . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    if (roleHasAdminAccess($myRole)) {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE id != ? ORDER BY last_activity DESC, xp DESC, username ASC LIMIT ? OFFSET ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE id != ? AND searchable = 1 AND profile_public = 1 ORDER BY last_activity DESC, xp DESC, username ASC LIMIT ? OFFSET ?");
    }
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrfToken = generateCsrfToken();
$items = [];
foreach ($users as $user) {
    $status = getFriendshipStatus($pdo, $userId, (int)$user['id']);
    $roleBadge = getRoleBadge((string)$user['role']);
    $avatarSrc = userAvatarSrc((string)($user['avatar_path'] ?? ''));
    $isOnline = !empty($user['last_activity']) && (time() - strtotime((string)$user['last_activity'])) < 300;
    $canAddUser = canSendFriendRequestToUser($pdo, $userId, (int)$user['id'], (string)($user['role'] ?? 'user'), (bool)($user['allow_friend_requests'] ?? true));

    ob_start();
    ?>
    <article class="user-result-card">
        <a href="user/profile.php?id=<?php echo (int)$user['id']; ?>" class="user-result-main">
            <?php if ($avatarSrc): ?>
                <img class="user-avatar-search" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="user-avatar-search" aria-hidden="true">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="min-w-0">
                <h6 class="mb-1 fw-800 text-truncate"><?php echo htmlspecialchars($user['username']); ?><?php echo getUserBadgeHtml($user['role'] ?? 'user', (int)($user['is_verified'] ?? 0)); ?></h6>
                <div class="user-status-row">
                    <span class="badge rounded-pill <?php echo $roleBadge['class']; ?>"><?php echo htmlspecialchars($roleBadge['label']); ?></span>
                    <span class="user-status-pill <?php echo $isOnline ? 'is-online' : ''; ?>">
                        <i class="bi <?php echo $isOnline ? 'bi-circle-fill' : 'bi-circle'; ?>"></i><?php echo $isOnline ? 'Online' : 'Offline'; ?>
                    </span>
                </div>
            </div>
        </a>
        <div class="user-card-actions">
            <span class="user-status-pill">
                <i class="bi bi-lightning-charge"></i><?php echo number_format((int)($user['xp'] ?? 0)); ?> XP
            </span>
            <?php if ($status === 'none'): ?>
            <form action="actions/send_friend_request.php" method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="friend_id" value="<?php echo (int)$user['id']; ?>">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" <?php echo !$canAddUser ? 'disabled title="Nie można zaprosić tego użytkownika"' : 'title="Zaproś do znajomych"'; ?>>
                    <i class="bi <?php echo !$canAddUser ? 'bi-person-x-fill' : 'bi-person-plus-fill'; ?> me-1"></i><?php echo !$canAddUser ? 'Niedostępne' : 'Zaproś'; ?>
                </button>
            </form>
            <?php elseif ($status === 'friends'): ?>
                <a href="user/profile.php?id=<?php echo (int)$user['id']; ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i>Znajomy
                </a>
            <?php elseif ($status === 'sent'): ?>
                <span class="user-status-pill is-waiting">
                    <i class="bi bi-clock-history"></i>Wysłane
                </span>
            <?php elseif ($status === 'pending'): ?>
                <form action="user/social.php" method="POST" class="d-flex gap-2 m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="friend_id" value="<?php echo (int)$user['id']; ?>">
                    <button type="submit" name="action" value="accept" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-check-lg me-1"></i>Akceptuj
                    </button>
                    <button type="submit" name="action" value="decline" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-x-lg me-1"></i>Odrzuć
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
    $items[] = ob_get_clean();
}

echo securityJsonEncode([
    'status' => 'success',
    'count' => count($users),
    'has_more' => count($users) >= $limit,
    'next_offset' => $offset + count($users),
    'html' => implode("\n", $items),
]);
