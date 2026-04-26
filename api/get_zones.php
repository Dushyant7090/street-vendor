<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'vendor'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $occupiedExpr = zoneCapacityExpression();
    $sql = "
        SELECT z.id,
               z.zone_name,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_capacity,
               {$occupiedExpr} AS current_capacity,
               COALESCE(z.description, z.area_description, '') AS description,
               COALESCE(z.geometry_json, z.geometry) AS geometry_json,
               CASE
                   WHEN COALESCE(z.status, IF(z.is_active = 1, 'available', 'not_available')) IN ('available', 'Available') THEN 'available'
                   ELSE 'not_available'
               END AS status,
               z.created_at
        FROM zones z
        WHERE COALESCE(z.geometry_json, z.geometry, '') <> ''
        ORDER BY z.zone_name ASC
    ";

    $result = $conn->query($sql);
    $zones = [];
    while ($row = $result->fetch_assoc()) {
        $max = (int) $row['max_capacity'];
        $current = (int) $row['current_capacity'];
        $status = (string) $row['status'];
        $row['available_slots'] = max($max - $current, 0);
        $row['color_status'] = zoneColorStatus($current, $max, $status);

        // Backward-compatible aliases for older map code.
        $row['max_vendors'] = $row['max_capacity'];
        $row['occupied_spots'] = $row['current_capacity'];
        $row['geometry'] = $row['geometry_json'];
        $row['area_description'] = $row['description'];
        $row['is_active'] = $status === 'available' ? 1 : 0;

        $zones[] = $row;
    }

    $stats = [
        'totalZones' => count($zones),
        'activeZones' => count(array_filter($zones, static fn($z) => $z['status'] === 'available')),
        'inactiveZones' => count(array_filter($zones, static fn($z) => $z['status'] !== 'available')),
        'totalCapacity' => array_sum(array_map(static fn($z) => (int) $z['max_capacity'], $zones)),
    ];

    echo json_encode(['success' => true, 'data' => ['zones' => $zones, 'stats' => $stats]]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
