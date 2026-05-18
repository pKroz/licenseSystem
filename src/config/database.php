<?php
// config/database.php
// Configuración de conexión a MySQL

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_licencias');
define('DB_USER', 'root');       // Cambia por tu usuario
define('DB_PASS', '');           // Cambia por tu contraseña
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
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
                die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
    }
}
