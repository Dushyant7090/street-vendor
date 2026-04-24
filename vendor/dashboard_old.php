<?php
session_start();
include __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../index.php");
    exit();
}

$name = $_SESSION['name'] ?? 'Vendor User';
$userId = $_SESSION['user_id'] ?? 0;

// Get vendor details
$vendorQuery = $conn->prepare("SELECT id, phone FROM vendors WHERE user_id = ?");
$vendorQuery->bind_param("i", $userId);
$vendorQuery->execute();
$vendorResult = $vendorQuery->get_result();
$vendor = $vendorResult->fetch_assoc();
$vendorId = $vendor['id'] ?? 0;
$phone = $vendor['phone'] ?? 'N/A';

// Get zone
$zoneQuery = $conn->prepare("SELECT z.zone_name FROM locations l JOIN zones z ON l.zone_id = z.id WHERE l.vendor_id = ? LIMIT 1");
$zoneQuery->bind_param("i", $vendorId);
$zoneQuery->execute();
$zoneResult = $zoneQuery->get_result();
$zoneName = $zoneResult->fetch_assoc()['zone_name'] ?? 'Not Assigned';

// Stats
$activeLicensesQuery = $conn->prepare("SELECT COUNT(*) AS count FROM licenses WHERE vendor_id = ? AND status='approved'");
$activeLicensesQuery->bind_param("i", $vendorId);
$activeLicensesQuery->execute();
$activeLicenses = $activeLicensesQuery->get_result()->fetch_assoc()['count'] ?? 0;

$registeredLocationsQuery = $conn->prepare("SELECT COUNT(*) AS count FROM locations WHERE vendor_id = ?");
$registeredLocationsQuery->bind_param("i", $vendorId);
$registeredLocationsQuery->execute();
$registeredLocations = $registeredLocationsQuery->get_result()->fetch_assoc()['count'] ?? 0;

$pendingApplicationsQuery = $conn->prepare("SELECT COUNT(*) AS count FROM licenses WHERE vendor_id = ? AND status='pending'");
$pendingApplicationsQuery->bind_param("i", $vendorId);
$pendingApplicationsQuery->execute();
$pendingApplications = $pendingApplicationsQuery->get_result()->fetch_assoc()['count'] ?? 0;

$downloads = $activeLicenses; // Assuming downloads equal to active licenses

// License progress
$progressQuery = $conn->prepare("SELECT expiry_date FROM licenses WHERE vendor_id = ? AND status='approved' ORDER BY expiry_date DESC LIMIT 1");
$progressQuery->bind_param("i", $vendorId);
$progressQuery->execute();
$progressResult = $progressQuery->get_result();
$percentage = 0;
$remainingDays = 0;
$expiryDate = 'N/A';
if ($row = $progressResult->fetch_assoc()) {
    $expiry = new DateTime($row['expiry_date']);
    $now = new DateTime();
    $totalDays = 365; // Assume 1 year validity
    $remainingDays = $expiry > $now ? $expiry->diff($now)->days : 0;
    $percentage = min(100, ($remainingDays / $totalDays) * 100);
    $expiryDate = $expiry->format('M d, Y');
}

