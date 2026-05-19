<?php
// api/validar_licencia.php
// Endpoint público para que apps externas validen sus licencias
// Uso: POST /api/validar_licencia.php
// Body JSON: { "clave": "XXXX-XXXX-XXXX-XXXX", "dominio": "miapp.com", "dispositivo_id": "abc123" }

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../models/Licencia.php';

// Leer body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (empty($body['clave'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el parámetro clave']);
    exit;
}

$modelo    = new Licencia();
$contexto  = [
    'ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
    'dominio'        => $body['dominio']        ?? null,
    'dispositivo_id' => $body['dispositivo_id'] ?? null,
    'instalacion_id' => $body['instalacion_id'] ?? null,
];

$respuesta = $modelo->validarClave($body['clave'], $contexto);

$httpCode = match($respuesta['resultado']) {
    'activa'        => 200,
    'vencida'       => 403,
    'suspendida'    => 403,
    'no_autorizada' => 401,
    default         => 404,
};

http_response_code($httpCode);
echo json_encode($respuesta);
