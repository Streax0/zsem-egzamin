<?php
/**
 * Helper functions library
 * Provides utility functions for the quiz application
 */

// ============================================
// General Utility Functions
// ============================================

/**
 * Sanitize user input data
 * @param string $data Raw input data
 * @return string Sanitized string
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validatePasswordPolicy(string $password): array {
    $errors = [];
    if (mb_strlen($password, '8bit') < 6) {
        $errors[] = 'Hasło musi mieć minimum 6 znaków.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Hasło musi zawierać małą literę.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Hasło musi zawierać wielką literę.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Hasło musi zawierać cyfrę.';
    }
    $weak = ['password', 'haslo', 'qwerty', '123456', 'zsemtech'];
    $lower = mb_strtolower($password, 'UTF-8');
    foreach ($weak as $fragment) {
        if (str_contains($lower, $fragment)) {
            $errors[] = 'Hasło nie może zawierać popularnych fraz.';
            break;
        }
    }
    return $errors;
}

function registrationUsernameSlug(string $value, string $fallback = 'uczen'): string {
    $raw = trim($value);
    $map = [
        'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
        'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z'
    ];
    $raw = strtr($raw, $map);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if ($converted !== false) {
            $raw = $converted;
        }
    }

    $slug = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', $raw));
    $slug = trim((string)preg_replace('/-+/', '-', $slug), '-');
    if (strlen($slug) < 2 || (function_exists('containsProfanity') && containsProfanity($slug))) {
        $slug = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', $fallback));
        $slug = trim((string)preg_replace('/-+/', '-', $slug), '-');
    }

    return $slug !== '' ? $slug : 'uczen';
}

function registrationUsernameSuggestions(PDO $pdo, string $seed, int $count = 2): array {
    $count = max(1, min(5, $count));
    $raw = trim($seed);
    $map = [
        'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
        'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z'
    ];
    $raw = strtr($raw, $map);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if ($converted !== false) {
            $raw = $converted;
        }
    }

    $base = strtolower((string)preg_replace('/[^A-Za-z0-9_.-]+/', '', $raw));
    $base = trim((string)preg_replace('/[._-]{2,}/', '_', $base), '._-');
    if (strlen($base) < 3 || (function_exists('containsProfanity') && containsProfanity($base))) {
        $base = 'user';
    }
    $base = substr($base, 0, 10);

    $suggestions = [];
    for ($i = 0; count($suggestions) < $count && $i < 40; $i++) {
        $suffix = $i < 8 ? (string)random_int(10, 99) : bin2hex(secureRandomBytes(2));
        $candidate = substr($base, 0, 16 - strlen($suffix)) . $suffix;
        if (!preg_match('/^[A-Za-z0-9_.-]{3,16}$/', $candidate)) {
            continue;
        }
        if (function_exists('containsProfanity') && containsProfanity($candidate)) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn() && !in_array($candidate, $suggestions, true)) {
            $suggestions[] = $candidate;
        }
    }

    return $suggestions;
}

function adminRoleValues(): array {
    return ['admin', 'dyrektor'];
}

function teacherPanelRoleValues(): array {
    return ['teacher', 'admin', 'dyrektor'];
}

function privilegedStaffRoles(): array {
    return ['admin', 'dyrektor', 'teacher'];
}

function assignableRoleValues(): array {
    return ['user', 'teacher', 'admin', 'dyrektor', 'wujek_luki'];
}

function rankingEligibleRoles(): array {
    return ['user', 'wujek_luki'];
}

function roleParticipatesInRanking($role): bool {
    return in_array((string)$role, rankingEligibleRoles(), true);
}

function roleHasAdminAccess($role): bool {
    return in_array((string)$role, adminRoleValues(), true);
}

function roleHasTeacherPanelAccess($role): bool {
    return in_array((string)$role, teacherPanelRoleValues(), true);
}

function roleBypassesSocialPrivacy($role): bool {
    return in_array((string)$role, privilegedStaffRoles(), true);
}

/**
 * Determine whether a friend request can be sent based on sender and target roles.
 *
 * Normal users may not send requests to staff accounts.
 * Staff roles bypass target friend request privacy.
 */
function canSendFriendRequest($senderRole, $targetRole, $targetAllowsRequests = true) {
    if (!roleBypassesSocialPrivacy($senderRole) && in_array((string)$targetRole, privilegedStaffRoles(), true)) {
        return false;
    }

    if (!$targetAllowsRequests && !roleBypassesSocialPrivacy($senderRole)) {
        return false;
    }

    return true;
}

function getUserRoleBadge(string $role): array {
    $role = trim(strtolower($role));
    $map = [
        'admin' => ['label' => 'Administrator', 'class' => 'bg-danger text-white'],
        'dyrektor' => ['label' => 'Dyrektor', 'class' => 'bg-dark text-white'],
        'teacher' => ['label' => 'Nauczyciel', 'class' => 'bg-info text-white'],
        'wujek_luki' => ['label' => 'Wujek Luki', 'class' => 'bg-warning text-dark'],
        'user' => ['label' => 'Użytkownik', 'class' => 'bg-primary text-white'],
    ];

    if (isset($map[$role])) {
        return $map[$role];
    }

    $label = str_replace('_', ' ', $role);
    $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    return ['label' => $label ?: 'Użytkownik', 'class' => 'bg-secondary text-white'];
}

function userDisplayName(array $user): string {
    $fullName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
    return $fullName !== '' ? $fullName : (string)($user['username'] ?? 'Użytkownik');
}

function userHandle(array $user): string {
    $username = trim((string)($user['username'] ?? ''));
    return $username !== '' ? '@' . $username : '';
}

function dbIdentifier(string $name): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    return '`' . $name . '`';
}

function &dbRequestRuntimeCache(): array {
    static $cache = [];
    return $cache;
}

function dbSchemaNameMap(array $names): array {
    $map = [];
    foreach ($names as $name) {
        $name = (string)$name;
        if ($name !== '') $map[$name] = true;
    }
    return $map;
}

function dbSchemaTables(PDO $pdo): ?array {
    $cache =& dbRequestRuntimeCache();
    $cacheKey = 'schema_tables:' . spl_object_id($pdo);
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    try {
        $stmt = $pdo->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        if ($stmt === false) return null;
        return $cache[$cacheKey] = dbSchemaNameMap($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return null;
    }
}

function dbSchemaColumns(PDO $pdo, string $table): ?array {
    dbIdentifier($table);
    $cache =& dbRequestRuntimeCache();
    $cacheKey = 'schema_columns:' . spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    try {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return $cache[$cacheKey] = dbSchemaNameMap($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return null;
    }
}

function dbSchemaIndexes(PDO $pdo, string $table): ?array {
    dbIdentifier($table);
    $cache =& dbRequestRuntimeCache();
    $cacheKey = 'schema_indexes:' . spl_object_id($pdo) . ':' . $table;
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];

    try {
        $stmt = $pdo->prepare('SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return $cache[$cacheKey] = dbSchemaNameMap($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return null;
    }
}

function dbRememberSchemaObject(PDO $pdo, string $type, string $table, string $name): void {
    $cache =& dbRequestRuntimeCache();
    $cacheKey = 'schema_' . $type . ':' . spl_object_id($pdo) . ':' . $table;
    if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
        $cache[$cacheKey][$name] = true;
    }
}

function dbTableExists(PDO $pdo, string $table): bool {
    dbIdentifier($table);
    $tables = dbSchemaTables($pdo);
    if ($tables === null) return false;
    $exists = isset($tables[$table]);
    if (!$exists && dbRuntimeSchemaUpdatesEnabled()) {
        $cache =& dbRequestRuntimeCache();
        unset($cache['schema_tables:' . spl_object_id($pdo)]);
    }
    return $exists;
}

function dbColumnExists(PDO $pdo, string $table, string $column): bool {
    dbIdentifier($column);
    $columns = dbSchemaColumns($pdo, $table);
    return $columns !== null && isset($columns[$column]);
}

function dbIndexExists(PDO $pdo, string $table, string $index): bool {
    dbIdentifier($index);
    $indexes = dbSchemaIndexes($pdo, $table);
    return $indexes !== null && isset($indexes[$index]);
}

function dbRuntimeSchemaUpdatesEnabled(): bool {
    return function_exists('appRuntimeSchemaUpdatesEnabled') && appRuntimeSchemaUpdatesEnabled();
}

function dbAddColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    if (!dbRuntimeSchemaUpdatesEnabled()) return;

    try {
        if (!dbColumnExists($pdo, $table, $column)) {
            $pdo->exec('ALTER TABLE ' . dbIdentifier($table) . ' ADD COLUMN ' . dbIdentifier($column) . ' ' . $definition);
            dbRememberSchemaObject($pdo, 'columns', $table, $column);
        }
    } catch (Throwable $e) {
        error_log("Failed to add column {$column} to table {$table}: " . $e->getMessage());
    }
}

function dbAddIndexIfMissing(PDO $pdo, string $table, string $index, string $definition): void {
    if (!dbRuntimeSchemaUpdatesEnabled()) return;

    try {
        if (!dbIndexExists($pdo, $table, $index)) {
            $pdo->exec('ALTER TABLE ' . dbIdentifier($table) . ' ADD INDEX ' . dbIdentifier($index) . ' ' . $definition);
            dbRememberSchemaObject($pdo, 'indexes', $table, $index);
        }
    } catch (Throwable $e) {
        error_log("Failed to add index {$index} to table {$table}: " . $e->getMessage());
    }
}

function clientIpAddress(): string {
    if (function_exists('securityClientIp')) {
        $ip = securityClientIp();
        return $ip === '0.0.0.0' ? 'unknown' : $ip;
    }

    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
}

function consumeRateLimit(PDO $pdo, string $bucket, string $identity, int $maxAttempts, int $windowSeconds): bool {
    try {
        ensurePlatformEnhancements($pdo);
        $bucket = mb_substr(preg_replace('/[^a-z0-9_.:-]/i', '', $bucket), 0, 80) ?: 'default';
        $identityHash = hash('sha256', mb_strtolower(trim($identity ?: clientIpAddress()), 'UTF-8'));
        $windowSeconds = max(60, min(86400, $windowSeconds));
        $maxAttempts = max(1, min(500, $maxAttempts));

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM rate_limit_events
            WHERE bucket = ? AND identity_hash = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL {$windowSeconds} SECOND)
        ");
        $stmt->execute([$bucket, $identityHash]);
        if ((int)$stmt->fetchColumn() >= $maxAttempts) {
            return false;
        }

        $stmt = $pdo->prepare("INSERT INTO rate_limit_events (bucket, identity_hash, ip_address, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$bucket, $identityHash, mb_substr(clientIpAddress(), 0, 45)]);
        if (random_int(1, 100) === 1) {
            $pdo->exec("DELETE FROM rate_limit_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
        }
        return true;
    } catch (PDOException $e) {
        error_log('Rate limit check failed: ' . $e->getMessage());
        return true;
    }
}

function seedRankingEventTemplates(PDO $pdo): void {
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ranking_event_templates')->fetchColumn();
        if ($count >= 50) return;

        $themes = [
            ['summer-code', 'Letni Kod', 'Szybszy progres za systematyczne testy w letnim sprincie.'],
            ['winter-network', 'Zimowa Siec', 'Wydarzenie dla osób ćwiczących systemy i sieci.'],
            ['autumn-debug', 'Jesienny Debug', 'Premia za poprawianie słabszych obszarów.'],
            ['spring-stack', 'Wiosenny Stack', 'Premia za regularne rozwiązywanie arkuszy.'],
            ['zsem-tech-fest', 'ZSEM Tech Fest', 'Szkolny festiwal wiedzy technicznej.'],
            ['programming-fest', 'Programistyczny Sprint', 'Tydzień algorytmów, PHP i baz danych.'],
            ['network-fest', 'Festiwal Sieci', 'Wydarzenie dla konfiguracji, usług i bezpieczeństwa.'],
            ['exam-boost', 'Przedegzaminacyjny Boost', 'Premia za pełne testy rankingowe.'],
            ['sql-lab', 'SQL Lab', 'Wydarzenie dla zapytań i modelowania danych.'],
            ['security-week', 'Security Week', 'Premia za naukę zabezpieczeń.'],
            ['hardware-lab', 'Hardware Lab', 'Wydarzenie dla urządzeń i diagnostyki.'],
            ['web-week', 'Web Week', 'Premia za HTML, CSS, JS i backend.']
        ];
        $variants = [
            ['Tydzień', 7, 1.10],
            ['Sprint', 7, 1.20],
            ['Maraton', 14, 1.25],
            ['Sezon', 30, 1.35],
            ['Masterclass', 14, 1.50]
        ];

        $stmt = $pdo->prepare('
            INSERT IGNORE INTO ranking_event_templates
                (slug, name, description, multiplier, duration_days, season, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ');
        foreach ($themes as $theme) {
            foreach ($variants as $variant) {
                $slug = $theme[0] . '-' . strtolower($variant[0]);
                $slug = strtr($slug, ['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ż'=>'z','ź'=>'z']);
                $stmt->execute([
                    $slug,
                    $variant[0] . ' ' . $theme[1],
                    $theme[2],
                    $variant[2],
                    $variant[1],
                    $theme[0]
                ]);
            }
        }
    } catch (PDOException $e) {
        error_log('Ranking event seed failed: ' . $e->getMessage());
    }
}

function ensurePlatformEnhancements(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    if (!dbRuntimeSchemaUpdatesEnabled()) {
        $done = true;
        return;
    }

    _ensurePlatformUsers($pdo);
    _ensurePlatformTests($pdo);
    _ensurePlatformSocial($pdo);
    _ensurePlatformExams($pdo);
    _ensurePlatformSystem($pdo);
    _ensurePlatformSecurity($pdo);
    _ensurePlatformRanking($pdo);
    _ensurePlatformAdmin($pdo);

    $done = true;
}

function _ensurePlatformUsers(PDO $pdo): void {
    // 1. Users table
    try {
        if (dbTableExists($pdo, 'users')) {
            try {
                $roleColumn = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
                if ($roleColumn && !str_contains((string)$roleColumn, "'dyrektor'")) {
                    $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','teacher','admin','dyrektor','wujek_luki') DEFAULT 'user'");
                }
            } catch (PDOException $e) {
                error_log('Role enum migration skipped: ' . $e->getMessage());
            }
            dbAddColumnIfMissing($pdo, 'users', 'first_name', "VARCHAR(50) DEFAULT NULL AFTER role");
            dbAddColumnIfMissing($pdo, 'users', 'last_name', "VARCHAR(50) DEFAULT NULL AFTER first_name");
            dbAddColumnIfMissing($pdo, 'users', 'ranking_visible', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified");
            dbAddColumnIfMissing($pdo, 'users', 'verified_at', "DATETIME DEFAULT NULL AFTER is_verified");
            dbAddColumnIfMissing($pdo, 'users', 'verified_by_admin_id', "INT DEFAULT NULL AFTER verified_at");
            dbAddColumnIfMissing($pdo, 'users', 'avatar_path', "VARCHAR(255) DEFAULT NULL AFTER bio");
            dbAddColumnIfMissing($pdo, 'users', 'avatar_changed_at', "DATETIME DEFAULT NULL AFTER avatar_path");
            dbAddColumnIfMissing($pdo, 'users', 'ban_expires_at', "DATETIME DEFAULT NULL AFTER is_banned");
            dbAddColumnIfMissing($pdo, 'users', 'trust_status', "VARCHAR(30) NOT NULL DEFAULT 'trusted' AFTER is_banned");
            dbAddColumnIfMissing($pdo, 'users', 'risk_flags', "TEXT DEFAULT NULL AFTER trust_status");
            dbAddColumnIfMissing($pdo, 'users', 'registration_ip', "VARCHAR(45) DEFAULT NULL AFTER risk_flags");
            dbAddColumnIfMissing($pdo, 'users', 'show_missions', "TINYINT(1) DEFAULT 1 AFTER searchable");
            dbAddColumnIfMissing($pdo, 'users', 'show_online_status', "TINYINT(1) DEFAULT 1 AFTER show_missions");
            dbAddColumnIfMissing($pdo, 'users', 'show_recent_activity', "TINYINT(1) DEFAULT 1 AFTER show_online_status");
            dbAddColumnIfMissing($pdo, 'users', 'session_version', "INT NOT NULL DEFAULT 1 AFTER last_activity");
            dbAddIndexIfMissing($pdo, 'users', 'idx_role_xp_activity', '(role, xp, last_activity)');
            dbAddIndexIfMissing($pdo, 'users', 'idx_trust_status', '(trust_status)');
            dbAddIndexIfMissing($pdo, 'users', 'idx_registration_ip', '(registration_ip)');
            dbAddIndexIfMissing($pdo, 'users', 'idx_ban_expiry', '(is_banned, ban_expires_at)');
            try {
                $pdo->exec("ALTER TABLE users MODIFY xp INT DEFAULT 4100");
            } catch (PDOException $e) {
                error_log('XP default migration skipped: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Users table enhancements failed: ' . $e->getMessage());
    }
}

function _ensurePlatformTests(PDO $pdo): void {
    // 2. Test results table
    try {
        if (dbTableExists($pdo, 'test_results')) {
            $modeColumn = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'test_results' AND COLUMN_NAME = 'mode'")->fetchColumn();
            if ($modeColumn && !str_contains((string)$modeColumn, "'exam_simulator'")) {
                $pdo->exec("ALTER TABLE test_results MODIFY mode ENUM('exam','practice','single','exam_simulator') DEFAULT 'exam'");
            }
            dbAddColumnIfMissing($pdo, 'test_results', 'exclude_from_ranking', "TINYINT(1) DEFAULT 0 AFTER mode");
            dbAddIndexIfMissing($pdo, 'test_results', 'idx_user_test_date', '(user_id, test_date)');
            dbAddIndexIfMissing($pdo, 'test_results', 'idx_ranking_tests', '(total_questions, exclude_from_ranking)');
            dbAddIndexIfMissing($pdo, 'test_results', 'idx_user_mode_date', '(user_id, mode, test_date)');
        }
    } catch (Throwable $e) {
        error_log('Test results enhancements failed: ' . $e->getMessage());
    }

    // 2b. Test answers and progress lookup indexes
    try {
        if (dbTableExists($pdo, 'test_answers')) {
            dbAddIndexIfMissing($pdo, 'test_answers', 'idx_question_correct', '(question_id, is_correct)');
            dbAddIndexIfMissing($pdo, 'test_answers', 'idx_result_question', '(result_id, question_id)');
        }
        if (dbTableExists($pdo, 'user_question_progress')) {
            dbAddIndexIfMissing($pdo, 'user_question_progress', 'idx_user_mastered_last', '(user_id, is_mastered, last_seen)');
            dbAddIndexIfMissing($pdo, 'user_question_progress', 'idx_question_mastered', '(question_id, is_mastered)');
        }
    } catch (Throwable $e) {
        error_log('Test answer/progress index enhancements failed: ' . $e->getMessage());
    }
}

function _ensurePlatformSocial(PDO $pdo): void {
    // 3. Notifications table
    try {
        if (dbTableExists($pdo, 'notifications')) {
            dbAddColumnIfMissing($pdo, 'notifications', 'dedupe_key', "VARCHAR(160) DEFAULT NULL AFTER message");
            dbAddColumnIfMissing($pdo, 'notifications', 'action_url', "VARCHAR(500) DEFAULT NULL AFTER dedupe_key");
            dbAddIndexIfMissing($pdo, 'notifications', 'idx_user_unread_created', '(user_id, is_read, created_at)');
            dbAddIndexIfMissing($pdo, 'notifications', 'idx_user_dedupe', '(user_id, dedupe_key, created_at)');
        }
    } catch (Throwable $e) {
        error_log('Notifications enhancements failed: ' . $e->getMessage());
    }

    // 4. XP events table
    try {
        if (dbTableExists($pdo, 'xp_events')) {
            dbAddIndexIfMissing($pdo, 'xp_events', 'idx_user_created_perf', '(user_id, created_at)');
        }
    } catch (Throwable $e) {
        error_log('XP events enhancements failed: ' . $e->getMessage());
    }

    // 5. Friends table
    try {
        if (dbTableExists($pdo, 'friends')) {
            dbAddIndexIfMissing($pdo, 'friends', 'idx_friend_lookup', '(user_id, friend_id, status)');
            dbAddIndexIfMissing($pdo, 'friends', 'idx_friend_reverse', '(friend_id, user_id, status)');
        }
    } catch (Throwable $e) {
        error_log('Friends enhancements failed: ' . $e->getMessage());
    }

    // 6. User social links platform enum modification
    try {
        if (dbTableExists($pdo, 'user_social_links')) {
            $socialColumn = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_social_links' AND COLUMN_NAME = 'platform'")->fetchColumn();
            if ($socialColumn && !str_contains((string)$socialColumn, "'youtube'")) {
                $pdo->exec("ALTER TABLE user_social_links MODIFY platform ENUM('github','linkedin','instagram','youtube','facebook','x','tiktok','gitlab') NOT NULL");
            }
        }
    } catch (Throwable $e) {
        error_log('user_social_links enhancements failed: ' . $e->getMessage());
    }
}

function _ensurePlatformExams(PDO $pdo): void {
    // 7. Exams table
    try {
        if (dbTableExists($pdo, 'exams')) {
            dbAddColumnIfMissing($pdo, 'exams', 'navigation_mode', "VARCHAR(30) NOT NULL DEFAULT 'free' AFTER max_attempts");
            dbAddColumnIfMissing($pdo, 'exams', 'allow_answer_changes', "TINYINT(1) NOT NULL DEFAULT 1 AFTER navigation_mode");
            dbAddColumnIfMissing($pdo, 'exams', 'warning_limit', "TINYINT UNSIGNED DEFAULT NULL AFTER allow_answer_changes");
            dbAddColumnIfMissing($pdo, 'exams', 'warning_action', "VARCHAR(30) NOT NULL DEFAULT 'notify' AFTER warning_limit");
            dbAddColumnIfMissing($pdo, 'exams', 'late_join_cutoff_minutes', "TINYINT UNSIGNED DEFAULT NULL AFTER warning_action");
            dbAddColumnIfMissing($pdo, 'exams', 'results_available_at', "DATETIME DEFAULT NULL AFTER late_join_cutoff_minutes");
            dbAddColumnIfMissing($pdo, 'exams', 'print_include_answer_key', "TINYINT(1) NOT NULL DEFAULT 0 AFTER results_available_at");
        }
    } catch (Throwable $e) {
        error_log('Exams enhancements failed: ' . $e->getMessage());
    }

    // 8. Exam session questions table
    try {
        if (dbTableExists($pdo, 'exam_session_questions')) {
            dbAddColumnIfMissing($pdo, 'exam_session_questions', 'correct_answer_override', "CHAR(1) CHARACTER SET ascii DEFAULT NULL AFTER question_order");
            dbAddColumnIfMissing($pdo, 'exam_session_questions', 'override_reason', "VARCHAR(255) DEFAULT NULL AFTER correct_answer_override");
            dbAddColumnIfMissing($pdo, 'exam_session_questions', 'override_by', "INT DEFAULT NULL AFTER override_reason");
            dbAddColumnIfMissing($pdo, 'exam_session_questions', 'override_at', "DATETIME DEFAULT NULL AFTER override_by");
            dbAddIndexIfMissing($pdo, 'exam_session_questions', 'idx_session_question', '(session_id, question_id)');
            dbAddIndexIfMissing($pdo, 'exam_session_questions', 'idx_session_order', '(session_id, question_order)');
        }
    } catch (Throwable $e) {
        error_log('Exam session questions enhancements failed: ' . $e->getMessage());
    }

    // 8b. Exam participant and answer lookup indexes
    try {
        if (dbTableExists($pdo, 'exam_participants')) {
            dbAddIndexIfMissing($pdo, 'exam_participants', 'idx_session_user_status', '(session_id, user_id, status)');
            dbAddIndexIfMissing($pdo, 'exam_participants', 'idx_session_status_joined', '(session_id, status, joined_at)');
        }
        if (dbTableExists($pdo, 'exam_answers')) {
            dbAddIndexIfMissing($pdo, 'exam_answers', 'idx_session_participant', '(session_id, participant_id)');
            dbAddIndexIfMissing($pdo, 'exam_answers', 'idx_participant_order', '(participant_id, question_order)');
        }
    } catch (Throwable $e) {
        error_log('Exam participant/answer index enhancements failed: ' . $e->getMessage());
    }
}

function _ensurePlatformSystem(PDO $pdo): void {
    // 9. App Settings table and initialization
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $flag = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'teacher_ranking_initialized' LIMIT 1");
        $flag->execute();
        if ($flag->fetchColumn() === false && dbColumnExists($pdo, 'users', 'ranking_visible')) {
            $pdo->exec("UPDATE users SET ranking_visible = 0 WHERE role = 'teacher'");
            $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('teacher_ranking_initialized', '1')");
        }
        
        $privacyFlag = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'privileged_roles_private_initialized' LIMIT 1");
        $privacyFlag->execute();
        if ($privacyFlag->fetchColumn() === false && dbTableExists($pdo, 'users')) {
            $pdo->exec("UPDATE users SET profile_public = 0, stats_public = 0, searchable = 0, allow_friend_requests = 0 WHERE role IN ('admin','teacher','dyrektor')");
            $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('privileged_roles_private_initialized', '1')");
        }
        
        $directorPrivacyFlag = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'director_privacy_initialized' LIMIT 1");
        $directorPrivacyFlag->execute();
        if ($directorPrivacyFlag->fetchColumn() === false && dbTableExists($pdo, 'users')) {
            $pdo->exec("UPDATE users SET profile_public = 0, stats_public = 0, searchable = 0, allow_friend_requests = 0, is_verified = 1 WHERE role = 'dyrektor'");
            $pdo->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('director_privacy_initialized', '1')");
        }
    } catch (Throwable $e) {
        error_log('App settings enhancements failed: ' . $e->getMessage());
    }

    // 10. Application statuses table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_statuses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            body TEXT NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active_created (is_active, created_at),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_status_deliveries (
            status_id INT NOT NULL,
            user_id INT NOT NULL,
            delivered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (status_id, user_id),
            INDEX idx_user_status_delivery (user_id, delivered_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Application statuses table creation failed: ' . $e->getMessage());
    }

    // 11. Rate limit events table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS feature_page_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_key VARCHAR(80) NOT NULL,
            title VARCHAR(160) NOT NULL,
            body TEXT NOT NULL,
            target_roles TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            disabled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ended_at DATETIME DEFAULT NULL,
            INDEX idx_category_active (category_key, is_active),
            INDEX idx_active_disabled (is_active, disabled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Feature page blocks table creation failed: ' . $e->getMessage());
    }

    // 11b. Sandbox element blocks table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sandbox_element_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            element_key VARCHAR(120) NOT NULL,
            title VARCHAR(160) NOT NULL,
            body TEXT NOT NULL,
            target_roles TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            disabled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ended_at DATETIME DEFAULT NULL,
            INDEX idx_element_active (element_key, is_active),
            INDEX idx_active_disabled (is_active, disabled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Sandbox element blocks table creation failed: ' . $e->getMessage());
    }
}

function _ensurePlatformSecurity(PDO $pdo): void {
    // 12. Rate limit events table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limit_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            bucket VARCHAR(80) NOT NULL,
            identity_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bucket_identity_created (bucket, identity_hash, created_at),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Rate limit events table creation failed: ' . $e->getMessage());
    }

    // 12. Password resets table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            ip_address VARCHAR(45) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_expires_used (expires_at, used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Password resets table creation failed: ' . $e->getMessage());
    }

    // 13. Active user sessions table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS active_user_sessions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent_hash CHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_session (user_id, session_hash),
            INDEX idx_user_last_seen (user_id, last_seen),
            INDEX idx_last_seen (last_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Active user sessions table creation failed: ' . $e->getMessage());
    }

    // 14. User MFA table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_mfa (
            user_id INT NOT NULL PRIMARY KEY,
            secret VARCHAR(64) NOT NULL,
            enabled_at DATETIME DEFAULT NULL,
            recovery_codes_hash TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_enabled (enabled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('User MFA table creation failed: ' . $e->getMessage());
    }
}

function _ensurePlatformRanking(PDO $pdo): void {
    // 15. All in duel usage table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS all_in_duel_usage (
            user_id INT NOT NULL,
            usage_date DATE NOT NULL,
            usage_count INT NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, usage_date),
            INDEX idx_usage_date (usage_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('All in duel usage table creation failed: ' . $e->getMessage());
    }

    // 16. Ranking event templates table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ranking_event_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            description VARCHAR(255) NOT NULL,
            multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.10,
            duration_days INT NOT NULL DEFAULT 7,
            season VARCHAR(80) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Ranking event templates table creation failed: ' . $e->getMessage());
    }

    // 17. Ranking events table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ranking_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_id INT DEFAULT NULL,
            name VARCHAR(160) NOT NULL,
            description VARCHAR(255) NOT NULL,
            multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.10,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            status ENUM('scheduled','active','finished','cancelled') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status_dates (status, starts_at, ends_at),
            INDEX idx_template (template_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Ranking events table creation failed: ' . $e->getMessage());
    }
}

function _ensurePlatformAdmin(PDO $pdo): void {
    // 18. Admin request replies table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_request_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            admin_id INT DEFAULT NULL,
            reply_text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_request_created (request_id, created_at),
            INDEX idx_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Admin request replies table creation failed: ' . $e->getMessage());
    }

    // 19. Abuse reports table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS abuse_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_user_id INT DEFAULT NULL,
            report_type VARCHAR(80) NOT NULL DEFAULT 'other',
            content_url VARCHAR(500) DEFAULT NULL,
            description TEXT NOT NULL,
            reporter_email VARCHAR(160) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            status ENUM('new','reviewing','resolved','rejected') NOT NULL DEFAULT 'new',
            admin_note TEXT DEFAULT NULL,
            handled_by INT DEFAULT NULL,
            handled_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status_created (status, created_at),
            INDEX idx_reporter_created (reporter_user_id, created_at),
            INDEX idx_ip_created (ip_address, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        if (dbTableExists($pdo, 'abuse_reports')) {
            dbAddColumnIfMissing($pdo, 'abuse_reports', 'reporter_user_id', "INT DEFAULT NULL AFTER id");
            dbAddIndexIfMissing($pdo, 'abuse_reports', 'idx_reporter_created', '(reporter_user_id, created_at)');
        }
    } catch (Throwable $e) {
        error_log('Abuse reports table enhancements failed: ' . $e->getMessage());
    }

    // 20. Lessons table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS lessons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            title VARCHAR(160) NOT NULL,
            body TEXT NOT NULL,
            pdf_path VARCHAR(255) DEFAULT NULL,
            pdf_filename VARCHAR(255) DEFAULT NULL,
            pdf_download_allowed TINYINT(1) NOT NULL DEFAULT 0,
            qualification VARCHAR(20) NOT NULL DEFAULT 'general',
            lesson_type ENUM('lesson','homework') NOT NULL DEFAULT 'lesson',
            status ENUM('published','archived') NOT NULL DEFAULT 'published',
            due_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status_created (status, created_at),
            INDEX idx_teacher_status (teacher_id, status),
            INDEX idx_qualification_status (qualification, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        if (dbTableExists($pdo, 'lessons')) {
            dbAddColumnIfMissing($pdo, 'lessons', 'pdf_path', "VARCHAR(255) DEFAULT NULL AFTER body");
            dbAddColumnIfMissing($pdo, 'lessons', 'pdf_filename', "VARCHAR(255) DEFAULT NULL AFTER pdf_path");
            dbAddColumnIfMissing($pdo, 'lessons', 'pdf_download_allowed', "TINYINT(1) NOT NULL DEFAULT 0 AFTER pdf_filename");
        }
    } catch (Throwable $e) {
        error_log('Lessons table enhancements failed: ' . $e->getMessage());
    }

    // 21. Admin audit log table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(80) DEFAULT NULL,
            target_id INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_created (admin_id, created_at),
            INDEX idx_action_created (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('Admin audit log table creation failed: ' . $e->getMessage());
    }

    // 22. Seed templates
    try {
        seedRankingEventTemplates($pdo);
    } catch (Throwable $e) {
        error_log('Seeding ranking event templates failed: ' . $e->getMessage());
    }
}

