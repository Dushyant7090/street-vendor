<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor' || empty($_SESSION['user_id'])) {
    redirect('/street_vendor/login.php');
}

$name = $_SESSION['name'] ?? 'Vendor User';
$userId = (int) ($_SESSION['user_id'] ?? 0);

function fetchSingleRow(mysqli $conn, string $sql, int $id): ?array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetchCount(mysqli $conn, string $sql, int $id): int
{
    $row = fetchSingleRow($conn, $sql, $id);
    return (int) ($row['count'] ?? 0);
}

$vendorId = 0;
$phone = 'Not available';
$zoneName = 'Not available';
$activeLicenses = 0;
$registeredLocations = 0;
$pendingApplications = 0;
$latestStatus = 'No data available';
$latestLicenseType = 'Not available';
$percentage = 0;
$remainingDays = 0;
$expiryDate = 'Not available';

$vendor = fetchSingleRow($conn, 'SELECT id, phone FROM vendors WHERE user_id = ? LIMIT 1', $userId);
if ($vendor) {
    $vendorId = (int) ($vendor['id'] ?? 0);
    $phone = trim((string) ($vendor['phone'] ?? ''));
    if ($phone === '') {
        $phone = 'Not available';
    }
}

if ($vendorId > 0) {
    $zoneRow = fetchSingleRow(
        $conn,
        'SELECT z.zone_name FROM locations l JOIN zones z ON l.zone_id = z.id WHERE l.vendor_id = ? LIMIT 1',
        $vendorId
    );
    if ($zoneRow) {
        $zoneName = trim((string) ($zoneRow['zone_name'] ?? ''));
        if ($zoneName === '') {
            $zoneName = 'Not available';
        }
    }

    $activeLicenses = fetchCount($conn, "SELECT COUNT(*) AS count FROM licenses WHERE vendor_id = ? AND status='approved'", $vendorId);
    $registeredLocations = fetchCount($conn, 'SELECT COUNT(*) AS count FROM locations WHERE vendor_id = ?', $vendorId);
    $pendingApplications = fetchCount($conn, "SELECT COUNT(*) AS count FROM licenses WHERE vendor_id = ? AND status='pending'", $vendorId);

    $latestRow = fetchSingleRow($conn, 'SELECT status, license_type FROM licenses WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 1', $vendorId);
    if ($latestRow) {
        $latestStatus = ucfirst((string) ($latestRow['status'] ?? 'pending'));
        if ($latestStatus === '') {
            $latestStatus = 'No data available';
        }

        $latestLicenseType = trim((string) ($latestRow['license_type'] ?? ''));
        if ($latestLicenseType === '') {
            $latestLicenseType = 'Not available';
        }
    }

    $progressRow = fetchSingleRow(
        $conn,
        "SELECT expiry_date FROM licenses WHERE vendor_id = ? AND status='approved' AND expiry_date IS NOT NULL ORDER BY expiry_date DESC LIMIT 1",
        $vendorId
    );

    if ($progressRow && !empty($progressRow['expiry_date'])) {
        try {
            $expiry = new DateTime((string) $progressRow['expiry_date']);
            $now = new DateTime();
            $totalDays = 365;
            $remainingDays = $expiry > $now ? $expiry->diff($now)->days : 0;
            $percentage = min(100, ($remainingDays / $totalDays) * 100);
            $expiryDate = $expiry->format('M d, Y');
        } catch (Throwable $e) {
            $remainingDays = 0;
            $percentage = 0;
            $expiryDate = 'Not available';
        }
    }
}

