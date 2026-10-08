<?php
session_start();

include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';

/* ==============================
   CHECK SESSION FIRST - Redirect if expired
============================== */
if(!isset($_SESSION['office_officer_id']) || !isset($_SESSION['office_role'])){
    header("Location: login.php");
    exit();
}

officerAuth();

/* ==============================
   SECURITY FIX (IMPORTANT)
   BLOCK DEPARTMENT OFFICERS
============================== */
if(!isset($_SESSION['office_role']) || $_SESSION['office_role'] !== 'OFFICE'){
    session_destroy();
    header("Location: login.php");
    exit();
}

/* ==============================
   OFFICE SESSION
============================== */
if(!isset($_SESSION['office_name'])){
    header("Location: login.php");
    exit();
}

$office = strtoupper($_SESSION['office_name']);
$office_officer_name = $_SESSION['office_officer_name'] ?? '';
$office_email = $_SESSION['office_email'] ?? '';

/* ==============================
   VERIFY OFFICE EXISTS
============================== */
$check = mysqli_query($conn,
"SELECT * FROM offices WHERE UPPER(offices_name)='$office' LIMIT 1");

if(!$check || mysqli_num_rows($check) == 0){
    die("Office not registered in system");
}

$office_data = mysqli_fetch_assoc($check);

