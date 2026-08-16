# Project: Database Schema Consolidation, DDL Guarding & Connection Optimization

## Architecture
- **Database Engine**: MySQL 8.0+ / MariaDB 10.4+, InnoDB, `utf8mb4` charset, `utf8mb4_unicode_ci` collation.
- **Connection Architecture**: Single, shared PDO instance initialized in `config/db.php` with hardened DSN, parameterized queries (`ATTR_EMULATE_PREPARES => false`), exception error mode (`ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`), configurable persistent connections (`MYSQL_PERSISTENT`), and memory-cached query execution (`dbQueryCached`).
- **Schema Management Architecture**: Centralized declarative schema in `full_schema.sql`. Runtime schema mutation is strictly isolated and guarded by `appRuntimeSchemaUpdatesEnabled()`, preventing any DDL execution during standard web HTTP requests.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Full Schema Consolidation | Consolidate all 62 application tables into `full_schema.sql` with consistent topological CREATE and reverse DROP order | M1 | Survey E1 |
| 2 | Schema Deduplication | Remove duplicate `CREATE TABLE` definitions (`lessons`, `luki_spins`) and merge trailing `ALTER TABLE` migrations into canonical table DDL | M1 | Survey E1 |
| 3 | Missing Table & Column Inclusion | Add `app_statuses`, `app_status_deliveries`, `user_passkeys`, `registration_attempts`, and all course tables, plus missing columns on `users` and `courses` | M1 | Survey E1 |
| 4 | Inline DDL Cleanup in Endpoints | Remove uncoordinated inline `ALTER TABLE` in `user/social.php` and optimize raw `SHOW TABLES`/`SHOW COLUMNS` probes | M2 | Survey E2 |
| 5 | Runtime Schema Guard Verification | Ensure all runtime migration helpers in `includes/functions.php`, `includes/auth.php`, `admin/index.php`, etc., are strictly guarded | M2 | Survey E2 |
| 6 | Database Connection Optimization | Optimize `config/db.php` PDO options with configurable persistent connection support (`MYSQL_PERSISTENT`) and streamlined session configuration | M3 | Survey E3 |
| 7 | Query Caching & Memoization | Implement L1 static in-memory memoization in `dbQueryCached` for zero-overhead intra-request query reuse | M3 | Survey E3 |
| 8 | E2E Testing & Static Compliance | Build comprehensive automated test suite verifying schema syntax, FK dependencies, deduplication, runtime guards, and DB performance | M4 | Survey E1-E3 |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | SQL Schema Consolidation | Consolidate `full_schema.sql` (62 tables, 0 duplicates, topological order, full columns) | None | DONE |
| 2 | Inline DDL Cleanup | Clean `user/social.php`, sanitize metadata probes, verify runtime schema guards | M1 | DONE |
| 3 | DB Connection & Query Optimization | Update `config/db.php` with persistent pooling support, init command tuning, L1 query cache | M1, M2 | DONE |
| 4 | E2E Testing & Final Verification | Execute complete E2E test suite (Tiers 1-4) and publish `TEST_READY.md` | M1, M2, M3 | DONE |

## Interface Contracts
### `full_schema.sql` Contract
- Declares all 62 application tables with `CREATE TABLE IF NOT EXISTS ... ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- `DROP TABLE IF EXISTS` header includes all 62 tables in exact reverse topological order of foreign key dependencies.
- Zero duplicate `CREATE TABLE` statements.
- `courses` declared before `user_certificates` and course items.

### `appRuntimeSchemaUpdatesEnabled()` Contract
- Signature: `appRuntimeSchemaUpdatesEnabled(): bool`
- Semantics: Returns `true` ONLY when `defined('APP_RUNTIME_SCHEMA_UPDATES') && APP_RUNTIME_SCHEMA_UPDATES === true && PHP_SAPI === 'cli'`.
- All schema modification functions (`ensurePlatformEnhancements`, `dbAddColumnIfMissing`, etc.) return immediately if this returns `false`.

### `config/db.php` Contract
- Exposes `$pdo` PDO instance.
- Sets hardened PDO options: `ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `ATTR_EMULATE_PREPARES => false`, `ATTR_PERSISTENT => bool`, `ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`.
- `dbQueryCached(PDO $pdo, string $sql, array $params = [], int $ttl = 300)` returns array result with L1 static intra-request memoization and L2 file/APCu cache.

## Code Layout
- `full_schema.sql` — Canonical MySQL DDL definition (62 tables).
- `config/db.php` — Database connection configuration, PDO initialization, schema guard definition, cached query handler.
- `user/social.php` — Social profile endpoint (cleaned of inline DDL).
- `includes/functions.php` — Centralized application functions and guarded schema helpers.
- `includes/auth.php` — Authentication functions and guarded attempt tables.
- `tests/` — Automated test suites:
  - `tests/test_schema_syntax.py` — Schema DDL validation, table count, FK dependency check, duplicate check.
  - `tests/runtime_schema_guard_runtime.php` — Runtime schema guard execution test.
  - `tests/db_connection_config_runtime.php` — DB connection config test.
  - `tests/db_read_performance_runtime.php` — DB query performance test.
  - `tests/static_compliance_check.py` — Static and runtime compliance test suite.
