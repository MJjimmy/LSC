<?php

declare(strict_types=1);

namespace LSC\Invitations;

use PDO;
use LSC\Groups\GroupRepository;
use LSC\Groups\GroupService;
use LSC\Notifications\NotificationDispatcher;
use LSC\Notifications\NotificationRepository;
use Throwable;

final class InvitationService
{
    public function __construct(
        private readonly PDO $database,
        private readonly InvitationRepository $invitations,
        private readonly GroupRepository $groups,
        private readonly GroupService $groupService,
        private readonly NotificationRepository $notifications,
        private readonly NotificationDispatcher $dispatcher
    ) {
    }

    public function create(int $groupId, int $invitedBy, string $role, string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$this->groupService->canManage($groupId, $invitedBy, $role)) {
            return ['ok' => false, 'error' => 'Enter a valid email address.'];
        }
        if ($this->invitations->memberWithEmail($groupId, $email)) {
            return ['ok' => false, 'error' => 'That person is already a group member.'];
        }

        $invitedUserId = $this->invitations->activeUserIdByEmail($email);
        $token = bin2hex(random_bytes(32));
        $this->invitations->declinePending($groupId, $email);
        $ok = $this->invitations->create($groupId, $invitedBy, $invitedUserId ?: null, $email, $token);

        if ($ok && $invitedUserId) {
            $this->notifications->create(
                $invitedUserId,
                'group_invite',
                'You were invited to a study group',
                'Open the invitation to join ' . $this->groups->groupName($groupId) . '.',
                'invite.php?token=' . $token
            );
        }

        // Outbound email goes through the notification queue. Registered
        // users receive it on the channels they enabled (profile >
        // Notifications); unregistered people are always emailed - the link
        // is how they find out an LSC account is waiting for them.
        if ($ok) {
            $groupName = $this->groups->groupName($groupId);
            $inviteUrl = $this->inviteUrl($token);
            $subject = "You've been invited to join {$groupName} on LSC";
            $body = "You were invited to join the study group \"{$groupName}\" on LSC.\n\n"
                . "Open this link to accept: {$inviteUrl}\n\n"
                . "If you weren't expecting this, you can ignore this email.";
            if ($invitedUserId) {
                $this->dispatcher->queueForUser($invitedUserId, $subject, $body);
            } else {
                $this->dispatcher->queueEmail($email, $subject, $body);
            }
        }

        return $ok
            ? ['ok' => true, 'token' => $token, 'registered' => (bool)$invitedUserId]
            : ['ok' => false, 'error' => 'The invitation could not be created.'];
    }

    public static function belongsTo(array $invite, int $userId, string $email): bool
    {
        if ($userId < 1 || $email === '') {
            return false;
        }
        // Once tied to an existing account, email matching cannot transfer ownership.
        return $invite['invited_user_id'] !== null
            ? (int)$invite['invited_user_id'] === $userId
            : strcasecmp(trim($invite['email']), trim($email)) === 0;
    }

    public function accept(string $token, int $userId): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || $userId < 1) {
            return ['ok' => false, 'error' => 'This invitation link is invalid.'];
        }
        try {
            $this->database->beginTransaction();
            $invite = $this->invitations->lockForAcceptance($token, $userId);
            if (!$invite || !self::belongsTo($invite, $userId, $invite['accepting_email'])) {
                $this->database->rollBack();
                return ['ok' => false, 'error' => 'Sign in with the verified account that received this invitation.'];
            }
            $groupId = (int)$invite['group_id'];
            if ($invite['status'] === 'accepted' && $this->groups->isMember($groupId, $userId)) {
                $this->database->commit();
                return ['ok' => true, 'group_id' => $groupId];
            }
            if ($invite['status'] !== 'pending' || !(bool)$invite['not_expired']) {
                $this->database->rollBack();
                return ['ok' => false, 'error' => 'This invitation has expired or is no longer available. Ask the group owner for a new link.'];
            }
            if (!$this->groups->join($groupId, $userId) || !$this->groups->isMember($groupId, $userId)
                || !$this->invitations->markAccepted((int)$invite['invitation_id'], $userId)) {
                throw new \RuntimeException('Invitation membership was not saved.');
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('InvitationService::accept failed: ' . get_class($exception));
            return ['ok' => false, 'error' => 'The invitation could not be accepted. Please try again later.'];
        }
        // Membership is already committed. A secondary notification must not turn success into failure.
        try {
            $this->notifications->create(
                (int)$invite['invited_by'], 'invite_accepted', 'Group invitation accepted',
                'A new member joined ' . $invite['group_name'] . ' through your invitation.',
                'group.php?id=' . $groupId . '&tab=members'
            );
        } catch (Throwable) {
            error_log('Invitation accepted; owner notification could not be saved.');
        }
        return ['ok' => true, 'group_id' => $groupId];
    }

    private function inviteUrl(string $token): string
    {
        $baseUrl = rtrim((string)getenv('APP_URL'), '/');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $baseUrl = $scheme . '://' . $host;
        }
        return $baseUrl . '/invite.php?token=' . rawurlencode($token);
    }
}
