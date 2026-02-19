<?php
session_name('voter');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

include "db_connection.php";

$voter_id = $_SESSION['id'];
$vote_id = isset($_GET['vote_id']) ? (int) $_GET['vote_id'] : 0;

if (!$vote_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote ID']);
    exit();
}

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
                c.party_affiliation,
                c.biography,
                c.campaign_statement,
                c.profile_image,
                c.status as candidate_status,
                vr.full_name as candidate_name,
                vr.email as candidate_email,
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

    $vote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vote) {
        echo json_encode(['success' => true, 'vote' => $vote]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vote not found']);
    }

} catch (PDOException $e) {
    error_log("Error fetching vote details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}