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
- `views` contains page templates and shared layouts.
- `public` contains only web entry points and public assets.
- `database` and `storage/uploads` remain outside the public document root.

## Local start

1. Configure the environment variables above.
2. Make `public` the web document root.
3. Ensure `storage/uploads` is writable by PHP and backed by persistent storage in production.
4. Import the correct SQL file for a fresh or existing database.
5. Start the PHP/web-server process of your choice.

The repository includes Railway/Nixpacks configuration that serves `/app/public`.

## New collaboration workflows

### Chat attachments

Open a group, choose **Chat**, select the round `+` button, choose a supported file, and send. The message shows an image preview when appropriate and always includes an authenticated download action. Recent files also appear in the **Chat files** panel.

Supported types: PDF, DOCX, PPTX, XLSX, TXT, CSV, ZIP, PNG, JPG/JPEG, GIF, and WebP. The limit is 100 MB per group file.

### Group invitations

Group creators and administrators can open **Members → Invite people**, enter an email address, and create a link valid for seven days. Registered users receive an in-app notification. For an unregistered person, copy and send the generated link directly.

### Password recovery

The sign-in page links to **Forgot password?**. Reset tokens are random, stored only as SHA-256 hashes, expire after 60 minutes, and become invalid after one successful use. Configure `APP_URL`, `MAIL_FROM`, and a working PHP mail transport for production email delivery.

## Security notes

- New account, profile, invite, notification, chat attachment, file, and recovery actions use CSRF tokens.
- Downloads verify that the requester belongs to the file's group.
- Uploads use random stored names, extension allowlists, size limits, and image validation.
- Group management actions are limited to group creators or administrators.
- Password-reset responses do not reveal whether an email address exists.
- New registrations can only receive the student or lecturer role; administrator access must be granted deliberately in the database or through a future secured admin workflow.

## Production considerations

Railway's local filesystem can be ephemeral. Mount a persistent volume at `/app/storage/uploads` or use object storage before relying on uploaded files in production. Configure a transactional email provider or SMTP bridge for dependable reset-email delivery. Back up the database before applying migrations.

Before opening the service to public traffic, replace the repository's `php -S` start command with Railway's supported PHP/Nginx runtime (or another production application server), and configure shared rate limiting for login and password-reset routes at the edge or through a shared store. The PHP built-in server and per-process session state are not suitable substitutes for those controls.

## Verification

All PHP files pass PHP 8.2 syntax lint. Before release, run the acceptance checklist in `docs/StudySync_Project_Documentation.docx` against a staging database and verify desktop and mobile layouts.

## Documentation

The full architecture, user guide, deployment procedure, security model, and release checklist are in:

```text
docs/StudySync_Project_Documentation.docx
```
