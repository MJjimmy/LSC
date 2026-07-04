<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Users\AccountController;
use StudySync\Users\AccountService;
use StudySync\Users\PasswordResetService;
use StudySync\Users\UserRepository;

$database = Database::connection();
$users = new UserRepository($database);
(new AccountController(new Auth($database), new AccountService($users), new PasswordResetService($database)))->resetPassword();
