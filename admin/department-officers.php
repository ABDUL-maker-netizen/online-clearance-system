<?php
session_start();

include '../includes/config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

// Handle Delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM department_officers WHERE id='$id'");
    $_SESSION['alert_message'] = "Department officer deleted successfully.";
    $_SESSION['alert_type'] = 'success';
    header("Location: department-officers.php");
    exit();
}

// Handle Add/Edit
if(isset($_POST['save_dept_officer'])){
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $faculty_name = mysqli_real_escape_string($conn, $_POST['faculty_name']);
    $department_name = mysqli_real_escape_string($conn, $_POST['department_name']);
    $password = $_POST['password'];
    
    if($id > 0){
        if(!empty($password)){
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $query = "
                UPDATE department_officers SET 
                    fullname='$fullname', email='$email', phone='$phone', 
                    faculty_name='$faculty_name', department_name='$department_name', password='$hashed'
                WHERE id='$id'
            ";
        } else {
            $query = "
                UPDATE department_officers SET 
                    fullname='$fullname', email='$email', phone='$phone', 
                    faculty_name='$faculty_name', department_name='$department_name'
                WHERE id='$id'
            ";
        }
        mysqli_query($conn, $query);
        $_SESSION['alert_message'] = "Department officer updated successfully.";
        $_SESSION['alert_type'] = 'success';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $query = "
            INSERT INTO department_officers (fullname, email, phone, faculty_name, department_name, password)
            VALUES ('$fullname', '$email', '$phone', '$faculty_name', '$department_name', '$hashed')
        ";
        mysqli_query($conn, $query);
        $_SESSION['alert_message'] = "Department officer added successfully.";
        $_SESSION['alert_type'] = 'success';
    }
    header("Location: department-officers.php");
    exit();
}

$officers = mysqli_query($conn, "SELECT * FROM department_officers ORDER BY id DESC");
$edit_officer = null;
if(isset($_GET['edit']) && is_numeric($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    $edit_officer = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM department_officers WHERE id='$edit_id'"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Officers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--sidebar-width:260px;--primary:#004bca;}
        *{box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:#f0f2f5;}
        .sidebar{position:fixed;left:0;top:0;height:100vh;width:var(--sidebar-width);background:#1a1a2e;color:#fff;padding:20px 0;overflow-y:auto;z-index:1000;}
        .sidebar .brand{font-size:22px;font-weight:700;padding:0 24px 20px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:16px;display:flex;align-items:center;gap:10px;}
        .sidebar .brand i{font-size:28px;color:#4edea3;}
        .sidebar .nav-link{color:rgba(255,255,255,0.7);padding:10px 24px;display:flex;align-items:center;gap:12px;text-decoration:none;transition:0.2s;border-left:3px solid transparent;}
        .sidebar .nav-link:hover{background:rgba(255,255,255,0.05);color:#fff;}
        .sidebar .nav-link.active{background:rgba(255,255,255,0.08);color:#fff;border-left-color:#4edea3;}
        .sidebar .nav-link i{width:22px;text-align:center;font-size:18px;}
        .main-content{margin-left:var(--sidebar-width);padding:24px;}
        .top-bar{background:#fff;padding:16px 24px;border-radius:12px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
        .top-bar .user{display:flex;align-items:center;gap:12px;}
        .top-bar .user .avatar{width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;}
        .card-custom{background:#fff;border-radius:12px;border:1px solid #e9ecef;box-shadow:0 1px 3px rgba(0,0,0,0.06);}
        .card-custom .card-header{background:transparent;border-bottom:1px solid #e9ecef;padding:16px 20px;font-weight:600;}
        .card-custom .card-body{padding:20px;}
        .btn-primary{background:var(--primary);border:none;}
        .btn-primary:hover{background:#003da1;}
        .btn-sm{padding:4px 12px;font-size:12px;border-radius:6px;}
        .table th{background:#f8f9fa;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;}
        .table td{vertical-align:middle;}
        @media(max-width:768px){.sidebar{width:100%;height:auto;position:relative;}.main-content{margin-left:0;}}
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-shield-lock"></i> Admin Panel</div>
    <a href="dashboard.php" class="nav-link"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="students.php" class="nav-link"><i class="bi bi-people"></i> Students (Admission List)</a>
    <a href="officers.php" class="nav-link"><i class="bi bi-person-badge"></i> Officers</a>
    <a href="faculty-officers.php" class="nav-link"><i class="bi bi-mortarboard"></i> Faculty Officers</a>
    <a href="department-officers.php" class="nav-link active"><i class="bi bi-building"></i> Department Officers</a>
    <a href="logout.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main-content">

    <div class="top-bar">
        <h5 class="mb-0 fw-bold">Manage Department Officers</h5>
        <div class="user">
            <span class="text-muted small"><?php echo date('l, F d, Y'); ?></span>
            <div class="avatar"><?php echo substr($_SESSION['admin_name'], 0, 1); ?></div>
        </div>
    </div>

    <?php if(isset($_GET['action']) && $_GET['action'] == 'add' || isset($_GET['edit'])): ?>
    <div class="card-custom mb-4">
        <div class="card-header">
            <?php echo isset($_GET['edit']) ? 'Edit Department Officer' : 'Add New Department Officer'; ?>
            <a href="department-officers.php" class="btn btn-secondary btn-sm float-end">Back</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if(isset($_GET['edit'])): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_officer['id']; ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo $edit_officer['fullname'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $edit_officer['email'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $edit_officer['phone'] ?? ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Faculty Name</label>
                        <input type="text" name="faculty_name" class="form-control" value="<?php echo $edit_officer['faculty_name'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Department Name</label>
                        <input type="text" name="department_name" class="form-control" value="<?php echo $edit_officer['department_name'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><?php echo isset($_GET['edit']) ? 'New Password (leave blank to keep current)' : 'Password'; ?></label>
                        <input type="password" name="password" class="form-control" <?php echo !isset($_GET['edit']) ? 'required' : ''; ?>>
                    </div>
                </div>
                <button type="submit" name="save_dept_officer" class="btn btn-primary mt-3">
                    <i class="bi bi-check2"></i> <?php echo isset($_GET['edit']) ? 'Update' : 'Add'; ?>
                </button>
                <a href="department-officers.php" class="btn btn-secondary mt-3">Cancel</a>
            </form>
        </div>
    </div>
    <?php else: ?>
    
    <div class="card-custom">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-building"></i> All Department Officers</span>
            <a href="department-officers.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Faculty</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($officers) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($officers)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                <td>
                                    <a href="department-officers.php?edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                    <a href="department-officers.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No department officers found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>