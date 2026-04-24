<?php
/**
 * Admin - Manage Licenses
 * Advanced Professional License Management Page
 */

session_start();
require_once __DIR__ . '/../includes/db.php';

/* Admin Authentication Check */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}

/* Search and filters */
$qVendor = trim($_GET['q_vendor'] ?? '');
$qLicense = trim($_GET['q_license'] ?? '');
$qStatus = trim($_GET['status'] ?? '');
$qZone = trim($_GET['zone'] ?? '');
$qFrom = trim($_GET['from'] ?? '');
$qTo = trim($_GET['to'] ?? '');

$where = [];

if ($qVendor !== '') {
    $safeVendor = $conn->real_escape_string($qVendor);
    $where[] = "u.name LIKE '%{$safeVendor}%'";
}
if ($qLicense !== '') {
    $safeLicense = $conn->real_escape_string($qLicense);
    $where[] = "(l.license_number LIKE '%{$safeLicense}%' OR CAST(l.id AS CHAR) LIKE '%{$safeLicense}%')";
}
if ($qStatus !== '' && in_array($qStatus, ['pending', 'approved', 'rejected', 'expired'], true)) {
    $safeStatus = $conn->real_escape_string($qStatus);
    $where[] = "l.status = '{$safeStatus}'";
}
if ($qZone !== '') {
    $safeZone = $conn->real_escape_string($qZone);
    $where[] = "z.zone_name = '{$safeZone}'";
}
if ($qFrom !== '') {
    $safeFrom = $conn->real_escape_string($qFrom);
    $where[] = "l.applied_date >= '{$safeFrom}'";
}
if ($qTo !== '') {
    $safeTo = $conn->real_escape_string($qTo);
    $where[] = "l.applied_date <= '{$safeTo}'";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* Fetch filtered licenses */
$query = "
    SELECT l.id,
           l.vendor_id,
           l.license_number,
           l.status,
           l.applied_date,
           l.issue_date,
           l.expiry_date,
           l.remarks,
           l.created_at,
           u.name AS vendor_name,
           u.email AS vendor_email,
           v.phone,
           COALESCE(z.zone_name, 'Not Assigned') AS zone_name,
           COALESCE(loc.spot_number, 'N/A') AS spot_number,
           CASE
               WHEN COALESCE(loc.spot_number, '') LIKE 'F%' THEN 'Food Stall'
               WHEN COALESCE(loc.spot_number, '') LIKE 'V%' THEN 'Vegetable Stall'
               WHEN COALESCE(loc.spot_number, '') LIKE 'M%' THEN 'Mobile Stall'
               ELSE 'General Stall'
           END AS stall_type
    FROM licenses l
    JOIN vendors v ON l.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    LEFT JOIN locations loc ON loc.vendor_id = v.id AND loc.is_active = 1
    LEFT JOIN zones z ON z.id = loc.zone_id
    {$whereSql}
    ORDER BY
        CASE l.status
            WHEN 'pending' THEN 0
            WHEN 'approved' THEN 1
            WHEN 'rejected' THEN 2
            WHEN 'expired' THEN 3
        END,
        l.created_at DESC
";

$result = $conn->query($query);
$licenses = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

/* Zone options for filter */
$zoneResult = $conn->query("SELECT zone_name FROM zones ORDER BY zone_name ASC");
$zones = $zoneResult ? $zoneResult->fetch_all(MYSQLI_ASSOC) : [];

/* Status counts and overview */
$statsResult = $conn->query("SELECT status, COUNT(*) AS c FROM licenses GROUP BY status");
$allStats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'expired' => 0,
];

if ($statsResult) {
    while ($row = $statsResult->fetch_assoc()) {
        if (isset($allStats[$row['status']])) {
            $allStats[$row['status']] = (int) $row['c'];
        }
    }
}

$totalLicenses = array_sum($allStats);
$pendingCount = $allStats['pending'];
$approvedCount = $allStats['approved'];
$rejectedCount = $allStats['rejected'];
$expiredCount = $allStats['expired'];

$renewalResult = $conn->query("SELECT COUNT(*) AS c FROM licenses WHERE status='approved' AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)");
$renewalCount = $renewalResult ? (int) ($renewalResult->fetch_assoc()['c'] ?? 0) : 0;

