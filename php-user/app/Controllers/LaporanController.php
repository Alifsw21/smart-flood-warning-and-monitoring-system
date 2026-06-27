<?php

namespace App\Controllers;

use App\Models\LaporanModel;
use App\Validators\LaporanValidator;

class LaporanController extends BaseController
{
    private $model;
    private $validator;

    public function __construct(LaporanModel $model)
    {
        $this->model = $model;
        $this->validator = new LaporanValidator();
    }

    public function index($userRole, $userId)
    {
        $filters = $_GET;
        $errors = $this->validator->validateFilters($filters);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Filter Tidak Valid.', $errors);
        }

        if ($userRole !== 'admin') {
            $filters['idPengguna'] = (string) $userId;
        }

        try {
            $data = $this->model->getAll($filters);

            if (empty($data)) {
                $this->sendResponse(200, 'success', 'Belum Ada Data Laporan.', []);
            }

            $this->sendResponse(200, 'success', 'Berhasil Mengambil Data Laporan.', $data);
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

        try {
            $data = $this->model->getById($id);

            if ($data === null) {
                $this->sendResponse(404, 'error', "Laporan Dengan ID $id Tidak Ditemukan.");
            }

            if ($userRole !== 'admin' && (int) $data['idPengguna'] !== (int) $userId) {
                $this->sendResponse(403, 'error', 'Akses Ditolak. Anda Hanya Dapat Melihat Laporan Sendiri.');
            }

            $this->sendResponse(200, 'success', 'Berhasil Mengambil Detail Laporan.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function store($userRole, $userId)
    {
        if ($userId <= 0) {
            $this->sendResponse(401, 'error', 'Pengguna Tidak Terautentikasi.');
        }

        $input = $this->getJsonInput();
        $errors = $this->validator->validateCreate($input);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Data Tidak Valid.', $errors);
        }

        try {
            $insertId = $this->model->create([
                'idPengguna' => $userId,
                'deskripsiLaporan' => trim($input['deskripsiLaporan']),
            ]);

            $data = $this->model->getById($insertId);

            $this->publishEvent('report.submitted', [
                'id' => $insertId,
                'idPengguna' => $userId,
                'deskripsiLaporan' => $data['deskripsiLaporan'],
                'waktuDibuat' => $data['waktuDibuat'],
                'submitted_by_role' => $userRole,
            ]);

            $this->sendResponse(201, 'success', 'Laporan Berhasil Dikirim.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function update($id, $userRole, $userId)
    {
        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        $input = $this->getJsonInput();
        $errors = $this->validator->validateUpdate($input);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Data Tidak Valid.', $errors);
        }

        try {
            $existing = $this->model->getById($id);

            if ($existing === null) {
                $this->sendResponse(404, 'error', "Laporan Dengan ID $id Tidak Ditemukan.");
            }

            if ($userRole !== 'admin' && (int) $existing['idPengguna'] !== (int) $userId) {
                $this->sendResponse(403, 'error', 'Akses Ditolak. Anda Hanya Dapat Memperbarui Laporan Sendiri.');
            }

            $this->model->update($id, [
                'deskripsiLaporan' => trim($input['deskripsiLaporan']),
            ]);

            $data = $this->model->getById($id);
            $this->sendResponse(200, 'success', 'Laporan Berhasil Diperbarui.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function destroy($id, $userRole, $userId)
    {
        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        try {
            $existing = $this->model->getById($id);

            if ($existing === null) {
                $this->sendResponse(404, 'error', "Laporan Dengan ID $id Tidak Ditemukan.");
            }

            if ($userRole !== 'admin' && (int) $existing['idPengguna'] !== (int) $userId) {
                $this->sendResponse(403, 'error', 'Akses Ditolak. Anda Hanya Dapat Menghapus Laporan Sendiri.');
            }

            $this->model->delete($id);
            $this->sendResponse(200, 'success', "Laporan Dengan ID $id Berhasil Dihapus.");
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function updateStatus($id, $userRole)
    {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Hanya Admin Yang Dapat Memperbarui Status Laporan.');
        }

        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        $input = $this->getJsonInput();
        $errors = $this->validator->validateStatusUpdate($input);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Data Tidak Valid.', $errors);
        }

        try {
            $existing = $this->model->getById($id);

            if ($existing === null) {
                $this->sendResponse(404, 'error', "Laporan Dengan ID $id Tidak Ditemukan.");
            }

            $this->model->updateStatus($id, $input['status']);
            $data = $this->model->getById($id);
            $this->sendResponse(200, 'success', 'Status Laporan Berhasil Diperbarui.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }
}
