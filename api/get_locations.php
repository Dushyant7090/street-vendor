<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $qZone = trim((string) ($_GET['q_zone'] ?? $_GET['zone'] ?? ''));
    $qVendor = trim((string) ($_GET['q_vendor'] ?? ''));
    $qStatus = trim((string) ($_GET['status'] ?? ''));

    $where = [];
    $params = [];
    $types = '';
    if ($qZone !== '') {
        $where[] = 'z.zone_name = ?';
        $params[] = $qZone;
        $types .= 's';
    }
    if ($qVendor !== '') {
        $where[] = 'u.name LIKE ?';
        $params[] = '%' . $qVendor . '%';
        $types .= 's';
    }
    if ($qStatus !== '' && in_array($qStatus, ['active', 'inactive'], true)) {
        $where[] = 'loc.status = ?';
        $params[] = $qStatus;
        $types .= 's';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT loc.id,
               loc.vendor_id,
               loc.application_id,
               loc.zone_id,
               loc.spot_number,
               loc.latitude,
               loc.longitude,
               loc.allocated_date,
               loc.assigned_at,
               loc.is_active,
               loc.status,
               z.zone_name,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_capacity,
               COALESCE(z.current_capacity, (SELECT COUNT(*) FROM locations l2 WHERE l2.zone_id = z.id AND l2.is_active = 1)) AS current_capacity,
               u.name AS vendor_name,
               u.email AS vendor_email,
               COALESCE(lic.business_type, lic.license_type, lic.business_name) AS business_type
        FROM locations loc
        JOIN zones z ON z.id = loc.zone_id
        JOIN vendors v ON v.id = loc.vendor_id
        JOIN users u ON u.id = v.user_id
        LEFT JOIN licenses lic ON lic.id = loc.application_id
        {$whereSql}
        ORDER BY loc.is_active DESC, COALESCE(loc.assigned_at, loc.created_at) DESC
    ";
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $zonesResult = $conn->query('SELECT zone_name FROM zones ORDER BY zone_name ASC');
    $zones = $zonesResult ? $zonesResult->fetch_all(MYSQLI_ASSOC) : [];

    $statsResult = $conn->query("
        SELECT
            COUNT(*) AS total_locations,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_locations
        FROM locations
    ");
    $stats = $statsResult ? $statsResult->fetch_assoc() : ['total_locations' => 0, 'active_locations' => 0];

    echo json_encode([
        'success' => true,
        'data' => [
            'locations' => $locations,
            'zones' => $zones,
            'stats' => [
                'totalLocations' => (int) ($stats['total_locations'] ?? 0),
                'allocatedStalls' => (int) ($stats['active_locations'] ?? 0),
                'filteredCount' => count($locations),
            ],
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
