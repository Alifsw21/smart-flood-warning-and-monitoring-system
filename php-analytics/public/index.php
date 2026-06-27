<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Controllers\PeringatanController;
use App\Models\PeringatanModel;

$requestHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$userRole = (($requestHeaders['x-user-role'] ?? '') === 'admin') ? 'admin' : 'user';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim($requestPath, '/');

if ($path === '//') {
    $path = '/';
}

$makeController = function () {
    return new PeringatanController(new PeringatanModel());
};

$sendRouterResponse = function ($code, $status, $message) {
    http_response_code($code);
    echo json_encode([
        "status" => $status,
        "code" => (int) $code,
        "data" => null,
        "message" => $message,
        "timestamp" => date('Y-m-d\TH:i:s.v\Z'),
        "service" => "php-analytics"
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

if ($path === '/api/analytics/peringatan' || $path === '/api/environment/alerts') {
    if ($method === 'GET') {
        if ($path === '/api/environment/alerts') {
            $makeController()->activeAlerts();
        } else {
            $makeController()->index();
        }
    }

    $sendRouterResponse(405, "error", "Method HTTP Tidak Diizinkan.");
}

if (preg_match('#^/api/analytics/peringatan/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $makeController()->show($id);
    }

    if ($method === 'DELETE') {
        $makeController()->delete($id, $userRole);
    }

    $sendRouterResponse(405, "error", "Method HTTP Tidak Diizinkan.");
}

if ($path === '/health') {
    if ($method === 'GET') {
        $makeController()->health();
    }

    $sendRouterResponse(405, "error", "Method HTTP Tidak Diizinkan.");
}

$sendRouterResponse(404, "error", "Endpoint Tidak Ditemukan.");
