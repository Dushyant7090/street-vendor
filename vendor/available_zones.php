<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireVendor();

$name = $_SESSION['name'] ?? 'Vendor User';
$pageTitle = 'Available Zones';
$vendorPage = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .vendor-map-search {
        display: flex;
        align-items: center;
        gap: 8px;
        width: min(620px, 100%);
        min-height: 42px;
        padding: 4px 4px 4px 12px;
        border: 1px solid hsl(var(--border));
        border-radius: 10px;
        background: hsl(var(--background));
    }
    .vendor-map-search i {
        color: hsl(var(--muted-foreground));
        font-size: 18px;
    }
    .vendor-map-search input {
        flex: 1;
        min-width: 0;
        border: 0;
        outline: 0;
        background: transparent;
        color: hsl(var(--foreground));
        font-weight: 500;
    }
    .vendor-map-search:focus-within {
        border-color: hsl(var(--ring));
        box-shadow: 0 0 0 3px hsla(var(--ring)/.18);
    }
    .vendor-map-search .btn {
        min-height: 32px;
        padding: 0 14px;
        border-radius: 8px;
    }
</style>

<div id="vendor-wrapper">
    <aside id="vendor-sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 style="color:hsl(var(--primary))" class="fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Vendor Portal</small>
        </div>
        <nav class="d-flex flex-column mt-3">
            <a href="/street_vendor/vendor/dashboard.php" class="vendor-sidebar-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="/street_vendor/vendor/available_zones.php" class="vendor-sidebar-link active"><i class='bx bx-map-alt'></i> Available Zones</a>
            <a href="/street_vendor/vendor/apply_license.php" class="vendor-sidebar-link"><i class='bx bxs-file-plus'></i> Apply for License</a>
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
            <h5 class="mb-0 fw-semibold text-foreground">Available Zones</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle"><i class='bx bx-moon'></i></button>
                <span class="fw-semibold text-foreground"><?php echo htmlspecialchars($name); ?></span>
            </div>
        </header>

        <main class="container-fluid p-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card bg-card border-border shadow-sm">
                        <div class="card-header bg-transparent border-border">
                            <form class="vendor-map-search" onsubmit="searchVendorMapLocation(event)">
                                <i class='bx bx-search'></i>
                                <input type="search" id="vendor-map-search-input" placeholder="Search city, area, or street" autocomplete="off">
                                <button class="btn btn-primary fw-semibold" type="submit">Search</button>
                            </form>
                        </div>
                        <div class="card-body p-0" style="overflow:hidden;border-radius:var(--radius)">
                            <div id="zones-map" style="height:640px;width:100%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card bg-card border-border shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold text-foreground mb-3">Zone Legend</h5>
                            <div class="d-grid gap-2">
                                <div class="d-flex align-items-center gap-2"><span class="rounded-circle" style="width:14px;height:14px;background:#22c55e"></span> Available</div>
                                <div class="d-flex align-items-center gap-2"><span class="rounded-circle" style="width:14px;height:14px;background:#eab308"></span> Near capacity</div>
                                <div class="d-flex align-items-center gap-2"><span class="rounded-circle" style="width:14px;height:14px;background:#ef4444"></span> Full or not available</div>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-card border-border shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold text-foreground mb-3">Zones</h5>
                            <div id="zone-list" class="d-grid gap-3">
                                <span class="skeleton-text w-100"></span>
                                <span class="skeleton-text w-100"></span>
                                <span class="skeleton-text w-100"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

