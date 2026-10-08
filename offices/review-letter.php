<?php
session_start();

include '../includes/config.php';
include '../includes/functions.php';

if(!isset($_SESSION['officer_id'])){
    header("Location: login.php");
    exit();
}

$id = intval($_GET['id']);

$query = mysqli_query($conn,"
SELECT dl.*, s.fullname, s.reg_number, s.department
FROM department_letters dl
JOIN students s ON s.id = dl.student_id
WHERE dl.id='$id'
");

$data = mysqli_fetch_assoc($query);

if(!$data){
    die("Not found");
}

$message = "";

/* APPROVE */
if(isset($_POST['approve'])){

    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $officer = $_SESSION['officer_id'];

    mysqli_query($conn,"
    UPDATE department_letters
    SET status='approved',
        comment='$comment'
    WHERE id='$id'
    ");

    /* NOTIFICATION */
    notify(
        $conn,
        $data['student_id'],
        "Your department clearance letter has been signed and approved."
    );

    $message = "<div class='alert alert-success'>Approved</div>";
}

/* REJECT */
if(isset($_POST['reject'])){

    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    mysqli_query($conn,"
    UPDATE department_letters
    SET status='rejected',
        comment='$comment'
    WHERE id='$id'
    ");

    /* NOTIFICATION */
    notify(
        $conn,
        $data['student_id'],
        "Your department clearance letter was rejected. Please review the comment and resubmit."
    );

    $message = "<div class='alert alert-danger'>Rejected</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Review Letter</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>

<div class="container mt-5">

<h3>Review Letter</h3>

<?php echo $message; ?>

<p><b>Name:</b> <?php echo htmlspecialchars($data['fullname']); ?></p>
<p><b>Reg No:</b> <?php echo htmlspecialchars($data['reg_number']); ?></p>
<p><b>Dept:</b> <?php echo htmlspecialchars($data['department']); ?></p>

<a target="_blank"
href="../assets/uploads/department_letters/<?php echo $data['letter_file']; ?>">
View Letter
</a>

<form method="POST" class="mt-4">

<textarea
name="comment"
class="form-control mb-2"
placeholder="Enter comment"></textarea>

<button name="approve" class="btn btn-success">
Approve
</button>

<button name="reject" class="btn btn-danger">
Reject
</button>

</form>

</div>

</body>
</html>