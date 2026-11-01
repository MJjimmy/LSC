<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use LSC\Notifications\NotificationDispatcher;
use LSC\Notifications\ResendMailer;
$n=0;
function expect(bool $ok,string $name):void{global $n;if(!$ok)throw new RuntimeException($name);$n++;}
final class QueueTestDb extends PDO {
 public array $log=[];public int $lock=1;public array $rows=[];
 public function __construct(){}
 public function prepare(string $query,array $options=[]):PDOStatement|false {$this->log[]=$query;return new QueueTestStatement($this,$query);}
 public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false {$this->log[]=$query;return new QueueTestStatement($this,$query);}
}
final class QueueTestStatement extends PDOStatement {
 public function __construct(private QueueTestDb $db,private string $sql){}
 public function execute(?array $params=null):bool{$this->db->log[]=['execute',$this->sql,$params];return true;}
 public function bindValue(string|int $param,mixed $value,int $type=PDO::PARAM_STR):bool{return true;}
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->db->rows;}
 public function fetchColumn(int $column=0):mixed{return str_contains($this->sql,'GET_LOCK')?$this->db->lock:(str_contains($this->sql,'DATABASE()')?'fixture_database':1);}
}
putenv('EMAIL_DELIVERY_ENABLED=1');putenv('RESEND_API_KEY=fixture-not-a-real-key');putenv('RESEND_FROM_EMAIL=sender@example.invalid');
putenv('NOTIFICATION_WORKER_ENABLED=0');putenv('NOTIFICATION_SEND_AFTER=2000-01-01 00:00:00');
$db=new QueueTestDb();$d=new NotificationDispatcher($db);
$r=$d->processPending();expect($db->log===[],'Disabled worker must not select or send queued messages');
putenv('NOTIFICATION_WORKER_ENABLED=1');putenv('NOTIFICATION_SEND_AFTER=');
$db=new QueueTestDb();(new NotificationDispatcher($db))->processPending();expect($db->log===[],'Missing backlog cutoff must fail closed');
putenv('NOTIFICATION_SEND_AFTER=2000-01-01 00:00:00');
putenv('NOTIFICATION_MIN_QUEUE_ID=');
$db=new QueueTestDb();(new NotificationDispatcher($db))->processPending();
expect($db->log===[],'Missing activation queue ID barrier must fail closed');
putenv('NOTIFICATION_MIN_QUEUE_ID=1');
$db=new QueueTestDb();$db->lock=0;$calls=0;
$mailer=new ResendMailer(function()use(&$calls){$calls++;return ['status'=>200,'body'=>'{"id":"fixture-accepted"}','network_error'=>false];});
(new NotificationDispatcher($db,$mailer))->processPending();
expect($calls===0,'A competing worker cannot send without the advisory lock');
expect(!str_contains(json_encode($db->log),'SELECT * FROM notification_queue'),'Lock acquisition must precede job selection');
$db=new QueueTestDb();$db->rows=[['queue_id'=>7,'channel'=>'email','recipient'=>'person@example.invalid','subject'=>'Fixture','body'=>'Fixture message','created_at'=>'2026-01-01 00:00:00','attempts'=>0]];
$r=(new NotificationDispatcher($db,$mailer))->processPending();
expect($calls===1 && $r['sent']===1,'One claimed job obtains one provider acceptance');
$log=json_encode($db->log);
expect(str_contains($log,'GET_LOCK') && str_contains($log,'RELEASE_LOCK'),'Worker must acquire and release a database advisory lock');
expect(str_contains($log,'provider_message_id'),'Provider acceptance ID must be stored');
expect(str_contains($log,'created_at >=') && str_contains($log,"channel = 'email'"),'Only new email jobs are selected, not old backlog or SMS');
expect(str_contains($log,'queue_id >= ?'),'Activation queue ID barrier must be included in selection');
echo "Passed {$n} email worker guard checks; no real messages sent.\n";
