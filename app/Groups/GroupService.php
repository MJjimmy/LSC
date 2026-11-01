<?php

declare(strict_types=1);

namespace LSC\Groups;

use PDO;
use LSC\Notifications\NotificationRepository;
use Throwable;

final class GroupService
{
    public function __construct(
        private readonly PDO $database,
        private readonly GroupRepository $groups,
        private readonly NotificationRepository $notifications
    ) {
    }

    public function canManage(int $groupId, int $userId, string $role): bool
    {
        return $role === 'admin' || $this->groups->isCreator($groupId, $userId);
    }

    public function requestJoin(int $groupId, int $userId): bool
    {
        // Resolve the actor's role from the database, never from submitted fields.
        $actor = $this->database->prepare('SELECT role FROM users WHERE user_id = ? AND is_active = 1');
        $actor->execute([$userId]);
        $role = $actor->fetchColumn();
        if ($role === false) {
            return false;
        }
        if ($role === 'admin') {
            return $this->joinAsAdministrator($groupId, $userId);
        }

        if ($this->groups->isMember($groupId, $userId)) {
            return false;
        }

        $result = $this->groups->addJoinRequest($groupId, $userId);
        if ($result['created'] && ($details = $this->groups->requestNotificationDetails($groupId, $userId))) {
            $this->notifications->create(
                (int)$details['created_by'],
                'join_request',
                'New group join request',
                $details['requester_name'] . ' wants to join ' . $details['group_name'] . '.',
                'group.php?id=' . $groupId . '&tab=members'
            );
        }

        return $result['ok'];
    }

    private function joinAsAdministrator(int $groupId, int $userId): bool
    {
        if (!$this->groups->find($groupId)) {
            return false;
        }
        try {
            $this->groups->ensureJoinRequestsTable();
            $this->database->beginTransaction();
            $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                // Acquire the fixture database write lock before the security read.
                $this->database->prepare('UPDATE users SET user_id = user_id WHERE user_id = ?')->execute([$userId]);
            }
            $actor = $this->database->prepare(
                'SELECT role FROM users WHERE user_id = ? AND is_active = 1'
                . ($driver === 'mysql' ? ' FOR UPDATE' : '')
            );
            $actor->execute([$userId]);
            if ($actor->fetchColumn() !== 'admin') {
                $this->database->rollBack();
                return false;
            }
            if (!$this->groups->join($groupId, $userId)
                || !$this->groups->isMember($groupId, $userId)
                || !$this->groups->deleteRequestsForMember($groupId, $userId)) {
                $this->database->rollBack();
                return false;
            }
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('GroupService::joinAsAdministrator failed.');
            return false;
        }
    }

    public function approveRequest(int $requestId, int $managerId, string $managerRole): bool
    {
        try {
            $this->groups->ensureJoinRequestsTable();
            $this->database->beginTransaction();
            $request = $this->groups->findJoinRequest($requestId, true);
            if (!$request || !$this->canManage((int)$request['group_id'], $managerId, $managerRole)) {
                $this->database->rollBack();
                return false;
            }

            $this->groups->join((int)$request['group_id'], (int)$request['user_id']);
            $this->groups->deleteJoinRequest($requestId);
            $groupName = $this->groups->groupName((int)$request['group_id']);
            $this->database->commit();

            $this->notifications->create(
                (int)$request['user_id'],
                'group_approved',
                'Group request approved',
                'You can now collaborate in ' . $groupName . '.',
                'group.php?id=' . (int)$request['group_id']
            );
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('GroupService::approveRequest failed: ' . $exception->getMessage());
            return false;
        }
    }

    public function rejectRequest(int $requestId, int $managerId, string $managerRole): bool
    {
        $request = $this->groups->findJoinRequest($requestId);
        if (!$request || !$this->canManage((int)$request['group_id'], $managerId, $managerRole)) {
            return false;
        }

        $groupName = $this->groups->groupName((int)$request['group_id']);
        $ok = $this->groups->deleteJoinRequest($requestId);
        if ($ok) {
            $this->notifications->create(
                (int)$request['user_id'],
                'group_declined',
                'Group request update',
                'Your request to join ' . $groupName . ' was declined.',
                'groups.php'
            );
        }
        return $ok;
    }

    public function leaveGroup(int $groupId, int $userId): bool
    {
        if ($groupId <= 0 || $userId <= 0) {
            return false;
        }
        try {
            $this->database->beginTransaction();
            // Self-service only: recheck active identity and creator protection
            // in the mutation itself, not just the page's earlier snapshot.
            $statement = $this->database->prepare(<<<'SQL'
                DELETE FROM group_members
                WHERE group_id = ? AND user_id = ?
                  AND EXISTS (
                      SELECT 1 FROM study_groups sg
                      WHERE sg.group_id = group_members.group_id AND sg.created_by <> ?
                  )
                  AND EXISTS (SELECT 1 FROM users actor WHERE actor.user_id = ? AND actor.is_active = 1)
                SQL);
            $statement->execute([$groupId, $userId, $userId, $userId]);
            if ($statement->rowCount() !== 1 || !$this->groups->deleteRequestsForMember($groupId, $userId)) {
                $this->database->rollBack();
                return false;
            }
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('GroupService::leaveGroup failed.');
            return false;
        }
    }

    public function removeMember(
        int $groupId,
        int $memberId,
        int $managerId,
        string $managerRole
    ): bool {
        if (
            !$this->canManage($groupId, $managerId, $managerRole)
            || $this->groups->isCreator($groupId, $memberId)
        ) {
            return false;
        }
        return $this->groups->removeMember($groupId, $memberId);
    }
}
