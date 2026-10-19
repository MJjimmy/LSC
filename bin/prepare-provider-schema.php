<?php

declare(strict_types=1);

// Never expose a schema-writing endpoint to HTTP, even if misrouted by a server.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;

try {
    $db = Database::connection();
    $schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if ($schema === '') {
        throw new RuntimeException('No database selected.');
    }
    $columns = static function (string $table) use ($db, $schema): array {
        $query = $db->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $query->execute([$schema, $table]);
        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $result[$column['COLUMN_NAME']] = $column;
        }
        return $result;
    };
    $users = $columns('users');
    $userType = strtolower((string)($users['user_id']['COLUMN_TYPE'] ?? ''));
    if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(\d+\))?( unsigned)?$/D', $userType)) {
        throw new RuntimeException('Unsupported users.user_id type; no schema changes made.');
    }
    $engineQuery = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $engineQuery->execute([$schema, 'users']);
    if (strtoupper((string)$engineQuery->fetchColumn()) !== 'INNODB') {
        throw new RuntimeException('users must already use InnoDB; no schema changes made.');
    }
    // Embedded SQL: deployment images need bin/, not a copied database/ directory.
    // The only interpolated SQL token is the strictly validated existing integer type.
    $db->exec("CREATE TABLE IF NOT EXISTS oauth_identities (
        provider VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        subject VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        user_id {$userType} NOT NULL,
        UNIQUE KEY uq_oauth_provider_subject (provider, subject),
        UNIQUE KEY uq_oauth_user_provider (user_id, provider),
        CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(user_id)
    ) ENGINE=InnoDB");

    // CREATE IF NOT EXISTS is not validation. Fail closed; never repair/reset an existing table.
    $oauth = $columns('oauth_identities');
    foreach (['provider' => 32, 'subject' => 255] as $name => $length) {
        $column = $oauth[$name] ?? [];
        if (($column['DATA_TYPE'] ?? '') !== 'varchar' || (int)($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0) !== $length
            || ($column['IS_NULLABLE'] ?? '') !== 'NO' || ($column['COLLATION_NAME'] ?? '') !== 'ascii_bin') {
            throw new RuntimeException('oauth_identities string columns mismatch; manual review required.');
        }
    }
    if (strtolower((string)($oauth['user_id']['COLUMN_TYPE'] ?? '')) !== $userType || ($oauth['user_id']['IS_NULLABLE'] ?? '') !== 'NO') {
        throw new RuntimeException('oauth_identities user_id mismatch; manual review required.');
    }
    $engineQuery->execute([$schema, 'oauth_identities']);
    if (strtoupper((string)$engineQuery->fetchColumn()) !== 'INNODB') {
        throw new RuntimeException('oauth_identities engine mismatch; manual review required.');
    }
    $query = $db->prepare('SELECT INDEX_NAME, COLUMN_NAME, SUB_PART, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $query->execute([$schema, 'oauth_identities']);
    $unique = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $index) {
        if ((int)$index['NON_UNIQUE'] === 0) {
            $unique[$index['INDEX_NAME']][] = $index['SUB_PART'] === null ? $index['COLUMN_NAME'] : 'PREFIX_NOT_ALLOWED';
        }
    }
    if (!in_array(['provider', 'subject'], $unique, true) || !in_array(['user_id', 'provider'], $unique, true)) {
        throw new RuntimeException('oauth_identities exact unique keys mismatch; manual review required.');
    }
    $query = $db->prepare('SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION');
    $query->execute([$schema, 'oauth_identities']);
    $foreign = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $key) {
        $foreign[$key['CONSTRAINT_NAME']][] = [$key['COLUMN_NAME'], $key['REFERENCED_TABLE_SCHEMA'], $key['REFERENCED_TABLE_NAME'], $key['REFERENCED_COLUMN_NAME']];
    }
    if (!in_array([['user_id', $schema, 'users', 'user_id']], $foreign, true)) {
        throw new RuntimeException('oauth_identities users foreign key mismatch; manual review required.');
    }

    $queue = $columns('notification_queue');
    if ($queue === []) {
        throw new RuntimeException('notification_queue is missing; existing application schema must be provisioned first.');
    }
    if (!isset($queue['provider_message_id'])) {
        $db->exec('ALTER TABLE notification_queue ADD COLUMN provider_message_id VARCHAR(255) NULL DEFAULT NULL');
    }
    $queue = $columns('notification_queue');
    $messageId = $queue['provider_message_id'] ?? [];
    if (($messageId['DATA_TYPE'] ?? '') !== 'varchar' || (int)($messageId['CHARACTER_MAXIMUM_LENGTH'] ?? 0) !== 255
        || ($messageId['IS_NULLABLE'] ?? '') !== 'YES') {
        throw new RuntimeException('notification_queue.provider_message_id mismatch; manual review required.');
    }
    fwrite(STDOUT, "Provider schema ready: OAuth keys/FK verified; nullable queue provider_message_id verified.\n");
} catch (Throwable $exception) {
    // No SQL, connection strings, credentials or row values in migration output.
    fwrite(STDERR, "Provider schema preparation failed. No destructive repair was attempted. Review database schema and permissions.\n");
    exit(1);
}
