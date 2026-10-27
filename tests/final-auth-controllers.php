<?php
declare(strict_types=1);
// Real SQLite persistence and production controllers; no bootstrap, transport or HTTP server.
if (getenv('AUTH_SECURITY_CI') !== 'isolated') throw new RuntimeException('isolated runner required');
spl_autoload_register(static function(string $class): void {
    if (str_starts_with($class, 'LSC\\')) require_once dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
});
define('LSC_ROOT', dirname(__DIR__));
session_start(); ob_start();
function csrfToken(): string { return LSC\Core\Csrf::token(); }
// Exact legacy dialect adaptations keep baseline RED behavioral. Real SQLite
// predicates, row counts, transactions, controller dispatch and data are retained.
class AuthFixturePDO extends PDO {
    private function sql(string $sql): string {
        if (str_contains($sql,'ON DUPLICATE KEY UPDATE')) {
            if (!str_contains($sql,'INSERT INTO rate_limits')) throw new RuntimeException('unknown legacy upsert');
            return "INSERT INTO rate_limits (rl_key, attempts, expires_at) VALUES (:key, 1, datetime('now', '+' || :decay || ' seconds')) ON CONFLICT(rl_key) DO UPDATE SET attempts=CASE WHEN rate_limits.expires_at > datetime('now') THEN rate_limits.attempts+1 ELSE 1 END, expires_at=CASE WHEN rate_limits.expires_at > datetime('now') THEN rate_limits.expires_at ELSE datetime('now','+' || :decay2 || ' seconds') END";
        }
        $sql=str_replace(['DATE_ADD(NOW(), INTERVAL 60 MINUTE)','DATE_SUB(NOW(), INTERVAL 1 DAY)','TIMESTAMPDIFF(SECOND, NOW(), expires_at)'],["datetime('now','+60 minutes')","datetime('now','-1 day')","CAST(strftime('%s',expires_at) AS INTEGER)-CAST(strftime('%s','now') AS INTEGER)"],$sql);
        $sql=preg_replace('/ FOR UPDATE\s*$/','',$sql);
        return str_replace('NOW()',"datetime('now')",$sql);
    }
    public function prepare(string $query, array $options=[]): PDOStatement|false { return parent::prepare($this->sql($query),$options); }
    public function exec(string $statement): int|false { return parent::exec($this->sql($statement)); }
}
$owned=realpath((string)getenv('FINAL_AUTH_TEMP'));
if (!$owned || file_get_contents($owned.'/owned') !== 'lsc-auth-offline') throw new RuntimeException('owned scratch required');
$path=$owned.'/controllers.sqlite';
if (($argv[1]??'') === 'reserve') {
    if (!is_file($path)) throw new RuntimeException('fixture not initialized');
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=3000');
    echo (new LSC\Core\RateLimiter($db))->consume('parallel:quota',6,3600) ? '1' : '0';
    exit;
}
if (($argv[1]??'') === 'protected') {
    if (!is_file($path)) throw new RuntimeException('fixture not initialized');
    $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $_SESSION=json_decode(file_get_contents($owned.'/saved-session.json'),true,flags:JSON_THROW_ON_ERROR);
    (new LSC\Core\Auth($db))->requireLogin();
    echo 'AUTHORIZED'; exit;
}
if (($argv[1]??'') === 'register') {
    if (!is_file($path)) throw new RuntimeException('fixture not initialized');
    $db=new AuthFixturePDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $_SESSION=[];
    $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'192.0.2.19']; $_GET=[];
    $_POST=['csrf_token'=>LSC\Core\Csrf::token(),'name'=>'Synthetic','email'=>'quota'.(int)$argv[2].'@example.invalid','password'=>'SyntheticPass123!','confirm_password'=>'SyntheticPass123!','role'=>'student'];
    (new LSC\Users\AccountController(new LSC\Core\Auth($db),new LSC\Users\AccountService(new LSC\Users\UserRepository($db)),new LSC\Users\PasswordResetService($db),new LSC\Core\RateLimiter($db)))->register();
    exit;
}
if (file_exists($path)) throw new RuntimeException('refuse existing fixture');
$db=new AuthFixturePDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password_hash TEXT, role TEXT, is_active INTEGER DEFAULT 1, avatar_path TEXT)');
$db->exec('CREATE TABLE rate_limits(rl_key TEXT PRIMARY KEY, attempts INTEGER, expires_at TEXT)');
$db->exec('CREATE TABLE password_reset_tokens(reset_id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(user_id),token_hash TEXT UNIQUE,expires_at TEXT,used_at TEXT)');
$db->exec('CREATE TABLE app_auth_codes(code_hash TEXT PRIMARY KEY,challenge TEXT,user_id INTEGER REFERENCES users(user_id),expires_at INTEGER)');
$db->exec('CREATE TABLE oauth_identities(provider TEXT, subject TEXT, user_id INTEGER REFERENCES users(user_id), UNIQUE(provider,subject))');
$pass=$fail=0;
function assertion(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); }
function scenario(string $label,callable $run): void { global $pass,$fail; try { $run(); ++$pass; echo "PASS: $label\n"; } catch(Throwable $e) { ++$fail; echo 'FAIL: '.$label.' ('.get_class($e).': '.$e->getMessage().")\n"; } }
scenario('actual register controller retains six successful reservations; seventh creates no user', function() use($db,$owned) {
    for($i=1;$i<=7;$i++) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,__FILE__,'register',(string)$i],[0=>['pipe','r'],1=>['file',$owned.'/register-output','w'],2=>['file',$owned.'/register-errors','w']],$pipes);
        assertion(is_resource($process),'child start'); fclose($pipes[0]);
        assertion(proc_close($process)===0 && filesize($owned.'/register-errors')===0,'controller child failed');
    }
    assertion((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===6,'successful signup quota bypassed');
    assertion((int)$db->query('SELECT attempts FROM rate_limits')->fetchColumn()===6,'successful quota not retained');
});
// Other scenarios independent of quota failure.
$db->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)')->execute(['Reset','reset@example.invalid',password_hash('Before123!',PASSWORD_DEFAULT),'student']);
$id=(int)$db->lastInsertId();
$service=new LSC\Users\PasswordResetService($db);
scenario('reset atomically revokes sibling tokens and all issued native codes',function() use($db,$service,$id) {
    $token=$service->createToken('reset@example.invalid'); assertion(is_string($token),'token setup');
    $db->prepare("INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,datetime('now','+1 hour'))")->execute([$id,hash('sha256','sibling')]);
    $db->prepare('INSERT INTO app_auth_codes VALUES(?,?,?,?)')->execute([hash('sha256','issued'),str_repeat('a',64),$id,time()+120]);
    assertion($service->reset($token,'After123!'),'reset rejected');
    assertion(password_verify('After123!',(string)$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn()),'password unchanged');
    assertion((int)$db->query('SELECT COUNT(*) FROM app_auth_codes WHERE user_id='.$id)->fetchColumn()===0,'issued ticket survived reset');
    assertion((int)$db->query('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id='.$id.' AND used_at IS NULL')->fetchColumn()===0,'sibling token survived reset');
    assertion(!$service->reset($token,'Reuse123!'),'token reused');
});
scenario('failed code deletion rolls back password token and native code',function() use($db,$service,$id) {
    $token=$service->createToken('reset@example.invalid');
    $before=$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn();
    $db->prepare('INSERT OR REPLACE INTO app_auth_codes VALUES(?,?,?,?)')->execute([hash('sha256','rollback'),str_repeat('a',64),$id,time()+120]);
    $db->exec("CREATE TRIGGER fail_delete BEFORE DELETE ON app_auth_codes BEGIN SELECT RAISE(ABORT,'synthetic rollback'); END");
    $previous=ini_get('error_log'); $expected=getenv('FINAL_AUTH_TEMP').'/expected-reset-log'; ini_set('error_log',$expected);
    try { $accepted=$service->reset($token,'Rollback123!'); } finally { ini_set('error_log',$previous); $db->exec('DROP TRIGGER fail_delete'); }
    // Only the deliberately injected sanitized rollback log is permitted.
    $log=is_file($expected)?file_get_contents($expected):'';
    assertion($log==='' || preg_match('/^\[[^\]]+\] PasswordResetService::reset failed(?:\.|: SQLSTATE\[23000\]: Integrity constraint violation: 19 synthetic rollback)\s*$/D',$log)===1,'unexpected rollback log');
    assertion(!$accepted,'failed transaction reported success');
    assertion($before===$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn(),'password not rolled back');
    assertion((int)$db->query("SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash='".hash('sha256',$token)."' AND used_at IS NULL")->fetchColumn()===1,'token not rolled back');
    assertion((int)$db->query("SELECT COUNT(*) FROM app_auth_codes WHERE code_hash='".hash('sha256','rollback')."'")->fetchColumn()===1,'code not rolled back');
});
scenario('actual reset controller uses real SQLite service and rejects token reuse',function() use($db,$service,$id) {
    if(session_status()!==PHP_SESSION_ACTIVE) session_start(); $_SESSION=[]; $_GET=[];
    $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'192.0.2.20'];
    $token=$service->createToken('reset@example.invalid');
    $_POST=['token'=>$token,'password'=>'ControllerAfter123!','confirm_password'=>'ControllerAfter123!','csrf_token'=>LSC\Core\Csrf::token()];
    $controller=new LSC\Users\AccountController(new LSC\Core\Auth($db),new LSC\Users\AccountService(new LSC\Users\UserRepository($db)),$service,new LSC\Core\RateLimiter($db));
    ob_start(); $controller->resetPassword(); $html=ob_get_clean();
    assertion(str_contains($html,'Password updated.'),'controller did not confirm reset');
    assertion(password_verify('ControllerAfter123!',(string)$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn()),'controller did not persist password');
    ob_start(); $controller->resetPassword(); $html=ob_get_clean();
    assertion(str_contains($html,'expired or has already been used'),'reuse not rejected');
});
scenario('twelve independent SQLite processes admit exactly six signup reservations',function() use($db,$owned) {
    $processes=[];
    for($i=0;$i<12;$i++) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,__FILE__,'reserve'],[0=>['pipe','r'],1=>['file',$owned.'/parallel-'.$i.'.out','w'],2=>['file',$owned.'/parallel-'.$i.'.err','w']],$pipes);
        assertion(is_resource($process),'parallel start'); fclose($pipes[0]); $processes[$i]=$process;
    }
    $admitted=0;
    foreach($processes as $i=>$process) {
        assertion(proc_close($process)===0 && filesize($owned.'/parallel-'.$i.'.err')===0,'parallel process failed');
        $value=file_get_contents($owned.'/parallel-'.$i.'.out');
        assertion($value==='0'||$value==='1','parallel response'); $admitted+=(int)$value;
    }
    assertion($admitted===6,'parallel cap not six');
    assertion((int)$db->query("SELECT attempts FROM rate_limits WHERE rl_key='parallel:quota'")->fetchColumn()===6,'parallel persistence');
});
scenario('real reset revokes local resolved-OAuth native sessions and completed intent; protected guard denies each',function() use($db,$service,$id,$owned) {
    $auth=new LSC\Core\Auth($db); $app=new LSC\Users\AppSignIn($db);
    $proof=(string)$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn();
    $_SESSION=[]; assertion($auth->login($id,'Forged','admin',null,$proof),'local session'); $sessions=[$_SESSION];
    $db->prepare('INSERT INTO oauth_identities VALUES(?,?,?)')->execute(['google','offline-subject',$id]);
    $resolved=(new LSC\Users\OAuthAccounts($db))->resolve('google',['subject'=>'offline-subject'],null);
    $_SESSION=[]; assertion($auth->login((int)$resolved['user_id'],$resolved['name'],$resolved['role'],$resolved['avatar_path'],$resolved['password_hash']),'resolved OAuth session'); $sessions[]=$_SESSION;
    $make=function() use($app,$id): void {
        $intent=$app->createIntent(hash('sha256',str_repeat('a',64)),str_repeat('b',64));
        $_SESSION['app_sign_in']['oauth_state']=str_repeat('c',64);
        $app->completeGoogle(['provider'=>'google','link_user_id'=>null,'next'=>'app-auth.php?action=complete','app_intent_id'=>$intent['intent_id'],'state'=>str_repeat('c',64)],$id);
    };
    $make(); $ticket=$app->complete(); $_SESSION=[]; $app->exchange($ticket['code'],str_repeat('a',64)); $sessions[]=$_SESSION;
    $make(); $completed=$_SESSION;
    $token=$service->createToken('reset@example.invalid'); assertion($service->reset($token,'FinalReset123!'),'real reset');
    // No intervening isLoggedIn()/requireLogin() before checking completed intent.
    $_SESSION=$completed; $rejected=false;
    try { $app->complete(); } catch(RuntimeException) { $rejected=true; }
    assertion($rejected,'completed intent survived reset');
    foreach($sessions as $session) {
        $_SESSION=$session; assertion(!$auth->isLoggedIn(),'pre-reset issuer session survived');
        file_put_contents($owned.'/saved-session.json',json_encode($session,JSON_THROW_ON_ERROR));
        $pipes=[]; $child=proc_open([PHP_BINARY,__FILE__,'protected'],[0=>['pipe','r'],1=>['file',$owned.'/protected.out','w'],2=>['file',$owned.'/protected.err','w']],$pipes);
        assertion(is_resource($child),'protected guard child'); fclose($pipes[0]);
        assertion(proc_close($child)===0 && filesize($owned.'/protected.err')===0,'protected guard child failure');
        assertion(file_get_contents($owned.'/protected.out')==='','direct protected guard authorized revoked session');
    }
});
scenario('inactive expired and unknown reset tokens never change password',function() use($db,$service,$id) {
    $token=$service->createToken('reset@example.invalid');
    $before=$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn();
    $db->exec('UPDATE users SET is_active=0 WHERE user_id='.$id);
    assertion(!$service->reset($token,'Inactive123!'),'inactive reset accepted');
    $db->exec('UPDATE users SET is_active=1 WHERE user_id='.$id);
    $db->exec("UPDATE password_reset_tokens SET expires_at=CURRENT_TIMESTAMP WHERE user_id=".$id);
    assertion(!$service->reset($token,'Expired123!') && !$service->reset(str_repeat('0',64),'Unknown123!'),'invalid reset accepted');
    assertion($before===$db->query('SELECT password_hash FROM users WHERE user_id='.$id)->fetchColumn(),'invalid token changed password');
});
echo "Controller/reset scenarios: $pass passed, $fail failed (no HTTP/MySQL proof).\n";
exit($fail?1:0);
