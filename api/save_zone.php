<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$id = isset($data['id']) ? (int) $data['id'] : 0;
$zoneName = trim((string) ($data['zone_name'] ?? ''));
$maxCapacity = (int) ($data['max_capacity'] ?? $data['max_vendors'] ?? 0);
$description = trim((string) ($data['description'] ?? ''));
$status = (string) ($data['status'] ?? 'available');
$status = $status === 'not_available' ? 'not_available' : 'available';
$geometry = $data['geometry_json'] ?? $data['geometry'] ?? null;
$geometryJson = is_string($geometry) ? $geometry : json_encode($geometry);

if ($zoneName === '' || $maxCapacity <= 0 || empty($geometryJson)) {
    echo json_encode(['success' => false, 'error' => 'Zone name, capacity, and polygon are required']);
    exit();
}

$decoded = json_decode($geometryJson, true);
if (!is_array($decoded)) {
    echo json_encode(['success' => false, 'error' => 'Invalid polygon geometry']);
    exit();
}

$isActive = $status === 'available' ? 1 : 0;

try {
    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE zones
            SET zone_name = ?,
                max_vendors = ?,
                max_capacity = ?,
                area_description = ?,
                description = ?,
                geometry = ?,
                geometry_json = ?,
                is_active = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->bind_param('siissssisi', $zoneName, $maxCapacity, $maxCapacity, $description, $description, $geometryJson, $geometryJson, $isActive, $status, $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Zone updated', 'id' => $id]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO zones
            (zone_name, max_vendors, max_capacity, current_capacity, area_description, description, geometry, geometry_json, is_active, status)
        VALUES
            (?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('siissssis', $zoneName, $maxCapacity, $maxCapacity, $description, $description, $geometryJson, $geometryJson, $isActive, $status);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Zone created', 'id' => $newId]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
