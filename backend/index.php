<?php
header('Access-Control-Allow-Origin: https://license.devits.pe');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/users.php';
require_once __DIR__ . '/api/clients.php';
require_once __DIR__ . '/api/products.php';
require_once __DIR__ . '/api/licenses.php';
require_once __DIR__ . '/api/validate.php';
require_once __DIR__ . '/api/dashboard.php';
require_once __DIR__ . '/api/audit.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

$segments = explode('/', trim($uri, '/'));
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($resource) {
        case 'auth':
            $action = $id;
            handleAuth($action, $method);
            break;
        case 'users':
            requireAuth();
            handleUsers($id, $method);
            break;
        case 'clients':
            requireAuth();
            handleClients($id, $method);
            break;
        case 'products':
            requireAuth();
            handleProducts($id, $method);
            break;
        case 'licenses':
            requireAuth();
            handleLicenses($id, $method);
            break;
        case 'validate':
            handleValidate($method);
            break;
        case 'dashboard':
            requireAuth();
            handleDashboard($method);
            break;
        case 'audit':
            requireAuth();
            handleAudit($method);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
