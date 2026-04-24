<?php
session_start();
include "config/database.php";

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_assoc($result);

    if(password_verify($password, $row['password'])){
        $_SESSION['name'] = $row['name'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['user_id'] = $row['id'];

        if($row['role'] == 'admin'){
            header("Location: admin/dashboard.php");
            exit();
        }
        else if($row['role'] == 'user'){
            header("Location: user/dashboard.php");
            exit();
        }
    } else {
        echo "Wrong password";
    }
} else {
    echo "User not found";
}
?>