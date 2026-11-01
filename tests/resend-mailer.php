<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use LSC\Notifications\ResendMailer;
$n=0;
function expect(bool $ok,string $name):void { global $n; if(!$ok)throw new RuntimeException($name);$n++; }
expect(class_exists(ResendMailer::class),'Resend mailer must be implemented');
putenv('RESEND_API_KEY=fixture-not-a-real-key');putenv('RESEND_FROM_EMAIL=LSC <sender@example.invalid>');
putenv('EMAIL_DELIVERY_ENABLED=0');$calls=[];
$transport=function(string $url,string $body,array $headers)use(&$calls):array {$calls[]=[$url,json_decode($body,true),$headers];return ['status'=>200,'body'=>'{"id":"fixture-provider-id"}','network_error'=>false];};
$m=new ResendMailer($transport);
expect(!$m->send('recipient@example.invalid','Test','Body','fixture/one')['ok'] && count($calls)===0,'Sending must default to disabled');
putenv('EMAIL_DELIVERY_ENABLED=1');
$r=$m->send('recipient@example.invalid','Test','Body','fixture/one');
expect($r['ok'] && $r['provider_id']==='fixture-provider-id','Success must retain the provider ID');
expect($calls[0][0]==='https://api.resend.com/emails','Provider URL is fixed HTTPS');
expect($calls[0][1]['to']===['recipient@example.invalid'],'Recipient stays in JSON payload');
expect(in_array('Idempotency-Key: fixture/one',$calls[0][2],true),'Requests have an idempotency key');
$before=count($calls);
expect(!$m->send('bad-email','Test','Body','fixture/two')['ok'] && count($calls)===$before,'Invalid recipient never reaches provider');
expect(!$m->send('recipient@example.invalid',"Test\r\nInjected",'Body','fixture/two')['ok'],'Header-like subject injection rejected');
expect(!$m->send('recipient@example.invalid','Test','Body',"key\r\nInjected")['ok'],'Idempotency header injection rejected');
putenv('RESEND_FROM_EMAIL=bad@example.invalid\r\nInjected');
expect(!$m->send('recipient@example.invalid','Test','Body','fixture/two')['ok'],'Invalid sender rejected');
putenv('RESEND_FROM_EMAIL=LSC <sender@example.invalid>');
foreach([
 ['status'=>200,'body'=>'{}','network_error'=>false],
 ['status'=>200,'body'=>'not-json','network_error'=>false],
 ['status'=>401,'body'=>'credential=fixture-secret','network_error'=>false],
 ['status'=>429,'body'=>'rate limit','network_error'=>false],
 ['status'=>0,'body'=>'','network_error'=>true]
] as $reply){
 $bad=new ResendMailer(fn()=> $reply);$r=$bad->send('recipient@example.invalid','Test','Body','fixture/three');
 expect(!$r['ok'],'HTTP/network/invalid-payload failures must not be accepted');
 expect(!str_contains(json_encode($r),'fixture-secret'),'Provider response secrets are not returned');
}
putenv('RESEND_API_KEY=');
expect(!$m->configured(),'Missing API key disables mailer');
echo "Passed {$n} Resend mailer checks; no real messages sent.\n";
