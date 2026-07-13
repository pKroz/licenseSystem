<?php
define('DB_HOST', getenv('DATABASE_HOST') ?: 'localhost');
define('DB_NAME', getenv('DATABASE_NAME') ?: 'licenseflow_db');
define('DB_USER', getenv('DATABASE_USER') ?: 'root');
define('DB_PASS', getenv('DATABASE_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed']));
            }
        }
        return self::$instance;
    }
}