<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}
/**
 * Admin - Allocate Location
 * Assign a vending spot to a vendor. Prevents duplicate spot allocation.
 */
require_once __DIR__ . '/../config/database.php';
requireAdmin();

// Get vendors without active location
$unassignedVendors = $conn->query("
    SELECT v.id as vendor_id, u.name 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id
    WHERE v.id NOT IN (SELECT vendor_id FROM locations WHERE is_active = 1)
    ORDER BY u.name
")->fetch_all(MYSQLI_ASSOC);

// Get zones with availability
$zones = $conn->query("
    SELECT z.*, 
        (SELECT COUNT(*) FROM locations WHERE zone_id = z.id AND is_active = 1) as occupied
    FROM zones z WHERE z.is_active = 1
")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorId = intval($_POST['vendor_id'] ?? 0);
    $zoneId = intval($_POST['zone_id'] ?? 0);
    $spotNumber = trim($_POST['spot_number'] ?? '');

    if (!$vendorId || !$zoneId || empty($spotNumber)) {
        setFlash('error', 'Please fill in all fields.');
    } else {
        // Check for duplicate spot in the same zone
        $stmt = $conn->prepare("SELECT id FROM locations WHERE zone_id = ? AND spot_number = ? AND is_active = 1");
        $stmt->bind_param("is", $zoneId, $spotNumber);
        $stmt->execute();
        $duplicate = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($duplicate) {
            setFlash('error', 'This spot is already allocated in the selected zone.');
        } else {
            // Check zone capacity
            $stmt = $conn->prepare("SELECT z.max_vendors, 
                (SELECT COUNT(*) FROM locations WHERE zone_id = z.id AND is_active = 1) as occupied 
                FROM zones z WHERE z.id = ?");
            $stmt->bind_param("i", $zoneId);
            $stmt->execute();
            $zoneInfo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($zoneInfo && $zoneInfo['occupied'] >= $zoneInfo['max_vendors']) {
                setFlash('error', 'This zone has reached maximum vendor capacity.');
            } else {
                $allocatedDate = date('Y-m-d');
                $stmt = $conn->prepare("INSERT INTO locations (vendor_id, zone_id, spot_number, allocated_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $vendorId, $zoneId, $spotNumber, $allocatedDate);

                if ($stmt->execute()) {
                    // Get vendor name for logging
                    $vstmt = $conn->prepare("SELECT u.name FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.id = ?");
                    $vstmt->bind_param("i", $vendorId);
                    $vstmt->execute();
                    $vName = $vstmt->get_result()->fetch_assoc()['name'] ?? 'Unknown';
                    $vstmt->close();

                    logAdminAction('Location Allocated', "Assigned Spot {$spotNumber} to vendor: {$vName}");
                    setFlash('success', 'Location allocated successfully!');
                    redirect('/street_vendor/admin/locations.php');
                } else {
                    setFlash('error', 'Failed to allocate location.');
                }
                $stmt->close();
            }
        }
    }
}

$pageTitle = 'Allocate Location';
include __DIR__ . '/../includes/header.php';
?>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="top-header">
            <div class="page-title">
                <button class="menu-toggle"><i class='bx bx-menu'></i></button>
                <h2>Allocate Location</h2>
                <p>Assign a vending spot to a vendor</p>
            </div>
        </header>

        <div class="page-content">
            <?php include __DIR__ . '/../includes/flash.php'; ?>

            <div class="card" style="max-width: 600px;">
                <div class="card-header">
                    <h3><i class='bx bxs-map-pin'></i> Assign Vendor Location</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($unassignedVendors)): ?>
                        <div class="alert-box alert-info">
                            <span class="alert-icon">ℹ️</span>
                            <div>All vendors already have active locations assigned.</div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" data-validate>
                            <div class="form-group">
                                <label for="vendor_id">Select Vendor</label>
                                <select id="vendor_id" name="vendor_id" required>
                                    <option value="">— Choose a vendor —</option>
                                    <?php foreach ($unassignedVendors as $v): ?>
                                        <option value="<?php echo $v['vendor_id']; ?>"><?php echo htmlspecialchars($v['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="zone_id">Select Zone</label>
                                <select id="zone_id" name="zone_id" required>
                                    <option value="">— Choose a zone —</option>
                                    <?php foreach ($zones as $z): ?>
                                        <option value="<?php echo $z['id']; ?>">
                                            <?php echo htmlspecialchars($z['zone_name']); ?> 
                                            (<?php echo $z['occupied']; ?>/<?php echo $z['max_vendors']; ?> occupied)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="spot_number">Spot Number</label>
                                <input type="text" id="spot_number" name="spot_number" placeholder="e.g., A-01, B-12" required>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class='bx bx-check'></i> Allocate Location
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
