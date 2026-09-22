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

- PHP 8.2 or newer with PDO MySQL, `mbstring`, and `curl` (curl is used by the outbound email/SMS notification dispatcher)
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
| `APP_URL` | Recommended | Public base URL used in password-reset links |
| `APP_ENV` | Required in production | Set to `production` to enforce production session and error settings |
| `APP_DEBUG` | No | Set to `1` only during local debugging; never enable it in production |
| `MAIL_FROM` | For email reset delivery | Sender address used by PHP `mail()` |
| `TRUSTED_PROXY` | No | Set to `cloudflare` when LSC sits behind Cloudflare so rate limiting reads the real client IP |

A `.env.example` file at the project root is provided: copy it to `.env` and
adjust — `config/bootstrap.php` loads `.env` automatically, and variables
already set in the real environment always take precedence.

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

To promote the designated administrator, first register `jimmyadmin@gmail.com`
with a password you control, then run:

```text
database/2026_07_07_promote_jimmy_admin.sql
```

Use that email and the same registration password to sign in. Admin remains
unavailable as a public registration role.

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

The repository includes Railway/Nixpacks configuration that serves `/app/public` (through `public/router.php` so missing URLs render the application 404 page; Apache uses `ErrorDocument`, nginx uses `error_page`).

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

The sign-in page links to **Forgot password?**. Reset tokens are random, stored only as SHA-256 hashes, expire after 60 minutes, and become invalid after one successful use. Configure `APP_URL`, `MAIL_FROM`, and a working PHP mail transport for production email delivery.

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

All PHP files pass PHP 8.2 syntax lint. Before release, run the acceptance checklist in `docs/LSC_Project_Documentation.docx` against a staging database and verify desktop and mobile layouts.

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
