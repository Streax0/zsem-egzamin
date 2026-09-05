<?php
// Include required files
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Start secure session and require login
startSecureSession();
requireLogin();

$myId = $_SESSION['user_id'];

// Handle actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setSessionMessage('error', 'Błąd bezpieczeństwa CSRF.');
    } else {
        $action = $_POST['action'] ?? '';
        $friendId = (int)($_POST['friend_id'] ?? 0);

        if ($friendId > 0 && $friendId != $myId) {
            switch ($action) {
                case 'send':
                    // Check privacy and roles before sending
                    $stmt = $pdo->prepare("SELECT role, allow_friend_requests FROM users WHERE id = ?");
                    $stmt->execute([$friendId]);
                    $target = $stmt->fetch();
                    
                    if ($target) {
                        $myRole = $_SESSION['role'] ?? 'user';
                        if (!canSendMoreFriendRequests($pdo, (int)$myId)) {
                            setSessionMessage('error', 'Masz już 4 oczekujące wysłane zaproszenia. Anuluj jedno albo poczekaj na akceptację.');
                        } elseif (canSendFriendRequest($myRole, $target['role'], $target['allow_friend_requests'])) {
                            if (sendFriendRequest($pdo, $myId, $friendId)) {
                                setSessionMessage('success', 'Zaproszenie zostało wysłane.');
                            } else {
                                setSessionMessage('error', 'Nie udało się wysłać zaproszenia.');
                            }
                        } else {
                            setSessionMessage('error', 'Nie masz uprawnień do zaproszenia tego użytkownika.');
                        }
                    }
                    break;
                case 'accept':
                    if (acceptFriendRequest($pdo, $myId, $friendId)) {
                        setSessionMessage('success', 'Zaproszenie zaakceptowane!');
                    } else {
                        setSessionMessage('error', 'Nie udało się zaakceptować zaproszenia.');
                    }
                    break;
                case 'remove':
                case 'cancel':
                case 'decline':
                    if (removeFriendship($pdo, $myId, $friendId)) {
                        $msg = ($action === 'remove') ? 'Znajomy został usunięty.' : 
                              (($action === 'cancel') ? 'Zaproszenie zostało anulowane.' : 'Zaproszenie zostało odrzucone.');
                        setSessionMessage('info', $msg);
                    } else {
                        setSessionMessage('error', 'Błąd podczas wykonywania akcji.');
                    }
                    break;
            }
        }
    }
    header('Location: social.php' . (!empty($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit;
}

// Fetch social data
$friends = getUserFriends($pdo, $myId);
$friendsLimit = max(6, min(48, (int)($_GET['friends_limit'] ?? 6)));
$initialFriendsVisible = $friendsLimit;
$pendingRequests = getPendingFriendRequests($pdo, $myId);
$sentRequestLimit = friendRequestLimit();

// Fetch requests SENT by me
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.xp, u.avatar_path, f.created_at
    FROM users u
    JOIN friends f ON u.id = f.friend_id
    WHERE f.user_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->execute([$myId]);
$sentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sentRequestCount = count($sentRequests);
$canSendMoreRequests = $sentRequestCount < $sentRequestLimit;

// Search for users if query exists
$searchQuery = trim($_GET['search'] ?? '');
$showUserDirectory = array_key_exists('search', $_GET) || isset($_GET['browse']);
$searchResults = [];
$myRole = $_SESSION['role'] ?? 'user';

if ($showUserDirectory) {
    if (roleHasAdminAccess($myRole)) {
        $sql = "SELECT id, username, xp, role, is_verified, allow_friend_requests, avatar_path FROM users WHERE id != ?";
        $params = [$myId];
    } else {
        $sql = "SELECT id, username, xp, role, is_verified, allow_friend_requests, avatar_path FROM users WHERE id != ? AND searchable = 1";
        $params = [$myId];
    }
    if ($searchQuery !== '') {
        $sql .= " AND username LIKE ?";
        $params[] = '%' . addcslashes($searchQuery, '%_\\') . '%';
    }
    $sql .= " ORDER BY last_activity DESC, xp DESC, username ASC LIMIT 6";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get suggested users (random non-friends and non-pending)
$stmt = $pdo->prepare("
    SELECT id, username, xp, role, is_verified, allow_friend_requests, avatar_path FROM users 
    WHERE id != ? 
    AND searchable = 1
    AND role NOT IN ('admin', 'teacher', 'dyrektor')
    AND id NOT IN (SELECT friend_id FROM friends WHERE user_id = ?)
    AND id NOT IN (SELECT user_id FROM friends WHERE friend_id = ?)
    ORDER BY RAND() LIMIT 5
");
$stmt->execute([$myId, $myId, $myId]);
$suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.xp, u.role, u.is_verified, u.avatar_path, u.last_activity, u.show_online_status, f.status
    FROM users u
    JOIN friends f ON (u.id = f.user_id OR u.id = f.friend_id)
    WHERE (f.user_id = ? OR f.friend_id = ?)
      AND u.id != ?
      AND f.status = 'accepted'
    ORDER BY u.last_activity DESC, u.xp DESC
    LIMIT 3
");
$stmt->execute([$myId, $myId, $myId]);
$recentFriendActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Społeczność – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
    :root {
        --card-radius: 1.25rem;
        --avatar-radius: 1rem;
    }

    .social-hero {
        border-radius: 28px;
        padding: clamp(1.25rem, 3vw, 2.4rem);
        background: radial-gradient(circle at 88% 14%, rgba(255,255,255,.24), transparent 28%),
                    linear-gradient(135deg, var(--primary-color-dark, #1e40af), #0f172a);
        color: #fff;
        box-shadow: 0 18px 44px rgba(37, 99, 235, .18);
        border: 1px solid rgba(255, 255, 255, 0.12);
    }
    body.dark-mode .social-hero {
        background: radial-gradient(circle at 12% 20%, rgba(96, 165, 250, .18), transparent 30%),
                    linear-gradient(135deg, #1e293b, #0f172a) !important;
        border-color: rgba(255, 255, 255, 0.08);
    }

    .social-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0,1fr));
        gap: .75rem;
    }
    .social-stat {
        border-radius: 18px;
        padding: 1rem;
        background: rgba(255,255,255,.14);
        border: 1px solid rgba(255,255,255,.18);
        text-align: center;
        backdrop-filter: blur(8px);
    }

    .search-bar-container {
        position: relative;
        max-width: 520px;
        display: flex;
        gap: .6rem;
    }
    .search-bar-container .search-leading-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted, #94a3b8);
        pointer-events: none;
    }
    .search-input-modern {
        padding-left: 3rem !important;
        padding-right: 1.25rem !important;
        height: 50px;
        border-radius: 1rem !important;
        border: 1px solid var(--border-color, #e2e8f0);
        background: #ffffff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: all 0.2s;
    }
    body.dark-mode .search-input-modern {
        background: #1e293b;
        color: #f8fafc;
        border-color: rgba(255, 255, 255, 0.12);
    }
    .search-input-modern:focus {
        border-color: var(--primary-color, #3b82f6);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    }
    .search-submit-btn {
        width: 50px;
        height: 50px;
        border-radius: 1rem;
        display: inline-grid;
        place-items: center;
        flex: 0 0 auto;
    }
    body.dark-mode .search-submit-btn {
        background: #1e293b !important;
        color: #e5e7eb !important;
        border-color: rgba(148, 163, 184, .35) !important;
    }

    .social-card {
        border: 1px solid var(--border-color, #e2e8f0);
        background: var(--panel-bg, #ffffff);
        border-radius: var(--card-radius);
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .social-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        border-color: rgba(59, 130, 246, 0.35);
    }
    body.dark-mode .social-card,
    body.dark-mode .dashboard-panel {
        background: #1e293b;
        border-color: rgba(255, 255, 255, 0.08);
        color: #f8fafc;
    }

    .user-avatar-social {
        width: 50px;
        height: 50px;
        border-radius: var(--avatar-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
        background: linear-gradient(135deg, var(--primary-color, #3b82f6) 0%, var(--primary-color-dark, #1d4ed8) 100%);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
    }
    .user-avatar-social.is-image {
        aspect-ratio: 1 / 1;
        object-fit: cover;
        padding: 0;
        background: #e5e7eb;
    }

    .avatar-a { background: linear-gradient(135deg, #f87171, #dc2626); }
    .avatar-b { background: linear-gradient(135deg, #fb923c, #ea580c); }
    .avatar-c { background: linear-gradient(135deg, #fbbf24, #d97706); }
    .avatar-d { background: linear-gradient(135deg, #a3e635, #65a30d); }
    .avatar-e { background: linear-gradient(135deg, #4ade80, #16a34a); }
    .avatar-f { background: linear-gradient(135deg, #2dd4bf, #0d9488); }
    .avatar-g { background: linear-gradient(135deg, #38bdf8, #0284c7); }
    .avatar-h { background: linear-gradient(135deg, #60a5fa, #2563eb); }
    .avatar-i { background: linear-gradient(135deg, #818cf8, #4f46e5); }
    .avatar-j { background: linear-gradient(135deg, #a78bfa, #7c3aed); }
    .avatar-k { background: linear-gradient(135deg, #c084fc, #9333ea); }
    .avatar-l { background: linear-gradient(135deg, #e879f9, #c026d3); }
    .avatar-m { background: linear-gradient(135deg, #f472b6, #db2777); }
    .avatar-n { background: linear-gradient(135deg, #fb7185, #e11d48); }

    .status-badge {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.25rem 0.6rem;
        border-radius: 2rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .status-online { background: #dcfce7; color: #166534; }
    .status-offline { background: #f1f5f9; color: #475569; }
    body.dark-mode .status-online { background: rgba(34, 197, 94, 0.2); color: #86efac; }
    body.dark-mode .status-offline { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }

    .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
    .dot-online { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
    .dot-offline { background: #94a3b8; }

    .xp-pill {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-color, #2563eb);
        padding: 0.25rem 0.65rem;
        border-radius: 0.75rem;
        font-weight: 800;
        font-size: 0.76rem;
    }
    body.dark-mode .xp-pill {
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
    }

    .btn-duel-modern {
        background: linear-gradient(135deg, var(--primary-color, #3b82f6) 0%, var(--primary-color-dark, #1d4ed8) 100%);
        color: white;
        border: none;
        font-weight: 700;
        padding: 0.45rem 1.15rem;
        border-radius: 0.75rem;
        font-size: 0.82rem;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .btn-duel-modern:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
        color: white;
    }

    .action-btn {
        width: 38px;
        height: 38px;
        border-radius: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
        border: none;
        background: linear-gradient(135deg, var(--primary-color, #3b82f6), var(--primary-color-dark, #1d4ed8));
        color: white;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    .action-btn:hover {
        transform: scale(1.08);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.2);
    }
    .action-btn.btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .action-btn.btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }

    .request-badge {
        width: 24px;
        height: 24px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .pending-request-row {
        position: relative;
        background: #ffffff;
        border: 1px solid rgba(59, 130, 246, 0.18);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        border-radius: 1rem;
        transition: transform 0.2s ease;
    }
    body.dark-mode .pending-request-row {
        background: #1e293b;
        border-color: rgba(59, 130, 246, 0.3);
    }
    .pending-request-row:hover {
        transform: translateY(-2px);
    }

    .sent-request-row {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    body.dark-mode .sent-request-row {
        background: #1e293b;
        border-color: rgba(255, 255, 255, 0.08);
    }

    .social-request-limit {
        border: 1px solid rgba(59, 130, 246, .16);
        border-radius: .9rem;
        background: rgba(59, 130, 246, .07);
        color: #1e40af;
        padding: .65rem .85rem;
        font-size: .8rem;
        font-weight: 700;
    }
    body.dark-mode .social-request-limit {
        background: rgba(96, 165, 250, .12);
        border-color: rgba(96, 165, 250, .22);
        color: #bfdbfe;
    }

    .empty-state {
        padding: 2.5rem 1.5rem;
        text-align: center;
        background: rgba(148, 163, 184, 0.04);
        border-radius: 1.5rem;
        border: 2px dashed rgba(148, 163, 184, 0.2);
    }

    /* Insights and Suggestions Grid */
    .social-insights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.25rem;
    }
    .suggested-user-row {
        padding: .85rem;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        transition: transform 0.18s ease;
    }
    body.dark-mode .suggested-user-row {
        background: #1e293b;
        border-color: rgba(255, 255, 255, 0.08);
    }
    .suggested-user-row:hover {
        transform: translateY(-2px);
    }

    .social-more-btn {
        border-radius: 999px;
        padding: .7rem 1.5rem;
        font-weight: 800;
    }
</style>
HTML;
include '../includes/header.php';
?>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid">
                    
                    <!-- Hero Banner -->
                    <section class="social-hero mb-4">
                        <div class="row align-items-center g-4">
                            <div class="col-lg-7">
                                <span class="badge bg-white bg-opacity-25 rounded-pill mb-3 px-3 py-2 fw-bold">
                                    <i class="bi bi-people-fill me-1"></i>Społeczność ZSEM Tech
                                </span>
                                <h2 class="fw-900 mb-2 text-white">Znajomi, wyzwania i wspólna nauka</h2>
                                <p class="mb-0 text-white-50">Szukaj osób z technikum, zapraszaj znajomych, tocz pojedynki 1v1 i śledź postępy edukacyjne.</p>
                            </div>
                            <div class="col-lg-5">
                                <div class="social-stat-grid mb-3">
                                    <div class="social-stat">
                                        <div class="h4 fw-900 mb-0"><?php echo count($friends); ?></div>
                                        <div class="small opacity-75">znajomych</div>
                                    </div>
                                    <div class="social-stat">
                                        <div class="h4 fw-900 mb-0"><?php echo count($pendingRequests); ?></div>
                                        <div class="small opacity-75">wniosków</div>
                                    </div>
                                    <div class="social-stat">
                                        <div class="h4 fw-900 mb-0"><?php echo count($suggestions); ?></div>
                                        <div class="small opacity-75">propozycji</div>
                                    </div>
                                </div>
                                <form action="social.php" method="GET" id="socialSearchForm">
                                    <input type="hidden" name="browse" value="1">
                                    <div class="search-bar-container">
                                        <i class="bi bi-search search-leading-icon"></i>
                                        <input type="text" name="search" id="socialLiveSearch" class="form-control search-input-modern" 
                                               placeholder="Znajdź kogoś po loginie..." 
                                               value="<?php echo htmlspecialchars($searchQuery); ?>">
                                        <button type="submit" class="btn btn-light search-submit-btn" aria-label="Szukaj użytkowników">
                                            <i class="bi bi-search"></i>
                                        </button>
                                        <?php if (!empty($searchQuery)): ?>
                                            <a href="social.php" class="position-absolute top-50 translate-middle-y text-muted" style="right: 4.3rem;" aria-label="Wyczyść wyszukiwanie">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </section>

                    <?php
                    $flashMsg = getSessionMessage();
                    if ($flashMsg): ?>
                        <div class="alert alert-<?php echo ($flashMsg['type'] === 'error') ? 'danger' : ($flashMsg['type'] === 'success' ? 'success' : 'info'); ?> border-0 shadow-sm alert-dismissible fade show mb-4 rounded-4" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi <?php echo ($flashMsg['type'] === 'error') ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'; ?> me-2"></i>
                                <?php echo htmlspecialchars($flashMsg['message']); ?>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div id="socialLiveResults" class="mb-4" hidden></div>

                    <!-- Master Two-Column Grid -->
                    <div class="row g-4">
                        
                        <!-- Left Main Column (Search Results, Friends Grid, Insights) -->
                        <div class="col-xl-8 col-lg-7">
                            
                            <?php if ($showUserDirectory): ?>
                                <!-- Search Results Section -->
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <h4 class="fw-800 mb-0"><?php echo $searchQuery !== '' ? 'Wyniki dla "' . htmlspecialchars($searchQuery) . '"' : 'Katalog użytkowników'; ?></h4>
                                        <span class="badge bg-primary rounded-pill"><?php echo count($searchResults); ?> osób</span>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <?php if (empty($searchResults)): ?>
                                            <div class="col-12">
                                                <div class="empty-state">
                                                    <div class="display-3 text-muted mb-2"><i class="bi bi-person-x"></i></div>
                                                    <h5 class="fw-bold">Nic nie znaleźliśmy</h5>
                                                    <p class="text-muted small mb-0">Spróbuj wpisać inny login lub sprawdź pisownię.</p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($searchResults as $user): 
                                                $status = getFriendshipStatus($pdo, $myId, $user['id']);
                                                $avatarClass = 'avatar-' . strtolower(substr($user['username'], 0, 1));
                                                $avatarSrc = userAvatarSrc($user['avatar_path'] ?? '');
                                            ?>
                                                <div class="col-md-6">
                                                    <div class="social-card p-3 social-click-card h-100" data-profile-url="profile.php?id=<?php echo (int)$user['id']; ?>" role="link" tabindex="0">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <?php if ($avatarSrc): ?>
                                                                <img class="user-avatar-social is-image" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" loading="lazy" decoding="async">
                                                            <?php else: ?>
                                                                <div class="user-avatar-social <?php echo $avatarClass; ?>">
                                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php $searchRoleBadge = getUserRoleBadge($user['role'] ?? 'user'); ?>
                                                            <div class="flex-grow-1 min-w-0">
                                                                <h6 class="mb-1 fw-bold text-truncate"><?php echo htmlspecialchars($user['username']); ?><?php echo getUserBadgeHtml($user['role'] ?? 'user', (int)($user['is_verified'] ?? 0)); ?></h6>
                                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                                    <span class="xp-pill d-inline-block"><?php echo number_format($user['xp']); ?> XP</span>
                                                                    <span class="badge rounded-pill <?php echo $searchRoleBadge['class']; ?> small"><?php echo htmlspecialchars($searchRoleBadge['label']); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <?php if ($status === 'none'): ?>
                                                                    <?php 
                                                                        $canAdd = $canSendMoreRequests && canSendFriendRequest($_SESSION['role'], $user['role'], $user['allow_friend_requests'] ?? 1);
                                                                    ?>
                                                                    <form action="social.php" method="POST">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                        <input type="hidden" name="action" value="send">
                                                                        <input type="hidden" name="friend_id" value="<?php echo $user['id']; ?>">
                                                                        <button type="submit" class="action-btn" <?php echo !$canAdd ? 'disabled aria-label="Niedostępne"' : 'aria-label="Zaproś"'; ?> title="<?php echo !$canAdd ? 'Niedostępne' : 'Zaproś'; ?>">
                                                                            <i class="bi <?php echo !$canAdd ? 'bi-person-x-fill' : 'bi-person-plus-fill'; ?>"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php elseif ($status === 'sent'): ?>
                                                                    <form action="social.php" method="POST">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                        <input type="hidden" name="action" value="cancel">
                                                                        <input type="hidden" name="friend_id" value="<?php echo $user['id']; ?>">
                                                                        <button type="submit" class="action-btn btn-danger" title="Anuluj zaproszenie">
                                                                            <i class="bi bi-x-lg"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php elseif ($status === 'pending'): ?>
                                                                    <form action="social.php" method="POST" class="d-flex gap-1">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                        <input type="hidden" name="friend_id" value="<?php echo $user['id']; ?>">
                                                                        <button type="submit" name="action" value="accept" class="action-btn btn-success" title="Akceptuj">
                                                                            <i class="bi bi-check-lg"></i>
                                                                        </button>
                                                                        <button type="submit" name="action" value="decline" class="action-btn btn-danger" title="Odrzuć">
                                                                            <i class="bi bi-trash3"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="action-btn" title="Profil">
                                                                        <i class="bi bi-person-fill"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Friends Grid Section -->
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h4 class="fw-800 mb-0"><i class="bi bi-person-check-fill text-primary me-2"></i>Twoi znajomi</h4>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold"><?php echo count($friends); ?> w sieci</span>
                                </div>

                                <?php if (empty($friends)): ?>
                                    <div class="empty-state">
                                        <div class="display-3 text-muted mb-2"><i class="bi bi-people"></i></div>
                                        <h5 class="fw-bold">Brak znajomych</h5>
                                        <p class="text-muted small mb-0">Twoja lista znajomych jest pusta. Użyj paska wyszukiwania, by znaleźć kolegów z klasy!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3" id="friendsGrid">
                                        <?php foreach (array_slice($friends, 0, $initialFriendsVisible) as $friendIndex => $friend):
                                            $showOnlineStatusFriend = (bool)($friend['show_online_status'] ?? 1);
                                            $isOnline = $showOnlineStatusFriend ? isUserOnline($friend['last_activity']) : false;
                                            $avatarClass = 'avatar-' . strtolower(substr($friend['username'], 0, 1));
                                            $avatarSrc = userAvatarSrc($friend['avatar_path'] ?? '');
                                            $friendRoleBadge = getUserRoleBadge($friend['role'] ?? 'user');
                                        ?>
                                            <div class="col-md-6 social-friend-item">
                                                <div class="social-card p-3 h-100 d-flex flex-column justify-content-between">
                                                    <div class="d-flex align-items-center gap-3 mb-3">
                                                        <?php if ($avatarSrc): ?>
                                                            <img class="user-avatar-social is-image" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" loading="lazy" decoding="async">
                                                        <?php else: ?>
                                                            <div class="user-avatar-social <?php echo $avatarClass; ?>">
                                                                <?php echo strtoupper(substr($friend['username'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="flex-grow-1 min-w-0">
                                                            <h6 class="mb-1 fw-bold text-truncate">
                                                                <a href="profile.php?id=<?php echo $friend['id']; ?>" class="text-reset text-decoration-none">
                                                                    <?php echo htmlspecialchars($friend['username']); ?><?php echo getUserBadgeHtml($friend['role'] ?? 'user', (int)($friend['is_verified'] ?? 0)); ?>
                                                                </a>
                                                            </h6>
                                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                                <span class="status-badge <?php echo $isOnline ? 'status-online' : 'status-offline'; ?>">
                                                                    <span class="dot <?php echo $isOnline ? 'dot-online' : 'dot-offline'; ?>"></span>
                                                                    <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                                                                </span>
                                                                <span class="badge rounded-pill <?php echo $friendRoleBadge['class']; ?> small"><?php echo htmlspecialchars($friendRoleBadge['label']); ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="dropdown">
                                                            <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown" aria-label="Więcej opcji">
                                                                <i class="bi bi-three-dots-vertical fs-5"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                                                <li><a class="dropdown-item py-2" href="profile.php?id=<?php echo $friend['id']; ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <form action="social.php" method="POST" onsubmit="return appConfirmSubmit(this, 'Czy na pewno chcesz usunąć tego znajomego?')">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                        <input type="hidden" name="action" value="remove">
                                                                        <input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
                                                                        <button type="submit" class="dropdown-item py-2 text-danger">
                                                                            <i class="bi bi-person-x me-2"></i>Usuń znajomego
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-auto">
                                                        <div class="xp-pill"><?php echo number_format($friend['xp']); ?> XP</div>
                                                        <a href="duels/challenge.php?opponent=<?php echo $friend['id']; ?>" class="btn-duel-modern text-decoration-none">
                                                            <i class="bi bi-lightning-charge-fill"></i>Wyzwanie
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($friends) > $initialFriendsVisible): ?>
                                    <div class="text-center mt-4">
                                        <a class="btn btn-outline-primary social-more-btn" id="showMoreFriends" href="social.php?friends_limit=<?php echo min(count($friends), $initialFriendsVisible + 6); ?>">
                                            <i class="bi bi-chevron-down me-1"></i>Pokaż więcej znajomych
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Social Insights Grid (Activity + Suggestions) -->
                            <div class="social-insights-main mb-4">
                                <div class="social-insights-grid">
                                    
                                    <!-- Recent Friend Activity -->
                                    <div class="dashboard-panel social-activity-card">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h5 class="fw-800 mb-0"><i class="bi bi-activity text-success me-2"></i>Ostatnia aktywność</h5>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Na żywo</span>
                                        </div>
                                        <?php if (empty($recentFriendActivity)): ?>
                                            <div class="empty-state p-3">
                                                <p class="text-muted small mb-0">Brak nowej aktywności znajomych.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="vstack gap-2">
                                                <?php foreach ($recentFriendActivity as $activity): 
                                                    $showOnlineStatusFriend = (bool)($activity['show_online_status'] ?? 1);
                                                    $avatarClass = 'avatar-' . strtolower(substr($activity['username'], 0, 1));
                                                    $avatarSrc = userAvatarSrc($activity['avatar_path'] ?? '');
                                                    $lastActivity = !empty($activity['last_activity']) ? strtotime($activity['last_activity']) : null;
                                                    $isOnline = $showOnlineStatusFriend ? isUserOnline($activity['last_activity'] ?? null) : false;
                                                ?>
                                                    <div class="d-flex align-items-center gap-3 p-2 rounded-3 border bg-body-tertiary">
                                                        <?php if ($avatarSrc): ?>
                                                            <img class="user-avatar-social is-image" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" style="width: 38px; height: 38px; font-size: 0.9rem;" loading="lazy" decoding="async">
                                                        <?php else: ?>
                                                            <div class="user-avatar-social <?php echo $avatarClass; ?>" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                                <?php echo strtoupper(substr($activity['username'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="flex-grow-1 min-w-0">
                                                            <div class="fw-bold small text-truncate">
                                                                <a class="text-reset text-decoration-none" href="profile.php?id=<?php echo (int)$activity['id']; ?>"><?php echo htmlspecialchars($activity['username']); ?></a>
                                                            </div>
                                                            <div class="text-muted smaller" style="font-size: .72rem;">
                                                                <?php echo $isOnline ? '<span class="text-success fw-bold">Online</span>' : ($lastActivity ? date('d.m H:i', $lastActivity) : '—'); ?>
                                                            </div>
                                                        </div>
                                                        <span class="xp-pill"><?php echo number_format((int)$activity['xp']); ?> XP</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Suggested Users Card -->
                                    <div class="dashboard-panel suggested-users-card">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h5 class="fw-800 mb-0"><i class="bi bi-person-plus text-primary me-2"></i>Możesz ich znać</h5>
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2">Sugestie</span>
                                        </div>
                                        <?php if (empty($suggestions)): ?>
                                            <div class="empty-state p-3">
                                                <p class="text-muted small mb-0">Brak nowych propozycji znajomych.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="vstack gap-2">
                                                <?php foreach ($suggestions as $s): 
                                                    $avatarClass = 'avatar-' . strtolower(substr($s['username'], 0, 1));
                                                    $avatarSrc = userAvatarSrc($s['avatar_path'] ?? '');
                                                    $suggStatus = getFriendshipStatus($pdo, $myId, $s['id']);
                                                    $suggestRoleBadge = getUserRoleBadge($s['role'] ?? 'user');
                                                ?>
                                                    <div class="suggested-user-row d-flex align-items-center gap-2 p-2">
                                                        <?php if ($avatarSrc): ?>
                                                            <img class="user-avatar-social is-image" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" style="width: 36px; height: 36px; font-size: 0.85rem;" loading="lazy" decoding="async">
                                                        <?php else: ?>
                                                            <div class="user-avatar-social <?php echo $avatarClass; ?>" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                                <?php echo strtoupper(substr($s['username'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="flex-grow-1 min-w-0">
                                                            <div class="fw-bold small text-truncate">
                                                                <a href="profile.php?id=<?php echo $s['id']; ?>" class="text-reset text-decoration-none">
                                                                    <?php echo htmlspecialchars($s['username']); ?>
                                                                </a>
                                                            </div>
                                                            <div class="text-muted smaller" style="font-size: 0.7rem;"><?php echo number_format($s['xp']); ?> XP</div>
                                                        </div>
                                                        <?php if ($suggStatus === 'none' && canSendFriendRequest($_SESSION['role'] ?? 'user', $s['role'], $s['allow_friend_requests'] ?? 1)): ?>
                                                            <form action="social.php" method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                <input type="hidden" name="action" value="send">
                                                                <input type="hidden" name="friend_id" value="<?php echo $s['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" style="font-size: 0.72rem;" <?php echo $canSendMoreRequests ? '' : 'disabled title="Limit zaproszeń osiągnięty"'; ?>>Dodaj</button>
                                                            </form>
                                                        <?php elseif ($suggStatus === 'sent'): ?>
                                                            <form action="social.php" method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                <input type="hidden" name="action" value="cancel">
                                                                <input type="hidden" name="friend_id" value="<?php echo $s['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" style="font-size: 0.72rem;">Anuluj</button>
                                                            </form>
                                                        <?php elseif ($suggStatus === 'friends'): ?>
                                                            <span class="badge bg-success rounded-pill" style="font-size: 0.65rem;">Znajomy</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>

                        </div>

                        <!-- Right Column: Invites & Requests Sidebar -->
                        <!-- Right: Invites -->
                        <div class="col-xl-4 col-lg-5 social-sidebar">
                            
                            <!-- Received Invitations -->
                            <div class="dashboard-panel mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="fw-800 mb-0">
                                        <i class="bi bi-inbox-fill text-primary me-2"></i>Otrzymane zaproszenia
                                    </h5>
                                    <?php if (count($pendingRequests) > 0): ?>
                                        <span class="request-badge"><?php echo count($pendingRequests); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (empty($pendingRequests)): ?>
                                    <div class="empty-state p-3">
                                        <i class="bi bi-envelope-open text-muted fs-3 d-block mb-1"></i>
                                        <p class="text-muted small mb-0">Brak nowych zaproszeń do znajomych.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="vstack gap-2">
                                        <?php foreach ($pendingRequests as $req): 
                                            $avatarClass = 'avatar-' . strtolower(substr($req['username'], 0, 1));
                                            $avatarSrc = userAvatarSrc($req['avatar_path'] ?? '');
                                        ?>
                                            <div class="pending-request-row d-flex align-items-center gap-3 p-3">
                                                <a href="profile.php?id=<?php echo (int)$req['id']; ?>" class="text-decoration-none flex-shrink-0" aria-label="Profil <?php echo htmlspecialchars($req['username']); ?>">
                                                    <?php if ($avatarSrc): ?>
                                                        <img class="user-avatar-social is-image" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" style="width: 40px; height: 40px; font-size: 0.95rem;" loading="lazy" decoding="async">
                                                    <?php else: ?>
                                                        <div class="user-avatar-social <?php echo $avatarClass; ?>" style="width: 40px; height: 40px; font-size: 0.95rem;">
                                                            <?php echo strtoupper(substr($req['username'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                                <div class="flex-grow-1 min-w-0">
                                                    <a href="profile.php?id=<?php echo (int)$req['id']; ?>" class="fw-bold small text-truncate d-block text-reset text-decoration-none"><?php echo htmlspecialchars($req['username']); ?></a>
                                                    <div class="text-muted smaller" style="font-size: 0.72rem;"><?php echo number_format($req['xp']); ?> XP</div>
                                                </div>
                                                <form action="social.php" method="POST" class="d-flex gap-1 flex-shrink-0">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="friend_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" name="action" value="accept" class="action-btn btn-success" title="Akceptuj">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="submit" name="action" value="decline" class="action-btn btn-danger" title="Odrzuć">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Sent Invitations -->
                            <?php if (!empty($sentRequests)): ?>
                                <div class="dashboard-panel mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h5 class="fw-800 mb-0"><i class="bi bi-send-fill text-muted me-2"></i>Wysłane zaproszenia</h5>
                                    </div>
                                    <div class="social-request-limit mb-3">
                                        <i class="bi bi-info-circle me-1"></i><?php echo (int)$sentRequestCount; ?> / <?php echo (int)$sentRequestLimit; ?> aktywne zaproszenia.
                                    </div>
                                    <div class="vstack gap-2">
                                        <?php foreach ($sentRequests as $req): 
                                            $avatarClass = 'avatar-' . strtolower(substr($req['username'], 0, 1));
                                            $avatarSrc = userAvatarSrc($req['avatar_path'] ?? '');
                                        ?>
                                            <div class="sent-request-row d-flex align-items-center gap-3 p-3">
                                                <?php if ($avatarSrc): ?>
                                                    <img class="user-avatar-social is-image" src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="" style="width: 38px; height: 38px; font-size: 0.9rem;" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                    <div class="user-avatar-social <?php echo $avatarClass; ?>" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                                        <?php echo strtoupper(substr($req['username'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="fw-bold small text-truncate"><?php echo htmlspecialchars($req['username']); ?></div>
                                                    <div class="text-muted smaller" style="font-size: 0.72rem;">Oczekuje na akceptację</div>
                                                </div>
                                                <form action="social.php" method="POST" class="flex-shrink-0">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="friend_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" class="action-btn btn-danger" title="Anuluj zaproszenie">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Fast Action & Duel Info Card -->
                            <div class="dashboard-panel mb-4">
                                <h6 class="fw-800 mb-2"><i class="bi bi-shield-shaded text-primary me-2"></i>Pojedynki 1v1</h6>
                                <p class="small text-muted mb-3">Rzuć wyzwanie znajomemu z listy! Oboje otrzymacie ten sam zestaw pytań egzaminacyjnych z kwalifikacji INF.02 lub INF.03.</p>
                                <a href="duels/history.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold w-100">
                                    <i class="bi bi-clock-history me-1"></i>Historia Twoich Pojedynków
                                </a>
                            </div>

                        </div>

                    </div>

                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    (() => {
        document.querySelectorAll('.social-click-card').forEach(card => {
            const openProfile = event => {
                if (event.target.closest('a, button, form, input, select, textarea')) return;
                window.location.href = card.dataset.profileUrl;
            };
            card.addEventListener('click', openProfile);
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openProfile(event);
                }
            });
        });

        const input = document.getElementById('socialLiveSearch');
        const box = document.getElementById('socialLiveResults');
        if (!input || !box) return;
        let timer = null;
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
        const avatarHtml = (row) => row.avatar
            ? `<img class="user-avatar-social is-image" src="${esc(row.avatar)}" alt="" loading="lazy" decoding="async">`
            : `<div class="user-avatar-social">${esc(row.username).slice(0,1).toUpperCase()}</div>`;
        const render = (rows) => {
            if (!rows.length) {
                box.hidden = true;
                box.innerHTML = '';
                return;
            }
            box.hidden = false;
            box.innerHTML = `
                <div class="dashboard-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-800 mb-0">Szybkie wyniki</h5>
                        <span class="badge bg-primary rounded-pill">${rows.length}/6</span>
                    </div>
                    <div class="row g-3">
                        ${rows.map(row => `
                            <div class="col-md-6 col-xl-4">
                                <a class="social-card p-3 d-flex align-items-center gap-3 text-decoration-none text-reset" href="profile.php?id=${row.id}">
                                    ${avatarHtml(row)}
                                    <div class="min-w-0">
                                        <div class="fw-bold text-truncate">${esc(row.username)} ${row.verified ? '<i class="bi bi-patch-check-fill text-primary"></i>' : ''}</div>
                                        <div class="small text-muted">${Number(row.xp).toLocaleString('pl-PL')} XP • ${row.online ? 'Online' : 'Offline'} • ${esc(row.status)}</div>
                                    </div>
                                </a>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
        };
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (!q) return render([]);
            timer = setTimeout(async () => {
                try {
                    const res = await fetch(`ajax/search_users_live.php?q=${encodeURIComponent(q)}`, {headers: {'Accept': 'application/json'}});
                    const data = await res.json();
                    render(data.ok ? data.results : []);
                } catch (_) {
                    render([]);
                }
            }, 180);
        });
    })();
    </script>
</body>
</html>
