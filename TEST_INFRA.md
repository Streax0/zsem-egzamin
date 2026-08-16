# E2E Test Infra: Database Schema Consolidation & Optimization

## Test Philosophy
- Opaque-box, requirement-driven verification covering schema integrity, foreign key ordering, deduplication, runtime DDL isolation, and connection performance.
- Methodology: Static AST / Lexical scanning + Category-Partition + Boundary Value Analysis + Runtime Execution.

## Feature Inventory & Test Mapping
| # | Feature | Requirement Source | Tier 1 (Unit/Feature) | Tier 2 (Boundary) | Tier 3 (Integration/Pairwise) | Tier 4 (Scenario) |
|---|---------|-------------------|:---------------------:|:-----------------:|:-----------------------------:|:-----------------:|
| 1 | Full Schema Table Inclusions | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | ✓ |
| 2 | Schema Deduplication & Drops | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | ✓ |
| 3 | FK Topological Ordering | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | ✓ |
| 4 | Inline DDL Guarding | ORIGINAL_REQUEST R2 | 5 | 5 | ✓ | ✓ |
| 5 | DB Connection & Pooling | ORIGINAL_REQUEST R3 | 5 | 5 | ✓ | ✓ |
| 6 | Query Caching & Memoization | ORIGINAL_REQUEST R3 | 5 | 5 | ✓ | ✓ |

## Test Architecture
- **Schema Test Suite**: `tests/test_schema_syntax.py` and `tests/test_schema_comprehensive.py`.
  - Verifies exact table count (62 tables).
  - Verifies zero duplicate `CREATE TABLE` statements.
  - Verifies presence of all required tables (`app_statuses`, `app_status_deliveries`, `user_passkeys`, `registration_attempts`, all 8 course tables).
  - Verifies all foreign key parent tables are declared BEFORE child tables (topological consistency).
  - Verifies `DROP TABLE IF EXISTS` header covers all tables in exact reverse topological order.
  - Verifies engine (`ENGINE=InnoDB`) and collation (`DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`) consistency.
- **Runtime Guard Test Suite**: `tests/runtime_schema_guard_runtime.php` & `tests/static_compliance_check.py`.
  - Verifies `appRuntimeSchemaUpdatesEnabled()` blocks web requests.
  - Verifies `user/social.php` has zero unguarded DDL queries.
- **Connection & Performance Suite**: `tests/db_connection_config_runtime.php` & `tests/db_read_performance_runtime.php`.
  - Verifies PDO options, timeouts, DSN validation, and persistent connection flags.
  - Benchmarks cached query execution and in-memory static memoization.

## Real-World Application Scenarios (Tier 4)
| # | Scenario | Features Exercised | Complexity |
|---|----------|--------------------|------------|
| 1 | Clean Database Provisioning from `full_schema.sql` | F1, F2, F3 | High |
| 2 | High-throughput web request handling with zero DDL overhead | F4, F5, F7 | High |
| 3 | Rapid repeated query execution via `dbQueryCached` memoization | F6, F7 | Medium |
| 4 | Administrative CLI schema migration with `APP_RUNTIME_SCHEMA_UPDATES=true` | F4, F5 | Medium |
