<?php

declare(strict_types=1);

namespace LSC\Core;

use PDO;

/**
 * The only place in the application that knows SQL dialects.
 *
 * LSC runs on Postgres in production (Render) and on SQLite in the offline
 * regression fixtures. The application code was originally written for MySQL,
 * so every construct that is not portable is centralised here and selected by
 * driver name. Callers ask for a snippet; they never write DATE_ADD(),
 * CURDATE(), GET_LOCK(), INSERT IGNORE or ON DUPLICATE KEY UPDATE themselves.
 *
 * `tests/postgres_dialect_test.py` enforces that rule statically, so a MySQL
 * construct cannot creep back into a repository that has to run on Postgres.
 */
final class SqlDialect
{
    public const SQLITE = 'sqlite';
    public const MYSQL = 'mysql';
    public const PGSQL = 'pgsql';

    public static function driver(PDO $database): string
    {
        try {
            $driver = (string)$database->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable) {
            // Test doubles may not call PDO::__construct(); treat them the way
            // the legacy fixtures are written (MySQL dialect, SQLite separate).
            return self::MYSQL;
        }

        return in_array($driver, [self::SQLITE, self::MYSQL, self::PGSQL], true) ? $driver : self::MYSQL;
    }

    public static function isSqlite(PDO $database): bool
    {
        return self::driver($database) === self::SQLITE;
    }

    public static function isPostgres(PDO $database): bool
    {
        return self::driver($database) === self::PGSQL;
    }

    /** Portable "current time" expression. */
    public static function currentTimestamp(PDO $database): string
    {
        return self::isSqlite($database) ? "datetime('now')" : 'CURRENT_TIMESTAMP';
    }

    /** Retained for callers that treat the value as a UTC mirror of now(). */
    public static function utcTimestamp(PDO $database): string
    {
        return match (self::driver($database)) {
            self::SQLITE => "datetime('now')",
            self::PGSQL => "(CURRENT_TIMESTAMP AT TIME ZONE 'UTC')",
            default => 'UTC_TIMESTAMP()',
        };
    }

    /** Literal expression: current timestamp plus $seconds seconds. */
    public static function plusSeconds(PDO $database, int $seconds): string
    {
        $seconds = max(0, $seconds);

        return match (self::driver($database)) {
            self::SQLITE => "datetime('now', '+{$seconds} seconds')",
            self::PGSQL => "CURRENT_TIMESTAMP + INTERVAL '{$seconds} seconds'",
            default => "DATE_ADD(NOW(), INTERVAL {$seconds} SECOND)",
        };
    }

    /** Literal expression: current timestamp plus $days days. */
    public static function plusDays(PDO $database, int $days): string
    {
        $days = max(0, $days);

        return match (self::driver($database)) {
            self::SQLITE => "datetime('now', '+{$days} days')",
            self::PGSQL => "CURRENT_TIMESTAMP + INTERVAL '{$days} days'",
            default => "DATE_ADD(NOW(), INTERVAL {$days} DAY)",
        };
    }

    /** Literal expression: current timestamp minus $hours hours. */
    public static function minusHours(PDO $database, int $hours): string
    {
        $hours = max(0, $hours);

        return match (self::driver($database)) {
            self::SQLITE => "datetime('now', '-{$hours} hours')",
            self::PGSQL => "CURRENT_TIMESTAMP - INTERVAL '{$hours} hours'",
            default => "DATE_SUB(NOW(), INTERVAL {$hours} HOUR)",
        };
    }

    /** Literal expression: current timestamp minus $days days. */
    public static function minusDays(PDO $database, int $days): string
    {
        $days = max(0, $days);

        return match (self::driver($database)) {
            self::SQLITE => "datetime('now', '-{$days} days')",
            self::PGSQL => "CURRENT_TIMESTAMP - INTERVAL '{$days} days'",
            default => "DATE_SUB(NOW(), INTERVAL {$days} DAY)",
        };
    }

    /**
     * Expression for "current timestamp plus $seconds", where $seconds is a
     * bound parameter name such as ':decay'. Postgres needs the value cast to
     * an interval; SQLite concatenates it into a modifier string.
     */
    public static function plusSecondsParameter(PDO $database, string $parameter): string
    {
        return match (self::driver($database)) {
            self::SQLITE => "datetime('now', '+' || {$parameter} || ' seconds')",
            self::PGSQL => "(CURRENT_TIMESTAMP + ({$parameter} || ' seconds')::interval)",
            default => "DATE_ADD(NOW(), INTERVAL {$parameter} SECOND)",
        };
    }

    /** Whole seconds between now and $column, as an integer expression. */
    public static function secondsUntil(PDO $database, string $column): string
    {
        return match (self::driver($database)) {
            self::SQLITE => "CAST(strftime('%s', {$column}) AS INTEGER) - CAST(strftime('%s', 'now') AS INTEGER)",
            self::PGSQL => "CAST(EXTRACT(EPOCH FROM ({$column} - CURRENT_TIMESTAMP)) AS INTEGER)",
            default => "TIMESTAMPDIFF(SECOND, NOW(), {$column})",
        };
    }

