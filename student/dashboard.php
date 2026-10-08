<?php

session_start();

include_once '../includes/config.php';
include_once '../includes/session.php';
include_once '../includes/auth.php';

studentAuth();

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* =========================================================
   STUDENT DATA
========================================================= */
$student_query = mysqli_query(
    $conn,
    "SELECT * FROM students WHERE id='$student_id'"
);

$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$status = $student['status'];

/* =========================================================
   TOTAL OFFICES
========================================================= */
$offices_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM offices
     WHERE status='active'"
);

$offices = mysqli_fetch_assoc($offices_q);
$total = $offices['total'] ?? 0;

/* =========================================================
   APPROVED
========================================================= */
$approved_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM clearance_status
     WHERE student_id='$student_id'
     AND status='approved'"
);

$approved_data = mysqli_fetch_assoc($approved_q);
$approved = $approved_data['total'] ?? 0;

/* =========================================================
   REJECTED
========================================================= */
$rejected_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM clearance_status
     WHERE student_id='$student_id'
     AND status='rejected'"
);

$rejected_data = mysqli_fetch_assoc($rejected_q);
$rejected = $rejected_data['total'] ?? 0;

/* =========================================================
   PENDING
========================================================= */
$pending_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM clearance_status
     WHERE student_id='$student_id'
     AND status='pending'"
);

$pending_data = mysqli_fetch_assoc($pending_q);
$pending = $pending_data['total'] ?? 0;

/* =========================================================
   PROGRESS
========================================================= */
$progress = ($total > 0)
    ? round(($approved / $total) * 100)
    : 0;

/* =========================================================
   NOTIFICATIONS
========================================================= */
$notif_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id='$student_id'
     AND status='unread'"
);

$notif_data = mysqli_fetch_assoc($notif_q);
$notif_count = $notif_data['total'] ?? 0;

/* =========================================================
   QR CODE
========================================================= */
$qr_file = "../assets/qrcodes/student_" . $student_id . ".png";
$qr_exists = file_exists($qr_file);

if (strtolower($status) == 'cleared' && !$qr_exists) {

    if (function_exists('generateStudentQR')) {
        generateStudentQR($student_id);
    }

    $qr_exists = file_exists($qr_file);
}

/* =========================================================
   SIGNED DEPARTMENT LETTER
========================================================= */
$letter_q = mysqli_query(
    $conn,
    "SELECT *
     FROM department_letters
     WHERE student_id='$student_id'
     AND status='signed'
     AND signed_file IS NOT NULL
     AND signed_at IS NOT NULL
     ORDER BY id DESC
     LIMIT 1"
);

$signed_letter = mysqli_fetch_assoc($letter_q);

$has_signed_letter = (
    $signed_letter &&
    !empty($signed_letter['signed_file']) &&
    !empty($signed_letter['signed_at'])
);

/* =========================================================
   CHECK MAIN CLEARANCE FILE
========================================================= */
$main_file_q = mysqli_query(
    $conn,
    "SELECT *
     FROM clearance_uploads
     WHERE student_id='$student_id'
     LIMIT 1"
);

$has_main_file = mysqli_num_rows($main_file_q) > 0;

/* =========================================================
   CHECK CLEARANCE STATUS RECORDS
========================================================= */
$status_q = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM clearance_status
     WHERE student_id='$student_id'"
);

$status_data = mysqli_fetch_assoc($status_q);

$has_status_records = ($status_data['total'] ?? 0) > 0;

/* =========================================================
   CLEARANCE FORM
========================================================= */
$clearance_form_alternatives = [
    "clearance-template.pdf",
    "../assets/clearance-form.pdf",
    "../assets/clearance-form.docx",
    "../assets/clearance_form.pdf",
    "../assets/Clearance-Form.pdf",
    "../assets/clearance_template.pdf"
];

$found_form = false;
$form_path = "";

foreach ($clearance_form_alternatives as $alt_path) {

    if (file_exists($alt_path)) {
        $found_form = true;
        $form_path = $alt_path;
        break;
    }
}

/* =========================================================
   GET STARTED MESSAGE - ONCE PER DAY
========================================================= */
$show_get_started = false;

$get_started_key = 'get_started_' . $student_id;

if (!$has_status_records && $found_form) {

    $last_shown = $_SESSION[$get_started_key] ?? null;

    $today = date('Y-m-d');

    if ($last_shown !== $today) {

        $show_get_started = true;

        $_SESSION[$get_started_key] = $today;
    }
}

/* =========================================================
   SUBMIT SIGNED DOCUMENTS CHECKS
========================================================= */

/* Department letter must be signed */
$letter_q_check = mysqli_query(
    $conn,
    "
    SELECT id, signed_file, signed_at, signature_file
    FROM department_letters
    WHERE student_id='$student_id'
    AND status='signed'
    AND signed_file IS NOT NULL
    AND signed_at IS NOT NULL
    AND signature_file IS NOT NULL
    ORDER BY id DESC
    LIMIT 1
    "
);

$letter_check = mysqli_fetch_assoc($letter_q_check);

$has_signed_letter_check = (
    $letter_check &&
    !empty($letter_check['signed_file']) &&
    !empty($letter_check['signed_at']) &&
    !empty($letter_check['signature_file'])
);

/* =========================================================
   CHECK ALL OFFICES APPROVED
========================================================= */
$check_all_approved = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM clearance_status
    WHERE student_id='$student_id'
    AND status='approved'
    "
);

$approved_data_check = mysqli_fetch_assoc($check_all_approved);

$approved_count_check = $approved_data_check['total'] ?? 0;

/* =========================================================
   TOTAL ACTIVE OFFICES
========================================================= */
$total_q = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM offices
    WHERE status='active'
    "
);

$total_data_check = mysqli_fetch_assoc($total_q);

$total_offices_check = $total_data_check['total'] ?? 0;

/* =========================================================
   ALL OFFICES APPROVED
========================================================= */
$all_offices_approved = (
    $approved_count_check >= $total_offices_check &&
    $total_offices_check > 0
);

/* =========================================================
   STUDENT FULLY CLEARED
========================================================= */
$is_fully_cleared = (
    strtolower($student['status']) == 'cleared'
);

/* =========================================================
   CAN SUBMIT SIGNED DOCUMENTS
========================================================= */
$can_submit_check = (
    $has_signed_letter_check &&
    $all_offices_approved &&
    $is_fully_cleared
);

/* =========================================================
   CHECK SENATE SUBMISSION
========================================================= */
$submission_q_check = mysqli_query(
    $conn,
    "
    SELECT status, submitted_at, id
    FROM senate_submissions
    WHERE student_id='$student_id'
    ORDER BY id DESC
    LIMIT 1
    "
);

$submission_check = mysqli_fetch_assoc($submission_q_check);

$is_submitted = (
    $submission_check &&
    $submission_check['status'] != 'returned'
);

$is_returned = (
    $submission_check &&
    $submission_check['status'] == 'returned'
);

$submission_status = $submission_check['status'] ?? null;

$submission_id = $submission_check['id'] ?? null;

