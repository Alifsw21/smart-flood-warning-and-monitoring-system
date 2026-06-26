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

            $this->sendResponse(200, true, "Data log sensor IoT berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store() {
        $input = $this->getJsonInput();

        try {
            $validated = RiverValidator::validateReading($input);
            $record = $this->sensorReadingModel->createReading($validated);
            $this->publishEvent('river_sensor_events', [
                'event' => 'sensor_data_ingested',
                'id' => $record['id'] ?? null,
                'idNode' => $validated['idNode'],
                'tinggiAir' => $validated['tinggiAir'],
                'kelembapanTanah' => $validated['kelembapanTanah'],
                'curahHujan' => $validated['curahHujan'],
                'suhuRataRata' => $validated['suhuRataRata'],
                'kelembapanUdara' => $validated['kelembapanUdara'],
                'kecepatanAngin' => $validated['kecepatanAngin'],
                'arahAngin' => $input['arahAngin'] ?? null,
                'timestamp' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z')
            ]);

            $this->sendResponse(201, true, "Data telemetri IoT berhasil disimpan", $record);
            
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) { 
            $this->handleException($e);
        }
    }
}