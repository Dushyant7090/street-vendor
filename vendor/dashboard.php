<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor' || empty($_SESSION['user_id'])) {
    header('Location: /street_vendor/login.php');
    exit();
}

$name = $_SESSION['name'] ?? 'Vendor User';
$pageTitle = 'Vendor Dashboard';
$vendorPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div id="vendor-wrapper">
    <!-- Sidebar -->
    <aside id="vendor-sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 style="color:hsl(var(--primary))" class="fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Vendor Portal</small>
        </div>
        <nav class="d-flex flex-column mt-3">
            <a href="/street_vendor/vendor/dashboard.php" class="vendor-sidebar-link active">
                <i class='bx bxs-dashboard'></i> Dashboard
            </a>
            <a href="/street_vendor/vendor/profile.php" class="vendor-sidebar-link">
                <i class='bx bxs-user-detail'></i> My Profile
            </a>
            <a href="/street_vendor/vendor/available_zones.php" class="vendor-sidebar-link">
                <i class='bx bx-map-alt'></i> Available Zones
            </a>
            <a href="/street_vendor/vendor/apply_license.php" class="vendor-sidebar-link">
                <i class='bx bxs-file-plus'></i> Apply for License
            </a>
            <a href="/street_vendor/vendor/my_licenses.php" class="vendor-sidebar-link">
                <i class='bx bxs-id-card'></i> My Licenses
            </a>
            <a href="/street_vendor/vendor/location.php" class="vendor-sidebar-link">
                <i class='bx bxs-map'></i> My Location
            </a>
            <div class="mt-auto px-3 pt-4">
                <a href="/street_vendor/auth/logout.php" class="vendor-sidebar-link text-danger">
                    <i class='bx bx-log-out'></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div id="vendor-page-content">
        <header class="vendor-top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">Dashboard</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle">
                    <i class='bx bx-moon'></i>
                </button>
                <span class="fw-semibold text-foreground"><i class='bx bxs-user-circle'></i> <?php echo htmlspecialchars($name); ?></span>
            </div>
        </header>

        <main class="container-fluid p-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($name); ?></h2>
                    <p class="text-muted-foreground">Here's your vendor activity overview.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted-foreground mb-0">Application Status</h6>
                                <i class='bx bxs-file text-primary fs-4'></i>
                            </div>
                            <h3 class="fw-bold mb-0" id="stat-status"><span class="skeleton-text"></span></h3>
                            <small class="text-muted-foreground" id="stat-license-type"></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted-foreground mb-0">Active Licenses</h6>
                                <i class='bx bxs-check-shield text-success fs-4'></i>
                            </div>
                            <h3 class="fw-bold mb-0" id="stat-licenses"><span class="skeleton-text"></span></h3>
                            <small class="text-muted-foreground" id="stat-pending-label"></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted-foreground mb-0">Assigned Zone</h6>
                                <i class='bx bxs-map-pin text-warning fs-4'></i>
                            </div>
                            <h3 class="fw-bold mb-0 fs-5" id="stat-zone"><span class="skeleton-text"></span></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted-foreground mb-0">License Expiry</h6>
                                <i class='bx bxs-calendar text-info fs-4'></i>
                            </div>
                            <h3 class="fw-bold mb-1 fs-5" id="stat-expiry"><span class="skeleton-text"></span></h3>
                            <div class="progress mt-2" style="height:5px">
                                <div class="progress-bar bg-primary" id="stat-progress-bar" style="width:0%"></div>
                            </div>
                            <small class="text-muted-foreground" id="stat-remaining"></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions + Info -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4 text-foreground">Quick Actions</h5>
                            <div class="d-flex flex-column gap-3">
                                <a href="/street_vendor/vendor/apply_license.php" class="btn btn-primary fw-semibold d-flex align-items-center gap-2">
                                    <i class='bx bxs-file-plus'></i> Apply for New License
                                </a>
                                <a href="/street_vendor/vendor/available_zones.php" class="btn btn-outline-secondary border-border fw-semibold d-flex align-items-center gap-2">
                                    <i class='bx bx-map-alt'></i> View Available Zones
                                </a>
                                <a href="/street_vendor/vendor/profile.php" class="btn btn-outline-secondary border-border fw-semibold d-flex align-items-center gap-2">
                                    <i class='bx bxs-user-detail'></i> Update Profile
                                </a>
                                <a href="/street_vendor/vendor/my_licenses.php" class="btn btn-outline-secondary border-border fw-semibold d-flex align-items-center gap-2">
                                    <i class='bx bxs-id-card'></i> My Applications / My Licenses
                                </a>
                                <a href="/street_vendor/vendor/location.php" class="btn btn-outline-secondary border-border fw-semibold d-flex align-items-center gap-2">
                                    <i class='bx bxs-map'></i> Assigned Location
                                </a>
                                <a href="/street_vendor/vendor/my_licenses.php" class="btn btn-outline-secondary border-border fw-semibold d-flex align-items-center gap-2">
                                    <i class='bx bx-download'></i> Download License
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4 text-foreground">Account Information</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                    <span class="text-muted-foreground">Phone</span>
                                    <span class="fw-semibold text-foreground" id="info-phone"><span class="skeleton-text" style="width:100px"></span></span>
                                </li>
                                <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                    <span class="text-muted-foreground">Registered Locations</span>
                                    <span class="fw-semibold text-foreground" id="info-locations"><span class="skeleton-text" style="width:30px"></span></span>
                                </li>
                                <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                    <span class="text-muted-foreground">Pending Applications</span>
                                    <span class="fw-semibold text-foreground" id="info-pending"><span class="skeleton-text" style="width:30px"></span></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// Theme toggle
document.getElementById('theme-toggle').addEventListener('click', () => {
    const html = document.documentElement;
    const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('vendorTheme', next);
    document.querySelector('#theme-toggle i').className = next === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
});
if(document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle i').className = 'bx bx-sun';
}

// Fetch dashboard data
document.addEventListener('DOMContentLoaded', () => {
    fetch('/street_vendor/api/get_vendor_dashboard.php')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const d = res.data;
            document.getElementById('stat-status').textContent = d.latestStatus;
            document.getElementById('stat-license-type').textContent = d.latestLicenseType;
            document.getElementById('stat-licenses').textContent = d.activeLicenses;
            document.getElementById('stat-pending-label').textContent = d.pendingApplications + ' pending';
            document.getElementById('stat-zone').textContent = d.zoneName;
            document.getElementById('stat-expiry').textContent = d.expiryDate;
            document.getElementById('stat-progress-bar').style.width = d.percentage + '%';
            document.getElementById('stat-remaining').textContent = d.remainingDays + ' days remaining';
            document.getElementById('info-phone').textContent = d.phone;
            document.getElementById('info-locations').textContent = d.registeredLocations;
            document.getElementById('info-pending').textContent = d.pendingApplications;
        })
        .catch(err => console.error(err));
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
