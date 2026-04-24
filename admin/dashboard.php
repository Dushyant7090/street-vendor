<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /street_vendor/login.php');
    exit();
}

$name = $_SESSION['name'] ?? 'System Admin';
$totalVendors = 12492;
$totalLicenses = 9381;
$zoneOccupancy = 78;
$dailyCollection = 256400;
$pendingComplaints = 12;
$renewalAlerts = 9;

$pageTitle = 'Admin Dashboard';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
    background: url('/street_vendor/assets/img/gov_vendor_bg_india.png') no-repeat center center fixed;
    background-size: cover;
    color: #333;
}

.dashboard-wrapper {
    max-width: 1480px;
    margin: 0 auto;
}

.top-nav-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 48px;
    padding: 18px 28px;
    background: #e3f2fd;
    border-radius: 24px;
    border: 1px solid #ddd;
    box-shadow: 0 4px 12px #ccc;
    flex-wrap: wrap;
}

.top-nav-bar a {
    padding: 10px 18px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 24px;
    color: #333;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.35s ease;
}

.top-nav-bar a:hover {
    background: #00bcd4;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px #999;
}

.top-nav-bar .logout-link {
    background: #009688;
    color: #fff;
    border-color: #009688;
}

.top-nav-bar .logout-link:hover {
    background: #00796b;
}

.dashboard-featured {
    display: grid;
    grid-template-columns: 1.2fr 1.3fr;
    gap: 32px;
    padding: 48px;
    background: #f5f5f5;
    border-radius: 64px;
    border: 1px solid #ddd;
    box-shadow: 0 4px 12px #ccc;
    align-items: start;
    animation: slideUp 0.8s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.visual-panel {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 580px;
    background: #ffffff;
    border-radius: 52px;
    border: 1px solid #ddd;
    overflow: hidden;
    box-shadow: 0 4px 12px #ccc;
}

.visual-content {
    position: relative;
    z-index: 2;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 40px;
}

.vendor-icon {
    width: 120px;
    height: 120px;
    background: #00bcd4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    box-shadow: 0 4px 12px #999;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-12px); }
}

.visual-text h3 {
    margin: 0;
    font-size: 1.6rem;
    color: #1e3a8a;
    font-weight: 700;
}

.visual-text p {
    margin: 0;
    color: #333;
    font-size: 0.95rem;
    line-height: 1.6;
    max-width: 280px;
}

.dashboard-panel {
    display: flex;
    flex-direction: column;
    gap: 28px;
}

.panel-header {
    display: grid;
    gap: 12px;
}

.panel-label {
    display: inline-flex;
    width: fit-content;
    padding: 8px 16px;
    background: #e0e0e0;
    border-radius: 20px;
    color: #333;
    font-weight: 700;
    font-size: 0.8rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.panel-title {
    font-size: 2.8rem;
    font-weight: 800;
    color: #1e3a8a;
    line-height: 1.1;
    letter-spacing: -0.02em;
    margin: 0;
}

.metrics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 16px;
}

.metric-card {
    padding: 20px 22px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 24px;
    box-shadow: 0 4px 12px #ccc;
    transition: all 0.35s ease;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px #999;
}

.metric-card .metric-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e3a8a;
    margin: 0 0 6px;
}

.metric-card .metric-label {
    font-size: 0.85rem;
    color: #333;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 600;
}

.status-section {
    padding: 22px 24px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 32px;
    display: grid;
    gap: 14px;
    box-shadow: 0 4px 12px #ccc;
}

.status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}

.status-value {
    color: #666;
    font-size: 0.9rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #e0e0e0;
    border-radius: 16px;
    color: #333;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    box-shadow: 0 0 8px #28a745;
}

.time-section {
    padding: 18px 20px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 28px;
    text-align: center;
    display: grid;
    gap: 6px;
    box-shadow: 0 4px 12px #ccc;
}

.time-display {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1e3a8a;
}

.date-display {
    font-size: 0.9rem;
    color: #333;
    font-weight: 600;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-top: 8px;
}