const map = L.map('zones-map').setView([15.4319, 75.6340], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

const colors = { green: '#22c55e', yellow: '#eab308', red: '#ef4444' };
let zoneBounds = [];
let vendorSearchMarker = null;

function esc(value) {
    const d = document.createElement('div');
    d.textContent = value ?? '';
    return d.innerHTML;
}

function renderPopup(zone) {
    const max = parseInt(zone.max_capacity) || 0;
    const current = parseInt(zone.current_capacity) || 0;
    const slots = Math.max(max - current, 0);
    const canApply = zone.status === 'available' && slots > 0;
    return `
        <div style="min-width:230px">
            <strong style="font-size:15px">${esc(zone.zone_name)}</strong>
            <div style="margin-top:8px;font-size:12px;line-height:1.7">
                <div>Capacity: <strong>${max}</strong></div>
                <div>Current occupancy: <strong>${current}</strong></div>
                <div>Available slots: <strong>${slots}</strong></div>
                <div>Status: <strong>${zone.status === 'available' ? 'Available' : 'Not Available'}</strong></div>
            </div>
            ${canApply
                ? `<a href="/street_vendor/vendor/apply_license.php?zone_id=${zone.id}" style="display:block;margin-top:10px;padding:8px 10px;border-radius:8px;background:#22c55e;color:#fff;text-align:center;text-decoration:none;font-weight:700">Apply for this Zone</a>`
                : `<div style="margin-top:10px;padding:8px 10px;border-radius:8px;background:#fee2e2;color:#991b1b;text-align:center;font-weight:700">Applications closed</div>`}
        </div>
    `;
}

function renderList(zones) {
    const container = document.getElementById('zone-list');
    if (!zones.length) {
        container.innerHTML = '<div class="text-muted-foreground text-center py-4">No zones have been created yet.</div>';
        return;
    }
    container.innerHTML = zones.map(zone => {
        const color = colors[zone.color_status] || colors.red;
        const max = parseInt(zone.max_capacity) || 0;
        const current = parseInt(zone.current_capacity) || 0;
        const pct = max > 0 ? Math.min(100, Math.round(current / max * 100)) : 0;
        return `
            <button type="button" class="btn text-start border-border bg-card" onclick="focusZone(${zone.id})">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>${esc(zone.zone_name)}</strong>
                    <span class="badge" style="background:${color}">${current}/${max}</span>
                </div>
                <div class="progress mt-2" style="height:6px">
                    <div class="progress-bar" style="width:${pct}%;background:${color}"></div>
                </div>
            </button>
        `;
    }).join('');
}

function focusZone(id) {
    const found = zoneBounds.find(item => item.id == id);
    if (found) map.fitBounds(found.bounds, { padding: [30, 30] });
}

function searchVendorMapLocation(event) {
    event.preventDefault();
    const input = document.getElementById('vendor-map-search-input');
    const query = input ? input.value.trim() : '';
    if (!query) {
        alert('Enter a city, area, or street name to search.');
        return;
    }

    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query), {
        headers: { 'Accept': 'application/json' }
    })
        .then(response => response.json())
        .then(results => {
            if (!Array.isArray(results) || results.length === 0) {
                alert('No location found for "' + query + '".');
                return;
            }

            const result = results[0];
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                alert('Location result did not include valid coordinates.');
                return;
            }

            map.setView([lat, lng], 16);
            if (vendorSearchMarker) map.removeLayer(vendorSearchMarker);
            vendorSearchMarker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`<strong>Search result</strong><br>${esc(result.display_name || query)}`)
                .openPopup();
        })
        .catch(() => {
            alert('Location search failed. Check your internet connection and try again.');
        });
}

fetch('/street_vendor/api/get_zones.php')
    .then(r => r.json())
    .then(res => {
        if (!res.success) throw new Error(res.error || 'Unable to load zones');
        const zones = res.data.zones;
        renderList(zones);
        zones.forEach(zone => {
            if (!zone.geometry_json) return;
            let geo;
            try { geo = JSON.parse(zone.geometry_json); } catch (e) { return; }
            const color = colors[zone.color_status] || colors.red;
            const layer = L.geoJSON(geo, {
                style: { color, fillColor: color, weight: 3, fillOpacity: 0.24 }
            }).addTo(map);
            const popupHtml = renderPopup(zone);
            layer.bindPopup(popupHtml);
            zoneBounds.push({ id: zone.id, bounds: layer.getBounds() });

            if (layer.getBounds().isValid()) {
                const center = layer.getBounds().getCenter();
                const zoneMarker = L.circleMarker(center, {
                    radius: 14,
                    color: '#ffffff',
                    weight: 3,
                    fillColor: color,
                    fillOpacity: 0.95,
                    opacity: 1
                }).addTo(map);
                zoneMarker.bindPopup(popupHtml);
                zoneMarker.bindTooltip(esc(zone.zone_name), {
                    permanent: false,
                    direction: 'top'
                });
            }
        });
        if (zoneBounds.length) {
            const group = L.featureGroup(zoneBounds.map(item => L.rectangle(item.bounds, { opacity: 0, fillOpacity: 0 })));
            map.fitBounds(group.getBounds(), { padding: [30, 30] });
        }
    })
    .catch(err => {
        document.getElementById('zone-list').innerHTML = `<div class="alert alert-danger">${esc(err.message)}</div>`;
    });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