function getAppSetting(PDO $pdo, string $key, $default = null) {
    $cache =& dbRequestRuntimeCache();
    $cacheKey = 'setting:' . spl_object_id($pdo) . ':' . $key;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey]['found'] ? $cache[$cacheKey]['value'] : $default;
    }
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$cacheKey] = ['found' => $value !== false, 'value' => $value];
        return $value === false ? $default : $value;
    } catch (PDOException $e) {
        return $default;
    }
}

function setAppSetting(PDO $pdo, string $key, $value): bool {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $saved = $stmt->execute([$key, (string)$value]);
        if ($saved) {
            $cache =& dbRequestRuntimeCache();
            $cache['setting:' . spl_object_id($pdo) . ':' . $key] = ['found' => true, 'value' => (string)$value];
        }
        return $saved;
    } catch (PDOException $e) {
        error_log('App setting save failed: ' . $e->getMessage());
        return false;
    }
}

function pruneAppStatuses(PDO $pdo, int $limit = 10): void {
    try {
        $limit = max(1, $limit);
        $pdo->exec("DELETE FROM app_statuses WHERE id NOT IN (SELECT id FROM (SELECT id FROM app_statuses ORDER BY created_at DESC, id DESC LIMIT {$limit}) keep_rows)");
    } catch (PDOException $e) {
        error_log('Prune app statuses failed: ' . $e->getMessage());
    }
}

function getAppStatuses(PDO $pdo, bool $activeOnly = false, int $limit = 10): array {
    try {
        ensurePlatformEnhancements($pdo);
        $sql = "
            SELECT s.*, u.first_name, u.last_name, u.username, u.role
            FROM app_statuses s
            LEFT JOIN users u ON u.id = s.created_by
        ";
        if ($activeOnly) {
            $sql .= " WHERE s.is_active = 1";
        }
        $sql .= " ORDER BY s.created_at DESC, s.id DESC LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get app statuses failed: ' . $e->getMessage());
        return [];
    }
}

function getAppStatusById(PDO $pdo, int $statusId): ?array {
    if ($statusId <= 0) return null;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT s.*, u.first_name, u.last_name, u.username, u.role
            FROM app_statuses s
            LEFT JOIN users u ON u.id = s.created_by
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$statusId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('Get app status failed: ' . $e->getMessage());
        return null;
    }
}

function appStatusModeratorLabel(array $status): string {
    $roleBadge = getUserRoleBadge($status['role'] ?? 'admin');
    $label = trim(userDisplayName($status) . ' ' . userHandle($status));
    if ($label === '') {
        $label = 'Administrator';
    }
    return trim($label . ' (' . $roleBadge['label'] . ')');
}

function extractAppStatusIdFromNotification(array $notification): int {
    $actionUrl = (string)($notification['action_url'] ?? '');
    if (preg_match('/app-status-(\d+)/', $actionUrl, $matches)) {
        return (int)$matches[1];
    }
    if (preg_match('/#(\d+)/', (string)($notification['message'] ?? ''), $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

function resolveAppStatusNotification(PDO $pdo, array $notification): ?array {
    if (($notification['type'] ?? '') !== 'app_status') return null;
    $statusId = extractAppStatusIdFromNotification($notification);
    $status = $statusId > 0 ? getAppStatusById($pdo, $statusId) : null;
    if (!$status) {
        $titleCandidate = trim(preg_replace('/^Nowy status\s*#\d+:\s*/u', '', (string)($notification['message'] ?? '')));
        if ($titleCandidate !== '') {
            try {
                ensurePlatformEnhancements($pdo);
                $stmt = $pdo->prepare("
                    SELECT s.*, u.first_name, u.last_name, u.username, u.role
                    FROM app_statuses s
                    LEFT JOIN users u ON u.id = s.created_by
                    WHERE s.title = ?
                    ORDER BY s.is_active DESC, s.created_at DESC, s.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$titleCandidate]);
                $status = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException $e) {
                error_log('Resolve app status by title failed: ' . $e->getMessage());
            }
        }
    }
    if (!$status) {
        $message = preg_replace('/^Nowy status\s*#\d+:\s*/u', '', (string)($notification['message'] ?? ''));
        return [
            'id' => $statusId,
            'title' => trim((string)$message) ?: 'Status',
            'body' => 'Ten status nie jest już dostępny.',
            'level' => 'info',
            'date' => !empty($notification['created_at']) ? date('d.m.Y H:i', strtotime((string)$notification['created_at'])) : date('d.m.Y H:i'),
            'moderator' => 'System',
        ];
    }
    return [
        'id' => (int)$status['id'],
        'title' => (string)$status['title'],
        'body' => (string)$status['body'],
        'level' => (string)($status['level'] ?? 'info'),
        'date' => !empty($status['created_at']) ? date('d.m.Y H:i', strtotime((string)$status['created_at'])) : date('d.m.Y H:i'),
        'moderator' => appStatusModeratorLabel($status),
    ];
}

function createAppStatus(PDO $pdo, string $title, string $body, string $level, int $adminId): int {
    try {
        ensurePlatformEnhancements($pdo);
        $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM app_statuses WHERE is_active = 1")->fetchColumn();
        if ($activeCount >= 2) {
            return 0;
        }
        $allowedLevels = ['info', 'success', 'warning', 'danger'];
        if (!in_array($level, $allowedLevels, true)) $level = 'info';
        $stmt = $pdo->prepare("INSERT INTO app_statuses (title, body, level, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            mb_substr(trim($title), 0, 160),
            mb_substr(trim($body), 0, 1200),
            $level,
            $adminId > 0 ? $adminId : null,
        ]);
        $statusId = (int)$pdo->lastInsertId();
        pruneAppStatuses($pdo, 10);
        return $statusId;
    } catch (PDOException $e) {
        error_log('Create app status failed: ' . $e->getMessage());
        return 0;
    }
}

function deleteAppStatus(PDO $pdo, int $statusId, int $adminId): bool {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("UPDATE app_statuses SET is_active = 0, updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$statusId]);
        if ($ok) {
            logAdminAction($pdo, $adminId, 'delete_app_status', 'app_status', $statusId);
            pruneAppStatuses($pdo, 10);
        }
        return $ok;
    } catch (PDOException $e) {
        error_log('Delete app status failed: ' . $e->getMessage());
        return false;
    }
}

function addAppStatusNotification(PDO $pdo, int $userId, array $status): bool {
    $statusId = (int)($status['id'] ?? 0);
    $title = trim((string)($status['title'] ?? ''));
    if ($userId <= 0 || $statusId <= 0 || $title === '') return false;

    try {
        ensurePlatformEnhancements($pdo);
        $actionUrl = 'settings.php#app-status-' . $statusId;
        $delivery = $pdo->prepare("SELECT 1 FROM app_status_deliveries WHERE status_id = ? AND user_id = ? LIMIT 1");
        $delivery->execute([$statusId, $userId]);
        if ($delivery->fetchColumn()) return false;

        if (dbColumnExists($pdo, 'notifications', 'dedupe_key')) {
            $dedupeKey = hash('sha256', $userId . '|app_status|' . $statusId);
            $hasActionUrl = dbColumnExists($pdo, 'notifications', 'action_url');
            $sql = "SELECT id FROM notifications WHERE user_id = ? AND type = 'app_status' AND (dedupe_key = ? OR message LIKE ?";
            $params = [$userId, $dedupeKey, '%#' . $statusId . ':%'];
            if ($hasActionUrl) {
                $sql .= " OR action_url = ?";
                $params[] = $actionUrl;
            }
            $sql .= ") LIMIT 1";
            $check = $pdo->prepare($sql);
            $check->execute($params);
            if ($check->fetchColumn()) {
                $pdo->prepare("INSERT IGNORE INTO app_status_deliveries (status_id, user_id) VALUES (?, ?)")->execute([$statusId, $userId]);
                return false;
            }
            $ok = addNotification($pdo, $userId, 'app_status', $title, $actionUrl, $dedupeKey);
            if ($ok) {
                $pdo->prepare("INSERT IGNORE INTO app_status_deliveries (status_id, user_id) VALUES (?, ?)")->execute([$statusId, $userId]);
            }
            return $ok;
        }

        if (dbColumnExists($pdo, 'notifications', 'action_url')) {
            $check = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND type = 'app_status' AND action_url = ? LIMIT 1");
            $check->execute([$userId, $actionUrl]);
            if ($check->fetchColumn()) {
                $pdo->prepare("INSERT IGNORE INTO app_status_deliveries (status_id, user_id) VALUES (?, ?)")->execute([$statusId, $userId]);
                return false;
            }
        }
        $ok = addNotification($pdo, $userId, 'app_status', $title, $actionUrl);
        if ($ok) {
            $pdo->prepare("INSERT IGNORE INTO app_status_deliveries (status_id, user_id) VALUES (?, ?)")->execute([$statusId, $userId]);
        }
        return $ok;
    } catch (PDOException $e) {
        error_log('Add app status notification failed: ' . $e->getMessage());
        return false;
    }
}

function syncAppStatusNotificationsForUser(PDO $pdo, int $userId): int {
    if ($userId <= 0) return 0;

    $syncKey = 'app_status_sync_at';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $lastSync = (int)($_SESSION[$syncKey] ?? 0);
        if ($lastSync > time() - 300) {
            return 0;
        }
        // Set before querying so a temporary database failure cannot create a
        // retry storm from the notification poller.
        $_SESSION[$syncKey] = time();
    }

    $created = 0;
    foreach (getAppStatuses($pdo, true, 2) as $status) {
        if (addAppStatusNotification($pdo, $userId, $status)) {
            $created++;
        }
    }
    return $created;
}

function notifyUsersAboutAppStatus(PDO $pdo, int $statusId, string $title): int {
    if ($statusId <= 0) return 0;
    try {
        $status = getAppStatusById($pdo, $statusId);
        if (!$status) return 0;
        $stmt = $pdo->query("SELECT id FROM users WHERE COALESCE(is_banned, 0) = 0");
        $sent = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $targetUserId) {
            if (addAppStatusNotification($pdo, (int)$targetUserId, $status)) {
                $sent++;
            }
        }
        return $sent;
    } catch (PDOException $e) {
        error_log('Notify app status failed: ' . $e->getMessage());
        return 0;
    }
}

function examAiCopyGuardSettingKey(int $examId): string {
    return 'exam_ai_copy_guard_' . max(0, $examId);
}

function examAiCopyGuardEnabled(PDO $pdo, int $examId): bool {
    if ($examId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([examAiCopyGuardSettingKey($examId)]);
        return (string)$stmt->fetchColumn() === '1';
    } catch (PDOException $e) {
        return false;
    }
}

function setExamAiCopyGuard(PDO $pdo, int $examId, bool $enabled): bool {
    if ($examId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO app_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        return $stmt->execute([examAiCopyGuardSettingKey($examId), $enabled ? '1' : '0']);
    } catch (PDOException $e) {
        error_log('Exam AI copy guard save failed: ' . $e->getMessage());
        return false;
    }
}

