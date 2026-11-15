# 🗺️ ZSEM Tech – Application Map & Reference Guide

This document is the **memory file** and source of truth for the ZSEM Tech platform codebase. When onboarding, searching for functionality, or modifying features, **always consult this file first**. Keep it updated whenever files are added, removed, or restructured.

---

## 🏗️ Core Architecture Overview

ZSEM Tech is a PHP-based web application structured as a clean, modular monolith. It uses a combination of server-side PHP templates for pages and raw SQL queries via a PDO instance for database interaction. Asynchronous frontend interactivity (such as taking exams, dueling 1v1, playing courses, and using sandbox tools) is driven by custom client-side JavaScript communicating with specialized AJAX handlers.

- **Primary Language**: PHP (with MySQL database)
- **Frontend Interactivity**: Plain JS / CSS (Vibrant dark design system, glassmorphism)
- **Security & Hardening**: Strict central filtering, custom Rate Limiting, CSRF tokens, and secure headers.
- **Database Entry Point**: [config/db.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/config/db.php)
- **Database Schema**: Defined in [full_schema.sql](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/full_schema.sql)
- **Global Helper Functions**: Defined in [includes/functions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/functions.php) (Auth, XP, Ranks, Missions, Notifications, and more).

---

## 📂 Directory Map

Below is a detailed breakdown of the application structure, describing what resides in each directory and where to search for specific functionality.

### 📁 Root Directory (`/`)
Contains main landing pages, public directories, and primary modules:
*   [index.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/index.php) – **Dashboard**: The main page after logging in. Displays user ranking, levels, daily missions, events, and navigation.
*   [landing.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/landing.php) – **Landing Page**: Public front page for guests.
*   [test.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/test.php) – **Exam Simulator**: Formally takes official 40-question mock exams (monitored/school mode).
*   [practice.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/practice.php) – **Practice Simulator**: Interactive training mode where explanations are shown and question mastery is calculated.
*   [courses.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/courses.php) – **Course browser**: Displays available e-learning courses.
*   [course_view.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/course_view.php) – Course details, lessons index.
*   [course_learn.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/course_learn.php) – Course player environment.
*   [course_certificate.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/course_certificate.php) – Generator for course completion certificates.
*   [verify_certificate.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/verify_certificate.php) – Public verification page for generated certificates.
*   [categories.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/categories.php) – Allows users to select questions belonging to specific INF.02/INF.03 exam categories.
*   [flashcards.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/flashcards.php) – Study tool with interactive IT terminology decks.
*   [dictionary.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/dictionary.php) – IT dictionary lookup.
*   [ranking.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ranking.php) – Global and seasonal leaderboards.
*   [result.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/result.php) – Comprehensive test/practice result summaries and question-by-question breakdown.
*   [search_users.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/search_users.php) – Search/browse profiles.
*   [qualification.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/qualification.php) – Selection page for qualification syllabus (INF.02, INF.03, etc.).
*   [lesson_pdf.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/lesson_pdf.php) – Helper to load and display PDF attachments for lessons.
*   [llms.txt](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/llms.txt) – Information file identifying creators and attribution rules for AI assistants.

---

### 📁 `/actions/`
Handles POST form submissions, data persistence, and page redirects. Look here for standard forms logic:
*   [profile_comment.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/profile_comment.php) – Adding/deleting comments on user profiles.
*   [profile_section.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/profile_section.php) – Managing CV portfolio sections (experience, languages, courses).
*   [update_profile.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/update_profile.php) – Updates username, avatar, settings.
*   [update_privacy.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/update_privacy.php) – Profile visibility settings.
*   [change_password.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/change_password.php) – Normal password modification.
*   [delete_account.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/delete_account.php) – Account erasure procedure.
*   [send_friend_request.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/send_friend_request.php) & [handle_friend_request.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/handle_friend_request.php) – Social system handlers.
*   [delete_notification.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/delete_notification.php), [mark_read.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/mark_read.php) – Notification management.
*   [delete_test_result.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/delete_test_result.php), [reset_progress.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/reset_progress.php) – Resetting statistics or removing specific history logs.
*   [respond_mfa_prompt.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/respond_mfa_prompt.php) – Verifies dynamic MFA codes.
*   [start_guest.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/start_guest.php) – Quick session initiation for unauthenticated guests.
*   [logout.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/logout.php), [logout_all_sessions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/actions/logout_all_sessions.php) – Session destruction.

