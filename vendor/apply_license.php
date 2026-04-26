<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireVendor();

$userId = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Vendor User';
$vendorId = currentUserVendorId($conn);
$zoneId = (int) ($_GET['zone_id'] ?? $_POST['zone_id'] ?? 0);
$zone = $zoneId > 0 ? fetchZoneById($conn, $zoneId) : null;
$errors = [];
$success = false;

if ($vendorId <= 0) {
    $errors[] = 'Vendor profile was not found. Please contact the administrator.';
}

if ($zoneId <= 0) {
    $errors[] = 'Please choose a zone before applying.';
} elseif (!$zone) {
    $errors[] = 'Selected zone was not found.';
} else {
    $max = (int) $zone['effective_max_capacity'];
    $current = (int) $zone['effective_current_capacity'];
    if ($zone['effective_status'] !== 'available') {
        $errors[] = 'This zone is currently not available for applications.';
    } elseif ($current >= $max) {
        $errors[] = 'This zone is already full. Please choose another zone.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $businessType = trim((string) ($_POST['business_type'] ?? ''));
    $vendorCategory = trim((string) ($_POST['vendor_category'] ?? ''));
    $priorityType = trim((string) ($_POST['priority_type'] ?? ''));

    if ($businessType === '') $errors[] = 'Business type is required.';
    if ($vendorCategory === '') $errors[] = 'Vendor category is required.';
    if ($priorityType === '') $errors[] = 'Priority type is required.';

    $aadharPath = saveVendorDocument('aadhar_proof', $vendorId, $errors, true);
    $photoPath = saveVendorDocument('passport_photo', $vendorId, $errors, true);
    $businessProofPath = saveVendorDocument('business_proof', $vendorId, $errors, false);

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO licenses
                (vendor_id, zone_id, business_type, license_type, vendor_category, priority_type,
                 aadhar_path, photo_path, business_proof_path, status, applied_date, applied_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURDATE(), NOW())
        ");
        $stmt->bind_param(
            'iisssssss',
            $vendorId,
            $zoneId,
            $businessType,
            $businessType,
            $vendorCategory,
            $priorityType,
            $aadharPath,
            $photoPath,
            $businessProofPath
        );
        $stmt->execute();
        $stmt->close();
        $success = true;
    }
}

$pageTitle = 'Apply for License';
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
            <a href="/street_vendor/vendor/apply_license.php" class="vendor-sidebar-link active"><i class='bx bxs-file-plus'></i> Apply for License</a>
            <a href="/street_vendor/vendor/my_licenses.php" class="vendor-sidebar-link"><i class='bx bxs-id-card'></i> My Licenses</a>
            <a href="/street_vendor/vendor/location.php" class="vendor-sidebar-link"><i class='bx bxs-map'></i> My Location</a>
            <a href="/street_vendor/vendor/profile.php" class="vendor-sidebar-link"><i class='bx bxs-user-detail'></i> My Profile</a>
            <div class="mt-auto px-3 pt-4">
                <a href="/street_vendor/auth/logout.php" class="vendor-sidebar-link text-danger"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </nav>
    </aside>

    <div id="vendor-page-content">
        <header class="vendor-top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">Apply for License</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle"><i class='bx bx-moon'></i></button>
                <span class="fw-semibold text-foreground"><?php echo htmlspecialchars($name); ?></span>
            </div>
        </header>

        <main class="container-fluid p-4">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    Application submitted successfully. It is now pending admin review.
                    <a href="/street_vendor/vendor/my_licenses.php" class="alert-link">View status</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <?php if ($zoneId <= 0 || !$zone || ($zone && ((int)$zone['available_slots'] <= 0 || $zone['effective_status'] !== 'available'))): ?>
                        <div class="mt-2"><a href="/street_vendor/vendor/available_zones.php" class="alert-link">Choose another available zone</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($zone && !$success): ?>
                <div class="card bg-card border-border shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between gap-3">
                            <div>
                                <h4 class="fw-bold text-foreground mb-1"><?php echo htmlspecialchars($zone['zone_name']); ?></h4>
                                <p class="text-muted-foreground mb-0"><?php echo htmlspecialchars($zone['effective_description'] ?: 'No description provided.'); ?></p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap align-items-start">
                                <span class="badge bg-primary">Max: <?php echo (int) $zone['effective_max_capacity']; ?></span>
                                <span class="badge bg-secondary">Occupied: <?php echo (int) $zone['effective_current_capacity']; ?></span>
                                <span class="badge bg-success">Slots: <?php echo (int) $zone['available_slots']; ?></span>
                                <span class="badge <?php echo $zone['effective_status'] === 'available' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $zone['effective_status'] === 'available' ? 'Available' : 'Not Available'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($zone && empty($errors) && !$success): ?>
                <div class="card bg-card border-border shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold text-foreground mb-4">Application Details</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
                            <input type="hidden" name="zone_id" value="<?php echo (int) $zoneId; ?>">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted-foreground">Business Type</label>
                                    <select name="business_type" class="form-select" required>
                                        <option value="">Select type</option>
                                        <option value="Food">Food</option>
                                        <option value="Vegetables/Fruits">Vegetables/Fruits</option>
                                        <option value="Clothing">Clothing</option>
                                        <option value="Household Goods">Household Goods</option>
                                        <option value="Services">Services</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted-foreground">Vendor Category</label>
                                    <select name="vendor_category" class="form-select" required>
                                        <option value="">Select category</option>
                                        <option value="Street Vendor">Street Vendor</option>
                                        <option value="Mobile Vendor">Mobile Vendor</option>
                                        <option value="Temporary Stall">Temporary Stall</option>
                                        <option value="Daily Market Vendor">Daily Market Vendor</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted-foreground">Priority Type</label>
                                    <select name="priority_type" class="form-select" required>
                                        <option value="">Select priority</option>
                                        <option value="General">General</option>
                                        <option value="Women">Women</option>
                                        <option value="Senior Citizen">Senior Citizen</option>
                                        <option value="Disabled">Disabled</option>
                                        <option value="Existing Vendor">Existing Vendor</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted-foreground">Aadhar Proof</label>
                                    <input type="file" name="aadhar_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small class="text-muted-foreground">JPG, PNG, or PDF. Max 10 MB.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted-foreground">Passport Size Photo</label>
                                    <input type="file" name="passport_photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small class="text-muted-foreground">JPG, PNG, or PDF. Max 10 MB.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small text-muted-foreground">Business Proof <span class="text-muted">(optional)</span></label>
                                    <input type="file" name="business_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                    <small class="text-muted-foreground">Optional. Max 10 MB.</small>
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                        <i class='bx bx-send'></i> Submit Application
                                    </button>
                                    <a href="/street_vendor/vendor/available_zones.php" class="btn btn-outline-secondary border-border ms-2">Change Zone</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif (!$zone && !$success): ?>
                <div class="card bg-card border-border shadow-sm">
                    <div class="card-body text-center p-5">
                        <i class='bx bx-map-alt text-muted-foreground' style="font-size:3rem"></i>
                        <h5 class="fw-bold mt-3 text-foreground">Choose a Zone First</h5>
                        <p class="text-muted-foreground">Applications are now zone-based. Open the map and select an available zone.</p>
                        <a href="/street_vendor/vendor/available_zones.php" class="btn btn-primary fw-semibold">View Available Zones</a>
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
