# LSC 
#Learn Share Connect

LSC is a responsive PHP and MySQL collaboration platform for student groups. It combines group workspaces, task tracking, a calendar, file sharing, chat, member profiles, invitations, password recovery, notifications, admin tools, and progressive-web-app support.

## Current feature set

- Public and private study groups with join-request moderation
- Group tasks, due dates, status tracking, and calendar views
- Shared file library with authenticated downloads
- Group chat with a `+` attachment control for pictures and documents
- A chat file panel that keeps recent shared resources within reach
- Seven-day group invitations by email address and shareable link
- Student profile pages with photo, institution, course, bio, and activity statistics
- One-time password-reset tokens that expire after 60 minutes
- In-app notifications for invitations and group membership events
- Per-user notification preferences (phone number, email and SMS toggles on the profile page); outbound invite emails/SMS go through a queue processed by `bin/process-notification-queue.php`
- Safe automatic page refreshes that pause while forms or uploads are in progress
- Responsive desktop/mobile navigation and installable PWA metadata
- Admin dashboard for platform-level oversight
- Registration role selection for students and lecturers; administrator access remains database-controlled

## Requirements

- PHP 8.3 or newer for development (CI uses the distribution default) with PDO MySQL, `mbstring`, `curl` and `fileinfo`; the production image pins a patched PHP 8.5 and `composer.json` declares `^8.4`
- MySQL 8 or MariaDB 10.4+
- A web server whose document root is the `public` directory
- Write permission for `storage/uploads`

## Environment variables

| Variable | Required | Purpose |
|---|---:|---|
| `MYSQLHOST` | Yes | MySQL host name |
| `MYSQLPORT` | Yes | MySQL port |
| `MYSQLDATABASE` | Yes | Database name |
| `MYSQLUSER` | Yes | Database user |
| `MYSQLPASSWORD` | Yes | Database password |
| `APP_URL` | **Yes in production** | Absolute public origin, e.g. `https://lsc.example.ac.za`. Emailed links (password reset, invitation, email proof) are built only from this value. If it is missing or invalid the server refuses to create those links instead of trusting the request `Host` header. |
| `APP_ENV` | Yes in production | `production` enables the HTTPS redirect, HSTS, secure cookies and the strict link rules. |
| `APP_DEBUG` | No | `1` shows PHP errors outside production only. Never enable on a live deployment. |
| `APP_SHOW_RESET_LINKS` | Local only | `1` renders password-reset links on screen instead of emailing them. Ignored when `APP_ENV=production`. |
| `EMAIL_DELIVERY_ENABLED` | For any email | Must be `1` for verification, reset and invitation email to be sent. |
| `RESEND_API_KEY`, `RESEND_FROM_EMAIL` | With email delivery | Resend credentials and the verified sender address. |
| `AFRICASTALKING_USERNAME`, `AFRICASTALKING_API_KEY`, `AFRICASTALKING_SENDER_ID` | For SMS | Africa's Talking credentials; the sender ID is optional. |
| `NOTIFICATION_PROCESSOR_ENABLED` | For the queue worker | `1` starts the supervised queue worker. |
| `NOTIFICATION_WORKER_ENABLED` | For the queue worker | Second switch the worker checks before sending. |
| `NOTIFICATION_SEND_AFTER`, `NOTIFICATION_MIN_QUEUE_ID` | Optional | Queue cutover guards: only rows created at/after this UTC timestamp and at/above this ID are sent. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | For Google sign-in | OAuth client; the redirect URI is `APP_URL/oauth.php?provider=google`. Leave unset to hide the button. |
| `TRUSTED_PROXIES` | Behind a proxy | Comma-separated IPs/CIDRs (`10.0.0.0/8`), or the keywords `cloudflare` and `loopback`. Forwarded client-IP headers are ignored unless the direct peer matches. `TRUSTED_PROXY=cloudflare` still works for older deployments. |
| `PORT` | Runtime | Port the managed nginx/FPM runtime listens on. |

A `.env.example` file at the project root is provided: copy it to `.env` and
adjust — `config/bootstrap.php` loads `.env` automatically, and variables
already set in the real environment always take precedence.

Sign-in requires a proven mailbox (`users.email_verified_at`), and the proof
email needs working delivery. If delivery is misconfigured, or an upgrade
leaves existing accounts unverified, nobody — including the administrator —
can sign in. `bin/account-recovery.php` is the audited way out of that state;
see `docs/ACCOUNT_RECOVERY.md`.

