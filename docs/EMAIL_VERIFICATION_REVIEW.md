# Email verification, passwords and invitation repair

This review branch is based on `deploy/railpack` and retains its credential-bound sessions, reset revocation, atomic signup quotas, native-code protections and explicit Google-link ownership checks.

## Behaviour

- New and existing password accounts must verify mailbox ownership before gaining access. An email-format check alone does not establish that a mailbox exists.
- Verification links expire after one hour, are single-use, are stored only as hashes, and are bound to the current email and password hash.
- Verification requires choosing a new password, preventing an earlier unverified registrant from retaining a known password for someone else's mailbox.
- Passwords must contain at least 15 Unicode characters (at most 72 UTF-8 bytes), with uppercase, lowercase, a digit and punctuation/symbol. Common/repeated numeric passwords are refused.
- The shared server-side policy applies to registration, verification, password reset and password-based authentication.
- Verified Google identities can verify only their matching local email. Email-only account linking remains prohibited.
- Invitation acceptance retains recipient ownership checks and CSRF, uses the database clock for expiration, verifies membership persistence and does not report failure because an owner notification failed after commit.
- A wrong-account invitation provides a POST/CSRF-protected account switch that preserves the invitation. Repeated acceptance by a current member is idempotent; removed members cannot reuse an old accepted invitation.

## Before any production merge/deployment

1. Confirm a database backup and working HTTPS `APP_URL` plus `EMAIL_DELIVERY_ENABLED=1`, `RESEND_API_KEY` and `RESEND_FROM_EMAIL` through the approved configuration process. Never commit these values.
2. Rehearse `php bin/prepare-email-verification.php` against a disposable MySQL copy. This additive, CLI-only migration creates the token table and nullable `users.email_verified_at`; it deliberately does not mark existing accounts verified. `bin/prepare-deployment.php` includes it.
3. Notify existing password-account users that verification and a policy-compliant password are required. Existing unverified sessions will lose access; administrators are included.
4. Test actual inbox delivery and complete an invitation with real consenting test users. Provider API acceptance and synthetic transport tests do not prove inbox delivery.
5. Preserve the production CI/deployment gate. This review branch is not an instruction to deploy or merge.

## Verification scope

Local testing uses the approved portable PHP runtime with clean environment/INI, isolated SQLite fixtures and fake email transports. SQL-dialect adaptations do not prove MySQL migrations, locks or concurrent MySQL behaviour. The loopback HTTP tests exercise real controllers and views, not browser/phone behaviour. GitHub CI runs the existing release workflow on the review branch, including the new fixtures; database-image integration remains paused.

The original Desktop folder and its broken Git pointer were left unchanged during integration. Do not replace current production authentication files with the older Desktop source.
