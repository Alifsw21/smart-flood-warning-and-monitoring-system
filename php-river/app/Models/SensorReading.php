<?php

namespace App\Models;

use PDO;

class SensorReading extends BaseModel {
    protected $table = 'river_sensorReading';

    public function getLatestReadings($limit = 50) {
        $query = "SELECT r.*, n.namaNode, n.idStation, s.lokasiSungai, s.zoneId, z.nama_kota
                  FROM {$this->table} r
                  JOIN river_sensorNode n ON r.idNode = n.id
                  JOIN river_sungai s ON n.idSungai = s.id
                  JOIN river_zones z ON s.zoneId = z.id
                  ORDER BY r.recorded_at DESC
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReadingByNode($idNode, $limit = 20) {
        $query = "SELECT r.*, n.namaNode, n.idStation, s.lokasiSungai, s.zoneId, z.nama_kota
                  FROM {$this->table} r
                  JOIN river_sensorNode n ON r.idNode = n.id
                  JOIN river_sungai s ON n.idSungai = s.id
                  JOIN river_zones z ON s.zoneId = z.id
                  WHERE r.idNode = :idNode
                  ORDER BY r.recorded_at DESC
                  LIMIT :limit";
        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':idNode', $idNode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCurrentByZone(?int $zoneId = null) {
        $query = "SELECT r.*, n.namaNode, n.idStation, n.posisi,
                         s.id AS idSungai, s.lokasiSungai, s.zoneId, z.nama_kota
                  FROM {$this->table} r
                  INNER JOIN (
                      SELECT idNode, MAX(recorded_at) AS max_recorded
                      FROM {$this->table}
                      GROUP BY idNode
                  ) latest ON r.idNode = latest.idNode AND r.recorded_at = latest.max_recorded
                  JOIN river_sensorNode n ON r.idNode = n.id
                  JOIN river_sungai s ON n.idSungai = s.id
                  JOIN river_zones z ON s.zoneId = z.id";

        if ($zoneId !== null) {
            $query .= " WHERE s.zoneId = :zoneId";
        }

        $query .= " ORDER BY z.id ASC, n.id ASC";

        $stmt = $this->db->prepare($query);
        if ($zoneId !== null) {
            $stmt->bindValue(':zoneId', $zoneId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistory(array $filters): array {
        $conditions = [];
        $params = [];

        if (!empty($filters['zoneId'])) {
            $conditions[] = 's.zoneId = :zoneId';
            $params[':zoneId'] = (int)$filters['zoneId'];
        }

        if (!empty($filters['idNode'])) {
            $conditions[] = 'r.idNode = :idNode';
            $params[':idNode'] = (int)$filters['idNode'];
        }

        if (!empty($filters['from'])) {
            $conditions[] = 'r.recorded_at >= :from';
            $params[':from'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $conditions[] = 'r.recorded_at <= :to';
            $params[':to'] = $filters['to'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $limit = min(max((int)($filters['limit'] ?? 100), 1), 500);

        $query = "SELECT r.*, n.namaNode, n.idStation, s.lokasiSungai, s.zoneId, z.nama_kota
                  FROM {$this->table} r
                  JOIN river_sensorNode n ON r.idNode = n.id
                  JOIN river_sungai s ON n.idSungai = s.id
                  JOIN river_zones z ON s.zoneId = z.id
                  $where
                  ORDER BY r.recorded_at DESC
                  LIMIT :limit";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
