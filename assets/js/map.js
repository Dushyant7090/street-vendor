// ========== STATE ==========
let map, drawnItems, drawControl;
let zonesLayer = L.layerGroup();
let vendorMarkers = L.layerGroup();
let allZones = [], allAssigned = [], allUnassigned = [];
let selectedVendorId = null;
let selectedApplicationId = null;
let editingZoneId = null;
let editingLayer = null;
let searchMarker = null;

// ========== INIT ==========
function initMap() {
    map = L.map('map', { zoomControl: false }).setView([15.4319, 75.6340], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);
    zonesLayer.addTo(map);
    vendorMarkers.addTo(map);

    // Click handler for vendor assignment
    map.on('click', function(e) {
        if (!selectedVendorId) return;
        handleAssignClick(e.latlng);
    });

    loadMapData();
}

// ========== DATA LOADING ==========
function loadMapData() {
    fetch('/street_vendor/api/get_map_data.php')
        .then(r => r.json())
        .then(res => {
            if (!res.success) { console.error(res.error); return; }
            allZones = res.data.zones;
            allAssigned = res.data.assigned_vendors;
            allUnassigned = res.data.unassigned_vendors;
            renderZonesOnMap();
            renderVendorMarkers();
            renderZonesList();
            renderUnassignedList();
        })
        .catch(err => console.error('Load error:', err));
}

// ========== ZONES ON MAP ==========
function getCapacityColor(occupied, max, status = 'available') {
    if (status !== 'available') return { color: '#ef4444', cls: 'red' };
    const pct = max > 0 ? (occupied / max) * 100 : 0;
    if (pct >= 100) return { color: '#ef4444', cls: 'red' };
    if (pct >= 70) return { color: '#eab308', cls: 'yellow' };
    return { color: '#22c55e', cls: 'green' };
}

function renderZonesOnMap() {
    zonesLayer.clearLayers();
    allZones.forEach(zone => {
        if (!zone.geometry) return;
        let geojson;
        try { geojson = JSON.parse(zone.geometry); } catch(e) { return; }

        const occ = parseInt(zone.occupied_spots || zone.current_capacity) || 0;
        const max = parseInt(zone.max_vendors || zone.max_capacity) || 1;
        const status = zone.status || 'available';
        const cap = getCapacityColor(occ, max, status);
        const slots = Math.max(max - occ, 0);

        const popupHtml = `
            <div style="min-width:180px">
                <strong style="font-size:14px">${esc(zone.zone_name)}</strong><br>
                <span style="font-size:12px;color:#666">Capacity: ${occ} / ${max}</span>
                <div style="font-size:12px;color:#666">Available slots: ${slots}</div>
                <div style="font-size:12px;color:#666">Status: ${status === 'available' ? 'Available' : 'Not Available'}</div>
                <div style="height:4px;background:#e5e7eb;border-radius:9px;margin:6px 0;overflow:hidden">
                    <div style="height:100%;width:${Math.min(100, Math.round(occ/max*100))}%;background:${cap.color};border-radius:9px"></div>
                </div>
                <div style="display:flex;gap:6px;margin-top:8px">
                    <button onclick="startEditZone(${zone.id})" style="flex:1;padding:4px 8px;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer;font-size:11px;font-weight:600">Edit</button>
                    <button onclick="deleteZone(${zone.id})" style="flex:1;padding:4px 8px;border:1px solid #fca5a5;border-radius:6px;background:#fff;color:#dc2626;cursor:pointer;font-size:11px;font-weight:600">Delete</button>
                </div>
            </div>
        `;

        const layer = L.geoJSON(geojson, {
            style: { color: cap.color, weight: 3, fillColor: cap.color, fillOpacity: 0.22 }
        });

        layer.bindPopup(popupHtml);

        layer.on('click', function() { layer.openPopup(); });
        zonesLayer.addLayer(layer);

        const bounds = layer.getBounds();
        if (bounds.isValid()) {
            const center = bounds.getCenter();
            const zoneMarker = L.circleMarker(center, {
                radius: 14,
                color: '#ffffff',
                weight: 3,
                fillColor: cap.color,
                fillOpacity: 0.95,
                opacity: 1
            });
            zoneMarker.bindPopup(popupHtml);
            zoneMarker.bindTooltip(esc(zone.zone_name), {
                permanent: false,
                direction: 'top',
                className: 'zone-center-tooltip'
            });
            zonesLayer.addLayer(zoneMarker);
        }
    });
}

// ========== LOCATION SEARCH ==========
function searchMapLocation(event) {
    if (event) event.preventDefault();

    const input = document.getElementById('map-search-input');
    const query = input ? input.value.trim() : '';
    if (!query) {
        alert('Enter a city, area, or street name to search.');
        return;
    }

    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query);
    fetch(url, { headers: { 'Accept': 'application/json' } })
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
            if (searchMarker) map.removeLayer(searchMarker);
            searchMarker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`<strong>Search result</strong><br>${esc(result.display_name || query)}`)
                .openPopup();
        })
        .catch(() => {
            alert('Location search failed. Check your internet connection and try again.');
        });
}

