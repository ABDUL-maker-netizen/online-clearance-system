<?php
session_start();

include '../../includes/config.php';
include '../../includes/auth.php';
include '../../includes/functions.php';

// Check if faculty officer is logged in
if(!isset($_SESSION['faculty_officers_id'])){
    header("Location: login.php");
    exit();
}

$faculty_name = trim($_SESSION['faculty_name']);
$officer_id = $_SESSION['faculty_officers_id'];

// Check if request is POST
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: dashboard.php");
    exit();
}

// Get selected student IDs
if(!isset($_POST['student_ids']) || empty($_POST['student_ids'])){
    $_SESSION['alert_message'] = "No students selected for bulk approval.";
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
    FROM faculty_officers
    WHERE id='$officer_id'
    LIMIT 1
");
$sig_data = mysqli_fetch_assoc($sig_q);
$signature = $sig_data['digital_signature'] ?? '';

if(empty($signature)){
    $_SESSION['alert_message'] = "You must upload your digital signature before approving students.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Build safe ID list for query
$ids_string = implode(',', array_map('intval', $valid_ids));

// ============================================================
// FUNCTIONS FOR CHECKING FACULTY REQUIREMENTS
// ============================================================

function getFacultyRequirementFilesCount($conn, $student_id){
    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='FACULTY'
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($q);
    if(!$row){
        return 0;
    }
    $files = json_decode($row['requirement_files'] ?? '[]', true);
    return is_array($files) ? count($files) : 0;
}

function hasFacultyDocuments($conn, $student_id){
    $q = mysqli_query($conn,"
        SELECT id FROM clearance_uploads
        WHERE student_id='$student_id'
        LIMIT 1
    ");
    return mysqli_num_rows($q) > 0;
}

// ============================================================
// VERIFY ALL SELECTED STUDENTS BELONG TO THIS FACULTY
// ============================================================
$verify_query = mysqli_query($conn,"
SELECT s.id, s.fullname, s.reg_number, cs.status, cs.document_file, cs.requirement_files
FROM students s
INNER JOIN clearance_status cs ON s.id = cs.student_id
WHERE s.id IN ($ids_string)
AND cs.department_role = 'FACULTY'
AND (
    TRIM(LOWER(s.faculty_name)) = LOWER('$faculty_name')
    OR s.faculty_name LIKE '%$faculty_name%'
)
");

if(mysqli_num_rows($verify_query) == 0){
    $_SESSION['alert_message'] = "No valid students found for this faculty.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Process each student with all checks
$approved_count = 0;
$skipped_count = 0;
$failed_count = 0;
$success_students = [];
$skipped_students = [];
$failed_students = [];

while($row = mysqli_fetch_assoc($verify_query)){
    $student_id = $row['id'];
    $student_name = $row['fullname'];
    $reg_number = $row['reg_number'];
    $current_status = strtolower($row['status'] ?? 'pending');
    $has_document = !empty($row['document_file']);
    $has_requirement_files = !empty($row['requirement_files']);
    
    // ✅ Check if already approved
    if($current_status == 'approved'){
        $skipped_count++;
        $skipped_students[] = "$student_name ($reg_number) - Already approved";
        continue;
    }
    
    // ✅ Check if rejected
    if($current_status == 'rejected'){
        $skipped_count++;
        $skipped_students[] = "$student_name ($reg_number) - Rejected";
        continue;
    }
    
    // ✅ Check if not pending
    if($current_status != 'pending'){
        $skipped_count++;
        $skipped_students[] = "$student_name ($reg_number) - Status: $current_status (not pending)";
        continue;
    }
    
    // ✅ Check if student has uploaded main document
    if(!$has_document){
        $skipped_count++;
        $skipped_students[] = "$student_name ($reg_number) - No main document uploaded";
        continue;
    }
    
    // ✅ Get requirement files count
    $req_count = getFacultyRequirementFilesCount($conn, $student_id);
    
    // ✅ All checks passed - Approve the student
    $comment = "Bulk approved by faculty officer on " . date('Y-m-d H:i:s');
    
    // Check if record exists
    $check_exists = mysqli_query($conn,"
        SELECT id FROM clearance_status
        WHERE student_id = '$student_id'
        AND department_role = 'FACULTY'
        LIMIT 1
    ");
    
    if(mysqli_num_rows($check_exists) > 0){
        // Update existing record
        $update_query = "
        UPDATE clearance_status
        SET status='approved',
            comment='$comment',
            approved_by='$officer_id',
            approved_at=NOW()
        WHERE student_id = '$student_id'
        AND department_role = 'FACULTY'
        ";
    } else {
        // Insert new record
        $update_query = "
        INSERT INTO clearance_status
        (student_id, department_role, status, comment, approved_by, approved_at, student_name, reg_number)
        VALUES
        ('$student_id','FACULTY','approved','$comment','$officer_id',NOW(),'$student_name','$reg_number')
        ";
    }
    
    if(mysqli_query($conn, $update_query)){
        $approved_count++;
        $success_students[] = "$student_name ($reg_number)";
        
        if(function_exists('updateClearanceStatus')){
            updateClearanceStatus($conn, $student_id, 'FACULTY', 'approved');
        }
        
        // ✅ UPDATE DASHBOARD STATUSES
        if(function_exists('updateDashboardStatuses')){
            updateDashboardStatuses($conn, $student_id);
        }
        
    } else {
        $failed_count++;
        $failed_students[] = "$student_name ($reg_number) - Database error: " . mysqli_error($conn);
    }
}

// ✅ Backup: Update statuses for all successfully approved students
if($approved_count > 0 && function_exists('updateDashboardStatuses')){
    foreach($success_students as $student) {
        preg_match('/\((\d+)\)/', $student, $matches);
        if(isset($matches[1])){
            updateDashboardStatuses($conn, $matches[1]);
        }
    }
}

// Build detailed result message
$result_message = "";
$result_type = 'approved';

if($approved_count > 0){
    $result_message = "✅ $approved_count student(s) approved successfully!";
    if(count($success_students) > 0 && count($success_students) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $success_students) . "</small>";
    } elseif(count($success_students) > 5){
        $result_message .= "<br><small>" . count($success_students) . " students processed successfully.</small>";
    }
}

if($skipped_count > 0){
    $result_message .= "<br><br>⏭️ $skipped_count student(s) skipped:";
    if(count($skipped_students) > 0 && count($skipped_students) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $skipped_students) . "</small>";
    } else {
        $result_message .= "<br><small>" . $skipped_count . " students skipped due to incomplete requirements.</small>";
    }
}

if($failed_count > 0){
    $result_message .= "<br><br>❌ $failed_count student(s) failed:";
    if(count($failed_students) > 0 && count($failed_students) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $failed_students) . "</small>";
    } else {
        $result_message .= "<br><small>" . $failed_count . " students failed.</small>";
    }
    $result_type = 'error';
}

if($approved_count == 0 && $failed_count == 0 && $skipped_count > 0){
    $result_message = "⚠️ No students were approved. $skipped_count student(s) were skipped due to incomplete requirements.";
    $result_type = 'info';
}

if($approved_count == 0 && $failed_count > 0 && $skipped_count == 0){
    $result_message = "❌ Failed to approve any students. All " . count($valid_ids) . " student(s) failed.";
    $result_type = 'error';
}

$_SESSION['alert_message'] = $result_message;
$_SESSION['alert_type'] = $result_type;

header("Location: dashboard.php");
exit();
?>