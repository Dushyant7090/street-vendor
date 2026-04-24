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
         LIMIT 5"
    );

    if ($nearbyStmt) {
        $nearbyStmt->bind_param('i', $currentZoneId);
        $nearbyStmt->execute();
        $nearbyResult = $nearbyStmt->get_result();
        if ($nearbyResult) {
            while ($row = $nearbyResult->fetch_assoc()) {
                $row['occupied'] = (int) ($row['occupied'] ?? 0);
                $row['max_vendors'] = (int) ($row['max_vendors'] ?? 0);
                $available = $row['max_vendors'] - $row['occupied'];
                $row['available'] = $available > 0 ? $available : 0;
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
         LIMIT 6"
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

$locationStatus = 'Pending';
if ($currentLocation) {
    $locationStatus = (int) ($currentLocation['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive';
}

$allocatedDate = !empty($currentLocation['allocated_date']) ? date('M d, Y', strtotime((string) $currentLocation['allocated_date'])) : 'Not Available';

$lat = 'N/A';
$lng = 'N/A';
if ($zoneId > 0) {
    $latValue = 23.0 + (($zoneId * 7 + $vendorId) % 90) / 100;
    $lngValue = 72.0 + (($zoneId * 11 + $vendorId) % 90) / 100;
    $lat = number_format($latValue, 4);
    $lng = number_format($lngValue, 4);
}

$availableSlots = 0;
$currentZoneCapacity = 0;
$currentZoneOccupied = 0;
if ($currentLocation) {
    $currentZoneCapacity = (int) ($currentLocation['max_vendors'] ?? 0);
    $currentZoneOccupied = (int) ($currentLocation['occupied_slots'] ?? 0);
    $availableSlots = max(0, $currentZoneCapacity - $currentZoneOccupied);
}

$officerNames = ['R. Sharma', 'P. Verma', 'A. Khan', 'S. Iyer', 'N. Das'];
$officerPhones = ['+91 98110 22441', '+91 98220 44831', '+91 98888 71045', '+91 99771 20984', '+91 98101 77192'];
$officerIndex = $zoneId > 0 ? $zoneId % count($officerNames) : 0;
$assignedOfficer = $officerNames[$officerIndex];
$assignedOfficerPhone = $officerPhones[$officerIndex];

$totalNearbyFree = 0;
foreach ($nearbyZones as $zone) {
    $totalNearbyFree += (int) ($zone['available'] ?? 0);
}

$routeNote = $zoneName !== 'Not Assigned'
    ? 'Route optimized toward ' . $zoneName . '. Estimated commute: 11-17 min.'
    : 'No assigned route yet. Assign location to enable route guidance.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Module</title>
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --main-panel: #3a4350;
            --sidebar-bg: #1c2229;
            --top-navbar: #252c35;
            --card-bg: rgba(56, 65, 78, 0.92);
            --button-bg: #b87333;
            --hover-bg: #c48243;
            --accent: #c87d3a;
            --text-primary: #f5efe6;
            --text-secondary: #d8cdc0;
            --shadow: 0 14px 34px rgba(10, 13, 17, 0.54);
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
            min-height: 100vh;
            background: url('/street_vendor/assets/img/gov_vendor_bg_india.png') no-repeat center center fixed;
            background-size: cover;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(
                120deg,
                rgba(255, 255, 255, 0.03) 0,
                rgba(255, 255, 255, 0.03) 1px,
                transparent 1px,
                transparent 30px
            );
            pointer-events: none;
            z-index: 0;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            z-index: 1;
            animation: pageFadeIn 0.8s ease;
        }

        .sidebar {
            width: 80px;
            background: linear-gradient(180deg, rgba(22, 27, 33, 0.98), rgba(35, 42, 51, 0.98));
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
            border: 1px solid rgba(200, 125, 58, 0.28);
            transition: width 0.3s ease, box-shadow 0.35s ease;
            backdrop-filter: blur(10px);
        }

        .sidebar:hover {
            width: 210px;
            box-shadow: 0 16px 38px rgba(8, 11, 15, 0.62), 0 0 18px rgba(200, 125, 58, 0.18);
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
            background: rgba(200, 125, 58, 0.2);
            color: #f7e8d7;
            transform: translateX(5px);
        }

        .sidebar-item.active {
            background: rgba(200, 125, 58, 0.3);
            color: #f7e8d7;
            border-left: 3px solid var(--accent);
        }

        .sidebar-item i {
            font-size: 1.4rem;
            margin-right: 15px;
            min-width: 30px;
            animation: iconPulse 2.4s infinite ease-in-out;
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
            background: linear-gradient(145deg, rgba(38, 46, 56, 0.93), rgba(50, 59, 70, 0.93));
            border: 1px solid rgba(200, 125, 58, 0.36);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            backdrop-filter: blur(12px);
            animation: slideInTop 0.8s ease;
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
            color: var(--text-secondary);
            font-weight: 600;
        }

        .nav-meta {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(200, 125, 58, 0.2);
            border: 1px solid rgba(200, 125, 58, 0.5);
            color: #f8e8d8;
            border-radius: 999px;
            padding: 7px 13px;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .location-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(300px, 0.75fr);
            gap: 18px;
            align-items: start;
            position: relative;
            z-index: 2;
        }

        .left-map-section {
            display: grid;
            gap: 14px;
            position: relative;
            z-index: 2;
        }

        .map-master-card {
            background: linear-gradient(155deg, rgba(56, 65, 78, 0.96), rgba(70, 80, 95, 0.96));
            border-radius: 42px 18px 44px 28px;
            border: 1.8px solid rgba(200, 125, 58, 0.48);
            box-shadow: 0 18px 42px rgba(9, 12, 17, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            overflow: hidden;
            position: relative;
            animation: mapCardReveal 0.92s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .map-master-card::before {
            content: '';
            position: absolute;
            right: -90px;
            top: -90px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200, 125, 58, 0.3), transparent 68%);
            pointer-events: none;
        }

        .map-header {
            padding: 18px 20px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(200, 125, 58, 0.28);
            position: relative;
            z-index: 2;
        }

        .map-header h3 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .map-header h3 i {
            color: #d79b62;
            animation: pinPulse 1.8s infinite ease-in-out;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(200, 125, 58, 0.45);
            background: rgba(200, 125, 58, 0.15);
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #f7e8d8;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d79b62;
            animation: pinPulse 1.6s infinite ease-in-out;
        }

        .location-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 12px;
            padding: 16px 20px;
            position: relative;
            z-index: 2;
        }

        .info-box {
            background: rgba(24, 30, 37, 0.48);
            border: 1px solid rgba(200, 125, 58, 0.3);
            border-radius: 16px;
            padding: 12px;
            transition: transform 0.26s ease, border-color 0.26s ease, box-shadow 0.26s ease;
        }

        .info-box:hover {
            transform: translateY(-3px);
            border-color: rgba(200, 125, 58, 0.62);
            box-shadow: 0 12px 24px rgba(8, 11, 15, 0.52), 0 0 10px rgba(200, 125, 58, 0.2);
        }

        .info-box label {
            display: block;
            margin: 0 0 6px;
            color: #e4d7c8;
            font-size: 0.72rem;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .info-box p {
            margin: 0;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .map-preview {
            margin: 0 20px 16px;
            border-radius: 22px;
            border: 1px solid rgba(200, 125, 58, 0.45);
            background: linear-gradient(135deg, rgba(35, 42, 50, 0.95), rgba(52, 62, 74, 0.95));
            min-height: 250px;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        }

        .map-preview::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 36px 36px;
            opacity: 0.5;
        }

        .route-line {
            position: absolute;
            width: 280px;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(200, 125, 58, 0.8), transparent);
            top: 52%;
            left: 14%;
            border-radius: 99px;
            transform: rotate(-12deg);
            animation: routeGlow 4s ease-in-out infinite;
        }

        .route-line.alt {
            width: 220px;
            top: 40%;
            left: 40%;
            transform: rotate(23deg);
            animation-delay: 1.2s;
        }

        .map-pin {
            position: absolute;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(231, 179, 128, 1) 0%, rgba(200, 125, 58, 0.95) 60%, rgba(145, 86, 39, 0.95) 100%);
            border: 2px solid rgba(255, 230, 205, 0.7);
            box-shadow: 0 0 0 0 rgba(200, 125, 58, 0.45);
            animation: pinPulse 2s infinite;
        }

        .map-pin.main {
            top: 42%;
            left: 48%;
        }

        .map-pin.alt-1 {
            top: 24%;
            left: 23%;
            width: 16px;
            height: 16px;
            animation-delay: 0.6s;
        }

        .map-pin.alt-2 {
            top: 62%;
            left: 70%;
            width: 14px;
            height: 14px;
            animation-delay: 1.1s;
        }

        .map-caption {
            position: absolute;
            left: 14px;
            bottom: 12px;
            padding: 8px 10px;
            border-radius: 12px;
            border: 1px solid rgba(200, 125, 58, 0.36);
            background: rgba(24, 30, 37, 0.72);
            color: #f2e7db;
            font-size: 0.77rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .quick-actions {
            padding: 0 20px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-quick {
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

        .btn-quick:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 22px rgba(8, 11, 15, 0.58), 0 0 12px rgba(200, 125, 58, 0.24);
            color: #fff5ea;
            filter: brightness(1.05);
        }

        .history-card {
            background: linear-gradient(150deg, rgba(56, 65, 78, 0.9), rgba(69, 80, 94, 0.9));
            border: 1px solid rgba(200, 125, 58, 0.4);
            border-radius: 26px;
            padding: 16px;
            box-shadow: 0 10px 26px rgba(10, 13, 18, 0.52);
            backdrop-filter: blur(8px);
            animation: slideInUp 1s ease;
        }

        .history-card h4 {
            margin: 0 0 12px;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 9px;
        }

        .timeline li {
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(200, 125, 58, 0.24);
            background: rgba(25, 31, 38, 0.42);
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .timeline li strong {
            color: #f6e9db;
            font-weight: 800;
        }

        .timeline .badge-mini {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 999px;
            margin-left: 8px;
            font-size: 0.64rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 800;
            border: 1px solid rgba(200, 125, 58, 0.4);
            color: #f7e8d7;
            background: rgba(200, 125, 58, 0.14);
        }

        .right-stack {
            display: grid;
            gap: 12px;
            position: sticky;
            top: 16px;
            z-index: 2;
        }

        .float-card {
            background: linear-gradient(148deg, rgba(56, 65, 78, 0.9), rgba(69, 80, 94, 0.9));
            border: 1px solid rgba(200, 125, 58, 0.4);
            border-radius: 22px;
            padding: 14px;
            box-shadow: 0 10px 24px rgba(10, 13, 18, 0.5);
            backdrop-filter: blur(8px);
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
            animation: floatCard 6.2s ease-in-out infinite;
        }

        .float-card:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(200, 125, 58, 0.66);
            box-shadow: 0 15px 30px rgba(8, 12, 17, 0.62), 0 0 14px rgba(200, 125, 58, 0.2);
        }

        .float-card:nth-child(2) { animation-delay: 0.5s; }
        .float-card:nth-child(3) { animation-delay: 1.0s; }
        .float-card:nth-child(4) { animation-delay: 1.5s; }
        .float-card:nth-child(5) { animation-delay: 2.0s; }

        .float-card h4 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .float-card h4 i {
            color: #d79b62;
            animation: iconPulse 2.1s infinite ease-in-out;
        }

        .float-card p,
        .float-card li,
        .float-card small {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.8rem;
            line-height: 1.5;
            font-weight: 600;
        }

        .zone-mini-grid {
            margin-top: 10px;
            display: grid;
            gap: 8px;
        }

        .zone-mini {
            border: 1px solid rgba(200, 125, 58, 0.24);
            border-radius: 12px;
            background: rgba(25, 31, 38, 0.45);
            padding: 8px 9px;
        }

        .zone-mini strong {
            display: block;
            color: #f6e9db;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }

        .metric-chip {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            border: 1px solid rgba(200, 125, 58, 0.44);
            background: rgba(200, 125, 58, 0.14);
            color: #f7e8d7;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .diag-panel,
        .corner-shape,
        .route-particle,
        .floating-circle,
        .soft-rect,
        .pin-dot {
            position: absolute;
            pointer-events: none;
            z-index: 0;
        }

        .diag-panel {
            width: 230px;
            height: 72px;
            border-radius: 16px;
            transform: rotate(-12deg);
            background: linear-gradient(90deg, rgba(200, 125, 58, 0.12), rgba(200, 125, 58, 0.02));
            border: 1px solid rgba(200, 125, 58, 0.2);
            animation: driftRect 10s ease-in-out infinite;
        }

        .diag-a { top: 170px; right: 30%; }
        .diag-b { bottom: 130px; left: 22%; animation-delay: 2s; }

        .corner-shape {
            width: 170px;
            height: 170px;
            border-radius: 44px;
            border: 1px solid rgba(200, 125, 58, 0.24);
            background: radial-gradient(circle, rgba(200, 125, 58, 0.2), transparent 72%);
            animation: cornerPulse 7s ease-in-out infinite;
        }

        .corner-a { top: 82px; right: 5%; }

        .route-particle {
            width: 200px;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(200, 125, 58, 0.48), transparent);
            animation: lineDrift 8s ease-in-out infinite;
        }

        .route-a { top: 250px; right: 12%; }
        .route-b { bottom: 160px; left: 36%; animation-delay: 2.2s; }

        .floating-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 1px solid rgba(200, 125, 58, 0.3);
            background: rgba(200, 125, 58, 0.14);
            animation: floatShape 8s ease-in-out infinite;
        }

        .circ-a { top: 110px; left: 6%; }
        .circ-b { bottom: 70px; right: 24%; width: 88px; height: 88px; animation-delay: 1.8s; }

        .soft-rect {
            width: 68px;
            height: 40px;
            border-radius: 12px;
            background: rgba(200, 125, 58, 0.12);
            border: 1px solid rgba(200, 125, 58, 0.2);
            animation: softRectMove 9s ease-in-out infinite;
        }

        .srect-a { top: 58%; right: 8%; }
        .srect-b { top: 75%; left: 16%; animation-delay: 2.3s; }

        .pin-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #d79b62;
            box-shadow: 0 0 0 0 rgba(200, 125, 58, 0.42);
            animation: pinPulse 2s infinite;
        }

        .dot-a { top: 220px; left: 47%; }
        .dot-b { top: 68%; right: 16%; animation-delay: 0.9s; }

        @keyframes pageFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInTop {
            from { opacity: 0; transform: translateY(-35px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes mapCardReveal {
            from { opacity: 0; transform: translateY(30px) scale(0.96) rotateX(6deg); }
            to { opacity: 1; transform: translateY(0) scale(1) rotateX(0); }
        }

        @keyframes floatCard {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
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
            0%, 100% { opacity: 0.32; transform: translateX(0) rotate(-12deg); }
            50% { opacity: 0.92; transform: translateX(8px) rotate(-11deg); }
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-22px) rotate(12deg); }
        }

        @keyframes driftRect {
            0%, 100% { transform: rotate(-12deg) translateX(0); }
            50% { transform: rotate(-10deg) translateX(10px); }
        }

        @keyframes cornerPulse {
            0%, 100% { opacity: 0.45; }
            50% { opacity: 0.9; }
        }

        @keyframes lineDrift {
            0%, 100% { transform: translateX(0); opacity: 0.3; }
            50% { transform: translateX(15px); opacity: 0.85; }
        }

        @keyframes softRectMove {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-9px); }
        }

        @media (max-width: 1160px) {
            .location-layout {
                grid-template-columns: 1fr;
            }

            .right-stack {
                position: static;
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

            .location-info-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                flex-direction: column;
            }

            .btn-quick {
                width: 100%;
                justify-content: center;
            }

            .diag-panel,
            .corner-shape,
            .route-particle,
            .floating-circle,
            .soft-rect,
            .pin-dot {
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
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/location.php'">
                <i class="fas fa-map-marker-alt"></i>
                <span>Locations</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/license.php'">
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
                    <h1>Location Management Module</h1>
                    <p>Advanced map-style control center for assigned vendor location</p>
                </div>
                <div class="nav-meta">
                    <span class="nav-pill"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($vendorPhone); ?></span>
                    <span class="nav-pill"><i class="fas fa-user"></i> <?php echo htmlspecialchars($name); ?></span>
                </div>
            </div>

            <div class="diag-panel diag-a"></div>
            <div class="diag-panel diag-b"></div>
            <div class="corner-shape corner-a"></div>
            <div class="route-particle route-a"></div>
            <div class="route-particle route-b"></div>
            <div class="floating-circle circ-a"></div>
            <div class="floating-circle circ-b"></div>
            <div class="soft-rect srect-a"></div>
            <div class="soft-rect srect-b"></div>
            <div class="pin-dot dot-a"></div>
            <div class="pin-dot dot-b"></div>

            <section class="location-layout">
                <section class="left-map-section">
                    <article class="map-master-card">
                        <div class="map-header">
                            <h3><i class="fas fa-location-crosshairs"></i> Current Assigned Location</h3>
                            <span class="status-indicator">
                                <span class="status-dot"></span>
                                <?php echo htmlspecialchars($locationStatus); ?>
                            </span>
                        </div>

                        <div class="location-info-grid">
                            <div class="info-box">
                                <label>Zone Name</label>
                                <p><?php echo htmlspecialchars($zoneName); ?></p>
                            </div>
                            <div class="info-box">
                                <label>Stall Number</label>
                                <p><?php echo htmlspecialchars($stallNumber); ?></p>
                            </div>
                            <div class="info-box">
                                <label>Area Name</label>
                                <p><?php echo htmlspecialchars($areaName); ?></p>
                            </div>
                            <div class="info-box">
                                <label>Street Name</label>
                                <p><?php echo htmlspecialchars($streetName); ?></p>
                            </div>
                            <div class="info-box">
                                <label>Zone Code</label>
                                <p><?php echo htmlspecialchars($zoneCode); ?></p>
                            </div>
                            <div class="info-box">
                                <label>Coordinates</label>
                                <p><?php echo htmlspecialchars($lat . ', ' . $lng); ?></p>
                            </div>
                        </div>

                        <div class="map-preview" aria-label="Map Preview Area">
                            <div class="route-line"></div>
                            <div class="route-line alt"></div>
                            <div class="map-pin main" title="Assigned Location"></div>
                            <div class="map-pin alt-1" title="Nearby Point"></div>
                            <div class="map-pin alt-2" title="Nearby Point"></div>
                            <div class="map-caption">
                                Zone: <?php echo htmlspecialchars($zoneCode); ?> | Allocated: <?php echo htmlspecialchars($allocatedDate); ?>
                            </div>
                        </div>

                        <div class="quick-actions">
                            <a class="btn-quick" href="/street_vendor/vendor/apply_license.php?change_location=1"><i class="fas fa-arrows-rotate"></i> Change Location</a>
                            <a class="btn-quick" href="/street_vendor/vendor/apply_license.php?transfer_request=1"><i class="fas fa-right-left"></i> Request Transfer</a>
                            <a class="btn-quick" href="/street_vendor/vendor/location.php?view_map=1"><i class="fas fa-map"></i> View Map</a>
                            <a class="btn-quick" href="downloads.php"><i class="fas fa-download"></i> Download Slip</a>
                        </div>
                    </article>

                    <article class="history-card">
                        <h4><i class="fas fa-timeline"></i> Location History</h4>
                        <ul class="timeline">
                            <?php if (count($locationHistory) === 0): ?>
                                <li>No location history available yet.</li>
                            <?php else: ?>
                                <?php foreach ($locationHistory as $entry): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars((string) ($entry['zone_name'] ?? 'Unknown Zone')); ?></strong>
                                        <span class="badge-mini"><?php echo (int) ($entry['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive'; ?></span>
                                        <br>
                                        Spot <?php echo htmlspecialchars((string) ($entry['spot_number'] ?? 'N/A')); ?> |
                                        <?php echo !empty($entry['allocated_date']) ? htmlspecialchars(date('M d, Y', strtotime((string) $entry['allocated_date']))) : 'Date unavailable'; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>
                </section>

                <aside class="right-stack">
                    <article class="float-card">
                        <h4><i class="fas fa-compass"></i> Nearby Zones</h4>
                        <div class="zone-mini-grid">
                            <?php if (count($nearbyZones) === 0): ?>
                                <div class="zone-mini">No nearby zones found.</div>
                            <?php else: ?>
                                <?php foreach ($nearbyZones as $zone): ?>
                                    <div class="zone-mini">
                                        <strong><?php echo htmlspecialchars((string) ($zone['zone_name'] ?? 'Unnamed Zone')); ?></strong>
                                        <small><?php echo htmlspecialchars((string) (($zone['area_description'] ?? '') !== '' ? $zone['area_description'] : 'Area info not available')); ?></small>
                                        <div class="metric-chip"><i class="fas fa-map-pin"></i> <?php echo (int) ($zone['available'] ?? 0); ?> free</div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="float-card">
                        <h4><i class="fas fa-chart-pie"></i> Available Slots</h4>
                        <p>Current zone occupancy and nearby free capacity.</p>
                        <div class="zone-mini-grid" style="margin-top:10px;">
                            <div class="zone-mini">
                                <strong>Current Zone Capacity</strong>
                                <small><?php echo (int) $currentZoneOccupied; ?> occupied / <?php echo (int) $currentZoneCapacity; ?> total</small>
                                <div class="metric-chip"><i class="fas fa-cubes"></i> <?php echo (int) $availableSlots; ?> available</div>
                            </div>
                            <div class="zone-mini">
                                <strong>Nearby Total Free Slots</strong>
                                <small>Calculated from top nearby zones list</small>
                                <div class="metric-chip"><i class="fas fa-layer-group"></i> <?php echo (int) $totalNearbyFree; ?> slots</div>
                            </div>
                        </div>
                    </article>

                    <article class="float-card">
                        <h4><i class="fas fa-user-tie"></i> Assigned Officer</h4>
                        <p><?php echo htmlspecialchars($assignedOfficer); ?></p>
                        <small><?php echo htmlspecialchars($assignedOfficerPhone); ?></small>
                        <div class="metric-chip"><i class="fas fa-badge-check"></i> Zone Supervisor</div>
                    </article>

                    <article class="float-card">
                        <h4><i class="fas fa-route"></i> Route / Direction</h4>
                        <p><?php echo htmlspecialchars($routeNote); ?></p>
                        <div class="metric-chip"><i class="fas fa-location-arrow"></i> Smart route active</div>
                    </article>

                    <article class="float-card">
                        <h4><i class="fas fa-bell"></i> Recent Location Updates</h4>
                        <ul class="timeline" style="margin-top:10px;">
                            <?php if (count($locationHistory) === 0): ?>
                                <li>No updates recorded yet.</li>
                            <?php else: ?>
                                <?php foreach (array_slice($locationHistory, 0, 3) as $update): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars((string) ($update['zone_name'] ?? 'Unknown')); ?></strong>
                                        <?php echo !empty($update['allocated_date']) ? htmlspecialchars(date('M d, Y', strtotime((string) $update['allocated_date']))) : 'N/A'; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
