<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/bootstrap.php';
use LSC\Core\Database;
use LSC\Notifications\NotificationDispatcher;
use LSC\Notifications\ResendMailer;
if(getenv('NOTIFICATION_PROCESSOR_ENABLED')!=='1') {
    echo "LSC_QUEUE_WORKER disabled\n"; exit;
}
echo "LSC_QUEUE_WORKER started; maximum one email per 15-second cycle\n";
$cycle=0;
while(true) {
    try {
        $mailer=new ResendMailer();
        if(getenv('NOTIFICATION_WORKER_ENABLED')==='1' && $mailer->enabled()) {
            $result=(new NotificationDispatcher(Database::connection(),$mailer))->processPending(1);
            if($result['sent'] || $result['failed'] || $cycle%4===0) {
                echo 'LSC_QUEUE_WORKER '.json_encode(['alive'=>true,'provider_accepted'=>$result['sent'],'failed'=>$result['failed']],JSON_THROW_ON_ERROR)."\n";
            }
        } elseif($cycle%4===0) {
            echo "LSC_QUEUE_WORKER paused; email delivery/worker flags or sender configuration not ready\n";
        }
    } catch(Throwable $e) {
        // Never log recipient, token, payload, connection string, or provider response.
        fwrite(STDERR,'LSC_QUEUE_WORKER restarting after '.get_class($e)."\n");
        exit(1); // s6 restarts with a fresh database connection.
    }
    $cycle++;
    sleep(15);
}
