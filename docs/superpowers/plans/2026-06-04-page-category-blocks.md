# Page Category Blocks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin-managed category/page blocking plus sandbox element blocking with role targeting, blocking popup for affected users, and bypass notice for admins/directors.

**Architecture:** Store one active block per page category in `feature_page_blocks` and one active block per sandbox item in `sandbox_element_blocks`. Resolve the current script path to a category in helper functions, enforce pages centrally from `requireLogin()`, and enforce sandbox items in `sandbox.php` before rendering tools/components.

**Tech Stack:** PHP 8, PDO/MySQL, Bootstrap 5, existing `admin.php`, `includes/auth.php`, `includes/functions.php`, `includes/topbar.php`, `full_schema.sql`, Python static tests.

---

## File Structure

- Modify `tests/static_compliance_check.py`: add regression checks before implementation.
- Modify `includes/functions.php`: table creation, category registry, CRUD helpers, block resolution, blocking-page renderer.
- Modify `includes/auth.php`: call enforcement after auth/MFA in `requireLogin()`.
- Modify `includes/topbar.php`: render admin/director bypass notice when set by the guard.
- Modify `admin.php`: POST actions, data loading, UI card for blocks.
- Modify `sandbox.php`: hide/disable blocked tools and logic components for targeted roles; show notices for admin/director bypass.
- Modify `full_schema.sql`: include table in full install schema.

## Tasks

### Task 1: Tests First

**Files:**
- Modify: `tests/static_compliance_check.py`

- [ ] Add a test named `test_page_category_blocks_admin_guard_and_schema`.
- [ ] Assert these strings exist: `feature_page_blocks`, `getFeaturePageBlockCategories`, `createFeaturePageBlock`, `endFeaturePageBlock`, `resolveFeaturePageBlockForRequest`, `enforceFeaturePageBlockForCurrentRequest`, `renderFeaturePageBlockScreen`, `feature_block_notice`, `create_feature_page_block`, `end_feature_page_block`, `admin-page-blocks`, and `pageBlockAdminNotice`.
- [ ] Assert these strings exist: `sandbox_element_blocks`, `getSandboxBlockableElements`, `createSandboxElementBlock`, `endSandboxElementBlock`, `resolveSandboxElementBlock`, `getSandboxElementBlockMapForRole`, `create_sandbox_element_block`, `end_sandbox_element_block`, `admin-sandbox-blocks`, `data-sandbox-element-key`, and `sandboxElementAdminNotice`.
- [ ] Run `python -m pytest tests/static_compliance_check.py -q`.
- [ ] Expected RED: the new test fails because implementation strings are missing.

### Task 2: Helpers And Schema Runtime

**Files:**
- Modify: `includes/functions.php`
- Modify: `full_schema.sql`

- [ ] Add `CREATE TABLE IF NOT EXISTS feature_page_blocks` in `ensurePlatformEnhancements()`.
- [ ] Add `feature_page_blocks` to `full_schema.sql`.
- [ ] Add category registry and labels.
- [ ] Add active block CRUD helpers.
- [ ] Add request resolution and role matching helpers.
- [ ] Add `renderFeaturePageBlockScreen()` using escaped output.
- [ ] Add `CREATE TABLE IF NOT EXISTS sandbox_element_blocks`.
- [ ] Add sandbox element registry and active-block helpers.

### Task 3: Central Enforcement

**Files:**
- Modify: `includes/auth.php`
- Modify: `includes/topbar.php`

- [ ] Call `enforceFeaturePageBlockForCurrentRequest()` after MFA checks in `requireLogin()`.
- [ ] For target roles, stop with the blocking page.
- [ ] For `admin` and `dyrektor`, set `$_SESSION['feature_block_notice']`.
- [ ] Render `pageBlockAdminNotice` in `includes/topbar.php` and clear the notice after display.

### Task 4: Admin Panel

**Files:**
- Modify: `admin.php`

- [ ] Add POST case `create_feature_page_block`.
- [ ] Add POST case `end_feature_page_block`.
- [ ] Add POST case `create_sandbox_element_block`.
- [ ] Add POST case `end_sandbox_element_block`.
- [ ] Load active blocks and categories before render.
- [ ] Add `admin-page-blocks` card in the Moderation/System grid.
- [ ] Add `admin-sandbox-blocks` card in the Moderation/System grid.
- [ ] Reuse existing CSRF and `logAdminAction()`.

### Task 5: Sandbox Element Enforcement

**Files:**
- Modify: `sandbox.php`

- [ ] Load sandbox block maps for the current role.
- [ ] Disable whole tool cards/tabs for affected roles.
- [ ] If direct navigation targets a disabled tool, render a blocking notice in the sandbox content area.
- [ ] Render blocked logic components as disabled locked buttons using `data-sandbox-element-key`.
- [ ] For `admin` and `dyrektor`, keep elements enabled and display `sandboxElementAdminNotice`.

### Task 6: Verification

**Files:**
- Test: `tests/static_compliance_check.py`
- Check: modified PHP files

- [ ] Run `python -m pytest tests/static_compliance_check.py -q`.
- [ ] Run `php -l includes/functions.php`.
- [ ] Run `php -l includes/auth.php`.
- [ ] Run `php -l includes/topbar.php`.
- [ ] Run `php -l admin.php`.
- [ ] Run `php -l sandbox.php`.
- [ ] Inspect `git diff --check`.
