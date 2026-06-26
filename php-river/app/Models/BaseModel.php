<?php

namespace App\Models;

use PDO;

class BaseModel {
    protected $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }
}