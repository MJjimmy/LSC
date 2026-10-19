<?php
declare(strict_types=1);
putenv('APP_ENV=production');putenv('APP_URL=https://lsc.example.invalid');putenv('APP_SHOW_RESET_LINKS=1');
require dirname(__DIR__).'/config/bootstrap.php';
use LSC\Core\Auth;use LSC\Core\Csrf;use LSC\Core\RateLimiter;
use LSC\Users\AccountController;use LSC\Users\AccountService;use LSC\Users\PasswordResetService;use LSC\Users\UserRepository;
use LSC\Notifications\ResendMailer;
$n=0;function expect(bool $ok,string $name):void{global $n;if(!$ok)throw new RuntimeException($name);$n++;}
expect(method_exists(PasswordResetService::class,'revokeToken'),'Undeliverable reset tokens must support revocation');
final class ResetFixtureDb extends PDO {
 public function prepare(string $q,array $options=[]):PDOStatement|false {
  if(str_contains($q,'INSERT INTO rate_limits'))$q='SELECT :key, :decay, :decay2';
  $q=str_replace('DATE_ADD(NOW(), INTERVAL 60 MINUTE)',"datetime('now', '+60 minutes')",$q);
  $q=str_replace('NOW()',"datetime('now')",$q);
  return parent::prepare($q,$options);
 }
 public function exec(string $q):int|false {return parent::exec(str_replace('DATE_SUB(NOW(), INTERVAL 1 DAY)',"datetime('now','-1 day')",$q));}
}
$db=new ResetFixtureDb('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,is_active INTEGER,avatar_path TEXT)');
$db->exec("INSERT INTO users VALUES(1,'Fixture','person@example.invalid','fixture-hash','student',1,NULL)");
$db->exec('CREATE TABLE password_reset_tokens(reset_id INTEGER PRIMARY KEY,user_id INTEGER,token_hash TEXT,expires_at TEXT,used_at TEXT)');
$db->exec('CREATE TABLE rate_limits(rl_key TEXT PRIMARY KEY,attempts INTEGER,expires_at TEXT)');
putenv('RESEND_API_KEY=fixture-not-a-real-key');putenv('RESEND_FROM_EMAIL=sender@example.invalid');putenv('EMAIL_DELIVERY_ENABLED=1');
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';
$calls=[];
$mailer=new ResendMailer(function($url,$body,$headers)use(&$calls){$calls[]=json_decode($body,true);return ['status'=>200,'body'=>'{"id":"fixture-accepted"}','network_error'=>false];});
function submit(ResendMailer $mailer,string $email):string {
 global $db;$_SESSION=[];$_POST=['csrf_token'=>Csrf::token(),'email'=>$email];
 $c=new AccountController(new Auth($db),new AccountService(new UserRepository($db)),new PasswordResetService($db),new RateLimiter($db),$mailer);
 ob_start();$c->forgotPassword();return ob_get_clean();
}
$html=submit($mailer,'person@example.invalid');
expect(count($calls)===1,'Forgot-password must send through the injected Resend transport');
expect((int)$db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn()===1,'Accepted email retains a hashed reset token');
preg_match('/token=([a-f0-9]{64})/',$calls[0]['text'],$match);
expect(isset($match[1]) && hash('sha256',$match[1])===$db->query('SELECT token_hash FROM password_reset_tokens')->fetchColumn(),'Database retains only the hash of the emailed token');
expect(!str_contains($html,$match[1]),'Production HTML never discloses the emailed reset token');
$bad=new ResendMailer(fn()=>['status'=>503,'body'=>'secret-response','network_error'=>false]);
submit($bad,'person@example.invalid');
expect((int)$db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn()===0,'Rejected email revokes the undeliverable reset token');
putenv('EMAIL_DELIVERY_ENABLED=0');$before=count($calls);submit($mailer,'person@example.invalid');
expect(count($calls)===$before && (int)$db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn()===0,'Disabled delivery creates neither email nor dangling token');
putenv('EMAIL_DELIVERY_ENABLED=1');submit($mailer,'missing@example.invalid');expect(count($calls)===$before,'Unknown account causes no provider call');
echo "Passed {$n} real-controller reset email checks; no real messages sent.\n";
