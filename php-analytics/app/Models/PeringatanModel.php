<?php

namespace App\Models;

use PDO;
use PDOException;

class PeringatanModel {
    private $db;
    private $connectionError;
    private $table = 'analytics_peringatan';

    public function __construct(?\PDO $db = null) {
        if ($db !== null) {
            $this->db = $db;
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return;
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'kelompok2';
        $user = getenv('DB_USER') ?: 'analytics';
        $password = getenv('DB_PASSWORD') ?: 'AnalyticSecret';
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        try {
            $this->db = new PDO($dsn, $user, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->connectionError = $e;
        }
    }

    public function getAll(array $filters) {
        $query = "SELECT p.id, p.idSungai, s.lokasiSungai, p.tipePeringatan, p.nilaiProbabilitas, p.recorded_at
                FROM {$this->table} p
                LEFT JOIN river_sungai s ON p.idSungai = s.id";
        $where = [];
        $params = [];

        if (isset($filters['idSungai']) && $filters['idSungai'] !== '') {
            $where[] = "p.idSungai = :idSungai";
            $params[':idSungai'] = [(int) $filters['idSungai'], PDO::PARAM_INT];
        }

        if (isset($filters['tipePeringatan']) && $filters['tipePeringatan'] !== '') {
            $where[] = "p.tipePeringatan = :tipePeringatan";
            $params[':tipePeringatan'] = [$filters['tipePeringatan'], PDO::PARAM_STR];
        }

        if (isset($filters['from']) && $filters['from'] !== '') {
            $where[] = "p.recorded_at >= :from";
            $params[':from'] = [$filters['from'], PDO::PARAM_STR];
        }

        if (isset($filters['to']) && $filters['to'] !== '') {
            $where[] = "p.recorded_at <= :to";
            $params[':to'] = [$filters['to'], PDO::PARAM_STR];
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " ORDER BY p.recorded_at DESC, p.id DESC";

        $stmt = $this->getConnection()->prepare($query);

        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value[0], $value[1]);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT p.id, p.idSungai, s.lokasiSungai, p.tipePeringatan, p.nilaiProbabilitas, p.recorded_at
                FROM {$this->table} p
                LEFT JOIN river_sungai s ON p.idSungai = s.id
                WHERE p.id = :id
                LIMIT 1";
        $stmt = $this->getConnection()->prepare($query);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->getConnection()->prepare($query);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    public function createFromAlert(array $payload): array {
        $idSungai = (int) ($payload['idSungai'] ?? 0);
        $tipe = strtolower((string) ($payload['tipePeringatan'] ?? 'normal'));
        if (!in_array($tipe, ['normal', 'waspada', 'bencana'], true)) {
            $tipe = 'normal';
        }
        $probabilitas = (float) ($payload['nilaiProbabilitas'] ?? $payload['probabilitas'] ?? 0.0);
        $tinggiAir = (float) ($payload['tinggiAir'] ?? 0.0);

        $db = $this->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO {$this->table} (idSungai, tipePeringatan, nilaiProbabilitas)
             VALUES (:idSungai, :tipePeringatan, :nilaiProbabilitas)"
        );
        $stmt->bindValue(':idSungai', $idSungai, PDO::PARAM_INT);
        $stmt->bindValue(':tipePeringatan', $tipe, PDO::PARAM_STR);
        $stmt->bindValue(':nilaiProbabilitas', $probabilitas);
        $stmt->execute();

        if (in_array($tipe, ['waspada', 'bencana'], true)) {
            if ($tinggiAir >= 3.5) {
                $statusRiwayat = 'tinggi';
            } elseif ($tinggiAir >= 2.0) {
                $statusRiwayat = 'sedang';
            } else {
                $statusRiwayat = 'ringan';
            }

            $history = $db->prepare(
                "INSERT INTO user_riwayatBanjir (idSungai, tinggiAir, status)
                 VALUES (:idSungai, :tinggiAir, :status)"
            );
            $history->bindValue(':idSungai', $idSungai, PDO::PARAM_INT);
            $history->bindValue(':tinggiAir', $tinggiAir);
            $history->bindValue(':status', $statusRiwayat, PDO::PARAM_STR);
            $history->execute();
        }

        return [
            'id' => (int) $db->lastInsertId(),
            'idSungai' => $idSungai,
            'tipePeringatan' => $tipe,
            'nilaiProbabilitas' => $probabilitas,
        ];
    }

    public function ping() {
        if ($this->connectionError instanceof PDOException) {
            return false;
        }

        try {
            $this->getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function getConnection() {
        if ($this->connectionError instanceof PDOException) {
            throw $this->connectionError;
        }

        return $this->db;
    }
}
