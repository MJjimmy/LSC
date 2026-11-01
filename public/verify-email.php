<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Notifications\ResendMailer;
use LSC\Users\EmailVerificationController;
use LSC\Users\EmailVerificationService;

$database = Database::connection();
(new EmailVerificationController(new EmailVerificationService($database, new ResendMailer()), new RateLimiter($database)))->show();