    /** 1/0 expression: portable stand-in for a boolean column predicate. */
    public static function isActiveExpression(string $column): string
    {
        return "CASE WHEN {$column} THEN 1 ELSE 0 END";
    }

    /** Month number of a date column (calendar queries). */
    public static function monthExpression(PDO $database, string $column): string
    {
        return match (self::driver($database)) {
            self::SQLITE => "CAST(strftime('%m', {$column}) AS INTEGER)",
            self::PGSQL => "CAST(EXTRACT(MONTH FROM {$column}) AS INTEGER)",
            default => "MONTH({$column})",
        };
    }

    /** Year number of a date column (calendar queries). */
    public static function yearExpression(PDO $database, string $column): string
    {
        return match (self::driver($database)) {
            self::SQLITE => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            self::PGSQL => "CAST(EXTRACT(YEAR FROM {$column}) AS INTEGER)",
            default => "YEAR({$column})",
        };
    }

    /** Row lock clause. SQLite serialises writers itself and rejects the syntax. */
    public static function forUpdate(PDO $database): string
    {
        return self::isSqlite($database) ? '' : ' FOR UPDATE';
    }

    /**
     * Dialect-specific suffix for an insert that must ignore duplicate keys.
     * SQLite and MySQL keep the literal "INSERT IGNORE INTO" form because the
     * offline fixtures rewrite that exact text.
     */
    public static function insertIgnoringDuplicates(PDO $database, string $table, string $columns, string $values): string
    {
        $statement = "INSERT IGNORE INTO {$table} ({$columns}) VALUES ({$values})";

        return self::isPostgres($database)
            ? "INSERT INTO {$table} ({$columns}) VALUES ({$values}) ON CONFLICT DO NOTHING"
            : $statement;
    }

    /** Suffix that makes an INSERT a no-op when $key already exists. */
    public static function ignoreConflictSuffix(PDO $database, string $key): string
    {
        return match (self::driver($database)) {
            self::SQLITE => " ON CONFLICT({$key}) DO NOTHING",
            self::PGSQL => " ON CONFLICT ({$key}) DO NOTHING",
            default => " ON DUPLICATE KEY UPDATE {$key} = {$key}",
        };
    }

    /**
     * Clause that turns an INSERT into an upsert with the given assignments.
     * The assignments themselves must stay portable (CASE ... END, no IF()).
     */
    public static function upsertClause(PDO $database, string $key, string $assignments): string
    {
        return match (self::driver($database)) {
            self::SQLITE => " ON CONFLICT({$key}) DO UPDATE SET {$assignments}",
            self::PGSQL => " ON CONFLICT ({$key}) DO UPDATE SET {$assignments}",
            default => " ON DUPLICATE KEY UPDATE {$assignments}",
        };
    }

    /** Normalise a driver-returned boolean ("t"/"f" on Postgres, 1/0 elsewhere). */
    public static function isTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 't', 'true', 'y', 'yes', 'on'], true);
    }

    /** Current database/schema name, used to namespace the worker lock. */
    public static function databaseName(PDO $database): string
    {
        try {
            return match (self::driver($database)) {
                self::PGSQL => (string)$database->query('SELECT current_database()')->fetchColumn(),
                self::SQLITE => 'lsc',
                default => (string)$database->query('SELECT DATABASE()')->fetchColumn(),
            };
        } catch (\Throwable) {
            return 'lsc';
        }
    }

    /** Session time zone, for operator audits. */
    public static function timeZoneExpression(PDO $database): string
    {
        return match (self::driver($database)) {
            self::SQLITE => "'UTC'",
            self::PGSQL => "current_setting('TimeZone')",
            default => '@@session.time_zone',
        };
    }

    /**
     * Single-writer guard for the notification worker.
     *
     * @return array{acquire: array{0: string, 1: list<string|int>}, release: array{0: string, 1: list<string|int>}}
     */
    public static function workerLock(PDO $database, string $name): array
    {
        return match (self::driver($database)) {
            // SQLite has a single writer; the surrounding transaction is the lock.
            self::SQLITE => [
                'acquire' => ['SELECT 1', []],
                'release' => ['SELECT 1', []],
            ],
            self::PGSQL => (static function () use ($name): array {
                // Advisory locks take a signed 64-bit key. Derive one that is
                // stable for this deployment and safe to inline as a literal.
                $key = (int)(hexdec(substr(hash('sha256', $name), 0, 15)) % 4611686018427387904);

                return [
                    'acquire' => ['SELECT pg_try_advisory_lock(' . $key . ')', []],
                    'release' => ['SELECT pg_advisory_unlock(' . $key . ')', []],
                ];
            })(),
            default => [
                'acquire' => ['SELECT GET_LOCK(?, 0)', [$name]],
                'release' => ['SELECT RELEASE_LOCK(?)', [$name]],
            ],
        };
    }
}
