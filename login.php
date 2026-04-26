<?php
session_start();
require_once("config.php");

$error = '';
$role = $_POST['role'] ?? 'vendor'; // Default to vendor (User)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($login_id) && !empty($password) && !empty($role)) {
        
        $is_email = (strpos($login_id, '@') !== false);
        $field = $is_email ? 'email' : 'username';

        // Secure authentication block
        $stmt = $conn->prepare("SELECT * FROM users WHERE $field=? AND role=?");
        if ($stmt) {
            $stmt->bind_param("ss", $login_id, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_email'] = $user['email'];

                    if ($user['role'] === "admin") {
                        header("Location: /street_vendor/admin/dashboard.php");
                        exit();
                    } else {
                        // Vendor/User redirect
                        header("Location: /street_vendor/vendor/dashboard.php");
                        exit();
                    }
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "Government ID or Username not found.";
            }
            $stmt->close();
        } else {
            $error = "System error. Please try again later.";
        }
    } else {
        $error = "Please provide all required credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Management Portal - Secure Login</title>
    <!-- Luxury Theme CSS -->
    <link rel="stylesheet" href="/street_vendor/assets/css/theme.css">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --font-main: 'Inter', -apple-system, sans-serif;
            --color-sapphire: #0e1e3b; 
            --color-emerald: #10b981;  
            --color-purple: #4c1d95;   
            --color-neon: #34d399;     
            --color-charcoal: #111827;
            --text-main: #1f2937;
            --text-muted: #6b7280;
        }

        html, body { width: 100%; min-height: 100%; height: 100%; margin: 0; padding: 0; overflow-x: hidden; background: transparent !important; color: var(--text-main); -webkit-font-smoothing: antialiased; }

        /* ===== BACKGROUND: Full-screen Visual Hero ===== */
        .hero-section {
            position: fixed;
            inset: 0;
            background: transparent !important;
            overflow: hidden;
            z-index: 0;
        }

        .bg-image {
            position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 1; z-index: -3; user-select: none; pointer-events: none;
            animation: breatheBg 30s infinite alternate ease-in-out;
        }

        @keyframes breatheBg { 0% { transform: scale(1); } 100% { transform: scale(1.04); } }

        .bg-gradient-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(18, 58, 99, 0.10), rgba(43, 179, 166, 0.04));
            z-index: -1; pointer-events: none;
        }

        .blob {
            position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.6; z-index: -1; pointer-events: none;
            animation: mapBlobs 25s infinite alternate ease-in-out; background: var(--color-emerald);
        }
        .blob-1 { width: 500px; height: 500px; bottom: -150px; left: -100px; }
        .blob-2 { width: 600px; height: 600px; background: var(--color-purple); top: -250px; right: 0; animation-delay: -7s; }

        @keyframes mapBlobs { 0% { transform: translate(0, 0) scale(1); } 50% { transform: translate(80px, -60px) scale(1.1); } 100% { transform: translate(-40px, 80px) scale(0.9); } }

        /* CSS Particles */
        #particles-container { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
        .particle {
            position: absolute; width: 12px; height: 12px; background: linear-gradient(45deg, #00f0ff, #a800ff); border-radius: 50%; filter: blur(10px); opacity: 0.4; animation: float 12s infinite alternate;
        }
        @keyframes float { 0% { transform: translateY(0) translateX(0); } 50% { transform: translateY(-50px) translateX(30px); } 100% { transform: translateY(0) translateX(0); } }

        /* ===== LOGIN PANEL ===== */
        .auth-section {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; max-width: 520px; display: flex; align-items: center; justify-content: center; z-index: 10;
        }



        .auth-card {
            width: 100%; max-width: 460px; padding: 2.5rem; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-radius: 24px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.12),
                0 0 40px rgba(16, 185, 129, 0.04);
            position: relative; z-index: 10;
        }

        .logo-row { display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 1.5rem; }
        .logo-box {
            width: 56px; height: 56px; background: linear-gradient(135deg, var(--color-charcoal), var(--color-sapphire)); border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 26px; margin-bottom: 1rem; box-shadow: 0 12px 20px rgba(15, 23, 42, 0.2); position: relative;
        }
        .logo-box::after { content: ''; position: absolute; inset: -2px; border-radius: 18px; background: linear-gradient(135deg, var(--color-neon), transparent); z-index: -1; opacity: 0.5; }

        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; font-weight: 600;
        }

        /* Pill-style Role Tabs (Admin / User Only) */
        .role-tabs {
            display: flex; background: #f1f5f9; border-radius: 100px; padding: 5px; margin-bottom: 1.5rem; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;
        }
        .role-radio { display: none; }
        .role-label {
            flex: 1; text-align: center; padding: 11px 0; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-radius: 100px; z-index: 2; transition: color 0.3s;
        }
        .role-label.active { color: #ffffff; }
        
        .role-pill-bg {
            position: absolute; top: 5px; bottom: 5px; left: 5px; width: calc(50% - 5px); background: linear-gradient(135deg, var(--color-charcoal), var(--color-sapphire)); border-radius: 100px; z-index: 1; transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1.2); box-shadow: 0 4px 10px rgba(15,23,42,0.2);
        }

        #roleAdmin:checked ~ .role-pill-bg { transform: translateX(0); }
        #roleUser:checked ~ .role-pill-bg { transform: translateX(100%); }

        /* Floating Label Inputs */
        .input-group {
            position: relative;
            display: block;
            margin-bottom: 1.25rem;
        }
        
        .form-control {
            width: 100%;
            height: 64px;
            padding: 26px 48px 10px 54px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            font-size: 0.98rem;
            line-height: 1.2;
            font-weight: 600;
            color: var(--text-main);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.01);
            box-sizing: border-box;
        }
        .form-control:focus { outline: none; border-color: var(--color-emerald); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15), inset 0 2px 4px rgba(0,0,0,0.01); }
        
        .input-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 21px; color: #94a3b8; transition: color 0.3s; pointer-events: none; z-index: 2; }
        .form-control:focus ~ .input-icon { color: var(--color-emerald); }

        .floating-label {
            position: absolute;
            left: 54px;
            top: 21px;
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1;
            font-weight: 600;
            pointer-events: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }
        .form-control:focus ~ .floating-label, .form-control:not(:placeholder-shown) ~ .floating-label {
            top: 10px; font-size: 0.68rem; font-weight: 800; color: var(--color-emerald); letter-spacing: 0.04em; text-transform: uppercase;
        }

        .btn-eye { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 21px; color: #94a3b8; cursor: pointer; transition: color 0.2s; z-index: 3; }
        .btn-eye:hover { color: var(--color-charcoal); }

        /* Options row */
        .options-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        
        .modern-switch { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
        .switch-track {
            width: 38px; height: 22px; background: #cbd5e1; border-radius: 20px; position: relative; transition: 0.3s; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); border: 1px solid transparent;
        }
        .switch-thumb {
            position: absolute; width: 16px; height: 16px; background: white; border-radius: 50%; top: 3px; left: 3px; transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1.2); box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        #rememberAuth { display: none; }
        #rememberAuth:checked + .switch-track { background: var(--color-emerald); border-color: var(--color-emerald); }
        #rememberAuth:checked + .switch-track .switch-thumb { transform: translateX(16px); }

        .forgot-pass { color: var(--color-purple); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: color 0.2s; }
        .forgot-pass:hover { color: var(--color-emerald); text-decoration: underline; }

        /* Primary Button */
        .btn-submit {
            width: 100%; height: 56px; border: none; border-radius: 14px;
            background: linear-gradient(135deg, var(--color-sapphire) 0%, var(--color-purple) 50%, var(--color-emerald) 100%);
            background-size: 200% auto;
            color: white; font-size: 1.05rem; font-weight: 700; letter-spacing: 0.02em;
            cursor: pointer; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 10px 20px -5px rgba(76, 29, 149, 0.3), inset 0 1px 1px rgba(255,255,255,0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-submit:hover { background-position: right center; box-shadow: 0 15px 30px -5px rgba(16, 185, 129, 0.4), inset 0 1px 1px rgba(255,255,255,0.3); transform: translateY(-3px); }
        .btn-submit:active { transform: translateY(1px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }

        .ink { position: absolute; background: rgba(255,255,255,0.4); border-radius: 50%; transform: scale(0); animation: ripple 0.6s linear; pointer-events: none; }
        @keyframes ripple { to { transform: scale(4); opacity: 0; } }

        /* Separator & Social */
        .social-separator { display: flex; align-items: center; gap: 16px; margin: 1.5rem 0; }
        .social-separator::before, .social-separator::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .social-separator span { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }

        .social-auth { display: flex; justify-content: center; align-items: center; }
        .btn-sso {
            height: 48px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; font-size: 0.9rem; color: var(--text-main); cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.01);
        }
        .btn-sso:hover { background: #f8fafc; border-color: #94a3b8; box-shadow: 0 4px 6px rgba(0,0,0,0.03); transform: translateY(-1px); }

        @media (max-width: 1000px) {
            .hero-section { position: fixed; inset: 0; }
            .auth-section { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%; max-width: 520px; display: flex; align-items: center; justify-content: center; z-index: 10; }
        }
    </style>
</head>
<body>
    
    <!-- Visual Hero Background -->
    <div class="hero-section">
        <img src="./assets/img/gov_vendor_bg_india.png" alt="Indian Street Market Scene" class="bg-image">
        <div class="bg-gradient-overlay"></div>
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div id="particles-container"></div>
    </div>

    <!-- Authentication Form -->
    <div class="auth-section">



            <div class="auth-card">
                <div class="logo-row">
                    <div class="logo-box">
                        <i class="ph-fill ph-buildings"></i>
                    </div>
                    <div style="font-weight: 800; font-size: 1.05rem; color: var(--color-charcoal); text-transform: uppercase; letter-spacing: 0.05em;">Street Vendor</div>
                    <div style="font-size: 0.75rem; color: var(--color-emerald); font-weight: 700; text-transform: uppercase;">License & Location System</div>
                </div>

                <?php if($error): ?>
                    <div class="alert-error">
                        <i class="ph-fill ph-warning-circle" style="font-size: 1.3rem;"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Role Pill Selector -->
                    <div class="role-tabs">
                        <input type="radio" name="role" id="roleAdmin" value="admin" class="role-radio" <?= ($role === 'admin')?'checked':'' ?>>
                        <input type="radio" name="role" id="roleUser" value="vendor" class="role-radio" <?= ($role === 'vendor')?'checked':'' ?>>
                        
                        <label for="roleAdmin" class="role-label <?= ($role === 'admin')?'active':'' ?>" onclick="updateRoleUI(this)">Admin</label>
                        <label for="roleUser" class="role-label <?= ($role === 'vendor')?'active':'' ?>" onclick="updateRoleUI(this)">User</label>
                        
                        <div class="role-pill-bg"></div>
                    </div>

                    <!-- Email / Username Input -->
                    <div class="input-group">
                        <input type="text" name="login_id" id="loginId" class="form-control" placeholder=" " value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>" required>
                        <i class="ph-fill ph-user input-icon"></i>
                        <label for="loginId" class="floating-label">Email / Username</label>
                    </div>

                    <!-- Password Input -->
                    <div class="input-group">
                        <input type="password" name="password" id="loginPass" class="form-control" placeholder=" " required>
                        <i class="ph-fill ph-shield-check input-icon"></i>
                        <label for="loginPass" class="floating-label">Password</label>
                        <i class="ph ph-eye btn-eye" id="togglePswd"></i>
                    </div>

                    <div class="options-row">
                        <label class="modern-switch" for="rememberAuth">
                            <input type="checkbox" id="rememberAuth" name="remember">
                            <div class="switch-track">
                                <div class="switch-thumb"></div>
                            </div>
                            Remember me
                        </label>
                        <a href="forgot_password.php" class="forgot-pass">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        Secure Login <i class="ph-bold ph-arrow-right"></i>
                    </button>

                    <div class="social-separator">
                        <span>Or continue with</span>
                    </div>

                    <div class="social-auth">
                        <button type="button" class="btn-sso">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" width="18" alt="G"> Google
                        </button>
                    </div>
                    <div style="text-align:center;margin-top:1.25rem;font-size:0.9rem;font-weight:600;color:#64748b;">
                        New vendor? <a href="/street_vendor/auth/signup.php" style="color:#10b981;text-decoration:none;">Create account</a>
                    </div>
                </form>
            </div>
        </div>

    <!-- Scripts -->
    <script>
        // Password Visibility Toggle
        const togglePswd = document.getElementById('togglePswd');
        const loginPass = document.getElementById('loginPass');

        togglePswd.addEventListener('click', () => {
            const isPassword = loginPass.getAttribute('type') === 'password';
            loginPass.setAttribute('type', isPassword ? 'text' : 'password');
            togglePswd.classList.toggle('ph-eye');
            togglePswd.classList.toggle('ph-eye-closed');
            togglePswd.style.color = isPassword ? '#10b981' : '#94a3b8';
        });

        // Pill Tab interaction
        function updateRoleUI(clickedLabel) {
            document.querySelectorAll('.role-label').forEach(lbl => lbl.classList.remove('active'));
            clickedLabel.classList.add('active');
        }

        // Ripple Animation on button click
        document.getElementById('submitBtn').addEventListener('click', function(e) {
            let ink = document.createElement('span');
            ink.classList.add('ink');
            let rect = this.getBoundingClientRect();
            let d = Math.max(rect.width, rect.height);
            let x = e.clientX - rect.left - d/2;
            let y = e.clientY - rect.top - d/2;
            
            ink.style.width = ink.style.height = d + 'px';
            ink.style.left = x + 'px';
            ink.style.top = y + 'px';
            
            this.appendChild(ink);
            setTimeout(() => ink.remove(), 600);
        });

        // Generate CSS Particles solely inside Left Panel
        const particlesContainer = document.getElementById('particles-container');
        const numParticles = 40;
        
        for(let i = 0; i < numParticles; i++) {
            let particle = document.createElement('div');
            particle.classList.add('particle');
            
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            
            let size = Math.random() * 12 + 6;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particle.style.animationDelay = (Math.random() * -15) + 's';
            
            particlesContainer.appendChild(particle);
        }
    </script>
</body>
</html>
