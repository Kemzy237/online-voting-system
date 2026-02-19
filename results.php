<?php
session_name('voter');
session_start();

// Check if user is logged in
if (!isset($_SESSION['id']) && !isset($_SESSION['email'])) {
    $message = "Please login first";
    $status = "error";
    header("Location: index.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";
include "app/model/candidates.php";
include "app/model/votes.php";
include "app/model/voters.php";
include "app/model/elections.php";

// Get election ID from URL
$election_id = isset($_GET['election_id']) ? (int) $_GET['election_id'] : 0;
$voter_id = $_SESSION['id'] ?? 0;

if (!$election_id) {
    header("Location: voter_dashboard.php?message=Invalid election&status=error");
    exit();
}

// Get voter information
$voter = get_voter_by_id($conn, $_SESSION['id']);

// Get election details
$election = get_election_by_id($conn, $election_id);

// Check if user has voted in this election
try {
    $sql = "SELECT * FROM votes 
            WHERE voter_id = :voter_id 
            AND election_id = :election_id 
            AND status IN ('verified', 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':voter_id' => $voter_id,
        ':election_id' => $election_id
    ]);
    $user_vote = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error checking user vote: " . $e->getMessage());
    $user_vote = false;
}

// Get all candidates for this election with vote counts
try {
    $sql = "SELECT 
                c.*,
                v.full_name as candidate_name,
                v.email as candidate_email,
                COUNT(votes.id) as vote_count,
                CASE WHEN MAX(votes.voter_id) = :voter_id THEN 1 ELSE 0 END as is_user_vote
            FROM candidates c
            JOIN voters v ON c.voter_id = v.id
            LEFT JOIN votes ON c.id = votes.candidate_id 
                AND votes.election_id = c.election_id 
                AND votes.status IN ('verified', 'pending')
            WHERE c.election_id = :election_id 
                AND c.status = 'approved'
            GROUP BY c.id, v.full_name, v.email, c.party_affiliation, 
                     c.biography, c.campaign_statement, c.profile_image
            ORDER BY vote_count DESC, candidate_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':election_id' => $election_id,
        ':voter_id' => $voter_id
    ]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching candidates: " . $e->getMessage());
    $candidates = [];
}

// Calculate total votes
$total_votes = 0;
foreach ($candidates as $candidate) {
    $total_votes += $candidate['vote_count'];
}

// Get voter turnout
try {
    $sql = "SELECT COUNT(DISTINCT voter_id) as unique_voters,
                   (SELECT COUNT(*) FROM voters WHERE status = 'verified') as total_verified_voters
            FROM votes 
            WHERE election_id = :election_id 
            AND status IN ('verified', 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':election_id' => $election_id]);
    $turnout_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $unique_voters = $turnout_data['unique_voters'] ?? 0;
    $total_verified_voters = $turnout_data['total_verified_voters'] ?? 1;
    $turnout_percentage = round(($unique_voters / $total_verified_voters) * 100, 1);
} catch (PDOException $e) {
    error_log("Error calculating turnout: " . $e->getMessage());
    $unique_voters = 0;
    $turnout_percentage = 0;
}

// Get admin contact info
try {
    $sql = "SELECT contact, email, location FROM admin LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admin = [
        'contact' => '+237 653 426 838',
        'email' => 'support@votesecure.com',
        'location' => 'Douala, Cameroon'
    ];
}

// Format dates
$election_start = date('F j, Y', strtotime($election['start_datetime']));
$election_end = date('F j, Y', strtotime($election['end_datetime']));
$election_start_full = date('F j, Y g:i A', strtotime($election['start_datetime']));
$election_end_full = date('F j, Y g:i A', strtotime($election['end_datetime']));

// Determine if results are available
$results_available = ($election['status'] == 'completed' || $election['status'] == 'active');
$election_ended = ($election['status'] == 'completed' || strtotime($election['end_datetime']) < time());

