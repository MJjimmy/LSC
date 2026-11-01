<?php

declare(strict_types=1);

// Additive, CLI-only migration. Existing accounts remain unverified; no grandfathering.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;

try {
    $db = Database::connection();
    $schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    $columns = static function (string $table) use ($db, $schema): array {
        $query = $db->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $query->execute([$schema, $table]);
        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $result[$column['COLUMN_NAME']] = $column;
        }
        return $result;
    };
    $users = $columns('users');
    $userType = strtolower((string)($users['user_id']['COLUMN_TYPE'] ?? ''));
    if ($schema === '' || !preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(\d+\))?( unsigned)?$/D', $userType)) {
        throw new RuntimeException('Unsupported users schema.');
    }
    $engine = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $engine->execute([$schema, 'users']);
    if (strtoupper((string)$engine->fetchColumn()) !== 'INNODB') {
        throw new RuntimeException('Transactional users table required.');
    }
    if (!isset($users['email_verified_at'])) {
        $db->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL');
    }
    $verified = $columns('users')['email_verified_at'] ?? [];
    if (($verified['DATA_TYPE'] ?? '') !== 'datetime' || ($verified['IS_NULLABLE'] ?? '') !== 'YES'
        || !in_array($verified['COLUMN_DEFAULT'] ?? null, [null, 'NULL'], true)) {
        throw new RuntimeException('email_verified_at schema mismatch.');
    }
    $db->exec("CREATE TABLE IF NOT EXISTS email_verification_tokens (
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        user_id {$userType} NOT NULL,
        email VARCHAR(150) NOT NULL,
        credential_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        expires_at BIGINT UNSIGNED NOT NULL,
        next_path VARCHAR(500) NOT NULL DEFAULT 'dashboard.php',
        PRIMARY KEY (token_hash),
        KEY idx_verification_user (user_id),
        KEY idx_verification_expiry (expires_at),
        CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $tokens = $columns('email_verification_tokens');
    foreach (['token_hash', 'credential_hash'] as $name) {
        $c = $tokens[$name] ?? [];
        if (($c['DATA_TYPE'] ?? '') !== 'char' || (int)($c['CHARACTER_MAXIMUM_LENGTH'] ?? 0) !== 64
            || ($c['COLLATION_NAME'] ?? '') !== 'ascii_bin' || ($c['IS_NULLABLE'] ?? '') !== 'NO') {
            throw new RuntimeException('Verification hash column mismatch.');
        }
    }
    foreach (['email' => 150, 'next_path' => 500] as $name => $length) {
        $c = $tokens[$name] ?? [];
        if (($c['DATA_TYPE'] ?? '') !== 'varchar' || (int)($c['CHARACTER_MAXIMUM_LENGTH'] ?? 0) !== $length
            || ($c['IS_NULLABLE'] ?? '') !== 'NO') {
            throw new RuntimeException('Verification text column mismatch.');
        }
    }
    if (strtolower((string)($tokens['user_id']['COLUMN_TYPE'] ?? '')) !== $userType
        || ($tokens['user_id']['IS_NULLABLE'] ?? '') !== 'NO'
        || !preg_match('/^bigint(\(\d+\))? unsigned$/D', strtolower((string)($tokens['expires_at']['COLUMN_TYPE'] ?? '')))
        || ($tokens['expires_at']['IS_NULLABLE'] ?? '') !== 'NO') {
        throw new RuntimeException('Verification numeric column mismatch.');
    }
    $engine->execute([$schema, 'email_verification_tokens']);
    if (strtoupper((string)$engine->fetchColumn()) !== 'INNODB') {
        throw new RuntimeException('Transactional verification table required.');
    }
    $query = $db->prepare('SELECT INDEX_NAME, COLUMN_NAME, SUB_PART, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $query->execute([$schema, 'email_verification_tokens']);
    $keys = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $keys[$key['INDEX_NAME']][] = $key['SUB_PART'] === null ? $key['COLUMN_NAME'] : 'PREFIX_NOT_ALLOWED';
    }
    if (($keys['PRIMARY'] ?? []) !== ['token_hash'] || !in_array(['user_id'], $keys, true) || !in_array(['expires_at'], $keys, true)) {
        throw new RuntimeException('Verification indexes mismatch.');
    }
    $query = $db->prepare('SELECT COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
    $query->execute([$schema, 'email_verification_tokens']);
    $foreign = $query->fetchAll(PDO::FETCH_NUM);
    if (!in_array(['user_id', $schema, 'users', 'user_id'], $foreign, true)) {
        throw new RuntimeException('Verification foreign key mismatch.');
    }
    fwrite(STDOUT, "Email verification schema ready. Existing accounts were not marked verified.\n");
} catch (Throwable) {
    fwrite(STDERR, "Email verification migration failed. Review schema and permissions; no destructive repair was attempted.\n");
    exit(1);
}
