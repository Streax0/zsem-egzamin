from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def assert_contains(path: str, *needles: str) -> None:
    content = read(path)
    missing = [needle for needle in needles if needle not in content]
    assert not missing, f"{path}: missing {missing}"


def extract_between(path: str, start: str, end: str) -> str:
    content = read(path)
    assert start in content, f"{path}: missing start marker {start!r}"
    after_start = content.split(start, 1)[1]
    assert end in after_start, f"{path}: missing end marker {end!r}"
    return after_start.split(end, 1)[0]


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


def test_page_category_blocks_admin_guard_and_schema() -> None:
    assert_contains(
        "includes/functions.php",
        "feature_page_blocks",
        "getFeaturePageBlockCategories",
        "createFeaturePageBlock",
        "endFeaturePageBlock",
        "resolveFeaturePageBlockForRequest",
        "enforceFeaturePageBlockForCurrentRequest",
        "renderFeaturePageBlockScreen",
        "sandbox_element_blocks",
        "getSandboxBlockableElements",
        "createSandboxElementBlock",
        "endSandboxElementBlock",
        "resolveSandboxElementBlock",
        "getSandboxElementBlockMapForRole",
    )
    assert_contains("includes/auth.php", "enforceFeaturePageBlockForCurrentRequest")
    assert_contains(
        "includes/topbar.php",
        "feature_block_notice",
        "pageBlockAdminNotice",
        "sandboxElementAdminNotice",
    )
    assert_contains(
        "admin.php",
        "create_feature_page_block",
        "end_feature_page_block",
        "create_sandbox_element_block",
        "end_sandbox_element_block",
        "admin-page-blocks",
        "admin-sandbox-blocks",
    )
    assert_contains(
        "sandbox.php",
        "getSandboxElementBlockMapForRole",
        "data-sandbox-element-key",
        "sandboxElementAdminNotice",
    )
    assert_contains("full_schema.sql", "feature_page_blocks", "sandbox_element_blocks")


def test_blocked_page_screen_is_single_safe_return_card() -> None:
    screen = extract_between(
        "includes/functions.php",
        "function renderFeaturePageBlockScreen",
        "function enforceFeaturePageBlockForCurrentRequest",
    )

    assert "featureBlockModal" not in screen
    assert "bootstrap.Modal" not in screen
    assert "actions/logout.php" not in screen
    assert "Wyloguj" not in screen
    assert "Ustawienia" not in screen
    assert "Kategoria wyłączona" in screen
    assert "Wyłączył" in screen
    assert "Powrót" in screen
    assert "featureBlockSafeReturnUrl" in read("includes/functions.php")
    assert "parse_url($referer" in read("includes/functions.php")
    assert "parse_url('http://' . $requestAuthority)" in read("includes/functions.php")


def test_sandbox_disabled_tools_and_logic_elements_render_server_side() -> None:
    sandbox = read("sandbox.php")
    disabled_tile = extract_between(
        "sandbox.php",
        "<?php if ($toolElementBlock): ?>",
        "<?php else: ?>",
    )

    assert "Uruchom narzędzie" not in disabled_tile
    assert "sandbox-arrow" not in disabled_tile
    assert "$sandboxBlockMetaList($toolElementBlock)" in disabled_tile
    assert "sandbox-tool-disabled-meta" in sandbox
    assert "Wyłączył" in sandbox
    assert "Role" in sandbox

    assert "$sandboxRenderLogicButton('logic.input_a'" in sandbox
    assert "$sandboxRenderLogicButton('logic.input_b'" in sandbox
    assert "$sandboxRenderLogicButton('logic.const_1'" in sandbox
    assert "$sandboxRenderLogicButton('logic.const_0'" in sandbox
    assert "$sandboxRenderLogicButton('logic.output_led'" in sandbox
    assert "$sandboxRenderLogicButton('logic.output_table'" in sandbox
    assert "$sandboxRenderLogicButton('logic.gate_' . strtolower($gate)" in sandbox
    assert '<button type="button" data-logic-input="A" draggable="true">' not in sandbox


