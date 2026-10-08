<?php
session_start();

include '../includes/config.php';

// Redirect if already logged in
if(isset($_SESSION['admin_id'])){
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$fullname = '';
$email = '';

// Check if registration is enabled (you can set this to false to disable registration)
$allow_registration = true;

if(!$allow_registration){
    die("Registration is currently disabled. Please contact the system administrator.");
}

if(isset($_POST['register'])){
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validation
    if(empty($fullname)){
        $error = "Full name is required.";
    } elseif(empty($email)){
        $error = "Email address is required.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } elseif(empty($password)){
        $error = "Password is required.";
    } elseif(strlen($password) < 6){
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $check = mysqli_query($conn, "SELECT id FROM admins WHERE email='$email' LIMIT 1");
        if(mysqli_num_rows($check) > 0){
            $error = "This email address is already registered.";
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "
                INSERT INTO admins (fullname, email, password, created_at)
                VALUES ('$fullname', '$email', '$hashed_password', NOW())
            ";
            
            if(mysqli_query($conn, $query)){
                $success = "Registration successful! You can now login.";
                
                // Auto-login after registration
                $admin_id = mysqli_insert_id($conn);
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['admin_name'] = $fullname;
                $_SESSION['admin_email'] = $email;
                
                // Redirect to dashboard after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                </script>";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration | Online Clearance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .register-card .subtitle {
            color: #4b5563;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 14px;
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

        /* Password requirements container */
        .password-requirements-box {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 12px 16px;
            margin-top: 8px;
            font-size: 12px;
            color: #4b5563;
        }
        .password-requirements-box ul {
            margin: 4px 0 0 0;
            padding-left: 20px;
        }
        .password-requirements-box ul li {
            line-height: 1.6;
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
                <h1 class="hero-title">Admin Clearance Management</h1>
                <p class="hero-subtitle">Final Year Student Clearance Platform</p>
            </div>
            
            <!-- Registration Form - Centered -->
            <div class="hero-form-column">
                <div class="register-card">
                    <h2>Admin Registration</h2>
                    <p class="subtitle">Create an administrator account</p>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo $success; ?>
                            <div class="mt-2">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Redirecting to dashboard...
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if(!$success): ?>
                    <form method="POST" id="registerForm">
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" 
                                   id="fullname"
                                   name="fullname" 
                                   class="form-control" 
                                   placeholder="Enter your full name"
                                   value="<?php echo htmlspecialchars($fullname); ?>"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   id="email"
                                   name="email" 
                                   class="form-control" 
                                   placeholder="admin@example.com"
                                   value="<?php echo htmlspecialchars($email); ?>"
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
                            <div class="password-requirements-box">
                                <small><i class="bi bi-info-circle me-1"></i> Password must contain:</small>
                                <ul class="password-requirements" id="passwordRequirements">
                                    <li id="reqLength"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 6 characters</li>
                                    <li id="reqLower"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 lowercase letter</li>
                                    <li id="reqUpper"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 uppercase letter</li>
                                    <li id="reqNumber"><span class="req-icon"><i class="bi bi-circle"></i></span> At least 1 number</li>
                                </ul>
                            </div>
                        </div>
                        
                        <button type="submit" name="register" class="btn-submit" id="registerBtn" disabled>
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <span class="text-muted">Already have an account?</span>
                        <a href="login.php" class="login-link ms-1">Login here</a>
                    </div>
                    <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>
<script>
// Toggle password visibility
function togglePassword(inputId, button) {
    var field = document.getElementById(inputId);
    var icon = button.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

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
    registerBtn.style.opacity = registerBtn.disabled ? '0.6' : '1';
    registerBtn.style.cursor = registerBtn.disabled ? 'not-allowed' : 'pointer';

    return allValid;
}

// Validate on password input
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
});
</script>

</body>
</html>