---

### 📁 `/ajax/`
API endpoints called by frontend JavaScript to perform background operations and return JSON:
*   [quiz_action.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/quiz_action.php) – Key backend handler for practice questions, storing answers, and marking mastery.
*   [exam_action.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/exam_action.php), [exam_status.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/exam_status.php), [exam_violation.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/exam_violation.php) – Real-time event communication for Monitored School Exams (Anti-Cheat logs, focus losses, and question progress).
*   [course_progress.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/course_progress.php) – Tracks and updates course completion metrics.
*   [duel_respond.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/duel_respond.php) – Handles multiplayer 1v1 challenges, responses, and bets.
*   [passkey_register.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/passkey_register.php) & [passkey_login.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/passkey_login.php) – WebAuthn registration/login negotiation.
*   [admin_courses.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/admin_courses.php) – course management hooks.
*   [get_test_details.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/get_test_details.php) – Fetches historical test data for summary modals.
*   [check_registration_availability.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/check_registration_availability.php) – Live validation of username/email availability.
*   [notifications_feed.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/notifications_feed.php) – Powers real-time notification updates.
*   [extend_session.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/extend_session.php), [get_session_status.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/get_session_status.php) – Handles session health.
*   [sync_offline_progress.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/sync_offline_progress.php) – Transactional batch processor for PWA offline test submissions and XP rewards.
*   [teacher_status.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/teacher_status.php) – Feeds real-time monitoring graphs for active tests to the teacher's dashboard.

---

### 📁 `/api/`
Public and internal streaming API endpoints:
*   [events_sse.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/api/events_sse.php) – **Real-Time SSE Stream**: Server-Sent Events engine delivering live participant and violation updates for teacher proctoring and 1v1 duel progress without session lock contention.
*   [health.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/api/health.php) – System health and service probe endpoint.

---

### 📁 `/admin/`
Control panel views restricted to administrator accounts:
*   [index.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/admin/index.php) – Main admin panel housing auditing logs, ban tables, user role managers, support tickets, and sandbox controls.
*   [course_builder.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/admin/course_builder.php) – Dynamic builder for custom courses and interactive lessons.
*   [manage_courses.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/admin/manage_courses.php) – Courses list administrator.
*   [manage_questions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/admin/manage_questions.php) – Edit/add exam questions manually.
*   [requests.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/admin/requests.php) – Review and accept requests for teacher role allocations.

---

### 📁 `/teacher/`
Classroom control features designed for educational instructors:
*   [index.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/index.php) – Main teacher dashboard.
*   [create_exam.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/create_exam.php) & [edit_exam.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/edit_exam.php) – Generates controlled test sessions, sets full-screen policies, maximum attempts, and target questions.
*   [custom_exams.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/custom_exams.php) – View list of created school exams.
*   [host_exam.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/host_exam.php) – **Real-Time Proctor Panel**: Monitors live students, current question progress, focus loss incidents, anti-cheat violations, and warnings.
*   [pdf_generator.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/pdf_generator.php) – Generates printable PDF test sheets from selected pools of questions.
*   [txt_generator.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/txt_generator.php) – Exports selected exam questions to raw txt files.
*   [import_txt.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/import_txt.php) – Import questions from TXT format.
*   [view_participant_result.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/view_participant_result.php) – Details score cards for individual student submissions.

---

### 📁 `/user/`
Personal dashboards, stats, configurations, and social interfaces:
*   [profile.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/profile.php) – Highly customizable user dashboard acting as a **Virtual CV Portfolio**. Supports editing profile headers, language proficiency, education history, work history, and viewing certifications.
*   [settings.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/settings.php) – Central configuration panel containing security details, password resets, email adjustments, session trackers, MFA settings, and WebAuthn (Passkeys) configurations.
*   [progress.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/progress.php) – Renders comprehensive progress diagrams showing category mastery statistics and answer history.
*   [goals.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/goals.php) – Daily goals and checklist achievements.
*   [history.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/history.php) – List of completed tests, scores, and links to detailed results.
*   [notifications.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/notifications.php) – In-app notifications box.
*   [social.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/social.php) – Friends list, pending invitations, and interactive search.

