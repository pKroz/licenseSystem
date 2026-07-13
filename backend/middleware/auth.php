<?php
define('JWT_SECRET', 'licenseflow_secret_key_2024_change_in_production');
define('JWT_EXPIRY', 86400); // 24 hours

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function generateToken(array $payload): string {
    $header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true));
    return "$header.$payloadEncoded.$signature";
}

function verifyToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $signature] = $parts;
    $expectedSig = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expectedSig, $signature)) return null;
    $decoded = json_decode(base64UrlDecode($payload), true);
    if (!$decoded || $decoded['exp'] < time()) return null;
    return $decoded;
}

function requireAuth(array $allowedRoles = []): array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Token requerido']);
        exit();
    }
    $token = substr($authHeader, 7);
    $payload = verifyToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit();
    }
    if (!empty($allowedRoles) && !in_array($payload['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permisos para esta acción']);
        exit();
    }
    return $payload;
}

function logAction(int $userId, string $action, string $entity, ?int $entityId = null, ?string $details = null): void {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $entity, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Fail silently for logs
    }
}
