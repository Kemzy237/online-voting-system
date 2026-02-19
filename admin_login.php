<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | SecureVote</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #1abc9c;
            --admin-color: #9b59b6;
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
            display: flex;
            flex-direction: column;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .navbar-brand span {
            color: var(--admin-color);
        }

        .admin-login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #1a2530 0%, var(--primary-color) 100%);
            position: relative;
            overflow: hidden;
        }

        .admin-login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MCIgaGVpZ2h0PSI1MCIgdmlld0JveD0iMCAwIDUwIDUwIj48cGF0aCBkPSJNMjUgMTBjLTMuMyAwLTYgMi43LTYgNnMyLjcgNiA2IDYgNi0yLjcgNi02LTIuNy02LTYtNnptMTUgMTVoLTMwYy0xLjEgMC0yIC45LTIgMnYxMGMwIDEuMS45IDIgMiAyaDMwYzEuMSAwIDItLjkgMi0ydi0xMGMwLTEuMS0uOS0yLTItMnoiIGZpbGw9IiMxQTQyRDUiIG9wYWNpdHk9IjAuMSIvPjwvc3ZnPg==');
            z-index: 0;
        }

        .admin-card {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
        }

        .admin-card-header {
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            border-radius: 0 0 30px 30px;
            margin-bottom: 1.5rem;
        }

        .admin-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            background-color: rgba(255, 255, 255, 0.2);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .admin-card-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .admin-card-subtitle {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .admin-form {
            padding: 0 2rem 2rem;
        }

        .form-control {
            border-radius: 10px;
            padding: 0.85rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.25rem rgba(155, 89, 182, 0.25);
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 10px 0 0 10px;
        }

        .btn-admin {
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            color: white;
            border: none;
            padding: 0.9rem 2rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 1rem;
        }

        .btn-admin:hover {
            background: linear-gradient(135deg, #8e44ad 0%, var(--admin-color) 100%);
            transform: translateY(-2px);
            color: white;
        }

        .security-notice {
            background-color: rgba(155, 89, 182, 0.1);
            border-left: 4px solid var(--admin-color);
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .security-notice i {
            color: var(--admin-color);
            margin-right: 0.5rem;
        }

        .admin-features {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .admin-feature-list {
            list-style-type: none;
            padding-left: 0;
            font-size: 0.9rem;
        }

        .admin-feature-list li {
            padding: 0.4rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .admin-feature-list li:before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--admin-color);
        }

        .back-to-home {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .back-to-home a {
            color: var(--admin-color);
            text-decoration: none;
            font-weight: 500;
        }

        .back-to-home a:hover {
            text-decoration: underline;
        }

        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem 0;
            text-align: center;
            font-size: 0.9rem;
        }

        .error-message {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: none;
        }

        .password-toggle {
            cursor: pointer;
            color: #666;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--admin-color);
        }

        /* Two-factor section */
        .two-factor-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .two-factor-code {
            letter-spacing: 0.5rem;
            font-size: 1.8rem;
            font-weight: 600;
            text-align: center;
            color: var(--admin-color);
            margin: 1rem 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .admin-card {
                max-width: 90%;
            }
            
            .admin-form {
                padding: 0 1.5rem 1.5rem;
            }
            
            .admin-card-header {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .admin-card-header {
                padding: 1.2rem;
            }
            
            .admin-icon {
                width: 80px;
                height: 80px;
                font-size: 2.8rem;
            }
            
            .admin-card-title {
                font-size: 1.5rem;
            }
            
            .admin-form {
                padding: 0 1.2rem 1.2rem;
            }
        }
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            min-width: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status) {
                Swal.fire({
                    title: status,
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
        });
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-vote-yea me-2"></i>Secure<span>Vote</span> <small class="text-muted ms-2">Admin</small>
            </a>
            <div class="navbar-text">
                <span class="badge bg-purple">Restricted Access</span>
            </div>
        </div>
    </nav>

    <!-- Admin Login Container -->
    <main class="admin-login-container">
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1 class="admin-card-title">Administrator Login</h1>
                <p class="admin-card-subtitle">Secure access to the voting system management panel</p>
            </div>
            
            <div class="admin-form">
                <form id="adminLoginForm" method="post" action="app/admin_login.php">
                    <div class="mb-3">
                        <label for="adminUsername" class="form-label fw-semibold">Admin Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="adminUsername" name="username" placeholder="Enter admin username" required>
                        </div>
                        <div class="error-message" id="usernameError">Please enter a valid admin username</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adminPassword" class="form-label fw-semibold">Admin Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="adminPassword" name="password" placeholder="Enter admin password" required>
                            <span class="input-group-text password-toggle" id="passwordToggle">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="error-message" id="passwordError">Please enter your admin password</div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn-admin" id="loginButton">
                            <i class="fas fa-sign-in-alt me-2"></i> Access Admin Dashboard
                        </button>
                    </div>
                    
                    <div class="security-notice">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Security Notice:</strong> This page is for authorized personnel only. All login attempts are logged and monitored.
                    </div>
                </form>
                
                <div class="back-to-home">
                    <a href="index.php">
                        <i class="fas fa-arrow-left me-1"></i> Back to Voting Homepage
                    </a>
                </div>
            </div>
        </div>
    </main>

     <?php //$pass = password_hash(123, PASSWORD_DEFAULT);     echo $pass;?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <p>&copy; 2026 SecureVote Online Voting System. Admin access restricted to authorized personnel only.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('adminPassword');
        const passwordIcon = passwordToggle.querySelector('i');
        
        passwordToggle.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        });
        
        // Form submission handling
        const adminLoginForm = document.getElementById('adminLoginForm');
        const loginButton = document.getElementById('loginButton');
        const twoFactorSection = document.getElementById('twoFactorSection');
        const twoFactorCode = document.getElementById('twoFactorCode');
        const twoFactorInput = document.getElementById('twoFactorInput');
        const resendCodeBtn = document.getElementById('resendCode');
        
        // Generate a random 6-digit code for two-factor authentication
        function generateTwoFactorCode() {
            return Math.floor(100000 + Math.random() * 900000).toString();
        }
        
        // Initially set a random code
        twoFactorCode.textContent = generateTwoFactorCode();
        
        // Handle form submission
        adminLoginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('adminUsername').value;
            const password = document.getElementById('adminPassword').value;
            const secretKey = document.getElementById('adminSecretKey').value;
            
            // Reset error messages
            document.querySelectorAll('.error-message').forEach(el => {
                el.style.display = 'none';
            });
            
            let valid = true;
            
            // Simple validation
            if (!username) {
                document.getElementById('usernameError').style.display = 'block';
                valid = false;
            }
            
            if (!password) {
                document.getElementById('passwordError').style.display = 'block';
                valid = false;
            }
            
            if (!valid) return;
            
            // For demo purposes - check for specific credentials
            if (username === 'admin' && password === 'admin123') {
                // Show two-factor authentication
                twoFactorSection.style.display = 'block';
                loginButton.innerHTML = '<i class="fas fa-check-circle me-2"></i> Verify & Continue';
                loginButton.type = 'button';
                loginButton.onclick = verifyTwoFactor;
                
                // Scroll to two-factor section
                twoFactorSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Update login button to verify two-factor code
                loginButton.onclick = verifyTwoFactor;
            } else if (username === 'superadmin' && password === 'super123' && secretKey === 'securekey2023') {
                // Direct access for super admin with security key
                alert('Super admin access granted! Redirecting to admin dashboard...');
                // In a real app: window.location.href = 'admin-dashboard.html';
            } else {
                alert('Invalid admin credentials. Please try again.\n\nDemo credentials:\nUsername: admin\nPassword: admin123\n\nSuper admin:\nUsername: superadmin\nPassword: super123\nKey: securekey2023');
            }
        });
        
        // Verify two-factor authentication code
        function verifyTwoFactor() {
            const enteredCode = twoFactorInput.value;
            const actualCode = twoFactorCode.textContent;
            
            if (enteredCode === actualCode) {
                alert('Two-factor authentication successful! Redirecting to admin dashboard...');
                // In a real app: window.location.href = 'admin-dashboard.html';
                
                // Simulate redirect after 1 second
                setTimeout(() => {
                    loginButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Redirecting...';
                    loginButton.disabled = true;
                }, 100);
            } else {
                document.getElementById('twoFactorError').style.display = 'block';
                twoFactorInput.classList.add('is-invalid');
            }
        }
        
        // Resend two-factor code
        resendCodeBtn.addEventListener('click', function() {
            const newCode = generateTwoFactorCode();
            twoFactorCode.textContent = newCode;
            
            // Visual feedback
            resendCodeBtn.innerHTML = '<i class="fas fa-check me-1"></i> Code Sent';
            resendCodeBtn.classList.remove('btn-outline-secondary');
            resendCodeBtn.classList.add('btn-success');
            
            // Reset after 3 seconds
            setTimeout(() => {
                resendCodeBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Code';
                resendCodeBtn.classList.remove('btn-success');
                resendCodeBtn.classList.add('btn-outline-secondary');
            }, 3000);
        });
        
        // Clear two-factor error when user types
        twoFactorInput.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            document.getElementById('twoFactorError').style.display = 'none';
        });
        
        // Demo credentials auto-fill for testing
        function fillDemoCredentials(type) {
            if (type === 'admin') {
                document.getElementById('adminUsername').value = 'admin';
                document.getElementById('adminPassword').value = 'admin123';
                document.getElementById('adminSecretKey').value = '';
            } else if (type === 'superadmin') {
                document.getElementById('adminUsername').value = 'superadmin';
                document.getElementById('adminPassword').value = 'super123';
                document.getElementById('adminSecretKey').value = 'securekey2023';
            }
        }
        
        // Add demo buttons for testing (hidden by default but can be enabled)
        // Uncomment the following lines to add visible demo buttons:
        /*
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.admin-form');
            const demoDiv = document.createElement('div');
            demoDiv.className = 'mt-3 p-2 border rounded';
            demoDiv.innerHTML = `
                <p class="small mb-2"><strong>Demo Credentials:</strong></p>
                <button class="btn btn-sm btn-outline-primary me-2" onclick="fillDemoCredentials('admin')">Admin</button>
                <button class="btn btn-sm btn-outline-danger" onclick="fillDemoCredentials('superadmin')">Super Admin</button>
            `;
            form.insertBefore(demoDiv, form.querySelector('.security-notice'));
        });
        */
    </script>
</body>
</html>