<?php

namespace App\Models;

use PDO;

class LaporanModel extends BaseModel
{
    private $table = 'user_laporan';

    public function getAll(array $filters)
    {
        $query = "SELECT l.id, l.idPengguna, u.username, l.deskripsiLaporan, l.waktuDibuat
                  FROM {$this->table} l
                  LEFT JOIN user_user u ON l.idPengguna = u.id";
        $where = [];
        $params = [];

        if (isset($filters['idPengguna']) && $filters['idPengguna'] !== '') {
            $where[] = 'l.idPengguna = :idPengguna';
            $params[':idPengguna'] = [(int) $filters['idPengguna'], PDO::PARAM_INT];
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY l.waktuDibuat DESC, l.id DESC';

        $stmt = $this->getConnection()->prepare($query);

        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value[0], $value[1]);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $query = "SELECT l.id, l.idPengguna, u.username, l.deskripsiLaporan, l.waktuDibuat
                  FROM {$this->table} l
                  LEFT JOIN user_user u ON l.idPengguna = u.id
                  WHERE l.id = :id
                  LIMIT 1";
        $stmt = $this->getConnection()->prepare($query);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = $this->getConnection()->prepare(
            "INSERT INTO {$this->table} (idPengguna, deskripsiLaporan)
             VALUES (:idPengguna, :deskripsiLaporan)"
        );
        $stmt->bindValue(':idPengguna', (int) $data['idPengguna'], PDO::PARAM_INT);
        $stmt->bindValue(':deskripsiLaporan', $data['deskripsiLaporan'], PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->getConnection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $stmt = $this->getConnection()->prepare(
            "UPDATE {$this->table} SET deskripsiLaporan = :deskripsiLaporan WHERE id = :id"
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':deskripsiLaporan', $data['deskripsiLaporan'], PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    public function delete($id)
    {
        $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->rowCount();
    }
}
