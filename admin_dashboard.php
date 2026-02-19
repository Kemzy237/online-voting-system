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
include "app/model/voters.php";
include "app/model/votes.php";
include "app/model/elections.php";
include "app/model/candidates.php";

// Get all voter stats
$voters = get_all_voters($conn);
$num_voters = ($voters == 0) ? 0 : count($voters);

$verified_voters = get_all_verified_voters($conn);
$num_verified_voters = ($verified_voters == 0) ? 0 : count($verified_voters);
$verified_percentage = ($num_voters > 0) ? ($num_verified_voters / $num_voters) * 100 : 0;

$num_active_elections = get_active_elections($conn);
$votes = get_number_of_votes($conn);

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

// Get election status counts
$status_counts = [
    'active' => 0,
    'upcoming' => 0,
    'completed' => 0,
    'draft' => 0,
    'cancelled' => 0
];
$elections = get_all_elections($conn);

if ($elections != 0) {
    foreach ($elections as $election) {
        $status = strtolower($election['status']);
        if (isset($status_counts[$status])) {
            $status_counts[$status]++;
        }
    }
}

// Get voting activity for last 7 days
function get_voting_activity_last_7_days($conn)
{
    $activity = [];
    $labels = [];
    $data = [];

    // Generate last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('D', strtotime($date));
        $labels[] = $day_name;

        // Query votes for this date
        $sql = "SELECT COUNT(*) as count FROM votes 
                WHERE DATE(vote_timestamp) = ? 
                AND status IN ('verified', 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $data[] = $result ? $result['count'] : 0;
    }

    return ['labels' => $labels, 'data' => $data];
}

$voting_activity = get_voting_activity_last_7_days($conn);

// Get voter age demographics
function get_voter_demographics($conn)
{
    $demographics = [
        'labels' => ['18-25', '26-35', '36-45', '46-55', '56-65', '65+'],
        'data' => [0, 0, 0, 0, 0, 0]
    ];

    $sql = "SELECT dob, status FROM voters WHERE status IN ('verified', 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($voters as $voter) {
        $dob = new DateTime($voter['dob']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age >= 18 && $age <= 25) {
            $demographics['data'][0]++;
        } elseif ($age >= 26 && $age <= 35) {
            $demographics['data'][1]++;
        } elseif ($age >= 36 && $age <= 45) {
            $demographics['data'][2]++;
        } elseif ($age >= 46 && $age <= 55) {
            $demographics['data'][3]++;
        } elseif ($age >= 56 && $age <= 65) {
            $demographics['data'][4]++;
        } elseif ($age > 65) {
            $demographics['data'][5]++;
        }
    }

    return $demographics;
}

$voter_demographics = get_voter_demographics($conn);

