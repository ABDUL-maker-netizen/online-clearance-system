<?php
session_start();

include '../includes/config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

// Handle Delete from admission_list
if(isset($_GET['delete']) && is_numeric($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM admission_list WHERE id='$id'");
    $_SESSION['alert_message'] = "Student record deleted successfully.";
    $_SESSION['alert_type'] = 'success';
    header("Location: students.php");
    exit();
}

// Handle CSV Import
if(isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0){
    $file = $_FILES['csv_file']['tmp_name'];
    $filename = $_FILES['csv_file']['name'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Check if file is CSV
    if($extension != 'csv'){
        $_SESSION['alert_message'] = "Please upload a valid CSV file.";
        $_SESSION['alert_type'] = 'error';
        header("Location: students.php");
        exit();
    }
    
    // Open and read CSV file
    $handle = fopen($file, 'r');
    if($handle === false){
        $_SESSION['alert_message'] = "Failed to open CSV file.";
        $_SESSION['alert_type'] = 'error';
        header("Location: students.php");
        exit();
    }
    
    // Read header row
    $header = fgetcsv($handle);
    
    // Expected header columns
    $expected_headers = ['fullname', 'reg_number', 'faculty_name', 'department', 'programme', 'degree_awarded'];
    $header_map = [];
    
    // Map headers to database columns
    foreach($expected_headers as $col){
        $found = false;
        foreach($header as $index => $h){
            if(strtolower(trim($h)) == $col){
                $header_map[$col] = $index;
                $found = true;
                break;
            }
        }
        if(!$found){
            $_SESSION['alert_message'] = "CSV file must contain column: '$col'. Please check your file format.";
            $_SESSION['alert_type'] = 'error';
            fclose($handle);
            header("Location: students.php");
            exit();
        }
    }
    
    // Process rows
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    $error_messages = [];
    
    while(($row = fgetcsv($handle)) !== false){
        // Skip empty rows
        if(empty(array_filter($row))) continue;
        
        $fullname = mysqli_real_escape_string($conn, trim($row[$header_map['fullname']] ?? ''));
        $reg_number = mysqli_real_escape_string($conn, trim($row[$header_map['reg_number']] ?? ''));
        $faculty_name = mysqli_real_escape_string($conn, trim($row[$header_map['faculty_name']] ?? ''));
        $department = mysqli_real_escape_string($conn, trim($row[$header_map['department']] ?? ''));
        $programme = mysqli_real_escape_string($conn, trim($row[$header_map['programme']] ?? ''));
        $degree_awarded = mysqli_real_escape_string($conn, trim($row[$header_map['degree_awarded']] ?? ''));
        
        // Validate required fields
        if(empty($fullname) || empty($reg_number)){
            $errors++;
            $error_messages[] = "Row " . ($imported + $skipped + $errors + 1) . ": Missing name or registration number.";
            continue;
        }
        
        // Check if reg_number already exists
        $check = mysqli_query($conn, "SELECT id FROM admission_list WHERE reg_number='$reg_number' LIMIT 1");
        if(mysqli_num_rows($check) > 0){
            $skipped++;
            continue;
        }
        
        // Insert into admission_list
        $query = "
            INSERT INTO admission_list 
            (fullname, reg_number, faculty_name, department, programme, degree_awarded, status)
            VALUES 
            ('$fullname', '$reg_number', '$faculty_name', '$department', '$programme', '$degree_awarded', 'admitted')
        ";
        
        if(mysqli_query($conn, $query)){
            $imported++;
        } else {
            $errors++;
            $error_messages[] = "Row " . ($imported + $skipped + $errors + 1) . ": Database error - " . mysqli_error($conn);
        }
    }
    
    fclose($handle);
    
    // Build result message
    $message = "CSV Import Complete: ";
    $message .= "$imported records imported successfully.";
    if($skipped > 0){
        $message .= " $skipped records skipped (duplicate registration numbers).";
    }
    if($errors > 0){
        $message .= " $errors records failed.";
    }
    
    $_SESSION['alert_message'] = $message;
    $_SESSION['alert_type'] = ($errors > 0) ? 'error' : 'success';
    header("Location: students.php");
    exit();
}

// Handle Add/Edit in admission_list
if(isset($_POST['save_student'])){
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $reg_number = mysqli_real_escape_string($conn, $_POST['reg_number']);
    $faculty_id = isset($_POST['faculty_id']) ? intval($_POST['faculty_id']) : 0;
    $faculty_name = mysqli_real_escape_string($conn, $_POST['faculty_name']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $programme = mysqli_real_escape_string($conn, $_POST['programme']);
    $degree_awarded = mysqli_real_escape_string($conn, $_POST['degree_awarded']);
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'admitted');
    
    if($id > 0){
        // Update existing record
        $query = "
            UPDATE admission_list SET 
                fullname='$fullname', 
                reg_number='$reg_number',
                faculty_id='$faculty_id',
                faculty_name='$faculty_name', 
                department='$department', 
                programme='$programme',
                degree_awarded='$degree_awarded',
                status='$status'
            WHERE id='$id'
        ";
        mysqli_query($conn, $query);
        $_SESSION['alert_message'] = "Student record updated successfully.";
        $_SESSION['alert_type'] = 'success';
    } else {
        // Insert new record
        // Check if reg_number already exists
        $check = mysqli_query($conn, "SELECT id FROM admission_list WHERE reg_number='$reg_number' LIMIT 1");
        if(mysqli_num_rows($check) > 0){
            $_SESSION['alert_message'] = "Registration number already exists in admission list.";
            $_SESSION['alert_type'] = 'error';
            header("Location: students.php");
            exit();
        }
        
        $query = "
            INSERT INTO admission_list 
            (fullname, reg_number, faculty_id, faculty_name, department, programme, degree_awarded, status)
            VALUES 
            ('$fullname', '$reg_number', '$faculty_id', '$faculty_name', '$department', '$programme', '$degree_awarded', '$status')
        ";
        mysqli_query($conn, $query);
        $_SESSION['alert_message'] = "Student added to admission list successfully.";
        $_SESSION['alert_type'] = 'success';
    }
    header("Location: students.php");
    exit();
}

// Get all students from admission_list
$students = mysqli_query($conn, "SELECT * FROM admission_list ORDER BY id DESC");
$edit_student = null;
if(isset($_GET['edit']) && is_numeric($_GET['edit'])){
    $edit_id = intval($_GET['edit']);
    $edit_student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admission_list WHERE id='$edit_id'"));
}

// Get all faculties for dropdown
$faculties = mysqli_query($conn, "SELECT id, faculty_name FROM faculties ORDER BY faculty_name ASC");

// Get departments for dropdown (all)
$departments = mysqli_query($conn, "SELECT id, department_name, faculty_id FROM departments ORDER BY department_name ASC");

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
    <title>Manage Students - Admission List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
        .btn-outline-secondary{color:#4b5563;border-color:#4b5563;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-outline-secondary:hover{background:#4b5563;color:#fff;transform:scale(0.95);}
        .btn-sm{padding:4px 12px;font-size:12px;border-radius:6px;}
        .table th{background:#f8f9fa;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-family:'Hanken Grotesk',sans-serif;}
        .table td{vertical-align:middle;font-family:'Hanken Grotesk',sans-serif;}
        .status-badge{padding:3px 12px;border-radius:20px;font-size:12px;font-weight:500;font-family:'Hanken Grotesk',sans-serif;}
        .status-badge.admitted{background:#d1fae5;color:#065f46;}
        .status-badge.pending{background:#fef3c7;color:#92400e;}
        .status-badge.registered{background:#e8f0fe;color:#1e40af;}
        .action-buttons{display:flex;gap:4px;flex-wrap:wrap;}
        .csv-import-box{background:#f8f9fa;border:2px dashed #e5e7eb;border-radius:8px;padding:20px;text-align:center;}
        .csv-import-box .icon{font-size:48px;color:#4b5563;}
        .alert-info{background:#e8f0fe;color:#1e40af;border-color:#b4c5ff;}
        .alert-info .btn{color:#fff;}
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
    <a href="students.php" class="nav-link active"><i class="bi bi-people"></i> Students (Admission List)</a>
    <a href="officers.php" class="nav-link"><i class="bi bi-person-badge"></i> Officers</a>
    <a href="logout.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- Top Bar -->
    <div class="top-bar">
        <h5 class="mb-0 fw-bold" style="font-family:'Hanken Grotesk',sans-serif;">Manage Students (Admission List)</h5>
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

    <!-- Add/Edit Form (with CSV Import) -->
    <?php if(isset($_GET['action']) && $_GET['action'] == 'add' || isset($_GET['edit'])): ?>
    <div class="card-custom mb-4">
        <div class="card-header">
            <?php echo isset($_GET['edit']) ? 'Edit Student Record' : 'Add New Student to Admission List'; ?>
            <a href="students.php" class="btn btn-secondary btn-sm float-end">Back</a>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                <strong>Note:</strong> Students added here will appear in the Admission List. Students can register themselves using the student registration page.
            </div>

            <?php if(!isset($_GET['edit'])): ?>
            <!-- CSV Import Section (Only visible on Add page) -->
            <div class="csv-import-box mb-4">
                <i class="bi bi-file-earmark-spreadsheet icon"></i>
                <h6 class="mt-3" style="font-family:'Hanken Grotesk',sans-serif;">Or Import Multiple Students via CSV</h6>
                <p class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;">
                    Upload a CSV file to import multiple students into the admission list at once.
                </p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center" onsubmit="return confirmCSVImport()">
                            <div class="col-md-8">
                                <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="import_csv" class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-upload"></i> Import CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="downloadCSVTemplate()">
                        <i class="bi bi-download"></i> Download CSV Template
                    </button>
                    <small class="text-muted d-block mt-2" style="font-family:'Hanken Grotesk',sans-serif;">
                        <i class="bi bi-info-circle"></i> 
                        Required columns: fullname, reg_number, faculty_name, department, programme, degree_awarded
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="studentForm">
                <?php if(isset($_GET['edit'])): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_student['id']; ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control" value="<?php echo $edit_student['fullname'] ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Registration Number <span class="text-danger">*</span></label>
                        <input type="text" name="reg_number" class="form-control" value="<?php echo $edit_student['reg_number'] ?? ''; ?>" required>
                    </div>
                    
                    <!-- Faculty Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Faculty <span class="text-danger">*</span></label>
                        <select name="faculty_id" class="form-select" id="facultySelect" required>
                            <option value="">Select Faculty</option>
                            <?php 
                            $faculties_result = mysqli_query($conn, "SELECT id, faculty_name FROM faculties ORDER BY faculty_name ASC");
                            while($faculty = mysqli_fetch_assoc($faculties_result)):
                                $selected = '';
                                if(isset($edit_student) && $edit_student['faculty_id'] == $faculty['id']){
                                    $selected = 'selected';
                                }
                            ?>
                                <option value="<?php echo $faculty['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($faculty['faculty_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted" id="facultyHelp">Select a faculty</small>
                    </div>
                    
                    <!-- Faculty Name (auto-filled) -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Faculty Name <span class="text-danger">*</span></label>
                        <input type="text" name="faculty_name" id="facultyNameInput" class="form-control" value="<?php echo $edit_student['faculty_name'] ?? ''; ?>" required readonly>
                        <small class="text-muted">Auto-filled from faculty selection</small>
                    </div>
                    
                    <!-- Department Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                        <select name="department" id="departmentSelect" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php 
                            if(isset($edit_student) && !empty($edit_student['department'])){
                                // If editing, show the current department
                                echo '<option value="' . htmlspecialchars($edit_student['department']) . '" selected>' . htmlspecialchars($edit_student['department']) . '</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted" id="departmentHelp">Select a faculty first to load departments</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Programme</label>
                        <input type="text" name="programme" class="form-control" value="<?php echo $edit_student['programme'] ?? ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Degree Awarded</label>
                        <input type="text" name="degree_awarded" class="form-control" value="<?php echo $edit_student['degree_awarded'] ?? ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="admitted" <?php echo (isset($edit_student) && $edit_student['status'] == 'admitted') ? 'selected' : ''; ?>>Admitted</option>
                            <option value="pending" <?php echo (isset($edit_student) && $edit_student['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="registered" <?php echo (isset($edit_student) && $edit_student['status'] == 'registered') ? 'selected' : ''; ?>>Registered</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="save_student" class="btn btn-primary mt-3">
                    <i class="bi bi-check2"></i> <?php echo isset($_GET['edit']) ? 'Update Record' : 'Add to Admission List'; ?>
                </button>
                <a href="students.php" class="btn btn-secondary mt-3">Cancel</a>
            </form>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Students Table - From admission_list -->
    <div class="card-custom">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-family:'Hanken Grotesk',sans-serif;"><i class="bi bi-people"></i> Admission List (<?php echo mysqli_num_rows($students); ?> records)</span>
            <div>
                <a href="students.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Student</a>
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
                        <?php if(mysqli_num_rows($students) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($students)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['programme']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="students.php?edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4" style="font-family:'Hanken Grotesk',sans-serif;">No students found in admission list</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent">
            <small class="text-muted" style="font-family:'Hanken Grotesk',sans-serif;">
                <i class="bi bi-info-circle"></i> 
                Total: <?php echo mysqli_num_rows($students); ?> students in admission list
            </small>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// ============================================================
// Load Departments based on selected Faculty
// ============================================================
function loadDepartments(facultyId, selectedDept) {
    var departmentSelect = document.getElementById('departmentSelect');
    var departmentHelp = document.getElementById('departmentHelp');
    
    // Clear existing options
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    
    if(!facultyId) {
        departmentHelp.textContent = 'Select a faculty first to load departments';
        return;
    }
    
    departmentHelp.textContent = 'Loading departments...';
    
    // AJAX request to fetch departments
    $.ajax({
        url: 'get-departments.php',
        method: 'POST',
        data: { faculty_id: facultyId },
        dataType: 'json',
        success: function(departments) {
            if(departments.length === 0) {
                departmentHelp.textContent = 'No departments found for this faculty';
            } else {
                departmentHelp.textContent = 'Select a department';
                departments.forEach(function(dept) {
                    var option = document.createElement('option');
                    option.value = dept.department_name;
                    option.textContent = dept.department_name;
                    if(selectedDept && selectedDept === dept.department_name) {
                        option.selected = true;
                    }
                    departmentSelect.appendChild(option);
                });
            }
        },
        error: function() {
            departmentHelp.textContent = 'Error loading departments';
            console.error('Error loading departments');
        }
    });
}

// ============================================================
// Load Faculty Name based on selected Faculty
// ============================================================
function loadFacultyName(facultyId) {
    var facultyNameInput = document.getElementById('facultyNameInput');
    var facultyHelp = document.getElementById('facultyHelp');
    
    if(!facultyId) {
        facultyNameInput.value = '';
        facultyHelp.textContent = 'Select a faculty';
        return;
    }
    
    facultyHelp.textContent = 'Loading faculty name...';
    
    $.ajax({
        url: 'get-faculty-name.php',
        method: 'POST',
        data: { faculty_id: facultyId },
        dataType: 'json',
        success: function(data) {
            if(data && data.faculty_name) {
                facultyNameInput.value = data.faculty_name;
                facultyHelp.textContent = 'Faculty name auto-filled';
            } else {
                facultyNameInput.value = '';
                facultyHelp.textContent = 'Faculty name not found';
            }
        },
        error: function() {
            facultyHelp.textContent = 'Error loading faculty name';
            console.error('Error loading faculty name');
        }
    });
}

// ============================================================
// Faculty Selection Change Handler
// ============================================================
$(document).ready(function() {
    $('#facultySelect').on('change', function() {
        var facultyId = $(this).val();
        var selectedDept = '<?php echo isset($edit_student) ? addslashes($edit_student['department']) : ''; ?>';
        
        loadDepartments(facultyId, selectedDept);
        loadFacultyName(facultyId);
    });
    
    // If editing, load departments on page load
    <?php if(isset($edit_student) && $edit_student['faculty_id'] > 0): ?>
    var facultyId = <?php echo $edit_student['faculty_id']; ?>;
    var selectedDept = '<?php echo addslashes($edit_student['department']); ?>';
    loadDepartments(facultyId, selectedDept);
    <?php endif; ?>
});

// ============================================================
// SweetAlert2 Delete Confirmation
// ============================================================
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this! This will remove the student from the admission list.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#4b5563',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'students.php?delete=' + id;
        }
    });
}

// ============================================================
// CSV Import Confirmation
// ============================================================
function confirmCSVImport() {
    var fileInput = document.querySelector('input[name="csv_file"]');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No File Selected',
            text: 'Please select a CSV file to import.',
            confirmButtonColor: '#0ea5e9'
        });
        return false;
    }
    
    var fileName = fileInput.files[0].name;
    var fileSize = (fileInput.files[0].size / 1024).toFixed(2);
    
    Swal.fire({
        title: 'Import CSV File?',
        html: 'You are about to import <strong>' + fileName + '</strong> (' + fileSize + ' KB).<br><br>This will add new students to the admission list.<br>Duplicate registration numbers will be skipped.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#006c49',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Import!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            return true;
        }
        return false;
    });
}

// ============================================================
// Download CSV Template
// ============================================================
function downloadCSVTemplate() {
    var csvContent = "fullname,reg_number,faculty_name,department,programme,degree_awarded\n";
    csvContent += "John Doe,20/12345U/1,Faculty of Science,Computer Science,B.Sc Computer Science,B.Sc Computer Science\n";
    csvContent += "Jane Smith,20/67890U/1,Faculty of Arts,English,B.A English,B.A English\n";
    csvContent += "Bob Johnson,20/54321U/1,Faculty of Engineering,Mechanical Engineering,B.Eng Mechanical Engineering,B.Eng Mechanical Engineering\n";
    
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'admission_list_template.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ============================================================
// Form Validation
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('studentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            var regNumber = document.querySelector('input[name="reg_number"]');
            var facultySelect = document.getElementById('facultySelect');
            var departmentSelect = document.getElementById('departmentSelect');
            
            if (regNumber && !regNumber.value.trim()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Registration Number Required',
                    text: 'Please enter a registration number.',
                    confirmButtonColor: '#0ea5e9'
                });
                return false;
            }
            
            if (facultySelect && !facultySelect.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Faculty Required',
                    text: 'Please select a faculty.',
                    confirmButtonColor: '#0ea5e9'
                });
                return false;
            }
            
            if (departmentSelect && !departmentSelect.value) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Department Required',
                    text: 'Please select a department.',
                    confirmButtonColor: '#0ea5e9'
                });
                return false;
            }
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>