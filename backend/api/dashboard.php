<?php
function handleDashboard(string $method): void {
    if ($method !== 'GET') { http_response_code(405); return; }
    $db = Database::getInstance();
    requireAuth();

    $stats = [];

    // License counts by status
    $stmt = $db->query("SELECT status, COUNT(*) AS total FROM licenses GROUP BY status");
    $byStatus = [];
    foreach ($stmt->fetchAll() as $row) { $byStatus[$row['status']] = (int)$row['total']; }
    $stats['licenses'] = [
        'active'    => $byStatus['active'] ?? 0,
        'expired'   => $byStatus['expired'] ?? 0,
        'suspended' => $byStatus['suspended'] ?? 0,
        'cancelled' => $byStatus['cancelled'] ?? 0,
        'total'     => array_sum($byStatus),
    ];

    // Expiring soon (next 30 days)
    $stmt = $db->query("SELECT COUNT(*) AS total FROM licenses WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)");
    $stats['expiring_soon'] = (int)$stmt->fetchColumn();

    // Total clients and products
    $stats['total_clients']  = (int)$db->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL")->fetchColumn();
    $stats['total_products'] = (int)$db->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();

    // Monthly licenses (last 6 months)
    $stmt = $db->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
        FROM licenses
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month ORDER BY month
    ");
    $stats['monthly_licenses'] = $stmt->fetchAll();

    // Top clients by active licenses
    $stmt = $db->query("
        SELECT c.company_name, COUNT(l.id) AS active_licenses
        FROM clients c
        JOIN licenses l ON l.client_id = c.id AND l.status = 'active'
        WHERE c.deleted_at IS NULL
        GROUP BY c.id ORDER BY active_licenses DESC LIMIT 5
    ");
    $stats['top_clients'] = $stmt->fetchAll();

    // Licenses expiring soon details
    $stmt = $db->query("
        SELECT l.license_key, c.company_name AS client, p.name AS product,
               l.expires_at, DATEDIFF(l.expires_at, NOW()) AS days_remaining
        FROM licenses l
        JOIN clients c ON l.client_id = c.id
        JOIN products p ON l.product_id = p.id
        WHERE l.status = 'active' AND l.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        ORDER BY l.expires_at ASC LIMIT 10
    ");
    $stats['expiring_soon_list'] = $stmt->fetchAll();

    // Recent activity
    $stmt = $db->query("
        SELECT al.*, u.full_name AS user_name FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC LIMIT 10
    ");
    $stats['recent_activity'] = $stmt->fetchAll();

    echo json_encode($stats);
}
