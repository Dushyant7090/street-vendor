<?php
/**
 * Sidebar Navigation Component
 * Renders role-based sidebar menu for Admin and Vendor dashboards.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? 'vendor';
$userName = $_SESSION['user_name'] ?? 'User';
$userInitial = strtoupper(substr($userName, 0, 1));
?>

<!-- Mobile Overlay -->
<div class="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">🏪</div>
        <div class="brand-text">
            <h2>Street Vendor</h2>
            <span>Management System</span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-menu">
        <?php if ($userRole === 'admin'): ?>
            <!-- ADMIN MENU -->
            <div class="menu-label">Main</div>
            <a href="/street_vendor/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-dashboard'></i></span>
                Dashboard
            </a>

            <div class="menu-label">Management</div>
            <a href="/street_vendor/admin/vendors.php" class="<?php echo $currentPage === 'vendors.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-user-detail'></i></span>
                Vendors
            </a>
            <a href="/street_vendor/admin/licenses.php" class="<?php echo $currentPage === 'licenses.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-id-card'></i></span>
                Licenses
            </a>
            <a href="/street_vendor/admin/locations.php" class="<?php echo $currentPage === 'locations.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-map'></i></span>
                Locations
            </a>
            <a href="/street_vendor/admin/zones.php" class="<?php echo $currentPage === 'zones.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-map-alt'></i></span>
                Zones
            </a>
            <a href="/street_vendor/admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-cog'></i></span>
                Settings
            </a>

        <?php else: ?>
            <!-- VENDOR MENU -->
            <div class="menu-label">Main</div>
            <a href="/street_vendor/vendor/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-dashboard'></i></span>
                Dashboard
            </a>

            <div class="menu-label">License</div>
            <a href="/street_vendor/vendor/apply_license.php" class="<?php echo $currentPage === 'apply_license.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-file-plus'></i></span>
                Apply for License
            </a>
            <a href="/street_vendor/vendor/my_licenses.php" class="<?php echo $currentPage === 'my_licenses.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-id-card'></i></span>
                My Licenses
            </a>

            <div class="menu-label">Location</div>
            <a href="/street_vendor/vendor/my_location.php" class="<?php echo $currentPage === 'my_location.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-map-pin'></i></span>
                My Location
            </a>

            <div class="menu-label">Account</div>
            <a href="/street_vendor/vendor/profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class='bx bxs-user-circle'></i></span>
                Profile
            </a>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer (User Info) -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo $userInitial; ?></div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($userName); ?></h4>
                <span><?php echo $userRole; ?></span>
            </div>
        </div>
        <a href="/street_vendor/auth/logout.php" class="btn btn-outline btn-sm btn-block mt-2" style="border-color: rgba(255,255,255,0.2); color: var(--text-sidebar);">
            <i class='bx bx-log-out'></i> Logout
        </a>
    </div>
</aside>
