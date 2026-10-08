<?php

// START SESSION

if(session_status() == PHP_SESSION_NONE){

    session_start();
}

// =====================================
// STUDENT SESSION CHECK
// =====================================

function student_session(){

    if(!isset($_SESSION['student_id'])){

        header("Location: ../login.php");

        exit();
    }
}

// =====================================
// ADMIN SESSION CHECK
// =====================================

function admin_session(){

    if(!isset($_SESSION['admin_id'])){

        header("Location: ../admin/login.php");

        exit();
    }
}

// =====================================
// DEPARTMENT OFFICER SESSION CHECK
// =====================================

function department_session(){

    if(!isset($_SESSION['officer_id'])){

        header("Location: ../department/login.php");

        exit();
    }
}

// =====================================
// SESSION SECURITY
// =====================================

// AUTO LOGOUT AFTER 30 MINUTES

if(isset($_SESSION['LAST_ACTIVITY'])){

    if(time() - $_SESSION['LAST_ACTIVITY'] > 1800){

        session_unset();

        session_destroy();

        header("Location: ../login.php");

        exit();
    }
}

// UPDATE LAST ACTIVITY

$_SESSION['LAST_ACTIVITY'] = time();

// =====================================
// SESSION REGENERATION
// =====================================

if(!isset($_SESSION['CREATED'])){

    $_SESSION['CREATED'] = time();

} else if(time() - $_SESSION['CREATED'] > 1800){

    session_regenerate_id(true);

    $_SESSION['CREATED'] = time();
}

?>