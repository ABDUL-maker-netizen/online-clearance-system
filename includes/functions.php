
<?php
include_once 'mail.php';

/* =========================
   HELPERS
========================= */

function clean($data){
    return htmlspecialchars(trim($data));
}

function redirect($location){
    header("Location: $location");
    exit();
}

/* =========================
   NOTIFICATION CORE (NEW)
========================= */

function notify($conn, $user_id, $message){

    mysqli_query($conn,
        "INSERT INTO notifications(user_id,message,status,created_at)
         VALUES(
            '$user_id',
            '$message',
            'unread',
            NOW()
         )"
    );
}

/* =========================
   TOTAL OFFICES
========================= */

if (!function_exists('getTotalDepartments')) {

    function getTotalDepartments($conn){

        $result = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total
             FROM offices
             WHERE status='active'"
        );

        if (!$result) {
            return 0;
        }

        $row = mysqli_fetch_assoc($result);

        return $row['total'] ?? 0;
    }
}
/* =========================
   REQUIREMENT VALIDATION
========================= */

function requirementValid($office, $student_id, $conn){

    $office = strtoupper($office);

    if(in_array($office, ['SPORT UNIT','HALL'])){
        return [true, 'Auto-approved office'];
    }

    $q = mysqli_query($conn,"
        SELECT requirement_files
        FROM clearance_status
        WHERE student_id='$student_id'
        AND department_role='$office'
        LIMIT 1
    ");

    $row = mysqli_fetch_assoc($q);

    $files = [];

    if($row && !empty($row['requirement_files'])){
        $files = json_decode($row['requirement_files'], true);
    }

    if(!is_array($files)){
        $files = [];
    }

    if($office == 'DEPARTMENT' && count($files) < 1){
        return [false,'Department clearance form required'];
    }

    if($office == 'ALUMNI RELATION DIVISION' && count($files) < 1){
        return [false,'Alumni receipt required'];
    }

    if($office == 'LIBRARY' && count($files) < 4){
        return [false,'Library requires 4 documents'];
    }

    return [true,'Valid'];
}

/* =========================
   UPDATE CLEARANCE STATUS
   (UPDATED WITH NOTIFICATIONS)
========================= */

function updateClearanceStatus($conn, $student_id, $office, $status){

    // update status
    mysqli_query($conn,"
        UPDATE clearance_status
        SET status='$status'
        WHERE student_id='$student_id'
        AND department_role='$office'
    ");

    // 🔔 NOTIFICATIONS
    if($status == 'approved'){
        notify($conn, $student_id,
        "Your clearance for $office has been APPROVED");
    }

    if($status == 'rejected'){
        notify($conn, $student_id,
        "Your clearance for $office was REJECTED. Please resubmit required documents.");
    }

    // recalc overall status
    updateClearanceCompletion($conn, $student_id);
}

/* =========================
   UPDATE CLEARANCE COMPLETION
========================= */

function updateClearanceCompletion($conn, $student_id){

    $total_offices = getTotalDepartments($conn);

    $latest_query = mysqli_query($conn,
        "SELECT cs1.department_role, cs1.status
         FROM clearance_status cs1
         INNER JOIN (
             SELECT department_role, MAX(id) AS max_id
             FROM clearance_status
             WHERE student_id='$student_id'
             GROUP BY department_role
         ) cs2
         ON cs1.department_role = cs2.department_role
         AND cs1.id = cs2.max_id
         WHERE cs1.student_id='$student_id'"
    );

    $approved = 0;
    $rejected = 0;

    while($row = mysqli_fetch_assoc($latest_query)){

        if($row['status'] == 'approved') $approved++;
        if($row['status'] == 'rejected') $rejected++;
    }

    // if rejected anywhere → pending
    if($rejected > 0){

        mysqli_query($conn,
        "UPDATE students
         SET status='pending'
         WHERE id='$student_id'");

        return;
    }

    // fully cleared
    if($approved >= $total_offices){

        $verification_code = bin2hex(random_bytes(16));

        mysqli_query($conn,
        "UPDATE students
         SET status='cleared',
             verification_code='$verification_code'
         WHERE id='$student_id'");

        $student = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM students WHERE id='$student_id'"));

        // email only once
        if($student && $student['email_sent'] == 0){

            send_notification(
                $student['email'],
                "<h3>Clearance Completed</h3>
                 <p>You can now print your clearance slip.</p>",
                "Clearance Completed"
            );

            mysqli_query($conn,
            "UPDATE students SET email_sent=1 WHERE id='$student_id'");
        }

        // 🔔 FINAL NOTIFICATION
        notify($conn, $student_id,
        "🎉 Clearance Completed Successfully. You can now print your slip.");

        generateStudentQR($student_id);

    } else {

        mysqli_query($conn,
        "UPDATE students SET status='pending'
         WHERE id='$student_id'");
    }
}

/* =========================
   UPDATE DASHBOARD STATUSES (NEW)
   ========================= */

function updateDashboardStatuses($conn, $student_id){
    // Get total offices
    $total_q = mysqli_query($conn,"
        SELECT COUNT(*) as total FROM offices WHERE status='active'
    ");
    $total_data = mysqli_fetch_assoc($total_q);
    $total = $total_data['total'] ?? 0;
    
    // Get approved count
    $approved_q = mysqli_query($conn,"
        SELECT COUNT(*) as approved FROM clearance_status
        WHERE student_id='$student_id'
        AND status='approved'
    ");
    $approved_data = mysqli_fetch_assoc($approved_q);
    $approved = $approved_data['approved'] ?? 0;
    
    // Get rejected count
    $rejected_q = mysqli_query($conn,"
        SELECT COUNT(*) as rejected FROM clearance_status
        WHERE student_id='$student_id'
        AND status='rejected'
    ");
    $rejected_data = mysqli_fetch_assoc($rejected_q);
    $rejected = $rejected_data['rejected'] ?? 0;
    
    // Get pending count
    $pending_q = mysqli_query($conn,"
        SELECT COUNT(*) as pending FROM clearance_status
        WHERE student_id='$student_id'
        AND status='pending'
    ");
    $pending_data = mysqli_fetch_assoc($pending_q);
    $pending = $pending_data['pending'] ?? 0;
    
    // Update student status
    $new_status = ($approved >= $total && $total > 0 && $rejected == 0) ? 'cleared' : 'pending';
    
    mysqli_query($conn,"
        UPDATE students
        SET status='$new_status'
        WHERE id='$student_id'
    ");
    
    // Update clearance_requests progress
    $progress = ($total > 0) ? round(($approved / $total) * 100) : 0;
    $request_status = ($approved >= $total && $total > 0 && $rejected == 0) ? 'cleared' : 'pending';
    
    mysqli_query($conn,"
        UPDATE clearance_requests
        SET progress='$progress',
            status='$request_status'
        WHERE student_id='$student_id'
    ");
    
    return [
        'approved' => $approved,
        'rejected' => $rejected,
        'pending' => $pending,
        'total' => $total,
        'progress' => $progress,
        'status' => $new_status
    ];
}

/* =========================
   QR GENERATION
========================= */

function generateStudentQR($student_id){

    $url = "http://localhost/online-clearance-system/generate_qr.php?id=".$student_id;
    @file_get_contents($url);
}

/* =========================
   LOGS
========================= */

function logAction($conn, $user_id, $user_type, $activity){

    $ip = $_SERVER['REMOTE_ADDR'];

    mysqli_query($conn,
        "INSERT INTO activity_logs(
            user_id,user_type,activity,ip_address,created_at
        ) VALUES(
            '$user_id','$user_type','$activity','$ip',NOW()
        )"
    );
}
?>

