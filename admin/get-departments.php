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
    echo json_encode([]);
    exit();
}

// Fetch departments for the given faculty
$query = mysqli_query($conn, "
    SELECT id, department_name 
    FROM departments 
    WHERE faculty_id = '$faculty_id' 
    ORDER BY department_name ASC
");

$departments = [];
while($row = mysqli_fetch_assoc($query)){
    $departments[] = [
        'id' => $row['id'],
        'department_name' => $row['department_name']
    ];
}

header('Content-Type: application/json');
echo json_encode($departments);
exit();
?>