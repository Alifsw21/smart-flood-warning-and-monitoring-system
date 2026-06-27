<?php

namespace App\Controllers;

use App\Services\RabbitMQPublisher;

class BaseController
{
    protected function sendResponse($code, $status, $message, $data = null)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'code' => (int) $code,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('Y-m-d\TH:i:s.v\Z'),
            'service' => 'php-user',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function getJsonInput()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    protected function publishEvent($routingKey, array $eventData)
    {
        try {
            $publisher = new RabbitMQPublisher();
            $publisher->publish($routingKey, $eventData);
            $publisher->close();
        } catch (\Throwable $e) {
            error_log('RabbitMQ Publish Error: ' . $e->getMessage());
        }
    }
}
