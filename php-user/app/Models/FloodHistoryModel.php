<?php

namespace App\Models;

use PDO;
use Database\Database;

class FloodHistoryModel
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAll($filters = [])
    {
        $sql = "
            SELECT
                rb.id,
                rb.idSungai,
                rs.lokasiSungai,
                rb.tinggiAir,
                rb.status,
                rb.waktuTerjadi
            FROM user_riwayatBanjir rb
            LEFT JOIN river_sungai rs
                ON rb.idSungai = rs.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND rb.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['idSungai'])) {
            $sql .= " AND rb.idSungai = :idSungai";
            $params['idSungai'] = $filters['idSungai'];
        }

        $sql .= " ORDER BY rb.waktuTerjadi DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $sql = "
            SELECT
                rb.id,
                rb.idSungai,
                rs.lokasiSungai,
                rb.tinggiAir,
                rb.status,
                rb.waktuTerjadi
            FROM user_riwayatBanjir rb
            LEFT JOIN river_sungai rs
                ON rb.idSungai = rs.id
            WHERE rb.id = :id
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("
            DELETE FROM user_riwayatBanjir
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->rowCount();
    }

    public function ping()
    {
        try {

            $this->conn->query("SELECT 1");

            return true;

        } catch (\PDOException $e) {

            return false;
        }
    }
}