def test_local_css_assets_are_versioned_sitewide_and_on_landing() -> None:
    assert_contains(
        "includes/session.php",
        "appVersionLocalStylesheetHrefs",
        "appVersionLocalStylesheetHref",
        "filemtime($absolute)",
        "assets/css/",
    )
    assert_contains(
        "landing.php",
        "require_once 'includes/functions.php'",
        "assetUrl('assets/css/landing.css')",
    )
    assert 'href="assets/css/landing.css"' not in read("landing.php")


def test_topbar_dropdown_animation_uses_css_not_display_hack() -> None:
    topbar = read("includes/topbar.php")
    css = read("assets/css/dashboard-new.css")
    assert "menu.style.display = 'none'" not in topbar
    assert "Double RAF" not in topbar
    assert "topbarDropdownIn" in css
    assert "transform-origin: top right" in css
    assert "will-change: opacity, transform" in css
    assert "body.reduce-motion .top-header .topbar-dropdown.show" in css


def test_settings_controls_call_real_preference_handlers() -> None:
    settings = read("settings.php")
    handler = read("assets/js/theme-handler.js")
    assert_contains(
        "settings.php",
        "updateDashboardViewSetting(this.value)",
        "updateDefaultTestModeSetting(this.value)",
        "updateNotifyActivitySetting(this.checked)",
        "updateUiSoundsSetting(this.checked)",
        "updateExternalNewTabSetting(this.checked)",
        "updateHelpCenterSetting(this.checked)",
    )
    assert_contains(
        "assets/js/theme-handler.js",
        "window.updateDashboardViewSetting",
        "window.updateDefaultTestModeSetting",
        "window.updateNotifyActivitySetting",
        "window.updateUiSoundsSetting",
        "window.updateExternalNewTabSetting",
        "window.updateHelpCenterSetting",
        "applyDashboardViewPreference();",
        "applyDefaultTestModePreference();",
        "applyHelpCenterPreference();",
        "applyExternalLinkPreference();",
    )
    assert "localStorage.setItem('notify_new_tests'" not in settings
    assert "localStorage.setItem('ui_sounds'" not in settings


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


def test_observatory_header_hardening() -> None:
    assert_contains(
        "includes/session.php",
        "appSecurityPermissionsPolicy",
        "appContentSecurityPolicyReportOnly",
        "Content-Security-Policy-Report-Only",
        "require-trusted-types-for 'script'",
        "style-src-elem 'self' 'nonce-{$nonce}'",
        "base-uri 'none'",
        "max-age=63072000; includeSubDomains; preload",
        "X-Frame-Options: SAMEORIGIN",
        "X-DNS-Prefetch-Control: off",
        "Origin-Agent-Cluster: ?1",
    )
    assert_contains(
        ".htaccess",
        "Header always set X-Frame-Options \"SAMEORIGIN\"",
        "Header always set X-DNS-Prefetch-Control \"off\"",
        "Header always set Origin-Agent-Cluster \"?1\"",
        "interest-cohort=()",
        "browsing-topics=()",
        "max-age=63072000; includeSubDomains; preload",
    )


def test_sidebar_topbar_animation_polish() -> None:
    assert_contains(
        "assets/css/dashboard-new.css",
        "sidebarItemEnter",
        "sidebarBrandPulse",
        "topbarIconGlow",
        "notificationBadgePulse",
        "topbarDropdownIn 0.18s cubic-bezier",
        "body.reduce-motion .top-header .topbar-dropdown.show",
        ".sidebar-overlay",
        "visibility: hidden",
        ".sidebar.show .sidebar-item",
        "@media (prefers-reduced-motion: reduce)",
    )
    assert_contains(
        "includes/topbar.php",
        "openSidebar",
        "closeSidebar",
        "sidebar.classList.add('is-opening')",
        "document.addEventListener('keydown'",
    )
    assert "event.key === 'Escape' && sidebar.classList.contains('show')" not in read("includes/topbar.php")


