<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Users\AccountController;
use LSC\Users\AccountService;
use LSC\Users\PasswordResetService;
use LSC\Users\UserRepository;
use LSC\Users\EmailVerificationService;

$database = Database::connection();
$users = new UserRepository($database);
$limiter = new RateLimiter($database);
(new AccountController(new Auth($database), new AccountService($users), new PasswordResetService($database), $limiter, null, new EmailVerificationService($database)))->register();
