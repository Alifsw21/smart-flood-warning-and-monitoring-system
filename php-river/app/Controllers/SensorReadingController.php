<?php

namespace App\Controllers;

use App\Models\SensorReading;

class SensorReadingController extends BaseController {
    private $sensorReadingModel;
    
    public function __construct($db) {
        $this->sensorReadingModel = new SensorReading($db);
    }

    public function index() {
        try {
            $data = $this->sensorReadingModel->getLatestReadings();

            if (empty($data)) {
                $this->sendResponse(200, true, "Belum ada data dari sensor IoT", []);
            }

            $this->sendResponse(200, true, "Data sensor IoT berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }
}