## Database setup

For a new installation, create an empty database and import:

```text
database/init.sql
```

That file is the complete current schema — it already includes everything the
individual migrations add (chat-file links, group invitations, notifications,
password-reset tokens, profile fields, join requests, the lecturer role, the
outbound notification queue, and the rate-limit tables). No further SQL is
needed for a fresh install.

For an existing StudySync database, back it up first, then run each migration
you have not applied yet, in this order:

```text
database/2026_06_29_collaboration_features.sql    # only if the database predates the collaboration features
database/add_group_join_requests.sql              # join-request moderation (safe to re-run)
database/2026_07_07_add_lecturer_role.sql         # lecturer registration
database/2026_07_31_add_notification_channels.sql # phone number, email/SMS preferences, outbound queue
database/2026_08_11_add_rate_limits.sql           # rate-limiting tables
```

Administrator access is never a public registration role. Create the first
administrator from the server:

```text
php bin/account-recovery.php create-admin --email=you@example.ac.za --name="Site administrator" --password-stdin
```

The account is created already verified and active, so it can sign in even
before outbound email is configured. Existing accounts can be promoted with
`promote-admin`, and a mailbox can be proven with `verify-email` — see
`docs/ACCOUNT_RECOVERY.md`. `database/2026_07_07_promote_jimmy_admin.sql` only
grants the role to a hard-coded personal address and is kept for compatibility
history, not for new deployments.

## Application structure

- `app/Core` contains database, authentication, CSRF and upload-storage infrastructure.
- Feature folders under `app` contain controllers, services and PDO repositories.
- `views` contains page templates and shared layouts; `views/partials` holds the shared theme-toggle and cookie-disclosure snippets.
- `public` contains only web entry points and public assets, including the standalone public pages (`404.php`, `privacy.php`, `terms.php`, `offline.php`) and `router.php`, which gives the PHP built-in server the application 404 page.
- `database` and `storage/uploads` remain outside the public document root.

## Local start

1. Copy `.env.example` to `.env` and adjust it (it is loaded automatically).
2. Make `public` the web document root.
3. Ensure `storage/uploads` is writable by PHP and backed by persistent storage in production.
4. Import the correct SQL file for a fresh or existing database — `docker compose -f database/docker-compose.yml up -d` gives you MySQL 8 + phpMyAdmin with the consolidated schema already imported.
5. Start the PHP/web-server process of your choice, e.g. `php -S 0.0.0.0:8000 -t public`.

Local development only: `php -S 0.0.0.0:8000 -t public` is single-threaded and unsuitable for serving real users. The production runtime is the managed nginx + php-fpm + s6 image built from `Dockerfile.railway` and started by `railpack.json`, which serves `/app/public` and answers `/healthz`.

For a custom domain: Railway → service → **Networking → Add Domain**, then point a `CNAME` (apex domains use Railway's NS or an `A` record per Railway's dialog) at the target shown there, and set `APP_URL=https://yourdomain` so links in emails and invitations use it.

## New collaboration workflows

### Chat attachments

Open a group, choose **Chat**, select the round `+` button, choose a supported file, and send. The message shows an image preview when appropriate and always includes an authenticated download action. Recent files also appear in the **Chat files** panel.

Supported types: PDF, DOCX, PPTX, XLSX, TXT, CSV, ZIP, PNG, JPG/JPEG, GIF, and WebP. The limit is 100 MB per group file.

### Group invitations

Group creators and administrators can open **Members → Invite people**, enter an email address, and create a link valid for seven days. Registered users receive an in-app notification. For an unregistered person, copy and send the generated link directly.

### Notification preferences

