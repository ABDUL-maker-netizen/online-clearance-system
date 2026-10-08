<!DOCTYPE html>
<html>
<head>
    <title>Online Clearance system</title>
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
            text-decoration:none;

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
            text-decoration:none;

        }
        .btn-register:hover {
            background-color: #1e3a8a !important;
            transform: scale(0.95);
        }
        .btn-register-now {
            background-color: #1e40af !important;
            color: white !important;
            font-weight: 700;
            padding: 0.875rem 2rem;
            border-radius: 0.75rem;
            border: none;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration:none;
        }
        .btn-register-now:hover {
            background-color: #1e3a8a !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: scale(0.95);
            color: white !important;
        }
        .btn-student-login {
            color: white !important;
            font-weight: 700;
            padding: 0.875rem 2rem;
            border-radius: 0.75rem;
            border: 2px solid rgba(255, 255, 255, 0.8) !important;
            background-color: transparent;
            backdrop-filter: blur(4px);
            transition: all 0.15s ease-in-out;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration:none;

        }
        .btn-student-login:hover {
            background-color: #000000 !important;
            color: #ffffff !important;
            border-color: #000000 !important;
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
            max-width: 896px;
            padding: 0 1.5rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
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
            font-size: 2.25rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.025em;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0;
        }
        .hero-subtitle {
            font-size: 1.125rem;
            color: rgba(229, 231, 235, 1);
            font-weight: 500;
            max-width: 672px;
            margin: 0 auto;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        @media (min-width: 768px) {
            .hero-title {
                font-size: 3rem;
            }
        }
        @media (min-width: 992px) {
            .hero-title {
                font-size: 3.75rem;
            }
            .hero-subtitle {
                font-size: 1.25rem;
            }
        }

        /* Info Cards */
        .info-section {
            padding: 4rem 2rem;
            background-color: #f8f9ff;
            position: relative;
            z-index: 20;
        }
        .info-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            text-align: center;
            height: 100%;
        }
        .info-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem auto;
            background-color: rgba(30, 64, 175, 0.1);
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e40af;
        }
        .info-icon svg {
            width: 1.5rem;
            height: 1.5rem;
        }
        .info-title {
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
            color: #111827;
        }
        .info-text {
            color: #4b5563;
            font-size: 0.875rem;
            margin: 0;
        }

        /* Footer */
        .footer-custom {
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 2.5rem 2rem;
            margin-top: auto;
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

        /* Responsive spacing utilities */
        .gap-6 {
            gap: 1.5rem !important;
        }
        .gap-4 {
            gap: 1rem !important;
        }
        .gap-3 {
            gap: 0.75rem !important;
        }
        .gap-2 {
            gap: 0.5rem !important;
        }

        /* Icon inline SVG styles */
        .icon-arrow {
            width: 1.25rem;
            height: 1.25rem;
        }

        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .hero-content {
                gap: 1.5rem;
                padding: 0 1rem;
            }
            .hero-title {
                font-size: 2rem;
            }
            .hero-subtitle {
                font-size: 1rem;
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
            .hero-subtitle {
                font-size: 0.95rem;
            }
            .logo-wrapper {
                width: 6rem;
                height: 6rem;
            }
            .info-section {
                padding: 2rem 1rem;
            }
            .btn-register-now,
            .btn-student-login {
                width: 100%;
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }
            .hero-buttons {
                width: 100%;
            }
            .hero-buttons .btn {
                width: 100%;
            }
            .hero-content {
                gap: 1rem;
                padding: 0 1rem;
            }
        }

        @media (min-width: 576px) and (max-width: 767.98px) {
            .hero-section {
                min-height: auto;
                padding: 3rem 0;
            }
            .hero-buttons .btn {
                width: auto;
            }
            .hero-title {
                font-size: 2rem;
            }
        }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .hero-section {
                min-height: auto;
                padding: 4rem 0;
            }
        }
    </style>
</head>
<body>

<!-- BEGIN: Simplified TopNavBar -->
<nav class="navbar navbar-expand-md navbar-custom sticky-top">
    <div class="container-fluid px-3 px-md-4" style="max-width: 1280px;">
        <a class="navbar-brand navbar-brand-custom" href="index.php">ATBU Clearance Portal</a>
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
        <!-- Background Image -->
        <div class="hero-background">
            <img alt="University Campus Building" src="assets/images/atbu.jfif">
            <div class="hero-overlay"></div>
            <div class="hero-gradient"></div>
        </div>
        
        <div class="hero-content">
            <!-- University Logo -->
            <div class="logo-wrapper">
                <img src="assets/images/atbu-logo.jfif" alt="Atbu logo">
            </div>
            
            <div>
                <h1 class="hero-title">Online Student Clearance System</h1>
                <p class="hero-subtitle">Final Year Student Clearance Platform</p>
            </div>
            
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-4 mt-4 hero-buttons" style="width: 100%;">
                <!-- Register Now Button -->
                <a href="register.php" class="btn-register-now">
                    Register Now
                    <svg class="icon-arrow" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" fill-rule="evenodd"></path>
                    </svg>
                </a>
                
                <!-- Student Login Button -->
                <a href="login.php" class="btn-student-login">Student Login</a>
            </div>
        </div>
    </section>
    <!-- END: Hero Section -->

    <!-- BEGIN: Quick Info Section -->
    <section class="info-section">
        <div class="container-fluid" style="max-width: 1280px;">
            <div class="row g-4">
                <!-- Card 1: Fast Processing -->
                <div class="col-12 col-md-4">
                    <div class="info-card">
                        <div class="info-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        <h3 class="info-title">Fast Processing</h3>
                        <p class="info-text">Automated workflows ensure quicker clearance from all departments.</p>
                    </div>
                </div>
                
                <!-- Card 2: Paperless System -->
                <div class="col-12 col-md-4">
                    <div class="info-card">
                        <div class="info-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        <h3 class="info-title">Paperless System</h3>
                        <p class="info-text">Upload documents digitally. No more moving files physically across campus.</p>
                    </div>
                </div>
                
                <!-- Card 3: Secure Data -->
                <div class="col-12 col-md-4">
                    <div class="info-card">
                        <div class="info-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </div>
                        <h3 class="info-title">Secure Data</h3>
                        <p class="info-text">Your academic and personal information is protected with enterprise-grade security.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- END: Quick Info Section -->
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