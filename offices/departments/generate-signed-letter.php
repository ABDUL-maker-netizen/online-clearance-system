<?php
session_start();

include '../../includes/config.php';
require '../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

// ✅ Check if PhpWord classes are available
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

if(!isset($_SESSION['department_officer_id'])){
    die("Unauthorized");
}

$id = intval($_GET['id']);

if($id <= 0){
    die("Invalid letter ID");
}

/* GET LETTER */
$letter_q = mysqli_query($conn,"
SELECT dl.*, s.fullname, s.reg_number, s.department
FROM department_letters dl
JOIN students s ON s.id = dl.student_id
WHERE dl.id='$id'");

$letter = mysqli_fetch_assoc($letter_q);

if(!$letter){
    die("Letter not found");
}

/* GET OFFICER */
$officer_id = $_SESSION['department_officer_id'];

$officer_q = mysqli_query($conn,"
SELECT fullname, digital_signature, email
FROM department_officers
WHERE id='$officer_id'");

$officer = mysqli_fetch_assoc($officer_q);

if(empty($officer['digital_signature'])){
    die("Upload signature first");
}

/* ===============================
   FILE PATHS
=============================== */
$original_file = "../../assets/uploads/department_letters/" . $letter['original_file'];

if(!file_exists($original_file)){
    die("Student's letter file not found. Please ask the student to upload the letter again.");
}

// Check file extension
$file_info = pathinfo($original_file);
$file_extension = strtolower($file_info['extension'] ?? '');
$file_basename = $file_info['filename'] ?? 'letter';

// Officer's signature
$signature_path = "../../assets/uploads/signatures/" . $officer['digital_signature'];

if(!file_exists($signature_path)){
    die("Signature file not found. Please re-upload your signature.");
}

/* ===============================
   CONVERT DOCX TO PDF IF NEEDED
=============================== */
$pdf_file = $original_file;
$temp_pdf = null;

// If the file is DOCX, convert it to PDF first
if(($file_extension == 'docx' || $file_extension == 'doc') && $use_docx_conversion){
    try {
        $phpWord = IOFactory::load($original_file);
        $temp_pdf = "../../assets/uploads/department_letters/temp_" . time() . ".pdf";
        $pdf_writer = IOFactory::createWriter($phpWord, 'PDF');
        $pdf_writer->save($temp_pdf);
        $pdf_file = $temp_pdf;
        error_log("Converted DOCX to PDF: " . $original_file . " -> " . $temp_pdf);
    } catch (Exception $e) {
        die("Error converting Word document to PDF: " . $e->getMessage() . ". Please upload a PDF file instead.");
    }
} elseif(($file_extension == 'docx' || $file_extension == 'doc') && !$use_docx_conversion) {
    die("DOCX conversion is not available. Please install PhpWord and DomPDF, or upload a PDF file.");
}

// Check if the file is a valid PDF
try {
    $test_pdf = new Fpdi();
    $test_pdf->setSourceFile($pdf_file);
    $test_pdf->importPage(1);
} catch (Exception $e) {
    if($file_extension == 'docx' || $file_extension == 'doc'){
        die("The DOCX file could not be converted to PDF. Please ensure the file is not corrupted and try again, or upload a PDF file.");
    }
    die("The file appears to be corrupted or invalid. Please ask the student to re-upload the letter as a valid PDF or Word document.");
}

/* ===============================
   CREATE SIGNED PDF
=============================== */
try {
    $pdf = new Fpdi();
    $pdf->AddPage();
    
    $pdf->setSourceFile($pdf_file);
    $template = $pdf->importPage(1);
    $pdf->useTemplate($template);

    $page_width = $pdf->getPageWidth();
    $page_height = $pdf->getPageHeight();
    
    /* ===============================
       POSITION CONFIGURATION
       - Signature moved down (y: page_height - 35)
       - Width reduced (width: 12)
       - Officer name REMOVED
    =============================== */
    $positions = [
        'signature' => [
            'x' => 155,
            'y' => $page_height - 54,  // Moved down from -45 to -35
            'width' => 13              // Reduced from 15 to 12
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
    $pdf->Cell($positions['date']['width'], 5,  date("Y-m-d"), 0, 1);

    /* ===============================
       GENERATE UNIQUE FILENAME
    =============================== */
    $signed_filename = "signed_" . time() . "_" . $file_basename . ".pdf";
    $save_path = "../../assets/uploads/department_letters/" . $signed_filename;
    
    $pdf->Output("F", $save_path);

    // Clean up temporary PDF if it was created
    if(isset($temp_pdf) && file_exists($temp_pdf) && $temp_pdf != $original_file){
        unlink($temp_pdf);
    }

    /* ===============================
       UPDATE DB - ONLY UPDATE LETTER STATUS
       ❌ REMOVED AUTO-APPROVAL OF MAIN CLEARANCE
    =============================== */
    mysqli_query($conn,"
        UPDATE department_letters
        SET status='signed',
            signed_file='$signed_filename',
            signature_file='{$officer['digital_signature']}',
            signed_at=NOW(),
            officer_name='{$officer['fullname']}'
        WHERE id='$id'
    ");

    // ❌ REMOVED: Auto-approval of main clearance
    // The officer must approve separately via review-request.php

    // ✅ SET SESSION ALERT
    $_SESSION['alert_message'] = "Letter signed successfully! Please approve the main clearance separately.";
    $_SESSION['alert_type'] = 'approved';

    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    die("Error generating signed PDF: " . $e->getMessage());
}
?>