<?php
// Get candidates by election ID
function get_candidates_by_election($conn, $election_id)
{
    $sql = "SELECT * FROM candidates WHERE election_id = ? ORDER BY total_votes DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$election_id]);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetchAll();
    } else {
        return 0;
    }
}

// Get candidate name by ID
function get_candidate_name_by_id($conn, $candidate_id)
{
    $sql = "SELECT v.full_name FROM candidates c 
            JOIN voters v ON c.voter_id = v.id 
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$candidate_id]);

    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch();
        return $result['full_name'];
    } else {
        return null;
    }
}

function add_candidate($conn, $data)
{
    $sql = "INSERT INTO candidates (voter_id, election_id, party_affiliation, biography, campaign_statement, status) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        $data['voter_id'],
        $data['election_id'],
        $data['party_affiliation'],
        $data['biography'],
        $data['campaign_statement'],
        $data['status']
    ]);
}

// Get candidate by voter and election
function get_candidate_by_voter_election($conn, $voter_id, $election_id)
{
    $sql = "SELECT * FROM candidates WHERE voter_id = ? AND election_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$voter_id, $election_id]);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch();
    } else {
        return false;
    }
}

// Update candidate status
function update_candidate_status($conn, $candidate_id, $status)
{
    $sql = "UPDATE candidates SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$status, $candidate_id]);
}

// Delete candidate
function delete_candidate($conn, $candidate_id)
{
    $sql = "DELETE FROM candidates WHERE id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$candidate_id]);
}

// Get voter by ID (for candidate management)
