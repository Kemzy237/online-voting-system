<?php
// admin_settings.php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include "db_connection.php";

// Set timezone
date_default_timezone_set('Africa/Douala');

// Get current admin ID from session
$admin_id = $_SESSION['id'];

// Fetch admin details
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    $_SESSION['error'] = "Admin account not found";
    header("Location: admin_login.php");
    exit();
}

// Initialize variables
$message = '';
$status = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (empty($username) || empty($contact) || empty($email)) {
            $message = "Please fill in all required fields";
            $status = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address";
            $status = "error";
        } else {
            // Check if username already exists (excluding current admin)
            $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
            $stmt->execute([$username, $admin_id]);
            if ($stmt->rowCount() > 0) {
                $message = "Username already exists. Please choose another one.";
                $status = "error";
            } else {
                // Update admin profile
                $stmt = $conn->prepare("UPDATE admin SET username = ?, contact = ?, email = ?, location = ? WHERE id = ?");
                $stmt->execute([$username, $contact, $email, $location, $admin_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Profile updated successfully!";
                    $status = "success";
                    // Update session username
                    $_SESSION['username'] = $username;
                    // Refresh admin data
                    $stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    $admin = $stmt->fetch();
                } else {
                    $message = "No changes were made to your profile";
                    $status = "info";
                }
            }
        }
    }

    if (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "Please fill in all password fields";
            $status = "error";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match";
            $status = "error";
        } elseif (strlen($new_password) < 8) {
            $message = "New password must be at least 8 characters long";
            $status = "error";
        } else {
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password
                $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $admin_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Password changed successfully!";
                    $status = "success";
                    // Refresh admin data
                    $stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    $admin = $stmt->fetch();
                } else {
                    $message = "Failed to change password";
                    $status = "error";
                }
            } else {
                $message = "Current password is incorrect";
                $status = "error";
            }
        }
    }

    if (isset($_POST['update_system'])) {
        // Update system settings (you can add more settings here)
        $site_name = filter_input(INPUT_POST, 'site_name', FILTER_SANITIZE_STRING);
        $site_email = filter_input(INPUT_POST, 'site_email', FILTER_SANITIZE_EMAIL);
        $site_contact = filter_input(INPUT_POST, 'site_contact', FILTER_SANITIZE_STRING);
        $max_voters_per_election = filter_input(INPUT_POST, 'max_voters_per_election', FILTER_VALIDATE_INT);
        $voter_verification_required = isset($_POST['voter_verification_required']) ? 1 : 0;

        // You can store these in a separate settings table or in the admin table
        // For now, we'll store them in a JSON file or you can create a settings table
        $settings = [
            'site_name' => $site_name,
            'site_email' => $site_email,
            'site_contact' => $site_contact,
            'max_voters_per_election' => $max_voters_per_election,
            'voter_verification_required' => $voter_verification_required,
            'last_updated' => date('Y-m-d H:i:s'),
            'updated_by' => $admin_id
        ];

        // Save to file (or you can create a settings table)
        file_put_contents('system_settings.json', json_encode($settings, JSON_PRETTY_PRINT));

        $message = "System settings updated successfully!";
        $status = "success";
    }
}

// Load system settings
$system_settings = [];
if (file_exists('system_settings.json')) {
    $system_settings = json_decode(file_get_contents('system_settings.json'), true);
}

// Set default values if not set
$default_settings = [
    'site_name' => 'SecureVote',
    'site_email' => 'support@votesecure.com',
    'site_contact' => '+237 653 426 838',
    'max_voters_per_election' => 1000,
    'voter_verification_required' => true
];

