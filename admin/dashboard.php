<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /street_vendor/login.php');
    exit();
}

$name = $_SESSION['name'] ?? 'System Admin';

$pageTitle = 'Admin Dashboard';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div id="admin-wrapper">
    <!-- Sidebar -->
    <aside id="sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 class="text-primary fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Admin Portal</small>
        </div>
        
        <nav class="d-flex flex-column mt-4">
            <a href="/street_vendor/admin/dashboard.php" class="sidebar-link active">
                <i class='bx bxs-dashboard'></i> Dashboard
            </a>
            <a href="/street_vendor/admin/vendors.php" class="sidebar-link">
                <i class='bx bxs-group'></i> Vendors
            </a>
            <a href="/street_vendor/admin/licenses.php" class="sidebar-link">
                <i class='bx bxs-id-card'></i> Licenses
            </a>
            <a href="/street_vendor/admin/zones.php" class="sidebar-link">
                <i class='bx bx-map-alt'></i> Zones & Map
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div id="page-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center">
                <h5 class="mb-0 fw-semibold text-foreground">Dashboard Overview</h5>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle">
                    <i class='bx bx-moon'></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle border-border" type="button" data-bs-toggle="dropdown">
                        <i class='bx bxs-user-circle'></i> <?php echo htmlspecialchars($name); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                        <li><a class="dropdown-item text-foreground" href="#">Settings</a></li>
                        <li><hr class="dropdown-divider border-border"></li>
                        <li><a class="dropdown-item text-danger" href="/street_vendor/auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Container -->
        <main class="container-fluid p-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($name); ?></h2>
                    <p class="text-muted-foreground">Here's what's happening in the vendor network today.</p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title text-muted-foreground mb-0">Total Vendors</h6>
                                <i class='bx bxs-group text-primary fs-4'></i>
                            </div>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-vendors"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title text-muted-foreground mb-0">Active Licenses</h6>
                                <i class='bx bxs-id-card text-success fs-4'></i>
                            </div>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-licenses"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title text-muted-foreground mb-0">Zone Occupancy</h6>
                                <i class='bx bx-pie-chart-alt text-warning fs-4'></i>
                            </div>
                            <h2 class="fw-bold text-foreground mb-0"><span id="stat-occupancy"><span class="skeleton-text"></span></span>%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title text-muted-foreground mb-0">Pending Alerts</h6>
                                <i class='bx bxs-bell-ring text-danger fs-4'></i>
                            </div>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-renewals"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Data Table Chart Widget (User requested style) -->
                <div class="col-lg-4">
                    <div class="stat-widget-card shadow-sm">
                        <div class="stat-widget-header">
                            <span class="stat-widget-title">Applications</span>
                            <button class="stat-widget-full-stats-btn" type="button" onclick="window.location.href='/street_vendor/admin/licenses.php'">Full stats-&gt;</button>
                        </div>

                        <div class="stat-widget-range">
                            <span class="stat-widget-range-value" id="stat-pending-applications"><span class="skeleton-text"></span></span>
                            <span class="stat-widget-range-unit">pending</span>
                        </div>

                        <div class="stat-widget-date-range" id="liveDateWidget">Loading...</div>

                        <div class="stat-widget-chart-container">
                            <div class="stat-widget-avg-line"></div>
                            <div class="stat-widget-avg-label" id="stat-applications-average">Avg. 0</div>
                            <div class="stat-widget-chart" id="applicationsTrendChart">
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:32px;margin-bottom:8px;"></div></div>
                                    <div class="stat-widget-day-label">Mo</div>
                                </div>
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:44px;margin-bottom:14px;"><div class="stat-widget-dot stat-widget-dot-top"></div></div></div>
                                    <div class="stat-widget-day-label">Tu</div>
                                </div>
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:25px;margin-bottom:12px;"><div class="stat-widget-dot stat-widget-dot-bottom"></div></div></div>
                                    <div class="stat-widget-day-label">We</div>
                                </div>
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:32px;margin-bottom:10px;"></div></div>
                                    <div class="stat-widget-day-label">Th</div>
                                </div>
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:44px;margin-bottom:14px;"><div class="stat-widget-dot stat-widget-dot-bottom"></div></div></div>
                                    <div class="stat-widget-day-label">Fr</div>
                                </div>
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:38px;margin-bottom:12px;"></div></div>
                                    <div class="stat-widget-day-label">Sa</div>
                                </div>
                                <div class="stat-widget-bar-wrapper">
                                    <div class="stat-widget-bar-container"><div class="stat-widget-bar" style="height:28px;margin-bottom:13px;"><div class="stat-widget-dot stat-widget-dot-top"></div><div class="stat-widget-dot stat-widget-dot-bottom"></div></div></div>
                                    <div class="stat-widget-day-label">Su</div>
                                </div>
                            </div>
                        </div>

                        <div class="stat-widget-readings">
                            <div class="stat-widget-reading">
                                <span class="stat-widget-reading-time">Total Applications</span>
                                <span class="stat-widget-reading-value">₹<span id="stat-revenue"><span class="skeleton-text"></span></span></span>
                            </div>
                            <div class="stat-widget-reading">
                                <span class="stat-widget-reading-time">Approved Applications</span>
                                <span class="stat-widget-reading-value text-success" id="stat-approved-applications"><span class="skeleton-text"></span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity / Info -->
                <div class="col-lg-8">
                    <div class="card bg-card border-border shadow-sm h-100">
                        <div class="card-header bg-transparent border-border">
                            <h6 class="fw-bold mb-0 text-foreground">Recent Actions & Quick Links</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex gap-3 flex-wrap">
                                <a href="/street_vendor/admin/vendors.php" class="btn btn-primary px-4 py-2 fw-semibold">
                                    <i class='bx bxs-user-plus'></i> Add Vendor
                                </a>
                                <a href="/street_vendor/admin/licenses.php" class="btn btn-outline-secondary px-4 py-2 border-border fw-semibold">
                                    <i class='bx bx-check-shield'></i> Verify Licenses
                                </a>
                            </div>

                            <div class="mt-5 p-4 bg-muted rounded border border-border text-center">
                                <div class="spinner-container mb-3" style="width: 30px; height: 30px;">
                                    <div class="spinner-bars text-primary">
                                        <div class="spinner-bar" style="animation-delay: -1.2s; transform: rotate(0deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -1.1s; transform: rotate(30deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -1.0s; transform: rotate(60deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.9s; transform: rotate(90deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.8s; transform: rotate(120deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.7s; transform: rotate(150deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.6s; transform: rotate(180deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.5s; transform: rotate(210deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.4s; transform: rotate(240deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.3s; transform: rotate(270deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.2s; transform: rotate(300deg) translate(146%);"></div>
                                        <div class="spinner-bar" style="animation-delay: -0.1s; transform: rotate(330deg) translate(146%);"></div>
                                    </div>
                                </div>
                                <h6 class="text-foreground fw-bold" id="syncStatusTitle">Live System Sync Active</h6>
                                <p class="text-muted-foreground small mb-1">Dashboard metrics auto-refresh from the backend every 30 seconds.</p>
                                <p class="text-muted-foreground small mb-0">Last updated: <strong id="lastUpdatedAt">Not synced yet</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
