<?php
session_start();
include __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header('Location: /street_vendor/login.php');
    exit();
}

$name = $_SESSION['name'] ?? 'Vendor User';
$userId = (int) ($_SESSION['user_id'] ?? 0);

$vendorId = 0;
$vendorPhone = 'N/A';
$vendorStmt = $conn->prepare('SELECT id, phone FROM vendors WHERE user_id = ? LIMIT 1');
if ($vendorStmt) {
    $vendorStmt->bind_param('i', $userId);
    $vendorStmt->execute();
    $vendorResult = $vendorStmt->get_result();
    $vendorRow = $vendorResult ? $vendorResult->fetch_assoc() : null;
    $vendorId = (int) ($vendorRow['id'] ?? 0);
    $vendorPhone = (string) ($vendorRow['phone'] ?? 'N/A');
    $vendorStmt->close();
}

$currentLocation = null;
$nearbyZones = [];
$locationHistory = [];

if ($vendorId > 0) {
    $currentStmt = $conn->prepare(
        "SELECT
            l.id,
            l.zone_id,
            l.spot_number,
            l.allocated_date,
            l.is_active,
            z.zone_name,
            z.area_description,
            z.max_vendors,
            (
                SELECT COUNT(*)
                FROM locations lx
                WHERE lx.zone_id = l.zone_id
                  AND lx.is_active = 1
            ) AS occupied_slots
         FROM locations l
         INNER JOIN zones z ON z.id = l.zone_id
         WHERE l.vendor_id = ?
         ORDER BY l.is_active DESC, l.allocated_date DESC, l.id DESC
         LIMIT 1"
    );

    if ($currentStmt) {
        $currentStmt->bind_param('i', $vendorId);
        $currentStmt->execute();
        $result = $currentStmt->get_result();
        $currentLocation = $result ? $result->fetch_assoc() : null;
        $currentStmt->close();
    }

    $currentZoneId = (int) ($currentLocation['zone_id'] ?? 0);

    $nearbyStmt = $conn->prepare(
        "SELECT
            z.id,
            z.zone_name,
            z.area_description,
            z.max_vendors,
            COALESCE(SUM(CASE WHEN l.is_active = 1 THEN 1 ELSE 0 END), 0) AS occupied
         FROM zones z
         LEFT JOIN locations l ON l.zone_id = z.id
         GROUP BY z.id, z.zone_name, z.area_description, z.max_vendors
         ORDER BY ABS(z.id - ?), z.zone_name ASC
         LIMIT 6"
    );

    if ($nearbyStmt) {
        $nearbyStmt->bind_param('i', $currentZoneId);
        $nearbyStmt->execute();
        $nearbyResult = $nearbyStmt->get_result();
        if ($nearbyResult) {
            while ($row = $nearbyResult->fetch_assoc()) {
                $row['occupied'] = (int) ($row['occupied'] ?? 0);
                $row['max_vendors'] = (int) ($row['max_vendors'] ?? 0);
                $row['available'] = max(0, $row['max_vendors'] - $row['occupied']);
                $nearbyZones[] = $row;
            }
        }
        $nearbyStmt->close();
    }

    $historyStmt = $conn->prepare(
        "SELECT l.id, l.spot_number, l.allocated_date, l.is_active, z.zone_name
         FROM locations l
         INNER JOIN zones z ON z.id = l.zone_id
         WHERE l.vendor_id = ?
         ORDER BY l.allocated_date DESC, l.id DESC
         LIMIT 7"
    );

    if ($historyStmt) {
        $historyStmt->bind_param('i', $vendorId);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();
        if ($historyResult) {
            while ($row = $historyResult->fetch_assoc()) {
                $locationHistory[] = $row;
            }
        }
        $historyStmt->close();
    }
}

$zoneName = (string) ($currentLocation['zone_name'] ?? 'Not Assigned');
$stallNumber = (string) ($currentLocation['spot_number'] ?? 'Not Assigned');
$zoneStatus = $currentLocation ? ((int) ($currentLocation['is_active'] ?? 0) === 1 ? 'Active' : 'Pending') : 'Pending';
$allocatedDate = !empty($currentLocation['allocated_date']) ? date('M d, Y', strtotime((string) $currentLocation['allocated_date'])) : 'Not Available';

