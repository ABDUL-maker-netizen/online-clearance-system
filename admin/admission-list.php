<?php
session_start();

include '../includes/config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

// Handle Delete from admission list
if(isset($_GET['delete']) && is_numeric($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM admission_list WHERE id='$id'");
    $_SESSION['alert_message'] = "Admission record deleted successfully.";
    $_SESSION['alert_type'] = 'success';
    header("Location: admission-list.php");
    exit();
}

// Import student from admission list
if(isset($_GET['import']) && is_numeric($_GET['import'])){
    $id = intval($_GET['import']);
    $admission = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admission_list WHERE id='$id'"));
    
    if($admission){
        // Check if student already exists
        $check = mysqli_query($conn, "SELECT id FROM students WHERE reg_number='{$admission['reg_number']}'");
        if(mysqli_num_rows($check) > 0){
            $_SESSION['alert_message'] = "Student with registration number {$admission['reg_number']} already exists.";
            $_SESSION['alert_type'] = 'error';
        } else {
            // Generate password (default: reg_number)
            $password = password_hash($admission['reg_number'], PASSWORD_DEFAULT);
            $verification_code = md5($admission['reg_number'] . time());
            
            $query = "
                INSERT INTO students (fullname, reg_number, faculty_name, department, programme, degree_awarded, password, verification_code, status)
                VALUES (
                    '{$admission['fullname']}',
                    '{$admission['reg_number']}',
                    '{$admission['faculty_name']}',
                    '{$admission['department']}',
                    '{$admission['programme']}',
                    '{$admission['degree_awarded']}',
                    '$password',
                    '$verification_code',
                    'pending'
                )
            ";
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Student imported successfully!";
            $_SESSION['alert_type'] = 'success';
        }
    }
    header("Location: admission-list.php");
    exit();
}

// Import all students from admission list
if(isset($_GET['import_all'])){
    $admissions = mysqli_query($conn, "SELECT * FROM admission_list WHERE status='admitted'");
    $imported = 0;
    $skipped = 0;
    
    while($admission = mysqli_fetch_assoc($admissions)){
        $check = mysqli_query($conn, "SELECT id FROM students WHERE reg_number='{$admission['reg_number']}'");
        if(mysqli_num_rows($check) == 0){
            $password = password_hash($admission['reg_number'], PASSWORD_DEFAULT);
            $verification_code = md5($admission['reg_number'] . time());
            
            $query = "
                INSERT INTO students (fullname, reg_number, faculty_name, department, programme, degree_awarded, password, verification_code, status)
                VALUES (
                    '{$admission['fullname']}',
                    '{$admission['reg_number']}',
                    '{$admission['faculty_name']}',
                    '{$admission['department']}',
                    '{$admission['programme']}',
                    '{$admission['degree_awarded']}',
                    '$password',
                    '$verification_code',
                    'pending'
                )
            ";
            mysqli_query($conn, $query);
            $imported++;
        } else {
            $skipped++;
        }
    }
    
    $_SESSION['alert_message'] = "Imported $imported students. $skipped already exist.";
    $_SESSION['alert_type'] = 'success';
    header("Location: admission-list.php");
    exit();
}

$admissions = mysqli_query($conn, "SELECT * FROM admission_list ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission List</title>
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
        .status-badge{padding:3px 12px;border-radius:20px;font-size:12px;font-weight:500;background:#d4edda;color:#155724;}
        .btn-import{background:#28a745;color:#fff;border:none;}
        .btn-import:hover{background:#218838;color:#fff;}
        @media(max-width:768px){.sidebar{width:100%;height:auto;position:relative;}.main-content{margin-left:0;}}
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="bi bi-shield-lock"></i> Admin Panel</div>
    <a href="dashboard.php" class="nav-link"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="students.php" class="nav-link"><i class="bi bi-people"></i> Students</a>
    <a href="officers.php" class="nav-link"><i class="bi bi-person-badge"></i> Officers</a>
    <a href="faculty-officers.php" class="nav-link"><i class="bi bi-mortarboard"></i> Faculty Officers</a>
    <a href="department-officers.php" class="nav-link"><i class="bi bi-building"></i> Department Officers</a>
    <a href="admission-list.php" class="nav-link active"><i class="bi bi-list-check"></i> Admission List</a>
    <a href="logout.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main-content">

    <div class="top-bar">
        <h5 class="mb-0 fw-bold">Admission List</h5>
        <div class="user">
            <span class="text-muted small"><?php echo date('l, F d, Y'); ?></span>
            <div class="avatar"><?php echo substr($_SESSION['admin_name'], 0, 1); ?></div>
        </div>
    </div>

    <div class="card-custom">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-list-check"></i> All Admitted Students</span>
            <div>
                <a href="admission-list.php?import_all=1" class="btn btn-import btn-sm" onclick="return confirm('Import all students from admission list?')">
                    <i class="bi bi-upload"></i> Import All
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Reg No</th>
                            <th>Faculty</th>
                            <th>Department</th>
                            <th>Programme</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($admissions) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($admissions)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['programme']); ?></td>
                                <td><span class="status-badge"><?php echo ucfirst($row['status']); ?></span></td>
                                <td>
                                    <a href="admission-list.php?import=<?php echo $row['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Import this student?')"><i class="bi bi-upload"></i></a>
                                    <a href="admission-list.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No records found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>