function notifyTeacherAboutExamAiGuard(PDO $pdo, array $sessionInfo, array $participant, string $violationType): bool {
    $teacherId = (int)($sessionInfo['teacher_id'] ?? 0);
    $sessionId = (int)($participant['session_id'] ?? 0);
    $participantId = (int)($participant['id'] ?? 0);
    if ($teacherId <= 0 || $sessionId <= 0 || $participantId <= 0) {
        return false;
    }

    $participantName = trim((string)($participant['first_name'] ?? '') . ' ' . (string)($participant['last_name'] ?? ''));
    if ($participantName === '') {
        $participantName = 'Uczestnik #' . $participantId;
    }
    $incident = $violationType === 'screenshot_attempt' ? 'próbował wykonać zrzut ekranu' : 'próbował skopiować treść pytania';
    $examTitle = trim((string)($sessionInfo['exam_title'] ?? 'sprawdzian'));
    $message = $participantName . ' ' . $incident . ' podczas sprawdzianu „' . $examTitle . '”.';
    $dedupeBucket = (string)floor(time() / 300);
    $dedupeKey = hash('sha256', 'exam-ai-guard|' . $sessionId . '|' . $participantId . '|' . $violationType . '|' . $dedupeBucket);

    try {
        $check = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? AND dedupe_key = ? LIMIT 1');
        $check->execute([$teacherId, $dedupeKey]);
        if ($check->fetchColumn()) {
            return true;
        }

        $stmt = $pdo->prepare('
            INSERT INTO notifications (user_id, type, message, dedupe_key, action_url)
            VALUES (?, ?, ?, ?, ?)
        ');
        return $stmt->execute([
            $teacherId,
            'exam_ai_guard',
            $message,
            $dedupeKey,
            'teacher/exam_details.php?session=' . $sessionId,
        ]);
    } catch (PDOException $e) {
        error_log('Exam AI guard notification failed: ' . $e->getMessage());
        return false;
    }
}

function featureBlockTargetRoleValues(): array {
    return ['user', 'teacher', 'wujek_luki'];
}

function featureBlockRoleLabels(): array {
    return [
        'user' => 'Uczniowie',
        'teacher' => 'Nauczyciele',
        'wujek_luki' => 'Wujek Luki',
        'admin' => 'Administratorzy',
        'dyrektor' => 'Dyrektorzy',
    ];
}

function featureBlockRoleLabel(string $role): string {
    $labels = featureBlockRoleLabels();
    return $labels[$role] ?? $role;
}

function featureBlockNormalizeRoles(array $roles): array {
    $allowed = featureBlockTargetRoleValues();
    $normalized = [];
    foreach ($roles as $role) {
        $role = trim((string)$role);
        if (in_array($role, $allowed, true) && !in_array($role, $normalized, true)) {
            $normalized[] = $role;
        }
    }
    return $normalized;
}

function featureBlockDecodeRoles($value): array {
    if (is_array($value)) {
        return featureBlockNormalizeRoles($value);
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? featureBlockNormalizeRoles($decoded) : [];
}

function featureBlockModeratorLabel(array $block): string {
    $role = (string)($block['role'] ?? 'admin');
    $roleBadge = function_exists('getUserRoleBadge') ? getUserRoleBadge($role) : ['label' => featureBlockRoleLabel($role)];
    $label = trim(userDisplayName($block) . ' ' . userHandle($block));
    if ($label === '') {
        $label = 'Administrator';
    }
    return trim($label . ' (' . ($roleBadge['label'] ?? featureBlockRoleLabel($role)) . ')');
}

function featureBlockFormatRow(array $row, string $labelKey, string $labelValue): array {
    $roles = featureBlockDecodeRoles($row['target_roles'] ?? '[]');
    $row['target_roles_array'] = $roles;
    $row['target_role_labels'] = array_map('featureBlockRoleLabel', $roles);
    $row[$labelKey] = $labelValue;
    $row['moderator_label'] = featureBlockModeratorLabel($row);
    $row['disabled_date'] = !empty($row['disabled_at']) ? date('d.m.Y H:i', strtotime((string)$row['disabled_at'])) : date('d.m.Y H:i');
    return $row;
}

function getFeaturePageBlockCategories(): array {
    return [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill'],
        'tests' => ['label' => 'Testy', 'icon' => 'bi-journal-text'],
        'categories' => ['label' => 'Kategorie', 'icon' => 'bi-tags'],
        'practice' => ['label' => 'Praktyka', 'icon' => 'bi-tools'],
        'lessons' => ['label' => 'Lekcje', 'icon' => 'bi-easel2'],
        'ranking' => ['label' => 'Ranking', 'icon' => 'bi-trophy'],
        'dictionary' => ['label' => 'Słownik pojęć', 'icon' => 'bi-book'],
        'flashcards' => ['label' => 'Fiszki', 'icon' => 'bi-card-text'],
        'sandbox' => ['label' => 'Sandbox', 'icon' => 'bi-cpu'],
        'social' => ['label' => 'Społeczność', 'icon' => 'bi-people'],
        'progress' => ['label' => 'Statystyki', 'icon' => 'bi-bar-chart-line'],
        'missions' => ['label' => 'Misje', 'icon' => 'bi-lightning-charge'],
        'history' => ['label' => 'Historia', 'icon' => 'bi-clock-history'],
        'exam' => ['label' => 'Sprawdzian', 'icon' => 'bi-qr-code-scan'],
        'teacher' => ['label' => 'Panel nauczyciela', 'icon' => 'bi-clipboard2-pulse-fill'],
    ];
}

function resolveFeaturePageCategoryForPath(?string $path = null): ?string {
    $path = str_replace('\\', '/', (string)($path ?? ($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''))));
    $path = parse_url($path, PHP_URL_PATH) ?: $path;
    $path = trim($path, '/');

    $ends = static fn(string $suffix): bool => $path === $suffix || str_ends_with($path, '/' . $suffix);

    if (str_contains($path, '/teacher/') || str_starts_with($path, 'teacher/')) return 'teacher';
    if (str_contains($path, '/exam/') || str_starts_with($path, 'exam/')) return 'exam';
    if (str_contains($path, '/duels/') || str_starts_with($path, 'duels/')) return 'social';
    if ($ends('index.php')) return 'dashboard';
    if ($ends('test.php')) return 'tests';
    if ($ends('categories.php')) return 'categories';
    if ($ends('practice.php')) return 'practice';
    if ($ends('lessons.php') || $ends('lesson_pdf.php')) return 'lessons';
    if ($ends('ranking.php')) return 'ranking';
    if ($ends('dictionary.php')) return 'dictionary';
    if ($ends('flashcards.php')) return 'flashcards';
    if ($ends('sandbox.php')) return 'sandbox';
    if ($ends('social.php') || $ends('profile.php') || $ends('search_users.php')) return 'social';
    if ($ends('progress.php')) return 'progress';
    if ($ends('goals.php')) return 'missions';
    if ($ends('history.php') || $ends('result.php')) return 'history';
    return null;
}

function getActiveFeaturePageBlocks(PDO $pdo, int $limit = 80): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT b.*, u.username, u.first_name, u.last_name, u.role
            FROM feature_page_blocks b
            LEFT JOIN users u ON u.id = b.created_by
            WHERE b.is_active = 1
            ORDER BY b.disabled_at DESC, b.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $categories = getFeaturePageBlockCategories();
        return array_map(static function (array $row) use ($categories): array {
            $category = (string)($row['category_key'] ?? '');
            return featureBlockFormatRow($row, 'category_label', $categories[$category]['label'] ?? $category);
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log('Get feature page blocks failed: ' . $e->getMessage());
        return [];
    }
}

function getActiveFeaturePageBlockByCategory(PDO $pdo, string $categoryKey): ?array {
    $categories = getFeaturePageBlockCategories();
    if (!isset($categories[$categoryKey])) return null;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT b.*, u.username, u.first_name, u.last_name, u.role
            FROM feature_page_blocks b
            LEFT JOIN users u ON u.id = b.created_by
            WHERE b.category_key = ? AND b.is_active = 1
            ORDER BY b.disabled_at DESC, b.id DESC
            LIMIT 1
        ");
        $stmt->execute([$categoryKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? featureBlockFormatRow($row, 'category_label', $categories[$categoryKey]['label']) : null;
    } catch (PDOException $e) {
        error_log('Get feature page block failed: ' . $e->getMessage());
        return null;
    }
}

function createFeaturePageBlock(PDO $pdo, string $categoryKey, array $targetRoles, string $title, string $body, int $adminId): int {
    $categories = getFeaturePageBlockCategories();
    $roles = featureBlockNormalizeRoles($targetRoles);
    $title = mb_substr(trim($title), 0, 160);
    $body = mb_substr(trim($body), 0, 1200);
    if (!isset($categories[$categoryKey]) || empty($roles) || $title === '' || $body === '') return 0;

    try {
        ensurePlatformEnhancements($pdo);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE feature_page_blocks SET is_active = 0, ended_at = NOW(), updated_at = NOW() WHERE category_key = ? AND is_active = 1")
            ->execute([$categoryKey]);
        $stmt = $pdo->prepare("INSERT INTO feature_page_blocks (category_key, title, body, target_roles, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$categoryKey, $title, $body, json_encode($roles, JSON_UNESCAPED_UNICODE), $adminId > 0 ? $adminId : null]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Create feature page block failed: ' . $e->getMessage());
        return 0;
    }
}

function endFeaturePageBlock(PDO $pdo, int $blockId, int $adminId): bool {
    if ($blockId <= 0) return false;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("UPDATE feature_page_blocks SET is_active = 0, ended_at = NOW(), updated_at = NOW() WHERE id = ? AND is_active = 1");
        $stmt->execute([$blockId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('End feature page block failed: ' . $e->getMessage());
        return false;
    }
}

function resolveFeaturePageBlockForRequest(PDO $pdo, ?string $path = null, ?string $role = null): ?array {
    $category = resolveFeaturePageCategoryForPath($path);
    if (!$category) return null;
    return getActiveFeaturePageBlockByCategory($pdo, $category);
}

function featureBlockSafeReturnUrl(string $fallback = 'index.php'): string {
    $fallback = trim($fallback) !== '' ? $fallback : 'index.php';
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '' || str_starts_with($referer, '//')) {
        return $fallback;
    }

    $parts = parse_url($referer);
    if (!is_array($parts)) {
        return $fallback;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
        return $fallback;
    }

    $requestAuthority = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestParts = $requestAuthority !== '' ? parse_url('http://' . $requestAuthority) : [];
    $requestHost = is_array($requestParts) ? strtolower((string)($requestParts['host'] ?? '')) : '';
    $refererHost = strtolower((string)($parts['host'] ?? ''));
    if ($refererHost !== '' && ($requestHost === '' || $refererHost !== $requestHost)) {
        return $fallback;
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '' || str_starts_with($path, '//')) {
        return $fallback;
    }

    $currentUri = (string)($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    $currentPath = (string)(parse_url($currentUri, PHP_URL_PATH) ?: $currentUri);
    if ($currentPath !== '' && rtrim($path, '/') === rtrim($currentPath, '/')) {
        return $fallback;
    }

    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return $path . $query;
}

function renderFeaturePageBlockScreen(array $block): void {
    $esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = (string)($block['title'] ?? 'Strona jest czasowo wyłączona');
    $body = (string)($block['body'] ?? 'Ta kategoria jest obecnie niedostępna.');
    $category = (string)($block['category_label'] ?? ($block['category_key'] ?? 'Kategoria'));
    $moderator = (string)($block['moderator_label'] ?? 'Administrator');
    $date = (string)($block['disabled_date'] ?? date('d.m.Y H:i'));
    $roles = implode(', ', $block['target_role_labels'] ?? []);
    $script = $_SERVER['PHP_SELF'] ?? '';
    $base = (strpos($script, '/teacher/') !== false || strpos($script, '/exam/') !== false || strpos($script, '/duels/') !== false) ? '../' : '';
    $returnUrl = featureBlockSafeReturnUrl($base . 'index.php');
    http_response_code(403);
    echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $esc($title) . ' - ZSEM Tech</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">';
    echo '<style>body{min-height:100vh;display:grid;place-items:center;padding:1rem;background:linear-gradient(180deg,#f8fafc,#eef4fb);color:#0f172a;font-family:Inter,system-ui,sans-serif}.blocked-card{width:min(760px,94vw);border:1px solid rgba(148,163,184,.32);border-radius:8px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.16);padding:clamp(1.35rem,3vw,2.25rem)}.blocked-lock{width:56px;height:56px;border-radius:8px;display:grid;place-items:center;background:#fff7d6;color:#d97706;font-size:1.8rem;flex:0 0 auto}.blocked-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .65rem;border-radius:999px;background:#facc15;color:#172033;font-weight:900;font-size:.78rem}.blocked-meta{display:grid;gap:.65rem;margin-top:1.25rem}.blocked-meta div{padding:.8rem .95rem;border:1px solid rgba(148,163,184,.28);border-radius:8px;background:#f8fafc}.blocked-meta span{display:block;color:#64748b;font-size:.78rem;font-weight:800;text-transform:uppercase}.blocked-card .btn{font-weight:800}@media (max-width:560px){.blocked-card{padding:1.1rem}.blocked-heading{flex-direction:column}.blocked-lock{width:48px;height:48px}}</style>';
    echo '</head><body><main class="blocked-card" role="main">';
    echo '<div class="blocked-heading d-flex align-items-start gap-3"><div class="blocked-lock"><i class="bi bi-lock-fill"></i></div><div><p class="blocked-badge mb-2">Kategoria wyłączona</p><h1 class="h3 fw-bold mb-2">' . $esc($title) . '</h1><p class="text-muted mb-0">' . nl2br($esc($body)) . '</p></div></div>';
    echo '<div class="blocked-meta"><div><span>Kategoria</span><strong>' . $esc($category) . '</strong></div><div><span>Wyłączył</span><strong>' . $esc($moderator) . '</strong></div><div><span>Data</span><strong>' . $esc($date) . '</strong></div><div><span>Role</span><strong>' . $esc($roles ?: 'Wybrane role') . '</strong></div></div>';
    echo '<div class="mt-4 d-flex gap-2 flex-wrap"><a class="btn btn-primary rounded-pill px-4" href="' . $esc($returnUrl) . '"><i class="bi bi-arrow-left me-1"></i>Powrót</a></div>';
    echo '</main>';
    echo '</body></html>';
}

function enforceFeaturePageBlockForCurrentRequest(PDO $pdo): void {
    $role = (string)($_SESSION['role'] ?? 'user');
    $block = resolveFeaturePageBlockForRequest($pdo, null, $role);
    if (!$block) return;

    if (roleHasAdminAccess($role)) {
        $_SESSION['feature_block_notice'] = [
            'title' => $block['title'] ?? 'Kategoria wyłączona',
            'category_label' => $block['category_label'] ?? ($block['category_key'] ?? ''),
            'moderator_label' => $block['moderator_label'] ?? 'Administrator',
            'disabled_date' => $block['disabled_date'] ?? date('d.m.Y H:i'),
            'target_role_labels' => $block['target_role_labels'] ?? [],
        ];
        return;
    }

    if (in_array($role, $block['target_roles_array'] ?? [], true) || ($role === 'guest' && !empty($block['target_roles_array']))) {
        renderFeaturePageBlockScreen($block);
        exit;
    }
}

function getSandboxBlockableElements(): array {
    $elements = [
        'tool.logic' => ['label' => 'Sandbox: Bramki logiczne', 'group' => 'Narzędzia'],
        'tool.psu' => ['label' => 'Sandbox: Kalkulator PSU', 'group' => 'Narzędzia'],
        'tool.subnet' => ['label' => 'Sandbox: Podsieci IP', 'group' => 'Narzędzia'],
        'tool.router' => ['label' => 'Sandbox: Laboratorium sieci', 'group' => 'Narzędzia'],
        'tool.numbers' => ['label' => 'Sandbox: Systemy liczbowe', 'group' => 'Narzędzia'],
        'tool.ohm' => ['label' => 'Sandbox: Prawo Ohma', 'group' => 'Narzędzia'],
        'tool.live' => ['label' => 'Sandbox: Live HTML/CSS/JS', 'group' => 'Narzędzia'],
        'logic.input_a' => ['label' => 'Bramki: Przełącznik A', 'group' => 'Bramki logiczne'],
        'logic.input_b' => ['label' => 'Bramki: Przełącznik B', 'group' => 'Bramki logiczne'],
        'logic.const_1' => ['label' => 'Bramki: Stała 1', 'group' => 'Bramki logiczne'],
        'logic.const_0' => ['label' => 'Bramki: Stała 0', 'group' => 'Bramki logiczne'],
        'logic.output_led' => ['label' => 'Bramki: LED', 'group' => 'Bramki logiczne'],
        'logic.output_table' => ['label' => 'Bramki: Tabela prawdy', 'group' => 'Bramki logiczne'],
    ];
    foreach (['BUFFER', 'NOT', 'AND', 'NAND', 'OR', 'NOR', 'XOR', 'XNOR'] as $gate) {
        $elements['logic.gate_' . strtolower($gate)] = ['label' => 'Bramka ' . $gate, 'group' => 'Bramki logiczne'];
    }
    return $elements;
}

function getActiveSandboxElementBlocks(PDO $pdo, int $limit = 120): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT b.*, u.username, u.first_name, u.last_name, u.role
            FROM sandbox_element_blocks b
            LEFT JOIN users u ON u.id = b.created_by
            WHERE b.is_active = 1
            ORDER BY b.disabled_at DESC, b.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $elements = getSandboxBlockableElements();
        return array_map(static function (array $row) use ($elements): array {
            $key = (string)($row['element_key'] ?? '');
            return featureBlockFormatRow($row, 'element_label', $elements[$key]['label'] ?? $key);
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log('Get sandbox element blocks failed: ' . $e->getMessage());
        return [];
    }
}

function getActiveSandboxElementBlockByKey(PDO $pdo, string $elementKey): ?array {
    $elements = getSandboxBlockableElements();
    if (!isset($elements[$elementKey])) return null;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT b.*, u.username, u.first_name, u.last_name, u.role
            FROM sandbox_element_blocks b
            LEFT JOIN users u ON u.id = b.created_by
            WHERE b.element_key = ? AND b.is_active = 1
            ORDER BY b.disabled_at DESC, b.id DESC
            LIMIT 1
        ");
        $stmt->execute([$elementKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? featureBlockFormatRow($row, 'element_label', $elements[$elementKey]['label']) : null;
    } catch (PDOException $e) {
        error_log('Get sandbox element block failed: ' . $e->getMessage());
        return null;
    }
}

function createSandboxElementBlock(PDO $pdo, string $elementKey, array $targetRoles, string $title, string $body, int $adminId): int {
    $elements = getSandboxBlockableElements();
    $roles = featureBlockNormalizeRoles($targetRoles);
    $title = mb_substr(trim($title), 0, 160);
    $body = mb_substr(trim($body), 0, 1200);
    if (!isset($elements[$elementKey]) || empty($roles) || $title === '' || $body === '') return 0;

    try {
        ensurePlatformEnhancements($pdo);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE sandbox_element_blocks SET is_active = 0, ended_at = NOW(), updated_at = NOW() WHERE element_key = ? AND is_active = 1")
            ->execute([$elementKey]);
        $stmt = $pdo->prepare("INSERT INTO sandbox_element_blocks (element_key, title, body, target_roles, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$elementKey, $title, $body, json_encode($roles, JSON_UNESCAPED_UNICODE), $adminId > 0 ? $adminId : null]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Create sandbox element block failed: ' . $e->getMessage());
        return 0;
    }
}

function endSandboxElementBlock(PDO $pdo, int $blockId, int $adminId): bool {
    if ($blockId <= 0) return false;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("UPDATE sandbox_element_blocks SET is_active = 0, ended_at = NOW(), updated_at = NOW() WHERE id = ? AND is_active = 1");
        $stmt->execute([$blockId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('End sandbox element block failed: ' . $e->getMessage());
        return false;
    }
}

function resolveSandboxElementBlock(PDO $pdo, string $elementKey, ?string $role = null): ?array {
    $role = $role ?? (string)($_SESSION['role'] ?? 'user');
    $block = getActiveSandboxElementBlockByKey($pdo, $elementKey);
    if (!$block) return null;
    if (roleHasAdminAccess($role) || in_array($role, $block['target_roles_array'] ?? [], true) || ($role === 'guest' && !empty($block['target_roles_array']))) {
        return $block;
    }
    return null;
}

function getSandboxElementBlockMapForRole(PDO $pdo, string $role): array {
    $map = [];
    foreach (getActiveSandboxElementBlocks($pdo, 200) as $block) {
        if (roleHasAdminAccess($role) || in_array($role, $block['target_roles_array'] ?? [], true) || ($role === 'guest' && !empty($block['target_roles_array']))) {
            $map[(string)$block['element_key']] = $block;
        }
    }
    return $map;
}

function logAdminAction(PDO $pdo, $adminId, string $action, ?string $targetType = null, $targetId = null, ?string $details = null): void {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('
            INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $adminId ? (int)$adminId : null,
            mb_substr($action, 0, 100),
            $targetType ? mb_substr($targetType, 0, 80) : null,
            $targetId !== null ? (int)$targetId : null,
            $details ? mb_substr($details, 0, 2000) : null,
            clientIpAddress()
        ]);
        $pdo->exec('DELETE FROM admin_audit_log WHERE id NOT IN (SELECT id FROM (SELECT id FROM admin_audit_log ORDER BY created_at DESC, id DESC LIMIT 50) keep_rows)');
    } catch (PDOException $e) {
        error_log('Admin audit log failed: ' . $e->getMessage());
    }
}

function getAdminAuditLog(PDO $pdo, int $limit = 50): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('
            SELECT a.*, u.username AS admin_username
            FROM admin_audit_log a
            LEFT JOIN users u ON u.id = a.admin_id
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function deleteAdminAuditLogEntry(PDO $pdo, int $entryId, int $adminId): bool {
    if ($entryId <= 0) {
        return false;
    }

    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare('DELETE FROM admin_audit_log WHERE id = ?');
        $stmt->execute([$entryId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            logAdminAction($pdo, $adminId, 'delete_admin_audit_entry', 'admin_audit_log', $entryId);
        }
        return $ok;
    } catch (PDOException $e) {
        error_log('Admin audit delete failed: ' . $e->getMessage());
        return false;
    }
}

function profanityWordList(): array {
    return [
        'kurw', 'chuj', 'huj', 'pierd', 'jeb', 'spier', 'wypier',
        'cwel', 'dziwk', 'kutas', 'skurw', 'zjeb', 'debil',
        'idiot', 'szmata', 'pedal', 'pojeba', 'cip', 'dupa', 'ruchac',
        'fuck', 'shit', 'bitch', 'nigg', 'hitler', 'nazi',
        'retard', 'whore', 'slut', 'cunt', 'fag', 'heil', 'rasist',
        'nienawidze', 'zabij', 'samoboj', 'terror', 'porn', 'sex',
        'asshole', 'bastard', 'motherfuck', 'dickhead', 'wanker',
        'mierda', 'puta', 'puto', 'cabron', 'joder', 'gilipoll',
        'scheisse', 'arschloch', 'fotze', 'wichser',
        'salope', 'putain', 'merde', 'connard',
        'blyat', 'suka', 'pidor', 'khuy', 'govno'
    ];
}

function normalizeProfanityText($value, bool $joinTokens = true): string {
    $value = mb_strtolower((string)$value, 'UTF-8');
    $value = strtr($value, [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ż' => 'z', 'ź' => 'z',
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c',
        'у' => 'y', 'х' => 'x', 'к' => 'k', 'м' => 'm', 'т' => 't',
        '@' => 'a', '$' => 's', '€' => 'e', '0' => 'o', '1' => 'i', '!' => 'i',
        '|' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '+' => 't',
        '8' => 'b', '9' => 'g'
    ]);
    $value = preg_replace('/(.)\1{2,}/u', '$1$1', $value);
    $pattern = $joinTokens ? '/[^\p{L}\p{N}]+/u' : '/[^\p{L}\p{N}]+/u';
    return trim((string)preg_replace($pattern, $joinTokens ? '' : ' ', $value));
}

function profanityVariants($value): array {
    $spaced = normalizeProfanityText($value, false);
    $joined = normalizeProfanityText($value, true);
    $compactNoVowels = preg_replace('/[aeiouy]+/u', '', $joined);
    return array_values(array_unique(array_filter([$joined, $spaced, $compactNoVowels])));
}

function containsProfanity($value) {
    $blocked = profanityWordList();
    foreach (profanityVariants($value) as $variant) {
        $joinedVariant = str_replace(' ', '', $variant);
        foreach ($blocked as $word) {
            if ($word !== '' && mb_strpos($joinedVariant, $word) !== false) {
                return true;
            }
        }

        $tokens = preg_split('/\s+/u', $variant) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token, 'UTF-8') < 4 || mb_strlen($token, 'UTF-8') > 32) {
                continue;
            }
            foreach ($blocked as $word) {
                $wordLen = mb_strlen($word, 'UTF-8');
                if ($wordLen < 4 || abs(mb_strlen($token, 'UTF-8') - $wordLen) > 2) {
                    continue;
                }
                $distance = levenshtein($token, $word);
                $limit = $wordLen >= 7 ? 2 : 1;
                if ($distance <= $limit) {
                    return true;
                }
            }
        }
    }
    return false;
}

function validateAllowedEmail($email) {
    $email = trim((string)$email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
    $allowed = [
        'gmail.com','interia.pl','outlook.com','hotmail.com','live.com','msn.com',
        'wp.pl','o2.pl','op.pl','onet.pl','int.pl','yahoo.com','icloud.com',
        'me.com','mac.com','proton.me','protonmail.com','mail.com','zsem.edu.pl'
    ];
    return in_array($domain, $allowed, true);
}

function normalizeClassParts($year, $suffix) {
    $rawYear = trim((string)$year);
    $suffix = strtoupper(trim((string)$suffix));
    if ($rawYear === '' || in_array(mb_strtolower($rawYear, 'UTF-8'), ['na', 'n/a', 'none', 'brak', 'nie dotyczy'], true)) {
        return ['year' => null, 'suffix' => '', 'label' => null];
    }
    $year = (int)$rawYear;
    if ($year < 1 || $year > 5) return null;
    if (!preg_match('/^[A-Z]{0,2}$/', $suffix)) return null;
    return ['year' => $year, 'suffix' => $suffix, 'label' => trim($year . $suffix)];
}

function parseClassLabel($class) {
    $class = strtoupper(trim((string)$class));
    if (!preg_match('/^([1-5])([A-Z]{0,2})$/', $class, $m)) return null;
    return ['year' => (int)$m[1], 'suffix' => $m[2], 'label' => $m[1] . $m[2]];
}

function getRankInfoByXp($xp) {
    $xp = max(0, (int)$xp);
    $thresholds = null;
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $dbRanks = getRankDefinitions($GLOBALS['pdo']);
        if (!empty($dbRanks)) $thresholds = $dbRanks;
    }
    if ($thresholds === null) {
        $thresholds = [];
        $tiers = [
            ['name' => 'Bronze', 'xp' => [0, 250, 500, 800, 1100], 'icon' => 'bi-shield', 'color' => '#64748b'],
            ['name' => 'Silver', 'xp' => [1500, 2000, 2600, 3300, 4100], 'icon' => 'bi-shield-fill', 'color' => '#94a3b8'],
            ['name' => 'Gold', 'xp' => [5000, 6000, 7100, 8300, 9600], 'icon' => 'bi-award-fill', 'color' => '#f59e0b'],
            ['name' => 'Platinum', 'xp' => [11000, 12500, 14100, 15800, 17600], 'icon' => 'bi-gem', 'color' => '#0ea5e9'],
            ['name' => 'Diamond', 'xp' => [19500, 21500, 23600, 25800, 28100], 'icon' => 'bi-diamond-fill', 'color' => '#8b5cf6'],
            ['name' => 'Master', 'xp' => [30500, 33000, 35600, 38300, 41100], 'icon' => 'bi-stars', 'color' => '#ec4899'],
            ['name' => 'Grandmaster', 'xp' => [44000, 47000, 50100, 53300, 56600], 'icon' => 'bi-trophy-fill', 'color' => '#ef4444'],
            ['name' => 'Wujek luki', 'xp' => [75000], 'icon' => 'bi-crown-fill', 'color' => '#facc15']
        ];

        foreach ($tiers as $tier) {
            $roman = ['V', 'IV', 'III', 'II', 'I'];
            foreach ($tier['xp'] as $idx => $minXp) {
                $name = $tier['name'];
                if ($name !== 'Wujek luki') {
                    $name .= ' ' . ($roman[$idx] ?? ($idx + 1));
                }
                $thresholds[] = [
                    'name' => $name,
                    'min_xp' => $minXp,
                    'icon' => $tier['icon'],
                    'color' => $tier['color'],
                    'description' => null
                ];
            }
        }
    }

    $current = $thresholds[0];
    $next = $thresholds[0] ?? null;
    foreach ($thresholds as $idx => $rank) {
        if ($xp >= $rank['min_xp']) {
            $current = $rank;
            $next = $thresholds[$idx + 1] ?? null;
        } else {
            break;
        }
    }

    $nextXp = $next['min_xp'] ?? $xp;
    $span = max(1, $nextXp - $current['min_xp']);
    $progress = $next ? min(100, round((($xp - $current['min_xp']) / $span) * 100)) : 100;
    if (mb_strtolower($current['name'] ?? '', 'UTF-8') === 'wujek luki') {
        $current['icon'] = $current['icon'] ?: 'bi-crown-fill';
        $current['color'] = $current['color'] ?: '#facc15';
    }

    return [
        'name' => $current['name'],
        'icon' => normalizeRankIcon($current['icon'] ?? ''),
        'color' => normalizeRankColor($current['color'] ?? ''),
        'description' => $current['description'] ?? null,
        'min_xp' => $current['min_xp'],
        'next_name' => $next['name'] ?? null,
        'next_xp' => $nextXp,
        'xp_to_next' => $next ? max(0, $nextXp - $xp) : 0,
        'progress' => $progress
    ];
}

function normalizeRankColor($color, string $fallback = '#3b82f6'): string {
    $color = strtolower(trim((string)$color));
    return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : $fallback;
}

function normalizeRankIcon($icon, string $fallback = 'bi-shield-fill'): string {
    $icon = trim((string)$icon);
    return preg_match('/^bi-[a-z0-9-]{1,64}$/i', $icon) ? $icon : $fallback;
}

function getRankDefinitions($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, min_xp, icon, color, description FROM rank_definitions WHERE is_active = 1 ORDER BY min_xp ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function createRankDefinition($pdo, $name, $minXp, $icon, $color, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO rank_definitions (name, min_xp, icon, color, description) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([trim($name), max(0, (int)$minXp), normalizeRankIcon($icon), normalizeRankColor($color), trim($description) ?: null]);
    } catch (PDOException $e) {
        error_log('Create rank failed: ' . $e->getMessage());
        return false;
    }
}

function updateRankDefinition($pdo, $id, $name, $minXp, $icon, $color, $description) {
    try {
        $stmt = $pdo->prepare("
            UPDATE rank_definitions
            SET name = ?, min_xp = ?, icon = ?, color = ?, description = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            trim($name),
            max(0, (int)$minXp),
            normalizeRankIcon($icon),
            normalizeRankColor($color),
            trim($description) ?: null,
            (int)$id
        ]);
    } catch (PDOException $e) {
        error_log('Update rank failed: ' . $e->getMessage());
        return false;
    }
}

function awardXp($pdo, $userId, $amount, $source, $sourceId = null, $description = null) {
    $amount = (int)$amount;
    if ($amount === 0) return false;
    try {
        $pdo->prepare("UPDATE users SET xp = GREATEST(0, xp + ?) WHERE id = ?")->execute([$amount, $userId]);
        try {
            $stmt = $pdo->prepare("INSERT INTO xp_events (user_id, source, source_id, amount, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $source, $sourceId, $amount, $description]);
        } catch (PDOException $e) {
            error_log('XP event insert failed: ' . $e->getMessage());
        }
        return true;
    } catch (PDOException $e) {
        error_log('XP award failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update user activity timestamp in database
 */
function updateUserActivity($pdo, $userId) {
    if (!$userId) return;
    
    // Optimization: Only update DB if more than 2 minutes since last update in this session
    $now = time();
    $lastUpdate = $_SESSION['last_activity_update'] ?? 0;
    if ($now - $lastUpdate < 120) return;

    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$userId]);
        $_SESSION['last_activity_update'] = $now;
    } catch (PDOException $e) {
        error_log("Failed to update user activity: " . $e->getMessage());
    }
}

/**
 * Check if user is online (active in the last 5 minutes)
 */
function isUserOnline($lastActivity) {
    if (!$lastActivity) return false;
    $last = strtotime($lastActivity);
    $now = time();
    return ($now - $last) < 300; // 5 minutes
}

/**
 * Escape a string for safe HTML output (alias for sanitize)
 * @param string $str Input string
 * @return string Escaped string
 */
function escapeHtml($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

/**
 * Safely redirect to a URL and exit
 * @param string $url URL to redirect to
 */
function redirect($url) {
    $url = function_exists('securityLocalRedirectTarget')
        ? securityLocalRedirectTarget((string)$url, 'index.php')
        : str_replace(["\r", "\n"], '', (string)$url);
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    } else {
        // If headers already sent, use JavaScript redirect
        echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        exit();
    }
}

// ============================================
// Question Management Functions
// ============================================

/**
 * Resolve an image URL to a local path if it exists in the cache
 * @param string|null $url Original URL or path
 * @return string|null Resolved local path or original URL
 */
function resolveImagePath($url) {
    if (empty($url)) return null;
    $url = str_replace(["\r", "\n", "\0"], '', trim((string)$url));
    
    // If it's already a local path, just return it
    if (!preg_match('#^https?://#i', $url)) {
        return $url;
    }
    
    $basename = basename($url);
    
    // Cache the directory listing to avoid repeated disk I/O
    static $imageMap = null;
    if ($imageMap === null) {
        $imageMap = [];
        $dirs = ['assets/images/ee08', 'assets/images/questions'];
        foreach ($dirs as $dir) {
            $fullDir = __DIR__ . '/../' . $dir;
            if (is_dir($fullDir)) {
                $files = scandir($fullDir);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        // Pattern: prefix_hash_originalBasename.ext
                        $parts = explode('_', $file);
                        $originalBasename = end($parts);
                        $imageMap[$originalBasename] = $dir . '/' . $file;
                    }
                }
            }
        }
    }
    
    // Check if we have a local version of this basename
    if (isset($imageMap[$basename])) {
        return $imageMap[$basename];
    }
    
    return $url;
}

function isPrivateIpAddress(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return true;
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
}

function isAllowedRemoteQuestionImageHost(string $host): bool {
    $host = mb_strtolower(rtrim($host, '.'), 'UTF-8');
    $allowed = [
        'praktycznyegzamin.pl',
        'www.praktycznyegzamin.pl',
        'zsemtech.zsem.edu.pl',
        $_SERVER['HTTP_HOST'] ?? ''
    ];
    $allowed = array_filter(array_map(static fn($h) => mb_strtolower(preg_replace('/:\d+$/', '', trim((string)$h)), 'UTF-8'), $allowed));
    return in_array($host, $allowed, true);
}

function questionImageSrc($url, string $basePrefix = ''): ?string {
    $safe = sanitizeQuestionImageUrl($url);
    if ($safe === null) return null;

    $resolved = resolveImagePath($safe);
    if ($resolved === null || $resolved === '') return null;
    if (preg_match('#^https?://#i', $resolved) || strpos($resolved, 'data:') === 0 || strpos($resolved, '/') === 0) {
        return $resolved;
    }
    return rtrim($basePrefix, '/') . ($basePrefix !== '' ? '/' : '') . ltrim($resolved, '/');
}

/**
 * Load questions from JSON file
 * @param PDO|null $pdo Optional database connection for hybrid data sources
 * @return array Array of questions
 */
function loadQuestions($pdo = null, $includeDbQuestions = true) {
    // Static cache: avoid re-parsing on the same request
    static $cache = [
        'with_db' => null,
        'json_only' => null,
    ];

    $cacheKey = $includeDbQuestions ? 'with_db' : 'json_only';
    if ($cache[$cacheKey] !== null) {
        return $cache[$cacheKey];
    }

    $questions = [];
    $seenQuestions = []; // Track text to avoid duplicates
    $autoId = 1;

    // 1. Try to load from database if PDO provided and the caller wants DB questions
    if ($pdo && $includeDbQuestions) {
        try {
            $stmt = $pdo->query("SELECT * FROM questions ORDER BY id");
            $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($dbQuestions)) {
                foreach ($dbQuestions as $q) {
                    $text = trim($q['question_text'] ?? ($q['question'] ?? ''));
                    if (empty($text)) continue;

                    $cat = $q['category'] ?? '';
                    if ($cat === 'EE.08') $cat = 'INF.02';

                    $questions[] = [
                        'id' => (int)($q['id'] ?? $autoId++),
                        'category' => $cat,
                        'question_text' => $text,
                        'option_a' => $q['option_a'] ?? '',
                        'option_b' => $q['option_b'] ?? '',
                        'option_c' => $q['option_c'] ?? '',
                        'option_d' => $q['option_d'] ?? '',
                        'correct_answer' => strtoupper($q['correct_answer'] ?? ($q['correct'] ?? '')),
                        'explanation' => $q['explanation'] ?? '',
                        'image_url' => resolveImagePath($q['image_url'] ?? ($q['image'] ?? null))
                    ];
                    $seenQuestions[$text] = true;
                    $autoId = max($autoId, (int)($q['id'] ?? 0) + 1);
                }
            }
        } catch (PDOException $e) {
            error_log("Database error loading questions: " . $e->getMessage());
        }
    }

    // 2. Load from ALL JSON files in data_question directory
    $dataDir = __DIR__ . '/../data_question/';
    $jsonFiles = glob($dataDir . '*.json');
    sort($jsonFiles); // Crucial for stable IDs
    
    foreach ($jsonFiles as $jsonPath) {
        if (file_exists($jsonPath)) {
            $jsonData = file_get_contents($jsonPath);
            if (strncmp($jsonData, "\xEF\xBB\xBF", 3) === 0) {
                $jsonData = substr($jsonData, 3);
            }
            $data = json_decode($jsonData, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $filename = strtolower(basename($jsonPath, '.json'));
                
                $defaultCategory = '';
                if (strpos($filename, 'inf') === 0 && strlen($filename) >= 5) {
                    $num = substr($filename, 3);
                    $defaultCategory = 'INF.' . $num;
                } elseif ($filename === 'questions') {
                    $defaultCategory = 'Ogólne';
                }

                $raw = isset($data['questions']) ? $data['questions'] : (is_array($data) && !isset($data['questions']) ? $data : []);
                
                foreach ($raw as $q) {
                    $text = trim($q['question'] ?? ($q['question_text'] ?? ''));
                    if (empty($text) || isset($seenQuestions[$text])) continue;

                    $ansOptions = $q['options'] ?? ($q['answers'] ?? []);
                    $cat = !empty($q['category']) ? $q['category'] : $defaultCategory;
                    if ($cat === 'EE.08') $cat = 'INF.02';
                    if ($cat === 'EE.09') $cat = 'INF.03';

                    // Simplest and most stable ID: SHA-256 of normalized text only
                    // Category is excluded because it might vary (EE.08 vs INF.02)
                    $normalizedText = preg_replace('/[^a-z0-9]/', '', strtolower($text));
                    $stableId = hexdec(substr(hash('sha256', $normalizedText), 0, 7));

                    $questions[] = [
                        'id' => $stableId,
                        'category' => $cat,
                        'question_text' => $text,
                        'option_a' => $ansOptions['A'] ?? ($q['option_a'] ?? ''),
                        'option_b' => $ansOptions['B'] ?? ($q['option_b'] ?? ''),
                        'option_c' => $ansOptions['C'] ?? ($q['option_c'] ?? ''),
                        'option_d' => $ansOptions['D'] ?? ($q['option_d'] ?? ''),
                        'correct_answer' => strtoupper($q['correct'] ?? ($q['correct_answer'] ?? '')),
                        'explanation' => $q['explanation'] ?? ($q['explain'] ?? ''),
                        'image_url' => resolveImagePath($q['image'] ?? ($q['image_url'] ?? null))
                    ];
                    $seenQuestions[$text] = true;
                }
            }
        }
    }

    $cache[$cacheKey] = $questions;
    return $questions;
}

function normalizeQuestionRow(array $q): array {
    $cat = $q['category'] ?? '';
    if ($cat === 'EE.08') $cat = 'INF.02';
    if ($cat === 'EE.09') $cat = 'INF.03';

    return [
        'id' => (int)($q['id'] ?? 0),
        'category' => $cat,
        'question_text' => trim($q['question_text'] ?? ($q['question'] ?? '')),
        'option_a' => $q['option_a'] ?? '',
        'option_b' => $q['option_b'] ?? '',
        'option_c' => $q['option_c'] ?? '',
        'option_d' => $q['option_d'] ?? '',
        'correct_answer' => strtoupper($q['correct_answer'] ?? ($q['correct'] ?? '')),
        'explanation' => $q['explanation'] ?? '',
        'image_url' => resolveImagePath($q['image_url'] ?? ($q['image'] ?? null))
    ];
}

function getQuestionsByIds($pdo, array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (empty($ids)) return [];

    $found = [];

    if ($pdo) {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $question = normalizeQuestionRow($row);
                if ($question['question_text'] !== '') {
                    $found[(int)$question['id']] = $question;
                }
            }
        } catch (PDOException $e) {
            error_log('Targeted question lookup failed: ' . $e->getMessage());
        }
    }

    if (count($found) < count($ids)) {
        $missing = array_diff($ids, array_keys($found));
        $missingMap = array_fill_keys($missing, true);
        foreach (loadQuestions($pdo, false) as $question) {
            $questionId = (int)($question['id'] ?? 0);
            if (isset($missingMap[$questionId])) {
                $found[$questionId] = $question;
                unset($missingMap[$questionId]);
                if (empty($missingMap)) break;
            }
        }
    }

    $ordered = [];
    foreach ($ids as $id) {
        if (isset($found[$id])) $ordered[] = $found[$id];
    }

    return $ordered;
}

/**
 * Get random questions from array
 * @param array $questions Array of all questions
 * @param int $count Number of questions to return
 * @return array Random subset of questions
 */
function getRandomQuestions($questions, $count = 40) {
    if (empty($questions)) {
        return [];
    }

    $count = max(1, min($count, count($questions)));

    // Shuffle the entire pool using PHP's Mersenne Twister-based shuffle
    shuffle($questions);

    // Slice the requested number of questions
    return array_slice($questions, 0, $count);
}

function getUserQuestionProgressMap($pdo, int $userId, array $questionIds): array {
    if (!$pdo || $userId <= 0 || empty($questionIds)) {
        return [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $questionIds), static fn($id) => $id > 0)));
    if (empty($ids)) {
        return [];
    }

    $progress = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT question_id, times_seen, times_correct, is_mastered, UNIX_TIMESTAMP(last_seen) as last_seen_ts FROM user_question_progress WHERE user_id = ? AND question_id IN ($placeholders)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$userId], $chunk));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $progress[(int)$row['question_id']] = $row;
            }
        } catch (Exception $e) {
            error_log('Failed to load targeted question progress: ' . $e->getMessage());
            return [];
        }
    }

    return $progress;
}

function normalizeTestCategoryFilter($category): array {
    $items = is_array($category) ? $category : explode(',', (string)$category);
    $normalized = [];
    foreach ($items as $item) {
        $value = trim((string)$item);
        if ($value !== '') {
            $normalized[$value] = true;
        }
    }
    return array_keys($normalized);
}

function getQuestionDifficultyBucket(array $question): string {
    static $cache = [];
    $qid = (int)($question['id'] ?? 0);
    $text = (string)($question['question_text'] ?? '');
    $key = $qid . ':' . hash('sha256', $text);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $hashVal = hexdec(substr(hash('sha256', $text . $qid), 0, 4));
    $difficulty = 'medium';
    if (strlen($text) < 100 && ($hashVal % 3 === 0)) {
        $difficulty = 'easy';
    } elseif (strlen($text) > 180 || ($hashVal % 3 === 2)) {
        $difficulty = 'hard';
    }

    $cache[$key] = $difficulty;
    return $difficulty;
}

function filterQuestionPoolForTest($pdo, array $questions, $category, string $difficulty = 'all', string $scope = 'all', int $userId = 0): array {
    if (empty($questions)) {
        return [];
    }

    $categories = normalizeTestCategoryFilter($category);
    $hasCategoryFilter = !empty($categories);
    $categoryMap = $hasCategoryFilter ? array_fill_keys($categories, true) : [];
    $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'all';
    $scope = in_array($scope, ['unseen', 'incorrect', 'exclude_correct'], true) ? $scope : 'all';

    $pool = [];
    foreach ($questions as $question) {
        if ($hasCategoryFilter && !isset($categoryMap[(string)($question['category'] ?? '')])) {
            continue;
        }
        if ($difficulty !== 'all' && getQuestionDifficultyBucket($question) !== $difficulty) {
            continue;
        }
        $pool[] = $question;
    }

    if ($scope === 'all' || $userId <= 0 || empty($pool)) {
        return array_values($pool);
    }

    $poolQuestionIds = array_map(static fn($q) => (int)($q['id'] ?? 0), $pool);
    $userProgress = getUserQuestionProgressMap($pdo, $userId, $poolQuestionIds);

    return array_values(array_filter($pool, static function($question) use ($scope, $userProgress): bool {
        $qid = (int)($question['id'] ?? 0);
        $progress = $userProgress[$qid] ?? null;

        if ($scope === 'unseen') {
            return !$progress || (int)($progress['times_seen'] ?? 0) === 0;
        }
        if ($scope === 'incorrect') {
            return $progress && (int)($progress['times_correct'] ?? 0) < (int)($progress['times_seen'] ?? 0);
        }
        if ($scope === 'exclude_correct') {
            return !$progress || (int)($progress['times_correct'] ?? 0) === 0;
        }
        return true;
    }));
}

function selectQuestionsForTest($pdo, array $pool, string $mode, int $count, string $order = 'random', bool $smart = false, int $userId = 0, bool $isGuest = false): array {
    if (empty($pool)) {
        return [];
    }

    $count = max(1, min($count, count($pool)));
    $canUseSmart = !$isGuest && $smart && $userId > 0;

    if ($mode === 'exam_simulator') {
        return getRandomQuestions($pool, min(40, count($pool)));
    }
    if ($mode === 'single') {
        return $canUseSmart ? getWeightedRandomQuestions($pdo, $pool, 1, $userId) : getRandomQuestions($pool, 1);
    }
    if ($mode === 'practice' && $order === 'sequential') {
        return array_slice($pool, 0, $count);
    }

    return $canUseSmart ? getWeightedRandomQuestions($pdo, $pool, $count, $userId) : getRandomQuestions($pool, $count);
}

/**
 * Get weighted random questions using user progress to prioritize weaker items
 * New questions and questions answered incorrectly appear more often.
 * Falls back to uniform random when $pdo or $userId not provided.
 *
 * @param PDO|null $pdo
 * @param array $questions
 * @param int $count
 * @param int $userId
 * @return array
 */
function getWeightedRandomQuestions($pdo, $questions, $count = 40, $userId = 0) {
    if (empty($questions)) return [];
    $totalAvailable = count($questions);
    $count = max(1, min($count, $totalAvailable));

    if (!$pdo || !$userId) {
        return getRandomQuestions($questions, $count);
    }

    // Build id list for a single batch query
    $ids = [];
    foreach ($questions as $q) $ids[] = (int)($q['id'] ?? 0);
    $progress = getUserQuestionProgressMap($pdo, (int)$userId, $ids);

    // Compute weights per index
    $weights = [];
    $now = time();
    foreach ($questions as $idx => $q) {
        $qid = (int)($q['id'] ?? 0);
        $w = 1.0;
        if (isset($progress[$qid])) {
            $p = $progress[$qid];
            if ((int)$p['is_mastered'] === 1) {
                $w = 0.1; // deprioritize mastered
            } else {
                $times_seen = (int)($p['times_seen'] ?? 0);
                $times_correct = (int)($p['times_correct'] ?? 0);
                $accuracy = ($times_seen > 0) ? ($times_correct / $times_seen) : 0;
                // base weight increased when accuracy low
                $w = 1.0 + (1.0 - $accuracy) * 2.0; // range 1..3

                // boost slightly if recent (last 7 days) and low accuracy
                if (!empty($p['last_seen_ts'])) {
                    $age = $now - (int)$p['last_seen_ts'];
                    if ($age < 7 * 86400) {
                        $recencyFactor = (7 * 86400 - $age) / (7 * 86400);
                        $w *= 1.0 + ($recencyFactor * 0.2 * (1.0 - $accuracy));
                    }
                }
            }
        } else {
            // New question: slightly favored
            $w = 1.5;
        }
        $weights[$idx] = max(0.01, $w);
    }

    // Weighted sampling without replacement
    $available = array_keys($questions);
    $result = [];
    for ($i = 0; $i < $count && !empty($available); $i++) {
        $totalW = 0.0;
        foreach ($available as $a) $totalW += $weights[$a];
        if ($totalW <= 0) break;
        $r = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) * $totalW;
        $cum = 0.0;
        $selected = null;
        foreach ($available as $a) {
            $cum += $weights[$a];
            if ($r <= $cum) { $selected = $a; break; }
        }
        if ($selected === null) {
            $randKey = array_keys($available)[random_int(0, count($available) - 1)];
            $selected = $available[$randKey];
        }
        $result[] = $questions[$selected];
        // remove selected
        $pos = array_search($selected, $available);
        if ($pos !== false) array_splice($available, $pos, 1);
    }

    return $result;
}

