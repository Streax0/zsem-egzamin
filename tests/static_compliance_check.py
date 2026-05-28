from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def assert_contains(path: str, *needles: str) -> None:
    content = read(path)
    missing = [needle for needle in needles if needle not in content]
    assert not missing, f"{path}: missing {missing}"


def test_cookie_consent_controls() -> None:
    assert_contains(
        "includes/cookie_consent.php",
        "Akceptuj wszystkie",
        "Odrzuć wszystkie",
        "Dostosuj",
        "cookie_consent_v2",
        "timestamp",
        "version",
        "categories",
    )


def test_tracking_scripts_not_loaded_without_consent() -> None:
    combined = "\n".join(path.read_text(encoding="utf-8", errors="ignore") for path in ROOT.rglob("*.php"))
    script_sources = re.findall(r"<script[^>]+src=[\"']([^\"']+)", combined, re.I)
    blocked = [src for src in script_sources if re.search(r"(googletagmanager|google-analytics|facebook|hotjar|clarity\.ms|tiktok)", src, re.I)]
    assert not blocked, f"tracking scripts loaded directly: {blocked}"


def test_footer_compliance_links() -> None:
    assert_contains(
        "includes/footer.php",
        "privacy.php",
        "polityka-cookies.php",
        "terms.php",
        "zglos-naruszenie.php",
        "dostepnosc.php",
        "data-cookie-settings",
    )


def test_abuse_report_form_exists() -> None:
    assert_contains(
        "zglos-naruszenie.php",
        "csrfTokenField('report_abuse')",
        "good_faith",
        "data/abuse_reports",
        "reporter_email",
    )


def test_accessibility_skip_link() -> None:
    assert_contains("includes/sidebar.php", "skip-link", "#main-content")
    assert_contains("assets/css/style.css", ".skip-link", ":focus-visible")


def test_images_have_alt_attributes() -> None:
    missing = []
    for path in ROOT.rglob("*.php"):
        lines = path.read_text(encoding="utf-8", errors="ignore").splitlines()
        for index, line in enumerate(lines):
            if "<img" not in line.lower():
                continue
            snippet = "\n".join(lines[index:index + 5])
            if not re.search(r"\salt\s*=", snippet, re.I):
                missing.append(f"{path.relative_to(ROOT)}:{index + 1}")
    assert not missing, "missing alt attributes: " + "; ".join(missing)


def test_admin_mobile_table_cards() -> None:
    assert_contains("admin.php", "admin-users-table-panel", 'data-label="Użytkownik"', 'data-label="Akcje"')
    assert_contains("admin.php", "admin-hero", "admin-kpi-card", "admin-tool-card", 'id="admin-ranks"')
    assert_contains("manage_questions.php", "questions-table-panel", 'data-label="Treść pytania"', "question-editor-modal")
    assert_contains("assets/css/style.css", ".admin-users-table-panel", ".questions-table-panel", "overflow-x: auto")


def test_admin_temporary_bans_and_safe_modals() -> None:
    assert_contains(
        "admin.php",
        "adminBanModal",
        "ban_duration",
        "data-admin-ban-user",
        "admin-action-grid",
        "admin-expiry-chip",
        "cleanupAdminModalArtifacts",
        "body.admin-page > .modal-backdrop",
    )
    assert "prompt(" not in read("admin.php")
    assert "confirm(" not in read("admin.php")
    assert_contains(
        "includes/auth.php",
        "clearExpiredBanForUser",
        "isBanActiveSql",
        "expires_at IS NULL OR expires_at > NOW()",
        "ban_expires_at > NOW()",
    )
    assert_contains("includes/functions.php", "ban_expires_at", "idx_ban_expiry")
    assert_contains("full_schema.sql", "ban_expires_at DATETIME DEFAULT NULL", "idx_ban_expiry")


def test_guest_navigation_and_sandbox_access() -> None:
    assert_contains("login.php", "actions/start_guest.php", "Tryb gościa")
    assert_contains("actions/start_guest.php", "startGuestSession()")
    assert_contains("sandbox.php", "requireLogin(true)")
    assert_contains("includes/sidebar.php", "$isGuestSidebar", "Wyjdź", "sandbox.php")
    assert_contains("includes/topbar.php", "$isGuestTopbar", "Wyjdź")
    assert_contains("includes/navbar.php", "Wyjdź")


def test_pdf_remaining_test_flow_and_modals() -> None:
    assert_contains(
        "test.php",
        "previous_question",
        'data-question-nav="previous"',
        "testConfirmModal",
        "testTimeExpiredModal",
        "submitFinishEarlyForm",
    )
    assert "confirm(" not in read("test.php")
    assert "alert(" not in read("test.php")
    assert "confirm(" not in read("includes/navbar.php")


