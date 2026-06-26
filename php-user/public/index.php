<?php

require_once __DIR__ . '/../bootstrap/app.php';

$routes = require __DIR__ . '/../routes/web.php';

$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$route =
    $routes[$method][$uri]
    ?? null;

if (!$route) {

    http_response_code(404);

    exit('404 Not Found');

}

[$controller, $action] = $route;

(new $controller)->$action();