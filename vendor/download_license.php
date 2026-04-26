<?php
require_once __DIR__ . '/../includes/workflow_helpers.php';
requireVendor();

$userId = (int) $_SESSION['user_id'];
$licenseId = (int) ($_GET['id'] ?? 0);
if ($licenseId <= 0) {
    redirect('/street_vendor/vendor/my_licenses.php');
}

$stmt = $conn->prepare("
    SELECT l.*,
           COALESCE(l.business_type, l.license_type, l.business_name) AS display_business_type,
           u.name,
           u.email,
           v.phone,
           v.address,
           z.zone_name,
           loc.spot_number,
           loc.latitude,
           loc.longitude
    FROM licenses l
    JOIN vendors v ON l.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    LEFT JOIN zones z ON z.id = l.zone_id
    LEFT JOIN locations loc ON loc.application_id = l.id AND loc.is_active = 1
    WHERE l.id = ? AND v.user_id = ? AND l.status = 'approved'
    LIMIT 1
");
$stmt->bind_param('ii', $licenseId, $userId);
$stmt->execute();
$license = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$license) {
    redirect('/street_vendor/vendor/my_licenses.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License - <?php echo htmlspecialchars($license['license_number']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 24px; color: #172033; }
        .license-doc { max-width: 850px; margin: 0 auto; background: #fff; border: 2px solid #1f7a4d; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 45px rgba(15, 23, 42, .12); }
        .header { background: #1f7a4d; color: #fff; padding: 28px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 6px 0 0; opacity: .9; }
        .body { padding: 30px; }
        .license-number { text-align: center; font-size: 26px; font-weight: 800; color: #1f7a4d; margin-bottom: 18px; letter-spacing: 1px; }
        .status { display: inline-block; padding: 7px 16px; border-radius: 999px; background: #dcfce7; color: #166534; font-weight: 800; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 24px; margin-top: 26px; }
        .item label { display: block; color: #667085; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; }
        .item span { font-weight: 700; color: #172033; }
        .footer { background: #f8fafc; color: #667085; padding: 18px 30px; font-size: 13px; text-align: center; }
        .print-btn { display: block; margin: 20px auto 0; border: 0; border-radius: 8px; background: #1f7a4d; color: #fff; padding: 12px 22px; font-weight: 800; cursor: pointer; }
        @media print {
            body { background: #fff; padding: 0; }
            .print-btn { display: none; }
            .license-doc { box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="license-doc">
        <div class="header">
            <h1>Smart Street Vendor License</h1>
            <p>License & Location Management System</p>
        </div>
        <div class="body">
            <div class="license-number"><?php echo htmlspecialchars($license['license_number']); ?></div>
            <div style="text-align:center"><span class="status">APPROVED / ACTIVE</span></div>
            <div class="grid">
                <div class="item"><label>Vendor Name</label><span><?php echo htmlspecialchars($license['name']); ?></span></div>
                <div class="item"><label>Email</label><span><?php echo htmlspecialchars($license['email']); ?></span></div>
                <div class="item"><label>Phone</label><span><?php echo htmlspecialchars($license['phone'] ?? 'N/A'); ?></span></div>
                <div class="item"><label>Business Type</label><span><?php echo htmlspecialchars($license['display_business_type'] ?? 'N/A'); ?></span></div>
                <div class="item"><label>Vendor Category</label><span><?php echo htmlspecialchars($license['vendor_category'] ?? 'N/A'); ?></span></div>
                <div class="item"><label>Priority Type</label><span><?php echo htmlspecialchars($license['priority_type'] ?? 'N/A'); ?></span></div>
                <div class="item"><label>Assigned Zone</label><span><?php echo htmlspecialchars($license['zone_name'] ?? 'N/A'); ?></span></div>
                <div class="item"><label>Spot / Coordinates</label><span><?php echo htmlspecialchars(($license['spot_number'] ?: 'N/A') . ' / ' . ($license['latitude'] ?: '-') . ', ' . ($license['longitude'] ?: '-')); ?></span></div>
                <div class="item"><label>Issue Date</label><span><?php echo $license['issue_date'] ? date('d M Y', strtotime($license['issue_date'])) : 'N/A'; ?></span></div>
                <div class="item"><label>Expiry Date</label><span><?php echo $license['expiry_date'] ? date('d M Y', strtotime($license['expiry_date'])) : 'N/A'; ?></span></div>
                <div class="item"><label>Status</label><span><?php echo ucfirst((string) $license['status']); ?></span></div>
                <div class="item"><label>Generated On</label><span><?php echo date('d M Y, h:i A'); ?></span></div>
            </div>
        </div>
        <div class="footer">This digitally generated license is valid with the assigned zone and spot details shown above.</div>
    </div>
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
</body>
</html>
