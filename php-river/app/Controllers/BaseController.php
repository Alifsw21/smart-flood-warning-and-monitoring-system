<?php

namespace App\Controllers;

use App\Services\RabbitMQPublisher;

class BaseController {
    protected function sendResponse($code, $success, $message, $data = null) {
        http_response_code($code);

        header('Content-Type: application/json');

        echo json_encode([
            "success"   => $success,
            "code"      => $code,
            "message"   => $message,
            "data"      => $data,
            "service"   => "php-river"
        ]);
        exit;
    }

    protected function getJsonInput() {
        $json = file_get_contents('php://input');

        return json_decode($json, true);
    }

    protected function handleException(\Throwable $e) {
        $isDebug = isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
        $debugData = $isDebug ? $e->getMessage() : null;

        if ($e instanceof \PDOException) {
            error_log("Database Error: " . $e->getMessage());
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
    }
}