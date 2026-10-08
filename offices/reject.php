<?php
session_start();

include '../includes/config.php';
include '../includes/functions.php';
include '../includes/csrf.php';

if(!isset($_SESSION['officer_id']) || !isset($_SESSION['office'])){
    header("Location: login.php");
    exit();
}

verify_csrf();

$office = strtoupper($_SESSION['office']);
$id = intval($_POST['id']);
$reason = mysqli_real_escape_string($conn, $_POST['comment']);

mysqli_query($conn,
"INSERT INTO clearance_status
(student_id, department_role, status, comment, approved_by, approved_at)
VALUES
('$id','$office','rejected','$reason','{$_SESSION['officer_id']}',NOW())
ON DUPLICATE KEY UPDATE
status='rejected',
comment='$reason',
approved_by='{$_SESSION['officer_id']}',
approved_at=NOW()
");

header("Location: dashboard.php?msg=rejected");
exit();
?>