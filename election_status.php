<?php
$elecs = get_all_elections($conn);
$current_time = time();

if ($elecs != 0) {
    foreach ($elecs as $elec) {
        $start_time = strtotime($elec['start_datetime']);
        $end_time   = strtotime($elec['end_datetime']);
        $cans       = get_election_candidates($conn, $elec['id']);

        if ($elec['status'] == "upcoming" && $current_time >= $start_time) {
            // If start time arrived with no candidates, revert to draft; otherwise activate
            $new_status = ($cans == 0) ? "draft" : "active";
            
            // Edge case: if end time has also already passed and it had candidates
            if ($new_status == "active" && $current_time >= $end_time) {
                $new_status = "completed";
            }

            $sql = "UPDATE elections SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$new_status, $elec['id']]);

        } elseif ($elec['status'] == "active" && $current_time >= $end_time) {
            $sql = "UPDATE elections SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute(["completed", $elec['id']]);
        }
    }
}
?>