<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}

$pageTitle = 'Vendor Management';
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
            <a href="/street_vendor/admin/dashboard.php" class="sidebar-link">
                <i class='bx bxs-dashboard'></i> Dashboard
            </a>
            <a href="/street_vendor/admin/vendors.php" class="sidebar-link active">
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
                <h5 class="mb-0 fw-semibold text-foreground">Vendor Management</h5>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle">
                    <i class='bx bx-moon'></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle border-border" type="button" data-bs-toggle="dropdown">
                        <i class='bx bxs-user-circle'></i> Admin
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                        <li><a class="dropdown-item text-danger" href="/street_vendor/auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Container -->
        <main class="container-fluid p-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="fw-bold mb-1">Vendor Registry Flow</h3>
                    <p class="text-muted-foreground">A curated lens into registration, compliance, and assignment patterns across the managed street vendor network.</p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-muted-foreground mb-2">Total Vendors</h6>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-vendors"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-muted-foreground mb-2">Approved Licenses</h6>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-licenses"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-muted-foreground mb-2">Assigned Locations</h6>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-assigned"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-card border-border h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-muted-foreground mb-2">Pending Placement</h6>
                            <h2 class="fw-bold text-foreground mb-0" id="stat-pending"><span class="skeleton-text"></span></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="card bg-card border-border shadow-sm mb-4">
                <div class="card-body">
                    <!-- Shadcn Style Form Group -->
                    <div class="field-group mb-4">
                        <div class="field">
                            <label class="field-label" for="searchInput">Search Vendors</label>
                            <div class="field-content">
                                <input type="text" id="searchInput" class="form-control" placeholder="Search by name, email or ID...">
                                <p class="field-description">Filter the table below by typing a vendor's details.</p>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-foreground" id="vendorTable">
                            <thead class="text-muted-foreground">
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
                            <tbody id="vendorTableBody">
                                <tr><td colspan="8"><span class="skeleton-text w-100"></span></td></tr>
                                <tr><td colspan="8"><span class="skeleton-text w-100"></span></td></tr>
                                <tr><td colspan="8"><span class="skeleton-text w-100"></span></td></tr>
                                <tr><td colspan="8"><span class="skeleton-text w-100"></span></td></tr>
                                <tr><td colspan="8"><span class="skeleton-text w-100"></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="vendorViewModal" tabindex="-1" aria-labelledby="vendorViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <div>
                    <h5 class="modal-title fw-bold text-foreground" id="vendorViewTitle">Vendor Details</h5>
                    <small class="text-muted-foreground" id="vendorViewSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="vendorViewBody">
                <div class="text-center py-5 text-muted-foreground">Loading vendor details...</div>
            </div>
            <div class="modal-footer border-border">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="vendorEditModal" tabindex="-1" aria-labelledby="vendorEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-card border-border">
            <form id="vendorEditForm">
                <div class="modal-header border-border">
                    <div>
                        <h5 class="modal-title fw-bold text-foreground" id="vendorEditTitle">Edit Vendor</h5>
                        <small class="text-muted-foreground">Update profile and identity details</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="vendor_id" id="edit_vendor_id">
                    <div id="vendorEditAlert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">Full Name</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">Phone</label>
                            <input type="text" class="form-control" name="phone" id="edit_phone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">ID Proof Type</label>
                            <select class="form-select" name="id_proof_type" id="edit_id_proof_type">
                                <option value="">Select proof type</option>
                                <option value="Aadhar Card">Aadhar Card</option>
                                <option value="PAN Card">PAN Card</option>
                                <option value="Voter ID">Voter ID</option>
                                <option value="Driving License">Driving License</option>
                                <option value="Passport">Passport</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">ID Proof Number</label>
                            <input type="text" class="form-control" name="id_proof_number" id="edit_id_proof_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">Business Name</label>
                            <input type="text" class="form-control" name="business_name" id="edit_business_name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted-foreground">Business Location</label>
                            <input type="text" class="form-control" name="business_location" id="edit_business_location">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted-foreground">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-border">
                    <button type="button" class="btn btn-outline-secondary border-border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveVendorBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
let vendorsCache = [];

// Theme Toggle Logic (Duplicate logic from dashboard if included multiple times, ideally should be a shared JS)
document.getElementById('theme-toggle').addEventListener('click', () => {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-bs-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('adminTheme', newTheme);
    const icon = document.querySelector('#theme-toggle i');
    icon.className = newTheme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
});
if(document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle i').className = 'bx bx-sun';
}

