from pathlib import Path
import re
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def assert_contains(path: str, *needles: str) -> None:
    content = read(path)
    missing = [needle for needle in needles if needle not in content]
    assert not missing, f"{path}: missing {missing}"


def assert_not_contains(path: str, *needles: str) -> None:
    content = read(path)
    present = [needle for needle in needles if needle in content]
    assert not present, f"{path}: unexpected {present}"


def read_tree(*dirs: str) -> str:
    chunks = []
    for dirname in dirs:
        for path in (ROOT / dirname).rglob("*"):
            if path.suffix.lower() in {".php", ".js"}:
                chunks.append(path.read_text(encoding="utf-8", errors="ignore"))
    return "\n".join(chunks)


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
    assert_contains("admin_requests.php", "admin-requests-table-panel", 'data-label="Użytkownik"', 'data-label="Akcje"', "admin-requests-actions")
    assert_contains("assets/css/style.css", ".admin-users-table-panel", ".questions-table-panel", ".admin-requests-table-panel", "overflow-x: auto")
    assert_contains("index.php", "recent-tests-table-wrap", "recent-tests-table")
    assert_not_contains("assets/css/dashboard-new.css", ".dashboard-panel table.table tbody tr")


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
        "appVersionLocalScriptSrcs",
        "appVersionLocalScriptHref",
        "filemtime($absolute)",
        "assets/css/",
        "assets/js/",
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
    assert "will-change: opacity, clip-path" in css
    assert "clip-path: inset(0 0 12px 0 round 16px)" in css
    assert "transform: translate3d(0, -6px, 0) scale(0.985)" not in css
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
    )
    assert_contains(
        "assets/js/theme-handler.js",
        "window.updateDashboardViewSetting",
        "window.updateDefaultTestModeSetting",
        "window.updateNotifyActivitySetting",
        "window.updateUiSoundsSetting",
        "window.updateExternalNewTabSetting",
        "applyDashboardViewPreference();",
        "applyDefaultTestModePreference();",
        "applyExternalLinkPreference();",
    )
    assert "localStorage.setItem('notify_new_tests'" not in settings
    assert "localStorage.setItem('ui_sounds'" not in settings


def test_guest_navigation_and_sandbox_access() -> None:
    assert_contains("login.php", "actions/start_guest.php", "Tryb gościa")
    assert_contains("actions/start_guest.php", "securityValidateRequestCsrf('guest_start')", "securityConsumeRateLimit('guest:start:'", "startGuestSession()")
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


def test_tests_update_answer_check_surface() -> None:
    assert_contains(
        "test.php",
        "check_answer",
        "'answer_check_limit' => $mode === 'single' ? 0 : 3",
        "answer_check_used",
        "$test['phase'] ?? 'answering'",
        "'smart'      => $smart",
        "'smart' => $smart",
        "filterQuestionPoolForTest($pdo, $allQuestions",
        "selectQuestionsForTest($pdo, $pool",
        "prepareNextSingleQuestion($pdo, $test",
        "answer-check-counter",
        "Sprawdzenia:",
        "Sprawdź odpowiedź",
        "prefers-reduced-motion",
    )
    assert_contains(
        "ajax/quiz_action.php",
        "case 'check_answer'",
        "applyTestAnswerCheck",
        "prepareNextSingleQuestion",
        "testAnswerCheckPayload",
        "Question already reviewed",
    )
    assert_contains(
        "assets/js/quiz-engine.js",
        "checkAnswer()",
        "check_answer",
        "syncAnswerCheckControls",
        "Sprawdź odpowiedź",
        "cardBody.querySelectorAll('.review-box, .review-next-actions')",
        "is-updated",
    )
    assert_contains(
        "includes/functions.php",
        "function applyTestAnswerCheck",
        "function prepareNextSingleQuestion",
        "function normalizeTestCategoryFilter",
        "function getQuestionDifficultyBucket",
        "function filterQuestionPoolForTest",
        "function selectQuestionsForTest",
        "function testAnswerCheckPayload",
        "revealed_by_check",
        "['exam', 'practice']",
        "$test['phase'] ?? 'answering'",
        "To pytanie jest juz w trybie podgladu.",
        "answer_check_attempt_number",
        "answer_check_used_at",
        "$previousQuestionId",
        "$difficulty = (string)($config['difficulty'] ?? 'all')",
        "$scope = (string)($config['scope'] ?? 'all')",
    )
    assert_not_contains("settings.php", "BIG TEST UPDATE")
    assert_contains("settings.php", "TESTS UPDATE", "settings-release-grid")
    teacher_exam_surface = read_tree("teacher", "exam")
    assert "check_answer" not in teacher_exam_surface
    assert "Sprawdź odpowiedź" not in teacher_exam_surface
    assert "answer_check_limit" not in teacher_exam_surface


