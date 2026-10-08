<?php
session_start();
include '../includes/config.php';

/* CHECK ID */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id <= 0){
    die("Invalid Student ID");
}

/* FETCH STUDENT (optional safety check) */
$student = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT * FROM students WHERE id='$id'
"));

if(!$student){
    die("Student not found");
}

/* UPDATE STATUS */
mysqli_query($conn,"
UPDATE students
SET senate_status='rejected',
    status='pending'
WHERE id='$id'
");

/* OPTIONAL: LOG OR NOTIFICATION */
$message = "Your clearance has been rejected by the Senate. Please contact the office.";

mysqli_query($conn,"
INSERT INTO notifications(
    user_id,
    message,
    status,
    created_at
) VALUES(
    '$id',
    '$message',
    'unread',
    NOW()
)");

/* REDIRECT BACK */
header("Location: dashboard.php");
exit();
?>