<?php

declare(strict_types=1);

namespace StudySync\Invitations;

use PDO;

final class InvitationRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function memberWithEmail(int $groupId, string $email): bool
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT 1 FROM users u
            JOIN group_members gm ON gm.user_id = u.user_id
            WHERE gm.group_id = ? AND LOWER(u.email) = ? LIMIT 1
            SQL);
        $statement->execute([$groupId, $email]);
        return (bool)$statement->fetch();
    }

    public function activeUserIdByEmail(string $email): int
    {
        $statement = $this->database->prepare(
            'SELECT user_id FROM users WHERE LOWER(email) = ? AND is_active = 1 LIMIT 1'
        );
        $statement->execute([$email]);
        return (int)$statement->fetchColumn();
    }

    public function declinePending(int $groupId, string $email): void
    {
        $this->database->prepare(<<<'SQL'
            UPDATE group_invitations SET status = 'declined'
            WHERE group_id = ? AND LOWER(email) = ? AND status = 'pending'
            SQL)->execute([$groupId, $email]);
    }

    public function create(int $groupId, int $invitedBy, ?int $invitedUserId, string $email, string $token): bool
    {
        $statement = $this->database->prepare(<<<'SQL'
            INSERT INTO group_invitations
                (group_id, invited_by, invited_user_id, email, token, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            SQL);
        return $statement->execute([$groupId, $invitedBy, $invitedUserId, $email, $token]);
    }

    public function findByToken(string $token): array|false
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT gi.*, sg.group_name, sg.description, inviter.name AS inviter_name
            FROM group_invitations gi
            JOIN study_groups sg ON sg.group_id = gi.group_id
            JOIN users inviter ON inviter.user_id = gi.invited_by
            WHERE gi.token = ? LIMIT 1
            SQL);
        $statement->execute([$token]);
        return $statement->fetch();
    }

    public function lockForAcceptance(string $token, int $userId): array|false
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT gi.*, u.email AS accepting_email, sg.group_name
            FROM group_invitations gi
            JOIN users u ON u.user_id = ?
            JOIN study_groups sg ON sg.group_id = gi.group_id
            WHERE gi.token = ? FOR UPDATE
            SQL);
        $statement->execute([$userId, $token]);
        return $statement->fetch();
    }

    public function markAccepted(int $invitationId, int $userId): bool
    {
        return $this->database->prepare(<<<'SQL'
            UPDATE group_invitations SET status = 'accepted', invited_user_id = ? WHERE invitation_id = ?
            SQL)->execute([$userId, $invitationId]);
    }
}
