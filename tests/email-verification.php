<?php
declare(strict_types=1);
// Real services, synthetic in-memory SQLite; no bootstrap or external transport.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function(string $class): void {
    if (str_starts_with($class, 'LSC\\')) require_once dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
});
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
echo json_encode(['passed'=>$passed,'failed'=>$failed,'production_changed'=>false,'real_email_sent'=>false],JSON_THROW_ON_ERROR)."\n";
ob_end_flush(); exit($failed?1:0);
