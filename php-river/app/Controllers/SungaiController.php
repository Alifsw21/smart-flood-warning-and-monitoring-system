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
            $this->sendResponse(200, true, "Data sungai berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store() {
        $input = $this->getJsonInput();

        try {
            $validated = RiverValidator::validateSungai($input);
            $id = $this->sungaiModel->create($validated);

            $this->publishEvent('sungai_events', [
                'event' => 'sungai_created',
                'id' => $id,
                'zoneId' => $validated['zoneId'],
                'lokasiSungai' => $validated['lokasiSungai'],
                'timestamp' => date('c')
            ]);

            $this->sendResponse(201, true, "Data sungai berhasil ditambahkan", ["id" => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function show($id) {
        try {
            $data = $this->sungaiModel->find($id);
            if (!$data) {
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
            if (!$data) {
                $this->sendResponse(404, false, "Data sungai dengan id {$id} tidak ditemukan");
            }

            $input = $this->getJsonInput();
            $validated = RiverValidator::validateSungai($input);

            $this->sungaiModel->update($id, $validated);
            $updatedData = $this->sungaiModel->find($id);

            $this->publishEvent('sungai_events', [
                'event' => 'sungai_updated',
                'data_lama' => $data,
                'data_baru' => $updatedData,
                'timestamp' => date('c')
            ]);

            $this->sendResponse(200, true, "Data sungai dengan id {$id} berhasil diperbarui", $updatedData);
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function destroy($id) {
        try {
            $data = $this->sungaiModel->find($id);

            if (!$data) {
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