// Get recent votes (for activity feed) - FIXED
function get_recent_votes($conn, $limit = 5)
{
    // Use string concatenation for limit since PDO doesn't support binding limit directly
    $sql = "SELECT v.*, vr.full_name as voter_name, c.id as candidate_id, 
                   vr.id as voter_id, e.title as election_title
            FROM votes v
            JOIN voters vr ON v.voter_id = vr.id
            JOIN elections e ON v.election_id = e.id
            LEFT JOIN candidates c ON v.candidate_id = c.id
            ORDER BY v.vote_timestamp DESC 
            LIMIT " . (int) $limit;
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recent_votes = get_recent_votes($conn, 5);

// Get recent voter registrations - FIXED
function get_recent_registrations($conn, $limit = 5)
{
    $sql = "SELECT full_name, email, created_at, status 
            FROM voters 
            ORDER BY created_at DESC 
            LIMIT " . (int) $limit;
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recent_registrations = get_recent_registrations($conn, 5);

// Get recent elections - FIXED
function get_recent_elections($conn, $limit = 5)
{
    $sql = "SELECT title, start_datetime, end_datetime, status 
            FROM elections 
            ORDER BY created_at DESC 
            LIMIT " . (int) $limit;
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recent_elections = get_recent_elections($conn, 3);

// Get votes per election
function get_votes_per_election($conn)
{
    $sql = "SELECT e.title, COUNT(v.id) as vote_count
            FROM elections e
            LEFT JOIN votes v ON e.id = v.election_id AND v.status IN ('verified', 'pending')
            GROUP BY e.id, e.title
            ORDER BY vote_count DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$votes_per_election = get_votes_per_election($conn);

// Get admin name from session
$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SecureVote</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .sidebar-menu a.active_1 {
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

        .admin-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.8rem;
        }

        .admin-info {
            margin-right: 1rem;
        }

        .admin-name {
            font-weight: 600;
            color: var(--primary-color);
        }

        .admin-role {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .notification-badge {
            position: relative;
            margin-right: 1.5rem;
        }

        .notification-badge i {
            font-size: 1.3rem;
            color: var(--primary-color);
        }

        .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(155, 89, 182, 0.2);
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

        .stat-icon.active-polls {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
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

        /* Chart Containers */
        .chart-container {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            height: 100%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
            padding: 0;
        }

        .activity-item {
            display: flex;
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
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-icon.voter {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .activity-icon.poll {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .activity-icon.system {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .activity-icon.alert {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }

        .activity-time {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Recent Votes Table */
        .recent-table {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-badge.pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .status-badge.invalid {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-badge.rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
            justify-content: space-between;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            background-color: white;
            border-radius: 12px;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            color: var(--admin-color);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .action-label {
            font-weight: 600;
            font-size: 0.9rem;
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

        @media (max-width: 992px) {
            #mobile-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .dashboard-content {
                padding: 1.5rem;
            }

            .quick-actions {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .header-left h1 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 576px) {
            .dashboard-content {
                padding: 1rem;
            }

            .welcome-card {
                padding: 1.5rem;
            }

            .welcome-card h2 {
                font-size: 1.5rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
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
                <h1>Admin Dashboard</h1>
            </div>
            <div class="header-right">
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <?= strtoupper(substr($admin_name, 0, 2)) ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?= htmlspecialchars($admin_name) ?></div>
                        <div class="admin-role">System Administrator</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>Welcome back, <?= htmlspecialchars($admin_name) ?>!</h2>
                <p>Here's what's happening with your voting system today.</p>
            </div>

            <!-- Stats Cards -->
            <div class="row stats-cards">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon voters">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-number"><?= $num_voters ?></div>
                        <div class="stat-label">Total Registered Voters</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?= $num_voters - $num_verified_voters ?> pending
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon votes">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-number"><?= $votes ?></div>
                        <div class="stat-label">Total Votes Cast</div>
                        <div class="stat-change positive">
                            <i class="fas fa-chart-line"></i> Real-time count
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon active-polls">
                            <i class="fas fa-poll-h"></i>
                        </div>
                        <div class="stat-number"><?= $num_active_elections ?></div>
                        <div class="stat-label">Active Polls</div>
                        <div class="stat-change positive">
                            <i class="fas fa-running"></i> Live elections
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon verified">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-number"><?= sprintf("%.1f", $verified_percentage) ?>%</div>
                        <div class="stat-label">Verified Users</div>
                        <div class="stat-change <?= $verified_percentage > 70 ? 'positive' : 'negative' ?>">
                            <i class="fas <?= $verified_percentage > 70 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                            <?= $num_verified_voters ?> verified
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Voting Activity (Last 7 Days)</h3>
                            <span class="text-muted">Real-time data</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="votingActivityChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Election Status</h3>
                            <span class="text-muted">Distribution</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="electionStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="recent-table">
                        <h3 class="mb-3">Recent Votes</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Voter</th>
                                        <th>Election</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_votes)): ?>
                                        <?php foreach ($recent_votes as $vote): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($vote['voter_name']) ?></td>
                                                <td><?= htmlspecialchars($vote['election_title']) ?></td>
                                                <td><?= date('M d, Y H:i', strtotime($vote['vote_timestamp'])) ?></td>
                                                <td>
                                                    <span class="status-badge <?= $vote['status'] ?>">
                                                        <?= ucfirst($vote['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No votes recorded yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Recent Registrations</h3>
                            <span class="text-muted">Last 5 voters</span>
                        </div>
                        <ul class="activity-list">
                            <?php if (!empty($recent_registrations)): ?>
                                <?php foreach ($recent_registrations as $voter): ?>
                                    <li class="activity-item">
                                        <div class="activity-icon voter">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?= htmlspecialchars($voter['full_name']) ?></div>
                                            <div class="activity-time"><?= date('M d, Y', strtotime($voter['created_at'])) ?>
                                            </div>
                                            <small class="text-muted">Status: <?= $voter['status'] ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="activity-item">
                                    <div class="activity-content">
                                        <div class="activity-title">No recent registrations</div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="election_polls.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-poll-h"></i>
                    </div>
                    <div class="action-label">Manage Elections</div>
                </a>

                <a href="voter_management.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-label">Manage Voters</div>
                </a>

                <a href="system_settings.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="action-label">System Settings</div>
                </a>

                <a href="reports.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="action-label">Reports</div>
                </a>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleSidebarBtn = document.getElementById('toggle-sidebar');
        const mobileToggleBtn = document.getElementById('mobile-toggle');

        if (toggleSidebarBtn) {
            const toggleIcon = toggleSidebarBtn.querySelector('i');

            toggleSidebarBtn.addEventListener('click', function () {
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
            mobileToggleBtn.addEventListener('click', function () {
                sidebar.classList.toggle('mobile-show');
            });
        }

        // Initialize Voting Activity Chart
        function initializeVotingActivityChart() {
            const ctx = document.getElementById('votingActivityChart').getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(155, 89, 182, 0.3)');
            gradient.addColorStop(1, 'rgba(155, 89, 182, 0.05)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($voting_activity['labels']) ?>,
                    datasets: [{
                        label: 'Votes Cast',
                        data: <?= json_encode($voting_activity['data']) ?>,
                        backgroundColor: gradient,
                        borderColor: '#9b59b6',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#9b59b6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            titleFont: {
                                family: "'Poppins', sans-serif"
                            },
                            bodyFont: {
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    family: "'Poppins', sans-serif"
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Poppins', sans-serif"
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize Election Status Chart
        function initializeElectionStatusChart() {
            const ctx = document.getElementById('electionStatusChart').getContext('2d');
            const statusData = <?= json_encode([
                'labels' => ['Active', 'Upcoming', 'Completed', 'Draft', 'Cancelled'],
                'data' => [
                    $status_counts['active'],
                    $status_counts['upcoming'],
                    $status_counts['completed'],
                    $status_counts['draft'],
                    $status_counts['cancelled']
                ],
                'colors' => ['#9b59b6', '#3498db', '#2ecc71', '#95a5a6', '#e74c3c']
            ]) ?>;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: statusData.labels,
                    datasets: [{
                        data: statusData.data,
                        backgroundColor: statusData.colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 12
                                },
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            titleFont: {
                                family: "'Poppins', sans-serif"
                            },
                            bodyFont: {
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function () {
            // Check for SweetAlert notifications
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

            initializeVotingActivityChart();
            initializeElectionStatusChart();
        });
    </script>
</body>

</html>