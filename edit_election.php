<?php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) && !isset($_SESSION['id'])) {
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";
include "app/model/elections.php";


// Check if election ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $message = "No election specified";
    $status = "error";
    header("Location: election_polls.php?message=$message&status=$status");
    exit();
}

$election_id = $_GET['id'];

// Get election data
$election = get_election_by_id($conn, $election_id);
if ($election == 0) {
    $message = "Election not found";
    $status = "error";
    header("Location: election_polls.php?message=$message&status=$status");
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_datetime = $_POST['start_datetime'] ?? '';
    $end_datetime = $_POST['end_datetime'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    // Validate input
    $errors = [];

    if (empty($title)) {
        $errors[] = "Title is required";
    }

    if (empty($start_datetime)) {
        $errors[] = "Start date and time is required";
    }

    if (empty($end_datetime)) {
        $errors[] = "End date and time is required";
    }

    if ($start_datetime >= $end_datetime) {
        $errors[] = "End date must be after start date";
    }

    // If no errors, update election
    if (empty($errors)) {
        $update_data = [
            'id' => $election_id,
            'title' => $title,
            'description' => $description,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'status' => $status
        ];

        if (update_election($conn, $update_data)) {
            $message = "Election updated successfully!";
            $status = "success";
            header("Location: election_polls.php?message=$message&status=$status");
            exit();
        } else {
            $errors[] = "Failed to update election. Please try again.";
        }
    }

    // If there are errors, update form values with submitted data
    $election['title'] = $title;
    $election['description'] = $description;
    $election['start_datetime'] = $start_datetime;
    $election['end_datetime'] = $end_datetime;
    $election['status'] = $status;
}

// Format dates for form input
$start_date = date('Y-m-d\TH:i', strtotime($election['start_datetime']));
$end_date = date('Y-m-d\TH:i', strtotime($election['end_datetime']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Election | SecureVote Admin</title>
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
        .sidebar-menu a.active_3 {
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

        /* Dashboard Content */
        .dashboard-content {
            padding: 0 2rem 2rem;
        }

        /* Form Container */
        .form-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .form-header .subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
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

        .required::after {
            content: " *";
            color: var(--danger-color);
        }

        .btn-purple {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-purple:hover {
            background-color: #8e44ad;
            border-color: #8e44ad;
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline-purple {
            border-color: var(--admin-color);
            color: var(--admin-color);
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-outline-purple:hover {
            background-color: var(--admin-color);
            color: white;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-draft {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .status-upcoming {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-completed {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .status-cancelled {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        /* Election Info Card */
        .info-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            display: flex;
            margin-bottom: 0.75rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            min-width: 150px;
        }

        .info-value {
            color: #6c757d;
        }

        /* Alert Styles */
        .alert-container {
            margin-bottom: 2rem;
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
            #sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }

            #sidebar.mobile-show {
                margin-left: 0;
            }

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
            .dashboard-content {
                padding: 0 1rem 1rem;
            }

            .page-header {
                padding: 1.5rem 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .info-item {
                flex-direction: column;
            }

            .info-label {
                min-width: auto;
                margin-bottom: 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .form-header h3 {
                font-size: 1.3rem;
            }

            .btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn-group .btn {
                width: 100%;
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
                <h1>Edit Election</h1>
            </div>
        </header>

        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">Edit Election Poll</h2>
            <p class="page-subtitle">Update election details, dates, and status</p>
        </div>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert-container">
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i> Please fix the following errors:</h5>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Election Information Card -->
            <div class="info-card">
                <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i> Election Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">Election ID:</div>
                            <div class="info-value"><strong>#
                                    <?= $election['id'] ?>
                                </strong></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Created On:</div>
                            <div class="info-value">
                                <?= date('F d, Y \a\t h:i A', strtotime($election['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <div class="info-label">Current Status:</div>
                            <div class="info-value">
                                <?php
                                $status_badge = '';
                                switch ($election['status']) {
                                    case 'draft':
                                        $status_badge = 'status-draft';
                                        break;
                                    case 'upcoming':
                                        $status_badge = 'status-upcoming';
                                        break;
                                    case 'active':
                                        $status_badge = 'status-active';
                                        break;
                                    case 'completed':
                                        $status_badge = 'status-completed';
                                        break;
                                    case 'cancelled':
                                        $status_badge = 'status-cancelled';
                                        break;
                                    default:
                                        $status_badge = 'status-draft';
                                }
                                ?>
                                <span class="status-badge <?= $status_badge ?>">
                                    <?= ucfirst($election['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Registered Voters:</div>
                            <div class="info-value"><strong>
                                    <?= $election['total_registered_voters'] ?>
                                </strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="form-container">
                <div class="form-header">
                    <h3>Edit Election Details</h3>
                    <p class="subtitle">Update the election information below. Fields marked with * are required.</p>
                </div>

                <form method="POST" action="" id="editElectionForm">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label required">Election Title</label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?= htmlspecialchars($election['title']) ?>" placeholder="Enter election title"
                                required>
                            <div class="form-text">A clear, descriptive title for the election.</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label required">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="draft" <?= $election['status'] == 'draft' ? 'selected' : '' ?>>Draft
                                </option>
                                <option value="upcoming" <?= $election['status'] == 'upcoming' ? 'selected' : '' ?>
                                    >Upcoming</option>
                                <option value="active" <?= $election['status'] == 'active' ? 'selected' : '' ?>>Active
                                </option>
                                <option value="completed" <?= $election['status'] == 'completed' ? 'selected' : '' ?>
                                    >Completed</option>
                                <option value="cancelled" <?= $election['status'] == 'cancelled' ? 'selected' : '' ?>
                                    >Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"
                            placeholder="Enter election description (optional)"><?= htmlspecialchars($election['description']) ?></textarea>
                        <div class="form-text">Provide details about the election purpose, rules, or any important
                            information.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_datetime" class="form-label required">Start Date & Time</label>
                            <input type="datetime-local" class="form-control" id="start_datetime" name="start_datetime"
                                value="<?= $start_date ?>" required>
                            <div class="form-text">When voting begins for this election.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_datetime" class="form-label required">End Date & Time</label>
                            <input type="datetime-local" class="form-control" id="end_datetime" name="end_datetime"
                                value="<?= $end_date ?>" required>
                            <div class="form-text">When voting ends for this election.</div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Changing the status to "Active" will make the election available for
                        voting.
                        Changing to "Completed" will close the election and prevent further votes.
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="election_polls.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Elections
                            </a>
                            <a href="view_election.php?id=<?= $election['id'] ?>" class="btn btn-outline-purple ms-2">
                                <i class="fas fa-eye me-2"></i> View Election
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-purple">
                                <i class="fas fa-save me-2"></i> Update Election
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
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sidebar toggle
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

            // Form validation
            const form = document.getElementById('editElectionForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    // Clear previous error highlights
                    clearErrors();

                    let isValid = true;

                    // Validate title
                    const title = document.getElementById('title');
                    if (!title.value.trim()) {
                        showError(title, 'Title is required');
                        isValid = false;
                    }

                    // Validate start datetime
                    const startDatetime = document.getElementById('start_datetime');
                    if (!startDatetime.value) {
                        showError(startDatetime, 'Start date and time is required');
                        isValid = false;
                    }

                    // Validate end datetime
                    const endDatetime = document.getElementById('end_datetime');
                    if (!endDatetime.value) {
                        showError(endDatetime, 'End date and time is required');
                        isValid = false;
                    }

                    // Validate dates
                    if (startDatetime.value && endDatetime.value) {
                        const startDate = new Date(startDatetime.value);
                        const endDate = new Date(endDatetime.value);

                        if (startDate >= endDate) {
                            showError(endDatetime, 'End date must be after start date');
                            isValid = false;
                        }

                        // Check if end date is in the past for upcoming/active status
                        const status = document.getElementById('status').value;
                        const now = new Date();

                        if ((status === 'upcoming' || status === 'active') && endDate <= now) {
                            showError(endDatetime, 'End date must be in the future for upcoming/active elections');
                            isValid = false;
                        }

                        if (status === 'active' && startDate > now) {
                            showError(startDatetime, 'Start date must be in the past for active elections');
                            isValid = false;
                        }
                    }

                    if (!isValid) {
                        e.preventDefault();

                        // Show error alert
                        Swal.fire({
                            title: 'Validation Error',
                            text: 'Please fix the errors in the form before submitting.',
                            icon: 'error',
                            confirmButtonColor: '#9b59b6'
                        });
                    } else {
                        // Show confirmation for status changes
                        const currentStatus = '<?= $election['status'] ?>';
                        const newStatus = document.getElementById('status').value;

                        if (currentStatus !== newStatus) {
                            e.preventDefault();

                            let confirmationMessage = '';
                            switch (newStatus) {
                                case 'active':
                                    confirmationMessage = 'Changing status to Active will open the election for voting. Are you sure?';
                                    break;
                                case 'completed':
                                    confirmationMessage = 'Changing status to Completed will close the election. No further votes can be cast. Are you sure?';
                                    break;
                                case 'cancelled':
                                    confirmationMessage = 'Changing status to Cancelled will cancel the election. This action cannot be undone. Are you sure?';
                                    break;
                                default:
                                    form.submit();
                                    return;
                            }

                            Swal.fire({
                                title: 'Confirm Status Change',
                                text: confirmationMessage,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#9b59b6',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Yes, update status',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    form.submit();
                                }
                            });
                        }
                    }
                });
            }

            // Real-time date validation
            const startDatetime = document.getElementById('start_datetime');
            const endDatetime = document.getElementById('end_datetime');

            if (startDatetime && endDatetime) {
                startDatetime.addEventListener('change', validateDates);
                endDatetime.addEventListener('change', validateDates);
            }

            // Status change warning
            const statusSelect = document.getElementById('status');
            if (statusSelect) {
                statusSelect.addEventListener('change', function () {
                    const currentStatus = '<?= $election['status'] ?>';
                    const newStatus = this.value;

                    if (currentStatus === 'active' && newStatus !== 'active') {
                        // Show warning when changing from active
                        Swal.fire({
                            title: 'Status Change Warning',
                            text: 'Changing from Active status will close the election to voters.',
                            icon: 'warning',
                            confirmButtonColor: '#9b59b6'
                        });
                    }

                    if (currentStatus === 'completed' && newStatus !== 'completed') {
                        // Show warning when changing from completed
                        Swal.fire({
                            title: 'Status Change Warning',
                            text: 'Re-opening a completed election may affect vote integrity.',
                            icon: 'warning',
                            confirmButtonColor: '#9b59b6'
                        });
                    }
                });
            }

            // Helper functions
            function showError(element, message) {
                element.classList.add('is-invalid');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = message;
                element.parentNode.appendChild(errorDiv);
            }

            function clearErrors() {
                // Remove all error highlights
                const invalidElements = form.querySelectorAll('.is-invalid');
                invalidElements.forEach(el => {
                    el.classList.remove('is-invalid');
                });

                // Remove all error messages
                const errorMessages = form.querySelectorAll('.invalid-feedback');
                errorMessages.forEach(el => {
                    el.remove();
                });
            }

            function validateDates() {
                if (startDatetime.value && endDatetime.value) {
                    const startDate = new Date(startDatetime.value);
                    const endDate = new Date(endDatetime.value);

                    if (startDate >= endDate) {
                        endDatetime.classList.add('is-invalid');
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'End date must be after start date';
                        endDatetime.parentNode.appendChild(errorDiv);
                    } else {
                        endDatetime.classList.remove('is-invalid');
                        const errorDiv = endDatetime.parentNode.querySelector('.invalid-feedback');
                        if (errorDiv) errorDiv.remove();
                    }
                }
            }

            // Set minimum date for datetime inputs to current date
            const now = new Date();
            const timezoneOffset = now.getTimezoneOffset() * 60000;
            const localISOTime = new Date(now.getTime() - timezoneOffset).toISOString().slice(0, 16);

            if (startDatetime) {
                startDatetime.min = localISOTime;
            }

            if (endDatetime) {
                endDatetime.min = localISOTime;
            }

            // Handle URL parameters for notifications
            handleURLParameters();
        });

        // Function to handle URL parameters for notifications
        function handleURLParameters() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                const config = getAlertConfig(status);

                Swal.fire({
                    title: config.title,
                    text: message,
                    icon: config.icon,
                    iconColor: config.iconColor,
                    confirmButtonColor: config.confirmButtonColor,
                    background: config.background,
                    color: config.textColor,
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true,
                    showClass: {
                        popup: 'animate__animated animate__fadeInRight'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutRight'
                    }
                });

                // Clear URL parameters
                history.replaceState(null, null, window.location.pathname);
            }
        }

        function getAlertConfig(status) {
            const configs = {
                'success': {
                    icon: 'success',
                    title: 'Success!',
                    iconColor: '#28a745',
                    textColor: '#155724',
                    background: '#d4edda',
                    confirmButtonColor: '#28a745'
                },
                'error': {
                    icon: 'error',
                    title: 'Error!',
                    iconColor: '#dc3545',
                    textColor: '#721c24',
                    background: '#f8d7da',
                    confirmButtonColor: '#dc3545'
                }
            };
            return configs[status.toLowerCase()] || configs.error;
        }
    </script>
</body>

</html>