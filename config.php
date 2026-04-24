<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "street_vendor";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>