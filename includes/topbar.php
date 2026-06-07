<?php
if (isset($pdo) && isset($_SESSION['user_id']) && function_exists('updateUserActivity')) {
    updateUserActivity($pdo, $_SESSION['user_id']);
}
$current_page = basename($_SERVER['PHP_SELF'], ".php");
// Determine base path by looking for a root-level file
$base_url = file_exists('config/db.php') ? '' : '../';
$isGuestTopbar = function_exists('isGuestMode') && isGuestMode();
$pageBlockAdminNotice = $_SESSION['feature_block_notice'] ?? null;
unset($_SESSION['feature_block_notice']);
$sandboxElementAdminNotice = $_SESSION['sandbox_element_block_notice'] ?? null;
unset($_SESSION['sandbox_element_block_notice']);
?>
<script src="<?php echo htmlspecialchars($base_url); ?>assets/js/theme-handler.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/theme-handler.js'); ?>"></script>
<?php if (!$isGuestTopbar && isset($_SESSION['user_id'])): $appApiClientLoaded = true; ?>
<script src="<?php echo htmlspecialchars($base_url); ?>assets/js/api-client.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/api-client.js'); ?>"></script>
<?php endif; ?>
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
        if (function_exists('syncAppStatusNotificationsForUser')) {
            syncAppStatusNotificationsForUser($pdo, (int)$_SESSION['user_id']);
        }
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
    <div class="topbar-actions ms-auto d-flex align-items-center">
    <a href="https://zsem.edu.pl" target="_blank" rel="noopener noreferrer" class="topbar-icon" title="Strona szkoły" aria-label="Strona szkoły">
        <i class="bi bi-mortarboard"></i>
    </a>
    <div class="dropdown" id="notificationsDropdownRoot"
         data-feed-url="<?php echo htmlspecialchars($base_url . 'ajax/notifications_feed.php'); ?>"
         data-respond-url="<?php echo htmlspecialchars($base_url . 'ajax/duel_respond.php'); ?>"
         data-base-url="<?php echo htmlspecialchars($base_url); ?>"
         data-csrf="<?php echo htmlspecialchars(generateCsrfToken('notifications')); ?>">
        <button type="button" class="topbar-icon" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Pokaż powiadomienia">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span class="notification-badge<?php echo $unreadCount > 0 ? '' : ' d-none'; ?>" id="notificationBadge" aria-live="polite">
                <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
            </span>
        </button>
        <div class="dropdown-menu dropdown-menu-end topbar-dropdown notification-dropdown" id="notificationDropdownMenu">
            <div class="notification-dropdown-header">
                <div>
                    <h6 class="mb-0 fw-bold">Powiadomienia</h6>
                    <span class="notification-dropdown-sub">Ostatnie aktywności</span>
                </div>
                <form action="<?php echo $base_url; ?>actions/mark_read.php" method="POST" class="m-0<?php echo $unreadCount > 0 ? '' : ' d-none'; ?>" id="notificationMarkReadForm">
                    <?php echo csrfTokenField('notifications'); ?>
                    <button type="submit" class="btn btn-link notification-mark-read-btn">Oznacz jako przeczytane</button>
                </form>
            </div>
            <div class="notification-list" id="notificationList" data-poll-interval="2000">
                <?php if (!$isGuestTopbar && !empty($_SESSION['user_id'])): ?>
                    <?php echo renderNotificationsDropdownListHtml($pdo, (int)$_SESSION['user_id'], $notifications, $base_url); ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted notification-empty-state">
                        <p class="small mb-0">Zaloguj się, aby zobaczyć powiadomienia.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="notification-dropdown-footer">
                <a href="<?php echo $base_url; ?>notifications.php" class="notification-see-all">Zobacz wszystkie <i class="bi bi-arrow-right-short"></i></a>
            </div>
        </div>
    </div>
    
    <div class="dropdown">
        <button type="button" class="user-profile-info dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu użytkownika">
            <span class="user-profile-text d-none d-sm-block">
                <span class="user-profile-name"><?php echo htmlspecialchars(function_exists('userDisplayName') ? userDisplayName($topbarUser) : ($_SESSION['username'] ?? 'admin')); ?><?php echo function_exists('getUserBadgeHtml') ? getUserBadgeHtml($topbarUser['role'] ?? ($_SESSION['role'] ?? 'user'), (int)($topbarUser['is_verified'] ?? 0)) : ''; ?></span>
                <span class="user-profile-role">
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
            <i class="bi bi-chevron-down user-profile-chevron d-none d-sm-inline" aria-hidden="true"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end topbar-dropdown user-profile-dropdown" aria-labelledby="userDropdown">
            <?php if ($isGuestTopbar): ?>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>test.php?setup=1&new=1"><i class="bi bi-journal-text"></i>Test jako gość</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>login.php"><i class="bi bi-box-arrow-in-right"></i>Zaloguj</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>register.php"><i class="bi bi-person-plus"></i>Załóż konto</a></li>
            <?php else: ?>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>profile.php"><i class="bi bi-person"></i>Mój profil</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>settings.php"><i class="bi bi-gear"></i>Ustawienia</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>progress.php"><i class="bi bi-graph-up"></i>Statystyki</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="<?php echo $base_url; ?>actions/logout.php" method="POST" class="m-0">
                    <?php echo csrfTokenField('logout'); ?>
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi <?php echo $isGuestTopbar ? 'bi-door-open' : 'bi-box-arrow-right'; ?>"></i><?php echo $isGuestTopbar ? 'Wyjdź' : 'Wyloguj się'; ?>
                    </button>
                </form>
            </li>
        </ul>
    </div>
    </div>
