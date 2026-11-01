<?php
require_once dirname(__DIR__).'/config/bootstrap.php';

use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Users\EmailVerificationController;
use LSC\Users\EmailVerificationService;

$database=Database::connection();
(new EmailVerificationController(new EmailVerificationService($database),new RateLimiter($database)))->show();
