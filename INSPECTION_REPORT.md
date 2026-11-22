# LSC — Inspection Report

**Repository:** `MJjimmy/LSC` · **Branch inspected:** `arena/35a22b88-lsc` (from `deploy/railpack` @ `a4a6fbf` "mod render", 2026-11-22)
**Date:** 2026-11-22 · **Working tree:** clean, no uncommitted changes
**Scope:** full static inspection of code, config, schema, tests, CI and docs. No PHP or Docker runtime was available in this sandbox (no `php`, no `docker`, no apt permission), so PHP lint/unit tests and container builds were verified via CI results and the Python/Node test subsets that run without PHP.

---

## 0. Status of the findings (update 2026-11-22)

The deployment target is **Render with managed Postgres**, so the findings
below were acted on directly:

| Finding | Status |
|---|---|
| F1 — MySQL dialect in application code | **Fixed.** All dialect-specific SQL now lives in `app/Core/SqlDialect.php`; repositories are dialect-neutral. `tests/postgres-integration.php` exercises them against a real `postgres:16` in CI. |
| F2 — README/`.env.example` documented MySQL | **Fixed.** Postgres variables, `database/init-postgres.sql`, `PGSSLMODE` and a Render walkthrough (`docs/POSTGRES_DEPLOY.md`). |
| F3 — no production verification recorded | **Tooling added.** `render.yaml` + the deploy doc list the exact steps and the smoke sequence; issue #2 still needs a human to record the result. |
| F4 — single squashed commit | Unchanged (needs the real history). |
| F5 — workflow trigger drift | Partially fixed: the Postgres job is wired into the release gate; `ci.yml` still targets `main`. |
| F6 — operational prerequisites | Documented; `render.yaml` now declares the upload disk and `LSC_UPLOAD_VOLUME_PATH`. |
| F7 — minor hygiene | Unchanged. |

---

## 1. What LSC is

LSC (Learn · Share · Connect) is a dependency-free PHP collaboration platform for student groups: study groups with join-request moderation, tasks + calendar, shared file library, group chat with attachments, email/link invitations, profiles, notifications (email/SMS queue), password recovery, Google sign-in, admin dashboard, and PWA support.

| Area | Size |
|---|---|
| `app/` (controllers, services, repositories, core) | ~4,350 LOC PHP |
| `public/` (28 web entry points) | ~1,430 LOC |
| `views/` (templates + layouts) | ~3,500 LOC |
| `config/bootstrap.php`, `bin/` CLI tools, `runtime/` (nginx/php-fpm/s6) | ~830 LOC |
| `tests/` (42 test files) | ~2,740 LOC |
| Total tracked files | 187 (2.0 MB incl. docs) |

**Stack:** PHP 8.4 declared (`composer.json`, zero third-party PHP dependencies), production image pinned to PHP 8.5.12 on Chainguard Wolfi with nginx + php-fpm supervised by s6 (`Dockerfile.railway`, `railpack.json`). Database is **PostgreSQL** (`pdo_pgsql`, `PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD`, `database/init-postgres.sql`, `render.yaml`). Front-end is Bootstrap Icons 1.11.3 + Google Fonts "Inter" via CDN.

**Architecture:** vanilla PHP, one entry point per page under `public/`, shared bootstrap in `config/bootstrap.php` (`.env` loader, autoloader, session + security headers), feature folders under `app/` (`Core`, `Users`, `Groups`, `Tasks`, `Files`, `Chat`, `Invitations`, `Notifications`, `Dashboard`) with PDO repositories. Uploads live outside the web root in `storage/uploads`.

---

## 2. Health checks performed

