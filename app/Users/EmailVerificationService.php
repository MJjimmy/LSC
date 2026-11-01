<?php
declare(strict_types=1);
namespace LSC\Users;

use LSC\Core\RateLimiter;
use LSC\Notifications\ResendMailer;
use PDO;
use Throwable;

/** Inbox proof only. No caller receives the raw token; no automatic sign-in. */
final class EmailVerificationService
{
    public function __construct(private readonly PDO $database, private readonly ?ResendMailer $mailer=null) {}

    private function baseUrl(): ?string
    {
        $base=rtrim(trim((string)getenv('APP_URL')),'/'); $parts=parse_url($base);
        if (!is_array($parts) || ($parts['scheme']??'')!=='https' || empty($parts['host'])
            || isset($parts['user'],$parts['pass']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7f]/',$base)
            || filter_var($base,FILTER_VALIDATE_URL)===false) return null;
        return $base;
    }

    public function available(): bool
    {
        return $this->baseUrl()!==null && ($this->mailer??new ResendMailer())->enabled();
    }

    private function lockUser(int $id): array|false
    {
        $sqlite=$this->database->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        if ($sqlite) $this->database->prepare('UPDATE users SET user_id=user_id WHERE user_id=?')->execute([$id]);
        $q=$this->database->prepare('SELECT user_id,email,is_active,email_verified_at FROM users WHERE user_id=?'.($sqlite?'':' FOR UPDATE'));
        $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC);
    }

    /** Generic result for absent/verified/throttled addresses. False means delivery unavailable. */
    public function request(string $email): bool
    {
        if (!$this->available() || $this->database->inTransaction()) return false;
        $email=strtolower(trim($email));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150) return true;
        if (!(new RateLimiter($this->database))->consume('verify:email:'.hash('sha256',$email),3,3600)) return true;
        $token=null;
        try {
            $user=(new UserRepository($this->database))->findByEmail($email);
            if (!$user) return true;
            $this->database->beginTransaction();
            $user=$this->lockUser((int)$user['user_id']);
            if (!$user || (int)$user['is_active']!==1 || !empty($user['email_verified_at']) || strcasecmp(trim($user['email']),$email)!==0) {
                $this->database->rollBack(); return true;
            }
            $token=bin2hex(random_bytes(32));
            $this->database->prepare('INSERT INTO email_verification_tokens(token_hash,user_id,email_hash,expires_at) VALUES(?,?,?,?)')
                ->execute([hash('sha256',$token),(int)$user['user_id'],hash('sha256',$email),time()+3600]);
            $this->database->commit();
            $url=$this->baseUrl().'/verify-email.php?token='.$token;
            $sent=($this->mailer??new ResendMailer())->send($user['email'],'Verify your LSC email',
                "Confirm your email within 60 minutes. Open this link, then press Confirm email. If you did not request this, ignore this message.\n\n".$url,
                'lsc/verify/'.hash('sha256',$token));
            if (!$sent['ok']) {
                $this->database->prepare('DELETE FROM email_verification_tokens WHERE token_hash=?')->execute([hash('sha256',$token)]);
                return false;
            }
            return true;
        } catch(Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            // No email, SQL, raw proof or provider error details in logs/responses.
            return false;
        }
    }

    public function verify(mixed $token): bool
    {
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D',$token)!==1 || $this->database->inTransaction()) return false;
        try {
            $hash=hash('sha256',$token);
            $q=$this->database->prepare('SELECT user_id FROM email_verification_tokens WHERE token_hash=?'); $q->execute([$hash]);
            $id=(int)$q->fetchColumn(); if ($id<1) return false;
            $this->database->beginTransaction(); $user=$this->lockUser($id);
            $q=$this->database->prepare('SELECT email_hash,expires_at,used_at FROM email_verification_tokens WHERE token_hash=? AND user_id=?'.($this->database->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE'));
            $q->execute([$hash,$id]); $proof=$q->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$proof || (int)$user['is_active']!==1 || !empty($user['email_verified_at'])
                || $proof['used_at']!==null || (int)$proof['expires_at']<=time()
                || !hash_equals($proof['email_hash'],hash('sha256',strtolower(trim($user['email']))))) {
                $this->database->rollBack(); return false;
            }
            $q=$this->database->prepare('UPDATE users SET email_verified_at=CURRENT_TIMESTAMP WHERE user_id=? AND email_verified_at IS NULL AND is_active=1'); $q->execute([$id]);
            if ($q->rowCount()!==1) { $this->database->rollBack(); return false; }
            $this->database->prepare('UPDATE email_verification_tokens SET used_at=CURRENT_TIMESTAMP WHERE user_id=? AND used_at IS NULL')->execute([$id]);
            $this->database->commit(); return true;
        } catch(Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            return false;
        }
    }
}
