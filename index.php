<?php
    include "db_connection.php";

    $sql = "SELECT contact, email, location FROM admin";
    $stmt = $conn->prepare($sql);
    $stmt->execute([]);

    if ($stmt->rowCount() == 1) {
        $admin = $stmt->fetch();
    } else{
        $admin = 0;
    }

    
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureVote | Online Voting System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #1abc9c;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --danger-color: #e74c3c;
            --success-color: #27ae60;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .navbar-brand span {
            color: var(--secondary-color);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a2530 100%);
            color: white;
            padding: 6rem 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MCIgaGVpZ2h0PSI1MCIgdmlld0JveD0iMCAwIDUwIDUwIj48cGF0aCBkPSJNMjAgNDBoMTB2LTEwaDEwdjEwaDEwdi0yMGgtMTB2LTEwaC0xMHYxMGgtMTB2MjB6IiBmaWxsPSIjMUE0MkQ1IiBvcGFjaXR5PSIwLjEiLz48L3N2Zz4=');
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background-color: white;
            border-bottom: 2px solid var(--accent-color);
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.3rem;
            color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success-color);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: #219653;
            transform: translateY(-2px);
        }

        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .system-name {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            margin-top: 1rem;
        }

        .feature-list {
            list-style-type: none;
            padding-left: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            padding-left: 2rem;
            position: relative;
        }

        .feature-list li:before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--success-color);
        }

        .hidden-admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            padding: 12px;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 100;
            opacity: 0.3;
            transition: opacity 0.3s ease;
        }

        .hidden-admin-link:hover {
            opacity: 1;
            color: white;
        }

        .hidden-admin-link i {
            font-size: 1.5rem;
        }

        .system-info {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 3rem;
        }

        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }

        .login-form-container,
        .signup-form-container {
            display: none;
        }

        .active-form {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        .admin-info {
            background-color: #e8f4fc;
            border-left: 4px solid var(--secondary-color);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .admin-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .invalid-feedback {
            display: none;
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .was-validated .form-control:invalid~.invalid-feedback {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-section {
                padding: 4rem 0;
            }

            .hidden-admin-link {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-vote-yea text-primary me-2"></i>Secure<span>Vote</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title">Secure Online Voting System</h1>
                    <p class="hero-subtitle">Cast your vote securely from anywhere, at any time. Our blockchain-based
                        voting system ensures transparency, security, and accessibility for all.</p>

                    <!-- Display Admin Contact Info from Database -->

                    <div class="system-name" style="color: white;">
                        <i class="fas fa-shield-alt me-2" style="color: white;"></i>System: SecureVote Pro v3.2.1
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header text-center">
                            Access Your Account
                        </div>
                        <div class="card-body p-4">
                            <!-- Tabs for Login/Signup -->
                            <ul class="nav nav-tabs nav-justified mb-4" id="authTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="login-tab" data-bs-toggle="tab"
                                        data-bs-target="#login" type="button" role="tab">Login</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="signup-tab" data-bs-toggle="tab"
                                        data-bs-target="#signup" type="button" role="tab">Sign Up</button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" id="authTabsContent">
                                <!-- Login Form -->
                                <div class="tab-pane fade show active" id="login" role="tabpanel">
                                    <form id="loginForm" method="POST" action="app/voter_login.php" novalidate>
                                        <div class="mb-3">
                                            <label for="loginEmail" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="email"
                                                placeholder="Enter your email" required>
                                            <div class="invalid-feedback">
                                                Please enter a valid email address.
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="loginPassword" class="form-label">Password</label>
                                            <input type="password" class="form-control" name="password"
                                                placeholder="Enter your password" required>
                                            <div class="invalid-feedback">
                                                Please enter your password.
                                            </div>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-lg">Login to Your
                                                Account</button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Signup Form -->
                                <div class="tab-pane fade" id="signup" role="tabpanel">
                                    <form id="signupForm" method="POST" action="app/voter_signup.php" novalidate>
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="full_name" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name"
                                                    placeholder="Enter your full name" required>
                                                <div class="invalid-feedback">
                                                    Please enter your full name.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="signupEmail" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" id="signupEmail"
                                                    placeholder="Enter your email" required>
                                                <div class="invalid-feedback">
                                                    Please enter a valid email address.
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="dob" class="form-label">Date of Birth</label>
                                                <input type="date" name="dob" class="form-control" id="dob"
                                                    placeholder="Enter your date of birth" required>
                                                <div class="invalid-feedback" id="dob-error">
                                                    You must be at least 18 years old to register.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="contact" class="form-label">Contact</label>
                                                <input type="tel" class="form-control" name="contact" id="contact"
                                                    placeholder="Enter your contact number" required
                                                    pattern="[\+\d\s\-\(\)]{10,}">
                                                <div class="invalid-feedback">
                                                    Please enter a valid contact number.
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="address" class="form-label">Address</label>
                                                <input type="text" class="form-control" id="address" name="address"
                                                    placeholder="Enter your address" required>
                                                <div class="invalid-feedback">
                                                    Please enter your address.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="signupPassword" class="form-label">Password</label>
                                                <input type="password" class="form-control" name="password"
                                                    id="signupPassword" placeholder="Create a strong password" required
                                                    minlength="6">
                                                <div class="invalid-feedback">
                                                    Password must be at least 6 characters long.
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                                <input type="password" class="form-control" name="confirm_password"
                                                    id="confirmPassword" placeholder="Confirm your password" required>
                                                <div class="invalid-feedback" id="confirm-password-error">
                                                    Passwords do not match.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-success btn-lg">Create New
                                                Account</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container" id="features">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Why Choose SecureVote?</h2>
                <p class="lead">Our voting platform combines cutting-edge technology with user-friendly design</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-shield-alt fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Military-Grade Security</h4>
                        <p class="card-text">End-to-end encryption and blockchain technology ensure your vote is secure
                            and tamper-proof.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-user-check fa-3x text-success"></i>
                        </div>
                        <h4 class="card-title">Identity Verification</h4>
                        <p class="card-text">Advanced identity verification systems prevent fraud and ensure one vote
                            per eligible voter.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-chart-bar fa-3x text-info"></i>
                        </div>
                        <h4 class="card-title">Real-Time Results</h4>
                        <p class="card-text">Watch live results as they come in with our real-time data visualization
                            and analytics.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- System Info Section -->
    <section class="container system-info" id="about">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h3 class="text-center mb-4">About SecureVote System</h3>
                <p class="mb-4">SecureVote is a state-of-the-art online voting platform designed for organizations,
                    institutions, and governments. Our system ensures:</p>
                <ul class="feature-list mb-4">
                    <li>Complete anonymity and privacy for voters</li>
                    <li>Transparent and auditable voting process</li>
                    <li>Accessibility for voters with disabilities</li>
                    <li>Multi-language support for diverse communities</li>
                    <li>Compliance with international election standards</li>
                </ul>
                <div class="text-center">
                    <p class="system-name">
                        <i class="fas fa-server me-2"></i>Current System Status: <span
                            class="text-success">Operational</span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Hidden Admin Link -->
    <a href="admin_login.php" class="hidden-admin-link" id="adminLink" title="Admin Login">
        <i class="fas fa-cogs"></i>
    </a>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row" style="justify-content: space-evenly; display: flex;">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-vote-yea me-2"></i>SecureVote</h5>
                    <p>Making democratic processes accessible, secure, and transparent through technology.</p>
                </div>
                <!-- <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">How to Vote</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Election Calendar</a></li>
                        <li><a href="#" class="text-light text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-light text-decoration-none">Privacy Policy</a></li>
                    </ul>
                </div> -->
                <div class="col-md-4 mb-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i><?= $admin['email'] ?></li>
                        <li><i class="fas fa-phone me-2"></i><?= $admin['contact'] ?></li>
                        <li><i class="fas fa-map-marker-alt me-2"></i><?= $admin['location'] ?></li>
                    </ul>
                </div>
            </div>
            <hr class="bg-light">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p>&copy; 2023 SecureVote Online Voting System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // URL parameters handling for status messages
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status) {
                Swal.fire({
                    title: status.charAt(0).toUpperCase() + status.slice(1),
                    text: message,
                    icon: status,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6',
                    showCloseButton: true,
                    allowOutsideClick: false,
                    allowEscapeKey: true
                });

                history.replaceState(null, null, window.location.pathname);
            }

            // Calculate maximum date for 18 years old (18 years ago from today)
            function calculateMaxDateFor18YearsOld() {
                const today = new Date();
                const maxDate = new Date();
                maxDate.setFullYear(today.getFullYear() - 18);

                // Format as YYYY-MM-DD
                const year = maxDate.getFullYear();
                const month = String(maxDate.getMonth() + 1).padStart(2, '0');
                const day = String(maxDate.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            }

            // Set maximum date for date of birth field
            const dobInput = document.getElementById('dob');
            if (dobInput) {
                const maxDate = calculateMaxDateFor18YearsOld();
                dobInput.max = maxDate;

                // Add change event to validate age
                dobInput.addEventListener('change', function () {
                    validateAge(this);
                });
            }

            // Validate age is at least 18 years
            function validateAge(dobInput) {
                const selectedDate = new Date(dobInput.value);
                const today = new Date();
                const minAgeDate = new Date();
                minAgeDate.setFullYear(today.getFullYear() - 18);

                if (selectedDate > minAgeDate) {
                    dobInput.setCustomValidity('You must be at least 18 years old to register.');
                    document.getElementById('dob-error').style.display = 'block';
                    return false;
                } else {
                    dobInput.setCustomValidity('');
                    document.getElementById('dob-error').style.display = 'none';
                    return true;
                }
            }

            // Password confirmation validation
            const passwordInput = document.getElementById('signupPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');

            if (passwordInput && confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function () {
                    validatePasswordMatch();
                });

                passwordInput.addEventListener('input', function () {
                    validatePasswordMatch();
                });
            }

            function validatePasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (password !== confirmPassword && confirmPassword !== '') {
                    confirmPasswordInput.setCustomValidity('Passwords do not match.');
                    document.getElementById('confirm-password-error').style.display = 'block';
                    return false;
                } else {
                    confirmPasswordInput.setCustomValidity('');
                    document.getElementById('confirm-password-error').style.display = 'none';
                    return true;
                }
            }

            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    // Additional custom validation
                    if (form.id === 'signupForm') {
                        const dobValid = validateAge(dobInput);
                        const passwordMatchValid = validatePasswordMatch();

                        if (!dobValid || !passwordMatchValid) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }

                    form.classList.add('was-validated');
                }, false);
            });

            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();

                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;

                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Add animation to cards on scroll
            const observerOptions = {
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    }
                });
            }, observerOptions);

            // Observe all cards for animation
            document.querySelectorAll('.card').forEach(card => {
                observer.observe(card);
            });

            // Admin link hover effect
            const adminLink = document.getElementById('adminLink');
            if (adminLink) {
                adminLink.addEventListener('mouseenter', function () {
                    this.style.opacity = '1';
                });

                adminLink.addEventListener('mouseleave', function () {
                    this.style.opacity = '0.3';
                });
            }
        });
    </script>
</body>

</html>