<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/bootstrap.php';
use LSC\Core\Database;
use LSC\Notifications\ResendMailer;
try {
    $db=Database::connection();$mailer=new ResendMailer();
    $cutoff=(string)getenv('NOTIFICATION_SEND_AFTER');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$cutoff,new DateTimeZone('UTC'));
    $validDate=$date && $date->format('Y-m-d H:i:s')===$cutoff;
    $minimum=filter_var(getenv('NOTIFICATION_MIN_QUEUE_ID'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $clock=$db->query('SELECT UTC_TIMESTAMP() AS utc_now, NOW() AS db_now, @@session.time_zone AS session_timezone')->fetch(PDO::FETCH_ASSOC);
    $out=['db_clock'=>$clock,'max_queue_id'=>(int)$db->query('SELECT COALESCE(MAX(queue_id),0) FROM notification_queue')->fetchColumn(),
        'processor_enabled'=>getenv('NOTIFICATION_PROCESSOR_ENABLED')==='1',
        'worker_enabled'=>getenv('NOTIFICATION_WORKER_ENABLED')==='1',
        'email_enabled'=>$mailer->enabled(),'sender_and_key_present'=>$mailer->configured(),
        'send_after'=>$cutoff,'minimum_queue_id'=>$minimum===false?null:$minimum,
        'queue_counts'=>$db->query('SELECT channel,status,COUNT(*) AS total FROM notification_queue GROUP BY channel,status')->fetchAll(PDO::FETCH_ASSOC),
        'invitation_counts'=>$db->query('SELECT status,COUNT(*) AS total FROM group_invitations GROUP BY status')->fetchAll(PDO::FETCH_ASSOC),
        'pending_registered_invites_email_disabled'=>(int)$db->query("SELECT COUNT(*) FROM group_invitations i JOIN users u ON u.user_id=i.invited_user_id WHERE i.status='pending' AND u.notify_email=0")->fetchColumn(),
        'provider_accepted_count'=>(int)$db->query("SELECT COUNT(*) FROM notification_queue WHERE channel='email' AND status='sent' AND provider_message_id IS NOT NULL")->fetchColumn()];
    $out['eligible_email_count']=0;
    if($minimum!==false && $validDate) {
        $q=$db->prepare("SELECT COUNT(*) FROM notification_queue WHERE channel='email' AND status='pending' AND attempts<3 AND queue_id>=? AND created_at>=? AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 23 HOUR)");
        $q->execute([$minimum,$cutoff]);$out['eligible_email_count']=(int)$q->fetchColumn();
    }
    echo 'LSC_QUEUE_AUDIT '.json_encode($out,JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) { fwrite(STDERR,'LSC_QUEUE_AUDIT failed: '.get_class($e)."\n");exit(1); }
