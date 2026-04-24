<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'street_vendor');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit();
    }
}

if (!function_exists('setFlash')) {
    function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return (($_SESSION['role'] ?? '') === 'admin');
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        if (!isAdmin()) {
            redirect('/street_vendor/login.php');
        }
    }
}

if (!function_exists('generateLicenseNumber')) {
    function generateLicenseNumber(): string
    {
        return 'LIC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

if (!function_exists('logAdminAction')) {
    function logAdminAction(string $action, string $details = ''): void
    {
        global $conn;

        if (!($conn instanceof mysqli)) {
            return;
        }

        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)');
        if (!$stmt) {
            return;
        }

        $nullableAdminId = $adminId > 0 ? $adminId : null;
        $stmt->bind_param('iss', $nullableAdminId, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}
?>