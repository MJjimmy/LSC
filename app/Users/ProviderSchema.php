<?php

declare(strict_types=1);

namespace LSC\Users;

use PDO;
use RuntimeException;

/**
 * Additive preparation of the sign-in/provider tables on Postgres.
 *
 * The MySQL equivalent lives in bin/prepare-provider-schema.php and is kept
 * for legacy installs. Both create only missing objects and refuse a table
 * that exists in an unexpected shape: nothing is ever repaired, rebuilt or
 * backfilled here.
 */
final class ProviderSchema
{
    public static function prepare(PDO $db): void
    {
        $users = self::columns($db, 'users');
        $userIdType = strtolower((string)($users['user_id']['data_type'] ?? ''));
        if (!in_array($userIdType, ['smallint', 'integer', 'bigint'], true)) {
            throw new RuntimeException('Existing user key required.');
        }

        $db->exec("CREATE TABLE IF NOT EXISTS oauth_identities (
            provider varchar(32) NOT NULL,
            subject varchar(255) NOT NULL,
            user_id {$userIdType} NOT NULL REFERENCES users(user_id),
            CONSTRAINT uq_oauth_provider_subject UNIQUE (provider, subject),
            CONSTRAINT uq_oauth_user_provider UNIQUE (user_id, provider)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS app_auth_codes (
            code_hash char(64) NOT NULL,
            challenge char(64) NOT NULL,
            user_id {$userIdType} NOT NULL REFERENCES users(user_id),
            expires_at bigint NOT NULL,
            CONSTRAINT pk_app_auth_codes PRIMARY KEY (code_hash)
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_app_auth_expiry ON app_auth_codes (expires_at)');

        if (!isset(self::columns($db, 'notification_queue')['provider_message_id'])) {
            $db->exec('ALTER TABLE notification_queue ADD COLUMN provider_message_id varchar(255) NULL DEFAULT NULL');
        }

        self::assertColumns($db, 'oauth_identities', [
            'provider' => ['character varying', 32, 'NO'],
            'subject' => ['character varying', 255, 'NO'],
            'user_id' => [$userIdType, null, 'NO'],
        ]);
        self::assertColumns($db, 'app_auth_codes', [
            'code_hash' => ['character', 64, 'NO'],
            'challenge' => ['character', 64, 'NO'],
            'user_id' => [$userIdType, null, 'NO'],
            'expires_at' => ['bigint', null, 'NO'],
        ]);
        self::assertColumns($db, 'notification_queue', [
            'provider_message_id' => ['character varying', 255, 'YES'],
        ]);

        self::assertUnique($db, 'oauth_identities', ['provider', 'subject']);
        self::assertUnique($db, 'oauth_identities', ['user_id', 'provider']);
        self::assertPrimaryKey($db, 'app_auth_codes', ['code_hash']);
        self::assertForeignKey($db, 'oauth_identities');
        self::assertForeignKey($db, 'app_auth_codes');
        if (self::indexedColumns($db, 'app_auth_codes', ['expires_at']) === false) {
            throw new RuntimeException('app_auth_codes expiry index mismatch; manual review required.');
        }
    }

    /** @return array<string, array<string, string|null>> */
    private static function columns(PDO $db, string $table): array
    {
        $query = $db->prepare(
            'SELECT column_name, data_type, is_nullable, character_maximum_length
             FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ?'
        );
        $query->execute([$table]);
        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['column_name']] = $row;
        }
        return $result;
    }

    /**
     * @param array<string, array{0: string, 1: int|null, 2: string}> $expected
     */
    private static function assertColumns(PDO $db, string $table, array $expected): void
    {
        $columns = self::columns($db, $table);
        foreach ($expected as $name => [$type, $length, $nullable]) {
            $column = $columns[$name] ?? null;
            if ($column === null
                || strtolower((string)$column['data_type']) !== $type
                || ($length !== null && (int)$column['character_maximum_length'] !== $length)
                || (string)$column['is_nullable'] !== $nullable) {
                throw new RuntimeException($table . ' columns mismatch; manual review required.');
            }
        }
    }

    /** @param list<string> $columns */
    private static function assertUnique(PDO $db, string $table, array $columns): void
    {
        $query = $db->prepare(
            "SELECT c.conname, a.attname
             FROM pg_constraint c
             JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
             WHERE c.contype = 'u' AND c.conrelid = ?::regclass
             ORDER BY c.conname, array_position(c.conkey, a.attnum)"
        );
        $query->execute([$table]);
        $sets = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sets[(string)$row['conname']][] = (string)$row['attname'];
        }
        if (!in_array($columns, array_values($sets), true)) {
            throw new RuntimeException($table . ' exact unique keys mismatch; manual review required.');
        }
    }

    /** @param list<string> $columns */
    private static function assertPrimaryKey(PDO $db, string $table, array $columns): void
    {
        $query = $db->prepare(
            "SELECT a.attname
             FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY (i.indkey)
             WHERE i.indrelid = ?::regclass AND i.indisprimary
             ORDER BY a.attnum"
        );
        $query->execute([$table]);
        if ($query->fetchAll(PDO::FETCH_COLUMN) !== $columns) {
            throw new RuntimeException($table . ' primary key mismatch; manual review required.');
        }
    }

    private static function assertForeignKey(PDO $db, string $table): void
    {
        $query = $db->prepare(
            "SELECT kcu.column_name AS local_column, ccu.table_name AS target_table,
                    ccu.column_name AS target_column
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
             WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = current_schema()
               AND tc.table_name = ?"
        );
        $query->execute([$table]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $key = $rows[0] ?? [];
        if (count($rows) !== 1
            || (string)($key['local_column'] ?? '') !== 'user_id'
            || (string)($key['target_table'] ?? '') !== 'users'
            || (string)($key['target_column'] ?? '') !== 'user_id') {
            throw new RuntimeException($table . ' users foreign key mismatch; manual review required.');
        }
    }

    /** @param list<string> $columns */
    private static function indexedColumns(PDO $db, string $table, array $columns): bool
    {
        $query = $db->prepare(
            "SELECT i.indexrelid, a.attname
             FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY (i.indkey)
             WHERE i.indrelid = ?::regclass
             ORDER BY i.indexrelid, a.attnum"
        );
        $query->execute([$table]);
        $indexes = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexes[(string)$row['indexrelid']][] = (string)$row['attname'];
        }

        return in_array($columns, array_values($indexes), true);
    }
}
