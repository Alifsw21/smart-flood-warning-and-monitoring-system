<?php

namespace App\Models;

use PDO;

class FloodHistoryModel extends BaseModel
{
    private $table = 'user_riwayatBanjir';

    public function getAll(array $filters)
    {
        $query = "SELECT rb.id, rb.idSungai, rs.lokasiSungai, rb.tinggiAir, rb.status, rb.waktuTerjadi
                  FROM {$this->table} rb
                  LEFT JOIN river_sungai rs ON rb.idSungai = rs.id";
        $where = [];
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'rb.status = :status';
            $params[':status'] = [$filters['status'], PDO::PARAM_STR];
        }

        if (isset($filters['idSungai']) && $filters['idSungai'] !== '') {
            $where[] = 'rb.idSungai = :idSungai';
            $params[':idSungai'] = [(int) $filters['idSungai'], PDO::PARAM_INT];
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY rb.waktuTerjadi DESC, rb.id DESC';

        $stmt = $this->getConnection()->prepare($query);

        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value[0], $value[1]);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $query = "SELECT rb.id, rb.idSungai, rs.lokasiSungai, rb.tinggiAir, rb.status, rb.waktuTerjadi
                  FROM {$this->table} rb
                  LEFT JOIN river_sungai rs ON rb.idSungai = rs.id
                  WHERE rb.id = :id
                  LIMIT 1";
        $stmt = $this->getConnection()->prepare($query);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function delete($id)
    {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getConnection()->prepare($query);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->rowCount();
    }
}
