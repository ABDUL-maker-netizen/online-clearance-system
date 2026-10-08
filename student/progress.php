<?php

session_start();
include '../includes/config.php';

if(!isset($_SESSION['student_id'])){
    exit;
}

$student_id = $_SESSION['student_id'];

/* TOTAL OFFICES (NOT DEPARTMENTS) */
$total_query = mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM offices
WHERE status='active'");

$total_row = mysqli_fetch_assoc($total_query);
$total = $total_row['total'] ?? 0;

/* APPROVED COUNT */
$approved_query = mysqli_query($conn,
"SELECT COUNT(*) AS approved
FROM clearance_status
WHERE student_id='$student_id'
AND status='approved'");

$approved_row = mysqli_fetch_assoc($approved_query);
$approved = $approved_row['approved'] ?? 0;

/* PROGRESS CALCULATION */
$progress = 0;

if($total > 0){
    $progress = round(($approved / $total) * 100);
}

/* RETURN JSON */
header('Content-Type: application/json');

echo json_encode([
    "approved" => (int)$approved,
    "progress" => (int)$progress,
    "total" => (int)$total
]);