/* =========================================================
   ACCESS TO SUBMIT PAGE
========================================================= */
$can_access_submit = (
    $can_submit_check ||
    $is_submitted ||
    $is_returned
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="utf-8">

<meta
    content="width=device-width, initial-scale=1.0"
    name="viewport"
>

<title>Student Clearance Dashboard</title>

<!-- =====================================================
     BOOTSTRAP 5.3
===================================================== -->
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<!-- =====================================================
     BOOTSTRAP ICONS
===================================================== -->
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>

<!-- =====================================================
     MATERIAL SYMBOLS
===================================================== -->
<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&display=swap"
    rel="stylesheet"
>

<!-- =====================================================
     HANKEN GROTESK FONT - MATCHING INDEX.PHP
===================================================== -->
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

/* =========================================================
   1. DESIGN TOKENS - MATCHING INDEX.PHP COLORS
========================================================= */

:root{
  --color-primary: #1e40af;
  --color-primary-hover: #1e3a8a;
  --color-surface: #f8f9ff;
  --color-surface-container: #e8f0fe;
  --color-surface-container-lowest: #ffffff;
  --color-on-surface: #111827;
  --color-on-surface-variant: #4b5563;
  --color-outline: #d1d5db;
  --color-outline-variant: #e5e7eb;
  
  --color-secondary: #006c49;
  --color-secondary-hover: #005236;
  --color-secondary-container: #6cf8bb;
  
  --color-error: #dc2626;
  --color-error-hover: #b91c1c;
  
  --color-inverse-surface: #1e293b;
  
  --radius: 0.25rem;
  --radius-lg: 0.5rem;
  --radius-xl: 0.75rem;
  --radius-full: 9999px;
  --stack-lg: 24px;
  --container-max: 1280px;
  --margin-desktop: 32px;
  --margin-mobile: 16px;
  --gutter: 24px;
  --stack-md: 16px;
  --stack-sm: 8px;
}

/* =========================================================
   2. BASE - HANKEN GROTESK FONT
========================================================= */

body{
  font-family: 'Hanken Grotesk', sans-serif;
  background-color: var(--color-surface);
  color: var(--color-on-surface);
  min-height: 100vh;
}

.material-symbols-outlined{
  font-variation-settings:
  'FILL' 0,
  'wght' 400,
  'GRAD' 0,
  'opsz' 24;
  vertical-align: middle;
  line-height: 1;
}

.shadow-ambient{
  box-shadow:
  0px 1px 3px rgba(0,0,0,0.05),
  0px 10px 15px -5px rgba(0,0,0,0.05);
}

/* =========================================================
   3. TYPE SCALE - UPDATED
========================================================= */

.cc-body-lg{
  font-size:16px;
  line-height:24px;
  font-weight:400;
}

.cc-display-lg{
  font-size:24px;
  line-height:32px;
  letter-spacing:-0.01em;
  font-weight:700;
}

.cc-label-md{
  font-size:12px;
  line-height:16px;
  letter-spacing:0.05em;
  font-weight:600;
}

.cc-headline-md{
  font-size:24px;
  line-height:32px;
  letter-spacing:-0.01em;
  font-weight:600;
}

.cc-body-md{
  font-size:14px;
  line-height:20px;
  font-weight:400;
}

.cc-headline-sm{
  font-size:20px;
  line-height:28px;
  font-weight:600;
}

.cc-label-sm{
  font-size:11px;
  line-height:14px;
  font-weight:500;
}

@media (min-width:768px){
  .cc-display-lg{
    font-size:32px;
    line-height:40px;
    letter-spacing:-0.02em;
    font-weight:700;
  }
}

/* =========================================================
   4. COLOR UTILITIES - UPDATED TO MATCH INDEX.PHP
========================================================= */

.cc-bg-background{
  background-color: var(--color-surface);
}

.cc-bg-on-surface{
  background-color: var(--color-on-surface);
}

.cc-bg-surface-container-lowest{
  background-color: var(--color-surface-container-lowest);
}

.cc-bg-primary{
  background-color: var(--color-primary) !important;
}

.cc-bg-primary-hover:hover{
  background-color: var(--color-primary-hover) !important;
}

.cc-bg-secondary{
  background-color: var(--color-secondary);
}

.cc-bg-secondary-container-20{
  background-color: rgba(108,248,187,0.20);
}

.cc-bg-secondary-container-10{
  background-color: rgba(108,248,187,0.10);
}

.cc-bg-surface-variant{
  background-color: var(--color-surface-container);
}

.cc-bg-surface-variant-10{
  background-color: rgba(232,240,254,0.10);
}

.cc-bg-surface-variant-20{
  background-color: rgba(232,240,254,0.20);
}

.cc-bg-error{
  background-color: var(--color-error);
}

.cc-bg-error-90{
  background-color: rgba(220,38,38,0.9);
}

.cc-bg-inverse-surface{
  background-color: var(--color-inverse-surface);
}

.cc-bg-white{
  background-color:#ffffff;
}

.cc-text-on-background{
  color: var(--color-on-surface);
}

.cc-text-on-surface{
  color: var(--color-on-surface);
}

.cc-text-on-surface-variant{
  color: var(--color-on-surface-variant);
}

.cc-text-on-primary{
  color: #ffffff;
}

.cc-text-on-secondary{
  color: #ffffff;
}

.cc-text-on-error{
  color: #ffffff;
}

.cc-text-inverse-on-surface{
  color: #f8fafc;
}

.cc-text-secondary{
  color: var(--color-secondary);
}

.cc-text-surface-variant{
  color: var(--color-surface-container);
}

.cc-text-white{
  color:#ffffff;
}

.cc-border-secondary{
  border-color: var(--color-secondary) !important;
}

.cc-border-primary{
  border-color: var(--color-primary) !important;
}

.cc-border-outline-variant-20{
  border-color: rgba(229,231,235,0.20) !important;
}

.cc-border-outline-variant-30{
  border-color: rgba(229,231,235,0.30) !important;
}

.cc-border-outline-variant{
  border-color: var(--color-outline-variant) !important;
}

.cc-border-secondary-20{
  border-color: rgba(0,108,73,0.20) !important;
}

.cc-border-secondary-container{
  border-color: var(--color-secondary-container) !important;
}

.cc-border-surface-variant-20{
  border-color: rgba(232,240,254,0.20) !important;
}

/* =========================================================
   5. LAYOUT HELPERS
========================================================= */

.cc-w-280{
  width:280px;
}

.cc-w-24{
  width:96px;
}

.cc-h-24{
  height:96px;
}

.cc-w-48{
  width:192px;
}

.cc-h-48{
  height:192px;
}

.cc-w-2_5{
  width:10px;
}

.cc-h-2_5{
  height:10px;
}

.cc-container-max{
  max-width: var(--container-max);
}

.cc-px-margin-desktop{
  padding-left: var(--margin-desktop);
  padding-right: var(--margin-desktop);
}

.cc-p-margin-desktop{
  padding: var(--margin-desktop);
}

.cc-gap-1{
  gap:.25rem;
}

.cc-gap-2{
  gap:.5rem;
}

.cc-gap-stack-sm{
  gap:8px;
}

.cc-gap-stack-md{
  gap:16px;
}

.cc-gap-stack-lg{
  gap:24px;
}

.cc-gap-gutter{
  gap:24px;
}

.cc-rounded-full{
  border-radius: var(--radius-full);
}

.cc-rounded-lg{
  border-radius: var(--radius-lg);
}

.cc-rounded-xl{
  border-radius: var(--radius-xl);
}

/* =========================================================
   6. STRUCTURAL LAYOUT - UPDATED SIDEBAR
========================================================= */

.side-nav{
  position:fixed;
  left:0;
  top:0;
  height:100vh;
  width:280px;
  display:flex;
  flex-direction:column;
  padding:24px 0;
  gap:16px;
  z-index:1030;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);
  overflow-y:auto;
  transition: transform 0.3s ease;
  background-color: var(--color-on-surface) !important;
}