// Search Filter
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#vendorTableBody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});

// Fetch Data
function loadVendors() {
    fetch('/street_vendor/api/get_vendors.php')
        .then(response => response.json())
        .then(res => {
            if (res.success && res.data) {
                const stats = res.data.stats;
                const vendors = res.data.vendors;
                vendorsCache = vendors;
                
                // Update stats
                document.getElementById('stat-vendors').textContent = new Intl.NumberFormat('en-IN').format(stats.totalVendors);
                document.getElementById('stat-licenses').textContent = new Intl.NumberFormat('en-IN').format(stats.totalLicenses);
                document.getElementById('stat-assigned').textContent = new Intl.NumberFormat('en-IN').format(stats.totalAssigned);
                document.getElementById('stat-pending').textContent = new Intl.NumberFormat('en-IN').format(stats.pending);
                
                // Update table
                const tbody = document.getElementById('vendorTableBody');
                tbody.innerHTML = ''; // Clear skeletons
                
                if (vendors.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center">No vendors found.</td></tr>';
                } else {
                    vendors.forEach((v, i) => {
                        const tr = document.createElement('tr');
                        const joinedDate = new Date(v.joined).toLocaleDateString('en-IN', {
                            day: '2-digit', month: 'short', year: 'numeric'
                        });
                        
                        tr.innerHTML = `
                            <td>${i + 1}</td>
                            <td><strong>${escapeHtml(v.name)}</strong><br><small class="text-muted-foreground">${escapeHtml(v.address || '')}</small></td>
                            <td>${escapeHtml(v.email)}<br><small class="text-muted-foreground">${escapeHtml(v.phone || '')}</small></td>
                            <td>${escapeHtml(v.id_proof_type || '')}<br><small class="text-muted-foreground">${escapeHtml(v.id_proof_number || '')}</small></td>
                            <td><span class="badge ${v.active_licenses > 0 ? 'bg-primary' : 'bg-secondary'}">${v.active_licenses > 0 ? 'Active' : 'None'}</span></td>
                            <td><span class="badge ${v.has_location > 0 ? 'bg-success' : 'bg-warning text-dark'}">${v.has_location > 0 ? 'Assigned' : 'Unassigned'}</span></td>
                            <td>${joinedDate}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary border-border" onclick="openVendorView(${v.vendor_id})">View</button>
                                <button class="btn btn-sm btn-outline-secondary border-border" onclick="openVendorEdit(${v.vendor_id})">Edit</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } else {
                console.error('API Error:', res.error);
                document.getElementById('vendorTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load data.</td></tr>';
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            document.getElementById('vendorTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load data.</td></tr>';
        });
}

document.addEventListener('DOMContentLoaded', loadVendors);

function formatDate(value) {
    if (!value) return 'N/A';
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function documentButton(path, label) {
    if (!path) return '<span class="text-muted-foreground small">Not uploaded</span>';
    return `<a class="btn btn-sm btn-outline-secondary border-border" href="/street_vendor/${escapeHtml(path)}" target="_blank" rel="noopener">${escapeHtml(label)}</a>`;
}

function statusBadge(status) {
    const cls = status === 'approved' ? 'bg-success' : status === 'rejected' ? 'bg-danger' : status === 'expired' ? 'bg-warning text-dark' : 'bg-primary';
    return `<span class="badge ${cls}">${escapeHtml(status || 'pending')}</span>`;
}

function openVendorView(vendorId) {
    document.getElementById('vendorViewBody').innerHTML = '<div class="text-center py-5 text-muted-foreground">Loading vendor details...</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorViewModal')).show();

    fetch(`/street_vendor/api/get_vendor_details.php?vendor_id=${vendorId}`)
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Unable to load vendor details.');
            const vendor = res.data.vendor;
            const apps = res.data.applications || [];
            const location = res.data.location;

            document.getElementById('vendorViewTitle').textContent = vendor.name || 'Vendor Details';
            document.getElementById('vendorViewSubtitle').textContent = `Vendor ID #${vendor.vendor_id} | Joined ${formatDate(vendor.joined)}`;

            const appsHtml = apps.length ? apps.map(app => `
                <tr>
                    <td>#${app.id}<br><small class="text-muted-foreground">${formatDate(app.applied_at || app.applied_date)}</small></td>
                    <td>${escapeHtml(app.zone_name || 'Not selected')}</td>
                    <td>
                        <strong>${escapeHtml(app.business_type || 'N/A')}</strong><br>
                        <small>Category: ${escapeHtml(app.vendor_category || 'N/A')}</small><br>
                        <small>Priority: ${escapeHtml(app.priority_type || 'N/A')}</small>
                    </td>
                    <td>${statusBadge(app.status)}<br><small class="text-muted-foreground">${escapeHtml(app.license_number || 'No license number')}</small></td>
                    <td class="d-flex gap-1 flex-wrap">
                        ${documentButton(app.aadhar_path, 'Aadhar')}
                        ${documentButton(app.photo_path, 'Photo')}
                        ${documentButton(app.business_proof_path, 'Business Proof')}
                    </td>
                </tr>
            `).join('') : '<tr><td colspan="5" class="text-center text-muted-foreground py-3">No applications found.</td></tr>';

            document.getElementById('vendorViewBody').innerHTML = `
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card bg-card border-border h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-foreground mb-3">Profile</h6>
                                <div class="d-grid gap-2 small">
                                    <div><span class="text-muted-foreground">Name:</span> <strong>${escapeHtml(vendor.name || 'N/A')}</strong></div>
                                    <div><span class="text-muted-foreground">Email:</span> ${escapeHtml(vendor.email || 'N/A')}</div>
                                    <div><span class="text-muted-foreground">Phone:</span> ${escapeHtml(vendor.phone || 'N/A')}</div>
                                    <div><span class="text-muted-foreground">Address:</span> ${escapeHtml(vendor.address || 'N/A')}</div>
                                    <div><span class="text-muted-foreground">ID Proof:</span> ${escapeHtml(vendor.id_proof_type || 'N/A')} ${escapeHtml(vendor.id_proof_number || '')}</div>
                                    <div><span class="text-muted-foreground">Business:</span> ${escapeHtml(vendor.business_name || 'N/A')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card bg-card border-border h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-foreground mb-3">Assigned Location</h6>
                                ${location ? `
                                    <div class="d-grid gap-2 small">
                                        <div><span class="text-muted-foreground">Zone:</span> <strong>${escapeHtml(location.zone_name || 'N/A')}</strong></div>
                                        <div><span class="text-muted-foreground">Spot:</span> ${escapeHtml(location.spot_number || 'N/A')}</div>
                                        <div><span class="text-muted-foreground">Coordinates:</span> ${escapeHtml(location.latitude || '-')}, ${escapeHtml(location.longitude || '-')}</div>
                                        <div><span class="text-muted-foreground">Assigned:</span> ${formatDate(location.assigned_at)}</div>
                                    </div>
                                ` : '<div class="text-muted-foreground small">No active location assigned.</div>'}
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Application</th><th>Zone</th><th>Details</th><th>Status</th><th>Documents</th></tr></thead>
                                <tbody>${appsHtml}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            document.getElementById('vendorViewBody').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        });
}

function openVendorEdit(vendorId) {
    document.getElementById('vendorEditAlert').innerHTML = '';
    document.getElementById('vendorEditForm').reset();
    document.getElementById('edit_vendor_id').value = vendorId;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorEditModal')).show();

    fetch(`/street_vendor/api/get_vendor_details.php?vendor_id=${vendorId}`)
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Unable to load vendor details.');
            const vendor = res.data.vendor;

            document.getElementById('edit_vendor_id').value = vendor.vendor_id;
            document.getElementById('edit_name').value = vendor.name || '';
            document.getElementById('edit_email').value = vendor.email || '';
            document.getElementById('edit_phone').value = vendor.phone || '';
            document.getElementById('edit_address').value = vendor.address || '';
            document.getElementById('edit_id_proof_type').value = vendor.id_proof_type || '';
            document.getElementById('edit_id_proof_number').value = vendor.id_proof_number || '';
            document.getElementById('edit_business_name').value = vendor.business_name || '';
            document.getElementById('edit_business_location').value = vendor.business_location || '';
        })
        .catch(error => {
            document.getElementById('vendorEditAlert').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        });
}

document.getElementById('vendorEditForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveVendorBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    fetch('/street_vendor/api/update_vendor.php', {
        method: 'POST',
        body: new FormData(this)
    })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'Unable to save vendor.');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorEditModal')).hide();
            loadVendors();
        })
        .catch(error => {
            document.getElementById('vendorEditAlert').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        });
});

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
</script>

</body>
</html>
