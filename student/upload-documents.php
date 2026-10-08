<?php
//session_start();

include '../includes/config.php';
include '../includes/functions.php';
include '../includes/csrf.php';

if(!isset($_SESSION['student_id'])){
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

$student = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT * FROM students WHERE id='$student_id'"));

/* ==============================
   OFFICES
============================== */
function getOffices($conn){
    return mysqli_query($conn,
    "SELECT * FROM offices WHERE status='active' ORDER BY id ASC");
}

/* ==============================
   OFFICE RULES
============================== */
function getOfficeRule($office, $student){

    $reg = strtoupper($student['reg_number']);

    switch($office){

        case 'DEPARTMENT':
            return "Department clearance form required (1 file only)";

        case 'BURSAR':
            // Calculate based on program duration
            $program = strtolower($student['programme'] ?? '');
            $reg = strtoupper($student['reg_number']);
            
            // Check if it's a medical program (6 years)
            $is_medical = strpos($program, 'medicine') !== false || 
                         strpos($program, 'medical') !== false ||
                         strpos($program, 'mbbs') !== false ||
                         strpos($program, 'pharmacy') !== false && strpos($program, '6') !== false;
            
            // Check if it's a 5-year program (engineering, law, architecture, etc.)
            $is_5_year = strpos($program, 'engineering') !== false || 
                        strpos($program, 'law') !== false ||
                        strpos($program, 'architecture') !== false ||
                        strpos($program, 'geology') !== false;
            
            // Default: 4-year program (most courses)
            $years = 4;
            
            if($is_medical){
                $years = 6;
            } elseif($is_5_year){
                $years = 5;
            }
            
            return "Fees payment evidence required (Upload all session receipts)";

        case 'ALUMNI RELATION DIVISION':
            return "Alumni payment receipt required (1 file only)";

        case 'LIBRARY':
            return "Library ID + 3 Borrowers Cards required (Max 4 uploads)";

        case 'FACULTY':
        case 'SPORT UNIT':
        case 'HALL':
            return "No requirement (Auto approval)";

        default:
            return "Upload required document";
    }
}

/* ==============================
   GET MAX BURSAR UPLOADS BASED ON PROGRAM
============================== */
function getBursarMaxUploads($student){
    $program = strtolower($student['programme'] ?? '');
    $reg = strtoupper($student['reg_number']);
    
    // Medical programs (6 years)
    $is_medical = strpos($program, 'medicine') !== false || 
                 strpos($program, 'medical') !== false ||
                 strpos($program, 'mbbs') !== false ||
                 strpos($program, 'pharmacy') !== false ||
                 strpos($program, 'veterinary') !== false;
    
    // 5-year programs
    $is_5_year = strpos($program, 'engineering') !== false || 
                strpos($program, 'law') !== false ||
                strpos($program, 'architecture') !== false ||
                strpos($program, 'geology') !== false ||
                strpos($program, 'urban') !== false ||
                strpos($program, 'planning') !== false;
    
    // Check if it's a direct entry (DE) student - starts from 200 level
    $is_de = strpos($reg, 'DE') !== false || 
             strpos($reg, 'D/E') !== false ||
             strpos($reg, 'D.E') !== false;
    
    // Default: 4-year program
    $years = 4;
    if($is_medical){
        $years = 6;
    } elseif($is_5_year){
        $years = 5;
    }
    
    // If DE student, they start from year 2, so subtract 1 year
    if($is_de){
        $years = $years - 1;
    }
    
    // Add buffer for carryover/spill over (max +2 extra years)
    $max_uploads = $years + 2;
    
    return $max_uploads;
}

/* ==============================
   SUPPORTED FILE TYPES - UPDATED
   ============================== */
function getAllowedExtensions($office = null){
    // For BURSAR, ALUMNI RELATION DIVISION, and LIBRARY - only images
    // Students upload receipts, payment proofs, ID cards, borrower cards - these are images
    if(in_array($office, ['BURSAR', 'ALUMNI RELATION DIVISION', 'LIBRARY'])){
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    }
    
    // For DEPARTMENT - only PDF and Word documents
    if($office == 'DEPARTMENT'){
        return ['pdf', 'docx', 'doc'];
    }
    
    // Default for other offices (FYP, Main Clearance, etc.)
    return ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'doc'];
}

