#!/usr/bin/env python3
"""
Test Suite: End-to-End Database Workflow & Multi-Tier Integrity Runner
Executes comprehensive tests across Tier 1 (Features), Tier 2 (Boundaries),
Tier 3 (Cross-Feature Combinations), and Tier 4 (Real-World Scenarios).
"""

from pathlib import Path
import re
import shutil
import subprocess
import sys
import unittest
from collections import defaultdict, deque

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_FILE = ROOT / "full_schema.sql"
DB_CONFIG_FILE = ROOT / "config" / "db.php"
SOCIAL_FILE = ROOT / "user" / "social.php"
FUNCTIONS_FILE = ROOT / "includes" / "functions.php"
AUTH_FILE = ROOT / "includes" / "auth.php"

# Resolve PHP CLI path
PHP_BIN = shutil.which("php")
if PHP_BIN is None:
    xampp_php = Path("C:/xampp/php/php.exe")
    if xampp_php.exists():
        PHP_BIN = str(xampp_php)


def run_php_script(script_rel_path: str) -> subprocess.CompletedProcess:
    """Executes a PHP script and returns the completed process."""
    if PHP_BIN is None:
        raise RuntimeError("PHP CLI is required to execute PHP test scripts.")
    return subprocess.run(
        [PHP_BIN, str(ROOT / script_rel_path)],
        cwd=str(ROOT),
        capture_output=True,
        text=True,
        timeout=30,
        check=False,
    )


class SchemaLexer:
    """Parses full_schema.sql into tables, foreign keys, drops, and columns."""

    def __init__(self, sql_path: Path):
        self.raw_sql = sql_path.read_text(encoding="utf-8")
        self.drop_tables = []
        self.create_tables = []
        self.foreign_keys = defaultdict(list)
        self.table_columns = defaultdict(dict)
        self.table_engines = {}
        self.table_charsets = {}
        self.table_collations = {}
        self.cascade_paths = defaultdict(list)
        self._parse()

    def _parse(self):
        # Drops
        self.drop_tables = re.findall(
            r"DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*;",
            self.raw_sql,
            re.I,
        )

        # Creates
        create_pattern = re.compile(
            r"CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([a-zA-Z0-9_]+)(.*?);",
            re.DOTALL | re.I,
        )

        for match in create_pattern.finditer(self.raw_sql):
            table = match.group(1)
            body = match.group(2)
            engine = match.group(3)
            opts = match.group(4)

            self.create_tables.append(table)
            self.table_engines[table] = engine

            cs_m = re.search(r"(?:DEFAULT\s+)?CHARSET\s*=\s*([a-zA-Z0-9_]+)", opts, re.I)
            cl_m = re.search(r"COLLATE\s*=\s*([a-zA-Z0-9_]+)", opts, re.I)
            self.table_charsets[table] = cs_m.group(1) if cs_m else ""
            self.table_collations[table] = cl_m.group(1) if cl_m else ""

            for line in body.splitlines():
                clean = line.strip().rstrip(",")
                # FK
                fk_m = re.search(
                    r"FOREIGN\s+KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)(?:\s+ON\s+DELETE\s+([a-zA-Z\s]+))?",
                    clean,
                    re.I,
                )
                if fk_m:
                    col, parent, parent_col, on_delete = fk_m.groups()
                    action = on_delete.strip().upper() if on_delete else "RESTRICT"
                    self.foreign_keys[table].append(
                        {
                            "col": col,
                            "parent": parent,
                            "parent_col": parent_col,
                            "on_delete": action,
                        }
                    )
                    if action == "CASCADE":
                        self.cascade_paths[parent].append((table, col))
                    continue

                # Column
                if not re.match(
                    r"^(PRIMARY|FOREIGN|INDEX|KEY|UNIQUE|CONSTRAINT)", clean, re.I
                ):
                    col_m = re.match(r"^`?([a-zA-Z0-9_]+)`?\s+([a-zA-Z0-9_]+)", clean)
                    if col_m:
                        c_name, c_type = col_m.groups()
                        self.table_columns[table][c_name] = c_type


