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

$id = intval($_GET['id'] ?? 0);

if($id <= 0){
    header("Location: dashboard.php");
    exit();
}

/* =========================
   GET OFFICER DETAILS
========================= */
$officer_id = $_SESSION['department_officer_id'];
$department_name = isset($_SESSION['department_name']) ? trim($_SESSION['department_name']) : '';

/* =========================
   GET LETTER WITH STUDENT INFO - Use ID only
========================= */
$letter_q = mysqli_query($conn,"
SELECT
    dl.*,
    s.fullname AS student_name,
    s.reg_number AS student_reg,
    s.faculty_name,
    s.department,
    s.id AS student_id
FROM department_letters dl
INNER JOIN students s ON s.id = dl.student_id
WHERE dl.id='$id'
LIMIT 1
");

$letter = mysqli_fetch_assoc($letter_q);

if(!$letter){
    die("Letter not found. ID: $id");
}

$student_id = $letter['student_id'];

/* =========================
   VERIFY THE OFFICER HAS ACCESS TO THIS LETTER
========================= */
$letter_dept = trim(strtolower($letter['department_name']));
$session_dept = trim(strtolower($department_name));
$student_dept = trim(strtolower($letter['department']));

$has_access = false;

if($letter_dept == $session_dept){
    $has_access = true;
} elseif($student_dept == $session_dept){
    $has_access = true;
    // Update the letter's department_name to match the session
    mysqli_query($conn,"
        UPDATE department_letters
        SET department_name = '$session_dept'
        WHERE id = '$id'
    ");
    $letter['department_name'] = $session_dept;
} elseif(strpos($letter_dept, $session_dept) !== false || strpos($session_dept, $letter_dept) !== false){
    $has_access = true;
} else {
    die("Access Denied: This letter does not belong to your department.");
}

/* CHECK IF LETTER IS ALREADY SIGNED */
if($letter['status'] == 'signed'){
    $_SESSION['alert_message'] = "This letter has already been signed!";
    $_SESSION['alert_type'] = 'info';
    header("Location: dashboard.php");
    exit();
}

/* CHECK IF LETTER IS ALREADY REJECTED */
if($letter['status'] == 'rejected'){
    $_SESSION['alert_message'] = "This letter has already been rejected!";
    $_SESSION['alert_type'] = 'info';
    header("Location: dashboard.php");
    exit();
}

/* GET OFFICER SIGNATURE STATUS */
$sig_q = mysqli_query($conn,"
    SELECT digital_signature, fullname
    FROM department_officers
    WHERE id='$officer_id'
    LIMIT 1
");

$sig_data = mysqli_fetch_assoc($sig_q);
$has_signature = !empty($sig_data['digital_signature']);

/* =========================
   APPROVE & SIGN (PDF FLOW) - NO AUTO-APPROVAL
========================= */
if(isset($_POST['sign'])){

    verify_csrf();

    $officer_id = $_SESSION['department_officer_id'];

    /* GET OFFICER SIGNATURE */
    $sig_q = mysqli_query($conn,"
        SELECT digital_signature, fullname
        FROM department_officers
        WHERE id='$officer_id'
        LIMIT 1
    ");

    $sig = mysqli_fetch_assoc($sig_q);

    if(empty($sig['digital_signature'])){
        $error = "Please upload your signature first";
    } else {

        /* UPDATE LETTER STATUS ONLY - NO AUTO-APPROVAL */
        mysqli_query($conn,"
            UPDATE department_letters
            SET status='signed',
                officer_name='{$sig['fullname']}',
                signature_file='{$sig['digital_signature']}',
                signed_at=NOW()
            WHERE id='$id'
        ");

        /* ✅ UPDATE CLEARANCE STATUS - Mark that letter is signed */
        // Check if clearance status exists for this student
        $check_cs = mysqli_query($conn,"
            SELECT id FROM clearance_status
            WHERE student_id='$student_id'
            AND department_role='DEPARTMENT'
            LIMIT 1
        ");
        
        if(mysqli_num_rows($check_cs) > 0){
            // Update existing clearance status - add note that letter is signed
            mysqli_query($conn,"
                UPDATE clearance_status
                SET comment = CONCAT(IFNULL(comment, ''), ' [Letter signed on ' , NOW(), ']')
                WHERE student_id='$student_id'
                AND department_role='DEPARTMENT'
            ");
        }

        /* Set success message */
        $_SESSION['alert_message'] = "Letter signed successfully! The student's clearance is still pending review.";
        $_SESSION['alert_type'] = 'approved';
        
        /* REDIRECT TO PDF GENERATOR */
        header("Location: generate-signed-letter.php?id=$id");
        exit();
    }
}

/* REJECT LETTER */
if(isset($_POST['reject'])){

    verify_csrf();

    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'No reason provided');

    mysqli_query($conn,"
        UPDATE department_letters
        SET status='rejected',
            comment='$reason'
        WHERE id='$id'
    ");

    $_SESSION['alert_message'] = "Letter rejected successfully!";
    $_SESSION['alert_type'] = 'rejected';
    
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Review Letter - <?php echo htmlspecialchars($department_name); ?></title>
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
        
        .btn-sign {
            background: #006c49;
            color: #ffffff;
            padding: 8px 30px;
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
        
        .btn-sign:hover {
            background: #005236;
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-sign:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-reject {
            background: #dc2626;
            color: #ffffff;
            padding: 8px 30px;
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
            padding: 6px 18px;
            border-radius: 0.375rem;
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
            .btn-sign, .btn-reject, .btn-back {
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
            .btn-sign, .btn-reject, .btn-back {
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
        
        .status-badge-signed {
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
        
        /* Text muted */
        .text-muted {
            color: var(--text-muted) !important;
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
            <h3><i class="bi bi-envelope"></i> DEPARTMENT LETTER REVIEW</h3>
            <a href="dashboard.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <hr>

        <!-- Student Information -->
        <div class="section-heading"><i class="bi bi-person"></i> Student Information</div>
        <div class="info-card">
            <p><b>Name:</b> <?php echo htmlspecialchars($letter['student_name']); ?></p>
            <p><b>Reg Number:</b> <?php echo htmlspecialchars($letter['student_reg']); ?></p>
            <p><b>Faculty:</b> <?php echo htmlspecialchars($letter['faculty_name']); ?></p>
            <p><b>Department:</b> <?php echo htmlspecialchars($letter['department']); ?></p>
            <p><b>Letter Department:</b> <?php echo htmlspecialchars($letter['department_name']); ?></p>
        </div>

        <!-- ℹ️ NOTE: Letter signing vs Clearance approval -->
        <div class="info-note">
            <i class="bi bi-info-circle"></i>
            <strong>Note:</strong> Signing this letter does NOT automatically approve the student's clearance. You must still go to the <strong>"Review Request"</strong> page to approve their clearance after all requirements are met.
        </div>

        <!-- Letter Status -->
        <div class="section-heading"><i class="bi bi-info-circle"></i> Letter Status</div>
        <div class="mb-3">
            <?php
            if($letter['status'] == 'signed'){
                echo "<span class='status-badge status-badge-signed'><i class='bi bi-check-circle'></i> Signed</span>";
            } elseif($letter['status'] == 'rejected') {
                echo "<span class='status-badge status-badge-rejected'><i class='bi bi-x-circle'></i> Rejected</span>";
            } else {
                echo "<span class='status-badge status-badge-pending'><i class='bi bi-clock'></i> Pending</span>";
            }
            ?>
        </div>

        <!-- Original Letter -->
        <div class="section-heading"><i class="bi bi-file-earmark"></i> Original Letter</div>
        <div class="mb-3">
            <a target="_blank" class="btn-view" href="../../assets/uploads/department_letters/<?php echo $letter['original_file']; ?>">
                <i class="bi bi-eye"></i> View Original Letter
            </a>
        </div>

        <!-- Error Message -->
        <?php if(isset($error)){ ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
            </div>
        <?php } ?>

        <!-- Signature Status -->
        <?php if(!$has_signature){ ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span class="flex-grow-1">You need to upload your digital signature before signing letters.</span>
                <a href="upload-signature.php" class="btn-upload-signature">
                    <i class="bi bi-upload"></i> Upload Signature
                </a>
            </div>
        <?php } ?>

        <!-- Actions -->
        <?php if($letter['status'] == 'pending'){ ?>
            <hr>
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <div class="mb-3">
                            <label class="form-label">Comment</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Optional comment"></textarea>
                        </div>
                        <div class="action-group">
                            <button type="submit" name="sign" class="btn-sign" <?php echo !$has_signature ? 'disabled' : ''; ?>>
                                <i class="bi bi-pencil"></i> Sign & Generate PDF
                            </button>
                            <button type="submit" name="reject" class="btn-reject">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 text-md-end">
                    <?php if(!$has_signature){ ?>
                        <small class="text-muted">Upload your signature to enable signing</small>
                    <?php } ?>
                </div>
            </div>
        <?php } else { ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i> This letter has already been <?php echo $letter['status']; ?>.
            </div>
        <?php } ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>