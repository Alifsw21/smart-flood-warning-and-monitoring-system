<?php

namespace App\Models;

use PDO;

class UserModel extends BaseModel
{
    private $table = 'user_user';

    public function getAll()
    {
        $stmt = $this->getConnection()->query(
            "SELECT id, username, email, role, waktuDibuat FROM {$this->table} ORDER BY id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->getConnection()->prepare(
            "SELECT id, username, email, role, waktuDibuat FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data)
    {
        $stmt = $this->getConnection()->prepare(
            "INSERT INTO {$this->table} (username, email, password, role)
             VALUES (:username, :email, :password, :role)"
        );
        $stmt->bindValue(':username', $data['username'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':password', $data['password'], PDO::PARAM_STR);
        $stmt->bindValue(':role', $data['role'], PDO::PARAM_STR);
        $stmt->execute();

        return (int) $this->getConnection()->lastInsertId();
    }

    public function update($id, array $data)
    {
        $fields = [];
        $params = [':id' => (int) $id];

        if (array_key_exists('username', $data)) {
            $fields[] = 'username = :username';
            $params[':username'] = $data['username'];
        }

        if (array_key_exists('email', $data)) {
            $fields[] = 'email = :email';
            $params[':email'] = $data['email'];
        }

        if (array_key_exists('password', $data)) {
            $fields[] = 'password = :password';
            $params[':password'] = $data['password'];
        }

        if (array_key_exists('role', $data)) {
            $fields[] = 'role = :role';
            $params[':role'] = $data['role'];
        }

        if (empty($fields)) {
            return 0;
        }

        $query = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->getConnection()->prepare($query);

        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

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