// Theme Toggle Logic
document.getElementById('theme-toggle').addEventListener('click', () => {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('adminTheme', newTheme);
    
    const icon = document.querySelector('#theme-toggle i');
    icon.className = newTheme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
});

// Update icon on load
if(document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle i').className = 'bx bx-sun';
}

// Live date for the widget
const now = new Date();
const dateStr = new Intl.DateTimeFormat('en-IN', {
    timeZone: 'Asia/Kolkata', day: '2-digit', month: 'short', year: 'numeric'
}).format(now);
document.getElementById('liveDateWidget').textContent = `As of ${dateStr}`;

function renderApplicationsTrend(trend) {
    const chart = document.getElementById('applicationsTrendChart');
    if (!chart) return;

    if (!trend.length) {
        chart.innerHTML = '<div class="text-muted-foreground small">No application data</div>';
        return;
    }

    const maxCount = Math.max(...trend.map(item => parseInt(item.count) || 0), 1);
    chart.innerHTML = trend.map(item => {
        const count = parseInt(item.count) || 0;
        const height = Math.max(10, Math.round((count / maxCount) * 56));
        const label = (item.label || '').slice(0, 2);
        return `
            <div class="stat-widget-bar-wrapper" title="${count} applications">
                <div class="stat-widget-bar-container">
                    <div class="stat-widget-bar" style="height:${height}px;margin-bottom:8px;">
                        ${count > 0 ? '<div class="stat-widget-dot stat-widget-dot-top"></div>' : ''}
                    </div>
                </div>
                <div class="stat-widget-day-label">${label}</div>
            </div>
        `;
    }).join('');
}

