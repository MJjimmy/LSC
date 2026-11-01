<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/config/bootstrap.php';
try {
    LSC\Users\EmailVerificationSchema::prepare(LSC\Core\Database::connection());
    fwrite(STDOUT,"Email verification schema ready. Existing accounts remain unverified unless ownership was previously proven.\n");
} catch (Throwable) {
    fwrite(STDERR,"Email verification preparation failed. Review schema and permissions; no destructive repair attempted.\n");
    exit(1);
}
