<?php
// edit_voter.php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) && !isset($_SESSION['id'])) {
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";
include "app/model/voters.php";
include "app/model/elections.php";

// Check if voter ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: voter_management.php?message=No voter ID provided&status=error");
    exit();
}

$voter_id = $_GET['id'];

// Get voter details
$voter = get_voter_by_id($conn, $voter_id);

if (!$voter) {
    header("Location: voter_management.php?message=Voter not found&status=error");
    exit();
}

$elecs = get_all_elections($conn);
$current_date = date('d M, Y');
foreach ($elecs as $elec) {
    if ($elec['status'] == "upcoming" && $elec['start_datetime'] >= $current_date) {
        $sql = "UPDATE elections SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $data = array("active", $elec['id']);
        $stmt->execute($data);
    }
    if ($elec['status'] == "active" && $elec['end_datetime'] >= $current_date) {
        $sql = "UPDATE elections SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $data = array("completed", $elec['id']);
        $stmt->execute($data);
    }
    $cans = get_election_candidates($conn, $elec['id']);
    if ($elec['status'] == "upcoming" && $cans == 0 && $elec['start_datetime'] >= $current_date) {
        $sql = "UPDATE elections SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $data = array("draft", $elec['id']);
        $stmt->execute($data);
    }
}

// Get initials for avatar
$initials = '';
$name_parts = explode(' ', $voter['full_name']);
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
} else {
    $initials = strtoupper(substr($voter['full_name'], 0, 2));
}