.side-nav-content{
  flex:1;
  display:flex;
  flex-direction:column;
}

.side-nav-footer{
  margin-top:auto;
  padding:16px 8px;
  border-top:1px solid rgba(229,231,235,0.20);
}

.main-wrap{
  display:flex;
  flex-direction:column;
  min-height:100vh;
}

@media (min-width:769px){
  .main-wrap{
    margin-left:280px;
  }
}

/* Mobile sidebar toggle */
.sidebar-toggle {
    display: none;
    background: none;
    border: none;
    color: var(--color-on-surface);
    font-size: 24px;
    padding: 4px 8px;
    cursor: pointer;
}

/* Sidebar overlay for mobile */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1029;
}

.sidebar-overlay.active {
    display: block;
}

/* Mobile sidebar active state */
.side-nav.active {
    transform: translateX(0);
}

@media (max-width:768px){
    .side-nav {
        transform: translateX(-100%);
        width: 280px;
    }
    .side-nav.active {
        transform: translateX(0);
    }
    .sidebar-toggle {
        display: block;
    }
    .main-wrap {
        margin-left: 0;
    }
}

.top-navbar{
  position:sticky;
  top:0;
  z-index:1020;
  min-height:64px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  border-bottom:1px solid rgba(229,231,235,0.20);
  flex-wrap: wrap;
  gap: 8px;
  background-color: rgba(255,255,255,0.9) !important;
  backdrop-filter: blur(10px);
}

.top-navbar .d-flex:first-child {
    flex: 1;
    min-width: 0;
}

.top-navbar h1 {
    font-size: clamp(14px, 2.5vw, 24px);
    line-height: 1.2;
    word-break: break-word;
}

.side-link{
  display:flex;
  align-items:center;
  gap:8px;
  padding:8px 16px;
  margin:0 8px;
  border-radius: var(--radius-lg);
  transition: background-color .15s ease, color .15s ease;
  text-decoration:none;
  color: var(--color-surface-container) !important;
}

.side-link.active{
  background-color: var(--color-primary) !important;
  color: #ffffff !important;
}

.side-link:not(.active){
  color: var(--color-surface-container) !important;
}

.side-link:hover{
  background-color: rgba(232,240,254,0.10);
  color: #ffffff !important;
}

.side-link-download{
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 16px;
  margin:0 8px;
  border-radius: var(--radius-lg);
  transition: background-color .15s ease, color .15s ease;
  text-decoration:none;
  background: rgba(108,248,187,0.15);
  border: 1px solid rgba(108,248,187,0.30);
  color: #6ffbbe;
}

.side-link-download:hover{
  background: rgba(108,248,187,0.25);
  color: #ffffff;
  border-color: rgba(108,248,187,0.50);
}

.side-link-download .material-symbols-outlined{
  font-size:22px;
}

.side-link-disabled{
  opacity:0.5;
  cursor:not-allowed;
}

.side-link-disabled:hover{
  background-color:transparent !important;
  color: var(--color-surface-container) !important;
}

/* =========================================================
   BUTTONS - UPDATED TO MATCH INDEX.PHP
========================================================= */

.btn-primary-cc{
  background-color: var(--color-primary) !important;
  color: #ffffff !important;
  transition: background-color .15s ease;
  border: none;
}

.btn-primary-cc:hover{
  background-color: var(--color-primary-hover) !important;
  color: #ffffff !important;
  transform: scale(0.98);
}

.btn-secondary-cc{
  background-color: var(--color-secondary) !important;
  color: #ffffff !important;
  transition: background-color .15s ease;
  border: none;
}

.btn-secondary-cc:hover{
  background-color: var(--color-secondary-hover) !important;
  color: #ffffff !important;
  transform: scale(0.98);
}

.btn-inverse-cc{
  background-color: var(--color-inverse-surface) !important;
  color: #f8fafc !important;
  transition: background-color .15s ease;
  border: none;
}

.btn-inverse-cc:hover{
  background-color: #0f172a !important;
  color: #ffffff !important;
  transform: scale(0.98);
}

.btn-error-cc{
  background-color: var(--color-error) !important;
  color: #ffffff !important;
  transition: background-color .15s ease;
  border: none;
}

.btn-error-cc:hover{
  background-color: var(--color-error-hover) !important;
  color: #ffffff !important;
  transform: scale(0.98);
}

.btn-outline-white-cc{
  background: transparent;
  border: 2px solid var(--color-outline-variant);
  color: var(--color-on-surface);
  transition: background-color .15s ease;
}

.btn-outline-white-cc:hover{
  background-color: rgba(232,240,254,0.20);
}

.btn-icon{
  background:none;
  border:none;
  color: var(--color-on-surface-variant);
}

.btn-icon:hover{
  background-color: rgba(232,240,254,0.20);
}

