<?php
session_name('voter');
session_start();

// Uncomment for actual authentication
if (!isset($_SESSION['id']) && !isset($_SESSION['email'])) {
    $message = "Please login first";
    $status = "error";
    header("Location: index.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";
include "app/model/voters.php";
include "app/model/votes.php";
include "app/model/elections.php";

$sql = "SELECT contact, email, location FROM admin";
$stmt = $conn->prepare($sql);
$stmt->execute([]);

if ($stmt->rowCount() == 1) {
    $admin = $stmt->fetch();
} else {
    $admin = 0;
}

$id = $_SESSION['id'] ?? 1; // Default for testing
$email = $_SESSION['email'] ?? 'test@example.com'; // Default for testing

// Get voter by ID
$voter = get_voter_by_id($conn, $id);
if (!$voter) {
    session_destroy();
    header("Location: index.php?message=Voter not found&status=error");
    exit();
}

// Function to get total votes by voter
function get_total_votes_by_voter($conn, $voter_id) {
    try {
        $sql = "SELECT COUNT(*) as total FROM votes 
                WHERE voter_id = :voter_id 
                AND status IN ('verified', 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    } catch (PDOException $e) {
        error_log("Error getting total votes: " . $e->getMessage());
        return 0;
    }
}

// Function to get elections voter has voted in
function get_voted_elections($conn, $voter_id, $limit = 5) {
    try {
        $sql = "SELECT e.*, v.vote_timestamp 
                FROM votes v
                JOIN elections e ON v.election_id = e.id
                WHERE v.voter_id = :voter_id 
                AND v.status IN ('verified', 'pending')
                ORDER BY v.vote_timestamp DESC 
                LIMIT :limit";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':voter_id', $voter_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting voted elections: " . $e->getMessage());
        return [];
    }
}

// Function to get active elections for voter
function get_active_elections_for_voter($conn, $voter_id) {
    try {
        // Get all active elections
        $sql = "SELECT * FROM elections 
                WHERE status = 'active' 
                AND start_datetime <= NOW() 
                AND end_datetime >= NOW()
                ORDER BY end_datetime ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check which elections voter hasn't voted in
        $available_elections = [];
        foreach ($elections as $election) {
            $check_vote_sql = "SELECT id FROM votes 
                              WHERE voter_id = :voter_id 
                              AND election_id = :election_id 
                              AND status IN ('verified', 'pending')";
            $check_stmt = $conn->prepare($check_vote_sql);
            $check_stmt->execute([
                ':voter_id' => $voter_id,
                ':election_id' => $election['id']
            ]);

            if (!$check_stmt->fetch()) {
                $available_elections[] = $election;
            }
        }

        return $available_elections;
    } catch (PDOException $e) {
        error_log("Error getting active elections: " . $e->getMessage());
        return [];
    }
}

// Get voter statistics
$total_votes = get_total_votes_by_voter($conn, $id);
$recent_votes = get_voted_elections($conn, $id, 10);
$active_elections = get_active_elections_for_voter($conn, $id);
$active_elections_count = count($active_elections);

// Check if voter is verified
$is_verified = ($voter['status'] == 'verified');

// Get voter's age
try {
    $dob = new DateTime($voter['dob']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
} catch (Exception $e) {
    $age = 'N/A';
}

// Calculate account age
try {
    $created_at = new DateTime($voter['created_at']);
    $account_age = $now->diff($created_at);
    $account_age_years = $account_age->y;
    $account_age_months = $account_age->m;
    $account_age_days = $account_age->d;
} catch (Exception $e) {
    $account_age_years = $account_age_months = $account_age_days = 0;
}

// Handle form submissions
$update_success = false;
$update_message = '';
$update_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update basic profile information
        try {
            $full_name = htmlspecialchars(trim($_POST['full_name']));
            $contact = htmlspecialchars(trim($_POST['contact']));
            $address = htmlspecialchars(trim($_POST['address']));
            
            $sql = "UPDATE voters SET full_name = :full_name, contact = :contact, address = :address WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':full_name' => $full_name,
                ':contact' => $contact,
                ':address' => $address,
                ':id' => $id
            ]);
            
            $update_success = true;
            $update_message = "Profile updated successfully!";
            $update_status = "success";
            
            // Refresh voter data
            $voter = get_voter_by_id($conn, $id);
            
        } catch (PDOException $e) {
            $update_success = false;
            $update_message = "Error updating profile: " . $e->getMessage();
            $update_status = "error";
        }
    }
    
    if (isset($_POST['change_password'])) {
        // Change password
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            $sql = "SELECT password FROM voters WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && password_verify($current_password, $result['password'])) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 6) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $sql = "UPDATE voters SET password = :password WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':password' => $hashed_password,
                            ':id' => $id
                        ]);
                        
                        $update_success = true;
                        $update_message = "Password changed successfully!";
                        $update_status = "success";
                    } else {
                        $update_success = false;
                        $update_message = "Password must be at least 6 characters long";
                        $update_status = "error";
                    }
                } else {
                    $update_success = false;
                    $update_message = "New passwords do not match";
                    $update_status = "error";
                }
            } else {
                $update_success = false;
                $update_message = "Current password is incorrect";
                $update_status = "error";
            }
        } catch (PDOException $e) {
            $update_success = false;
            $update_message = "Error changing password: " . $e->getMessage();
            $update_status = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | SecureVote</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #1abc9c;
            --voter-color: #3498db;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --danger-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
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
            color: #333;
            min-height: 100vh;
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

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
        }

        .navbar-brand span {
            color: var(--voter-color);
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        .voter-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
        }

        .voter-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.8rem;
        }

        .voter-info {
            margin-right: 1rem;
        }

        .voter-name {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.95rem;
        }

        .voter-status {
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            display: inline-block;
            margin-top: 0.1rem;
        }

        .voter-status.verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .voter-status.pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .voter-status.suspended {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        /* Profile Content */
        .profile-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
        }

        /* Profile Sections */
        .profile-section {
            margin-bottom: 2rem;
        }

        .section-card {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-header h2 i {
            margin-right: 0.75rem;
            color: var(--voter-color);
        }

        /* Profile Overview */
        .profile-overview {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .profile-overview {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Avatar */
        .profile-avatar-container {
            text-align: center;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
            border: 5px solid white;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            background-color: #e9ecef;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Profile Details */
        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-group h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #f0f0f0;
        }

        .detail-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .detail-value {
            font-weight: 500;
            color: var(--primary-color);
            text-align: right;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--voter-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        /* Recent Activity */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }

        .activity-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #adb5bd;
        }

        /* Button Styles */
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--voter-color);
            border-color: var(--voter-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .btn-outline-primary {
            color: var(--voter-color);
            border-color: var(--voter-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--voter-color);
            border-color: var(--voter-color);
        }

        /* Tab Navigation */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 8px 8px 0 0;
            margin-right: 0.5rem;
        }

        .nav-tabs .nav-link:hover {
            color: var(--voter-color);
            border: none;
        }

        .nav-tabs .nav-link.active {
            color: var(--voter-color);
            background-color: rgba(52, 152, 219, 0.1);
            border: none;
            border-bottom: 3px solid var(--voter-color);
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .section-card {
                padding: 1.5rem;
            }

            .profile-stats {
                grid-template-columns: 1fr;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            #main-header {
                padding: 0 1rem;
            }

            .voter-info {
                display: none;
            }

            .profile-avatar {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .detail-value {
                text-align: left;
                margin-top: 0.25rem;
            }
        }

        /* Password Strength Indicator */
        .password-strength {
            height: 4px;
            margin-top: 0.5rem;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background-color: var(--danger-color);
            width: 25%;
        }

        .strength-medium {
            background-color: var(--warning-color);
            width: 50%;
        }

        .strength-strong {
            background-color: var(--success-color);
            width: 100%;
        }

        /* Verification Status */
        .verification-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .verification-badge.verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .verification-badge.pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .verification-badge.suspended {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header id="main-header">
        <div class="header-left">
            <a class="navbar-brand" href="#">
                <i class="fas fa-vote-yea text-primary me-2"></i>Secure<span>Vote</span>
            </a>
        </div>
        <div class="header-right">
            <div class="voter-profile dropdown">
                <div class="d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="voter-avatar">
                        <?= strtoupper(substr($voter['full_name'], 0, 2)) ?>
                    </div>
                    <div class="voter-info">
                        <div class="voter-name"><?= htmlspecialchars($voter['full_name']) ?></div>
                        <span class="voter-status <?= htmlspecialchars($voter['status']) ?>">
                            <?= ucfirst(htmlspecialchars($voter['status'])) ?>
                        </span>
                    </div>
                    <i class="fas fa-chevron-down ms-1 text-muted"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <h6 class="dropdown-header">Voter Account</h6>
                    </li>
                    <li><a class="dropdown-item" href="voter_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a class="dropdown-item" href="voter_profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="voting_history.php"><i class="fas fa-history"></i> Voting History</a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Profile Content -->
    <main class="profile-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Manage your personal information and account settings</p>
        </div>

        <?php if ($update_success && $update_message): ?>
            <div class="alert alert-<?= $update_status == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $update_status == 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($update_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                    <i class="fas fa-user-circle me-2"></i>Profile Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                    <i class="fas fa-lock me-2"></i>Security
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabsContent">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="profile-overview">
                    <!-- Profile Avatar and Stats -->
                    <div class="section-card">
                        <div class="profile-avatar-container">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($voter['full_name'], 0, 2)) ?>
                            </div>
                            <h3><?= htmlspecialchars($voter['full_name']) ?></h3>
                            <p class="text-muted mb-3">Voter ID: <?= htmlspecialchars($voter['id']) ?></p>
                            <div class="verification-badge <?= htmlspecialchars($voter['status']) ?>">
                                <i class="fas fa-<?= $voter['status'] == 'verified' ? 'check-circle' : ($voter['status'] == 'pending' ? 'clock' : 'ban') ?> me-2"></i>
                                <?= ucfirst(htmlspecialchars($voter['status'])) ?> Account
                            </div>
                            
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?= $total_votes ?></div>
                                    <div class="stat-label">Total Votes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= $active_elections_count ?></div>
                                    <div class="stat-label">Active Elections</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?= $account_age_years ?></div>
                                    <div class="stat-label">Years Member</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Details -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2><i class="fas fa-info-circle"></i>Personal Information</h2>
                        </div>
                        
                        <div class="profile-details">
                            <div class="detail-group">
                                <h4>Basic Information</h4>
                                <div class="detail-item">
                                    <span class="detail-label">Full Name:</span>
                                    <span class="detail-value"><?= htmlspecialchars($voter['full_name']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email Address:</span>
                                    <span class="detail-value"><?= htmlspecialchars($voter['email']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone Number:</span>
                                    <span class="detail-value"><?= htmlspecialchars($voter['contact']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Date of Birth:</span>
                                    <span class="detail-value">
                                        <?= date('F j, Y', strtotime($voter['dob'])) ?> (Age: <?= $age ?>)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <h4>Account Information</h4>
                                <div class="detail-item">
                                    <span class="detail-label">Account Status:</span>
                                    <span class="detail-value">
                                        <span class="voter-status <?= htmlspecialchars($voter['status']) ?>">
                                            <?= ucfirst(htmlspecialchars($voter['status'])) ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Member Since:</span>
                                    <span class="detail-value"><?= date('F j, Y', strtotime($voter['created_at'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Account Age:</span>
                                    <span class="detail-value">
                                        <?= $account_age_years ?> year<?= $account_age_years != 1 ? 's' : '' ?>, 
                                        <?= $account_age_months ?> month<?= $account_age_months != 1 ? 's' : '' ?>, 
                                        <?= $account_age_days ?> day<?= $account_age_days != 1 ? 's' : '' ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Updated:</span>
                                    <span class="detail-value">
                                        <?= !empty($voter['updated_at']) ? date('F j, Y g:i A', strtotime($voter['updated_at'])) : 'Never' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <h4>Address Information</h4>
                                <div class="detail-item">
                                    <span class="detail-label">Address:</span>
                                    <span class="detail-value"><?= nl2br(htmlspecialchars($voter['address'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i>Recent Voting Activity</h2>
                    </div>
                    
                    <?php if (!empty($recent_votes)): ?>
                        <div class="activity-list">
                            <?php foreach ($recent_votes as $vote): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-vote-yea"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            Voted in: <?= htmlspecialchars($vote['title']) ?>
                                        </div>
                                        <div class="activity-description">
                                            Election period: 
                                            <?= date('M j', strtotime($vote['start_datetime'])) ?> - 
                                            <?= date('M j, Y', strtotime($vote['end_datetime'])) ?>
                                        </div>
                                        <div class="activity-time">
                                            <i class="far fa-clock me-1"></i>
                                            <?= date('F j, Y g:i A', strtotime($vote['vote_timestamp'])) ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-success">Completed</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-vote-yea fa-3x text-muted mb-3"></i>
                            <h4>No Voting Activity Yet</h4>
                            <p class="text-muted">You haven't participated in any elections yet.</p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-poll-h me-2"></i>View Active Elections
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Profile Tab -->
            <div class="tab-pane fade" id="edit" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-user-edit"></i>Edit Personal Information</h2>
                    </div>
                    
                    <form method="POST" action="" id="editProfileForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?= htmlspecialchars($voter['full_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?= htmlspecialchars($voter['email']) ?>" readonly disabled>
                                    <small class="text-muted">Email cannot be changed. Contact support for assistance.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="contact" name="contact" 
                                           value="<?= htmlspecialchars($voter['contact']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="dob" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="dob" 
                                           value="<?= htmlspecialchars($voter['dob']) ?>" readonly disabled>
                                    <small class="text-muted">Date of birth cannot be changed.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($voter['address']) ?></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Fields marked with * are required. Some information cannot be changed for security reasons.
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Reset</button>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-shield-alt"></i>Change Password</h2>
                    </div>
                    
                    <form method="POST" action="" id="changePasswordForm">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordStrength" class="password-strength d-none"></div>
                            <small class="text-muted">Password must be at least 6 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-2"></div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            After changing your password, you will be logged out from all devices.
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Reset</button>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Account Security -->
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-user-shield"></i>Account Security</h2>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="alert-heading mb-2">Security Tips</h5>
                                <ul class="mb-0">
                                    <li>Use a strong, unique password that you don't use elsewhere</li>
                                    <li>Never share your password with anyone</li>
                                    <li>Log out after each session, especially on shared computers</li>
                                    <li>Contact support immediately if you suspect unauthorized access</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="voting_history.php" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i>View Voting History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-vote-yea me-2"></i>SecureVote</h5>
                    <p>Your voice, your choice. Participate in democratic processes securely.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="voter_dashboard.php" class="text-light text-decoration-none">Dashboard</a></li>
                        <li><a href="voter_profile.php" class="text-light text-decoration-none">My Profile</a></li>
                        <li><a href="voting_history.php" class="text-light text-decoration-none">Voting History</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Need Help?</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i><?= $admin['email'] ?></li>
                        <li><i class="fas fa-phone me-2"></i><?= $admin['contact'] ?></li>
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
            // Password toggle functionality
            function setupPasswordToggle(passwordId, toggleId) {
                const passwordInput = document.getElementById(passwordId);
                const toggleButton = document.getElementById(toggleId);
                
                if (passwordInput && toggleButton) {
                    toggleButton.addEventListener('click', function() {
                        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        passwordInput.setAttribute('type', type);
                        const icon = this.querySelector('i');
                        icon.classList.toggle('fa-eye');
                        icon.classList.toggle('fa-eye-slash');
                    });
                }
            }
            
            // Setup password toggles
            setupPasswordToggle('current_password', 'toggleCurrentPassword');
            setupPasswordToggle('new_password', 'toggleNewPassword');
            setupPasswordToggle('confirm_password', 'toggleConfirmPassword');
            
            // Password strength checker
            const newPasswordInput = document.getElementById('new_password');
            const passwordStrength = document.getElementById('passwordStrength');
            
            if (newPasswordInput && passwordStrength) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 6) strength++;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                    if (password.match(/\d/)) strength++;
                    if (password.match(/[^a-zA-Z\d]/)) strength++;
                    
                    passwordStrength.classList.remove('d-none');
                    passwordStrength.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                    
                    if (strength < 2) {
                        passwordStrength.classList.add('strength-weak');
                    } else if (strength < 4) {
                        passwordStrength.classList.add('strength-medium');
                    } else {
                        passwordStrength.classList.add('strength-strong');
                    }
                });
            }
            
            // Password match checker
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (newPasswordInput && confirmPasswordInput && passwordMatch) {
                function checkPasswordMatch() {
                    const password = newPasswordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (!password && !confirmPassword) {
                        passwordMatch.innerHTML = '';
                        return;
                    }
                    
                    if (password === confirmPassword) {
                        passwordMatch.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Passwords match</span>';
                    } else {
                        passwordMatch.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Passwords do not match</span>';
                    }
                }
                
                newPasswordInput.addEventListener('input', checkPasswordMatch);
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }
            
            // Form validation
            const editProfileForm = document.getElementById('editProfileForm');
            const changePasswordForm = document.getElementById('changePasswordForm');
            
            if (editProfileForm) {
                editProfileForm.addEventListener('submit', function(e) {
                    const fullName = document.getElementById('full_name').value.trim();
                    const contact = document.getElementById('contact').value.trim();
                    
                    if (!fullName || !contact) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Missing Information',
                            text: 'Please fill in all required fields.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
            
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const currentPassword = document.getElementById('current_password').value;
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (!currentPassword || !newPassword || !confirmPassword) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Missing Information',
                            text: 'Please fill in all password fields.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Password Too Short',
                            text: 'Password must be at least 6 characters long.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Passwords Don\'t Match',
                            text: 'New password and confirm password must match.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    // Show confirmation
                    e.preventDefault();
                    Swal.fire({
                        title: 'Change Password?',
                        text: 'You will be logged out from all devices after changing your password.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, change password',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            changePasswordForm.submit();
                        }
                    });
                });
            }
            
            // Initialize Bootstrap tabs
            const triggerTabList = document.querySelectorAll('#profileTabs button');
            triggerTabList.forEach(triggerEl => {
                const tabTrigger = new bootstrap.Tab(triggerEl);
                triggerEl.addEventListener('click', event => {
                    event.preventDefault();
                    tabTrigger.show();
                });
            });
            
            // Initialize Bootstrap dropdowns
            var dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(function(dropdown) {
                new bootstrap.Dropdown(dropdown);
            });
            
            // Check URL for tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.getElementById(tab + '-tab');
                if (tabElement) {
                    const tabTrigger = new bootstrap.Tab(tabElement);
                    tabTrigger.show();
                }
            }
        });
    </script>
</body>

</html>