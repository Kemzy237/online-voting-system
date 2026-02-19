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
include "app/model/voters.php";
include "app/model/votes.php";

// Get all elections
$elections = get_all_elections($conn);
if ($elections == 0) {
    $num_elections = 0;
} else {
    $num_elections = count($elections);
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
$active_elections = get_active_elections($conn);
$completed_elections = get_completed_elections($conn);
$upcoming_elections = get_upcoming_elections($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Polls | SecureVote Admin</title>
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

        .stat-icon.active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.completed {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .stat-icon.upcoming {
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

        /* Elections Table */
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

        .btn-view {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }

        .btn-view:hover {
            background-color: var(--secondary-color);
            color: white;
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

        /* Countdown Timer */
        .countdown {
            font-family: monospace;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .countdown.active {
            color: var(--success-color);
        }

        .countdown.upcoming {
            color: var(--warning-color);
        }

        /* Date Badge */
        .date-badge {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* Pagination Custom */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 1px solid #dee2e6 !important;
            border-radius: 6px !important;
            margin: 0 2px !important;
            padding: 0.375rem 0.75rem !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--admin-color) !important;
            border-color: var(--admin-color) !important;
            color: white !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f8f9fa !important;
            border-color: #dee2e6 !important;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background-color: var(--admin-color);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #eee;
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
                <h1>Election Polls</h1>
            </div>
        </header>
        <br>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Stats Cards -->
            <div class="row stats-cards">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="all">
                        <div class="stat-icon total">
                            <i class="fas fa-poll"></i>
                        </div>
                        <div class="stat-number" id="totalElections">
                            <?= $num_elections ?>
                        </div>
                        <div class="stat-label">Total Elections</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="active">
                        <div class="stat-icon active">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="stat-number" id="activeElections">
                            <?= $active_elections ?>
                        </div>
                        <div class="stat-label">Active Elections</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="completed">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number" id="completedElections">
                            <?= $completed_elections ?>
                        </div>
                        <div class="stat-label">Completed Elections</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="upcoming">
                        <div class="stat-icon upcoming">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number" id="upcomingElections">
                            <?= $upcoming_elections ?>
                        </div>
                        <div class="stat-label">Upcoming Elections</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h3><i class="fas fa-filter me-2"></i> Filter & Search Elections</h3>
                    <button class="filter-toggle" id="filterToggle" data-bs-toggle="collapse"
                        data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <div class="collapse show" id="filterCollapse">
                    <div class="filter-body">
                        <!-- Search Box -->
                        <div class="filter-group">
                            <label for="searchElections"><i class="fas fa-search me-2"></i>Search by Title or ID</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchElections"
                                    placeholder="Type election title or ID...">
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label><i class="fas fa-tag me-2"></i>Filter by Status</label>
                            <div class="status-filter-buttons">
                                <button class="status-filter-btn active" data-status="all">All Elections</button>
                                <button class="status-filter-btn" data-status="draft">Draft</button>
                                <button class="status-filter-btn" data-status="upcoming">Upcoming</button>
                                <button class="status-filter-btn" data-status="active">Active</button>
                                <button class="status-filter-btn" data-status="completed">Completed</button>
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
                                <option value="title_asc">Title (A-Z)</option>
                                <option value="title_desc">Title (Z-A)</option>
                                <option value="progress_asc">Progress (Low to High)</option>
                                <option value="progress_desc">Progress (High to Low)</option>
                            </select>
                        </div>

                        <!-- Filter Actions -->
                        <div class="filter-actions">
                            <button class="btn btn-purple" id="applyFilters">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <button class="btn btn-outline-secondary" id="clearFilters">
                                <i class="fas fa-times me-2"></i> Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Elections Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Elections <span id="filteredCount" class="badge bg-purple ms-2"><?= $num_elections ?></span>
                    </h3>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-purple " data-bs-toggle="modal"
                            data-bs-target="#addElectionModal">
                            <i class="fas fa-plus-circle me-2"></i>Create New Election
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if ($elections != 0) {
                        // Store election data for JavaScript
                        $election_data_json = [];
                        ?>
                        <table id="electionsTable" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Dates</th>
                                    <th>Status</th>
                                    <th>Voters / Votes</th>
                                    <th>Progress</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($elections as $election) {
                                    // Format dates
                                    $start_date = date('d M, Y', strtotime($election['start_datetime']));
                                    $end_date = date('d M, Y', strtotime($election['end_datetime']));

                                    // Calculate progress percentage
                                    $total_voters = count(get_all_verified_voters($conn)) ?: 1;
                                    $num_votes_cast = get_election_votes($conn, $election['id']);
                                    if ($num_votes_cast != 0) {
                                        $votes_cast = count($num_votes_cast);
                                    } else {
                                        $votes_cast = 0;
                                    }

                                    $progress = ($votes_cast / $total_voters) * 100;
                                    $progress = min($progress, 100);

                                    // Get status badge
                                    $status_badge = '';
                                    switch ($election['status']) {
                                        case 'draft':
                                            $status_badge = '<span class="status-badge status-draft">Draft</span>';
                                            $progress_class = 'bg-secondary';
                                            break;
                                        case 'upcoming':
                                            $status_badge = '<span class="status-badge status-upcoming">Upcoming</span>';
                                            $progress_class = 'progress-upcoming';
                                            break;
                                        case 'active':
                                            $status_badge = '<span class="status-badge status-active">Active</span>';
                                            $progress_class = 'progress-active';
                                            break;
                                        case 'completed':
                                            $status_badge = '<span class="status-badge status-completed">Completed</span>';
                                            $progress_class = 'progress-completed';
                                            break;
                                        case 'cancelled':
                                            $status_badge = '<span class="status-badge status-cancelled">Cancelled</span>';
                                            $progress_class = 'bg-danger';
                                            break;
                                        default:
                                            $status_badge = '<span class="status-badge status-draft">Draft</span>';
                                            $progress_class = 'bg-secondary';
                                    }

                                    // Store data for JavaScript filtering
                                    $election_data_json[] = [
                                        'id' => $election['id'],
                                        'title' => htmlspecialchars($election['title']),
                                        'description' => htmlspecialchars($election['description'] ?? ''),
                                        'status' => $election['status'],
                                        'start_date' => $election['start_datetime'],
                                        'end_date' => $election['end_datetime'],
                                        'total_voters' => $total_voters,
                                        'votes_cast' => $votes_cast,
                                        'progress' => $progress,
                                        'status_badge' => $status_badge,
                                        'progress_class' => $progress_class,
                                        'formatted_start' => $start_date,
                                        'formatted_end' => $end_date
                                    ];
                                    ?>
                                    <tr data-election-id="<?= $election['id'] ?>" data-status="<?= $election['status'] ?>"
                                        data-title="<?= htmlspecialchars($election['title']) ?>"
                                        data-start-date="<?= $election['start_datetime'] ?>"
                                        data-end-date="<?= $election['end_datetime'] ?>" data-progress="<?= $progress ?>">
                                        <td><strong>#
                                                <?= $election['id'] ?>
                                            </strong></td>
                                        <td>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($election['title']) ?>
                                            </div>
                                            <?php if (!empty($election['description'])): ?>
                                                <small class="text-muted">
                                                    <?= substr(htmlspecialchars($election['description']), 0, 50) ?>...
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div><span class="date-badge">Start:</span>
                                                    <?= $start_date ?>
                                                </div>
                                                <div><span class="date-badge">End:</span>
                                                    <?= $end_date ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $status_badge ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div>Voters: <strong>
                                                        <?= $total_voters ?>
                                                    </strong></div>
                                                <div>Votes: <strong>
                                                        <?= $votes_cast ?>
                                                    </strong></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small mb-1">
                                                <?= number_format($progress, 1) ?>%
                                            </div>
                                            <div class="progress-container">
                                                <div class="progress-bar <?= $progress_class ?>"
                                                    style="width: <?= $progress ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="view_election.php?id=<?= $election['id'] ?>"
                                                    class="btn-action btn-view" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_election.php?id=<?= $election['id'] ?>"
                                                    class="btn-action btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn-action btn-delete"
                                                    onclick="confirmDelete(<?= $election['id'] ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <div class="text-center py-5">
                            <i class="fas fa-poll fa-3x text-muted mb-3"></i>
                            <h4>No Elections Found</h4>
                            <p class="text-muted">No elections have been created yet.</p>
                            <button type="button" class="btn btn-purple" data-bs-toggle="modal"
                                data-bs-target="#addElectionModal">
                                <i class="fas fa-plus-circle me-2"></i>Create New Election
                            </button>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Election Modal -->
    <div class="modal fade" id="addElectionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Election</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addVoterForm" method="POST" action="app/add_election.php">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                                <div class="invalid-feedback">Please enter the election's title.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea rows="2" placeholder="Enter election description" class="form-control"
                                    id="description" name="description" required></textarea>
                                <div class="invalid-feedback">Please enter the description.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start" name="start" required>
                                <div class="invalid-feedback">Please enter a start date.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end" name="end" required>
                                <div class="invalid-feedback">Please enter an end date.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Election Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="draft" selected>Draft</option>
                                    <option value="upcoming">Upcoming</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle me-2"></i>Fields marked with * are required. Election ID
                                is auto-generated.</small>
                        </div>

                        <div class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= bin2hex(random_bytes(32)) ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-purple">
                                <i class="fas fa-user-plus me-2"></i> Create Election
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
        // Store election data from PHP
        const electionData = <?php echo json_encode($election_data_json ?? []); ?>;

        // SweetAlert2 Notification Handler
        const sweetAlertHandler = {
            showNotification: function (status, message) {
                const config = this.getAlertConfig(status);

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
            },

            getAlertConfig: function (status) {
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
                    },
                    'warning': {
                        icon: 'warning',
                        title: 'Warning!',
                        iconColor: '#ffc107',
                        textColor: '#856404',
                        background: '#fff3cd',
                        confirmButtonColor: '#ffc107'
                    },
                    'info': {
                        icon: 'info',
                        title: 'Information',
                        iconColor: '#17a2b8',
                        textColor: '#0c5460',
                        background: '#d1ecf1',
                        confirmButtonColor: '#17a2b8'
                    }
                };

                return configs[status.toLowerCase()] || configs.info;
            },

            confirmAction: function (title, text, confirmButtonText = 'Yes, proceed') {
                return Swal.fire({
                    title: title,
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#9b59b6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: confirmButtonText,
                    cancelButtonText: 'Cancel'
                });
            }
        };

        // Filter and Search System for Elections
        class ElectionFilterSystem {
            constructor() {
                this.currentFilters = {
                    search: '',
                    status: 'all',
                    startDate: '',
                    endDate: '',
                    sort: 'newest'
                };
                this.filteredElections = [];
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.applyFilters();
            }

            setupEventListeners() {
                // Search input
                document.getElementById('searchElections').addEventListener('input', (e) => {
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
                const rows = document.querySelectorAll('#electionsTable tbody tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    let showRow = true;

                    // Apply search filter
                    if (this.currentFilters.search) {
                        const title = row.dataset.title.toLowerCase();
                        const id = row.dataset.electionId.toString();
                        showRow = showRow && (
                            title.includes(this.currentFilters.search) ||
                            id.includes(this.currentFilters.search)
                        );
                    }

                    // Apply status filter
                    if (this.currentFilters.status !== 'all') {
                        showRow = showRow && (row.dataset.status === this.currentFilters.status);
                    }

                    // Apply start date filter
                    if (this.currentFilters.startDate) {
                        const startDate = new Date(row.dataset.startDate.split(' ')[0]); // Get date part only
                        const filterStartDate = new Date(this.currentFilters.startDate);
                        showRow = showRow && (startDate >= filterStartDate);
                    }

                    // Apply end date filter
                    if (this.currentFilters.endDate) {
                        const endDate = new Date(row.dataset.endDate.split(' ')[0]); // Get date part only
                        const filterEndDate = new Date(this.currentFilters.endDate);
                        showRow = showRow && (endDate <= filterEndDate);
                    }

                    // Show/hide row
                    if (showRow) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Update filtered count
                document.getElementById('filteredCount').textContent = visibleCount;

                // Sort rows if needed
                if (this.currentFilters.sort !== 'newest') {
                    this.sortTable(rows);
                }

                // Show no results message if needed
                this.showNoResultsMessage(visibleCount);
            }

            sortTable(rows) {
                const tbody = document.querySelector('#electionsTable tbody');
                const rowsArray = Array.from(rows);

                rowsArray.sort((a, b) => {
                    switch (this.currentFilters.sort) {
                        case 'oldest':
                            return new Date(a.dataset.startDate) - new Date(b.dataset.startDate);
                        case 'title_asc':
                            return a.dataset.title.localeCompare(b.dataset.title);
                        case 'title_desc':
                            return b.dataset.title.localeCompare(a.dataset.title);
                        case 'progress_asc':
                            return parseFloat(a.dataset.progress) - parseFloat(b.dataset.progress);
                        case 'progress_desc':
                            return parseFloat(b.dataset.progress) - parseFloat(a.dataset.progress);
                        default: // newest
                            return new Date(b.dataset.startDate) - new Date(a.dataset.startDate);
                    }
                });

                // Reorder rows in DOM
                rowsArray.forEach(row => tbody.appendChild(row));
            }

            showNoResultsMessage(visibleCount) {
                const tableBody = document.querySelector('#electionsTable tbody');
                const noResultsRow = tableBody.querySelector('.no-results-row');
                const rows = tableBody.querySelectorAll('tr');

                if (visibleCount === 0 && rows.length > 0) {
                    if (!noResultsRow) {
                        const tr = document.createElement('tr');
                        tr.className = 'no-results-row';
                        tr.innerHTML = `
                    <td colspan="7" class="text-center py-5">
                        <div class="no-results">
                            <div class="no-results-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h4 class="text-muted mb-2">No elections found</h4>
                            <p class="text-muted mb-4">Try adjusting your filters or search terms</p>
                            <button class="btn btn-purple" id="clearFilters2">
                                <i class="fas fa-times me-2"></i> Clear All Filters
                            </button>
                        </div>
                    </td>
                `;
                        tableBody.appendChild(tr);

                        document.getElementById('clearFilters2').addEventListener('click', () => {
                            this.clearFilters();
                        });
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
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
                document.getElementById('searchElections').value = '';
                document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                document.querySelector('.status-filter-btn[data-status="all"]').classList.add('active');
                document.getElementById('startDateFilter').value = '';
                document.getElementById('endDateFilter').value = '';
                document.getElementById('sortFilter').value = 'newest';

                document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));

                this.applyFilters();
            }
        }

        // Election Management System
        class ElectionManagementSystem {
            constructor() {
                this.electionData = electionData;
                this.filterSystem = null;
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupDataTable();
                this.handleURLParameters();

                // Initialize filter system
                this.filterSystem = new ElectionFilterSystem();
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
            }

            setupDataTable() {
                // Initialize DataTables if needed
                if ($.fn.DataTable) {
                    $('#electionsTable').DataTable({
                        paging: true,
                        searching: false, // We'll use custom search
                        ordering: false, // We'll use custom sorting
                        info: true,
                        responsive: true,
                        language: {
                            emptyTable: "No elections found",
                            info: "Showing _START_ to _END_ of _TOTAL_ elections",
                            infoEmpty: "Showing 0 to 0 of 0 elections",
                            infoFiltered: "(filtered from _MAX_ total elections)",
                            lengthMenu: "Show _MENU_ elections",
                            loadingRecords: "Loading...",
                            zeroRecords: "No matching elections found"
                        }
                    });
                }
            }

            handleURLParameters() {
                const urlParams = new URLSearchParams(window.location.search);
                const statusParam = urlParams.get('status_filter');

                if (statusParam && ['draft', 'upcoming', 'active', 'completed'].includes(statusParam)) {
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

        // Function to confirm election deletion
        function confirmDelete(electionId) {
            sweetAlertHandler.confirmAction(
                'Delete Election',
                'Are you sure you want to delete this election? This action will also delete all associated candidates and votes. This action cannot be undone.',
                'Yes, delete it!'
            ).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to delete script
                    window.location.href = 'delete_election.php?id=' + electionId;
                }
            });
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.electionSystem = new ElectionManagementSystem();

            // Add date validation for election form
            const startDateInput = document.getElementById('start');
            const endDateInput = document.getElementById('end');

            if (startDateInput && endDateInput) {
                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                startDateInput.min = today;

                // Update end date min when start date changes
                startDateInput.addEventListener('change', function () {
                    endDateInput.min = this.value;

                    // If end date is before start date, reset it
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = this.value;
                    }
                });

                // Validate end date on change
                endDateInput.addEventListener('change', function () {
                    if (startDateInput.value && this.value < startDateInput.value) {
                        sweetAlertHandler.showNotification('error', 'End date cannot be before start date.');
                        this.value = startDateInput.value;
                    }
                });
            }
        });
    </script>
</body>

</html>