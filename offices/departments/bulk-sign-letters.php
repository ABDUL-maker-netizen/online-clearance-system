<?php
session_start();

include '../../includes/config.php';
include '../../includes/functions.php';
include '../../includes/csrf.php';
require '../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

// ✅ Check if PhpWord classes are available for DOCX conversion
$use_docx_conversion = false;
if(class_exists('PhpOffice\PhpWord\IOFactory')){
    try {
        Settings::setPdfRendererPath('../../vendor/dompdf/dompdf');
        Settings::setPdfRendererName('DomPDF');
        $use_docx_conversion = true;
    } catch (Exception $e) {
        $use_docx_conversion = false;
        error_log("PhpWord PDF renderer configuration failed: " . $e->getMessage());
    }
}

/* =========================
   SECURITY
========================= */
if(!isset($_SESSION['department_officer_id'])){
    header("Location: login.php");
    exit();
}

$officer_id = $_SESSION['department_officer_id'];
$department_name = trim($_SESSION['department_name']);

// Check if request is POST
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: dashboard.php");
    exit();
}

// Get selected letter IDs
if(!isset($_POST['letter_ids']) || empty($_POST['letter_ids'])){
    $_SESSION['alert_message'] = "No letters selected for bulk signing.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

$letter_ids = $_POST['letter_ids'];
if(!is_array($letter_ids)){
    $letter_ids = [];
}

// Sanitize and validate IDs
$valid_ids = [];
foreach($letter_ids as $id){
    $valid_ids[] = intval($id);
}

if(empty($valid_ids)){
    $_SESSION['alert_message'] = "Invalid letter IDs provided.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Get officer signature
$sig_q = mysqli_query($conn,"
    SELECT digital_signature, fullname
    FROM department_officers
    WHERE id='$officer_id'
    LIMIT 1
");
$sig_data = mysqli_fetch_assoc($sig_q);
$signature = $sig_data['digital_signature'] ?? '';
$officer_name = $sig_data['fullname'] ?? '';

if(empty($signature)){
    $_SESSION['alert_message'] = "You must upload your digital signature before signing letters.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Build safe ID list for query
$ids_string = implode(',', array_map('intval', $valid_ids));

// ✅ DEBUG: Log the department name being used
error_log("Department name: " . $department_name);

// ✅ FIXED: More flexible query to find letters
$verify_query = mysqli_query($conn,"
SELECT dl.*, s.fullname as student_name, s.reg_number, s.department
FROM department_letters dl
INNER JOIN students s ON s.id = dl.student_id
WHERE dl.id IN ($ids_string)
AND dl.status = 'pending'
AND (
    TRIM(LOWER(dl.department_name)) = TRIM(LOWER('$department_name'))
    OR 
    TRIM(LOWER(s.department)) = TRIM(LOWER('$department_name'))
    OR
    TRIM(LOWER(dl.department_name)) LIKE CONCAT('%', TRIM(LOWER('$department_name')), '%')
    OR
    TRIM(LOWER(s.department)) LIKE CONCAT('%', TRIM(LOWER('$department_name')), '%')
    OR
    TRIM(LOWER('$department_name')) LIKE CONCAT('%', TRIM(LOWER(dl.department_name)), '%')
    OR
    TRIM(LOWER('$department_name')) LIKE CONCAT('%', TRIM(LOWER(s.department)), '%')
)
");

// ✅ DEBUG: Check how many rows were found
$row_count = mysqli_num_rows($verify_query);
error_log("Found $row_count pending letters for department: $department_name");

if($row_count == 0){
    // ✅ Try a more lenient query to debug - get all pending letters
    $debug_query = mysqli_query($conn,"
    SELECT dl.*, s.fullname as student_name, s.reg_number, s.department, dl.department_name as letter_dept
    FROM department_letters dl
    INNER JOIN students s ON s.id = dl.student_id
    WHERE dl.id IN ($ids_string)
    AND dl.status = 'pending'
    ");
    
    $debug_count = mysqli_num_rows($debug_query);
    error_log("Total pending letters in selected IDs: $debug_count");
    
    while($debug_row = mysqli_fetch_assoc($debug_query)){
        error_log("Letter ID: {$debug_row['id']}, Letter Dept: {$debug_row['letter_dept']}, Student Dept: {$debug_row['department']}");
    }
    
    $_SESSION['alert_message'] = "No pending letters found for this department. Debug: Found $debug_count pending letters but none match department '$department_name'.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// Process each letter with enhanced error handling
$signed_count = 0;
$skipped_count = 0;
$failed_count = 0;
$success_letters = [];
$skipped_letters = [];
$failed_letters = [];

while($row = mysqli_fetch_assoc($verify_query)){
    $letter_id = $row['id'];
    $student_id = $row['student_id'];
    $student_name = $row['student_name'];
    $reg_number = $row['reg_number'];
    $original_file = $row['original_file'];
    $current_status = $row['status'];
    
    // ✅ Check if already signed
    if($current_status == 'signed'){
        $skipped_count++;
        $skipped_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Already signed";
        continue;
    }
    
    // ✅ Check if rejected
    if($current_status == 'rejected'){
        $skipped_count++;
        $skipped_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Rejected";
        continue;
    }
    
    // ✅ Check if not pending
    if($current_status != 'pending'){
        $skipped_count++;
        $skipped_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Status: $current_status (not pending)";
        continue;
    }
    
    // Get file info
    $file_info = pathinfo($original_file);
    $file_extension = strtolower($file_info['extension'] ?? '');
    $file_basename = $file_info['filename'] ?? 'letter';
    
    // Build file paths
    $original_path = "../../assets/uploads/department_letters/" . $original_file;
    $signature_path = "../../assets/uploads/signatures/" . $signature;
    
    // ✅ Check if original file exists
    if(!file_exists($original_path)){
        $skipped_count++;
        $skipped_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Original file not found: $original_path";
        continue;
    }
    
    // ✅ Check if signature exists
    if(!file_exists($signature_path)){
        $skipped_count++;
        $skipped_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Signature file not found: $signature_path";
        continue;
    }
    
    // Determine the PDF file to use as template
    $pdf_file = $original_path;
    $temp_pdf = null;
    
    // If the file is DOCX, convert it to PDF first
    if(($file_extension == 'docx' || $file_extension == 'doc') && $use_docx_conversion){
        try {
            $phpWord = IOFactory::load($original_path);
            $temp_pdf = "../../assets/uploads/department_letters/temp_" . time() . "_" . $letter_id . ".pdf";
            $pdf_writer = IOFactory::createWriter($phpWord, 'PDF');
            $pdf_writer->save($temp_pdf);
            $pdf_file = $temp_pdf;
            error_log("Converted DOCX to PDF for letter ID: $letter_id");
        } catch (Exception $e) {
            $failed_count++;
            $failed_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - DOCX conversion failed: " . $e->getMessage();
            continue;
        }
    } elseif(($file_extension == 'docx' || $file_extension == 'doc') && !$use_docx_conversion) {
        $failed_count++;
        $failed_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - DOCX conversion not available";
        continue;
    }
    
    // ✅ Check if the file is a valid PDF
    try {
        $test_pdf = new Fpdi();
        $test_pdf->setSourceFile($pdf_file);
        $test_pdf->importPage(1);
    } catch (Exception $e) {
        $failed_count++;
        $failed_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Invalid PDF: " . $e->getMessage();
        // Clean up temp file if it exists
        if($temp_pdf && file_exists($temp_pdf)){
            unlink($temp_pdf);
        }
        continue;
    }
    
    // Create signed PDF
    try {
        $pdf = new Fpdi();
        $pdf->AddPage();
        
        // Use the converted PDF (or original) as the template
        $pdf->setSourceFile($pdf_file);
        $template = $pdf->importPage(1);
        $pdf->useTemplate($template);

        // Get the page dimensions
        $page_width = $pdf->getPageWidth();
        $page_height = $pdf->getPageHeight();
        
        /* ===============================
           POSITION CONFIGURATION
           - Signature moved down (y: page_height - 40)
           - Width reduced (width: 12)
           - Officer name REMOVED from PDF
        =============================== */
        $positions = [
            'signature' => [
                'x' => 155,
                'y' => $page_height - 54,  // Moved down
                'width' => 13              // Reduced width
            ],
            'date' => [
                'x' => 150,
                'y' => $page_height - 46,  // Adjusted to match signature position
                'width' => 15
            ]
        ];

        /* ===============================
           ADD SIGNATURE (SMALLER WIDTH)
        =============================== */
        if(file_exists($signature_path)){
            $pdf->Image(
                $signature_path, 
                $positions['signature']['x'], 
                $positions['signature']['y'], 
                $positions['signature']['width']
            );
        }

        /* ===============================
           ADD DATE ONLY (OFFICER NAME REMOVED)
        =============================== */
        $pdf->SetFont('Arial','',11);
        $pdf->SetXY($positions['date']['x'], $positions['date']['y']);
        $pdf->Cell($positions['date']['width'], 5, date("Y-m-d"), 0, 1);

        /* ===============================
           GENERATE UNIQUE FILENAME
        =============================== */
        $signed_filename = "signed_" . time() . "_" . $letter_id . "_" . $file_basename . ".pdf";
        $save_path = "../../assets/uploads/department_letters/" . $signed_filename;
        
        // Save the PDF to file
        $pdf->Output("F", $save_path);

        // Clean up temporary PDF if it was created
        if($temp_pdf && file_exists($temp_pdf) && $temp_pdf != $original_path){
            unlink($temp_pdf);
        }

        /* ===============================
           UPDATE DB - Only update letter status, NO AUTO-APPROVAL
           Officer name is still saved to database for record-keeping
        =============================== */
        $update_query = "
        UPDATE department_letters
        SET status='signed',
            signed_file='$signed_filename',
            signature_file='$signature',
            signed_at=NOW(),
            officer_name='$officer_name'
        WHERE id='$letter_id'
        ";
        
        if(mysqli_query($conn, $update_query)){
            $signed_count++;
            $success_letters[] = "Letter ID $letter_id - $student_name ($reg_number)";
            
            // ✅ DO NOT auto-approve the main clearance
            // The officer must approve separately via review-request.php
        } else {
            $failed_count++;
            $failed_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - Database update failed: " . mysqli_error($conn);
            // Delete the generated file if database update failed
            if(file_exists($save_path)){
                unlink($save_path);
            }
        }
        
    } catch (Exception $e) {
        $failed_count++;
        $failed_letters[] = "Letter ID $letter_id - $student_name ($reg_number) - " . $e->getMessage();
        // Clean up temp file if it exists
        if($temp_pdf && file_exists($temp_pdf)){
            unlink($temp_pdf);
        }
    }
}

// Build detailed result message
$result_message = "";
$result_type = 'approved';

if($signed_count > 0){
    $result_message = "✅ $signed_count letter(s) signed successfully!";
    if(count($success_letters) > 0 && count($success_letters) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $success_letters) . "</small>";
    } elseif(count($success_letters) > 5){
        $result_message .= "<br><small>" . count($success_letters) . " letters processed successfully.</small>";
    }
}

// Add skipped information
if($skipped_count > 0){
    $result_message .= "<br><br>⏭️ $skipped_count letter(s) skipped:";
    if(count($skipped_letters) > 0 && count($skipped_letters) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $skipped_letters) . "</small>";
    } else {
        $result_message .= "<br><small>" . $skipped_count . " letters skipped (see details).</small>";
    }
}

// Add failed information
if($failed_count > 0){
    $result_message .= "<br><br>❌ $failed_count letter(s) failed:";
    if(count($failed_letters) > 0 && count($failed_letters) <= 5){
        $result_message .= "<br><small>" . implode("<br>", $failed_letters) . "</small>";
    } else {
        $result_message .= "<br><small>" . $failed_count . " letters failed (see details).</small>";
    }
    $result_type = 'error';
}

// If nothing was signed and nothing failed but everything was skipped
if($signed_count == 0 && $failed_count == 0 && $skipped_count > 0){
    $result_message = "⚠️ No letters were signed. $skipped_count letter(s) were skipped:";
    if(count($skipped_letters) > 0 && count($skipped_letters) <= 10){
        $result_message .= "<br><small>" . implode("<br>", $skipped_letters) . "</small>";
    } else {
        $result_message .= "<br><small>All selected letters are already processed.</small>";
    }
    $result_type = 'info';
}

// If everything failed
if($signed_count == 0 && $failed_count > 0 && $skipped_count == 0){
    $result_message = "❌ Failed to sign any letters. All " . count($valid_ids) . " letter(s) failed.";
    $result_type = 'error';
}

// Store in session for display
$_SESSION['alert_message'] = $result_message;
$_SESSION['alert_type'] = $result_type;

header("Location: dashboard.php");
exit();
?>