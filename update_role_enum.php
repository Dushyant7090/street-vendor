<?php
require_once("config.php");
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'vendor', 'officer') NOT NULL DEFAULT 'vendor'");
echo "DB Updated";
?>
