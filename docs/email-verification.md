# Email ownership verification

## Runtime behavior

Local registration creates an unverified account. With email delivery unavailable or an invalid/non-HTTPS `APP_URL`, the public registration controller refuses to create it. After creation it sends an inbox-proof message through the existing `ResendMailer`, then renders a pending state, not an authenticated session. Rejected delivery leaves an unverified account and explicit recovery through `verify-email.php`.

The verification endpoint never consumes proof on GET. A user must submit the confirmation form with a session CSRF token. Confirmation is not sign-in. The token is 32 random bytes represented as 64 lowercase hex characters; storage holds only its SHA-256 hash, user key, normalized current-email hash, one-hour expiry and consumption timestamp. Expired, used, inactive-account or changed-email proofs are rejected. User-first transactional locking serializes consumption; success consumes sibling proofs. Resending does not invalidate earlier unexpired messages.

Public resend responses do not disclose account existence, verification state, quota or provider acceptance. Admission is bounded to six requests per IP/hour and three per normalized email/hour using the existing atomic limiter. Confirmation is bounded to ten attempts per IP/hour. Resend never returns a raw proof URL. Provider acceptance does not establish inbox delivery.

Password authentication, authoritative session revalidation, native code issuance and native exchange require `email_verified_at`. Resetting a password does not verify email. Existing Google provider validation still requires the boolean `email_verified=true`; no email-only linking is introduced. A fresh provider-created account, or an existing mapped identity whose validated provider email matches the current primary email case-insensitively, can establish ownership. A different linked-provider email cannot newly verify an unverified primary email. Already-verified account/role, initiating-account credential proof, active-user checks and native state/verifier/expiry/single-use protections are retained.

## Additive deployment preparation (not executed against production here)

`bin/prepare-deployment.php` invokes the existing provider preparation, then CLI-only `bin/prepare-email-verification.php`, then the existing queue audit. `EmailVerificationSchema` adds nullable `users.email_verified_at DEFAULT NULL` only if absent and creates `email_verification_tokens` with exact hash keys, user foreign key and expiry index. The source users schema also defaults ownership to NULL. No existing account is automatically verified; even administrators must prove ownership or be provisioned through a separately authorized controlled process. Public first-signup administrator elevation is not introduced.

Preparation is idempotent and preserves application rows. Existing incompatible columns, keys, engine or foreign-key metadata fail closed rather than being silently repaired. MySQL DDL may leave already-completed additive steps in place if a later validation fails; retry only after reviewing the mismatch. Runtime authentication does not bypass a missing verification schema. Prepare and validate before routing traffic to this revision, with the separately approved maintenance/backup process.

Forward path: additive schema preparation, verification of metadata, then application rollout. Safe operational rollback is maintenance/isolation while repairing forward. Do not revert to an older application that permits unverified access; leave additive columns/tables in place. No destructive down migration, account wipe, automatic administrator creation, sender/provider changes or queue processing is part of this change.

## Verification scope

The existing hosted `lsc-web.yml` runs the new fixture through the approved isolated PHP supervisor alongside every original stage. Tests use actual account/auth/native/Google-resolution/controllers, synthetic SQLite and an injected fake `ResendMailer` transport. They exercise proof success/expiry/replay/current-email binding, scanner-safe GET, CSRF POST, resend bounds, normal verified auth, unverified access denial, registration pending/recovery, and repeatable schema expansion with preservation/mismatch rejection. Existing password and invitation regressions remain active.

SQLite dialect adaptations and metadata checks do not establish real MySQL deployment/concurrency behavior. Database-image suites remain paused, not passed. No real email, production database, provider consent, browser login, physical phone or deployment was used to prove this feature.
