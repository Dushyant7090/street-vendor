<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireAdmin();

$selectedApplicationId = (int) ($_GET['application_id'] ?? $_POST['application_id'] ?? 0);

$applications = $conn->query("
    SELECT l.id AS application_id,
           l.vendor_id,
           u.name AS vendor_name,
           COALESCE(l.business_type, l.license_type, l.business_name) AS business_type,
           z.id AS zone_id,
           z.zone_name,
           COALESCE(z.geometry_json, z.geometry) AS geometry_json
    FROM licenses l
    JOIN vendors v ON v.id = l.vendor_id
    JOIN users u ON u.id = v.user_id
    JOIN zones z ON z.id = l.zone_id
    WHERE l.status = 'approved'
      AND NOT EXISTS (SELECT 1 FROM locations loc WHERE loc.application_id = l.id AND loc.is_active = 1)
    ORDER BY l.reviewed_at DESC, l.id DESC
")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $spotNumber = trim((string) ($_POST['spot_number'] ?? ''));
    $latitude = trim((string) ($_POST['latitude'] ?? ''));
    $longitude = trim((string) ($_POST['longitude'] ?? ''));

    if ($applicationId <= 0 || $spotNumber === '' || $latitude === '' || $longitude === '') {
        setFlash('error', 'Please select an approved application, spot number, and map coordinates.');
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                SELECT l.id,
                       l.vendor_id,
                       l.zone_id,
                       u.name AS vendor_name
                FROM licenses l
                JOIN vendors v ON v.id = l.vendor_id
                JOIN users u ON u.id = v.user_id
                WHERE l.id = ? AND l.status = 'approved'
                FOR UPDATE
            ");
            $stmt->bind_param('i', $applicationId);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$app) {
                throw new RuntimeException('Approved application not found.');
            }

            $zoneId = (int) $app['zone_id'];
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
            if (!$zone || $zone['status'] !== 'available') {
                throw new RuntimeException('Requested zone is not available.');
            }

            $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM locations WHERE zone_id = ? AND is_active = 1');
            $stmt->bind_param('i', $zoneId);
            $stmt->execute();
            $current = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
            if ($current >= (int) $zone['max_capacity']) {
                throw new RuntimeException('Zone is full. Cannot assign a new spot.');
            }

            $stmt = $conn->prepare('SELECT id FROM locations WHERE zone_id = ? AND spot_number = ? AND is_active = 1 LIMIT 1');
            $stmt->bind_param('is', $zoneId, $spotNumber);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($duplicate) {
                throw new RuntimeException('This spot number is already assigned in the selected zone.');
            }

            $lat = (float) $latitude;
            $lng = (float) $longitude;
            $stmt = $conn->prepare("
                INSERT INTO locations
                    (vendor_id, application_id, zone_id, spot_number, latitude, longitude, allocated_date, assigned_at, is_active, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 1, 'active')
            ");
            $stmt->bind_param('iiisdd', $app['vendor_id'], $applicationId, $zoneId, $spotNumber, $lat, $lng);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                UPDATE zones z
                SET current_capacity = (SELECT COUNT(*) FROM locations l WHERE l.zone_id = z.id AND l.is_active = 1)
                WHERE z.id = ?
            ");
            $stmt->bind_param('i', $zoneId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            logAdminAction('Location Allocated', "Assigned spot {$spotNumber} to {$app['vendor_name']}");
            setFlash('success', 'Location allocated successfully. Zone capacity has been updated.');
            redirect('/street_vendor/admin/locations.php');
        } catch (Throwable $e) {
            $conn->rollback();
            setFlash('error', $e->getMessage());
        }
    }
}

