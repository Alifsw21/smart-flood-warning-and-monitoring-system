<?php

namespace App\Models;

use PDO;

class Sungai extends BaseModel {
    protected $table = 'river_sungai';

    public function all() {
        $query = "SELECT * FROM {$this->table}";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table} (zoneId, lokasiSungai) VALUES (:zoneId, :lokasiSungai)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':zoneId' => $data['zoneId'],
            ':lokasiSungai' => $data['lokasiSungai']
        ]);

        return $this->db->lastInsertId();
    }


    public function find($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $query = "UPDATE {$this->table} SET zoneId = :zoneId, lokasiSungai = :lokasiSungai WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id' => $id,
            ':zoneId' => $data['zoneId'],
            ':lokasiSungai' => $data['lokasiSungai']
        ]);
    }

    public function delete($id) {
        try {
            $this->db->beginTransaction();

            $deleteReadings = $this->db->prepare(
                'DELETE rr FROM river_sensorReading rr
                 INNER JOIN river_sensorNode n ON rr.idNode = n.id
                 WHERE n.idSungai = :id'
            );
            $deleteReadings->execute([':id' => $id]);

            $deleteNodes = $this->db->prepare('DELETE FROM river_sensorNode WHERE idSungai = :id');
            $deleteNodes->execute([':id' => $id]);

            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}