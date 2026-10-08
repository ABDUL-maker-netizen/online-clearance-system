
<?php

session_start();

include '../includes/config.php';
include '../includes/functions.php';

require '../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

/* ===============================
   CHECK IF STUDENT IS LOGGED IN OR IF OFFICER/ADMIN IS PRINTING
=============================== */

$student_id = null;
$is_officer_print = false;

// Check if student_id is passed via GET (officer/admin printing)
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
    $is_officer_print = true;
} 
// Check if student is logged in
else if (isset($_SESSION['student_id'])) {
    $student_id = $_SESSION['student_id'];
}
// If neither, redirect to login
else {
    header("Location: ../login.php");
    exit();
}

/* ===============================
   FETCH STUDENT
=============================== */

$student_query = mysqli_query(
    $conn,
    "SELECT * FROM students WHERE id='$student_id'"
);

if (!$student_query) {
    die("Unable to fetch student information.");
}

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    die("Student not found.");
}

/* ===============================
   CHECK IF PHPWORD IS AVAILABLE
   FOR DOCX CONVERSION
=============================== */

$use_docx_conversion = false;

if (class_exists('PhpOffice\\PhpWord\\IOFactory')) {

    try {

        Settings::setPdfRendererPath('../vendor/dompdf/dompdf');
        Settings::setPdfRendererName('DomPDF');

        $use_docx_conversion = true;

    } catch (Exception $e) {

        $use_docx_conversion = false;

        error_log(
            "PhpWord PDF renderer configuration failed: "
            . $e->getMessage()
        );
    }
}

/* ===============================
   GET STUDENT'S MAIN CLEARANCE FILE
=============================== */

$main_file_query = mysqli_query(
    $conn,
    "SELECT filename
     FROM clearance_uploads
     WHERE student_id='$student_id'
     ORDER BY id DESC
     LIMIT 1"
);

$main_file_data = mysqli_fetch_assoc($main_file_query);

$template_file = null;
$file_exists = false;
$use_dynamic_template = false;
$temp_pdf = null;

/* ===============================
   CHECK UPLOADED CLEARANCE FILE
=============================== */

if ($main_file_data && !empty($main_file_data['filename'])) {

    $template_file =
        "../assets/uploads/" .
        $main_file_data['filename'];

    $file_exists = file_exists($template_file);

    if ($file_exists) {

        $ext = strtolower(
            pathinfo($template_file, PATHINFO_EXTENSION)
        );

        /* ===============================
           PDF FILE
        =============================== */

        if ($ext === 'pdf') {

            $use_dynamic_template = true;
        }

        /* ===============================
           DOCX / DOC FILE
        =============================== */

        elseif (
            ($ext === 'docx' || $ext === 'doc')
            && $use_docx_conversion
        ) {

            try {

                $phpWord = IOFactory::load($template_file);

                $temp_pdf =
                    "../assets/uploads/temp_" .
                    time() .
                    ".pdf";

                $pdf_writer =
                    IOFactory::createWriter(
                        $phpWord,
                        'PDF'
                    );

                $pdf_writer->save($temp_pdf);

                $template_file = $temp_pdf;

                $use_dynamic_template = true;
                $file_exists = true;

            } catch (Exception $e) {

                $use_dynamic_template = false;
                $file_exists = false;

                error_log(
                    "DOCX to PDF conversion failed: "
                    . $e->getMessage()
                );
            }
        }

        /* ===============================
           UNSUPPORTED FILE
        =============================== */

        else {

            $use_dynamic_template = false;
            $file_exists = false;
        }
    }
}

/* ===============================
   DEFAULT TEMPLATE
=============================== */

if (!$file_exists || !$use_dynamic_template) {

    $default_templates = [

        "../assets/clearance-template.pdf",

        "../assets/clearance_form.pdf",

        "../assets/clearance-form.pdf",

        "../assets/templates/clearance-template.pdf"
    ];

    $template_found = false;

    foreach ($default_templates as $template_path) {

        if (file_exists($template_path)) {

            $template_file = $template_path;

            $file_exists = true;

            $template_found = true;

            break;
        }
    }

    if (!$template_found) {

        die(
            "No template file found. Please upload your main clearance file or contact admin."
        );
    }
}

/* ===============================
   TOTAL OFFICES
=============================== */

