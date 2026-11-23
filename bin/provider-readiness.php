<?php

declare(strict_types=1);

// Operator diagnostic only: never expose it over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;
use LSC\Core\SqlDialect;
use LSC\Users\OAuthProvider;

// Presence and public callback only: no credential value is printed, so the
// output is safe to paste into a deployment log or an issue.
$report = OAuthProvider::readiness('google');
$problems = $report['problems'];

$schemaReady = false;
try {
    $database = Database::connection();
    $schemaReady = SqlDialect::isSqlite($database)
        ? (int)$database->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='oauth_identities'")->fetchColumn() === 1
        : $database->query("SELECT to_regclass('public.oauth_identities')")->fetchColumn() !== null;
    if (!$schemaReady) {
        $problems[] = 'the oauth_identities table is missing; run php bin/prepare-provider-schema.php';
    }
} catch (Throwable) {
    $problems[] = 'the database is unavailable; check the PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD variables';
}

fwrite(STDOUT, "Google sign-in readiness\n");
fwrite(STDOUT, sprintf("  APP_URL               %s\n", $report['app_url'] ?? '(missing or invalid)'));
fwrite(STDOUT, sprintf("  GOOGLE_CLIENT_ID      %s\n", $report['client_id'] ? 'set' : 'MISSING'));
fwrite(STDOUT, sprintf("  GOOGLE_CLIENT_SECRET  %s\n", $report['client_secret'] ? 'set' : 'MISSING'));
fwrite(STDOUT, sprintf("  oauth_identities      %s\n", $schemaReady ? 'present' : 'MISSING'));
fwrite(STDOUT, sprintf("  button enabled        %s\n", $report['enabled'] && $schemaReady ? 'yes' : 'no'));

if ($report['redirect_uri'] !== null) {
    fwrite(STDOUT, "\nAuthorised redirect URI to register on the Google OAuth client:\n");
    fwrite(STDOUT, '  ' . $report['redirect_uri'] . "\n");
}

if ($problems !== []) {
    fwrite(STDERR, "\nBlocking problems:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, '  - ' . $problem . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "\nGoogle sign-in is configured. If Google still refuses the callback, check that the\n");
fwrite(STDOUT, "redirect URI above is listed on the OAuth client and that the consent screen\n");
fwrite(STDOUT, "allows the account you are testing with.\n");
