#!/usr/bin/env python3
"""
Test Suite: Schema Syntax, Deduplication, Topological Ordering, and Integrity
Validates full_schema.sql against Tier 1 and Tier 2 specifications.
"""

from pathlib import Path
import re
import sys
import unittest
from collections import defaultdict, deque

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_FILE = ROOT / "full_schema.sql"

# The canonical list of all 62 application tables required by the platform
EXPECTED_TABLES = {
    "users",
    "rank_definitions",
    "app_settings",
    "ranking_event_templates",
    "questions",
    "badges",
    "login_attempts",
    "registration_attempts",
    "rate_limit_events",
    "active_user_sessions",
    "app_statuses",
    "feature_page_blocks",
    "sandbox_element_blocks",
    "admin_requests",
    "abuse_reports",
    "lessons",
    "admin_audit_log",
    "banned_emails",
    "banned_ips",
    "test_results",
    "user_active_tests",
    "notifications",
    "exams",
    "user_daily_missions",
    "xp_events",
    "luki_spins",
    "courses",
    "course_custom_labs",
    "user_education",
    "user_courses",
    "user_volunteering",
    "user_languages",
    "user_organizations",
    "user_social_links",
    "profile_comments",
    "friends",
    "duels",
    "unranked_usage",
    "all_in_duel_usage",
    "password_resets",
    "user_mfa",
    "user_passkeys",
    "ranking_events",
    "user_question_progress",
    "user_badges",
    "app_status_deliveries",
    "admin_request_replies",
    "test_answers",
    "exam_sessions",
    "course_shares",
    "course_modules",
    "user_course_enrollments",
    "user_certificates",
    "duel_answers",
    "exam_session_questions",
    "exam_participants",
    "course_items",
    "exam_answers",
    "exam_violations",
    "exam_warnings",
    "course_quiz_questions",
    "user_course_progress",
}

COURSE_TABLES = {
    "courses",
    "course_shares",
    "course_modules",
    "course_custom_labs",
    "course_items",
    "course_quiz_questions",
    "user_course_enrollments",
    "user_course_progress",
}

MISSING_TABLES_TO_VERIFY = {
    "app_statuses",
    "app_status_deliveries",
    "user_passkeys",
    "registration_attempts",
} | COURSE_TABLES


class SchemaParser:
    """Parses full_schema.sql into structured tables, columns, foreign keys, and drop statements."""

    def __init__(self, sql_path: Path):
        self.sql_text = sql_path.read_text(encoding="utf-8")
        self.drop_tables = []
        self.create_tables = []
        self.table_ddl = {}
        self.table_foreign_keys = defaultdict(list)
        self.table_columns = defaultdict(list)
        self.table_engine = {}
        self.table_charset = {}
        self.table_collation = {}
        self._parse()

    def _parse(self):
        # Extract DROP TABLE statements
        drop_matches = re.findall(
            r"DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*;",
            self.sql_text,
            re.IGNORECASE,
        )
        self.drop_tables = drop_matches

        # Extract CREATE TABLE blocks
        create_pattern = re.compile(
            r"CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([a-zA-Z0-9_]+)(.*?);",
            re.DOTALL | re.IGNORECASE,
        )

        for match in create_pattern.finditer(self.sql_text):
            table_name = match.group(1)
            body = match.group(2)
            engine = match.group(3)
            options = match.group(4)

            self.create_tables.append(table_name)
            self.table_ddl[table_name] = match.group(0)
            self.table_engine[table_name] = engine

            # Charset & Collation
            charset_match = re.search(
                r"(?:DEFAULT\s+)?CHARSET\s*=\s*([a-zA-Z0-9_]+)", options, re.I
            )
            collation_match = re.search(
                r"COLLATE\s*=\s*([a-zA-Z0-9_]+)", options, re.I
            )
            self.table_charset[table_name] = (
                charset_match.group(1) if charset_match else ""
            )
            self.table_collation[table_name] = (
                collation_match.group(1) if collation_match else ""
            )

            # Parse lines within body
            lines = [line.strip() for line in body.splitlines() if line.strip()]
            for line in lines:
                # Remove trailing comma
                clean_line = line.rstrip(",")

                # Foreign Key
                fk_match = re.search(
                    r"FOREIGN\s+KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)",
                    clean_line,
                    re.I,
                )
                if fk_match:
                    fk_col, ref_table, ref_col = fk_match.groups()
                    self.table_foreign_keys[table_name].append(
                        {
                            "column": fk_col,
                            "ref_table": ref_table,
                            "ref_column": ref_col,
                            "definition": clean_line,
                        }
                    )
                    continue

                # Column definition (skip constraints, keys, indices)
                if not re.match(
                    r"^(PRIMARY\s+KEY|FOREIGN\s+KEY|INDEX|KEY|UNIQUE|CONSTRAINT)",
                    clean_line,
                    re.I,
                ):
                    col_match = re.match(r"^`?([a-zA-Z0-9_]+)`?\s+(.+)", clean_line)
                    if col_match:
                        col_name, col_def = col_match.groups()
                        self.table_columns[table_name].append(
                            {
                                "name": col_name,
                                "definition": col_def,
                            }
                        )