/*
 * IMPORTANT:
 * getTotalDepartments() is already defined
 * inside ../includes/functions.php.
 *
 * DO NOT define it again here.
 */

$total_offices = getTotalDepartments($conn);

/* ===============================
   APPROVED COUNT
=============================== */

$approved_query = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT department_role) AS approved_count
     FROM clearance_status
     WHERE student_id='$student_id'
     AND status='approved'"
);

if (!$approved_query) {
    $approved = 0;
} else {

    $approved_data = mysqli_fetch_assoc($approved_query);

    $approved =
        $approved_data['approved_count'] ?? 0;
}

/* ===============================
   BLOCK IF CLEARANCE IS INCOMPLETE
=============================== */

if ($approved < $total_offices) {

    $progress_percent =
        ($total_offices > 0)
        ? round(($approved / $total_offices) * 100)
        : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Clearance Incomplete</title>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

body {
    background: #f8f9ff;
    font-family: 'Hanken Grotesk', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 20px;
}

.incomplete-card {
    max-width: 500px;
    width: 100%;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 1rem;
    padding: 40px 32px;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    text-align: center;
    border: 1px solid #e5e7eb;
}

.incomplete-card .icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 9999px;
    background: #fee2e2;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px auto;
}

.incomplete-card .icon-wrapper .icon {
    font-size: 40px;
    line-height: 1;
}

.incomplete-card h3 {
    font-size: 24px;
    font-weight: 700;
    color: #dc2626;
    margin-bottom: 12px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.incomplete-card .subtitle {
    font-size: 16px;
    color: #4b5563;
    margin-bottom: 24px;
    line-height: 1.5;
}

.progress-info {
    background: #f8f9ff;
    padding: 20px;
    border-radius: 0.75rem;
    margin: 20px 0;
    border: 1px solid #e5e7eb;
}

.progress-info .progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
    color: #111827;
    font-weight: 500;
}

.progress-info .progress-label .count {
    color: #4b5563;
    font-weight: 400;
}

.progress-info .progress-track {
    height: 10px;
    background: #e5e7eb;
    border-radius: 9999px;
    overflow: hidden;
}

.progress-info .progress-track .progress-fill {
    height: 100%;
    background: #1e40af;
    border-radius: 9999px;
    transition: width 0.6s ease;
}

.btn-back {
    background: #1e40af;
    color: #ffffff;
    padding: 12px 24px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    font-family: 'Hanken Grotesk', sans-serif;
}

.btn-back:hover {
    background: #1e3a8a;
    color: #ffffff;
    transform: scale(0.98);
    text-decoration: none;
}

.btn-back i {
    font-size: 1.1rem;
}

/* Responsive */
@media (max-width: 576px) {
    .incomplete-card {
        padding: 28px 20px;
    }
    
    .incomplete-card .icon-wrapper {
        width: 64px;
        height: 64px;
    }
    
    .incomplete-card .icon-wrapper .icon {
        font-size: 32px;
    }
    
    .incomplete-card h3 {
        font-size: 20px;
    }
    
    .incomplete-card .subtitle {
        font-size: 14px;
    }
    
    .progress-info {
        padding: 16px;
    }
    
    .progress-info .progress-label {
        font-size: 13px;
    }
}

@media (max-width: 400px) {
    .incomplete-card {
        padding: 20px 16px;
    }
    
    .incomplete-card h3 {
        font-size: 18px;
    }
    
    .btn-back {
        font-size: 14px;
        padding: 10px 16px;
    }
}

</style>

</head>

<body>

<div class="container">
    <div class="incomplete-card">
        <div class="icon-wrapper">
            <div class="icon">⚠️</div>
        </div>

        <h3>Clearance Not Complete</h3>

        <p class="subtitle">
            You must be approved by all departments before printing your clearance slip.
        </p>

        <div class="progress-info">
            <div class="progress-label">
                <span>Progress</span>
                <span class="count">
                    <?php echo $approved; ?> / <?php echo $total_offices; ?> offices
                </span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
            </div>
            <div style="text-align: right; margin-top: 4px; font-size: 13px; color: #4b5563;">
                <?php echo $progress_percent; ?>% Complete
            </div>
        </div>

        <a href="dashboard.php" class="btn-back">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

</body>

</html>

<?php

    exit();
}

/* ===============================
   CHECK IF STUDENT ALREADY HAS AN ACTIVE CLEARANCE SLIP
   If yes, just serve the existing file without regenerating
=============================== */

