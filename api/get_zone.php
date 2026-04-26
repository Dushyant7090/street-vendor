<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'vendor'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$zoneId = (int) ($_GET['id'] ?? 0);
if ($zoneId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid zone ID']);
    exit();
}

$zone = fetchZoneById($conn, $zoneId);
if (!$zone) {
    echo json_encode(['success' => false, 'error' => 'Zone not found']);
    exit();
}

$max = (int) $zone['effective_max_capacity'];
$current = (int) $zone['effective_current_capacity'];
$status = (string) $zone['effective_status'];

echo json_encode([
    'success' => true,
    'data' => [
        'id' => (int) $zone['id'],
        'zone_name' => $zone['zone_name'],
        'max_capacity' => $max,
        'current_capacity' => $current,
        'available_slots' => max($max - $current, 0),
        'description' => $zone['effective_description'],
        'status' => $status,
        'geometry_json' => $zone['effective_geometry'],
        'color_status' => zoneColorStatus($current, $max, $status),
    ],
]);
?>