class TestTier1SchemaFeatures(unittest.TestCase):
    """Tier 1: Feature Coverage (>= 5 tests per feature)."""

    @classmethod
    def setUpClass(cls):
        cls.parser = SchemaParser(SCHEMA_FILE)

    # Feature 1: Full Schema Table Inclusions (5 tests)
    def test_f1_01_total_table_count_equals_62(self):
        """T1.F1.1: Verify exactly 62 unique tables are declared in full_schema.sql."""
        self.assertEqual(
            len(self.parser.create_tables),
            62,
            f"Expected 62 CREATE TABLE statements, found {len(self.parser.create_tables)}",
        )
        self.assertEqual(
            len(set(self.parser.create_tables)),
            62,
            "Expected 62 unique table names in CREATE TABLE statements",
        )

    def test_f1_02_all_expected_tables_present(self):
        """T1.F1.2: Verify every table in canonical EXPECTED_TABLES is present."""
        created_set = set(self.parser.create_tables)
        missing = EXPECTED_TABLES - created_set
        self.assertEqual(missing, set(), f"Missing tables in schema: {missing}")

    def test_f1_03_missing_tables_added_with_correct_columns(self):
        """T1.F1.3: Verify newly included tables (app_statuses, app_status_deliveries, user_passkeys, registration_attempts) exist with required columns."""
        for table in MISSING_TABLES_TO_VERIFY:
            self.assertIn(
                table,
                self.parser.table_ddl,
                f"Missing table {table} not defined in full_schema.sql",
            )

        # Validate app_statuses columns
        app_status_cols = {
            col["name"] for col in self.parser.table_columns["app_statuses"]
        }
        self.assertTrue(
            {"id", "title", "body", "level", "is_active", "created_by"}.issubset(
                app_status_cols
            ),
            f"app_statuses columns incomplete: {app_status_cols}",
        )

        # Validate app_status_deliveries columns
        delivery_cols = {
            col["name"] for col in self.parser.table_columns["app_status_deliveries"]
        }
        self.assertTrue(
            {"status_id", "user_id", "delivered_at"}.issubset(delivery_cols),
            f"app_status_deliveries columns incomplete: {delivery_cols}",
        )

        # Validate user_passkeys columns
        passkey_cols = {
            col["name"] for col in self.parser.table_columns["user_passkeys"]
        }
        self.assertTrue(
            {
                "id",
                "user_id",
                "credential_id",
                "public_key",
                "counter",
                "device_name",
            }.issubset(passkey_cols),
            f"user_passkeys columns incomplete: {passkey_cols}",
        )

        # Validate registration_attempts columns
        reg_cols = {
            col["name"] for col in self.parser.table_columns["registration_attempts"]
        }
        self.assertTrue(
            {"id", "ip_address", "email_hash", "success", "attempt_time"}.issubset(
                reg_cols
            ),
            f"registration_attempts columns incomplete: {reg_cols}",
        )

    def test_f1_04_all_eight_course_tables_declared(self):
        """T1.F1.4: Verify all 8 course module and progress tables are declared in schema."""
        created_set = set(self.parser.create_tables)
        missing_courses = COURSE_TABLES - created_set
        self.assertEqual(
            missing_courses,
            set(),
            f"Course tables missing: {missing_courses}",
        )

    def test_f1_05_users_and_courses_contain_all_migrated_columns(self):
        """T1.F1.5: Verify users and courses tables contain migrated privacy and external columns."""
        user_cols = {col["name"] for col in self.parser.table_columns["users"]}
        self.assertIn("show_missions", user_cols)
        self.assertIn("show_online_status", user_cols)
        self.assertIn("show_recent_activity", user_cols)

        course_cols = {col["name"] for col in self.parser.table_columns["courses"]}
        self.assertIn("is_external", course_cols)
        self.assertIn("external_url", course_cols)

    # Feature 2: Schema Deduplication & Drops (5 tests)
    def test_f2_01_zero_duplicate_create_table_statements(self):
        """T1.F2.1: Verify zero duplicate CREATE TABLE statements across full_schema.sql."""
        seen = set()
        duplicates = []
        for tbl in self.parser.create_tables:
            if tbl in seen:
                duplicates.append(tbl)
            seen.add(tbl)
        self.assertEqual(
            duplicates,
            [],
            f"Duplicate CREATE TABLE statements detected: {duplicates}",
        )

    def test_f2_02_lessons_table_deduplicated_and_has_pdf_support(self):
        """T1.F2.2: Verify lessons table is declared only once and contains pdf_path, pdf_filename, pdf_download_allowed."""
        occurrences = [
            tbl for tbl in self.parser.create_tables if tbl.lower() == "lessons"
        ]
        self.assertEqual(
            len(occurrences), 1, f"lessons table defined {len(occurrences)} times"
        )

        lesson_cols = {col["name"] for col in self.parser.table_columns["lessons"]}
        self.assertTrue(
            {"pdf_path", "pdf_filename", "pdf_download_allowed"}.issubset(lesson_cols),
            f"lessons table missing PDF columns: {lesson_cols}",
        )

    def test_f2_03_luki_spins_table_deduplicated_and_has_foreign_key(self):
        """T1.F2.3: Verify luki_spins is declared only once and retains FK to users(id)."""
        occurrences = [
            tbl for tbl in self.parser.create_tables if tbl.lower() == "luki_spins"
        ]
        self.assertEqual(
            len(occurrences), 1, f"luki_spins table defined {len(occurrences)} times"
        )

        fks = self.parser.table_foreign_keys["luki_spins"]
        has_user_fk = any(
            fk["ref_table"] == "users" and fk["column"] == "user_id" for fk in fks
        )
        self.assertTrue(has_user_fk, "luki_spins missing FK reference to users(id)")

    def test_f2_04_drop_table_header_has_exact_62_tables(self):
        """T1.F2.4: Verify DROP TABLE IF EXISTS header contains exactly 62 tables."""
        self.assertEqual(
            len(self.parser.drop_tables),
            62,
            f"Expected 62 DROP TABLE statements, found {len(self.parser.drop_tables)}",
        )
        self.assertEqual(
            len(set(self.parser.drop_tables)),
            62,
            "DROP TABLE statements contain duplicate table names",
        )

    def test_f2_05_drop_table_set_matches_create_table_set(self):
        """T1.F2.5: Verify set of dropped tables exactly matches the set of created tables."""
        drop_set = set(self.parser.drop_tables)
        create_set = set(self.parser.create_tables)
        self.assertEqual(
            drop_set,
            create_set,
            f"Drop set and Create set mismatch. Extra in drops: {drop_set - create_set}, Extra in creates: {create_set - drop_set}",
        )


