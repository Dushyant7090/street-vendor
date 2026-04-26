<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // 1. Total Vendors
    $query = $conn->query("SELECT COUNT(*) as count FROM vendors");
    $totalVendors = $query ? $query->fetch_assoc()['count'] : 0;

    // 2. Active Licenses
    $query = $conn->query("SELECT COUNT(*) as count FROM licenses WHERE status = 'approved'");
    $totalLicenses = $query ? $query->fetch_assoc()['count'] : 0;

    // 3. Zone Occupancy (Calculation: allocated locations / max_vendors across all zones)
    $zoneQuery = $conn->query("SELECT SUM(max_vendors) as max_capacity FROM zones WHERE is_active = 1");
    $maxCapacity = $zoneQuery ? $zoneQuery->fetch_assoc()['max_capacity'] : 0;
    
    $allocQuery = $conn->query("SELECT COUNT(*) as count FROM locations WHERE is_active = 1");
    $totalAllocated = $allocQuery ? $allocQuery->fetch_assoc()['count'] : 0;
    
    $zoneOccupancy = ($maxCapacity > 0) ? round(($totalAllocated / $maxCapacity) * 100) : 0;

    // 4. Real application counts
    $query = $conn->query("SELECT COUNT(*) as count FROM licenses");
    $totalApplications = $query ? (int) $query->fetch_assoc()['count'] : 0;

    $query = $conn->query("SELECT COUNT(*) as count FROM licenses WHERE status = 'pending'");
    $pendingApplications = $query ? (int) $query->fetch_assoc()['count'] : 0;

    $query = $conn->query("SELECT COUNT(*) as count FROM licenses WHERE status = 'rejected'");
    $rejectedApplications = $query ? (int) $query->fetch_assoc()['count'] : 0;

    // 5. Last 7 days application trend
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $trend[$date] = [
            'date' => $date,
            'label' => date('D', strtotime($date)),
            'count' => 0,
        ];
    }

    $trendQuery = $conn->query("
        SELECT DATE(COALESCE(applied_at, created_at)) AS application_date, COUNT(*) AS count
        FROM licenses
        WHERE DATE(COALESCE(applied_at, created_at)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
        GROUP BY DATE(COALESCE(applied_at, created_at))
    ");
    if ($trendQuery) {
        while ($row = $trendQuery->fetch_assoc()) {
            if (isset($trend[$row['application_date']])) {
                $trend[$row['application_date']]['count'] = (int) $row['count'];
            }
        }
    }

    $trendValues = array_values($trend);
    $totalWeeklyApplications = array_sum(array_column($trendValues, 'count'));
    $averageDailyApplications = round($totalWeeklyApplications / 7, 1);

    // 6. Renewal Alerts (Licenses expiring in next 30 days)
    $renewalQuery = $conn->query("SELECT COUNT(*) as count FROM licenses WHERE status = 'approved' AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $renewalAlerts = $renewalQuery ? $renewalQuery->fetch_assoc()['count'] : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'totalVendors' => (int)$totalVendors,
            'totalLicenses' => (int)$totalLicenses,
            'zoneOccupancy' => (int)$zoneOccupancy,
            'totalApplications' => (int)$totalApplications,
            'pendingApplications' => (int)$pendingApplications,
            'rejectedApplications' => (int)$rejectedApplications,
            'weeklyApplications' => $trendValues,
            'averageDailyApplications' => $averageDailyApplications,
            'renewalAlerts' => (int)$renewalAlerts
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
