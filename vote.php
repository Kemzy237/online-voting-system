<?php
session_name('voter');
session_start();

if (!isset($_SESSION['id']) && !isset($_SESSION['email'])) {
    $message = "Please login first";
    $status = "error";
    header("Location: index.php?message=$message&status=$status");
    exit();
}

include "db_connection.php";
include "app/model/voters.php";
include "app/model/votes.php";
include "app/model/elections.php";
include "app/model/candidates.php";

$id = $_SESSION['id'];
$email = $_SESSION['email'];

// Get voter by ID
$voter = get_voter_by_id($conn, $id);
if (!$voter) {
    session_destroy();
    header("Location: index.php?message=Voter not found&status=error");
    exit();
}

// Check if voter is verified
if ($voter['status'] != 'verified') {
    header("Location: voter_dashboard.php?message=Your account needs to be verified to vote&status=error");
    exit();
}

// Check if election_id is provided
if (!isset($_GET['election_id']) || empty($_GET['election_id'])) {
    header("Location: voter_dashboard.php?message=Please select an election to vote&status=error");
    exit();
}

$election_id = $_GET['election_id'];

// Get election details
$election = get_election_by_id($conn, $election_id);
if (!$election) {
    header("Location: voter_dashboard.php?message=Election not found&status=error");
    exit();
}

// Check if election is active
$current_time = date('Y-m-d H:i:s');
if ($election['status'] != 'active' || $current_time < $election['start_datetime'] || $current_time > $election['end_datetime']) {
    header("Location: voter_dashboard.php?message=This election is not currently active&status=error");
    exit();
}

// Check if voter has already voted in this election
$has_voted = check_if_voted($conn, $id, $election_id);
if ($has_voted) {
    header("Location: voter_dashboard.php?message=You have already voted in this election&status=error");
    exit();
}

// Get candidates for this election
$candidates = get_candidates_by_election($conn, $election_id);
if (empty($candidates)) {
    header("Location: voter_dashboard.php?message=No candidates available for this election&status=error");
    exit();
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cast_vote']) && isset($_POST['candidate_id'])) {
        $candidate_id = $_POST['candidate_id'];
        
        // Verify candidate exists in this election
        $candidate_exists = false;
        foreach ($candidates as $candidate) {
            if ($candidate['id'] == $candidate_id) {
                $candidate_exists = true;
                break;
            }
        }
        
        if (!$candidate_exists) {
            $error_message = "Invalid candidate selected";
        } else {
            // Cast vote
            try {
                $sql = "INSERT INTO votes (voter_id, election_id, candidate_id, status, vote_timestamp) 
                        VALUES (:voter_id, :election_id, :candidate_id, 'pending', NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':voter_id' => $id,
                    ':election_id' => $election_id,
                    ':candidate_id' => $candidate_id
                ]);
                
                // Record the vote ID for receipt
                $vote_id = $conn->lastInsertId();
                
                // Redirect to success page with vote receipt
                header("Location: vote_success.php?vote_id=$vote_id");
                exit();
                
            } catch (PDOException $e) {
                $error_message = "Error casting vote: " . $e->getMessage();
                error_log("Vote submission error: " . $e->getMessage());
            }
        }
    } else {
        $error_message = "Please select a candidate to vote for";
    }
}

// Function to check if voter has already voted
function check_if_voted($conn, $voter_id, $election_id) {
    try {
        $sql = "SELECT id FROM votes 
                WHERE voter_id = :voter_id 
                AND election_id = :election_id 
                AND status IN ('verified', 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':voter_id' => $voter_id,
            ':election_id' => $election_id
        ]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error checking vote: " . $e->getMessage());
        return true; // Err on the side of caution
    }
}

// Calculate time remaining
$end_time = strtotime($election['end_datetime']);
$current_time = time();
$time_remaining = $end_time - $current_time;
$hours_remaining = floor($time_remaining / 3600);
$minutes_remaining = floor(($time_remaining % 3600) / 60);
$seconds_remaining = $time_remaining % 60;

