<?php

declare(strict_types=1);

namespace StudySync\Groups;

use StudySync\Chat\MessageRepository;
use StudySync\Core\Auth;
use StudySync\Core\Csrf;
use StudySync\Core\UploadStorage;
use StudySync\Files\FileRepository;
use StudySync\Invitations\InvitationService;
use StudySync\Tasks\TaskRepository;

final class GroupController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly GroupRepository $groups,
        private readonly GroupService $service,
        private readonly UploadStorage $storage,
        private readonly TaskRepository $tasks,
        private readonly FileRepository $files,
        private readonly MessageRepository $messages,
        private readonly InvitationService $invitations
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        $userId = (int)$_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();

            if (isset($_POST['create_group'])) {
                $name = trim((string)($_POST['group_name'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                if ($name !== '' && mb_strlen($name) <= 100) {
                    $this->groups->create($name, $description, isset($_POST['is_private']) ? 1 : 0, $userId);
                }
            } elseif (($groupId = (int)($_POST['request_join_group_id'] ?? 0)) > 0) {
                $this->service->requestJoin($groupId, $userId);
            }

            header('Location: groups.php');
            exit;
        }

        $myGroups = $this->groups->forUser($userId);
        $publicGroups = $this->groups->publicGroupsAvailableTo($userId);
        ob_start();
        require STUDYSYNC_ROOT . '/views/groups/index.php';
    }

    public function show(): void
    {
        $this->auth->requireLogin();
        $userId = (int)$_SESSION['user_id'];
        $role = (string)$_SESSION['user_role'];
        $groupId = (int)($_GET['id'] ?? 0);
        $group = $groupId > 0 ? $this->groups->find($groupId) : false;

        if (!$group) {
            header('Location: groups.php');
            exit;
        }

        $canManageGroup = $this->service->canManage($groupId, $userId, $role);
        $isMember = $this->groups->isMember($groupId, $userId);
        if (!$isMember && !$canManageGroup) {
            header('Location: groups.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $this->handleShowAction($groupId, $userId, $role, $canManageGroup, $isMember);
        }

        $groupFlash = $_SESSION['group_flash'] ?? null;
        unset($_SESSION['group_flash']);
        $tab = (string)($_GET['tab'] ?? 'tasks');
        $members = $this->groups->members($groupId);
        $tasks = $this->tasks->forGroup($groupId);
        $files = $this->files->forGroup($groupId);
        $messages = $this->messages->forGroup($groupId);
        $pendingJoinRequests = $canManageGroup ? $this->groups->pendingRequests($groupId) : [];
        $tabs = [
            'tasks' => ['icon' => 'bi-check2-square', 'label' => 'Tasks'],
            'members' => ['icon' => 'bi-people-fill', 'label' => 'Members'],
            'files' => ['icon' => 'bi-folder-fill', 'label' => 'Files'],
            'chat' => ['icon' => 'bi-chat-dots-fill', 'label' => 'Chat'],
        ];

        ob_start();
        require STUDYSYNC_ROOT . '/views/groups/show.php';
    }

    private function handleShowAction(
        int $groupId,
        int $userId,
        string $role,
        bool $canManageGroup,
        bool $isMember
    ): void {
        if (isset($_POST['delete_group']) && $canManageGroup) {
            $this->groups->delete($groupId);
            $this->redirect('groups.php');
        }

        if (isset($_POST['approve_request_id']) && $canManageGroup) {
            $this->service->approveRequest((int)$_POST['approve_request_id'], $userId, $role);
            $this->redirect("group.php?id={$groupId}&tab=members");
        }

        if (isset($_POST['reject_request_id']) && $canManageGroup) {
            $this->service->rejectRequest((int)$_POST['reject_request_id'], $userId, $role);
            $this->redirect("group.php?id={$groupId}&tab=members");
        }

        if (isset($_POST['remove_member_user_id']) && $canManageGroup) {
            $this->service->removeMember(
                $groupId,
                (int)$_POST['remove_member_user_id'],
                $userId,
                $role
            );
            $this->redirect("group.php?id={$groupId}&tab=members");
        }

        if (isset($_POST['invite_email']) && $canManageGroup) {
            $result = $this->invitations->create(
                $groupId,
                $userId,
                $role,
                (string)$_POST['invite_email']
            );
            $_SESSION['group_flash'] = $result['ok']
                ? [
                    'type' => 'success',
                    'message' => $result['registered']
                        ? 'Invitation created and added to the user\'s notifications.'
                        : 'Invitation created. Copy the link below and share it securely.',
                    'invite_path' => 'invite.php?token=' . $result['token'],
                ]
                : ['type' => 'error', 'message' => $result['error']];
            $this->redirect("group.php?id={$groupId}&tab=members");
        }

        if (isset($_POST['send_message']) && $isMember) {
            $this->sendMessage($groupId, $userId);
            $this->redirect("group.php?id={$groupId}&tab=chat");
        }
    }

    private function sendMessage(int $groupId, int $userId): void
    {
        $message = trim((string)($_POST['message'] ?? ''));
        $attachmentId = null;

        if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->storage->store($_FILES['attachment'], 'group-files');
            if ($stored['ok']) {
                $attachmentId = $this->files->create(
                    $groupId,
                    $userId,
                    $stored['file_name'],
                    $stored['file_path'],
                    $stored['file_type'],
                    $stored['file_size']
                );
                if (!$attachmentId) {
                    $this->storage->remove($stored['file_path']);
                    $_SESSION['group_flash'] = ['type' => 'error', 'message' => 'The attachment could not be saved.'];
                }
            } else {
                $_SESSION['group_flash'] = ['type' => 'error', 'message' => $stored['error']];
            }
        }

        if (($message !== '' || $attachmentId) && !$this->messages->create($groupId, $userId, $message, $attachmentId ?: null)) {
            $_SESSION['group_flash'] = ['type' => 'error', 'message' => 'The message could not be sent.'];
        }
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
