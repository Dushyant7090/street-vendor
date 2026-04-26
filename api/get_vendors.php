<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $vendors = [];
    $query = $conn->query(
        "SELECT 
            u.id as user_id,
            u.name,
            u.email,
            u.created_at as joined,
            v.id as vendor_id,
            v.phone,
            v.address,
            v.id_proof_type,
            v.id_proof_number,
            (SELECT COUNT(*) FROM licenses WHERE vendor_id = v.id AND status = 'approved') as active_licenses,
            (SELECT COUNT(*) FROM locations WHERE vendor_id = v.id AND is_active = 1) as has_location
        FROM users u
        JOIN vendors v ON v.user_id = u.id
        WHERE u.role = 'vendor'
        ORDER BY u.created_at DESC"
    );

    if ($query) {
        $vendors = $query->fetch_all(MYSQLI_ASSOC);
    }

    $totalVendors = count($vendors);
    $totalLicenses = array_sum(array_column($vendors, 'active_licenses'));
    $totalAssigned = array_sum(array_column($vendors, 'has_location'));
    $pending = $totalVendors - $totalAssigned;

    echo json_encode([
        'success' => true,
        'data' => [
            'vendors' => $vendors,
            'stats' => [
                'totalVendors' => $totalVendors,
                'totalLicenses' => $totalLicenses,
                'totalAssigned' => $totalAssigned,
                'pending' => $pending
            ]
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
