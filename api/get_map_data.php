<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $occupiedExpr = zoneCapacityExpression();
    $zonesSql = "
        SELECT z.id,
               z.zone_name,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_capacity,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_vendors,
               {$occupiedExpr} AS current_capacity,
               {$occupiedExpr} AS occupied_spots,
               COALESCE(z.description, z.area_description, '') AS description,
               COALESCE(z.description, z.area_description, '') AS area_description,
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
    $zonesRes = $conn->query($zonesSql);
    $zones = [];
    while ($row = $zonesRes->fetch_assoc()) {
        $row['available_slots'] = max((int) $row['max_capacity'] - (int) $row['current_capacity'], 0);
        $row['color_status'] = zoneColorStatus((int) $row['current_capacity'], (int) $row['max_capacity'], (string) $row['status']);
        $zones[] = $row;
    }

    $assignedSql = "
        SELECT l.id AS location_id,
               l.latitude,
               l.longitude,
               l.spot_number,
               l.zone_id,
               l.application_id,
               v.id AS vendor_id,
               COALESCE(v.name, u.name) AS vendor_name,
               COALESCE(v.business_name, lic.business_type, lic.business_name) AS business_name
        FROM locations l
        JOIN vendors v ON l.vendor_id = v.id
        JOIN users u ON u.id = v.user_id
        LEFT JOIN licenses lic ON lic.id = l.application_id
        WHERE l.is_active = 1 AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL
    ";
    $assignedRes = $conn->query($assignedSql);
    $assignedVendors = $assignedRes ? $assignedRes->fetch_all(MYSQLI_ASSOC) : [];

    $unassignedSql = "
        SELECT lic.id AS application_id,
               v.id AS vendor_id,
               COALESCE(v.name, u.name) AS vendor_name,
               COALESCE(lic.business_type, lic.business_name, v.business_name) AS business_name,
               v.phone,
               lic.zone_id,
               z.zone_name
        FROM licenses lic
        JOIN vendors v ON lic.vendor_id = v.id
        JOIN users u ON u.id = v.user_id
        LEFT JOIN zones z ON z.id = lic.zone_id
        WHERE lic.status = 'approved'
          AND NOT EXISTS (
              SELECT 1 FROM locations l
              WHERE l.application_id = lic.id AND l.is_active = 1
          )
        ORDER BY lic.reviewed_at DESC, lic.id DESC
    ";
    $unassignedRes = $conn->query($unassignedSql);
    $unassignedVendors = $unassignedRes ? $unassignedRes->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode([
        'success' => true,
        'data' => [
            'zones' => $zones,
            'assigned_vendors' => $assignedVendors,
            'unassigned_vendors' => $unassignedVendors,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