$existing_slip_query = mysqli_query($conn, "
    SELECT id, slip_filename, slip_path 
    FROM clearance_slips 
    WHERE student_id = '$student_id' 
    AND is_active = 1
    ORDER BY id DESC 
    LIMIT 1
");

if (mysqli_num_rows($existing_slip_query) > 0) {
    $existing_slip = mysqli_fetch_assoc($existing_slip_query);
    $existing_file_path = $existing_slip['slip_path'];
    
    // Check if the file still exists on the server
    if (file_exists($existing_file_path)) {
        // File exists, serve it directly without regenerating
        $existing_filename = $existing_slip['slip_filename'];
        
        // Output the existing PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $existing_filename . '"');
        header('Content-Length: ' . filesize($existing_file_path));
        readfile($existing_file_path);
        exit();
    } else {
        // File exists in database but not on server - deactivate it and regenerate
        mysqli_query($conn, "
            UPDATE clearance_slips 
            SET is_active = 0 
            WHERE id = '" . $existing_slip['id'] . "'
        ");
    }
}

/* ===============================
   PDF INITIALIZATION - Only reaches here if no valid existing slip
=============================== */

try {

    $pdf = new Fpdi();

    $pdf->AddPage();

    $pdf->setSourceFile($template_file);

    $template = $pdf->importPage(1);

    $pdf->useTemplate($template);

} catch (Exception $e) {

    if (
        isset($temp_pdf)
        && file_exists($temp_pdf)
    ) {
        unlink($temp_pdf);
    }

    die(
        "Unable to load the clearance template: "
        . htmlspecialchars($e->getMessage())
    );
}

/* ===============================
   GET PAGE DIMENSIONS
=============================== */

$page_width = $pdf->getPageWidth();

$page_height = $pdf->getPageHeight();

/* ===============================
   STUDENT INFORMATION
=============================== */

$pdf->SetFont(
    'Arial',
    '',
    10
);

/* Student Name */

$pdf->SetXY(
    30,
    32
);

$pdf->Cell(
    90,
    5,
    $student['fullname'],
    0,
    0,
    'C'
);

/* Registration Number */

$pdf->SetXY(
    165,
    32
);

$pdf->Cell(
    40,
    5,
    $student['reg_number'],
    0,
    0,
    'L'
);

/* Programme */

$pdf->SetXY(
    60,
    40
);

$pdf->Cell(
    90,
    5,
    $student['programme'],
    0,
    0,
    'L'
);

/* Degree Awarded */

$pdf->SetXY(
    166,
    40
);

$pdf->Cell(
    40,
    5,
    $student['degree_awarded'],
    0,
    0,
    'L'
);

/* ===============================
   DYNAMIC POSITIONS
=============================== */

$positions = [

    'BURSAR' => [

        'y_start' => 88,

        'signature' => [
            'x' => 140,
            'y_offset' => -28
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -24
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -25
        ]
    ],

    'ALUMNI RELATION DIVISION' => [

        'y_start' => 120,

        'signature' => [
            'x' => 140,
            'y_offset' => -35
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -31
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -31
        ]
    ],

    'LIBRARY' => [

        'y_start' => 152,

        'signature' => [
            'x' => 140,
            'y_offset' => -41
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -37
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -38
        ]
    ],

    'DEPARTMENT' => [

        'y_start' => 184,

        'signature' => [
            'x' => 140,
            'y_offset' => -44
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -39
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -40
        ]
    ],

    'FACULTY' => [

        'y_start' => 216,

        'signature' => [
            'x' => 140,
            'y_offset' => -50
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -45
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -46
        ]
    ],

    'SPORT UNIT' => [

        'y_start' => 248,

        'signature' => [
            'x' => 140,
            'y_offset' => -53
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -47
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -48
        ]
    ],

    'HALL' => [

        'y_start' => 280,

        'signature' => [
            'x' => 140,
            'y_offset' => -55
        ],

        'name' => [
            'x' => 20,
            'y_offset' => -49
        ],

        'date' => [
            'x' => 150,
            'y_offset' => -50
        ]
    ]
];

/* ===============================
   APPROVALS
=============================== */

$approval_query = mysqli_query(
    $conn,
    "SELECT *
     FROM clearance_status
     WHERE student_id='$student_id'
     AND status='approved'"
);

if ($approval_query) {

    while ($row = mysqli_fetch_assoc($approval_query)) {

        $office =
            strtoupper(
                trim($row['department_role'])
            );

        $approved_at =
            $row['approved_at'] ?? null;

        if (!isset($positions[$office])) {
            continue;
        }

        $role =
            mysqli_real_escape_string(
                $conn,
                $row['department_role']
            );

        $officer_name = 'N/A';
        $signature = '';

        /* ===============================
           GET OFFICER BASED ON OFFICE TYPE
        =============================== */

        if ($office === 'DEPARTMENT') {
            // Get department officer
            $dept = mysqli_real_escape_string(
                $conn,
                trim($student['department'])
            );
            
            $officer_result = mysqli_query(
                $conn,
                "SELECT fullname, digital_signature
                 FROM department_officers
                 WHERE TRIM(LOWER(department_name)) = TRIM(LOWER('$dept'))
                 LIMIT 1"
            );
            
            if ($officer_result && mysqli_num_rows($officer_result) > 0) {
                $officer_data = mysqli_fetch_assoc($officer_result);
                $officer_name = $officer_data['fullname'] ?? 'N/A';
                $signature = $officer_data['digital_signature'] ?? '';
            }
            
        } elseif ($office === 'FACULTY') {
            // Get faculty officer
            $faculty = mysqli_real_escape_string(
                $conn,
                trim($student['faculty_name'])
            );
            
            $officer_result = mysqli_query(
                $conn,
                "SELECT fullname, digital_signature
                 FROM faculty_officers
                 WHERE TRIM(LOWER(faculty_name)) = TRIM(LOWER('$faculty'))
                 LIMIT 1"
            );
            
            if ($officer_result && mysqli_num_rows($officer_result) > 0) {
                $officer_data = mysqli_fetch_assoc($officer_result);
                $officer_name = $officer_data['fullname'] ?? 'N/A';
                $signature = $officer_data['digital_signature'] ?? '';
            }
            
        } else {
            // Get regular office officer from officers table
            $officer_result = mysqli_query(
                $conn,
                "SELECT fullname, digital_signature
                 FROM officers
                 WHERE UPPER(office) = UPPER('$role')
                 LIMIT 1"
            );
            
            if ($officer_result && mysqli_num_rows($officer_result) > 0) {
                $officer_data = mysqli_fetch_assoc($officer_result);
                $officer_name = $officer_data['fullname'] ?? 'N/A';
                $signature = $officer_data['digital_signature'] ?? '';
            }
        }

        $pos =
            $positions[$office];

        $y =
            $pos['y_start'];

        /* ===============================
           SIGNATURE PATH
        =============================== */

        $sig_path =
            "../assets/uploads/signatures/"
            . $signature;

        /* ===============================
           ADD SIGNATURE
        =============================== */

        if (
            !empty($signature)
            && file_exists($sig_path)
        ) {

            $pdf->Image(
                $sig_path,
                $pos['signature']['x'],
                $y + $pos['signature']['y_offset'],
                12
            );
        }

        /* ===============================
           OFFICER NAME
        =============================== */

        $pdf->SetFont(
            'Arial',
            '',
            10
        );

        if (
            $pdf->GetStringWidth($officer_name)
            > 40
        ) {

            $pdf->SetFont(
                'Arial',
                '',
                9
            );
        }

        $pdf->SetXY(
            $pos['name']['x'],
            $y + $pos['name']['y_offset']
        );

        $pdf->Cell(
            40,
            4,
            $officer_name,
            0,
            0,
            'C'
        );

        /* ===============================
           APPROVAL DATE
        =============================== */

        $pdf->SetFont(
            'Arial',
            '',
            8
        );

        $pdf->SetXY(
            $pos['date']['x'],
            $y + $pos['date']['y_offset']
        );

        $approval_date =
            !empty($approved_at)
            ? date(
                "Y-m-d",
                strtotime($approved_at)
            )
            : '';

        $pdf->Cell(
            25,
            4,
            $approval_date,
            0,
            0,
            'C'
        );
    }
}

/* ===============================
   GENERATE QR CODE
=============================== */

$qr_dir =
    __DIR__ .
    "/../assets/qrcodes/";

if (!file_exists($qr_dir)) {

    mkdir(
        $qr_dir,
        0777,
        true
    );
}

/* ===============================
   GET BASE URL
=============================== */

$protocol =
    (
        isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] === 'on'
    )
    ? "https://"
    : "http://";

$host =
    $_SERVER['HTTP_HOST']
    ?? 'localhost';

$base_url =
    $protocol
    . $host
    . "/online-clearance-system/";

$verification_code =
    $student['verification_code']
    ?? '';

$qr_data =
    $base_url
    . "verify.php?id="
    . urlencode($student['id'])
    . "&code="
    . urlencode($verification_code);

/* ===============================
   CREATE QR
=============================== */

try {

    $qrCode =
        new QrCode($qr_data);

    $writer =
        new PngWriter();

    $result =
        $writer->write($qrCode);

    $qr_file =
        $qr_dir
        . "student_"
        . $student_id
        . ".png";

    $result->saveToFile($qr_file);

    /* ===============================
       ADD QR TO PDF
    =============================== */

    $qr_x = 150;

    $qr_y =
        $page_height - 30;

    $qr_width = 30;

    if (file_exists($qr_file)) {

        $pdf->Image(
            "../assets/qrcodes/student_"
            . $student_id
            . ".png",
            $qr_x,
            $qr_y,
            $qr_width
        );
    }

} catch (Exception $e) {

    error_log(
        "QR Code generation failed: "
        . $e->getMessage()
    );
}

/* ===============================
   GENERATE UNIQUE FILENAME FOR SIGNED CLEARANCE SLIP
   - Replace slashes in reg_number with underscore to avoid directory issues
=============================== */

// Create a safe version of the registration number
$safe_reg_number = str_replace('/', '_', $student['reg_number']);

$signed_slip_filename = "cleared_slip_" . time() . "_" . $safe_reg_number . ".pdf";

/* ===============================
   SAVE THE PDF TO FILE (STORE IN ASSETS)
=============================== */

$save_path = "../assets/uploads/clearance_slips/" . $signed_slip_filename;

// Create directory if not exists
if (!file_exists("../assets/uploads/clearance_slips/")) {
    mkdir("../assets/uploads/clearance_slips/", 0777, true);
}

// Save the PDF to file
$pdf->Output("F", $save_path);

/* ===============================
   STORE IN DATABASE - Check if table exists, create if not
=============================== */

// Check if the clearance_slips table exists
$table_check = mysqli_query($conn, "
    SELECT 1 FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'clearance_slips'
");

if (mysqli_num_rows($table_check) == 0) {
    // Create the table if it doesn't exist
    $create_table = "
    CREATE TABLE IF NOT EXISTS clearance_slips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        reg_number VARCHAR(100) NOT NULL,
        slip_filename VARCHAR(255) NOT NULL,
        slip_path VARCHAR(255) NOT NULL,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_student_id (student_id),
        INDEX idx_reg_number (reg_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    mysqli_query($conn, $create_table);
}

// Deactivate any existing slips for this student (should already be deactivated, but just in case)
mysqli_query($conn, "
    UPDATE clearance_slips 
    SET is_active = 0 
    WHERE student_id = '$student_id' 
    AND is_active = 1
");

// Insert new slip record
$insert_slip = mysqli_query($conn, "
    INSERT INTO clearance_slips 
    (student_id, reg_number, slip_filename, slip_path) 
    VALUES (
        '$student_id',
        '{$student['reg_number']}',
        '$signed_slip_filename',
        '$save_path'
    )
");

if (!$insert_slip) {
    error_log("Failed to save clearance slip to database: " . mysqli_error($conn));
}

/* ===============================
   ALSO STORE IN STUDENTS TABLE
=============================== */

// Update students table with the clearance slip filename
mysqli_query($conn, "
    UPDATE students 
    SET clearance_slip = '$signed_slip_filename' 
    WHERE id = '$student_id'
");

/* ===============================
   OUTPUT PDF TO BROWSER
=============================== */

// Use the original reg_number with slashes for display
$display_filename = 'clearance-slip-' . $student['reg_number'] . '.pdf';

/*
 * Output the PDF to the browser.
 */

$pdf->Output(
    'I',
    $display_filename
);

/* ===============================
   CLEAN TEMP PDF
=============================== */

if (
    isset($temp_pdf)
    && file_exists($temp_pdf)
) {

    unlink($temp_pdf);
}

?>