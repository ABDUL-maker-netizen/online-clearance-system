<?php
include 'includes/config.php';

$token = $_GET['token'];

mysqli_query($conn,
"UPDATE students SET verified='1'
WHERE verification_token='$token'");

echo "Email Verified Successfully";
?>