/* ==============================
   GET OFFICER SIGNATURE
============================== */
$sig_q = mysqli_query($conn,"
    SELECT digital_signature, fullname, email
    FROM officers
    WHERE id='{$_SESSION['office_officer_id']}'
    LIMIT 1
");

$sig_data = mysqli_fetch_assoc($sig_q);
$signature = $sig_data['digital_signature'] ?? '';
$officer_name = $sig_data['fullname'] ?? $office_officer_name;
$officer_email = $sig_data['email'] ?? $office_email;

/* ==============================
   FETCH STUDENTS + CLEARANCE - FIXED for ONLY_FULL_GROUP_BY
============================== */
$query = mysqli_query($conn,"
SELECT 
    s.id,
    s.fullname,
    s.reg_number,
    s.faculty_name,
    s.department,
    cs.status,
    cs.document_file,
    cs.requirement_files,
    cs.comment,
    cs.approved_at
FROM students s
INNER JOIN (
    SELECT student_id, status, document_file, requirement_files, comment, approved_at,
           ROW_NUMBER() OVER (PARTITION BY student_id ORDER BY id DESC) as rn
    FROM clearance_status
    WHERE UPPER(department_role)=UPPER('$office')
) cs ON s.id = cs.student_id AND cs.rn = 1
ORDER BY s.id DESC
");

$total_records = mysqli_num_rows($query);

// ============================================================
// FOR HALL OFFICE ONLY: Get students with all offices approved
// AND signed department letter
// ============================================================
$hall_cleared_students = [];
if(strtoupper($office) == 'HALL'){
    // Get students who have been fully cleared AND have signed their department letter
    $cleared_query = mysqli_query($conn,"
    SELECT DISTINCT 
        s.id, 
        s.fullname, 
        s.reg_number, 
        s.faculty_name, 
        s.department,
        dl.signed_file,
        dl.signed_at,
        dl.officer_name
    FROM students s
    INNER JOIN clearance_status cs ON s.id = cs.student_id
    LEFT JOIN department_letters dl ON s.id = dl.student_id
    WHERE LOWER(s.status) = 'cleared'
    AND cs.department_role = 'HALL'
    AND cs.status = 'approved'
    AND dl.status = 'signed'
    AND dl.signed_file IS NOT NULL
    AND dl.signed_at IS NOT NULL
    AND dl.signature_file IS NOT NULL
    ORDER BY s.id DESC
    ");
    
    while($row = mysqli_fetch_assoc($cleared_query)){
        $hall_cleared_students[] = $row;
    }
}

// Store alert message in a variable before clearing session
$alert_message = isset($_SESSION['alert_message']) ? $_SESSION['alert_message'] : null;
$alert_type = isset($_SESSION['alert_type']) ? $_SESSION['alert_type'] : null;

// Clear session variables immediately after storing
if(isset($_SESSION['alert_message'])){
    unset($_SESSION['alert_message']);
}
if(isset($_SESSION['alert_type'])){
    unset($_SESSION['alert_type']);
}

// Get CSRF token function
function csrf_token() {
    if(!isset($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($office); ?> Office Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- ✅ SWEETALERT2 - Always loaded for bulk approval -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- ✅ jQuery - For AJAX real-time updates -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <style>
        /* Matching design styles - Updated to match image design */
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
        
        /* Header - Updated to match image */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .header-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0;
            letter-spacing: -0.02em;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .header-title .office-name {
            color: var(--primary-blue);
        }
        
        .header-title i {
            color: var(--primary-blue);
            margin-right: 10px;
        }
        
        .header-title .badge-count {
            background: var(--primary-blue);
            color: #ffffff;
            font-size: 14px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-left: 8px;
            font-weight: 600;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .header-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin: 0;
            font-weight: 400;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .header-actions .btn {
            font-size: 13px;
            padding: 8px 20px;
            border-radius: 0.5rem;
            font-weight: 500;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .header-actions .btn i {
            font-size: 15px;
        }
        
        .header-actions .btn:hover {
            transform: scale(0.95);
        }
        
        .btn-primary-custom {
            background: var(--primary-blue);
            color: #ffffff;
            border: none;
        }
        
        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: #ffffff;
        }
        
        .btn-success-custom {
            background: #006c49;
            color: #ffffff;
            border: none;
        }
        
        .btn-success-custom:hover {
            background: #005236;
            color: #ffffff;
        }
        
        .btn-danger-custom {
            background: #dc2626;
            color: #ffffff;
            border: none;
        }
        
        .btn-danger-custom:hover {
            background: #b91c1c;
            color: #ffffff;
        }
        
        /* Card */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            background: #ffffff;
            margin-bottom: 24px;
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 20px;
            font-weight: 600;
            font-family: 'Hanken Grotesk', sans-serif;
            border-radius: 1rem 1rem 0 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 0.5rem;
            padding: 12px 16px;
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .alert-warning-custom {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .alert-warning-custom i {
            font-size: 20px;
            color: #f59e0b;
        }
        
        .alert-warning-custom .btn {
            margin-left: auto;
            flex-shrink: 0;
        }
        
        /* Table - Updated to match image design */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            margin-bottom: 0;
            width: 100%;
            font-family: 'Hanken Grotesk', sans-serif;
            border-collapse: collapse;
        }
        
        .table thead th {
            background: #111827;
            color: white;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border: none;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            font-family: 'Hanken Grotesk', sans-serif;
            text-align: left;
        }
        
        .table thead th:first-child { 
            border-radius: 0; 
            text-align: center;
        }
        .table thead th:last-child { border-radius: 0; }
        
        .table tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            font-family: 'Hanken Grotesk', sans-serif;
            text-align: left;
        }
        
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover { background: #f8f9ff; }
        
        /* Status Badge - Updated to match image */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.5;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .status-badge i {
            font-size: 14px;
        }
        .status-badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-badge-pending i {
            color: #f59e0b;
        }
        .status-badge-approved {
            background: #d1fae5;
            color: #065f46;
        }
        .status-badge-approved i {
            color: #10b981;
        }
        .status-badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-badge-rejected i {
            color: #ef4444;
        }
        
        /* Document badges - Updated to match image */
        .badge-document {
            background: #e8f0fe;
            color: var(--primary-blue);
            font-weight: 500;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            text-decoration: none;
            font-family: 'Hanken Grotesk', sans-serif;
            transition: all 0.15s ease;
        }
        
        .badge-document:hover {
            background: #dbe1ff;
            color: var(--primary-dark);
            text-decoration: none;
        }
        
        .badge-document i {
            font-size: 12px;
            margin-right: 4px;
        }
        
        /* Document files list - Updated to match image */
        .doc-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .doc-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }
        
        .doc-item .file-icon {
            color: var(--primary-blue);
        }
        
        .file-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 12px;
            font-family: 'Hanken Grotesk', sans-serif;
            word-break: break-all;
        }
        
        .file-link:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }
        
        .no-files-text {
            color: #9ca3af;
            font-size: 12px;
            font-style: italic;
        }
        
        /* Buttons - Updated to match image */
        .btn-review {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 5px 18px;
            font-size: 13px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        
        .btn-review:hover {
            background: var(--primary-dark);
            color: #ffffff;
            transform: scale(0.95);
            text-decoration: none;
        }
        
        .btn-review i {
            font-size: 13px;
        }
        
        .btn-review-disabled {
            background: #f3f4f6;
            color: #6b7280;
            padding: 5px 18px;
            font-size: 13px;
            border-radius: 0.375rem;
            border: none;
            cursor: not-allowed;
            font-family: 'Hanken Grotesk', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-review-disabled i {
            font-size: 13px;
        }
        
        .btn-bulk {
            background: var(--primary-blue);
            color: #ffffff;
            padding: 5px 16px;
            font-size: 13px;
            border-radius: 0.375rem;
            border: none;
            font-weight: 500;
            transition: all 0.15s ease;
            font-family: 'Hanken Grotesk', sans-serif;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-bulk:hover {
            background: var(--primary-dark);
            color: #ffffff;
            transform: scale(0.95);
        }
        
        .btn-bulk i {
            font-size: 14px;
        }
        
        /* Bulk preview section - Updated to match image */
        .bulk-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .bulk-section .bulk-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bulk-section .bulk-left label {
            margin: 0;
            font-size: 14px;
            color: var(--text-muted);
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .bulk-section .bulk-left label strong {
            color: var(--text-dark);
        }
        
        .bulk-section .bulk-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bulk-section .selected-count {
            color: var(--text-muted);
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* Table footer - Updated to match image */
        .table-footer {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 0 0 1rem 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 14px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .table-footer strong {
            color: var(--text-dark);
        }
        
        /* Hall Office Specific Styles */
        .hall-cleared-card {
            background: #f0f7ff;
            border: 1px solid #cce5ff;
            border-radius: 0.5rem;
            padding: 12px 16px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        .hall-cleared-card:hover {
            background: #e3f0ff;
            border-color: #99caff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .hall-cleared-card .student-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .hall-cleared-card .student-reg {
            color: var(--text-muted);
            font-size: 13px;
        }
        .hall-cleared-card .student-detail {
            color: var(--text-muted);
            font-size: 12px;
            display: block;
        }
        .hall-cleared-card .signed-badge {
            background: #d1fae5;
            color: #065f46;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-block;
            margin-top: 2px;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .hall-cleared-card .signed-badge i {
            font-size: 10px;
        }
        
        /* Download buttons */
        .btn-download-slip {
            background: #006c49;
            color: #fff;
            padding: 5px 14px;
            border-radius: 0.375rem;
            border: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .btn-download-slip:hover {
            background: #005236;
            color: #fff;
            transform: scale(1.05);
            text-decoration: none;
        }
        .btn-download-slip:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-download-slip i {
            font-size: 14px;
        }
        
        .btn-download-all {
            background: #006c49;
            color: #fff;
            padding: 6px 16px;
            border-radius: 0.375rem;
            border: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .btn-download-all:hover {
            background: #005236;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-download-all i {
            font-size: 16px;
        }
        
        .btn-print-all {
            background: #0ea5e9;
            color: #fff;
            padding: 6px 16px;
            border-radius: 0.375rem;
            border: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        .btn-print-all:hover {
            background: #0284c7;
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-print-all i {
            font-size: 16px;
        }
        
        /* Checkbox styling */
        .checkbox-col {
            width: 40px;
            text-align: center !important;
        }
        .id-col {
            width: 60px;
        }
        .status-col {
            width: 130px;
        }
        .action-col {
            width: 160px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* Empty state */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
            color: #d1d5db;
        }
        
        .empty-state p {
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        /* Office Info Card - NEW */
        .office-info-card {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
        }
        
        .office-info-card h5 {
            font-weight: 600;
            color: var(--text-dark);
            font-family: 'Hanken Grotesk', sans-serif;
            margin-bottom: 16px;
        }
        
        .office-info-card .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            font-family: 'Hanken Grotesk', sans-serif;
        }
        
        .office-info-card .info-item:last-child {
            border-bottom: none;
        }
        
        .office-info-card .info-label {
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .office-info-card .info-value {
            color: var(--text-dark);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .container { padding: 20px 16px; }
            .header-title { font-size: 24px; }
            .table thead th, .table tbody td { padding: 10px 12px; font-size: 12px; }
        }
        
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .page-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .header-title { font-size: 20px; }
            .header-actions { justify-content: flex-start; }
            .header-actions .btn { font-size: 12px; padding: 6px 14px; }
            .table thead th, .table tbody td { padding: 8px 10px; font-size: 11px; }
            .status-badge { font-size: 11px; padding: 4px 10px; }
            .bulk-section { flex-direction: column; align-items: stretch; gap: 8px; }
            .bulk-section .bulk-right { justify-content: flex-start; }
            .hall-cleared-card { padding: 10px 12px; }
            .table-footer { flex-direction: column; text-align: center; gap: 4px; }
            .office-info-card { padding: 16px; }
        }
        
        @media (max-width: 576px) {
            .container { padding: 12px; }
            .header-title { font-size: 18px; }
            .header-title .badge-count { font-size: 11px; padding: 2px 8px; }
            .header-subtitle { font-size: 12px; }
            .header-actions .btn { font-size: 11px; padding: 5px 10px; }
            .header-actions .btn i { font-size: 12px; }
            .table thead th, .table tbody td { padding: 6px 8px; font-size: 10px; }
            .table thead th { font-size: 9px; }
            .status-badge { font-size: 10px; padding: 3px 8px; }
            .status-badge i { font-size: 10px; }
            .btn-review, .btn-review-disabled { font-size: 10px; padding: 4px 10px; }
            .btn-bulk { font-size: 10px; padding: 4px 10px; }
            .badge-document { font-size: 9px; padding: 2px 8px; }
            .file-link { font-size: 9px; }
            .bulk-section .bulk-left label { font-size: 12px; }
            .bulk-section .selected-count { font-size: 12px; }
            .table-footer { font-size: 12px; }
            .checkbox-col { width: 30px; }
            .id-col { width: 40px; }
            .status-col { width: 90px; }
            .action-col { width: 100px; }
            .office-info-card { padding: 12px; }
            .office-info-card .info-item { font-size: 13px; }
        }
        
        @media (max-width: 400px) {
            .container { padding: 8px; }
            .header-title { font-size: 16px; }
            .header-title .badge-count { font-size: 10px; padding: 2px 6px; }
            .table thead th, .table tbody td { padding: 4px 6px; font-size: 9px; }
            .table thead th { font-size: 8px; padding: 4px 6px; }
            .status-badge { font-size: 9px; padding: 2px 6px; }
            .btn-review, .btn-review-disabled { font-size: 9px; padding: 3px 8px; }
            .btn-bulk { font-size: 9px; padding: 3px 8px; }
            .bulk-section { padding: 8px 12px; }
            .bulk-section .bulk-left label { font-size: 11px; }
            .bulk-section .selected-count { font-size: 11px; }
            .doc-item { font-size: 9px; }
            .office-info-card .info-item { font-size: 12px; }
        }
    </style>
</head>

<body>

<!-- SUCCESS/ERROR MESSAGE - FIXED WITH INLINE FUNCTION -->
<?php if($alert_message && $alert_type){ ?> 
<script>
    function customAlert(message, type){
        let iconType = 'success';
        let title = 'Success';
        let buttonColor = '#1e40af';
        
        if(type === 'error' || type === 'rejected'){
            iconType = 'error';
            title = 'Rejected';
            buttonColor = '#dc2626';
        } else if(type === 'approved'){
            iconType = 'success';
            title = 'Approved';
            buttonColor = '#006c49';
        } else if(type === 'info'){
            iconType = 'info';
            title = 'Information';
            buttonColor = '#0ea5e9';
        }
        
        Swal.fire({
            icon: iconType,
            title: title,
            text: message,
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: buttonColor
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        customAlert(
            '<?php echo addslashes($alert_message); ?>', 
            '<?php echo $alert_type; ?>'
        );
    });
</script>
<?php } ?>

<div class="container">

<!-- HEADER - Updated to match image design -->
<div class="page-header">
    <div>
        <h1 class="header-title">
            <i class="bi bi-building"></i> <?php echo htmlspecialchars($office); ?> OFFICE
            <span class="badge-count"><?php echo $total_records; ?> Students</span>
        </h1>
        <p class="header-subtitle">DASHBOARD</p>
    </div>
    <div class="header-actions">
        <a href="profile.php" class="btn btn-primary-custom">
            <i class="bi bi-upload"></i> Upload Signature
        </a>
        <a href="reports.php" class="btn btn-success-custom">
            <i class="bi bi-file-earmark-text"></i> Reports
        </a>
        <a href="logout.php" class="btn btn-danger-custom">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- SIGNATURE WARNING -->
<?php if(empty($signature)){ ?>
<div class="alert alert-warning-custom">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span class="flex-grow-1">You have not uploaded your digital signature yet. You cannot approve student requests until you upload it.</span>
    <a href="profile.php" class="btn btn-primary-custom btn-sm">
        <i class="bi bi-upload"></i> Upload Signature
    </a>
</div>
<?php } ?>

<!-- ============================================================
   HALL OFFICE ONLY: CLEARED STUDENTS WITH DOWNLOAD BUTTON
   - Only shows when Hall officer has signature uploaded
   - Only shows students who are fully cleared AND have signed department letter
============================================================ -->
<?php if(strtoupper($office) == 'HALL'): ?>
    <?php if(empty($signature)): ?>
    <div class="card border border-warning">
        <div class="card-header bg-warning text-dark">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Signature Required</strong>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle text-warning me-3" style="font-size:24px;"></i>
                <div>
                    <p class="mb-1">You need to upload your digital signature before you can generate clearance slips for students.</p>
                    <a href="profile.php" class="btn btn-primary-custom btn-sm">
                        <i class="bi bi-upload"></i> Upload Signature Now
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php elseif(!empty($hall_cleared_students)): ?>
    <div class="card border border-success">
        <div class="card-header" style="background: #006c49; color: #ffffff; border-bottom: none;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    <i class="bi bi-check-circle-fill me-2"></i> 
                    Cleared Students - Ready for Hall Clearance
                    <span class="badge" style="background: rgba(255,255,255,0.2); color: #ffffff; margin-left: 8px;"><?php echo count($hall_cleared_students); ?> Students</span>
                </span>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn-download-all" onclick="downloadAllSlips()">
                        <i class="bi bi-download"></i> Download All Slips
                    </button>
                    <button class="btn-print-all" onclick="printAllSlips()">
                        <i class="bi bi-printer"></i> Print All Slips
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach($hall_cleared_students as $student): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="hall-cleared-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex:1; min-width:0;">
                                <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                                <div class="student-reg"><?php echo htmlspecialchars($student['reg_number']); ?></div>
                                <div class="student-detail"><?php echo htmlspecialchars($student['faculty_name']); ?></div>
                                <div class="student-detail"><?php echo htmlspecialchars($student['department']); ?></div>
                                <?php if(!empty($student['signed_at'])): ?>
                                <span class="signed-badge">
                                    <i class="bi bi-check-circle"></i> Signed: <?php echo date('M d, Y', strtotime($student['signed_at'])); ?>
                                </span>
                                <?php endif; ?>
                                <?php if(!empty($student['officer_name'])): ?>
                                <div class="text-muted small mt-1" style="font-size:10px;">
                                    <i class="bi bi-person-check"></i> Signed by: <?php echo htmlspecialchars($student['officer_name']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="../student/print-slip.php?student_id=<?php echo $student['id']; ?>" 
                               target="_blank" 
                               class="btn-download-slip"
                               title="Generate Clearance Slip">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 text-muted small">
                <i class="bi bi-info-circle"></i> 
                These students have been fully cleared by all offices and have signed their department letter. 
                Click the <strong>Print</strong> button to generate their official clearance slip.
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card border border-warning">
        <div class="card-header" style="background: #fef3c7; color: #92400e; border-bottom: none;">
            <i class="bi bi-clock-history me-2"></i> 
            No Cleared Students
        </div>
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-inbox" style="font-size:48px;display:block;margin-bottom:12px;opacity:0.3;"></i>
            <p class="mb-2">No students have been fully cleared and signed their documents yet.</p>
            <div class="small">Students will appear here once they are fully cleared and have signed their department letter.</div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- TABLE CARD -->
<div class="card">

    <!-- BULK PREVIEW & APPROVAL SECTION - Updated to match image -->
    <?php if(!empty($signature)){ ?>
    <div class="bulk-section">
        <div class="bulk-left">
            <input type="checkbox" id="selectAllPending" class="form-check-input" onclick="toggleSelectAll(this)">
            <label for="selectAllPending" class="mb-0">
                <strong>Select All Pending</strong>
            </label>
        </div>
        <div class="bulk-right">
            <span class="selected-count" id="selectedCount">0 student(s) selected</span>
            <button type="button" class="btn-bulk" onclick="confirmBulkPreview()">
                <i class="bi bi-eye"></i> Preview & Approve
            </button>
        </div>
    </div>
    <?php } else { ?>
    <div class="alert alert-warning" style="margin: 12px 16px; border-left: 4px solid #f59e0b;">
        <i class="bi bi-info-circle me-2"></i> 
        <strong>Bulk approval requires a digital signature.</strong> 
        Please upload your signature first.
    </div>
    <?php } ?>

    <div class="table-responsive-custom">
        <form id="bulkApproveForm" method="POST" action="bulk-preview.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <table class="table">
                <thead>
                    <tr>
                        <th class="checkbox-col">
                            <input type="checkbox" id="selectAllPendingTable" class="form-check-input" onclick="toggleSelectAll(this)">
                        </th>
                        <th class="id-col">ID</th>
                        <th>NAME</th>
                        <th>REG NO</th>
                        <th class="status-col">STATUS</th>
                        <th>DOCUMENTS</th>
                        <th class="action-col">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($query && mysqli_num_rows($query) > 0){ ?>
                        <?php while($row = mysqli_fetch_assoc($query)){ 
                            $status = strtolower($row['status']);
                            
                            if($status == 'approved'){
                                $status_class = 'status-badge-approved';
                                $status_icon = 'bi-check-circle-fill';
                                $status_text = 'Approved';
                            }elseif($status == 'rejected'){
                                $status_class = 'status-badge-rejected';
                                $status_icon = 'bi-x-circle-fill';
                                $status_text = 'Rejected';
                            }else{
                                $status_class = 'status-badge-pending';
                                $status_icon = 'bi-clock-fill';
                                $status_text = 'Pending';
                            }
                        ?>
                        <tr>
                            <td class="text-center">
                                <?php if($status == 'pending'){ ?>
                                <input type="checkbox" class="student-checkbox pending-checkbox form-check-input" name="student_ids[]" value="<?php echo $row['id']; ?>" onclick="updateSelectionCount()">
                                <?php } ?>
                            </td>
                            <td><strong><?php echo $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>" data-student-id="<?php echo $row['id']; ?>">
                                    <i class="bi <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="doc-list">
                                    <?php
                                    if(!empty($row['document_file'])){
                                        echo "<div class='doc-item'>";
                                        echo "<span class='file-icon'><i class='bi bi-file-earmark-pdf-fill'></i></span>";
                                        echo "<span class='badge-document'>Main File</span>";
                                        echo "</div>";
                                    }

                                    $files = [];
                                    if(!empty($row['requirement_files'])){
                                        $decoded = json_decode($row['requirement_files'], true);
                                        if(is_array($decoded)){
                                            $files = $decoded;
                                        }
                                    }

                                    if(count($files) > 0){
                                        foreach($files as $f){
                                            echo "<div class='doc-item'>";
                                            echo "<span class='file-icon'><i class='bi bi-paperclip'></i></span>";
                                            echo "<a href='../assets/uploads/requirements/$f' target='_blank' class='file-link'>" . htmlspecialchars(substr($f, 11)) . "</a>";
                                            echo "</div>";
                                        }
                                    }

                                    if(empty($files) && empty($row['document_file'])){
                                        echo "<span class='no-files-text'>No files</span>";
                                    }

                                    if(!empty($row['comment'])){
                                        echo "<div class='doc-item mt-1'><small class='text-muted'><i class='bi bi-chat'></i> " . htmlspecialchars($row['comment']) . "</small></div>";
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php if(!empty($signature)){ ?>
                                    <a href="review-request.php?id=<?php echo $row['id']; ?>" class="btn-review">
                                        <i class="bi bi-eye"></i> Review
                                    </a>
                                <?php } else { ?>
                                    <button class="btn-review-disabled" disabled>
                                        <i class="bi bi-upload"></i> Upload Signature First
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <p class="mb-0">No clearance requests found for this office</p>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </form>
    </div>

    <!-- TABLE FOOTER - Updated to match image -->
    <div class="table-footer">
        <span>
            Showing <strong><?php echo ($total_records > 0) ? '1' : '0'; ?></strong> 
            to <strong><?php echo $total_records; ?></strong> 
            of <strong><?php echo $total_records; ?></strong> entries
        </span>
        <span>
            <i class="bi bi-people"></i> Total Students: <?php echo $total_records; ?>
        </span>
    </div>

</div>

<!-- OFFICE INFO CARD - NEWLY ADDED -->
<div class="card shadow-sm">
    <div class="card-body office-info-card">
        <h5><i class="bi bi-info-circle"></i> Office Information</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-building"></i> Office:</span>
                    <span class="info-value ms-2"><?php echo htmlspecialchars($office); ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-person"></i> Officer:</span>
                    <span class="info-value ms-2"><?php echo htmlspecialchars($officer_name); ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-item">
                    <span class="info-label"><i class="bi bi-envelope"></i> Email:</span>
                    <span class="info-value ms-2"><?php echo htmlspecialchars($officer_email); ?></span>
                </div>
            </div>
        </div>
        <?php if(!empty($signature)){ ?>
            <div class="alert alert-success mt-3 mb-0" style="border-left:4px solid #28a745;">
                <i class="bi bi-check-circle"></i> Digital signature uploaded successfully
            </div>
        <?php } ?>
    </div>
</div>

</div>

<!-- Hidden form for downloading all slips -->
<form id="downloadAllForm" method="POST" action="download-all-slips.php" target="_blank">
    <input type="hidden" name="student_ids" id="downloadStudentIds" value="">
</form>

<script src="../assets/js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>

<script>
function toggleSelectAll(checkbox) {
    var checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
    updateSelectionCount();
}

function updateSelectionCount() {
    var checkboxes = document.querySelectorAll('.student-checkbox:checked');
    var count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count + ' student' + (count !== 1 ? 's' : '') + ' selected';
}

// ============================================================
// DOWNLOAD ALL SLIPS AS ZIP FILE (NEW FUNCTION)
// ============================================================
function downloadAllSlips() {
    <?php if(strtoupper($office) == 'HALL' && !empty($hall_cleared_students)): ?>
    var students = <?php echo json_encode($hall_cleared_students); ?>;
    var count = students.length;
    
    Swal.fire({
        title: 'Download All Clearance Slips?',
        html: 'You are about to download <strong>' + count + '</strong> clearance slip(s) as a ZIP file.<br><br>This may take a moment depending on the number of students.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#006c49',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Download All',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Generating ZIP File...',
                html: 'Please wait while we generate all clearance slips.<br><div class="spinner-border text-success mt-3" role="status"></div>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            
            // Get all student IDs
            var studentIds = students.map(function(s) { return s.id; });
            
            // Submit the form to generate ZIP
            var form = document.getElementById('downloadAllForm');
            document.getElementById('downloadStudentIds').value = JSON.stringify(studentIds);
            form.submit();
            
            // Close loading after a moment
            setTimeout(function() {
                Swal.close();
            }, 2000);
        }
    });
    <?php else: ?>
    Swal.fire({
        icon: 'info',
        title: 'No Students Available',
        text: 'There are no cleared students with signed documents to download.',
        confirmButtonColor: '#0ea5e9'
    });
    <?php endif; ?>
}

// ============================================================
// PRINT ALL SLIPS FUNCTION (Opens in new tabs)
// ============================================================
function printAllSlips() {
    <?php if(strtoupper($office) == 'HALL' && !empty($hall_cleared_students)): ?>
    var students = <?php echo json_encode($hall_cleared_students); ?>;
    var count = students.length;
    
    Swal.fire({
        title: 'Print All Clearance Slips?',
        html: 'You are about to open <strong>' + count + '</strong> clearance slip(s) for printing.<br><br>Please ensure your browser allows pop-ups for this site.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Print All',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            var opened = 0;
            var total = students.length;
            
            students.forEach(function(student, index) {
                setTimeout(function() {
                    var win = window.open('../student/print-slip.php?student_id=' + student.id, '_blank');
                    if(win) {
                        opened++;
                    }
                    
                    // If this is the last one, show summary
                    if(index === total - 1) {
                        setTimeout(function() {
                            var message = opened + ' out of ' + total + ' clearance slip(s) opened.';
                            if(opened < total) {
                                message += ' <br><small class="text-warning">Some windows may have been blocked. Please check your pop-up blocker.</small>';
                            }
                            Swal.fire({
                                icon: opened > 0 ? 'success' : 'warning',
                                title: opened > 0 ? 'Printing Started' : 'Pop-ups Blocked',
                                html: message,
                                timer: 5000,
                                timerProgressBar: true,
                                showConfirmButton: true,
                                confirmButtonColor: '#0ea5e9'
                            });
                        }, 1000);
                    }
                }, index * 600);
            });
        }
    });
    <?php else: ?>
    Swal.fire({
        icon: 'info',
        title: 'No Students Available',
        text: 'There are no cleared students with signed documents to print.',
        confirmButtonColor: '#0ea5e9'
    });
    <?php endif; ?>
}

// ============================================================
// BULK PREVIEW - NEW FUNCTION
// ============================================================
function confirmBulkPreview() {
    var checkboxes = document.querySelectorAll('.student-checkbox:checked');
    var count = checkboxes.length;
    
    console.log('Bulk preview clicked. Selected:', count);
    
    if(count === 0){
        Swal.fire({
            icon: 'warning',
            title: 'No Students Selected',
            text: 'Please select at least one pending student to preview.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }
    
    Swal.fire({
        title: 'Preview Selected Students?',
        html: 'You are about to preview <strong>' + count + '</strong> student' + (count !== 1 ? 's' : '') + '.<br><br>You will be able to review all documents before approving.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e40af',
        cancelButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Preview',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if(result.isConfirmed) {
            // Set form action to bulk-preview.php and submit
            var form = document.getElementById('bulkApproveForm');
            form.action = 'bulk-preview.php';
            form.submit();
        }
    });
}

// ============================================================
// ✅ REAL-TIME STATUS UPDATES - Auto-refresh every 10 seconds
// ============================================================
setInterval(function() {
    $.ajax({
        url: '../get-status-updates.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            // Update student status badges
            $('.status-badge').each(function() {
                var studentId = $(this).data('student-id');
                if(data[studentId]) {
                    var status = data[studentId];
                    var badge = $(this);
                    
                    // Remove all status classes
                    badge.removeClass('status-badge-pending status-badge-approved status-badge-rejected');
                    
                    // Add the new status class
                    badge.addClass('status-badge-' + status);
                    
                    // Update the icon and text
                    var icon = status === 'approved' ? 'bi-check-circle-fill' : 
                               status === 'rejected' ? 'bi-x-circle-fill' : 'bi-clock-fill';
                    var text = status.charAt(0).toUpperCase() + status.slice(1);
                    
                    badge.html('<i class="bi ' + icon + '"></i> ' + text);
                }
            });
        },
        error: function(xhr, status, error) {
            console.log('Status update error:', error);
        }
    });
}, 10000); // Refresh every 10 seconds
</script>
</body>
</html>