<?php

namespace App\Controllers;

use App\Models\Zone;

class ZoneController extends BaseController {
    private $zoneModel;

    public function __construct($db) {
        $this->zoneModel = new Zone($db);
    }

    public function index() {
        try {
            $data = $this->zoneModel->all();

            if (empty($data)) {
                $this->sendResponse(200, true, "Belum ada data zona", []);
            }

            $this->sendResponse(200, true, "Data zona berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store() {
        $input = $this->getJsonInput();

        if (empty($input['nama_kota'])) {
            $this->sendResponse(400, false, "nama_kota harus diisi");
        }

        try {
            $id = $this->zoneModel->create($input);

            $this->publishEvent('zone_events', [
                'event' => 'zona_created',
                'id' => $id,
                'nama_kota' => $input['nama_kota'],
                'timestamp' => date('c')
            ]);

            $this->sendResponse(201, true, "Data zona berhasil ditambahkan", ["id" => $id]);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function show($id) {
        try {
            $data = $this->zoneModel->find($id);

            if (empty($data)) {
                $this->sendResponse(404, false, "Data zona dengan id {$id} tidak ditemukan");
            }

            $this->sendResponse(200, true, "Data zona dengan id {$id} berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function update($id) {
        try {
            $data = $this->zoneModel->find($id);

            if (empty($data)) {
                $this->sendResponse(404, false, "Data zona dengan id {$id} tidak ditemukan");
            }

            $input = $this->getJsonInput();
            $this->zoneModel->update($id, $input);
            $updateData = $this->zoneModel->find($id);

            $this->publishEvent('zone_events', [
                'event' => 'zona_updated',
                'data_lama' => $data,
                'data_baru' => $updateData,
                'timestamp' => date('c')
            ]);
            $this->sendResponse(200, true, "Data zona dengan id {$id} berhasil diperbarui", $updateData);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function destroy($id) {
        try {
            $data = $this->zoneModel->find($id);

            if (empty($data)) {
                $this->sendResponse(404, false, "Data zona dengan id {$id} tidak ditemukan");
            }

            $this->zoneModel->delete($id);

            $this->publishEvent('zone_events', [
                'event' => 'zona_deleted',
                'id' => $id,
                'data' => $data,
                'timestamp' => date('c') 
            ]);

            $this->sendResponse(200, true, "Data zona dengan id {$id} berhasil dihapus", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }
}