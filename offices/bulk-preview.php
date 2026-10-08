<?php
session_start();

include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';

// Check if officer is logged in
if(!isset($_SESSION['office_officer_id']) || !isset($_SESSION['office_role'])){
    header("Location: login.php");
    exit();
}

officerAuth();

// Verify role
if(!isset($_SESSION['office_role']) || $_SESSION['office_role'] !== 'OFFICE'){
    session_destroy();
    header("Location: login.php");
    exit();
}

if(!isset($_SESSION['office_name'])){
    header("Location: login.php");
    exit();
}

$office = strtoupper($_SESSION['office_name']);
$officer_id = $_SESSION['office_officer_id'];

// Check if request is POST from dashboard
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: dashboard.php");
    exit();
}

// Get selected student IDs
if(!isset($_POST['student_ids']) || empty($_POST['student_ids'])){
    $_SESSION['alert_message'] = "No students selected for bulk preview.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

$student_ids = $_POST['student_ids'];
if(!is_array($student_ids)){
    $student_ids = [];
}

// Sanitize and validate IDs
$valid_ids = [];
foreach($student_ids as $id){
    $valid_ids[] = intval($id);
}

if(empty($valid_ids)){
    $_SESSION['alert_message'] = "Invalid student IDs provided.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Get officer signature
$sig_q = mysqli_query($conn,"
    SELECT digital_signature
    FROM officers
    WHERE id='$officer_id'
    LIMIT 1
");
$sig_data = mysqli_fetch_assoc($sig_q);
$signature = $sig_data['digital_signature'] ?? '';

// Build safe ID list for query
$ids_string = implode(',', array_map('intval', $valid_ids));

// ============================================================
// FUNCTIONS FOR CHECKING REQUIREMENTS - DYNAMIC FOR BURSAR
// ============================================================

function getMinimumRequiredFiles($office, $student_reg, $student_program = ''){
    $reg = strtoupper($student_reg);
    $program = strtolower($student_program);
    
    switch($office){
        case 'BURSAR':
            // Check program duration
            $is_medical = strpos($program, 'medicine') !== false || 
                         strpos($program, 'medical') !== false ||
                         strpos($program, 'mbbs') !== false ||
                         strpos($program, 'pharmacy') !== false ||
                         strpos($program, 'veterinary') !== false;
            
            $is_5_year = strpos($program, 'engineering') !== false || 
                        strpos($program, 'law') !== false ||
                        strpos($program, 'architecture') !== false ||
                        strpos($program, 'geology') !== false ||
                        strpos($program, 'urban') !== false ||
                        strpos($program, 'planning') !== false;
            
            $is_de = strpos($reg, 'DE') !== false || 
                     strpos($reg, 'D/E') !== false ||
                     strpos($reg, 'D.E') !== false;
            
            $years = 4;
            if($is_medical) $years = 6;
            elseif($is_5_year) $years = 5;
            if($is_de) $years = $years - 1;
            
            return $years;
            
        case 'ALUMNI RELATION DIVISION':
            return 1;
        case 'LIBRARY':
            return 4;
        case 'DEPARTMENT':
            return 1;
        case 'SPORT UNIT':
        case 'HALL':
            return 0; // Auto-approved
        default:
            return 0;
    }
}

function getUploadedFilesCount($conn, $student_id, $office){
    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");
    
    $row = mysqli_fetch_assoc($q);
    
    if(!$row){
        return 0;
    }
    
    $files = json_decode($row['requirement_files'] ?? '[]', true);
    return is_array($files) ? count($files) : 0;
}

function getRequirementFilesList($conn, $student_id, $office){
    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");
    
    $row = mysqli_fetch_assoc($q);
    
    if(!$row){
        return [];
    }
    
    $files = json_decode($row['requirement_files'] ?? '[]', true);
    return is_array($files) ? $files : [];
}

// ============================================================
// FUNCTION TO GET FILE ICON BASED ON EXTENSION
// ============================================================
function getFileIcon($filename){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'bi-file-earmark-pdf',
        'doc' => 'bi-file-earmark-word',
        'docx' => 'bi-file-earmark-word',
        'jpg' => 'bi-file-earmark-image',
        'jpeg' => 'bi-file-earmark-image',
        'png' => 'bi-file-earmark-image',
        'gif' => 'bi-file-earmark-image',
        'webp' => 'bi-file-earmark-image',
        'bmp' => 'bi-file-earmark-image',
        'xls' => 'bi-file-earmark-excel',
        'xlsx' => 'bi-file-earmark-excel',
        'ppt' => 'bi-file-earmark-ppt',
        'pptx' => 'bi-file-earmark-ppt',
        'txt' => 'bi-file-earmark-text',
        'zip' => 'bi-file-earmark-zip',
        'rar' => 'bi-file-earmark-zip',
    ];
    
    return $icons[$ext] ?? 'bi-file-earmark';
}

// ============================================================
// FUNCTION TO CHECK IF FILE IS AN IMAGE
// ============================================================
function isImageFile($filename){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
}

// ============================================================
// FETCH STUDENTS DATA
// ============================================================
$query = mysqli_query($conn,"
SELECT 
    s.id,
    s.fullname,
    s.reg_number,
    s.faculty_name,
    s.department,
    s.programme,
    cs.status,
    cs.document_file,
    cs.requirement_files,
    cs.comment,
    cs.approved_at
FROM students s
INNER JOIN clearance_status cs ON s.id = cs.student_id
WHERE s.id IN ($ids_string)
AND UPPER(cs.department_role) = UPPER('$office')
ORDER BY s.id DESC
");

$students_data = [];
while($row = mysqli_fetch_assoc($query)){
    $students_data[] = $row;
}

$total_students = count($students_data);

if($total_students == 0){
    $_SESSION['alert_message'] = "No valid students found for this office.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Initialize count variables
$ready_count = 0;
$pending_count = 0;
$incomplete_count = 0;

// Calculate requirements for each student
foreach($students_data as &$student){
    $reg_number = $student['reg_number'];
    $student_id = $student['id'];
    $student_program = $student['programme'] ?? '';
    $current_status = strtolower($student['status'] ?? 'pending');
    
    $is_auto_approved = in_array($office, ['SPORT UNIT', 'HALL']);
    $uploaded_count = getUploadedFilesCount($conn, $student_id, $office);
    $required_count = getMinimumRequiredFiles($office, $reg_number, $student_program);
    $requirement_files = getRequirementFilesList($conn, $student_id, $office);
    
    $student['uploaded_count'] = $uploaded_count;
    $student['required_count'] = $required_count;
    $student['is_auto_approved'] = $is_auto_approved;
    $student['requirement_files'] = $requirement_files;
    $student['has_document'] = !empty($student['document_file']);
    $student['is_approved'] = $current_status == 'approved';
    $student['is_rejected'] = $current_status == 'rejected';
    $student['is_pending'] = $current_status == 'pending';
    $student['can_approve'] = (
        $student['is_pending'] && 
        $student['has_document'] &&
        ($is_auto_approved || $uploaded_count >= $required_count)
    );
    
    // Count statuses for summary
    if($student['can_approve']){
        $ready_count++;
    } elseif($student['is_pending'] && !$student['can_approve']){
        $pending_count++;
    } elseif(!$student['can_approve'] && !$student['is_approved'] && !$student['is_rejected']){
        $incomplete_count++;
    }
}
unset($student);

// ============================================================
// CSRF Token Function
// ============================================================
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
    <title>Bulk Preview - <?php echo htmlspecialchars($office); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <style>
        /* Matching design styles */
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .header-title i {
            color: var(--primary-blue);
            margin-right: 8px;
        }
        
        .header-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* Card */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            background: #ffffff;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 20px;
            font-weight: 600;
            font-family: 'Hanken Grotesk', sans-serif;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Alert */
        .alert {
            border-radius: 0.5rem;
            padding: 12px 16px;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .alert-warning {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
        
        .alert-warning .btn {
            flex-shrink: 0;
        }
        
        /* Buttons */
        .btn-back {
            background: #4b5563;
            color: #ffffff;
            padding: 8px 20px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 14px;
        }
        
        .btn-back:hover {
            background: #374151;
            color: #ffffff;
            text-decoration: none;
            transform: scale(0.95);
        }
        
        .btn-approve-all {
            background: #006c49;
            color: #ffffff;
            padding: 6px 16px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-approve-all:hover {
            background: #005236;
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-approve-all:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Student Card */
        .student-card {
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 16px;
            margin-bottom: 16px;
            background: #ffffff;
            transition: all 0.2s;
        }
        
        .student-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .student-card.border-success {
            border-left: 4px solid #10b981;
        }
        
        .student-card.border-info {
            border-left: 4px solid #0ea5e9;
        }
        
        .student-card.border-danger {
            border-left: 4px solid #ef4444;
        }
        
        .student-card.border-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .student-card .student-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .student-card .student-reg {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .status-badge-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge-ready {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge-incomplete {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Summary Box */
        .summary-box {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .summary-box .number {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .summary-box .number.primary { color: var(--primary-blue); }
        .summary-box .number.success { color: #006c49; }
        .summary-box .number.warning { color: #f59e0b; }
        .summary-box .number.danger { color: #ef4444; }
        
        .summary-box .label {
            font-size: 12px;
            color: var(--text-muted);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* File Gallery */
        .file-gallery {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 4px;
            flex-wrap: nowrap;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .file-gallery::-webkit-scrollbar {
            height: 6px;
        }
        
        .file-gallery::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .file-gallery::-webkit-scrollbar-thumb {
            background: #c1c7cd;
            border-radius: 10px;
        }
        
        .file-gallery::-webkit-scrollbar-thumb:hover {
            background: #a8b0b8;
        }
        
        /* File Card */
        .file-card {
            flex: 0 0 140px;
            min-width: 140px;
            max-width: 140px;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 8px;
            text-align: center;
            background: #ffffff;
            transition: all 0.2s;
            position: relative;
        }
        
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--primary-blue);
        }
        
        .file-card .file-thumbnail {
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 0.25rem;
            overflow: hidden;
            margin-bottom: 4px;
            cursor: pointer;
        }
        
        .file-card .file-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-card .file-thumbnail .file-icon {
            font-size: 48px;
            color: #6b7280;
        }
        
        .file-card .file-thumbnail .file-icon.pdf { color: #ef4444; }
        .file-card .file-thumbnail .file-icon.word { color: var(--primary-blue); }
        .file-card .file-thumbnail .file-icon.image { color: #10b981; }
        .file-card .file-thumbnail .file-icon.excel { color: #006c49; }
        .file-card .file-thumbnail .file-icon.ppt { color: #f59e0b; }
        
        .file-card .file-name {
            font-size: 11px;
            color: #495057;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
            margin: 0 auto;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .file-card .file-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 500;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .file-card .file-badge.main {
            background: var(--primary-blue);
            color: #ffffff;
        }
        
        .file-card .file-badge.requirement {
            background: #4b5563;
            color: #ffffff;
        }
        
        .file-card .file-actions {
            margin-top: 4px;
        }
        
        .file-card .file-actions .btn {
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 0.25rem;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
        }
        
        .file-card .file-actions .btn-primary {
            background: var(--primary-blue);
            color: #ffffff;
            border: none;
        }
        
        .file-card .file-actions .btn-primary:hover {
            background: var(--primary-dark);
            transform: scale(0.95);
        }
        
        .file-card .file-actions .btn-secondary {
            background: #4b5563;
            color: #ffffff;
            border: none;
        }
        
        .file-card .file-actions .btn-secondary:hover {
            background: #374151;
            transform: scale(0.95);
        }
        
        /* Requirement check */
        .requirement-check {
            font-size: 13px;
            padding: 4px 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .requirement-check .icon {
            font-size: 16px;
        }
        
        .text-success { color: #006c49 !important; }
        .text-danger { color: #ef4444 !important; }
        
        /* Checkbox */
        .checkbox-col {
            width: 40px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* Legend */
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .legend .badge {
            font-family: 'Hanken Grotesk', sans-serif;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .page-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .header-title { font-size: 20px; }
            .student-card { padding: 12px; }
            .file-card { flex: 0 0 100px; min-width: 100px; max-width: 100px; }
            .file-card .file-thumbnail { height: 75px; }
            .file-card .file-thumbnail .file-icon { font-size: 36px; }
            .summary-box .number { font-size: 22px; }
        }
        
        @media (max-width: 576px) {
            .container { padding: 12px; }
            .header-title { font-size: 18px; }
            .card-body { padding: 14px; }
            .card-header { padding: 12px 14px; flex-direction: column; align-items: stretch; gap: 8px; }
            .student-card .row { flex-direction: column; gap: 10px; }
            .checkbox-col { width: 100%; }
            .file-gallery { gap: 6px; }
            .file-card { flex: 0 0 80px; min-width: 80px; max-width: 80px; }
            .file-card .file-thumbnail { height: 60px; }
            .file-card .file-thumbnail .file-icon { font-size: 28px; }
            .file-card .file-name { font-size: 9px; max-width: 70px; }
            .file-card .file-badge { font-size: 8px; padding: 1px 6px; }
            .file-card .file-actions .btn { font-size: 8px; padding: 1px 6px; }
            .summary-box .number { font-size: 18px; }
            .summary-box .label { font-size: 10px; }
            .btn-back, .btn-approve-all { font-size: 12px; padding: 5px 12px; }
            .legend { gap: 6px; }
            .legend .badge { font-size: 10px; }
        }
        
        @media (max-width: 400px) {
            .container { padding: 8px; }
            .header-title { font-size: 16px; }
            .file-card { flex: 0 0 70px; min-width: 70px; max-width: 70px; }
            .file-card .file-thumbnail { height: 50px; }
            .file-card .file-thumbnail .file-icon { font-size: 24px; }
            .file-card .file-name { font-size: 8px; max-width: 60px; }
            .status-badge { font-size: 10px; padding: 2px 8px; }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="header-title">
                <i class="bi bi-eye"></i> Bulk Preview
            </h1>
            <p class="header-subtitle">
                Review <?php echo $total_students; ?> student(s) before approving
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Signature Status -->
    <?php if(empty($signature)){ ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <span class="flex-grow-1">You must upload your digital signature before approving students.</span>
        <a href="profile.php" class="btn btn-primary btn-sm" style="background: var(--primary-blue); color: #fff; border: none; border-radius: 0.375rem; padding: 4px 14px; font-family: 'Hanken Grotesk', sans-serif;">
            <i class="bi bi-upload"></i> Upload Signature
        </a>
    </div>
    <?php } ?>

    <!-- Summary -->
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="summary-box">
                        <div class="number primary"><?php echo $total_students; ?></div>
                        <div class="label">Total Selected</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-box">
                        <div class="number success"><?php echo $ready_count; ?></div>
                        <div class="label">Ready to Approve</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-box">
                        <div class="number warning"><?php echo $pending_count; ?></div>
                        <div class="label">Pending Review</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="summary-box">
                        <div class="number danger"><?php echo $incomplete_count; ?></div>
                        <div class="label">Incomplete</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students List -->
    <div class="card">
        <div class="card-header">
            <span><i class="bi bi-people"></i> Student Documents Preview</span>
            <button type="button" class="btn-approve-all" id="bulkApproveBtn" onclick="confirmBulkApprove()" <?php echo empty($signature) ? 'disabled' : ''; ?>>
                <i class="bi bi-check2-all"></i> Approve All Ready
            </button>
        </div>
        <div class="card-body">
            
            <form id="bulkApproveForm" method="POST" action="bulk-approve.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                
                <?php foreach($students_data as $student): 
                    $status = strtolower($student['status']);
                    $is_ready = $student['can_approve'];
                    $is_approved = $student['is_approved'];
                    $is_rejected = $student['is_rejected'];
                    $is_pending = $student['is_pending'];
                    
                    // Get main file info
                    $main_file = $student['document_file'] ?? '';
                    $has_main = !empty($main_file);
                    $main_is_image = $has_main && isImageFile($main_file);
                    $main_icon = $has_main ? getFileIcon($main_file) : '';
                ?>
                <div class="student-card <?php echo $is_ready ? 'border-success' : ($is_approved ? 'border-info' : ($is_rejected ? 'border-danger' : 'border-warning')); ?>">
                    <div class="row align-items-start g-2">
                        <!-- Checkbox -->
                        <div class="col-auto checkbox-col">
                            <?php if($is_pending && $is_ready){ ?>
                                <input type="checkbox" class="student-checkbox form-check-input" name="student_ids[]" value="<?php echo $student['id']; ?>" checked>
                            <?php } elseif($is_pending && !$is_ready){ ?>
                                <input type="checkbox" class="student-checkbox form-check-input" name="student_ids[]" value="<?php echo $student['id']; ?>" disabled>
                            <?php } else { ?>
                                <input type="checkbox" class="form-check-input" disabled>
                            <?php } ?>
                        </div>
                        
                        <!-- Student Info -->
                        <div class="col-md-3">
                            <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                            <div class="student-reg"><?php echo htmlspecialchars($student['reg_number']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($student['department']); ?></div>
                            <div class="text-muted small" style="font-size:11px;"><?php echo htmlspecialchars($student['programme']); ?></div>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-2">
                            <div class="mb-1">
                                <?php if($is_approved){ ?>
                                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Approved</span>
                                <?php } elseif($is_rejected){ ?>
                                    <span class="status-badge status-badge-rejected"><i class="bi bi-x-circle"></i> Rejected</span>
                                <?php } elseif($is_ready){ ?>
                                    <span class="status-badge status-badge-ready"><i class="bi bi-check-circle"></i> Ready</span>
                                <?php } else { ?>
                                    <span class="status-badge status-badge-incomplete"><i class="bi bi-clock"></i> Incomplete</span>
                                <?php } ?>
                            </div>
                            <?php if(!$is_approved && !$is_rejected): ?>
                            <div class="requirement-check">
                                <?php if($student['is_auto_approved']){ ?>
                                    <span class="text-success"><i class="bi bi-check-circle icon"></i> Auto-approved</span>
                                <?php } else { ?>
                                    <span class="<?php echo $student['uploaded_count'] >= $student['required_count'] ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi <?php echo $student['uploaded_count'] >= $student['required_count'] ? 'bi-check-circle' : 'bi-x-circle'; ?> icon"></i>
                                        <?php echo $student['uploaded_count']; ?>/<?php echo $student['required_count']; ?> files
                                    </span>
                                <?php } ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Documents Gallery - Horizontal -->
                        <div class="col-md-7">
                            <!-- File Gallery -->
                            <div class="file-gallery">
                                
                                <!-- Main Clearance Form -->
                                <?php if($has_main): ?>
                                <div class="file-card" title="<?php echo htmlspecialchars($main_file); ?>">
                                    <div class="file-thumbnail" onclick="window.open('../assets/uploads/<?php echo $main_file; ?>', '_blank')">
                                        <?php if($main_is_image): ?>
                                            <img src="../assets/uploads/<?php echo $main_file; ?>" alt="<?php echo htmlspecialchars($main_file); ?>">
                                        <?php else: ?>
                                            <i class="bi <?php echo $main_icon; ?> file-icon pdf"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-name" title="<?php echo htmlspecialchars($main_file); ?>">
                                        <?php echo htmlspecialchars(substr($main_file, 11)); ?>
                                    </div>
                                    <span class="file-badge main">Main</span>
                                    <div class="file-actions">
                                        <a href="../assets/uploads/<?php echo $main_file; ?>" target="_blank" class="btn btn-primary">View</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Requirement Files -->
                                <?php if(!empty($student['requirement_files'])): ?>
                                    <?php foreach($student['requirement_files'] as $req_file): ?>
                                    <?php $req_is_image = isImageFile($req_file); ?>
                                    <?php $req_icon = getFileIcon($req_file); ?>
                                    <div class="file-card" title="<?php echo htmlspecialchars($req_file); ?>">
                                        <div class="file-thumbnail" onclick="window.open('../assets/uploads/requirements/<?php echo $req_file; ?>', '_blank')">
                                            <?php if($req_is_image): ?>
                                                <img src="../assets/uploads/requirements/<?php echo $req_file; ?>" alt="<?php echo htmlspecialchars($req_file); ?>">
                                            <?php else: ?>
                                                <i class="bi <?php echo $req_icon; ?> file-icon <?php echo strpos($req_icon, 'pdf') !== false ? 'pdf' : (strpos($req_icon, 'word') !== false ? 'word' : (strpos($req_icon, 'image') !== false ? 'image' : '')); ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="file-name" title="<?php echo htmlspecialchars($req_file); ?>">
                                            <?php echo htmlspecialchars(substr($req_file, 11)); ?>
                                        </div>
                                        <span class="file-badge requirement">Req</span>
                                        <div class="file-actions">
                                            <a href="../assets/uploads/requirements/<?php echo $req_file; ?>" target="_blank" class="btn btn-secondary">View</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <!-- No Files Message -->
                                <?php if(!$has_main && empty($student['requirement_files'])): ?>
                                <div class="text-muted" style="padding: 20px 10px; text-align: center; width: 100%;">
                                    <i class="bi bi-file-earmark-x" style="font-size: 32px;"></i>
                                    <div style="font-family: 'Hanken Grotesk', sans-serif;">No files uploaded</div>
                                </div>
                                <?php endif; ?>
                                
                            </div>
                            
                            <!-- Comment -->
                            <?php if(!empty($student['comment'])): ?>
                            <div class="mt-1">
                                <small class="text-muted" style="font-family: 'Hanken Grotesk', sans-serif;">
                                    <i class="bi bi-chat"></i> <?php echo htmlspecialchars($student['comment']); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
            
            <!-- Legend -->
            <div class="mt-3 pt-3 border-top">
                <div class="legend">
                    <span><span class="badge bg-success">Ready</span> = Can be approved</span>
                    <span><span class="badge bg-warning text-dark">Incomplete</span> = Missing requirements</span>
                    <span><span class="badge bg-info">Approved</span> = Already approved</span>
                    <span><span class="badge bg-danger">Rejected</span> = Previously rejected</span>
                    <span class="text-muted small" style="font-family: 'Hanken Grotesk', sans-serif;">
                        <i class="bi bi-info-circle"></i> Click on any file thumbnail to preview
                    </span>
                </div>
            </div>
            
        </div>
    </div>

</div>

<script>
function confirmBulkApprove() {
    var checkboxes = document.querySelectorAll('.student-checkbox:checked');
    var count = checkboxes.length;
    
    if(count === 0){
        Swal.fire({
            icon: 'warning',
            title: 'No Students Ready',
            text: 'No students are ready for approval. Please check the requirements.',
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    Swal.fire({
        title: 'Approve Selected Students?',
        html: 'You are about to approve <strong>' + count + '</strong> student' + (count !== 1 ? 's' : '') + '. This action will approve their clearance requests using your digital signature.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#006c49',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Approve Selected',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            document.getElementById('bulkApproveForm').submit();
        }
    });
}

// Auto-select all ready students
document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(function(cb) {
        if(!cb.disabled){
            cb.checked = true;
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>