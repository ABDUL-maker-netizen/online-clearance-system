<?php

// =====================================
// GENERAL LOGIN CHECK
// =====================================

function isLoggedIn(){

    if(
        !isset($_SESSION['student_id']) &&
        !isset($_SESSION['admin_id']) &&
        !isset($_SESSION['officer_id']) &&
        !isset($_SESSION['office_officer_id']) &&
        !isset($_SESSION['department_officer_id']) &&
        !isset($_SESSION['faculty_officers_id']) &&
        !isset($_SESSION['senate_officer_id'])
    ){

        header("Location: ../login.php");

        exit();
    }
}

// =====================================
// STUDENT AUTH
// =====================================

function studentAuth(){

    if(!isset($_SESSION['student_id'])){

        header("Location: ../login.php");

        exit();
    }
}

// =====================================
// ADMIN AUTH
// =====================================

function adminAuth(){

    if(!isset($_SESSION['admin_id'])){

        header("Location: ../admin/login.php");

        exit();
    }
}

// =====================================
// OFFICE OFFICER AUTH (BURSAR, LIBRARY, ALUMNI, SPORT, HALL)
// =====================================

function officerAuth(){

    // Check for both old and new session variables
    if(!isset($_SESSION['officer_id']) && !isset($_SESSION['office_officer_id'])){
        header("Location: ../offices/login.php");
        exit();
    }
    
    // If old session exists but new doesn't, set new from old
    if(isset($_SESSION['officer_id']) && !isset($_SESSION['office_officer_id'])){
        $_SESSION['office_officer_id'] = $_SESSION['officer_id'];
    }
    
    // If new session exists but old doesn't, set old from new
    if(isset($_SESSION['office_officer_id']) && !isset($_SESSION['officer_id'])){
        $_SESSION['officer_id'] = $_SESSION['office_officer_id'];
    }
}

// =====================================
// DEPARTMENT OFFICER AUTH
// =====================================

function departmentOfficerAuth(){

    if(!isset($_SESSION['department_officer_id'])){
        header("Location: ../offices/departments/login.php");
        exit();
    }
}

// =====================================
// FACULTY OFFICER AUTH
// =====================================

function facultyOfficerAuth(){

    if(!isset($_SESSION['faculty_officers_id'])){
        header("Location: ../offices/faculty/login.php");
        exit();
    }
}

// =====================================
// SENATE OFFICER AUTH
// =====================================

function senateAuth(){

    if(!isset($_SESSION['senate_officer_id'])){
        header("Location: ../senate/login.php");
        exit();
    }
}

// =====================================
// SPECIFIC OFFICE AUTH
// =====================================

function officeAuth($office){

    // Check for both old and new session variables
    $session_office = isset($_SESSION['office']) ? $_SESSION['office'] : (isset($_SESSION['office_name']) ? $_SESSION['office_name'] : null);

    if(
        !isset($_SESSION['officer_id']) &&
        !isset($_SESSION['office_officer_id'])
    ){
        echo "
        <div style='
        padding:20px;
        background:red;
        color:white;
        text-align:center;
        font-family:Arial,sans-serif;
        '>
        ACCESS DENIED - Not logged in
        </div>
        ";
        exit();
    }

    if($session_office != $office){
        echo "
        <div style='
        padding:20px;
        background:red;
        color:white;
        text-align:center;
        font-family:Arial,sans-serif;
        '>
        ACCESS DENIED - This office is not authorized
        </div>
        ";
        exit();
    }
}

// =====================================
// CHECK IF USER IS OFFICE OFFICER
// =====================================

function isOfficeOfficer(){

    // Check for both old and new session variables
    if(isset($_SESSION['officer_id']) || isset($_SESSION['office_officer_id'])){
        return true;
    }
    return false;
}

// =====================================
// CHECK IF USER IS DEPARTMENT OFFICER
// =====================================

function isDepartmentOfficer(){

    if(isset($_SESSION['department_officer_id'])){
        return true;
    }
    return false;
}

// =====================================
// CHECK IF USER IS FACULTY OFFICER
// =====================================

function isFacultyOfficer(){

    if(isset($_SESSION['faculty_officers_id'])){
        return true;
    }
    return false;
}

// =====================================
// CHECK IF USER IS SENATE OFFICER
// =====================================

function isSenateOfficer(){

    if(isset($_SESSION['senate_officer_id'])){
        return true;
    }
    return false;
}

// =====================================
// GET CURRENT OFFICE NAME
// =====================================

function getCurrentOffice(){

    if(isset($_SESSION['office'])){
        return $_SESSION['office'];
    }
    
    if(isset($_SESSION['office_name'])){
        return $_SESSION['office_name'];
    }
    
    return null;
}

// =====================================
// GET CURRENT OFFICER ID
// =====================================

function getCurrentOfficerId(){

    if(isset($_SESSION['officer_id'])){
        return $_SESSION['officer_id'];
    }
    
    if(isset($_SESSION['office_officer_id'])){
        return $_SESSION['office_officer_id'];
    }
    
    return null;
}

// =====================================
// GET CURRENT OFFICER ROLE
// =====================================

function getCurrentRole(){

    if(isset($_SESSION['role'])){
        return $_SESSION['role'];
    }
    
    if(isset($_SESSION['office_role'])){
        return $_SESSION['office_role'];
    }
    
    return null;
}

// =====================================
// CHECK IF USER HAS SIGNATURE
// =====================================

function hasDigitalSignature($conn, $officer_id){

    $query = mysqli_query($conn,"
        SELECT digital_signature
        FROM officers
        WHERE id='$officer_id'
        LIMIT 1
    ");

    $row = mysqli_fetch_assoc($query);
    
    return !empty($row['digital_signature']);
}
?>