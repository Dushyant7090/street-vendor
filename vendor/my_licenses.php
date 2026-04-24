<?php
session_start();
include __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: /street_vendor/login.php");
    exit();
}

$name = $_SESSION['name'] ?? 'Vendor User';
$userId = $_SESSION['user_id'] ?? 0;

$vendorStmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->bind_param("i", $userId);
$vendorStmt->execute();
$vendorResult = $vendorStmt->get_result();
$vendor = $vendorResult->fetch_assoc();
$vendorId = (int) ($vendor['id'] ?? 0);
$vendorStmt->close();

$licenses = [];
if ($vendorId > 0) {
    $licenseStmt = $conn->prepare("SELECT id, license_number, status, applied_date, issue_date, expiry_date, remarks FROM licenses WHERE vendor_id = ? ORDER BY created_at DESC");
    $licenseStmt->bind_param("i", $vendorId);
    $licenseStmt->execute();
    $licenses = $licenseStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $licenseStmt->close();
}

$current = $licenses[0] ?? null;
$licenseNumber = $current['license_number'] ?? 'Not Issued';
$status = $current['status'] ?? 'pending';
$issueDate = !empty($current['issue_date']) ? date('M d, Y', strtotime($current['issue_date'])) : 'Not Issued';
$expiryDate = !empty($current['expiry_date']) ? date('M d, Y', strtotime($current['expiry_date'])) : 'Not Available';
$remarks = trim((string) ($current['remarks'] ?? ''));

$renewalStatus = 'Not Required';
$daysRemaining = null;

if (!empty($current['expiry_date'])) {
    $today = new DateTime();
    $expiry = new DateTime($current['expiry_date']);
    $daysRemaining = (int) $today->diff($expiry)->format('%r%a');

    if ($daysRemaining < 0) {
        $renewalStatus = 'Expired - Renewal Required';
    } elseif ($daysRemaining <= 45) {
        $renewalStatus = 'Due Soon';
    } else {
        $renewalStatus = 'Up to Date';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Licenses</title>
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

        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 30px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }

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
            box-shadow: 0 12px 45px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.5);
            animation: slideInTop 0.9s cubic-bezier(0.34, 1.56, 0.64, 1);
            grid-column: span 4;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header h2 {
            color: var(--text-primary);
            font-size: 2.2rem;
            margin: 0;
            font-weight: 800;
        }

        .dashboard-header p {
            color: var(--accent);
            margin: 8px 0 0 0;
            font-size: 1rem;
            font-weight: 500;
        }

        .license-card {
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border-radius: 35px;
            padding: 36px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.5);
            backdrop-filter: blur(15px);
            animation: slideInUp 1s cubic-bezier(0.34, 1.56, 0.64, 1);
            grid-column: span 4;
            transition: all 0.4s ease;
        }

        .license-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 52px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.65);
        }

        .license-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 16px;
        }

        .license-item {
            padding: 18px;
            background: rgba(255,255,255,0.35);
            border-radius: 16px;
            border: 1px solid rgba(184,178,171,0.15);
            transition: all 0.3s ease;
        }

        .license-item:hover {
            background: rgba(255,255,255,0.5);
            transform: translateY(-3px);
        }

        .license-item label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }

        .license-item p {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .st-approved { background: #e8f5e0; color: #6a8a4a; border: 1px solid #c8dc98; }
        .st-pending { background: #fef5e8; color: #a8844a; border: 1px solid #e0d0b0; }
        .st-rejected { background: #fae8e8; color: #8a6a6a; border: 1px solid #e0c0c0; }
        .st-expired { background: #f1efef; color: #5f5a5a; border: 1px solid #d0cbcb; }

        .download-wrap {
            margin-top: 20px;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--button-bg);
            color: var(--text-primary);
            border: 2px solid rgba(184,178,171,0.3);
            padding: 11px 20px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            background: var(--hover-bg);
            color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(184,178,171,0.25);
        }

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

        @keyframes slideInTop {
            from { opacity: 0; transform: translateY(-40px) rotateX(10deg); }
            to { opacity: 1; transform: translateY(0) rotateX(0); }
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            50% { transform: translateY(-30px) rotate(180deg) scale(1.1); }
        }

        @media (max-width: 992px) {
            .dashboard-grid,
            .license-grid {
                grid-template-columns: 1fr 1fr;
            }

            .dashboard-header,
            .license-card {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
                padding: 20px;
            }

            .dashboard-grid,
            .license-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header,
            .license-card {
                grid-column: span 1;
                padding: 24px;
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
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/my_licenses.php'">
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

        <div class="main-content">
            <div class="floating-shape shape-1"></div>
            <div class="floating-shape shape-2"></div>
            <div class="floating-shape shape-3"></div>

            <div class="dashboard-grid" style="grid-column: span 4;">
                <div class="dashboard-header" style="grid-column: span 4;">
                    <div>
                        <h2>My License Details</h2>
                        <p>Track your license lifecycle and renewal status</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="license-card">
                    <div class="license-grid">
                        <div class="license-item">
                            <label>License Number</label>
                            <p><?php echo htmlspecialchars((string) $licenseNumber); ?></p>
                        </div>
                        <div class="license-item">
                            <label>Status</label>
                            <p>
                                <span class="status-pill st-<?php echo htmlspecialchars($status); ?>">
                                    <?php echo ucfirst(htmlspecialchars($status)); ?>
                                </span>
                            </p>
                        </div>
                        <div class="license-item">
                            <label>Issue Date</label>
                            <p><?php echo htmlspecialchars($issueDate); ?></p>
                        </div>
                        <div class="license-item">
                            <label>Expiry Date</label>
                            <p><?php echo htmlspecialchars($expiryDate); ?></p>
                        </div>
                        <div class="license-item">
                            <label>Renewal Status</label>
                            <p><?php echo htmlspecialchars($renewalStatus); ?></p>
                        </div>
                        <div class="license-item">
                            <label>Days Remaining</label>
                            <p><?php echo $daysRemaining === null ? 'N/A' : $daysRemaining; ?></p>
                        </div>
                    </div>

                    <?php if ($remarks !== ''): ?>
                        <div class="license-item" style="margin-top: 16px;">
                            <label>Remarks</label>
                            <p><?php echo htmlspecialchars($remarks); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="download-wrap">
                        <?php if ($status === 'approved' && !empty($current['license_number'])): ?>
                            <a class="download-btn" href="downloads.php">
                                <i class="fas fa-download"></i>
                                Download License
                            </a>
                        <?php else: ?>
                            <a class="download-btn" href="javascript:void(0)" onclick="alert('License download is available only after approval.');">
                                <i class="fas fa-download"></i>
                                Download License
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.license-card, .license-item').forEach(panel => {
            panel.addEventListener('mouseenter', () => {
                panel.style.transform = 'translateY(-3px)';
            });
            panel.addEventListener('mouseleave', () => {
                panel.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
