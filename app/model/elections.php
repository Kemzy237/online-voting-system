<?php
// Get all elections
function get_all_elections($conn)
{
    $sql = "SELECT * FROM elections ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        return $stmt->fetchAll();
    } else {
        return 0;
    }
}

// Get active elections count
function get_active_elections($conn)
{
    $sql = "SELECT COUNT(*) as count FROM elections WHERE status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'];
}

// Get completed elections count
function get_completed_elections($conn)
{
    $sql = "SELECT COUNT(*) as count FROM elections WHERE status = 'completed'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'];
}

// Get upcoming elections count
function get_upcoming_elections($conn)
{
    $sql = "SELECT COUNT(*) as count FROM elections WHERE status = 'upcoming'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'];
}

// Get election by ID
function get_election_by_id($conn, $election_id)
{
    $sql = "SELECT * FROM elections WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$election_id]);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch();
    } else {
        return 0;
    }
}

// Create new election
function create_election($conn, $data)
{
    $sql = "INSERT INTO elections (title, description, start_datetime, end_datetime, status) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($data);
}

// Update election
function update_election($conn, $data)
{
    $sql = "UPDATE elections SET 
            title = ?, 
            description = ?, 
            start_datetime = ?, 
            end_datetime = ?, 
            status = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([
        $data['title'],
        $data['description'],
        $data['start_datetime'],
        $data['end_datetime'],
        $data['status'],
        $data['id']
    ]);
}

// Delete election
function delete_election($conn, $election_id)
{
    // Delete associated candidates and votes (cascade in database)
    $sql = "DELETE FROM elections WHERE id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$election_id]);
}

// Get election statistics
function get_election_statistics($conn, $election_id)
{
    $sql = "SELECT 
            e.*,
            COUNT(DISTINCT c.id) as total_candidates,
            COUNT(DISTINCT v.id) as total_voters_registered,
            COUNT(DISTINCT vt.id) as total_votes_cast
            FROM elections e
            LEFT JOIN candidates c ON e.id = c.election_id
            LEFT JOIN voters v ON v.status = 'verified'
            LEFT JOIN votes vt ON e.id = vt.election_id AND vt.status = 'verified'
            WHERE e.id = ?
            GROUP BY e.id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$election_id]);

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch();
    } else {
        return 0;
    }
}

function get_election_candidates($conn, $id){
    $sql = "SELECT * FROM candidates WHERE election_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    if($stmt->rowCount() > 0){
        $candidates = $stmt->fetchAll();
    }else $candidates = 0;
    return $candidates;
}

?>