def test_pdf_remaining_generator_groups_and_filters() -> None:
    assert_contains(
        "teacher/pdf_generator.php",
        "worksheetBuildGroups",
        "group_count",
        "group_strategy",
        "worksheet-group-label",
        "data-question-category",
        "questionImageSrc($question['image_url'], '../')",
    )


def test_pdf_remaining_profile_email_and_filters() -> None:
    assert_contains(
        "includes/functions.php",
        "normalizeProfanityText",
        "profanityVariants",
        "levenshtein",
        "'icloud.com'",
        "'proton.me'",
        "'mail.com'",
    )
    assert_contains("profile.php", "u.avatar_path", "comment-avatar-img", "userAvatarSrc($comment['avatar_path']")
    assert_contains("assets/js/register.js", "acceptedDomains", "icloud.com", "proton.me", "mail.com")
    assert "Dozwolone domeny:" not in read("assets/js/register.js")


def test_pdf_remaining_progress_and_performance() -> None:
    assert_contains("progress.php", "ensurePlatformEnhancements($pdo)", "progressNotice", "showProgressNotice")
    assert "alert(" not in read("progress.php")
    assert_contains("lessons.php", "lessonArchiveModal", "confirmArchiveLesson")
    assert "confirm(" not in read("lessons.php")
    assert_contains("assets/js/app-dialogs.js", "appConfirmSubmit", "appPrompt", "appNotice")
    assert_contains(
        "assets/js/performance-metrics.js",
        "performance.mark",
        "PerformanceObserver",
        "layout-shift",
        "durationThreshold",
    )
    assert_contains("includes/footer.php", "https://zsem.edu.pl/", "app-dialogs.js", "performance-metrics.js")


def test_no_native_dialogs_in_app_code() -> None:
    offenders = []
    for path in ROOT.rglob("*"):
        if path.is_dir() or path.suffix.lower() not in {".php", ".js"}:
            continue
        rel = path.relative_to(ROOT).as_posix()
        if rel.startswith(("tests/", "scratch/", "data_question/")):
            continue
        content = path.read_text(encoding="utf-8", errors="ignore")
        if re.search(r"\b(confirm|alert|prompt)\(", content):
            offenders.append(rel)
    assert not offenders, "native dialogs remain in app code: " + ", ".join(offenders)


def test_pdf_security_fixes() -> None:
    assert_contains("includes/functions.php", "sanitizeQuestionDataForStorage", "strip_tags", "sanitizeQuestionImageUrl")
    assert_contains("includes/functions.php", "isPrivateIpAddress", "isAllowedRemoteQuestionImageHost", "questionImageSrc")
    assert_contains("actions/logout.php", "validateCsrfToken", "'logout'", "$_SERVER['REQUEST_METHOD'] !== 'POST'")
    assert_contains("includes/topbar.php", "actions/logout.php", "method=\"POST\"", "csrfTokenField('logout')")
    assert_contains(".htaccess", "Strict-Transport-Security", "Content-Security-Policy")
    assert_contains("includes/session.php", "Strict-Transport-Security")
    assert_contains("includes/auth.php", "active_user_sessions", "registerCurrentUserSession", "validateCurrentUserSession")
    assert_contains("login.php", "session_expired", "registerCurrentUserSession")
    assert_contains("full_schema.sql", "active_user_sessions")
    assert "$_GET['id']" not in read("settings.php")


def test_duel_integrity_guards() -> None:
    assert_contains(
        "duels/finish.php",
        "COUNT(DISTINCT question_id)",
        "Odpowiedz na wszystkie pytania",
        "rowCount() === 1",
    )
    assert_contains("duels/save_answer.php", "Answer already saved", "Duel already finished")


def test_password_reset_and_mfa_exist() -> None:
    assert_contains("forgot_password.php", "createPasswordResetToken", "resetPasswordWithToken", "forgot_password")
    assert_contains("includes/auth.php", "totpCode", "verifyTotpCode", "session_version", "mfaAccessRequired")
    assert_contains("mfa.php", "getOrCreateMfaSecret", "enableMfaForUser", "recovery_code", "totpQrCode", "QRCode.toCanvas")
    assert_contains("full_schema.sql", "password_resets", "user_mfa", "rate_limit_events")


