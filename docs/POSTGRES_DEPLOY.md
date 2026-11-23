# Deploying LSC on Render (Postgres)

This is the supported production path: a Render web service built from
`Dockerfile` (nginx, php-fpm, s6) talking to a Render managed Postgres
database. `render.yaml` describes the whole stack, so **New → Blueprint** in the
Render dashboard provisions it in one step.

## 1. Blueprint

`render.yaml` creates:

| Resource | Detail |
|---|---|
| `lsc-db` | Managed Postgres 16, database `lsc_db`, user `lsc_db_user` |
| `lsc-web` | Docker service from `Dockerfile`, health check `/healthz` |
| disk | `lsc-uploads` mounted at `/app/storage/uploads` (uploads survive deploys) |

`PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER` and `PGPASSWORD` are wired to the
database with `fromDatabase`, so the connection string never appears in the
repository or the dashboard as a literal.

Values you must supply in the dashboard (`sync: false`):

- `APP_URL` — absolute origin, e.g. `https://lsc.example.ac.za`
- `EMAIL_DELIVERY_ENABLED=1`, `RESEND_API_KEY`, `RESEND_FROM_EMAIL`
- optionally `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`,
  `AFRICASTALKING_*`, and the `NOTIFICATION_*` switch/cutover values

## 2. Import the schema (once)

From the Render service shell, or from your machine with the database's
external connection string:

```bash
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f database/init-postgres.sql
php bin/prepare-deployment.php
```

`bin/prepare-deployment.php` runs the additive provider and mailbox-proof
preparers, audits the notification queue and prints a sign-in readiness summary.
It is safe to re-run: it verifies shapes and refuses to repair anything.

If the database was created from an older Postgres export (boolean flag
columns), run the alignment migration first, with a backup:

```bash
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f database/2026_11_22_postgres_alignment.sql
```

## 3. Create the first administrator

Sign-in requires a proven mailbox, and proof email needs delivery. On a fresh
deployment nobody can sign in until an admin exists, so use:

```bash
php bin/account-recovery.php create-admin \
    --email=you@example.ac.za --name="Site administrator" --password-stdin
```

The account is created active and verified. See `docs/ACCOUNT_RECOVERY.md` for
`promote-admin`, `verify-email` and `set-password`.

## 4. Checks that must pass before real traffic

```bash
php bin/prepare-deployment.php        # schema + readiness summary
php bin/audit-notification-queue.php  # queue/clock/flag audit (JSON)
```

Then, from a browser: register → verify email → create a group → invite →
upload a file → create a task → reset a password. That sequence touches every
place the MySQL-to-Postgres port changed.

## 5. What changed in the port

`app/Core/SqlDialect.php` is the only file allowed to contain dialect-specific
SQL; repositories ask it for the snippet they need. `tests/postgres_dialect_test.py`
fails the build if MySQL-only SQL reappears anywhere else, and
`tests/postgres-integration.php` runs the affected repositories against a real
Postgres server in CI (job `Run against Postgres`).

Postgres-specific adaptations:

- flag columns are `smallint` 0/1 (not `boolean`), so `is_active = 1` and PHP's
  `(int)` casts stay correct
- time windows use `CURRENT_TIMESTAMP ± INTERVAL` instead of `DATE_ADD`/`DATE_SUB`
- `CURDATE()` became `CURRENT_DATE`
- `INSERT IGNORE`/`ON DUPLICATE KEY UPDATE` became `ON CONFLICT ... DO NOTHING`/`DO UPDATE`
- the worker's `GET_LOCK` became `pg_try_advisory_lock`
- `DATABASE()` became `current_database()`, `BINARY col = BINARY ?` became plain
  equality (Postgres text comparison is already case-sensitive)

## 6. Operational notes

- **Uploads:** the disk in `render.yaml` is mandatory. Without it every deploy
  discards files.
- **Proxy headers:** Render terminates TLS and forwards the client address.
  `TRUSTED_PROXIES=10.0.0.0/8` makes the rate limiter read the real client;
  without it every visitor shares one bucket and one attacker can lock out
  everyone.
- **Email:** with delivery misconfigured, verification, reset and invitations
  all fail silently closed. Watch `bin/audit-notification-queue.php`.
- **Backups:** enable Render's Postgres backups before onboarding real users.
- **Region:** keep the database and the service in the same region to avoid
  cross-region query latency.
