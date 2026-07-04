<?php

declare(strict_types=1);

namespace StudySync\Files;

use StudySync\Core\Auth;
use StudySync\Core\Csrf;
use StudySync\Groups\GroupRepository;

final class FileController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly FileRepository $files,
        private readonly FileService $service,
        private readonly GroupRepository $groups
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        $userId = (int)$_SESSION['user_id'];
        $myGroups = $this->groups->forUser($userId);
        $groupIds = array_map(static fn(array $group): int => (int)$group['group_id'], $myGroups);
        $selectedGroup = (int)($_GET['group_id'] ?? ($groupIds[0] ?? 0));
        if ($selectedGroup && !in_array($selectedGroup, $groupIds, true)) {
            $selectedGroup = $groupIds[0] ?? 0;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $groupId = (int)($_POST['group_id'] ?? $selectedGroup);
            if (isset($_FILES['file'])) {
                $result = $this->service->upload($_FILES['file'], $groupId, $userId);
                $_SESSION['file_flash'] = [
                    'type' => $result['ok'] ? 'success' : 'error',
                    'message' => $result['ok'] ? 'File uploaded successfully.' : $result['error'],
                ];
            } elseif (isset($_POST['delete_file_id'])) {
                if ($this->service->deleteOwned((int)$_POST['delete_file_id'], $userId)) {
                    $_SESSION['file_flash'] = ['type' => 'success', 'message' => 'File deleted.'];
                }
            }
            header('Location: files.php?group_id=' . $groupId);
            exit;
        }

        $fileFlash = $_SESSION['file_flash'] ?? null;
        unset($_SESSION['file_flash']);
        $files = $selectedGroup ? $this->files->forGroup($selectedGroup) : [];
        $totalSize = round(array_sum(array_column($files, 'file_size')) / 1024 / 1024, 1);
        $currentGroupName = 'None';
        foreach ($myGroups as $group) {
            if ((int)$group['group_id'] === $selectedGroup) {
                $currentGroupName = $group['group_name'];
                break;
            }
        }

        ob_start();
        require STUDYSYNC_ROOT . '/views/files/index.php';
    }
}