Open **Profile → Notifications** to set your phone number (international format, e.g. `+27821234567`) and enable email and/or SMS as your channels. Invitations to registered users are queued on the channels they enabled (delivered by the `bin/process-notification-queue.php` worker using Resend / Africa's Talking); invitations to people without an LSC account are always emailed so they can discover the link. Password-reset emails always use the user's registered email, regardless of these preferences.

### Password recovery

The sign-in page links to **Forgot password?**. Reset tokens are random, stored only as SHA-256 hashes, expire after 60 minutes, and become invalid after one successful use. Delivery needs `APP_URL`, `EMAIL_DELIVERY_ENABLED=1` and the Resend credentials; with no usable `APP_URL` the form refuses to issue a link rather than inventing one.

## Security notes

- All database queries use prepared statements; every page renders user content through `htmlspecialchars`.
- CSRF tokens protect all mutating actions (accounts, profile, invites, notifications, tasks, files, chat, admin).
- Downloads verify that the requester belongs to the file's group; redirects are restricted to local paths.
- Uploads use random stored names, extension allowlists, size limits, image-header validation, and container-signature checks for PDF/Office/ZIP files. Uploaded files are stored outside the web root.
- Group management actions are limited to group creators or administrators.
- Passwords are hashed with `password_hash` (bcrypt) and transparently rehashed when PHP's default changes; reset tokens are stored only as SHA-256 hashes, expire after 60 minutes, and are single-use. Password-reset responses do not reveal whether an email address exists.
- Signed-in sessions expire after 30 minutes idle or 24 hours, whichever comes first.
- In production (`APP_ENV=production`) HTTP requests are redirected to HTTPS (301) and HSTS is enabled; the session cookie is Secure/HttpOnly/SameSite=Lax.
- Security headers on all pages: Content-Security-Policy, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, and `Cache-Control: no-store` for HTML (avatars set their own short-lived private cache).
- Rate limiting (database-backed fixed windows): login, registration, password recovery, plus per-user limits on group creation, join requests, invitations, chat messages, task creation, and file uploads.
- New registrations can only receive the student or lecturer role; administrator access must be granted deliberately in the database or through a future secured admin workflow.
- There are no third-party PHP dependencies to scan; the only front-end dependencies are pinned CDN assets (Bootstrap Icons 1.11.3, Google Fonts "Inter").

## Compliance notes

LSC processes student personal data (names, emails, photos, phone numbers, academic details, chat and files), so the applicable framework depends on where you deploy:

- **POPIA (South Africa)**: if you deploy for a SA institution, POPIA applies. This codebase provides the technical controls it expects: purpose limitation, data minimisation (optional profile fields), security (headers, hashed passwords, access control, rate limiting) and a privacy policy with a contact point. The operational side remains with you: designate a Responsible Person, keep a record of processing, and handle data-subject requests.
- **GDPR (EU/UK)**: the same controls map to GDPR's technical measures. If EU students use the instance you additionally need the legal bases, an Article 30 record, and processor terms with Resend/Africa's Talking.
- **Under-18 learners**: where learners under 18 may register, parental-consent and child-protection rules apply in many jurisdictions. The safest default is to restrict registration to 18+ or to obtain institutional/parental consent.
- No payment data is processed. This section is not legal advice.

## Production considerations

Railway's local filesystem can be ephemeral. Mount a persistent volume at `/app/storage/uploads` or use object storage before relying on uploaded files in production. Configure a transactional email provider or SMTP bridge for dependable reset-email delivery. Back up the database before applying migrations.

Before opening the service to public traffic, replace the repository's `php -S` start command with Railway's supported PHP/Nginx runtime (or another production application server), and configure shared rate limiting for login and password-reset routes at the edge or through a shared store. The PHP built-in server and per-process session state are not suitable substitutes for those controls.

## Verification

The `LSC Web Release` workflow (`.github/workflows/lsc-web.yml`) is the release gate; Railway deploys `deploy/railpack` only after it passes. It lints every PHP file, then runs the regression suite in `tests/` — controller fixtures on isolated SQLite, HTTP probes, browser checks and the runtime image build:

```text
php tests/account-hardening.php        # password policy, registration
php tests/email-verification.php       # mailbox proof, session revocation
php tests/app-url-hardening.php        # emailed links never trust Host
php tests/client-ip.php                # rate limiting behind a proxy
php tests/account-recovery.php         # break-glass admin recovery
```

Run one of them directly, or use `python tests/final-auth-runner.py <script>` to reproduce the harness (isolated php.ini, no network, no `mail`/`curl_exec`). Database-image integration tests remain paused; SQLite fixtures adapt MySQL dialect and do not prove MySQL locking behaviour.

Before release, also run the acceptance checklist in `docs/LSC_Project_Documentation.docx` against a staging database and verify desktop and mobile layouts.

## Documentation

The full architecture, user guide, deployment procedure, security model, and release checklist are in:

```text
docs/LSC_Project_Documentation.docx
```

For an institution-facing product overview, implementation plan, security posture,
compliance considerations, and current integration status, see:

```text
docs/LSC_Institutional_Overview.docx
docs/LSC_Institutional_Overview.md
```
