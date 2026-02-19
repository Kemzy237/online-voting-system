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
include "app/model/candidates.php";

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

// Function to get active elections for voter - FIXED
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

// Function to get elections voter has voted in - FIXED
function get_voted_elections($conn, $voter_id) {
    try {
        $sql = "SELECT e.*, v.vote_timestamp 
                FROM votes v
                JOIN elections e ON v.election_id = e.id
                WHERE v.voter_id = :voter_id 
                AND v.status IN ('verified', 'pending')
                ORDER BY v.vote_timestamp DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting voted elections: " . $e->getMessage());
        return [];
    }
}

// Function to get upcoming elections
function get_all_upcoming_elections($conn) {
    try {
        $sql = "SELECT * FROM elections 
                WHERE status IN ('upcoming', 'draft')
                AND start_datetime > NOW()
                ORDER BY start_datetime ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting upcoming elections: " . $e->getMessage());
        return [];
    }
}

// Function to get recent votes by voter - FIXED
function get_recent_votes_by_voter($conn, $voter_id, $limit = 5) {
    try {
        $sql = "SELECT v.*, e.title as election_title
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
        error_log("Error getting recent votes: " . $e->getMessage());
        return [];
    }
}

// Function to get total votes by voter - FIXED
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

// Get data using the functions
try {
    $active_elections = get_active_elections_for_voter($conn, $id);
    $voted_elections = get_voted_elections($conn, $id);
    $upcoming_elections = get_all_upcoming_elections($conn);
    $recent_votes = get_recent_votes_by_voter($conn, $id, 5);
    $total_votes_by_voter = get_total_votes_by_voter($conn, $id);
} catch (Exception $e) {
    error_log("Error fetching dashboard data: " . $e->getMessage());
    $active_elections = [];
    $voted_elections = [];
    $upcoming_elections = [];
    $recent_votes = [];
    $total_votes_by_voter = 0;
}

// Check if voter is verified
$is_verified = ($voter['status'] == 'verified');

