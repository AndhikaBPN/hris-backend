<?php

// ================================================================
// HRIS Attendance System — Front Controller
// ================================================================

require_once __DIR__ . '/bootstrap.php';

// ----------------------------------------------------------------
// CORS headers
// ----------------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----------------------------------------------------------------
// Router
// ----------------------------------------------------------------
$method    = $_SERVER['REQUEST_METHOD'];
$uriRaw    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri       = rtrim($uriRaw, '/');
$routes    = require __DIR__ . '/routes/api.php';

foreach ($routes as [$routeMethod, $pattern, $controllerName, $action, $allowedRoles]) {

    // Konversi pattern {id} → regex
    $regex = '#^' . preg_replace('/\{[a-z]+\}/', '([^/]+)', $pattern) . '$#';

    if ($routeMethod !== $method || !preg_match($regex, $uri, $matches)) {
        continue;
    }

    // Ambil dynamic segments (misal id)
    array_shift($matches); // buang full match

    // Require auth jika route tidak public
    $payload = [];
    $db      = (new Database())->getConnection();

    if (!empty($allowedRoles)) {
        $auth    = new AuthMiddleware($db);
        $payload = $auth->handle();
        RoleMiddleware::require($payload, $allowedRoles);
    }

    // Inject ke request supaya controller bisa pakai
    $GLOBALS['auth_user'] = $payload;

    // Panggil controller
    require_once __DIR__ . "/app/Controllers/{$controllerName}.php";
    $controller = new $controllerName($db);
    call_user_func_array([$controller, $action], $matches);
    exit;
}

// Tidak ada route yang cocok
ResponseHelper::error('Endpoint not found', 404);
