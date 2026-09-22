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

    public function accept(string $token, int $userId): array
    {
        try {
            $this->database->beginTransaction();
            $invite = $this->invitations->lockForAcceptance($token, $userId);
            $correctUser = $invite && (
                (int)($invite['invited_user_id'] ?? 0) === $userId
                || strtolower($invite['email']) === strtolower($invite['accepting_email'])
            );
            if (!$invite || $invite['status'] !== 'pending' || strtotime($invite['expires_at']) < time() || !$correctUser) {
                $this->database->rollBack();
                return ['ok' => false, 'error' => 'This invitation is invalid, expired, or belongs to another account.'];
            }

            $this->groups->join((int)$invite['group_id'], $userId);
            $this->invitations->markAccepted((int)$invite['invitation_id'], $userId);
            $this->database->commit();
            $this->notifications->create(
                (int)$invite['invited_by'],
                'invite_accepted',
                'Group invitation accepted',
                'A new member joined ' . $invite['group_name'] . ' through your invitation.',
                'group.php?id=' . (int)$invite['group_id'] . '&tab=members'
            );
            return ['ok' => true, 'group_id' => (int)$invite['group_id']];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('InvitationService::accept failed: ' . $exception->getMessage());
            return ['ok' => false, 'error' => 'The invitation could not be accepted.'];
        }
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
