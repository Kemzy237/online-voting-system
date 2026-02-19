<?php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) && !isset($_SESSION['id'])) {
    die("Access denied");
}

include "../db_connection.php";

$format = $_GET['format'] ?? 'pdf';

// Set timezone
date_default_timezone_set('Africa/Douala');

// Initialize PDO connection


// Get overall statistics
$stats = [];
$queries = [
    'total_elections' => "SELECT COUNT(*) as count FROM elections",
    'active_elections' => "SELECT COUNT(*) as count FROM elections WHERE status = 'active'",
    'completed_elections' => "SELECT COUNT(*) as count FROM elections WHERE status = 'completed'",
    'total_voters' => "SELECT COUNT(*) as count FROM voters",
    'verified_voters' => "SELECT COUNT(*) as count FROM voters WHERE status = 'verified'",
    'total_votes' => "SELECT COUNT(*) as count FROM votes WHERE status = 'verified'",
    'total_candidates' => "SELECT COUNT(*) as count FROM candidates"
];

foreach ($queries as $key => $query) {
    $stmt = $conn->query($query);
    $stats[$key] = $stmt->fetch()['count'];
}

// Get recent elections
$recentElections = $conn->query("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidate_count,
           COUNT(DISTINCT v.id) as vote_count
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN votes v ON e.id = v.election_id AND v.status = 'verified'
    GROUP BY e.id
    ORDER BY e.created_at DESC
    LIMIT 10
")->fetchAll();

// Get top candidates
$topCandidates = $conn->query("
    SELECT 
        v.full_name as candidate_name,
        c.party_affiliation,
        e.title as election_title,
        COUNT(vt.id) as vote_count
    FROM candidates c
    JOIN voters v ON c.voter_id = v.id
    JOIN elections e ON c.election_id = e.id
    LEFT JOIN votes vt ON c.id = vt.candidate_id AND vt.status = 'verified'
    WHERE e.status = 'completed'
    GROUP BY c.id
    ORDER BY vote_count DESC
    LIMIT 10
")->fetchAll();

// Generate filename
$filename = 'analytics_report_' . date('Y-m-d_H-i-s') . '.' . $format;

if ($format === 'csv') {
    // Export as CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Write overall stats
    fputcsv($output, ['Overall Statistics', '']);
    fputcsv($output, ['Metric', 'Value']);
    foreach ($stats as $key => $value) {
        $label = str_replace('_', ' ', ucfirst($key));
        fputcsv($output, [$label, $value]);
    }
    fputcsv($output, []); // Empty row

    // Write recent elections
    fputcsv($output, ['Recent Elections (Last 10)', '']);
    fputcsv($output, ['ID', 'Title', 'Status', 'Candidates', 'Votes', 'Created']);
    foreach ($recentElections as $election) {
        fputcsv($output, [
            $election['id'],
            $election['title'],
            $election['status'],
            $election['candidate_count'],
            $election['vote_count'],
            $election['created_at']
        ]);
    }
    fputcsv($output, []); // Empty row

    // Write top candidates
    fputcsv($output, ['Top Candidates (Last 10)', '']);
    fputcsv($output, ['Candidate Name', 'Party', 'Election', 'Votes']);
    foreach ($topCandidates as $candidate) {
        fputcsv($output, [
            $candidate['candidate_name'],
            $candidate['party_affiliation'] ?? 'Independent',
            $candidate['election_title'],
            $candidate['vote_count']
        ]);
    }

    fclose($output);

} else {
    // Export as PDF would require a library like TCPDF or Dompdf
    // For simplicity, we'll redirect to analytics page with print dialog
    header("Location: ../analytics.php?print=true");
}
?>