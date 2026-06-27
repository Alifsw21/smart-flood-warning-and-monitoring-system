<?php

namespace App\Models;

use PDO;

class NotificationModel extends BaseModel
{
    private $table = 'user_notifications';

    public function getByUserId(int $userId, array $filters = []): array
    {
        $query = "SELECT id, idPengguna, title, body, is_read, created_at
                  FROM {$this->table}
                  WHERE idPengguna = :idPengguna";
        $params = [':idPengguna' => [$userId, PDO::PARAM_INT]];

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query .= ' AND is_read = :is_read';
            $params[':is_read'] = [(int) $filters['is_read'], PDO::PARAM_INT];
        }

        $query .= ' ORDER BY created_at DESC, id DESC';

        $stmt = $this->getConnection()->prepare($query);

        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value[0], $value[1]);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->getConnection()->prepare(
            "INSERT INTO {$this->table} (idPengguna, title, body, is_read)
             VALUES (:idPengguna, :title, :body, :is_read)"
        );
        $stmt->bindValue(':idPengguna', (int) $data['idPengguna'], PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':body', $data['body'], PDO::PARAM_STR);
        $stmt->bindValue(':is_read', (int) ($data['is_read'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->getConnection()->lastInsertId();
    }

    public function createFromReport(array $payload): array
    {
        $reportId = (int) ($payload['id'] ?? 0);
        $userId = (int) ($payload['idPengguna'] ?? 0);
        $description = (string) ($payload['deskripsiLaporan'] ?? '');

        $insertId = $this->create([
            'idPengguna' => $userId,
            'title' => "Laporan #{$reportId} diterima",
            'body' => mb_substr($description, 0, 500),
            'is_read' => 0,
        ]);

        return $this->getById($insertId);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT id, idPengguna, title, body, is_read, created_at
             FROM {$this->table}
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function ping(): bool
    {
        $stmt = $this->getConnection()->query('SELECT 1');

        return $stmt !== false;
    }
}
