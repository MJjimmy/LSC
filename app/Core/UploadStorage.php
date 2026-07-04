<?php

declare(strict_types=1);

namespace StudySync\Core;

final class UploadStorage
{
    public function __construct(private readonly string $root)
    {
    }

    public function store(array $file, string $category = 'group-files'): array
    {
        $rules = [
            'group-files' => [
                'max' => 100 * 1024 * 1024,
                'extensions' => ['pdf', 'docx', 'pptx', 'xlsx', 'txt', 'csv', 'zip', 'png', 'jpg', 'jpeg', 'gif', 'webp'],
            ],
            'avatars' => [
                'max' => 5 * 1024 * 1024,
                'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            ],
        ];

        if (!isset($rules[$category])) {
            return ['ok' => false, 'error' => 'Unknown upload category.'];
        }

        $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $limit = (int)round($rules[$category]['max'] / 1024 / 1024);
            return ['ok' => false, 'error' => "The file is too large. The maximum size is {$limit} MB."];
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'The upload did not complete. Please try again.'];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $rules[$category]['max']) {
            $limit = (int)round($rules[$category]['max'] / 1024 / 1024);
            return ['ok' => false, 'error' => "Files must be no larger than {$limit} MB."];
        }

        $originalName = basename((string)($file['name'] ?? 'upload'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $rules[$category]['extensions'], true)) {
            return ['ok' => false, 'error' => 'That file type is not supported.'];
        }

        if (
            in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)
            && @getimagesize($file['tmp_name']) === false
        ) {
            return ['ok' => false, 'error' => 'The selected image is not valid.'];
        }

        $uploadDirectory = $this->root . DIRECTORY_SEPARATOR . $category;
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            return ['ok' => false, 'error' => 'Upload storage is unavailable.'];
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['ok' => false, 'error' => 'The file could not be saved.'];
        }

        return [
            'ok' => true,
            'file_name' => $originalName,
            'file_path' => '/uploads/' . $category . '/' . $storedName,
            'file_type' => $extension,
            'file_size' => $size,
        ];
    }

    public function resolve(?string $storedPath): ?string
    {
        if (!$storedPath || !str_starts_with($storedPath, '/uploads/')) {
            return null;
        }

        $relativePath = str_replace('/', DIRECTORY_SEPARATOR, substr($storedPath, strlen('/uploads/')));
        $root = realpath($this->root);
        $target = realpath($this->root . DIRECTORY_SEPARATOR . $relativePath);

        if (!$root || !$target || !str_starts_with($target, $root . DIRECTORY_SEPARATOR) || !is_file($target)) {
            return null;
        }

        return $target;
    }

    public function remove(?string $storedPath): void
    {
        $target = $this->resolve($storedPath);
        if ($target) {
            @unlink($target);
        }
    }
}