// Format dates for display
$dob_display = !empty($voter['dob']) ? date('d F, Y', strtotime($voter['dob'])) : '';
$reg_date_display = date('d F, Y \a\t h:i A', strtotime($voter['created_at']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Voter | SecureVote Admin</title>
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

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active_2 {
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

        .header-right {
            display: flex;
            align-items: center;
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

        /* Edit Content */
        .edit-content {
            padding: 0 2rem 2rem;
        }

        .edit-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .edit-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .edit-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .edit-body {
            padding: 1.5rem;
        }

        /* Voter Profile */
        .voter-profile {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .voter-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .voter-info h4 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .voter-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-weight: 500;
            color: var(--primary-color);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .status-suspended {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-inactive {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Form Styles */
        .form-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-section h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #dee2e6;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--admin-color);
            box-shadow: 0 0 0 0.25rem rgba(155, 89, 182, 0.25);
        }

        /* Buttons */
        .btn-purple {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
            color: white;
        }

        .btn-purple:hover {
            background-color: #8e44ad;
            border-color: #8e44ad;
            color: white;
        }

        .btn-outline-purple {
            border-color: var(--admin-color);
            color: var(--admin-color);
        }

        .btn-outline-purple:hover {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .voter-profile {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .voter-meta {
                justify-content: center;
            }

            .edit-content {
                padding: 0 1rem 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .voter-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .meta-item {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                width: 100%;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

                // Remove query parameters from URL
                const newUrl = window.location.pathname + '?id=<?= $voter_id ?>';
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</head>

<body>
    <!-- Sidebar -->
    <?php include "include/sidebar.php" ?>

    <!-- Main Content -->
    <div id="main-content">
        <!-- Header -->
        <header id="main-header">
            <div class="header-left">
                <h1>Edit Voter</h1>
            </div>
            <div class="header-right">
                <a href="voter_management.php" class="btn btn-outline-purple">
                    <i class="fas fa-arrow-left me-2"></i> Back to Voters
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="edit-content">
            <div class="edit-container">
                <!-- Voter Profile -->
                <div class="voter-profile">
                    <div class="voter-avatar">
                        <?= $initials ?>
                    </div>
                    <div class="voter-info">
                        <h4>
                            <?= htmlspecialchars($voter['full_name']) ?>
                        </h4>
                        <div class="voter-meta">
                            <div class="meta-item">
                                <span class="meta-label">Voter ID</span>
                                <span class="meta-value">#
                                    <?= $voter['id'] ?>
                                </span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Email</span>
                                <span class="meta-value">
                                    <?= htmlspecialchars($voter['email']) ?>
                                </span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Status</span>
                                <span class="meta-value">
                                    <?php
                                    if ($voter['status'] == 'verified'): ?>
                                        <span class="status-badge status-verified">Verified</span>
                                    <?php elseif ($voter['status'] == 'pending'): ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php elseif ($voter['status'] == 'suspended'): ?>
                                        <span class="status-badge status-suspended">Suspended</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Registered</span>
                                <span class="meta-value">
                                    <?= $reg_date_display ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <form method="POST" action="app/edit_voter.php">
                    <div class="edit-body">
                        <!-- Personal Information -->
                        <div class="form-section">
                            <h5><i class="fas fa-user-circle me-2"></i> Personal Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                        value="<?= htmlspecialchars($voter['full_name']) ?>" required>
                                    <div class="invalid-feedback">Please enter the voter's full name.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?= htmlspecialchars($voter['email']) ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="contact" name="contact"
                                        value="<?= htmlspecialchars($voter['contact'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Please enter a phone number.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="dob" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="dob" name="dob"
                                        value="<?= !empty($voter['dob']) ? date('Y-m-d', strtotime($voter['dob'])) : '' ?>"
                                        required max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                                    <div class="invalid-feedback">Please enter a valid date of birth (must be 18+).
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address"
                                    rows="2"><?= htmlspecialchars($voter['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Account Settings -->
                        <div class="form-section">
                            <h5><i class="fas fa-cog me-2"></i> Account Settings</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Account Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="verified" <?= $voter['status'] == 'verified' ? 'selected' : '' ?>
                                            >Verified</option>
                                        <option value="pending" <?= $voter['status'] == 'pending' ? 'selected' : '' ?>
                                            >Pending</option>
                                        <option value="suspended" <?= $voter['status'] == 'suspended' ? 'selected' : '' ?>
                                            >Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="voter_id" class="form-label">Voter ID</label>
                                    <input type="text" class="form-control" id="voter_id"
                                        value="<?= $voter['voter_id'] ?? 'N/A' ?>" readonly>
                                    <small class="text-muted">System-generated, cannot be changed</small>
                                </div>
                            </div>
                        </div>

                        <!-- Password Reset (Optional) -->
                        <div class="form-section">
                            <h5><i class="fas fa-key me-2"></i> Password Reset (Optional)</h5>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Leave password fields blank if you don't want to change the password.
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="Leave blank to keep current">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 6 characters if changing</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" placeholder="Confirm new password">
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>
                        </div>

                        <!-- CSRF Token -->
                        <div class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= bin2hex(random_bytes(32)) ?>">
                            <input type="hidden" name="voter_id" value="<?= $voter_id ?>">
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="voter_management.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-purple">
                                <i class="fas fa-save me-2"></i> Update Voter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Toggle password visibility
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            if (togglePasswordBtn && passwordInput) {
                togglePasswordBtn.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }

            // Form validation
            const form = document.querySelector('form');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');

            if (form) {
                form.addEventListener('submit', function (e) {
                    let isValid = true;

                    // Validate required fields
                    const requiredFields = this.querySelectorAll('[required]');
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });

                    // Validate email
                    const emailField = document.getElementById('email');
                    if (emailField && emailField.value) {
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailPattern.test(emailField.value)) {
                            emailField.classList.add('is-invalid');
                            isValid = false;
                        }
                    }

                    // Validate password match
                    if (passwordField.value || confirmPasswordField.value) {
                        if (passwordField.value !== confirmPasswordField.value) {
                            confirmPasswordField.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            confirmPasswordField.classList.remove('is-invalid');
                        }

                        if (passwordField.value && passwordField.value.length < 6) {
                            passwordField.classList.add('is-invalid');
                            isValid = false;
                        }
                    }

                    // Validate age
                    const dobField = document.getElementById('dob');
                    if (dobField && dobField.value) {
                        const dob = new Date(dobField.value);
                        const today = new Date();
                        const age = today.getFullYear() - dob.getFullYear();
                        const monthDiff = today.getMonth() - dob.getMonth();
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                            age--;
                        }

                        if (age < 18) {
                            dobField.classList.add('is-invalid');
                            dobField.nextElementSibling.textContent = 'Voter must be at least 18 years old.';
                            isValid = false;
                        }
                    }

                    if (!isValid) {
                        e.preventDefault();
                        sweetAlertHandler.showNotification('error', 'Please correct the errors in the form.');
                    }
                });

                // Real-time validation
                const fields = form.querySelectorAll('input, select, textarea');
                fields.forEach(field => {
                    field.addEventListener('input', function () {
                        this.classList.remove('is-invalid');
                    });
                });
            }
        });
    </script>
</body>

</html>