def test_security_layer_and_quiz_api_client() -> None:
    for path in [
        "Security/bootstrap.php",
        "Security/RequestContext.php",
        "Security/Input.php",
        "Security/CsrfGuard.php",
        "Security/RateLimiter.php",
        "Security/JsonResponse.php",
        "Security/Audit.php",
        "Security/Headers.php",
        "Security/Redirect.php",
    ]:
        assert (ROOT / path).is_file(), f"{path} missing"

    assert_contains(
        "includes/session.php",
        "Security/bootstrap.php",
        "securityApplyResponseHeaders",
        "securityValidateRequestCsrf",
        "securityJsonEncode",
    )
    assert_contains(
        "Security/Headers.php",
        "X-Content-Type-Options: nosniff",
        "X-Frame-Options: SAMEORIGIN",
        "Referrer-Policy: strict-origin-when-cross-origin",
        "appSecurityPermissionsPolicy",
        "camera=(self), microphone=(), geolocation=(), payment=()",
    )
    assert_contains(
        "includes/session.php",
        "appSecurityPermissionsPolicy",
        "camera=(self)",
    )
    assert_not_contains("Security/Headers.php", "camera=()")
    assert_not_contains("includes/session.php", "camera=()")
    assert "Content-Security-Policy" not in read("Security/Headers.php")
    assert_contains(
        "Security/JsonResponse.php",
        "array_key_exists('success', $payload)",
        "array_key_exists('ok', $payload)",
        "$payload['success'] = (bool)$payload['ok']",
        "$payload['ok'] = (bool)$payload['success']",
        "return array_merge($payload, securityJsonMeta())",
        '"success":false,"ok":false',
    )
    assert_contains(
        "ajax/quiz_action.php",
        "securityApplyJsonHeaders",
        "securityInputEnum",
        "securityInputAnswerLetter",
        "securityConsumeRateLimit",
        "securityJsonEncode",
    )
    assert_contains(
        "ajax/exam_action.php",
        "securityApplyJsonHeaders",
        "securityInputEnum",
        "securityInputAnswerLetter",
        "securityConsumeRateLimit",
        "securityJsonEncode",
    )
    assert_contains(
        "exam/take.php",
        "securityInputInt($_GET['session']",
        "securityValidateRequestCsrf",
        "securityInputAnswerLetter",
        "securityConsumeRateLimit",
    )
    assert_contains(
        "includes/auth.php",
        "securityJsonEncode",
        "requireJsonLogin",
    )
    assert_contains(
        "test.php",
        "assets/js/api-client.js",
        "securityValidateRequestCsrf",
        "securityConsumeRateLimit",
    )
    assert_contains(
        "assets/js/api-client.js",
        "function normalizeResponseShape",
        "const TIMEOUT_MESSAGE",
        "data.success = Boolean(data.ok)",
        "data.ok = Boolean(data.success)",
        "data.error = data.message",
        "data.message = data.error",
        "'Accept': 'application/json'",
        "credentials: 'same-origin'",
        "cache: 'no-store'",
        "X-Client-Request-ID",
        "AbortController",
        "postForm",
        "getJson",
        "urlEncoded",
    )
    assert_contains(
        "assets/js/quiz-engine.js",
        "postQuizAction",
        "window.AppApi?.postForm",
    )
    assert_contains(
        "includes/footer.php",
        "assets/js/api-client.js",
        "window.AppApi?.getJson",
        "window.AppApi?.postForm",
        "prefers-reduced-motion: reduce",
        "document.hidden",
        "stopCtaTimer",
    )
    assert_contains(
        "register.php",
        "assets/js/api-client.js",
        "assets/js/register.js",
    )
    assert_contains(
        "assets/js/register.js",
        "window.AppApi?.getJson",
        "check_registration_availability.php",
    )
    for path in [
        "ajax/session_status.php",
        "ajax/extend_session.php",
        "ajax/notifications_feed.php",
        "ajax/search_users_live.php",
        "ajax/check_registration_availability.php",
        "ajax/check_user.php",
        "ajax/mark_mastered.php",
        "ajax/update_bio.php",
        "ajax/exam_violation.php",
        "ajax/send_warning.php",
        "ajax/check_unranked.php",
        "ajax/exam_status.php",
        "ajax/get_session_status.php",
        "ajax/teacher_status.php",
        "ajax/duel_respond.php",
    ]:
        assert_contains(path, "securityApplyJsonHeaders", "securityJsonEncode")
    assert_contains("Security/Endpoint.php", "function securityRequireMethod", "function securityThrottle")
    assert_contains("Security/bootstrap.php", "Endpoint.php", "Redirect.php")
    assert_contains("Security/Input.php", "function securityJsonBody")
    assert_contains("Security/CsrfGuard.php", "securityJsonBody")
    assert_contains(
        "Security/Redirect.php",
        "function securityFallbackRedirectTarget",
        "function securityLocalRedirectTarget",
        "function securityReferrerRedirectTarget",
        "function securityRedirect",
        "$safeFallback = securityFallbackRedirectTarget($fallback)",
        "isset($parts['scheme'])",
        "isset($parts['host'])",
    )
    assert_contains(
        "includes/functions.php",
        "securityLocalRedirectTarget((string)$url, 'index.php')",
    )
    assert_contains(
        "exam/take.php",
        "../assets/js/api-client.js",
        "../assets/js/exam-engine.js",
    )
    assert_contains(
        "assets/js/exam-engine.js",
        "postExamAction",
        "window.AppApi?.postForm",
    )
    assert_contains(
        "assets/js/notifications-poll.js",
        "window.AppApi?.postForm",
        "window.AppApi?.getJson",
    )
    assert_contains(
        "includes/topbar.php",
        "assets/js/api-client.js",
        "assets/js/notifications-poll.js",
        "$appApiClientLoaded = true",
        "window.AppApi.postForm(readUrl",
    )
    topbar = read("includes/topbar.php")
    api_script_lines = [line for line in topbar.splitlines() if "<script" in line and "assets/js/api-client.js" in line]
    assert len(api_script_lines) == 1
    assert topbar.index("assets/js/api-client.js") < topbar.index("roleDecisionLayer")
    assert_contains(
        "includes/footer.php",
        "empty($appApiClientLoaded)",
        "$appApiClientLoaded = true",
    )
    assert_contains(
        "actions/mark_read.php",
        "securityValidateRequestCsrf('notifications')",
        "securityInputInt($_POST['notification_id']",
        "securityThrottle(",
        "securitySendJson",
    )
    for path, csrf_action, rate_bucket in [
        ("actions/delete_notification.php", "securityValidateRequestCsrf()", "notifications:delete:"),
        ("actions/delete_test_result.php", "securityValidateRequestCsrf('delete_test_result')", "history:delete_test_result:"),
        ("actions/delete_duel_history.php", "securityValidateRequestCsrf('delete_duel_history')", "history:delete_duel:"),
    ]:
        assert_contains(path, csrf_action, "securityInputInt($_POST", "securityConsumeRateLimit", rate_bucket)
    assert_contains(
        "actions/delete_test_result.php",
        "securityLocalRedirectTarget(",
        "#^(?:\\.\\./)?(?:history|profile)\\.php(?:\\?id=\\d+)?$#",
    )
    assert_contains(
        "actions/delete_duel_history.php",
        "securityLocalRedirectTarget(",
        "#^(?:\\.\\./)?(?:history|profile)\\.php(?:\\?id=\\d+)?$#",
    )
    assert_contains(
        "actions/mark_read.php",
        "securityRedirect(securityReferrerRedirectTarget('../index.php'), '../index.php')",
    )
    assert "HTTP_REFERER" not in read("actions/mark_read.php")
    assert_contains(
        "actions/send_friend_request.php",
        "securityValidateRequestCsrf()",
        "securityInputInt($_POST['friend_id']",
        "securityConsumeRateLimit('social:send_friend_request:'",
        "securityReferrerRedirectTarget('../social.php')",
    )
    assert "HTTP_REFERER" not in read("actions/send_friend_request.php")
    assert_contains(
        "actions/handle_friend_request.php",
        "securityValidateRequestCsrf()",
        "securityInputInt($_POST['user_id']",
        "securityInputEnum($_POST['action']",
        "securityConsumeRateLimit('social:handle_friend_request:'",
        "securityRedirect('../social.php', '../social.php')",
    )
    assert_contains(
        "actions/change_password.php",
        "securityReferrerRedirectTarget('../settings.php')",
        "securityLocalRedirectTarget('../' . $_POST['return_to']",
        "securityValidateRequestCsrf()",
        "securityConsumeRateLimit('auth:change_password:'",
        "securityRedirect($returnTo, '../settings.php')",
    )
    for path in ["actions/send_friend_request.php", "actions/handle_friend_request.php", "actions/change_password.php"]:
        assert "validateCsrfToken($_POST['csrf_token']" not in read(path)
    for path, csrf_action, rate_bucket in [
        ("actions/update_privacy.php", "securityValidateRequestCsrf()", "settings:update_privacy:"),
        ("actions/logout_all_sessions.php", "securityValidateRequestCsrf('logout_all')", "auth:logout_all:"),
        ("actions/reset_progress.php", "securityValidateRequestCsrf()", "settings:reset_progress:"),
        ("actions/delete_account.php", "securityValidateRequestCsrf()", "settings:delete_account:"),
    ]:
        assert_contains(path, csrf_action, "securityConsumeRateLimit", rate_bucket, "securityRedirect")
        assert "validateCsrfToken($_POST['csrf_token']" not in read(path)
    assert_contains(
        "actions/update_profile.php",
        "securityValidateRequestCsrf()",
        "securityConsumeRateLimit('profile:' . $profileAction",
        "securityRedirect($returnTarget, '../settings.php')",
    )
    assert "validateCsrfToken($_POST['csrf_token']" not in read("actions/update_profile.php")
    assert "header('Location: '" not in read("actions/update_profile.php")
    assert_contains("actions/start_guest.php", "securityRedirect($url, '../landing.php')")
    assert_contains(
        "ajax/get_test_details.php",
        "securityApplyResponseHeaders",
        "securityConsumeRateLimit",
        "getQuestionsByIds($pdo, array_values(array_unique($missingQuestionIds)))",
    )
    assert "loadQuestions($pdo)" not in read("ajax/get_test_details.php")