/* Download Form Button - Updated */
.btn-download-form{
  background: linear-gradient(135deg, #1e40af, #1e3a8a) !important;
  color: #ffffff !important;
  border: none;
  padding: 10px 20px;
  border-radius: var(--radius-lg);
  font-weight: 600;
  font-size: 13px;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  justify-content: center;
  text-decoration: none;
}

.btn-download-form:hover{
  background: linear-gradient(135deg, #1e3a8a, #1e2a6a) !important;
  color: #ffffff !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(30,64,175,0.3);
}

.btn-download-form .material-symbols-outlined{
  font-size:22px;
}

.progress-track{
  height:10px;
  border-radius: var(--radius-full);
  overflow:hidden;
}

.progress-fill{
  height:100%;
  border-radius: var(--radius-full);
}

.avatar-96{
  width:96px;
  height:96px;
}

.avatar-border{
  border:4px solid rgba(232,240,254,0.20);
}

/* Responsive avatar */
@media (max-width:576px){
    .avatar-96 {
        width:72px;
        height:72px;
    }
}

/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state-icon{
  font-size:64px;
  color: var(--color-outline);
  opacity:0.5;
}

@media (max-width:576px){
    .empty-state-icon {
        font-size:48px;
    }
}

.empty-state-card{
  background: var(--color-surface-container-lowest);
  border: 2px dashed var(--color-outline-variant);
  border-radius: var(--radius-xl);
  padding: 32px 24px;
  text-align:center;
}

@media (max-width:576px){
    .empty-state-card {
        padding:20px 16px;
    }
}

.empty-state-card .empty-title{
  font-size:18px;
  font-weight:600;
  color: var(--color-on-surface);
  margin-top:12px;
}

@media (max-width:576px){
    .empty-state-card .empty-title {
        font-size:16px;
    }
}

.empty-state-card .empty-subtitle{
  color: var(--color-on-surface-variant);
  font-size:14px;
  margin-top:4px;
}

/* =========================================================
   SUBMIT STATUS
========================================================= */

.submit-status-text{
  font-size:11px;
  padding:2px 0;
}

.submit-status-text.pending{
  color:#856404;
}

.submit-status-text.approved{
  color:#155724;
}

.submit-status-text.rejected{
  color:#721c24;
}

.submit-status-text.returned{
  color:#856404;
}

/* =========================================================
   ACCOUNT ARROW
========================================================= */

.account-arrow{
  transition:transform 0.3s ease;
}

.nav-item
.side-link[data-bs-toggle="collapse"][aria-expanded="true"]
.account-arrow{
  transform:rotate(180deg);
}

/* =========================================================
   RESPONSIVE TABLES
========================================================= */

.table-responsive-custom {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -4px;
    padding: 0 4px;
}

.table-custom {
    width: 100%;
    min-width: 500px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
}

@media (max-width:640px){
    .table-custom {
        min-width: 400px;
        font-size: 12px;
    }
    .table-custom th,
    .table-custom td {
        padding: 8px 10px !important;
    }
}

@media (max-width:480px){
    .table-custom {
        min-width: 320px;
        font-size: 11px;
    }
    .table-custom th,
    .table-custom td {
        padding: 6px 8px !important;
    }
}

/* =========================================================
   RESPONSIVE CARDS & GRID
========================================================= */

.row-custom {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -12px;
}

.col-custom {
    flex: 1 1 auto;
    padding: 0 12px;
    min-width: 0;
}

@media (max-width:576px){
    .row-custom {
        margin: 0 -8px;
    }
    .col-custom {
        padding: 0 8px;
    }
}

@media (max-width:576px){
    .cc-p-margin-desktop {
        padding: 12px !important;
    }
    .cc-px-margin-desktop {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
}

/* =========================================================
   RESPONSIVE ALERTS
========================================================= */

.alert {
    padding: 12px 16px;
    font-size: 14px;
    border-radius: var(--radius-lg);
}

.alert-info {
    background-color: #e8f0fe;
    border-color: var(--color-primary);
    color: var(--color-primary);
}

@media (max-width:576px){
    .alert {
        padding: 10px 12px;
        font-size: 13px;
    }
    .alert .btn-sm {
        font-size: 12px;
        padding: 4px 10px;
    }
}

/* =========================================================
   RESPONSIVE BUTTONS
========================================================= */

.btn {
    white-space: nowrap;
}

@media (max-width:576px){
    .btn {
        font-size: 13px;
        padding: 6px 14px;
    }
    .btn-sm {
        font-size: 11px;
        padding: 4px 10px;
    }
}

/* =========================================================
   RESPONSIVE FORMS
========================================================= */

.form-control {
    max-width: 100%;
}

@media (max-width:576px){
    .form-control {
        font-size: 16px;
        padding: 8px 12px;
    }
    .form-label {
        font-size: 13px;
    }
}

/* =========================================================
   RESPONSIVE MOBILE
========================================================= */

@media (max-width:767.98px){
    .side-nav{
        position:fixed;
        width:280px;
        height:100vh;
        transform:translateX(-100%);
        transition:transform 0.3s ease;
    }
    .side-nav.active{
        transform:translateX(0);
    }
    .main-wrap{
        margin-left:0;
    }
    .top-navbar{
        padding-left:12px!important;
        padding-right:12px!important;
        flex-wrap:wrap;
        gap:8px;
    }
    .top-navbar h1 {
        font-size:16px;
        line-height:1.2;
    }
    .cc-p-margin-desktop{
        padding:12px!important;
    }
    .cc-px-margin-desktop{
        padding-left:12px!important;
        padding-right:12px!important;
    }
    .sidebar-toggle {
        display:block;
    }
    .sidebar-overlay.active {
        display:block;
    }
    .row.g-4 {
        --bs-gutter-y: 1rem;
    }
    .col-12.col-lg-8,
    .col-12.col-lg-4 {
        padding-left: 0;
        padding-right: 0;
    }
}

/* =========================================================
   RESPONSIVE TABLES - Mobile friendly
========================================================= */

@media (max-width:767.98px){
    .table-custom {
        font-size: 12px;
    }
    .table-custom th,
    .table-custom td {
        padding: 8px 10px !important;
        word-break: break-word;
    }
}

/* =========================================================
   RESPONSIVE EXTRA
========================================================= */

@media (max-width:400px){
    .side-nav {
        width: 260px;
    }
    .side-nav .avatar-96 {
        width: 60px;
        height: 60px;
    }
    .side-link {
        font-size: 12px;
        padding: 6px 12px;
    }
    .side-link .material-symbols-outlined {
        font-size: 18px !important;
    }
    .top-navbar .btn {
        font-size: 12px;
        padding: 4px 10px;
    }
    .top-navbar h1 {
        font-size: 14px;
    }
    .progress-track {
        height: 8px;
    }
    .btn-download-form {
        font-size: 11px;
        padding: 8px 12px;
    }
    .btn-download-form .material-symbols-outlined {
        font-size: 18px !important;
    }
}

/* =========================================================
   PRINT STYLES
========================================================= */

@media print {
    .side-nav,
    .top-navbar,
    .sidebar-toggle,
    .sidebar-overlay,
    .footer-custom {
        display: none !important;
    }
    .main-wrap {
        margin-left: 0 !important;
    }
    body {
        background: #fff !important;
    }
}

/* =========================================================
   CLEARANCE STATUS PAGE
========================================================= */

@media (max-width:576px){
    .clearance-status-table th,
    .clearance-status-table td {
        padding: 8px 10px !important;
        font-size: 12px;
    }
    .clearance-status-table .status-badge {
        font-size: 10px;
        padding: 2px 8px;
    }
}

/* =========================================================
   NOTIFICATIONS PAGE
========================================================= */

@media (max-width:576px){
    .notification-item {
        padding: 16px !important;
        gap: 12px !important;
    }
    .notification-item .bi {
        font-size: 1.2rem !important;
    }
    .notification-item p {
        font-size: 14px !important;
    }
}

/* =========================================================
   SUBMIT SIGNED DOCUMENTS PAGE
========================================================= */

@media (max-width:576px){
    .submit-requirements-grid {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    .submit-document-box {
        padding: 12px !important;
    }
    .submit-document-box .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 8px !important;
    }
}

/* =========================================================
   UPLOAD DOCUMENTS PAGE
========================================================= */

@media (max-width:576px){
    .upload-main-file {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .upload-main-file input[type="file"] {
        min-width: 100% !important;
    }
    .upload-main-file button {
        width: 100% !important;
    }
}

</style>

</head>

<body class="cc-bg-background cc-text-on-background">

<!-- =========================================================
     SIDEBAR OVERLAY (Mobile)
========================================================= -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="side-nav" id="sidebar">

<div class="side-nav-content">

<!-- STUDENT PROFILE -->

<div class="text-center d-flex flex-column align-items-center px-4 mb-4">

<img
    alt="Student Profile Picture"
    class="avatar-96 cc-rounded-full avatar-border mb-3"
    style="object-fit:cover;"
    src="../assets/uploads/<?php echo htmlspecialchars($student['passport'] ?? ''); ?>"
>

<h2 class="cc-headline-sm cc-text-white mb-0" style="word-break:break-word; text-align:center; font-size:clamp(16px, 2vw, 20px); color:#ffffff;">

<?php echo htmlspecialchars($student['fullname']); ?>

</h2>

<p class="cc-body-md mt-1 mb-0" style="word-break:break-word; text-align:center; color:rgba(255,255,255,0.7);">

<?php echo htmlspecialchars($student['reg_number']); ?>

</p>

</div>

<div
    class="mx-4 mb-3"
    style="border-top:1px solid rgba(255,255,255,0.10);"
></div>

<!-- =====================================================
     NAVIGATION
===================================================== -->

<nav class="flex-grow-1 d-flex flex-column cc-gap-2">

<!-- DASHBOARD -->

<a
    class="side-link active cc-label-md"
    href="dashboard.php"
>

<span class="material-symbols-outlined">
dashboard
</span>

<span>
Dashboard
</span>

</a>

<!-- =====================================================
     ACCOUNT DROPDOWN
===================================================== -->

<div class="nav-item">

<a
    class="side-link cc-label-md"
    data-bs-toggle="collapse"
    href="#accountMenu"
    role="button"
    aria-expanded="false"
    aria-controls="accountMenu"
>

<span class="material-symbols-outlined">
person
</span>

<span>
Account
</span>

<span class="ms-auto">

<span
    class="material-symbols-outlined account-arrow"
    style="font-size:18px;"
>
expand_more
</span>

</span>

</a>

<div
    class="collapse"
    id="accountMenu"
>

<div class="ms-4 mt-1 d-flex flex-column cc-gap-1">

<a
    href="dashboard.php?edit_photo"
    class="side-link cc-label-md ps-5"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
edit
</span>

<span>
Edit Photo
</span>

</a>

<a
    href="dashboard.php?changepassword"
    class="side-link cc-label-md ps-5"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
key
</span>

<span>
Change Password
</span>

</a>

</div>

</div>

</div>

<!-- =====================================================
     SUBMIT SIGNED DOCUMENTS
===================================================== -->

<div class="nav-item mt-1">

<a
    href="dashboard.php?submit-signed-documents"
    class="side-link cc-label-md <?php echo $can_access_submit ? '' : 'side-link-disabled'; ?>"
    onclick="<?php echo $can_access_submit ? '' : 'return false;'; ?>"
>

<span class="material-symbols-outlined">
upload_file
</span>

<span>
Submit Signed Documents
</span>

<?php if (!$can_access_submit && !$is_submitted) { ?>

<span
    class="material-symbols-outlined ms-auto"
    style="font-size:16px;"
>
lock
</span>

<?php } ?>

</a>

<?php if (!$can_access_submit && !$is_submitted) { ?>

<div
    class="text-muted small ps-5 mt-1"
    style="font-size:10px; opacity:0.7; word-break:break-word; color:rgba(255,255,255,0.5);"
>

<i class="bi bi-lock"></i>

<?php

$reasons = [];

if (!$has_signed_letter_check) {

    $reasons[] =
        "Department letter not signed yet";
}

if (!$all_offices_approved) {

    $reasons[] =
        "Clearance not fully approved (" .
        $approved_count_check .
        "/" .
        $total_offices_check .
        " offices)";
}

if (!$is_fully_cleared) {

    $reasons[] =
        "Not fully cleared";
}

echo
    "Available after: " .
    implode(", ", $reasons);

?>

</div>

<?php } ?>

<?php if (
    $is_submitted &&
    $submission_status == 'pending'
) { ?>

<div
    class="submit-status-text pending ps-5 mt-1"
    style="font-size:10px; color:#ffc107;"
>

<i class="bi bi-clock"></i>

Submitted — Awaiting Senate Review

<br>

<small>

Submitted:

<?php

echo date(
    'M d, Y',
    strtotime(
        $submission_check['submitted_at'] ?? 'now'
    )
);

?>

</small>

</div>

<?php } ?>

<?php if ($submission_status == 'approved') { ?>

<div
    class="submit-status-text approved ps-5 mt-1"
    style="font-size:10px; color:#28a745;"
>

<i class="bi bi-check-circle"></i>

Approved by Senate

</div>

<?php } ?>

<?php if ($submission_status == 'rejected') { ?>

<div
    class="submit-status-text rejected ps-5 mt-1"
    style="font-size:10px; color:#dc3545;"
>

<i class="bi bi-x-circle"></i>

Rejected by Senate

</div>

<?php } ?>

<?php if ($submission_status == 'returned') { ?>

<div
    class="submit-status-text returned ps-5 mt-1"
    style="font-size:10px; color:#ffc107;"
>

<i class="bi bi-arrow-return-left"></i>

Returned for Correction

</div>

<?php } ?>

</div>

</nav>

<!-- =====================================================
     DOWNLOAD CLEARANCE FORM
===================================================== -->

<div class="side-nav-footer">

<?php if (
    !$has_status_records ||
    strtolower($status) == 'pending'
) { ?>

<?php if ($found_form) { ?>

<a
    href="<?php echo htmlspecialchars($form_path); ?>"
    target="_blank"
    class="btn-download-form"
    download
>

<span class="material-symbols-outlined">
download
</span>

Download Clearance Form

</a>

<p
    class="text-center mt-2 mb-0"
    style="font-size:10px; opacity:0.7; color:rgba(255,255,255,0.5);"
>

PDF format • Print and fill

</p>

<?php } else { ?>

<button
    class="btn-download-form"
    disabled
    style="opacity:0.5; cursor:not-allowed; background:#555 !important;"
>

<span class="material-symbols-outlined">
download
</span>

Form Unavailable

</button>

<p
    class="text-center mt-2 mb-0"
    style="font-size:10px; opacity:0.7; color:rgba(255,255,255,0.5);"
>

Please contact admin

</p>

<?php } ?>

<?php } elseif (
    strtolower($status) == 'cleared'
) { ?>

<div class="text-center">

<span
    class="material-symbols-outlined"
    style="color:#4edea3; font-size:32px;"
>
check_circle
</span>

<p
    style="font-size:11px; color:rgba(255,255,255,0.7); margin-top:4px;"
>

✅ Cleared

</p>

</div>

<?php } else { ?>

<div class="text-center">

<span
    class="material-symbols-outlined"
    style="color:#ffb95f; font-size:32px;"
>
hourglass_top
</span>

<p
    style="font-size:11px; color:rgba(255,255,255,0.7); margin-top:4px;"
>

In Progress

</p>

</div>

<?php } ?>

</div>

</div>

</aside>

<!-- =========================================================
     MAIN WRAPPER
========================================================= -->

<div class="main-wrap">

<!-- =====================================================
     TOP NAVBAR - UPDATED
===================================================== -->

<header
    class="top-navbar cc-px-margin-desktop"
>

<div class="d-flex align-items-center cc-gap-stack-md" style="flex:1; min-width:0;">

<button class="sidebar-toggle d-md-none" onclick="toggleSidebar()" aria-label="Toggle navigation">
    <i class="bi bi-list" style="font-size:24px;"></i>
</button>

<h1
    class="cc-headline-sm fw-semibold cc-text-on-surface mb-0"
    style="font-size:clamp(14px, 2.5vw, 20px); word-break:break-word; line-height:1.2;"
>

Welcome,

<?php echo htmlspecialchars($student['fullname']); ?>

</h1>

</div>

<div class="d-flex align-items-center cc-gap-stack-md" style="flex-shrink:0;">

<a
    href="dashboard.php?notifications"
    class="btn btn-icon cc-rounded-full p-2 position-relative text-decoration-none"
>

<span class="material-symbols-outlined">
notifications
</span>

<?php if ($notif_count > 0) { ?>

<span
    class="position-absolute cc-w-2_5 cc-h-2_5 cc-bg-error cc-rounded-full"
    style="top:4px; right:4px; border:2px solid #ffffff;"
></span>

<?php } ?>

</a>

<a
    href="../logout.php"
    class="btn btn-error-cc d-flex align-items-center cc-gap-2 px-3 py-2 cc-rounded-lg cc-label-md text-decoration-none"
    style="font-size:clamp(11px, 1vw, 13px); white-space:nowrap;"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
logout
</span>

<span class="d-none d-sm-inline">Logout</span>

</a>

</div>

</header>

<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main
    class="flex-grow-1 cc-p-margin-desktop cc-container-max mx-auto w-100"
>

<?php

if (
    !isset($_GET['edit_photo']) &&
    !isset($_GET['changepassword']) &&
    !isset($_GET['notifications']) &&
    !isset($_GET['upload-documents']) &&
    !isset($_GET['clearance-status']) &&
    !isset($_GET['submit-signed-documents'])
) {

?>

<!-- =====================================================
     GET STARTED ALERT
===================================================== -->

<?php if ($show_get_started) { ?>

<div
    class="alert alert-info d-flex align-items-center gap-3 mb-4"
    style="border-left:4px solid var(--color-primary); position:relative; flex-wrap:wrap;"
>

<span
    class="material-symbols-outlined"
    style="font-size:32px; color:var(--color-primary); flex-shrink:0;"
>
info
</span>

<div class="flex-grow-1" style="min-width:120px;">

<strong>
Get Started!
</strong>

Download the clearance form below, fill it out, and upload it using the "Upload Documents" button.

</div>

<a
    href="<?php echo htmlspecialchars($form_path); ?>"
    target="_blank"
    class="btn btn-primary-cc btn-sm"
    download
    style="white-space:nowrap; flex-shrink:0;"
>

<span
    class="material-symbols-outlined"
    style="font-size:16px;"
>
download
</span>

Download Form

</a>

<button
    type="button"
    class="btn-close ms-2"
    data-bs-dismiss="alert"
    aria-label="Close"
    style="flex-shrink:0;"
></button>

</div>

<?php } ?>

<!-- =====================================================
     MAIN GRID
===================================================== -->

<div class="row g-3 g-md-4">

<!-- =====================================================
     LEFT COLUMN
===================================================== -->

<div class="col-12 col-lg-8">

<div class="d-flex flex-column cc-gap-stack-lg">

<!-- =====================================================
     CLEARANCE STATUS BANNER - UPDATED COLORS
===================================================== -->

<div
    id="clearanceStatusBanner"
    class="cc-bg-secondary-container-20 border cc-border-secondary-20 cc-rounded-xl p-3 p-md-4 d-flex align-items-center cc-gap-stack-md"
    style="flex-wrap:wrap;"
>

<div
    class="cc-bg-primary cc-text-on-primary p-2 cc-rounded-full d-flex align-items-center justify-content-center"
    style="flex-shrink:0;"
>

<span class="material-symbols-outlined">
check
</span>

</div>

<div style="flex:1; min-width:120px;">

<?php if (strtolower($status) == 'cleared') { ?>

<h2 class="cc-headline-sm cc-text-secondary mb-0" style="font-size:clamp(16px, 2vw, 20px); color:var(--color-secondary);">
Fully Cleared
</h2>

<p
    class="cc-body-md cc-text-on-surface-variant mt-1 mb-0"
>
Your clearance process is complete.
</p>

<?php } elseif (!$has_status_records) { ?>

<h2 class="cc-headline-sm cc-text-secondary mb-0" style="font-size:clamp(16px, 2vw, 20px); color:var(--color-secondary);">
Not Started
</h2>

<p
    class="cc-body-md cc-text-on-surface-variant mt-1 mb-0"
>
Download the clearance form to get started.
</p>

<?php } else { ?>

<h2 class="cc-headline-sm cc-text-secondary mb-0" style="font-size:clamp(16px, 2vw, 20px); color:var(--color-secondary);">
In Progress
</h2>

<p
    class="cc-body-md cc-text-on-surface-variant mt-1 mb-0"
>
Your clearance is still in progress.
</p>

<?php } ?>

</div>

</div>

<!-- =====================================================
     PROGRESS CARD - UPDATED BORDER COLOR
===================================================== -->

<div
    class="cc-bg-surface-container-lowest cc-rounded-xl p-3 p-md-4 shadow-ambient"
    style="border-left-width:4px; border-left-style:solid; border-left-color:var(--color-primary);"
>

<div
    class="d-flex flex-wrap justify-content-between align-items-end mb-3"
    style="gap:8px;"
>

<h3 class="cc-headline-sm cc-text-on-surface mb-0" style="font-size:clamp(16px, 1.8vw, 20px);">
Progress
</h3>

<span class="cc-text-on-surface-variant cc-body-md" style="font-size:clamp(12px, 1.2vw, 14px);">

Approved:

<strong id="approvedCount">
<?php echo $approved; ?>
</strong>

/

Total Offices:

<strong id="totalCount">
<?php echo $total; ?>
</strong>

</span>

</div>

<div
    class="progress-track w-100 cc-bg-surface-variant"
>

<div
    class="progress-fill cc-bg-primary"
    id="progress"
    style="width:<?php echo $progress; ?>%;"
></div>

</div>

<div class="mt-2 text-end">

<span
    class="cc-text-primary cc-label-md"
    id="progressText"
    style="color:var(--color-primary);"
>
<?php echo $progress; ?>%
</span>

</div>

<?php if ($rejected > 0) { ?>

<div
    class="mt-3 cc-bg-error-90 text-white p-3 cc-rounded-lg d-flex align-items-center cc-gap-2"
    style="flex-wrap:wrap;"
>

<span class="material-symbols-outlined">
warning
</span>

<span class="cc-body-md mb-0">

You have

<strong>
<?php echo $rejected; ?>
</strong>

rejected clearance request(s).

</span>

</div>

<?php } ?>

<?php if (!$has_status_records) { ?>

<div
    class="mt-3 cc-bg-primary text-white p-3 cc-rounded-lg d-flex align-items-center cc-gap-2"
    style="flex-wrap:wrap;"
>

<span class="material-symbols-outlined">
info
</span>

<span class="cc-body-md mb-0">

Click "Upload Documents" below to start your clearance process.

</span>

</div>

<?php } ?>

</div>

<!-- =====================================================
     QUICK ACTIONS - UPDATED BUTTONS
===================================================== -->

<div
    class="cc-bg-surface-container-lowest cc-rounded-xl p-3 p-md-4 shadow-ambient"
    style="border-left-width:4px; border-left-style:solid; border-left-color:var(--color-primary);"
>

<h3 class="cc-headline-sm cc-text-on-surface mb-3" style="font-size:clamp(16px, 1.8vw, 20px);">
Quick Actions
</h3>

<div class="d-flex flex-wrap cc-gap-stack-md">

<a
    href="dashboard.php?upload-documents"
    class="btn btn-primary-cc flex-fill cc-label-md py-3 px-3 px-md-4 cc-rounded-lg text-center text-decoration-none"
    style="min-width:120px; font-size:clamp(11px, 1vw, 13px);"
>
Upload Documents
</a>

<a
    href="dashboard.php?clearance-status"
    class="btn btn-secondary-cc flex-fill cc-label-md py-3 px-3 px-md-4 cc-rounded-lg text-center text-decoration-none"
    style="min-width:120px; font-size:clamp(11px, 1vw, 13px);"
>
View Status
</a>

<?php if (strtolower($status) == 'cleared') { ?>

<a
    href="print-slip.php"
    class="btn btn-inverse-cc flex-fill cc-label-md py-3 px-3 px-md-4 cc-rounded-lg text-center text-decoration-none"
    style="min-width:120px; font-size:clamp(11px, 1vw, 13px);"
>
Print Slip
</a>

<?php } ?>

</div>

</div>

<!-- =====================================================
     STUDENT INFO
===================================================== -->

<div
    class="cc-bg-surface-container-lowest cc-rounded-xl p-3 p-md-4 shadow-ambient"
>

<h3
    class="cc-headline-sm cc-text-on-surface mb-3 pb-2"
    style="border-bottom:1px solid rgba(229,231,235,0.30); font-size:clamp(16px, 1.8vw, 20px);"
>
Student Info
</h3>

<div class="row row-cols-1 row-cols-sm-2 g-2 g-md-3">

<div class="col">

<p
    class="cc-text-on-surface-variant cc-label-md mb-1"
>
Name
</p>

<p
    class="cc-text-on-surface cc-body-lg mb-0"
    style="word-break:break-word; font-size:clamp(14px, 1.2vw, 16px);"
>
<?php echo htmlspecialchars($student['fullname']); ?>
</p>

</div>

<div class="col">

<p
    class="cc-text-on-surface-variant cc-label-md mb-1"
>
Student ID
</p>

<p
    class="cc-text-on-surface cc-body-lg mb-0"
    style="word-break:break-word; font-size:clamp(14px, 1.2vw, 16px);"
>
<?php echo htmlspecialchars($student['reg_number']); ?>
</p>

</div>

<div class="col">

<p
    class="cc-text-on-surface-variant cc-label-md mb-1"
>
Faculty
</p>

<p
    class="cc-text-on-surface cc-body-lg mb-0"
    style="word-break:break-word; font-size:clamp(14px, 1.2vw, 16px);"
>
<?php echo htmlspecialchars($student['faculty_name']); ?>
</p>

</div>

<div class="col">

<p
    class="cc-text-on-surface-variant cc-label-md mb-1"
>
Department
</p>

<p
    class="cc-text-on-surface cc-body-lg mb-0"
    style="word-break:break-word; font-size:clamp(14px, 1.2vw, 16px);"
>
<?php echo htmlspecialchars($student['department']); ?>
</p>

</div>

</div>

</div>

</div>

</div>

<!-- =====================================================
     RIGHT COLUMN
===================================================== -->

<div class="col-12 col-lg-4">

<div class="d-flex flex-column cc-gap-stack-lg">

<!-- =====================================================
     DEPARTMENT CLEARANCE LETTER
===================================================== -->

<?php if (
    $signed_letter &&
    !empty($signed_letter['signed_file'])
) { ?>

<div
    class="cc-bg-surface-container-lowest cc-rounded-xl p-3 p-md-4 shadow-ambient"
    style="border-top-width:4px; border-top-style:solid; border-top-color:var(--color-primary);"
>

<h3 class="cc-headline-sm cc-text-on-surface mb-3" style="font-size:clamp(16px, 1.8vw, 20px);">
Department Clearance Letter
</h3>

<div
    class="cc-bg-secondary-container-10 border cc-border-secondary-container cc-rounded-lg p-3 mb-3"
>

<p
    class="cc-text-on-surface cc-body-md mb-0"
>
Your department clearance letter has been signed and approved.
</p>

</div>

<a
    href="../assets/uploads/department_letters/<?php echo htmlspecialchars($signed_letter['signed_file']); ?>"
    target="_blank"
    download
    class="btn btn-primary-cc w-100 cc-label-md py-3 cc-rounded-lg d-flex align-items-center justify-content-center cc-gap-2 text-decoration-none"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
download
</span>

Download Signed Letter

</a>

</div>

<?php } else { ?>

<div class="empty-state-card">

<span
    class="material-symbols-outlined empty-state-icon"
>
description
</span>

<h4 class="empty-title">
No Signed Letter Yet
</h4>

<p class="empty-subtitle">

Your department clearance letter will appear here once it's signed by the department officer.

</p>

<a
    href="dashboard.php?upload-documents"
    class="btn btn-primary-cc mt-3 cc-label-md py-2 px-4 cc-rounded-lg text-decoration-none d-inline-flex align-items-center cc-gap-2"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
upload
</span>

Upload Documents

</a>

</div>

<?php } ?>

<!-- =====================================================
     QR VERIFICATION
===================================================== -->

<?php if (
    strtolower($status) == 'cleared' &&
    $qr_exists
) { ?>

<div
    class="cc-bg-surface-container-lowest cc-rounded-xl p-3 p-md-4 shadow-ambient d-flex flex-column align-items-center text-center"
>

<h3
    class="cc-headline-sm cc-text-on-surface mb-4 w-100 text-start"
    style="font-size:clamp(16px, 1.8vw, 20px);"
>
QR Verification
</h3>

<div
    class="cc-bg-white p-3 cc-rounded-xl border cc-border-surface-variant-20 mb-4 shadow-sm"
>

<img
    alt="QR Code"
    class="cc-w-48 cc-h-48"
    src="<?php echo htmlspecialchars($qr_file); ?>"
    style="max-width:100%; height:auto;"
>

</div>

<div class="d-flex flex-column w-100 gap-2">

<a
    href="<?php echo htmlspecialchars($qr_file); ?>"
    download
    class="btn btn-outline-white-cc w-100 cc-label-md py-3 cc-rounded-lg d-flex align-items-center justify-content-center cc-gap-2 text-decoration-none"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
qr_code_scanner
</span>

Download QR

</a>

<a
    href="print-slip.php"
    class="btn btn-primary-cc w-100 cc-label-md py-3 cc-rounded-lg d-flex align-items-center justify-content-center cc-gap-2 text-decoration-none"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
print
</span>

Print Clearance Slip

</a>

</div>

</div>

<?php } else { ?>

<div class="empty-state-card">

<span
    class="material-symbols-outlined empty-state-icon"
>
qr_code
</span>

<h4 class="empty-title">
QR Code Not Available
</h4>

<p class="empty-subtitle">

<?php if (strtolower($status) == 'cleared') { ?>

Your QR code will be generated when you print your clearance slip.

<?php } else { ?>

Complete all clearance requirements to generate your QR code.

<?php } ?>

</p>

<div class="d-flex flex-column w-100 gap-2 mt-3">

<?php if (strtolower($status) == 'cleared') { ?>

<a
    href="print-slip.php"
    class="btn btn-primary-cc w-100 cc-label-md py-2 cc-rounded-lg d-flex align-items-center justify-content-center cc-gap-2 text-decoration-none"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
print
</span>

Print Clearance Slip

</a>

<?php } else { ?>

<a
    href="dashboard.php?clearance-status"
    class="btn btn-primary-cc w-100 cc-label-md py-2 cc-rounded-lg d-flex align-items-center justify-content-center cc-gap-2 text-decoration-none"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
visibility
</span>

View Status

</a>

<a
    href="dashboard.php?upload-documents"
    class="btn btn-secondary-cc w-100 cc-label-md py-2 cc-rounded-lg d-flex align-items-center justify-content-center cc-gap-2 text-decoration-none"
    style="font-size:clamp(12px, 1vw, 14px);"
>

<span
    class="material-symbols-outlined"
    style="font-size:18px;"
>
upload
</span>

Upload Documents

</a>

<?php } ?>

</div>

</div>

<?php } ?>

</div>

</div>

</div>

<?php } ?>

<!-- =========================================================
     OTHER PAGES
========================================================= -->

<?php

if (isset($_GET['edit_photo'])) {

    include('../edit-photo.php');

}

elseif (isset($_GET['changepassword'])) {

    include('../changepassword.php');

}

elseif (isset($_GET['notifications'])) {

    include('notifications.php');

}

elseif (isset($_GET['upload-documents'])) {

    include('upload-documents.php');

}

elseif (isset($_GET['clearance-status'])) {

    include('clearance-status.php');

}

elseif (isset($_GET['submit-signed-documents'])) {

    /* =====================================================
       SECURITY CHECK
    ===================================================== */

    if (
        !$can_access_submit &&
        !$is_submitted
    ) {

        echo "

        <div class='alert alert-warning'>

        <i class='bi bi-lock'></i>

        You do not have access to this page.

        <br>

        <strong>Requirements:</strong>

        <ul>
        ";

        if (!$has_signed_letter_check) {

            echo "
            <li>
            Department letter must be signed
            (with signature and date)
            </li>
            ";
        }

        if (!$all_offices_approved) {

            echo "
            <li>
            All offices must approve your clearance
            ($approved_count_check/$total_offices_check)
            </li>
            ";
        }

        if (!$is_fully_cleared) {

            echo "
            <li>
            Your overall clearance status must be 'Cleared'
            </li>
            ";
        }

        echo "

        </ul>

        <a
            href='dashboard.php'
            class='btn btn-primary-cc btn-sm mt-2'
        >
        Back to Dashboard
        </a>

        </div>

        ";

    } else {

        include('submit-signed-documents.php');

    }
}

?>

</main>

</div>

<!-- =========================================================
     JQUERY
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script>

/* =========================================================
   SIDEBAR TOGGLE FOR MOBILE
========================================================= */

function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && 
            !toggleBtn.contains(event.target) && 
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    }
});

// Close sidebar on window resize to desktop
window.addEventListener('resize', function() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }
});

