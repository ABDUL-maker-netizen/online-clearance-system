<?php

include 'includes/config.php';
include 'includes/mail.php';
include 'includes/csrf.php';

$message = "";
$email = ""; // Store email for re-display

if(isset($_POST['submit'])){

    verify_csrf();

    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if email exists
    $check = mysqli_query($conn,
    "SELECT * FROM students
    WHERE email='$email'");

    if(mysqli_num_rows($check) > 0){

        // Get student data for personalized email
        $student = mysqli_fetch_assoc($check);
        $fullname = $student['fullname'];

        // GENERATE TOKEN
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // SAVE TOKEN
        $update_token = mysqli_query($conn,
        "UPDATE students
        SET reset_token='$token',
        reset_token_expiry='$expiry'
        WHERE email='$email'");

        if($update_token){
            // RESET LINK - Make sure this URL is correct for your setup
            $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $base_path = '/online-clearance-system/'; // Adjust if needed
            $link = $protocol . $host . $base_path . "reset-password.php?token=$token";

            // EMAIL SUBJECT
            $subject = "Password Reset Request - ATBU Clearance System";

            // EMAIL BODY - Professional HTML email
            $message_body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Password Reset</title>
                <style>
                    body {
                        font-family: Arial, Helvetica, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                        background-color: #ffffff;
                    }
                    .header {
                        background: linear-gradient(135deg, #1e40af, #1e3a8a);
                        color: white;
                        padding: 30px 20px;
                        text-align: center;
                        border-radius: 5px 5px 0 0;
                    }
                    .header h2 {
                        margin: 0;
                        font-size: 24px;
                    }
                    .content {
                        padding: 30px 20px;
                        border: 1px solid #e5e7eb;
                        border-top: none;
                        border-radius: 0 0 5px 5px;
                        background-color: #ffffff;
                    }
                    .greeting {
                        font-size: 18px;
                        color: #111827;
                        margin-bottom: 15px;
                    }
                    .message {
                        color: #374151;
                        line-height: 1.6;
                        margin-bottom: 25px;
                    }
                    .button-container {
                        text-align: center;
                        margin: 30px 0;
                    }
                    .btn-reset {
                        display: inline-block;
                        background: linear-gradient(135deg, #1e40af, #1e3a8a);
                        color: white !important;
                        padding: 14px 35px;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: 600;
                        font-size: 16px;
                        transition: all 0.3s ease;
                        box-shadow: 0 4px 6px rgba(30, 64, 175, 0.2);
                    }
                    .btn-reset:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 8px rgba(30, 64, 175, 0.3);
                    }
                    .divider {
                        border-top: 1px solid #e5e7eb;
                        margin: 25px 0;
                    }
                    .info-text {
                        color: #6b7280;
                        font-size: 14px;
                        line-height: 1.6;
                    }
                    .info-text strong {
                        color: #374151;
                    }
                    .footer {
                        text-align: center;
                        padding: 20px;
                        color: #6b7280;
                        font-size: 12px;
                        border-top: 1px solid #e5e7eb;
                        margin-top: 20px;
                    }
                    .footer a {
                        color: #1e40af;
                        text-decoration: none;
                    }
                    .footer a:hover {
                        text-decoration: underline;
                    }
                    .expiry-note {
                        background-color: #f3f4f6;
                        padding: 10px 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                        font-size: 14px;
                        color: #4b5563;
                    }
                    @media only screen and (max-width: 480px) {
                        .container {
                            padding: 10px;
                        }
                        .header h2 {
                            font-size: 20px;
                        }
                        .content {
                            padding: 20px 15px;
                        }
                        .btn-reset {
                            padding: 12px 25px;
                            font-size: 14px;
                            display: block;
                        }
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🔐 ATBU Clearance System</h2>
                        <p style='margin: 5px 0 0; opacity: 0.9;'>Password Reset Request</p>
                    </div>
                    
                    <div class='content'>
                        <div class='greeting'>
                            <strong>Hello $fullname,</strong>
                        </div>
                        
                        <div class='message'>
                            We received a request to reset your password for your ATBU Online Clearance System account.
                        </div>
                        
                        <div class='button-container'>
                            <a href='$link' class='btn-reset'>🔑 Reset Password</a>
                        </div>
                        
                        <div class='expiry-note'>
                            ⏰ <strong>Note:</strong> This link will expire in <strong>1 hour</strong> for security reasons.
                        </div>
                        
                        <div class='divider'></div>
                        
                        <div class='info-text'>
                            <p><strong>Why did I receive this email?</strong></p>
                            <p>You requested a password reset for your ATBU Clearance System account. If you didn't make this request, you can safely ignore this email.</p>
                            <br>
                            <p><strong>Security Tip:</strong></p>
                            <p>Never share your password with anyone. The ATBU Clearance System will never ask for your password via email.</p>
                        </div>
                        
                        <div class='divider'></div>
                        
                        <p style='text-align: center; color: #6b7280; font-size: 14px;'>
                            If you have any issues, please contact the support team.
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p style='margin: 0;'>
                            &copy; 2024 Abubakar Tafawa Balewa University. All rights reserved.
                        </p>
                        <p style='margin: 5px 0 0;'>
                            <a href='http://www.atbu.edu.ng'>www.atbu.edu.ng</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";

            // SEND EMAIL
            $email_sent = send_notification($email, $message_body, $subject);
            
            // Log the attempt for debugging
            error_log("Password reset email sent to $email: " . ($email_sent ? "Success" : "Failed"));

            if($email_sent){
                $message = "
                <div class='alert alert-success'>
                    <strong>✓ Success!</strong> Password reset link has been sent to your email.
                    <br><small>Please check your inbox (and spam/junk folder) and follow the instructions.</small>
                </div>
                ";
            } else {
                $message = "
                <div class='alert alert-danger'>
                    <strong>✗ Error!</strong> Failed to send reset email. 
                    <br><small>Please try again or contact support if the problem persists.</small>
                </div>
                ";
            }
        } else {
            $message = "
            <div class='alert alert-danger'>
                <strong>✗ Database Error!</strong> Failed to generate reset token. Please try again.
                <br><small>Error: " . mysqli_error($conn) . "</small>
            </div>
            ";
        }

    } else {
        $message = "
        <div class='alert alert-danger'>
            <strong>✗ Email Not Found!</strong> We couldn't find an account with this email address.
            <br><small>Please check your email or <a href='register.php' style='color: #1e40af;'>register</a> if you don't have an account.</small>
        </div>
        ";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password | Online Clearance System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
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
        .form-card .btn-submit:hover {
            background-color: #1e3a8a;
            transform: scale(0.98);
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
            padding: 1rem;
        }
        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .alert-danger {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        .alert small {
            display: block;
            margin-top: 5px;
            opacity: 0.8;
        }
        .alert a {
            color: #1e40af;
            font-weight: 600;
        }
        .gap-3 {
            gap: 0.75rem !important;
        }
        .gap-2 {
            gap: 0.5rem !important;
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
        }

        @media (min-width: 576px) and (max-width: 767.98px) {
            .hero-section {
                min-height: auto;
                padding: 3rem 0;
            }
        }
    </style>
</head>
<body>

<!-- BEGIN: Simplified TopNavBar -->
<nav class="navbar navbar-expand-md navbar-custom sticky-top">
    <div class="container-fluid px-3 px-md-4" style="max-width: 1280px;">
        <a class="navbar-brand navbar-brand-custom" href="forgot-password.php">ATBU Clearance Portal</a>
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
            
            <!-- Forgot Password Form - Centered -->
            <div class="hero-form-column">
                <div class="form-card">
                    <h2>🔑 Forgot Password</h2>
                    
                    <?php echo $message; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   id="email"
                                   name="email" 
                                   class="form-control" 
                                   placeholder="your@email.com" 
                                   value="<?php echo htmlspecialchars($email); ?>"
                                   required>
                            <small class="text-muted">Enter the email address associated with your account</small>
                        </div>
                        
                        <button type="submit" name="submit" class="btn-submit">
                            📧 Send Reset Link
                        </button>
                    </form>
                    
                    <div class="extra-links">
                        <a href="login.php">← Back to Login</a>
                        <a href="register.php">Create Student Account</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