</header>

<?php if (is_array($pageBlockAdminNotice)): ?>
<div class="container-fluid px-4 pt-3 feature-block-notice">
    <div class="alert alert-warning border-0 shadow-sm mb-0" role="status">
        <div class="d-flex gap-2 align-items-start">
            <i class="bi bi-lock-fill mt-1"></i>
            <div>
                <strong>Kategoria jest obecnie wyłączona dla wybranych ról.</strong>
                <div class="small">
                    <?php echo htmlspecialchars((string)($pageBlockAdminNotice['category_label'] ?? 'Kategoria')); ?>
                    · <?php echo htmlspecialchars((string)($pageBlockAdminNotice['moderator_label'] ?? 'Administrator')); ?>
                    · <?php echo htmlspecialchars((string)($pageBlockAdminNotice['disabled_date'] ?? date('d.m.Y H:i'))); ?>
                    <?php $noticeRoles = $pageBlockAdminNotice['target_role_labels'] ?? []; ?>
                    <?php if (is_array($noticeRoles) && $noticeRoles): ?>
                        · role: <?php echo htmlspecialchars(implode(', ', array_map('strval', $noticeRoles))); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (is_array($sandboxElementAdminNotice)): ?>
<div class="container-fluid px-4 pt-3 sandbox-element-block-notice">
    <div class="alert alert-info border-0 shadow-sm mb-0" role="status">
        <div class="d-flex gap-2 align-items-start">
            <i class="bi bi-unlock-fill mt-1"></i>
            <div>
                <strong>Element sandboxa jest wyłączony dla wybranych ról, ale masz dostęp administracyjny.</strong>
                <div class="small">
                    <?php echo htmlspecialchars((string)($sandboxElementAdminNotice['element_label'] ?? 'Element sandboxa')); ?>
                    · <?php echo htmlspecialchars((string)($sandboxElementAdminNotice['moderator_label'] ?? 'Administrator')); ?>
                    · <?php echo htmlspecialchars((string)($sandboxElementAdminNotice['disabled_date'] ?? date('d.m.Y H:i'))); ?>
                    <?php $sandboxNoticeRoles = $sandboxElementAdminNotice['target_role_labels'] ?? []; ?>
                    <?php if (is_array($sandboxNoticeRoles) && $sandboxNoticeRoles): ?>
                        · role: <?php echo htmlspecialchars(implode(', ', array_map('strval', $sandboxNoticeRoles))); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$isTeacherAreaTopbar = !$isGuestTopbar
    && isset($_SESSION['user_id'])
    && in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'], true)
    && strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/teacher/') !== false;
