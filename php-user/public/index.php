<?php

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Jakarta');

use App\Controllers\FloodHistoryController;
use App\Controllers\LaporanController;
use App\Controllers\UserController;
use App\Models\FloodHistoryModel;
use App\Models\LaporanModel;
use App\Models\UserModel;

$requestHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$userRole = (($requestHeaders['x-user-role'] ?? '') === 'admin') ? 'admin' : 'user';
$userId = isset($requestHeaders['x-user-id']) ? (int) $requestHeaders['x-user-id'] : 0;
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

$floodHistoryController = new FloodHistoryController(new FloodHistoryModel());
$userController = new UserController(new UserModel());
$laporanController = new LaporanController(new LaporanModel());

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
        $floodHistoryController->index();
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/flood-history/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $floodHistoryController->show($id);
    }

    if ($method === 'DELETE') {
        $floodHistoryController->delete($id, $userRole);
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/api/users') {
    if ($method === 'GET') {
        $userController->index($userRole);
    }

    if ($method === 'POST') {
        $userController->store($userRole);
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/users/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $userController->show($id, $userRole, $userId);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $userController->update($id, $userRole, $userId);
    }

    if ($method === 'DELETE') {
        $userController->destroy($id, $userRole, $userId);
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/api/laporan') {
    if ($method === 'GET') {
        $laporanController->index($userRole, $userId);
    }

    if ($method === 'POST') {
        $laporanController->store($userRole, $userId);
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/laporan/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $laporanController->show($id, $userRole, $userId);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $laporanController->update($id, $userRole, $userId);
    }

    if ($method === 'DELETE') {
        $laporanController->destroy($id, $userRole, $userId);
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/health') {
    if ($method === 'GET') {
        $floodHistoryController->health();
    }

    $sendRouterResponse(405, 'error', 'Method HTTP Tidak Diizinkan.');
}

$sendRouterResponse(404, 'error', 'Endpoint Tidak Ditemukan.');
