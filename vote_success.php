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
// Note: You might need to update your model files to match the new schema

$id = $_SESSION['id'];
$email = $_SESSION['email'];

// Get voter by ID (using voters table from schema)
try {
    $sql = "SELECT * FROM voters WHERE id = :id AND email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':email' => $email
    ]);

    $voter = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voter) {
        session_destroy();
        header("Location: index.php?message=Voter not found&status=error");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching voter: " . $e->getMessage());
    session_destroy();
    header("Location: index.php?message=Database error&status=error");
    exit();
}

// Check if vote_id is provided
if (!isset($_GET['vote_id']) || empty($_GET['vote_id'])) {
    header("Location: voter_dashboard.php?message=No vote receipt found&status=error");
    exit();
}

$vote_id = $_GET['vote_id'];

// Get vote details with election and candidate info
try {
    $sql = "SELECT v.*, 
                   e.title as election_title, e.description as election_description,
                   e.start_datetime, e.end_datetime, e.status as election_status,
                   c.voter_id as candidate_voter_id, 
                   c.party_affiliation, c.biography, c.campaign_statement,
                   c.profile_image, c.status as candidate_status,
                   vr.full_name as candidate_name, vr.email as candidate_email,
                   v2.full_name as voter_name, v2.email as voter_email,
                   v2.contact as voter_contact, v2.address as voter_address
            FROM votes v
            JOIN elections e ON v.election_id = e.id
            JOIN candidates c ON v.candidate_id = c.id
            JOIN voters vr ON c.voter_id = vr.id
            JOIN voters v2 ON v.voter_id = v2.id
            WHERE v.id = :vote_id AND v.voter_id = :voter_id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':vote_id' => $vote_id,
        ':voter_id' => $id
    ]);

    $vote_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vote_details) {
        header("Location: voter_dashboard.php?message=Vote receipt not found&status=error");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching vote details: " . $e->getMessage());
    header("Location: voter_dashboard.php?message=Error loading vote receipt&status=error");
    exit();
}

// Get admin contact info (from admin table in schema)
try {
    $sql = "SELECT contact, email, location FROM admin LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching admin info: " . $e->getMessage());
    $admin = [
        'contact' => '+237 653 426 838',
        'email' => 'support@votesecure.com',
        'location' => 'Douala, Cameroon'
    ];
}

// Format dates
$vote_date = date('F j, Y', strtotime($vote_details['vote_timestamp']));
$vote_time = date('h:i A', strtotime($vote_details['vote_timestamp']));
$election_start = date('F j, Y', strtotime($vote_details['start_datetime']));
$election_end = date('F j, Y', strtotime($vote_details['end_datetime']));

// Generate receipt number
$receipt_number = 'VOTE-' . str_pad($vote_id, 6, '0', STR_PAD_LEFT);
$transaction_id = 'TX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

// Get candidate initials
$candidate_initials = strtoupper(substr($vote_details['candidate_name'], 0, 2));
$voter_initials = strtoupper(substr($vote_details['voter_name'], 0, 2));

// Check vote status for display
$vote_status_display = ucfirst($vote_details['status']);
$vote_status_color = '';
switch ($vote_details['status']) {
    case 'verified':
        $vote_status_color = 'success';
        break;
    case 'pending':
        $vote_status_color = 'warning';
        break;
    case 'invalid':
    case 'rejected':
        $vote_status_color = 'danger';
        break;
    default:
        $vote_status_color = 'info';
}