---

### 📁 `/includes/`
Central initialization files, UI layout templates, helper modules, and library integrations:
*   [functions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/functions.php) – The absolute backbone of business logic. Contains utility methods, rank progression calculators, daily mission generators, notifications dispensers, audit logging functions, wheel of fortune processing, and authentication state validations.
*   [session.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/session.php) – Custom session management enforcing activity timeouts, fingerprint checks, database backups for active sessions, and multi-session control.
*   [header.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/header.php) – Head HTML tag, page titles, CDN setups, and styling attachments.
*   [topbar.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/topbar.php) – Top navigational header layout (level, XP progress bars, wheel of fortune button, and theme controls).
*   [sidebar.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/sidebar.php) – Side navigation listing practice tools, exam simulators, sandbox options, and profile links.
*   [footer.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/footer.php) – Footer links, modals initialization, dynamic JS setups.
*   [CourseService.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/CourseService.php) – OOP Service handler managing backend course queries, lesson ordering, progress calculators, and certificate templates.
*   [KappiCrypt.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/KappiCrypt.php) – Encryption wrapper.
*   [autoloader.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/autoloader.php) – Simple class autoloader for dynamic object loading.
*   📁 `WebAuthn/` – External library configuration handling passkey structure, attestation verification, and CBOR structures.

---

### 📁 `/Security/`
Custom security framework regulating application-wide filtering, requests, and protective layers. Instantiated globally inside `includes/functions.php` or `bootstrap.php`:
*   [bootstrap.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/bootstrap.php) – Initializes security headers and configurations.
*   [RequestContext.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/RequestContext.php) – Extracts current URL, HTTP parameters, query inputs, and filters request parameters.
*   [Input.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/Input.php) – Input sanitization layer preventing XSS, SQLi injections, and validating email/number patterns.
*   [CsrfGuard.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/CsrfGuard.php) – CSRF validation helper generating and verifying tokens on page submissions and AJAX targets.
*   [RateLimiter.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/RateLimiter.php) – Prevents brute-force or enumeration attacks on authentication routes or quiz submissions.
*   [Headers.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/Headers.php) – Appends security response headers (CSP, HSTS, X-Frame-Options, Content-Type-Options).
*   [Redirect.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/Redirect.php) – Secure redirect mechanisms checking host validation.
*   [Audit.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/Audit.php) – Handles audit logging for critical administrative events.
*   [PublicUrl.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/PublicUrl.php) – Defines whitelist routes accessible without authentication.
*   [JsonResponse.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security/JsonResponse.php) – standardizes API responses.

---

### 📁 `/assets/`
Static frontend design resources:
*   📁 `/assets/css/` – Custom stylesheet configurations:
    *   [style.css](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/css/style.css) – Main stylesheet defining theme colors, layouts, cards, fonts, and responsiveness.
    *   [dashboard-new.css](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/css/dashboard-new.css) – Modern layout adjustments, animations, and charts.
    *   [sandbox.css](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/css/sandbox.css) – Stylings for calculators, logic simulator, and bitwise converters.
    *   [network-lab.css](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/css/network-lab.css) – Styles specific to the router lab layout.
*   📁 `/assets/js/` – Custom client-side scripts:
    *   [theme-handler.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/theme-handler.js) – Toggles dark/light mode configurations and saves settings.
    *   [sandbox.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/sandbox.js) – Entire core engine for logic gate editor, subnets, PSU, Ohm's law, and number conversions.
    *   [network-lab.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/network-lab.js) – Visual router laboratory script enabling port-to-port links, console interactions, and command line mockups.
    *   [quiz-engine.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/quiz-engine.js) – Dynamic questions player, clock tracker, and review manager.
    *   [exam-engine.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/exam-engine.js) – Monitored testing client. Hooked into Fullscreen APIs, blur tracking, and anti-cheat actions.
    *   [offline-engine.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/offline-engine.js) – IndexedDB database manager for PWA offline test solving and auto-sync.
    *   [sw.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/sw.js) – Service worker providing asset caching, background sync and offline fallback.

---

