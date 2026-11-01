<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pageTitle = 'Help | LSC';
$isSignedIn = isset($_SESSION['user_id']);
$authSuffix = ($_GET['app'] ?? '') === 'android' ? '?app=android' : '';
if ($isSignedIn) {
    (new \LSC\Core\Auth(\LSC\Core\Database::connection()))->requireLogin();
}
ob_start();
require LSC_ROOT . '/views/help/index.php';
$page_content = ob_get_clean();
require LSC_ROOT . ($isSignedIn ? '/views/layouts/app.php' : '/views/layouts/help.php');
