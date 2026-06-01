<?php

// ================================================================
// HRIS Attendance System — Front Controller
// ================================================================

// ----------------------------------------------------------------
// CORS headers — harus di-set SEBELUM bootstrap/require apapun
// agar tidak ada output yang menghalangi header() call
// ----------------------------------------------------------------
$allowedOrigins = [
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: ' . (
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']
    ?? 'Content-Type, Authorization, Accept, Origin, X-Requested-With'
));
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Serve static files from storage/ directly (e.g. profile photos)
$staticPath = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($staticPath) && str_starts_with(realpath($staticPath), realpath(__DIR__ . '/storage'))) {
    $mime = mime_content_type($staticPath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($staticPath);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

// ----------------------------------------------------------------
// Router
// ----------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

// Method spoofing: POST + _method field → override method (untuk file upload via PUT/PATCH)
if ($method === 'POST' && !empty($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

// PHP hanya auto-populate $_POST/$_FILES untuk POST.
// Untuk PUT/PATCH dengan multipart/form-data, parse manual dari php://input.
if (in_array($method, ['PUT', 'PATCH'], true)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'multipart/form-data') && preg_match('/boundary=(.+)$/i', $ct, $bm)) {
        $boundary = trim($bm[1]);
        $raw = file_get_contents('php://input');
        foreach (explode("--{$boundary}", $raw) as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || str_starts_with($part, '--')) continue;
            if (!str_contains($part, "\r\n\r\n")) continue;
            [$headers, $body] = explode("\r\n\r\n", $part, 2);
            $body = rtrim($body, "\r\n");
            if (!preg_match('/;\s*name="([^"]+)"/i', $headers, $nm)) continue;
            $name = $nm[1];
            if (preg_match('/filename="([^"]+)"/i', $headers, $fm)) {
                $mime = 'application/octet-stream';
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $cm)) {
                    $mime = trim($cm[1]);
                }
                $tmp = tempnam(sys_get_temp_dir(), 'php_put_');
                file_put_contents($tmp, $body);
                $_FILES[$name] = [
                    'name'     => $fm[1],
                    'type'     => $mime,
                    'tmp_name' => $tmp,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => strlen($body),
                ];
            } else {
                $_POST[$name] = $body;
            }
        }
    }
}

$uriRaw = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uriRaw, '/');
$routes = require __DIR__ . '/routes/api.php';

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
    $db = (new Database())->getConnection();

    if (!empty($allowedRoles)) {
        $auth = new AuthMiddleware($db);
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
