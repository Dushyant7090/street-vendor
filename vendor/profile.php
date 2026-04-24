<?php
session_start();
include __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header('Location: /street_vendor/login.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

$message = '';
$messageType = '';

function vendorColumnExists(mysqli $conn, string $columnName): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vendors'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $columnName);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

function tableColumnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

$hasVendorPhotoColumn = vendorColumnExists($conn, 'photo');
$hasVendorUpdatedAtColumn = vendorColumnExists($conn, 'updated_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_details') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $idProofType = trim((string) ($_POST['id_proof_type'] ?? 'Aadhar Card'));
        $idProofNumber = trim((string) ($_POST['id_proof_number'] ?? ''));

        if ($name === '' || $phone === '' || $address === '' || $idProofNumber === '') {
            $message = 'Please fill in all required fields before updating profile.';
            $messageType = 'error';
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $message = 'Phone number must contain 10 to 15 digits.';
            $messageType = 'error';
        } else {
            $_SESSION['name'] = $name;
            $message = 'Demo Mode: Data not saved';
            $messageType = 'success';
        }
    }

    if ($action === 'upload_photo') {
        if (!$hasVendorPhotoColumn) {
            $message = 'Photo upload is unavailable until the vendors.photo column is added.';
            $messageType = 'error';
        } elseif (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            $message = 'No photo upload request received.';
            $messageType = 'error';
        } else {
            $file = $_FILES['photo'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $message = 'Please choose an image file to upload.';
                $messageType = 'error';
            } else {
                $name = (string) ($file['name'] ?? '');
                $tmpName = (string) ($file['tmp_name'] ?? '');
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($extension, $allowed, true)) {
                    $message = 'Only JPG, JPEG, PNG, and WEBP files are allowed.';
                    $messageType = 'error';
                } else {
                    $message = 'Demo Mode: Data not saved';
                    $messageType = 'success';
                }
            }
        }
    }
}

$profile = null;
$photoSelect = $hasVendorPhotoColumn ? 'v.photo' : "'' AS photo";
$updatedAtSelect = $hasVendorUpdatedAtColumn ? 'v.updated_at AS vendor_updated_at' : 'u.created_at AS vendor_updated_at';
$stmt = $conn->prepare(
    "SELECT
        u.id AS user_id,
        u.name,
        u.email,
        u.created_at AS user_created_at,
        v.id AS vendor_id,
        v.phone,
        v.address,
        v.id_proof_type,
        v.id_proof_number,
        $photoSelect,
        $updatedAtSelect
     FROM users u
    LEFT JOIN vendors v ON v.user_id = u.id
     WHERE u.id = ?
     LIMIT 1"
);
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$profile || (int) ($profile['user_id'] ?? 0) <= 0) {
    header('Location: /street_vendor/login.php');
    exit();
}