// ========== VENDOR MARKERS ==========
function renderVendorMarkers() {
    vendorMarkers.clearLayers();
    allAssigned.forEach(v => {
        const lat = parseFloat(v.latitude);
        const lng = parseFloat(v.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        const icon = L.divIcon({
            className: 'vendor-map-marker',
            html: `<div style="width:28px;height:28px;border-radius:50%;background:#8a7cee;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700">${esc(v.vendor_name?.charAt(0) || 'V')}</div>`,
            iconSize: [28, 28], iconAnchor: [14, 14]
        });

        const marker = L.marker([lat, lng], { icon });
        marker.bindPopup(`
            <strong>${esc(v.vendor_name)}</strong><br>
            <small style="color:#666">${esc(v.business_name || 'N/A')}</small>
        `);
        vendorMarkers.addLayer(marker);
    });
}

// ========== SIDEBAR: ZONES LIST ==========
function renderZonesList() {
    const container = document.getElementById('zones-list');
    const search = document.getElementById('zone-search')?.value?.toLowerCase() || '';

    const filtered = allZones.filter(z => z.zone_name.toLowerCase().includes(search));

    if (filtered.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="bx bx-map-pin" style="font-size:2rem"></i><br>No zones found</div>';
        return;
    }

    container.innerHTML = filtered.map(z => {
        const occ = parseInt(z.occupied_spots || z.current_capacity) || 0;
        const max = parseInt(z.max_vendors || z.max_capacity) || 1;
        const cap = getCapacityColor(occ, max, z.status || 'available');
        const pct = Math.min(100, Math.round(occ / max * 100));
        return `
            <div class="zone-list-item" onclick="flyToZone(${z.id})">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span class="zone-name">${esc(z.zone_name)}</span>
                    <span class="badge-cap ${cap.cls}">${occ}/${max}</span>
                </div>
                <div class="zone-capacity-bar"><div class="zone-capacity-fill" style="width:${pct}%;background:${cap.color}"></div></div>
            </div>`;
    }).join('');
}

function flyToZone(id) {
    const zone = allZones.find(z => z.id == id);
    if (!zone || !zone.geometry) return;
    try {
        const geo = JSON.parse(zone.geometry);
        const layer = L.geoJSON(geo);
        map.fitBounds(layer.getBounds(), { padding: [40, 40] });
    } catch(e) {}
}

// ========== SIDEBAR: UNASSIGNED VENDORS ==========
function renderUnassignedList() {
    const container = document.getElementById('unassigned-list');
    const search = document.getElementById('vendor-search')?.value?.toLowerCase() || '';

    const filtered = allUnassigned.filter(v =>
        (v.vendor_name || '').toLowerCase().includes(search) ||
        (v.business_name || '').toLowerCase().includes(search)
    );

    if (filtered.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="bx bx-user-check" style="font-size:2rem"></i><br>All vendors assigned</div>';
        return;
    }

    container.innerHTML = filtered.map(v => `
        <div class="vendor-list-item ${selectedVendorId == v.vendor_id ? 'selected' : ''}" onclick="selectVendor(${v.vendor_id})">
            <div class="vendor-avatar">${esc((v.vendor_name || 'V').charAt(0))}</div>
            <div class="vendor-info">
                <div class="vendor-name">${esc(v.vendor_name)}</div>
                <div class="vendor-biz">${esc(v.business_name || v.phone || 'No details')}</div>
            </div>
        </div>`).join('');
}

// ========== VENDOR ASSIGNMENT ==========
function selectVendor(id) {
    if (selectedVendorId === id) {
        selectedVendorId = null;
        selectedApplicationId = null;
        document.querySelector('.assign-banner').classList.remove('visible');
    } else {
        selectedVendorId = id;
        const v = allUnassigned.find(x => x.vendor_id == id);
        selectedApplicationId = v ? (v.application_id || null) : null;
        document.getElementById('assign-vendor-name').textContent = v ? v.vendor_name : 'Vendor';
        document.querySelector('.assign-banner').classList.add('visible');
    }
    renderUnassignedList();
}

function cancelAssign() {
    selectedVendorId = null;
    selectedApplicationId = null;
    document.querySelector('.assign-banner').classList.remove('visible');
    renderUnassignedList();
}

function handleAssignClick(latlng) {
    // Find which zone this point falls in
    let targetZone = null;
    zonesLayer.eachLayer(gLayer => {
        gLayer.eachLayer(subLayer => {
            if (subLayer.getBounds && subLayer.getBounds().contains(latlng)) {
                // rough check with bounds, more accurate with turf but this is sufficient
                targetZone = allZones.find(z => {
                    if (!z.geometry) return false;
                    try {
                        const geo = JSON.parse(z.geometry);
                        const poly = L.geoJSON(geo);
                        return poly.getBounds().contains(latlng);
                    } catch(e) { return false; }
                });
            }
        });
    });

    if (!targetZone) {
        alert('Please click inside a zone boundary to assign this vendor.');
        return;
    }

    const occ = parseInt(targetZone.occupied_spots || targetZone.current_capacity) || 0;
    const max = parseInt(targetZone.max_vendors || targetZone.max_capacity) || 1;
    if ((targetZone.status || 'available') !== 'available' || occ >= max) {
        alert(`Zone "${targetZone.zone_name}" is at full capacity (${occ}/${max}). Choose another zone.`);
        return;
    }

    if (occ >= max * 0.7) {
        if (!confirm(`Warning: Zone "${targetZone.zone_name}" is near capacity (${occ}/${max}). Proceed?`)) return;
    }

    fetch('/street_vendor/api/assign_vendor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            vendor_id: selectedVendorId,
            application_id: selectedApplicationId,
            zone_id: targetZone.id,
            latitude: latlng.lat,
            longitude: latlng.lng,
            spot_number: prompt('Enter spot number for this assignment:', '') || ''
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            cancelAssign();
            loadMapData();
        } else {
            alert('Assignment failed: ' + (res.error || 'Unknown error'));
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

// ========== ZONE CREATION ==========
function startDrawZone() {
    if (drawControl) { map.removeControl(drawControl); }
    drawnItems.clearLayers();

    drawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: { allowIntersection: false, shapeOptions: { color: '#8a7cee', weight: 2 } },
            polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false
        },
        edit: { featureGroup: drawnItems }
    });
    map.addControl(drawControl);

    map.once(L.Draw.Event.CREATED, function(e) {
        drawnItems.addLayer(e.layer);
        map.removeControl(drawControl);
        drawControl = null;
        showZoneModal(null, e.layer.toGeoJSON().geometry);
    });
}

