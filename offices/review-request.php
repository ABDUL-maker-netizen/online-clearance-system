<?php
session_start();

include '../includes/config.php';
include '../includes/functions.php';
include '../includes/csrf.php';

/* ==============================
   CHECK SESSION - Support both old and new session variables
============================== */
$officer_id = isset($_SESSION['officer_id']) ? $_SESSION['officer_id'] : (isset($_SESSION['office_officer_id']) ? $_SESSION['office_officer_id'] : null);
$office = isset($_SESSION['office']) ? $_SESSION['office'] : (isset($_SESSION['office_name']) ? $_SESSION['office_name'] : null);
$user_role = isset($_SESSION['office_role']) ? $_SESSION['office_role'] : null;

if(!$officer_id || !$office){
    header("Location: login.php");
    exit();
}

$office = strtoupper($office);
$student_id = intval($_GET['id'] ?? 0);

if($student_id <= 0){
    header("Location: dashboard.php");
    exit();
}

/* ==============================
   STUDENT DATA
============================== */
$student_q = mysqli_query($conn,
"SELECT * FROM students WHERE id='$student_id' LIMIT 1");

$student = mysqli_fetch_assoc($student_q);

if(!$student){
    die("Student not found");
}

/* ==============================
   CURRENT STATUS
============================== */
$status_q = mysqli_query($conn,
"SELECT * FROM clearance_status
WHERE student_id='$student_id'
AND department_role='$office'
ORDER BY id DESC
LIMIT 1");

$current = mysqli_fetch_assoc($status_q);

/* ==============================
   MAIN FILE
============================== */
$document = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT filename FROM clearance_uploads
WHERE student_id='$student_id'
ORDER BY id DESC
LIMIT 1
"));

/* ==============================
   REQUIREMENT RULE
============================== */
function officeRule($office, $student){

    $reg = strtoupper($student['reg_number']);
    $program = strtolower($student['programme'] ?? '');

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
            
            $max_uploads = $years + 2; // +2 buffer for carryover
            
            return "Fees proof required (Upload all session receipts - Max $max_uploads files)";

        case 'ALUMNI RELATION DIVISION':
            return "Alumni receipt required";

        case 'LIBRARY':
            return "Library card + 3 borrower cards required";

        case 'SPORT UNIT':
        case 'HALL':
            return "No requirement (auto approve)";

        case 'DEPARTMENT':
            return "Department clearance form required (1 file only)";

        default:
            return "General requirement";
    }
}

/* ==============================
   REQUIREMENT FILES
============================== */
function getRequirementFiles($conn, $student_id, $office){

    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");

    $row = mysqli_fetch_assoc($q);

    $files = [];

    if($row && !empty($row['requirement_files'])){
        $decoded = json_decode($row['requirement_files'], true);
        if(is_array($decoded)){
            $files = $decoded;
        }
    }

    return $files;
}

$files = getRequirementFiles($conn, $student_id, $office);

/* ==============================
   GET OFFICER SIGNATURE STATUS - ONLY FROM officers TABLE
============================== */
$has_signature = false;
$signature_file = '';