$pageTitle = 'License Management Portal';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<style>
.licenses-shell {
    background: rgba(238, 234, 229, 0.93);
    border: 1px solid rgba(186, 178, 171, 0.46);
    border-radius: 34px;
    box-shadow: 0 22px 52px rgba(50, 46, 41, 0.18);
    padding: 28px;
    animation: pageFadeIn 0.6s ease;
}

.license-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    background: linear-gradient(135deg, #f3f0ec 0%, #e7e2dc 100%);
    border: 1px solid #cdc5bc;
    border-radius: 28px;
    padding: 24px 28px;
    margin-bottom: 24px;
    box-shadow: 0 10px 32px rgba(80, 72, 64, 0.14);
}

.license-hero h2 {
    margin: 0;
    font-size: 1.95rem;
    color: #2d2a27;
    letter-spacing: -0.3px;
    font-weight: 800;
}

.license-hero p {
    margin: 6px 0 0;
    color: #68605a;
    font-weight: 500;
}

.hero-badge {
    padding: 12px 18px;
    border-radius: 999px;
    background: #ebe6df;
    border: 1px solid #c7beb4;
    color: #413c37;
    font-size: 0.8rem;
    letter-spacing: 0.09em;
    font-weight: 700;
    text-transform: uppercase;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(165px, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    position: relative;
    overflow: hidden;
    background: linear-gradient(150deg, #f7f4f0 0%, #ece7e1 100%);
    border: 1px solid #cbc2b8;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 10px 24px rgba(71, 64, 57, 0.12);
    transition: transform 0.28s ease, box-shadow 0.28s ease;
}

.stat-card::after {
    content: '';
    position: absolute;
    right: -24px;
    top: -24px;
    width: 94px;
    height: 94px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(175, 166, 157, 0.3) 0%, rgba(175, 166, 157, 0) 70%);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 28px rgba(62, 54, 48, 0.18);
}

.stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6e655e;
    font-weight: 700;
}

.stat-value {
    font-size: 2rem;
    line-height: 1;
    margin-top: 10px;
    font-weight: 800;
    color: #2f2b27;
}

.stat-sub {
    margin-top: 8px;
    color: #7b726b;
    font-size: 0.82rem;
    font-weight: 500;
}

