<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

$host = "localhost";
$user = "root";
$password = "root";
$database = "clearance_system";

$conn = mysqli_connect($host, $user, $password, $database);

if(!$conn){
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>