/* =========================================================
   AJAX PROGRESS UPDATE
========================================================= */

setInterval(function () {

    $.ajax({

        url:'progress.php',

        method:'GET',

        dataType:'json',

        success:function(data){

            if(data){

                $('#progress').css(
                    'width',
                    (data.progress || 0) + '%'
                );

                $('#progressText').html(
                    (data.progress || 0) + '%'
                );

                $('#approvedCount').html(
                    data.approved || 0
                );

                $('#totalCount').html(
                    data.total || 0
                );
            }
        },

        error:function(xhr,status,error){

            console.log(
                'Progress update error:',
                error
            );
        }

    });

},3000);


/* =========================================================
   ACCOUNT DROPDOWN ARROW
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function(){

        var accountMenu =
            document.querySelector('#accountMenu');

        if(accountMenu){

            accountMenu.addEventListener(
                'show.bs.collapse',
                function(){

                    var toggle =
                        document.querySelector(
                            '[data-bs-toggle="collapse"][href="#accountMenu"]'
                        );

                    if(toggle){

                        toggle.setAttribute(
                            'aria-expanded',
                            'true'
                        );
                    }

                }
            );

            accountMenu.addEventListener(
                'hide.bs.collapse',
                function(){

                    var toggle =
                        document.querySelector(
                            '[data-bs-toggle="collapse"][href="#accountMenu"]'
                        );

                    if(toggle){

                        toggle.setAttribute(
                            'aria-expanded',
                            'false'
                        );
                    }

                }
            );
        }

    }
);


/* =========================================================
   REAL-TIME STUDENT STATUS UPDATE
========================================================= */

