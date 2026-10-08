<?php
// No need to start session here - dashboard.php already handles it

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

$message = "";

if(isset($_POST['change_password'])){

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $student = mysqli_fetch_assoc(
        mysqli_query($conn,
        "SELECT * FROM students
        WHERE id='$student_id'")
    );

    if(!password_verify(
        $current_password,
        $student['password']
    )){

        $message = "<div class='alert alert-danger'>
        Current password is incorrect.
        </div>";

    }elseif($new_password != $confirm_password){

        $message = "<div class='alert alert-danger'>
        New passwords do not match.
        </div>";

    }elseif(strlen($new_password) < 6){

        $message = "<div class='alert alert-danger'>
        New password must be at least 6 characters long.
        </div>";

    }elseif(!preg_match('/[a-z]/', $new_password)){

        $message = "<div class='alert alert-danger'>
        New password must contain at least 1 lowercase letter.
        </div>";

    }elseif(!preg_match('/[A-Z]/', $new_password)){

        $message = "<div class='alert alert-danger'>
        New password must contain at least 1 uppercase letter.
        </div>";

    }elseif(!preg_match('/[0-9]/', $new_password)){

        $message = "<div class='alert alert-danger'>
        New password must contain at least 1 number.
        </div>";

    }else{

        $hashed_password =
        password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        mysqli_query($conn,
        "UPDATE students
        SET password='$hashed_password'
        WHERE id='$student_id'");

        // Send email notification
        $to = $student['email'];
        $subject = "Password Changed Successfully - ATBU Clearance System";
        
        $email_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 5px 5px; }
                .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px; }
                .alert { background: #d1fae5; padding: 15px; border-radius: 5px; border-left: 4px solid #10b981; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔐 Password Changed</h2>
                </div>
                <div class='content'>
                    <div class='alert'>
                        <p><strong>Hello " . htmlspecialchars($student['fullname']) . ",</strong></p>
                        <p>Your password has been successfully changed.</p>
                    </div>
                    <p><strong>Account Details:</strong></p>
                    <ul>
                        <li><strong>Name:</strong> " . htmlspecialchars($student['fullname']) . "</li>
                        <li><strong>Registration Number:</strong> " . htmlspecialchars($student['reg_number']) . "</li>
                        <li><strong>Email:</strong> " . htmlspecialchars($student['email']) . "</li>
                    </ul>
                    <p>If you did not make this change, please contact the system administrator immediately.</p>
                    <p style='text-align: center;'>
                        <a href='http://localhost/online-clearance-system/login.php' style='display: inline-block; background: #1e40af; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;'>
                            Login Now
                        </a>
                    </p>
                    <p><small>This is an automated notification. Please do not reply to this email.</small></p>
                </div>
                <div class='footer'>
                    <p>&copy; 2024 Abubakar Tafawa Balewa University. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Include mail function if not already included
        if (!function_exists('send_notification')) {
            include_once '../includes/mail.php';
        }

        $email_sent = send_notification($to, $email_message, $subject);

        if($email_sent){
            $message = "<div class='alert alert-success'>
            <strong>✅ Password changed successfully!</strong><br>
            A confirmation email has been sent to <strong>" . htmlspecialchars($student['email']) . "</strong>.
            </div>";
        } else {
            $message = "<div class='alert alert-success'>
            <strong>✅ Password changed successfully!</strong><br>
            <small>We encountered an issue sending the confirmation email, but your password has been updated.</small>
            </div>";
        }
    }
}
?>

<!-- =========================================================
     CHANGE PASSWORD PAGE CONTENT - UPDATED WITH MATCHING DESIGN
     This is included inside dashboard.php
========================================================= -->

<!-- ✅ IMPORTANT: Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
/* Matching design styles */
.change-password-page {
    max-width: 1280px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header-password {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.page-header-password .header-icon {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    background-color: rgba(30, 64, 175, 0.1);
    color: #1e40af;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-password .header-icon i {
    font-size: 2rem;
    line-height: 1;
}

.page-header-password h1 {
    font-size: clamp(24px, 3vw, 32px);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.02em;
    color: #111827;
    margin: 0;
    font-family: 'Hanken Grotesk', sans-serif;
}

.page-header-password p {
    font-size: clamp(14px, 1.2vw, 16px);
    font-weight: 400;
    line-height: 1.4;
    color: #4b5563;
    margin-top: 4px;
    margin-bottom: 0;
}

/* Card styling */
.card-custom {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: clamp(16px, 2vw, 24px);
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

/* Alert styling */
.alert {
    border-radius: 0.5rem;
    padding: 12px 16px;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
    margin-bottom: 20px;
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

/* Password Tips Box */
.password-tips {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 12px 16px;
    margin-bottom: 20px;
    border-left: 4px solid #1e40af;
}

.password-tips strong {
    color: #111827;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.password-tips ul {
    margin: 4px 0 0 0;
    padding-left: 20px;
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
}

.password-tips ul li {
    margin-bottom: 2px;
}

/* Password wrapper with eye icon */
.password-wrapper {
    position: relative;
}

.password-wrapper .form-control {
    padding-right: 3rem;
}

.password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0.5rem;
    font-size: 1.1rem;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.password-toggle:hover {
    color: #1e40af;
}

.password-toggle:focus {
    outline: none;
}

.password-toggle i {
    pointer-events: none;
}

/* Form controls */
.form-control {
    display: block;
    width: 100%;
    padding: 8px 40px 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-sizing: border-box;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
    transition: all 0.15s ease;
}

.form-control:focus {
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    outline: none;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #111827;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
}

/* Password requirements list */
.password-requirements {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0 0;
    font-size: 0.85rem;
    color: #6b7280;
    font-family: 'Hanken Grotesk', sans-serif;
}

.password-requirements li {
    padding: 0.15rem 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.password-requirements li .req-icon {
    width: 1.1rem;
    text-align: center;
    font-size: 0.75rem;
}

.password-requirements li.valid {
    color: #10b981;
}

.password-requirements li.invalid {
    color: #ef4444;
}

/* Buttons */
.btn-change-password {
    background: #1e40af;
    color: #ffffff;
    padding: 8px 24px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 500;
    transition: all 0.15s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
}

.btn-change-password:hover:not(:disabled) {
    background: #1e3a8a;
    transform: scale(0.98);
}

.btn-change-password:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-back {
    background: #4b5563;
    color: #ffffff;
    padding: 8px 20px;
    border-radius: 0.5rem;
    border: none;
    font-weight: 500;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Hanken Grotesk', sans-serif;
    font-size: 14px;
}

.btn-back:hover {
    background: #374151;
    color: #ffffff;
    text-decoration: none;
    transform: scale(0.98);
}

/* Button group */
.button-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .change-password-page {
        padding: 12px;
    }
    
    .page-header-password {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .page-header-password h1 {
        font-size: 24px;
    }
    
    .page-header-password p {
        font-size: 14px;
    }
    
    .card-custom {
        padding: 16px;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .button-group .btn-change-password,
    .button-group .btn-back {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .page-header-password h1 {
        font-size: 20px;
    }
    
    .page-header-password .header-icon {
        width: 40px;
        height: 40px;
    }
    
    .page-header-password .header-icon i {
        font-size: 1.5rem;
    }
    
    .card-custom {
        padding: 12px;
    }
    
    .form-control {
        font-size: 16px; /* Prevents iOS zoom */
        padding: 8px 36px 8px 12px;
    }
    
    .password-toggle {
        padding: 0.35rem;
        font-size: 1rem;
    }
    
    .password-tips {
        padding: 10px 12px;
    }
    
    .password-tips ul {
        font-size: 13px;
        padding-left: 16px;
    }
    
    .password-requirements {
        font-size: 12px;
    }
}

@media (max-width: 400px) {
    .page-header-password h1 {
        font-size: 18px;
    }
    
    .password-tips ul {
        font-size: 12px;
    }
    
    .btn-change-password,
    .btn-back {
        font-size: 13px;
        padding: 6px 16px;
    }
}

/* Animation for requirements */
.password-requirements li {
    transition: color 0.2s ease;
}

.password-requirements li .req-icon i {
    transition: all 0.2s ease;
}

/* Breadcrumb */
.breadcrumb-custom {
    color: #4b5563;
    font-size: 14px;
    font-family: 'Hanken Grotesk', sans-serif;
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
</style>

<div class="change-password-page">

    <!-- Page Header -->
    <div class="page-header-password">
        <div class="header-icon">
            <i class="bi bi-key"></i>
        </div>
        <div style="flex:1; min-width:150px;">
            <h1>Change Password</h1>
            <p>Update your account password for better security.</p>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card-custom">

        <?php echo $message; ?>

        <!-- Password Tips -->
        <div class="password-tips">
            <strong><i class="bi bi-info-circle"></i> Password Requirements:</strong>
            <ul>
                <li>Minimum 6 characters long</li>
                <li>At least 1 lowercase letter</li>
                <li>At least 1 uppercase letter</li>
                <li>At least 1 number</li>
                <li>Use a mix of letters, numbers, and symbols for better security</li>
            </ul>
        </div>

        <form method="POST" id="changePasswordForm">

            <div style="margin-bottom:16px;">
                <label class="form-label">Current Password</label>
                <div class="password-wrapper">
                    <input type="password"
                           name="current_password"
                           id="current_password"
                           class="form-control"
                           required>
                    <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label class="form-label">New Password</label>
                <div class="password-wrapper">
                    <input type="password"
                           name="new_password"
                           id="new_password"
                           class="form-control"
                           required>
                    <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <!-- Password requirements list -->
                <ul class="password-requirements" id="passwordRequirements">
                    <li id="reqLength"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 6 characters</li>
                    <li id="reqLower"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 lowercase letter</li>
                    <li id="reqUpper"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 uppercase letter</li>
                    <li id="reqNumber"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 number</li>
                </ul>
            </div>

            <div style="margin-bottom:20px;">
                <label class="form-label">Confirm New Password</label>
                <div class="password-wrapper">
                    <input type="password"
                           name="confirm_password"
                           id="confirm_password"
                           class="form-control"
                           required>
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)" aria-label="Toggle password visibility">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="button-group">
                <button type="submit"
                        name="change_password"
                        id="changePasswordBtn"
                        class="btn-change-password"
                        disabled>
                    <i class="bi bi-check2-circle"></i> Change Password
                </button>
                <a href="dashboard.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

        </form>

    </div>

</div>

<script>
(function() {
    'use strict';

    // --- Password toggle function using Bootstrap Icons ---
    window.togglePassword = function(inputId, button) {
        var input = document.getElementById(inputId);
        var icon = button.querySelector('i');
        if (input.getAttribute('type') === 'password') {
            input.setAttribute('type', 'text');
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.setAttribute('type', 'password');
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    };

    // --- Password validation with real-time feedback ---
    var newPasswordInput = document.getElementById('new_password');
    var reqLength = document.getElementById('reqLength');
    var reqLower = document.getElementById('reqLower');
    var reqUpper = document.getElementById('reqUpper');
    var reqNumber = document.getElementById('reqNumber');
    var changePasswordBtn = document.getElementById('changePasswordBtn');

    // Helper to update requirement status
    function updateRequirement(element, isValid) {
        var icon = element.querySelector('.req-icon i');
        if (isValid) {
            element.classList.remove('invalid');
            element.classList.add('valid');
            icon.className = 'bi bi-check-circle-fill';
        } else {
            element.classList.remove('valid');
            element.classList.add('invalid');
            icon.className = 'bi bi-circle';
        }
    }

    function validatePassword() {
        var pwd = newPasswordInput.value;

        // At least 6 characters
        var lenValid = pwd.length >= 6;
        updateRequirement(reqLength, lenValid);

        // At least 1 lowercase letter
        var lowerValid = /[a-z]/.test(pwd);
        updateRequirement(reqLower, lowerValid);

        // At least 1 uppercase letter
        var upperValid = /[A-Z]/.test(pwd);
        updateRequirement(reqUpper, upperValid);

        // At least 1 number
        var numberValid = /\d/.test(pwd);
        updateRequirement(reqNumber, numberValid);

        // All requirements met
        var allValid = lenValid && lowerValid && upperValid && numberValid;
        changePasswordBtn.disabled = !allValid;
        changePasswordBtn.style.opacity = allValid ? '1' : '0.6';
        changePasswordBtn.style.cursor = allValid ? 'pointer' : 'not-allowed';

        return allValid;
    }

    // Validate on input
    newPasswordInput.addEventListener('input', validatePassword);

    // Also validate on page load (in case browser autofills)
    window.addEventListener('DOMContentLoaded', function() {
        validatePassword();
    });

    // Additional check before form submit (enforce)
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        var currentPwd = document.getElementById('current_password').value;
        var newPwd = document.getElementById('new_password').value;
        var confirmPwd = document.getElementById('confirm_password').value;

        // Check if all password requirements are met
        if (!validatePassword()) {
            e.preventDefault();
            alert('Please ensure your new password meets all requirements:\n\n• At least 6 characters\n• At least 1 lowercase letter\n• At least 1 uppercase letter\n• At least 1 number');
            return false;
        }

        // Check if current password is empty
        if (currentPwd.length === 0) {
            e.preventDefault();
            alert('Please enter your current password.');
            return false;
        }

        // Check if passwords match
        if (newPwd !== confirmPwd) {
            e.preventDefault();
            alert('New passwords do not match. Please re-enter your password.');
            return false;
        }
    });

})();
</script>


