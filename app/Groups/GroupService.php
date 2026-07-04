<?php

declare(strict_types=1);

namespace StudySync\Groups;

use PDO;
use StudySync\Notifications\NotificationRepository;
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