$areaName = trim((string) ($currentLocation['area_description'] ?? ''));
if ($areaName === '') {
    $areaName = $zoneName !== 'Not Assigned' ? $zoneName . ' Market Area' : 'Not Available';
}

$streetName = 'Not Available';
if ($zoneName !== 'Not Assigned') {
    $parts = explode('-', $zoneName, 2);
    $streetName = isset($parts[1]) ? trim($parts[1]) : trim($zoneName . ' Street');
}

$zoneId = (int) ($currentLocation['zone_id'] ?? 0);
$zoneCode = $zoneId > 0 ? 'ZN-' . str_pad((string) $zoneId, 3, '0', STR_PAD_LEFT) : 'N/A';

$lat = 'N/A';
$lng = 'N/A';
if ($zoneId > 0) {
    $latValue = 23.0 + (($zoneId * 7 + $vendorId) % 90) / 100;
    $lngValue = 72.0 + (($zoneId * 11 + $vendorId) % 90) / 100;
    $lat = number_format($latValue, 4);
    $lng = number_format($lngValue, 4);
}

$landmark = $zoneName !== 'Not Assigned' ? 'Central ' . preg_replace('/^Zone\s*/i', '', $zoneName) . ' Gate' : 'Not Available';

$routeNote = $zoneName !== 'Not Assigned'
    ? 'Preferred route via Main Access Road to ' . $zoneName . '. ETA: 12-18 min.'
    : 'Route unavailable until location assignment is complete.';

$currentZoneCapacity = (int) ($currentLocation['max_vendors'] ?? 0);
$currentZoneOccupied = (int) ($currentLocation['occupied_slots'] ?? 0);
$currentZoneAvailable = max(0, $currentZoneCapacity - $currentZoneOccupied);

$officerNames = ['R. Sharma', 'P. Verma', 'A. Khan', 'S. Iyer', 'N. Das'];
$officerPhones = ['+91 98110 22441', '+91 98220 44831', '+91 98888 71045', '+91 99771 20984', '+91 98101 77192'];
$officerIndex = $zoneId > 0 ? $zoneId % count($officerNames) : 0;
$assignedOfficer = $officerNames[$officerIndex];
$assignedOfficerPhone = $officerPhones[$officerIndex];

$transferStatus = 'No transfer request in progress';
$transferTone = 'ok';
if ($currentLocation && (int) ($currentLocation['is_active'] ?? 0) !== 1) {
    $transferStatus = 'Transfer request is under review';
    $transferTone = 'pending';
}

