<?php
session_start();

include '../../includes/config.php';
include '../../includes/functions.php';
include '../../includes/csrf.php';

/* =========================
   SECURITY
========================= */
if(!isset($_SESSION['department_officer_id'])){
    header("Location: login.php");
    exit();
}

$office = 'DEPARTMENT';

$student_id = intval($_GET['id'] ?? 0);

if($student_id <= 0){
    header("Location: dashboard.php");
    exit();
}

/* =========================
   STUDENT DATA
========================= */
$student_q = mysqli_query($conn,
"SELECT * FROM students
WHERE id='$student_id'
LIMIT 1");

$student = mysqli_fetch_assoc($student_q);

if(!$student){
    die("Student not found");
}

/* =========================
   CURRENT STATUS
========================= */
$status_q = mysqli_query($conn,
"SELECT *
FROM clearance_status
WHERE student_id='$student_id'
AND department_role='DEPARTMENT'
ORDER BY id DESC
LIMIT 1");

$current = mysqli_fetch_assoc($status_q);

/* =========================
   MAIN CLEARANCE FILE
========================= */
$document = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT filename
FROM clearance_uploads
WHERE student_id='$student_id'
ORDER BY id DESC
LIMIT 1
"));

/* =========================
   REQUIREMENT FILES
========================= */
$files = [];

if($current && !empty($current['requirement_files'])){
    $decoded = json_decode(
        $current['requirement_files'],
        true
    );
    if(is_array($decoded)){
        $files = $decoded;
    }
}

/* =========================
   GET OFFICER SIGNATURE STATUS
========================= */
$sig_q = mysqli_query($conn,"
    SELECT digital_signature
    FROM department_officers
    WHERE id='{$_SESSION['department_officer_id']}'
    LIMIT 1
");

$sig_data = mysqli_fetch_assoc($sig_q);
$has_signature = !empty($sig_data['digital_signature']);

/* =========================
   CHECK IF DEPARTMENT LETTER EXISTS AND IS SIGNED
========================= */
function hasDepartmentLetter($conn, $student_id){
    $q = mysqli_query($conn,"
        SELECT id, status, original_file
        FROM department_letters
        WHERE student_id='$student_id'
        LIMIT 1
    ");
    return mysqli_fetch_assoc($q);
}

$letter_data = hasDepartmentLetter($conn, $student_id);
$has_letter = $letter_data && !empty($letter_data['original_file']);
$letter_uploaded = $has_letter;
$letter_signed = $letter_data && $letter_data['status'] == 'signed';

/* =========================
   ✅ CHECK FINAL YEAR PROJECT (FYP)
========================= */
$fyp_q = mysqli_query($conn,"
    SELECT fyp_file, uploaded_at
    FROM final_year_projects
    WHERE student_id='$student_id'
    LIMIT 1
");
$fyp_data = mysqli_fetch_assoc($fyp_q);
$has_fyp = $fyp_data && !empty($fyp_data['fyp_file']);

/* =========================
   APPROVE
========================= */
if(isset($_POST['approve'])){

    verify_csrf();

    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    /* =========================
       CHECK SIGNATURE FIRST
    ========================= */
    if(!$has_signature){
        $error = "You cannot approve requests without uploading your digital signature.";
    } else {
        /* =========================
           ✅ CHECK 1: Department letter must be uploaded and signed first
        ========================= */
        if(!$has_letter){
            $error = "Student has not uploaded the department letter. Please ask the student to upload the department clearance letter first.";
        } elseif(!$letter_signed){
            $error = "The department letter has been uploaded but not signed yet. Please go to the Department Letters section and sign the letter first.";
        } 
        /* =========================
           ✅ CHECK 2: Final Year Project must be uploaded
        ========================= */
        elseif(!$has_fyp){
            $error = "Student has not uploaded the Final Year Project. Please ask the student to upload their Final Year Project before approving.";
        } else {
            /* =========================
               APPROVE ONLY IF ALL CHECKS PASS
            ========================= */
            mysqli_query($conn,"
            INSERT INTO clearance_status
            (
                student_id,
                department_role,
                status,
                comment,
                approved_by,
                approved_at,
                student_name,
                reg_number
            )
            VALUES
            (
                '$student_id',
                'DEPARTMENT',
                'approved',
                '$comment',
                '{$_SESSION['department_officer_id']}',
                NOW(),
                '{$student['fullname']}',
                '{$student['reg_number']}'
            )
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
                'DEPARTMENT',
                'approved'
            );

            // ✅ UPDATE DASHBOARD STATUSES
            updateDashboardStatuses($conn, $student_id);

            $_SESSION['alert_message'] = "Student request approved successfully!";
            $_SESSION['alert_type'] = 'approved';
            
            header("Location: dashboard.php");
            exit();
        }
    }
}

/* =========================
   REJECT
========================= */
if(isset($_POST['reject'])){

    verify_csrf();

    $comment = mysqli_real_escape_string(
        $conn,
        $_POST['comment']
    );

    mysqli_query($conn,"
    INSERT INTO clearance_status
    (
        student_id,
        department_role,
        status,
        comment,
        approved_by,
        approved_at,
        student_name,
        reg_number
    )
    VALUES
    (
        '$student_id',
        'DEPARTMENT',
        'rejected',
        '$comment',
        '{$_SESSION['department_officer_id']}',
        NOW(),
        '{$student['fullname']}',
        '{$student['reg_number']}'
    )
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
        'DEPARTMENT',
        'rejected'
    );

    // ✅ UPDATE DASHBOARD STATUSES
    updateDashboardStatuses($conn, $student_id);

    $_SESSION['alert_message'] = "Student request rejected successfully!";
    $_SESSION['alert_type'] = 'rejected';
    
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Department Review Panel</title>
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
        
        .action-group {
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
            .action-group {
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
        
        /* Letter Status Box */
        .letter-status-box {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 12px 16px;
            border-left: 4px solid var(--primary-blue);
            margin-bottom: 16px;
        }
        
        .letter-status-box .status-badge {
            padding: 3px 10px;
            font-size: 11px;
        }
        
        /* FYP Status Box */
        .fyp-status-box {
            background: #f0f7ff;
            border-radius: 0.75rem;
            padding: 12px 16px;
            border-left: 4px solid var(--primary-blue);
            margin-bottom: 16px;
        }
        
        .fyp-status-box .status-badge {
            padding: 3px 10px;
            font-size: 11px;
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
        
        /* Text muted */
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        .text-success {
            color: #006c49 !important;
        }
        
        .text-warning {
            color: #f59e0b !important;
        }
        
        .text-danger {
            color: #ef4444 !important;
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
            .section-heading {
                font-size: 14px;
            }
            .letter-status-box, .fyp-status-box {
                padding: 10px 12px;
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
            .letter-status-box, .fyp-status-box {
                padding: 8px 10px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <!-- Header -->
        <div class="page-header">
            <h3><i class="bi bi-clipboard"></i> DEPARTMENT REVIEW PANEL</h3>
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
        </div>

        <!-- Department Requirement -->
        <div class="section-heading"><i class="bi bi-list-check"></i> Department Requirement</div>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Department clearance form required.
        </div>

        <!-- ✅ DEPARTMENT LETTER STATUS CHECK -->
        <div class="letter-status-box">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><i class="bi bi-file-earmark"></i> Department Letter Status</strong>
                </div>
                <?php if($letter_signed){ ?>
                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Signed</span>
                <?php } elseif($has_letter){ ?>
                    <span class="status-badge status-badge-pending"><i class="bi bi-clock"></i> Pending</span>
                <?php } else { ?>
                    <span class="status-badge status-badge-rejected"><i class="bi bi-x-circle"></i> Not Uploaded</span>
                <?php } ?>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <?php if($letter_signed){ ?>
                        <span class="text-success">✅ Department letter has been uploaded and signed.</span>
                    <?php } elseif($has_letter){ ?>
                        <span class="text-warning">⚠️ Department letter is uploaded but <strong>NOT</strong> signed yet.</span>
                        <br>
                        <span class="text-muted">Please go to <strong>"Department Letters"</strong> section to sign it.</span>
                    <?php } else { ?>
                        <span class="text-danger">❌ Student has not uploaded the department letter.</span>
                        <br>
                        <span class="text-muted">Please ask the student to upload the department clearance letter first.</span>
                    <?php } ?>
                </small>
            </div>
        </div>

        <!-- ✅ FINAL YEAR PROJECT STATUS CHECK -->
        <div class="fyp-status-box">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><i class="bi bi-file-earmark"></i> Final Year Project Status</strong>
                </div>
                <?php if($has_fyp){ ?>
                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Uploaded</span>
                <?php } else { ?>
                    <span class="status-badge status-badge-rejected"><i class="bi bi-x-circle"></i> Not Uploaded</span>
                <?php } ?>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <?php if($has_fyp){ ?>
                        <span class="text-success">✅ Final Year Project has been uploaded.</span>
                        <br>
                        <span class="text-muted">File: <?php echo htmlspecialchars($fyp_data['fyp_file']); ?></span>
                        <br>
                        <a target="_blank" href="../../assets/uploads/fyp/<?php echo $fyp_data['fyp_file']; ?>" class="btn-view mt-1">
                            <i class="bi bi-eye"></i> View FYP
                        </a>
                    <?php } else { ?>
                        <span class="text-danger">❌ Student has not uploaded the Final Year Project.</span>
                        <br>
                        <span class="text-muted">Please ask the student to upload their Final Year Project first.</span>
                    <?php } ?>
                </small>
            </div>
        </div>

        <!-- Main File -->
        <div class="section-heading"><i class="bi bi-file-earmark"></i> Main Clearance File</div>
        <?php if(!empty($document['filename'])){ ?>
            <a target="_blank" class="btn-view" href="../../assets/uploads/<?php echo $document['filename']; ?>">
                <i class="bi bi-eye"></i> View Clearance Form
            </a>
        <?php } else { ?>
            <p class="text-danger" style="font-family: 'Hanken Grotesk', sans-serif;">
                <i class="bi bi-exclamation-circle"></i> No clearance file uploaded
            </p>
        <?php } ?>

        <!-- Requirement Files -->
        <hr>
        <div class="section-heading"><i class="bi bi-paperclip"></i> Additional Requirement Files</div>
        <?php if(count($files) > 0){ ?>
            <div class="file-list">
                <ul class="list-group">
                <?php foreach($files as $file){ ?>
                    <li class="list-group-item">
                        <a target="_blank" href="../../assets/uploads/requirements/<?php echo $file; ?>">
                            <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($file); ?>
                        </a>
                    </li>
                <?php } ?>
                </ul>
            </div>
        <?php } else { ?>
            <p class="text-muted" style="font-family: 'Hanken Grotesk', sans-serif;">
                <i class="bi bi-info-circle"></i> No additional requirement files uploaded.
            </p>
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
                    <a href="upload-signature.php" class="btn-upload-signature">
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
                <a href="upload-signature.php" class="btn-upload-signature">
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
            } elseif(!$has_letter){
                $approve_disabled = true;
                $disable_reason = "Student must upload the department letter";
            } elseif(!$letter_signed){
                $approve_disabled = true;
                $disable_reason = "Department letter must be signed first";
            } elseif(!$has_fyp){
                $approve_disabled = true;
                $disable_reason = "Student must upload Final Year Project";
            }
            ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="mb-3">
                    <label class="form-label">Comment</label>
                    <textarea name="comment" class="form-control" rows="3" placeholder="Enter your comment here..." required></textarea>
                </div>
                <div class="action-group">
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