<?php
// admin_reports.php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Include database connection
include "db_connection.php";

// Set timezone
date_default_timezone_set('Africa/Douala');

// Initialize variables - Only overview report now
$report_type = 'overview'; // Fixed to overview only
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'html';

// Validate dates
if (!empty($start_date) && !empty($end_date)) {
    if (strtotime($end_date) < strtotime($start_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }
}

// Function to generate overview report only
function generateOverviewReport($conn, $start_date, $end_date)
{
    $report = [];

    // Format dates for SQL
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    // Key metrics - Fixed: Each UNION query has its own date range
    $stmt = $conn->prepare("
        SELECT 
            'Total Elections' as metric,
            COUNT(*) as value
        FROM elections
        WHERE created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'Active Elections' as metric,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as value
        FROM elections
        WHERE created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'New Voters' as metric,
            COUNT(*) as value
        FROM voters
        WHERE created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'Total Votes' as metric,
            COUNT(*) as value
        FROM votes
        WHERE vote_timestamp BETWEEN ? AND ?
            AND status = 'verified'
        UNION ALL
        SELECT 
            'Candidate Applications' as metric,
            COUNT(*) as value
        FROM candidates
        WHERE created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'Verified Voters' as metric,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as value
        FROM voters
        WHERE created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'Completed Elections' as metric,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as value
        FROM elections
        WHERE created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'Pending Candidates' as metric,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as value
        FROM candidates
        WHERE created_at BETWEEN ? AND ?
    ");

    // Execute with parameters - Each UNION query needs its own date parameters
    $stmt->execute([
        $start_datetime,
        $end_datetime,  // Total Elections
        $start_datetime,
        $end_datetime,  // Active Elections  
        $start_datetime,
        $end_datetime,  // New Voters
        $start_datetime,
        $end_datetime,  // Total Votes
        $start_datetime,
        $end_datetime,  // Candidate Applications
        $start_datetime,
        $end_datetime,  // Verified Voters
        $start_datetime,
        $end_datetime,  // Completed Elections
        $start_datetime,
        $end_datetime   // Pending Candidates
    ]);

    $report['key_metrics'] = $stmt->fetchAll();

    // Recent elections
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            COUNT(DISTINCT c.id) as candidate_count,
            COUNT(DISTINCT v.id) as vote_count
        FROM elections e
        LEFT JOIN candidates c ON e.id = c.election_id
        LEFT JOIN votes v ON e.id = v.election_id AND v.status = 'verified'
        WHERE e.created_at BETWEEN ? AND ?
        GROUP BY e.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$start_datetime, $end_datetime]);
    $report['recent_elections'] = $stmt->fetchAll();

    // Election status distribution (all time, not filtered by date)
    $stmt = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM elections), 1) as percentage
        FROM elections
        GROUP BY status
        ORDER BY FIELD(status, 'active', 'upcoming', 'completed', 'draft', 'cancelled')
    ");
    $report['election_status'] = $stmt->fetchAll();

    // Voter status distribution (all time)
    $stmt = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM voters), 1) as percentage
        FROM voters
        GROUP BY status
        ORDER BY FIELD(status, 'verified', 'pending', 'suspended')
    ");
    $report['voter_status'] = $stmt->fetchAll();

    // Voting activity by day for the selected period
    $stmt = $conn->prepare("
        SELECT 
            DATE(vote_timestamp) as date,
            COUNT(*) as total_votes,
            COUNT(DISTINCT voter_id) as unique_voters
        FROM votes
        WHERE vote_timestamp BETWEEN ? AND ?
            AND status = 'verified'
        GROUP BY DATE(vote_timestamp)
        ORDER BY date
    ");
    $stmt->execute([$start_datetime, $end_datetime]);
    $report['voting_activity'] = $stmt->fetchAll();

    // Candidate status distribution (all time)
    $stmt = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM candidates), 1) as percentage
        FROM candidates
        GROUP BY status
        ORDER BY FIELD(status, 'approved', 'pending', 'disqualified', 'withdrawn')
    ");
    $report['candidate_status'] = $stmt->fetchAll();

    // Top 5 candidates by votes
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            v.full_name as candidate_name,
            c.party_affiliation,
            e.title as election_title,
            COUNT(vt.id) as vote_count
        FROM candidates c
        JOIN voters v ON c.voter_id = v.id
        JOIN elections e ON c.election_id = e.id
        LEFT JOIN votes vt ON c.id = vt.candidate_id AND vt.status = 'verified'
        WHERE vt.vote_timestamp BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY vote_count DESC
        LIMIT 5
    ");
    $stmt->execute([$start_datetime, $end_datetime]);
    $report['top_candidates'] = $stmt->fetchAll();

    // System performance metrics - Fixed parameter count
    $stmt = $conn->prepare("
        SELECT 
            'Voter Participation Rate' as metric,
            ROUND(
                (SELECT COUNT(DISTINCT voter_id) FROM votes WHERE status = 'verified' AND vote_timestamp BETWEEN ? AND ?) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM voters WHERE status = 'verified'), 0), 
                2
            ) as value,
            '%' as unit
        UNION ALL
        SELECT 
            'Average Votes per Election' as metric,
            ROUND(
                (SELECT COUNT(*) FROM votes WHERE status = 'verified' AND vote_timestamp BETWEEN ? AND ?) / 
                NULLIF((SELECT COUNT(*) FROM elections WHERE status IN ('completed', 'active') AND created_at BETWEEN ? AND ?), 0),
                2
            ) as value,
            'votes' as unit
        UNION ALL
        SELECT 
            'Registration Completion Rate' as metric,
            ROUND(
                (SELECT COUNT(*) FROM voters WHERE status = 'verified' AND created_at BETWEEN ? AND ?) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM voters WHERE created_at BETWEEN ? AND ?), 0),
                2
            ) as value,
            '%' as unit
        UNION ALL
        SELECT 
            'Candidate Approval Rate' as metric,
            ROUND(
                (SELECT COUNT(*) FROM candidates WHERE status = 'approved' AND created_at BETWEEN ? AND ?) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM candidates WHERE created_at BETWEEN ? AND ?), 0),
                2
            ) as value,
            '%' as unit
    ");

    // Each subquery needs its own date parameters - total 12 placeholders
    $stmt->execute([
        $start_datetime,
        $end_datetime,  // Voter Participation Rate - subquery 1
        $start_datetime,
        $end_datetime,  // Average Votes - subquery 1
        $start_datetime,
        $end_datetime,  // Average Votes - subquery 2
        $start_datetime,
        $end_datetime,  // Registration Completion - subquery 1
        $start_datetime,
        $end_datetime,  // Registration Completion - subquery 2
        $start_datetime,
        $end_datetime,  // Candidate Approval - subquery 1
        $start_datetime,
        $end_datetime   // Candidate Approval - subquery 2
    ]);

    $report['performance_metrics'] = $stmt->fetchAll();

    return $report;
}

