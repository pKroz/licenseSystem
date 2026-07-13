<?php
function handleProducts(?string $id, string $method): void {
    $db = Database::getInstance();
    $auth = requireAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($id === null) {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT p.*, COUNT(l.id) AS total_licenses, SUM(l.status='active') AS active_licenses FROM products p LEFT JOIN licenses l ON l.product_id = p.id WHERE p.deleted_at IS NULL GROUP BY p.id ORDER BY p.name");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            return;
        }
        if ($method === 'POST') {
            requireAuth(['administrador']);
            if (empty($body['name'])) { http_response_code(400); echo json_encode(['error' => 'Nombre requerido']); return; }
            $stmt = $db->prepare("INSERT INTO products (name, description, version, status, modules, created_at) VALUES (?, ?, ?, 'active', ?, NOW())");
            $stmt->execute([$body['name'], $body['description'] ?? null, $body['version'] ?? '1.0', isset($body['modules']) ? json_encode($body['modules']) : null]);
            $newId = $db->lastInsertId();
            logAction($auth['user_id'], 'CREATE', 'product', (int)$newId);
            http_response_code(201);
            echo json_encode(['id' => $newId, 'message' => 'Producto creado correctamente']);
            return;
        }
    } else {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if (!$product) { http_response_code(404); echo json_encode(['error' => 'Producto no encontrado']); return; }
            echo json_encode($product);
            return;
        }
        if ($method === 'PUT') {
            requireAuth(['administrador']);
            $fields = []; $params = [];
            foreach (['name', 'description', 'version', 'status'] as $field) {
                if (array_key_exists($field, $body)) { $fields[] = "$field = ?"; $params[] = $body[$field]; }
            }
            if (array_key_exists('modules', $body)) { $fields[] = 'modules = ?'; $params[] = json_encode($body['modules']); }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'Sin campos para actualizar']); return; }
            $params[] = $id;
            $db->prepare("UPDATE products SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            logAction($auth['user_id'], 'UPDATE', 'product', (int)$id);
            echo json_encode(['message' => 'Producto actualizado correctamente']);
            return;
        }
        if ($method === 'DELETE') {
            requireAuth(['administrador']);
            $db->prepare("UPDATE products SET deleted_at = NOW(), status = 'inactive' WHERE id = ?")->execute([$id]);
            logAction($auth['user_id'], 'DELETE', 'product', (int)$id);
            echo json_encode(['message' => 'Producto desactivado correctamente']);
            return;
        }
    }
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
