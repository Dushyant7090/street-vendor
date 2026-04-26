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
$vendorCreatedAt = null;
$vendorLocation = 'Not Available';
$vendorStmt = $conn->prepare('SELECT id, name, phone, location, created_at FROM vendors WHERE user_id = ? LIMIT 1');
if ($vendorStmt) {
    $vendorStmt->bind_param('i', $userId);
    $vendorStmt->execute();
    $vendorResult = $vendorStmt->get_result();
    $vendor = $vendorResult ? $vendorResult->fetch_assoc() : null;
    $vendorId = (int) ($vendor['id'] ?? 0);
    $vendorPhone = (string) ($vendor['phone'] ?? 'N/A');
    $vendorLocation = trim((string) ($vendor['location'] ?? ''));
    if ($vendorLocation === '') {
        $vendorLocation = 'Not Available';
    }
    $vendorCreatedAt = !empty($vendor['created_at']) ? (string) $vendor['created_at'] : null;
    if (!empty($vendor['name'])) {
        $name = (string) $vendor['name'];
    }
    $vendorStmt->close();
}

$approvedLicense = null;
$latestLicense = null;
$currentLocation = null;

if ($vendorId > 0) {
    $approvedStmt = $conn->prepare(
        "SELECT id, license_number, status, issue_date, expiry_date, updated_at
         FROM licenses
         WHERE vendor_id = ? AND status = 'approved'
         ORDER BY issue_date DESC, id DESC
         LIMIT 1"
    );
    if ($approvedStmt) {
        $approvedStmt->bind_param('i', $vendorId);
        $approvedStmt->execute();
        $approvedRes = $approvedStmt->get_result();
        $approvedLicense = $approvedRes ? $approvedRes->fetch_assoc() : null;
        $approvedStmt->close();
    }

    $latestStmt = $conn->prepare(
        "SELECT id, license_number, status, created_at, updated_at
         FROM licenses
         WHERE vendor_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1"
    );
    if ($latestStmt) {
        $latestStmt->bind_param('i', $vendorId);
        $latestStmt->execute();
        $latestRes = $latestStmt->get_result();
        $latestLicense = $latestRes ? $latestRes->fetch_assoc() : null;
        $latestStmt->close();
    }

    $locationStmt = $conn->prepare(
        "SELECT l.id, l.spot_number, l.allocated_date, z.zone_name
         FROM locations l
         INNER JOIN zones z ON z.id = l.zone_id
         WHERE l.vendor_id = ?
         ORDER BY l.is_active DESC, l.allocated_date DESC, l.id DESC
         LIMIT 1"
    );
    if ($locationStmt) {
        $locationStmt->bind_param('i', $vendorId);
        $locationStmt->execute();
        $locationRes = $locationStmt->get_result();
        $currentLocation = $locationRes ? $locationRes->fetch_assoc() : null;
        $locationStmt->close();
    }
}

