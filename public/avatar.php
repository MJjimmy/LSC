<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Core\UploadStorage;
use LSC\Users\UserRepository;

$database = Database::connection();
(new Auth($database))->requireLogin();
$users = new UserRepository($database);
$uploadStorage = new UploadStorage(LSC_STORAGE . DIRECTORY_SEPARATOR . 'uploads');

$userId = (int)($_GET['user_id'] ?? 0);
$profile = $userId > 0
    ? $users->findProfile($userId, ($_SESSION['user_role'] ?? '') === 'admin')
    : false;
$absolutePath = $profile ? $uploadStorage->resolve($profile['avatar_path'] ?? null) : null;

if (!$absolutePath) {
    http_response_code(404);
    exit('Avatar not found.');
}

$extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
$contentTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
];

if (!isset($contentTypes[$extension])) {
    http_response_code(404);
    exit('Avatar not found.');
}

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=300');
readfile($absolutePath);
exit;