// ========== ZONE EDITING ==========
function startEditZone(id) {
    const zone = allZones.find(z => z.id == id);
    if (!zone) return;
    map.closePopup();
    editingZoneId = id;
    showZoneModal(zone, zone.geometry ? JSON.parse(zone.geometry) : null);
}

// ========== ZONE DELETION ==========
function deleteZone(id) {
    const zone = allZones.find(z => z.id == id);
    if (!confirm(`Delete zone "${zone?.zone_name}"? This cannot be undone.`)) return;
    map.closePopup();

    fetch('/street_vendor/api/delete_zone.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) loadMapData();
        else alert('Delete failed: ' + (res.error || 'Unknown'));
    })
    .catch(err => alert('Network error: ' + err.message));
}

// ========== MODAL ==========
function showZoneModal(zone, geometry) {
    document.getElementById('modal-zone-name').value = zone ? zone.zone_name : '';
    document.getElementById('modal-zone-capacity').value = zone ? (zone.max_capacity || zone.max_vendors) : 10;
    document.getElementById('modal-zone-description').value = zone ? (zone.description || zone.area_description || '') : '';
    document.getElementById('modal-zone-status').value = zone ? (zone.status || (parseInt(zone.is_active) === 1 ? 'available' : 'not_available')) : 'available';
    document.getElementById('zone-modal').dataset.geometry = typeof geometry === 'string' ? geometry : JSON.stringify(geometry);
    document.getElementById('zone-modal').dataset.zoneId = zone ? zone.id : '';
    document.getElementById('zone-modal').classList.add('visible');
}

function closeZoneModal() {
    document.getElementById('zone-modal').classList.remove('visible');
    drawnItems.clearLayers();
    editingZoneId = null;
}

function saveZoneFromModal() {
    const modal = document.getElementById('zone-modal');
    const name = document.getElementById('modal-zone-name').value.trim();
    const cap = parseInt(document.getElementById('modal-zone-capacity').value) || 10;
    const description = document.getElementById('modal-zone-description').value.trim();
    const status = document.getElementById('modal-zone-status').value;
    const geoStr = modal.dataset.geometry;
    const zoneId = modal.dataset.zoneId;

    if (!name) { alert('Please enter a zone name.'); return; }
    if (!geoStr) { alert('No zone boundary defined.'); return; }

    let geometry;
    try { geometry = JSON.parse(geoStr); } catch(e) { alert('Invalid geometry data.'); return; }

    const payload = { zone_name: name, max_capacity: cap, max_vendors: cap, description, status, geometry };
    if (zoneId) payload.id = parseInt(zoneId);

    fetch('/street_vendor/api/save_zone.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) { closeZoneModal(); loadMapData(); }
        else alert('Save failed: ' + (res.error || 'Unknown'));
    })
    .catch(err => alert('Network error: ' + err.message));
}

// ========== UTILITY ==========
function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ========== BOOT ==========
document.addEventListener('DOMContentLoaded', initMap);