| Check | Result |
|---|---|
| Git status / history | Clean tree. History is a **single squashed commit** (`a4a6fbf`) — no bisect/blame trail. |
| Secrets scan (code, SQL, workflows) | ✅ No hard-coded credentials. `.env` is git-ignored and absent; only `.env.example` is tracked. |
| Debug leftovers (`TODO/FIXME/var_dump/print_r/die`, raw `echo $_GET/$_POST`) | ✅ None found. |
| SQL injection surface | ✅ 124 `prepare()` calls; remaining `query()` calls are static `COUNT(*)` aggregates with no user input. No string-interpolated SQL found. |
| Output escaping | ✅ 126 `htmlspecialchars` uses in `views/`; no unescaped `echo $var` found. |
| Python unit suites (no PHP needed) | ✅ 6 suites pass: `app_auth_navigation`, `group_wiring`, `php_runtime_patch`, `railpack_config` (6 tests), `runtime_config` (4 tests), `ui_navigation`. |
| Node suites | ✅ `light-theme-test.cjs` (12 cases) and `stage2-filter.cjs` pass. `stage2-browser.cjs` fails in this sandbox only (expects a pinned git object not present in the shallow clone). |
| Docker-dependent suites (`database/runtime_smoke/volume/provider_schema/mysql_transfer/cutover_gate`) | ⚠️ Cannot run here — no Docker in sandbox. Covered by CI instead. |
| PHP lint + PHP regression suites | ⚠️ Cannot run here — no PHP in sandbox. Covered by CI (see §4). |
| GitHub Actions (latest, `deploy/railpack`, 2026-11-22 05:46 UTC) | ✅ **All green:** LSC Web Release, Combined admin/auth checks, Runtime review checks. One earlier LSC Web Release failure (05:09 run) was fixed by the subsequent `pdo_pgsql` commit. |
| Open issues | 1 open: **#2 "Record Railway production deployment verification"** (2026-10-27) — production verification still outstanding. |

---

## 3. Security posture — strong

The codebase shows deliberate, above-average hardening:

- **CSRF:** fail-closed verifier (`app/Core/Csrf.php`) — every POST requires a session token compared with `hash_equals`; empty/missing tokens are rejected (covered by `security_csrf_regression.php`).
- **Emailed-link integrity:** `AppUrl` never trusts the `Host` header in production — links are built only from validated `APP_URL`, otherwise refused. This closes a classic password-reset poisoning primitive (covered by `app-url-hardening.php`).
- **Sessions:** strict mode, `HttpOnly`/`SameSite=Lax`/`Secure` in production, 30-minute idle + 24-hour absolute expiry, session-ID regeneration on login, and a password-hash fingerprint so a password change revokes other sessions.
- **Passwords & tokens:** `password_hash` (bcrypt) with transparent rehash, password policy, reset tokens stored only as SHA-256 hashes, 60-minute expiry, single-use, enumeration-safe responses.
- **Uploads:** extension allowlist + image-header validation + magic-byte checks for PDF/Office/ZIP, random stored names, 100 MB group-file / 5 MB avatar limits, files outside the web root, path-traversal-safe resolver.
- **Rate limiting:** database-backed fixed windows for login/registration/recovery plus per-user limits on groups, invites, chat, tasks and uploads; proxy-aware client-IP resolution (`TRUSTED_PROXIES`, Cloudflare/loopback keywords).
- **Headers & transport:** CSP, `nosniff`, `DENY` framing, strict referrer/permissions policies, `no-store` on HTML, HTTPS 301 + HSTS in production.
- **Input boundaries:** scalar-only `$_GET/$_POST` enforcement (HTTP 400 on array shapes), safe local-redirect allowlist, group-membership checks on downloads/tasks, admin-only routes, and a 503 cutover gate that stops web traffic before any DB access during migration.
- **Admin bootstrap:** no public admin registration; first admin is created via audited CLI (`bin/account-recovery.php`), which also handles the "nobody can sign in because email is unverified" lockout.

---

## 4. CI & tests

Seven workflows in `.github/workflows/`: `lsc-web.yml` (release gate — lint + regression suite + image build), `final-integration.yml`, `railpack-review.yml`, `php-runtime-patch.yml`, `stage2-security.yml`, `security-audit.yml`, `ci.yml`. Most trigger on `deploy/railpack` (some also on `ui-fix`); `ci.yml` targets `main`, which does not exist in this clone — worth aligning.

Test design is notable: PHP suites run against isolated SQLite fixtures (dialect-adapted), plus HTTP probes, browser checks and runtime-image builds. The README is candid that SQLite fixtures **do not prove MySQL/Postgres locking behaviour** — relevant to §5.

---

## 5. Findings (ordered by risk)

### 🔴 F1 — PostgreSQL migration is incomplete: app code still speaks MySQL dialect
`Database.php`, `composer.json`, `Dockerfile.railway`, `render.yaml`, `.env.example` and `database/init-postgres.sql` all target Postgres, but the application layer was never ported. The code only branches between `sqlite` (tests) and everything-else-assumed-MySQL, so on a real Postgres connection the MySQL branch executes and fails:

