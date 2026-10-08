<?php

// =====================================
// PREVENT XSS
// =====================================

function xss_clean($data){

    return htmlspecialchars(
        strip_tags(trim($data)),
        ENT_QUOTES,
        'UTF-8'
    );
}

// =====================================
// VALIDATE EMAIL
// =====================================

function validate_email($email){

    return filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    );
}

// =====================================
// STRONG PASSWORD CHECK
// =====================================

function strong_password($password){

    // MINIMUM 8 CHARACTERS

    if(strlen($password) < 8){

        return false;
    }

    // UPPERCASE

    if(!preg_match('/[A-Z]/', $password)){

        return false;
    }

    // LOWERCASE

    if(!preg_match('/[a-z]/', $password)){

        return false;
    }

    // NUMBER

    if(!preg_match('/[0-9]/', $password)){

        return false;
    }

    return true;
}

// =====================================
// VALIDATE IMAGE
// =====================================

function validate_image($file){

    $allowed = ['jpg','jpeg','png','pdf'];

    $extension = strtolower(
        pathinfo($file, PATHINFO_EXTENSION)
    );

    if(in_array($extension, $allowed)){

        return true;
    }

    return false;
}

// =====================================
// GENERATE TOKEN
// =====================================

function generate_token($length = 32){

    return bin2hex(
        random_bytes($length)
    );
}

// =====================================
// USER IP
// =====================================

function user_ip(){

    if(!empty($_SERVER['HTTP_CLIENT_IP'])){

        return $_SERVER['HTTP_CLIENT_IP'];

    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){

        return $_SERVER['HTTP_X_FORWARDED_FOR'];

    } else {

        return $_SERVER['REMOTE_ADDR'];
    }
}

// =====================================
// LOGIN ATTEMPTS
// =====================================

function login_attempt_check(){

    if(!isset($_SESSION['login_attempt'])){

        $_SESSION['login_attempt'] = 0;
    }

    if($_SESSION['login_attempt'] >= 5){

        die("Too Many Login Attempts");
    }
}

// =====================================
// INCREASE ATTEMPT
// =====================================

function increase_login_attempt(){

    $_SESSION['login_attempt'] += 1;
}

// =====================================
// RESET ATTEMPTS
// =====================================

function reset_login_attempt(){

    $_SESSION['login_attempt'] = 0;
}

// =====================================
// SECURE FILE NAME
// =====================================

function secure_file_name($file){

    $file = preg_replace(
        "/[^a-zA-Z0-9.]/",
        "_",
        $file
    );

    return time().'_'.$file;
}

?>