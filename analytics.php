<?php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) && !isset($_SESSION['id'])) {
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}

// Include database connection
include "db_connection.php";

// Set timezone
date_default_timezone_set('Africa/Douala');

// Get time period filter (default: this month)
$period = isset($_GET['period']) ? $_GET['period'] : 'month';

// Validate period
$valid_periods = ['today', 'week', 'month', 'quarter', 'year'];
if (!in_array($period, $valid_periods)) {
    $period = 'month';
}

// Calculate date ranges based on period
$date_ranges = calculateDateRanges($period);

// Function to calculate date ranges based on period
function calculateDateRanges($period)
{
    $ranges = [];
    $today = new DateTime();

    switch ($period) {
        case 'today':
            $ranges['start_date'] = $today->format('Y-m-d');
            $ranges['end_date'] = $today->format('Y-m-d');
            $ranges['previous_start'] = $today->modify('-1 day')->format('Y-m-d');
            $ranges['previous_end'] = $ranges['previous_start'];
            $today->modify('+1 day'); // Reset
            break;

        case 'week':
            // This week (Monday to Sunday)
            $ranges['start_date'] = $today->modify('Monday this week')->format('Y-m-d');
            $ranges['end_date'] = $today->modify('Sunday this week')->format('Y-m-d');
            // Previous week
            $ranges['previous_start'] = $today->modify('Monday last week')->format('Y-m-d');
            $ranges['previous_end'] = $today->modify('Sunday last week')->format('Y-m-d');
            break;

        case 'month':
            // This month
            $ranges['start_date'] = $today->modify('first day of this month')->format('Y-m-d');
            $ranges['end_date'] = $today->modify('last day of this month')->format('Y-m-d');
            // Previous month
            $ranges['previous_start'] = $today->modify('first day of last month')->format('Y-m-d');
            $ranges['previous_end'] = $today->modify('last day of last month')->format('Y-m-d');
            break;

        case 'quarter':
            // This quarter
            $current_month = (int) $today->format('n');
            $current_quarter = ceil($current_month / 3);
            $first_month_of_quarter = ($current_quarter - 1) * 3 + 1;

            $ranges['start_date'] = $today->setDate($today->format('Y'), $first_month_of_quarter, 1)->format('Y-m-d');
            $ranges['end_date'] = $today->modify('+2 months')->modify('last day of this month')->format('Y-m-d');

            // Previous quarter
            $ranges['previous_start'] = $today->modify('first day of -3 months')->format('Y-m-d');
            $ranges['previous_end'] = $today->modify('+2 months')->modify('last day of this month')->format('Y-m-d');
            break;

        case 'year':
            // This year
            $ranges['start_date'] = $today->modify('first day of January this year')->format('Y-m-d');
            $ranges['end_date'] = $today->modify('last day of December this year')->format('Y-m-d');
            // Previous year
            $ranges['previous_start'] = $today->modify('first day of January last year')->format('Y-m-d');
            $ranges['previous_end'] = $today->modify('last day of December last year')->format('Y-m-d');
            break;
    }

    return $ranges;
}

// Function to get all analytics data with period filter
function getAnalyticsData($conn, $period, $date_ranges)
{
    $analytics = [];

    // 1. Overall Statistics (for the period)
    $analytics['overall'] = getOverallStats($conn, $period, $date_ranges);

    // 2. Recent Elections (for the period)
    $analytics['recent_elections'] = getRecentElections($conn, $period, $date_ranges);

    // 3. Voter Statistics (for the period)
    $analytics['voter_stats'] = getVoterStatistics($conn, $period, $date_ranges);

    // 4. Voting Trends (for the period)
    $analytics['voting_trends'] = getVotingTrends($conn, $period, $date_ranges);

    // 5. Top Candidates (for the period)
    $analytics['top_candidates'] = getTopCandidates($conn, $period, $date_ranges);

    // 6. Election Status Distribution (current)
    $analytics['election_distribution'] = getElectionDistribution($conn);

    // 7. Comparison with previous period
    $analytics['comparison'] = getPeriodComparison($conn, $period, $date_ranges);

    return $analytics;
}

