<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === 'admin@gmail.com' && $password === '123456') {
        $_SESSION['role'] = 'admin';
        $_SESSION['name'] = 'Super Admin';
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Access Denied: Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --acc-blue: #4a90e2;
            --acc-cyan: #00e5ff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Manrope', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f0f5ff, #d0e8ff, #ffecf2);
            background-size: 300% 300%;
            animation: smoothGradient 15s ease-in-out infinite alternate;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        @keyframes smoothGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Abstract Particles */
        .bg-fx {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0; pointer-events: none; overflow: hidden;
        }

        .blob {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.5;
            animation: blobFlow 20s infinite alternate ease-in-out;
        }

        .blob-1 { width: 500px; height: 500px; background: rgba(74, 144, 226, 0.4); top: 10%; left: 10%; }
        .blob-2 { width: 600px; height: 600px; background: rgba(0, 229, 255, 0.3); bottom: -10%; right: 10%; animation-delay: -5s; }

        @keyframes blobFlow {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(40px, 40px) scale(1.1); }
            100% { transform: translate(-30px, -40px) scale(0.9); }
        }

        .light-streak {
            position: absolute; background: rgba(255, 255, 255, 0.7); filter: blur(40px); border-radius: 50%;
            animation: streakSweep 15s linear infinite; width: 120vw; height: 100px;
        }
        .streak-1 { top: -50%; left: -50%; transform: rotate(35deg); }
        .streak-2 { bottom: -50%; right: -50%; animation-delay: 7s; transform: rotate(35deg); }

        @keyframes streakSweep {
            0% { transform: translate(-100vw, -100vh) rotate(35deg); }
            100% { transform: translate(100vw, 100vh) rotate(35deg); }
        }

        /* Login Card */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 28px;
            padding: 50px 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08), inset 0 2px 0 rgba(255,255,255,0.8);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            text-align: center;
        }

        .glass-card:hover { transform: translateY(-5px); box-shadow: 0 25px 45px rgba(0, 0, 0, 0.12); }

        .logo-box {
            width: 60px; height: 60px; margin: 0 auto 24px;
            background: linear-gradient(135deg, var(--acc-blue), var(--acc-cyan));
            border-radius: 18px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 25px rgba(0, 229, 255, 0.4);
        }
        .logo-box svg { color: white; width: 30px; }

        .header h1 { font-size: 26px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; letter-spacing: -0.5px; }
        .header p { font-size: 15px; color: var(--text-muted); margin-bottom: 36px; font-weight: 500; }

        /* Inputs */
        .form-group { margin-bottom: 20px; position: relative; text-align: left; }
        .input-wrapper { position: relative; z-index: 1; }
        
        .input-wrapper::before {
            content: ''; position: absolute; inset: -2px;
            background: linear-gradient(90deg, var(--acc-blue), var(--acc-cyan));
            border-radius: 16px; z-index: -1; opacity: 0; transition: 0.3s;
        }

        .input-wrapper input {
            width: 100%; padding: 16px 16px 16px 52px; background: rgba(255, 255, 255, 0.8);
            border: 2px solid transparent; border-radius: 14px; font-size: 15px; font-weight: 600;
            color: var(--text-dark); outline: none; transition: 0.3s; box-shadow: inset 0 2px 6px rgba(0,0,0,0.02);
        }

        .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: 0.3s; z-index: 2; }

        .input-wrapper:focus-within::before { opacity: 1; filter: blur(3px); }
        .input-wrapper:focus-within input { background: #ffffff; transform: translateY(-1px); }
        .input-wrapper:focus-within .input-icon { color: var(--acc-blue); }

        /* Button */
        .btn-primary {
            width: 100%; padding: 16px; margin-top: 10px;
            background: linear-gradient(135deg, var(--acc-blue), var(--acc-cyan));
            color: white; border: none; border-radius: 14px; font-size: 16px; font-weight: 800;
            cursor: pointer; box-shadow: 0 10px 25px rgba(0, 229, 255, 0.3); transition: 0.3s; position: relative; overflow: hidden;
        }
        .btn-primary:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 15px 35px rgba(0, 229, 255, 0.5); }

        .ripple {
            position: absolute; background: rgba(255,255,255,0.6); border-radius: 50%;
            transform: translate(-50%, -50%); pointer-events: none; animation: rippleEffect 0.6s ease-out;
        }
        @keyframes rippleEffect { 0% { width: 0; height: 0; opacity: 1; } 100% { width: 500px; height: 500px; opacity: 0; } }

        .alert {
            background: rgba(255, 64, 129, 0.1); border: 1px solid rgba(255, 64, 129, 0.2); color: #d11f4d;
            border-radius: 12px; padding: 14px; font-size: 14px; font-weight: 600; margin-bottom: 24px;
        }
    </style>
</head>
<body>

    <div class="bg-fx">
        <div class="light-streak streak-1"></div>
        <div class="light-streak streak-2"></div>
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="login-wrapper">
        <div class="glass-card">
            <div class="header">
                <div class="logo-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"></polygon>
                    </svg>
                </div>
                <h1>Admin Login</h1>
                <p>Secure portal access.</p>
            </div>

            <?php if (!empty($error)) : ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <div class="input-wrapper">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        <input type="email" name="email" placeholder="Email Address" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="loginBtn">Authenticate to Dashboard</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('loginBtn').addEventListener('click', function(e) {
            let rect = this.getBoundingClientRect();
            let x = e.clientX - rect.left;
            let y = e.clientY - rect.top;
            let ripple = document.createElement('span');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            this.appendChild(ripple);
            setTimeout(() => { ripple.remove(); }, 600);
        });
    </script>
</body>
</html>
