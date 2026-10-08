<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/auth.php';

// Check if user is logged in
$is_logged_in = false;
$user_type = null;

// Determine user type and validate session
if(isset($_SESSION['student_id'])){
    $is_logged_in = true;
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
} elseif(isset($_SESSION['office_officer_id'])){
    $is_logged_in = true;
    $user_type = 'office';
    $office = $_SESSION['office_name'];
} elseif(isset($_SESSION['department_officer_id'])){
    $is_logged_in = true;
    $user_type = 'department';
    $department = $_SESSION['department_name'];
} elseif(isset($_SESSION['faculty_officers_id'])){
    $is_logged_in = true;
    $user_type = 'faculty';
    $faculty = $_SESSION['faculty_name'];
}

if(!$is_logged_in){
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$response = [];

if($user_type === 'student'){
    // Student - get their own LATEST status from clearance_status
    $q = mysqli_query($conn,"
        SELECT cs1.status 
        FROM clearance_status cs1
        INNER JOIN (
            SELECT department_role, MAX(id) AS max_id
            FROM clearance_status
            WHERE student_id='$user_id'
            GROUP BY department_role
        ) cs2 ON cs1.id = cs2.max_id
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($q);
    $response[$user_id] = $row['status'] ?? 'pending';
    
} elseif($user_type === 'office'){
    // Office officer - get LATEST status per student for their office
    // FIX: Use subquery with MAX(id) to get only the most recent record per student
    $office_escaped = mysqli_real_escape_string($conn, $office);
    $q = mysqli_query($conn,"
        SELECT s.id, cs1.status 
        FROM students s
        INNER JOIN clearance_status cs1 ON s.id = cs1.student_id
        INNER JOIN (
            SELECT student_id, MAX(id) AS max_id
            FROM clearance_status
            WHERE UPPER(department_role) = UPPER('$office_escaped')
            GROUP BY student_id
        ) cs2 ON cs1.id = cs2.max_id
        WHERE UPPER(cs1.department_role) = UPPER('$office_escaped')
        ORDER BY s.id DESC
    ");
    while($row = mysqli_fetch_assoc($q)){
        $response[$row['id']] = $row['status'];
    }
    
} elseif($user_type === 'department'){
    // Department officer - get LATEST status per student for DEPARTMENT
    $department_escaped = mysqli_real_escape_string($conn, $department);
    $q = mysqli_query($conn,"
        SELECT s.id, cs1.status 
        FROM students s
        INNER JOIN clearance_status cs1 ON s.id = cs1.student_id
        INNER JOIN (
            SELECT student_id, MAX(id) AS max_id
            FROM clearance_status
            WHERE UPPER(department_role) = 'DEPARTMENT'
            GROUP BY student_id
        ) cs2 ON cs1.id = cs2.max_id
        WHERE UPPER(cs1.department_role) = 'DEPARTMENT'
        AND (
            TRIM(LOWER(cs1.department_name)) = LOWER('$department_escaped')
            OR TRIM(LOWER(s.department)) = LOWER('$department_escaped')
        )
        ORDER BY s.id DESC
    ");
    while($row = mysqli_fetch_assoc($q)){
        $response[$row['id']] = $row['status'];
    }
    
} elseif($user_type === 'faculty'){
    // Faculty officer - get LATEST status per student for FACULTY
    $faculty_escaped = mysqli_real_escape_string($conn, $faculty);
    $q = mysqli_query($conn,"
        SELECT s.id, cs1.status 
        FROM students s
        INNER JOIN clearance_status cs1 ON s.id = cs1.student_id
        INNER JOIN (
            SELECT student_id, MAX(id) AS max_id
            FROM clearance_status
            WHERE UPPER(department_role) = 'FACULTY'
            GROUP BY student_id
        ) cs2 ON cs1.id = cs2.max_id
        WHERE UPPER(cs1.department_role) = 'FACULTY'
        AND TRIM(LOWER(s.faculty_name)) = LOWER('$faculty_escaped')
        ORDER BY s.id DESC
    ");
    while($row = mysqli_fetch_assoc($q)){
        $response[$row['id']] = $row['status'];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>