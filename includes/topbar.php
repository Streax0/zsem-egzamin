<?php
if (isset($pdo) && isset($_SESSION['user_id']) && function_exists('updateUserActivity')) {
    updateUserActivity($pdo, $_SESSION['user_id']);
}
$current_page = basename($_SERVER['PHP_SELF'], ".php");
// Determine base path by looking for a root-level file
$base_url = file_exists('config/db.php') ? '' : '../';
$isGuestTopbar = function_exists('isGuestMode') && isGuestMode();
?>
<header class="top-header" role="banner">
    <button type="button" class="topbar-icon me-auto d-md-none" id="sidebarToggle" aria-label="Otwórz menu boczne">
        <i class="bi bi-list fs-3" aria-hidden="true"></i>
    </button>
    
    <?php
    $unreadCount = 0;
    $notifications = [];
    $topbarUser = [
        'role' => $isGuestTopbar ? 'guest' : ($_SESSION['role'] ?? 'user'),
        'username' => $isGuestTopbar ? 'Gosc' : ($_SESSION['username'] ?? ''),
        'first_name' => '',
        'last_name' => '',
        'is_verified' => 0,
        'xp' => 0,
        'avatar_path' => '',
    ];
    if (!$isGuestTopbar && isset($pdo) && isset($_SESSION['user_id']) && function_exists('getUnreadNotificationsCount') && function_exists('getNotifications')) {
        $unreadCount = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
        $notifications = getNotifications($pdo, $_SESSION['user_id'], 5);
        try {
            $stmtTopbarUser = $pdo->prepare("SELECT username, first_name, last_name, role, is_verified, xp, avatar_path FROM users WHERE id = ? LIMIT 1");
            $stmtTopbarUser->execute([$_SESSION['user_id']]);
            $rowTopbarUser = $stmtTopbarUser->fetch(PDO::FETCH_ASSOC);
            if ($rowTopbarUser) {
                $topbarUser = array_merge($topbarUser, $rowTopbarUser);
            }
        } catch (Exception $e) {
            // Keep session fallback.
        }
    }
    $decisionNotification = null;
    foreach ($notifications as $candidateNotification) {
        if (empty($candidateNotification['is_read']) && in_array($candidateNotification['type'] ?? '', ['teacher_application_approved', 'teacher_application_rejected'], true)) {
            $decisionNotification = $candidateNotification;
            break;
        }
    }
    ?>
    <a href="https://zsem.edu.pl" target="_blank" rel="noopener noreferrer" class="topbar-icon me-2" title="Strona szkoły" aria-label="Strona szkoły">
        <i class="bi bi-mortarboard"></i>
    </a>
    <div class="dropdown me-2">
        <button type="button" class="topbar-icon" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Pokaż powiadomienia">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <?php if ($unreadCount > 0): ?>
                <span class="notification-badge">
                    <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                </span>
            <?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-0 notification-dropdown">
            <div class="notification-dropdown-header p-3 border-bottom d-flex justify-content-between align-items-center rounded-top-3">
                <h6 class="mb-0 fw-bold">Powiadomienia</h6>
                <?php if ($unreadCount > 0): ?>
                <form action="<?php echo $base_url; ?>actions/mark_read.php" method="POST" class="m-0">
                    <?php echo csrfTokenField('notifications'); ?>
                    <button type="submit" class="btn btn-link text-primary small text-decoration-none p-0">Oznacz jako przeczytane</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="notification-list">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-bell-slash fs-2 mb-2 d-block opacity-25"></i>
                        <p class="small mb-0">Brak nowych powiadomień</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                        $icon = 'bi-info-circle';
                        $tone = 'primary';
                        $label = 'System';
                        switch($notif['type']) {
                            case 'rank_up': $icon = 'bi-graph-up-arrow'; $tone = 'success'; $label = 'Ranga'; break;
                            case 'rank_down': $icon = 'bi-graph-down-arrow'; $tone = 'danger'; $label = 'Ranga'; break;
                            case 'friend_request': $icon = 'bi-person-plus'; $tone = 'info'; $label = 'Znajomi'; break;
                            case 'missions_refresh': $icon = 'bi-arrow-repeat'; $tone = 'warning'; $label = 'Misje'; break;
                            case 'daily_missions_refresh':
                            case 'weekly_missions_refresh':
                            case 'monthly_missions_refresh': $icon = 'bi-arrow-repeat'; $tone = 'warning'; $label = 'Misje'; break;
                            case 'mission_complete': $icon = 'bi-trophy'; $tone = 'success'; $label = 'Misje'; break;
                            case 'duel_challenge':
                            case 'duel_accepted':
                            case 'duel_finished': $icon = 'bi-lightning-charge'; $tone = 'danger'; $label = 'Pojedynek'; break;
                        }
                        $notifUrl = !empty($notif['action_url']) ? normalizeNotificationActionUrl($notif['action_url']) : null;
                        $notifHref = $notifUrl ? (preg_match('#^https?://#i', $notifUrl) ? $notifUrl : $base_url . ltrim($notifUrl, '/')) : $base_url . 'notifications.php';
                        ?>
                        <a href="<?php echo htmlspecialchars($notifHref); ?>" class="notification-menu-item <?php echo $notif['is_read'] ? 'is-read' : 'is-unread'; ?> text-decoration-none text-reset">
                            <div class="notification-menu-icon text-<?php echo $tone; ?>">
                                <i class="bi <?php echo $icon; ?>"></i>
                            </div>
                            <div class="notification-menu-body flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <span class="notification-menu-label"><?php echo htmlspecialchars($label); ?></span>
                                    <?php if (!$notif['is_read']): ?><span class="notification-menu-dot" aria-label="Nieprzeczytane"></span><?php endif; ?>
                                </div>
                                <div class="notification-menu-message text-wrap"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-menu-time">
                                    <i class="bi bi-clock me-1"></i><?php echo date('d.m, H:i', strtotime($notif['created_at'])); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="p-2 text-center border-top">
                <a href="<?php echo $base_url; ?>notifications.php" class="text-muted small text-decoration-none">Zobacz wszystkie</a>
            </div>
        </div>
    </div>
    
    <div class="dropdown">
        <button type="button" class="user-profile-info dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu użytkownika">
            <span class="text-end d-none d-sm-block me-2">
                <span class="fw-bold small d-block"><?php echo htmlspecialchars(function_exists('userDisplayName') ? userDisplayName($topbarUser) : ($_SESSION['username'] ?? 'admin')); ?><?php echo function_exists('getUserBadgeHtml') ? getUserBadgeHtml($topbarUser['role'] ?? ($_SESSION['role'] ?? 'user'), (int)($topbarUser['is_verified'] ?? 0)) : ''; ?></span>
                <span class="text-muted d-block" style="font-size: 0.75rem;">
                    <?php 
                    $role = $topbarUser['role'] ?? ($_SESSION['role'] ?? 'user');
                    switch($role) {
                        case 'admin': echo 'Administrator'; break;
                        case 'dyrektor': echo 'Dyrektor'; break;
                        case 'teacher': echo 'Nauczyciel'; break;
                        case 'guest': echo 'Gość'; break;
                        default:
                            if (function_exists('getRankInfoByXp')) {
                                try {
                                    $rankTop = getRankInfoByXp((int)($topbarUser['xp'] ?? 0));
                                    echo htmlspecialchars($rankTop['name']);
                                } catch (Exception $e) {
                                    echo 'Uczeń';
                                }
                            } else {
                                echo 'Uczeń';
                            }
                            break;
                    }
                    ?>
                </span>
            </span>
            <span class="user-avatar-small">
                <?php
                $topbarAvatar = (string)($topbarUser['avatar_path'] ?? '');
                if ($topbarAvatar !== '' && preg_match('~^uploads/avatars/[a-zA-Z0-9_.-]+\.webp$~', $topbarAvatar)):
                ?>
                    <img src="<?php echo $base_url . htmlspecialchars($topbarAvatar); ?>" alt="" class="user-avatar-img">
                <?php else: ?>
                    <?php echo strtoupper(substr(function_exists('userDisplayName') ? userDisplayName($topbarUser) : ($_SESSION['username'] ?? 'A'), 0, 1)); ?>
                <?php endif; ?>
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2" aria-labelledby="userDropdown">
            <?php if ($isGuestTopbar): ?>
            <li><a class="dropdown-item py-2" href="<?php echo $base_url; ?>test.php?setup=1&new=1"><i class="bi bi-journal-text me-2 text-primary"></i>Test jako gość</a></li>
            <li><a class="dropdown-item py-2" href="<?php echo $base_url; ?>login.php"><i class="bi bi-box-arrow-in-right me-2 text-success"></i>Zaloguj</a></li>
            <li><a class="dropdown-item py-2" href="<?php echo $base_url; ?>register.php"><i class="bi bi-person-plus me-2 text-info"></i>Załóż konto</a></li>
            <?php else: ?>
            <li><a class="dropdown-item py-2" href="<?php echo $base_url; ?>profile.php"><i class="bi bi-person me-2 text-primary"></i>Mój profil</a></li>
            <li><a class="dropdown-item py-2" href="<?php echo $base_url; ?>settings.php"><i class="bi bi-gear me-2 text-info"></i>Ustawienia</a></li>
            <li><a class="dropdown-item py-2" href="<?php echo $base_url; ?>progress.php"><i class="bi bi-graph-up me-2 text-success"></i>Statystyki</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="<?php echo $base_url; ?>actions/logout.php" method="POST" class="m-0">
                    <?php echo csrfTokenField('logout'); ?>
                    <button type="submit" class="dropdown-item py-2 text-danger">
                        <i class="bi <?php echo $isGuestTopbar ? 'bi-door-open' : 'bi-box-arrow-right'; ?> me-2"></i><?php echo $isGuestTopbar ? 'Wyjdź' : 'Wyloguj się'; ?>
                    </button>
                </form>
            </li>
        </ul>
    </div>
