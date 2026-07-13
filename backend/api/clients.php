<?php
function handleClients(?string $id, string $method): void {
    $db = Database::getInstance();
    $auth = requireAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($id === null) {
        if ($method === 'GET') {
            $search = $_GET['search'] ?? '';
            $params = [];
            $where = 'WHERE c.deleted_at IS NULL';
            if ($search) { $where .= ' AND (c.company_name LIKE ? OR c.ruc_dni LIKE ? OR c.email LIKE ?)'; $params = ["%$search%", "%$search%", "%$search%"]; }
            $stmt = $db->prepare("
                SELECT c.*, COUNT(l.id) AS total_licenses,
                       SUM(l.status = 'active') AS active_licenses
                FROM clients c
                LEFT JOIN licenses l ON l.client_id = c.id
                $where GROUP BY c.id ORDER BY c.company_name
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            return;
        }
        if ($method === 'POST') {
            requireAuth(['administrador', 'vendedor']);
            $required = ['company_name', 'email'];
            foreach ($required as $f) {
                if (empty($body[$f])) { http_response_code(400); echo json_encode(['error' => "Campo '$f' requerido"]); return; }
            }
            $stmt = $db->prepare("INSERT INTO clients (company_name, ruc_dni, email, phone, address, representative, type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$body['company_name'], $body['ruc_dni'] ?? null, $body['email'], $body['phone'] ?? null, $body['address'] ?? null, $body['representative'] ?? null, $body['type'] ?? 'empresa']);
            $newId = $db->lastInsertId();
            logAction($auth['user_id'], 'CREATE', 'client', (int)$newId);
            http_response_code(201);
            echo json_encode(['id' => $newId, 'message' => 'Cliente registrado correctamente']);
            return;
        }
    } else {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT c.*, COUNT(l.id) AS total_licenses FROM clients c LEFT JOIN licenses l ON l.client_id = c.id WHERE c.id = ? AND c.deleted_at IS NULL GROUP BY c.id");
            $stmt->execute([$id]);
            $client = $stmt->fetch();
            if (!$client) { http_response_code(404); echo json_encode(['error' => 'Cliente no encontrado']); return; }
            $lStmt = $db->prepare("SELECT l.*, p.name AS product_name FROM licenses l LEFT JOIN products p ON l.product_id = p.id WHERE l.client_id = ? ORDER BY l.created_at DESC");
            $lStmt->execute([$id]);
            $client['licenses'] = $lStmt->fetchAll();
            echo json_encode($client);
            return;
        }
        if ($method === 'PUT') {
            requireAuth(['administrador', 'vendedor']);
            $fields = []; $params = [];
            $allowed = ['company_name', 'ruc_dni', 'email', 'phone', 'address', 'representative', 'type'];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $body)) { $fields[] = "$field = ?"; $params[] = $body[$field]; }
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'Sin campos para actualizar']); return; }
            $params[] = $id;
            $db->prepare("UPDATE clients SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            logAction($auth['user_id'], 'UPDATE', 'client', (int)$id);
            echo json_encode(['message' => 'Cliente actualizado correctamente']);
            return;
        }
        if ($method === 'DELETE') {
            requireAuth(['administrador']);
            $db->prepare("UPDATE clients SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            logAction($auth['user_id'], 'DELETE', 'client', (int)$id);
            echo json_encode(['message' => 'Cliente eliminado correctamente']);
            return;
        }
    }
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
