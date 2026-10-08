<?php
include '../includes/config.php';
include '../includes/csrf.php';

$message = "";

/* FETCH OFFICES FROM offices TABLE */
$dept_query = mysqli_query($conn, "SELECT * FROM offices");

$offices = [];

while($row = mysqli_fetch_assoc($dept_query)){
    $offices[] = strtoupper($row['offices_name']);
}

if(isset($_POST['register'])){

    verify_csrf();

    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $office_raw = strtoupper(mysqli_real_escape_string($conn, $_POST['office']));
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    /* CHECK IF OFFICE EXISTS IN offices TABLE */
    if(!in_array($office_raw, $offices)){

        $message = "<div class='alert alert-danger'>
        Invalid Office (Not Found In Department Table)
        </div>";

    } else {

        /* EXCLUDE FACULTY + DEPARTMENT */
        $allowed = [
            'BURSAR',
            'ALUMNI RELATION DIVISION',
            'LIBRARY',
            'SPORT UNIT',
            'HALL'
        ];

        if(!in_array($office_raw, $allowed)){

            $message = "<div class='alert alert-danger'>
            This office is not allowed
            </div>";

        } else {

            /* CHECK EMAIL */
            $check = mysqli_query($conn,
            "SELECT * FROM officers
            WHERE email='$email'");

            if(mysqli_num_rows($check) > 0){

                $message = "<div class='alert alert-danger'>
                Email already exists
                </div>";

            } else {

                /* ONLY ONE OFFICER PER OFFICE */
                $office_check = mysqli_query($conn,
                "SELECT * FROM officers
                WHERE office='$office_raw'");

                if(mysqli_num_rows($office_check) > 0){

                    $message = "<div class='alert alert-danger'>
                    This office already has an assigned officer
                    </div>";

                } else {

                    $insert = mysqli_query($conn,
                    "INSERT INTO officers
                    (
                        fullname,
                        office,
                        email,
                        phone,
                        password,
                        role
                    )
                    VALUES
                    (
                        '$fullname',
                        '$office_raw',
                        '$email',
                        '$phone',
                        '$password',
                        'OFFICE'
                    )");

                    if($insert){

                        $message = "<div class='alert alert-success'>
                        Registration Successful
                        </div>";

                    } else {

                        $message = "<div class='alert alert-danger'>
                        Registration Failed
                        </div>";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Officer Registration | Online Clearance System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- ✅ IMPORTANT: Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.css">
    <link rel="icon" type="imagejfif" sizes="16x16" href="../assets/images/atbu-logo.jfif">
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

        /* Buttons */
        .btn-login-nav {
            color: #1e40af !important;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
        }
        .btn-login-nav:hover {
            background-color: rgba(30, 64, 175, 0.1) !important;
        }
        .btn-register-nav {
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
        .btn-register-nav:hover {
            background-color: #1e3a8a !important;
            transform: scale(0.95);
        }

        /* Hero Section */
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

        /* Logo */
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

        /* Hero Text */
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

        /* Hero Header Section */
        .hero-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 100%;
        }

        /* Registration Card */
        .hero-form-column {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }

        .register-card {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(229, 231, 235, 0.5);
            width: 100%;
        }
        .register-card h2 {
            color: #1e40af;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .register-card .form-control {
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.15s ease-in-out;
        }
        .register-card .form-control:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .register-card .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .register-card .btn-submit {
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
        .register-card .btn-submit:hover {
            background-color: #1e3a8a;
            transform: scale(0.98);
        }
        .register-card .login-link {
            color: #1e40af;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s ease-in-out;
        }
        .register-card .login-link:hover {
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
            .register-card {
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
            .register-card {
                padding: 1.25rem;
            }
            .register-card h2 {
                font-size: 1.25rem;
            }
            .hero-content {
                gap: 1.5rem;
            }
        }

        @media (min-width: 576px) and (max-width: 767.98px) {
            .hero-section {
                min-height: auto;
                padding: 3rem 0;
            }
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
        <a class="navbar-brand navbar-brand-custom" href="register.php">ATBU Clearance Portal</a>
        <div class="d-flex align-items-center gap-3">
            <a href="login.php" class="btn-login-nav d-none d-md-inline-block">Login</a>
            <a href="register.php" class="btn-register-nav d-inline-block">Register</a>
        </div>
    </div>
</nav>
<!-- END: Simplified TopNavBar -->

<!-- BEGIN: MainContent -->
<main>
    <!-- BEGIN: Hero Section -->
    <section class="hero-section">
        <!-- Background Image -->
        <div class="hero-background">
            <img alt="University Campus Building" src="../assets/images/atbu.jfif">
            <div class="hero-overlay"></div>
            <div class="hero-gradient"></div>
        </div>
        
        <div class="hero-content">
            <!-- Logo + Text - Centered -->
            <div class="hero-header">
                <div class="logo-wrapper">
                    <img src="../assets/images/atbu-logo.jfif" alt="ATBU Logo">
                </div>
                <h1 class="hero-title">Officer Clearance Management</h1>
                <p class="hero-subtitle">Final Year Student Clearance Platform</p>
            </div>
            
            <!-- Registration Form - Centered -->
            <div class="hero-form-column">
                <div class="register-card">
                    <h2>Officer Registration</h2>
                    
                    <?php echo $message; ?>
                    
                    <form method="POST" id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" 
                                   id="fullname"
                                   name="fullname" 
                                   class="form-control" 
                                   placeholder="Enter full name" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="office" class="form-label">Office</label>
                            <select name="office" id="office" class="form-control" required>
                                <option value="">Select Office</option>
                                <?php foreach($offices as $dept){ ?>
                                    <option value="<?php echo $dept; ?>">
                                        <?php echo $dept; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   id="email"
                                   name="email" 
                                   class="form-control" 
                                   placeholder="your@email.com" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" 
                                   id="phone"
                                   name="phone" 
                                   class="form-control" 
                                   placeholder="Enter phone number" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-wrapper">
                                <input type="password" 
                                       id="password"
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Create a strong password" 
                                       required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            
                            <!-- Password requirements -->
                            <ul class="password-requirements" id="passwordRequirements">
                                <li id="reqLength"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 6 characters</li>
                                <li id="reqLower"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 lowercase letter</li>
                                <li id="reqUpper"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 uppercase letter</li>
                                <li id="reqNumber"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 number</li>
                            </ul>
                        </div>
                        
                        <button type="submit" name="register" class="btn-submit" id="registerBtn" disabled>
                            <i class="bi bi-person-plus"></i> Register
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <span class="text-muted">Already have an account?</span>
                        <a href="login.php" class="login-link ms-1">Login</a>
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

<!-- ✅ IMPORTANT: Load Bootstrap JS and Icons -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- ✅ IMPORTANT: Bootstrap Icons CDN (fallback) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<script>
(function() {
    'use strict';

    // --- Password toggle function ---
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
    var passwordInput = document.getElementById('password');
    var reqLength = document.getElementById('reqLength');
    var reqLower = document.getElementById('reqLower');
    var reqUpper = document.getElementById('reqUpper');
    var reqNumber = document.getElementById('reqNumber');
    var registerBtn = document.getElementById('registerBtn');

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
        var pwd = passwordInput.value;

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
        registerBtn.disabled = !allValid;
        registerBtn.style.opacity = allValid ? '1' : '0.6';
        registerBtn.style.cursor = allValid ? 'pointer' : 'not-allowed';

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

    // Additional check before form submit
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        var pwd = document.getElementById('password').value;

        // Check if all password requirements are met
        if (!validatePassword()) {
            e.preventDefault();
            alert('Please ensure your password meets all requirements:\n\n• At least 6 characters\n• At least 1 lowercase letter\n• At least 1 uppercase letter\n• At least 1 number');
            return false;
        }

        // Check if password is empty
        if (pwd.length === 0) {
            e.preventDefault();
            alert('Please enter a password.');
            return false;
        }
    });

})();
</script>

</body>
</html>