<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $isDebug ? '1' : '0');

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../database/database.php';
$dbInstance = new Database();
$db = $dbInstance->getConnection();

$requestHeaders = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
$userRole = $requestHeaders['x-user-role'] ?? 'user';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim($requestPath, '/');

if ($path === '//') {
    $path = '/';
}

$sendRouterResponse = function (int $code, string $message): void {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'code' => $code,
        'data' => null,
        'message' => $message,
        'timestamp' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
        'service' => 'php-river',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$sendHealthResponse = function (int $code, bool $healthy, string $message): void {
    http_response_code($code);
    echo json_encode([
        'status' => $healthy ? 'success' : 'error',
        'code' => $code,
        'data' => ['db' => $healthy ? 'connected' : 'disconnected'],
        'message' => $message,
        'timestamp' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
        'service' => 'php-river',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

if ($path === '/health') {
    if ($method !== 'GET') {
        $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
    }

    try {
        $db->query('SELECT 1');
        $sendHealthResponse(200, true, 'Service php-river sehat');
    } catch (Throwable $e) {
        $sendHealthResponse(503, false, 'Database tidak tersedia');
    }
}

$sensorNodeController = new \App\Controllers\SensorNodeController($db);
$zoneController = new \App\Controllers\ZoneController($db);
$sungaiController = new \App\Controllers\SungaiController($db);
$readingController = new \App\Controllers\SensorReadingController($db);

if ($path === '/api/river/sensors') {
    if ($method === 'GET') {
        $sensorNodeController->index();
    }
    if ($method === 'POST') {
        $sensorNodeController->store($userRole);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/river/sensors/([^/]+)/readings$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $readingController->readingsByNode($id);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/river/sensors/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $sensorNodeController->show($id);
    }
    if ($method === 'PUT') {
        $sensorNodeController->update($id, $userRole);
    }
    if ($method === 'DELETE') {
        $sensorNodeController->delete($id, $userRole);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/api/river/zones') {
    if ($method === 'GET') {
        $zoneController->index();
    }
    if ($method === 'POST') {
        $zoneController->store($userRole);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/river/zones/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $zoneController->show($id);
    }
    if ($method === 'PUT') {
        $zoneController->update($id, $userRole);
    }
    if ($method === 'DELETE') {
        $zoneController->destroy($id, $userRole);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/api/river/sungai') {
    if ($method === 'GET') {
        $sungaiController->index();
    }
    if ($method === 'POST') {
        $sungaiController->store($userRole);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if (preg_match('#^/api/river/sungai/([^/]+)$#', $path, $matches)) {
    $id = $matches[1];

    if ($method === 'GET') {
        $sungaiController->show($id);
    }
    if ($method === 'PUT') {
        $sungaiController->update($id, $userRole);
    }
    if ($method === 'DELETE') {
        $sungaiController->destroy($id, $userRole);
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

$readingPaths = ['/api/river/readings', '/api/environment/readings', '/api/traffic/readings'];

if (in_array($path, $readingPaths, true)) {
    if ($method === 'GET') {
        $readingController->index();
    }
    if ($method === 'POST') {
        $readingController->store();
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

$currentPaths = ['/api/environment/current', '/api/traffic/current'];

if (in_array($path, $currentPaths, true)) {
    if ($method === 'GET') {
        $readingController->current();
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

if ($path === '/api/traffic/history') {
    if ($method === 'GET') {
        $readingController->history();
    }
    $sendRouterResponse(405, 'Method HTTP Tidak Diizinkan.');
}

$sendRouterResponse(404, 'Endpoint Tidak Ditemukan.');