foreach ($default_settings as $key => $value) {
    if (!isset($system_settings[$key])) {
        $system_settings[$key] = $value;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | SecureVote Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

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
            --warning-color: #f39c12;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active_5 {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid var(--admin-color);
        }

        /* Main Content Styles */
        #main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        #main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Header Styles */
        #main-header {
            background-color: white;
            height: var(--header-height);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-left h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        /* Page Header */
        .page-header {
            background-color: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eee;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 1rem;
        }

        /* Settings Content */
        .settings-content {
            padding: 0 2rem 2rem;
        }

        /* Settings Cards */
        .settings-card {
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background-color: rgba(155, 89, 182, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--admin-color);
            font-size: 1.2rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--admin-color);
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.25rem rgba(155, 89, 182, 0.25);
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }

        .strength-weak {
            background-color: var(--danger-color);
        }

        .strength-medium {
            background-color: var(--warning-color);
        }

        .strength-strong {
            background-color: var(--success-color);
        }

        /* Toggle Switch */
        .form-check {
            margin-bottom: 1rem;
        }

        .form-check-input {
            width: 3em;
            height: 1.5em;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
        }

        /* Danger Zone */
        .danger-zone {
            border: 2px solid var(--danger-color);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            background-color: rgba(231, 76, 60, 0.05);
        }

        .danger-title {
            color: var(--danger-color);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-description {
            color: #666;
            margin-bottom: 1.5rem;
        }

        /* Alert Messages */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .alert-info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background-color: var(--admin-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #8e44ad;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .btn-outline-secondary {
            border: 1px solid #dee2e6;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
        }

        /* Mobile Toggle Button */
        #mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-right: 1rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            #main-content {
                margin-left: 0;
            }

            #main-content.expanded {
                margin-left: 0;
            }

            #mobile-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .settings-content {
                padding: 0 1rem 1rem;
            }

            .page-header {
                padding: 1.5rem 1rem;
            }

            .settings-card {
                padding: 1.5rem;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .card-icon {
                margin-right: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include "include/sidebar.php" ?>

    <!-- Main Content -->
    <div id="main-content">
        <!-- Header -->
        <header id="main-header">
            <div class="header-left">
                <button id="mobile-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>System Settings</h1>
            </div>
            <div class="header-right">
                <div class="admin-info">
                    <span class="text-muted">Welcome, </span>
                    <strong>
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </strong>
                </div>
            </div>
        </header>

        <!-- Settings Content -->
        <main class="settings-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">System Settings</h1>
                <p class="page-subtitle">Manage your account settings and system preferences</p>
            </div>

            <!-- Display Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $status ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h2 class="card-title">Profile Settings</h2>
                </div>

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    Username
                                </label>
                                <input type="text" class="form-control" name="username"
                                    value="<?= htmlspecialchars($admin['username']) ?>" required>
                                <small class="text-muted">This will be your login username</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i>
                                    Email Address
                                </label>
                                <input type="email" class="form-control" name="email"
                                    value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i>
                                    Contact Number
                                </label>
                                <input type="text" class="form-control" name="contact"
                                    value="<?= htmlspecialchars($admin['contact']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Location
                                </label>
                                <input type="text" class="form-control" name="location"
                                    value="<?= htmlspecialchars($admin['location']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-shield-alt"></i>
                                    Role
                                </label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($admin['role']) ?>"
                                    disabled>
                                <small class="text-muted">Role cannot be changed</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Account Created
                                </label>
                                <input type="text" class="form-control"
                                    value="<?= date('F j, Y', strtotime($admin['created_at'] ?? 'now')) ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h2 class="card-title">Password Settings</h2>
                </div>

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Current Password
                                </label>
                                <input type="password" class="form-control" name="current_password"
                                    id="current_password" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    New Password
                                </label>
                                <input type="password" class="form-control" name="new_password" id="new_password"
                                    required minlength="8">
                                <div class="password-strength">
                                    <div class="strength-bar" id="password-strength-bar"></div>
                                </div>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Confirm New Password
                                </label>
                                <input type="password" class="form-control" name="confirm_password"
                                    id="confirm_password" required minlength="8">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Modals -->

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize sidebar toggle
            initSidebarToggle();

            // Initialize password strength checker
            initPasswordStrengthChecker();

            // Initialize modal functionality
            initModals();
        });

        function initSidebarToggle() {
            const toggleSidebarBtn = document.getElementById('toggle-sidebar');
            const mobileToggleBtn = document.getElementById('mobile-toggle');

            if (toggleSidebarBtn) {
                toggleSidebarBtn.addEventListener('click', () => {
                    const sidebar = document.getElementById('sidebar');
                    const mainContent = document.getElementById('main-content');
                    const toggleIcon = toggleSidebarBtn.querySelector('i');

                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');

                    if (sidebar.classList.contains('collapsed')) {
                        toggleIcon.classList.remove('fa-chevron-left');
                        toggleIcon.classList.add('fa-chevron-right');
                    } else {
                        toggleIcon.classList.remove('fa-chevron-right');
                        toggleIcon.classList.add('fa-chevron-left');
                    }
                });
            }

            if (mobileToggleBtn) {
                mobileToggleBtn.addEventListener('click', () => {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.toggle('mobile-show');
                });
            }
        }

        function initPasswordStrengthChecker() {
            const passwordInput = document.getElementById('new_password');
            const strengthBar = document.getElementById('password-strength-bar');

            if (passwordInput && strengthBar) {
                passwordInput.addEventListener('input', function () {
                    const password = this.value;
                    let strength = 0;

                    // Length check
                    if (password.length >= 8) strength += 25;
                    if (password.length >= 12) strength += 25;

                    // Complexity checks
                    if (/[A-Z]/.test(password)) strength += 25;
                    if (/[0-9]/.test(password)) strength += 25;
                    if (/[^A-Za-z0-9]/.test(password)) strength += 25;

                    // Cap at 100
                    strength = Math.min(strength, 100);

                    // Update strength bar
                    strengthBar.style.width = strength + '%';

                    // Update color
                    strengthBar.className = 'strength-bar';
                    if (strength < 50) {
                        strengthBar.classList.add('strength-weak');
                    } else if (strength < 75) {
                        strengthBar.classList.add('strength-medium');
                    } else {
                        strengthBar.classList.add('strength-strong');
                    }
                });
            }
        }

        function initModals() {
            // Clear Cache Button
            const clearCacheBtn = document.getElementById('clearCacheBtn');
            if (clearCacheBtn) {
                clearCacheBtn.addEventListener('click', function () {
                    // Show loading
                    const btn = this;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
                    btn.disabled = true;

                    // Simulate API call
                    setTimeout(() => {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Cache Cleared',
                            text: 'System cache has been cleared successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // Reset button
                        btn.innerHTML = originalText;
                        btn.disabled = false;

                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('clearCacheModal'));
                        modal.hide();
                    }, 1500);
                });
            }

            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function (e) {
                    // Add loading state to submit buttons
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        submitBtn.disabled = true;
                    }
                });
            });

            // Password confirmation validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            function validatePasswordMatch() {
                if (newPasswordInput.value && confirmPasswordInput.value) {
                    if (newPasswordInput.value !== confirmPasswordInput.value) {
                        confirmPasswordInput.setCustomValidity('Passwords do not match');
                        confirmPasswordInput.classList.add('is-invalid');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
                        confirmPasswordInput.classList.remove('is-invalid');
                    }
                }
            }

            if (newPasswordInput && confirmPasswordInput) {
                newPasswordInput.addEventListener('input', validatePasswordMatch);
                confirmPasswordInput.addEventListener('input', validatePasswordMatch);
            }
        }
    </script>
</body>

</html>