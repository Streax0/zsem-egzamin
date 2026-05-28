<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

function adminBanDurationOptions(): array {
    return [
        'permanent' => ['label' => 'Bezterminowo', 'seconds' => null],
        '1h' => ['label' => '1 godzina', 'seconds' => 3600],
        '24h' => ['label' => '24 godziny', 'seconds' => 86400],
        '7d' => ['label' => '7 dni', 'seconds' => 604800],
        '30d' => ['label' => '30 dni', 'seconds' => 2592000],
    ];
}

function adminBanExpiresAt(string $duration): ?string {
    $options = adminBanDurationOptions();
    $seconds = $options[$duration]['seconds'] ?? null;
    if ($seconds === null) {
        return null;
    }
    return (new DateTimeImmutable('now'))->modify('+' . (int)$seconds . ' seconds')->format('Y-m-d H:i:s');
}

function adminFormatBanExpiry(?string $expiresAt): string {
    if (!$expiresAt) {
        return 'bezterminowo';
    }
    $timestamp = strtotime($expiresAt);
    return $timestamp ? 'do ' . date('d.m.Y H:i', $timestamp) : 'czasowy';
}

function bannedEmailsTableExists(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'banned_emails'");
        return (bool) $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function ensureBannedEmailsTable(PDO $pdo): bool {
    if (bannedEmailsTableExists($pdo)) {
        if (function_exists('dbAddColumnIfMissing')) {
            dbAddColumnIfMissing($pdo, 'banned_emails', 'expires_at', "DATETIME DEFAULT NULL AFTER banned_by");
        }
        return true;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS banned_emails (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) UNIQUE NOT NULL,
            reason TEXT DEFAULT NULL,
            banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            banned_by INT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        return bannedEmailsTableExists($pdo);
    } catch (PDOException $e) {
        error_log('Failed to create banned_emails table: ' . $e->getMessage());
        return false;
    }
}

function bannedIpsTableExists(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'banned_ips'");
        return (bool) $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function ensureBannedIpsTable(PDO $pdo): bool {
    if (bannedIpsTableExists($pdo)) {
        if (function_exists('dbAddColumnIfMissing')) {
            dbAddColumnIfMissing($pdo, 'banned_ips', 'expires_at', "DATETIME DEFAULT NULL AFTER banned_by");
        }
        return true;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS banned_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) UNIQUE NOT NULL,
            reason TEXT DEFAULT NULL,
            banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            banned_by INT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        return bannedIpsTableExists($pdo);
    } catch (PDOException $e) {
        error_log('Failed to create banned_ips table: ' . $e->getMessage());
        return false;
    }
}

// Only admins allowed
if (!isAdmin($pdo, $_SESSION['user_id'])) {
    setSessionMessage('error', 'Brak uprawnień do panelu administracyjnego.');
    redirect('index.php');
}
ensurePlatformEnhancements($pdo);

// Handle POST actions (reset password, delete, set role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token, 'admin')) {
        setSessionMessage('error', 'Nieprawidłowy token CSRF.');
        redirect('admin.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    switch ($action) {
        case 'reset_password':
            $newPassword = trim($_POST['new_password'] ?? '');
            $passwordErrors = $newPassword !== '' ? validatePasswordPolicy($newPassword) : [];
            if ($userId <= 0) {
                setSessionMessage('error', 'Nieprawidłowy użytkownik.');
            } elseif ($newPassword === '') {
                setSessionMessage('error', 'Podaj nowe hasło. Panel nie wyświetla już haseł tymczasowych ze względów bezpieczeństwa.');
            } elseif (!empty($passwordErrors)) {
                setSessionMessage('error', implode(' ', $passwordErrors));
            } elseif (updateUserPassword($pdo, $userId, $newPassword)) {
                setSessionMessage('success', 'Hasło użytkownika zostało zresetowane.');
            } else {
                setSessionMessage('error', 'Błąd podczas resetowania hasła.');
            }
            redirect('admin.php');
            break;

        case 'delete_user':
            if ($userId <= 0) {
                setSessionMessage('error', 'Nieprawidłowy użytkownik.');
            } elseif ($userId === (int)$_SESSION['user_id']) {
                setSessionMessage('error', 'Nie możesz usunąć własnego konta.');
            } else {
                if (deleteUser($pdo, $userId)) {
                    logAdminAction($pdo, $_SESSION['user_id'], 'delete_user', 'user', $userId);
                    setSessionMessage('success', 'Użytkownik usunięty.');
                } else {
                    setSessionMessage('error', 'Błąd podczas usuwania użytkownika.');
                }
            }
            redirect('admin.php');
            break;

        case 'set_role':
            $role = trim($_POST['role'] ?? 'user');
            if ($userId <= 0 || !in_array($role, ['user', 'teacher', 'admin', 'dyrektor'])) {
                setSessionMessage('error', 'Nieprawidłowe dane.');
            } elseif ($userId === (int)$_SESSION['user_id'] && !in_array($role, ['admin', 'dyrektor'], true)) {
                setSessionMessage('error', 'Nie możesz odebrać sobie dostępu administracyjnego.');
            } else {
                if (setUserRole($pdo, $userId, $role)) {
                    logAdminAction($pdo, $_SESSION['user_id'], 'set_role', 'user', $userId, $role);
                    setSessionMessage('success', 'Rola użytkownika zaktualizowana.');
                } else {
                    setSessionMessage('error', 'Błąd przy ustawianiu roli.');
                }
            }
            redirect('admin.php');
            break;

        case 'ban_user':
            $reason = trim((string)($_POST['reason'] ?? 'Naruszenie regulaminu'));
            $reason = mb_substr($reason !== '' ? $reason : 'Naruszenie regulaminu', 0, 500);
            $banMethod = trim($_POST['ban_method'] ?? 'both');
            $banDuration = trim((string)($_POST['ban_duration'] ?? 'permanent'));
            if (!array_key_exists($banDuration, adminBanDurationOptions())) {
                $banDuration = 'permanent';
            }
            $banExpiresAt = adminBanExpiresAt($banDuration);
            if ($userId <= 0) {
                setSessionMessage('error', 'Nieprawidłowy użytkownik.');
            } elseif ($userId === (int)$_SESSION['user_id']) {
                setSessionMessage('error', 'Nie możesz zbanować własnego konta.');
            } elseif (!in_array($banMethod, ['email', 'ip', 'both'], true)) {
                setSessionMessage('error', 'Nieprawidłowa metoda bana.');
            } else {
                $emailTableReady = ensureBannedEmailsTable($pdo);
                $ipTableReady = ensureBannedIpsTable($pdo);
                $banEmail = in_array($banMethod, ['email', 'both'], true);
                $banIp = in_array($banMethod, ['ip', 'both'], true);

                try {
                    $stmt = $pdo->prepare("SELECT email, last_login_ip FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([$userId]);
                    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$userData) {
                        setSessionMessage('error', 'Nie znaleziono użytkownika.');
                        redirect('admin.php');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE users SET is_banned = 1, ban_expires_at = ?, session_version = COALESCE(session_version, 1) + 1 WHERE id = ?")
                        ->execute([$banExpiresAt, $userId]);
                    $email = $userData['email'] ?? null;
                    $lastLoginIp = $userData['last_login_ip'] ?? null;

                    if ($banEmail && $emailTableReady && $email) {
                        $pdo->prepare("INSERT INTO banned_emails (email, reason, banned_by, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason), banned_by = VALUES(banned_by), banned_at = NOW(), expires_at = VALUES(expires_at)")
                            ->execute([$email, $reason, $_SESSION['user_id'], $banExpiresAt]);
                    }

                    if ($banIp && $ipTableReady && !empty($lastLoginIp)) {
                        $pdo->prepare("INSERT INTO banned_ips (ip_address, reason, banned_by, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason), banned_by = VALUES(banned_by), banned_at = NOW(), expires_at = VALUES(expires_at)")
                            ->execute([$lastLoginIp, $reason, $_SESSION['user_id'], $banExpiresAt]);
                    }

                    $pdo->commit();
                    logAdminAction($pdo, $_SESSION['user_id'], 'ban_user', 'user', $userId, $banMethod . '; ' . $banDuration);
                    setSessionMessage('success', 'Użytkownik został zbanowany ' . adminFormatBanExpiry($banExpiresAt) . '.');
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Admin ban failed: ' . $e->getMessage());
                    setSessionMessage('error', 'Nie udało się zbanować użytkownika.');
                }
            }
            redirect('admin.php');
            break;

        case 'unban_user':
            $emailTableReady = ensureBannedEmailsTable($pdo);
            $ipTableReady = ensureBannedIpsTable($pdo);
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL, session_version = COALESCE(session_version, 1) + 1 WHERE id = ?")->execute([$userId]);
                
                $stmt = $pdo->prepare("SELECT email, last_login_ip FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                $email = $userData['email'] ?? null;
                $lastLoginIp = $userData['last_login_ip'] ?? null;

                if ($emailTableReady && $email) {
                    $pdo->prepare("DELETE FROM banned_emails WHERE email = ?")->execute([$email]);
                }

                if ($ipTableReady && !empty($lastLoginIp)) {
                    $pdo->prepare("DELETE FROM banned_ips WHERE ip_address = ?")->execute([$lastLoginIp]);
                }
                
                $pdo->commit();
                logAdminAction($pdo, $_SESSION['user_id'], 'unban_user', 'user', $userId);
                setSessionMessage('success', 'Użytkownik został odbanowany.');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setSessionMessage('error', 'Błąd podczas odbanowania.');
            }
            redirect('admin.php');
            break;

        case 'create_rank':
            $rankName = trim($_POST['rank_name'] ?? '');
            $rankXp = (int)($_POST['rank_min_xp'] ?? 0);
            $rankIcon = trim($_POST['rank_icon'] ?? 'bi-shield-fill');
            $rankColor = trim($_POST['rank_color'] ?? 'var(--primary-color)');
            $rankDescription = trim($_POST['rank_description'] ?? '');
            if ($rankName === '' || $rankXp < 0) {
                setSessionMessage('error', 'Podaj nazwę rangi i poprawny próg XP.');
            } elseif (createRankDefinition($pdo, $rankName, $rankXp, $rankIcon, $rankColor, $rankDescription)) {
                setSessionMessage('success', 'Nowa ranga została dodana.');
            } else {
                setSessionMessage('error', 'Nie udało się dodać rangi. Sprawdź, czy full_schema.sql został zaimportowany.');
            }
            redirect('admin.php');
            break;

        case 'reset_mfa':
            if ($userId <= 0) {
                setSessionMessage('error', 'Nieprawidłowy użytkownik.');
            } elseif (resetMfaForUser($pdo, $userId)) {
                addNotification($pdo, $userId, 'mfa_reset', 'Administrator zresetował 2FA na Twoim koncie. Skonfiguruj je ponownie przy następnym logowaniu.', 'mfa.php');
                logAdminAction($pdo, $_SESSION['user_id'], 'reset_mfa', 'user', $userId);
                setSessionMessage('success', '2FA użytkownika zostało zresetowane.');
            } else {
                setSessionMessage('error', 'Nie udało się zresetować 2FA.');
            }
            redirect('admin.php');
            break;

        case 'broadcast_notification':
            $message = trim($_POST['message'] ?? '');
            $targetRole = trim($_POST['target_role'] ?? 'all');
            if ($message === '' || mb_strlen($message) > 500) {
                setSessionMessage('error', 'Komunikat musi mieć od 1 do 500 znaków.');
            } else {
                $allowedRoles = ['all', 'user', 'teacher', 'admin', 'dyrektor'];
                if (!in_array($targetRole, $allowedRoles, true)) $targetRole = 'all';
                if ($targetRole === 'all') {
                    $stmt = $pdo->query("SELECT id FROM users WHERE is_banned = 0");
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND is_banned = 0");
                    $stmt->execute([$targetRole]);
                }
                $sent = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $targetUserId) {
                    if (addNotification($pdo, (int)$targetUserId, 'admin_broadcast', $message)) $sent++;
                }
                logAdminAction($pdo, $_SESSION['user_id'], 'broadcast_notification', 'role', 0, $targetRole . ': ' . $message);
                setSessionMessage('success', 'Wysłano komunikat do ' . $sent . ' kont.');
            }
            redirect('admin.php#admin-system');
            break;

        case 'update_system_limits':
            $allInLimit = max(1, min(20, (int)($_POST['all_in_daily_limit'] ?? 3)));
            setAppSetting($pdo, 'all_in_daily_limit', $allInLimit);
            logAdminAction($pdo, $_SESSION['user_id'], 'update_system_limits', 'app_settings', null, 'all_in_daily_limit=' . $allInLimit);
            setSessionMessage('success', 'Limity systemowe zostały zapisane.');
            redirect('admin.php#admin-system');
            break;

        case 'start_ranking_event':
            $templateId = (int)($_POST['template_id'] ?? 0);
            $duration = max(7, min(30, (int)($_POST['duration_days'] ?? 7)));
            try {
                ensurePlatformEnhancements($pdo);
                $stmt = $pdo->prepare("SELECT * FROM ranking_event_templates WHERE id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$templateId]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$template) {
                    setSessionMessage('error', 'Nie znaleziono szablonu wydarzenia.');
                } elseif (count(getActiveRankingEvents($pdo, 2)) >= 2) {
                    setSessionMessage('error', 'Aktywne mogą być maksymalnie 2 wydarzenia rankingowe.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ranking_events (template_id, name, description, multiplier, starts_at, ends_at, status) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active')");
                    $stmt->execute([(int)$template['id'], $template['name'], $template['description'], (float)$template['multiplier'], $duration]);
                    logAdminAction($pdo, $_SESSION['user_id'], 'start_ranking_event', 'ranking_event_template', $templateId, $template['name']);
                    setSessionMessage('success', 'Wydarzenie rankingowe zostało uruchomione.');
                }
            } catch (PDOException $e) {
                setSessionMessage('error', 'Nie udało się uruchomić wydarzenia.');
            }
            redirect('admin.php#admin-system');
            break;

        case 'finish_ranking_events':
            ensurePlatformEnhancements($pdo);
            $pdo->exec("UPDATE ranking_events SET status = 'finished' WHERE status = 'active'");
            logAdminAction($pdo, $_SESSION['user_id'], 'finish_ranking_events', 'ranking_event', null);
            setSessionMessage('success', 'Aktywne wydarzenia zostały zakończone.');
            redirect('admin.php#admin-system');
            break;

        case 'update_abuse_report':
            $reportId = (int)($_POST['report_id'] ?? 0);
            $status = $_POST['report_status'] ?? 'reviewing';
            $note = trim($_POST['admin_note'] ?? '');
            if (updateAbuseReportStatus($pdo, $reportId, $status, $note, (int)$_SESSION['user_id'])) {
                setSessionMessage('success', 'Zgłoszenie zostało zaktualizowane.');
            } else {
                setSessionMessage('error', 'Nie udało się zaktualizować zgłoszenia.');
            }
            redirect('admin.php#admin-system');
            break;

        case 'delete_abuse_report':
            $reportId = (int)($_POST['report_id'] ?? 0);
            if (deleteAbuseReport($pdo, $reportId, (int)$_SESSION['user_id'])) {
                setSessionMessage('success', 'Zgłoszenie zostało usunięte.');
            } else {
                setSessionMessage('error', 'Nie udało się usunąć zgłoszenia.');
            }
            redirect('admin.php#admin-system');
            break;

        case 'delete_admin_audit_entry':
            $auditId = (int)($_POST['audit_id'] ?? 0);
            if (deleteAdminAuditLogEntry($pdo, $auditId, (int)$_SESSION['user_id'])) {
                setSessionMessage('success', 'Wpis audytu został usunięty.');
            } else {
                setSessionMessage('error', 'Nie udało się usunąć wpisu audytu.');
            }
            redirect('admin.php#admin-audit');
            break;

        case 'update_rank':
            $rankId = (int)($_POST['rank_id'] ?? 0);
            $rankName = trim($_POST['rank_name'] ?? '');
            $rankXp = (int)($_POST['rank_min_xp'] ?? 0);
            $rankIcon = trim($_POST['rank_icon'] ?? 'bi-shield-fill');
            $rankColor = trim($_POST['rank_color'] ?? '#3b82f6');
            $rankDescription = trim($_POST['rank_description'] ?? '');
            if ($rankId <= 0 || $rankName === '' || $rankXp < 0) {
                setSessionMessage('error', 'Podaj poprawne dane rangi.');
            } elseif (updateRankDefinition($pdo, $rankId, $rankName, $rankXp, $rankIcon, $rankColor, $rankDescription)) {
                setSessionMessage('success', 'Ranga została zaktualizowana.');
            } else {
                setSessionMessage('error', 'Nie udało się zaktualizować rangi.');
            }
            redirect('admin.php');
            break;

        case 'delete_rank':
            $rankId = (int)($_POST['rank_id'] ?? 0);
            if ($rankId <= 0) {
                setSessionMessage('error', 'Nieprawidłowa ranga.');
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM rank_definitions WHERE id = ?");
                    $stmt->execute([$rankId]);
                    setSessionMessage('success', 'Ranga została usunięta.');
                } catch (PDOException $e) {
                    setSessionMessage('error', 'Nie udało się usunąć rangi.');
                }
            }
            redirect('admin.php');
            break;

        case 'update_user_settings':
            $xp = max(0, (int)($_POST['xp'] ?? 0));
            $profilePublic = isset($_POST['profile_public']) ? 1 : 0;
            $statsPublic = isset($_POST['stats_public']) ? 1 : 0;
            $allowFriends = isset($_POST['allow_friend_requests']) ? 1 : 0;
            $allowComments = isset($_POST['allow_profile_comments']) ? 1 : 0;
            $searchable = isset($_POST['searchable']) ? 1 : 0;
            $verified = isset($_POST['is_verified']) ? 1 : 0;
            $rankingVisible = isset($_POST['ranking_visible']) ? 1 : 0;
            if ($userId <= 0) {
                setSessionMessage('error', 'Nieprawidłowy użytkownik.');
            } else {
                try {
                    $hasCommentColumn = false;
                    try {
                        $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'allow_profile_comments'");
                        $hasCommentColumn = (bool)$columnStmt->fetch();
                    } catch (PDOException $e) {
                        $hasCommentColumn = false;
                    }
                    $forcedVerified = 0;
                    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                    $roleStmt->execute([$userId]);
                    $targetRole = (string)$roleStmt->fetchColumn();
                    if (in_array($targetRole, privilegedStaffRoles(), true)) {
                        $verified = 1;
                        $forcedVerified = 1;
                    }
                    $verifiedAtSql = dbColumnExists($pdo, 'users', 'verified_at')
                        ? ", verified_at = CASE WHEN ? = 1 AND verified_at IS NULL THEN NOW() WHEN ? = 0 THEN NULL ELSE verified_at END, verified_by_admin_id = CASE WHEN ? = 1 THEN ? ELSE NULL END"
                        : "";
                    if ($hasCommentColumn) {
                        $stmt = $pdo->prepare("UPDATE users SET xp = ?, profile_public = ?, stats_public = ?, allow_friend_requests = ?, searchable = ?, is_verified = ?, allow_profile_comments = ?, ranking_visible = ? $verifiedAtSql WHERE id = ?");
                        $params = [$xp, $profilePublic, $statsPublic, $allowFriends, $searchable, $verified, $allowComments, $rankingVisible];
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET xp = ?, profile_public = ?, stats_public = ?, allow_friend_requests = ?, searchable = ?, is_verified = ?, ranking_visible = ? $verifiedAtSql WHERE id = ?");
                        $params = [$xp, $profilePublic, $statsPublic, $allowFriends, $searchable, $verified, $rankingVisible];
                    }
                    if ($verifiedAtSql !== '') {
                        array_push($params, $verified, $verified, $verified, $_SESSION['user_id']);
                    }
                    $params[] = $userId;
                    $stmt->execute($params);
                    logAdminAction($pdo, $_SESSION['user_id'], 'update_user_settings', 'user', $userId);
                    setSessionMessage('success', $forcedVerified ? 'Ustawienia zapisane. Rola uprzywilejowana pozostaje zweryfikowana automatycznie.' : 'Ustawienia użytkownika zostały zapisane.');
                } catch (PDOException $e) {
                    setSessionMessage('error', 'Nie udało się zapisać ustawień użytkownika.');
                }
            }
            redirect('admin.php');
            break;
    }
}