</header>

<?php if ($decisionNotification): ?>
<?php
    $decisionApproved = ($decisionNotification['type'] ?? '') === 'teacher_application_approved';
    $decisionMessage = (string)($decisionNotification['message'] ?? '');
    if (function_exists('mb_check_encoding') && !mb_check_encoding($decisionMessage, 'UTF-8')) {
        $convertedDecisionMessage = @mb_convert_encoding($decisionMessage, 'UTF-8', 'Windows-1250');
        if ($convertedDecisionMessage !== false) {
            $decisionMessage = $convertedDecisionMessage;
        }
    }
    $decisionMessage = str_replace(["\r", "\0"], ["\n", ''], $decisionMessage);
    $decisionDateText = '';
    $decisionAdminText = '';
    $decisionNoteText = '';
    foreach (preg_split('/\n+/', $decisionMessage) ?: [] as $decisionLine) {
        $decisionLine = trim($decisionLine);
        if ($decisionLine === '') continue;
        if ($decisionDateText === '' && preg_match('/\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}/', $decisionLine, $dateMatch)) {
            $decisionDateText = $dateMatch[0];
        }
        if (stripos($decisionLine, 'Notatka:') !== false) {
            $decisionNoteText = trim(substr($decisionLine, strpos($decisionLine, ':') + 1));
            continue;
        }
        if (strpos($decisionLine, ':') !== false) {
            [$decisionKey, $decisionValue] = array_map('trim', explode(':', $decisionLine, 2));
            $decisionKeyLower = function_exists('mb_strtolower') ? mb_strtolower($decisionKey, 'UTF-8') : strtolower($decisionKey);
            if (
                $decisionAdminText === ''
                && $decisionValue !== ''
                && (
                    strpos($decisionKeyLower, 'podj') !== false
                    || preg_match('/^[lł]$/iu', $decisionKey)
                    || (stripos($decisionValue, 'administrator') !== false && stripos($decisionKeyLower, 'data') === false)
                )
            ) {
                $decisionAdminText = $decisionValue;
            }
        }
    }
    if ($decisionDateText === '' && !empty($decisionNotification['created_at'])) {
        $decisionTimestamp = strtotime((string)$decisionNotification['created_at']);
        if ($decisionTimestamp) {
            $decisionDateText = date('d.m.Y H:i', $decisionTimestamp);
        }
    }
    if ($decisionAdminText !== '' && isset($pdo)) {
        $adminUsernameCandidate = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $decisionAdminText));
        $adminUsernameCandidate = ltrim($adminUsernameCandidate, '@');
        if ($adminUsernameCandidate !== '') {
            try {
                $adminLookupStmt = $pdo->prepare("SELECT username, first_name, last_name, role FROM users WHERE username = ? LIMIT 1");
                $adminLookupStmt->execute([$adminUsernameCandidate]);
                $adminLookup = $adminLookupStmt->fetch(PDO::FETCH_ASSOC);
                if ($adminLookup) {
                    $adminLookupName = function_exists('userDisplayName') ? userDisplayName($adminLookup) : ($adminLookup['username'] ?? $decisionAdminText);
                    $adminLookupHandle = function_exists('userHandle') ? userHandle($adminLookup) : '';
                    $adminLookupRole = function_exists('getUserRoleBadge') ? getUserRoleBadge($adminLookup['role'] ?? 'admin')['label'] : 'Administrator';
                    $decisionAdminText = $adminLookupName;
                    if ($adminLookupHandle !== '' && $adminLookupName !== ($adminLookup['username'] ?? '')) {
                        $decisionAdminText .= ' ' . $adminLookupHandle;
                    }
                    $decisionAdminText .= ' (' . $adminLookupRole . ')';
                }
            } catch (Exception $e) {
                // Keep parsed label.
            }
        }
    }
    if ($decisionAdminText === '') $decisionAdminText = 'Administrator';
    if ($decisionDateText === '') $decisionDateText = date('d.m.Y H:i');
    $decisionNotificationId = (int)($decisionNotification['id'] ?? 0);
    $decisionEsc = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<style>
