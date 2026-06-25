<?php 
header("Content-Type: application/json");
date_default_timezone_set('Asia/Jakarta');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

$requestHeaders = array_change_key_case(getallheaders(), CASE_LOWER);
$userRole = $requestHeaders['x-user-role'] ?? 'pengguna';
$method = $_SERVER['REQUEST_METHOD'];
$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id = $_GET['id'] ?? null;

$controller = new App\Controllers\SensorController();

if ($url === '/api/river/sensors') {
    switch ($method) {
        case 'GET';
            $controller->index();
            break;
        case 'POST';
            $input = json_decode(file_get_contents('php://input'), true);
            $controller->store($input, $userRole);
            break;
        case 'PUT';
            $input = json_decode(file_get_contents('php://input'), true);
            $controller->update($id, $input, $userRole);
            break;
        case 'DELETE';
            $controller->delete($id, $userRole);
            break;
        default;
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