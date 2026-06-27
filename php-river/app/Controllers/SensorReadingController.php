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
            $nodeId = $_GET['nodeId'] ?? $_GET['idNode'] ?? null;
            $zoneId = $_GET['zoneId'] ?? null;
            $from = $_GET['from'] ?? null;
            $to = $_GET['to'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            if ($nodeId !== null && $nodeId !== '') {
                $data = $this->sensorReadingModel->getReadingByNode((int)$nodeId, $limit);
                $this->sendResponse(200, true, "Data pembacaan sensor untuk node $nodeId berhasil diambil", $data);
                return;
            }

            if ($zoneId !== null || $from !== null || $to !== null) {
                $data = $this->sensorReadingModel->getHistory([
                    'zoneId' => $zoneId,
                    'from' => $from,
                    'to' => $to,
                    'limit' => $limit,
                ]);
                $this->sendResponse(200, true, "Riwayat pembacaan sensor berhasil diambil", $data);
                return;
            }

            $data = $this->sensorReadingModel->getLatestReadings($limit);
            $this->sendResponse(200, true, "Data log sensor IoT berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function readingsByNode($idNode) {
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $data = $this->sensorReadingModel->getReadingByNode((int)$idNode, $limit);
            $this->sendResponse(200, true, "Data pembacaan sensor untuk node $idNode berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function current() {
        try {
            $zoneId = $_GET['zoneId'] ?? null;
            $parsedZoneId = ($zoneId !== null && $zoneId !== '') ? (int)$zoneId : null;

            $data = $this->sensorReadingModel->getCurrentByZone($parsedZoneId);
            $message = $parsedZoneId === null
                ? "Kondisi lingkungan real-time semua zona berhasil diambil"
                : "Kondisi lingkungan real-time zona $parsedZoneId berhasil diambil";

            $this->sendResponse(200, true, $message, $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function history() {
        try {
            $data = $this->sensorReadingModel->getHistory([
                'zoneId' => $_GET['zoneId'] ?? null,
                'idNode' => $_GET['nodeId'] ?? $_GET['idNode'] ?? null,
                'from' => $_GET['from'] ?? null,
                'to' => $_GET['to'] ?? null,
                'limit' => $_GET['limit'] ?? 100,
            ]);

            $this->sendResponse(200, true, "Riwayat pembacaan sensor berhasil diambil", $data);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function store() {
        $input = $this->getJsonInput();

        try {
            $validated = RiverValidator::validateReading($input);
            $record = $this->sensorReadingModel->createReading($validated);

            $eventPayload = [
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
                'timestamp' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
            ];

            $this->publishEvent('river_sensor_events', $eventPayload);
            $this->publishEvent('air.new', $eventPayload);
            $this->publishEvent('traffic.new', $eventPayload);

            $this->sendResponse(201, true, "Data telemetri IoT berhasil disimpan", $record);
        } catch (\InvalidArgumentException $e) {
            $this->sendResponse(400, false, $e->getMessage());
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }
}
