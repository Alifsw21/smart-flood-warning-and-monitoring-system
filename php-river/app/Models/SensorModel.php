<?php
namespace App\Models;
use PDO;
use PDOException;

class SensorModel {
    private $db;

    public function __construct() {
        $host = 'localhost';
        $dbname = 'kelompok2';
        $username = 'root';
        $password = 'RootSecret';

        try {
            $this->db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            http_response_Code(500);
            echo json_encode([
                "status" => "error",
                "code" => 500,
                "message" => "Database connection failed: " . $e->getMessage(),
                "timestamp" => date('c'),
                "service" => "php-river"
            ]);
            exit;
        }
    }

    public function getLatestReadings() {
        $query = "SELECT n.id, n.idStation, n.namaNode, n.posisi, n.elevasi, s.lokasiSungai
                FROM river_sensorNode n
                JOIN river_sungai s ON n.idSungai = s.id
                ORDER BY n.id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createNode($data) {
        try {
            $query = "INSERT INTO river_sensorNode (idSungai, idStation, namaNode, posisi, elevasi)
                VALUES (:idSungai, :idStation, :namaNode, :posisi, :elevasi)";

            $stmt = $this->db->prepare($query);

            $stmt->execute([
                ':idSungai' => $data['idSungai'],
                ':idStation' => $data['idStation'],
                ':namaNode' => $data['namaNode'],
                ':posisi' => $data['posisi'],
                ':elevasi' => $data['elevasi']
            ]);

            return $this->db->lastInsertId();

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                if (str_contains($e->getMessage(), '1062')) {
                    throw new PDOException("Data Gagal Disimpan. ID Station '" . $data['idStation'] . "' Sudah Terdaftar Dalam Sistem.");
                }
            }
            throw $e;
        }
    }

    public function updateNode($id, $data) {
        try {
            $query = "UPDATE river_sensorNode
                    SET idSungai = :idSungai, idStation = :idStation, namaNode = :namaNode, posisi = :posisi, elevasi = :elevasi
                    WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                ':id'   => $id,
                ':idSungai' => $data['idSungai'],
                ':idStation' => $data['idStation'],
                ':namaNode' => $data['namaNode'],
                ':posisi' => $data['posisi'],
                ':elevasi' => $data['elevasi']
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                if (str_contains($e->getMessage(), '1062')) {
                    throw new PDOException("Data Gagal Diperbarui. ID Station '" . $data['idStation'] . "' Sudah Terdaftar Dalam Sistem.");
                }
            }
            throw $e;
        }
    }

    public function deleteNode($id) {
        $query = "DELETE FROM river_sensorNode WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);

        return $stmt;
    }
}