// Overall Statistics for period
function getOverallStats($conn, $period, $date_ranges)
{
    $stats = [];

    // Total elections (all time)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM elections");
    $stats['total_elections'] = $stmt->fetch()['total'];

    // Elections in current period
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM elections 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $stats['period_elections'] = $stmt->fetch()['total'];

    // Active elections
    $stmt = $conn->query("SELECT COUNT(*) as total FROM elections WHERE status = 'active'");
    $stats['active_elections'] = $stmt->fetch()['total'];

    // Completed elections (in period) - using end_datetime instead of updated_at
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM elections 
        WHERE status = 'completed' 
        AND end_datetime BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $stats['completed_elections'] = $stmt->fetch()['total'];

    // Total voters (all time)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM voters");
    $stats['total_voters'] = $stmt->fetch()['total'];

    // Voters registered in period
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM voters 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $stats['period_voters'] = $stmt->fetch()['total'];

    // Verified voters
    $stmt = $conn->query("SELECT COUNT(*) as total FROM voters WHERE status = 'verified'");
    $stats['verified_voters'] = $stmt->fetch()['total'];

    // Total votes cast (in period)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM votes 
        WHERE status = 'verified' 
        AND vote_timestamp BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $stats['period_votes'] = $stmt->fetch()['total'];

    // Total votes cast (all time)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM votes WHERE status = 'verified'");
    $stats['total_votes'] = $stmt->fetch()['total'];

    // Total candidates (all time)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM candidates");
    $stats['total_candidates'] = $stmt->fetch()['total'];

    return $stats;
}

// Recent Elections for period
function getRecentElections($conn, $period, $date_ranges)
{
    $stmt = $conn->prepare("
        SELECT e.*, 
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
    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    return $stmt->fetchAll();
}

// Voter Statistics for period
function getVoterStatistics($conn, $period, $date_ranges)
{
    $stats = [];

    // Status distribution (current)
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM voters 
        GROUP BY status
    ");
    $stats['status_distribution'] = $stmt->fetchAll();

    // Registration trend for the period
    $date_format = getDateGroupFormat($period);
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, ?) as time_period,
            COUNT(*) as registrations
        FROM voters
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, ?)
        ORDER BY created_at
    ");
    $stmt->execute([$date_format, $date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59', $date_format]);
    $stats['period_registrations'] = $stmt->fetchAll();

    return $stats;
}

// Helper function to get appropriate date grouping format
function getDateGroupFormat($period)
{
    switch ($period) {
        case 'today':
            return '%H:00'; // Group by hour
        case 'week':
            return '%Y-%m-%d'; // Group by day
        case 'month':
            return '%Y-%m-%d'; // Group by day
        case 'quarter':
            return '%Y-%m'; // Group by month
        case 'year':
            return '%Y-%m'; // Group by month
        default:
            return '%Y-%m-%d';
    }
}

// Voting Trends for period
function getVotingTrends($conn, $period, $date_ranges)
{
    $date_format = getDateGroupFormat($period);

    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(v.vote_timestamp, ?) as time_period,
            COUNT(*) as vote_count,
            e.title as election_name
        FROM votes v
        JOIN elections e ON v.election_id = e.id
        WHERE v.status = 'verified'
            AND v.vote_timestamp BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(v.vote_timestamp, ?), e.id
        ORDER BY v.vote_timestamp
    ");
    $stmt->execute([$date_format, $date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59', $date_format]);
    return $stmt->fetchAll();
}

// Top Candidates for period
function getTopCandidates($conn, $period, $date_ranges)
{
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            v.full_name as candidate_name,
            c.party_affiliation,
            e.title as election_title,
            COUNT(vt.id) as vote_count,
            c.profile_image
        FROM candidates c
        JOIN voters v ON c.voter_id = v.id
        JOIN elections e ON c.election_id = e.id
        LEFT JOIN votes vt ON c.id = vt.candidate_id 
            AND vt.status = 'verified'
            AND vt.vote_timestamp BETWEEN ? AND ?
        WHERE (e.status = 'completed' OR e.status = 'active')
        GROUP BY c.id
        ORDER BY vote_count DESC
        LIMIT 10
    ");
    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    return $stmt->fetchAll();
}

// Election Status Distribution (current)
function getElectionDistribution($conn)
{
    $stmt = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM elections), 1) as percentage
        FROM elections
        GROUP BY status
    ");
    return $stmt->fetchAll();
}

// Comparison with previous period
function getPeriodComparison($conn, $period, $date_ranges)
{
    $comparison = [];

    // Votes comparison
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM votes 
        WHERE status = 'verified' 
        AND vote_timestamp BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['previous_start'] . ' 00:00:00', $date_ranges['previous_end'] . ' 23:59:59']);
    $previous_votes = $stmt->fetch()['total'];

    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $current_votes = $stmt->fetch()['total'];

    $comparison['votes'] = [
        'current' => $current_votes,
        'previous' => $previous_votes,
        'change' => $previous_votes > 0 ? (($current_votes - $previous_votes) / $previous_votes) * 100 : 0
    ];

    // Voters comparison
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM voters 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['previous_start'] . ' 00:00:00', $date_ranges['previous_end'] . ' 23:59:59']);
    $previous_voters = $stmt->fetch()['total'];

    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $current_voters = $stmt->fetch()['total'];

    $comparison['voters'] = [
        'current' => $current_voters,
        'previous' => $previous_voters,
        'change' => $previous_voters > 0 ? (($current_voters - $previous_voters) / $previous_voters) * 100 : 0
    ];

    // Elections comparison
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM elections 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_ranges['previous_start'] . ' 00:00:00', $date_ranges['previous_end'] . ' 23:59:59']);
    $previous_elections = $stmt->fetch()['total'];

    $stmt->execute([$date_ranges['start_date'] . ' 00:00:00', $date_ranges['end_date'] . ' 23:59:59']);
    $current_elections = $stmt->fetch()['total'];

    $comparison['elections'] = [
        'current' => $current_elections,
        'previous' => $previous_elections,
        'change' => $previous_elections > 0 ? (($current_elections - $previous_elections) / $previous_elections) * 100 : 0
    ];

    return $comparison;
}

