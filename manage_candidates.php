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
include "app/model/voters.php";
include "app/model/votes.php";

//Check if election ID is provided
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

if($election['status'] == "completed"){
    $message = "Election already completed. Candidate management no longer necessary";
    $status = "info";
    header("Location: view_election.php?message=$message&status=$status&id=$election_id");
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

// Get candidates for this election
$candidates = get_candidates_by_election($conn, $election_id);
if ($candidates == 0) {
    $num_candidates = 0;
} else {
    $num_candidates = count($candidates);
}

// Get verified voters for candidate selection
$verified_voters = get_all_verified_voters($conn);
if ($verified_voters == 0) {
    $num_verified_voters = 0;
} else {
    $num_verified_voters = count($verified_voters);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new candidate
    if (isset($_POST['add_candidate'])) {
        $voter_id = $_POST['voter_id'] ?? '';
        $party_affiliation = trim($_POST['party_affiliation'] ?? '');
        $biography = trim($_POST['biography'] ?? '');
        $campaign_statement = trim($_POST['campaign_statement'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        // Validate input
        $errors = [];

        if (empty($voter_id)) {
            $errors[] = "Please select a voter";
        }

        // Check if voter is already a candidate in this election
        if (!empty($voter_id)) {
            $existing_candidate = get_candidate_by_voter_election($conn, $voter_id, $election_id);
            if ($existing_candidate) {
                $errors[] = "This voter is already a candidate in this election";
            }
        }

        // If no errors, add candidate
        if (empty($errors)) {
            $candidate_data = [
                'voter_id' => $voter_id,
                'election_id' => $election_id,
                'party_affiliation' => $party_affiliation,
                'biography' => $biography,
                'campaign_statement' => $campaign_statement,
                'status' => $status
            ];

            if (add_candidate($conn, $candidate_data)) {
                $message = "Candidate added successfully!";
                $status = "success";
                header("Location: manage_candidates.php?id=$election_id&message=$message&status=$status");
                exit();
            } else {
                $errors[] = "Failed to add candidate. Please try again.";
            }
        }
    }

    // Update candidate status
    if (isset($_POST['update_status'])) {
        $candidate_id = $_POST['candidate_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';

        if (!empty($candidate_id) && !empty($new_status)) {
            if (update_candidate_status($conn, $candidate_id, $new_status)) {
                $message = "Candidate status updated successfully!";
                $status = "success";
            } else {
                $message = "Failed to update candidate status";
                $status = "error";
            }
            header("Location: manage_candidates.php?id=$election_id&message=$message&status=$status");
            exit();
        }
    }

    // Delete candidate
    if (isset($_POST['delete_candidate'])) {
        $candidate_id = $_POST['candidate_id'] ?? '';

        if (!empty($candidate_id)) {
            if (delete_candidate($conn, $candidate_id)) {
                $message = "Candidate deleted successfully!";
                $status = "success";
            } else {
                $message = "Failed to delete candidate";
                $status = "error";
            }
            header("Location: manage_candidates.php?id=$election_id&message=$message&status=$status");
            exit();
        }
    }
}

// Format election dates
$start_date = date('F d, Y', strtotime($election['start_datetime']));
$end_date = date('F d, Y', strtotime($election['end_datetime']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates | SecureVote Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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

        .stat-icon.candidates {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .stat-icon.voters {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .stat-icon.votes {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.approved {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
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

        .status-pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .status-approved {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-disqualified {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-withdrawn {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Candidates Table */
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
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            width: 100% !important;
        }

        table.dataTable thead th {
            border: none !important;
            background-color: #f8f9fa;
            color: var(--primary-color);
            font-weight: 600;
            padding: 1rem 0.75rem;
        }

        table.dataTable tbody td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #eee;
        }

        table.dataTable tbody tr:hover {
            background-color: rgba(155, 89, 182, 0.05);
        }

        /* Candidate Avatar */
        .candidate-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
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

        .btn-edit {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .btn-edit:hover {
            background-color: var(--admin-color);
            color: white;
        }

        .btn-delete {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background-color: var(--danger-color);
            color: white;
        }

        /* Election Info Card */
        .election-info-card {
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(155, 89, 182, 0.2);
        }

        .election-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .election-dates {
            opacity: 0.9;
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

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .election-info-card {
                padding: 1.2rem;
            }

            .election-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 576px) {
            .stat-number {
                font-size: 1.8rem;
            }

            .action-btns {
                flex-wrap: wrap;
            }

            .btn-action {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
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
                    title: "Hello",
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
                <h1>Manage Candidates</h1>
            </div>
            <div class="action-buttons">
                <a href="view_election.php?id=<?= $election['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i> Back to Election
                </a>
            </div>
        </header>

        <!-- Election Info Card -->
        <div class="election-info-card">
            <h2 class="election-title">
                <?= htmlspecialchars($election['title']) ?>
            </h2>
            <div class="election-dates">
                <i class="fas fa-calendar me-1"></i>
                <?= $start_date ?> -
                <?= $end_date ?>
                <span class="mx-2">•</span>
                <i class="fas fa-users me-1"></i>
                <?= $num_candidates ?> Candidates
            </div>
        </div>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Stats Cards -->
            <div class="row stats-cards">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon candidates">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-number">
                            <?= $num_candidates ?>
                        </div>
                        <div class="stat-label">Total Candidates</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon voters">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number">
                            <?= $num_verified_voters ?>
                        </div>
                        <div class="stat-label">Verified Voters</div>
                        <div class="small text-muted">Eligible to be candidates</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <?php
                    // Count approved candidates
                    $approved_count = 0;
                    if ($candidates != 0) {
                        foreach ($candidates as $c) {
                            if ($c['status'] == 'approved') {
                                $approved_count++;
                            }
                        }
                    }
                    ?>
                    <div class="stat-card">
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number">
                            <?= $approved_count ?>
                        </div>
                        <div class="stat-label">Approved Candidates</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <?php
                    // Calculate average votes per candidate
                    $total_votes = 0;
                    if ($candidates != 0) {
                        foreach ($candidates as $c) {
                            $total_votes += $c['total_votes'] ?: 0;
                        }
                        $avg_votes = $num_candidates > 0 ? round($total_votes / $num_candidates, 1) : 0;
                    } else {
                        $avg_votes = 0;
                    }
                    ?>
                    <div class="stat-card">
                        <div class="stat-icon votes">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="stat-number">
                            <?= $avg_votes ?>
                        </div>
                        <div class="stat-label">Avg Votes per Candidate</div>
                    </div>
                </div>
            </div>

            <!-- Add Candidate Form -->
            <div class="form-container">
                <div class="form-header">
                    <h3><i class="fas fa-user-plus me-2"></i> Add New Candidate</h3>
                    <p class="subtitle">Select a verified voter to add as a candidate to this election</p>
                </div>

                <?php if (isset($errors) && !empty($errors)): ?>
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
                <?php endif; ?>

                <form method="POST" action="" id="addCandidateForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="voter_id" class="form-label required">Select Voter</label>
                            <select class="form-select" id="voter_id" name="voter_id" required>
                                <option value="">-- Choose a voter --</option>
                                <?php if ($verified_voters != 0): ?>
                                    <?php foreach ($verified_voters as $voter):
                                        // Check if voter is already a candidate
                                        $is_candidate = false;
                                        if ($candidates != 0) {
                                            foreach ($candidates as $c) {
                                                if ($c['voter_id'] == $voter['id']) {
                                                    $is_candidate = true;
                                                    break;
                                                }
                                            }
                                        }

                                        if (!$is_candidate):
                                            ?>
                                            <option value="<?= $voter['id'] ?>">
                                                <?= htmlspecialchars($voter['full_name']) ?>
                                                (
                                                <?= htmlspecialchars($voter['email']) ?>)
                                            </option>
                                        <?php endif; endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No verified voters available</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">Only verified voters can be added as candidates</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="party_affiliation" class="form-label">Party Affiliation</label>
                            <input type="text" class="form-control" id="party_affiliation" name="party_affiliation"
                                placeholder="e.g., Democratic Party, Independent, etc.">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label required">Initial Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" selected>Pending</option>
                                <option value="approved">Approved</option>
                                <option value="disqualified">Disqualified</option>
                            </select>
                            <div class="form-text">Candidates must be approved to appear on ballot</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="campaign_statement" class="form-label">Campaign Statement</label>
                            <input type="text" class="form-control" id="campaign_statement" name="campaign_statement"
                                placeholder="Short campaign slogan or statement">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="biography" class="form-label">Biography (Optional)</label>
                        <textarea class="form-control" id="biography" name="biography" rows="3"
                            placeholder="Candidate background, experience, and qualifications"></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Once added, candidates can be approved, disqualified, or withdrawn as
                        needed.
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" name="add_candidate" class="btn btn-purple">
                            <i class="fas fa-user-plus me-2"></i> Add Candidate
                        </button>
                    </div>
                </form>
            </div>

            <!-- Candidates Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Current Candidates (
                        <?= $num_candidates ?>)
                    </h3>
                    <?php if ($num_candidates > 0): ?>
                        <div class="table-actions">
                            <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="disqualified">Disqualified</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <?php if ($candidates != 0) { ?>
                        <table id="candidatesTable" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th width="50"></th>
                                    <th>Candidate</th>
                                    <th>Party</th>
                                    <th>Votes</th>
                                    <th>Status</th>
                                    <th>Added Date</th>
                                    <th width="100">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $candidate):
                                    // Get voter details
                                    $voter = get_voter_by_id($conn, $candidate['voter_id']);
                                    if (!$voter)
                                        continue;

                                    // Get initials for avatar
                                    $initials = '';
                                    $name_parts = explode(' ', $voter['full_name']);
                                    if (count($name_parts) >= 2) {
                                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
                                    } else {
                                        $initials = strtoupper(substr($voter['full_name'], 0, 2));
                                    }

                                    // Format date
                                    $added_date = date('M d, Y', strtotime($candidate['created_at']));

                                    // Status badge
                                    $status_badge = '';
                                    switch ($candidate['status']) {
                                        case 'pending':
                                            $status_badge = 'status-pending';
                                            break;
                                        case 'approved':
                                            $status_badge = 'status-approved';
                                            break;
                                        case 'disqualified':
                                            $status_badge = 'status-disqualified';
                                            break;
                                        case 'withdrawn':
                                            $status_badge = 'status-withdrawn';
                                            break;
                                        default:
                                            $status_badge = 'status-pending';
                                    }
                                    ?>
                                    <tr data-status="<?= $candidate['status'] ?>">
                                        <td>
                                            <div class="candidate-avatar">
                                                <?= $initials ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($voter['full_name']) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($voter['email']) ?>
                                            </small>
                                            <?php if (!empty($candidate['campaign_statement'])): ?>
                                                <div class="small mt-1">"
                                                    <?= htmlspecialchars(substr($candidate['campaign_statement'], 0, 50)) ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($candidate['party_affiliation'])): ?>
                                                <span class="badge bg-light text-dark">
                                                    <?= htmlspecialchars($candidate['party_affiliation']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Independent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold">
                                                <?= $candidate['total_votes'] ?: 0 ?>
                                            </div>
                                            <small class="text-muted">votes</small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $status_badge ?>">
                                                <?= ucfirst($candidate['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $added_date ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <!-- Status Update Dropdown -->
                                                <div class="dropdown">
                                                    <button class="btn-action btn-edit dropdown-toggle" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="candidate_id"
                                                                    value="<?= $candidate['id'] ?>">
                                                                <input type="hidden" name="new_status" value="pending">
                                                                <button type="submit" name="update_status"
                                                                    class="dropdown-item">
                                                                    <i class="fas fa-clock text-warning me-2"></i> Set to
                                                                    Pending
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="candidate_id"
                                                                    value="<?= $candidate['id'] ?>">
                                                                <input type="hidden" name="new_status" value="approved">
                                                                <button type="submit" name="update_status"
                                                                    class="dropdown-item">
                                                                    <i class="fas fa-check text-success me-2"></i> Approve
                                                                    Candidate
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="candidate_id"
                                                                    value="<?= $candidate['id'] ?>">
                                                                <input type="hidden" name="new_status" value="disqualified">
                                                                <button type="submit" name="update_status"
                                                                    class="dropdown-item">
                                                                    <i class="fas fa-times text-danger me-2"></i> Disqualify
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <hr class="dropdown-divider">
                                                        </li>
                                                        <li>
                                                            <button type="button" class="dropdown-item text-danger"
                                                                onclick="confirmDelete(<?= $candidate['id'] ?>, '<?= htmlspecialchars($voter['full_name']) ?>')">
                                                                <i class="fas fa-trash me-2"></i> Delete Candidate
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>

                                                <!-- Delete Form (hidden, triggered by JS) -->
                                                <form method="POST" action="" id="deleteForm<?= $candidate['id'] ?>"
                                                    class="d-none">
                                                    <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                                                    <input type="hidden" name="delete_candidate" value="1">
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                            <h4>No Candidates Yet</h4>
                            <p class="text-muted">No candidates have been added to this election yet.</p>
                            <p class="text-muted">Use the form above to add the first candidate.</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
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

            // Initialize DataTable if table exists
            const table = document.getElementById('candidatesTable');
            if (table) {
                $('#candidatesTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                    order: [[3, 'desc']], // Sort by votes descending
                    language: {
                        search: "",
                        searchPlaceholder: "Search candidates..."
                    }
                });

                // Filter candidates by status
                const filterStatus = document.getElementById('filterStatus');
                if (filterStatus) {
                    filterStatus.addEventListener('change', function () {
                        const status = this.value;
                        const dataTable = $('#candidatesTable').DataTable();

                        if (status === '') {
                            dataTable.search('').draw();
                        } else {
                            dataTable.column(4).search(status).draw();
                        }
                    });
                }
            }

            // Form validation
            const form = document.getElementById('addCandidateForm');
            if (form) {
                form.addEventListener('submit', function (e) {
                    const voterSelect = document.getElementById('voter_id');
                    if (!voterSelect.value) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Validation Error',
                            text: 'Please select a voter to add as a candidate.',
                            icon: 'error',
                            confirmButtonColor: '#9b59b6'
                        });
                        voterSelect.focus();
                    }
                });
            }

            // Handle status update confirmations
            const statusForms = document.querySelectorAll('form[name="update_status"]');
            statusForms.forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const candidateId = this.querySelector('input[name="candidate_id"]').value;
                    const newStatus = this.querySelector('input[name="new_status"]').value;

                    let actionText = '';
                    switch (newStatus) {
                        case 'approved':
                            actionText = 'approve this candidate';
                            break;
                        case 'disqualified':
                            actionText = 'disqualify this candidate';
                            break;
                        case 'withdrawn':
                            actionText = 'mark this candidate as withdrawn';
                            break;
                        default:
                            actionText = 'set this candidate to pending';
                    }

                    Swal.fire({
                        title: 'Confirm Status Change',
                        text: `Are you sure you want to ${actionText}?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#9b59b6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, update status',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });

            // Handle URL parameters for notifications
           
        });

        // Function to confirm candidate deletion
        function confirmDelete(candidateId, candidateName) {
            Swal.fire({
                title: 'Delete Candidate',
                html: `Are you sure you want to delete <strong>${candidateName}</strong> as a candidate?<br><br>
                       <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>
                       This will also delete all votes cast for this candidate!</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm' + candidateId).submit();
                }
            });
        }

        // // Function to handle URL parameters for notifications
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
        //         history.replaceState(null, null, window.location.pathname + '?election_id=<?= $election_id ?>');
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