.role-decision-layer {
    position: fixed;
    inset: 0;
    z-index: 1090;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(248, 250, 252, 0.42);
    backdrop-filter: blur(2px);
    opacity: 0;
    pointer-events: none;
    transition: opacity .16s ease;
}
.role-decision-layer.is-visible {
    opacity: 1;
    pointer-events: auto;
}
.role-decision-card {
    width: min(540px, 100%);
    border: 1px solid rgba(15, 23, 42, .1);
    border-radius: 20px;
    background: #fff;
    color: #0f172a;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
    padding: 1.25rem;
}
.role-decision-top {
    display: flex;
    gap: .9rem;
    align-items: flex-start;
}
.role-decision-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    flex: 0 0 auto;
    font-size: 1.4rem;
}
.role-decision-card.is-approved .role-decision-icon {
    background: #dcfce7;
    color: #15803d;
}
.role-decision-card.is-rejected .role-decision-icon {
    background: #fee2e2;
    color: #b91c1c;
}
.role-decision-close {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: #64748b;
    font-size: 1.35rem;
    line-height: 1;
    padding: .25rem;
}
.role-decision-close:hover {
    color: #0f172a;
}
.role-decision-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
}
.role-decision-copy {
    margin: .25rem 0 0;
    color: #475569;
}
.role-decision-details {
    display: grid;
    gap: .7rem;
    margin: 1rem 0 1.1rem;
}
.role-decision-detail {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 14px;
    background: #f8fafc;
    padding: .75rem .85rem;
}
.role-decision-detail dt {
    margin-bottom: .2rem;
    color: #64748b;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.role-decision-detail dd {
    margin: 0;
    color: #0f172a;
    font-weight: 700;
}
.role-decision-actions {
    display: flex;
    justify-content: flex-end;
    gap: .6rem;
    flex-wrap: wrap;
}
.role-decision-actions .btn {
    border-radius: 999px;
    padding-inline: 1.15rem;
    font-weight: 700;
}
body.dark-mode .role-decision-layer {
    background: rgba(15, 23, 42, .24);
}
body.dark-mode .role-decision-card {
    background: #111827;
    color: #f8fafc;
    border-color: rgba(148, 163, 184, .25);
}
body.dark-mode .role-decision-copy,
body.dark-mode .role-decision-close {
    color: #cbd5e1;
}
body.dark-mode .role-decision-detail {
    background: rgba(15, 23, 42, .6);
    border-color: rgba(148, 163, 184, .18);
}
body.dark-mode .role-decision-detail dt {
    color: #94a3b8;
}
body.dark-mode .role-decision-detail dd,
body.dark-mode .role-decision-close:hover {
    color: #fff;
}
</style>
<div
    class="role-decision-layer"
    id="roleDecisionLayer"
    aria-hidden="true"
    data-read-url="<?php echo $decisionEsc($base_url . 'actions/mark_read.php'); ?>"
    data-csrf="<?php echo $decisionEsc(generateCsrfToken('notifications')); ?>"
    data-notification-id="<?php echo $decisionNotificationId; ?>"
    data-storage-key="role-decision-read-<?php echo $decisionNotificationId; ?>"
