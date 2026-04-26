<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$vendorId = (int) ($_POST['vendor_id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$idProofType = trim((string) ($_POST['id_proof_type'] ?? ''));
$idProofNumber = trim((string) ($_POST['id_proof_number'] ?? ''));
$businessName = trim((string) ($_POST['business_name'] ?? ''));
$businessLocation = trim((string) ($_POST['business_location'] ?? ''));

if ($vendorId <= 0 || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
    echo json_encode(['success' => false, 'error' => 'Name, valid email, and phone are required.']);
    exit();
}

try {
    $transactionStarted = false;

    $stmt = $conn->prepare('SELECT user_id FROM vendors WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vendor) {
        echo json_encode(['success' => false, 'error' => 'Vendor not found.']);
        exit();
    }

    $userId = (int) $vendor['user_id'];
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    $duplicate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($duplicate) {
        echo json_encode(['success' => false, 'error' => 'Email is already used by another account.']);
        exit();
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
    $stmt->bind_param('sssi', $name, $email, $phone, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE vendors
        SET name = ?,
            email = ?,
            phone = ?,
            address = ?,
            id_proof_type = ?,
            id_proof_number = ?,
            business_name = ?,
            business_location = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ssssssssi', $name, $email, $phone, $address, $idProofType, $idProofNumber, $businessName, $businessLocation, $vendorId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Vendor updated successfully.']);
} catch (Throwable $e) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . $e->getMessage()]);
}
?>
