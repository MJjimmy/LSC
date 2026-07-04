<?php

declare(strict_types=1);

namespace StudySync\Users;

use StudySync\Core\Auth;
use StudySync\Core\Csrf;
use StudySync\Core\UploadStorage;

final class ProfileController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly UploadStorage $storage
    ) {
    }

    public function show(): void
    {
        $this->auth->requireLogin();
        $profileUserId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);
        $isOwnProfile = $profileUserId === (int)$_SESSION['user_id'];
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            if (!$isOwnProfile) {
                http_response_code(403);
                exit('You can only edit your own profile.');
            }
            [$error, $success] = $this->update($profileUserId);
        }

        $profile = $this->users->findActive($profileUserId);
        if (!$profile) {
            http_response_code(404);
            exit('Profile not found.');
        }

        ob_start();
        require STUDYSYNC_ROOT . '/views/users/profile.php';
    }

    private function update(int $userId): array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        $institution = trim((string)($_POST['institution'] ?? ''));
        $course = trim((string)($_POST['course'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            return ['Enter a name between 1 and 100 characters.', ''];
        }
        if (mb_strlen($institution) > 150 || mb_strlen($course) > 150 || mb_strlen($bio) > 1000) {
            return ['One or more profile fields are too long.', ''];
        }

        $current = $this->users->findActive($userId);
        $newAvatar = $current['avatar_path'] ?? null;
        if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->storage->store($_FILES['avatar'], 'avatars');
            if (!$stored['ok']) {
                return [$stored['error'], ''];
            }
            $newAvatar = $stored['file_path'];
        }

        if (!$this->users->updateProfile($userId, $name, $institution, $course, $bio)) {
            if ($newAvatar !== ($current['avatar_path'] ?? null)) {
                $this->storage->remove($newAvatar);
            }
            return ['Your profile could not be updated.', ''];
        }

        if ($newAvatar !== ($current['avatar_path'] ?? null)) {
            if ($this->users->updateAvatar($userId, $newAvatar)) {
                $this->storage->remove($current['avatar_path'] ?? null);
            } else {
                $this->storage->remove($newAvatar);
                $newAvatar = $current['avatar_path'] ?? null;
            }
        }

        $_SESSION['user_name'] = $name;
        $_SESSION['user_avatar'] = $newAvatar;
        return ['', 'Profile updated successfully.'];
    }
}