$pageTitle = 'Allocate Location';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div id="admin-wrapper">
    <aside id="sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 class="text-primary fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Admin Portal</small>
        </div>
        <nav class="d-flex flex-column mt-4">
            <a href="/street_vendor/admin/dashboard.php" class="sidebar-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="/street_vendor/admin/licenses.php" class="sidebar-link"><i class='bx bxs-id-card'></i> Licenses</a>
            <a href="/street_vendor/admin/allocate_location.php" class="sidebar-link active"><i class='bx bxs-map-pin'></i> Allocate Location</a>
            <a href="/street_vendor/admin/locations.php" class="sidebar-link"><i class='bx bxs-map'></i> Locations</a>
            <a href="/street_vendor/admin/zones.php" class="sidebar-link"><i class='bx bx-map-alt'></i> Zones & Map</a>
        </nav>
    </aside>

    <div id="page-content">
        <header class="top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">Allocate Approved Application</h5>
            <a class="btn btn-sm btn-outline-danger border-border" href="/street_vendor/auth/logout.php">Logout</a>
        </header>

        <main class="container-fluid p-4">
            <?php include __DIR__ . '/../includes/flash.php'; ?>

            <?php if (empty($applications)): ?>
                <div class="card bg-card border-border shadow-sm">
                    <div class="card-body text-center p-5">
                        <h5 class="fw-bold text-foreground">No Approved Applications Awaiting Allocation</h5>
                        <p class="text-muted-foreground">Approve a pending application first, then return here to assign a spot.</p>
                        <a href="/street_vendor/admin/licenses.php" class="btn btn-primary">Review Applications</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card bg-card border-border shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-foreground mb-4">Assignment Details</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted-foreground">Approved Application</label>
                                        <select name="application_id" id="application_id" class="form-select" required>
                                            <option value="">Select application</option>
                                            <?php foreach ($applications as $app): ?>
                                                <option
                                                    value="<?php echo (int) $app['application_id']; ?>"
                                                    data-zone-id="<?php echo (int) $app['zone_id']; ?>"
                                                    <?php echo $selectedApplicationId === (int) $app['application_id'] ? 'selected' : ''; ?>>
                                                    #<?php echo (int) $app['application_id']; ?> - <?php echo htmlspecialchars($app['vendor_name'] . ' / ' . $app['zone_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted-foreground">Spot Number</label>
                                        <input type="text" name="spot_number" class="form-control" placeholder="e.g. A-01" required>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small text-muted-foreground">Latitude</label>
                                            <input type="text" name="latitude" id="latitude" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold small text-muted-foreground">Longitude</label>
                                            <input type="text" name="longitude" id="longitude" class="form-control" required>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary fw-semibold mt-4 w-100">Save Allocation</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card bg-card border-border shadow-sm">
                            <div class="card-body p-0" style="overflow:hidden;border-radius:var(--radius)">
                                <div id="assign-map" style="height:620px;width:100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
const applications = <?php echo json_encode($applications); ?>;
const mapEl = document.getElementById('assign-map');
let map = null;
let zoneLayer = null;
let marker = null;
if (mapEl) {
    map = L.map('assign-map').setView([15.4319, 75.6340], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
}

function selectedApp() {
    const id = document.getElementById('application_id').value;
    return applications.find(app => String(app.application_id) === String(id));
}

function renderSelectedZone() {
    if (!map) return;
    if (zoneLayer) map.removeLayer(zoneLayer);
    const app = selectedApp();
    if (!app || !app.geometry_json) return;
    let geo;
    try { geo = JSON.parse(app.geometry_json); } catch (e) { return; }
    zoneLayer = L.geoJSON(geo, { style: { color: '#22c55e', fillColor: '#22c55e', fillOpacity: 0.18, weight: 2 } }).addTo(map);
    map.fitBounds(zoneLayer.getBounds(), { padding: [30, 30] });
}

document.getElementById('application_id')?.addEventListener('change', renderSelectedZone);
if (map) map.on('click', e => {
    if (!selectedApp()) {
        alert('Select an approved application first.');
        return;
    }
    if (zoneLayer && !zoneLayer.getBounds().contains(e.latlng)) {
        if (!confirm('This point appears outside the selected zone boundary. Use it anyway?')) return;
    }
    document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
    document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
    if (marker) map.removeLayer(marker);
    marker = L.marker(e.latlng).addTo(map).bindPopup('Selected spot').openPopup();
});
document.addEventListener('DOMContentLoaded', renderSelectedZone);
</script>
</body>
</html>
