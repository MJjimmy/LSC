<?php
declare(strict_types=1);
// Real services, synthetic in-memory SQLite; no bootstrap or external transport.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function(string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        $path=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        if (is_file($path)) require_once $path;
    }
});
define('LSC_ROOT',dirname(__DIR__));
session_start(); ob_start();
function emailDb(): PDO {
    $db = new class('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]) extends PDO {
        public function prepare(string $query, array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
    };
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,is_active INTEGER DEFAULT 1,avatar_path TEXT,email_verified_at TEXT DEFAULT NULL)');
    $db->exec('CREATE TABLE oauth_identities(user_id INTEGER REFERENCES users(user_id),provider TEXT,subject TEXT,UNIQUE(provider,subject),UNIQUE(user_id,provider))');
    $db->exec('CREATE TABLE app_auth_codes(code_hash TEXT PRIMARY KEY,user_id INTEGER REFERENCES users(user_id),challenge TEXT,expires_at INTEGER)');
    $db->exec('CREATE TABLE rate_limits(rl_key TEXT PRIMARY KEY,attempts INTEGER,expires_at TEXT)');
    $db->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)')->execute(['Synthetic learner','learner@example.invalid',password_hash('Orbit!29',PASSWORD_DEFAULT),'student']);
    return $db;
}
if (($argv[1]??'')==='register-probe' || ($argv[1]??'')==='csrf-probe') {
    $owned=realpath((string)getenv('FINAL_AUTH_TEMP'));
    if (!$owned || file_get_contents($owned.'/owned')!=='lsc-auth-offline') throw new RuntimeException('owned fixture required');
    $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db); $messages=[]; $service=emailService($db,$messages);
    $mode=$argv[1];
    if (($argv[2]??'')==='offline') putenv('EMAIL_DELIVERY_ENABLED=0');
    if (($argv[2]??'')==='rejected') $service=new LSC\Users\EmailVerificationService($db,new LSC\Notifications\ResendMailer(static function(string $url,string $payload) use (&$messages): array { $messages[]=json_decode($payload,true,flags:JSON_THROW_ON_ERROR); return ['status'=>503,'body'=>'']; }));
    register_shutdown_function(static function() use($db,&$messages,$owned,$mode): void {
        file_put_contents($owned.'/'.$mode.'.json',json_encode(['status'=>http_response_code()?:200,'messages'=>count($messages),'users'=>(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),'verified'=>(int)$db->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL')->fetchColumn(),'logged_in'=>isset($_SESSION['user_id'])],JSON_THROW_ON_ERROR));
    });
    $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'192.0.2.18']; $_GET=[];
    if ($mode==='register-probe') {
        $_POST=['csrf_token'=>LSC\Core\Csrf::token(),'name'=>'Synthetic signup','email'=>'signup@example.invalid','password'=>'Orbit!29','confirm_password'=>'Orbit!29','role'=>'student'];
        (new LSC\Users\AccountController(new LSC\Core\Auth($db),new LSC\Users\AccountService(new LSC\Users\UserRepository($db)),new LSC\Users\PasswordResetService($db),new LSC\Core\RateLimiter($db),null,$service))->register();
    } else {
        $_POST=['action'=>'resend','email'=>'learner@example.invalid','csrf_token'=>''];
        (new LSC\Users\EmailVerificationController($service,new LSC\Core\RateLimiter($db)))->show();
    }
    exit;
}
$passed=$failed=0;
function emailCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function emailTest(string $name,callable $test): void {
    global $passed,$failed;
    try { $_SESSION=[]; $test(); $passed++; echo "PASS: {$name}\n"; }
    catch(Throwable $e) { $failed++; echo "FAIL: {$name}: ".get_class($e).': '.$e->getMessage()."\n"; }
}
emailTest('password authentication denies unverified signup',function(): void {
    $db=emailDb(); $accounts=new LSC\Users\AccountService(new LSC\Users\UserRepository($db));
    emailCheck(!$accounts->authenticate('learner@example.invalid','Orbit!29')['ok'],'unverified password account authenticated');
});
emailTest('Auth denies unverified login and revalidates existing sessions',function(): void {
    $db=emailDb(); $hash=$db->query('SELECT password_hash FROM users')->fetchColumn(); $auth=new LSC\Core\Auth($db);
    emailCheck(!$auth->login(1,'Synthetic learner','student',null,$hash),'unverified direct login accepted');
    $_SESSION=['user_id'=>1,'auth_fingerprint'=>LSC\Core\Auth::fingerprint($hash)];
    emailCheck(!$auth->isLoggedIn() && !isset($_SESSION['user_id']),'unverified existing session remained authorized');
});
emailTest('native completion denies unverified users',function(): void {
    $db=emailDb(); $hash=$db->query('SELECT password_hash FROM users')->fetchColumn(); $app=new LSC\Users\AppSignIn($db);
    $app->createIntent(str_repeat('a',64),str_repeat('b',64));
    $_SESSION['user_id']=1; $_SESSION['auth_fingerprint']=LSC\Core\Auth::fingerprint($hash);
    $_SESSION['app_sign_in']['completed_user_id']=1; $_SESSION['app_sign_in']['completed_auth_fingerprint']=$_SESSION['auth_fingerprint'];
    $denied=false; try { $app->complete(); } catch(RuntimeException) { $denied=true; }
    emailCheck($denied && (int)$db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn()===0,'unverified native completion issued a code');
});
emailTest('Google mismatching primary email cannot verify an unverified account',function(): void {
    $db=emailDb(); $hash=$db->query('SELECT password_hash FROM users')->fetchColumn();
    $db->exec("INSERT INTO oauth_identities VALUES(1,'google','synthetic-subject')");
    $denied=false; try { (new LSC\Users\OAuthAccounts($db))->resolve('google',['subject'=>'synthetic-subject','email'=>'other@example.invalid','name'=>'Synthetic'],null); } catch(RuntimeException) { $denied=true; }
    emailCheck($denied && $db->query('SELECT email_verified_at FROM users')->fetchColumn()===null,'mismatching Google email bypassed primary ownership');
});
emailTest('ownership service sends only inbox proof and consumes once',function(): void {
    emailCheck(class_exists(LSC\Users\EmailVerificationService::class),'inbox verification service is missing');
    $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db);
    $messages=[]; $service=emailService($db,$messages);
    emailCheck($service->request('learner@example.invalid'),'verification request was not accepted');
    emailCheck(count($messages)===1,'fake transport did not receive one proof message');
    $token=emailToken($messages[0]);
    $row=$db->query('SELECT * FROM email_verification_tokens')->fetch();
    emailCheck($row['token_hash']===hash('sha256',$token) && !in_array($token,$row,true),'raw token persisted');
    emailCheck(!$service->verify(['bad']) && !$service->verify($token."\n"),'malformed proof accepted');
    emailCheck($service->verify($token),'inbox proof rejected');
    emailCheck(!$service->verify($token),'proof replay accepted');
    emailCheck((new LSC\Users\AccountService(new LSC\Users\UserRepository($db)))->authenticate('learner@example.invalid','Orbit!29')['ok'],'verified password login failed');
});
emailTest('ownership proof stays expiry and current-email bound',function(): void {
    emailCheck(class_exists(LSC\Users\EmailVerificationService::class),'inbox verification service is missing');
    foreach (['expired','changed','inactive'] as $case) {
        $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db); $messages=[]; $service=emailService($db,$messages);
        emailCheck($service->request('learner@example.invalid'),'proof setup failed'); $token=emailToken($messages[0]);
        if ($case==='expired') $db->exec('UPDATE email_verification_tokens SET expires_at=0');
        if ($case==='changed') $db->exec("UPDATE users SET email='changed@example.invalid'");
        if ($case==='inactive') $db->exec('UPDATE users SET is_active=0');
        emailCheck(!$service->verify($token),'invalid proof accepted: '.$case);
        emailCheck($db->query('SELECT email_verified_at FROM users')->fetchColumn()===null,'invalid proof changed account');
    }
});
emailTest('resend is generic and bounded without invalidating earlier mail',function(): void {
    emailCheck(class_exists(LSC\Users\EmailVerificationService::class),'inbox verification service is missing');
    $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db); $messages=[]; $service=emailService($db,$messages);
    for ($i=0;$i<5;$i++) emailCheck($service->request('learner@example.invalid'),'public resend result leaked quota');
    emailCheck(count($messages)===3,'email quota did not limit fake sends');
    emailCheck($service->request('unknown@example.invalid'),'unknown address disclosed');
    emailCheck(count($messages)===3,'unknown address sent mail');
    emailCheck($service->verify(emailToken($messages[0])),'earlier unexpired email invalidated by resend');
    emailCheck(!$service->verify(emailToken($messages[1])),'sibling proof survived successful verification');
});
emailTest('additive preparation is idempotent preserves rows and refuses mismatches',function(): void {
    emailCheck(class_exists(LSC\Users\EmailVerificationSchema::class),'verification schema preparation is missing');
    $db=emailDb(); $before=$db->query('SELECT * FROM users')->fetchAll();
    LSC\Users\EmailVerificationSchema::prepare($db); LSC\Users\EmailVerificationSchema::prepare($db);
    emailCheck($before===$db->query('SELECT * FROM users')->fetchAll(),'preparation changed existing account data');
    $db->exec('DROP TABLE email_verification_tokens'); $db->exec('CREATE TABLE email_verification_tokens(token_hash TEXT)');
    $denied=false; try { LSC\Users\EmailVerificationSchema::prepare($db); } catch(RuntimeException) { $denied=true; }
    emailCheck($denied,'incompatible existing token table accepted');
});
emailTest('verification controller GET is scanner safe and POST confirms ownership',function(): void {
    emailCheck(class_exists(LSC\Users\EmailVerificationController::class),'verification endpoint controller is missing');
    $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db); $messages=[]; $service=emailService($db,$messages);
    emailCheck($service->request('learner@example.invalid'),'controller setup failed'); $token=emailToken($messages[0]);
    $controller=new LSC\Users\EmailVerificationController($service,new LSC\Core\RateLimiter($db));
    $_SERVER=['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'192.0.2.11']; $_GET=['token'=>$token]; $_POST=[];
    ob_start(); $controller->show(); $html=ob_get_clean();
    emailCheck(str_contains($html,'Confirm email') && $db->query('SELECT email_verified_at FROM users')->fetchColumn()===null,'GET consumed proof or omitted confirmation');
    $_SERVER['REQUEST_METHOD']='POST'; $_POST=['token'=>$token,'action'=>'confirm','csrf_token'=>LSC\Core\Csrf::token()];
    ob_start(); $controller->show(); $html=ob_get_clean();
    emailCheck(str_contains($html,'Email verified') && $db->query('SELECT email_verified_at FROM users')->fetchColumn()!==null,'CSRF confirmation failed');
    emailCheck(!isset($_SESSION['user_id']),'verification silently signed in');
});
emailTest('real registration sends proof and shows pending rather than access',function(): void {
    [$report,$html]=emailProbe('register-probe');
    emailCheck($report['users']===2 && $report['verified']===0 && !$report['logged_in'],'registration granted access');
    emailCheck($report['messages']===1 && str_contains($html,'Check your inbox'),'registration did not send inbox proof and render pending status');
});
emailTest('verification resend rejects absent CSRF without sending',function(): void {
    [$report,$html]=emailProbe('csrf-probe');
    emailCheck($report['status']===403 && $report['messages']===0 && $report['verified']===0,'missing CSRF mutated verification state');
});
emailTest('delivery failures and unsafe APP_URL never expose a usable proof',function(): void {
    $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db); $messages=[]; $service=emailService($db,$messages);
    foreach (['http://offline.example.invalid','https://user@offline.example.invalid','https://offline.example.invalid?x=1','https://offline.example.invalid/#x'] as $url) {
        putenv('APP_URL='.$url); emailCheck(!$service->available() && !$service->request('learner@example.invalid'),'unsafe APP_URL accepted');
    }
    emailCheck(count($messages)===0,'unsafe URL sent proof');
    putenv('APP_URL=https://offline.example.invalid');
    $fail=new LSC\Users\EmailVerificationService($db,new LSC\Notifications\ResendMailer(static fn()=>['status'=>503,'body'=>'']));
    emailCheck(!$fail->request('learner@example.invalid') && (int)$db->query('SELECT COUNT(*) FROM email_verification_tokens')->fetchColumn()===0,'failed delivery left usable proof or reported success');
});
emailTest('native exchange denies a code after ownership revocation',function(): void {
    $db=emailDb(); $hash=$db->query('SELECT password_hash FROM users')->fetchColumn(); $verifier=str_repeat('a',64); $code=str_repeat('b',64);
    $db->prepare('INSERT INTO app_auth_codes VALUES(?,?,?,?)')->execute([hash('sha256',$code),1,hash_hmac('sha256',hash('sha256',$verifier),$hash),time()+120]);
    $denied=false; try { (new LSC\Users\AppSignIn($db))->exchange($code,$verifier); } catch(RuntimeException) { $denied=true; }
    emailCheck($denied && !isset($_SESSION['user_id']),'unverified native exchange authenticated');
});
emailTest('preparation expands a legacy users table without verifying existing accounts',function(): void {
    $db=emailDb(); $db->exec('ALTER TABLE users DROP COLUMN email_verified_at');
    $before=$db->query('SELECT user_id,name,email,password_hash,role,is_active,avatar_path FROM users')->fetchAll();
    LSC\Users\EmailVerificationSchema::prepare($db); LSC\Users\EmailVerificationSchema::prepare($db);
    emailCheck($before===$db->query('SELECT user_id,name,email,password_hash,role,is_active,avatar_path FROM users')->fetchAll(),'legacy row changed');
    emailCheck($db->query('SELECT email_verified_at FROM users')->fetchColumn()===null,'legacy account automatically verified');
    $db->exec('ALTER TABLE users DROP COLUMN email_verified_at'); $db->exec('ALTER TABLE users ADD COLUMN email_verified_at INTEGER DEFAULT 1');
    $denied=false; try { LSC\Users\EmailVerificationSchema::prepare($db); } catch(RuntimeException) { $denied=true; }
    emailCheck($denied,'incompatible ownership column accepted');
});
emailTest('verified sessions stop authorizing immediately when ownership is revoked',function(): void {
    $db=emailDb(); $hash=$db->query('SELECT password_hash FROM users')->fetchColumn(); $auth=new LSC\Core\Auth($db);
    $db->exec('UPDATE users SET email_verified_at=CURRENT_TIMESTAMP');
    emailCheck($auth->login(1,'Synthetic learner','student',null,$hash) && $auth->isLoggedIn(),'verified normal session rejected');
    $db->exec('UPDATE users SET email_verified_at=NULL');
    emailCheck(!$auth->isLoggedIn() && !isset($_SESSION['user_id']),'session remained authorized after ownership revocation');
});
emailTest('Google current primary match verifies ownership without email-only linking',function(): void {
    $db=emailDb(); $db->exec("INSERT INTO oauth_identities VALUES(1,'google','synthetic-subject')");
    $identity=LSC\Users\OAuthProvider::identity('google',['sub'=>'synthetic-subject','email'=>'LEARNER@example.invalid','email_verified'=>true,'name'=>'Synthetic']);
    $accounts=new LSC\Users\OAuthAccounts($db); $user=$accounts->resolve('google',$identity,null);
    emailCheck(!empty($user['email_verified_at']),'verified provider primary match was not recorded');
    emailCheck((new LSC\Core\Auth($db))->login(1,$user['name'],$user['role'],null,$user['password_hash']),'verified Google identity cannot authenticate');
    $denied=false; try { $accounts->resolve('google',['subject'=>'unlinked-subject','email'=>'learner@example.invalid','name'=>'Synthetic'],null); } catch(RuntimeException) { $denied=true; }
    emailCheck($denied && (int)$db->query('SELECT COUNT(*) FROM oauth_identities')->fetchColumn()===1,'email-only provider linking accepted');
    $fresh=$accounts->resolve('google',LSC\Users\OAuthProvider::identity('google',['sub'=>'fresh-subject','email'=>'fresh@example.invalid','email_verified'=>true,'name'=>'Synthetic']),null);
    emailCheck(!empty($fresh['email_verified_at']) && $fresh['role']==='student','fresh verified Google account not usable');
});
emailTest('registration refuses offline email and provides recovery after rejected delivery',function(): void {
    [$report,$html]=emailProbe('register-probe','offline');
    emailCheck($report['users']===1 && $report['messages']===0 && str_contains($html,'temporarily unavailable'),'offline registration silently persisted account');
    [$report,$html]=emailProbe('register-probe','rejected');
    emailCheck($report['users']===2 && $report['verified']===0 && !$report['logged_in'] && str_contains($html,'pending verification') && str_contains($html,'href="verify-email.php"'),'failed signup delivery has no recovery');
});
emailTest('public resend response is generic and never returns proof',function(): void {
    $db=emailDb(); LSC\Users\EmailVerificationSchema::prepare($db); $messages=[]; $service=emailService($db,$messages);
    $controller=new LSC\Users\EmailVerificationController($service,new LSC\Core\RateLimiter($db));
    $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>'192.0.2.11']; $_GET=[]; $outputs=[];
    foreach (['learner@example.invalid','unknown@example.invalid'] as $email) {
        $_POST=['action'=>'resend','email'=>$email,'csrf_token'=>LSC\Core\Csrf::token()];
        ob_start(); $controller->show(); $outputs[]=ob_get_clean();
    }
    emailCheck($outputs[0]===$outputs[1] && count($messages)===1,'resend publicly distinguished account existence');
    emailCheck(!str_contains($outputs[0],emailToken($messages[0])),'resend disclosed inbox proof');
});
function emailProbe(string $mode,string $variant=''): array {
    $pipes=[]; $p=proc_open([PHP_BINARY,__FILE__,$mode,$variant],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    emailCheck(is_resource($p),'probe failed to start'); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    emailCheck(proc_close($p)===0 && $err==='','probe did not run cleanly');
    $path=getenv('FINAL_AUTH_TEMP').'/'.$mode.'.json'; $report=json_decode(file_get_contents($path),true,flags:JSON_THROW_ON_ERROR); unlink($path);
    return [$report,$out];
}
function csrfToken(): string { return LSC\Core\Csrf::token(); }
function emailService(PDO $db,array &$messages): LSC\Users\EmailVerificationService {
    putenv('APP_URL=https://offline.example.invalid'); putenv('EMAIL_DELIVERY_ENABLED=1');
    putenv('RESEND_API_KEY=synthetic-not-a-credential'); putenv('RESEND_FROM_EMAIL=sender@example.invalid');
    $mailer=new LSC\Notifications\ResendMailer(static function(string $url,string $payload,array $headers) use (&$messages): array {
        $messages[]=json_decode($payload,true,flags:JSON_THROW_ON_ERROR);
        return ['status'=>200,'body'=>'{"id":"synthetic-receipt"}'];
    });
    return new LSC\Users\EmailVerificationService($db,$mailer);
}
function emailToken(array $message): string {
    emailCheck(preg_match('/verify-email\.php\?token=([a-f0-9]{64})/D',trim($message['text']),$match)===1,'proof message missing safe URL');
    return $match[1];
}
echo json_encode(['passed'=>$passed,'failed'=>$failed,'production_changed'=>false,'real_email_sent'=>false],JSON_THROW_ON_ERROR)."\n";
ob_end_flush(); exit($failed?1:0);