def test_json_endpoints_use_security_response_helpers() -> None:
    checked = list((ROOT / "ajax").glob("*.php")) + [ROOT / "actions" / "mark_read.php"]
    assert checked, "no JSON endpoints checked"
    offenders = []
    for path in checked:
        content = path.read_text(encoding="utf-8", errors="ignore")
        if "echo json_encode" in content or "Content-Type: application/json" in content:
            offenders.append(str(path.relative_to(ROOT)))
    assert not offenders, "manual JSON responses remain: " + ", ".join(offenders)


def test_backend_performance_indexes_for_tests_and_exams() -> None:
    needles = [
        "idx_user_mode_date",
        "idx_question_correct",
        "idx_result_question",
        "idx_user_mastered_last",
        "idx_question_mastered",
        "idx_session_order",
        "idx_session_user_status",
        "idx_session_status_joined",
        "idx_session_participant",
        "idx_participant_order",
        "idx_updated_at",
    ]
    assert_contains("includes/functions.php", *needles)
    assert_contains("full_schema.sql", *needles)
    assert_contains(
        "includes/functions.php",
        "function getUserQuestionProgressMap",
        "array_chunk($ids, 500)",
        "question_id IN ($placeholders)",
        "foreach (loadQuestions($pdo, false) as $question)",
        "function cleanupStaleActiveTests",
        "max(1, min(90, $maxAgeDays))",
        "DELETE FROM user_active_tests WHERE updated_at < ?",
        "cleanupStaleActiveTests($pdo)",
    )
    assert_contains(
        "test.php",
        "filterQuestionPoolForTest($pdo, $allQuestions",
        "selectQuestionsForTest($pdo, $pool",
    )
    assert_contains(
        "includes/functions.php",
        "getUserQuestionProgressMap($pdo, $userId, $poolQuestionIds)",
        "function filterQuestionPoolForTest",
    )
    assert "SELECT question_id, times_seen, times_correct FROM user_question_progress WHERE user_id = ?" not in read("test.php")


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
        "Header setifempty Content-Security-Policy \"default-src 'none'",
        "script-src 'self' 'unsafe-inline' blob: https://cdn.jsdelivr.net",
        "object-src 'none'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'",
        "Header always set X-Frame-Options \"SAMEORIGIN\"",
        "Header always set X-DNS-Prefetch-Control \"off\"",
        "Header always set Origin-Agent-Cluster \"?1\"",
        "interest-cohort=()",
        "browsing-topics=()",
        "max-age=63072000; includeSubDomains; preload",
    )
    assert 'header("Content-Security-Policy: " . appContentSecurityPolicy($cspNonce));' in read("includes/session.php")


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
    assert_contains("includes/auth.php", "totpCode", "verifyTotpCode", "session_version", "mfaAccessRequired", "return $role === 'admin'")
    assert_contains("includes/functions.php", "notifyOptionalMfaForRole", "mfa_optional_prompt", "Czy włączyć 2 etapowe uwierzytelnianie?")
    assert_contains("admin.php", "notifyOptionalMfaForRole($pdo, $userId, $role)")
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
    assert_contains("ajax/get_session_status.php", "securityInputEnum($_GET['scope'] ?? 'full'", "$scope !== 'participants'")
    assert_contains("includes/functions.php", "function getPublicQuestionCategories", "return getPublicCategories($pdo)")
    assert "join.php?session=" not in read("teacher/host_exam.php")


def test_join_qr_scanner_extracts_access_code() -> None:
    assert_contains(
        "exam/join.php",
        "jsQR",
        "getUserMedia",
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
    assert "Tryb CKE" not in combined, "old CKE mode label remains in app code"
    assert_contains("includes/functions.php", "'exam' => 'Test'", "'exam_simulator' => 'Egzamin'")
    assert_contains("result.php", "'exam' => ['name' => 'Test'", "'exam_simulator' => ['name' => 'Egzamin'")
    assert_contains("index.php", "'exam' => ['label' => 'Test'", "'exam_simulator' => ['label' => 'Egzamin'")
    assert_contains("test.php", ">Egzamin")
    assert "Egzamin - symulator CKE" not in read("test.php")


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
        "1.9.1 HOTFIX",
        "settings-overview-grid",
        "settings-switch-grid",
        "settings-release-grid",
        "Changelog 1.9.1 Hotfix",
        "Płynniejsze menu powiadomień i profilu",
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
        "worksheet-student-header",
        "worksheet-question-number",
        "worksheet-points-total",
        "worksheetTotalPoints",
        "background-size:18px 18px",
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
        "assets/css/flashcards.css",
        "assets/js/flashcards.js",
        "flashcard-request-form",
        "csrfTokenField('flashcard_request')",
        "createAdminRequest($pdo",
        "'flashcard_request'",
        "data-flashcard-progress",
        "flashcard-shortcuts",
    )
    assert_contains(
        "assets/js/flashcards.js",
        "function qualificationProgress",
        "window.zsemFlashcards",
        "zsem.flashcards.progress.v2",
        "data-flashcard-load-more",
    )
    content = read("flashcards.php")
    assert "customKey" not in content
    assert "addCustomCard" not in content
    assert "Moje fiszki" not in content
    assert "Własna fiszka" not in content