$dashboardFlashType = '';
$dashboardFlashMessage = '';
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $dashboardFlashType = (string) ($_SESSION['flash']['type'] ?? 'success');
    $dashboardFlashMessage = (string) ($_SESSION['flash']['message'] ?? '');
    unset($_SESSION['flash']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard</title>
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Premium Whitish-Grey Luxury Color Palette */
            --main-panel: #f0ede9; /* soft white-grey */
            --sidebar-bg: #f0ede9; /* soft white-grey */
            --top-navbar: #f0ede9; /* soft white-grey */
            --card-bg: #e8e5e0; /* mist grey */
            --button-bg: #dcdad7; /* light silver grey */
            --hover-bg: #d0ccc7; /* smoke grey */
            --accent: #b8b2ab; /* warm pearl grey */
            --text-primary: #3a3834; /* dark grey */
            --text-secondary: #8a8580; /* medium grey */
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            --border-radius: 28px;
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

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
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
            backdrop-filter: blur(10px);
            border: 1px solid rgba(184,178,171,0.2);
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
            border-radius: 12px;
            margin: 5px 10px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 20px;
        }

        /* Hero Section - HIDDEN */
        .hero-section {
            display: none;
        }

        .hero-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .welcome-text p {
            color: var(--accent);
            margin: 5px 0 0 0;
        }

        .hero-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            color: var(--accent);
            font-size: 0.9rem;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }

        /* Premium Dashboard Header - Curved */
        .dashboard-header {
            background: linear-gradient(150deg, rgba(232,229,224,0.98) 0%, rgba(240,237,233,1) 100%);
            padding: 45px 50px;
            border-radius: 40px 15px 50px 50px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(15px);
            border: 2px solid rgba(184,178,171,0.25);
            box-shadow: 0 12px 45px rgba(0,0,0,0.12), inset 0 1px 0px rgba(255,255,255,0.5);
            animation: slideInTop 0.9s cubic-bezier(0.34, 1.56, 0.64, 1);
            grid-column: span 4;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(184,178,171,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .dashboard-header h2 {
            color: var(--text-primary);
            font-size: 2.2rem;
            margin: 0;
            font-weight: 800;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .dashboard-header p {
            color: var(--accent);
            margin: 8px 0 0 0;
            font-size: 1rem;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        @keyframes slideInTop {
            from { opacity: 0; transform: translateY(-40px) rotateX(10deg); }
            to { opacity: 1; transform: translateY(0) rotateX(0); }
        }

        .analytics-panel {
            background: linear-gradient(145deg, rgba(232,229,224,0.95) 0%, rgba(236,233,228,0.98) 100%);
            border-radius: 50px 20px 50px 50px;
            padding: 40px 35px;
            border: 2px solid rgba(184,178,171,0.22);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1), inset 0 1px 0px rgba(255,255,255,0.6);
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(15px);
            animation: slideInUp 1s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
            grid-column: span 1;
            position: relative;
            overflow: hidden;
        }

        .analytics-panel::before {
            content: '';
            position: absolute;
            top: -40%;
            left: -30%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(184,178,171,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .analytics-panel > * {
            position: relative;
            z-index: 1;
        }

        .analytics-panel:hover {
            transform: translateY(-16px) scale(1.08) rotate(-1deg);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), inset 0 1px 0px rgba(255,255,255,0.7);
            background: linear-gradient(145deg, rgba(228,225,220,0.98) 0%, rgba(232,229,224,1) 100%);
            border-color: rgba(184,178,171,0.35);
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .progress-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .progress-container h4 {
            color: var(--text-primary);
            margin: 15px 0 10px 0;
            font-weight: 700;
            font-size: 1.05rem;
        }

        .progress-container p {
            color: var(--text-secondary);
            margin: 5px 0;
            font-size: 0.9rem;
        }

        .progress-ring {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }

        .progress-ring circle {
            fill: none;
            stroke-width: 8;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        .progress-ring .bg {
            stroke: rgba(138,133,128,0.1);
        }

        .progress-ring .fg {
            stroke: var(--accent);
            stroke-dasharray: 377;
            stroke-dashoffset: 377;
            transition: stroke-dashoffset 1s ease-in-out;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }

        .timeline-panel {
            background: linear-gradient(160deg, rgba(232,229,224,0.96) 0%, rgba(236,233,228,0.99) 100%);
            border-radius: 30px 50px 30px 50px;
            padding: 40px;
            border: 2px solid rgba(184,178,171,0.2);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1), inset 0 1px 0px rgba(255,255,255,0.5);
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(15px);
            animation: slideInRight 1.1s cubic-bezier(0.34, 1.56, 0.64, 1);
            grid-column: span 2;
            position: relative;
            overflow: hidden;
        }

        .timeline-panel::after {
            content: '';
            position: absolute;
            bottom: -40%;
            right: -30%;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(184,178,171,0.07) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .timeline-panel > * {
            position: relative;
            z-index: 1;
        }

        .timeline-panel:hover {
            transform: translateY(-14px) rotate(0.5deg);
            box-shadow: 0 18px 55px rgba(0,0,0,0.12), inset 0 1px 0px rgba(255,255,255,0.6);
            border-color: rgba(184,178,171,0.32);
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(60px) skewX(-10deg); }
            to { opacity: 1; transform: translateX(0) skewX(0); }
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(184,178,171,0.15);
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-item i {
            color: var(--accent);
            font-size: 1.3rem;
            transition: all 0.3s ease;
        }
        
        .timeline-item:hover i {
            transform: scale(1.2) rotate(5deg);
            color: #9d9691;
        }

        .timeline-item .content h4 {
            color: var(--text-primary);
            margin: 0;
            font-size: 0.98rem;
            font-weight: 700;
        }

        .timeline-item .content p {
            color: var(--text-secondary);
            margin: 3px 0 0 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .actions-panel {
            display: flex;
            flex-direction: row;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .action-tile {
            background: linear-gradient(140deg, rgba(228,225,220,0.90) 0%, rgba(232,229,224,0.95) 100%);
            border-radius: 35px 50px 35px 50px;
            padding: 30px 35px;
            text-align: center;
            border: 2px solid rgba(184,178,171,0.25);
            box-shadow: 0 10px 35px rgba(0,0,0,0.1), inset 0 1px 0px rgba(255,255,255,0.6);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            backdrop-filter: blur(12px);
            animation: floatIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            min-width: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .action-tile::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(184,178,171,0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }

        .action-tile:hover::before {
            transform: scale(1.5);
        }

        .action-tile:hover {
            transform: translateY(-20px) scale(1.1) rotate(-2deg);
            box-shadow: 0 25px 60px rgba(0,0,0,0.15), inset 0 1px 0px rgba(255,255,255,0.7);
            background: linear-gradient(140deg, rgba(224,221,216,0.98) 0%, rgba(228,225,220,1) 100%);
            border-color: rgba(184,178,171,0.4);
        }

        @keyframes floatIn {
            0% { opacity: 0; transform: translateY(30px); }
            50% { opacity: 0.8; }
            100% { opacity: 1; transform: translateY(0); }
        }

        .action-tile i {
            color: var(--accent);
            font-size: 2.2rem;
            margin-bottom: 12px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 2;
        }
        
        .action-tile:hover i {
            font-size: 2.6rem;
            transform: scale(1.2) rotate(8deg);
            color: #9d9691;
            text-shadow: 0 0 15px rgba(184,178,171,0.3);
        }

        .action-tile h4 {
            color: var(--text-primary);
            margin: 10px 0 0 0;
            font-size: 1rem;
            font-weight: 700;
            transition: all 0.4s ease;
            position: relative;
            z-index: 2;
        }
        
        .action-tile:hover h4 {
            color: var(--accent);
            font-size: 1.05rem;
        }

        /* Table Section */
        .table-section {
            background: linear-gradient(155deg, rgba(232,229,224,0.95) 0%, rgba(236,233,228,0.98) 100%);
            border-radius: 50px 30px 50px 30px;
            padding: 40px;
            border: 2px solid rgba(184,178,171,0.22);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1), inset 0 1px 0px rgba(255,255,255,0.5);
            backdrop-filter: blur(15px);
            animation: slideInUp 1.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            grid-column: span 4;
            position: relative;
            overflow: hidden;
        }

        .table-section::before {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -30%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(184,178,171,0.07) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .table-section > * {
            position: relative;
            z-index: 1;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .table-section h3 {
            color: var(--text-primary);
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .analytics-panel h3 {
            color: var(--text-primary);
            margin-bottom: 25px;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .timeline-panel h3 {
            color: var(--text-primary);
            margin-bottom: 25px;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table {
            color: var(--text-primary);
        }

        .data-table th,
        .data-table td {
            padding: 16px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(184,178,171,0.15);
        }

        .data-table th {
            color: var(--accent);
            font-weight: 800;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(184,178,171,0.08);
            border-radius: 8px;
        }

        .data-table td {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .data-table tr:hover {
            background: rgba(184,178,171,0.06);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(184,178,171,0.15);
            color: var(--accent);
            border: 1px solid rgba(184,178,171,0.4);
            font-weight: 600;
        }

        .status-expiring {
            background: rgba(220,180,120,0.15);
            color: #a8844a;
            border: 1px solid rgba(220,180,120,0.4);
            font-weight: 600;
        }

        .status-expired {
            background: rgba(180,140,140,0.15);
            color: #8a6a6a;
            border: 1px solid rgba(180,140,140,0.4);
            font-weight: 600;
        }

        .renew-btn {
            background: var(--button-bg);
            color: var(--text-primary);
            border: 2px solid rgba(184,178,171,0.3);
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .renew-btn:hover {
            background: var(--hover-bg);
            box-shadow: 0 6px 18px rgba(184,178,171,0.25);
            transform: scale(1.08);
            border-color: rgba(184,178,171,0.5);
            color: var(--accent);
        }

        /* Floating Shapes */
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(184,178,171,0.06);
            animation: floatShape 8s ease-in-out infinite;
            pointer-events: none;
            border: 1px solid rgba(184,178,171,0.12);
        }

        .shape-1 { width: 120px; height: 120px; top: 15%; left: 10%; animation-delay: 0s; }
        .shape-2 { width: 100px; height: 100px; top: 50%; right: 8%; animation-delay: 2s; }
        .shape-3 { width: 80px; height: 80px; bottom: 20%; left: 15%; animation-delay: 4s; }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
            50% { transform: translateY(-30px) rotate(180deg) scale(1.1); }
        }

        /* Statistics Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
            grid-column: span 4;
        }

        .stat-card {
            background: linear-gradient(145deg, rgba(240,237,233,0.95) 0%, rgba(244,241,237,0.98) 100%);
            border-radius: 25px;
            padding: 30px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08), inset 0 1px 0px rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(12px);
            animation: slideInUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .stat-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12), inset 0 1px 0px rgba(255,255,255,0.7);
            border-color: rgba(184,178,171,0.3);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(232,229,224,0.8) 0%, rgba(228,225,220,0.9) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--accent);
            flex-shrink: 0;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .stat-content h3 {
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
        }

        .stat-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 5px 0 0 0;
        }

        /* Notifications Panel */
        .notifications-panel {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border-radius: 35px;
            padding: 40px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08), inset 0 1px 0px rgba(255,255,255,0.5);
            grid-column: span 2;
            backdrop-filter: blur(15px);
            animation: slideInUp 1s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .notifications-panel h3 {
            color: var(--text-primary);
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 0 25px 0;
            letter-spacing: -0.5px;
        }

        .notification-item {
            display: flex;
            gap: 18px;
            padding: 18px 0;
            border-bottom: 1px solid rgba(184,178,171,0.12);
            align-items: flex-start;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-badge {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
            font-weight: bold;
        }

        .notification-badge.success {
            background: linear-gradient(135deg, #a8d14a 0%, #8bc84a 100%);
        }

        .notification-badge.info {
            background: linear-gradient(135deg, #a8c8d8 0%, #8ab8d8 100%);
        }

        .notification-badge.warning {
            background: linear-gradient(135deg, #d8a8a8 0%, #d88a8a 100%);
        }

        .notification-content h4 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 5px 0;
        }

        .notification-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.4;
        }

        .notification-time {
            color: #a8a0a0;
            font-size: 0.8rem;
            display: block;
            margin-top: 8px;
            font-weight: 500;
        }

        /* Profile Summary */
        .profile-summary {
            background: linear-gradient(160deg, rgba(244,241,237,0.96) 0%, rgba(240,237,233,0.98) 100%);
            border-radius: 35px;
            padding: 40px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08), inset 0 1px 0px rgba(255,255,255,0.5);
            grid-column: span 2;
            backdrop-filter: blur(15px);
            animation: slideInUp 1.1s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .profile-summary h3 {
            color: var(--text-primary);
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 0 25px 0;
            letter-spacing: -0.5px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .profile-item {
            padding: 18px;
            background: rgba(255,255,255,0.3);
            border-radius: 16px;
            border: 1px solid rgba(184,178,171,0.15);
        }

        .profile-item label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }

        .profile-item p {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 600;
            margin: 0;
        }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .sidebar {
                width: 60px;
            }
            .main-content {
                margin-left: 60px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 50px;
            }
            .main-content {
                margin-left: 50px;
            }
            .hero-stats {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/dashboard.php'">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </div>
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/my_location.php'">
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

        <!-- Main Content -->
        <div class="main-content">
            <?php if ($dashboardFlashMessage !== ''): ?>
                <div style="margin: 0 0 16px 0; padding: 12px 16px; border-radius: 12px; font-weight: 600; background: <?php echo $dashboardFlashType === 'error' ? '#fdecec' : '#e8f6ed'; ?>; color: <?php echo $dashboardFlashType === 'error' ? '#a04646' : '#256f3f'; ?>; border: 1px solid <?php echo $dashboardFlashType === 'error' ? '#f4c2c2' : '#b9dfc7'; ?>;">
                    <?php echo htmlspecialchars($dashboardFlashMessage); ?>
                </div>
            <?php endif; ?>

            <div class="floating-shape shape-1"></div>
            <div class="floating-shape shape-2"></div>
            <div class="floating-shape shape-3"></div>
            <!-- Dashboard Header -->
            <div class="dashboard-grid" style="grid-column: span 4;">
                <div class="dashboard-header" style="grid-column: span 4;">
                    <div>
                        <h2>Welcome back, <?php echo htmlspecialchars($name); ?>!</h2>
                        <p>Your comprehensive vendor dashboard</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Analytics Panel -->
                <div class="analytics-panel">
                <h3 style="color: var(--text-primary); margin-bottom: 20px;">License Analytics</h3>
                    <div class="progress-container">
                        <div class="progress-ring">
                            <svg width="150" height="150">
                                <circle class="bg" cx="75" cy="75" r="60"></circle>
                                <circle class="fg" cx="75" cy="75" r="60" style="stroke-dashoffset: <?php echo 377 - (377 * $percentage / 100); ?>;"></circle>
                            </svg>
                            <div class="progress-text"><?php echo round($percentage); ?>%</div>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 10px 0;">License Validity</h4>
                        <p style="color: var(--text-secondary); margin: 0;">Days Remaining: <?php echo $remainingDays; ?></p>
                        <p style="color: var(--text-secondary); margin: 5px 0 0 0;">Expires: <?php echo $expiryDate; ?></p>
                    </div>
                </div>

                <!-- Timeline Panel -->
                <div class="timeline-panel">
                    <h3 style="color: var(--text-primary); margin-bottom: 20px;">Recent Activities</h3>
                    <div class="timeline-item">
                        <i class="fas fa-file-alt"></i>
                        <div class="content">
                            <h4>License Application Submitted</h4>
                            <p>2 days ago</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <i class="fas fa-upload"></i>
                        <div class="content">
                            <h4>Document Uploaded</h4>
                            <p>1 week ago</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="content">
                            <h4>Location Updated</h4>
                            <p>2 weeks ago</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <i class="fas fa-download"></i>
                        <div class="content">
                            <h4>License Downloaded</h4>
                            <p>1 month ago</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="stats-section">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $activeLicenses; ?></h3>
                            <p>Active Licenses</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-map-pin"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $registeredLocations; ?></h3>
                            <p>Registered Locations</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo htmlspecialchars($latestStatus); ?></h3>
                            <p>Latest Application (<?php echo htmlspecialchars($latestLicenseType); ?>)</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo htmlspecialchars($zoneName); ?></h3>
                            <p>Current Zone</p>
                        </div>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div class="notifications-panel">
                    <h3>Notifications & Updates</h3>
                    <div class="notification-item">
                        <div class="notification-badge success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="notification-content">
                            <h4>License Active</h4>
                            <p>Your license has been approved and is now active</p>
                            <span class="notification-time">Today</span>
                        </div>
                    </div>

                    <div class="notification-item">
                        <div class="notification-badge info">
                            <i class="fas fa-info"></i>
                        </div>
                        <div class="notification-content">
                            <h4>Location Verified</h4>
                            <p>Your location at Zone A has been verified and registered</p>
                            <span class="notification-time">2 days ago</span>
                        </div>
                    </div>

                    <div class="notification-item">
                        <div class="notification-badge warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="notification-content">
                            <h4>License Expiry Notice</h4>
                            <p>Your license will expire in 45 days. Please prepare for renewal</p>
                            <span class="notification-time">1 week ago</span>
                        </div>
                    </div>
                </div>

                <!-- Profile Summary Section -->
                <div class="profile-summary">
                    <h3>Profile Summary</h3>
                    <div class="profile-grid">
                        <div class="profile-item">
                            <label>Vendor Name</label>
                            <p><?php echo htmlspecialchars($name); ?></p>
                        </div>
                        <div class="profile-item">
                            <label>Phone</label>
                            <p><?php echo htmlspecialchars($phone); ?></p>
                        </div>
                        <div class="profile-item">
                            <label>Current Zone</label>
                            <p><?php echo htmlspecialchars($zoneName); ?></p>
                        </div>
                        <div class="profile-item">
                            <label>Member Since</label>
                            <p>Apr 2024</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add hover effects
        document.querySelectorAll('.analytics-panel, .timeline-panel, .stat-card').forEach(panel => {
            panel.addEventListener('mouseenter', () => {
                panel.style.transform = 'translateY(-5px)';
            });
            panel.addEventListener('mouseleave', () => {
                panel.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>