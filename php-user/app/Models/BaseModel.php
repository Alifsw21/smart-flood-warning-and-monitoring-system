<?php

namespace App\Models;

use PDO;
use PDOException;

abstract class BaseModel
{
    protected $db;
    protected $connectionError;

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return;
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'kelompok2');
        $user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'user');
        $password = getenv('DB_PASSWORD') ?: 'UserSecret';
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        try {
            $this->db = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $this->connectionError = $e;
        }
    }

    public function ping()
    {
        if ($this->connectionError instanceof PDOException) {
            return false;
        }

        try {
            $this->getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    protected function getConnection()
    {
        if ($this->connectionError instanceof PDOException) {
            throw $this->connectionError;
        }

        return $this->db;
    }
}
