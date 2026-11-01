<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Real invitation services, synthetic SQLite. Only MySQL dialect spelling is adapted.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
final class InvitationFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $query = str_replace(['DATE_ADD(NOW(), INTERVAL 7 DAY)', ' FOR UPDATE', 'INSERT IGNORE INTO'], ["datetime('now', '+7 days')", '', 'INSERT OR IGNORE INTO'], $query);
        return parent::prepare($query, $options);
    }
}
function inviteFixture(): array {
    $db = new InvitationFixturePDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,is_active INTEGER DEFAULT 1,avatar_path TEXT,email_verified_at TEXT DEFAULT CURRENT_TIMESTAMP,phone_number TEXT,notify_email INTEGER DEFAULT 1,notify_sms INTEGER DEFAULT 0)');
    $db->exec('CREATE TABLE study_groups(group_id INTEGER PRIMARY KEY,group_name TEXT,description TEXT,created_by INTEGER REFERENCES users(user_id),is_private INTEGER DEFAULT 0)');
    $db->exec('CREATE TABLE group_members(member_id INTEGER PRIMARY KEY,group_id INTEGER REFERENCES study_groups(group_id),user_id INTEGER REFERENCES users(user_id),UNIQUE(group_id,user_id))');
    $db->exec("CREATE TABLE group_invitations(invitation_id INTEGER PRIMARY KEY,group_id INTEGER REFERENCES study_groups(group_id),invited_by INTEGER REFERENCES users(user_id),invited_user_id INTEGER REFERENCES users(user_id),email TEXT,token TEXT UNIQUE,status TEXT DEFAULT 'pending',created_at TEXT DEFAULT CURRENT_TIMESTAMP,expires_at TEXT)");
    $db->exec('CREATE TABLE notifications(notification_id INTEGER PRIMARY KEY,user_id INTEGER,type TEXT,title TEXT,body TEXT,link TEXT,is_read INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $db->exec("CREATE TABLE notification_queue(queue_id INTEGER PRIMARY KEY,user_id INTEGER,channel TEXT,recipient TEXT,subject TEXT,body TEXT,status TEXT DEFAULT 'pending',attempts INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $insert=$db->prepare('INSERT INTO users(user_id,name,email,password_hash,role) VALUES(?,?,?,?,?)');
    foreach ([1=>'owner',2=>'recipient',3=>'other'] as $id=>$name) $insert->execute([$id,'Synthetic '.$name,$name.'@example.invalid',password_hash('Synthetic-fixture-password!', PASSWORD_DEFAULT),'student']);
    $db->exec("INSERT INTO study_groups(group_id,group_name,description,created_by) VALUES(1,'Synthetic group','Offline fixture',1)");
    $db->exec('INSERT INTO group_members(group_id,user_id) VALUES(1,1)');
    $groups=new LSC\Groups\GroupRepository($db); $notes=new LSC\Notifications\NotificationRepository($db);
    $repo=new LSC\Invitations\InvitationRepository($db);
    $service=new LSC\Invitations\InvitationService($db,$repo,$groups,new LSC\Groups\GroupService($db,$groups,$notes),$notes,new LSC\Notifications\NotificationDispatcher($db));
    return [$db,$repo,$service];
}
$passed=$failed=0;
function inviteCheck(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
function inviteTest(string $name,callable $call):void {global $passed,$failed;try{$call();$passed++;echo "PASS: {$name}\n";}catch(Throwable $e){$failed++;echo "FAIL: {$name}: ".get_class($e).': '.$e->getMessage()."\n";}}
inviteTest('resending a pending invitation preserves the first emailed link',function():void{
    [$db,$repo,$service]=inviteFixture();
    $first=$service->create(1,1,'student','recipient@example.invalid');
    $second=$service->create(1,1,'student','recipient@example.invalid');
    inviteCheck($first['ok'] && $second['ok'],'fixture invitation was not created');
    $row=$repo->findByToken($first['token']);
    inviteCheck($row && $row['status']==='pending' && strtotime($row['expires_at'])>time(),'first emailed link was invalidated by a resend');
    inviteCheck($first['token']===$second['token'],'resend replaced the live token');
    inviteCheck((int)$db->query('SELECT COUNT(*) FROM group_invitations')->fetchColumn()===1,'resend created another invitation record');
});
inviteTest('a correct recipient can accept once but a different account cannot',function():void{
    [$db,$repo,$service]=inviteFixture(); $invite=$service->create(1,1,'student','recipient@example.invalid');
    inviteCheck(!$service->accept($invite['token'],3)['ok'],'another account accepted the invitation');
    inviteCheck($service->accept($invite['token'],2)['ok'],'correct recipient could not accept');
    inviteCheck(!$service->accept($invite['token'],2)['ok'],'accepted invitation replayed');
    inviteCheck((int)$db->query('SELECT COUNT(*) FROM group_members WHERE user_id=2 AND group_id=1')->fetchColumn()===1,'membership was not created exactly once');
});
inviteTest('an actually expired invitation stays unusable and a fresh invitation has a new token',function():void{
    [$db,$repo,$service]=inviteFixture(); $old=$service->create(1,1,'student','recipient@example.invalid');
    $db->exec("UPDATE group_invitations SET expires_at='2000-01-01 00:00:00'");
    inviteCheck(!$service->accept($old['token'],2)['ok'],'expired invitation was accepted');
    $fresh=$service->create(1,1,'student','recipient@example.invalid');
    inviteCheck($fresh['ok'] && $fresh['token']!==$old['token'],'expired token was reused');
    inviteCheck($service->accept($fresh['token'],2)['ok'],'fresh invitation was rejected');
});
inviteTest('revoked invitation feedback is not falsely labelled as expiry',function():void{
    $parent=realpath((string)getenv('FINAL_AUTH_TEMP')); inviteCheck($parent!==false,'owned test scratch is missing');
    $owned=$parent.'/invite-view-'.bin2hex(random_bytes(6));
    mkdir($owned.'/views/layouts',0700,true);
    file_put_contents($owned.'/views/layouts/app.php','<?php echo $page_content;');
    define('LSC_ROOT',$owned);
    try {
        $invite=['status'=>'declined','expires_at'=>'2099-01-01 00:00:00'];$isAvailable=false;$error='';$token=str_repeat('a',64);
        ob_start(); ob_start(); require dirname(__DIR__).'/views/invitations/show.php'; $html=(string)ob_get_clean();
        inviteCheck(str_contains(strtolower($html),'revoked') || str_contains(strtolower($html),'replaced'),'revoked invitation has no distinct explanation');
        inviteCheck(!str_contains(strtolower($html),'expired'),'revoked invitation was called expired');
    } finally {unlink($owned.'/views/layouts/app.php');rmdir($owned.'/views/layouts');rmdir($owned.'/views');rmdir($owned);}
});
echo json_encode(['passed'=>$passed,'failed'=>$failed,'mysql_concurrency'=>'NOT TESTED','real_email_sent'=>false,'production_changed'=>false],JSON_THROW_ON_ERROR)."\n";
exit($failed?1:0);
