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
    FROM officers
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

// ============================================================
// VERIFY ALL SELECTED STUDENTS BELONG TO THIS OFFICE
// ============================================================
$verify_query = mysqli_query($conn,"
SELECT s.id, s.fullname, s.reg_number, s.programme, cs.status, cs.document_file
FROM students s
INNER JOIN clearance_status cs ON s.id = cs.student_id
WHERE s.id IN ($ids_string)
AND UPPER(cs.department_role) = UPPER('$office')
");

if(mysqli_num_rows($verify_query) == 0){
    $_SESSION['alert_message'] = "No valid students found for this office.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Process each student with requirement checks
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
    $student_program = $row['programme'] ?? '';
    $current_status = strtolower($row['status'] ?? 'pending');
    $has_document = !empty($row['document_file']);
    
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
    
    // ✅ NEW: CHECK REQUIREMENT FILES (SAME AS review-request.php)
    $is_auto_approved = in_array($office, ['SPORT UNIT', 'HALL']);
    
    if(!$is_auto_approved){
        $uploaded_count = getUploadedFilesCount($conn, $student_id, $office);
        $required_count = getMinimumRequiredFiles($office, $reg_number, $student_program);
        
        if($uploaded_count == 0){
            $skipped_count++;
            $skipped_students[] = "$student_name ($reg_number) - No requirement files uploaded (need $required_count)";
            continue;
        }
        
        if($uploaded_count < $required_count){
            $skipped_count++;
            $skipped_students[] = "$student_name ($reg_number) - Incomplete requirements ($uploaded_count/$required_count files)";
            continue;
        }
    }
    
    // ✅ All checks passed - Approve the student
    $comment = "Bulk approved by officer on " . date('Y-m-d H:i:s');
    
    // Check if record exists
    $check_exists = mysqli_query($conn,"
        SELECT id FROM clearance_status
        WHERE student_id = '$student_id'
        AND department_role = '$office'
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
        AND department_role = '$office'
        ";
    } else {
        // Insert new record
        $update_query = "
        INSERT INTO clearance_status
        (student_id, department_role, status, comment, approved_by, approved_at, student_name, reg_number)
        VALUES
        ('$student_id','$office','approved','$comment','$officer_id',NOW(),'$student_name','$reg_number')
        ";
    }
    
    if(mysqli_query($conn, $update_query)){
        $approved_count++;
        $success_students[] = "$student_name ($reg_number)";
        
        // Call updateClearanceStatus if exists
        if(function_exists('updateClearanceStatus')){
            updateClearanceStatus($conn, $student_id, $office, 'approved');
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
        // Extract student ID from the string (format: "Name (ID)")
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
        $result_message .= "<br><small>$skipped_count students skipped due to incomplete requirements.</small>";
    }
}

if($failed_count > 0){
    $result_message .= "<br><br>❌ $failed_count student(s) failed:";
    if(count($failed_students) > 0 && count($failed_students) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $failed_students) . "</small>";
    } else {
        $result_message .= "<br><small>$failed_count students failed.</small>";
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