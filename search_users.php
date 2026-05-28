<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$query = trim($_GET['query'] ?? '');

$results = [];
$myRole = $_SESSION['role'] ?? 'user';

if (!empty($query)) {
    consumeRateLimit($pdo, 'user_search_page', (string)$userId . '|' . clientIpAddress(), 80, 300);
    if (roleHasAdminAccess($myRole)) {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE username LIKE ? AND id != ? ORDER BY last_activity DESC, xp DESC, username ASC LIMIT 6");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE username LIKE ? AND id != ? AND searchable = 1 AND profile_public = 1 ORDER BY last_activity DESC, xp DESC, username ASC LIMIT 6");
    }
    $stmt->execute(['%' . $query . '%', $userId]);
    $results = $stmt->fetchAll();
} else {
    if (roleHasAdminAccess($myRole)) {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE id != ? ORDER BY last_activity DESC, xp DESC, username ASC LIMIT 6");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, role, is_verified, xp, last_activity, allow_friend_requests, avatar_path FROM users WHERE id != ? AND searchable = 1 AND profile_public = 1 ORDER BY last_activity DESC, xp DESC, username ASC LIMIT 6");
    }
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szukaj użytkowników - Platforma Testowa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <style>
        .user-search-hero {
            border-radius: 28px;
            padding: clamp(1.25rem, 3vw, 2.35rem);
            background:
                radial-gradient(circle at 90% 10%, rgba(255,255,255,.22), transparent 26%),
                linear-gradient(135deg, var(--primary-color-dark), #0f172a);
            color: #fff;
            box-shadow: 0 18px 44px rgba(37, 99, 235, .18);
        }
        .user-search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
        }
        .user-result-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 100%;
            padding: 1rem;
            border-radius: 22px;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .18);
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .user-result-card:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, .35);
            box-shadow: 0 20px 44px rgba(37, 99, 235, .1);
        }
        .user-result-main {
            display: flex;
            align-items: center;
            gap: .9rem;
            min-width: 0;
            color: inherit;
            text-decoration: none;
        }
        .user-avatar-search {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-color-dark));
            color: #fff;
            font-weight: 900;
            font-size: 1.25rem;
            box-shadow: 0 10px 24px rgba(59, 130, 246, .22);
        }
        img.user-avatar-search {
            object-fit: cover;
            background: #e5e7eb;
        }
        .user-status-row,
        .user-card-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }
        .user-card-actions {
            justify-content: space-between;
            border-top: 1px solid rgba(148, 163, 184, .16);
            padding-top: .85rem;
        }
        .user-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .65rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
            background: rgba(148, 163, 184, .12);
            color: #475569;
        }
        .user-status-pill.is-online {
            background: rgba(34, 197, 94, .12);
            color: #15803d;
        }
        .user-status-pill.is-friend {
            background: rgba(34, 197, 94, .12);
            color: #15803d;
        }
        .user-status-pill.is-waiting {
            background: rgba(245, 158, 11, .14);
            color: #b45309;
        }
        body.dark-mode .user-result-card {
            background: rgba(15, 23, 42, .86);
            border-color: rgba(148, 163, 184, .22);
        }
        body.dark-mode .user-status-pill {
            color: #cbd5e1;
            background: rgba(148, 163, 184, .14);
        }
        @media (max-width: 575.98px) {
            .user-card-actions > .btn,
            .user-card-actions > form,
            .user-card-actions form .btn {
                width: 100%;
            }
            .user-card-actions form.d-flex {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <section class="user-search-hero mb-4 animate-in">
                        <span class="badge bg-white bg-opacity-25 rounded-pill mb-3">Społeczność</span>
                        <h2 class="fw-900 mb-2"><?php echo $query !== '' ? 'Wyniki wyszukiwania' : 'Lista użytkowników'; ?></h2>
                        <p class="opacity-75 mb-0">
                            <?php echo $query !== '' ? 'Dla frazy: "' . htmlspecialchars($query) . '"' : 'Przeglądaj aktywne konta, statusy i relacje znajomych.'; ?>
                        </p>
                    </section>

                    <div class="dashboard-panel animate-in">
                        <?php if ($results): ?>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                                <h5 class="fw-800 mb-0">Znalezione konta</h5>
                                <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo count($results); ?> osób</span>
                            </div>
                            <div class="user-search-grid">
                                <?php 
                                foreach ($results as $user): 
                                    $status = getFriendshipStatus($pdo, $userId, $user['id']);
                                    $roleBadge = getUserRoleBadge($user['role'] ?? 'user');
                                    $isOnline = isUserOnline($user['last_activity'] ?? null);
                                    $canAddUser = canSendFriendRequest($myRole, $user['role'] ?? 'user', isset($user['allow_friend_requests']) ? $user['allow_friend_requests'] == 1 : true);
                                    $avatarSrc = userAvatarSrc($user['avatar_path'] ?? '');
                                ?>
                                    <article class="user-result-card">
                                        <a href="profile.php?id=<?php echo (int)$user['id']; ?>" class="user-result-main">
                                            <?php if ($avatarSrc): ?>
                                                <img class="user-avatar-search" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="">
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
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="friend_id" value="<?php echo (int)$user['id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" <?php echo !$canAddUser ? 'disabled title="Nie można zaprosić tego użytkownika"' : 'title="Zaproś do znajomych"'; ?>>
                                                    <i class="bi <?php echo !$canAddUser ? 'bi-person-x-fill' : 'bi-person-plus-fill'; ?> me-1"></i><?php echo !$canAddUser ? 'Niedostępne' : 'Zaproś'; ?>
                                                </button>
                                            </form>
                                            <?php elseif ($status === 'friends'): ?>
                                                <a href="profile.php?id=<?php echo (int)$user['id']; ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
                                                    <i class="bi bi-check2-circle me-1"></i>Znajomy
                                                </a>
                                            <?php elseif ($status === 'sent'): ?>
                                                <span class="user-status-pill is-waiting">
                                                    <i class="bi bi-clock-history"></i>Wysłane
                                                </span>
                                            <?php elseif ($status === 'pending'): ?>
                                                <form action="social.php" method="POST" class="d-flex gap-2 m-0">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
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
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                                <p class="mt-3 text-muted">Nie znaleziono użytkowników pasujących do zapytania.</p>
                                <a href="profile.php" class="btn btn-primary">Powrót do profilu</a>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>
