<?php
/**
 * Logout
 * Destroys session and redirects to login page.
 */
require_once __DIR__ . '/../config/database.php';

// Destroy all session data
session_unset();
session_destroy();

// Start new session for flash message
session_start();
setFlash('success', 'You have been logged out successfully.');

// Redirect to login
redirect('/street_vendor/auth/login.php');
?>
