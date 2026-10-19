<?php

declare(strict_types=1);

namespace LSC\Notifications;

use PDO;

/**
 * Queues real-world notifications (email, SMS) and sends them.
 *
 * Nothing here happens inline during a web request — queue() just writes a
 * row. A cron job calls processPending() every minute or so to actually
 * send, so a slow provider API never makes a user wait on a page load.
 *
 * Required environment variables (set these in Hostinger's hPanel, not in
 * code):
 *   RESEND_API_KEY, RESEND_FROM_EMAIL
 *   AFRICASTALKING_USERNAME, AFRICASTALKING_API_KEY, AFRICASTALKING_SENDER_ID (optional)
 */
final class NotificationDispatcher
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly PDO $database, private readonly ?ResendMailer $mailer = null)
    {
    }

    public function queueEmail(string $toEmail, string $subject, string $body, ?int $userId = null): bool
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return $this->insert($userId, 'email', $toEmail, $subject, $body);
    }

    public function queueSms(string $toPhone, string $message, ?int $userId = null): bool
    {
        $normalized = $this->normalizePhone($toPhone);
        if (!$normalized) {
            return false;
        }
        return $this->insert($userId, 'sms', $normalized, null, $message);
    }

    /**
     * Queue an outbound notification respecting the user's own channel
     * preferences (users.notify_email / users.notify_sms). Returns false if
     * nothing was queued (user missing, inactive, or no channel enabled).
     */
    public function queueForUser(int $userId, string $subject, string $message): bool
    {
        $statement = $this->database->prepare(
            'SELECT email, phone_number, notify_email, notify_sms FROM users WHERE user_id = ? AND is_active = 1'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();
        if (!$user) {
            return false;
        }

        $queued = false;
        if ((int)$user['notify_email'] === 1 && $user['email']) {
            $queued = $this->queueEmail($user['email'], $subject, $message, $userId) || $queued;
        }
        if ((int)$user['notify_sms'] === 1 && $user['phone_number']) {
            $queued = $this->queueSms($user['phone_number'], $subject . ': ' . $message, $userId) || $queued;
        }
        return $queued;
    }

    /**
     * Process a batch of pending notifications. Call this from a cron job:
     *   php bin/process-notification-queue.php
     */
    public function processPending(int $limit = 20): array
    {
        $results = ['sent' => 0, 'failed' => 0];
        $mailer = $this->mailer ?? new ResendMailer();
        $cutoff = (string)getenv('NOTIFICATION_SEND_AFTER');
        $minimum = filter_var(getenv('NOTIFICATION_MIN_QUEUE_ID'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $cutoff, new \DateTimeZone('UTC'));
        if ($minimum === false || getenv('NOTIFICATION_WORKER_ENABLED') !== '1' || !$mailer->enabled()
            || !$date || $date->format('Y-m-d H:i:s') !== $cutoff) return $results;
        $name = 'lsc_mail_' . substr(hash('sha256', (string)$this->database->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
        $lock = $this->database->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) return $results;
        try {
            $statement = $this->database->prepare(
                "SELECT * FROM notification_queue WHERE status = 'pending' AND channel = 'email'
                 AND attempts < ? AND created_at >= ? AND queue_id >= ?
                 AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 23 HOUR)
                 ORDER BY created_at, queue_id LIMIT ?"
            );
            $statement->bindValue(1, self::MAX_ATTEMPTS, PDO::PARAM_INT);
            $statement->bindValue(2, $cutoff, PDO::PARAM_STR);
            $statement->bindValue(3, $minimum, PDO::PARAM_INT);
            $statement->bindValue(4, max(1, min(20, $limit)), PDO::PARAM_INT);
            $statement->execute();
            foreach ($statement->fetchAll() as $row) {
                $this->database->prepare('UPDATE notification_queue SET attempts = attempts + 1 WHERE queue_id = ?')
                    ->execute([(int)$row['queue_id']]);
                $key = 'lsc/queue/' . hash('sha256', (string)getenv('APP_URL') . '|' . $row['queue_id'] . '|' . $row['created_at']);
                $sent = $mailer->send($row['recipient'], (string)$row['subject'], $row['body'], $key);
                if ($sent['ok']) {
                    $this->markSent((int)$row['queue_id'], $sent['provider_id']);
                    $results['sent']++;
                } else {
                    $this->markFailed((int)$row['queue_id'], (int)$row['attempts'], $sent['error']);
                    $results['failed']++;
                }
            }
        } finally {
            $this->database->prepare('SELECT RELEASE_LOCK(?)')->execute([$name]);
        }
        // Legacy "sent" means provider-accepted, not inbox-delivered.
        return $results;
    }

    private function sendEmail(string $to, string $subject, string $body): array
    {
        $apiKey = getenv('RESEND_API_KEY');
        $from = getenv('RESEND_FROM_EMAIL');
        if (!$apiKey || !$from) {
            return ['ok' => false, 'error' => 'Email is not configured (missing RESEND_API_KEY/RESEND_FROM_EMAIL).'];
        }

        $payload = json_encode([
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'text' => $body,
        ]);

        return $this->postJson('https://api.resend.com/emails', $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
    }

    private function sendSms(string $to, string $message): array
    {
        $username = getenv('AFRICASTALKING_USERNAME');
        $apiKey = getenv('AFRICASTALKING_API_KEY');
        if (!$username || !$apiKey) {
            return ['ok' => false, 'error' => 'SMS is not configured (missing AFRICASTALKING_USERNAME/AFRICASTALKING_API_KEY).'];
        }

        $fields = [
            'username' => $username,
            'to' => $to,
            'message' => $message,
        ];
        $senderId = getenv('AFRICASTALKING_SENDER_ID');
        if ($senderId) {
            $fields['from'] = $senderId;
        }

        return $this->postForm('https://api.africastalking.com/version1/messaging', $fields, [
            'apiKey: ' . $apiKey,
            'Accept: application/json',
        ]);
    }

    private function postJson(string $url, string $payload, array $headers): array
    {
        return $this->curlRequest($url, $payload, $headers);
    }

    private function postForm(string $url, array $fields, array $headers): array
    {
        return $this->curlRequest($url, http_build_query($fields), array_merge(
            $headers,
            ['Content-Type: application/x-www-form-urlencoded']
        ));
    }

    private function curlRequest(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'Network error: ' . $curlError];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => "HTTP {$status}: " . substr((string)$response, 0, 300)];
        }
        return ['ok' => true, 'error' => null];
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';
        // Expect E.164 format (+27...). Reject anything that clearly isn't a phone number.
        return preg_match('/^\+\d{8,15}$/', $digits) ? $digits : null;
    }

    private function insert(?int $userId, string $channel, string $recipient, ?string $subject, string $body): bool
    {
        $statement = $this->database->prepare(
            'INSERT INTO notification_queue (user_id, channel, recipient, subject, body) VALUES (?, ?, ?, ?, ?)'
        );
        return $statement->execute([$userId, $channel, $recipient, $subject, $body]);
    }

    private function markSent(int $queueId, string $providerId): void
    {
        $this->database->prepare(
            'UPDATE notification_queue SET status = "sent", sent_at = UTC_TIMESTAMP(), provider_message_id = ?, last_error = NULL WHERE queue_id = ?'
        )->execute([$providerId, $queueId]);
    }

    private function markFailed(int $queueId, int $attempts, ?string $error): void
    {
        $attempts++;
        $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
        $this->database->prepare(
            'UPDATE notification_queue SET status = ?, attempts = ?, last_error = ? WHERE queue_id = ?'
        )->execute([$status, $attempts, $error ? substr($error, 0, 500) : null, $queueId]);
    }
}
