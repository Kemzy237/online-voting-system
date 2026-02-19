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
include "app/model/candidates.php";
include "app/model/votes.php";
include "app/model/voters.php";

// Check if election ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $message = "No election specified";
    $status = "error";
    header("Location: election_polls.php?message=$message&status=$status");
    exit();
}

$id = $_GET['id'];

// Get election data
$election = get_election_by_id($conn, $id);
if (!$election) {
    $message = "Election not found";
    $status = "error";
    header("Location: election_polls.php?message=$message&status=$status");
    exit();
}

// Function to update vote status
function update_vote_status($conn, $vote_id, $status)
{
    if ($status == 'verified') {
        $query = "UPDATE votes SET status = :status, verified_at = NOW() WHERE id = :vote_id";
    } else {
        $query = "UPDATE votes SET status = :status WHERE id = :vote_id";
    }

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
    return $stmt->execute();
}

// Function to delete vote
function delete_vote($conn, $vote_id)
{
    $query = "DELETE FROM votes WHERE id = :vote_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':vote_id', $vote_id, PDO::PARAM_INT);
    return $stmt->execute();
}

// Handle individual vote actions
if (isset($_GET['action']) && isset($_GET['vote_id'])) {
    $vote_id = $_GET['vote_id'];
    $action = $_GET['action'];

    switch ($action) {
        case 'verify':
            if (update_vote_status($conn, $vote_id, 'verified')) {
                $message = "Vote verified successfully";
                $status = "success";
            } else {
                $message = "Failed to verify vote";
                $status = "error";
            }
            break;

        case 'reject':
            if (update_vote_status($conn, $vote_id, 'rejected')) {
                $message = "Vote rejected successfully";
                $status = "success";
            } else {
                $message = "Failed to reject vote";
                $status = "error";
            }
            break;

        case 'delete':
            if (delete_vote($conn, $vote_id)) {
                $message = "Vote deleted successfully";
                $status = "success";
            } else {
                $message = "Failed to delete vote";
                $status = "error";
            }
            break;

        default:
            $message = "Invalid action";
            $status = "error";
    }

    header("Location: view_all_votes.php?id=$id&message=$message&status=$status");
    exit();
}

// Get all votes for this election
$votes = get_election_votes($conn, $id);
$verified_votes = 0;
$pending_votes = 0;
$invalid_votes = 0;
$rejected_votes = 0;
if ($votes != 0) {
    $total_votes = count($votes);
    foreach ($votes as $vote) {
        switch ($vote['status']) {
            case 'verified':
                $verified_votes++;
                break;
            case 'pending':
                $pending_votes++;
                break;
            case 'invalid':
                $invalid_votes++;
                break;
            case 'rejected':
                $rejected_votes++;
                break;
        }
    }
} else
    $total_votes = 0;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_votes']) && !empty($_POST['selected_votes'])) {
        $selected_votes = $_POST['selected_votes'];
        $bulk_action = $_POST['bulk_action'];
        $success_count = 0;
        $error_count = 0;

        foreach ($selected_votes as $vote_id) {
            $vote_id = intval($vote_id);

            switch ($bulk_action) {
                case 'verify':
                    if (update_vote_status($conn, $vote_id, 'verified')) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $action_message = "verified";
                    break;

                case 'reject':
                    if (update_vote_status($conn, $vote_id, 'rejected')) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $action_message = "rejected";
                    break;

                case 'delete':
                    if (delete_vote($conn, $vote_id)) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $action_message = "deleted";
                    break;

                default:
                    $action_message = "No action performed";
                    break;
            }
        }

        if ($success_count > 0) {
            $message = "Successfully $action_message $success_count vote(s)";
            if ($error_count > 0) {
                $message .= ", $error_count failed";
            }
            $status = "success";
        } else {
            $message = "Failed to perform bulk action";
            $status = "error";
        }

        header("Location: view_all_votes.php?id=$id&message=$message&status=$status");
        exit();
    }
}

// Format dates
$election_start = date('d F, Y', strtotime($election['start_datetime']));
$election_end = date('d F, Y', strtotime($election['end_datetime']));

