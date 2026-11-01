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