$location = null;
$stmt = $conn->prepare(
    "SELECT l.spot_number, l.allocated_date, z.zone_name
     FROM locations l
     INNER JOIN zones z ON z.id = l.zone_id
     WHERE l.vendor_id = ?
     ORDER BY l.is_active DESC, l.allocated_date DESC
     LIMIT 1"
);
if ($stmt) {
    $vendorId = (int) ($profile['vendor_id'] ?? 0);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $location = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$latestLicense = null;
$hasLicenseStatus = tableColumnExists($conn, 'licenses', 'status');
$hasLicenseAppliedDate = tableColumnExists($conn, 'licenses', 'applied_date');
$hasLicenseExpiryDate = tableColumnExists($conn, 'licenses', 'expiry_date');
$hasLicenseNumber = tableColumnExists($conn, 'licenses', 'license_number');
$hasLicenseCreatedAt = tableColumnExists($conn, 'licenses', 'created_at');

$statusSelect = $hasLicenseStatus ? 'status' : "'pending' AS status";
$appliedDateSelect = $hasLicenseAppliedDate ? 'applied_date' : 'NULL AS applied_date';
$expiryDateSelect = $hasLicenseExpiryDate ? 'expiry_date' : 'NULL AS expiry_date';
$licenseNumberSelect = $hasLicenseNumber ? 'license_number' : "'' AS license_number";
$licenseOrderBy = $hasLicenseCreatedAt ? 'created_at DESC' : 'id DESC';

$stmt = $conn->prepare(
    "SELECT $statusSelect, $appliedDateSelect, $expiryDateSelect, $licenseNumberSelect
     FROM licenses
     WHERE vendor_id = ?
     ORDER BY $licenseOrderBy
     LIMIT 1"
);
if ($stmt) {
    $vendorId = (int) ($profile['vendor_id'] ?? 0);
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $latestLicense = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$vendorName = (string) ($profile['name'] ?? 'Vendor User');
$vendorCode = 'VND-' . str_pad((string) ((int) ($profile['vendor_id'] ?? $userId)), 4, '0', STR_PAD_LEFT);
$zoneName = (string) ($location['zone_name'] ?? 'Not Assigned');
$stallInfo = (string) ($location['spot_number'] ?? 'Not Assigned');
$businessType = $latestLicense && !empty($latestLicense['license_number']) ? 'Licensed Street Vendor' : 'Street Vendor';
$profileStatus = $latestLicense && strtolower((string) ($latestLicense['status'] ?? 'pending')) === 'approved' ? 'Verified' : 'Standard';
$licenseType = 'Vendor License';
$appliedOn = !empty($latestLicense['applied_date']) ? date('M d, Y', strtotime((string) $latestLicense['applied_date'])) : 'Not Available';
$expiryOn = !empty($latestLicense['expiry_date']) ? date('M d, Y', strtotime((string) $latestLicense['expiry_date'])) : 'Not Available';
$lastLogin = isset($_SESSION['last_login']) ? (string) $_SESSION['last_login'] : date('M d, Y h:i A');

$photoPath = (string) ($profile['photo'] ?? '');
$photoUrl = '/street_vendor/assets/img/default-avatar.png';
if ($photoPath !== '') {
    $photoUrl = '/street_vendor/' . ltrim(str_replace('\\', '/', $photoPath), '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Profile</title>
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
            --shadow: 0 12px 36px rgba(10, 12, 15, 0.55);
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
            background: repeating-linear-gradient(
                118deg,
                rgba(255, 255, 255, 0.03) 0,
                rgba(255, 255, 255, 0.03) 1px,
                transparent 1px,
                transparent 29px
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
            animation: iconPulse 2.3s infinite ease-in-out;
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
            z-index: 2;
        }

        .top-navbar {
            background: linear-gradient(145deg, rgba(38, 46, 56, 0.93), rgba(50, 59, 70, 0.93));
            border: 1px solid rgba(200, 125, 58, 0.38);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
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
            color: var(--text-secondary);
            font-weight: 600;
        }

        .nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .shape-hex,
        .shape-wave,
        .shape-bar,
        .shape-glow,
        .shape-capsule,
        .shape-poly,
        .shape-line {
            position: absolute;
            pointer-events: none;
            z-index: 0;
        }

        .shape-hex {
            width: 88px;
            height: 88px;
            clip-path: polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%);
            background: rgba(200,125,58,0.2);
            border: 1px solid rgba(200,125,58,0.32);
            animation: floatShape 8s ease-in-out infinite;
        }

        .hex-a { top: 106px; left: 10%; }
        .hex-b { top: 148px; left: 16%; width: 56px; height: 56px; animation-delay: 1.1s; }

        .shape-wave {
            width: 300px;
            height: 78px;
            left: 4%;
            bottom: 22px;
            border-radius: 120px;
            background: linear-gradient(110deg, rgba(200,125,58,0.17), rgba(56,65,78,0.03));
            border: 1px solid rgba(200,125,58,0.18);
            animation: waveDrift 11s ease-in-out infinite;
        }

        .shape-bar {
            width: 220px;
            height: 16px;
            border-radius: 11px;
            background: linear-gradient(90deg, rgba(200,125,58,0.46), rgba(200,125,58,0.04));
            transform: rotate(-14deg);
            border: 1px solid rgba(200,125,58,0.22);
            animation: barDrift 9.8s ease-in-out infinite;
        }

        .bar-a { top: 320px; right: 18%; }
        .bar-b { top: 420px; right: 25%; animation-delay: 2s; }

        .shape-glow {
            width: 230px;
            height: 230px;
            border-radius: 50%;
            top: 160px;
            right: 4%;
            background: radial-gradient(circle at 40% 30%, rgba(200,125,58,0.3), rgba(56,65,78,0.05) 72%);
            filter: blur(2px);
            animation: glowPulse 7s ease-in-out infinite;
        }

        .shape-capsule {
            width: 138px;
            height: 42px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(200,125,58,0.2), rgba(200,125,58,0.03));
            border: 1px solid rgba(200,125,58,0.22);
            animation: capsuleFloat 8.6s ease-in-out infinite;
        }

        .cap-a { top: 236px; left: 39%; }

        .shape-poly {
            width: 110px;
            height: 92px;
            top: 220px;
            left: 54%;
            clip-path: polygon(7% 17%, 62% 0, 100% 35%, 86% 86%, 30% 100%, 0 58%);
            background: rgba(200,125,58,0.16);
            border: 1px solid rgba(200,125,58,0.24);
            animation: polyTilt 10s ease-in-out infinite;
        }

        .shape-line {
            width: 170px;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgba(200,125,58,0.46), transparent);
            animation: lineDrift 8s ease-in-out infinite;
        }

        .line-a { top: 348px; left: 50%; }
        .line-b { top: 386px; left: 58%; animation-delay: 1.6s; }

        .profile-hero {
            position: relative;
            z-index: 2;
            background: linear-gradient(150deg, rgba(56, 65, 78, 0.95), rgba(72, 84, 98, 0.95));
            border-radius: 42px 18px 46px 30px;
            border: 2px solid rgba(200,125,58,0.46);
            box-shadow: 0 18px 42px rgba(8, 12, 17, 0.62), inset 0 1px 0 rgba(255,255,255,0.08);
            padding: 18px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            margin-bottom: 14px;
            animation: heroDrop 0.9s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .avatar-wrap {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 2px solid rgba(200,125,58,0.64);
            box-shadow: 0 0 0 4px rgba(200,125,58,0.18), 0 10px 18px rgba(9,12,17,0.48);
            overflow: hidden;
            background: rgba(25,31,38,0.65);
            position: relative;
        }

        .avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .hero-info h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.35rem;
            font-weight: 800;
        }

        .hero-meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .hero-chip {
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(200,125,58,0.44);
            background: rgba(200,125,58,0.15);
            color: #f7e8d7;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .status-badge {
            padding: 8px 11px;
            border-radius: 999px;
            border: 1px solid rgba(200,125,58,0.55);
            background: rgba(200,125,58,0.18);
            color: #f9ebdd;
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 800;
            white-space: nowrap;
        }

        .profile-layout {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, 1.52fr) minmax(300px, 0.72fr);
            gap: 14px;
            align-items: start;
        }

        .left-main {
            display: grid;
            gap: 12px;
        }

        .profile-details {
            background: linear-gradient(152deg, rgba(56, 65, 78, 0.95), rgba(70, 82, 96, 0.95));
            border-radius: 30px 14px 36px 24px;
            border: 1.8px solid rgba(200,125,58,0.45);
            box-shadow: 0 16px 40px rgba(9, 12, 17, 0.58), inset 0 1px 0 rgba(255,255,255,0.08);
            padding: 16px;
            animation: cardReveal 0.85s ease;
        }

        .details-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .details-head h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 9px;
        }

        .detail-box {
            grid-column: span 6;
            border-radius: 14px;
            padding: 10px;
            border: 1px solid rgba(200,125,58,0.3);
            background: rgba(24, 30, 37, 0.48);
            transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease;
        }

        .detail-box.wide {
            grid-column: span 12;
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
            font-size: 0.86rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .form-wrap {
            margin-top: 10px;
            border-top: 1px solid rgba(200,125,58,0.26);
            padding-top: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(200px, 1fr));
            gap: 8px;
        }

        .field {
            border-radius: 12px;
            border: 1px solid rgba(200,125,58,0.3);
            background: rgba(24,30,37,0.45);
            padding: 9px;
        }

        .field label {
            display: block;
            color: #e4d7c8;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid rgba(200,125,58,0.35);
            border-radius: 10px;
            padding: 8px 10px;
            color: var(--text-primary);
            background: rgba(17, 22, 28, 0.88);
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.24s ease;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: rgba(200,125,58,0.72);
            box-shadow: 0 0 0 3px rgba(200,125,58,0.2);
        }

        .field textarea {
            min-height: 76px;
            resize: vertical;
        }

        .right-stack {
            display: grid;
            gap: 10px;
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

        .stack-card:nth-child(1) { min-height: 120px; animation-delay: 0.06s; }
        .stack-card:nth-child(2) { min-height: 136px; margin-left: 10px; animation-delay: 0.14s; }
        .stack-card:nth-child(3) { min-height: 112px; animation-delay: 0.22s; }
        .stack-card:nth-child(4) { min-height: 126px; margin-left: 6px; animation-delay: 0.3s; }

        .stack-card h4 {
            margin: 0;
            font-size: 0.87rem;
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
            margin: 8px 0 0;
            padding: 0;
            display: grid;
            gap: 6px;
        }

        .stack-card li,
        .stack-card p,
        .stack-card small {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.77rem;
            font-weight: 600;
            line-height: 1.45;
        }

        .stack-card li {
            padding: 7px 8px;
            border-radius: 10px;
            border: 1px solid rgba(200,125,58,0.24);
            background: rgba(25,31,38,0.42);
        }

        .btn-copper {
            border: 1px solid rgba(200, 125, 58, 0.62);
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 800;
            font-size: 0.72rem;
            color: #fff1e0;
            background: linear-gradient(145deg, #c48243 0%, #b06f33 60%, #905725 100%);
            box-shadow: 0 8px 16px rgba(9, 12, 17, 0.45), inset 0 1px 0 rgba(255,255,255,0.2);
            transition: all 0.24s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .btn-copper::before {
            content: '';
            position: absolute;
            left: -55%;
            top: 0;
            width: 34%;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.28), rgba(255,255,255,0));
            transform: skewX(-20deg);
            transition: left 0.45s ease;
        }

        .btn-copper:hover {
            transform: translateY(-2px) scale(1.02);
            color: #fff5ea;
            box-shadow: 0 12px 20px rgba(8, 11, 15, 0.56), 0 0 12px rgba(200, 125, 58, 0.22);
        }

        .btn-copper:hover::before { left: 120%; }

        .btn-row {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .timeline-panel {
            position: relative;
            z-index: 2;
            margin-top: 12px;
            background: linear-gradient(154deg, rgba(56, 65, 78, 0.94), rgba(71, 83, 97, 0.94));
            border: 1.8px solid rgba(200,125,58,0.45);
            border-radius: 26px;
            box-shadow: 0 14px 36px rgba(9, 12, 17, 0.56), inset 0 1px 0 rgba(255,255,255,0.07);
            padding: 14px;
            animation: timelineUp 0.9s ease;
        }

        .timeline-panel h3 {
            margin: 0 0 10px;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .timeline li {
            border: 1px solid rgba(200,125,58,0.24);
            border-radius: 12px;
            background: rgba(25,31,38,0.42);
            padding: 9px 10px;
            color: var(--text-secondary);
            font-size: 0.79rem;
            font-weight: 600;
        }

        .timeline strong {
            color: #f7e8d8;
            display: inline-block;
            margin-bottom: 2px;
            font-weight: 800;
        }

        .msg {
            margin: 0 0 10px;
            padding: 9px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid;
        }

        .msg.success {
            color: #ddf7d9;
            background: rgba(47, 94, 43, 0.45);
            border-color: rgba(151, 205, 145, 0.45);
        }

        .msg.error {
            color: #ffd2d2;
            background: rgba(122, 38, 38, 0.45);
            border-color: rgba(230, 150, 150, 0.45);
        }

        @keyframes pageFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideInTop { from { opacity: 0; transform: translateY(-34px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes heroDrop { from { opacity: 0; transform: translateY(-20px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes cardReveal { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes staggerReveal { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes timelineUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes iconPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        @keyframes floatShape { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
        @keyframes waveDrift { 0%,100% { transform: translateX(0) scaleX(1); } 50% { transform: translateX(10px) scaleX(1.03); } }
        @keyframes barDrift { 0%,100% { transform: rotate(-14deg) translateX(0); } 50% { transform: rotate(-12deg) translateX(9px); } }
        @keyframes glowPulse { 0%,100% { opacity: 0.44; } 50% { opacity: 0.9; } }
        @keyframes capsuleFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes polyTilt { 0%,100% { transform: rotate(0deg); } 50% { transform: rotate(8deg); } }
        @keyframes lineDrift { 0%,100% { transform: translateX(0); opacity: 0.3; } 50% { transform: translateX(12px); opacity: 0.82; } }

        @media (max-width: 1140px) {
            .profile-layout { grid-template-columns: 1fr; }
            .stack-card:nth-child(2), .stack-card:nth-child(4) { margin-left: 0; }
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
            .profile-hero {
                grid-template-columns: 1fr;
                justify-items: start;
            }
            .details-grid { grid-template-columns: 1fr; }
            .detail-box, .detail-box.wide { grid-column: span 1; }
            .form-grid { grid-template-columns: 1fr; }
            .shape-hex, .shape-wave, .shape-bar, .shape-glow, .shape-capsule, .shape-poly, .shape-line { opacity: 0.45; }
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
            <div class="sidebar-item active" onclick="window.location.href='profile.php'">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </div>
        </nav>

        <main class="main-content">
            <div class="top-navbar">
                <div class="nav-title">
                    <h1>Vendor Profile Dashboard</h1>
                    <p>Identity, account controls, activity trail, and profile management</p>
                </div>
                <span class="nav-pill"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($vendorCode); ?></span>
            </div>

            <div class="shape-hex hex-a"></div>
            <div class="shape-hex hex-b"></div>
            <div class="shape-wave"></div>
            <div class="shape-bar bar-a"></div>
            <div class="shape-bar bar-b"></div>
            <div class="shape-glow"></div>
            <div class="shape-capsule cap-a"></div>
            <div class="shape-poly"></div>
            <div class="shape-line line-a"></div>
            <div class="shape-line line-b"></div>

            <?php if ($message !== ''): ?>
                <div class="msg <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <section class="profile-hero">
                <div class="avatar-wrap">
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Vendor avatar">
                </div>
                <div class="hero-info">
                    <h2><?php echo htmlspecialchars($vendorName); ?></h2>
                    <div class="hero-meta">
                        <span class="hero-chip">Vendor ID: <?php echo htmlspecialchars($vendorCode); ?></span>
                        <span class="hero-chip">Stall / Business: <?php echo htmlspecialchars($stallInfo . ' / ' . $businessType); ?></span>
                        <span class="hero-chip">Zone: <?php echo htmlspecialchars($zoneName); ?></span>
                    </div>
                </div>
                <span class="status-badge"><?php echo htmlspecialchars($profileStatus); ?></span>
            </section>

            <section class="profile-layout">
                <div class="left-main">
                    <section class="profile-details">
                        <div class="details-head">
                            <h3><i class="fas fa-address-card"></i> Profile Details Card</h3>
                            <span class="nav-pill"><i class="fas fa-phone"></i> <?php echo htmlspecialchars((string) ($profile['phone'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="details-grid">
                            <div class="detail-box"><label>Full Name</label><p><?php echo htmlspecialchars($vendorName); ?></p></div>
                            <div class="detail-box"><label>Phone Number</label><p><?php echo htmlspecialchars((string) ($profile['phone'] ?? 'N/A')); ?></p></div>
                            <div class="detail-box"><label>Email</label><p><?php echo htmlspecialchars((string) ($profile['email'] ?? 'N/A')); ?></p></div>
                            <div class="detail-box"><label>Aadhaar / ID</label><p><?php echo htmlspecialchars((string) ($profile['id_proof_type'] ?? 'ID') . ': ' . (string) ($profile['id_proof_number'] ?? 'N/A')); ?></p></div>
                            <div class="detail-box"><label>Business Name</label><p><?php echo htmlspecialchars($vendorName . ' Enterprises'); ?></p></div>
                            <div class="detail-box"><label>License Type</label><p><?php echo htmlspecialchars($licenseType); ?></p></div>
                            <div class="detail-box wide"><label>Address</label><p><?php echo nl2br(htmlspecialchars((string) ($profile['address'] ?? 'Not Available'))); ?></p></div>
                        </div>

                        <div class="form-wrap">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="action" value="update_details">
                                <div class="form-grid">
                                    <div class="field">
                                        <label>Full Name</label>
                                        <input type="text" name="name" value="<?php echo htmlspecialchars((string) ($profile['name'] ?? '')); ?>" required>
                                    </div>
                                    <div class="field">
                                        <label>Phone Number</label>
                                        <input type="text" name="phone" value="<?php echo htmlspecialchars((string) ($profile['phone'] ?? '')); ?>" required>
                                    </div>
                                    <div class="field">
                                        <label>ID Proof Type</label>
                                        <select name="id_proof_type">
                                            <?php
                                            $proofTypes = ['Aadhar Card', 'PAN Card', 'Voter ID', 'Driving License', 'Passport'];
                                            foreach ($proofTypes as $type):
                                            ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ((string) ($profile['id_proof_type'] ?? '') === $type) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>ID Proof Number</label>
                                        <input type="text" name="id_proof_number" value="<?php echo htmlspecialchars((string) ($profile['id_proof_number'] ?? '')); ?>" required>
                                    </div>
                                    <div class="field" style="grid-column: span 2;">
                                        <label>Address</label>
                                        <textarea name="address" required><?php echo htmlspecialchars((string) ($profile['address'] ?? '')); ?></textarea>
                                    </div>
                                </div>
                                <div class="btn-row">
                                    <button class="btn-copper" type="submit"><i class="fas fa-user-pen"></i> Update Details</button>
                                    <a class="btn-copper" href="/street_vendor/update_pass.php"><i class="fas fa-key"></i> Change Password</a>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>

                <aside class="right-stack">
                    <article class="stack-card">
                        <h4><i class="fas fa-shield-check"></i> Account Status</h4>
                        <ul>
                            <li>Status: <?php echo htmlspecialchars($profileStatus); ?></li>
                            <li>Zone: <?php echo htmlspecialchars($zoneName); ?></li>
                            <li>License: <?php echo htmlspecialchars((string) ($latestLicense['status'] ?? 'pending')); ?></li>
                        </ul>
                    </article>

                    <article class="stack-card">
                        <h4><i class="fas fa-clock-rotate-left"></i> Recent Activity</h4>
                        <ul>
                            <li>Profile viewed today</li>
                            <li>Last update: <?php echo htmlspecialchars(date('M d, Y', strtotime((string) ($profile['vendor_updated_at'] ?? 'now')))); ?></li>
                            <li>Last login: <?php echo htmlspecialchars($lastLogin); ?></li>
                        </ul>
                    </article>

                    <article class="stack-card">
                        <h4><i class="fas fa-bell"></i> Notifications</h4>
                        <p>Keep phone and ID details current for smooth license renewals and admin verification checks.</p>
                    </article>

                    <article class="stack-card">
                        <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                        <div class="btn-row">
                            <a class="btn-copper" href="#"><i class="fas fa-user-pen"></i> Edit Profile</a>
                            <?php if ($hasVendorPhotoColumn): ?>
                            <button class="btn-copper" type="button" onclick="document.getElementById('photoInput').click();"><i class="fas fa-camera"></i> Upload Photo</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasVendorPhotoColumn): ?>
                        <form method="POST" action="" enctype="multipart/form-data" style="margin-top:8px;">
                            <input type="hidden" name="action" value="upload_photo">
                            <input id="photoInput" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" style="display:none" onchange="this.form.submit()">
                        </form>
                        <?php else: ?>
                        <small>Upload is disabled because this database does not have a <strong>vendors.photo</strong> column.</small>
                        <?php endif; ?>
                    </article>
                </aside>
            </section>

            <section class="timeline-panel">
                <h3><i class="fas fa-timeline"></i> Activity Timeline</h3>
                <ul class="timeline">
                    <li><strong>Profile Updated</strong><br>Latest profile details synchronized on <?php echo htmlspecialchars(date('M d, Y', strtotime((string) ($profile['vendor_updated_at'] ?? 'now')))); ?>.</li>
                    <li><strong>Recent Changes</strong><br>ID proof and contact details ready for admin review workflows.</li>
                    <li><strong>Last Login</strong><br><?php echo htmlspecialchars($lastLogin); ?></li>
                    <li><strong>Document Uploads</strong><br><?php echo htmlspecialchars($photoPath !== '' ? 'Profile photo uploaded and linked to account.' : 'No custom profile photo uploaded yet.'); ?></li>
                </ul>
            </section>
        </main>
    </div>
</body>
</html>