/* ==============================
   CHECK IF OFFICE IS APPROVED
============================== */
function isOfficeApproved($conn, $student_id, $office){
    $q = mysqli_query($conn,"
        SELECT status FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($q);
    return ($row && strtolower($row['status']) == 'approved');
}

/* ==============================
   CHECK IF ALL OFFICES ARE APPROVED
============================== */
function isAllOfficesApproved($conn, $student_id){
    $total_q = mysqli_query($conn,"
        SELECT COUNT(*) as total FROM offices WHERE status='active'
    ");
    $total_data = mysqli_fetch_assoc($total_q);
    $total = $total_data['total'] ?? 0;
    
    $approved_q = mysqli_query($conn,"
        SELECT COUNT(*) as approved FROM clearance_status
        WHERE student_id='$student_id'
        AND status='approved'
    ");
    $approved_data = mysqli_fetch_assoc($approved_q);
    $approved = $approved_data['approved'] ?? 0;
    
    return ($approved >= $total && $total > 0);
}

/* ==============================
   GET APPROVED OFFICES COUNT
============================== */
function getApprovedOfficesCount($conn, $student_id){
    $approved_q = mysqli_query($conn,"
        SELECT COUNT(*) as approved FROM clearance_status
        WHERE student_id='$student_id'
        AND status='approved'
    ");
    $approved_data = mysqli_fetch_assoc($approved_q);
    return $approved_data['approved'] ?? 0;
}

/* ==============================
   GET FINAL YEAR PROJECT FILE
============================== */
function getFYPFile($conn, $student_id){
    $q = mysqli_query($conn,"
        SELECT fyp_file, uploaded_at
        FROM final_year_projects
        WHERE student_id='$student_id'
        LIMIT 1
    ");
    return mysqli_fetch_assoc($q);
}

/* ==============================
   CHECK IF FYP IS UPLOADED
============================== */
function hasFYPFile($conn, $student_id){
    $q = mysqli_query($conn,"
        SELECT id FROM final_year_projects
        WHERE student_id='$student_id'
        LIMIT 1
    ");
    return mysqli_num_rows($q) > 0;
}

$message = "";
$message_type = "";

/* =========================================================
   MAIN CLEARANCE
========================================================= */
if(isset($_POST['submit_clearance'])){

    verify_csrf();

    $file = $_FILES['clearance_file'];

    if(empty($file['name'])){
        $message = "Please select a file";
        $message_type = 'danger';
        goto end_main;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(!in_array($ext, ['pdf', 'docx', 'doc'])){
        $message = "Main clearance file must be a PDF or Word document (.pdf, .docx, .doc). Please upload a valid file.";
        $message_type = 'danger';
        goto end_main;
    }

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot modify the main file. Please contact the offices.";
        $message_type = 'warning';
        goto end_main;
    }

    $original = preg_replace('/[^A-Za-z0-9.\-_]/','',$file['name']);
    $filename = time().'_'.$original;
    $path = "../assets/uploads/".$filename;

    if(move_uploaded_file($file['tmp_name'],$path)){

        $existing = mysqli_query($conn,"
            SELECT filename FROM clearance_uploads 
            WHERE student_id='$student_id' LIMIT 1
        ");
        
        if(mysqli_num_rows($existing) > 0){
            $old = mysqli_fetch_assoc($existing);
            $old_file = $old['filename'];
            if(file_exists("../assets/uploads/".$old_file)){
                unlink("../assets/uploads/".$old_file);
            }
        }

        mysqli_query($conn,"
            INSERT IGNORE INTO clearance_requests(student_id, progress, status)
            VALUES('$student_id',0,'pending')
        ");

        mysqli_query($conn,"
            DELETE FROM clearance_uploads WHERE student_id='$student_id'
        ");

        mysqli_query($conn,"
            INSERT INTO clearance_uploads(student_id, filename, uploaded_at)
            VALUES('$student_id','$filename',NOW())
        ");

        $offices_q = getOffices($conn);

        while($o = mysqli_fetch_assoc($offices_q)){

            $office = strtoupper($o['offices_name']);
            
            $is_approved = isOfficeApproved($conn, $student_id, $office);
            $status = ($is_approved) ? 'approved' : 'pending';

            $check = mysqli_query($conn,"
                SELECT id FROM clearance_status 
                WHERE student_id='$student_id' AND department_role='$office'
            ");

            if(mysqli_num_rows($check) > 0){
                mysqli_query($conn,"
                    UPDATE clearance_status 
                    SET document_file='$filename',
                        student_name='{$student['fullname']}',
                        reg_number='{$student['reg_number']}',
                        status='$status'
                    WHERE student_id='$student_id' AND department_role='$office'
                ");
            } else {
                mysqli_query($conn,"
                    INSERT INTO clearance_status
                    (student_id, department_role, status, student_name, reg_number, document_file)
                    VALUES
                    ('$student_id','$office','$status',
                    '{$student['fullname']}',
                    '{$student['reg_number']}',
                    '$filename')
                ");
            }
        }

        updateDashboardStatuses($conn, $student_id);

        $message = "Main clearance uploaded successfully!";
        $message_type = 'success';
    } else {
        $message = "Failed to upload file. Please try again.";
        $message_type = 'danger';
    }

    end_main:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =========================================================
   FINAL YEAR PROJECT UPLOAD
========================================================= */
if(isset($_POST['upload_fyp'])){

    verify_csrf();

    $file = $_FILES['fyp_file'];

    if(empty($file['name'])){
        $message = "Please select a file";
        $message_type = 'danger';
        goto end_fyp;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(!in_array($ext, ['pdf', 'docx', 'doc'])){
        $message = "Final Year Project must be a PDF or Word document (.pdf, .docx, .doc). Please upload a valid file.";
        $message_type = 'danger';
        goto end_fyp;
    }

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot upload Final Year Project files.";
        $message_type = 'warning';
        goto end_fyp;
    }

    if(isOfficeApproved($conn, $student_id, 'DEPARTMENT')){
        $message = "Department has already approved your request. You cannot upload or modify Final Year Project files.";
        $message_type = 'warning';
        goto end_fyp;
    }

    if(!file_exists("../assets/uploads/fyp/")){
        mkdir("../assets/uploads/fyp/", 0777, true);
    }

    $original = preg_replace('/[^A-Za-z0-9.\-_]/','',$file['name']);
    $filename = time().'_'.$original;
    $path = "../assets/uploads/fyp/".$filename;

    if(move_uploaded_file($file['tmp_name'],$path)){

        $check_fyp = mysqli_query($conn,"
            SELECT id FROM final_year_projects
            WHERE student_id='$student_id'
        ");

        if(mysqli_num_rows($check_fyp) > 0){
            $old_fyp = mysqli_fetch_assoc($check_fyp);
            $old_file = $old_fyp['fyp_file'];
            if(file_exists("../assets/uploads/fyp/".$old_file)){
                unlink("../assets/uploads/fyp/".$old_file);
            }

            mysqli_query($conn,"
                UPDATE final_year_projects
                SET fyp_file='$filename',
                    uploaded_at=NOW()
                WHERE student_id='$student_id'
            ");
        } else {
            mysqli_query($conn,"
                INSERT INTO final_year_projects
                (student_id, fyp_file, uploaded_at)
                VALUES
                ('$student_id','$filename',NOW())
            ");
        }

        updateDashboardStatuses($conn, $student_id);

        $message = "Final Year Project uploaded successfully!";
        $message_type = 'success';
    } else {
        $message = "Failed to upload file. Please try again.";
        $message_type = 'danger';
    }

    end_fyp:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =========================================================
   DELETE FYP FILE
========================================================= */
if(isset($_POST['delete_fyp'])){

    verify_csrf();

    if(isOfficeApproved($conn, $student_id, 'DEPARTMENT')){
        $message = "Department has already approved your request. You cannot delete Final Year Project files.";
        $message_type = 'warning';
        goto end_delete_fyp;
    }

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot delete Final Year Project files.";
        $message_type = 'warning';
        goto end_delete_fyp;
    }

    $fyp_data = getFYPFile($conn, $student_id);
    if($fyp_data && !empty($fyp_data['fyp_file'])){
        $filepath = "../assets/uploads/fyp/".$fyp_data['fyp_file'];
        if(file_exists($filepath)){
            unlink($filepath);
        }
    }

    mysqli_query($conn,"
        DELETE FROM final_year_projects
        WHERE student_id='$student_id'
    ");

    updateDashboardStatuses($conn, $student_id);

    $message = "Final Year Project deleted successfully.";
    $message_type = 'success';
    
    end_delete_fyp:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =========================================================
   REQUIREMENT UPLOAD
========================================================= */
if(isset($_POST['upload_req'])){

    verify_csrf();

    $office = strtoupper($_POST['office']);
    $file = $_FILES['file'];

    // Check if office is auto-approved (no upload required)
    if(in_array($office, ['FACULTY', 'SPORT UNIT', 'HALL'])){
        $message = "$office requires no upload (Auto approved)";
        $message_type = 'warning';
        $_SESSION['upload_message'] = $message;
        $_SESSION['upload_message_type'] = $message_type;
        echo "<script>
            window.location.href = 'dashboard.php?upload-documents';
        </script>";
        exit();
    }

    if(empty($file['name'])){
        $message = "No file selected";
        $message_type = 'danger';
        goto end_upload;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowed_extensions = getAllowedExtensions($office);
    
    if(!in_array($ext, $allowed_extensions)){
        $allowed_list = implode(', ', $allowed_extensions);
        $message = "Invalid file type. Allowed types: $allowed_list";
        $message_type = 'danger';
        goto end_upload;
    }

    $is_approved = isOfficeApproved($conn, $student_id, $office);
    if($is_approved){
        $message = "This office has already approved your request. You cannot upload additional files. Please contact the office.";
        $message_type = 'warning';
        goto end_upload;
    }

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot upload additional files.";
        $message_type = 'warning';
        goto end_upload;
    }

    $check = mysqli_query($conn,"
        SELECT id FROM clearance_status 
        WHERE student_id='$student_id' AND department_role='$office'
    ");
    
    if(mysqli_num_rows($check) == 0){
        mysqli_query($conn,"
            INSERT INTO clearance_status
            (student_id, department_role, status, student_name, reg_number)
            VALUES
            ('$student_id','$office','pending',
            '{$student['fullname']}',
            '{$student['reg_number']}')
        ");
    }

    $q = mysqli_query($conn,"
        SELECT requirement_files FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");

    $row = mysqli_fetch_assoc($q);

    $files = json_decode($row['requirement_files'] ?? '[]', true);
    if(!is_array($files)) $files = [];

    $original = preg_replace('/[^A-Za-z0-9.\-_]/','',$file['name']);

    foreach($files as $f){
        if(strtolower(preg_replace('/^\d+_/','',$f)) == strtolower($original)){
            $message = "Duplicate file detected";
            $message_type = 'warning';
            goto end_upload;
        }
    }

    $limit = null;

    if($office == 'ALUMNI RELATION DIVISION') $limit = 1;
    elseif($office == 'DEPARTMENT') $limit = 1;
    elseif($office == 'LIBRARY') $limit = 4;
    elseif($office == 'BURSAR'){
        $limit = getBursarMaxUploads($student);
    }

    if($limit !== null && count($files) >= $limit){
        $message = "Upload limit reached (Max $limit files)";
        $message_type = 'danger';
        goto end_upload;
    }

    $filename = time().'_'.$original;
    $path = "../assets/uploads/requirements/".$filename;

    if(move_uploaded_file($file['tmp_name'],$path)){

        $files[] = $filename;

        mysqli_query($conn,"
            UPDATE clearance_status
            SET requirement_files='".mysqli_real_escape_string($conn,json_encode($files))."',
                status='pending'
            WHERE student_id='$student_id'
            AND department_role='$office'
        ");

        /* DEPARTMENT LETTER */
        if($office == 'DEPARTMENT'){

            $department_name = mysqli_real_escape_string(
                $conn,
                $student['department']
            );

            if(!file_exists("../assets/uploads/department_letters/")){
                mkdir("../assets/uploads/department_letters/", 0777, true);
            }

            $letter_path = "../assets/uploads/department_letters/".$filename;
            
            if(rename($path, $letter_path)){
                $path = $letter_path;
            } else {
                if(copy($path, $letter_path)){
                    unlink($path);
                    $path = $letter_path;
                }
            }

            $check_letter = mysqli_query($conn,"
                SELECT id
                FROM department_letters
                WHERE student_id='$student_id'
                LIMIT 1
            ");

            if(mysqli_num_rows($check_letter) > 0){
                mysqli_query($conn,"
                    UPDATE department_letters
                    SET
                        department_name='$department_name',
                        original_file='$filename',
                        status='pending',
                        signed_file=NULL,
                        signature_file=NULL,
                        signed_pdf=NULL,
                        officer_name=NULL,
                        signed_at=NULL
                    WHERE student_id='$student_id'
                ");
            } else {
                mysqli_query($conn,"
                    INSERT INTO department_letters
                    (
                        student_id,
                        department_name,
                        original_file,
                        status
                    )
                    VALUES
                    (
                        '$student_id',
                        '$department_name',
                        '$filename',
                        'pending'
                    )
                ");
            }
        }
        
        updateDashboardStatuses($conn, $student_id);

        $message = "Uploaded successfully for <b>$office</b>";
        $message_type = 'success';
    } else {
        $message = "Failed to upload file. Please try again.";
        $message_type = 'danger';
    }
end_upload:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =================================
   DELETE REQUIREMENT FILE
================================= */
if(isset($_POST['delete_file'])){

    verify_csrf();

    $office = strtoupper($_POST['office']);
    $file_name = basename($_POST['file_name']);

    // Check if office is auto-approved
    if(in_array($office, ['FACULTY', 'SPORT UNIT', 'HALL'])){
        $message = "$office has no requirement files to delete (Auto approved)";
        $message_type = 'warning';
        $_SESSION['upload_message'] = $message;
        $_SESSION['upload_message_type'] = $message_type;
        echo "<script>
            window.location.href = 'dashboard.php?upload-documents';
        </script>";
        exit();
    }

    $is_approved = isOfficeApproved($conn, $student_id, $office);
    if($is_approved){
        $message = "This office has already approved your request. You cannot delete files. Please contact the office.";
        $message_type = 'warning';
        goto end_delete_req;
    }

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot delete files.";
        $message_type = 'warning';
        goto end_delete_req;
    }

    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");

    $row = mysqli_fetch_assoc($q);

    $files = json_decode($row['requirement_files'] ?? '[]', true);

    if(!is_array($files)){
        $files = [];
    }

    $files = array_values(array_filter($files, function($f) use ($file_name){
        return $f !== $file_name;
    }));

    mysqli_query($conn,"
        UPDATE clearance_status
        SET requirement_files='".mysqli_real_escape_string($conn,json_encode($files))."'
        WHERE student_id='$student_id'
        AND department_role='$office'
    ");

    if($office == 'DEPARTMENT'){
        mysqli_query($conn,"
            DELETE FROM department_letters
            WHERE student_id='$student_id'
        ");
        
        $letter_path = "../assets/uploads/department_letters/".$file_name;
        if(file_exists($letter_path)){
            unlink($letter_path);
        }
    }

    $filepath = "../assets/uploads/requirements/".$file_name;
    if(file_exists($filepath)){
        unlink($filepath);
    }

    updateDashboardStatuses($conn, $student_id);

    $message = "File deleted successfully. Your status has been reset to pending for this office.";
    $message_type = 'success';
    
    end_delete_req:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =================================
   REPLACE REQUIREMENT FILE
================================= */
if(isset($_POST['replace_file'])){

    verify_csrf();

    $office = strtoupper($_POST['office']);
    $old_file = basename($_POST['old_file']);

    // Check if office is auto-approved
    if(in_array($office, ['FACULTY', 'SPORT UNIT', 'HALL'])){
        $message = "$office has no requirement files to replace (Auto approved)";
        $message_type = 'warning';
        $_SESSION['upload_message'] = $message;
        $_SESSION['upload_message_type'] = $message_type;
        echo "<script>
            window.location.href = 'dashboard.php?upload-documents';
        </script>";
        exit();
    }

    $is_approved = isOfficeApproved($conn, $student_id, $office);
    if($is_approved){
        $message = "This office has already approved your request. You cannot replace files. Please contact the office.";
        $message_type = 'warning';
        goto end_replace_req;
    }

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot replace files.";
        $message_type = 'warning';
        goto end_replace_req;
    }

    if(empty($_FILES['new_file']['name'])){
        $message = "Select a new file";
        $message_type = 'danger';
    }else{

        $ext = strtolower(pathinfo($_FILES['new_file']['name'], PATHINFO_EXTENSION));

        if(!in_array($ext,['pdf','docx','doc','jpg','jpeg','png'])){
            $message = "Invalid file type";
            $message_type = 'danger';
        }else{

            $new_file = time().'_'.preg_replace(
                '/[^A-Za-z0-9.\-_]/',
                '',
                $_FILES['new_file']['name']
            );

            $path = "../assets/uploads/requirements/".$new_file;

            if(move_uploaded_file($_FILES['new_file']['tmp_name'],$path)){

                $q = mysqli_query($conn,"
                    SELECT requirement_files
                    FROM clearance_status
                    WHERE student_id='$student_id'
                    AND department_role='$office'
                    LIMIT 1
                ");

                $row = mysqli_fetch_assoc($q);

                $files = json_decode($row['requirement_files'] ?? '[]', true);

                if(!is_array($files)){
                    $files = [];
                }

                foreach($files as $k=>$v){
                    if($v == $old_file){
                        $files[$k] = $new_file;
                    }
                }

                mysqli_query($conn,"
                    UPDATE clearance_status
                    SET requirement_files='".mysqli_real_escape_string($conn,json_encode($files))."',
                        status='pending'
                    WHERE student_id='$student_id'
                    AND department_role='$office'
                ");

                if($office == 'DEPARTMENT'){
                    if(!file_exists("../assets/uploads/department_letters/")){
                        mkdir("../assets/uploads/department_letters/", 0777, true);
                    }

                    $letter_path = "../assets/uploads/department_letters/".$new_file;
                    if(rename($path, $letter_path)){
                        $path = $letter_path;
                    } else {
                        copy($path, $letter_path);
                        unlink($path);
                    }

                    mysqli_query($conn,"
                        UPDATE department_letters
                        SET original_file='$new_file',
                            status='pending',
                            signed_file=NULL,
                            signature_file=NULL,
                            signed_pdf=NULL,
                            officer_name=NULL,
                            signed_at=NULL
                        WHERE student_id='$student_id'
                    ");
                }

                $old_path = "../assets/uploads/requirements/".$old_file;
                $old_letter_path = "../assets/uploads/department_letters/".$old_file;
                
                if(file_exists($old_path)){
                    unlink($old_path);
                }
                if(file_exists($old_letter_path)){
                    unlink($old_letter_path);
                }

                updateDashboardStatuses($conn, $student_id);

                $message = "File replaced successfully. Your status has been reset to pending for this office.";
                $message_type = 'success';
            }
        }
    }
    end_replace_req:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =================================
   DELETE MAIN FILE
================================= */
if(isset($_POST['delete_main_file'])){

    verify_csrf();

    $main_file = basename($_POST['main_file']);

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot delete the main file. Please contact the offices.";
        $message_type = 'warning';
        goto end_delete_main;
    }

    mysqli_query($conn,"
        DELETE FROM clearance_uploads
        WHERE student_id='$student_id'
        AND filename='$main_file'
    ");

    mysqli_query($conn,"
        UPDATE clearance_status
        SET document_file = NULL
        WHERE student_id='$student_id'
        AND document_file = '$main_file'
    ");

    $filepath = "../assets/uploads/".$main_file;
    if(file_exists($filepath)){
        unlink($filepath);
    }

    updateDashboardStatuses($conn, $student_id);

    $message = "Main file deleted successfully. Please upload a new file to continue.";
    $message_type = 'success';
    
    end_delete_main:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* =================================
   REPLACE MAIN FILE
================================= */
if(isset($_POST['replace_main_file'])){

    verify_csrf();

    $old_file = basename($_POST['old_main_file']);

    if(isAllOfficesApproved($conn, $student_id)){
        $message = "All offices have already approved your request. You cannot replace the main file. Please contact the offices.";
        $message_type = 'warning';
        goto end_replace_main;
    }

    if(empty($_FILES['new_main_file']['name'])){
        $message = "Select a new file";
        $message_type = 'danger';
    }else{

        $ext = strtolower(pathinfo($_FILES['new_main_file']['name'], PATHINFO_EXTENSION));

        if(!in_array($ext,['pdf','docx','doc'])){
            $message = "Invalid file type. Only PDF, DOCX, DOC allowed.";
            $message_type = 'danger';
        }else{

            $new_file = time().'_'.preg_replace(
                '/[^A-Za-z0-9.\-_]/',
                '',
                $_FILES['new_main_file']['name']
            );

            $path = "../assets/uploads/".$new_file;

            if(move_uploaded_file($_FILES['new_main_file']['tmp_name'],$path)){

                mysqli_query($conn,"
                    UPDATE clearance_uploads
                    SET filename='$new_file', uploaded_at=NOW()
                    WHERE student_id='$student_id'
                    AND filename='$old_file'
                ");

                mysqli_query($conn,"
                    UPDATE clearance_status
                    SET document_file='$new_file',
                        status='pending'
                    WHERE student_id='$student_id'
                    AND document_file='$old_file'
                ");

                $old_path = "../assets/uploads/".$old_file;
                if(file_exists($old_path)){
                    unlink($old_path);
                }

                updateDashboardStatuses($conn, $student_id);

                $message = "Main file replaced successfully. Your status has been reset to pending for all offices.";
                $message_type = 'success';
            }
        }
    }
    end_replace_main:
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_message_type'] = $message_type;
    echo "<script>
        window.location.href = 'dashboard.php?upload-documents';
    </script>";
    exit();
}

/* ============================== */
$offices_q = getOffices($conn);
$total = mysqli_num_rows($offices_q);

$approved_q = mysqli_query($conn,"
SELECT COUNT(*) as total FROM clearance_status
WHERE student_id='$student_id' AND status='approved'
");

$approved = mysqli_fetch_assoc($approved_q)['total'];
$all_approved = isAllOfficesApproved($conn, $student_id);
$approved_count = getApprovedOfficesCount($conn, $student_id);

$progress = ($total>0) ? round(($approved/$total)*100) : 0;

$main_uploads = mysqli_query($conn,"
SELECT * FROM clearance_uploads WHERE student_id='$student_id'
");

$fyp_data = getFYPFile($conn, $student_id);
$has_fyp = $fyp_data && !empty($fyp_data['fyp_file']);
$dept_approved = isOfficeApproved($conn, $student_id, 'DEPARTMENT');

updateDashboardStatuses($conn, $student_id);

// Check for session messages
$session_message = isset($_SESSION['upload_message']) ? $_SESSION['upload_message'] : '';
$session_message_type = isset($_SESSION['upload_message_type']) ? $_SESSION['upload_message_type'] : '';
unset($_SESSION['upload_message']);
unset($_SESSION['upload_message_type']);
?>

<!-- =========================================================
     UPLOAD PAGE CONTENT - UPDATED WITH MATCHING DESIGN
     This is included inside dashboard.php
========================================================= -->

<style>
/* Matching design styles */
.upload-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}

.page-header h2 {
    font-weight: 700;
    color: #111827;
    font-size: 24px;
    margin: 0;
    font-family: 'Hanken Grotesk', sans-serif;
}

.breadcrumb-custom {
    color: #4b5563;
    font-size: 14px;
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

/* Card styling matching main site */
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

/* Alert styling matching main site */
.alert {
    border-radius: 0.5rem;
    padding: 12px 16px;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
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

.alert-secondary {
    background-color: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}

/* Button styling matching main site */
.btn-primary-custom {
    background-color: #1e40af !important;
    color: #ffffff !important;
    padding: 8px 24px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary-custom:hover {
    background-color: #1e3a8a !important;
    transform: scale(0.98);
}

.btn-primary-custom:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-success-custom {
    background-color: #006c49 !important;
    color: #ffffff !important;
    padding: 6px 20px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.15s ease;
}

.btn-success-custom:hover {
    background-color: #005236 !important;
    transform: scale(0.98);
}

.btn-danger-custom {
    background-color: #dc2626 !important;
    color: #ffffff !important;
    padding: 4px 12px;
    border-radius: 0.375rem;
    border: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s ease;
}

.btn-danger-custom:hover {
    background-color: #b91c1c !important;
    transform: scale(0.95);
}

.btn-warning-custom {
    background-color: #f59e0b !important;
    color: #000000 !important;
    padding: 4px 12px;
    border-radius: 0.375rem;
    border: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s ease;
}

.btn-warning-custom:hover {
    background-color: #d97706 !important;
    transform: scale(0.95);
}

/* Form controls matching main site */
.form-control {
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    padding: 0.75rem 1rem;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
    transition: all 0.15s ease;
}

.form-control:focus {
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    outline: none;
}

.form-control:disabled {
    background-color: #f3f4f6;
    cursor: not-allowed;
}

/* File input styling */
.edit-file-input {
    width: 140px;
    font-size: 12px;
    padding: 4px 8px;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    font-family: 'Hanken Grotesk', sans-serif;
}

/* Table styling matching main site */
.table-custom {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.table-custom thead th {
    background: #f8f9fa;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    border-bottom: 2px solid #e5e7eb;
    text-align: left;
}

.table-custom tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.table-custom tbody tr.locked-row {
    background-color: #f8f9fa;
    opacity: 0.8;
}

.table-custom .file-link {
    color: #1e40af;
    text-decoration: none;
    word-break: break-all;
}

.table-custom .file-link:hover {
    text-decoration: underline;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    font-family: 'Hanken Grotesk', sans-serif;
}

.status-badge-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-badge-pending {
    background: #fef3c7;
    color: #92400e;
}

/* Auto approved box */
.auto-approved-box {
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    border-radius: 0.5rem;
    padding: 16px;
    margin-bottom: 12px;
}

.auto-approved-box .office-title {
    font-weight: 600;
    color: #065f46;
    font-size: 14px;
    margin-bottom: 2px;
}

.auto-approved-box .office-rule {
    color: #065f46;
    font-size: 14px;
    margin-bottom: 0;
}

/* Office upload box */
.office-upload-box {
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 16px;
    margin-bottom: 12px;
}

.office-upload-box .office-title {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
    margin-bottom: 4px;
}

.office-upload-box .office-rule {
    color: #4b5563;
    font-size: 14px;
    margin-bottom: 4px;
}

.office-upload-box .remaining-uploads {
    font-size: 14px;
    color: #111827;
    margin-bottom: 10px;
}

.office-upload-box .remaining-uploads span {
    font-weight: normal;
    color: #4b5563;
}

/* Action cell */
.action-cell {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

/* Upload form */
.upload-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.upload-form .form-control {
    flex: 1;
    min-width: 180px;
}

/* File input wrapper */
.file-input-wrapper {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.file-input-wrapper .form-control {
    flex: 1;
    min-width: 200px;
}

/* Responsive */
@media (max-width: 768px) {
    .upload-page {
        padding: 12px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header h2 {
        font-size: 20px;
    }
    
    .card-custom {
        padding: 16px;
    }
    
    .upload-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .upload-form .form-control {
        min-width: 100%;
    }
    
    .file-input-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .file-input-wrapper .form-control {
        min-width: 100%;
    }
    
    .file-input-wrapper .btn-primary-custom {
        width: 100%;
        justify-content: center;
    }
    
    .action-cell {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-cell form {
        width: 100%;
    }
    
    .edit-file-input {
        width: 100%;
    }
    
    .table-custom {
        font-size: 12px;
    }
    
    .table-custom thead th,
    .table-custom tbody td {
        padding: 8px 10px;
    }
}

@media (max-width: 576px) {
    .table-custom thead {
        display: none;
    }
    
    .table-custom tbody tr {
        display: block;
        margin-bottom: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 8px;
        background: #fff;
    }
    
    .table-custom tbody td {
        display: flex;
        justify-content: space-between;
        padding: 6px 8px !important;
        border-bottom: 1px solid #f0f0f0;
        align-items: center;
        width: 100%;
    }
    
    .table-custom tbody td:last-child {
        border-bottom: none;
    }
    
    .table-custom tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 11px;
        color: #666;
        margin-right: 8px;
        flex-shrink: 0;
    }
    
    .table-custom tbody tr.locked-row {
        background-color: #f8f9fa;
    }
}

/* Small text */
.text-muted {
    color: #4b5563 !important;
}

.text-muted .bi {
    margin-right: 4px;
}
</style>

<div class="upload-page">

    <div class="page-header">
        <h2>Upload Documents</h2>
        <div class="breadcrumb-custom">
            <a href="dashboard.php"><i class="bi bi-house"></i> Home</a> 
            <i class="bi bi-chevron-right"></i> Upload Documents
        </div>
    </div>

    <!-- Alert Message -->
    <?php if($session_message): ?>
        <div class="alert alert-<?php echo $session_message_type; ?> alert-dismissible fade show" role="alert" style="position: sticky; top: 10px; z-index: 100;">
            <i class="bi <?php echo $session_message_type == 'success' ? 'bi-check-circle-fill' : ($session_message_type == 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill'); ?> me-2"></i>
            <?php echo $session_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if(!$all_approved && $approved_count > 0){ ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle"></i>
        <strong>Progress:</strong> <?php echo $approved_count; ?> out of <?php echo $total; ?> offices have approved your request.
        <?php if($approved_count < $total){ ?>
            <span class="badge bg-primary ms-2" style="background-color:#1e40af !important;"><?php echo round(($approved_count/$total)*100); ?>% Complete</span>
        <?php } ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php } ?>

    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle"></i>
        <strong>Important:</strong> 
        <ul class="mb-0 mt-1">
            <li>Each office's requirement files are locked once that office approves.</li>
            <li>The main file is ONLY locked when ALL offices have approved.</li>
            <li>FACULTY, SPORT UNIT, and HALL are auto-approved (no files required).</li>
            <li>BURSAR upload limit is based on your program duration (4, 5, or 6 years).</li>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- MAIN CLEARANCE -->
    <div class="card-custom">
        <h3><i class="bi bi-file-earmark-text"></i> Main Clearance Submission</h3>
        
        <?php if($all_approved){ ?>
            <div class="alert alert-success mb-3">
                <i class="bi bi-check-circle-fill"></i> 
                <strong>All <?php echo $total; ?> offices have approved your request!</strong> The main file is now locked.
            </div>
        <?php } else { ?>
            <div class="alert alert-secondary mb-3">
                <i class="bi bi-info-circle"></i> 
                <strong><?php echo $approved_count; ?> / <?php echo $total; ?></strong> offices approved. 
                Main file is editable until all <?php echo $total; ?> offices approve.
            </div>
        <?php } ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="file-input-wrapper">
                <input type="file" name="clearance_file" class="form-control" <?php echo $all_approved ? 'disabled' : ''; ?> required>
                <button class="btn-primary-custom" name="submit_clearance" <?php echo $all_approved ? 'disabled' : ''; ?>>
                    <i class="bi <?php echo $all_approved ? 'bi-lock-fill' : 'bi-upload'; ?>"></i>
                    <?php echo $all_approved ? 'Fully Locked' : 'Submit / Replace'; ?>
                </button>
            </div>
            <?php if($all_approved){ ?>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-lock-fill"></i> Main file is locked. All <?php echo $total; ?> offices have approved your request.
                </small>
            <?php } else { ?>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-pencil"></i> Main file is editable. <?php echo $approved_count; ?> / <?php echo $total; ?> offices approved.
                </small>
            <?php } ?>
        </form>

        <div class="mt-4">
            <h5 style="font-size:15px; font-weight:600; color:#111827; margin-bottom:12px; font-family:'Hanken Grotesk',sans-serif;">Current Main File</h5>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($main_uploads) > 0){ 
                            while($m = mysqli_fetch_assoc($main_uploads)){
                                $is_fully_locked = $all_approved;
                        ?>
                        <tr class="<?php echo $is_fully_locked ? 'locked-row' : ''; ?>" style="<?php echo $is_fully_locked ? 'background-color:#f8f9fa; opacity:0.8;' : ''; ?>">
                            <td data-label="File">
                                <?php if($is_fully_locked){ ?>
                                    <span class="text-muted"><i class="bi bi-file-earmark"></i> <?php echo $m['filename']; ?></span>
                                <?php } else { ?>
                                    <a target="_blank" href="../assets/uploads/<?php echo $m['filename']; ?>" class="file-link">
                                        <i class="bi bi-file-earmark"></i> <?php echo $m['filename']; ?>
                                    </a>
                                <?php } ?>
                            </td>
                            <td data-label="Date" style="color:#4b5563;"><?php echo $m['uploaded_at']; ?></td>
                            <td data-label="Status">
                                <?php if($all_approved){ ?>
                                    <span class="status-badge status-badge-approved">
                                        <i class="bi bi-lock-fill"></i> Fully Locked
                                    </span>
                                    <small class="text-muted d-block">All <?php echo $total; ?> offices approved</small>
                                <?php } else { ?>
                                    <span class="status-badge status-badge-pending">
                                        <i class="bi bi-pencil"></i> Editable
                                    </span>
                                    <small class="text-muted d-block"><?php echo $approved_count; ?> / <?php echo $total; ?> offices approved</small>
                                <?php } ?>
                            </td>
                            <td data-label="Actions">
                                <?php if(!$is_fully_locked){ ?>
                                <div class="action-cell">
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="main_file" value="<?php echo $m['filename']; ?>">
                                        <button class="btn-danger-custom" name="delete_main_file" onclick="return confirm('Are you sure? This will reset all office statuses to pending.')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                    <form method="POST" enctype="multipart/form-data" style="display:inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="old_main_file" value="<?php echo $m['filename']; ?>">
                                        <input type="file" name="new_main_file" class="edit-file-input" required>
                                        <button class="btn-warning-custom" name="replace_main_file" onclick="return confirm('Replacing will reset all office statuses to pending. Continue?')">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </form>
                                </div>
                                <?php } else { ?>
                                    <span class="text-muted"><i class="bi bi-lock-fill"></i> Fully Locked</span>
                                    <small class="text-muted d-block">All offices approved</small>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php }} else { ?>
                        <tr>
                            <td colspan="4" style="padding:12px 16px; border-bottom:1px solid #e5e7eb; vertical-align:middle; text-align:center; color:#4b5563;">
                                <i class="bi bi-inbox"></i> No main file uploaded yet
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FINAL YEAR PROJECT UPLOAD -->
    <div class="card-custom">
        <h3><i class="bi bi-file-earmark-pdf"></i> Final Year Project Upload</h3>
        
        <?php if($dept_approved || $all_approved){ ?>
            <div class="alert alert-warning mb-3">
                <i class="bi bi-lock-fill"></i>
                <strong><?php echo $dept_approved ? 'Department has approved' : 'All offices have approved'; ?>.</strong> 
                Final Year Project upload is locked.
            </div>
        <?php } else { ?>
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle"></i>
                <strong>Note:</strong> Upload your Final Year Project here. This will be reviewed by the Department office.
            </div>
        <?php } ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="file-input-wrapper">
                <input type="file" name="fyp_file" class="form-control" <?php echo ($dept_approved || $all_approved) ? 'disabled' : ''; ?> required>
                <button class="btn-primary-custom" name="upload_fyp" <?php echo ($dept_approved || $all_approved) ? 'disabled' : ''; ?>>
                    <i class="bi <?php echo ($dept_approved || $all_approved) ? 'bi-lock-fill' : 'bi-upload'; ?>"></i>
                    <?php echo ($dept_approved || $all_approved) ? 'Locked' : 'Upload FYP'; ?>
                </button>
            </div>
            <?php if($dept_approved || $all_approved){ ?>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-lock-fill"></i> Final Year Project upload is locked.
                </small>
            <?php } else { ?>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-upload"></i> Upload your Final Year Project (PDF, DOCX, DOC)
                </small>
            <?php } ?>
        </form>

        <?php if($has_fyp){ ?>
        <div class="mt-4">
            <h5 style="font-size:15px; font-weight:600; color:#111827; margin-bottom:12px; font-family:'Hanken Grotesk',sans-serif;">Current Final Year Project</h5>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="<?php echo ($dept_approved || $all_approved) ? 'locked-row' : ''; ?>" style="<?php echo ($dept_approved || $all_approved) ? 'background-color:#f8f9fa; opacity:0.8;' : ''; ?>">
                            <td data-label="File">
                                <?php if($dept_approved || $all_approved){ ?>
                                    <span class="text-muted"><i class="bi bi-file-earmark"></i> <?php echo $fyp_data['fyp_file']; ?></span>
                                <?php } else { ?>
                                    <a target="_blank" href="../assets/uploads/fyp/<?php echo $fyp_data['fyp_file']; ?>" class="file-link">
                                        <i class="bi bi-file-earmark"></i> <?php echo $fyp_data['fyp_file']; ?>
                                    </a>
                                <?php } ?>
                            </td>
                            <td data-label="Date" style="color:#4b5563;"><?php echo $fyp_data['uploaded_at'] ?? 'N/A'; ?></td>
                            <td data-label="Status">
                                <?php if($dept_approved){ ?>
                                    <span class="status-badge status-badge-approved">
                                        <i class="bi bi-lock-fill"></i> Locked (Dept Approved)
                                    </span>
                                <?php } elseif($all_approved) { ?>
                                    <span class="status-badge status-badge-approved">
                                        <i class="bi bi-lock-fill"></i> Fully Locked
                                    </span>
                                <?php } else { ?>
                                    <span class="status-badge status-badge-pending">
                                        <i class="bi bi-pencil"></i> Editable
                                    </span>
                                <?php } ?>
                            </td>
                            <td data-label="Actions">
                                <?php if(!$dept_approved && !$all_approved){ ?>
                                <div class="action-cell">
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <button class="btn-danger-custom" name="delete_fyp" onclick="return confirm('Delete Final Year Project?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                    <form method="POST" enctype="multipart/form-data" style="display:inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="file" name="fyp_file" class="edit-file-input" required>
                                        <button class="btn-warning-custom" name="upload_fyp">
                                            <i class="bi bi-pencil"></i> Replace
                                        </button>
                                    </form>
                                </div>
                                <?php } else { ?>
                                    <span class="text-muted"><i class="bi bi-lock-fill"></i> Locked</span>
                                <?php } ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>
    </div>

    <!-- OFFICE UPLOADS -->
    <div class="card-custom">
        <h3><i class="bi bi-building"></i> Office Uploads</h3>

        <?php 
        $offices_q = getOffices($conn);
        while($o = mysqli_fetch_assoc($offices_q)){ 
            $office = strtoupper($o['offices_name']);
            
            $q = mysqli_query($conn,"
                SELECT requirement_files, status
                FROM clearance_status
                WHERE student_id='$student_id'
                AND department_role='$office'
            ");
            $row = mysqli_fetch_assoc($q);
            $files = json_decode($row['requirement_files'] ?? '[]', true);
            if(!is_array($files)) $files = [];
            
            $office_status = $row['status'] ?? 'pending';
            $is_approved = strtolower($office_status) == 'approved';
            
            $limit = null;
            if($office === 'ALUMNI RELATION DIVISION') $limit = 1;
            elseif($office === 'DEPARTMENT') $limit = 1;
            elseif($office === 'LIBRARY') $limit = 4;
            elseif($office === 'BURSAR'){
                $limit = getBursarMaxUploads($student);
            }
            
            $used = count($files);
            $remaining = $limit !== null ? $limit - $used : null;
        ?>

        <?php if(in_array($office,['FACULTY', 'SPORT UNIT', 'HALL'])){ ?>
        <div class="auto-approved-box">
            <div class="office-title"><i class="bi bi-check-circle"></i> <?php echo $office; ?></div>
            <div class="office-rule">No upload required (Auto Approved)</div>
            <?php if($is_approved){ ?>
                <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Approved</span>
            <?php } ?>
        </div>
        <?php continue; } ?>

        <div class="office-upload-box <?php echo ($is_approved || $all_approved) ? 'opacity-75' : ''; ?>" style="<?php echo ($is_approved || $all_approved) ? 'opacity:0.75;' : ''; ?>">
            <div class="office-title">
                <i class="bi bi-building"></i> <?php echo $office; ?>
                <?php if($is_approved){ ?>
                    <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i> Approved</span>
                <?php } else { ?>
                    <span class="status-badge status-badge-pending"><i class="bi bi-clock"></i> Pending</span>
                <?php } ?>
            </div>
            <div class="office-rule"><?php echo getOfficeRule($office,$student); ?></div>
            <?php if($limit !== null){ ?>
            <div class="remaining-uploads">Remaining Uploads: <span><?php echo $remaining; ?> / <?php echo $limit; ?></span></div>
            <?php } ?>
            
            <?php if($is_approved){ ?>
                <div class="alert alert-success mt-2 mb-0" style="font-size:13px; padding:10px 14px; border-radius:0.5rem; background:#d1fae5; color:#065f46; border:1px solid #a7f3d0;">
                    <i class="bi bi-lock-fill"></i> This office has approved your request. Uploads are locked.
                </div>
            <?php } elseif($all_approved) { ?>
                <div class="alert alert-warning mt-2 mb-0" style="font-size:13px; padding:10px 14px; border-radius:0.5rem; background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                    <i class="bi bi-lock-fill"></i> All offices have approved. Uploads are locked.
                </div>
            <?php } else { ?>
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="office" value="<?php echo $office; ?>">
                    <input type="file" name="file" class="form-control" required>
                    <button class="btn-success-custom" name="upload_req">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </form>
            <?php } ?>
        </div>

        <?php } ?>
    </div>

    <!-- REQUIREMENTS TABLE -->
    <div class="card-custom">
        <h3><i class="bi bi-file-earmark-check"></i> My Requirement Files (Edit / Delete)</h3>
        
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Office</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $offices_q2 = getOffices($conn);
                    $has_files = false;
                    while($o = mysqli_fetch_assoc($offices_q2)){
                        $office = strtoupper($o['offices_name']);
                        
                        if(in_array($office, ['FACULTY', 'SPORT UNIT', 'HALL'])) continue;
                        
                        $q = mysqli_query($conn,"
                            SELECT requirement_files, status
                            FROM clearance_status
                            WHERE student_id='$student_id'
                            AND department_role='$office'
                        ");
                        $row = mysqli_fetch_assoc($q);
                        if(!$row) continue;
                        
                        $files = json_decode($row['requirement_files'] ?? '[]', true);
                        if(!is_array($files)) $files = [];
                        
                        $office_status = $row['status'] ?? 'pending';
                        $is_approved = strtolower($office_status) == 'approved';
                        
                        foreach($files as $file){
                            $has_files = true;
                    ?>
                    <tr class="<?php echo ($is_approved || $all_approved) ? 'locked-row' : ''; ?>" style="<?php echo ($is_approved || $all_approved) ? 'background-color:#f8f9fa; opacity:0.8;' : ''; ?>">
                        <td class="fw-semibold" data-label="Office" style="padding:12px 16px; border-bottom:1px solid #e5e7eb; vertical-align:middle; font-weight:600;">
                            <?php echo $office; ?>
                            <?php if($is_approved){ ?>
                                <span class="status-badge status-badge-approved"><i class="bi bi-check-circle"></i></span>
                            <?php } ?>
                        </td>
                        <td data-label="File" style="padding:12px 16px; border-bottom:1px solid #e5e7eb; vertical-align:middle;">
                            <?php if($is_approved || $all_approved){ ?>
                                <span class="text-muted">
                                    <i class="bi bi-file-earmark"></i> <?php echo $file; ?>
                                </span>
                            <?php } else { ?>
                                <a target="_blank" href="../assets/uploads/requirements/<?php echo $file; ?>" class="file-link">
                                    <i class="bi bi-file-earmark"></i> <?php echo $file; ?>
                                </a>
                            <?php } ?>
                        </td>
                        <td data-label="Status" style="padding:12px 16px; border-bottom:1px solid #e5e7eb; vertical-align:middle;">
                            <?php if($is_approved){ ?>
                                <span class="status-badge status-badge-approved">
                                    <i class="bi bi-lock-fill"></i> Locked (Office Approved)
                                </span>
                            <?php } elseif($all_approved) { ?>
                                <span class="status-badge status-badge-approved">
                                    <i class="bi bi-lock-fill"></i> Fully Locked
                                </span>
                            <?php } else { ?>
                                <span class="status-badge status-badge-pending">
                                    <i class="bi bi-pencil"></i> Editable
                                </span>
                            <?php } ?>
                        </td>
                        <td data-label="Actions" style="padding:12px 16px; border-bottom:1px solid #e5e7eb; vertical-align:middle;">
                            <?php if(!$is_approved && !$all_approved){ ?>
                            <div class="action-cell">
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="office" value="<?php echo $office; ?>">
                                    <input type="hidden" name="file_name" value="<?php echo $file; ?>">
                                    <button class="btn-danger-custom" name="delete_file" onclick="return confirm('Delete this file? It will reset status to pending.')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                                <form method="POST" enctype="multipart/form-data" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="office" value="<?php echo $office; ?>">
                                    <input type="hidden" name="old_file" value="<?php echo $file; ?>">
                                    <input type="file" name="new_file" class="edit-file-input" required>
                                    <button class="btn-warning-custom" name="replace_file" onclick="return confirm('Replace this file? It will reset status to pending.')">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                </form>
                            </div>
                            <?php } else { ?>
                                <span class="text-muted"><i class="bi bi-lock-fill"></i> Locked</span>
                                <?php if($is_approved){ ?>
                                    <small class="text-muted d-block">This office approved</small>
                                <?php } elseif($all_approved) { ?>
                                    <small class="text-muted d-block">All offices approved</small>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php }} ?>
                    <?php if(!$has_files){ ?>
                    <tr>
                        <td colspan="4" style="padding:12px 16px; border-bottom:1px solid #e5e7eb; vertical-align:middle; text-align:center; color:#4b5563;">
                            <i class="bi bi-inbox"></i> No requirement files uploaded yet
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>