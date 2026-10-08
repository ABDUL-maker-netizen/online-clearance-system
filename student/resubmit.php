<?php

session_start();

include '../includes/config.php';
include '../includes/functions.php';

if(!isset($_SESSION['student_id'])){
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

if(!isset($_GET['office']) || empty($_GET['office'])){
    header("Location: dashboard.php");
    exit();
}

$office = strtoupper(
    mysqli_real_escape_string(
        $conn,
        $_GET['office']
    )
);

/* =========================
   VERIFY OFFICE EXISTS
========================= */
$check_office = mysqli_query($conn,
"SELECT *
 FROM offices
 WHERE UPPER(offices_name)=UPPER('$office')
 LIMIT 1");

if(mysqli_num_rows($check_office) == 0){
    die("Invalid Office");
}

/* =========================
   RESET ONLY REJECTED RECORD
========================= */
$update = mysqli_query($conn,
"UPDATE clearance_status
SET status='pending',
    comment=''
WHERE student_id='$student_id'
AND department_role='$office'
AND status='rejected'");

/* =========================
   SEND RESUBMISSION NOTIFICATION
========================= */
if(mysqli_affected_rows($conn) > 0){

    notify(
        $conn,
        $student_id,
        "Your clearance application for $office has been resubmitted and is awaiting review."
    );

}

/* =========================
   UPDATE STUDENT STATUS
========================= */
mysqli_query($conn,
"UPDATE students
SET status='pending'
WHERE id='$student_id'");

/* =========================
   REDIRECT BACK
========================= */
header("Location: dashboard.php");
exit();

?>