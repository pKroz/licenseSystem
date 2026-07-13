<?php
function generateLicenseKey(): string {
    $segments = [];
    for ($i = 0; $i < 4; $i++) {
        $segments[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return implode('-', $segments);
}

function handleLicenses(?string $id, string $method): void {
    $db = Database::getInstance();
    $auth = requireAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($id === null) {
        // Collection
        if ($method === 'GET') {
            $where = [];
            $params = [];
            if (!empty($_GET['client_id'])) { $where[] = 'l.client_id = ?'; $params[] = $_GET['client_id']; }
            if (!empty($_GET['product_id'])) { $where[] = 'l.product_id = ?'; $params[] = $_GET['product_id']; }
            if (!empty($_GET['status'])) { $where[] = 'l.status = ?'; $params[] = $_GET['status']; }
            if (!empty($_GET['plan'])) { $where[] = 'l.plan = ?'; $params[] = $_GET['plan']; }
            if ($auth['role'] === 'cliente') { $where[] = 'l.client_id IN (SELECT client_id FROM client_users WHERE user_id = ?)'; $params[] = $auth['user_id']; }
            $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $db->prepare("
                SELECT l.*, c.company_name AS client_name, p.name AS product_name,
                       DATEDIFF(l.expires_at, NOW()) AS days_remaining
                FROM licenses l
                LEFT JOIN clients c ON l.client_id = c.id
                LEFT JOIN products p ON l.product_id = p.id
                $whereStr
                ORDER BY l.created_at DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            return;
        }
        if ($method === 'POST') {
            requireAuth(['administrador', 'vendedor']);
            $required = ['client_id', 'product_id', 'plan', 'starts_at', 'expires_at'];
            foreach ($required as $f) {
                if (empty($body[$f])) { http_response_code(400); echo json_encode(['error' => "Campo '$f' requerido"]); return; }
            }
            $licenseKey = generateLicenseKey();
            $stmt = $db->prepare("
                INSERT INTO licenses (license_key, client_id, product_id, plan, status, starts_at, expires_at,
                    max_users, max_devices, max_installations, modules, created_by, created_at)
                VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $licenseKey, $body['client_id'], $body['product_id'], $body['plan'],
                $body['starts_at'], $body['expires_at'],
                $body['max_users'] ?? null, $body['max_devices'] ?? null,
                $body['max_installations'] ?? null,
                isset($body['modules']) ? json_encode($body['modules']) : null,
                $auth['user_id']
            ]);
            $newId = $db->lastInsertId();
            logAction($auth['user_id'], 'CREATE', 'license', (int)$newId, "Key: $licenseKey");
            http_response_code(201);
            echo json_encode(['id' => $newId, 'license_key' => $licenseKey, 'message' => 'Licencia creada correctamente']);
            return;
        }
    } else {
        // Single resource
        if ($method === 'GET') {
            $stmt = $db->prepare("
                SELECT l.*, c.company_name AS client_name, p.name AS product_name,
                       u.full_name AS created_by_name,
                       DATEDIFF(l.expires_at, NOW()) AS days_remaining
                FROM licenses l
                LEFT JOIN clients c ON l.client_id = c.id
                LEFT JOIN products p ON l.product_id = p.id
                LEFT JOIN users u ON l.created_by = u.id
                WHERE l.id = ?
            ");
            $stmt->execute([$id]);
            $license = $stmt->fetch();
            if (!$license) { http_response_code(404); echo json_encode(['error' => 'Licencia no encontrada']); return; }
            // History
            $histStmt = $db->prepare("SELECT lh.*, u.full_name AS changed_by_name FROM license_history lh LEFT JOIN users u ON lh.changed_by = u.id WHERE lh.license_id = ? ORDER BY lh.created_at DESC");
            $histStmt->execute([$id]);
            $license['history'] = $histStmt->fetchAll();
            echo json_encode($license);
            return;
        }
        if ($method === 'PUT') {
            requireAuth(['administrador', 'vendedor']);
            $stmt = $db->prepare("SELECT * FROM licenses WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if (!$current) { http_response_code(404); echo json_encode(['error' => 'Licencia no encontrada']); return; }

            $fields = [];
            $params = [];
            $allowed = ['status', 'expires_at', 'plan', 'max_users', 'max_devices', 'max_installations', 'modules'];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $body)) {
                    $fields[] = "$field = ?";
                    $params[] = $field === 'modules' ? json_encode($body[$field]) : $body[$field];
                }
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'Sin campos para actualizar']); return; }
            $params[] = $id;
            $db->prepare("UPDATE licenses SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?")->execute($params);

            // Save history
            $action = $body['status'] ?? 'UPDATE';
            $db->prepare("INSERT INTO license_history (license_id, action, old_status, new_status, changed_by, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$id, strtoupper($action), $current['status'], $body['status'] ?? $current['status'], $auth['user_id'], $body['notes'] ?? null]);

            logAction($auth['user_id'], 'UPDATE', 'license', (int)$id, json_encode($body));
            echo json_encode(['message' => 'Licencia actualizada correctamente']);
            return;
        }
        if ($method === 'DELETE') {
            requireAuth(['administrador']);
            $db->prepare("UPDATE licenses SET status = 'cancelled', updated_at = NOW() WHERE id = ?")->execute([$id]);
            logAction($auth['user_id'], 'DELETE', 'license', (int)$id);
            echo json_encode(['message' => 'Licencia cancelada correctamente']);
            return;
        }
    }
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
