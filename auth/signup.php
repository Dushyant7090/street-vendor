<?php
/**
 * Signup Page
 * Handles new vendor registration with profile details.
 */
require_once __DIR__ . '/../config/database.php';

setFlash('error', 'Demo Mode: Registration is disabled.');
redirect('/street_vendor/login.php');

// If already logged in, redirect
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('/street_vendor/admin/dashboard.php');
    } else {
        redirect('/street_vendor/vendor/dashboard.php');
    }
}

// Handle Signup Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $id_proof_type = trim($_POST['id_proof_type'] ?? 'Aadhar Card');
    $id_proof_number = trim($_POST['id_proof_number'] ?? '');

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($address)) $errors[] = 'Address is required.';
    if (empty($id_proof_number)) $errors[] = 'ID proof number is required.';

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email is already registered.';
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Begin transaction
        $conn->begin_transaction();
        try {
            // 1. Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'vendor')");
            $stmt->bind_param("sss", $name, $email, $hashedPassword);
            $stmt->execute();
            $userId = $conn->insert_id;
            $stmt->close();

            // 2. Insert into vendors table
            $stmt = $conn->prepare("INSERT INTO vendors (user_id, phone, address, id_proof_type, id_proof_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $userId, $phone, $address, $id_proof_type, $id_proof_number);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            setFlash('success', 'Account created successfully! Please login.');
            redirect('/street_vendor/auth/login.php');

        } catch (Exception $e) {
            $conn->rollback();
            setFlash('error', 'Registration failed. Please try again.');
        }
    }
}

$pageTitle = 'Create Account';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 520px;">
        <!-- Logo / Brand -->
        <div class="logo">
            <div class="icon">🏪</div>
            <h1>Create Account</h1>
            <p>Register as a Street Vendor</p>
        </div>

        <!-- Flash Messages -->
        <?php include __DIR__ . '/../includes/flash.php'; ?>

        <!-- Signup Form -->
        <form method="POST" action="" data-validate>
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your full name" required
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Min 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" placeholder="Enter phone number" required
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="id_proof_type">ID Proof Type</label>
                    <select id="id_proof_type" name="id_proof_type">
                        <option value="Aadhar Card" <?php echo (($_POST['id_proof_type'] ?? '') === 'Aadhar Card') ? 'selected' : ''; ?>>Aadhar Card</option>
                        <option value="PAN Card" <?php echo (($_POST['id_proof_type'] ?? '') === 'PAN Card') ? 'selected' : ''; ?>>PAN Card</option>
                        <option value="Voter ID" <?php echo (($_POST['id_proof_type'] ?? '') === 'Voter ID') ? 'selected' : ''; ?>>Voter ID</option>
                        <option value="Driving License" <?php echo (($_POST['id_proof_type'] ?? '') === 'Driving License') ? 'selected' : ''; ?>>Driving License</option>
                        <option value="Passport" <?php echo (($_POST['id_proof_type'] ?? '') === 'Passport') ? 'selected' : ''; ?>>Passport</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="id_proof_number">ID Proof Number</label>
                <input type="text" id="id_proof_number" name="id_proof_number" placeholder="Enter your ID proof number" required
                       value="<?php echo htmlspecialchars($_POST['id_proof_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" placeholder="Enter your full address" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class='bx bx-user-plus'></i> Create Account
            </button>
        </form>

        <!-- Footer Link -->
        <div class="auth-footer">
            Already have an account? <a href="/street_vendor/auth/login.php">Sign In</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