if ($isTeacherAreaTopbar):
$teacherOpsCurrent = basename(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
$teacherOpsLinks = [
    ['href' => 'teacher/index.php', 'icon' => 'bi-speedometer2', 'label' => 'Panel', 'files' => ['index.php']],
    ['href' => 'teacher/create_exam.php', 'icon' => 'bi-plus-circle', 'label' => 'Online', 'files' => ['create_exam.php', 'edit_exam.php', 'host_exam.php', 'exam_details.php']],
    ['href' => 'teacher/pdf_generator.php', 'icon' => 'bi-file-earmark-text', 'label' => 'Generator', 'files' => ['pdf_generator.php', 'txt_generator.php', 'import_txt.php']],
    ['href' => 'teacher/custom_exams.php', 'icon' => 'bi-collection', 'label' => 'Baza własna', 'files' => ['custom_exams.php', 'custom_exam.php', 'custom_exam_edit.php']],
    ['href' => 'teacher/requests.php', 'icon' => 'bi-inbox', 'label' => 'Zgłoszenia', 'files' => ['requests.php']],
];
?>
<nav class="teacher-ops-strip" aria-label="Narzędzia sprawdzianów nauczyciela" data-teacher-ops-strip="1">
    <?php foreach ($teacherOpsLinks as $link): ?>
        <?php $isCurrentTeacherOp = in_array($teacherOpsCurrent, $link['files'], true); ?>
        <a href="<?php echo htmlspecialchars($base_url . $link['href']); ?>" class="<?php echo $isCurrentTeacherOp ? 'active teacher-ops-strip-current' : ''; ?>" <?php echo $isCurrentTeacherOp ? 'aria-current="page"' : ''; ?>>
            <i class="bi <?php echo htmlspecialchars($link['icon']); ?>"></i><?php echo htmlspecialchars($link['label']); ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="modal fade app-status-modal" id="appStatusModal" tabindex="-1" aria-labelledby="appStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="badge rounded-pill text-bg-info mb-2" id="appStatusModalLevel">Status</span>
                    <h5 class="modal-title fw-900" id="appStatusModalLabel">Status</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="app-status-modal-body mb-3" id="appStatusModalBody"></p>
                <div class="app-status-modal-meta small text-muted" id="appStatusModalMeta"></div>
            </div>
        </div>
    </div>
</div>

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

        const readUrl = layer.dataset.readUrl || 'actions/mark_read.php';
        const markReadRequest = window.AppApi
            ? window.AppApi.postForm(readUrl, body, { timeout: 8000 })
            : fetch(readUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

        markReadRequest.catch(function() {}).finally(function() {
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

<?php if (!$isGuestTopbar && isset($_SESSION['user_id'])): ?>
<script src="<?php echo htmlspecialchars($base_url); ?>assets/js/notifications-poll.js?v=<?php echo (int)@filemtime(__DIR__ . '/../assets/js/notifications-poll.js'); ?>"></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        const openSidebar = function() {
            sidebar.classList.add('is-opening');
            sidebar.classList.add('show');
            overlay.classList.add('show');
            document.body.classList.add('sidebar-open');
            window.requestAnimationFrame(function() {
                window.setTimeout(function() {
                    sidebar.classList.remove('is-opening');
                }, 360);
            });
        };
        const closeSidebar = function() {
            sidebar.classList.remove('show', 'is-opening');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        };
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', openSidebar);
        }
        
        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }
        
        overlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Tab' && sidebar.classList.contains('show')) {
                sidebar.classList.add('keyboard-navigation');
            }
        });
    }
});
</script>
