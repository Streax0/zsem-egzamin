# PDF Final List Implementation Plan

> For agentic workers: REQUIRED SUB-SKILL: use superpowers:executing-plans or subagent-driven-development before implementation. Execute task-by-task and update checkbox state.

**Goal:** Implement every actionable item from `C:\Users\damia\Downloads\LISTA FINAL-1 (1).pdf` exactly, including image-based layout requests.

**Architecture:** Keep existing PHP app structure. Preserve internal mode keys and database schema where possible; change user-facing copy, page layout, upload handling, and frontend behavior at the owning pages/assets. Split the flashcards rebuild into dedicated CSS/JS assets so `flashcards.php` owns data and markup, not the full interaction implementation.

**Tech Stack:** PHP 8, PDO/MySQL, Bootstrap, Bootstrap Icons, vanilla JS, existing `assetUrl()` cache-busting, existing Python static checks.

**Baseline/Authority Refs:**
- PDF rendered pages: `tmp/pdfs/lista-final/page-001.png`, `tmp/pdfs/lista-final/page-002.png`
- Extracted PDF text: `tmp/pdfs/lista-final/extracted.txt`
- Current tests: `tests/static_compliance_check.py`

**Compatibility Boundary:**
- Do not rename database values for modes. Keep `exam`, `exam_simulator`, `practice`, `single` as stored keys.
- Do not let students create or submit custom flashcards.
- Keep teacher/admin/dyrektor request permissions for flashcard proposals.
- Keep avatar file path format compatible with `userAvatarSrc()` and `deleteLocalAvatarFile()`.
- Keep generated worksheet questions compatible with existing DB, TXT, manual, and saved custom exam flows.

**Verification:**
- `python tests/static_compliance_check.py`
- `php -l register.php login.php social.php flashcards.php test.php actions/update_profile.php ajax/check_registration_availability.php includes/functions.php teacher/pdf_generator.php`
- Browser QA for registration, login email, test setup slider, social layout, flashcards, generator print/PDF preview.
- Scoped security check for auth/login, registration availability endpoint, avatar upload, and worksheet print HTML escaping.

---

## Plan Basis

PDF has 9 numbered rows. Items 1-8 are actionable. Item 9 is empty on page 2; treat it as reviewed and non-actionable, not skipped.

## Files

- Modify `register.php`: remove forbidden registration helper copy; keep optional username field; align placeholder/help text with new suggestion behavior.
- Modify `assets/js/register.js`: remove forbidden empty-login copy; show available suggestions when typed nick is taken; keep local validation.
- Modify `ajax/check_registration_availability.php`: ensure taken username returns generated suggestions.
- Verify/possibly modify `login.php` and `includes/auth.php`: email login already exists; adjust copy only if needed.
- Modify `includes/functions.php`, `index.php`, `test.php`, `result.php`, `assets/js/result-share-card.js`: user-facing mode labels `Tryb CKE` -> `Egzamin`, `Egzamin` -> `Test` for test modes only.
- Modify `social.php`: move suggested users and recent activity cards from cramped right sidebar into the large center/main area shown in PDF screenshot.
- Modify `teacher/pdf_generator.php`: fix generation/print flow and restyle worksheet output to match PDF reference sheet.
- Modify `actions/update_profile.php`: compress saved avatar WebP to max 25 KB, with deterministic fallback/rejection.
- Possibly modify `profile.php` and `settings.php`: avatar upload text should state 25 KB server limit after compression.
- Replace/modify `flashcards.php`; create `assets/css/flashcards.css`; create `assets/js/flashcards.js`.
- Modify `test.php` and `assets/css/style.css`: repair time slider panel/range styling.
- Modify `tests/static_compliance_check.py`: update existing assertions to the new PDF requirements and add guards for avatar 25 KB, mode labels, social card relocation, and flashcard no-student-add rule.

## Architecture Integrity Lens

- Invariant: app stores stable internal keys; UI labels can change without data migration.
- Canonical owners: registration suggestion logic stays in `includes/functions.php` + `ajax/check_registration_availability.php`; avatar storage stays in `actions/update_profile.php`; worksheet print stays in `teacher/pdf_generator.php`; flashcard UI state stays client-side.
- Responsibility overlap: avoid duplicating mode label arrays by updating all existing label maps now, then consider a helper later only if duplication keeps growing.
- Higher-level simplification: flashcards get asset split because current page mixes data, CSS, and interaction in one large file.
- Retirement/falsifier: old student/custom flashcard copy and forbidden registration helper copy must not remain in PHP/JS.
- Verdict: proceed with scoped edits, no schema migration required.

## Plan Pressure Test