def test_luki_spin_ajax_without_page_refresh() -> None:
    assert_contains(
        "luki_panel.php",
        "function lukiWantsJson",
        "function lukiJsonResponse",
        "lukiSpinResponsePayload",
        "data-luki-spin-form",
        "data-luki-spin-alert",
        "data-spin-result-mount",
        "data-luki-xp",
        "data-spins-left",
        "fetch(form.action || 'luki_panel.php'",
        "'X-Requested-With': 'XMLHttpRequest'",
        "renderSpinResultCard",
        "history.prepend(entry)",
        "playSpinResult",
    )


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


def test_cke_mode_labels_and_no_ckz_copy() -> None:
    combined = "\n".join(
        path.read_text(encoding="utf-8", errors="ignore")
        for path in ROOT.rglob("*")
        if path.suffix.lower() in {".php", ".js", ".css"}
        and not path.relative_to(ROOT).as_posix().startswith(("tests/", "scratch/"))
    )
    assert "CKZ" not in combined and "ckz" not in combined.lower(), "CKZ copy remains in app code"
    assert_contains("includes/functions.php", "'exam_simulator' => 'Tryb CKE'")
    assert_contains("result.php", "'exam_simulator' => ['name' => 'Tryb CKE'")
    assert_contains("index.php", "'exam_simulator' => ['label' => 'Tryb CKE'")
    assert_contains("test.php", "Tryb testu CKE", "Tryb CKE")


def test_external_cdn_resources_have_sri() -> None:
    missing = []
    for path in ROOT.rglob("*.php"):
        rel = path.relative_to(ROOT).as_posix()
        content = path.read_text(encoding="utf-8", errors="ignore")
        for match in re.finditer(r"<(?:script|link)\b[^>]+https://[^>]+>", content, re.I):
            tag = match.group(0)
            if any(allowed in tag for allowed in ("fonts.googleapis.com", "fonts.gstatic.com", "api.qrserver.com")):
                continue
            if "preconnect" in tag or "workerSrc" in tag:
                continue
            if "integrity=" not in tag or "crossorigin=" not in tag:
                missing.append(f"{rel}: {tag[:140]}")
    assert not missing, "external CDN resources without SRI: " + "; ".join(missing)


def test_json_session_guards_cover_private_endpoints() -> None:
    guarded = [
        "actions/mark_read.php",
        "ajax/check_unranked.php",
        "ajax/duel_respond.php",
        "ajax/exam_action.php",
        "ajax/exam_status.php",
        "ajax/exam_violation.php",
        "ajax/extend_session.php",
        "ajax/get_session_status.php",
        "ajax/mark_mastered.php",
        "ajax/notifications_feed.php",
        "ajax/quiz_action.php",
        "ajax/search_users_live.php",
        "ajax/send_warning.php",
        "ajax/session_status.php",
        "ajax/teacher_status.php",
        "ajax/update_bio.php",
        "duels/finish.php",
        "duels/lobby.php",
        "duels/results.php",
        "duels/save_answer.php",
    ]
    for path in guarded:
        assert_contains(path, "requireJsonLogin(")


def test_admin_and_luki_expanded_operational_panels() -> None:
    assert_contains(
        "admin.php",
        "admin-ops-strip",
        "Konta bez weryfikacji",
        "Logowania 7 dni",
        "recent_logins",
        "adminOpsChecks",
    )
    assert_contains(
        "luki_panel.php",
        "Tydzień losu",
        "luki-risk-meter",
        "weeklySpinCount",
        "riskScore",
        "Ostatni spin",
    )


