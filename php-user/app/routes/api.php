<?php

use App\Controllers\FloodHistoryController;
use App\Models\FloodHistoryModel;

$model = new FloodHistoryModel();
$controller = new FloodHistoryController($model);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$basePath = "/api";

if ($method === "GET" && $uri === "$basePath/health") {
    $controller->health();
}

if ($method === "GET" && $uri === "$basePath/flood-history") {
    $controller->index();
}

if ($method === "GET" &&
    preg_match("#^$basePath/flood-history/(\d+)$#", $uri, $matches)) {

    $controller->show($matches[1]);
}

if ($method === "DELETE" &&
    preg_match("#^$basePath/flood-history/(\d+)$#", $uri, $matches)) {

    $userRole = "admin";

    $controller->delete($matches[1], $userRole);
}

http_response_code(404);

echo json_encode([
    "status" => "error",
    "code" => 404,
    "message" => "Endpoint Tidak Ditemukan",
    "timestamp" => date('Y-m-d\TH:i:s.v\Z'),
    "service" => "php-user"
]);