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

        if ($e instanceof \PDOException) {
            if ($e->getCode() == 23000) {
            $this->sendResponse(409, false, $e->getMessage());
            return;
        }

        $debugData = $isDebug ? $e->getMessage() : null;
            error_log("Database Error: " . $e->getMessage());
            $this->sendResponse(500, false, "Terjadi kesalahan pada database", $debugData);
        } else {
            $debugData = $isDebug ? $e->getMessage() : null;
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

    protected function publishEvents(array $events) {
        if (empty($events)) {
            return;
        }

        try {
            $rabbitMQ = new RabbitMQPublisher();
            $rabbitMQ->publishMany($events);
            $rabbitMQ->close();
        } catch (\Throwable $e) {
            error_log("RabbitMQ Publish Error: " . $e->getMessage());
        }
    }
}