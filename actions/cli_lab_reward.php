<?php
/**
 * CLI Lab Reward Action
 *
 * Awards XP to user for completing CKE CLI scenarios and saves progress.
 * POST {scenario_id, os, csrf_token}
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();

// Auth guard
if (!isset($_SESSION['user_id'])) {
    securitySendJson(['success' => false, 'error' => 'unauthorized', 'message' => 'Musisz być zalogowany, aby zdobywać punkty XP.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    securitySendJson(['success' => false, 'error' => 'method_not_allowed'], 405);
}

$userId = (int)$_SESSION['user_id'];
$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));

// CSRF validation
if (!validateCsrfToken($csrfToken, 'cli_lab')) {
    securitySendJson(['success' => false, 'error' => 'invalid_csrf', 'message' => 'Nieprawidłowy token bezpieczeństwa CSRF. Odśwież stronę.'], 403);
}

$scenarioId = trim((string)($_POST['scenario_id'] ?? ''));
$os = trim((string)($_POST['os'] ?? 'linux'));

// Valid scenario registry with XP amounts
$scenarioRegistry = [
    'inf02_ip_diag' => ['title' => 'Pełna diagnostyka interfejsu sieciowego', 'xp' => 20],
    'inf02_static_ip' => ['title' => 'Konfiguracja statycznego IP i bramy', 'xp' => 25],
    'inf02_apache_vhost' => ['title' => 'Instalacja i konfiguracja VirtualHost Apache2', 'xp' => 40],
    'inf02_dns_zone' => ['title' => 'Konfiguracja strefy domeny w BIND9 DNS', 'xp' => 40],
    'inf02_samba_share' => ['title' => 'Udostępnianie zasobu w sieci przez Sambę', 'xp' => 35],
    'inf02_dhcp_server' => ['title' => 'Konfiguracja serwera ISC-DHCP', 'xp' => 30],
    'inf08_iptables_drop' => ['title' => 'Konfiguracja reguł filtrowania w iptables', 'xp' => 30],
    'inf08_ufw_secure' => ['title' => 'Zarządzanie zaporą UFW', 'xp' => 20],
    'inf08_win_firewall' => ['title' => 'Konfiguracja Zapory Windows Defender (netsh)', 'xp' => 25],
    'inf02_diskpart_vol' => ['title' => 'Zarządzanie woluminami w Windows DiskPart', 'xp' => 30],
    'inf03_mysql_db' => ['title' => 'Zarządzanie relacyjną bazą danych MySQL', 'xp' => 30],
    'inf02_chmod_file' => ['title' => 'Zarządzanie uprawnieniami (chmod 750) i właścicielem', 'xp' => 20],
    'inf02_user_mgmt' => ['title' => 'Tworzenie kont użytkowników i grup w systemie', 'xp' => 20],
    'inf02_win_iis' => ['title' => 'Zarządzanie serwerem IIS w Windows (appcmd)', 'xp' => 30],
    'inf02_vsftpd_setup' => ['title' => 'Konfiguracja serwera FTP vsftpd', 'xp' => 35],
    'inf02_postfix_mail' => ['title' => 'Instalacja i konfiguracja serwera pocztowego Postfix', 'xp' => 35],
    'inf02_nfs_exports' => ['title' => 'Konfiguracja zasobów sieciowych NFS exports', 'xp' => 30],
    'inf02_win_powershell_diag' => ['title' => 'Diagnostyka sieci i usług w PowerShell', 'xp' => 25],
    'inf02_crontab_backup' => ['title' => 'Automatyzacja kopii zapasowych w cronie', 'xp' => 25],
    'inf08_ssh_hardening' => ['title' => 'Zabezpieczenie serwera SSH (sshd_config)', 'xp' => 30],
    'inf02_lvm_volumes' => ['title' => 'Konfiguracja woluminów logicznych LVM', 'xp' => 40],
    'inf02_raid1_mdadm' => ['title' => 'Tworzenie macierzy dyskowej RAID 1 w mdadm', 'xp' => 40],
    'inf02_ps_dhcp_dns' => ['title' => 'PowerShell: Zarządzanie rolami DHCP i DNS w Windows', 'xp' => 35],
    'inf03_mysql_adv_grant' => ['title' => 'MySQL: Relacje tabel, klucze obce i uprawnienia GRANT', 'xp' => 35],
    'inf03_mysqldump_backup' => ['title' => 'Wykonywanie i przywracanie kopii bazy (mysqldump)', 'xp' => 30],
    'inf08_ssh_hardened_cfg' => ['title' => 'Zaawansowane utwardzanie serwera OpenSSH', 'xp' => 35],
    'inf08_iptables_nat_portfwd' => ['title' => 'iptables: Konfiguracja reguł NAT i Port Forwardingu', 'xp' => 40],
    'inf08_fail2ban_setup' => ['title' => 'Ochrona przed atakami brute-force w Fail2ban', 'xp' => 35],
    'inf02_ad_dsadd_mgmt' => ['title' => 'Zarządzanie Active Directory (dsadd / PowerShell)', 'xp' => 35],
    'inf02_route_diag_traceroute' => ['title' => 'Diagnostyka routingu sieciowego i trasowania pakietów', 'xp' => 30],
];

if (!isset($scenarioRegistry[$scenarioId])) {
    securitySendJson(['success' => false, 'error' => 'invalid_scenario', 'message' => 'Nieznany identyfikator zadania.'], 400);
}

$scenarioMeta = $scenarioRegistry[$scenarioId];
$xpReward = (int)$scenarioMeta['xp'];
$scenarioTitle = (string)$scenarioMeta['title'];

// Ensure completions table exists
if (function_exists('appRuntimeSchemaUpdatesEnabled') && appRuntimeSchemaUpdatesEnabled()) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cli_lab_completions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scenario_id VARCHAR(64) NOT NULL,
            os VARCHAR(16) NOT NULL,
            xp_awarded INT NOT NULL,
            completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_scenario (user_id, scenario_id),
            INDEX idx_user_completions (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        error_log('cli_lab_completions table check failed: ' . $e->getMessage());
    }
}

// Check if user already completed this scenario
$isFirstTime = true;
try {
    $checkStmt = $pdo->prepare("SELECT id, xp_awarded, completed_at FROM cli_lab_completions WHERE user_id = ? AND scenario_id = ?");
    $checkStmt->execute([$userId, $scenarioId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $isFirstTime = false;
    }
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), "doesn't exist") || $e->getCode() === '42S02') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cli_lab_completions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                scenario_id VARCHAR(64) NOT NULL,
                os VARCHAR(16) NOT NULL,
                xp_awarded INT NOT NULL,
                completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_scenario (user_id, scenario_id),
                INDEX idx_user_completions (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $ignore) {}
    } else {
        error_log('cli_lab_completions lookup failed: ' . $e->getMessage());
    }
}

// Fetch current user XP
$userStmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentXp = (int)$userStmt->fetchColumn();

if ($isFirstTime) {
    // Record completion
    try {
        $insertStmt = $pdo->prepare("INSERT INTO cli_lab_completions (user_id, scenario_id, os, xp_awarded) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$userId, $scenarioId, $os, $xpReward]);
    } catch (PDOException $e) {
        error_log('cli_lab_completions insert failed: ' . $e->getMessage());
    }

    // Award XP
    awardXp($pdo, $userId, $xpReward, 'cli_lab', null, "CLI Lab: Ukończono zadanie $scenarioTitle");

    // Fetch updated XP
    $userStmt->execute([$userId]);
    $newXp = (int)$userStmt->fetchColumn();
    $rankInfo = getRankInfoByXp($newXp);

    securitySendJson([
        'success' => true,
        'is_first_time' => true,
        'xp_earned' => $xpReward,
        'total_xp' => $newXp,
        'rank' => $rankInfo['name'],
        'rank_icon' => $rankInfo['icon'],
        'message' => "Przyznano +{$xpReward} XP za ukończenie zadania '{$scenarioTitle}'!"
    ]);
} else {
    $rankInfo = getRankInfoByXp($currentXp);
    securitySendJson([
        'success' => true,
        'is_first_time' => false,
        'xp_earned' => 0,
        'total_xp' => $currentXp,
        'rank' => $rankInfo['name'],
        'rank_icon' => $rankInfo['icon'],
        'message' => "Zadanie '{$scenarioTitle}' zostało już wcześniej ukończone."
    ]);
}