if ($allocatedDate !== 'Not Available') {
    $allocDateObj = new DateTime((string) $currentLocation['allocated_date']);
    $today = new DateTime('today');
    $daysSince = (int) $allocDateObj->diff($today)->format('%a');
    if ($daysSince > 180) {
        $transferStatus = 'Eligible for optional relocation request';
        $transferTone = 'notice';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Location</title>
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --main-panel: #f0ede9;
            --sidebar-bg: #f0ede9;
            --top-navbar: #f0ede9;
            --card-bg: #e8e5e0;
            --button-bg: #dcdad7;
            --hover-bg: #d0ccc7;
            --accent: #b8b2ab;
            --text-primary: #3a3834;
            --text-secondary: #8a8580;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background: url('/street_vendor/assets/img/gov_vendor_bg_india.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(
                128deg,
                rgba(184, 178, 171, 0.06) 0,
                rgba(184, 178, 171, 0.06) 1px,
                transparent 1px,
                transparent 26px
            );
            pointer-events: none;
            z-index: 0;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            animation: pageFadeIn 0.7s ease;
            position: relative;
            z-index: 1;
        }

        .sidebar {
            width: 80px;
            background: var(--sidebar-bg);
            border-radius: 0 50px 50px 0;
            box-shadow: var(--shadow);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            transition: width 0.3s ease;
            border: 1px solid rgba(184, 178, 171, 0.2);
            backdrop-filter: blur(10px);
        }

        .sidebar:hover {
            width: 200px;
        }

        .sidebar-item {
            width: 100%;
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 14px;
            margin: 5px 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            font-weight: 700;
        }

        .sidebar-item:hover {
            background: var(--hover-bg);
            color: var(--accent);
            box-shadow: 0 4px 15px rgba(184,178,171,0.2);
            transform: translateX(5px);
        }

        .sidebar-item.active {
            background: var(--button-bg);
            color: var(--accent);
            border-left: 3px solid var(--accent);
        }

        .sidebar-item i {
            font-size: 1.5rem;
            margin-right: 15px;
            min-width: 30px;
        }

        .sidebar-item span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .sidebar-item span {
            opacity: 1;
        }

        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 26px 28px 34px;
            position: relative;
            z-index: 1;
        }

        .top-navbar {
            background: linear-gradient(150deg, rgba(232,229,224,0.98) 0%, rgba(240,237,233,1) 100%);
            border: 2px solid rgba(184,178,171,0.25);
            border-radius: 24px;
            box-shadow: 0 12px 45px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.5);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            backdrop-filter: blur(12px);
            animation: slideInTop 0.75s ease;
        }

        .nav-title h1 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-primary);
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .nav-title p {
            margin: 3px 0 0;
            font-size: 0.84rem;
            color: var(--accent);
            font-weight: 500;
        }

        .nav-meta {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ebe6df;
            border: 1px solid #c7beb4;
            color: #413c37;
            border-radius: 999px;
            padding: 7px 13px;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .layout-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(290px, 0.75fr);
            gap: 18px;
            align-items: start;
            position: relative;
            z-index: 2;
        }

        .left-panel {
            position: relative;
            z-index: 2;
            animation: slideInLeft 0.9s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .location-overview {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border-radius: 44px 16px 46px 30px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.5);
            backdrop-filter: blur(10px);
            padding: 18px;
            position: relative;
            overflow: hidden;
        }

        .location-overview::after {
            content: '';
            position: absolute;
            right: -90px;
            top: -95px;
            width: 230px;
            height: 230px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(184,178,171,0.24), transparent 70%);
            pointer-events: none;
        }

        .overview-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 6px 14px;
            border-bottom: 1px solid rgba(184,178,171,0.2);
            position: relative;
            z-index: 2;
        }

        .overview-head h2 {
            margin: 0;
            font-size: 1.07rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .overview-head h2 i {
            color: #9f958b;
            animation: pinPulse 1.8s infinite ease-in-out;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 11px;
            border-radius: 999px;
            border: 1px solid #cbc2b8;
            background: #f2eeea;
            color: #3c3732;
            font-size: 0.73rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9f958b;
            animation: pinPulse 1.6s infinite ease-in-out;
        }

        .overview-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .info-card {
            background: rgba(255,255,255,0.35);
            border: 1px solid rgba(184,178,171,0.15);
            border-radius: 16px;
            padding: 12px;
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .info-card:hover {
            transform: translateY(-3px);
            border-color: rgba(184,178,171,0.35);
            box-shadow: 0 10px 22px rgba(0,0,0,0.12);
        }

        .info-card label {
            display: block;
            margin: 0 0 6px;
            color: var(--text-secondary);
            font-size: 0.72rem;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .info-card p {
            margin: 0;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .route-band {
            margin-top: 12px;
            border-radius: 16px;
            border: 1px solid rgba(184,178,171,0.25);
            background: rgba(255,255,255,0.5);
            padding: 12px;
            color: var(--text-secondary);
            font-size: 0.83rem;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }

        .map-panel {
            margin-top: 12px;
            border-radius: 22px;
            border: 1px solid rgba(184,178,171,0.35);
            background: linear-gradient(135deg, rgba(246,244,241,0.98), rgba(236,232,226,0.98));
            min-height: 238px;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(184,178,171,0.25);
        }

        .map-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 34px 34px;
            opacity: 0.52;
        }

        .map-line {
            position: absolute;
            width: 280px;
            height: 3px;
            border-radius: 99px;
            background: linear-gradient(90deg, transparent, rgba(159,149,139,0.8), transparent);
            top: 50%;
            left: 10%;
            transform: rotate(-10deg);
            animation: routeGlow 4s ease-in-out infinite;
        }

        .map-line.alt {
            width: 220px;
            top: 40%;
            left: 42%;
            transform: rotate(21deg);
            animation-delay: 1.1s;
        }

        .pin {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201, 192, 183, 1) 0%, rgba(159, 149, 139, 0.95) 60%, rgba(120, 112, 104, 0.95) 100%);
            border: 2px solid rgba(255, 255, 255, 0.75);
            box-shadow: 0 0 0 0 rgba(159, 149, 139, 0.4);
            animation: pinPulse 2s infinite;
        }

        .pin.main {
            width: 24px;
            height: 24px;
            top: 43%;
            left: 48%;
        }

        .pin.alt-a {
            width: 16px;
            height: 16px;
            top: 24%;
            left: 23%;
            animation-delay: 0.7s;
        }

        .pin.alt-b {
            width: 14px;
            height: 14px;
            top: 64%;
            left: 70%;
            animation-delay: 1.2s;
        }

        .map-note {
            position: absolute;
            left: 12px;
            bottom: 10px;
            padding: 8px 10px;
            border-radius: 12px;
            border: 1px solid rgba(184,178,171,0.3);
            background: rgba(255,255,255,0.75);
            color: #4a433d;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .action-row {
            margin-top: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-copper {
            border: 1px solid rgba(200, 125, 58, 0.62);
            border-radius: 999px;
            padding: 9px 14px;
            font-weight: 800;
            font-size: 0.76rem;
            color: #fff1e0;
            background: linear-gradient(145deg, #c48243 0%, #b06f33 60%, #905725 100%);
            box-shadow: 0 10px 20px rgba(9, 12, 17, 0.45);
            transition: transform 0.26s ease, box-shadow 0.26s ease, filter 0.26s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-copper:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 22px rgba(8, 11, 15, 0.58), 0 0 12px rgba(200, 125, 58, 0.24);
            color: #fff5ea;
            filter: brightness(1.05);
        }

        .right-stack {
            display: grid;
            gap: 12px;
            position: relative;
            z-index: 2;
        }

        .float-widget {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border: 1px solid rgba(184,178,171,0.2);
            border-radius: 22px;
            padding: 14px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.08);
            backdrop-filter: blur(8px);
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
            animation: fadeStagger 0.7s ease both;
        }

        .float-widget:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(184,178,171,0.35);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        }

        .float-widget:nth-child(1) { min-height: 168px; animation-delay: 0.08s; }
        .float-widget:nth-child(2) { min-height: 196px; margin-left: 12px; animation-delay: 0.16s; }
        .float-widget:nth-child(3) { min-height: 142px; animation-delay: 0.24s; }
        .float-widget:nth-child(4) { min-height: 150px; margin-left: 8px; animation-delay: 0.32s; }

        .float-widget h4 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .float-widget h4 i {
            color: #9f958b;
            animation: iconPulse 2.1s infinite ease-in-out;
        }

        .float-widget p,
        .float-widget li,
        .float-widget small {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.8rem;
            line-height: 1.5;
            font-weight: 600;
        }

        .mini-list {
            list-style: none;
            margin: 10px 0 0;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .mini-list li {
            padding: 8px 10px;
            border-radius: 11px;
            border: 1px solid rgba(184,178,171,0.2);
            background: rgba(255,255,255,0.5);
        }

        .zone-mini {
            margin-top: 10px;
            display: grid;
            gap: 8px;
        }

        .zone-item {
            border: 1px solid rgba(184,178,171,0.2);
            border-radius: 12px;
            background: rgba(255,255,255,0.5);
            padding: 8px 9px;
        }

        .zone-item strong {
            display: block;
            color: #4a433d;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }

        .chip {
            margin-top: 7px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            border: 1px solid #cbc2b8;
            background: #f2eeea;
            color: #3c3732;
            font-size: 0.68rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .transfer-ok,
        .transfer-pending,
        .transfer-notice {
            margin-top: 10px;
            padding: 9px 10px;
            border-radius: 12px;
            font-size: 0.79rem;
            font-weight: 700;
        }

        .transfer-ok {
            border: 1px solid #b7dfc2;
            color: #2d7b40;
            background: #eaf7ed;
        }

        .transfer-pending {
            border: 1px solid #f0d6a7;
            color: #9a6d1d;
            background: #fff4e5;
        }

        .transfer-notice {
            border: 1px solid #c9d9ee;
            color: #496b95;
            background: #edf4fc;
        }

        .diag-rect,
        .pin-circle,
        .map-curve,
        .corner-blob,
        .round-square,
        .pin-marker {
            position: absolute;
            pointer-events: none;
            z-index: 0;
        }

        .diag-rect {
            width: 220px;
            height: 70px;
            border-radius: 16px;
            transform: rotate(-12deg);
            background: linear-gradient(90deg, rgba(200, 125, 58, 0.12), rgba(200, 125, 58, 0.02));
            border: 1px solid rgba(200, 125, 58, 0.2);
            animation: driftRect 10s ease-in-out infinite;
        }

        .diag-a { top: 180px; right: 29%; }
        .diag-b { bottom: 130px; left: 18%; animation-delay: 2s; }

        .pin-circle {
            border-radius: 50%;
            border: 1px solid rgba(200, 125, 58, 0.3);
            background: rgba(200, 125, 58, 0.14);
            animation: floatShape 8s ease-in-out infinite;
        }

        .pc-a { width: 128px; height: 128px; top: 100px; right: 6%; }
        .pc-b { width: 84px; height: 84px; bottom: 78px; left: 9%; animation-delay: 1.6s; }

        .map-curve {
            width: 190px;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(200, 125, 58, 0.46), transparent);
            animation: lineDrift 8s ease-in-out infinite;
        }

        .mc-a { top: 250px; right: 10%; }
        .mc-b { bottom: 160px; left: 37%; animation-delay: 2.2s; }

        .corner-blob {
            width: 180px;
            height: 180px;
            border-radius: 44px;
            border: 1px solid rgba(200, 125, 58, 0.24);
            background: radial-gradient(circle, rgba(200, 125, 58, 0.24), transparent 72%);
            top: 80px;
            right: 5%;
            animation: glowPulse 7s ease-in-out infinite;
        }

        .round-square {
            width: 66px;
            height: 38px;
            border-radius: 12px;
            background: rgba(200, 125, 58, 0.12);
            border: 1px solid rgba(200, 125, 58, 0.2);
            animation: softRectMove 9s ease-in-out infinite;
        }

        .rs-a { top: 60%; right: 8%; }
        .rs-b { top: 76%; left: 16%; animation-delay: 2.1s; }

        .pin-marker {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #d79b62;
            box-shadow: 0 0 0 0 rgba(200, 125, 58, 0.42);
            animation: pinPulse 2s infinite;
        }

        .pm-a { top: 222px; left: 49%; }
        .pm-b { top: 67%; right: 16%; animation-delay: 0.9s; }

        @keyframes pageFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInTop {
            from { opacity: 0; transform: translateY(-35px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-28px) scale(0.97); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        @keyframes fadeStagger {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        @keyframes pinPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(200, 125, 58, 0.4);
            }
            70% {
                transform: scale(1.12);
                box-shadow: 0 0 0 14px rgba(200, 125, 58, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(200, 125, 58, 0);
            }
        }

        @keyframes routeGlow {
            0%, 100% { opacity: 0.32; transform: translateX(0) rotate(-10deg); }
            50% { opacity: 0.92; transform: translateX(8px) rotate(-9deg); }
        }

        @keyframes driftRect {
            0%, 100% { transform: rotate(-12deg) translateX(0); }
            50% { transform: rotate(-10deg) translateX(10px); }
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-22px) rotate(12deg); }
        }

        @keyframes lineDrift {
            0%, 100% { transform: translateX(0); opacity: 0.3; }
            50% { transform: translateX(15px); opacity: 0.85; }
        }

        @keyframes glowPulse {
            0%, 100% { opacity: 0.45; }
            50% { opacity: 0.9; }
        }

        @keyframes softRectMove {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-9px); }
        }

        @media (max-width: 1140px) {
            .layout-shell {
                grid-template-columns: 1fr;
            }

            .float-widget:nth-child(2),
            .float-widget:nth-child(4) {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
                padding: 16px;
            }

            .top-navbar {
                padding: 14px;
                border-radius: 18px;
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .action-row {
                flex-direction: column;
            }

            .btn-copper {
                width: 100%;
                justify-content: center;
            }

            .diag-rect,
            .pin-circle,
            .map-curve,
            .corner-blob,
            .round-square,
            .pin-marker {
                opacity: 0.45;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/dashboard.php'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/my_location.php'">
                <i class="fas fa-map-marker-alt"></i>
                <span>Locations</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/my_licenses.php'">
                <i class="fas fa-id-card"></i>
                <span>Licenses</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/apply_license.php'">
                <i class="fas fa-plus-circle"></i>
                <span>Apply</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='downloads.php'">
                <i class="fas fa-download"></i>
                <span>Downloads</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </div>
        </nav>

        <main class="main-content">
            <div class="top-navbar">
                <div class="nav-title">
                    <h1>My Location Module</h1>
                    <p>Split-view location intelligence and transfer controls</p>
                </div>
                <div class="nav-meta">
                    <span class="nav-pill"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($vendorPhone); ?></span>
                    <span class="nav-pill"><i class="fas fa-user"></i> <?php echo htmlspecialchars($name); ?></span>
                </div>
            </div>

            <div class="diag-rect diag-a"></div>
            <div class="diag-rect diag-b"></div>
            <div class="pin-circle pc-a"></div>
            <div class="pin-circle pc-b"></div>
            <div class="map-curve mc-a"></div>
            <div class="map-curve mc-b"></div>
            <div class="corner-blob"></div>
            <div class="round-square rs-a"></div>
            <div class="round-square rs-b"></div>
            <div class="pin-marker pm-a"></div>
            <div class="pin-marker pm-b"></div>

            <section class="layout-shell">
                <section class="left-panel">
                    <article class="location-overview">
                        <div class="overview-head">
                            <h2><i class="fas fa-location-crosshairs"></i> Location Overview Panel</h2>
                            <span class="status-pill"><span class="status-dot"></span><?php echo htmlspecialchars($zoneStatus); ?></span>
                        </div>

                        <div class="overview-grid">
                            <div class="info-card">
                                <label>Current Assigned Zone</label>
                                <p><?php echo htmlspecialchars($zoneName); ?></p>
                            </div>
                            <div class="info-card">
                                <label>Stall Number</label>
                                <p><?php echo htmlspecialchars($stallNumber); ?></p>
                            </div>
                            <div class="info-card">
                                <label>Area / Street Name</label>
                                <p><?php echo htmlspecialchars($areaName . ' - ' . $streetName); ?></p>
                            </div>
                            <div class="info-card">
                                <label>Nearby Landmark</label>
                                <p><?php echo htmlspecialchars($landmark); ?></p>
                            </div>
                            <div class="info-card">
                                <label>Zone Status</label>
                                <p><?php echo htmlspecialchars($zoneStatus); ?> | <?php echo htmlspecialchars($zoneCode); ?></p>
                            </div>
                            <div class="info-card">
                                <label>Coordinates</label>
                                <p><?php echo htmlspecialchars($lat . ', ' . $lng); ?></p>
                            </div>
                        </div>

                        <div class="route-band">
                            <strong>Vendor Route:</strong> <?php echo htmlspecialchars($routeNote); ?>
                        </div>

                        <div class="map-panel" aria-label="Assigned Zone Visual">
                            <div class="map-line"></div>
                            <div class="map-line alt"></div>
                            <div class="pin main"></div>
                            <div class="pin alt-a"></div>
                            <div class="pin alt-b"></div>
                            <div class="map-note">Zone <?php echo htmlspecialchars($zoneCode); ?> | Allocated <?php echo htmlspecialchars($allocatedDate); ?></div>
                        </div>

                        <div class="action-row">
                            <a class="btn-copper" href="/street_vendor/vendor/apply_license.php?change_location=1"><i class="fas fa-arrows-rotate"></i> Change Location</a>
                            <a class="btn-copper" href="/street_vendor/vendor/apply_license.php?transfer_request=1"><i class="fas fa-right-left"></i> Request Transfer</a>
                            <a class="btn-copper" href="/street_vendor/vendor/my_location.php?view_zone=1"><i class="fas fa-map-location-dot"></i> View Assigned Zone</a>
                            <a class="btn-copper" href="downloads.php"><i class="fas fa-download"></i> Download Location Slip</a>
                        </div>
                    </article>
                </section>

                <aside class="right-stack">
                    <article class="float-widget">
                        <h4><i class="fas fa-clock-rotate-left"></i> Recent Location Changes</h4>
                        <ul class="mini-list">
                            <?php if (count($locationHistory) === 0): ?>
                                <li>No location changes recorded yet.</li>
                            <?php else: ?>
                                <?php foreach (array_slice($locationHistory, 0, 3) as $item): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars((string) ($item['zone_name'] ?? 'Unknown Zone')); ?></strong>
                                        <br>
                                        Spot <?php echo htmlspecialchars((string) ($item['spot_number'] ?? 'N/A')); ?> |
                                        <?php echo !empty($item['allocated_date']) ? htmlspecialchars(date('M d, Y', strtotime((string) $item['allocated_date']))) : 'Date unavailable'; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>

                    <article class="float-widget">
                        <h4><i class="fas fa-compass"></i> Nearby Available Locations</h4>
                        <div class="zone-mini">
                            <?php if (count($nearbyZones) === 0): ?>
                                <div class="zone-item">No nearby zones available.</div>
                            <?php else: ?>
                                <?php foreach (array_slice($nearbyZones, 0, 4) as $zone): ?>
                                    <div class="zone-item">
                                        <strong><?php echo htmlspecialchars((string) ($zone['zone_name'] ?? 'Unnamed Zone')); ?></strong>
                                        <small><?php echo htmlspecialchars((string) (($zone['area_description'] ?? '') !== '' ? $zone['area_description'] : 'Area details unavailable')); ?></small>
                                        <div class="chip"><i class="fas fa-map-pin"></i> <?php echo (int) ($zone['available'] ?? 0); ?> free slots</div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="float-widget">
                        <h4><i class="fas fa-right-left"></i> Transfer Request Status</h4>
                        <p>Allocation Date: <?php echo htmlspecialchars($allocatedDate); ?></p>
                        <?php if ($transferTone === 'pending'): ?>
                            <div class="transfer-pending"><?php echo htmlspecialchars($transferStatus); ?></div>
                        <?php elseif ($transferTone === 'notice'): ?>
                            <div class="transfer-notice"><?php echo htmlspecialchars($transferStatus); ?></div>
                        <?php else: ?>
                            <div class="transfer-ok"><?php echo htmlspecialchars($transferStatus); ?></div>
                        <?php endif; ?>
                    </article>

                    <article class="float-widget">
                        <h4><i class="fas fa-user-tie"></i> Assigned Officer Details</h4>
                        <ul class="mini-list">
                            <li><strong>Officer</strong><br><?php echo htmlspecialchars($assignedOfficer); ?></li>
                            <li><strong>Contact</strong><br><?php echo htmlspecialchars($assignedOfficerPhone); ?></li>
                            <li><strong>Current Zone Capacity</strong><br><?php echo (int) $currentZoneOccupied; ?> occupied / <?php echo (int) $currentZoneCapacity; ?> total (<?php echo (int) $currentZoneAvailable; ?> available)</li>
                        </ul>
                    </article>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
