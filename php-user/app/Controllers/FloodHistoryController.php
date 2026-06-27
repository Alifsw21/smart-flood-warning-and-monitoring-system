<?php

namespace App\Controllers;

use App\Models\FloodHistoryModel;
use App\Validators\FloodHistoryValidator;

class FloodHistoryController extends BaseController
{
    private $model;
    private $validator;

    public function __construct(FloodHistoryModel $model)
    {
        $this->model = $model;
        $this->validator = new FloodHistoryValidator();
    }

    public function index()
    {
        $errors = $this->validator->validateFilters($_GET);

        if (!empty($errors)) {
            $this->sendResponse(400, 'error', 'Filter Tidak Valid.', $errors);
        }

        try {
            $data = $this->model->getAll($_GET);

            if (empty($data)) {
                $this->sendResponse(200, 'success', 'Belum Ada Riwayat Banjir.', []);
            }

            $this->sendResponse(200, 'success', 'Berhasil Mengambil Data Riwayat Banjir.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function show($id)
    {
        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        try {
            $data = $this->model->getById($id);

            if ($data === null) {
                $this->sendResponse(404, 'error', "Riwayat Banjir Dengan ID $id Tidak Ditemukan.");
            }

            $this->sendResponse(200, 'success', 'Berhasil Mengambil Detail Riwayat Banjir.', $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function delete($id, $userRole)
    {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, 'error', 'Akses Ditolak. Hanya Admin Yang Dapat Menghapus Riwayat Banjir.');
        }

        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, 'error', $error);
        }

        try {
            $deletedRows = $this->model->delete($id);

            if ($deletedRows === 0) {
                $this->sendResponse(404, 'error', "Riwayat Banjir Dengan ID $id Tidak Ditemukan.");
            }

            $this->sendResponse(200, 'success', "Riwayat Banjir Dengan ID $id Berhasil Dihapus.");
        } catch (\PDOException $e) {
            $this->sendResponse(500, 'error', 'Terjadi Kesalahan Pada Database.');
        }
    }

    public function health()
    {
        $isConnected = $this->model->ping();

        if ($isConnected) {
            $this->sendResponse(200, 'success', 'Service Berjalan.', [
                'status' => 'ok',
                'db' => 'connected',
            ]);
        }

        $this->sendResponse(503, 'error', 'Database Tidak Terhubung.', [
            'status' => 'error',
            'db' => 'disconnected',
        ]);
    }
}
