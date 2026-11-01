<?php

declare(strict_types=1);

namespace LSC\Invitations;

use PDO;
use LSC\Core\AppUrl;
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
        // Fail closed before writing anything: consent links must never be
        // built from the request Host header.
        $inviteBase = AppUrl::base();
        if ($inviteBase === null) {
            return ['ok' => false, 'error' => 'Invitations are unavailable because this site has no configured public URL. Contact an administrator.'];
        }

        if ($this->database->inTransaction()) {
            return ['ok' => false, 'error' => 'The invitation could not be created.'];
        }
        try {
            $this->database->beginTransaction();
            // Serialize resends and recheck the acting account, not submitted role data.
            $actor = $this->database->prepare('SELECT role FROM users WHERE user_id = ? AND is_active = 1 FOR UPDATE');
            $actor->execute([$invitedBy]);
            $actorRole = $actor->fetchColumn();
            $owner = $this->database->prepare('SELECT created_by FROM study_groups WHERE group_id = ? FOR UPDATE');
            $owner->execute([$groupId]);
            $creator = $owner->fetchColumn();
            if ($actorRole === false || $creator === false
                || ($actorRole !== 'admin' && (int)$creator !== $invitedBy)) {
                $this->database->rollBack();
                return ['ok' => false, 'error' => 'Only the group creator or an administrator may invite members.'];
            }
            if ($this->invitations->memberWithEmail($groupId, $email)) {
                $this->database->rollBack();
                return ['ok' => false, 'error' => 'That person is already a group member.'];
            }
            $invitedUserId = $this->invitations->activeUserIdByEmail($email);
            $pending = $this->invitations->findPending($groupId, $email);
            if ($pending) {
                // Delayed/earlier emails must remain usable until real expiry or acceptance.
                $token = $pending['token'];
            } else {
                $token = bin2hex(random_bytes(32));
                $this->invitations->declinePending($groupId, $email);
                if (!$this->invitations->create($groupId, $invitedBy, $invitedUserId ?: null, $email, $token)) {
                    throw new \RuntimeException('Invitation persistence failed.');
                }
            }
            if ($invitedUserId && !$this->notifications->create(
                $invitedUserId, 'group_invite', 'You were invited to a study group',
                'Open the invitation to join ' . $this->groups->groupName($groupId) . '.',
                'invite.php?token=' . $token
            )) {
                throw new \RuntimeException('Invitation notification failed.');
            }
            // Queueing is a database operation, not an inline provider request.
            // Preserve the recipient's channel preferences and existing worker safeguards.
            $groupName = $this->groups->groupName($groupId);
            $subject = "You've been invited to join {$groupName} on LSC";
            $body = "You were invited to join the study group \"{$groupName}\" on LSC.\n\n"
                . 'Open this link to accept: ' . $inviteBase . '/invite.php?token=' . rawurlencode($token) . "\n\n"
                . 'If you were not expecting this, you can ignore this email.';
            $queued = $invitedUserId
                ? $this->dispatcher->queueForUser($invitedUserId, $subject, $body)
                : $this->dispatcher->queueEmail($email, $subject, $body);
            $this->database->commit();
            return ['ok' => true, 'token' => $token, 'registered' => (bool)$invitedUserId,
                'reused' => (bool)$pending, 'delivery_queued' => $queued];
        } catch (Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            error_log('InvitationService::create failed.');
            return ['ok' => false, 'error' => 'The invitation could not be created. Please try again.'];
        }
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
}
