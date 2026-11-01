<?php
declare(strict_types=1);
namespace LSC\Notifications;

use Closure;
use JsonException;
use Throwable;

/** HTTPS-only Resend transport. API acceptance is not proof of inbox delivery. */
final class ResendMailer
{
    public function __construct(private readonly ?Closure $transport = null) {}

    public function configured(): bool
    {
        $key=trim((string)getenv('RESEND_API_KEY'));
        $from=trim((string)getenv('RESEND_FROM_EMAIL'));
        if($key==='' || preg_match('/[\r\n]/',$key.$from) || strlen($from)>320)return false;
        $address=$from;
        if(preg_match('/^[^<>]+<([^<>]+)>$/D',$from,$m))$address=trim($m[1]);
        return filter_var($address,FILTER_VALIDATE_EMAIL)!==false;
    }

    public function enabled(): bool
    {
        return getenv('EMAIL_DELIVERY_ENABLED')==='1' && $this->configured();
    }

    /** @return array{ok:bool,provider_id:?string,error:?string} */
    public function send(string $to,string $subject,string $text,string $key): array
    {
        if(!$this->enabled())return $this->failure('Email delivery is disabled or not configured.');
        if(!filter_var($to,FILTER_VALIDATE_EMAIL) || strlen($to)>190
            || preg_match('/[\r\n]/',$subject) || mb_strlen($subject)>190 || $subject===''
            || strlen($text)>524288 || !preg_match('~^[A-Za-z0-9/_=:.+-]{1,256}$~D',$key)) {
            return $this->failure('Invalid email request.');
        }
        try {
            $payload=json_encode(['from'=>trim((string)getenv('RESEND_FROM_EMAIL')),'to'=>[$to],'subject'=>$subject,'text'=>$text],JSON_THROW_ON_ERROR);
        }catch(JsonException){return $this->failure('Invalid email encoding.');}
        $headers=['Authorization: Bearer '.trim((string)getenv('RESEND_API_KEY')),'Content-Type: application/json','Idempotency-Key: '.$key];
        try {
            $response=$this->transport!==null
                ? ($this->transport)('https://api.resend.com/emails',$payload,$headers)
                : $this->request($payload,$headers);
        } catch(Throwable) { return $this->failure('Email provider could not be reached.'); }
        if(!is_array($response))return $this->failure('Email provider returned an invalid response.');
        if(!empty($response['network_error']))return $this->failure('Email provider could not be reached.');
        $status=(int)($response['status']??0);
        if($status<200 || $status>=300)return $this->failure('Resend request failed (HTTP '.$status.').');
        $data=json_decode((string)($response['body']??''),true);
        $id=is_array($data)?($data['id']??null):null;
        if(!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$id))return $this->failure('Email provider returned an invalid acceptance receipt.');
        return ['ok'=>true,'provider_id'=>$id,'error'=>null];
    }

    private function failure(string $reason): array
    {
        return ['ok'=>false,'provider_id'=>null,'error'=>$reason];
    }

    private function request(string $payload,array $headers): array
    {
        if(!function_exists('curl_init'))return ['network_error'=>true];
        $ch=curl_init('https://api.resend.com/emails');
        if($ch===false)return ['network_error'=>true];
        $options=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROXY=>''];
        if(defined('CURLOPT_PROTOCOLS_STR'))$options[constant('CURLOPT_PROTOCOLS_STR')]='https';
        else $options[CURLOPT_PROTOCOLS]=CURLPROTO_HTTPS;
        curl_setopt_array($ch,$options);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        return ['status'=>$status,'body'=>is_string($body)?$body:'','network_error'=>$body===false];
    }
}
