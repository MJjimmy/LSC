# Google / Resend integration rollout

This uses the existing PHP application and MySQL database. No Android, Docker, database service, upload-volume or SQL credential replacement is required.

## Google

Google's server-side OAuth credentials live only on the Railway web service. The PHP callback remains `/oauth.php?provider=google`. Use the browser for the initial real-account test. Google does not permit embedded WebView authentication; the Android application will need a separate browser-to-WebView return flow, not a copied client secret.

New Google accounts receive the student role. An existing LSC email is not silently linked: sign in using its existing password, open your own Profile, and choose Connect Google. This preserves account ownership and the existing local role.

The private schema-preparation task creates only `oauth_identities` and a nullable `notification_queue.provider_message_id`; it never resets users, hashes, roles or existing notification statuses.

## Resend

Existing server variables:
- `RESEND_API_KEY`
- `RESEND_FROM_EMAIL` (configured sender on the verified domain)

`EMAIL_DELIVERY_ENABLED` defaults OFF. Set it to `1` only after approval for controlled delivery testing/activation. Password-reset requests use Resend directly, with a deterministic idempotency key per token and a bounded HTTPS timeout. A rejected/uncertain email revokes its undeliverable token. Production responses never reveal reset links; unknown accounts do not cause a provider call.

No real email is sent by the automated tests. Do not use live provider keys in GitHub Actions, Android source, or repository files.

## Queued notifications

The worker additionally requires:
- `NOTIFICATION_WORKER_ENABLED=1`
- `NOTIFICATION_SEND_AFTER=YYYY-MM-DD HH:MM:SS` in UTC

Leave both unset while configuring. No worker/cron is enabled by this change. When activation is approved, choose a fresh cutoff to prevent legacy fixtures/backlog from sending. The worker processes email only, holds a MySQL connection-scoped advisory lock, increments attempts before sending, uses stable Resend idempotency keys, bounds attempts/batch size, and ignores jobs older than 23 hours to remain within Resend's 24-hour idempotency window. Existing SMS queue records remain untouched.

The legacy database status/return key `sent` means **provider-accepted**, not inbox-delivered. The provider receipt ID is retained. Delivery webhooks and automated bounce/complaint processing are not claimed by this implementation.

## Verification boundary

Hosted PHP regression tests use synthetic credentials and fake provider transports. OAuth persistence is tested with PDO SQLite while stripping only MySQL's FOR UPDATE in the test adapter; this does not prove MySQL concurrency. Worker guard tests use a PDO test double to check lock-before-selection, lock refusal, default-off and cutoff protections; do not describe them as an experimentally reproduced multi-process race test.

The existing hosted runtime/database CI remains responsible for the actual production-image build and disposable database checks. A real Google round trip and a user-approved email/receipt test are separate final acceptance steps.
