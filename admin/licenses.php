<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireAdmin();

$pageTitle = 'License Applications';
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
            <a href="/street_vendor/admin/vendors.php" class="sidebar-link"><i class='bx bxs-group'></i> Vendors</a>
            <a href="/street_vendor/admin/licenses.php" class="sidebar-link active"><i class='bx bxs-id-card'></i> Licenses</a>
            <a href="/street_vendor/admin/allocate_location.php" class="sidebar-link"><i class='bx bxs-map-pin'></i> Allocate Location</a>
            <a href="/street_vendor/admin/zones.php" class="sidebar-link"><i class='bx bx-map-alt'></i> Zones & Map</a>
        </nav>
    </aside>

    <div id="page-content">
        <header class="top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">Application Review</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle"><i class='bx bx-moon'></i></button>
                <a class="btn btn-sm btn-outline-danger border-border" href="/street_vendor/auth/logout.php">Logout</a>
            </div>
        </header>

        <main class="container-fluid p-4">
            <?php include __DIR__ . '/../includes/flash.php'; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="card bg-card border-border shadow-sm"><div class="card-body"><small class="text-muted-foreground">Pending</small><h3 id="stat-pending">0</h3></div></div></div>
                <div class="col-md-3"><div class="card bg-card border-border shadow-sm"><div class="card-body"><small class="text-muted-foreground">Approved</small><h3 id="stat-approved">0</h3></div></div></div>
                <div class="col-md-3"><div class="card bg-card border-border shadow-sm"><div class="card-body"><small class="text-muted-foreground">Rejected</small><h3 id="stat-rejected">0</h3></div></div></div>
                <div class="col-md-3"><div class="card bg-card border-border shadow-sm"><div class="card-body"><small class="text-muted-foreground">Total</small><h3 id="stat-total">0</h3></div></div></div>
            </div>

            <div class="card bg-card border-border shadow-sm mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small text-muted-foreground">Vendor Name</label>
                            <input type="text" id="q_vendor" class="form-control" placeholder="Search vendor">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small text-muted-foreground">Status</label>
                            <select id="q_status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small text-muted-foreground">Zone</label>
                            <select id="q_zone" class="form-select"><option value="">All zones</option></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small text-muted-foreground">Category</label>
                            <select id="q_category" class="form-select"><option value="">All categories</option></select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary fw-semibold" type="submit">Apply Filters</button>
                            <button class="btn btn-outline-secondary border-border fw-semibold" type="button" id="resetFilters">Reset</button>
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
                                    <th>Selected Zone</th>
                                    <th>Details</th>
                                    <th>Documents</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="licensesBody">
                                <tr><td colspan="7"><span class="skeleton-text w-100"></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <div>
                    <h5 class="modal-title fw-bold text-foreground" id="documentPreviewTitle">Document Preview</h5>
                    <small class="text-muted-foreground" id="documentPreviewPath"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="documentPreviewBody" class="d-flex align-items-center justify-content-center bg-muted rounded border border-border" style="min-height:520px; overflow:hidden;"></div>
            </div>
            <div class="modal-footer border-border">
                <a href="#" id="documentOpenNewTab" class="btn btn-outline-secondary border-border" target="_blank" rel="noopener">Open in New Tab</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('theme-toggle').addEventListener('click', () => {
    const html = document.documentElement;
    const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('adminTheme', next);
    document.querySelector('#theme-toggle i').className = next === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
});
if (document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle i').className = 'bx bx-sun';
}

function esc(value) {
    const d = document.createElement('div');
    d.textContent = value ?? '';
    return d.innerHTML;
}

function docLink(path, label) {
    if (!path) return '<span class="text-muted-foreground small">Missing</span>';
    const url = `/street_vendor/${esc(path)}`;
    return `<button type="button" class="btn btn-sm btn-outline-secondary border-border mb-1" data-doc-url="${url}" data-doc-title="${esc(label)}" onclick="previewDocument(this)">${esc(label)}</button>`;
}

function previewDocument(button) {
    const url = button.getAttribute('data-doc-url');
    const title = button.getAttribute('data-doc-title') || 'Document Preview';
    const body = document.getElementById('documentPreviewBody');
    const pathLabel = document.getElementById('documentPreviewPath');
    const openLink = document.getElementById('documentOpenNewTab');
    const modalTitle = document.getElementById('documentPreviewTitle');
    const lowerUrl = url.toLowerCase();

    modalTitle.textContent = title + ' Preview';
    pathLabel.textContent = url.replace('/street_vendor/', '');
    openLink.href = url;

    if (lowerUrl.endsWith('.pdf')) {
        body.innerHTML = `<iframe src="${url}" title="${esc(title)}" style="width:100%;height:70vh;border:0;border-radius:10px;background:#fff"></iframe>`;
    } else {
        body.innerHTML = `<img src="${url}" alt="${esc(title)}" style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:10px;box-shadow:0 12px 35px rgba(15,23,42,.16);background:#fff">`;
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('documentPreviewModal')).show();
}

