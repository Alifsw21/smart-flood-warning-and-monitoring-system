<?php

namespace App\Models;

use PDO;
use PDOException;

class SensorNode extends BaseModel {
    private $table = 'river_sensorNode';

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