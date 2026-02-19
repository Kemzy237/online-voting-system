<?php
session_name('voter');
session_start();

// Check authentication
if (!isset($_SESSION['id']) && !isset($_SESSION['email'])) {
    $message = "Please login first";
    $status = "error";
    header("Location: index.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";

$voter_id = $_SESSION['id'];

// Get voter information
try {
    $sql = "SELECT full_name, email, status, dob, contact, address, created_at 
            FROM voters WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $voter_id]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voter) {
        session_destroy();
        header("Location: index.php?message=Voter not found&status=error");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching voter: " . $e->getMessage());
    header("Location: index.php?message=Database error&status=error");
    exit();
}

/**
 * Get voting history with filters using PDO
 */
function get_voting_history($conn, $voter_id, $filters = [])
{
    try {
        $sql = "SELECT 
                    v.id as vote_id,
                    v.vote_timestamp,
                    v.status as vote_status,
                    v.verified_at,
                    e.id as election_id,
                    e.title as election_title,
                    e.description as election_description,
                    e.start_datetime,
                    e.end_datetime,
                    e.status as election_status,
                    c.id as candidate_id,
                    c.voter_id as candidate_voter_id,
                    c.party_affiliation,
                    c.biography,
                    c.campaign_statement,
                    c.profile_image,
                    c.status as candidate_status,
                    vr.full_name as candidate_name,
                    vr.email as candidate_email
                FROM votes v
                INNER JOIN elections e ON v.election_id = e.id
                INNER JOIN candidates c ON v.candidate_id = c.id
                INNER JOIN voters vr ON c.voter_id = vr.id
                WHERE v.voter_id = :voter_id";

        $params = [':voter_id' => $voter_id];

        // Apply filters
        if (!empty($filters['election_id'])) {
            $sql .= " AND v.election_id = :election_id";
            $params[':election_id'] = $filters['election_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND v.status = :vote_status";
            $params[':vote_status'] = $filters['status'];
        }

        if (!empty($filters['year'])) {
            $sql .= " AND YEAR(v.vote_timestamp) = :year";
            $params[':year'] = $filters['year'];
        }

        if (!empty($filters['month'])) {
            $sql .= " AND MONTH(v.vote_timestamp) = :month";
            $params[':month'] = $filters['month'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (e.title LIKE :search OR vr.full_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Order by
        $order_by = 'v.vote_timestamp';
        $order_dir = 'DESC';

        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'oldest':
                    $order_dir = 'ASC';
                    break;
                case 'election_asc':
                    $order_by = 'e.title';
                    $order_dir = 'ASC';
                    break;
                case 'election_desc':
                    $order_by = 'e.title';
                    $order_dir = 'DESC';
                    break;
                case 'candidate_asc':
                    $order_by = 'vr.full_name';
                    $order_dir = 'ASC';
                    break;
                case 'candidate_desc':
                    $order_by = 'vr.full_name';
                    $order_dir = 'DESC';
                    break;
            }
        }

        $sql .= " ORDER BY $order_by $order_dir";

        $stmt = $conn->prepare($sql);

        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error getting voting history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get voting statistics using PDO
 */
function get_voting_statistics($conn, $voter_id)
{
    try {
        $stats = [];

        // Total votes
        $sql = "SELECT COUNT(*) as total_votes FROM votes WHERE voter_id = :voter_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $stats['total_votes'] = $stmt->fetchColumn();

        // Votes by status
        $sql = "SELECT status, COUNT(*) as count 
                FROM votes 
                WHERE voter_id = :voter_id 
                GROUP BY status";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['status_counts'] = [];
        foreach ($status_counts as $row) {
            $stats['status_counts'][$row['status']] = $row['count'];
        }

        // Unique elections voted in
        $sql = "SELECT COUNT(DISTINCT election_id) as unique_elections 
                FROM votes WHERE voter_id = :voter_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $stats['unique_elections'] = $stmt->fetchColumn();

        // First and last vote
        $sql = "SELECT MIN(vote_timestamp) as first_vote, 
                       MAX(vote_timestamp) as last_vote 
                FROM votes WHERE voter_id = :voter_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $timestamps = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['first_vote'] = $timestamps['first_vote'];
        $stats['last_vote'] = $timestamps['last_vote'];

        // Votes by year
        $sql = "SELECT YEAR(vote_timestamp) as year, COUNT(*) as count 
                FROM votes WHERE voter_id = :voter_id 
                GROUP BY YEAR(vote_timestamp) 
                ORDER BY year DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $stats['votes_by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Most voted candidate
        $sql = "SELECT 
                    vr.full_name, 
                    COUNT(*) as vote_count,
                    c.party_affiliation
                FROM votes v 
                JOIN candidates c ON v.candidate_id = c.id 
                JOIN voters vr ON c.voter_id = vr.id
                WHERE v.voter_id = :voter_id 
                GROUP BY v.candidate_id, vr.full_name, c.party_affiliation
                ORDER BY vote_count DESC 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        $stats['most_voted_candidate'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;

    } catch (PDOException $e) {
        error_log("Error getting voting statistics: " . $e->getMessage());
        return [
            'total_votes' => 0,
            'unique_elections' => 0,
            'status_counts' => [],
            'votes_by_year' => [],
            'most_voted_candidate' => null
        ];
    }
}

/**
 * Get available voting years using PDO
 */
function get_voting_years($conn, $voter_id)
{
    try {
        $sql = "SELECT DISTINCT YEAR(vote_timestamp) as year 
                FROM votes WHERE voter_id = :voter_id 
                ORDER BY year DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting voting years: " . $e->getMessage());
        return [];
    }
}

/**
 * Get elections voter participated in using PDO
 */
function get_voter_elections($conn, $voter_id)
{
    try {
        $sql = "SELECT DISTINCT 
                    e.id, 
                    e.title 
                FROM votes v 
                JOIN elections e ON v.election_id = e.id 
                WHERE v.voter_id = :voter_id 
                ORDER BY e.title";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting voter elections: " . $e->getMessage());
        return [];
    }
}

/**
 * Get single vote details by ID using PDO
 */
function get_vote_details($conn, $vote_id, $voter_id)
{
    try {
        $sql = "SELECT 
                    v.*,
                    e.title as election_title,
                    e.description as election_description,
                    e.start_datetime,
                    e.end_datetime,
                    e.status as election_status,
                    vr.full_name as candidate_name,
                    vr.email as candidate_email,
                    c.party_affiliation,
                    c.biography,
                    c.campaign_statement,
                    c.status as candidate_status,
                    v2.full_name as voter_name,
                    v2.email as voter_email
                FROM votes v
                JOIN elections e ON v.election_id = e.id
                JOIN candidates c ON v.candidate_id = c.id
                JOIN voters vr ON c.voter_id = vr.id
                JOIN voters v2 ON v.voter_id = v2.id
                WHERE v.id = :vote_id AND v.voter_id = :voter_id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':vote_id' => $vote_id,
            ':voter_id' => $voter_id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error getting vote details: " . $e->getMessage());
        return null;
    }
}

/**
 * Export voting history as CSV using PDO
 */
function export_history_csv($conn, $voter_id)
{
    try {
        $sql = "SELECT 
                    v.id as vote_id,
                    v.vote_timestamp,
                    v.status as vote_status,
                    v.verified_at,
                    e.title as election_title,
                    e.start_datetime,
                    e.end_datetime,
                    e.status as election_status,
                    vr.full_name as candidate_name,
                    vr.email as candidate_email,
                    c.party_affiliation
                FROM votes v
                JOIN elections e ON v.election_id = e.id
                JOIN candidates c ON v.candidate_id = c.id
                JOIN voters vr ON c.voter_id = vr.id
                WHERE v.voter_id = :voter_id
                ORDER BY v.vote_timestamp DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':voter_id' => $voter_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error exporting CSV: " . $e->getMessage());
        return [];
    }
}

// Get admin contact info using PDO
try {
    $sql = "SELECT contact, email, location FROM admin LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        $admin = [
            'contact' => '+237 653 426 838',
            'email' => 'support@votesecure.com',
            'location' => 'Douala, Cameroon'
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching admin: " . $e->getMessage());
    $admin = [
        'contact' => '+237 653 426 838',
        'email' => 'support@votesecure.com',
        'location' => 'Douala, Cameroon'
    ];
}

// Process CSV export if requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_data = export_history_csv($conn, $voter_id);

    if (!empty($export_data)) {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="voting_history_' . date('Y-m-d') . '.csv"');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Add headers
        fputcsv($output, [
            'Vote ID',
            'Vote Date',
            'Vote Time',
            'Vote Status',
            'Verified Date',
            'Election',
            'Election Period',
            'Election Status',
            'Candidate',
            'Party Affiliation'
        ]);

        // Add data rows
        foreach ($export_data as $row) {
            fputcsv($output, [
                'VOTE-' . str_pad($row['vote_id'], 6, '0', STR_PAD_LEFT),
                date('Y-m-d', strtotime($row['vote_timestamp'])),
                date('H:i:s', strtotime($row['vote_timestamp'])),
                ucfirst($row['vote_status']),
                $row['verified_at'] ? date('Y-m-d H:i:s', strtotime($row['verified_at'])) : 'N/A',
                $row['election_title'],
                date('Y-m-d', strtotime($row['start_datetime'])) . ' to ' . date('Y-m-d', strtotime($row['end_datetime'])),
                ucfirst($row['election_status']),
                $row['candidate_name'],
                $row['party_affiliation'] ?? 'Independent'
            ]);
        }

        fclose($output);
        exit();
    }
}

// Get filter parameters
$filters = [];

if (isset($_GET['election_id']) && !empty($_GET['election_id'])) {
    $filters['election_id'] = (int) $_GET['election_id'];
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['year']) && !empty($_GET['year'])) {
    $filters['year'] = (int) $_GET['year'];
}
if (isset($_GET['month']) && !empty($_GET['month'])) {
    $filters['month'] = (int) $_GET['month'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = trim($_GET['search']);
}
if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    $filters['sort'] = $_GET['sort'];
}

// Get voting history with filters
$voting_history = get_voting_history($conn, $voter_id, $filters);
$total_history_count = count($voting_history);

// Get statistics
$statistics = get_voting_statistics($conn, $voter_id);

// Get available filters
$available_years = get_voting_years($conn, $voter_id);
$voter_elections = get_voter_elections($conn, $voter_id);

// Calculate derived statistics
$current_year = date('Y');
$votes_this_year = 0;
$votes_last_year = 0;

foreach ($statistics['votes_by_year'] ?? [] as $year_data) {
    if ($year_data['year'] == $current_year) {
        $votes_this_year = $year_data['count'];
    } elseif ($year_data['year'] == $current_year - 1) {
        $votes_last_year = $year_data['count'];
    }
}

// Months array for filter
$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

// Get voter initials for avatar
$voter_initials = strtoupper(substr($voter['full_name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting History | SecureVote</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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

        /* Page Content */
        .history-content {
            padding: 2rem;
            max-width: 1600px;
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
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
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .stat-card.total-votes::before {
            background-color: var(--voter-color);
        }

        .stat-card.unique-elections::before {
            background-color: var(--success-color);
        }

        .stat-card.this-year::before {
            background-color: var(--warning-color);
        }

        .stat-card.last-year::before {
            background-color: var(--accent-color);
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

        .stat-icon.total-votes {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
        }

        .stat-icon.unique-elections {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.this-year {
            background-color: rgba(241, 196, 15, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.last-year {
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

        .stat-trend {
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .trend-up {
            color: var(--success-color);
        }

        .trend-down {
            color: var(--danger-color);
        }

        /* Filter Section */
        .filter-section {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .filter-header h2 i {
            margin-right: 0.75rem;
            color: var(--voter-color);
        }

        .filter-toggle {
            background: none;
            border: none;
            color: var(--voter-color);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        /* History Table Section */
        .history-table-section {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .table-header h2 i {
            margin-right: 0.75rem;
            color: var(--voter-color);
        }

        .history-count {
            background-color: var(--voter-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .table-responsive {
            padding: 0 2rem 2rem;
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
            border-top: 1px solid #eee;
        }

        table.dataTable tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #eee;
        }

        table.dataTable tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
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

        .status-invalid {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .status-rejected {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Election Status */
        .election-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .election-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .election-completed {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .election-upcoming {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
        }

        .election-cancelled {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .election-draft {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Candidate Info */
        .candidate-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .candidate-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }

        .candidate-details {
            flex: 1;
            min-width: 0;
        }

        .candidate-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .candidate-party {
            font-size: 0.8rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Timeline View */
        .timeline-view {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .timeline-view h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .timeline-view h2 i {
            margin-right: 0.75rem;
            color: var(--voter-color);
        }

        .timeline-item {
            position: relative;
            padding-left: 2.5rem;
            margin-bottom: 2rem;
            border-left: 2px solid #e9ecef;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -7px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--voter-color);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--voter-color);
        }

        .timeline-date {
            font-size: 0.9rem;
            color: var(--voter-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }

        .timeline-content h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        /* Export Section */
        .export-section {
            background-color: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .export-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .export-options {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #6c757d;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 1.5rem;
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
            color: white;
        }

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #dee2e6;
        }

        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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
            color: var(--voter-color);
        }

        .btn-view:hover {
            background-color: var(--voter-color);
            color: white;
        }

        .btn-receipt {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .btn-receipt:hover {
            background-color: var(--success-color);
            color: white;
        }

        /* View Toggle */
        .view-toggle {
            display: flex;
            gap: 0.5rem;
            background-color: #f8f9fa;
            padding: 0.25rem;
            border-radius: 8px;
        }

        .view-toggle-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            color: #6c757d;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-toggle-btn.active {
            background-color: white;
            color: var(--voter-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: white;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .history-content {
                padding: 1.5rem;
            }

            .filter-body {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .table-responsive {
                padding: 0 1.5rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .history-content {
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .btn {
                width: 100%;
            }

            .export-options {
                flex-direction: column;
            }

            .export-options .btn {
                width: 100%;
            }

            .voter-info {
                display: none;
            }
        }

        @media (max-width: 576px) {
            #main-header {
                padding: 0 1rem;
            }

            .candidate-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .timeline-item {
                padding-left: 1.5rem;
            }
        }

        /* Print Styles */
        @media print {
            #main-header,
            .filter-section,
            .export-section,
            .footer,
            .view-toggle,
            .action-btns,
            .no-print {
                display: none !important;
            }
            
            body {
                background-color: white !important;
            }
            
            .history-content {
                padding: 0 !important;
            }
            
            .page-header {
                background: var(--primary-color) !important;
                color: black !important;
                box-shadow: none !important;
            }
            
            .history-table-section {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header id="main-header">
        <div class="header-left">
            <a class="navbar-brand" href="voter_dashboard.php">
                <i class="fas fa-vote-yea text-primary me-2"></i>Secure<span>Vote</span>
            </a>
        </div>
        <div class="header-right">
            <div class="voter-profile dropdown">
                <div class="d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="voter-avatar">
                        <?= htmlspecialchars($voter_initials) ?>
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

    <!-- History Content -->
    <main class="history-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Voting History</h1>
            <p>Track all your voting activity and election participation</p>
        </div>

        <!-- Stats Cards -->
        <?php if ($statistics['total_votes'] > 0): ?>
            <div class="stats-grid">
                <div class="stat-card total-votes">
                    <div class="stat-icon total-votes">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="stat-number"><?= number_format($statistics['total_votes']) ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                    <?php if (!empty($statistics['most_voted_candidate']['full_name'])): ?>
                            <div class="stat-trend">
                                <i class="fas fa-user-check"></i>
                                Most voted: <?= htmlspecialchars($statistics['most_voted_candidate']['full_name']) ?>
                            </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card unique-elections">
                    <div class="stat-icon unique-elections">
                        <i class="fas fa-poll-h"></i>
                    </div>
                    <div class="stat-number"><?= number_format($statistics['unique_elections']) ?></div>
                    <div class="stat-label">Unique Elections</div>
                    <?php if (!empty($statistics['first_vote'])): ?>
                            <div class="stat-trend">
                                <i class="fas fa-calendar-alt"></i>
                                First vote: <?= date('M Y', strtotime($statistics['first_vote'])) ?>
                            </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card this-year">
                    <div class="stat-icon this-year">
                        <i class="fas fa-calendar-star"></i>
                    </div>
                    <div class="stat-number"><?= number_format($votes_this_year) ?></div>
                    <div class="stat-label">Votes This Year</div>
                    <?php if ($votes_last_year > 0): ?>
                            <?php $trend = (($votes_this_year - $votes_last_year) / $votes_last_year) * 100; ?>
                            <div class="stat-trend <?= $trend >= 0 ? 'trend-up' : 'trend-down' ?>">
                                <i class="fas fa-<?= $trend >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs(round($trend, 1)) ?>% from last year
                            </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card last-year">
                    <div class="stat-icon last-year">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number"><?= number_format($votes_last_year) ?></div>
                    <div class="stat-label">Votes Last Year</div>
                    <?php if (!empty($statistics['last_vote'])): ?>
                            <div class="stat-trend">
                                <i class="fas fa-clock"></i>
                                Last vote: <?= date('d M Y', strtotime($statistics['last_vote'])) ?>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h2><i class="fas fa-filter"></i> Filter Voting History</h2>
                <button class="filter-toggle" id="filterToggle" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <span>Show Filters</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="filterCollapse">
                <form method="GET" action="" id="filterForm">
                    <div class="filter-body">
                        <!-- Search -->
                        <div class="filter-group">
                            <label for="search"><i class="fas fa-search me-2"></i>Search</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>" 
                                       placeholder="Search elections or candidates...">
                            </div>
                        </div>

                        <!-- Election Filter -->
                        <div class="filter-group">
                            <label for="election_id"><i class="fas fa-poll-h me-2"></i>Election</label>
                            <select class="form-select" id="election_id" name="election_id">
                                <option value="">All Elections</option>
                                <?php foreach ($voter_elections as $election): ?>
                                        <option value="<?= $election['id'] ?>" 
                                                <?= isset($filters['election_id']) && $filters['election_id'] == $election['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($election['title']) ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label for="status"><i class="fas fa-check-circle me-2"></i>Vote Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="verified" <?= isset($filters['status']) && $filters['status'] == 'verified' ? 'selected' : '' ?>>Verified</option>
                                <option value="pending" <?= isset($filters['status']) && $filters['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="invalid" <?= isset($filters['status']) && $filters['status'] == 'invalid' ? 'selected' : '' ?>>Invalid</option>
                                <option value="rejected" <?= isset($filters['status']) && $filters['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>

                        <!-- Year Filter -->
                        <div class="filter-group">
                            <label for="year"><i class="fas fa-calendar me-2"></i>Year</label>
                            <select class="form-select" id="year" name="year">
                                <option value="">All Years</option>
                                <?php foreach ($available_years as $year): ?>
                                        <option value="<?= $year ?>" 
                                                <?= isset($filters['year']) && $filters['year'] == $year ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Month Filter -->
                        <div class="filter-group">
                            <label for="month"><i class="fas fa-calendar-alt me-2"></i>Month</label>
                            <select class="form-select" id="month" name="month">
                                <option value="">All Months</option>
                                <?php foreach ($months as $num => $name): ?>
                                        <option value="<?= $num ?>" 
                                                <?= isset($filters['month']) && $filters['month'] == $num ? 'selected' : '' ?>>
                                            <?= $name ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sort Filter -->
                        <div class="filter-group">
                            <label for="sort"><i class="fas fa-sort me-2"></i>Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="">Newest First</option>
                                <option value="oldest" <?= isset($filters['sort']) && $filters['sort'] == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="election_asc" <?= isset($filters['sort']) && $filters['sort'] == 'election_asc' ? 'selected' : '' ?>>Election (A-Z)</option>
                                <option value="election_desc" <?= isset($filters['sort']) && $filters['sort'] == 'election_desc' ? 'selected' : '' ?>>Election (Z-A)</option>
                                <option value="candidate_asc" <?= isset($filters['sort']) && $filters['sort'] == 'candidate_asc' ? 'selected' : '' ?>>Candidate (A-Z)</option>
                                <option value="candidate_desc" <?= isset($filters['sort']) && $filters['sort'] == 'candidate_desc' ? 'selected' : '' ?>>Candidate (Z-A)</option>
                            </select>
                        </div>

                        <!-- Filter Actions -->
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="voting_history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i> Clear All
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Toggle -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Your Voting Records</h3>
            <?php if (!empty($voting_history)): ?>
                <div class="view-toggle">
                    <button class="view-toggle-btn active" data-view="table">
                        <i class="fas fa-table me-2"></i>Table View
                    </button>
                    <button class="view-toggle-btn" data-view="timeline">
                        <i class="fas fa-stream me-2"></i>Timeline View
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Table View -->
        <div class="history-table-section view-content" id="tableView">
            <div class="table-header">
                <h2><i class="fas fa-history me-2"></i>Voting Records</h2>
                <div class="history-count"><?= $total_history_count ?> Record<?= $total_history_count != 1 ? 's' : '' ?> Found</div>
            </div>

            <?php if (!empty($voting_history)): ?>
                    <div class="table-responsive">
                        <table id="votingHistoryTable" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Election</th>
                                    <th>Candidate</th>
                                    <th>Voted On</th>
                                    <th>Vote Status</th>
                                    <th>Election Status</th>
                                    <th width="100">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voting_history as $index => $vote):
                                    $candidate_initials = strtoupper(substr($vote['candidate_name'], 0, 2));
                                    $vote_date = date('d M Y', strtotime($vote['vote_timestamp']));
                                    $vote_time = date('h:i A', strtotime($vote['vote_timestamp']));
                                    $election_end_date = date('d M Y', strtotime($vote['end_datetime']));
                                    ?>
                                        <tr data-vote-id="<?= $vote['vote_id'] ?>">
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong><?= htmlspecialchars($vote['election_title']) ?></strong>
                                                    <small class="text-muted">Ended: <?= $election_end_date ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="candidate-info">
                                                    <div class="candidate-avatar">
                                                        <?= $candidate_initials ?>
                                                    </div>
                                                    <div class="candidate-details">
                                                        <div class="candidate-name"><?= htmlspecialchars($vote['candidate_name']) ?></div>
                                                        <?php if (!empty($vote['party_affiliation'])): ?>
                                                                <div class="candidate-party"><?= htmlspecialchars($vote['party_affiliation']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong><?= $vote_date ?></strong>
                                                    <small class="text-muted"><?= $vote_time ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($vote['vote_status']) {
                                                    case 'verified':
                                                        $status_class = 'status-verified';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'status-pending';
                                                        break;
                                                    case 'invalid':
                                                        $status_class = 'status-invalid';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'status-rejected';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= ucfirst($vote['vote_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $election_status_class = '';
                                                switch ($vote['election_status']) {
                                                    case 'completed':
                                                        $election_status_class = 'election-completed';
                                                        break;
                                                    case 'active':
                                                        $election_status_class = 'election-active';
                                                        break;
                                                    case 'upcoming':
                                                        $election_status_class = 'election-upcoming';
                                                        break;
                                                    case 'cancelled':
                                                        $election_status_class = 'election-cancelled';
                                                        break;
                                                    case 'draft':
                                                        $election_status_class = 'election-draft';
                                                        break;
                                                }
                                                ?>
                                                <span class="election-status <?= $election_status_class ?>">
                                                    <?= ucfirst($vote['election_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="btn-action btn-view" title="View Details" 
                                                            onclick="showVoteDetails(<?= $vote['vote_id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <!-- <a href="vote_receipt.php?vote_id=<?= $vote['vote_id'] ?>" 
                                                       class="btn-action btn-receipt" 
                                                       title="View Receipt">
                                                        <i class="fas fa-receipt"></i>
                                                    </a> -->
                                                </div>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
            <?php else: ?>
                    <div class="empty-state py-5">
                        <i class="fas fa-history fa-4x"></i>
                        <h3>No Voting Records Found</h3>
                        <p>You haven't participated in any elections yet, or no records match your filters.</p>
                        <a href="voter_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-poll-h me-2"></i>View Active Elections
                        </a>
                    </div>
            <?php endif; ?>
        </div>

        <!-- Timeline View (Hidden by default) -->
        <div class="timeline-view view-content d-none" id="timelineView">
            <h2><i class="fas fa-stream me-2"></i>Voting Timeline</h2>
            
            <?php if (!empty($voting_history)):
                // Group votes by year
                $votes_by_year = [];
                foreach ($voting_history as $vote) {
                    $year = date('Y', strtotime($vote['vote_timestamp']));
                    $votes_by_year[$year][] = $vote;
                }
                krsort($votes_by_year); // Sort years in descending order
                ?>
                    <?php foreach ($votes_by_year as $year => $year_votes): ?>
                            <h3 class="mb-4" style="color: var(--voter-color);"><?= $year ?></h3>
                            <?php foreach ($year_votes as $vote):
                                $vote_date = date('d F Y', strtotime($vote['vote_timestamp']));
                                $vote_time = date('h:i A', strtotime($vote['vote_timestamp']));
                                ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?= $vote_date ?>
                                        </div>
                                        <div class="timeline-content">
                                            <h4><?= htmlspecialchars($vote['election_title']) ?></h4>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong>Candidate:</strong> <?= htmlspecialchars($vote['candidate_name']) ?>
                                                    <?php if (!empty($vote['party_affiliation'])): ?>
                                                            <span class="text-muted">(<?= htmlspecialchars($vote['party_affiliation']) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php
                                                $status_class = '';
                                                switch ($vote['vote_status']) {
                                                    case 'verified':
                                                        $status_class = 'status-verified';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'status-pending';
                                                        break;
                                                    case 'invalid':
                                                        $status_class = 'status-invalid';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'status-rejected';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= ucfirst($vote['vote_status']) ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between text-muted">
                                                <small><i class="far fa-clock me-1"></i> <?= $vote_time ?></small>
                                                <small>Election: <?= ucfirst($vote['election_status']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                            <?php endforeach; ?>
                    <?php endforeach; ?>
            <?php else: ?>
                    <div class="empty-state py-5">
                        <i class="fas fa-history fa-4x"></i>
                        <h3>No Voting Timeline Available</h3>
                        <p>Switch to table view or clear your filters to see your voting history.</p>
                    </div>
            <?php endif; ?>
        </div>

        <!-- Export Section -->
        <?php if (!empty($voting_history)): ?>
                <div class="export-section">
                    <h3><i class="fas fa-download me-2"></i>Export History</h3>
                    <div class="export-options">
                        <a href="voting_history.php?export=csv&<?= http_build_query($filters) ?>" class="btn btn-outline-primary">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </a>
                        <button class="btn btn-outline-primary" onclick="printHistory()">
                            <i class="fas fa-print me-2"></i>Print History
                        </button>
                    </div>
                </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
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
                        <li><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($admin['email']) ?></li>
                        <li><i class="fas fa-phone me-2"></i><?= htmlspecialchars($admin['contact']) ?></li>
                        <li><i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($admin['location']) ?></li>
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

    <!-- Vote Details Modal -->
    <div class="modal fade" id="voteDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Vote Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="voteDetailsContent">
                    <!-- Details will be loaded here -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading vote details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printReceiptBtn" style="display: none;">
                        <i class="fas fa-print me-2"></i>Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable if we have records
            <?php if (!empty($voting_history)): ?>
                $('#votingHistoryTable').DataTable({
                    paging: true,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    searching: false,
                    ordering: true,
                    info: true,
                    responsive: true,
                    language: {
                        emptyTable: "No voting records found",
                        info: "Showing _START_ to _END_ of _TOTAL_ votes",
                        infoEmpty: "Showing 0 to 0 of 0 votes",
                        infoFiltered: "(filtered from _MAX_ total votes)",
                        lengthMenu: "Show _MENU_ votes per page",
                        loadingRecords: "Loading...",
                        zeroRecords: "No matching votes found",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            <?php endif; ?>

            // View toggle functionality
            $('.view-toggle-btn').on('click', function() {
                const view = $(this).data('view');
                
                $('.view-toggle-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.view-content').addClass('d-none');
                $('#' + view + 'View').removeClass('d-none');
            });

            // Filter toggle
            $('#filterToggle').on('click', function() {
                const icon = $(this).find('i');
                const text = $(this).find('span');
                
                if (icon.hasClass('fa-chevron-down')) {
                    icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    text.text('Hide Filters');
                } else {
                    icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    text.text('Show Filters');
                }
            });

            // Auto-collapse filter if no filters are active
            <?php if (empty($filters)): ?>
                $('#filterCollapse').removeClass('show');
                $('#filterToggle span').text('Show Filters');
                $('#filterToggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
            <?php endif; ?>

            // Filter form validation
            $('#filterForm').on('submit', function(e) {
                const year = $('#year').val();
                const month = $('#month').val();
                
                if (month && !year) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Year Required',
                        text: 'Please select a year when filtering by month.',
                        icon: 'warning',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });

        // Show vote details via AJAX
        function showVoteDetails(voteId) {
            const modal = new bootstrap.Modal(document.getElementById('voteDetailsModal'));
            
            // Show loading state
            document.getElementById('voteDetailsContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading vote details...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch vote details via AJAX
            $.ajax({
                url: 'get_vote_details.php',
                method: 'GET',
                data: { vote_id: voteId },
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (data.success) {
                            const v = data.vote;
                            const receiptNumber = 'VOTE-' + String(v.vote_id).padStart(6, '0');
                            const transactionId = 'TX-' + v.vote_timestamp.split(' ')[0].replace(/-/g, '') + '-' + 
                                                Math.random().toString(36).substring(2, 8).toUpperCase();
                            
                            const detailsHtml = `
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">VOTE INFORMATION</h6>
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Vote ID</label>
                                            <p class="fw-bold">${receiptNumber}</p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Vote Timestamp</label>
                                            <p class="fw-bold">${new Date(v.vote_timestamp).toLocaleString()}</p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Vote Status</label>
                                            <p><span class="status-badge status-${v.vote_status}">${ucfirst(v.vote_status)}</span></p>
                                        </div>
                                        ${v.verified_at ? `
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Verified At</label>
                                            <p class="fw-bold">${new Date(v.verified_at).toLocaleString()}</p>
                                        </div>
                                        ` : ''}
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-2">ELECTION DETAILS</h6>
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Election Title</label>
                                            <p class="fw-bold">${escapeHtml(v.election_title)}</p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Election Period</label>
                                            <p class="fw-bold">${new Date(v.start_datetime).toLocaleDateString()} - ${new Date(v.end_datetime).toLocaleDateString()}</p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Election Status</label>
                                            <p><span class="election-status election-${v.election_status}">${ucfirst(v.election_status)}</span></p>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="text-muted mb-3">CANDIDATE DETAILS</h6>
                                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                            <div class="candidate-avatar" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                                ${getInitials(v.candidate_name)}
                                            </div>
                                            <div>
                                                <h5 class="mb-1">${escapeHtml(v.candidate_name)}</h5>
                                                <p class="text-muted mb-1">${v.party_affiliation ? escapeHtml(v.party_affiliation) : 'Independent'}</p>
                                                ${v.campaign_statement ? `<p class="mb-0">"${escapeHtml(v.campaign_statement)}"</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    This vote has been ${v.vote_status === 'verified' ? 'verified and recorded' : 'received and is pending verification'}. 
                                    Transaction ID: <code>${transactionId}</code>
                                </div>
                            `;
                            
                            document.getElementById('voteDetailsContent').innerHTML = detailsHtml;
                            
                            // Set print button action
                            document.getElementById('printReceiptBtn').onclick = function() {
                                window.location.href = 'vote_receipt.php?vote_id=' + v.vote_id;
                            };
                        } else {
                            throw new Error(data.message || 'Failed to load vote details');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        document.getElementById('voteDetailsContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error loading vote details. Please try again.
                            </div>
                        `;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    document.getElementById('voteDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load vote details. Please try again.
                        </div>
                    `;
                }
            });
        }

        // Helper functions
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function getInitials(name) {
            return name.split(' ').map(n => n.charAt(0)).join('').substring(0, 2).toUpperCase();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Print history
        function printHistory() {
            window.print();
        }

        // Check for URL parameters on load
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                Swal.fire({
                    title: ucfirst(status),
                    text: decodeURIComponent(message),
                    icon: status,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6',
                    showCloseButton: true
                });

                // Clean URL
                const url = new URL(window.location);
                url.searchParams.delete('status');
                url.searchParams.delete('message');
                window.history.replaceState({}, document.title, url);
            }
        });
    </script>
</body>

</html>