// Generate the report
$report = generateOverviewReport($conn, $start_date, $end_date);

// Export if requested
if ($format !== 'html' && !empty($report)) {
    // Export function for overview report
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_overview_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Export key metrics
    fputcsv($output, ['Key Metrics']);
    fputcsv($output, ['Metric', 'Value']);
    foreach ($report['key_metrics'] as $metric) {
        fputcsv($output, [$metric['metric'], $metric['value']]);
    }

    // Export recent elections
    fputcsv($output, []);
    fputcsv($output, ['Recent Elections']);
    if (!empty($report['recent_elections'])) {
        $headers = array_keys($report['recent_elections'][0]);
        fputcsv($output, $headers);
        foreach ($report['recent_elections'] as $election) {
            fputcsv($output, $election);
        }
    }

    fclose($output);
    exit();
}

// Report titles - Only overview now
$report_titles = [
    'overview' => 'System Overview Report'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | SecureVote Admin</title>
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
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

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
        .sidebar-menu a.active_6 {
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

        /* Reports Content */
        .reports-content {
            padding: 0 2rem 2rem;
        }

        /* Report Filters */
        .report-filters {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filter-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .filter-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-title i {
            color: var(--admin-color);
        }

        /* Date Range Picker */
        .date-range {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-input {
            flex: 1;
            min-width: 150px;
        }

        .date-input label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary-color);
        }

        /* Report Display */
        .report-display {
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .report-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .export-options {
            display: flex;
            gap: 0.5rem;
        }

        .export-btn {
            padding: 0.5rem 1rem;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn:hover {
            background-color: #f8f9fa;
            border-color: var(--admin-color);
            color: var(--admin-color);
        }

        /* Report Sections */
        .report-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--admin-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--admin-color);
        }

        /* Metrics Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            border-left: 4px solid var(--admin-color);
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-3px);
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .metric-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .metric-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .change-up {
            color: var(--success-color);
        }

        .change-down {
            color: var(--danger-color);
        }

        /* Charts */
        .chart-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            height: 300px;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .data-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .data-table tr:hover {
            background-color: #f8f9fa;
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

        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-completed {
            background-color: rgba(155, 89, 182, 0.1);
            color: var(--admin-color);
        }

        .status-draft {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Progress Bars */
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
            background-color: var(--admin-color);
            transition: width 0.3s ease;
        }

        /* Loading State */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            color: #6c757d;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--admin-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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
            .reports-content {
                padding: 0 1rem 1rem;
            }

            .page-header {
                padding: 1.5rem 1rem;
            }

            .report-filters,
            .report-display {
                padding: 1.5rem;
            }

            .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .export-options {
                width: 100%;
                justify-content: flex-start;
            }

            .date-range {
                flex-direction: column;
                align-items: stretch;
            }

            .date-input {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .metrics-grid {
                grid-template-columns: 1fr;
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
                <h1>System Overview Report</h1>
            </div>
            <div class="header-right">
                <span class="text-muted">Generated: <?= date('F j, Y h:i A') ?></span>
            </div>
        </header>

        <!-- Reports Content -->
        <main class="reports-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">System Overview Report</h1>
                <p class="page-subtitle">Comprehensive overview of your voting system statistics and performance</p>
            </div>

            <!-- Report Filters -->
            <div class="report-filters">
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-filter"></i>
                        Select Date Range
                    </h3>
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                    value="<?= $start_date ?>" max="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                    value="<?= $end_date ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Generate Report
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                <i class="fas fa-redo"></i> Reset Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Display -->
            <div class="report-display">
                <div class="report-header">
                    <h2>System Overview Report</h2>
                    <div class="export-options">
                        <span class="text-muted me-2">Export as:</span>
                        <a href="?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&format=csv" class="export-btn"
                            target="_blank">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>

                <!-- Overview Report -->
                <?php if (isset($report['key_metrics']) && !empty($report['key_metrics'])): ?>
                    <div class="report-section">
                        <h3 class="section-title">
                            <i class="fas fa-tachometer-alt"></i>
                            Key Performance Indicators
                        </h3>
                        <div class="metrics-grid">
                            <?php foreach ($report['key_metrics'] as $metric): ?>
                                <div class="metric-card">
                                    <div class="metric-value"><?= number_format($metric['value']) ?></div>
                                    <div class="metric-label"><?= $metric['metric'] ?></div>
                                    <div class="metric-period">
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($start_date)) ?> -
                                            <?= date('M j, Y', strtotime($end_date)) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="report-section">
                                <h3 class="section-title">
                                    <i class="fas fa-poll"></i>
                                    Election Status Distribution
                                </h3>
                                <div class="chart-container">
                                    <canvas id="electionStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-section">
                                <h3 class="section-title">
                                    <i class="fas fa-users"></i>
                                    Voter Status Distribution
                                </h3>
                                <div class="chart-container">
                                    <canvas id="voterStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="report-section">
                                <h3 class="section-title">
                                    <i class="fas fa-user-tie"></i>
                                    Candidate Status Distribution
                                </h3>
                                <div class="chart-container">
                                    <canvas id="candidateStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-section">
                                <h3 class="section-title">
                                    <i class="fas fa-chart-line"></i>
                                    Voting Activity Trend
                                </h3>
                                <div class="chart-container">
                                    <canvas id="votingActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="report-section">
                        <h3 class="section-title">
                            <i class="fas fa-trophy"></i>
                            Top Performing Candidates
                        </h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th>Party</th>
                                    <th>Election</th>
                                    <th>Votes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($report['top_candidates'])): ?>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($report['top_candidates'] as $candidate): ?>
                                        <tr>
                                            <td>#<?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($candidate['candidate_name']) ?></td>
                                            <td><?= htmlspecialchars($candidate['party_affiliation'] ?? 'Independent') ?></td>
                                            <td><?= htmlspecialchars($candidate['election_title']) ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= $candidate['vote_count'] ?></div>
                                                <?php if (!empty($report['top_candidates'])): ?>
                                                    <div class="progress-container">
                                                        <?php
                                                        // Find max votes for percentage calculation
                                                        $max_votes = max(array_column($report['top_candidates'], 'vote_count'));
                                                        $percentage = $max_votes > 0 ? ($candidate['vote_count'] / $max_votes) * 100 : 0;
                                                        ?>
                                                        <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            No voting activity in the selected period
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-bar"></i>
                            System Performance Metrics
                        </h3>
                        <div class="metrics-grid">
                            <?php if (!empty($report['performance_metrics'])): ?>
                                <?php foreach ($report['performance_metrics'] as $metric): ?>
                                    <div class="metric-card">
                                        <div class="metric-value"><?= $metric['value'] ?></div>
                                        <div class="metric-label"><?= $metric['metric'] ?></div>
                                        <div class="metric-unit">
                                            <small class="text-muted"><?= $metric['unit'] ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center text-muted">
                                    No performance metrics available for the selected period
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="report-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Recent Elections
                        </h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Candidates</th>
                                    <th>Votes</th>
                                    <th>Created</th>
                                    <th>Period</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($report['recent_elections'])): ?>
                                    <?php foreach ($report['recent_elections'] as $election): ?>
                                        <tr>
                                            <td>#<?= $election['id'] ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($election['title']) ?></div>
                                                <?php if (!empty($election['description'])): ?>
                                                    <small class="text-muted">
                                                        <?= substr(htmlspecialchars($election['description']), 0, 50) ?>...
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $election['status'] ?>">
                                                    <?= ucfirst($election['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= $election['candidate_count'] ?></td>
                                            <td><?= $election['vote_count'] ?></td>
                                            <td><?= date('M d, Y', strtotime($election['created_at'])) ?></td>
                                            <td>
                                                <?= date('M d', strtotime($election['start_datetime'])) ?> -
                                                <?= date('M d, Y', strtotime($election['end_datetime'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">
                                            No elections created in the selected period
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        No data found for the selected date range. Please try a different date range.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Report Footer -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Report Summary:</strong>
                This system overview report was generated on <?= date('F j, Y \a\t h:i A') ?>
                for the period <?= date('M j, Y', strtotime($start_date)) ?> to
                <?= date('M j, Y', strtotime($end_date)) ?>.
                <?php if (isset($report['key_metrics']) && !empty($report['key_metrics'])): ?>
                    Total records analyzed: <?= array_sum(array_column($report['key_metrics'], 'value')) ?>.
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize sidebar toggle
            initSidebarToggle();

            // Initialize filters
            initFilters();

            // Initialize charts if data exists
            <?php if (isset($report['key_metrics']) && !empty($report['key_metrics'])): ?>
                initCharts();
            <?php endif; ?>

            // Initialize DataTables
            initDataTables();
        });

        function initSidebarToggle() {
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

        function initFilters() {
            // Reset filters button
            const resetBtn = document.getElementById('resetFilters');
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    const today = new Date().toISOString().split('T')[0];
                    const firstDay = new Date();
                    firstDay.setDate(1);
                    const firstDayStr = firstDay.toISOString().split('T')[0];

                    document.getElementById('start_date').value = firstDayStr;
                    document.getElementById('end_date').value = today;
                });
            }

            // Date range validation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            if (startDate && endDate) {
                startDate.addEventListener('change', function () {
                    endDate.min = this.value;
                    if (endDate.value && endDate.value < this.value) {
                        endDate.value = this.value;
                    }
                });

                endDate.addEventListener('change', function () {
                    if (startDate.value && this.value < startDate.value) {
                        this.value = startDate.value;
                    }
                });
            }
        }

        function initCharts() {
            // Election Status Chart
            const electionCtx = document.getElementById('electionStatusChart');
            if (electionCtx) {
                const electionData = <?= json_encode($report['election_status']) ?>;
                if (electionData && electionData.length > 0) {
                    new Chart(electionCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: electionData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                            datasets: [{
                                data: electionData.map(item => item.count),
                                backgroundColor: [
                                    '#2ecc71', // Active - green
                                    '#f39c12', // Upcoming - orange
                                    '#9b59b6', // Completed - purple
                                    '#7f8c8d', // Draft - gray
                                    '#e74c3c'  // Cancelled - red
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    electionCtx.parentElement.innerHTML = '<div class="text-center text-muted py-5">No election data available</div>';
                }
            }

            // Voter Status Chart
            const voterCtx = document.getElementById('voterStatusChart');
            if (voterCtx) {
                const voterData = <?= json_encode($report['voter_status']) ?>;
                if (voterData && voterData.length > 0) {
                    new Chart(voterCtx.getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: voterData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                            datasets: [{
                                data: voterData.map(item => item.count),
                                backgroundColor: [
                                    '#2ecc71', // Verified - green
                                    '#f39c12', // Pending - orange
                                    '#e74c3c'  // Suspended - red
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                } else {
                    voterCtx.parentElement.innerHTML = '<div class="text-center text-muted py-5">No voter data available</div>';
                }
            }

            // Candidate Status Chart
            const candidateCtx = document.getElementById('candidateStatusChart');
            if (candidateCtx) {
                const candidateData = <?= json_encode($report['candidate_status']) ?>;
                if (candidateData && candidateData.length > 0) {
                    new Chart(candidateCtx.getContext('2d'), {
                        type: 'polarArea',
                        data: {
                            labels: candidateData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                            datasets: [{
                                data: candidateData.map(item => item.count),
                                backgroundColor: [
                                    '#2ecc71', // Approved - green
                                    '#f39c12', // Pending - orange
                                    '#e74c3c', // Disqualified - red
                                    '#7f8c8d'  // Withdrawn - gray
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                } else {
                    candidateCtx.parentElement.innerHTML = '<div class="text-center text-muted py-5">No candidate data available</div>';
                }
            }

            // Voting Activity Chart
            const votingCtx = document.getElementById('votingActivityChart');
            if (votingCtx) {
                const votingData = <?= json_encode($report['voting_activity']) ?>;
                if (votingData && votingData.length > 0) {
                    const dates = votingData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    });
                    const votes = votingData.map(item => item.total_votes || 0);
                    const voters = votingData.map(item => item.unique_voters || 0);

                    new Chart(votingCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: dates,
                            datasets: [
                                {
                                    label: 'Total Votes',
                                    data: votes,
                                    borderColor: '#9b59b6',
                                    backgroundColor: 'rgba(155, 89, 182, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Unique Voters',
                                    data: voters,
                                    borderColor: '#3498db',
                                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }
                    });
                } else {
                    votingCtx.parentElement.innerHTML = '<div class="text-center text-muted py-5">No voting activity in the selected period</div>';
                }
            }
        }

        function initDataTables() {
            // Initialize all tables with DataTables
            const tables = document.querySelectorAll('.data-table');
            tables.forEach(table => {
                if ($(table).find('tbody tr').length > 0) {
                    $(table).DataTable({
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        order: [],
                        language: {
                            search: "Search:",
                            lengthMenu: "Show _MENU_ entries",
                            info: "Showing _START_ to _END_ of _TOTAL_ entries",
                            paginate: {
                                first: "First",
                                last: "Last",
                                next: "Next",
                                previous: "Previous"
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>

</html>