<?php
session_name("admin");
session_start();
if(!isset($_SESSION['role']) && !isset($_SESSION['id'])){
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";
include "app/model/elections.php";
include "app/model/candidates.php";
include "app/model/votes.php";
include "app/model/voters.php";

// Check if election ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $message = "No election specified";
    $status = "error";
    header("Location: election_polls.php?message=$message&status=$status");
    exit();
}

$election_id = $_GET['id'];

// Get election data
$election = get_election_by_id($conn, $election_id);
if($election == 0) {
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

// Get election statistics
$statistics = get_election_statistics($conn, $election_id);
if($statistics == 0) {
    // If no statistics function, create basic stats
    $statistics = [
        'total_candidates' => 0,
        'total_voters_registered' => 0,
        'total_votes_cast' => 0
    ];
}

// Get candidates for this election
$candidates = get_candidates_by_election($conn, $election_id);
if($candidates == 0) {
    $num_candidates = 0;
} else {
    $num_candidates = count($candidates);
}

// Get recent votes for this election
$recent_votes = get_recent_votes_by_election($conn, $election_id);
if($recent_votes == 0) {
    $num_recent_votes = 0;
} else {
    $num_recent_votes = count($recent_votes);
}

// Calculate time remaining
$now = new DateTime();
$start_date = new DateTime($election['start_datetime']);
$end_date = new DateTime($election['end_datetime']);

$time_remaining = '';
$is_active = false;
$is_upcoming = false;
$is_completed = false;

if($election['status'] == 'active') {
    $is_active = true;
    if($now < $end_date) {
        $interval = $now->diff($end_date);
        $time_remaining = $interval->format('%a days, %h hours, %i minutes remaining');
    } else {
        $time_remaining = 'Ended';
    }
} elseif($election['status'] == 'upcoming') {
    $is_upcoming = true;
    $interval = $now->diff($start_date);
    $time_remaining = $interval->format('%a days, %h hours, %i minutes until start');
} elseif($election['status'] == 'completed') {
    $is_completed = true;
    $time_remaining = 'Election completed';
}

// Format dates for display
$created_date = date('F d, Y \a\t h:i A', strtotime($election['created_at']));
$start_date_formatted = date('F d, Y \a\t h:i A', strtotime($election['start_datetime']));
$end_date_formatted = date('F d, Y \a\t h:i A', strtotime($election['end_datetime']));

// Calculate voting percentage
$total_voters = count(get_all_verified_voters($conn)) ?: 1;
$num_votes_cast = get_election_votes($conn, $election['id']);
if ($num_votes_cast != 0) {
    $votes_cast = count($num_votes_cast);
} else {
    $votes_cast = 0;
}
$voting_percentage = ($votes_cast / $total_voters) * 100;
$voting_percentage = min($voting_percentage, 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Election | SecureVote Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .sidebar-menu a:hover, .sidebar-menu a.active_3 {
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

        /* Election Header Card */
        .election-header-card {
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(155, 89, 182, 0.2);
        }

        .election-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .election-id {
            opacity: 0.9;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .time-remaining {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            margin-top: 1rem;
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
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.voters {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .stat-icon.votes {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.candidates {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .stat-icon.percentage {
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

        /* Info Cards */
        .info-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .info-item {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f8f9fa;
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            min-width: 180px;
        }

        .info-value {
            color: #6c757d;
        }

        /* Progress Bar */
        .progress-container {
            background-color: #e9ecef;
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .progress-active {
            background-color: var(--success-color);
        }

        .progress-upcoming {
            background-color: var(--warning-color);
        }

        .progress-completed {
            background-color: var(--admin-color);
        }

        /* Candidates Table */
        .candidate-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .candidate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .candidate-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .candidate-avatar {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .candidate-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0 0 0.25rem 0;
        }

        .candidate-party {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .candidate-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .vote-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--admin-color);
        }

        .vote-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Votes Table */
        .table-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .table-responsive {
            padding: 0 1.5rem 1.5rem;
        }

        /* Chart Container */
        .chart-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

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
            color: white;
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
            
            .election-header-card {
                padding: 1.5rem;
            }
            
            .election-title {
                font-size: 1.6rem;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                min-width: auto;
                margin-bottom: 0.25rem;
            }
            
            .candidate-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .candidate-avatar {
                margin-bottom: 1rem;
                margin-right: 0;
            }
        }

        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
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
                    icon: "info",
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6',
                    showCloseButton: true,
                    allowOutsideClick: false,
                    allowEscapeKey: true
                });

                history.replaceState(null, null, window.location.pathname);
                // history.replaceState(null, null, window.location.pathname + '?election_id=<?= $election_id ?>');
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
                <button id="mobile-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>View Election</h1>
            </div>
            <div class="action-buttons">
                <a href="election_polls.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i> Back to Elections
                </a>
                <a href="edit_election.php?id=<?= $election['id'] ?>" class="btn btn-purple btn-sm">
                    <i class="fas fa-edit me-2"></i> Edit Election
                </a>
            </div>
        </header>
        
        <!-- Election Header -->
        <div class="election-header-card">
            <h1 class="election-title"><?= htmlspecialchars($election['title']) ?></h1>
            <div class="election-id">Election ID: #<?= $election['id'] ?></div>
            <?php if(!empty($election['description'])): ?>
            <p class="mb-3"><?= htmlspecialchars($election['description']) ?></p>
            <?php endif; ?>
            <?php if($time_remaining): ?>
            <div class="time-remaining">
                <i class="fas fa-clock me-2"></i><?= $time_remaining ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Stats Cards -->
            <div class="row stats-cards">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon voters">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?= $total_voters ?></div>
                        <div class="stat-label">Registered Voters</div>
                        <div class="small text-muted">Eligible to vote</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon votes">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-number"><?= $votes_cast ?></div>
                        <div class="stat-label">Total Votes Cast</div>
                        <div class="small text-muted">Verified votes</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon candidates">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-number"><?= $num_candidates ?></div>
                        <div class="stat-label">Candidates</div>
                        <div class="small text-muted">Running in election</div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon percentage">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number"><?= number_format($voting_percentage, 1) ?>%</div>
                        <div class="stat-label">Voter Turnout</div>
                        <div class="small text-muted">Participation rate</div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Row -->
            <div class="row">
                <!-- Left Column: Election Details -->
                <div class="col-lg-8 mb-4">
                    <!-- Election Details Card -->
                    <div class="info-card">
                        <div class="card-header">
                            <h3><i class="fas fa-info-circle me-2"></i> Election Details</h3>
                            <?php 
                            $status_badge = '';
                            switch($election['status']) {
                                case 'draft': $status_badge = 'status-draft'; break;
                                case 'upcoming': $status_badge = 'status-upcoming'; break;
                                case 'active': $status_badge = 'status-active'; break;
                                case 'completed': $status_badge = 'status-completed'; break;
                                case 'cancelled': $status_badge = 'status-cancelled'; break;
                                default: $status_badge = 'status-draft';
                            }
                            ?>
                            <span class="status-badge <?= $status_badge ?>"><?= ucfirst($election['status']) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Election ID:</div>
                            <div class="info-value"><strong>#<?= $election['id'] ?></strong></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Created On:</div>
                            <div class="info-value"><?= $created_date ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Start Date & Time:</div>
                            <div class="info-value"><?= $start_date_formatted ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">End Date & Time:</div>
                            <div class="info-value"><?= $end_date_formatted ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Voting Progress:</div>
                            <div class="info-value">
                                <div class="d-flex justify-content-between">
                                    <span><?= $votes_cast ?> of <?= $total_voters ?> votes</span>
                                    <span><?= number_format($voting_percentage, 1) ?>%</span>
                                </div>
                                <div class="progress-container mt-1">
                                    <div class="progress-bar <?= $is_active ? 'progress-active' : ($is_upcoming ? 'progress-upcoming' : 'progress-completed') ?>" 
                                         style="width: <?= $voting_percentage ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if(!empty($election['description'])): ?>
                        <div class="info-item">
                            <div class="info-label">Description:</div>
                            <div class="info-value"><?= htmlspecialchars($election['description']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Candidates Section -->
                    <div class="info-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-tie me-2"></i> Candidates (<?= $num_candidates ?>)</h3>
                            <a href="manage_candidates.php?id=<?= $election['id'] ?>" class="btn btn-sm btn-purple">
                                <i class="fas fa-plus me-1"></i> Add Candidate
                            </a>
                        </div>
                        
                        <?php if($candidates != 0): ?>
                            <div class="row">
                                <?php foreach($candidates as $candidate): 
                                    // Get candidate initials
                                    $voter_name = get_voter_name_by_id($conn, $candidate['voter_id']);
                                    $initials = '';
                                    if($voter_name) {
                                        $name_parts = explode(' ', $voter_name);
                                        if(count($name_parts) >= 2) {
                                            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
                                        } else {
                                            $initials = strtoupper(substr($voter_name, 0, 2));
                                        }
                                    } else {
                                        $initials = '??';
                                    }
                                    
                                    // Calculate vote percentage
                                    $data = array($election_id, $candidate['id']);
                                    $candidate_votes = get_candidate_votes($conn, $data);
                                    if($candidate_votes != 0){
                                        $candidate_votes = count($candidate_votes);
                                    }
                                    $vote_percentage = $votes_cast > 0 ? ($candidate_votes / $votes_cast) * 100 : 0;
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="candidate-card">
                                        <div class="candidate-header">
                                            <div class="candidate-avatar">
                                                <?= $initials ?>
                                            </div>
                                            <div class="candidate-info">
                                                <h4><?= htmlspecialchars($voter_name ?: 'Unknown Candidate') ?></h4>
                                                <?php if(!empty($candidate['party_affiliation'])): ?>
                                                <div class="candidate-party">
                                                    <i class="fas fa-flag me-1"></i><?= htmlspecialchars($candidate['party_affiliation']) ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if($candidate['status'] != 'approved'): ?>
                                                <span class="badge bg-warning mt-1"><?= ucfirst($candidate['status']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if(!empty($candidate['campaign_statement'])): ?>
                                        <p class="small text-muted mb-2"><?= substr(htmlspecialchars($candidate['campaign_statement']), 0, 100) ?>...</p>
                                        <?php endif; ?>
                                        
                                        <div class="candidate-stats">
                                            <div>
                                                <div class="vote-count"><?= $candidate_votes ?></div>
                                                <div class="vote-label">Votes</div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-semibold"><?= number_format($vote_percentage, 1) ?>%</div>
                                                <div class="vote-label">of total</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-tie fa-2x text-muted mb-3"></i>
                                <h5>No Candidates</h5>
                                <p class="text-muted">No candidates have been added to this election yet.</p>
                                <a href="manage_candidates.php?id=<?= $election['id'] ?>" class="btn btn-purple">
                                    <i class="fas fa-plus me-2"></i> Add First Candidate
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column: Charts & Recent Activity -->
                <div class="col-lg-4 mb-4">
                    <!-- Quick Actions Card -->
                    <div class="info-card">
                        <div class="card-header">
                            <h3><i class="fas fa-bolt me-2"></i> Quick Actions</h3>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="edit_election.php?id=<?= $election['id'] ?>" class="btn btn-purple">
                                <i class="fas fa-edit me-2"></i> Edit Election
                            </a>
                            <a href="manage_candidates.php?id=<?= $election['id'] ?>" class="btn btn-outline-purple">
                                <i class="fas fa-user-tie me-2"></i> Manage Candidates
                            </a>
                            <a href="election_results.php?id=<?= $election['id'] ?>" class="btn btn-outline-purple">
                                <i class="fas fa-chart-bar me-2"></i> View Results
                            </a>
                            <a href="view_all_votes.php?id=<?= $election['id'] ?>" class="btn btn-outline-purple">
                                <i class="fas fa-eye me-2"></i> View All Votes
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recent Votes Card -->
                    <div class="info-card">
                        <div class="card-header">
                            <h3><i class="fas fa-history me-2"></i> Recent Votes</h3>
                        </div>
                        
                        <?php if($recent_votes != 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_votes as $vote): 
                                    $voter_name = get_voter_name_by_id($conn, $vote['voter_id']);
                                    $candidate_name = get_candidate_name_by_id($conn, $vote['candidate_id']);
                                    $vote_time = date('d M, H:i', strtotime($vote['vote_timestamp']));
                                ?>
                                <div class="list-group-item border-0 px-0 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-semibold small"><?= htmlspecialchars($voter_name ?: 'Unknown Voter') ?></div>
                                            <div class="text-muted small">Voted for <?= htmlspecialchars($candidate_name ?: 'Unknown Candidate') ?></div>
                                        </div>
                                        <div class="text-muted small"><?= $vote_time ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-vote-yea fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No votes cast yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Initialize Status Chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                const statusData = {
                    labels: ['Votes Cast', 'Remaining Votes'],
                    datasets: [{
                        data: [<?= $votes_cast ?>, <?= max(0, $total_voters - $votes_cast) ?>],
                        backgroundColor: [
                            'rgba(155, 89, 182, 0.8)',
                            'rgba(201, 203, 207, 0.8)'
                        ],
                        borderColor: [
                            'rgba(155, 89, 182, 1)',
                            'rgba(201, 203, 207, 1)'
                        ],
                        borderWidth: 1
                    }]
                };

                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: statusData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }

            // Handle URL parameters for notifications
            // handleURLParameters();
        });

        // Function to handle URL parameters for notifications
        // function handleURLParameters() {
        //     const urlParams = new URLSearchParams(window.location.search);
        //     const status = urlParams.get('status');
        //     const message = urlParams.get('message');
            
        //     if (status && message) {
        //         const config = getAlertConfig(status);
                
        //         Swal.fire({
        //             title: config.title,
        //             text: message,
        //             icon: config.icon,
        //             iconColor: config.iconColor,
        //             confirmButtonColor: config.confirmButtonColor,
        //             background: config.background,
        //             color: config.textColor,
        //             timer: 5000,
        //             timerProgressBar: true,
        //             showConfirmButton: false,
        //             position: 'top-end',
        //             toast: true,
        //             showClass: {
        //                 popup: 'animate__animated animate__fadeInRight'
        //             },
        //             hideClass: {
        //                 popup: 'animate__animated animate__fadeOutRight'
        //             }
        //         });
                
        //         // Clear URL parameters
        //         history.replaceState(null, null, window.location.pathname);
        //     }
        // }
        
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