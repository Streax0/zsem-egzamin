-- ============================================================
-- FULL UNIFIED SCHEMA - ZSEM Tech Platform
-- Zawiera bazę podstawową + wszystkie rozszerzenia (v2)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Usuwanie istniejących tabel (kolejność ma znaczenie ze względu na klucze obce)
-- --------------------------------------------------------
DROP TABLE IF EXISTS user_daily_missions;
DROP TABLE IF EXISTS admin_audit_log;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS abuse_reports;
DROP TABLE IF EXISTS admin_request_replies;
DROP TABLE IF EXISTS admin_requests;
DROP TABLE IF EXISTS ranking_events;
DROP TABLE IF EXISTS ranking_event_templates;
DROP TABLE IF EXISTS sandbox_element_blocks;
DROP TABLE IF EXISTS feature_page_blocks;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS rank_definitions;
DROP TABLE IF EXISTS profile_comments;
DROP TABLE IF EXISTS user_social_links;
DROP TABLE IF EXISTS user_organizations;
DROP TABLE IF EXISTS user_languages;
DROP TABLE IF EXISTS user_volunteering;
DROP TABLE IF EXISTS user_courses;
DROP TABLE IF EXISTS user_certificates;
DROP TABLE IF EXISTS user_education;
DROP TABLE IF EXISTS xp_events;
DROP TABLE IF EXISTS luki_spins;
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS badges;
DROP TABLE IF EXISTS duel_answers;
DROP TABLE IF EXISTS duels;
DROP TABLE IF EXISTS exam_warnings;
DROP TABLE IF EXISTS exam_violations;
DROP TABLE IF EXISTS exam_answers;
DROP TABLE IF EXISTS exam_participants;
DROP TABLE IF EXISTS exam_session_questions;
DROP TABLE IF EXISTS exam_sessions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS friends;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS unranked_usage;
DROP TABLE IF EXISTS all_in_duel_usage;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS user_active_tests;
DROP TABLE IF EXISTS test_answers;
DROP TABLE IF EXISTS user_question_progress;
DROP TABLE IF EXISTS test_results;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS banned_ips;
DROP TABLE IF EXISTS banned_emails;
DROP TABLE IF EXISTS active_user_sessions;
DROP TABLE IF EXISTS users;

