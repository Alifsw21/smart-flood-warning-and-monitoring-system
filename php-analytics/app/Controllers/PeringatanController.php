<?php

namespace App\Controllers;

use App\Models\PeringatanModel;
use App\Validators\PeringatanValidator;

class PeringatanController {
    private $model;
    private $validator;

    public function __construct(PeringatanModel $model) {
        $this->model = $model;
        $this->validator = new PeringatanValidator();
    }

    public function index() {
        $errors = $this->validator->validateFilters($_GET);

        if (!empty($errors)) {
            $this->sendResponse(400, "error", "Filter Tidak Valid.", $errors);
        }

        try {
            $data = $this->model->getAll($_GET);
            $this->sendResponse(200, "success", "Berhasil Mengambil Data Peringatan.", $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, "error", "Terjadi Kesalahan Pada Database.");
        }
    }

    public function activeAlerts() {
        $errors = $this->validator->validateFilters($_GET);

        if (!empty($errors)) {
            $this->sendResponse(400, "error", "Filter Tidak Valid.", $errors);
        }

        try {
            $data = $this->model->getActive($_GET);
            $this->sendResponse(200, "success", "Berhasil Mengambil Alert Aktif.", $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, "error", "Terjadi Kesalahan Pada Database.");
        }
    }

    public function show($id) {
        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, "error", $error);
        }

        try {
            $data = $this->model->getById($id);

            if ($data === null) {
                $this->sendResponse(404, "error", "Peringatan Dengan ID $id Tidak Ditemukan.");
            }

            $this->sendResponse(200, "success", "Berhasil Mengambil Data Peringatan.", $data);
        } catch (\PDOException $e) {
            $this->sendResponse(500, "error", "Terjadi Kesalahan Pada Database.");
        }
    }

    public function delete($id, $userRole) {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, "error", "Akses Ditolak. Hanya Admin yang Dapat Menghapus Data Peringatan.");
        }

        $error = $this->validator->validateId($id);

        if ($error !== null) {
            $this->sendResponse(400, "error", $error);
        }

        try {
            $deletedRows = $this->model->delete($id);

            if ($deletedRows === 0) {
                $this->sendResponse(404, "error", "Peringatan Dengan ID $id Tidak Ditemukan.");
            }

            $this->sendResponse(200, "success", "Peringatan Dengan ID $id Berhasil Dihapus.");
        } catch (\PDOException $e) {
            $this->sendResponse(500, "error", "Terjadi Kesalahan Pada Database.");
        }
    }

    public function health() {
        $isConnected = $this->model->ping();

        if ($isConnected) {
            $this->sendResponse(200, "success", "Service Berjalan.", [
                "status" => "ok",
                "db" => "connected"
            ]);
        }

        $this->sendResponse(503, "error", "Database Tidak Terhubung.", [
            "status" => "error",
            "db" => "disconnected"
        ]);
    }

    private function sendResponse($code, $status, $message, $data = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            "status" => $status,
            "code" => (int) $code,
            "data" => $data,
            "message" => $message,
            "timestamp" => date('Y-m-d\TH:i:s.v\Z'),
            "service" => "php-analytics"
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