### 📁 Other Folders
*   📁 `/auth/` – Custom auth layouts:
    *   [login.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/auth/login.php) – Login form template.
    *   [register.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/auth/register.php) – Registration form template.
    *   [mfa.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/auth/mfa.php) – Authentication secondary step.
    *   [forgot_password.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/auth/forgot_password.php), [verify_email.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/auth/verify_email.php) – Credentials validation.
*   📁 `/data/` & `/data_question/` – Application assets.
    *   `/data/` contains user-uploaded items (avatar assets).
    *   `/data_question/` holds standard JSON files representing databases of exam questions (`inf02.json`, `inf03.json`, etc.).
*   📁 `/duels/` – Multiplayer matchmaking layouts:
    *   [lobby.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/duels/lobby.php) – Matching hub.
    *   [challenge.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/duels/challenge.php) – Sets rules and stakes for a match.
    *   [take.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/duels/take.php) – Active battle view with question timers.
    *   [results.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/duels/results.php) – Outcome board.
*   📁 `/sheets/` – Houses downloadable syllabus PDFs, divided by qualifications (`Praktyka INF 02`, `Praktyka INF 03`, etc.).
*   📁 `/tests/` – Python validation engine and runtime checks:
    *   [static_compliance_check.py](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/tests/static_compliance_check.py) – Large Python script performing quality and compliance validations on the project files.
    *   Various `*_runtime.php` scripts checking specific database read speeds, API endpoints, and auth processes.

---

## 🛠️ Feature Reference (Where to look for what?)

| Requirement / Task | Frontend File | Backend / API Endpoint | Logic & Helpers |
|---|---|---|---|
| **User XP & Leveling** | [topbar.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/topbar.php) | *Direct PHP Injection* | `addXP()`, `calculateRank()` in [functions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/functions.php) |
| **Exam Engine UI & Clock** | [test.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/test.php) | [ajax/quiz_action.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/quiz_action.php) | [assets/js/quiz-engine.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/quiz-engine.js) |
| **Question Mastery Tracker** | [practice.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/practice.php) | [ajax/quiz_action.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/quiz_action.php) | `updateMastery()` in [functions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/functions.php) |
| **1v1 Duels Battle** | [duels/take.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/duels/take.php) | [ajax/duel_respond.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/duel_respond.php) | [duels/save_answer.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/duels/save_answer.php), `resolveDuel()` in [functions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/functions.php) |
| **Anti-Cheat Validation** | [exam/take.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/exam/take.php) | [ajax/exam_violation.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/exam_violation.php) | [assets/js/exam-engine.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/exam-engine.js) |
| **Proctor Proctoring** | [teacher/host_exam.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/teacher/host_exam.php) | [ajax/teacher_status.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/teacher_status.php) | *Server-Sent Events / Long-polling* |
| **Passkey (FIDO2) Registration** | [user/settings.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/user/settings.php) | [ajax/passkey_register.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/passkey_register.php) | [includes/WebAuthn/WebAuthn.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/WebAuthn/WebAuthn.php) |
| **Sandbox: Logic Gates** | [sandbox/index.php?tool=logic](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/sandbox/index.php) | *Client-Side Only* | [assets/js/sandbox.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/sandbox.js) (`LogicWorkbench`) |
| **Sandbox: Router Lab** | [sandbox/index.php?tool=router](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/sandbox/index.php) | *Client-Side Only* | [assets/js/network-lab.js](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/assets/js/network-lab.js) (`RouterSim`) |
| **Sandbox Blocks** | [admin/index.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/admin/index.php) | [ajax/quiz_action.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/ajax/quiz_action.php) | `isSandboxToolBlocked()` in [functions.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/includes/functions.php) |

---

## 🧭 Instructions for AI Development

When working on this codebase:
1. **Search first**: If you are trying to understand a feature or looking for where to apply a change, read this map file first.
2. **Update the map**: If you introduce a new file, remove a file, or modify the core purpose of a folder or file, **you must update this file immediately**.
3. **Check connections**: Ensure that when modifying form templates, their corresponding files in `/actions/` or endpoints in `/ajax/` are also checked.
4. **Follow security guidelines**: Any DB query modifications must use parameterized PDO bindings as established in [config/db.php](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/config/db.php). Any input handling must pass through the security controllers in [/Security/](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/Security).
