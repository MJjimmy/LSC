<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Core\UploadStorage;
use LSC\Files\FileRepository;
use LSC\Groups\GroupRepository;

$database = Database::connection();
(new Auth($database))->requireLogin();
$files = new FileRepository($database);
$groups = new GroupRepository($database);
$uploadStorage = new UploadStorage(LSC_STORAGE . DIRECTORY_SEPARATOR . 'uploads');

$fileId = (int)($_GET['id'] ?? 0);
$file = $fileId ? $files->find($fileId) : false;

if (!$file || !$groups->isMember((int)$file['group_id'], (int)$_SESSION['user_id'])) {
    http_response_code(404);
    exit('File not found.');
}

$absolutePath = $uploadStorage->resolve($file['file_path']);
if (!$absolutePath) {
    http_response_code(404);
    exit('File not found.');
}

$downloadName = str_replace(["\r", "\n", '"'], '', basename($file['file_name']));
$previewTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
$isPreview = isset($_GET['preview']) && isset($previewTypes[strtolower($file['file_type'])]);
header('Content-Type: ' . ($isPreview ? $previewTypes[strtolower($file['file_type'])] : 'application/octet-stream'));
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: ' . ($isPreview ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
