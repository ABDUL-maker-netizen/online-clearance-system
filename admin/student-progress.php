<?php

include '../includes/config.php';

$student_id = intval($_GET['id']);

/* TOTAL DEPARTMENTS */
$total = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM departments"))['total'];

/* APPROVED COUNT */
$approved = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(DISTINCT department_role) AS total
 FROM clearance_status
 WHERE student_id='$student_id'
 AND status='approved'"))['total'];

$percent = ($total > 0) ? round(($approved / $total) * 100) : 0;

echo json_encode([
    "student_id" => $student_id,
    "progress" => $percent,
    "approved" => $approved,
    "total" => $total
]);