<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$userId = $_SESSION['user_id'];
$flashMsg = getSessionMessage();
syncAppStatusNotificationsForUser($pdo, (int)$userId);
$notifications = getNotifications($pdo, $userId, 50);
$notificationCounts = [
    'all' => count($notifications),
    'missions' => 0,
    'rank' => 0,
    'social' => 0,
    'system' => 0,
];
foreach ($notifications as $notification) {
    $type = $notification['type'] ?? 'system';
    if (strpos($type, 'mission') !== false) {
        $notificationCounts['missions']++;
    } elseif (strpos($type, 'rank') !== false) {
        $notificationCounts['rank']++;
    } elseif (strpos($type, 'friend') !== false || strpos($type, 'duel') !== false) {
        $notificationCounts['social']++;
    } else {
        $notificationCounts['system']++;
    }
}

// Mark all as read when visiting this page
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type NOT IN ('mfa_optional_prompt', 'mfa_optional_declined')")->execute([$userId]);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Powiadomienia - Platforma Testowa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .notification-page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: 24px;
            color: #fff;
            background: linear-gradient(135deg, var(--primary-color-dark), #0f172a);
            box-shadow: 0 18px 44px rgba(37, 99, 235, .16);
        }
        .notification-page-head .text-muted { color: rgba(255,255,255,.74) !important; }
        .notification-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
        }
        .notification-filter {
            border: 1px solid rgba(148,163,184,.22);
            border-radius: 18px;
            padding: 1rem;
            background: #fff;
            display: flex;
            align-items: center;
            gap: .75rem;
            text-align: left;
            color: #0f172a;
            box-shadow: 0 12px 28px rgba(15,23,42,.06);
        }
        .notification-filter i { color: var(--primary-color-dark); font-size: 1.25rem; }
        .notification-filter strong { margin-left: auto; }
        .notification-filter.active { border-color: var(--primary-color-dark); box-shadow: 0 0 0 4px rgba(37,99,235,.1); }
        .notification-timeline { padding: .75rem; }
        .notification-card {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 18px;
            border: 1px solid rgba(148,163,184,.18);
            background: #fff;
            margin-bottom: .75rem;
        }
        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: #f8fafc;
            flex: 0 0 auto;
            font-size: 1.25rem;
        }
        .notification-message { font-weight: 600; overflow-wrap: anywhere; }
        .notification-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-left: auto;
        }
        body.dark-mode .notification-filter,
        body.dark-mode .notification-card { background:#1e293b; color:#e5e7eb; border-color:rgba(148,163,184,.24); }
        body.dark-mode .notification-icon { background:#0f172a; }
        @media (max-width: 767.98px) {
            .notification-page-head { align-items: stretch; flex-direction: column; }
            .notification-summary-grid { grid-template-columns: 1fr; }
            .notification-card { flex-direction: column; }
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
                    <div class="notification-page-head mb-4">
                        <div>
                            <h2 class="fw-bold mb-1">Powiadomienia</h2>
                            <p class="text-muted mb-0">Historia aktywności, misji, rang i alertów systemowych.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (!empty($notifications)): ?>
                            <form action="actions/delete_notification.php" method="POST" onsubmit="return appConfirmSubmit(this, 'Usunąć wszystkie powiadomienia?')">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="delete_all" value="1">
                                <button type="submit" class="btn btn-light rounded-pill px-4">
                                    <i class="bi bi-trash3 me-1"></i>Usuń wszystkie
                                </button>
                            </form>
                            <?php endif; ?>
                            <a href="settings.php" class="btn btn-outline-light rounded-pill px-4">
                                <i class="bi bi-sliders me-1"></i>Ustawienia
                            </a>
                        </div>
                    </div>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?php echo ($flashMsg['type'] === 'error') ? 'danger' : 'info'; ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                            <?php echo htmlspecialchars($flashMsg['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
                        </div>
                    <?php endif; ?>

                    <div class="notification-summary-grid mb-4">
                        <button type="button" class="notification-filter active" data-filter="all">
                            <i class="bi bi-inboxes"></i>
                            <span>Wszystkie</span>
                            <strong><?php echo (int)$notificationCounts['all']; ?></strong>
                        </button>
                        <button type="button" class="notification-filter" data-filter="missions">
                            <i class="bi bi-trophy"></i>
                            <span>Misje</span>
                            <strong><?php echo (int)$notificationCounts['missions']; ?></strong>
                        </button>
                        <button type="button" class="notification-filter" data-filter="rank">
                            <i class="bi bi-stars"></i>
                            <span>Rangi</span>
                            <strong><?php echo (int)$notificationCounts['rank']; ?></strong>
                        </button>
                        <button type="button" class="notification-filter" data-filter="social">
                            <i class="bi bi-people"></i>
                            <span>Społeczne</span>
                            <strong><?php echo (int)$notificationCounts['social']; ?></strong>
                        </button>
                    </div>

                    <div class="dashboard-panel notification-timeline-panel p-0 overflow-hidden">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell-slash text-muted opacity-25" style="font-size: 4rem;"></i>
                                <h5 class="mt-3">Brak powiadomień</h5>
                                <p class="text-muted">Gdy coś się wydarzy, poinformujemy Cię o tym!</p>
                            </div>
                        <?php else: ?>
                            <div class="notification-timeline">
                                <?php foreach ($notifications as $notif): ?>
                                    <?php
                                    $icon = 'bi-info-circle';
                                    $tone = 'primary';
                                    $group = 'system';
                                    $label = 'System';
                                    switch($notif['type']) {
                                        case 'rank_up': $icon = 'bi-graph-up-arrow'; $tone = 'success'; $group = 'rank'; $label = 'Ranga'; break;
                                        case 'rank_down': $icon = 'bi-graph-down-arrow'; $tone = 'danger'; $group = 'rank'; $label = 'Ranga'; break;
                                        case 'friend_request': $icon = 'bi-person-plus'; $tone = 'info'; $group = 'social'; $label = 'Znajomi'; break;
                                        case 'duel_challenge': $icon = 'bi-lightning-charge'; $tone = 'danger'; $group = 'social'; $label = 'Pojedynek'; break;
                                        case 'duel_accepted': $icon = 'bi-lightning-charge-fill'; $tone = 'success'; $group = 'social'; $label = 'Pojedynek'; break;
                                        case 'duel_finished': $icon = 'bi-flag-fill'; $tone = 'primary'; $group = 'social'; $label = 'Pojedynek'; break;
                                        case 'missions_refresh': $icon = 'bi-arrow-repeat'; $tone = 'warning'; $group = 'missions'; $label = 'Misje'; break;
                                        case 'daily_missions_refresh':
                                        case 'weekly_missions_refresh':
                                        case 'monthly_missions_refresh': $icon = 'bi-arrow-repeat'; $tone = 'warning'; $group = 'missions'; $label = 'Misje'; break;
                                        case 'mission_complete': $icon = 'bi-trophy'; $tone = 'success'; $group = 'missions'; $label = 'Misje'; break;
                                        case 'mfa_optional_prompt': $icon = 'bi-shield-lock'; $tone = 'warning'; $group = 'system'; $label = 'Bezpieczeństwo'; break;
                                        case 'app_status': $icon = 'bi-broadcast'; $tone = 'info'; $group = 'system'; $label = 'Status'; break;
                                    }
                                    $appStatusPayload = resolveAppStatusNotification($pdo, $notif);
                                    $actionUrl = !empty($notif['action_url']) ? notificationActionHref($notif['action_url']) : null;
                                    ?>
                                    <div class="notification-card" data-group="<?php echo htmlspecialchars($group); ?>">
                                        <div class="notification-icon text-<?php echo $tone; ?>">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="notification-body">
                                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1">
                                                <span class="badge rounded-pill text-bg-<?php echo $tone === 'warning' ? 'warning' : $tone; ?>"><?php echo htmlspecialchars($label); ?></span>
                                                <div class="notification-actions">
                                                    <span class="text-muted small"><i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($notif['created_at'])); ?></span>
                                                    <?php if ($appStatusPayload): ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-primary rounded-pill"
                                                                data-app-status-open
                                                                data-status-title="<?php echo htmlspecialchars($appStatusPayload['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                data-status-body="<?php echo htmlspecialchars($appStatusPayload['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                data-status-level="<?php echo htmlspecialchars($appStatusPayload['level'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                data-status-date="<?php echo htmlspecialchars($appStatusPayload['date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                                                data-status-moderator="<?php echo htmlspecialchars($appStatusPayload['moderator'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                                            Więcej
                                                        </button>
                                                    <?php elseif ($actionUrl): ?>
                                                        <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                                            <i class="bi bi-box-arrow-up-right me-1"></i>Otwórz
                                                        </a>
                                                    <?php endif; ?>
                                                    <form action="actions/delete_notification.php" method="POST" class="m-0">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="notification_id" value="<?php echo (int)$notif['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" aria-label="Usuń powiadomienie">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php
                                                $notificationMessage = (string)($notif['message'] ?? '');
                                                if (in_array($notif['type'] ?? '', ['teacher_application_approved', 'teacher_application_rejected'], true)) {
                                                    $notificationMessage = hydrateTeacherDecisionNotificationMessage($pdo, $notificationMessage);
                                                }
                                                if ($appStatusPayload) {
                                                    $notificationMessage = $appStatusPayload['title'];
                                                }
                                            ?>
                                            <div class="notification-message"><?php echo nl2br(htmlspecialchars($notificationMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
    document.querySelectorAll('.notification-filter').forEach(button => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter;
            document.querySelectorAll('.notification-filter').forEach(el => el.classList.remove('active'));
            button.classList.add('active');
            document.querySelectorAll('.notification-card').forEach(card => {
                card.hidden = filter !== 'all' && card.dataset.group !== filter;
            });
        });
    });
    </script>
</body>
</html>
