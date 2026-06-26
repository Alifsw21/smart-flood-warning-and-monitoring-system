<?php

namespace App\Models;

use PDO;

class SensorReading extends BaseModel {
    protected $table = 'river_sensorReading';

    public function getLatestReadings($limit = 50) {
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);   
    }

    public function getReadingByNode($idNode, $limit = 20) {
        $query = "SELECT * FROM {$this->table} WHERE idNode = :idNode ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':idNode', $idNode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}