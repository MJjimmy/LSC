<?php
require_once __DIR__ . '/bootstrap.php';

$host     = getenv('MYSQLHOST');
$port     = getenv('MYSQLPORT');
$dbname   = getenv('MYSQLDATABASE');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');

if (!$host || !$port || !$dbname || !$username || !$password) {
    error_log('Database connection failed: required environment variables are missing.');
    http_response_code(503);
    exit('StudySync is temporarily unavailable. Please try again later.');
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('StudySync is temporarily unavailable. Please try again later.');
}
