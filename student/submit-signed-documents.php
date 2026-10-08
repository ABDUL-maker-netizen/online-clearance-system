<?php
//session_start();

include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/auth.php';
include_once '../includes/csrf.php';

studentAuth();

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* STUDENT DATA */
$student_query = mysqli_query(
    $conn,
    "SELECT * FROM students WHERE id='$student_id'"
);
$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found");
}

/* ===============================
   CHECK IF STUDENT CAN SUBMIT - USING SAME STRICT CHECKS AS DASHBOARD
=============================== */

// ✅ CHECK 1: Department letter must be SIGNED (with signature file and date)
$letter_q = mysqli_query($conn,"
    SELECT id, signed_file, signed_at, signature_file, original_file
    FROM department_letters
    WHERE student_id='$student_id'
    AND status='signed'
    AND signed_file IS NOT NULL
    AND signed_at IS NOT NULL
    AND signature_file IS NOT NULL
    ORDER BY id DESC
    LIMIT 1
");
$signed_letter = mysqli_fetch_assoc($letter_q);
$has_signed_letter = $signed_letter && !empty($signed_letter['signed_file']) && !empty($signed_letter['signed_at']);

// ✅ CHECK 2: ALL offices must be approved (student must be fully cleared)
$check_all_approved = mysqli_query($conn,"
    SELECT COUNT(*) as total
    FROM clearance_status
    WHERE student_id='$student_id'
    AND status='approved'
");
$approved_data_check = mysqli_fetch_assoc($check_all_approved);
$approved_count_check = $approved_data_check['total'] ?? 0;

// Get total active offices
$total_q = mysqli_query($conn,"
    SELECT COUNT(*) as total FROM offices WHERE status='active'
");
$total_data_check = mysqli_fetch_assoc($total_q);
$total_offices_check = $total_data_check['total'] ?? 0;

// ✅ Check if ALL offices are approved
$all_offices_approved = ($approved_count_check >= $total_offices_check && $total_offices_check > 0);

// Student's overall clearance status must be 'cleared'
$is_fully_cleared = strtolower($student['status']) == 'cleared';

// ✅ BOTH conditions must be met
$can_submit = $has_signed_letter && $all_offices_approved && $is_fully_cleared;

// Check if already submitted
$submission_q = mysqli_query($conn,"
    SELECT * FROM senate_submissions
    WHERE student_id='$student_id'
    ORDER BY id DESC
    LIMIT 1
");
$submission = mysqli_fetch_assoc($submission_q);
$has_submitted = $submission && $submission['status'] != 'returned';
$submission_status = $submission['status'] ?? null;

$message = "";
$submission_success = false;

/* ===============================
   HANDLE SUBMISSION
=============================== */
if (isset($_POST['submit_senate'])) {
    verify_csrf();

    // Check if already submitted
    if ($has_submitted && $submission['status'] != 'returned') {
        $message = "<div class='alert alert-warning'>You have already submitted your documents. Waiting for Senate review.</div>";
        goto end_submit;
    }

    // Check if both documents are available using strict checks
    if (!$has_signed_letter) {
        $message = "<div class='alert alert-danger'>Your department letter must be signed before submission.</div>";
        goto end_submit;
    }

    if (!$all_offices_approved) {
        $message = "<div class='alert alert-danger'>All offices must approve your clearance before submission. ($approved_count_check/$total_offices_check approved)</div>";
        goto end_submit;
    }

    if (!$is_fully_cleared) {
        $message = "<div class='alert alert-danger'>Your clearance status must be 'Cleared' before submission.</div>";
        goto end_submit;
    }

    // ✅ GET MAIN CLEARANCE FILE FROM DATABASE (clearance_slips table)
    $main_file_q = mysqli_query($conn,"
        SELECT slip_filename, slip_path
        FROM clearance_slips
        WHERE student_id='$student_id'
        AND is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $main_file = mysqli_fetch_assoc($main_file_q);

    // If not found in clearance_slips, try clearance_uploads as fallback
    if (!$main_file || empty($main_file['slip_filename'])) {
        $main_file_q = mysqli_query($conn,"
            SELECT filename
            FROM clearance_uploads
            WHERE student_id='$student_id'
            ORDER BY id DESC
            LIMIT 1
        ");
        $main_file_data = mysqli_fetch_assoc($main_file_q);
        
        if ($main_file_data && !empty($main_file_data['filename'])) {
            $main_filename = $main_file_data['filename'];
            $main_path = "../assets/uploads/" . $main_filename;
        } else {
            $message = "<div class='alert alert-danger'>Main clearance file not found. Please print your clearance slip first.</div>";
            goto end_submit;
        }
    } else {
        $main_filename = $main_file['slip_filename'];
        $main_path = $main_file['slip_path'];
    }

    // ✅ Check if the main clearance file actually exists on the server
    if (!file_exists($main_path)) {
        $message = "<div class='alert alert-danger'>Main clearance file is missing from the server. Please print your clearance slip again.</div>";
        goto end_submit;
    }

    // ✅ Check if the signed department letter actually exists on the server
    $letter_file_path = "../assets/uploads/department_letters/" . $signed_letter['signed_file'];
    if (!file_exists($letter_file_path)) {
        $message = "<div class='alert alert-danger'>Signed department letter is missing from the server. Please contact the department office.</div>";
        goto end_submit;
    }

    // Insert submission
    $insert_q = mysqli_query($conn,"
        INSERT INTO senate_submissions
        (student_id, signed_clearance_form, signed_department_letter, status, submitted_at)
        VALUES
        ('$student_id', '$main_filename', '{$signed_letter['signed_file']}', 'pending', NOW())
    ");

    if ($insert_q) {
        // Store success in session for the dashboard to display
        $_SESSION['alert_message'] = "Your documents have been submitted to the Senate successfully!";
        $_SESSION['alert_type'] = 'approved';
        $submission_success = true;
        
        // Use JavaScript redirect since we're inside dashboard.php
        echo "<script>
            window.location.href = 'dashboard.php?submit-signed-documents';
        </script>";
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Failed to submit documents. Please try again.</div>";
    }
}

end_submit:

// Get latest submission status
$submission_q = mysqli_query($conn,"
    SELECT * FROM senate_submissions
    WHERE student_id='$student_id'
    ORDER BY id DESC
    LIMIT 1
");
$submission = mysqli_fetch_assoc($submission_q);
$has_submitted = $submission && $submission['status'] != 'returned';
$submission_status = $submission['status'] ?? null;

// ✅ GET MAIN CLEARANCE FILE FROM DATABASE (clearance_slips table first)
$main_file_q2 = mysqli_query($conn,"
    SELECT slip_filename, slip_path
    FROM clearance_slips
    WHERE student_id='$student_id'
    AND is_active = 1
    ORDER BY id DESC
    LIMIT 1
");
$main_file2 = mysqli_fetch_assoc($main_file_q2);

// If not found in clearance_slips, try clearance_uploads as fallback
if (!$main_file2 || empty($main_file2['slip_filename'])) {
    $main_file_q2 = mysqli_query($conn,"
        SELECT filename
        FROM clearance_uploads
        WHERE student_id='$student_id'
        ORDER BY id DESC
        LIMIT 1
    ");
    $main_file_data2 = mysqli_fetch_assoc($main_file_q2);
    
    if ($main_file_data2 && !empty($main_file_data2['filename'])) {
        $has_clearance_form = true;
        $clearance_filename = $main_file_data2['filename'];
        $clearance_file_path = "../assets/uploads/" . $clearance_filename;
        $clearance_file_exists = file_exists($clearance_file_path);
    } else {
        $has_clearance_form = false;
        $clearance_filename = '';
        $clearance_file_path = '';
        $clearance_file_exists = false;
    }
} else {
    $has_clearance_form = true;
    $clearance_filename = $main_file2['slip_filename'];
    $clearance_file_path = $main_file2['slip_path'];
    $clearance_file_exists = file_exists($clearance_file_path);
}

// Check if signed department letter physically exists
$letter_file_exists = false;
if ($has_signed_letter) {
    $letter_file_path = "../assets/uploads/department_letters/" . $signed_letter['signed_file'];
    $letter_file_exists = file_exists($letter_file_path);
}

// Determine if both documents are ready (exist on server)
$both_documents_ready = $clearance_file_exists && $letter_file_exists;

// If submission was successful, redirect using JavaScript
if ($submission_success) {
    echo "<script>
        window.location.href = 'dashboard.php?submit-signed-documents';
    </script>";
    exit();
}
?>

<!-- =========================================================
     SUBMIT SIGNED DOCUMENTS PAGE CONTENT - UPDATED WITH MATCHING DESIGN
     This is included inside dashboard.php
========================================================= -->

<style>
/* Matching design styles */
.submit-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header-submit {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    margin-top: 8px;
}

.page-header-submit .header-icon {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    background-color: rgba(30, 64, 175, 0.1);
    color: #1e40af;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-submit .header-icon i {
    font-size: 2rem;
    line-height: 1;
}

.page-header-submit h1 {
    font-size: 32px;
    font-weight: 700;
    line-height: 40px;
    letter-spacing: -0.02em;
    color: #111827;
    margin: 0;
    font-family: 'Hanken Grotesk', sans-serif;
}

.page-header-submit p {
    font-size: 16px;
    font-weight: 400;
    line-height: 24px;
    color: #4b5563;
    margin-top: 4px;
    margin-bottom: 0;
}

/* Card styling */
.card-custom {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.card-custom h3 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 16px;
    font-family: 'Hanken Grotesk', sans-serif;
}

/* Alert styling */
.alert {
    border-radius: 0.5rem;
    padding: 12px 16px;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
    margin-bottom: 20px;
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

.alert-info {
    background-color: #e8f0fe;
    border-color: #1e40af;
    color: #1e40af;
}

/* Requirement Grid */
.requirement-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.requirement-item {
    text-align: center;
}

.requirement-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Hanken Grotesk', sans-serif;
}

.requirement-badge.success {
    background: #d1fae5;
    color: #065f46;
}

.requirement-badge.danger {
    background: #fee2e2;
    color: #991b1b;
}

.requirement-badge.warning {
    background: #fef3c7;
    color: #92400e;
}

.requirement-label {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    color: #4b5563;
}

/* Requirements not met */
.requirements-not-met {
    margin-top: 16px;
    padding: 12px 16px;
    border-radius: 0.5rem;
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    color: #92400e;
}

.requirements-not-met ul {
    margin-bottom: 0;
    margin-top: 4px;
    padding-left: 20px;
}

/* Document Box */
.document-box {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 16px;
    margin-bottom: 12px;
    background: #f8f9fa;
}

.document-box:last-child {
    margin-bottom: 0;
}

.document-box .doc-title {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
    margin-bottom: 4px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.document-box .doc-status {
    font-size: 13px;
    margin-bottom: 8px;
}

.document-box .doc-status .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Hanken Grotesk', sans-serif;
}

.document-box .doc-status .status-badge.ready {
    background: #d1fae5;
    color: #065f46;
}

.document-box .doc-status .status-badge.missing {
    background: #fef3c7;
    color: #92400e;
}

.document-box .doc-status .status-badge.not-available {
    background: #fee2e2;
    color: #991b1b;
}

.doc-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-download {
    background: #e8f0fe;
    color: #1e40af;
    padding: 6px 16px;
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

.btn-download:hover {
    background: #dbe1ff;
    color: #1e3a8a;
    text-decoration: none;
}

/* Submit Button */
.btn-submit-senate {
    background: #1e40af !important;
    color: #ffffff !important;
    padding: 10px 32px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-family: 'Hanken Grotesk', sans-serif;
}

.btn-submit-senate:hover {
    background: #1e3a8a !important;
    transform: scale(0.98);
}

.btn-submit-senate:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-submit-senate.success {
    background: #006c49 !important;
}

.btn-submit-senate.success:hover {
    background: #005236 !important;
}

/* Back Button */
.btn-back {
    background: #4b5563;
    color: #ffffff;
    padding: 8px 20px;
    border-radius: 0.375rem;
    border: none;
    font-weight: 500;
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
}

/* Submission Status Box */
.submission-status-box {
    padding: 16px;
    border-radius: 0.5rem;
    text-align: center;
}

.submission-status-box.pending {
    background: #fef3c7;
    border: 1px solid #fcd34d;
}

.submission-status-box.approved {
    background: #d1fae5;
    border: 1px solid #34d399;
}

.submission-status-box.rejected {
    background: #fee2e2;
    border: 1px solid #f87171;
}

.submission-status-box.returned {
    background: #fef3c7;
    border: 1px solid #fcd34d;
}

.submission-status-box .status-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 8px;
}

.submission-status-box .status-title {
    font-size: 18px;
    font-weight: 600;
    font-family: 'Hanken Grotesk', sans-serif;
}

.submission-status-box .status-subtitle {
    font-size: 14px;
    color: #4b5563;
}

.submission-status-box .status-comment {
    margin-top: 8px;
}

.submission-status-box .status-comment strong {
    color: #111827;
}

/* Responsive */
@media (max-width: 768px) {
    .submit-page {
        padding: 12px;
    }
    
    .page-header-submit {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .page-header-submit h1 {
        font-size: 24px;
        line-height: 32px;
    }
    
    .page-header-submit p {
        font-size: 14px;
    }
    
    .card-custom {
        padding: 16px;
    }
    
    .requirement-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .document-box .doc-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .document-box .doc-actions .btn-download {
        text-align: center;
        justify-content: center;
    }
    
    .btn-submit-senate {
        width: 100%;
        justify-content: center;
    }
    
    .btn-back {
        width: 100%;
        justify-content: center;
    }
    
    .submission-status-box .status-icon {
        font-size: 36px;
    }
}

@media (max-width: 576px) {
    .page-header-submit h1 {
        font-size: 20px;
    }
    
    .page-header-submit .header-icon {
        width: 40px;
        height: 40px;
    }
    
    .page-header-submit .header-icon i {
        font-size: 1.5rem;
    }
    
    .card-custom h3 {
        font-size: 16px;
    }
    
    .document-box {
        padding: 12px;
    }
    
    .requirement-badge {
        font-size: 12px;
        padding: 4px 12px;
    }
}

/* Breadcrumb */
.breadcrumb-custom {
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.breadcrumb-custom a {
    color: #1e40af;
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-custom a:hover {
    text-decoration: underline;
}

.breadcrumb-custom i {
    margin: 0 4px;
}

/* Info box */
.info-box {
    padding: 12px 16px;
    border-radius: 0.5rem;
    background: #e8f0fe;
    border-left: 4px solid #1e40af;
    color: #1e40af;
    margin-bottom: 16px;
}

.info-box ul {
    margin-bottom: 0;
    margin-top: 4px;
    padding-left: 20px;
}
</style>

<div class="submit-page">

    <!-- Page Header -->
    <div class="page-header-submit">
        <div class="header-icon">
            <i class="bi bi-send"></i>
        </div>
        <div>
            <h1>Submit Signed Documents</h1>
            <p>Submit your signed documents to the Senate for final approval.</p>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if ($message) { ?>
        <div style="margin-bottom:20px;"><?php echo $message; ?></div>
    <?php } ?>

    <!-- Progress Summary -->
    <div class="card-custom">
        <h3><i class="bi bi-clipboard-check"></i> Submission Requirements</h3>
        <div class="requirement-grid">
            <div class="requirement-item">
                <div class="requirement-badge <?php echo ($has_signed_letter && $letter_file_exists) ? 'success' : 'danger'; ?>">
                    <i class="bi <?php echo ($has_signed_letter && $letter_file_exists) ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>
                    Department Letter
                </div>
                <span class="requirement-label">
                    <?php 
                    if ($has_signed_letter && $letter_file_exists) { 
                        echo '✅ Signed & Ready';
                    } elseif ($has_signed_letter && !$letter_file_exists) {
                        echo '⚠️ Signed but file missing';
                    } else {
                        echo '❌ Not Signed';
                    }
                    ?>
                </span>
            </div>
            <div class="requirement-item">
                <div class="requirement-badge <?php echo $all_offices_approved ? 'success' : 'danger'; ?>">
                    <i class="bi <?php echo $all_offices_approved ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>
                    All Offices Approved
                </div>
                <span class="requirement-label"><?php echo $approved_count_check; ?>/<?php echo $total_offices_check; ?> approved</span>
            </div>
            <div class="requirement-item">
                <div class="requirement-badge <?php echo $is_fully_cleared ? 'success' : 'danger'; ?>">
                    <i class="bi <?php echo $is_fully_cleared ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>
                    Fully Cleared
                </div>
                <span class="requirement-label"><?php echo $is_fully_cleared ? '✅ Yes' : '❌ No'; ?></span>
            </div>
        </div>
        <?php if (!$can_submit && !$has_submitted) { ?>
            <div class="requirements-not-met">
                <i class="bi bi-lock"></i> 
                <strong>Requirements not met:</strong>
                <ul>
                    <?php if (!$has_signed_letter || !$letter_file_exists) { ?>
                        <li>Department letter must be signed and exist on the server</li>
                    <?php } ?>
                    <?php if (!$all_offices_approved) { ?>
                        <li>All <?php echo $total_offices_check; ?> offices must approve your clearance (<?php echo $approved_count_check; ?> approved)</li>
                    <?php } ?>
                    <?php if (!$is_fully_cleared) { ?>
                        <li>Your overall clearance status must be 'Cleared'</li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>

    <!-- Submission Status -->
    <?php if ($has_submitted && $submission_status != 'returned') { ?>
    <div class="card-custom">
        <h3><i class="bi bi-clock-history"></i> Submission Status</h3>
        <div class="submission-status-box <?php echo $submission_status; ?>">
            <?php if ($submission_status == 'pending') { ?>
                <span class="status-icon">⏳</span>
                <div class="status-title" style="color:#92400e;">Pending Senate Review</div>
                <div class="status-subtitle">Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($submission['submitted_at'])); ?></div>
                <div style="margin-top:8px; color:#4b5563;">Your documents are being reviewed by the Senate.</div>
            <?php } elseif ($submission_status == 'approved') { ?>
                <span class="status-icon">✅</span>
                <div class="status-title" style="color:#065f46;">Approved by Senate</div>
                <div class="status-subtitle">Reviewed on <?php echo date('F d, Y \a\t h:i A', strtotime($submission['reviewed_at'])); ?></div>
                <div style="margin-top:8px; color:#065f46;">Your documents have been approved by the Senate.</div>
            <?php } elseif ($submission_status == 'rejected') { ?>
                <span class="status-icon">❌</span>
                <div class="status-title" style="color:#991b1b;">Rejected by Senate</div>
                <div class="status-subtitle">Reviewed on <?php echo date('F d, Y \a\t h:i A', strtotime($submission['reviewed_at'])); ?></div>
                <?php if (!empty($submission['comment'])) { ?>
                    <div class="status-comment" style="color:#991b1b;"><strong>Reason:</strong> <?php echo htmlspecialchars($submission['comment']); ?></div>
                <?php } ?>
                <div style="margin-top:8px; color:#4b5563;">Please contact the Senate office for clarification.</div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <!-- Documents Display -->
    <div class="card-custom">
        <h3><i class="bi bi-file-earmark-text"></i> Your Documents</h3>

        <!-- Department Clearance Letter -->
        <div class="document-box">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <div class="doc-title">
                        <i class="bi bi-file-earmark-pdf"></i> Department Clearance Letter
                    </div>
                    <div class="doc-status">
                        <?php if ($has_signed_letter && $letter_file_exists) { ?>
                            <span class="status-badge ready">
                                <i class="bi bi-check-circle"></i> Ready
                            </span>
                            <span style="color:#4b5563; margin-left:8px; word-break:break-all;"><?php echo htmlspecialchars($signed_letter['signed_file']); ?></span>
                        <?php } elseif ($has_signed_letter && !$letter_file_exists) { ?>
                            <span class="status-badge missing">
                                <i class="bi bi-exclamation-triangle"></i> File Missing
                            </span>
                            <span style="color:#4b5563; margin-left:8px;">Please contact the department office.</span>
                        <?php } else { ?>
                            <span class="status-badge not-available">
                                <i class="bi bi-x-circle"></i> Not Available
                            </span>
                        <?php } ?>
                    </div>
                </div>
                <?php if ($has_signed_letter && $letter_file_exists) { ?>
                    <div class="doc-actions">
                        <a href="../assets/uploads/department_letters/<?php echo htmlspecialchars($signed_letter['signed_file']); ?>" 
                           target="_blank" 
                           class="btn-download">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Main Clearance Form -->
        <div class="document-box">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div>
                    <div class="doc-title">
                        <i class="bi bi-file-earmark-pdf"></i> Main Clearance Form (Signed Slip)
                    </div>
                    <div class="doc-status">
                        <?php if ($has_clearance_form && $clearance_file_exists) { ?>
                            <span class="status-badge ready">
                                <i class="bi bi-check-circle"></i> Ready
                            </span>
                            <span style="color:#4b5563; margin-left:8px; word-break:break-all;"><?php echo htmlspecialchars($clearance_filename); ?></span>
                        <?php } elseif ($has_clearance_form && !$clearance_file_exists) { ?>
                            <span class="status-badge missing">
                                <i class="bi bi-exclamation-triangle"></i> File Missing
                            </span>
                            <span style="color:#4b5563; margin-left:8px;">Please print your clearance slip again.</span>
                        <?php } else { ?>
                            <span class="status-badge not-available">
                                <i class="bi bi-x-circle"></i> Not Available
                            </span>
                            <span style="color:#4b5563; margin-left:8px;">Please print your clearance slip first.</span>
                        <?php } ?>
                    </div>
                </div>
                <?php if ($has_clearance_form && $clearance_file_exists) { ?>
                    <div class="doc-actions">
                        <a href="<?php echo htmlspecialchars($clearance_file_path); ?>" 
                           target="_blank" 
                           class="btn-download">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <?php if (!$has_submitted || $submission_status == 'returned') { ?>
    <div class="card-custom">
        <h3><i class="bi bi-send"></i> Submit to Senate</h3>
        
        <?php if ($submission_status == 'returned') { ?>
            <div class="alert alert-warning">
                <i class="bi bi-arrow-return-left"></i>
                <strong>Returned for Correction:</strong> 
                <?php if (!empty($submission['comment'])) { ?>
                    <?php echo htmlspecialchars($submission['comment']); ?>
                <?php } else { ?>
                    Your submission has been returned. Please ensure all documents are correct and resubmit.
                <?php } ?>
            </div>
        <?php } ?>

        <div class="info-box">
            <i class="bi bi-info-circle"></i>
            <strong>Before you submit:</strong>
            <ul>
                <li>Ensure both documents are the correct signed versions.</li>
                <li>The main clearance form is the signed slip generated when you printed it.</li>
                <li>You will not be able to change them after submission unless returned.</li>
            </ul>
        </div>

        <?php if (($can_submit && $both_documents_ready) || $submission_status == 'returned') { ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <button type="submit" 
                        name="submit_senate" 
                        class="btn-submit-senate success">
                    <i class="bi bi-send"></i> Submit to Senate
                </button>
            </form>
        <?php } else { ?>
            <div class="alert alert-danger">
                <i class="bi bi-lock"></i>
                <strong>Cannot Submit:</strong>
                <ul style="margin-bottom:0; margin-top:4px; padding-left:20px;">
                    <?php if (!$has_signed_letter || !$letter_file_exists) { ?>
                        <li>Department letter must be signed and exist on the server</li>
                    <?php } ?>
                    <?php if (!$has_clearance_form || !$clearance_file_exists) { ?>
                        <li>Main clearance form must be printed and exist on the server</li>
                    <?php } ?>
                    <?php if (!$all_offices_approved) { ?>
                        <li>All <?php echo $total_offices_check; ?> offices must approve your clearance (<?php echo $approved_count_check; ?>/<?php echo $total_offices_check; ?>)</li>
                    <?php } ?>
                    <?php if (!$is_fully_cleared) { ?>
                        <li>Your overall clearance status must be 'Cleared'</li>
                    <?php } ?>
                </ul>
            </div>
            <button class="btn-submit-senate" disabled>
                <i class="bi bi-lock"></i> Submit to Senate
            </button>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- Back Button -->
    <a href="dashboard.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

</div>

<script>
// SweetAlert2 is loaded from dashboard.php
// This function is called from the inline onclick
function confirmSubmit() {
    Swal.fire({
        title: 'Submit Signed Documents?',
        html: 'Are you sure you want to submit these signed documents to the Senate?<br><br><strong>You will not be able to change them after submission unless the Senate returns them for correction.</strong>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e40af',
        cancelButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Submit',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            // Submit the form
            document.querySelector('form').submit();
        }
    });
}
</script>