| File | MySQL-only construct |
|---|---|
| `app/Core/RateLimiter.php` | `DATE_ADD(NOW(), INTERVAL ? SECOND)`, `IF(...)`, `ON DUPLICATE KEY UPDATE`, `DATE_SUB`, `TIMESTAMPDIFF` |
| `app/Dashboard/DashboardRepository.php` | `CURDATE()` |
| `app/Invitations/InvitationRepository.php` | `DATE_ADD(NOW(), INTERVAL 7 DAY)` |
| `app/Notifications/NotificationDispatcher.php` | `SELECT DATABASE()`, `DATE_SUB(UTC_TIMESTAMP(), ...)` |
| `app/Users/EmailVerificationSchema.php` | `SELECT DATABASE()`, `ENGINE=InnoDB`, and throws `Unsupported verification schema driver` for anything that isn't `mysql`/`sqlite` — i.e. **always throws on Postgres** |
| `app/Groups/GroupRepository.php` | Runtime `CREATE TABLE ... AUTO_INCREMENT ... ENGINE=InnoDB` |
| `app/Users/PasswordResetService.php` | `DATE_ADD(NOW(), INTERVAL 60 MINUTE)` (non-sqlite branch) |
| `app/Core/Database.php` | Error message still says "check the `MYSQL*` environment variables" |

**Impact:** rate limiting, dashboard stats, invitations, notification queue, email verification, join-request table creation and password reset will error against Postgres. CI stays green because tests run on SQLite. **Fix:** add a `pgsql` dialect branch (or a small query-builder abstraction) for each construct above, extend `EmailVerificationSchema` to support `pgsql`, and add at least one Postgres-backed integration test (the paused database-image tests in `tests/*runtime_test.py` are the natural vehicle).

### 🟠 F2 — README documents MySQL; deployment docs contradict the code
README still specifies `MYSQLHOST/MYSQLPORT/...`, `database/init.sql`, MySQL docker-compose and "MySQL 8 or MariaDB 10.4+" — while `.env.example`, `render.yaml` and the runtime all use Postgres. An operator following the README will configure the wrong variables and import the wrong schema. **Fix:** rewrite the README's requirements/env/database sections for Postgres (or state explicitly that MySQL is legacy).

### 🟠 F3 — Production verification still open (issue #2)
The only open issue asks for recorded Railway production deployment verification. Combined with F1, there is currently **no evidence the app has run end-to-end against Postgres in production**. Recommend closing this only after F1 is fixed and a staging smoke test (login → group → task → upload → invite → reset) is recorded.

### 🟡 F4 — Single squashed commit; no history
The entire codebase landed as one commit (`a4a6fbf "mod render"`). Any future regression cannot be bisected, and `git blame` is useless. Consider importing the real history (or at least tagging milestones) going forward.

### 🟡 F5 — Workflow trigger drift
`ci.yml` triggers on `main`, which doesn't exist here (default branch is `deploy/railpack`); `security-audit.yml` triggers on a stale `security/2026-10-22-hardening` branch. Align triggers with the actual branching model.

### 🟡 F6 — Operational prerequisites (documented, but easy to miss)
- Uploads need a persistent volume at `/app/storage/uploads` (Railway/Render filesystems are ephemeral).
- Sign-in requires a verified mailbox, which requires working Resend delivery (`EMAIL_DELIVERY_ENABLED=1`, `RESEND_*`) plus a valid `APP_URL` — misconfiguration locks out *everyone*, including admins (mitigated by `bin/account-recovery.php`).
- SMS needs Africa's Talking credentials; Google sign-in needs OAuth client config.
- POPIA/GDPR operational duties (Responsible Person, records of processing, processor terms) remain with the operator — the README says this clearly.

### 🟢 F7 — Minor hygiene
- `docs/` ships two binary `.docx` files (~470 KB) alongside Markdown equivalents; consider generating them in CI instead of committing binaries.
- `database/2026_07_07_promote_jimmy_admin.sql` grants admin to a hard-coded personal address — flagged in the README as legacy, but consider removing it.
- `render.yaml` and `railpack.json` both exist (Render vs Railway) — fine if intentional, but pick one canonical deploy path to avoid config drift.

---

## 6. Verdict

LSC is a **well-structured, security-conscious, dependency-free PHP application** with a strong test/CI culture and honest documentation. Its security controls (CSRF, AppUrl fail-closed links, upload validation, rate limiting, session hardening) are genuinely good.

**The one blocking issue is F1:** the Postgres migration stopped at the infrastructure layer. Until the MySQL dialect constructs in the app layer are ported to Postgres, the app cannot run correctly on its declared production database — and the SQLite-based test suite cannot catch this. Fix F1, correct the README (F2), then close out production verification (F3).

*Inspection method: static analysis + runnable Python/Node test subsets + GitHub Actions history. PHP/Docker execution was unavailable in this sandbox; those results are taken from CI.*
