<?php
/**
 * Admin - Location Management
 * Enterprise urban location allocation and zone control portal.
 */

session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}

/* Search and filters */
$qLocation = trim($_GET['q_location'] ?? '');
$qZone = trim($_GET['q_zone'] ?? '');
$qStatus = trim($_GET['status'] ?? '');
$qMarket = trim($_GET['market_area'] ?? '');

$where = [];

if ($qLocation !== '') {
    $safeLocation = $conn->real_escape_string($qLocation);
    $where[] = "(CAST(l.id AS CHAR) LIKE '%{$safeLocation}%' OR l.spot_number LIKE '%{$safeLocation}%')";
}
if ($qZone !== '') {
    $safeZone = $conn->real_escape_string($qZone);
    $where[] = "z.zone_name = '{$safeZone}'";
}
if ($qStatus !== '' && in_array($qStatus, ['active', 'inactive', 'unassigned'], true)) {
    if ($qStatus === 'active') {
        $where[] = "l.is_active = 1";
    } elseif ($qStatus === 'inactive') {
        $where[] = "l.is_active = 0";
    }
}
if ($qMarket !== '') {
    $safeMarket = $conn->real_escape_string($qMarket);
    $where[] = "COALESCE(z.area_description, '') LIKE '%{$safeMarket}%'";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* Location records */
$locationsSql = "
    SELECT l.id,
           l.vendor_id,
           l.zone_id,
           l.spot_number,
           l.allocated_date,
           l.is_active,
           l.created_at,
           z.zone_name,
           COALESCE(z.area_description, 'N/A') AS market_area,
           COALESCE(u.name, 'Unassigned') AS vendor_name,
           COALESCE(u.email, 'N/A') AS vendor_email
    FROM locations l
    JOIN zones z ON z.id = l.zone_id
    LEFT JOIN vendors v ON v.id = l.vendor_id
    LEFT JOIN users u ON u.id = v.user_id
    {$whereSql}
    ORDER BY l.is_active DESC, l.allocated_date DESC, l.id DESC
";

$locationsResult = $conn->query($locationsSql);
$locations = $locationsResult ? $locationsResult->fetch_all(MYSQLI_ASSOC) : [];

/* Zone list for filters */
$zonesResult = $conn->query("SELECT id, zone_name, area_description, max_vendors, is_active FROM zones ORDER BY zone_name ASC");
$zones = $zonesResult ? $zonesResult->fetch_all(MYSQLI_ASSOC) : [];

/* Occupancy by zone for map/preview */
$occupancySql = "
    SELECT z.id,
           z.zone_name,
           COALESCE(z.area_description, 'N/A') AS area_description,
           z.max_vendors,
           z.is_active,
           (SELECT COUNT(*) FROM locations l2 WHERE l2.zone_id = z.id AND l2.is_active = 1) AS occupied
    FROM zones z
    ORDER BY z.zone_name ASC
";
$occupancyResult = $conn->query($occupancySql);
$zoneOccupancy = $occupancyResult ? $occupancyResult->fetch_all(MYSQLI_ASSOC) : [];

/* Global stats */
$totalLocationsResult = $conn->query("SELECT COUNT(*) AS c FROM locations");
$totalLocations = $totalLocationsResult ? (int) ($totalLocationsResult->fetch_assoc()['c'] ?? 0) : 0;

$allocatedResult = $conn->query("SELECT COUNT(*) AS c FROM locations WHERE is_active = 1");
$allocatedStalls = $allocatedResult ? (int) ($allocatedResult->fetch_assoc()['c'] ?? 0) : 0;

$capacityResult = $conn->query("SELECT COALESCE(SUM(max_vendors), 0) AS c FROM zones WHERE is_active = 1");
$totalCapacity = $capacityResult ? (int) ($capacityResult->fetch_assoc()['c'] ?? 0) : 0;
$availableLocations = max($totalCapacity - $allocatedStalls, 0);

$pendingResult = $conn->query("SELECT COUNT(*) AS c FROM vendors v WHERE NOT EXISTS (SELECT 1 FROM locations l WHERE l.vendor_id = v.id AND l.is_active = 1)");
$pendingAllocation = $pendingResult ? (int) ($pendingResult->fetch_assoc()['c'] ?? 0) : 0;

$restrictedResult = $conn->query("SELECT COUNT(*) AS c FROM zones WHERE is_active = 0");
$restrictedZones = $restrictedResult ? (int) ($restrictedResult->fetch_assoc()['c'] ?? 0) : 0;

$activeZonesResult = $conn->query("SELECT COUNT(*) AS c FROM zones WHERE is_active = 1");
$activeZones = $activeZonesResult ? (int) ($activeZonesResult->fetch_assoc()['c'] ?? 0) : 0;

$pageTitle = 'Location Management Portal';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<style>
.locations-shell {
    background: rgba(238, 234, 229, 0.93);
    border: 1px solid rgba(186, 178, 171, 0.46);
    border-radius: 34px;
    box-shadow: 0 22px 52px rgba(50, 46, 41, 0.18);
    padding: 28px;
    animation: pageFadeIn 0.6s ease;
}

.location-hero {
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

.location-hero h2 {
    margin: 0;
    font-size: 1.95rem;
    color: #2d2a27;
    letter-spacing: -0.3px;
    font-weight: 800;
}

.location-hero p {
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
    grid-template-columns: repeat(6, minmax(150px, 1fr));
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

.action-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.action-panel {
    background: linear-gradient(135deg, #f5f2ee 0%, #e9e5df 100%);
    border: 1px solid #cec5bc;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 10px 24px rgba(79, 71, 63, 0.12);
}

.action-panel h4 {
    margin: 0 0 10px;
    color: #2f2a25;
    font-size: 1rem;
    font-weight: 700;
}

.action-row {
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

.preview-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 16px;
    margin-bottom: 18px;
}

.preview-card,
.cluster-card {
    background: linear-gradient(140deg, #f3f0ec 0%, #e7e2dc 100%);
    border: 1px solid #cbc2b8;
    border-radius: 24px;
    padding: 18px;
    box-shadow: 0 10px 24px rgba(74, 66, 59, 0.11);
}

.preview-card h3,
.cluster-card h3 {
    margin: 0 0 12px;
    color: #2f2a25;
    font-size: 1.02rem;
    font-weight: 800;
}

.map-stage {
    position: relative;
    border-radius: 18px;
    min-height: 190px;
    border: 1px dashed #b9aea2;
    background: linear-gradient(135deg, #f9f7f4 0%, #ece6df 100%);
    overflow: hidden;
}

.map-dot {
    position: absolute;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #8d837a;
    box-shadow: 0 0 0 4px rgba(141, 131, 122, 0.2);
    animation: pulse 2.2s infinite ease;
}

.dot-a { left: 18%; top: 32%; }
.dot-b { left: 42%; top: 58%; animation-delay: 0.3s; }
.dot-c { left: 66%; top: 28%; animation-delay: 0.7s; }
.dot-d { left: 75%; top: 64%; animation-delay: 1s; }

.cluster-list {
    display: grid;
    gap: 10px;
}

.cluster-item {
    background: #f8f5f1;
    border: 1px solid #d1c7bc;
    border-radius: 16px;
    padding: 10px 12px;
}

.cluster-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #3d3732;
    font-size: 0.84rem;
    font-weight: 700;
    margin-bottom: 6px;
}

.occupancy-track {
    height: 8px;
    background: #e6dfd7;
    border-radius: 999px;
    overflow: hidden;
}

.occupancy-fill {
    height: 100%;
    background: linear-gradient(90deg, #c9c0b6 0%, #9b8f84 100%);
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
    grid-template-columns: repeat(4, minmax(160px, 1fr));
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
    min-width: 1120px;
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

.st-active { background: #eaf7ed; color: #2d7b40; border: 1px solid #b7dfc2; }
.st-inactive { background: #f1efef; color: #5f5a5a; border: 1px solid #d0cbcb; }

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
    margin: 2px;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.btn-allocate { background: #e7f5ea; color: #2d7b40; border-color: #b7dfc2; }
.btn-reallocate { background: #f6f0e8; color: #6d5843; border-color: #decfbf; }
.btn-view { background: #ece9e4; color: #403a33; }
.btn-edit { background: #f3efe9; color: #4a443e; }
.btn-remove { background: #fdeeee; color: #a04646; border-color: #e9bcbc; }

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

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
}

@media (max-width: 1280px) {
    .stats-grid {
        grid-template-columns: repeat(3, minmax(150px, 1fr));
    }

    .filters-grid {
        grid-template-columns: repeat(2, minmax(150px, 1fr));
    }
}

@media (max-width: 960px) {
    .action-grid,
    .preview-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .locations-shell,
    .location-hero,
    .filters-panel,
    .table-card,
    .preview-card,
    .cluster-card {
        border-radius: 18px;
        padding: 14px;
    }

    .stats-grid,
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .location-hero {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="app-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <header class="top-header">
            <div class="page-title">
                <h2>Urban Location Allocation Portal</h2>
                <p>Advanced administration for market zoning, stall allocation, and operational location control.</p>
            </div>
        </header>

        <div class="page-content">
            <div class="locations-shell">
                <section class="location-hero">
                    <div>
                        <h2>Location & Zone Control Center</h2>
                        <p>Govern location distribution, optimize market capacity, and track city-zone occupancy in real time.</p>
                    </div>
                    <div class="hero-badge">Locations: Command Mode</div>
                </section>

                <section class="stats-grid">
                    <article class="stat-card">
                        <div class="stat-label">Total Locations</div>
                        <div class="stat-value"><?php echo $totalLocations; ?></div>
                        <div class="stat-sub">Registered location rows</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Allocated Stalls</div>
                        <div class="stat-value"><?php echo $allocatedStalls; ?></div>
                        <div class="stat-sub">Active assignments</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Available Locations</div>
                        <div class="stat-value"><?php echo $availableLocations; ?></div>
                        <div class="stat-sub">Open capacity in active zones</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Pending Allocation</div>
                        <div class="stat-value"><?php echo $pendingAllocation; ?></div>
                        <div class="stat-sub">Vendors awaiting assignment</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Restricted Zones</div>
                        <div class="stat-value"><?php echo $restrictedZones; ?></div>
                        <div class="stat-sub">Currently locked/inactive</div>
                    </article>
                    <article class="stat-card">
                        <div class="stat-label">Active Zones</div>
                        <div class="stat-value"><?php echo $activeZones; ?></div>
                        <div class="stat-sub">Operational market zones</div>
                    </article>
                </section>

                <section class="action-grid">
                    <div class="action-panel">
                        <h4>Smart Action Panels</h4>
                        <div class="action-row">
                            <a class="pill-btn" href="/street_vendor/admin/allocate_location.php">Allocate Stall</a>
                            <a class="pill-btn" href="/street_vendor/admin/vendors.php">Vendor Queue</a>
                            <a class="pill-btn" href="/street_vendor/admin/zones.php">Zone Matrix</a>
                        </div>
                    </div>
                    <div class="action-panel">
                        <h4>Operational Snapshot</h4>
                        <div class="action-row">
                            <span class="pill-btn">Pending Queue: <?php echo $pendingAllocation; ?></span>
                            <span class="pill-btn">Active Capacity: <?php echo $allocatedStalls; ?>/<?php echo $totalCapacity; ?></span>
                            <span class="pill-btn">Filtered Rows: <?php echo count($locations); ?></span>
                        </div>
                    </div>
                </section>

                <section class="preview-grid">
                    <div class="preview-card">
                        <h3>Zone Map Preview</h3>
                        <div class="map-stage" aria-label="Zone map preview">
                            <div class="map-dot dot-a"></div>
                            <div class="map-dot dot-b"></div>
                            <div class="map-dot dot-c"></div>
                            <div class="map-dot dot-d"></div>
                        </div>
                        <p style="margin: 10px 0 0; color:#6d655e; font-size:0.84rem; font-weight:600;">Market location overview and stall cluster visualization preview panel.</p>
                    </div>

                    <div class="cluster-card">
                        <h3>Stall Cluster Visualization</h3>
                        <div class="cluster-list">
                            <?php foreach (array_slice($zoneOccupancy, 0, 4) as $zone): ?>
                                <?php
                                    $occupied = (int) $zone['occupied'];
                                    $max = max((int) $zone['max_vendors'], 1);
                                    $pct = min(100, (int) round(($occupied / $max) * 100));
                                ?>
                                <div class="cluster-item">
                                    <div class="cluster-head">
                                        <span><?php echo htmlspecialchars($zone['zone_name']); ?></span>
                                        <span><?php echo $occupied; ?>/<?php echo $max; ?></span>
                                    </div>
                                    <div class="occupancy-track">
                                        <div class="occupancy-fill" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="filters-panel">
                    <form method="GET">
                        <div class="filters-grid">
                            <input type="text" name="q_location" placeholder="Search location ID / stall" value="<?php echo htmlspecialchars($qLocation); ?>">
                            <select name="q_zone">
                                <option value="">Filter by zone</option>
                                <?php foreach ($zones as $zone): ?>
                                    <?php $zoneName = $zone['zone_name']; ?>
                                    <option value="<?php echo htmlspecialchars($zoneName); ?>" <?php echo $qZone === $zoneName ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($zoneName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <option value="">Filter by status</option>
                                <option value="active" <?php echo $qStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $qStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <input type="text" name="market_area" placeholder="Filter by market area" value="<?php echo htmlspecialchars($qMarket); ?>">
                        </div>
                        <div class="filters-actions">
                            <button type="submit" class="btn-main">Apply Filters</button>
                            <a href="/street_vendor/admin/locations.php" class="btn-soft">Reset</a>
                        </div>
                    </form>
                </section>

                <section class="table-card">
                    <div class="table-head">
                        <h3>Location Administration Registry</h3>
                        <span class="table-note">Hover-enabled rows, action pills, and enterprise status tracking</span>
                    </div>

                    <?php if (empty($locations)): ?>
                        <div class="empty-msg">No location records matched the selected filters.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Location ID</th>
                                        <th>Zone Name</th>
                                        <th>Stall Number</th>
                                        <th>Vendor Assigned</th>
                                        <th>Market Area</th>
                                        <th>Allocation Status</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $loc): ?>
                                        <tr>
                                            <td><strong>#<?php echo (int) $loc['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($loc['zone_name']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($loc['spot_number']); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($loc['vendor_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($loc['vendor_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($loc['market_area']); ?></td>
                                            <td>
                                                <span class="status-pill <?php echo ((int) $loc['is_active'] === 1) ? 'st-active' : 'st-inactive'; ?>">
                                                    <?php echo ((int) $loc['is_active'] === 1) ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($loc['created_at'] ?? $loc['allocated_date'])); ?></td>
                                            <td>
                                                <a href="/street_vendor/admin/allocate_location.php" class="action-btn btn-allocate">Allocate</a>
                                                <a href="/street_vendor/admin/allocate_location.php?vendor_id=<?php echo (int) $loc['vendor_id']; ?>" class="action-btn btn-reallocate">Reallocate</a>
                                                <a href="/street_vendor/admin/locations.php?q_location=<?php echo (int) $loc['id']; ?>" class="action-btn btn-view">View</a>
                                                <a href="javascript:void(0)" class="action-btn btn-edit" onclick="alert('Edit workflow can be linked to a dedicated location editor.')">Edit</a>
                                                <a href="javascript:void(0)" class="action-btn btn-remove" onclick="alert('Remove workflow can be linked after policy confirmation.')">Remove</a>
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
