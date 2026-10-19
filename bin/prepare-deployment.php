<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/prepare-provider-schema.php';
require __DIR__ . '/audit-notification-queue.php';
