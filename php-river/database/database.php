<?php

class Database {
    private $pdo;

    public function __construct() {
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $db   = $_ENV['DB_DATABASE'];
        $user = $_ENV['DB_USERNAME'];
        $pass = $_ENV['DB_PASSWORD'];

        try {
            $dsn = "mysql:host=$host;port:$port;dbname=$db;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES      => false
            ];

            $this->pdo = new PDO($dsn, $user, $pass, $options);

        } catch (\PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Terjadi kesalahan pada server",
                "data"    => $e->getMessage()
            ]);
            exit;
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}