<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $qVendor = trim((string) ($_GET['q_vendor'] ?? ''));
    $qStatus = trim((string) ($_GET['status'] ?? ''));
    $qZone = trim((string) ($_GET['zone'] ?? ''));
    $qCategory = trim((string) ($_GET['category'] ?? ''));

    $where = [];
    $params = [];
    $types = '';

    if ($qVendor !== '') {
        $where[] = 'u.name LIKE ?';
        $params[] = '%' . $qVendor . '%';
        $types .= 's';
    }
    if ($qStatus !== '' && in_array($qStatus, ['pending', 'approved', 'rejected', 'expired'], true)) {
        $where[] = 'l.status = ?';
        $params[] = $qStatus;
        $types .= 's';
    }
    if ($qZone !== '') {
        $where[] = 'z.zone_name = ?';
        $params[] = $qZone;
        $types .= 's';
    }
    if ($qCategory !== '') {
        $where[] = 'l.vendor_category = ?';
        $params[] = $qCategory;
        $types .= 's';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "
        SELECT l.id,
               l.vendor_id,
               l.zone_id,
               l.license_number,
               l.status,
               l.remarks,
               l.applied_date,
               l.applied_at,
               l.reviewed_at,
               l.issue_date,
               l.expiry_date,
               COALESCE(l.business_type, l.license_type, l.business_name) AS business_type,
               l.vendor_category,
               l.priority_type,
               l.aadhar_path,
               l.photo_path,
               l.business_proof_path,
               u.name AS vendor_name,
               u.email AS vendor_email,
               v.phone,
               z.zone_name,
               COALESCE(z.max_capacity, z.max_vendors, 0) AS max_capacity,
               COALESCE(z.current_capacity, (SELECT COUNT(*) FROM locations loc WHERE loc.zone_id = z.id AND loc.is_active = 1)) AS current_capacity,
               (SELECT spot_number FROM locations loc WHERE loc.application_id = l.id AND loc.is_active = 1 LIMIT 1) AS spot_number
        FROM licenses l
        JOIN vendors v ON v.id = l.vendor_id
        JOIN users u ON u.id = v.user_id
        LEFT JOIN zones z ON z.id = l.zone_id
        {$whereSql}
        ORDER BY
            CASE l.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END,
            COALESCE(l.applied_at, l.created_at) DESC,
            l.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $licenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $zonesResult = $conn->query('SELECT zone_name FROM zones ORDER BY zone_name ASC');
    $zones = $zonesResult ? $zonesResult->fetch_all(MYSQLI_ASSOC) : [];

    $categoriesResult = $conn->query("SELECT DISTINCT vendor_category FROM licenses WHERE vendor_category IS NOT NULL AND vendor_category <> '' ORDER BY vendor_category ASC");
    $categories = $categoriesResult ? $categoriesResult->fetch_all(MYSQLI_ASSOC) : [];

    $statsResult = $conn->query('SELECT status, COUNT(*) AS c FROM licenses GROUP BY status');
    $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'expired' => 0];
    if ($statsResult) {
        while ($row = $statsResult->fetch_assoc()) {
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']] = (int) $row['c'];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'licenses' => $licenses,
            'zones' => $zones,
            'categories' => $categories,
            'stats' => [
                'totalLicenses' => array_sum($statusCounts),
                'pendingCount' => $statusCounts['pending'],
                'approvedCount' => $statusCounts['approved'],
                'rejectedCount' => $statusCounts['rejected'],
                'expiredCount' => $statusCounts['expired'],
                'filteredCount' => count($licenses),
            ],
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
