<?php
function handleAudit(string $method): void {
    if ($method !== 'GET') { http_response_code(405); return; }
    $db = Database::getInstance();
    requireAuth(['administrador', 'auditor']);

    $where = [];
    $params = [];
    if (!empty($_GET['entity'])) { $where[] = 'al.entity = ?'; $params[] = $_GET['entity']; }
    if (!empty($_GET['user_id'])) { $where[] = 'al.user_id = ?'; $params[] = $_GET['user_id']; }
    if (!empty($_GET['action'])) { $where[] = 'al.action = ?'; $params[] = $_GET['action']; }
    if (!empty($_GET['from'])) { $where[] = 'al.created_at >= ?'; $params[] = $_GET['from']; }
    if (!empty($_GET['to'])) { $where[] = 'al.created_at <= ?'; $params[] = $_GET['to'] . ' 23:59:59'; }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $offset = (int)($_GET['offset'] ?? 0);

    $stmt = $db->prepare("
        SELECT al.*, u.full_name AS user_name, u.role AS user_role
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $whereStr
        ORDER BY al.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    echo json_encode(['data' => $logs, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
}
