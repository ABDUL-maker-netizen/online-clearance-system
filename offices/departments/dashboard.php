<?php
session_start();

include '../../includes/config.php';
include '../../includes/auth.php';
include '../../includes/functions.php';

/* =========================
   CHECK SESSION FIRST - Redirect if expired
========================= */
if(!isset($_SESSION['department_officer_id'])){
    header("Location: login.php");
    exit();
}

// Also check if the officer still exists in database
$check_officer = mysqli_query($conn,"
    SELECT id FROM department_officers 
    WHERE id='{$_SESSION['department_officer_id']}' 
    LIMIT 1
");

if(mysqli_num_rows($check_officer) == 0){
    session_destroy();
    header("Location: login.php");
    exit();
}

/* =========================
   SECURITY FIX (IMPORTANT)
========================= */
if(!isset($_SESSION['department_officer_id'])){
    header("Location: login.php");
    exit();
}

$department_name = trim($_SESSION['department_name']);
$officer_id = $_SESSION['department_officer_id'];

/* =========================
   GET OFFICER SIGNATURE & DETAILS
========================= */
$sig_q = mysqli_query($conn,"
    SELECT digital_signature, fullname, email
    FROM department_officers
    WHERE id='$officer_id'
    LIMIT 1
");

$sig_data = mysqli_fetch_assoc($sig_q);
$signature = $sig_data['digital_signature'] ?? '';
$officer_name = $sig_data['fullname'] ?? $_SESSION['department_officer_name'] ?? '';
$officer_email = $sig_data['email'] ?? $_SESSION['department_officer_email'] ?? '';

/* =========================
   STUDENTS - Get distinct students by reg_number
========================= */
$clean_department = trim($department_name);

$query = mysqli_query($conn,"
SELECT DISTINCT s.id,
       s.fullname,
       s.reg_number,
       s.faculty_name,
       s.department,
       cs.status,
       cs.document_file,
       cs.comment
FROM students s
LEFT JOIN clearance_status cs 
    ON s.id = cs.student_id 
    AND cs.department_role = 'DEPARTMENT'
WHERE TRIM(LOWER(s.department)) = LOWER('$clean_department')
   OR TRIM(s.department) LIKE CONCAT('%', TRIM('$clean_department'), '%')
   OR TRIM(LOWER(s.department)) LIKE CONCAT('%', TRIM(LOWER('$clean_department')), '%')
GROUP BY s.id, s.reg_number
ORDER BY s.id DESC
");

/* =========================
   LETTERS - FIXED: Better matching with DISTINCT
========================= */
$letters = mysqli_query($conn,"
SELECT DISTINCT dl.*,
       s.fullname,
       s.reg_number
FROM department_letters dl
INNER JOIN students s ON s.id = dl.student_id
WHERE TRIM(LOWER(dl.department_name)) = LOWER('$clean_department')
   OR TRIM(LOWER(s.department)) = LOWER('$clean_department')
   OR dl.department_name LIKE '%$clean_department%'
GROUP BY dl.id
ORDER BY dl.id DESC
");

// Count distinct students
$total_students = mysqli_num_rows($query);
$total_letters = mysqli_num_rows($letters);

// Store alert message in a variable before clearing session
$alert_message = isset($_SESSION['alert_message']) ? $_SESSION['alert_message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;

// Clear session variables immediately after storing
if(isset($_SESSION['alert_message'])){
    unset($_SESSION['alert_message']);
}
if(isset($_SESSION['alert_type'])){
    unset($_SESSION['alert_type']);
}

// CSRF token function
function csrf_token() {
    if(!isset($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($department_name); ?> Department Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- ✅ SWEETALERT2 - Always loaded for bulk approval -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ✅ JQUERY - Required for AJAX -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
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
        
        body{background:#f8f9fa;font-family:'Hanken Grotesk', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;margin:0;padding:0;}
        
        .container {
            max-width: 1280px;
            padding: 24px 20px;
            margin: 0 auto;
        }
        
        .card{border:none;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);border:1px solid var(--border-color);margin-bottom:24px;}
        
        .table th, .table td{vertical-align:middle;padding:14px 16px;}
        .file-link{color:#0d6efd;text-decoration:none;}
        .file-link:hover{text-decoration:underline;}
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.5;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .status-badge i {
            font-size: 14px;
        }
        .status-badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-badge-pending i {
            color: #856404;
        }
        .status-badge-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-badge-approved i {
            color: #155724;
        }
        .status-badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .status-badge-rejected i {
            color: #721c24;
        }
        
        .table-hover tbody tr:hover{background-color:#f1f4f9;}
        .table thead th{background:#1a1a2e;color:#fff;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;border:none;white-space:nowrap;font-family:'Hanken Grotesk', sans-serif;}
        .table thead th:first-child{border-radius:8px 0 0 0;text-align:center;}
        .table thead th:last-child{border-radius:0 8px 0 0;}
        .table tbody td{border-bottom:1px solid #e9ecef;}
        .table tbody tr:last-child td{border-bottom:none;}
        .table tbody tr:last-child td:first-child{border-radius:0 0 0 8px;}
        .table tbody tr:last-child td:last-child{border-radius:0 0 8px 0;}
        
        .btn-review{padding:5px 18px;font-size:13px;border-radius:6px;background:var(--primary-blue);color:#fff;border:none;display:inline-flex;align-items:center;gap:4px;transition:all 0.15s ease;text-decoration:none;font-family:'Hanken Grotesk', sans-serif;}
        .btn-review:hover{background:var(--primary-dark);color:#fff;transform:scale(0.95);text-decoration:none;}
        .btn-review i{font-size:13px;}
        
        .btn-review-disabled{background:#e9ecef;color:#6c757d;padding:5px 18px;font-size:13px;border-radius:6px;border:none;cursor:not-allowed;display:inline-flex;align-items:center;gap:4px;font-family:'Hanken Grotesk', sans-serif;}
        .btn-review-disabled i{font-size:13px;}
        
        .header-title{font-size:28px;font-weight:800;color:var(--text-dark);margin:0;letter-spacing:-0.02em;font-family:'Hanken Grotesk', sans-serif;}
        .header-title i{font-size:28px;margin-right:10px;color:var(--primary-blue);}
        .header-title .badge-count{background:var(--primary-blue);color:#fff;font-size:14px;padding:4px 12px;border-radius:20px;margin-left:8px;font-weight:600;font-family:'Hanken Grotesk', sans-serif;}
        
        .header-subtitle{color:var(--text-muted);font-size:14px;margin:0;font-weight:400;font-family:'Hanken Grotesk', sans-serif;}
        
        .header-actions .btn{font-size:13px;padding:8px 20px;border-radius:8px;font-weight:500;font-family:'Hanken Grotesk', sans-serif;display:inline-flex;align-items:center;gap:6px;transition:all 0.15s ease;}
        .header-actions .btn i{font-size:15px;}
        .header-actions .btn:hover{transform:scale(0.95);}
        
        .alert-warning-custom{background:#fef3c7;border-left:4px solid #f59e0b;border-radius:8px;padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;}
        .alert-warning-custom i{font-size:20px;color:#f59e0b;}
        .alert-warning-custom .btn{margin-left:auto;flex-shrink:0;}
        
        .table-footer{background:#f8f9fa;padding:12px 16px;border-radius:0 0 12px 12px;border-top:1px solid #e9ecef;font-size:14px;color:#6c757d;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;font-family:'Hanken Grotesk', sans-serif;}
        .table-footer strong{color:var(--text-dark);}
        
        .badge-document{background:#e7f3ff;color:#0d6efd;font-weight:500;padding:3px 12px;border-radius:12px;font-size:12px;display:inline-block;text-decoration:none;transition:all 0.15s ease;font-family:'Hanken Grotesk', sans-serif;}
        .badge-document:hover{background:#dbe1ff;color:var(--primary-dark);text-decoration:none;}
        .badge-document i{font-size:12px;margin-right:4px;}
        
        .no-files-text{color:#adb5bd;font-size:12px;font-style:italic;font-family:'Hanken Grotesk', sans-serif;}
        .fyp-badge{background:#f0f7ff;color:#004085;border:1px solid #cce5ff;}
        
        .checkbox-col{width:40px;text-align:center !important;}
        .id-col{width:60px;}
        .status-col{width:130px;}
        .action-col{width:130px;}
        
        /* Bulk section styling */
        .bulk-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .bulk-section .bulk-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bulk-section .bulk-left label {
            margin: 0;
            font-size: 14px;
            color: var(--text-muted);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .bulk-section .bulk-left label strong {
            color: var(--text-dark);
        }
        
        .bulk-section .bulk-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bulk-section .selected-count {
            color: var(--text-muted);
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .btn-bulk {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 5px 16px;
            font-size: 13px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            font-family: 'Hanken Grotesk', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-bulk:hover {
            background: var(--primary-dark);
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-bulk i {
            font-size: 14px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* Empty state */
        .empty-state {
            padding: 40px 20px;
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
        
        /* Department Info Card - NEW */
        .dept-info-card {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
        }
        
        .dept-info-card h5 {
            font-weight: 600;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
            margin-bottom: 16px;
        }
        
        .dept-info-card .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .dept-info-card .info-item:last-child {
            border-bottom: none;
        }
        
        .dept-info-card .info-label {
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .dept-info-card .info-value {
            color: var(--text-dark);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .container { padding: 20px 16px; }
            .header-title { font-size: 24px; }
            .table th, .table td { padding: 10px 12px; font-size: 13px; }
        }
        
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .header-title { font-size: 20px; }
            .header-title .badge-count { font-size: 12px; padding: 3px 10px; }
            .header-actions .btn { font-size: 12px; padding: 6px 14px; }
            .table th, .table td { padding: 8px 10px; font-size: 12px; }
            .table thead th { font-size: 10px; }
            .status-badge { font-size: 11px; padding: 4px 10px; }
            .bulk-section { flex-direction: column; align-items: stretch; gap: 8px; }
            .bulk-section .bulk-right { justify-content: flex-start; }
            .table-footer { flex-direction: column; text-align: center; gap: 4px; }
            .header-actions { justify-content: flex-start; }
            .alert-warning-custom .btn { margin-left: 0; }
            .dept-info-card { padding: 16px; }
        }
        
        @media (max-width: 576px) {
            .container { padding: 12px; }
            .header-title { font-size: 18px; }
            .header-title i { font-size: 20px; }
            .header-title .badge-count { font-size: 10px; padding: 2px 8px; }
            .header-subtitle { font-size: 12px; }
            .header-actions .btn { font-size: 11px; padding: 5px 10px; }
            .header-actions .btn i { font-size: 12px; }
            .table th, .table td { padding: 6px 8px; font-size: 11px; }
            .table thead th { font-size: 9px; padding: 6px 8px; }
            .status-badge { font-size: 10px; padding: 3px 8px; }
            .status-badge i { font-size: 10px; }
            .btn-review, .btn-review-disabled { font-size: 10px; padding: 4px 10px; }
            .btn-bulk { font-size: 10px; padding: 4px 10px; }
            .badge-document { font-size: 10px; padding: 2px 8px; }
            .file-link { font-size: 10px; }
            .bulk-section .bulk-left label { font-size: 12px; }
            .bulk-section .selected-count { font-size: 12px; }
            .table-footer { font-size: 12px; }
            .checkbox-col { width: 30px; }
            .id-col { width: 40px; }
            .status-col { width: 90px; }
            .action-col { width: 100px; }
            .alert-warning-custom { padding: 10px 14px; font-size: 13px; }
            .dept-info-card { padding: 12px; }
            .dept-info-card .info-item { font-size: 13px; }
        }
        
        @media (max-width: 400px) {
            .container { padding: 8px; }
            .header-title { font-size: 16px; }
            .header-title .badge-count { font-size: 9px; padding: 2px 6px; }
            .table th, .table td { padding: 4px 6px; font-size: 10px; }
            .table thead th { font-size: 8px; padding: 4px 6px; }
            .status-badge { font-size: 9px; padding: 2px 6px; }
            .btn-review, .btn-review-disabled { font-size: 9px; padding: 3px 8px; }
            .btn-bulk { font-size: 9px; padding: 3px 8px; }
            .bulk-section { padding: 8px 12px; }
            .bulk-section .bulk-left label { font-size: 11px; }
            .bulk-section .selected-count { font-size: 11px; }
            .dept-info-card .info-item { font-size: 12px; }
        }
    </style>
</head>

<body>

<!-- SUCCESS/ERROR MESSAGE - FIXED WITH INLINE FUNCTION -->
<?php if($alert_message && $alert_type){ ?> 
<script>
    function customAlert(message, type){
        let iconType = 'success';
        let title = 'Success';
        let buttonColor = '#1e40af';
        
        if(type === 'error' || type === 'rejected'){
            iconType = 'error';
            title = 'Rejected';
            buttonColor = '#dc2626';
        } else if(type === 'approved'){
            iconType = 'success';
            title = 'Approved';
            buttonColor = '#006c49';
        } else if(type === 'info'){
            iconType = 'info';
            title = 'Information';
            buttonColor = '#0ea5e9';
        }
        
        Swal.fire({
            icon: iconType,
            title: title,
            text: message,
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: buttonColor
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        customAlert(
            '<?php echo addslashes($alert_message); ?>', 
            '<?php echo $alert_type; ?>'
        );
    });
</script>
<?php } ?>

<div class="container">

<!-- HEADER - Updated to match image design -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h1 class="header-title">
            <i class="bi bi-building"></i> <?php echo htmlspecialchars($department_name); ?> DEPARTMENT
            <span class="badge-count"><?php echo $total_students; ?> Students</span>
        </h1>
        <p class="header-subtitle">DASHBOARD</p>
    </div>
    <div class="header-actions d-flex flex-wrap gap-2">
        <a href="upload-signature.php" class="btn btn-primary">
            <i class="bi bi-upload"></i> Upload Signature
        </a>
        <a href="report.php" class="btn btn-success">
            <i class="bi bi-file-earmark-text"></i> Reports
        </a>
        <a href="logout.php" class="btn btn-danger">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- SIGNATURE WARNING -->
<?php if(empty($signature)){ ?>
<div class="alert alert-warning-custom">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span class="flex-grow-1">You have not uploaded your digital signature yet. You cannot approve student requests or sign letters until you upload it.</span>
    <a href="upload-signature.php" class="btn btn-primary btn-sm">
        <i class="bi bi-upload"></i> Upload Signature
    </a>
</div>
<?php } ?>

<!-- ============================================================
     STUDENTS CARD
============================================================ -->
<div class="card shadow-sm">

    <!-- BULK PREVIEW & APPROVAL SECTION FOR STUDENTS - Updated to match image -->
    <?php if(!empty($signature)){ ?>
    <div class="bulk-section">
        <div class="bulk-left">
            <input type="checkbox" id="selectAllPending" class="form-check-input" onclick="toggleSelectAll(this)">
            <label for="selectAllPending" class="mb-0">
                <strong>Select All Pending</strong>
            </label>
        </div>
        <div class="bulk-right">
            <span class="selected-count" id="selectedCount">0 students selected</span>
            <button type="button" class="btn-bulk" id="bulkPreviewBtn" onclick="confirmBulkPreview()">
                <i class="bi bi-eye"></i> Preview & Approve
            </button>
        </div>
    </div>
    <?php } else { ?>
    <div class="alert alert-warning m-3" style="border-left:4px solid #f59e0b;">
        <i class="bi bi-info-circle me-2"></i> 
        <strong>Bulk approval requires a digital signature.</strong> 
        Please upload your signature first.
    </div>
    <?php } ?>

    <div class="table-responsive">
        <form id="bulkApproveForm" method="POST" action="bulk-preview.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="checkbox-col">
                            <input type="checkbox" id="selectAllPendingTable" class="form-check-input" onclick="toggleSelectAll(this)">
                        </th>
                        <th class="id-col">ID</th>
                        <th>NAME</th>
                        <th>REG NO</th>
                        <th class="status-col">STATUS</th>
                        <th>DOCUMENTS</th>
                        <th class="action-col">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query) > 0){ ?>
                        <?php while($row = mysqli_fetch_assoc($query)){ 
                            $status = strtolower($row['status'] ?? 'pending');
                            
                            if($status == 'approved'){
                                $status_class = 'status-badge-approved';
                                $status_icon = 'bi-check-circle-fill';
                                $status_text = 'Approved';
                            }elseif($status == 'rejected'){
                                $status_class = 'status-badge-rejected';
                                $status_icon = 'bi-x-circle-fill';
                                $status_text = 'Rejected';
                            }else{
                                $status_class = 'status-badge-pending';
                                $status_icon = 'bi-clock-fill';
                                $status_text = 'Pending';
                            }
                            
                            // GET FINAL YEAR PROJECT
                            $fyp_query = mysqli_query($conn,"
                                SELECT fyp_file, uploaded_at
                                FROM final_year_projects
                                WHERE student_id = '{$row['id']}'
                                LIMIT 1
                            ");
                            $fyp_data = mysqli_fetch_assoc($fyp_query);
                            $has_fyp = $fyp_data && !empty($fyp_data['fyp_file']);
                        ?>
                        <tr>
                            <td class="text-center">
                                <?php if($status == 'pending'){ ?>
                                <input type="checkbox" class="student-checkbox pending-checkbox form-check-input" name="student_ids[]" value="<?php echo $row['id']; ?>" onclick="updateSelectionCount()">
                                <?php } ?>
                            </td>
                            <td><strong><?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>" data-student-id="<?php echo $row['id']; ?>">
                                    <i class="bi <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                // Main File
                                if(!empty($row['document_file'])){
                                    echo "<span class='badge-document me-1 mb-1'>";
                                    echo "<i class='bi bi-file-earmark-pdf-fill'></i> Main File</span>";
                                }
                                
                                // FINAL YEAR PROJECT
                                if($has_fyp){
                                    echo "<span class='badge-document fyp-badge me-1 mb-1'>";
                                    echo "<i class='bi bi-file-earmark'></i> Final Year Project</span>";
                                }
                                
                                if(empty($row['document_file']) && !$has_fyp){
                                    echo "<span class='no-files-text'>No files</span>";
                                }
                                
                                // Comment
                                if(!empty($row['comment'])){
                                    echo "<div class='mt-1'><small class='text-muted'><i class='bi bi-chat'></i> " . htmlspecialchars($row['comment']) . "</small></div>";
                                }
                                ?>
                            </td>
                            <td>
                                <?php if(empty($signature)){ ?>
                                    <button class="btn-review-disabled" disabled>
                                        <i class="bi bi-upload"></i> Upload Signature First
                                    </button>
                                <?php } else { ?>
                                    <a href="review-request.php?id=<?php echo $row['id']; ?>" class="btn-review">
                                        <i class="bi bi-eye"></i> Review
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No students found in this department</p>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </form>
    </div>

    <!-- TABLE FOOTER - Updated to match image -->
    <div class="table-footer">
        <span>
            Showing <strong><?php echo ($total_students > 0) ? '1' : '0'; ?></strong> 
            to <strong><?php echo $total_students; ?></strong> 
            of <strong><?php echo $total_students; ?></strong> entries
        </span>
        <span>
            <i class="bi bi-people"></i> Total Students: <?php echo $total_students; ?>
        </span>
    </div>

</div>

<!-- ============================================================
     LETTERS CARD
============================================================ -->
<div class="card shadow-sm">

    <!-- BULK PREVIEW & SIGNING SECTION FOR LETTERS - Updated to match image -->
    <?php if(!empty($signature)){ ?>
    <div class="bulk-section">
        <div class="bulk-left">
            <input type="checkbox" id="selectAllPendingLetters" class="form-check-input" onclick="toggleSelectAllLetters(this)">
            <label for="selectAllPendingLetters" class="mb-0">
                <strong>Select All Pending Letters</strong>
            </label>
        </div>
        <div class="bulk-right">
            <span class="selected-count" id="selectedLettersCount">0 letters selected</span>
            <button type="button" class="btn-bulk" id="bulkPreviewLettersBtn" onclick="confirmBulkPreviewLetters()">
                <i class="bi bi-eye"></i> Preview & Sign
            </button>
        </div>
    </div>
    <?php } else { ?>
    <div class="alert alert-warning m-3" style="border-left:4px solid #f59e0b;">
        <i class="bi bi-info-circle me-2"></i> 
        <strong>Signing letters requires a digital signature.</strong> 
        Please upload your signature first.
    </div>
    <?php } ?>

    <div class="table-responsive">
        <form id="bulkSignLettersForm" method="POST" action="bulk-preview-sign-letters.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="checkbox-col">
                            <input type="checkbox" id="selectAllPendingLettersTable" class="form-check-input" onclick="toggleSelectAllLetters(this)">
                        </th>
                        <th class="id-col">ID</th>
                        <th>STUDENT</th>
                        <th>FILE</th>
                        <th class="status-col">STATUS</th>
                        <th class="action-col">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($letters) > 0){ ?>
                        <?php while($l = mysqli_fetch_assoc($letters)){ 
                            $is_pending = ($l['status'] == 'pending');
                            if($l['status'] == 'signed'){
                                $letter_status_class = 'status-badge-approved';
                                $letter_status_icon = 'bi-check-circle-fill';
                                $letter_status_text = 'Signed';
                            }elseif($l['status'] == 'rejected'){
                                $letter_status_class = 'status-badge-rejected';
                                $letter_status_icon = 'bi-x-circle-fill';
                                $letter_status_text = 'Rejected';
                            }else{
                                $letter_status_class = 'status-badge-pending';
                                $letter_status_icon = 'bi-clock-fill';
                                $letter_status_text = 'Pending';
                            }
                        ?>
                        <tr>
                            <td class="text-center">
                                <?php if($is_pending){ ?>
                                <input type="checkbox" class="letter-checkbox pending-letter-checkbox form-check-input" name="letter_ids[]" value="<?php echo $l['id']; ?>" onclick="updateLettersSelectionCount()">
                                <?php } ?>
                            </td>
                            <td><strong><?php echo $l['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($l['fullname']); ?></td>
                            <td>
                                <?php if($l['status'] == 'signed'){ ?>
                                    <a target="_blank" href="../../assets/uploads/department_letters/<?php echo $l['signed_file']; ?>" class="badge-document text-decoration-none">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> View Signed Letter
                                    </a>
                                <?php } else { ?>
                                    <a target="_blank" href="../../assets/uploads/department_letters/<?php echo $l['original_file']; ?>" class="badge-document text-decoration-none">
                                        <i class="bi bi-file-earmark"></i> View Letter
                                    </a>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $letter_status_class; ?>">
                                    <i class="bi <?php echo $letter_status_icon; ?>"></i> <?php echo $letter_status_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php if(empty($signature)){ ?>
                                    <button class="btn-review-disabled" disabled>
                                        <i class="bi bi-upload"></i> Upload Signature First
                                    </button>
                                <?php } else { ?>
                                    <a href="review-letter.php?id=<?php echo $l['id']; ?>" class="btn-review" style="background:#1a1a2e;">
                                        <i class="bi bi-eye"></i> Review
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No letters found</p>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </form>
    </div>

    <!-- TABLE FOOTER - Updated to match image -->
    <div class="table-footer">
        <span>
            Showing <strong><?php echo ($total_letters > 0) ? '1' : '0'; ?></strong> 
            to <strong><?php echo $total_letters; ?></strong> 
            of <strong><?php echo $total_letters; ?></strong> entries
        </span>
        <span>
            <i class="bi bi-envelope"></i> Total Letters: <?php echo $total_letters; ?>
        </span>
    </div>

</div>

<!-- DEPARTMENT INFO CARD - NEWLY ADDED -->
<div class="card shadow-sm">
    <div class="card-body dept-info-card">
        <h5><i class="bi bi-info-circle"></i> Department Information</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-building"></i> Department:</span>
                    <span class="info-value ms-2"><?php echo htmlspecialchars($department_name); ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-person"></i> Officer:</span>
                    <span class="info-value ms-2"><?php echo htmlspecialchars($officer_name); ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-envelope"></i> Email:</span>
                    <span class="info-value ms-2"><?php echo htmlspecialchars($officer_email); ?></span>
                </div>
            </div>
        </div>
        <?php if(!empty($signature)){ ?>
            <div class="alert alert-success mt-3 mb-0" style="border-left:4px solid #28a745;">
                <i class="bi bi-check-circle"></i> Digital signature uploaded successfully
            </div>
        <?php } ?>
    </div>
</div>

</div>

<script src="../../assets/js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>

<script>
// ============================================================
// STUDENT BULK PREVIEW & APPROVAL FUNCTIONS
// ============================================================
function toggleSelectAll(checkbox) {
    var checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
    updateSelectionCount();
}

function updateSelectionCount() {
    var checkboxes = document.querySelectorAll('.student-checkbox:checked');
    var count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count + ' student' + (count !== 1 ? 's' : '') + ' selected';
}

function confirmBulkPreview() {
    var checkboxes = document.querySelectorAll('.student-checkbox:checked');
    var count = checkboxes.length;
    
    console.log('Bulk preview clicked. Selected:', count);
    
    if(count === 0){
        Swal.fire({
            icon: 'warning',
            title: 'No Students Selected',
            text: 'Please select at least one pending student to preview.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }
    
    Swal.fire({
        title: 'Preview Selected Students?',
        html: 'You are about to preview <strong>' + count + '</strong> student' + (count !== 1 ? 's' : '') + '.<br><br>You will be able to review all documents before approving.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e40af',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Preview',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            // Set form action to bulk-preview.php and submit
            var form = document.getElementById('bulkApproveForm');
            form.action = 'bulk-preview.php';
            form.submit();
        }
    });
}

// ============================================================
// LETTER BULK PREVIEW & SIGNING FUNCTIONS
// ============================================================
function toggleSelectAllLetters(checkbox) {
    var checkboxes = document.querySelectorAll('.letter-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
    updateLettersSelectionCount();
}

function updateLettersSelectionCount() {
    var checkboxes = document.querySelectorAll('.letter-checkbox:checked');
    var count = checkboxes.length;
    document.getElementById('selectedLettersCount').textContent = count + ' letter' + (count !== 1 ? 's' : '') + ' selected';
}

function confirmBulkPreviewLetters() {
    var checkboxes = document.querySelectorAll('.letter-checkbox:checked');
    var count = checkboxes.length;
    
    console.log('Bulk preview letters clicked. Selected:', count);
    
    if(count === 0){
        Swal.fire({
            icon: 'warning',
            title: 'No Letters Selected',
            text: 'Please select at least one pending letter to preview.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }
    
    Swal.fire({
        title: 'Preview Selected Letters?',
        html: 'You are about to preview <strong>' + count + '</strong> letter' + (count !== 1 ? 's' : '') + '.<br><br>You will be able to review each letter before signing.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e40af',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Preview',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            document.getElementById('bulkSignLettersForm').submit();
        }
    });
}

// ============================================================
// ✅ REAL-TIME STATUS UPDATES - Auto-refresh every 10 seconds
// ============================================================
setInterval(function() {
    $.ajax({
        url: '../../get-status-updates.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            // Update student status badges using data-student-id attribute
            $('.status-badge').each(function() {
                var studentId = $(this).data('student-id');
                if(data[studentId]) {
                    var status = data[studentId];
                    var badge = $(this);
                    
                    // Remove all status classes
                    badge.removeClass('status-badge-pending status-badge-approved status-badge-rejected');
                    
                    // Add the new status class
                    badge.addClass('status-badge-' + status);
                    
                    // Update the icon and text
                    var icon = status === 'approved' ? 'bi-check-circle-fill' : 
                               status === 'rejected' ? 'bi-x-circle-fill' : 'bi-clock-fill';
                    var text = status.charAt(0).toUpperCase() + status.slice(1);
                    
                    badge.html('<i class="bi ' + icon + '"></i> ' + text);
                }
            });
        },
        error: function(xhr, status, error) {
            console.log('Status update error:', error);
        }
    });
}, 10000); // Refresh every 10 seconds
</script>
</body>
</html>