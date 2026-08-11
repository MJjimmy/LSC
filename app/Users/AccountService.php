<?php

declare(strict_types=1);

namespace LSC\Users;

final class AccountService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function authenticate(string $email, string $password): array
    {
        if (trim($email) === '' || $password === '') {
            return ['ok' => false, 'error' => 'Please enter your email and password.'];
        }
        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }
        if ((int)$user['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Your account has been deactivated. Contact an administrator.'];
        }
        return ['ok' => true, 'user' => $user];
    }

    public function register(
        string $name,
        string $email,
        string $password,
        string $confirmation,
        string $role
    ): array
    {
        $name = trim($name);
        $email = trim($email);
        $role = strtolower(trim($role));
        if ($name === '' || $email === '' || $password === '') {
            return ['ok' => false, 'error' => 'All fields are required.'];
        }
        if (!in_array($role, ['student', 'lecturer'], true)) {
            return ['ok' => false, 'error' => 'Please select Student or Lecturer.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if ($password !== $confirmation) {
            return ['ok' => false, 'error' => 'Passwords do not match.'];
        }
        if ($this->users->findByEmail($email)) {
            return ['ok' => false, 'error' => 'This email is already registered.'];
        }
        return $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), $role)
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Something went wrong. Please try again.'];
    }
}
