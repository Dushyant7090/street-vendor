<?php
require 'c:\wamp64\www\street_vendor\config.php';
$hash = password_hash('123456', PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = 'admin@gmail.com'");
$stmt->bind_param('s', $hash);
$stmt->execute();
if ($stmt->affected_rows > 0) {
    echo "Password updated successfully.\n";
} else {
    // If admin@gmail.com doesn't exist, insert it
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@gmail.com', ?, 'admin')");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    echo "Admin user created with password.\n";
}
?>
