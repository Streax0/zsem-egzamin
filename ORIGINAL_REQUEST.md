# Original User Request

## Initial Request — 2026-08-16T16:36:40Z

Audit all SQL DDL creation queries across the codebase, consolidate them into `full_schema.sql`, remove redundant duplicate schema declarations, and optimize database connection handling and query management in `config/db.php`.

Working directory: c:\Users\damia\OneDrive\Pulpit\stronammmmmmmm\public_html
Integrity mode: development

## Requirements

### R1. SQL Schema Consolidation and Deduplication
Scan all repository files for SQL table creation and schema definitions. Add all missing tables (`app_statuses`, `app_status_deliveries`, `user_passkeys`, `registration_attempts`, and all course-related tables) into `full_schema.sql`. Deduplicate repeated table definitions (such as `lessons` and `luki_spins`). Ensure foreign key dependencies and `DROP TABLE IF EXISTS` cascading order are completely consistent.

### R2. Cleanup of Inline DDL Queries
Ensure runtime table creation in PHP files is properly encapsulated and guarded by `appRuntimeSchemaUpdatesEnabled()` so standard web requests do not execute redundant DDL checks. Remove ad-hoc, uncoordinated inline table creation queries from isolated endpoint scripts.

### R3. Database Connection and Management Optimization
Optimize `config/db.php` connection parameters (connection pooling / persistent connection support, timeouts, charset handling, SQL mode setting) and ensure query execution and caching utilities operate with minimal overhead.

## Acceptance Criteria

### Schema Integrity & Syntax
- [ ] `full_schema.sql` contains all database tables required by the application without omissions.
- [ ] `full_schema.sql` contains zero duplicate `CREATE TABLE` statements.
- [ ] `full_schema.sql` imports into MySQL without syntax errors or foreign key constraint violations.
- [ ] All table fields, data types, defaults, and indexes in `full_schema.sql` match application code expectations.

### Architecture & Connection Performance
- [ ] Standalone scripts no longer execute ad-hoc DDL queries outside centralized schema handlers.
- [ ] Connection handling in `config/db.php` supports optimal performance configurations without compromising security.