// Store votes data for JavaScript
$votes_data_json = [];
if ($votes != 0) {
    foreach ($votes as $vote) {
        $voter = get_voter_by_id($conn, $vote['voter_id']);
        $candidate = get_voter_by_id($conn, $vote['candidate_id']);

        // Format timestamp
        $vote_date = date('d M, Y', strtotime($vote['vote_timestamp']));
        $vote_time = date('h:i A', strtotime($vote['vote_timestamp']));

        // Status badge
        $status_badge = '';
        $status_text = '';
        switch ($vote['status']) {
            case 'pending':
                $status_badge = 'status-pending';
                $status_text = 'Pending';
                break;
            case 'verified':
                $status_badge = 'status-verified';
                $status_text = 'Verified';
                break;
            case 'invalid':
                $status_badge = 'status-invalid';
                $status_text = 'Invalid';
                break;
            case 'rejected':
                $status_badge = 'status-rejected';
                $status_text = 'Rejected';
                break;
            default:
                $status_badge = 'status-pending';
                $status_text = 'Pending';
        }

        $votes_data_json[] = [
            'id' => $vote['id'],
            'voter_id' => $vote['voter_id'],
            'voter_name' => htmlspecialchars($voter['full_name'] ?? 'Unknown'),
            'voter_email' => htmlspecialchars($voter['email'] ?? 'N/A'),
            'candidate_id' => $vote['candidate_id'],
            'candidate_name' => htmlspecialchars($candidate['full_name'] ?? 'Unknown'),
            'timestamp' => $vote['vote_timestamp'],
            'formatted_date' => $vote_date,
            'formatted_time' => $vote_time,
            'status' => $vote['status'],
            'status_text' => $status_text,
            'status_badge' => $status_badge,
            'verified_at' => $vote['verified_at'] ?? null
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Votes | SecureVote Admin</title>
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
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .stat-card.active {
            border: 2px solid var(--admin-color);
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

        .stat-icon.total {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .stat-icon.verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.invalid {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
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

        /* Filter and Search Section */
        .filter-section {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .filter-toggle {
            background: none;
            border: none;
            color: var(--admin-color);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .filter-toggle i {
            transition: transform 0.3s ease;
        }

        .filter-toggle.collapsed i {
            transform: rotate(180deg);
        }

        .filter-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .filter-group {
            margin-bottom: 1rem;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary-color);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding-left: 2.5rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        /* Status Filter Buttons */
        .status-filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .status-filter-btn {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            border: 1px solid #dee2e6;
            background-color: white;
            color: #6c757d;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .status-filter-btn:hover {
            border-color: var(--admin-color);
            color: var(--admin-color);
        }

        .status-filter-btn.active {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
            color: white;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            grid-column: 1 / -1;
        }

        .filter-actions .btn {
            min-width: 120px;
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

        /* Custom Table Styles */
        table.table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        table.table thead th {
            border: none;
            background-color: #f8f9fa;
            color: var(--primary-color);
            font-weight: 600;
            padding: 1rem 0.75rem;
            border-bottom: 2px solid #dee2e6;
        }

        table.table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #eee;
        }

        table.table tbody tr:hover {
            background-color: rgba(155, 89, 182, 0.05);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .status-verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-invalid {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-rejected {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Vote Timestamp */
        .vote-timestamp {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .vote-date {
            font-weight: 600;
            color: var(--primary-color);
        }

        .vote-time {
            color: #6c757d;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-verify {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .btn-verify:hover {
            background-color: var(--success-color);
            color: white;
        }

        .btn-reject {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .btn-reject:hover {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-delete {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .btn-delete:hover {
            background-color: #6c757d;
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

        /* Bulk Actions */
        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
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

        /* Checkbox styling */
        .form-check-input:checked {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 3rem;
        }

        .no-results-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
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

            .filter-body {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .btn {
                width: 100%;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .bulk-actions {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .action-btns {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 576px) {
            .stat-number {
                font-size: 1.8rem;
            }

            .status-filter-buttons {
                justify-content: center;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                Swal.fire({
                    title: status.charAt(0).toUpperCase() + status.slice(1),
                    text: message,
                    icon: status,
                    confirmButtonText: 'OK',
                    showCloseButton: true
                });

                // Remove query parameters from URL
                const newUrl = window.location.pathname + '?id=<?= $id ?>';
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
                <button id="mobile-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>View Votes</h1>
            </div>
            <div class="action-buttons">
                <a href="view_election.php?id=<?= $election['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i> Back to Election
                </a>
            </div>
        </header>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <?= htmlspecialchars($election['title']) ?> - All Votes
            </h1>
            <p class="page-subtitle">
                Election Period: <?= $election_start ?> - <?= $election_end ?> |
                Total Votes: <span id="totalVotesCount"><?= $total_votes ?></span>
            </p>
        </div>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Stats Cards -->
            <div class="row stats-cards">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="all">
                        <div class="stat-icon total">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-number" id="totalVotes"><?= $total_votes ?></div>
                        <div class="stat-label">Total Votes</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="verified">
                        <div class="stat-icon verified">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number" id="verifiedVotes"><?= $verified_votes ?></div>
                        <div class="stat-label">Verified Votes</div>
                        <div class="small text-muted" id="verifiedPercentage">
                            <?= $total_votes > 0 ? round(($verified_votes / $total_votes) * 100, 1) : 0 ?>% of total
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="pending">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number" id="pendingVotes"><?= $pending_votes ?></div>
                        <div class="stat-label">Pending Verification</div>
                        <div class="small text-muted">Require admin review</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="invalid">
                        <div class="stat-icon invalid">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-number" id="invalidVotes"><?= $invalid_votes + $rejected_votes ?></div>
                        <div class="stat-label">Invalid/Rejected</div>
                        <div class="small text-muted">Excluded from results</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h3><i class="fas fa-filter me-2"></i> Filter & Search Votes</h3>
                    <button class="filter-toggle" id="filterToggle" data-bs-toggle="collapse"
                        data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <div class="collapse show" id="filterCollapse">
                    <div class="filter-body">
                        <!-- Search Box -->
                        <div class="filter-group">
                            <label for="searchVotes"><i class="fas fa-search me-2"></i>Search by Voter or
                                Candidate</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchVotes"
                                    placeholder="Search by voter name, email or candidate...">
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label><i class="fas fa-tag me-2"></i>Filter by Status</label>
                            <div class="status-filter-buttons">
                                <button class="status-filter-btn active" data-status="all">All Votes</button>
                                <button class="status-filter-btn" data-status="pending">Pending</button>
                                <button class="status-filter-btn" data-status="verified">Verified</button>
                                <button class="status-filter-btn" data-status="rejected">Rejected</button>
                            </div>
                        </div>

                        <!-- Date Range Filter -->
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt me-2"></i>Date Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="date" class="form-control form-control-sm" id="startDateFilter"
                                        placeholder="Start Date">
                                </div>
                                <div class="col-6">
                                    <input type="date" class="form-control form-control-sm" id="endDateFilter"
                                        placeholder="End Date">
                                </div>
                            </div>
                        </div>

                        <!-- Sort Options -->
                        <div class="filter-group">
                            <label><i class="fas fa-sort me-2"></i>Sort By</label>
                            <select class="form-select" id="sortFilter">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="voter_asc">Voter Name (A-Z)</option>
                                <option value="voter_desc">Voter Name (Z-A)</option>
                                <option value="candidate_asc">Candidate (A-Z)</option>
                                <option value="candidate_desc">Candidate (Z-A)</option>
                            </select>
                        </div>

                        <!-- Filter Actions -->
                        <div class="filter-actions">
                            <button class="btn btn-purple" id="applyFilters">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <button class="btn btn-outline-purple" id="clearFilters">
                                <i class="fas fa-times me-2"></i> Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Form -->
            <form method="POST" action="" id="bulkActionForm">
                <input type="hidden" name="id" value="<?= $id ?>">

                <!-- Votes Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>All Votes <span id="filteredCount" class="badge bg-purple ms-2"><?= $total_votes ?></span>
                        </h3>
                        <div class="bulk-actions">
                            <select class="form-select form-select-sm" id="bulkActionSelect" name="bulk_action"
                                style="width: auto;">
                                <option value="">Bulk Actions</option>
                                <option value="verify">Verify Selected</option>
                                <option value="reject">Reject Selected</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button type="submit" class="btn btn-purple btn-sm" id="applyBulkAction">
                                <i class="fas fa-play me-1"></i> Apply
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <?php if ($total_votes > 0): ?>
                            <table class="table table-hover" id="votesTable">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Vote ID</th>
                                        <th>Voter</th>
                                        <th>Candidate</th>
                                        <th>Timestamp</th>
                                        <th>Status</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($votes != 0): ?>
                                        <?php foreach ($votes as $vote):
                                            $voter = get_voter_by_id($conn, $vote['voter_id']);
                                            $candidate = get_voter_by_id($conn, $vote['candidate_id']);

                                            // Format timestamp
                                            $vote_date = date('d M, Y', strtotime($vote['vote_timestamp']));
                                            $vote_time = date('h:i A', strtotime($vote['vote_timestamp']));

                                            // Status badge
                                            $status_badge = '';
                                            $status_text = '';
                                            switch ($vote['status']) {
                                                case 'pending':
                                                    $status_badge = 'status-pending';
                                                    $status_text = 'Pending';
                                                    break;
                                                case 'verified':
                                                    $status_badge = 'status-verified';
                                                    $status_text = 'Verified';
                                                    break;
                                                case 'invalid':
                                                    $status_badge = 'status-invalid';
                                                    $status_text = 'Invalid';
                                                    break;
                                                case 'rejected':
                                                    $status_badge = 'status-rejected';
                                                    $status_text = 'Rejected';
                                                    break;
                                                default:
                                                    $status_badge = 'status-pending';
                                                    $status_text = 'Pending';
                                            }
                                            ?>
                                            <tr data-vote-id="<?= $vote['id'] ?>" data-status="<?= $vote['status'] ?>"
                                                data-voter-name="<?= htmlspecialchars($voter['full_name'] ?? '') ?>"
                                                data-voter-email="<?= htmlspecialchars($voter['email'] ?? '') ?>"
                                                data-candidate-name="<?= htmlspecialchars($candidate['full_name'] ?? '') ?>"
                                                data-timestamp="<?= $vote['vote_timestamp'] ?>">
                                                <td>
                                                    <input type="checkbox" name="selected_votes[]" value="<?= $vote['id'] ?>"
                                                        class="form-check-input vote-checkbox">
                                                </td>
                                                <td>
                                                    <strong>#<?= $vote['id'] ?></strong>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars($voter['full_name'] ?? 'Unknown') ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($voter['email'] ?? 'N/A') ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars($candidate['full_name'] ?? 'Unknown') ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="vote-timestamp">
                                                        <div class="vote-date"><?= $vote_date ?></div>
                                                        <div class="vote-time"><?= $vote_time ?></div>
                                                        <?php if ($vote['verified_at']): ?>
                                                            <div class="small text-success">
                                                                <i class="fas fa-check-circle me-1"></i>
                                                                Verified: <?= date('d M, h:i A', strtotime($vote['verified_at'])) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?= $status_badge ?>">
                                                        <?= $status_text ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-btns">
                                                        <?php if ($vote['status'] == 'pending'): ?>
                                                            <a href="view_all_votes.php?id=<?= $id ?>&action=verify&vote_id=<?= $vote['id'] ?>"
                                                                class="btn-action btn-verify" title="Verify Vote"
                                                                onclick="return confirm('Are you sure you want to verify this vote?')">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                            <a href="view_all_votes.php?id=<?= $id ?>&action=reject&vote_id=<?= $vote['id'] ?>"
                                                                class="btn-action btn-reject" title="Reject Vote"
                                                                onclick="return confirm('Are you sure you want to reject this vote?')">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php elseif ($vote['status'] == 'verified'): ?>
                                                            <a href="view_all_votes.php?id=<?= $id ?>&action=reject&vote_id=<?= $vote['id'] ?>"
                                                                class="btn-action btn-reject" title="Mark as Rejected"
                                                                onclick="return confirm('Are you sure you want to mark this vote as rejected?')">
                                                                <i class="fas fa-ban"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="view_all_votes.php?id=<?= $id ?>&action=delete&vote_id=<?= $vote['id'] ?>"
                                                            class="btn-action btn-delete" title="Delete Vote"
                                                            onclick="return confirm('Are you sure you want to delete this vote? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <div id="noResultsMessage" class="no-results" style="display: none;">
                                <div class="no-results-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h4 class="text-muted mb-2">No votes found</h4>
                                <p class="text-muted mb-4">Try adjusting your filters or search terms</p>
                                <button class="btn btn-purple" id="clearFilters2">
                                    <i class="fas fa-times me-2"></i> Clear All Filters
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-vote-yea fa-3x text-muted mb-3"></i>
                                <h4>No Votes Found</h4>
                                <p class="text-muted">
                                    No votes have been cast in this election yet.
                                </p>
                                <a href="view_election.php?id=<?= $election['id'] ?>" class="btn btn-purple">
                                    <i class="fas fa-eye me-2"></i> View Election Details
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        // Store votes data from PHP
        const votesData = <?php echo json_encode($votes_data_json ?? []); ?>;

        // Filter and Search System for Votes
        class VotesFilterSystem {
            constructor() {
                this.currentFilters = {
                    search: '',
                    status: 'all',
                    startDate: '',
                    endDate: '',
                    sort: 'newest'
                };
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.updateStats();
            }

            setupEventListeners() {
                // Search input
                document.getElementById('searchVotes').addEventListener('input', (e) => {
                    this.currentFilters.search = e.target.value.toLowerCase();
                    this.debouncedApplyFilters();
                });

                // Status filter buttons
                document.querySelectorAll('.status-filter-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                        e.target.classList.add('active');
                        this.currentFilters.status = e.target.dataset.status;

                        // Highlight corresponding stat card
                        document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
                        if (this.currentFilters.status !== 'all') {
                            document.querySelector(`.stat-card[data-status="${this.currentFilters.status}"]`)?.classList.add('active');
                        }

                        this.applyFilters();
                    });
                });

                // Date range filters
                document.getElementById('startDateFilter').addEventListener('change', (e) => {
                    this.currentFilters.startDate = e.target.value;
                    this.applyFilters();
                });

                document.getElementById('endDateFilter').addEventListener('change', (e) => {
                    this.currentFilters.endDate = e.target.value;
                    this.applyFilters();
                });

                // Sort filter
                document.getElementById('sortFilter').addEventListener('change', (e) => {
                    this.currentFilters.sort = e.target.value;
                    this.applyFilters();
                });

                // Apply filters button
                document.getElementById('applyFilters').addEventListener('click', () => {
                    this.applyFilters();
                });

                // Clear filters button
                document.getElementById('clearFilters').addEventListener('click', () => {
                    this.clearFilters();
                });

                // Clear filters button in no results message
                document.getElementById('clearFilters2')?.addEventListener('click', () => {
                    this.clearFilters();
                });

                // Stat card clicks
                document.querySelectorAll('.stat-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        const status = card.dataset.status;
                        if (status) {
                            document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                            document.querySelector(`.status-filter-btn[data-status="${status}"]`)?.classList.add('active');

                            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
                            card.classList.add('active');

                            this.currentFilters.status = status;
                            this.applyFilters();
                        }
                    });
                });

                // Filter toggle
                document.getElementById('filterToggle').addEventListener('click', function () {
                    this.classList.toggle('collapsed');
                });
            }

            debouncedApplyFilters() {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.applyFilters(), 300);
            }

            applyFilters() {
                const rows = document.querySelectorAll('#votesTable tbody tr');
                let visibleCount = 0;
                let verifiedCount = 0;
                let pendingCount = 0;
                let invalidRejectedCount = 0;

                rows.forEach(row => {
                    let showRow = true;

                    // Apply search filter
                    if (this.currentFilters.search) {
                        const voterName = row.dataset.voterName.toLowerCase();
                        const voterEmail = row.dataset.voterEmail.toLowerCase();
                        const candidateName = row.dataset.candidateName.toLowerCase();
                        const voteId = row.dataset.voteId.toString();

                        showRow = showRow && (
                            voterName.includes(this.currentFilters.search) ||
                            voterEmail.includes(this.currentFilters.search) ||
                            candidateName.includes(this.currentFilters.search) ||
                            voteId.includes(this.currentFilters.search)
                        );
                    }

                    // Apply status filter
                    if (this.currentFilters.status !== 'all') {
                        showRow = showRow && (row.dataset.status === this.currentFilters.status);
                    }

                    // Apply start date filter
                    if (this.currentFilters.startDate) {
                        const voteDate = new Date(row.dataset.timestamp.split(' ')[0]); // Get date part only
                        const filterStartDate = new Date(this.currentFilters.startDate);
                        showRow = showRow && (voteDate >= filterStartDate);
                    }

                    // Apply end date filter
                    if (this.currentFilters.endDate) {
                        const voteDate = new Date(row.dataset.timestamp.split(' ')[0]); // Get date part only
                        const filterEndDate = new Date(this.currentFilters.endDate);
                        showRow = showRow && (voteDate <= filterEndDate);
                    }

                    // Show/hide row and update counts
                    if (showRow) {
                        row.style.display = '';
                        visibleCount++;

                        // Update status counts
                        const status = row.dataset.status;
                        if (status === 'verified') verifiedCount++;
                        else if (status === 'pending') pendingCount++;
                        else if (status === 'invalid' || status === 'rejected') invalidRejectedCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update filtered count
                document.getElementById('filteredCount').textContent = visibleCount;

                // Update stats
                this.updateFilteredStats(visibleCount, verifiedCount, pendingCount, invalidRejectedCount);

                // Sort rows if needed
                if (this.currentFilters.sort !== 'newest') {
                    this.sortTable(rows);
                }

                // Show no results message if needed
                this.showNoResultsMessage(visibleCount);

                // Reset bulk selection
                this.resetBulkSelection();
            }

            sortTable(rows) {
                const tbody = document.querySelector('#votesTable tbody');
                const rowsArray = Array.from(rows);

                rowsArray.sort((a, b) => {
                    switch (this.currentFilters.sort) {
                        case 'oldest':
                            return new Date(a.dataset.timestamp) - new Date(b.dataset.timestamp);
                        case 'voter_asc':
                            return a.dataset.voterName.localeCompare(b.dataset.voterName);
                        case 'voter_desc':
                            return b.dataset.voterName.localeCompare(a.dataset.voterName);
                        case 'candidate_asc':
                            return a.dataset.candidateName.localeCompare(b.dataset.candidateName);
                        case 'candidate_desc':
                            return b.dataset.candidateName.localeCompare(a.dataset.candidateName);
                        default: // newest
                            return new Date(b.dataset.timestamp) - new Date(a.dataset.timestamp);
                    }
                });

                // Reorder rows in DOM
                rowsArray.forEach(row => tbody.appendChild(row));
            }

            showNoResultsMessage(visibleCount) {
                const tableBody = document.querySelector('#votesTable tbody');
                const noResultsMessage = document.getElementById('noResultsMessage');
                const rows = tableBody.querySelectorAll('tr');

                if (visibleCount === 0 && rows.length > 0) {
                    if (noResultsMessage) {
                        noResultsMessage.style.display = 'block';
                    }
                } else if (noResultsMessage) {
                    noResultsMessage.style.display = 'none';
                }
            }

            updateStats() {
                // Initial stats update
                this.updateFilteredStats(
                    <?= $total_votes ?>,
                    <?= $verified_votes ?>,
                    <?= $pending_votes ?>,
                    <?= $invalid_votes + $rejected_votes ?>
                );
            }

            updateFilteredStats(total, verified, pending, invalidRejected) {
                // Update stat numbers
                document.getElementById('totalVotes').textContent = total;
                document.getElementById('verifiedVotes').textContent = verified;
                document.getElementById('pendingVotes').textContent = pending;
                document.getElementById('invalidVotes').textContent = invalidRejected;

                // Update verified percentage
                const verifiedPercentage = total > 0 ? ((verified / total) * 100).toFixed(1) : 0;
                document.getElementById('verifiedPercentage').textContent = `${verifiedPercentage}% of filtered`;

                // Update header count
                document.getElementById('totalVotesCount').textContent = total;
            }

            clearFilters() {
                // Reset filters
                this.currentFilters = {
                    search: '',
                    status: 'all',
                    startDate: '',
                    endDate: '',
                    sort: 'newest'
                };

                // Reset UI
                document.getElementById('searchVotes').value = '';
                document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                document.querySelector('.status-filter-btn[data-status="all"]').classList.add('active');
                document.getElementById('startDateFilter').value = '';
                document.getElementById('endDateFilter').value = '';
                document.getElementById('sortFilter').value = 'newest';

                document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));

                this.applyFilters();
            }

            resetBulkSelection() {
                const selectAll = document.getElementById('selectAll');
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }

                document.querySelectorAll('.vote-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });

                this.updateBulkActionButton();
            }
        }

        // Main Application
        class VotesManagementSystem {
            constructor() {
                this.votesData = votesData;
                this.filterSystem = null;
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.handleURLParameters();

                // Initialize filter system
                this.filterSystem = new VotesFilterSystem();
            }

            setupEventListeners() {
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

                // Bulk selection
                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function () {
                        const checkboxes = document.querySelectorAll('.vote-checkbox');
                        checkboxes.forEach(checkbox => {
                            if (checkbox.closest('tr').style.display !== 'none') {
                                checkbox.checked = this.checked;
                            }
                        });
                        updateBulkActionButton();
                    });
                }

                // Update select all when individual checkboxes change
                document.querySelectorAll('.vote-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        updateSelectAllCheckbox();
                        updateBulkActionButton();
                    });
                });

                // Bulk action form submission
                const bulkActionForm = document.getElementById('bulkActionForm');
                if (bulkActionForm) {
                    bulkActionForm.addEventListener('submit', function (e) {
                        const selectedAction = document.getElementById('bulkActionSelect').value;
                        const selectedVotes = document.querySelectorAll('.vote-checkbox:checked');

                        if (selectedAction === '') {
                            e.preventDefault();
                            Swal.fire({
                                title: 'No Action Selected',
                                text: 'Please select a bulk action to perform.',
                                icon: 'warning',
                                confirmButtonColor: '#9b59b6'
                            });
                            return;
                        }

                        if (selectedVotes.length === 0) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'No Votes Selected',
                                text: 'Please select at least one vote to perform the action.',
                                icon: 'warning',
                                confirmButtonColor: '#9b59b6'
                            });
                            return;
                        }

                        let actionText = '';
                        let confirmText = '';
                        let confirmButtonText = '';

                        switch (selectedAction) {
                            case 'verify':
                                actionText = 'verify';
                                confirmText = 'verify';
                                confirmButtonText = 'Verify';
                                break;
                            case 'reject':
                                actionText = 'reject';
                                confirmText = 'reject';
                                confirmButtonText = 'Reject';
                                break;
                            case 'delete':
                                actionText = 'delete';
                                confirmText = 'delete';
                                confirmButtonText = 'Delete';
                                break;
                        }

                        e.preventDefault();

                        Swal.fire({
                            title: `Confirm ${confirmButtonText}`,
                            html: `Are you sure you want to <strong>${confirmText}</strong> ${selectedVotes.length} selected vote(s)?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#9b59b6',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: `Yes, ${actionText} them`,
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Show loading state
                                const applyButton = document.getElementById('applyBulkAction');
                                const originalText = applyButton.innerHTML;
                                applyButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
                                applyButton.disabled = true;

                                // Submit form
                                bulkActionForm.submit();
                            }
                        });
                    });
                }
            }

            handleURLParameters() {
                const urlParams = new URLSearchParams(window.location.search);
                const statusParam = urlParams.get('status_filter');

                if (statusParam && ['pending', 'verified', 'rejected', 'invalid'].includes(statusParam)) {
                    document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                    document.querySelector(`.status-filter-btn[data-status="${statusParam}"]`)?.classList.add('active');
                    document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
                    document.querySelector(`.stat-card[data-status="${statusParam}"]`)?.classList.add('active');

                    if (this.filterSystem) {
                        this.filterSystem.currentFilters.status = statusParam;
                        setTimeout(() => this.filterSystem.applyFilters(), 100);
                    }
                }
            }
        }

        // Helper functions
        function updateSelectAllCheckbox() {
            const selectAll = document.getElementById('selectAll');
            if (!selectAll) return;

            const checkboxes = Array.from(document.querySelectorAll('.vote-checkbox')).filter(cb =>
                cb.closest('tr').style.display !== 'none'
            );
            const checked = checkboxes.filter(cb => cb.checked);

            selectAll.checked = checked.length === checkboxes.length && checkboxes.length > 0;
            selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }

        function updateBulkActionButton() {
            const selectedCount = document.querySelectorAll('.vote-checkbox:checked').length;
            const applyButton = document.getElementById('applyBulkAction');

            if (applyButton) {
                applyButton.innerHTML = selectedCount > 0
                    ? `<i class="fas fa-play me-1"></i> Apply (${selectedCount})`
                    : `<i class="fas fa-play me-1"></i> Apply`;
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.votesSystem = new VotesManagementSystem();

            // Handle individual action confirmations
            document.querySelectorAll('.btn-verify, .btn-reject, .btn-delete').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    if (this.classList.contains('btn-verify')) {
                        if (!confirm('Are you sure you want to verify this vote?')) {
                            e.preventDefault();
                        }
                    } else if (this.classList.contains('btn-reject')) {
                        if (!confirm('Are you sure you want to reject this vote?')) {
                            e.preventDefault();
                        }
                    } else if (this.classList.contains('btn-delete')) {
                        if (!confirm('Are you sure you want to delete this vote? This action cannot be undone.')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>