def test_release_teacher_generator_luki_v17_surface() -> None:
    assert_contains(
        "settings.php",
        "1.8 BETA",
        "settings-overview-grid",
        "settings-switch-grid",
        "settings-release-grid",
        "Prywatne loginy i szybsze powiadomienia",
    )
    assert_contains(
        "includes/topbar.php",
        "data-teacher-ops-strip",
        "teacher/pdf_generator.php",
        "teacher/custom_exams.php",
    )
    assert_contains(
        "assets/css/dashboard-new.css",
        ".teacher-ops-strip",
        "border-radius: 8px",
    )
    assert_contains(
        "teacher/pdf_generator.php",
        "pdf-generator-page",
        "data-generator-preset",
        "worksheetEstimate",
        "syncWorksheetEstimate",
        "body.dark-mode .worksheet-page .fw-bold",
        "body.dark-mode .generator-preset",
        "body.dark-mode.pdf-generator-page .form-control",
        "Próbny egzamin zawodowy",
    )
    assert_contains(
        "luki_panel.php",
        "'archetype' => 'forge'",
        "'archetype' => 'mirror'",
        "'archetype' => 'archive'",
        "Zakonnica Kuźni",
        "Zakonnica Lustra",
        "Zakonnica Archiwum",
    )


def test_pdf_final_cke_history_copy_and_launch_card() -> None:
    assert_contains(
        "includes/functions.php",
        "function normalizeHistoryMode",
        "'official_cke'",
        "'exam_simulator'",
    )
    assert_contains(
        "test.php",
        "Oficjalny tryb: 40 pytań",
        "Próg zdawalności",
        "Możesz wracać",
        "Zakończenie",
        "Włącz, aby rozwiązać",
        "exam-sim-launch-card",
        "linear-gradient(135deg, #102a6b",
    )
    assert "Wlacz" not in read("test.php")
    assert "Liczba pytan" not in read("test.php")
    assert "Prog zdawalnosci" not in read("test.php")
    assert "Mozesz" not in read("test.php")


def test_pdf_final_flashcards_surface_and_teacher_requests() -> None:
    assert_contains(
        "flashcards.php",
        "flashcard-request-form",
        "csrfTokenField('flashcard_request')",
        "createAdminRequest($pdo",
        "'flashcard_request'",
        "qualificationProgress",
        "data-flashcard-progress",
        "flashcard-shortcuts",
    )
    content = read("flashcards.php")
    assert "customKey" not in content
    assert "addCustomCard" not in content
    assert "Moje fiszki" not in content
    assert "Własna fiszka" not in content


def test_pdf_final_router_web_emulator() -> None:
    assert_contains(
        "sandbox.php",
        "router-web-emulator",
        "ZSEM RouterOS",
        "MAC Clone",
        "WAN",
        "LAN",
        "DHCP",
        "Wireless",
    )
    assert_contains(
        "assets/js/sandbox.js",
        "initRouterWebEmulator",
        "routerWanMac",
        "routerDhcpToggle",
        "routerSaveConfig",
    )


def test_pdf_final_explanations_settings_teacher_avatar() -> None:
    assert_contains(
        "includes/functions.php",
        "function normalizeHistoryMode",
        "function buildDistractorExplanation",
        "Dlaczego nie reszta?",
        "upperSkinRatio",
        "centerSkinRatio",
    )
    assert_contains(
        "settings.php",
        "data-settings-overview",
        "syncSettingsOverviewCards",
        "syncSettingsMiniCards",
    )
    assert_contains(
        "assets/css/dashboard-new.css",
        ".teacher-ops-strip a.active",
        "teacher-ops-strip-current",
        "overflow-x: auto",
    )


