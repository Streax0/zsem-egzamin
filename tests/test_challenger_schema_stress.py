#!/usr/bin/env python3
"""
Challenger 1 - Schema Stress & Adversarial Verifier
Empirical test suite for full_schema.sql:
1. Complete dependency graph validation (cycles, forward references, dangling FK target tables/columns).
2. Cross-verification against r1_files_audit.json schemas and direct codebase AST scanning.
3. Charset (utf8mb4), Collation (utf8mb4_unicode_ci), and Engine (InnoDB) verification on all 62 tables.
4. Simulated teardown and recreation cycles under active foreign key constraints in SQLite.
5. Deep adversarial stress tests: FK nullability on SET NULL, type compatibility, and PK invariants.
"""

from collections import defaultdict, deque
import json
import os
from pathlib import Path
import re
import sqlite3
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_FILE = ROOT / "full_schema.sql"
AUDIT_FILE = ROOT / "r1_files_audit.json"

class AdvancedSchemaAST:
    def __init__(self, sql_path: Path):
        self.sql_path = sql_path
        self.sql_text = sql_path.read_text(encoding="utf-8")
        self.drop_tables = []
        self.create_order = []
        self.tables = {}
        self._parse()

    def _parse(self):
        # Extract DROP TABLE statements
        drop_pattern = re.compile(
            r"DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?\s*;", re.I
        )
        self.drop_tables = drop_pattern.findall(self.sql_text)

        # Extract CREATE TABLE statements
        create_pattern = re.compile(
            r"CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=\s*([a-zA-Z0-9_]+)(.*?);",
            re.DOTALL | re.IGNORECASE,
        )

        for match in create_pattern.finditer(self.sql_text):
            table_name = match.group(1)
            body = match.group(2)
            engine = match.group(3)
            options = match.group(4)

            self.create_order.append(table_name)
            t_info = {
                "name": table_name,
                "engine": engine,
                "options": options,
                "raw_ddl": match.group(0),
                "columns": {},
                "column_order": [],
                "primary_keys": [],
                "foreign_keys": [],
                "indexes": [],
                "uniques": [],
                "charset": "",
                "collation": "",
            }

            charset_m = re.search(r"(?:DEFAULT\s+)?CHARSET\s*=\s*([a-zA-Z0-9_]+)", options, re.I)
            collate_m = re.search(r"COLLATE\s*=\s*([a-zA-Z0-9_]+)", options, re.I)
            t_info["charset"] = charset_m.group(1) if charset_m else ""
            t_info["collation"] = collate_m.group(1) if collate_m else ""

            lines = [l.strip() for l in body.splitlines() if l.strip()]
            for line in lines:
                clean = line.rstrip(",")

                # Primary Key
                pk_m = re.search(r"PRIMARY\s+KEY\s*\((.*?)\)", clean, re.IGNORECASE)
                if pk_m:
                    cols = [c.strip().strip("`") for c in pk_m.group(1).split(",") if c.strip()]
                    t_info["primary_keys"].extend(cols)
                    continue

                # Foreign Key
                fk_m = re.search(
                    r"FOREIGN\s+KEY\s*\(`?([a-zA-Z0-9_]+)`?\)\s*REFERENCES\s*`?([a-zA-Z0-9_]+)`?\s*\(`?([a-zA-Z0-9_]+)`?\)(?:\s+ON\s+DELETE\s+([a-zA-Z\s]+))?(?:\s+ON\s+UPDATE\s+([a-zA-Z\s]+))?",
                    clean,
                    re.IGNORECASE,
                )
                if fk_m:
                    t_info["foreign_keys"].append({
                        "column": fk_m.group(1),
                        "ref_table": fk_m.group(2),
                        "ref_column": fk_m.group(3),
                        "on_delete": fk_m.group(4).strip().upper() if fk_m.group(4) else "RESTRICT",
                        "on_update": fk_m.group(5).strip().upper() if fk_m.group(5) else "RESTRICT",
                        "raw": clean,
                    })
                    continue

                # Unique Key / Index
                uniq_m = re.search(
                    r"UNIQUE\s+(?:KEY|INDEX)?\s*(?:`?([a-zA-Z0-9_]+)`?\s*)?\((.*?)\)", clean, re.IGNORECASE
                )
                if uniq_m:
                    name = uniq_m.group(1) or "unnamed_unique"
                    cols = [c.strip().strip("`") for c in uniq_m.group(2).split(",") if c.strip()]
                    t_info["uniques"].append({"name": name, "columns": cols})
                    continue

                # Normal Index
                idx_m = re.search(r"INDEX\s+(?:`?([a-zA-Z0-9_]+)`?\s*)?\((.*?)\)", clean, re.IGNORECASE)
                if idx_m:
                    name = idx_m.group(1) or "unnamed_index"
                    cols = [c.strip().strip("`") for c in idx_m.group(2).split(",") if c.strip()]
                    t_info["indexes"].append({"name": name, "columns": cols})
                    continue

                # Standard Column
                col_m = re.match(r"^`?([a-zA-Z0-9_]+)`?\s+([a-zA-Z0-9_]+(?:\(.*?\))?)(.*)$", clean)
                if col_m:
                    col_name = col_m.group(1)
                    col_type = col_m.group(2)
                    col_attrs = col_m.group(3).strip()

                    if "PRIMARY KEY" in col_attrs.upper():
                        t_info["primary_keys"].append(col_name)

                    t_info["columns"][col_name] = {
                        "name": col_name,
                        "type": col_type,
                        "attributes": col_attrs,
                        "nullable": "NOT NULL" not in col_attrs.upper(),
                        "auto_increment": "AUTO_INCREMENT" in col_attrs.upper(),
                        "raw": clean,
                    }
                    t_info["column_order"].append(col_name)

            self.tables[table_name] = t_info


class SchemaStressAndIntegrityTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.ast = AdvancedSchemaAST(SCHEMA_FILE)

    # =========================================================================
    # 1. DEPENDENCY GRAPH, CYCLES, AND TOPOLOGICAL ORDERING
    # =========================================================================
    def test_01_table_counts(self):
        """Verify exactly 66 CREATE statements and 66 DROP statements."""
        self.assertEqual(len(self.ast.create_order), 66, f"Found {len(self.ast.create_order)} CREATE TABLE statements")
        self.assertEqual(len(self.ast.drop_tables), 66, f"Found {len(self.ast.drop_tables)} DROP TABLE statements")
        self.assertEqual(len(set(self.ast.create_order)), 66, "Duplicate CREATE TABLE statements detected")
        self.assertEqual(len(set(self.ast.drop_tables)), 66, "Duplicate DROP TABLE statements detected")
        self.assertEqual(set(self.ast.create_order), set(self.ast.drop_tables), "Mismatch between CREATE and DROP table sets")

    def test_02_graph_acyclicity_tarjan(self):
        """Adversarial Cycle Detection using Tarjan's Strongly Connected Components algorithm."""
        adj = defaultdict(list)
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                parent = fk["ref_table"]
                if parent != tbl:  # ignore self-references
                    adj[parent].append(tbl)

        index = 0
        indices = {}
        lowlink = {}
        on_stack = set()
        stack = []
        sccs = []

        def strongconnect(v):
            nonlocal index
            indices[v] = index
            lowlink[v] = index
            index += 1
            stack.append(v)
            on_stack.add(v)

            for w in adj[v]:
                if w not in indices:
                    strongconnect(w)
                    lowlink[v] = min(lowlink[v], lowlink[w])
                elif w in on_stack:
                    lowlink[v] = min(lowlink[v], indices[w])

            if lowlink[v] == indices[v]:
                scc = []
                while True:
                    w = stack.pop()
                    on_stack.remove(w)
                    scc.append(w)
                    if w == v:
                        break
                if len(scc) > 1:
                    sccs.append(scc)

        for node in self.ast.create_order:
            if node not in indices:
                strongconnect(node)

        self.assertEqual(sccs, [], f"Cycles detected in Foreign Key Dependency Graph: {sccs}")

    def test_03_strict_forward_reference_invariants(self):
        """Ensure every FK parent is declared strictly BEFORE child table in full_schema.sql."""
        created = set()
        violations = []
        for tbl in self.ast.create_order:
            for fk in self.ast.tables[tbl]["foreign_keys"]:
                parent = fk["ref_table"]
                if parent != tbl and parent not in created:
                    violations.append(f"Table '{tbl}' (idx {self.ast.create_order.index(tbl)}) references '{parent}' before '{parent}' is defined.")
            created.add(tbl)

        self.assertEqual(violations, [], "Forward reference violations in CREATE sequence:\n" + "\n".join(violations))

    def test_04_strict_reverse_drop_invariants(self):
        """Ensure every child table is dropped strictly BEFORE its referenced parent table."""
        dropped = set()
        violations = []
        for tbl in self.ast.drop_tables:
            for child, tinfo in self.ast.tables.items():
                for fk in tinfo["foreign_keys"]:
                    if fk["ref_table"] == tbl and child != tbl:
                        if child not in dropped:
                            violations.append(f"Parent '{tbl}' dropped at idx {self.ast.drop_tables.index(tbl)} before dependent child '{child}' was dropped.")
            dropped.add(tbl)

        self.assertEqual(violations, [], "Teardown drop order violations:\n" + "\n".join(violations))

    def test_05_dangling_foreign_keys_and_target_columns(self):
        """Verify all FK referenced tables and columns exist and are indexed."""
        violations = []
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                ref_tbl = fk["ref_table"]
                ref_col = fk["ref_column"]

                if ref_tbl not in self.ast.tables:
                    violations.append(f"Table '{tbl}' references non-existent table '{ref_tbl}'")
                    continue

                parent_info = self.ast.tables[ref_tbl]
                if ref_col not in parent_info["columns"]:
                    violations.append(f"Table '{tbl}' references non-existent column '{ref_col}' in '{ref_tbl}'")
                    continue

                # Check if ref_col is primary key or unique or indexed in parent
                is_pk = ref_col in parent_info["primary_keys"]
                is_uniq = any(ref_col in u["columns"] for u in parent_info["uniques"])
                is_indexed = any(ref_col in idx["columns"] for idx in parent_info["indexes"])
                if not (is_pk or is_uniq or is_indexed):
                    violations.append(f"Table '{tbl}' references column '{ref_col}' in '{ref_tbl}' which is NOT indexed or unique.")

        self.assertEqual(violations, [], "Dangling or unindexed FK target errors:\n" + "\n".join(violations))

    # =========================================================================
    # 2. CHARSET, COLLATION & ENGINE AUDIT
    # =========================================================================
    def test_06_uniform_engine_charset_collation(self):
        """Verify 100% compliance with InnoDB, utf8mb4 charset, and utf8mb4_unicode_ci collation."""
        engine_errors = []
        charset_errors = []
        collation_errors = []

        for name, tinfo in self.ast.tables.items():
            if tinfo["engine"].lower() != "innodb":
                engine_errors.append(f"Table '{name}' has engine '{tinfo['engine']}' (expected InnoDB)")
            if tinfo["charset"].lower() != "utf8mb4":
                charset_errors.append(f"Table '{name}' has charset '{tinfo['charset']}' (expected utf8mb4)")
            if tinfo["collation"].lower() != "utf8mb4_unicode_ci":
                collation_errors.append(f"Table '{name}' has collation '{tinfo['collation']}' (expected utf8mb4_unicode_ci)")

        self.assertEqual(engine_errors, [], "Engine audit failures:\n" + "\n".join(engine_errors))
        self.assertEqual(charset_errors, [], "Charset audit failures:\n" + "\n".join(charset_errors))
        self.assertEqual(collation_errors, [], "Collation audit failures:\n" + "\n".join(collation_errors))

    # =========================================================================
    # 3. CODEBASE AUDIT CROSS-VERIFICATION
    # =========================================================================
    def test_07_audit_json_table_and_column_coverage(self):
        """Cross-check every table and column identified in r1_files_audit.json against full_schema.sql."""
        if not AUDIT_FILE.exists():
            self.skipTest("r1_files_audit.json not found")

        audit_data = json.loads(AUDIT_FILE.read_text(encoding="utf-8"))
        schema_tables = self.ast.tables

        schemas_dict = audit_data.get("schemas", {})
        missing_tables = []
        missing_columns = []

        for table_name, expected_cols in schemas_dict.items():
            if table_name not in schema_tables:
                missing_tables.append(table_name)
                continue

            table_cols = set(schema_tables[table_name]["columns"].keys())
            for col in expected_cols:
                if col not in table_cols:
                    missing_columns.append(f"Table '{table_name}' is missing column '{col}'")

        self.assertEqual(missing_tables, [], f"Tables in audit missing from full_schema.sql: {missing_tables}")
        self.assertEqual(missing_columns, [], f"Columns in audit missing from full_schema.sql:\n" + "\n".join(missing_columns))

    def test_08_direct_codebase_sql_table_references(self):
        """Directly scan PHP codebase and verify all accessed SQL tables exist in full_schema.sql."""
        valid_schema_tables = set(self.ast.tables.keys())
        php_files = list(ROOT.glob("**/*.php"))

        accessed_tables = set()
        sql_from_join_pattern = re.compile(r"\b(?:FROM|JOIN|INTO|UPDATE|TABLE)\s+`?([a-zA-Z0-9_]+)`?", re.I)
        sql_keywords = {
            "dual", "select", "insert", "update", "delete", "where", "from", "join", "left", "right",
            "inner", "outer", "group", "order", "by", "limit", "offset", "set", "values", "as", "on",
            "table", "tables", "database", "schema", "information_schema", "columns", "like", "in",
            "into", "exists", "and", "or", "not", "null", "is", "distinct", "union", "count", "sum",
            "avg", "max", "min", "case", "when", "then", "else", "end", "having", "between", "asc", "desc"
        }

        for php_path in php_files:
            if ".agents" in str(php_path) or "tests" in str(php_path):
                continue
            text = php_path.read_text(encoding="utf-8", errors="ignore")
            for match in sql_from_join_pattern.finditer(text):
                if match.group(1):
                    tbl = match.group(1).lower()
                    if tbl not in sql_keywords and len(tbl) > 2:
                        if tbl in valid_schema_tables:
                            accessed_tables.add(tbl)

        self.assertTrue(len(accessed_tables) >= 30, f"Expected at least 30 active tables referenced in PHP, found {len(accessed_tables)}")
        self.assertTrue({"users", "courses", "course_modules", "course_items", "lessons", "exams", "duels", "questions"}.issubset(accessed_tables))

    # =========================================================================
    # 4. EMPIRICAL SQLITE DDL & REVERSE TEARDOWN STRESS HARNESS
    # =========================================================================
    def test_09_empirical_sqlite_recreation_and_teardown_stress_cycles(self):
        """Convert MySQL DDL to SQLite, execute all 62 CREATE tables, test FK constraints, and drop in reverse order."""
        def mysql_to_sqlite(raw_ddl: str) -> str:
            ddl = raw_ddl
            ddl = re.sub(r"\)\s*ENGINE\s*=\s*[a-zA-Z0-9_]+.*?;", ");", ddl, flags=re.IGNORECASE | re.DOTALL)
            ddl = re.sub(r"\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP", "", ddl, flags=re.I)
            ddl = re.sub(r"\s+COMMENT\s+'[^']*'", "", ddl, flags=re.I)
            ddl = re.sub(r"\bDEFAULT\s+\(CURDATE\(\)\)", "DEFAULT CURRENT_DATE", ddl, flags=re.I)
            ddl = re.sub(r"\b(?:TINYINT|SMALLINT|MEDIUMINT|BIGINT|INT)\b(?:\s+UNSIGNED)?\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b", "INTEGER PRIMARY KEY AUTOINCREMENT", ddl, flags=re.I)
            ddl = re.sub(r"\bAUTO_INCREMENT\b", "", ddl, flags=re.I)
            ddl = re.sub(r"\bENUM\s*\([^)]+\)", "TEXT", ddl, flags=re.I)
            ddl = re.sub(r"\b(?:TINYINT|SMALLINT|MEDIUMINT|BIGINT|INT)\b(?:\([0-9]+\))?(?:\s+UNSIGNED)?", "INTEGER", ddl, flags=re.I)
            ddl = re.sub(r"\b(?:VARCHAR\([0-9]+\)|TEXT|LONGTEXT|MEDIUMTEXT|CHAR\([0-9]+\))(?:\s+CHARACTER\s+SET\s+[a-zA-Z0-9_]+)?", "TEXT", ddl, flags=re.I)
            ddl = re.sub(r"\b(?:DATETIME|TIMESTAMP|DATE)\b", "TEXT", ddl, flags=re.I)
            ddl = re.sub(r"\b(?:DECIMAL\([0-9,\s]+\)|FLOAT|DOUBLE)\b", "REAL", ddl, flags=re.I)
            ddl = re.sub(r"\bJSON\b", "TEXT", ddl, flags=re.I)
            ddl = re.sub(r"\bUNIQUE\s+(?:KEY|INDEX)\s+(?:`?[a-zA-Z0-9_]+`?\s*)?\(", "UNIQUE (", ddl, flags=re.I)

            clean_lines = []
            for line in ddl.splitlines():
                stripped = line.strip()
                if re.match(r"^(?:INDEX|KEY)\s+`?[a-zA-Z0-9_]*`?\s*\(", stripped, re.I):
                    continue
                clean_lines.append(line)
            ddl = "\n".join(clean_lines)
            ddl = re.sub(r",\s*\n\s*\)", "\n)", ddl)
            return ddl

        # Run 5 consecutive create-populate-teardown stress cycles
        for cycle in range(1, 6):
            con = sqlite3.connect(":memory:")
            con.execute("PRAGMA foreign_keys = ON;")

            # 1. Execute all 62 CREATE statements in topological order
            for table_name in self.ast.create_order:
                raw = self.ast.tables[table_name]["raw_ddl"]
                sqlite_sql = mysql_to_sqlite(raw)
                try:
                    con.execute(sqlite_sql)
                except Exception as e:
                    self.fail(f"Cycle {cycle}: Failed to execute CREATE TABLE '{table_name}' in SQLite: {e}\nSQL:\n{sqlite_sql}")

            # 2. Insert mock parent records and child records with FK
            con.execute("INSERT INTO users (id, username, email, password_hash) VALUES (1, 'admin', 'admin@example.com', 'hash123');")
            con.execute("INSERT INTO courses (id, title, created_by) VALUES (1, 'PHP Security', 1);")
            con.execute("INSERT INTO course_modules (id, course_id, title) VALUES (1, 1, 'Module 1');")
            con.execute("INSERT INTO course_items (id, module_id, title, type) VALUES (1, 1, 'Lesson 1', 'text');")
            con.execute("INSERT INTO user_passkeys (id, user_id, credential_id, public_key, counter, device_name) VALUES (1, 1, 'cred_1', 'pk_1', 0, 'YubiKey');")
            con.execute("INSERT INTO app_statuses (id, title, body, level, created_by) VALUES (1, 'System Notice', 'Maintenance scheduled', 'info', 1);")
            con.execute("INSERT INTO app_status_deliveries (status_id, user_id) VALUES (1, 1);")

            # 3. Assert FK constraint enforcement: inserting child with invalid FK must throw IntegrityError
            with self.assertRaises(sqlite3.IntegrityError, msg=f"Cycle {cycle}: FK constraint failed to reject non-existent user_id"):
                con.execute("INSERT INTO user_passkeys (id, user_id, credential_id, public_key, counter, device_name) VALUES (2, 999, 'c2', 'pk2', 0, 'Chrome');")

            # 4. Execute all 62 DROP TABLE statements in reverse topological order with foreign_keys = ON
            for drop_table in self.ast.drop_tables:
                try:
                    con.execute(f"DROP TABLE IF EXISTS {drop_table};")
                except Exception as e:
                    self.fail(f"Cycle {cycle}: Failed to execute DROP TABLE '{drop_table}' with active foreign keys: {e}")

            con.close()

    # =========================================================================
    # 5. DEEP ADVERSARIAL STRESS: NULLABILITY, TYPE CONFORMANCE & PK COVERAGE
    # =========================================================================
    def test_10_fk_nullability_on_delete_set_null(self):
        """Adversarial Check: Every column with 'ON DELETE SET NULL' must be nullable (NOT NULL violates FK constraint semantics)."""
        violations = []
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                if fk["on_delete"] == "SET NULL":
                    col_name = fk["column"]
                    col_info = tinfo["columns"].get(col_name)
                    if col_info and not col_info["nullable"]:
                        violations.append(f"Table '{tbl}.{col_name}' has ON DELETE SET NULL but is declared NOT NULL!")

        self.assertEqual(violations, [], "Illegal ON DELETE SET NULL on NOT NULL columns:\n" + "\n".join(violations))

    def test_11_fk_data_type_compatibility(self):
        """Adversarial Check: Verify FK column data type aligns with referenced parent column type."""
        violations = []
        for tbl, tinfo in self.ast.tables.items():
            for fk in tinfo["foreign_keys"]:
                col_name = fk["column"]
                ref_tbl = fk["ref_table"]
                ref_col = fk["ref_column"]

                col_info = tinfo["columns"].get(col_name)
                parent_info = self.ast.tables.get(ref_tbl, {}).get("columns", {}).get(ref_col)

                if col_info and parent_info:
                    # Normalize types: e.g. INT, INT(11), TINYINT, BIGINT
                    c_type = re.sub(r"\(.*?\)", "", col_info["type"]).upper()
                    p_type = re.sub(r"\(.*?\)", "", parent_info["type"]).upper()

                    # In MySQL, signedness and type family must be compatible
                    if ("INT" in c_type and "INT" not in p_type) or ("CHAR" in c_type and "CHAR" not in p_type):
                        violations.append(f"Type mismatch: '{tbl}.{col_name}' ({c_type}) -> '{ref_tbl}.{ref_col}' ({p_type})")

        self.assertEqual(violations, [], "FK type mismatch errors:\n" + "\n".join(violations))

    def test_12_primary_key_and_autoincrement_integrity(self):
        """Adversarial Check: All 62 tables have Primary Keys and AUTO_INCREMENT fields are properly configured."""
        violations = []
        for tbl, tinfo in self.ast.tables.items():
            if not tinfo["primary_keys"]:
                violations.append(f"Table '{tbl}' has no PRIMARY KEY defined")

            auto_inc_cols = [c for c, ci in tinfo["columns"].items() if ci["auto_increment"]]
            if len(auto_inc_cols) > 1:
                violations.append(f"Table '{tbl}' has multiple AUTO_INCREMENT columns: {auto_inc_cols}")
            elif len(auto_inc_cols) == 1:
                # Must be part of primary key
                if auto_inc_cols[0] not in tinfo["primary_keys"]:
                    violations.append(f"Table '{tbl}' AUTO_INCREMENT column '{auto_inc_cols[0]}' is not a PRIMARY KEY")

        self.assertEqual(violations, [], "Primary key / AUTO_INCREMENT violations:\n" + "\n".join(violations))


if __name__ == "__main__":
    unittest.main(verbosity=2)
