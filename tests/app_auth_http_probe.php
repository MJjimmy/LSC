<?php
declare(strict_types=1);
// Executed ONLY inside the disposable CI web container. No Google requests or live accounts.
$base='http://127.0.0.1:18765';
$jar1='/tmp/lsc-fixture-browser.cookies';$jar2='/tmp/lsc-fixture-webview.cookies';
function request(string $path,?array $data=null,string $jar='/tmp/lsc-fixture-browser.cookies'):array {
 global $base;
 $h=curl_init($base.$path);
 curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
  CURLOPT_TIMEOUT=>10,CURLOPT_PROXY=>'',CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar]);
 if($data!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
 $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);$size=(int)curl_getinfo($h,CURLINFO_HEADER_SIZE);
 curl_setopt($h,CURLOPT_COOKIELIST,'FLUSH');unset($h);
 if(!is_string($raw))throw new RuntimeException('Fixture HTTP connection failed');
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
check($status===200 && str_contains($html,'Sign in to the LSC Android app'),'Browser confirmation is rendered');
check(str_contains($startHeaders, "form-action 'self' https://accounts.google.com"),'The originating confirmation document must permit the Google redirect');
$csrf=field($html,'csrf_token');$intent=field($html,'intent_id');
[$status]=request('/app-auth.php?action=complete');check($status===400,'An already logged-in browser cannot skip Google completion');
[$status]=request('/app-auth.php?action=start',['intent_id'=>$intent]);check($status===403,'Browser confirmation requires its CSRF token');
[$status,$headers]=request('/app-auth.php?action=start',['csrf_token'=>$csrf,'intent_id'=>$intent]);
check($status===303 && str_contains($headers,'Location: https://accounts.google.com/'),'Confirmed browser flow redirects to Google without following it');
// Seed an opaque code ONLY for the disposable fixture account; no OAuth exchange is simulated as real.
require '/app/config/bootstrap.php';
$db=LSC\Core\Database::connection();$s=$db->prepare('SELECT user_id,role FROM users WHERE email = ?');$s->execute([$email]);$user=$s->fetch(PDO::FETCH_ASSOC);
check(is_array($user) && $user['role']==='student','Registration stored the correct authoritative role');
$code=bin2hex(random_bytes(32));
$db->prepare('INSERT INTO app_auth_codes(code_hash,challenge,user_id,expires_at) VALUES(?,?,?,?)')->execute([hash('sha256',$code),$challenge,$user['user_id'],time()+120]);
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
echo "PASS: {$n} real HTTP registration/password-login/browser-confirmation/native-exchange checks on a disposable database; no real Google exchange or email.\n";