let dashboardRefreshInProgress = false;
let dashboardRefreshTimer = null;

function formatSyncTime(date) {
    return new Intl.DateTimeFormat('en-IN', {
        timeZone: 'Asia/Kolkata',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    }).format(date);
}

function setSyncStatus(title, isError = false) {
    const titleEl = document.getElementById('syncStatusTitle');
    if (!titleEl) return;
    titleEl.textContent = title;
    titleEl.classList.toggle('text-danger', isError);
}

function updateDashboardMetrics(data) {
    document.getElementById('stat-vendors').textContent = new Intl.NumberFormat('en-IN').format(data.totalVendors);
    document.getElementById('stat-licenses').textContent = new Intl.NumberFormat('en-IN').format(data.totalLicenses);
    document.getElementById('stat-occupancy').textContent = data.zoneOccupancy;
    document.getElementById('stat-renewals').textContent = data.renewalAlerts;
    document.getElementById('stat-pending-applications').textContent = new Intl.NumberFormat('en-IN').format(data.pendingApplications);

    const legacyRevenue = document.getElementById('stat-revenue');
    if (legacyRevenue && legacyRevenue.parentElement) {
        legacyRevenue.parentElement.outerHTML = '<span class="stat-widget-reading-value" id="stat-total-applications"></span>';
    }
    document.getElementById('stat-total-applications').textContent = new Intl.NumberFormat('en-IN').format(data.totalApplications);
    document.getElementById('stat-approved-applications').textContent = new Intl.NumberFormat('en-IN').format(data.totalLicenses);
    document.getElementById('stat-applications-average').textContent = 'Avg. ' + data.averageDailyApplications;
    renderApplicationsTrend(data.weeklyApplications || []);
}

function refreshDashboardMetrics() {
    if (dashboardRefreshInProgress) return;
    dashboardRefreshInProgress = true;
    setSyncStatus('Syncing dashboard data...');

    fetch('/street_vendor/api/get_admin_dashboard.php', { cache: 'no-store' })
        .then(response => response.json())
        .then(res => {
            if (!res.success || !res.data) {
                throw new Error(res.error || 'Failed to fetch dashboard metrics.');
            }
            updateDashboardMetrics(res.data);
            document.getElementById('lastUpdatedAt').textContent = formatSyncTime(new Date());
            setSyncStatus('Live System Sync Active');
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
            setSyncStatus('Sync Failed', true);
            if (document.getElementById('lastUpdatedAt').textContent === 'Not synced yet') {
                document.querySelectorAll('.skeleton-text').forEach(el => el.textContent = 'Error');
            }
        })
        .finally(() => {
            dashboardRefreshInProgress = false;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    refreshDashboardMetrics();
    dashboardRefreshTimer = window.setInterval(refreshDashboardMetrics, 30000);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
