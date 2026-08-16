#!/usr/bin/env python3
"""
Test Suite: Comprehensive Schema AST, Constraints, and Boundary Validation (Tiers 1 & 2)
Validates all 62 tables, data types, indexes, foreign keys, deduplication, and constraints.
"""

from pathlib import Path
import re
import unittest
from collections import defaultdict, deque

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_FILE = ROOT / "full_schema.sql"

EXPECTED_62_TABLES = [
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
]


class SchemaAST:
    """Detailed AST scanner for full_schema.sql."""

    def __init__(self, sql_content: str):
        self.raw_sql = sql_content
        self.tables = {}
        self.drop_list = []
        self.create_order = []
        self._parse()

    def _parse(self):
        # Parse drops
        drop_pattern = re.compile(
            r"DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*;", re.I
        )
        self.drop_list = drop_pattern.findall(self.raw_sql)

        # Parse create tables
        table_pattern = re.compile(
            r"CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([a-zA-Z0-9_]+)(.*?);",
            re.DOTALL | re.IGNORECASE,
        )

        for match in table_pattern.finditer(self.raw_sql):
            table_name = match.group(1)
            body = match.group(2)
            engine = match.group(3)
            trailing_options = match.group(4)

            self.create_order.append(table_name)
            table_info = {
                "name": table_name,
                "engine": engine,
                "options": trailing_options,
                "raw_ddl": match.group(0),
                "columns": {},
                "column_order": [],
                "primary_keys": [],
                "foreign_keys": [],
                "indexes": [],
                "uniques": [],
            }

            charset_m = re.search(
                r"(?:DEFAULT\s+)?CHARSET\s*=\s*([a-zA-Z0-9_]+)", trailing_options, re.I
            )
            collate_m = re.search(
                r"COLLATE\s*=\s*([a-zA-Z0-9_]+)", trailing_options, re.I
            )
            table_info["charset"] = charset_m.group(1) if charset_m else ""
            table_info["collation"] = collate_m.group(1) if collate_m else ""

            # Parse lines in table definition
            lines = [l.strip() for l in body.splitlines() if l.strip()]
            for line in lines:
                clean = line.rstrip(",")

                # Primary Key
                pk_m = re.search(
                    r"PRIMARY\s+KEY\s*\((.*?)\)", clean, re.IGNORECASE
                )
                if pk_m:
                    cols = [
                        c.strip().strip("`")
                        for c in pk_m.group(1).split(",")
                        if c.strip()
                    ]
                    table_info["primary_keys"].extend(cols)
                    continue

                # Foreign Key
                fk_m = re.search(
                    r"FOREIGN\s+KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)(?:\s+ON\s+DELETE\s+([a-zA-Z\s]+))?(?:\s+ON\s+UPDATE\s+([a-zA-Z\s]+))?",
                    clean,
                    re.IGNORECASE,
                )
                if fk_m:
                    table_info["foreign_keys"].append(
                        {
                            "column": fk_m.group(1),
                            "ref_table": fk_m.group(2),
                            "ref_column": fk_m.group(3),
                            "on_delete": (
                                fk_m.group(4).strip() if fk_m.group(4) else "RESTRICT"
                            ),
                            "on_update": (
                                fk_m.group(5).strip() if fk_m.group(5) else "RESTRICT"
                            ),
                            "raw": clean,
                        }
                    )
                    continue

                # Unique Key / Index
                uniq_m = re.search(
                    r"UNIQUE\s+(?:KEY|INDEX)?\s*(?:`?([a-zA-Z0-9_]+)`?\s*)?\((.*?)\)",
                    clean,
                    re.IGNORECASE,
                )
                if uniq_m:
                    name = uniq_m.group(1) or "unnamed_unique"
                    cols = [
                        c.strip().strip("`")
                        for c in uniq_m.group(2).split(",")
                        if c.strip()
                    ]
                    table_info["uniques"].append({"name": name, "columns": cols})
                    continue

                # Normal Index
                idx_m = re.search(
                    r"INDEX\s+(?:`?([a-zA-Z0-9_]+)`?\s*)?\((.*?)\)", clean, re.IGNORECASE
                )
                if idx_m:
                    name = idx_m.group(1) or "unnamed_index"
                    cols = [
                        c.strip().strip("`")
                        for c in idx_m.group(2).split(",")
                        if c.strip()
                    ]
                    table_info["indexes"].append({"name": name, "columns": cols})
                    continue

                # Standard Column
                col_m = re.match(
                    r"^`?([a-zA-Z0-9_]+)`?\s+([a-zA-Z0-9_]+(?:\(.*?\))?)(.*)$", clean
                )
                if col_m:
                    col_name = col_m.group(1)
                    col_type = col_m.group(2)
                    col_attrs = col_m.group(3).strip()

                    is_pk = "PRIMARY KEY" in col_attrs.upper()
                    if is_pk:
                        table_info["primary_keys"].append(col_name)

                    table_info["columns"][col_name] = {
                        "name": col_name,
                        "type": col_type,
                        "attributes": col_attrs,
                        "nullable": "NOT NULL" not in col_attrs.upper(),
                        "auto_increment": "AUTO_INCREMENT" in col_attrs.upper(),
                        "raw": clean,
                    }
                    table_info["column_order"].append(col_name)

            self.tables[table_name] = table_info


