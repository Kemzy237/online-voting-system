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

$election_id = $_GET['id'];

// Get election data
$election = get_election_by_id($conn, $election_id);
if (!$election) {
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

// Get candidates for this election
$candidates = get_candidates_by_election($conn, $election_id);
$num_candidates = is_array($candidates) ? count($candidates) : 0;

// Get all votes for this election
$T_votes = get_election_votes($conn, $election_id);
if($T_votes != 0){
    $total_votes = count($T_votes) ?? 1;
}else $total_votes = 1;


// Get verified voters count
$total_voters = count(get_all_verified_voters($conn));

// Calculate voting percentage
$voting_percentage = ($total_votes / $total_voters) * 100;
$voting_percentage = min($voting_percentage, 100);

// Calculate candidate votes and percentages
$candidate_votes_data = [];
$winner_votes = 0;
$winners = [];

$candidate_votes_data = [];
$winner_votes = 0;
$winners = [];

if ($num_candidates > 0) {
    foreach ($candidates as $candidate) {
        // Get votes for this candidate using PDO
        $vote_query = "SELECT COUNT(*) as vote_count FROM votes WHERE candidate_id = :candidate_id AND election_id = :election_id";
        $vote_stmt = $conn->prepare($vote_query);
        $vote_stmt->bindParam(':candidate_id', $candidate['id'], PDO::PARAM_INT);
        $vote_stmt->bindParam(':election_id', $election_id, PDO::PARAM_INT);
        $vote_stmt->execute();
        $vote_row = $vote_stmt->fetch(PDO::FETCH_ASSOC);
        $vote_count = $vote_row['vote_count'] ?? 0;

        $candidate_votes_data[] = [
            'id' => $candidate['id'],
            'voter_id' => $candidate['voter_id'],
            'name' => get_voter_name_by_id($conn, $candidate['voter_id']),
            'party' => $candidate['party_affiliation'] ?? 'Independent',
            'campaign_statement' => $candidate['campaign_statement'] ?? '',
            'votes' => $vote_count,
            'percentage' => $total_votes > 0 ? ($vote_count / $total_votes) * 100 : 0
        ];
    }

    // Sort by votes (highest to lowest)
    usort($candidate_votes_data, function ($a, $b) {
        return $b['votes'] - $a['votes'];
    });

    // Determine winner(s)
    if (!empty($candidate_votes_data)) {
        $winner_votes = $candidate_votes_data[0]['votes'];
        foreach ($candidate_votes_data as $candidate) {
            if ($candidate['votes'] == $winner_votes && $winner_votes > 0) {
                $winners[] = $candidate;
            }
        }
    }
}


// Format dates
$start_date_formatted = date('F d, Y \a\t h:i A', strtotime($election['start_datetime']));
$end_date_formatted = date('F d, Y \a\t h:i A', strtotime($election['end_datetime']));

// Check if election is completed
$is_completed = ($election['status'] == 'completed');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results | SecureVote Admin</title>
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

        /* Results Header Card */
        .results-header-card {
            background: linear-gradient(135deg, var(--admin-color) 0%, #8e44ad 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem;
            box-shadow: 0 10px 30px rgba(155, 89, 182, 0.2);
        }

        .results-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        /* Results Content */
        .results-content {
            padding: 0 2rem 2rem;
        }

        /* Winner Banner */
        .winner-banner {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #856404;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid #ffc107;
        }

        /* Stats Cards */
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            height: 100%;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Results Table */
        .results-table-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            padding: 1.5rem;
        }

        .rank-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }

        .rank-1 {
            background-color: #ffd700;
        }

        .rank-2 {
            background-color: #c0c0c0;
        }

        .rank-3 {
            background-color: #cd7f32;
        }

        .rank-other {
            background-color: #6c757d;
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
            height: 300px;
            position: relative;
        }

        /* Progress Bar */
        .vote-progress {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .vote-progress-bar {
            height: 100%;
            border-radius: 4px;
        }

        /* Buttons */
        .btn-purple {
            background-color: var(--admin-color);
            border-color: var(--admin-color);
            color: white;
        }

        .btn-outline-purple {
            border-color: var(--admin-color);
            color: var(--admin-color);
        }

        /* Mobile Toggle */
        #mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-right: 1rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            #main-content {
                margin-left: 0;
            }

            #mobile-toggle {
                display: block;
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
                    title: "Notification",
                    text: message,
                    icon: status,
                    confirmButtonText: 'OK',
                    showCloseButton: true
                });

                // Remove query parameters from URL
                const newUrl = window.location.pathname;
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
                <h1>Election Results</h1>
            </div>
            <div class="action-buttons">
                <a href="view_election.php?id=<?= $election['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i> Back to Election
                </a>
            </div>
        </header>

        <!-- Results Header -->
        <div class="results-header-card">
            <h1 class="results-title"><?= htmlspecialchars($election['title']) ?> - Results</h1>
            <div class="results-subtitle">
                Election ID: #<?= $election['id'] ?> |
                Status: <strong><?= ucfirst($election['status']) ?></strong>
            </div>
            <div class="mt-2">
                <span class="badge bg-light text-dark me-2">
                    <i class="fas fa-calendar me-1"></i> <?= $start_date_formatted ?>
                </span>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-calendar-check me-1"></i> <?= $end_date_formatted ?>
                </span>
            </div>
        </div>

        <!-- Results Content -->
        <main class="results-content">
            <!-- Winner Banner -->
            <?php if (!empty($winners) && $winner_votes > 0): ?>
                <div class="winner-banner">
                    <h5 class="winner-title">
                        <i class="fas fa-trophy"></i>
                        <?php if (count($winners) == 1): ?>
                            Winner
                        <?php else: ?>
                            Winners (Tie)
                        <?php endif; ?>
                    </h5>
                    <p>With <?= $winner_votes ?> vote<?= $winner_votes != 1 ? 's' : '' ?>
                        (<?= number_format(($winner_votes / $total_votes) * 100, 1) ?>% of total votes)</p>
                    <div class="winner-list">
                        <?php foreach ($winners as $winner): ?>
                            <div class="badge bg-white text-dark me-2">
                                <i class="fas fa-crown text-warning me-1"></i>
                                <?= htmlspecialchars($winner['name']) ?>
                                <span class="text-muted">(<?= htmlspecialchars($winner['party']) ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users text-primary"></i>
                        </div>
                        <div class="stat-number"><?= $total_voters ?></div>
                        <div class="stat-label">Registered Voters</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-vote-yea text-success"></i>
                        </div>
                        <div class="stat-number"><?= $total_votes ?></div>
                        <div class="stat-label">Total Votes Cast</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie text-purple"></i>
                        </div>
                        <div class="stat-number"><?= $num_candidates ?></div>
                        <div class="stat-label">Candidates</div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line text-warning"></i>
                        </div>
                        <div class="stat-number"><?= number_format($voting_percentage, 1) ?>%</div>
                        <div class="stat-label">Voter Turnout</div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <!-- Left Column: Results Table -->
                <div class="col-lg-8">
                    <!-- Results Table -->
                    <div class="results-table-container">
                        <h3><i class="fas fa-list-ol me-2"></i> Candidate Results</h3>
                        <div class="table-responsive mt-3">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="60">Rank</th>
                                        <th>Candidate</th>
                                        <th>Party</th>
                                        <th width="100">Votes</th>
                                        <th width="100">Percentage</th>
                                        <th width="150">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($candidate_votes_data)): ?>
                                        <?php $rank = 1; ?>
                                        <?php foreach ($candidate_votes_data as $candidate):
                                            $is_winner = ($winner_votes > 0 && $candidate['votes'] == $winner_votes);
                                            $rank_class = $rank <= 3 ? "rank-$rank" : "rank-other";
                                            ?>
                                            <tr class="<?= $is_winner ? 'table-warning' : '' ?>">
                                                <td>
                                                    <div class="rank-badge <?= $rank_class ?>">
                                                        <?= $rank ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($candidate['name']) ?></strong>
                                                    <?php if (!empty($candidate['campaign_statement'])): ?>
                                                        <div class="small text-muted">
                                                            "<?= htmlspecialchars(substr($candidate['campaign_statement'], 0, 50)) ?>"
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-secondary"><?= htmlspecialchars($candidate['party']) ?></span>
                                                </td>
                                                <td class="fw-bold"><?= $candidate['votes'] ?></td>
                                                <td class="fw-bold"><?= number_format($candidate['percentage'], 1) ?>%</td>
                                                <td>
                                                    <div class="vote-progress">
                                                        <div class="vote-progress-bar"
                                                            style="width: <?= $candidate['percentage'] ?>%; 
                                                                    background-color: <?= $is_winner ? '#ffc107' : '#9b59b6' ?>;">
                                                        </div>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?= number_format($candidate['percentage'], 1) ?>%</div>
                                                </td>
                                            </tr>
                                            <?php $rank++; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-user-tie fa-2x text-muted mb-3"></i>
                                                <h5>No Candidates</h5>
                                                <p class="text-muted">No candidates participated in this election.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Charts -->
                <div class="col-lg-4">
                    <!-- Results Chart -->
                    <div class="chart-container">
                        <h3><i class="fas fa-poll me-2"></i> Vote Distribution</h3>
                        <div class="chart-wrapper">
                            <canvas id="resultsChart"></canvas>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="chart-container">
                        <h3><i class="fas fa-cogs me-2"></i> Quick Action</h3>
                        <div class="d-grid gap-2">
                            <a href="view_election.php?id=<?= $election['id'] ?>" class="btn btn-outline-purple">
                                <i class="fas fa-eye me-2"></i> View Election Details
                            </a>
                            <a href="view_all_votes.php?id=<?= $election['id'] ?>" class="btn btn-outline-purple">
                                <i class="fas fa-eye me-2"></i> View All Votes
                            </a>
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
            // Sidebar toggle
            const mobileToggleBtn = document.getElementById('mobile-toggle');
            if (mobileToggleBtn) {
                mobileToggleBtn.addEventListener('click', () => {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.toggle('mobile-show');
                });
            }

            // Prepare chart data
            <?php if (!empty($candidate_votes_data)): ?>
                const candidateNames = [];
                const candidateVotes = [];
                const candidateColors = [];

                <?php
                $counter = 0;
                foreach ($candidate_votes_data as $candidate):
                    if ($counter >= 8)
                        break; // Limit to top 8 for readability
                    $is_winner = ($winner_votes > 0 && $candidate['votes'] == $winner_votes);
                    ?>
                    candidateNames.push("<?= addslashes($candidate['name']) ?>");
                    candidateVotes.push(<?= $candidate['votes'] ?>);
                    candidateColors.push("<?= $is_winner ? '#ffc107' : '#9b59b6' ?>");
                    <?php
                    $counter++;
                endforeach;
                ?>
            <?php endif; ?>

            // Initialize Results Chart (Bar Chart)
            const resultsCtx = document.getElementById('resultsChart');
            if (resultsCtx && candidateNames.length > 0) {
                new Chart(resultsCtx, {
                    type: 'bar',
                    data: {
                        labels: candidateNames,
                        datasets: [{
                            label: 'Votes',
                            data: candidateVotes,
                            backgroundColor: candidateColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45
                                }
                            }
                        }
                    }
                });
            }
           
        });
    </script>
</body>

</html>