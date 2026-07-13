<?php
function handleAuth(?string $action, string $method): void {
    $db = Database::getInstance();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
        case 'login':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Método no permitido']); return; }
            $identifier = trim($body['identifier'] ?? '');
            $password = $body['password'] ?? '';
            if (!$identifier || !$password) {
                http_response_code(400);
                echo json_encode(['error' => 'Usuario/correo y contraseña requeridos']);
                return;
            }
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Credenciales incorrectas']);
                return;
            }
            // Log access
            $db->prepare("INSERT INTO access_logs (user_id, ip_address, created_at) VALUES (?, ?, NOW())")
               ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            $token = generateToken(['user_id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]);
            echo json_encode([
                'token' => $token,
                'user' => ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'role' => $user['role'], 'full_name' => $user['full_name']]
            ]);
            break;

        case 'register':
            if ($method !== 'POST') { http_response_code(405); return; }
            $required = ['username', 'email', 'password', 'full_name'];
            foreach ($required as $field) {
                if (empty($body[$field])) { http_response_code(400); echo json_encode(['error' => "Campo '$field' requerido"]); return; }
            }
            if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400); echo json_encode(['error' => 'Email inválido']); return;
            }
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$body['username'], $body['email']]);
            if ($stmt->fetch()) { http_response_code(409); echo json_encode(['error' => 'Usuario o email ya existe']); return; }
            $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, 'cliente', 'active', NOW())");
            $stmt->execute([$body['username'], $body['email'], password_hash($body['password'], PASSWORD_BCRYPT), $body['full_name']]);
            http_response_code(201);
            echo json_encode(['message' => 'Usuario registrado correctamente']);
            break;

        case 'forgot-password':
            if ($method !== 'POST') { http_response_code(405); return; }
            $email = trim($body['email'] ?? '');
            if (!$email) { http_response_code(400); echo json_encode(['error' => 'Email requerido']); return; }
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $db->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())")
                   ->execute([$user['id'], $token, $expires]);
                // In production: send email with reset link
            }
            echo json_encode(['message' => 'Si el correo existe, recibirás instrucciones para restablecer tu contraseña']);
            break;

        case 'reset-password':
            if ($method !== 'POST') { http_response_code(405); return; }
            $token = $body['token'] ?? '';
            $newPassword = $body['password'] ?? '';
            if (!$token || !$newPassword) { http_response_code(400); echo json_encode(['error' => 'Token y contraseña requeridos']); return; }
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            if (!$reset) { http_response_code(400); echo json_encode(['error' => 'Token inválido o expirado']); return; }
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPassword, PASSWORD_BCRYPT), $reset['user_id']]);
            $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);
            echo json_encode(['message' => 'Contraseña actualizada correctamente']);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Acción no encontrada']);
    }
}
