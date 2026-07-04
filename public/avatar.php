<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Core\UploadStorage;
use StudySync\Users\UserRepository;

$database = Database::connection();
(new Auth($database))->requireLogin();
$users = new UserRepository($database);
$uploadStorage = new UploadStorage(STUDYSYNC_STORAGE . DIRECTORY_SEPARATOR . 'uploads');

$userId = (int)($_GET['user_id'] ?? 0);
$profile = $userId > 0 ? $users->findActive($userId) : false;
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
