# Supervised email queue processor

The existing web container can run an s6-supervised PHP queue process; no extra Railway service is required.

Activation requires `NOTIFICATION_PROCESSOR_ENABLED=1`, `NOTIFICATION_WORKER_ENABLED=1`, `EMAIL_DELIVERY_ENABLED=1`, configured Resend sender/key, a valid UTC `NOTIFICATION_SEND_AFTER` and a positive `NOTIFICATION_MIN_QUEUE_ID`. The processor service is not linked into s6 unless explicitly enabled. The dispatcher fails closed without its timestamp and queue-ID barriers.

Before activation run the CLI-only, read-only `php bin/audit-notification-queue.php`. It prints counts, highest queue ID, database clock and readiness booleans, never recipients, bodies, invite links, keys or credentials. Set the minimum queue ID to one greater than the audited high watermark and use a fresh database UTC cutoff. Existing queued rows remain untouched and are skipped, not automatically replayed. User notification opt-outs stay respected.

The daemon processes at most one email per 15-second cycle. Existing per-job idempotency keys, three-attempt ceiling, 23-hour age cap, provider receipt IDs and MySQL connection-scoped consumer lock remain in force. Exceptions cause a sanitized log and process exit so s6 restarts with a fresh PDO connection. Routine logs contain only liveness and acceptance/failure counts. `provider_accepted` is not proof of inbox delivery.

Hosted fixture tests use a private network and synthetic provider configuration with a queue-ID barrier above all fixture records, exercise real registration/login/handoff HTTP requests, and confirm the queue process runs as UID65532 and restarts after termination without sending fixture emails.
