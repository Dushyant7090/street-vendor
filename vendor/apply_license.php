<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor' || empty($_SESSION['user_id'])) {
    redirect('/street_vendor/login.php');
}

$userId = (int) $_SESSION['user_id'];
$sessionName = trim((string) ($_SESSION['name'] ?? 'Vendor User'));
$sessionEmail = trim((string) ($_SESSION['user_email'] ?? ''));
$today = date('Y-m-d');
$errors = [];

function cleanInput(string $value): string
{
    return trim(strip_tags($value));
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$values): void
{
    $bindArgs = [$types];

    foreach ($values as $index => &$value) {
        $bindArgs[] = &$value;
    }

    if (!call_user_func_array([$stmt, 'bind_param'], $bindArgs)) {
        throw new RuntimeException('Could not bind statement parameters.');
    }
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$vendorHasBusinessName = columnExists($conn, 'vendors', 'business_name');
$vendorHasBusinessLocation = columnExists($conn, 'vendors', 'business_location');

$vendorSelectColumns = ['id', 'phone', 'address'];
if ($vendorHasBusinessName) {
    $vendorSelectColumns[] = 'business_name';
}
if ($vendorHasBusinessLocation) {
    $vendorSelectColumns[] = 'business_location';
}

$vendorQuery = $conn->prepare('SELECT ' . implode(', ', $vendorSelectColumns) . ' FROM vendors WHERE user_id = ? LIMIT 1');
if (!$vendorQuery) {
    die('Could not load vendor profile.');
}
$vendorQuery->bind_param('i', $userId);
$vendorQuery->execute();
$vendor = $vendorQuery->get_result()->fetch_assoc() ?: [];
$vendorQuery->close();

$formData = [
    'name' => $sessionName,
    'business_name' => trim((string) ($vendor['business_name'] ?? $sessionName)),
    'phone' => trim((string) ($vendor['phone'] ?? '')),
    'email' => $sessionEmail,
    'license_type' => '',
    'business_location' => trim((string) ($vendor['business_location'] ?? '')),
    'address' => trim((string) ($vendor['address'] ?? '')),
    'description' => '',
    'apply_date' => $today,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name'] = cleanInput((string) ($_POST['name'] ?? ''));
    $formData['business_name'] = cleanInput((string) ($_POST['business_name'] ?? ''));
    $formData['phone'] = cleanInput((string) ($_POST['phone'] ?? ''));
    $formData['email'] = cleanInput((string) ($_POST['email'] ?? ''));
    $formData['license_type'] = cleanInput((string) ($_POST['license_type'] ?? ''));
    $formData['business_location'] = cleanInput((string) ($_POST['business_location'] ?? ''));
    $formData['address'] = cleanInput((string) ($_POST['address'] ?? ''));
    $formData['description'] = cleanInput((string) ($_POST['description'] ?? ''));
    $formData['apply_date'] = cleanInput((string) ($_POST['apply_date'] ?? $today));

    foreach (['name', 'business_name', 'phone', 'email', 'license_type', 'business_location', 'address', 'description', 'apply_date'] as $requiredField) {
        if ($formData[$requiredField] === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $requiredField)) . ' is required.';
        }
    }

    if ($formData['phone'] !== '' && !preg_match('/^[0-9]{10,15}$/', $formData['phone'])) {
        $errors[] = 'Phone number must contain 10 to 15 digits.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $formData['apply_date']);
    $dateIsValid = $dateObject instanceof DateTime && $dateObject->format('Y-m-d') === $formData['apply_date'];
    if (!$dateIsValid) {
        $errors[] = 'Apply date must be a valid date.';
    }

    if (empty($errors)) {
        // Demo mode: keep validation/UI flow but do not store vendor-side data.
        $_SESSION['name'] = $formData['name'];
        $_SESSION['user_email'] = $formData['email'];
        setFlash('success', 'Demo Mode: Data not saved');
        redirect('/street_vendor/vendor/dashboard.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for License</title>
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
            animation: pageFadeIn 0.7s ease;
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
            border-radius: 14px;
            margin: 5px 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
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
            gap: 26px;
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
        .shape-2 { width: 95px; height: 95px; top: 48%; right: 8%; animation-delay: 2s; }
        .shape-3 { width: 80px; height: 80px; bottom: 20%; left: 15%; animation-delay: 4s; }

        .form-panel {
            grid-column: span 4;
            background: linear-gradient(155deg, rgba(244,241,237,0.97) 0%, rgba(240,237,233,0.99) 100%);
            border-radius: 35px;
            padding: 36px;
            border: 1.5px solid rgba(184,178,171,0.2);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.5);
            animation: slideInUp 1s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(15px);
            transition: all 0.4s ease;
        }

        .form-panel:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 52px rgba(0,0,0,0.12), inset 0 1px 0 rgba(255,255,255,0.65);
        }

        .form-title {
            margin: 0 0 20px;
            color: var(--text-primary);
            font-size: 1.45rem;
            font-weight: 800;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 18px;
        }

        .field-block {
            background: rgba(255,255,255,0.35);
            border: 1px solid rgba(184,178,171,0.15);
            border-radius: 16px;
            padding: 18px;
            transition: all 0.3s ease;
        }

        .field-block:focus-within {
            border-color: rgba(184,178,171,0.3);
            box-shadow: 0 0 0 3px rgba(184,178,171,0.2);
            background: rgba(255,255,255,0.5);
            transform: translateY(-3px);
        }

        .field-block label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .field-block input,
        .field-block select,
        .field-block textarea {
            width: 100%;
            border: 1px solid rgba(184,178,171,0.2);
            border-radius: 13px;
            padding: 10px 12px;
            color: var(--text-primary);
            font-size: 0.92rem;
            font-weight: 600;
            background: rgba(255,255,255,0.6);
            transition: all 0.24s ease;
        }

        .field-block input:focus,
        .field-block select:focus,
        .field-block textarea:focus {
            outline: none;
            border-color: rgba(184,178,171,0.35);
            box-shadow: 0 0 0 3px rgba(184,178,171,0.2);
            background: rgba(255,255,255,0.85);
        }

        .field-block textarea {
            min-height: 95px;
            resize: vertical;
        }

        .field-block option {
            color: #3a3834;
            background: #f5f2ee;
            font-style: normal;
        }

        .wide {
            grid-column: span 2;
        }

        .message-box {
            border-radius: 16px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-weight: 700;
            border: 1px solid;
        }

        .message-error {
            color: #8c4a4a;
            background: #fdf0ef;
            border-color: #efc5c2;
        }

        .message-success {
            color: #2d7041;
            background: #ecf8ef;
            border-color: #bfe2cb;
        }

        .submit-wrap {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-premium {
            border: 1px solid rgba(200, 125, 58, 0.66);
            border-radius: 999px;
            padding: 12px 22px;
            font-weight: 800;
            font-size: 0.9rem;
            color: #fff0df;
            background: linear-gradient(145deg, #c48243 0%, #b06f33 60%, #905725 100%);
            box-shadow: 0 10px 24px rgba(13, 15, 19, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 30px rgba(12, 14, 18, 0.58);
            background: linear-gradient(145deg, #d09153 0%, #bb7c40 60%, #9d642e 100%);
            color: #fff4e7;
        }

        .success-modal {
            position: fixed;
            inset: 0;
            background: rgba(20, 24, 30, 0.72);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(3px);
        }

        .success-modal.show {
            display: flex;
            animation: pageFadeIn 0.3s ease;
        }

        .success-content {
            width: min(480px, 92%);
            border-radius: 24px;
            border: 1px solid rgba(200, 125, 58, 0.52);
            background: linear-gradient(145deg, rgba(46, 54, 64, 0.97), rgba(62, 72, 84, 0.97));
            box-shadow: 0 18px 45px rgba(9, 11, 14, 0.68);
            padding: 26px;
            text-align: center;
        }

        .success-content i {
            font-size: 2.6rem;
            color: #d69a63;
            margin-bottom: 8px;
            animation: iconPulse 1.2s infinite ease-in-out;
        }

        .success-content h4 {
            margin: 0 0 8px;
            color: var(--text-primary);
            font-size: 1.4rem;
            font-weight: 800;
        }

        .success-content p {
            margin: 0;
            color: var(--text-secondary);
            font-weight: 600;
        }

        @keyframes slideInTop {
            from { opacity: 0; transform: translateY(-38px) rotateX(10deg); }
            to { opacity: 1; transform: translateY(0) rotateX(0); }
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(36px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            50% { transform: translateY(-28px) rotate(180deg) scale(1.08); }
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        @keyframes pageFadeIn {
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
            .sidebar {
                width: 60px;
            }

            .main-content {
                margin-left: 60px;
                padding: 20px;
            }

            .dashboard-header,
            .form-panel {
                padding: 22px;
            }

            .submit-wrap {
                justify-content: stretch;
                flex-direction: column;
            }

            .btn-premium {
                width: 100%;
                text-align: center;
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
            <div class="sidebar-item active" onclick="window.location.href='/street_vendor/vendor/apply_license.php'">
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
                        <h2>Apply for License</h2>
                        <p>Fill in the details below to submit your license request</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="form-panel">
                    <?php if (!empty($errors)): ?>
                        <div class="message-box message-error">
                            <?php echo htmlspecialchars(implode(' ', $errors)); ?>
                        </div>
                    <?php endif; ?>

                    <h3 class="form-title">Premium License Application Form</h3>

                    <form id="licenseForm" action="" method="POST" novalidate>
                        <div class="form-grid">
                            <div class="field-block">
                                <label>Full Name</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                            </div>

                            <div class="field-block">
                                <label>Business Name</label>
                                <input type="text" name="business_name" value="<?php echo htmlspecialchars($formData['business_name']); ?>" required>
                            </div>

                            <div class="field-block">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>" required>
                            </div>

                            <div class="field-block">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>

                            <div class="field-block">
                                <label>License Type</label>
                                <select name="license_type" required>
                                    <option value="">Select License Type</option>
                                    <option value="Food License" <?php echo $formData['license_type'] === 'Food License' ? 'selected' : ''; ?>>Food License</option>
                                    <option value="Trade License" <?php echo $formData['license_type'] === 'Trade License' ? 'selected' : ''; ?>>Trade License</option>
                                    <option value="Shop License" <?php echo $formData['license_type'] === 'Shop License' ? 'selected' : ''; ?>>Shop License</option>
                                    <option value="Vendor License" <?php echo $formData['license_type'] === 'Vendor License' ? 'selected' : ''; ?>>Vendor License</option>
                                </select>
                            </div>

                            <div class="field-block">
                                <label>Business Location</label>
                                <input type="text" name="business_location" value="<?php echo htmlspecialchars($formData['business_location']); ?>" required>
                            </div>

                            <div class="field-block wide">
                                <label>Address</label>
                                <textarea name="address" required><?php echo htmlspecialchars($formData['address']); ?></textarea>
                            </div>

                            <div class="field-block wide">
                                <label>Description / Notes</label>
                                <textarea name="description" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
                            </div>

                            <div class="field-block">
                                <label>Apply Date</label>
                                <input type="date" name="apply_date" value="<?php echo htmlspecialchars($formData['apply_date']); ?>" required>
                            </div>
                        </div>

                        <div class="submit-wrap">
                            <button type="submit" class="btn-premium">
                                <i class="fas fa-paper-plane"></i>
                                Submit Application
                            </button>
                            <button type="reset" class="btn-premium">
                                <i class="fas fa-rotate-left"></i>
                                Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const form = document.getElementById('licenseForm');

            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                const fullName = form.querySelector('[name="name"]').value.trim();
                const businessName = form.querySelector('[name="business_name"]').value.trim();
                const phone = form.querySelector('[name="phone"]').value.trim();
                const email = form.querySelector('[name="email"]').value.trim();
                const licenseType = form.querySelector('[name="license_type"]').value;
                const businessLocation = form.querySelector('[name="business_location"]').value.trim();
                const address = form.querySelector('[name="address"]').value.trim();
                const description = form.querySelector('[name="description"]').value.trim();
                const applyDate = form.querySelector('[name="apply_date"]').value;

                const phoneOk = /^[0-9]{10,15}$/.test(phone);
                const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

                if (!fullName || !businessName || !phoneOk || !emailOk || !licenseType || !businessLocation || !address || !description || !applyDate) {
                    event.preventDefault();
                    alert('Please complete all required fields with valid details.');
                }
            });
        })();
    </script>
</body>
</html>