-- --------------------------------------------------------
-- 1. Tabela użytkowników (zintegrowana z v2)
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'teacher', 'admin', 'dyrektor', 'wujek_luki') DEFAULT 'user',
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    class VARCHAR(20) DEFAULT NULL,
    class_year TINYINT UNSIGNED DEFAULT NULL,
    class_suffix VARCHAR(2) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    avatar_changed_at DATETIME DEFAULT NULL,
    xp INT DEFAULT 4100,
    profile_public TINYINT(1) DEFAULT 1,
    stats_public TINYINT(1) DEFAULT 1,
    allow_profile_comments TINYINT(1) DEFAULT 1,
    allow_friend_requests TINYINT(1) DEFAULT 1,
    searchable TINYINT(1) DEFAULT 1,
    is_verified TINYINT(1) DEFAULT 0,
    verified_at DATETIME DEFAULT NULL,
    verified_by_admin_id INT DEFAULT NULL,
    ranking_visible TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(255) DEFAULT NULL,
    is_banned TINYINT(1) DEFAULT 0,
    ban_expires_at DATETIME DEFAULT NULL,
    trust_status VARCHAR(30) NOT NULL DEFAULT 'trusted',
    risk_flags TEXT DEFAULT NULL,
    registration_ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    last_activity DATETIME DEFAULT NULL,
    session_version INT NOT NULL DEFAULT 1,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_verified (is_verified),
    INDEX idx_banned (is_banned),
    INDEX idx_ban_expiry (is_banned, ban_expires_at),
    INDEX idx_trust_status (trust_status),
    INDEX idx_registration_ip (registration_ip),
    INDEX idx_role_xp_activity (role, xp, last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE active_user_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent_hash CHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_session (user_id, session_hash),
    INDEX idx_user_last_seen (user_id, last_seen),
    INDEX idx_last_seen (last_seen),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 1d. Definicje rang XP
-- --------------------------------------------------------
CREATE TABLE rank_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    min_xp INT NOT NULL,
    icon VARCHAR(80) NOT NULL DEFAULT 'bi-shield-fill',
    color VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rank_name (name),
    INDEX idx_min_xp (min_xp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rank_definitions (name, min_xp, icon, color, description) VALUES
('Bronze V', 0, 'bi-shield', '#64748b', 'Startowa ranga użytkownika.'),
('Bronze IV', 250, 'bi-shield', '#64748b', 'Początek drogi w IT.'),
('Bronze III', 500, 'bi-shield', '#64748b', 'Pierwsze kroki za Tobą.'),
('Bronze II', 800, 'bi-shield', '#64748b', 'Rozgrzewka przed wyzwaniami.'),
('Bronze I', 1100, 'bi-shield', '#64748b', 'Granica wejścia do wyższej ligi.'),
('Silver V', 1500, 'bi-shield-fill', '#94a3b8', 'Pierwszy próg regularnej nauki.'),
('Silver IV', 2000, 'bi-shield-fill', '#94a3b8', 'Stabilny postęp w nauce.'),
('Silver III', 2600, 'bi-shield-fill', '#94a3b8', 'Coraz lepsza znajomość materiału.'),
('Silver II', 3300, 'bi-shield-fill', '#94a3b8', 'Dobry poziom systematyczności.'),
('Silver I', 4100, 'bi-shield-fill', '#94a3b8', 'Granica wejścia do złotej ligi.'),
('Gold V', 5000, 'bi-award-fill', '#f59e0b', 'Zaawansowany poziom aktywności.'),
('Gold IV', 6000, 'bi-award-fill', '#f59e0b', 'Wysoka regularność pracy.'),
('Gold III', 7100, 'bi-award-fill', '#f59e0b', 'Złoty standard wiedzy.'),
('Gold II', 8300, 'bi-award-fill', '#f59e0b', 'Wybitna systematyczność.'),
('Gold I', 9600, 'bi-award-fill', '#f59e0b', 'Prestiżowy poziom Gold.'),
('Platinum V', 11000, 'bi-gem', '#0ea5e9', 'Platynowy progres.'),
('Platinum IV', 12500, 'bi-gem', '#0ea5e9', 'Wysoka biegłość w testach.'),
('Platinum III', 14100, 'bi-gem', '#0ea5e9', 'Dążenie do perfekcji.'),
('Platinum II', 15800, 'bi-gem', '#0ea5e9', 'Mistrzowski poziom Platyny.'),
('Platinum I', 17600, 'bi-gem', '#0ea5e9', 'Ostatni krok przed Diamentem.'),
('Diamond V', 19500, 'bi-diamond-fill', '#8b5cf6', 'Ekspercki poziom Diamond.'),
('Diamond IV', 21500, 'bi-diamond-fill', '#8b5cf6', 'Wyjątkowa wiedza techniczna.'),
('Diamond III', 23600, 'bi-diamond-fill', '#8b5cf6', 'Diamentowa precyzja.'),
('Diamond II', 25800, 'bi-diamond-fill', '#8b5cf6', 'Weteran platformy.'),
('Diamond I', 28100, 'bi-diamond-fill', '#8b5cf6', 'Najwyższy poziom Diamentu.'),
('Master V', 30500, 'bi-stars', '#ec4899', 'Mistrzowski poziom Master.'),
('Master IV', 33000, 'bi-stars', '#ec4899', 'Incredible skills.'),
('Master III', 35600, 'bi-stars', '#ec4899', 'Master of the platform.'),
('Master II', 38300, 'bi-stars', '#ec4899', 'Legendary status.'),
('Master I', 41100, 'bi-stars', '#ec4899', 'Absolute mastery.'),
('Grandmaster V', 44000, 'bi-trophy-fill', '#ef4444', 'Grandmaster of ZSEM Tech.'),
('Grandmaster IV', 47000, 'bi-trophy-fill', '#ef4444', 'Elite knowledge.'),
('Grandmaster III', 50100, 'bi-trophy-fill', '#ef4444', 'World-class expertise.'),
('Grandmaster II', 53300, 'bi-trophy-fill', '#ef4444', 'Peak performance.'),
('Grandmaster I', 56600, 'bi-trophy-fill', '#ef4444', 'Ultimate Grandmaster.'),
('Wujek luki', 75000, 'bi-crown-fill', '#facc15', 'Legenda ZSEM Tech.');

CREATE TABLE app_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feature_page_blocks (
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category_active (category_key, is_active),
    INDEX idx_active_disabled (is_active, disabled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sandbox_element_blocks (
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_element_active (element_key, is_active),
    INDEX idx_active_disabled (is_active, disabled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ranking_event_templates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ranking_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT DEFAULT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(255) NOT NULL,
    multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.10,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('scheduled','active','finished','cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ranking_event_templates(id) ON DELETE SET NULL,
    INDEX idx_status_dates (status, starts_at, ends_at),
    INDEX idx_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent','read','replied','closed') NOT NULL DEFAULT 'sent',
    admin_reply TEXT DEFAULT NULL,
    replied_by INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_created (status, created_at),
    INDEX idx_teacher_created (teacher_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_request_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    admin_id INT NOT NULL,
    reply_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES admin_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_request_created (request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE abuse_reports (
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_created (status, created_at),
    INDEX idx_reporter_created (reporter_user_id, created_at),
    INDEX idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lessons (
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
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status_created (status, created_at),
    INDEX idx_teacher_status (teacher_id, status),
    INDEX idx_qualification_status (qualification, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(80) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_created (admin_id, created_at),
    INDEX idx_action_created (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 1b. Tabela zablokowanych e-maili
-- --------------------------------------------------------
CREATE TABLE banned_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    reason TEXT DEFAULT NULL,
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    banned_by INT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    INDEX idx_email (email),
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 1c. Tabela zablokowanych adresów IP
-- --------------------------------------------------------
CREATE TABLE banned_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    reason TEXT DEFAULT NULL,
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    banned_by INT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    INDEX idx_ip (ip_address),
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Tabela pytań
-- --------------------------------------------------------
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL DEFAULT '',
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL DEFAULT '',
    option_b VARCHAR(500) NOT NULL DEFAULT '',
    option_c VARCHAR(500) NOT NULL DEFAULT '',
    option_d VARCHAR(500) NOT NULL DEFAULT '',
    correct_answer CHAR(1) CHARACTER SET ascii NOT NULL,
    explanation TEXT,
    image_url VARCHAR(500) DEFAULT NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabela wyników testów
-- --------------------------------------------------------
CREATE TABLE test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    test_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    start_time DATETIME DEFAULT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    score_percent DECIMAL(5,2) NOT NULL,
    time_spent INT,
    mode ENUM('exam', 'practice', 'single', 'exam_simulator') DEFAULT 'exam',
    exclude_from_ranking TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_test_date (test_date),
    INDEX idx_user_test_date (user_id, test_date),
    INDEX idx_ranking_tests (total_questions, exclude_from_ranking),
    INDEX idx_user_mode_date (user_id, mode, test_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabela postępu użytkownika
-- --------------------------------------------------------
CREATE TABLE user_question_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    times_seen INT DEFAULT 0,
    times_correct INT DEFAULT 0,
    last_seen DATETIME,
    is_mastered BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_user_question (user_id, question_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_question_id (question_id),
    INDEX idx_mastered (is_mastered),
    INDEX idx_user_mastered_last (user_id, is_mastered, last_seen),
    INDEX idx_question_mastered (question_id, is_mastered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabela odpowiedzi w testach
-- --------------------------------------------------------
CREATE TABLE test_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer CHAR(1) CHARACTER SET ascii,
    correct_answer CHAR(1) CHARACTER SET ascii NOT NULL,
    is_correct BOOLEAN NOT NULL,
    FOREIGN KEY (result_id) REFERENCES test_results(id) ON DELETE CASCADE,
    INDEX idx_result_id (result_id),
    INDEX idx_question_correct (question_id, is_correct),
    INDEX idx_result_question (result_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_active_tests (
    user_id INT NOT NULL PRIMARY KEY,
    payload LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Tabela powiadomień
-- --------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    dedupe_key VARCHAR(160) DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_user_unread_created (user_id, is_read, created_at),
    INDEX idx_user_dedupe (user_id, dedupe_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Tabela sprawdzianów nauczyciela (exams)
-- --------------------------------------------------------
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    question_count INT DEFAULT 40,
    selected_questions JSON DEFAULT NULL,
    categories JSON DEFAULT NULL,
    difficulty_level ENUM('easy', 'medium', 'hard', 'mixed') DEFAULT 'mixed',
    shuffle_questions TINYINT(1) DEFAULT 1,
    shuffle_answers TINYINT(1) DEFAULT 0,
    max_participants INT DEFAULT 36,
    time_per_question INT DEFAULT NULL,
    total_time INT DEFAULT NULL,
    exam_mode TINYINT(1) DEFAULT 1,
    auto_finish_on_time TINYINT(1) DEFAULT 1,
    allow_rejoin TINYINT(1) DEFAULT 0,
    anti_cheat_enabled TINYINT(1) DEFAULT 0,
    block_tab_switch TINYINT(1) DEFAULT 0,
    require_fullscreen TINYINT(1) DEFAULT 0,
    lobby_enabled TINYINT(1) DEFAULT 1,
    show_results_to_student TINYINT(1) DEFAULT 0,
    show_predicted_grade TINYINT(1) DEFAULT 0,
    show_correct_answers TINYINT(1) DEFAULT 0,
    randomize_per_student TINYINT(1) DEFAULT 0,
    lock_after_finish TINYINT(1) DEFAULT 1,
    pass_threshold TINYINT UNSIGNED DEFAULT 50,
    max_attempts TINYINT UNSIGNED DEFAULT 1,
    navigation_mode VARCHAR(30) NOT NULL DEFAULT 'free',
    allow_answer_changes TINYINT(1) NOT NULL DEFAULT 1,
    warning_limit TINYINT UNSIGNED DEFAULT NULL,
    warning_action VARCHAR(30) NOT NULL DEFAULT 'notify',
    late_join_cutoff_minutes TINYINT UNSIGNED DEFAULT NULL,
    results_available_at DATETIME DEFAULT NULL,
    print_include_answer_key TINYINT(1) NOT NULL DEFAULT 0,
    available_from DATETIME DEFAULT NULL,
    available_until DATETIME DEFAULT NULL,
    grade_thresholds JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Sesje egzaminacyjne
-- --------------------------------------------------------
CREATE TABLE exam_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    access_code VARCHAR(20) UNIQUE NOT NULL,
    status ENUM('lobby', 'in_progress', 'paused', 'finished', 'expired') DEFAULT 'lobby',
    started_at DATETIME DEFAULT NULL,
    paused_at DATETIME DEFAULT NULL,
    paused_seconds INT NOT NULL DEFAULT 0,
    finished_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    INDEX idx_code (access_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Pytania przypisane do sesji (Snapshot)
-- --------------------------------------------------------
CREATE TABLE exam_session_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT DEFAULT 0,
    correct_answer_override CHAR(1) CHARACTER SET ascii DEFAULT NULL,
    override_reason VARCHAR(255) DEFAULT NULL,
    override_by INT DEFAULT NULL,
    override_at DATETIME DEFAULT NULL,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_session_question (session_id, question_id),
    INDEX idx_session_order (session_id, question_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Uczestnicy sprawdzianu
-- --------------------------------------------------------
CREATE TABLE exam_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    class VARCHAR(20) NOT NULL,
    status ENUM('in_lobby', 'taking_exam', 'finished', 'removed', 'disconnected') DEFAULT 'in_lobby',
    current_question INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    total_answered INT DEFAULT 0,
    score_percent DECIMAL(5,2) DEFAULT 0,
    time_spent INT DEFAULT 0,
    violation_count INT DEFAULT 0,
    started_at DATETIME DEFAULT NULL,
    finished_at DATETIME DEFAULT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_session_user_status (session_id, user_id, status),
    INDEX idx_session_status_joined (session_id, status, joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Odpowiedzi w sprawdzianie
-- --------------------------------------------------------
CREATE TABLE exam_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT DEFAULT 0,
    user_answer CHAR(1) CHARACTER SET ascii DEFAULT NULL,
    correct_answer CHAR(1) CHARACTER SET ascii NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    time_spent INT DEFAULT 0,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participant_question (participant_id, question_id),
    FOREIGN KEY (participant_id) REFERENCES exam_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    INDEX idx_session_participant (session_id, participant_id),
    INDEX idx_participant_order (participant_id, question_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. Naruszenia zasad
-- --------------------------------------------------------
CREATE TABLE exam_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    session_id INT NOT NULL,
    violation_type ENUM('tab_switch', 'window_blur', 'fullscreen_exit', 'copy_paste', 'other') NOT NULL,
    question_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES exam_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. Misje dzienne
-- --------------------------------------------------------
CREATE TABLE user_daily_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mission_type VARCHAR(50) NOT NULL,
    mission_description TEXT NOT NULL,
    target_value INT DEFAULT 1,
    current_value INT DEFAULT 0,
    xp_reward INT DEFAULT 10,
    is_completed TINYINT(1) DEFAULT 0,
    assigned_date DATE NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    UNIQUE KEY unique_user_mission_day (user_id, mission_type, assigned_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, assigned_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13b. Dziennik XP i rozszerzone profile
-- --------------------------------------------------------
CREATE TABLE xp_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source VARCHAR(50) NOT NULL,
    source_id INT DEFAULT NULL,
    amount INT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_source (source, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE luki_spins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spin_date DATE NOT NULL,
    archetype VARCHAR(40) NOT NULL,
    label VARCHAR(120) NOT NULL,
    xp_delta INT NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, spin_date),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_education (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    level ENUM('podstawowe','średnie','wyższe') NOT NULL,
    school_name VARCHAR(160) NOT NULL,
    field VARCHAR(160) DEFAULT NULL,
    start_year SMALLINT NOT NULL,
    end_year SMALLINT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT DEFAULT NULL,
    name VARCHAR(160) NOT NULL,
    organization VARCHAR(160) NOT NULL,
    certificate_code VARCHAR(64) DEFAULT NULL,
    obtained_date DATE DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    UNIQUE KEY uk_user_course (user_id, course_id),
    UNIQUE KEY uk_cert_code (certificate_code),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(160) NOT NULL,
    provider VARCHAR(160) NOT NULL,
    completed_date DATE DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_volunteering (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization VARCHAR(160) NOT NULL,
    role_name VARCHAR(160) NOT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    language_name VARCHAR(80) NOT NULL,
    level ENUM('podstawowy','średni','zaawansowany','biegły') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(160) NOT NULL,
    role_name VARCHAR(160) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_social_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    platform ENUM('github','linkedin','instagram','youtube','facebook','x','tiktok','gitlab') NOT NULL,
    url VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_platform (user_id, platform),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_user_id INT NOT NULL,
    author_id INT NOT NULL,
    comment_text VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_profile_created (profile_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14. Znajomi
-- --------------------------------------------------------
CREATE TABLE friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending', 'accepted') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_friendship (user_id, friend_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_friend_lookup (user_id, friend_id, status),
    INDEX idx_friend_reverse (friend_id, user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14b. Pojedynki znajomych i ostrzeżenia egzaminacyjne
-- --------------------------------------------------------
CREATE TABLE duels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenger_id INT NOT NULL,
    opponent_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    question_count INT NOT NULL DEFAULT 10,
    question_ids JSON DEFAULT NULL,
    mode VARCHAR(30) NOT NULL DEFAULT 'classic',
    preset VARCHAR(40) NOT NULL DEFAULT 'classic',
    stake_xp INT NOT NULL DEFAULT 0,
    underdog_bonus DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    time_per_question_seconds INT DEFAULT NULL,
    total_time_seconds INT DEFAULT NULL,
    require_answer_confirmation TINYINT(1) NOT NULL DEFAULT 0,
    allow_early_finish TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending','accepted','declined','finished','expired') NOT NULL DEFAULT 'pending',
    challenger_score_percent DECIMAL(5,2) DEFAULT 0,
    opponent_score_percent DECIMAL(5,2) DEFAULT 0,
    challenger_time_spent INT DEFAULT NULL,
    opponent_time_spent INT DEFAULT NULL,
    challenger_finished_at DATETIME DEFAULT NULL,
    opponent_finished_at DATETIME DEFAULT NULL,
    challenger_started_at DATETIME DEFAULT NULL,
    opponent_started_at DATETIME DEFAULT NULL,
    challenger_hidden_at DATETIME DEFAULT NULL,
    opponent_hidden_at DATETIME DEFAULT NULL,
    winner_id INT DEFAULT NULL,
    revenge_parent_id INT DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_opponent_status (opponent_id, status),
    INDEX idx_challenger_status (challenger_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE duel_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    duel_id INT NOT NULL,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer CHAR(1) CHARACTER SET ascii NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    time_spent INT NOT NULL DEFAULT 0,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_duel_user_question (duel_id, user_id, question_id),
    FOREIGN KEY (duel_id) REFERENCES duels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exam_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    session_id INT NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (participant_id) REFERENCES exam_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    INDEX idx_participant_read (participant_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE unranked_usage (
    user_id INT NOT NULL,
    used_date DATE NOT NULL,
    usage_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, used_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE all_in_duel_usage (
    user_id INT NOT NULL,
    usage_date DATE NOT NULL,
    usage_count INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, usage_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 15. Odznaki (Badges)
-- --------------------------------------------------------
CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(50) NOT NULL DEFAULT 'bi-award',
    color VARCHAR(20) NOT NULL DEFAULT '#3b82f6',
    criteria_type VARCHAR(50) NOT NULL,
    criteria_value INT DEFAULT 1,
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed: Odznaki domyślne
-- --------------------------------------------------------
INSERT IGNORE INTO badges (slug, name, description, icon, color, criteria_type, criteria_value, rarity) VALUES
('first_perfect', 'Pierwsza setka', '100% poprawnych odpowiedzi na teście', 'bi-trophy-fill', '#f59e0b', 'perfect_score', 1, 'rare'),
('winning_streak', 'Seria zwycięstw', '5 testów z rzędu z wynikiem >80%', 'bi-fire', '#ef4444', 'win_streak', 5, 'epic'),
('bookworm', 'Bibliotekarz', 'Rozwiąż 500 pytań', 'bi-book-fill', '#8b5cf6', 'questions_answered', 500, 'uncommon'),
('speedster', 'Błyskawica', 'Test ukończony w <5 min z wynikiem >90%', 'bi-lightning-fill', '#eab308', 'speed_test', 1, 'epic'),
('sniper', 'Snajper', '10 testów z rzędu bez błędu', 'bi-crosshair', '#10b981', 'flawless_streak', 10, 'legendary'),
('inf02_master', 'Mistrz INF.02', 'Opanowanie ponad 80% wszystkich pytań', 'bi-mortarboard-fill', '#6366f1', 'mastery_percent', 80, 'legendary'),
('fair_player', 'Uczciwy gracz', '0 naruszeń w sprawdzianie nauczyciela', 'bi-shield-check', '#22c55e', 'zero_violations', 1, 'common'),
('first_test', 'Pierwszy krok', 'Ukończ swój pierwszy test', 'bi-rocket-takeoff-fill', '#3b82f6', 'tests_completed', 1, 'common'),
('test_veteran', 'Weteran', 'Ukończ 50 testów', 'bi-star-fill', '#f97316', 'tests_completed', 50, 'rare'),
('night_owl', 'Nocna sowa', 'Rozwiąż test po godzinie 22:00', 'bi-moon-stars-fill', '#6366f1', 'night_test', 1, 'uncommon'),
('early_bird', 'Ranny ptaszek', 'Rozwiąż test przed godziną 7:00', 'bi-sunrise-fill', '#f59e0b', 'early_test', 1, 'uncommon'),
('social_butterfly', 'Towarzyski', 'Dodaj 10 znajomych', 'bi-people-fill', '#ec4899', 'friends_count', 10, 'uncommon'),
('exam_survivor', 'Ocalały', 'Ukończ sprawdzian nauczyciela', 'bi-award-fill', '#14b8a6', 'exam_completed', 1, 'common'),
('top_scorer', 'Najlepszy wynik', 'Zajmij 1. miejsce w sprawdzianie nauczyciela', 'bi-1-circle-fill', '#f59e0b', 'exam_first_place', 1, 'epic'),
('marathon', 'Maratończyk', 'Rozwiąż łącznie 1000 pytań', 'bi-infinity', '#dc2626', 'questions_answered', 1000, 'rare');

-- --------------------------------------------------------
-- Logowanie (Rate Limiting)
-- --------------------------------------------------------
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    username VARCHAR(50),
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_ip (ip_address),
    INDEX idx_username_time (username, attempt_time),
    INDEX idx_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_expires_used (expires_at, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_mfa (
    user_id INT NOT NULL PRIMARY KEY,
    secret VARCHAR(64) NOT NULL,
    enabled_at DATETIME DEFAULT NULL,
    recovery_codes_hash TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled (enabled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limit_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(80) NOT NULL,
    identity_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bucket_identity_created (bucket, identity_hash, created_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Aktualizacje istniejących instalacji bez kasowania danych
-- --------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS allow_profile_comments TINYINT(1) DEFAULT 1 AFTER stats_public;
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL AFTER bio;
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_changed_at DATETIME DEFAULT NULL AFTER avatar_path;
ALTER TABLE users ADD COLUMN IF NOT EXISTS ranking_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL AFTER is_verified;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verified_by_admin_id INT DEFAULT NULL AFTER verified_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS ban_expires_at DATETIME DEFAULT NULL AFTER is_banned;
ALTER TABLE users ADD COLUMN IF NOT EXISTS trust_status VARCHAR(30) NOT NULL DEFAULT 'trusted' AFTER is_banned;
ALTER TABLE users ADD COLUMN IF NOT EXISTS risk_flags TEXT DEFAULT NULL AFTER trust_status;
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 1 AFTER last_activity;
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_ban_expiry (is_banned, ban_expires_at);
ALTER TABLE users MODIFY role ENUM('user','teacher','admin','dyrektor','wujek_luki') DEFAULT 'user';
ALTER TABLE users MODIFY xp INT DEFAULT 4100;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS dedupe_key VARCHAR(160) DEFAULT NULL AFTER is_read;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS action_url VARCHAR(500) DEFAULT NULL AFTER dedupe_key;
ALTER TABLE abuse_reports ADD COLUMN IF NOT EXISTS reporter_user_id INT DEFAULT NULL AFTER id;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS preset VARCHAR(40) NOT NULL DEFAULT 'classic' AFTER mode;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS time_per_question_seconds INT DEFAULT NULL AFTER underdog_bonus;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS total_time_seconds INT DEFAULT NULL AFTER time_per_question_seconds;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS require_answer_confirmation TINYINT(1) NOT NULL DEFAULT 0 AFTER total_time_seconds;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS allow_early_finish TINYINT(1) NOT NULL DEFAULT 1 AFTER require_answer_confirmation;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS challenger_started_at DATETIME DEFAULT NULL AFTER opponent_finished_at;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS opponent_started_at DATETIME DEFAULT NULL AFTER challenger_started_at;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS challenger_hidden_at DATETIME DEFAULT NULL AFTER opponent_started_at;
ALTER TABLE duels ADD COLUMN IF NOT EXISTS opponent_hidden_at DATETIME DEFAULT NULL AFTER challenger_hidden_at;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS navigation_mode VARCHAR(30) NOT NULL DEFAULT 'free' AFTER max_attempts;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS allow_answer_changes TINYINT(1) NOT NULL DEFAULT 1 AFTER navigation_mode;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS warning_limit TINYINT UNSIGNED DEFAULT NULL AFTER allow_answer_changes;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS warning_action VARCHAR(30) NOT NULL DEFAULT 'notify' AFTER warning_limit;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS late_join_cutoff_minutes TINYINT UNSIGNED DEFAULT NULL AFTER warning_action;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS results_available_at DATETIME DEFAULT NULL AFTER late_join_cutoff_minutes;
ALTER TABLE exams ADD COLUMN IF NOT EXISTS print_include_answer_key TINYINT(1) NOT NULL DEFAULT 0 AFTER results_available_at;
ALTER TABLE exam_session_questions ADD COLUMN IF NOT EXISTS correct_answer_override CHAR(1) CHARACTER SET ascii DEFAULT NULL AFTER question_order;
ALTER TABLE exam_session_questions ADD COLUMN IF NOT EXISTS override_reason VARCHAR(255) DEFAULT NULL AFTER correct_answer_override;
ALTER TABLE exam_session_questions ADD COLUMN IF NOT EXISTS override_by INT DEFAULT NULL AFTER override_reason;
ALTER TABLE exam_session_questions ADD COLUMN IF NOT EXISTS override_at DATETIME DEFAULT NULL AFTER override_by;
ALTER TABLE exam_session_questions ADD INDEX IF NOT EXISTS idx_session_question (session_id, question_id);
ALTER TABLE exam_session_questions ADD INDEX IF NOT EXISTS idx_session_order (session_id, question_order);
ALTER TABLE profile_comments MODIFY comment_text VARCHAR(100) NOT NULL;
ALTER TABLE test_results ADD INDEX IF NOT EXISTS idx_user_test_date (user_id, test_date);
ALTER TABLE test_results ADD INDEX IF NOT EXISTS idx_ranking_tests (total_questions, exclude_from_ranking);
ALTER TABLE test_results ADD INDEX IF NOT EXISTS idx_user_mode_date (user_id, mode, test_date);
ALTER TABLE test_answers ADD INDEX IF NOT EXISTS idx_question_correct (question_id, is_correct);
ALTER TABLE test_answers ADD INDEX IF NOT EXISTS idx_result_question (result_id, question_id);
ALTER TABLE user_question_progress ADD INDEX IF NOT EXISTS idx_user_mastered_last (user_id, is_mastered, last_seen);
ALTER TABLE user_question_progress ADD INDEX IF NOT EXISTS idx_question_mastered (question_id, is_mastered);
ALTER TABLE user_active_tests ADD INDEX IF NOT EXISTS idx_updated_at (updated_at);
ALTER TABLE exam_participants ADD INDEX IF NOT EXISTS idx_session_user_status (session_id, user_id, status);
ALTER TABLE exam_participants ADD INDEX IF NOT EXISTS idx_session_status_joined (session_id, status, joined_at);
ALTER TABLE exam_answers ADD INDEX IF NOT EXISTS idx_session_participant (session_id, participant_id);
ALTER TABLE exam_answers ADD INDEX IF NOT EXISTS idx_participant_order (participant_id, question_order);
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_user_unread_created (user_id, is_read, created_at);
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_user_dedupe (user_id, dedupe_key, created_at);
ALTER TABLE friends ADD INDEX IF NOT EXISTS idx_friend_lookup (user_id, friend_id, status);
ALTER TABLE friends ADD INDEX IF NOT EXISTS idx_friend_reverse (friend_id, user_id, status);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role_xp_activity (role, xp, last_activity);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_trust_status (trust_status);
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    body TEXT NOT NULL,
    qualification VARCHAR(20) NOT NULL DEFAULT 'general',
    lesson_type ENUM('lesson','homework') NOT NULL DEFAULT 'lesson',
    status ENUM('published','archived') NOT NULL DEFAULT 'published',
    due_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_created (status, created_at),
    INDEX idx_teacher_status (teacher_id, status),
    INDEX idx_qualification_status (qualification, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS luki_spins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spin_date DATE NOT NULL,
    archetype VARCHAR(40) NOT NULL,
    label VARCHAR(120) NOT NULL,
    xp_delta INT NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, spin_date),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content LONGTEXT,
    image_url VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT NULL,
    estimated_hours INT UNSIGNED DEFAULT NULL,
    status ENUM('active', 'hidden') NOT NULL DEFAULT 'hidden',
    sequential_learning TINYINT(1) NOT NULL DEFAULT 0,
    has_certificate TINYINT(1) NOT NULL DEFAULT 1,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course_sort (course_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_custom_labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    tool_key VARCHAR(50) NOT NULL,
    instructions TEXT NOT NULL,
    topology_data LONGTEXT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type ENUM('text', 'video', 'quiz', 'lab', 'exam') NOT NULL,
    content LONGTEXT NULL,
    video_url VARCHAR(255) NULL,
    quiz_passing_score INT NOT NULL DEFAULT 70,
    lab_source ENUM('sandbox', 'custom') DEFAULT 'sandbox',
    lab_tool_key VARCHAR(50) NULL,
    lab_custom_id INT NULL,
    lab_instructions TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (lab_custom_id) REFERENCES course_custom_labs(id) ON DELETE SET NULL,
    INDEX idx_module_sort (module_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) DEFAULT NULL,
    option_d VARCHAR(255) DEFAULT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    explanation TEXT NULL,
    FOREIGN KEY (item_id) REFERENCES course_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('active', 'completed') NOT NULL DEFAULT 'active',
    progress_percent INT NOT NULL DEFAULT 0,
    enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_course (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_course_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    item_id INT NOT NULL,
    status ENUM('started', 'completed') NOT NULL DEFAULT 'started',
    quiz_score INT NULL,
    quiz_attempts INT NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES course_items(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_item (user_id, item_id),
    INDEX idx_user_course_progress (user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