// Get voter's age
try {
    $dob = new DateTime($voter['dob']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
} catch (Exception $e) {
    $age = 'N/A';
    error_log("Error calculating age: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard | SecureVote</title>
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
            --sidebar-width: 260px;
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

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
        }

        .welcome-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-card p {
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Voter Info Card */
        .voter-info-card {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .voter-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .voter-info-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .voter-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            margin-bottom: 1rem;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-cards {
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }

        .stat-icon.active-polls {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
        }

        .stat-icon.votes {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.upcoming {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stat-icon.verified {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        /* Election Cards */
        .election-section {
            margin-bottom: 2.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .election-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .election-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .election-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .election-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .election-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .election-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .election-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .election-status.active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .election-status.upcoming {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
        }

        .election-status.completed {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .election-body {
            padding: 1.5rem;
        }

        .election-info {
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-label {
            color: #6c757d;
        }

        .info-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .action-btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            text-align: center;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .action-btn.vote {
            background-color: var(--voter-color);
            color: white;
        }

        .action-btn.vote:hover {
            background-color: #2980b9;
            color: white;
        }

        .action-btn.view {
            background-color: #f8f9fa;
            color: var(--primary-color);
            border: 1px solid #dee2e6;
        }

        .action-btn.view:hover {
            background-color: #e9ecef;
            color: var(--primary-color);
        }

        .action-btn.disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
            cursor: not-allowed;
        }

        /* Recent Votes */
        .recent-votes {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .recent-votes h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .vote-item {
            display: flex;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .vote-item:last-child {
            border-bottom: none;
        }

        .vote-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .vote-content {
            flex: 1;
        }

        .vote-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }

        .vote-time {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            min-width: 200px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .dropdown-item i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }

        .dropdown-divider {
            margin: 0.5rem 0;
        }

        /* Alert Banner */
        .alert-banner {
            background-color: rgba(241, 196, 15, 0.1);
            border: 1px solid rgba(241, 196, 15, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .alert-banner i {
            color: var(--warning-color);
            font-size: 1.2rem;
            margin-right: 0.75rem;
        }

        .alert-banner p {
            margin: 0;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        .empty-state h4 {
            margin-bottom: 0.5rem;
            color: #6c757d;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-content {
                padding: 1.5rem;
            }

            .election-cards {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .dashboard-content {
                padding: 1rem;
            }

            .welcome-card {
                padding: 1.5rem;
            }

            .welcome-card h2 {
                font-size: 1.5rem;
            }

            .voter-info-card {
                padding: 1.5rem;
            }

            .header-left h1 {
                font-size: 1.2rem;
            }

            .voter-info {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .election-cards {
                grid-template-columns: 1fr;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            #main-header {
                padding: 0 1rem;
            }
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
                    <li><a class="dropdown-item" href="voting_history.php"><i class="fas fa-history"></i> Voting History</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Dashboard Content -->
    <main class="dashboard-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Welcome, <?= htmlspecialchars($voter['full_name']) ?>!</h2>
            <p>Your voice matters. Cast your vote in ongoing elections and make a difference.</p>
        </div>

        <!-- Alert for unverified voters -->
        <?php if (!$is_verified): ?>
            <div class="alert-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Your account is pending verification. You will be able to vote once your account is verified by the
                    administrator.</p>
            </div>
        <?php endif; ?>

        <!-- Voter Info Card -->
        <div class="voter-info-card">
            <div class="voter-info-header">
                <h3>Your Voter Information</h3>
                <span class="voter-status <?= htmlspecialchars($voter['status']) ?>">
                    <?= ucfirst(htmlspecialchars($voter['status'])) ?>
                </span>
            </div>
            <div class="voter-details">
                <div class="detail-item">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value"><?= htmlspecialchars($voter['full_name']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value"><?= htmlspecialchars($voter['email']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-value"><?= date('F j, Y', strtotime($voter['dob'])) ?> (Age: <?= $age ?>)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value"><?= htmlspecialchars($voter['contact']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?= htmlspecialchars($voter['address']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Member Since</div>
                    <div class="detail-value"><?= date('F j, Y', strtotime($voter['created_at'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row stats-cards">
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon active-polls">
                        <i class="fas fa-poll-h"></i>
                    </div>
                    <div class="stat-number"><?= count($active_elections) ?></div>
                    <div class="stat-label">Active Polls Available</div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon votes">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="stat-number"><?= $total_votes_by_voter ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon upcoming">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-number"><?= count($upcoming_elections) ?></div>
                    <div class="stat-label">Upcoming Elections</div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon verified">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-label">Account Status</div>
                    <div class="voter-status <?= htmlspecialchars($voter['status']) ?> mt-2">
                        <?= ucfirst(htmlspecialchars($voter['status'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Elections Section -->
        <div class="election-section">
            <div class="section-header">
                <h3>Active Elections</h3>
                <span class="text-muted"><?= count($active_elections) ?> available</span>
            </div>

            <?php if (!empty($active_elections) && $is_verified): ?>
                <div class="election-cards">
                    <?php foreach ($active_elections as $election):
                        try {
                            $time_remaining = strtotime($election['end_datetime']) - time();
                            $days_remaining = floor($time_remaining / (60 * 60 * 24));
                            $hours_remaining = floor(($time_remaining % (60 * 60 * 24)) / (60 * 60));
                        } catch (Exception $e) {
                            $days_remaining = 0;
                            $hours_remaining = 0;
                        }
                        ?>
                        <div class="election-card">
                            <div class="election-header">
                                <span class="election-status active">Active</span>
                                <h4 class="election-title"><?= htmlspecialchars($election['title']) ?></h4>
                                <?php if (!empty($election['description'])): ?>
                                    <p class="election-description">
                                        <?= htmlspecialchars(substr($election['description'], 0, 100)) ?>
                                        <?= strlen($election['description']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="election-body">
                                <div class="election-info">
                                    <div class="info-item">
                                        <span class="info-label">Starts:</span>
                                        <span class="info-value">
                                            <?= date('M j, Y g:i A', strtotime($election['start_datetime'])) ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Ends:</span>
                                        <span class="info-value">
                                            <?= date('M j, Y g:i A', strtotime($election['end_datetime'])) ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Time Remaining:</span>
                                        <span class="info-value">
                                            <?= $days_remaining > 0 ? $days_remaining . ' day' . ($days_remaining != 1 ? 's' : '') . ', ' : '' ?>
                                            <?= $hours_remaining ?> hour<?= $hours_remaining != 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                </div>
                                <a href="vote.php?election_id=<?= $election['id'] ?>" class="action-btn vote">
                                    <i class="fas fa-vote-yea me-2"></i>Vote Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$is_verified): ?>
                <div class="empty-state">
                    <i class="fas fa-user-check"></i>
                    <h4>Account Verification Required</h4>
                    <p>Your account needs to be verified before you can participate in elections.</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-poll-h"></i>
                    <h4>No Active Elections</h4>
                    <p>There are currently no active elections available for voting.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Votes Section -->
        <?php if (!empty($recent_votes)): ?>
            <div class="recent-votes">
                <h3>Your Recent Votes</h3>
                <?php foreach ($recent_votes as $vote): ?>
                    <div class="vote-item">
                        <div class="vote-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="vote-content">
                            <div class="vote-title"><?= htmlspecialchars($vote['election_title']) ?></div>
                            <div class="vote-time">
                                Voted on <?= date('F j, Y g:i A', strtotime($vote['vote_timestamp'])) ?>
                            </div>
                        </div>
                        <span class="voter-status <?= htmlspecialchars($vote['status']) ?>">
                            <?= ucfirst(htmlspecialchars($vote['status'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Upcoming Elections Section -->
        <?php if (!empty($upcoming_elections)): ?>
            <div class="election-section">
                <div class="section-header">
                    <h3>Upcoming Elections</h3>
                    <span class="text-muted"><?= count($upcoming_elections) ?> scheduled</span>
                </div>
                <div class="election-cards">
                    <?php foreach ($upcoming_elections as $election):
                        try {
                            $time_until = strtotime($election['start_datetime']) - time();
                            $days_until = floor($time_until / (60 * 60 * 24));
                        } catch (Exception $e) {
                            $days_until = 0;
                        }
                        ?>
                        <div class="election-card">
                            <div class="election-header">
                                <span class="election-status upcoming">Upcoming</span>
                                <h4 class="election-title"><?= htmlspecialchars($election['title']) ?></h4>
                                <?php if (!empty($election['description'])): ?>
                                    <p class="election-description">
                                        <?= htmlspecialchars(substr($election['description'], 0, 100)) ?>
                                        <?= strlen($election['description']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="election-body">
                                <div class="election-info">
                                    <div class="info-item">
                                        <span class="info-label">Starts In:</span>
                                        <span class="info-value">
                                            <?= $days_until > 0 ? $days_until . ' day' . ($days_until != 1 ? 's' : '') : 'Less than a day' ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Start Date:</span>
                                        <span class="info-value">
                                            <?= date('M j, Y g:i A', strtotime($election['start_datetime'])) ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">End Date:</span>
                                        <span class="info-value">
                                            <?= date('M j, Y g:i A', strtotime($election['end_datetime'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <button class="action-btn disabled" disabled>
                                    <i class="fas fa-clock me-2"></i>Starts Soon
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Completed Elections Section -->
        <?php if (!empty($voted_elections)): ?>
            <div class="election-section">
                <div class="section-header">
                    <h3>Elections You've Voted In</h3>
                    <span class="text-muted"><?= count($voted_elections) ?> completed</span>
                </div>
                <div class="election-cards">
                    <?php foreach (array_slice($voted_elections, 0, 3) as $election): ?>
                        <div class="election-card">
                            <div class="election-header">
                                <span class="election-status completed">Completed</span>
                                <h4 class="election-title"><?= htmlspecialchars($election['title']) ?></h4>
                            </div>
                            <div class="election-body">
                                <div class="election-info">
                                    <div class="info-item">
                                        <span class="info-label">Voted On:</span>
                                        <span class="info-value">
                                            <?= date('M j, Y g:i A', strtotime($election['vote_timestamp'])) ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Election Period:</span>
                                        <span class="info-value">
                                            <?= date('M j', strtotime($election['start_datetime'])) ?> -
                                            <?= date('M j, Y', strtotime($election['end_datetime'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <a href="results.php?election_id=<?= $election['id'] ?>" class="action-btn view">
                                    <i class="fas fa-chart-bar me-2"></i>View Results
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row" style="display: flex; justify-content: space-between;">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-vote-yea me-2"></i>SecureVote</h5>
                    <p>Your voice, your choice. Participate in democratic processes securely.</p>
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
            // Check for URL parameters for notifications
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                Swal.fire({
                    title: status.charAt(0).toUpperCase() + status.slice(1),
                    text: message,
                    icon: status,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6',
                    showCloseButton: true
                });

                // Clean URL
                history.replaceState(null, null, window.location.pathname);
            }

            // Auto-refresh page every 30 seconds to check for new elections
            setInterval(() => {
                // Only refresh if there are active elections
                const activeElections = document.querySelector('.election-status.active');
                if (activeElections) {
                    location.reload();
                }
            }, 30000);

            // Add click handlers for dropdown items
            document.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (this.getAttribute('href') === '#') {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Coming Soon',
                            text: 'This feature is currently under development.',
                            icon: 'info',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });

            // Add hover effects to election cards
            document.querySelectorAll('.election-card').forEach(card => {
                card.addEventListener('mouseenter', function () {
                    this.style.transform = 'translateY(-5px)';
                });

                card.addEventListener('mouseleave', function () {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Initialize Bootstrap dropdowns
            var dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(function(dropdown) {
                new bootstrap.Dropdown(dropdown);
            });
        });
    </script>
</body>

</html>