- Owner / contract / retirement: each PDF point maps to one owner file group; old copy and old flashcard custom-add surface retired.
- Architecture integrity / higher-level path: use existing helpers and role functions; no new auth model.
- Verification scope: static assertions + PHP lint + browser QA.
- Task executability: tasks can be done in order; flashcards and generator are largest.
- Pressure result: proceed.

## Plan-Time Complexity Check

- Target files: `flashcards.php`, `teacher/pdf_generator.php`, `test.php`, `social.php`, `actions/update_profile.php`, `includes/functions.php`.
- Existing size / shape signals: several large PHP files; avoid broad refactors.
- Owner fit: existing page owners are correct.
- Add-in-place risk: high for `flashcards.php` if JS/CSS remains inline.
- Better file boundary: extract flashcard CSS/JS to assets; keep generator CSS inline because print CSS is page-specific.
- Recommendation: extract helper/assets only for flashcards and avatar compression helper; edit the rest in place.

## Tasks

### Task 1: Registration Login Suggestions

**Files:** `register.php`, `assets/js/register.js`, `ajax/check_registration_availability.php`, `includes/functions.php`, `tests/static_compliance_check.py`

**Why:** PDF item 1 says remove the empty-login helper copy and propose variants when a chosen nick is taken.

**Steps:**
- [ ] Add/adjust tests: forbidden strings are absent: `Nie wpisujesz loginu? Przykład`, `Dokładny numer dobierze serwer`, `Puste pole = login wygenerowany automatycznie`.
- [ ] Keep optional username, but replace helper copy with short neutral text that does not describe automatic empty-login behavior.
- [ ] Ensure AJAX returns 2-3 available suggestions like `test53` when `test` is taken.
- [ ] Ensure JS renders suggestion buttons and clicking one fills username + rechecks.
- [ ] Run static check and PHP lint for touched files.

**Acceptance:**
- Typing a taken username shows clickable alternatives.
- Empty username no longer shows the forbidden PDF text.
- Server-side duplicate username error also includes suggestions.

### Task 2: Email Login

**Files:** `login.php`, `includes/auth.php`, `tests/static_compliance_check.py`

**Why:** PDF item 2 requires login by email.

**Steps:**
- [ ] Verify `login()` searches by username OR email.
- [ ] Keep or adjust login field label to clearly say `Login lub e-mail`.
- [ ] Add static assertion for `WHERE username = :username OR email = :email`.
- [ ] Browser QA: login form accepts an email-shaped identifier.

**Acceptance:**
- No regression to username-only login.
- UI clearly communicates email login.

### Task 3: Rename Test Mode Labels

**Files:** `includes/functions.php`, `index.php`, `test.php`, `result.php`, `assets/js/result-share-card.js`, `tests/static_compliance_check.py`

**Why:** PDF item 3 says mode `cke` becomes `Egzamin`; mode `egzamin` becomes `Test`.

**Steps:**
- [ ] Update user-facing labels for `exam_simulator`/CKE to `Egzamin`.
- [ ] Update user-facing labels for `exam` to `Test`.
- [ ] Keep internal keys unchanged.
- [ ] Update static tests that currently require `Tryb CKE`.
- [ ] Search app code for leftover `Tryb CKE` in user-facing PHP/JS and remove/replace where it means test mode.

**Acceptance:**
- User sees `Egzamin` for the CKE simulator mode.
- User sees `Test` for the old exam test mode.
- Teacher-hosted exam/session wording is not broken by an unsafe global replace.

### Task 4: Social Page Card Relocation

**Files:** `social.php`, `tests/static_compliance_check.py`

**Why:** PDF item 4 screenshot shows the suggested users and recent activity cards moved out of the narrow right-bottom area into the larger main content area.

**Steps:**
- [ ] Move `social-insights-grid` out of `.social-sidebar`.
- [ ] Place recent activity + suggested users under the main friends/search content so they occupy the large center space.
- [ ] Leave received/sent invitations in the right sidebar.
- [ ] Adjust CSS for desktop two-column cards and mobile single-column order.
- [ ] Browser QA desktop and mobile.

**Acceptance:**
- `Ostatnia aktywność znajomych` and `Ludzie, których możesz znać` are visible in main area, not squeezed in right sidebar.
- Search results and friends list remain usable.

### Task 5: Worksheet Generator Repair And Print Styling

**Files:** `teacher/pdf_generator.php`, `tests/static_compliance_check.py`

**Why:** PDF item 5 says generator does not work and generated documents should look like the provided reference image.

**Steps:**
- [ ] Verify DB/TXT/manual generation paths produce `$worksheetGroups`.
- [ ] Fix any form/action/JS issue found during browser QA.
- [ ] Restyle worksheet output to A4 reference: top student/class/points line, group badge, black numbered question boxes, compact option rows, graph-paper answer areas for open questions, footer with generator brand and page text.
- [ ] Keep question images printed with safe `questionImageSrc()`.
- [ ] Ensure `printWorksheet('pdf')` opens a clean printable document and does not include app chrome.
- [ ] Browser QA: generate DB sample, print all groups, print one group, include answer key.

