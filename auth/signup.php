<?php
require_once __DIR__ . '/../config/database.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? '/street_vendor/admin/dashboard.php' : '/street_vendor/vendor/dashboard.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            $errors[] = 'Email is already registered.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'vendor')");
            $stmt->bind_param('ssss', $name, $email, $phone, $hash);
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();

            $stmt = $conn->prepare('INSERT INTO vendors (user_id, name, email, phone) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $userId, $name, $email, $phone);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = true;
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = 'Registration failed. Please check if the phone or email is already used.';
        }
    }
}

$pageTitle = 'Create Vendor Account';
include __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --font-main: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        --color-sapphire: #0e1e3b;
        --color-emerald: #10b981;
        --color-purple: #4c1d95;
        --color-neon: #34d399;
        --color-charcoal: #111827;
        --text-main: #1f2937;
        --text-muted: #6b7280;
    }

    html, body {
        width: 100%;
        min-height: 100%;
        height: 100%;
        margin: 0;
        overflow-x: hidden;
        background: transparent !important;
        color: var(--text-main);
        font-family: var(--font-main);
    }

    .signup-hero-section {
        position: fixed;
        inset: 0;
        overflow: hidden;
        z-index: 0;
    }

    .signup-bg-image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -3;
        user-select: none;
        pointer-events: none;
        animation: signupBreatheBg 30s infinite alternate ease-in-out;
    }

    @keyframes signupBreatheBg {
        0% { transform: scale(1); }
        100% { transform: scale(1.04); }
    }

    .signup-bg-gradient-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(18, 58, 99, 0.10), rgba(43, 179, 166, 0.04));
        z-index: -1;
        pointer-events: none;
    }

    .signup-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(100px);
        opacity: 0.6;
        z-index: -1;
        pointer-events: none;
        animation: signupMapBlobs 25s infinite alternate ease-in-out;
        background: var(--color-emerald);
    }

    .signup-blob-1 { width: 500px; height: 500px; bottom: -150px; left: -100px; }
    .signup-blob-2 { width: 600px; height: 600px; background: var(--color-purple); top: -250px; right: 0; animation-delay: -7s; }

    @keyframes signupMapBlobs {
        0% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(80px, -60px) scale(1.1); }
        100% { transform: translate(-40px, 80px) scale(0.9); }
    }

    .signup-auth-section {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 28px 16px;
        z-index: 10;
    }

    .signup-auth-card {
        width: 100%;
        max-width: 500px;
        padding: 2.25rem;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 24px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 0 40px rgba(16, 185, 129, 0.04);
        position: relative;
    }

    .signup-logo-row {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .signup-logo-box {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--color-charcoal), var(--color-sapphire));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 26px;
        margin-bottom: 1rem;
        box-shadow: 0 12px 20px rgba(15, 23, 42, 0.2);
        position: relative;
    }

    .signup-logo-box::after {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 18px;
        background: linear-gradient(135deg, var(--color-neon), transparent);
        z-index: -1;
        opacity: 0.5;
    }

    .signup-brand {
        font-weight: 800;
        font-size: 1.05rem;
        color: var(--color-charcoal);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .signup-subtitle {
        font-size: 0.75rem;
        color: var(--color-emerald);
        font-weight: 700;
        text-transform: uppercase;
    }

    .signup-alert {
        padding: 12px 16px;
        border-radius: 12px;
        margin-bottom: 1.25rem;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .signup-alert.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    .signup-alert.success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
    }

    .signup-field {
        position: relative;
        margin-bottom: 1rem;
    }

    .signup-field input {
        width: 100%;
        height: 56px;
        padding: 22px 16px 8px 48px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-main);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .signup-field input:focus {
        outline: none;
        border-color: var(--color-emerald);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
    }

    .signup-field i {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 20px;
        color: #94a3b8;
        pointer-events: none;
    }

    .signup-field label {
        position: absolute;
        left: 48px;
        top: 18px;
        color: #94a3b8;
        font-size: 0.95rem;
        font-weight: 500;
        pointer-events: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .signup-field input:focus ~ label,
    .signup-field input:not(:placeholder-shown) ~ label {
        top: 8px;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--color-emerald);
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .signup-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .signup-submit {
        width: 100%;
        height: 56px;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--color-sapphire) 0%, var(--color-purple) 50%, var(--color-emerald) 100%);
        background-size: 200% auto;
        color: white;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 10px 20px -5px rgba(76, 29, 149, 0.3), inset 0 1px 1px rgba(255,255,255,0.2);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        margin-top: 0.5rem;
    }

    .signup-submit:hover {
        background-position: right center;
        box-shadow: 0 15px 30px -5px rgba(16, 185, 129, 0.4), inset 0 1px 1px rgba(255,255,255,0.3);
        transform: translateY(-3px);
    }

    .signup-footer {
        text-align: center;
        margin-top: 1.25rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #64748b;
    }

    .signup-footer a,
    .signup-alert a {
        color: var(--color-emerald);
        text-decoration: none;
        font-weight: 800;
    }

    @media (max-width: 560px) {
        .signup-auth-card { padding: 1.5rem; }
        .signup-form-row { grid-template-columns: 1fr; gap: 0; }
    }
</style>

<div class="signup-hero-section">
    <img src="/street_vendor/assets/img/gov_vendor_bg_india.png" alt="Indian Street Market Scene" class="signup-bg-image">
    <div class="signup-bg-gradient-overlay"></div>
    <div class="signup-blob signup-blob-1"></div>
    <div class="signup-blob signup-blob-2"></div>
</div>

<div class="signup-auth-section">
    <div class="signup-auth-card">
        <div class="signup-logo-row">
            <div class="signup-logo-box"><i class='bx bxs-buildings'></i></div>
            <div class="signup-brand">Street Vendor</div>
            <div class="signup-subtitle">License & Location System</div>
        </div>

        <?php if ($success): ?>
            <div class="signup-alert success">
                Account created successfully. <a href="/street_vendor/login.php">Login now</a>.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="signup-alert error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="signup-field">
                <input type="text" id="name" name="name" placeholder=" " required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                <i class='bx bxs-user'></i>
                <label for="name">Full Name</label>
            </div>
            <div class="signup-field">
                <input type="email" id="email" name="email" placeholder=" " required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                <i class='bx bxs-envelope'></i>
                <label for="email">Email Address</label>
            </div>
            <div class="signup-field">
                <input type="text" id="phone" name="phone" placeholder=" " required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                <i class='bx bxs-phone'></i>
                <label for="phone">Phone Number</label>
            </div>
            <div class="signup-form-row">
                <div class="signup-field">
                    <input type="password" id="password" name="password" placeholder=" " required>
                    <i class='bx bxs-lock-alt'></i>
                    <label for="password">Password</label>
                </div>
                <div class="signup-field">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required>
                    <i class='bx bxs-shield'></i>
                    <label for="confirm_password">Confirm Password</label>
                </div>
            </div>
            <button type="submit" class="signup-submit">
                Create Account <i class='bx bx-right-arrow-alt'></i>
            </button>
        </form>

        <div class="signup-footer">
            Already registered? <a href="/street_vendor/login.php">Sign in</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