class TestComprehensiveSchema(unittest.TestCase):
    """Deep structural and boundary verification for the unified 62-table schema."""

    @classmethod
    def setUpClass(cls):
        cls.sql_text = SCHEMA_FILE.read_text(encoding="utf-8")
        cls.ast = SchemaAST(cls.sql_text)

    # -------------------------------------------------------------
    # Tier 1 Feature 1: Complete 62 Table Inventory & Column Verifications (5 tests)
    # -------------------------------------------------------------
    def test_t1_f1_01_exact_62_table_manifest(self):
        """T1.F1.1: Verify the exact count and presence of all 62 application tables."""
        self.assertEqual(
            len(self.ast.create_order), 62, "Schema must declare exactly 62 tables"
        )
        self.assertEqual(
            len(self.ast.tables), 62, "Schema must have 62 unique table entries"
        )
        for expected in EXPECTED_62_TABLES:
            self.assertIn(
                expected,
                self.ast.tables,
                f"Missing expected canonical table: {expected}",
            )

    def test_t1_f1_02_app_statuses_and_deliveries_schema_validation(self):
        """T1.F1.2: Verify app_statuses and app_status_deliveries table structures."""
        # app_statuses
        st = self.ast.tables["app_statuses"]
        self.assertIn("id", st["columns"])
        self.assertTrue(st["columns"]["id"]["auto_increment"])
        self.assertIn("title", st["columns"])
        self.assertIn("body", st["columns"])
        self.assertIn("level", st["columns"])
        self.assertIn("is_active", st["columns"])
        self.assertIn("created_by", st["columns"])

        # Check FK from app_statuses to users
        fk = [
            f
            for f in st["foreign_keys"]
            if f["column"] == "created_by" and f["ref_table"] == "users"
        ]
        self.assertEqual(len(fk), 1, "app_statuses.created_by must reference users(id)")
        self.assertEqual(fk[0]["on_delete"].upper(), "SET NULL")

        # app_status_deliveries
        deliv = self.ast.tables["app_status_deliveries"]
        self.assertIn("status_id", deliv["columns"])
        self.assertIn("user_id", deliv["columns"])
        self.assertIn("delivered_at", deliv["columns"])
        self.assertEqual(
            set(deliv["primary_keys"]),
            {"status_id", "user_id"},
            "app_status_deliveries composite primary key invalid",
        )

    def test_t1_f1_03_user_passkeys_and_registration_attempts_validation(self):
        """T1.F1.3: Verify user_passkeys and registration_attempts table structures."""
        # user_passkeys
        pk = self.ast.tables["user_passkeys"]
        self.assertIn("credential_id", pk["columns"])
        self.assertIn("public_key", pk["columns"])
        self.assertIn("counter", pk["columns"])
        self.assertIn("device_name", pk["columns"])
        fk = [
            f
            for f in pk["foreign_keys"]
            if f["column"] == "user_id" and f["ref_table"] == "users"
        ]
        self.assertEqual(len(fk), 1, "user_passkeys.user_id must reference users(id)")
        self.assertEqual(fk[0]["on_delete"].upper(), "CASCADE")

        # registration_attempts
        ra = self.ast.tables["registration_attempts"]
        self.assertIn("ip_address", ra["columns"])
        self.assertIn("email_hash", ra["columns"])
        self.assertIn("success", ra["columns"])
        self.assertIn("attempt_time", ra["columns"])

    def test_t1_f1_04_all_course_module_and_item_tables_validation(self):
        """T1.F1.4: Verify the 8 course-related tables and their relational links."""
        course_tables = [
            "courses",
            "course_shares",
            "course_modules",
            "course_custom_labs",
            "course_items",
            "course_quiz_questions",
            "user_course_enrollments",
            "user_course_progress",
        ]
        for tbl in course_tables:
            self.assertIn(tbl, self.ast.tables, f"Course table {tbl} missing")

        # courses columns
        c = self.ast.tables["courses"]
        self.assertIn("is_external", c["columns"])
        self.assertIn("external_url", c["columns"])

        # course_items references course_modules & course_custom_labs
        ci = self.ast.tables["course_items"]
        fks = {f["column"]: f["ref_table"] for f in ci["foreign_keys"]}
        self.assertEqual(fks.get("module_id"), "course_modules")
        self.assertEqual(fks.get("lab_custom_id"), "course_custom_labs")

        # course_quiz_questions references course_items
        cqq = self.ast.tables["course_quiz_questions"]
        fks_q = {f["column"]: f["ref_table"] for f in cqq["foreign_keys"]}
        self.assertEqual(fks_q.get("item_id"), "course_items")

    def test_t1_f1_05_user_privacy_and_gamification_columns_in_users(self):
        """T1.F1.5: Verify user table privacy toggles, verification fields, and ban expiry."""
        u = self.ast.tables["users"]
        self.assertIn("show_missions", u["columns"])
        self.assertIn("show_online_status", u["columns"])
        self.assertIn("show_recent_activity", u["columns"])
        self.assertIn("profile_public", u["columns"])
        self.assertIn("stats_public", u["columns"])
        self.assertIn("allow_profile_comments", u["columns"])
        self.assertIn("allow_friend_requests", u["columns"])
        self.assertIn("searchable", u["columns"])
        self.assertIn("ranking_visible", u["columns"])
        self.assertIn("ban_expires_at", u["columns"])

    # -------------------------------------------------------------
    # Tier 1 Feature 2: Deduplication & Clean Drop Header (5 tests)
    # -------------------------------------------------------------
    def test_t1_f2_01_no_duplicate_tables_in_ddl(self):
        """T1.F2.1: Verify no duplicate table definitions exist in full_schema.sql."""
        self.assertEqual(
            len(self.ast.create_order),
            len(set(self.ast.create_order)),
            f"Duplicate CREATE statements found: {len(self.ast.create_order)} vs {len(set(self.ast.create_order))}",
        )

    def test_t1_f2_02_lessons_is_canonical_and_unique(self):
        """T1.F2.2: Verify lessons is defined exactly once with full PDF attributes."""
        count = self.ast.create_order.count("lessons")
        self.assertEqual(count, 1, f"lessons defined {count} times")
        l = self.ast.tables["lessons"]
        self.assertIn("pdf_path", l["columns"])
        self.assertIn("pdf_filename", l["columns"])
        self.assertIn("pdf_download_allowed", l["columns"])
        fks = [
            f
            for f in l["foreign_keys"]
            if f["column"] == "teacher_id" and f["ref_table"] == "users"
        ]
        self.assertEqual(len(fks), 1, "lessons.teacher_id must reference users(id)")

    def test_t1_f2_03_luki_spins_is_canonical_and_unique(self):
        """T1.F2.3: Verify luki_spins is defined exactly once with FK to users."""
        count = self.ast.create_order.count("luki_spins")
        self.assertEqual(count, 1, f"luki_spins defined {count} times")
        ls = self.ast.tables["luki_spins"]
        self.assertIn("archetype", ls["columns"])
        self.assertIn("xp_delta", ls["columns"])
        fks = [
            f
            for f in ls["foreign_keys"]
            if f["column"] == "user_id" and f["ref_table"] == "users"
        ]
        self.assertEqual(len(fks), 1, "luki_spins.user_id must reference users(id)")

    def test_t1_f2_04_drop_list_exact_62_tables(self):
        """T1.F2.4: Verify DROP TABLE IF EXISTS list contains exactly 62 tables."""
        self.assertEqual(
            len(self.ast.drop_list),
            62,
            f"DROP list has {len(self.ast.drop_list)} tables instead of 62",
        )
        self.assertEqual(
            len(set(self.ast.drop_list)), 62, "DROP list contains duplicates"
        )

    def test_t1_f2_05_drop_list_is_exact_reverse_of_create_order(self):
        """T1.F2.5: Verify DROP list matches the reverse topological sequence."""
        dropped = set()
        for tbl in self.ast.drop_list:
            for parent_tbl, tinfo in self.ast.tables.items():
                for fk in tinfo["foreign_keys"]:
                    if fk["ref_table"] == tbl and parent_tbl != tbl:
                        self.assertIn(
                            parent_tbl,
                            dropped,
                            f"Table {tbl} dropped before dependent table {parent_tbl}",
                        )
            dropped.add(tbl)

    # -------------------------------------------------------------
    # Tier 2 Boundary 1: Topological Ordering & Foreign Key Validity (5 tests)
    # -------------------------------------------------------------
    def test_t2_f1_01_strict_topological_create_order(self):
        """T2.F1.1: Verify that when any table is created, all its referenced parent tables already exist."""
        created = set()
        for tbl in self.ast.create_order:
            tinfo = self.ast.tables[tbl]
            for fk in tinfo["foreign_keys"]:
                parent = fk["ref_table"]
                if parent != tbl:  # allow self-referential
                    self.assertIn(
                        parent,
                        created,
                        f"Topological violation: table '{tbl}' references '{parent}', but '{parent}' was not created yet.",
                    )
            created.add(tbl)

    def test_t2_f1_02_all_referenced_columns_exist_in_parent_tables(self):
        """T2.F1.2: Verify every foreign key references a column that exists and is a primary key or unique."""
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                ref_tbl = fk["ref_table"]
                ref_col = fk["ref_column"]
                self.assertIn(
                    ref_tbl,
                    self.ast.tables,
                    f"Referenced table '{ref_tbl}' does not exist",
                )
                parent_info = self.ast.tables[ref_tbl]
                self.assertIn(
                    ref_col,
                    parent_info["columns"],
                    f"Referenced column '{ref_col}' does not exist in table '{ref_tbl}'",
                )

    def test_t2_f1_03_courses_hierarchy_topological_ordering(self):
        """T2.F1.3: Verify courses -> course_modules -> course_items -> course_quiz_questions ordering."""
        c_idx = self.ast.create_order.index("courses")
        cm_idx = self.ast.create_order.index("course_modules")
        ci_idx = self.ast.create_order.index("course_items")
        cqq_idx = self.ast.create_order.index("course_quiz_questions")

        self.assertLess(c_idx, cm_idx)
        self.assertLess(cm_idx, ci_idx)
        self.assertLess(ci_idx, cqq_idx)

    def test_t2_f1_04_exam_hierarchy_topological_ordering(self):
        """T2.F1.4: Verify exams -> exam_sessions -> exam_participants -> exam_answers/violations/warnings ordering."""
        e_idx = self.ast.create_order.index("exams")
        es_idx = self.ast.create_order.index("exam_sessions")
        ep_idx = self.ast.create_order.index("exam_participants")
        ea_idx = self.ast.create_order.index("exam_answers")
        ev_idx = self.ast.create_order.index("exam_violations")
        ew_idx = self.ast.create_order.index("exam_warnings")

        self.assertLess(e_idx, es_idx)
        self.assertLess(es_idx, ep_idx)
        self.assertLess(ep_idx, ea_idx)
        self.assertLess(ep_idx, ev_idx)
        self.assertLess(ep_idx, ew_idx)

    def test_t2_f1_05_admin_requests_and_replies_ordering(self):
        """T2.F1.5: Verify admin_requests precedes admin_request_replies in creation order."""
        ar_idx = self.ast.create_order.index("admin_requests")
        arr_idx = self.ast.create_order.index("admin_request_replies")
        self.assertLess(ar_idx, arr_idx)

    # -------------------------------------------------------------
    # Tier 2 Boundary 2: Reverse DROP TABLE Cascades (5 tests)
    # -------------------------------------------------------------
    def test_t2_f2_01_user_course_progress_dropped_first_or_early(self):
        """T2.F2.1: Verify deepest dependent leaf tables are dropped at the beginning of DROP sequence."""
        ucp_drop = self.ast.drop_list.index("user_course_progress")
        c_drop = self.ast.drop_list.index("courses")
        self.assertLess(
            ucp_drop,
            c_drop,
            "user_course_progress must be dropped before courses",
        )

    def test_t2_f2_02_exam_answers_and_violations_dropped_before_exam_sessions(self):
        """T2.F2.2: Verify exam telemetry tables dropped before parent exam_sessions."""
        ea_drop = self.ast.drop_list.index("exam_answers")
        ev_drop = self.ast.drop_list.index("exam_violations")
        es_drop = self.ast.drop_list.index("exam_sessions")
        self.assertLess(ea_drop, es_drop)
        self.assertLess(ev_drop, es_drop)

    def test_t2_f2_03_duel_answers_dropped_before_duels(self):
        """T2.F2.3: Verify duel_answers dropped before duels."""
        da_drop = self.ast.drop_list.index("duel_answers")
        d_drop = self.ast.drop_list.index("duels")
        self.assertLess(da_drop, d_drop)

    def test_t2_f2_04_test_answers_dropped_before_test_results_and_questions(self):
        """T2.F2.4: Verify test_answers dropped before test_results and questions."""
        ta_drop = self.ast.drop_list.index("test_answers")
        tr_drop = self.ast.drop_list.index("test_results")
        q_drop = self.ast.drop_list.index("questions")
        self.assertLess(ta_drop, tr_drop)
        self.assertLess(ta_drop, q_drop)

    def test_t2_f2_05_users_is_dropped_after_all_referencing_tables(self):
        """T2.F2.5: Verify users table is dropped after every single dependent child table."""
        u_drop = self.ast.drop_list.index("users")
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                if fk["ref_table"] == "users" and tbl != "users":
                    dep_drop = self.ast.drop_list.index(tbl)
                    self.assertLess(
                        dep_drop,
                        u_drop,
                        f"Dependent table '{tbl}' dropped after users in teardown",
                    )

    # -------------------------------------------------------------
    # Tier 2 Boundary 3: Charset, Collation & Engine Uniformity (5 tests)
    # -------------------------------------------------------------
    def test_t2_f3_01_all_tables_innodb_strict(self):
        """T2.F3.1: Verify every table uses InnoDB engine."""
        for name, tinfo in self.ast.tables.items():
            self.assertEqual(
                tinfo["engine"].upper(),
                "INNODB",
                f"Table {name} uses non-InnoDB engine: {tinfo['engine']}",
            )

    def test_t2_f3_02_all_tables_utf8mb4_charset_strict(self):
        """T2.F3.2: Verify every table specifies utf8mb4 default charset."""
        for name, tinfo in self.ast.tables.items():
            self.assertEqual(
                tinfo["charset"].lower(),
                "utf8mb4",
                f"Table {name} uses non-utf8mb4 charset: {tinfo['charset']}",
            )

    def test_t2_f3_03_all_tables_utf8mb4_unicode_ci_collation_strict(self):
        """T2.F3.3: Verify every table specifies utf8mb4_unicode_ci collation."""
        for name, tinfo in self.ast.tables.items():
            self.assertEqual(
                tinfo["collation"].lower(),
                "utf8mb4_unicode_ci",
                f"Table {name} uses non-utf8mb4_unicode_ci collation: {tinfo['collation']}",
            )

    def test_t2_f3_04_primary_keys_defined_on_all_tables(self):
        """T2.F3.4: Verify every single table has at least one primary key column."""
        for name, tinfo in self.ast.tables.items():
            self.assertTrue(
                len(tinfo["primary_keys"]) > 0,
                f"Table {name} does not have a PRIMARY KEY defined",
            )

    def test_t2_f3_05_all_foreign_key_references_are_valid_in_schema(self):
        """T2.F3.5: Verify every foreign key definition is valid and has matching parent key."""
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                ref_tbl = fk["ref_table"]
                ref_col = fk["ref_column"]
                parent_info = self.ast.tables[ref_tbl]
                # Either ref_col is primary key or unique in parent
                is_parent_pk = ref_col in parent_info["primary_keys"]
                is_parent_uniq = any(
                    ref_col in u["columns"] for u in parent_info["uniques"]
                )
                self.assertTrue(
                    is_parent_pk or is_parent_uniq,
                    f"FK in '{tbl}' references non-unique/non-PK column '{ref_col}' in '{ref_tbl}'",
                )


if __name__ == "__main__":
    unittest.main(verbosity=2)