// Get admin contact info
$sql = "SELECT contact, email, location FROM admin LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote | SecureVote</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* Vote Content */
        .vote-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Election Header */
        .election-header {
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
            position: relative;
            overflow: hidden;
        }

        .election-header::before {
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

        .election-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .election-header p {
            opacity: 0.9;
            margin-bottom: 1rem;
            position: relative;
        }

        .time-remaining {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            position: relative;
            backdrop-filter: blur(10px);
        }

        /* Vote Container */
        .vote-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 992px) {
            .vote-container {
                grid-template-columns: 1fr;
            }
        }

        /* Candidates Section */
        .candidates-section {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-header h2 i {
            margin-right: 0.75rem;
            color: var(--voter-color);
        }

        .candidates-count {
            background-color: var(--voter-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Candidates Grid */
        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .candidates-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Candidate Card */
        .candidate-card {
            background-color: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .candidate-card:hover {
            border-color: var(--voter-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.1);
        }

        .candidate-card.selected {
            border-color: var(--success-color);
            background-color: rgba(46, 204, 113, 0.05);
        }

        .candidate-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .candidate-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 auto 1rem;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .candidate-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .candidate-party {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .candidate-bio {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            line-height: 1.5;
            text-align: center;
        }

        .candidate-statement {
            font-style: italic;
            color: var(--primary-color);
            text-align: center;
            font-size: 0.9rem;
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .select-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .candidate-card.selected .select-indicator {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .candidate-card.selected .select-indicator::after {
            content: '✓';
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
        }

        /* Voting Sidebar */
        .voting-sidebar {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            height: fit-content;
            position: sticky;
            top: calc(var(--header-height) + 2rem);
        }

        .voter-info-sidebar {
            text-align: center;
            margin-bottom: 2rem;
        }

        .voter-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--voter-color) 0%, #2980b9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 1rem;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .voter-name-sidebar {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .voter-id {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .vote-instructions {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .vote-instructions h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .instructions-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .instructions-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .instructions-list li i {
            color: var(--voter-color);
            margin-right: 0.5rem;
            margin-top: 0.1rem;
        }

        /* Vote Form */
        .vote-form {
            margin-top: 2rem;
        }

        .selected-candidate-info {
            background-color: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .selected-candidate-info.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .selected-candidate-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .selected-candidate-avatar {
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

        .selected-candidate-name {
            font-weight: 600;
            color: var(--success-color);
            font-size: 1.1rem;
        }

        .confirmation-text {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .vote-actions {
            display: flex;
            gap: 1rem;
        }

        .vote-actions .btn {
            flex: 1;
            padding: 0.75rem;
            font-weight: 600;
        }

        /* Terms Modal */
        .terms-content {
            max-height: 400px;
            overflow-y: auto;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .terms-content h5 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .terms-content p {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .terms-content ul {
            padding-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .terms-content li {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .vote-content {
                padding: 1rem;
            }

            .election-header {
                padding: 1.5rem;
            }

            .election-header h1 {
                font-size: 1.5rem;
            }

            .candidates-section,
            .voting-sidebar {
                padding: 1.5rem;
            }

            .vote-actions {
                flex-direction: column;
            }

            #main-header {
                padding: 0 1rem;
            }

            .voter-info {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .candidates-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Error Message */
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: var(--danger-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .error-message i {
            font-size: 1.2rem;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--voter-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.1rem;
            color: var(--primary-color);
            font-weight: 600;
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

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .btn-success:hover {
            background-color: #229954;
            border-color: #229954;
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
                        <?= strtoupper(substr($voter['full_name'], 0, 2)) ?>
                    </div>
                    <div class="voter-info">
                        <div class="voter-name"><?= htmlspecialchars($voter['full_name']) ?></div>
                        <span class="voter-status verified">
                            Verified Voter
                        </span>
                    </div>
                    <i class="fas fa-chevron-down ms-1 text-muted"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <h6 class="dropdown-header">Voter Account</h6>
                    </li>
                    <li><a class="dropdown-item" href="voter_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a class="dropdown-item" href="voter_profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="voting_history.php"><i class="fas fa-history"></i> Voting History</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Vote Content -->
    <main class="vote-content">
        <!-- Election Header -->
        <div class="election-header">
            <h1><?= htmlspecialchars($election['title']) ?></h1>
            <?php if (!empty($election['description'])): ?>
                <p><?= htmlspecialchars($election['description']) ?></p>
            <?php endif; ?>
            
            <div class="time-remaining">
                <i class="fas fa-clock"></i>
                <span id="countdown">
                    <?= sprintf('%02d:%02d:%02d', $hours_remaining, $minutes_remaining, $seconds_remaining) ?>
                </span>
                <span>remaining to vote</span>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <div class="vote-container">
            <!-- Candidates Section -->
            <div class="candidates-section">
                <div class="section-header">
                    <h2><i class="fas fa-users me-2"></i>Select Your Candidate</h2>
                    <div class="candidates-count"><?= count($candidates) ?> Candidates</div>
                </div>

                <form method="POST" action="" id="voteForm">
                    <div class="candidates-grid">
                        <?php foreach ($candidates as $index => $candidate): 
                            $candidate_info = get_voter_by_id($conn, $candidate['voter_id']);
                            $candidate_initials = strtoupper(substr($candidate_info['full_name'], 0, 2));
                        ?>
                            <label class="candidate-card" for="candidate_<?= $candidate['id'] ?>">
                                <input type="radio" name="candidate_id" value="<?= $candidate['id'] ?>" 
                                       id="candidate_<?= $candidate['id'] ?>" class="candidate-radio">
                                <div class="select-indicator"></div>
                                
                                <div class="candidate-avatar">
                                    <?= $candidate_initials ?>
                                </div>
                                
                                <h3 class="candidate-name"><?= htmlspecialchars($candidate_info['full_name']) ?></h3>
                                
                                <?php if (!empty($candidate['party_affiliation'])): ?>
                                    <div class="candidate-party"><?= htmlspecialchars($candidate['party_affiliation']) ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($candidate['biography'])): ?>
                                    <p class="candidate-bio"><?= htmlspecialchars(substr($candidate['biography'], 0, 150)) ?>
                                        <?= strlen($candidate['biography']) > 150 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($candidate['campaign_statement'])): ?>
                                    <p class="candidate-statement">"<?= htmlspecialchars(substr($candidate['campaign_statement'], 0, 100)) ?>
                                        <?= strlen($candidate['campaign_statement']) > 100 ? '...' : '' ?>"
                                    </p>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hidden form field for CSRF protection -->
                    <input type="hidden" name="cast_vote" value="1">
                </form>
            </div>

            <!-- Voting Sidebar -->
            <div class="voting-sidebar">
                <div class="voter-info-sidebar">
                    <div class="voter-avatar-large">
                        <?= strtoupper(substr($voter['full_name'], 0, 2)) ?>
                    </div>
                    <h3 class="voter-name-sidebar"><?= htmlspecialchars($voter['full_name']) ?></h3>
                    <div class="voter-id">Voter ID: <?= htmlspecialchars($voter['id']) ?></div>
                    <span class="voter-status verified">Verified Voter</span>
                </div>

                <div class="vote-instructions">
                    <h4><i class="fas fa-info-circle me-2"></i>Voting Instructions</h4>
                    <ul class="instructions-list">
                        <li><i class="fas fa-check-circle"></i> Select one candidate by clicking on their card</li>
                        <li><i class="fas fa-check-circle"></i> Review your selection in the confirmation section</li>
                        <li><i class="fas fa-check-circle"></i> Read and accept the voting terms and conditions</li>
                        <li><i class="fas fa-check-circle"></i> Click "Cast Vote" to submit your vote</li>
                        <li><i class="fas fa-exclamation-triangle"></i> You cannot change your vote after submission</li>
                    </ul>
                </div>

                <div class="selected-candidate-info" id="selectedCandidateInfo">
                    <div class="selected-candidate-header">
                        <div class="selected-candidate-avatar" id="selectedAvatar">JD</div>
                        <div>
                            <div class="selected-candidate-name" id="selectedName">John Doe</div>
                            <div class="candidate-party" id="selectedParty">Independent</div>
                        </div>
                    </div>
                    <p class="mb-0">You have selected this candidate. Click "Cast Vote" to confirm.</p>
                </div>

                <div class="vote-form">
                    <div class="confirmation-text" id="confirmationText">
                        Please select a candidate to vote for
                    </div>
                    
                    <div class="vote-actions">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </button>
                        <button type="button" class="btn btn-success" id="castVoteBtn" disabled 
                                data-bs-toggle="modal" data-bs-target="#confirmVoteModal">
                            <i class="fas fa-vote-yea me-2"></i>Cast Vote
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
                        <li><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($admin['email'] ?? 'support@securevote.com') ?></li>
                        <li><i class="fas fa-phone me-2"></i><?= htmlspecialchars($admin['contact'] ?? '+1 (555) 123-4567') ?></li>
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

    <!-- Confirm Vote Modal -->
    <div class="modal fade" id="confirmVoteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-vote-yea me-2"></i>Confirm Your Vote</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <i class="fas fa-vote-yea fa-3x text-success"></i>
                        </div>
                        <h4 class="text-success mb-3">Are you sure?</h4>
                        <p class="mb-4">You are about to cast your vote for:</p>
                        
                        <div class="selected-candidate-info show" style="max-width: 400px; margin: 0 auto;">
                            <div class="selected-candidate-header">
                                <div class="selected-candidate-avatar" id="modalSelectedAvatar">JD</div>
                                <div>
                                    <div class="selected-candidate-name" id="modalSelectedName">John Doe</div>
                                    <div class="candidate-party" id="modalSelectedParty">Independent</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="terms-content mt-4">
                            <h5>Voting Terms & Conditions</h5>
                            <p>By casting your vote, you agree to the following:</p>
                            <ul>
                                <li>Your vote is final and cannot be changed after submission</li>
                                <li>Your vote will be recorded anonymously</li>
                                <li>Voting is restricted to one vote per election per voter</li>
                                <li>Any attempt to vote multiple times will result in disqualification</li>
                                <li>Your vote will be verified and recorded in the system</li>
                                <li>You will receive a voting receipt for your records</li>
                            </ul>
                            <p><strong>Note:</strong> This is a secure voting system. Your vote is encrypted and protected.</p>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I have read and agree to the voting terms and conditions
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="confirmVoteBtn" form="voteForm" disabled>
                        <i class="fas fa-vote-yea me-2"></i> Confirm & Cast Vote
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processing your vote...</div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Countdown timer
            let hours = <?= $hours_remaining ?>;
            let minutes = <?= $minutes_remaining ?>;
            let seconds = <?= $seconds_remaining ?>;
            
            function updateCountdown() {
                seconds--;
                
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                    
                    if (minutes < 0) {
                        minutes = 59;
                        hours--;
                        
                        if (hours < 0) {
                            // Time's up - redirect to dashboard
                            clearInterval(countdownInterval);
                            Swal.fire({
                                title: 'Time\'s Up!',
                                text: 'The voting period for this election has ended.',
                                icon: 'warning',
                                confirmButtonText: 'Return to Dashboard'
                            }).then(() => {
                                window.location.href = 'voter_dashboard.php';
                            });
                            return;
                        }
                    }
                }
                
                document.getElementById('countdown').textContent = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            
            const countdownInterval = setInterval(updateCountdown, 1000);
            
            // Candidate selection
            const candidateCards = document.querySelectorAll('.candidate-card');
            const selectedCandidateInfo = document.getElementById('selectedCandidateInfo');
            const confirmationText = document.getElementById('confirmationText');
            const castVoteBtn = document.getElementById('castVoteBtn');
            const selectedAvatar = document.getElementById('selectedAvatar');
            const selectedName = document.getElementById('selectedName');
            const selectedParty = document.getElementById('selectedParty');
            const modalSelectedAvatar = document.getElementById('modalSelectedAvatar');
            const modalSelectedName = document.getElementById('modalSelectedName');
            const modalSelectedParty = document.getElementById('modalSelectedParty');
            
            candidateCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    candidateCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Get candidate info
                    const candidateName = this.querySelector('.candidate-name').textContent;
                    const candidateParty = this.querySelector('.candidate-party')?.textContent || 'Independent';
                    const candidateAvatar = this.querySelector('.candidate-avatar').textContent;
                    
                    // Update sidebar info
                    selectedAvatar.textContent = candidateAvatar;
                    selectedName.textContent = candidateName;
                    selectedParty.textContent = candidateParty;
                    selectedCandidateInfo.classList.add('show');
                    confirmationText.textContent = 'Your selection is ready. Click "Cast Vote" to proceed.';
                    castVoteBtn.disabled = false;
                    
                    // Update modal info
                    modalSelectedAvatar.textContent = candidateAvatar;
                    modalSelectedName.textContent = candidateName;
                    modalSelectedParty.textContent = candidateParty;
                });
            });
            
            // Terms agreement
            const agreeTermsCheckbox = document.getElementById('agreeTerms');
            const confirmVoteBtn = document.getElementById('confirmVoteBtn');
            
            agreeTermsCheckbox.addEventListener('change', function() {
                confirmVoteBtn.disabled = !this.checked;
            });
            
            // Confirm vote modal handling
            const confirmVoteModal = document.getElementById('confirmVoteModal');
            if (confirmVoteModal) {
                confirmVoteModal.addEventListener('show.bs.modal', function() {
                    // Reset terms checkbox
                    agreeTermsCheckbox.checked = false;
                    confirmVoteBtn.disabled = true;
                    
                    // Check if a candidate is selected
                    const selectedCandidate = document.querySelector('.candidate-card.selected');
                    if (!selectedCandidate) {
                        Swal.fire({
                            title: 'No Candidate Selected',
                            text: 'Please select a candidate before casting your vote.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                });
            }
            
            // Form submission
            const voteForm = document.getElementById('voteForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            voteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading overlay
                loadingOverlay.style.display = 'flex';
                
                // Validate form
                const selectedCandidate = document.querySelector('.candidate-radio:checked');
                if (!selectedCandidate) {
                    loadingOverlay.style.display = 'none';
                    Swal.fire({
                        title: 'Selection Required',
                        text: 'Please select a candidate to vote for.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
                
                // Submit form
                this.submit();
            });
            
            // Initialize Bootstrap dropdowns
            var dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(function(dropdown) {
                new bootstrap.Dropdown(dropdown);
            });
            
            // Handle browser back button
            window.addEventListener('beforeunload', function(e) {
                const selectedCandidate = document.querySelector('.candidate-radio:checked');
                if (selectedCandidate) {
                    e.preventDefault();
                    e.returnValue = 'You have selected a candidate. Are you sure you want to leave?';
                }
            });
        });
    </script>
</body>

</html>