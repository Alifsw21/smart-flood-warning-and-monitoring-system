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
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}