setInterval(function(){

    $.ajax({

        url:'get-student-status.php',

        method:'GET',

        dataType:'json',

        success:function(data){

            if(!data){
                return;
            }

            var approvedCount =
                data.approved || 0;

            var totalOffices =
                data.total || 0;

            var progress =
                data.progress || 0;

            var isCleared =
                data.is_cleared || false;

            var hasRecords =
                data.has_records || false;

            var rejectedCount =
                data.rejected || 0;


            /* =================================================
               UPDATE PROGRESS
            ================================================= */

            $('#progress').css(
                'width',
                progress + '%'
            );

            $('#progressText').html(
                progress + '%'
            );

            $('#approvedCount').html(
                approvedCount
            );

            $('#totalCount').html(
                totalOffices
            );


            /* =================================================
               UPDATE STATUS BANNER
            ================================================= */

            if(
                $('#clearanceStatusBanner').length
            ){

                if(isCleared){

                    $('#clearanceStatusBanner h2')
                        .text('Fully Cleared');

                    $('#clearanceStatusBanner p')
                        .text(
                            'Your clearance process is complete.'
                        );

                }

                else if(!hasRecords){

                    $('#clearanceStatusBanner h2')
                        .text('Not Started');

                    $('#clearanceStatusBanner p')
                        .text(
                            'Download the clearance form to get started.'
                        );

                }

                else{

                    $('#clearanceStatusBanner h2')
                        .text('In Progress');

                    $('#clearanceStatusBanner p')
                        .text(
                            'Your clearance is still in progress.'
                        );
                }
            }


            /* =================================================
               UPDATE SIDEBAR FOOTER
            ================================================= */

            var footer =
                $('.side-nav-footer');

            if(isCleared){

                footer.find('.text-center').html(

                    '<span class="material-symbols-outlined" ' +
                    'style="color:#4edea3;font-size:32px;">' +
                    'check_circle' +
                    '</span>' +

                    '<p style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;">' +
                    '✅ Cleared' +
                    '</p>'
                );

            }

            else if(hasRecords){

                footer.find('.text-center').html(

                    '<span class="material-symbols-outlined" ' +
                    'style="color:#ffb95f;font-size:32px;">' +
                    'hourglass_top' +
                    '</span>' +

                    '<p style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;">' +
                    'In Progress' +
                    '</p>'
                );

            }

            /* =================================================
               UPDATE REJECTED ALERT
            ================================================= */

            if(rejectedCount > 0){

                if(
                    $('.cc-bg-error-90').length === 0
                ){

                    var rejectedAlert =

                        '<div class="mt-3 cc-bg-error-90 text-white p-3 cc-rounded-lg d-flex align-items-center cc-gap-2">' +

                        '<span class="material-symbols-outlined">' +
                        'warning' +
                        '</span>' +

                        '<span class="cc-body-md mb-0">' +

                        'You have <strong>' +
                        rejectedCount +
                        '</strong> rejected clearance request(s).' +

                        '</span>' +

                        '</div>';

                    $('.cc-border-primary')
                        .first()
                        .after(rejectedAlert);

                }

                else{

                    $('.cc-bg-error-90 .cc-body-md')
                        .html(
                            'You have <strong>' +
                            rejectedCount +
                            '</strong> rejected clearance request(s).'
                        );
                }

            }

            else{

                $('.cc-bg-error-90').remove();
            }

        },

        error:function(xhr,status,error){

            console.log(
                'Status update error:',
                error
            );

        }

    });

},10000);

</script>

</body>

</html>