$documents = [
    [
        'key' => 'license_pdf',
        'title' => 'License PDF',
        'type' => 'PDF',
        'source' => 'Approved license',
        'generated' => $approvedLicense && !empty($approvedLicense['issue_date']) ? date('d M Y', strtotime((string) $approvedLicense['issue_date'])) : 'Awaiting approval',
        'generated_ts' => $approvedLicense && !empty($approvedLicense['issue_date']) ? strtotime((string) $approvedLicense['issue_date']) : null,
        'icon' => 'fa-file-pdf',
        'available' => (bool) $approvedLicense,
        'download' => $approvedLicense ? '/street_vendor/vendor/download_license.php?id=' . (int) $approvedLicense['id'] : '#',
        'preview' => '/street_vendor/vendor/my_licenses.php',
        'badge' => $approvedLicense ? 'Ready' : 'Pending',
        'downloadable' => (bool) $approvedLicense,
    ],
    [
        'key' => 'location_slip',
        'title' => 'Location Slip',
        'type' => 'PDF',
        'source' => 'Assigned location',
        'generated' => $currentLocation && !empty($currentLocation['allocated_date']) ? date('d M Y', strtotime((string) $currentLocation['allocated_date'])) : 'Pending allocation',
        'generated_ts' => $currentLocation && !empty($currentLocation['allocated_date']) ? strtotime((string) $currentLocation['allocated_date']) : null,
        'icon' => 'fa-map-location-dot',
        'available' => (bool) $currentLocation,
        'download' => $currentLocation ? '/street_vendor/vendor/location.php' : '#',
        'preview' => '/street_vendor/vendor/location.php',
        'badge' => $currentLocation ? 'Ready' : 'Pending',
        'downloadable' => (bool) $currentLocation,
    ],
    [
        'key' => 'application_receipt',
        'title' => 'Application Receipt',
        'type' => 'PDF',
        'source' => 'Latest application',
        'generated' => $latestLicense && !empty($latestLicense['created_at']) ? date('d M Y', strtotime((string) $latestLicense['created_at'])) : 'Not available',
        'generated_ts' => $latestLicense && !empty($latestLicense['created_at']) ? strtotime((string) $latestLicense['created_at']) : null,
        'icon' => 'fa-receipt',
        'available' => (bool) $latestLicense,
        'download' => $latestLicense ? '/street_vendor/vendor/apply_license.php' : '#',
        'preview' => '/street_vendor/vendor/apply_license.php',
        'badge' => $latestLicense ? 'Ready' : 'Pending',
        'downloadable' => (bool) $latestLicense,
    ],
    [
        'key' => 'vendor_id_card',
        'title' => 'Vendor ID Card',
        'type' => 'PNG',
        'source' => 'Vendor profile',
        'generated' => $vendorCreatedAt ? date('d M Y', strtotime($vendorCreatedAt)) : 'Profile created',
        'generated_ts' => $vendorCreatedAt ? strtotime($vendorCreatedAt) : null,
        'icon' => 'fa-id-card',
        'available' => true,
        'download' => '#',
        'preview' => '/street_vendor/vendor/profile.php',
        'badge' => 'Preview only',
        'downloadable' => false,
    ],
    [
        'key' => 'payment_receipt',
        'title' => 'Payment Receipt',
        'type' => 'PDF',
        'source' => 'Approved license payment',
        'generated' => $approvedLicense && !empty($approvedLicense['issue_date']) ? date('d M Y', strtotime((string) $approvedLicense['issue_date'])) : 'No paid record',
        'generated_ts' => $approvedLicense && !empty($approvedLicense['issue_date']) ? strtotime((string) $approvedLicense['issue_date']) : null,
        'icon' => 'fa-file-invoice-dollar',
        'available' => (bool) $approvedLicense,
        'download' => $approvedLicense ? '/street_vendor/vendor/my_licenses.php' : '#',
        'preview' => '/street_vendor/vendor/my_licenses.php',
        'badge' => $approvedLicense ? 'Ready' : 'Pending',
        'downloadable' => (bool) $approvedLicense,
    ],
];

$totalDocuments = count($documents);
$availableDocuments = 0;
$pendingFiles = 0;
$recentlyGenerated = 0;

foreach ($documents as $doc) {
    if ($doc['available']) {
        $availableDocuments++;
    } else {
        $pendingFiles++;
    }

    if (!empty($doc['generated_ts']) && $doc['generated_ts'] >= strtotime('-30 days')) {
        $recentlyGenerated++;
    }
}

