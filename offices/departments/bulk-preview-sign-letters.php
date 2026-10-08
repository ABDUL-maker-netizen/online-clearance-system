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

$officer_id = $_SESSION['department_officer_id'];
$department_name = trim($_SESSION['department_name']);

// Check if request is POST from dashboard
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: dashboard.php");
    exit();
}

// Get selected letter IDs
if(!isset($_POST['letter_ids']) || empty($_POST['letter_ids'])){
    $_SESSION['alert_message'] = "No letters selected for bulk signing preview.";
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

// ============================================================
// FETCH LETTERS DATA
// ============================================================
$query = mysqli_query($conn,"
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
ORDER BY dl.id DESC
");

$letters_data = [];
while($row = mysqli_fetch_assoc($query)){
    $letters_data[] = $row;
}

$total_letters = count($letters_data);

if($total_letters == 0){
    $_SESSION['alert_message'] = "No pending letters found for this department.";
    $_SESSION['alert_type'] = 'error';
    header("Location: dashboard.php");
    exit();
}

// ============================================================
// FUNCTION TO GET FILE ICON BASED ON EXTENSION
// ============================================================
function getFileIcon($filename){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'bi-file-earmark-pdf',
        'doc' => 'bi-file-earmark-word',
        'docx' => 'bi-file-earmark-word',
        'jpg' => 'bi-file-earmark-image',
        'jpeg' => 'bi-file-earmark-image',
        'png' => 'bi-file-earmark-image',
        'gif' => 'bi-file-earmark-image',
        'webp' => 'bi-file-earmark-image',
        'bmp' => 'bi-file-earmark-image',
        'xls' => 'bi-file-earmark-excel',
        'xlsx' => 'bi-file-earmark-excel',
        'ppt' => 'bi-file-earmark-ppt',
        'pptx' => 'bi-file-earmark-ppt',
        'txt' => 'bi-file-earmark-text',
        'zip' => 'bi-file-earmark-zip',
        'rar' => 'bi-file-earmark-zip',
    ];
    
    return $icons[$ext] ?? 'bi-file-earmark';
}

function isImageFile($filename){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Check each letter's file exists
foreach($letters_data as &$letter){
    $original_file = $letter['original_file'];
    $original_path = "../../assets/uploads/department_letters/" . $original_file;
    $letter['file_exists'] = file_exists($original_path);
    $letter['file_size'] = $letter['file_exists'] ? formatFileSize(filesize($original_path)) : '0 B';
    $letter['file_ext'] = strtolower(pathinfo($original_file, PATHINFO_EXTENSION));
}
unset($letter);

// Count ready and missing
$ready_count = 0;
foreach($letters_data as $l){
    if($l['file_exists']) $ready_count++;
}
$missing_count = $total_letters - $ready_count;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Preview Letters - <?php echo htmlspecialchars($department_name); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
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
        }
        
        .container {
            max-width: 1280px;
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
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            background: #ffffff;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 20px;
            font-weight: 600;
            font-family: 'Hanken Grotesk', sans-serif;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        @media (max-width: 576px) {
            .card-body {
                padding: 14px;
            }
            .card-header {
                padding: 12px 14px;
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .header-title i {
            color: var(--primary-blue);
            margin-right: 8px;
        }
        
        .header-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            .header-title {
                font-size: 20px;
            }
        }
        
        @media (max-width: 400px) {
            .header-title {
                font-size: 18px;
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
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 14px;
        }
        
        .btn-back:hover {
            background: #374151;
            color: #ffffff;
            text-decoration: none;
            transform: scale(0.95);
        }
        
        .btn-sign-all {
            background: #006c49;
            color: #ffffff;
            padding: 6px 16px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-sign-all:hover {
            background: #005236;
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-sign-all:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-view {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 2px 10px;
            border-radius: 0.25rem;
            border: none;
            font-weight: 500;
            font-size: 10px;
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
        
        .btn-view:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Alert */
        .alert {
            border-radius: 0.5rem;
            padding: 12px 16px;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
        }
        
        .alert .btn {
            flex-shrink: 0;
        }
        
        /* Summary Box */
        .summary-box {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .summary-box .number {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .summary-box .number.primary { color: var(--primary-blue); }
        .summary-box .number.success { color: #006c49; }
        .summary-box .number.danger { color: #ef4444; }
        
        .summary-box .label {
            font-size: 12px;
            color: var(--text-muted);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* Letter Card */
        .letter-card {
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 16px;
            margin-bottom: 16px;
            background: #ffffff;
            transition: all 0.2s;
        }
        
        .letter-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .letter-card.ready {
            border-left: 4px solid #006c49;
        }
        
        .letter-card.missing {
            border-left: 4px solid #ef4444;
        }
        
        .letter-card .student-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .letter-card .student-reg {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .letter-card .text-muted {
            color: var(--text-muted) !important;
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
        
        .status-badge-ready {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge-missing {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Requirement Check */
        .requirement-check {
            font-size: 13px;
            padding: 4px 0;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .requirement-check .icon {
            font-size: 16px;
        }
        
        /* File Gallery */
        .file-gallery {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 4px;
            flex-wrap: nowrap;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .file-gallery::-webkit-scrollbar {
            height: 6px;
        }
        
        .file-gallery::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .file-gallery::-webkit-scrollbar-thumb {
            background: #c1c7cd;
            border-radius: 10px;
        }
        
        .file-gallery::-webkit-scrollbar-thumb:hover {
            background: #a8b0b8;
        }
        
        /* File Card */
        .file-card {
            flex: 0 0 140px;
            min-width: 140px;
            max-width: 140px;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 8px;
            text-align: center;
            background: #ffffff;
            transition: all 0.2s;
            position: relative;
        }
        
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--primary-blue);
        }
        
        .file-card.border-danger {
            border-color: #ef4444;
        }
        
        .file-card .file-thumbnail {
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 0.25rem;
            overflow: hidden;
            margin-bottom: 4px;
            cursor: pointer;
        }
        
        .file-card .file-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-card .file-thumbnail .file-icon {
            font-size: 48px;
            color: #6b7280;
        }
        
        .file-card .file-thumbnail .file-icon.pdf { color: #ef4444; }
        .file-card .file-thumbnail .file-icon.word { color: var(--primary-blue); }
        .file-card .file-thumbnail .file-icon.image { color: #10b981; }
        .file-card .file-thumbnail .file-icon.missing { color: #ef4444; opacity: 0.5; }
        
        .file-card .file-name {
            font-size: 11px;
            color: #495057;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
            margin: 0 auto;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .file-card .file-size {
            font-size: 9px;
            color: var(--text-muted);
        }
        
        .file-card .file-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 500;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .file-card .file-badge.letter {
            background: #f59e0b;
            color: #ffffff;
        }
        
        .file-card .file-badge.missing {
            background: #ef4444;
            color: #ffffff;
        }
        
        .file-card .file-actions {
            margin-top: 4px;
        }
        
        .file-card .file-actions .btn {
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 0.25rem;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
        }
        
        /* Signature Preview */
        .signature-preview {
            background: #f8f9fa;
            border: 1px dashed var(--border-color);
            border-radius: 0.375rem;
            padding: 8px 12px;
            text-align: center;
            margin-top: 4px;
        }
        
        .signature-preview img {
            max-height: 40px;
            max-width: 100px;
        }
        
        .signature-preview .no-signature {
            color: var(--text-muted);
            font-size: 12px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* Checkbox */
        .checkbox-col {
            width: 40px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* Legend */
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .legend .badge {
            font-family: 'Hanken Grotesk', sans-serif;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .letter-card {
                padding: 12px;
            }
            .file-card {
                flex: 0 0 100px;
                min-width: 100px;
                max-width: 100px;
            }
            .file-card .file-thumbnail {
                height: 75px;
            }
            .file-card .file-thumbnail .file-icon {
                font-size: 36px;
            }
            .summary-box .number {
                font-size: 22px;
            }
        }
        
        @media (max-width: 576px) {
            .letter-card .row {
                flex-direction: column;
                gap: 10px;
            }
            .checkbox-col {
                width: 100%;
            }
            .file-gallery {
                gap: 6px;
            }
            .file-card {
                flex: 0 0 80px;
                min-width: 80px;
                max-width: 80px;
            }
            .file-card .file-thumbnail {
                height: 60px;
            }
            .file-card .file-thumbnail .file-icon {
                font-size: 28px;
            }
            .file-card .file-name {
                font-size: 9px;
                max-width: 70px;
            }
            .file-card .file-badge {
                font-size: 8px;
                padding: 1px 6px;
            }
            .file-card .file-actions .btn {
                font-size: 8px;
                padding: 1px 6px;
            }
            .summary-box .number {
                font-size: 18px;
            }
            .summary-box .label {
                font-size: 10px;
            }
            .btn-back, .btn-sign-all {
                font-size: 12px;
                padding: 5px 12px;
            }
            .legend {
                gap: 6px;
            }
            .legend .badge {
                font-size: 10px;
            }
        }
        
        @media (max-width: 400px) {
            .container {
                padding: 8px;
            }
            .header-title {
                font-size: 16px;
            }
            .file-card {
                flex: 0 0 70px;
                min-width: 70px;
                max-width: 70px;
            }
            .file-card .file-thumbnail {
                height: 50px;
            }
            .file-card .file-thumbnail .file-icon {
                font-size: 24px;
            }
            .file-card .file-name {
                font-size: 8px;
                max-width: 60px;
            }
            .status-badge {
                font-size: 10px;
                padding: 2px 8px;
            }
            .letter-card .student-name {
                font-size: 14px;
            }
            .letter-card .student-reg {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="header-title">
                <i class="bi bi-eye"></i> Preview Letters
            </h1>
            <p class="header-subtitle">
                Review <?php echo $total_letters; ?> letter(s) before signing
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Signature Status -->
    <?php if(empty($signature)){ ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <span class="flex-grow-1">You must upload your digital signature before signing letters.</span>
        <a href="upload-signature.php" class="btn btn-primary btn-sm" style="background: var(--primary-blue); color: #fff; border: none; border-radius: 0.375rem; padding: 4px 14px; font-family: 'Hanken Grotesk', sans-serif;">
            <i class="bi bi-upload"></i> Upload Signature
        </a>
    </div>
    <?php } else { ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-2"></i>
        <span class="flex-grow-1">Your digital signature is ready. You can sign the selected letters.</span>
        <div class="signature-preview">
            <img src="../../assets/uploads/signatures/<?php echo $signature; ?>" alt="Your Signature">
        </div>
    </div>
    <?php } ?>

    <!-- Summary -->
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="summary-box">
                        <div class="number primary"><?php echo $total_letters; ?></div>
                        <div class="label">Total Selected</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-box">
                        <div class="number success" id="readyCount"><?php echo $ready_count; ?></div>
                        <div class="label">Ready to Sign</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-box">
                        <div class="number danger" id="missingCount"><?php echo $missing_count; ?></div>
                        <div class="label">File Missing</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Letters List -->
    <div class="card">
        <div class="card-header">
            <span><i class="bi bi-envelope"></i> Letters Preview</span>
            <button type="button" class="btn-sign-all" id="bulkSignBtn" onclick="confirmBulkSign()" <?php echo empty($signature) ? 'disabled' : ''; ?>>
                <i class="bi bi-pencil"></i> Sign All Ready
            </button>
        </div>
        <div class="card-body">
            
            <form id="bulkSignForm" method="POST" action="bulk-sign-letters.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                
                <?php foreach($letters_data as $letter): 
                    $is_ready = $letter['file_exists'];
                    $file_ext = $letter['file_ext'];
                    $file_icon = getFileIcon($letter['original_file']);
                    $is_image = isImageFile($letter['original_file']);
                ?>
                <div class="letter-card <?php echo $is_ready ? 'ready' : 'missing'; ?>">
                    <div class="row align-items-start g-2">
                        <!-- Checkbox -->
                        <div class="col-auto checkbox-col">
                            <?php if($is_ready){ ?>
                                <input type="checkbox" class="letter-checkbox form-check-input" name="letter_ids[]" value="<?php echo $letter['id']; ?>" checked>
                            <?php } else { ?>
                                <input type="checkbox" class="letter-checkbox form-check-input" name="letter_ids[]" value="<?php echo $letter['id']; ?>" disabled>
                            <?php } ?>
                        </div>
                        
                        <!-- Student Info -->
                        <div class="col-md-3">
                            <div class="student-name"><?php echo htmlspecialchars($letter['student_name']); ?></div>
                            <div class="student-reg"><?php echo htmlspecialchars($letter['reg_number']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($letter['department']); ?></div>
                            <div class="text-muted small">Letter Dept: <?php echo htmlspecialchars($letter['department_name']); ?></div>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-2">
                            <div class="mb-1">
                                <?php if($is_ready){ ?>
                                    <span class="status-badge status-badge-ready"><i class="bi bi-check-circle"></i> Ready</span>
                                <?php } else { ?>
                                    <span class="status-badge status-badge-missing"><i class="bi bi-x-circle"></i> File Missing</span>
                                <?php } ?>
                            </div>
                            <div class="requirement-check">
                                <span class="text-muted small">
                                    <i class="bi bi-file-earmark"></i> 
                                    <?php echo strtoupper($file_ext); ?>
                                </span>
                                <br>
                                <span class="text-muted small">
                                    <i class="bi bi-hdd"></i> 
                                    <?php echo $letter['file_size']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- File Gallery -->
                        <div class="col-md-7">
                            <div class="file-gallery">
                                
                                <!-- Letter File -->
                                <div class="file-card <?php echo !$is_ready ? 'border-danger' : ''; ?>" title="<?php echo htmlspecialchars($letter['original_file']); ?>">
                                    <div class="file-thumbnail" onclick="<?php echo $is_ready ? "window.open('../../assets/uploads/department_letters/" . $letter['original_file'] . "', '_blank')" : "return false;"; ?>">
                                        <?php if($is_ready && $is_image): ?>
                                            <img src="../../assets/uploads/department_letters/<?php echo $letter['original_file']; ?>" alt="<?php echo htmlspecialchars($letter['original_file']); ?>">
                                        <?php else: ?>
                                            <i class="bi <?php echo $file_icon; ?> file-icon <?php echo $is_ready ? (strpos($file_icon, 'pdf') !== false ? 'pdf' : (strpos($file_icon, 'word') !== false ? 'word' : (strpos($file_icon, 'image') !== false ? 'image' : ''))) : 'missing'; ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-name" title="<?php echo htmlspecialchars($letter['original_file']); ?>">
                                        <?php echo htmlspecialchars(substr($letter['original_file'], 11)); ?>
                                    </div>
                                    <?php if($is_ready): ?>
                                        <span class="file-badge letter">Letter</span>
                                    <?php else: ?>
                                        <span class="file-badge missing">Missing</span>
                                    <?php endif; ?>
                                    <div class="file-actions">
                                        <?php if($is_ready): ?>
                                            <a href="../../assets/uploads/department_letters/<?php echo $letter['original_file']; ?>" target="_blank" class="btn-view">View</a>
                                        <?php else: ?>
                                            <button class="btn-view" disabled>File Not Found</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Signature Preview -->
                                <div class="file-card" style="flex: 0 0 120px; min-width: 120px; max-width: 120px; border-style: dashed;">
                                    <div class="file-thumbnail" style="height: 80px; background: #f8f9fa; flex-direction: column; gap: 4px;">
                                        <?php if(!empty($signature)): ?>
                                            <img src="../../assets/uploads/signatures/<?php echo $signature; ?>" alt="Signature" style="max-height: 50px; max-width: 80px;">
                                        <?php else: ?>
                                            <i class="bi bi-pencil file-icon" style="font-size: 32px; color: #6b7280;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-name">Signature</div>
                                    <span class="file-badge" style="background: #006c49; color: #fff;">Ready</span>
                                    <div class="file-actions">
                                        <span class="text-success small" style="font-family: 'Hanken Grotesk', sans-serif;">Will be applied</span>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <!-- Comment -->
                            <?php if(!empty($letter['comment'])): ?>
                            <div class="mt-1">
                                <small class="text-muted" style="font-family: 'Hanken Grotesk', sans-serif;">
                                    <i class="bi bi-chat"></i> <?php echo htmlspecialchars($letter['comment']); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
            
            <!-- Legend -->
            <div class="mt-3 pt-3 border-top">
                <div class="legend">
                    <span><span class="badge" style="background: #006c49; color: #fff;">Ready</span> = File exists and can be signed</span>
                    <span><span class="badge" style="background: #ef4444; color: #fff;">Missing</span> = File not found on server</span>
                    <span class="text-muted small" style="font-family: 'Hanken Grotesk', sans-serif;">
                        <i class="bi bi-info-circle"></i> Click on any file thumbnail to preview
                    </span>
                    <span class="text-muted small" style="font-family: 'Hanken Grotesk', sans-serif;">
                        <i class="bi bi-info-circle"></i> Your signature will be applied to all selected letters
                    </span>
                </div>
            </div>
            
        </div>
    </div>

</div>

<script>
function confirmBulkSign() {
    var checkboxes = document.querySelectorAll('.letter-checkbox:checked');
    var count = checkboxes.length;
    
    if(count === 0){
        Swal.fire({
            icon: 'warning',
            title: 'No Letters Ready',
            text: 'No letters are ready for signing. Please check the files.',
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    Swal.fire({
        title: 'Sign Selected Letters?',
        html: 'You are about to sign <strong>' + count + '</strong> letter' + (count !== 1 ? 's' : '') + '.<br><br>This will:<br>• Add your digital signature to each letter<br>• Add the current date<br><br><strong>Note:</strong> This will NOT auto-approve the main clearance.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#006c49',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Sign Selected',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            document.getElementById('bulkSignForm').submit();
        }
    });
}

// Auto-select all ready letters
document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.letter-checkbox');
    checkboxes.forEach(function(cb) {
        if(!cb.disabled){
            cb.checked = true;
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>