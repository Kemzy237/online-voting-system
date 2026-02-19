<?php
// Get recent votes by election
function get_recent_votes_by_election($conn, $election_id)
{
    $sql = "SELECT v.* FROM votes v 
            WHERE v.election_id = ? 
            ORDER BY v.vote_timestamp";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$election_id]);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetchAll();
    } else {
        return 0;
    }
}

function get_election_votes($conn, $id){
    $sql = "SELECT * FROM votes WHERE election_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    if($stmt->rowCount() > 0){
        $votes =  $stmt->fetchAll();
    }else $votes = 0;

    return $votes;
}

function get_candidate_votes($conn, $data){
    $sql = "SELECT * FROM votes WHERE election_id = ? AND candidate_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);
    if ($stmt->rowCount() > 0) {
        $votes = $stmt->fetchAll();
    } else
        $votes = 0;

    return $votes;
}

function get_number_of_votes($conn){
    $sql = "SELECT * FROM votes";
    $stmt = $conn->prepare($sql);
    $stmt->execute([]);
    if($stmt->rowCount() > 0){
        $result = $stmt->fetchAll();
        $votes = count($result);
    } else $votes = 0;
    return $votes;
}