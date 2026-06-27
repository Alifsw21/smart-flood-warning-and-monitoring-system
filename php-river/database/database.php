<?php

class Database {
    private $pdo;

    public function __construct() {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $db   = $_ENV['DB_DATABASE'] ?? 'kelompok2';
        $user = $_ENV['DB_USERNAME'] ?? 'river';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES      => false
            ];

            $this->pdo = new PDO($dsn, $user, $pass, $options);

        } catch (\PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());

            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'code' => 503,
                'data' => $isDebug ? ['detail' => $e->getMessage()] : null,
                'message' => 'Database tidak tersedia',
                'timestamp' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
                'service' => 'php-river',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