def test_missions_and_single_history_guards() -> None:
    assert_contains("includes/functions.php", "testResultQualifiesForMissions", "mode` <> 'single'", "completedFullTestSql($alias, 40, true)")
    assert_contains("includes/functions.php", "single_result_dedupe_", "DATE_SUB(NOW(), INTERVAL 10 SECOND)")


def test_exam_visibility_and_override() -> None:
    assert_contains("exam/finished.php", "results_available_at", "Wyniki będą dostępne od")
    assert_contains("teacher/view_participant_result.php", "applyExamCorrectAnswerOverride", "exam_answer_override", "Zmień i zgłoś")
    assert_contains("exam/take.php", "correct_answer_override", "questionImageSrc")


def test_sandbox_router_tools() -> None:
    assert_contains("sandbox.php", "'logic'", "'psu'", "'subnet'", "'numbers'", "'live'", "sandbox.php?tool=")
    assert_contains("sandbox.php", "izolowanym podglądzie")
    assert "filtrem niedozwolonych treści" not in read("sandbox.php")
    assert_contains("assets/js/sandbox.js", "containsProfanity", "XNOR", "ipv6Out", "U2 8-bit", "MAX_NODES = 80", "dragstart")


def test_profile_social_language_guards() -> None:
    assert_contains("profile.php", "youtube", "facebook", "select name=\"language_name\"", "profileCsrfToken")
    assert_contains("actions/profile_section.php", "allowedLanguages", "youtube.com", "facebook.com", "x.com", "gitlab.com")
    assert_contains("full_schema.sql", "'youtube'", "'facebook'", "'gitlab'")


def test_ranking_load_more_limit() -> None:
    assert_contains("ranking.php", "getTopRankings($pdo, 200)", "ranking-list-scroll", "rankingLoadMore", 'data-visible="50"')


def test_result_qualification_badges() -> None:
    assert_contains("result.php", "question_category", "qualification_label", "showAnswerQualifications", "modalQuestionMeta")


def test_dev_danger_files_removed() -> None:
    removed = [
        "seed_questions.php",
        "migrate_db.php",
        "migrate_bans.php",
        "migrate_custom_exams.php",
        "tmp_db_update.php",
        "fix_violations_db.php",
        "cookies.txt",
        "csrf.txt",
        "qid.txt",
        "response.json",
        "page.html",
    ]
    existing = [path for path in removed if (ROOT / path).exists()]
    assert not existing, f"dev/danger files still present: {existing}"


def test_host_exam_pdf_qr_and_participant_refresh() -> None:
    assert_contains(
        "teacher/pdf_generator.php",
        "id=\"worksheetPrintSource\"",
        "function printWorksheet()",
        "worksheetPrintCss",
        ".worksheet-options",
    )
    assert_contains("includes/session.php", "https://api.qrserver.com")
    assert_contains(".htaccess", "https://api.qrserver.com")
    assert_contains(
        "teacher/host_exam.php",
        "/exam/join.php?code=",
        "participantsList",
        "refreshParticipantsOnly",
        "INITIAL_PARTICIPANTS",
        "remove_participant",
    )
    assert_contains("ajax/get_session_status.php", "$scope = $_GET['scope'] ?? 'full'", "$scope !== 'participants'")
    assert_contains("includes/functions.php", "function getPublicQuestionCategories", "return getPublicCategories($pdo)")
    assert "join.php?session=" not in read("teacher/host_exam.php")


def test_join_qr_scanner_extracts_access_code() -> None:
    assert_contains(
        "exam/join.php",
        "jsQR",
        "extractAccessCode",
        "searchParams.get('code')",
        "Kod wpisany automatycznie",
    )
    assert "mobileScanAllowed" not in read("exam/join.php")


def test_director_role_permissions() -> None:
    assert_contains("full_schema.sql", "'dyrektor'")
    assert_contains(
        "includes/functions.php",
        "adminRoleValues",
        "teacherPanelRoleValues",
        "privilegedStaffRoles",
        "'dyrektor'",
        "roleHasAdminAccess",
    )
    assert_contains("includes/auth.php", "['admin', 'dyrektor', 'teacher']")
    assert_contains("admin.php", 'option value="dyrektor"', "privilegedStaffRoles()")
    assert_contains("includes/sidebar.php", "['admin', 'dyrektor']")
    assert_contains("luki_panel.php", "['admin', 'wujek_luki']")
    assert "['admin', 'dyrektor', 'wujek_luki']" not in read("luki_panel.php")


if __name__ == "__main__":
    for name, fn in sorted(globals().items()):
        if name.startswith("test_") and callable(fn):
            fn()
            print(f"OK {name}")
