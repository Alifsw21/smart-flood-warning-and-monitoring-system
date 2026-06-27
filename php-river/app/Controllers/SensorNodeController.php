<?php
namespace App\Controllers;

use App\Models\SensorNode;

class SensorNodeController extends BaseController {
    private $model;

    public function __construct($db) {
        $this->model = new SensorNode($db);
    }

    public function index() {
        try {
            $data = $this->model->getLatestReadings();
            $this->sendResponse(200, true, "Berhasil mengambil data informasi sensor", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store($userRole) {
        if ($userRole !== 'admin') {
            $this->sendResponse(403, false, "Akses ditolak. Hanya admin yang dapat menambahkan node sensor baru.");
            return;
        }

        $inputData = $this->getJsonInput();

        try {
            $validated = RiverValidator::validateNode($inputData);

            $insertId = $this->model->createNode($validated);
            $responseData = array_merge(["id" => $insertId], $validated);

            $responseData['timestamp'] = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

            $this->publishEvent('river.node.created', $responseData);

            $this->sendResponse(201, true, "Node sensor baru berhasil ditambahkan.", $responseData);
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

        public function update($id, $userRole) {
            if ($userRole !== 'admin') {
                $this->sendResponse(403, false, "Akses ditolak. Hanya admin yang dapat memperbarui data node sensor.");
                return;
            }

            if (!$id) {
                $this->sendResponse(400, false, "Diperlukan parameter ID sensor.");
                return;
            }

            $inputData = $this->getJsonInput();

            try {
                $validated = RiverValidator::validateNode($inputData);
                $this->model->updateNode($id, $validated);
                $responseData = array_merge(["id" => $id], $validated);
                $responseData['timestamp'] = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

                $this->publishEvent('river.node.updated', $responseData);

                $this->sendResponse(200, true, "Data node sensor dengan ID $id berhasil diperbarui.", $responseData);
            } catch (\InvalidArgumentException $e) {
                $this->sendResponse(400, false, $e->getMessage());
            } catch (\Throwable $e) {
                $this->handleException($e);
            }
        }

        public function delete($id, $userRole) {
            if ($userRole !== 'admin') {
                $this->sendResponse(403, false, "Akses ditolak. Hanya admin yang dapat menghapus data node sensor.");
                return;
            }

            if (!$id) {
                $this->sendResponse(400, false, "Diperlukan parameter ID sensor.");
                return;
            }

            try {
                $stmt = $this->model->deleteNode($id);
                if ($stmt->rowCount () === 0) {
                    $this->sendResponse(404, false, "Node sensor dengan ID $id tidak ditemukan.");
                    return;
                }

                $eventData = [
                    "id" => $id,
                    "timestamp" => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z')
                ];

                $this->publishEvent('river.node.deleted', $eventData);

                $this->sendResponse(200, true, "Node sensor dengan ID $id berhasil dihapus.");
            } catch (\Throwable $e) {
                $this->handleException($e);
            }
        }
    }