// Get all analytics data with period filter
$analytics = getAnalyticsData($conn, $period, $date_ranges);

// Calculate participation rate for the period
$participation_rate = 0;
if ($analytics['overall']['verified_voters'] > 0) {
    $participation_rate = ($analytics['overall']['period_votes'] / $analytics['overall']['verified_voters']) * 100;
}

// Prepare data for charts
$chart_data = [
    'election_status' => [],
    'voter_status' => [],
    'period_votes' => [],
    'period_registrations' => [],
    'top_candidates' => []
];

foreach ($analytics['election_distribution'] as $item) {
    $chart_data['election_status'][] = [
        'label' => ucfirst($item['status']),
        'count' => $item['count'],
        'percentage' => $item['percentage']
    ];
}

foreach ($analytics['voter_stats']['status_distribution'] as $item) {
    $chart_data['voter_status'][] = [
        'label' => ucfirst($item['status']),
        'count' => $item['count']
    ];
}

// Format voting trends data for the period
$period_votes = [];
foreach ($analytics['voting_trends'] as $trend) {
    $time_period = formatTimePeriod($trend['time_period'], $period);
    if (!isset($period_votes[$time_period])) {
        $period_votes[$time_period] = 0;
    }
    $period_votes[$time_period] += $trend['vote_count'];
}

foreach ($period_votes as $time_period => $count) {
    $chart_data['period_votes'][] = [
        'period' => $time_period,
        'votes' => $count
    ];
}

// Format registration trends data
$period_registrations = [];
foreach ($analytics['voter_stats']['period_registrations'] as $registration) {
    $time_period = formatTimePeriod($registration['time_period'], $period);
    $period_registrations[$time_period] = $registration['registrations'];
}

foreach ($period_registrations as $time_period => $count) {
    $chart_data['period_registrations'][] = [
        'period' => $time_period,
        'registrations' => $count
    ];
}

