<?php
session_name("admin");
session_start();

// Check if admin is logged in
if(!isset($_SESSION['role']) || !isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../db_connection.php';
require_once 'app/model/voters.php';

// Get voter ID from request
$voter_id = $_GET['voter_id'];

if (empty($voter_id)) {
    echo json_encode(['success' => false, 'message' => 'Voter ID is required']);
    exit();
}

try {
    // Get voter data
    $voter = get_voter_by_id($conn, $voter_id);
    
    if ($voter) {
        // Remove sensitive data
        unset($voter['password']);
        
        echo json_encode([
            'success' => true,
            'voter' => $voter
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Voter not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn = null;
?>