>
    <section class="role-decision-card <?php echo $decisionApproved ? 'is-approved' : 'is-rejected'; ?>" role="dialog" aria-modal="true" aria-labelledby="roleDecisionTitle">
        <div class="role-decision-top">
            <div class="role-decision-icon" aria-hidden="true">
                <i class="bi <?php echo $decisionApproved ? 'bi-check2' : 'bi-x-lg'; ?>"></i>
            </div>
            <div>
                <h2 class="role-decision-title" id="roleDecisionTitle">
                    <?php echo $decisionApproved ? 'Aplikacja zaakceptowana' : 'Aplikacja odrzucona'; ?>
                </h2>
                <p class="role-decision-copy">
                    Twoja prośba o rolę nauczyciela została <?php echo $decisionApproved ? 'zaakceptowana' : 'odrzucona'; ?>.
                </p>
            </div>
            <button type="button" class="role-decision-close" data-role-decision-close aria-label="Zamknij">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <dl class="role-decision-details">
            <div class="role-decision-detail">
                <dt>Data decyzji</dt>
                <dd><?php echo $decisionEsc($decisionDateText); ?></dd>
            </div>
            <div class="role-decision-detail">
                <dt>Decyzję podjął</dt>
                <dd><?php echo $decisionEsc($decisionAdminText); ?></dd>
            </div>
            <?php if ($decisionNoteText !== ''): ?>
            <div class="role-decision-detail">
                <dt>Notatka</dt>
                <dd><?php echo $decisionEsc($decisionNoteText); ?></dd>
            </div>
            <?php endif; ?>
        </dl>
        <div class="role-decision-actions">
            <a href="<?php echo $base_url; ?>notifications.php" class="btn btn-outline-primary" data-role-decision-go>Powiadomienia</a>
            <button type="button" class="btn btn-primary" data-role-decision-close>OK</button>
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const layer = document.getElementById('roleDecisionLayer');
    if (!layer) return;
    const storageKey = layer.dataset.storageKey || '';
    if (storageKey && window.sessionStorage && sessionStorage.getItem(storageKey) === '1') return;
    layer.classList.add('is-visible');
    layer.setAttribute('aria-hidden', 'false');

    let acknowledged = false;
    const acknowledge = function(targetUrl) {
        if (acknowledged) {
            if (targetUrl) window.location.href = targetUrl;
            return;
        }
        acknowledged = true;
        if (storageKey && window.sessionStorage) sessionStorage.setItem(storageKey, '1');
        layer.classList.remove('is-visible');
        layer.setAttribute('aria-hidden', 'true');

        const body = new URLSearchParams();
        body.set('csrf_token', layer.dataset.csrf || '');
        body.set('notification_id', layer.dataset.notificationId || '0');

        fetch(layer.dataset.readUrl || 'actions/mark_read.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).catch(function() {}).finally(function() {
            if (targetUrl) window.location.href = targetUrl;
        });
    };

    layer.querySelectorAll('[data-role-decision-close]').forEach(function(button) {
        button.addEventListener('click', function() { acknowledge(''); });
    });
    layer.querySelectorAll('[data-role-decision-go]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            acknowledge(link.href);
        });
    });
});
</script>
<?php endif; ?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            });
        }
        
        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
});
</script>
