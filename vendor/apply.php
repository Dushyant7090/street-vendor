<?php
session_start();
include __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: /street_vendor/login.php");
    exit();
}

$name = $_SESSION['name'] ?? 'Vendor User';
$userId = $_SESSION['user_id'] ?? 0;

$vendorPhone = '';
$vendorStmt = $conn->prepare("SELECT phone FROM vendors WHERE user_id = ? LIMIT 1");
$vendorStmt->bind_param("i", $userId);
$vendorStmt->execute();
$vendorRow = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();
if ($vendorRow) {
    $vendorPhone = (string) ($vendorRow['phone'] ?? '');
}

$zones = [];
$zoneResult = $conn->query("SELECT id, zone_name FROM zones WHERE is_active = 1 ORDER BY zone_name ASC");
if ($zoneResult) {
    $zones = $zoneResult->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply License</title>
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
            animation: pageFade 0.75s ease;
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
            position: relative;
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

        .form-panel {
            grid-column: span 4;
            background: linear-gradient(160deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border-radius: 35px;
            padding: 35px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 14px 42px rgba(0,0,0,0.10), inset 0 1px 0 rgba(255,255,255,0.6);
            backdrop-filter: blur(15px);
            animation: slideInUp 1s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .form-title {
            margin: 0 0 24px 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 18px;
        }

        .field-block {
            background: #f5f3f0;
            border: 1px solid rgba(184,178,171,0.2);
            border-radius: 18px;
            padding: 14px;
            transition: all 0.3s ease;
        }

        .field-block:focus-within {
            border-color: rgba(184,178,171,0.45);
            box-shadow: 0 0 0 4px rgba(184,178,171,0.18);
            transform: translateY(-2px);
        }

        .field-block label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 700;
            color: #6f6862;
        }

        .field-block input,
        .field-block select,
        .field-block textarea {
            width: 100%;
            border: 1px solid rgba(184,178,171,0.25);
            background: #fffdfb;
            border-radius: 14px;
            padding: 10px 12px;
            color: var(--text-primary);
            font-size: 0.93rem;
            font-weight: 500;
            transition: all 0.25s ease;
        }

        .field-block textarea {
            min-height: 96px;
            resize: vertical;
        }

        .field-block input:focus,
        .field-block select:focus,
        .field-block textarea:focus {
            outline: none;
            border-color: rgba(184,178,171,0.5);
            box-shadow: 0 0 0 3px rgba(184,178,171,0.18);
        }

        .wide {
            grid-column: span 2;
        }

        .upload-box {
            border: 2px dashed rgba(184,178,171,0.45);
            border-radius: 18px;
            background: linear-gradient(135deg, #f8f6f3 0%, #efebe6 100%);
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-box:hover {
            background: linear-gradient(135deg, #f5f3f0 0%, #eae5df 100%);
            transform: translateY(-2px);
        }

        .upload-box i {
            font-size: 2rem;
            color: #8f8881;
            margin-bottom: 8px;
        }

        .submit-wrap {
            margin-top: 22px;
            display: flex;
            justify-content: flex-end;
        }

        .submit-btn {
            border: none;
            border-radius: 999px;
            padding: 12px 22px;
            font-weight: 800;
            font-size: 0.92rem;
            letter-spacing: 0.02em;
            color: #2f2b27;
            background: linear-gradient(145deg, #e3ddd6 0%, #d2cbc3 100%);
            border: 1px solid rgba(184,178,171,0.45);
            box-shadow: 0 8px 22px rgba(66, 57, 49, 0.16);
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(66, 57, 49, 0.2);
            background: linear-gradient(145deg, #d8d0c8 0%, #c8bfb6 100%);
        }

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

        @keyframes pageFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .wide {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .main-content {
                margin-left: 60px;
                padding: 20px;
            }
            .dashboard-header,
            .form-panel {
                padding: 24px;
            }
            .submit-wrap {
                justify-content: stretch;
            }
            .submit-btn {
                width: 100%;
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
            <div class="sidebar-item" onclick="window.location.href='/street_vendor/vendor/my_licenses.php'">
                <i class="fas fa-id-card"></i>
                <span>Licenses</span>
            </div>
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/apply.php'">
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
                        <h2>License Application Workspace</h2>
                        <p>Submit your vendor license request with complete details</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="form-panel">
                    <h3 class="form-title">Vendor License Application Form</h3>

                    <form action="/street_vendor/vendor/apply_license.php" method="POST" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="field-block">
                                <label>Vendor Name</label>
                                <input type="text" name="vendor_name" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>

                            <div class="field-block">
                                <label>Stall Type</label>
                                <select name="stall_type" required>
                                    <option value="">Select Stall Type</option>
                                    <option value="Food Stall">Food Stall</option>
                                    <option value="Vegetable Stall">Vegetable Stall</option>
                                    <option value="Fruit Stall">Fruit Stall</option>
                                    <option value="General Stall">General Stall</option>
                                    <option value="Mobile Stall">Mobile Stall</option>
                                </select>
                            </div>

                            <div class="field-block">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" value="<?php echo htmlspecialchars($vendorPhone); ?>" required>
                            </div>

                            <div class="field-block">
                                <label>Aadhaar / ID Number</label>
                                <input type="text" name="id_number" placeholder="Enter Aadhaar or ID number" required>
                            </div>

                            <div class="field-block">
                                <label>Zone Preference</label>
                                <select name="zone_preference" required>
                                    <option value="">Select Preferred Zone</option>
                                    <?php foreach ($zones as $zone): ?>
                                        <option value="<?php echo htmlspecialchars((string) $zone['id']); ?>">
                                            <?php echo htmlspecialchars((string) $zone['zone_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field-block">
                                <label>Stall Category</label>
                                <select name="stall_category" required>
                                    <option value="">Select Category</option>
                                    <option value="Temporary">Temporary</option>
                                    <option value="Permanent">Permanent</option>
                                    <option value="Seasonal">Seasonal</option>
                                </select>
                            </div>

                            <div class="field-block">
                                <label>Business Type</label>
                                <input type="text" name="business_type" placeholder="e.g. Tea, Snacks, Produce" required>
                            </div>

                            <div class="field-block wide">
                                <label>Address</label>
                                <textarea name="address" placeholder="Enter full business address" required></textarea>
                            </div>

                            <div class="field-block wide">
                                <label>Upload Required Documents</label>
                                <div class="upload-box">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p style="margin: 0 0 10px; color: #6f6862; font-weight: 600;">Upload Aadhaar, photo, and supporting files</p>
                                    <input type="file" name="documents[]" multiple>
                                </div>
                            </div>
                        </div>

                        <div class="submit-wrap">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-paper-plane" style="margin-right:8px;"></i>
                                Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
