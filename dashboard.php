<?php

session_start();

include 'includes/config.php';
include 'includes/session.php';
include 'includes/auth.php';

studentAuth();

if(!isset($_SESSION['student_id'])){
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* TOTAL DEPARTMENTS */
$result = mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM departments
WHERE status='active'");

$row = mysqli_fetch_assoc($result);

$total = $row['total'];

/* APPROVED DEPARTMENTS */
$approved = mysqli_num_rows(mysqli_query($conn,
"SELECT * FROM clearance_status
WHERE student_id='$student_id'
AND status='approved'"));

$progress = 0;

/* CHECK UPLOADS */
$upload_check = mysqli_query($conn,
"SELECT * FROM clearance_uploads
WHERE student_id='$student_id'");

if(mysqli_num_rows($upload_check) > 0){
    if($total > 0){
        $progress = round(($approved / $total) * 100);
    }
}

/* STUDENT INFO */
$student_query = mysqli_query($conn,
"SELECT * FROM students
WHERE id='$student_id'");

$student = mysqli_fetch_assoc($student_query);

$status = $student['status'];

/* QR FILE */
$qr_file = "../assets/qrcodes/student_" . $student_id . ".png";

/* ===========================
   NOTIFICATION COUNT
=========================== */
$notif_query = mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM notifications
WHERE user_id='$student_id'
AND status='unread'");

$notif_row = mysqli_fetch_assoc($notif_query);

$notif_count = $notif_row['total'] ?? 0;




?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
      <link rel="stylesheet" href="assets/css/bootstrap.css">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css">

    <style>
        body{
            overflow-x:hidden;
            background:#f8f9fa;
        }

        .wrapper{
            display:flex;
        }

        .sidebar{
            width:280px;
            min-height:100vh;
            background:#212529;
            color:#fff;
            transition:all .3s ease;
        }

        .sidebar.collapsed{
            width:70px;
        }

        .sidebar.collapsed .sidebar-text,
        .sidebar.collapsed .profile-section,
        .sidebar.collapsed .submenu{
            display:none;
        }

        .sidebar .nav-link{
            color:#fff;
            padding:12px 15px;
        }

        .sidebar .nav-link:hover{
            background:rgba(255,255,255,.1);
        }

        .content{
            flex:1;
            transition:all .3s ease;
        }

        .profile-img{
            width:100px;
            height:100px;
            border-radius:50%;
            object-fit:cover;
            border:3px solid #fff;
        }

        @media(max-width:991px){

            .sidebar{
                position:fixed;
                left:-280px;
                top:0;
                z-index:1050;
            }

            .sidebar.show{
                left:0;
            }

            .content{
                width:100%;
            }
        }
    </style>
</head> 
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-dark bg-dark shadow-sm py-4">
    <div class="container-fluid">

        <!-- Left Side -->
        <div class="d-flex align-items-center">

            <button class="navbar-toggler me-3"
                    type="button"
                    id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>

            <span class="navbar-brand mb-0">
                Welcome,
                <?php echo htmlspecialchars($student['fullname']); ?>
            </span>

        </div>

        <!-- Right Side -->
        <div class="d-flex align-items-center">

            <a href="student/notifications.php"
               class="position-relative me-4 text-decoration-none">

                <i class="bi bi-bell text-white fs-4"></i>

                <?php if($notif_count > 0){ ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $notif_count; ?>
                    </span>
                <?php } else { ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
                        0
                    </span>
                <?php } ?>

            </a>

            <a href="logout.php" class="btn btn-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

    </div>
</nav>



<div class="wrapper">

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">

        <div class="text-center p-3 profile-section">

            <img src="assets/uploads/<?php echo htmlspecialchars($student['passport']); ?>"
     class="profile-img"
     alt="Student">

            <h6 class="mt-3 mb-1">
             <?php echo htmlspecialchars($student['fullname']); ?>

            </h6>

            <small>
             <?php echo htmlspecialchars($student['reg_number']); ?>

            </small>

        </div>

        <hr class="text-secondary">

        <ul class="nav flex-column">

            <?php include('sidebar.php'); ?>

        </ul>

    </div>

    <!-- Main Content -->
    <div class="content p-4">

    

        <div class="card shadow-sm">
            <div class="card-body">

                <?php
            if(isset($_GET['edit_photo'])){
                include('edit-photo.php');
            }

            if(isset($_GET['changepassword'])){
                include('changepassword.php');
            }
            ?>

            </div>
        </div>

    </div>

</div>

<script>
const toggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

toggleBtn.addEventListener('click', function(){

    if(window.innerWidth <= 991){
        sidebar.classList.toggle('show');
    }else{
        sidebar.classList.toggle('collapsed');
    }

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>