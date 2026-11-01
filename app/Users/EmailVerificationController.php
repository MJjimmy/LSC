<?php
declare(strict_types=1);
namespace LSC\Users;

use LSC\Core\Csrf;
use LSC\Core\RateLimiter;

final class EmailVerificationController
{
    public function __construct(private readonly EmailVerificationService $verification,private readonly RateLimiter $limiter) {}

    public function show(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        $raw=$_POST['token']??$_GET['token']??'';
        $token=is_string($raw) && preg_match('/^[a-f0-9]{64}$/D',$raw)===1 ? $raw : '';
        $error=''; $success='';
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            Csrf::verify();
            $ip=RateLimiter::clientIp();
            if (($_POST['action']??'')==='confirm') {
                if (!$this->limiter->consume('verify:confirm:ip:'.$ip,10,3600)) {
                    $error='Too many attempts. Please try again later.';
                } elseif ($this->verification->verify($token)) {
                    $success='Email verified. You can now sign in.'; $token='';
                } else {
                    $error='This verification link is invalid, expired or already used. Request another link below.'; $token='';
                }
            } elseif (($_POST['action']??'')==='resend') {
                $email=$_POST['email']??'';
                // Identical public response regardless of existence, quota or delivery outcome.
                if ($this->limiter->consume('verify:resend:ip:'.$ip,6,3600) && is_string($email)) $this->verification->request($email);
                $success='If an active account needs verification, a link has been requested. Check your inbox and spam folder. If no email arrives, try again later.';
                $token='';
            } else {
                $error='Choose a verification action.';
            }
        }
        ob_start(); require \LSC_ROOT.'/views/auth/verify-email.php';
    }
}
