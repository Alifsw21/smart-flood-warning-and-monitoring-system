<?php

namespace App\Controllers;

use App\Models\Sungai;

class SungaiController extends BaseController {
    private $sungaiModel;

    public function __construct($db) {
        $this->sungaiModel = new Sungai($db);
    }

    public function index() {
        try {
            $data = $this->sungaiModel->all();

            if (empty($data)) {
                $this->sendResponse(200, true, "Belum ada data sungai", []);
            }

            $this->sendResponse(200, true, "Data sungai berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store() {
        $input = $this->getJsonInput();

        if (empty($input['zoneId']) || empty($input['lokasiSungai'])) {
            $this->sendResponse(400, false, "zoneId dan lokasiSungai harus diisi");
        }

        try {
            $id = $this->sungaiModel->create($input);

            $this->publishEvent('sungai_events', [
                'event' => 'sungai_created',
                'id' => $id,
                'zoneId' => $input['zoneId'],
                'lokasiSungai' => $input['lokasiSungai'],
                'timestamp' => date('c')
            ]);

            $this->sendResponse(201, true, "Data sungai berhasil ditambahkan", ["id" => $id]);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function show($id) {
        try {
            $data = $this->sungaiModel->find($id);

            if (empty($data)) {
                $this->sendResponse(404, false, "Data sungai dengan id {$id} tidak ditemukan");
            }

            $this->sendResponse(200, true, "Data sungai dengan id {$id} berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function update($id) {
        try {
            $data = $this->sungaiModel->find($id);

            if (empty($data)) {
                $this->sendResponse(404, false, "Data sungai dengan id {$id} tidak ditemukan");
            }

            $input = $this->getJsonInput();
            $this->sungaiModel->update($id, $input);
            $updatedData = $this->sungaiModel->find($id);

            $this->publishEvent('sungai_events', [
                'event' => 'sungai_updated',
                'data_lama' => $data,
                'data_baru' => $updatedData,
                'timestamp' => date('c')
            ]);

            $this->sendResponse(200, true, "Data sungai dengan id {$id} berhasil diperbarui", $updatedData);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function destroy($id) {
        try {
            $data = $this->sungaiModel->find($id);

            if (empty($data)) {
                $this->sendResponse(404, false, "Data sungai dengan id {$id} tidak ditemukan");
            }

            $this->sungaiModel->delete($id);
            
            $this->publishEvent('sungai_events', [
                'event' => 'sungai_deleted',
                'id' => $id,
                'data' => $data,
                'timestamp' => date('c')
            ]);
            $this->sendResponse(200, true, "Data sungai dengan id {$id} berhasil dihapus", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }
}