// Only check the officers table
$sig_q = mysqli_query($conn,"
    SELECT digital_signature
    FROM officers
    WHERE id='$officer_id'
    LIMIT 1
");

if(mysqli_num_rows($sig_q) > 0){
    $sig_data = mysqli_fetch_assoc($sig_q);
    if(!empty($sig_data['digital_signature'])){
        $has_signature = true;
        $signature_file = $sig_data['digital_signature'];
    }
}

// Check if signature file physically exists
if($has_signature && !empty($signature_file)){
    $sig_file_path = "../assets/uploads/signatures/" . $signature_file;
    if(!file_exists($sig_file_path)){
        $has_signature = false;
        $signature_file = '';
    }
}

/* ==============================
   CHECK IF OFFICE HAS UPLOAD REQUIREMENTS
============================== */
function hasRequirementFiles($conn, $student_id, $office){
    // Skip check for auto-approved offices
    if(in_array($office, ['SPORT UNIT', 'HALL'])){
        return true; // Auto-approved, no files needed
    }
    
    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");
    
    $row = mysqli_fetch_assoc($q);
    
    if(!$row){
        return false;
    }
    
    $files = json_decode($row['requirement_files'] ?? '[]', true);
    return is_array($files) && count($files) > 0;
}

/* ==============================
   GET MINIMUM REQUIRED FILES - DYNAMIC FOR BURSAR
============================== */
function getMinimumRequiredFiles($office, $student){
    $reg = strtoupper($student['reg_number']);
    $program = strtolower($student['programme'] ?? '');
    
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
            
            // Return the years count (minimum required)
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

/* ==============================
   GET UPLOADED FILES COUNT
============================== */
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

/* ==============================
   APPROVE (WITH SIGNATURE CHECK)
============================== */
if(isset($_POST['approve'])){

    verify_csrf();

    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // 🔴 BLOCK APPROVAL IF NO SIGNATURE IN OFFICERS TABLE
    if(!$has_signature){
        $error = "You cannot approve requests without uploading your digital signature. Please upload your signature in your profile.";
    } else {
        // ✅ CHECK: Auto-approved offices (SPORT UNIT, HALL) - Always approve
        if(in_array($office, ['SPORT UNIT', 'HALL'])){
            // Auto-approve - no requirement files needed
            $can_approve = true;
        } else {
            // ✅ CHECK: Student has uploaded requirement files
            $uploaded_count = getUploadedFilesCount($conn, $student_id, $office);
            $required_count = getMinimumRequiredFiles($office, $student);
            
            if($uploaded_count == 0){
                $error = "Student has not uploaded any requirement files for this office. Please ask the student to upload the required documents before approving.";
            } elseif($uploaded_count < $required_count){
                $error = "Student has uploaded $uploaded_count out of $required_count required files. Please ask the student to upload all required documents before approving.";
            } else {
                $can_approve = true;
            }
        }

        if(isset($can_approve) && $can_approve){
            // Check if requirementValid function exists
            if(function_exists('requirementValid')){
                list($valid, $msg) = requirementValid($office, $student_id, $conn);
            } else {
                $valid = true;
                $msg = "";
            }

            if(!$valid){
                $error = $msg;
            } else {

                mysqli_query($conn,"
                INSERT INTO clearance_status
                (student_id, department_role, status, comment, approved_by, approved_at, student_name, reg_number)
                VALUES
                ('$student_id','$office','approved',
                '$comment',
                '$officer_id',NOW(),
                '{$student['fullname']}','{$student['reg_number']}')
                ON DUPLICATE KEY UPDATE
                status='approved',
                comment=VALUES(comment),
                approved_by=VALUES(approved_by),
                approved_at=VALUES(approved_at),
                student_name=VALUES(student_name),
                reg_number=VALUES(reg_number)
                ");

                updateClearanceStatus(
                    $conn,
                    $student_id,
                    $office,
                    'approved'
                );

                // ✅ UPDATE DASHBOARD STATUSES
                updateDashboardStatuses($conn, $student_id);

                // ✅ SET SUCCESS MESSAGE
                $_SESSION['alert_message'] = "Student request approved successfully!";
                $_SESSION['alert_type'] = 'approved';
                
                header("Location: dashboard.php");
                exit();
            }
        }
    }
}

/* ==============================
   REJECT (NO SIGNATURE REQUIRED)
============================== */
if(isset($_POST['reject'])){

    verify_csrf();

    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    mysqli_query($conn,"
    INSERT INTO clearance_status
    (student_id, department_role, status, comment, approved_by, approved_at, student_name, reg_number)
    VALUES
    ('$student_id','$office','rejected',
    '$comment',
    '$officer_id',NOW(),
    '{$student['fullname']}','{$student['reg_number']}')
    ON DUPLICATE KEY UPDATE
    status='rejected',
    comment=VALUES(comment),
    approved_by=VALUES(approved_by),
    approved_at=VALUES(approved_at),
    student_name=VALUES(student_name),
    reg_number=VALUES(reg_number)
    ");

    updateClearanceStatus(
        $conn,
        $student_id,
        $office,
        'rejected'
    );
    
    // ✅ UPDATE DASHBOARD STATUSES
    updateDashboardStatuses($conn, $student_id);
    
    // ✅ SET ERROR MESSAGE
    $_SESSION['alert_message'] = "Student request rejected successfully!";
    $_SESSION['alert_type'] = 'rejected';
    
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review Panel - <?php echo htmlspecialchars($office); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
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
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            padding: 24px 20px;
            margin: 0 auto;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 12px;
            }
        }
        
        /* Card */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            background: #ffffff;
            padding: 24px;
        }
        
        @media (max-width: 576px) {
            .card {
                padding: 16px;
            }
        }
        
        @media (max-width: 400px) {
            .card {
                padding: 12px;
            }
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .page-header h3 {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 22px;
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .page-header h3 i {
            color: var(--primary-blue);
            margin-right: 10px;
        }
        
        @media (max-width: 576px) {
            .page-header h3 {
                font-size: 18px;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        @media (max-width: 400px) {
            .page-header h3 {
                font-size: 16px;
            }
        }
        
        /* Buttons */
        .btn-back {
            background: #4b5563;
            color: #ffffff;
            padding: 8px 20px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .btn-back:hover {
            background: #374151;
            color: #ffffff;
            text-decoration: none;
            transform: scale(0.95);
        }
        
        .btn-approve {
            background: #006c49;
            color: #ffffff;
            padding: 8px 25px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-approve:hover {
            background: #005236;
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-approve:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-reject {
            background: #dc2626;
            color: #ffffff;
            padding: 8px 25px;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-reject:hover {
            background: #b91c1c;
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-view {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 4px 14px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            color: #ffffff;
            text-decoration: none;
            transform: scale(0.95);
        }
        
        .btn-upload-signature {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 4px 14px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .btn-upload-signature:hover {
            background: var(--primary-dark);
            color: #ffffff;
            text-decoration: none;
            transform: scale(0.95);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 576px) {
            .btn-approve, .btn-reject, .btn-back {
                width: 100%;
                justify-content: center;
                font-size: 13px;
                padding: 8px 16px;
            }
            .button-group {
                flex-direction: column;
            }
        }
        
        @media (max-width: 400px) {
            .btn-approve, .btn-reject, .btn-back {
                font-size: 12px;
                padding: 6px 12px;
            }
        }
        
        /* Info Card */
        .info-card {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        
        .info-card p {
            margin-bottom: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .info-card p b {
            color: var(--text-dark);
        }
        
        .info-card p:last-child {
            margin-bottom: 0;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 0.5rem;
            padding: 12px 16px;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .alert-info {
            background-color: #e8f0fe;
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .alert-success {
            background-color: #d1fae5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
        
        .alert-secondary {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: #374151;
        }
        
        .alert .btn {
            flex-shrink: 0;
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
        
        /* Requirement Check */
        .requirement-check {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 12px 16px;
            border-left: 4px solid var(--primary-blue);
            margin-bottom: 16px;
        }
        
        .requirement-check .status-badge {
            padding: 3px 10px;
            font-size: 11px;
        }
        
        /* Signature Status Box */
        .signature-status-box {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 12px 16px;
            border-left: 4px solid #006c49;
            margin-bottom: 16px;
        }
        
        .signature-status-box.no-signature {
            border-left-color: #dc2626;
        }
        
        /* File List */
        .file-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .file-list .list-group-item {
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            margin-bottom: 4px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .file-list .list-group-item a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 13px;
        }
        
        .file-list .list-group-item a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }
        
        /* Info Note */
        .info-note {
            background: #e8f0fe;
            border-radius: 0.75rem;
            padding: 10px 14px;
            border-left: 4px solid var(--primary-blue);
            font-size: 13px;
            font-family: 'Hanken Grotesk', sans-serif;
            margin-bottom: 16px;
        }
        
        .info-note i {
            color: var(--primary-blue);
        }
        
        /* Heading */
        .section-heading {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-dark);
            margin: 16px 0 8px 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .section-heading i {
            color: var(--primary-blue);
            margin-right: 6px;
        }
        
        /* Divider */
        hr {
            border-color: var(--border-color);
            opacity: 0.5;
            margin: 16px 0;
        }
        
        /* Form Controls */
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-sizing: border-box;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
            background: #ffffff;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            outline: none;
        }
        
        .mb-3 {
            margin-bottom: 16px;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .alert {
                font-size: 13px;
                padding: 10px 12px;
            }
            .info-card {
                padding: 12px;
            }
            .info-card p {
                font-size: 13px;
            }
            .requirement-check {
                padding: 10px 12px;
            }
            .signature-status-box {
                padding: 10px 12px;
            }
            .section-heading {
                font-size: 14px;
            }
            .file-list .list-group-item a {
                font-size: 12px;
            }
            .btn-view {
                font-size: 11px;
                padding: 3px 10px;
            }
        }
        
        @media (max-width: 400px) {
            .card {
                padding: 12px;
            }
            .info-card p {
                font-size: 12px;
            }
            .alert {
                font-size: 12px;
                padding: 8px 10px;
            }
            .status-badge {
                font-size: 10px;
                padding: 3px 8px;
            }
            .section-heading {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <!-- Header -->
        <div class="page-header">
            <h3><i class="bi bi-clipboard"></i> <?php echo htmlspecialchars($office); ?> REVIEW PANEL</h3>
            <a href="dashboard.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <hr>

        <!-- Student Information -->
        <div class="section-heading"><i class="bi bi-person"></i> Student Info</div>
        <div class="info-card">
            <p><b>Name:</b> <?php echo htmlspecialchars($student['fullname']); ?></p>
            <p><b>Reg No:</b> <?php echo htmlspecialchars($student['reg_number']); ?></p>
            <p><b>Faculty:</b> <?php echo htmlspecialchars($student['faculty_name']); ?></p>
            <p><b>Department:</b> <?php echo htmlspecialchars($student['department']); ?></p>
            <p><b>Programme:</b> <?php echo htmlspecialchars($student['programme']); ?></p>
        </div>

        <!-- ✅ SIGNATURE STATUS - Display clearly -->
        <div class="signature-status-box <?php echo $has_signature ? '' : 'no-signature'; ?>">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><i class="bi bi-pencil"></i> Digital Signature Status</strong>
                </div>
                <?php if($has_signature){ ?>
                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Uploaded</span>
                <?php } else { ?>
                    <span class="status-badge status-badge-rejected"><i class="bi bi-x-circle"></i> Not Uploaded</span>
                <?php } ?>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <?php if($has_signature){ ?>
                        <span class="text-success">✅ Your digital signature is uploaded and ready.</span>
                        <?php if(!empty($signature_file)){ ?>
                            <br>
                            <span class="text-muted">File: <?php echo htmlspecialchars($signature_file); ?></span>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="text-danger">❌ You have not uploaded your digital signature.</span>
                        <br>
                        <span class="text-muted">Please go to <a href="profile.php" class="text-primary">Profile</a> to upload your signature.</span>
                    <?php } ?>
                </small>
            </div>
        </div>

        <!-- Requirement -->
        <div class="section-heading"><i class="bi bi-list-check"></i> Requirement</div>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> <?php echo officeRule($office, $student); ?>
        </div>

        <!-- ✅ REQUIREMENT FILE CHECK -->
        <?php 
        $uploaded_count = getUploadedFilesCount($conn, $student_id, $office);
        $required_count = getMinimumRequiredFiles($office, $student);
        $has_files = hasRequirementFiles($conn, $student_id, $office);
        $is_auto_approved = in_array($office, ['SPORT UNIT', 'HALL']);
        ?>
        
        <div class="requirement-check">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><i class="bi bi-file-check"></i> Requirement Files Status</strong>
                </div>
                <?php if($is_auto_approved){ ?>
                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Auto-Approved</span>
                <?php } elseif($has_files && $uploaded_count >= $required_count){ ?>
                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Complete (<?php echo $uploaded_count; ?>/<?php echo $required_count; ?>)</span>
                <?php } elseif($has_files && $uploaded_count < $required_count){ ?>
                    <span class="status-badge status-badge-pending"><i class="bi bi-exclamation-triangle"></i> Incomplete (<?php echo $uploaded_count; ?>/<?php echo $required_count; ?>)</span>
                <?php } else { ?>
                    <span class="status-badge status-badge-rejected"><i class="bi bi-x-circle"></i> No Files Uploaded</span>
                <?php } ?>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <?php if($is_auto_approved){ ?>
                        This office does not require any file uploads.
                    <?php } elseif($uploaded_count == 0){ ?>
                        <span class="text-danger">⚠️ Student has not uploaded any requirement files.</span>
                    <?php } elseif($uploaded_count < $required_count){ ?>
                        <span class="text-warning">⚠️ Student needs to upload <?php echo $required_count - $uploaded_count; ?> more file(s).</span>
                    <?php } else { ?>
                        <span class="text-success">✅ All required files have been uploaded.</span>
                    <?php } ?>
                </small>
            </div>
        </div>

        <!-- ℹ️ NOTE: Only Department Letter needs signature -->
        <?php if($office != 'DEPARTMENT'){ ?>
        <div class="info-note">
            <i class="bi bi-info-circle"></i>
            <strong>Note:</strong> This office only needs to verify the uploaded requirement files. No signature is required on these files. Only the Department Letter requires a signature.
        </div>
        <?php } ?>

        <!-- Main File -->
        <div class="section-heading"><i class="bi bi-file-earmark"></i> Main File</div>
        <?php if(!empty($document['filename'])){ ?>
            <a target="_blank" class="btn-view" href="../assets/uploads/<?php echo $document['filename']; ?>">
                <i class="bi bi-eye"></i> View Main File
            </a>
        <?php } else { ?>
            <p class="text-danger" style="font-family: 'Hanken Grotesk', sans-serif;"><i class="bi bi-exclamation-circle"></i> No file uploaded</p>
        <?php } ?>

        <!-- Requirement Files -->
        <hr>
        <div class="section-heading"><i class="bi bi-paperclip"></i> Requirement Files</div>
        <?php if(count($files) > 0){ ?>
            <div class="file-list">
                <ul class="list-group">
                <?php foreach($files as $file){ ?>
                    <li class="list-group-item">
                        <a target="_blank" href="../assets/uploads/requirements/<?php echo $file; ?>">
                            <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($file); ?>
                        </a>
                    </li>
                <?php } ?>
                </ul>
            </div>
        <?php } else { ?>
            <p class="text-muted" style="font-family: 'Hanken Grotesk', sans-serif;"><i class="bi bi-info-circle"></i> No requirement files uploaded.</p>
        <?php } ?>

        <!-- Current Status -->
        <?php if($current){ ?>
            <hr>
            <div class="alert alert-secondary">
                <i class="bi bi-info-circle"></i> Current Status: 
                <?php
                $status = strtolower($current['status']);
                if($status == 'approved'){
                    echo "<span class='status-badge status-badge-approved'><i class='bi bi-check-circle'></i> Approved</span>";
                } elseif($status == 'rejected') {
                    echo "<span class='status-badge status-badge-rejected'><i class='bi bi-x-circle'></i> Rejected</span>";
                } else {
                    echo "<span class='status-badge status-badge-pending'><i class='bi bi-clock'></i> Pending</span>";
                }
                ?>
            </div>
        <?php } ?>

        <!-- Error Message -->
        <?php if(isset($error)){ ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                <?php if(!$has_signature){ ?>
                    <a href="profile.php" class="btn-upload-signature">
                        <i class="bi bi-upload"></i> Upload Signature
                    </a>
                <?php } ?>
            </div>
        <?php } ?>

        <!-- Signature Status Warning -->
        <?php if(!$has_signature){ ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span class="flex-grow-1">You cannot approve requests without uploading your digital signature.</span>
                <a href="profile.php" class="btn-upload-signature">
                    <i class="bi bi-upload"></i> Upload Signature
                </a>
            </div>
        <?php } ?>

        <!-- Action Buttons -->
        <?php if(!$current || strtolower($current['status']) == 'pending'){ ?>
            <hr>
            <?php 
            // Check if approval should be disabled
            $approve_disabled = false;
            $disable_reason = "";
            
            if(!$has_signature){
                $approve_disabled = true;
                $disable_reason = "Upload signature first";
            } elseif(!$is_auto_approved && $uploaded_count < $required_count){
                $approve_disabled = true;
                $disable_reason = "Student needs to upload all required files ($uploaded_count/$required_count)";
            } elseif(!$is_auto_approved && $uploaded_count == 0){
                $approve_disabled = true;
                $disable_reason = "No requirement files uploaded";
            }
            ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="mb-3">
                    <label class="form-label">Comment</label>
                    <textarea name="comment" class="form-control" rows="3" placeholder="Enter your comment here..." required></textarea>
                </div>
                <div class="button-group">
                    <button type="submit" name="approve" class="btn-approve" <?php echo $approve_disabled ? 'disabled' : ''; ?>>
                        <i class="bi bi-check-circle"></i> Approve
                    </button>
                    <button type="submit" name="reject" class="btn-reject">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                </div>
                <?php if($approve_disabled){ ?>
                    <small class="text-danger d-block mt-2" style="font-family: 'Hanken Grotesk', sans-serif;">
                        <i class="bi bi-lock-fill"></i> Approve button is disabled: <?php echo $disable_reason; ?>
                    </small>
                <?php } ?>
            </form>
        <?php } else { ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i> This request has already been processed.
            </div>
        <?php } ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>