$recentItems = array_values(array_filter($documents, static function (array $doc): bool {
    return $doc['available'] && !empty($doc['generated_ts']);
}));
usort($recentItems, static function (array $left, array $right): int {
    return ($right['generated_ts'] ?? 0) <=> ($left['generated_ts'] ?? 0);
});
$recentItems = array_slice($recentItems, 0, 4);
$favoriteItems = array_slice(array_values(array_filter($documents, static function (array $doc): bool {
    return $doc['available'];
})), 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloads Hub</title>
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <link href="/street_vendor/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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

        * { box-sizing: border-box; }

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
            background: repeating-linear-gradient(125deg, rgba(184,178,171,0.06) 0, rgba(184,178,171,0.06) 1px, transparent 1px, transparent 25px);
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
            border: 1px solid rgba(184,178,171,0.2);
            transition: width 0.3s ease;
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
            padding: 24px 28px 34px;
            position: relative;
            z-index: 2;
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
            margin-bottom: 16px;
            backdrop-filter: blur(12px);
            animation: slideInTop 0.7s ease;
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

        .summary-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
            position: relative;
            z-index: 2;
        }

        .sum-card {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border: 1px solid rgba(184,178,171,0.2);
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 9px 22px rgba(0,0,0,0.08);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            animation: riseFade 0.6s ease both;
            position: relative;
            overflow: hidden;
        }

        .sum-card::after {
            content: '';
            position: absolute;
            right: -28px;
            bottom: -38px;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(184,178,171,0.24), transparent 72%);
        }

        .sum-card:hover {
            transform: translateY(-3px);
            border-color: rgba(184,178,171,0.35);
            box-shadow: 0 14px 26px rgba(0,0,0,0.12);
        }

        .sum-card:nth-child(2) { animation-delay: 0.06s; }
        .sum-card:nth-child(3) { animation-delay: 0.12s; }
        .sum-card:nth-child(4) { animation-delay: 0.18s; }

        .sum-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
        }

        .sum-card strong {
            display: block;
            margin-top: 6px;
            font-size: 1.48rem;
            line-height: 1;
            color: var(--text-primary);
            font-weight: 800;
        }

        .hub-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.72fr);
            gap: 16px;
            align-items: start;
            position: relative;
            z-index: 2;
        }

        .library-panel {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border: 1.5px solid rgba(184,178,171,0.2);
            border-radius: 28px 14px 30px 24px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.5);
            padding: 16px;
            overflow: hidden;
            animation: slideInLeft 0.8s ease;
            position: relative;
            backdrop-filter: blur(15px);
        }

        .library-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding: 0 3px;
        }

        .library-head h3 {
            margin: 0;
            font-size: 1.02rem;
            color: var(--text-primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .library-head small {
            color: var(--text-secondary);
            font-weight: 700;
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 12px;
        }

        .doc-card {
            background: rgba(255,255,255,0.35);
            border: 1px solid rgba(184,178,171,0.15);
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            transition: transform 0.28s ease, border-color 0.28s ease, box-shadow 0.28s ease;
            animation: fadeUpCard 0.55s ease both;
            position: relative;
            overflow: hidden;
        }

        .doc-card:nth-child(2) { animation-delay: 0.06s; }
        .doc-card:nth-child(3) { animation-delay: 0.12s; }
        .doc-card:nth-child(4) { animation-delay: 0.18s; }
        .doc-card:nth-child(5) { animation-delay: 0.24s; }
        .doc-card:nth-child(6) { animation-delay: 0.3s; }

        .doc-card:hover {
            transform: translateY(-4px);
            border-color: rgba(184,178,171,0.35);
            box-shadow: 0 15px 26px rgba(0,0,0,0.12);
        }

        .doc-head {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 10px;
        }

        .doc-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(184,178,171,0.3);
            background: rgba(232,229,224,0.9);
            color: #7a726a;
            animation: iconPulse 2.2s infinite ease-in-out;
            transition: transform 0.28s ease;
            flex: 0 0 auto;
        }

        .doc-card:hover .doc-icon { transform: translateY(-2px) scale(1.06); }

        .doc-title {
            margin: 0;
            color: var(--text-primary);
            font-size: 0.93rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .doc-meta {
            margin-top: 8px;
            display: grid;
            gap: 4px;
        }

        .doc-meta span {
            color: var(--text-secondary);
            font-size: 0.76rem;
            font-weight: 600;
        }

        .badge-state {
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.66rem;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.05em;
            border: 1px solid #cbc2b8;
            background: #f2eeea;
            color: #3c3732;
            white-space: nowrap;
        }

        .doc-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .btn-doc {
            border: 1px solid rgba(200, 125, 58, 0.62);
            border-radius: 999px;
            padding: 6px 11px;
            font-size: 0.71rem;
            font-weight: 800;
            color: #fff1e0;
            background: linear-gradient(145deg, #c48243 0%, #b06f33 60%, #905725 100%);
            box-shadow: 0 8px 16px rgba(9, 12, 17, 0.45), inset 0 1px 0 rgba(255,255,255,0.2);
            text-decoration: none;
            transition: transform 0.24s ease, box-shadow 0.24s ease, filter 0.24s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-doc::before {
            content: '';
            position: absolute;
            left: -55%;
            top: 0;
            width: 34%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.32), rgba(255,255,255,0));
            transform: skewX(-20deg);
            transition: left 0.45s ease;
        }

        .btn-doc:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(8, 11, 15, 0.58), 0 0 10px rgba(200, 125, 58, 0.23);
            color: #fff5ea;
            filter: brightness(1.05);
        }

        .btn-doc:hover::before { left: 120%; }

        .btn-doc.disabled {
            opacity: 0.5;
            pointer-events: none;
            filter: grayscale(0.2);
        }

        .btn-ripple {
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            background: rgba(255,255,255,0.35);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }

        .right-widgets {
            display: grid;
            gap: 10px;
            position: sticky;
            top: 18px;
            z-index: 2;
        }

        .widget {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border: 1px solid rgba(184,178,171,0.2);
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.08);
            backdrop-filter: blur(8px);
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
            animation: floatWidget 6.3s ease-in-out infinite;
        }

        .widget:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(184,178,171,0.35);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        }

        .widget:nth-child(2) { animation-delay: 0.5s; }
        .widget:nth-child(3) { animation-delay: 1s; }
        .widget:nth-child(4) { animation-delay: 1.5s; }

        .widget h4 {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .widget h4 i {
            color: #9f958b;
            animation: iconPulse 2.1s infinite ease-in-out;
        }

        .widget ul {
            list-style: none;
            padding: 0;
            margin: 10px 0 0;
            display: grid;
            gap: 7px;
        }

        .widget li,
        .widget p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.5;
        }

        .widget li {
            padding: 7px 8px;
            border-radius: 10px;
            border: 1px solid rgba(184,178,171,0.2);
            background: rgba(255,255,255,0.5);
        }

        .shape-rect,
        .shape-paper,
        .shape-fold,
        .shape-line,
        .shape-file,
        .shape-arrow {
            position: absolute;
            pointer-events: none;
            z-index: 0;
        }

        .shape-rect {
            width: 220px;
            height: 70px;
            border-radius: 16px;
            transform: rotate(-12deg);
            background: linear-gradient(90deg, rgba(184,178,171,0.14), rgba(184,178,171,0.02));
            border: 1px solid rgba(184,178,171,0.2);
            animation: driftRect 10s ease-in-out infinite;
        }

        .rect-a { top: 180px; right: 30%; }
        .rect-b { bottom: 130px; left: 16%; animation-delay: 2s; }

        .shape-paper {
            width: 130px;
            height: 160px;
            border-radius: 14px;
            border: 1px solid rgba(184,178,171,0.24);
            background: linear-gradient(160deg, rgba(184,178,171,0.08), rgba(255,255,255,0.2));
            animation: floatShape 8s ease-in-out infinite;
        }

        .paper-a { top: 94px; right: 7%; }
        .paper-b { bottom: 72px; left: 9%; width: 94px; height: 118px; animation-delay: 1.5s; }

        .shape-fold {
            width: 0;
            height: 0;
            border-left: 28px solid transparent;
            border-top: 28px solid rgba(184,178,171,0.45);
            filter: drop-shadow(0 0 8px rgba(184,178,171,0.22));
            animation: glowPulse 6s ease-in-out infinite;
        }

        .fold-a { top: 112px; right: 10%; }

        .shape-line {
            width: 180px;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(184,178,171,0.48), transparent);
            animation: lineDrift 8s ease-in-out infinite;
        }

        .line-a { top: 255px; right: 12%; }
        .line-b { bottom: 160px; left: 37%; animation-delay: 2.2s; }

        .shape-file {
            width: 34px;
            height: 42px;
            border-radius: 8px;
            border: 1px solid rgba(184,178,171,0.35);
            background: rgba(184,178,171,0.18);
            animation: miniFloat 6s ease-in-out infinite;
        }

        .file-a { top: 286px; left: 50%; }
        .file-b { top: 62%; right: 17%; animation-delay: 1.2s; }

        .shape-arrow {
            color: rgba(184,178,171,0.65);
            font-size: 1.15rem;
            animation: arrowBounce 2.5s ease-in-out infinite;
        }

        .empty-download-msg {
            margin: 0 0 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f7f3ee;
            border: 1px dashed #bfb3a8;
            color: #655e57;
            font-weight: 600;
            text-align: center;
        }

        .arr-a { top: 338px; right: 19%; }
        .arr-b { bottom: 114px; left: 24%; animation-delay: 1s; }

        @keyframes pageFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideInTop { from { opacity: 0; transform: translateY(-35px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideInLeft { from { opacity: 0; transform: translateX(-24px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes riseFade { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeUpCard { from { opacity: 0; transform: translateY(18px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes iconPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        @keyframes floatWidget { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
        @keyframes driftRect { 0%,100% { transform: rotate(-12deg) translateX(0); } 50% { transform: rotate(-10deg) translateX(10px); } }
        @keyframes floatShape { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-16px); } }
        @keyframes glowPulse { 0%,100% { opacity: 0.45; } 50% { opacity: 0.9; } }
        @keyframes lineDrift { 0%,100% { transform: translateX(0); opacity: 0.3; } 50% { transform: translateX(15px); opacity: 0.85; } }
        @keyframes miniFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes arrowBounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        @keyframes ripple { to { transform: scale(4); opacity: 0; } }

        @media (max-width: 1120px) {
            .summary-strip { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
            .hub-layout { grid-template-columns: 1fr; }
            .right-widgets { position: static; }
        }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .main-content { margin-left: 60px; padding: 16px; }
            .top-navbar {
                padding: 14px;
                border-radius: 18px;
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .summary-strip { grid-template-columns: 1fr; }
            .doc-grid { grid-template-columns: 1fr; }
            .doc-actions a { flex: 1 1 calc(50% - 8px); text-align: center; }
            .shape-rect,.shape-paper,.shape-fold,.shape-line,.shape-file,.shape-arrow { opacity: 0.45; }
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
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/location.php'">
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
            <div class="sidebar-item active" onclick="window.location.href='downloads.php'">
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
                    <h1>Document Hub</h1>
                    <p>Premium download center for all vendor documents</p>
                </div>
                <span class="nav-pill"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($vendorPhone); ?></span>
            </div>

            <div class="shape-rect rect-a"></div>
            <div class="shape-rect rect-b"></div>
            <div class="shape-paper paper-a"></div>
            <div class="shape-paper paper-b"></div>
            <div class="shape-fold fold-a"></div>
            <div class="shape-line line-a"></div>
            <div class="shape-line line-b"></div>
            <div class="shape-file file-a"></div>
            <div class="shape-file file-b"></div>
            <i class="fas fa-arrow-down shape-arrow arr-a"></i>
            <i class="fas fa-arrow-down shape-arrow arr-b"></i>

            <section class="summary-strip">
                <article class="sum-card">
                    <p>Total Documents</p>
                    <strong><?php echo (int) $totalDocuments; ?></strong>
                </article>
                <article class="sum-card">
                    <p>Pending Files</p>
                    <strong><?php echo (int) $pendingFiles; ?></strong>
                </article>
                <article class="sum-card">
                    <p>Recent Records</p>
                    <strong><?php echo (int) $recentlyGenerated; ?></strong>
                </article>
                <article class="sum-card">
                    <p>Available Documents</p>
                    <strong><?php echo (int) $availableDocuments; ?></strong>
                </article>
            </section>

            <section class="hub-layout">
                <section class="library-panel">
                    <div class="library-head">
                        <h3><i class="fas fa-folder-open"></i> Document Library</h3>
                        <small><?php echo htmlspecialchars($name); ?> | <?php echo date('d M Y'); ?></small>
                    </div>

                    <?php if ($availableDocuments === 0): ?>
                        <div class="empty-download-msg">No files available for download</div>
                    <?php endif; ?>

                    <div class="doc-grid">
                        <?php foreach ($documents as $doc): ?>
                            <article class="doc-card">
                                <div class="doc-head">
                                    <div style="display:flex; gap:10px; align-items:start;">
                                        <span class="doc-icon"><i class="fas <?php echo htmlspecialchars($doc['icon']); ?>"></i></span>
                                        <div>
                                            <h4 class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                            <div class="doc-meta">
                                                <span>Type: <?php echo htmlspecialchars($doc['type']); ?></span>
                                                <span>Source: <?php echo htmlspecialchars($doc['source']); ?></span>
                                                <span>Generated: <?php echo htmlspecialchars($doc['generated']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge-state"><?php echo htmlspecialchars($doc['badge']); ?></span>
                                </div>

                                <div class="doc-actions">
                                    <a class="btn-doc <?php echo !empty($doc['downloadable']) ? '' : 'disabled'; ?>" href="<?php echo !empty($doc['downloadable']) ? htmlspecialchars($doc['download']) : '#'; ?>">Download</a>
                                    <a class="btn-doc" href="<?php echo htmlspecialchars($doc['preview']); ?>">Preview</a>
                                    <a class="btn-doc" href="<?php echo htmlspecialchars($doc['preview']); ?>?share=<?php echo urlencode($doc['key']); ?>">Share</a>
                                    <a class="btn-doc <?php echo !empty($doc['downloadable']) ? '' : 'disabled'; ?>" href="<?php echo !empty($doc['downloadable']) ? htmlspecialchars($doc['download']) : '#'; ?>">Export PDF</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <aside class="right-widgets">
                    <article class="widget">
                        <h4><i class="fas fa-clock-rotate-left"></i> Recent Records</h4>
                        <ul>
                            <?php if (count($recentItems) === 0): ?>
                                <li>No generated records available yet.</li>
                            <?php else: ?>
                                <?php foreach ($recentItems as $item): ?>
                                    <li><?php echo htmlspecialchars($item['title']); ?> <br><small><?php echo htmlspecialchars($item['generated']); ?></small></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>

                    <article class="widget">
                        <h4><i class="fas fa-bolt"></i> Quick Access</h4>
                        <ul>
                            <li><a href="/street_vendor/vendor/my_licenses.php" style="color:inherit;text-decoration:none;">License Dashboard</a></li>
                            <li><a href="/street_vendor/vendor/location.php" style="color:inherit;text-decoration:none;">Location Module</a></li>
                            <li><a href="/street_vendor/vendor/my_licenses.php" style="color:inherit;text-decoration:none;">My Licenses</a></li>
                        </ul>
                    </article>

                    <article class="widget">
                        <h4><i class="fas fa-star"></i> Favorites</h4>
                        <ul>
                            <?php if (count($favoriteItems) === 0): ?>
                                <li>No favorites yet.</li>
                            <?php else: ?>
                                <?php foreach ($favoriteItems as $item): ?>
                                    <li><?php echo htmlspecialchars($item['title']); ?> <br><small><?php echo htmlspecialchars($item['generated']); ?></small></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>

                    <article class="widget">
                        <h4><i class="fas fa-circle-info"></i> Status Updates</h4>
                        <ul>
                            <?php if ($approvedLicense): ?>
                                <li>License approved and ready for secure download.</li>
                            <?php else: ?>
                                <li>License is still pending approval.</li>
                            <?php endif; ?>
                            <?php if ($currentLocation): ?>
                                <li>Location assigned: <?php echo htmlspecialchars((string) $currentLocation['zone_name'] . ' - Spot ' . (string) $currentLocation['spot_number']); ?></li>
                            <?php else: ?>
                                <li>Location has not been assigned yet.</li>
                            <?php endif; ?>
                            <?php if ($latestLicense): ?>
                                <li>Latest application saved on <?php echo htmlspecialchars($latestLicense['created_at'] ? date('d M Y', strtotime((string) $latestLicense['created_at'])) : 'unknown date'); ?>.</li>
                            <?php endif; ?>
                        </ul>
                    </article>

                    <article class="widget">
                        <h4><i class="fas fa-bell"></i> Notifications</h4>
                        <p>Approved documents are available for secure download. Pending documents will appear automatically once generated.</p>
                    </article>
                </aside>
            </section>
        </main>
    </div>

    <script>
        (function () {
            const buttons = document.querySelectorAll('.btn-doc:not(.disabled)');
            function addRipple(event, button) {
                const circle = document.createElement('span');
                circle.className = 'btn-ripple';
                const diameter = Math.max(button.clientWidth, button.clientHeight);
                const radius = diameter / 2;
                const rect = button.getBoundingClientRect();
                circle.style.width = circle.style.height = diameter + 'px';
                circle.style.left = event.clientX - rect.left - radius + 'px';
                circle.style.top = event.clientY - rect.top - radius + 'px';
                const old = button.querySelector('.btn-ripple');
                if (old) {
                    old.remove();
                }
                button.appendChild(circle);
            }

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    addRipple(event, btn);
                });
            });
        })();
    </script>
</body>
</html>
