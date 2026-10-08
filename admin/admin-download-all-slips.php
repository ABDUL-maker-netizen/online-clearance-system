<?php
session_start();
include '../includes/config.php';
include '../includes/functions.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])){
    die("Unauthorized access.");
}

// Get student IDs from POST
if(!isset($_POST['student_ids']) || empty($_POST['student_ids'])){
    die("No students selected.");
}

$student_ids = json_decode($_POST['student_ids'], true);
if(!is_array($student_ids) || empty($student_ids)){
    die("Invalid student IDs.");
}

// Create temporary directory
$temp_dir = "../assets/temp/" . time() . "_" . uniqid();
if(!file_exists($temp_dir)){
    mkdir($temp_dir, 0777, true);
}

$zip_filename = "clearance_slips_" . date('Y-m-d_H-i-s') . ".zip";
$zip_path = $temp_dir . "/" . $zip_filename;

// Create ZIP file
$zip = new ZipArchive();
if($zip->open($zip_path, ZipArchive::CREATE) !== TRUE){
    die("Failed to create ZIP file.");
}

$success_count = 0;
$fail_count = 0;

foreach($student_ids as $student_id){
    $student_id = intval($student_id);
    
    // Get student info
    $student_query = mysqli_query($conn, "
        SELECT fullname, reg_number 
        FROM students 
        WHERE id = '$student_id'
    ");
    $student = mysqli_fetch_assoc($student_query);
    
    if(!$student){
        $fail_count++;
        continue;
    }
    
    // Check if student has a signed slip
    $slip_query = mysqli_query($conn, "
        SELECT slip_path, slip_filename 
        FROM clearance_slips 
        WHERE student_id = '$student_id' 
        AND is_active = 1
        ORDER BY id DESC 
        LIMIT 1
    ");
    
    $slip = mysqli_fetch_assoc($slip_query);
    
    if($slip && file_exists($slip['slip_path'])){
        // Use existing slip
        $file_content = file_get_contents($slip['slip_path']);
        $safe_name = preg_replace('/[^A-Za-z0-9\-_]/', '_', $student['reg_number']);
        $filename = $safe_name . "_" . $student['fullname'] . ".pdf";
        $zip->addFromString($filename, $file_content);
        $success_count++;
    } else {
        $fail_count++;
    }
}

$zip->close();

// Check if any files were added
if($success_count == 0){
    // Clean up
    unlink($zip_path);
    rmdir($temp_dir);
    die("No clearance slips could be generated.");
}

// Output the ZIP file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);

// Clean up
unlink($zip_path);
rmdir($temp_dir);

exit();
?>