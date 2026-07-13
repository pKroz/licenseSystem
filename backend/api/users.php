<?php
function handleUsers(?string $id, string $method): void {
    $db = Database::getInstance();
    $auth = requireAuth(['administrador']);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($id === null) {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT id, username, email, full_name, role, status, created_at, last_login FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            return;
        }
        if ($method === 'POST') {
            $required = ['username', 'email', 'password', 'full_name', 'role'];
            foreach ($required as $f) {
                if (empty($body[$f])) { http_response_code(400); echo json_encode(['error' => "Campo '$f' requerido"]); return; }
            }
            $validRoles = ['administrador', 'cliente', 'soporte', 'vendedor', 'auditor'];
            if (!in_array($body['role'], $validRoles)) { http_response_code(400); echo json_encode(['error' => 'Rol inválido']); return; }
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$body['username'], $body['email']]);
            if ($stmt->fetch()) { http_response_code(409); echo json_encode(['error' => 'Usuario o email ya existe']); return; }
            $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$body['username'], $body['email'], password_hash($body['password'], PASSWORD_BCRYPT), $body['full_name'], $body['role']]);
            $newId = $db->lastInsertId();
            logAction($auth['user_id'], 'CREATE', 'user', (int)$newId);
            http_response_code(201);
            echo json_encode(['id' => $newId, 'message' => 'Usuario creado correctamente']);
            return;
        }
    } else {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT id, username, email, full_name, role, status, created_at FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) { http_response_code(404); echo json_encode(['error' => 'Usuario no encontrado']); return; }
            $logStmt = $db->prepare("SELECT * FROM access_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $logStmt->execute([$id]);
            $user['access_history'] = $logStmt->fetchAll();
            echo json_encode($user);
            return;
        }
        if ($method === 'PUT') {
            $fields = []; $params = [];
            $allowed = ['email', 'full_name', 'role', 'status'];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $body)) { $fields[] = "$field = ?"; $params[] = $body[$field]; }
            }
            if (array_key_exists('password', $body) && $body['password']) {
                $fields[] = 'password = ?';
                $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'Sin campos para actualizar']); return; }
            $params[] = $id;
            $db->prepare("UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            logAction($auth['user_id'], 'UPDATE', 'user', (int)$id);
            echo json_encode(['message' => 'Usuario actualizado correctamente']);
            return;
        }
        if ($method === 'DELETE') {
            $db->prepare("UPDATE users SET deleted_at = NOW(), status = 'deleted' WHERE id = ?")->execute([$id]);
            logAction($auth['user_id'], 'DELETE', 'user', (int)$id);
            echo json_encode(['message' => 'Usuario eliminado correctamente']);
            return;
        }
    }
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
