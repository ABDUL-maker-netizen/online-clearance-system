<?php
session_start();
include '../includes/config.php';

if(!isset($_SESSION['admin_id'])){
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$response = [];

// Get total started
$total_started = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT student_id) as total 
    FROM clearance_status
"))['total'] ?? 0;

// Get total cleared
$total_cleared = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM students 
    WHERE LOWER(status) = 'cleared'
"))['total'] ?? 0;

// Get total students
$students_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM admission_list"))['count'] ?? 0;

$response = [
    'total_started' => $total_started,
    'total_cleared' => $total_cleared,
    'total_pending' => $total_started - $total_cleared,
    'total_rejected' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE LOWER(status) = 'rejected'"))['total'] ?? 0,
    'total_not_started' => $students_count - $total_started,
    'students_count' => $students_count
];

echo json_encode($response);