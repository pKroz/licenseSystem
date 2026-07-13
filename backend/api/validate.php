<?php
function handleValidate(string $method): void {
    if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Método no permitido']); return; }
    $db = Database::getInstance();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $licenseKey = trim($body['license_key'] ?? '');
    if (!$licenseKey) { http_response_code(400); echo json_encode(['valid' => false, 'error' => 'Clave de licencia requerida']); return; }

    // Reject unauthorized API keys
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$apiKey) { http_response_code(401); echo json_encode(['valid' => false, 'error' => 'API key requerida']); return; }
    $stmt = $db->prepare("SELECT id FROM api_keys WHERE api_key = ? AND status = 'active'");
    $stmt->execute([$apiKey]);
    if (!$stmt->fetch()) { http_response_code(401); echo json_encode(['valid' => false, 'error' => 'API key inválida']); return; }

    $stmt = $db->prepare("
        SELECT l.*, p.name AS product_name, c.company_name AS client_name
        FROM licenses l
        LEFT JOIN products p ON l.product_id = p.id
        LEFT JOIN clients c ON l.client_id = c.id
        WHERE l.license_key = ?
    ");
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $domain = $body['domain'] ?? null;
    $deviceId = $body['device_id'] ?? null;
    $installId = $body['installation_id'] ?? null;

    if (!$license) {
        $db->prepare("INSERT INTO validation_logs (license_key, result, ip_address, domain, device_id, installation_id, created_at) VALUES (?, 'invalid', ?, ?, ?, ?, NOW())")
           ->execute([$licenseKey, $ip, $domain, $deviceId, $installId]);
        http_response_code(404);
        echo json_encode(['valid' => false, 'status' => 'invalid', 'message' => 'Licencia no encontrada']);
        return;
    }

    // Auto-expire
    if ($license['status'] === 'active' && strtotime($license['expires_at']) < time()) {
        $db->prepare("UPDATE licenses SET status = 'expired', updated_at = NOW() WHERE id = ?")->execute([$license['id']]);
        $license['status'] = 'expired';
    }

    $result = $license['status'];
    $valid = $license['status'] === 'active';
    $messages = [
        'active'    => 'Licencia activa y válida',
        'expired'   => 'Licencia vencida',
        'suspended' => 'Licencia suspendida',
        'cancelled' => 'Licencia cancelada',
    ];

    $db->prepare("INSERT INTO validation_logs (license_id, license_key, result, ip_address, domain, device_id, installation_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())")
       ->execute([$license['id'], $licenseKey, $result, $ip, $domain, $deviceId, $installId]);

    $response = [
        'valid'       => $valid,
        'status'      => $result,
        'message'     => $messages[$result] ?? 'Estado desconocido',
        'product'     => $license['product_name'],
        'client'      => $license['client_name'],
        'expires_at'  => $license['expires_at'],
        'days_remaining' => max(0, (int)ceil((strtotime($license['expires_at']) - time()) / 86400)),
        'max_users'   => $license['max_users'],
        'max_devices' => $license['max_devices'],
        'modules'     => $license['modules'] ? json_decode($license['modules'], true) : [],
        'validated_at'=> date('Y-m-d H:i:s'),
    ];

    http_response_code($valid ? 200 : 403);
    echo json_encode($response);
}
