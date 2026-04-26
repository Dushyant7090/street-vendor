<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireAdmin();

$licenseId = (int) ($_GET['id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));

if ($licenseId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    setFlash('error', 'Invalid application request.');
    redirect('/street_vendor/admin/licenses.php');
}

$stmt = $conn->prepare("
    SELECT l.*,
           u.name AS vendor_name,
           z.zone_name,
           COALESCE(z.max_capacity, z.max_vendors, 0) AS max_capacity,
           COALESCE(z.status, IF(z.is_active = 1, 'available', 'not_available')) AS zone_status
    FROM licenses l
    JOIN vendors v ON v.id = l.vendor_id
    JOIN users u ON u.id = v.user_id
    LEFT JOIN zones z ON z.id = l.zone_id
    WHERE l.id = ? AND l.status = 'pending'
    LIMIT 1
");
$stmt->bind_param('i', $licenseId);
$stmt->execute();
$license = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$license) {
    setFlash('error', 'Application not found or already processed.');
    redirect('/street_vendor/admin/licenses.php');
}

if ($action === 'reject') {
    $remarks = trim((string) ($_GET['remarks'] ?? 'Application did not meet the requirements.'));
    $stmt = $conn->prepare("UPDATE licenses SET status = 'rejected', remarks = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $remarks, $licenseId);
    $stmt->execute();
    $stmt->close();

    logAdminAction('License Rejected', "Rejected application #{$licenseId} for {$license['vendor_name']}");
    setFlash('success', "Application for {$license['vendor_name']} has been rejected.");
    redirect('/street_vendor/admin/licenses.php');
}

if (empty($license['zone_id'])) {
    setFlash('error', 'Cannot approve: application has no selected zone.');
    redirect('/street_vendor/admin/licenses.php');
}

$zoneId = (int) $license['zone_id'];
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        SELECT id,
               zone_name,
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
        throw new RuntimeException('Selected zone no longer exists.');
    }
    if ($zone['status'] !== 'available') {
        throw new RuntimeException('Selected zone is not available. Reject this application or ask the vendor to apply for another available zone.');
    }

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM locations WHERE zone_id = ? AND is_active = 1');
    $stmt->bind_param('i', $zoneId);
    $stmt->execute();
    $currentCapacity = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    if ($currentCapacity >= (int) $zone['max_capacity']) {
        throw new RuntimeException('Selected zone is full. Reject this application or assign another available zone before approval.');
    }

    $licenseNumber = generateLicenseNumber();
    $issueDate = date('Y-m-d');
    $expiryDate = date('Y-m-d', strtotime('+1 year'));
    $remarks = 'Approved. Final capacity will update after spot allocation.';

    $stmt = $conn->prepare("
        UPDATE licenses
        SET status = 'approved',
            license_number = ?,
            issue_date = ?,
            expiry_date = ?,
            reviewed_at = NOW(),
            remarks = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ssssi', $licenseNumber, $issueDate, $expiryDate, $remarks, $licenseId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    logAdminAction('License Approved', "Approved application #{$licenseId} for {$license['vendor_name']} ({$licenseNumber})");
    setFlash('success', "Application approved. Now assign an exact spot/location for {$license['vendor_name']}.");
    redirect('/street_vendor/admin/allocate_location.php?application_id=' . $licenseId);
} catch (Throwable $e) {
    $conn->rollback();
    setFlash('error', 'Approval blocked: ' . $e->getMessage());
    redirect('/street_vendor/admin/licenses.php');
}
?>
