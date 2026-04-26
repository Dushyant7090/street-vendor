<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireVendor();

$userId = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Vendor User';

$stmt = $conn->prepare("
    SELECT loc.latitude,
           loc.longitude,
           loc.spot_number,
           COALESCE(loc.assigned_at, CONCAT(loc.allocated_date, ' 00:00:00')) AS assigned_at,
           z.zone_name,
           COALESCE(z.geometry_json, z.geometry) AS geometry_json,
           lic.license_number
    FROM vendors v
    JOIN locations loc ON loc.vendor_id = v.id AND loc.is_active = 1
    JOIN zones z ON z.id = loc.zone_id
    LEFT JOIN licenses lic ON lic.id = loc.application_id
    WHERE v.user_id = ? AND (lic.status = 'approved' OR loc.application_id IS NULL)
    ORDER BY loc.assigned_at DESC, loc.id DESC
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle = 'My Location';
$vendorPage = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div id="vendor-wrapper">
    <aside id="vendor-sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 style="color:hsl(var(--primary))" class="fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Vendor Portal</small>
        </div>
        <nav class="d-flex flex-column mt-3">
            <a href="/street_vendor/vendor/dashboard.php" class="vendor-sidebar-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="/street_vendor/vendor/available_zones.php" class="vendor-sidebar-link"><i class='bx bx-map-alt'></i> Available Zones</a>
            <a href="/street_vendor/vendor/apply_license.php" class="vendor-sidebar-link"><i class='bx bxs-file-plus'></i> Apply for License</a>
            <a href="/street_vendor/vendor/my_licenses.php" class="vendor-sidebar-link"><i class='bx bxs-id-card'></i> My Licenses</a>
            <a href="/street_vendor/vendor/location.php" class="vendor-sidebar-link active"><i class='bx bxs-map'></i> My Location</a>
            <a href="/street_vendor/vendor/profile.php" class="vendor-sidebar-link"><i class='bx bxs-user-detail'></i> My Profile</a>
            <div class="mt-auto px-3 pt-4">
                <a href="/street_vendor/auth/logout.php" class="vendor-sidebar-link text-danger"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </nav>
    </aside>

    <div id="vendor-page-content">
        <header class="vendor-top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">My Location</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle"><i class='bx bx-moon'></i></button>
                <span class="fw-semibold text-foreground"><?php echo htmlspecialchars($name); ?></span>
            </div>
        </header>

        <main class="container-fluid p-4">
            <?php if (!$location): ?>
                <div class="card bg-card border-border shadow-sm">
                    <div class="card-body text-center p-5">
                        <i class='bx bxs-map text-muted-foreground' style="font-size:3rem"></i>
                        <h5 class="fw-bold mt-3 text-foreground">No Location Assigned Yet</h5>
                        <p class="text-muted-foreground">Your location will be visible after admin approval and allocation.</p>
                        <a href="/street_vendor/vendor/my_licenses.php" class="btn btn-outline-secondary border-border">View Application Status</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card bg-card border-border shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="fw-bold text-foreground mb-4">Assigned Location</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                        <span class="text-muted-foreground">Zone</span>
                                        <span class="fw-bold text-foreground"><?php echo htmlspecialchars($location['zone_name']); ?></span>
                                    </li>
                                    <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                        <span class="text-muted-foreground">Spot Number</span>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($location['spot_number'] ?: 'N/A'); ?></span>
                                    </li>
                                    <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                        <span class="text-muted-foreground">Latitude</span>
                                        <span class="fw-semibold"><?php echo htmlspecialchars((string) $location['latitude']); ?></span>
                                    </li>
                                    <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                        <span class="text-muted-foreground">Longitude</span>
                                        <span class="fw-semibold"><?php echo htmlspecialchars((string) $location['longitude']); ?></span>
                                    </li>
                                    <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                        <span class="text-muted-foreground">Assigned</span>
                                        <span class="fw-semibold"><?php echo $location['assigned_at'] ? date('M d, Y', strtotime($location['assigned_at'])) : 'N/A'; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card bg-card border-border shadow-sm">
                            <div class="card-body p-0" style="overflow:hidden;border-radius:var(--radius)">
                                <div id="vendor-map" style="height:520px;width:100%"></div>
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
document.getElementById('theme-toggle').addEventListener('click', () => {
    const html = document.documentElement;
    const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('vendorTheme', next);
    document.querySelector('#theme-toggle i').className = next === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
});
if (document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle i').className = 'bx bx-sun';
}

<?php if ($location): ?>
const lat = <?php echo (float) $location['latitude']; ?>;
const lng = <?php echo (float) $location['longitude']; ?>;
const map = L.map('vendor-map').setView([lat, lng], 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

<?php if (!empty($location['geometry_json'])): ?>
const zoneGeo = <?php echo $location['geometry_json']; ?>;
const polygon = L.geoJSON(zoneGeo, {
    style: { color: '#22c55e', fillColor: '#22c55e', weight: 2, fillOpacity: 0.18 }
}).addTo(map);
<?php endif; ?>

L.marker([lat, lng]).addTo(map)
    .bindPopup('<strong>Your assigned spot</strong><br><?php echo htmlspecialchars($location['zone_name']); ?> - Spot <?php echo htmlspecialchars($location['spot_number'] ?: 'N/A'); ?>')
    .openPopup();
<?php endif; ?>
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
