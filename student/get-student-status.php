<?php
session_start();
include_once '../includes/config.php';
include_once '../includes/auth.php';

// Check if student is logged in
if(!isset($_SESSION['student_id'])){
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student overall status from students table
$q = mysqli_query($conn,"
    SELECT status FROM students WHERE id='$student_id'
");
$row = mysqli_fetch_assoc($q);
$overall_status = $row['status'] ?? 'pending';

// Get approved count from clearance_status
$approved_q = mysqli_query($conn,"
    SELECT COUNT(*) as approved 
    FROM clearance_status
    WHERE student_id='$student_id'
    AND status='approved'
");
$approved_data = mysqli_fetch_assoc($approved_q);
$approved_count = $approved_data['approved'] ?? 0;

// Get total offices count
$total_q = mysqli_query($conn,"
    SELECT COUNT(*) as total 
    FROM offices 
    WHERE status='active'
");
$total_data = mysqli_fetch_assoc($total_q);
$total_offices = $total_data['total'] ?? 0;

// Get pending count
$pending_q = mysqli_query($conn,"
    SELECT COUNT(*) as pending 
    FROM clearance_status
    WHERE student_id='$student_id'
    AND status='pending'
");
$pending_data = mysqli_fetch_assoc($pending_q);
$pending_count = $pending_data['pending'] ?? 0;

// Get rejected count
$rejected_q = mysqli_query($conn,"
    SELECT COUNT(*) as rejected 
    FROM clearance_status
    WHERE student_id='$student_id'
    AND status='rejected'
");
$rejected_data = mysqli_fetch_assoc($rejected_q);
$rejected_count = $rejected_data['rejected'] ?? 0;

// Calculate progress
$progress = ($total_offices > 0) ? round(($approved_count / $total_offices) * 100) : 0;

// Check if all offices are approved
$all_approved = ($approved_count >= $total_offices && $total_offices > 0);

// If all offices are approved but student status is not 'cleared', update it
if($all_approved && $overall_status != 'cleared'){
    mysqli_query($conn,"
        UPDATE students 
        SET status = 'cleared' 
        WHERE id = '$student_id'
    ");
    $overall_status = 'cleared';
}

// Check if there are any clearance status records
$status_q = mysqli_query($conn,"
    SELECT COUNT(*) as total 
    FROM clearance_status 
    WHERE student_id='$student_id'
");
$status_data = mysqli_fetch_assoc($status_q);
$has_records = $status_data['total'] > 0;

$response = [
    'status' => $overall_status,
    'approved' => $approved_count,
    'total' => $total_offices,
    'pending' => $pending_count,
    'rejected' => $rejected_count,
    'progress' => $progress,
    'all_approved' => $all_approved,
    'has_records' => $has_records,
    'is_cleared' => ($overall_status == 'cleared')
];

echo json_encode($response);
?>