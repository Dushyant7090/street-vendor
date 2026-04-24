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

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

$licenses = [];
$recentActivity = [];
$latestExpiryRaw = null;

if ($vendorId > 0) {
    $statsStmt = $conn->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
         FROM licenses
         WHERE vendor_id = ?"
    );

    if ($statsStmt) {
        $statsStmt->bind_param('i', $vendorId);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $statsRow = $statsResult ? $statsResult->fetch_assoc() : null;
        if ($statsRow) {
            $stats['total'] = (int) ($statsRow['total'] ?? 0);
            $stats['pending'] = (int) ($statsRow['pending'] ?? 0);
            $stats['approved'] = (int) ($statsRow['approved'] ?? 0);
            $stats['rejected'] = (int) ($statsRow['rejected'] ?? 0);
        }
        $statsStmt->close();
    }

    $tableStmt = $conn->prepare(
        "SELECT
            l.id,
            l.license_number,
            l.status,
            l.expiry_date,
            l.applied_date,
            l.created_at,
            l.remarks,
            u.name AS vendor_name
         FROM licenses l
         INNER JOIN vendors v ON v.id = l.vendor_id
         INNER JOIN users u ON u.id = v.user_id
         WHERE l.vendor_id = ?
         ORDER BY l.created_at DESC
         LIMIT 12"
    );

    if ($tableStmt) {
        $tableStmt->bind_param('i', $vendorId);
        $tableStmt->execute();
        $tableResult = $tableStmt->get_result();
        if ($tableResult) {
            while ($row = $tableResult->fetch_assoc()) {
                $licenses[] = $row;
            }
        }
        $tableStmt->close();
    }

    $activityStmt = $conn->prepare(
        "SELECT id, license_number, status, created_at
         FROM licenses
         WHERE vendor_id = ?
         ORDER BY created_at DESC
         LIMIT 5"
    );

    if ($activityStmt) {
        $activityStmt->bind_param('i', $vendorId);
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        if ($activityResult) {
            while ($row = $activityResult->fetch_assoc()) {
                $recentActivity[] = $row;
            }
        }
        $activityStmt->close();
    }

    $expiryStmt = $conn->prepare(
        "SELECT expiry_date
         FROM licenses
         WHERE vendor_id = ?
           AND expiry_date IS NOT NULL
         ORDER BY expiry_date ASC
         LIMIT 1"
    );

    if ($expiryStmt) {
        $expiryStmt->bind_param('i', $vendorId);
        $expiryStmt->execute();
        $expiryResult = $expiryStmt->get_result();
        $expiryRow = $expiryResult ? $expiryResult->fetch_assoc() : null;
        $latestExpiryRaw = $expiryRow['expiry_date'] ?? null;
        $expiryStmt->close();
    }
}

$approvedRatio = $stats['total'] > 0 ? (int) round(($stats['approved'] / $stats['total']) * 100) : 0;
$trackerRatio = $stats['total'] > 0 ? (int) round((($stats['approved'] + $stats['pending']) / $stats['total']) * 100) : 0;

$renewalText = 'No immediate renewals required';
$renewalClass = 'ok';
if ($latestExpiryRaw) {
    $today = new DateTime('today');
    $expiryDateObj = new DateTime($latestExpiryRaw);
    $daysRemaining = (int) $today->diff($expiryDateObj)->format('%r%a');

    if ($daysRemaining < 0) {
        $renewalText = 'License expired. Renew immediately.';
        $renewalClass = 'urgent';
    } elseif ($daysRemaining <= 30) {
        $renewalText = 'License expires in ' . $daysRemaining . ' day(s).';
        $renewalClass = 'warning';
    } else {
        $renewalText = 'Nearest expiry in ' . $daysRemaining . ' day(s).';
    }
}