/**
 * Filter questions by category
 * @param array $questions Array of questions
 * @param string $category Category name
 * @return array Filtered questions
 */
function getQuestionsByCategory($questions, $category) {
    return array_filter($questions, function($q) use ($category) {
        return isset($q['category']) && $q['category'] === $category;
    });
}

// ============================================
// Scoring and Time Formatting
// ============================================

/**
 * Calculate percentage score
 * @param int $correct Number of correct answers
 * @param int $total Total number of questions
 * @return float Percentage score (0-100)
 */
function calculateScore($correct, $total) {
    if ($total <= 0) return 0.0;
    return round(($correct / $total) * 100, 2);
}

/**
 * Format seconds to mm:ss string
 * @param int $seconds Time in seconds
 * @return string Formatted time string
 */
function formatTime($seconds) {
    $seconds = max(0, (int)$seconds);
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $remainingSeconds);
}

// ============================================
// Progress Tracking Functions
// ============================================

/**
 * Get user progress percentage
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $totalQuestions Total number of questions in system
 * @return float Progress percentage (0-100)
 */
function getProgressPercentage($pdo, $userId, $totalQuestions) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT question_id) as seen_count
            FROM user_question_progress
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $seenCount = $result['seen_count'] ?? 0;

        if ($totalQuestions <= 0) return 0.0;

        return min(100.0, round(($seenCount / $totalQuestions) * 100, 2));
    } catch (PDOException $e) {
        error_log("Error calculating progress: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Update user question progress with mastery logic
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $questionId Question ID
 * @param bool $isCorrect Whether answer was correct
 */
function updateQuestionProgress($pdo, $userId, $questionId, $isCorrect) {
    try {
        $isCorrectVal = $isCorrect ? 1 : 0;
        
        // Single query optimization: Insert or Update using DB logic for mastery
        // Mastery condition: seen >= 3 times AND accuracy >= 80%
        $stmt = $pdo->prepare("
            INSERT INTO user_question_progress 
                (user_id, question_id, times_seen, times_correct, is_mastered, last_seen)
            VALUES 
                (:user_id, :question_id, 1, :is_correct, 0, NOW())
            ON DUPLICATE KEY UPDATE 
                times_seen = times_seen + 1,
                times_correct = times_correct + :is_correct_update,
                is_mastered = CASE 
                    WHEN (times_seen + 1) >= 3 AND (times_correct + :is_correct_mastery) / (times_seen + 1) >= 0.8 
                    THEN 1 
                    ELSE is_mastered 
                END,
                last_seen = NOW()
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':question_id' => $questionId,
            ':is_correct' => $isCorrectVal,
            ':is_correct_update' => $isCorrectVal,
            ':is_correct_mastery' => $isCorrectVal
        ]);
    } catch (PDOException $e) {
        error_log("Error updating question progress: " . $e->getMessage());
    }
}

/**
 * Get user statistics
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array Associative array with user stats
 */
function getUserStats($pdo, $userId) {
    // Return keys expected by the UI (`index.php`):
    // - tests_taken
    // - average_score
    // - progress_percentage
    // - total_time_seconds
    // - mastered_questions
    $stats = [
        'tests_taken' => 0,
        'average_score' => 0.0,
        'progress_percentage' => 0.0,
        'total_time_seconds' => 0,
        'mastered_questions' => 0
    ];

    try {
        // Test result aggregates share the same completion predicate, so fetch them together.
        $completedSql = completedFullTestSql('tr', 1, true);
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_tests,
                AVG(score_percent) as average_score,
                COALESCE(SUM(time_spent), 0) as total_time
            FROM test_results tr
            WHERE tr.user_id = :user_id AND {$completedSql}
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $stats['tests_taken'] = (int)($result['total_tests'] ?? 0);
            $stats['average_score'] = round((float)($result['average_score'] ?? 0.0), 2);
            $stats['total_time_seconds'] = (int)($result['total_time'] ?? 0);
        }

        // Mastery and progress use the same user rows, so fetch them together.
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN is_mastered = 1 THEN 1 ELSE 0 END) as mastered_count,
                COUNT(DISTINCT question_id) as seen_count
            FROM user_question_progress
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['mastered_questions'] = (int)($result['mastered_count'] ?? 0);
        $seenCount = (int)($result['seen_count'] ?? 0);

        // Calculate progress percentage based on total questions available
        try {
            $questions = loadQuestions($pdo);
            $totalQuestions = is_array($questions) ? count($questions) : 0;
            if ($totalQuestions > 0) {
                $stats['progress_percentage'] = min(100.0, round(($seenCount / $totalQuestions) * 100, 2));
            } else {
                $stats['progress_percentage'] = 0.0;
            }
        } catch (Exception $e) {
            $stats['progress_percentage'] = 0.0;
        }

    } catch (PDOException $e) {
        error_log("Error getting user stats: " . $e->getMessage());
    }

    // Backwards compatibility: provide legacy keys some templates still expect
    $stats['total_tests'] = $stats['tests_taken'];
    $stats['total_time_spent'] = $stats['total_time_seconds'];

    return $stats;
}

/**
 * Get recent test results for user
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $limit Number of results to return
 * @return array Array of test result rows
 */
function getTestResults($pdo, $userId, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, score_percent, correct_answers, correct_answers AS correct_count,
                   total_questions, time_spent, mode, mode AS test_type, mode AS test_mode,
                   test_date, test_date AS completed_at, start_time,
                   ROUND(score_percent, 1) AS percentage
            FROM test_results
            WHERE user_id = :user_id
            ORDER BY test_date DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting test results: " . $e->getMessage());
        return [];
    }
}

function completedFullTestSql(string $alias = 'tr', int $minQuestions = 1, bool $excludeSingle = true): string {
    $prefix = $alias !== '' ? dbIdentifier($alias) . '.' : '';
    $idExpr = $prefix . '`id`';
    $totalExpr = $prefix . '`total_questions`';
    $parts = [
        "{$totalExpr} >= " . max(1, $minQuestions),
        "(SELECT COUNT(*) FROM test_answers ta_full WHERE ta_full.result_id = {$idExpr}) >= {$totalExpr}"
    ];
    if ($excludeSingle) {
        $parts[] = "{$prefix}`mode` <> 'single'";
    }
    return implode(' AND ', $parts);
}

