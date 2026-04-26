<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'vendor'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$zoneId = (int) ($_GET['zone_id'] ?? $_GET['id'] ?? 0);
$zone = $zoneId > 0 ? fetchZoneById($conn, $zoneId) : null;
if (!$zone) {
    echo json_encode(['success' => false, 'error' => 'Zone not found']);
    exit();
}

$max = (int) $zone['effective_max_capacity'];
$current = (int) $zone['effective_current_capacity'];
$status = (string) $zone['effective_status'];
$available = max($max - $current, 0);

echo json_encode([
    'success' => true,
    'data' => [
        'zone_id' => $zoneId,
        'max_capacity' => $max,
        'current_capacity' => $current,
        'available_slots' => $available,
        'status' => $status,
        'is_full' => $status !== 'available' || $available <= 0,
        'color_status' => zoneColorStatus($current, $max, $status),
    ],
]);
?>
