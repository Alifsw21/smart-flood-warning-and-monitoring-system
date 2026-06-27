<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Validators\UserValidator;

class UserController extends BaseController
{
    private $model;
    private $validator;

    public function __construct(UserModel $model)
    {
        $this->model = $model;
        $this->validator = new UserValidator();
    }

    public function index($userRole)
    {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Hanya Admin Yang Dapat Melihat Daftar Pengguna.');
        }

        try {
            $data = $this->model->getAll();
            $this->sendResponse(200, 'success', 'Berhasil Mengambil Data Pengguna.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function show($id, $userRole, $userId)
    {
        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        if ($userRole !== 'admin' && (int) $userId !== (int) $id) {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Anda Hanya Dapat Melihat Profil Sendiri.');
        }

        try {
            $data = $this->model->getById($id);

            if ($data === null) {
                $this->sendResponse(404, 'error', "Pengguna Dengan ID $id Tidak Ditemukan.");
            }

            $this->sendResponse(200, 'success', 'Berhasil Mengambil Data Pengguna.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function store($userRole)
    {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Hanya Admin Yang Dapat Menambahkan Pengguna.');
        }

        $input = $this->getJsonInput();
        $errors = $this->validator->validateCreate($input, true);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Data Tidak Valid.', $errors);
        }

        try {
            $insertId = $this->model->create([
                'username' => trim($input['username']),
                'email' => $input['email'] ?? null,
                'password' => password_hash($input['password'], PASSWORD_BCRYPT),
                'role' => $input['role'] ?? 'user',
            ]);

            $data = $this->model->getById($insertId);
            $this->sendResponse(201, 'success', 'Pengguna Berhasil Ditambahkan.', $data);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062')) {
                $this->sendResponse(409, 'error', 'Username Atau Email Sudah Digunakan.');
            }

            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function update($id, $userRole, $userId)
    {
        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        $isAdmin = $userRole === 'admin';
        $isSelf = (int) $userId === (int) $id;

        if (!$isAdmin && !$isSelf) {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Anda Hanya Dapat Memperbarui Profil Sendiri.');
        }

        $input = $this->getJsonInput();
        $errors = $this->validator->validateUpdate($input, $isAdmin);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Data Tidak Valid.', $errors);
        }

        try {
            $existing = $this->model->getById($id);

            if ($existing === null) {
                $this->sendResponse(404, 'error', "Pengguna Dengan ID $id Tidak Ditemukan.");
            }

            $payload = [];

            if (isset($input['username'])) {
                $payload['username'] = trim($input['username']);
            }

            if (array_key_exists('email', $input)) {
                $payload['email'] = $input['email'];
            }

            if (!empty($input['password'])) {
                $payload['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
            }

            if ($isAdmin && isset($input['role'])) {
                $payload['role'] = $input['role'];
            }

            $this->model->update($id, $payload);
            $data = $this->model->getById($id);
            $this->sendResponse(200, 'success', 'Data Pengguna Berhasil Diperbarui.', $data);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062')) {
                $this->sendResponse(409, 'error', 'Username Atau Email Sudah Digunakan.');
            }

            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function destroy($id, $userRole, $userId)
    {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Hanya Admin Yang Dapat Menghapus Pengguna.');
        }

        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        if ((int) $userId === (int) $id) {
            $this->sendResponse(409, 'error', 'Admin Tidak Dapat Menghapus Akun Sendiri.');
        }

        try {
            $deletedRows = $this->model->delete($id);

            if ($deletedRows === 0) {
                $this->sendResponse(404, 'error', "Pengguna Dengan ID $id Tidak Ditemukan.");
            }

            $this->sendResponse(200, 'success', "Pengguna Dengan ID $id Berhasil Dihapus.");
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1451')) {
                $this->sendResponse(409, 'error', 'Pengguna Tidak Dapat Dihapus Karena Masih Memiliki Data Terkait.');
            }

            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }
}
