#!/usr/bin/env python3
"""Static guard: the application must not drift back to MySQL-only SQL.

LSC runs on Postgres in production (Render) and on SQLite in the offline
fixtures. Every non-portable construct belongs in app/Core/SqlDialect.php,
which selects SQL by driver. This test fails if a MySQL-only construct appears
anywhere else, so a repository cannot silently stop working on the database the
project actually deploys against.

Run: python -I tests/postgres_dialect_test.py
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# Constructs that only MySQL understands.
MYSQL_ONLY = {
    "DATE_ADD(",
    "DATE_SUB(",
    "CURDATE(",
    "TIMESTAMPDIFF",
    "MONTH(",
    "YEAR(",
    "GET_LOCK(",
    "RELEASE_LOCK(",
    "DATABASE()",
    "UTC_TIMESTAMP",
    "ENGINE=InnoDB",
    "AUTO_INCREMENT",
    "ON DUPLICATE KEY",
    "INSERT IGNORE",
    "@@session",
}

# The single file allowed to contain dialect-specific SQL.
DIALECT_HOME = "app/Core/SqlDialect.php"

# Deliberate legacy-MySQL code paths. Each entry is a file and the exact number
# of occurrences that are understood and reviewed; anything new fails the test.
LEGACY_ALLOWANCES = {
    # MySQL branch of the ensure-table DDL (Postgres and SQLite have their own).
    "app/Groups/GroupRepository.php": {"AUTO_INCREMENT": 1, "ENGINE=InnoDB": 1},
    # MySQL branch of the mailbox-proof schema preparation.
    "app/Users/EmailVerificationSchema.php": {"DATABASE()": 1, "ENGINE=InnoDB": 1},
    # MySQL branch of the provider schema preparation.
    "bin/prepare-provider-schema.php": {"DATABASE()": 1, "ENGINE=InnoDB": 2},
}

REQUIRED_PG_SCHEMA = [
    "CREATE TABLE users",
    "CREATE TABLE study_groups",
    "CREATE TABLE group_members",
    "CREATE TABLE group_join_requests",
    "CREATE TABLE group_invitations",
    "CREATE TABLE tasks",
    "CREATE TABLE files",
    "CREATE TABLE messages",
    "CREATE TABLE notifications",
    "CREATE TABLE notification_queue",
    "CREATE TABLE password_reset_tokens",
    "CREATE TABLE rate_limits",
    "CREATE TABLE email_verification_tokens",
    "CREATE TABLE oauth_identities",
    "CREATE TABLE app_auth_codes",
]


def fail(message: str) -> None:
    raise SystemExit("FAIL: " + message)


def php_files() -> list[Path]:
    files: list[Path] = []
    for directory in ("app", "bin", "config", "public", "views"):
        files.extend(sorted((ROOT / directory).rglob("*.php")))
    return files


def main() -> None:
    offenders: dict[str, dict[str, int]] = {}
    for path in php_files():
        relative = path.relative_to(ROOT).as_posix()
        if relative == DIALECT_HOME:
            continue
        text = path.read_text(encoding="utf-8")
        for token in MYSQL_ONLY:
            count = text.count(token)
            if count:
                offenders.setdefault(relative, {})[token] = count

    allowed = LEGACY_ALLOWANCES
    for relative, counts in offenders.items():
        allowance = allowed.get(relative, {})
        if allowance.get("*", object()) is None and "*" in allowance:
            continue
        for token, count in counts.items():
            expected = allowance.get(token, 0)
            if count > expected:
                fail(
                    f"{relative} contains MySQL-only SQL `{token}` "
                    f"({count} occurrence(s), {expected} reviewed). "
                    f"Move it into {DIALECT_HOME} or delete it."
                )
    print(f"PASS: no unreviewed MySQL-only SQL in {len(php_files())} PHP files.")

    # Postgres must stay the declared production database.
    database = (ROOT / "app/Core/Database.php").read_text(encoding="utf-8")
    if "pgsql:host=" not in database:
        fail("app/Core/Database.php no longer connects with the pgsql DSN.")
    for name in ("PGHOST", "PGPORT", "PGDATABASE", "PGUSER", "PGPASSWORD"):
        if name not in database:
            fail(f"app/Core/Database.php lost the {name} contract.")
    if "MYSQL" in database:
        fail("app/Core/Database.php still mentions a MYSQL* environment variable.")
    print("PASS: Postgres remains the only configured database driver.")

    # The Postgres schema must hold every table the application queries.
    schema = (ROOT / "database/init-postgres.sql").read_text(encoding="utf-8")
    for table in REQUIRED_PG_SCHEMA:
        if table not in schema:
            fail(f"database/init-postgres.sql is missing `{table}`.")
    code_only = "\n".join(line for line in schema.splitlines() if not line.strip().startswith("--"))
    if re.search(r"\bboolean\b", code_only):
        fail(
            "database/init-postgres.sql declares a boolean column. The application "
            "reads 1/0 integers; keep smallint so active/read flags stay correct."
        )
    print("PASS: Postgres schema is complete and uses integer flags.")

    # Dialect helpers the repositories depend on must exist.
    dialect = (ROOT / DIALECT_HOME).read_text(encoding="utf-8")
    for helper in (
        "function plusSeconds",
        "function plusDays",
        "function minusHours",
        "function minusDays",
        "function secondsUntil",
        "function forUpdate",
        "function insertIgnoringDuplicates",
        "function upsertClause",
        "function workerLock",
        "function utcTimestamp",
        "function isTrue",
        "self::PGSQL",
    ):
        if helper not in dialect:
            fail(f"{DIALECT_HOME} lost `{helper}`.")

    # Render is the deployment target: keep the blueprint wired to Postgres.
    render = (ROOT / "render.yaml").read_text(encoding="utf-8")
    for needle in ("PGHOST", "PGPASSWORD", "fromDatabase", "healthCheckPath", "/app/storage/uploads"):
        if needle not in render:
            fail(f"render.yaml is missing `{needle}`.")
    print("PASS: SqlDialect coverage and the Render blueprint are intact.")


if __name__ == "__main__":
    main()
