<?php

namespace App\Models;

use PDO;

class SensorReading extends BaseModel {
    protected $table = 'river_sensorReading';

    public function getLatestReadings($limit = 50) {
        $query = "SELECT * FROM {$this->table} ORDER BY recorded_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReadingByNode($idNode, $limit = 20) {
        $query = "SELECT * FROM {$this->table} WHERE idNode = :idNode ORDER BY recorded_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':idNode', $idNode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createReading(array $data): array {
        $query = "INSERT INTO {$this->table}
            (idNode, tinggiAir, kelembapanTanah, curahHujan, suhuRataRata, kelembapanUdara, kecepatanAngin, arahAngin)
            VALUES
            (:idNode, :tinggiAir, :kelembapanTanah, :curahHujan, :suhuRataRata, :kelembapanUdara, :kecepatanAngin, :arahAngin)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':idNode' => $data['idNode'],
            ':tinggiAir' => $data['tinggiAir'],
            ':kelembapanTanah' => $data['kelembapanTanah'],
            ':curahHujan' => $data['curahHujan'],
            ':suhuRataRata' => $data['suhuRataRata'],
            ':kelembapanUdara' => $data['kelembapanUdara'],
            ':kecepatanAngin' => $data['kecepatanAngin'],
            ':arahAngin' => $data['arahAngin'] ?? 0,
        ]);

        $id = $this->db->lastInsertId();
        $fetch = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $fetch->execute([':id' => $id]);

        return $fetch->fetch(PDO::FETCH_ASSOC);
    }
}
