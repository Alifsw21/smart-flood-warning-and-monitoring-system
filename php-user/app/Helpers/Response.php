<?php

namespace App\Helpers;

class Response
{
    public static function json(
        $code,
        $status,
        $message,
        $data = null,
        $service = "php-user"
    )
    {
        http_response_code($code);

        header("Content-Type: application/json");

        echo json_encode([
            "status" => $status,
            "code" => $code,
            "data" => $data,
            "message" => $message,
            "timestamp" => date("Y-m-d\TH:i:s.v\Z"),
            "service" => $service
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }
}