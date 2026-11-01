<?php

declare(strict_types=1);

namespace LSC\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = getenv('MYSQLHOST');
        $port = getenv('MYSQLPORT');
        $database = getenv('MYSQLDATABASE');
        $username = getenv('MYSQLUSER');
        $password = getenv('MYSQLPASSWORD');

        if (!$host || !$port || !$database || !$username || !$password) {
            self::unavailable('required environment variables are missing');
        }

        try {
            self::$connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $exception) {
            self::unavailable($exception->getMessage());
        }

        return self::$connection;
    }

    private static function unavailable(string $reason): never
    {
        error_log('Database connection failed: ' . $reason);
        if (PHP_SAPI === 'cli') {
            // Operators and supervisors read the exit code: a zero here made
            // bin/prepare-deployment.php and the queue worker report success
            // while nothing had touched the database.
            fwrite(STDERR, "LSC database unavailable; check the MYSQL* environment variables.\n");
            exit(1);
        }
        http_response_code(503);
        exit('LSC is temporarily unavailable. Please try again later.');
    }
}
