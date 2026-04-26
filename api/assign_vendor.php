<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$applicationId = (int) ($data['application_id'] ?? $data['license_id'] ?? 0);
$vendorId = (int) ($data['vendor_id'] ?? 0);
$zoneId = (int) ($data['zone_id'] ?? 0);
$latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
$longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
$spotNumber = trim((string) ($data['spot_number'] ?? ''));

if ($latitude === null || $longitude === null || !$zoneId || (!$applicationId && !$vendorId)) {
    echo json_encode(['success' => false, 'error' => 'Application/vendor, zone, latitude, and longitude are required']);
    exit();
}

try {
    $conn->begin_transaction();

    if ($applicationId <= 0) {
        $stmt = $conn->prepare("
            SELECT id, vendor_id, zone_id
            FROM licenses
            WHERE vendor_id = ? AND status = 'approved'
              AND NOT EXISTS (SELECT 1 FROM locations l WHERE l.application_id = licenses.id AND l.is_active = 1)
            ORDER BY reviewed_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$app) {
            throw new RuntimeException('No approved unassigned application found for this vendor.');
        }
        $applicationId = (int) $app['id'];
        $vendorId = (int) $app['vendor_id'];
        $zoneId = (int) ($app['zone_id'] ?: $zoneId);
    } else {
        $stmt = $conn->prepare("SELECT id, vendor_id, zone_id, status FROM licenses WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$app || $app['status'] !== 'approved') {
            throw new RuntimeException('Only approved applications can be allocated.');
        }
        $vendorId = (int) $app['vendor_id'];
        $zoneId = (int) ($app['zone_id'] ?: $zoneId);
    }

    $stmt = $conn->prepare("
        SELECT id,
               COALESCE(max_capacity, max_vendors, 0) AS max_capacity,
               COALESCE(status, IF(is_active = 1, 'available', 'not_available')) AS status
        FROM zones
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $zoneId);
    $stmt->execute();
    $zone = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$zone) {
        throw new RuntimeException('Zone not found.');
    }
    if ($zone['status'] !== 'available') {
        throw new RuntimeException('Selected zone is not available.');
    }

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM locations WHERE zone_id = ? AND is_active = 1');
    $stmt->bind_param('i', $zoneId);
    $stmt->execute();
    $current = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    $max = (int) $zone['max_capacity'];
    if ($current >= $max) {
        throw new RuntimeException('Selected zone is full.');
    }

    if ($spotNumber === '') {
        $spotNumber = 'SP-' . $zoneId . '-' . str_pad((string) ($current + 1), 3, '0', STR_PAD_LEFT);
    }

    $stmt = $conn->prepare('SELECT id FROM locations WHERE zone_id = ? AND spot_number = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('is', $zoneId, $spotNumber);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($duplicate) {
        throw new RuntimeException('This spot number is already active in the selected zone.');
    }

    $stmt = $conn->prepare('SELECT id FROM locations WHERE application_id = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('i', $applicationId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        throw new RuntimeException('This application already has an active location.');
    }

    $stmt = $conn->prepare("
        INSERT INTO locations
            (vendor_id, application_id, zone_id, spot_number, latitude, longitude, allocated_date, assigned_at, is_active, status)
        VALUES
            (?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 1, 'active')
    ");
    $stmt->bind_param('iiisdd', $vendorId, $applicationId, $zoneId, $spotNumber, $latitude, $longitude);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE zones z
        SET current_capacity = (
            SELECT COUNT(*) FROM locations l WHERE l.zone_id = z.id AND l.is_active = 1
        )
        WHERE z.id = ?
    ");
    $stmt->bind_param('i', $zoneId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Vendor assigned successfully', 'spot_number' => $spotNumber]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