// Get voter initials for avatar
$voter_initials = strtoupper(substr($voter['full_name'] ?? 'V', 0, 2));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($election['title']) ?> - Results | SecureVote
    </title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
        }

        /* Results Content */
        .results-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Election Header */
        .election-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(44, 62, 80, 0.2);
        }

        .election-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .election-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            width: 20px;
            color: rgba(255, 255, 255, 0.8);
        }

        .election-status-badge {
            display: inline-block;
            padding: 0.35rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .election-status-badge.completed {
            background: var(--success-color);
        }

        .election-status-badge.active {
            background: var(--warning-color);
            color: var(--primary-color);
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
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-icon.total {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
        }

        .stat-icon.turnout {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .stat-icon.participants {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-sub {
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* Chart Container */
        .chart-container {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }

        /* Candidates Results */
        .candidates-results {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .candidates-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .candidates-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .candidate-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
        }

        .candidate-item:last-child {
            border-bottom: none;
        }

        .candidate-item:hover {
            background-color: #f8f9fa;
        }

        .candidate-item.winner {
            background-color: rgba(46, 204, 113, 0.05);
            border-left: 4px solid var(--success-color);
        }

        .candidate-rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--primary-color);
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .candidate-rank.winner {
            background-color: var(--success-color);
            color: white;
        }

        .candidate-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            margin-right: 1.5rem;
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .candidate-info {
            flex: 1;
        }

        .candidate-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .candidate-party {
            display: inline-block;
            padding: 0.2rem 0.75rem;
            background-color: #f8f9fa;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .candidate-bio {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .candidate-statement {
            font-size: 0.9rem;
            color: var(--primary-color);
            font-style: italic;
        }

        .candidate-votes {
            min-width: 200px;
            text-align: right;
        }

        .vote-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .vote-percentage {
            font-size: 1rem;
            color: #6c757d;
            font-weight: 500;
        }

        .progress-bar-container {
            width: 150px;
            height: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--voter-color) 0%, #2980b9 100%);
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .winner-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .user-vote-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--voter-color);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Your Vote Card */
        .your-vote-card {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(41, 128, 185, 0.05) 100%);
            border: 2px solid var(--voter-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .vote-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .vote-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--voter-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .vote-text h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .vote-text p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .receipt-btn {
            padding: 0.75rem 1.5rem;
            background-color: var(--voter-color);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .receipt-btn:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-2px);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background-color: var(--voter-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid #dee2e6;
        }

        .btn-outline:hover {
            background-color: #f8f9fa;
            border-color: var(--voter-color);
            color: var(--voter-color);
        }

        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .results-content {
                padding: 1.5rem;
            }

            .candidate-item {
                flex-direction: column;
                text-align: center;
            }

            .candidate-avatar {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .candidate-votes {
                text-align: center;
                margin-top: 1rem;
                width: 100%;
            }

            .progress-bar-container {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .election-header h1 {
                font-size: 1.5rem;
            }

            .election-meta {
                gap: 1rem;
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .your-vote-card {
                flex-direction: column;
                text-align: center;
            }

            .vote-info {
                flex-direction: column;
                margin-bottom: 1rem;
            }

            .action-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .results-content {
                padding: 1rem;
            }

            .chart-wrapper {
                height: 300px;
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
            <div class="voter-profile">
                <div class="d-flex align-items-center">
                    <div class="voter-avatar">
                        <?= $voter_initials ?>
                    </div>
                    <span class="ms-2 d-none d-md-block">
                        <?= htmlspecialchars($voter['full_name'] ?? 'Voter') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <!-- Results Content -->
    <main class="results-content">
        <!-- Election Header -->
        <div class="election-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1>
                        <?= htmlspecialchars($election['title']) ?>
                    </h1>
                    <div class="election-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>
                                <?= $election_start_full ?> -
                                <?= $election_end_full ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span>
                                <?= $unique_voters ?> Voters Participated
                            </span>
                        </div>
                    </div>
                </div>
                <span class="election-status-badge <?= $election['status'] ?>">
                    <?= ucfirst($election['status']) ?>
                </span>
            </div>
            <?php if (!empty($election['description'])): ?>
                <p class="mt-3" style="opacity: 0.9;">
                    <?= htmlspecialchars($election['description']) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!$results_available): ?>
            <!-- No Results Available -->
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>Results Not Available</h3>
                <p>Results for this election will be available after the voting period ends.</p>
                <a href="voter_dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Return to Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Total Votes Cast</div>
                        <div class="stat-number">
                            <?= number_format($total_votes) ?>
                        </div>
                        <div class="stat-sub">Across
                            <?= count($candidates) ?> candidates
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon turnout">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Voter Turnout</div>
                        <div class="stat-number">
                            <?= $turnout_percentage ?>%
                        </div>
                        <div class="stat-sub">
                            <?= $unique_voters ?> of
                            <?= $total_verified_voters ?> voters
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon participants">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Candidates</div>
                        <div class="stat-number">
                            <?= count($candidates) ?>
                        </div>
                        <div class="stat-sub">Approved participants</div>
                    </div>
                </div>
            </div>

            <!-- Your Vote Card (if user voted) -->
            <?php if ($user_vote): ?>
                <div class="your-vote-card">
                    <div class="vote-info">
                        <div class="vote-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="vote-text">
                            <h4>You Voted in This Election</h4>
                            <p>Vote recorded on
                                <?= date('F j, Y \a\t g:i A', strtotime($user_vote['vote_timestamp'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Results Chart -->
            <?php if (!empty($candidates) && $total_votes > 0): ?>
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Vote Distribution</h3>
                        <span class="text-muted">Based on verified votes</span>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="resultsChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Candidates Results -->
            <div class="candidates-results">
                <div class="candidates-header">
                    <h3>Candidate Results</h3>
                    <span class="text-muted">
                        <?= $total_votes ?> total votes
                    </span>
                </div>

                <?php if (empty($candidates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No approved candidates found for this election.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $rank = 1;
                    $max_votes = !empty($candidates) ? $candidates[0]['vote_count'] : 0;
                    foreach ($candidates as $candidate):
                        $percentage = $total_votes > 0 ? round(($candidate['vote_count'] / $total_votes) * 100, 1) : 0;
                        $is_winner = ($rank == 1 && $election_ended && $candidate['vote_count'] > 0);
                        $is_user_vote = $candidate['is_user_vote'] ?? 0;
                        ?>
                        <div class="candidate-item <?= $is_winner ? 'winner' : '' ?>">
                            <div class="candidate-rank <?= $is_winner ? 'winner' : '' ?>">
                                <?= $rank ?>
                            </div>
                            <div class="candidate-avatar">
                                <?= strtoupper(substr($candidate['candidate_name'], 0, 2)) ?>
                            </div>
                            <div class="candidate-info">
                                <div class="d-flex align-items-center flex-wrap">
                                    <span class="candidate-name">
                                        <?= htmlspecialchars($candidate['candidate_name']) ?>
                                    </span>
                                    <?php if ($is_winner): ?>
                                        <span class="winner-badge">
                                            <i class="fas fa-crown me-1"></i>Winner
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($is_user_vote): ?>
                                        <span class="user-vote-badge">
                                            <i class="fas fa-check-circle me-1"></i>Your Vote
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($candidate['party_affiliation'])): ?>
                                    <span class="candidate-party">
                                        <?= htmlspecialchars($candidate['party_affiliation']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($candidate['campaign_statement'])): ?>
                                    <p class="candidate-statement mt-1">
                                        "
                                        <?= htmlspecialchars($candidate['campaign_statement']) ?>"
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="candidate-votes">
                                <div class="vote-count">
                                    <?= number_format($candidate['vote_count']) ?>
                                </div>
                                <div class="vote-percentage">
                                    <?= $percentage ?>%
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill"
                                        style="width: <?= ($max_votes > 0) ? ($candidate['vote_count'] / $max_votes * 100) : 0 ?>%;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $rank++;
                    endforeach;
                    ?>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="voter_dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <?php if ($election_ended): ?>
                    <button class="btn btn-outline" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Results
                    </button>
                <?php endif; ?>
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
                        <li><i class="fas fa-envelope me-2"></i>
                            <?= htmlspecialchars($admin['email'] ?? 'support@votesecure.com') ?>
                        </li>
                        <li><i class="fas fa-phone me-2"></i>
                            <?= htmlspecialchars($admin['contact'] ?? '+237 653 426 838') ?>
                        </li>
                        <li><i class="fas fa-map-marker-alt me-2"></i>
                            <?= htmlspecialchars($admin['location'] ?? 'Douala, Cameroon') ?>
                        </li>
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

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if ($results_available && !empty($candidates) && $total_votes > 0): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Prepare chart data
                const ctx = document.getElementById('resultsChart').getContext('2d');

                const candidateNames = <?= json_encode(array_map(function ($c) {
                    return htmlspecialchars($c['candidate_name']);
                }, $candidates)) ?>;

                const voteCounts = <?= json_encode(array_map(function ($c) {
                    return (int) $c['vote_count'];
                }, $candidates)) ?>;

                const backgroundColors = [
                    '#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6',
                    '#1abc9c', '#e67e22', '#34495e', '#16a085', '#c0392b'
                ];

                // Create chart
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: candidateNames,
                        datasets: [{
                            label: 'Votes Received',
                            data: voteCounts,
                            backgroundColor: backgroundColors.slice(0, candidateNames.length),
                            borderColor: '#fff',
                            borderWidth: 2,
                            borderRadius: 6,
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
                                callbacks: {
                                    label: function (context) {
                                        const value = context.raw;
                                        const total = <?= $total_votes ?>;
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${value} votes (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f8f9fa'
                                },
                                title: {
                                    display: true,
                                    text: 'Number of Votes'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });

                // Check for URL parameters
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
                    history.replaceState(null, null, window.location.pathname + '?election_id=' + <?= $election_id ?>);
                }

                // Animate progress bars on scroll
                const progressBars = document.querySelectorAll('.progress-bar-fill');
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.width = entry.target.style.width;
                        }
                    });
                });

                progressBars.forEach(bar => observer.observe(bar));
            });
        </script>
    <?php endif; ?>
</body>

</html>