def test_pdf_final_router_web_emulator() -> None:
    assert_contains(
        "sandbox.php",
        "network-lab-embed",
        "network-lab-frame",
        "sandbox_network_lab.php",
        "Laboratorium sieci INF.02",
    )
    assert_contains(
        "assets/js/network-lab.js",
        "PDF_URLS",
        "runVerify",
        "requestResetAll",
        "router-model-sel",
        "TL-SG108E",
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
    assert_contains(
        "actions/update_profile.php",
        "AVATAR_MAX_BYTES = 25600",
        "saveAvatarWebpWithinLimit",
        "filesize($dest) <= AVATAR_MAX_BYTES",
        "25 KB",
    )
    assert_contains("settings.php", "25 KB")
    assert_contains("profile.php", "25 KB")


def test_v18_registration_password_autologin_and_suggestions() -> None:
    assert_contains(
        "register.php",
        "Nazwa użytkownika jest wymagana.",
        "registrationUsernameSuggestions($pdo, $username, 3)",
        "registerCurrentUserSession($pdo, (int)$newUserId)",
        "header('Location: index.php')",
    )
    assert_contains(
        "includes/functions.php",
        "function registrationUsernameSlug",
        "function registrationUsernameSuggestions",
    )
    assert "function registrationGeneratedUsername" not in read("includes/functions.php")
    register_content = read("register.php")
    assert "registrationGeneratedUsername($pdo" not in register_content
    assert "Puste = imię.nazwisko" not in register_content
    assert "registrationUsernameBase($first_name, $last_name)" not in register_content
    assert "'user_' . bin2hex(secureRandomBytes(4))" not in register_content
    assert "Puste = losowy prywatny login" not in register_content
    assert "Puste pole = login wygenerowany automatycznie" not in register_content
    assert_contains(
        "ajax/check_registration_availability.php",
        "registrationUsernameSuggestions($pdo, $value, 3)",
        "'suggestions'",
    )
    assert_contains(
        "assets/js/register.js",
        "previewGeneratedUsername",
        "generatedUsernamePreview",
        "renderUsernameSuggestions",
        "suggestions.forEach",
        "[data-password-toggle]",
        "Ukryj hasło",
        "Nazwa użytkownika jest wymagana.",
        "Znak specjalny zwiększa siłę hasła, ale nie jest wymagany.",
    )
    assert_contains("register.php", 'data-password-toggle="regPassword"', 'data-password-toggle="confirm_password"', "generatedUsernamePreview")
    assert "Wpisz nick. Jeśli jest zajęty, pokażemy wolne propozycje." not in register_content
    assert "Wpisz nick. Jeśli jest zajęty, pokażemy wolne propozycje." not in read("assets/js/register.js")
    assert "Hasło musi zawierać znak specjalny." not in read("includes/functions.php")


def test_fact_based_question_explanations_and_stats_labels() -> None:
    for rel in ("includes/functions.php", "result.php", "assets/js/quiz-engine.js"):
        content = read(rel)
        assert "nie spełnia bezpośrednio warunku z pytania" not in content
        assert "opisuje inną warstwę działania" not in content
        assert "nie spełnia głównego warunku pytania" not in content
    assert_contains("includes/functions.php", "return '';", "Poprawna odpowiedź:", "Wybrano:")
    assert_contains("result.php", "$showAnswerQualifications = true", "qualification_label", "Poprawna odpowiedź:")
    assert_contains("progress.php", "buildQuestionExplanation($questionForExplanation)", "$questionExplanation")


def test_admin_audit_is_capped_and_lazy_loaded() -> None:
    assert_contains(
        "includes/functions.php",
        "LIMIT 50) keep_rows",
        "max(1, min(50, $limit))",
    )
    assert_contains(
        "admin.php",
        "$auditInitialLimit = 20",
        "data-admin-audit-row",
        "adminAuditLoadMore",
        "Załaduj więcej logów",
    )


def test_refresh_animation_reduced_and_settings_spacing_fixed() -> None:
    assert_contains("assets/css/dashboard-new.css", "animation: none", ".settings-active-preferences")
    assert_contains("assets/css/style.css", ".animate-in", "animation: none")
    assert_contains("settings.php", "settings-side-stack", "grid-template-columns: repeat(auto-fit, minmax(210px, 1fr))")


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
        "Egzamin",
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
        "network-lab-embed",
        "sandbox_network_lab.php",
        "Laboratorium sieci INF.02",
    )
    assert_contains(
        "sandbox_network_lab.php",
        "requireLogin(true)",
        "assets/css/network-lab.css",
        "assets/js/network-lab.js",
    )
    assert_contains(
        "flashcards.php",
        "assets/css/flashcards.css",
        "assets/js/flashcards.js",
        "flashcard-qualification-grid",
        "data-flashcard-load-more",
        "flashcard-study-builder",
        "data-flashcard-difficulty",
        "Powtórka błędnych pojęć",
    )
    assert_contains(
        "social.php",
        "social-insights-main",
        "Right: Invites",
        "social-insights-grid",
        "suggested-users-card",
        "social-activity-card",
    )
    assert_contains(
        "assets/css/style.css",
        ".exam-setup-compact-col .time-slider-panel.open",
        'input[type="range"].custom-range-slider',
    )
    assert_contains("test.php", 'class="custom-range-slider"', "time-slider-panel")
    assert_contains(
        "LICENSE",
        "non-profit",
        "niekomercyjnych",
        "prawami autorskimi",
    )


def test_network_lab_inf02_scope_and_local_assets() -> None:
    assert_contains(
        "sandbox_network_lab.php",
        'id="exam-sel"',
        'value="2025-cze"',
        'value="2024-cze"',
        'value="2024-sty"',
        'value="2023-cze"',
        'value="2023-sty"',
        'value="2022-cze"',
        'value="2022-sty"',
        'value="2021-cze"',
        'value="cke"',
        'id="tab-router"',
        'id="tab-switch"',
        'id="router-model-sel"',
        'value="cisco"',
        "Cisco RV132W",
        'value="tplink"',
        "TP-Link TL-WR841ND",
        'value="mikrotik-wb"',
        "MikroTik (WinBox)",
        'value="mikrotik-wf"',
        "MikroTik (WebFig)",
        'id="btn-reset"',
        'id="btn-verify"',
        'id="modal"',
        'rel="noopener noreferrer"',
    )
    assert_contains(
        "assets/js/network-lab.js",
        "'2025-cze': 'sandbox_network_pdf.php?session=2025-cze'",
        "'2024-cze': 'sandbox_network_pdf.php?session=2024-cze'",
        "'2024-sty': 'sandbox_network_pdf.php?session=2024-sty'",
        "'2023-cze': 'sandbox_network_pdf.php?session=2023-cze'",
        "'2023-sty': 'sandbox_network_pdf.php?session=2023-sty'",
        "'2022-cze': 'sandbox_network_pdf.php?session=2022-cze'",
        "'2022-sty': 'sandbox_network_pdf.php?session=2022-sty'",
        "'2021-cze': 'sandbox_network_pdf.php?session=2021-cze'",
        "'cke': 'sandbox_network_pdf.php?session=cke'",
        "TL-SG108E",
        "runVerify",
        "resetAll",
    )
    assert "data/pdfs/" not in read("assets/js/network-lab.js")
    lab = read("sandbox_network_lab.php") + read("assets/js/network-lab.js")
    assert "firebase" not in lab.lower()
    assert "auth.js" not in lab
    assert "zawod-header" not in lab
    assert "site-header" not in read("sandbox_network_lab.php")
    for name in [
        "inf02_2025_cze.pdf",
        "inf02_2024_cze.pdf",
        "inf02_2024_sty.pdf",
        "inf02_2023_cze.pdf",
        "inf02_2023_sty.pdf",
        "inf02_2022_cze.pdf",
        "inf02_2022_sty.pdf",
        "inf02_2021_cze.pdf",
        "inf02_cke_2026.pdf",
    ]:
        pdf = ROOT / "data" / "pdfs" / name
        assert pdf.is_file() and pdf.stat().st_size > 0, f"missing local PDF: {name}"


