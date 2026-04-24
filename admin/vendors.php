<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}

$vendors = [];
$query = $conn->query(
    "SELECT 
        u.id as user_id,
        u.name,
        u.email,
        u.created_at as joined,
        v.id as vendor_id,
        v.phone,
        v.address,
        v.id_proof_type,
        v.id_proof_number,
        (SELECT COUNT(*) FROM licenses WHERE vendor_id = v.id AND status = 'approved') as active_licenses,
        (SELECT COUNT(*) FROM locations WHERE vendor_id = v.id AND is_active = 1) as has_location
    FROM users u
    JOIN vendors v ON v.user_id = u.id
    WHERE u.role = 'vendor'
    ORDER BY u.created_at DESC"
);

if ($query) {
    $vendors = $query->fetch_all(MYSQLI_ASSOC);
}

$totalVendors = count($vendors);
$totalLicenses = array_sum(array_column($vendors, 'active_licenses'));
$totalAssigned = array_sum(array_column($vendors, 'has_location'));
$pending = $totalVendors - $totalAssigned;

$pageTitle = 'Vendor Management';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<style>
body {
    background: url('/street_vendor/assets/img/gov_vendor_bg_india.png') no-repeat center center fixed !important;
    background-size: cover !important;
}
</style>

<div class="admin-shell">
  <section class="hero-banner">
    <span class="hero-label">Vendor command center</span>
    <h1 class="hero-title">Premium vendor operations</h1>
    <p class="hero-copy">Monitor vendor activity, license status, and location assignment with a high-end warm sand control surface.</p>
    <div class="status-strip" style="margin-top: 24px; gap: 16px;">
      <span><?php echo number_format($totalVendors); ?> vendors</span>
      <span><?php echo number_format($totalLicenses); ?> active permits</span>
      <span><?php echo number_format($totalAssigned); ?> assigned locations</span>
      <span><?php echo number_format($pending); ?> pending checks</span>
    </div>
  </section>

  <section class="ribbon-panel">
    <a href="vendors.php" class="ribbon-link active">Overview</a>
    <a href="locations.php" class="ribbon-link">Locations</a>
    <a href="licenses.php" class="ribbon-link">Licenses</a>
  </section>

  <section class="report-panel">
    <h2 class="section-title">Vendor registry flow</h2>
    <p class="panel-copy">A curated lens into registration, compliance, and assignment patterns across the managed street vendor network.</p>
    <div class="stats-grid" style="margin-top: 28px;">
      <div class="metric-card">
        <strong><?php echo number_format($totalVendors); ?></strong>
        <span>Total vendors</span>
      </div>
      <div class="metric-card">
        <strong><?php echo number_format($totalLicenses); ?></strong>
        <span>Approved licenses</span>
      </div>
      <div class="metric-card">
        <strong><?php echo number_format($totalAssigned); ?></strong>
        <span>Assigned locations</span>
      </div>
      <div class="metric-card">
        <strong><?php echo number_format($pending); ?></strong>
        <span>Pending placement</span>
      </div>
    </div>
  </section>

  <section class="curve-panel" style="padding: 36px 34px;">
    <div class="search-box" style="margin-bottom: 28px;">
      <input type="text" id="searchInput" placeholder="Search vendors by name, email or ID...">
    </div>
    <div class="table-panel">
      <table id="vendorTable" class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Vendor</th>
            <th>Contact</th>
            <th>ID Proof</th>
            <th>License</th>
            <th>Location</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($vendors as $i => $v): ?>
          <tr>
            <td><?php echo $i + 1; ?></td>
            <td><strong><?php echo htmlspecialchars($v['name']); ?></strong><br><span class="text-small"><?php echo htmlspecialchars($v['address']); ?></span></td>
            <td><?php echo htmlspecialchars($v['email']); ?><br><span class="text-small"><?php echo htmlspecialchars($v['phone']); ?></span></td>
            <td><?php echo htmlspecialchars($v['id_proof_type']); ?><br><span class="text-small"><?php echo htmlspecialchars($v['id_proof_number']); ?></span></td>
            <td><span class="badge <?php echo $v['active_licenses'] ? 'accent' : ''; ?>"><?php echo $v['active_licenses'] ? 'Active' : 'None'; ?></span></td>
            <td><span class="badge <?php echo $v['has_location'] ? 'success' : 'warning'; ?>"><?php echo $v['has_location'] ? 'Assigned' : 'Unassigned'; ?></span></td>
            <td><?php echo date('d M Y', strtotime($v['joined'])); ?></td>
            <td><div class="actions" style="gap: 10px; flex-wrap: wrap;"><button class="btn btn-secondary">View</button><button class="btn btn-secondary">Edit</button></div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#vendorTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