foreach ($analytics['top_candidates'] as $candidate) {
    $chart_data['top_candidates'][] = [
        'name' => $candidate['candidate_name'],
        'votes' => $candidate['vote_count']
    ];
}

// Helper function to format time period for display
function formatTimePeriod($time_period, $period_type)
{
    switch ($period_type) {
        case 'today':
            return date('g A', strtotime($time_period . ':00'));
        case 'week':
        case 'month':
            return date('M d', strtotime($time_period));
        case 'quarter':
        case 'year':
            return date('M Y', strtotime($time_period . '-01'));
        default:
            return $time_period;
    }
}

// Get period label for display
$period_labels = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'quarter' => 'This Quarter',
    'year' => 'This Year'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard | SecureVote Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .sidebar-menu a.active_4 {
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

        .stat-icon.voters {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.votes {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .stat-icon.participation {
            background-color: rgba(26, 188, 156, 0.1);
            color: var(--accent-color);
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

        .stat-change {
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

        .stat-period {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        /* Period Comparison Badge */
        .period-badge {
            background-color: var(--admin-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-left: 0.5rem;
        }

        /* Chart Cards */
        .chart-card {
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

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Recent Elections Table */
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

        /* Top Candidates */
        .candidate-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f1f1f1;
        }

        .candidate-item:last-child {
            border-bottom: none;
        }

        .candidate-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            overflow: hidden;
        }

        .candidate-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .candidate-info {
            flex: 1;
        }

        .candidate-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .candidate-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .vote-count {
            font-weight: 600;
            color: var(--admin-color);
        }

        /* Time Filter */
        .time-filter {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .time-filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 6px;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .time-filter-btn:hover,
        .time-filter-btn.active {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
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

            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
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

            .time-filter {
                flex-wrap: wrap;
            }

            .time-filter-btn {
                flex: 1;
                text-align: center;
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
                <h1>Analytics Dashboard</h1>
            </div>
            <div class="header-right">
                <span class="period-badge" id="currentPeriodBadge">
                    <?= $period_labels[$period] ?>
                </span>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Analytics Dashboard</h1>
                <p class="page-subtitle">Comprehensive statistics and insights about your voting system</p>
                <div class="time-filter">
                    <?php foreach ($period_labels as $key => $label): ?>
                        <a href="?period=<?= $key ?>" class="time-filter-btn <?= $period === $key ? 'active' : '' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row stats-cards">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon total">
                            <i class="fas fa-poll"></i>
                        </div>
                        <div class="stat-number">
                            <?= number_format($analytics['overall']['period_elections']) ?>
                        </div>
                        <div class="stat-label">Elections This Period</div>
                        <div
                            class="stat-change <?= $analytics['comparison']['elections']['change'] >= 0 ? 'change-up' : 'change-down' ?>">
                            <i
                                class="fas fa-arrow-<?= $analytics['comparison']['elections']['change'] >= 0 ? 'up' : 'down' ?>"></i>
                            <?= number_format(abs($analytics['comparison']['elections']['change']), 1) ?>% vs previous
                        </div>
                        <div class="stat-period">
                            Total: <?= number_format($analytics['overall']['total_elections']) ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon active">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="stat-number">
                            <?= number_format($analytics['overall']['active_elections']) ?>
                        </div>
                        <div class="stat-label">Active Elections</div>
                        <div class="stat-period">
                            Current active elections
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number">
                            <?= number_format($analytics['overall']['completed_elections']) ?>
                        </div>
                        <div class="stat-label">Completed This Period</div>
                        <div class="stat-period">
                            Elections completed in <?= $period_labels[$period] ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon voters">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number">
                            <?= number_format($analytics['overall']['period_voters']) ?>
                        </div>
                        <div class="stat-label">Voters Registered</div>
                        <div
                            class="stat-change <?= $analytics['comparison']['voters']['change'] >= 0 ? 'change-up' : 'change-down' ?>">
                            <i
                                class="fas fa-arrow-<?= $analytics['comparison']['voters']['change'] >= 0 ? 'up' : 'down' ?>"></i>
                            <?= number_format(abs($analytics['comparison']['voters']['change']), 1) ?>% vs previous
                        </div>
                        <div class="stat-period">
                            Total: <?= number_format($analytics['overall']['total_voters']) ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon votes">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-number">
                            <?= number_format($analytics['overall']['period_votes']) ?>
                        </div>
                        <div class="stat-label">Votes Cast</div>
                        <div
                            class="stat-change <?= $analytics['comparison']['votes']['change'] >= 0 ? 'change-up' : 'change-down' ?>">
                            <i
                                class="fas fa-arrow-<?= $analytics['comparison']['votes']['change'] >= 0 ? 'up' : 'down' ?>"></i>
                            <?= number_format(abs($analytics['comparison']['votes']['change']), 1) ?>% vs previous
                        </div>
                        <div class="stat-period">
                            Total: <?= number_format($analytics['overall']['total_votes']) ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon participation">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number">
                            <?= number_format($participation_rate, 1) ?>%
                        </div>
                        <div class="stat-label">Participation Rate</div>
                        <div class="stat-period">
                            Based on <?= $period_labels[$period] ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Voting Activity (<?= $period_labels[$period] ?>)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="votingActivityChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Voter Registrations (<?= $period_labels[$period] ?>)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="registrationsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Election Status Distribution</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="electionStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Top Performing Candidates (<?= $period_labels[$period] ?>)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="topCandidatesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Elections & Top Candidates -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="table-container">
                        <div class="table-header">
                            <h3>Recent Elections (<?= $period_labels[$period] ?>)</h3>
                            <a href="election_polls.php" class="btn btn-sm btn-purple">View All Elections</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Candidates</th>
                                        <th>Votes</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($analytics['recent_elections']) > 0): ?>
                                        <?php foreach ($analytics['recent_elections'] as $election): ?>
                                            <tr>
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
                                                </td>
                                                <td>
                                                    <?= $election['candidate_count'] ?>
                                                </td>
                                                <td>
                                                    <?= $election['vote_count'] ?>
                                                </td>
                                                <td>
                                                    <?= date('M d, Y', strtotime($election['created_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted">
                                                No elections found for <?= $period_labels[$period] ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Top Candidates (<?= $period_labels[$period] ?>)</h3>
                        </div>
                        <div class="candidates-list">
                            <?php if (count($analytics['top_candidates']) > 0): ?>
                                <?php foreach ($analytics['top_candidates'] as $candidate): ?>
                                    <div class="candidate-item">
                                        <div class="candidate-avatar">
                                            <?php if (!empty($candidate['profile_image'])): ?>
                                                <img src="<?= htmlspecialchars($candidate['profile_image']) ?>"
                                                    alt="<?= htmlspecialchars($candidate['candidate_name']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="candidate-info">
                                            <div class="candidate-name">
                                                <?= htmlspecialchars($candidate['candidate_name']) ?>
                                            </div>
                                            <div class="candidate-meta">
                                                <?= htmlspecialchars($candidate['party_affiliation'] ?? 'Independent') ?> •
                                                <?= htmlspecialchars($candidate['election_title']) ?>
                                            </div>
                                        </div>
                                        <div class="vote-count">
                                            <?= $candidate['vote_count'] ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-3 text-muted">
                                    No voting activity for <?= $period_labels[$period] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="row">
                <div class="col-12">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Detailed Statistics (<?= $period_labels[$period] ?>)</h3>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <h5>Election Statistics</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Total Elections</span>
                                        <strong>
                                            <?= $analytics['overall']['total_elections'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Active Elections</span>
                                        <strong>
                                            <?= $analytics['overall']['active_elections'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Completed This Period</span>
                                        <strong>
                                            <?= $analytics['overall']['completed_elections'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>New This Period</span>
                                        <strong>
                                            <?= $analytics['overall']['period_elections'] ?>
                                        </strong>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h5>Voter Statistics</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Total Voters</span>
                                        <strong>
                                            <?= $analytics['overall']['total_voters'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Verified Voters</span>
                                        <strong>
                                            <?= $analytics['overall']['verified_voters'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>New Registrations</span>
                                        <strong>
                                            <?= $analytics['overall']['period_voters'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Participation Rate</span>
                                        <strong>
                                            <?= number_format($participation_rate, 1) ?>%
                                        </strong>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h5>Voting Statistics</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Votes This Period</span>
                                        <strong>
                                            <?= $analytics['overall']['period_votes'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Total Votes</span>
                                        <strong>
                                            <?= $analytics['overall']['total_votes'] ?>
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Growth vs Previous</span>
                                        <strong
                                            class="<?= $analytics['comparison']['votes']['change'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $analytics['comparison']['votes']['change'] >= 0 ? '+' : '' ?>
                                            <?= number_format($analytics['comparison']['votes']['change'], 1) ?>%
                                        </strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Avg Votes/Day</span>
                                        <strong>
                                            <?php
                                            $days_in_period = getDaysInPeriod($period);
                                            $avg_votes = $days_in_period > 0 ? $analytics['overall']['period_votes'] / $days_in_period : 0;
                                            echo number_format($avg_votes, 1);
                                            ?>
                                        </strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
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
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize charts
            initCharts();

            // Initialize sidebar toggle
            initSidebarToggle();
        });

        function initCharts() {
            // Voting Activity Chart (Line)
            const votingActivityCtx = document.getElementById('votingActivityChart').getContext('2d');
            const votingActivityChart = new Chart(votingActivityCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($chart_data['period_votes'], 'period')) ?>,
                    datasets: [{
                        label: 'Votes Cast',
                        data: <?= json_encode(array_column($chart_data['period_votes'], 'votes')) ?>,
                        borderColor: 'rgb(155, 89, 182)',
                        backgroundColor: 'rgba(155, 89, 182, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Registrations Chart (Bar)
            const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
            const registrationsChart = new Chart(registrationsCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($chart_data['period_registrations'], 'period')) ?>,
                    datasets: [{
                        label: 'New Voters',
                        data: <?= json_encode(array_column($chart_data['period_registrations'], 'registrations')) ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.8)',
                        borderColor: 'rgb(52, 152, 219)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Election Status Chart (Doughnut)
            const electionStatusCtx = document.getElementById('electionStatusChart').getContext('2d');
            const electionStatusChart = new Chart(electionStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($chart_data['election_status'], 'label')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($chart_data['election_status'], 'count')) ?>,
                        backgroundColor: [
                            'rgba(108, 117, 125, 0.8)',    // Draft - gray
                            'rgba(241, 196, 15, 0.8)',     // Upcoming - yellow
                            'rgba(46, 204, 113, 0.8)',     // Active - green
                            'rgba(155, 89, 182, 0.8)',     // Completed - purple
                            'rgba(231, 76, 60, 0.8)'       // Cancelled - red
                        ],
                        borderColor: [
                            'rgb(108, 117, 125)',
                            'rgb(241, 196, 15)',
                            'rgb(46, 204, 113)',
                            'rgb(155, 89, 182)',
                            'rgb(231, 76, 60)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
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

            // Top Candidates Chart (Horizontal Bar)
            const topCandidatesCtx = document.getElementById('topCandidatesChart').getContext('2d');
            const topCandidatesChart = new Chart(topCandidatesCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($chart_data['top_candidates'], 'name')) ?>,
                    datasets: [{
                        label: 'Votes',
                        data: <?= json_encode(array_column($chart_data['top_candidates'], 'votes')) ?>,
                        backgroundColor: 'rgba(155, 89, 182, 0.8)',
                        borderColor: 'rgb(155, 89, 182)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

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
    </script>
</body>

</html>

<?php
// Helper function to get number of days in period
function getDaysInPeriod($period)
{
    switch ($period) {
        case 'today':
            return 1;
        case 'week':
            return 7;
        case 'month':
            return date('t'); // Days in current month
        case 'quarter':
            return 90; // Approximate
        case 'year':
            return date('L') ? 366 : 365; // Leap year check
        default:
            return 30;
    }
}
?>