def test_network_lab_mikrotik_menu_items_have_renderers() -> None:
    js = read("assets/js/network-lab.js")
    menu_start = js.index("var MT_MENU")
    menu_end = js.index("function buildMtWbNav", menu_start)
    menu = js[menu_start:menu_end]
    ids = set(re.findall(r"id:'([^']+)'", menu))
    parent_ids = set(re.findall(r"id:'([^']+)'[^\n}]*sub:\[", menu))
    leaf_ids = ids - parent_ids

    renderer_start = js.index("function mtPageHtml")
    renderer_end = js.index("function mtWbTbar", renderer_start)
    cases = set(re.findall(r"case '([^']+)'", js[renderer_start:renderer_end]))

    missing = sorted(leaf_ids - cases)
    assert missing == [], f"MikroTik menu items without renderer: {missing}"


def test_network_lab_has_fallback_for_static_device_actions() -> None:
    assert_contains(
        "assets/js/network-lab.js",
        "function bindNetworkLabFallbackActions",
        "function bindDynamicFieldMemory",
        "function restoreDynamicFields",
        "data-sim-action",
        "Symulacja",
        "addEventListener('click', function(e)",
    )
    js = read("assets/js/network-lab.js")
    assert 'onclick="return false"' not in js
    assert "Page not found" not in js


def test_network_lab_pdf_urls_and_logout_static_guard() -> None:
    assert_contains(
        "assets/js/network-lab.js",
        "function resolveLabAssetUrl",
        "window.location.href",
        "resolveLabAssetUrl(PDF_URLS[key] || PDF_URLS['2025-cze'])",
        "sandbox_network_pdf.php?session=",
        "function isStaticLogoutAction",
        "if (isStaticLogoutAction(label)) return;",
    )


def test_network_lab_pdf_proxy_is_authenticated_and_whitelisted() -> None:
    assert_contains(
        "sandbox_network_pdf.php",
        "requireLogin(true)",
        "$pdfFiles",
        "'2025-cze' => 'inf02_2025_cze.pdf'",
        "'2023-cze' => 'inf02_2023_cze.pdf'",
        "'cke' => 'inf02_cke_2026.pdf'",
        "realpath(__DIR__ . '/data/pdfs')",
        "strncmp($filePath, $basePrefix, strlen($basePrefix))",
        "Content-Type: application/pdf",
        "Content-Disposition: inline",
        "X-Content-Type-Options: nosniff",
        "readfile($filePath)",
    )


def test_help_center_has_reliable_opening_path() -> None:
    assert_contains(
        "includes/help_center.php",
        "data-help-center-trigger",
        "document.body.appendChild(panel)",
        "fab?.classList.remove('d-none')",
        "Offcanvas.getOrCreateInstance(panel).show()",
        "setFallbackOpen(true)",
    )
    assert_not_contains(
        "assets/js/theme-handler.js",
        "hide_help_center",
        "applyHelpCenterPreference",
        "updateHelpCenterSetting",
    )
    assert_not_contains("settings.php", "hide_help_center", "helpCenterSwitch")

    missing_bootstrap = []
    footer_pattern = re.compile(r"include\s+['\"](?:\.\./)?includes/footer\.php['\"]")
    for path in ROOT.rglob("*.php"):
        content = path.read_text(encoding="utf-8", errors="ignore")
        if footer_pattern.search(content) and "bootstrap.bundle.min.js" not in content:
            missing_bootstrap.append(str(path.relative_to(ROOT)))
    assert not missing_bootstrap, f"footer pages without Bootstrap bundle: {missing_bootstrap}"


def test_theme_colors_follow_app_preference_only() -> None:
    css = read("assets/css/style.css")
    handler = read("assets/js/theme-handler.js")
    assert "prefers-color-scheme" not in css
    assert "body.dark-mode" in css
    assert "--kolor-tekst-ciemny: #0f172a" in css
    assert "classList.toggle('light-mode', theme !== 'dark')" in handler
    assert "style.colorScheme = theme === 'dark' ? 'dark' : 'light'" in handler


def test_admin_search_uses_unique_pdo_placeholders() -> None:
    admin = read("admin.php")
    functions = read("includes/functions.php")
    search_block = admin.split("if ($search !== '') {", 1)[1].split("} else {", 1)[0]
    helper = functions.split("function searchAdminUsers", 1)[1].split("function getUsers", 1)[0]

    assert "searchAdminUsers($pdo, $search, $limit, $offset)" in search_block
    assert "$adminSearchFailed = !empty($searchResult['error']);" in search_block
    assert "prepare(" not in search_block
    assert "LIKE :q OR" not in helper
    for placeholder in [":q_username", ":q_email", ":q_first_name", ":q_last_name", ":q_class"]:
        assert placeholder in helper
    assert "$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);" in helper
    assert "$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);" in helper
    assert "'error' => true" in helper
    assert "Nie udało się wykonać wyszukiwania użytkowników" in admin


def test_single_question_mode_has_category_setup_without_time_or_answer_check() -> None:
    test_page = read("test.php")
    functions = read("includes/functions.php")
    index = read("index.php")
    theme_handler = read("assets/js/theme-handler.js")

    assert "test.php?mode=single&setup=1&new=1" in index
    assert "test.php?mode=single&setup=1&new=1" in theme_handler
    assert "$categoryExplicitlySelected" in test_page
    assert "$mode === 'single' && (!$wantsStart || !$categoryExplicitlySelected)" in test_page
    assert "$timeLimit = 0;" in test_page
    assert "$timeOption = 'unlimited';" in test_page
    assert "$timeLimitSeconds = 0;" in test_page
    assert "'answer_check_limit' => $mode === 'single' ? 0 : 3" in test_page
    assert "<?php if ($mode !== 'single'): ?>\n                <div class=\"row exam-setup-compact-row\">" in test_page
    assert "<?= $mode === 'single' ? 'show' : '' ?>" in test_page
    assert "['exam', 'practice']" in functions
    assert "['exam', 'practice', 'single']" not in functions.split("function testAnswerCheckModeAllowed", 1)[1].split("}", 1)[0]
    assert "normalizeSingleQuestionTestState($test)" in test_page
    assert "function normalizeSingleQuestionTestState" in functions
    assert "function testCanAdvanceFromReview" in functions
    assert "function singleQuestionCompletedResultId" in functions
    assert "function singleQuestionCategoryFilter" in functions
    assert "function ensureSingleQuestionResultSaved" in functions
    assert "if (!testCanAdvanceFromReview($test))" in test_page
    assert "ensureSingleQuestionResultSaved($pdo, $test, $userId)" in test_page
    assert "recordSingleQuestionResultId($test, $singleResultId);" in test_page
    assert "finishGuestTest($test)" in test_page.split("$nextSingle = prepareNextSingleQuestion", 1)[1].split("exit;", 1)[0]
    assert "$resultId = $singleResultId;" in test_page.split("$nextSingle = prepareNextSingleQuestion", 1)[1].split("exit;", 1)[0]

    ajax = read("ajax/quiz_action.php")
    assert "if (!testCanAdvanceFromReview($test))" in ajax
    assert "ensureSingleQuestionResultSaved($pdo, $test, $ajaxUserId)" in ajax


