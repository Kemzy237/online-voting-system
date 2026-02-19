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
include "app/model/elections.php";
$voters = get_all_voters($conn);
if ($voters == 0) {
    $num_voters = 0;
} else {
    $num_voters = count($voters);
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

$verified_voters = get_all_verified_voters($conn);
if ($verified_voters == 0) {
    $num_verified_voters = 0;
} else {
    $num_verified_voters = count($verified_voters);
}

$pending_voters = get_all_pending_voters($conn);
if ($pending_voters == 0) {
    $num_pending_voters = 0;
} else {
    $num_pending_voters = count($pending_voters);
}

$suspended_voters = get_all_suspended_voters($conn);
if ($suspended_voters == 0) {
    $num_suspended_voters = 0;
} else {
    $num_suspended_voters = count($suspended_voters);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Management | SecureVote Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Select2 CSS for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        .stat-icon.verified {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.suspended {
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

        .filter-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            grid-column: 1 / -1;
        }

        .filter-actions .btn {
            min-width: 120px;
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

        /* Voter Table */
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

        .status-active {
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
                <h1>Voter Management</h1>
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
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number" id="totalVoters"><?= $num_voters ?></div>
                        <div class="stat-label">Total Registered Voters</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="verified">
                        <div class="stat-icon verified">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-number" id="verifiedVoters"><?= $num_verified_voters ?></div>
                        <div class="stat-label">Verified Voters</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="pending">
                        <div class="stat-icon pending">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-number" id="pendingVoters"><?= $num_pending_voters ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card" data-status="suspended">
                        <div class="stat-icon suspended">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="stat-number" id="suspendedVoters"><?= $num_suspended_voters ?></div>
                        <div class="stat-label">Suspended Accounts</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h3><i class="fas fa-filter me-2"></i> Filter & Search Voters</h3>
                    <button class="filter-toggle" id="filterToggle" data-bs-toggle="collapse"
                        data-bs-target="#filterCollapse">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <div class="collapse show" id="filterCollapse">
                    <div class="filter-body">
                        <!-- Search Box -->
                        <div class="filter-group">
                            <label for="searchVoter"><i class="fas fa-search me-2"></i>Search by Name or Email</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchVoter"
                                    placeholder="Type voter name or email...">
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label><i class="fas fa-user-tag me-2"></i>Filter by Status</label>
                            <div class="status-filter-buttons">
                                <button class="status-filter-btn active" data-status="all">All Voters</button>
                                <button class="status-filter-btn" data-status="verified">Verified</button>
                                <button class="status-filter-btn" data-status="pending">Pending</button>
                                <button class="status-filter-btn" data-status="suspended">Suspended</button>
                            </div>
                        </div>

                        <!-- Registration Date Filter -->
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt me-2"></i>Registration Date</label>
                            <select class="form-select" id="dateFilter">
                                <option value="all">All Dates</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>

                        <!-- Sort Options -->
                        <div class="filter-group">
                            <label><i class="fas fa-sort me-2"></i>Sort By</label>
                            <select class="form-select" id="sortFilter">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="name_asc">Name (A-Z)</option>
                                <option value="name_desc">Name (Z-A)</option>
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

            <!-- Voters Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Registered Voters <span id="filteredCount"
                            class="badge bg-purple ms-2"><?= $num_voters ?></span></h3>
                    <div class="action-buttons">
                        <button class="btn btn-purple" id="addVoterBtn">
                            <i class="fas fa-user-plus me-2"></i> Add New Voter
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if ($voters != 0) { ?>
                        <table id="votersTable" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Store voter data in JavaScript array for modal access
                                $voter_data_json = [];
                                foreach ($voters as $voter) {
                                    // Get initials for avatar
                                    $initials = '';
                                    $name_parts = explode(' ', $voter['full_name']);
                                    if (count($name_parts) >= 2) {
                                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
                                    } else {
                                        $initials = strtoupper(substr($voter['full_name'], 0, 2));
                                    }

                                    // Format registration date for table
                                    $reg_date = date('d M, Y', strtotime($voter['created_at']));

                                    // Store voter data for JavaScript
                                    $voter_data_json[] = [
                                        'id' => $voter['id'],
                                        'full_name' => htmlspecialchars($voter['full_name']),
                                        'email' => htmlspecialchars($voter['email']),
                                        'contact' => htmlspecialchars($voter['contact'] ?? 'Not provided'),
                                        'dob' => !empty($voter['dob']) ? date('d F, Y', strtotime($voter['dob'])) : 'Not provided',
                                        'address' => htmlspecialchars($voter['address'] ?? 'Not provided'),
                                        'created_at' => date('d F, Y \a\t h:i A', strtotime($voter['created_at'])),
                                        'status' => $voter['status'],
                                        'initials' => $initials,
                                        'reg_date' => $reg_date
                                    ];
                                    ?>
                                    <tr data-voter-id="<?= $voter['id'] ?>" data-status="<?= $voter['status'] ?>"
                                        data-name="<?= htmlspecialchars($voter['full_name']) ?>"
                                        data-email="<?= htmlspecialchars($voter['email']) ?>"
                                        data-reg-date="<?= $voter['created_at'] ?>">
                                        <td>
                                            <div class="admin-avatar me-2"
                                                style="width: 36px; height: 36px; font-size: 0.9rem;">
                                                <?= $initials ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?= htmlspecialchars($voter['full_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($voter['email']) ?></td>
                                        <td><?= $reg_date ?></td>
                                        <?php if ($voter['status'] == 'verified') { ?>
                                            <td><span class="status-badge status-active"><?= $voter['status'] ?></span></td>
                                        <?php } else if ($voter['status'] == 'pending') { ?>
                                                <td><span class="status-badge status-pending"><?= $voter['status'] ?></span></td>
                                        <?php } else if ($voter['status'] == 'suspended') { ?>
                                                    <td><span class="status-badge status-suspended"><?= $voter['status'] ?></span></td>
                                        <?php } else { ?>
                                                    <td><span class="status-badge status-inactive">Inactive</span></td>
                                        <?php } ?>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn-action btn-view" data-voter-id="<?= $voter['id'] ?>"
                                                    title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a class="btn-action btn-edit" href="edit_voter.php?id=<?= $voter['id'] ?>"
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn-delete btn-action" data-bs-toggle="modal"
                                                    data-bs-target="#deleteVoterModal" title="Delete">
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
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h4>No Voters Found</h4>
                            <p class="text-muted">No voters have registered yet.</p>
                            <button class="btn btn-purple" id="addFirstVoterBtn">
                                <i class="fas fa-user-plus me-2"></i> Add First Voter
                            </button>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Voter Modal -->
    <div class="modal fade" id="deleteVoterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <i class="fas fa-trash-alt fa-3x text-danger"></i>
                        </div>
                        <h4 class="text-danger mb-3">Are you sure?</h4>
                        <p class="mb-0" id="deleteVoterName">You are about to delete <span class="fw-bold"></span>. This
                            action cannot be undone.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <a class="btn btn-danger" id="deleteVoterLink" href="#">
                        <i class="fas fa-trash-alt me-2"></i> Delete Voter
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Voter Modal -->
    <div class="modal fade" id="addVoterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Voter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addVoterForm" method="POST" action="app/add_voter.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fullName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="fullName" name="full_name" required>
                                <div class="invalid-feedback">Please enter the voter's full name.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="contact" name="contact" required>
                                <div class="invalid-feedback">Please enter a phone number.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dob" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="dob" name="dob" required
                                    max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                                <div class="invalid-feedback">Please enter a valid date of birth (must be 18+).</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"
                                placeholder="Enter full address"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                        minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirmPassword" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password"
                                    required>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Account Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="verified" selected>verified</option>
                                    <option value="pending">Pending</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle me-2"></i>Fields marked with * are required. Voter ID is
                                auto-generated.</small>
                        </div>

                        <div class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= bin2hex(random_bytes(32)) ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-purple">
                                <i class="fas fa-user-plus me-2"></i> Add Voter
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Voter Details Modal -->
    <div class="modal fade" id="viewVoterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i> Voter Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="voterDetailsContent">
                        <!-- Voter details will be dynamically loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Close
                    </button>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Store voter data from PHP
        const voterData = <?php echo json_encode($voter_data_json ?? []); ?>;

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

        // Filter and Search System
        class FilterSystem {
            constructor() {
                this.currentFilters = {
                    search: '',
                    status: 'all',
                    date: 'all',
                    sort: 'newest'
                };
                this.filteredVoters = [];
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.applyFilters();
            }

            setupEventListeners() {
                // Search input
                document.getElementById('searchVoter').addEventListener('input', (e) => {
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

                // Date filter
                document.getElementById('dateFilter').addEventListener('change', (e) => {
                    this.currentFilters.date = e.target.value;
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
                const rows = document.querySelectorAll('#votersTable tbody tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    let showRow = true;

                    // Apply search filter
                    if (this.currentFilters.search) {
                        const name = row.dataset.name.toLowerCase();
                        const email = row.dataset.email.toLowerCase();
                        showRow = showRow && (name.includes(this.currentFilters.search) || email.includes(this.currentFilters.search));
                    }

                    // Apply status filter
                    if (this.currentFilters.status !== 'all') {
                        showRow = showRow && (row.dataset.status === this.currentFilters.status);
                    }

                    // Apply date filter
                    if (this.currentFilters.date !== 'all') {
                        const regDate = new Date(row.dataset.regDate);
                        const today = new Date();
                        let include = false;

                        switch (this.currentFilters.date) {
                            case 'today':
                                include = regDate.toDateString() === today.toDateString();
                                break;
                            case 'week':
                                const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                                include = regDate >= weekAgo;
                                break;
                            case 'month':
                                const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
                                include = regDate >= monthAgo;
                                break;
                            case 'year':
                                const yearAgo = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
                                include = regDate >= yearAgo;
                                break;
                        }

                        showRow = showRow && include;
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
                const tbody = document.querySelector('#votersTable tbody');
                const rowsArray = Array.from(rows);

                rowsArray.sort((a, b) => {
                    switch (this.currentFilters.sort) {
                        case 'oldest':
                            return new Date(a.dataset.regDate) - new Date(b.dataset.regDate);
                        case 'name_asc':
                            return a.dataset.name.localeCompare(b.dataset.name);
                        case 'name_desc':
                            return b.dataset.name.localeCompare(a.dataset.name);
                        default: // newest
                            return new Date(b.dataset.regDate) - new Date(a.dataset.regDate);
                    }
                });

                // Reorder rows in DOM
                rowsArray.forEach(row => tbody.appendChild(row));
            }

            showNoResultsMessage(visibleCount) {
                const tableBody = document.querySelector('#votersTable tbody');
                const noResultsRow = tableBody.querySelector('.no-results-row');

                if (visibleCount === 0 && rows.length > 0) {
                    if (!noResultsRow) {
                        const tr = document.createElement('tr');
                        tr.className = 'no-results-row';
                        tr.innerHTML = `
                    <td colspan="6" class="text-center py-5">
                        <div class="no-results">
                            <div class="no-results-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h4 class="text-muted mb-2">No voters found</h4>
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
                    date: 'all',
                    sort: 'newest'
                };

                // Reset UI
                document.getElementById('searchVoter').value = '';
                document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                document.querySelector('.status-filter-btn[data-status="all"]').classList.add('active');
                document.getElementById('dateFilter').value = 'all';
                document.getElementById('sortFilter').value = 'newest';

                document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));

                this.applyFilters();
            }
        }

        // Voter Management System
        class VoterManagementSystem {
            constructor() {
                this.voterData = voterData;
                this.filterSystem = null;
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupDataTable();
                this.handleURLParameters();

                // Initialize filter system
                this.filterSystem = new FilterSystem();
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

                // Add voter button
                document.getElementById('addVoterBtn')?.addEventListener('click', () => {
                    this.showAddVoterForm();
                });

                // Add first voter button
                document.getElementById('addFirstVoterBtn')?.addEventListener('click', () => {
                    this.showAddVoterForm();
                });

                // View voter details
                document.querySelector('#votersTable')?.addEventListener('click', (e) => {
                    if (e.target.closest('.btn-view')) {
                        const voterId = e.target.closest('.btn-view').dataset.voterId;
                        this.viewVoterDetails(voterId);
                    }
                });
            }

            setupDataTable() {
                // Initialize DataTables if needed
                if ($.fn.DataTable) {
                    $('#votersTable').DataTable({
                        paging: true,
                        searching: false, // We'll use custom search
                        ordering: false, // We'll use custom sorting
                        info: true,
                        responsive: true,
                        language: {
                            emptyTable: "No voters found",
                            info: "Showing _START_ to _END_ of _TOTAL_ voters",
                            infoEmpty: "Showing 0 to 0 of 0 voters",
                            infoFiltered: "(filtered from _MAX_ total voters)",
                            lengthMenu: "Show _MENU_ voters",
                            loadingRecords: "Loading...",
                            zeroRecords: "No matching voters found"
                        }
                    });
                }
            }

            showAddVoterForm() {
                const modal = new bootstrap.Modal(document.getElementById('addVoterModal'));
                modal.show();
            }

            viewVoterDetails(voterId) {
                const voter = this.voterData.find(v => v.id == voterId);

                if (!voter) {
                    sweetAlertHandler.showNotification('error', 'Voter not found.');
                    return;
                }

                let statusBadge = '';
                if (voter.status === 'verified') {
                    statusBadge = '<span class="status-badge status-active">Active</span>';
                } else if (voter.status === 'pending') {
                    statusBadge = '<span class="status-badge status-pending">Pending</span>';
                } else if (voter.status === 'suspended') {
                    statusBadge = '<span class="status-badge status-suspended">Suspended</span>';
                } else {
                    statusBadge = '<span class="status-badge status-inactive">Inactive</span>';
                }

                const content = `
            <div class="row">
                <div class="col-md-4 text-center mb-4">
                    <div class="mb-3">
                        <div class="admin-avatar mx-auto" style="width: 100px; height: 100px; font-size: 2.5rem;">
                            ${voter.initials}
                        </div>
                    </div>
                    <h5 class="mb-1">${voter.full_name}</h5>
                    <p class="text-muted">Voter ID: ${voter.id}</p>
                </div>
                <div class="col-md-8">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Email</label>
                            <p><strong>${voter.email}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Phone</label>
                            <p><strong>${voter.contact}</strong></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Date of Birth</label>
                            <p><strong>${voter.dob}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Address</label>
                            <p><strong>${voter.address}</strong></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Registration Date</label>
                            <p><strong>${voter.created_at}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Account Status</label>
                            <p>${statusBadge}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

                document.getElementById('voterDetailsContent').innerHTML = content;
                const modal = new bootstrap.Modal(document.getElementById('viewVoterModal'));
                modal.show();
            }

            handleURLParameters() {
                const urlParams = new URLSearchParams(window.location.search);
                const statusParam = urlParams.get('status_filter');

                if (statusParam && ['verified', 'pending', 'suspended'].includes(statusParam)) {
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

        // Delete Voter Handler
        class DeleteVoterHandler {
            constructor() {
                this.currentVoterId = null;
                this.currentVoterName = null;
                this.setupEventListeners();
            }

            setupEventListeners() {
                document.querySelector('#votersTable')?.addEventListener('click', (e) => {
                    if (e.target.closest('.btn-delete')) {
                        const row = e.target.closest('tr[data-voter-id]');

                        if (row) {
                            this.currentVoterId = row.dataset.voterId;
                            this.currentVoterName = row.dataset.name;
                            this.populateDeleteModal();
                        }
                    }
                });
            }

            populateDeleteModal() {
                const deleteVoterNameSpan = document.querySelector('#deleteVoterName span');
                if (deleteVoterNameSpan) {
                    deleteVoterNameSpan.textContent = this.currentVoterName;
                }

                const deleteLink = document.getElementById('deleteVoterLink');
                if (deleteLink && this.currentVoterId) {
                    deleteLink.href = `delete_voter.php?id=${this.currentVoterId}`;
                }
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.voterSystem = new VoterManagementSystem();
            window.deleteVoterHandler = new DeleteVoterHandler();

            // Add voter form validation
            const addVoterForm = document.getElementById('addVoterForm');
            if (addVoterForm) {
                addVoterForm.addEventListener('submit', function (e) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;

                    if (password !== confirmPassword) {
                        e.preventDefault();
                        sweetAlertHandler.showNotification('error', 'Passwords do not match.');
                        return false;
                    }

                    return true;
                });
            }

            // Password toggle
            const togglePasswordBtn = document.getElementById('togglePassword');
            if (togglePasswordBtn) {
                togglePasswordBtn.addEventListener('click', function () {
                    const passwordInput = document.getElementById('password');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
        });
    </script>
</body>

</html>