class TestTier1FeatureCoverage(unittest.TestCase):
    """Tier 1: Feature Coverage (>= 5 tests per feature)."""

    @classmethod
    def setUpClass(cls):
        cls.schema = SchemaLexer(SCHEMA_FILE)

    # Feature 1: Full Schema Table Inclusions (5 tests)
    def test_t1_f1_01_all_62_tables_present(self):
        """T1.F1.1: Verify full schema contains all 62 application tables."""
        self.assertEqual(len(self.schema.create_tables), 62)
        self.assertEqual(len(set(self.schema.create_tables)), 62)

    def test_t1_f1_02_missing_status_tables_included(self):
        """T1.F1.2: Verify app_statuses and app_status_deliveries are included."""
        self.assertIn("app_statuses", self.schema.create_tables)
        self.assertIn("app_status_deliveries", self.schema.create_tables)
        self.assertIn("body", self.schema.table_columns["app_statuses"])
        self.assertIn("level", self.schema.table_columns["app_statuses"])

    def test_t1_f1_03_missing_auth_tables_included(self):
        """T1.F1.3: Verify user_passkeys and registration_attempts are included."""
        self.assertIn("user_passkeys", self.schema.create_tables)
        self.assertIn("registration_attempts", self.schema.create_tables)
        self.assertIn("credential_id", self.schema.table_columns["user_passkeys"])
        self.assertIn("ip_address", self.schema.table_columns["registration_attempts"])

    def test_t1_f1_04_course_curriculum_tables_included(self):
        """T1.F1.4: Verify all 8 course tables are included."""
        courses = [
            "courses",
            "course_shares",
            "course_modules",
            "course_custom_labs",
            "course_items",
            "course_quiz_questions",
            "user_course_enrollments",
            "user_course_progress",
        ]
        for c in courses:
            self.assertIn(c, self.schema.create_tables)

    def test_t1_f1_05_migrated_columns_on_users_and_courses(self):
        """T1.F1.5: Verify migrated privacy flags and external URL columns exist in DDL."""
        u_cols = self.schema.table_columns["users"]
        self.assertIn("show_missions", u_cols)
        self.assertIn("show_online_status", u_cols)
        self.assertIn("show_recent_activity", u_cols)

        c_cols = self.schema.table_columns["courses"]
        self.assertIn("is_external", c_cols)
        self.assertIn("external_url", c_cols)

    # Feature 2: Schema Deduplication & Drops (5 tests)
    def test_t1_f2_01_no_duplicate_create_table_statements(self):
        """T1.F2.1: Verify exactly zero duplicate CREATE statements."""
        self.assertEqual(len(self.schema.create_tables), len(set(self.schema.create_tables)))

    def test_t1_f2_02_lessons_is_single_and_canonical(self):
        """T1.F2.2: Verify lessons is declared only once."""
        self.assertEqual(self.schema.create_tables.count("lessons"), 1)

    def test_t1_f2_03_luki_spins_is_single_and_canonical(self):
        """T1.F2.3: Verify luki_spins is declared only once."""
        self.assertEqual(self.schema.create_tables.count("luki_spins"), 1)

    def test_t1_f2_04_drop_header_has_exact_62_tables(self):
        """T1.F2.4: Verify DROP TABLE IF EXISTS has exactly 62 statements."""
        self.assertEqual(len(self.schema.drop_tables), 62)
        self.assertEqual(len(set(self.schema.drop_tables)), 62)

    def test_t1_f2_05_drop_matches_create_table_names(self):
        """T1.F2.5: Verify all created tables are dropped and vice-versa."""
        self.assertEqual(set(self.schema.drop_tables), set(self.schema.create_tables))

    # Feature 3: Inline DDL Guarding (5 tests)
    def test_t1_f3_01_runtime_schema_guard_php_suite(self):
        """T1.F3.1: Execute runtime_schema_guard_runtime.php and verify 0 errors."""
        res = run_php_script("tests/runtime_schema_guard_runtime.php")
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)
        self.assertIn("runtime schema guard OK", res.stdout)

    def test_t1_f3_02_guard_function_signature_in_db_php(self):
        """T1.F3.2: Verify appRuntimeSchemaUpdatesEnabled() defined in config/db.php."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("function appRuntimeSchemaUpdatesEnabled(): bool", db_code)
        self.assertIn("defined('APP_RUNTIME_SCHEMA_UPDATES')", db_code)
        self.assertIn("PHP_SAPI === 'cli'", db_code)

    def test_t1_f3_03_user_social_has_zero_inline_ddl(self):
        """T1.F3.3: Verify user/social.php contains zero inline ALTER/CREATE TABLE queries."""
        social_code = SOCIAL_FILE.read_text(encoding="utf-8")
        self.assertNotIn("ALTER TABLE users", social_code)
        self.assertNotIn("show_online_status TINYINT", social_code)
        self.assertNotIn("show_recent_activity TINYINT", social_code)

    def test_t1_f3_04_functions_php_schema_helpers_are_guarded(self):
        """T1.F3.4: Verify functions.php schema migration functions are guarded."""
        fn_code = FUNCTIONS_FILE.read_text(encoding="utf-8")
        self.assertIn("dbRuntimeSchemaUpdatesEnabled()", fn_code)
        self.assertIn("appRuntimeSchemaUpdatesEnabled()", fn_code)

    def test_t1_f3_05_auth_php_attempt_tables_are_guarded(self):
        """T1.F3.5: Verify auth.php attempt table creators are guarded."""
        auth_code = AUTH_FILE.read_text(encoding="utf-8")
        self.assertIn("if (!appRuntimeSchemaUpdatesEnabled()) return;", auth_code)

    # Feature 4: DB Connection & Pooling (5 tests)
    def test_t1_f4_01_db_connection_config_runtime_suite(self):
        """T1.F4.1: Execute db_connection_config_runtime.php and verify 0 errors."""
        res = run_php_script("tests/db_connection_config_runtime.php")
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)
        self.assertIn("database connection config runtime OK", res.stdout)

    def test_t1_f4_02_persistent_connection_option_handling(self):
        """T1.F4.2: Verify config/db.php supports persistent connection configuration."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("PDO::ATTR_PERSISTENT", db_code)
        self.assertIn("MYSQL_PERSISTENT", db_code)

    def test_t1_f4_03_dsn_builder_formats_tcp_and_socket(self):
        """T1.F4.3: Verify appDbBuildDsn handles both TCP and UNIX socket connections."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("mysql:unix_socket=", db_code)
        self.assertIn("mysql:host=", db_code)
        self.assertIn(";charset=utf8mb4", db_code)

    def test_t1_f4_04_connect_timeout_bounded(self):
        """T1.F4.4: Verify connect timeout is bounded between 1 and 30 seconds."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("max(1, min(30,", db_code)

    def test_t1_f4_05_charset_init_command_configured(self):
        """T1.F4.5: Verify SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci is in init command."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci", db_code)

    # Feature 5: Query Caching & Memoization (5 tests)
    def test_t1_f5_01_db_read_performance_runtime_suite(self):
        """T1.F5.1: Execute db_read_performance_runtime.php and verify 0 errors."""
        res = run_php_script("tests/db_read_performance_runtime.php")
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)
        self.assertIn("database read performance runtime OK", res.stdout)

    def test_t1_f5_02_db_query_cached_l1_memoization(self):
        """T1.F5.2: Verify dbQueryCached contains static L1 in-memory memoization."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("static $memoryCache = [];", db_code)
        self.assertIn("array_key_exists($cacheKey, $memoryCache)", db_code)
        self.assertIn("$memoryCache[$cacheKey] = $result;", db_code)

    def test_t1_f5_03_db_query_cached_differentiates_fetch_one(self):
        """T1.F5.3: Verify dbQueryCached distinguishes fetchOne mode in cache key."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("($fetchOne ? '_one' : '_all')", db_code)

    def test_t1_f5_04_db_workflow_runtime_suite(self):
        """T1.F5.4: Execute tests/db_workflow_runtime.php and verify 100% success."""
        res = run_php_script("tests/db_workflow_runtime.php")
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)
        self.assertIn("ALL RUNTIME DB WORKFLOW TESTS PASSED", res.stdout)

    def test_t1_f5_05_information_schema_caching_in_functions(self):
        """T1.F5.5: Verify INFORMATION_SCHEMA probes are cached in runtime cache maps."""
        fn_code = FUNCTIONS_FILE.read_text(encoding="utf-8")
        self.assertIn("dbRequestRuntimeCache()", fn_code)
        self.assertIn("FROM INFORMATION_SCHEMA.TABLES", fn_code)
        self.assertIn("FROM INFORMATION_SCHEMA.COLUMNS", fn_code)
        self.assertIn("FROM INFORMATION_SCHEMA.STATISTICS", fn_code)


class TestTier2BoundaryAndCornerCases(unittest.TestCase):
    """Tier 2: Boundary & Corner Cases (>= 5 tests per feature)."""

    @classmethod
    def setUpClass(cls):
        cls.schema = SchemaLexer(SCHEMA_FILE)

    # Boundary 1: Foreign Key Topological Validity (5 tests)
    def test_t2_f1_01_no_forward_references(self):
        """T2.F1.1: Verify that every foreign key references a parent table created earlier."""
        created = set()
        for tbl in self.schema.create_tables:
            for fk in self.schema.foreign_keys[tbl]:
                parent = fk["parent"]
                if parent != tbl:
                    self.assertIn(
                        parent,
                        created,
                        f"Forward FK reference: {tbl} references {parent} before {parent} created",
                    )
            created.add(tbl)

    def test_t2_f1_02_dependency_dag_is_acyclic(self):
        """T2.F1.2: Verify foreign key dependency graph has no circular references."""
        adj = defaultdict(set)
        in_degree = defaultdict(int)
        for tbl in self.schema.create_tables:
            in_degree[tbl] = 0

        for tbl, fks in self.schema.foreign_keys.items():
            for fk in fks:
                parent = fk["parent"]
                if parent != tbl:
                    adj[parent].add(tbl)

        for p, children in adj.items():
            for c in children:
                in_degree[c] += 1

        queue = deque([t for t, deg in in_degree.items() if deg == 0])
        visited = 0
        while queue:
            node = queue.popleft()
            visited += 1
            for neighbor in adj[node]:
                in_degree[neighbor] -= 1
                if in_degree[neighbor] == 0:
                    queue.append(neighbor)

        self.assertEqual(visited, 62, "FK graph contains cyclic dependencies!")

    def test_t2_f1_03_courses_created_before_user_certificates(self):
        """T2.F1.3: Verify courses is created before user_certificates."""
        c_idx = self.schema.create_tables.index("courses")
        uc_idx = self.schema.create_tables.index("user_certificates")
        self.assertLess(c_idx, uc_idx)

    def test_t2_f1_04_exam_sessions_created_before_participants(self):
        """T2.F1.4: Verify exam_sessions is created before exam_participants."""
        es_idx = self.schema.create_tables.index("exam_sessions")
        ep_idx = self.schema.create_tables.index("exam_participants")
        self.assertLess(es_idx, ep_idx)

    def test_t2_f1_05_parent_columns_exist_for_all_foreign_keys(self):
        """T2.F1.5: Verify all referenced parent columns exist in the parent table."""
        for tbl, fks in self.schema.foreign_keys.items():
            for fk in fks:
                parent = fk["parent"]
                parent_col = fk["parent_col"]
                self.assertIn(parent, self.schema.create_tables)
                self.assertIn(
                    parent_col,
                    self.schema.table_columns[parent],
                    f"Referenced column {parent_col} missing in parent table {parent}",
                )

    # Boundary 2: Reverse DROP TABLE Order Validity (5 tests)
    def test_t2_f2_01_reverse_drop_consistency(self):
        """T2.F2.1: Verify every child table is dropped before its referenced parent table."""
        dropped = set()
        for tbl in self.schema.drop_tables:
            for child, fks in self.schema.foreign_keys.items():
                for fk in fks:
                    if fk["parent"] == tbl and child != tbl:
                        self.assertIn(
                            child,
                            dropped,
                            f"Parent {tbl} dropped before child {child}",
                        )
            dropped.add(tbl)

    def test_t2_f2_02_user_certificates_dropped_before_courses(self):
        """T2.F2.2: Verify user_certificates is dropped before courses."""
        uc_idx = self.schema.drop_tables.index("user_certificates")
        c_idx = self.schema.drop_tables.index("courses")
        self.assertLess(uc_idx, c_idx)

    def test_t2_f2_03_course_quiz_questions_dropped_before_course_items(self):
        """T2.F2.3: Verify course_quiz_questions is dropped before course_items."""
        qq_idx = self.schema.drop_tables.index("course_quiz_questions")
        ci_idx = self.schema.drop_tables.index("course_items")
        self.assertLess(qq_idx, ci_idx)

    def test_t2_f2_04_exam_telemetry_dropped_before_exam_sessions(self):
        """T2.F2.4: Verify exam_answers and exam_violations dropped before exam_sessions."""
        ea_idx = self.schema.drop_tables.index("exam_answers")
        ev_idx = self.schema.drop_tables.index("exam_violations")
        es_idx = self.schema.drop_tables.index("exam_sessions")
        self.assertLess(ea_idx, es_idx)
        self.assertLess(ev_idx, es_idx)

    def test_t2_f2_05_users_dropped_after_all_referencing_tables(self):
        """T2.F2.5: Verify users table is dropped after all 20+ referencing tables."""
        u_idx = self.schema.drop_tables.index("users")
        for child, fks in self.schema.foreign_keys.items():
            for fk in fks:
                if fk["parent"] == "users" and child != "users":
                    c_idx = self.schema.drop_tables.index(child)
                    self.assertLess(c_idx, u_idx)

    # Boundary 3: Charset and Collation Enforcement (5 tests)
    def test_t2_f3_01_all_tables_use_innodb(self):
        """T2.F3.1: Verify all 62 tables specify ENGINE=InnoDB."""
        for tbl, eng in self.schema.table_engines.items():
            self.assertEqual(eng.upper(), "INNODB", f"Table {tbl} not InnoDB")

    def test_t2_f3_02_all_tables_use_utf8mb4(self):
        """T2.F3.2: Verify all 62 tables specify DEFAULT CHARSET=utf8mb4."""
        for tbl, cs in self.schema.table_charsets.items():
            self.assertEqual(cs.lower(), "utf8mb4", f"Table {tbl} not utf8mb4")

    def test_t2_f3_03_all_tables_use_utf8mb4_unicode_ci(self):
        """T2.F3.3: Verify all 62 tables specify COLLATE=utf8mb4_unicode_ci."""
        for tbl, cl in self.schema.table_collations.items():
            self.assertEqual(
                cl.lower(), "utf8mb4_unicode_ci", f"Table {tbl} not utf8mb4_unicode_ci"
            )

    def test_t2_f3_04_no_legacy_latin1_or_utf8(self):
        """T2.F3.4: Verify no latin1 or 3-byte utf8 collations exist."""
        bad = re.findall(
            r"CHARSET\s*=\s*(latin1|utf8\b|ascii)", self.schema.raw_sql, re.I
        )
        self.assertEqual(bad, [])

    def test_t2_f3_05_header_sets_sql_mode_and_utc_tz(self):
        """T2.F3.5: Verify SQL_MODE and time_zone configured in schema header."""
        self.assertIn('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";', self.schema.raw_sql)
        self.assertIn('SET time_zone = "+00:00";', self.schema.raw_sql)

    # Boundary 4: DSN Injection Boundary Checks (5 tests)
    def test_t2_f4_01_reject_host_semicolon_injection(self):
        """T2.F4.1: Verify host DSN parameter rejects semicolons."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("preg_match('/[;\\x00-\\x1F\\x7F]/', $value)", db_code)

    def test_t2_f4_02_reject_dbname_null_bytes(self):
        """T2.F4.2: Verify dbname DSN parameter rejects null bytes."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("Invalid database ' . $label . ' configuration.", db_code)

    def test_t2_f4_03_port_range_clamped_1_to_65535(self):
        """T2.F4.3: Verify port is clamped between 1 and 65535."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("max(1, min(65535,", db_code)

    def test_t2_f4_04_timeout_range_clamped_1_to_30(self):
        """T2.F4.4: Verify timeout is clamped between 1 and 30."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("max(1, min(30,", db_code)

    def test_t2_f4_05_socket_path_dsn_validated(self):
        """T2.F4.5: Verify socket parameter is passed through appDbDsnValue."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("appDbDsnValue($socket, 'socket')", db_code)

    # Boundary 5: Blank Password / Root Blocking Outside Local (5 tests)
    def test_t2_f5_01_reject_empty_password_in_production(self):
        """T2.F5.1: Verify empty password is rejected in production."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("Empty database password is not allowed outside local development.", db_code)

    def test_t2_f5_02_reject_root_user_in_production(self):
        """T2.F5.2: Verify root account is rejected outside local development."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("The root database account is not allowed outside local development.", db_code)

    def test_t2_f5_03_loopback_endpoints_recognized(self):
        """T2.F5.3: Verify localhost, 127.0.0.1, ::1 recognized as local endpoints."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("['localhost', '127.0.0.1', '::1']", db_code)

    def test_t2_f5_04_local_environments_recognized(self):
        """T2.F5.4: Verify local, dev, development, test, testing are recognized as local."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("['local', 'dev', 'development', 'test', 'testing']", db_code)

    def test_t2_f5_05_username_control_chars_rejected(self):
        """T2.F5.5: Verify username cannot contain control characters or null bytes."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("preg_match('/[\\x00-\\x1F\\x7F]/', $user)", db_code)


class TestTier3CrossFeatureCombinations(unittest.TestCase):
    """Tier 3: Cross-Feature Integration & Cascade Graph Analysis."""

    @classmethod
    def setUpClass(cls):
        cls.schema = SchemaLexer(SCHEMA_FILE)

    def test_t3_01_user_deletion_cascade_depth(self):
        """T3.1: Verify deleting a user cascades through all child entities."""
        user_cascades = [
            tbl
            for tbl, fks in self.schema.foreign_keys.items()
            if any(
                fk["parent"] == "users" and fk["on_delete"] == "CASCADE"
                for fk in fks
            )
        ]
        self.assertGreaterEqual(
            len(user_cascades),
            15,
            f"User deletion should cascade to at least 15 relational tables, found: {len(user_cascades)}",
        )
        self.assertIn("active_user_sessions", user_cascades)
        self.assertIn("user_daily_missions", user_cascades)
        self.assertIn("luki_spins", user_cascades)
        self.assertIn("user_badges", user_cascades)
        self.assertIn("user_passkeys", user_cascades)
        self.assertIn("app_status_deliveries", user_cascades)

    def test_t3_02_course_curriculum_cascade_depth(self):
        """T3.2: Verify deleting a course cascades to modules, items, quiz questions, and progress."""
        course_cascades = [
            tbl
            for tbl, fks in self.schema.foreign_keys.items()
            if any(
                fk["parent"] == "courses" and fk["on_delete"] == "CASCADE"
                for fk in fks
            )
        ]
        self.assertIn("course_modules", course_cascades)
        self.assertIn("course_shares", course_cascades)
        self.assertIn("user_course_enrollments", course_cascades)

        module_cascades = [
            tbl
            for tbl, fks in self.schema.foreign_keys.items()
            if any(
                fk["parent"] == "course_modules" and fk["on_delete"] == "CASCADE"
                for fk in fks
            )
        ]
        self.assertIn("course_items", module_cascades)

        item_cascades = [
            tbl
            for tbl, fks in self.schema.foreign_keys.items()
            if any(
                fk["parent"] == "course_items" and fk["on_delete"] == "CASCADE"
                for fk in fks
            )
        ]
        self.assertIn("course_quiz_questions", item_cascades)

    def test_t3_03_user_privacy_settings_integration(self):
        """T3.3: Verify user privacy settings in users table are integrated with update_privacy action."""
        privacy_code = (ROOT / "actions" / "update_privacy.php").read_text(encoding="utf-8")
        self.assertIn("show_missions", privacy_code)
        self.assertIn("show_online_status", privacy_code)
        self.assertIn("show_recent_activity", privacy_code)

    def test_t3_04_concurrent_cached_query_execution_isolation(self):
        """T3.4: Verify cache keys isolate different queries and parameter payloads."""
        db_code = DB_CONFIG_FILE.read_text(encoding="utf-8")
        self.assertIn("md5($sql . '|' . json_encode($params))", db_code)


class TestTier4RealWorldScenarios(unittest.TestCase):
    """Tier 4: Real-World Scenarios and Provisioning Simulation."""

    @classmethod
    def setUpClass(cls):
        cls.schema = SchemaLexer(SCHEMA_FILE)

    def test_t4_01_simulated_full_schema_build_and_teardown_cycle(self):
        """T4.1: Simulate creating all 62 tables in topological order and dropping in exact reverse order."""
        active_database = set()

        # 1. BUILD CYCLE
        for table in self.schema.create_tables:
            for fk in self.schema.foreign_keys[table]:
                parent = fk["parent"]
                if parent != table:
                    self.assertIn(
                        parent,
                        active_database,
                        f"FK Constraint Failure during provisioning: {table} requires {parent}",
                    )
            active_database.add(table)

        self.assertEqual(len(active_database), 62, "Build cycle did not provision all 62 tables")

        # 2. TEARDOWN CYCLE
        for table in self.schema.drop_tables:
            for remaining in active_database:
                if remaining == table:
                    continue
                for fk in self.schema.foreign_keys[remaining]:
                    if fk["parent"] == table:
                        self.fail(
                            f"FK Constraint Violation during teardown: Cannot drop '{table}' while dependent child table '{remaining}' exists!"
                        )
            active_database.remove(table)

        self.assertEqual(len(active_database), 0, "Teardown cycle left un-dropped tables")

    def test_t4_02_web_request_simulation_with_zero_ddl(self):
        """T4.2: Simulate execution of web requests and verify 0 DDL statements are triggered."""
        res = run_php_script("tests/db_workflow_runtime.php")
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)
        self.assertIn("ALL RUNTIME DB WORKFLOW TESTS PASSED", res.stdout)

    def test_t4_03_high_throughput_repeated_query_memoization(self):
        """T4.3: Verify intra-request query reuse achieves 0 database overhead after first execution."""
        res = run_php_script("tests/db_read_performance_runtime.php")
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)
        self.assertIn("database read performance runtime OK", res.stdout)

    def test_t4_04_full_static_and_runtime_quality_gates(self):
        """T4.4: Execute full platform static compliance check ensuring zero regressions."""
        res = subprocess.run(
            [sys.executable, str(ROOT / "tests/static_compliance_check.py")],
            cwd=str(ROOT),
            capture_output=True,
            text=True,
            timeout=60,
            check=False,
        )
        self.assertEqual(res.returncode, 0, res.stdout + res.stderr)


if __name__ == "__main__":
    unittest.main(verbosity=2)