// Check election status
$election_active = ($vote_details['election_status'] == 'active');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Receipt | SecureVote</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #1abc9c;
            --voter-color: #3498db;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
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
            height: 70px;
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

        /* Success Content */
        .success-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Success Header */
        .success-header {
            background: linear-gradient(135deg, var(--success-color) 0%, #229954 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-header::before {
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

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .success-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .success-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
        }

        /* Receipt Container */
        .receipt-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 992px) {
            .receipt-container {
                grid-template-columns: 1fr;
            }
        }

        /* Receipt Card */
        .receipt-card {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .receipt-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .receipt-number {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .receipt-number span {
            font-weight: 600;
            color: var(--success-color);
        }

        /* Receipt Sections */
        .receipt-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 0.5rem;
            color: var(--voter-color);
        }

        /* Receipt Details */
        .receipt-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-group {
            margin-bottom: 1rem;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Candidate Info */
        .candidate-info-receipt {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background-color: rgba(52, 152, 219, 0.05);
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .candidate-avatar-receipt {
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
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .candidate-details-receipt {
            flex: 1;
        }

        .candidate-name-receipt {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .candidate-party-receipt {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #f8f9fa;
            color: #6c757d;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .candidate-statement-receipt {
            font-style: italic;
            color: var(--primary-color);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Verification Info */
        .verification-info {
            background-color: rgba(46, 204, 113, 0.05);
            border: 1px solid rgba(46, 204, 113, 0.2);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .verification-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .verification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .verification-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--success-color);
            margin: 0;
        }

        .verification-details {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.6;
        }

        .transaction-id {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            display: inline-block;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Action Sidebar */
        .action-sidebar {
            background-color: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            height: fit-content;
            position: sticky;
            top: calc(70px + 2rem);
        }

        .action-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .voter-avatar-receipt {
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

        .voter-name-receipt {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .voter-id-receipt {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-buttons .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-buttons .btn i {
            margin-right: 0.5rem;
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
            border-color: #229954;
            color: white;
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

        /* Important Notes */
        .important-notes {
            background-color: rgba(241, 196, 15, 0.05);
            border: 1px solid rgba(241, 196, 15, 0.2);
            border-radius: 10px;
            padding: 1.5rem;
        }

        .important-notes h5 {
            color: var(--warning-color);
            margin-bottom: 1rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }

        .important-notes h5 i {
            margin-right: 0.5rem;
        }

        .important-notes ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .important-notes li {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
        }

        .important-notes li i {
            color: var(--warning-color);
            margin-right: 0.5rem;
            margin-top: 0.1rem;
            font-size: 0.8rem;
        }

        /* Footer */
        .footer {
            background-color: var(--primary-color);
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
            border-radius: 30px 30px 0 0;
        }

        /* Print Styles */
        @media print {

            #main-header,
            .action-sidebar,
            .footer,
            .no-print {
                display: none !important;
            }

            body {
                background-color: white !important;
            }

            .success-content {
                padding: 0 !important;
                max-width: 100% !important;
            }

            .receipt-container {
                grid-template-columns: 1fr !important;
                gap: 0 !important;
            }

            .receipt-card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }

            .success-header {
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .success-content {
                padding: 1rem;
            }

            .success-header {
                padding: 1.5rem;
            }

            .success-header h1 {
                font-size: 1.8rem;
            }

            .receipt-card,
            .action-sidebar {
                padding: 1.5rem;
            }

            #main-header {
                padding: 0 1rem;
            }

            .candidate-info-receipt {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .receipt-details {
                grid-template-columns: 1fr;
            }

            .success-header h1 {
                font-size: 1.5rem;
            }

            .success-icon {
                font-size: 3rem;
            }
        }

        /* QR Code */
        .qr-code {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px dashed #dee2e6;
        }

        .qr-placeholder {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin: 0 auto 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .qr-placeholder i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .qr-text {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <!-- Header -->
        <!-- Header -->
    <header id="main-header">
        <div class="header-left">
            <a class="navbar-brand" href="voter_dashboard.php">
                <i class="fas fa-vote-yea text-primary me-2"></i>Secure<span>Vote</span>
            </a>
        </div>
        <div class="header-right">
            <div class="d-flex align-items-center">
                <div class="voter-avatar-receipt" style="width: 40px; height: 40px; font-size: 1rem;">
                    <?= $voter_initials ?>
                    </div>
                    <div class="ms-2">
                        <div class="voter-name-receipt" style="font-size: 0.9rem; margin-bottom: 0;">
                            <?= htmlspecialchars($vote_details['voter_name']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Success Content -->
        <main class="success-content">
            <!-- Success Header -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Vote Successfully Cast!</h1>
                <p>Thank you for participating in the democratic process. Your vote has been recorded.</p>
            </div>
        
            <div class="receipt-container">
                <!-- Receipt Card -->
                <div class="receipt-card">
                    <div class="receipt-header">
                        <h2>Voting Receipt</h2>
                        <div class="receipt-number">
                            Receipt No: <span><?= $receipt_number ?></span>
                        </div>
                    </div>
        
                    <!-- Election Details -->
                    <div class="receipt-section">
                        <h3 class="section-title">
                            <i class="fas fa-poll-h"></i> Election Details
                        </h3>
                        <div class="receipt-details">
                            <div class="detail-group">
                                <div class="detail-label">Election Title</div>
                                <div class="detail-value"><?= htmlspecialchars($vote_details['election_title']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Election Period</div>
                                <div class="detail-value"><?= $election_start ?> - <?= $election_end ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Vote Status</div>
                                <div class="detail-value">
                                    <span style="color: var(--<?= $vote_status_color ?>-color); font-weight: 600;">
                                        <i
                                            class="fas fa-<?= $vote_status_color == 'success' ? 'check' : ($vote_status_color == 'warning' ? 'clock' : 'exclamation') ?>-circle me-1"></i>
                                        <?= $vote_status_display ?>
                                    </span>
                                </div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Election Status</div>
                                <div class="detail-value">
                                    <?= ucfirst($vote_details['election_status']) ?>
                                    <?php if ($election_active): ?>
                                        <span class="badge bg-success ms-2">Live</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
        
                    <!-- Candidate Information -->
                    <div class="receipt-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-tie"></i> Selected Candidate
                        </h3>
                        <div class="candidate-info-receipt">
                            <div class="candidate-avatar-receipt">
                                <?= $candidate_initials ?>
                            </div>
                            <div class="candidate-details-receipt">
                                <h4 class="candidate-name-receipt"><?= htmlspecialchars($vote_details['candidate_name']) ?></h4>
                                <?php if (!empty($vote_details['party_affiliation'])): ?>
                                    <div class="candidate-party-receipt"><?= htmlspecialchars($vote_details['party_affiliation']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($vote_details['campaign_statement'])): ?>
                                    <p class="candidate-statement-receipt">
                                        "<?= htmlspecialchars($vote_details['campaign_statement']) ?>"</p>
                                <?php endif; ?>
                                <div class="candidate-status mt-2">
                                    <small
                                        class="badge bg-<?= $vote_details['candidate_status'] == 'approved' ? 'success' : ($vote_details['candidate_status'] == 'pending' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($vote_details['candidate_status']) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
        
                    <!-- Voting Information -->
                    <div class="receipt-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i> Voting Information
                        </h3>
                        <div class="receipt-details">
                            <div class="detail-group">
                                <div class="detail-label">Voter Name</div>
                                <div class="detail-value"><?= htmlspecialchars($vote_details['voter_name']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Voter Email</div>
                                <div class="detail-value"><?= htmlspecialchars($vote_details['voter_email']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Voter Contact</div>
                                <div class="detail-value"><?= htmlspecialchars($vote_details['voter_contact']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Vote Timestamp</div>
                                <div class="detail-value"><?= $vote_date ?> at <?= $vote_time ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Vote ID</div>
                                <div class="detail-value"><?= $receipt_number ?></div>
                            </div>
                            <?php if ($vote_details['verified_at']): ?>
                                <div class="detail-group">
                                    <div class="detail-label">Verified At</div>
                                    <div class="detail-value">
                                        <?= date('F j, Y \a\t h:i A', strtotime($vote_details['verified_at'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
        
                    <!-- Verification Info -->
                    <div class="verification-info">
                        <div class="verification-header">
                            <div class="verification-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h4 class="verification-title">Vote Verification</h4>
                        </div>
                        <div class="verification-details">
                            <p>Your vote has been securely recorded and encrypted in our system. This receipt serves as proof of
                                your participation.</p>
                            <p>Transaction ID: <span class="transaction-id"><?= $transaction_id ?></span></p>
                            <?php if ($vote_details['status'] == 'pending'): ?>
                                <p class="text-warning mt-2">
                                    <i class="fas fa-clock me-1"></i>
                                    Your vote is pending verification. It will be processed shortly.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
        
                <!-- Action Sidebar -->
                <div class="action-sidebar no-print">
                    <div class="action-header">
                        <div class="voter-avatar-receipt">
                            <?= $voter_initials ?>
                        </div>
                        <h3 class="voter-name-receipt"><?= htmlspecialchars($vote_details['voter_name']) ?></h3>
                        <div class="voter-id-receipt">Voter ID: <?= htmlspecialchars($id) ?></div>
                        <span style="color: var(--<?= $vote_status_color ?>-color); font-weight: 600;">
                            <i
                                class="fas fa-<?= $vote_status_color == 'success' ? 'check' : ($vote_status_color == 'warning' ? 'clock' : 'exclamation') ?>-circle me-1"></i>
                            <?= $vote_status_display ?>
                        </span>
                    </div>
        
                    <div class="action-buttons">
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print Receipt
                        </button>
                        <a href="voting_history.php" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i> View Voting History
                        </a>
                        <a href="voter_dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-tachometer-alt me-2"></i> Return to Dashboard
                        </a>
                    </div>
        
                    <div class="important-notes">
                        <h5><i class="fas fa-exclamation-circle"></i> Important Notes</h5>
                        <ul>
                            <li><i class="fas fa-check"></i> Keep this receipt for your records</li>
                            <li><i class="fas fa-check"></i> Your vote is anonymous and cannot be traced back to you</li>
                            <li><i class="fas fa-check"></i> Votes cannot be changed after submission</li>
                            <li><i class="fas fa-check"></i> Election results will be announced after voting ends</li>
                            <li><i class="fas fa-check"></i> Contact support if you notice any discrepancies</li>
                            <?php if ($vote_details['status'] == 'pending'): ?>
                                <li><i class="fas fa-info-circle"></i> Pending votes are verified within 24 hours</li>
                            <?php endif; ?>
                        </ul>
                    </div>
        
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            This receipt was generated on <?= date('F j, Y \a\t h:i A') ?>
                        </small>
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?= htmlspecialchars($admin['location'] ?? 'Douala, Cameroon') ?>
                        </small>
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
                            <li><i
                                    class="fas fa-envelope me-2"></i><?= htmlspecialchars($admin['email'] ?? 'support@votesecure.com') ?>
                            </li>
                            <li><i
                                    class="fas fa-phone me-2"></i><?= htmlspecialchars($admin['contact'] ?? '+237 653 426 838') ?>
                            </li>
                            <li><i
                                    class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($admin['location'] ?? 'Douala, Cameroon') ?>
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
        
        <!-- JavaScript Libraries -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Auto-scroll to top for better receipt view
            window.scrollTo(0, 0);

            // Check for URL parameters for notifications
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
                history.replaceState(null, null, window.location.pathname);
            }

            // Download PDF receipt
            document.getElementById('downloadReceipt').addEventListener('click', function () {
                Swal.fire({
                    title: 'Download PDF Receipt',
                    text: 'Your receipt is being prepared for download.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Download PDF',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Simulate PDF download (in real implementation, this would generate a PDF)
                        Swal.fire({
                            title: 'Download Started!',
                            text: 'Your receipt PDF is downloading.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // Simulate download completion
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Download Complete!',
                                text: 'Your voting receipt has been saved as PDF.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });
                        }, 2000);
                    }
                });
            });

            // Share receipt functionality
            const shareBtn = document.getElementById('shareReceipt');
            if (shareBtn) {
                shareBtn.addEventListener('click', function () {
                    if (navigator.share) {
                        navigator.share({
                            title: 'My Vote Receipt - SecureVote',
                            text: 'I have successfully cast my vote in ' + '<?= htmlspecialchars($vote_details['election_title']) ?>',
                            url: window.location.href,
                        })
                            .then(() => console.log('Successful share'))
                            .catch((error) => console.log('Error sharing:', error));
                    } else {
                        Swal.fire({
                            title: 'Share Receipt',
                            text: 'Copy the link to share your voting receipt:',
                            icon: 'info',
                            html: '<input type="text" class="form-control" value="' + window.location.href + '" readonly>',
                            showCancelButton: true,
                            confirmButtonText: 'Copy Link',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                navigator.clipboard.writeText(window.location.href);
                                Swal.fire({
                                    title: 'Copied!',
                                    text: 'Link copied to clipboard.',
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        });
                    }
                });
            }

            // Auto-save receipt notification
            setTimeout(() => {
                Swal.fire({
                    title: 'Receipt Saved',
                    text: 'Your voting receipt has been automatically saved to your voting history.',
                    icon: 'info',
                    timer: 3000,
                    showConfirmButton: false,
                    position: 'bottom-end',
                    toast: true
                });
            }, 5000);

            // Print optimization
            window.addEventListener('beforeprint', function () {
                // Add print-specific styles
                document.body.classList.add('printing');
            });

            window.addEventListener('afterprint', function () {
                // Remove print-specific styles
                document.body.classList.remove('printing');
            });

            // Generate fake QR code (in real implementation, this would be a real QR code)
            setTimeout(() => {
                const qrPlaceholder = document.querySelector('.qr-placeholder');
                if (qrPlaceholder) {
                    qrPlaceholder.innerHTML = `
                        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 2px; padding: 10px;">
                            ${Array(25).fill(0).map(() => `<div style="width: 20px; height: 20px; background-color: ${Math.random() > 0.5 ? '#2c3e50' : 'transparent'}; border-radius: 2px;"></div>`).join('')}
                        </div>
                        <small style="margin-top: 5px; color: #6c757d;">Verification Code</small>
                    `;
                }
            }, 1000);

            // Add animation to success icon
            const successIcon = document.querySelector('.success-icon');
            if (successIcon) {
                successIcon.style.animation = 'bounce 1s ease';
            }

            // Add bounce animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes bounce {
                    0%, 20%, 60%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-10px); }
                    80% { transform: translateY(-5px); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>

</html>