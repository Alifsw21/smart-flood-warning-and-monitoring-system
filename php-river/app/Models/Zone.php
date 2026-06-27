<?php

namespace App\Models;

use PDO;

class Zone extends BaseModel {
    protected $table = 'river_zones';

    public function all() {
        $query = "SELECT * FROM {$this->table}";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table} (nama_kota) VALUES (:nama_kota)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':nama_kota' => $data['nama_kota']
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
        $query = "UPDATE {$this->table} SET nama_kota = :nama_kota WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id' => $id,
            ':nama_kota' => $data['nama_kota']
        ]);
    }

    public function delete($id) {
        $check = $this->db->prepare('SELECT COUNT(*) FROM river_sungai WHERE zoneId = :id');
        $check->execute([':id' => $id]);
        if ((int)$check->fetchColumn() > 0) {
            throw new \PDOException('Zona masih memiliki data sungai terkait.');
        }

        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}