.widget-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.widget-panel {
    background: linear-gradient(135deg, #f5f2ee 0%, #e9e5df 100%);
    border: 1px solid #cec5bc;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 10px 24px rgba(79, 71, 63, 0.12);
}

.widget-panel h4 {
    margin: 0 0 10px;
    color: #2f2a25;
    font-size: 1rem;
    font-weight: 700;
}

.widget-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.pill-btn {
    border: 1px solid #c8beb4;
    border-radius: 999px;
    background: #f2eeea;
    color: #3c3732;
    padding: 8px 14px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.25s ease;
}

.pill-btn:hover {
    background: #dfd7cf;
    color: #292521;
    transform: translateY(-2px);
}

.filters-panel {
    background: linear-gradient(140deg, #f3f0ec 0%, #e7e2dc 100%);
    border: 1px solid #cbc2b8;
    border-radius: 24px;
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: 0 10px 24px rgba(74, 66, 59, 0.11);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(140px, 1fr));
    gap: 12px;
}

.filters-grid input,
.filters-grid select {
    width: 100%;
    border: 1px solid #c9c0b7;
    border-radius: 14px;
    padding: 11px 12px;
    background: #f9f7f4;
    color: #302c28;
    font-size: 0.88rem;
    font-weight: 500;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.filters-grid input:focus,
.filters-grid select:focus {
    outline: none;
    border-color: #aea499;
    box-shadow: 0 0 0 3px rgba(174, 164, 153, 0.2);
}

.filters-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}

.btn-main,
.btn-soft {
    border-radius: 999px;
    padding: 10px 16px;
    font-size: 0.82rem;
    font-weight: 700;
    border: 1px solid #bcb1a5;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s ease;
}

.btn-main {
    background: #dbd3ca;
    color: #2f2a25;
}

.btn-main:hover {
    background: #cbc1b6;
    transform: translateY(-2px);
}

.btn-soft {
    background: #f3efeb;
    color: #49433d;
}

.btn-soft:hover {
    background: #e5ddd4;
}

.table-card {
    background: linear-gradient(160deg, #f7f4f0 0%, #ece7e1 100%);
    border: 1px solid #cbc2b8;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 10px 26px rgba(73, 66, 59, 0.13);
}

.table-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.table-head h3 {
    margin: 0;
    color: #2f2a25;
    font-size: 1.1rem;
    font-weight: 800;
}

.table-note {
    color: #6f665f;
    font-size: 0.82rem;
    font-weight: 600;
}

.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1080px;
}

.data-table thead th {
    background: #ebe5de;
    color: #4d463f;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
    padding: 12px 10px;
    border-bottom: 1px solid #cbbfb3;
}

.data-table tbody td {
    background: #fdfcfb;
    color: #3f3934;
    padding: 12px 10px;
    border-bottom: 1px solid #ece4db;
    font-size: 0.88rem;
}

.data-table tbody tr {
    transition: all 0.2s ease;
}

.data-table tbody tr:hover td {
    background: #f3eee8;
    transform: translateY(-1px);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.st-pending { background: #fff4e5; color: #9a6d1d; border: 1px solid #f0d6a7; }
.st-approved { background: #eaf7ed; color: #2d7b40; border: 1px solid #b7dfc2; }
.st-rejected { background: #fdeeee; color: #a04646; border: 1px solid #e9bcbc; }
.st-expired { background: #f1efef; color: #5f5a5a; border: 1px solid #d0cbcb; }

.action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 11px;
    border-radius: 999px;
    border: 1px solid #c8beb4;
    text-decoration: none;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    transition: all 0.24s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.btn-approve { background: #e7f5ea; color: #2d7b40; border-color: #b7dfc2; }
.btn-reject { background: #fdeeee; color: #a04646; border-color: #e9bcbc; }
.btn-view { background: #ece9e4; color: #403a33; }
.btn-download { background: #f3efe9; color: #4a443e; }
.btn-renew { background: #efe8df; color: #53493f; }

.empty-msg {
    background: #f7f3ee;
    border: 1px dashed #bfb3a8;
    border-radius: 16px;
    padding: 22px;
    text-align: center;
    color: #655e57;
    font-weight: 600;
}

@keyframes pageFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 1280px) {
    .stats-grid {
        grid-template-columns: repeat(3, minmax(160px, 1fr));
    }

    .filters-grid {
        grid-template-columns: repeat(3, minmax(150px, 1fr));
    }
}

@media (max-width: 900px) {
    .widget-grid,
    .stats-grid,
    .filters-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .licenses-shell,
    .license-hero,
    .filters-panel,
    .table-card {
        border-radius: 18px;
        padding: 14px;
    }

    .widget-grid,
    .stats-grid,
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .license-hero {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../includes/flash.php'; ?>
        <header class="top-header">
            <div class="page-title">
                <h2>License Control Portal</h2>
                <p>Enterprise-grade administration for licensing, approval workflow, and compliance tracking.</p>
            </div>
        </header>

        <div class="page-content">
            <div class="licenses-shell">
                <section class="license-hero">
                    <div>
                        <h2>Government License Management</h2>
                        <p>Monitor issuance, pending verification, renewals, and policy enforcement across all vendor licenses.</p>
                    </div>
                    <div class="hero-badge">Licenses: Active Control</div>
                </section>

                <section class="stats-grid">
                    <article class="stat-card">
                        <div class="stat-label">Total Licenses</div>
                        <div class="stat-value"><?php echo $totalLicenses; ?></div>
                        <div class="stat-sub">System-wide records</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Pending Approval</div>
                        <div class="stat-value"><?php echo $pendingCount; ?></div>
                        <div class="stat-sub">Requires admin review</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Approved</div>
                        <div class="stat-value"><?php echo $approvedCount; ?></div>
                        <div class="stat-sub">Operational licenses</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Rejected</div>
                        <div class="stat-value"><?php echo $rejectedCount; ?></div>
                        <div class="stat-sub">Compliance failed</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Expired</div>
                        <div class="stat-value"><?php echo $expiredCount; ?></div>
                        <div class="stat-sub">Needs renewal action</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Renewal Requests</div>
                        <div class="stat-value"><?php echo $renewalCount; ?></div>
                        <div class="stat-sub">45-day window</div>
                    </article>
                </section>

                <section class="widget-grid">
                    <div class="widget-panel">
                        <h4>Smart Action Widgets</h4>
                        <div class="widget-row">
                            <a class="pill-btn" href="/street_vendor/admin/transactions.php">Financial Trace</a>
                            <a class="pill-btn" href="/street_vendor/admin/vendors.php">Vendor Records</a>
                        </div>
                    </div>
                    <div class="widget-panel">
                        <h4>Queue Snapshot</h4>
                        <div class="widget-row">
                            <span class="pill-btn">High Priority: <?php echo $pendingCount > 12 ? 'Yes' : 'Normal'; ?></span>
                            <span class="pill-btn">Renewal Load: <?php echo $renewalCount; ?></span>
                            <span class="pill-btn">Total Rows: <?php echo count($licenses); ?></span>
                        </div>
                    </div>
                </section>

                <section class="filters-panel">
                    <form method="GET">
                        <div class="filters-grid">
                            <input type="text" name="q_vendor" placeholder="Search vendor name" value="<?php echo htmlspecialchars($qVendor); ?>">
                            <input type="text" name="q_license" placeholder="Search license ID / number" value="<?php echo htmlspecialchars($qLicense); ?>">
                            <select name="status">
                                <option value="">All status</option>
                                <option value="pending" <?php echo $qStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $qStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $qStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="expired" <?php echo $qStatus === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                            <select name="zone">
                                <option value="">All zones</option>
                                <?php foreach ($zones as $zone): ?>
                                    <?php $zoneName = $zone['zone_name']; ?>
                                    <option value="<?php echo htmlspecialchars($zoneName); ?>" <?php echo $qZone === $zoneName ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($zoneName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="from" value="<?php echo htmlspecialchars($qFrom); ?>">
                            <input type="date" name="to" value="<?php echo htmlspecialchars($qTo); ?>">
                        </div>
                        <div class="filters-actions">
                            <button type="submit" class="btn-main">Apply Filters</button>
                            <a href="/street_vendor/admin/licenses.php" class="btn-soft">Reset</a>
                        </div>
                    </form>
                </section>

                <section class="table-card">
                    <div class="table-head">
                        <h3>License Administration Registry</h3>
                        <span class="table-note">Interactive row highlight and instant action controls enabled</span>
                    </div>

                    <?php if (empty($licenses)): ?>
                        <div class="empty-msg">No licenses match the selected filters.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>License ID</th>
                                        <th>Vendor Name</th>
                                        <th>Stall Type</th>
                                        <th>Zone</th>
                                        <th>Issue Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($licenses as $lic): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo (int) $lic['id']; ?></strong><br>
                                                <small><?php echo htmlspecialchars($lic['license_number'] ?: 'Unassigned'); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($lic['vendor_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($lic['vendor_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($lic['stall_type']); ?><br>
                                                <small>Spot: <?php echo htmlspecialchars($lic['spot_number']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($lic['zone_name']); ?></td>
                                            <td><?php echo $lic['issue_date'] ? date('d M Y', strtotime($lic['issue_date'])) : 'Not issued'; ?></td>
                                            <td><?php echo $lic['expiry_date'] ? date('d M Y', strtotime($lic['expiry_date'])) : 'Not set'; ?></td>
                                            <td>
                                                <span class="status-pill st-<?php echo htmlspecialchars($lic['status']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($lic['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-row">
                                                    <a href="approve_license.php?id=<?php echo (int) $lic['id']; ?>&action=approve" class="action-btn btn-approve">Approve</a>
                                                    <a href="approve_license.php?id=<?php echo (int) $lic['id']; ?>&action=reject" class="action-btn btn-reject">Reject</a>
                                                    <a href="licenses.php?q_license=<?php echo urlencode((string) $lic['id']); ?>" class="action-btn btn-view">View</a>
                                                    <a href="javascript:void(0)" class="action-btn btn-download" onclick="alert('Download module can be linked to PDF service.')">Download</a>
                                                    <a href="javascript:void(0)" class="action-btn btn-renew" onclick="alert('Renew workflow can be connected to renewal endpoint.')">Renew</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>