def test_security_request_context_does_not_trust_proxy_headers_by_default() -> None:
    request_context = read("Security/RequestContext.php")
    session = read("includes/session.php")
    auth = read("includes/auth.php")
    functions = read("includes/functions.php")

    assert "function securityTrustProxyHeaders(): bool" in request_context
    assert "function securityRequestIsSecure(): bool" in request_context
    assert "if (!securityTrustProxyHeaders())" in request_context
    assert "securityRequestIsSecure()" in session
    assert "HTTP_X_FORWARDED_PROTO" not in session.split("function startSecureSession", 1)[1].split("// Set session cookie", 1)[0]

    auth_ip = auth.split("function authClientIpAddress", 1)[1].split("function enforceUserSessionLimit", 1)[0]
    shared_ip = functions.split("function clientIpAddress", 1)[1].split("function consumeRateLimit", 1)[0]
    assert "securityClientIp()" in auth_ip
    assert "securityClientIp()" in shared_ip
    assert "HTTP_X_FORWARDED_FOR" not in auth_ip
    assert "HTTP_X_FORWARDED_FOR" not in shared_ip


def test_security_helpers_runtime_suite() -> None:
    php = shutil.which("php")
    if php is None:
        xampp_php = Path("C:/xampp/php/php.exe")
        php = str(xampp_php) if xampp_php.is_file() else None
    assert php is not None, "PHP CLI is required for security helper runtime tests"

    result = subprocess.run(
        [php, str(ROOT / "tests/security_helpers_runtime.php")],
        cwd=ROOT,
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )
    assert result.returncode == 0, result.stdout + result.stderr
    assert "security helper runtime OK" in result.stdout


def test_help_center_runtime_suite() -> None:
    node = shutil.which("node")
    if node is None:
        raise AssertionError("node executable is required for help center runtime checks")
    result = subprocess.run(
        [node, str(ROOT / "tests/help_center_runtime.js")],
        cwd=ROOT,
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )
    assert result.returncode == 0, result.stdout + result.stderr
    assert "help center runtime OK" in result.stdout


def test_admin_search_runtime_suite() -> None:
    php = shutil.which("php")
    if php is None:
        xampp_php = Path("C:/xampp/php/php.exe")
        php = str(xampp_php) if xampp_php.exists() else None
    if php is None:
        raise AssertionError("php executable is required for admin search runtime checks")
    result = subprocess.run(
        [php, str(ROOT / "tests/admin_search_runtime.php")],
        cwd=ROOT,
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )
    assert result.returncode == 0, result.stdout + result.stderr
    assert "admin search runtime OK" in result.stdout


def test_single_question_runtime_suite() -> None:
    php = shutil.which("php")
    if php is None:
        xampp_php = Path("C:/xampp/php/php.exe")
        php = str(xampp_php) if xampp_php.exists() else None
    if php is None:
        raise AssertionError("php executable is required for single-question runtime checks")
    result = subprocess.run(
        [php, str(ROOT / "tests/single_question_runtime.php")],
        cwd=ROOT,
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )
    assert result.returncode == 0, result.stdout + result.stderr
    assert "single question runtime OK" in result.stdout


def test_exam_ai_guard_runtime_suite() -> None:
    php = shutil.which("php")
    if php is None:
        xampp_php = Path("C:/xampp/php/php.exe")
        php = str(xampp_php) if xampp_php.exists() else None
    if php is None:
        raise AssertionError("php executable is required for exam AI guard runtime checks")
    result = subprocess.run(
        [php, str(ROOT / "tests/exam_ai_guard_runtime.php")],
        cwd=ROOT,
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )
    assert result.returncode == 0, result.stdout + result.stderr
    assert "exam AI guard runtime OK" in result.stdout


def test_exam_ai_guard_is_optional_hidden_and_reported() -> None:
    take = read("exam/take.php")
    action = read("ajax/exam_action.php")
    functions = read("includes/functions.php")

    for form in ["teacher/create_exam.php", "teacher/edit_exam.php", "teacher/custom_exam.php"]:
        assert 'name="ai_copy_guard"' in read(form)
    assert "examAiCopyGuardEnabled($pdo" in take
    assert "event.clipboardData?.setData('text/plain', aiCopyGuardPrompt)" in take
    assert "event.preventDefault();" in take
    assert "reportAiGuardViolation('copy_paste')" in take
    assert "reportAiGuardViolation('screenshot_attempt')" in take
    assert "PrintScreen" in take
    assert "screenshot_attempt" in action
    assert "notifyTeacherAboutExamAiGuard" in action
    assert "requireJsonLogin(true" in action
    assert "guestExamParticipantId($sessionId)" in action
    guard_helpers = functions.split("function examAiCopyGuardSettingKey", 1)[1].split("function featureBlockTargetRoleValues", 1)[0]
    assert "CREATE TABLE" not in guard_helpers
    assert "ALTER TABLE" not in guard_helpers
    assert 'Proszę nie oszukiwać, zostało to zgłoszone do nauczyciela' in functions


def test_password_reset_links_use_configured_public_origin() -> None:
    auth = read("includes/auth.php")
    public_url = read("Security/PublicUrl.php")

    reset_block = auth.split("function sendPasswordResetEmail", 1)[1].split("function getPasswordResetUser", 1)[0]
    assert "securityPasswordResetUrl($token)" in reset_block
    assert "HTTP_HOST" not in reset_block
    assert "SCRIPT_NAME" not in reset_block
    assert "|| true" not in reset_block
    assert "function securityPublicBaseUrl" in public_url
    assert "APP_BASE_URL" in public_url
    assert "https://zsem-egzamin.online" in public_url


def test_password_policy_and_duel_question_lookup_are_hardened() -> None:
    functions = read("includes/functions.php")
    duel_save = read("duels/save_answer.php")

    policy = functions.split("function validatePasswordPolicy", 1)[1].split("function registrationUsernameSlug", 1)[0]
    assert "< 10" in policy
    assert "minimum 10" in policy
    assert "getQuestionsByIds($pdo, [$questionId])" in duel_save
    assert "loadQuestions($pdo)" not in duel_save


