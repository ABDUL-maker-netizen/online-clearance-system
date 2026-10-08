<?php
session_start();

include '../includes/config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];

// Get counts from admission_list instead of students table
$students_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM admission_list"))['count'] ?? 0;
$officers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM officers"))['count'] ?? 0;
$faculty_officers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM faculty_officers"))['count'] ?? 0;
$dept_officers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM department_officers"))['count'] ?? 0;
$admission_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM admission_list"))['count'] ?? 0;

// ============================================================
// CLEARANCE PROGRESS STATISTICS
// ============================================================

// Get total students who have started clearance (have clearance_status records)
$total_started = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT student_id) as total 
    FROM clearance_status
"))['total'] ?? 0;

// Get total cleared students (all offices approved)
$total_cleared = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM students 
    WHERE LOWER(status) = 'cleared'
"))['total'] ?? 0;

// Get total pending students (started but not cleared)
$total_pending = $total_started - $total_cleared;

// Get total rejected students
$total_rejected = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM students 
    WHERE LOWER(status) = 'rejected'
"))['total'] ?? 0;

// Get not started students (in admission list but no clearance status)
$total_not_started = $students_count - $total_started;

// ============================================================
// OFFICE-WISE APPROVAL STATISTICS
// ============================================================

$office_stats = [];
$office_query = mysqli_query($conn, "
    SELECT 
        cs.department_role,
        COUNT(DISTINCT cs.student_id) as total_students,
        SUM(CASE WHEN cs.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN cs.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN cs.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM clearance_status cs
    GROUP BY cs.department_role
    ORDER BY cs.department_role ASC
");

while($row = mysqli_fetch_assoc($office_query)){
    $office_stats[] = $row;
}

// ============================================================
// GET CLEARED STUDENTS WITH SIGNED DOCUMENTS - FIXED TO PREVENT DUPLICATES
// ============================================================

// Get students who are fully cleared and have signed documents
// Using GROUP BY on student id to ensure one record per student
$cleared_students_query = mysqli_query($conn, "
    SELECT 
        s.id,
        s.fullname,
        s.reg_number,
        s.faculty_name,
        s.department,
        s.status,
        MAX(dl.signed_file) as signed_file,
        MAX(dl.signed_at) as signed_at,
        MAX(dl.officer_name) as officer_name,
        MAX(dl.signature_file) as signature_file,
        MAX(cs.approved_at) as last_approval
    FROM students s
    INNER JOIN clearance_status cs ON s.id = cs.student_id
    INNER JOIN department_letters dl ON s.id = dl.student_id
    WHERE LOWER(s.status) = 'cleared'
    AND dl.status = 'signed'
    AND dl.signed_file IS NOT NULL
    AND dl.signed_at IS NOT NULL
    AND dl.signature_file IS NOT NULL
    GROUP BY s.id, s.fullname, s.reg_number, s.faculty_name, s.department, s.status
    ORDER BY s.fullname ASC
");

$cleared_students = [];
while($row = mysqli_fetch_assoc($cleared_students_query)){
    $cleared_students[] = $row;
}

// ============================================================
// RECENT ACTIVITY - Get all for DataTables (no limit)
// ============================================================

$recent_activities = mysqli_query($conn, "
    SELECT 
        cs.*,
        s.fullname,
        s.reg_number
    FROM clearance_status cs
    INNER JOIN students s ON s.id = cs.student_id
    WHERE cs.approved_at IS NOT NULL
    ORDER BY cs.approved_at DESC
");

// Store alert message
$alert_message = isset($_SESSION['alert_message']) ? $_SESSION['alert_message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;
unset($_SESSION['alert_message']);
unset($_SESSION['alert_type']);

// Function to get status badge color
function getStatusBadge($status) {
    $status = strtolower($status);
    if($status == 'cleared' || $status == 'approved'){
        return 'success';
    } elseif($status == 'rejected'){
        return 'danger';
    } else {
        return 'warning';
    }
}

// Function to check if student has signed documents
function hasSignedDocuments($student_id, $conn) {
    $check = mysqli_query($conn, "
        SELECT id FROM department_letters
        WHERE student_id = '$student_id'
        AND status = 'signed'
        AND signed_file IS NOT NULL
        AND signed_at IS NOT NULL
        AND signature_file IS NOT NULL
        LIMIT 1
    ");
    return mysqli_num_rows($check) > 0;
}

// Function to get student's signed letter file
function getSignedLetterFile($student_id, $conn) {
    $query = mysqli_query($conn, "
        SELECT signed_file, signed_at, officer_name 
        FROM department_letters
        WHERE student_id = '$student_id'
        AND status = 'signed'
        AND signed_file IS NOT NULL
        LIMIT 1
    ");
    return mysqli_fetch_assoc($query);
}

// Build valid students data for JavaScript (cleaned)
$valid_students_for_js = [];
foreach($cleared_students as $student){
    $signed_letter = getSignedLetterFile($student['id'], $conn);
    if($signed_letter && !empty($signed_letter['signed_file'])){
        $valid_students_for_js[] = [
            'id' => $student['id'],
            'name' => trim(preg_replace('/\s+/', ' ', $student['fullname'])),
            'file' => $signed_letter['signed_file']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <style>
        :root {
            --sidebar-width: 260px;
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --bg: #f8f9ff;
        }
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
        .stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);border:1px solid #e5e7eb;transition:transform 0.2s;}
        .stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.08);}
        .stat-card .number{font-size:32px;font-weight:700;color:#111827;font-family:'Hanken Grotesk',sans-serif;}
        .stat-card .label{color:#4b5563;font-size:14px;font-family:'Hanken Grotesk',sans-serif;}
        .stat-card .icon{font-size:32px;opacity:0.3;}
        .stat-card .number.text-primary{color:#1e40af;}
        .stat-card .number.text-success{color:#006c49;}
        .stat-card .number.text-warning{color:#f59e0b;}
        .stat-card .number.text-danger{color:#dc2626;}
        .stat-card .number.text-secondary{color:#4b5563;}
        .stat-card .number.text-info{color:#0ea5e9;}
        
        .card-custom{background:#fff;border-radius:12px;border:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);}
        .card-custom .card-header{background:transparent;border-bottom:1px solid #e5e7eb;padding:16px 20px;font-weight:600;font-family:'Hanken Grotesk',sans-serif;}
        .card-custom .card-body{padding:20px;}
        .card-custom .card-body.p-0{padding:0;}
        
        .btn-primary{background:var(--primary);border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-primary:hover{background:var(--primary-dark);transform:scale(0.95);}
        .btn-success{background:#006c49;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-success:hover{background:#005236;transform:scale(0.95);}
        .btn-info{background:#0ea5e9;border:none;font-family:'Hanken Grotesk',sans-serif;transition:all 0.15s ease;}
        .btn-info:hover{background:#0284c7;transform:scale(0.95);}
        .btn-sm{padding:4px 12px;font-size:12px;border-radius:6px;}
        
        .status-badge{padding:3px 12px;border-radius:20px;font-size:12px;font-weight:500;font-family:'Hanken Grotesk',sans-serif;}
        .status-badge.success{background:#d1fae5;color:#065f46;}
        .status-badge.warning{background:#fef3c7;color:#92400e;}
        .status-badge.danger{background:#fee2e2;color:#991b1b;}
        .status-badge.info{background:#e8f0fe;color:#1e40af;}
        
        .progress-track{height:8px;border-radius:9999px;overflow:hidden;background:#e5e7eb;}
        .progress-fill{height:100%;border-radius:9999px;transition:width 0.5s ease;}
        
        .office-stat-card{background:#f8f9fa;border-radius:8px;padding:12px 16px;border:1px solid #e5e7eb;margin-bottom:8px;}
        .office-stat-card .office-name{font-weight:600;font-size:14px;color:#111827;font-family:'Hanken Grotesk',sans-serif;}
        .office-stat-card .office-counts{font-size:13px;display:flex;gap:16px;flex-wrap:wrap;margin-top:4px;font-family:'Hanken Grotesk',sans-serif;}
        .office-stat-card .office-counts .approved{color:#006c49;}
        .office-stat-card .office-counts .pending{color:#f59e0b;}
        .office-stat-card .office-counts .rejected{color:#dc2626;}
        
        /* DataTables custom styles */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px 12px;
            margin-left: 8px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(30,64,175,0.15);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 4px 8px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            margin: 0 2px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary);
            color: #fff !important;
            border-color: var(--primary);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e5e7eb;
            border-color: #d0d0d0;
        }
        .dataTables_info {
            font-size: 14px;
            color: #4b5563;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .table.dataTable {
            margin-bottom: 0 !important;
        }
        .table.dataTable thead th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .table tbody td {
            font-family:'Hanken Grotesk',sans-serif;
        }
        
        /* Cleared Students Card Styles */
        .cleared-student-card {
            background: #f0f7ff;
            border: 1px solid #cce5ff;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .cleared-student-card:hover {
            background: #e3f0ff;
            border-color: #99caff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .cleared-student-card .student-name {
            font-weight: 600;
            color: #111827;
            font-size: 15px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .cleared-student-card .student-reg {
            color: #4b5563;
            font-size: 13px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .cleared-student-card .student-detail {
            color: #4b5563;
            font-size: 12px;
            display: inline-block;
            margin-right: 4px;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .cleared-student-card .student-detail-separator {
            color: #9ca3af;
            font-size: 12px;
            margin: 0 2px;
        }
        .cleared-student-card .signed-badge {
            background: #d1fae5;
            color: #065f46;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-block;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .cleared-student-card .signed-badge i {
            font-size: 10px;
        }
        .cleared-student-card .info-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 2px;
        }
        .btn-print-slip {
            background: #006c49;
            color: #fff;
            padding: 5px 14px;
            border-radius: 4px;
            border: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .btn-print-slip:hover {
            background: #005236;
            color: #fff;
            transform: scale(1.05);
        }
        .btn-print-slip i {
            font-size: 14px;
        }
        .btn-print-slip:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-download-signed {
            background: #0ea5e9;
            color: #fff;
            padding: 5px 14px;
            border-radius: 4px;
            border: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .btn-download-signed:hover {
            background: #0284c7;
            color: #fff;
            transform: scale(1.05);
        }
        .btn-download-signed i {
            font-size: 14px;
        }
        
        /* Print all button */
        .btn-print-all {
            background: #0ea5e9;
            color: #fff;
            padding: 6px 16px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .btn-print-all:hover {
            background: #0284c7;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-print-all i {
            font-size: 16px;
        }
        
        /* Download all button */
        .btn-download-all {
            background: #006c49;
            color: #fff;
            padding: 6px 16px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .btn-download-all:hover {
            background: #005236;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-download-all i {
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .card-footer-info {
            background: #f8f9fa;
            padding: 10px 16px;
            border-radius: 0 0 8px 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #4b5563;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            font-family:'Hanken Grotesk',sans-serif;
        }
        .card-footer-info .badge-info {
            background: #e8f0fe;
            color: #1e40af;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            font-family:'Hanken Grotesk',sans-serif;
        }
        
        @media(max-width:768px){.sidebar{width:100%;height:auto;position:relative;}.main-content{margin-left:0;}.stat-card .number{font-size:24px;}}
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand"><i class="bi bi-shield-lock"></i> Admin Panel</div>
    <a href="dashboard.php" class="nav-link active"><i class="bi bi-grid"></i> Dashboard</a>
    <a href="students.php" class="nav-link"><i class="bi bi-people"></i> Students (Admission List)</a>
    <a href="officers.php" class="nav-link"><i class="bi bi-person-badge"></i> Officers</a>
    <a href="logout.php" class="nav-link" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Top Bar -->
    <div class="top-bar">
        <h5 class="mb-0 fw-bold" style="font-family:'Hanken Grotesk',sans-serif;">Welcome, <?php echo htmlspecialchars($admin_name); ?></h5>
        <div class="user">
            <span class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;"><?php echo date('l, F d, Y'); ?></span>
            <div class="avatar"><?php echo substr($admin_name, 0, 1); ?></div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($alert_message){ ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $alert_type == 'success' ? 'success' : 'error'; ?>',
                title: '<?php echo $alert_type == 'success' ? 'Success' : 'Error'; ?>',
                text: '<?php echo $alert_message; ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonColor: '#1e40af'
            });
        });
    </script>
    <?php } ?>

    <!-- ============================================================
         CLEARED STUDENTS WITH PRINT & DOWNLOAD BUTTONS
    ============================================================ -->
    <?php if(!empty($cleared_students)): ?>
    <div class="card-custom mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-check-circle-fill text-success me-2"></i> 
                Fully Cleared Students (Signed Documents)
                <span class="badge bg-success ms-2" style="background:#006c49 !important;"><?php echo count($cleared_students); ?> Students</span>
            </span>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn-download-all" onclick="downloadAllLetters()">
                    <i class="bi bi-download"></i> Download All Letters
                </button>
                <button type="button" class="btn-print-all" onclick="downloadAllSlips()">
                    <i class="bi bi-download"></i> Download All Slips
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach($cleared_students as $student): 
                    $signed_letter = getSignedLetterFile($student['id'], $conn);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="cleared-student-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex:1; min-width:0;">
                                <div class="student-name"><?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $student['fullname']))); ?></div>
                                <div class="student-reg"><?php echo htmlspecialchars($student['reg_number']); ?></div>
                                
                                <!-- Horizontal details row -->
                                <div class="info-row mt-1">
                                    <span class="student-detail"><?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $student['faculty_name']))); ?></span>
                                    <span class="student-detail-separator">|</span>
                                    <span class="student-detail"><?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $student['department']))); ?></span>
                                    <span class="student-detail-separator">|</span>
                                    <span class="signed-badge">
                                        <i class="bi bi-check-circle"></i> Signed
                                    </span>
                                </div>
                                
                                <!-- Signed date and officer - horizontal -->
                                <div class="info-row mt-1" style="font-size:11px; color:#4b5563;">
                                    <?php if($student['signed_at']): ?>
                                    <span><i class="bi bi-clock"></i> <?php echo date('M d, Y', strtotime($student['signed_at'])); ?></span>
                                    <?php endif; ?>
                                    <?php if($student['officer_name']): ?>
                                    <span class="student-detail-separator">|</span>
                                    <span><i class="bi bi-person-check"></i> <?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $student['officer_name']))); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <?php if($signed_letter && !empty($signed_letter['signed_file'])): ?>
                                <a href="../assets/uploads/department_letters/<?php echo $signed_letter['signed_file']; ?>" 
                                   target="_blank" 
                                   download
                                   class="btn-download-signed"
                                   title="Download Signed Letter">
                                    <i class="bi bi-download"></i> Letter
                                </a>
                                <?php endif; ?>
                                <a href="../student/print-slip.php?student_id=<?php echo $student['id']; ?>" 
                                   target="_blank" 
                                   class="btn-print-slip"
                                   title="Print Clearance Slip">
                                    <i class="bi bi-printer"></i> Print
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Footer info -->
            <div class="card-footer-info">
                <span><i class="bi bi-info-circle"></i> Students fully cleared with signed department letters</span>
                <span class="badge-info"><i class="bi bi-printer"></i> Print - Opens clearance slip</span>
                <span class="badge-info"><i class="bi bi-download"></i> Letter - Downloads signed letter</span>
                <span class="badge-info"><i class="bi bi-download"></i> Download All - Get ZIP files</span>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card-custom mb-4">
        <div class="card-header">
            <i class="bi bi-check-circle-fill text-success me-2"></i> 
            Cleared Students
        </div>
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-inbox" style="font-size:48px;display:block;margin-bottom:12px;opacity:0.3;"></i>
            No students have been fully cleared and signed their documents yet.
            <div class="small mt-2">Students will appear here once they are fully cleared and have signed their department letter.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         CLEARANCE PROGRESS STATS
    ============================================================ -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="number text-primary"><?php echo $total_started; ?></div>
                        <div class="label">Started Clearance</div>
                    </div>
                    <i class="bi bi-play-circle icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="number text-success"><?php echo $total_cleared; ?></div>
                        <div class="label">Fully Cleared</div>
                    </div>
                    <i class="bi bi-check-circle icon text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="number text-warning"><?php echo $total_pending; ?></div>
                        <div class="label">In Progress</div>
                    </div>
                    <i class="bi bi-clock icon text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="number text-danger"><?php echo $total_rejected; ?></div>
                        <div class="label">Rejected</div>
                    </div>
                    <i class="bi bi-x-circle icon text-danger"></i>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="number text-secondary"><?php echo $total_not_started; ?></div>
                        <div class="label">Not Started</div>
                    </div>
                    <i class="bi bi-hourglass-split icon text-secondary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="number">
                            <?php 
                            $clearance_rate = ($total_started > 0) ? round(($total_cleared / $total_started) * 100) : 0;
                            echo $clearance_rate . '%';
                            ?>
                        </div>
                        <div class="label">Clearance Rate</div>
                    </div>
                    <i class="bi bi-graph-up-arrow icon text-info"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         PROGRESS BAR - Overall Clearance Progress
    ============================================================ -->
    <div class="card-custom mb-4">
        <div class="card-header">
            <i class="bi bi-bar-chart-fill me-2"></i> Overall Clearance Progress
            <span class="badge bg-secondary float-end" style="background:#4b5563 !important;"><?php echo $total_cleared; ?>/<?php echo $students_count; ?> Cleared</span>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;">Progress</span>
                <span class="fw-bold" style="font-family:'Hanken Grotesk',sans-serif;"><?php echo ($students_count > 0) ? round(($total_cleared / $students_count) * 100) : 0; ?>%</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width: <?php echo ($students_count > 0) ? round(($total_cleared / $students_count) * 100) : 0; ?>%; background: linear-gradient(90deg, #1e40af, #10b981);"></div>
            </div>
            <div class="d-flex justify-content-between mt-3 text-muted small" style="font-family:'Hanken Grotesk',sans-serif;">
                <span><i class="bi bi-people"></i> Total Students: <?php echo $students_count; ?></span>
                <span><i class="bi bi-check-circle text-success"></i> Cleared: <?php echo $total_cleared; ?></span>
                <span><i class="bi bi-clock text-warning"></i> In Progress: <?php echo $total_pending; ?></span>
                <span><i class="bi bi-x-circle text-danger"></i> Rejected: <?php echo $total_rejected; ?></span>
            </div>
        </div>
    </div>

    <!-- ============================================================
         OFFICE-WISE STATISTICS
    ============================================================ -->
    <div class="card-custom mb-4">
        <div class="card-header">
            <i class="bi bi-building me-2"></i> Office-Wise Clearance Status
        </div>
        <div class="card-body">
            <div class="row">
                <?php if(count($office_stats) > 0): ?>
                    <?php foreach($office_stats as $office): ?>
                    <div class="col-md-4">
                        <div class="office-stat-card">
                            <div class="office-name"><?php echo htmlspecialchars($office['department_role']); ?></div>
                            <div class="office-counts">
                                <span class="approved"><i class="bi bi-check-circle"></i> <?php echo $office['approved_count']; ?> Approved</span>
                                <span class="pending"><i class="bi bi-clock"></i> <?php echo $office['pending_count']; ?> Pending</span>
                                <span class="rejected"><i class="bi bi-x-circle"></i> <?php echo $office['rejected_count']; ?> Rejected</span>
                                <span class="text-muted"><i class="bi bi-people"></i> Total: <?php echo $office['total_students']; ?></span>
                            </div>
                            <div class="progress-track mt-2">
                                <?php 
                                $office_progress = ($office['total_students'] > 0) ? round(($office['approved_count'] / $office['total_students']) * 100) : 0;
                                ?>
                                <div class="progress-fill" style="width: <?php echo $office_progress; ?>%; background: <?php echo $office_progress >= 80 ? '#006c49' : ($office_progress >= 50 ? '#f59e0b' : '#1e40af'); ?>;"></div>
                            </div>
                            <div class="text-muted small mt-1" style="font-family:'Hanken Grotesk',sans-serif;"><?php echo $office_progress; ?>% Complete</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center text-muted py-4" style="font-family:'Hanken Grotesk',sans-serif;">No office clearance data available</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================
         RECENT ACTIVITY - With DataTables
    ============================================================ -->
    <div class="card-custom mb-4">
        <div class="card-header">
            <i class="bi bi-clock-history me-2"></i> Recent Clearance Activity
            <span class="badge bg-secondary float-end" style="background:#4b5563 !important;">Total: <?php echo mysqli_num_rows($recent_activities); ?> records</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="recentActivityTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Reg No</th>
                            <th>Office</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($recent_activities) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($recent_activities)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $row['fullname']))); ?></td>
                                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_role']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo getStatusBadge($row['status']); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['approved_at']): ?>
                                        <?php echo date('M d, Y h:i A', strtotime($row['approved_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4" style="font-family:'Hanken Grotesk',sans-serif;">No recent activity found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================================
         QUICK STATS CARDS
    ============================================================ -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card-custom">
                <div class="card-body text-center">
                    <i class="bi bi-people display-4 text-primary"></i>
                    <h6 class="mt-2" style="font-family:'Hanken Grotesk',sans-serif;">Admission List</h6>
                    <p class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;">Total students in admission list</p>
                    <a href="students.php" class="btn btn-primary btn-sm">View All (<?php echo $students_count; ?>)</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom">
                <div class="card-body text-center">
                    <i class="bi bi-person-badge display-4 text-success"></i>
                    <h6 class="mt-2" style="font-family:'Hanken Grotesk',sans-serif;">Total Officers</h6>
                    <p class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;">All officers across all offices</p>
                    <a href="officers.php" class="btn btn-success btn-sm">Manage Officers</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-custom">
                <div class="card-body text-center">
                    <i class="bi bi-check2-all display-4 text-warning"></i>
                    <h6 class="mt-2" style="font-family:'Hanken Grotesk',sans-serif;">Clearance Rate</h6>
                    <p class="text-muted small" style="font-family:'Hanken Grotesk',sans-serif;"><?php echo $clearance_rate; ?>% of started students cleared</p>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo $clearance_rate; ?>%; background: <?php echo $clearance_rate >= 80 ? '#006c49' : ($clearance_rate >= 50 ? '#f59e0b' : '#dc2626'); ?>;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Hidden forms for downloading all items -->
<form id="downloadAllLettersForm" method="POST" action="admin-download-all-letters.php" target="_blank">
    <input type="hidden" name="student_ids" id="downloadLettersStudentIds" value="">
</form>

<form id="downloadAllSlipsForm" method="POST" action="admin-download-all-slips.php" target="_blank">
    <input type="hidden" name="student_ids" id="downloadSlipsStudentIds" value="">
</form>

<script>
// ============================================================
// DataTables Initialization for Recent Activity
// ============================================================
$(document).ready(function () {
    $('#recentActivityTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[4, 'desc']], // Sort by Date column descending
        columnDefs: [
            { orderable: false, targets: [] }
        ],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            infoFiltered: "(filtered from _MAX_ total records)",
            zeroRecords: "No matching records found",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });
});

// ============================================================
// STUDENTS DATA - Passed from PHP to JavaScript (CLEANED)
// ============================================================
var clearedStudents = <?php echo json_encode($cleared_students); ?>;
var validStudents = <?php echo json_encode($valid_students_for_js); ?>;

// ============================================================
// DOWNLOAD ALL SLIPS AS ZIP FILE
// ============================================================
function downloadAllSlips() {
    if (clearedStudents.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'No Students Available',
            text: 'There are no cleared students with signed documents to download.',
            confirmButtonColor: '#0ea5e9'
        });
        return;
    }

    var count = clearedStudents.length;

    Swal.fire({
        title: 'Download All Clearance Slips?',
        html: 'You are about to download <strong>' + count + '</strong> clearance slip(s) as a ZIP file.<br><br>This may take a moment depending on the number of students.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#006c49',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Download All',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Generating ZIP File...',
                html: 'Please wait while we generate all clearance slips.<br><div class="spinner-border text-success mt-3" role="status"></div>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            var studentIds = clearedStudents.map(function(s) { return s.id; });

            var form = document.getElementById('downloadAllSlipsForm');
            document.getElementById('downloadSlipsStudentIds').value = JSON.stringify(studentIds);
            form.submit();

            setTimeout(function() {
                Swal.close();
            }, 2000);
        }
    });
}

// ============================================================
// DOWNLOAD ALL LETTERS AS ZIP FILE
// ============================================================
function downloadAllLetters() {
    if (validStudents.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Signed Letters',
            text: 'None of the cleared students have signed letters available for download.',
            confirmButtonColor: '#0ea5e9'
        });
        return;
    }

    var count = validStudents.length;

    Swal.fire({
        title: 'Download All Signed Letters?',
        html: 'You are about to download <strong>' + count + '</strong> signed letter(s) as a ZIP file.<br><br>This may take a moment depending on the number of students.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#006c49',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Download All',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Generating ZIP File...',
                html: 'Please wait while we generate all signed letters.<br><div class="spinner-border text-success mt-3" role="status"></div>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            var studentIds = validStudents.map(function(s) { return s.id; });

            var form = document.getElementById('downloadAllLettersForm');
            document.getElementById('downloadLettersStudentIds').value = JSON.stringify(studentIds);
            form.submit();

            setTimeout(function() {
                Swal.close();
            }, 2000);
        }
    });
}

// ============================================================
// Auto-refresh data every 30 seconds
// ============================================================
setInterval(function() {
    $.ajax({
        url: 'dashboard-data.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if(data) {
                $('.stat-card .number').each(function() {
                    var label = $(this).closest('.stat-card').find('.label').text().trim();
                    if(label === 'Started Clearance') {
                        $(this).text(data.total_started || 0);
                    } else if(label === 'Fully Cleared') {
                        $(this).text(data.total_cleared || 0);
                    } else if(label === 'In Progress') {
                        $(this).text(data.total_pending || 0);
                    } else if(label === 'Rejected') {
                        $(this).text(data.total_rejected || 0);
                    } else if(label === 'Not Started') {
                        $(this).text(data.total_not_started || 0);
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            console.log('Dashboard update error:', error);
        }
    });
}, 30000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>