$currentLicense = $licenses[0] ?? null;
$licenseIdLabel = $currentLicense ? ('#' . (int) ($currentLicense['id'] ?? 0)) : 'Not Issued';
$currentStatus = $currentLicense ? ucfirst((string) ($currentLicense['status'] ?? 'pending')) : 'Pending';
$renewalDate = $latestExpiryRaw ? date('M d, Y', strtotime((string) $latestExpiryRaw)) : 'Not Available';
$applicationDate = !empty($currentLicense['applied_date']) ? date('M d, Y', strtotime((string) $currentLicense['applied_date'])) : 'Not Available';
$expiryDateLabel = !empty($currentLicense['expiry_date']) ? date('M d, Y', strtotime((string) $currentLicense['expiry_date'])) : 'Not Set';
$licenseType = 'Vendor License';
$verificationDetails = 'Verification in progress';
if (strtolower((string) ($currentLicense['status'] ?? 'pending')) === 'approved') {
    $verificationDetails = 'Verified and approved by licensing authority';
} elseif (strtolower((string) ($currentLicense['status'] ?? 'pending')) === 'rejected') {
    $verificationDetails = 'Application reviewed and rejected';
} elseif (strtolower((string) ($currentLicense['status'] ?? 'pending')) === 'expired') {
    $verificationDetails = 'License has expired and requires renewal';
}

