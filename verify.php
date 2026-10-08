<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php'; // ADD THIS - needed for getTotalDepartments()

/* ===============================
   GET PARAMETERS
=============================== */
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$verification_code = isset($_GET['code']) ? mysqli_real_escape_string($conn, $_GET['code']) : '';

/* ===============================
   VERIFY STUDENT
=============================== */
if ($student_id > 0 && !empty($verification_code)) {
    
    $query = mysqli_query($conn, "
        SELECT s.*, 
               COUNT(cs.id) as approved_count,
               (SELECT COUNT(*) FROM clearance_status WHERE student_id = s.id AND status = 'approved') as total_approved
        FROM students s
        LEFT JOIN clearance_status cs ON s.id = cs.student_id AND cs.status = 'approved'
        WHERE s.id = '$student_id' 
        AND s.verification_code = '$verification_code'
        GROUP BY s.id
    ");
    
    $student = mysqli_fetch_assoc($query);
    
    if ($student) {
        // Student found - display information
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Student Verification - <?php echo htmlspecialchars($student['fullname']); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                body {
                    background: #f8f9fa;
                    font-family: 'Inter', sans-serif;
                }
                .verification-card {
                    max-width: 800px;
                    margin: 50px auto;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    padding: 40px;
                }
                .verified-badge {
                    background: #28a745;
                    color: white;
                    padding: 8px 24px;
                    border-radius: 50px;
                    display: inline-block;
                    font-weight: 600;
                }
                .student-photo {
                    width: 120px;
                    height: 120px;
                    border-radius: 50%;
                    object-fit: cover;
                    border: 4px solid #28a745;
                }
                .info-label {
                    color: #6c757d;
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                .info-value {
                    font-size: 18px;
                    font-weight: 500;
                    color: #0b1c30;
                }
                .status-badge {
                    padding: 4px 16px;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 14px;
                }
                .status-cleared {
                    background: #d4edda;
                    color: #155724;
                }
                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                }
                .error-icon {
                    font-size: 64px;
                    display: block;
                    margin-bottom: 20px;
                }
                @media (max-width: 768px) {
                    .verification-card {
                        margin: 20px 15px;
                        padding: 20px;
                    }
                    .student-photo {
                        width: 100px;
                        height: 100px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="verification-card">
                    <div class="text-center mb-4">
                        <?php 
                        $passport_path = 'assets/uploads/' . htmlspecialchars($student['passport'] ?? 'default.jpg');
                        if (!file_exists($passport_path)) {
                            $passport_path = 'assets/uploads/default.jpg';
                        }
                        ?>
                        <img src="<?php echo $passport_path; ?>" 
                             alt="Student Photo" 
                             class="student-photo mb-3"
                             onerror="this.src='https://via.placeholder.com/120x120/0b1c30/ffffff?text=No+Photo'">
                        <h2 class="mb-1"><?php echo htmlspecialchars($student['fullname']); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars($student['reg_number']); ?></p>
                        <span class="verified-badge">
                            <i class="bi bi-check-circle-fill"></i> Verified Student
                        </span>
                    </div>

                    <hr>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <p class="info-label">Student ID</p>
                            <p class="info-value"><?php echo htmlspecialchars($student['reg_number']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="info-label">Full Name</p>
                            <p class="info-value"><?php echo htmlspecialchars($student['fullname']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="info-label">Faculty</p>
                            <p class="info-value"><?php echo htmlspecialchars($student['faculty_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="info-label">Department</p>
                            <p class="info-value"><?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="info-label">Programme</p>
                            <p class="info-value"><?php echo htmlspecialchars($student['programme'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="info-label">Status</p>
                            <p class="info-value">
                                <?php 
                                $status = strtolower($student['status'] ?? 'pending');
                                $badge_class = ($status == 'cleared') ? 'status-cleared' : 'status-pending';
                                ?>
                                <span class="status-badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-12">
                            <p class="info-label">Clearance Progress</p>
                            <?php 
                            // FIXED: Use the function from functions.php
                            $total_offices = getTotalDepartments($conn);
                            $progress = ($total_offices > 0) ? round(($student['total_approved'] / $total_offices) * 100) : 0;
                            ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $progress; ?>%;" 
                                     aria-valuenow="<?php echo $progress; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                            <p class="text-muted mt-2">
                                <?php echo $student['total_approved']; ?> out of <?php echo $total_offices; ?> offices approved
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center mt-3">
                        <p class="text-muted small">
                            <i class="bi bi-shield-check"></i> 
                            Verified on <?php echo date('F d, Y h:i A'); ?>
                        </p>
                        <p class="text-muted small">
                            <i class="bi bi-info-circle"></i> 
                            This is an official verification from the University Clearance System
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    } else {
        // Invalid or not found
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invalid Verification</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background: #f8f9fa;
                    font-family: 'Inter', sans-serif;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                }
                .error-card {
                    max-width: 500px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 16px;
                    padding: 40px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .error-icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-card">
                    <div class="error-icon">🔒</div>
                    <h3 class="text-danger mb-3">Invalid Verification</h3>
                    <p class="text-muted">The QR code you scanned is invalid or has expired.</p>
                    <p class="text-muted small">Please contact the university administration for assistance.</p>
                    <a href="index.php" class="btn btn-primary mt-3">Go to Home</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
} else {
    // Missing parameters
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Request</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: #f8f9fa;
                font-family: 'Inter', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
            }
            .error-card {
                max-width: 500px;
                margin: 0 auto;
                background: white;
                border-radius: 16px;
                padding: 40px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                text-align: center;
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h3 class="text-warning mb-3">Invalid Request</h3>
                <p class="text-muted">Missing verification parameters.</p>
                <a href="index.php" class="btn btn-primary mt-3">Go to Home</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>