<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/prepare-provider-schema.php';
require __DIR__ . '/prepare-email-verification.php';
require __DIR__ . '/audit-notification-queue.php';

// Read-only sign-in readiness check. Never changes data and never alters the
// exit code of the preparation steps above; it exists so an operator running
// the documented prepare step sees a lockout before opening traffic.
try {
    $status = (new LSC\Users\AccountRecovery(LSC\Core\Database::connection()))->status();
    fwrite(STDOUT, sprintf(
        "Sign-in readiness: %d account(s), %d unverified, %d verified administrator(s).\n",
        $status['accounts'], $status['unverified'], $status['verified_admins']
    ));
    if (!$status['schema_ready']) {
        fwrite(STDOUT, "WARNING: verification schema missing; run bin/prepare-email-verification.php.\n");
    } elseif ($status['verified_admins'] === 0) {
        fwrite(STDOUT, "WARNING: no verified administrator can sign in. Use bin/account-recovery.php create-admin.\n");
    } elseif ($status['unverified'] > 0) {
        fwrite(STDOUT, "Note: unverified accounts cannot sign in; see docs/ACCOUNT_RECOVERY.md before reopening traffic.\n");
    }
} catch (Throwable) {
    fwrite(STDOUT, "Sign-in readiness check skipped (database unavailable).\n");
}
