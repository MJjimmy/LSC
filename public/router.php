<?php
// Router for the PHP built-in server (used by the Railway start command).
// Existing files are served normally (return false); anything missing gets
// the application 404 page instead of the built-in server's plain one.
//
//   php -S 0.0.0.0:$PORT -t public public/router.php

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && !is_file($file)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return true;
}

return false;
