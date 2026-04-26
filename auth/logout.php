<?php
/**
 * Logout
 * Destroys session and redirects to login page.
 */
require_once __DIR__ . '/../config/database.php';

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login
redirect('/street_vendor/login.php');
?>
