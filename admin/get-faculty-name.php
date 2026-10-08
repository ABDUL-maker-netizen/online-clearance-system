<?php
session_start();
include '../includes/config.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])){
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get faculty_id from POST
$faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;

if($faculty_id <= 0){
    echo json_encode(['faculty_name' => '']);
    exit();
}

// Fetch faculty name
$query = mysqli_query($conn, "
    SELECT faculty_name 
    FROM faculties 
    WHERE id = '$faculty_id'
");

if($row = mysqli_fetch_assoc($query)){
    echo json_encode(['faculty_name' => $row['faculty_name']]);
} else {
    echo json_encode(['faculty_name' => '']);
}
exit();
?>