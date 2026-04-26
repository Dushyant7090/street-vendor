<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireVendor();

$userId = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Vendor User';
$vendorId = currentUserVendorId($conn);
$licenses = [];

if ($vendorId > 0) {
    $stmt = $conn->prepare("
        SELECT l.*,
               z.zone_name,
               COALESCE(l.business_type, l.license_type, l.business_name) AS display_business_type
        FROM licenses l
        LEFT JOIN zones z ON z.id = l.zone_id
        WHERE l.vendor_id = ?
        ORDER BY COALESCE(l.applied_at, l.created_at) DESC, l.id DESC
    ");
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $licenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$pageTitle = 'My Applications';
$vendorPage = true;
include __DIR__ . '/../includes/header.php';
?>

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
            <a href="/street_vendor/vendor/my_licenses.php" class="vendor-sidebar-link active"><i class='bx bxs-id-card'></i> My Licenses</a>
            <a href="/street_vendor/vendor/location.php" class="vendor-sidebar-link"><i class='bx bxs-map'></i> My Location</a>
            <a href="/street_vendor/vendor/profile.php" class="vendor-sidebar-link"><i class='bx bxs-user-detail'></i> My Profile</a>
            <div class="mt-auto px-3 pt-4">
                <a href="/street_vendor/auth/logout.php" class="vendor-sidebar-link text-danger"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </nav>
    </aside>

    <div id="vendor-page-content">
        <header class="vendor-top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">My Applications / Licenses</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle"><i class='bx bx-moon'></i></button>
                <span class="fw-semibold text-foreground"><?php echo htmlspecialchars($name); ?></span>
            </div>
        </header>

        <main class="container-fluid p-4">
            <?php if (empty($licenses)): ?>
                <div class="card bg-card border-border shadow-sm">
                    <div class="card-body text-center p-5">
                        <i class='bx bxs-id-card text-muted-foreground' style="font-size:3rem"></i>
                        <h5 class="fw-bold mt-3 text-foreground">No Applications Found</h5>
                        <p class="text-muted-foreground">Choose an available zone and submit your first application.</p>
                        <a href="/street_vendor/vendor/available_zones.php" class="btn btn-primary fw-semibold">View Available Zones</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card bg-card border-border shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle text-foreground">
                                <thead class="text-muted-foreground">
                                    <tr>
                                        <th>Application</th>
                                        <th>Selected Zone</th>
                                        <th>Business Type</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                        <th>License</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($licenses as $lic):
                                        $status = $lic['status'] ?? 'pending';
                                        $badgeClass = match ($status) {
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'expired' => 'bg-warning text-dark',
                                            default => 'bg-primary',
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary">#<?php echo (int) $lic['id']; ?></strong><br>
                                                <small class="text-muted-foreground">
                                                    <?php echo !empty($lic['applied_at']) ? date('M d, Y', strtotime($lic['applied_at'])) : (!empty($lic['applied_date']) ? date('M d, Y', strtotime($lic['applied_date'])) : 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($lic['zone_name'] ?? 'Not selected'); ?></td>
                                            <td><?php echo htmlspecialchars($lic['display_business_type'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($lic['vendor_category'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($lic['priority_type'] ?? 'N/A'); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($status); ?></span></td>
                                            <td><?php echo htmlspecialchars($lic['remarks'] ?? '-'); ?></td>
                                            <td>
                                                <?php if (!empty($lic['license_number'])): ?>
                                                    <strong><?php echo htmlspecialchars($lic['license_number']); ?></strong><br>
                                                    <small class="text-muted-foreground">
                                                        <?php echo $lic['issue_date'] ? date('M d, Y', strtotime($lic['issue_date'])) : '-'; ?>
                                                        to
                                                        <?php echo $lic['expiry_date'] ? date('M d, Y', strtotime($lic['expiry_date'])) : '-'; ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted-foreground">Pending approval</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($status === 'approved' && !empty($lic['license_number'])): ?>
                                                    <a href="/street_vendor/vendor/download_license.php?id=<?php echo (int) $lic['id']; ?>" class="btn btn-sm btn-outline-secondary border-border">
                                                        <i class='bx bx-download'></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted-foreground small">Available after approval</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

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
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
