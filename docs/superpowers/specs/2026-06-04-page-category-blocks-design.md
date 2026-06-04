# Page Category Blocks Design

## Goal

Administrators need a safe way to disable whole page categories for selected roles from the admin panel. Affected users should see a blocking popup instead of the requested page. Administrators and directors should retain emergency access, but they should see a visible notice that the category is currently disabled for other roles.

## Scope

The feature covers authenticated, protected application pages that already pass through `requireLogin()`. Public legal/contact pages, login, registration, logout actions, AJAX endpoints, and the admin panel itself remain outside blocking enforcement.

Initial blockable categories:

- `dashboard`: dashboard
- `tests`: tests and question test flow
- `categories`: qualification/category pages
- `practice`: practical exam sheets
- `lessons`: lesson pages
- `ranking`: ranking pages
- `dictionary`: dictionary
- `flashcards`: flashcards
- `sandbox`: sandbox
- `social`: community, friends, and duels
- `progress`: statistics/progress
- `missions`: goals and missions
- `history`: result history and result detail
- `exam`: live exam/sprawdzian join, lobby, take, finished
- `teacher`: teacher panel, online exams, generated/own tests

## Data Model

Create `feature_page_blocks` with one active block per category:

- `id`
- `category_key`
- `title`
- `body`
- `target_roles` as JSON array of role keys
- `is_active`
- `created_by`
- `disabled_at`
- `updated_at`
- `ended_at`

`created_by` links to the admin user when available. The table is created automatically by `ensurePlatformEnhancements()` and included in `full_schema.sql`.

## Admin UI

Add a card in `admin.php` under Moderation/System:

- category select
- role checkboxes for `user`, `teacher`, and `wujek_luki`
- title field
- description field
- submit button to disable category
- active block list with category label, roles, timestamp, admin label, and disable/remove action

POST actions use the existing admin CSRF token and `logAdminAction()`.

## Enforcement

`requireLogin()` calls a helper after session and MFA checks:

1. Resolve current request path into a category key.
2. Load active block for that category.
3. If current role is in `target_roles`, render a minimal full-page block screen with Bootstrap modal and stop execution.
4. If current role is `admin` or `dyrektor`, do not block access, but store the block details for a one-way notice rendered in `includes/topbar.php`.
5. If no active block applies, continue normally.

This keeps enforcement centralized and avoids copying guards into many pages.

## Popup Content

Affected users see:

- title set by admin
- description set by admin
- category label
- disabled by admin display name and role
- date and time
- roles affected

The modal has no navigation that could accidentally expose the blocked page content. The fallback screen remains readable if JavaScript fails.

Admins and directors see a non-blocking notice with:

- category label
- disabled by
- date/time
- affected roles
- short message that the current category is globally disabled for selected roles

## Sandbox Element Blocks

The admin panel also supports disabling selected sandbox elements for selected roles. This is separate from disabling the whole `sandbox` category.

Initial sandbox blockable elements:

- whole sandbox tools: logic gates, PSU calculator, subnet calculator, router lab, number systems, Ohm calculator, live HTML/CSS/JS
- logic workbench components: switch A, switch B, constant 1, constant 0, BUFFER, NOT, AND, NAND, OR, NOR, XOR, XNOR, LED, truth table

Create `sandbox_element_blocks` with:

- `id`
- `element_key`
- `title`
- `body`
- `target_roles` as JSON array of role keys
- `is_active`
- `created_by`
- `disabled_at`
- `updated_at`
- `ended_at`

Affected users do not receive clickable/draggable controls for disabled sandbox elements. Disabled controls render as locked buttons with the admin-provided title/description available as context. If a whole sandbox tool is disabled, the tool card/tab is disabled and direct navigation to `sandbox.php?tool=...` shows a blocking notice instead of the workbench.

Admins and directors retain access to disabled sandbox tools/elements, but receive a one-way notice that the item is disabled for selected roles.

## Safety

- Admin panel and admin request pages stay reachable.
- `admin` and `dyrektor` cannot be locked out by this feature.
- Inputs are trimmed, length-limited, escaped on output, and persisted via prepared statements.
- Unknown categories and roles are rejected.
- JSON role data is decoded defensively.
- Audit log records create/end actions with category and target roles.
- Sandbox element blocks are enforced server-side by not rendering usable controls for affected roles; direct tool URLs are handled before workbench rendering.

## Testing

Static tests cover:

- schema and auto-create table exist
- helper names exist
- `requireLogin()` calls the guard
- admin POST actions exist
- admin UI card exists
- topbar admin bypass notice exists
- sandbox element block table, helper names, admin UI, and sandbox enforcement markers exist
- native dialogs are not introduced

Syntax verification runs on modified PHP files.
