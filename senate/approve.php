<?php
session_start();
include '../includes/config.php';

/* CHECK LOGIN (optional but recommended) */
if(!isset($_SESSION['senate_id'])){
    // adjust if you use different session name
    // header("Location: login.php");
    // exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id <= 0){
    die("Invalid Student ID");
}

/* FETCH STUDENT */
$student_query = mysqli_query($conn,"
SELECT * FROM students WHERE id='$id'
");

$student = mysqli_fetch_assoc($student_query);

if(!$student){
    die("Student not found");
}

/* HANDLE ACTION */
if(isset($_GET['action'])){

    $action = $_GET['action'];

    if($action == 'approve'){

        mysqli_query($conn,"
        UPDATE students
        SET senate_status='approved',
            status='cleared'
        WHERE id='$id'
        ");

    } elseif($action == 'reject'){

        mysqli_query($conn,"
        UPDATE students
        SET senate_status='rejected',
            status='pending'
        WHERE id='$id'
        ");
    }

    header("Location: approve.php?id=$id");
    exit();
}

/* REFRESH STUDENT DATA AFTER UPDATE */
$student = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT * FROM students WHERE id='$id'
"));

$senate_status = $student['senate_status'] ?? 'pending';
$status = $student['status'];

?>

<!DOCTYPE html>
<html>
<head>

<title>Senate Approval Panel</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
}
.card{
    border:none;
    border-radius:12px;
}
.status-box{
    padding:15px;
    border-radius:10px;
    font-weight:bold;
}
</style>

</head>

<body>

<div class="container mt-5">

<div class="card shadow p-4">

<h3 class="mb-3">🎓 Senate Clearance Approval</h3>

<hr>

<p><strong>Name:</strong> <?php echo htmlspecialchars($student['fullname']); ?></p>
<p><strong>Reg Number:</strong> <?php echo htmlspecialchars($student['reg_number']); ?></p>
<p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>

<hr>

<!-- SENATE STATUS -->
<div class="status-box text-center">

<?php if($senate_status == 'approved'){ ?>

    <div class="alert alert-success">
        ✔ SENATE APPROVED
    </div>

<?php } elseif($senate_status == 'rejected'){ ?>

    <div class="alert alert-danger">
        ✖ SENATE REJECTED
    </div>

<?php } else { ?>

    <div class="alert alert-warning">
        ⏳ PENDING SENATE REVIEW
    </div>

<?php } ?>

</div>

<!-- ACTION BUTTONS -->
<div class="d-flex gap-2 justify-content-center mt-3">

<a href="?id=<?php echo $id; ?>&action=approve"
class="btn btn-success">

Approve
</a>

<a href="?id=<?php echo $id; ?>&action=reject"
class="btn btn-danger">

Reject
</a>

<a href="dashboard.php"
class="btn btn-secondary">

Back
</a>

</div>

</div>

</div>

</body>
</html>