<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}
/**
 * Admin - Approve/Reject License
 * Processes license approval or rejection.
 */
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

$licenseId = intval($_GET['id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));

if (!$licenseId || !in_array($action, ['approve', 'reject'], true)) {
    setFlash('error', 'Invalid request.');
    redirect('/street_vendor/admin/licenses.php');
}

// Get the license
$stmt = $conn->prepare("SELECT l.*, v.user_id, u.name as vendor_name FROM licenses l 
    JOIN vendors v ON l.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE l.id = ? AND l.status = 'pending'");
$stmt->bind_param("i", $licenseId);
$stmt->execute();
$license = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$license) {
    setFlash('error', 'License not found or already processed.');
    redirect('/street_vendor/admin/licenses.php');
}

if ($action === 'approve') {
    // Generate license number, set dates
    $licenseNumber = generateLicenseNumber();
    $issueDate = date('Y-m-d');
    $expiryDate = date('Y-m-d', strtotime('+1 year')); // 1 year validity

    $stmt = $conn->prepare("UPDATE licenses SET status = 'approved', license_number = ?, issue_date = ?, expiry_date = ? WHERE id = ?");
    $stmt->bind_param("sssi", $licenseNumber, $issueDate, $expiryDate, $licenseId);

    if ($stmt->execute()) {
        logAdminAction('License Approved', "Approved license for vendor: {$license['vendor_name']} (#{$licenseNumber})");
        setFlash('success', "License approved for {$license['vendor_name']}! License #: {$licenseNumber}");
    } else {
        setFlash('error', 'Failed to approve license.');
    }
    $stmt->close();

} elseif ($action === 'reject') {
    $remarks = trim((string) ($_GET['remarks'] ?? 'Application did not meet the requirements.'));

    $stmt = $conn->prepare("UPDATE licenses SET status = 'rejected', remarks = ? WHERE id = ?");
    $stmt->bind_param("si", $remarks, $licenseId);

    if ($stmt->execute()) {
        logAdminAction('License Rejected', "Rejected license for vendor: {$license['vendor_name']}");
        setFlash('success', "License application for {$license['vendor_name']} has been rejected.");
    } else {
        setFlash('error', 'Failed to reject license.');
    }
    $stmt->close();
}

redirect('/street_vendor/admin/licenses.php');
?>
