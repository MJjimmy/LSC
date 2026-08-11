<?php

declare(strict_types=1);

// Run this from a Hostinger cron job, e.g. every minute:
//   /usr/bin/php /home/USERNAME/domains/yourdomain.com/public_html/bin/process-notification-queue.php
// Set up under hPanel > Advanced > Cron Jobs.

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;
use LSC\Notifications\NotificationDispatcher;

$dispatcher = new NotificationDispatcher(Database::connection());
$results = $dispatcher->processPending(20);

echo sprintf(
    "[%s] Processed queue: %d sent, %d failed.\n",
    date('Y-m-d H:i:s'),
    $results['sent'],
    $results['failed']
);
