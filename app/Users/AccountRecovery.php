<?php

declare(strict_types=1);

namespace LSC\Users;

use PDO;
use Throwable;

/**
 * Operator-facing account recovery, used only by bin/account-recovery.php.
 *
 * Sign-in requires users.email_verified_at (Auth::activeUser) and the email
 * proof flow needs working outbound email. That combination has no recovery
 * path: on a fresh install with email delivery unconfigured, or after an
 * upgrade where every existing account is unverified, nobody - including the
 * administrator - can sign in and nothing in the UI can repair it.
 *
 * Every method here is explicit and auditable. None of them guesses mailbox
 * ownership, so a deliberate decision (grandfather a known cohort, promote a
 * new administrator, or re-issue a password) stays with the operator.
 */
final class AccountRecovery
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** Snapshot for preflight checks and CLI `status`. */
    public function status(): array
    {
        $snapshot = ['schema_ready' => true, 'accounts' => 0, 'unverified' => 0, 'admins' => 0, 'verified_admins' => 0];
        try {
            $snapshot['accounts'] = (int)$this->database->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $snapshot['unverified'] = (int)$this->database
                ->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn();
            $snapshot['admins'] = (int)$this->database
                ->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            $snapshot['verified_admins'] = (int)$this->database
                ->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND email_verified_at IS NOT NULL")
                ->fetchColumn();
        } catch (Throwable) {
            // Pre-migration databases have no email_verified_at column yet.
            $snapshot['schema_ready'] = false;
        }
        return $snapshot;
    }

    /** Create a verified, active administrator that can sign in immediately. */
    public function createAdmin(string $email, string $name, string $password, bool $dryRun = false): array
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        if (($invalid = $this->validateIdentity($email, $name)) !== null) {
            return $this->fail($invalid);
        }
        if (($policy = PasswordPolicy::error($password)) !== null) {
            return $this->fail($policy);
        }
        if ($this->find($email) !== false) {
            return $this->fail('That email is already registered. Use promote-admin and set-password instead.');
        }
        if ($dryRun) {
            return $this->done('Dry run: would create a verified administrator for ' . $email . '.');
        }

        return $this->transactional(function () use ($email, $name, $password): array {
            $statement = $this->database->prepare(<<<'SQL'
                INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at)
                VALUES (?, ?, ?, 'admin', 1, CURRENT_TIMESTAMP)
                SQL);
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            return $this->done('Created a verified administrator for ' . $email . '.');
        });
    }

    /** Grant administrator rights without touching the password or proof. */
    public function promoteAdmin(string $email, bool $dryRun = false): array
    {
        $email = strtolower(trim($email));
        if (($invalid = $this->validateEmail($email)) !== null) {
            return $this->fail($invalid);
        }
        $user = $this->find($email);
        if ($user === false) {
            return $this->fail('No account exists for ' . $email . '. Use create-admin to make one.');
        }
        $detail = 'Promoted ' . $email . ' to administrator and reactivated the account.';
        if (empty($user['email_verified_at'])) {
            $detail .= ' The mailbox is still unverified: run verify-email before signing in.';
        }
        if ($dryRun) {
            return $this->done('Dry run: ' . lcfirst($detail));
        }

        return $this->transactional(function () use ($user, $detail): array {
            $this->database->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE user_id = ?")
                ->execute([(int)$user['user_id']]);
            return $this->done($detail);
        });
    }

    /**
     * Record that a mailbox is proven without an email round-trip. This is the
     * deliberate grandfathering step for accounts created before ownership
     * proof existed.
     */
    public function verifyEmail(string $email, bool $dryRun = false): array
    {
        $email = strtolower(trim($email));
        if (($invalid = $this->validateEmail($email)) !== null) {
            return $this->fail($invalid);
        }
        $user = $this->find($email);
        if ($user === false) {
            return $this->fail('No account exists for ' . $email . '.');
        }
        if (!empty($user['email_verified_at'])) {
            return $this->done($email . ' was already verified; nothing changed.');
        }
        if ($dryRun) {
            return $this->done('Dry run: would mark ' . $email . ' as verified.');
        }

        return $this->transactional(function () use ($user, $email): array {
            $statement = $this->database->prepare(
                'UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE user_id = ? AND email_verified_at IS NULL'
            );
            $statement->execute([(int)$user['user_id']]);
            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('Verification was already recorded by another process.');
            }
            return $this->done('Marked ' . $email . ' as verified.');
        });
    }

    /**
     * Replace a password and, when needed, prove the mailbox. Existing sessions
     * end automatically because sessions are bound to the password hash.
     */
    public function setPassword(string $email, string $password, bool $dryRun = false): array
    {
        $email = strtolower(trim($email));
        if (($invalid = $this->validateEmail($email)) !== null) {
            return $this->fail($invalid);
        }
        if (($policy = PasswordPolicy::error($password)) !== null) {
            return $this->fail($policy);
        }
        $user = $this->find($email);
        if ($user === false) {
            return $this->fail('No account exists for ' . $email . '.');
        }
        if (password_verify($password, (string)$user['password_hash'])) {
            return $this->fail('That password is already in use for this account; choose a new one.');
        }
        $detail = 'Replaced the password for ' . $email . ' and revoked outstanding reset links. Existing sessions were invalidated.';
        if (empty($user['email_verified_at'])) {
            $detail .= ' The mailbox was unverified, so it is now marked verified.';
        }
        if ($dryRun) {
            return $this->done('Dry run: would ' . lcfirst($detail));
        }

        return $this->transactional(function () use ($user, $email, $password, $detail): array {
            $this->database->prepare(
                'UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE user_id = ?'
            )->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['user_id']]);

            // A reset link issued before this change must not survive it.
            try {
                $this->database->prepare(
                    'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL'
                )->execute([(int)$user['user_id']]);
            } catch (Throwable) {
                // Older schemas without the reset table still get a new password.
            }

            return $this->done($detail);
        });
    }

    private function find(string $email): array|false
    {
        $statement = $this->database->prepare(
            'SELECT user_id, email, role, is_active, password_hash, email_verified_at FROM users WHERE LOWER(email) = ? LIMIT 1'
        );
        $statement->execute([$email]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function validateEmail(string $email): ?string
    {
        if ($email === '' || strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Enter a valid email address (at most 150 characters).';
        }
        return null;
    }

    private function validateIdentity(string $email, string $name): ?string
    {
        if (($invalid = $this->validateEmail($email)) !== null) {
            return $invalid;
        }
        if ($name === '' || mb_strlen($name) > 100) {
            return 'Enter a display name between 1 and 100 characters.';
        }
        return null;
    }

    private function transactional(callable $work): array
    {
        if ($this->database->inTransaction()) {
            return $this->fail('Refused to run inside an open transaction.');
        }
        try {
            $this->database->beginTransaction();
            $result = $work();
            $this->database->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            // Never echo driver text: it can quote column values.
            error_log('AccountRecovery failed: ' . get_class($exception));
            return $this->fail('The change could not be applied. Check the database connection and schema, then retry.');
        }
    }

    /** @return array{ok: bool, error: null|string, detail: string} */
    private function done(string $detail): array
    {
        return ['ok' => true, 'error' => null, 'detail' => $detail];
    }

    /** @return array{ok: bool, error: null|string, detail: string} */
    private function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'detail' => ''];
    }
}