.action-btn {
    padding: 16px 20px;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 24px;
    color: #333;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.35s ease;
    text-align: center;
}

.action-btn:hover {
    background: #00bcd4;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px #999;
}

@media (max-width: 1024px) {
    .dashboard-featured {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    .visual-panel {
        min-height: 380px;
    }
}

@media (max-width: 768px) {
    .dashboard-featured {
        padding: 32px 24px;
        border-radius: 48px;
    }
    .top-nav-bar { gap: 10px; }
    .top-nav-bar a { font-size: 0.85rem; padding: 8px 14px; }
    .panel-title { font-size: 2rem; }
    .metrics-grid { grid-template-columns: 1fr; }
    .quick-actions { grid-template-columns: 1fr; }
}
</style>

<div class="dashboard-wrapper">
    <nav class="top-nav-bar">
        <a href="/street_vendor/admin/dashboard.php">Dashboard</a>
        <a href="/street_vendor/admin/vendors.php">Vendors</a>
        <a href="/street_vendor/admin/locations.php">Locations</a>
        <a href="/street_vendor/admin/licenses.php">Licenses</a>
        <a href="/street_vendor/admin/zones.php">Zones</a>
        <a href="/street_vendor/admin/transactions.php">Transactions</a>
        <a href="/street_vendor/admin/dashboard.php">Settings</a>
        <a href="/street_vendor/auth/logout.php" class="logout-link">Logout</a>
    </nav>

    <div class="dashboard-featured">
        <div class="visual-panel">
            <div class="visual-content">
                <div class="vendor-icon">🏪</div>
                <div class="visual-text">
                    <h3>Smart City Vendor Network</h3>
                    <p>Gadag street vendor governance platform for civic excellence</p>
                </div>
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="panel-header">
                <span class="panel-label">Command Center</span>
                <h1 class="panel-title">GADAG STREET VENDOR SYSTEM</h1>
            </div>

            <div class="metrics-grid">
                <article class="metric-card">
                    <div class="metric-value"><?php echo number_format($totalVendors); ?></div>
                    <div class="metric-label">Total Vendors</div>
                </article>
                <article class="metric-card">
                    <div class="metric-value"><?php echo number_format($totalLicenses); ?></div>
                    <div class="metric-label">Active Licenses</div>
                </article>
                <article class="metric-card">
                    <div class="metric-value"><?php echo $zoneOccupancy; ?>%</div>
                    <div class="metric-label">Zone Occupancy</div>
                </article>
                <article class="metric-card">
                    <div class="metric-value">₹<?php echo number_format(intval($dailyCollection / 100000)); ?>L</div>
                    <div class="metric-label">Daily Revenue</div>
                </article>
            </div>

            <div class="status-section">
                <div class="status-item">
                    <span class="status-label">System Status</span>
                    <span class="status-badge"><span class="status-dot"></span>Operational</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Pending Alerts</span>
                    <span class="status-value"><?php echo $renewalAlerts; ?> active</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Complaints</span>
                    <span class="status-value"><?php echo $pendingComplaints; ?> pending</span>
                </div>
            </div>

            <div class="time-section">
                <div class="time-display" id="liveTime">00:00:00</div>
                <div class="date-display" id="liveDate">Loading...</div>
            </div>

            <div class="quick-actions">
                <a href="/street_vendor/admin/vendors.php" class="action-btn">Manage Vendors</a>
                <a href="/street_vendor/admin/licenses.php" class="action-btn">View Licenses</a>
            </div>
        </div>
    </div>
</div>

<script>
function updateLiveTime() {
    const now = new Date();
    const istTime = new Intl.DateTimeFormat('en-IN', {
        timeZone: 'Asia/Kolkata',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    }).format(now);
    
    const istDate = new Intl.DateTimeFormat('en-IN', {
        timeZone: 'Asia/Kolkata',
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    }).format(now);
    
    document.getElementById('liveTime').textContent = istTime;
    document.getElementById('liveDate').textContent = istDate + ' IST';
}

updateLiveTime();
setInterval(updateLiveTime, 1000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
