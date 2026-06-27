<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Jakarta');

use App\Controllers\FloodHistoryController;
use App\Models\FloodHistoryModel;

$requestHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$userRole = (($requestHeaders['x-user-role'] ?? '') === 'admin') ? 'admin' : 'user';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim($requestPath, '/');

if ($path === '//') {
    $path = '/';
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/login.php';
    exit;
}

$makeController = function () {
    return new FloodHistoryController(new FloodHistoryModel());
};

$sendRouterResponse = function ($code, $status, $message) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'code' => (int) $code,
        'data' => null,
        'message' => $message,
        'timestamp' => date('Y-m-d\TH:i:s.v\Z'),
        'service' => 'php-user',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

if ($path === '/api/flood-history') {
    if ($method === 'GET') {
        $makeController()->index();
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/flood-history/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $makeController()->show($id);
    }

    if ($method === 'DELETE') {
        $makeController()->delete($id, $userRole);
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/health') {
    if ($method === 'GET') {
        $makeController()->health();
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

$sendRouterResponse(404, 'error', 'Endpoint Tidak Ditemukan.');
