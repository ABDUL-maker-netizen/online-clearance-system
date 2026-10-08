<?php
session_start();

include '../includes/config.php';
include '../includes/auth.php';
include '../includes/csrf.php';

// Check if Senate officer is logged in
senateAuth();

if(!isset($_SESSION['senate_officer_id'])){
    header("Location: login.php");
    exit();
}

$officer_id = $_SESSION['senate_officer_id'];
$officer_name = $_SESSION['senate_officer_name'];

// Handle status update
if(isset($_POST['update_status'])){
    verify_csrf();

    $submission_id = intval($_POST['submission_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment'] ?? '');

    // Only allow known statuses
    $allowed_statuses = ['pending', 'approved', 'rejected', 'returned'];
    if(!in_array($status, $allowed_statuses, true)){
        $_SESSION['alert_message'] = "Invalid status selected.";
        $_SESSION['alert_type'] = 'error';
        header("Location: dashboard.php");
        exit();
    }

    // Get student info for notification
    $student_q = mysqli_query($conn,"
        SELECT student_id FROM senate_submissions WHERE id='$submission_id'
    ");
    $student_data = mysqli_fetch_assoc($student_q);

    $update_result = mysqli_query($conn,"
        UPDATE senate_submissions
        SET status='$status',
            reviewed_at=NOW(),
            reviewed_by='$officer_id',
            comment='$comment'
        WHERE id='$submission_id'
    ");

    if($update_result){
        // Add notification for student
        if($student_data){
            $notify_msg = "Your Senate submission has been " . ucfirst($status);
            if($status == 'returned' && !empty($comment)){
                $notify_msg .= ": $comment";
            }
            if($status == 'approved'){
                $notify_msg = "🎉 Congratulations! Your Senate submission has been approved.";
            }
            mysqli_query($conn,"
                INSERT INTO notifications (user_id, message, status, created_at)
                VALUES ('{$student_data['student_id']}', '$notify_msg', 'unread', NOW())
            ");
        }

        $_SESSION['alert_message'] = "Submission status updated to " . ucfirst($status) . " successfully!";
        $_SESSION['alert_type'] = 'approved';
    } else {
        $_SESSION['alert_message'] = "Failed to update status. Please try again.";
        $_SESSION['alert_type'] = 'error';
    }

    header("Location: dashboard.php");
    exit();
}

// Get all submissions with student details
$query = mysqli_query($conn,"
SELECT 
    ss.*,
    s.fullname,
    s.reg_number,
    s.faculty_name,
    s.department
FROM senate_submissions ss
INNER JOIN students s ON s.id = ss.student_id
ORDER BY 
    CASE ss.status 
        WHEN 'pending' THEN 1 
        WHEN 'returned' THEN 2
        WHEN 'approved' THEN 3 
        WHEN 'rejected' THEN 4 
    END,
    ss.submitted_at DESC
");

$total_submissions = mysqli_num_rows($query);

// Get counts
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
$returned_count = 0;

$count_query = mysqli_query($conn,"
SELECT status, COUNT(*) as count FROM senate_submissions GROUP BY status
");
while($c = mysqli_fetch_assoc($count_query)){
    if($c['status'] == 'pending') $pending_count = $c['count'];
    elseif($c['status'] == 'approved') $approved_count = $c['count'];
    elseif($c['status'] == 'rejected') $rejected_count = $c['count'];
    elseif($c['status'] == 'returned') $returned_count = $c['count'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Senate Dashboard - ATBU Clearance System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Matching design styles - Updated */
        :root {
            --primary-blue: #1e40af;
            --primary-dark: #1e3a8a;
            --bg-light: #f8f9ff;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.05);
            --border-color: #e5e7eb;
            --text-dark: #111827;
            --text-muted: #4b5563;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--bg-light);
            font-family: 'Hanken Grotesk', sans-serif;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1280px;
            padding: 24px 20px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0 0 4px 0;
            letter-spacing: -0.02em;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .page-header h1 i {
            color: var(--primary-blue);
            margin-right: 10px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 16px;
            margin: 0;
            font-weight: 400;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .officer-badge {
            background: #f3f4f6;
            color: var(--text-dark);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .officer-badge i {
            color: var(--primary-blue);
        }
        
        .btn-logout {
            background: #dc2626;
            color: white;
            padding: 6px 16px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .btn-logout:hover {
            background: #b91c1c;
            color: white;
            transform: scale(0.95);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }
        
        .stat-card .stat-icon {
            font-size: 22px;
            margin-bottom: 8px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .stat-card .stat-border {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        
        .stat-card.total .stat-border { background: var(--primary-blue); }
        .stat-card.pending .stat-border { background: #f59e0b; }
        .stat-card.approved .stat-border { background: #10b981; }
        .stat-card.rejected .stat-border { background: #ef4444; }
        
        .stat-card.total .stat-number { color: var(--primary-blue); }
        .stat-card.pending .stat-number { color: #92400e; }
        .stat-card.approved .stat-number { color: #065f46; }
        .stat-card.rejected .stat-number { color: #991b1b; }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .table-card-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: #fafbfc;
        }
        
        .table-card-header h5 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table-card-header h5 i {
            margin-right: 8px;
            color: var(--primary-blue);
        }
        
        .status-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .status-filter {
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background: #f3f4f6;
            color: var(--text-muted);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .status-filter.pending { background: #fef3c7; color: #92400e; }
        .status-filter.approved { background: #d1fae5; color: #065f46; }
        .status-filter.rejected { background: #fee2e2; color: #991b1b; }
        .status-filter.returned { background: #f3f4f6; color: #374151; }
        
        /* Table */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            margin-bottom: 0;
            width: 100%;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table thead th {
            background: #111827;
            color: white;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border: none;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table thead th:first-child { border-radius: 0; }
        .table thead th:last-child { border-radius: 0; }
        
        .table tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #f8f9ff; }
        
        .table tbody tr.pending { border-left: 3px solid #f59e0b; }
        .table tbody tr.approved { border-left: 3px solid #10b981; }
        .table tbody tr.rejected { border-left: 3px solid #ef4444; }
        .table tbody tr.returned { border-left: 3px solid #f59e0b; }
        
        /* Status Badge */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.approved { background: #d1fae5; color: #065f46; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.returned { background: #f3f4f6; color: #374151; }
        
        /* Documents */
        .documents-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .btn-file {
            padding: 2px 10px;
            font-size: 11px;
            border-radius: 0.375rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            width: fit-content;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
        }
        
        .btn-file:hover { text-decoration: none; transform: scale(0.95); }
        .btn-file.missing { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .btn-file.btn-outline-primary { 
            color: var(--primary-blue); 
            border: 1px solid var(--primary-blue);
            background: transparent;
        }
        .btn-file.btn-outline-primary:hover { background: var(--primary-blue); color: white; }
        .btn-file.btn-outline-info { 
            color: #0ea5e9; 
            border: 1px solid #0ea5e9;
            background: transparent;
        }
        .btn-file.btn-outline-info:hover { background: #0ea5e9; color: white; }
        
        .file-status { font-size: 9px; color: #ef4444; }
        
        /* Action Form */
        .action-form {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 140px;
        }
        
        .action-form .form-select {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .action-form .form-select:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.15);
        }
        
        .action-form .form-control {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .action-form .form-control:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.15);
        }
        
        .action-form .btn-update {
            background: var(--primary-blue);
            color: white;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            cursor: pointer;
            width: 100%;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .action-form .btn-update:hover {
            background: var(--primary-dark);
            transform: scale(0.95);
        }
        
        /* Empty State */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            color: #d1d5db;
        }
        
        .empty-state p {
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .container { padding: 16px; }
            .stat-card .stat-number { font-size: 26px; }
        }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .page-header h1 { font-size: 22px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card .stat-number { font-size: 22px; }
            .stat-card .stat-label { font-size: 12px; }
            .table thead th, .table tbody td { padding: 8px 12px; font-size: 12px; }
            .header-right { justify-content: flex-start; }
        }
        
        @media (max-width: 576px) {
            .container { padding: 12px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
            .stat-card { padding: 12px; }
            .stat-card .stat-number { font-size: 20px; }
            .stat-card .stat-label { font-size: 11px; }
            .stat-card .stat-icon { font-size: 18px; margin-bottom: 4px; }
            .page-header h1 { font-size: 18px; }
            .page-header p { font-size: 13px; }
            .table-card-header { padding: 12px 16px; flex-direction: column; align-items: stretch; }
            .status-filters { justify-content: center; }
            .table thead th, .table tbody td { padding: 6px 8px; font-size: 11px; }
            .action-form { min-width: 100px; }
            .action-form .form-select, .action-form .form-control { font-size: 10px; padding: 3px 6px; }
            .action-form .btn-update { font-size: 10px; padding: 3px 8px; }
            .status-badge { font-size: 10px; padding: 3px 8px; }
            .btn-file { font-size: 10px; padding: 2px 8px; }
            .officer-badge { font-size: 11px; padding: 4px 10px; }
            .btn-logout { font-size: 11px; padding: 4px 12px; }
        }
        
        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stat-card { padding: 10px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card .stat-label { font-size: 10px; }
            .page-header h1 { font-size: 16px; }
        }
    </style>
</head>
<body>

<div class="container">

    <!-- SUCCESS/ERROR MESSAGE -->
    <?php if(isset($_SESSION['alert_message'])){ ?> 
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?php echo $_SESSION['alert_type'] == 'approved' ? 'success' : 'error'; ?>',
                title: '<?php echo $_SESSION['alert_type'] == 'approved' ? 'Success' : 'Error'; ?>',
                text: '<?php echo addslashes($_SESSION['alert_message']); ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonColor: '#1e40af'
            });
        });
    </script>
    <?php 
        unset($_SESSION['alert_message']);
        unset($_SESSION['alert_type']);
    } ?>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="bi bi-building"></i> Senate Dashboard</h1>
            <p>Review and manage student document submissions</p>
        </div>
        <div class="header-right">
            <span class="officer-badge">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($officer_name); ?>
            </span>
            <a href="logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-border"></div>
            <span class="stat-icon">📊</span>
            <div class="stat-number"><?php echo $total_submissions; ?></div>
            <div class="stat-label">Total Submissions</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-border"></div>
            <span class="stat-icon">⏳</span>
            <div class="stat-number"><?php echo $pending_count; ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-border"></div>
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo $approved_count; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-border"></div>
            <span class="stat-icon">❌</span>
            <div class="stat-number"><?php echo $rejected_count + $returned_count; ?></div>
            <div class="stat-label">Rejected / Returned</div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-card-header">
            <h5><i class="bi bi-list-ul"></i> Submissions</h5>
            <div class="status-filters">
                <span class="status-filter pending">Pending: <?php echo $pending_count; ?></span>
                <span class="status-filter approved">Approved: <?php echo $approved_count; ?></span>
                <span class="status-filter rejected">Rejected: <?php echo $rejected_count; ?></span>
                <span class="status-filter returned">Returned: <?php echo $returned_count; ?></span>
            </div>
        </div>
        
        <div class="table-responsive-custom">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="min-width:140px;">STUDENT</th>
                        <th style="min-width:100px;">REG NO</th>
                        <th style="min-width:110px;">SUBMITTED</th>
                        <th style="min-width:100px;">STATUS</th>
                        <th style="min-width:140px;">DOCUMENTS</th>
                        <th style="min-width:160px;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0){ 
                        $counter = 1;
                        while($row = mysqli_fetch_assoc($query)){ 
                            $status_class = $row['status'];
                            
                            // Status icons
                            $status_icon = '';
                            if($row['status'] == 'pending') $status_icon = 'bi-clock';
                            elseif($row['status'] == 'approved') $status_icon = 'bi-check-circle';
                            elseif($row['status'] == 'rejected') $status_icon = 'bi-x-circle';
                            elseif($row['status'] == 'returned') $status_icon = 'bi-arrow-return-left';
                            
                            // Check file paths
                            $clearance_file = $row['signed_clearance_form'];
                            if(!empty($clearance_file)){
                                if(strpos($clearance_file, 'cleared_slip_') === 0){
                                    $clearance_path = "../assets/uploads/clearance_slips/" . $clearance_file;
                                } else {
                                    $clearance_path = "../assets/uploads/" . $clearance_file;
                                }
                                $clearance_exists = file_exists($clearance_path);
                            } else {
                                $clearance_path = '';
                                $clearance_exists = false;
                            }
                            
                            $dept_letter = $row['signed_department_letter'];
                            if(!empty($dept_letter)){
                                $dept_path = "../assets/uploads/department_letters/" . $dept_letter;
                                $dept_exists = file_exists($dept_path);
                            } else {
                                $dept_path = '';
                                $dept_exists = false;
                            }
                    ?>
                    <tr class="<?php echo $status_class; ?>">
                        <td><?php echo $counter++; ?></td>
                        <td>
                            <strong style="font-size:13px; font-family:'Hanken Grotesk',sans-serif;"><?php echo htmlspecialchars($row['fullname']); ?></strong>
                            <br>
                            <small class="text-muted" style="font-size:11px; color:#4b5563;"><?php echo htmlspecialchars($row['faculty_name']); ?></small>
                        </td>
                        <td style="font-size:13px;"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                        <td>
                            <span title="<?php echo date('F d, Y h:i:s A', strtotime($row['submitted_at'])); ?>" style="font-size:13px;">
                                <?php echo date('M d, Y', strtotime($row['submitted_at'])); ?>
                            </span>
                            <br>
                            <small class="text-muted" style="font-size:10px; color:#4b5563;"><?php echo date('h:i A', strtotime($row['submitted_at'])); ?></small>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <i class="bi <?php echo $status_icon; ?>"></i>
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            <?php if(!empty($row['comment'])){ ?>
                                <br>
                                <small class="text-muted" style="font-size:10px; color:#4b5563;">💬 <?php echo htmlspecialchars(substr($row['comment'], 0, 30)); ?></small>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="documents-cell">
                                <?php if(!empty($clearance_file)){ ?>
                                    <a href="<?php echo $clearance_path; ?>" 
                                       target="_blank" 
                                       class="btn-file btn-outline-primary <?php echo !$clearance_exists ? 'missing' : ''; ?>"
                                       <?php echo !$clearance_exists ? 'onclick="return false;"' : ''; ?>>
                                        <i class="bi bi-file-earmark-pdf"></i> Clearance Form
                                        <?php if(!$clearance_exists){ ?>
                                            <span class="file-status">(missing)</span>
                                        <?php } ?>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted" style="font-size:11px; color:#4b5563;">No clearance form</span>
                                <?php } ?>
                                
                                <?php if(!empty($dept_letter)){ ?>
                                    <a href="<?php echo $dept_path; ?>" 
                                       target="_blank" 
                                       class="btn-file btn-outline-info <?php echo !$dept_exists ? 'missing' : ''; ?>"
                                       <?php echo !$dept_exists ? 'onclick="return false;"' : ''; ?>>
                                        <i class="bi bi-file-earmark-pdf"></i> Dept Letter
                                        <?php if(!$dept_exists){ ?>
                                            <span class="file-status">(missing)</span>
                                        <?php } ?>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted" style="font-size:11px; color:#4b5563;">No dept letter</span>
                                <?php } ?>
                            </div>
                        </td>
                        <td>
                            <form method="POST" class="action-form" onsubmit="return confirmStatusUpdate(this)">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="submission_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                
                                <select name="status" class="form-select">
                                    <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>📋 Pending</option>
                                    <option value="approved" <?php echo $row['status'] == 'approved' ? 'selected' : ''; ?>>✅ Approve</option>
                                    <option value="rejected" <?php echo $row['status'] == 'rejected' ? 'selected' : ''; ?>>❌ Reject</option>
                                    <option value="returned" <?php echo $row['status'] == 'returned' ? 'selected' : ''; ?>>↩️ Return</option>
                                </select>
                                
                                <input type="text" name="comment" class="form-control" 
                                       placeholder="Optional comment" value="<?php echo htmlspecialchars($row['comment'] ?? ''); ?>">
                                
                                <button type="submit" class="btn-update">
                                    <i class="bi bi-check2"></i> Update
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php } else { ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No submissions found</p>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmStatusUpdate(form) {
    var select = form.querySelector('select[name="status"]');
    var status = select.options[select.selectedIndex].text.trim();
    var studentName = form.closest('tr').querySelector('td:nth-child(2) strong').textContent;
    
    Swal.fire({
        title: 'Update Submission Status?',
        html: 'You are about to change the status to:<br><strong>' + status + '</strong><br><br>Student: <strong>' + studentName + '</strong>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e40af',
        cancelButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Update',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    
    return false;
}
</script>
</body>
</html>