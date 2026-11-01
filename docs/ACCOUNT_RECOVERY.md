# Account recovery and the verification lockout

## Why this exists

`LSC\Core\Auth::activeUser()` only accepts an account whose
`users.email_verified_at` is set, and `AccountService::authenticate()` refuses
unverified mailboxes. That is the right default — an email address alone does
not prove a mailbox exists — but it has no in-app escape hatch:

- a fresh install with `EMAIL_DELIVERY_ENABLED=0` (or missing Resend
  credentials, or an unreachable `APP_URL`) cannot complete the proof flow, so
  **nobody can sign in, including the administrator**;
- an upgrade from a database that predates ownership proof leaves every
  existing account unverified, so the same thing happens to a working
  deployment on the day it is upgraded.

`bin/account-recovery.php` is the audited way out. It writes through
`LSC\Users\AccountRecovery`, which never guesses ownership: each command is an
explicit, logged operator decision.

## Before you upgrade an existing deployment

```text
php bin/prepare-email-verification.php      # additive: adds the column/table
php bin/account-recovery.php status         # blast radius, before any traffic
```

`status` prints account, unverified and administrator counts. Treat
`Verified administrators: 0` as a release blocker.

Then choose one rollout policy **deliberately**:

1. **Verify the cohort that already proved ownership elsewhere** (recommended
   where the institution controls the mailboxes):
   ```text
   php bin/account-recovery.php verify-email --email=staff@example.ac.za
   php bin/account-recovery.php create-admin --email=admin@example.ac.za --name="Site administrator" --password-stdin
   ```
   Repeat for each account, or script it around `status`. This is slow but
   auditable.
2. **Grandfather everyone** (fastest; weakens the guarantee for one window).
   Only when the existing account list is trustworthy:
   ```sql
   -- Decide this with the data owner, not by accident.
   UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE email_verified_at IS NULL;
   ```
   Then confirm with `status`. Accounts created after this point still need
   proof, so leave email delivery working before you reopen traffic.
3. **Re-invite**: deactivate the old rows and let people register again through
   the proof flow. Use this only if the account list itself is suspect.

Whichever policy you pick, create the first administrator with `create-admin`
before opening traffic.

## Commands

All commands run on the server. `bin/` is outside the document root and each
script refuses a non-CLI SAPI.

| Command | Effect |
|---|---|
| `status` | Read-only counts (accounts, unverified, administrators, verified administrators). Never fails on a pre-migration schema; reports `schema_ready=false` instead. |
| `create-admin --email= --name=` | Creates a verified, active administrator that can sign in immediately. Refused if the address already exists. |
| `promote-admin --email=` | Grants `admin` and reactivates the account. Warns if the mailbox is still unverified. |
| `verify-email --email=` | Records that the mailbox is proven. Idempotent; this is the grandfathering step. |
| `set-password --email=` | Replaces the password, proves the mailbox if needed and revokes outstanding reset links. Existing sessions end automatically because sessions are bound to the password hash. |

Passwords are read from stdin only:

```text
# pipe (CI, provisioning scripts)
printf '%s\n' "$ADMIN_PASSWORD" | php bin/account-recovery.php create-admin \
  --email=admin@example.ac.za --name="Site administrator" --password-stdin

# interactive prompt, echo suppressed
php bin/account-recovery.php set-password --email=student@example.ac.za
```

`--password=...` is refused: arguments land in shell history, `ps` output and
CI logs. Every command accepts `--dry-run`, which reports the intended change
and writes nothing.

Exit codes: `0` success, `1` refused or failed (validation, unknown account,
database error), `2` usage error. A CLI process with an unreachable database
now also exits non-zero; that used to print a web-style message and exit `0`.

## Where the guard rails are

- Policy enforcement: `LSC\Users\PasswordPolicy` (at least 8 characters
  including a symbol such as `!`, `@` or `#`, at most 72 UTF-8 bytes, and no
  common or sequential passwords).
- No implicit backfill: `bin/prepare-email-verification.php` and
  `EmailVerificationSchema` deliberately never mark existing accounts verified.
- No email round-trip is forged: `verify-email` records an operator decision;
  it does not claim the mailbox replied.
- Regression coverage: `tests/account-recovery.php` proves the lockout is
  reproducible and that each recovery path restores sign-in, that weak
  passwords and duplicate addresses are refused, that dry runs write nothing,
  and that a pre-migration schema degrades instead of crashing.
