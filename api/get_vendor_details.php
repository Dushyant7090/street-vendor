<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$vendorId = (int) ($_GET['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid vendor ID']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT v.id AS vendor_id,
               v.user_id,
               COALESCE(v.name, u.name) AS name,
               COALESCE(v.email, u.email) AS email,
               v.phone,
               v.address,
               v.location,
               v.business_name,
               v.business_location,
               v.id_proof_type,
               v.id_proof_number,
               v.photo,
               u.created_at AS joined
        FROM vendors v
        JOIN users u ON u.id = v.user_id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vendor) {
        echo json_encode(['success' => false, 'error' => 'Vendor not found']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT l.id,
               l.status,
               l.license_number,
               COALESCE(l.business_type, l.license_type, l.business_name) AS business_type,
               l.vendor_category,
               l.priority_type,
               l.remarks,
               l.applied_at,
               l.applied_date,
               l.issue_date,
               l.expiry_date,
               l.aadhar_path,
               l.photo_path,
               l.business_proof_path,
               z.zone_name
        FROM licenses l
        LEFT JOIN zones z ON z.id = l.zone_id
        WHERE l.vendor_id = ?
        ORDER BY COALESCE(l.applied_at, l.created_at) DESC, l.id DESC
    ");
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT loc.id,
               loc.spot_number,
               loc.latitude,
               loc.longitude,
               loc.status,
               COALESCE(loc.assigned_at, CONCAT(loc.allocated_date, ' 00:00:00')) AS assigned_at,
               z.zone_name
        FROM locations loc
        LEFT JOIN zones z ON z.id = loc.zone_id
        WHERE loc.vendor_id = ?
        ORDER BY loc.is_active DESC, COALESCE(loc.assigned_at, loc.created_at) DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $location = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => [
            'vendor' => $vendor,
            'applications' => $applications,
            'location' => $location,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
