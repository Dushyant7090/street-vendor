<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor' || empty($_SESSION['user_id'])) {
    header('Location: /street_vendor/login.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_details') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $idProofType = trim($_POST['id_proof_type'] ?? 'Aadhar Card');
        $idProofNumber = trim($_POST['id_proof_number'] ?? '');

        if ($name === '' || $phone === '') {
            $message = 'Name and phone are required.';
            $messageType = 'danger';
        } else {
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $userId);
            $stmt->execute();
            $stmt->close();

            // Update vendors table
            $stmt = $conn->prepare("UPDATE vendors SET name = ?, phone = ?, address = ?, id_proof_type = ?, id_proof_number = ? WHERE user_id = ?");
            $stmt->bind_param("sssssi", $name, $phone, $address, $idProofType, $idProofNumber, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['name'] = $name;
            $message = 'Profile updated successfully.';
            $messageType = 'success';
        }
    }

    if ($action === 'upload_photo' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'vendor_' . $userId . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../uploads/photos/' . $filename;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0777, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $path = 'uploads/photos/' . $filename;
                $stmt = $conn->prepare("UPDATE vendors SET photo = ? WHERE user_id = ?");
                $stmt->bind_param("si", $path, $userId);
                $stmt->execute();
                $stmt->close();
                $message = 'Photo uploaded successfully.';
                $messageType = 'success';
            }
        } else {
            $message = 'Only JPG, PNG, WEBP files allowed.';
            $messageType = 'danger';
        }
    }
}

// Fetch profile
$stmt = $conn->prepare("SELECT u.id AS user_id, u.name, u.email, u.created_at, v.id AS vendor_id, v.phone, v.address, v.id_proof_type, v.id_proof_number, v.photo FROM users u LEFT JOIN vendors v ON v.user_id = u.id WHERE u.id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) { header('Location: /street_vendor/login.php'); exit(); }

$photoUrl = '/street_vendor/assets/img/default-avatar.png';
if (!empty($profile['photo'])) {
    $photoUrl = '/street_vendor/' . ltrim(str_replace('\\', '/', $profile['photo']), '/');
}

$pageTitle = 'My Profile';
$vendorPage = true;
include __DIR__ . '/../includes/header.php';
?>

<div id="vendor-wrapper">
    <aside id="vendor-sidebar" class="py-4">
        <div class="px-4 mb-4">
            <h4 style="color:hsl(var(--primary))" class="fw-bold mb-0">StreetVendor</h4>
            <small class="text-muted-foreground">Vendor Portal</small>
        </div>
        <nav class="d-flex flex-column mt-3">
            <a href="/street_vendor/vendor/dashboard.php" class="vendor-sidebar-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="/street_vendor/vendor/profile.php" class="vendor-sidebar-link active"><i class='bx bxs-user-detail'></i> My Profile</a>
            <a href="/street_vendor/vendor/apply_license.php" class="vendor-sidebar-link"><i class='bx bxs-file-plus'></i> Apply for License</a>
            <a href="/street_vendor/vendor/my_licenses.php" class="vendor-sidebar-link"><i class='bx bxs-id-card'></i> My Licenses</a>
            <a href="/street_vendor/vendor/location.php" class="vendor-sidebar-link"><i class='bx bxs-map'></i> My Location</a>
            <div class="mt-auto px-3 pt-4">
                <a href="/street_vendor/auth/logout.php" class="vendor-sidebar-link text-danger"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </nav>
    </aside>

    <div id="vendor-page-content">
        <header class="vendor-top-navbar">
            <h5 class="mb-0 fw-semibold text-foreground">My Profile</h5>
            <div class="d-flex align-items-center gap-3">
                <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-circle"><i class='bx bx-moon'></i></button>
                <span class="fw-semibold text-foreground"><i class='bx bxs-user-circle'></i> <?php echo htmlspecialchars($profile['name'] ?? 'Vendor'); ?></span>
            </div>
        </header>

        <main class="container-fluid p-4">
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Left: Profile Card + Photo -->
                <div class="col-lg-4">
                    <div class="card bg-card border-border shadow-sm text-center mb-4">
                        <div class="card-body p-4">
                            <div class="mx-auto mb-3" style="width:100px;height:100px;border-radius:50%;overflow:hidden;border:3px solid hsl(var(--primary))">
                                <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover">
                            </div>
                            <h5 class="fw-bold text-foreground"><?php echo htmlspecialchars($profile['name'] ?? 'Vendor'); ?></h5>
                            <p class="text-muted-foreground mb-1 small"><?php echo htmlspecialchars($profile['email'] ?? ''); ?></p>
                            <span class="badge bg-primary">VND-<?php echo str_pad($profile['vendor_id'] ?? $userId, 4, '0', STR_PAD_LEFT); ?></span>

                            <form method="POST" enctype="multipart/form-data" class="mt-3">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="form-control form-control-sm mb-2">
                                <button type="submit" class="btn btn-sm btn-outline-secondary border-border w-100">Upload Photo</button>
                            </form>
                        </div>
                    </div>

                    <div class="card bg-card border-border shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold text-foreground mb-3">Account Details</h6>
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                    <span class="text-muted-foreground">Member Since</span>
                                    <span class="fw-semibold"><?php echo date('M d, Y', strtotime($profile['created_at'])); ?></span>
                                </li>
                                <li class="list-group-item bg-transparent border-border d-flex justify-content-between">
                                    <span class="text-muted-foreground">Vendor ID</span>
                                    <span class="fw-semibold">#<?php echo $profile['vendor_id'] ?? 'N/A'; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Right: Edit Form -->
                <div class="col-lg-8">
                    <div class="card bg-card border-border shadow-sm">
                        <div class="card-body">
                            <h5 class="fw-bold text-foreground mb-4">Edit Profile</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_details">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted-foreground">Full Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted-foreground">Email (Read-only)</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted-foreground">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted-foreground">ID Proof Type</label>
                                        <select name="id_proof_type" class="form-select">
                                            <?php $types = ['Aadhar Card','PAN Card','Voter ID','Driving License','Passport'];
                                            foreach($types as $t): ?>
                                            <option value="<?php echo $t; ?>" <?php echo ($profile['id_proof_type'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted-foreground">ID Proof Number</label>
                                        <input type="text" name="id_proof_number" class="form-control" value="<?php echo htmlspecialchars($profile['id_proof_number'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted-foreground">Address</label>
                                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="submit" class="btn btn-primary px-4 fw-semibold">Save Changes</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="/street_vendor/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('theme-toggle').addEventListener('click', () => {
    const html = document.documentElement;
    const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', next);
    localStorage.setItem('vendorTheme', next);
    document.querySelector('#theme-toggle i').className = next === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
});
if(document.documentElement.getAttribute('data-bs-theme') === 'dark') {
    document.querySelector('#theme-toggle i').className = 'bx bx-sun';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
