<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: /street_vendor/login.php");
    exit();
}
/**
 * Download License as PDF
 * Generates a simple HTML-based printable license document.
 * Uses browser print-to-PDF since we're avoiding external libraries.
 */
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$licenseId = intval($_GET['id'] ?? 0);

if (!$licenseId) {
    header('Location: /street_vendor/vendor/my_licenses.php');
    exit();
}

// Get license with vendor and user details
$stmt = $conn->prepare("
    SELECT l.*, v.phone, v.address, v.id_proof_type, v.id_proof_number, u.name, u.email
    FROM licenses l 
    JOIN vendors v ON l.vendor_id = v.id 
    JOIN users u ON v.user_id = u.id
    WHERE l.id = ? AND v.user_id = ? AND l.status = 'approved'
");
$stmt->bind_param("ii", $licenseId, $userId);
$stmt->execute();
$license = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$license) {
    header('Location: /street_vendor/vendor/my_licenses.php');
    exit();
}

// Get location if assigned
$stmt = $conn->prepare("SELECT l.spot_number, z.zone_name FROM locations l 
    JOIN zones z ON l.zone_id = z.id 
    JOIN vendors v ON l.vendor_id = v.id
    WHERE v.user_id = ? AND l.is_active = 1 LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License - <?php echo $license['license_number']; ?></title>
    <!-- Luxury Theme CSS -->
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; padding: 20px; }
        .license-doc {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 3px solid #6c5ce7;
            border-radius: 12px;
            overflow: hidden;
        }
        .license-header {
            background: linear-gradient(135deg, #6c5ce7, #4834d4);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .license-header h1 { font-size: 1.6rem; font-weight: 800; }
        .license-header p { font-size: 0.9rem; opacity: 0.85; margin-top: 5px; }
        .license-body { padding: 30px; }
        .license-number-display {
            text-align: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: #6c5ce7;
            margin: 15px 0;
            letter-spacing: 2px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 25px 0;
        }
        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #636e72;
            display: block;
            margin-bottom: 4px;
        }
        .info-item span {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3436;
        }
        .divider {
            border: none;
            border-top: 1px dashed #e9ecef;
            margin: 20px 0;
        }
        .license-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            font-size: 0.8rem;
            color: #636e72;
        }
        .status-approved {
            display: inline-block;
            background: rgba(0,184,148,0.15);
            color: #00b894;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .print-btn {
            display: block;
            margin: 20px auto;
            padding: 12px 30px;
            background: #6c5ce7;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        .print-btn:hover { background: #4834d4; }
        @media print {
            body { background: #fff; padding: 0; }
            .print-btn { display: none; }
            .license-doc { border: 2px solid #333; }
        }
        body {
            background: url('/street_vendor/assets/img/gov_vendor_bg_india.png') no-repeat center center fixed !important;
            background-size: cover !important;
        }
    </style>
</head>
<body>
    <div class="license-doc">
        <div class="license-header">
            <h1>🏪 Street Vendor License</h1>
            <p>Municipal Corporation — License & Location Management System</p>
        </div>
        <div class="license-body">
            <div class="license-number-display"><?php echo htmlspecialchars($license['license_number']); ?></div>
            <div class="text-center" style="text-align:center;">
                <span class="status-approved">✅ APPROVED</span>
            </div>

            <hr class="divider">

            <div class="info-grid">
                <div class="info-item">
                    <label>Vendor Name</label>
                    <span><?php echo htmlspecialchars($license['name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($license['email']); ?></span>
                </div>
                <div class="info-item">
                    <label>Phone</label>
                    <span><?php echo htmlspecialchars($license['phone']); ?></span>
                </div>
                <div class="info-item">
                    <label>Address</label>
                    <span><?php echo htmlspecialchars($license['address']); ?></span>
                </div>
                <div class="info-item">
                    <label><?php echo htmlspecialchars($license['id_proof_type']); ?></label>
                    <span><?php echo htmlspecialchars($license['id_proof_number']); ?></span>
                </div>
                <div class="info-item">
                    <label>Issued On</label>
                    <span><?php echo date('d M Y', strtotime($license['issue_date'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Valid Until</label>
                    <span><?php echo date('d M Y', strtotime($license['expiry_date'])); ?></span>
                </div>
                <?php if ($location): ?>
                <div class="info-item">
                    <label>Allocated Location</label>
                    <span><?php echo htmlspecialchars($location['zone_name'] . ' — Spot ' . $location['spot_number']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="license-footer">
            <p>This is a digitally generated license. Valid as per municipal regulations.</p>
            <p>Generated on: <?php echo date('d M Y, h:i A'); ?></p>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
</body>
</html>
