<?php

require_once 'config.php';

// =====================================
// DATABASE CONNECTION CHECK
// =====================================

if(!$conn){

    die("Database Connection Failed");
}

// =====================================
// SANITIZE INPUT
// =====================================

function sanitize($data){

    global $conn;

    return mysqli_real_escape_string(
        $conn,
        trim($data)
    );
}

// =====================================
// FETCH ALL RECORDS
// =====================================

function fetchAll($table){

    global $conn;

    return mysqli_query(
        $conn,
        "SELECT * FROM $table"
    );
}

// =====================================
// FETCH SINGLE CONDITION
// =====================================

function fetchWhere($table, $column, $value){

    global $conn;

    $value = sanitize($value);

    return mysqli_query(
        $conn,
        "SELECT * FROM $table
        WHERE $column='$value'"
    );
}

// =====================================
// DELETE RECORD
// =====================================

function deleteRecord($table, $column, $value){

    global $conn;

    $value = sanitize($value);

    return mysqli_query(
        $conn,
        "DELETE FROM $table
        WHERE $column='$value'"
    );
}

// =====================================
// COUNT RECORDS
// =====================================

function countRecords($table){

    global $conn;

    $query = mysqli_query(
        $conn,
        "SELECT * FROM $table"
    );

    return mysqli_num_rows($query);
}

// =====================================
// COUNT WITH CONDITION
// =====================================

function countWhere($table, $column, $value){

    global $conn;

    $value = sanitize($value);

    $query = mysqli_query(
        $conn,
        "SELECT * FROM $table
        WHERE $column='$value'"
    );

    return mysqli_num_rows($query);
}

// =====================================
// UPDATE RECORD
// =====================================

function updateRecord(
    $table,
    $column,
    $value,
    $condition_column,
    $condition_value
){

    global $conn;

    $value = sanitize($value);

    $condition_value = sanitize($condition_value);

    return mysqli_query(
        $conn,
        "UPDATE $table
        SET $column='$value'
        WHERE $condition_column='$condition_value'"
    );
}

// =====================================
// INSERT RECORD
// =====================================

function insertRecord($query){

    global $conn;

    return mysqli_query($conn, $query);
}

// =====================================
// FETCH SINGLE ROW
// =====================================

function fetchSingle($table, $column, $value){

    global $conn;

    $value = sanitize($value);

    $query = mysqli_query(
        $conn,
        "SELECT * FROM $table
        WHERE $column='$value'
        LIMIT 1"
    );

    return mysqli_fetch_assoc($query);
}

// =====================================
// DATABASE ERROR
// =====================================

function dbError(){

    global $conn;

    return mysqli_error($conn);
}

?>