def test_auth_guards_fail_closed_and_rate_limits_are_process_shared() -> None:
    auth = read("includes/auth.php")
    limiter = read("Security/RateLimiter.php")

    validation = auth.split("function validateCurrentUserSession", 1)[1].split("function forgetCurrentUserSession", 1)[0]
    assert "catch (Throwable $e)" in validation
    assert "return false;" in validation.split("catch (Throwable $e)", 1)[1]

    json_guard = auth.split("function requireJsonLogin", 1)[1].split("function requireLogin", 1)[0]
    json_catch = json_guard.split("catch (Throwable $e)", 1)[1]
    assert "http_response_code(503);" in json_catch
    assert "exit;" in json_catch

    assert "APP_RATE_LIMIT_DIR" in limiter
    assert "flock($handle, LOCK_EX)" in limiter
    assert "$_SESSION['security_rate_limits']" not in limiter


def test_html_auth_reset_privacy_and_limiter_writes_are_hardened() -> None:
    auth = read("includes/auth.php")
    forgot_password = read("forgot_password.php")
    limiter = read("Security/RateLimiter.php")
    functions = read("includes/functions.php")

    sync_guard = auth.split("function syncSessionUserRole", 1)[1].split("function requireJsonLogin", 1)[0]
    sync_catch = sync_guard.split("catch (Throwable $e)", 1)[1]
    assert "http_response_code(503);" in sync_catch
    assert "exit;" in sync_catch

    assert "Cache-Control: no-store, private" in forgot_password
    assert "Pragma: no-cache" in forgot_password
    assert "Referrer-Policy: no-referrer" in forgot_password

    assert "if (!ftruncate($handle, 0))" in limiter
    assert "if ($payload === false || fwrite($handle, $payload) !== strlen($payload) || !fflush($handle))" in limiter

    user_of_day = functions.split("function getUserOfDay", 1)[1].split("function ", 1)[0]
    assert "DATE(x.created_at)" not in user_of_day
    assert "x.created_at >= CURDATE()" in user_of_day
    assert "x.created_at < CURDATE() + INTERVAL 1 DAY" in user_of_day


def test_session_duel_and_teacher_result_hot_paths_are_bounded() -> None:
    auth = read("includes/auth.php")
    duel_save = read("duels/save_answer.php")
    participant_result = read("teacher/view_participant_result.php")
    exam_details = read("teacher/exam_details.php")

    validation = auth.split("function validateCurrentUserSession", 1)[1].split("function forgetCurrentUserSession", 1)[0]
    assert "UNIX_TIMESTAMP(last_seen)" in validation
    assert "active_session_cleanup_at" in validation
    assert "INTERVAL 5 MINUTE" in validation
    assert "enforceUserSessionLimit" not in validation
    assert "SELECT COUNT(*) FROM active_user_sessions" not in validation
    assert "registerCurrentUserSession($pdo, $userId)" not in validation

    sync_guard = auth.split("function syncSessionUserRole", 1)[1].split("function requireJsonLogin", 1)[0]
    assert "if (!$pdo instanceof PDO)" in sync_guard
    assert "http_response_code(503);" in sync_guard

    json_guard = auth.split("function requireJsonLogin", 1)[1].split("function requireLogin", 1)[0]
    assert "if (!$pdo instanceof PDO)" in json_guard
    assert "Authentication service unavailable" in json_guard

    assert "ON DUPLICATE KEY UPDATE id = id" in duel_save
    assert "SELECT user_answer FROM duel_answers" in duel_save

    assert "getQuestionsByIds($pdo, $sessionQuestionIds)" in participant_result
    assert "loadQuestions($pdo)" not in participant_result

    assert "$hasAdminAccess = roleHasAdminAccess" in exam_details
    assert "($hasAdminAccess || (int)$session['teacher_id'] === (int)$userId)" in exam_details


def test_rank_style_values_and_failed_avatar_uploads_are_contained() -> None:
    functions = read("includes/functions.php")
    profile_action = read("actions/update_profile.php")

    assert "function normalizeRankColor" in functions
    assert "function normalizeRankIcon" in functions
    assert "preg_match('/^#[0-9a-f]{6}$/i'" in functions
    assert "preg_match('/^bi-[a-z0-9-]{1,64}$/i'" in functions

    create_rank = functions.split("function createRankDefinition", 1)[1].split("function updateRankDefinition", 1)[0]
    update_rank = functions.split("function updateRankDefinition", 1)[1].split("function awardXp", 1)[0]
    assert "normalizeRankColor($color)" in create_rank
    assert "normalizeRankIcon($icon)" in create_rank
    assert "normalizeRankColor($color)" in update_rank
    assert "normalizeRankIcon($icon)" in update_rank

    assert "$avatarDestination = null;" in profile_action
    assert "if ($avatarUploaded && $avatarDestination && is_file($avatarDestination))" in profile_action


def test_destructive_actions_and_public_exam_links_use_hardened_context() -> None:
    delete_account = read("actions/delete_account.php")
    reset_progress = read("actions/reset_progress.php")
    test_details = read("ajax/get_test_details.php")
    logout = read("actions/logout.php")
    host_exam = read("teacher/host_exam.php")

    assert "requireLogin();" in delete_account
    assert "requireLogin();" in reset_progress
    assert "syncSessionUserRole();" in test_details
    assert "mfaAccessRequired()" in test_details

    assert "securityRequestIsSecure()" in logout
    assert "HTTP_X_FORWARDED_PROTO" not in logout

    join_url = host_exam.split("$joinUrl = '';", 1)[1].split("?>", 1)[0]
    assert "securityPublicBaseUrl()" in join_url
    assert "HTTP_HOST" not in join_url
    assert "HTTP_X_FORWARDED_PROTO" not in join_url


def test_login_and_registration_have_database_independent_rate_limits() -> None:
    auth = read("includes/auth.php")

    registration_limit = auth.split("function checkRegistrationRateLimit", 1)[1].split("function recordRegistrationAttempt", 1)[0]
    login_limit = auth.split("function checkRateLimit", 1)[1].split("function getFailedLoginAttemptCount", 1)[0]

    assert "securityConsumeRateLimit('auth:register:ip:'" in registration_limit
    assert "securityConsumeRateLimit('auth:register:identity:'" in registration_limit
    assert "5, 3600" in registration_limit
    assert "securityConsumeRateLimit('auth:login:ip:'" in login_limit
    assert "securityConsumeRateLimit('auth:login:identity:'" in login_limit
    assert "20, 600" in login_limit
    assert "empty($ipLimit['allowed']) || empty($identityLimit['allowed'])" in registration_limit
    assert "empty($ipLimit['allowed']) || empty($identityLimit['allowed'])" in login_limit


def test_proxy_allowlist_public_url_fallback_and_limiter_cleanup_exist() -> None:
    request_context = read("Security/RequestContext.php")
    public_url = read("Security/PublicUrl.php")
    limiter = read("Security/RateLimiter.php")

    assert "APP_TRUSTED_PROXY_IPS" in request_context
    assert "function securityRequestComesFromTrustedProxy" in request_context
    assert "securityRequestComesFromTrustedProxy()" in request_context
    assert "function securityIpMatchesTrustedRange" in request_context

    assert "CLIENT_URL" in public_url
    assert "APP_BASE_URL" in public_url

    assert "function securityPruneRateLimitFiles" in limiter
    assert "DirectoryIterator" in limiter
    assert "securityPruneRateLimitFiles($directory, $now)" in limiter
    assert "$deleted >= 500" in limiter
    assert "$scanned >= 500" not in limiter


