<?php

namespace Database;

use PDO;
use PDOException;

class Database
{
    private $connection;

    public function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? 'database';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $db   = $_ENV['DB_DATABASE'] ?? 'kelompok2';
        $user = $_ENV['DB_USERNAME'] ?? 'user';
        $pass = $_ENV['DB_PASSWORD'] ?? 'UserSecret';

        try {

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

            $this->connection = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

        } catch (PDOException $e) {

            die("Database Connection Failed");
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }
}