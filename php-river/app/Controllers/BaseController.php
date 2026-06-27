<?php

namespace App\Controllers;

use App\Services\RabbitMQPublisher;

class BaseController {
    protected function sendResponse($code, $success, $message, $data = null) {
        http_response_code($code);

        header('Content-Type: application/json');

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d\TH:i:s.v\Z');

        echo json_encode([
            "status"   => $success ? "success" : "error",
            "code"      => $code,
            "data"      => $data,
            "message"   => $message,
            "timestamp" => $timestamp,
            "service"   => "php-river"
        ]);
        exit;
    }

    protected function getJsonInput() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    protected function handleException(\Throwable $e) {
       $isDebug = isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
        $debugData = $isDebug ? $e->getMessage() : null;

        if ($e instanceof \PDOException) {
            error_log("Database Error: " . $e->getMessage());
            if (str_contains($e->getMessage(), '1062')) {
                $this->sendResponse(409, false, $e->getMessage(), $debugData);
            }
            if (str_contains($e->getMessage(), '1451') || str_contains($e->getMessage(), 'foreign key constraint')) {
                $this->sendResponse(409, false, "Data tidak dapat dihapus karena masih memiliki relasi aktif.", $debugData);
            }
            if (str_contains($e->getMessage(), 'masih memiliki')) {
                $this->sendResponse(409, false, $e->getMessage(), $debugData);
            }
            $this->sendResponse(500, false, "Terjadi kesalahan pada database", $debugData);
        } else {
           error_log("System Error: " . $e->getMessage());
           $this->sendResponse(500, false, "Terjadi kesalahan pada server", $debugData);
           }
    }

    protected function publishEvent($queueName, $eventData) {
        try {
            $rabbitMQ = new RabbitMQPublisher();
            $rabbitMQ->publish($queueName, $eventData);
            $rabbitMQ->close();
        } catch (\Throwable $e) {
          error_log("RabbitMQ Publish Error: " . $e->getMessage());
        }
        return;
    }
}