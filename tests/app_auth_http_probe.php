<?php
declare(strict_types=1);
// Executed ONLY inside the disposable CI web container. No Google requests or live accounts.
$base='http://127.0.0.1:18765';
$jar1='/tmp/lsc-fixture-browser.cookies';$jar2='/tmp/lsc-fixture-webview.cookies';
$appCsrfFailures=0;
function request(string $path,?array $data=null,string $jar='/tmp/lsc-fixture-browser.cookies'):array {
 global $base,$appCsrfFailures;
 $h=curl_init($base.$path);
 curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
  CURLOPT_TIMEOUT=>10,CURLOPT_PROXY=>'',CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar]);
 if($data!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
 $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);$size=(int)curl_getinfo($h,CURLINFO_HEADER_SIZE);
 curl_setopt($h,CURLOPT_COOKIELIST,'FLUSH');unset($h);
 if(!is_string($raw))throw new RuntimeException('Fixture HTTP connection failed');
 if($path==='/app-auth.php?action=start' && $data!==null && $status===403)$appCsrfFailures++;
 return [$status,substr($raw,0,$size),substr($raw,$size)];
}
$n=0;function check(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function field(string $html,string $name):string {
 if(!preg_match('/name="'.preg_quote($name,'/').'"\s+value="([^"]+)"/',$html,$m))throw new RuntimeException('Missing fixture form field '.$name);
 return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
}
[$status,,$html]=request('/register.php?app=android');check($status===200,'Registration GET');
check(str_contains($html,'href="lscapp://google"'),'Registration uses native Google trigger');
$password=bin2hex(random_bytes(16));$email='app-http-fixture@example.invalid';
[$status,$headers]=request('/register.php?app=android',['csrf_token'=>field($html,'csrf_token'),'name'=>'Android HTTP fixture','email'=>$email,'password'=>$password,'confirm_password'=>$password,'role'=>'student']);
check(in_array($status,[302,303],true) && str_contains($headers,'Location: login.php'),'Actual registration POST succeeds');
[$status,,$html]=request('/login.php?app=android');check($status===200,'Sign-in GET');
[$status,$headers]=request('/login.php?app=android',['csrf_token'=>field($html,'csrf_token'),'email'=>$email,'password'=>$password,'next'=>'dashboard.php']);
check(in_array($status,[302,303],true) && str_contains($headers,'Location: dashboard.php'),'Actual password sign-in succeeds');
[$status,,$html]=request('/dashboard.php');check($status===200 && str_contains($html,'Android HTTP fixture'),'Password session reaches protected dashboard');
$verifier=bin2hex(random_bytes(32));$challenge=hash('sha256',$verifier);$state=bin2hex(random_bytes(32));
[$status,$startHeaders,$html]=request('/app-auth.php?action=start&challenge='.$challenge.'&state='.$state);
check($status===200 && str_contains($html,"document.getElementById('google-start').submit()"),'Browser automatically submits its private start form');
check(!str_contains($html,'<h1>Sign in to the LSC Android app</h1>'),'No extra app sign-in screen is displayed');
check(str_contains($html,'<noscript>'),'Script-disabled browsers retain an explicit fallback');
check(str_contains($startHeaders, "form-action 'self' https://accounts.google.com"),'The originating confirmation document must permit the Google redirect');
$csrf=field($html,'csrf_token');$intent=field($html,'intent_id');
[$status]=request('/app-auth.php?action=complete');check($status===400,'An already logged-in browser cannot skip Google completion');
[$status]=request('/app-auth.php?action=start',['intent_id'=>$intent]);check($status===403,'Browser confirmation requires its CSRF token');
[$status,$headers]=request('/app-auth.php?action=start',['csrf_token'=>$csrf,'intent_id'=>$intent]);
check($status===303 && str_contains($headers,'Location: https://accounts.google.com/'),'Confirmed browser flow redirects to Google without following it');
// Anonymous sessions must work without a preceding password login, and remain isolated.
$anonymousJar='/tmp/lsc-fixture-anonymous.cookies';
$lostCookieJar='/tmp/lsc-fixture-lost-cookie.cookies';
$otherJar='/tmp/lsc-fixture-other-browser.cookies';
[$status,,$anonymousHtml]=request('/app-auth.php?action=start&challenge='.$challenge.'&state='.$state,null,$anonymousJar);
check($status===200,'Fresh anonymous browser receives a private start form');
$anonymousCsrf=field($anonymousHtml,'csrf_token');$anonymousIntent=field($anonymousHtml,'intent_id');
$confirmationHelp='The browser could not verify this sign-in confirmation. Your browser session may have changed or cookies may be unavailable.';
[$status,,$failureHtml]=request('/app-auth.php?action=start',['csrf_token'=>$anonymousCsrf,'intent_id'=>$anonymousIntent],$lostCookieJar);
check($status===403,'Losing the GET session cookie never bypasses browser confirmation');
check(str_contains($failureHtml,$confirmationHelp) && !str_contains($failureHtml,'invalid or expired'),'CSRF help describes an unverified browser confirmation, not proven expiry');
$badCsrf=($anonymousCsrf[0]==='0'?'1':'0').substr($anonymousCsrf,1);
[$status]=request('/app-auth.php?action=start',['csrf_token'=>$badCsrf,'intent_id'=>$anonymousIntent],$anonymousJar);
check($status===403,'A changed token in the correct anonymous session remains forbidden');
[$status]=request('/app-auth.php?action=start',['csrf_token'=>['unexpected'],'intent_id'=>$anonymousIntent],$anonymousJar);
check($status===403,'A non-string CSRF token remains forbidden');
[$status,,$otherHtml]=request('/app-auth.php?action=start&challenge='.$challenge.'&state='.$state,null,$otherJar);
check($status===200,'Independent anonymous browser receives its own session');
[$status]=request('/app-auth.php?action=start',['csrf_token'=>$anonymousCsrf,'intent_id'=>$anonymousIntent],$otherJar);
check($status===403,'A stale form from a different session remains forbidden');
// A second GET replaces only the intent, not CSRF: a stale intent is 400, not this 403.
[$status,,$newHtml]=request('/app-auth.php?action=start&challenge='.$challenge.'&state='.$state,null,$anonymousJar);
check($status===200,'Anonymous browser can restart its native intent');
check(field($newHtml,'csrf_token')===$anonymousCsrf,'Replacing an intent retains the browser CSRF token');
[$status]=request('/app-auth.php?action=start',['csrf_token'=>$anonymousCsrf,'intent_id'=>$anonymousIntent],$anonymousJar);
check($status===400,'A superseded intent cannot begin OAuth even with valid CSRF');
$anonymousIntent=field($newHtml,'intent_id');
[$status,$anonymousHeaders]=request('/app-auth.php?action=start',['csrf_token'=>$anonymousCsrf,'intent_id'=>$anonymousIntent],$anonymousJar);
check($status===303 && preg_match('/^Location: (https:\/\/accounts\.google\.com\/[^\r\n]+)\r?$/m',$anonymousHeaders,$anonymousLocation)===1,'Valid anonymous confirmation redirects to Google without following it');
parse_str((string)parse_url($anonymousLocation[1],PHP_URL_QUERY),$anonymousQuery);
// Complete only the trusted callback hook in this isolated fixture; no real Google exchange is claimed.
require '/app/config/bootstrap.php';
$db=LSC\Core\Database::connection();$s=$db->prepare('SELECT user_id,role FROM users WHERE email = ?');$s->execute([$email]);$user=$s->fetch(PDO::FETCH_ASSOC);
check(is_array($user) && $user['role']==='student','Registration stored the correct authoritative role');
$anonymousSessionId=null;
foreach(file($anonymousJar,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
 $cookie=explode("\t",$line);
 if(count($cookie)===7 && $cookie[5]==='LSC_session')$anonymousSessionId=$cookie[6];
}
check(is_string($anonymousSessionId) && preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$anonymousSessionId)===1,'Anonymous fixture has a valid session cookie');
session_write_close();session_save_path('/tmp/lsc/sessions');session_id($anonymousSessionId);session_start();
check(!isset($_SESSION['user_id']),'Google begin does not authenticate the anonymous browser');
check(($_SESSION['app_sign_in']['intent_id']??null)===$anonymousIntent && ($_SESSION['app_sign_in']['state']??null)===$state && ($_SESSION['app_sign_in']['challenge']??null)===$challenge,'Anonymous confirmation preserves the current native intent, state and challenge');
check(($_SESSION['oauth_flow']['app_intent_id']??null)===$anonymousIntent && ($_SESSION['oauth_flow']['state']??null)===($anonymousQuery['state']??null) && ($_SESSION['app_sign_in']['oauth_state']??null)===($anonymousQuery['state']??null),'Anonymous OAuth state is bound to the same private intent');
// Simulate loss of stored session data while retaining the same browser cookie.
// Only this disposable fixture session is changed; no production hook is added.
$_SESSION=[];
session_write_close();
[$status]=request('/app-auth.php?action=start',['csrf_token'=>$anonymousCsrf,'intent_id'=>$anonymousIntent],$anonymousJar);
check($status===403,'Losing server-side session data never accepts the old confirmation');
// Load the actual fixture browser session, then invoke the same trusted completion hook
// that production invokes only AFTER verified Google exchange and identity resolution.
$browserSessionId=null;
foreach(file($jar1,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
 $cookie=explode("\t",$line);
 if(count($cookie)===7 && $cookie[5]==='LSC_session')$browserSessionId=$cookie[6];
}
check(is_string($browserSessionId) && preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$browserSessionId)===1,'Fixture browser session cookie is present');
session_write_close();session_save_path('/tmp/lsc/sessions');session_id($browserSessionId);session_start();
$flow=LSC\Users\OAuthProvider::consume('google',$_SESSION['oauth_flow']['state']);
(new LSC\Users\AppSignIn($db))->completeGoogle($flow,(int)$user['user_id']);
session_write_close();
[$status,$headers,$html]=request('/app-auth.php?action=complete');
check($status===303 && preg_match('/^Location: (lscapp:\/\/auth\?[^\r\n]+)\r?$/m',$headers,$location)===1,'Completed browser redirects straight to the native app');
parse_str((string)parse_url($location[1],PHP_URL_QUERY),$nativeReturn);
check(($nativeReturn['state']??null)===$state && preg_match('/^[a-f0-9]{64}$/D',$nativeReturn['code']??'')===1,'Direct return retains its code and matching native state');
check($html==='' && !str_contains($html,'Google sign-in complete'),'Completion renders no extra return-link screen');
$code=$nativeReturn['code'];
[$status]=request('/app-auth.php?action=complete');check($status===400,'Completed intent cannot issue another callback');
[$status]=request('/app-auth.php?action=exchange',['code'=>$code,'verifier'=>str_repeat('0',64)],$jar2);
check($status===400,'Wrong native verifier is refused over HTTP');
[$status,$headers]=request('/app-auth.php?action=exchange',['code'=>$code,'verifier'=>$verifier,'role'=>'admin','user_id'=>999999],$jar2);
check($status===303 && str_contains($headers,'Location: dashboard.php'),'Fresh WebView redeems the code and gets a real session');
[$status,,$html]=request('/dashboard.php',null,$jar2);check($status===200 && str_contains($html,'Android HTTP fixture'),'Returned WebView session opens the protected dashboard');
[$status]=request('/app-auth.php?action=exchange',['code'=>$code,'verifier'=>$verifier],$jar2);check($status===400,'One-time code cannot replay over HTTP');
$s=$db->prepare('SELECT role FROM users WHERE user_id = ?');$s->execute([$user['user_id']]);check($s->fetchColumn()==='student','Native request cannot change authoritative role');
[$status,,$html]=request('/help.php?app=android',null,'/tmp/lsc-fixture-help.cookies');
check($status===200 && str_contains($html,'<h1>Help &amp; getting started</h1>'),'Guest Android Help is public');
check(str_contains($html,'href="login.php?app=android"'),'Guest Help preserves native Google route on return to sign-in');
[$status,,$html]=request('/profile.php',null,$jar2);
check($status===200 && str_contains($html,'name="update_profile"') && str_contains($html,'name="update_notifications"'),'Authenticated Profile retains account and notification settings');
check(!str_contains($html,'oauth.php?provider=google') && !str_contains($html,'Connect Google'),'Authenticated Profile has no second Google control');
check(str_contains($html,'href="help.php"'),'Authenticated mobile layout links to Help');
[$status,,$html]=request('/help.php',null,$jar2);
check($status===200 && str_contains($html,'Android HTTP fixture') && str_contains($html,'<h1>Help &amp; getting started</h1>'),'Signed-in Help uses the real authenticated layout');
check(str_contains($html,'id="invitations"') && str_contains($html,'id="android"'),'Help includes invitation and app guidance');
$db->prepare('DELETE FROM app_auth_codes WHERE user_id = ?')->execute([$user['user_id']]);
$db->prepare('DELETE FROM users WHERE user_id = ? AND email = ?')->execute([$user['user_id'],$email]);
echo "APP_AUTH_CSRF_FAILURES={$appCsrfFailures}\n";
echo "PASS: {$n} real HTTP registration/password-login/browser-confirmation/native-exchange checks on a disposable database; no real Google exchange or email.\n";
