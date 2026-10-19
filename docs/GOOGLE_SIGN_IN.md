# Google website sign-in

This ports the existing canonical OAuth provider/account implementation into the website, narrowed to Google. Microsoft is rejected; no SDK or Android browser handoff is included.

## Configuration

Use a Google OAuth **Web application** client. Server environment variables:

- `APP_URL=https://lsc.up.railway.app`
- `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` (keep server-side; never commit values)
- Authorized redirect URI must match exactly: `https://lsc.up.railway.app/oauth.php?provider=google`

HTTPS is required except localhost/127.0.0.1 in non-production. The button is disabled if configuration is missing. Google consent-screen testing/publication and allowed test users must be configured in the Google project as appropriate. No credential value needs to be printed to verify this deployment.

## Additive schema prerequisite

From the deployed application image, with the normal server database environment:

```sh
php bin/prepare-provider-schema.php
```

Run before enabling user traffic for the feature, then run again to verify idempotence. The script is CLI-only and embeds its SQL because the web image copies `bin/`, not `database/`.

It creates `oauth_identities` only if absent. It derives the integer type of `user_id` from the existing `users.user_id`, with strict type validation; it never modifies `users`. The table uses InnoDB, non-null ASCII binary provider/subject strings, exact full-column unique keys `(provider, subject)` and `(user_id, provider)`, and a foreign key to `users(user_id)`. It reads back and verifies those properties even if the table already existed. Mismatch exits nonzero and is not automatically repaired. Do not drop an existing identity table to resolve a mismatch; review its structure/data manually.

It also checks `information_schema.COLUMNS` before adding nullable `notification_queue.provider_message_id VARCHAR(255) DEFAULT NULL`. An existing column must have the expected nullable varchar shape. It never rewrites queue statuses or existing payloads. The notification queue must already exist; this is not a full application schema installer.

MySQL DDL auto-commits: if a later verification fails, an earlier additive creation may remain. Rerun only after reviewing the cause. No reset, DROP, TRUNCATE, data cleanup or role migration is performed. A backup and normal migration change controls remain appropriate. Deploys should run one schema preparer at a time.

## Account rules

- The browser submits only a CSRF-protected start request. A random, expiring, single-use state and S256 PKCE verifier bind the callback to that session and its current local user (including anonymous state).
- The server exchanges the code and retrieves Google userinfo over HTTPS, with TLS verification enabled. It requires a stable subject and boolean `email_verified=true`. It does not decode/trust an unverified ID token or accept browser identity claims.
- New users are always `student`, regardless of page role fields. They receive an unguessable password hash, not an empty password.
- Existing identities retain their local user, name, email and role even if Google contact data changes. Inactive local accounts cannot sign in or be linked.
- Matching email alone **never links accounts**. If your email already exists, sign in with your LSC password (or use Forgot password), open **your own Profile**, and choose **Connect Google**. Linking requires an authenticated active account, explicit `intent=link`, CSRF, and the same account at callback. Completion returns to `profile.php?connected=1`, not Help.
- Each local account can have only one Google identity; an identity already connected elsewhere cannot be claimed. There is no unlink/replacement UI in this change.
- Continuations allow only a same-directory PHP filename and a restricted query; otherwise they fall back to `dashboard.php`.
- Provider-returned error text and database exception details are never rendered. User-facing controlled errors are HTML-escaped. Logs contain exception class or database error code only.
- Google forms are outside password forms. The existing CSP is preserved, with only the Google authorization origin added to `form-action` on pages containing the Google form, to accommodate browsers that check POST redirect targets.

## Verification

Run on the hosted Ubuntu PHP runner (PHP CLI, mbstring and PDO SQLite required):

```sh
php -l app/Users/OAuthProvider.php
php -l app/Users/OAuthAccounts.php
php -l public/oauth.php
php -l bin/prepare-provider-schema.php
php tests/google-sign-in.php
```

Lint all changed PHP templates as part of CI too. The self-contained test uses no bootstrap/.env, network or deployment database. It tests configuration, PKCE/state replay/provider/expiry/session binding, verified identity, student-only signup, existing-email refusal, explicit admin linking/role preservation, duplicate identity constraints, inactive accounts, safe continuations, button rendering and escaping. Its SQLite PDO adapter strips only MySQL `FOR UPDATE`; it does **not** prove MySQL concurrency, schema-migration behavior, real Google exchange or hosted browser redirects.

Before claiming deployment success:

1. Run the preparer against a disposable MySQL schema with realistic existing users/queue rows, twice; compare users, roles, queue statuses/payloads before/after. Check wrong unique keys, prefix indexes, FK and nullable-column mismatches all fail closed.
2. Apply to the hosted schema under normal migration controls and read back constraints/column metadata without exposing credentials or PII.
3. Verify guest login/register Google forms, missing-CSRF rejection, unsupported provider rejection, cancelled/wrong/expired state, and absence of Microsoft UI.
4. In a real browser, complete a fresh Google signup and verify student role; test repeat login, existing-email refusal and password-login → Profile → Connect Google. Check return to Profile and preservation of an existing admin/lecturer role. Verify inactive and cross-account linking refusal.
5. Verify password login/registration still work. Do not claim the interactive consent flow passed without actually completing it.

Local PHP execution was unavailable during this port; no local Docker, package installation, deployment or Android changes were used. Hosted PHP execution and real Google/browser verification remain required.
