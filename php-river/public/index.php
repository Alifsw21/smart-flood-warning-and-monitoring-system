<?php 

error_reporting(E_ALL);
ini_set('display_errors', '1');

header("Content-Type: application/json");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once __DIR__ . '/../database/database.php';
$dbInstance = new Database();
$db = $dbInstance->getConnection();

$requestHeaders = array_change_key_case(getallheaders(), CASE_LOWER);
$userRole = $requestHeaders['x-user-role'] ?? 'pengguna';
$method = $_SERVER['REQUEST_METHOD'];

$url = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$id = $_GET['id'] ?? null;

$controller = new \App\Controllers\SensorNodeController($db);

if ($url === '/api/river/sensors') {
    switch ($method) {
        case 'GET':
            $controller->index();
            break;
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $controller->store($input, $userRole);
            break;
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $controller->update($id, $input, $userRole);
            break;
        case 'DELETE':
            $controller->delete($id, $userRole);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                "status" => "error",
                "code" => 405,
                "message" => "Method HTTP Tidak Diizinkan."
            ]);
            break;
    }
} else {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "code" => 404,
        "message" => "Endpoint Tidak Ditemukan."
    ]);
}