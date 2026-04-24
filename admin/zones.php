<?php
session_start();
require_once __DIR__ . '/../config/database.php';

/* ---------- ADMIN CHECK ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /street_vendor/login.php");
    exit();
}

/* ---------- HANDLE ADD ZONE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_zone'])) {
    $zoneName = trim($_POST['zone_name']);
    $areaDesc = trim($_POST['area_description']);
    $maxVendors = intval($_POST['max_vendors']);

    if (!empty($zoneName)) {
        $stmt = $conn->prepare("INSERT INTO zones (zone_name, area_description, max_vendors, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("ssi", $zoneName, $areaDesc, $maxVendors);
        $stmt->execute();
        $stmt->close();

        header("Location: zones.php");
        exit();
    }
}

/* ---------- TOGGLE STATUS ---------- */
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE zones SET is_active = NOT is_active WHERE id=$id");

    header("Location: zones.php");
    exit();
}

/* ---------- FETCH ZONES ---------- */
$zones = $conn->query("
    SELECT z.*,
    (SELECT COUNT(*) FROM locations WHERE zone_id=z.id AND is_active=1) as occupied_spots
    FROM zones z
    ORDER BY z.id DESC
")->fetch_all(MYSQLI_ASSOC);

/* ---------- STATS ---------- */
$totalZones = count($zones);
$activeZones = 0;
$inactiveZones = 0;
$totalCapacity = 0;

foreach ($zones as $z) {
    if ($z['is_active']) $activeZones++;
    else $inactiveZones++;

    $totalCapacity += $z['max_vendors'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advanced Zones Management</title>

<!-- Luxury Theme CSS -->
<link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    color:white;
    padding:30px;
}

.container{
    max-width:1400px;
    margin:auto;
}

.header{
    margin-bottom:30px;
}

.header h1{
    font-size:32px;
    font-weight:700;
}

.header p{
    color:#94a3b8;
}

/* STAT CARDS */
.stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-bottom:30px;
}

.card{
    background:rgba(255,255,255,0.05);
    padding:25px;
    border-radius:18px;
    backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,0.08);
}

.card h3{
    color:#94a3b8;
    font-size:14px;
    margin-bottom:10px;
}

.card h2{
    font-size:32px;
}

/* MAIN GRID */
.grid{
    display:grid;
    grid-template-columns:1fr 2fr;
    gap:25px;
}

/* FORM */
.form-box{
    background:rgba(255,255,255,0.05);
    padding:25px;
    border-radius:18px;
}

.form-box h2{
    margin-bottom:20px;
}

input, textarea{
    width:100%;
    padding:14px;
    margin-bottom:15px;
    border:none;
    border-radius:12px;
    background:#1e293b;
    color:white;
}

button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:12px;
    background:#06b6d4;
    color:white;
    font-weight:600;
    cursor:pointer;
}

button:hover{
    background:#0891b2;
}

/* TABLE */
.table-box{
    background:rgba(255,255,255,0.05);
    padding:25px;
    border-radius:18px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:16px;
    text-align:left;
}

th{
    color:#94a3b8;
    font-size:14px;
}

tr{
    border-bottom:1px solid rgba(255,255,255,0.06);
}

.badge{
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.active{
    background:#22c55e;
}

.inactive{
    background:#ef4444;
}

.toggle-btn{
    padding:8px 14px;
    border-radius:10px;
    text-decoration:none;
    color:white;
    font-size:13px;
}

.on{
    background:#f59e0b;
}

.off{
    background:#22c55e;
}

/* PROGRESS BAR */
.progress{
    width:100%;
    height:8px;
    background:#1e293b;
    border-radius:10px;
    overflow:hidden;
    margin-top:8px;
}

.progress-fill{
    height:100%;
    background:#06b6d4;
}
body {
    background: url('/street_vendor/assets/img/gov_vendor_bg_india.png') no-repeat center center fixed !important;
    background-size: cover !important;
}
</style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>Zones Management</h1>
        <p>Advanced Admin Control Panel for vending zones</p>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="card">
            <h3>Total Zones</h3>
            <h2><?php echo $totalZones; ?></h2>
        </div>

        <div class="card">
            <h3>Active Zones</h3>
            <h2><?php echo $activeZones; ?></h2>
        </div>

        <div class="card">
            <h3>Inactive Zones</h3>
            <h2><?php echo $inactiveZones; ?></h2>
        </div>

        <div class="card">
            <h3>Total Capacity</h3>
            <h2><?php echo $totalCapacity; ?></h2>
        </div>
    </div>

    <div class="grid">

        <!-- ADD ZONE FORM -->
        <div class="form-box">
            <h2>Add New Zone</h2>

            <form method="POST">
                <input type="text" name="zone_name" placeholder="Zone Name" required>

                <textarea name="area_description" rows="5" placeholder="Area Description"></textarea>

                <input type="number" name="max_vendors" value="10" min="1">

                <button type="submit" name="add_zone">Create Zone</button>
            </form>
        </div>

        <!-- ZONE TABLE -->
        <div class="table-box">
            <h2 style="margin-bottom:20px;">All Zones</h2>

            <table>
                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Capacity</th>
                        <th>Occupancy</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($zones as $z): ?>
                    <?php 
                        $percent = ($z['occupied_spots'] / $z['max_vendors']) * 100;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($z['zone_name']); ?></strong><br>
                            <small style="color:#94a3b8;">
                                <?php echo htmlspecialchars($z['area_description']); ?>
                            </small>
                        </td>

                        <td>
                            <?php echo $z['occupied_spots']; ?> / <?php echo $z['max_vendors']; ?>
                        </td>

                        <td>
                            <div class="progress">
                                <div class="progress-fill" style="width:<?php echo $percent; ?>%"></div>
                            </div>
                        </td>

                        <td>
                            <span class="badge <?php echo $z['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $z['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>

                        <td>
                            <a class="toggle-btn <?php echo $z['is_active'] ? 'on' : 'off'; ?>"
                               href="?toggle=<?php echo $z['id']; ?>">
                                <?php echo $z['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div>
</div>

</body>
</html>