# LSC — Learn · Share · Connect

A collaboration workspace for student groups: study groups, tasks and deadlines,
a calendar, shared files, group chat, invitations, notifications and profiles —
server-rendered PHP on PostgreSQL, installable as a web app.

[![LSC Web Release](https://github.com/MJjimmy/LSC/actions/workflows/lsc-web.yml/badge.svg?branch=deploy%2Frailpack)](https://github.com/MJjimmy/LSC/actions/workflows/lsc-web.yml)
![PHP](https://img.shields.io/badge/PHP-8.4%2B-777bb3)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14%2B-336791)
![Dependencies](https://img.shields.io/badge/PHP%20dependencies-0-brightgreen)

| | |
|---|---|
| **Stack** | PHP 8.4+ (production image pins 8.5.12), nginx + php-fpm + s6, no PHP runtime dependencies |
| **Database** | PostgreSQL 14+ (Render managed Postgres) |
| **Deploy** | `render.yaml` blueprint → Docker service from `Dockerfile` |
| **Source of truth** | branch `deploy/railpack` |
| **Tests** | 42 files under `tests/`, gated by `.github/workflows/lsc-web.yml` |

> MySQL is **not** a supported target. The MySQL schema and its dated
> migrations were removed in the clean-up; `database/init-postgres.sql` is the
> only schema, and it is the one CI imports. The old files remain in git history.

## Contents

- [Quick start](#quick-start)
- [What it does](#what-it-does)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Configuration](#configuration)
- [Database setup](#database-setup)
- [Deployment](#deployment)
- [Operations](#operations)
- [Security model](#security-model)
- [Tests and CI](#tests-and-ci)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Compliance notes](#compliance-notes)
- [Documentation](#documentation)
- [Licence](#licence)

## Quick start

```bash
cp .env.example .env                       # already matches the compose database
docker compose -f database/docker-compose.yml up -d
echo 'PGSSLMODE=disable' >> .env           # the local server has no TLS
php -S 0.0.0.0:8000 -t public              # development only — single-threaded
```

Then create the first administrator. Sign-in requires a proven mailbox, so a
fresh database with no admin and no working email delivery has no way in; this
command creates an account that is already active and verified:

```bash
php bin/account-recovery.php create-admin \
    --email=you@example.ac.za --name="Site administrator" --password-stdin
```

Open <http://localhost:8000> and sign in. The compose stack imports
`database/init-postgres.sql` automatically; Adminer is on <http://localhost:8080>.

## What it does

**Groups and membership**
- Public and private study groups, with join-request moderation for private ones
- Seven-day email/link invitations for creators and administrators
- Group roles resolved from the database, never from submitted form fields

**Work and files**
- Tasks with due dates, assignment and status, plus a calendar view
- Shared file library and chat attachments (PDF, DOCX, PPTX, XLSX, TXT, CSV, ZIP, PNG, JPG, GIF, WebP; 100 MB per file)
- Group chat with a `+` attachment control and a recent-files panel
- Downloads stream only to members of the file's group

**Identity and access**
- Registration as student or lecturer; administrator is never a public role
- Email-ownership proof before sign-in, password reset with single-use hashed tokens
- Google sign-in, plus a PKCE-bound hand-off for the Android WebView app
- Profiles with photo, institution, course, bio and activity statistics

**Notifications and platform**
- In-app notifications, with per-user email/SMS preferences
- Outbound email (Resend) and SMS (Africa's Talking) via a queue worker
- Admin dashboard for platform-level oversight
- Installable PWA with an offline page; light-only responsive UI

## Architecture

```text
Browser  /  Android WebView (separate repo)
        │  HTTPS
        ▼
      nginx ──── /healthz ────► php-fpm ────► app/
                                            controller → service → repository
                                              │
                                              ├── PostgreSQL        (PDO, prepared statements)
                                              ├── storage/uploads   (outside the web root)
                                              ├── Resend            (email, via queue worker)
                                              └── Africa's Talking  (SMS, via queue worker)
```

`s6` supervises nginx, php-fpm and the optional queue worker inside one
container, which is why a worker crash restarts without taking the site down.

```text
public/         web root — one PHP entry file per page, PWA assets, no logic
views/          templates by feature, plus layouts (app, auth, help, pwa)
app/Core/       Database, Auth, Csrf, RateLimiter, AppUrl, UploadStorage, SqlDialect
app/<Feature>/  Groups, Tasks, Files, Chat, Invitations, Notifications, Users, Dashboard
config/         bootstrap.php — .env loader, autoloader, sessions, security headers
bin/            CLI: account recovery, schema preparation, queue audit, worker
runtime/        production nginx/php-fpm/s6 configuration and entrypoint
database/       init-postgres.sql (canonical) + alignment migration, compose file
tests/          PHP regression suites, Python harnesses, Node checks
storage/uploads user files — outside the document root, persistent disk in production
```

A request: `bootstrap.php` loads env and starts a session → the page wires its
dependencies by hand → `Auth` requires a signed-in, active, verified account →
`Csrf::verify()` on writes → service enforces the rules → repository runs
prepared SQL → the controller renders a view inside the layout.

**SQL dialects.** `app/Core/SqlDialect.php` is the only file allowed to contain
database-specific SQL. Repositories ask it for intervals, upserts,
insert-ignore, row locks, advisory locks and date parts. The application runs on
Postgres in production and SQLite in the offline fixtures; `tests/postgres_dialect_test.py`
fails the build if MySQL-only SQL appears anywhere else.

## Requirements

- PHP 8.3+ for development, with `pdo_pgsql`, `mbstring`, `curl` and `fileinfo`
  (the production image pins PHP 8.5.12 FPM; `composer.json` declares `^8.4`)
- PostgreSQL 14 or newer
- A web server whose document root is `public/`
- Write access to `storage/uploads`, backed by a persistent disk in production

## Configuration

| Variable | Required | Purpose |
|---|---:|---|
| `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` | Yes | Postgres connection. The CLI exits non-zero when any is missing, so a supervisor cannot mistake a dead database for success. |
| `PGSSLMODE` | Optional | Postgres TLS mode, default `require` (what Render needs). Use `disable` for a local server without TLS. |
| `APP_URL` | **Yes in production** | Absolute public origin, e.g. `https://lsc.example.ac.za`. Emailed links (reset, invitation, mailbox proof) are built **only** from this value; when it is missing or invalid the server refuses to create those links instead of trusting the request `Host` header. |
| `APP_ENV` | Yes in production | `production` enables the HTTPS redirect, HSTS, secure cookies and the strict link rules. |
| `APP_DEBUG` | No | `1` shows PHP errors outside production only. Never enable on a live deployment. |
| `APP_SHOW_RESET_LINKS` | Local only | `1` renders password-reset links on screen instead of emailing them. Ignored when `APP_ENV=production`. |
| `EMAIL_DELIVERY_ENABLED` | For any email | Must be `1` for verification, reset and invitation email to be sent. |
| `RESEND_API_KEY`, `RESEND_FROM_EMAIL` | With email delivery | Resend credentials and the verified sender address. |
| `AFRICASTALKING_USERNAME`, `AFRICASTALKING_API_KEY`, `AFRICASTALKING_SENDER_ID` | For SMS | Africa's Talking credentials; the sender ID is optional. |
| `NOTIFICATION_PROCESSOR_ENABLED` | For the queue worker | `1` starts the supervised worker inside the runtime. |
| `NOTIFICATION_WORKER_ENABLED` | For the queue worker | Second switch the worker checks before sending. |
| `NOTIFICATION_SEND_AFTER`, `NOTIFICATION_MIN_QUEUE_ID` | Optional | Queue cutover guards: only rows created at/after this UTC timestamp and at/above this ID are ever sent. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | For Google sign-in | OAuth client; redirect URI is `APP_URL/oauth.php?provider=google`. Leave unset to hide the button. |
| `TRUSTED_PROXIES` | Behind a proxy | Comma-separated IPs/CIDRs (`10.0.0.0/8`) or the keywords `cloudflare` and `loopback`. Forwarded client-IP headers are ignored unless the direct peer matches, otherwise one shared bucket locks out every user. |
| `LSC_DB_CUTOVER_MAINTENANCE` | Optional | `1` answers web traffic with 503 before any database access (`/healthz` and CLI stay up) while a migration runs. |
| `PORT` | Runtime | Port the managed nginx/php-fpm runtime listens on. |

`config/bootstrap.php` loads `.env` for local work; variables already present in
the real environment always win. Production should use the platform's variables,
not a committed file.

## Database setup

```bash
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f database/init-postgres.sql
php bin/prepare-deployment.php
```

`database/init-postgres.sql` is the complete schema: it already contains
everything the dated migrations added — chat-file links, group invitations,
notifications, reset tokens, profile fields, join requests, the lecturer role,
the notification queue, rate limits, mailbox-proof tokens, OAuth identities and
native app sign-in codes.

Two details worth knowing:

- **Flag columns are `smallint` 0/1, not `boolean`.** PHP reads them as integers;
  a Postgres `boolean` returns `t`/`f`, and `'f'` is truthy — an inactive account
  or unread notification would render as active.
- **Existing Postgres databases** created from an earlier export (the one with
  `boolean` columns) should run `database/2026_11_22_postgres_alignment.sql`
  once, with a backup. It converts those columns in place, adds the range
  checks, and creates the tables added since.

Then bootstrap administrator access (see [Quick start](#quick-start)).
`promote-admin`, `verify-email` and `set-password` cover the other cases — see
[`docs/ACCOUNT_RECOVERY.md`](docs/ACCOUNT_RECOVERY.md).

## Deployment

The supported path is Render, described end to end in
[`docs/POSTGRES_DEPLOY.md`](docs/POSTGRES_DEPLOY.md):

1. Apply `render.yaml` (*New → Blueprint*). It creates `lsc-db` (Postgres 16),
   the Docker web service from `Dockerfile`, and a disk mounted at
   `/app/storage/uploads`. `PG*` values are wired with `fromDatabase`.
2. Set the values the blueprint cannot invent: `APP_URL`, `EMAIL_DELIVERY_ENABLED`,
   `RESEND_API_KEY`, `RESEND_FROM_EMAIL` (plus any optional integrations).
3. Import the schema and prepare the deployment (`psql` + `php bin/prepare-deployment.php`).
4. Create the first administrator, then walk the smoke sequence below.

Smoke sequence after any deploy: register → verify email → create a group →
invite → upload a file → create a task → reset a password.

Custom domain: Render → service → **Settings → Custom Domains**, then point the
`CNAME` at the target Render shows and set `APP_URL` to the same origin.

**One deploy path.** `render.yaml` + `Dockerfile` is the only supported way to
run this application. The configs from the earlier hosts (`railpack.json`,
`Caddyfile`, the root `nginx.conf`) and the MariaDB image (`Dockerfile.database`)
were deleted once Render became the target, so there is nothing left to drift and
no second pipeline to keep in sync.

## Operations

| Task | Command / setting |
|---|---|
| Schema + readiness check | `php bin/prepare-deployment.php` |
| Queue and clock audit (JSON) | `php bin/audit-notification-queue.php` |
| Notification worker | `NOTIFICATION_PROCESSOR_ENABLED=1` starts it under s6; it sends one email per cycle with advisory-lock single-writer protection, a three-attempt ceiling and a 23-hour age cap |
| Maintenance window | `LSC_DB_CUTOVER_MAINTENANCE=1`, then wait for the previous instance to drain |
| Health check | `GET /healthz` (nginx → php-fpm ping) |

- **Uploads** live on a persistent disk. Without one, every deploy discards
  them; Render does not export the mount path, so the blueprint sets
  `LSC_UPLOAD_VOLUME_PATH` and `runtime/volume-permissions.php` hands the
  directory to the unprivileged application user.
- **Backups.** Enable the provider's Postgres backups and rehearse a restore
  before onboarding real users. No backup automation ships in this repository.
- **Monitoring.** Errors go to `error_log` and the platform log; there is no
  Sentry/APM integration and no alerting on queue failures yet.
- **Sessions** expire after 30 minutes idle or 24 hours total; a password change
  revokes other sessions through the password-hash fingerprint.

## Security model

- All user-supplied values reach SQL as bound parameters; remaining `query()`
  calls are static aggregates. Every rendered value passes through
  `htmlspecialchars` (126 call sites across `views/`).
- CSRF tokens are required for all mutating actions, verified with `hash_equals`
  and fail-closed when the token is absent.
- Emailed links are built only from a validated `APP_URL`; a spoofed `Host`
  header can never produce a reset link.
- Passwords use `password_hash` (bcrypt) with transparent rehashing; reset and
  mailbox-proof tokens are stored as SHA-256 hashes, expire, and are single-use.
- Sessions: strict mode, ID regeneration on login, `HttpOnly`/`SameSite=Lax`,
  `Secure` in production, plus a per-user active/verified re-check on each request.
- Uploads: random stored names, extension allowlist, size limits, image-header
  validation, container-signature checks for PDF/Office/ZIP, and storage outside
  the web root.
- Rate limiting is database-backed and shared: login, registration, password
  recovery, plus per-user limits on groups, join requests, invitations, chat,
  tasks and uploads.
- Production adds HTTPS 301, HSTS, CSP, `X-Content-Type-Options`, `X-Frame-Options: DENY`,
  `Referrer-Policy`, `Permissions-Policy` and `Cache-Control: no-store` for HTML.
- No PHP runtime dependencies, so there is no vendor tree to audit.

**Known hardening gaps** (deliberate, tracked): CSP still allows `'unsafe-inline'`
for scripts and styles; uploads are signature-checked but not run through
`finfo`; no 2FA or admin audit log; no automated backups or error monitoring.

## Tests and CI

`LSC Web Release` (`.github/workflows/lsc-web.yml`) is the repository's only
workflow and the release gate — triggered on pull requests and pushes to
`deploy/railpack`, or manually from the Actions tab (**Run workflow**). Jobs:

| Job | What it proves |
|---|---|
| Check code | PHP lint of every file, Python syntax, deployment/runtime config, and the **dialect guard** |
| Test website | PHP regression suites (SQLite fixtures), HTTP probes, browser and theme checks |
| Run against Postgres | Imports `init-postgres.sql` into `postgres:16`, re-runs the alignment migration (proving it is a no-op on a fresh schema), runs `tests/postgres-integration.php` |
| Verify web build | Builds `Dockerfile`, checks PHP version and extensions, runs the runtime smoke, volume and cutover-gate tests |
| Release gate | Fails the release unless all of the above succeed |

Run locally:

```bash
# self-contained PHP regressions
php tests/account-hardening.php         # password policy, registration
php tests/app-url-hardening.php         # emailed links never trust Host
php tests/client-ip.php                 # rate limiting behind a proxy
php tests/account-recovery.php          # break-glass admin recovery

# suites that need the isolated harness (own scratch, no network, no mail)
python tests/final-auth-runner.py email-verification.php final-auth.php ui-fix.php

# static and postgres checks
python -I tests/postgres_dialect_test.py
LSC_POSTGRES_TEST=1 php tests/postgres-integration.php   # after importing the schema

node tests/light-theme-test.cjs && node tests/stage2-filter.cjs
```

The SQLite fixtures adapt only the SQL dialect; they do not prove Postgres
locking. That is what `tests/postgres-integration.php` covers — rate limiter,
groups and join requests, invitations, password reset, user repository, files,
tasks/calendar, notification queue, dashboard aggregates and both schema
preparers, all against real Postgres.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `503 LSC is temporarily unavailable` | `LSC_DB_CUTOVER_MAINTENANCE=1` | Clear the variable after the migration |
| 503 on every page, CLI exits 1 | Missing `PG*` variables or wrong `PGSSLMODE` | Set the connection values; `disable` only for a local server |
| Nobody can sign in, including admins | No verified mailbox and email delivery is off | `php bin/account-recovery.php create-admin …` (or `verify-email`) |
| Uploads disappear after a deploy | No persistent disk | Mount the disk at `/app/storage/uploads` |
| Everyone is rate-limited at once | `TRUSTED_PROXIES` not set, so all traffic looks like one proxy IP | Set it to the provider's proxy range |
| `403 Invalid CSRF token` | Stale tab, or a session cookie dropped by a scheme/host change | Reload, sign in again, keep one canonical origin |
| `SQLSTATE[42P01]` / missing table | Schema not imported or alignment migration not run | Import `init-postgres.sql`, then run the alignment script |
| Emails never arrive | `EMAIL_DELIVERY_ENABLED` or worker switches off | Check `php bin/audit-notification-queue.php` |

## Contributing

- **Keep SQL portable.** Put dialect differences in `app/Core/SqlDialect.php`;
  `tests/postgres_dialect_test.py` fails on MySQL-only SQL elsewhere.
- **Escape at the edge.** Views receive data and escape with `htmlspecialchars`;
  never echo a superglobal.
- **Add a regression test** for behaviour changes, in the same style as the
  existing suites (isolated fixture, no network, synthetic data).
- **No new PHP dependencies** without discussion — the zero-dependency design is
  deliberate.
- Before pushing: `php -l` on changed files and the relevant suite above.
- Keep `.env`, real credentials and uploaded user files out of git.

## Compliance notes

LSC processes student personal data (names, emails, photos, phone numbers,
academic details, chat and files), so the applicable framework depends on where
you deploy:

- **POPIA (South Africa)** — the codebase provides the expected technical
  controls (purpose limitation, data minimisation, hashed passwords, access
  control, rate limiting, privacy policy). The operational side stays with you:
  designate a Responsible Person, keep a record of processing, handle
  data-subject requests.
- **GDPR (EU/UK)** — the same controls map to its technical measures; you also
  need legal bases, an Article 30 record, and processor terms with Resend and
  Africa's Talking.
- **Under-18 learners** — parental-consent and child-protection rules apply in
  many jurisdictions; restricting registration to 18+ is the safest default.
- No payment data is processed. This section is not legal advice.

## Documentation

| Document | Contents |
|---|---|
| [`docs/POSTGRES_DEPLOY.md`](docs/POSTGRES_DEPLOY.md) | Render walkthrough: blueprint, schema, first admin, smoke test |
| [`docs/ACCOUNT_RECOVERY.md`](docs/ACCOUNT_RECOVERY.md) | Break-glass admin creation, promotion, mailbox proof |
| [`docs/NOTIFICATION_WORKER.md`](docs/NOTIFICATION_WORKER.md) | Queue worker behaviour and safeguards |
| [`docs/email-verification.md`](docs/email-verification.md) | Mailbox-proof design |
| [`docs/GOOGLE_SIGN_IN.md`](docs/GOOGLE_SIGN_IN.md) · [`docs/ANDROID_SIGN_IN.md`](docs/ANDROID_SIGN_IN.md) | OAuth setup and the native hand-off |
| [`docs/LSC_Institutional_Overview.md`](docs/LSC_Institutional_Overview.md) | Institution-facing overview |

Post-mortems and generated Word files from earlier deployments (`MYSQL_CUTOVER`,
`RAILPACK_REVIEW`, `RUNTIME_FIX`, `UI-FIX`, the `.docx` guides) were removed in
the clean-up; git history keeps them if you need the archaeology.

## Licence

No licence file is present in this repository yet. Until one is added, the code
is all-rights-reserved by default — ask the maintainer before reusing it.