function loadLicenses() {
    const params = new URLSearchParams();
    ['vendor', 'status', 'zone', 'category'].forEach(key => {
        const value = document.getElementById('q_' + key).value;
        if (value) params.set(key === 'vendor' ? 'q_vendor' : key, value);
    });

    fetch('/street_vendor/api/get_licenses.php?' + params.toString())
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Failed to load applications');
            const data = res.data;
            document.getElementById('stat-pending').textContent = data.stats.pendingCount;
            document.getElementById('stat-approved').textContent = data.stats.approvedCount;
            document.getElementById('stat-rejected').textContent = data.stats.rejectedCount;
            document.getElementById('stat-total').textContent = data.stats.totalLicenses;

            const zoneSelect = document.getElementById('q_zone');
            if (zoneSelect.options.length <= 1) {
                data.zones.forEach(z => zoneSelect.add(new Option(z.zone_name, z.zone_name)));
            }
            const catSelect = document.getElementById('q_category');
            if (catSelect.options.length <= 1) {
                data.categories.forEach(c => catSelect.add(new Option(c.vendor_category, c.vendor_category)));
            }

            const tbody = document.getElementById('licensesBody');
            if (!data.licenses.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted-foreground py-4">No applications found.</td></tr>';
                return;
            }
            tbody.innerHTML = data.licenses.map(lic => {
                const badge = lic.status === 'approved' ? 'bg-success' : lic.status === 'rejected' ? 'bg-danger' : lic.status === 'expired' ? 'bg-warning text-dark' : 'bg-primary';
                const available = Math.max((parseInt(lic.max_capacity) || 0) - (parseInt(lic.current_capacity) || 0), 0);
                const actions = lic.status === 'pending'
                    ? `<a href="/street_vendor/admin/approve_license.php?id=${lic.id}&action=approve" class="btn btn-sm btn-outline-success border-border">Approve</a>
                       <a href="/street_vendor/admin/approve_license.php?id=${lic.id}&action=reject" class="btn btn-sm btn-outline-danger border-border">Reject</a>`
                    : lic.status === 'approved' && !lic.spot_number
                        ? `<a href="/street_vendor/admin/allocate_location.php?application_id=${lic.id}" class="btn btn-sm btn-primary">Assign Spot</a>`
                        : '<span class="text-muted-foreground small">Processed</span>';
                return `
                    <tr>
                        <td><strong>${esc(lic.vendor_name)}</strong><br><small class="text-muted-foreground">${esc(lic.vendor_email)}</small></td>
                        <td>${esc(lic.zone_name || 'Not selected')}<br><small class="text-muted-foreground">Slots: ${available}/${esc(lic.max_capacity || 0)}</small></td>
                        <td>
                            <strong>${esc(lic.business_type || 'N/A')}</strong><br>
                            <small>Category: ${esc(lic.vendor_category || 'N/A')}</small><br>
                            <small>Priority: ${esc(lic.priority_type || 'N/A')}</small>
                        </td>
                        <td>
                            ${docLink(lic.aadhar_path, 'Aadhar')}
                            ${docLink(lic.photo_path, 'Photo')}
                            ${docLink(lic.business_proof_path, 'Business Proof')}
                        </td>
                        <td><span class="badge ${badge}">${esc(lic.status)}</span></td>
                        <td>${esc(lic.remarks || '-')}</td>
                        <td><div class="d-flex gap-2 flex-wrap">${actions}</div></td>
                    </tr>
                `;
            }).join('');
        })
        .catch(err => {
            document.getElementById('licensesBody').innerHTML = `<tr><td colspan="7" class="text-danger text-center">${esc(err.message)}</td></tr>`;
        });
}

document.getElementById('filterForm').addEventListener('submit', e => {
    e.preventDefault();
    loadLicenses();
});
document.getElementById('resetFilters').addEventListener('click', () => {
    document.getElementById('filterForm').reset();
    loadLicenses();
});
document.addEventListener('DOMContentLoaded', loadLicenses);
</script>
</body>
</html>
