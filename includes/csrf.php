<?php

// START SESSION

if(session_status() == PHP_SESSION_NONE){

    session_start();
}

// =====================================
// GENERATE CSRF TOKEN
// =====================================

if(empty($_SESSION['csrf_token'])){

    $_SESSION['csrf_token'] =
    bin2hex(random_bytes(32));
}

// =====================================
// GET CSRF TOKEN
// =====================================

function csrf_token(){

    return $_SESSION['csrf_token'];
}

// =====================================
// VERIFY CSRF TOKEN
// =====================================

function verify_csrf(){

    if(
        !isset($_POST['csrf_token']) ||

        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ){

        die("CSRF Token Validation Failed");
    }
}

// =====================================
// REGENERATE TOKEN
// =====================================

function regenerate_csrf(){

    $_SESSION['csrf_token'] =
    bin2hex(random_bytes(32));
}

?>