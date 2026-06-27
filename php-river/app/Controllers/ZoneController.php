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
            $this->sendResponse(200, true, "Data zona berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store() {
        $input = $this->getJsonInput();

        try {
            $validated = RiverValidator::validateZone($input);
            $id = $this->zoneModel->create($validated);

            $this->publishEvent('zone_events', [
                'event' => 'zona_created',
                'id' => $id,
                'nama_kota' => $validated['nama_kota'],
                'timestamp' => date('c')
            ]);

            $this->sendResponse(201, true, "Data zona berhasil ditambahkan", ["id" => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function show($id) {
        try {
            $data = $this->zoneModel->find($id);
            if (!$data) {
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
            if (!$data) {
                $this->sendResponse(404, false, "Data zona dengan id {$id} tidak ditemukan");
            }

            $input = $this->getJsonInput();
            $validated = RiverValidator::validateZone($input);

            $this->zoneModel->update($id, $validated);
            $updateData = $this->zoneModel->find($id);

            $this->publishEvent('zone_events', [
                'event' => 'zona_updated',
                'data_lama' => $data,
                'data_baru' => $updateData,
                'timestamp' => date('c')
            ]);
            $this->sendResponse(200, true, "Data zona dengan id {$id} berhasil diperbarui", $updateData);
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function destroy($id) {
        try {
            $data = $this->zoneModel->find($id);
            if (!$data) {
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