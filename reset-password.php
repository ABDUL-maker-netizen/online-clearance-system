<?php

include 'includes/config.php';
include 'includes/csrf.php';

$message = "";

// ======================================
// CHECK RESET TOKEN
// ======================================

if(isset($_GET['token'])){

    $token = mysqli_real_escape_string(
        $conn,
        $_GET['token']
    );

    // CHECK TOKEN + EXPIRY

    $check = mysqli_query($conn,

    "SELECT * FROM students

    WHERE reset_token='$token'

    AND reset_token_expiry > NOW()");

    if(mysqli_num_rows($check) == 0){

        die("Invalid or Expired Token");
    }

}else{

    die("No Token Found");
}

// ======================================
// RESET PASSWORD
// ======================================

if(isset($_POST['reset'])){

    // VERIFY CSRF TOKEN HERE
    verify_csrf();

    // VALIDATE PASSWORD

    if(empty($_POST['password'])){

        $message = "

        <div class='alert alert-danger'>

        Password is required.

        </div>

        ";

    }else{

        $password = password_hash(
            $_POST['password'],
            PASSWORD_DEFAULT
        );

        $update = mysqli_query($conn,

        "UPDATE students

        SET password='$password',
        reset_token='',
        reset_token_expiry=NULL

        WHERE reset_token='$token'");

        if($update){

            $message = "

            <div class='alert alert-success'>

            Password Updated Successfully.

            <br><br>

            <a href='login.php'
            class='btn btn-success'>

            Login Now

            </a>

            </div>

            ";

        }else{

            $message = "

            <div class='alert alert-danger'>

            Failed to update password.

            </div>

            ";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password | Online Clearance System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- ✅ IMPORTANT: Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <link rel="icon" type="imagejfif" sizes="16x16" href="assets/images/atbu-logo.jfif">
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Reset and Base */
        body {
            font-family: 'Hanken Grotesk', sans-serif;
            background-color: #f8f9ff;
            color: #111827;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        main {
            flex: 1;
        }

        /* Typography */
        .text-primary-custom {
            color: #1e40af !important;
        }
        .bg-primary-custom {
            background-color: #1e40af !important;
        }
        .bg-primary-hover:hover {
            background-color: #1e3a8a !important;
        }
        .text-on-surface-variant {
            color: #4b5563 !important;
        }
        .border-outline-variant {
            border-color: #e5e7eb !important;
        }

        /* Navbar */
        .navbar-custom {
            background-color: rgba(248, 249, 255, 0.9) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            padding: 1rem 2rem;
        }
        .navbar-brand-custom {
            font-weight: 700;
            font-size: 1.25rem;
            color: #1e40af !important;
            letter-spacing: -0.025em;
        }
        .nav-link-custom {
            color: #4b5563 !important;
            font-weight: 500;
            padding: 0.5rem 0 !important;
            transition: color 0.15s ease-in-out;
        }
        .nav-link-custom:hover {
            color: #1e40af !important;
        }
        .nav-link-custom.active {
            color: #1e40af !important;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 0.25rem !important;
        }

        /* Buttons */
        .btn-login {
            color: #1e40af !important;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
        }
        .btn-login:hover {
            background-color: rgba(30, 64, 175, 0.1) !important;
        }
        .btn-register {
            background-color: #1e40af !important;
            color: white !important;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            text-decoration: none;
        }
        .btn-register:hover {
            background-color: #1e3a8a !important;
            transform: scale(0.95);
        }

        /* Hero Section - Centered layout */
        .hero-section {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 2rem 0;
        }
        .hero-background {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .hero-background img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.4);
            mix-blend-mode: multiply;
        }
        .hero-gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, transparent 70%, #f8f9ff 100%);
        }
        .hero-content {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1280px;
            padding: 0 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2.5rem;
        }

        /* Logo - Centered */
        .logo-wrapper {
            width: 8rem;
            height: 8rem;
            border-radius: 9999px;
            background-color: white;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 0.5rem;
            border: 4px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            flex-shrink: 0;
            margin: 0 auto;
        }
        .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        @media (min-width: 768px) {
            .logo-wrapper {
                width: 10rem;
                height: 10rem;
            }
        }

        /* Hero Text - Centered */
        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.025em;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0;
            line-height: 1.2;
            text-align: center;
        }
        .hero-subtitle {
            font-size: 1.25rem;
            color: rgba(229, 231, 235, 1);
            font-weight: 500;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0;
            text-align: center;
        }
        @media (min-width: 992px) {
            .hero-title {
                font-size: 3.5rem;
            }
            .hero-subtitle {
                font-size: 1.5rem;
            }
        }

        /* Hero Header Section - Logo + Text centered */
        .hero-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
        }

        /* Form Card - Centered */
        .hero-form-column {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        /* Form Card */
        .form-card {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(229, 231, 235, 0.5);
            width: 100%;
        }
        .form-card h2 {
            color: #1e40af;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-card .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.15s ease-in-out;
        }
        .form-card .form-control:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .form-card .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .form-card .btn-submit {
            background-color: #1e40af;
            color: white;
            font-weight: 700;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: none;
            width: 100%;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .form-card .btn-submit:hover:not(:disabled) {
            background-color: #1e3a8a;
            transform: scale(0.98);
        }
        .form-card .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-card .login-link {
            color: #1e40af;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        .form-card .login-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }
        .form-card .extra-links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .form-card .extra-links a {
            color: #1e40af;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        .form-card .extra-links a:hover {
            color: #1e3a8a;
            text-decoration: underline;
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

        /* Password requirements list */
        .password-requirements {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
            font-size: 0.85rem;
            color: #6b7280;
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

        /* Footer */
        .footer-custom {
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 2.5rem 2rem;
            margin-top: auto;
            position: relative;
            z-index: 20;
        }
        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
        }
        .footer-brand {
            font-weight: 600;
            font-size: 1.125rem;
            color: #111827;
        }
        .footer-text {
            color: #4b5563;
            font-size: 0.875rem;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .hero-content {
                gap: 2rem;
                padding: 1rem;
            }
            .hero-title {
                font-size: 2rem;
            }
            .hero-subtitle {
                font-size: 1rem;
            }
            .form-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .navbar-custom {
                padding: 0.75rem 1rem;
            }
            .hero-section {
                min-height: auto;
                padding: 2rem 0;
            }
            .hero-title {
                font-size: 1.75rem;
            }
            .logo-wrapper {
                width: 6rem;
                height: 6rem;
            }
            .form-card {
                padding: 1.25rem;
            }
            .form-card h2 {
                font-size: 1.25rem;
            }
            .hero-content {
                gap: 1.5rem;
            }
            .password-toggle {
                padding: 0.35rem;
                font-size: 1rem;
            }
        }

        @media (min-width: 576px) and (max-width: 767.98px) {
            .hero-section {
                min-height: auto;
                padding: 3rem 0;
            }
        }

        /* Alert styling */
        .alert {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
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
        .alert .btn {
            margin-left: 0.5rem;
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        .gap-3 {
            gap: 0.75rem !important;
        }
        .gap-2 {
            gap: 0.5rem !important;
        }
    </style>
</head>
<body>

<!-- BEGIN: Simplified TopNavBar -->
<nav class="navbar navbar-expand-md navbar-custom sticky-top">
    <div class="container-fluid px-3 px-md-4" style="max-width: 1280px;">
        <a class="navbar-brand navbar-brand-custom" href="reset-password.php">ATBU Clearance Portal</a>
        <div class="d-flex align-items-center gap-3">
            <a href="login.php" class="btn-login d-none d-md-inline-block">Login</a>
            <a href="register.php" class="btn-register d-inline-block">Register</a>
        </div>
    </div>
</nav>
<!-- END: Simplified TopNavBar -->

<!-- BEGIN: MainContent -->
<main>
    <!-- BEGIN: Hero Section -->
    <section class="hero-section">
        <!-- Background Image - Centered -->
        <div class="hero-background">
            <img alt="University Campus Building" src="assets/images/atbu.jfif">
            <div class="hero-overlay"></div>
            <div class="hero-gradient"></div>
        </div>
        
        <div class="hero-content">
            <!-- Logo + Text - Centered -->
            <div class="hero-header">
                <div class="logo-wrapper">
                    <img src="assets/images/atbu-logo.jfif" alt="Atbu logo">
                </div>
                <h1 class="hero-title">Online Student Clearance System</h1>
                <p class="hero-subtitle">Final Year Student Clearance Platform</p>
            </div>
            
            <!-- Reset Password Form - Centered -->
            <div class="hero-form-column">
                <div class="form-card">
                    <h2>Reset Password</h2>
                    
                    <?php echo $message; ?>
                    
                    <form method="POST" id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="password-wrapper">
                                <input type="password" 
                                       id="password"
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Enter new password" 
                                       required>
                                <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
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
                        
                        <button type="submit" name="reset" class="btn-submit" id="resetBtn" disabled>
                            Reset Password
                        </button>
                    </form>
                    
                    <div class="extra-links">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- END: Hero Section -->
</main>
<!-- END: MainContent -->

<!-- BEGIN: Footer -->
<footer class="footer-custom">
    <div class="footer-content">
        <span class="footer-brand">ATBU Clearance Portal</span>
        <p class="footer-text">© 2024 Abubakar Tafawa Balewa University. All rights reserved.</p>
    </div>
</footer>
<!-- END: Footer -->

<!-- ✅ IMPORTANT: Load Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- ✅ IMPORTANT: Bootstrap Icons CDN (fallback) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script>
    (function() {
        'use strict';

        // --- Password toggle using Bootstrap Icons ---
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePassword');
        const eyeIcon = document.getElementById('eyeIcon');

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.classList.toggle('bi-eye');
            eyeIcon.classList.toggle('bi-eye-slash');
        });

        // --- Password validation with real-time feedback ---
        const reqLength = document.getElementById('reqLength');
        const reqLower = document.getElementById('reqLower');
        const reqUpper = document.getElementById('reqUpper');
        const reqNumber = document.getElementById('reqNumber');
        const resetBtn = document.getElementById('resetBtn');

        // Helper to update requirement status
        function updateRequirement(element, isValid) {
            const icon = element.querySelector('.req-icon i');
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
            const pwd = passwordInput.value;

            // At least 6 characters
            const lenValid = pwd.length >= 6;
            updateRequirement(reqLength, lenValid);

            // At least 1 lowercase letter
            const lowerValid = /[a-z]/.test(pwd);
            updateRequirement(reqLower, lowerValid);

            // At least 1 uppercase letter
            const upperValid = /[A-Z]/.test(pwd);
            updateRequirement(reqUpper, upperValid);

            // At least 1 number
            const numberValid = /\d/.test(pwd);
            updateRequirement(reqNumber, numberValid);

            // All requirements met
            const allValid = lenValid && lowerValid && upperValid && numberValid;
            resetBtn.disabled = !allValid;

            return allValid;
        }

        // Validate on input
        passwordInput.addEventListener('input', validatePassword);

        // Also validate on page load (in case browser autofills)
        window.addEventListener('DOMContentLoaded', function() {
            if (passwordInput.value.length > 0) {
                validatePassword();
            }
        });

        // Additional check before form submit (enforce)
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (!validatePassword()) {
                e.preventDefault();
                alert('Please ensure your password meets all requirements:\n\n• At least 6 characters\n• At least 1 lowercase letter\n• At least 1 uppercase letter\n• At least 1 number');
            }
        });

    })();
</script>
</body>
</html>