def test_server_config_and_turnstile_do_not_trust_spoofable_host_headers() -> None:
    htaccess = read(".htaccess")
    auth = read("includes/auth.php")
    exam_take = read("exam/take.php")

    https_redirect = htaccess.split("# Redirect production traffic to HTTPS", 1)[1].split("# Block internal", 1)[0]
    assert "X-Forwarded-Proto" not in https_redirect

    turnstile = auth.split("function verifyTurnstile", 1)[1].split("function ", 1)[0]
    assert "HTTP_HOST" not in turnstile
    assert "securityClientIp()" in turnstile

    debug_block = exam_take.split("$debugInfo = '';", 1)[1].split("$csrfToken", 1)[0]
    assert "HTTP_HOST" not in debug_block


def test_ci_runs_all_static_and_runtime_quality_gates() -> None:
    workflow = read(".github/workflows/quality.yml")
    assert "shivammathur/setup-php" in workflow
    assert "python tests/static_compliance_check.py" in workflow
    assert "php -l" in workflow
    assert "node --check" in workflow


def test_password_hashes_upgrade_and_secure_randomness_fails_closed() -> None:
    auth = read("includes/auth.php")
    session = read("includes/session.php")

    assert "PASSWORD_BCRYPT" not in auth
    login_block = auth.split("function login", 1)[1].split("function register", 1)[0]
    rehash_block = auth.split("function upgradePasswordHashIfNeeded", 1)[1].split("// =============================================================================", 1)[0]
    assert "upgradePasswordHashIfNeeded($pdo, $user, $password);" in login_block
    assert "password_needs_rehash($currentHash, PASSWORD_DEFAULT)" in rehash_block
    assert "SET password_hash = :password_hash WHERE id = :user_id AND password_hash = :current_hash" in rehash_block
    assert "'current_hash' => $currentHash" in rehash_block
    assert "$stmt->rowCount() === 1" in rehash_block
    assert "session_version" not in rehash_block

    random_bytes_helper = session.split("function secureRandomBytes", 1)[1].split("function getCsrfTokenMaxAge", 1)[0]
    assert "return random_bytes($length);" in random_bytes_helper
    assert "mt_rand" not in random_bytes_helper
    assert "openssl_random_pseudo_bytes" not in random_bytes_helper


def test_notification_and_lesson_pdf_paths_are_confined() -> None:
    functions = read("includes/functions.php")
    feed = read("ajax/notifications_feed.php")
    lesson_pdf = read("lesson_pdf.php")

    action_url = functions.split("function normalizeNotificationActionUrl", 1)[1].split("function ", 1)[0]
    assert "HTTP_HOST" not in action_url
    assert "preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)" in action_url
    assert "securityPublicBaseUrl()" in action_url
    assert "$configuredBasePath" in action_url
    assert "rawurldecode($decodedPath)" in action_url
    assert "str_starts_with($url, '//')" in action_url
    assert "return null;" in action_url
    assert "function notificationActionHref" in functions
    assert "notificationActionHref($notifUrl, $baseUrl)" in functions
    assert "notificationActionHref($notif['action_url'])" in read("notifications.php")

    assert "in_array($baseUrl, ['', '../'], true)" in feed
    assert "str_starts_with($baseUrl, '//')" not in feed

    assert "$pdfRoot = realpath($pdfDir)" in lesson_pdf
    assert "$resolvedPdfFile = realpath($pdfFile)" in lesson_pdf
    assert "strncmp($resolvedPdfFile, $pdfPrefix, strlen($pdfPrefix)) !== 0" in lesson_pdf


def test_global_and_admin_text_contrast_guards() -> None:
    style = read("assets/css/style.css")
    admin = read("admin.php")

    for rule in [
        ".text-primary { color: #1d4ed8 !important; }",
        ".text-success { color: #047857 !important; }",
        ".text-danger { color: #b91c1c !important; }",
        ".text-info { color: #0e7490 !important; }",
        ".text-warning { color: #92400e !important; }",
        "body.dark-mode .text-primary { color: #93c5fd !important; }",
        "body.dark-mode .text-success { color: #6ee7b7 !important; }",
        "body.dark-mode .text-danger { color: #fca5a5 !important; }",
        "body.dark-mode .text-info { color: #67e8f9 !important; }",
        "body.dark-mode .text-warning { color: #fde68a !important; }",
    ]:
        assert rule in style

    assert "body.dark-mode .admin-user-email" in admin
    assert "color: #cbd5e1 !important;" in admin
    assert "body.dark-mode .admin-icon-btn.btn-outline-success" in admin
    assert "color: #6ee7b7 !important;" in admin
    assert 'class="admin-user-email" style="font-size: .8rem; color:' not in admin


def test_app_status_fallback_sync_is_session_throttled() -> None:
    functions = read("includes/functions.php")
    sync_block = functions.split("function syncAppStatusNotificationsForUser", 1)[1].split("function notifyUsersAboutAppStatus", 1)[0]

    assert "app_status_sync_at" in sync_block
    assert "session_status() === PHP_SESSION_ACTIVE" in sync_block
    assert "time() - 300" in sync_block
    assert "$_SESSION[$syncKey] = time();" in sync_block


def test_friend_request_endpoint_uses_single_authoritative_eligibility_path() -> None:
    action = read("actions/send_friend_request.php")
    functions = read("includes/functions.php")
    helper = functions.split("function sendFriendRequest", 1)[1].split("function acceptFriendRequest", 1)[0]

    assert "SELECT id, role, allow_friend_requests" not in action
    assert "canSendMoreFriendRequests" not in action
    assert "canSendFriendRequest" not in action
    assert "sendFriendRequest($pdo, $myId, $friendId, $failureReason)" in action
    assert "?string &$failureReason = null" in helper
    assert "friend_request_limit" in helper
    assert "friend_request_privacy" in helper


def test_admin_dashboard_avoids_duplicate_aggregation_and_listener_fanout() -> None:
    admin = read("admin.php")
    functions = read("includes/functions.php")

    assert "$totalUsers = $adminKpis['users_total'];" in admin
    assert "getAllAdminRequests($pdo, 8)" in admin
    assert "countOpenAdminRequests($pdo)" in admin
    assert "array_slice($allAdminRequests" not in admin
    assert "document.querySelectorAll(selector).forEach" not in admin
    assert "document.querySelectorAll('form[data-admin-confirm]').forEach" not in admin
    assert "document.querySelectorAll('button[data-admin-confirm]').forEach" not in admin
    assert "function getAllAdminRequests($pdo, ?int $limit = null)" in functions
    assert "function countOpenAdminRequests(PDO $pdo): int" in functions


if __name__ == "__main__":
    for name, fn in sorted(globals().items()):
        if name.startswith("test_") and callable(fn):
            fn()
            print(f"OK {name}")