**Acceptance:**
- Generator produces a visible worksheet from at least one source.
- Printed/PDF view resembles the PDF sample, not a dashboard card page.

### Task 6: Avatar Files Max 25 KB

**Files:** `actions/update_profile.php`, `profile.php`, `settings.php`, `tests/static_compliance_check.py`

**Why:** PDF item 6 requires user avatars not to take more than 25 KB on server.

**Steps:**
- [ ] Add helper flow to encode WebP repeatedly until `filesize <= 25 * 1024`.
- [ ] Try quality reduction first, then smaller dimensions if needed.
- [ ] If still too large, delete temp output and show a clear upload error.
- [ ] Keep safety scan before saving.
- [ ] Update upload helper copy to mention compression/25 KB server cap.

**Acceptance:**
- Saved avatar file is never above 25 KB.
- Existing path validation still accepts new avatars.

### Task 7: Flashcards Rebuild

**Files:** `flashcards.php`, `assets/css/flashcards.css`, `assets/js/flashcards.js`, `tests/static_compliance_check.py`

**Why:** PDF item 7 says rebuild flashcards completely, redesign look and behavior, students cannot add their own cards, teachers can send requests.

**Steps:**
- [ ] Keep data sources: `data/dictionary.json` + eligible DB questions.
- [ ] Keep POST request path only for `teacher`, `admin`, `dyrektor`.
- [ ] Replace page with redesigned study workspace: deck, filters, queue/list, progress, keyboard/swipe controls, session stats, teacher request panel.
- [ ] Move CSS to `assets/css/flashcards.css`.
- [ ] Move JS to `assets/js/flashcards.js`, with server-injected card JSON.
- [ ] Ensure no student-facing custom-add form or local custom-card storage remains.
- [ ] Browser QA: student/guest sees no request form; teacher sees request form; study controls work.

**Acceptance:**
- No `Moje fiszki`, `Własna fiszka`, `customKey`, or `addCustomCard` surface.
- Teachers can submit moderated requests.
- Students only study approved/generated cards.

### Task 8: Test Configurator Time Slider

**Files:** `test.php`, `assets/css/style.css`, `tests/static_compliance_check.py`

**Why:** PDF item 8 says the time slider styling broke.

**Steps:**
- [ ] Remove/override conflicting compact CSS that collapses `.time-slider-panel` padding/open state.
- [ ] Style range input track/thumb for light and dark mode.
- [ ] Keep value bubble synced for total time and per-question custom time.
- [ ] Browser QA at desktop and mobile widths.

**Acceptance:**
- Opening `Własny` or `Własny / pyt.` shows a readable slider with stable spacing.
- Slider thumb/track are visible in light and dark mode.

### Task 9: Empty PDF Item

**Files:** none

**Why:** PDF page 2 contains numbered item `9.` with no text or image after it.

**Steps:**
- [ ] Re-check rendered page 2 before final report.
- [ ] State in final that point 9 was reviewed and has no actionable content.

**Acceptance:**
- Point 9 is not ignored; it is explicitly closed as empty.

## Web Release Gates

| Journey | Lab check | Stop condition |
| --- | --- | --- |
| Registration | Type taken login, accept suggestion | No suggestion or forbidden copy remains |
| Login | Email identifier login path | Auth query is username-only |
| Test setup | Toggle time modes and move sliders | Slider hidden, clipped, or unreadable |
| Social | Desktop and mobile layout | moved cards still in sidebar or overflow |
| Generator | Generate and print worksheet | blank print window, app chrome in print, missing images |
| Avatar | Upload large image | saved file > 25 KB |
| Flashcards | Study, rate, filter, teacher request | student can add/request card; controls inert |

## Security Notes

- Registration availability endpoint must not leak extra user data; return only availability and suggestions.
- Login remains rate-limited by existing username/email identity path.
- Avatar processing must validate MIME, dimensions, safety scan, path pattern, and size before DB update.
- Worksheet printable window must escape title and question content.

## Risks

- Generator "does not work" may have a browser-specific failure; browser QA must identify concrete breakage before marking fixed.
- Mode label rename must be scoped; a blind global replace could damage teacher exam/session wording.
- Flashcards rebuild is broad; extract CSS/JS to reduce future maintenance risk.

## Execution Order

1. Registration + email login verification.
2. Mode labels.
3. Social layout.
4. Avatar compression.
5. Time slider.
6. Generator repair/styling.
7. Flashcards rebuild.
8. Static tests, PHP lint, Browser QA, security spot-check, PDF point 9 closure.

