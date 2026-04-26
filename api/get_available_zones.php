<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (($_SESSION['role'] ?? '') !== 'vendor') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $occupiedExpr = zoneCapacityExpression();
    $sql = "
        SELECT z.id,
               z.zone_name,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_capacity,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_vendors,
               {$occupiedExpr} AS current_capacity,
               {$occupiedExpr} AS occupied_spots,
               COALESCE(z.description, z.area_description, '') AS description,
               COALESCE(z.geometry_json, z.geometry) AS geometry_json,
               COALESCE(z.geometry_json, z.geometry) AS geometry,
               CASE
                   WHEN COALESCE(z.status, IF(z.is_active = 1, 'available', 'not_available')) IN ('available', 'Available') THEN 'available'
                   ELSE 'not_available'
               END AS status
        FROM zones z
        WHERE COALESCE(z.geometry_json, z.geometry, '') <> ''
        ORDER BY z.zone_name ASC
    ";
    $result = $conn->query($sql);
    $zones = [];
    while ($row = $result->fetch_assoc()) {
        $max = (int) $row['max_capacity'];
        $current = (int) $row['current_capacity'];
        $row['available_slots'] = max($max - $current, 0);
        $row['color_status'] = zoneColorStatus($current, $max, (string) $row['status']);
        $zones[] = $row;
    }
    echo json_encode(['success' => true, 'data' => ['zones' => $zones]]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