// Listing / search / pagination
$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, role, class, avatar_path, xp, profile_public, stats_public, allow_friend_requests, searchable, is_verified, ranking_visible, created_at, last_login, is_banned, ban_expires_at FROM users WHERE username LIKE :q OR email LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR class LIKE :q ORDER BY COALESCE(NULLIF(class, ''), 'ZZZ'), id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':q', $like, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE :q OR email LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR class LIKE :q");
    $countStmt->bindValue(':q', $like, PDO::PARAM_STR);
    $countStmt->execute();
    $totalUsers = (int)$countStmt->fetchColumn();
} else {
    $users = getUsers($pdo, $limit, $offset);
    $totalUsers = getUsersCount($pdo);
}

try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'allow_profile_comments'");
    if ($columnStmt->fetch() && !empty($users)) {
        $ids = array_map('intval', array_column($users, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $commentStmt = $pdo->prepare("SELECT id, allow_profile_comments FROM users WHERE id IN ($placeholders)");
        $commentStmt->execute($ids);
        $commentMap = [];
        foreach ($commentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $commentMap[(int)$row['id']] = (int)$row['allow_profile_comments'];
        }
        foreach ($users as &$userRow) {
            $userRow['allow_profile_comments'] = $commentMap[(int)$userRow['id']] ?? 1;
        }
        unset($userRow);
    }
} catch (PDOException $e) {
    foreach ($users as &$userRow) {
        $userRow['allow_profile_comments'] = 1;
    }
    unset($userRow);
}

try {
    ensurePlatformEnhancements($pdo);
    if (!empty($users)) {
        $ids = array_map('intval', array_column($users, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $mfaStmt = $pdo->prepare("SELECT user_id, enabled_at FROM user_mfa WHERE user_id IN ($placeholders)");
        $mfaStmt->execute($ids);
        $mfaMap = [];
        foreach ($mfaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mfaMap[(int)$row['user_id']] = !empty($row['enabled_at']);
        }
        foreach ($users as &$userRow) {
            $userRow['mfa_enabled'] = $mfaMap[(int)$userRow['id']] ?? false;
        }
        unset($userRow);
    }
} catch (PDOException $e) {
    foreach ($users as &$userRow) {
        $userRow['mfa_enabled'] = false;
    }
    unset($userRow);
}

$totalPages = max(1, (int)ceil($totalUsers / $limit));
$rankDefinitions = getRankDefinitions($pdo);
$abuseReports = getAbuseReports($pdo, 80);
$abuseReportsRecent = $abuseReports;
$auditLog = getAdminAuditLog($pdo, 50);
$allAdminRequests = getAllAdminRequests($pdo);
$adminRequests = array_slice($allAdminRequests, 0, 8);
$rankingEvents = getRankingEvents($pdo, 8);
$rankingTemplates = [];
try {
    ensurePlatformEnhancements($pdo);
    $rankingTemplates = $pdo->query("SELECT id, name, multiplier, duration_days FROM ranking_event_templates WHERE is_active = 1 ORDER BY name ASC LIMIT 80")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rankingTemplates = [];
}
$allInDailyLimit = getAllInDailyLimit($pdo);
$systemStats = [
    'reports_new' => count(array_filter($abuseReports, static fn($r) => ($r['status'] ?? '') === 'new')),
    'requests_open' => count(array_filter($allAdminRequests, static fn($r) => in_array(($r['status'] ?? ''), ['sent', 'read', 'replied'], true))),
    'events_active' => count(array_filter($rankingEvents, static fn($r) => ($r['status'] ?? '') === 'active')),
    'audit_items' => count($auditLog),
];
$adminKpis = [
    'users_total' => $totalUsers,
    'teachers' => 0,
    'admins' => 0,
    'banned' => 0,
    'verified' => 0,
];
try {
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS users_total,
            SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) AS teachers,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins,
            SUM(CASE WHEN role = 'dyrektor' THEN 1 ELSE 0 END) AS directors,
            SUM(CASE WHEN is_banned = 1 THEN 1 ELSE 0 END) AS banned,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS verified
        FROM users
    ");
    $statsRow = $statsStmt ? $statsStmt->fetch(PDO::FETCH_ASSOC) : [];
    foreach ($adminKpis as $key => $fallback) {
        $adminKpis[$key] = isset($statsRow[$key]) ? (int)$statsRow[$key] : $fallback;
    }
} catch (PDOException $e) {
    $adminKpis['users_total'] = $totalUsers;
}
// Get flash message (compat with old string-style flash_message)
$rawFlash = getSessionMessage();
$flashMessage = '';
$flashType = 'info';
if (is_array($rawFlash)) {
    $flashMessage = $rawFlash['message'] ?? '';
    $flashType = $rawFlash['type'] ?? 'info';
} elseif (is_string($rawFlash)) {
    $flashMessage = $rawFlash;
    $flashType = 'info';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admina - ZSEM Egzamin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <style>
        body,
        .content-body {
            background: #eef3f6;
        }
        .dashboard-panel.admin-panel,
        .dashboard-panel.admin-rank-manager,
        #admin-system.dashboard-panel {
            border-radius: 8px;
            border: 1px solid #d8e2ea;
            background: #ffffff;
            box-shadow: 0 16px 42px rgba(15, 23, 42, .05);
        }
        .admin-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, .85fr);
            gap: 1rem;
            padding: clamp(1rem, 3vw, 2rem);
            border-radius: 12px;
            color: #0f172a;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(148, 163, 184, .15);
            box-shadow: 0 20px 60px rgba(15, 23, 42, .06);
            overflow: hidden;
            position: relative;
        }
        .admin-hero::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 6px;
            background: linear-gradient(180deg, #3b82f6, #06b6d4 50%, #8b5cf6);
            border-radius: 6px 0 0 6px;
        }
        .admin-hero h2 {
            color: #0f172a;
            font-weight: 900;
            letter-spacing: -0.8px;
        }
        .admin-hero p {
            color: #64748b;
            max-width: 680px;
            line-height: 1.5;
        }
        .admin-hero-copy {
            display: flex;
            min-height: 100%;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
        }
        .admin-hero-label {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: .5rem;
            border-radius: 8px;
            padding: .5rem 1rem;
            color: #3b82f6;
            background: rgba(59, 130, 246, .08);
            border: 1px solid rgba(59, 130, 246, .2);
            font-weight: 700;
            font-size: .85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-nav-pills {
            display: flex;
            gap: .6rem;
            flex-wrap: wrap;
        }
        .admin-nav-pills a {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            padding: .6rem .9rem;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, .2);
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        .admin-nav-pills a:hover {
            background: #f0f9ff;
            border-color: #3b82f6;
            color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, .15);
        }
        .admin-kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
        }
        .admin-kpi-card {
            min-height: 104px;
            padding: 1rem;
            border-radius: 8px;
            background: #0f172a;
            border: 1px solid #111827;
            color: #f8fafc;
        }
        .admin-kpi-card i {
            display: inline-grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: rgba(255, 255, 255, .12);
            margin-bottom: .65rem;
        }
        .admin-kpi-value {
            font-size: clamp(1.35rem, 2vw, 2rem);
            line-height: 1;
            font-weight: 900;
        }
        .admin-kpi-label {
            color: #cbd5e1;
            font-size: .82rem;
            margin-top: .3rem;
        }
        .admin-search-card {
            border: 1px solid #d8e2ea;
            background: #ffffff;
            box-shadow: none;
        }
        .admin-search-card .input-group {
            border-radius: 8px !important;
        }
        .admin-table-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(148, 163, 184, .16);
            background: #fff;
        }
        .admin-users-table thead th {
            color: #475569;
            font-size: .75rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            border: none;
            background: linear-gradient(90deg, #f8fafc, #f0f9ff);
            font-weight: 700;
            padding: 1rem 1.25rem !important;
        }
        .admin-users-table tbody tr {
            border-bottom: 1px solid rgba(148, 163, 184, .06) !important;
            transition: all 0.25s ease;
            background: #ffffff;
        }
        .admin-users-table tbody tr:hover {
            background: linear-gradient(90deg, rgba(59, 130, 246, .02), rgba(59, 130, 246, .04)) !important;
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, .1) !important;
        }
        .admin-users-table tbody td {
            padding: 1.1rem 1.25rem !important;
            vertical-align: middle;
            color: #1e293b;
        }
        .admin-tool-card {
            border-radius: 12px;
            border: 1px solid rgba(148, 163, 184, .12);
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }
        .admin-request-card {
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, .18);
            background: rgba(248, 250, 252, .72);
        }
        .admin-reply-preview {
            border-left: 3px solid #2563eb;
            padding: .55rem .75rem;
            background: #ffffff;
            border-radius: 0 8px 8px 0;
        }
        .admin-audit-actions {
            width: 1%;
            white-space: nowrap;
        }
        .rank-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            margin-bottom: 0.55rem;
            flex-wrap: nowrap;
            min-width: 0;
        }
        .rank-chip-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.65rem 1rem;
            border-radius: 999px;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            min-height: 44px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            flex-shrink: 1;
            min-width: 0;
            overflow: hidden;
        }
        .rank-chip-badge i {
            font-size: 1rem;
            margin-left: 0.1rem;
        }
        .rank-chip-text {
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 260px;
        }
        .rank-delete-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.18);
            color: #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            flex-shrink: 0;
            transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
        }
        .rank-delete-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-1px);
            color: #111827;
        }
        .rank-delete-btn:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        .rank-delete-btn i {
            font-size: 0.95rem;
        }
        .admin-users-table-panel {
            border: 1px solid rgba(148, 163, 184, .1);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .04);
            background: #ffffff;
            overflow: hidden;
        }
        .admin-users-table tbody tr {
            background: #ffffff;
        }
        .admin-users-table tbody td {
            background: inherit;
            color: #334155;
        }
        .admin-class-row td {
            background: linear-gradient(90deg, rgba(59, 130, 246, .08), transparent) !important;
            border-top: 2px solid rgba(59, 130, 246, .15);
            border-bottom: 1px solid rgba(59, 130, 246, .1);
            padding: 1rem 1.25rem !important;
        }
        .admin-user-name {
            color: #0f172a;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .admin-user-email {
            color: #6b7280;
            overflow-wrap: anywhere;
            font-size: 0.8rem;
        }
        .admin-status-badge {
            padding: 0.4rem 0.75rem !important;
            font-size: 0.75rem !important;
            font-weight: 700;
            border-radius: 6px;
        }
        .admin-table-actions {
            display: flex;
            justify-content: flex-end;
            gap: .4rem;
            flex-wrap: nowrap;
            align-items: center;
        }
        .admin-action-grid {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
        }
        .admin-icon-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            padding: 0;
            font-size: .9rem;
            flex-shrink: 0;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .admin-icon-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            background-color: #f0f9ff !important;
            border-color: rgba(59, 130, 246, .3) !important;
        }
        .admin-role-select,
        .ban-method-select {
            background-color: #f8fafc;
            color: #0f172a;
            border-color: rgba(148, 163, 184, .25);
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            padding: 0.55rem 0.85rem;
        }
        .admin-role-select:hover,
        .ban-method-select:hover {
            border-color: rgba(59, 130, 246, .4);
            background-color: #f0f9ff;
        }
        .admin-role-select:focus,
        .ban-method-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .1);
            outline: none;
        }
        .admin-role-form {
            min-width: 240px;
            display: flex !important;
            gap: 0.6rem;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc, #f0f9ff);
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            border: 1px solid rgba(59, 130, 246, .15);
        }
        .admin-role-form select {
            flex: 1;
            min-width: 120px;
            background-color: #ffffff;
            border: 1px solid rgba(148, 163, 184, .2);
        }
        .admin-role-form button {
            border-radius: 8px !important;
            background-color: #3b82f6 !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, .3);
            transition: all 0.2s ease !important;
        }
        .admin-role-form button:hover {
            background-color: #2563eb !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, .4) !important;
        }
        .admin-expiry-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-top: .35rem;
            padding: .35rem 0.65rem;
            border-radius: 8px;
            background: rgba(234, 179, 8, .1);
            color: #b45309;
            border: 1px solid rgba(234, 179, 8, .3);
            font-size: .75rem;
            font-weight: 700;
        }
        .admin-user-modal-note {
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: .75rem;
            color: #475569;
        }
        .admin-rank-manager {
            background:
                radial-gradient(circle at 100% 0%, rgba(59, 130, 246, .12), transparent 30%),
                #ffffff;
            border: 1px solid rgba(148, 163, 184, .18);
        }
        .rank-editor-grid {
            display: grid;
            gap: .85rem;
        }
        .rank-editor-select-card {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 8px;
            background: rgba(248, 250, 252, .9);
            padding: 1rem;
        }
        .rank-editor-panel {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(148, 163, 184, .18);
        }
        .rank-editor-panel.active {
            display: block;
        }
        .rank-editor-row {
            display: grid;
            grid-template-columns: minmax(140px, 1.1fr) 110px minmax(130px, .9fr) 74px minmax(180px, 1.4fr) auto auto;
            gap: .65rem;
            align-items: center;
            padding: .85rem;
            border-radius: 8px;
            background: rgba(248, 250, 252, .9);
            border: 1px solid rgba(148, 163, 184, .18);
        }
        .rank-update-form {
            display: contents;
        }
        .rank-preview-dot {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .16);
        }
        body.dark-mode .admin-users-table tbody tr,
        body.dark-mode .admin-rank-manager,
        body.dark-mode .admin-search-card,
        body.dark-mode .admin-table-title,
        body.dark-mode .admin-request-card,
        body.dark-mode .admin-reply-preview,
        body.dark-mode .admin-tool-card {
            background: #111827;
        }
        /* Role Badge Styling */
        .badge {
            border-radius: 8px;
            font-weight: 700;
            padding: 0.5rem 0.85rem !important;
            font-size: 0.8rem !important;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .badge.bg-primary {
            background: rgba(59, 130, 246, .12) !important;
            color: #3b82f6 !important;
            border-color: rgba(59, 130, 246, .3) !important;
        }
        .badge.bg-success {
            background: rgba(34, 197, 94, .12) !important;
            color: #22c55e !important;
            border-color: rgba(34, 197, 94, .3) !important;
        }
        .badge.bg-danger {
            background: rgba(239, 68, 68, .12) !important;
            color: #ef4444 !important;
            border-color: rgba(239, 68, 68, .3) !important;
        }
        .badge.bg-warning {
            background: rgba(234, 179, 8, .12) !important;
            color: #b45309 !important;
            border-color: rgba(234, 179, 8, .3) !important;
        }
        .badge.bg-info {
            background: rgba(6, 182, 212, .12) !important;
            color: #06b6d4 !important;
            border-color: rgba(6, 182, 212, .3) !important;
        }
        .badge.bg-light {
            background: rgba(209, 213, 219, .2) !important;
            color: #374151 !important;
            border-color: rgba(209, 213, 219, .4) !important;
        }
        .admin-search-card {
            border: 1px solid rgba(148, 163, 184, .12) !important;
            background: #ffffff !important;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .02);
        }
        .admin-table-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, .08);
            background: linear-gradient(90deg, #ffffff, #f9fafb);
        }
        .admin-table-title h4 {
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .admin-table-title .text-muted {
            color: #64748b !important;
        }
        /* Admin Button Styling */
        .admin-icon-btn.btn-warning {
            background: linear-gradient(135deg, rgba(234, 179, 8, .15), rgba(251, 146, 60, .1)) !important;
            color: #b45309 !important;
            border: 1px solid rgba(234, 179, 8, .3) !important;
        }
        .admin-icon-btn.btn-warning:hover {
            background: linear-gradient(135deg, rgba(234, 179, 8, .25), rgba(251, 146, 60, .2)) !important;
            border-color: rgba(234, 179, 8, .5) !important;
        }
        .admin-icon-btn.btn-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, .15), rgba(248, 113, 113, .1)) !important;
            color: #dc2626 !important;
            border: 1px solid rgba(239, 68, 68, .3) !important;
        }
        .admin-icon-btn.btn-danger:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, .25), rgba(248, 113, 113, .2)) !important;
            border-color: rgba(239, 68, 68, .5) !important;
        }
        .admin-icon-btn.btn-light {
            background: #f3f4f6 !important;
            color: #374151 !important;
            border: 1px solid rgba(148, 163, 184, .2) !important;
        }
        .admin-icon-btn.btn-light:hover {
            background: #e5e7eb !important;
            border-color: rgba(148, 163, 184, .4) !important;
        }
        .admin-icon-btn.btn-outline-secondary {
            background: transparent !important;
            color: #6366f1 !important;
            border: 1.5px solid rgba(99, 102, 241, .3) !important;
        }
        .admin-icon-btn.btn-outline-secondary:hover {
            background: rgba(99, 102, 241, .1) !important;
            border-color: rgba(99, 102, 241, .6) !important;
        }
        .admin-icon-btn.btn-outline-success {
            background: transparent !important;
            color: #22c55e !important;
            border: 1.5px solid rgba(34, 197, 94, .3) !important;
        }
        .admin-icon-btn.btn-outline-success:hover {
            background: rgba(34, 197, 94, .1) !important;
            border-color: rgba(34, 197, 94, .6) !important;
        }
        body.dark-mode .admin-users-table tbody td,
        body.dark-mode .admin-user-name {
            color: #f8fafc;
        }
        body.dark-mode .admin-users-table thead th {
            background: #0f172a;
            color: #cbd5e1;
            border-color: rgba(148, 163, 184, .24);
        }
        body.dark-mode .admin-user-email {
            color: #cbd5e1;
        }
        body.dark-mode .admin-tool-card.bg-light {
            background: #111827 !important;
        }
        body.dark-mode .rank-editor-row {
            background: rgba(15, 23, 42, .78);
            border-color: rgba(148, 163, 184, .24);
        }
        body.dark-mode .rank-editor-select-card {
            background: rgba(15, 23, 42, .78);
            border-color: rgba(148, 163, 184, .24);
        }
        body.admin-page > .modal {
            z-index: 2070 !important;
        }
        body.admin-page > .modal-backdrop {
            z-index: 2060 !important;
        }
        body.admin-page > .modal-backdrop.show {
            opacity: .34;
            background-color: #0f172a;
        }
        body.admin-page .modal-content {
            background: #ffffff;
            color: #0f172a;
            border-radius: 8px;
        }
        body.admin-page .modal-open .sidebar,
        body.admin-page.modal-open .sidebar {
            pointer-events: none;
        }
        @media (max-width: 1199.98px) {
            .rank-editor-row {
                grid-template-columns: 1fr 120px;
            }
            .rank-editor-row .btn {
                width: 100%;
            }
        }
        @media (max-width: 991.98px) {
            .admin-hero {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 575.98px) {
            .admin-hero {
                padding: 1rem;
            }
            .admin-kpi-grid {
                grid-template-columns: 1fr;
            }
            .admin-table-title {
                align-items: flex-start;
                flex-direction: column;
            }
            .admin-action-grid {
                grid-template-columns: repeat(2, minmax(38px, auto));
                justify-content: start;
            }
            .admin-role-form {
                grid-column: 1 / -1;
                width: 100%;
            }
            .admin-users-table tbody td:nth-child(3) {
                display: none;
            }
            .admin-users-table thead th:nth-child(3) {
                display: none;
            }
            .admin-icon-btn {
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body class="admin-page">

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    
                    <section class="admin-hero mb-4 animate-in">
                        <div class="admin-hero-copy">
                            <div>
                                <span class="admin-hero-label"><i class="bi bi-command"></i>Ops desk</span>
                                <h2 class="fw-bold mt-3 mb-2">Sterowanie platformą</h2>
                                <p class="mb-0">Konta, role, zgłoszenia, wnioski, audyt i limity w zwartym panelu operacyjnym.</p>
                            </div>
                            <div class="admin-nav-pills">
                                <a href="#admin-users"><i class="bi bi-people"></i>Użytkownicy</a>
                                <a href="#admin-requests"><i class="bi bi-person-badge"></i>Wnioski</a>
                                <a href="admin_requests.php"><i class="bi bi-reply"></i>Odpowiedzi</a>
                                <a href="#admin-ranks"><i class="bi bi-award"></i>Rangi</a>
                                <a href="#admin-system"><i class="bi bi-activity"></i>System</a>
                            </div>
                        </div>
                        <div class="admin-kpi-grid">
                            <div class="admin-kpi-card">
                                <i class="bi bi-people-fill"></i>
                                <div class="admin-kpi-value"><?php echo (int)$adminKpis['users_total']; ?></div>
                                <div class="admin-kpi-label">kont ogółem</div>
                            </div>
                            <div class="admin-kpi-card">
                                <i class="bi bi-person-workspace"></i>
                                <div class="admin-kpi-value"><?php echo (int)$adminKpis['teachers']; ?></div>
                                <div class="admin-kpi-label">nauczycieli</div>
                            </div>
                            <div class="admin-kpi-card">
                                <i class="bi bi-person-x"></i>
                                <div class="admin-kpi-value"><?php echo (int)$adminKpis['banned']; ?></div>
                                <div class="admin-kpi-label">zbanowanych</div>
                            </div>
                            <div class="admin-kpi-card">
                                <i class="bi bi-flag-fill"></i>
                                <div class="admin-kpi-value"><?php echo (int)$systemStats['reports_new']; ?></div>
                                <div class="admin-kpi-label">nowych zgłoszeń</div>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($flashMessage)): ?>
                        <div class="alert alert-<?php echo ($flashType === 'error') ? 'danger' : ($flashType === 'success' ? 'success' : 'info'); ?> border-0 shadow-sm animate-in">
                            <i class="bi bi-info-circle-fill me-2"></i><?php echo htmlspecialchars($flashMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-panel mb-4 animate-in admin-panel admin-search-card">
                        <form method="GET" class="row g-3 admin-search-form align-items-end p-4">
                            <div class="col-12 col-md-8 col-lg-6">
                                <label class="form-label fw-bold small text-uppercase letter-spacing text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">Szukaj użytkownika</label>
                                <div class="input-group" style="border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(59, 130, 246, .08);">
                                    <span class="input-group-text bg-white border-0" style="border-right: 1px solid rgba(148, 163, 184, .1);"><i class="bi bi-search text-primary" style="font-weight: 600;"></i></span>
                                    <input type="text" name="q" class="form-control border-0" placeholder="np. imię, nick lub email..." value="<?php echo htmlspecialchars($search); ?>" style="font-weight: 500;">
                                    <button class="btn btn-primary px-4" type="submit" style="border-radius: 0; background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; font-weight: 700;"><i class="bi bi-search me-2"></i>Szukaj</button>
                                </div>
                            </div>
                            <?php if ($search !== ''): ?>
                                <div class="col-12 col-md-auto">
                                    <a href="admin.php" class="btn btn-outline-secondary" style="border-radius: 8px; font-weight: 600;"><i class="bi bi-x-lg me-1"></i>Wyczyść filtry</a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="dashboard-panel p-0 overflow-hidden animate-in admin-panel admin-users-table-panel" id="admin-users">
                        <div class="admin-table-title">
                            <div>
                                <h4 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>Użytkownicy</h4>
                                <div class="text-muted small"><?php echo $search !== '' ? 'Wyniki wyszukiwania' : 'Konta pogrupowane według klasy'; ?>: <?php echo (int)$totalUsers; ?></div>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2"><?php echo count($users); ?> na stronie</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 admin-users-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Użytkownik</th>
                                        <th>Rola</th>
                                        <th>Klasa</th>
                                        <th>Rejestracja</th>
                                        <th>Ostatni login</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $currentAdminClass = null; ?>
                                    <?php foreach ($users as $u): ?>
                                    <?php
                                        $adminClassLabel = trim((string)($u['class'] ?? '')) !== '' ? trim((string)$u['class']) : 'Bez klasy';
                                        $adminAvatar = (string)($u['avatar_path'] ?? '');
                                        $adminAvatarSrc = (preg_match('#^uploads/avatars/[a-zA-Z0-9_.-]+\.webp$#', $adminAvatar) && is_file(__DIR__ . '/' . $adminAvatar)) ? $adminAvatar : '';
                                        $adminDisplayName = userDisplayName($u);
                                        $adminHandle = userHandle($u);
                                    ?>
                                    <?php if ($adminClassLabel !== $currentAdminClass): ?>
                                        <?php $currentAdminClass = $adminClassLabel; ?>
                                        <tr class="admin-class-row">
                                            <td colspan="7" class="px-4 py-2">
                                                <span class="badge bg-light text-dark border"><i class="bi bi-mortarboard me-1"></i><?php echo htmlspecialchars($adminClassLabel); ?></span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="ps-4" data-label="Użytkownik">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="user-avatar-small bg-primary bg-opacity-10 text-primary fw-bold" style="width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem;">
                                                    <?php if ($adminAvatarSrc !== ''): ?>
                                                        <img src="<?php echo htmlspecialchars($adminAvatarSrc); ?>" alt="" class="user-avatar-img" style="width: 100%; height: 100%; border-radius: 10px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0" style="flex: 1;">
                                                    <div class="admin-user-name fw-600" style="font-size: .95rem; margin-bottom: .25rem;"><?php echo htmlspecialchars($adminDisplayName); ?></div>
                                                    <div class="admin-user-email" style="font-size: .8rem; color: #6b7280;"><?php echo htmlspecialchars(trim($adminHandle . ' ' . $u['email'])); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Rola">
                                            <?php $userRoleBadge = getUserRoleBadge($u['role'] ?? 'user'); ?>
                                            <span class="badge <?php echo htmlspecialchars($userRoleBadge['class']); ?> px-3">
                                                <?php echo htmlspecialchars($userRoleBadge['label']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Klasa">
                                            <span class="badge bg-light text-dark border px-3"><?php echo htmlspecialchars($adminClassLabel); ?></span>
                                        </td>
                                        <td data-label="Rejestracja" class="text-muted small" style="font-size: .85rem;"><?php echo date('d.m.Y', strtotime($u['created_at'])); ?></td>
                                        <td data-label="Ostatni login" class="text-muted small" style="font-size: .85rem;"><?php echo ($u['last_login'] && $u['last_login'] !== '0000-00-00 00:00:00') ? date('d.m.Y', strtotime($u['last_login'])) : 'Nigdy'; ?></td>
                                        <td data-label="Status">
                                            <?php $isBanned = isset($u['is_banned']) && $u['is_banned']; ?>
                                            <?php $banExpiresAt = !empty($u['ban_expires_at']) ? (string)$u['ban_expires_at'] : null; ?>
                                            <?php if ($isBanned): ?>
                                                <span class="badge bg-danger admin-status-badge">ZBANOWANY</span>
                                                <div class="admin-expiry-chip"><i class="bi bi-hourglass-split"></i><?php echo htmlspecialchars(adminFormatBanExpiry($banExpiresAt)); ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-success admin-status-badge">AKTYWNY</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Akcje" class="text-end pe-4">
                                            <div class="admin-table-actions admin-action-grid">
                                                <button type="button" class="btn btn-warning btn-sm admin-icon-btn" title="Resetuj hasło" data-bs-toggle="modal" data-bs-target="#adminPasswordModal" data-admin-reset-user data-user-id="<?php echo (int)$u['id']; ?>" data-user-label="<?php echo htmlspecialchars($adminDisplayName); ?>">
                                                    <i class="bi bi-key"></i>
                                                </button>

                                                <form method="POST" class="mfa-reset-form">
                                                    <?php echo csrfTokenField('admin'); ?>
                                                    <input type="hidden" name="action" value="reset_mfa">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm admin-icon-btn" title="<?php echo !empty($u['mfa_enabled']) ? 'Resetuj 2FA' : 'Wyczyść konfigurację 2FA'; ?>" data-admin-confirm="Zresetować 2FA tego konta?">
                                                        <i class="bi bi-shield-x"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" class="role-form admin-role-form d-flex gap-1 align-items-center">
                                                    <?php echo csrfTokenField('admin'); ?>
                                                    <input type="hidden" name="action" value="set_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <select name="role" class="form-select form-select-sm admin-role-select">
                                                        <option value="user" <?php echo ($u['role'] === 'user') ? 'selected' : ''; ?>>UCZEŃ</option>
                                                        <option value="teacher" <?php echo ($u['role'] === 'teacher') ? 'selected' : ''; ?>>NAUCZYCIEL</option>
                                                        <option value="admin" <?php echo ($u['role'] === 'admin') ? 'selected' : ''; ?>>ADMIN</option>
                                                        <option value="dyrektor" <?php echo ($u['role'] === 'dyrektor') ? 'selected' : ''; ?>>DYREKTOR</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm admin-icon-btn" title="Zapisz rolę"><i class="bi bi-check2"></i></button>
                                                </form>

                                                <button type="button" class="btn btn-light btn-sm admin-icon-btn admin-settings-btn" data-bs-toggle="modal" data-bs-target="#userSettings<?= (int)$u['id']; ?>" title="Ustawienia">
                                                    <i class="bi bi-sliders"></i>
                                                </button>

                                                <button type="button" class="btn btn-light text-danger btn-sm admin-icon-btn" title="Usuń konto" data-bs-toggle="modal" data-bs-target="#adminDeleteModal" data-admin-delete-user data-user-id="<?php echo (int)$u['id']; ?>" data-user-label="<?php echo htmlspecialchars($adminDisplayName); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>

                                                <?php if ($isBanned): ?>
                                                <form method="POST" class="unban-form">
                                                    <?php echo csrfTokenField('admin'); ?>
                                                    <input type="hidden" name="action" value="unban_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-success btn-sm admin-icon-btn" title="Odbanuj">
                                                        <i class="bi bi-person-check"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-danger btn-sm admin-icon-btn" title="Zbanuj" data-bs-toggle="modal" data-bs-target="#adminBanModal" data-admin-ban-user data-user-id="<?php echo (int)$u['id']; ?>" data-user-label="<?php echo htmlspecialchars($adminDisplayName); ?>">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php foreach ($users as $u): ?>
                    <div class="modal fade admin-user-settings-modal" id="userSettings<?= (int)$u['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <form method="POST">
                                    <?php echo csrfTokenField('admin'); ?>
                                    <input type="hidden" name="action" value="update_user_settings">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <div class="modal-header border-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Ustawienia: <?php echo htmlspecialchars($u['username']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">XP użytkownika</label>
                                            <input type="number" name="xp" class="form-control" min="0" value="<?php echo (int)($u['xp'] ?? 0); ?>">
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_verified" id="verified<?= (int)$u['id']; ?>" <?php echo !empty($u['is_verified']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="verified<?= (int)$u['id']; ?>">Zweryfikowany</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="profile_public" id="profilePublic<?= (int)$u['id']; ?>" <?php echo !empty($u['profile_public']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="profilePublic<?= (int)$u['id']; ?>">Profil publiczny</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="stats_public" id="statsPublic<?= (int)$u['id']; ?>" <?php echo !empty($u['stats_public']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="statsPublic<?= (int)$u['id']; ?>">Statystyki publiczne</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="allow_friend_requests" id="friends<?= (int)$u['id']; ?>" <?php echo !empty($u['allow_friend_requests']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="friends<?= (int)$u['id']; ?>">Zaproszenia znajomych</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="allow_profile_comments" id="comments<?= (int)$u['id']; ?>" <?php echo !isset($u['allow_profile_comments']) || !empty($u['allow_profile_comments']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="comments<?= (int)$u['id']; ?>">Komentarze profilu</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="searchable" id="searchable<?= (int)$u['id']; ?>" <?php echo !empty($u['searchable']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="searchable<?= (int)$u['id']; ?>">Widoczny w wyszukiwarce</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="ranking_visible" id="rankingVisible<?= (int)$u['id']; ?>" <?php echo !empty($u['ranking_visible']) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="rankingVisible<?= (int)$u['id']; ?>">Udział w rankingu</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                                        <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="modal fade" id="adminPasswordModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <form method="POST" id="adminPasswordForm">
                                    <?php echo csrfTokenField('admin'); ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="">
                                    <div class="modal-header border-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2 text-warning"></i>Reset hasła</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="admin-user-modal-note mb-3">Konto: <strong data-admin-modal-user>użytkownik</strong>. Po zapisie stare sesje konta zostaną unieważnione.</div>
                                        <label class="form-label fw-semibold" for="adminNewPassword">Nowe hasło</label>
                                        <input type="password" name="new_password" id="adminNewPassword" class="form-control" minlength="10" autocomplete="new-password" required>
                                        <div class="form-text">Minimum 10 znaków, mała i wielka litera, cyfra oraz znak specjalny.</div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                                        <button type="submit" class="btn btn-warning"><i class="bi bi-check2 me-1"></i>Zresetuj</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="adminBanModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <form method="POST" id="adminBanForm">
                                    <?php echo csrfTokenField('admin'); ?>
                                    <input type="hidden" name="action" value="ban_user">
                                    <input type="hidden" name="user_id" value="">
                                    <div class="modal-header border-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-slash-circle-fill me-2 text-danger"></i>Blokada konta</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="admin-user-modal-note mb-3">Konto: <strong data-admin-modal-user>użytkownik</strong>. Blokada wyloguje aktywne sesje po następnej walidacji.</div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" for="adminBanDuration">Czas blokady</label>
                                                <select name="ban_duration" id="adminBanDuration" class="form-select" required>
                                                    <?php foreach (adminBanDurationOptions() as $value => $meta): ?>
                                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($meta['label']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold" for="adminBanMethod">Zakres</label>
                                                <select name="ban_method" id="adminBanMethod" class="form-select ban-method-select" required>
                                                    <option value="both">E-mail + IP</option>
                                                    <option value="email">Tylko e-mail</option>
                                                    <option value="ip">Tylko IP</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-semibold" for="adminBanReason">Powód</label>
                                                <textarea name="reason" id="adminBanReason" class="form-control" rows="3" maxlength="500" required>Naruszenie regulaminu</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-slash-circle me-1"></i>Zablokuj</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="adminDeleteModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <form method="POST" id="adminDeleteForm">
                                    <?php echo csrfTokenField('admin'); ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="">
                                    <div class="modal-header border-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-trash3-fill me-2 text-danger"></i>Usuń konto</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="admin-user-modal-note">Konto: <strong data-admin-modal-user>użytkownik</strong>. Operacja usuwa konto i powiązane dane zależne od relacji w bazie.</div>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i>Usuń</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content border-0 shadow-lg">
                                <div class="modal-header border-0">
                                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Potwierdzenie</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-0" id="adminConfirmMessage">Potwierdzić akcję?</p>
                                </div>
                                <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                                    <button type="button" class="btn btn-danger" id="adminConfirmSubmit">Potwierdź</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <section class="dashboard-panel admin-rank-manager mt-4 animate-in" id="admin-ranks">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
                            <div>
                                <h4 class="fw-900 mb-1"><i class="bi bi-award-fill text-warning me-2"></i>Rangi XP</h4>
                                <p class="text-muted mb-0">Dodawaj, edytuj i usuwaj progi rang widoczne na profilach użytkowników.</p>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo count($rankDefinitions); ?> aktywnych</span>
                        </div>

                        <form method="POST" class="row g-3 align-items-end mb-4">
                            <?php echo csrfTokenField('admin'); ?>
                            <input type="hidden" name="action" value="create_rank">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Nazwa</label>
                                <input type="text" name="rank_name" class="form-control" placeholder="np. Expert I" maxlength="80" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Od XP</label>
                                <input type="number" name="rank_min_xp" class="form-control" min="0" value="0" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Ikona Bootstrap</label>
                                <input type="text" name="rank_icon" class="form-control" value="bi-shield-fill" maxlength="80">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small fw-bold">Kolor</label>
                                <input type="color" name="rank_color" class="form-control form-control-color w-100" value="#3b82f6">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Opis</label>
                                <input type="text" name="rank_description" class="form-control" maxlength="255" placeholder="Krótki opis rangi">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </form>

                        <?php if (empty($rankDefinitions)): ?>
                            <div class="alert alert-warning border-0 mb-0">Brak tabeli lub aktywnych rang. Zaimportuj schemat albo dodaj pierwszą rangę.</div>
                        <?php else: ?>
                            <?php $firstRankId = (int)($rankDefinitions[0]['id'] ?? 0); ?>
                            <div class="rank-editor-select-card">
                                <label class="form-label small fw-bold" for="rankEditorSelect">Edytuj istniejącą rangę</label>
                                <select class="form-select" id="rankEditorSelect">
                                    <?php foreach ($rankDefinitions as $rank): ?>
                                        <option value="<?php echo (int)$rank['id']; ?>">
                                            <?php echo htmlspecialchars($rank['name']); ?> · od <?php echo (int)$rank['min_xp']; ?> XP
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php foreach ($rankDefinitions as $rank): ?>
                                    <?php $rankColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($rank['color'] ?? '')) ? $rank['color'] : '#3b82f6'; ?>
                                    <div class="rank-editor-panel <?php echo (int)$rank['id'] === $firstRankId ? 'active' : ''; ?>" data-rank-panel="<?php echo (int)$rank['id']; ?>">
                                        <div class="rank-editor-row">
                                            <form method="POST" class="rank-update-form">
                                                <?php echo csrfTokenField('admin'); ?>
                                                <input type="hidden" name="action" value="update_rank">
                                                <input type="hidden" name="rank_id" value="<?php echo (int)$rank['id']; ?>">
                                                <input type="text" name="rank_name" class="form-control form-control-sm fw-bold" value="<?php echo htmlspecialchars($rank['name']); ?>" maxlength="80" required aria-label="Nazwa rangi">
                                                <input type="number" name="rank_min_xp" class="form-control form-control-sm" min="0" value="<?php echo (int)$rank['min_xp']; ?>" required aria-label="Próg XP">
                                                <input type="text" name="rank_icon" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rank['icon']); ?>" maxlength="80" aria-label="Ikona rangi">
                                                <input type="color" name="rank_color" class="form-control form-control-color form-control-sm w-100" value="<?php echo htmlspecialchars($rankColor); ?>" aria-label="Kolor rangi">
                                                <input type="text" name="rank_description" class="form-control form-control-sm" value="<?php echo htmlspecialchars($rank['description'] ?? ''); ?>" maxlength="255" aria-label="Opis rangi">
                                                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1"></i>Zapisz</button>
                                            </form>
                                            <form method="POST" data-admin-confirm="Usunąć tę rangę XP?">
                                                <?php echo csrfTokenField('admin'); ?>
                                                <input type="hidden" name="action" value="delete_rank">
                                                <input type="hidden" name="rank_id" value="<?php echo (int)$rank['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="dashboard-panel mt-4 animate-in" id="admin-system">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
                            <div>
                                <h4 class="fw-900 mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Moderacja i system</h4>
                                <p class="text-muted mb-0">Zgłoszenia, broadcast, limity, wydarzenia rankingowe, health check i audyt akcji.</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-danger rounded-pill px-3 py-2"><?php echo (int)$systemStats['reports_new']; ?> nowych zgłoszeń</span>
                                <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo (int)$systemStats['requests_open']; ?> otwartych wniosków</span>
                                <span class="badge bg-success rounded-pill px-3 py-2"><?php echo (int)$systemStats['events_active']; ?> aktywnych eventów</span>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="admin-tool-card p-3 h-100">
                                    <h6 class="fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Broadcast</h6>
                                    <form method="POST" class="vstack gap-2">
                                        <?php echo csrfTokenField('admin'); ?>
                                        <input type="hidden" name="action" value="broadcast_notification">
                                        <select name="target_role" class="form-select">
                                            <option value="all">Wszyscy aktywni</option>
                                            <option value="user">Uczniowie</option>
                                            <option value="teacher">Nauczyciele</option>
                                            <option value="admin">Admini</option>
                                            <option value="dyrektor">Dyrektorzy</option>
                                        </select>
                                        <textarea name="message" class="form-control" rows="3" maxlength="500" style="resize:none;" placeholder="Treść komunikatu..." required></textarea>
                                        <button class="btn btn-primary rounded-pill">Wyślij powiadomienie</button>
                                    </form>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="admin-tool-card p-3 h-100">
                                    <h6 class="fw-bold"><i class="bi bi-sliders me-2 text-warning"></i>Limity</h6>
                                    <form method="POST" class="vstack gap-2">
                                        <?php echo csrfTokenField('admin'); ?>
                                        <input type="hidden" name="action" value="update_system_limits">
                                        <label class="form-label small fw-bold">All-In Duel dziennie / użytkownik</label>
                                        <input type="number" name="all_in_daily_limit" class="form-control" min="1" max="20" value="<?php echo (int)$allInDailyLimit; ?>">
                                        <button class="btn btn-outline-primary rounded-pill">Zapisz limity</button>
                                    </form>
                                    <div class="small text-muted mt-3">Komentarze profilu: 20 wpisów, 100 znaków. Te limity są egzekwowane w backendzie.</div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="admin-tool-card p-3 h-100">
                                    <h6 class="fw-bold"><i class="bi bi-activity me-2 text-success"></i>System health</h6>
                                    <div class="vstack gap-2 small">
                                        <div class="d-flex justify-content-between"><span>PHP</span><strong><?php echo htmlspecialchars(PHP_VERSION); ?></strong></div>
                                        <div class="d-flex justify-content-between"><span>Użytkownicy</span><strong><?php echo (int)$totalUsers; ?></strong></div>
                                        <div class="d-flex justify-content-between"><span>Rangi</span><strong><?php echo count($rankDefinitions); ?></strong></div>
                                        <div class="d-flex justify-content-between"><span>Zgłoszenia</span><strong><?php echo count($abuseReports); ?></strong></div>
                                        <div class="d-flex justify-content-between"><span>Audyt</span><strong><?php echo count($auditLog); ?> ostatnich</strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="admin-tool-card p-3 h-100">
                                    <h6 class="fw-bold"><i class="bi bi-calendar-event me-2 text-primary"></i>Eventy rankingu</h6>
                                    <form method="POST" class="row g-2 align-items-end mb-3">
                                        <?php echo csrfTokenField('admin'); ?>
                                        <input type="hidden" name="action" value="start_ranking_event">
                                        <div class="col-md-7">
                                            <select name="template_id" class="form-select" required>
                                                <?php foreach ($rankingTemplates as $tpl): ?>
                                                    <option value="<?php echo (int)$tpl['id']; ?>"><?php echo htmlspecialchars($tpl['name']); ?> x<?php echo number_format((float)$tpl['multiplier'], 2); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" name="duration_days" class="form-control" min="7" max="30" value="7">
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-primary w-100"><i class="bi bi-play-fill"></i></button>
                                        </div>
                                    </form>
                                    <form method="POST" class="mb-3">
                                        <?php echo csrfTokenField('admin'); ?>
                                        <input type="hidden" name="action" value="finish_ranking_events">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill">Zakończ aktywne eventy</button>
                                    </form>
                                    <div class="vstack gap-2">
                                        <?php foreach ($rankingEvents as $event): ?>
                                            <div class="d-flex justify-content-between align-items-center gap-2 border rounded-3 p-2 small">
                                                <span><?php echo htmlspecialchars($event['name']); ?> <span class="text-muted">x<?php echo number_format((float)$event['multiplier'], 2); ?></span></span>
                                                <span class="badge bg-<?php echo ($event['status'] ?? '') === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($event['status']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6" id="admin-requests">
                                <div class="admin-tool-card p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <h6 class="fw-bold mb-1"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Wnioski i odpowiedzi</h6>
                                            <p class="small text-muted mb-0">Ostatnie wnioski nauczycieli oraz widoczna odpowiedź administratora.</p>
                                        </div>
                                        <a href="admin_requests.php" class="btn btn-sm btn-outline-primary rounded-pill">Otwórz</a>
                                    </div>
                                    <div class="vstack gap-2" style="max-height: 460px; overflow:auto;">
                                        <?php foreach ($adminRequests as $request): ?>
                                            <?php
                                                $requestStatus = (string)($request['status'] ?? 'sent');
                                                $requestStatusClass = match ($requestStatus) {
                                                    'sent' => 'bg-warning text-dark',
                                                    'read' => 'bg-info text-dark',
                                                    'replied' => 'bg-primary',
                                                    'closed' => 'bg-success',
                                                    default => 'bg-secondary',
                                                };
                                                $requestAuthor = trim((string)($request['first_name'] ?? '') . ' ' . (string)($request['last_name'] ?? ''));
                                                if ($requestAuthor === '') $requestAuthor = (string)($request['teacher_username'] ?? 'Użytkownik');
                                            ?>
                                            <div class="admin-request-card p-3" id="admin-request-<?php echo (int)$request['id']; ?>">
                                                <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                                                    <div>
                                                        <strong>#<?php echo (int)$request['id']; ?> <?php echo htmlspecialchars($request['subject'] ?? 'Wniosek'); ?></strong>
                                                        <div class="small text-muted">
                                                            <?php echo htmlspecialchars($requestAuthor); ?>
                                                            <?php if (!empty($request['type'])): ?> · <?php echo htmlspecialchars($request['type']); ?><?php endif; ?>
                                                            <?php if (!empty($request['created_at'])): ?> · <?php echo htmlspecialchars($request['created_at']); ?><?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <span class="badge <?php echo $requestStatusClass; ?>"><?php echo htmlspecialchars($requestStatus); ?></span>
                                                </div>
                                                <div class="small text-muted mb-2"><?php echo htmlspecialchars(mb_substr((string)($request['message'] ?? ''), 0, 180)); ?><?php echo mb_strlen((string)($request['message'] ?? ''), 'UTF-8') > 180 ? '...' : ''; ?></div>
                                                <?php if (!empty($request['admin_reply'])): ?>
                                                    <div class="admin-reply-preview small">
                                                        <strong>Odpowiedź admina:</strong>
                                                        <div><?php echo nl2br(htmlspecialchars(mb_substr((string)$request['admin_reply'], 0, 260))); ?><?php echo mb_strlen((string)$request['admin_reply'], 'UTF-8') > 260 ? '...' : ''; ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="small text-muted"><i class="bi bi-hourglass-split me-1"></i>Brak odpowiedzi.</div>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center gap-2 mt-2 small text-muted">
                                                    <span><?php echo (int)($request['reply_count'] ?? 0); ?> odpowiedzi w historii</span>
                                                    <a href="admin_requests.php#request-<?php echo (int)$request['id']; ?>" class="text-decoration-none fw-semibold">Szczegóły</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($adminRequests)): ?><p class="text-muted mb-0">Brak wniosków.</p><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="admin-tool-card p-3 h-100">
                                    <h6 class="fw-bold"><i class="bi bi-flag me-2 text-danger"></i>Zgłoszenia naruszeń</h6>
                                    <p class="small text-muted mb-3">Lista pokazuje wszystkie ostatnio pobrane zgłoszenia. Każdy wpis można obsłużyć albo usunąć osobno.</p>
                                    <div class="vstack gap-3" style="max-height: 460px; overflow:auto;">
                                        <?php foreach ($abuseReportsRecent as $report): ?>
                                            <form method="POST" class="admin-tool-card p-3 bg-light">
                                                <?php echo csrfTokenField('admin'); ?>
                                                <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                                <div class="d-flex justify-content-between gap-2 mb-2 flex-wrap">
                                                    <div>
                                                        <strong>#<?php echo (int)$report['id']; ?> <?php echo htmlspecialchars($report['report_type']); ?></strong>
                                                        <div class="small text-muted">
                                                            Zgłaszający:
                                                            <?php if (!empty($report['reporter_username'])): ?>
                                                                <a href="profile.php?id=<?php echo (int)$report['reporter_user_id']; ?>"><?php echo htmlspecialchars($report['reporter_username']); ?></a>
                                                                <span class="text-muted">(<?php echo htmlspecialchars($report['reporter_role'] ?? 'user'); ?>)</span>
                                                            <?php else: ?>
                                                                anonim / publiczne zgłoszenie
                                                            <?php endif; ?>
                                                            <?php if (!empty($report['reporter_email'])): ?>
                                                                · <?php echo htmlspecialchars($report['reporter_email']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-secondary align-self-start"><?php echo htmlspecialchars($report['status']); ?></span>
                                                </div>
                                                <?php if (!empty($report['content_url'])): ?>
                                                    <div class="small mb-2 text-break"><strong>Treść:</strong> <?php echo htmlspecialchars($report['content_url']); ?></div>
                                                <?php endif; ?>
                                                <div class="small mb-2"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
                                                <div class="small text-muted mb-2">
                                                    <?php echo htmlspecialchars($report['created_at'] ?? ''); ?>
                                                    <?php if (!empty($report['ip_address'])): ?> · IP: <?php echo htmlspecialchars($report['ip_address']); ?><?php endif; ?>
                                                    <?php if (!empty($report['handler_username'])): ?> · obsłużył: <?php echo htmlspecialchars($report['handler_username']); ?><?php endif; ?>
                                                </div>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <select name="report_status" class="form-select form-select-sm">
                                                        <?php foreach (['new','reviewing','resolved','rejected'] as $status): ?>
                                                            <option value="<?php echo $status; ?>" <?php echo $report['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input name="admin_note" class="form-control form-control-sm" maxlength="255" value="<?php echo htmlspecialchars($report['admin_note'] ?? ''); ?>" placeholder="Notatka">
                                                    <button class="btn btn-sm btn-primary" name="action" value="update_abuse_report">OK</button>
                                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete_abuse_report" data-admin-confirm="Usunąć zgłoszenie?"><i class="bi bi-trash3"></i></button>
                                                </div>
                                            </form>
                                        <?php endforeach; ?>
                                        <?php if (empty($abuseReports)): ?><p class="text-muted mb-0">Brak zgłoszeń.</p><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="admin-audit">
                                <div class="admin-tool-card p-3">
                                    <h6 class="fw-bold"><i class="bi bi-journal-text me-2 text-info"></i>Audyt akcji admina</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead><tr><th>Czas</th><th>Admin</th><th>Akcja</th><th>Cel</th><th>Szczegóły</th><th class="text-end">Akcje</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($auditLog as $log): ?>
                                                <tr>
                                                    <td class="small text-muted"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['admin_username'] ?? 'system'); ?></td>
                                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                                    <td class="small"><?php echo htmlspecialchars(($log['target_type'] ?? '-') . ' #' . ($log['target_id'] ?? '-')); ?></td>
                                                    <td class="small text-muted"><?php echo htmlspecialchars(mb_substr($log['details'] ?? '', 0, 120)); ?></td>
                                                    <td class="admin-audit-actions text-end">
                                                        <form method="POST" data-admin-confirm="Usunąć pojedynczy wpis audytu?">
                                                            <?php echo csrfTokenField('admin'); ?>
                                                            <input type="hidden" name="action" value="delete_admin_audit_entry">
                                                            <input type="hidden" name="audit_id" value="<?php echo (int)$log['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Usuń wpis audytu"><i class="bi bi-trash3"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($auditLog)): ?>
                                                <tr><td colspan="6" class="text-muted small">Brak wpisów audytu.</td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        function cleanupAdminModalArtifacts() {
            if (document.querySelector('.modal.show')) return;
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        document.querySelectorAll('.modal').forEach((modal) => {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.addEventListener('hidden.bs.modal', cleanupAdminModalArtifacts);
        });
        document.addEventListener('hidden.bs.modal', cleanupAdminModalArtifacts, true);

        function bindAdminUserModal(selector, formSelector) {
            const form = document.querySelector(formSelector);
            if (!form) return;
            document.querySelectorAll(selector).forEach((btn) => {
                btn.addEventListener('click', () => {
                    form.querySelector('input[name="user_id"]').value = btn.dataset.userId || '';
                    form.querySelectorAll('[data-admin-modal-user]').forEach((node) => {
                        node.textContent = btn.dataset.userLabel || 'użytkownik';
                    });
                });
            });
        }

        bindAdminUserModal('[data-admin-reset-user]', '#adminPasswordForm');
        bindAdminUserModal('[data-admin-ban-user]', '#adminBanForm');
        bindAdminUserModal('[data-admin-delete-user]', '#adminDeleteForm');

        const adminConfirmEl = document.getElementById('adminConfirmModal');
        const adminConfirmMessage = document.getElementById('adminConfirmMessage');
        const adminConfirmSubmit = document.getElementById('adminConfirmSubmit');
        const adminConfirmDialog = adminConfirmEl ? new bootstrap.Modal(adminConfirmEl) : null;
        let adminPendingAction = null;

        function openAdminConfirm(message, callback) {
            if (!adminConfirmDialog) {
                callback();
                return;
            }
            adminConfirmMessage.textContent = message || 'Potwierdzić akcję?';
            adminPendingAction = callback;
            adminConfirmDialog.show();
        }

        adminConfirmSubmit?.addEventListener('click', () => {
            const callback = adminPendingAction;
            adminPendingAction = null;
            adminConfirmDialog?.hide();
            if (callback) callback();
        });

        document.querySelectorAll('form[data-admin-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.adminConfirmed === '1') {
                    return;
                }
                event.preventDefault();
                openAdminConfirm(form.dataset.adminConfirm, () => {
                    form.dataset.adminConfirmed = '1';
                    form.submit();
                });
            });
        });

        document.querySelectorAll('button[data-admin-confirm]').forEach((button) => {
            button.addEventListener('click', (event) => {
                if (button.dataset.adminConfirmed === '1') {
                    return;
                }
                event.preventDefault();
                openAdminConfirm(button.dataset.adminConfirm, () => {
                    button.dataset.adminConfirmed = '1';
                    if (button.form) {
                        if (typeof button.form.requestSubmit === 'function') {
                            button.form.requestSubmit(button);
                        } else {
                            if (button.name) {
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = button.name;
                                hidden.value = button.value;
                                button.form.appendChild(hidden);
                            }
                            button.form.submit();
                        }
                    }
                });
            });
        });

        const rankEditorSelect = document.getElementById('rankEditorSelect');
        rankEditorSelect?.addEventListener('change', () => {
            document.querySelectorAll('[data-rank-panel]').forEach(panel => {
                panel.classList.toggle('active', panel.dataset.rankPanel === rankEditorSelect.value);
            });
        });

    </script>
</body>
</html>
 
