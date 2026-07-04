<?php

declare(strict_types=1);

namespace StudySync\Files;

use StudySync\Core\UploadStorage;
use StudySync\Groups\GroupRepository;

final class FileService
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly GroupRepository $groups,
        private readonly UploadStorage $storage
    ) {
    }

    public function upload(array $upload, int $groupId, int $userId): array
    {
        if (!$this->groups->isMember($groupId, $userId)) {
            return ['ok' => false, 'error' => 'You can only upload files to groups you have joined.'];
        }

        $stored = $this->storage->store($upload, 'group-files');
        if (!$stored['ok']) {
            return $stored;
        }

        $fileId = $this->files->create(
            $groupId,
            $userId,
            $stored['file_name'],
            $stored['file_path'],
            $stored['file_type'],
            $stored['file_size']
        );
        if (!$fileId) {
            $this->storage->remove($stored['file_path']);
            return ['ok' => false, 'error' => 'The file could not be added to the library.'];
        }

        return ['ok' => true, 'file_id' => $fileId];
    }

    public function deleteOwned(int $fileId, int $userId): bool
    {
        $file = $this->files->find($fileId);
        if (!$file || (int)$file['uploaded_by'] !== $userId) {
            return false;
        }
        if (!$this->files->deleteOwned($fileId, $userId)) {
            return false;
        }
        $this->storage->remove($file['file_path']);
        return true;
    }
}
