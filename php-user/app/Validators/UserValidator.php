<?php

namespace App\Validators;

class UserValidator
{
    private $allowedRoles = ['admin', 'user'];

    public function validateId($id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return 'ID Harus Berupa Bilangan Bulat Positif.';
        }

        return null;
    }

    public function validateCreate(array $data, $isAdmin)
    {
        $errors = [];

        if (empty($data['username'])) {
            $errors['username'] = 'Username Wajib Diisi.';
        } elseif (strlen($data['username']) > 100) {
            $errors['username'] = 'Username Maksimal 100 Karakter.';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password Wajib Diisi.';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = 'Password Minimal 6 Karakter.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format Email Tidak Valid.';
        }

        if ($isAdmin) {
            if (!empty($data['role']) && !in_array($data['role'], $this->allowedRoles, true)) {
                $errors['role'] = 'Role Hanya Boleh admin atau user.';
            }
        }

        return $errors;
    }

    public function validateUpdate(array $data, $isAdmin)
    {
        $errors = [];

        if (isset($data['username']) && $data['username'] === '') {
            $errors['username'] = 'Username Tidak Boleh Kosong.';
        }

        if (isset($data['password']) && $data['password'] !== '' && strlen($data['password']) < 6) {
            $errors['password'] = 'Password Minimal 6 Karakter.';
        }

        if (isset($data['email']) && $data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format Email Tidak Valid.';
        }

        if ($isAdmin && isset($data['role']) && !in_array($data['role'], $this->allowedRoles, true)) {
            $errors['role'] = 'Role Hanya Boleh admin atau user.';
        }

        if (!$isAdmin && isset($data['role'])) {
            $errors['role'] = 'Hanya Admin Yang Dapat Mengubah Role.';
        }

        return $errors;
    }
}
