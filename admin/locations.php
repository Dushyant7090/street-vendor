<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireAdmin();

$pageTitle = 'Assigned Locations';
$adminPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div id="admin-wrapper">
    <aside id="sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 class="text-primary fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Admin Portal</small>
        </div>
        <nav class="d-flex flex-column mt-4">
            <a href="/street_vendor/admin/dashboard.php" class="sidebar-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="/street_vendor/admin/licenses.php" class="sidebar-link"><i class='bx bxs-id-card'></i> Licenses</a>
            <a href="/street_vendor/admin/allocate_location.php" class="sidebar-link"><i class='bx bxs-map-pin'></i> Allocate Location</a>
            <a href="/street_vendor/admin/locations.php" class="sidebar-link active"><i class='bx bxs-map'></i> Locations</a>
            <a href="/street_vendor/admin/zones.php" class="sidebar-link"><i class='bx bx-map-alt'></i> Zones & Map</a>
        </nav>
    </aside>

    <div id="page-content">
        <header class="top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">Assigned Locations</h5>
            <a class="btn btn-sm btn-outline-danger border-border" href="/street_vendor/auth/logout.php">Logout</a>
        </header>

        <main class="container-fluid p-4">
            <?php include __DIR__ . '/../includes/flash.php'; ?>

            <div class="card bg-card border-border shadow-sm mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted-foreground">Vendor</label>
                            <input type="text" id="q_vendor" class="form-control" placeholder="Search vendor">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted-foreground">Zone</label>
                            <select id="q_zone" class="form-select"><option value="">All zones</option></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted-foreground">Status</label>
                            <select id="q_status" class="form-select">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary fw-semibold" type="submit">Apply Filters</button>
                            <button class="btn btn-outline-secondary border-border fw-semibold" type="button" id="resetFilters">Reset</button>
                            <a href="/street_vendor/admin/allocate_location.php" class="btn btn-outline-success border-border fw-semibold">Allocate Spot</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-card border-border shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-foreground">
                            <thead class="text-muted-foreground">
                                <tr>
                                    <th>Vendor</th>
                                    <th>Zone</th>
                                    <th>Spot</th>
                                    <th>Coordinates</th>
                                    <th>Capacity Status</th>
                                    <th>Allocation Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="locationsBody">
                                <tr><td colspan="7"><span class="skeleton-text w-100"></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
function esc(value) {
    const d = document.createElement('div');
    d.textContent = value ?? '';
    return d.innerHTML;
}

function loadLocations() {
    const params = new URLSearchParams();
    const vendor = document.getElementById('q_vendor').value;
    const zone = document.getElementById('q_zone').value;
    const status = document.getElementById('q_status').value;
    if (vendor) params.set('q_vendor', vendor);
    if (zone) params.set('q_zone', zone);
    if (status) params.set('status', status);

    fetch('/street_vendor/api/get_locations.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Failed to load locations');
            const zoneSelect = document.getElementById('q_zone');
            if (zoneSelect.options.length <= 1) {
                res.data.zones.forEach(z => zoneSelect.add(new Option(z.zone_name, z.zone_name)));
            }
            const tbody = document.getElementById('locationsBody');
            if (!res.data.locations.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted-foreground py-4">No assigned locations found.</td></tr>';
                return;
            }
            tbody.innerHTML = res.data.locations.map(loc => {
                const max = parseInt(loc.max_capacity) || 0;
                const current = parseInt(loc.current_capacity) || 0;
                const pct = max > 0 ? Math.round(current / max * 100) : 0;
                const badge = loc.status === 'active' ? 'bg-success' : 'bg-secondary';
                return `
                    <tr>
                        <td><strong>${esc(loc.vendor_name)}</strong><br><small class="text-muted-foreground">${esc(loc.vendor_email)}</small></td>
                        <td>${esc(loc.zone_name)}</td>
                        <td><span class="badge bg-light text-dark border">${esc(loc.spot_number)}</span></td>
                        <td>${esc(loc.latitude)}, ${esc(loc.longitude)}</td>
                        <td>${current}/${max}<div class="progress mt-1" style="height:5px"><div class="progress-bar" style="width:${Math.min(100, pct)}%"></div></div></td>
                        <td>${loc.assigned_at ? new Date(loc.assigned_at).toLocaleDateString('en-IN') : esc(loc.allocated_date || 'N/A')}</td>
                        <td><span class="badge ${badge}">${esc(loc.status)}</span></td>
                    </tr>
                `;
            }).join('');
        })
        .catch(err => {
            document.getElementById('locationsBody').innerHTML = `<tr><td colspan="7" class="text-center text-danger">${esc(err.message)}</td></tr>`;
        });
}

document.getElementById('filterForm').addEventListener('submit', e => {
    e.preventDefault();
    loadLocations();
});
document.getElementById('resetFilters').addEventListener('click', () => {
    document.getElementById('filterForm').reset();
    loadLocations();
});
document.addEventListener('DOMContentLoaded', loadLocations);
</script>
</body>
</html>
