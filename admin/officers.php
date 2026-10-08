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
    
    // Check which table the officer belongs to
    $check_officers = mysqli_query($conn, "SELECT id FROM officers WHERE id='$id'");
    if(mysqli_num_rows($check_officers) > 0){
        mysqli_query($conn, "DELETE FROM officers WHERE id='$id'");
    } else {
        $check_faculty = mysqli_query($conn, "SELECT id FROM faculty_officers WHERE id='$id'");
        if(mysqli_num_rows($check_faculty) > 0){
            mysqli_query($conn, "DELETE FROM faculty_officers WHERE id='$id'");
        } else {
            $check_dept = mysqli_query($conn, "SELECT id FROM department_officers WHERE id='$id'");
            if(mysqli_num_rows($check_dept) > 0){
                mysqli_query($conn, "DELETE FROM department_officers WHERE id='$id'");
            }
        }
    }
    
    $_SESSION['alert_message'] = "Officer deleted successfully.";
    $_SESSION['alert_type'] = 'success';
    header("Location: officers.php");
    exit();
}

// Handle Add/Edit
if(isset($_POST['save_officer'])){
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $office = mysqli_real_escape_string($conn, $_POST['office']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'];
    $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;
    $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
    
    $hashed = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    
    if($id > 0){
        // Update existing record - handle based on role
        if($role == 'FACULTY'){
            // Update faculty_officers table
            $faculty_name = '';
            if($faculty_id > 0){
                $faculty_result = mysqli_query($conn, "SELECT faculty_name FROM faculties WHERE id='$faculty_id'");
                if($faculty_row = mysqli_fetch_assoc($faculty_result)){
                    $faculty_name = $faculty_row['faculty_name'];
                }
            }
            
            if(!empty($password)){
                $query = "
                    UPDATE faculty_officers SET 
                        fullname='$fullname', 
                        email='$email', 
                        phone='$phone', 
                        faculty_id='$faculty_id',
                        faculty_name='$faculty_name',
                        password='$hashed'
                    WHERE id='$id'
                ";
            } else {
                $query = "
                    UPDATE faculty_officers SET 
                        fullname='$fullname', 
                        email='$email', 
                        phone='$phone', 
                        faculty_id='$faculty_id',
                        faculty_name='$faculty_name'
                    WHERE id='$id'
                ";
            }
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Faculty officer updated successfully.";
            $_SESSION['alert_type'] = 'success';
            
        } elseif($role == 'DEPARTMENT'){
            // Update department_officers table
            $faculty_name = '';
            $department_name = '';
            if($faculty_id > 0){
                $faculty_result = mysqli_query($conn, "SELECT faculty_name FROM faculties WHERE id='$faculty_id'");
                if($faculty_row = mysqli_fetch_assoc($faculty_result)){
                    $faculty_name = $faculty_row['faculty_name'];
                }
            }
            if($department_id > 0){
                $dept_result = mysqli_query($conn, "SELECT department_name FROM departments WHERE id='$department_id'");
                if($dept_row = mysqli_fetch_assoc($dept_result)){
                    $department_name = $dept_row['department_name'];
                }
            }
            
            if(!empty($password)){
                $query = "
                    UPDATE department_officers SET 
                        fullname='$fullname', 
                        email='$email', 
                        phone='$phone', 
                        faculty_id='$faculty_id',
                        faculty_name='$faculty_name',
                        department_id='$department_id',
                        department_name='$department_name',
                        password='$hashed'
                    WHERE id='$id'
                ";
            } else {
                $query = "
                    UPDATE department_officers SET 
                        fullname='$fullname', 
                        email='$email', 
                        phone='$phone', 
                        faculty_id='$faculty_id',
                        faculty_name='$faculty_name',
                        department_id='$department_id',
                        department_name='$department_name'
                    WHERE id='$id'
                ";
            }
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Department officer updated successfully.";
            $_SESSION['alert_type'] = 'success';
            
        } else {
            // Update officers table (OFFICE role)
            if(!empty($password)){
                $query = "
                    UPDATE officers SET 
                        fullname='$fullname', 
                        office='$office', 
                        email='$email', 
                        phone='$phone', 
                        role='$role', 
                        password='$hashed'
                    WHERE id='$id'
                ";
            } else {
                $query = "
                    UPDATE officers SET 
                        fullname='$fullname', 
                        office='$office', 
                        email='$email', 
                        phone='$phone', 
                        role='$role'
                    WHERE id='$id'
                ";
            }
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Officer updated successfully.";
            $_SESSION['alert_type'] = 'success';
        }
        
    } else {
        // Insert new record - based on role
        if($role == 'FACULTY'){
            $faculty_name = '';
            if($faculty_id > 0){
                $faculty_result = mysqli_query($conn, "SELECT faculty_name FROM faculties WHERE id='$faculty_id'");
                if($faculty_row = mysqli_fetch_assoc($faculty_result)){
                    $faculty_name = $faculty_row['faculty_name'];
                }
            }
            
            $query = "
                INSERT INTO faculty_officers (fullname, email, phone, faculty_id, faculty_name, password)
                VALUES ('$fullname', '$email', '$phone', '$faculty_id', '$faculty_name', '$hashed')
            ";
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Faculty officer added successfully.";
            $_SESSION['alert_type'] = 'success';
            
        } elseif($role == 'DEPARTMENT'){
            $faculty_name = '';
            $department_name = '';
            if($faculty_id > 0){
                $faculty_result = mysqli_query($conn, "SELECT faculty_name FROM faculties WHERE id='$faculty_id'");
                if($faculty_row = mysqli_fetch_assoc($faculty_result)){
                    $faculty_name = $faculty_row['faculty_name'];
                }
            }
            if($department_id > 0){
                $dept_result = mysqli_query($conn, "SELECT department_name FROM departments WHERE id='$department_id'");
                if($dept_row = mysqli_fetch_assoc($dept_result)){
                    $department_name = $dept_row['department_name'];
                }
            }
            
            $query = "
                INSERT INTO department_officers (fullname, email, phone, faculty_id, faculty_name, department_id, department_name, password)
                VALUES ('$fullname', '$email', '$phone', '$faculty_id', '$faculty_name', '$department_id', '$department_name', '$hashed')
            ";
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Department officer added successfully.";
            $_SESSION['alert_type'] = 'success';
            
        } else {
            $query = "
                INSERT INTO officers (fullname, office, email, phone, role, password)
                VALUES ('$fullname', '$office', '$email', '$phone', '$role', '$hashed')
            ";
            mysqli_query($conn, $query);
            $_SESSION['alert_message'] = "Officer added successfully.";
            $_SESSION['alert_type'] = 'success';
        }
    }
    header("Location: officers.php");
    exit();
}

// Get officers from officers table (OFFICE role only)
$officers = mysqli_query($conn, "SELECT * FROM officers WHERE role = 'OFFICE' ORDER BY id DESC");
$edit_officer = null;

// Also get faculty officers and department officers for display
$faculty_officers = mysqli_query($conn, "SELECT * FROM faculty_officers ORDER BY id DESC");
$department_officers = mysqli_query($conn, "SELECT * FROM department_officers ORDER BY id DESC");

// Get all faculties for dropdown
$faculties = mysqli_query($conn, "SELECT id, faculty_name FROM faculties ORDER BY faculty_name ASC");

if(isset($_GET['edit']) && is_numeric($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    // Check which table the ID belongs to
    $check_officers = mysqli_query($conn, "SELECT * FROM officers WHERE id='$edit_id'");
    if(mysqli_num_rows($check_officers) > 0){
        $edit_officer = mysqli_fetch_assoc($check_officers);
        $edit_officer['table'] = 'officers';
        $edit_officer['role'] = 'OFFICE';
    } else {
        $check_faculty = mysqli_query($conn, "SELECT * FROM faculty_officers WHERE id='$edit_id'");
        if(mysqli_num_rows($check_faculty) > 0){
            $edit_officer = mysqli_fetch_assoc($check_faculty);
            $edit_officer['table'] = 'faculty_officers';
            $edit_officer['role'] = 'FACULTY';
        } else {
            $check_dept = mysqli_query($conn, "SELECT * FROM department_officers WHERE id='$edit_id'");
            if(mysqli_num_rows($check_dept) > 0){
                $edit_officer = mysqli_fetch_assoc($check_dept);
                $edit_officer['table'] = 'department_officers';
                $edit_officer['role'] = 'DEPARTMENT';
            }
        }
    }
}

// Get alert message from session
$alert_message = isset($_SESSION['alert_message']) ? $_SESSION['alert_message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;
unset($_SESSION['alert_message']);
unset($_SESSION['alert_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Officers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root{--sidebar-width:260px;--primary:#1e40af;--primary-dark:#1e3a8a;--bg:#f8f9ff;}
        *{box-sizing:border-box;}
        body{font-family:'Hanken Grotesk',sans-serif;background:var(--bg);}
        .sidebar{position:fixed;left:0;top:0;height:100vh;width:var(--sidebar-width);background:#111827;color:#fff;padding:20px 0;overflow-y:auto;z-index:1000;}
        .sidebar .brand{font-size:22px;font-weight:700;padding:0 24px 20px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:16px;display:flex;align-items:center;gap:10px;font-family:'Hanken Grotesk',sans-serif;}
        .sidebar .brand i{font-size:28px;color:#10b981;}
        .sidebar .nav-link{color:rgba(255,255,255,0.7);padding:10px 24px;display:flex;align-items:center;gap:12px;text-decoration:none;transition:0.2s;border-left:3px solid transparent;font-family:'Hanken Grotesk',sans-serif;}
        .sidebar .nav-link:hover{background:rgba(255,255,255,0.05);color:#fff;}
        .sidebar .nav-link.active{background:rgba(255,255,255,0.08);color:#fff;border-left-color:#10b981;}
        .sidebar .nav-link i{width:22px;text-align:center;font-size:18px;}
        .main-content{margin-left:var(--sidebar-width);padding:24px;}
        .top-bar{background:#fff;padding:16px 24px;border-radius:12px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 3px rgba(0,0,0,0.05);border:1px solid #e5e7eb;}
        .top-bar .user{display:flex;align-items:center;gap:12px;font-family:'Hanken Grotesk',sans-serif;}
        .top-bar .user .avatar{width:40px;height:40px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;}
        .card-custom{background:#fff;border-radius:12px;border:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
        .card-custom .card-header{background:transparent;border-bottom:1px solid #e5e7eb;padding:16px 20px;font-weight:600;font-family:'Hanken Grotesk',sans-serif;}
        .card-custom .card-body{padding:20px;}
        .btn-primary{background:var(--primary);border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-primary:hover{background:var(--primary-dark);transform:scale(0.95);}
        .btn-success{background:#006c49;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-success:hover{background:#005236;transform:scale(0.95);}
        .btn-warning{background:#f59e0b;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;color:#000;}
        .btn-warning:hover{background:#d97706;transform:scale(0.95);color:#000;}
        .btn-danger{background:#dc2626;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-danger:hover{background:#b91c1c;transform:scale(0.95);}
        .btn-secondary{background:#4b5563;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-secondary:hover{background:#374151;transform:scale(0.95);}
        .btn-info{background:#0ea5e9;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;color:#fff;}
        .btn-info:hover{background:#0284c7;transform:scale(0.95);color:#fff;}
        .btn-sm{padding:4px 12px;font-size:12px;border-radius:6px;}
        .table th{background:#f8f9fa;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-family:'Hanken Grotesk',sans-serif;}
        .table td{vertical-align:middle;font-family:'Hanken Grotesk',sans-serif;}
        .role-badge{padding:3px 12px;border-radius:20px;font-size:12px;font-weight:500;font-family:'Hanken Grotesk',sans-serif;}
        .role-badge.office{background:#e8f0fe;color:#1e40af;}
        .role-badge.faculty{background:#d1fae5;color:#065f46;}
        .role-badge.department{background:#fef3c7;color:#92400e;}
        .office-dropdown{width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px;font-family:'Hanken Grotesk',sans-serif;}
        .office-dropdown:focus{border-color:var(--primary);outline:none;box-shadow:0 0 0 0.2rem rgba(30,64,175,0.15);}
        .conditional-fields{margin-top:12px;padding:12px;background:#f8f9fa;border-radius:8px;border:1px solid #e5e7eb;display:none;}
        .conditional-fields.show{display:block;}
        .required-star{color:#dc2626;margin-left:2px;}
        .form-label{font-weight:500;font-family:'Hanken Grotesk',sans-serif;}
        .text-muted{color:#4b5563 !important;font-family:'Hanken Grotesk',sans-serif;}
        @media(max-width:768px){.sidebar{width:100%;height:auto;position:relative;}.main-content{margin-left:0;}}
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand"><i class="bi bi-shield-lock"></i> Admin Panel</div>
    <a href="dashboard.php" class="nav-link"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="students.php" class="nav-link"><i class="bi bi-people"></i> Students (Admission List)</a>
    <a href="officers.php" class="nav-link active"><i class="bi bi-person-badge"></i> Officers</a>
    <a href="logout.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">

    <div class="top-bar">
        <h5 class="mb-0 fw-bold" style="font-family:'Hanken Grotesk',sans-serif;">Manage Officers</h5>
        <div class="user">
            <span class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;"><?php echo date('l, F d, Y'); ?></span>
            <div class="avatar"><?php echo substr($_SESSION['admin_name'], 0, 1); ?></div>
        </div>
    </div>

    <!-- SweetAlert2 Alert Messages -->
    <?php if($alert_message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $alert_type == 'success' ? 'success' : 'error'; ?>',
                title: '<?php echo $alert_type == 'success' ? 'Success!' : 'Error!'; ?>',
                text: '<?php echo $alert_message; ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonColor: '<?php echo $alert_type == 'success' ? '#006c49' : '#dc2626'; ?>'
            });
        });
    </script>
    <?php endif; ?>

    <?php if(isset($_GET['action']) && $_GET['action'] == 'add' || isset($_GET['edit'])): ?>
    <div class="card-custom mb-4">
        <div class="card-header">
            <?php echo isset($_GET['edit']) ? 'Edit Officer' : 'Add New Officer'; ?>
            <a href="officers.php" class="btn btn-secondary btn-sm float-end">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" id="officerForm">
                <?php if(isset($_GET['edit'])): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_officer['id']; ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="required-star">*</span></label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo $edit_officer['fullname'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email <span class="required-star">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo $edit_officer['email'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $edit_officer['phone'] ?? ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Role <span class="required-star">*</span></label>
                        <select name="role" class="form-select" id="roleSelect" onchange="toggleConditionalFields()" required>
                            <option value="">Select Role</option>
                            <option value="OFFICE" <?php echo (isset($edit_officer) && $edit_officer['role'] == 'OFFICE') ? 'selected' : ''; ?>>Office Officer</option>
                            <option value="DEPARTMENT" <?php echo (isset($edit_officer) && $edit_officer['role'] == 'DEPARTMENT') ? 'selected' : ''; ?>>Department Officer</option>
                            <option value="FACULTY" <?php echo (isset($edit_officer) && $edit_officer['role'] == 'FACULTY') ? 'selected' : ''; ?>>Faculty Officer</option>
                        </select>
                    </div>
                    
                    <!-- Office Field (only for OFFICE role) -->
                    <div class="col-md-6" id="officeField">
                        <label class="form-label fw-semibold">Office <span class="required-star">*</span></label>
                        <select name="office" class="office-dropdown form-select" id="officeSelect">
                            <option value="">Select Office</option>
                            <option value="BURSAR" <?php echo (isset($edit_officer) && isset($edit_officer['office']) && $edit_officer['office'] == 'BURSAR') ? 'selected' : ''; ?>>BURSAR</option>
                            <option value="ALUMNI RELATION DIVISION" <?php echo (isset($edit_officer) && isset($edit_officer['office']) && $edit_officer['office'] == 'ALUMNI RELATION DIVISION') ? 'selected' : ''; ?>>ALUMNI RELATION DIVISION</option>
                            <option value="LIBRARY" <?php echo (isset($edit_officer) && isset($edit_officer['office']) && $edit_officer['office'] == 'LIBRARY') ? 'selected' : ''; ?>>LIBRARY</option>
                            <option value="SPORT UNIT" <?php echo (isset($edit_officer) && isset($edit_officer['office']) && $edit_officer['office'] == 'SPORT UNIT') ? 'selected' : ''; ?>>SPORT UNIT</option>
                            <option value="HALL" <?php echo (isset($edit_officer) && isset($edit_officer['office']) && $edit_officer['office'] == 'HALL') ? 'selected' : ''; ?>>HALL</option>
                        </select>
                    </div>
                    
                    <!-- Conditional: Faculty and Department Fields (only for FACULTY and DEPARTMENT roles) -->
                    <div class="conditional-fields" id="facultyDepartmentFields">
                        <!-- Faculty Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Faculty <span class="required-star">*</span></label>
                            <select name="faculty_id" class="form-select" id="facultySelect" onchange="loadDepartments()">
                                <option value="">Select Faculty</option>
                                <?php 
                                $faculties_result = mysqli_query($conn, "SELECT id, faculty_name FROM faculties ORDER BY faculty_name ASC");
                                while($faculty = mysqli_fetch_assoc($faculties_result)): 
                                    $selected = '';
                                    if(isset($edit_officer) && isset($edit_officer['faculty_id']) && $edit_officer['faculty_id'] == $faculty['id']){
                                        $selected = 'selected';
                                    }
                                ?>
                                    <option value="<?php echo $faculty['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($faculty['faculty_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Department Selection (only for DEPARTMENT role) -->
                        <div class="mb-3" id="departmentField">
                            <label class="form-label fw-semibold">Department <span class="required-star">*</span></label>
                            <select name="department_id" class="form-select" id="departmentSelect">
                                <option value="">Select Department</option>
                                <?php 
                                if(isset($edit_officer) && isset($edit_officer['department_id']) && $edit_officer['department_id'] > 0){
                                    $dept_result = mysqli_query($conn, "SELECT id, department_name FROM departments WHERE id = '{$edit_officer['department_id']}'");
                                    while($dept = mysqli_fetch_assoc($dept_result)){
                                        echo '<option value="' . $dept['id'] . '" selected>' . htmlspecialchars($dept['department_name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <small class="text-muted" id="departmentHelp">Select a faculty first to load departments</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold"><?php echo isset($_GET['edit']) ? 'New Password (leave blank to keep current)' : 'Password <span class="required-star">*</span>'; ?></label>
                        <input type="password" name="password" class="form-control" <?php echo !isset($_GET['edit']) ? 'required' : ''; ?>>
                    </div>
                </div>
                <button type="submit" name="save_officer" class="btn btn-primary mt-3">
                    <i class="bi bi-check2"></i> <?php echo isset($_GET['edit']) ? 'Update Officer' : 'Add Officer'; ?>
                </button>
                <a href="officers.php" class="btn btn-secondary mt-3">Cancel</a>
            </form>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Office Officers Table -->
    <div class="card-custom mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-family:'Hanken Grotesk',sans-serif;"><i class="bi bi-person-badge"></i> Office Officers</span>
            <a href="officers.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Officer</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Office</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($officers) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($officers)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['office']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span class="role-badge office"><?php echo $row['role']; ?></span></td>
                                <td>
                                    <a href="officers.php?edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4" style="font-family:'Hanken Grotesk',sans-serif;">No office officers found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Faculty Officers Table -->
    <div class="card-custom mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-family:'Hanken Grotesk',sans-serif;"><i class="bi bi-mortarboard"></i> Faculty Officers</span>
            <a href="officers.php?action=add" class="btn btn-success btn-sm"><i class="bi bi-plus"></i> Add Faculty Officer</a>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($faculty_officers) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($faculty_officers)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td>
                                    <a href="officers.php?edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4" style="font-family:'Hanken Grotesk',sans-serif;">No faculty officers found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Department Officers Table -->
    <div class="card-custom">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-family:'Hanken Grotesk',sans-serif;"><i class="bi bi-building"></i> Department Officers</span>
            <a href="officers.php?action=add" class="btn btn-info btn-sm"><i class="bi bi-plus"></i> Add Department Officer</a>
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
                        <?php if(mysqli_num_rows($department_officers) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($department_officers)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                <td>
                                    <a href="officers.php?edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i></a>
                                    <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4" style="font-family:'Hanken Grotesk',sans-serif;">No department officers found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Toggle conditional fields based on role selection
function toggleConditionalFields() {
    var role = document.getElementById('roleSelect').value;
    var officeField = document.getElementById('officeField');
    var facultyDeptFields = document.getElementById('facultyDepartmentFields');
    var departmentField = document.getElementById('departmentField');
    var facultySelect = document.getElementById('facultySelect');
    var departmentSelect = document.getElementById('departmentSelect');
    
    // Hide all conditional fields first
    facultyDeptFields.style.display = 'none';
    officeField.style.display = 'none';
    facultyDeptFields.classList.remove('show');
    
    // Remove required attributes
    document.querySelector('select[name="office"]').required = false;
    facultySelect.required = false;
    departmentSelect.required = false;
    
    if(role === 'OFFICE'){
        // ONLY show office field - NO faculty or department
        officeField.style.display = 'block';
        document.querySelector('select[name="office"]').required = true;
    } else if(role === 'FACULTY'){
        // Show faculty field only (no department)
        facultyDeptFields.style.display = 'block';
        facultyDeptFields.classList.add('show');
        departmentField.style.display = 'none';
        facultySelect.required = true;
        departmentSelect.required = false;
        // Load departments (optional, but won't be visible)
        loadDepartments();
    } else if(role === 'DEPARTMENT'){
        // Show both faculty and department fields
        facultyDeptFields.style.display = 'block';
        facultyDeptFields.classList.add('show');
        departmentField.style.display = 'block';
        facultySelect.required = true;
        departmentSelect.required = true;
        // Load departments based on selected faculty
        loadDepartments();
    }
}

// Load departments based on selected faculty
function loadDepartments() {
    var facultyId = document.getElementById('facultySelect').value;
    var departmentSelect = document.getElementById('departmentSelect');
    var departmentHelp = document.getElementById('departmentHelp');
    
    // Clear existing options
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    
    if(facultyId === '') {
        departmentHelp.textContent = 'Select a faculty first to load departments';
        return;
    }
    
    departmentHelp.textContent = 'Loading departments...';
    
    // AJAX request to fetch departments
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'get-departments.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4 && xhr.status === 200) {
            try {
                var departments = JSON.parse(xhr.responseText);
                if(departments.length === 0) {
                    departmentHelp.textContent = 'No departments found for this faculty';
                } else {
                    departmentHelp.textContent = 'Select a department';
                    departments.forEach(function(dept) {
                        var option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = dept.department_name;
                        departmentSelect.appendChild(option);
                    });
                }
            } catch(e) {
                departmentHelp.textContent = 'Error loading departments';
                console.error('Error:', e);
            }
        }
    };
    xhr.send('faculty_id=' + encodeURIComponent(facultyId));
}

// SweetAlert2 Delete Confirmation
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#4b5563',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'officers.php?delete=' + id;
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleConditionalFields();
    
    // If editing and faculty is selected, load departments
    var facultySelect = document.getElementById('facultySelect');
    if(facultySelect && facultySelect.value !== '') {
        loadDepartments();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>