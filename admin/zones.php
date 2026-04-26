<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zones & Map | StreetVendor Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/street_vendor/assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <!-- Bootstrap + Theme -->
    <link href="/street_vendor/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/street_vendor/assets/css/admin-redesign.css">
    <link rel="stylesheet" href="/street_vendor/assets/css/map.css">
    <script>
        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body class="admin-theme bg-background text-foreground" style="margin:0;overflow:hidden">

<div id="map-wrapper">
    <!-- SIDEBAR -->
    <aside id="map-sidebar">
        <div class="sidebar-header">
            <h4 style="color:hsl(var(--primary))">StreetVendor</h4>
            <small>Zone & Location Map</small>
        </div>

        <div class="sidebar-nav">
            <a href="/street_vendor/admin/dashboard.php" class="nav-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="/street_vendor/admin/vendors.php" class="nav-link"><i class='bx bxs-group'></i> Vendors</a>
            <a href="/street_vendor/admin/licenses.php" class="nav-link"><i class='bx bxs-id-card'></i> Licenses</a>
            <a href="/street_vendor/admin/zones.php" class="nav-link active"><i class='bx bx-map-alt'></i> Zones & Map</a>
        </div>

        <div class="sidebar-tabs">
            <button class="tab-btn active" onclick="switchTab('zones')">Zones</button>
            <button class="tab-btn" onclick="switchTab('vendors')">Unassigned Vendors</button>
        </div>

        <div class="sidebar-content">
            <!-- Zones Tab -->
            <div class="tab-panel active" id="tab-zones">
                <input type="text" class="search-input" id="zone-search" placeholder="Search zones..." oninput="renderZonesList()">
                <div id="zones-list"></div>
            </div>
            <!-- Vendors Tab -->
            <div class="tab-panel" id="tab-vendors">
                <input type="text" class="search-input" id="vendor-search" placeholder="Search vendors..." oninput="renderUnassignedList()">
                <div id="unassigned-list"></div>
            </div>
        </div>
    </aside>

    <!-- MAP AREA -->
    <div id="map-container">
        <div class="map-top-bar">
            <div class="map-toolbar">
                <div class="map-tool-group">
                    <button class="toolbar-btn" onclick="startDrawZone()"><i class='bx bx-pencil'></i> Draw Zone</button>
                    <button class="toolbar-btn" onclick="loadMapData()"><i class='bx bx-refresh'></i> Refresh</button>
                </div>
                <form class="map-search-form" onsubmit="searchMapLocation(event)">
                    <i class='bx bx-search map-search-icon'></i>
                    <input type="search" id="map-search-input" class="map-search-input" placeholder="Search city, area, or street" autocomplete="off">
                    <button class="toolbar-btn search-submit" type="submit">Search</button>
                </form>
                <button class="toolbar-btn icon-only" id="theme-toggle-map" onclick="toggleTheme()" title="Toggle theme"><i class='bx bx-moon'></i></button>
            </div>
        </div>
        <div id="map"></div>

        <!-- Assign Banner -->
        <div class="assign-banner">
            <i class='bx bx-map-pin' style="font-size:1.2rem"></i>
            <span>Click inside a zone to assign <strong id="assign-vendor-name">Vendor</strong></span>
            <button class="cancel-assign" onclick="cancelAssign()">Cancel</button>
        </div>
    </div>
</div>

<!-- Zone Create/Edit Modal -->
<div class="modal-overlay" id="zone-modal">
    <div class="modal-box">
        <h3 id="modal-title">New Zone</h3>
        <div class="field">
            <label for="modal-zone-name">Zone Name</label>
            <input type="text" id="modal-zone-name" placeholder="e.g. Market Road Commercial">
        </div>
        <div class="field">
            <label for="modal-zone-capacity">Max Vendor Capacity</label>
            <input type="number" id="modal-zone-capacity" value="10" min="1">
        </div>
        <div class="field">
            <label for="modal-zone-description">Description</label>
            <textarea id="modal-zone-description" rows="3" placeholder="Market area, nearby landmarks, or allocation notes"></textarea>
        </div>
        <div class="field">
            <label for="modal-zone-status">Status</label>
            <select id="modal-zone-status">
                <option value="available">Available</option>
                <option value="not_available">Not Available</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeZoneModal()">Cancel</button>
            <button class="btn-save" onclick="saveZoneFromModal()">Save Zone</button>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Map Logic -->
<script src="/street_vendor/assets/js/map.js"></script>
<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    if (tab === 'zones') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tab-zones').classList.add('active');
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('tab-vendors').classList.add('active');
    }
}
function toggleTheme() {
    const html = document.documentElement;
    const curr = html.getAttribute('data-bs-theme');
    const next = curr === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('adminTheme', next);
    document.querySelector('#theme-toggle-map i').className = next === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
}
if(document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle-map i').className = 'bx bx-sun';
}
</script>
</body>
</html>
