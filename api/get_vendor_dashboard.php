<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/workflow_helpers.php';

if (($_SESSION['role'] ?? '') !== 'vendor' || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $vendorId = currentUserVendorId($conn);
    $phone = 'Not available';
    $latestStatus = 'No application';
    $latestLicenseType = 'Not available';
    $selectedZone = 'Not selected';
    $activeLicenses = 0;
    $registeredLocations = 0;
    $pendingApplications = 0;
    $remainingDays = 0;
    $percentage = 0;
    $expiryDate = 'Not available';

    if ($vendorId > 0) {
        $stmt = $conn->prepare('SELECT phone FROM vendors WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $vendor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $phone = trim((string) ($vendor['phone'] ?? '')) ?: 'Not available';

        $stmt = $conn->prepare("
            SELECT l.status,
                   COALESCE(l.business_type, l.license_type, l.business_name) AS business_type,
                   z.zone_name
            FROM licenses l
            LEFT JOIN zones z ON z.id = l.zone_id
            WHERE l.vendor_id = ?
            ORDER BY COALESCE(l.applied_at, l.created_at) DESC, l.id DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $latest = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($latest) {
            $latestStatus = ucfirst((string) $latest['status']);
            $latestLicenseType = $latest['business_type'] ?: 'Not available';
            $selectedZone = $latest['zone_name'] ?: 'Not selected';
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM licenses WHERE vendor_id = ? AND status = 'approved'");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $activeLicenses = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM licenses WHERE vendor_id = ? AND status = 'pending'");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $pendingApplications = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM locations WHERE vendor_id = ? AND is_active = 1');
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $registeredLocations = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT expiry_date
            FROM licenses
            WHERE vendor_id = ? AND status = 'approved' AND expiry_date IS NOT NULL
            ORDER BY expiry_date DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        $exp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($exp['expiry_date'])) {
            $expiry = new DateTime((string) $exp['expiry_date']);
            $now = new DateTime();
            $remainingDays = $expiry > $now ? $expiry->diff($now)->days : 0;
            $percentage = min(100, round(($remainingDays / 365) * 100));
            $expiryDate = $expiry->format('M d, Y');
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'vendorId' => $vendorId,
            'phone' => $phone,
            'zoneName' => $selectedZone,
            'selectedZone' => $selectedZone,
            'activeLicenses' => $activeLicenses,
            'registeredLocations' => $registeredLocations,
            'pendingApplications' => $pendingApplications,
            'latestStatus' => $latestStatus,
            'latestLicenseType' => $latestLicenseType,
            'percentage' => $percentage,
            'remainingDays' => $remainingDays,
            'expiryDate' => $expiryDate,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