class TestTier2BoundaryAndTopologicalOrdering(unittest.TestCase):
    """Tier 2: Boundary & Corner Cases (>= 5 tests per feature)."""

    @classmethod
    def setUpClass(cls):
        cls.parser = SchemaParser(SCHEMA_FILE)

    # Feature 1: Foreign Key Topological Validity (5 tests)
    def test_f1_01_no_forward_references_in_create_order(self):
        """T2.F1.1: Verify every foreign key parent table is declared BEFORE its child table."""
        declared_so_far = set()
        violations = []

        for table in self.parser.create_tables:
            for fk in self.parser.table_foreign_keys[table]:
                parent = fk["ref_table"]
                if parent != table and parent not in declared_so_far:
                    violations.append(
                        f"Table '{table}' references parent '{parent}' before '{parent}' is created"
                    )
            declared_so_far.add(table)

        self.assertEqual(
            violations,
            [],
            "Forward foreign key references detected:\n" + "\n".join(violations),
        )

    def test_f1_02_courses_precedes_user_certificates(self):
        """T2.F1.2: Verify courses is declared before user_certificates in CREATE order."""
        courses_idx = self.parser.create_tables.index("courses")
        certs_idx = self.parser.create_tables.index("user_certificates")
        self.assertLess(
            courses_idx,
            certs_idx,
            f"courses (idx {courses_idx}) must precede user_certificates (idx {certs_idx})",
        )

    def test_f1_03_course_modules_and_custom_labs_precede_course_items(self):
        """T2.F1.3: Verify course_modules and course_custom_labs precede course_items."""
        items_idx = self.parser.create_tables.index("course_items")
        modules_idx = self.parser.create_tables.index("course_modules")
        labs_idx = self.parser.create_tables.index("course_custom_labs")
        self.assertLess(modules_idx, items_idx)
        self.assertLess(labs_idx, items_idx)

    def test_f1_04_exam_sessions_precedes_exam_session_questions_and_participants(self):
        """T2.F1.4: Verify exam_sessions precedes dependent participant and question snapshot tables."""
        session_idx = self.parser.create_tables.index("exam_sessions")
        q_idx = self.parser.create_tables.index("exam_session_questions")
        p_idx = self.parser.create_tables.index("exam_participants")
        self.assertLess(session_idx, q_idx)
        self.assertLess(session_idx, p_idx)

    def test_f1_05_fk_dependency_graph_is_acyclic(self):
        """T2.F1.5: Verify the table foreign key dependency graph is a valid DAG (no cycles)."""
        adj = defaultdict(set)
        in_degree = defaultdict(int)

        for table in self.parser.create_tables:
            in_degree[table] = 0

        for table, fks in self.parser.table_foreign_keys.items():
            for fk in fks:
                parent = fk["ref_table"]
                if parent != table:  # ignore self-referential
                    adj[parent].add(table)

        for parent, children in adj.items():
            for child in children:
                in_degree[child] += 1

        queue = deque([tbl for tbl, deg in in_degree.items() if deg == 0])
        visited_count = 0

        while queue:
            node = queue.popleft()
            visited_count += 1
            for neighbor in adj[node]:
                in_degree[neighbor] -= 1
                if in_degree[neighbor] == 0:
                    queue.append(neighbor)

        self.assertEqual(
            visited_count,
            len(self.parser.create_tables),
            "Dependency graph contains cycles! Kahn's algorithm failed to visit all tables.",
        )

    # Feature 2: Reverse DROP TABLE Order Validity (5 tests)
    def test_f2_01_reverse_drop_order_validity(self):
        """T2.F2.1: Verify every child table is dropped BEFORE its referenced parent table."""
        dropped_so_far = set()
        violations = []

        for table in self.parser.drop_tables:
            # When dropping 'table', all tables that reference 'table' as FK must already be dropped!
            for other_table, fks in self.parser.table_foreign_keys.items():
                for fk in fks:
                    if fk["ref_table"] == table and other_table != table:
                        if other_table not in dropped_so_far:
                            violations.append(
                                f"Parent table '{table}' dropped before child table '{other_table}'"
                            )
            dropped_so_far.add(table)

        self.assertEqual(
            violations,
            [],
            "Invalid DROP order violations:\n" + "\n".join(violations),
        )

    def test_f2_02_user_certificates_dropped_before_courses(self):
        """T2.F2.2: Verify user_certificates is dropped before courses."""
        certs_drop = self.parser.drop_tables.index("user_certificates")
        courses_drop = self.parser.drop_tables.index("courses")
        self.assertLess(
            certs_drop,
            courses_drop,
            f"user_certificates (drop idx {certs_drop}) must be dropped before courses (drop idx {courses_drop})",
        )

    def test_f2_03_course_quiz_questions_dropped_before_course_items(self):
        """T2.F2.3: Verify course_quiz_questions is dropped before course_items."""
        qq_drop = self.parser.drop_tables.index("course_quiz_questions")
        items_drop = self.parser.drop_tables.index("course_items")
        self.assertLess(qq_drop, items_drop)

    def test_f2_04_exam_answers_dropped_before_exam_participants(self):
        """T2.F2.4: Verify exam_answers is dropped before exam_participants."""
        ans_drop = self.parser.drop_tables.index("exam_answers")
        part_drop = self.parser.drop_tables.index("exam_participants")
        self.assertLess(ans_drop, part_drop)

    def test_f2_05_users_dropped_last_or_after_all_user_dependents(self):
        """T2.F2.5: Verify users table is dropped after all tables referencing users(id)."""
        users_drop = self.parser.drop_tables.index("users")
        user_dependents = [
            tbl
            for tbl, fks in self.parser.table_foreign_keys.items()
            if any(fk["ref_table"] == "users" for fk in fks)
        ]
        for dep in user_dependents:
            dep_drop = self.parser.drop_tables.index(dep)
            self.assertLess(
                dep_drop,
                users_drop,
                f"Dependent table '{dep}' (idx {dep_drop}) dropped after users (idx {users_drop})",
            )

    # Feature 3: Charset and Collation Enforcement (5 tests)
    def test_f3_01_all_62_tables_use_innodb_engine(self):
        """T2.F3.1: Verify all 62 tables explicitly specify ENGINE=InnoDB."""
        non_innodb = [
            tbl
            for tbl, eng in self.parser.table_engine.items()
            if eng.lower() != "innodb"
        ]
        self.assertEqual(
            non_innodb, [], f"Tables not using InnoDB engine: {non_innodb}"
        )

    def test_f3_02_all_62_tables_use_utf8mb4_charset(self):
        """T2.F3.2: Verify all 62 tables specify DEFAULT CHARSET=utf8mb4."""
        non_utf8mb4 = [
            tbl
            for tbl, charset in self.parser.table_charset.items()
            if charset.lower() != "utf8mb4"
        ]
        self.assertEqual(
            non_utf8mb4, [], f"Tables not using utf8mb4 charset: {non_utf8mb4}"
        )

    def test_f3_03_all_62_tables_use_utf8mb4_unicode_ci_collation(self):
        """T2.F3.3: Verify all 62 tables specify COLLATE=utf8mb4_unicode_ci."""
        non_unicode = [
            tbl
            for tbl, coll in self.parser.table_collation.items()
            if coll.lower() != "utf8mb4_unicode_ci"
        ]
        self.assertEqual(
            non_unicode,
            [],
            f"Tables not using utf8mb4_unicode_ci collation: {non_unicode}",
        )

    def test_f3_04_schema_sets_sql_mode_and_timezone(self):
        """T2.F3.4: Verify full_schema.sql header configures SQL_MODE and time_zone."""
        self.assertIn('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";', self.parser.sql_text)
        self.assertIn('SET time_zone = "+00:00";', self.parser.sql_text)

    def test_f3_05_no_deprecated_latin1_or_utf8_references(self):
        """T2.F3.5: Verify no deprecated latin1 or 3-byte utf8 collations exist in table DDLs."""
        bad_charsets = re.findall(
            r"CHARSET\s*=\s*(latin1|utf8\b|ascii)", self.parser.sql_text, re.I
        )
        self.assertEqual(
            bad_charsets, [], f"Found non-utf8mb4 charsets: {bad_charsets}"
        )


if __name__ == "__main__":
    unittest.main(verbosity=2)