function statusClass(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'approved') {
        return 'st-approved';
    }
    if ($status === 'rejected') {
        return 'st-rejected';
    }
    if ($status === 'expired') {
        return 'st-expired';
    }
    return 'st-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Module</title>
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
            background: repeating-linear-gradient(126deg, rgba(255,255,255,0.03) 0, rgba(255,255,255,0.03) 1px, transparent 1px, transparent 30px);
            pointer-events: none;
            z-index: 0;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            z-index: 1;
            animation: pageFadeIn 0.75s ease;
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
            animation: iconPulse 2.2s infinite ease-in-out;
        }

        .sidebar-item span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .sidebar-item span { opacity: 1; }

        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 24px 28px 34px;
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
            position: relative;
            z-index: 2;
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

        .shape-orb,
        .shape-bar,
        .shape-ribbon,
        .shape-poly,
        .shape-capsule,
        .shape-blob,
        .shape-square {
            position: absolute;
            pointer-events: none;
            z-index: 0;
        }

        .shape-orb {
            width: 220px;
            height: 220px;
            border-radius: 50%;
            top: 94px;
            right: 4%;
            background: radial-gradient(circle at 40% 35%, rgba(200,125,58,0.34), rgba(56,65,78,0.09) 70%);
            border: 1px solid rgba(200,125,58,0.26);
            animation: floatShape 9s ease-in-out infinite;
        }

        .shape-bar {
            width: 230px;
            height: 18px;
            border-radius: 12px;
            background: linear-gradient(90deg, rgba(200,125,58,0.45), rgba(200,125,58,0.05));
            transform: rotate(-14deg);
            border: 1px solid rgba(200,125,58,0.22);
            animation: driftBar 10s ease-in-out infinite;
        }

        .bar-a { top: 236px; right: 23%; }
        .bar-b { bottom: 186px; left: 16%; animation-delay: 2.2s; }

        .shape-ribbon {
            width: 280px;
            height: 70px;
            left: 2%;
            bottom: 42px;
            border-radius: 120px;
            background: linear-gradient(110deg, rgba(200,125,58,0.16), rgba(56,65,78,0.03));
            border: 1px solid rgba(200,125,58,0.18);
            filter: blur(1px);
            animation: ribbonFlow 11s ease-in-out infinite;
        }

        .shape-poly {
            width: 90px;
            height: 90px;
            top: 330px;
            right: 32%;
            clip-path: polygon(16% 3%, 82% 10%, 100% 64%, 58% 100%, 8% 78%, 0 30%);
            background: rgba(200,125,58,0.18);
            border: 1px solid rgba(200,125,58,0.25);
            animation: polyTilt 8s ease-in-out infinite;
        }

        .shape-capsule {
            width: 150px;
            height: 46px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(200,125,58,0.2), rgba(200,125,58,0.03));
            border: 1px solid rgba(200,125,58,0.24);
            animation: capsuleDrift 9s ease-in-out infinite;
        }

        .capsule-a { top: 164px; left: 36%; }
        .capsule-b { bottom: 120px; right: 18%; animation-delay: 1.7s; }

        .shape-blob {
            width: 200px;
            height: 180px;
            border-radius: 58% 42% 61% 39% / 44% 53% 47% 56%;
            background: radial-gradient(circle at 32% 24%, rgba(200,125,58,0.28), rgba(56,65,78,0.08) 75%);
            filter: blur(6px);
            bottom: 14px;
            left: -18px;
            animation: blobFloat 13s ease-in-out infinite;
        }

        .shape-square {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 1px solid rgba(200,125,58,0.34);
            background: rgba(200,125,58,0.16);
            animation: squareDrift 6.6s ease-in-out infinite;
        }

        .sq-a { top: 386px; left: 50%; }
        .sq-b { top: 420px; left: 55%; animation-delay: 0.9s; }
        .sq-c { top: 348px; left: 58%; animation-delay: 1.5s; }

        .overview-wide {
            background: linear-gradient(150deg, rgba(56, 65, 78, 0.95), rgba(72, 84, 98, 0.95));
            border-radius: 42px 16px 46px 28px;
            border: 2px solid rgba(200,125,58,0.46);
            box-shadow: 0 18px 42px rgba(8, 12, 17, 0.62), inset 0 1px 0 rgba(255,255,255,0.08);
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.86s ease;
            z-index: 2;
        }

        .overview-wide::after {
            content: '';
            position: absolute;
            right: -90px;
            top: -76px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(200,125,58,0.32), transparent 70%);
        }

        .overview-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            position: relative;
            z-index: 2;
        }

        .overview-head h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .overview-head h2 i {
            color: #d79b62;
            animation: iconPulse 2.2s infinite ease-in-out;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 10px;
            position: relative;
            z-index: 2;
            align-items: stretch;
        }

        .overview-item {
            border-radius: 14px;
            padding: 10px;
            border: 1px solid rgba(200,125,58,0.3);
            background: rgba(24, 30, 37, 0.46);
            transition: transform 0.24s ease, box-shadow 0.24s ease, border-color 0.24s ease;
        }

        .overview-item:hover {
            transform: translateY(-2px);
            border-color: rgba(200,125,58,0.58);
            box-shadow: 0 10px 20px rgba(9,12,17,0.48), 0 0 10px rgba(200,125,58,0.2);
        }

        .overview-item label {
            display: block;
            color: #e4d7c8;
            font-size: 0.69rem;
            letter-spacing: 0.07em;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .overview-item p {
            margin: 0;
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 700;
        }

        .content-split {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.68fr);
            gap: 14px;
            align-items: start;
            position: relative;
            z-index: 2;
        }

        .license-detail-panel {
            background: linear-gradient(152deg, rgba(56, 65, 78, 0.95), rgba(70, 82, 96, 0.95));
            border-radius: 30px 14px 36px 24px;
            border: 1.8px solid rgba(200,125,58,0.45);
            box-shadow: 0 16px 40px rgba(9, 12, 17, 0.58), inset 0 1px 0 rgba(255,255,255,0.08);
            padding: 18px;
            animation: slideInUp 0.95s ease;
            position: relative;
            overflow: hidden;
        }

        .detail-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .detail-head h3 {
            margin: 0;
            font-size: 1.02rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-chip {
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(200,125,58,0.5);
            background: rgba(200,125,58,0.16);
            color: #f7e8d7;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 10px;
        }

        .detail-box {
            grid-column: span 6;
        }

        .detail-box.wide {
            grid-column: span 12;
        }

        .detail-box {
            border-radius: 14px;
            padding: 11px;
            border: 1px solid rgba(200,125,58,0.3);
            background: rgba(24, 30, 37, 0.48);
            transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease;
        }

        .detail-box:hover {
            transform: translateY(-2px);
            border-color: rgba(200,125,58,0.6);
            box-shadow: 0 10px 20px rgba(8,11,15,0.5), 0 0 10px rgba(200,125,58,0.18);
        }

        .detail-box label {
            display: block;
            color: #e4d7c8;
            font-size: 0.69rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .detail-box p {
            margin: 0;
            color: var(--text-primary);
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .verify-strip {
            margin-top: 10px;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(200,125,58,0.4);
            background: rgba(25,31,38,0.56);
            color: var(--text-secondary);
            font-size: 0.81rem;
            font-weight: 600;
        }

        .verify-strip strong {
            color: #f2e5d7;
        }

        .right-stack {
            display: grid;
            gap: 10px;
            position: relative;
            z-index: 2;
            align-items: start;
        }

        .stack-card {
            background: linear-gradient(150deg, rgba(56, 65, 78, 0.9), rgba(69, 80, 94, 0.9));
            border: 1px solid rgba(200, 125, 58, 0.4);
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 10px 24px rgba(10, 13, 18, 0.5);
            backdrop-filter: blur(8px);
            transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
            animation: staggerReveal 0.7s ease both;
        }

        .stack-card:hover {
            transform: translateY(-4px);
            border-color: rgba(200, 125, 58, 0.66);
            box-shadow: 0 15px 30px rgba(8, 12, 17, 0.62), 0 0 14px rgba(200, 125, 58, 0.2);
        }

        .stack-card:nth-child(1) { min-height: 132px; animation-delay: 0.08s; }
        .stack-card:nth-child(2) { min-height: 162px; margin-left: 14px; animation-delay: 0.16s; }
        .stack-card:nth-child(3) { min-height: 120px; animation-delay: 0.24s; }
        .stack-card:nth-child(4) { min-height: 154px; margin-left: 8px; animation-delay: 0.32s; }

        .stack-card h4 {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .stack-card h4 i {
            color: #d79b62;
            animation: iconPulse 2.1s infinite ease-in-out;
        }

        .stack-card ul {
            list-style: none;
            margin: 9px 0 0;
            padding: 0;
            display: grid;
            gap: 7px;
        }

        .stack-card li,
        .stack-card p,
        .stack-card small {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.5;
        }

        .stack-card li {
            padding: 7px 8px;
            border-radius: 10px;
            border: 1px solid rgba(200,125,58,0.24);
            background: rgba(25,31,38,0.42);
        }

        .renewal-pill {
            margin-top: 8px;
            padding: 8px 9px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .renewal-pill.ok {
            border: 1px solid rgba(128,182,109,0.45);
            color: #dcf2d0;
            background: rgba(83,128,73,0.26);
        }

        .renewal-pill.warning {
            border: 1px solid rgba(200,147,79,0.55);
            color: #f8e9d4;
            background: rgba(140,92,40,0.35);
        }

        .renewal-pill.urgent {
            border: 1px solid rgba(188,106,106,0.55);
            color: #f5dada;
            background: rgba(133,64,64,0.34);
        }

        .btn-action {
            border: 1px solid rgba(200, 125, 58, 0.56);
            border-radius: 999px;
            padding: 7px 11px;
            color: #fff0df;
            background: linear-gradient(145deg, #c48243 0%, #b06f33 60%, #905725 100%);
            font-size: 0.71rem;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.28s ease;
            box-shadow: 0 8px 16px rgba(9, 12, 17, 0.45);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            color: #fff5ea;
            box-shadow: 0 12px 20px rgba(8, 11, 15, 0.56), 0 0 12px rgba(200, 125, 58, 0.22);
        }

        .quick-actions {
            margin-top: 9px;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        @keyframes pageFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInTop {
            from { opacity: 0; transform: translateY(-35px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(24px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes staggerReveal {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-18px) scale(1.05); }
        }

        @keyframes driftBar {
            0%, 100% { transform: rotate(-14deg) translateX(0); }
            50% { transform: rotate(-12deg) translateX(10px); }
        }

        @keyframes ribbonFlow {
            0%, 100% { transform: translateX(0) scaleX(1); }
            50% { transform: translateX(12px) scaleX(1.04); }
        }

        @keyframes polyTilt {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(8deg); }
        }

        @keyframes capsuleDrift {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes blobFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        @keyframes squareDrift {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(12deg); }
        }

        @media (max-width: 1120px) {
            .overview-grid {
                grid-template-columns: repeat(2, minmax(150px, 1fr));
            }

            .content-split {
                grid-template-columns: 1fr;
            }

            .stack-card:nth-child(2),
            .stack-card:nth-child(4) {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }

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

            .overview-grid,
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-box,
            .detail-box.wide {
                grid-column: span 1;
            }

            .shape-orb,
            .shape-bar,
            .shape-ribbon,
            .shape-poly,
            .shape-capsule,
            .shape-blob,
            .shape-square {
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
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/my_location.php'">
                <i class="fas fa-map-marker-alt"></i>
                <span>Locations</span>
            </div>
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/license.php'">
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
                    <h1>License Management Dashboard</h1>
                    <p>Premium asymmetrical workspace for active license control</p>
                </div>
                <div class="nav-meta">
                    <span class="nav-pill"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($vendorPhone); ?></span>
                    <span class="nav-pill"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($name); ?></span>
                </div>
            </div>

            <div class="shape-orb"></div>
            <div class="shape-bar bar-a"></div>
            <div class="shape-bar bar-b"></div>
            <div class="shape-ribbon"></div>
            <div class="shape-poly"></div>
            <div class="shape-capsule capsule-a"></div>
            <div class="shape-capsule capsule-b"></div>
            <div class="shape-blob"></div>
            <div class="shape-square sq-a"></div>
            <div class="shape-square sq-b"></div>
            <div class="shape-square sq-c"></div>

            <section class="overview-wide">
                <div class="overview-head">
                    <h2><i class="fas fa-shield-halved"></i> License Overview</h2>
                    <span class="nav-pill"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($licenseIdLabel); ?></span>
                </div>
                <div class="overview-grid">
                    <div class="overview-item">
                        <label>License Overview</label>
                        <p><?php echo (int) $stats['total']; ?> total, <?php echo (int) $stats['approved']; ?> approved</p>
                    </div>
                    <div class="overview-item">
                        <label>License ID</label>
                        <p><?php echo htmlspecialchars($licenseIdLabel); ?></p>
                    </div>
                    <div class="overview-item">
                        <label>Current Status</label>
                        <p><?php echo htmlspecialchars($currentStatus); ?></p>
                    </div>
                    <div class="overview-item">
                        <label>Renewal Date</label>
                        <p><?php echo htmlspecialchars($renewalDate); ?></p>
                    </div>
                </div>
            </section>

            <section class="content-split">
                <section class="license-detail-panel">
                    <div class="detail-head">
                        <h3><i class="fas fa-file-signature"></i> License Details Panel</h3>
                        <span class="status-chip"><?php echo htmlspecialchars($currentStatus); ?></span>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-box">
                            <label>Vendor Name</label>
                            <p><?php echo htmlspecialchars($name); ?></p>
                        </div>
                        <div class="detail-box">
                            <label>License Type</label>
                            <p><?php echo htmlspecialchars($licenseType); ?></p>
                        </div>
                        <div class="detail-box">
                            <label>Application Date</label>
                            <p><?php echo htmlspecialchars($applicationDate); ?></p>
                        </div>
                        <div class="detail-box">
                            <label>Expiry Date</label>
                            <p><?php echo htmlspecialchars($expiryDateLabel); ?></p>
                        </div>
                        <div class="detail-box wide">
                            <label>Verification Status</label>
                            <p><?php echo htmlspecialchars($verificationDetails); ?></p>
                        </div>
                    </div>
                </section>

                <aside class="right-stack">
                    <article class="stack-card">
                        <h4><i class="fas fa-clock-rotate-left"></i> Recent Updates</h4>
                        <ul>
                            <?php if (count($recentActivity) === 0): ?>
                                <li>No updates recorded yet.</li>
                            <?php else: ?>
                                <?php foreach (array_slice($recentActivity, 0, 3) as $activity): ?>
                                    <li>
                                        <strong>#<?php echo (int) $activity['id']; ?></strong>
                                        <?php echo htmlspecialchars((string) ucfirst((string) ($activity['status'] ?? 'pending'))); ?>
                                        <br>
                                        <small><?php echo htmlspecialchars(date('M d, Y', strtotime((string) ($activity['created_at'] ?? 'now')))); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </article>

                    <article class="stack-card">
                        <h4><i class="fas fa-bell"></i> Notifications</h4>
                        <ul>
                            <li>Check profile details before renewal request.</li>
                            <li>Maintain valid documents in Downloads hub.</li>
                            <li>Review remarks for delayed applications.</li>
                        </ul>
                    </article>

                    <article class="stack-card">
                        <h4><i class="fas fa-calendar-check"></i> Renewal Reminder</h4>
                        <p>Based on nearest expiry:</p>
                        <div class="renewal-pill <?php echo htmlspecialchars($renewalClass); ?>">
                            <?php echo htmlspecialchars($renewalText); ?>
                        </div>
                    </article>

                    <article class="stack-card">
                        <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                        <div class="quick-actions">
                            <a class="btn-action" href="/street_vendor/vendor/my_licenses.php"><i class="fas fa-eye"></i> View All</a>
                            <a class="btn-action" href="downloads.php"><i class="fas fa-download"></i> Downloads</a>
                            <a class="btn-action" href="/street_vendor/vendor/apply_license.php"><i class="fas fa-plus"></i> Apply New</a>
                            <a class="btn-action" href="/street_vendor/vendor/apply_license.php?renew=1"><i class="fas fa-rotate-right"></i> Renew</a>
                        </div>
                    </article>
                </aside>
            </section>
        </main>
    </div>
</body>
</html>