function getQualifiedTestResults(PDO $pdo, int $userId, int $limit = 100, int $minQuestions = 40): array {
    try {
        $qualifiedSql = completedFullTestSql('tr', $minQuestions, true);
        $stmt = $pdo->prepare("
            SELECT tr.id, tr.score_percent, tr.correct_answers, tr.correct_answers AS correct_count,
                   tr.total_questions, tr.time_spent, tr.mode, tr.mode AS test_type, tr.mode AS test_mode,
                   tr.test_date, tr.test_date AS completed_at, tr.start_time,
                   ROUND(tr.score_percent, 1) AS percentage
            FROM test_results tr
            WHERE tr.user_id = :user_id AND {$qualifiedSql}
            ORDER BY tr.test_date DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting qualified test results: " . $e->getMessage());
        return [];
    }
}

function normalizeHistoryMode($mode, array $row = []): string {
    $raw = strtolower(trim((string)$mode));
    $aliases = [
        'exam' => 'exam',
        'practice' => 'practice',
        'single' => 'single',
        'exam_simulator' => 'exam_simulator',
        'official_cke' => 'exam_simulator',
        'oficjalny_cke' => 'exam_simulator',
        'cke' => 'exam_simulator',
        'tryb_cke' => 'exam_simulator',
    ];
    if (isset($aliases[$raw])) {
        return $aliases[$raw];
    }
    if ($raw === '') {
        return 'exam_simulator';
    }
    return 'exam';
}

function getUnifiedUserHistory(PDO $pdo, int $userId, int $limit = 200): array {
    $items = [];
    $modeLabels = [
        'exam' => 'Test',
        'practice' => 'Ćwiczenia',
        'single' => 'Jedno pytanie',
        'exam_simulator' => 'Egzamin',
    ];
    foreach (getTestResults($pdo, $userId, $limit) as $row) {
        $mode = normalizeHistoryMode($row['mode'] ?? $row['test_type'] ?? 'exam', $row);
        $items[] = [
            'kind' => 'test',
            'id' => (int)$row['id'],
            'date' => $row['completed_at'],
            'mode' => $mode,
            'label' => $modeLabels[$mode] ?? ucfirst(str_replace('_', ' ', $mode)),
            'score_percent' => (float)$row['percentage'],
            'correct_count' => (int)$row['correct_count'],
            'total_questions' => (int)$row['total_questions'],
            'time_spent' => (int)$row['time_spent'],
            'url' => 'result.php?id=' . (int)$row['id'],
        ];
    }

    try {
        ensureDuelModeColumns($pdo);
        $duelHistoryCanHide = dbColumnExists($pdo, 'duels', 'challenger_hidden_at')
            && dbColumnExists($pdo, 'duels', 'opponent_hidden_at');
        $visibilityFilter = $duelHistoryCanHide ? "
              AND (
                  (d.challenger_id = ? AND d.challenger_hidden_at IS NULL)
                  OR (d.opponent_id = ? AND d.opponent_hidden_at IS NULL)
              )" : '';
        $stmt = $pdo->prepare("
            SELECT d.*, challenger.username AS challenger_name, opponent.username AS opponent_name
            FROM duels d
            JOIN users challenger ON challenger.id = d.challenger_id
            JOIN users opponent ON opponent.id = d.opponent_id
            WHERE (d.challenger_id = ? OR d.opponent_id = ?)
              AND (d.challenger_finished_at IS NOT NULL OR d.opponent_finished_at IS NOT NULL OR d.status = 'finished')
              {$visibilityFilter}
            ORDER BY COALESCE(
                CASE WHEN d.challenger_id = ? THEN d.challenger_finished_at ELSE d.opponent_finished_at END,
                d.created_at
            ) DESC
            LIMIT ?
        ");
        $position = 1;
        $stmt->bindValue($position++, $userId, PDO::PARAM_INT);
        $stmt->bindValue($position++, $userId, PDO::PARAM_INT);
        if ($duelHistoryCanHide) {
            $stmt->bindValue($position++, $userId, PDO::PARAM_INT);
            $stmt->bindValue($position++, $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue($position++, $userId, PDO::PARAM_INT);
        $stmt->bindValue($position, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $duel) {
            $isChallenger = (int)$duel['challenger_id'] === $userId;
            $score = $isChallenger ? (float)$duel['challenger_score_percent'] : (float)$duel['opponent_score_percent'];
            $finishedAt = $isChallenger ? ($duel['challenger_finished_at'] ?: $duel['created_at']) : ($duel['opponent_finished_at'] ?: $duel['created_at']);
            $opponentName = $isChallenger ? $duel['opponent_name'] : $duel['challenger_name'];
            $items[] = [
                'kind' => 'duel',
                'id' => (int)$duel['id'],
                'date' => $finishedAt,
                'label' => 'Pojedynek: ' . $opponentName,
                'score_percent' => $score,
                'correct_count' => null,
                'total_questions' => (int)$duel['question_count'],
                'time_spent' => (int)($isChallenger ? $duel['challenger_time_spent'] : $duel['opponent_time_spent']),
                'url' => 'duels/results.php?id=' . (int)$duel['id'],
                'can_hide' => $duelHistoryCanHide,
            ];
        }
    } catch (PDOException $e) {
        error_log('Unified history duel lookup failed: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("
            SELECT ep.*, e.title, e.show_results_to_student, e.results_available_at, es.id AS session_id
            FROM exam_participants ep
            JOIN exam_sessions es ON es.id = ep.session_id
            JOIN exams e ON e.id = es.exam_id
            WHERE ep.user_id = ? AND ep.status = 'finished'
            ORDER BY COALESCE(ep.finished_at, ep.joined_at) DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $exam) {
            $visible = !empty($exam['show_results_to_student']);
            if (!empty($exam['results_available_at']) && strtotime((string)$exam['results_available_at']) > time()) {
                $visible = false;
            }
            $items[] = [
                'kind' => 'exam_session',
                'id' => (int)$exam['id'],
                'date' => $exam['finished_at'] ?: $exam['joined_at'],
                'label' => 'Sprawdzian: ' . ($exam['title'] ?? 'bez nazwy'),
                'score_percent' => $visible ? (float)$exam['score_percent'] : 0,
                'correct_count' => $visible ? (int)$exam['correct_answers'] : null,
                'total_questions' => (int)$exam['total_answered'],
                'time_spent' => (int)$exam['time_spent'],
                'url' => 'exam/finished.php?session=' . (int)$exam['session_id'],
                'locked' => !$visible,
            ];
        }
    } catch (PDOException $e) {
        error_log('Unified history exam lookup failed: ' . $e->getMessage());
    }

    usort($items, static function($a, $b) {
        return strtotime((string)$b['date']) <=> strtotime((string)$a['date']);
    });
    return array_slice($items, 0, max(1, min(250, $limit)));
}

// ============================================
// Category and Role Functions
// ============================================

/**
 * Get all available categories from questions
 * @return array Array of category names
 */
function getAllCategories($pdo = null) {
    $categories = [];
    $questions = loadQuestions($pdo);

    foreach ($questions as $question) {
        if (isset($question['category']) && !empty($question['category']) && !in_array($question['category'], $categories)) {
            $categories[] = $question['category'];
        }
    }

    sort($categories);
    return $categories;
}

function isInternalQuestionCategory($category) {
    $category = trim((string)$category);
    if ($category === '') return false;

    return (bool)preg_match('/^(custom|custom_user|własne|wlasne|import)[_\-\s]/iu', $category)
        || (bool)preg_match('/^custom_user$/iu', $category);
}

function getPublicCategories($pdo = null) {
    return array_values(array_filter(getAllCategories($pdo), static function($category) {
        return !isInternalQuestionCategory($category);
    }));
}

function getPublicQuestionCategories($pdo = null) {
    return getPublicCategories($pdo);
}

/**
 * Get stats for all categories including question counts and user progress
 */
function getCategoryStats($pdo, $userId) {
    $questions = loadQuestions($pdo);
    $stats = [];
    
    // Total per category
    foreach ($questions as $q) {
        $cat = !empty($q['category']) ? $q['category'] : 'Inne';
        if (isInternalQuestionCategory($cat)) continue;
        if (!isset($stats[$cat])) {
            $stats[$cat] = ['total' => 0, 'mastered' => 0, 'seen' => 0, 'correct' => 0];
        }
        $stats[$cat]['total']++;
    }
    
    // Mastered per category (is_mastered = 1 in user_question_progress)
    try {
        $stmt = $pdo->prepare("
            SELECT question_id, times_seen, times_correct, is_mastered
            FROM user_question_progress
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $progressRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $progressById = [];
        foreach ($progressRows as $row) {
            $progressById[(int)$row['question_id']] = $row;
        }
        
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            if (isset($progressById[$qid])) {
                $cat = !empty($q['category']) ? $q['category'] : 'Inne';
                if (isInternalQuestionCategory($cat)) continue;
                if (isset($stats[$cat])) {
                    if ((int)$progressById[$qid]['times_seen'] > 0) $stats[$cat]['seen']++;
                    if ((int)$progressById[$qid]['times_correct'] > 0) $stats[$cat]['correct']++;
                    if ((int)$progressById[$qid]['is_mastered'] === 1) $stats[$cat]['mastered']++;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error in getCategoryStats: " . $e->getMessage());
    }
    
    return $stats;
}

function getQualificationInfo($code) {
    $code = strtoupper(trim((string)$code));
    $map = [
        'INF.02' => [
            'title' => 'INF.02',
            'description' => 'Administracja i eksploatacja komputerów, urządzeń peryferyjnych i sieci lokalnych: od montażu stanowiska, przez konfigurację systemów, po diagnozę awarii i dokumentowanie napraw.',
            'learn' => 'Systemy Windows i Linux osobno, montaż i diagnostyka PC, adresacja IP, usługi DHCP/DNS, udziały SMB, uprawnienia NTFS, drukarki, backup oraz podstawy bezpieczeństwa stacji roboczej.',
            'jobs' => ['technik informatyk', 'helpdesk IT', 'administrator junior', 'serwisant komputerowy', 'technik wsparcia infrastruktury'],
            'salary' => 'około 4500-7500 PLN brutto na start, zależnie od regionu i zakresu obowiązków',
            'tech' => ['Windows', 'Linux', 'TCP/IP', 'LAN', 'PowerShell', 'Bash', 'Active Directory'],
            'paths' => ['helpdesk -> administrator', 'serwis -> specjalista IT', 'sieci LAN -> network admin', 'wsparcie szkolne -> opiekun pracowni IT'],
            'related' => ['INF.03', 'INF.04']
        ],
        'INF.03' => [
            'title' => 'INF.03',
            'description' => 'Tworzenie stron, aplikacji internetowych i baz danych: projektujesz strukturę HTML/CSS, piszesz logikę JS/PHP, obsługujesz formularze i zapisujesz dane w SQL.',
            'learn' => 'Semantyczny HTML, responsywny CSS, JavaScript, PHP z PDO, MySQL, relacje w bazie, zapytania JOIN, CRUD, walidacja danych, obsługa błędów i bezpieczne wypisywanie treści.',
            'jobs' => ['web developer junior', 'administrator WWW', 'programista PHP junior', 'database assistant', 'junior full-stack developer'],
            'salary' => 'około 5000-9000 PLN brutto dla stanowisk juniorskich',
            'tech' => ['HTML', 'CSS', 'JavaScript', 'PHP', 'MySQL', 'Git'],
            'paths' => ['frontend junior -> full stack', 'PHP junior -> backend', 'SQL -> database developer', 'WordPress/admin WWW -> web developer'],
            'related' => ['INF.02', 'INF.04']
        ],
        'INF.04' => [
            'title' => 'INF.04',
            'description' => 'Projektowanie, programowanie i testowanie aplikacji: analiza wymagań, model danych, implementacja, obsługa wyjątków, dokumentacja oraz testy działania.',
            'learn' => 'Algorytmy, aplikacje desktopowe i webowe, API, struktury danych, testy manualne i automatyczne, repozytorium Git, diagramy, dokumentacja techniczna i przypadki graniczne.',
            'jobs' => ['programista junior', 'tester manualny/automatyzujący', 'web app developer', 'junior backend developer'],
            'salary' => 'około 5500-10000 PLN brutto na start',
            'tech' => ['JavaScript', 'PHP', 'SQL', 'Git', 'API', 'testy'],
            'paths' => ['junior developer -> mid developer', 'tester -> QA automation', 'backend -> API developer', 'analityk wymagań -> developer'],
            'related' => ['INF.03']
        ],
        'INF.07' => [
            'title' => 'INF.07',
            'description' => 'Montaż i konfiguracja sieci lokalnych oraz administrowanie systemami serwerowymi: okablowanie, VLAN-y, routing, usługi sieciowe i kontrola dostępu.',
            'learn' => 'Patchcordy RJ-45, przełączniki, routery, VLAN, routing statyczny, DHCP relay, DNS, Active Directory, Windows Server, podstawy Linux Server, firewall i diagnostyka połączeń.',
            'jobs' => ['technik sieciowy', 'administrator LAN junior', 'support network', 'technik teleinformatyk'],
            'salary' => 'około 4500-8000 PLN brutto',
            'tech' => ['TCP/IP', 'VLAN', 'routing', 'switching', 'Linux', 'Windows Server'],
            'paths' => ['support -> network admin', 'technik -> specjalista sieci', 'administrator LAN -> security/network engineer'],
            'related' => ['INF.02']
        ],
        'INF.08' => [
            'title' => 'INF.08',
            'description' => 'Eksploatacja systemów, urządzeń peryferyjnych i sieci: serwis sprzętu, instalacja wielu systemów, konfiguracja drukarek, backupy i utrzymanie stanowisk.',
            'learn' => 'Diagnostyka PC, BIOS/UEFI, dyski i SMART, instalacja Windows, instalacja Linux, dual boot i GRUB, sterowniki, drukarki/skanery, kopie zapasowe, harmonogram zadań i podstawowa automatyzacja.',
            'jobs' => ['serwisant IT', 'helpdesk', 'technik wsparcia', 'opiekun pracowni komputerowej'],
            'salary' => 'około 4300-7500 PLN brutto',
            'tech' => ['Windows', 'Linux', 'LAN', 'diagnostyka PC'],
            'paths' => ['helpdesk -> admin', 'serwis -> infrastruktura IT', 'technician -> endpoint management'],
            'related' => ['INF.02']
        ],
    ];
    return $map[$code] ?? [
        'title' => $code,
        'description' => 'Kwalifikacja informatyczna obejmująca praktyczne umiejętności zawodowe.',
        'learn' => 'Materiał zgodny z pytaniami dostępnymi w bazie.',
        'jobs' => ['technik informatyk', 'specjalista IT junior'],
        'salary' => 'zależne od specjalizacji i doświadczenia',
        'tech' => ['systemy komputerowe', 'sieci', 'aplikacje'],
        'paths' => ['praktyka szkolna -> junior IT -> specjalizacja'],
        'related' => []
    ];
}

/**
 * Check if user has admin role
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return bool True if admin, false otherwise
 */
function isAdmin($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT role FROM users
            WHERE id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && isset($result['role']) && roleHasAdminAccess($result['role']);
    } catch (PDOException $e) {
        error_log("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

// ============================================
// User management helper functions (for admin panel)
// ============================================

/**
 * Search users for the admin panel with unique native-PDO placeholders.
 *
 * @return array{users: array, total: int, error: bool}
 */
function searchAdminUsers(PDO $pdo, string $search, int $limit = 20, int $offset = 0): array {
    $search = mb_substr(trim($search), 0, 100);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    if ($search === '') {
        return [
            'users' => getUsers($pdo, $limit, $offset),
            'total' => getUsersCount($pdo),
            'error' => false,
        ];
    }

    $like = '%' . $search . '%';
    $searchPlaceholders = [':q_username', ':q_email', ':q_first_name', ':q_last_name', ':q_class'];
    try {
        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, role, class, avatar_path, xp, profile_public, stats_public, allow_friend_requests, searchable, is_verified, ranking_visible, created_at, last_login, is_banned, ban_expires_at FROM users WHERE username LIKE :q_username OR email LIKE :q_email OR first_name LIKE :q_first_name OR last_name LIKE :q_last_name OR class LIKE :q_class ORDER BY CASE role WHEN 'admin' THEN 'Administratorzy' WHEN 'dyrektor' THEN 'Dyrekcja' WHEN 'teacher' THEN 'Nauczyciele' WHEN 'wujek_luki' THEN 'Wujek Luki' ELSE COALESCE(NULLIF(class, ''), 'ZZZ') END, id DESC LIMIT :limit OFFSET :offset");
        foreach ($searchPlaceholders as $placeholder) {
            $stmt->bindValue($placeholder, $like, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE :q_username OR email LIKE :q_email OR first_name LIKE :q_first_name OR last_name LIKE :q_last_name OR class LIKE :q_class");
        foreach ($searchPlaceholders as $placeholder) {
            $countStmt->bindValue($placeholder, $like, PDO::PARAM_STR);
        }
        $countStmt->execute();

        return [
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => (int)$countStmt->fetchColumn(),
            'error' => false,
        ];
    } catch (PDOException $e) {
        error_log('Admin user search failed: ' . $e->getMessage());
        return ['users' => [], 'total' => 0, 'error' => true];
    }
}

/**
 * Get a page of users
 * @param PDO $pdo
 * @param int $limit
 * @param int $offset
 * @return array
 */
function getUsers($pdo, $limit = 50, $offset = 0) {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, role, class, avatar_path, xp, profile_public, stats_public, allow_friend_requests, searchable, is_verified, ranking_visible, created_at, last_login, is_banned, ban_expires_at FROM users ORDER BY CASE role WHEN 'admin' THEN 'Administratorzy' WHEN 'dyrektor' THEN 'Dyrekcja' WHEN 'teacher' THEN 'Nauczyciele' WHEN 'wujek_luki' THEN 'Wujek Luki' ELSE COALESCE(NULLIF(class, ''), 'ZZZ') END, id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total number of users
 * @param PDO $pdo
 * @return int
 */
function getUsersCount($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($res['cnt'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error counting users: " . $e->getMessage());
        return 0;
    }
}

/**
 * Update a user's password (hashes before storing)
 * @param PDO $pdo
 * @param int $userId
 * @param string $newPassword
 * @return bool
 */
function updateUserPassword($pdo, $userId, $newPassword) {
    try {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, session_version = COALESCE(session_version, 1) + 1 WHERE id = :id");
        return $stmt->execute([':hash' => $hash, ':id' => $userId]);
    } catch (PDOException $e) {
        error_log("Error updating user password: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a user
 * @param PDO $pdo
 * @param int $userId
 * @return bool
 */
function deleteLocalAvatarFile(string $avatarPath): bool {
    $avatarPath = trim($avatarPath);
    if ($avatarPath === '' || !preg_match('#^uploads/avatars/user_\d+_[a-f0-9]{12}\.webp$#', $avatarPath)) {
        return false;
    }
    $absoluteAvatarPath = dirname(__DIR__) . '/' . $avatarPath;
    if (!is_file($absoluteAvatarPath)) {
        return false;
    }
    return @unlink($absoluteAvatarPath);
}

function answerOptionText(array $question, string $letter): string {
    $letter = strtolower(trim($letter));
    return trim((string)($question['option_' . $letter] ?? ''));
}

function buildDistractorExplanation(array $question, string $letter, string $optionText, string $questionText = ''): string {
    $text = mb_strtolower(trim($optionText), 'UTF-8');
    if ($text === '') {
        return '';
    }
    if (str_contains($text, 'modem analog')) {
        return 'modem analogowy służy głównie do transmisji danych przez linię telefoniczną, a nie do zamiany połączenia PSTN na rozmowę VoIP.';
    }
    if (str_contains($text, 'mostek') || str_contains($text, 'bridge')) {
        return 'mostek łączy segmenty sieci komputerowej i nie obsługuje bezpośrednio analogowych aparatów telefonicznych.';
    }
    if (str_contains($text, 'repet') || str_contains($text, 'wzmacni')) {
        return 'repeater wzmacnia lub regeneruje sygnał w sieci, ale nie konwertuje telefonu analogowego na usługi internetowe.';
    }
    if (str_contains($text, 'voip') || str_contains($text, 'bramk')) {
        return 'ta odpowiedź opisuje urządzenie łączące telefonię analogową z transmisją pakietową.';
    }
    if (str_contains($text, 'dns')) {
        return 'DNS rozwiązuje nazwy domen na adresy IP, więc pasuje tylko wtedy, gdy pytanie dotyczy nazw hostów.';
    }
    if (str_contains($text, 'dhcp')) {
        return 'DHCP przydziela konfigurację IP klientom, więc nie zastępuje usługi ani urządzenia wskazanego w pytaniu.';
    }
    if (str_contains($text, 'router')) {
        return 'router przekazuje ruch między sieciami; jest poprawny tylko wtedy, gdy pytanie dotyczy routingu lub bramy sieciowej.';
    }
    if (str_contains($text, 'switch') || str_contains($text, 'przełącz')) {
        return 'przełącznik działa głównie w sieci lokalnej i nie realizuje funkcji opisanej przez poprawną odpowiedź.';
    }
    if (str_contains($text, 'mask')) {
        return 'maska podsieci opisuje część sieciową adresu, ale sama nie wykonuje akcji wymaganej w pytaniu.';
    }

    // Generic context-aware fallback
    if (str_contains($text, 'sterownik')) {
        return 'problem ze sterownikami zazwyczaj objawia się błędami sprzętowymi (BSoD) po wczytaniu jądra, a nie na etapie samego startu narzędzi naprawczych przed załadowaniem środowiska.';
    }
    if (str_contains($text, 'dysk') || str_contains($text, 'miejsc')) {
        return 'brak miejsca na dysku nie powoduje błędów rozruchowych o takim charakterze w tej fazie bootowania.';
    }
    if (str_contains($text, 'klawiatur') || str_contains($text, 'mysz')) {
        return 'urządzenia peryferyjne są sygnalizowane przez BIOS/UEFI wcześniej i nie generują takiego błędu startowego Windows.';
    }
    if (str_contains($text, 'pamięć') || str_contains($text, 'ram')) {
        return 'błędy pamięci operacyjnej częściej powodują sprzętowe zawieszenia lub losowe restarty całego systemu.';
    }
    if (str_contains($text, 'zasilacz')) {
        return 'uszkodzenie zasilacza prowadzi do wyłączania się sprzętu lub niemożności jego uruchomienia, a nie do komunikatów systemu operacyjnego.';
    }

    return 'ta odpowiedź dotyczy innego aspektu działania systemu i nie jest bezpośrednią przyczyną opisanego problemu.';
}

function buildQuestionExplanation(array $question, string $userAnswer = '', ?bool $isCorrect = null): string {
    $existing = trim((string)($question['explanation'] ?? ''));
    if ($existing !== '') return $existing;

    $correct = strtoupper(trim((string)($question['correct_answer'] ?? ($question['correct'] ?? ''))));
    $user = strtoupper(trim($userAnswer));
    $correctText = answerOptionText($question, $correct);
    $userText = answerOptionText($question, $user);
    $questionText = trim((string)($question['question_text'] ?? ($question['question'] ?? '')));

    $correctLabel = $correctText !== '' ? "{$correct}. {$correctText}" : $correct;
    $parts = ["Wyjaśnienie:"];
    $parts[] = $correctText !== ''
        ? "• Poprawna odpowiedź: {$correctLabel}."
        : "• Poprawna odpowiedź: {$correct}.";
    if ($questionText !== '') {
        $parts[] = "Treść pytania: {$questionText}";
    }
    if ($user !== '' && $user !== '-' && $user !== $correct) {
        $userLabel = $userText !== '' ? "{$user}. {$userText}" : $user;
        $parts[] = "Wybrano: {$userLabel}.";
    } elseif ($isCorrect === true || ($user !== '' && $user === $correct)) {
        $parts[] = "Wybrano poprawną odpowiedź.";
    }
    $distractors = [];
    foreach (['A', 'B', 'C', 'D'] as $letter) {
        if ($letter === $correct) continue;
        $option = answerOptionText($question, $letter);
        if ($option === '') continue;
        $reason = buildDistractorExplanation($question, $letter, $option, $questionText);
        if ($reason === '') continue;
        $distractors[] = "• {$letter}. {$option} - " . $reason;
    }
    if (!empty($distractors)) {
        $parts[] = "";
        $parts[] = "Dlaczego nie reszta?";
        array_push($parts, ...$distractors);
    }
    return implode("\n", $parts);
}

function deleteUserAvatar(PDO $pdo, int $userId, bool $clearColumn = true): bool {
    if ($userId <= 0) return false;
    try {
        $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $avatarPath = (string)($stmt->fetchColumn() ?: '');
        if ($clearColumn) {
            $pdo->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?")->execute([$userId]);
        }
        return deleteLocalAvatarFile($avatarPath);
    } catch (PDOException $e) {
        error_log('Delete user avatar failed: ' . $e->getMessage());
        return false;
    }
}

function deleteUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $avatarPath = (string)($stmt->fetchColumn() ?: '');
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $ok = $stmt->execute([':id' => $userId]);
        if ($ok) {
            deleteLocalAvatarFile($avatarPath);
        }
        return $ok;
    } catch (PDOException $e) {
        error_log("Error deleting user: " . $e->getMessage());
        return false;
    }
}

function adminPanelUserGroupLabel(array $user): string {
    $role = (string)($user['role'] ?? 'user');
    return match ($role) {
        'admin' => 'Administratorzy',
        'dyrektor' => 'Dyrekcja',
        'teacher' => 'Nauczyciele',
        'wujek_luki' => 'Wujek Luki',
        default => trim((string)($user['class'] ?? '')) !== '' ? trim((string)$user['class']) : 'Bez klasy',
    };
}

/**
 * Set a user's role
 * @param PDO $pdo
 * @param int $userId
 * @param string $role ('user'|'teacher'|'admin'|'dyrektor'|'wujek_luki')
 * @return bool
 */
function setUserRole($pdo, $userId, $role) {
    if (!in_array($role, assignableRoleValues(), true)) return false;
    try {
        ensurePlatformEnhancements($pdo);
        $rankingVisible = roleParticipatesInRanking($role) ? 1 : 0;
        $verified = in_array($role, privilegedStaffRoles(), true) ? 1 : 0;
        if (dbColumnExists($pdo, 'users', 'verified_at')) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET role = ?,
                    ranking_visible = ?,
                    is_verified = GREATEST(is_verified, ?),
                    verified_at = CASE WHEN ? = 1 AND verified_at IS NULL THEN NOW() ELSE verified_at END
                WHERE id = ?
            ");
            $success = $stmt->execute([$role, $rankingVisible, $verified, $verified, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, ranking_visible = ?, is_verified = GREATEST(is_verified, ?) WHERE id = ?");
            $success = $stmt->execute([$role, $rankingVisible, $verified, $userId]);
        }
        if ($success && in_array($role, privilegedStaffRoles(), true)) {
            if ($role === 'teacher') {
                $pdo->prepare("UPDATE users SET class = NULL, class_year = NULL, class_suffix = NULL WHERE id = ?")->execute([$userId]);
            }
            $pdo->prepare("UPDATE users SET profile_public = 0, stats_public = 0, searchable = 0, allow_friend_requests = 0 WHERE id = ?")->execute([$userId]);
            syncTeacherAdminFriends($pdo, $userId);
        }
        return $success;
    } catch (PDOException $e) {
        error_log("Error setting user role: " . $e->getMessage());
        return false;
    }
}

function notifyOptionalMfaForRole(PDO $pdo, int $userId, string $role): bool {
    if ($userId <= 0 || !in_array($role, ['teacher', 'dyrektor'], true)) {
        return false;
    }
    if (function_exists('mfaUserHasEnabled') && mfaUserHasEnabled($pdo, $userId)) {
        return false;
    }

    return addNotification(
        $pdo,
        $userId,
        'mfa_optional_prompt',
        'Czy włączyć 2 etapowe uwierzytelnianie?',
        'mfa.php',
        'mfa_optional_prompt:' . $userId . ':' . $role
    );
}

function clearOptionalMfaPrompt(PDO $pdo, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND type = 'mfa_optional_prompt'");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log('Optional MFA prompt cleanup failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Automatically link admins and teachers as friends
 */
function syncTeacherAdminFriends($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();

        if (in_array($role, privilegedStaffRoles(), true)) {
            $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'teacher', 'dyrektor')");
            $peers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($peers as $peerId) {
                if ($peerId != $userId) {
                    $u1 = min($userId, $peerId);
                    $u2 = max($userId, $peerId);
                    
                    $check = $pdo->prepare("SELECT status FROM friends WHERE user_id = ? AND friend_id = ?");
                    $check->execute([$u1, $u2]);
                    $existing = $check->fetchColumn();
                    
                    if (!$existing) {
                        $insert = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                        $insert->execute([$u1, $u2]);
                    } elseif ($existing !== 'accepted') {
                        $update = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
                        $update->execute([$u1, $u2]);
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error syncing friends: " . $e->getMessage());
    }
}

// ============================================
// Validation Functions
// ============================================

/**
 * Validate email format
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return validateAllowedEmail($email);
}

/**
 * Validate username format
 * @param string $username Username to validate
 * @return bool True if valid (alphanumeric, 3-20 chars), false otherwise
 */
function validateUsername($username) {
    $username = trim($username);
    $length = strlen($username);

    if ($length < 3 || $length > 20) {
        return false;
    }

    // Alphanumeric only (letters and numbers)
    return preg_match('/^[a-zA-Z0-9]+$/', $username) === 1;
}

// ============================================
// Mission & Ranking Functions
// ============================================

function applyMissionStatAliases(array $stats): array {
    $aliases = [
        'daily_warmup' => 'tests_taken',
        'daily_grind' => 'tests_taken',
        'daily_answer_sprint' => 'correct_answers',
        'daily_answer_storm' => 'correct_answers',
        'daily_no_mistake' => 'streak_correct',
        'daily_perfect_chain' => 'streak_correct',
        'daily_precision_80' => 'score_avg',
        'daily_precision_90' => 'score_avg',
        'daily_quick_repeat' => 'review_questions',
        'daily_deep_repeat' => 'review_questions',
        'weekly_test_runner' => 'weekly_mastery',
        'weekly_marathon' => 'weekly_mastery',
        'weekly_precision_80' => 'weekly_accuracy',
        'weekly_precision_90' => 'weekly_accuracy',
        'weekly_answer_bank' => 'weekly_answers',
        'weekly_answer_sprint' => 'weekly_answers',
        'monthly_exam_grind' => 'monthly_champion',
        'monthly_consistency' => 'monthly_champion',
        'monthly_precision_80' => 'monthly_accuracy',
        'monthly_precision_90' => 'monthly_accuracy',
        'monthly_answer_bank' => 'monthly_answers',
        'monthly_answer_storm' => 'monthly_answers',
        'daily_legendary_streak' => 'streak_correct',
        'daily_titan' => 'tests_taken',
        'daily_perfect_precision' => 'score_avg',
        'weekly_marathon_pro' => 'weekly_mastery',
        'weekly_accuracy_elite' => 'weekly_accuracy',
        'weekly_answer_avalanche' => 'weekly_answers',
        'monthly_grandmaster' => 'monthly_champion',
        'monthly_perfect_accuracy' => 'monthly_accuracy',
        'monthly_answer_tsunami' => 'monthly_answers',
        'monthly_super_consistent' => 'monthly_champion'
    ];

    foreach ($aliases as $alias => $source) {
        $stats[$alias] = $stats[$source] ?? 0;
    }

    return $stats;
}

function missionQualifiedTestSql(string $alias = 'tr'): string {
    return completedFullTestSql($alias, 40, true);
}

function testResultQualifiesForMissions(PDO $pdo, int $resultId, ?int $totalQuestions = null): bool {
    if ($resultId <= 0) return false;
    try {
        $stmt = $pdo->prepare("
            SELECT tr.mode, tr.total_questions,
                   (SELECT COUNT(*) FROM test_answers ta WHERE ta.result_id = tr.id) AS answered_count
            FROM test_results tr
            WHERE tr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$resultId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $total = (int)($row['total_questions'] ?? $totalQuestions ?? 0);
        return ($row['mode'] ?? '') !== 'single'
            && $total >= 40
            && (int)($row['answered_count'] ?? 0) >= $total;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get user stats for a specific date (defaults to today)
 */
function getUserDailyStats($pdo, $userId, $date = null) {
    if (!$date) $date = date('Y-m-d');
    
    $stats = [
        'tests_taken' => 0,
        'correct_answers' => 0,
        'streak_correct' => 0,
        'score_avg' => 0,
        'review_questions' => 0,
        'streak_daily' => 0,
        'weekly_mastery' => 0,
        'weekly_accuracy' => 0,
        'weekly_answers' => 0,
        'monthly_champion' => 0,
        'monthly_accuracy' => 0,
        'monthly_answers' => 0
    ];
    
    try {
        // Questions correctly solved today
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM test_answers ta
            JOIN test_results tr ON ta.result_id = tr.id
            WHERE tr.user_id = ? AND DATE(tr.test_date) = ? AND ta.is_correct = 1
        ");
        $stmt->execute([$userId, $date]);
        $stats['correct_answers'] = (int)$stmt->fetchColumn();
        $stats['review_questions'] = $stats['correct_answers'];
        
        // Tests completed today and average score
        $qualifiedSql = missionQualifiedTestSql('tr');
        $stmt = $pdo->prepare("SELECT COUNT(*), AVG(score_percent) FROM test_results tr WHERE user_id = ? AND DATE(test_date) = ? AND {$qualifiedSql}");
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch();
        $stats['tests_taken'] = (int)($row['COUNT(*)'] ?? 0);
        $stats['score_avg'] = round((float)($row['AVG(score_percent)'] ?? 0.0), 1);
        $stats['weekly_accuracy'] = $stats['score_avg'];
        $stats['monthly_accuracy'] = $stats['score_avg'];
        
        // Streak - last 20 answers today
        $stmt = $pdo->prepare("
            SELECT ta.is_correct 
            FROM test_answers ta
            JOIN test_results tr ON ta.result_id = tr.id
            WHERE tr.user_id = ? AND DATE(tr.test_date) = ? 
            ORDER BY ta.id DESC LIMIT 20
        ");
        $stmt->execute([$userId, $date]);
        $answers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxStreak = 0;
        $currentStreak = 0;
        foreach ($answers as $correct) {
            if ($correct) {
                $currentStreak++;
                if ($currentStreak > $maxStreak) $maxStreak = $currentStreak;
            } else {
                $currentStreak = 0;
            }
        }
        $stats['streak_correct'] = $maxStreak;

        // Daily test streak: consecutive days with a completed test
        $stmt = $pdo->prepare("SELECT DISTINCT DATE(test_date) AS day FROM test_results tr WHERE user_id = ? AND DATE(test_date) <= ? AND {$qualifiedSql} ORDER BY day DESC LIMIT 30");
        $stmt->execute([$userId, $date]);
        $days = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'day');
        $expected = new DateTime($date);
        $dailyStreak = 0;
        foreach ($days as $day) {
            if ($day === $expected->format('Y-m-d')) {
                $dailyStreak++;
                $expected->modify('-1 day');
            } else {
                break;
            }
        }
        $stats['streak_daily'] = $dailyStreak;

        // Weekly mastery: tests completed in the last 7 days including today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_results tr WHERE user_id = ? AND test_date >= DATE_SUB(?, INTERVAL 6 DAY) AND test_date < DATE_ADD(?, INTERVAL 1 DAY) AND {$qualifiedSql}");
        $stmt->execute([$userId, $date, $date]);
        $stats['weekly_mastery'] = (int)$stmt->fetchColumn();
        $stats['weekly_answers'] = $stats['correct_answers'];

        // Monthly champion: tests completed during the current month
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_results tr WHERE user_id = ? AND YEAR(test_date) = YEAR(?) AND MONTH(test_date) = MONTH(?) AND {$qualifiedSql}");
        $stmt->execute([$userId, $date, $date]);
        $stats['monthly_champion'] = (int)$stmt->fetchColumn();
        $stats['monthly_answers'] = $stats['correct_answers'];
        
    } catch (PDOException $e) {
        error_log("Error fetching daily stats: " . $e->getMessage());
    }
    
    return applyMissionStatAliases($stats);
}

function getMissionPeriodWindow($period = 'daily') {
    $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';
    $start = new DateTime('today');
    if ($period === 'weekly') {
        $start->modify('monday this week');
    } elseif ($period === 'monthly') {
        $start->modify('first day of this month');
    }
    $end = clone $start;
    if ($period === 'daily') {
        $end->modify('+1 day');
    } elseif ($period === 'weekly') {
        $end->modify('+1 week');
    } else {
        $end->modify('first day of next month');
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function getUserMissionStats($pdo, $userId, $period = 'daily') {
    if ($period === 'daily') {
        return getUserDailyStats($pdo, $userId);
    }

    [$startDate, $endDate] = getMissionPeriodWindow($period);
    $stats = [
        'tests_taken' => 0,
        'correct_answers' => 0,
        'streak_correct' => 0,
        'score_avg' => 0,
        'review_questions' => 0,
        'streak_daily' => 0,
        'weekly_mastery' => 0,
        'weekly_accuracy' => 0,
        'weekly_answers' => 0,
        'monthly_champion' => 0,
        'monthly_accuracy' => 0,
        'monthly_answers' => 0
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM test_answers ta
            JOIN test_results tr ON ta.result_id = tr.id
            WHERE tr.user_id = ? AND tr.test_date >= ? AND tr.test_date < ? AND ta.is_correct = 1
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        $stats['correct_answers'] = (int)$stmt->fetchColumn();
        $stats['review_questions'] = $stats['correct_answers'];

        $qualifiedSql = missionQualifiedTestSql('tr');
        $stmt = $pdo->prepare("SELECT COUNT(*), AVG(score_percent) FROM test_results tr WHERE user_id = ? AND test_date >= ? AND test_date < ? AND {$qualifiedSql}");
        $stmt->execute([$userId, $startDate, $endDate]);
        $row = $stmt->fetch();
        $stats['tests_taken'] = (int)($row['COUNT(*)'] ?? 0);
        $stats['score_avg'] = round((float)($row['AVG(score_percent)'] ?? 0.0), 1);
        $stats['weekly_accuracy'] = $stats['score_avg'];
        $stats['monthly_accuracy'] = $stats['score_avg'];

        $stmt = $pdo->prepare("
            SELECT ta.is_correct
            FROM test_answers ta
            JOIN test_results tr ON ta.result_id = tr.id
            WHERE tr.user_id = ? AND tr.test_date >= ? AND tr.test_date < ?
            ORDER BY ta.id DESC LIMIT 50
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        $answers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $currentStreak = 0;
        foreach ($answers as $correct) {
            if ($correct) {
                $currentStreak++;
            } else {
                break;
            }
        }
        $stats['streak_correct'] = $currentStreak;

        $daily = getUserDailyStats($pdo, $userId);
        $stats['streak_daily'] = $daily['streak_daily'] ?? 0;
        $stats['weekly_mastery'] = $period === 'weekly' ? $stats['tests_taken'] : ($daily['weekly_mastery'] ?? 0);
        $stats['weekly_answers'] = $stats['correct_answers'];
        $stats['monthly_champion'] = $period === 'monthly' ? $stats['tests_taken'] : ($daily['monthly_champion'] ?? 0);
        $stats['monthly_answers'] = $stats['correct_answers'];
    } catch (PDOException $e) {
        error_log("Error fetching {$period} mission stats: " . $e->getMessage());
    }

    return applyMissionStatAliases($stats);
}

function getMissionPoolByPeriod(array $missionsJson, $period = 'daily') {
    return array_filter($missionsJson, static function($mission) use ($period) {
        return ($mission['period'] ?? 'daily') === $period;
    });
}

function fetchMissionsForPeriod($pdo, $userId, $assignedDate, array $missionTypes) {
    if (empty($missionTypes)) return [];
    $placeholders = implode(',', array_fill(0, count($missionTypes), '?'));
    $stmt = $pdo->prepare("SELECT * FROM user_daily_missions WHERE user_id = ? AND assigned_date = ? AND mission_type IN ($placeholders) ORDER BY id ASC");
    $stmt->execute(array_merge([$userId, $assignedDate], array_values($missionTypes)));
    return $stmt->fetchAll();
}

/**
 * Assign and update missions for user by reset period
 */
function syncUserMissionsForPeriod($pdo, $userId, $period = 'daily', $missionCount = 3) {
    $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';
    [$assignedDate] = getMissionPeriodWindow($period);
    $stats = getUserMissionStats($pdo, $userId, $period);

    $missionsJson = json_decode(file_get_contents(__DIR__ . '/../data/missions.json'), true);
    if (!is_array($missionsJson)) $missionsJson = [];
    $missionPool = getMissionPoolByPeriod($missionsJson, $period);
    if (empty($missionPool)) $missionPool = $missionsJson;
    $missionTypes = array_keys($missionPool);

    $userMissions = fetchMissionsForPeriod($pdo, $userId, $assignedDate, $missionTypes);
    $wasEmpty = empty($userMissions);

    if (count($userMissions) < $missionCount) {
        $assignedTypes = array_map(function($m) { return $m['mission_type']; }, $userMissions);
        $remainingTypes = array_diff($missionTypes, $assignedTypes);
        
        if (!empty($remainingTypes)) {
            $needed = $missionCount - count($userMissions);
            shuffle($remainingTypes);
            $selectedKeys = array_slice($remainingTypes, 0, $needed);
            
            foreach ($selectedKeys as $key) {
                if (!isset($missionPool[$key])) continue;
                $m = $missionPool[$key];
                $target = $m['base_target'];
                $desc = str_replace('{target}', (string)$target, $m['desc']);
                $reward = $m['reward_xp'];
                $stmt = $pdo->prepare("INSERT INTO user_daily_missions (user_id, mission_type, mission_description, current_value, target_value, xp_reward, assigned_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $key, $desc, $stats[$key] ?? 0, $target, $reward, $assignedDate]);
            }
            
            $userMissions = fetchMissionsForPeriod($pdo, $userId, $assignedDate, $missionTypes);
        }
        
        if ($wasEmpty && !empty($userMissions)) {
            $label = ['daily' => 'codzienne', 'weekly' => 'tygodniowe', 'monthly' => 'miesięczne'][$period];
            addNotification($pdo, $userId, $period . '_missions_refresh', "Twoje {$label} misje zostały odświeżone! Sprawdź nowe wyzwania.", 'goals.php');
        }
    }

    // Update progress/description for all active missions
    foreach ($userMissions as $m) {
        $key = $m['mission_type'];
        $currentVal = $stats[$key] ?? 0;
        $isComp = ($currentVal >= $m['target_value']) ? 1 : 0;
        $description = (string)($m['mission_description'] ?? '');
        if (strpos($description, '{target}') !== false && isset($missionPool[$key]['desc'])) {
            $description = str_replace('{target}', (string)$m['target_value'], $missionPool[$key]['desc']);
        }
        
        $pdo->prepare("UPDATE user_daily_missions SET current_value = ?, is_completed = ?, mission_description = ? WHERE id = ?")
            ->execute([$currentVal, $isComp, $description, $m['id']]);
    }
    
    $userMissions = fetchMissionsForPeriod($pdo, $userId, $assignedDate, $missionTypes);
    
    return ['missions' => $userMissions, 'pool' => $missionPool, 'period' => $period, 'assigned_date' => $assignedDate];
}

function syncUserMissions($pdo, $userId) {
    return syncUserMissionsForPeriod($pdo, $userId, 'daily', 3);
}

function completeEligibleMissionsAfterTest($pdo, $userId, $resultId, $totalQuestions) {
    $qualifiedForAnyMission = testResultQualifiesForMissions($pdo, (int)$resultId, (int)$totalQuestions);
    foreach (['daily', 'weekly', 'monthly'] as $period) {
        $missionsData = syncUserMissionsForPeriod($pdo, $userId, $period, 3);
        $pool = $missionsData['pool'];

        foreach ($missionsData['missions'] as $mission) {
            if ((int)$mission['is_completed'] !== 1 || !empty($mission['completed_at'])) continue;

            $desc = mb_strtolower((string)($mission['mission_description'] ?? ''), 'UTF-8');
            $allowsAny = mb_strpos($desc, 'dowolny test') !== false || mb_strpos($desc, 'dowolne testy') !== false || $period !== 'daily';
            if (!$qualifiedForAnyMission) continue;
            if ($totalQuestions < 40 && !$allowsAny) continue;

            $reward = (int)($pool[$mission['mission_type']]['reward_xp'] ?? $mission['xp_reward'] ?? 0);
            if ($reward <= 0) continue;

            awardXp($pdo, $userId, $reward, 'mission', (int)$mission['id'], $mission['mission_description']);
            $pdo->prepare("UPDATE user_daily_missions SET completed_at = NOW() WHERE id = ? AND completed_at IS NULL")
                ->execute([(int)$mission['id']]);
            $title = $pool[$mission['mission_type']]['title'] ?? 'Misja';
            addNotification($pdo, $userId, 'mission_complete', "Gratulacje! Ukończyłeś misję: $title. Otrzymujesz +$reward XP.", 'goals.php');
        }
    }
}

/**
 * Finalize a test, calculate score, grant XP and save results
 */
/**
 * Get current rank of a user
 */
function getUserRank($pdo, $userId) {
    try {
        ensurePlatformEnhancements($pdo);
        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $roleStmt->execute([$userId]);
        if (!roleParticipatesInRanking((string)$roleStmt->fetchColumn())) {
            return 0;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*) + 1
            FROM users ranked
            WHERE ranked.xp > (SELECT xp FROM users WHERE id = ?)
              AND ranked.role IN ('user','wujek_luki')
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function ensureUserActiveTestsTable(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }
    if (!dbRuntimeSchemaUpdatesEnabled()) {
        $ensured = true;
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_active_tests (
        user_id INT NOT NULL PRIMARY KEY,
        payload LONGTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbAddIndexIfMissing($pdo, 'user_active_tests', 'idx_updated_at', '(updated_at)');
    $ensured = true;
}

function cleanupStaleActiveTests(PDO $pdo, int $maxAgeDays = 14): void {
    static $cleaned = false;
    if ($cleaned) {
        return;
    }
    $cleaned = true;
    $maxAgeDays = max(1, min(90, $maxAgeDays));
    try {
        ensureUserActiveTestsTable($pdo);
        $cutoff = date('Y-m-d H:i:s', time() - ($maxAgeDays * 86400));
        $stmt = $pdo->prepare('DELETE FROM user_active_tests WHERE updated_at < ?');
        $stmt->execute([$cutoff]);
    } catch (PDOException $e) {
        error_log('cleanupStaleActiveTests failed: ' . $e->getMessage());
    }
}

function hasActiveTestInSession(): bool {
    return isset($_SESSION['current_test'])
        && is_array($_SESSION['current_test'])
        && !empty($_SESSION['current_test']['questions']);
}

function getActiveTestSummary(?array $test): array {
    if (!$test || empty($test['questions'])) {
        return [];
    }
    $modeLabels = [
        'exam' => 'Test',
        'practice' => 'Ćwiczenia',
        'single' => 'Jedno pytanie',
        'exam_simulator' => 'Egzamin',
    ];
    $mode = (string)($test['mode'] ?? 'exam');
    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $categories = trim((string)($config['category'] ?? ''));
    return [
        'mode' => $mode,
        'mode_label' => $modeLabels[$mode] ?? 'Test',
        'total' => count($test['questions']),
        'answered' => count($test['answers'] ?? []),
        'current' => min(count($test['questions']), (int)($test['current'] ?? 0) + 1),
        'categories' => $categories,
        'categories_label' => $categories !== '' ? $categories : 'wszystkie dostępne',
    ];
}

function persistActiveTestToDb(PDO $pdo, int $userId, array $test): void {
    if ($userId <= 0) {
        return;
    }
    try {
        ensureUserActiveTestsTable($pdo);
        cleanupStaleActiveTests($pdo);
        $payload = json_encode($test, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO user_active_tests (user_id, payload, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = NOW()"
        );
        $stmt->execute([$userId, $payload]);
    } catch (PDOException $e) {
        error_log('persistActiveTestToDb failed: ' . $e->getMessage());
    }
}

function clearPersistedActiveTest(?PDO $pdo, ?int $userId): void {
    if (!$pdo || !$userId || $userId <= 0) {
        return;
    }
    try {
        ensureUserActiveTestsTable($pdo);
        $pdo->prepare('DELETE FROM user_active_tests WHERE user_id = ?')->execute([$userId]);
    } catch (PDOException $e) {
        error_log('clearPersistedActiveTest failed: ' . $e->getMessage());
    }
}

function restoreActiveTestFromDb(PDO $pdo, int $userId): ?array {
    if ($userId <= 0) {
        return null;
    }
    try {
        ensureUserActiveTestsTable($pdo);
        cleanupStaleActiveTests($pdo);
        $stmt = $pdo->prepare('SELECT payload FROM user_active_tests WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $payload = $stmt->fetchColumn();
        if (!is_string($payload) || $payload === '') {
            return null;
        }
        $test = json_decode($payload, true);
        if (!is_array($test) || empty($test['questions']) || !isset($test['start_time'], $test['mode'])) {
            clearPersistedActiveTest($pdo, $userId);
            return null;
        }
        if (!isset($test['answers']) || !is_array($test['answers'])) {
            $test['answers'] = [];
        }
        normalizeSingleQuestionTestState($test);
        normalizeTestAnswerChecks($test);
        return $test;
    } catch (PDOException $e) {
        error_log('restoreActiveTestFromDb failed: ' . $e->getMessage());
        return null;
    }
}

function restoreActiveTestForUser(?PDO $pdo, ?int $userId): void {
    if (hasActiveTestInSession() || !$pdo || !$userId || $userId <= 0) {
        return;
    }
    $restored = restoreActiveTestFromDb($pdo, $userId);
    if (is_array($restored)) {
        $_SESSION['current_test'] = $restored;
    }
}

function saveCurrentTest(?PDO $pdo, ?int $userId, array $test): void {
    normalizeSingleQuestionTestState($test);
    normalizeTestAnswerChecks($test);
    $_SESSION['current_test'] = $test;
    if ($pdo && $userId && $userId > 0) {
        persistActiveTestToDb($pdo, $userId, $test);
    }
}

function cancelActiveTest(?PDO $pdo, ?int $userId): void {
    unset($_SESSION['current_test'], $_SESSION['test_start_time']);
    clearPersistedActiveTest($pdo, $userId);
}

function getActiveTestConfigFromSession(): array {
    if (!hasActiveTestInSession()) {
        return [];
    }
    $test = $_SESSION['current_test'];
    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $result = [
        'mode' => (string)($test['mode'] ?? 'exam'),
    ];
    if (array_key_exists('category', $config)) {
        $result['category'] = (string)$config['category'];
    }
    if (isset($config['count'])) {
        $result['count'] = (int)$config['count'];
    }
    if (isset($config['time'])) {
        $result['timeLimit'] = (int)$config['time'];
    }
    if (isset($config['time_option'])) {
        $result['timeOption'] = (string)$config['time_option'];
    }
    if (isset($config['time_per_question'])) {
        $result['timePerQuestion'] = (int)$config['time_per_question'];
    }
    if (isset($config['difficulty'])) {
        $result['difficulty'] = (string)$config['difficulty'];
    }
    if (isset($config['scope'])) {
        $result['scope'] = (string)$config['scope'];
    }
    if (isset($config['order'])) {
        $result['order'] = (string)$config['order'];
    }
    if (isset($config['preset'])) {
        $result['preset'] = (string)$config['preset'];
    }
    if (array_key_exists('smart', $config)) {
        $result['smart'] = !empty($config['smart']);
    }
    return $result;
}

function getTestQuestionTimeLimit(array $test): int {
    if (!empty($test['question_time_limit'])) {
        return max(0, (int)$test['question_time_limit']);
    }
    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $opt = (string)($config['time_option'] ?? '');
    if ($opt === '30s') {
        return 30;
    }
    if ($opt === '60s') {
        return 60;
    }
    if ($opt === 'per_question_custom') {
        return max(15, (int)($config['time_per_question'] ?? 60));
    }
    return 0;
}

function touchTestQuestionStart(array &$test): void {
    $test['question_start_time'] = time();
}

function testAnswerMatchesQuestion(array $question, $answer): bool {
    if (!is_array($answer)) {
        return false;
    }

    $questionId = (int)($question['id'] ?? 0);
    $answerQuestionId = (int)($answer['question_id'] ?? 0);
    return $questionId <= 0 || $answerQuestionId === $questionId;
}

function testHasReviewedCurrentAnswer(array $test): bool {
    if (($test['phase'] ?? '') !== 'reviewing') {
        return false;
    }

    $currentIndex = max(0, (int)($test['current'] ?? 0));
    $question = $test['questions'][$currentIndex] ?? null;
    return is_array($question)
        && testAnswerMatchesQuestion($question, $test['answers'][$currentIndex] ?? null);
}

function testCanAdvanceFromReview(array $test): bool {
    if (!testHasReviewedCurrentAnswer($test)) {
        return false;
    }
    $mode = (string)($test['mode'] ?? '');
    if (in_array($mode, ['practice', 'single'], true)) {
        return true;
    }
    // Allow advancing from review in exam mode when the answer was revealed by check_answer
    $currentIndex = max(0, (int)($test['current'] ?? 0));
    $answer = is_array($test['answers'][$currentIndex] ?? null) ? $test['answers'][$currentIndex] : [];
    return !empty($answer['revealed_by_check']);
}

function singleQuestionCompletedResultId(array $test): int {
    if (($test['mode'] ?? '') !== 'single' || !testHasReviewedCurrentAnswer($test)) {
        return 0;
    }

    $currentIndex = max(0, (int)($test['current'] ?? 0));
    return max(0, (int)($test['answers'][$currentIndex]['history_id'] ?? 0));
}

function recordSingleQuestionResultId(array &$test, int $resultId): void {
    if ($resultId <= 0 || ($test['mode'] ?? '') !== 'single' || !testHasReviewedCurrentAnswer($test)) {
        return;
    }

    $currentIndex = max(0, (int)($test['current'] ?? 0));
    $test['answers'][$currentIndex]['history_id'] = $resultId;
    if (!is_array($test['last_result'] ?? null)) {
        $test['last_result'] = testReviewResultFromAnswer($test, $currentIndex);
    }
    $test['last_result']['history_id'] = $resultId;
}

function ensureSingleQuestionResultSaved(PDO $pdo, array &$test, int $userId): int {
    if ($userId <= 0 || ($test['mode'] ?? '') !== 'single' || !testHasReviewedCurrentAnswer($test)) {
        return 0;
    }

    $existingId = singleQuestionCompletedResultId($test);
    if ($existingId > 0) {
        return $existingId;
    }

    $currentIndex = max(0, (int)($test['current'] ?? 0));
    $question = $test['questions'][$currentIndex] ?? [];
    $answer = $test['answers'][$currentIndex] ?? [];
    $userAnswer = (string)($answer['user_answer'] ?? '');
    $correctAnswer = strtoupper(trim((string)($question['correct_answer'] ?? '')));
    $isCorrect = $userAnswer !== '' && strtoupper(trim($userAnswer)) === $correctAnswer;
    $resultId = saveSingleQuestionResult($pdo, $userId, $question, $userAnswer, $isCorrect);
    if ($resultId > 0) {
        recordSingleQuestionResultId($test, $resultId);
        $_SESSION['last_result_id'] = $resultId;
    }

    return $resultId;
}

function singleQuestionCategoryFilter(array $test): string {
    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $configured = trim((string)($config['category'] ?? ''));
    if ($configured !== '') {
        return $configured;
    }

    return trim((string)($test['questions'][0]['category'] ?? ''));
}

function getTestQuestionTimeRemaining(array $test, int $perQuestionLimit): int {
    if ($perQuestionLimit <= 0) {
        return 0;
    }
    $started = (int)($test['question_start_time'] ?? 0);
    if ($started <= 0) {
        return $perQuestionLimit;
    }
    return max(0, $perQuestionLimit - (time() - $started));
}

function normalizeSingleQuestionTestState(array &$test): bool {
    if (($test['mode'] ?? '') !== 'single') {
        return false;
    }

    $before = serialize($test);
    $questions = is_array($test['questions'] ?? null) ? array_values($test['questions']) : [];
    $answers = is_array($test['answers'] ?? null) ? $test['answers'] : [];
    $currentIndex = max(0, min(count($questions) - 1, (int)($test['current'] ?? 0)));
    $currentQuestion = $questions[$currentIndex] ?? null;
    $candidateAnswer = $answers[$currentIndex] ?? null;
    $currentAnswer = is_array($currentQuestion) && testAnswerMatchesQuestion($currentQuestion, $candidateAnswer)
        ? $candidateAnswer
        : null;

    if (count($questions) > 1) {
        $test['questions'] = isset($questions[$currentIndex]) ? [$questions[$currentIndex]] : [];
    } elseif (isset($test['questions']) && is_array($test['questions'])) {
        $test['questions'] = $questions;
    }
    $test['current'] = 0;

    if ($currentAnswer !== null && empty($currentAnswer['revealed_by_check'])) {
        $test['answers'] = [0 => $currentAnswer];
        if (($test['phase'] ?? '') === 'reviewing') {
            $previousResult = is_array($test['last_result'] ?? null) ? $test['last_result'] : [];
            $historyId = (int)($currentAnswer['history_id'] ?? 0);
            if (
                $historyId <= 0
                && (int)($previousResult['question_id'] ?? 0) === (int)($currentQuestion['id'] ?? 0)
                && strtoupper(trim((string)($previousResult['user_answer'] ?? ''))) === strtoupper(trim((string)($currentAnswer['user_answer'] ?? '')))
                && strtoupper(trim((string)($previousResult['correct_answer'] ?? ''))) === strtoupper(trim((string)($currentQuestion['correct_answer'] ?? '')))
            ) {
                $historyId = (int)($previousResult['history_id'] ?? 0);
            }
            $test['last_result'] = testReviewResultFromAnswer($test, 0);
            if ($historyId > 0) {
                recordSingleQuestionResultId($test, $historyId);
            }
        } else {
            unset($test['last_result']);
        }
    } else {
        $test['answers'] = [];
        $test['phase'] = 'answering';
        unset($test['last_result']);
    }

    $test['time_limit'] = 0;
    $test['question_time_limit'] = 0;
    $test['answer_check_limit'] = 0;
    $test['answer_check_used'] = 0;
    unset($test['question_start_time']);

    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $test['config'] = array_merge($config, [
        'count' => 1,
        'time' => 0,
        'time_option' => 'unlimited',
        'time_per_question' => 0,
    ]);

    return serialize($test) !== $before;
}

function normalizeTestAnswerChecks(array &$test): void {
    $limit = ($test['mode'] ?? '') === 'single'
        ? 0
        : (array_key_exists('answer_check_limit', $test) ? (int)$test['answer_check_limit'] : 3);
    $limit = max(0, $limit);
    $used = array_key_exists('answer_check_used', $test) ? (int)$test['answer_check_used'] : 0;
    $test['answer_check_limit'] = $limit;
    $test['answer_check_used'] = max(0, min($limit, $used));
}

function testAnswerCheckModeAllowed(array $test): bool {
    return in_array((string)($test['mode'] ?? 'exam'), ['exam', 'practice'], true);
}

function testAnswerCheckPayload(array $test): array {
    normalizeTestAnswerChecks($test);
    $limit = (int)$test['answer_check_limit'];
    $used = (int)$test['answer_check_used'];
    $remaining = max(0, $limit - $used);
    $modeAllowed = testAnswerCheckModeAllowed($test);
    $disabledReason = '';
    if (!$modeAllowed) {
        $disabledReason = 'mode';
    } elseif ($remaining <= 0) {
        $disabledReason = 'limit';
    }

    return [
        'answer_check_limit' => $limit,
        'answer_check_used' => $used,
        'answer_check_remaining' => $remaining,
        'answer_check_available' => $modeAllowed && $remaining > 0,
        'answer_check_mode_allowed' => $modeAllowed,
        'answer_check_disabled_reason' => $disabledReason,
    ];
}

function testReviewResultFromAnswer(array $test, int $currentIdx): array {
    normalizeTestAnswerChecks($test);
    $questions = $test['questions'] ?? [];
    $question = $questions[$currentIdx] ?? [];
    $answer = is_array($test['answers'][$currentIdx] ?? null) ? $test['answers'][$currentIdx] : [];
    $total = count($questions);
    $mode = (string)($test['mode'] ?? 'exam');
    $correctAnswer = strtoupper(trim((string)($question['correct_answer'] ?? '')));
    $userAnswer = strtoupper(trim((string)($answer['user_answer'] ?? '')));
    $isCorrect = $userAnswer !== '' && $correctAnswer !== '' && $userAnswer === $correctAnswer;
    $revealedByCheck = !empty($answer['revealed_by_check']);

    $result = [
        'question_id' => (int)($question['id'] ?? 0),
        'is_correct' => $isCorrect,
        'user_answer' => $userAnswer,
        'user_answer_text' => answerOptionText($question, $userAnswer),
        'correct_answer' => $correctAnswer,
        'correct_answer_text' => answerOptionText($question, $correctAnswer),
        'explanation' => buildQuestionExplanation($question, $userAnswer, $isCorrect),
        'is_last' => $mode === 'single' ? false : ($currentIdx >= $total - 1),
        'revealed_by_check' => $revealedByCheck,
        'checked_before_answer' => !empty($answer['checked_before_answer']),
        'answer_check_attempt_number' => (int)($answer['answer_check_attempt_number'] ?? 0),
        'answer_check_used_at' => (int)($answer['answer_check_used_at'] ?? 0),
    ];

    if ($revealedByCheck) {
        if ($isCorrect) {
            $result['review_note'] = 'Sprawdzenie zużyło jedną pomoc, ale zaznaczona odpowiedź była poprawna i została zaliczona.';
        } elseif ($userAnswer !== '') {
            $result['review_note'] = 'Sprawdzenie zużyło jedną pomoc. Zaznaczona odpowiedź była błędna, więc pytanie zaliczono jako błędne.';
        } else {
            $result['review_note'] = 'Sprawdzenie zużyło jedną pomoc. Brak zaznaczonej odpowiedzi oznacza błędne pytanie.';
        }
    }

    return array_merge($result, testAnswerCheckPayload($test));
}

function restoreCheckedQuestionReview(array &$test): bool {
    $currentIdx = max(0, (int)($test['current'] ?? 0));
    $answer = is_array($test['answers'][$currentIdx] ?? null) ? $test['answers'][$currentIdx] : [];
    
    $shouldRestore = false;
    $mode = (string)($test['mode'] ?? 'exam');
    if (in_array($mode, ['practice', 'single'], true)) {
        if (isset($answer['user_answer']) && $answer['user_answer'] !== '') {
            $shouldRestore = true;
        }
    } else {
        if (!empty($answer['revealed_by_check'])) {
            $shouldRestore = true;
        }
    }

    if (!$shouldRestore) {
        return false;
    }
    if (($test['phase'] ?? '') === 'reviewing' && !empty($test['last_result'])) {
        return false;
    }
    $test['phase'] = 'reviewing';
    $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
    return true;
}

function applyTestAnswerCheck(array &$test, int $questionId, string $userAnswer, ?PDO $pdo = null, ?int $userId = null, bool $isGuest = false): array {
    normalizeTestAnswerChecks($test);
    $currentIdx = max(0, (int)($test['current'] ?? 0));
    $questions = $test['questions'] ?? [];

    if (!isset($questions[$currentIdx])) {
        return ['success' => false, 'error' => 'Brak aktywnego pytania.'];
    }
    if ((int)($questions[$currentIdx]['id'] ?? 0) !== $questionId) {
        return ['success' => false, 'error' => 'Nieprawidłowe pytanie.'];
    }
    if (!testAnswerCheckModeAllowed($test)) {
        return ['success' => false, 'error' => 'Sprawdzanie odpowiedzi jest wyłączone w tym trybie.'];
    }

    $existingAnswer = is_array($test['answers'][$currentIdx] ?? null) ? $test['answers'][$currentIdx] : [];
    if (!empty($existingAnswer['revealed_by_check'])) {
        $test['phase'] = 'reviewing';
        $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);
        return ['success' => true, 'result' => $test['last_result'], 'already_checked' => true];
    }
    if (($test['phase'] ?? 'answering') !== 'answering') {
        return ['success' => false, 'error' => 'To pytanie jest juz w trybie podgladu.'];
    }

    $payload = testAnswerCheckPayload($test);
    if (empty($payload['answer_check_available'])) {
        return ['success' => false, 'error' => 'Limit sprawdzeń odpowiedzi został wykorzystany.'];
    }

    $selected = strtoupper(trim($userAnswer));
    if (!in_array($selected, ['A', 'B', 'C', 'D'], true)) {
        $selected = '';
    }

    $question = $questions[$currentIdx];
    $correctAnswer = strtoupper(trim((string)($question['correct_answer'] ?? '')));
    $isCorrect = $selected !== '' && $correctAnswer !== '' && $selected === $correctAnswer;

    $nextCheckNumber = (int)$test['answer_check_used'] + 1;
    $test['answer_check_used'] = min((int)$test['answer_check_limit'], $nextCheckNumber);
    $test['answers'][$currentIdx] = [
        'question_id' => $questionId,
        'user_answer' => $selected,
        'correct' => $isCorrect,
        'revealed_by_check' => true,
        'checked_before_answer' => true,
        'answer_check_attempt_number' => $nextCheckNumber,
        'answer_check_used_at' => time(),
    ];

    if (!$isGuest && $pdo && $userId && $userId > 0) {
        updateQuestionProgress($pdo, $userId, $questionId, $isCorrect);
        if (($test['mode'] ?? '') === 'single') {
            $historyId = saveSingleQuestionResult($pdo, $userId, $question, $selected, $isCorrect);
            if ($historyId > 0) {
                $_SESSION['last_result_id'] = $historyId;
            }
        }
    }

    $test['phase'] = 'reviewing';
    $test['last_result'] = testReviewResultFromAnswer($test, $currentIdx);

    return ['success' => true, 'result' => $test['last_result'], 'already_checked' => false];
}

function prepareNextSingleQuestion(PDO $pdo, array &$test, ?int $userId, bool $isGuest = false): array {
    $previousQuestionId = (int)($test['questions'][0]['id'] ?? 0);
    $categoryFilter = singleQuestionCategoryFilter($test);
    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $difficulty = (string)($config['difficulty'] ?? 'all');
    $scope = (string)($config['scope'] ?? 'all');
    $allQuestions = loadQuestions($pdo, false);
    $pool = filterQuestionPoolForTest($pdo, $allQuestions, $categoryFilter, $difficulty, $scope, $userId ?? 0);
    if (empty($pool)) {
        return ['success' => false, 'error' => 'No questions available in selected category'];
    }
    if ($previousQuestionId > 0 && count($pool) > 1) {
        $pool = array_values(array_filter($pool, static function ($question) use ($previousQuestionId): bool {
            return (int)($question['id'] ?? 0) !== $previousQuestionId;
        }));
    }

    $smart = !empty($test['smart']) || !empty($test['config']['smart']);
    $newQuestionSet = selectQuestionsForTest($pdo, $pool, 'single', 1, 'random', $smart, $userId ?? 0, $isGuest);
    if (empty($newQuestionSet)) {
        return ['success' => false, 'error' => 'No questions available in selected category'];
    }

    $newQuestion = $newQuestionSet[0];
    $test['questions'] = [$newQuestion];
    $test['current'] = 0;
    $test['answers'] = [];
    $test['phase'] = 'answering';
    $test['last_result'] = null;
    touchTestQuestionStart($test);

    return ['success' => true, 'question' => $newQuestion];
}

function testDisallowsPreviousQuestion(array $test): bool {
    $config = is_array($test['config'] ?? null) ? $test['config'] : [];
    $opt = (string)($config['time_option'] ?? '');
    return in_array($opt, ['30s', '60s'], true);
}

/**
 * Finalize a test, calculate score, grant XP and save results
 */
function finishTest($pdo, $userId, $test) {
    // Get old rank before XP update
    $oldRank = getUserRank($pdo, $userId);
    $allowedModes = ['exam', 'practice', 'single', 'exam_simulator'];
    $testMode = in_array((string)($test['mode'] ?? 'exam'), $allowedModes, true) ? (string)$test['mode'] : 'exam';

    $totalQ     = count($test['questions']);
    $correctCount = 0;
    foreach ($test['questions'] as $index => $q) {
        $userAnswer = strtoupper(trim((string)($test['answers'][$index]['user_answer'] ?? '')));
        $correctAnswer = strtoupper(trim((string)($q['correct_answer'] ?? '')));
        if ($userAnswer !== '' && $correctAnswer !== '' && $userAnswer === $correctAnswer) {
            $correctCount++;
        }
    }
    $scorePct   = $totalQ > 0 ? round(($correctCount / $totalQ) * 100, 2) : 0;
    $timeSpent  = time() - $test['start_time'];

    // Save test summary
    $startTime = date('Y-m-d H:i:s', $test['start_time']);
    
    $excludeFromRanking = $test['exclude_from_ranking'] ?? 0;

    $skipUnrankedQuota = false;
    
    // If excluding from ranking, check and update daily usage atomically.
    if ($excludeFromRanking && !$skipUnrankedQuota) {
        try {
            $today = date('Y-m-d');
            if (dbRuntimeSchemaUpdatesEnabled()) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS unranked_usage (
                    user_id INT NOT NULL,
                    used_date DATE NOT NULL,
                    usage_count INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (user_id, used_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            $stmt = $pdo->prepare("SELECT usage_count FROM unranked_usage WHERE user_id = ? AND used_date = ? FOR UPDATE");
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $commitUsage = true;
            } else {
                $commitUsage = false;
            }
            $stmt->execute([$userId, $today]);
            $usage = $stmt->fetch();
            if ($usage && $usage['usage_count'] >= 2) {
                $excludeFromRanking = 0; // Limit reached, force ranked
            } else {
                $pdo->prepare("INSERT INTO unranked_usage (user_id, used_date, usage_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE usage_count = usage_count + 1")
                    ->execute([$userId, $today]);
            }
            if (!empty($commitUsage)) $pdo->commit();
        } catch (PDOException $e) {
            if (!empty($commitUsage) && $pdo->inTransaction()) $pdo->rollBack();
            error_log("Unranked usage error: " . $e->getMessage());
        }
    }
    
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO test_results (user_id, total_questions, correct_answers, score_percent, time_spent, mode, start_time, exclude_from_ranking)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $totalQ, $correctCount, $scorePct, $timeSpent, $testMode, $startTime, $excludeFromRanking]);
    } catch (PDOException $e) {
        // Fallback if exclude_from_ranking column doesn't exist
        if ($e->getCode() == '42S22') {
            $insertWithoutRankingFlag = static function () use ($pdo, $userId, $totalQ, $correctCount, $scorePct, $timeSpent, $testMode): void {
                $stmt = $pdo->prepare(
                    "INSERT INTO test_results (user_id, total_questions, correct_answers, score_percent, time_spent, mode)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $totalQ, $correctCount, $scorePct, $timeSpent, $testMode]);
            };
            if (!dbRuntimeSchemaUpdatesEnabled()) {
                $insertWithoutRankingFlag();
            } else {
                try {
                    $pdo->exec("ALTER TABLE test_results ADD COLUMN exclude_from_ranking TINYINT(1) DEFAULT 0 AFTER mode");
                    $stmt = $pdo->prepare(
                        "INSERT INTO test_results (user_id, total_questions, correct_answers, score_percent, time_spent, mode, start_time, exclude_from_ranking)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$userId, $totalQ, $correctCount, $scorePct, $timeSpent, $testMode, $startTime, $excludeFromRanking]);
                } catch (PDOException $e2) {
                    $insertWithoutRankingFlag();
                }
            }
        } else {
            throw $e;
        }
    }
    
    $resultId = (int)$pdo->lastInsertId();

    // Optimized: Save individual answers to test_answers using a transaction and batch insert
    $pdo->beginTransaction();
    try {
        $values = [];
        $params = [];
        foreach ($test['questions'] as $index => $q) {
            $qId = (int)$q['id'];
            $userAnswer = '';
            $isCorrect = 0;
            
            if (isset($test['answers'][$index])) {
                $userAnswer = strtoupper(trim((string)$test['answers'][$index]['user_answer']));
            }

            $correctAnswer = strtoupper(trim((string)($q['correct_answer'] ?? '')));
            $isCorrect = ($userAnswer !== '' && $correctAnswer !== '' && $userAnswer === $correctAnswer) ? 1 : 0;
            $values[] = "(?, ?, ?, ?, ?)";
            array_push($params, $resultId, $qId, $userAnswer, $correctAnswer, $isCorrect);
        }

        if (!empty($values)) {
            $sql = "INSERT INTO test_answers (result_id, question_id, user_answer, correct_answer, is_correct) VALUES " . implode(',', $values);
            $pdo->prepare($sql)->execute($params);
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error saving test answers: " . $e->getMessage());
    }

    $_SESSION['last_result_id'] = $resultId;
    
    $baseXpDelta = calculateRankedTestXpDelta($scorePct);
    $xpMeta = applyActiveRankingMultiplier($pdo, $baseXpDelta);
    $xpDelta = (int)$xpMeta['amount'];

    if (($test['mode'] ?? 'exam') !== 'single' && !$excludeFromRanking && $xpDelta !== 0) {
        $description = $xpDelta > 0 ? 'XP za wynik testu rankingowego' : 'Korekta XP za wynik testu rankingowego';
        if (!empty($xpMeta['event'])) {
            $description .= ' (' . $xpMeta['event']['name'] . ' x' . number_format((float)$xpMeta['event']['multiplier'], 2, '.', '') . ')';
        }
        awardXp($pdo, $userId, $xpDelta, 'test', $resultId, $description);
        
        // Check for rank changes
        $newRank = getUserRank($pdo, $userId);
        if ($newRank < $oldRank) {
            addNotification($pdo, $userId, 'rank_up', "Awansowałeś w rankingu! Twoja nowa pozycja to #$newRank.");
        } elseif ($newRank > $oldRank) {
            addNotification($pdo, $userId, 'rank_down', "Spadłeś w rankingu na pozycję #$newRank. Nie poddawaj się!");
        }
    }

    completeEligibleMissionsAfterTest($pdo, $userId, $resultId, $totalQ);
    pruneUserTestHistory($pdo, (int)$userId, 50);
    
    cancelActiveTest($pdo, (int)$userId);
    return $resultId;
}

function finishGuestTest(array $test): string {
    $questions = $test['questions'] ?? [];
    $answers = $test['answers'] ?? [];
    $totalQuestions = count($questions);
    $correct = 0;
    $answerRows = [];

    foreach ($questions as $idx => $question) {
        $saved = $answers[$idx] ?? [];
        $correctAnswer = strtoupper(trim((string)($question['correct_answer'] ?? '')));
        $userAnswer = strtoupper(trim((string)($saved['user_answer'] ?? '')));
        $isCorrect = $userAnswer !== '' && $correctAnswer !== '' && $userAnswer === $correctAnswer;
        if ($isCorrect) $correct++;
        $answerRows[] = [
            'question_id' => (int)($question['id'] ?? 0),
            'user_answer' => $userAnswer,
            'correct_answer' => $correctAnswer,
            'is_correct' => $isCorrect ? 1 : 0,
            'question_text' => (string)($question['question_text'] ?? ''),
            'question_category' => (string)($question['category'] ?? ''),
            'option_a' => (string)($question['option_a'] ?? ''),
            'option_b' => (string)($question['option_b'] ?? ''),
            'option_c' => (string)($question['option_c'] ?? ''),
            'option_d' => (string)($question['option_d'] ?? ''),
            'explanation' => (string)($question['explanation'] ?? ''),
        ];
    }

    $resultId = bin2hex(secureRandomBytes(8));
    $timeSpent = isset($test['start_time']) ? max(0, time() - (int)$test['start_time']) : 0;
    $_SESSION['guest_test_results'] = $_SESSION['guest_test_results'] ?? [];
    $_SESSION['guest_test_results'][$resultId] = [
        'row' => [
            'id' => $resultId,
            'user_id' => null,
            'correct_answers' => $correct,
            'total_questions' => $totalQuestions,
            'score_percent' => $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 2) : 0,
            'time_spent' => $timeSpent,
            'mode' => in_array((string)($test['mode'] ?? 'exam'), ['exam', 'practice', 'single', 'exam_simulator'], true) ? (string)$test['mode'] : 'exam',
            'test_date' => date('Y-m-d H:i:s'),
        ],
        'answers' => $answerRows,
    ];
    $_SESSION['last_guest_result_id'] = $resultId;
    if (count($_SESSION['guest_test_results']) > 5) {
        $_SESSION['guest_test_results'] = array_slice($_SESSION['guest_test_results'], -5, null, true);
    }

    cancelActiveTest(null, null);
    return $resultId;
}

/**
 * Save a single-question result into history without altering session or awarding XP.
 * Returns inserted test_results ID on success, 0 on failure.
 */
function saveSingleQuestionResult($pdo, $userId, $question, $userAnswer, $isCorrect) {
    try {
        $questionId = (int)($question['id'] ?? 0);
        $answerKey = strtoupper(trim((string)$userAnswer));
        $sessionKey = 'single_result_dedupe_' . $questionId . '_' . hash('sha256', $answerKey);
        if (!empty($_SESSION[$sessionKey]['id']) && (time() - (int)($_SESSION[$sessionKey]['time'] ?? 0)) <= 10) {
            return (int)$_SESSION[$sessionKey]['id'];
        }
        $stmt = $pdo->prepare("
            SELECT tr.id
            FROM test_results tr
            JOIN test_answers ta ON ta.result_id = tr.id
            WHERE tr.user_id = ? AND tr.mode = 'single' AND tr.test_date >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
              AND ta.question_id = ? AND ta.user_answer <=> ?
            ORDER BY tr.id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $questionId, $answerKey]);
        $existingId = (int)$stmt->fetchColumn();
        if ($existingId > 0) {
            $_SESSION[$sessionKey] = ['id' => $existingId, 'time' => time()];
            return $existingId;
        }

        $correctAnswer = strtoupper(trim((string)($question['correct_answer'] ?? ($question['correct'] ?? ''))));
        $isCorrect = ($answerKey !== '' && $correctAnswer !== '' && $answerKey === $correctAnswer);
        $totalQ = 1;
        $correctCount = $isCorrect ? 1 : 0;
        $scorePct = $correctCount * 100;
        $timeSpent = 0;
        $startTime = date('Y-m-d H:i:s');

        $excludeFromRanking = 1;

        // Try insert with exclude_from_ranking column (newer schema)
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO test_results (user_id, total_questions, correct_answers, score_percent, time_spent, mode, start_time, exclude_from_ranking)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $totalQ, $correctCount, $scorePct, $timeSpent, 'single', $startTime, $excludeFromRanking]);
        } catch (PDOException $e) {
            // Fallback if column doesn't exist
            if ($e->getCode() == '42S22') {
                $stmt = $pdo->prepare(
                    "INSERT INTO test_results (user_id, total_questions, correct_answers, score_percent, time_spent, mode, start_time)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $totalQ, $correctCount, $scorePct, $timeSpent, 'single', $startTime]);
            } else {
                throw $e;
            }
        }

        $resultId = (int)$pdo->lastInsertId();

        // Insert single answer row
        $stmt = $pdo->prepare("INSERT INTO test_answers (result_id, question_id, user_answer, correct_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $resultId,
            $questionId,
            $answerKey,
            $correctAnswer,
            $isCorrect ? 1 : 0
        ]);

        $_SESSION[$sessionKey] = ['id' => $resultId, 'time' => time()];
        pruneUserTestHistory($pdo, (int)$userId, 50);
        return $resultId;
    } catch (PDOException $e) {
        error_log("Error saving single question result: " . $e->getMessage());
        return 0;
    }
}

function pruneUserTestHistory(PDO $pdo, int $userId, int $limit = 50): void {
    if ($userId <= 0) return;
    try {
        $limit = max(1, $limit);
        $stmt = $pdo->prepare("
            SELECT id FROM test_results
            WHERE user_id = ?
            ORDER BY test_date DESC, id DESC
            LIMIT 1000 OFFSET {$limit}
        ");
        $stmt->execute([$userId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM test_answers WHERE result_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM test_results WHERE user_id = ? AND id IN ($placeholders)")->execute(array_merge([$userId], $ids));
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Prune test history failed: ' . $e->getMessage());
    }
}

function deleteUserTestResult(PDO $pdo, int $userId, int $resultId): bool {
    if ($userId <= 0 || $resultId <= 0) return false;
    try {
        $stmt = $pdo->prepare("SELECT id FROM test_results WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$resultId, $userId]);
        if (!$stmt->fetchColumn()) return false;
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM test_answers WHERE result_id = ?")->execute([$resultId]);
        $ok = $pdo->prepare("DELETE FROM test_results WHERE id = ? AND user_id = ?")->execute([$resultId, $userId]);
        $pdo->commit();
        return $ok;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Delete user test result failed: ' . $e->getMessage());
        return false;
    }
}

function hideUserDuelFromHistory(PDO $pdo, int $userId, int $duelId): bool {
    if ($userId <= 0 || $duelId <= 0) return false;
    if (!dbColumnExists($pdo, 'duels', 'challenger_hidden_at') || !dbColumnExists($pdo, 'duels', 'opponent_hidden_at')) {
        return false;
    }
    try {
        ensureDuelModeColumns($pdo);
        $stmt = $pdo->prepare("SELECT challenger_id, opponent_id FROM duels WHERE id = ? LIMIT 1");
        $stmt->execute([$duelId]);
        $duel = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$duel) return false;
        if ((int)$duel['challenger_id'] === $userId) {
            $stmt = $pdo->prepare("UPDATE duels SET challenger_hidden_at = NOW() WHERE id = ?");
        } elseif ((int)$duel['opponent_id'] === $userId) {
            $stmt = $pdo->prepare("UPDATE duels SET opponent_hidden_at = NOW() WHERE id = ?");
        } else {
            return false;
        }
        return $stmt->execute([$duelId]);
    } catch (PDOException $e) {
        error_log('Hide user duel from history failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get top users by XP
 */
function getTopRankings($pdo, $limit = 10) {
    ensurePlatformEnhancements($pdo);
    $completedSql = completedFullTestSql('tr_count', 40, true);
    $stmt = $pdo->prepare("SELECT id, username, role, xp, is_verified, ranking_visible, avatar_path,
        (SELECT COUNT(*) FROM test_results tr_count WHERE tr_count.user_id = users.id AND {$completedSql} AND COALESCE(tr_count.exclude_from_ranking, 0) = 0) as tests_count
        FROM users
        WHERE role IN ('user','wujek_luki')
        ORDER BY xp DESC, tests_count DESC, last_activity DESC
        LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function classifyUserPerformanceStreakScores(array $scores): array {
    if (empty($scores)) {
        return ['type' => 'none', 'label' => 'bez serii', 'count' => 0, 'class' => 'streak-neutral'];
    }

    $first = (float)$scores[0];
    if ($first >= 80) {
        $count = 0;
        foreach ($scores as $score) {
            if ((float)$score < 80) break;
            $count++;
        }
        return ['type' => 'win', 'label' => '🔥 x' . $count, 'count' => $count, 'class' => 'streak-fire'];
    }

    if ($first < 50) {
        $count = 0;
        foreach ($scores as $score) {
            if ((float)$score >= 50) break;
            $count++;
        }
        return ['type' => 'cold', 'label' => '❄ cold' . ($count > 1 ? ' x' . $count : ''), 'count' => $count, 'class' => 'streak-cold'];
    }

    return ['type' => 'none', 'label' => 'stabilnie', 'count' => 0, 'class' => 'streak-neutral'];
}

function getUsersPerformanceStreaks(PDO $pdo, array $userIds): array {
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
    if (empty($userIds)) return [];

    $result = [];
    foreach ($userIds as $userId) {
        $result[$userId] = classifyUserPerformanceStreakScores([]);
    }

    try {
        $completedSql = completedFullTestSql('tr', 40, true);
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("
            SELECT ranked.user_id, ranked.score_percent
            FROM (
                SELECT tr.user_id, tr.score_percent,
                    ROW_NUMBER() OVER (PARTITION BY tr.user_id ORDER BY tr.test_date DESC, tr.id DESC) AS row_num
                FROM test_results tr
                WHERE tr.user_id IN ({$placeholders})
                  AND {$completedSql}
                  AND COALESCE(tr.exclude_from_ranking, 0) = 0
            ) ranked
            WHERE ranked.row_num <= 20
            ORDER BY ranked.user_id, ranked.row_num
        ");
        $stmt->execute($userIds);

        $scoresByUser = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scoresByUser[(int)$row['user_id']][] = (float)$row['score_percent'];
        }
        foreach ($scoresByUser as $userId => $scores) {
            $result[$userId] = classifyUserPerformanceStreakScores($scores);
        }
    } catch (PDOException $e) {
        error_log('Bulk performance streak error: ' . $e->getMessage());
        foreach ($userIds as $userId) {
            $result[$userId] = getUserPerformanceStreak($pdo, $userId);
        }
    }

    return $result;
}

function getUserPerformanceStreak($pdo, $userId) {
    try {
        $completedSql = completedFullTestSql('tr', 40, true);
        $stmt = $pdo->prepare("
            SELECT score_percent
            FROM test_results tr
            WHERE user_id = ? AND {$completedSql} AND COALESCE(exclude_from_ranking, 0) = 0
            ORDER BY test_date DESC, id DESC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        return classifyUserPerformanceStreakScores($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        error_log('Performance streak error: ' . $e->getMessage());
        return classifyUserPerformanceStreakScores([]);
    }
}

function getUserOfDay($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.role, u.xp, u.is_verified, SUM(x.amount) as today_xp
            FROM xp_events x
            JOIN users u ON u.id = x.user_id
            WHERE x.created_at >= CURDATE()
              AND x.created_at < CURDATE() + INTERVAL 1 DAY
              AND x.amount > 0
              AND u.role IN ('user','wujek_luki')
            GROUP BY u.id, u.username, u.role, u.xp, u.is_verified
            HAVING today_xp > 0
            ORDER BY today_xp DESC, u.xp DESC
            LIMIT 1
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function ensureDuelModeColumns($pdo) {
    static $done = false;
    if ($done) return;
    if (!dbRuntimeSchemaUpdatesEnabled()) {
        $done = true;
        return;
    }

    $columns = [
        'mode' => "VARCHAR(30) NOT NULL DEFAULT 'classic' AFTER question_ids",
        'stake_xp' => "INT NOT NULL DEFAULT 0 AFTER mode",
        'underdog_bonus' => "DECIMAL(4,2) NOT NULL DEFAULT 1.00 AFTER stake_xp",
        'revenge_parent_id' => "INT DEFAULT NULL AFTER winner_id",
        'preset' => "VARCHAR(40) NOT NULL DEFAULT 'classic' AFTER mode",
        'time_per_question_seconds' => "INT DEFAULT NULL AFTER underdog_bonus",
        'total_time_seconds' => "INT DEFAULT NULL AFTER time_per_question_seconds",
        'require_answer_confirmation' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER total_time_seconds",
        'allow_early_finish' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER require_answer_confirmation",
        'challenger_started_at' => "DATETIME DEFAULT NULL AFTER opponent_finished_at",
        'opponent_started_at' => "DATETIME DEFAULT NULL AFTER challenger_started_at",
        'challenger_hidden_at' => "DATETIME DEFAULT NULL AFTER opponent_started_at",
        'opponent_hidden_at' => "DATETIME DEFAULT NULL AFTER challenger_hidden_at",
    ];

    foreach ($columns as $column => $definition) {
        dbAddColumnIfMissing($pdo, 'duels', $column, $definition);
    }

    $done = true;
}

function getAllInDailyLimit(PDO $pdo): int {
    return max(1, min(20, (int)getAppSetting($pdo, 'all_in_daily_limit', 3)));
}

function getAllInUsage(PDO $pdo, int $userId, ?string $date = null): int {
    try {
        ensurePlatformEnhancements($pdo);
        $date = $date ?: (new DateTime('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
        $stmt = $pdo->prepare('SELECT usage_count FROM all_in_duel_usage WHERE user_id = ? AND usage_date = ? LIMIT 1');
        $stmt->execute([$userId, $date]);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function canUseAllInDuel(PDO $pdo, int $userId, ?string $date = null): bool {
    return getAllInUsage($pdo, $userId, $date) < getAllInDailyLimit($pdo);
}

function consumeAllInDuelUse(PDO $pdo, int $userId, ?string $date = null): bool {
    try {
        ensurePlatformEnhancements($pdo);
        $date = $date ?: (new DateTime('now', new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
        $limit = getAllInDailyLimit($pdo);
        $started = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $started = true;
        }
        $stmt = $pdo->prepare('SELECT usage_count FROM all_in_duel_usage WHERE user_id = ? AND usage_date = ? FOR UPDATE');
        $stmt->execute([$userId, $date]);
        $current = $stmt->fetchColumn();
        $count = $current === false ? 0 : (int)$current;
        if ($count >= $limit) {
            if ($started) $pdo->rollBack();
            return false;
        }
        $stmt = $pdo->prepare('
            INSERT INTO all_in_duel_usage (user_id, usage_date, usage_count)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE usage_count = usage_count + 1
        ');
        $stmt->execute([$userId, $date]);
        if ($started) $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if (!empty($started) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('All-In usage failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add a notification for a user
 */
function addNotification($pdo, $userId, $type, $message, $actionUrl = null, ?string $dedupeKeyOverride = null) {
    try {
        ensurePlatformEnhancements($pdo);
        $dedupeKey = $dedupeKeyOverride ?: hash('sha256', (int)$userId . '|' . (string)$type . '|' . trim((string)$message));
        $actionUrl = normalizeNotificationActionUrl($actionUrl);
        if (dbColumnExists($pdo, 'notifications', 'dedupe_key')) {
            $check = $pdo->prepare("
                SELECT id
                FROM notifications
                WHERE user_id = ? AND dedupe_key = ? AND (is_read = 0 OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
                ORDER BY id DESC
                LIMIT 1
            ");
            $check->execute([$userId, $dedupeKey]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                if ($actionUrl !== null && dbColumnExists($pdo, 'notifications', 'action_url')) {
                    $touch = $pdo->prepare("UPDATE notifications SET created_at = NOW(), action_url = ? WHERE id = ?");
                    $touch->execute([$actionUrl, (int)$existingId]);
                } else {
                    $touch = $pdo->prepare("UPDATE notifications SET created_at = NOW() WHERE id = ?");
                    $touch->execute([(int)$existingId]);
                }
                return true;
            }
            if (dbColumnExists($pdo, 'notifications', 'action_url')) {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, dedupe_key, action_url) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$userId, $type, $message, $dedupeKey, $actionUrl]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, dedupe_key) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([$userId, $type, $message, $dedupeKey]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)");
            $result = $stmt->execute([$userId, $type, $message]);
        }

        if ($result) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
            $countStmt->execute([$userId]);
            if ((int)$countStmt->fetchColumn() > 50) {
                // Keep only the 50 most recent notifications per user.
                $cleanup = $pdo->prepare(
                    "DELETE FROM notifications WHERE user_id = ? AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50
                        ) AS recent_ids
                    )"
                );
                $cleanup->execute([$userId, $userId]);
            }
        }

        return $result;
    } catch (PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 */
function getNotifications($pdo, $userId, $limit = 5) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND type NOT IN ('mfa_optional_prompt', 'mfa_optional_declined') ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min(100, (int)$limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function extractDuelIdFromNotificationUrl(?string $url): int {
    $url = trim((string)$url);
    if ($url === '') {
        return 0;
    }
    if (preg_match('#/duels/(?:lobby|take|results|challenge)\.php(?:\?|.*&)?id=(\d+)#i', $url, $matches)) {
        return (int)$matches[1];
    }
    if (preg_match('#[?&]id=(\d+)#', $url, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

function resolvePublicBaseUrl(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.+)/(ajax|actions|duels|teacher|exam)/#', $script, $matches)) {
        $base = rtrim($matches[1], '/') . '/';
        return $base === '/' ? '/' : $base;
    }
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

function publicUrl(string $path): string {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return resolvePublicBaseUrl() . $path;
}

function assetUrl(string $path, string $basePrefix = ''): string {
    $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
    $absolute = dirname(__DIR__) . '/' . $cleanPath;
    $version = is_file($absolute) ? (string)filemtime($absolute) : (string)time();
    $prefix = rtrim($basePrefix, '/');
    return ($prefix !== '' ? $prefix . '/' : '') . $cleanPath . '?v=' . rawurlencode($version);
}

function getPendingDuelChallengeForUser(PDO $pdo, int $userId, int $duelId): ?array {
    if ($duelId <= 0 || $userId <= 0) {
        return null;
    }
    try {
        ensureDuelModeColumns($pdo);
        $stmt = $pdo->prepare("
            SELECT id, challenger_id, opponent_id, category, mode, stake_xp, expires_at, status
            FROM duels
            WHERE id = ? AND opponent_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$duelId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()) {
            return null;
        }
        return $row;
    } catch (PDOException $e) {
        return null;
    }
}

function ensureDuelParticipantStarted(PDO $pdo, int $duelId, bool $isChallenger): int {
    ensureDuelModeColumns($pdo);
    $column = $isChallenger ? 'challenger_started_at' : 'opponent_started_at';
    if (!dbColumnExists($pdo, 'duels', $column)) return time();
    try {
        $stmt = $pdo->prepare("SELECT {$column} FROM duels WHERE id = ? LIMIT 1");
        $stmt->execute([$duelId]);
        $existing = $stmt->fetchColumn();
        if (!empty($existing)) {
            return (int)(strtotime((string)$existing) ?: time());
        }
        $pdo->prepare("UPDATE duels SET {$column} = NOW() WHERE id = ? AND {$column} IS NULL")
            ->execute([$duelId]);
        $stmt->execute([$duelId]);
        $existing = $stmt->fetchColumn();
        return (int)(strtotime((string)$existing) ?: time());
    } catch (PDOException $e) {
        error_log('ensureDuelParticipantStarted failed: ' . $e->getMessage());
        return time();
    }
}

function getDuelAnsweredCount(PDO $pdo, int $duelId, int $userId): int {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM duel_answers WHERE duel_id = ? AND user_id = ?');
        $stmt->execute([$duelId, $userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function getNotificationPresentationMeta(array $notif): array {
    $icon = 'bi-info-circle';
    $tone = 'primary';
    $label = 'System';
    switch ($notif['type'] ?? '') {
        case 'rank_up':
            $icon = 'bi-graph-up-arrow';
            $tone = 'success';
            $label = 'Ranga';
            break;
        case 'rank_down':
            $icon = 'bi-graph-down-arrow';
            $tone = 'danger';
            $label = 'Ranga';
            break;
        case 'friend_request':
            $icon = 'bi-person-plus';
            $tone = 'info';
            $label = 'Znajomi';
            break;
        case 'missions_refresh':
        case 'daily_missions_refresh':
        case 'weekly_missions_refresh':
        case 'monthly_missions_refresh':
            $icon = 'bi-arrow-repeat';
            $tone = 'warning';
            $label = 'Misje';
            break;
        case 'mission_complete':
            $icon = 'bi-trophy';
            $tone = 'success';
            $label = 'Misje';
            break;
        case 'duel_challenge':
        case 'duel_accepted':
        case 'duel_finished':
            $icon = 'bi-lightning-charge';
            $tone = 'danger';
            $label = 'Pojedynek';
            break;
        case 'teacher_application_approved':
        case 'teacher_application_rejected':
            $icon = 'bi-mortarboard';
            $tone = 'primary';
            $label = 'Rola';
            break;
        case 'mfa_optional_prompt':
            $icon = 'bi-shield-lock';
            $tone = 'warning';
            $label = 'Bezpieczeństwo';
            break;
        case 'app_status':
            $icon = 'bi-broadcast';
            $tone = 'info';
            $label = 'Status';
            break;
    }
    return ['icon' => $icon, 'tone' => $tone, 'label' => $label];
}

function renderNotificationsDropdownListHtml(PDO $pdo, int $userId, array $notifications, string $baseUrl): string {
    if (empty($notifications)) {
        return '<div class="p-4 text-center text-muted notification-empty-state">'
            . '<i class="bi bi-bell-slash fs-2 mb-2 d-block opacity-25"></i>'
            . '<p class="small mb-0">Brak nowych powiadomień</p>'
            . '</div>';
    }

    $csrf = htmlspecialchars(generateCsrfToken('notifications'), ENT_QUOTES, 'UTF-8');
    $baseUrl = rtrim($baseUrl, '/');
    if ($baseUrl !== '' && substr($baseUrl, -1) !== '/') {
        $baseUrl .= '/';
    }

    ob_start();
    foreach ($notifications as $notif) {
        $meta = getNotificationPresentationMeta($notif);
        $icon = $meta['icon'];
        $tone = $meta['tone'];
        $label = $meta['label'];
        $isRead = !empty($notif['is_read']);
        $appStatusPayload = resolveAppStatusNotification($pdo, $notif);
        if ($appStatusPayload) {
            $notif['message'] = $appStatusPayload['title'];
        }
        $notifUrl = !empty($notif['action_url']) ? normalizeNotificationActionUrl($notif['action_url']) : null;
        $notifHref = notificationActionHref($notifUrl, $baseUrl) ?? ($baseUrl . 'notifications.php');
        $duelId = 0;
        $pendingDuel = null;
        if (($notif['type'] ?? '') === 'duel_challenge') {
            $duelId = extractDuelIdFromNotificationUrl((string)($notif['action_url'] ?? ''));
            if ($duelId <= 0) {
                $duelId = extractDuelIdFromNotificationUrl($notifUrl);
            }
            $pendingDuel = getPendingDuelChallengeForUser($pdo, $userId, $duelId);
        }
        $itemClass = 'notification-menu-item ' . ($isRead ? 'is-read' : 'is-unread');
        if ($pendingDuel) {
            $itemClass .= ' notification-has-duel-actions';
        }
        ?>
        <div class="<?php echo htmlspecialchars($itemClass); ?>">
            <?php if ($appStatusPayload): ?>
            <div class="notification-menu-link notification-status-link text-reset">
                <div class="notification-menu-icon text-<?php echo htmlspecialchars($tone); ?>">
                    <i class="bi <?php echo htmlspecialchars($icon); ?>"></i>
                </div>
                <div class="notification-menu-body flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="notification-menu-label"><?php echo htmlspecialchars($label); ?></span>
                        <?php if (!$isRead): ?><span class="notification-menu-dot" aria-label="Nieprzeczytane"></span><?php endif; ?>
                    </div>
                    <div class="notification-menu-message text-wrap"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
                    <div class="notification-menu-time">
                        <i class="bi bi-clock me-1"></i><?php echo date('d.m, H:i', strtotime($notif['created_at'] ?? 'now')); ?>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary rounded-pill notification-status-more mt-2"
                            data-app-status-open
                            data-status-title="<?php echo htmlspecialchars($appStatusPayload['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            data-status-body="<?php echo htmlspecialchars($appStatusPayload['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            data-status-level="<?php echo htmlspecialchars($appStatusPayload['level'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            data-status-date="<?php echo htmlspecialchars($appStatusPayload['date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                            data-status-moderator="<?php echo htmlspecialchars($appStatusPayload['moderator'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        Więcej
                    </button>
                </div>
            </div>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars($notifHref); ?>" class="notification-menu-link text-decoration-none text-reset">
                <div class="notification-menu-icon text-<?php echo htmlspecialchars($tone); ?>">
                    <i class="bi <?php echo htmlspecialchars($icon); ?>"></i>
                </div>
                <div class="notification-menu-body flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="notification-menu-label"><?php echo htmlspecialchars($label); ?></span>
                        <?php if (!$isRead): ?><span class="notification-menu-dot" aria-label="Nieprzeczytane"></span><?php endif; ?>
                    </div>
                    <div class="notification-menu-message text-wrap"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
                    <div class="notification-menu-time">
                        <i class="bi bi-clock me-1"></i><?php echo date('d.m, H:i', strtotime($notif['created_at'] ?? 'now')); ?>
                    </div>
                </div>
            </a>
            <?php endif; ?>
            <?php if ($pendingDuel): ?>
            <div class="notification-duel-actions px-3 pb-3 pt-0" data-duel-id="<?php echo (int)$duelId; ?>">
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3" data-duel-action="accept" data-duel-id="<?php echo (int)$duelId; ?>">
                    <i class="bi bi-check2-circle me-1"></i>Akceptuj
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-duel-action="decline" data-duel-id="<?php echo (int)$duelId; ?>">
                    Odrzuć
                </button>
                <a href="<?php echo htmlspecialchars($baseUrl . 'duels/lobby.php?id=' . (int)$duelId); ?>" class="btn btn-sm btn-link text-decoration-none">Lobby</a>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    return (string)ob_get_clean();
}

function buildNotificationsDropdownPayload(PDO $pdo, int $userId, string $baseUrl, int $limit = 5): array {
    syncAppStatusNotificationsForUser($pdo, $userId);
    $notifications = getNotifications($pdo, $userId, $limit);
    $unreadCount = getUnreadNotificationsCount($pdo, $userId);
    return [
        'unread_count' => $unreadCount,
        'html' => renderNotificationsDropdownListHtml($pdo, $userId, $notifications, $baseUrl),
        'has_unread' => $unreadCount > 0,
    ];
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND type NOT IN ('mfa_optional_prompt', 'mfa_optional_declined')");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function hydrateTeacherDecisionNotificationMessage(PDO $pdo, string $message): string {
    if (function_exists('mb_check_encoding') && !mb_check_encoding($message, 'UTF-8')) {
        $converted = @mb_convert_encoding($message, 'UTF-8', 'Windows-1250');
        if ($converted !== false) {
            $message = $converted;
        }
    }
    $pattern = '/Decyzję\s+podjął:\s*([^\n(@]+|@[^\n\s(]+)\s*(\([^)]*\))?/u';
    if (!preg_match($pattern, $message, $match)) {
        return $message;
    }

    $username = trim($match[1] ?? '');
    $username = ltrim($username, '@');
    if ($username === '') {
        return $message;
    }

    try {
        $stmt = $pdo->prepare("SELECT username, first_name, last_name, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            return $message;
        }
        $adminName = userDisplayName($admin);
        $adminHandle = userHandle($admin);
        $adminRole = getUserRoleBadge($admin['role'] ?? 'admin')['label'];
        $adminLabel = $adminName;
        if ($adminHandle !== '' && $adminName !== ($admin['username'] ?? '')) {
            $adminLabel .= ' ' . $adminHandle;
        }
        $adminLabel .= ' (' . $adminRole . ')';
        return preg_replace_callback($pattern, fn() => 'Decyzję podjął: ' . $adminLabel, $message, 1) ?: $message;
    } catch (PDOException $e) {
        return $message;
    }
}

// ============================================
// Admin Requests (Wnioski) Helpers
// ============================================

/**
 * Ensure the admin_requests table exists
 */
function ensureAdminRequestsTableExists($pdo) {
    if (!dbRuntimeSchemaUpdatesEnabled()) return;

    try {
        ensurePlatformEnhancements($pdo);
        $sql = "CREATE TABLE IF NOT EXISTS admin_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'general',
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('sent','read','replied','closed') NOT NULL DEFAULT 'sent',
            admin_reply TEXT NULL,
            replied_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_teacher (teacher_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
        dbAddColumnIfMissing($pdo, 'admin_requests', 'type', "VARCHAR(50) NOT NULL DEFAULT 'general' AFTER teacher_id");
        try {
            $pdo->exec("ALTER TABLE admin_requests MODIFY status ENUM('sent','read','replied','closed') NOT NULL DEFAULT 'sent'");
        } catch (PDOException $e) {
            error_log('Admin request status migration skipped: ' . $e->getMessage());
        }
    } catch (PDOException $e) {
        error_log('Failed to ensure admin_requests table: ' . $e->getMessage());
    }
}

/**
 * Create a new admin request from a teacher
 */
function createAdminRequest($pdo, $teacherId, $subject, $message, string $type = 'general') {
    ensureAdminRequestsTableExists($pdo);
    try {
        $subject = trim((string)$subject);
        $message = trim((string)$message);
        $type = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'general';
        if ($subject === '' || $message === '' || containsProfanity($subject) || containsProfanity($message)) {
            return 0;
        }
        if (!consumeRateLimit($pdo, 'admin_request', (string)$teacherId . '|' . clientIpAddress(), 8, 3600)) {
            return 0;
        }
        $stmt = $pdo->prepare("INSERT INTO admin_requests (teacher_id, type, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$teacherId, mb_substr($type, 0, 50), mb_substr($subject, 0, 180), mb_substr($message, 0, 4000)]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Error creating admin request: ' . $e->getMessage());
        return 0;
    }
}

function createTeacherApplicationRequest(PDO $pdo, int $userId, string $motivation): int {
    ensureAdminRequestsTableExists($pdo);
    $motivation = trim($motivation);
    $limitErrors = validateTeacherMotivationLimits($motivation);
    if ($userId <= 0 || !empty($limitErrors) || ($motivation !== '' && containsProfanity($motivation))) {
        return 0;
    }
    try {
        $duplicate = detectTeacherApplicationDuplicate($pdo, $userId);
        $dup = $pdo->prepare("SELECT id FROM admin_requests WHERE teacher_id = ? AND type = 'teacher_application' AND status IN ('sent','read') LIMIT 1");
        $dup->execute([$userId]);
        $openRequestId = (int)($dup->fetchColumn() ?: 0);
        if ($openRequestId > 0) {
            markUserUntrusted($pdo, $userId, 'possible fraud / duplicate identity');
            notifyAdminsAboutTeacherApplication($pdo, $openRequestId, $userId, true);
            return $openRequestId;
        }
        $message = $motivation !== '' ? $motivation : 'Brak podanej przyczyny.';
        if ($duplicate['is_duplicate']) {
            markUserUntrusted($pdo, $userId, 'possible fraud / duplicate identity');
            $message = "[ALERT: possible fraud / duplicate identity]\n" . $message;
        }
        $requestId = createAdminRequest($pdo, $userId, 'Aplikacja na nauczyciela', $message, 'teacher_application');
        if ($requestId > 0) {
            notifyAdminsAboutTeacherApplication($pdo, $requestId, $userId, (bool)$duplicate['is_duplicate']);
        }
        return $requestId;
    } catch (PDOException $e) {
        error_log('Teacher application create failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Admin replies to a request
 */
function replyAdminRequest($pdo, $requestId, $adminId, $replyText) {
    ensureAdminRequestsTableExists($pdo);
    try {
        $replyText = trim((string)$replyText);
        if ($replyText === '') return false;

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO admin_request_replies (request_id, admin_id, reply_text) VALUES (?, ?, ?)");
        $stmt->execute([$requestId, $adminId, $replyText]);

        $statusStmt = $pdo->prepare("SELECT status FROM admin_requests WHERE id = ? LIMIT 1");
        $statusStmt->execute([$requestId]);
        $nextStatus = $statusStmt->fetchColumn() === 'closed' ? 'closed' : 'replied';
        $stmt = $pdo->prepare("UPDATE admin_requests SET admin_reply = ?, status = ?, replied_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$replyText, $nextStatus, $adminId, $requestId]);

        // Notify teacher
        $stmt = $pdo->prepare("SELECT teacher_id FROM admin_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $teacherId = $stmt->fetchColumn();
        $pdo->commit();

        if ($teacherId) {
            addNotification($pdo, $teacherId, 'request_reply', 'Administrator odpowiedział na Twój wniosek.', 'teacher/requests.php');
        }
        logAdminAction($pdo, $adminId, 'reply_admin_request', 'admin_request', $requestId);
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Error replying to admin request: ' . $e->getMessage());
        return false;
    }
}

function normalizeNotificationActionUrl($url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    $url = str_replace(["\r", "\n", "\0"], '', $url);

    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        if (!preg_match('#^https?://#i', $url) || !function_exists('securityPublicBaseUrl')) {
            return null;
        }

        $parts = parse_url($url);
        $baseParts = parse_url(securityPublicBaseUrl());
        if (!is_array($parts) || !is_array($baseParts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $baseScheme = strtolower((string)($baseParts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $baseHost = strtolower((string)($baseParts['host'] ?? ''));
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $basePort = (int)($baseParts['port'] ?? ($baseScheme === 'https' ? 443 : 80));
        $path = (string)($parts['path'] ?? '/');
        $basePath = rtrim((string)($baseParts['path'] ?? ''), '/');
        if (
            $scheme !== $baseScheme
            || $host === ''
            || $host !== $baseHost
            || $port !== $basePort
            || ($basePath !== '' && $path !== $basePath && !str_starts_with($path, $basePath . '/'))
        ) {
            return null;
        }

        $path = $basePath !== '' ? substr($path, strlen($basePath)) : $path;
        $url = ($path !== '' && $path !== '/' ? ltrim($path, '/') : 'index.php')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    $pathPart = preg_split('/[?#]/', $url, 2)[0] ?? '';
    if (str_starts_with($pathPart, '/') && function_exists('securityPublicBaseUrl')) {
        $configuredBasePath = rtrim((string)(parse_url(securityPublicBaseUrl(), PHP_URL_PATH) ?? ''), '/');
        if (
            $configuredBasePath !== ''
            && ($pathPart === $configuredBasePath || str_starts_with($pathPart, $configuredBasePath . '/'))
        ) {
            $pathSuffix = substr($pathPart, strlen($configuredBasePath));
            $url = ($pathSuffix !== '' && $pathSuffix !== '/' ? ltrim($pathSuffix, '/') : 'index.php')
                . substr($url, strlen($pathPart));
            $pathPart = preg_split('/[?#]/', $url, 2)[0] ?? '';
        }
    }

    $decodedPath = $pathPart;
    for ($decodePass = 0; $decodePass < 3; $decodePass++) {
        $nextDecodedPath = rawurldecode($decodedPath);
        if ($nextDecodedPath === $decodedPath) break;
        $decodedPath = $nextDecodedPath;
    }
    if (
        str_starts_with($url, '//')
        || str_starts_with($decodedPath, '//')
        || str_contains($url, '\\')
        || str_contains($decodedPath, '\\')
        || str_contains($decodedPath, '..')
        || !preg_match('~^/?[a-zA-Z0-9_./?=&%#+-]+$~', $url)
    ) {
        return null;
    }
    return mb_substr(ltrim($url, '/'), 0, 500);
}

function getPendingOptionalMfaPrompt(PDO $pdo, int $userId, string $role): ?array {
    if ($userId <= 0 || !in_array($role, ['teacher', 'dyrektor'], true)) {
        return null;
    }
    if (function_exists('mfaUserHasEnabled') && mfaUserHasEnabled($pdo, $userId)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id
            FROM notifications
            WHERE user_id = ? AND type = 'mfa_optional_prompt'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $notificationId = (int)($stmt->fetchColumn() ?: 0);
        if ($notificationId > 0) {
            return ['id' => $notificationId];
        }

        $declined = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND type = 'mfa_optional_declined' LIMIT 1");
        $declined->execute([$userId]);
        if ($declined->fetchColumn()) {
            return null;
        }

        if (notifyOptionalMfaForRole($pdo, $userId, $role)) {
            $stmt->execute([$userId]);
            $notificationId = (int)($stmt->fetchColumn() ?: 0);
            return $notificationId > 0 ? ['id' => $notificationId] : null;
        }

        return null;
    } catch (PDOException $e) {
        error_log('Optional MFA prompt lookup failed: ' . $e->getMessage());
        return null;
    }
}

function notificationActionHref($actionUrl, string $baseUrl = ''): ?string {
    $normalized = normalizeNotificationActionUrl($actionUrl);
    if ($normalized === null) return null;

    $baseUrl = in_array($baseUrl, ['', '../'], true) ? $baseUrl : '';
    if (str_starts_with($normalized, '?') || str_starts_with($normalized, '#')) {
        return $normalized;
    }
    return $baseUrl . $normalized;
}

/**
 * Mark a request as read (admin)
 */
function markRequestRead($pdo, $requestId, $adminId = null) {
    ensureAdminRequestsTableExists($pdo);
    try {
        $stmt = $pdo->prepare("UPDATE admin_requests SET status = 'read', updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$requestId]);
        if ($ok && $adminId) logAdminAction($pdo, $adminId, 'mark_admin_request_read', 'admin_request', $requestId);
        return $ok;
    } catch (PDOException $e) {
        error_log('Error marking request read: ' . $e->getMessage());
        return false;
    }
}

function deleteAdminRequest(PDO $pdo, int $requestId, int $adminId): bool {
    ensureAdminRequestsTableExists($pdo);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM admin_request_replies WHERE request_id = ?");
        $stmt->execute([$requestId]);
        $stmt = $pdo->prepare("DELETE FROM admin_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $ok = $stmt->rowCount() > 0;
        $pdo->commit();
        if ($ok) logAdminAction($pdo, $adminId, 'delete_admin_request', 'admin_request', $requestId);
        return $ok;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Error deleting admin request: ' . $e->getMessage());
        return false;
    }
}

function resolveTeacherApplication(PDO $pdo, int $requestId, int $adminId, string $decision, string $replyText = ''): bool {
    ensureAdminRequestsTableExists($pdo);
    if (!in_array($decision, ['approve', 'reject'], true)) return false;
    try {
        $stmt = $pdo->prepare("SELECT ar.*, u.username, u.first_name, u.last_name FROM admin_requests ar JOIN users u ON u.id = ar.teacher_id WHERE ar.id = ? AND ar.type = 'teacher_application' LIMIT 1");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$request || ($request['status'] ?? '') === 'closed') return false;

        $adminStmt = $pdo->prepare("SELECT username, first_name, last_name, role FROM users WHERE id = ? LIMIT 1");
        $adminStmt->execute([$adminId]);
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC) ?: ['username' => 'Administrator', 'role' => 'admin'];
        $adminRoleLabel = getUserRoleBadge($admin['role'] ?? 'admin')['label'];
        $adminName = function_exists('userDisplayName') ? userDisplayName($admin) : ($admin['username'] ?? 'Administrator');
        $adminHandle = function_exists('userHandle') ? userHandle($admin) : '';
        $adminLabel = $adminName;
        if ($adminHandle !== '' && $adminName !== ($admin['username'] ?? '')) {
            $adminLabel .= ' ' . $adminHandle;
        }
        $adminLabel .= ' (' . $adminRoleLabel . ')';
        $decisionDate = date('d.m.Y H:i');

        $pdo->beginTransaction();
        if ($decision === 'approve') {
            if (!setUserRole($pdo, (int)$request['teacher_id'], 'teacher')) {
                $pdo->rollBack();
                return false;
            }
            $note = trim($replyText);
            $message = "Aplikacja na nauczyciela zaakceptowana.\nData decyzji: {$decisionDate}\nDecyzję podjął: {$adminLabel}";
            if ($note !== '') $message .= "\nNotatka: {$note}";
            $statusMessage = 'Aplikacja zaakceptowana.';
            $notificationType = 'teacher_application_approved';
        } else {
            $note = trim($replyText);
            $message = "Aplikacja na nauczyciela odrzucona.\nData decyzji: {$decisionDate}\nDecyzję podjął: {$adminLabel}";
            if ($note !== '') $message .= "\nNotatka: {$note}";
            $statusMessage = 'Aplikacja odrzucona.';
            $notificationType = 'teacher_application_rejected';
        }

        $pdo->prepare("INSERT INTO admin_request_replies (request_id, admin_id, reply_text) VALUES (?, ?, ?)")
            ->execute([$requestId, $adminId, $message]);
        $pdo->prepare("UPDATE admin_requests SET admin_reply = ?, status = 'closed', replied_by = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$message, $adminId, $requestId]);
        $pdo->commit();

        clearTeacherApplicationNotifications($pdo, $requestId);
        addNotification($pdo, (int)$request['teacher_id'], $notificationType, $message, $decision === 'approve' ? 'teacher/index.php' : 'notifications.php');
        if ($decision === 'approve') {
            notifyOptionalMfaForRole($pdo, (int)$request['teacher_id'], 'teacher');
        }
        logAdminAction($pdo, $adminId, 'teacher_application_' . $decision, 'admin_request', $requestId, $request['username'] ?? '');
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Teacher application resolve failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get requests for a given teacher
 */
function getAdminRequestsForTeacher($pdo, $teacherId) {
    ensureAdminRequestsTableExists($pdo);
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_requests WHERE teacher_id = ? ORDER BY created_at DESC");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching teacher requests: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all requests (admin view)
 */
function getAllAdminRequests($pdo, ?int $limit = null) {
    ensureAdminRequestsTableExists($pdo);
    try {
        $sql = "
            SELECT ar.*, u.username as teacher_username, u.first_name, u.last_name, u.email, u.class,
                   u.trust_status, u.risk_flags,
                   (SELECT COUNT(*) FROM admin_request_replies rr WHERE rr.request_id = ar.id) AS reply_count
            FROM admin_requests ar
            LEFT JOIN users u ON u.id = ar.teacher_id
            ORDER BY ar.created_at DESC
        ";
        if ($limit !== null) {
            $stmt = $pdo->prepare($sql . ' LIMIT ?');
            $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching all admin requests: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get single request by id
 */
function getAdminRequestById($pdo, $id) {
    ensureAdminRequestsTableExists($pdo);
    try {
        $stmt = $pdo->prepare("SELECT ar.*, u.username as teacher_username, u.first_name, u.last_name, u.email, u.class FROM admin_requests ar LEFT JOIN users u ON u.id = ar.teacher_id WHERE ar.id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching admin request: ' . $e->getMessage());
        return null;
    }
}

// ============================================
// Social & Friends Functions
// ============================================

/**
 * Get friendship status between two users
 */
function getFriendshipStatus($pdo, $user1, $user2) {
    if ($user1 == $user2) return 'self';
    
    $stmt = $pdo->prepare("SELECT * FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    $stmt->execute([$user1, $user2, $user2, $user1]);
    $friendship = $stmt->fetch();
    
    if (!$friendship) return 'none';
    if ($friendship['status'] === 'accepted') return 'friends';
    
    return ($friendship['user_id'] == $user1) ? 'sent' : 'pending';
}

/**
 * Get list of friends for a user
 */
function getUserFriends($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.xp, u.last_activity, u.role, u.is_verified, u.avatar_path
        FROM users u
        JOIN friends f ON (u.id = f.user_id OR u.id = f.friend_id)
        WHERE (f.user_id = ? OR f.friend_id = ?) 
        AND u.id != ?
        AND f.status = 'accepted'
        ORDER BY u.last_activity DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get pending friend requests for a user
 */
function getPendingFriendRequests($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.xp, u.avatar_path, f.created_at
        FROM users u
        JOIN friends f ON u.id = f.user_id
        WHERE f.friend_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send a friend request
 */
function sendFriendRequest($pdo, $fromId, $toId, ?string &$failureReason = null) {
    $failureReason = null;
    if ($fromId == $toId) {
        $failureReason = 'friend_request_self';
        return false;
    }
    
    $status = getFriendshipStatus($pdo, $fromId, $toId);
    if ($status !== 'none') {
        $failureReason = 'friend_request_exists';
        return false;
    }
    if (getActiveSentFriendRequestCount($pdo, (int)$fromId) >= friendRequestLimit()) {
        $failureReason = 'friend_request_limit';
        return false;
    }

    $stmt = $pdo->prepare("SELECT id, role, allow_friend_requests FROM users WHERE id IN (?, ?)");
    $stmt->execute([$fromId, $toId]);
    $senderRole = 'user';
    $targetRole = 'user';
    $targetAllowsRequests = true;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['id'] === (int)$fromId) $senderRole = $row['role'] ?? 'user';
        if ((int)$row['id'] === (int)$toId) {
            $targetRole = $row['role'] ?? 'user';
            $targetAllowsRequests = ((int)($row['allow_friend_requests'] ?? 1) === 1);
        }
    }
    if (!canSendFriendRequest($senderRole, $targetRole, $targetAllowsRequests)) {
        $failureReason = 'friend_request_privacy';
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$fromId, $toId]);
        
        $username = $_SESSION['username'] ?? 'Ktoś';
        addNotification($pdo, $toId, 'friend_request', "Użytkownik $username wysłał Ci zaproszenie do znajomych.", 'social.php');
        return true;
    } catch (PDOException $e) {
        $failureReason = 'friend_request_failed';
        return false;
    }
}

function countOpenAdminRequests(PDO $pdo): int {
    ensureAdminRequestsTableExists($pdo);
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_requests WHERE status IN ('sent', 'read', 'replied')");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Accept a friend request
 */
function acceptFriendRequest($pdo, $userId, $friendId) {
    try {
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
        $stmt->execute([$friendId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            $username = $_SESSION['username'] ?? 'Twój znajomy';
            addNotification($pdo, $friendId, 'friend_request', "Użytkownik $username zaakceptował Twoje zaproszenie!", 'profile.php?id=' . (int)$userId);
            return true;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Remove a friend or cancel a request
 */
function removeFriendship($pdo, $user1, $user2) {
    $stmt = $pdo->prepare("DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    return $stmt->execute([$user1, $user2, $user2, $user1]);
}

// ============================================
// Question CRUD Functions
// ============================================

function sanitizeQuestionPlainText($value, $maxLength = 4000) {
    $clean = trim(strip_tags((string)$value));
    return mb_substr($clean, 0, $maxLength);
}

function getAdminRequestReplies(PDO $pdo, int $requestId): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT rr.*, u.username AS admin_username
            FROM admin_request_replies rr
            LEFT JOIN users u ON u.id = rr.admin_id
            WHERE rr.request_id = ?
            ORDER BY rr.created_at ASC, rr.id ASC
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function countWordsUtf8(string $text): int {
    preg_match_all('/[\p{L}\p{N}]+/u', trim($text), $matches);
    return count($matches[0] ?? []);
}

function validateTeacherMotivationLimits(string $text): array {
    $errors = [];
    preg_match_all('/[\p{L}\p{N}_-]+/u', trim($text), $matches);
    $words = $matches[0] ?? [];
    if (count($words) > 100) {
        $errors[] = 'Uzasadnienie aplikacji może mieć maksymalnie 100 słów.';
    }
    foreach ($words as $word) {
        if (mb_strlen($word, 'UTF-8') > 20) {
            $errors[] = 'Każde słowo w uzasadnieniu może mieć maksymalnie 20 znaków.';
            break;
        }
    }
    return $errors;
}

function userAvatarSrc($avatarPath, string $basePrefix = ''): ?string {
    $avatarPath = trim((string)$avatarPath);
    if ($avatarPath === '' || !preg_match('#^uploads/avatars/user_\d+_[a-f0-9]{12}\.webp$#', $avatarPath)) {
        return null;
    }
    if (!is_file(dirname(__DIR__) . '/' . $avatarPath)) {
        return null;
    }
    return rtrim($basePrefix, '/') . ($basePrefix !== '' ? '/' : '') . ltrim($avatarPath, '/');
}

function scanAvatarImageSafety($image, int $width, int $height): array {
    if (!is_resource($image) && !($image instanceof GdImage)) {
        return ['ok' => false, 'message' => 'Nie udało się zweryfikować zdjęcia profilowego.'];
    }
    $sampleW = min(80, max(1, $width));
    $sampleH = min(80, max(1, $height));
    $total = $sampleW * $sampleH;
    $skin = 0;
    $red = 0;
    $dark = 0;
    $symbolDark = 0;
    $symbolAxis = 0;
    $upperSkin = 0;
    $upperTotal = 0;
    $centerSkin = 0;
    $centerTotal = 0;
    for ($y = 0; $y < $sampleH; $y++) {
        for ($x = 0; $x < $sampleW; $x++) {
            $srcX = (int)floor($x * $width / $sampleW);
            $srcY = (int)floor($y * $height / $sampleH);
            $rgb = imagecolorat($image, min($width - 1, $srcX), min($height - 1, $srcY));
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            if ($max < 42) $dark++;
            $isSkin = $r > 95 && $g > 40 && $b > 20 && ($max - $min) > 15 && abs($r - $g) > 15 && $r > $g && $r > $b;
            $isWarmSkin = $r > 120 && $g > 70 && $b > 45 && $r > $g && $g > $b && ($r - $b) > 35;
            $isUpper = $y <= (int)floor($sampleH * 0.72);
            $isCenter = $x >= (int)floor($sampleW * 0.18) && $x <= (int)ceil($sampleW * 0.82)
                && $y >= (int)floor($sampleH * 0.08) && $y <= (int)ceil($sampleH * 0.92);
            if ($isUpper) $upperTotal++;
            if ($isCenter) $centerTotal++;
            if ($isSkin || $isWarmSkin) {
                $skin++;
                if ($isUpper) $upperSkin++;
                if ($isCenter) $centerSkin++;
            }
            if ($r > 130 && $g < 90 && $b < 90 && $r > ($g * 1.45) && $r > ($b * 1.45)) {
                $red++;
            }
            if ($max < 80 && ($max - $min) < 32) {
                $symbolDark++;
                $nearVertical = abs($x - ($sampleW / 2)) <= max(2, $sampleW * 0.08);
                $nearHorizontal = abs($y - ($sampleH / 2)) <= max(2, $sampleH * 0.08);
                if ($nearVertical || $nearHorizontal) {
                    $symbolAxis++;
                }
            }
        }
    }
    $skinRatio = $skin / max(1, $total);
    $redRatio = $red / max(1, $total);
    $darkRatio = $dark / max(1, $total);
    $symbolDarkRatio = $symbolDark / max(1, $total);
    $symbolAxisRatio = $symbolAxis / max(1, $symbolDark);
    $upperSkinRatio = $upperSkin / max(1, $upperTotal);
    $centerSkinRatio = $centerSkin / max(1, $centerTotal);
    if ($skinRatio > 0.48 || ($upperSkinRatio > 0.36 && $centerSkinRatio > 0.30) || ($skinRatio > 0.34 && $redRatio > 0.032)) {
        return ['ok' => false, 'message' => 'Zdjęcie wygląda jak niedozwolona nagość albo zbyt odsłonięty kadr. Wybierz neutralny avatar.'];
    }
    if ($redRatio > 0.085 || ($redRatio > 0.045 && $darkRatio > 0.16) || ($redRatio > 0.035 && $symbolDarkRatio > 0.12)) {
        return ['ok' => false, 'message' => 'Zdjęcie może zawierać przemoc lub drastyczne treści. Wybierz neutralny avatar.'];
    }
    if ($symbolDarkRatio > 0.045 && $symbolDarkRatio < 0.32 && $symbolAxisRatio > 0.26) {
        return ['ok' => false, 'message' => 'Zdjęcie może zawierać zakazany symbol. Wybierz neutralny avatar.'];
    }
    return ['ok' => true, 'message' => ''];
}

function friendRequestLimit(): int {
    return 4;
}

function getActiveSentFriendRequestCount(PDO $pdo, int $userId): int {
    if ($userId <= 0) return 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function canSendMoreFriendRequests(PDO $pdo, int $userId): bool {
    return getActiveSentFriendRequestCount($pdo, $userId) < friendRequestLimit();
}

function markUserUntrusted(PDO $pdo, int $userId, string $flag = 'possible fraud / duplicate identity'): void {
    if ($userId <= 0) return;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("SELECT risk_flags FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $existing = trim((string)($stmt->fetchColumn() ?: ''));
        $flags = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $existing))));
        if (!in_array($flag, $flags, true)) {
            $flags[] = $flag;
        }
        $pdo->prepare("UPDATE users SET trust_status = 'untrusted', risk_flags = ? WHERE id = ?")
            ->execute([implode("\n", $flags), $userId]);
    } catch (PDOException $e) {
        error_log('User trust flag failed: ' . $e->getMessage());
    }
}

function detectTeacherApplicationDuplicate(PDO $pdo, int $userId): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, class FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return ['is_duplicate' => false, 'reason' => ''];

        $past = $pdo->prepare("SELECT COUNT(*) FROM admin_requests WHERE teacher_id = ? AND type = 'teacher_application'");
        $past->execute([$userId]);
        if ((int)$past->fetchColumn() > 0) {
            return ['is_duplicate' => true, 'reason' => 'same account reapplied'];
        }

        $match = $pdo->prepare("
            SELECT COUNT(*)
            FROM users u
            JOIN admin_requests ar ON ar.teacher_id = u.id AND ar.type = 'teacher_application'
            WHERE u.id != ?
              AND LOWER(COALESCE(u.first_name,'')) = LOWER(COALESCE(?, ''))
              AND LOWER(COALESCE(u.last_name,'')) = LOWER(COALESCE(?, ''))
              AND COALESCE(u.class,'') = COALESCE(?, '')
        ");
        $match->execute([$userId, $user['first_name'] ?? '', $user['last_name'] ?? '', $user['class'] ?? '']);
        if ((int)$match->fetchColumn() > 0) {
            return ['is_duplicate' => true, 'reason' => 'matching identity data'];
        }
    } catch (PDOException $e) {
        error_log('Teacher application duplicate check failed: ' . $e->getMessage());
    }
    return ['is_duplicate' => false, 'reason' => ''];
}

function notifyAdminsAboutTeacherApplication(PDO $pdo, int $requestId, int $userId, bool $isDuplicate): void {
    try {
        $userStmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['username' => 'użytkownik'];
        $label = function_exists('userDisplayName') ? userDisplayName($user) : ($user['username'] ?? 'użytkownik');
        $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin','dyrektor')")->fetchAll(PDO::FETCH_COLUMN);
        $type = $isDuplicate ? 'teacher_application_duplicate' : 'teacher_application';
        $message = $isDuplicate
            ? "ALERT: aplikacja nauczyciela #{$requestId} może być duplikatem tożsamości ({$label}). Konto oznaczono jako untrusted / possible fraud / duplicate identity."
            : "Nowa aplikacja na nauczyciela #{$requestId}: {$label}.";
        foreach ($admins as $adminId) {
            addNotification($pdo, (int)$adminId, $type, $message, 'admin_requests.php#request-' . $requestId);
        }
    } catch (PDOException $e) {
        error_log('Teacher application admin notification failed: ' . $e->getMessage());
    }
}

function clearTeacherApplicationNotifications(PDO $pdo, int $requestId): void {
    try {
        ensurePlatformEnhancements($pdo);
        $action = 'admin_requests.php#request-' . $requestId;
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE type IN ('teacher_application','teacher_application_duplicate')
              AND is_read = 0
              AND (action_url = ? OR message LIKE ?)
        ");
        $stmt->execute([$action, '%#' . $requestId . '%']);

        $open = $pdo->query("SELECT COUNT(*) FROM admin_requests WHERE type = 'teacher_application' AND status IN ('sent','read')")->fetchColumn();
        if ((int)$open === 0) {
            $pdo->exec("DELETE FROM notifications WHERE type IN ('teacher_application','teacher_application_duplicate') AND is_read = 0 AND (action_url IS NULL OR action_url = 'admin_requests.php')");
        }
    } catch (PDOException $e) {
        error_log('Teacher application notification cleanup failed: ' . $e->getMessage());
    }
}

function createAbuseReport(PDO $pdo, array $data): array {
    try {
        ensurePlatformEnhancements($pdo);
        $ip = clientIpAddress();
        $reporterUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $rateSql = "SELECT COUNT(*) FROM abuse_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND (ip_address = ?";
        $rateParams = [$ip];
        if ($reporterUserId) {
            $rateSql .= " OR reporter_user_id = ?";
            $rateParams[] = $reporterUserId;
        }
        $rateSql .= ")";
        $rate = $pdo->prepare($rateSql);
        $rate->execute($rateParams);
        if ((int)$rate->fetchColumn() >= 1) {
            return ['ok' => false, 'message' => 'Możesz wysłać maksymalnie jedno zgłoszenie naruszenia dziennie.'];
        }

        $description = trim((string)($data['description'] ?? ''));
        if (containsProfanity($description) || containsProfanity($data['email'] ?? '') || containsProfanity($data['content_url'] ?? '')) {
            return ['ok' => false, 'message' => 'Zgłoszenie zawiera niedozwolone treści.'];
        }
        $wordCount = countWordsUtf8($description);
        if ($description === '' || mb_strlen($description, 'UTF-8') < 10 || $wordCount > 120) {
            return ['ok' => false, 'message' => 'Opis jest wymagany, musi być konkretny i może mieć maksymalnie 120 słów.'];
        }

        $allowedTypes = [
            'illegal_content', 'privacy', 'abuse', 'copyright',
            'spam', 'harassment', 'cheating', 'offensive_profile', 'offensive_comment', 'bug', 'other'
        ];
        $reportType = preg_replace('/[^a-z0-9_-]/i', '', (string)($data['report_type'] ?? 'other')) ?: 'other';
        if (!in_array($reportType, $allowedTypes, true)) {
            $reportType = 'other';
        }
        $contentUrl = trim((string)($data['content_url'] ?? ''));
        if ($contentUrl !== '' && !filter_var($contentUrl, FILTER_VALIDATE_URL) && !preg_match('#^/[a-zA-Z0-9_\-/?=&%.]+$#', $contentUrl)) {
            return ['ok' => false, 'message' => 'Podaj poprawny link lub ścieżkę do zgłaszanej treści.'];
        }
        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Podaj poprawny adres e-mail albo zostaw pole puste.'];
        }

        if (dbColumnExists($pdo, 'abuse_reports', 'reporter_user_id')) {
            $stmt = $pdo->prepare("
                INSERT INTO abuse_reports (reporter_user_id, report_type, content_url, description, reporter_email, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $reporterUserId ?: null,
                $reportType,
                $contentUrl ?: null,
                mb_substr($description, 0, 2000),
                $email ?: null,
                $ip ?: null,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO abuse_reports (report_type, content_url, description, reporter_email, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $reportType,
                $contentUrl ?: null,
                mb_substr($description, 0, 2000),
                $email ?: null,
                $ip ?: null,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
        }
        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log('Create abuse report failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Nie udało się zapisać zgłoszenia.'];
    }
}

function getAbuseReports(PDO $pdo, int $limit = 80): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT ar.*, reporter.username AS reporter_username, reporter.role AS reporter_role,
                   handler.username AS handler_username
            FROM abuse_reports ar
            LEFT JOIN users reporter ON reporter.id = ar.reporter_user_id
            LEFT JOIN users handler ON handler.id = ar.handled_by
            ORDER BY FIELD(ar.status, 'new','reviewing','resolved','rejected'), ar.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function updateAbuseReportStatus(PDO $pdo, int $reportId, string $status, ?string $note, int $adminId): bool {
    if (!in_array($status, ['new','reviewing','resolved','rejected'], true)) return false;
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            UPDATE abuse_reports
            SET status = ?, admin_note = ?, handled_by = ?, handled_at = NOW()
            WHERE id = ?
        ");
        $ok = $stmt->execute([$status, $note ? mb_substr($note, 0, 2000) : null, $adminId, $reportId]);
        if ($ok) logAdminAction($pdo, $adminId, 'update_abuse_report', 'abuse_report', $reportId, $status);
        return $ok;
    } catch (PDOException $e) {
        return false;
    }
}

function deleteAbuseReport(PDO $pdo, int $reportId, int $adminId): bool {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("DELETE FROM abuse_reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) logAdminAction($pdo, $adminId, 'delete_abuse_report', 'abuse_report', $reportId);
        return $ok;
    } catch (PDOException $e) {
        error_log('Delete abuse report failed: ' . $e->getMessage());
        return false;
    }
}

function applyExamCorrectAnswerOverride(PDO $pdo, int $participantId, int $questionId, string $newCorrectAnswer, int $adminOrTeacherId, string $reason = ''): bool {
    $newCorrectAnswer = strtoupper(trim($newCorrectAnswer));
    if (!in_array($newCorrectAnswer, ['A', 'B', 'C', 'D'], true)) return false;

    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT ep.session_id, ep.user_id, e.teacher_id, e.title
            FROM exam_participants ep
            JOIN exam_sessions es ON es.id = ep.session_id
            JOIN exams e ON e.id = es.exam_id
            WHERE ep.id = ?
            LIMIT 1
        ");
        $stmt->execute([$participantId]);
        $ctx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ctx) return false;

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE exam_session_questions
            SET correct_answer_override = ?, override_reason = ?, override_by = ?, override_at = NOW()
            WHERE session_id = ? AND question_id = ?
        ");
        $stmt->execute([$newCorrectAnswer, mb_substr($reason, 0, 255), $adminOrTeacherId, (int)$ctx['session_id'], $questionId]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE exam_answers
            SET correct_answer = ?, is_correct = (UPPER(COALESCE(user_answer, '')) = ?)
            WHERE session_id = ? AND question_id = ?
        ");
        $stmt->execute([$newCorrectAnswer, $newCorrectAnswer, (int)$ctx['session_id'], $questionId]);

        $stmt = $pdo->prepare("
            UPDATE exam_participants ep
            JOIN (
                SELECT participant_id, COUNT(*) AS answered, COALESCE(SUM(is_correct), 0) AS correct
                FROM exam_answers
                WHERE session_id = ?
                GROUP BY participant_id
            ) x ON x.participant_id = ep.id
            JOIN (
                SELECT session_id, COUNT(*) AS total_questions
                FROM exam_session_questions
                WHERE session_id = ?
                GROUP BY session_id
            ) tq ON tq.session_id = ep.session_id
            SET ep.total_answered = x.answered,
                ep.correct_answers = x.correct,
                ep.score_percent = IF(tq.total_questions > 0, ROUND((x.correct / tq.total_questions) * 100, 2), 0)
            WHERE ep.session_id = ?
        ");
        $stmt->execute([(int)$ctx['session_id'], (int)$ctx['session_id'], (int)$ctx['session_id']]);

        $requestId = createAdminRequest(
            $pdo,
            $adminOrTeacherId,
            'Weryfikacja pytania po sprawdzianie',
            'Zmieniono poprawną odpowiedź tylko dla sesji #' . (int)$ctx['session_id'] . ', pytanie #' . $questionId . ' na ' . $newCorrectAnswer . '. Powód: ' . ($reason ?: 'brak')
        );
        $pdo->commit();

        logAdminAction($pdo, $adminOrTeacherId, 'exam_answer_override', 'question', $questionId, 'session=' . (int)$ctx['session_id'] . '; request=' . $requestId);
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Exam answer override failed: ' . $e->getMessage());
        return false;
    }
}

function sanitizeQuestionImageUrl($value) {
    $url = trim(strip_tags((string)$value));
    $url = str_replace(["\r", "\n", "\0"], '', $url);
    if ($url === '') {
        return null;
    }
    $url = mb_substr($url, 0, 500);
    if (preg_match('#^https?://#i', $url)) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if ($host === '' || !isAllowedRemoteQuestionImageHost($host)) return null;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip && isPrivateIpAddress($ip)) return null;
        $path = $parts['path'] ?? '';
        if ($path !== '' && !preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $path)) return null;
        return $url;
    }
    $url = ltrim($url, '/');
    if (preg_match('#^(assets/images|uploads|data/question_images)/[a-zA-Z0-9_./% -]+\.(png|jpe?g|gif|webp|svg)$#i', $url) && strpos($url, '..') === false) {
        return $url;
    }
    return null;
}

function sanitizeQuestionDataForStorage($data) {
    $correct = strtoupper(sanitizeQuestionPlainText($data['correct_answer'] ?? 'A', 1));
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
        $correct = 'A';
    }

    return [
        'category' => sanitizeQuestionPlainText($data['category'] ?? 'Ogólne', 100) ?: 'Ogólne',
        'question_text' => sanitizeQuestionPlainText($data['question_text'] ?? '', 4000),
        'option_a' => sanitizeQuestionPlainText($data['option_a'] ?? '', 1000),
        'option_b' => sanitizeQuestionPlainText($data['option_b'] ?? '', 1000),
        'option_c' => sanitizeQuestionPlainText($data['option_c'] ?? '', 1000),
        'option_d' => sanitizeQuestionPlainText($data['option_d'] ?? '', 1000),
        'correct_answer' => $correct,
        'explanation' => sanitizeQuestionPlainText($data['explanation'] ?? '', 4000),
        'image_url' => sanitizeQuestionImageUrl($data['image_url'] ?? '')
    ];
}

/**
 * Add a new question to the database
 */
function addQuestion($pdo, $data) {
    $data = sanitizeQuestionDataForStorage($data);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO questions (category, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['category'] ?? 'Ogólne',
            $data['question_text'],
            $data['option_a'],
            $data['option_b'],
            $data['option_c'],
            $data['option_d'],
            $data['correct_answer'],
            $data['explanation'] ?? null,
            $data['image_url'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Error adding question: " . $e->getMessage());
        return false;
    }
}

function calculateRankedTestXpDelta($scorePct): int {
    $scorePct = max(0, min(100, (float)$scorePct));
    if ($scorePct > 75) {
        return (int)ceil(($scorePct - 75) / 5) * 10;
    }
    if ($scorePct < 50) {
        return -(int)ceil((50 - $scorePct) / 5) * 40;
    }
    return 0;
}

function getActiveRankingEvents(PDO $pdo, int $limit = 2): array {
    try {
        ensurePlatformEnhancements($pdo);
        $pdo->exec("UPDATE ranking_events SET status = 'finished' WHERE status = 'active' AND ends_at < NOW()");
        $stmt = $pdo->prepare("
            SELECT *
            FROM ranking_events
            WHERE status = 'active' AND starts_at <= NOW() AND ends_at >= NOW()
            ORDER BY multiplier DESC, starts_at DESC, id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, min(2, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Active ranking event lookup failed: ' . $e->getMessage());
        return [];
    }
}

function getActiveRankingEvent(PDO $pdo): ?array {
    try {
        ensurePlatformEnhancements($pdo);
        $activeEvents = getActiveRankingEvents($pdo, 2);
        $event = $activeEvents[0] ?? null;
        if ($event) return $event;

        $hasFuture = (int)$pdo->query("SELECT COUNT(*) FROM ranking_events WHERE status IN ('scheduled','active') AND ends_at >= NOW()")->fetchColumn();
        if ($hasFuture > 0) return null;

        $template = $pdo->query("SELECT * FROM ranking_event_templates WHERE is_active = 1 ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$template) return null;

        $duration = max(7, (int)$template['duration_days']);
        $stmt = $pdo->prepare("
            INSERT INTO ranking_events (template_id, name, description, multiplier, starts_at, ends_at, status)
            VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active')
        ");
        $stmt->execute([
            (int)$template['id'],
            $template['name'],
            $template['description'],
            (float)$template['multiplier'],
            $duration
        ]);

        $stmt = $pdo->prepare("SELECT * FROM ranking_events WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$pdo->lastInsertId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('Active ranking event lookup failed: ' . $e->getMessage());
        return null;
    }
}

function applyActiveRankingMultiplier(PDO $pdo, int $xpDelta): array {
    if ($xpDelta <= 0) {
        return ['amount' => $xpDelta, 'event' => null];
    }

    $events = getActiveRankingEvents($pdo, 2);
    $event = $events[0] ?? null;
    if (!$event) {
        return ['amount' => $xpDelta, 'event' => null];
    }

    $multiplier = max(1.0, (float)$event['multiplier']);
    return [
        'amount' => (int)round($xpDelta * $multiplier),
        'event' => $event
    ];
}

function getRankingEvents(PDO $pdo, int $limit = 8): array {
    try {
        ensurePlatformEnhancements($pdo);
        $stmt = $pdo->prepare("
            SELECT *
            FROM ranking_events
            WHERE status IN ('active', 'scheduled')
               OR id IN (
                    SELECT id FROM (
                        SELECT id
                        FROM ranking_events
                        WHERE status = 'finished'
                        ORDER BY ends_at DESC, id DESC
                        LIMIT 2
                    ) finished_events
               )
            ORDER BY FIELD(status, 'active','scheduled','finished','cancelled'), starts_at ASC, ends_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($events)) {
            $active = getActiveRankingEvent($pdo);
            return $active ? [$active] : [];
        }
        return $events;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Update an existing question
 */
function updateQuestion($pdo, $id, $data) {
    $data = sanitizeQuestionDataForStorage($data);
    try {
        $stmt = $pdo->prepare("
            UPDATE questions SET 
                category = ?, question_text = ?, 
                option_a = ?, option_b = ?, option_c = ?, option_d = ?, 
                correct_answer = ?, explanation = ?, image_url = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['category'],
            $data['question_text'],
            $data['option_a'],
            $data['option_b'],
            $data['option_c'],
            $data['option_d'],
            strtoupper($data['correct_answer']),
            $data['explanation'],
            $data['image_url'],
            $id
        ]);
    } catch (PDOException $e) {
        error_log("Error updating question: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a question
 */
function deleteQuestion($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error deleting question: " . $e->getMessage());
        return false;
    }
}

// Normal requests update activity only; schema changes belong to installation/migration CLI.
if (isset($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
    updateUserActivity($pdo, $_SESSION['user_id']);
}

/**
 * Get verified badge HTML for user
 */
function getUserBadgeHtml($role, $isVerified) {
    if ($isVerified || in_array($role, privilegedStaffRoles(), true)) {
        return ' <i class="bi bi-patch-check-fill text-primary" title="Profil zweryfikowany" style="font-size: 0.95em; vertical-align: middle;"></i>';
    }
    return '';
}

function duelRevengeIsAvailable(PDO $pdo, int $parentId, int $userId, int $opponentId): bool {
    if ($parentId <= 0) return false;
    $stmt = $pdo->prepare("
        SELECT challenger_id, opponent_id, status, challenger_finished_at, opponent_finished_at
        FROM duels
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$parentId]);
    $duel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$duel || ($duel['status'] ?? '') !== 'finished') return false;
    $participants = [(int)$duel['challenger_id'], (int)$duel['opponent_id']];
    if (!in_array($userId, $participants, true) || !in_array($opponentId, $participants, true) || $userId === $opponentId) {
        return false;
    }
    $finishedTs = 0;
    foreach ([$duel['challenger_finished_at'] ?? null, $duel['opponent_finished_at'] ?? null] as $finishedAt) {
        if (!empty($finishedAt)) $finishedTs = max($finishedTs, strtotime((string)$finishedAt) ?: 0);
    }
    return $finishedTs > 0 && (time() - $finishedTs) <= 600;
}
