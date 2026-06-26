<?php
namespace App\Controllers;
use App\Models\SensorNode;

class SensorController {
    private $model;

    public function __construct() {
        $this->model = new SensorModel();
    }

    public function index() {
        $data = $this->model->getLatestReadings();
        $this->sendResponse(200, "success", "Berhasil Mengambil Data Informasi Sensor", $data);
    }

    public function store($inputData, $userRole) {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, "error", "Akses Ditolak. Hanya Admin yang Dapat Menambahkan Node Sensor Baru.");
            return;
        }

        if (empty($inputData['idSungai']) || empty($inputData['idStation']) || empty($inputData['namaNode']) || empty($inputData['posisi']) || empty($inputData['elevasi'])) {
            $this->sendResponse(400, "error", "Input Tidak Valid. Pastikan Seluruh Field yang Diperlukan Telah Terisi (idSungai, idStation, namaNode, posisi, elevasi).");
            return;
        }

        try {
            $insertId = $this->model->createNode($inputData);
            $responseData = array_merge(["id" => $insertId], $inputData);
            $this->sendResponse(201, "success", "Node Sensor Baru Berhasil Ditambahkan.", $responseData);
        } catch (\PDOException $e) {
            $this->sendResponse(409, "error", "Terjadi Kesalahan Saat Menambahkan Node Sensor: " . $e->getMessage());
            return;
        }

        $insertId = $this->model->createNode($inputData);
        $responseData = array_merge(["id" => $insertId], $inputData);
        $this->sendResponse(201, "success", "Node Sensor Baru Berhasil Ditambahkan.", $responseData);
    }

    public function update($id, $inputData, $userRole) {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, "error", "Akses Ditolak. Hanya Admin yang Dapat Memperbarui Data Node Sensor.");
            return;
        }

        if (!$id) {
            $this->sendResponse(400, "error", "Diperlukan Parameter ID Sensor.");
            return;
        }

        try {
            $this->model->updateNode($id, $inputData);
            $responseData = array_merge(["id" => $id], $inputData);
            $this->sendResponse(200, "success", "Data Node Sensor Dengan ID $id Berhasil Diperbarui.", $responseData);
        
            } catch (\PDOException $e) {
            $this->sendResponse(409, "error", "Terjadi Kesalahan Saat Memperbarui Node Sensor: " . $e->getMessage());
            return;
        }
    }

    public function delete($id, $userRole) {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, "error", "Akses Ditolak. Hanya Admin yang Dapat Menghapus Data Node Sensor.");
            return;
        }   
        
        if (!$id) {
            $this->sendResponse(400, "error", "Diperlukan Parameter ID Sensor.");
            return;
        }

        try {
            $stmt = $this->model->deleteNode($id);
            if ($stmt->rowCount() === 0) {
                $this->sendResponse(404, "error", "Node Sensor Dengan ID $id Tidak Ditemukan.");
                return;
            }

            $this->sendResponse(200, "success", "Node Sensor Dengan ID $id Berhasil Dihapus.");
        }
        catch (\PDOException $e) {
            $this->sendResponse(409, "error", "Terjadi Kesalahan Saat Menghapus Node Sensor: " . $e->getMessage());
            return;
        }
    }

    private function sendResponse($code, $status, $message, $data = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            "status" => $status,
            "code" => (int)$code,
            "data" => $data,
            "message" => $message,
            "timestamp" => date('Y-m-d\TH:i:s.v\Z'),
            "service" => "php-river"
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}