// Latest status
$statusQuery = $conn->prepare("SELECT status FROM licenses WHERE vendor_id = ? ORDER BY id DESC LIMIT 1");
$statusQuery->bind_param("i", $vendorId);
$statusQuery->execute();
$statusResult = $statusQuery->get_result();
$latestStatus = $statusResult->fetch_assoc()['status'] ?? 'none';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard</title>
    <!-- Luxury Theme CSS -->
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f9f0dc, #f2e4c8, #e6d2b5);
            --accent-gold: #c79a3b;
            --soft-yellow: #e0b85c;
            --dark-text: #2f2f2f;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.18);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
            margin: 0;
            padding: 0;
        }

        .navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 10px 20px;
        }

        .navbar-nav .nav-link {
            color: var(--dark-text) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 20px;
            padding: 8px 16px;
        }

        .navbar-nav .nav-link:hover {
            background: var(--accent-gold);
            color: white !important;
            transform: translateY(-2px);
        }

        .hero-section {
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 40px;
            text-align: center;
            animation: fadeIn 1s ease-in;
        }

        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--accent-gold);
            margin-bottom: 10px;
        }

        .hero-section p {
            font-size: 1.2rem;
            color: var(--dark-text);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            animation: fadeIn 1s ease-in;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card i {
            font-size: 3rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-text);
            margin: 0;
        }

        .stat-card p {
            color: var(--soft-yellow);
            font-weight: 500;
            margin-top: 5px;
        }

        .main-cards {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin: 20px;
        }

        .main-card {
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 30px;
            animation: fadeIn 1s ease-in;
        }

        .profile-card h4 {
            color: var(--accent-gold);
            margin-bottom: 20px;
        }

        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .progress-card {
            text-align: center;
        }

        .progress-ring {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }

        .progress-ring circle {
            fill: none;
            stroke-width: 8;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        .progress-ring .bg {
            stroke: #e0e0e0;
        }

        .progress-ring .fg {
            stroke: var(--accent-gold);
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 1s ease-in-out;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark-text);
        }

        .status-tracker {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .status-step {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-step i {
            font-size: 1.5rem;
            color: var(--accent-gold);
        }

        .status-step.completed i {
            color: #28a745;
        }

        .status-step.pending i {
            color: #6c757d;
        }

        .timeline {
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
            animation: fadeIn 1s ease-in;
        }

        .timeline h4 {
            color: var(--accent-gold);
            margin-bottom: 20px;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-item i {
            color: var(--accent-gold);
            font-size: 1.2rem;
        }

        .timeline-item .content {
            flex: 1;
        }

        .timeline-item .date {
            color: var(--soft-yellow);
            font-size: 0.9rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .main-cards {
                grid-template-columns: 1fr;
            }
            .hero-section h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#" style="color: var(--accent-gold); font-weight: bold;">Vendor Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/street_vendor/vendor/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/street_vendor/vendor/my_location.php">My Locations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/street_vendor/vendor/my_licenses.php">My Licenses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/street_vendor/vendor/apply_license.php">Apply License</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="downloads.php">Download License</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-section">
        <h1>Welcome Back, <?php echo htmlspecialchars($name); ?></h1>
        <p>Manage your licenses, locations, applications, and profile in one place</p>
    </div>

    <div class="stats-cards">
        <div class="stat-card">
            <i class="fas fa-certificate"></i>
            <h3><?php echo $activeLicenses; ?></h3>
            <p>Active Licenses</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-map-marker-alt"></i>
            <h3><?php echo $registeredLocations; ?></h3>
            <p>Registered Locations</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <h3><?php echo $pendingApplications; ?></h3>
            <p>Pending Applications</p>
        </div>
        <div class="stat-card">
            <i class="fas fa-download"></i>
            <h3><?php echo $downloads; ?></h3>
            <p>Downloads</p>
        </div>
    </div>

    <div class="main-cards">
        <div class="main-card profile-card">
            <h4>Vendor Profile</h4>
            <div class="avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></p>
            <p><strong>Shop Type:</strong> Street Vendor</p>
            <p><strong>Zone:</strong> <?php echo htmlspecialchars($zoneName); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
            <p><strong>Verification:</strong> <span style="color: #28a745;">Verified</span></p>
            <button class="btn" onclick="window.location.href='profile.php'">View Profile</button>

        <div class="main-card progress-card">
            <h4>License Validity</h4>
            <div class="progress-ring">
                <svg width="120" height="120">
                    <circle class="bg" cx="60" cy="60" r="45"></circle>
                    <circle class="fg" cx="60" cy="60" r="45" style="stroke-dashoffset: <?php echo 283 - (283 * $percentage / 100); ?>;"></circle>
                </svg>
                <div class="progress-text"><?php echo round($percentage); ?>%</div>
            </div>
            <p><strong>Days Remaining:</strong> <?php echo $remainingDays; ?></p>
            <p><strong>Expiry Date:</strong> <?php echo $expiryDate; ?></p>
            <?php if ($remainingDays < 30): ?>
                <p style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Renewal Alert</p>
            <?php endif; ?>
        </div>

        <div class="main-card">
            <h4>Application Status</h4>
            <div class="status-tracker">
                <div class="status-step <?php echo in_array($latestStatus, ['pending', 'approved', 'expired']) ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Submitted</span>
                </div>
                <div class="status-step <?php echo in_array($latestStatus, ['approved', 'expired']) ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-clock"></i>
                    <span>Under Review</span>
                </div>
                <div class="status-step <?php echo $latestStatus === 'approved' ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-thumbs-up"></i>
                    <span>Approved</span>
                </div>
                <div class="status-step <?php echo $latestStatus === 'approved' ? 'completed' : 'pending'; ?>">
                    <i class="fas fa-download"></i>
                    <span>Download Ready</span>
                </div>
            </div>
        </div>
    </div>

    <div class="timeline">
        <h4>Recent Activities</h4>
        <div class="timeline-item">
            <i class="fas fa-file-alt"></i>
            <div class="content">
                <p>License application submitted</p>
                <span class="date">2 days ago</span>
            </div>
        </div>
        <div class="timeline-item">
            <i class="fas fa-upload"></i>
            <div class="content">
                <p>Document uploaded</p>
                <span class="date">1 week ago</span>
            </div>
        </div>
        <div class="timeline-item">
            <i class="fas fa-map-marker-alt"></i>
            <div class="content">
                <p>Location updated</p>
                <span class="date">2 weeks ago</span>
            </div>
        </div>
        <div class="timeline-item">
            <i class="fas fa-download"></i>
            <div class="content">
                <p>License downloaded</p>
                <span class="date">1 month ago</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add hover animations
        document.querySelectorAll('.stat-card, .main-card, .timeline-item').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'scale(1.02)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>