def test_v18_registration_password_autologin_and_suggestions() -> None:
    assert_contains(
        "register.php",
        "registrationGeneratedUsername($pdo, $first_name, $last_name, $classParts)",
        "registerCurrentUserSession($pdo, (int)$newUserId)",
        "header('Location: index.php')",
    )
    assert_contains(
        "includes/functions.php",
        "function registrationUsernameSlug",
        "function registrationGeneratedUsername",
        "imie-inicjal-klasa-numer",
        "$lastInitial",
    )
    register_content = read("register.php")
    assert "Puste = imię.nazwisko" not in register_content
    assert "registrationUsernameBase($first_name, $last_name)" not in register_content
    assert "'user_' . bin2hex(secureRandomBytes(4))" not in register_content
    assert "Puste = losowy prywatny login" not in register_content
    assert_contains(
        "ajax/check_registration_availability.php",
        "registrationUsernameSuggestions",
        "'suggestions'",
    )
    assert_contains(
        "assets/js/register.js",
        "previewGeneratedUsername",
        "generatedUsernamePreview",
        "renderUsernameSuggestions",
        "suggestions.forEach",
        "Znak specjalny zwiększa siłę hasła, ale nie jest wymagany.",
    )
    assert "Hasło musi zawierać znak specjalny." not in read("includes/functions.php")


def test_v19_settings_preferences_are_effective() -> None:
    assert_contains(
        "settings.php",
        "settings-active-preferences",
        "data-preference-status",
        "testPreferenceFeedback",
        "Alerty o aktywnościach",
    )
    assert_contains(
        "assets/js/theme-handler.js",
        "applyDashboardViewPreference",
        "applyExternalLinkPreference",
        "playUiPreferenceChime",
        "window.testPreferenceFeedback",
        "dashboard-view-learning",
        "dashboard-view-compact",
        "notify_new_tests",
        "ui_sounds",
    )
    assert_contains(
        "assets/js/notifications-poll.js",
        "window.zsemNotifyUnreadCountChanged",
        "previousUnreadCount",
    )
    assert_contains(
        "assets/css/dashboard-new.css",
        "body.dashboard-view-learning",
        "body.dashboard-view-compact",
        ".settings-active-preferences",
    )


def test_v18_pdf_remaining_auth_and_performance_surface() -> None:
    assert_contains(
        "exam/join.php",
        "join-hero-kicker",
        "data-join-code-card",
        "Lobby i start bez odświeżania",
    )
    assert_contains(
        "teacher/pdf_generator.php",
        "manual-q-textarea",
        "manual-q-explanation",
        "data-manual-category",
        "manualQuestionLimit",
    )
    assert_contains(
        "assets/js/result-share-card.js",
        "Tryb CKE",
        "data.modeName",
    )
    assert_contains(
        "result.php",
        "data-answer-analysis",
        "data-answer-toggle",
        "data-distractors-toggle",
        "shown.bs.collapse",
    )
    assert_contains(
        "assets/js/notifications-poll.js",
        "baseIntervalMs",
        "failureCount",
        "refreshOnOpen",
        "document.hidden",
    )


def test_v18_router_flashcards_social_license() -> None:
    assert_contains(
        "sandbox.php",
        "TP-LINK",
        "AC750 Wireless Dual Band Gigabit Router",
        "Model No. Archer C2",
        "routerMacCloneHelp",
    )
    assert_contains(
        "assets/js/sandbox.js",
        "zsem.router.config.v1",
        "routerFactoryMac",
        "routerCloneMac",
        "pagehide",
    )
    assert_contains(
        "flashcards.php",
        "flashcard-qualification-grid",
        "data-flashcard-load-more",
        "flashcard-study-builder",
        "data-flashcard-difficulty",
        "Powtórka błędnych pojęć",
    )
    assert_contains(
        "social.php",
        "social-insights-grid",
        "suggested-users-card",
        "social-activity-card",
    )
    assert_contains(
        "LICENSE",
        "non-profit",
        "niekomercyjnych",
        "prawami autorskimi",
    )


if __name__ == "__main__":
    for name, fn in sorted(globals().items()):
        if name.startswith("test_") and callable(fn):
            fn()
            print(f"OK {name}")
